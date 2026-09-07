<?php
// modules/admin/ajax_save_employee.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if (!isset($db)) {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$id = intval($_POST['id'] ?? $data['id'] ?? 0);
$name = $_POST['name'] ?? $data['name'] ?? '';
$dni = $_POST['dni'] ?? $data['dni'] ?? '';
$email = $_POST['email'] ?? $data['email'] ?? '';
$phone = $_POST['phone'] ?? $data['phone'] ?? '';
$role = $_POST['role'] ?? $data['role'] ?? '';
$department = $_POST['department'] ?? $data['department'] ?? '';
$status = $_POST['status'] ?? $data['status'] ?? 'Activo';
$salary = isset($_POST['salary']) ? floatval($_POST['salary']) : (isset($data['salary']) ? floatval($data['salary']) : 0);
$hire_date = $_POST['hire_date'] ?? $data['hire_date'] ?? '';
$work_start = !empty($_POST['work_start']) ? $_POST['work_start'] : (!empty($data['work_start']) ? $data['work_start'] : null);
$work_end = !empty($_POST['work_end']) ? $_POST['work_end'] : (!empty($data['work_end']) ? $data['work_end'] : null);

if (empty($name) || empty($email) || empty($role) || empty($department) || empty($hire_date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
    exit();
}

try {
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE employees SET name=?, dni=?, email=?, phone=?, role=?, department=?, status=?, salary=?, hire_date=?, work_start=?, work_end=? WHERE id=?");
        $stmt->execute([$name, $dni, $email, $phone, $role, $department, $status, $salary, $hire_date, $work_start, $work_end, $id]);
    } else {
        $stmt = $db->prepare("INSERT INTO employees (name, dni, email, phone, role, department, status, salary, hire_date, work_start, work_end) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $dni, $email, $phone, $role, $department, $status, $salary, $hire_date, $work_start, $work_end]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
