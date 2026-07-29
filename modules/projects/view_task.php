<?php
// modules/projects/view_task.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

$taskId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$taskId) {
    header("Location: index.php?module=projects&action=index");
    exit();
}

// Fetch task and project data
$stmt = $db->prepare("
    SELECT ps.*, 
           mp.name AS project_name, mp.id AS project_id,
           s.name AS service_type_name
    FROM project_services ps
    LEFT JOIN module_projects mp ON ps.project_id = mp.id
    LEFT JOIN services s ON ps.service_id = s.id
    WHERE ps.id = ?
");
$stmt->execute([$taskId]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    echo "Tarea no encontrada.";
    exit();
}

// Fetch Groups and Cards
$stmtGroups = $db->prepare("SELECT * FROM task_groups WHERE project_service_id = ? ORDER BY sort_order ASC, created_at ASC");
$stmtGroups->execute([$taskId]);
$groups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

$cardsByGroup = [];
$showTrash = isset($_GET['show_trash']) ? true : false;
if (!empty($groups)) {
    $groupIds = array_column($groups, 'id');
    $placeholders = str_repeat('?,', count($groupIds) - 1) . '?';
    if ($showTrash) {
        $stmtCards = $db->prepare("SELECT * FROM task_cards WHERE task_group_id IN ($placeholders) AND deleted_at IS NOT NULL ORDER BY sort_order ASC, created_at ASC");
    } else {
        $stmtCards = $db->prepare("SELECT * FROM task_cards WHERE task_group_id IN ($placeholders) AND deleted_at IS NULL ORDER BY sort_order ASC, created_at ASC");
    }
    $stmtCards->execute($groupIds);
    $allCards = $stmtCards->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($allCards)) {
        $cardIds = array_column($allCards, 'id');
        $cPlaceholders = str_repeat('?,', count($cardIds) - 1) . '?';
        $stmtSub = $db->prepare("SELECT * FROM task_card_subtasks WHERE task_card_id IN ($cPlaceholders) ORDER BY sort_order ASC, created_at ASC");
        $stmtSub->execute($cardIds);
        $allSubs = $stmtSub->fetchAll(PDO::FETCH_ASSOC);

        $subsByCard = [];
        foreach ($allSubs as $s) {
            $subsByCard[$s['task_card_id']][] = $s;
        }

        $stmtLogs = $db->prepare("
            SELECT l.*, u.name as user_name 
            FROM task_card_logs l
            JOIN users u ON l.user_id = u.id
            WHERE l.task_card_id IN ($cPlaceholders)
            ORDER BY l.created_at DESC
        ");
        $stmtLogs->execute($cardIds);
        $allLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        $logsByCard = [];
        foreach ($allLogs as $l) {
            $logsByCard[$l['task_card_id']][] = $l;
        }

        foreach ($allCards as &$c) {
            $c['subtasks'] = $subsByCard[$c['id']] ?? [];
            $c['logs'] = $logsByCard[$c['id']] ?? [];
            $cardsByGroup[$c['task_group_id']][] = $c;
        }
    }
}

$totalCards = 0;
$completedCards = 0;
if (!empty($allCards)) {
    $totalCards = count($allCards);
    foreach ($allCards as $c) {
        if ($c['status'] === 'Terminado') {
            $completedCards++;
        }
    }
}
$progressPercent = $totalCards > 0 ? round(($completedCards / $totalCards) * 100) : 0;

// Fetch all users for assignment
$stmtUsers = $db->query("SELECT id, name FROM users ORDER BY name ASC");
$allUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
$usersJson = json_encode($allUsers);

require_once 'includes/header.php';
?>

<style>
.vt-container {
    padding: 0.5rem 2rem 2rem 2rem;
    font-family: var(--font-main, 'Inter'), sans-serif;
    width: 100%;
    box-sizing: border-box;
}
.vt-progress-bar {
    background: #f1f5f9;
    border-radius: 999px;
    height: 8px;
    width: 100%;
    margin-bottom: 1.5rem;
    overflow: hidden;
    position: relative;
}
.vt-header-card {
    background: var(--bg-surface, #ffffff);
    border-radius: 20px;
    border: 1px solid var(--border-color, #e2e8f0);
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
}
.vt-header-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}
.vt-back-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: #f8fafc;
    border-radius: 50%;
    color: var(--text-main, #0f172a);
    text-decoration: none;
    font-size: 1.2rem;
    transition: all 0.2s;
    border: 1px solid #e2e8f0;
}
.vt-back-arrow:hover {
    background: #e2e8f0;
    transform: translateX(-2px);
}
.vt-title-group {
    display: flex;
    flex-direction: column;
}
.vt-project-name {
    font-size: 0.85rem;
    color: var(--text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 4px;
}
.vt-task-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}
.vt-task-type {
    font-size: 0.75rem;
    color: var(--primary-color, #22c55e);
    background: rgba(34,197,94,0.15);
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    align-self: flex-start;
}
.vt-btn-share {
    background: #ffffff;
    color: var(--text-main, #0f172a);
    border: 1px solid var(--border-color, #cbd5e1);
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    font-size: 0.95rem;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.vt-btn-share:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    transform: translateY(-1px);
}
.vt-btn-share i {
    font-size: 1.2rem;
    color: var(--primary-color, #22c55e);
}
.vt-main-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    align-items: start;
}
.vt-panel {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid var(--border-color, #e2e8f0);
    padding: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.vt-placeholder-content {
    text-align: center;
    color: var(--text-muted, #64748b);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    min-height: 300px;
}
.vt-placeholder-content i {
    font-size: 3rem;
    color: #cbd5e1;
}
.vt-placeholder-content h3 {
    font-size: 1.2rem;
    color: var(--text-main, #334155);
    margin: 0;
}
.vt-placeholder p { margin: 0; font-size: 0.95rem; }

/* Groups and Cards */
.vt-group-container {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.vt-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.vt-group-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
    margin: 0;
}
.vt-btn-add-card {
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    color: var(--text-muted, #64748b);
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.vt-btn-add-card:hover {
    background: #f1f5f9;
    color: var(--text-main, #0f172a);
    border-color: #94a3b8;
}
.vt-card {
    background: #ffffff;
    border: none;
    border-radius: 16px;
    margin-bottom: 1rem;
    overflow: hidden;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
}
.vt-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.06);
}
.vt-card-header {
    padding: 1.25rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    background: transparent;
    transition: background 0.2s;
}
.vt-card-header:hover {
    background: #f8fafc;
}
.vt-card-title {
    font-weight: 700;
    color: var(--text-main, #0f172a);
    font-size: 1rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.vt-card-status {
    font-size: 0.7rem;
    padding: 4px 12px;
    border-radius: 999px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.vt-status-Nuevo { background: #e0e7ff; color: #4338ca; }
.vt-status-En-proceso { background: #fef3c7; color: #b45309; }
.vt-status-En-revisión { background: #ffe4e6; color: #be123c; }
.vt-status-Terminado { background: #dcfce3; color: #15803d; }

.vt-card-body {
    padding: 1.5rem;
    border-top: 1px dashed #e2e8f0;
    display: none; 
    background: #ffffff;
}
.vt-card.expanded .vt-card-body {
    display: block;
}
.vt-card-desc {
    font-size: 0.95rem;
    color: var(--text-muted, #475569);
    margin-bottom: 1.5rem;
    white-space: pre-wrap;
    line-height: 1.5;
}
.vt-card-dates {
    display: flex;
    gap: 1.5rem;
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 1.5rem;
    background: #f8fafc;
    padding: 10px 14px;
    border-radius: 12px;
    border: 1px solid #eef2f6;
}
.vt-card-dates strong { color: #1e293b; font-weight: 700; }
.vt-card-ref a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--primary-color, #22c55e);
    text-decoration: none;
    background: rgba(34,197,94,0.1);
    padding: 6px 12px;
    border-radius: 8px;
}
.vt-card-ref a:hover { background: rgba(34,197,94,0.2); }

.vt-btn-card-action {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}
.vt-btn-card-action:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.vt-btn-card-action.danger {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #ef4444;
}
.vt-btn-card-action.danger:hover {
    background: #fee2e2;
    color: #dc2626;
}

.vt-top-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.vt-top-actions h2 { margin: 0; font-size: 1.2rem; }
.vt-btn-primary {
    background: var(--text-main, #0f172a);
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: flex; align-items: center; gap: 6px;
}

.vt-tooltip-soon {
    display: none;
    position: absolute;
    top: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    background: #1e293b;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 8px;
    white-space: nowrap;
    z-index: 10;
    pointer-events: none;
    animation: fadeInTooltip 0.2s ease;
}
.vt-tooltip-soon::before {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-bottom-color: #1e293b;
}
.vt-tooltip-soon.show {
    display: block;
}
@keyframes fadeInTooltip {
    from { opacity: 0; transform: translateX(-50%) translateY(4px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
}

/* Modals */
.vt-modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(6px);
    display: none; justify-content: center; align-items: center;
    z-index: 9999;
    padding: 1.5rem;
}
.vt-modal {
    background: #fff; border-radius: 20px;
    width: 100%; max-width: 520px;
    box-shadow: 0 25px 60px -12px rgba(0,0,0,0.3);
    display: flex; flex-direction: column;
    max-height: 92vh;
    animation: modalIn 0.25s ease;
}
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.96) translateY(12px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
#cardModal .vt-modal {
    max-width: 1300px;
    width: 95vw;
}
.vt-modal-header {
    padding: 1.5rem 2rem; border-bottom: 1px solid #e2e8f0;
    display: flex; justify-content: space-between; align-items: center;
    background: #f8fafc;
    border-radius: 20px 20px 0 0;
}
.vt-modal-header h2 {
    margin: 0; font-size: 1.25rem; font-weight: 800;
    color: var(--text-main, #0f172a);
    display: flex; align-items: center; gap: 10px;
}
.vt-modal-close {
    background: none; border: none; font-size: 1.5rem; cursor: pointer;
    color: #94a3b8; width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.vt-modal-close:hover { background: #e2e8f0; color: #0f172a; }
.vt-modal-body {
    padding: 2rem;
    overflow-y: auto;
    overflow-x: hidden;
}
.vt-form-group { margin-bottom: 1.2rem; display: flex; flex-direction: column; gap: 6px; }
.vt-form-group label {
    font-size: 0.8rem; font-weight: 700; color: #334155;
    text-transform: uppercase; letter-spacing: 0.4px;
}
.vt-form-group input[type="text"],
.vt-form-group input[type="date"],
.vt-form-group select,
.vt-form-group textarea {
    padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 10px;
    font-family: inherit; font-size: 0.92rem; background: #f8fafc;
    transition: all 0.2s; color: #1e293b;
}
.vt-form-group input:focus,
.vt-form-group select:focus,
.vt-form-group textarea:focus {
    outline: none; border-color: var(--primary-color, #22c55e);
    background: #fff; box-shadow: 0 0 0 3px rgba(34,197,94,0.12);
}
.vt-form-group textarea { resize: vertical; min-height: 80px; }
.vt-modal-footer {
    padding: 1.25rem 2rem; border-top: 1px solid #e2e8f0;
    display: flex; justify-content: flex-end; gap: 0.75rem;
    background: #ffffff;
    border-radius: 0 0 20px 20px;
}
.vt-btn-cancel {
    background: #f1f5f9; border: 1px solid #e2e8f0; padding: 10px 22px;
    border-radius: 10px; font-weight: 600; cursor: pointer;
    color: #475569; transition: all 0.2s; font-family: inherit;
}
.vt-btn-cancel:hover { background: #e2e8f0; }

/* Card Modal Column Headers */
.vt-col-header {
    margin: 0 0 1.25rem; padding-bottom: 10px;
    border-bottom: 2px solid #f1f5f9;
    font-size: 0.9rem; font-weight: 800; color: #0f172a;
    display: flex; align-items: center; gap: 8px;
}
.vt-col-header i {
    font-size: 1.1rem; color: var(--primary-color, #22c55e);
}

/* Card Modal Grid */
.vt-card-modal-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.3fr) minmax(0, 1fr);
    gap: 2rem;
}

/* File upload boxes */
.vt-upload-box {
    background: #f8fafc; padding: 1rem; border-radius: 12px;
    border: 1px dashed #cbd5e1; margin-bottom: 1rem;
    transition: border-color 0.2s;
}
.vt-upload-box:hover { border-color: #94a3b8; }
.vt-upload-box label {
    font-weight: 700; color: #334155; font-size: 0.82rem;
    display: flex; align-items: center; gap: 6px; margin-bottom: 8px;
    text-transform: uppercase; letter-spacing: 0.4px;
}
.vt-upload-box input[type="file"] { font-size: 0.85rem; }

/* Control toggles */
.vt-control-item {
    display: flex; align-items: center; gap: 10px; cursor: pointer;
    padding: 8px 12px; border-radius: 10px; transition: background 0.2s;
}
.vt-control-item:hover { background: #f8fafc; }
.vt-control-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary-color, #22c55e); }
.vt-control-item span { font-weight: 600; color: #334155; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; }

/* Subtasks UI */
.vt-subtask-item {
    display: flex; align-items: center; justify-content: space-between;
    background: #ffffff; padding: 8px 12px; border-radius: 10px; margin-bottom: 8px;
    border: 1px solid #e2e8f0; transition: all 0.2s;
}
.vt-subtask-item:hover { border-color: #cbd5e1; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.vt-subtask-left { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; }
.vt-subtask-left input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary-color, #22c55e); }
.vt-subtask-left span.completed { text-decoration: line-through; color: #94a3b8; }
.vt-subtask-del { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1rem; opacity: 0.5; transition: opacity 0.2s; }
.vt-subtask-del:hover { opacity: 1; }


.vt-dates-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
}
.vt-date-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f8fafc;
    color: var(--text-main, #334155);
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    border: 1px dashed #cbd5e1;
}
.vt-date-badge i {
    color: var(--text-muted, #64748b);
    font-size: 1rem;
}
.vt-date-separator {
    color: #cbd5e1;
    font-size: 1rem;
}

/* Collapsible header - desktop: always show everything */
.vt-header-toggle {
    display: none;
}
.vt-header-collapsible {
    display: contents;
}
.vt-header-right-inner {
    display: none;
}
.vt-desktop-only {
    display: flex;
}

@media (max-width: 1024px) {
    .vt-main-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .vt-container {
        padding: 0.25rem 0.5rem 0.5rem;
    }
    .vt-progress-bar {
        display: none;
    }

    /* Header */
    .vt-header-card {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
        padding: 1.25rem;
        margin-bottom: 0.75rem;
        border-radius: 16px;
    }
    .vt-header-left {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    .vt-back-arrow {
        display: none;
    }
    .vt-project-name {
        font-size: 0.75rem;
    }
    .vt-task-title {
        font-size: 1.15rem;
        flex-wrap: wrap;
        gap: 8px;
    }
    .vt-task-type {
        font-size: 0.68rem;
        padding: 3px 8px;
    }
    .vt-dates-row {
        flex-wrap: wrap;
        gap: 6px;
    }
    .vt-date-badge {
        font-size: 0.78rem;
        padding: 5px 10px;
    }
    .vt-date-separator {
        display: none;
    }

    /* Collapsible header on mobile */
    .vt-header-toggle {
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
    .vt-header-card.expanded .vt-header-toggle {
        transform: rotate(180deg);
        background: #e2e8f0;
    }
    .vt-header-collapsible {
        display: none;
        flex-direction: column;
        gap: 10px;
        margin-top: 10px;
        animation: slideDown 0.25s ease;
    }
    .vt-header-card.expanded .vt-header-collapsible {
        display: flex;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .vt-header-right-inner {
        display: flex;
        justify-content: flex-end;
        padding-top: 6px;
    }
    .vt-desktop-only {
        display: none !important;
    }
    .vt-btn-secondary {
        font-size: 0.82rem;
        padding: 8px 14px;
    }

    /* Main grid */
    .vt-main-grid {
        gap: 0.5rem;
    }
    .vt-panel {
        padding: 1.25rem;
        border-radius: 16px;
    }
    .vt-top-actions {
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .vt-top-actions h2 {
        font-size: 1.05rem;
    }
    .vt-btn-primary {
        font-size: 0.82rem;
        padding: 7px 12px;
        white-space: nowrap;
    }

    /* Groups */
    .vt-group-container {
        padding: 1rem;
        margin-bottom: 1rem;
    }
    .vt-group-title {
        font-size: 0.95rem;
    }
    .vt-placeholder-content {
        min-height: 200px;
        gap: 0.75rem;
    }
    .vt-placeholder-content i {
        font-size: 2.5rem;
    }
    .vt-placeholder-content h3 {
        font-size: 1rem;
    }

    /* Cards */
    .vt-card-header {
        padding: 0.8rem 1rem;
    }
    .vt-card-title {
        font-size: 0.88rem;
    }
    .vt-card-body {
        padding: 1rem;
    }
    .vt-card-dates {
        flex-direction: column;
        gap: 0.5rem;
    }

    /* Progress bar */
    .vt-container > div:first-child {
        margin-bottom: 1rem !important;
    }

    /* Modals */
    /* Modal responsive */
    .vt-modal-overlay {
        padding: 0.75rem;
    }
    .vt-modal {
        width: 100%;
        max-width: 100%;
        border-radius: 16px;
    }
    #cardModal .vt-modal {
        width: 100%;
        max-width: 100%;
    }
    .vt-card-modal-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    .vt-modal-header {
        padding: 1.25rem;
        border-radius: 16px 16px 0 0;
    }
    .vt-modal-body {
        padding: 1.25rem;
    }
    .vt-modal-footer {
        padding: 1rem 1.25rem;
    }
    .vt-col-header {
        font-size: 0.85rem;
    }
}

@media (max-width: 480px) {
    .vt-container {
        padding: 0.25rem;
    }
    .vt-header-card {
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 0.5rem;
    }
    .vt-task-title {
        font-size: 1.05rem;
    }
    .vt-date-badge {
        font-size: 0.72rem;
        padding: 4px 8px;
    }
    .vt-main-grid {
        gap: 0.4rem;
    }
    .vt-panel {
        padding: 1rem;
        border-radius: 12px;
    }
    .vt-group-container {
        padding: 0.75rem;
    }
    .vt-card-header {
        padding: 0.7rem 0.8rem;
    }
    .vt-card-title {
        font-size: 0.82rem;
        gap: 6px;
    }
    .vt-card-status {
        font-size: 0.6rem;
        padding: 3px 7px;
    }
    .vt-modal {
        width: 100%;
        max-width: 100%;
        border-radius: 14px 14px 0 0;
    }
}
</style>

<div class="vt-container">
    
    <div class="vt-header-card" id="vtHeaderCard">
        <div class="vt-header-left">
            <a href="index.php?module=projects&action=view&id=<?php echo $task['project_id']; ?>" class="vt-back-arrow" title="Volver al Proyecto">
                <i class="ph ph-arrow-left"></i>
            </a>
            <div class="vt-title-group">
                <span class="vt-project-name">Proyecto: <?php echo htmlspecialchars($task['project_name']); ?></span>
                <h1 class="vt-task-title">
                    <?php echo htmlspecialchars($task['title']); ?>
                    <button class="vt-header-toggle" onclick="document.getElementById('vtHeaderCard').classList.toggle('expanded')" aria-label="Desplegar detalles">
                        <i class="ph ph-caret-down"></i>
                    </button>
                </h1>
                
                <div class="vt-header-collapsible">
                    <span class="vt-task-type"><i class="ph ph-tag"></i> <?php echo htmlspecialchars($task['service_type_name'] ?? 'General'); ?></span>
                    
                    <div class="vt-header-right-inner">
                        <button class="vt-btn-secondary" style="background:#fff; border:1px solid #e2e8f0; text-decoration:none; cursor:default; position:relative;" onclick="event.stopPropagation(); this.querySelector('.vt-tooltip-soon').classList.toggle('show')" title="Próximamente">
                            <i class="ph ph-share-network"></i> Compartir Tarea
                            <span class="vt-tooltip-soon">Próximamente</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="vt-header-right vt-desktop-only">
            <button class="vt-btn-secondary" style="background:#fff; border:1px solid #e2e8f0; text-decoration:none; cursor:default; position:relative;" onclick="this.querySelector('.vt-tooltip-soon').classList.toggle('show')" title="Próximamente">
                <i class="ph ph-share-network"></i> Compartir Tarea
                <span class="vt-tooltip-soon">Próximamente</span>
            </button>
        </div>
    </div>

    <div class="vt-main-grid">
        <!-- Columna Izquierda (Principal) -->
        <div class="vt-panel">
            <div class="vt-top-actions">
                <h2>Tablero de Tarea</h2>
                <button class="vt-btn-primary" onclick="openGroupModal()">
                    <i class="ph ph-plus"></i> Nuevo Grupo
                </button>
            </div>

            <?php if(empty($groups)): ?>
                <div class="vt-placeholder-content" style="border: 1px dashed #cbd5e1; border-radius: 16px;">
                    <i class="ph ph-kanban"></i>
                    <h3>Aún no hay grupos</h3>
                    <p>Crea un grupo (ej. "Fase 1", "Entregables") para empezar a añadir tarjetas.</p>
                </div>
                <div id="groups-container"></div>
            <?php else: ?>
                <div id="groups-container">
                <?php foreach($groups as $group): 
                    $cards = $cardsByGroup[$group['id']] ?? [];
                    $gColor = !empty($group['color']) ? $group['color'] : '#0f172a';
                ?>
                    <div class="vt-group-container" data-group-id="<?php echo $group['id']; ?>" style="border-top: 4px solid <?php echo htmlspecialchars($gColor); ?>;">
                        <div class="vt-group-header">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <i class="ph ph-dots-six-vertical drag-handle-group" style="cursor:grab; color:#cbd5e1; font-size:1.2rem;"></i>
                                <h3 class="vt-group-title"><?php echo htmlspecialchars($group['name']); ?> <span style="color:#94a3b8; font-size:0.9rem; font-weight:normal;">(<?php echo count($cards); ?>)</span></h3>
                            </div>
                            <div style="display:flex; gap:8px;">
                                <button class="vt-btn-add-card" onclick="editGroup(<?php echo $group['id']; ?>, '<?php echo addslashes(htmlspecialchars($group['name'])); ?>', '<?php echo htmlspecialchars($gColor); ?>')" title="Editar Grupo">
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <button class="vt-btn-add-card" onclick="deleteGroup(<?php echo $group['id']; ?>)" title="Eliminar Grupo">
                                    <i class="ph ph-trash"></i>
                                </button>
                                <?php if($showTrash): ?>
                                    <button class="vt-btn-add-card" onclick="restoreCard(<?php echo $card['id']; ?>)" title="Restaurar" style="color:#22c55e; border-color:#bbf7d0;"><i class="ph ph-arrow-u-up-left"></i></button>
                                <?php else: ?>
                                    <button class="vt-btn-add-card" onclick="openCardModal(<?php echo $group['id']; ?>)" title="Añadir Tarjeta"><i class="ph ph-plus"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="vt-cards-list" data-group-id="<?php echo $group['id']; ?>" style="min-height: 50px;">
                            <?php 
                            if (!empty($cards)): 
                            ?>
                                <?php foreach($cards as $card): 
                                    $statusClass = 'vt-status-' . str_replace(' ', '-', $card['status']);
                                ?>
                                    <div class="vt-card" data-card-id="<?php echo $card['id']; ?>">
                                        <div class="vt-card-header" onclick="this.parentElement.classList.toggle('expanded')">
                                            <div style="flex:1;">
                                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                                    <i class="ph ph-dots-six-vertical drag-handle-card" style="color:#cbd5e1; cursor:grab;" onclick="event.stopPropagation()"></i>
                                                    <?php 
                                                        $priorityIconColor = '#94a3b8';
                                                        if($card['priority'] === 'Alta') $priorityIconColor = '#ef4444';
                                                        if($card['priority'] === 'Baja') $priorityIconColor = '#3b82f6';
                                                    ?>
                                                    <i class="ph ph-flag-banner" style="color:<?php echo $priorityIconColor; ?>;" title="Prioridad <?php echo htmlspecialchars($card['priority'] ?? 'Media'); ?>"></i>
                                                    
                                                    <?php 
                                                    $tags = !empty($card['tags']) ? json_decode($card['tags'], true) : [];
                                                    if(is_array($tags)): 
                                                        foreach($tags as $tag):
                                                    ?>
                                                        <span style="background:#e2e8f0; color:#475569; font-size:0.7rem; padding:2px 6px; border-radius:4px; font-weight:600; text-transform:uppercase;"><?php echo htmlspecialchars($tag); ?></span>
                                                    <?php 
                                                        endforeach;
                                                    endif; 
                                                    ?>
                                                </div>
                                                <h4 class="vt-card-title">
                                                    <i class="ph ph-caret-down" style="color:#94a3b8"></i> 
                                                    <?php echo htmlspecialchars($card['title']); ?>
                                                    <?php if($card['is_locked']): ?>
                                                        <i class="ph ph-lock-key" style="color:#ef4444; margin-left:4px;" title="Bloqueada"></i>
                                                    <?php endif; ?>
                                                    <?php if($card['is_approved']): ?>
                                                        <i class="ph ph-check-circle" style="color:#22c55e; margin-left:4px;" title="Aprobada"></i>
                                                    <?php endif; ?>
                                                </h4>
                                            </div>
                                            <span class="vt-card-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($card['status']); ?></span>
                                        </div>
                                        <div class="vt-card-body">
                                            <?php if(!empty($card['description'])): ?>
                                                <div class="vt-card-desc"><?php echo nl2br(htmlspecialchars($card['description'])); ?></div>
                                            <?php endif; ?>
                                            
                                            <div class="vt-card-dates">
                                                <span><i class="ph ph-calendar"></i> Inicio: <strong><?php echo $card['start_date'] ? date('d/m/Y', strtotime($card['start_date'])) : '-'; ?></strong></span>
                                                <span><i class="ph ph-flag"></i> Entrega: <strong><?php echo $card['due_date'] ? date('d/m/Y', strtotime($card['due_date'])) : '-'; ?></strong></span>
                                            </div>

                                            <?php if(!empty($card['reference_image'])): ?>
                                                <div class="vt-card-ref">
                                                    <a href="<?php echo htmlspecialchars($card['reference_image']); ?>" target="_blank">
                                                        <i class="ph ph-image"></i> Ver Imagen de Referencia
                                                    </a>
                                                </div>
                                            <?php endif; ?>

                                            <?php if(!empty($card['editable_file'])): ?>
                                                <div class="vt-card-ref" style="margin-top: 8px;">
                                                    <a href="<?php echo htmlspecialchars($card['editable_file']); ?>" target="_blank" style="color: #2563eb; background: rgba(37,99,235,0.1);">
                                                        <i class="ph ph-file-code"></i> Ver Archivo Editable
                                                    </a>
                                                </div>
                                            <?php endif; ?>

                                            <?php if(!empty($card['subtasks'])): 
                                                $totalSub = count($card['subtasks']);
                                                $compSub = 0;
                                                foreach($card['subtasks'] as $st) { if($st['is_completed']) $compSub++; }
                                                $subPercent = $totalSub > 0 ? round(($compSub/$totalSub)*100) : 0;
                                            ?>
                                                <div style="margin-top: 1rem;">
                                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                                        <strong style="font-size:0.85rem; color:#64748b;">Subtareas (<?php echo $compSub; ?>/<?php echo $totalSub; ?>)</strong>
                                                        <span style="font-size:0.75rem; color:#94a3b8;"><?php echo $subPercent; ?>%</span>
                                                    </div>
                                                    <div style="background:#e2e8f0; border-radius:999px; height:4px; width:100%; margin-bottom:8px; overflow:hidden;">
                                                        <div style="background:#3b82f6; height:100%; width:<?php echo $subPercent; ?>%;"></div>
                                                    </div>

                                                    <div style="display:flex; flex-direction:column; gap:6px;">
                                                        <?php foreach($card['subtasks'] as $st): ?>
                                                            <div style="font-size:0.85rem; display:flex; align-items:center; gap:6px; color: <?php echo $st['is_completed'] ? '#94a3b8' : '#334155'; ?>;">
                                                                <i class="ph <?php echo $st['is_completed'] ? 'ph-check-circle' : 'ph-circle'; ?>"></i>
                                                                <span style="<?php echo $st['is_completed'] ? 'text-decoration:line-through;' : ''; ?>"><?php echo htmlspecialchars($st['title']); ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <div style="margin-top: 1.5rem; border-top: 1px dashed #e2e8f0; padding-top: 1rem; display:flex; justify-content:flex-end; gap:0.5rem;">
                                                <button class="vt-btn-card-action" onclick="editCard(<?php echo htmlspecialchars(json_encode($card)); ?>)">
                                                    <i class="ph ph-pencil-simple"></i> Editar
                                                </button>
                                                <button class="vt-btn-card-action danger" onclick="deleteCard(<?php echo $card['id']; ?>)">
                                                    <i class="ph ph-trash"></i> Eliminar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Columna Derecha (Secundaria) -->
        <div class="vt-panel" style="border: 1px dashed var(--border-color, #cbd5e1);">
            <div class="vt-placeholder-content">
                <i class="ph ph-chat-teardrop-text"></i>
                <h3>Actividad y Comentarios</h3>
                <p>Próximamente.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Nuevo Grupo -->
<div class="vt-modal-overlay" id="groupModal">
    <div class="vt-modal">
        <div class="vt-modal-header">
            <h2>Nuevo Grupo</h2>
            <button class="vt-modal-close" onclick="closeGroupModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="vt-modal-body">
            <form id="groupForm">
                <input type="hidden" name="project_service_id" value="<?php echo $taskId; ?>">
                <input type="hidden" name="group_id" id="groupEditId" value="">
                <div class="vt-form-group">
                    <label>Nombre del Grupo *</label>
                    <input type="text" name="name" id="groupName" required placeholder="Ej: Semana 1, Fase de Diseño">
                </div>
                <div class="vt-form-group">
                    <label>Color del Grupo</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <input type="color" name="color" id="groupColor" value="#0f172a" style="width:40px; height:40px; padding:0; cursor:pointer; border-radius:4px;">
                        <span style="font-size:0.85rem; color:#64748b;">Elige un color para diferenciar esta columna.</span>
                    </div>
                </div>
            </form>
        </div>
        <div class="vt-modal-footer">
            <button class="vt-btn-cancel" onclick="closeGroupModal()">Cancelar</button>
            <button class="vt-btn-primary" onclick="saveGroup()">Guardar Grupo</button>
        </div>
    </div>
</div>

<!-- Modal: Nueva Tarjeta -->
<div class="vt-modal-overlay" id="cardModal">
    <div class="vt-modal">
        <div class="vt-modal-header">
            <h2>Nueva Tarjeta</h2>
            <button class="vt-modal-close" onclick="closeCardModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="vt-modal-body">
            <form id="cardForm" enctype="multipart/form-data">
                <input type="hidden" name="task_group_id" id="cardGroupId" value="">
                <input type="hidden" name="card_id" id="cardEditId" value="">
                <input type="hidden" name="subtasks" id="cardSubtasksJson" value="[]">
                
                <div class="vt-card-modal-grid">
                    
                    <!-- Columna Izquierda: Básico -->
                    <div>
                        <h4 class="vt-col-header"><i class="ph ph-info"></i> Información Básica</h4>
                        <div class="vt-form-group">
                            <label>Título *</label>
                            <input type="text" name="title" id="cardTitle" required placeholder="Título de la subtarea">
                        </div>
                        <div style="display:flex; gap:1rem;">
                            <div class="vt-form-group" style="flex:1;">
                                <label>Estado</label>
                                <select name="status" id="cardStatus">
                                    <option value="Nuevo">Nuevo</option>
                                    <option value="En proceso">En proceso</option>
                                    <option value="En revisión">En revisión</option>
                                    <option value="Terminado">Terminado</option>
                                </select>
                            </div>
                            <div class="vt-form-group" style="flex:1;">
                                <label>Prioridad</label>
                                <select name="priority" id="cardPriority">
                                    <option value="Baja">Baja</option>
                                    <option value="Media" selected>Media</option>
                                    <option value="Alta">Alta</option>
                                </select>
                            </div>
                        </div>
                        <div class="vt-form-group">
                            <label>Etiquetas</label>
                            <input type="text" id="tagsInputHelper" placeholder="Ej: Frontend, Diseño (separadas por coma)">
                            <input type="hidden" name="tags" id="cardTagsJson" value="[]">
                            <small style="color:#94a3b8; font-size:0.75rem;">Presiona coma para separar.</small>
                        </div>
                        <div style="display:flex; gap:1rem;">
                            <div class="vt-form-group" style="flex:1;">
                                <label>Fecha de Inicio</label>
                                <input type="date" name="start_date" id="cardStart">
                            </div>
                            <div class="vt-form-group" style="flex:1;">
                                <label>Fecha de Entrega</label>
                                <input type="date" name="due_date" id="cardDue">
                            </div>
                        </div>
                        <div class="vt-form-group">
                            <label>Descripción Corta</label>
                            <textarea name="description" id="cardDesc" rows="3" placeholder="Descripción resumida..."></textarea>
                        </div>
                    </div>

                    <!-- Columna Central: Detalles y Subtareas -->
                    <div>
                        <h4 class="vt-col-header"><i class="ph ph-code"></i> Desarrollo</h4>
                        <div class="vt-form-group">
                            <label>Detalles de la tarea</label>
                            <textarea name="details" id="cardDetails" rows="4" placeholder="Requerimientos extensos, especificaciones..."></textarea>
                        </div>
                        
                        <div class="vt-form-group">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <label>Subtareas (Checklist)</label>
                                <label style="font-size:0.75rem; color:#94a3b8; font-weight:normal; display:flex; align-items:center; gap:4px; cursor:pointer; text-transform:none; letter-spacing:0;">
                                    <input type="checkbox" id="hideCompletedSubtasks" onchange="renderSubtasks()"> Ocultar completadas
                                </label>
                            </div>
                            <div style="display:flex; gap:8px; margin-bottom: 12px; margin-top:4px;">
                                <input type="text" id="newSubtaskTitle" placeholder="Nueva subtarea..." style="flex:1;">
                                <button type="button" class="vt-btn-primary" onclick="addSubtask()">Añadir</button>
                            </div>
                            <div id="subtasksContainer">
                                <!-- Subtasks will be injected here via JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Archivos y Control -->
                    <div>
                        <h4 class="vt-col-header"><i class="ph ph-cloud-arrow-up"></i> Archivos en Drive</h4>
                        
                        <div class="vt-upload-box">
                            <label><i class="ph ph-image"></i> Imagen de Referencia</label>
                            <input type="file" name="reference_image" id="cardImage" accept="image/*">
                            <small style="color:#94a3b8; margin-top:6px; display:block; font-size:0.75rem;">Se subirá a la carpeta <strong>'Referencias'</strong>.</small>
                            <div id="cardImageLink" style="margin-top:8px; display:none; font-size:0.85rem;"></div>
                        </div>

                        <div class="vt-upload-box">
                            <label><i class="ph ph-file-code"></i> Archivo Editable</label>
                            <input type="file" name="editable_file" id="cardEditable">
                            <small style="color:#94a3b8; margin-top:6px; display:block; font-size:0.75rem;">Se subirá a la carpeta <strong>'Editables'</strong>.</small>
                            <div id="cardEditableLink" style="margin-top:8px; display:none; font-size:0.85rem;"></div>
                        </div>

                        <h4 class="vt-col-header" style="margin-top:1.5rem;"><i class="ph ph-sliders-horizontal"></i> Control</h4>
                        <div style="display:flex; flex-direction:column; gap:4px;">
                            <label class="vt-control-item">
                                <input type="hidden" name="is_locked" value="0">
                                <input type="checkbox" name="is_locked" id="cardLocked" value="1">
                                <span><i class="ph ph-lock-key" style="color:#ef4444;"></i> Congelar Tarjeta</span>
                            </label>
                            <label class="vt-control-item">
                                <input type="hidden" name="is_approved" value="0">
                                <input type="checkbox" name="is_approved" id="cardApproved" value="1">
                                <span><i class="ph ph-check-circle" style="color:#22c55e;"></i> Aprobar Tarjeta</span>
                            </label>
                            <small style="color:#94a3b8; font-size:0.75rem; padding-left:12px;">Las tarjetas congeladas no pueden ser modificadas ni movidas.</small>
                        </div>
                        
                        <h4 class="vt-col-header" style="margin-top:1.5rem;"><i class="ph ph-clock-counter-clockwise"></i> Historial de Actividad</h4>
                        <div id="cardLogsContainer" style="display:flex; flex-direction:column; gap:8px; max-height:150px; overflow-y:auto; font-size:0.8rem; color:#475569;">
                            <div class="vt-placeholder-content" style="padding:1rem; min-height:auto;">Aún no hay actividad.</div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
        <div class="vt-modal-footer">
            <button class="vt-btn-cancel" onclick="closeCardModal()">Cancelar</button>
            <button class="vt-btn-primary" onclick="saveCard()">Guardar Tarjeta</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
const allUsers = <?php echo $usersJson; ?>;
let currentSubtasks = [];
let subtasksSortable = null;

function initSubtaskSortable() {
    const container = document.getElementById('subtasksContainer');
    if (subtasksSortable) {
        subtasksSortable.destroy();
    }
    subtasksSortable = new Sortable(container, {
        animation: 150,
        handle: '.vt-subtask-drag',
        onEnd: function (evt) {
            const movedItem = currentSubtasks.splice(evt.oldIndex, 1)[0];
            currentSubtasks.splice(evt.newIndex, 0, movedItem);
            document.getElementById('cardSubtasksJson').value = JSON.stringify(currentSubtasks);
        }
    });
}

// Modals logic
function openGroupModal() {
    document.getElementById('groupForm').reset();
    document.getElementById('groupEditId').value = '';
    document.querySelector('#groupModal h2').innerText = 'Nuevo Grupo';
    document.getElementById('groupModal').style.display = 'flex';
}
function closeGroupModal() {
    document.getElementById('groupModal').style.display = 'none';
}

function editGroup(id, name, color) {
    document.getElementById('groupForm').reset();
    document.getElementById('groupEditId').value = id;
    document.getElementById('groupName').value = name;
    document.getElementById('groupColor').value = color || '#0f172a';
    document.querySelector('#groupModal h2').innerText = 'Editar Grupo';
    document.getElementById('groupModal').style.display = 'flex';
}

function deleteGroup(id) {
    Swal.fire({
        title: '¿Eliminar este grupo?',
        text: 'Debe estar vacío para poder eliminarlo.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('modules/projects/ajax_delete_task_group.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else Swal.fire('Error', data.message, 'error');
            });
        }
    });
}

// Handle Tags
document.getElementById('tagsInputHelper').addEventListener('input', function(e) {
    let val = this.value;
    if(val.includes(',')) {
        let parts = val.split(',').map(s => s.trim()).filter(s => s);
        document.getElementById('cardTagsJson').value = JSON.stringify(parts);
    } else {
        if(val.trim() === '') {
            document.getElementById('cardTagsJson').value = '[]';
        } else {
            document.getElementById('cardTagsJson').value = JSON.stringify([val.trim()]);
        }
    }
});

function renderSubtasks() {
    const container = document.getElementById('subtasksContainer');
    const hideCompleted = document.getElementById('hideCompletedSubtasks').checked;
    container.innerHTML = '';
    
    currentSubtasks.forEach((st, index) => {
        if (hideCompleted && st.is_completed) return; // Skip if hiding
        
        const div = document.createElement('div');
        div.className = 'vt-subtask-item';
        const checked = st.is_completed ? 'checked' : '';
        const textClass = st.is_completed ? 'completed' : '';
        
        div.innerHTML = `
            <div class="vt-subtask-left" style="display:flex; align-items:center;">
                <i class="ph ph-dots-six-vertical vt-subtask-drag" style="cursor:grab; color:#cbd5e1; margin-right:6px;"></i>
                <input type="checkbox" ${checked} onchange="toggleSubtask(${index}, this.checked)">
                <span class="${textClass}">${st.title}</span>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <input type="date" style="font-size:0.75rem; padding:2px 4px; border:1px solid #e2e8f0; border-radius:4px; color:#475569;" value="${st.due_date || ''}" onchange="updateSubtaskDate(${index}, this.value)">
                <select style="font-size:0.75rem; padding:2px 4px; border:1px solid #e2e8f0; border-radius:4px; color:#475569;" onchange="updateSubtaskUser(${index}, this.value)">
                    <option value="">Asignar...</option>
                    ${allUsers.map(u => `<option value="${u.id}" ${st.assigned_user_id == u.id ? 'selected' : ''}>${u.name}</option>`).join('')}
                </select>
                <button type="button" class="vt-subtask-del" onclick="removeSubtask(${index})"><i class="ph ph-trash"></i></button>
            </div>
        `;
        container.appendChild(div);
    });
    document.getElementById('cardSubtasksJson').value = JSON.stringify(currentSubtasks);
    initSubtaskSortable();
}

function updateSubtaskDate(index, val) {
    currentSubtasks[index].due_date = val;
    document.getElementById('cardSubtasksJson').value = JSON.stringify(currentSubtasks);
}

function updateSubtaskUser(index, val) {
    currentSubtasks[index].assigned_user_id = val;
    document.getElementById('cardSubtasksJson').value = JSON.stringify(currentSubtasks);
}

function convertSubtaskToCard(index) {
    const st = currentSubtasks[index];
    Swal.fire({
        title: '¿Convertir subtarea?',
        text: '¿Convertir esta subtarea en una tarjeta independiente?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, convertir',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const groupId = document.getElementById('cardGroupId').value;
            const formData = new FormData();
            formData.append('task_group_id', groupId);
            formData.append('title', st.title);
            formData.append('status', 'Nuevo');
            
            fetch('modules/projects/ajax_save_task_card.php', {
                method: 'POST',
                body: formData
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    currentSubtasks.splice(index, 1);
                    document.getElementById('cardSubtasksJson').value = JSON.stringify(currentSubtasks);
                    saveCard();
                } else {
                    Swal.fire('Error', data.message || 'Error al convertir la subtarea', 'error');
                }
            });
        }
    });
}

function addSubtask() {
    const input = document.getElementById('newSubtaskTitle');
    const title = input.value.trim();
    if(title) {
        currentSubtasks.push({ title: title, is_completed: false });
        input.value = '';
        renderSubtasks();
    }
}

function toggleSubtask(index, isCompleted) {
    currentSubtasks[index].is_completed = isCompleted;
    renderSubtasks();
}

function removeSubtask(index) {
    currentSubtasks.splice(index, 1);
    renderSubtasks();
}

function openCardModal(groupId) {
    document.getElementById('cardForm').reset();
    document.getElementById('cardEditId').value = '';
    document.getElementById('cardGroupId').value = groupId;
    document.querySelector('#cardModal h2').innerText = 'Nueva Tarjeta';
    document.getElementById('cardTagsJson').value = '[]';
    document.getElementById('tagsInputHelper').value = '';
    document.getElementById('cardLocked').checked = false;
    document.getElementById('cardApproved').checked = false;
    
    // Enable form fields if they were disabled
    document.querySelectorAll('#cardForm input, #cardForm select, #cardForm textarea, #cardForm button').forEach(el => el.disabled = false);
    
    document.getElementById('cardLogsContainer').innerHTML = '<div class="vt-placeholder-content" style="padding:1rem;">Aún no hay actividad.</div>';
    
    currentSubtasks = [];
    renderSubtasks();
    document.getElementById('cardImageLink').style.display = 'none';
    document.getElementById('cardEditableLink').style.display = 'none';
    document.getElementById('cardModal').style.display = 'flex';
}
function closeCardModal() {
    document.getElementById('cardModal').style.display = 'none';
}

function editCard(cardObj) {
    document.getElementById('cardForm').reset();
    document.getElementById('cardEditId').value = cardObj.id;
    document.getElementById('cardGroupId').value = cardObj.task_group_id;
    document.getElementById('cardTitle').value = cardObj.title;
    document.getElementById('cardStatus').value = cardObj.status;
    document.getElementById('cardPriority').value = cardObj.priority || 'Media';
    
    let tagsStr = cardObj.tags ? cardObj.tags : '[]';
    document.getElementById('cardTagsJson').value = tagsStr;
    try {
        let tagsArr = JSON.parse(tagsStr);
        document.getElementById('tagsInputHelper').value = tagsArr.join(', ');
    } catch(e) {}

    document.getElementById('cardStart').value = cardObj.start_date || '';
    document.getElementById('cardDue').value = cardObj.due_date || '';
    document.getElementById('cardDesc').value = cardObj.description || '';
    document.getElementById('cardDetails').value = cardObj.details || '';
    
    const isLocked = (cardObj.is_locked == 1 || cardObj.is_locked === true);
    const isApproved = (cardObj.is_approved == 1 || cardObj.is_approved === true);
    
    document.getElementById('cardLocked').checked = isLocked;
    document.getElementById('cardApproved').checked = isApproved;
    
    // If locked, disable fields
    document.querySelectorAll('#cardForm input:not(#cardLocked):not([name="is_locked"]), #cardForm select, #cardForm textarea, #cardForm button:not(.vt-modal-close)').forEach(el => {
        el.disabled = isLocked;
    });
    
    currentSubtasks = cardObj.subtasks ? JSON.parse(JSON.stringify(cardObj.subtasks)) : [];
    // Convert DB strings '1'/'0' to booleans just in case
    currentSubtasks.forEach(st => st.is_completed = (st.is_completed == 1 || st.is_completed === true));
    renderSubtasks();

    if(cardObj.reference_image) {
        document.getElementById('cardImageLink').style.display = 'block';
        document.getElementById('cardImageLink').innerHTML = `<a href="${cardObj.reference_image}" target="_blank" style="color:#22c55e;">Ver Referencia Actual</a>`;
    } else {
        document.getElementById('cardImageLink').style.display = 'none';
    }

    if(cardObj.editable_file) {
        document.getElementById('cardEditableLink').style.display = 'block';
        document.getElementById('cardEditableLink').innerHTML = `<a href="${cardObj.editable_file}" target="_blank" style="color:#2563eb;">Ver Editable Actual</a>`;
    } else {
        document.getElementById('cardEditableLink').style.display = 'none';
    }

    document.querySelector('#cardModal h2').innerText = 'Editar Tarjeta';
    document.getElementById('cardModal').style.display = 'flex';
    
    // Render Logs
    const logsContainer = document.getElementById('cardLogsContainer');
    if (cardObj.logs && cardObj.logs.length > 0) {
        logsContainer.innerHTML = '';
        cardObj.logs.forEach(log => {
            const div = document.createElement('div');
            div.style.padding = '8px';
            div.style.background = '#f8fafc';
            div.style.borderRadius = '6px';
            div.style.border = '1px solid #e2e8f0';
            const date = new Date(log.created_at).toLocaleString();
            div.innerHTML = `<strong>${log.user_name}</strong>: ${log.action} <br><small style="color:#94a3b8">${date}</small>`;
            logsContainer.appendChild(div);
        });
    } else {
        logsContainer.innerHTML = '<div class="vt-placeholder-content" style="padding:1rem;">Aún no hay actividad.</div>';
    }
}

function deleteCard(id) {
    Swal.fire({
        title: '¿Eliminar tarjeta?',
        text: '¿Enviar esta tarjeta a la papelera?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('modules/projects/ajax_delete_task_card.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else Swal.fire('Error', data.message, 'error');
            });
        }
    });
}

function restoreCard(id) {
    Swal.fire({
        title: '¿Restaurar tarjeta?',
        text: '¿Restaurar esta tarjeta de la papelera?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#22c55e',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, restaurar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('modules/projects/ajax_restore_task_card.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) location.reload();
                else Swal.fire('Error', data.message, 'error');
            });
        }
    });
}


function saveGroup() {
    const form = document.getElementById('groupForm');
    if (!form.reportValidity()) return;

    const btn = document.querySelector('#groupModal .vt-btn-primary');
    const ogText = btn.innerText;
    btn.innerText = 'Guardando...';
    btn.disabled = true;

    fetch('modules/projects/ajax_save_task_group.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error guardando grupo');
        }
    })
    .catch(err => alert('Error de conexión'))
    .finally(() => {
        btn.innerText = ogText;
        btn.disabled = false;
    });
}

function saveCard() {
    const form = document.getElementById('cardForm');
    if (!form.reportValidity()) return;

    const btn = document.querySelector('#cardModal .vt-btn-primary');
    const ogText = btn.innerText;
    btn.innerText = 'Subiendo...';
    btn.disabled = true;

    fetch('modules/projects/ajax_save_task_card.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const status = document.getElementById('cardStatus').value;
            if (status === 'Terminado') {
                confetti({
                    particleCount: 150,
                    spread: 70,
                    origin: { y: 0.6 }
                });
                setTimeout(() => location.reload(), 1500);
            } else {
                location.reload();
            }
        } else {
            alert(data.message || 'Error guardando tarjeta');
            btn.innerText = ogText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        alert('Error de conexión');
        btn.innerText = ogText;
        btn.disabled = false;
    });
}

// Drag and Drop Logic
document.addEventListener('DOMContentLoaded', function() {
    // Sortable Groups
    const groupsContainer = document.getElementById('groups-container');
    if (groupsContainer) {
        new Sortable(groupsContainer, {
            animation: 150,
            handle: '.drag-handle-group',
            ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                let items = [];
                groupsContainer.querySelectorAll('.vt-group-container').forEach((el, index) => {
                    items.push({ id: el.dataset.groupId, sort: index });
                });
                saveOrder('groups', items);
            }
        });
    }

    // Sortable Cards (Shared lists)
    const cardLists = document.querySelectorAll('.vt-cards-list');
    cardLists.forEach(list => {
        new Sortable(list, {
            group: 'shared-cards', // set both lists to same group
            animation: 150,
            handle: '.drag-handle-card',
            ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                let items = [];
                // Get all cards in the new list (in case it was moved across groups)
                const targetList = evt.to;
                const newGroupId = targetList.dataset.groupId;
                targetList.querySelectorAll('.vt-card').forEach((el, index) => {
                    items.push({ id: el.dataset.cardId, group_id: newGroupId, sort: index });
                });
                saveOrder('cards', items);
            }
        });
    });
});

function saveOrder(type, items) {
    const formData = new FormData();
    formData.append('type', type);
    formData.append('items', JSON.stringify(items));

    fetch('modules/projects/ajax_reorder_tasks.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            console.error('Error reordering', data.message);
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
