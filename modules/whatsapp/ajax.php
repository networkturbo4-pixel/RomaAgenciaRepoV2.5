<?php
require_once '../../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_status':
        $stmt = $db->query("SELECT status FROM wa_sessions ORDER BY id DESC LIMIT 1");
        $session = $stmt->fetch();
        echo json_encode(['success' => true, 'status' => $session ? $session['status'] : 'disconnected']);
        break;

    case 'get_contacts':
        $userId = $_SESSION['user_id'];
        $stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
        $stmtRole->execute([$userId]);
        $user = $stmtRole->fetch();
        $isAdmin = ($user && $user['role_id'] == 1);

        $whereClause = "1=1";
        if (!$isAdmin) {
            $whereClause = "(SELECT a.user_id FROM wa_chat_assignments a WHERE a.contact_id = c.id LIMIT 1) IS NULL 
                            OR 
                            (SELECT a.user_id FROM wa_chat_assignments a WHERE a.contact_id = c.id LIMIT 1) = $userId";
        }

        $stmt = $db->query("
            SELECT c.*, 
                   (SELECT body FROM wa_messages m WHERE m.contact_id = c.id ORDER BY timestamp DESC LIMIT 1) as last_message,
                   (SELECT a.user_id FROM wa_chat_assignments a WHERE a.contact_id = c.id LIMIT 1) as assigned_user_id
            FROM wa_contacts c 
            WHERE $whereClause
            ORDER BY c.last_message_at DESC 
            LIMIT 500
        ");
        $contacts = $stmt->fetchAll();
        
        // Fetch labels for contacts
        $labelsStmt = $db->query("
            SELECT cl.contact_id, l.id, l.name, l.color 
            FROM wa_contact_labels cl 
            JOIN wa_labels l ON cl.label_id = l.id
        ");
        $labels = $labelsStmt->fetchAll();
        
        $contactsWithLabels = array_map(function($c) use ($labels) {
            $c['labels'] = array_filter($labels, function($l) use ($c) {
                return $l['contact_id'] == $c['id'];
            });
            $c['labels'] = array_values($c['labels']);
            return $c;
        }, $contacts);
        
        echo json_encode(['success' => true, 'contacts' => $contactsWithLabels]);
        break;

    case 'get_messages':
        $contactId = (int)($_POST['contact_id'] ?? 0);
        $stmt = $db->prepare("
            SELECT m.*, u.name as sender_name 
            FROM wa_messages m 
            LEFT JOIN users u ON m.sent_by_user = u.id 
            WHERE m.contact_id = ? 
            ORDER BY m.timestamp ASC 
            LIMIT 100
        ");
        $stmt->execute([$contactId]);
        echo json_encode(['success' => true, 'messages' => $stmt->fetchAll()]);
        break;

    case 'assign_chat':
        $contactId = (int)($_POST['contact_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        
        if ($userId > 0) {
            $stmt = $db->prepare("INSERT INTO wa_chat_assignments (contact_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)");
            $stmt->execute([$contactId, $userId]);
        } else {
            $stmt = $db->prepare("DELETE FROM wa_chat_assignments WHERE contact_id = ?");
            $stmt->execute([$contactId]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'get_all_labels':
        $stmt = $db->query("SELECT * FROM wa_labels ORDER BY name ASC");
        echo json_encode(['success' => true, 'labels' => $stmt->fetchAll()]);
        break;

    case 'toggle_label':
        $contactId = (int)($_POST['contact_id'] ?? 0);
        $labelId = (int)($_POST['label_id'] ?? 0);
        
        // Check if exists
        $check = $db->prepare("SELECT * FROM wa_contact_labels WHERE contact_id = ? AND label_id = ?");
        $check->execute([$contactId, $labelId]);
        
        if ($check->rowCount() > 0) {
            $stmt = $db->prepare("DELETE FROM wa_contact_labels WHERE contact_id = ? AND label_id = ?");
            $stmt->execute([$contactId, $labelId]);
            $added = false;
        } else {
            $stmt = $db->prepare("INSERT INTO wa_contact_labels (contact_id, label_id) VALUES (?, ?)");
            $stmt->execute([$contactId, $labelId]);
            $added = true;
        }
        echo json_encode(['success' => true, 'added' => $added]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
