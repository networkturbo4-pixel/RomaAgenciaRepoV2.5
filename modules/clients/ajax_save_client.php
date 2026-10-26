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
$portal_enabled = isset($_POST['portal_enabled']) && ($_POST['portal_enabled'] == '1' || $_POST['portal_enabled'] === 'true' || $_POST['portal_enabled'] === 'on') ? 1 : 0;
$deletedBrands = json_decode($_POST['deleted_brands'] ?? '[]', true);
$brands = $_POST['brands'] ?? [];

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Nombre requerido']);
    exit();
}

try {
    $db->beginTransaction();

    if ($id) {
        if (array_key_exists('drive_folder_id', $_POST)) {
            $drive_folder_id = $_POST['drive_folder_id'] !== '' ? $_POST['drive_folder_id'] : null;
            $stmt = $db->prepare("UPDATE clients SET name = ?, whatsapp = ?, email = ?, dni = ?, drive_folder_id = ?, portal_enabled = ? WHERE id = ?");
            $stmt->execute([$name, $whatsapp, $email, $dni, $drive_folder_id, $portal_enabled, $id]);
        } else {
            $stmt = $db->prepare("UPDATE clients SET name = ?, whatsapp = ?, email = ?, dni = ?, portal_enabled = ? WHERE id = ?");
            $stmt->execute([$name, $whatsapp, $email, $dni, $portal_enabled, $id]);
        }
        $clientId = $id;
    } else {
        $stmt = $db->prepare("INSERT INTO clients (name, whatsapp, email, dni, portal_enabled) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $whatsapp, $email, $dni, $portal_enabled]);
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

        // Check if there is a file upload for this brand (from file input or clipboard)
        if (isset($_FILES["brands_files_$index"]) && $_FILES["brands_files_$index"]['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES["brands_files_$index"]['tmp_name'];
            $cleanName = basename($_FILES["brands_files_$index"]['name']);
            $cleanName = preg_replace("/[^a-zA-Z0-9_\-\.]/", "", $cleanName);
            if (empty($cleanName) || $cleanName === '.') $cleanName = 'logo_' . time() . '.png';
            $fileName = uniqid('brand_') . '_' . $cleanName;
            $filePath = $uploadDir . $fileName;
            if (move_uploaded_file($tmpName, $filePath)) {
                $brandLogo = $filePath;
            }
        } elseif (!empty($brandLogo)) {
            // Check if brandLogo is a base64 string
            if (strpos($brandLogo, 'data:image/') === 0) {
                if (preg_match('/^data:image\/(\w+);base64,/', $brandLogo, $typeMatch)) {
                    $base64Data = substr($brandLogo, strpos($brandLogo, ',') + 1);
                    $ext = strtolower($typeMatch[1]);
                    if ($ext === 'jpeg') $ext = 'jpg';
                    if (!in_array($ext, ['png', 'jpg', 'webp', 'svg', 'gif'])) {
                        $ext = 'png';
                    }
                    $decoded = base64_decode($base64Data);
                    if ($decoded !== false) {
                        $fileName = 'b64_' . uniqid() . '.' . $ext;
                        $filePath = $uploadDir . $fileName;
                        if (file_put_contents($filePath, $decoded)) {
                            $brandLogo = $filePath;
                        }
                    }
                }
            } elseif (strpos($brandLogo, 'http://') === 0 || strpos($brandLogo, 'https://') === 0) {
                // If it's a remote URL, attempt to download and save locally
                try {
                    $ctx = stream_context_create([
                        'http' => [
                            'timeout' => 4,
                            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
                        ],
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false
                        ]
                    ]);
                    $remoteImg = @file_get_contents($brandLogo, false, $ctx);
                    if ($remoteImg !== false && strlen($remoteImg) > 0) {
                        $pathExt = pathinfo(parse_url($brandLogo, PHP_URL_PATH), PATHINFO_EXTENSION);
                        $pathExt = strtolower($pathExt);
                        if (!$pathExt || !in_array($pathExt, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'])) {
                            $pathExt = 'png';
                        }
                        $fileName = 'url_' . uniqid() . '.' . $pathExt;
                        $filePath = $uploadDir . $fileName;
                        if (file_put_contents($filePath, $remoteImg)) {
                            $brandLogo = $filePath;
                        }
                    }
                } catch (\Throwable $t) {
                    // Retain the URL as fallback if download fails
                }
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
