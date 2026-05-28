<?php
// modules/config/index.php
require_once 'includes/header.php';

$success = '';
$error = '';
$active_tab = 'tab-personalization'; // Default tab

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $db;
    $action_type = $_POST['action_type'] ?? '';

    try {
        $stmt_admin_check = $db->prepare("SELECT role_id FROM users WHERE id = ?");
        $stmt_admin_check->execute([$_SESSION['user_id']]);
        if ($stmt_admin_check->fetchColumn() != 1) {
            throw new Exception('Acceso Denegado: Solo el Administrador principal puede realizar modificaciones.');
        }

        if (in_array($action_type, ['personalization', 'company', 'drive'])) {
            $active_tab = 'tab-' . $action_type;
            // Generic settings update
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = :key");
            $stmt_update = $db->prepare("UPDATE settings SET setting_value = :val WHERE setting_key = :key");
            $stmt_insert = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :val)");
            
            foreach ($_POST as $key => $val) {
                if ($key !== 'action_type') {
                    $stmt_check->execute([':key' => $key]);
                    if ($stmt_check->fetchColumn() > 0) {
                        $stmt_update->execute([':val' => $val, ':key' => $key]);
                    } else {
                        $stmt_insert->execute([':val' => $val, ':key' => $key]);
                    }
                }
            }
            
            // Handle File Uploads for logos/favicon
            $upload_dir = 'uploads/';
            $files_to_handle = ['favicon', 'logo_light', 'logo_dark', 'logo_collapsed'];
            foreach ($files_to_handle as $file_input) {
                if (isset($_FILES[$file_input]) && $_FILES[$file_input]['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES[$file_input]['name'], PATHINFO_EXTENSION);
                    $filename = $file_input . '_' . time() . '.' . $ext;
                    $target_path = $upload_dir . $filename;
                    if (move_uploaded_file($_FILES[$file_input]['tmp_name'], $target_path)) {
                        $stmt_check->execute([':key' => $file_input]);
                        if ($stmt_check->fetchColumn() > 0) {
                            $stmt_update->execute([':val' => $target_path, ':key' => $file_input]);
                        } else {
                            $stmt_insert->execute([':val' => $target_path, ':key' => $file_input]);
                        }
                    }
                }
            }

            $success = 'Configuración guardada exitosamente.';
        } elseif ($action_type === 'role_create') {
            $active_tab = 'tab-roles';
            $name = $_POST['role_name'] ?? '';
            $desc = $_POST['role_desc'] ?? '';
            $modules = $_POST['modules'] ?? [];
            
            $stmt = $db->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
            $role_id = $db->lastInsertId();
            
            $stmt_perm = $db->prepare("INSERT INTO role_permissions (role_id, module_name) VALUES (?, ?)");
            foreach($modules as $mod) {
                $stmt_perm->execute([$role_id, $mod]);
            }
            $success = 'Rol creado exitosamente.';
        } elseif ($action_type === 'role_edit') {
            $active_tab = 'tab-roles';
            $role_id = $_POST['role_id'] ?? 0;
            $name = $_POST['role_name'] ?? '';
            $desc = $_POST['role_desc'] ?? '';
            $modules = $_POST['modules'] ?? [];
            
            // Cannot edit admin role id 1 name usually, but we'll allow it or just update
            $stmt = $db->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $desc, $role_id]);
            
            // Update permissions: delete old, insert new
            $stmt_del = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt_del->execute([$role_id]);
            
            $stmt_perm = $db->prepare("INSERT INTO role_permissions (role_id, module_name) VALUES (?, ?)");
            foreach($modules as $mod) {
                $stmt_perm->execute([$role_id, $mod]);
            }
            $success = 'Rol actualizado exitosamente.';
        } elseif ($action_type === 'role_delete') {
            $active_tab = 'tab-roles';
            $role_id = $_POST['role_id'] ?? 0;
            if ($role_id == 1) {
                $error = 'No se puede eliminar el rol de Administrador principal.';
            } else {
                $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
                $stmt->execute([$role_id]);
                $success = 'Rol eliminado exitosamente.';
            }
        } elseif ($action_type === 'user_create') {
            $active_tab = 'tab-users';
            $name = $_POST['user_name'] ?? '';
            $email = $_POST['user_email'] ?? '';
            $password = !empty($_POST['user_password']) ? password_hash($_POST['user_password'], PASSWORD_DEFAULT) : null;
            $role_id = $_POST['user_role'] ?? 1;
            
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt_check->execute([$email]);
            if ($stmt_check->fetchColumn() > 0) {
                $error = 'El correo electrónico ya está registrado por otro usuario.';
            } else {
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $password, $role_id]);
                $success = 'Usuario creado exitosamente.';
            }
        } elseif ($action_type === 'user_edit') {
            $active_tab = 'tab-users';
            $user_id = $_POST['user_id'] ?? 0;
            $name = $_POST['user_name'] ?? '';
            $email = $_POST['user_email'] ?? '';
            $role_id = $_POST['user_role'] ?? 1;
            
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $stmt_check->execute([$email, $user_id]);
            if ($stmt_check->fetchColumn() > 0) {
                $error = 'El correo electrónico ya está registrado por otro usuario.';
            } else {
                if (!empty($_POST['user_password'])) {
                    $password = password_hash($_POST['user_password'], PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, role_id = ?, password = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $role_id, $password, $user_id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, role_id = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $role_id, $user_id]);
                }
                $success = 'Usuario actualizado exitosamente.';
            }
        } elseif ($action_type === 'user_delete') {
            $active_tab = 'tab-users';
            $user_id = $_POST['user_id'] ?? 0;
            if ($user_id == $_SESSION['user_id']) {
                $error = 'No puedes eliminar tu propio usuario activo.';
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $success = 'Usuario eliminado exitosamente.';
            }
        }
    } catch(Exception $e) {
        $error = 'Error al procesar la solicitud: ' . $e->getMessage();
    }
}

// Fetch current settings
global $db;
$stmt = $db->query("SELECT * FROM settings");
$settings_raw = $stmt->fetchAll();
$settings = [];
foreach ($settings_raw as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Check if current user is admin
$stmt_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt_admin->execute([$_SESSION['user_id']]);
$is_admin = ($stmt_admin->fetchColumn() == 1);
?>

<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="width: 56px; height: 56px; background: var(--bg-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
            <i class="ph ph-gear" style="font-size: 1.75rem; color: var(--primary-color);"></i>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Configuración Avanzada</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Administra personalización, datos de empresa, roles y usuarios.</p>
        </div>
    </div>
</div>

<?php if ($success): ?>
    <div style="background: #d1fae5; color: #059669; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
        <i class="ph ph-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background: #fee2e2; color: #ef4444; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
        <i class="ph ph-warning-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="tabs-nav">
        <button class="tab-btn <?php echo $active_tab === 'tab-personalization' ? 'active' : ''; ?>" data-tab="tab-personalization">
            <i class="ph ph-palette"></i> Personalización
        </button>
        <button class="tab-btn <?php echo $active_tab === 'tab-company' ? 'active' : ''; ?>" data-tab="tab-company">
            <i class="ph ph-buildings"></i> Datos de la Empresa
        </button>
        <button class="tab-btn <?php echo $active_tab === 'tab-roles' ? 'active' : ''; ?>" data-tab="tab-roles">
            <i class="ph ph-shield-check"></i> Roles y Permisos
        </button>
        <button class="tab-btn <?php echo $active_tab === 'tab-users' ? 'active' : ''; ?>" data-tab="tab-users">
            <i class="ph ph-users"></i> Usuarios
        </button>
        <button class="tab-btn <?php echo $active_tab === 'tab-drive' ? 'active' : ''; ?>" data-tab="tab-drive">
            <i class="ph ph-google-drive-logo"></i> Google Drive
        </button>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content">
        <!-- Tab 1: Personalization -->
        <div id="tab-personalization" class="tab-pane <?php echo $active_tab === 'tab-personalization' ? 'active' : ''; ?>">
            <?php include 'modules/config/tabs/personalization.php'; ?>
        </div>

        <!-- Tab 2: Company Data -->
        <div id="tab-company" class="tab-pane <?php echo $active_tab === 'tab-company' ? 'active' : ''; ?>">
            <?php include 'modules/config/tabs/company.php'; ?>
        </div>

        <!-- Tab 3: Roles -->
        <div id="tab-roles" class="tab-pane <?php echo $active_tab === 'tab-roles' ? 'active' : ''; ?>">
            <?php include 'modules/config/tabs/roles.php'; ?>
        </div>

        <!-- Tab 4: Users -->
        <div id="tab-users" class="tab-pane <?php echo $active_tab === 'tab-users' ? 'active' : ''; ?>">
            <?php include 'modules/config/tabs/users.php'; ?>
        </div>

        <!-- Tab 5: Drive -->
        <div id="tab-drive" class="tab-pane <?php echo $active_tab === 'tab-drive' ? 'active' : ''; ?>">
            <?php include 'modules/config/tabs/drive.php'; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
