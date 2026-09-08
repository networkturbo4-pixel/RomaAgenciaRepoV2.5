<?php
// ajax/notifications.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/NotificationHelper.php';

$database = new Database();
$db = $database->getConnection();
$userId = (int)$_SESSION['user_id'];

$action = $_REQUEST['action'] ?? 'get_notifications';

try {
    switch ($action) {
        case 'get_unread_count':
            $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            $count = (int)$stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'count' => $count
            ]);
            break;

        case 'get_notifications':
            $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;

            // Obtener notificaciones
            $stmt = $db->prepare("
                SELECT id, user_id, title, message, link, type, icon, is_read, created_at 
                FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Contador no leídas
            $countStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $countStmt->execute([$userId]);
            $unreadCount = (int)$countStmt->fetchColumn();

            $notifications = array_map(function($row) {
                return [
                    'id'         => (int)$row['id'],
                    'title'      => htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'message'    => htmlspecialchars($row['message'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'link'       => $row['link'] ?: '#',
                    'type'       => $row['type'] ?: 'general',
                    'icon'       => $row['icon'] ?: 'ph-bell',
                    'is_read'    => (int)$row['is_read'],
                    'created_at' => $row['created_at'],
                    'time_ago'   => NotificationHelper::formatTimeAgo($row['created_at'])
                ];
            }, $rows);

            echo json_encode([
                'success'       => true,
                'notifications' => $notifications,
                'unread_count'  => $unreadCount
            ]);
            break;

        case 'mark_as_read':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
            }

            $countStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $countStmt->execute([$userId]);
            $unreadCount = (int)$countStmt->fetchColumn();

            echo json_encode([
                'success'      => true,
                'unread_count' => $unreadCount
            ]);
            break;

        case 'mark_all_read':
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);

            echo json_encode([
                'success'      => true,
                'unread_count' => 0
            ]);
            break;

        case 'delete':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id > 0) {
                $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
            }

            $countStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $countStmt->execute([$userId]);
            $unreadCount = (int)$countStmt->fetchColumn();

            echo json_encode([
                'success'      => true,
                'unread_count' => $unreadCount
            ]);
            break;

        case 'send_test':
            NotificationHelper::send([
                'user_id' => $userId,
                'title'   => 'Notificación de Prueba 🔔',
                'message' => '¡Excelente! El sistema de notificaciones en tiempo real funciona correctamente.',
                'link'    => 'index.php',
                'type'    => 'general',
                'icon'    => 'ph-bell-ringing'
            ], $db);

            $countStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $countStmt->execute([$userId]);
            $unreadCount = (int)$countStmt->fetchColumn();

            echo json_encode([
                'success'      => true,
                'unread_count' => $unreadCount,
                'message'      => 'Notificación de prueba enviada con éxito'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
