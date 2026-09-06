<?php
// modules/admin/ajax_get_employees.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

if (!isset($db)) {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'Todos';

try {
    // 1. Get KPI Counts
    $totalCount = (int)$db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $activeCount = (int)$db->query("SELECT COUNT(*) FROM employees WHERE status = 'Activo'")->fetchColumn();
    $inactiveCount = (int)$db->query("SELECT COUNT(*) FROM employees WHERE status = 'Inactivo'")->fetchColumn();
    $pendingCount = (int)$db->query("SELECT COUNT(*) FROM employees WHERE status = 'Pendiente'")->fetchColumn();
    $totalPayroll = (float)$db->query("SELECT SUM(salary) FROM employees WHERE status = 'Activo'")->fetchColumn();

    // 2. Query Employees
    $sql = "SELECT * FROM employees WHERE 1=1";
    $params = [];

    if ($status !== 'Todos' && !empty($status)) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    if ($query !== '') {
        $sql .= " AND (name LIKE ? OR dni LIKE ? OR email LIKE ? OR role LIKE ? OR department LIKE ? OR phone LIKE ?)";
        $wildcard = "%$query%";
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate initials and consistent color
    $colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#0ea5e9'];
    foreach ($employees as &$emp) {
        $parts = preg_split('/\s+/', trim($emp['name']));
        $initials = '';
        if (count($parts) >= 2) {
            $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        } else {
            $initials = mb_strtoupper(mb_substr($emp['name'], 0, 2));
        }
        $emp['initials'] = $initials;
        $emp['color'] = $colors[$emp['id'] % count($colors)];
    }

    echo json_encode([
        'success' => true,
        'counts' => [
            'total' => $totalCount,
            'active' => $activeCount,
            'inactive' => $inactiveCount,
            'pending' => $pendingCount,
            'payroll' => $totalPayroll
        ],
        'data' => $employees
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
