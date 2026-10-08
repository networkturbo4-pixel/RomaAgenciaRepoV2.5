<?php
// modules/quotes/ajax_save_quote.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    // Debug: log session state to help diagnose the issue
    $debug_info = [
        'session_id' => session_id(),
        'session_status' => session_status(),
        'session_data' => $_SESSION,
        'cookie_set' => isset($_COOKIE[session_name()]),
        'cookie_name' => session_name(),
        'cookie_value' => $_COOKIE[session_name()] ?? 'NOT SET',
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'timestamp' => date('Y-m-d H:i:s'),
        'post_keys' => array_keys($_POST),
    ];
    $log_dir = __DIR__ . '/../../logs';
    if (!is_dir($log_dir)) @mkdir($log_dir, 0777, true);
    file_put_contents($log_dir . '/quote_save_debug.log', json_encode($debug_info, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);
    
    echo json_encode([
        'success' => false, 
        'message' => 'No autorizado - Sesión no encontrada. Session ID: ' . session_id() . ', Cookie: ' . (isset($_COOKIE[session_name()]) ? 'presente' : 'ausente')
    ]);
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Self-healing migration: check and auto-add customization columns if missing
$has_custom_cols = true;
try {
    $existing_cols = $db->query("SHOW COLUMNS FROM quotes")->fetchAll(PDO::FETCH_COLUMN);
    $missing_cols = [];
    if (!in_array('client_company', $existing_cols)) $missing_cols[] = "ADD COLUMN `client_company` VARCHAR(255) NULL AFTER `client_id`";
    if (!in_array('theme_color', $existing_cols)) $missing_cols[] = "ADD COLUMN `theme_color` VARCHAR(50) DEFAULT 'corporate-blue'";
    if (!in_array('cover_image', $existing_cols)) $missing_cols[] = "ADD COLUMN `cover_image` VARCHAR(255) NULL";
    if (!in_array('cover_gradient', $existing_cols)) $missing_cols[] = "ADD COLUMN `cover_gradient` VARCHAR(100) DEFAULT 'mesh-blue'";
    if (!in_array('hide_prices', $existing_cols)) $missing_cols[] = "ADD COLUMN `hide_prices` TINYINT(1) DEFAULT 0";
    if (!in_array('show_gantt', $existing_cols)) $missing_cols[] = "ADD COLUMN `show_gantt` TINYINT(1) DEFAULT 1";
    
    if (!empty($missing_cols)) {
        $db->exec("ALTER TABLE `quotes` " . implode(', ', $missing_cols));
    }
} catch (Exception $colEx) {
    // If ALTER TABLE fails due to user permissions, check if columns actually exist
    try {
        $checkCols = $db->query("SHOW COLUMNS FROM quotes LIKE 'theme_color'")->fetch();
        $has_custom_cols = !empty($checkCols);
    } catch(Exception $e2) {
        $has_custom_cols = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $quote_id = isset($_POST['quote_id']) ? (int)$_POST['quote_id'] : 0;
        $client_name = trim($_POST['client_name'] ?? '');
        $client_company = trim($_POST['client_company'] ?? '');
        $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
        $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+15 days'));
        $currency = $_POST['currency'] ?? 'USD';
        $status = $_POST['status'] ?? 'Borrador';
        $allowed_html = '<strong><em><b><i><u><br><ul><ol><li><p><span><div><mark><font>';
        $notes = isset($_POST['notes']) ? strip_tags($_POST['notes'], $allowed_html) : '';
        $terms_conditions = isset($_POST['terms_conditions']) ? strip_tags($_POST['terms_conditions'], $allowed_html) : '';
        $show_payment_methods = isset($_POST['show_payment_methods']) ? 1 : 0;
        $payment_methods_text = $_POST['payment_methods_text'] ?? '';
        $theme_color = !empty($_POST['theme_color']) ? trim($_POST['theme_color']) : 'corporate-blue';
        $cover_image = !empty($_POST['cover_image']) ? trim($_POST['cover_image']) : null;
        $cover_gradient = !empty($_POST['cover_gradient']) ? trim($_POST['cover_gradient']) : 'mesh-blue';
        $hide_prices = isset($_POST['hide_prices']) && ($_POST['hide_prices'] == '1' || $_POST['hide_prices'] === 'true' || $_POST['hide_prices'] === true) ? 1 : 0;
        $show_gantt = isset($_POST['show_gantt']) && ($_POST['show_gantt'] == '0' || $_POST['show_gantt'] === 'false' || $_POST['show_gantt'] === false) ? 0 : 1;
        
        if (empty($client_name)) {
            throw new Exception('El cliente es obligatorio.');
        }

        // Find or create client
        $post_client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
        $client_id = 0;
        if ($post_client_id > 0) {
            $stmtCheck = $db->prepare("SELECT id, name FROM clients WHERE id = ?");
            $stmtCheck->execute([$post_client_id]);
            $client_row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($client_row) {
                $client_id = $client_row['id'];
            }
        }

        if (!$client_id) {
            $stmtFindClient = $db->prepare("SELECT id FROM clients WHERE name = ?");
            $stmtFindClient->execute([$client_name]);
            $client = $stmtFindClient->fetch(PDO::FETCH_ASSOC);

            if ($client) {
                $client_id = $client['id'];
            } else {
                $stmtInsertClient = $db->prepare("INSERT INTO clients (name) VALUES (?)");
                $stmtInsertClient->execute([$client_name]);
                $client_id = $db->lastInsertId();
            }
        }

        // Auto-resolve company from client_brands if not provided
        if (empty($client_company) && $client_id > 0) {
            try {
                $stmtBr = $db->prepare("SELECT name FROM client_brands WHERE client_id = ? ORDER BY id ASC LIMIT 1");
                $stmtBr->execute([$client_id]);
                $client_company = $stmtBr->fetchColumn() ?: '';
            } catch(Exception $e) {}
        }
        
        $subtotal = 0;
        $tax = 0;
        $total = 0;
        
        $items = isset($_POST['items']) ? $_POST['items'] : [];
        // calculate totals based on items (or take from POST if sent, but better to calculate here)
        foreach ($items as $item) {
            $qty = isset($item['quantity']) ? (float)$item['quantity'] : 0;
            $price = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
            $item_total = $qty * $price;
            $subtotal += $item_total;
        }
        
        // Assume 18% tax or no tax? Let's assume passed tax or simple logic
        // For now, let's take tax from POST or assume 0 for simplicity, or 18% if applied
        $tax_rate = isset($_POST['tax_rate']) ? (float)$_POST['tax_rate'] : 0;
        $tax = $subtotal * ($tax_rate / 100);
        $total = $subtotal + $tax;

        if ($quote_id == 0) {
            $token = bin2hex(random_bytes(6));
            if ($has_custom_cols) {
                $stmt = $db->prepare("INSERT INTO quotes (client_id, client_company, issue_date, due_date, currency, status, subtotal, tax, total, notes, terms_conditions, show_payment_methods, payment_methods_text, public_token, theme_color, cover_image, cover_gradient, hide_prices, show_gantt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$client_id, $client_company, $issue_date, $due_date, $currency, $status, $subtotal, $tax, $total, $notes, $terms_conditions, $show_payment_methods, $payment_methods_text, $token, $theme_color, $cover_image, $cover_gradient, $hide_prices, $show_gantt]);
            } else {
                $stmt = $db->prepare("INSERT INTO quotes (client_id, client_company, issue_date, due_date, currency, status, subtotal, tax, total, notes, terms_conditions, show_payment_methods, payment_methods_text, public_token, hide_prices, show_gantt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$client_id, $client_company, $issue_date, $due_date, $currency, $status, $subtotal, $tax, $total, $notes, $terms_conditions, $show_payment_methods, $payment_methods_text, $token, $hide_prices, $show_gantt]);
            }
            $quote_id = $db->lastInsertId();
        } else {
            if ($has_custom_cols) {
                $stmt = $db->prepare("UPDATE quotes SET client_id=?, client_company=?, issue_date=?, due_date=?, currency=?, status=?, subtotal=?, tax=?, total=?, notes=?, terms_conditions=?, show_payment_methods=?, payment_methods_text=?, theme_color=?, cover_image=?, cover_gradient=?, hide_prices=?, show_gantt=? WHERE id=?");
                $stmt->execute([$client_id, $client_company, $issue_date, $due_date, $currency, $status, $subtotal, $tax, $total, $notes, $terms_conditions, $show_payment_methods, $payment_methods_text, $theme_color, $cover_image, $cover_gradient, $hide_prices, $show_gantt, $quote_id]);
            } else {
                $stmt = $db->prepare("UPDATE quotes SET client_id=?, client_company=?, issue_date=?, due_date=?, currency=?, status=?, subtotal=?, tax=?, total=?, notes=?, terms_conditions=?, show_payment_methods=?, payment_methods_text=?, hide_prices=?, show_gantt=? WHERE id=?");
                $stmt->execute([$client_id, $client_company, $issue_date, $due_date, $currency, $status, $subtotal, $tax, $total, $notes, $terms_conditions, $show_payment_methods, $payment_methods_text, $hide_prices, $show_gantt, $quote_id]);
            }
            
            // Delete old items and tasks to re-insert
            $db->prepare("DELETE FROM quote_items WHERE quote_id=?")->execute([$quote_id]);
            $db->prepare("DELETE FROM quote_gantt_tasks WHERE quote_id=?")->execute([$quote_id]);
        }

        // Insert Items
        if (!empty($items)) {
            $stmtItem = $db->prepare("INSERT INTO quote_items (quote_id, service_id, description, quantity, unit_price, discount, total, icon, gantt_start_date, gantt_duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtGantt = $db->prepare("INSERT INTO quote_gantt_tasks (quote_id, task_name, start_date, end_date, progress, color) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($items as $item) {
                $s_id = !empty($item['service_id']) ? $item['service_id'] : null;
                $desc = $item['description'] ?? '';
                $qty = isset($item['quantity']) ? (float)$item['quantity'] : 1;
                $price = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
                $disc = isset($item['discount']) ? (float)$item['discount'] : 0;
                $icon = $item['icon'] ?? '';
                $g_start = !empty($item['gantt_start_date']) ? $item['gantt_start_date'] : null;
                $g_dur = isset($item['gantt_duration']) ? (int)$item['gantt_duration'] : 0;
                
                $item_total = ($qty * $price) - $disc;
                $stmtItem->execute([$quote_id, $s_id, $desc, $qty, $price, $disc, $item_total, $icon, $g_start, $g_dur]);
                
                // Keep tasks table populated for backwards compatibility if needed, or simply for rendering the gantt chart later
                if ($g_start && $g_dur > 0) {
                    // Extract plain text from HTML description for task name
                    $plain_desc = strip_tags(str_replace(['<br>', '<br/>', '<p>'], ' ', $desc));
                    $task_name = substr(trim($plain_desc), 0, 50); // limit length
                    if(empty($task_name)) $task_name = "Tarea";
                    
                    $end_date = date('Y-m-d', strtotime($g_start . " + {$g_dur} days"));
                    $stmtGantt->execute([$quote_id, $task_name, $g_start, $end_date, 0, '#3498db']);
                }
            }
        }

        // Insert custom gantt tasks if passed from Gantt Constructor and not already inserted
        $gantt_tasks = isset($_POST['gantt_tasks']) && is_array($_POST['gantt_tasks']) ? $_POST['gantt_tasks'] : [];
        if (!empty($gantt_tasks)) {
            $stmtGanttCustom = $db->prepare("INSERT INTO quote_gantt_tasks (quote_id, task_name, start_date, end_date, progress, color) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($gantt_tasks as $gt) {
                $t_name = !empty($gt['task_name']) ? trim($gt['task_name']) : 'Fase';
                $t_start = !empty($gt['start_date']) ? $gt['start_date'] : date('Y-m-d');
                $t_end = !empty($gt['end_date']) ? $gt['end_date'] : $t_start;
                $t_prog = isset($gt['progress']) ? (int)$gt['progress'] : 0;
                $t_color = !empty($gt['color']) ? $gt['color'] : '#3498db';
                $stmtGanttCustom->execute([$quote_id, $t_name, $t_start, $t_end, $t_prog, $t_color]);
            }
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Cotización guardada exitosamente.', 'quote_id' => $quote_id]);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
