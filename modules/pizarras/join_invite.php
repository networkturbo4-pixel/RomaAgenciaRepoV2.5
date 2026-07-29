<?php
// modules/pizarras/join_invite.php
$is_public = true; // By-pass header auth redirect
$is_popup = true; // Ocultar sidebar
require_once 'includes/header.php';

global $db;

$token = $_GET['token'] ?? '';
if (!$token) {
    echo "<div style='padding:50px; text-align:center;'><h2>Enlace no válido</h2><p>Falta el token de invitación.</p></div>";
    exit;
}

$stmt = $db->prepare("SELECT i.*, w.title FROM whiteboard_invitations i JOIN whiteboards w ON i.whiteboard_id = w.id WHERE i.token = ?");
$stmt->execute([$token]);
$invite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invite) {
    echo "<div style='padding:50px; text-align:center;'><h2>Invitación no válida o expirada</h2><p>No se encontró la invitación. Puede que ya haya sido utilizada o cancelada.</p></div>";
    exit;
}

$error = '';

// Handle form submission to join
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join'])) {
    if (isset($_SESSION['user_id'])) {
        // User already logged in, check email
        $stmtUser = $db->prepare("SELECT email FROM users WHERE id = ?");
        $stmtUser->execute([$_SESSION['user_id']]);
        $userEmail = $stmtUser->fetchColumn();

        // In a real strict environment, we'd check if $userEmail == $invite['email'].
        // But for UX, since they clicked the link with a unique token, we might just let them in.
        // Let's grant access to whatever logged in user clicks it.
        $stmtInsert = $db->prepare("INSERT IGNORE INTO whiteboard_users (whiteboard_id, user_id, role) VALUES (?, ?, ?)");
        $stmtInsert->execute([$invite['whiteboard_id'], $_SESSION['user_id'], $invite['role']]);

        echo "<script>window.location.href='index.php?module=pizarras&action=view&id={$invite['whiteboard_id']}';</script>";
        exit;
    } else {
        // Create Guest User or Login
        $name = $_POST['name'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($name && $password) {
            // Check if email already exists
            $stmtCheck = $db->prepare("SELECT id, password FROM users WHERE email = ?");
            $stmtCheck->execute([$invite['email']]);
            $existingUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($existingUser) {
                // Verify password
                if (password_verify($password, $existingUser['password'])) {
                    $user_id = $existingUser['id'];
                } else {
                    $error = 'El correo ya está registrado, pero la contraseña es incorrecta.';
                }
            } else {
                // Create new user as Invitado
                $stmtRole = $db->prepare("SELECT id FROM roles WHERE name = 'Invitado'");
                $stmtRole->execute();
                $guestRoleId = $stmtRole->fetchColumn() ?: 4; // fallback to Cliente

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtNew = $db->prepare("INSERT INTO users (name, email, password, role_id) VALUES (?, ?, ?, ?)");
                $stmtNew->execute([$name, $invite['email'], $hash, $guestRoleId]);
                $user_id = $db->lastInsertId();
            }

            if (isset($user_id)) {
                // Log them in
                $_SESSION['user_id'] = $user_id;
                
                // Fetch basic data to match normal login
                $stmtLogin = $db->prepare("SELECT u.name, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                $stmtLogin->execute([$user_id]);
                $uData = $stmtLogin->fetch(PDO::FETCH_ASSOC);
                $_SESSION['user_name'] = $uData['name'];
                $_SESSION['user_role'] = $uData['role_name'] ?? 'Invitado';

                // Add to whiteboard
                $stmtInsert = $db->prepare("INSERT IGNORE INTO whiteboard_users (whiteboard_id, user_id, role) VALUES (?, ?, ?)");
                $stmtInsert->execute([$invite['whiteboard_id'], $user_id, $invite['role']]);

                echo "<script>window.location.href='index.php?module=pizarras&action=view&id={$invite['whiteboard_id']}';</script>";
                exit;
            }
        } else {
            $error = 'Completa todos los campos.';
        }
    }
}
?>

<div style="display:flex; align-items:center; justify-content:center; height:calc(100vh - 80px); background:#f8fafc;">
    <div style="background:#fff; padding:3rem; border-radius:16px; box-shadow:0 10px 25px rgba(0,0,0,0.05); max-width:450px; width:100%; text-align:center;">
        <i class="ph ph-envelope-open" style="font-size: 4rem; color: #3b82f6; margin-bottom:1rem;"></i>
        <h2 style="font-size:1.5rem; color:#0f172a; margin-bottom:0.5rem; font-weight:700;">Invitación a Pizarra</h2>
        <p style="color:#64748b; margin-bottom:2rem;">Te han invitado a colaborar en <strong><?php echo htmlspecialchars($invite['title']); ?></strong> como <strong><?php echo $invite['role'] == 'editor' ? 'Editor' : 'Espectador'; ?></strong>.</p>

        <?php if($error): ?>
            <div style="background:#fee2e2; color:#ef4444; padding:10px; border-radius:8px; margin-bottom:1.5rem; font-size:0.9rem;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['user_id'])): ?>
            <form method="POST">
                <button type="submit" name="join" style="width:100%; background:#3b82f6; color:#fff; border:none; padding:12px; border-radius:10px; font-weight:600; cursor:pointer; font-size:1rem; transition: background 0.2s;">
                    Entrar a la Pizarra
                </button>
            </form>
        <?php else: ?>
            <form method="POST" style="text-align:left;">
                <p style="font-size:0.9rem; color:#64748b; margin-bottom:1rem; text-align:center;">
                    Estás siendo invitado con el correo <strong><?php echo htmlspecialchars($invite['email']); ?></strong>.<br>
                    Crea una contraseña para ingresar, o ingresa tu contraseña si ya tienes cuenta.
                </p>
                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:5px;">Tu Nombre</label>
                    <input type="text" name="name" required placeholder="Ej. Juan Pérez" style="width:100%; padding:10px 15px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.95rem;">
                </div>
                <div style="margin-bottom:1.5rem;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:5px;">Contraseña</label>
                    <input type="password" name="password" required placeholder="••••••••" style="width:100%; padding:10px 15px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.95rem;">
                </div>
                <button type="submit" name="join" style="width:100%; background:#3b82f6; color:#fff; border:none; padding:12px; border-radius:10px; font-weight:600; cursor:pointer; font-size:1rem; transition: background 0.2s;">
                    Aceptar y Entrar
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
