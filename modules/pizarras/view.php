<?php
// modules/pizarras/view.php
require_once 'config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_instance = new Database();
$db = $db_instance->getConnection();

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "<script>window.location.href='index.php?module=pizarras';</script>";
    exit;
}

$stmt = $db->prepare("SELECT id, title, created_by, access_type, public_role FROM whiteboards WHERE id = ?");
$stmt->execute([$id]);
$whiteboard = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$whiteboard) {
    echo "<div class='alert alert-danger'>Pizarra no encontrada.</div>";
    exit;
}

$is_public = ($whiteboard['access_type'] === 'public');
$is_popup = true; // Oculta el sidebar para que la pizarra sea a pantalla completa

require_once 'includes/header.php';


$is_logged_in = isset($_SESSION['user_id']);
$is_admin = false;
$wb_role = null;

if ($is_logged_in) {
    $stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmtRole->execute([$_SESSION['user_id']]);
    $role_id = $stmtRole->fetchColumn();
    $is_admin = ($role_id == 1);

    // Get role in whiteboard
    $stmtUserRole = $db->prepare("SELECT role FROM whiteboard_users WHERE whiteboard_id = ? AND user_id = ?");
    $stmtUserRole->execute([$id, $_SESSION['user_id']]);
    $wb_role = $stmtUserRole->fetchColumn();
} else {
    // If not logged in and whiteboard is restricted, redirect to login
    if ($whiteboard['access_type'] === 'restricted') {
        header("Location: index.php?module=auth&action=login");
        exit;
    }
}

// Check permissions
if ($whiteboard['access_type'] === 'public') {
    if (!$wb_role && (!$is_logged_in || (!$is_admin && $whiteboard['created_by'] != $_SESSION['user_id']))) {
        // Assign the public role if they don't have explicit access
        $wb_role = $whiteboard['public_role'];
    }
} else {
    if (!$is_admin && $whiteboard['created_by'] != $_SESSION['user_id'] && !$wb_role) {
        echo "<div class='alert alert-danger' style='margin:20px;'>No tienes permisos para ver esta pizarra. No has sido asignado.</div>";
        require_once 'includes/footer.php';
        exit;
    }
}

// Fallback to editor if admin or creator
if (!$wb_role) {
    $wb_role = 'editor';
}

$is_creator_or_admin = ($is_logged_in && ($is_admin || $whiteboard['created_by'] == $_SESSION['user_id']));
$is_viewer_only = ($wb_role === 'viewer');

$assigned_users = [];
$all_users = [];
if ($is_logged_in) {
    // Fetch assigned users for the invite modal
    $stmtAssigned = $db->prepare("SELECT user_id FROM whiteboard_users WHERE whiteboard_id = ?");
    $stmtAssigned->execute([$id]);
    $assigned_users = $stmtAssigned->fetchAll(PDO::FETCH_COLUMN);

    // Fetch all users for assigning to whiteboard
    $stmtUsers = $db->query("SELECT id, name FROM users WHERE id != " . (int)$_SESSION['user_id']);
    $all_users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch chats for sharing via message
$user_chats = [];
if ($is_logged_in) {
    $stmtChats = $db->prepare("
        SELECT c.id, c.type, c.name, 
               IF(c.type='direct', 
                  (SELECT u.name FROM users u JOIN msg_participants cm2 ON u.id = cm2.user_id WHERE cm2.chat_id = c.id AND cm2.user_id != ? LIMIT 1), 
                  c.name) as display_name
        FROM msg_chats c
        JOIN msg_participants cm ON c.id = cm.chat_id
        WHERE cm.user_id = ?
    ");
    $stmtChats->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $user_chats = $stmtChats->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!-- Google Fonts (Inter) -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<!-- Tom Select CSS -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.default.min.css" rel="stylesheet">
<!-- Fabric.js CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<!-- jsPDF CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<!-- PDF.js para renderizar PDFs subidos -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script>
    // Configurar el worker de PDF.js
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
</script>
<!-- Pusher -->
<script src="https://js.pusher.com/8.0/pusher.min.js"></script>



<style>
    /* Ocultar el footer estándar y partes que estorben para que el canvas ocupe la pantalla */
    body { overflow: hidden; font-family: 'Inter', sans-serif; height: 100dvh; max-height: 100dvh; }
    .app-container { height: 100%; max-height: 100%; }
    .main-content { height: 100%; max-height: 100%; min-height: 0; }
    .content-wrapper { padding: 0 !important; margin: 0 !important; flex: 1; min-height: 0; height: 100%; display: flex; flex-direction: column; position: relative; font-family: 'Inter', sans-serif; }
    
    .wb-ui-layer {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 20; display: flex; flex-direction: column;
        transition: opacity 0.3s ease;
    }
    .wb-ui-layer.presentation-mode {
        opacity: 0;
        pointer-events: none !important;
    }
    
    /* Forzar colapso del sidebar y ocultar botón de expansión */
    .sidebar-collapse-btn { display: none !important; }
    html:not(.sidebar-is-collapsed) .sidebar { width: 80px !important; } /* Fallback */

    .wb-top-left, .wb-top-right, .wb-floating-toolbar, .wb-sidebar-toggle-group, .wb-zoom-controls, .wb-context-menu {
        pointer-events: auto;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border-radius: 12px;
        display: flex; align-items: center; gap: 5px; padding: 6px 10px;
    }
    
    .wb-context-menu { position:absolute; z-index:100; padding:8px; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.12); display:none; }
    
    .wb-top-left { position: absolute; top: 15px; left: 15px; }
    .wb-top-right { position: absolute; top: 15px; right: 15px; }
    .wb-floating-toolbar { position: absolute; bottom: 25px; left: 50%; transform: translateX(-50%); border-radius: 16px; padding: 8px 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
    .wb-sidebar-toggle-group { position: absolute; top: 50%; left: 15px; transform: translateY(-50%); flex-direction: column; padding: 10px 6px; }
    .wb-zoom-controls { position: absolute; bottom: 25px; right: 15px; border-radius: 12px; padding: 6px 8px; flex-direction: row; }

    .wb-tool-btn {
        background: transparent;
        border: 1px solid transparent;
        padding: 8px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-main);
        transition: all 0.2s;
    }

    .wb-tool-btn:hover { background: color-mix(in srgb, var(--primary-color) 10%, transparent); }
    .wb-tool-btn.active { background: color-mix(in srgb, var(--primary-color) 20%, transparent); color: var(--primary-color); border-color: var(--primary-color); }
    
    [data-theme="dark"] .wb-tool-btn.active {
        background: rgba(16, 185, 129, 0.15) !important;
        color: #10b981 !important;
        border-color: #10b981 !important;
    }
    [data-theme="dark"] .wb-tool-btn:hover { background: #334155 !important; }
    
    .wb-color-picker {
        width: 30px; height: 30px; border: none; border-radius: 50%; padding: 0; cursor: pointer; overflow: hidden; outline: none;
    }
    
    .wb-canvas-container {
        flex: 1;
        background-color: #f8fafc;
        background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
        background-size: 20px 20px;
        position: relative;
        overflow: hidden;
        width: 100%;
        height: 100%;
    }

    .wb-title {
        font-weight: 600;
        font-size: 1.1rem;
        margin-right: auto;
    }
    .ctx-btn {
        background: transparent; border: none; padding: 4px 6px; border-radius: 4px; cursor: pointer; color: var(--text-main); font-size: 1rem; transition: background 0.2s;
    }
    .ctx-btn:hover { background: color-mix(in srgb, var(--text-main) 10%, transparent); }
    .ctx-btn.active { background: color-mix(in srgb, var(--primary-color) 20%, transparent); color: var(--primary-color); }
    .mention-item { padding: 6px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; }
    .mention-item:hover { background: color-mix(in srgb, var(--text-main) 10%, transparent); }
    
    [data-theme="dark"] .wb-canvas-container {
        background-color: #0f172a;
        background-image: radial-gradient(#334155 1px, transparent 1px);
    }
    [data-theme="dark"] .wb-top-left, [data-theme="dark"] .wb-top-right, [data-theme="dark"] .wb-floating-toolbar, [data-theme="dark"] .wb-sidebar-toggle-group, [data-theme="dark"] .wb-zoom-controls, [data-theme="dark"] .wb-context-menu, [data-theme="dark"] #wb-context-menu {
        background: rgba(30, 41, 59, 0.85) !important;
        border-color: rgba(51, 65, 85, 0.8) !important;
    }
    
    [data-theme="dark"] #shape-presets, [data-theme="dark"] #frame-presets, [data-theme="dark"] #wb-right-click-menu {
        background: #1e293b !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
    }
    
    [data-theme="dark"] .shape-preset-btn, [data-theme="dark"] .frame-preset-btn {
        color: #f8fafc !important;
    }
    
    [data-theme="dark"] .shape-preset-btn:hover, [data-theme="dark"] .frame-preset-btn:hover, [data-theme="dark"] .rc-item:hover {
        background: #334155 !important;
    }

    [data-theme="dark"] .ctx-btn:hover { background: #334155 !important; }
    [data-theme="dark"] select#ctx-font { background: var(--bg-surface); color: var(--text-main); }
    [data-theme="dark"] select#ctx-font option { background: #1e293b; color: #f8fafc; }
    [data-theme="dark"] .wb-template-card { background: #1e293b; border-color: #334155; }
    [data-theme="dark"] .ctx-color-transparent { border-color: #64748b !important; }
    
    /* Drag & Drop Sidebar Styles */
    .wb-sidebar {
        box-sizing: border-box;
        position: absolute;
        left: 80px;
        top: 50%;
        transform: translateY(-50%) translateX(0);
        height: 80vh; max-height: 600px;
        width: 320px; border-radius: 16px;
        background: var(--bg-panel, #ffffff);
        border: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        padding: 25px 20px;
        overflow-y: auto;
        overflow-x: hidden;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s;
        z-index: 20;
        box-shadow: 4px 4px 20px rgba(0,0,0,0.08);
    }
    [data-theme="dark"] .wb-sidebar {
        background: #0f172a !important;
        border-color: #334155 !important;
    }
    .wb-sidebar-toggle-btn {
        width: 40px; height: 40px; border-radius: 8px; background: transparent; border: none; display: flex; justify-content: center; align-items: center; cursor: pointer; color: var(--text-main); transition: background 0.2s;
    }
    .wb-sidebar-toggle-btn:hover {
        background: #f1f5f9;
    }
    [data-theme="dark"] .wb-sidebar-toggle-btn:hover {
        background: #334155 !important;
    }
    .wb-sidebar.closed {
        transform: translateY(-50%) translateX(-20px);
        opacity: 0; pointer-events: none;
    }
    .wb-template-card {
        background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; cursor: grab; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; flex-direction: column; align-items: center; text-align: center;
    }
    .wb-template-card:hover { transform: translateY(-4px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
    .wb-template-card:active { cursor: grabbing; }
    .wb-template-header {
        background: color-mix(in srgb, var(--border-color) 40%, transparent); padding: 4px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; margin-bottom: 8px; color: var(--text-main); width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .wb-template-body { display: flex; justify-content: center; padding-bottom: 5px; transform: scale(0.85); transform-origin: top center; }
    .wb-template-note { width: 100px; height: 100px; position: relative; box-shadow: 2px 2px 8px rgba(0,0,0,0.1); }
    .wb-template-card:hover .wb-note-curl { border-width: 0 0 30px 30px; border-color: transparent transparent rgba(0,0,0,0.2) rgba(0,0,0,0.1); }
    .wb-note-curl {
        position: absolute; bottom: 0; right: 0; border-width: 0 0 15px 15px; border-style: solid; border-color: transparent transparent rgba(0,0,0,0.15) rgba(0,0,0,0.1); transition: all 0.2s;
    }
    [data-theme="dark"] .wb-template-card { background: #1e293b; border-color: #334155; }
    [data-theme="dark"] .wb-template-header { background: #334155; }
    
    /* Popover Dark Mode */
    [data-theme="dark"] #wb-comment-popover { background: #1e293b !important; border-color: #334155 !important; box-shadow: 0 8px 30px rgba(0,0,0,0.5) !important; }
    [data-theme="dark"] #wb-comment-popover textarea { background: #0f172a !important; color: #f8fafc !important; border-color: #334155 !important; }
    [data-theme="dark"] #wb-comment-popover .mention-item:hover { background: #334155 !important; }
    [data-theme="dark"] #comment-thread-view > div:first-child { border-bottom-color: #334155 !important; }
    [data-theme="dark"] #comment-thread-view label { color: #cbd5e1 !important; }
    [data-theme="dark"] #comment-thread-view i.ph { color: #cbd5e1 !important; }
    [data-theme="dark"] #wb-comment-popover > div:last-child { background: #1e293b !important; }
    [data-theme="dark"] #comment-popover-input { color: #f8fafc !important; }
    [data-theme="dark"] .comment-msg-item { background: transparent !important; }
    [data-theme="dark"] .comment-msg-item strong { color: #f8fafc !important; }
    [data-theme="dark"] .comment-msg-item .msg-text { color: #cbd5e1 !important; }
    [data-theme="dark"] .comment-msg-item .msg-date { color: #64748b !important; }
    
    /* Toasts y Presentación */
    #wb-toast-container { position: absolute; top: 20px; left: 50%; transform: translateX(-50%); display: flex; flex-direction: column; gap: 10px; z-index: 1000; pointer-events: none; }
    .wb-toast { background: var(--text-main); color: var(--bg-surface, #fff); padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 500; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 8px; opacity: 0; transform: translateY(-20px); transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .wb-toast.show { opacity: 1; transform: translateY(0); }
    
    @media (max-width: 768px) {
        .wb-floating-toolbar { width: 90%; overflow-x: auto; justify-content: flex-start; }
        .wb-top-right { top: auto; bottom: 80px; right: 15px; flex-direction: column; }
    }
</style>

<div id="wb-toast-container"></div>
<div class="wb-ui-layer">
    <div class="wb-top-left">
        <a href="index.php?module=pizarras" class="btn btn-outline" style="padding: 5px 10px; font-size: 0.8rem; border-color: transparent;"><i class="ph ph-arrow-left"></i> Volver</a>
        <div class="wb-title" style="margin-left: 5px;"><?php echo htmlspecialchars($whiteboard['title']); ?></div>
        <div style="width: 1px; height: 16px; background: var(--border-color); margin: 0 5px;"></div>
        <button class="wb-tool-btn" id="tool-undo" title="Deshacer (Ctrl+Z)"><i class="ph ph-arrow-u-up-left" style="font-size:1.2rem;"></i></button>
        <button class="wb-tool-btn" id="tool-redo" title="Rehacer (Ctrl+Y)"><i class="ph ph-arrow-u-up-right" style="font-size:1.2rem;"></i></button>
    </div>

    <div class="wb-top-right">
        <button id="btn-manual-save" style="background:#e2e8f0; color:#64748b; border:none; border-radius:99px; padding:6px 16px; font-size:0.85rem; font-weight:600; cursor: default; transition: all 0.2s; display:flex; align-items:center; gap:5px; margin-right: 5px;" disabled>
            <i class="ph ph-floppy-disk" style="font-size:1rem;"></i> <span id="manual-save-text">Guardado</span>
        </button>
        <div class="wb-bg-dropdown" style="position: relative;">
            <button class="wb-tool-btn" id="btn-bg-toggle" type="button" title="Fondo de la Pizarra">
                <i class="ph ph-grid-four" style="font-size:1.2rem;"></i>
            </button>
            <div id="bg-dropdown-menu" style="display: none; border-radius:12px; border:1px solid var(--border-color); padding:8px; position: absolute; top: 100%; right: 0; margin-top: 8px; background: var(--bg-surface, #fff); width: max-content; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.15); flex-direction: column; gap: 4px;">
                <a href="#" onclick="event.preventDefault(); document.getElementById('bg-dropdown-menu').style.display='none'; changeCanvasBackground('dots');" style="display: flex; align-items: center; gap: 8px; padding: 6px 12px; color: var(--text-main); text-decoration: none; border-radius: 6px; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'"><i class="ph ph-dots-nine"></i> Puntos</a>
                <a href="#" onclick="event.preventDefault(); document.getElementById('bg-dropdown-menu').style.display='none'; changeCanvasBackground('grid');" style="display: flex; align-items: center; gap: 8px; padding: 6px 12px; color: var(--text-main); text-decoration: none; border-radius: 6px; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'"><i class="ph ph-grid-nine"></i> Cuadrícula</a>
                <a href="#" onclick="event.preventDefault(); document.getElementById('bg-dropdown-menu').style.display='none'; changeCanvasBackground('lines');" style="display: flex; align-items: center; gap: 8px; padding: 6px 12px; color: var(--text-main); text-decoration: none; border-radius: 6px; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'"><i class="ph ph-rows"></i> Líneas</a>
                <a href="#" onclick="event.preventDefault(); document.getElementById('bg-dropdown-menu').style.display='none'; changeCanvasBackground('solid');" style="display: flex; align-items: center; gap: 8px; padding: 6px 12px; color: var(--text-main); text-decoration: none; border-radius: 6px; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'"><i class="ph ph-square"></i> Color Sólido</a>
            </div>
        </div>
        <button class="wb-tool-btn" id="btn-presentation" title="Modo Presentación"><i class="ph ph-presentation-chart" style="font-size:1.2rem;"></i></button>
        <div style="width: 1px; height: 16px; background: var(--border-color); margin: 0 5px;"></div>
        <?php if($is_creator_or_admin): ?>
        <button class="btn btn-primary" onclick="openShareWhiteboardModal('edit', <?php echo $id; ?>)" style="background:#3b82f6; border:none; color:white; border-radius:99px; padding:6px 16px; font-size:0.85rem; display:flex; align-items:center; gap:5px; font-weight:600; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'"><i class="ph ph-share-network" style="font-size:1rem;"></i> Compartir</button>
        <?php endif; ?>
        <div style="width: 1px; height: 16px; background: var(--border-color); margin: 0 5px;"></div>
        <div id="wb-active-users" style="display: flex; align-items: center; gap: -5px;"></div>
        <div id="wb-status" style="display: none;"></div>
    </div>

    <div class="wb-floating-toolbar">
        <button class="wb-tool-btn active" id="tool-select" title="Seleccionar/Mover"><i class="ph ph-cursor" style="font-size:1.2rem;"></i></button>
        <button class="wb-tool-btn" id="tool-pan" title="Mover / Manito (Espacio)"><i class="ph ph-hand-palm" style="font-size:1.2rem;"></i></button>
        <div style="width: 1px; height: 16px; background: var(--border-color); margin: 0 5px;"></div>
        <button class="wb-tool-btn" id="tool-draw" title="Dibujar (D)"><i class="ph ph-pencil-simple"></i></button>
        <button class="wb-tool-btn" id="tool-highlighter" title="Resaltador (H)"><i class="ph ph-highlighter"></i></button>
        <button class="wb-tool-btn" id="tool-eraser" title="Borrador (E)"><i class="ph ph-eraser"></i></button>
        <input type="color" id="wb-color" class="wb-color-picker" value="#000000" title="Color" style="margin: 0 5px; height: 24px; align-self: center; border: none; background: transparent; cursor: pointer;">
        <button class="wb-tool-btn" id="tool-text" title="Texto (T)"><i class="ph ph-text-t" style="font-size:1.2rem;"></i></button>
        <button class="wb-tool-btn" id="tool-sticky" title="Nota Adhesiva"><i class="ph ph-note" style="font-size:1.2rem;"></i></button>
        <button class="wb-tool-btn" id="tool-arrow" title="Flecha"><i class="ph ph-arrow-up-right" style="font-size:1.2rem;"></i></button>
        <div style="position: relative; display: flex; align-items: center;">
            <button class="wb-tool-btn" id="tool-shape" title="Formas (S)"><i class="ph ph-shapes" style="font-size:1.2rem;"></i></button>
            <div id="shape-presets" style="display: none; position: absolute; bottom: 100%; left: 0; background: var(--bg-panel, #ffffff); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 -4px 15px rgba(0,0,0,0.1); flex-direction: column; z-index: 100; min-width: 150px; padding: 5px; margin-bottom: 5px;">
                <button class="shape-preset-btn" data-shape="rect" style="padding: 8px 10px; text-align: left; background: none; border: none; color: var(--text-main); cursor: pointer; border-radius: 4px; font-size: 0.85rem;"><i class="ph ph-square" style="margin-right: 5px;"></i> Rectángulo</button>
                <button class="shape-preset-btn" data-shape="circle" style="padding: 8px 10px; text-align: left; background: none; border: none; color: var(--text-main); cursor: pointer; border-radius: 4px; font-size: 0.85rem;"><i class="ph ph-circle" style="margin-right: 5px;"></i> Círculo</button>
                <button class="shape-preset-btn" data-shape="triangle" style="padding: 8px 10px; text-align: left; background: none; border: none; color: var(--text-main); cursor: pointer; border-radius: 4px; font-size: 0.85rem;"><i class="ph ph-triangle" style="margin-right: 5px;"></i> Triángulo</button>
            </div>
        </div>
        
        <div style="position: relative; display: flex; align-items: center;">
            <button class="wb-tool-btn" id="tool-frame" title="Marco (M)"><i class="ph ph-frame-corners" style="font-size:1.2rem;"></i></button>
            <div id="frame-presets" style="display: none; position: absolute; bottom: 100%; left: 0; background: var(--bg-panel, #ffffff); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 -4px 15px rgba(0,0,0,0.1); flex-direction: column; z-index: 100; min-width: 180px; padding: 5px; margin-bottom: 5px;">
                <button class="frame-preset-btn" data-width="free" style="padding: 8px 10px; text-align: left; background: none; border: none; color: var(--text-main); cursor: pointer; border-radius: 4px; font-size: 0.85rem;"><i class="ph ph-bounding-box" style="margin-right: 5px;"></i> Forma Libre</button>
                <button class="frame-preset-btn" data-width="1920" data-height="1080" style="padding: 8px 10px; text-align: left; background: none; border: none; color: var(--text-main); cursor: pointer; border-radius: 4px; font-size: 0.85rem;"><i class="ph ph-monitor" style="margin-right: 5px;"></i> Presentación (16:9)</button>
                <button class="frame-preset-btn" data-width="1080" data-height="1080" style="padding: 8px 10px; text-align: left; background: none; border: none; color: var(--text-main); cursor: pointer; border-radius: 4px; font-size: 0.85rem;"><i class="ph ph-instagram-logo" style="margin-right: 5px;"></i> Post Social (1:1)</button>
                <button class="frame-preset-btn" data-width="390" data-height="844" style="padding: 8px 10px; text-align: left; background: none; border: none; color: var(--text-main); cursor: pointer; border-radius: 4px; font-size: 0.85rem;"><i class="ph ph-device-mobile" style="margin-right: 5px;"></i> Móvil (9:16)</button>
            </div>
        </div>
        <button class="wb-tool-btn" id="tool-embed" title="Insertar YouTube/Spotify"><i class="ph ph-youtube-logo" style="font-size:1.2rem;"></i></button>
    </div>

    <div class="wb-sidebar-toggle-group">
        <button id="toggle-sidebar-btn" class="wb-sidebar-toggle-btn" title="Plantillas">
            <i class="ph ph-squares-four" style="font-size: 1.5rem;"></i>
        </button>
        <button id="toggle-components-btn" class="wb-sidebar-toggle-btn" title="Componentes">
            <i class="ph ph-shapes" style="font-size: 1.5rem;"></i>
        </button>
    </div>
    
    <div class="wb-zoom-controls">
        <button class="wb-tool-btn" id="btn-zoom-out" title="Alejar" style="padding: 4px 6px;"><i class="ph ph-minus"></i></button>
        <span id="zoom-level-text" style="font-size: 0.85rem; font-weight: 600; width: 45px; text-align: center; color: var(--text-main); cursor: pointer;" title="Restablecer (100%)">100%</span>
        <button class="wb-tool-btn" id="btn-zoom-in" title="Acercar" style="padding: 4px 6px;"><i class="ph ph-plus"></i></button>
    </div>
</div>

<div style="display: flex; flex: 1; overflow: hidden; position: relative;">

    <!-- Panel de plantillas (Oculto por defecto) -->
    <div class="wb-sidebar closed" id="templates-sidebar" style="z-index: 20; padding: 16px; display: flex; flex-direction: column; gap: 12px; overflow-y: auto;">
        <h4 style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; margin: 0;">Plantillas</h4>
        
        <!-- Brand Selector -->
        <select id="tpl-brand-select" style="width: 100%; padding: 9px 12px; border: 1.5px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-main); outline: none; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: border-color 0.2s;">
            <option value="">Cargando marcas...</option>
        </select>

        <!-- Dynamic boards container -->
        <div id="tpl-boards-container" style="display: flex; flex-direction: column; gap: 10px;"></div>
    </div>
    
    <!-- Panel de Componentes (Oculto por defecto) -->
    <div class="wb-sidebar closed" id="components-sidebar" style="z-index: 20;">
        <div class="wb-sidebar-header"><h3>Componentes</h3></div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding-bottom: 30px;">
            <div class="wb-template-card" draggable="true" data-type="rotulo" data-title="Rótulo Proyecto">
                <div class="wb-template-header"><i class="ph ph-identification-card"></i> Rótulo Proyecto</div>
                <div class="wb-template-body">
                    <div style="width: 120px; padding: 4px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; display: flex; flex-direction: column; gap: 2px;">
                        <div style="height: 6px; width: 80%; background: #0f172a; border-radius: 2px;"></div>
                        <div style="height: 4px; width: 40%; background: #94a3b8; border-radius: 2px;"></div>
                        <div style="display: flex; justify-content: space-between; margin-top: 4px;">
                            <div style="height: 10px; width: 10px; border-radius: 50%; background: #3b82f6;"></div>
                            <div style="height: 10px; width: 30px; background: #e2e8f0; border-radius: 4px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wb-template-card" draggable="true" data-type="wireframe" data-title="Wireframe UI">
            <div class="wb-template-header"><i class="ph ph-browser"></i> Wireframe Browser</div>
            <div class="wb-template-body">
                <div style="width: 120px; height: 80px; border: 2px solid #cbd5e1; border-radius: 4px; background: #f8fafc; position: relative;">
                    <div style="height: 15px; border-bottom: 2px solid #cbd5e1; display:flex; gap: 3px; align-items:center; padding: 0 4px;">
                        <div style="width: 6px; height: 6px; border-radius:50%; background:#ef4444;"></div>
                        <div style="width: 6px; height: 6px; border-radius:50%; background:#eab308;"></div>
                        <div style="width: 6px; height: 6px; border-radius:50%; background:#22c55e;"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="wb-template-card" draggable="true" data-type="funnel" data-title="Embudo de Ventas">
            <div class="wb-template-header"><i class="ph ph-funnel"></i> Embudo (Funnel)</div>
            <div class="wb-template-body">
                <div style="width: 100px; display: flex; flex-direction: column; align-items: center; gap: 2px;">
                    <div style="width: 100%; height: 15px; background: #60a5fa; border-radius: 2px;"></div>
                    <div style="width: 75%; height: 15px; background: #3b82f6; border-radius: 2px;"></div>
                    <div style="width: 50%; height: 15px; background: #2563eb; border-radius: 2px;"></div>
                    <div style="width: 25%; height: 15px; background: #1d4ed8; border-radius: 2px;"></div>
                </div>
            </div>
        </div>
        
        <div class="wb-template-card" draggable="true" data-type="journey" data-title="User Journey">
            <div class="wb-template-header"><i class="ph ph-map-trifold"></i> Matriz Journey</div>
            <div class="wb-template-body">
                <div style="width: 120px; height: 60px; display: grid; grid-template-columns: 1fr 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 2px;">
                    <div style="background:#e2e8f0; border-radius:2px;"></div><div style="background:#e2e8f0; border-radius:2px;"></div><div style="background:#e2e8f0; border-radius:2px;"></div>
                    <div style="background:#cbd5e1; border-radius:2px;"></div><div style="background:#cbd5e1; border-radius:2px;"></div><div style="background:#cbd5e1; border-radius:2px;"></div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="decision" data-title="Decisión (Flujo)">
            <div class="wb-template-header"><i class="ph ph-diamond"></i> Decisión (Rombo)</div>
            <div class="wb-template-body">
                <div style="width: 50px; height: 50px; background: #fde047; transform: rotate(45deg); border: 2px solid #eab308; margin: 15px 0;"></div>
            </div>
        </div>
        
        <div class="wb-template-card" draggable="true" data-type="database" data-title="Base de Datos">
            <div class="wb-template-header"><i class="ph ph-database"></i> Base de Datos</div>
            <div class="wb-template-body">
                <div style="width: 60px; height: 70px; border: 2px solid #94a3b8; border-radius: 8px; background: #f1f5f9; position: relative;">
                    <div style="position: absolute; top: 15px; left: 0; width: 100%; border-top: 2px solid #94a3b8;"></div>
                    <div style="position: absolute; top: 35px; left: 0; width: 100%; border-top: 2px solid #94a3b8;"></div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="whatsapp" data-title="WhatsApp">
            <div class="wb-template-header"><i class="ph ph-whatsapp-logo"></i> Mensaje WA</div>
            <div class="wb-template-body">
                <div style="width: 100px; padding: 10px; background: #dcf8c6; border-radius: 8px; border-bottom-right-radius: 0; position: relative; border: 1px solid #b2d89b;">
                    <div style="width: 100%; height: 6px; background: rgba(0,0,0,0.1); border-radius: 3px; margin-bottom: 4px;"></div>
                    <div style="width: 60%; height: 6px; background: rgba(0,0,0,0.1); border-radius: 3px;"></div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="ecommerce" data-title="E-commerce">
            <div class="wb-template-header"><i class="ph ph-shopping-cart"></i> Producto</div>
            <div class="wb-template-body">
                <div style="width: 90px; border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; background: #fff;">
                    <div style="height: 60px; background: #e2e8f0; display:flex; justify-content:center; align-items:center;"><i class="ph ph-image" style="color:#94a3b8;"></i></div>
                    <div style="padding: 6px; display:flex; flex-direction:column; gap:4px;">
                        <div style="height:4px; width:100%; background:#cbd5e1; border-radius:2px;"></div>
                        <div style="height:8px; width:40%; background:#22c55e; border-radius:2px;"></div>
                        <div style="height:12px; width:100%; background:#3b82f6; border-radius:2px; margin-top:2px;"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="wb-template-card" draggable="true" data-type="kanban" data-title="Tablero Kanban">
            <div class="wb-template-header"><i class="ph ph-kanban"></i> Tablero Kanban</div>
            <div class="wb-template-body">
                <div style="width: 120px; height: 70px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; display: flex; gap: 2px; padding: 2px;">
                    <div style="flex:1; background: #e2e8f0; border-radius: 2px; display:flex; flex-direction:column; gap:2px; padding:2px;"><div style="height:10px; background:#fff;"></div><div style="height:10px; background:#fff;"></div></div>
                    <div style="flex:1; background: #e2e8f0; border-radius: 2px; display:flex; flex-direction:column; gap:2px; padding:2px;"><div style="height:10px; background:#fff;"></div></div>
                    <div style="flex:1; background: #e2e8f0; border-radius: 2px; display:flex; flex-direction:column; gap:2px; padding:2px;"><div style="height:10px; background:#fff;"></div><div style="height:10px; background:#fff;"></div><div style="height:10px; background:#fff;"></div></div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="ad-image" data-title="Anuncio Imagen">
            <div class="wb-template-header"><i class="ph ph-image-square"></i> Anuncio Imagen</div>
            <div class="wb-template-body">
                <div style="width: 100px; border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; background: #fff;">
                    <div style="height: 50px; background: #e2e8f0; display:flex; justify-content:center; align-items:center;"><i class="ph ph-image"></i></div>
                    <div style="padding: 4px; display:flex; flex-direction:column; gap:2px;">
                        <div style="height:4px; width:80%; background:#94a3b8; border-radius:2px;"></div>
                        <div style="height:3px; width:60%; background:#cbd5e1; border-radius:2px;"></div>
                        <div style="height:8px; width:40%; background:#2563eb; border-radius:2px; margin-top:2px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="ad-video" data-title="Anuncio Video">
            <div class="wb-template-header"><i class="ph ph-video"></i> Anuncio Video</div>
            <div class="wb-template-body">
                <div style="width: 100px; border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; background: #fff;">
                    <div style="height: 50px; background: #1e293b; display:flex; justify-content:center; align-items:center; color: #fff;"><i class="ph-fill ph-play-circle" style="font-size: 1.5rem;"></i></div>
                    <div style="padding: 4px; display:flex; flex-direction:column; gap:2px;">
                        <div style="height:4px; width:80%; background:#94a3b8; border-radius:2px;"></div>
                        <div style="height:8px; width:100%; background:#22c55e; border-radius:2px; margin-top:2px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="landing-page" data-title="Landing Page">
            <div class="wb-template-header"><i class="ph ph-layout"></i> Landing Page</div>
            <div class="wb-template-body">
                <div style="width: 100px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; display: flex; flex-direction: column;">
                    <div style="height: 10px; border-bottom: 1px solid #cbd5e1; display:flex; justify-content:space-between; padding: 2px;">
                        <div style="width: 20px; background: #e2e8f0;"></div>
                        <div style="width: 10px; background: #cbd5e1;"></div>
                    </div>
                    <div style="height: 30px; background: #e2e8f0; display:flex; justify-content:center; align-items:center; font-size:8px;">Hero</div>
                    <div style="padding: 4px; display:flex; flex-direction:column; gap:2px; align-items:center;">
                        <div style="height:4px; width:60%; background:#94a3b8;"></div>
                        <div style="height:10px; width:40%; background:#3b82f6; border-radius: 2px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="social-facebook" data-title="Facebook Post">
            <div class="wb-template-header" style="color: #1877F2;"><i class="ph-fill ph-facebook-logo"></i> Facebook Post</div>
            <div class="wb-template-body">
                <div style="width: 100px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff;">
                    <div style="display:flex; gap:4px; padding:4px; align-items:center;">
                        <div style="width:12px; height:12px; border-radius:50%; background:#1877F2;"></div>
                        <div style="height:4px; width:40%; background:#94a3b8; border-radius:2px;"></div>
                    </div>
                    <div style="height:40px; background:#e2e8f0;"></div>
                    <div style="padding:4px; display:flex; gap:4px;">
                        <div style="height:6px; width:20px; background:#cbd5e1;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="social-instagram" data-title="Instagram Post">
            <div class="wb-template-header" style="background: -webkit-linear-gradient(45deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><i class="ph ph-instagram-logo"></i> Instagram Post</div>
            <div class="wb-template-body">
                <div style="width: 100px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff;">
                    <div style="display:flex; gap:4px; padding:4px; align-items:center;">
                        <div style="width:12px; height:12px; border-radius:50%; background:#cbd5e1;"></div>
                        <div style="height:4px; width:30%; background:#94a3b8; border-radius:2px;"></div>
                    </div>
                    <div style="height:60px; background:#e2e8f0;"></div>
                    <div style="padding:4px; display:flex; gap:2px;">
                        <i class="ph ph-heart" style="font-size: 8px;"></i> <i class="ph ph-chat-circle" style="font-size: 8px;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="social-tiktok" data-title="TikTok Video">
            <div class="wb-template-header"><i class="ph ph-tiktok-logo"></i> TikTok Video</div>
            <div class="wb-template-body">
                <div style="width: 70px; height: 100px; background: #111; border-radius: 6px; position:relative; overflow:hidden;">
                    <div style="position:absolute; right:4px; bottom:20px; display:flex; flex-direction:column; gap:4px; align-items:center;">
                        <div style="width:10px; height:10px; border-radius:50%; background:#fff;"></div>
                        <div style="width:8px; height:8px; background:#fff; border-radius:50%;"></div>
                    </div>
                    <div style="position:absolute; left:4px; bottom:8px;">
                        <div style="height:4px; width:30px; background:#fff; border-radius:2px; margin-bottom:2px;"></div>
                        <div style="height:2px; width:40px; background:#aaa; border-radius:1px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="social-youtube" data-title="YouTube Video">
            <div class="wb-template-header" style="color: #FF0000;"><i class="ph-fill ph-youtube-logo"></i> YouTube</div>
            <div class="wb-template-body">
                <div style="width: 110px; border: 1px solid #cbd5e1; background: #fff; border-radius: 4px; overflow:hidden;">
                    <div style="height:60px; background:#000; display:flex; justify-content:center; align-items:center;">
                        <i class="ph-fill ph-play" style="color:#FF0000; font-size:1.2rem;"></i>
                    </div>
                    <div style="display:flex; gap:4px; padding:4px;">
                        <div style="width:12px; height:12px; border-radius:50%; background:#e2e8f0; flex-shrink:0;"></div>
                        <div style="flex:1; display:flex; flex-direction:column; gap:2px;">
                            <div style="height:4px; width:100%; background:#94a3b8; border-radius:2px;"></div>
                            <div style="height:3px; width:60%; background:#cbd5e1; border-radius:2px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="social-google" data-title="Google Search">
            <div class="wb-template-header"><i class="ph-fill ph-google-logo"></i> Google Search</div>
            <div class="wb-template-body">
                <div style="width: 120px; background: #fff; padding:6px; border:1px solid #cbd5e1; border-radius:4px; display:flex; flex-direction:column; gap:2px;">
                    <div style="height:3px; width:40%; background:#0f9d58; border-radius:1px;"></div>
                    <div style="height:6px; width:80%; background:#1a0dab; border-radius:3px;"></div>
                    <div style="height:3px; width:100%; background:#4d5156; border-radius:1px; margin-top:2px;"></div>
                    <div style="height:3px; width:90%; background:#4d5156; border-radius:1px;"></div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="social-linkedin" data-title="LinkedIn Post">
            <div class="wb-template-header" style="color: #0077b5;"><i class="ph-fill ph-linkedin-logo"></i> LinkedIn</div>
            <div class="wb-template-body">
                <div style="width: 100px; border: 1px solid #cbd5e1; background: #fff; border-radius: 4px;">
                    <div style="display:flex; gap:4px; padding:4px; align-items:center;">
                        <div style="width:12px; height:12px; border-radius:50%; background:#cbd5e1;"></div>
                        <div style="flex:1;">
                            <div style="height:3px; width:60%; background:#0077b5; border-radius:1px; margin-bottom:2px;"></div>
                            <div style="height:2px; width:40%; background:#94a3b8; border-radius:1px;"></div>
                        </div>
                    </div>
                    <div style="height:30px; background:#e2e8f0;"></div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="whatsapp-group" data-title="WhatsApp Grupo">
            <div class="wb-template-header" style="color: #25D366;"><i class="ph-fill ph-users-three"></i> Grupo WhatsApp</div>
            <div class="wb-template-body">
                <div style="width: 80px; height: 100px; border: 1px solid #cbd5e1; background: #e5ddd5; border-radius: 6px; overflow:hidden;">
                    <div style="height: 15px; background: #075E54; display:flex; gap:2px; align-items:center; padding-left:4px;">
                        <div style="width:6px; height:6px; border-radius:50%; background:#fff;"></div>
                        <div style="width:20px; height:3px; background:#fff; border-radius:1px;"></div>
                    </div>
                    <div style="padding:4px; display:flex; flex-direction:column; gap:4px;">
                        <div style="align-self:flex-start; background:#fff; padding:2px; border-radius:2px; width:40px;">
                            <div style="height:2px; width:20px; background:#34B7F1; margin-bottom:1px;"></div>
                            <div style="height:2px; width:100%; background:#aaa;"></div>
                        </div>
                        <div style="align-self:flex-end; background:#DCF8C6; padding:2px; border-radius:2px; width:40px;">
                            <div style="height:2px; width:100%; background:#555;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-template-card" draggable="true" data-type="ai" data-title="Componente IA">
            <div class="wb-template-header" style="color: #8b5cf6;"><i class="ph-fill ph-robot"></i> Chat IA</div>
            <div class="wb-template-body">
                <div style="width: 110px; border: 1px solid #c4b5fd; background: #fff; border-radius: 6px; overflow:hidden; display:flex; flex-direction:column;">
                    <div style="background:#f3f0ff; padding:4px; display:flex; align-items:center; gap:4px; border-bottom:1px solid #e2e8f0;">
                        <i class="ph-fill ph-sparkle" style="color:#8b5cf6; font-size:10px;"></i>
                        <div style="height:4px; width:30px; background:#8b5cf6; border-radius:2px;"></div>
                    </div>
                    <div style="padding:4px; display:flex; flex-direction:column; gap:4px; flex:1;">
                        <div style="align-self:flex-start; background:#f1f5f9; padding:2px; border-radius:2px; width:60px; height:6px;"></div>
                        <div style="align-self:flex-end; background:#8b5cf6; padding:2px; border-radius:2px; width:50px; height:6px;"></div>
                    </div>
                    <div style="margin:4px; height:8px; border:1px solid #cbd5e1; border-radius:4px; display:flex; justify-content:space-between; align-items:center; padding:0 2px;">
                        <div style="height:2px; width:20px; background:#cbd5e1;"></div>
                        <i class="ph-fill ph-paper-plane-right" style="font-size:6px; color:#8b5cf6;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- END COMPONENTS SIDEBAR -->

<!-- Modal para Insertar Embed/Video -->
<div class="wb-modal-overlay" id="embedModal">
    <div class="wb-modal" style="max-width: 450px;">
        <div class="wb-modal-header" style="border-bottom:1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
            <h5 style="margin:0; font-family:'Inter', sans-serif; font-weight:600; display:flex; align-items:center; gap:8px;"><i class="ph ph-youtube-logo" style="color:#ef4444; font-size:1.5rem;"></i> Insertar Video / Embed</h5>
            <button class="btn-close-modal" style="background:transparent; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-muted);"><i class="ph ph-x"></i></button>
        </div>
        <div class="wb-modal-body">
            <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:10px;">Pega el enlace del video (YouTube o Spotify):</p>
            <input type="text" id="embed-url-input" class="form-control" placeholder="https://www.youtube.com/watch?v=..." style="border-radius:8px; width:100%; padding:10px; border:1px solid var(--border-color); outline:none; margin-bottom:20px;">
        </div>
        <div class="wb-modal-footer" style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" class="btn btn-outline btn-close-modal" style="border-radius:8px; padding:8px 16px; border:1px solid var(--border-color); background:transparent; cursor:pointer;">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btn-confirm-embed" style="border-radius:8px; background:var(--primary-color, #3b82f6); border:none; color:white; padding:8px 16px; cursor:pointer;">Insertar</button>
        </div>
    </div>
</div>

<div id="main-content" style="flex: 1; position: relative; overflow: hidden; display: flex;">
    <div id="canvas-wrapper" style="flex: 1; position: relative; background: #eef2f6; overflow: hidden;">
        <!-- Canvas de Fabric.js -->
        <canvas id="whiteboard"></canvas>
        
        <!-- Capa Flotante de Iframes -->
        <div id="iframe-layer" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 5;"></div>
        
        

        <!-- Popover para Comentarios (Inicialmente Oculto) -->
        <div id="comment-popover" style="position: absolute; top: 0; left: 0; width: 320px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border: 1px solid rgba(226, 232, 240, 0.8); z-index: 40; opacity: 0; pointer-events: none; transition: opacity 0.2s ease, transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); transform: translateY(10px); display: flex; flex-direction: column; overflow: hidden;">
        <!-- Minimap -->
        <div id="minimap-container" style="position: absolute; bottom: 20px; right: 20px; width: 150px; height: 100px; background: #fff; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 10; display: none;">
            <canvas id="minimap"></canvas>
            <div id="minimap-viewport" style="position: absolute; border: 1px solid blue; background: rgba(0, 0, 255, 0.1); pointer-events: none;"></div>
        </div>
    </div>
    
    <!-- Context Menu flotante -->
    <div id="wb-context-menu" style="display:none; position:absolute; z-index:100; background:rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.15); padding:8px; gap:8px; border:1px solid rgba(226, 232, 240, 0.8); align-items:center;">
        
        <button class="wb-tool-btn" id="ctx-interact" title="Interactuar / Reproducir Video" style="display:none; color: #10b981; border: none; background: transparent; cursor: pointer;">
            <i class="ph ph-play-circle" style="font-size:1.2rem;"></i>
        </button>

        <button class="wb-tool-btn" id="ctx-copy-image" title="Copiar como Imagen" style="border: none; background: transparent; cursor: pointer; display: none;">
            <i class="ph ph-copy" style="font-size:1.2rem;"></i>
        </button>

        <button class="wb-tool-btn" id="ctx-lock" title="Bloquear/Desbloquear" style="border: none; background: transparent; cursor: pointer;">
            <i class="ph ph-lock-key" style="font-size:1.2rem; color: #64748b;" id="ctx-lock-icon"></i>
        </button>

        <div id="ctx-colors" style="display:flex; align-items:center; gap: 5px;">
            <div style="width: 1px; height: 16px; background: var(--border-color); margin: 0 2px;"></div>
            <div style="display:flex; gap:3px; padding-right:5px; border-right:1px solid var(--border-color);">
            <button class="ctx-color" data-color="#ffffff" style="background:#ffffff; width:20px; height:20px; border-radius:50%; border:1px solid #ddd; cursor:pointer;" title="Blanco"></button>
            <button class="ctx-color" data-color="#fef08a" style="background:#fef08a; width:20px; height:20px; border-radius:50%; border:1px solid #ddd; cursor:pointer;" title="Amarillo"></button>
            <button class="ctx-color" data-color="#bbf7d0" style="background:#bbf7d0; width:20px; height:20px; border-radius:50%; border:1px solid #ddd; cursor:pointer;" title="Verde"></button>
            <button class="ctx-color" data-color="#bfdbfe" style="background:#bfdbfe; width:20px; height:20px; border-radius:50%; border:1px solid #ddd; cursor:pointer;" title="Azul"></button>
            <button class="ctx-color" data-color="#fbcfe8" style="background:#fbcfe8; width:20px; height:20px; border-radius:50%; border:1px solid #ddd; cursor:pointer;" title="Rosa"></button>
            <button class="ctx-color" data-color="#fed7aa" style="background:#fed7aa; width:20px; height:20px; border-radius:50%; border:1px solid #ddd; cursor:pointer;" title="Naranja"></button>
            <button class="ctx-color ctx-color-transparent" data-color="transparent" style="background:transparent; width:20px; height:20px; border-radius:50%; border:1px dashed #999; cursor:pointer;" title="Sin fondo"></button>
            </div>
        </div>
        
        <div id="ctx-text-controls" style="display:flex; align-items:center; gap:5px;">
            <select id="ctx-font" class="ctx-btn" title="Tipografía" style="outline: none; border: 1px solid var(--border-color); border-radius: 4px;">
                <option value="Inter, sans-serif">Inter</option>
                <option value="Arial, sans-serif">Arial</option>
                <option value="'Courier New', Courier, monospace">Courier</option>
                <option value="'Times New Roman', Times, serif">Times</option>
                <option value="'Comic Sans MS', cursive, sans-serif">Comic</option>
            </select>
            
            <div style="width: 1px; height: 16px; background: var(--border-color); margin: 0 2px;"></div>
            
            <button class="ctx-btn ctx-align" data-align="left" title="Alinear Izquierda"><i class="ph ph-text-align-left"></i></button>
            <button class="ctx-btn ctx-align" data-align="center" title="Centrar"><i class="ph ph-text-align-center"></i></button>
            <button class="ctx-btn ctx-align" data-align="right" title="Alinear Derecha"><i class="ph ph-text-align-right"></i></button>
            
            <div style="width: 1px; height: 16px; background: #ccc; margin: 0 2px;"></div>
            
            <button class="ctx-btn" id="ctx-underline" title="Subrayar texto seleccionado"><i class="ph ph-text-underline"></i></button>
            <button class="ctx-btn" id="ctx-link" title="Enlace (al texto seleccionado)"><i class="ph ph-link"></i></button>
        </div>
        
        <div id="ctx-shape-controls" style="display: none; align-items: center;">
            <div title="Color de Relleno" style="display: flex; align-items: center; margin-right: 5px;">
                <i class="ph ph-paint-bucket" style="margin-right: 2px;"></i>
                <input type="color" id="ctx-shape-fill" class="wb-color-picker" style="height: 20px; width: 20px; padding: 0;">
                <button id="ctx-shape-fill-transparent" class="ctx-btn ctx-color-transparent" title="Sin relleno"><i class="ph ph-prohibit"></i></button>
            </div>
            <div style="width: 1px; height: 16px; background: var(--border-color); margin: 0 5px;"></div>
            <div title="Color de Trazo" style="display: flex; align-items: center;">
                <i class="ph ph-pencil-simple" style="margin-right: 2px;"></i>
                <input type="color" id="ctx-shape-stroke" class="wb-color-picker" style="height: 20px; width: 20px; padding: 0;">
                <button id="ctx-shape-stroke-transparent" class="ctx-btn ctx-color-transparent" title="Sin trazo"><i class="ph ph-prohibit"></i></button>
            </div>
            <div style="width: 1px; height: 16px; background: var(--border-color); margin: 0 5px;"></div>
        </div>

        <button id="ctx-crop" class="ctx-btn" style="display:none;" title="Recortar Imagen"><i class="ph ph-crop"></i></button>
        <button id="ctx-duplicate" class="ctx-btn" title="Duplicar (Ctrl+D)"><i class="ph ph-copy"></i></button>
        <button id="ctx-delete" class="ctx-btn" style="color: #ef4444;" title="Eliminar"><i class="ph ph-trash"></i></button>
        
        <div style="width:1px; height:16px; background:var(--border-color); margin:0 5px;"></div>
        
        <div id="ctx-multi-align" style="display: none; align-items: center;">
            <button class="ctx-btn ctx-obj-align" data-align="left" title="Alinear a la Izquierda"><i class="ph ph-align-left"></i></button>
            <button class="ctx-btn ctx-obj-align" data-align="center" title="Centrar Horizontalmente"><i class="ph ph-align-center-horizontal"></i></button>
            <button class="ctx-btn ctx-obj-align" data-align="right" title="Alinear a la Derecha"><i class="ph ph-align-right"></i></button>
            <div style="width: 1px; height: 16px; background: #ccc; margin: 0 2px;"></div>
            <button class="ctx-btn ctx-obj-align" data-align="top" title="Alinear Arriba"><i class="ph ph-align-top"></i></button>
            <button class="ctx-btn ctx-obj-align" data-align="middle" title="Centrar Verticalmente"><i class="ph ph-align-center-vertical"></i></button>
            <button class="ctx-btn ctx-obj-align" data-align="bottom" title="Alinear Abajo"><i class="ph ph-align-bottom"></i></button>
            <div style="width:1px; height:16px; background:var(--border-color); margin:0 5px;"></div>
        </div>

        <button id="ctx-bring-front" class="ctx-btn" title="Traer al frente"><i class="ph ph-caret-line-up"></i></button>
        <button id="ctx-send-back" class="ctx-btn" title="Enviar al fondo"><i class="ph ph-caret-line-down"></i></button>
        
        <div style="width: 1px; height: 16px; background: #ccc; margin: 0 2px;"></div>
        
        <button class="ctx-btn" id="ctx-group" title="Agrupar" style="display:none;"><i class="ph ph-intersect"></i></button>
        <button class="ctx-btn" id="ctx-lock" title="Bloquear"><i class="ph ph-lock-key"></i></button>
    </div>

    <!-- Menú Contextual de Click Derecho -->
    <div id="wb-right-click-menu" style="display:none; position:absolute; z-index:1000; background:var(--bg-surface, #fff); border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15); padding:8px; gap:2px; border:1px solid var(--border-color); flex-direction:column; min-width:180px; font-family:Inter,sans-serif; font-size:14px; color:#334155;">
        <style>
            .rc-item { padding: 8px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; border-radius: 4px; transition: background-color 0.2s; }
            .rc-item:hover { background-color: #f1f5f9; }
            .rc-item.disabled { opacity: 0.5; pointer-events: none; }
        </style>
        <div class="rc-item" id="rc-copy"><i class="ph ph-copy"></i> Copiar</div>
        <div class="rc-item" id="rc-paste"><i class="ph ph-clipboard"></i> Pegar</div>
        <div class="rc-item" id="rc-duplicate"><i class="ph ph-files"></i> Duplicar</div>
        <hr style="margin:4px 0; border:none; border-top:1px solid #e2e8f0;">
        <div class="rc-item" id="rc-download"><i class="ph ph-download-simple"></i> Descargar Imagen</div>
        <hr style="margin:4px 0; border:none; border-top:1px solid #e2e8f0;">
        <div class="rc-item" id="rc-delete" style="color:#ef4444;"><i class="ph ph-trash"></i> Eliminar</div>
    </div>

    <!-- Dropdown de menciones -->
    <div id="wb-mentions-dropdown" style="display:none; position:absolute; z-index:101; background:#fff; border:1px solid #ccc; border-radius:4px; box-shadow:0 2px 8px rgba(0,0,0,0.1); max-height:150px; overflow-y:auto; min-width:150px;"></div>
    <!-- Popover flotante para comentarios -->
    <div id="wb-comment-popover" style="display:none; position:absolute; z-index:102; background:#fff; border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,0.12); width:320px; transition: opacity 0.2s, transform 0.2s; transform: scale(0.95); opacity: 0; pointer-events: auto; border: 1px solid rgba(0,0,0,0.05); font-family: var(--font-family, 'Inter', sans-serif);">
        
        <!-- Estado 3: Hilo de comentarios (Visible si ya hay mensajes) -->
        <div id="comment-thread-view" style="display:none; flex-direction:column;">
            <div style="padding:10px 16px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; gap:6px;">
                    <div class="comment-color-btn" data-color="#94a3b8" style="width:16px; height:16px; border-radius:50%; border:2px solid #cbd5e1; background:transparent; cursor:pointer;" title="Gris (Default)"></div>
                    <div class="comment-color-btn" data-color="#84cc16" style="width:16px; height:16px; border-radius:50%; background:#84cc16; cursor:pointer;" title="Verde"></div>
                    <div class="comment-color-btn" data-color="#ef4444" style="width:16px; height:16px; border-radius:50%; background:#ef4444; cursor:pointer;" title="Rojo"></div>
                    <div class="comment-color-btn" data-color="#0ea5e9" style="width:16px; height:16px; border-radius:50%; background:#0ea5e9; cursor:pointer;" title="Azul"></div>
                    <div class="comment-color-btn" data-color="#0f172a" style="width:16px; height:16px; border-radius:50%; background:#0f172a; cursor:pointer;" title="Negro"></div>
                </div>
                <div style="display:flex; gap:8px; color:#475569;">
                    <i class="ph ph-bell" style="font-size:1.1rem; cursor:pointer;" title="Notificaciones"></i>
                    <i id="delete-comment-btn" class="ph ph-trash" style="font-size:1.1rem; cursor:pointer; color:#ef4444;" title="Eliminar Comentario"></i>
                </div>
            </div>
            
            <div id="comment-messages-list" style="max-height:250px; overflow-y:auto; padding:0;">
                <!-- Lista de mensajes generada por JS -->
            </div>
        </div>

        <!-- Área de Input (Sirve para Estado 1 y Estado 3) -->
        <div style="padding:12px 16px; position:relative; background:#fff; border-radius:0 0 16px 16px;">
            <textarea id="comment-popover-input" placeholder="Agrega un comentario. Usa @ para mencionar a alguien." style="width:100%; min-height:40px; border:none; outline:none; resize:none; font-family:inherit; font-size:0.95rem; color:#1e293b; background:transparent; line-height:1.4; padding-right:60px;" rows="1"></textarea>
            
            <div style="position:absolute; bottom:12px; right:12px; display:flex; gap:10px; align-items:center; color:#94a3b8;">
                <i class="ph ph-smiley" style="font-size:1.3rem; cursor:pointer;"></i>
                <i id="comment-popover-send" class="ph-fill ph-paper-plane-right" style="font-size:1.3rem; cursor:pointer; color:#4f46e5; transition: transform 0.2s;"></i>
            </div>
        </div>
    </div>
</div> <!-- End of flex container -->

<!-- Modals for Invite and Share -->
<style>
    /* Modal Styles (reused from index) */
    .wb-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px);
        z-index: 1000; display: flex; align-items: center; justify-content: center;
        opacity: 0; visibility: hidden; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .wb-modal-overlay.show { opacity: 1; visibility: visible; }
    
    .wb-modal {
        background: #ffffff; border-radius: 24px; width: 90%; max-width: 500px;
        display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        transform: scale(0.95) translateY(20px); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        overflow: hidden;
    }
    .wb-modal-overlay.show .wb-modal { transform: scale(1) translateY(0); }
    
    .wb-modal-header {
        padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-color, #e2e8f0);
        display: flex; align-items: center; justify-content: space-between; background: #f8fafc;
    }
    .wb-modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--text-main, #0f172a); }
    .wb-modal-close {
        background: transparent; border: none; font-size: 1.5rem; color: var(--text-muted, #64748b);
        cursor: pointer; border-radius: 50%; width: 36px; height: 36px;
        display: flex; align-items: center; justify-content: center; transition: background 0.2s, color 0.2s;
    }
    .wb-modal-close:hover { background: #e2e8f0; color: var(--text-main, #0f172a); }
    
    .wb-modal-body { padding: 2rem; }
    .wb-form-group label {
        display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-main, #334155);
        margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.3px;
    }
    
    .wb-modal-footer {
        padding: 1.5rem 2rem; border-top: 1px solid var(--border-color, #e2e8f0);
        display: flex; justify-content: flex-end; gap: 1rem; background: #ffffff;
    }
    .wb-btn-cancel {
        background: transparent; color: var(--text-main, #334155); border: 1px solid var(--border-color, #cbd5e1);
        padding: 10px 24px; border-radius: 9999px; font-weight: 600; cursor: pointer; transition: background 0.2s;
    }
    .wb-btn-cancel:hover { background: #f1f5f9; }
    .wb-btn-save {
        background: var(--primary-color, #22c55e); color: #fff; border: none;
        padding: 10px 24px; border-radius: 9999px; font-weight: 600; cursor: pointer; transition: all 0.2s;
    }
    .wb-btn-save:hover { background: var(--primary-hover, #16a34a); box-shadow: 0 4px 12px rgba(34,197,94,0.3); }

    /* Tom Select Overrides */
    .ts-control {
        border: 1px solid var(--border-color, #cbd5e1) !important;
        border-radius: 12px !important;
        padding: 10px 16px !important;
        box-shadow: none !important;
    }
    .ts-control.focus {
        border-color: var(--primary-color, #22c55e) !important;
        box-shadow: 0 0 0 3px rgba(34,197,94,0.15) !important;
    }
    
    /* Dark Mode Modal Overrides */
    [data-theme="dark"] .wb-modal { background: #1e293b !important; }
    [data-theme="dark"] .wb-modal-header { background: #0f172a !important; border-color: #334155 !important; }
    [data-theme="dark"] .wb-modal-header h2 { color: #f8fafc !important; }
    [data-theme="dark"] .wb-modal-footer { background: #1e293b !important; border-color: #334155 !important; }
    [data-theme="dark"] .wb-form-group label { color: #cbd5e1 !important; }
    [data-theme="dark"] .wb-btn-cancel { color: #f8fafc !important; border-color: #475569 !important; }
    [data-theme="dark"] .wb-btn-cancel:hover { background: #334155 !important; }
    [data-theme="dark"] .wb-modal-close:hover { background: #334155 !important; color: #f8fafc !important; }
    [data-theme="dark"] .ts-control { background: #0f172a !important; border-color: #475569 !important; color: #f8fafc !important; }
    [data-theme="dark"] .ts-control input { color: #f8fafc !important; }
</style>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<?php require_once 'components/share_modal.php'; ?>

<script>
    window.WHITEBOARD_ID = <?= json_encode($id) ?>;
    window.CURRENT_USER_NAME = <?= json_encode($_SESSION['user_name'] ?? 'Usuario') ?>;
    window.CURRENT_USER_ID = <?= json_encode($_SESSION['user_id'] ?? null) ?>;
    window.USER_IS_VIEWER = <?= json_encode($is_viewer_only) ?>;
</script>

<script src="assets/js/whiteboard.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
