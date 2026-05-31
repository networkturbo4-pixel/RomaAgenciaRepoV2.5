<?php
// modules/admin/ajax_generate_recurring.php
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

$data = json_decode(file_get_contents('php://input'), true);
$month = $data['month'] ?? '';

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de mes inválido. Use YYYY-MM.']);
    exit();
}

try {
    // Check if the month is closed
    $stmtCheck = $db->prepare("SELECT `status` FROM finance_monthly_closings WHERE period = ?");
    $stmtCheck->execute([$month]);
    $monthStatus = $stmtCheck->fetchColumn();

    if ($monthStatus === 'cerrado') {
        echo json_encode(['success' => false, 'message' => 'No se puede generar gastos. El mes ' . $month . ' está cerrado.']);
        exit();
    }

    // Get all recurring templates
    $stmtTemplates = $db->query("SELECT * FROM finance_recurring_expenses");
    $templates = $stmtTemplates->fetchAll(PDO::FETCH_ASSOC);

    $generated = 0;
    $year = intval(substr($month, 0, 4));
    $mon = intval(substr($month, 5, 2));
    // Get the last valid day of the month
    $lastDay = intval(date('t', mktime(0, 0, 0, $mon, 1, $year)));

    foreach ($templates as $template) {
        // Check if an expense already exists for this template in this month
        $stmtExists = $db->prepare("SELECT COUNT(*) FROM finance_expenses WHERE recurring_source_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?");
        $stmtExists->execute([$template['id'], $month]);
        $exists = (int)$stmtExists->fetchColumn();

        if ($exists === 0) {
            // Clamp dia_pago to valid day in the month
            $dia = intval($template['dia_pago']);
            if ($dia > $lastDay) {
                $dia = $lastDay;
            }
            if ($dia < 1) {
                $dia = 1;
            }
            $fecha = sprintf('%s-%02d', $month, $dia);

            $stmtInsert = $db->prepare("INSERT INTO finance_expenses (fecha, nombre_gasto, monto, categoria, recurring_source_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmtInsert->execute([
                $fecha,
                $template['nombre_gasto'],
                $template['monto'],
                $template['categoria'],
                $template['id']
            ]);
            $generated++;
        }
    }

    // =====================================================
    // RRHH: Generar sueldos de empleados activos
    // =====================================================
    $stmtEmps = $db->query("SELECT id, name, salary, hire_date FROM employees WHERE status = 'Activo' AND salary > 0");
    $employees = $stmtEmps->fetchAll(PDO::FETCH_ASSOC);
    $generatedRRHH = 0;

    foreach ($employees as $emp) {
        $gastoName = 'Sueldo - ' . $emp['name'];

        // Check if salary expense already exists for this employee in this month
        $stmtExists = $db->prepare("SELECT COUNT(*) FROM finance_expenses WHERE nombre_gasto = ? AND categoria = 'Personal' AND DATE_FORMAT(fecha, '%Y-%m') = ?");
        $stmtExists->execute([$gastoName, $month]);
        $exists = (int)$stmtExists->fetchColumn();

        if ($exists === 0) {
            // Use hire_date day as payment day, clamped to valid range
            $hireDay = intval(date('d', strtotime($emp['hire_date'])));
            if ($hireDay > $lastDay) $hireDay = $lastDay;
            if ($hireDay < 1) $hireDay = 1;
            $fecha = sprintf('%s-%02d', $month, $hireDay);

            $stmtInsert = $db->prepare("INSERT INTO finance_expenses (fecha, nombre_gasto, monto, categoria, recurring_source_id, created_at, updated_at) VALUES (?, ?, ?, 'Personal', NULL, NOW(), NOW())");
            $stmtInsert->execute([$fecha, $gastoName, $emp['salary']]);
            $generated++;
            $generatedRRHH++;
        }
    }

    echo json_encode([
        'success' => true,
        'generated' => $generated,
        'generated_rrhh' => $generatedRRHH,
        'message' => "Se generaron {$generated} gastos ({$generatedRRHH} sueldos RRHH) para {$month}."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
