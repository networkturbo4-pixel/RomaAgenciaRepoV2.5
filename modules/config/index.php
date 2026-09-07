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
            if (strpos($action_type, 'ajax_') === 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Acceso denegado.']);
                exit();
            }
            throw new Exception('Acceso Denegado: Solo el Administrador principal puede realizar modificaciones.');
        }

        if ($action_type === 'ajax_toggle_role_attendance') {
            header('Content-Type: application/json');
            $role_id = (int)($_POST['role_id'] ?? 0);
            $status = (int)($_POST['status'] ?? 1);
            if ($role_id == 1) {
                echo json_encode(['success' => false, 'error' => 'El Administrador siempre está exento de asistencia.']);
                exit();
            }
            $stmt = $db->prepare("UPDATE roles SET requires_attendance = ? WHERE id = ?");
            $stmt->execute([$status, $role_id]);
            echo json_encode(['success' => true, 'status' => $status]);
            exit();
        } elseif ($action_type === 'ajax_toggle_user_attendance') {
            header('Content-Type: application/json');
            $user_id = (int)($_POST['user_id'] ?? 0);
            $status = (int)($_POST['status'] ?? 1);
            $stmt = $db->prepare("UPDATE users SET requires_attendance = ? WHERE id = ?");
            $stmt->execute([$status, $user_id]);
            echo json_encode(['success' => true, 'status' => $status]);
            exit();
        }

        if (in_array($action_type, ['personalization', 'company', 'drive', 'backups', 'updates', 'mercadopago', 'google_workspace', 'ia'])) {
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
            $requires_attendance = isset($_POST['requires_attendance']) ? 1 : 0;
            $modules = $_POST['modules'] ?? [];
            
            $stmt = $db->prepare("INSERT INTO roles (name, description, requires_attendance) VALUES (?, ?, ?)");
            $stmt->execute([$name, $desc, $requires_attendance]);
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
            $requires_attendance = ($role_id == 1) ? 0 : (isset($_POST['requires_attendance']) ? 1 : 0);
            $modules = $_POST['modules'] ?? [];
            
            // Cannot edit admin role id 1 name usually, but we'll allow it or just update
            $stmt = $db->prepare("UPDATE roles SET name = ?, description = ?, requires_attendance = ? WHERE id = ?");
            $stmt->execute([$name, $desc, $requires_attendance, $role_id]);
            
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
            $requires_attendance = ($role_id == 1) ? 0 : (isset($_POST['requires_attendance']) ? 1 : 0);
            
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt_check->execute([$email]);
            if ($stmt_check->fetchColumn() > 0) {
                $error = 'El correo electrónico ya está registrado por otro usuario.';
            } else {
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role_id, requires_attendance) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $password, $role_id, $requires_attendance]);
                $success = 'Usuario creado exitosamente.';
            }
        } elseif ($action_type === 'user_edit') {
            $active_tab = 'tab-users';
            $user_id = $_POST['user_id'] ?? 0;
            $name = $_POST['user_name'] ?? '';
            $email = $_POST['user_email'] ?? '';
            $role_id = $_POST['user_role'] ?? 1;
            $requires_attendance = ($role_id == 1) ? 0 : (isset($_POST['requires_attendance']) ? 1 : 0);
            
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $stmt_check->execute([$email, $user_id]);
            if ($stmt_check->fetchColumn() > 0) {
                $error = 'El correo electrónico ya está registrado por otro usuario.';
            } else {
                if (!empty($_POST['user_password'])) {
                    $password = password_hash($_POST['user_password'], PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, role_id = ?, password = ?, requires_attendance = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $role_id, $password, $requires_attendance, $user_id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, role_id = ?, requires_attendance = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $role_id, $requires_attendance, $user_id]);
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

// Counters and badges for master sidebar
$total_roles = 0;
$total_users = 0;
try {
    $total_roles = (int)$db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    $total_users = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {}
?>

<link rel="stylesheet" href="assets/css/config.css?v=<?php echo file_exists('assets/css/config.css') ? filemtime('assets/css/config.css') : '1'; ?>">

<div class="settings-app-container">
    <!-- Modern App Header -->
    <div class="settings-header">
        <div class="settings-header-left">
            <div class="settings-header-icon">
                <i class="ph ph-sliders-horizontal"></i>
            </div>
            <div>
                <h1 class="settings-header-title">
                    Configuración del Sistema
                    <span class="settings-header-badge">Centro de Control</span>
                </h1>
                <p class="settings-header-desc">Administra la personalización de marca, roles de acceso, integraciones en la nube y mantenimiento general de la plataforma.</p>
            </div>
        </div>
        <div class="settings-header-right">
            <div class="settings-status-chip">
                <span class="status-dot"></span>
                <span>Plataforma Operativa</span>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="settings-alert success">
            <i class="ph ph-check-circle-fill"></i>
            <div><?php echo htmlspecialchars($success); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="settings-alert error">
            <i class="ph ph-warning-circle-fill"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <!-- Master-Detail Layout -->
    <div class="settings-layout">
        <!-- Master Sidebar -->
        <aside class="settings-sidebar">
            <div class="settings-search-box">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" id="settings-search-input" class="settings-search-input" placeholder="Buscar ajuste..." autocomplete="off">
                <button type="button" id="settings-search-clear" class="settings-search-clear" title="Limpiar búsqueda"><i class="ph ph-x"></i></button>
            </div>

            <!-- Group 1: Apariencia & Marca -->
            <div class="settings-nav-group">
                <div class="settings-nav-heading">Apariencia & Marca</div>
                <div class="settings-nav-list">
                    <button class="settings-tab tab-btn <?php echo $active_tab === 'tab-personalization' ? 'active' : ''; ?>" data-tab="tab-personalization">
                        <span class="tab-icon-wrap"><i class="ph ph-palette"></i></span>
                        <span class="tab-label">Personalización</span>
                        <span class="nav-badge">Marca</span>
                    </button>
                    <button class="settings-tab tab-btn <?php echo $active_tab === 'tab-company' ? 'active' : ''; ?>" data-tab="tab-company">
                        <span class="tab-icon-wrap"><i class="ph ph-buildings"></i></span>
                        <span class="tab-label">Datos de la Empresa</span>
                    </button>
                </div>
            </div>

            <!-- Group 2: Accesos & Seguridad -->
            <div class="settings-nav-group">
                <div class="settings-nav-heading">Accesos & Seguridad</div>
                <div class="settings-nav-list">
                    <button class="settings-tab tab-btn <?php echo $active_tab === 'tab-users' ? 'active' : ''; ?>" data-tab="tab-users">
                        <span class="tab-icon-wrap"><i class="ph ph-users"></i></span>
                        <span class="tab-label">Usuarios</span>
                        <span class="nav-badge"><?php echo $total_users; ?></span>
                    </button>
                    <button class="settings-tab tab-btn <?php echo $active_tab === 'tab-roles' ? 'active' : ''; ?>" data-tab="tab-roles">
                        <span class="tab-icon-wrap"><i class="ph ph-shield-check"></i></span>
                        <span class="tab-label">Roles y Permisos</span>
                        <span class="nav-badge"><?php echo $total_roles; ?></span>
                    </button>
                </div>
            </div>

            <!-- Group 3: Integraciones -->
            <div class="settings-nav-group">
                <div class="settings-nav-heading">Integraciones & Servicios</div>
                <div class="settings-nav-list">
                    <button class="settings-tab tab-btn <?php echo $active_tab === 'tab-drive' ? 'active' : ''; ?>" data-tab="tab-drive">
                        <span class="tab-icon-wrap"><i class="ph ph-google-drive-logo"></i></span>
                        <span class="tab-label">Google Drive</span>
                        <span class="nav-badge">Cloud</span>
                    </button>
                    <button class="settings-tab tab-btn <?php echo $active_tab === 'tab-google_workspace' ? 'active' : ''; ?>" data-tab="tab-google_workspace">
                        <span class="tab-icon-wrap"><i class="ph ph-google-logo"></i></span>
                        <span class="tab-label">Google Workspace</span>
                        <span class="nav-badge">Meet</span>
                    </button>
                    <button class="settings-tab tab-btn <?php echo $active_tab === 'tab-mercadopago' ? 'active' : ''; ?>" data-tab="tab-mercadopago">
                        <span class="tab-icon-wrap"><i class="ph ph-credit-card"></i></span>
                        <span class="tab-label">Mercado Pago</span>
                        <span class="nav-badge">Pagos</span>
                    </button>
                    <button class="settings-tab tab-btn <?php echo $active_tab === 'tab-ia' ? 'active' : ''; ?>" data-tab="tab-ia">
                        <span class="tab-icon-wrap"><i class="ph ph-sparkle"></i></span>
                        <span class="tab-label">Romita IA (Gemini)</span>
                        <span class="nav-badge">IA</span>
                    </button>
                </div>
            </div>

            <!-- Group 4: Mantenimiento & Sistema -->
            <div class="settings-nav-group">
                <div class="settings-nav-heading">Mantenimiento & Sistema</div>
                <div class="settings-nav-list">
                    <button class="settings-tab tab-btn <?php echo $active_tab === 'tab-backups' ? 'active' : ''; ?>" data-tab="tab-backups">
                        <span class="tab-icon-wrap"><i class="ph ph-database"></i></span>
                        <span class="tab-label">Copias de Seguridad</span>
                        <span class="nav-badge">ZIP</span>
                    </button>
                    <button class="settings-tab tab-btn <?php echo $active_tab === 'tab-updates' ? 'active' : ''; ?>" data-tab="tab-updates">
                        <span class="tab-icon-wrap"><i class="ph ph-rocket-launch"></i></span>
                        <span class="tab-label">Actualizaciones</span>
                        <span class="nav-badge">1 Clic</span>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Detail Contents -->
        <main class="settings-main">
            <!-- Tab 1: Personalization -->
            <div id="tab-personalization" class="settings-pane tab-pane <?php echo $active_tab === 'tab-personalization' ? 'active' : ''; ?>">
                <?php include 'modules/config/tabs/personalization.php'; ?>
            </div>

            <!-- Tab 2: Company Data -->
            <div id="tab-company" class="settings-pane tab-pane <?php echo $active_tab === 'tab-company' ? 'active' : ''; ?>">
                <?php include 'modules/config/tabs/company.php'; ?>
            </div>

            <!-- Tab 3: Roles -->
            <div id="tab-roles" class="settings-pane tab-pane <?php echo $active_tab === 'tab-roles' ? 'active' : ''; ?>">
                <?php include 'modules/config/tabs/roles.php'; ?>
            </div>

            <!-- Tab 4: Users -->
            <div id="tab-users" class="settings-pane tab-pane <?php echo $active_tab === 'tab-users' ? 'active' : ''; ?>">
                <?php include 'modules/config/tabs/users.php'; ?>
            </div>

            <!-- Tab 5: Drive -->
            <div id="tab-drive" class="settings-pane tab-pane <?php echo $active_tab === 'tab-drive' ? 'active' : ''; ?>">
                <?php include 'modules/config/tabs/drive.php'; ?>
            </div>

            <!-- Tab 5b: Google Workspace -->
            <div id="tab-google_workspace" class="settings-pane tab-pane <?php echo $active_tab === 'tab-google_workspace' ? 'active' : ''; ?>">
                <?php include 'modules/config/tabs/google_workspace.php'; ?>
            </div>

            <!-- Tab 6: Backups -->
            <div id="tab-backups" class="settings-pane tab-pane <?php echo $active_tab === 'tab-backups' ? 'active' : ''; ?>">
                <?php include 'modules/config/tabs/backups.php'; ?>
            </div>

            <!-- Tab 6b: System Updates -->
            <div id="tab-updates" class="settings-pane tab-pane <?php echo $active_tab === 'tab-updates' ? 'active' : ''; ?>">
                <?php include 'modules/config/tabs/updates.php'; ?>
            </div>

            <!-- Tab 7: Mercado Pago -->
            <div id="tab-mercadopago" class="settings-pane tab-pane <?php echo $active_tab === 'tab-mercadopago' ? 'active' : ''; ?>">
                <?php include 'modules/config/tabs/mercadopago.php'; ?>
            </div>

            <!-- Tab 9: Inteligencia Artificial (Gemini) -->
            <div id="tab-ia" class="settings-pane tab-pane <?php echo $active_tab === 'tab-ia' ? 'active' : ''; ?>">
                <?php include 'modules/config/tabs/ia.php'; ?>
            </div>
        </main>
    </div>
</div>

<script>
// Settings App Interactivity: Search, Tab Sync and State
document.addEventListener('DOMContentLoaded', () => {
    // Search filter logic
    const searchInput = document.getElementById('settings-search-input');
    const searchClear = document.getElementById('settings-search-clear');
    const navItems = document.querySelectorAll('.settings-sidebar .tab-btn');
    const navGroups = document.querySelectorAll('.settings-nav-group');

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase().trim();
            if (searchClear) searchClear.style.display = query ? 'block' : 'none';

            navGroups.forEach(group => {
                let groupHasVisible = false;
                const items = group.querySelectorAll('.tab-btn');
                items.forEach(item => {
                    const label = item.querySelector('.tab-label')?.textContent.toLowerCase() || '';
                    const badge = item.querySelector('.nav-badge')?.textContent.toLowerCase() || '';
                    const matches = label.includes(query) || badge.includes(query);
                    item.style.display = matches ? 'flex' : 'none';
                    if (matches) groupHasVisible = true;
                });
                group.style.display = groupHasVisible ? 'block' : 'none';
            });
        });

        if (searchClear) {
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            });
        }
    }

    // URL Hash support for tabs
    if (window.location.hash) {
        const hashTab = window.location.hash.replace('#', '');
        const targetBtn = document.querySelector(`.settings-sidebar .tab-btn[data-tab="${hashTab}"]`);
        if (targetBtn) {
            targetBtn.click();
        }
    }

    // Update URL Hash on tab switch
    navItems.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            if (tabId && history.pushState) {
                history.pushState(null, null, '#' + tabId);
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
