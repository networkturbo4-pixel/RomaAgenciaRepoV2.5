<?php
// modules/services/ajax_save_category.php
session_start();
require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $name = trim($_POST['category_name'] ?? '');

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'El nombre de la categoría es requerido']);
        exit;
    }

    try {
        if ($id) {
            $stmt = $db->prepare("UPDATE service_categories SET name = :name WHERE id = :id");
            $stmt->execute([':name' => $name, ':id' => $id]);
            $categoryId = $id;
            $message = 'Categoría actualizada correctamente';
        } else {
            $stmt = $db->prepare("INSERT INTO service_categories (name) VALUES (:name)");
            $stmt->execute([':name' => $name]);
            $categoryId = $db->lastInsertId();
            $message = 'Categoría guardada correctamente';
        }

        echo json_encode([
            'success' => true, 
            'message' => $message,
            'is_update' => $id ? true : false,
            'category' => [
                'id' => $categoryId,
                'name' => $name
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Error saving category: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al guardar la categoría']);
    }
}
