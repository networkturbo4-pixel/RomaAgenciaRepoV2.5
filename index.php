<?php
// index.php
session_start();

// PREVENT CACHING: Force the browser to always fetch the latest version of the CRM
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'config/database.php';

// Instantiate DB and connect
$database = new Database();
$db = $database->getConnection();

// Fetch Global Settings
$stmt = $db->query("SELECT * FROM settings");
$global_settings_raw = $stmt->fetchAll();
$global_settings = [];
foreach ($global_settings_raw as $row) {
    $global_settings[$row['setting_key']] = $row['setting_value'];
}

// Basic Routing
$module = !empty($_GET['module']) ? $_GET['module'] : 'dashboard';
$action = !empty($_GET['action']) ? $_GET['action'] : 'index';

// Check Authentication
if (!isset($_SESSION['user_id'])) {
    if ($module !== 'auth' 
        && !($module === 'work_orders' && $action === 'public') 
        && !($module === 'chat' && $action === 'public')
        && !($module === 'mensajes' && $action === 'guest')
        && !($module === 'forms' && $action === 'fill')
        && !($module === 'forms' && $action === 'ajax_submit_form')
        && !($module === 'admin' && $action === 'payment_note_webview' && isset($_GET['view']) && $_GET['view'] === 'public')
        && !($module === 'pizarras' && $action === 'join_invite')
        && !($module === 'pizarras' && $action === 'view')
        && $module !== 'public'
    ) {
        header("Location: index.php?module=auth&action=login");
        exit();
    }
} else {
    // User is logged in, fetch permissions
    $stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmtRole->execute([$_SESSION['user_id']]);
    $role_id = $stmtRole->fetchColumn();

    $user_permissions = [];
    $allowed_modules = ['auth', 'dashboard', 'workspace', 'drive', 'config', 'clients', 'work_orders', 'admin', 'services', 'calendar', 'community', 'project_board', 'month_board', 'quotes', 'forms', 'client_portal', 'contracts', 'conexiones', 'projects', 'reuniones', 'herramientas', 'pizarras', 'mensajes', 'whatsapp', 'romita', 'task_manager', 'desarrollo_marca'];
    
    if ($role_id) {
        if ($role_id == 1) {
            // Administrador role gets all permissions by default
            $user_permissions = $allowed_modules;
        } else {
            $stmtPerms = $db->prepare("SELECT module_name FROM role_permissions WHERE role_id = ?");
            $stmtPerms->execute([$role_id]);
            $user_permissions = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);
        }
        $_SESSION['user_permissions'] = $user_permissions;
    }

    // Enforce permission
    if ($module !== 'auth' 
        && !($module === 'work_orders' && $action === 'public')
        && !($module === 'forms' && $action === 'fill')
        && !($module === 'forms' && $action === 'ajax_submit_form')
        && !($module === 'forms' && $action === 'view_submission')
        && !($module === 'admin' && $action === 'payment_note_webview' && isset($_GET['view']) && $_GET['view'] === 'public')
        && !($module === 'pizarras' && $action === 'join_invite')
        && !($module === 'pizarras' && $action === 'view')
        && $module !== 'public'
    ) {
        if (!in_array($module, $user_permissions)) {
            if (!empty($user_permissions)) {
                $first = $user_permissions[0];
                header("Location: index.php?module={$first}&action=index");
                exit();
            } else {
                echo "<div style='padding:2rem; font-family:sans-serif;'><h2>403 - Acceso Denegado</h2><p>No tienes permisos para ningún módulo.</p><a href='index.php?module=auth&action=logout'>Cerrar sesión</a></div>";
                exit();
            }
        }
    }
}

// Map modules to their respective files
$allowed_modules = ['auth', 'dashboard', 'workspace', 'drive', 'config', 'clients', 'work_orders', 'admin', 'services', 'calendar', 'community', 'project_board', 'month_board', 'quotes', 'forms', 'client_portal', 'public', 'contracts', 'conexiones', 'projects', 'reuniones', 'herramientas', 'pizarras', 'mensajes', 'whatsapp', 'romita', 'task_manager', 'desarrollo_marca'];
if (in_array($module, $allowed_modules)) {
    $module_file = "modules/{$module}/{$action}.php";
    if (file_exists($module_file)) {
        require_once $module_file;
    } else {
        echo "404 - Action Not Found: " . htmlspecialchars($module_file);
    }
} else {
    echo "404 - Module Not Found";
}
?>
