<?php
// modules/design_tasks/ajax.php
session_start();
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
require_once '../../includes/GoogleDriveHelper.php';
require_once '../../includes/PushHelper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit();
}

$db = (new Database())->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

$stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmtRole->execute([$user_id]);
$role_id = $stmtRole->fetchColumn();
$isAdmin = ($role_id == 1);


// Aux function to get or create a folder in Drive
function getOrCreateSubfolder($drive, $parentFolderId, $folderName) {
    // We could search, but for simplicity, we'll just search by name
    $optParams = [
        'q' => "name='" . str_replace("'", "\\'", $folderName) . "' and '" . $parentFolderId . "' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false",
        'fields' => 'files(id)',
        'supportsAllDrives' => true,
        'includeItemsFromAllDrives' => true
    ];
    // We have to use the raw service for search since listFolders doesn't let us search by name easily
    // Let's modify our logic: GoogleDriveHelper listFolders doesn't filter by name. We'll fetch all and find it.
    $folders = $drive->listFolders($parentFolderId);
    if ($folders) {
        foreach ($folders as $f) {
            if (strtolower(trim($f->name)) === strtolower(trim($folderName))) {
                return $f->id;
            }
        }
    }
    // If not found, create it
    return $drive->createFolder($folderName, $parentFolderId);
}

try {
    switch ($action) {
        case 'fetch_users':
            $stmt = $db->query("SELECT id, name, avatar FROM users ORDER BY name ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $v = time();
            foreach ($users as &$u) {
                if (!empty($u['avatar'])) {
                    $u['avatar'] .= (strpos($u['avatar'], '?') !== false ? '&' : '?') . 'v=' . $v;
                }
            }
            echo json_encode(['success' => true, 'data' => $users]);
            break;

        case 'fetch_tasks':
            $sql = "SELECT * FROM design_tasks WHERE deleted_at IS NULL ORDER BY created_at DESC";
            $stmt = $db->query($sql);
            $allTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $tasks = [];
            foreach ($allTasks as $task) {
                $assigned = json_decode($task['assigned_to'] ?: '[]', true) ?: [];
                if ($isAdmin || in_array($user_id, $assigned) || $task['created_by'] == $user_id) {
                    $stStmt = $db->prepare("SELECT * FROM design_task_subtasks WHERE design_task_id = ?");
                    $stStmt->execute([$task['id']]);
                    $task['subtasks'] = $stStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $atStmt = $db->prepare("SELECT * FROM design_task_attachments WHERE design_task_id = ?");
                    $atStmt->execute([$task['id']]);
                    $task['attachments'] = $atStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $tagStmt = $db->prepare("SELECT name, color FROM design_task_tags WHERE design_task_id = ?");
                    $tagStmt->execute([$task['id']]);
                    $task['tags'] = $tagStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $task['assigned_to'] = $assigned;
                    $tasks[] = $task;
                }
            }
            echo json_encode(['success' => true, 'data' => $tasks]);
            break;

        case 'upload_avance':
            $task_id = $_POST['task_id'] ?? '';
            $drive_folder_id = $_POST['drive_folder_id'] ?? '';
            if (!$drive_folder_id || empty($_FILES['avance_image'])) {
                echo json_encode(['success' => false, 'error' => 'Faltan datos para subir el avance.']);
                exit();
            }

            $drive = new GoogleDriveHelper();
            if ($drive->isConfigured()) {
                $avanceFolderId = getOrCreateSubfolder($drive, $drive_folder_id, 'avances');
                if ($avanceFolderId) {
                    $tmp_name = $_FILES['avance_image']['tmp_name'];
                    $fileName = 'Avance_' . date('Ymd_His') . '.png';
                    $uploaded = $drive->uploadFile($tmp_name, $fileName, $avanceFolderId);
                    
                    if ($uploaded) {
                        $drive->makePublicViewer($uploaded['id']);
                        $newId = 0;
                        if ($task_id) {
                            $stmtFile = $db->prepare("INSERT INTO design_task_attachments (design_task_id, file_path, file_name, attachment_type) VALUES (?, ?, ?, 'avance')");
                            $stmtFile->execute([$task_id, $uploaded['webViewLink'], $fileName]);
                            $newId = $db->lastInsertId();
                        }
                        
                        echo json_encode(['success' => true, 'attachment' => [
                            'id' => $newId,
                            'file_name' => $fileName,
                            'file_path' => $uploaded['webViewLink'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]]);
                        exit();
                    }
                }
            }
            echo json_encode(['success' => false, 'error' => 'Error al subir el archivo a Drive.']);
            break;

        case 'save_task':
            $id = $_POST['id'] ?? '';
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $priority = $_POST['priority'] ?? 'media';
            $status = $_POST['status'] ?? 'Pendiente';
            $due_date = !empty($_POST['due_date']) ? date('Y-m-d H:i:s', strtotime($_POST['due_date'])) : null;
            $drive_folder_id = !empty($_POST['drive_folder_id']) ? $_POST['drive_folder_id'] : null;
            $assigned_to = isset($_POST['assigned_to']) ? json_encode($_POST['assigned_to']) : '[]';
            $external_links = $_POST['external_links'] ?? null;
            $linked_form_id = !empty($_POST['linked_form_id']) ? (int)$_POST['linked_form_id'] : null;
            $client_id = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
            $tags = $_POST['tags'] ?? [];
            
            if (empty($title)) {
                echo json_encode(['success' => false, 'error' => 'El título es obligatorio']);
                exit();
            }

            if ($id) {
                $stmt = $db->prepare("UPDATE design_tasks SET title=?, description=?, priority=?, status=?, due_date=?, assigned_to=?, drive_folder_id=?, external_links=?, linked_submission_id=?, client_id=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $stmt->execute([$title, $description, $priority, $status, $due_date, $assigned_to, $drive_folder_id, $external_links, $linked_form_id, $client_id, $id]);
                $taskId = $id;
                
                $assignedIds = json_decode($assigned_to, true) ?: [];
                $assignedIds = array_values(array_diff($assignedIds, [$user_id])); 
                if (!empty($assignedIds)) {
                    PushHelper::sendPushNotification($db, $assignedIds, "Tarea Actualizada", "Se ha actualizado la tarea: " . $title, "index.php?module=design_tasks", "design_task", ['module' => 'design_tasks']);
                }
            } else {
                $stmt = $db->prepare("INSERT INTO design_tasks (title, description, priority, status, due_date, assigned_to, drive_folder_id, external_links, linked_submission_id, client_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $priority, $status, $due_date, $assigned_to, $drive_folder_id, $external_links, $linked_form_id, $client_id, $user_id]);
                $taskId = $db->lastInsertId();
                
                $assignedIds = json_decode($assigned_to, true) ?: [];
                $assignedIds = array_values(array_diff($assignedIds, [$user_id])); 
                if (!empty($assignedIds)) {
                    PushHelper::sendPushNotification($db, $assignedIds, "Nueva Tarea de Diseño", "Te han asignado a: " . $title, "index.php?module=design_tasks", "design_task", ['module' => 'design_tasks']);
                }
            }

            // Tags
            $db->prepare("DELETE FROM design_task_tags WHERE design_task_id = ?")->execute([$taskId]);
            if (!empty($tags)) {
                $stmtTag = $db->prepare("INSERT INTO design_task_tags (design_task_id, name, color) VALUES (?, ?, ?)");
                
                // Preload master tags colors
                $masterTags = $db->query("SELECT name, color FROM design_tags_master")->fetchAll(PDO::FETCH_KEY_PAIR);
                $fallbackColors = ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#10b981', '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6', '#d946ef', '#f43f5e'];
                
                foreach ($tags as $tag) {
                    $tag = trim($tag);
                    if ($tag) {
                        if (isset($masterTags[$tag])) {
                            $color = $masterTags[$tag];
                        } else {
                            $hash = crc32($tag);
                            $color = $fallbackColors[$hash % count($fallbackColors)];
                        }
                        $stmtTag->execute([$taskId, $tag, $color]);
                    }
                }
            }

            // Subtasks
            if (isset($_POST['st_titles'])) {
                $st_ids = $_POST['st_ids'] ?? [];
                $st_titles = $_POST['st_titles'];
                $st_descs = $_POST['st_descs'] ?? [];
                $st_comps = $_POST['st_comps'] ?? [];
                $st_due_dates = $_POST['st_due_dates'] ?? [];
                
                // Track current active subtask IDs to delete removed ones
                $activeStIds = [];

                for ($i=0; $i < count($st_titles); $i++) {
                    $stTitle = trim($st_titles[$i]);
                    $stDesc = trim($st_descs[$i] ?? '');
                    $isComp = $st_comps[$i] ?? 0;
                    $stDue = !empty($st_due_dates[$i]) ? $st_due_dates[$i] : null;
                    $isComp = isset($st_comps[$i]) && $st_comps[$i] === '1' ? 1 : 0;
                    $stId = $st_ids[$i] ?? '';
                    
                    if ($stTitle) {
                        if ($stId) {
                            $db->prepare("UPDATE design_task_subtasks SET title=?, description=?, is_completed=?, due_date=? WHERE id=?")->execute([$stTitle, $stDesc, $isComp, $stDue, $stId]);
                            $activeStIds[] = $stId;
                            $currentStId = $stId;
                        } else {
                            $db->prepare("INSERT INTO design_task_subtasks (design_task_id, title, description, is_completed, due_date) VALUES (?, ?, ?, ?, ?)")->execute([$taskId, $stTitle, $stDesc, $isComp, $stDue]);
                            $currentStId = $db->lastInsertId();
                            $activeStIds[] = $currentStId;
                        }

                        // Upload subtask files if any
                        $fileInputName = 'st_files_' . $i;
                        if (!empty($_FILES[$fileInputName]['name'][0])) {
                            $drive = new GoogleDriveHelper();
                            if ($drive->isConfigured() && $drive_folder_id) {
                                // Create 'referencias' folder
                                $refFolderId = getOrCreateSubfolder($drive, $drive_folder_id, 'referencias');
                                if ($refFolderId) {
                                    $stmtFile = $db->prepare("INSERT INTO design_task_attachments (design_task_id, subtask_id, file_path, file_name, attachment_type) VALUES (?, ?, ?, ?, 'subtask_reference')");
                                    foreach ($_FILES[$fileInputName]['tmp_name'] as $key => $tmp_name) {
                                        $fileName = basename($_FILES[$fileInputName]['name'][$key]);
                                        $uploaded = $drive->uploadFile($tmp_name, $fileName, $refFolderId);
                                        if ($uploaded && isset($uploaded['webViewLink'])) {
                                            $stmtFile->execute([$taskId, $currentStId, $uploaded['webViewLink'], $fileName]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Delete removed subtasks
                if (!empty($activeStIds)) {
                    $in = str_repeat('?,', count($activeStIds) - 1) . '?';
                    $db->prepare("DELETE FROM design_task_subtasks WHERE design_task_id = ? AND id NOT IN ($in)")->execute(array_merge([$taskId], $activeStIds));
                } else {
                    $db->prepare("DELETE FROM design_task_subtasks WHERE design_task_id = ?")->execute([$taskId]);
                }
            } else {
                $db->prepare("DELETE FROM design_task_subtasks WHERE design_task_id = ?")->execute([$taskId]);
            }

            // Pending Avances Uploaded before save
            if (!empty($_POST['pending_avances'])) {
                $stmtFile = $db->prepare("UPDATE design_task_attachments SET design_task_id = ? WHERE id = ?");
                $stmtInsert = $db->prepare("INSERT INTO design_task_attachments (design_task_id, file_path, file_name, attachment_type) VALUES (?, ?, ?, 'avance')");
                
                foreach ($_POST['pending_avances'] as $avanceJson) {
                    $avance = json_decode($avanceJson, true);
                    if ($avance) {
                        if (!empty($avance['id'])) {
                            // Update existing attachment
                            $stmtFile->execute([$taskId, $avance['id']]);
                        } else if (!empty($avance['file_path'])) {
                            // Insert new attachment if it wasn't saved with an ID
                            $stmtInsert->execute([$taskId, $avance['file_path'], $avance['file_name']]);
                        }
                    }
                }
            }

            // General Uploads (Detalles / Archivos)
            $drive = new GoogleDriveHelper();
            if ($drive->isConfigured() && $drive_folder_id) {
                // Main References
                if (!empty($_FILES['main_references']['name'][0])) {
                    $refFolderId = getOrCreateSubfolder($drive, $drive_folder_id, 'referencias');
                    if ($refFolderId) {
                        $stmtFile = $db->prepare("INSERT INTO design_task_attachments (design_task_id, file_path, file_name, attachment_type) VALUES (?, ?, ?, 'reference')");
                        foreach ($_FILES['main_references']['tmp_name'] as $key => $tmp_name) {
                            $fileName = basename($_FILES['main_references']['name'][$key]);
                            $uploaded = $drive->uploadFile($tmp_name, $fileName, $refFolderId);
                            if ($uploaded && isset($uploaded['webViewLink'])) {
                                $stmtFile->execute([$taskId, $uploaded['webViewLink'], $fileName]);
                            }
                        }
                    }
                }

                // Design Files
                if (!empty($_FILES['design_files']['name'][0])) {
                    $desFolderId = getOrCreateSubfolder($drive, $drive_folder_id, 'Diseño y empaquetado');
                    if ($desFolderId) {
                        $stmtFile = $db->prepare("INSERT INTO design_task_attachments (design_task_id, file_path, file_name, attachment_type) VALUES (?, ?, ?, 'design')");
                        foreach ($_FILES['design_files']['tmp_name'] as $key => $tmp_name) {
                            $fileName = basename($_FILES['design_files']['name'][$key]);
                            $uploaded = $drive->uploadFile($tmp_name, $fileName, $desFolderId);
                            if ($uploaded && isset($uploaded['webViewLink'])) {
                                $stmtFile->execute([$taskId, $uploaded['webViewLink'], $fileName]);
                            }
                        }
                    }
                }
            } else {
                // If there are files but no drive folder, we can return an error
                if (!empty($_FILES['main_references']['name'][0]) || !empty($_FILES['design_files']['name'][0])) {
                    echo json_encode(['success' => false, 'error' => 'Debes seleccionar una carpeta de Drive para subir archivos. Ya no se permiten subidas locales.']);
                    exit();
                }
            }

            echo json_encode(['success' => true, 'id' => $taskId]);
            break;

        case 'update_status':
            $id = $_POST['id'] ?? '';
            $status = $_POST['status'] ?? '';
            if ($id && $status) {
                $db->prepare("UPDATE design_tasks SET status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$status, $id]);
                
                // Fetch assigned users to notify them
                $stmt = $db->prepare("SELECT title, assigned_to FROM design_tasks WHERE id=?");
                $stmt->execute([$id]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($task) {
                    $assignedIds = json_decode($task['assigned_to'], true) ?: [];
                    $assignedIds = array_values(array_diff($assignedIds, [$user_id]));
                    if (!empty($assignedIds)) {
                        PushHelper::sendPushNotification($db, $assignedIds, "Estado de tarea actualizado", "La tarea '{$task['title']}' ahora está: {$status}", "index.php?module=design_tasks", "design_task", ['module' => 'design_tasks']);
                    }
                }
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
            }
            break;

        case 'clone_task':
            $taskId = $_POST['task_id'] ?? '';
            if ($taskId) {
                // Clone task
                $stmt = $db->prepare("INSERT INTO design_tasks (title, description, priority, status, due_date, assigned_to, created_by, drive_folder_id) SELECT CONCAT(title, ' (Copia)'), description, priority, status, due_date, assigned_to, ?, drive_folder_id FROM design_tasks WHERE id=?");
                $stmt->execute([$currentUserId, $taskId]);
                $newTaskId = $db->lastInsertId();

                if ($newTaskId) {
                    // Clone subtasks
                    $stmtSub = $db->prepare("INSERT INTO design_task_subtasks (design_task_id, title, is_completed, description) SELECT ?, title, is_completed, description FROM design_task_subtasks WHERE design_task_id=?");
                    $stmtSub->execute([$newTaskId, $taskId]);

                    // Clone attachments (they will point to the same Drive URL)
                    $stmtAtt = $db->prepare("INSERT INTO design_task_attachments (design_task_id, subtask_id, file_path, file_name, attachment_type) SELECT ?, NULL, file_path, file_name, attachment_type FROM design_task_attachments WHERE design_task_id=? AND subtask_id IS NULL");
                    $stmtAtt->execute([$newTaskId, $taskId]);
                    
                    echo json_encode(['success' => true, 'new_id' => $newTaskId]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'No se pudo crear la copia']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Falta el ID de la tarea']);
            }
            break;

        case 'generate_folder_structure':
            $drive_folder_id = $_POST['drive_folder_id'] ?? '';
            $task_title = $_POST['task_title'] ?? 'Tarea sin título';
            if (!$drive_folder_id) {
                echo json_encode(['success' => false, 'error' => 'No se ha seleccionado carpeta raíz.']);
                exit();
            }
            $drive = new GoogleDriveHelper();
            if ($drive->isConfigured()) {
                // 1. Create main task folder inside the selected root folder
                $taskFolderId = getOrCreateSubfolder($drive, $drive_folder_id, $task_title);
                
                if ($taskFolderId) {
                    // 2. Create subfolders inside the new task folder
                    getOrCreateSubfolder($drive, $taskFolderId, 'Diseño y empaquetado');
                    getOrCreateSubfolder($drive, $taskFolderId, 'referencias');
                    getOrCreateSubfolder($drive, $taskFolderId, 'avances');
                    
                    echo json_encode(['success' => true, 'new_folder_id' => $taskFolderId]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'No se pudo crear la carpeta principal de la tarea.']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Google Drive no está configurado.']);
            }
            break;
            
        case 'update_date':
            $id = $_POST['id'] ?? '';
            $due_date = $_POST['due_date'] ?? '';
            if ($id && $due_date) {
                $formatted_date = date('Y-m-d H:i:s', strtotime($due_date));
                $db->prepare("UPDATE design_tasks SET due_date=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$formatted_date, $id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
            }
            break;

        case 'delete_task':
            $id = $_POST['id'] ?? '';
            if ($id) {
                // Soft delete
                $db->prepare("UPDATE design_tasks SET deleted_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Falta el ID']);
            }
            break;

        case 'convert_subtask_to_task':
            $subtaskId = $_POST['subtask_id'] ?? '';
            if ($subtaskId) {
                $stmt = $db->prepare("SELECT * FROM design_task_subtasks WHERE id=?");
                $stmt->execute([$subtaskId]);
                $st = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($st) {
                    $db->prepare("INSERT INTO design_tasks (title, description, due_date, created_by) VALUES (?, ?, ?, ?)")->execute([$st['title'], $st['description'], $st['due_date'], $user_id]);
                    $newId = $db->lastInsertId();
                    
                    // Move attachments to new task
                    $db->prepare("UPDATE design_task_attachments SET design_task_id=?, subtask_id=NULL WHERE subtask_id=?")->execute([$newId, $subtaskId]);
                    
                    // Delete old subtask
                    $db->prepare("DELETE FROM design_task_subtasks WHERE id=?")->execute([$subtaskId]);
                    
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'No se encontró la subtarea']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Falta el ID']);
            }
            break;

        case 'update_timer':
            $taskId = $_POST['task_id'] ?? '';
            $action_type = $_POST['type'] ?? ''; // start, stop
            if ($taskId && $action_type) {
                if ($action_type === 'start') {
                    $db->prepare("UPDATE design_tasks SET timer_running=1, timer_started_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$taskId]);
                } else {
                    $stmt = $db->prepare("SELECT timer_started_at, time_spent FROM design_tasks WHERE id=?");
                    $stmt->execute([$taskId]);
                    $t = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($t && $t['timer_started_at']) {
                        $start = strtotime($t['timer_started_at']);
                        $now = time();
                        $diffSecs = $now - $start;
                        $newSpent = $t['time_spent'] + $diffSecs;
                        $db->prepare("UPDATE design_tasks SET timer_running=0, time_spent=?, timer_started_at=NULL WHERE id=?")->execute([$newSpent, $taskId]);
                    }
                }
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
            }
            break;

        case 'delete_attachment':
            $id = $_POST['id'] ?? '';
            if ($id) {
                $db->prepare("DELETE FROM design_task_attachments WHERE id=?")->execute([$id]);
                echo json_encode(['success' => true]);
            }
            break;

        case 'fetch_trash':
            $sql = "SELECT id, title, deleted_at, status FROM design_tasks WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC";
            $stmt = $db->query($sql);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'restore_task':
            $id = $_POST['id'] ?? '';
            if ($id) {
                $db->prepare("UPDATE design_tasks SET deleted_at=NULL WHERE id=?")->execute([$id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Falta el ID']);
            }
            break;

        case 'force_delete_task':
            $id = $_POST['id'] ?? '';
            if ($id) {
                $db->prepare("DELETE FROM design_tasks WHERE id=?")->execute([$id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Falta el ID']);
            }
            break;

        case 'fetch_pins':
            $attachment_id = $_GET['attachment_id'] ?? '';
            if ($attachment_id) {
                $stmt = $db->prepare("SELECT p.*, u.name as user_name, u.avatar as user_avatar FROM design_task_comments p JOIN users u ON p.user_id = u.id WHERE p.attachment_id = ? ORDER BY p.created_at ASC");
                $stmt->execute([$attachment_id]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Falta ID de adjunto']);
            }
            break;

        case 'save_pin':
            $attachment_id = $_POST['attachment_id'] ?? '';
            $x = $_POST['x'] ?? '';
            $y = $_POST['y'] ?? '';
            $comment = $_POST['comment'] ?? '';
            
            if ($attachment_id && $x !== '' && $y !== '' && $comment) {
                $stmt = $db->prepare("INSERT INTO design_task_comments (attachment_id, user_id, x_pos, y_pos, comment) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$attachment_id, $user_id, $x, $y, $comment]);
                $commentId = $db->lastInsertId();
                
                // Fetch task to notify assignees
                $stmtAtt = $db->prepare("SELECT design_task_id FROM design_task_attachments WHERE id = ?");
                $stmtAtt->execute([$attachment_id]);
                $att = $stmtAtt->fetch();
                if ($att) {
                    $stmtTask = $db->prepare("SELECT title, assigned_to FROM design_tasks WHERE id = ?");
                    $stmtTask->execute([$att['design_task_id']]);
                    $task = $stmtTask->fetch();
                    if ($task) {
                        $assignedIds = json_decode($task['assigned_to'], true) ?: [];
                        $assignedIds = array_values(array_diff($assignedIds, [$user_id]));
                        if (!empty($assignedIds)) {
                            PushHelper::sendPushNotification($db, $assignedIds, "Nuevo comentario en diseño", "En la tarea '{$task['title']}': {$comment}", "index.php?module=design_tasks", "design_task", ['module' => 'design_tasks']);
                        }
                    }
                }
                
                echo json_encode(['success' => true, 'id' => $commentId]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            }
            break;

        case 'fetch_master_tags':
            $stmt = $db->query("SELECT * FROM design_tags_master ORDER BY name ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'save_master_tag':
            $id = $_POST['id'] ?? '';
            $name = $_POST['name'] ?? '';
            $color = $_POST['color'] ?? '#3b82f6';
            if ($name) {
                if ($id) {
                    $db->prepare("UPDATE design_tags_master SET name=?, color=? WHERE id=?")->execute([$name, $color, $id]);
                } else {
                    $db->prepare("INSERT INTO design_tags_master (name, color) VALUES (?, ?)")->execute([$name, $color]);
                }
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Nombre requerido']);
            }
            break;

        case 'delete_master_tag':
            $id = $_POST['id'] ?? '';
            if ($id) {
                $db->prepare("DELETE FROM design_tags_master WHERE id=?")->execute([$id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción desconocida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
