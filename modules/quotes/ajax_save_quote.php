<?php
// modules/quotes/ajax_save_quote.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $quote_id = isset($_POST['quote_id']) ? (int)$_POST['quote_id'] : 0;
        $client_name = trim($_POST['client_name'] ?? '');
        $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
        $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+15 days'));
        $currency = $_POST['currency'] ?? 'USD';
        $status = $_POST['status'] ?? 'Borrador';
        $notes = $_POST['notes'] ?? '';
        $terms_conditions = $_POST['terms_conditions'] ?? '';
        $show_payment_methods = isset($_POST['show_payment_methods']) ? 1 : 0;
        $payment_methods_text = $_POST['payment_methods_text'] ?? '';
        
        if (empty($client_name)) {
            throw new Exception('El cliente es obligatorio.');
        }

        // Find or create client
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
            $token = bin2hex(random_bytes(16));
            $stmt = $db->prepare("INSERT INTO quotes (client_id, issue_date, due_date, currency, status, subtotal, tax, total, notes, terms_conditions, show_payment_methods, payment_methods_text, public_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$client_id, $issue_date, $due_date, $currency, $status, $subtotal, $tax, $total, $notes, $terms_conditions, $show_payment_methods, $payment_methods_text, $token]);
            $quote_id = $db->lastInsertId();
        } else {
            $stmt = $db->prepare("UPDATE quotes SET client_id=?, issue_date=?, due_date=?, currency=?, status=?, subtotal=?, tax=?, total=?, notes=?, terms_conditions=?, show_payment_methods=?, payment_methods_text=? WHERE id=?");
            $stmt->execute([$client_id, $issue_date, $due_date, $currency, $status, $subtotal, $tax, $total, $notes, $terms_conditions, $show_payment_methods, $payment_methods_text, $quote_id]);
            
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
