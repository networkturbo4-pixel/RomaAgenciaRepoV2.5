<?php
// modules/clients/ajax_save_client.php
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$id = $_POST['client_id'] ?? '';
$name = $_POST['name'] ?? '';
$whatsapp = $_POST['whatsapp'] ?? '';
$email = $_POST['email'] ?? '';
$dni = $_POST['dni'] ?? '';
$drive_folder_id = $_POST['drive_folder_id'] ?? null;
if ($drive_folder_id === '') $drive_folder_id = null;
$deletedBrands = json_decode($_POST['deleted_brands'] ?? '[]', true);
$brands = $_POST['brands'] ?? [];

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Nombre requerido']);
    exit();
}

try {
    $db->beginTransaction();

    if ($id) {
        $stmt = $db->prepare("UPDATE clients SET name = ?, whatsapp = ?, email = ?, dni = ?, drive_folder_id = ? WHERE id = ?");
        $stmt->execute([$name, $whatsapp, $email, $dni, $drive_folder_id, $id]);
        $clientId = $id;
    } else {
        $stmt = $db->prepare("INSERT INTO clients (name, whatsapp, email, dni, drive_folder_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $whatsapp, $email, $dni, $drive_folder_id]);
        $clientId = $db->lastInsertId();
    }

    // Delete removed brands
    if (!empty($deletedBrands)) {
        $placeholders = implode(',', array_fill(0, count($deletedBrands), '?'));
        $delStmt = $db->prepare("DELETE FROM client_brands WHERE id IN ($placeholders) AND client_id = ?");
        $params = array_merge($deletedBrands, [$clientId]);
        $delStmt->execute($params);
    }

    // Handle brands and file uploads
    $uploadDir = 'uploads/brands/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    foreach ($brands as $index => $brand) {
        $brandId = $brand['id'] ?? '';
        $brandName = $brand['name'] ?? '';
        $brandLogo = $brand['logo'] ?? '';

        $brandHasMembership = isset($brand['has_membership']) && $brand['has_membership'] == '1' ? 1 : 0;
        $brandServicesIds = $brand['services_ids'] ?? '[]'; // should be json string
        $brandWhatsappGroup = $brand['whatsapp_group'] ?? null;

        // Check if there is a file upload for this brand
        if (isset($_FILES["brands_files_$index"]) && $_FILES["brands_files_$index"]['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES["brands_files_$index"]['tmp_name'];
            $fileName = uniqid() . '_' . basename($_FILES["brands_files_$index"]['name']);
            // Limpiar nombre de archivo
            $fileName = preg_replace("/[^a-zA-Z0-9_\-\.]/", "", $fileName);
            $filePath = $uploadDir . $fileName;
            if (move_uploaded_file($tmpName, $filePath)) {
                $brandLogo = $filePath;
            }
        }

        if ($brandId) {
            $stmt = $db->prepare("UPDATE client_brands SET name = ?, logo = ?, has_membership = ?, services_ids = ?, whatsapp_group = ? WHERE id = ? AND client_id = ?");
            $stmt->execute([$brandName, $brandLogo, $brandHasMembership, $brandServicesIds, $brandWhatsappGroup, $brandId, $clientId]);
        } else {
            $stmt = $db->prepare("INSERT INTO client_brands (client_id, name, logo, has_membership, services_ids, whatsapp_group) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$clientId, $brandName, $brandLogo, $brandHasMembership, $brandServicesIds, $brandWhatsappGroup]);
        }
    }

    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
