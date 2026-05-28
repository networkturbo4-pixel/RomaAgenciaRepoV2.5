<?php
// modules/admin/ajax_save_employee.php
// DB connection is handled by index.php

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$name = $_POST['name'] ?? '';
$dni = $_POST['dni'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$role = $_POST['role'] ?? '';
$department = $_POST['department'] ?? '';
$status = $_POST['status'] ?? 'Activo';
$salary = isset($_POST['salary']) ? floatval($_POST['salary']) : 0;
$hire_date = $_POST['hire_date'] ?? '';

if (empty($name) || empty($email) || empty($role) || empty($department) || empty($hire_date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
    exit();
}

try {
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE employees SET name=?, dni=?, email=?, phone=?, role=?, department=?, status=?, salary=?, hire_date=? WHERE id=?");
        $stmt->execute([$name, $dni, $email, $phone, $role, $department, $status, $salary, $hire_date, $id]);
    } else {
        $stmt = $db->prepare("INSERT INTO employees (name, dni, email, phone, role, department, status, salary, hire_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $dni, $email, $phone, $role, $department, $status, $salary, $hire_date]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
