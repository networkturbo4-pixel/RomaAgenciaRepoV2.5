<?php
// modules/services/ajax_save_service.php
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
    $id = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);

    if (empty($name) || $categoryId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
        exit;
    }

    try {
        $db->beginTransaction();

        if ($id) {
            // Update existing
            $stmt = $db->prepare("
                UPDATE services 
                SET category_id = :category_id, name = :name, description = :description, price = :price 
                WHERE id = :id
            ");
            $stmt->execute([
                ':category_id' => $categoryId,
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':id' => $id
            ]);
            $serviceId = $id;
            $message = 'Servicio actualizado correctamente';
        } else {
            // Insert new
            $stmt = $db->prepare("
                INSERT INTO services (category_id, name, description, price) 
                VALUES (:category_id, :name, :description, :price)
            ");
            $stmt->execute([
                ':category_id' => $categoryId,
                ':name' => $name,
                ':description' => $description,
                ':price' => $price
            ]);
            $serviceId = $db->lastInsertId();
            $message = 'Servicio creado correctamente';
        }

        // Handle features
        $features = json_decode($_POST['features'] ?? '[]', true);
        
        // Delete existing features for this service
        $stmtDel = $db->prepare("DELETE FROM service_features WHERE service_id = :service_id");
        $stmtDel->execute([':service_id' => $serviceId]);

        // Insert new features
        if (!empty($features)) {
            $stmtFeat = $db->prepare("INSERT INTO service_features (service_id, title, description, type) VALUES (:service_id, :title, :description, :type)");
            foreach ($features as $feature) {
                $stmtFeat->execute([
                    ':service_id' => $serviceId,
                    ':title' => $feature['title'],
                    ':description' => $feature['description'] ?? '',
                    ':type' => $feature['type'] ?? 'feature'
                ]);
            }
        }

        // Fetch category name
        $catStmt = $db->prepare("SELECT name FROM service_categories WHERE id = :id");
        $catStmt->execute([':id' => $categoryId]);
        $catName = $catStmt->fetchColumn() ?: 'Sin categoría';

        $db->commit();
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'is_update' => $id ? true : false,
            'service' => [
                'id' => $serviceId,
                'name' => $name,
                'description' => $description,
                'category_name' => $catName,
                'price' => $price
            ]
        ]);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error saving service: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al guardar el servicio']);
    }
}
