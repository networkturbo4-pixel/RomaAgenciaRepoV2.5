<?php
// ajax/notifications_poll.php
// Long-polling ligero: mantiene la petición abierta hasta ~25 s y responde en cuanto hay una notificación nueva.
// Uso: ?since=<ultimo_id>&timeout=25&types=task,meeting   (since=-1 => solo devuelve el último id, sin listar)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
// Liberar el bloqueo de sesión para no frenar el resto de peticiones del usuario
session_write_close();

require_once __DIR__ . '/../config/database.php';

if (function_exists('ob_gzhandler') && !ini_get('zlib.output_compression')) {
    ob_start('ob_gzhandler');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$since   = isset($_GET['since']) ? (int)$_GET['since'] : -1;
$timeout = isset($_GET['timeout']) ? max(0, min(28, (int)$_GET['timeout'])) : 25;
$types   = [];
if (!empty($_GET['types'])) {
    foreach (explode(',', (string)$_GET['types']) as $t) {
        $t = preg_replace('/[^a-z_]/i', '', trim($t));
        if ($t !== '') $types[] = $t;
    }
}

ignore_user_abort(false);
set_time_limit($timeout + 10);

try {
    $database = new Database();
    $db = $database->getConnection();

    // Respuesta mínima: solo el último id (sincronización inicial)
    if ($since < 0) {
        $stmt = $db->prepare("SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'last_id' => (int)$stmt->fetchColumn(), 'notifications' => []]);
        exit;
    }

    $sql = "SELECT id, title, message, link, type FROM notifications WHERE user_id = ? AND id > ? AND is_read = 0";
    $params = [$userId, $since];
    if (!empty($types)) {
        $sql .= " AND type IN (" . implode(',', array_fill(0, count($types), '?')) . ")";
        $params = array_merge($params, $types);
    }
    $sql .= " ORDER BY id ASC LIMIT 20";
    $stmt = $db->prepare($sql);

    $maxIdStmt = $db->prepare("SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = ?");

    $deadline = microtime(true) + $timeout;
    do {
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $out = array_map(function ($r) {
                return [
                    'id'      => (int)$r['id'],
                    'title'   => $r['title'] ?? '',
                    'message' => $r['message'] ?? '',
                    'link'    => $r['link'] ?: 'index.php',
                    'type'    => $r['type'] ?: 'general',
                ];
            }, $rows);
            echo json_encode(['success' => true, 'last_id' => end($out)['id'], 'notifications' => $out]);
            exit;
        }
        if (connection_aborted()) exit;
        if (microtime(true) >= $deadline) break;
        sleep(2);
    } while (true);

    // Sin novedades. Si el filtro de tipos dejó fuera avisos nuevos, avanzar el cursor para no reconsultarlos
    $newCursor = $since;
    if (!empty($types)) {
        $maxIdStmt->execute([$userId]);
        $newCursor = max($since, (int)$maxIdStmt->fetchColumn());
    }
    echo json_encode(['success' => true, 'last_id' => $newCursor, 'notifications' => []]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}
