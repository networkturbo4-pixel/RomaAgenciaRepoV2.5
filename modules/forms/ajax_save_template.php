<?php
// modules/forms/ajax_save_template.php
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'No autorizado']); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $fields_json = $_POST['fields_json'] ?? '[]';
    $settings_json = $_POST['settings_json'] ?? '{}';
    $status = $_POST['status'] ?? 'draft';

    if (empty($title)) { echo json_encode(['success'=>false,'error'=>'El título es obligatorio']); exit(); }

    try {
        if (empty($id)) {
            $token = bin2hex(random_bytes(16));
            $stmt = $db->prepare("INSERT INTO form_templates (title, description, fields_json, settings_json, public_token, status, created_by) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$title, $description, $fields_json, $settings_json, $token, $status, $_SESSION['user_id']]);
            $newId = $db->lastInsertId();

            // Create Drive folder if publishing
            if ($status === 'active') {
                try {
                    require_once 'includes/GoogleDriveHelper.php';
                    $drive = new GoogleDriveHelper();
                    if ($drive->isConfigured()) {
                        $briefsFolder = null;
                        $rootFolders = $drive->listFolders('root');
                        if ($rootFolders) {
                            foreach ($rootFolders as $f) {
                                if (strtolower(trim($f->name)) === 'briefs') { $briefsFolder = $f->id; break; }
                            }
                        }
                        if (!$briefsFolder) $briefsFolder = $drive->createFolder('Briefs');
                        if ($briefsFolder) {
                            $formFolder = $drive->createFolder($title, $briefsFolder);
                            if ($formFolder) {
                                $db->prepare("UPDATE form_templates SET drive_folder_id=? WHERE id=?")->execute([$formFolder, $newId]);
                            }
                        }
                    }
                } catch (Exception $e) { error_log("Drive error: " . $e->getMessage()); }
            }

            echo json_encode(['success'=>true, 'id'=>$newId, 'token'=>$token, 'redirect_url'=>"index.php?module=forms&action=builder&id=$newId"]);
        } else {
            $stmt = $db->prepare("UPDATE form_templates SET title=?, description=?, fields_json=?, settings_json=?, status=? WHERE id=?");
            $stmt->execute([$title, $description, $fields_json, $settings_json, $status, $id]);

            // Create Drive folder if first time publishing
            if ($status === 'active') {
                $check = $db->prepare("SELECT drive_folder_id FROM form_templates WHERE id=?");
                $check->execute([$id]);
                $existing = $check->fetchColumn();
                if (!$existing) {
                    try {
                        require_once 'includes/GoogleDriveHelper.php';
                        $drive = new GoogleDriveHelper();
                        if ($drive->isConfigured()) {
                            $briefsFolder = null;
                            $rootFolders = $drive->listFolders('root');
                            if ($rootFolders) {
                                foreach ($rootFolders as $f) {
                                    if (strtolower(trim($f->name)) === 'briefs') { $briefsFolder = $f->id; break; }
                                }
                            }
                            if (!$briefsFolder) $briefsFolder = $drive->createFolder('Briefs');
                            if ($briefsFolder) {
                                $formFolder = $drive->createFolder($title, $briefsFolder);
                                if ($formFolder) {
                                    $db->prepare("UPDATE form_templates SET drive_folder_id=? WHERE id=?")->execute([$formFolder, $id]);
                                }
                            }
                        }
                    } catch (Exception $e) { error_log("Drive error: " . $e->getMessage()); }
                }
            }

            echo json_encode(['success'=>true, 'id'=>$id]);
        }
    } catch (Exception $e) {
        echo json_encode(['success'=>false, 'error'=>'Error DB: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success'=>false, 'error'=>'Método no permitido']);
}
