<?php
// modules/pizarras/index.php
require_once 'includes/header.php';

global $db;

// Pagination and Filtering
$search = $_GET['search'] ?? '';
$current_folder_id = $_GET['folder'] ?? null;

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "w.title LIKE ?";
    $params[] = "%$search%";
}

if ($current_folder_id) {
    $where[] = "w.folder_id = ?";
    $params[] = $current_folder_id;
} else if (!$search) {
    $where[] = "w.folder_id IS NULL";
}

$whereClause = implode(" AND ", $where);

$stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmtRole->execute([$_SESSION['user_id']]);
$role_id = $stmtRole->fetchColumn();
$is_admin = ($role_id == 1);

// Fetch users for the assignment select
$stmtUsers = $db->query("SELECT id, name FROM users");
$all_users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Fetch folders with whiteboard count (ALL folders are public)
$stmtFolders = $db->prepare("
    SELECT f.id, f.name, f.color, COUNT(w.id) as board_count 
    FROM whiteboard_folders f 
    LEFT JOIN whiteboards w ON f.id = w.folder_id 
    GROUP BY f.id, f.name, f.color 
    ORDER BY f.name ASC
");
$stmtFolders->execute();
$folders = $stmtFolders->fetchAll(PDO::FETCH_ASSOC);

// Fetch whiteboards
// Filter: Admin sees all. Regular user sees if they created it OR they are in whiteboard_users.
$sql = "SELECT w.id, w.title, w.created_by, w.created_at, w.updated_at, w.folder_id, w.tags, w.thumbnail, w.profile_pic, u.name as creator_name, u.avatar as creator_avatar 
        FROM whiteboards w 
        LEFT JOIN users u ON w.created_by = u.id 
        WHERE $whereClause AND (w.created_by = ? OR EXISTS(SELECT 1 FROM whiteboard_users wu WHERE wu.whiteboard_id = w.id AND wu.user_id = ?) OR ?)
        ORDER BY w.updated_at DESC 
        LIMIT 100";
$params[] = $_SESSION['user_id'];
$params[] = $_SESSION['user_id'];
$params[] = $is_admin ? 1 : 0;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$whiteboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unique tags for Quick Tags feature
$all_tags = [];
try {
    $stmtTags = $db->prepare("SELECT tags FROM whiteboards WHERE tags IS NOT NULL");
    $stmtTags->execute();
    while($row = $stmtTags->fetch(PDO::FETCH_ASSOC)) {
        if (empty($row['tags']) || $row['tags'] === '[]') continue;
        $t = json_decode($row['tags'], true);
        if(is_array($t)) {
            foreach($t as $tagItem) {
                if (isset($tagItem['name']) && isset($tagItem['color'])) {
                    $key = $tagItem['name'] . '|' . $tagItem['color'];
                    if(!isset($all_tags[$key])) {
                        $all_tags[$key] = $tagItem;
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    // Ignore error if column doesn't exist or syntax error
}
$unique_tags = array_values($all_tags);

$stmtCheckRole = $db->prepare("SELECT name FROM roles WHERE id = ?");
$stmtCheckRole->execute([$role_id]);
$roleName = $stmtCheckRole->fetchColumn();

if ($roleName === 'Invitado') {
    if (count($whiteboards) > 0) {
        header("Location: index.php?module=pizarras&action=view&id=" . $whiteboards[0]['id']);
        exit;
    } else {
        echo "<div style='padding:50px; text-align:center;'><h2>Sin acceso</h2><p>No tienes acceso a ninguna pizarra.</p></div>";
        exit;
    }
}
?>
<!-- Tom Select CSS -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.default.min.css" rel="stylesheet">
<!-- Color Picker -->
<script src="https://cdn.jsdelivr.net/npm/@jaames/iro@5"></script>


<style>
    /* Global Background Override */
    .page-content { background-color: #f8fafc !important; }
    [data-theme="dark"] .page-content { background-color: #0f172a !important; }

    /* Content Container to restrict width */
    .wb-content-container {
        max-width: 1050px;
        margin: 0 auto;
        padding: 0 1rem;
    }

    /* Hero Section */
    .wb-hero {
        text-align: center;
        padding: 3rem 1rem 2rem 1rem;
        position: relative;
    }
    .wb-hero-avatar {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        margin: 0 auto 1.5rem auto;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .wb-hero-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .wb-hero h1 {
        font-size: 2.2rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.5rem 0;
        letter-spacing: -0.5px;
    }
    .wb-hero p {
        font-size: 1.1rem;
        color: #64748b;
        margin: 0;
    }
    [data-theme="dark"] .wb-hero h1 { color: #f8fafc; }
    [data-theme="dark"] .wb-hero p { color: #94a3b8; }

    /* Pills / Actions */
    .wb-action-pills {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin: 2rem auto 3rem auto;
        flex-wrap: wrap;
    }
    .wb-pill-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 12px 24px;
        border-radius: 99px;
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        font-size: 0.95rem;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        text-decoration: none;
    }
    .wb-pill-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        border-color: #cbd5e1;
    }
    .wb-pill-btn i { font-size: 1.2rem; }
    .wb-pill-primary {
        background: #3b82f6;
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 8px 25px rgba(59,130,246,0.3);
    }
    .wb-pill-primary:hover {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 12px 30px rgba(59,130,246,0.4);
    }
    [data-theme="dark"] .wb-pill-btn { background: #1e293b; color: #e2e8f0; border-color: #334155; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
    [data-theme="dark"] .wb-pill-btn:hover { border-color: #475569; background: #334155; }
    [data-theme="dark"] .wb-pill-primary { background: #3b82f6; color: #fff; border-color: transparent; }

    /* Section Title */
    .wb-section-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f172a;
        margin: 2rem 0 1.25rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    [data-theme="dark"] .wb-section-title { color: #f8fafc; }

    /* Glassmorphism Cards */
    .wb-glass-card {
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.9);
        border-radius: 20px;
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        display: flex;
        flex-direction: column;
        min-height: 170px;
        text-decoration: none;
        color: inherit;
        z-index: 1;
    }
    .wb-glass-card::before {
        content: '';
        position: absolute;
        top: -60%;
        left: -60%;
        width: 220%;
        height: 220%;
        background: radial-gradient(circle, var(--glow-color, rgba(59,130,246,0.12)) 0%, transparent 50%);
        opacity: 0.7;
        pointer-events: none;
        transition: opacity 0.3s;
        z-index: -1;
    }
    .wb-glass-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        border-color: rgba(255, 255, 255, 1);
    }
    .wb-glass-card:hover::before { opacity: 1; }
    
    .wb-glass-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        background: #ffffff;
        color: var(--icon-color, #3b82f6);
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        margin-bottom: 1.2rem;
        border: 1px solid rgba(226,232,240,0.5);
    }
    .wb-glass-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.5rem 0;
        letter-spacing: -0.3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .wb-glass-subtitle {
        font-size: 0.85rem;
        color: #64748b;
        margin: 0;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .wb-glass-actions {
        position: absolute;
        top: 1rem;
        right: 1rem;
        display: flex;
        gap: 0.35rem;
        opacity: 0;
        transition: opacity 0.2s;
    }
    .wb-glass-card:hover .wb-glass-actions { opacity: 1; }
    .wb-icon-btn {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(255,255,255,0.95);
        border: 1px solid rgba(226,232,240,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 1rem;
    }
    .wb-icon-btn:hover { background: #ffffff; color: #0f172a; transform: scale(1.1); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .wb-icon-btn.danger:hover { color: #ef4444; border-color: #fca5a5; }

    /* Tags inside glass card */
    .wb-glass-tags { margin-top: auto; padding-top: 1rem; }
    .wb-tag { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 0.7rem; font-weight: 600; color: #fff; margin-right: 4px; margin-top: 4px; }

    /* Dark mode for glass cards */
    [data-theme="dark"] .wb-glass-card {
        background: rgba(30, 41, 59, 0.65);
        border-color: rgba(51, 65, 85, 0.4);
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    [data-theme="dark"] .wb-glass-card:hover { border-color: rgba(71, 85, 105, 0.8); }
    [data-theme="dark"] .wb-glass-icon { background: #0f172a; border-color: rgba(51,65,85,0.8); }
    [data-theme="dark"] .wb-glass-title { color: #f8fafc; }
    [data-theme="dark"] .wb-glass-subtitle { color: #94a3b8; }
    [data-theme="dark"] .wb-icon-btn { background: #1e293b; border-color: #334155; color: #94a3b8; }
    [data-theme="dark"] .wb-icon-btn:hover { background: #334155; color: #f8fafc; }

    /* Dark Mode Empty State */
    [data-theme="dark"] .wb-empty-state { background: rgba(30, 41, 59, 0.3); border-color: #334155; }
    [data-theme="dark"] .wb-empty-icon { background: #1e293b; border-color: #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
    [data-theme="dark"] .wb-empty-title { color: #f8fafc; }
    [data-theme="dark"] .wb-empty-desc { color: #94a3b8; }

    /* Empty State */
    .wb-empty-state {
        width: 100%;
        text-align: center;
        padding: 4rem 2rem;
        background: rgba(255, 255, 255, 0.4);
        border: 2px dashed #cbd5e1;
        border-radius: 20px;
        margin: 1rem 0.75rem;
        transition: all 0.3s;
    }
    .wb-empty-icon {
        width: 80px; height: 80px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1.5rem auto;
        box-shadow: 0 10px 25px rgba(0,0,0,0.03);
        transform: rotate(-3deg);
    }
    .wb-empty-icon i { font-size: 2.8rem; color: #3b82f6; }
    .wb-empty-title { font-size: 1.3rem; font-weight: 700; color: #0f172a; margin: 0 0 0.5rem 0; }
    .wb-empty-desc { color: #64748b; margin: 0 0 1.5rem 0; font-size: 0.95rem; }

    /* Modals (Keep existing structure, updated vars) */
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
        display: flex; align-items: center; justify-content: center; transition: background 0.2s;
    }
    .wb-modal-close:hover { background: #e2e8f0; color: var(--text-main, #0f172a); }
    .wb-modal-body { padding: 2rem; }
    .wb-form-group label {
        display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-main, #334155);
        margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.3px;
    }
    .wb-form-group input {
        width: 100%; padding: 12px 16px; border: 1px solid var(--border-color, #cbd5e1);
        border-radius: 12px; font-size: 0.95rem; box-sizing: border-box; transition: all 0.2s; outline: none;
    }
    .wb-form-group input:focus {
        border-color: var(--primary-color, #22c55e); box-shadow: 0 0 0 3px rgba(34,197,94,0.15);
    }
    .wb-modal-footer {
        padding: 1.5rem 2rem; border-top: 1px solid var(--border-color, #e2e8f0);
        display: flex; justify-content: flex-end; gap: 1rem; background: #ffffff;
    }
    .wb-btn-cancel {
        background: transparent; color: var(--text-main, #334155); border: 1px solid var(--border-color, #cbd5e1);
        padding: 10px 24px; border-radius: 99px; font-weight: 600; cursor: pointer; transition: background 0.2s;
    }
    .wb-btn-cancel:hover { background: #f1f5f9; }
    .wb-btn-save, .wb-btn-danger {
        color: #fff; border: none; padding: 10px 24px; border-radius: 99px; font-weight: 600; cursor: pointer; transition: all 0.2s;
    }
    .wb-btn-save { background: var(--primary-color, #22c55e); }
    .wb-btn-save:hover { background: var(--primary-hover, #16a34a); box-shadow: 0 4px 12px rgba(34,197,94,0.3); }
    .wb-btn-danger { background: #ef4444; }
    .wb-btn-danger:hover { background: #dc2626; box-shadow: 0 4px 12px rgba(239,68,68,0.3); }

    /* Tom Select */
    .ts-control {
        border: 1px solid var(--border-color, #cbd5e1) !important; border-radius: 12px !important; padding: 10px 16px !important; box-shadow: none !important;
    }
    .ts-control.focus { border-color: var(--primary-color, #22c55e) !important; box-shadow: 0 0 0 3px rgba(34,197,94,0.15) !important; }
    
    /* Dark Modals */
    [data-theme="dark"] .wb-modal { background: #1e293b; }
    [data-theme="dark"] .wb-modal-header { background: #334155; border-bottom-color: #475569; }
    [data-theme="dark"] .wb-modal-header h2 { color: #f8fafc; }
    [data-theme="dark"] .wb-modal-footer { background: #1e293b; border-top-color: #334155; }
    [data-theme="dark"] .wb-btn-cancel { color: #cbd5e1; border-color: #475569; }
    [data-theme="dark"] .wb-btn-cancel:hover { background: #334155; color: #f8fafc; }
    [data-theme="dark"] .wb-modal-close:hover { background: #475569; color: #f8fafc; }
    [data-theme="dark"] .wb-form-group input, [data-theme="dark"] .ts-control { background: #0f172a !important; color: #f8fafc !important; border-color: #334155 !important; }
    [data-theme="dark"] .ts-dropdown { background: #1e293b !important; color: #f8fafc !important; border-color: #334155 !important; }
    [data-theme="dark"] .ts-dropdown .option:hover { background: #334155 !important; }
    [data-theme="dark"] .wb-form-group label { color: #94a3b8; }
</style>

<div class="wb-hero">
    <div class="wb-hero-avatar">
        <img src="<?php echo htmlspecialchars($global_settings['favicon'] ?? 'assets/img/logo.png'); ?>" alt="Logo">
    </div>
    <h1>Hola, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?> 👋</h1>
    <p>¿Listo para iniciar a colaborar tareas y proyectos?</p>
</div>

<div class="wb-action-pills">
    <?php if (!$current_folder_id && !$search): ?>
    <button class="wb-pill-btn" onclick="openFolderModal()">
        <i class="ph ph-folder-plus"></i> Crear Nueva Carpeta
    </button>
    <?php endif; ?>
    <button class="wb-pill-btn wb-pill-primary" onclick="openShareWhiteboardModal('create')">
        <i class="ph ph-chalkboard"></i> Crear Pizarra
    </button>
</div>

<!-- Folders Area -->
<div class="wb-content-container">
<?php if (!$current_folder_id && !$search): ?>
    <?php if(!empty($folders)): ?>
    <div class="wb-section-title"><i class="ph ph-folders"></i> Mis Carpetas</div>
    <div class="row" style="display: flex; flex-wrap: wrap; gap: 1.5rem; margin: 0 -0.75rem 2rem -0.75rem;">
        <?php foreach($folders as $f): 
            // Generar glow con opacidad baja
            $hex = $f['color'];
            list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x") ?: [59, 130, 246];
            $glow = "rgba($r, $g, $b, 0.15)";
        ?>
        <div style="flex: 1 1 250px; max-width: 320px; padding: 0 0.75rem; box-sizing: border-box;">
            <div class="wb-glass-card" style="--glow-color: <?php echo $glow; ?>; --icon-color: <?php echo htmlspecialchars($hex); ?>;" onclick="window.location.href='index.php?module=pizarras&folder=<?php echo $f['id']; ?>'">
                <div class="wb-glass-content">
                    <div class="wb-glass-icon"><i class="ph ph-folder-open"></i></div>
                    <div class="wb-glass-title" title="<?php echo htmlspecialchars($f['name']); ?>"><?php echo htmlspecialchars($f['name']); ?></div>
                    <div class="wb-glass-subtitle"><?php echo $f['board_count']; ?> pizarra<?php echo $f['board_count'] != 1 ? 's' : ''; ?> colaborativa<?php echo $f['board_count'] != 1 ? 's' : ''; ?></div>
                </div>
                
                <?php if ($is_admin): ?>
                <div class="wb-glass-actions" onclick="event.stopPropagation(); deleteFolder(<?php echo $f['id']; ?>);">
                    <button class="wb-icon-btn danger" title="Eliminar Carpeta"><i class="ph ph-trash"></i></button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Whiteboards Area -->
<div class="wb-section-title">
    <?php if ($current_folder_id): 
        $folder_name = "Carpeta";
        foreach($folders as $f) { if($f['id'] == $current_folder_id) { $folder_name = $f['name']; break; } }
    ?>
        <a href="index.php?module=pizarras" style="color: #64748b; text-decoration: none; margin-right: 8px;" title="Volver"><i class="ph ph-arrow-left"></i></a>
        <i class="ph ph-folder-open" style="color: var(--primary-color);"></i> <?php echo htmlspecialchars($folder_name); ?>
    <?php else: ?>
        <i class="ph ph-files"></i> Pizarras Recientes
    <?php endif; ?>
</div>

<div class="row" style="display: flex; flex-wrap: wrap; gap: 1.5rem; margin: 0 -0.75rem;">
    <?php if(empty($whiteboards)): ?>
        <div class="wb-empty-state">
            <div class="wb-empty-icon">
                <i class="ph ph-chalkboard"></i>
            </div>
            <h3 class="wb-empty-title">Tu espacio está vacío</h3>
            <p class="wb-empty-desc">Empieza creando una nueva pizarra colaborativa para ti y tu equipo.</p>
            <button class="wb-pill-btn wb-pill-primary" onclick="openShareWhiteboardModal('create')">
                <i class="ph ph-plus"></i> Crear mi primera pizarra
            </button>
        </div>
    <?php else: ?>
        <?php foreach($whiteboards as $w): 
            $tags = json_decode($w['tags'] ?? '[]', true);
            if (!is_array($tags)) $tags = [];
            // Usar color del primer tag para el glow, o fallback
            $glow = "rgba(59,130,246,0.12)";
            $iconColor = "#3b82f6";
            if (!empty($tags) && isset($tags[0]['color'])) {
                $hex = $tags[0]['color'];
                list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x") ?: [59, 130, 246];
                $glow = "rgba($r, $g, $b, 0.15)";
                $iconColor = $hex;
            }
        ?>
            <div style="flex: 1 1 250px; max-width: 320px; padding: 0 0.75rem; box-sizing: border-box;">
                <div class="wb-glass-card" style="--glow-color: <?php echo $glow; ?>; --icon-color: <?php echo htmlspecialchars($iconColor); ?>;" onclick="window.location.href='index.php?module=pizarras&action=view&id=<?php echo $w['id']; ?>'">
                    
                    <div class="wb-glass-content">
                        <div class="wb-glass-icon"><i class="ph ph-chalkboard"></i></div>
                        <div class="wb-glass-title" title="<?php echo htmlspecialchars($w['title']); ?>"><?php echo htmlspecialchars($w['title']); ?></div>
                        <div class="wb-glass-subtitle">Modificado el <?php echo date('d M, Y', strtotime($w['updated_at'])); ?></div>
                        
                        <?php if(!empty($tags)): ?>
                        <div class="wb-glass-tags">
                            <?php foreach($tags as $t): ?>
                                <span class="wb-tag" style="background: <?php echo htmlspecialchars($t['color']); ?>;"><?php echo htmlspecialchars($t['name']); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="wb-glass-actions" onclick="event.stopPropagation();">
                        <button onclick="openTagsModal(<?php echo $w['id']; ?>)" class="wb-icon-btn" title="Etiquetas / Mover">
                            <i class="ph ph-tag"></i>
                        </button>
                        <?php if ($is_admin || $w['created_by'] == $_SESSION['user_id']): ?>
                        <button onclick="openShareWhiteboardModal('edit', <?php echo $w['id']; ?>)" class="wb-icon-btn" title="Editar Título">
                            <i class="ph ph-pencil-simple"></i>
                        </button>
                        <button onclick="deleteWhiteboard(<?php echo $w['id']; ?>)" class="wb-icon-btn danger" title="Eliminar">
                            <i class="ph ph-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>

<?php require_once 'components/share_modal.php'; ?>

<!-- Modal Eliminar Pizarra -->
<div class="wb-modal-overlay" id="deleteWhiteboardModal">
    <div class="wb-modal" style="max-width: 400px; text-align: center;">
        <div class="wb-modal-body" style="padding: 2.5rem 2rem;">
            <i class="ph ph-warning-circle" style="font-size: 4.5rem; color: #ef4444; margin-bottom: 1rem;"></i>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">¿Eliminar Pizarra?</h2>
            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 2rem;">No podrás revertir esta acción. Todos los elementos y notas se perderán permanentemente.</p>
            
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button class="wb-btn-cancel" onclick="closeDeleteModal()" style="flex: 1;">Cancelar</button>
                <button class="wb-btn-danger" id="btn-confirm-delete" style="flex: 1;">Sí, eliminar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Eliminar Carpeta -->
<div class="wb-modal-overlay" id="deleteFolderModal">
    <div class="wb-modal" style="max-width: 400px; text-align: center;">
        <div class="wb-modal-body" style="padding: 2.5rem 2rem;">
            <i class="ph ph-warning-circle" style="font-size: 4.5rem; color: #ef4444; margin-bottom: 1rem;"></i>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">¿Eliminar Carpeta?</h2>
            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 2rem;">Las pizarras dentro no se borrarán, pero se moverán fuera de la carpeta (al inicio).</p>
            
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button class="wb-btn-cancel" onclick="closeDeleteFolderModal()" style="flex: 1;">Cancelar</button>
                <button class="wb-btn-danger" id="btn-confirm-delete-folder" style="flex: 1;">Sí, eliminar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Carpeta -->
<div class="wb-modal-overlay" id="newFolderModal">
    <div class="wb-modal">
        <div class="wb-modal-header">
            <h2>Crear Nueva Carpeta</h2>
            <button class="wb-modal-close" onclick="closeFolderModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="wb-modal-body">
            <div class="wb-form-group" style="margin-bottom: 1.5rem;">
                <label>Nombre de la carpeta</label>
                <input type="text" id="folder-title-input" placeholder="Ej. Proyectos 2024">
            </div>
            
            <div class="wb-form-group">
                <label>Color</label>
                <input type="color" id="folder-color-input" value="#3b82f6" style="height:40px; padding:2px;">
            </div>
        </div>
        <div class="wb-modal-footer">
            <button class="wb-btn-cancel" onclick="closeFolderModal()">Cancelar</button>
            <button class="wb-btn-save" onclick="submitNewFolder()">Crear Carpeta</button>
        </div>
    </div>
</div>

<!-- Modal Etiquetas y Mover -->
<div class="wb-modal-overlay" id="tagsModal">
    <div class="wb-modal">
        <div class="wb-modal-header">
            <h2>Organizar Pizarra</h2>
            <button class="wb-modal-close" onclick="closeTagsModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="wb-modal-body">
            <div class="wb-form-group" style="margin-bottom: 1.5rem;">
                <label>Mover a Carpeta</label>
                <select id="move-folder-select" class="ts-control">
                    <option value="">(Sin carpeta principal)</option>
                    <?php foreach($folders as $f): ?>
                        <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="wb-form-group" style="margin-bottom: 0.5rem;">
                <label>Añadir Etiqueta</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="tag-name-input" placeholder="Ej. Urgente" style="flex: 1;">
                    <input type="color" id="tag-color-input" value="#ef4444" style="width: 40px; height: 40px; padding: 2px;">
                    <button class="btn btn-primary" onclick="addTag()" style="background:#047857; border:none; border-radius:12px; color:white; padding:0 15px;"><i class="ph ph-plus"></i></button>
                </div>
            </div>
            
            <?php if(!empty($unique_tags)): ?>
            <div class="wb-form-group" style="margin-top: 1rem; margin-bottom: 0.5rem;">
                <label style="font-size:0.75rem;">Etiquetas Sugeridas (haz clic para usar)</label>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                    <?php foreach(array_slice($unique_tags, 0, 12) as $qt): ?>
                        <span class="wb-tag" style="background: <?php echo htmlspecialchars($qt['color']); ?>; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; font-size: 0.75rem; transition: transform 0.1s; border: 1px solid rgba(0,0,0,0.1);" onclick="document.getElementById('tag-name-input').value='<?php echo htmlspecialchars(addslashes($qt['name'])); ?>'; document.getElementById('tag-color-input').value='<?php echo htmlspecialchars($qt['color']); ?>'; addTag();" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            <?php echo htmlspecialchars($qt['name']); ?> <i class="ph ph-plus" style="opacity:0.7;"></i>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div id="current-tags-container" style="display:flex; flex-wrap:wrap; gap:5px; margin-top:15px; border-top: 1px dashed var(--border-color, #e2e8f0); padding-top: 10px;">
                <!-- Tags here -->
            </div>
        </div>
        <div class="wb-modal-footer">
            <button class="wb-btn-cancel" onclick="closeTagsModal()">Cerrar</button>
            <button class="wb-btn-save" onclick="saveBoardOrganization()">Guardar Organización</button>
        </div>
    </div>
</div>

<!-- Tom Select JS -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script>
let boardToDelete = null;

function deleteWhiteboard(id) {
    boardToDelete = id;
    document.getElementById('deleteWhiteboardModal').classList.add('show');
}

function closeDeleteModal() {
    document.getElementById('deleteWhiteboardModal').classList.remove('show');
    boardToDelete = null;
}

document.getElementById('btn-confirm-delete').addEventListener('click', function() {
    if (!boardToDelete) return;
    
    fetch('ajax/ajax_whiteboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id: boardToDelete })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.reload();
        } else {
            closeDeleteModal();
            Swal.fire('Error', res.error, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        closeDeleteModal();
    });
});

/* FOLDERS */
function openFolderModal() {
    document.getElementById('newFolderModal').classList.add('show');
}
function closeFolderModal() {
    document.getElementById('newFolderModal').classList.remove('show');
    document.getElementById('folder-title-input').value = '';
}
function submitNewFolder() {
    const name = document.getElementById('folder-title-input').value.trim();
    const color = document.getElementById('folder-color-input').value;
    if(!name) { Swal.fire('Aviso','Nombre de carpeta vacío','warning'); return; }
    
    fetch('ajax/ajax_whiteboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create_folder', name, color })
    }).then(r=>r.json()).then(res=>{
        if(res.success) window.location.reload();
        else Swal.fire('Error', res.error, 'error');
    });
}

let folderToDelete = null;

function deleteFolder(id) {
    folderToDelete = id;
    document.getElementById('deleteFolderModal').classList.add('show');
}

function closeDeleteFolderModal() {
    document.getElementById('deleteFolderModal').classList.remove('show');
    folderToDelete = null;
}

document.getElementById('btn-confirm-delete-folder').addEventListener('click', function() {
    if (!folderToDelete) return;
    
    fetch('ajax/ajax_whiteboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_folder', id: folderToDelete })
    }).then(r=>r.json()).then(res=>{
        if(res.success) window.location.reload();
        else {
            closeDeleteFolderModal();
            Swal.fire('Error', res.error, 'error');
        }
    }).catch(err => {
        closeDeleteFolderModal();
    });
});

/* TAGS AND ORGANIZE */
let currentBoardIdForTags = null;
let currentTagsArray = [];
const allBoardsData = <?php echo json_encode($whiteboards); ?>;

function openTagsModal(boardId) {
    currentBoardIdForTags = boardId;
    const board = allBoardsData.find(w => w.id == boardId);
    if (!board) return;
    
    document.getElementById('move-folder-select').value = board.folder_id || '';
    
    let tagsStr = board.tags;
    try { currentTagsArray = tagsStr ? JSON.parse(tagsStr) : []; } catch(e) { currentTagsArray = []; }
    if (!Array.isArray(currentTagsArray)) currentTagsArray = [];
    
    renderCurrentTags();
    document.getElementById('tagsModal').classList.add('show');
}

function closeTagsModal() {
    document.getElementById('tagsModal').classList.remove('show');
    currentBoardIdForTags = null;
}

function renderCurrentTags() {
    const c = document.getElementById('current-tags-container');
    c.innerHTML = '';
    currentTagsArray.forEach((t, index) => {
        const span = document.createElement('span');
        span.className = 'wb-tag';
        span.style.background = t.color;
        span.style.cursor = 'pointer';
        span.title = 'Clic para remover';
        span.innerHTML = t.name + ' &times;';
        span.onclick = () => {
            currentTagsArray.splice(index, 1);
            renderCurrentTags();
        };
        c.appendChild(span);
    });
}

function addTag() {
    const name = document.getElementById('tag-name-input').value.trim();
    const color = document.getElementById('tag-color-input').value;
    if(!name) return;
    currentTagsArray.push({name, color});
    document.getElementById('tag-name-input').value = '';
    renderCurrentTags();
}

function saveBoardOrganization() {
    const folder_id = document.getElementById('move-folder-select').value;
    
    // Guardar folder
    fetch('ajax/ajax_whiteboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'move_to_folder', id: currentBoardIdForTags, folder_id })
    })
    .then(r=>r.json())
    .then(res => {
        // Luego guardar tags
        return fetch('ajax/ajax_whiteboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_tags', id: currentBoardIdForTags, tags: currentTagsArray })
        });
    })
    .then(r=>r.json())
    .then(res => {
        window.location.reload();
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Hubo un error al organizar', 'error');
    });
}
</script>
</script>

<?php require_once 'includes/footer.php'; ?>
