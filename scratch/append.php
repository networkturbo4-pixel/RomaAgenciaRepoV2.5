<?php
$content = file_get_contents('c:\xampp\htdocs\CESARMENDOZA\ajax\ajax_whiteboard.php');
$new_code = <<<'CODE'

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
            $token = bin2hex(random_bytes(16));
            $stmtInsertInv->execute([$board_id, $email, $role, $token]);
            
            $link = $app_url . '/index.php?module=pizarras&action=join_invite&token=' . $token;
            $subject = "Invitacion a colaborar en la pizarra: " . $title;
            $message = "Has sido invitado a colaborar en la pizarra '{$title}'.\r\n\r\n";
            $message .= "Puedes acceder mediante el siguiente enlace:\r\n$link\r\n\r\n";
            $message .= "Rol: " . ($role == 'editor' ? 'Editor' : 'Lector');
            $headers = "From: no-reply@crm.com\r\n";
            
            @mail($email, $subject, $message, $headers);
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
CODE;
file_put_contents('c:\xampp\htdocs\CESARMENDOZA\ajax\ajax_whiteboard.php', $content . "\n" . $new_code);
echo "OK";
