<?php
header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Read POST fields
    $project_id   = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? intval($_POST['project_id']) : null;
    $name         = isset($_POST['name']) ? trim($_POST['name']) : '';
    $client_id    = isset($_POST['client_id']) && $_POST['client_id'] !== '' ? intval($_POST['client_id']) : null;
    $brand_id     = isset($_POST['brand_id']) && $_POST['brand_id'] !== '' ? intval($_POST['brand_id']) : null;
    $service_id   = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? intval($_POST['service_id']) : null;
    $team_members = isset($_POST['team_members']) ? $_POST['team_members'] : '[]';
    $os_correlativo = isset($_POST['os_correlativo']) ? trim($_POST['os_correlativo']) : '';
    $description  = isset($_POST['description']) ? trim($_POST['description']) : '';

    $create_drive = isset($_POST['create_drive_folder']) && $_POST['create_drive_folder'] == '1';
    $drive_parent = isset($_POST['drive_parent_id']) ? trim($_POST['drive_parent_id']) : null;

    // Validate required field
    if ($name === '') {
        echo json_encode(['success' => false, 'message' => 'El nombre del proyecto es obligatorio']);
        exit;
    }

    // Validate team_members is valid JSON
    $decoded_members = json_decode($team_members);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'El campo team_members no es un JSON válido']);
        exit;
    }
    // Re-encode to ensure clean JSON storage
    $team_members = json_encode($decoded_members);

    // ── Handle logo ──────────────────────────────────────────────────────
    $logo_path = null;

    // 1. Check for uploaded file
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../uploads/projects/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext  = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        if (!in_array($file_ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Formato de imagen no permitido']);
            exit;
        }

        $unique_name = uniqid('proj_', true) . '.' . $file_ext;
        $destination = $upload_dir . $unique_name;

        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $destination)) {
            echo json_encode(['success' => false, 'message' => 'Error al subir el archivo de logo']);
            exit;
        }

        $logo_path = 'uploads/projects/' . $unique_name;
    }
    // 2. Fallback: if no file uploaded and brand_id provided, use brand logo
    elseif ($brand_id) {
        $stmt_brand = $db->prepare("SELECT logo FROM client_brands WHERE id = :brand_id LIMIT 1");
        $stmt_brand->bindParam(':brand_id', $brand_id, PDO::PARAM_INT);
        $stmt_brand->execute();
        $brand_row = $stmt_brand->fetch(PDO::FETCH_ASSOC);
        if ($brand_row && !empty($brand_row['logo'])) {
            $logo_path = $brand_row['logo'];
        }
    }

    // ── INSERT or UPDATE ─────────────────────────────────────────────────
    if ($project_id === null) {
        // ── INSERT ───────────────────────────────────────────────────────

        // Auto-generate os_correlativo if not provided
        if ($os_correlativo === '') {
            $stmt_last = $db->query("SELECT MAX(id) AS max_id FROM module_projects");
            $row_last  = $stmt_last->fetch(PDO::FETCH_ASSOC);
            $next_num  = ($row_last && $row_last['max_id']) ? intval($row_last['max_id']) + 1 : 1;
            $os_correlativo = 'PRY-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
        }

        $sql = "INSERT INTO module_projects
                    (name, logo, client_id, brand_id, service_id, status, team_members, os_correlativo, description, created_at, updated_at)
                VALUES
                    (:name, :logo, :client_id, :brand_id, :service_id, 'active', :team_members, :os_correlativo, :description, NOW(), NOW())";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':logo', $logo_path);
        $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
        $stmt->bindParam(':brand_id', $brand_id, PDO::PARAM_INT);
        $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        $stmt->bindParam(':team_members', $team_members);
        $stmt->bindParam(':os_correlativo', $os_correlativo);
        $stmt->bindParam(':description', $description);
        $stmt->execute();

        $new_id = $db->lastInsertId();
        $final_id = $new_id;
        $response_msg = 'Proyecto creado exitosamente';

    } else {
        // ── UPDATE ───────────────────────────────────────────────────────

        // Build dynamic SET clause so logo is only updated when a new one is available
        $fields = [
            'name = :name',
            'client_id = :client_id',
            'brand_id = :brand_id',
            'service_id = :service_id',
            'team_members = :team_members',
            'os_correlativo = :os_correlativo',
            'description = :description',
            'updated_at = NOW()'
        ];
        $params = [
            ':name'            => $name,
            ':client_id'       => $client_id,
            ':brand_id'        => $brand_id,
            ':service_id'      => $service_id,
            ':team_members'    => $team_members,
            ':os_correlativo'  => $os_correlativo,
            ':description'     => $description,
            ':project_id'      => $project_id
        ];

        if ($logo_path !== null) {
            $fields[] = 'logo = :logo';
            $params[':logo'] = $logo_path;
        }

        $sql = "UPDATE module_projects SET " . implode(', ', $fields) . " WHERE id = :project_id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $final_id = $project_id;

        $response_msg = 'Proyecto actualizado exitosamente';
    }

    // ── Google Drive Integration ──
    if ($create_drive && $drive_parent && $final_id) {
        require_once 'includes/GoogleDriveHelper.php';
        $driveHelper = new GoogleDriveHelper();
        
        if ($driveHelper->isConfigured()) {
            $folderId = $driveHelper->createFolder($name, $drive_parent);
            if ($folderId) {
                // Generar link básico
                $folderLink = "https://drive.google.com/drive/folders/" . $folderId;
                
                // Actualizar en base de datos
                $stmtDrive = $db->prepare("UPDATE module_projects SET drive_folder_id = ?, drive_folder_link = ? WHERE id = ?");
                $stmtDrive->execute([$folderId, $folderLink, $final_id]);
                
                $response_msg .= ' (Carpeta de Drive creada)';
            }
        }
    }

    echo json_encode([
        'success'    => true,
        'message'    => $response_msg ?? 'Operación exitosa',
        'project_id' => intval($final_id)
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
