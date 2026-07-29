<?php
// modules/projects/view.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

$projectId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($projectId <= 0) {
    header("Location: index.php?module=projects&action=index");
    exit();
}

// Fetch the project
$stmt = $db->prepare("
    SELECT mp.*, 
           s.name AS service_name,
           c.name AS client_name,
           cb.name AS brand_name,
           cb.logo AS brand_logo
    FROM module_projects mp
    LEFT JOIN services s ON mp.service_id = s.id
    LEFT JOIN clients c ON mp.client_id = c.id
    LEFT JOIN client_brands cb ON mp.brand_id = cb.id
    WHERE mp.id = ?
");
$stmt->execute([$projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header("Location: index.php?module=projects&action=index");
    exit();
}

// Fetch team members
$stmtUsers = $db->query("SELECT id, name FROM users ORDER BY name ASC");
$allUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
$usersMap = [];
foreach ($allUsers as $u) { $usersMap[$u['id']] = $u; }

$teamIds = json_decode($project['team_members'], true) ?: [];
$teamNames = [];
$teamAvatars = [];
$avatarColors = ['#10b981','#0ea5e9','#ef4444','#8b5cf6','#f59e0b','#ec4899','#14b8a6','#6366f1'];

foreach ($teamIds as $idx => $uid) {
    if (isset($usersMap[$uid])) {
        $name = trim($usersMap[$uid]['name']);
        $teamNames[] = $name;
        $parts = explode(' ', $name);
        $initial = strtoupper(substr($parts[0], 0, 1));
        $color = $avatarColors[$idx % count($avatarColors)];
        $teamAvatars[] = ['initial' => $initial, 'color' => $color, 'name' => $name];
    }
}

// Fetch all services for the dropdown
$stmtServices = $db->query("SELECT id, name FROM services ORDER BY name ASC");
$allServices = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

// Fetch project services
$stmtSrv = $db->prepare("
    SELECT ps.*, s.name AS service_type_name
    FROM project_services ps
    LEFT JOIN services s ON ps.service_id = s.id
    WHERE ps.project_id = ?
    ORDER BY ps.created_at DESC
");
$stmtSrv->execute([$projectId]);
$projectServices = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);

// Logo
$logoPath = $project['logo'] ?: $project['brand_logo'];
$hasImage = !empty($logoPath) && file_exists($logoPath);

require_once 'includes/header.php';
?>

<style>
.pv-container {
    padding: 0.5rem 2rem 2rem 2rem; /* Reduced top padding to move it up */
    font-family: var(--font-main, 'Inter'), sans-serif;
    width: 100%;
    box-sizing: border-box;
}

.pv-header-card {
    background: var(--bg-surface, #fff);
    border-radius: var(--radius-lg, 16px);
    border: 1px solid var(--border-color, #e2e8f0);
    padding: 1.5rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.pv-header-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.pv-back-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: var(--text-muted, #64748b);
    text-decoration: none;
    font-size: 1.25rem;
    background: var(--bg-sidebar, #f1f5f9);
    transition: all 0.2s;
    flex-shrink: 0;
}
.pv-back-arrow:hover {
    background: #e2e8f0;
    color: var(--text-main, #0f172a);
}

.pv-logo {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--bg-sidebar, #f1f5f9);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--border-color, #e2e8f0);
}

.pv-logo img { width: 100%; height: 100%; object-fit: cover; }

.pv-logo-letter {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: 700;
    color: #fff;
    background: var(--primary-color, #22c55e);
}

.pv-info-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 4px;
}

.pv-info h1 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--text-main, #0f172a);
}

.pv-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    background: #f1f5f9;
}
.pv-status-badge.active { background: rgba(34,197,94,0.1); color: #16a34a; }
.pv-status-badge.archived { background: rgba(245,158,11,0.1); color: #d97706; }

.pv-status-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.pv-status-dot.active { background: #22c55e; }
.pv-status-dot.archived { background: #f59e0b; }

.pv-info p {
    margin: 0;
    color: var(--text-muted, #64748b);
    font-size: 0.9rem;
}

.pv-header-right {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.pv-team-group h4 {
    margin: 0 0 6px;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted, #64748b);
    font-weight: 700;
}


.pv-avatars {
    display: flex;
    flex-wrap: wrap;
    gap: -8px;
}
.pv-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; font-weight: 600;
    border: 2px solid #fff;
    margin-right: -8px; /* overlap */
}

.pv-placeholder {
    background: var(--bg-surface, #fff);
    border-radius: var(--radius-lg, 16px);
    border: 2px dashed var(--border-color, #cbd5e1);
    padding: 4rem 2rem;
    text-align: center;
    color: var(--text-muted, #64748b);
}

.pv-placeholder i {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
    color: var(--text-muted, #94a3b8);
}

.pv-placeholder h3 {
    margin: 0 0 0.5rem;
    color: var(--text-main, #334155);
}

/* Modal Styles */
.pj-modern-modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(8px);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.pj-modern-modal-overlay.show { opacity: 1; visibility: visible; }
.pj-modern-modal {
    background: #ffffff;
    border-radius: 24px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    transform: scale(0.95) translateY(20px);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}
.pj-modern-modal-overlay.show .pj-modern-modal { transform: scale(1) translateY(0); }
.pj-modern-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8fafc;
}
.pj-modern-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--text-main, #0f172a); }
.pj-modern-close {
    background: transparent; border: none; font-size: 1.5rem; color: var(--text-muted, #64748b);
    cursor: pointer; border-radius: 50%; width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center; transition: background 0.2s, color 0.2s;
}
.pj-modern-close:hover { background: #e2e8f0; color: var(--text-main, #0f172a); }
.pj-modern-body { padding: 2rem; overflow-y: auto; flex-grow: 1; }
.pj-modern-footer {
    padding: 1.5rem 2rem; border-top: 1px solid var(--border-color, #e2e8f0);
    display: flex; justify-content: flex-end; gap: 1rem; background: #ffffff;
}

.pj-modern-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.pj-modern-group { margin-bottom: 1.5rem; }
.pj-modern-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-main, #334155); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
.pj-modern-group input[type="text"], .pj-modern-group input[type="date"], .pj-modern-group select, .pj-modern-group textarea {
    width: 100%; padding: 12px 16px; border: 1px solid var(--border-color, #cbd5e1); border-radius: 12px; font-size: 0.95rem; font-family: inherit; background: #f8fafc; transition: all 0.2s; box-sizing: border-box;
}
.pj-modern-group input:focus, .pj-modern-group select:focus, .pj-modern-group textarea:focus {
    outline: none; border-color: var(--primary-color, #22c55e); background: #ffffff; box-shadow: 0 0 0 3px rgba(34,197,94,0.15);
}
.pj-modern-group textarea { resize: vertical; min-height: 100px; }

.pj-btn-modern-cancel { background: #f1f5f9; color: #475569; border: none; padding: 10px 20px; border-radius: 999px; font-weight: 600; cursor: pointer; transition: background 0.2s; font-family: inherit; }
.pj-btn-modern-cancel:hover { background: #e2e8f0; }
.pj-btn-modern-save { background: var(--primary-color, #22c55e); color: #ffffff; border: none; padding: 10px 24px; border-radius: 999px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; font-family: inherit; }
.pj-btn-modern-save:hover { background: var(--primary-hover, #16a34a); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(34,197,94,0.3); }

.pv-btn-new-service {
    background: var(--primary-color, #22c55e); color: #fff; border: none; padding: 8px 16px;
    border-radius: 999px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;
    transition: all 0.2s; font-family: inherit; font-size: 0.9rem;
}
.pv-btn-new-service:hover { background: var(--primary-hover, #16a34a); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(34,197,94,0.3); }

/* Services Grid */
.pv-services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}
.pv-service-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 0;
    display: flex;
    flex-direction: column;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}
.pv-service-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.08);
}

/* Card inner body */
.pv-card-body {
    padding: 1.4rem 1.4rem 0;
}

/* Title */
.pv-service-title {
    font-weight: 800;
    color: var(--text-main, #0f172a);
    font-size: 1.1rem;
    margin: 0 0 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.025em;
    line-height: 1.3;
}

/* Badges row */
.pv-badges-row {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.pv-service-type {
    font-size: 0.7rem;
    color: var(--primary-color, #22c55e);
    background: rgba(34,197,94,0.1);
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}
.pv-card-status {
    font-size: 0.7rem;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    letter-spacing: 0.3px;
}
.pv-card-status .pv-sdot {
    width: 6px; height: 6px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}
.pv-card-status.st-pending   { background: #fef3c7; color: #b45309; }
.pv-card-status.st-pending .pv-sdot { background: #f59e0b; }
.pv-card-status.st-progress  { background: #dbeafe; color: #1d4ed8; }
.pv-card-status.st-progress .pv-sdot { background: #3b82f6; }
.pv-card-status.st-completed { background: #dcfce7; color: #15803d; }
.pv-card-status.st-completed .pv-sdot { background: #22c55e; }

/* Dates section */
.pv-service-dates {
    display: flex;
    flex-direction: column;
    gap: 0;
    margin-bottom: 0;
    flex-grow: 1;
    background: #f8fafc;
    padding: 0;
    border-radius: 12px;
    border: 1px solid #eef2f6;
    overflow: hidden;
}
.pv-service-dates p { 
    margin: 0; 
    display: flex; 
    align-items: center; 
    justify-content: space-between;
    font-size: 0.82rem;
    color: var(--text-main, #334155);
    padding: 10px 14px;
}
.pv-service-dates p + p {
    border-top: 1px solid #eef2f6;
}
.pv-service-dates p span {
    color: var(--text-muted, #64748b);
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
}
.pv-service-dates p strong {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--text-main, #1e293b);
}

/* Action buttons row */
.pv-actions-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 1.25rem 1.4rem 1.4rem 1.4rem;
    margin-top: auto;
}
.pv-actions-row .pv-actions-spacer { display: none; }

.pv-icon-btn {
    width: 38px; height: 38px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    font-size: 1.15rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.pv-icon-btn:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
    transform: scale(1.1);
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}
.pv-icon-btn.danger:hover {
    border-color: #fca5a5;
    background: #fef2f2;
    color: #ef4444;
}
.pv-icon-btn img { display: block; }

/* Enter button */
.pv-btn-enter-service {
    background: var(--primary-color, #22c55e);
    color: #ffffff !important;
    border: none;
    padding: 12px 16px;
    border-radius: 9999px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.25s ease;
    font-family: inherit;
    text-align: center;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 0 1.4rem 1.4rem 1.4rem;
}
.pv-btn-enter-service:hover {
    background: var(--primary-hover, #16a34a);
    box-shadow: 0 6px 16px rgba(34,197,94,0.3);
    transform: translateY(-2px);
}


/* Keep drive-btn for backwards compat */
.pv-drive-btn {
    flex-shrink: 0;
    background: #f1f5f9;
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s, transform 0.2s;
}
.pv-drive-btn:hover {
    background: #e2e8f0;
    transform: scale(1.05);
}

/* Collapsible header - desktop */
.pv-header-toggle { display: none; }
.pv-header-collapsible { display: none; }
.pv-desktop-only { display: flex; }

/* Responsive adjustments */
@media (max-width: 768px) {
    .pv-container {
        padding: 0.75rem;
    }

    /* Header card */
    .pv-header-card {
        padding: 1.25rem;
        flex-direction: column;
        align-items: stretch;
        gap: 1.25rem;
    }
    .pv-header-left {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    .pv-back-arrow {
        display: none;
    }
    .pv-logo {
        width: 56px; height: 56px;
    }
    .pv-logo-letter { font-size: 1.4rem; }
    .pv-info-title {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .pv-info h1 {
        font-size: 1.2rem;
    }
    .pv-info p { font-size: 0.82rem; }

    /* Collapsible header on mobile */
    .pv-header-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        border: none;
        border-radius: 50%;
        width: 28px; height: 28px;
        cursor: pointer;
        color: #64748b;
        font-size: 1rem;
        transition: all 0.3s;
        flex-shrink: 0;
    }
    .pv-header-card.expanded .pv-header-toggle {
        transform: rotate(180deg);
        background: #e2e8f0;
    }
    .pv-header-collapsible {
        display: none;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #e2e8f0;
        animation: pvSlideDown 0.25s ease;
    }
    .pv-header-card.expanded .pv-header-collapsible {
        display: flex;
    }
    @keyframes pvSlideDown {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .pv-desktop-only {
        display: none !important;
    }
    .pv-team-group h4 { font-size: 0.7rem; margin-bottom: 4px; }
    .pv-avatar { width: 28px; height: 28px; font-size: 0.7rem; }

    /* Services grid */
    .pv-services-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    /* Card */
    .pv-card-body { padding: 1.2rem 1.2rem 0; }
    .pv-service-title { font-size: 1rem; }
    .pv-badges-row { gap: 5px; margin-bottom: 12px; }
    .pv-service-type,
    .pv-card-status { font-size: 0.65rem; padding: 3px 8px; }
    .pv-service-dates p { padding: 8px 12px; font-size: 0.8rem; }
    .pv-actions-row { padding: 10px 1.2rem; }
    .pv-btn-enter-service { padding: 12px; font-size: 0.85rem; }

    /* Placeholder */
    .pv-placeholder { padding: 2.5rem 1.5rem; }
    .pv-placeholder i { font-size: 2.5rem; }

    /* Modal */
    .pj-modern-modal {
        width: 95%;
        max-height: 90vh;
        border-radius: 20px;
    }
    .pj-modern-header { padding: 1.25rem 1.5rem; }
    .pj-modern-body { padding: 1.5rem; }
    .pj-modern-footer { padding: 1.25rem 1.5rem; }
    .pj-modern-form-row { grid-template-columns: 1fr; gap: 0; }
    .pj-modern-header h2 { font-size: 1.1rem; }

    .pv-btn-new-service { font-size: 0.82rem; padding: 8px 14px; }
}

@media (max-width: 480px) {
    .pv-container { padding: 0.5rem; }
    .pv-header-card { padding: 1rem; gap: 1rem; }
    .pv-header-left { gap: 0.75rem; }
    .pv-logo { width: 48px; height: 48px; }
    .pv-logo-letter { font-size: 1.2rem; }
    .pv-info h1 { font-size: 1.05rem; }
    .pv-info p { font-size: 0.78rem; }

    .pv-header-right {
        flex-wrap: wrap;
    }
    .pv-btn-new-service {
        width: 100%;
        justify-content: center;
    }

    .pv-card-body { padding: 1rem 1rem 0; }
    .pv-service-title { font-size: 0.95rem; }
    .pv-actions-row { padding: 8px 1rem; gap: 2px; }
    .pv-icon-btn { width: 30px; height: 30px; font-size: 0.95rem; }

    .pj-modern-modal { width: 100%; border-radius: 16px 16px 0 0; }
}
</style>

<div class="pv-container">
    <div class="pv-header-card" id="pvHeaderCard">
        <div class="pv-header-left">
            <a href="index.php?module=projects&action=index" class="pv-back-arrow" title="Volver a Proyectos">
                <i class="ph ph-arrow-left"></i>
            </a>
            <div class="pv-logo">
                <?php if ($hasImage): ?>
                    <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo">
                <?php else: ?>
                    <div class="pv-logo-letter"><?php echo strtoupper(substr(trim($project['name']), 0, 1)); ?></div>
                <?php endif; ?>
            </div>
            <div class="pv-info">
                <div class="pv-info-title">
                    <h1><?php echo htmlspecialchars($project['name']); ?></h1>
                    <span class="pv-status-badge <?php echo $project['status']; ?>">
                        <span class="pv-status-dot <?php echo $project['status']; ?>"></span>
                        <?php echo $project['status'] === 'active' ? 'Activo' : 'Archivado'; ?>
                    </span>
                    <button class="pv-header-toggle" onclick="document.getElementById('pvHeaderCard').classList.toggle('expanded')" aria-label="Desplegar detalles">
                        <i class="ph ph-caret-down"></i>
                    </button>
                </div>
                <p><?php echo htmlspecialchars($project['service_name'] ?? 'Sin servicio'); ?> · <?php echo htmlspecialchars($project['client_name'] ?? 'Sin cliente'); ?></p>
            </div>
        </div>

        <div class="pv-header-collapsible">
            
            <button class="pv-btn-new-service" onclick="openServiceModal()">
                <i class="ph ph-plus"></i> Nueva Tarea
            </button>
        </div>

        <div class="pv-header-right pv-desktop-only">
            
            <button class="pv-btn-new-service" onclick="openServiceModal()">
                <i class="ph ph-plus"></i> Nueva Tarea
            </button>
        </div>
    </div>

    <?php if (empty($projectServices)): ?>
        <div class="pv-placeholder">
            <i class="ph ph-rocket-launch"></i>
            <h3>Aún no hay tareas</h3>
            <p>Añade una nueva tarea para empezar a trabajar en este proyecto.</p>
        </div>
    <?php else: ?>
        <div class="pv-services-grid">
            <?php foreach($projectServices as $srv): ?>
                <div class="pv-service-card">
                    <div class="pv-card-body">
                        <h3 class="pv-service-title" title="<?php echo htmlspecialchars($srv['title']); ?>"><?php echo htmlspecialchars($srv['title']); ?></h3>
                        
                        <div class="pv-badges-row">
                            <span class="pv-service-type"><i class="ph ph-tag"></i> <?php echo htmlspecialchars($srv['service_type_name'] ?? 'General'); ?></span>
                            <?php
                            $stClass = 'st-pending';
                            $sLabel = 'Pendiente';
                            if($srv['status'] == 'completed') { $stClass = 'st-completed'; $sLabel = 'Completado'; }
                            elseif($srv['status'] == 'in_progress') { $stClass = 'st-progress'; $sLabel = 'En proceso'; }
                            elseif($srv['status'] == 'pending') { $stClass = 'st-pending'; $sLabel = 'Pendiente'; }
                            else { $sLabel = htmlspecialchars($srv['status']); }
                            ?>
                            <span class="pv-card-status <?php echo $stClass; ?>">
                                <span class="pv-sdot"></span>
                                <?php echo $sLabel; ?>
                            </span>
                        </div>

                        <div class="pv-service-dates">
                            <p>
                                <span><i class="ph ph-calendar-plus"></i> Inicio</span>
                                <strong><?php echo $srv['start_date'] ? date('d/m/Y', strtotime($srv['start_date'])) : '—'; ?></strong>
                            </p>
                            <p>
                                <span><i class="ph ph-flag-checkered"></i> Entrega</span>
                                <strong><?php echo $srv['due_date'] ? date('d/m/Y', strtotime($srv['due_date'])) : '—'; ?></strong>
                            </p>
                        </div>
                    </div>

                    <div class="pv-actions-row">
                        <?php if (!empty($srv['drive_folder_link'])): ?>
                            <a href="<?php echo htmlspecialchars($srv['drive_folder_link']); ?>" target="_blank" class="pv-icon-btn" title="Abrir en Drive">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/d/da/Google_Drive_logo.png" width="16">
                            </a>
                        <?php endif; ?>
                        <span class="pv-actions-spacer"></span>
                        <button class="pv-icon-btn" onclick='editService(<?php echo json_encode($srv, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="Editar"><i class="ph ph-pencil-simple"></i></button>
                        <button class="pv-icon-btn danger" onclick="deleteService(<?php echo $srv['id']; ?>)" title="Eliminar"><i class="ph ph-trash"></i></button>
                    </div>

                    <a href="index.php?module=projects&action=view_task&id=<?php echo $srv['id']; ?>" class="pv-btn-enter-service">
                        Entrar a la Tarea <i class="ph ph-arrow-right"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Nueva Tarea -->
<div class="pj-modern-modal-overlay" id="serviceModal">
    <div class="pj-modern-modal">
        <div class="pj-modern-header">
            <h2>Nueva Tarea</h2>
            <button class="pj-modern-close" onclick="closeServiceModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="pj-modern-body">
            <form id="serviceForm">
                <input type="hidden" name="id" id="srvId" value="">
                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                
                <div class="pj-modern-group">
                    <label>Título de la Tarea *</label>
                    <input type="text" name="title" id="srvTitle" required placeholder="Ej: Diseño de Logotipo">
                </div>

                <div class="pj-modern-group">
                    <label>Tipo de Tarea</label>
                    <select name="service_id" id="srvType" required>
                        <option value="">— Seleccionar —</option>
                        <?php foreach($allServices as $sv): ?>
                            <option value="<?php echo $sv['id']; ?>"><?php echo htmlspecialchars($sv['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pj-modern-group" id="groupStatus" style="display:none;">
                    <label>Estado</label>
                    <select name="status" id="srvStatus">
                        <option value="pending">Pendiente</option>
                        <option value="in_progress">En proceso</option>
                        <option value="completed">Completado</option>
                    </select>
                </div>

                <div class="pj-modern-form-row">
                    <div class="pj-modern-group">
                        <label>Fecha de Inicio</label>
                        <input type="date" name="start_date" id="srvStart">
                    </div>
                    <div class="pj-modern-group">
                        <label>Fecha de Entrega</label>
                        <input type="date" name="due_date" id="srvDue">
                    </div>
                </div>

                <div class="pj-modern-group">
                    <label>Descripción</label>
                    <textarea name="description" id="srvDesc" placeholder="Detalles de este servicio..."></textarea>
                </div>

                <?php if (!empty($project['drive_folder_id'])): ?>
                <div class="pj-modern-group" style="background: #f1f5f9; padding: 15px; border-radius: 12px; border: 1px dashed #cbd5e1; margin-bottom: 0;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; text-transform:none; font-size:0.95rem; margin:0;">
                        <input type="checkbox" name="create_subfolder" value="1" style="width:auto; margin:0;" checked>
                        <span><img src="https://upload.wikimedia.org/wikipedia/commons/d/da/Google_Drive_logo.png" width="16" style="vertical-align:middle;margin-right:4px;"> Crear subcarpeta en Google Drive</span>
                    </label>
                    <p style="font-size:0.8rem; color:var(--text-muted); margin: 6px 0 0 28px;">Se creará automáticamente dentro de la carpeta del proyecto principal.</p>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <div class="pj-modern-footer">
            <button class="pj-btn-modern-cancel" onclick="closeServiceModal()">Cancelar</button>
            <button class="pj-btn-modern-save" id="btnSaveService" onclick="saveProjectService()">
                <i class="ph ph-check"></i> Guardar Tarea
            </button>
        </div>
    </div>
</div>

<script>
function openServiceModal() {
    document.getElementById('serviceForm').reset();
    document.getElementById('srvId').value = '';
    document.getElementById('groupStatus').style.display = 'none';
    document.querySelector('#serviceModal h2').textContent = 'Nueva Tarea';
    document.getElementById('btnSaveService').innerHTML = '<i class="ph ph-check"></i> Guardar Tarea';
    document.getElementById('serviceModal').classList.add('show');
}

function editService(srv) {
    document.getElementById('serviceForm').reset();
    document.getElementById('srvId').value = srv.id;
    document.getElementById('srvTitle').value = srv.title;
    document.getElementById('srvType').value = srv.service_id;
    document.getElementById('srvStart').value = srv.start_date || '';
    document.getElementById('srvDue').value = srv.due_date || '';
    document.getElementById('srvDesc').value = srv.description || '';
    
    document.getElementById('srvStatus').value = srv.status || 'pending';
    document.getElementById('groupStatus').style.display = 'block';
    
    document.querySelector('#serviceModal h2').textContent = 'Editar Tarea';
    document.getElementById('btnSaveService').innerHTML = '<i class="ph ph-check"></i> Actualizar Tarea';
    document.getElementById('serviceModal').classList.add('show');
}

async function deleteService(id) {
    if(!confirm('¿Estás seguro de eliminar este servicio?')) return;
    
    try {
        const formData = new FormData();
        formData.append('id', id);
        const resp = await fetch('index.php?module=projects&action=ajax_delete_project_service', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();
        if(data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error al eliminar');
        }
    } catch(err) {
        console.error(err);
        alert('Error de conexión');
    }
}

function closeServiceModal() {
    document.getElementById('serviceModal').classList.remove('show');
}

document.getElementById('serviceModal').addEventListener('click', function(e) {
    if (e.target === this) closeServiceModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeServiceModal();
});

async function saveProjectService() {
    const btn = document.getElementById('btnSaveService');
    const form = document.getElementById('serviceForm');
    
    const title = document.getElementById('srvTitle').value.trim();
    const type = document.getElementById('srvType').value;
    
    if (!title || !type) {
        alert('El título y el tipo de servicio son obligatorios.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';

    const formData = new FormData(form);

    try {
        const resp = await fetch('index.php?module=projects&action=ajax_save_project_service', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();
        
        if (data.success) {
            closeServiceModal();
            window.location.reload();
        } else {
            alert(data.message || 'Error al guardar el servicio.');
            btn.disabled = false;
            btn.innerHTML = '<i class="ph ph-check"></i> Guardar Servicio';
        }
    } catch (err) {
        console.error(err);
        alert('Error de conexión.');
        btn.disabled = false;
        btn.innerHTML = '<i class="ph ph-check"></i> Guardar Servicio';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
