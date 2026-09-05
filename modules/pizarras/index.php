<?php
// modules/pizarras/index.php
require_once 'includes/header.php';

global $db;

// Pagination and Filtering
$search = $_GET['search'] ?? '';
$current_folder_id = isset($_GET['folder']) && $_GET['folder'] !== '' ? (int)$_GET['folder'] : null;

$stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmtRole->execute([$_SESSION['user_id']]);
$role_id = $stmtRole->fetchColumn();
$is_admin = ($role_id == 1);
$current_user_id = (int)$_SESSION['user_id'];

// Fetch users for assignment select
$stmtUsers = $db->query("SELECT id, name, avatar FROM users ORDER BY name ASC");
$all_users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Fetch folders with whiteboard count
$stmtFolders = $db->prepare("
    SELECT f.id, f.name, f.color, COUNT(w.id) as board_count 
    FROM whiteboard_folders f 
    LEFT JOIN whiteboards w ON f.id = w.folder_id 
    GROUP BY f.id, f.name, f.color 
    ORDER BY f.name ASC
");
$stmtFolders->execute();
$folders = $stmtFolders->fetchAll(PDO::FETCH_ASSOC);

// Find active folder details if in folder view
$active_folder = null;
if ($current_folder_id) {
    foreach ($folders as $f) {
        if ($f['id'] == $current_folder_id) {
            $active_folder = $f;
            break;
        }
    }
}

// Fetch whiteboards
$where = ["(w.created_by = ? OR EXISTS(SELECT 1 FROM whiteboard_users wu WHERE wu.whiteboard_id = w.id AND wu.user_id = ?) OR ?)"];
$params = [$current_user_id, $current_user_id, $is_admin ? 1 : 0];

if ($current_folder_id) {
    $where[] = "w.folder_id = ?";
    $params[] = $current_folder_id;
}

if ($search) {
    $where[] = "(w.title LIKE ? OR w.tags LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(" AND ", $where);

$sql = "SELECT w.id, w.title, w.created_by, w.created_at, w.updated_at, w.folder_id, 
               w.tags, w.thumbnail, w.profile_pic, w.access_type, w.public_role,
               u.name as creator_name, u.avatar as creator_avatar,
               f.name as folder_name, f.color as folder_color,
               (SELECT COUNT(*) FROM whiteboard_users wu WHERE wu.whiteboard_id = w.id) as user_count
        FROM whiteboards w 
        LEFT JOIN users u ON w.created_by = u.id 
        LEFT JOIN whiteboard_folders f ON w.folder_id = f.id
        WHERE $whereClause
        ORDER BY w.updated_at DESC 
        LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$whiteboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unique tags for Quick Tags feature
$all_tags = [];
try {
    $stmtTags = $db->prepare("SELECT tags FROM whiteboards WHERE tags IS NOT NULL AND tags != ''");
    $stmtTags->execute();
    while ($row = $stmtTags->fetch(PDO::FETCH_ASSOC)) {
        if (empty($row['tags']) || $row['tags'] === '[]') continue;
        $t = json_decode($row['tags'], true);
        if (is_array($t)) {
            foreach ($t as $tagItem) {
                if (isset($tagItem['name']) && isset($tagItem['color'])) {
                    $key = $tagItem['name'] . '|' . $tagItem['color'];
                    if (!isset($all_tags[$key])) {
                        $all_tags[$key] = $tagItem;
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    // Ignore error
}
$unique_tags = array_values($all_tags);

// Calculate summary stats
$totalWhiteboards = count($whiteboards);
$totalFolders = count($folders);
$collaborativeCount = 0;
$taggedCount = 0;
foreach ($whiteboards as $wb) {
    if (!empty($wb['user_count']) && $wb['user_count'] > 1) {
        $collaborativeCount++;
    }
    if (!empty($wb['tags']) && $wb['tags'] !== '[]') {
        $taggedCount++;
    }
}

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
/* ==========================================================================
   MODERN SAAS WHITEBOARDS MODULE - DESIGN SYSTEM (ui-guidelines compliant)
   ========================================================================== */

:root {
    --wb-radius-sm: 8px;
    --wb-radius-md: 12px;
    --wb-radius-lg: 16px;
    --wb-radius-xl: 20px;
    --wb-transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Full Width Container (No centered restriction) */
.wb-page-container {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 0 2.5rem 0;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    font-size: 13px;
    font-family: var(--font-family, 'Inter', sans-serif);
    color: var(--text-main);
    box-sizing: border-box;
}

/* --- 1. Hero / Header Banner --- */
.wb-saas-header {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--wb-radius-lg);
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
    width: 100%;
    box-sizing: border-box;
}

.wb-header-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.wb-header-avatar {
    width: 48px;
    height: 48px;
    border-radius: var(--wb-radius-md);
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    flex-shrink: 0;
    border: 1px solid color-mix(in srgb, var(--primary-color) 20%, transparent);
}

.wb-header-text {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.wb-header-title {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--text-main);
    margin: 0;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.wb-header-desc {
    font-size: 12px;
    color: var(--text-muted);
    margin: 0;
}

.wb-header-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.wb-btn-action-outline {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1rem;
    border-radius: var(--wb-radius-md);
    background: var(--bg-surface);
    color: var(--text-main);
    border: 1px solid var(--border-color);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--wb-transition);
}

.wb-btn-action-outline:hover {
    background: color-mix(in srgb, var(--text-muted) 8%, var(--bg-surface));
    border-color: var(--text-muted);
    transform: translateY(-1px);
}

.wb-btn-action-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.15rem;
    border-radius: var(--wb-radius-md);
    background: var(--primary-color);
    color: #ffffff;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color) 30%, transparent);
    transition: var(--wb-transition);
}

.wb-btn-action-primary:hover {
    background: var(--primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--primary-color) 40%, transparent);
}

/* Breadcrumb if inside folder */
.wb-breadcrumb-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: -0.25rem;
}

.wb-breadcrumb-link {
    color: var(--text-muted);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    transition: var(--wb-transition);
}

.wb-breadcrumb-link:hover {
    color: var(--primary-color);
}

.wb-breadcrumb-active {
    color: var(--text-main);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

/* --- 2. KPI Metrics Bar (Full Width Spanning) --- */
.wb-kpi-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.85rem;
    width: 100%;
}

.wb-kpi-item {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--wb-radius-md);
    padding: 0.85rem 1.15rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    transition: var(--wb-transition);
}

.wb-kpi-item:hover {
    border-color: color-mix(in srgb, var(--primary-color) 35%, var(--border-color));
    transform: translateY(-1px);
}

.wb-kpi-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--wb-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.wb-kpi-icon.blue { background: color-mix(in srgb, #3b82f6 15%, transparent); color: #3b82f6; }
.wb-kpi-icon.purple { background: color-mix(in srgb, #8b5cf6 15%, transparent); color: #8b5cf6; }
.wb-kpi-icon.teal { background: color-mix(in srgb, #10b981 15%, transparent); color: #10b981; }
.wb-kpi-icon.gold { background: color-mix(in srgb, #f59e0b 15%, transparent); color: #f59e0b; }

.wb-kpi-details {
    display: flex;
    flex-direction: column;
}

.wb-kpi-num {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-main);
    line-height: 1.2;
}

.wb-kpi-lbl {
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 500;
}

/* --- 3. Smart Toolbar: AJAX Search, Filter Pills & View Toggle --- */
.wb-toolbar {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--wb-radius-md);
    padding: 0.65rem 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    width: 100%;
    box-sizing: border-box;
}

.wb-toolbar-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    min-width: 280px;
    flex-wrap: wrap;
}

/* AJAX Search Box */
.wb-search-box {
    position: relative;
    flex: 1;
    min-width: 240px;
    max-width: 480px;
    display: flex;
    align-items: center;
}

.wb-search-icon {
    position: absolute;
    left: 0.75rem;
    color: var(--text-muted);
    font-size: 1rem;
    pointer-events: none;
}

.wb-search-box input {
    width: 100%;
    height: 38px;
    padding: 0 4rem 0 2.25rem;
    background: var(--bg-body, var(--bg-surface));
    border: 1px solid var(--border-color);
    border-radius: var(--wb-radius-sm);
    font-size: 13px;
    color: var(--text-main);
    transition: var(--wb-transition);
}

.wb-search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
    background: var(--bg-surface);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 15%, transparent);
}

.wb-search-box input::placeholder {
    color: var(--text-muted);
    font-size: 12px;
}

.wb-search-extras {
    position: absolute;
    right: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.wb-search-spinner {
    color: var(--primary-color);
    font-size: 1rem;
    animation: wb-spin 0.8s linear infinite;
}

.wb-search-clear {
    background: transparent;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: var(--radius-full);
    transition: var(--wb-transition);
}

.wb-search-clear:hover {
    background: var(--border-color);
    color: var(--text-main);
}

.wb-kbd {
    font-size: 10px;
    font-weight: 600;
    color: var(--text-muted);
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 2px 5px;
    pointer-events: none;
    user-select: none;
}

/* Filter Pills */
.wb-filter-pills {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    overflow-x: auto;
    scrollbar-width: none;
}

.wb-filter-pills::-webkit-scrollbar {
    display: none;
}

.wb-pill {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text-muted);
    padding: 0.35rem 0.75rem;
    border-radius: var(--radius-full);
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    transition: var(--wb-transition);
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.wb-pill:hover {
    color: var(--text-main);
    background: color-mix(in srgb, var(--text-muted) 10%, transparent);
}

.wb-pill.active {
    background: color-mix(in srgb, var(--primary-color) 15%, transparent);
    color: var(--primary-color);
    border-color: color-mix(in srgb, var(--primary-color) 25%, transparent);
    font-weight: 600;
}

/* Toolbar Right Controls */
.wb-toolbar-right {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.wb-count-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    white-space: nowrap;
}

.wb-view-switch {
    display: flex;
    background: var(--bg-body, #1e1e1e);
    border: 1px solid var(--border-color);
    border-radius: var(--wb-radius-sm);
    padding: 2px;
    gap: 2px;
}

.wb-view-switch button {
    border: none;
    background: transparent;
    color: var(--text-muted);
    padding: 0.35rem 0.55rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--wb-transition);
}

.wb-view-switch button:hover {
    color: var(--text-main);
}

.wb-view-switch button.active {
    background: var(--bg-surface);
    color: var(--primary-color);
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}

/* Quick Tags Bar */
.wb-tags-bar {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    padding: 0.1rem 0;
    width: 100%;
}

.wb-tag-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 3px 9px;
    border-radius: var(--radius-full);
    font-size: 11px;
    font-weight: 500;
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--text-main);
    cursor: pointer;
    transition: var(--wb-transition);
}

.wb-tag-chip:hover {
    transform: translateY(-1px);
    border-color: var(--primary-color);
}

.wb-tag-chip.active {
    border-color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 15%, var(--bg-surface));
    font-weight: 600;
}

.wb-tag-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* --- 4. Folders Grid Section --- */
.wb-folders-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100%;
}

.wb-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    margin: 0;
    width: 100%;
}

.wb-section-heading i {
    color: var(--primary-color);
    font-size: 1rem;
}

.wb-folders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 0.85rem;
    width: 100%;
}

.wb-folder-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--wb-radius-md);
    padding: 0.85rem 1.15rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    transition: var(--wb-transition);
    position: relative;
}

.wb-folder-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
}

.wb-folder-icon {
    width: 38px;
    height: 38px;
    border-radius: var(--wb-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}

.wb-folder-meta {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.wb-folder-name {
    font-weight: 600;
    font-size: 13px;
    color: var(--text-main);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.wb-folder-count {
    font-size: 11px;
    color: var(--text-muted);
}

.wb-folder-del-btn {
    opacity: 0;
    background: transparent;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 0.95rem;
    padding: 4px;
    border-radius: 4px;
    transition: var(--wb-transition);
}

.wb-folder-card:hover .wb-folder-del-btn {
    opacity: 1;
}

.wb-folder-del-btn:hover {
    color: var(--danger-color);
    background: color-mix(in srgb, var(--danger-color) 15%, transparent);
}

/* --- 5. Whiteboards Visual Grid (Cards - Responsive Full Width) --- */
.wb-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
    gap: 1.15rem;
    width: 100%;
}

.wb-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--wb-radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    cursor: pointer;
    transition: var(--wb-transition);
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}

.wb-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 45%, var(--border-color));
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

/* Visual Canvas Cover with Dot Grid Pattern */
.wb-card-cover {
    height: 135px;
    width: 100%;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--bg-body, #18181b);
    background-image: radial-gradient(color-mix(in srgb, var(--text-muted) 25%, transparent) 1px, transparent 1px);
    background-size: 14px 14px;
}

.wb-card-cover-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.wb-card-cover-placeholder {
    width: 46px;
    height: 46px;
    border-radius: var(--wb-radius-md);
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.45rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    color: var(--cover-icon-color, var(--primary-color));
    transition: var(--wb-transition);
}

.wb-card:hover .wb-card-cover-placeholder {
    transform: scale(1.1);
}

.wb-card-top-badges {
    position: absolute;
    top: 0.6rem;
    left: 0.6rem;
    right: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    pointer-events: none;
}

.wb-folder-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    color: #ffffff;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.wb-access-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    background: rgba(15, 23, 42, 0.75);
    color: #94a3b8;
    backdrop-filter: blur(4px);
}

.wb-access-badge.public {
    color: #34d399;
}

/* Card Body */
.wb-card-body {
    padding: 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    flex: 1;
}

.wb-card-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-main);
    margin: 0;
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.wb-card-tags {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    min-height: 22px;
}

.wb-card-tag {
    font-size: 10.5px;
    font-weight: 600;
    color: #ffffff;
    padding: 1px 7px;
    border-radius: 4px;
}

/* Card Footer */
.wb-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.65rem;
    border-top: 1px solid var(--border-color);
    margin-top: auto;
    font-size: 11px;
    color: var(--text-muted);
}

.wb-card-creator {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.wb-card-creator-avatar {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    object-fit: cover;
    background: var(--primary-color);
    color: white;
    font-size: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.wb-card-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.wb-card-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.9rem;
    transition: var(--wb-transition);
}

.wb-card-btn:hover {
    color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 10%, var(--bg-surface));
    border-color: var(--primary-color);
}

.wb-card-btn.danger:hover {
    color: var(--danger-color);
    background: color-mix(in srgb, var(--danger-color) 15%, var(--bg-surface));
    border-color: var(--danger-color);
}

/* --- 6. Whiteboards List View (Rows) --- */
.wb-list-view {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
}

.wb-row-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--wb-radius-md);
    padding: 0.75rem 1.15rem;
    display: flex;
    align-items: center;
    gap: 1.15rem;
    cursor: pointer;
    transition: var(--wb-transition);
}

.wb-row-card:hover {
    border-color: color-mix(in srgb, var(--primary-color) 40%, var(--border-color));
    background: color-mix(in srgb, var(--primary-color) 3%, var(--bg-surface));
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}

.wb-row-icon-col {
    width: 38px;
    height: 38px;
    border-radius: var(--wb-radius-sm);
    background: var(--bg-body, rgba(255,255,255,0.05));
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: var(--primary-color);
    flex-shrink: 0;
}

.wb-row-title-col {
    flex: 1.5;
    min-width: 180px;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.wb-row-title {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.wb-row-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 11px;
    color: var(--text-muted);
}

.wb-row-tags-col {
    flex: 1.2;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.wb-row-date-col {
    font-size: 11.5px;
    color: var(--text-muted);
    white-space: nowrap;
    min-width: 120px;
}

.wb-row-actions-col {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
}

/* --- 7. Empty State --- */
.wb-empty-state {
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3.5rem 1.5rem;
    text-align: center;
    background: var(--bg-surface);
    border: 1px dashed var(--border-color);
    border-radius: var(--wb-radius-lg);
    gap: 0.75rem;
    width: 100%;
    box-sizing: border-box;
}

.wb-empty-icon-wrap {
    width: 56px;
    height: 56px;
    border-radius: var(--radius-full);
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.wb-empty-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-main);
    margin: 0;
}

.wb-empty-desc {
    font-size: 12.5px;
    color: var(--text-muted);
    max-width: 380px;
    margin: 0;
}

/* --- 8. Modals Modernization --- */
.wb-modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(12px);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: var(--wb-transition);
}

.wb-modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

.wb-modal {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--wb-radius-xl);
    width: 90%;
    max-width: 500px;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-lg);
    transform: scale(0.95) translateY(10px);
    transition: var(--wb-transition);
    overflow: hidden;
}

.wb-modal-overlay.show .wb-modal {
    transform: scale(1) translateY(0);
}

.wb-modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.wb-modal-header h2 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.wb-modal-close {
    background: transparent;
    border: none;
    font-size: 1.25rem;
    color: var(--text-muted);
    cursor: pointer;
    border-radius: var(--radius-full);
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--wb-transition);
}

.wb-modal-close:hover {
    background: var(--border-color);
    color: var(--text-main);
}

.wb-modal-body {
    padding: 1.5rem;
}

.wb-modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background: var(--bg-surface);
}

.wb-btn-cancel {
    background: transparent;
    color: var(--text-main);
    border: 1px solid var(--border-color);
    padding: 0.5rem 1.2rem;
    border-radius: var(--wb-radius-md);
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: var(--wb-transition);
}

.wb-btn-cancel:hover {
    background: color-mix(in srgb, var(--text-muted) 10%, var(--bg-surface));
}

.wb-btn-save {
    background: var(--primary-color);
    color: #ffffff;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: var(--wb-radius-md);
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: var(--wb-transition);
}

.wb-btn-save:hover {
    background: var(--primary-hover);
}

.wb-btn-danger {
    background: var(--danger-color);
    color: #ffffff;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: var(--wb-radius-md);
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: var(--wb-transition);
}

.wb-btn-danger:hover {
    background: color-mix(in srgb, black 15%, var(--danger-color));
}

.wb-form-group {
    margin-bottom: 1.25rem;
}

.wb-form-group label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.35rem;
}

.wb-form-group input[type="text"],
.wb-form-group input[type="color"] {
    width: 100%;
    height: 38px;
    padding: 0 0.85rem;
    border: 1px solid var(--border-color);
    border-radius: var(--wb-radius-sm);
    background: var(--bg-body, var(--bg-surface));
    color: var(--text-main);
    font-size: 13px;
    outline: none;
    transition: var(--wb-transition);
}

.wb-form-group input[type="text"]:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 15%, transparent);
}

@keyframes wb-spin {
    to { transform: rotate(360deg); }
}

/* --- 9. Responsive Rules (Mobile & Tablet) --- */
@media (max-width: 1024px) {
    .wb-kpi-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .wb-saas-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .wb-header-actions {
        width: 100%;
    }

    .wb-btn-action-primary,
    .wb-btn-action-outline {
        flex: 1;
        justify-content: center;
    }

    .wb-kpi-row {
        display: flex;
        overflow-x: auto;
        padding-bottom: 0.35rem;
        scrollbar-width: none;
    }

    .wb-kpi-row::-webkit-scrollbar {
        display: none;
    }

    .wb-kpi-item {
        min-width: 160px;
        flex: 1 0 auto;
    }

    .wb-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }

    .wb-toolbar-left {
        width: 100%;
        min-width: 100%;
    }

    .wb-search-box {
        max-width: 100%;
        width: 100%;
    }

    .wb-toolbar-right {
        justify-content: space-between;
        width: 100%;
    }

    .wb-cards-grid {
        grid-template-columns: 1fr;
    }

    /* Row Card Mobile Layout */
    .wb-row-card {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }

    .wb-row-actions-col {
        justify-content: flex-end;
        border-top: 1px solid var(--border-color);
        padding-top: 0.5rem;
    }

    /* Modal as Bottom Sheet on Mobile */
    .wb-modal {
        width: 100% !important;
        max-width: 100% !important;
        border-radius: 20px 20px 0 0 !important;
        max-height: 90vh;
        margin: auto 0 0 0 !important;
    }
}
</style>

<div class="wb-page-container">
    <!-- Breadcrumb if inside folder -->
    <?php if ($active_folder): ?>
    <div class="wb-breadcrumb-bar">
        <a href="index.php?module=pizarras" class="wb-breadcrumb-link">
            <i class="ph ph-house"></i> Pizarras
        </a>
        <i class="ph ph-caret-right" style="font-size:10px;"></i>
        <span class="wb-breadcrumb-active">
            <i class="ph ph-folder-open" style="color: <?php echo htmlspecialchars($active_folder['color']); ?>;"></i>
            <?php echo htmlspecialchars($active_folder['name']); ?>
        </span>
    </div>
    <?php endif; ?>

    <!-- 1. Header Banner -->
    <div class="wb-saas-header">
        <div class="wb-header-info">
            <div class="wb-header-avatar">
                <i class="ph-duotone ph-chalkboard"></i>
            </div>
            <div class="wb-header-text">
                <h1 class="wb-header-title">
                    <?php if ($active_folder): ?>
                        <?php echo htmlspecialchars($active_folder['name']); ?>
                    <?php else: ?>
                        Pizarras Colaborativas
                    <?php endif; ?>
                </h1>
                <p class="wb-header-desc">
                    <?php if ($active_folder): ?>
                        Explora y colabora en las pizarras asignadas a esta carpeta.
                    <?php else: ?>
                        Hola, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?> 👋 ¿Listo para diseñar, planificar y colaborar?
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="wb-header-actions">
            <?php if (!$current_folder_id): ?>
            <button type="button" class="wb-btn-action-outline" onclick="openFolderModal()">
                <i class="ph ph-folder-plus"></i>
                <span>Nueva Carpeta</span>
            </button>
            <?php endif; ?>
            <button type="button" class="wb-btn-action-primary" onclick="openShareWhiteboardModal('create')">
                <i class="ph ph-plus-circle"></i>
                <span>Crear Pizarra</span>
            </button>
        </div>
    </div>

    <!-- 2. KPI Metrics Bar -->
    <div class="wb-kpi-row">
        <div class="wb-kpi-item">
            <div class="wb-kpi-icon blue">
                <i class="ph ph-chalkboard"></i>
            </div>
            <div class="wb-kpi-details">
                <span class="wb-kpi-num" id="kpiTotalBoards"><?php echo $totalWhiteboards; ?></span>
                <span class="wb-kpi-lbl">Total Pizarras</span>
            </div>
        </div>

        <div class="wb-kpi-item">
            <div class="wb-kpi-icon purple">
                <i class="ph ph-folders"></i>
            </div>
            <div class="wb-kpi-details">
                <span class="wb-kpi-num"><?php echo $totalFolders; ?></span>
                <span class="wb-kpi-lbl">Carpetas Activas</span>
            </div>
        </div>

        <div class="wb-kpi-item">
            <div class="wb-kpi-icon teal">
                <i class="ph ph-users-three"></i>
            </div>
            <div class="wb-kpi-details">
                <span class="wb-kpi-num"><?php echo $collaborativeCount; ?></span>
                <span class="wb-kpi-lbl">Colaborativas</span>
            </div>
        </div>

        <div class="wb-kpi-item">
            <div class="wb-kpi-icon gold">
                <i class="ph ph-tag"></i>
            </div>
            <div class="wb-kpi-details">
                <span class="wb-kpi-num"><?php echo $taggedCount; ?></span>
                <span class="wb-kpi-lbl">Con Etiquetas</span>
            </div>
        </div>
    </div>

    <!-- 3. Smart Toolbar: AJAX Search, Filters & View Toggle -->
    <div class="wb-toolbar">
        <div class="wb-toolbar-left">
            <!-- Search Box with AJAX & Debounce -->
            <div class="wb-search-box">
                <i class="ph ph-magnifying-glass wb-search-icon"></i>
                <input type="text" id="wbSearchInput" placeholder="Buscar pizarras por título, carpeta o etiqueta..." oninput="PizarrasModule.onSearchInput(this.value)" autocomplete="off">
                <div class="wb-search-extras">
                    <i class="ph ph-spinner wb-search-spinner" id="wbSearchSpinner" style="display: none;"></i>
                    <button type="button" class="wb-search-clear" id="wbSearchClearBtn" onclick="PizarrasModule.clearSearch()" style="display: none;" title="Limpiar búsqueda">
                        <i class="ph ph-x"></i>
                    </button>
                    <span class="wb-kbd"><kbd>⌘K</kbd></span>
                </div>
            </div>

            <!-- Filter Pills -->
            <div class="wb-filter-pills">
                <button type="button" class="wb-pill active" data-filter="all" onclick="PizarrasModule.setFilter('all', this)">
                    <i class="ph ph-squares-four"></i> Todas
                </button>
                <button type="button" class="wb-pill" data-filter="mine" onclick="PizarrasModule.setFilter('mine', this)">
                    <i class="ph ph-user"></i> Creadas por mí
                </button>
                <button type="button" class="wb-pill" data-filter="shared" onclick="PizarrasModule.setFilter('shared', this)">
                    <i class="ph ph-users-three"></i> Compartidas
                </button>
                <button type="button" class="wb-pill" data-filter="tagged" onclick="PizarrasModule.setFilter('tagged', this)">
                    <i class="ph ph-tag"></i> Con Etiquetas
                </button>
            </div>
        </div>

        <div class="wb-toolbar-right">
            <span class="wb-count-label" id="wbResultCount"><?php echo $totalWhiteboards; ?> pizarras</span>
            
            <div class="wb-view-switch">
                <button type="button" data-view="grid" class="active" onclick="PizarrasModule.setView('grid', this)" title="Vista Cuadrícula">
                    <i class="ph ph-squares-four"></i>
                </button>
                <button type="button" data-view="list" onclick="PizarrasModule.setView('list', this)" title="Vista Lista">
                    <i class="ph ph-list-dashes"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Tags Chips (if any exist) -->
    <?php if (!empty($unique_tags)): ?>
    <div class="wb-tags-bar" id="wbQuickTagsBar">
        <span style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Etiquetas:</span>
        <?php foreach (array_slice($unique_tags, 0, 10) as $t): ?>
        <button type="button" class="wb-tag-chip" data-tag="<?php echo htmlspecialchars($t['name']); ?>" onclick="PizarrasModule.toggleTag('<?php echo htmlspecialchars(addslashes($t['name'])); ?>', this)">
            <span class="wb-tag-dot" style="background: <?php echo htmlspecialchars($t['color']); ?>;"></span>
            <span><?php echo htmlspecialchars($t['name']); ?></span>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 4. Folders Area (only on root view) -->
    <?php if (!$current_folder_id): ?>
    <div class="wb-folders-section" id="wbFoldersArea">
        <div class="wb-section-heading">
            <span><i class="ph ph-folders"></i> Carpetas de Proyecto</span>
            <span style="font-weight: 500;"><?php echo count($folders); ?> disponibles</span>
        </div>

        <div class="wb-folders-grid" id="wbFoldersGrid">
            <?php foreach ($folders as $f): 
                $hex = $f['color'] ?: '#3b82f6';
            ?>
            <a href="index.php?module=pizarras&folder=<?php echo $f['id']; ?>" class="wb-folder-card">
                <div class="wb-folder-icon" style="background: color-mix(in srgb, <?php echo htmlspecialchars($hex); ?> 15%, transparent); color: <?php echo htmlspecialchars($hex); ?>;">
                    <i class="ph ph-folder-open"></i>
                </div>
                <div class="wb-folder-meta">
                    <span class="wb-folder-name" title="<?php echo htmlspecialchars($f['name']); ?>"><?php echo htmlspecialchars($f['name']); ?></span>
                    <span class="wb-folder-count"><?php echo $f['board_count']; ?> pizarra<?php echo $f['board_count'] != 1 ? 's' : ''; ?></span>
                </div>
                <?php if ($is_admin): ?>
                <button type="button" class="wb-folder-del-btn" onclick="event.preventDefault(); event.stopPropagation(); deleteFolder(<?php echo $f['id']; ?>);" title="Eliminar Carpeta">
                    <i class="ph ph-trash"></i>
                </button>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 5. Whiteboards Section (Grid View & List View) -->
    <div class="wb-section-heading" style="margin-top: 0.5rem;">
        <span><i class="ph ph-chalkboard"></i> Pizarras</span>
        <span id="wbHeaderBoardCount" style="font-weight: 500;"><?php echo count($whiteboards); ?> pizarras</span>
    </div>

    <!-- Grid View -->
    <div id="wbCardsGrid" class="wb-cards-grid">
        <?php if (empty($whiteboards)): ?>
        <div class="wb-empty-state">
            <div class="wb-empty-icon-wrap">
                <i class="ph ph-chalkboard"></i>
            </div>
            <h3 class="wb-empty-title">Tu espacio está listo</h3>
            <p class="wb-empty-desc">Crea tu primera pizarra colaborativa para bocetos, diagramas o notas con tu equipo.</p>
            <button type="button" class="wb-btn-action-primary" onclick="openShareWhiteboardModal('create')">
                <i class="ph ph-plus"></i> Crear Pizarra
            </button>
        </div>
        <?php else: ?>
            <?php foreach ($whiteboards as $w): 
                $tags = json_decode($w['tags'] ?? '[]', true);
                if (!is_array($tags)) $tags = [];
                $iconColor = '#3b82f6';
                if (!empty($tags) && isset($tags[0]['color'])) {
                    $iconColor = $tags[0]['color'];
                }
                $coverImg = $w['profile_pic'] ?: $w['thumbnail'];
                $isPublic = ($w['access_type'] === 'public');
            ?>
            <div class="wb-card" onclick="window.location.href='index.php?module=pizarras&action=view&id=<?php echo $w['id']; ?>'">
                <!-- Cover with Dot Grid or Image -->
                <div class="wb-card-cover">
                    <?php if ($coverImg): ?>
                    <img src="<?php echo htmlspecialchars($coverImg); ?>" class="wb-card-cover-img" alt="">
                    <?php else: ?>
                    <div class="wb-card-cover-placeholder" style="--cover-icon-color: <?php echo htmlspecialchars($iconColor); ?>;">
                        <i class="ph ph-chalkboard"></i>
                    </div>
                    <?php endif; ?>

                    <div class="wb-card-top-badges">
                        <?php if (!empty($w['folder_name'])): ?>
                        <span class="wb-folder-badge" style="border-color: <?php echo htmlspecialchars($w['folder_color'] ?: '#3b82f6'); ?>;">
                            <i class="ph ph-folder"></i> <?php echo htmlspecialchars($w['folder_name']); ?>
                        </span>
                        <?php else: ?>
                        <span></span>
                        <?php endif; ?>

                        <span class="wb-access-badge <?php echo $isPublic ? 'public' : ''; ?>">
                            <i class="ph <?php echo $isPublic ? 'ph-globe' : 'ph-lock'; ?>"></i>
                            <?php echo $isPublic ? 'Pública' : 'Privada'; ?>
                        </span>
                    </div>
                </div>

                <!-- Body -->
                <div class="wb-card-body">
                    <h3 class="wb-card-title" title="<?php echo htmlspecialchars($w['title']); ?>">
                        <?php echo htmlspecialchars($w['title']); ?>
                    </h3>

                    <div class="wb-card-tags">
                        <?php foreach (array_slice($tags, 0, 3) as $t): ?>
                        <span class="wb-card-tag" style="background: <?php echo htmlspecialchars($t['color']); ?>;">
                            <?php echo htmlspecialchars($t['name']); ?>
                        </span>
                        <?php endforeach; ?>
                        <?php if (count($tags) > 3): ?>
                        <span style="font-size:10px; color:var(--text-muted); font-weight:600;">+<?php echo count($tags) - 3; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="wb-card-footer" onclick="event.stopPropagation();">
                        <div class="wb-card-creator" title="Creado por <?php echo htmlspecialchars($w['creator_name'] ?? 'Usuario'); ?>">
                            <div class="wb-card-creator-avatar">
                                <?php echo htmlspecialchars(mb_strtoupper(mb_substr($w['creator_name'] ?? 'U', 0, 1))); ?>
                            </div>
                            <span><?php echo date('d M, Y', strtotime($w['updated_at'])); ?></span>
                        </div>

                        <div class="wb-card-actions">
                            <button type="button" class="wb-card-btn" onclick="openTagsModal(<?php echo $w['id']; ?>)" title="Etiquetas / Mover">
                                <i class="ph ph-tag"></i>
                            </button>
                            <?php if ($is_admin || $w['created_by'] == $current_user_id): ?>
                            <button type="button" class="wb-card-btn" onclick="openShareWhiteboardModal('edit', <?php echo $w['id']; ?>)" title="Compartir / Editar">
                                <i class="ph ph-pencil-simple"></i>
                            </button>
                            <button type="button" class="wb-card-btn danger" onclick="deleteWhiteboard(${w.id})" title="Eliminar">
                                <i class="ph ph-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- List View (Initially hidden) -->
    <div id="wbListView" class="wb-list-view" style="display: none;">
        <?php if (!empty($whiteboards)): ?>
            <?php foreach ($whiteboards as $w): 
                $tags = json_decode($w['tags'] ?? '[]', true);
                if (!is_array($tags)) $tags = [];
                $isPublic = ($w['access_type'] === 'public');
            ?>
            <div class="wb-row-card" onclick="window.location.href='index.php?module=pizarras&action=view&id=<?php echo $w['id']; ?>'">
                <div class="wb-row-icon-col">
                    <i class="ph ph-chalkboard"></i>
                </div>

                <div class="wb-row-title-col">
                    <div class="wb-row-title">
                        <span><?php echo htmlspecialchars($w['title']); ?></span>
                        <?php if ($isPublic): ?>
                        <span style="font-size:10px; color:#10b981; display:inline-flex; align-items:center; gap:2px;"><i class="ph ph-globe"></i> Pública</span>
                        <?php endif; ?>
                    </div>
                    <div class="wb-row-meta">
                        <?php if (!empty($w['folder_name'])): ?>
                        <span><i class="ph ph-folder" style="color:<?php echo htmlspecialchars($w['folder_color'] ?: '#3b82f6'); ?>;"></i> <?php echo htmlspecialchars($w['folder_name']); ?></span>
                        &bull;
                        <?php endif; ?>
                        <span>Por <?php echo htmlspecialchars($w['creator_name'] ?? 'Usuario'); ?></span>
                    </div>
                </div>

                <div class="wb-row-tags-col">
                    <?php foreach ($tags as $t): ?>
                    <span class="wb-card-tag" style="background: <?php echo htmlspecialchars($t['color']); ?>;">
                        <?php echo htmlspecialchars($t['name']); ?>
                    </span>
                    <?php endforeach; ?>
                </div>

                <div class="wb-row-date-col">
                    <i class="ph ph-calendar-blank"></i> <?php echo date('d M, Y', strtotime($w['updated_at'])); ?>
                </div>

                <div class="wb-row-actions-col" onclick="event.stopPropagation();">
                    <button type="button" class="wb-card-btn" onclick="openTagsModal(<?php echo $w['id']; ?>)" title="Etiquetas / Mover">
                        <i class="ph ph-tag"></i>
                    </button>
                    <?php if ($is_admin || $w['created_by'] == $current_user_id): ?>
                    <button type="button" class="wb-card-btn" onclick="openShareWhiteboardModal('edit', <?php echo $w['id']; ?>)" title="Compartir / Editar">
                        <i class="ph ph-pencil-simple"></i>
                    </button>
                    <button type="button" class="wb-card-btn danger" onclick="deleteWhiteboard(<?php echo $w['id']; ?>)" title="Eliminar">
                        <i class="ph ph-trash"></i>
                    </button>
                    <?php endif; ?>
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
        <div class="wb-modal-body" style="padding: 2rem 1.5rem;">
            <i class="ph ph-warning-circle" style="font-size: 3.5rem; color: #ef4444; margin-bottom: 0.75rem;"></i>
            <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">¿Eliminar Pizarra?</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Esta acción es irreversible. Se eliminarán permanentemente todos los elementos de la pizarra.</p>
            
            <div style="display: flex; gap: 0.75rem; justify-content: center;">
                <button type="button" class="wb-btn-cancel" onclick="closeDeleteModal()" style="flex: 1;">Cancelar</button>
                <button type="button" class="wb-btn-danger" id="btn-confirm-delete" style="flex: 1;">Sí, eliminar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Eliminar Carpeta -->
<div class="wb-modal-overlay" id="deleteFolderModal">
    <div class="wb-modal" style="max-width: 400px; text-align: center;">
        <div class="wb-modal-body" style="padding: 2rem 1.5rem;">
            <i class="ph ph-warning-circle" style="font-size: 3.5rem; color: #ef4444; margin-bottom: 0.75rem;"></i>
            <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">¿Eliminar Carpeta?</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Las pizarras dentro no se borrarán; volverán al espacio principal sin carpeta.</p>
            
            <div style="display: flex; gap: 0.75rem; justify-content: center;">
                <button type="button" class="wb-btn-cancel" onclick="closeDeleteFolderModal()" style="flex: 1;">Cancelar</button>
                <button type="button" class="wb-btn-danger" id="btn-confirm-delete-folder" style="flex: 1;">Sí, eliminar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Carpeta -->
<div class="wb-modal-overlay" id="newFolderModal">
    <div class="wb-modal">
        <div class="wb-modal-header">
            <h2><i class="ph ph-folder-plus"></i> Nueva Carpeta</h2>
            <button type="button" class="wb-modal-close" onclick="closeFolderModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="wb-modal-body">
            <div class="wb-form-group">
                <label for="folder-title-input">Nombre de la carpeta *</label>
                <input type="text" id="folder-title-input" placeholder="Ej. Campañas Q3 2026" required>
            </div>
            
            <div class="wb-form-group" style="margin-bottom: 0;">
                <label for="folder-color-input">Color Distintivo</label>
                <input type="color" id="folder-color-input" value="#3b82f6" style="height:40px; padding:2px; cursor:pointer;">
            </div>
        </div>
        <div class="wb-modal-footer">
            <button type="button" class="wb-btn-cancel" onclick="closeFolderModal()">Cancelar</button>
            <button type="button" class="wb-btn-save" onclick="submitNewFolder()">Crear Carpeta</button>
        </div>
    </div>
</div>

<!-- Modal Etiquetas y Mover -->
<div class="wb-modal-overlay" id="tagsModal">
    <div class="wb-modal">
        <div class="wb-modal-header">
            <h2><i class="ph ph-tag"></i> Organizar Pizarra</h2>
            <button type="button" class="wb-modal-close" onclick="closeTagsModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="wb-modal-body">
            <div class="wb-form-group">
                <label for="move-folder-select">Mover a Carpeta</label>
                <select id="move-folder-select" class="ts-control">
                    <option value="">(Sin carpeta principal)</option>
                    <?php foreach ($folders as $f): ?>
                        <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="wb-form-group">
                <label>Añadir Etiqueta</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="tag-name-input" placeholder="Ej. Urgente" style="flex: 1;">
                    <input type="color" id="tag-color-input" value="#ef4444" style="width: 40px; height: 38px; padding: 2px; cursor:pointer;">
                    <button type="button" class="wb-btn-save" onclick="addTag()" style="padding:0 14px; border-radius:var(--wb-radius-sm); display:flex; align-items:center;">
                        <i class="ph ph-plus"></i>
                    </button>
                </div>
            </div>
            
            <?php if (!empty($unique_tags)): ?>
            <div class="wb-form-group">
                <label>Etiquetas Sugeridas</label>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                    <?php foreach (array_slice($unique_tags, 0, 12) as $qt): ?>
                        <span class="wb-card-tag" style="background: <?php echo htmlspecialchars($qt['color']); ?>; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px;" onclick="document.getElementById('tag-name-input').value='<?php echo htmlspecialchars(addslashes($qt['name'])); ?>'; document.getElementById('tag-color-input').value='<?php echo htmlspecialchars($qt['color']); ?>'; addTag();">
                            <?php echo htmlspecialchars($qt['name']); ?> <i class="ph ph-plus" style="opacity:0.7;"></i>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div id="current-tags-container" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; border-top: 1px dashed var(--border-color); padding-top: 10px;">
                <!-- Tags here -->
            </div>
        </div>
        <div class="wb-modal-footer">
            <button type="button" class="wb-btn-cancel" onclick="closeTagsModal()">Cerrar</button>
            <button type="button" class="wb-btn-save" onclick="saveBoardOrganization()">Guardar Cambios</button>
        </div>
    </div>
</div>

<!-- Tom Select JS -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script>
// Pizarras Module JavaScript Engine
const PizarrasModule = (function() {
    let currentFilter = 'all';
    let currentTag = '';
    let currentSort = 'recent';
    let currentView = localStorage.getItem('roma_wb_view') || 'grid';
    let currentFolderId = <?php echo $current_folder_id ? $current_folder_id : 'null'; ?>;
    let currentUserId = <?php echo $current_user_id; ?>;
    let isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    let searchDebounceTimer = null;

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    function init() {
        setView(currentView, document.querySelector(`.wb-view-switch button[data-view="${currentView}"]`));

        // Keyboard Shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                const searchInput = document.getElementById('wbSearchInput');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            } else if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                const searchInput = document.getElementById('wbSearchInput');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            } else if (e.key === 'Escape') {
                document.querySelectorAll('.wb-modal-overlay.show').forEach(m => m.classList.remove('show'));
            }
        });
    }

    function onSearchInput(val) {
        const clearBtn = document.getElementById('wbSearchClearBtn');
        if (clearBtn) {
            clearBtn.style.display = val.trim().length > 0 ? 'flex' : 'none';
        }

        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            performAjaxSearch();
        }, 250);
    }

    function clearSearch() {
        const searchInput = document.getElementById('wbSearchInput');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
        const clearBtn = document.getElementById('wbSearchClearBtn');
        if (clearBtn) clearBtn.style.display = 'none';
        performAjaxSearch();
    }

    function setFilter(filter, el) {
        currentFilter = filter;
        document.querySelectorAll('.wb-pill').forEach(p => p.classList.remove('active'));
        if (el) el.classList.add('active');
        performAjaxSearch();
    }

    function toggleTag(tagName, el) {
        if (currentTag === tagName) {
            currentTag = '';
            if (el) el.classList.remove('active');
        } else {
            currentTag = tagName;
            document.querySelectorAll('.wb-tag-chip').forEach(c => c.classList.remove('active'));
            if (el) el.classList.add('active');
        }
        performAjaxSearch();
    }

    function setView(view, btn) {
        currentView = view || 'grid';
        localStorage.setItem('roma_wb_view', currentView);

        document.querySelectorAll('.wb-view-switch button').forEach(b => b.classList.remove('active'));
        if (btn) {
            btn.classList.add('active');
        } else {
            const activeBtn = document.querySelector(`.wb-view-switch button[data-view="${currentView}"]`);
            if (activeBtn) activeBtn.classList.add('active');
        }

        const gridView = document.getElementById('wbCardsGrid');
        const listView = document.getElementById('wbListView');

        if (gridView && listView) {
            if (currentView === 'grid') {
                gridView.style.display = 'grid';
                listView.style.display = 'none';
            } else {
                gridView.style.display = 'none';
                listView.style.display = 'flex';
            }
        }
    }

    async function performAjaxSearch() {
        const searchInput = document.getElementById('wbSearchInput');
        const query = searchInput ? searchInput.value.trim() : '';
        const spinner = document.getElementById('wbSearchSpinner');

        if (spinner) spinner.style.display = 'flex';

        try {
            const folderParam = currentFolderId ? `&folder_id=${currentFolderId}` : '';
            const tagParam = currentTag ? `&tag=${encodeURIComponent(currentTag)}` : '';
            const url = `index.php?module=pizarras&action=ajax_search_pizarras&q=${encodeURIComponent(query)}&filter=${encodeURIComponent(currentFilter)}${folderParam}${tagParam}&sort=${encodeURIComponent(currentSort)}`;

            const res = await fetch(url);
            const data = await res.json();

            if (data.success) {
                renderWhiteboards(data.whiteboards, query);
                if (!currentFolderId) {
                    renderFolders(data.folders, query);
                }
                updateCounters(data.total_boards, query);
            }
        } catch (err) {
            console.error('Error in whiteboards search:', err);
        } finally {
            if (spinner) spinner.style.display = 'none';
        }
    }

    function updateCounters(total, query) {
        const badge = document.getElementById('wbResultCount');
        const headerBadge = document.getElementById('wbHeaderBoardCount');
        const kpi = document.getElementById('kpiTotalBoards');

        const text = query || currentFilter !== 'all' || currentTag ? `${total} resultado${total === 1 ? '' : 's'}` : `${total} pizarras`;
        if (badge) badge.textContent = text;
        if (headerBadge) headerBadge.textContent = text;
        if (kpi && !query && currentFilter === 'all' && !currentTag) kpi.textContent = total;
    }

    function renderFolders(folders, query) {
        const foldersArea = document.getElementById('wbFoldersArea');
        const grid = document.getElementById('wbFoldersGrid');
        if (!foldersArea || !grid) return;

        if (currentFilter !== 'all' && currentFilter !== 'folders') {
            foldersArea.style.display = 'none';
            return;
        }

        if (!folders || folders.length === 0) {
            foldersArea.style.display = 'none';
            return;
        }

        foldersArea.style.display = 'flex';
        let html = '';
        folders.forEach(f => {
            const hex = f.color || '#3b82f6';
            html += `
                <a href="index.php?module=pizarras&folder=${f.id}" class="wb-folder-card">
                    <div class="wb-folder-icon" style="background: color-mix(in srgb, ${escapeHtml(hex)} 15%, transparent); color: ${escapeHtml(hex)};">
                        <i class="ph ph-folder-open"></i>
                    </div>
                    <div class="wb-folder-meta">
                        <span class="wb-folder-name" title="${escapeHtml(f.name)}">${escapeHtml(f.name)}</span>
                        <span class="wb-folder-count">${f.board_count} pizarra${f.board_count != 1 ? 's' : ''}</span>
                    </div>
                    ${isAdmin ? `
                        <button type="button" class="wb-folder-del-btn" onclick="event.preventDefault(); event.stopPropagation(); deleteFolder(${f.id});" title="Eliminar Carpeta">
                            <i class="ph ph-trash"></i>
                        </button>
                    ` : ''}
                </a>
            `;
        });
        grid.innerHTML = html;
    }

    function renderWhiteboards(whiteboards, query) {
        const gridView = document.getElementById('wbCardsGrid');
        const listView = document.getElementById('wbListView');
        if (!gridView || !listView) return;

        if (!whiteboards || whiteboards.length === 0) {
            const emptyHtml = `
                <div class="wb-empty-state">
                    <div class="wb-empty-icon-wrap">
                        <i class="ph ph-chalkboard"></i>
                    </div>
                    <h3 class="wb-empty-title">No se encontraron pizarras</h3>
                    <p class="wb-empty-desc">${query ? `No hay resultados para "${escapeHtml(query)}".` : 'No hay pizarras que coincidan con los filtros aplicados.'}</p>
                    <button type="button" class="wb-btn-action-outline" onclick="PizarrasModule.resetFilters()">
                        <i class="ph ph-arrow-counter-clockwise"></i> Restablecer filtros
                    </button>
                </div>
            `;
            gridView.innerHTML = emptyHtml;
            listView.innerHTML = emptyHtml;
            return;
        }

        // Render Grid Cards
        let gridHtml = '';
        whiteboards.forEach(w => {
            let tags = [];
            try { tags = typeof w.tags === 'string' ? JSON.parse(w.tags) : (w.tags || []); } catch(e) { tags = []; }
            if (!Array.isArray(tags)) tags = [];

            let iconColor = '#3b82f6';
            if (tags.length > 0 && tags[0].color) {
                iconColor = tags[0].color;
            }

            const coverImg = w.profile_pic || w.thumbnail;
            const isPublic = (w.access_type === 'public');
            const canEdit = isAdmin || w.created_by == currentUserId;
            const creatorInitial = (w.creator_name || 'U').substring(0, 1).toUpperCase();

            let tagsHtml = '';
            tags.slice(0, 3).forEach(t => {
                tagsHtml += `<span class="wb-card-tag" style="background: ${escapeHtml(t.color)};">${escapeHtml(t.name)}</span>`;
            });
            if (tags.length > 3) {
                tagsHtml += `<span style="font-size:10px; color:var(--text-muted); font-weight:600;">+${tags.length - 3}</span>`;
            }

            const dateStr = w.updated_at ? new Date(w.updated_at).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' }) : '';

            gridHtml += `
                <div class="wb-card" onclick="window.location.href='index.php?module=pizarras&action=view&id=${w.id}'">
                    <div class="wb-card-cover">
                        ${coverImg ? `
                            <img src="${escapeHtml(coverImg)}" class="wb-card-cover-img" alt="">
                        ` : `
                            <div class="wb-card-cover-placeholder" style="--cover-icon-color: ${escapeHtml(iconColor)};">
                                <i class="ph ph-chalkboard"></i>
                            </div>
                        `}
                        <div class="wb-card-top-badges">
                            ${w.folder_name ? `
                                <span class="wb-folder-badge" style="border-color: ${escapeHtml(w.folder_color || '#3b82f6')};">
                                    <i class="ph ph-folder"></i> ${escapeHtml(w.folder_name)}
                                </span>
                            ` : `<span></span>`}

                            <span class="wb-access-badge ${isPublic ? 'public' : ''}">
                                <i class="ph ${isPublic ? 'ph-globe' : 'ph-lock'}"></i>
                                ${isPublic ? 'Pública' : 'Privada'}
                            </span>
                        </div>
                    </div>

                    <div class="wb-card-body">
                        <h3 class="wb-card-title" title="${escapeHtml(w.title)}">${escapeHtml(w.title)}</h3>
                        <div class="wb-card-tags">${tagsHtml}</div>
                        <div class="wb-card-footer" onclick="event.stopPropagation();">
                            <div class="wb-card-creator" title="Creado por ${escapeHtml(w.creator_name || 'Usuario')}">
                                <div class="wb-card-creator-avatar">${escapeHtml(creatorInitial)}</div>
                                <span>${dateStr}</span>
                            </div>
                            <div class="wb-card-actions">
                                <button type="button" class="wb-card-btn" onclick="openTagsModal(${w.id})" title="Etiquetas / Mover">
                                    <i class="ph ph-tag"></i>
                                </button>
                                ${canEdit ? `
                                    <button type="button" class="wb-card-btn" onclick="openShareWhiteboardModal('edit', ${w.id})" title="Compartir / Editar">
                                        <i class="ph ph-pencil-simple"></i>
                                    </button>
                                    <button type="button" class="wb-card-btn danger" onclick="deleteWhiteboard(${w.id})" title="Eliminar">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        gridView.innerHTML = gridHtml;

        // Render List Rows
        let listHtml = '';
        whiteboards.forEach(w => {
            let tags = [];
            try { tags = typeof w.tags === 'string' ? JSON.parse(w.tags) : (w.tags || []); } catch(e) { tags = []; }
            if (!Array.isArray(tags)) tags = [];

            const isPublic = (w.access_type === 'public');
            const canEdit = isAdmin || w.created_by == currentUserId;
            const dateStr = w.updated_at ? new Date(w.updated_at).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' }) : '';

            let tagsHtml = '';
            tags.forEach(t => {
                tagsHtml += `<span class="wb-card-tag" style="background: ${escapeHtml(t.color)};">${escapeHtml(t.name)}</span>`;
            });

            listHtml += `
                <div class="wb-row-card" onclick="window.location.href='index.php?module=pizarras&action=view&id=${w.id}'">
                    <div class="wb-row-icon-col">
                        <i class="ph ph-chalkboard"></i>
                    </div>

                    <div class="wb-row-title-col">
                        <div class="wb-row-title">
                            <span>${escapeHtml(w.title)}</span>
                            ${isPublic ? `<span style="font-size:10px; color:#10b981; display:inline-flex; align-items:center; gap:2px;"><i class="ph ph-globe"></i> Pública</span>` : ''}
                        </div>
                        <div class="wb-row-meta">
                            ${w.folder_name ? `<span><i class="ph ph-folder" style="color:${escapeHtml(w.folder_color || '#3b82f6')};"></i> ${escapeHtml(w.folder_name)}</span> &bull; ` : ''}
                            <span>Por ${escapeHtml(w.creator_name || 'Usuario')}</span>
                        </div>
                    </div>

                    <div class="wb-row-tags-col">${tagsHtml}</div>

                    <div class="wb-row-date-col">
                        <i class="ph ph-calendar-blank"></i> ${dateStr}
                    </div>

                    <div class="wb-row-actions-col" onclick="event.stopPropagation();">
                        <button type="button" class="wb-card-btn" onclick="openTagsModal(${w.id})" title="Etiquetas / Mover">
                            <i class="ph ph-tag"></i>
                        </button>
                        ${canEdit ? `
                            <button type="button" class="wb-card-btn" onclick="openShareWhiteboardModal('edit', ${w.id})" title="Compartir / Editar">
                                <i class="ph ph-pencil-simple"></i>
                            </button>
                            <button type="button" class="wb-card-btn danger" onclick="deleteWhiteboard(${w.id})" title="Eliminar">
                                <i class="ph ph-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        listView.innerHTML = listHtml;
    }

    function resetFilters() {
        const searchInput = document.getElementById('wbSearchInput');
        if (searchInput) searchInput.value = '';
        const clearBtn = document.getElementById('wbSearchClearBtn');
        if (clearBtn) clearBtn.style.display = 'none';

        currentFilter = 'all';
        currentTag = '';
        document.querySelectorAll('.wb-pill').forEach(p => p.classList.toggle('active', p.dataset.filter === 'all'));
        document.querySelectorAll('.wb-tag-chip').forEach(c => c.classList.remove('active'));

        performAjaxSearch();
    }

    return {
        init: init,
        onSearchInput: onSearchInput,
        clearSearch: clearSearch,
        setFilter: setFilter,
        toggleTag: toggleTag,
        setView: setView,
        resetFilters: resetFilters,
        refresh: performAjaxSearch
    };
})();

document.addEventListener('DOMContentLoaded', PizarrasModule.init);

// Whiteboard CRUD helpers
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
            closeDeleteModal();
            PizarrasModule.refresh();
        } else {
            closeDeleteModal();
            alert('Error: ' + res.error);
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
    setTimeout(() => {
        const inp = document.getElementById('folder-title-input');
        if (inp) inp.focus();
    }, 100);
}

function closeFolderModal() {
    document.getElementById('newFolderModal').classList.remove('show');
    document.getElementById('folder-title-input').value = '';
}

function submitNewFolder() {
    const name = document.getElementById('folder-title-input').value.trim();
    const color = document.getElementById('folder-color-input').value;
    if (!name) { 
        alert('Ingresa un nombre para la carpeta.'); 
        return; 
    }
    
    fetch('ajax/ajax_whiteboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create_folder', name, color })
    }).then(r => r.json()).then(res => {
        if (res.success) {
            closeFolderModal();
            window.location.reload();
        } else {
            alert('Error al crear carpeta: ' + res.error);
        }
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
    }).then(r => r.json()).then(res => {
        if (res.success) {
            closeDeleteFolderModal();
            window.location.reload();
        } else {
            closeDeleteFolderModal();
            alert('Error: ' + res.error);
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
    
    const moveSelect = document.getElementById('move-folder-select');
    if (board && moveSelect) {
        moveSelect.value = board.folder_id || '';
    }
    
    let tagsStr = board ? board.tags : '';
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
        span.className = 'wb-card-tag';
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
    if (!name) return;
    currentTagsArray.push({name, color});
    document.getElementById('tag-name-input').value = '';
    renderCurrentTags();
}

function saveBoardOrganization() {
    const folder_id = document.getElementById('move-folder-select').value;
    
    fetch('ajax/ajax_whiteboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'move_to_folder', id: currentBoardIdForTags, folder_id })
    })
    .then(r => r.json())
    .then(res => {
        return fetch('ajax/ajax_whiteboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_tags', id: currentBoardIdForTags, tags: currentTagsArray })
        });
    })
    .then(r => r.json())
    .then(res => {
        closeTagsModal();
        PizarrasModule.refresh();
    })
    .catch(err => {
        console.error(err);
        alert('Hubo un error al organizar la pizarra.');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
