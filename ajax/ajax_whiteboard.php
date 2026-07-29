<?php
// ajax/ajax_whiteboard.php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'] ?? 1; // Fallback for dev if needed

$input = json_decode(file_get_contents('php://input'), true);
if (!$input && !empty($_POST)) {
    $input = $_POST;
}

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'No input']);
    exit;
}

$action = $input['action'] ?? '';

$stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmtRole->execute([$user_id]);
$role_id = $stmtRole->fetchColumn();
$is_admin = ($role_id == 1);

if ($action === 'create') {
    $title = $input['title'] ?? 'Sin título';
    $invites = isset($input['invites']) ? json_decode($input['invites'], true) : [];
    
    // Handle Profile Pic upload
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/whiteboards/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileName = uniqid() . '_' . basename($_FILES['profile_pic']['name']);
        $destPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destPath)) {
            $profile_pic = 'uploads/whiteboards/' . $fileName;
        }
    }
    
    $stmt = $db->prepare("INSERT INTO whiteboards (title, created_by, profile_pic) VALUES (?, ?, ?)");
    if ($stmt->execute([$title, $user_id, $profile_pic])) {
        $board_id = $db->lastInsertId();
        
        // Add creator as editor
        $stmtCreator = $db->prepare("INSERT INTO whiteboard_users (whiteboard_id, user_id, role) VALUES (?, ?, 'editor')");
        $stmtCreator->execute([$board_id, $user_id]);
        
        // Process invites
        if (is_array($invites) && count($invites) > 0) {
            $stmtInvite = $db->prepare("INSERT INTO whiteboard_invitations (whiteboard_id, email, role, token) VALUES (?, ?, ?, ?)");
            foreach ($invites as $inv) {
                $email = filter_var($inv['email'], FILTER_SANITIZE_EMAIL);
                $role = in_array($inv['role'], ['editor', 'viewer']) ? $inv['role'] : 'viewer';
                $token = $inv['token'] ?? bin2hex(random_bytes(16));
                
                $stmtInvite->execute([$board_id, $email, $role, $token]);
            }
        }
        
        echo json_encode(['success' => true, 'id' => $board_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo crear la pizarra.']);
    }
    exit;
}

if ($action === 'update_whiteboard') {
    $id = $input['id'] ?? 0;
    $title = $input['title'] ?? 'Sin título';
    
    // Check if the current user is admin or the creator
    $stmtCheck = $db->prepare("SELECT created_by FROM whiteboards WHERE id = ?");
    $stmtCheck->execute([$id]);
    $creator_id = $stmtCheck->fetchColumn();
    
    if (!$is_admin && $creator_id != $user_id) {
        echo json_encode(['success' => false, 'error' => 'No tienes permisos para editar esta pizarra.']);
        exit;
    }
    
    // Handle Profile Pic upload
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/whiteboards/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileName = uniqid() . '_' . basename($_FILES['profile_pic']['name']);
        $destPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destPath)) {
            $profile_pic = 'uploads/whiteboards/' . $fileName;
        }
    }
    
    if ($profile_pic) {
        $stmt = $db->prepare("UPDATE whiteboards SET title = ?, profile_pic = ?, updated_at = NOW() WHERE id = ?");
        $res = $stmt->execute([$title, $profile_pic, $id]);
    } else {
        $stmt = $db->prepare("UPDATE whiteboards SET title = ?, updated_at = NOW() WHERE id = ?");
        $res = $stmt->execute([$title, $id]);
    }
    
    if ($res) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo actualizar la pizarra.']);
    }
    exit;
}

if ($action === 'update_users') {
    $id = $input['id'] ?? 0;
    
    // Check if the current user is admin or the creator
    $stmtCheck = $db->prepare("SELECT created_by FROM whiteboards WHERE id = ?");
    $stmtCheck->execute([$id]);
    $creator_id = $stmtCheck->fetchColumn();
    
    if (!$is_admin && $creator_id != $user_id) {
        echo json_encode(['success' => false, 'error' => 'No tienes permisos para modificar los usuarios de esta pizarra.']);
        exit;
    }
    
    $assigned = $input['assigned'] ?? [];
    if (!is_array($assigned)) {
        // Fallback for empty assignment
        $assigned = $assigned ? [$assigned] : [];
    }
    
    try {
        $db->beginTransaction();
        
        // Retrieve existing roles to preserve them
        $stmtOld = $db->prepare("SELECT user_id, role FROM whiteboard_users WHERE whiteboard_id = ?");
        $stmtOld->execute([$id]);
        $oldRoles = [];
        while($row = $stmtOld->fetch(PDO::FETCH_ASSOC)) {
            $oldRoles[$row['user_id']] = $row['role'];
        }
        
        // Delete all current assignments
        $stmtDel = $db->prepare("DELETE FROM whiteboard_users WHERE whiteboard_id = ?");
        $stmtDel->execute([$id]);
        
        // Re-insert with preserved roles or default to editor
        $stmtIns = $db->prepare("INSERT INTO whiteboard_users (whiteboard_id, user_id, role) VALUES (?, ?, ?)");
        foreach ($assigned as $uid) {
            if ((int)$uid > 0) {
                $role = $oldRoles[$uid] ?? 'editor';
                $stmtIns->execute([$id, $uid, $role]);
            }
        }
        
        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'No se pudieron actualizar los usuarios.']);
    }
    exit;
}

if ($action === 'delete') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'No tienes permisos para eliminar pizarras.']);
        exit;
    }
    $id = $input['id'] ?? 0;
    $stmt = $db->prepare("DELETE FROM whiteboards WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo eliminar la pizarra.']);
    }
    exit;
}

if ($action === 'save') {
    $id = $input['id'] ?? 0;
    $content = $input['content'] ?? ''; // JSON string of canvas
    $thumbnail = $input['thumbnail'] ?? null;
    
    if ($thumbnail) {
        $stmt = $db->prepare("UPDATE whiteboards SET content = ?, thumbnail = ? WHERE id = ?");
        $res = $stmt->execute([$content, $thumbnail, $id]);
    } else {
        $stmt = $db->prepare("UPDATE whiteboards SET content = ? WHERE id = ?");
        $res = $stmt->execute([$content, $id]);
    }
    
    if ($res) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo guardar la pizarra.']);
    }
    exit;
}

if ($action === 'list_user_whiteboards') {
    $search = $input['search'] ?? '';
    $where = ["1=1"];
    $params = [];
    if ($search) {
        $where[] = "title LIKE ?";
        $params[] = "%$search%";
    }
    $whereClause = implode(" AND ", $where);
    
    $sql = "SELECT id, title, created_by, folder_id, tags 
            FROM whiteboards 
            WHERE $whereClause AND (created_by = ? OR assigned_users LIKE ? OR ?)
            ORDER BY updated_at DESC LIMIT 50";
    $params[] = $user_id;
    $params[] = '%"' . $user_id . '"%';
    $params[] = $is_admin ? 1 : 0;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $whiteboards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'whiteboards' => $whiteboards]);
    exit;
}

if ($action === 'create_folder') {
    $name = $input['name'] ?? 'Nueva Carpeta';
    $color = $input['color'] ?? '#3b82f6';
    $stmt = $db->prepare("INSERT INTO whiteboard_folders (name, color, created_by) VALUES (?, ?, ?)");
    if ($stmt->execute([$name, $color, $user_id])) {
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo crear la carpeta.']);
    }
    exit;
}

if ($action === 'list_folders') {
    $stmt = $db->prepare("SELECT id, name, color FROM whiteboard_folders WHERE created_by = ? ORDER BY id DESC");
    $stmt->execute([$user_id]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'folders' => $folders]);
    exit;
}

if ($action === 'delete_folder') {
    $id = $input['id'] ?? 0;
    // Poner folder_id a NULL para las pizarras que estaban aquí
    $db->prepare("UPDATE whiteboards SET folder_id = NULL WHERE folder_id = ? AND created_by = ?")->execute([$id, $user_id]);
    
    $stmt = $db->prepare("DELETE FROM whiteboard_folders WHERE id = ? AND created_by = ?");
    if ($stmt->execute([$id, $user_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo eliminar la carpeta.']);
    }
    exit;
}

if ($action === 'move_to_folder') {
    $id = $input['id'] ?? 0;
    $folder_id = $input['folder_id'] ?? null;
    if ($folder_id === 0 || $folder_id === '') $folder_id = null;
    
    $stmt = $db->prepare("UPDATE whiteboards SET folder_id = ? WHERE id = ? AND created_by = ?");
    if ($stmt->execute([$folder_id, $id, $user_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo mover la pizarra.']);
    }
    exit;
}

if ($action === 'update_tags') {
    $id = $input['id'] ?? 0;
    $tags = json_encode($input['tags'] ?? []);
    
    $stmt = $db->prepare("UPDATE whiteboards SET tags = ? WHERE id = ? AND created_by = ?");
    if ($stmt->execute([$tags, $id, $user_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudieron actualizar las etiquetas.']);
    }
    exit;
}

if ($action === 'load') {
    $id = $input['id'] ?? 0;
    $stmt = $db->prepare("SELECT content FROM whiteboards WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['success' => true, 'content' => $row['content']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Pizarra no encontrada.']);
    }
    exit;
}

// --- NEW UNIFIED ACTIONS ---
if ($action === 'get_share_info') {
    $id = $input['id'] ?? 0;
    
    $stmtCheck = $db->prepare("SELECT title, created_by, access_type, public_role FROM whiteboards WHERE id = ?");
    $stmtCheck->execute([$id]);
    $wb = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$wb) {
        echo json_encode(['success' => false, 'error' => 'Pizarra no encontrada']);
        exit;
    }
    
    if (!$is_admin && $wb['created_by'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'No tienes permisos']);
        exit;
    }
    
    $users = [];
    
    $stmtOwner = $db->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmtOwner->execute([$wb['created_by']]);
    $owner = $stmtOwner->fetch(PDO::FETCH_ASSOC);
    if($owner) {
        $users[] = [
            'id' => 'OWNER',
            'name' => $owner['name'] . ' (Tú)',
            'email' => $owner['email'],
            'role' => 'editor'
        ];
    }
    
    $stmtSys = $db->prepare("SELECT u.id, u.name, u.email, wu.role FROM whiteboard_users wu JOIN users u ON wu.user_id = u.id WHERE wu.whiteboard_id = ? AND wu.user_id != ?");
    $stmtSys->execute([$id, $wb['created_by']]);
    while($row = $stmtSys->fetch(PDO::FETCH_ASSOC)) {
        $users[] = [
            'id' => 'USER:' . $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role']
        ];
    }
    
    $stmtInv = $db->prepare("SELECT email, role FROM whiteboard_invitations WHERE whiteboard_id = ?");
    $stmtInv->execute([$id]);
    while($row = $stmtInv->fetch(PDO::FETCH_ASSOC)) {
        $users[] = [
            'id' => $row['email'],
            'name' => $row['email'],
            'email' => $row['email'],
            'role' => $row['role']
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'data' => [
            'title' => $wb['title'],
            'access_type' => $wb['access_type'],
            'public_role' => $wb['public_role'],
            'users' => $users
        ]
    ]);
    exit;
}

function process_unified_share($db, $board_id, $users_json, $title) {
    global $user_id;
    $users = json_decode($users_json, true) ?: [];
    
    $stmtCheck = $db->prepare("SELECT created_by FROM whiteboards WHERE id = ?");
    $stmtCheck->execute([$board_id]);
    $creator_id = $stmtCheck->fetchColumn();
    $owner_id = $creator_id ?: $user_id;
    
    // Save existing invitations to preserve their tokens and avoid resending emails
    $stmtGetInvites = $db->prepare("SELECT email, token FROM whiteboard_invitations WHERE whiteboard_id = ?");
    $stmtGetInvites->execute([$board_id]);
    $existingInvites = [];
    while($row = $stmtGetInvites->fetch(PDO::FETCH_ASSOC)) {
        $existingInvites[$row['email']] = $row['token'];
    }

    $stmtDel1 = $db->prepare("DELETE FROM whiteboard_users WHERE whiteboard_id = ? AND user_id != ?");
    $stmtDel1->execute([$board_id, $owner_id]);
    
    $stmtDel2 = $db->prepare("DELETE FROM whiteboard_invitations WHERE whiteboard_id = ?");
    $stmtDel2->execute([$board_id]);
    
    $stmtInsertSys = $db->prepare("INSERT INTO whiteboard_users (whiteboard_id, user_id, role) VALUES (?, ?, ?)");
    $stmtInsertInv = $db->prepare("INSERT INTO whiteboard_invitations (whiteboard_id, email, role, token) VALUES (?, ?, ?, ?)");
    
    $app_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF'], 2);
    
    foreach($users as $u) {
        if ($u['id'] === 'OWNER') continue;
        
        $role = in_array($u['role'], ['editor', 'viewer']) ? $u['role'] : 'viewer';
        
        if (strpos($u['id'], 'USER:') === 0) {
            $sys_id = str_replace('USER:', '', $u['id']);
            $stmtInsertSys->execute([$board_id, $sys_id, $role]);
        } else {
            $email = filter_var($u['email'], FILTER_SANITIZE_EMAIL);
            
            $isNew = false;
            if (isset($existingInvites[$email])) {
                $token = $existingInvites[$email];
            } else {
                $token = bin2hex(random_bytes(16));
                $isNew = true;
            }
            
            $stmtInsertInv->execute([$board_id, $email, $role, $token]);
            
            if ($isNew) {
                $link = $app_url . '/index.php?module=pizarras&action=join_invite&token=' . $token;
                $subject = "Invitacion a colaborar en la pizarra: " . $title;
                $roleName = ($role == 'editor' ? 'Editor' : 'Lector');
                
                $bodyHtml = "
                <div style='font-family: \"Segoe UI\", Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; background-color: #f8fafc; border-radius: 12px;'>
                    <div style='background-color: #ffffff; padding: 35px; border-radius: 8px; border-top: 4px solid #10b981; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                        <h2 style='color: #0f172a; font-size: 22px; margin-top: 0; margin-bottom: 15px;'>Invitación a colaborar</h2>
                        <p style='color: #475569; font-size: 16px; line-height: 1.6;'>Has sido invitado a participar en la pizarra colaborativa <strong>\"{$title}\"</strong>.</p>
                        <p style='color: #475569; font-size: 16px; line-height: 1.6;'>Se te ha asignado el rol de: <strong style='color: #3b82f6;'>{$roleName}</strong></p>
                        
                        <div style='text-align: center; margin: 35px 0;'>
                            <a href='{$link}' style='background-color: #10b981; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; display: inline-block;'>Abrir Pizarra</a>
                        </div>
                        
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
                        <p style='color: #94a3b8; font-size: 13px; text-align: center; margin-bottom: 0;'>Si el botón no funciona, puedes copiar y pegar este enlace en tu navegador:</p>
                        <p style='color: #64748b; font-size: 13px; text-align: center; word-break: break-all; margin-top: 5px;'>{$link}</p>
                    </div>
                </div>";
                
                $bodyText = "Has sido invitado a colaborar en la pizarra '{$title}'.\r\n\r\n";
                $bodyText .= "Puedes acceder mediante el siguiente enlace:\r\n$link\r\n\r\n";
                $bodyText .= "Rol: " . $roleName;
                
                require_once '../includes/Mailer.php';
                try {
                    $mailer = new Mailer($db);
                    $mailer->sendCustomEmail($email, $email, $subject, $bodyHtml, $bodyText);
                } catch (Exception $e) {
                    error_log("Error enviando email de invitación: " . $e->getMessage());
                }
            }
        }
    }
}

if ($action === 'create_unified') {
    $title = $input['title'] ?? 'Sin título';
    $access_type = in_array($input['access_type'] ?? '', ['restricted', 'public']) ? $input['access_type'] : 'restricted';
    $public_role = in_array($input['public_role'] ?? '', ['viewer', 'editor']) ? $input['public_role'] : 'viewer';
    
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/whiteboards/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = uniqid() . '_' . basename($_FILES['profile_pic']['name']);
        $destPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destPath)) {
            $profile_pic = 'uploads/whiteboards/' . $fileName;
        }
    }
    
    $stmt = $db->prepare("INSERT INTO whiteboards (title, created_by, profile_pic, access_type, public_role) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$title, $user_id, $profile_pic, $access_type, $public_role])) {
        $board_id = $db->lastInsertId();
        
        $stmtCreator = $db->prepare("INSERT INTO whiteboard_users (whiteboard_id, user_id, role) VALUES (?, ?, 'editor')");
        $stmtCreator->execute([$board_id, $user_id]);
        
        process_unified_share($db, $board_id, $input['users'] ?? '[]', $title);
        
        echo json_encode(['success' => true, 'id' => $board_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo crear la pizarra.']);
    }
    exit;
}

if ($action === 'update_unified') {
    $id = $input['id'] ?? 0;
    $title = $input['title'] ?? 'Sin título';
    $access_type = in_array($input['access_type'] ?? '', ['restricted', 'public']) ? $input['access_type'] : 'restricted';
    $public_role = in_array($input['public_role'] ?? '', ['viewer', 'editor']) ? $input['public_role'] : 'viewer';
    
    $stmtCheck = $db->prepare("SELECT created_by FROM whiteboards WHERE id = ?");
    $stmtCheck->execute([$id]);
    $creator_id = $stmtCheck->fetchColumn();
    
    if (!$is_admin && $creator_id != $user_id) {
        echo json_encode(['success' => false, 'error' => 'No tienes permisos para editar esta pizarra.']);
        exit;
    }
    
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/whiteboards/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = uniqid() . '_' . basename($_FILES['profile_pic']['name']);
        $destPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destPath)) {
            $profile_pic = 'uploads/whiteboards/' . $fileName;
        }
    }
    
    if ($profile_pic) {
        $stmt = $db->prepare("UPDATE whiteboards SET title = ?, profile_pic = ?, access_type = ?, public_role = ?, updated_at = NOW() WHERE id = ?");
        $res = $stmt->execute([$title, $profile_pic, $access_type, $public_role, $id]);
    } else {
        $stmt = $db->prepare("UPDATE whiteboards SET title = ?, access_type = ?, public_role = ?, updated_at = NOW() WHERE id = ?");
        $res = $stmt->execute([$title, $access_type, $public_role, $id]);
    }
    
    if ($res) {
        process_unified_share($db, $id, $input['users'] ?? '[]', $title);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo actualizar la pizarra.']);
    }
    exit;
}

// Fallback
echo json_encode(['success' => false, 'error' => 'Invalid action']);