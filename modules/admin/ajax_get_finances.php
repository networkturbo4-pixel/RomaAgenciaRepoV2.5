<?php
// modules/admin/ajax_get_finances.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$db = (new Database())->getConnection();

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Validate month format
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de mes inválido. Use YYYY-MM.']);
    exit();
}

try {
    // 1. Monthly closing record
    $stmtClosing = $db->prepare("SELECT * FROM finance_monthly_closings WHERE period = ?");
    $stmtClosing->execute([$month]);
    $closing = $stmtClosing->fetch(PDO::FETCH_ASSOC);
    if (!$closing) {
        $closing = [
            'period' => $month,
            'status' => 'abierto',
            'total_incomes' => 0,
            'total_expenses' => 0,
            'utilidad' => 0,
            'monto_repartido' => 0,
            'closed_by' => null,
            'closed_at' => null
        ];
    }

    // 2. Incomes for the month
    $stmtIncomes = $db->prepare("SELECT * FROM finance_incomes WHERE DATE_FORMAT(fecha_pago, '%Y-%m') = ? ORDER BY fecha_pago DESC");
    $stmtIncomes->execute([$month]);
    $incomes = $stmtIncomes->fetchAll(PDO::FETCH_ASSOC);

    // 3. Expenses for the month
    $stmtExpenses = $db->prepare("SELECT * FROM finance_expenses WHERE DATE_FORMAT(fecha, '%Y-%m') = ? ORDER BY fecha DESC");
    $stmtExpenses->execute([$month]);
    $expenses = $stmtExpenses->fetchAll(PDO::FETCH_ASSOC);

    // 4. Recurring templates
    $stmtRecurring = $db->query("SELECT * FROM finance_recurring_expenses ORDER BY nombre_gasto ASC");
    $recurring_templates = $stmtRecurring->fetchAll(PDO::FETCH_ASSOC);

    // 5. History for charts (last 12 months)
    $history = [];
    $currentDate = new DateTime($month . '-01');
    for ($i = 11; $i >= 0; $i--) {
        $d = clone $currentDate;
        $d->modify("-{$i} months");
        $p = $d->format('Y-m');

        $stmtHInc = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM finance_incomes WHERE DATE_FORMAT(fecha_pago, '%Y-%m') = ?");
        $stmtHInc->execute([$p]);
        $totalInc = (float)$stmtHInc->fetchColumn();

        $stmtHExp = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM finance_expenses WHERE DATE_FORMAT(fecha, '%Y-%m') = ?");
        $stmtHExp->execute([$p]);
        $totalExp = (float)$stmtHExp->fetchColumn();

        $history[] = [
            'period' => $p,
            'total_incomes' => $totalInc,
            'total_expenses' => $totalExp,
            'utilidad' => $totalInc - $totalExp
        ];
    }

    // 6. Closings history
    $stmtClosings = $db->query("
        SELECT mc.*, u.name as closed_by_name 
        FROM finance_monthly_closings mc 
        LEFT JOIN users u ON mc.closed_by = u.id 
        WHERE mc.`status` = 'cerrado'
        ORDER BY mc.period DESC
    ");
    $closings_history = $stmtClosings->fetchAll(PDO::FETCH_ASSOC);

    // 7. Categories breakdown for the selected month
    $stmtCategories = $db->prepare("
        SELECT categoria, COALESCE(SUM(monto), 0) as total 
        FROM finance_expenses 
        WHERE DATE_FORMAT(fecha, '%Y-%m') = ? 
        GROUP BY categoria 
        ORDER BY total DESC
    ");
    $stmtCategories->execute([$month]);
    $categories_breakdown = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

    // 8. Payment notes (for income creation reference)
    $stmtNotes = $db->query("
        SELECT id, note_code, client_name, company_name, total, status, 
               services_json, created_at
        FROM payment_notes
        ORDER BY created_at DESC
    ");
    $payment_notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

    // Parse services to get a summary description
    foreach ($payment_notes as &$note) {
        $services = json_decode($note['services_json'], true);
        $serviceNames = [];
        if (is_array($services)) {
            foreach ($services as $svc) {
                $serviceNames[] = $svc['name'] ?? $svc['servicio'] ?? '';
            }
        }
        $note['services_summary'] = implode(', ', array_filter($serviceNames));
        unset($note['services_json']); // No enviar JSON pesado al frontend
    }
    unset($note);

    echo json_encode([
        'success' => true,
        'closing' => $closing,
        'incomes' => $incomes,
        'expenses' => $expenses,
        'recurring_templates' => $recurring_templates,
        'history' => $history,
        'closings_history' => $closings_history,
        'categories_breakdown' => $categories_breakdown,
        'payment_notes' => $payment_notes
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
