<?php
// modules/month_board/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

$stmtUserRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmtUserRole->execute([$_SESSION['user_id']]);
$userRoleId = (int)$stmtUserRole->fetchColumn();
$isAdmin = ($userRoleId === 1);

$monthId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$monthId) {
    echo "ID de mes no proporcionado.";
    exit();
}

// Fetch month data, project data
$stmt = $db->prepare("
    SELECT pm.*, w.brand_name, w.correlativo, w.data 
    FROM project_months pm
    JOIN projects p ON pm.project_id = p.id
    JOIN work_orders w ON p.work_order_id = w.id
    WHERE pm.id = ?
");
$stmt->execute([$monthId]);
$monthData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$monthData) {
    echo "Mes no encontrado.";
    exit();
}

$stmtBrand = $db->prepare("SELECT logo FROM client_brands WHERE name = ?");
$stmtBrand->execute([$monthData['brand_name']]);
$brand = $stmtBrand->fetch(PDO::FETCH_ASSOC);
$logo = $brand && !empty($brand['logo']) ? $brand['logo'] : 'assets/img/default-logo.png';

// Fetch posts
$stmtPosts = $db->prepare("SELECT * FROM month_posts WHERE month_id = ? ORDER BY CASE WHEN post_date IS NULL OR post_date = '0000-00-00' OR post_date = '0000-00-00 00:00:00' THEN 1 ELSE 0 END ASC, post_date ASC, id ASC");
$stmtPosts->execute([$monthId]);
$posts = $stmtPosts->fetchAll(PDO::FETCH_ASSOC);

foreach ($posts as &$p) {
    $stmtC = $db->prepare("SELECT * FROM post_comments WHERE post_id = ? ORDER BY created_at DESC");
    $stmtC->execute([$p['id']]);
    $p['comments'] = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    $stmtR = $db->prepare("SELECT * FROM post_revisions WHERE post_id = ? ORDER BY id DESC");
    $stmtR->execute([$p['id']]);
    $p['revisions'] = $stmtR->fetchAll(PDO::FETCH_ASSOC);
}
unset($p); // CRITICAL: break the reference to prevent overwriting the last element in subsequent loops

// Calculate overall month progress
$totalPostsCount = count($posts);
$completedPostsCount = 0;
foreach ($posts as $pst) {
    if (in_array($pst['status'], ['Publicado', 'Aprobado'])) {
        $completedPostsCount++;
    }
}
$monthProgressPct = $totalPostsCount > 0 ? round(($completedPostsCount / $totalPostsCount) * 100) : 0;
$isMonthComplete = ($totalPostsCount > 0 && $monthProgressPct >= 100) || strtolower($monthData['status'] ?? '') === 'finalizado';

$monthNames = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Handle statuses and their colors
$statusColors = [
    'Borrador' => ['bg' => 'rgba(100, 116, 139, 0.1)', 'color' => '#64748b'],
    'En Revisión' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'color' => '#d97706'],
    'Aprobado' => ['bg' => 'rgba(59, 130, 246, 0.1)', 'color' => '#2563eb'],
    'Publicado' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'color' => '#059669'],
    'Archivado' => ['bg' => 'rgba(147, 51, 234, 0.1)', 'color' => '#9333ea'],
];

// Content Pillars Definitions & Palette
$pillarDefinitions = [
    'Educación' => ['label' => 'Educación', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.15)', 'icon' => 'ph-graduation-cap'],
    'Ventas' => ['label' => 'Ventas', 'color' => '#f43f5e', 'bg' => 'rgba(244,63,94,0.15)', 'icon' => 'ph-tag'],
    'Branding' => ['label' => 'Branding', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.15)', 'icon' => 'ph-sparkle'],
    'Entretenimiento' => ['label' => 'Entretenimiento', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.15)', 'icon' => 'ph-mask-happy'],
    'Comunidad' => ['label' => 'Comunidad', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.15)', 'icon' => 'ph-users-three'],
    'Testimonial' => ['label' => 'Testimonial', 'color' => '#06b6d4', 'bg' => 'rgba(6,182,212,0.15)', 'icon' => 'ph-star']
];

$pillarCounts = [];
foreach ($pillarDefinitions as $pKey => $pDef) {
    $pillarCounts[$pKey] = 0;
}
foreach ($posts as $pst) {
    $pPil = $pst['content_pillar'] ?? 'Educación';
    if (!isset($pillarCounts[$pPil])) {
        $pillarCounts[$pPil] = 0;
    }
    $pillarCounts[$pPil]++;
}

require_once 'includes/header.php';
?>
<!-- Swiper JS & CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<!-- FullCalendar Scripts -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js'></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

<style>
    /* ===== HEADER REDESIGN ===== */
    .board-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        padding: 1.1rem 1.6rem;
        border-radius: 20px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
        position: relative;
    }
    [data-theme="dark"] .board-header {
        background: #121212;
        border: 1px solid #27272a;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.7);
    }

    .board-header-left {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-shrink: 0;
    }
    .btn-back-compact {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        color: var(--text-color);
        text-decoration: none;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        flex-shrink: 0;
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }
    .btn-back-compact:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        transform: translateX(-3px);
        box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
    }
    [data-theme="dark"] .btn-back-compact {
        background: #181818;
        border-color: #27272a;
        color: #e4e4e7;
    }

    .board-header-info {
        display: flex;
        align-items: center;
        gap: 0.85rem;
    }
    .board-header-info img {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        object-fit: contain;
        background: #ffffff;
        padding: 0.35rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        border: 1px solid var(--border-color);
    }
    [data-theme="dark"] .board-header-info img {
        background: #181818;
        border-color: #27272a;
    }

    .board-title {
        font-size: 1.55rem;
        font-weight: 800;
        color: var(--color-title);
        margin: 0;
        letter-spacing: -0.5px;
        line-height: 1.2;
    }
    .board-meta-row {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        margin-top: 0.25rem;
        flex-wrap: wrap;
    }
    .board-brand-name {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    .board-ot-badge {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0.12rem 0.45rem;
        border-radius: 6px;
    }
    .phase-badge {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        text-transform: uppercase;
    }
    .phase-container {
        margin: 0.25rem 0 0 0; 
        font-size: 0.85rem; 
        color: var(--text-muted); 
        display: flex; 
        align-items: center; 
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-surface);
        padding: 1rem;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        margin-bottom: 2rem;
    }

    .posts-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 1.25rem;
    }
    @media (max-width: 1400px) {
        .posts-grid { grid-template-columns: repeat(4, 1fr); }
    }
    @media (max-width: 1100px) {
        .posts-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 768px) {
        .posts-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
    }
    @media (max-width: 480px) {
        .posts-grid { grid-template-columns: 1fr; }
    }

    /* ===== POST CARD — Modern Redesign ===== */
    .post-card {
        border-radius: 20px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s ease;
        position: relative;
        cursor: pointer;
        padding: 12px;
        gap: 12px;
    }
    .post-card:hover {
        transform: translateY(-6px) scale(1.01);
        box-shadow: 0 20px 50px rgba(0,0,0,0.15);
    }
    [data-theme="dark"] .post-card:hover {
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    }
    
    .post-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 4px;
    }
    
    .post-order-badge {
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: -0.3px;
    }
    
    .post-card-actions {
        display: flex;
        gap: 6px;
    }
    
    .post-card-actions .btn-action {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #ffffff;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        font-size: 0.9rem;
        transition: transform 0.2s;
    }
    .post-card-actions .btn-action:hover {
        transform: scale(1.1);
    }
    [data-theme="dark"] .post-card-actions .btn-action {
        background: var(--bg-surface);
    }

    /* Image section */
    .post-image {
        height: 200px;
        border-radius: 16px;
        background: linear-gradient(135deg, #e8edf5 0%, #d0d9e8 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        position: relative;
        overflow: hidden;
        flex-shrink: 0;
    }
    [data-theme="dark"] .post-image {
        background: linear-gradient(135deg, #1a1f2e 0%, #0f1320 100%);
    }
    .post-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        transition: transform 0.5s cubic-bezier(0.4,0,0.2,1);
    }
    .post-card:hover .post-image img { transform: scale(1.06); }
    
    /* Reference Badge at the bottom of image */
    .post-image .ref-badge {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 0.7rem;
        font-weight: 800;
        padding: 6px 0;
        text-transform: uppercase;
        z-index: 10;
        letter-spacing: 1px;
    }

    /* Card body */
    .post-body {
        background: #ffffff;
        border-radius: 16px;
        padding: 16px;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    [data-theme="dark"] .post-body {
        background: var(--bg-surface);
    }
    
    .post-badges {
        display: flex;
        gap: 8px;
        margin-bottom: 4px;
        flex-wrap: wrap;
    }
    
    .post-date-badge {
        background: #f1f5f9;
        color: #64748b;
        font-size: 0.65rem;
        padding: 4px 8px;
        border-radius: 20px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    [data-theme="dark"] .post-date-badge {
        background: rgba(255,255,255,0.05);
        color: #94a3b8;
    }
    
    .post-date-badge i, .platform-badge i {
        background: #ffffff;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 9px;
    }
    [data-theme="dark"] .post-date-badge i, [data-theme="dark"] .platform-badge i {
        background: rgba(255,255,255,0.1);
    }
    
    .platform-badge {
        font-size: 0.65rem;
        padding: 4px 8px;
        border-radius: 20px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 4px;
        text-transform: uppercase;
    }

    .post-concept {
        font-size: 0.95rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    [data-theme="dark"] .post-concept {
        color: #f8fafc;
    }
    
    .post-copy {
        font-size: 0.75rem;
        color: #64748b;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    [data-theme="dark"] .post-copy {
        color: #94a3b8;
    }


    @media (max-width: 768px) {
        .board-header {
            flex-direction: column;
            padding: 1.25rem;
            gap: 1.5rem;
            position: relative;
        }
        
        .board-header > div:first-child {
            flex-direction: column;
            width: 100%;
            gap: 1rem !important;
            align-items: center;
        }
        
        .btn-back-compact {
            position: absolute;
            top: 1.25rem;
            left: 1.25rem;
        }

        .board-header-info {
            flex-direction: column;
            text-align: center;
            width: 100%;
            gap: 0.5rem;
        }

        .board-header-info > div {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .phase-container {
            justify-content: center;
            flex-direction: column;
            gap: 0.5rem;
        }

        .phase-container span[style*="border-left"] {
            border-left: none !important;
            padding-left: 0 !important;
            margin-left: 0 !important;
            padding-top: 0.5rem;
            border-top: 1px solid var(--border-color);
            width: 100%;
        }

        .board-header > div:last-child {
            width: 100%;
        }

        .board-header > div:last-child button {
            flex: 1;
            justify-content: center;
        }
    } /* <-- CLosing brace for @media (max-width: 768px) */
    
    .btn-top-action {
        background: rgba(248, 250, 252, 0.8);
        backdrop-filter: blur(8px);
        color: #475569;
        border: 1px solid rgba(0, 0, 0, 0.06);
        font-weight: 600;
        padding: 0.6rem 0.9rem;
        border-radius: 14px;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    }
    .btn-top-action:hover {
        background: #ffffff;
        color: #0d945a;
        border-color: rgba(13, 148, 90, 0.2);
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    }
    [data-theme="dark"] .btn-top-action {
        background: #181818;
        border-color: #27272a;
        color: #cbd5e1;
    }
    [data-theme="dark"] .btn-top-action:hover {
        background: #27272a;
        color: #10b981;
    }
    .btn-top-action i {
        font-size: 1.25rem;
        color: inherit;
        transition: transform 0.3s ease;
    }
    .btn-top-action:hover i {
        transform: scale(1.15);
    }
    
    .btn-publish {
        background: linear-gradient(to right, #0d945a, #044b36);
        color: #ffffff;
        border: none;
        font-weight: 700;
        padding: 0.7rem 1.4rem;
        border-radius: 14px;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        box-shadow: 0 8px 24px rgba(13, 148, 90, 0.3);
        white-space: nowrap;
        font-size: 0.95rem;
    }
    .btn-publish:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 12px 30px rgba(13, 148, 90, 0.4);
        background: linear-gradient(to right, #0f9f61, #055940);
    }
    .btn-publish i {
        font-size: 1.25rem;
    }

    /* Cronómetro Total del Mes (Header Component Redesigned) */
    .month-total-timer-card {
        background: rgba(15, 23, 42, 0.03);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 0.65rem 1.15rem;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        min-width: 250px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    [data-theme="dark"] .month-total-timer-card {
        background: #181818;
        border-color: #27272a;
    }
    .month-total-timer-card.active {
        border-color: rgba(56, 189, 248, 0.3);
        box-shadow: 0 4px 20px rgba(56, 189, 248, 0.08);
    }
    .month-total-timer-card.warning {
        border-color: rgba(245, 158, 11, 0.4);
        box-shadow: 0 4px 20px rgba(245, 158, 11, 0.12);
    }
    .month-total-timer-card.expired {
        border-color: rgba(239, 68, 68, 0.4);
        box-shadow: 0 4px 20px rgba(239, 68, 68, 0.12);
    }
    .month-total-timer-card.completed {
        border-color: rgba(16, 185, 129, 0.4);
        box-shadow: 0 4px 20px rgba(16, 185, 129, 0.12);
    }
    .month-total-timer-card.upcoming {
        border-color: rgba(139, 92, 246, 0.4);
        box-shadow: 0 4px 20px rgba(139, 92, 246, 0.12);
    }

    .mtt-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
    }
    .mtt-pulse-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #38bdf8;
        box-shadow: 0 0 10px #38bdf8;
        display: inline-block;
        animation: pulse-dot 1.8s infinite;
    }
    .month-total-timer-card.warning .mtt-pulse-dot { background: #f59e0b; box-shadow: 0 0 10px #f59e0b; }
    .month-total-timer-card.expired .mtt-pulse-dot { background: #ef4444; box-shadow: 0 0 10px #ef4444; }
    .month-total-timer-card.completed .mtt-pulse-dot { background: #10b981; box-shadow: 0 0 10px #10b981; }
    .month-total-timer-card.upcoming .mtt-pulse-dot { background: #8b5cf6; box-shadow: 0 0 10px #8b5cf6; }
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(1.3); }
    }

    .mtt-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: var(--text-muted);
        flex: 1;
        margin-left: 0.35rem;
    }
    .mtt-counter-box {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
    }
    .mtt-counter-value {
        font-size: 1.35rem;
        font-weight: 800;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.5px;
        color: var(--color-title);
        line-height: 1.1;
    }
    .month-total-timer-card.active .mtt-counter-value { color: #38bdf8; }
    [data-theme="light"] .month-total-timer-card.active .mtt-counter-value { color: #0284c7; }
    .month-total-timer-card.warning .mtt-counter-value { color: #f59e0b; }
    .month-total-timer-card.expired .mtt-counter-value { color: #ef4444; }
    .month-total-timer-card.completed .mtt-counter-value { color: #10b981; }
    .month-total-timer-card.upcoming .mtt-counter-value { color: #8b5cf6; }

    .mtt-status-pill {
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        padding: 0.12rem 0.45rem;
        border-radius: 6px;
        letter-spacing: 0.4px;
    }
    .status-pill-active {
        background: rgba(56, 189, 248, 0.15);
        color: #0284c7;
    }
    [data-theme="dark"] .status-pill-active {
        color: #38bdf8;
    }
    .status-pill-warning {
        background: rgba(245, 158, 11, 0.15);
        color: #d97706;
    }
    [data-theme="dark"] .status-pill-warning {
        color: #fbbf24;
    }
    .status-pill-expired {
        background: rgba(239, 68, 68, 0.15);
        color: #dc2626;
    }
    [data-theme="dark"] .status-pill-expired {
        color: #f87171;
    }
    .status-pill-completed {
        background: rgba(16, 185, 129, 0.15);
        color: #059669;
    }
    [data-theme="dark"] .status-pill-completed {
        color: #34d399;
    }
    .status-pill-upcoming {
        background: rgba(139, 92, 246, 0.15);
        color: #7c3aed;
    }
    [data-theme="dark"] .status-pill-upcoming {
        color: #a78bfa;
    }
    .mtt-dates {
        font-size: 0.7rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-weight: 500;
    }

    /* RIGHT ACTIONS GROUP */
    .header-actions-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .header-action-buttons {
        display: flex;
        align-items: center;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 3px;
        gap: 2px;
    }
    [data-theme="dark"] .header-action-buttons {
        background: #1e293b;
        border-color: #334155;
    }
    .btn-top-action {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 0.75rem;
        border-radius: 10px;
        background: transparent;
        border: none;
        color: var(--text-muted);
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    .btn-top-action:hover {
        background: rgba(0, 0, 0, 0.05);
        color: var(--color-title);
    }
    [data-theme="dark"] .btn-top-action:hover {
        background: rgba(255, 255, 255, 0.08);
        color: #ffffff;
    }
    .btn-top-action i {
        font-size: 1.15rem;
    }
    .btn-publish {
        background: var(--color-btn-bg, var(--primary-color));
        color: var(--color-btn-text, #ffffff);
        border: none;
        font-weight: 700;
        padding: 0.65rem 1.25rem;
        border-radius: 12px;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        box-shadow: 0 4px 16px color-mix(in srgb, var(--color-btn-bg, var(--primary-color)) 30%, transparent);
        white-space: nowrap;
        font-size: 0.88rem;
        cursor: pointer;
    }
    .btn-publish:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px color-mix(in srgb, var(--color-btn-bg, var(--primary-color)) 45%, transparent);
        background: var(--color-btn-hover, var(--primary-hover, var(--primary-color)));
        color: var(--color-btn-text, #ffffff);
    }

    /* POST CARD TIMERS */
    .post-live-timer {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(15, 23, 42, 0.82);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.18);
        color: #38bdf8;
        padding: 0.3rem 0.65rem;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        box-shadow: 0 4px 14px rgba(0,0,0,0.3);
        z-index: 10;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.3px;
        transition: all 0.25s ease;
    }
    .post-live-timer.warning {
        background: rgba(245, 158, 11, 0.92);
        color: #ffffff;
        border-color: rgba(255,255,255,0.35);
        animation: pulse-timer-warning 2s infinite ease-in-out;
    }
    .post-live-timer.expired {
        background: rgba(239, 68, 68, 0.92);
        color: #ffffff;
        border-color: rgba(255,255,255,0.35);
    }
    .post-live-timer.completed {
        background: rgba(16, 185, 129, 0.92);
        color: #ffffff;
        border-color: rgba(255,255,255,0.35);
    }
    .post-live-timer.upcoming {
        background: rgba(99, 102, 241, 0.92);
        color: #ffffff;
        border-color: rgba(255,255,255,0.35);
    }
    @keyframes pulse-timer-warning {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.04); }
    }

    @media (max-width: 992px) {
        .board-header {
            flex-direction: column;
            align-items: stretch !important;
            gap: 1.25rem;
            padding: 1.25rem;
        }
        .board-header-left {
            width: 100%;
        }
        .month-total-timer-card {
            width: 100%;
            min-width: unset;
        }
        .header-actions-group {
            width: 100%;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .header-action-buttons {
            flex: 1;
            justify-content: space-around;
        }
    }
    @media (max-width: 576px) {
        .header-action-buttons {
            width: 100%;
            overflow-x: auto;
        }
        .action-btn-label {
            display: none;
        }
        .btn-publish {
            width: 100%;
        }
    }
</style>

<div class="board-header">
    <div class="board-header-left">
        <a href="index.php?module=project_board&id=<?php echo $monthData['project_id']; ?>" class="btn-back-compact" title="Volver a los Meses">
            <i class="ph ph-arrow-left"></i>
        </a>
        <div class="board-header-info">
            <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo">
            <div>
                <h1 class="board-title">
                    <?php echo $monthNames[$monthData['month']] . ' ' . $monthData['year']; ?>
                </h1>
                <div class="board-meta-row">
                    <span class="board-brand-name"><i class="ph-bold ph-storefront"></i> <?php echo htmlspecialchars($monthData['brand_name']); ?></span>
                    <?php $mbCorrelativo = (strpos($monthData['correlativo'] ?? '', 'OT-') === 0) ? $monthData['correlativo'] : 'OT-' . ($monthData['correlativo'] ?? ''); ?>
                    <span class="board-ot-badge"><?php echo htmlspecialchars($mbCorrelativo); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Center: Redesigned Month Total Timer HUD -->
    <div class="month-total-timer-card <?php echo $isMonthComplete ? 'completed' : 'active'; ?>" id="month-total-timer" data-start="<?php echo htmlspecialchars($monthData['start_date'] ?? ''); ?>" data-due="<?php echo htmlspecialchars($monthData['due_date'] ?? ''); ?>" data-status="<?php echo htmlspecialchars($monthData['status'] ?? ''); ?>" data-progress="<?php echo $monthProgressPct; ?>" data-posts="<?php echo $totalPostsCount; ?>">
        <div class="mtt-header">
            <span class="mtt-pulse-dot"></span>
            <span class="mtt-label">Tiempo Restante del Mes</span>
            <span class="mtt-status-pill <?php echo $isMonthComplete ? 'status-pill-completed' : 'status-pill-active'; ?>" id="mtt-status-pill"><?php echo $isMonthComplete ? 'Terminado' : 'En Curso'; ?></span>
        </div>
        <div class="mtt-counter-box">
            <div class="mtt-counter-value" id="mtt-countdown"><?php echo $isMonthComplete ? 'Terminado' : 'Calculando...'; ?></div>
        </div>
        <?php if (!empty($monthData['start_date']) && !empty($monthData['due_date'])): ?>
        <div class="mtt-dates">
            <i class="ph ph-calendar-blank"></i> <?php echo date('d M', strtotime($monthData['start_date'])) . ' - ' . date('d M Y', strtotime($monthData['due_date'])); ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Right Actions Group -->
    <div class="header-actions-group">
        <div class="header-action-buttons">
            <button class="btn-top-action" onclick="startPresentation()" title="Iniciar Presentación">
                <i class="ph ph-presentation-chart"></i>
                <span class="action-btn-label">Presentación</span>
            </button>
            <button class="btn-top-action" onclick="openSocialProfilesModal()" title="Vista Previa de Perfiles Sociales">
                <i class="ph ph-instagram-logo"></i>
                <span class="action-btn-label">Perfiles</span>
            </button>
            <button class="btn-top-action" onclick="openShareModal()" title="Compartir Tablero">
                <i class="ph ph-share-network"></i>
                <span class="action-btn-label">Compartir</span>
            </button>
            <button class="btn-top-action" onclick="openUploadDriveModal()" title="Subir Archivos a Google Drive">
                <i class="ph ph-upload-simple"></i>
                <span class="action-btn-label">Drive</span>
            </button>
        </div>
        <button class="btn btn-publish btn-responsive-full" onclick="openPostModal()">
            <i class="ph-bold ph-plus"></i> <span>Añadir Publicación</span>
        </button>
    </div>
</div>

<script>
function updateMonthBoardTimer() {
    // 1. Month Total Timer in Header
    const card = document.getElementById('month-total-timer');
    if (card) {
        const dueStr = card.getAttribute('data-due');
        const startStr = card.getAttribute('data-start');
        const status = (card.getAttribute('data-status') || '').toLowerCase();
        const progress = parseInt(card.getAttribute('data-progress') || '0', 10);
        const posts = parseInt(card.getAttribute('data-posts') || '0', 10);
        const countEl = document.getElementById('mtt-countdown');
        const pillEl = document.getElementById('mtt-status-pill');
        const now = new Date();

        if (status === 'finalizado' || (posts > 0 && progress >= 100)) {
            card.className = 'month-total-timer-card completed';
            if (pillEl) {
                pillEl.className = 'mtt-status-pill status-pill-completed';
                pillEl.textContent = 'Terminado';
            }
            if (countEl) countEl.textContent = 'Terminado';
        } else if (!dueStr) {
            if (countEl) countEl.textContent = 'Sin fecha';
            if (pillEl) {
                pillEl.className = 'mtt-status-pill status-pill-upcoming';
                pillEl.textContent = 'Sin fecha';
            }
        } else {
            const due = new Date(dueStr + (dueStr.includes('T') ? '' : 'T23:59:59'));
            const start = startStr ? new Date(startStr + (startStr.includes('T') ? '' : 'T00:00:00')) : new Date();

            if (now < start) {
                card.className = 'month-total-timer-card upcoming';
                if (pillEl) {
                    pillEl.className = 'mtt-status-pill status-pill-upcoming';
                    pillEl.textContent = 'Por Iniciar';
                }
                const diffUpcoming = start - now;
                const upDays = Math.floor(diffUpcoming / (1000 * 60 * 60 * 24));
                const upHours = String(Math.floor((diffUpcoming % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
                const upMins = String(Math.floor((diffUpcoming % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
                const upSecs = String(Math.floor((diffUpcoming % (1000 * 60)) / 1000)).padStart(2, '0');
                if (countEl) countEl.textContent = upDays > 0 ? `${upDays}d ${upHours}:${upMins}:${upSecs}` : `${upHours}:${upMins}:${upSecs}`;
            } else if (now > due) {
                card.className = 'month-total-timer-card expired';
                if (pillEl) {
                    pillEl.className = 'mtt-status-pill status-pill-expired';
                    pillEl.textContent = 'Agotado';
                }
                if (countEl) countEl.textContent = 'Tiempo agotado';
            } else {
                const diff = due - now;
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = String(Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
                const mins = String(Math.floor((diff % (1000 * 60)) / (1000 * 60))).padStart(2, '0');
                const secs = String(Math.floor((diff % (1000 * 60)) / 1000)).padStart(2, '0');

                if (days < 2) {
                    card.className = 'month-total-timer-card warning';
                    if (pillEl) {
                        pillEl.className = 'mtt-status-pill status-pill-warning';
                        pillEl.textContent = 'Por Vencer';
                    }
                } else {
                    card.className = 'month-total-timer-card active';
                    if (pillEl) {
                        pillEl.className = 'mtt-status-pill status-pill-active';
                        pillEl.textContent = 'En Curso';
                    }
                }
                if (countEl) countEl.textContent = days > 0 ? `${days}d ${hours}:${mins}:${secs}` : `${hours}:${mins}:${secs}`;
            }
        }
    }

    // 2. Individual Post Timers (Calculated based on Fecha de Creación and Fecha de Entrega)
    const now = new Date();
    document.querySelectorAll('.post-live-timer').forEach(el => {
        const dueStr = el.getAttribute('data-due');
        const startStr = el.getAttribute('data-start');
        const status = (el.getAttribute('data-status') || '').trim();
        const textEl = el.querySelector('.timer-text');
        const iconEl = el.querySelector('i');

        if (status === 'Publicado') {
            el.className = 'post-live-timer completed';
            if (iconEl) iconEl.className = 'ph-fill ph-check-circle';
            if (textEl) textEl.textContent = 'Publicado';
            return;
        }
        if (status === 'Aprobado') {
            el.className = 'post-live-timer completed';
            if (iconEl) iconEl.className = 'ph-fill ph-check-circle';
            if (textEl) textEl.textContent = 'Aprobado';
            return;
        }
        if (status === 'Archivado') {
            el.className = 'post-live-timer upcoming';
            if (iconEl) iconEl.className = 'ph ph-archive';
            if (textEl) textEl.textContent = 'Archivado';
            return;
        }

        if (!dueStr || dueStr === '0000-00-00' || dueStr === '0000-00-00 00:00:00') {
            el.className = 'post-live-timer upcoming';
            if (iconEl) iconEl.className = 'ph ph-calendar-blank';
            if (textEl) textEl.textContent = 'Sin entrega';
            return;
        }
        el.style.display = 'flex';

        let due;
        if (dueStr.includes(' ')) {
            due = new Date(dueStr.replace(' ', 'T'));
        } else if (!dueStr.includes('T')) {
            due = new Date(dueStr + 'T23:59:59');
        } else {
            due = new Date(dueStr);
        }

        let start = null;
        if (startStr && startStr !== '0000-00-00' && startStr !== '0000-00-00 00:00:00') {
            if (startStr.includes(' ')) {
                start = new Date(startStr.replace(' ', 'T'));
            } else if (!startStr.includes('T')) {
                start = new Date(startStr + 'T00:00:00');
            } else {
                start = new Date(startStr);
            }
        }

        if (isNaN(due.getTime())) {
            el.style.display = 'none';
            return;
        }

        // Check if creation date is in future
        if (start && !isNaN(start.getTime()) && now < start) {
            el.className = 'post-live-timer upcoming';
            if (iconEl) iconEl.className = 'ph-bold ph-clock-countdown';
            const diffUpcoming = start - now;
            const upDays = Math.floor(diffUpcoming / (1000 * 60 * 60 * 24));
            const upHours = String(Math.floor((diffUpcoming % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
            const upMins = String(Math.floor((diffUpcoming % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
            const upSecs = String(Math.floor((diffUpcoming % (1000 * 60)) / 1000)).padStart(2, '0');
            if (upDays > 0) {
                textEl.textContent = `Inicia en ${upDays}d ${upHours}:${upMins}:${upSecs}`;
            } else {
                textEl.textContent = `Inicia en ${upHours}:${upMins}:${upSecs}`;
            }
            return;
        }

        if (now > due) {
            el.className = 'post-live-timer expired';
            if (iconEl) iconEl.className = 'ph-fill ph-warning-circle';
            if (textEl) textEl.textContent = 'Tiempo agotado';
            return;
        }

        const diff = due - now;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = String(Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
        const mins = String(Math.floor((diff % (1000 * 60)) / (1000 * 60))).padStart(2, '0');
        const secs = String(Math.floor((diff % (1000 * 60)) / 1000)).padStart(2, '0');

        if (days < 1) {
            el.className = 'post-live-timer warning';
            if (iconEl) iconEl.className = 'ph-fill ph-hourglass-medium';
        } else {
            el.className = 'post-live-timer active';
            if (iconEl) iconEl.className = 'ph-fill ph-hourglass-high';
        }

        if (days > 0) {
            textEl.textContent = `${days}d ${hours}:${mins}:${secs}`;
        } else {
            textEl.textContent = `${hours}:${mins}:${secs}`;
        }
    });
}
document.addEventListener('DOMContentLoaded', updateMonthBoardTimer);
updateMonthBoardTimer();
setInterval(updateMonthBoardTimer, 1000);
</script>

<!-- Content Pillars Month Balance Banner -->
<?php if (!empty($posts)): ?>
<div class="content-pillars-bar-card">
    <div class="cpb-header">
        <div class="cpb-title">
            <i class="ph-bold ph-chart-pie-slice" style="color: var(--primary-color);"></i>
            <span>Equilibrio de Contenido del Mes</span>
        </div>
        <div class="cpb-legend">
            <?php foreach ($pillarDefinitions as $pKey => $pDef): 
                $count = $pillarCounts[$pKey] ?? 0;
                $pct = ($totalPostsCount > 0) ? round(($count / $totalPostsCount) * 100) : 0;
                if ($count === 0 && $totalPostsCount > 0) continue;
            ?>
                <div class="cpb-legend-item">
                    <span class="cpb-dot" style="background: <?php echo $pDef['color']; ?>;"></span>
                    <span class="cpb-name"><?php echo $pDef['label']; ?>:</span>
                    <span class="cpb-val" style="color: <?php echo $pDef['color']; ?>;"><?php echo $count; ?> (<?php echo $pct; ?>%)</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="cpb-progress-track">
        <?php foreach ($pillarDefinitions as $pKey => $pDef): 
            $count = $pillarCounts[$pKey] ?? 0;
            $pct = ($totalPostsCount > 0) ? round(($count / $totalPostsCount) * 100, 1) : 0;
            if ($pct <= 0) continue;
        ?>
            <div class="cpb-progress-segment" style="width: <?php echo $pct; ?>%; background: <?php echo $pDef['color']; ?>;" title="<?php echo $pDef['label'] . ': ' . $count . ' posts (' . $pct . '%)'; ?>"></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="posts-grid">
    <?php if (empty($posts)): ?>
        <div style="grid-column: 1 / -1; padding: 4rem 2rem; text-align: center; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: var(--radius-lg); color: var(--text-muted);">
            <i class="ph ph-image-square" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem; display: block;"></i>
            <h3>No hay publicaciones aún</h3>
            <p>Comienza añadiendo el primer post para este mes.</p>
        </div>
    <?php else: ?>
        <?php
        $activePosts = [];
        $archivedPosts = [];
        foreach ($posts as $p) {
            if ($p['status'] === 'Archivado') {
                $archivedPosts[] = $p;
            } else {
                $activePosts[] = $p;
            }
        }
        $renderGroups = [
            ['title' => '', 'posts' => $activePosts, 'icon' => '', 'color' => ''],
            ['title' => 'Publicaciones Archivadas', 'posts' => $archivedPosts, 'icon' => 'ph-archive', 'color' => '#9333ea']
        ];
        $postIndex = 0;
        ?>
        <?php foreach ($renderGroups as $group): ?>
            <?php if (empty($group['posts'])) continue; ?>
            
            <?php if (!empty($group['title'])): ?>
                <div style="grid-column: 1 / -1; margin-top: 2rem; margin-bottom: 0.5rem; display: flex; flex-direction: column; align-items: center;">
                    <button type="button" onclick="document.getElementById('archived-section').style.display = document.getElementById('archived-section').style.display === 'none' ? 'contents' : 'none'; this.innerHTML = document.getElementById('archived-section').style.display === 'none' ? '<i class=\'ph ph-archive\'></i> Ver Publicaciones Archivadas' : '<i class=\'ph ph-archive\'></i> Ocultar Publicaciones Archivadas'" style="background: white; border: 1px solid #9333ea; color: #9333ea; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;">
                        <i class="ph <?php echo $group['icon']; ?>"></i> Ver <?php echo $group['title']; ?>
                    </button>
                </div>
                <div id="archived-section" style="display: none;">
            <?php endif; ?>
            
            <?php foreach ($group['posts'] as $p): ?>
                <?php
            $postIndex++;
            $sColor = $statusColors[$p['status']] ?? $statusColors['Borrador'];
            $dateFmt = (!empty($p['post_date']) && $p['post_date'] !== '0000-00-00' && $p['post_date'] !== '0000-00-00 00:00:00') ? date('d M Y', strtotime($p['post_date'])) : 'Sin Fecha';
            $icons = [
                'Facebook' => ['icon' => 'ph-facebook-logo', 'color' => '#1877F2'],
                'Instagram' => ['icon' => 'ph-instagram-logo', 'color' => '#E1306C'],
                'TikTok' => ['icon' => 'ph-tiktok-logo', 'color' => '#000000'],
                'LinkedIn' => ['icon' => 'ph-linkedin-logo', 'color' => '#0A66C2'],
                'Twitter / X' => ['icon' => 'ph-twitter-logo', 'color' => '#0F1419'],
                'Otro' => ['icon' => 'ph-share-network', 'color' => '#64748b']
            ];
            $platforms = explode(', ', $p['platform']);
            $firstPlatform = trim($platforms[0]);
            $platformData = $icons[$firstPlatform] ?? $icons['Otro'];
            $icon = $platformData['icon'];
            $iconColor = $platformData['color'];
            $platformLabel = count($platforms) > 1 ? $firstPlatform . ' +' . (count($platforms) - 1) : $firstPlatform;

            // Compute Post Deadline for timer
            $postDeadline = '';
            if (!empty($p['end_date']) && $p['end_date'] !== '0000-00-00' && $p['end_date'] !== '0000-00-00 00:00:00') {
                $postDeadline = $p['end_date'];
            } elseif (!empty($p['post_date']) && $p['post_date'] !== '0000-00-00' && $p['post_date'] !== '0000-00-00 00:00:00') {
                $postDeadline = $p['post_date'];
            } elseif (!empty($monthData['due_date'])) {
                $postDeadline = $monthData['due_date'];
            }
            ?>
            <div class="post-card" style="background: <?php echo $sColor['bg']; ?>; border: 1px solid <?php echo $sColor['color']; ?>22;" onclick="editPost(<?php echo htmlspecialchars(json_encode($p) ?: '{}'); ?>)">
                <!-- Header Section -->
                <div class="post-card-header">
                    <div class="post-order-badge" style="color: <?php echo $sColor['color']; ?>;">
                        Post <?php echo str_pad($postIndex, 2, '0', STR_PAD_LEFT); ?>
                    </div>
                    <div class="post-card-actions">
                        <?php
                        $exp = time() + (7 * 24 * 3600); // 7 days
                        $secret = 'ROMA_SECRET_' . $p['id'];
                        $sig = md5($secret . $exp);
                        $shareUrl = "public_post.php?id={$p['id']}&exp={$exp}&sig={$sig}";
                        ?>
                        <button type="button" class="btn-action" onclick="event.stopPropagation(); window.open('<?php echo $shareUrl; ?>', '_blank')" style="color: <?php echo $sColor['color']; ?>;" title="Abrir en Nueva Pestaña">
                            <i class="ph ph-arrow-square-out"></i>
                        </button>
                        <button type="button" class="btn-action" onclick="event.stopPropagation(); setPostStatusAjax(<?php echo $p['id']; ?>, '<?php echo $p['status'] === 'Archivado' ? 'Borrador' : 'Archivado'; ?>')" style="color: <?php echo $sColor['color']; ?>;" title="<?php echo $p['status'] === 'Archivado' ? 'Restaurar a Borrador' : 'Archivar'; ?>">
                            <i class="ph <?php echo $p['status'] === 'Archivado' ? 'ph-arrow-u-up-left' : 'ph-archive'; ?>"></i>
                        </button>
                        <button type="button" class="btn-action" onclick="event.stopPropagation(); openShareSinglePostModal('<?php echo $shareUrl; ?>')" style="color: <?php echo $sColor['color']; ?>;" title="Compartir Post Público">
                            <i class="ph ph-share-network"></i>
                        </button>
                        <button type="button" class="btn-action delete" onclick="event.stopPropagation(); deletePost(<?php echo $p['id']; ?>)" style="color: <?php echo $sColor['color']; ?>;" title="Eliminar">
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                </div>

                <!-- Image Section -->
                <div class="post-image<?php echo empty($mediaList) ? ' no-img' : ''; ?>">
                    <!-- Cronómetro del Post en vivo (calculado con Fecha de Creación y Entrega) -->
                    <?php 
                        $pstStatus = trim($p['status'] ?? '');
                        $isPub = ($pstStatus === 'Publicado');
                        $isApr = ($pstStatus === 'Aprobado');
                        $timerClass = ($isPub || $isApr) ? 'post-live-timer completed' : 'post-live-timer';
                        $timerIcon = ($isPub || $isApr) ? 'ph-fill ph-check-circle' : 'ph-fill ph-hourglass-high';
                        $timerText = $isPub ? 'Publicado' : ($isApr ? 'Aprobado' : 'Calculando...');
                    ?>
                    <div class="<?php echo $timerClass; ?>" data-start="<?php echo htmlspecialchars($p['post_date'] ?? ''); ?>" data-due="<?php echo htmlspecialchars($p['end_date'] ?? ''); ?>" data-status="<?php echo htmlspecialchars($p['status']); ?>" data-id="<?php echo $p['id']; ?>">
                        <i class="<?php echo $timerIcon; ?>"></i>
                        <span class="timer-text"><?php echo $timerText; ?></span>
                    </div>

                    <?php 
                    $mediaStr = $p['post_type'] === 'Referencia Visual' ? $p['reference_image_link'] : $p['image_link'];
                    $mediaList = json_decode($mediaStr, true);
                    if (!is_array($mediaList) && !empty($mediaStr)) {
                        $mediaList = [$mediaStr];
                    }
                    if (empty($mediaList)) { $mediaList = []; }

                    if (count($mediaList) > 1): ?>
                        <div class="swiper mySwiper-<?php echo $p['id']; ?>" style="width: 100%; height: 100%;">
                            <div class="swiper-wrapper">
                                <?php foreach($mediaList as $mItem): ?>
                                    <div class="swiper-slide" style="display: flex; align-items: center; justify-content: center; background: #f1f5f9;">
                                        <img src="<?php echo htmlspecialchars($mItem); ?>" style="width: 100%; height: 100%; object-fit: contain;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="swiper-button-next" style="color: white; text-shadow: 0 1px 3px rgba(0,0,0,0.5); transform: scale(0.6);"></div>
                            <div class="swiper-button-prev" style="color: white; text-shadow: 0 1px 3px rgba(0,0,0,0.5); transform: scale(0.6);"></div>
                            <div class="swiper-pagination"></div>
                        </div>
                    <?php elseif (count($mediaList) === 1 && !empty($mediaList[0])): ?>
                        <?php 
                        $singleMedia = $mediaList[0];
                        if (preg_match('/^https?:\/\/.*(jpg|jpeg|png|webp|gif)$/i', $singleMedia)): ?>
                            <img src="<?php echo htmlspecialchars($singleMedia); ?>" style="width: 100%; height: 100%; object-fit: contain;">
                        <?php elseif (preg_match('/^https?:\/\/.*(mp4|webm|ogg)$/i', $singleMedia) || strpos($singleMedia, 'data:video/') === 0): ?>
                            <video src="<?php echo htmlspecialchars($singleMedia); ?>" controls style="width: 100%; height: 100%; object-fit: contain;"></video>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($singleMedia); ?>" style="width: 100%; height: 100%; object-fit: contain;">
                        <?php endif; ?>
                    <?php else: ?>
                        <i class="ph ph-image" style="font-size: 3rem; color: #94a3b8; opacity: 0.5;"></i>
                    <?php endif; ?>
                    
                    <?php if ($p['post_type'] === 'Referencia Visual'): ?>
                    <div style="position: absolute; bottom: 8px; right: 8px; background: rgba(0,0,0,0.6); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.65rem; font-weight: 700; backdrop-filter: blur(4px); z-index: 5;">
                        REFERENCIA GRÁFICA
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Body Section -->
                <div class="post-body">
                    <div class="post-badges">
                        <?php 
                            $pillar = $p['content_pillar'] ?? 'Educación';
                            $pDef = $pillarDefinitions[$pillar] ?? ['label' => $pillar, 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.15)', 'icon' => 'ph-graduation-cap'];
                        ?>
                        <div class="post-pillar-badge" style="background: <?php echo $pDef['bg']; ?>; color: <?php echo $pDef['color']; ?>; border: 1px solid <?php echo $pDef['color']; ?>33;" title="Pilar: <?php echo htmlspecialchars($pillar); ?>">
                            <i class="ph <?php echo $pDef['icon']; ?>"></i> <?php echo htmlspecialchars($pDef['label']); ?>
                        </div>

                        <?php
                            $qaData = !empty($p['qa_checklist']) ? json_decode($p['qa_checklist'], true) : [];
                            $qaTotal = 4;
                            $qaDone = 0;
                            if (is_array($qaData)) {
                                if (!empty($qaData['spelling'])) $qaDone++;
                                if (!empty($qaData['brand'])) $qaDone++;
                                if (!empty($qaData['format'])) $qaDone++;
                                if (!empty($qaData['cta'])) $qaDone++;
                            }
                            $qaClass = ($qaDone === $qaTotal) ? 'qa-complete' : (($qaDone > 0) ? 'qa-partial' : 'qa-pending');
                        ?>
                        <div class="post-qa-badge <?php echo $qaClass; ?>" title="Control de Calidad (QA): <?php echo $qaDone; ?>/<?php echo $qaTotal; ?> completados">
                            <i class="ph-bold <?php echo ($qaDone === $qaTotal) ? 'ph-check-circle' : 'ph-list-checks'; ?>"></i> QA <?php echo $qaDone; ?>/<?php echo $qaTotal; ?>
                        </div>

                        <?php if (!empty($p['post_date']) && $p['post_date'] !== '0000-00-00' && $p['post_date'] !== '0000-00-00 00:00:00'): ?>
                        <div class="post-date-badge" title="Fecha de Creación">
                            <i class="ph ph-calendar-plus" style="color: #64748b;"></i> <?php echo date('d M', strtotime($p['post_date'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($p['end_date']) && $p['end_date'] !== '0000-00-00' && $p['end_date'] !== '0000-00-00 00:00:00'): ?>
                        <div class="post-date-badge" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;" title="Fecha de Entrega">
                            <i class="ph-bold ph-flag-checkered" style="color: #3b82f6;"></i> <?php echo date('d M', strtotime($p['end_date'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="platform-badge" style="background: <?php echo $sColor['color']; ?>; color: #ffffff;">
                            <i class="ph ph-tag" style="color: <?php echo $sColor['color']; ?>;"></i> <?php echo htmlspecialchars($p['status']); ?>
                        </div>
                    </div>
                    <h3 class="post-concept"><?php echo htmlspecialchars($p['concept']); ?></h3>
                    <div class="post-copy">
                        <?php echo strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], " ", $p['copy_text'])); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
            <?php if (!empty($group['title'])): ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
/* ===== REDISEÑO MODAL APP MODERNO ===== */
.modal-overlay {
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    background: rgba(0, 0, 0, 0.8) !important;
    padding: 1.25rem;
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    z-index: 1050;
    align-items: center;
    justify-content: center;
}
.modal-overlay.active {
    display: flex;
}

.modal-content.crm-layout {
    max-width: 1540px;
    width: 96vw;
    height: 93vh;
    max-height: 94vh;
    display: flex;
    flex-direction: column;
    padding: 0;
    overflow: hidden;
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 24px;
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.2);
    position: relative;
}
[data-theme="dark"] .crm-modal-card {
    background: #121212;
    border-color: #27272a;
    box-shadow: 0 30px 90px -15px rgba(0, 0, 0, 0.9), 0 0 0 1px rgba(255, 255, 255, 0.06);
}

/* App Header (Full Width Top Bar) */
.crm-app-header {
    height: 64px;
    padding: 0 1.5rem;
    background: var(--bg-surface, #ffffff);
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    flex-shrink: 0;
    z-index: 20;
}
[data-theme="dark"] .crm-app-header {
    background: #141416;
    border-bottom-color: #27272a;
}
.crm-header-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex: 1;
    min-width: 0;
}
.crm-header-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(79, 70, 229, 0.12);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    border: 1px solid rgba(79, 70, 229, 0.2);
}
.crm-header-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--color-title);
    margin: 0;
    letter-spacing: -0.3px;
    line-height: 1.2;
}
.crm-header-subtitle {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-muted);
}

/* Pipeline de estados (Segmented Control) */
.pipeline-stages {
    display: flex;
    background: var(--bg-color, #f1f5f9);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 14px;
    padding: 3px;
    gap: 3px;
}
[data-theme="dark"] .pipeline-stages {
    background: #181818;
    border-color: #27272a;
}
.pipeline-stage {
    padding: 0.35rem 0.9rem;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.pipeline-stage:hover {
    color: var(--color-title, #0f172a);
    background: rgba(125, 125, 125, 0.08);
}
[data-theme="dark"] .pipeline-stage:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.04);
}
.pipeline-stage.active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
[data-theme="dark"] .pipeline-stage.active {
    background: #27272a;
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
}
.pipeline-stage.active[data-status="Borrador"] { background: rgba(100, 116, 139, 0.18); color: #475569; border: 1px solid rgba(100, 116, 139, 0.3); }
.pipeline-stage.active[data-status="En Revisión"] { background: rgba(245, 158, 11, 0.18); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); }
.pipeline-stage.active[data-status="Aprobado"] { background: rgba(59, 130, 246, 0.18); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.3); }
.pipeline-stage.active[data-status="Publicado"] { background: rgba(16, 185, 129, 0.18); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); }
.pipeline-stage.active[data-status="Archivado"] { background: rgba(147, 51, 234, 0.18); color: #7e22ce; border: 1px solid rgba(147, 51, 234, 0.3); }

[data-theme="dark"] .pipeline-stage.active[data-status="Borrador"] { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }
[data-theme="dark"] .pipeline-stage.active[data-status="En Revisión"] { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
[data-theme="dark"] .pipeline-stage.active[data-status="Aprobado"] { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
[data-theme="dark"] .pipeline-stage.active[data-status="Publicado"] { background: rgba(16, 185, 129, 0.2); color: #34d399; }
[data-theme="dark"] .pipeline-stage.active[data-status="Archivado"] { background: rgba(147, 51, 234, 0.2); color: #c084fc; }

/* Header Action Buttons */
.crm-header-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    flex: 1;
    min-width: 0;
}
.btn-header-social {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 0.95rem;
    border-radius: 12px;
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #6366f1;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}
[data-theme="dark"] .btn-header-social {
    color: #818cf8;
}
.btn-header-social:hover {
    background: #6366f1;
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
}

.btn-header-save {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 1.2rem;
    border-radius: 12px;
    background: var(--color-btn-bg, var(--primary-color));
    border: none;
    color: var(--color-btn-text, #ffffff);
    font-size: 0.84rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--color-btn-bg, var(--primary-color)) 30%, transparent);
    transition: all 0.2s ease;
}
.btn-header-save:hover {
    background: var(--color-btn-hover, var(--primary-hover, var(--primary-color)));
    transform: translateY(-1px);
    box-shadow: 0 6px 18px color-mix(in srgb, var(--color-btn-bg, var(--primary-color)) 45%, transparent);
    color: var(--color-btn-text, #ffffff);
}

.btn-header-close {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--bg-color, #f1f5f9);
    border: 1px solid var(--border-color, #e2e8f0);
    color: var(--text-muted, #64748b);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 1.1rem;
}
.btn-header-close:hover {
    background: var(--border-color, #e2e8f0);
    color: var(--color-title, #0f172a);
}
[data-theme="dark"] .btn-header-close {
    background: #181818;
    border-color: #27272a;
    color: #a1a1aa;
}
[data-theme="dark"] .btn-header-close:hover {
    background: #27272a;
    color: #ffffff;
}

/* Body Container */
.crm-body-container {
    display: flex;
    flex: 1;
    overflow: hidden;
    position: relative;
}

/* Sidebar Column */
.crm-sidebar {
    width: 360px;
    border-right: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-surface, #ffffff);
    padding: 1.4rem;
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    overflow-y: auto;
    flex-shrink: 0;
}
[data-theme="dark"] .crm-sidebar {
    border-right-color: #27272a;
    background: #141416;
}
.crm-sidebar-card {
    background: var(--bg-color, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 16px;
    padding: 1rem 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}
[data-theme="dark"] .crm-sidebar-card {
    background: #181818;
    border-color: #27272a;
}
.crm-sidebar-label {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
[data-theme="dark"] .crm-sidebar-label {
    color: #94a3b8;
}
.crm-sidebar-label.required::after {
    content: '*';
    color: var(--danger-color);
    font-size: 0.85rem;
}

/* Main Studio Panel */
.crm-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--bg-color, #f8fafc);
    position: relative;
}
[data-theme="dark"] .crm-main {
    background: #0f0f11;
}

/* Tabs Bar */
.crm-tabs {
    padding: 0 1.5rem;
    background: var(--bg-surface, #ffffff);
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    gap: 1.5rem;
    overflow-x: auto;
    flex-shrink: 0;
}
[data-theme="dark"] .crm-tabs {
    background: #141416;
    border-bottom-color: #27272a;
}
.crm-tab {
    padding: 0.85rem 0;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-weight: 700;
    color: var(--text-muted, #64748b);
    font-size: 0.88rem;
    white-space: nowrap;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.crm-tab:hover {
    color: var(--color-title, #0f172a);
}
[data-theme="dark"] .crm-tab:hover {
    color: #ffffff;
}
.crm-tab.active {
    border-bottom-color: var(--primary-color);
    color: var(--primary-color);
}
[data-theme="dark"] .crm-tab.active {
    color: #ffffff;
}
.crm-tab-pane {
    display: none;
    animation: fadeIn 0.3s ease;
}
.crm-tab-pane.active {
    display: block;
}

/* Studio Cards */
.studio-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 20px;
    padding: 1.4rem;
    box-shadow: 0 4px 16px rgba(0,0,0,0.03);
}
[data-theme="dark"] .studio-card {
    background: #181818;
    border-color: #27272a;
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
}

/* ===== PILARES DE CONTENIDO (MONTH BAR & MODAL) ===== */
.content-pillars-bar-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 18px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}
[data-theme="dark"] .content-pillars-bar-card {
    background: #141416;
    border-color: #27272a;
    box-shadow: none;
}
.cpb-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.cpb-title {
    font-size: 0.84rem;
    font-weight: 800;
    color: var(--color-title);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.cpb-legend {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.cpb-legend-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
}
.cpb-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.cpb-progress-track {
    width: 100%;
    height: 8px;
    background: rgba(0, 0, 0, 0.06);
    border-radius: 6px;
    overflow: hidden;
    display: flex;
}
[data-theme="dark"] .cpb-progress-track {
    background: #1f1f23;
}
.cpb-progress-segment {
    height: 100%;
    transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Pillar Pills in Modal */
.pillar-pill-group {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.45rem;
}
.pillar-input { display: none; }
.pillar-label {
    padding: 0.45rem 0.6rem;
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 10px;
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    color: var(--text-muted, #64748b);
    background: var(--bg-surface, #ffffff);
    transition: all 0.2s;
    user-select: none;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    justify-content: flex-start;
}
.pillar-label:hover { background: var(--bg-color, #f1f5f9); color: var(--color-title, #0f172a); }
[data-theme="dark"] .pillar-label {
    border-color: #27272a;
    color: #a1a1aa;
    background: #141416;
}
[data-theme="dark"] .pillar-label:hover { background: #27272a; color: #ffffff; }

input[value="Educación"]:checked + .pillar-label.pil-edu { background: rgba(59,130,246,0.2); color: #3b82f6; border-color: rgba(59,130,246,0.4); box-shadow: 0 2px 8px rgba(59,130,246,0.25); }
input[value="Ventas"]:checked + .pillar-label.pil-ventas { background: rgba(244,63,94,0.2); color: #e11d48; border-color: rgba(244,63,94,0.4); box-shadow: 0 2px 8px rgba(244,63,94,0.25); }
input[value="Branding"]:checked + .pillar-label.pil-brand { background: rgba(139,92,246,0.2); color: #8b5cf6; border-color: rgba(139,92,246,0.4); box-shadow: 0 2px 8px rgba(139,92,246,0.25); }
input[value="Entretenimiento"]:checked + .pillar-label.pil-ent { background: rgba(245,158,11,0.2); color: #d97706; border-color: rgba(245,158,11,0.4); box-shadow: 0 2px 8px rgba(245,158,11,0.25); }
input[value="Comunidad"]:checked + .pillar-label.pil-com { background: rgba(16,185,129,0.2); color: #059669; border-color: rgba(16,185,129,0.4); box-shadow: 0 2px 8px rgba(16,185,129,0.25); }
input[value="Testimonial"]:checked + .pillar-label.pil-test { background: rgba(6,182,212,0.2); color: #0891b2; border-color: rgba(6,182,212,0.4); box-shadow: 0 2px 8px rgba(6,182,212,0.25); }

[data-theme="dark"] input[value="Educación"]:checked + .pillar-label.pil-edu { color: #60a5fa; }
[data-theme="dark"] input[value="Ventas"]:checked + .pillar-label.pil-ventas { color: #fb7185; }
[data-theme="dark"] input[value="Branding"]:checked + .pillar-label.pil-brand { color: #c084fc; }
[data-theme="dark"] input[value="Entretenimiento"]:checked + .pillar-label.pil-ent { color: #fbbf24; }
[data-theme="dark"] input[value="Comunidad"]:checked + .pillar-label.pil-com { color: #34d399; }
[data-theme="dark"] input[value="Testimonial"]:checked + .pillar-label.pil-test { color: #22d3ee; }

/* Post Card Badges */
.post-pillar-badge {
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.post-qa-badge {
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.post-qa-badge.qa-complete {
    background: rgba(16,185,129,0.15);
    color: #059669;
    border: 1px solid rgba(16,185,129,0.3);
}
[data-theme="dark"] .post-qa-badge.qa-complete {
    color: #34d399;
}
.post-qa-badge.qa-partial {
    background: rgba(245,158,11,0.15);
    color: #d97706;
    border: 1px solid rgba(245,158,11,0.3);
}
[data-theme="dark"] .post-qa-badge.qa-partial {
    color: #fbbf24;
}
.post-qa-badge.qa-pending {
    background: rgba(100,116,139,0.15);
    color: #64748b;
    border: 1px solid rgba(100,116,139,0.3);
}
[data-theme="dark"] .post-qa-badge.qa-pending {
    color: #94a3b8;
}

/* ===== QA CHECKLIST CARD IN MODAL ===== */
.qa-checklist-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 16px;
    padding: 1rem 1.15rem;
    margin-top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
[data-theme="dark"] .qa-checklist-card {
    background: #141416;
    border-color: #27272a;
}
.qa-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.qa-title {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--color-title);
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.qa-counter-badge {
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.2rem 0.6rem;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.qa-badge-complete { background: rgba(16,185,129,0.18); color: #059669; border: 1px solid rgba(16,185,129,0.35); }
.qa-badge-progress { background: rgba(245,158,11,0.18); color: #d97706; border: 1px solid rgba(245,158,11,0.35); }
.qa-badge-pending { background: var(--bg-color, #f1f5f9); color: var(--text-muted, #64748b); }

[data-theme="dark"] .qa-badge-complete { color: #34d399; background: rgba(16,185,129,0.2); }
[data-theme="dark"] .qa-badge-progress { color: #fbbf24; background: rgba(245,158,11,0.2); }
[data-theme="dark"] .qa-badge-pending { background: #27272a; color: #a1a1aa; }

.qa-progress-bar-bg {
    width: 100%;
    height: 6px;
    background: rgba(0, 0, 0, 0.08);
    border-radius: 4px;
    overflow: hidden;
}
[data-theme="dark"] .qa-progress-bar-bg {
    background: #27272a;
}
.qa-progress-bar-fill {
    height: 100%;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), background 0.3s ease;
}

.qa-items-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem;
}
@media (max-width: 768px) {
    .qa-items-grid { grid-template-columns: 1fr; }
}
.qa-item {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    background: var(--bg-color, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 12px;
    padding: 0.6rem 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.qa-item:hover {
    background: #f1f5f9;
    border-color: var(--primary-color);
}
[data-theme="dark"] .qa-item {
    background: #18181a;
    border-color: #27272a;
}
[data-theme="dark"] .qa-item:hover {
    background: #222226;
    border-color: #3f3f46;
}
.qa-checkbox {
    width: 18px;
    height: 18px;
    accent-color: #10b981;
    margin-top: 2px;
    cursor: pointer;
}
.qa-item-content {
    flex: 1;
}
.qa-item-title {
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--color-title, #0f172a);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
[data-theme="dark"] .qa-item-title {
    color: #f1f5f9;
}
.qa-item-desc {
    font-size: 0.68rem;
    color: var(--text-muted, #64748b);
    line-height: 1.3;
    margin-top: 2px;
}
[data-theme="dark"] .qa-item-desc {
    color: #a1a1aa;
}

/* Radio/Checkboxes estilizados como botones (Pills) */
.pill-group { display: flex; flex-wrap: wrap; gap: 0.45rem; }
.pill-input { display: none; }
.pill-label { padding: 0.4rem 0.75rem; border: 1px solid var(--border-color, #e2e8f0); border-radius: 10px; font-size: 0.8rem; font-weight: 700; cursor: pointer; color: var(--text-muted, #64748b); background: var(--bg-surface, #ffffff); transition: all 0.2s; user-select: none; display: flex; align-items: center; gap: 0.35rem; }
.pill-label:hover { background: var(--bg-color, #f1f5f9); color: var(--color-title, #0f172a); }
[data-theme="dark"] .pill-label { border-color: #27272a; color: #a1a1aa; background: #141416; }
[data-theme="dark"] .pill-label:hover { background: #27272a; color: #ffffff; }

input[value="Facebook"]:checked + .pill-label { background: #1877F2; color: white; border-color: #1877F2; box-shadow: 0 4px 12px rgba(24,119,242,0.3); }
input[value="Instagram"]:checked + .pill-label { background: linear-gradient(45deg, #f09433, #dc2743, #bc1888); color: white; border-color: transparent; box-shadow: 0 4px 12px rgba(220,39,67,0.3); }
input[value="TikTok"]:checked + .pill-label { background: #000000; color: white; border-color: #333333; box-shadow: 0 4px 12px rgba(255,255,255,0.1); }
input[value="LinkedIn"]:checked + .pill-label { background: #0A66C2; color: white; border-color: #0A66C2; box-shadow: 0 4px 12px rgba(10,102,194,0.3); }
input[value="Twitter / X"]:checked + .pill-label { background: #0F1419; color: white; border-color: #333333; }
.pill-input:checked + .pill-label i.ph-plus { display: none; }
.pill-input:checked + .pill-label::before { content: '\e964'; font-family: "Phosphor"; font-size: 0.95rem; }

/* Toggle Group */
.toggle-group { display: flex; background: var(--bg-color, #f1f5f9); padding: 4px; border-radius: 12px; border: 1px solid var(--border-color, #e2e8f0); gap: 4px; margin-bottom: 1.25rem; }
[data-theme="dark"] .toggle-group { background: #141416; border-color: #27272a; }
.toggle-input { display: none; }
.toggle-label { flex: 1; text-align: center; padding: 0.45rem 0.6rem; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); border-radius: 8px; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
.toggle-input:checked + .toggle-label { background: #ffffff; color: var(--color-title, #0f172a); box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
[data-theme="dark"] .toggle-input:checked + .toggle-label { background: #27272a; color: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.3); }

/* Vista previa adaptativa */
.preview-container { display: flex; justify-content: center; margin-bottom: 0.5rem; }
.preview-box { background: var(--bg-color, #f8fafc); border: 2px dashed var(--border-color, #e2e8f0); border-radius: 18px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); position: relative; overflow: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
[data-theme="dark"] .preview-box { background: #141416; border-color: #27272a; }
.preview-box.ratio-9-16 { width: 150px; height: 260px; }
.preview-box.ratio-1-1 { width: 260px; height: 260px; }
.preview-box.ratio-4-5 { width: 210px; height: 260px; }
.preview-box.ratio-16-9 { width: 290px; height: 165px; } 
.preview-box.ratio-auto { width: 100%; height: auto; min-height: 220px; }
.preview-actions { display: flex; justify-content: center; gap: 0.4rem; margin-top: 1rem; flex-wrap: nowrap; }
.preview-actions button { display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 0.35rem; padding: 0.5rem 0.6rem; flex: 1; font-size: 0.74rem; font-weight: 700; color: var(--color-title, #0f172a); background: var(--bg-surface, #ffffff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 12px; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); text-align: center; }
[data-theme="dark"] .preview-actions button { color: #e4e4e7; background: #141416; border-color: #27272a; }
.preview-actions button i { font-size: 1.15rem; color: var(--primary-color); transition: transform 0.2s; }
.preview-actions button:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-color: var(--primary-color); color: var(--primary-color); background: var(--bg-surface, #ffffff); }
[data-theme="dark"] .preview-actions button:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.4); color: #ffffff; background: #27272a; }
.preview-actions button:hover i { transform: scale(1.1); }

/* Post Image Viewer Lightbox Styles */
.preview-interactive-img {
    cursor: zoom-in;
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), filter 0.25s ease;
}
.preview-interactive-img:hover {
    filter: brightness(1.05);
}
.preview-zoom-badge {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(15, 23, 42, 0.78);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 4px 9px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    opacity: 0.85;
    transition: all 0.2s ease;
    pointer-events: none;
    z-index: 10;
}
.preview-box:hover .preview-zoom-badge {
    opacity: 1;
    background: var(--primary-color, #3b82f6);
    border-color: transparent;
    transform: scale(1.04);
}

.post-img-viewer-overlay {
    position: fixed;
    inset: 0;
    z-index: 100050;
    background: rgba(8, 11, 18, 0.94);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    display: flex;
    flex-direction: column;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease, backdrop-filter 0.25s ease;
    user-select: none;
}
.post-img-viewer-overlay.active {
    opacity: 1;
    pointer-events: auto;
}
.piv-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.85rem 1.5rem;
    background: rgba(15, 23, 42, 0.65);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    z-index: 10;
}
.piv-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.piv-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 700;
    background: rgba(59, 130, 246, 0.18);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
}
.piv-counter {
    font-size: 0.8rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.7);
    background: rgba(255, 255, 255, 0.08);
    padding: 3px 10px;
    border-radius: 12px;
}
.piv-controls {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.piv-btn {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #ffffff;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.piv-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}
.piv-btn.piv-btn-close {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
    color: #f87171;
    margin-left: 0.4rem;
}
.piv-btn.piv-btn-close:hover {
    background: #ef4444;
    color: #ffffff;
    border-color: #ef4444;
}
.piv-zoom-level {
    font-size: 0.78rem;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.85);
    min-width: 46px;
    text-align: center;
    font-family: monospace;
}
.piv-divider {
    width: 1px;
    height: 22px;
    background: rgba(255, 255, 255, 0.12);
    margin: 0 4px;
}
.piv-stage {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
    cursor: grab;
}
.piv-stage.dragging {
    cursor: grabbing;
}
.piv-image {
    max-width: 90vw;
    max-height: 80vh;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
    transition: transform 0.12s ease-out;
    transform-origin: center center;
    pointer-events: auto;
}
.piv-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.75);
    border: 1px solid rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    color: #ffffff;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 20;
}
.piv-nav-btn:hover {
    background: var(--primary-color, #3b82f6);
    border-color: transparent;
    transform: translateY(-50%) scale(1.1);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}
.piv-nav-prev { left: 24px; }
.piv-nav-next { right: 24px; }

.piv-thumbnails {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0.75rem 1.5rem;
    background: rgba(15, 23, 42, 0.7);
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    overflow-x: auto;
    max-width: 100%;
    z-index: 10;
}
.piv-thumb-item {
    width: 52px;
    height: 52px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    border: 2px solid transparent;
    opacity: 0.5;
    transition: all 0.2s ease;
    flex-shrink: 0;
    background: #0f172a;
}
.piv-thumb-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.piv-thumb-item:hover {
    opacity: 0.85;
    transform: translateY(-2px);
}
.piv-thumb-item.active {
    opacity: 1;
    border-color: var(--primary-color, #3b82f6);
    box-shadow: 0 0 12px rgba(59, 130, 246, 0.5);
    transform: scale(1.05);
}

/* Native WYSIWYG */
.custom-wysiwyg-wrapper { border: 1px solid var(--border-color, #e2e8f0); border-radius: 16px; overflow: visible; background: var(--bg-surface, #ffffff); transition: all 0.2s; position: relative; }
.custom-wysiwyg-wrapper:focus-within { border-color: var(--primary-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 20%, transparent); }
[data-theme="dark"] .custom-wysiwyg-wrapper { border-color: #27272a; background: #141416; }
.wysiwyg-toolbar { display: flex; align-items: center; gap: 4px; padding: 8px 10px; border-bottom: 1px solid var(--border-color, #e2e8f0); background: var(--bg-color, #f8fafc); border-radius: 16px 16px 0 0; flex-wrap: wrap; }
[data-theme="dark"] .wysiwyg-toolbar { border-bottom-color: #27272a; background: #18181a; }
.wys-btn { background: transparent; border: none; padding: 6px 8px; border-radius: 8px; cursor: pointer; color: var(--text-muted, #64748b); display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 1.1rem; }
.wys-btn:hover { background: rgba(125, 125, 125, 0.1); color: var(--color-title, #0f172a); }
[data-theme="dark"] .wys-btn:hover { background: #27272a; color: #ffffff; }
.wys-divider { width: 1px; height: 20px; background: var(--border-color, #e2e8f0); margin: 0 4px; }
[data-theme="dark"] .wys-divider { background: #27272a; }
.wysiwyg-content { min-height: 380px; padding: 18px; outline: none; font-size: 0.95rem; line-height: 1.7; color: var(--color-title, #0f172a); max-height: 520px; overflow-y: auto; }
[data-theme="dark"] .wysiwyg-content { color: #f1f5f9; }
.wysiwyg-content:empty:before { content: attr(placeholder); color: var(--text-muted, #94a3b8); pointer-events: none; display: block; }
.wysiwyg-content ul { padding-left: 20px; list-style-type: disc; }
.wysiwyg-content ol { padding-left: 20px; list-style-type: decimal; }

.form-control { border: 1px solid var(--border-color, #e2e8f0); background-color: var(--bg-surface, #ffffff); color: var(--color-title, #0f172a); border-radius: 12px; padding: 0.6rem 0.85rem; font-size: 0.88rem; transition: all 0.2s ease; }
.form-control:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 20%, transparent); background: var(--bg-surface, #ffffff); }
[data-theme="dark"] .form-control { border-color: #27272a; background-color: #141416; color: #f1f5f9; }
[data-theme="dark"] .form-control:focus { background: #181818; }
.form-control-sm { padding: 0.45rem 0.75rem; font-size: 0.82rem; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

.comment-subtab.active {
    color: var(--accent-primary) !important;
    border-bottom-color: var(--accent-primary) !important;
}

/* Dynamic rows (Referencias & Variaciones) */
.dyn-row {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    background: var(--bg-color, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 12px;
}
[data-theme="dark"] .dyn-row {
    background: #18181a;
    border-color: #27272a;
}
    padding: 0.75rem;
}
.dyn-row-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.btn-remove-row {
    background: transparent;
    border: none;
    color: #ef4444;
    font-size: 1.15rem;
    cursor: pointer;
    padding: 0.35rem;
    border-radius: 8px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.btn-remove-row:hover {
    background: rgba(239, 68, 68, 0.15);
}

.crm-header-top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

@media (max-width: 992px) {
    .modal-overlay {
        padding: 0 !important;
    }
    .modal-content.crm-layout {
        width: 100vw !important;
        height: 100vh !important;
        max-height: 100vh !important;
        border-radius: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }
    .crm-app-header {
        height: auto !important;
        padding: 0.75rem 1rem !important;
        flex-wrap: wrap !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.65rem !important;
    }
    .crm-header-left {
        flex: 1 !important;
        min-width: 0 !important;
        gap: 0.5rem !important;
        order: 1 !important;
    }
    .crm-header-title {
        font-size: 0.98rem !important;
    }
    .crm-header-subtitle {
        font-size: 0.68rem !important;
    }
    .crm-header-actions {
        flex: 0 0 auto !important;
        order: 2 !important;
        display: flex !important;
        align-items: center !important;
    }
    .pipeline-stages {
        order: 3 !important;
        overflow-x: auto !important;
        flex-wrap: nowrap !important;
        width: 100% !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
        padding: 3px !important;
        justify-content: center !important;
    }
    .pipeline-stages::-webkit-scrollbar {
        display: none !important;
    }
    .pipeline-stage {
        padding: 0.35rem 0.75rem !important;
        font-size: 0.76rem !important;
        white-space: nowrap !important;
        flex-shrink: 0 !important;
    }
    .btn-header-save {
        padding: 0.4rem 0.85rem !important;
        font-size: 0.78rem !important;
    }

    /* Unificar scroll vertical limpio sin trampas de scroll anidadas */
    .crm-body-container {
        display: flex !important;
        flex-direction: column !important;
        flex: 1 !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        -webkit-overflow-scrolling: touch !important;
        height: auto !important;
    }
    .crm-sidebar {
        width: 100% !important;
        border-right: none !important;
        border-bottom: 1px solid #27272a !important;
        overflow: visible !important;
        height: auto !important;
        flex-shrink: 0 !important;
        padding: 1rem !important;
        gap: 0.85rem !important;
    }
    .crm-main {
        width: 100% !important;
        overflow: visible !important;
        height: auto !important;
        flex: none !important;
        display: flex !important;
        flex-direction: column !important;
    }
    .crm-studio-scroll {
        padding: 1rem !important;
        overflow: visible !important;
        height: auto !important;
        flex: none !important;
    }
    .crm-tabs {
        padding: 0 1rem !important;
        gap: 1rem !important;
        scrollbar-width: none !important;
    }
    .crm-tab {
        padding: 0.75rem 0 !important;
        font-size: 0.82rem !important;
    }
    .grid-2 {
        grid-template-columns: 1fr !important;
        gap: 1rem !important;
    }
    .studio-card {
        padding: 1.1rem !important;
        border-radius: 16px !important;
    }
    .wysiwyg-content {
        min-height: 220px !important;
        max-height: 380px !important;
        padding: 12px !important;
    }
    .preview-box.ratio-1-1, 
    .preview-box.ratio-9-16, 
    .preview-box.ratio-4-5, 
    .preview-box.ratio-16-9,
    .preview-box.ratio-auto {
        max-width: 100% !important;
    }
    .qa-items-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>

<!-- Modal Publicación (Modern App Layout) -->
<div class="modal-overlay" id="post-modal">
    <div class="modal-content crm-layout">
        
        <form id="post-form" style="display: contents;">
            <input type="hidden" name="id" id="post-id" value="">
            <input type="hidden" name="month_id" value="<?php echo $monthId; ?>">
            <input type="hidden" name="status" id="post-status" value="Borrador">

            <!-- Top App Bar -->
            <div class="crm-app-header">
                <!-- Left: Title & Status -->
                <div class="crm-header-left">
                    <div class="crm-header-icon">
                        <i class="ph-bold ph-newspaper"></i>
                    </div>
                    <div>
                        <h2 class="crm-header-title" id="post-modal-title">Añadir Publicación</h2>
                        <div class="crm-header-subtitle">Editor de Contenido & Visuales</div>
                    </div>
                    <span id="auto-save-indicator" style="font-size: 0.72rem; color: #10b981; font-weight: 600; opacity: 0; transition: opacity 0.3s; margin-left: 0.5rem; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="ph ph-check"></i> Guardado
                    </span>
                </div>

                <!-- Center: Pipeline Control -->
                <div class="pipeline-stages">
                    <div class="pipeline-stage active" data-status="Borrador" onclick="setPostStatus('Borrador')">Borrador</div>
                    <div class="pipeline-stage" data-status="En Revisión" onclick="setPostStatus('En Revisión')">En Revisión</div>
                    <div class="pipeline-stage" data-status="Aprobado" onclick="setPostStatus('Aprobado')">Aprobado</div>
                    <div class="pipeline-stage" data-status="Publicado" onclick="setPostStatus('Publicado')">Publicado</div>
                    <div class="pipeline-stage" data-status="Archivado" onclick="setPostStatus('Archivado')">Archivado</div>
                </div>

                <!-- Right: Save & Close Buttons -->
                <div class="crm-header-actions">
                    <button type="button" class="btn-header-save" id="btn-save-post" onclick="savePost()" title="Guardar Publicación">
                        <i class="ph-bold ph-floppy-disk"></i> <span id="btn-save-post-text">Guardar Publicación</span>
                    </button>
                    <button type="button" class="btn-header-close" onclick="attemptCloseModal()" title="Cerrar (Esc)">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
            </div>

            <!-- Body Area -->
            <div class="crm-body-container">
                <!-- SIDEBAR: Settings & Metadata -->
                <div class="crm-sidebar">
                    <!-- Concepto / Título -->
                    <div class="crm-sidebar-card">
                        <label class="crm-sidebar-label required"><i class="ph ph-textbox" style="color: var(--primary-color);"></i> Concepto / Título</label>
                        <textarea name="concept" id="post-concept" class="form-control" required placeholder="Ej. Promoción Especial de Verano" style="font-size: 0.92rem; font-weight: 700; resize: none; overflow: hidden; min-height: 48px; line-height: 1.4;" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                    </div>

                    <!-- Descripción para Referencia Gráfica -->
                    <div class="crm-sidebar-card">
                        <label class="crm-sidebar-label"><i class="ph-bold ph-notepad" style="color: var(--primary-color);"></i> Descripción / Idea Referencial</label>
                        <textarea name="design_brief" id="post-brief" class="form-control" placeholder="Describe la idea visual o instrucciones para la Referencia Gráfica..." style="font-size: 0.84rem; resize: vertical; min-height: 72px; line-height: 1.45;" oninput="markDirty(); updateSaveButtonState();"></textarea>
                        <div style="font-size: 0.68rem; color: var(--text-muted); margin-top: 0.25rem;">Instrucciones para la fase de Referencia Visual (el copy final se coloca en el editor de la derecha).</div>
                    </div>

                    <!-- Pilar de Contenido -->
                    <div class="crm-sidebar-card">
                        <label class="crm-sidebar-label required"><i class="ph-bold ph-chart-pie-slice" style="color: var(--primary-color);"></i> Pilar de Contenido</label>
                        <div class="pillar-pill-group">
                            <input type="radio" name="content_pillar" id="pil_edu" value="Educación" class="pillar-input" checked onchange="markDirty(); updateSaveButtonState();">
                            <label for="pil_edu" class="pillar-label pil-edu"><i class="ph ph-graduation-cap"></i> Educación</label>

                            <input type="radio" name="content_pillar" id="pil_ventas" value="Ventas" class="pillar-input" onchange="markDirty(); updateSaveButtonState();">
                            <label for="pil_ventas" class="pillar-label pil-ventas"><i class="ph ph-tag"></i> Ventas</label>

                            <input type="radio" name="content_pillar" id="pil_brand" value="Branding" class="pillar-input" onchange="markDirty(); updateSaveButtonState();">
                            <label for="pil_brand" class="pillar-label pil-brand"><i class="ph ph-sparkle"></i> Branding</label>

                            <input type="radio" name="content_pillar" id="pil_ent" value="Entretenimiento" class="pillar-input" onchange="markDirty(); updateSaveButtonState();">
                            <label for="pil_ent" class="pillar-label pil-ent"><i class="ph ph-mask-happy"></i> Entretenimiento</label>

                            <input type="radio" name="content_pillar" id="pil_com" value="Comunidad" class="pillar-input" onchange="markDirty(); updateSaveButtonState();">
                            <label for="pil_com" class="pillar-label pil-com"><i class="ph ph-users-three"></i> Comunidad</label>

                            <input type="radio" name="content_pillar" id="pil_test" value="Testimonial" class="pillar-input" onchange="markDirty(); updateSaveButtonState();">
                            <label for="pil_test" class="pillar-label pil-test"><i class="ph ph-star"></i> Testimonial</label>
                        </div>
                    </div>

                    <!-- Red Social -->
                    <div class="crm-sidebar-card">
                        <label class="crm-sidebar-label required"><i class="ph ph-share-network" style="color: var(--primary-color);"></i> Plataformas</label>
                        <div class="pill-group">
                            <input type="checkbox" name="platform[]" id="plat1" value="Facebook" class="pill-input">
                            <label for="plat1" class="pill-label"><i class="ph ph-facebook-logo"></i> FB</label>
                            
                            <input type="checkbox" name="platform[]" id="plat2" value="Instagram" class="pill-input">
                            <label for="plat2" class="pill-label"><i class="ph ph-instagram-logo"></i> IG</label>
                            
                            <input type="checkbox" name="platform[]" id="plat3" value="TikTok" class="pill-input">
                            <label for="plat3" class="pill-label"><i class="ph ph-tiktok-logo"></i> TT</label>
                            
                            <input type="checkbox" name="platform[]" id="plat4" value="LinkedIn" class="pill-input">
                            <label for="plat4" class="pill-label"><i class="ph ph-linkedin-logo"></i> IN</label>
                            
                            <input type="checkbox" name="platform[]" id="plat5" value="Twitter / X" class="pill-input">
                            <label for="plat5" class="pill-label"><i class="ph ph-twitter-logo"></i> X</label>
                        </div>
                    </div>

                    <!-- Fechas y Programación -->
                    <div class="crm-sidebar-card">
                        <label class="crm-sidebar-label"><i class="ph ph-calendar" style="color: var(--primary-color);"></i> Fechas y Cronómetro</label>
                        
                        <div>
                            <label style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Fecha Creación (Inicio)</label>
                            <input type="datetime-local" name="post_date" id="post-date" class="form-control form-control-sm" required readonly style="cursor: not-allowed; opacity: 0.85;" onchange="updateSaveButtonState()">
                        </div>

                        <div>
                            <label style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.25rem;">
                                <span>Fecha Entrega (Cronómetro)</span>
                                <?php if (!$isAdmin): ?>
                                    <span style="font-size: 0.62rem; background: rgba(239,68,68,0.15); color: #ef4444; padding: 1px 6px; border-radius: 4px; font-weight: 800;"><i class="ph ph-lock"></i> Solo Admin</span>
                                <?php endif; ?>
                            </label>
                            <input type="date" name="end_date" id="post-end-date" class="form-control form-control-sm" <?php echo !$isAdmin ? 'readonly style="cursor: not-allowed; opacity: 0.85;" title="Solo el administrador puede modificar la fecha de entrega"' : ''; ?> onchange="updateSaveButtonState()">
                        </div>

                        <div class="grid-2" style="margin-top: 0.25rem;">
                            <div>
                                <label style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Periodicidad</label>
                                <select name="periodicity" id="post-periodicity" class="form-control form-control-sm">
                                    <option value="">Única vez</option>
                                    <option value="Diario">Diario</option>
                                    <option value="Semanal">Semanal</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Recordatorio</label>
                                <select name="reminder" id="post-reminder" class="form-control form-control-sm">
                                    <option value="">Ninguno</option>
                                    <option value="1 dia antes">1 día</option>
                                    <option value="1 hora antes">1 h</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MAIN AREA: Studio Canvas -->
                <div class="crm-main">
                    <!-- Tabs Navigation -->
                    <div class="crm-tabs">
                        <div class="crm-tab active" onclick="switchCrmTab(this, 'tab-contenido')">
                            <i class="ph-bold ph-pen-nib"></i> Contenido & QA
                        </div>
                        <div class="crm-tab" onclick="switchCrmTab(this, 'tab-comentarios')">
                            <i class="ph-bold ph-chat-circle-dots"></i> Comentarios
                            <span id="comments-badge" style="display: none; background: #ef4444; color: white; border-radius: 12px; padding: 2px 6px; font-size: 0.6rem; font-weight: bold; line-height: 1;">Nuevo</span>
                        </div>
                    </div>

                    <!-- Content Scrollable Area -->
                    <div class="crm-studio-scroll" style="flex: 1; overflow-y: auto; padding: 1.5rem;">
                        
                        <!-- TAB 1: CONTENIDO & QA -->
                        <div id="tab-contenido" class="crm-tab-pane active">
                            <div class="grid-2" style="grid-template-columns: 1fr 380px; gap: 1.5rem; align-items: stretch;">
                                
                                <!-- Left: Copy Studio & QA Checklist -->
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <div class="studio-card" style="display: flex; flex-direction: column;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                                            <label class="crm-sidebar-label required" style="margin: 0;"><i class="ph-bold ph-text-align-left" style="color: var(--primary-color);"></i> Copy del Post</label>
                                        </div>
                                        <div class="custom-wysiwyg-wrapper" style="flex: 1; display: flex; flex-direction: column;">
                                            <div class="wysiwyg-toolbar">
                                                <button type="button" class="wys-btn" onclick="document.execCommand('undo', false, null)" title="Deshacer"><i class="ph ph-arrow-u-up-left"></i></button>
                                                <button type="button" class="wys-btn" onclick="document.execCommand('redo', false, null)" title="Rehacer"><i class="ph ph-arrow-u-up-right"></i></button>
                                                <div class="wys-divider"></div>
                                                <button type="button" class="wys-btn" onclick="document.execCommand('bold', false, null)" title="Negrita"><i class="ph ph-text-b"></i></button>
                                                <button type="button" class="wys-btn" onclick="document.execCommand('italic', false, null)" title="Cursiva"><i class="ph ph-text-italic"></i></button>
                                                <button type="button" class="wys-btn" onclick="document.execCommand('underline', false, null)" title="Subrayado"><i class="ph ph-text-underline"></i></button>
                                                <div class="wys-divider"></div>
                                                <button type="button" class="wys-btn" onclick="document.execCommand('insertUnorderedList', false, null)" title="Viñetas"><i class="ph ph-list-bullets"></i></button>
                                                <button type="button" class="wys-btn" onclick="document.execCommand('insertOrderedList', false, null)" title="Lista numerada"><i class="ph ph-list-numbers"></i></button>
                                                <div class="wys-divider"></div>
                                                <div style="position: relative; display: inline-block;">
                                                    <button type="button" class="wys-btn" onclick="toggleEmojiPicker()" title="Insertar Emoji"><i class="ph ph-smiley"></i></button>
                                                    <div id="emoji-picker-container" style="display:none; position:absolute; top:40px; left:0; z-index:9999; box-shadow: 0 10px 25px rgba(0,0,0,0.5); border-radius: 12px;">
                                                        <emoji-picker class="dark"></emoji-picker>
                                                    </div>
                                                </div>
                                                <div class="wys-divider"></div>
                                                <button type="button" class="wys-btn ai-btn" onclick="callGemini('corregir')" title="✨ Corregir Ortografía y Estilo" style="color: #a78bfa; background: rgba(167, 139, 250, 0.15); border-radius: 8px; font-weight: 700; font-size: 0.8rem; gap: 4px; padding: 4px 8px;">
                                                    <i class="ph ph-magic-wand"></i> Corregir
                                                </button>
                                                <button type="button" class="wys-btn ai-btn" onclick="callGemini('hashtags')" title="✨ Generar Hashtags" style="color: #60a5fa; background: rgba(96, 165, 250, 0.15); border-radius: 8px; font-weight: 700; font-size: 0.8rem; gap: 4px; padding: 4px 8px;">
                                                    <i class="ph ph-hash"></i> Hashtags
                                                </button>
                                                <button type="button" class="wys-btn ai-btn" onclick="callGemini('generar_desde_imagen')" title="✨ Generar Copy + Emojis + Hashtags analizando la imagen de Post Terminado" style="color: #10b981; background: rgba(16, 185, 129, 0.18); border-radius: 8px; font-weight: 700; font-size: 0.8rem; gap: 4px; padding: 4px 8px;">
                                                    <i class="ph ph-sparkle"></i> Copy con Imagen
                                                </button>
                                                <button type="button" class="wys-btn ai-btn" onclick="callGemini('generar')" title="✨ Escribir Post con IA desde texto" style="color: #fbbf24; background: rgba(251, 191, 36, 0.15); border-radius: 8px; font-weight: 700; font-size: 0.8rem; gap: 4px; padding: 4px 8px;">
                                                    <i class="ph ph-pencil-simple-line"></i> IA Texto
                                                </button>
                                            </div>
                                            <div id="post-copy-editable" class="wysiwyg-content" contenteditable="true" placeholder="Escribe el texto de la publicación..."></div>
                                            <textarea name="copy_text" id="post-copy" style="display:none;"></textarea>
                                        </div>
                                        <div style="text-align: right; margin-top: 0.65rem; font-size: 0.74rem; font-weight: 600; color: var(--text-muted);">
                                            <span id="char-count" style="color: #f1f5f9; font-weight: 800;">0</span> caracteres
                                        </div>
                                    </div>

                                    <!-- Control de Calidad QA Checklist -->
                                    <div class="qa-checklist-card">
                                        <div class="qa-header">
                                            <div class="qa-title">
                                                <i class="ph-bold ph-shield-check" style="color: #10b981;"></i>
                                                <span>Control de Calidad (QA Pre-Publicación)</span>
                                            </div>
                                            <div class="qa-counter-badge qa-badge-pending" id="qa-counter-badge">0/4 Pendiente</div>
                                        </div>
                                        <div class="qa-progress-bar-bg">
                                            <div class="qa-progress-bar-fill" id="qa-progress-bar-fill" style="width: 0%; background: #6366f1;"></div>
                                        </div>
                                        <div class="qa-items-grid">
                                            <label class="qa-item">
                                                <input type="checkbox" name="qa_checklist[spelling]" id="qa_spelling" class="qa-checkbox" onchange="updateQAUi(); markDirty();">
                                                <div class="qa-item-content">
                                                    <div class="qa-item-title"><i class="ph-bold ph-spell-check" style="color: #60a5fa;"></i> Ortografía & Hashtags</div>
                                                    <div class="qa-item-desc">Texto revisado, sin faltas ortográficas y hashtags optimizados.</div>
                                                </div>
                                            </label>

                                            <label class="qa-item">
                                                <input type="checkbox" name="qa_checklist[brand]" id="qa_brand" class="qa-checkbox" onchange="updateQAUi(); markDirty();">
                                                <div class="qa-item-content">
                                                    <div class="qa-item-title"><i class="ph-bold ph-palette" style="color: #c084fc;"></i> Logo & Identidad</div>
                                                    <div class="qa-item-desc">Paleta corporativa y logotipo oficial bien integrado.</div>
                                                </div>
                                            </label>

                                            <label class="qa-item">
                                                <input type="checkbox" name="qa_checklist[format]" id="qa_format" class="qa-checkbox" onchange="updateQAUi(); markDirty();">
                                                <div class="qa-item-content">
                                                    <div class="qa-item-title"><i class="ph-bold ph-aspect-ratio" style="color: #fbbf24;"></i> Formato & Resolución</div>
                                                    <div class="qa-item-desc">Dimensiones adecuadas (1:1, 4:5, 9:16) y visuales nítidos.</div>
                                                </div>
                                            </label>

                                            <label class="qa-item">
                                                <input type="checkbox" name="qa_checklist[cta]" id="qa_cta" class="qa-checkbox" onchange="updateQAUi(); markDirty();">
                                                <div class="qa-item-content">
                                                    <div class="qa-item-title"><i class="ph-bold ph-link-simple" style="color: #34d399;"></i> Call to Action & Links</div>
                                                    <div class="qa-item-desc">Llamado a la acción claro y enlaces verificados.</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Visual Asset Studio -->
                                <div class="studio-card" style="display: flex; flex-direction: column;">
                                    <div class="toggle-group">
                                        <input type="radio" name="post_type" id="pt_ref" value="Referencia Visual" class="toggle-input" checked onchange="updateVideoPreview()">
                                        <label for="pt_ref" class="toggle-label">Ref. Visual</label>
                                        
                                        <input type="radio" name="post_type" id="pt_post" value="Post Terminado" class="toggle-input" onchange="updateVideoPreview()">
                                        <label for="pt_post" class="toggle-label">Terminado</label>
                                    </div>

                                    <div class="preview-container" id="preview-container">
                                        <div class="preview-box ratio-auto" id="preview-box" ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                                            <i class="ph ph-image" id="preview-icon" style="font-size: 3rem; opacity: 0.3; color: white;"></i>
                                        </div>
                                    </div>
                                    <div class="preview-actions">
                                        <button type="button" id="btn-ver-recurso" onclick="openPostImageViewer()" title="Ver imagen en tamaño completo"><i class="ph ph-eye"></i> Ver</button>
                                        <button type="button" onclick="document.getElementById('post-main-image-upload').click()"><i class="ph ph-image-square"></i> Subir</button>
                                        <button type="button" id="btn-dibujar" onclick="openPaintEditor()"><i class="ph ph-paint-brush"></i> Dibujar</button>
                                        <button type="button" id="btn-eliminar-recurso" onclick="clearActiveTabImage();"><i class="ph ph-trash"></i> Eliminar</button>
                                    </div>
                                    <input type="file" id="post-main-image-upload" style="display:none" accept="image/*,video/mp4" multiple onchange="uploadMainImage(this)">
                                    <input type="hidden" name="image_link" id="post-image-link">
                                    <input type="hidden" name="reference_image_link" id="post-reference-link">
                                    <input type="hidden" name="paint_data" id="post-paint-data">
                                    <input type="hidden" name="drive_images" id="post-drive">

                                    <div id="video-url-container" style="border-top: 1px dashed #27272a; padding-top: 1.25rem; margin-top: 1.25rem; text-align: left;">
                                        <div id="video-url-input-group">
                                            <label style="font-size: 0.72rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.4rem;"><i class="ph ph-link"></i> Enlace Externo</label>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <input type="text" id="video-url-input" class="form-control form-control-sm" placeholder="Pegar URL aquí..." oninput="handleVideoUrlInput()">
                                                <button type="button" class="btn btn-outline" style="padding: 0 0.6rem; border-radius: 10px; border-color: #27272a;" onclick="document.getElementById('video-url-input').value=''; handleVideoUrlInput();">
                                                    <i class="ph ph-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div id="download-btn-group" style="display: none; margin-top: 0.75rem;">
                                            <button type="button" class="btn btn-primary" style="width: 100%; display: flex; justify-content: center; align-items: center; gap: 0.5rem; padding: 0.75rem; border-radius: 12px; font-weight: 700; background: var(--color-btn-bg, var(--primary-color)); border: none; box-shadow: 0 4px 14px color-mix(in srgb, var(--color-btn-bg, var(--primary-color)) 30%, transparent); color: var(--color-btn-text, #ffffff);" onclick="downloadActiveResource()"><i class="ph ph-download-simple" style="font-size: 1.2rem;"></i> Descargar Archivo</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: COMENTARIOS -->
                        <div id="tab-comentarios" class="crm-tab-pane">
                            <div class="studio-card" style="min-height: 440px;">
                                <!-- Sub-tabs -->
                                <div style="display:flex; gap:0; border-bottom: 1px solid #27272a; margin-bottom:1.25rem;">
                                    <button type="button" class="comment-subtab active" id="subtab-contenido" onclick="switchCommentSubtab('contenido')" style="flex:1; padding:0.75rem 1rem; border:none; background:transparent; font-family:inherit; font-size:0.85rem; font-weight:700; cursor:pointer; color:var(--text-muted); border-bottom:2px solid transparent; margin-bottom:-1px; transition: all 0.2s; display:flex; align-items:center; justify-content:center; gap:6px;">
                                        <i class="ph ph-text-aa"></i> Contenido <span id="count-contenido" style="background:var(--accent-primary); color:white; border-radius:10px; padding:0.1rem 0.5rem; font-size:0.7rem; min-width:18px; text-align:center;"></span>
                                    </button>
                                    <button type="button" class="comment-subtab" id="subtab-diseno" onclick="switchCommentSubtab('diseno')" style="flex:1; padding:0.75rem 1rem; border:none; background:transparent; font-family:inherit; font-size:0.85rem; font-weight:700; cursor:pointer; color:var(--text-muted); border-bottom:2px solid transparent; margin-bottom:-1px; transition: all 0.2s; display:flex; align-items:center; justify-content:center; gap:6px;">
                                        <i class="ph ph-paint-brush"></i> Fase de Diseño <span id="count-diseno" style="background:var(--accent-primary); color:white; border-radius:10px; padding:0.1rem 0.5rem; font-size:0.7rem; min-width:18px; text-align:center;"></span>
                                    </button>
                                </div>
                                <div id="comments-container-contenido">
                                    <!-- Comentarios de Contenido -->
                                </div>
                                <div id="comments-container-diseno" style="display:none;">
                                    <!-- Comentarios de Fase de Diseño -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
// JS para las pestañas y el pipeline
function switchCrmTab(tabElement, paneId) {
    // Quitar active a todos los tabs
    document.querySelectorAll('.crm-tab').forEach(t => t.classList.remove('active'));
    // Quitar active a todos los paneles
    document.querySelectorAll('.crm-tab-pane').forEach(p => p.classList.remove('active'));
    
    // Activar el seleccionado
    tabElement.classList.add('active');
    document.getElementById(paneId).classList.add('active');
}

function switchCommentSubtab(subtab) {
    const btnContenido = document.getElementById('subtab-contenido');
    const btnDiseno = document.getElementById('subtab-diseno');
    const containerContenido = document.getElementById('comments-container-contenido');
    const containerDiseno = document.getElementById('comments-container-diseno');

    if (btnContenido && btnDiseno && containerContenido && containerDiseno) {
        if (subtab === 'contenido') {
            btnContenido.classList.add('active');
            btnDiseno.classList.remove('active');
            containerContenido.style.display = 'block';
            containerDiseno.style.display = 'none';
        } else {
            btnDiseno.classList.add('active');
            btnContenido.classList.remove('active');
            containerDiseno.style.display = 'block';
            containerContenido.style.display = 'none';
        }
    }
}

function setPostStatus(status) {
    document.getElementById('post-status').value = status;
    updatePipelineUI();
    markDirty();
    updateSaveButtonState();
}

function updatePipelineUI() {
    const status = document.getElementById('post-status').value || 'Borrador';
    document.querySelectorAll('.pipeline-stage').forEach(el => {
        el.classList.remove('active');
        if (el.getAttribute('data-status') === status) {
            el.classList.add('active');
        }
    });
}
</script>

<!-- Modal Confirmar Eliminación -->
<div class="modal-overlay" id="deletePostConfirmModal" style="z-index: 1070;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="justify-content: center; border-bottom: none; padding-bottom: 0; margin-top: 1rem;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(239, 68, 68, 0.1); color: var(--danger-color); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto;">
                <i class="ph ph-warning"></i>
            </div>
        </div>
        <div class="modal-body" style="text-align: center; padding-top: 1rem;">
            <h3 style="margin-bottom: 0.5rem; color: var(--color-title); font-size: 1.25rem; font-weight: 600;">¿Eliminar Publicación?</h3>
            <p style="margin-bottom: 0;">Esta acción no se puede deshacer.</p>
            <input type="hidden" id="delete-post-id">
        </div>
        <div class="modal-footer" style="justify-content: center; border-top: none; padding-top: 0.5rem; gap: 1rem;">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('deletePostConfirmModal').classList.remove('active')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="confirmDeletePost()" style="background-color: var(--danger-color); border-color: var(--danger-color);">
                Sí, eliminar
            </button>
        </div>
    </div>
</div>

<!-- Modal Visor de Imagen (Lightbox para Post) -->
<div class="post-img-viewer-overlay" id="post-image-viewer-modal" onclick="pivHandleOverlayClick(event)">
    <div class="piv-topbar" onclick="event.stopPropagation()">
        <div class="piv-info">
            <div class="piv-badge" id="piv-type-badge"><i class="ph ph-image"></i> Referencia Visual</div>
            <span class="piv-counter" id="piv-counter" style="display: none;">1 / 1</span>
        </div>
        <div class="piv-controls">
            <button type="button" class="piv-btn" onclick="pivZoomOut()" title="Alejar (-)"><i class="ph ph-magnifying-glass-minus"></i></button>
            <span class="piv-zoom-level" id="piv-zoom-val">100%</span>
            <button type="button" class="piv-btn" onclick="pivZoomIn()" title="Acercar (+)"><i class="ph ph-magnifying-glass-plus"></i></button>
            <button type="button" class="piv-btn" onclick="pivResetTransform()" title="Restablecer vista"><i class="ph ph-arrows-counter-clockwise"></i></button>
            <div class="piv-divider"></div>
            <button type="button" class="piv-btn" onclick="pivRotate()" title="Girar 90°"><i class="ph ph-arrow-clockwise"></i></button>
            <button type="button" class="piv-btn" onclick="pivDownload()" title="Descargar"><i class="ph ph-download-simple"></i></button>
            <button type="button" class="piv-btn" onclick="pivOpenInNewTab()" title="Abrir en pestaña nueva"><i class="ph ph-arrow-square-out"></i></button>
            <div class="piv-divider"></div>
            <button type="button" class="piv-btn piv-btn-close" onclick="closePostImageViewer()" title="Cerrar (Esc)"><i class="ph ph-x"></i></button>
        </div>
    </div>

    <div class="piv-stage" id="piv-stage" onwheel="pivHandleWheel(event)" onmousedown="pivStartDrag(event)">
        <img src="" id="piv-main-image" class="piv-image" draggable="false" alt="Visualización" ondblclick="pivToggleZoom(event)">
    </div>

    <button type="button" class="piv-nav-btn piv-nav-prev" id="piv-prev-btn" onclick="event.stopPropagation(); pivPrevImage();" title="Anterior (←)"><i class="ph ph-caret-left"></i></button>
    <button type="button" class="piv-nav-btn piv-nav-next" id="piv-next-btn" onclick="event.stopPropagation(); pivNextImage();" title="Siguiente (→)"><i class="ph ph-caret-right"></i></button>

    <div class="piv-thumbnails" id="piv-thumbnails" style="display: none;" onclick="event.stopPropagation()"></div>
</div>

<!-- Modal Genérico de Confirmación -->
<div class="modal-overlay" id="genericConfirmModal" style="z-index: 1070;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="justify-content: center; border-bottom: none; padding-bottom: 0; margin-top: 1rem;">
            <div id="generic-confirm-icon-bg" style="width: 64px; height: 64px; border-radius: 50%; background: rgba(147, 51, 234, 0.1); color: #9333ea; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto;">
                <i id="generic-confirm-icon" class="ph ph-archive"></i>
            </div>
        </div>
        <div class="modal-body" style="text-align: center; padding-top: 1rem;">
            <h3 id="generic-confirm-title" style="margin-bottom: 0.5rem; color: var(--color-title); font-size: 1.25rem; font-weight: 600;">¿Confirmar Acción?</h3>
            <p id="generic-confirm-text" style="margin-bottom: 0;">¿Estás seguro?</p>
        </div>
        <div class="modal-footer" style="justify-content: center; border-top: none; padding-top: 0.5rem; gap: 1rem;">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('genericConfirmModal').classList.remove('active')">Cancelar</button>
            <button type="button" class="btn btn-primary" id="generic-confirm-btn" style="background-color: #9333ea; border-color: #9333ea;">
                Aceptar
            </button>
        </div>
    </div>
</div>

<script>
function showGenericConfirm(title, text, icon, color, confirmCallback) {
    document.getElementById('generic-confirm-title').innerText = title;
    document.getElementById('generic-confirm-text').innerText = text;
    document.getElementById('generic-confirm-icon').className = 'ph ' + icon;
    
    document.getElementById('generic-confirm-icon-bg').style.color = color;
    document.getElementById('generic-confirm-icon-bg').style.background = color + '22';
    
    document.getElementById('generic-confirm-btn').style.backgroundColor = color;
    document.getElementById('generic-confirm-btn').style.borderColor = color;
    
    const btn = document.getElementById('generic-confirm-btn');
    btn.onclick = function() {
        document.getElementById('genericConfirmModal').classList.remove('active');
        confirmCallback();
    };
    
    document.getElementById('genericConfirmModal').classList.add('active');
}
</script>

<!-- Modal Compartir Post Individual -->
<div class="modal-overlay" id="shareSinglePostModal" style="z-index: 1075;">
    <div class="modal-content" style="max-width: 480px; padding: 0; overflow: hidden; border-radius: 20px; background: white;">
        <div class="modal-header" style="background: #f8fafc; padding: 1.5rem 2rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.3rem; display: flex; align-items: center; gap: 0.6rem; color: #1e293b;">
                <i class="ph ph-share-network" style="color: #0d945a;"></i> Compartir Publicación
            </h3>
            <button type="button" class="btn-icon" onclick="document.getElementById('shareSinglePostModal').classList.remove('active')" style="background: white; border: 1px solid #e2e8f0; border-radius: 50%; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); cursor: pointer;"><i class="ph ph-x" style="color: #64748b;"></i></button>
        </div>
        <div class="modal-body" style="padding: 2rem;">
            <p style="font-size: 0.95rem; color: #64748b; margin-bottom: 1.5rem; line-height: 1.5;">
                El siguiente enlace es de acceso libre y caducará en 7 días.
            </p>

            <input type="text" id="single-post-share-url" style="display:none;">

            <button type="button" class="btn btn-publish" style="width: 100%; justify-content: center; padding: 1rem; font-size: 1.05rem; background: #0d945a; color: white; border: none; border-radius: 12px; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-weight: 600;" onclick="copySingleShareUrl()">
                <i class="ph ph-copy"></i> Copiar Enlace
            </button>
            
            <p id="single-share-feedback" style="color: #0d945a; font-size: 0.85rem; margin-top: 1rem; opacity: 0; transition: opacity 0.3s; margin-bottom: 0; text-align: center; font-weight: 600;">¡Enlace copiado al portapapeles!</p>
        </div>
    </div>
</div>

<script>
function openShareSinglePostModal(relativeUrl) {
    const fullUrl = window.location.origin + window.location.pathname.replace('index.php', '') + relativeUrl;
    document.getElementById('single-post-share-url').value = fullUrl;
    document.getElementById('single-share-feedback').style.opacity = '0';
    document.getElementById('shareSinglePostModal').classList.add('active');
}

function copySingleShareUrl() {
    const urlInput = document.getElementById('single-post-share-url');
    urlInput.select();
    urlInput.setSelectionRange(0, 99999); // Mobile
    navigator.clipboard.writeText(urlInput.value).then(() => {
        document.getElementById('single-share-feedback').style.opacity = '1';
        setTimeout(() => {
            document.getElementById('single-share-feedback').style.opacity = '0';
        }, 3000);
    });
}
</script>

<script>
let isFormDirty = false;
let autoSaveTimer = null;

function markDirty() {
    isFormDirty = true;
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('post-form').addEventListener('input', markDirty);
    document.getElementById('post-form').addEventListener('change', markDirty);
});

function attemptCloseModal() {
    if (isFormDirty) {
        if (confirm("¿Estás seguro de cerrar? Tienes cambios sin guardar.")) {
            document.getElementById('post-modal').classList.remove('active');
            clearInterval(autoSaveTimer);
        }
    } else {
        document.getElementById('post-modal').classList.remove('active');
        clearInterval(autoSaveTimer);
    }
}

function updateQAUi() {
    const chkSpelling = document.getElementById('qa_spelling');
    const chkBrand = document.getElementById('qa_brand');
    const chkFormat = document.getElementById('qa_format');
    const chkCta = document.getElementById('qa_cta');
    
    let count = 0;
    if (chkSpelling && chkSpelling.checked) count++;
    if (chkBrand && chkBrand.checked) count++;
    if (chkFormat && chkFormat.checked) count++;
    if (chkCta && chkCta.checked) count++;
    
    const pct = (count / 4) * 100;
    const barEl = document.getElementById('qa-progress-bar-fill');
    const badgeEl = document.getElementById('qa-counter-badge');
    
    if (barEl) {
        barEl.style.width = pct + '%';
        if (count === 4) {
            barEl.style.background = 'linear-gradient(90deg, #10b981, #059669)';
        } else if (count >= 2) {
            barEl.style.background = 'linear-gradient(90deg, #f59e0b, #d97706)';
        } else {
            barEl.style.background = 'linear-gradient(90deg, #6366f1, #4f46e5)';
        }
    }
    
    if (badgeEl) {
        if (count === 4) {
            badgeEl.className = 'qa-counter-badge qa-badge-complete';
            badgeEl.innerHTML = '<i class="ph-bold ph-check-circle"></i> 4/4 Verificado';
        } else if (count > 0) {
            badgeEl.className = 'qa-counter-badge qa-badge-progress';
            badgeEl.innerHTML = `<i class="ph-bold ph-hourglass-high"></i> ${count}/4 En Progreso`;
        } else {
            badgeEl.className = 'qa-counter-badge qa-badge-pending';
            badgeEl.innerHTML = '0/4 Pendiente';
        }
    }
}

function openPostModal() {
    // 1. Stop any running auto-save from a previous session
    clearInterval(autoSaveTimer);
    
    // 2. Reset the HTML form (checkboxes, selects, text inputs)
    document.getElementById('post-form').reset();
    
    // 3. Force-clear hidden fields that form.reset() may not reliably clear
    document.getElementById('post-id').value = '';
    document.getElementById('post-status').value = 'Borrador';
    document.getElementById('post-image-link').value = '';
    document.getElementById('post-reference-link').value = '';
    document.getElementById('post-paint-data').value = '';
    
    // Auto-set creation datetime to now
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    const nowIso = now.toISOString().slice(0, 16);
    if(document.getElementById('post-date')) document.getElementById('post-date').value = nowIso;
    
    // 4. Clear WYSIWYG editor & brief
    const wysiwygEditable = document.getElementById('post-copy-editable');
    const hiddenTextarea = document.getElementById('post-copy');
    if (wysiwygEditable) wysiwygEditable.innerHTML = '';
    if (hiddenTextarea) hiddenTextarea.value = '';
    if (document.getElementById('post-brief')) document.getElementById('post-brief').value = '';
    
    // 5. Clear dynamic containers
    if(document.getElementById('refs-container')) document.getElementById('refs-container').innerHTML = '';
    if(document.getElementById('vars-container')) document.getElementById('vars-container').innerHTML = '';
    
    // 6. Clear video/URL inputs
    document.getElementById('video-url-input').value = '';
    if(document.getElementById('post-drive')) document.getElementById('post-drive').value = '';
    if(document.getElementById('post-end-date')) document.getElementById('post-end-date').value = '<?php echo htmlspecialchars($monthData['due_date'] ?? date('Y-m-t')); ?>';
    
    // 7. Clear comments
    const commentsContainer = document.getElementById('comments-container');
    if (commentsContainer) {
        commentsContainer.innerHTML = '<p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 2rem 0;">No hay comentarios para esta publicación.</p>';
    }
    
    // 8. Uncheck all platform checkboxes explicitly
    document.querySelectorAll('input[name="platform[]"]').forEach(chk => chk.checked = false);
    
    // 9. Uncheck all format checkboxes explicitly
    document.querySelectorAll('input[name="formats[]"]').forEach(chk => chk.checked = false);
    
    // 10. Reset post type to default
    const refRadio = document.getElementById('pt_ref');
    if (refRadio) refRadio.checked = true;
    
    // 11. Reset Content Pillar & QA
    const defPillar = document.getElementById('pil_edu');
    if (defPillar) defPillar.checked = true;
    document.querySelectorAll('.qa-checkbox').forEach(chk => chk.checked = false);
    updateQAUi();
    
    document.getElementById('post-modal-title').innerText = 'Añadir Publicación';
    
    // Resetear pestañas y pipeline
    switchCrmTab(document.querySelector('.crm-tab:first-child'), 'tab-contenido');
    updatePipelineUI();
    
    document.getElementById('post-modal').classList.add('active');
    updateSaveButtonState();
    updateVideoPreview();
    updateCopyPreview();
    if(document.getElementById('post-drive') && typeof handleDriveLink === 'function') handleDriveLink();
    isFormDirty = false;
    startAutoSave();
}

function handleVideoUrlInput() {
    const url = document.getElementById('video-url-input').value;
    const isRef = document.querySelector('input[name="post_type"]:checked').value === 'Referencia Visual';
    if (isRef) {
        document.getElementById('post-reference-link').value = url;
    } else {
        document.getElementById('post-image-link').value = url;
        if (url.trim() !== '') {
            document.getElementById('post-status').value = 'Publicado';
            if (typeof updatePipelineUI === 'function') updatePipelineUI();
            if (typeof updateSaveButtonState === 'function') updateSaveButtonState();
        }
    }
    updateVideoPreview();
}

function clearActiveTabImage(indexToRemove = -1) {
    const isRef = document.querySelector('input[name="post_type"]:checked').value === 'Referencia Visual';
    const inputEl = isRef ? document.getElementById('post-reference-link') : document.getElementById('post-image-link');
    
    let urlsToDelete = [];
    if (indexToRemove === -1) {
        if (inputEl.value) {
            try {
                let list = JSON.parse(inputEl.value);
                urlsToDelete = Array.isArray(list) ? list : [inputEl.value];
            } catch(e) {
                urlsToDelete = [inputEl.value];
            }
        }
        inputEl.value = '';
    } else {
        try {
            let list = JSON.parse(inputEl.value);
            if (Array.isArray(list)) {
                urlsToDelete = [list[indexToRemove]];
                list.splice(indexToRemove, 1);
                inputEl.value = list.length > 0 ? JSON.stringify(list) : '';
            } else {
                urlsToDelete = [inputEl.value];
                inputEl.value = '';
            }
        } catch(e) {
            urlsToDelete = [inputEl.value];
            inputEl.value = '';
        }
    }
    
    if (urlsToDelete.length > 0) {
        const fd = new FormData();
        urlsToDelete.forEach(u => fd.append('url[]', u));
        fd.append('month_id', document.querySelector('input[name="month_id"]').value);
        fd.append('post_type', isRef ? 'Referencia Visual' : 'Post Terminado');
        
        fetch('modules/month_board/ajax_delete_file.php', { method: 'POST', body: fd })
            .catch(e => console.error('Error deleting file:', e));
    }
    
    updateVideoPreview();
    markDirty();
    savePost(true); // Save the post immediately so the UI removal persists
}

function updateVideoPreview() {
    const box = document.getElementById('preview-box');
    
    // Type check to differentiate reference vs final post
    let pTypeObj = document.querySelector('input[name="post_type"]:checked');
    let pType = pTypeObj ? pTypeObj.value : 'Post Terminado';
    let isRef = (pType === 'Referencia Visual');
    
    let overlayHtml = '';
    
    let refBadge = isRef ? '<div style="position: absolute; top: 10px; left: 10px; background: rgba(245, 158, 11, 0.9); color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; display: flex; align-items: center; gap: 4px; z-index: 10;"><i class="ph ph-lightbulb"></i> Referencia</div>' : '';
    
    let val = isRef ? document.getElementById('post-reference-link').value : document.getElementById('post-image-link').value;
    let mediaList = [];
    try {
        mediaList = JSON.parse(val);
        if(!Array.isArray(mediaList)) mediaList = val ? [val] : [];
    } catch(e) {
        mediaList = val ? [val] : [];
    }
    
    // Sync URL input
    document.getElementById('video-url-input').value = mediaList.length === 1 ? mediaList[0] : (mediaList.length > 1 ? val : '');
    
    // Toggle buttons based on isRef
    if (isRef) {
        document.getElementById('video-url-input-group').style.display = 'block';
        document.getElementById('download-btn-group').style.display = 'none';
        const btnD = document.getElementById('btn-dibujar');
        if (btnD) btnD.style.display = 'inline-flex';
    } else {
        document.getElementById('video-url-input-group').style.display = 'none';
        document.getElementById('download-btn-group').style.display = 'block';
        const btnD = document.getElementById('btn-dibujar');
        if (btnD) btnD.style.display = 'none';
    }
    
    // Si no hay URL
    if (mediaList.length === 0) {
        box.className = 'preview-box ratio-auto';
        box.style.width = ''; box.style.height = ''; box.style.maxWidth = '';
        box.style.display = 'flex'; box.style.padding = '0'; box.style.gridTemplateColumns = ''; box.style.gap = '';
        box.innerHTML = `${refBadge}<i class="ph ph-image" id="preview-icon" style="font-size: 3rem; opacity: 0.3; color: white;"></i>${overlayHtml}`;
        return;
    }

    // Si son múltiples imágenes
    if (mediaList.length > 1) {
        box.className = 'preview-box';
        box.style.width = '100%';
        box.style.height = '100%';
        box.style.maxWidth = 'none';
        box.style.padding = '0';
        box.style.display = 'flex';
        box.style.alignItems = 'center';
        box.style.justifyContent = 'center';
        box.style.overflow = 'hidden';
        box.style.position = 'relative';
        
        const carouselId = 'edit-carousel-' + Math.random().toString(36).substr(2, 9);
        let html = refBadge + `<div class="carousel-track" id="${carouselId}-track" style="display:flex; overflow-x:auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; width:100%; height:100%; scrollbar-width: none;">`;
        
        mediaList.forEach((url, i) => {
            html += `<div class="sortable-item" style="flex: 0 0 100%; scroll-snap-align: center; position:relative; width: 100%; height: 100%; overflow: hidden; background: #0f172a; cursor: grab;">
                        <img src="${url}" class="preview-interactive-img" onclick="openPostImageViewer(${i})" style="width:100%; height:100%; object-fit:contain;" title="Click para ver en el visor de imágenes">
                        <button type="button" onclick="event.stopPropagation(); clearActiveTabImage(${i});" style="position:absolute; top:10px; right:10px; background:rgba(239,68,68,0.9); color:white; border:none; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:12px; z-index: 20;" title="Eliminar"><i class="ph ph-x"></i></button>
                        <button type="button" onclick="event.stopPropagation(); openPostImageViewer(${i});" style="position:absolute; top:10px; left:10px; background:rgba(15,23,42,0.8); color:white; border:1px solid rgba(255,255,255,0.2); border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:14px; z-index: 20;" title="Ver imagen en tamaño completo"><i class="ph ph-eye"></i></button>
                        <div style="position:absolute; bottom:10px; right:10px; background:rgba(0,0,0,0.6); color:white; font-size:0.7rem; padding:3px 8px; border-radius:12px; pointer-events:none;">${i + 1} / ${mediaList.length}</div>
                     </div>`;
        });
        
        html += `</div>
        <button type="button" onclick="event.stopPropagation(); document.getElementById('${carouselId}-track').scrollBy({left: -300, behavior: 'smooth'});" style="position:absolute; top:50%; left:5px; transform:translateY(-50%); background:rgba(255,255,255,0.8); color:#333; border:none; border-radius:50%; width:30px; height:30px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(0,0,0,0.2); z-index:10;"><i class="ph ph-caret-left" style="font-weight:bold;"></i></button>
        <button type="button" onclick="event.stopPropagation(); document.getElementById('${carouselId}-track').scrollBy({left: 300, behavior: 'smooth'});" style="position:absolute; top:50%; right:5px; transform:translateY(-50%); background:rgba(255,255,255,0.8); color:#333; border:none; border-radius:50%; width:30px; height:30px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(0,0,0,0.2); z-index:10;"><i class="ph ph-caret-right" style="font-weight:bold;"></i></button>
        <style>#${carouselId}-track::-webkit-scrollbar { display: none; }</style>`;
        
        box.innerHTML = html + overlayHtml;
        
        if (typeof Sortable !== 'undefined') {
            new Sortable(box, {
                animation: 150,
                draggable: '.sortable-item',
                onEnd: function () {
                    const newUrls = [];
                    box.querySelectorAll('.sortable-item img').forEach(img => {
                        newUrls.push(img.getAttribute('src'));
                    });
                    
                    const isRef = document.querySelector('input[name="post_type"]:checked').value === 'Referencia Visual';
                    const inputEl = isRef ? document.getElementById('post-reference-link') : document.getElementById('post-image-link');
                    
                    inputEl.value = JSON.stringify(newUrls);
                    updateVideoPreview(); // Re-render to update the clearActiveTabImage indices
                    markDirty();
                }
            });
        }
        
        return;
    }

    let url = mediaList[0];
    box.style.display = 'flex'; box.style.padding = '0'; box.style.gridTemplateColumns = ''; box.style.gap = '';

    const isDriveImage = url.match(/drive\.google\.com\/(uc\?export=view&id=|thumbnail\?id=)([\w-]+)/i);
    const isVideoLink = !isDriveImage && url.match(/(youtu\.be|youtube\.com|tiktok\.com|\.mp4|drive\.google\.com|instagram\.com|facebook\.com|fb\.watch|pinterest\.com|pin\.it)/i);

    if (isVideoLink) {
        if (isRef) {
            box.className = 'preview-box';
            box.style.width = '100%';
            box.style.height = '500px';
            box.style.maxWidth = 'none';
        } else {
            box.className = 'preview-box ratio-9-16'; // Force Reels ratio
            box.style.width = ''; box.style.height = ''; box.style.maxWidth = '';
        }
        
        if (url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})/)) {
            const ytMatch = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})/);
            box.innerHTML = `${refBadge}<iframe width="100%" height="100%" src="https://www.youtube.com/embed/${ytMatch[1]}?autoplay=1&mute=1" frameborder="0" allowfullscreen style="border:none; border-radius:12px;"></iframe>${overlayHtml}`;
        } else if (url.toLowerCase().endsWith('.mp4')) {
            // Override container constraints for mp4
            box.className = 'preview-box';
            box.style.width = '100%';
            box.style.height = 'auto';
            box.style.maxWidth = 'none';
            box.innerHTML = `${refBadge}<video controls playsinline style="width: 100%; max-height: 600px; object-fit: contain; border-radius: 12px; background: #000; display: block;"><source src="${url}" type="video/mp4"></video>${overlayHtml}`;
        } else if (url.match(/tiktok\.com\/(?:@[\w.-]+\/video\/|v\/)?(\d+)/)) {
            const tiktokMatch = url.match(/tiktok\.com\/(?:@[\w.-]+\/video\/|v\/)?(\d+)/);
            box.innerHTML = `${refBadge}<iframe width="100%" height="100%" src="https://www.tiktok.com/embed/v2/${tiktokMatch[1]}" frameborder="0" allowfullscreen style="border:none; border-radius:12px;"></iframe>${overlayHtml}`;
        } else if (url.match(/instagram\.com\/(?:p|reel)\/([\w-]+)/)) {
            const igMatch = url.match(/instagram\.com\/(?:p|reel)\/([\w-]+)/);
            box.innerHTML = `${refBadge}<iframe width="100%" height="100%" src="https://www.instagram.com/p/${igMatch[1]}/embed/captioned" frameborder="0" scrolling="no" allowtransparency="true" style="border:none; border-radius:12px; background:white;"></iframe>${overlayHtml}`;
        } else if (url.match(/facebook\.com|fb\.watch/i)) {
            // Facebook: Meta blocks iframe embedding ?" show a styled preview card
            const shortFbUrl = url.length > 60 ? url.slice(0, 57) + '...' : url;
            box.className = 'preview-box';
            box.style.width = '100%'; box.style.height = 'auto'; box.style.maxWidth = 'none';
            box.innerHTML = `
                <div style="width:100%; min-height:220px; background:linear-gradient(135deg,#0d1b3e,#1a2a5e,#0a1628); border-radius:12px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:1.2rem; padding:2rem; box-sizing:border-box; border:1px solid rgba(24,119,242,0.25); position:relative; overflow:hidden;">
                    <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(24,119,242,0.15),transparent 70%);pointer-events:none;"></div>
                    <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(24,119,242,0.15);border:1px solid rgba(24,119,242,0.3);padding:5px 14px;border-radius:30px;font-size:0.75rem;font-weight:800;letter-spacing:0.5px;color:#60a5fa;text-transform:uppercase;">
                        <i class="ph ph-facebook-logo" style="font-size:1rem;"></i> Facebook
                    </div>
                    <div style="width:64px;height:64px;border-radius:50%;background:rgba(24,119,242,0.12);border:2px solid rgba(24,119,242,0.25);display:flex;align-items:center;justify-content:center;">
                        <i class="ph ph-play" style="font-size:1.8rem;color:#1877F2;margin-left:3px;"></i>
                    </div>
                    <div style="font-size:0.7rem;color:rgba(255,255,255,0.3);word-break:break-all;text-align:center;max-width:240px;background:rgba(255,255,255,0.04);border-radius:6px;padding:4px 8px;border:1px solid rgba(255,255,255,0.07);">${shortFbUrl}</div>
                    <a href="${url}" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:#1877F2;color:white;padding:10px 22px;border-radius:25px;text-decoration:none;font-weight:700;font-size:0.85rem;font-family:inherit;box-shadow:0 4px 15px rgba(24,119,242,0.4);transition:all 0.2s;" onmouseover="this.style.background='#0a5fd8';this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#1877F2';this.style.transform=''">
                        <i class="ph ph-play-circle"></i> Ver en Facebook <i class="ph ph-arrow-square-out" style="font-size:0.75rem;opacity:0.7;"></i>
                    </a>
                </div>
                ${refBadge}${overlayHtml}`;
        } else if (url.match(/pinterest\.com|pin\.it/i)) {
            const shortPinUrl = url.length > 60 ? url.slice(0, 57) + '...' : url;
            box.className = 'preview-box';
            box.style.width = '100%'; box.style.height = 'auto'; box.style.maxWidth = 'none';
            box.innerHTML = `
                <div style="width:100%; min-height:220px; background:linear-gradient(135deg,#e60023,#cc001b,#b30018); border-radius:12px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:1.2rem; padding:2rem; box-sizing:border-box; border:1px solid rgba(230,0,35,0.25); position:relative; overflow:hidden;">
                    <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(230,0,35,0.15),transparent 70%);pointer-events:none;"></div>
                    <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);padding:5px 14px;border-radius:30px;font-size:0.75rem;font-weight:800;letter-spacing:0.5px;color:#fff;text-transform:uppercase;">
                        <i class="ph ph-pinterest-logo" style="font-size:1rem;"></i> Pinterest
                    </div>
                    <div style="width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,0.12);border:2px solid rgba(255,255,255,0.25);display:flex;align-items:center;justify-content:center;">
                        <i class="ph ph-play" style="font-size:1.8rem;color:#fff;margin-left:3px;"></i>
                    </div>
                    <div style="font-size:0.7rem;color:rgba(255,255,255,0.8);word-break:break-all;text-align:center;max-width:240px;background:rgba(0,0,0,0.1);border-radius:6px;padding:4px 8px;border:1px solid rgba(255,255,255,0.1);">${shortPinUrl}</div>
                    <a href="${url}" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:#fff;color:#e60023;padding:10px 22px;border-radius:25px;text-decoration:none;font-weight:700;font-size:0.85rem;font-family:inherit;box-shadow:0 4px 15px rgba(230,0,35,0.4);transition:all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                        <i class="ph ph-play-circle"></i> Ver en Pinterest <i class="ph ph-arrow-square-out" style="font-size:0.75rem;opacity:0.7;"></i>
                    </a>
                </div>
                ${refBadge}${overlayHtml}`;
        } else if (url.match(/drive\.google\.com\/(?:file\/d\/|open\?id=)([\w-]+)/)) {
            const driveMatch = url.match(/drive\.google\.com\/(?:file\/d\/|open\?id=)([\w-]+)/);
            box.innerHTML = `${refBadge}<iframe width="100%" height="100%" src="https://drive.google.com/file/d/${driveMatch[1]}/preview" frameborder="0" allowfullscreen style="border:none; border-radius:12px;"></iframe>${overlayHtml}`;
        } else {
            box.innerHTML = `
                <div style="text-align: center; color: white; padding: 1rem; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; background: #1e293b; border-radius: 12px;">
                    <i class="ph ph-video-camera" style="font-size: 3rem; color: #3b82f6;"></i>
                    <p style="font-size: 0.75rem; margin-top: 0.5rem; word-break: normal; opacity: 0.8; line-height: 1.4;">Video Adjunto<br>Reels Ratio (9:16)</p>
                </div>
                </div>
                ${refBadge}${overlayHtml}
            `;
        }
    } else {
        // Intelligent image ratio
        box.className = 'preview-box';
        if (isRef) {
            box.style.width = '100%';
            box.style.height = '340px';
            box.style.maxWidth = 'none';
            box.style.overflow = 'hidden';
            box.style.position = 'relative';
            box.innerHTML = `${refBadge}<div style="position:relative; width:100%; height:100%; cursor:pointer;" onclick="openPostImageViewer(0)" title="Click para ver en el visor de imágenes"><img src="${url}" class="preview-interactive-img" style="width: 100%; height: 100%; display: block; border-radius: 12px; object-fit: contain;"><div class="preview-zoom-badge"><i class="ph ph-arrows-out"></i> Ver</div></div>${overlayHtml}`;
        } else {
            box.style.width = 'auto';
            box.style.height = 'auto';
            box.style.maxWidth = '280px';
            box.innerHTML = `${refBadge}<div style="position:relative; width:100%; cursor:pointer;" onclick="openPostImageViewer(0)" title="Click para ver en el visor de imágenes"><img src="${url}" class="preview-interactive-img" style="width: 100%; height: auto; max-height: 400px; display: block; border-radius: 12px; object-fit: contain;"><div class="preview-zoom-badge"><i class="ph ph-arrows-out"></i> Ver</div></div>${overlayHtml}`;
        }
    }
}

// ==========================================
// POST IMAGE VIEWER LIGHTBOX LOGIC
// ==========================================
let pivCurrentImages = [];
let pivCurrentIndex = 0;
let pivZoom = 1;
let pivRotateDeg = 0;
let pivTranslateX = 0;
let pivTranslateY = 0;
let pivIsDragging = false;
let pivStartX = 0;
let pivStartY = 0;

function openPostImageViewer(targetIndex = 0) {
    const isRef = document.querySelector('input[name="post_type"]:checked') ? (document.querySelector('input[name="post_type"]:checked').value === 'Referencia Visual') : true;
    const inputEl = isRef ? document.getElementById('post-reference-link') : document.getElementById('post-image-link');
    
    let rawVal = inputEl ? inputEl.value : '';
    let list = [];
    try {
        list = JSON.parse(rawVal);
        if (!Array.isArray(list)) list = rawVal ? [rawVal] : [];
    } catch(e) {
        list = rawVal ? [rawVal] : [];
    }
    
    // Filter valid non-empty URLs
    list = list.filter(u => typeof u === 'string' && u.trim() !== '');
    
    if (list.length === 0) {
        if (typeof showToast === 'function') {
            showToast('No hay ninguna imagen cargada para visualizar.');
        } else {
            alert('No hay ninguna imagen cargada para visualizar.');
        }
        return;
    }
    
    pivCurrentImages = list;
    pivCurrentIndex = Math.max(0, Math.min(targetIndex, list.length - 1));
    
    // Set Badge Info
    const badgeEl = document.getElementById('piv-type-badge');
    if (badgeEl) {
        badgeEl.innerHTML = isRef 
            ? '<i class="ph ph-lightbulb"></i> Referencia Visual' 
            : '<i class="ph ph-check-circle"></i> Post Terminado';
        badgeEl.style.background = isRef ? 'rgba(245, 158, 11, 0.2)' : 'rgba(16, 185, 129, 0.2)';
        badgeEl.style.color = isRef ? '#fbbf24' : '#34d399';
        badgeEl.style.borderColor = isRef ? 'rgba(245, 158, 11, 0.35)' : 'rgba(16, 185, 129, 0.35)';
    }
    
    // Show/Hide navigation buttons
    const prevBtn = document.getElementById('piv-prev-btn');
    const nextBtn = document.getElementById('piv-next-btn');
    const counterEl = document.getElementById('piv-counter');
    const thumbContainer = document.getElementById('piv-thumbnails');
    
    if (list.length > 1) {
        if (prevBtn) prevBtn.style.display = 'flex';
        if (nextBtn) nextBtn.style.display = 'flex';
        if (counterEl) counterEl.style.display = 'inline-block';
        if (thumbContainer) {
            thumbContainer.style.display = 'flex';
            let thumbHtml = '';
            list.forEach((url, idx) => {
                thumbHtml += `<div class="piv-thumb-item ${idx === pivCurrentIndex ? 'active' : ''}" onclick="pivSetImage(${idx})"><img src="${url}" alt="Miniatura ${idx + 1}"></div>`;
            });
            thumbContainer.innerHTML = thumbHtml;
        }
    } else {
        if (prevBtn) prevBtn.style.display = 'none';
        if (nextBtn) nextBtn.style.display = 'none';
        if (counterEl) counterEl.style.display = 'none';
        if (thumbContainer) thumbContainer.style.display = 'none';
    }
    
    pivSetImage(pivCurrentIndex);
    
    const modal = document.getElementById('post-image-viewer-modal');
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
    }
    
    // Add event listeners
    window.addEventListener('keydown', pivHandleKeydown);
    window.addEventListener('mousemove', pivDoDrag);
    window.addEventListener('mouseup', pivEndDrag);
}

function closePostImageViewer() {
    const modal = document.getElementById('post-image-viewer-modal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            if (!modal.classList.contains('active')) {
                modal.style.display = 'none';
            }
        }, 250);
    }
    window.removeEventListener('keydown', pivHandleKeydown);
    window.removeEventListener('mousemove', pivDoDrag);
    window.removeEventListener('mouseup', pivEndDrag);
}

function pivHandleOverlayClick(e) {
    if (e.target.id === 'post-image-viewer-modal' || e.target.id === 'piv-stage') {
        closePostImageViewer();
    }
}

function pivSetImage(index) {
    if (index < 0 || index >= pivCurrentImages.length) return;
    pivCurrentIndex = index;
    
    const imgEl = document.getElementById('piv-main-image');
    if (imgEl) {
        imgEl.src = pivCurrentImages[pivCurrentIndex];
    }
    
    // Update counter
    const counterEl = document.getElementById('piv-counter');
    if (counterEl && pivCurrentImages.length > 1) {
        counterEl.innerText = `${pivCurrentIndex + 1} / ${pivCurrentImages.length}`;
    }
    
    // Update active thumbnail
    document.querySelectorAll('.piv-thumb-item').forEach((th, idx) => {
        if (idx === pivCurrentIndex) {
            th.classList.add('active');
            th.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        } else {
            th.classList.remove('active');
        }
    });
    
    pivResetTransform();
}

function pivPrevImage() {
    if (pivCurrentImages.length <= 1) return;
    const newIdx = (pivCurrentIndex - 1 + pivCurrentImages.length) % pivCurrentImages.length;
    pivSetImage(newIdx);
}

function pivNextImage() {
    if (pivCurrentImages.length <= 1) return;
    const newIdx = (pivCurrentIndex + 1) % pivCurrentImages.length;
    pivSetImage(newIdx);
}

function pivUpdateTransform() {
    const imgEl = document.getElementById('piv-main-image');
    const zoomValEl = document.getElementById('piv-zoom-val');
    if (imgEl) {
        imgEl.style.transform = `translate(${pivTranslateX}px, ${pivTranslateY}px) scale(${pivZoom}) rotate(${pivRotateDeg}deg)`;
    }
    if (zoomValEl) {
        zoomValEl.innerText = Math.round(pivZoom * 100) + '%';
    }
}

function pivZoomIn() {
    pivZoom = Math.min(5, Number((pivZoom + 0.25).toFixed(2)));
    pivUpdateTransform();
}

function pivZoomOut() {
    pivZoom = Math.max(0.25, Number((pivZoom - 0.25).toFixed(2)));
    if (pivZoom <= 1) {
        pivTranslateX = 0;
        pivTranslateY = 0;
    }
    pivUpdateTransform();
}

function pivResetTransform() {
    pivZoom = 1;
    pivRotateDeg = 0;
    pivTranslateX = 0;
    pivTranslateY = 0;
    pivUpdateTransform();
}

function pivRotate() {
    pivRotateDeg = (pivRotateDeg + 90) % 360;
    pivUpdateTransform();
}

function pivToggleZoom(e) {
    if (pivZoom > 1.05) {
        pivResetTransform();
    } else {
        pivZoom = 2;
        pivUpdateTransform();
    }
}

function pivHandleWheel(e) {
    e.preventDefault();
    if (e.deltaY < 0) {
        pivZoomIn();
    } else {
        pivZoomOut();
    }
}

function pivStartDrag(e) {
    if (e.button !== 0) return; // Only primary button
    pivIsDragging = true;
    pivStartX = e.clientX - pivTranslateX;
    pivStartY = e.clientY - pivTranslateY;
    const stage = document.getElementById('piv-stage');
    if (stage) stage.classList.add('dragging');
}

function pivDoDrag(e) {
    if (!pivIsDragging) return;
    e.preventDefault();
    pivTranslateX = e.clientX - pivStartX;
    pivTranslateY = e.clientY - pivStartY;
    pivUpdateTransform();
}

function pivEndDrag() {
    if (!pivIsDragging) return;
    pivIsDragging = false;
    const stage = document.getElementById('piv-stage');
    if (stage) stage.classList.remove('dragging');
}

function pivDownload() {
    if (!pivCurrentImages[pivCurrentIndex]) return;
    const url = pivCurrentImages[pivCurrentIndex];
    const link = document.createElement('a');
    link.href = url;
    link.download = url.substring(url.lastIndexOf('/') + 1) || 'imagen_post.jpg';
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function pivOpenInNewTab() {
    if (!pivCurrentImages[pivCurrentIndex]) return;
    window.open(pivCurrentImages[pivCurrentIndex], '_blank');
}

function pivHandleKeydown(e) {
    const modal = document.getElementById('post-image-viewer-modal');
    if (!modal || !modal.classList.contains('active')) return;
    
    if (e.key === 'Escape') {
        e.stopPropagation();
        e.preventDefault();
        closePostImageViewer();
    } else if (e.key === 'ArrowLeft') {
        e.stopPropagation();
        e.preventDefault();
        pivPrevImage();
    } else if (e.key === 'ArrowRight') {
        e.stopPropagation();
        e.preventDefault();
        pivNextImage();
    } else if (e.key === '+' || e.key === '=') {
        e.stopPropagation();
        e.preventDefault();
        pivZoomIn();
    } else if (e.key === '-' || e.key === '_') {
        e.stopPropagation();
        e.preventDefault();
        pivZoomOut();
    } else if (e.key === '0' || e.key === 'r' || e.key === 'R') {
        e.stopPropagation();
        e.preventDefault();
        pivResetTransform();
    }
}

function downloadActiveResource() {
    const isRef = document.querySelector('input[name="post_type"]:checked').value === 'Referencia Visual';
    const val = isRef ? document.getElementById('post-reference-link').value : document.getElementById('post-image-link').value;
    
    let mediaList = [];
    try {
        mediaList = JSON.parse(val);
        if(!Array.isArray(mediaList)) mediaList = val ? [val] : [];
    } catch(e) {
        mediaList = val ? [val] : [];
    }
    
    if (mediaList.length === 0) {
        showToast('No hay archivo subido para descargar.');
        return;
    }

    mediaList.forEach(url => {
        let downloadUrl = url;
        const driveMatch = url.match(/drive\.google\.com\/(?:uc\?export=view&id=|thumbnail\?id=|file\/d\/|open\?id=)([\w-]+)/i);
        if (driveMatch) {
            downloadUrl = 'https://drive.google.com/uc?export=download&id=' + driveMatch[1];
        }
        window.open(downloadUrl, '_blank');
    });

    // Cambiar a publicado cuando se descarga el archivo terminado
    if (!isRef) {
        setPostStatus('Publicado');
        savePost(true);
    }
}
function editPost(postData) {
    let post = postData;
    // Fix: Always fetch the most up-to-date post from memory if it exists 
    // (covers cases where presentation calendar drag&drop updated it without reloading)
    if (post && post.id) {
        const memoryPost = studioPosts.find(p => p.id == post.id);
        if (memoryPost) {
            post = Object.assign({}, post, memoryPost);
        }
    }
    
    document.getElementById('post-form').reset();
    document.getElementById('post-id').value = post.id;
    // Format date string for datetime-local
    let d = post.post_date ? new Date(post.post_date) : new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    document.getElementById('post-date').value = d.toISOString().slice(0,16);

    // Platform (multiple checkboxes)
    let platforms = post.platform ? post.platform.split(', ') : [];
    document.querySelectorAll('input[name="platform[]"]').forEach(chk => {
        chk.checked = platforms.includes(chk.value);
    });

    document.getElementById('post-concept').value = post.concept;
    
    // 4. Set WYSIWYG content
    const wysiwygEditable = document.getElementById('post-copy-editable');
    const hiddenTextarea = document.getElementById('post-copy');
    if (wysiwygEditable) wysiwygEditable.innerHTML = post.copy_text || '';
    if (hiddenTextarea) hiddenTextarea.value = post.copy_text || '';
    
    document.getElementById('post-status').value = post.status;
    document.getElementById('post-image-link').value = post.image_link || '';
    document.getElementById('post-reference-link').value = post.reference_image_link || '';
    document.getElementById('post-paint-data').value = post.paint_data || '';
    
    // Nuevos campos
    if (post.post_type) {
        let typeRadio = document.querySelector(`input[name="post_type"][value="${post.post_type}"]`);
        if (typeRadio) {
            typeRadio.checked = true;
        }
    }
    
    updateVideoPreview();

    if (post.end_date) document.getElementById('post-end-date').value = post.end_date.split(' ')[0]; // just date part if it has time
    if (post.periodicity) document.getElementById('post-periodicity').value = post.periodicity;
    if (post.reminder) document.getElementById('post-reminder').value = post.reminder;
    if (post.design_brief && document.getElementById('post-brief')) {
        document.getElementById('post-brief').value = post.design_brief;

    }
    if (post.drive_images && document.getElementById('post-drive')) {
        document.getElementById('post-drive').value = post.drive_images;
        if(typeof handleDriveLink === 'function') handleDriveLink();
    }

    // Formatos
    let formats = [];
    try { formats = JSON.parse(post.formats || '[]'); } catch(e){}
    document.querySelectorAll('input[name="formats[]"]').forEach(chk => {
        chk.checked = formats.includes(chk.value);
    });

    // Content Pillar
    const pillar = post.content_pillar || 'Educación';
    const pilRadio = document.querySelector(`input[name="content_pillar"][value="${pillar}"]`);
    if (pilRadio) {
        pilRadio.checked = true;
    } else if (document.getElementById('pil_edu')) {
        document.getElementById('pil_edu').checked = true;
    }

    // QA Checklist
    let qaObj = {};
    try {
        qaObj = typeof post.qa_checklist === 'string' ? JSON.parse(post.qa_checklist || '{}') : (post.qa_checklist || {});
    } catch(e) {}
    if (document.getElementById('qa_spelling')) document.getElementById('qa_spelling').checked = !!(qaObj && qaObj.spelling);
    if (document.getElementById('qa_brand')) document.getElementById('qa_brand').checked = !!(qaObj && qaObj.brand);
    if (document.getElementById('qa_format')) document.getElementById('qa_format').checked = !!(qaObj && qaObj.format);
    if (document.getElementById('qa_cta')) document.getElementById('qa_cta').checked = !!(qaObj && qaObj.cta);
    updateQAUi();

    // Referencias
    let refs = [];
    try { refs = JSON.parse(post.visual_references || '[]'); } catch(e){}
    let refsContainer = document.getElementById('refs-container');
    if (refsContainer) {
        refsContainer.innerHTML = '';
        refs.forEach(r => addRefRow(r));
    }

    // Variaciones
    let vars = [];
    try { vars = JSON.parse(post.variations || '[]'); } catch(e){}
    let varsContainer = document.getElementById('vars-container');
    if (varsContainer) {
        varsContainer.innerHTML = '';
        vars.forEach(v => addVarRow(v.title, v.instructions));
    }
    
    // Comentarios - separados por fase
    let containerContenido = document.getElementById('comments-container-contenido');
    let containerDiseno = document.getElementById('comments-container-diseno');
    let commentsBadge = document.getElementById('comments-badge');
    
    if (containerContenido && containerDiseno) {
        containerContenido.innerHTML = '';
        containerDiseno.innerHTML = '';
        
        if (post && post.comments && post.comments.length > 0) {
            const activeComments = post.comments.filter(c => c.status !== 'Levantado').length;
            if (commentsBadge) commentsBadge.style.display = activeComments > 0 ? 'inline-block' : 'none';
            
            let countContenido = 0, countDiseno = 0;
            
            post.comments.forEach(c => {
                const html = renderCommentCard(c, post.id);
                const phase = (c.phase || '').toLowerCase();
                
                if (phase === 'bocetos' || phase === 'fase de diseño' || phase === 'fase de diseno' || phase === 'diseño' || phase === 'referencias') {
                    containerDiseno.innerHTML += html;
                    if (c.status !== 'Levantado') countDiseno++;
                } else {
                    containerContenido.innerHTML += html;
                    if (c.status !== 'Levantado') countContenido++;
                }
            });
            
            // Update count badges
            const countContEl = document.getElementById('count-contenido');
            const countDisEl = document.getElementById('count-diseno');
            if (countContEl) countContEl.textContent = countContenido > 0 ? countContenido : '';
            if (countDisEl) countDisEl.textContent = countDiseno > 0 ? countDiseno : '';
            if (countContEl) countContEl.style.display = countContenido > 0 ? 'inline-block' : 'none';
            if (countDisEl) countDisEl.style.display = countDiseno > 0 ? 'inline-block' : 'none';
            
            // Show empty states
            if (!containerContenido.innerHTML.trim()) {
                containerContenido.innerHTML = '<p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 2rem 0;">Sin comentarios de contenido.</p>';
            }
            if (!containerDiseno.innerHTML.trim()) {
                containerDiseno.innerHTML = '<p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 2rem 0;">Sin comentarios de diseño.</p>';
            }
        } else {
            if (commentsBadge) commentsBadge.style.display = 'none';
            const emptyMsg = '<p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 2rem 0;">No hay comentarios para esta publicación.</p>';
            containerContenido.innerHTML = emptyMsg;
            containerDiseno.innerHTML = emptyMsg;
            document.getElementById('count-contenido').style.display = 'none';
            document.getElementById('count-diseno').style.display = 'none';
        }
        
        // Reset to first subtab
        switchCommentSubtab('contenido');
    }

    document.getElementById('post-modal-title').innerText = 'Editar Publicación';
    
    // Resetear pestañas y pipeline
    switchCrmTab(document.querySelector('.crm-tab:first-child'), 'tab-contenido');
    updatePipelineUI();
    
    document.getElementById('post-modal').classList.add('active');
    updateSaveButtonState();
    updateCopyPreview();
    isFormDirty = false;
    startAutoSave();
}

// === Helper: Render a single comment card ===
function renderCommentCard(c, postId) {
    let html = `<div style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; display: flex; justify-content: space-between;">
            <span>${c.created_at}</span>
            <span class="status-badge" style="padding: 0.2rem 0.5rem; font-size: 0.7rem; border-radius: 4px; ${c.status === 'Levantado' ? 'background: rgba(16, 185, 129, 0.1); color: #059669;' : 'background: rgba(245, 158, 11, 0.1); color: #d97706;'}">${c.status}</span>
        </div>`;
    if (c.comment_text) {
        html += `<p style="margin: 0 0 1rem 0; font-size: 0.95rem; white-space: pre-wrap;">${c.comment_text}</p>`;
    }
    if (c.image_link) {
        html += `<div style="margin-bottom: 1rem;"><a href="${c.image_link}" target="_blank"><img src="${c.image_link}" style="max-width: 100%; border-radius: 8px; max-height: 200px;"></a></div>`;
    }
    if (c.audio_link) {
        const playerId = 'audio-player-' + c.id;
        html += `<div style="margin-bottom: 1rem; background: linear-gradient(135deg, rgba(102,126,234,0.12), rgba(118,75,162,0.12)); padding: 1rem; border-radius: 12px; border: 1px solid rgba(102,126,234,0.2);">
            <div style="font-size: 0.7rem; font-weight: 700; color: #667eea; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;"><i class="ph ph-microphone"></i> Nota de Voz del Cliente</div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" onclick="toggleCustomAudio('${playerId}')" id="btn-${playerId}" style="width:40px; height:40px; border-radius:50%; border:none; background: linear-gradient(135deg, #667eea, #764ba2); color:white; font-size:1rem; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition: transform 0.2s; box-shadow: 0 3px 10px rgba(102,126,234,0.3);">▶</button>
                <div style="flex:1; display:flex; flex-direction:column; gap:4px;">
                    <input type="range" id="progress-${playerId}" min="0" max="100" value="0" style="width:100%; height:4px; accent-color:#667eea; cursor:pointer;" oninput="seekCustomAudio('${playerId}', this.value)">
                    <div style="display:flex; justify-content:space-between; font-size:0.7rem; color: var(--text-muted);">
                        <span id="time-${playerId}">0:00</span>
                        <span id="dur-${playerId}">--:--</span>
                    </div>
                </div>
            </div>
            <audio id="${playerId}" preload="metadata" src="${c.audio_link}" ontimeupdate="updateCustomProgress('${playerId}')" onloadedmetadata="showCustomDuration('${playerId}')" onended="resetCustomAudio('${playerId}')"></audio>
        </div>`;
    }
    if (c.suggested_copy) {
        html += `<div style="margin-bottom: 1rem; background: rgba(245,158,11,0.05); padding: 0.75rem; border-radius: 8px; border: 1px solid rgba(245,158,11,0.15);">
            <div style="font-size: 0.75rem; font-weight: 700; color: #d97706; margin-bottom: 4px;"><i class="ph ph-pencil-simple"></i> Copy Sugerido por el Cliente</div>
            <p style="margin: 0; font-size: 0.9rem; white-space: pre-wrap;">${c.suggested_copy}</p>
        </div>`;
    }
    if (c.hotspot_x && c.hotspot_y) {
        html += `<div style="margin-bottom: 1rem; font-size: 0.8rem; color: #d97706; display: flex; align-items: center; gap: 6px;">
            <i class="ph ph-map-pin"></i> Marcó un punto en la imagen (X: ${parseFloat(c.hotspot_x).toFixed(1)}%, Y: ${parseFloat(c.hotspot_y).toFixed(1)}%)
        </div>`;
    }
    if (c.status !== 'Levantado') {
        html += `<button type="button" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.4rem 0.8rem; color: #10b981; border-color: #10b981;" onclick="markCommentResolved(${c.id}, ${postId})"><i class="ph ph-check-circle"></i> Marcar como Levantado</button>`;
    }
    html += `</div>`;
    return html;
}

// === Custom Audio Player Functions ===
function toggleCustomAudio(playerId) {
    const audio = document.getElementById(playerId);
    const btn = document.getElementById('btn-' + playerId);
    if (!audio) return;
    if (audio.paused) {
        // Pause all other players first
        document.querySelectorAll('audio').forEach(a => { if (a.id !== playerId && !a.paused) { a.pause(); const ob = document.getElementById('btn-' + a.id); if(ob) ob.innerHTML = '▶'; } });
        audio.play();
        btn.innerHTML = '⏸';
    } else {
        audio.pause();
        btn.innerHTML = '▶';
    }
}

function updateCustomProgress(playerId) {
    const audio = document.getElementById(playerId);
    const progress = document.getElementById('progress-' + playerId);
    const timeEl = document.getElementById('time-' + playerId);
    if (!audio || !progress) return;
    const pct = (audio.currentTime / audio.duration) * 100;
    progress.value = isNaN(pct) ? 0 : pct;
    const m = Math.floor(audio.currentTime / 60);
    const s = Math.floor(audio.currentTime % 60);
    timeEl.textContent = m + ':' + String(s).padStart(2, '0');
}

function seekCustomAudio(playerId, value) {
    const audio = document.getElementById(playerId);
    if (!audio || isNaN(audio.duration)) return;
    audio.currentTime = (value / 100) * audio.duration;
}

function showCustomDuration(playerId) {
    const audio = document.getElementById(playerId);
    const durEl = document.getElementById('dur-' + playerId);
    if (!audio || !durEl) return;
    const m = Math.floor(audio.duration / 60);
    const s = Math.floor(audio.duration % 60);
    durEl.textContent = m + ':' + String(s).padStart(2, '0');
}

function resetCustomAudio(playerId) {
    const btn = document.getElementById('btn-' + playerId);
    const progress = document.getElementById('progress-' + playerId);
    const timeEl = document.getElementById('time-' + playerId);
    if (btn) btn.innerHTML = '▶';
    if (progress) progress.value = 0;
    if (timeEl) timeEl.textContent = '0:00';
}

async function markCommentResolved(commentId, postId) {
    if (!confirm('¿Marcar comentario como levantado? El estado del post pasará a Borrador.')) return;
    
    const formData = new FormData();
    formData.append('comment_id', commentId);
    formData.append('post_id', postId);
    
    try {
        const response = await fetch('modules/month_board/ajax_mark_comment_resolved.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            showToast('Comentario marcado como levantado.', 'success');
            window.location.reload();
        } else {
            showToast('Error: ' + result.error);
        }
    } catch (err) {
        console.error(err);
        showToast('Error de red.');
    }
}

function addRefRow(val = '') {
    const div = document.createElement('div');
    div.className = 'dyn-row';
    div.innerHTML = `
        <div class="dyn-row-content">
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="url" name="visual_references[]" class="form-control form-control-sm" style="flex:1;" placeholder="https://..." value="${val}">
                <button type="button" class="btn btn-outline" style="padding: 0.2rem 0.5rem; font-size: 0.8rem;" onclick="this.nextElementSibling.click()"><i class="ph ph-upload-simple"></i></button>
                <input type="file" style="display:none" accept="image/*" onchange="uploadReferenceImage(this)">
            </div>
            <div class="ref-thumb" style="margin-top: 0.25rem;">${val ? `<img src="${val}" style="max-height: 50px; border-radius: 4px;">` : ''}</div>
        </div>
        <button type="button" class="btn-remove-row" onclick="this.parentElement.remove()"><i class="ph ph-trash"></i></button>
    `;
    document.getElementById('refs-container').appendChild(div);
}

async function uploadReferenceImage(input) {
    if(!input.files || !input.files[0]) return;
    const file = await compressImage(input.files[0]);
    const formData = new FormData();
    formData.append('image', file);
    formData.append('month_id', document.querySelector('input[name="month_id"]').value);
    try {
        const response = await fetch('modules/month_board/ajax_upload_reference.php', { method: 'POST', body: formData });
        const res = await response.json();
        if(res.success) {
            const row = input.closest('.dyn-row-content');
            row.querySelector('input[type="url"]').value = res.url;
            row.querySelector('.ref-thumb').innerHTML = `<img src="${res.url}" style="max-height: 50px; border-radius: 4px;">`;
            markDirty();
        } else {
            showToast(res.error || 'Error subiendo imagen.');
        }
    } catch(e) { console.error(e); }
}

function addVarRow(title = '', instructions = '') {
    const div = document.createElement('div');
    div.className = 'dyn-row';
    div.innerHTML = `
        <div class="dyn-row-content">
            <input type="text" name="variations[title][]" class="form-control form-control-sm" placeholder="Título (ej. Opción A - Formato Cuadrado)" value="${title}">
            <textarea name="variations[instructions][]" class="form-control form-control-sm" rows="2" placeholder="Instrucciones específicas...">${instructions}</textarea>
        </div>
        <button type="button" class="btn-remove-row" onclick="this.parentElement.remove()"><i class="ph ph-trash"></i></button>
    `;
    document.getElementById('vars-container').appendChild(div);
}

async function uploadMainImage(input) {
    if(!input.files || input.files.length === 0) return;
    
    const box = document.getElementById('preview-box');
    const oldHtml = box.innerHTML;
    box.style.display = 'flex'; box.style.padding = '0'; box.style.gridTemplateColumns = ''; box.style.gap = '';
    box.innerHTML = `
        <div style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100%; color: white; width:100%; padding: 1rem;">
            <i class="ph ph-cloud-arrow-up" style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--primary-color);"></i>
            <div style="width: 80%; height: 6px; background: rgba(255,255,255,0.2); border-radius: 3px; overflow: hidden;">
                <div id="upload-progress-fill" style="height: 100%; width: 0%; background: var(--primary-color); transition: width 0.2s;"></div>
            </div>
            <div id="upload-progress-text" style="font-size: 0.8rem; margin-top: 0.5rem; font-weight: 600;">Subiendo 0%</div>
        </div>
    `;
    
    let uploadedUrls = [];
    
    for (let i = 0; i < input.files.length; i++) {
        const url = await performMainImageUpload(input.files[i]);
        if (url) uploadedUrls.push(url);
    }
    
    if (uploadedUrls.length > 0) {
        const isRef = document.querySelector('input[name="post_type"]:checked').value === 'Referencia Visual';
        const inputEl = isRef ? document.getElementById('post-reference-link') : document.getElementById('post-image-link');
        
        let currentList = [];
        try {
            currentList = JSON.parse(inputEl.value);
            if(!Array.isArray(currentList)) currentList = inputEl.value ? [inputEl.value] : [];
        } catch(e) {
            currentList = inputEl.value ? [inputEl.value] : [];
        }
        
        currentList = currentList.concat(uploadedUrls);
        inputEl.value = JSON.stringify(currentList);
        
        // Auto-change status to Publicado if image uploaded in Terminado
        if (!isRef) {
            document.getElementById('post-status').value = 'Publicado';
            if (typeof updatePipelineUI === 'function') updatePipelineUI();
            if (typeof updateSaveButtonState === 'function') updateSaveButtonState();
        }
        
        updateVideoPreview();
        markDirty();
    } else {
        box.innerHTML = oldHtml;
        updateVideoPreview();
    }
    
    input.value = ''; // Reset input
}

async function savePost(isAutoSave = false) {
    // Capture the post-id BEFORE submit
    const postIdValue = document.getElementById('post-id').value;
    
    // Sync WYSIWYG to textarea
    const wysiwyg = document.getElementById('post-copy-editable');
    if (wysiwyg) document.getElementById('post-copy').value = wysiwyg.innerHTML;
    
    const form = document.getElementById('post-form');
    if (!isAutoSave && !form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const formData = new FormData(form);

    // Force the correct id value (defense against stale form state)
    formData.set('id', postIdValue);

    // QA Checklist Object to JSON
    const qaObj = {
        spelling: !!document.getElementById('qa_spelling')?.checked,
        brand: !!document.getElementById('qa_brand')?.checked,
        format: !!document.getElementById('qa_format')?.checked,
        cta: !!document.getElementById('qa_cta')?.checked
    };
    formData.set('qa_checklist', JSON.stringify(qaObj));

    let variationsArr = [];
    let titles = formData.getAll('variations[title][]');
    let insts = formData.getAll('variations[instructions][]');
    for (let i = 0; i < titles.length; i++) {
        if (titles[i] || insts[i]) {
            variationsArr.push({ title: titles[i], instructions: insts[i] });
        }
    }
    formData.delete('variations[title][]');
    formData.delete('variations[instructions][]');
    formData.append('variations', JSON.stringify(variationsArr));

    try {
        const response = await fetch('modules/month_board/ajax_save_post.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            // Keep memory in sync for Presentation calendar and editing without reload
            const formPostId = document.getElementById('post-id').value || result.id;
            if (formPostId) {
                const mem = studioPosts.find(p => p.id == formPostId);
                if (mem) {
                    const rawDate = document.getElementById('post-date').value;
                    mem.post_date = rawDate ? (rawDate.length === 16 ? rawDate.replace('T', ' ') + ':00' : rawDate.replace('T', ' ')) : null;
                    mem.concept = document.getElementById('post-concept').value;
                }
            }

            if (isAutoSave) {
                document.getElementById('post-id').value = result.id || document.getElementById('post-id').value;
                const ind = document.getElementById('auto-save-indicator');
                ind.style.opacity = 1;
                ind.innerText = `Guardado hace unos segundos`;
                setTimeout(() => ind.style.opacity = 0, 3000);
                isFormDirty = false;
            } else {
                let isUploading = false;
                if (document.getElementById('upload-progress-widget').style.display === 'flex') {
                    document.querySelectorAll('#upload-progress-list > div > div > span:last-child').forEach(span => {
                        if (span.innerText.includes('%') && span.innerText !== '100%') isUploading = true;
                    });
                }
                
                if (isUploading) {
                    closePostModal();
                    const toast = document.createElement('div');
                    toast.style = "position:fixed; top:20px; left:50%; transform:translateX(-50%); background:var(--primary-color); color:white; padding:1rem 2rem; border-radius:8px; z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,0.2); font-weight:600; display:flex; align-items:center; gap:0.5rem;";
                    toast.innerHTML = "<i class='ph ph-check-circle' style='font-size:1.2rem;'></i> Post guardado. Actualizando al terminar subidas...";
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 6000);
                    
                    window.reloadPending = true;
                } else {
                    window.location.reload();
                }
            }
        } else {
            if(!isAutoSave) showToast(result.error || 'Error al guardar.');
        }
    } catch (e) {
        console.error(e);
        if(!isAutoSave) showToast('Error de red.');
    }
}

function handleDragOver(e) { e.preventDefault(); e.currentTarget.style.border = "2px dashed #3b82f6"; }
function handleDragLeave(e) { e.preventDefault(); e.currentTarget.style.border = "none"; }
async function handleDrop(e) {
    e.preventDefault();
    e.currentTarget.style.border = "none";
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        const fakeInput = { files: e.dataTransfer.files, value: '' };
        await uploadMainImage(fakeInput);
    }
}

async function performMainImageUpload(originalFile) {
    return new Promise(async (resolve) => {
        const isRef = document.querySelector('input[name="post_type"]:checked').value === 'Referencia Visual';
        const file = isRef ? await compressImage(originalFile) : originalFile;
        
        const formData = new FormData();
        formData.append('image', file);
        formData.append('month_id', document.querySelector('input[name="month_id"]').value);
        
        formData.append('post_type', isRef ? 'Referencia Visual' : 'Post Terminado');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'modules/month_board/ajax_upload_reference.php', true);

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                const fill = document.getElementById('upload-progress-fill');
                const text = document.getElementById('upload-progress-text');
                if (fill && text) {
                    fill.style.width = percentComplete + '%';
                    text.innerText = `Subiendo ${percentComplete}%`;
                }
            }
        };

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    let text = xhr.responseText.trim();
                    if (text.indexOf('{') !== -1) {
                        text = text.substring(text.indexOf('{'), text.lastIndexOf('}') + 1);
                    }
                    const res = JSON.parse(text);
                    if (res.success) {
                        resolve(res.url);
                    } else {
                        showToast(res.error || 'Error subiendo imagen.');
                        resolve(null);
                    }
                } catch(e) {
                    console.error("Parse error. Raw response:", xhr.responseText);
                    showToast('Error procesando la respuesta.');
                    resolve(null);
                }
            } else {
                showToast('Error de red al subir la imagen.');
                resolve(null);
            }
        };

        xhr.onerror = function() {
            showToast('Error de red al subir la imagen.');
            resolve(null);
        };

        xhr.send(formData);
    });
}

function updateCopyPreview() {
    const copyEl = document.getElementById('post-copy');
    if (!copyEl) return;
    const text = copyEl.value;
    const len = text.length;
    const countEl = document.getElementById('char-count');
    if (countEl) {
        countEl.innerText = len;
        if (len > 2200) countEl.style.color = 'var(--danger-color)';
        else if (len > 2000) countEl.style.color = '#eab308';
        else countEl.style.color = 'var(--text-muted)';
    }

}

function updateSaveButtonState() {
    const status = document.getElementById('post-status').value;
    const date = document.getElementById('post-date').value;
    const btnText = document.getElementById('btn-save-post-text');
    if (!btnText) return;
    if (status === 'Borrador') {
        btnText.innerText = 'Guardar Borrador';
    } else if (date) {
        btnText.innerText = 'Programar Publicación';
    } else {
        btnText.innerText = 'Guardar Publicación';
    }
}

function handleDriveLink() {
    const url = document.getElementById('post-drive').value;
    const box = document.getElementById('drive-box-container');
    
    // --- Lógica del Drive Box (Recursos y Drive) ---
    if (url.includes('drive.google.com') || url.includes('docs.google.com')) {
        box.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                <img src="https://upload.wikimedia.org/wikipedia/commons/1/12/Google_Drive_icon_%282020%29.svg" width="32" alt="Drive">
                <div style="text-align: left;">
                    <strong style="color: var(--text-color); font-size: 0.9rem;">Recurso Vinculado</strong><br>
                    <span style="font-size: 0.75rem;">Se extrajo ID del enlace</span>
                </div>
            </div>`;
        box.style.borderColor = '#10b981';
        box.style.backgroundColor = 'rgba(16, 185, 129, 0.05)';
    } else {
        box.innerHTML = `
            <i class="ph ph-google-drive-logo" style="font-size: 2.5rem; color: #3b82f6; margin-bottom: 0.5rem;"></i>
            <p style="margin: 0; font-size: 0.9rem;">Pega un enlace de Google Drive abajo para vincular recursos.</p>`;
        box.style.borderColor = 'var(--border-color)';
        box.style.backgroundColor = 'var(--bg-color)';
    }

    // --- Lógica del Slides Widget (Diseño y Formatos) ---
    const emptyState = document.getElementById('slides-empty-state');
    const linkedState = document.getElementById('slides-linked-state');
    const slidesIframe = document.getElementById('slides-iframe');

    if (url.includes('presentation/d/')) {
        emptyState.style.display = 'none';
        linkedState.style.display = 'block';
        
        let embedUrl = url;
        if (url.includes('/edit')) {
            embedUrl = url.replace('/edit', '/preview'); // Vista previa limpia para el iframe
        }
        slidesIframe.src = embedUrl;
    } else {
        emptyState.style.display = 'block';
        linkedState.style.display = 'none';
        slidesIframe.src = '';
    }
}

function startAutoSave() {
    clearInterval(autoSaveTimer);
    autoSaveTimer = setInterval(() => {
        if (isFormDirty && document.getElementById('post-concept').value.trim() !== '') {
            savePost(true);
        }
    }, 30000); // 30 segundos
}

function deletePost(id) {
    document.getElementById('delete-post-id').value = id;
    document.getElementById('deletePostConfirmModal').classList.add('active');
}

async function confirmDeletePost() {
    const id = document.getElementById('delete-post-id').value;
    if (!id) return;
    
    const formData = new FormData();
    formData.append('id', id);
    
    try {
        const response = await fetch('modules/month_board/ajax_delete_post.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            showToast(result.error || 'Error al eliminar.');
        }
    } catch (e) {
        console.error(e);
        showToast('Error de red.');
    }
}
</script>

<script>
// Define Google Picker callbacks BEFORE loading the async script
// so they're available immediately when the API loads from cache
let pickerApiLoaded = false;

function onPickerApiLoad() {
    pickerApiLoaded = true;
}

function onApiLoad() {
    gapi.load('picker', {'callback': onPickerApiLoad});
}
</script>
<script async defer src="https://apis.google.com/js/api.js" onload="onApiLoad()"></script>
<script>
    // Recuperar configuración de la base de datos para pasarlo al JS
    <?php
    require_once 'includes/GoogleDriveHelper.php';
    $driveHelper = new GoogleDriveHelper();
    $backendToken = $driveHelper->getAccessToken();
    
    $stmtSettings = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('drive_api_key', 'drive_app_id')");
    $settingsDrive = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);
    ?>
    const DRIVE_DEVELOPER_KEY = '<?php echo htmlspecialchars($settingsDrive['drive_api_key'] ?? ""); ?>';
    const DRIVE_APP_ID = '<?php echo htmlspecialchars($settingsDrive['drive_app_id'] ?? ""); ?>';

    let oauthToken = '<?php echo $backendToken; ?>';

    function loadPicker() {
        if (!DRIVE_DEVELOPER_KEY || !oauthToken) {
            showToast('La conexión con Google Drive no está lista. Verifica las credenciales en Ajustes.');
            return;
        }
        createPicker();
    }

    function createPicker() {
        if (pickerApiLoaded && oauthToken) {
            var view = new google.picker.View(google.picker.ViewId.DOCS);
            var picker = new google.picker.PickerBuilder()
                .addView(view)
                .addView(new google.picker.DocsUploadView())
                .setOAuthToken(oauthToken)
                .setDeveloperKey(DRIVE_DEVELOPER_KEY)
                .setCallback(pickerCallback)
                .build();
            picker.setVisible(true);
        }
    }

    function pickerCallback(data) {
        if (data.action == google.picker.Action.PICKED) {
            var fileId = data.docs[0].id;
            var fileUrl = data.docs[0].url;
            document.getElementById('post-drive').value = fileUrl;
            handleDriveLink();
            markDirty();
        }
    }

    // --- GOOGLE SLIDES EDITOR FUNCTIONALITY ---
    async function openSlideEditor(btnElement) {
        const monthId = document.querySelector('input[name="month_id"]').value;
        const postCopy = document.getElementById('post-copy') ? document.getElementById('post-copy').value.substring(0, 30) : '';
        const postTitle = postCopy ? 'Presentación: ' + postCopy + '...' : 'Nueva Presentación Roma';
        
        const btnTextSpan = btnElement.querySelector('.btn-text');
        const iconElement = btnElement.querySelector('i');
        
        const originalText = btnTextSpan.innerText;
        const originalIcon = iconElement.className;
        
        // Show loading state
        btnTextSpan.innerText = 'Creando presentación...';
        iconElement.className = 'ph ph-spinner ph-spin';
        btnElement.disabled = true;

        const formData = new FormData();
        formData.append('month_id', monthId);
        formData.append('title', postTitle + ' - Slides');

        try {
            const response = await fetch('modules/month_board/ajax_create_slide.php', {
                method: 'POST',
                body: formData
            });
            const res = await response.json();

            if (res.success) {
                // Populate the drive link input
                const driveInput = document.getElementById('post-drive');
                driveInput.value = res.url;
                handleDriveLink(); // Update the preview UI
                markDirty();

                // Open the Google Slide editor in the modal
                openSlideEditorModal();
                
                btnTextSpan.innerText = 'Editor Abierto';
                iconElement.className = 'ph ph-check-circle';
                
                setTimeout(() => {
                    btnTextSpan.innerText = originalText;
                    iconElement.className = originalIcon;
                    btnElement.disabled = false;
                }, 3000);

            } else {
                showToast('Error al crear presentación: ' + (res.error || 'Error desconocido'));
                btnTextSpan.innerText = originalText;
                iconElement.className = originalIcon;
                btnElement.disabled = false;
            }
        } catch (error) {
            showToast('Error de red al intentar crear la presentación.');
            console.error(error);
            btnTextSpan.innerText = originalText;
            iconElement.className = originalIcon;
            btnElement.disabled = false;
        }
    }
</script>

<!-- Modal Editor de Slides -->
<div class="modal-overlay" id="slideEditorModal">
    <div class="modal" style="width: 95vw; max-width: 1400px; height: 95vh; padding: 0; display: flex; flex-direction: column;">
        <div class="modal-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--bg-surface); color: var(--text-main);">
            <h3 style="margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);"><i class="ph ph-file-slides" style="color: #eab308;"></i> Editor de Referencias</h3>
            <button type="button" class="btn-icon" onclick="document.getElementById('slideEditorModal').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <div style="flex: 1; position: relative; background: var(--bg-color);">
            <iframe id="slideEditorIframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>
</div>

<!-- Modal Compartir Vista Pública -->
<div class="modal-overlay" id="shareModal">
    <div class="modal-content share-modal-content">
        <div class="modal-header share-modal-header">
            <h3 class="share-modal-title">
                <i class="ph-bold ph-share-network"></i> Compartir con el Cliente
            </h3>
            <button type="button" class="share-modal-close" onclick="document.getElementById('shareModal').classList.remove('active')" aria-label="Cerrar modal">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        <div class="modal-body share-modal-body">
            <p class="share-modal-description">
                Envía este enlace a tu cliente para que revise el tablero de:
                <strong class="share-modal-board-name"><?php echo htmlspecialchars($monthData['brand_name']); ?> - <?php echo $monthNames[$monthData['month']] . ' ' . $monthData['year']; ?></strong>
            </p>

            <div class="share-pin-card">
                <div class="share-pin-label">
                    <i class="ph-bold ph-lock-key"></i> Proteger con PIN
                </div>
                <label class="switch">
                    <input type="checkbox" id="pin-toggle" onchange="togglePinProtection()" <?php echo !empty($monthData['pin']) ? 'checked' : ''; ?>>
                    <span class="slider round"></span>
                </label>
            </div>

            <div id="pin-container" class="share-pin-box" style="display: <?php echo !empty($monthData['pin']) ? 'block' : 'none'; ?>;">
                <div class="share-pin-box-label">El PIN actual es:</div>
                <div class="share-pin-number" id="current-pin">
                    <?php echo htmlspecialchars($monthData['pin'] ?? '------'); ?>
                </div>
                <button type="button" class="share-pin-btn-refresh" onclick="generateNewPin()">
                    <i class="ph-bold ph-arrows-clockwise"></i> Generar Nuevo PIN
                </button>
            </div>

            <button type="button" class="btn btn-publish share-modal-copy-btn" onclick="copyClientLink()">
                <i class="ph-bold ph-copy"></i> Copiar Enlace Mágico
            </button>
        </div>
    </div>
</div>
<style>
/* === SHARE MODAL STYLES (Light & Dark Mode Compatible) === */
.share-modal-content {
    max-width: 480px;
    padding: 0;
    overflow: hidden;
    border-radius: 20px;
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    box-shadow: var(--shadow-lg, 0 20px 40px rgba(0,0,0,0.25));
    color: var(--text-main, #1e293b);
    transition: background 0.25s ease, border-color 0.25s ease;
}

.share-modal-header {
    background: var(--bg-color, #f8fafc);
    padding: 1.25rem 1.75rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

[data-theme="dark"] .share-modal-header {
    background: rgba(255, 255, 255, 0.04);
}

.share-modal-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    color: var(--text-main, #1e293b);
}

.share-modal-title i {
    color: #10b981;
    font-size: 1.35rem;
}

.share-modal-close {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--text-muted, #64748b);
    transition: all 0.2s ease;
}

.share-modal-close:hover {
    color: var(--text-main, #0f172a);
    background: var(--bg-color, #f1f5f9);
    transform: scale(1.05);
}

[data-theme="dark"] .share-modal-close {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.12);
    color: #94a3b8;
}

[data-theme="dark"] .share-modal-close:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff;
}

.share-modal-body {
    padding: 1.75rem;
    background: var(--bg-surface, #ffffff);
}

.share-modal-description {
    font-size: 0.92rem;
    color: var(--text-muted, #64748b);
    margin-bottom: 1.35rem;
    line-height: 1.6;
}

[data-theme="dark"] .share-modal-description {
    color: #94a3b8;
}

.share-modal-board-name {
    color: var(--text-main, #0f172a);
    font-size: 1.05rem;
    font-weight: 700;
    display: block;
    margin-top: 0.4rem;
    letter-spacing: -0.01em;
}

[data-theme="dark"] .share-modal-board-name {
    color: #f1f5f9;
}

.share-pin-card {
    background: var(--bg-color, #f8fafc);
    padding: 1.1rem 1.35rem;
    border-radius: 14px;
    border: 1px solid var(--border-color, #e2e8f0);
    margin-bottom: 1.35rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
}

[data-theme="dark"] .share-pin-card {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.share-pin-label {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-main, #334155);
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.share-pin-label i {
    font-size: 1.2rem;
    color: #10b981;
}

[data-theme="dark"] .share-pin-label {
    color: #e2e8f0;
}

/* Switch Toggle */
.switch { position: relative; display: inline-block; width: 48px; height: 26px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #cbd5e1;
    transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
}
[data-theme="dark"] .slider {
    background-color: #334155;
}
.slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
input:checked + .slider { background-color: #10b981; }
input:focus + .slider { box-shadow: 0 0 1px #10b981; }
input:checked + .slider:before { transform: translateX(22px); }
.slider.round { border-radius: 26px; }
.slider.round:before { border-radius: 50%; }

/* PIN Box */
.share-pin-box {
    margin-bottom: 1.75rem;
    text-align: center;
    background: var(--bg-color, #f8fafc);
    padding: 1.35rem;
    border-radius: 14px;
    border: 1px dashed var(--border-color, #cbd5e1);
    transition: all 0.2s ease;
}

[data-theme="dark"] .share-pin-box {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.12);
}

.share-pin-box-label {
    font-size: 0.8rem;
    color: var(--text-muted, #64748b);
    margin-bottom: 0.4rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

[data-theme="dark"] .share-pin-box-label {
    color: #94a3b8;
}

.share-pin-number {
    font-size: 2.5rem;
    font-weight: 800;
    letter-spacing: 8px;
    color: var(--text-main, #0f172a);
    font-family: monospace, 'Courier New', Courier;
}

[data-theme="dark"] .share-pin-number {
    color: #f8fafc;
    text-shadow: 0 0 20px rgba(16, 185, 129, 0.25);
}

.share-pin-btn-refresh {
    padding: 0.55rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    margin-top: 0.85rem;
    border-radius: 10px;
    background: var(--bg-surface, white);
    border: 1px solid var(--border-color, #cbd5e1);
    color: var(--text-main, #475569);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    transition: all 0.2s ease;
}

.share-pin-btn-refresh:hover {
    background: var(--bg-color, #f1f5f9);
    border-color: #10b981;
    color: #10b981;
    transform: translateY(-1px);
}

[data-theme="dark"] .share-pin-btn-refresh {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.12);
    color: #e2e8f0;
}

[data-theme="dark"] .share-pin-btn-refresh:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: #10b981;
    color: #34d399;
}

.share-modal-copy-btn {
    width: 100%;
    justify-content: center;
    padding: 0.95rem;
    font-size: 1rem;
    font-weight: 700;
    border-radius: 12px;
    gap: 0.5rem;
    display: flex;
    align-items: center;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
    transition: all 0.2s ease;
}

.share-modal-copy-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.5);
}
</style>

<script>
    function openShareModal() {
        document.getElementById('shareModal').classList.add('active');
    }

    async function togglePinProtection() {
        const isActive = document.getElementById('pin-toggle').checked;
        const container = document.getElementById('pin-container');
        
        if (isActive) {
            container.style.display = 'block';
            if (document.getElementById('current-pin').innerText.trim() === '------') {
                await generateNewPin();
            }
        } else {
            container.style.display = 'none';
            await savePin(''); // Clear PIN
            document.getElementById('current-pin').innerText = '------';
        }
    }

    async function generateNewPin() {
        const pin = Math.floor(100000 + Math.random() * 900000).toString(); // 6 digits
        document.getElementById('current-pin').innerText = pin;
        await savePin(pin);
    }

    async function savePin(pin) {
        const formData = new FormData();
        formData.append('month_id', '<?php echo $monthId; ?>');
        formData.append('pin', pin);
        try {
            await fetch('modules/month_board/ajax_save_pin.php', {
                method: 'POST',
                body: formData
            });
        } catch (e) {
            console.error('Error saving PIN', e);
        }
    }

    function copyClientLink() {
        const baseUrl = window.location.origin + window.location.pathname.replace('index.php', '');
        const link = baseUrl + 'public_board.php?id=<?php echo $monthId; ?>';
        navigator.clipboard.writeText(link).then(() => {
            showToast('¡Enlace copiado al portapapeles!', 'success');
        });
    }

    function openSlideEditorModal() {
        const url = document.getElementById('post-drive').value;
        if (!url || !url.includes('presentation')) {
            showToast('No hay una presentación vinculada.');
            return;
        }
        // Force the URL to edit mode to ensure the toolbar is visible in the modal
        let editUrl = url;
        if (url.includes('/preview')) {
            editUrl = url.replace('/preview', '/edit');
        }
        
        // Si estamos en un dispositivo móvil, abrir en pestaña nueva para activar la app nativa de Google Slides
        const isMobile = window.innerWidth <= 992 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (isMobile) {
            window.open(editUrl, '_blank');
            return; // No abrimos el modal iframe
        }
        
        document.getElementById('slideEditorIframe').src = editUrl;
        document.getElementById('slideEditorModal').classList.add('active');
    }
</script>

<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@1/index.js"></script>
<script>
function toggleEmojiPicker() {
    const picker = document.getElementById('emoji-picker-container');
    if (picker) {
        picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
    }
}
</script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize Swipers
    document.querySelectorAll('.swiper').forEach(function(el) {
        new Swiper(el, {
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
        });
    });
    // Setup Custom WYSIWYG
    const wysiwygEditable = document.getElementById('post-copy-editable');
    const hiddenTextarea = document.getElementById('post-copy');
    
    if (wysiwygEditable && hiddenTextarea) {
        wysiwygEditable.addEventListener('input', function() {
            hiddenTextarea.value = this.innerHTML;
            updateCopyPreview();
            markDirty();
        });

        const emojiPicker = document.querySelector('emoji-picker');
        if (emojiPicker) {
            emojiPicker.addEventListener('emoji-click', event => {
                wysiwygEditable.focus();
                // Ensure focus is restored and text is inserted at cursor
                document.execCommand('insertText', false, event.detail.unicode);
                hiddenTextarea.value = wysiwygEditable.innerHTML;
                updateCopyPreview();
                markDirty();
                toggleEmojiPicker();
            });
            
            // Adjust emoji picker theme based on data-theme
            if (document.documentElement.getAttribute('data-theme') === 'dark') {
                emojiPicker.classList.remove('light');
                emojiPicker.classList.add('dark');
            }
        }
        
        document.addEventListener('click', (e) => {
            const picker = document.getElementById('emoji-picker-container');
            const btn = e.target.closest('.wys-btn');
            if (picker && !picker.contains(e.target) && (!btn || !btn.getAttribute('onclick')?.includes('toggleEmojiPicker'))) {
                picker.style.display = 'none';
            }
        });
    }


    // Detectar cambios de tema para actualizar el editor (opcional, requeriría recargar o destrozar la instancia)
});
</script>
<!-- ========== STUDIO MODE (Redesigned Presentation) ========== -->
<style>
/* ===== Studio Mode CSS — SaaS Premium Redesign ===== */

/* --- Design Tokens --- */
.studio-overlay {
    --s-bg: #000000;
    --s-surface: #0a0a0a;
    --s-surface-2: #141414;
    --s-surface-3: #1f1f1f;
    --s-border: rgba(255,255,255,0.06);
    --s-border-active: rgba(13, 148, 90, 0.5);
    --s-text: #fafafa;
    --s-text-muted: #a1a1aa;
    --s-text-dim: #52525b;
    --s-accent: #0d945a;
    --s-accent-2: #044b36;
    --s-gradient: linear-gradient(to right, #0d945a, #044b36);
    --s-green: #10b981;
    --s-radius: 16px;
    --s-radius-sm: 10px;
    --s-header-h: 56px;
    --s-thumb-h: 100px;
    --s-transition: 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

/* --- Overlay --- */
.studio-overlay {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: var(--s-bg); z-index: 1080; display: none; flex-direction: column;
    font-family: 'Inter', 'Segoe UI', sans-serif;
}
.studio-overlay.active { display: flex; }

/* --- Header --- */
.studio-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 1.5rem; height: var(--s-header-h); min-height: var(--s-header-h);
    background: rgba(0, 0, 0, 0.8);
    border-bottom: 1px solid var(--s-border);
    z-index: 100;
    backdrop-filter: blur(20px) saturate(180%);
}
.studio-header-left { display: flex; align-items: center; gap: 0.85rem; }
.studio-header-left img { height: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.4); }
.studio-header-title {
    color: var(--s-text); font-weight: 700; font-size: 1rem;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 280px;
}
.studio-header-sep { color: rgba(255,255,255,0.12); font-size: 1.2rem; font-weight: 300; }
.studio-status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 14px; border-radius: 20px; font-size: 0.75rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    border: 1px solid transparent;
}
.studio-header-date { color: var(--s-text-muted); font-size: 0.85rem; display: flex; align-items: center; gap: 6px; }
.studio-header-right { display: flex; align-items: center; gap: 0.6rem; }
.studio-header-actions { display: flex; align-items: center; gap: 0.6rem; }
.studio-mobile-menu-btn { display: none; background: transparent; border: 1px solid rgba(255,255,255,0.1); color: #fafafa; border-radius: 8px; cursor: pointer; }

/* Buttons */
.studio-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 16px; border-radius: var(--s-radius-sm); font-size: 0.85rem; font-weight: 600;
    cursor: pointer; border: 1px solid rgba(255,255,255,0.08); transition: all 0.25s ease;
    background: rgba(255,255,255,0.04); color: var(--s-text-muted);
    font-family: inherit;
    position: relative;
}
.studio-btn:hover {
    background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.15);
    color: var(--s-text); transform: translateY(-1px);
}
.studio-btn:active { transform: translateY(0); }
.studio-btn-primary {
    background: var(--s-gradient) !important; border-color: transparent !important;
    color: white !important; box-shadow: 0 4px 15px rgba(13, 148, 90, 0.3);
}
.studio-btn-primary:hover {
    box-shadow: 0 8px 28px rgba(13, 148, 90, 0.45) !important;
    transform: translateY(-2px);
    background: linear-gradient(to right, #0f9f61, #055940) !important;
}
.studio-btn-close {
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); color: var(--s-text-muted); font-size: 1.2rem;
    cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center;
    justify-content: center; border-radius: var(--s-radius-sm); transition: all 0.25s ease;
}
.studio-btn-close:hover { background: rgba(239, 68, 68, 0.12); color: #ef4444; border-color: rgba(239, 68, 68, 0.25); }

/* --- Body --- */
.studio-body {
    flex: 1; display: flex; overflow: hidden; min-height: 0;
}

/* --- Preview (Left) --- */
.studio-preview {
    flex: 1.5; background: #000000; display: flex; align-items: center;
    justify-content: center; position: relative; overflow: hidden;
    border-right: 1px solid var(--s-border);
}
.studio-preview::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at center, rgba(13, 148, 90, 0.02), transparent 70%);
    pointer-events: none;
}
.studio-preview-inner {
    position: relative; width: 100%; height: 100%; display: flex;
    align-items: center; justify-content: center; padding: 1.5rem;
    z-index: 1;
}
.studio-preview-inner img.studio-main-img {
    max-width: 100%; max-height: 100%; border-radius: var(--s-radius);
    box-shadow: 0 24px 80px rgba(0,0,0,0.8), 0 0 0 1px rgba(255,255,255,0.03);
    object-fit: contain;
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.studio-preview-inner .studio-grid-view {
    display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;
    width: 100%; max-width: 700px; max-height: 85vh; padding: 1rem;
}
.studio-preview-inner .studio-grid-view div {
    border-radius: var(--s-radius-sm); overflow: hidden;
    box-shadow: 0 8px 30px rgba(0,0,0,0.5);
    background: var(--s-surface-2);
}
.studio-preview-inner .studio-grid-view img {
    width: 100%; height: 100%; object-fit: cover; display: block;
}

/* --- Details (Right) --- */
.studio-details {
    flex: 1; background: var(--s-surface); color: var(--s-text); display: flex;
    flex-direction: column; overflow-y: auto; min-width: 360px; max-width: 480px;
    position: relative;
}
/* Scroll fade indicators */
.studio-details::before,
.studio-details::after {
    content: ''; position: absolute; left: 0; right: 0; height: 32px;
    pointer-events: none; z-index: 10;
}
.studio-details::before {
    top: 0; background: linear-gradient(to bottom, var(--s-surface), transparent);
}
.studio-details::after {
    bottom: 0; background: linear-gradient(to top, var(--s-surface), transparent);
}
.studio-details-scroll {
    flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem 2rem;
}

/* --- Sections --- */
.studio-section {
    margin-bottom: 1rem; background: rgba(255,255,255,0.02);
    border: 1px solid var(--s-border); border-radius: var(--s-radius-sm);
    overflow: hidden;
    transition: border-color 0.25s ease;
}
.studio-section:hover { border-color: rgba(255,255,255,0.1); }
.studio-section-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.7rem 1rem; font-size: 0.7rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1px; color: var(--s-text-muted);
    border-bottom: 1px solid var(--s-border);
    background: rgba(255,255,255,0.02);
}
.studio-section-body { padding: 1rem; }
.studio-copy-text {
    font-size: 0.92rem; line-height: 1.75; color: #d4d4d8;
    white-space: pre-wrap;
}
.studio-ref-grid {
    display: flex; gap: 0.6rem; overflow-x: auto; padding-bottom: 0.5rem;
}
.studio-ref-grid::-webkit-scrollbar { height: 4px; }
.studio-ref-grid::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }
.studio-ref-grid img {
    height: 72px; width: 72px; object-fit: cover; border-radius: var(--s-radius-sm);
    border: 1px solid var(--s-border); cursor: pointer;
    transition: all 0.25s ease;
}
.studio-ref-grid img:hover {
    transform: scale(1.08); border-color: var(--s-accent);
    box-shadow: 0 6px 20px rgba(13, 148, 90, 0.25);
}
.studio-ref-add {
    height: 72px; width: 72px; border-radius: var(--s-radius-sm); display: flex;
    align-items: center; justify-content: center; font-size: 1.3rem;
    color: var(--s-text-dim); border: 2px dashed var(--s-border);
    cursor: pointer; flex-shrink: 0; transition: all 0.25s ease;
}
.studio-ref-add:hover { border-color: var(--s-accent); color: var(--s-accent); }

/* --- Toolbar --- */
.studio-toolbar {
    display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 1rem;
}
.studio-tool-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 8px; font-size: 0.78rem; font-weight: 600;
    cursor: pointer; border: 1px solid var(--s-border);
    background: transparent; color: var(--s-text-muted); transition: all 0.25s ease;
    font-family: inherit;
}
.studio-tool-btn:hover { background: rgba(255,255,255,0.06); color: var(--s-text); border-color: rgba(255,255,255,0.12); }
.studio-tool-btn.active { background: rgba(13, 148, 90, 0.12); color: var(--s-accent); border-color: rgba(13, 148, 90, 0.3); }

/* --- Thumbstrip --- */
.studio-thumbstrip {
    height: var(--s-thumb-h); min-height: var(--s-thumb-h);
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(20px) saturate(180%);
    border-top: 1px solid var(--s-border);
    display: flex; flex-direction: column; z-index: 50;
    transition: height 0.25s ease, min-height 0.25s ease;
}
.studio-thumbstrip-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 1rem; height: 26px; min-height: 26px;
}
.studio-thumbstrip-label {
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1px; color: var(--s-text-dim);
}
.studio-thumbstrip-body {
    flex: 1; overflow: hidden; padding: 0 1rem 0.4rem;
}
.studio-thumbs .swiper-slide {
    width: 72px !important; height: 72px !important; flex-shrink: 0;
    border-radius: 12px; overflow: hidden; cursor: pointer;
    border: 2px solid rgba(255,255,255,0.04); transition: all 0.25s ease; opacity: 0.45;
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
.studio-thumbs .swiper-slide:hover { opacity: 0.8; border-color: rgba(255,255,255,0.12); transform: scale(1.05); }
.studio-thumbs .swiper-slide-thumb-active {
    border-color: var(--s-accent) !important; opacity: 1;
    box-shadow: 0 0 24px rgba(13, 148, 90, 0.4), 0 4px 12px rgba(0,0,0,0.4);
    transform: scale(1.08);
}
.studio-thumbs .swiper-slide img {
    width: 100%; height: 100%; object-fit: cover; display: block;
}
.studio-thumb-icon {
    width: 100%; height: 100%; display: flex; align-items: center;
    justify-content: center; background: var(--s-surface-2); color: var(--s-text-dim); font-size: 1.5rem;
}
.studio-thumb-label {
    position: absolute; bottom: 0; left: 0; right: 0; text-align: center;
    font-size: 0.5rem; color: rgba(255,255,255,0.6); font-weight: 600;
    background: linear-gradient(transparent, rgba(0,0,0,0.9)); padding: 8px 2px 2px;
}

/* --- Agenda Modal --- */
.studio-agenda-modal {
    display: none; position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%); width: 420px; max-width: 90vw;
    max-height: 85vh; background: var(--s-surface-2); border-radius: var(--s-radius);
    z-index: 1100; flex-direction: column;
    box-shadow: 0 32px 80px rgba(0,0,0,0.9); border: 1px solid var(--s-border);
}
.studio-agenda-modal.active { display: flex; }

/* --- History Overlay --- */
.studio-history-overlay {
    display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.96); z-index: 200; flex-direction: column;
    padding: 2rem; box-sizing: border-box; backdrop-filter: blur(16px);
}
.studio-history-overlay.active { display: flex; }

/* --- Special Slides (Portada / Cierre) --- */
.studio-special-slide {
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; width: 100%; text-align: center; padding: 2rem;
}

/* --- Info Pills --- */
.studio-info-row {
    display: flex; gap: 0.75rem; margin-bottom: 1rem;
}
.studio-info-pill {
    display: flex; flex-direction: column; gap: 4px; flex: 1;
    background: rgba(255,255,255,0.02); padding: 0.65rem 1rem;
    border-radius: var(--s-radius-sm); border: 1px solid var(--s-border);
    transition: border-color 0.25s ease;
}
.studio-info-pill:hover { border-color: rgba(255,255,255,0.1); }
.studio-info-pill-label {
    font-size: 0.62rem; text-transform: uppercase; color: var(--s-text-dim);
    font-weight: 700; letter-spacing: 0.5px;
}
.studio-info-pill-value {
    font-size: 0.88rem; font-weight: 600; color: var(--s-text);
    display: flex; align-items: center; gap: 4px;
}

/* --- Scrollbar --- */
.studio-details-scroll::-webkit-scrollbar { width: 4px; }
.studio-details-scroll::-webkit-scrollbar-track { background: transparent; }
.studio-details-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.06); border-radius: 4px; }
.studio-details-scroll::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.12); }

/* ===== VIDEO PLAYER ===== */
.studio-video-outer {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    position: relative;
}
.studio-video-card {
    position: relative; border-radius: 16px; overflow: hidden;
    box-shadow: 0 28px 80px rgba(0,0,0,0.8), 0 0 0 1px rgba(255,255,255,0.03);
    background: var(--s-surface); display: flex; flex-direction: column;
}
.studio-video-card.ratio-vertical  { width: min(320px, 46vw); aspect-ratio: 9/16; }
.studio-video-card.ratio-horizontal { width: min(600px, 78vw); aspect-ratio: 16/9; }
.studio-video-card.ratio-square     { width: min(450px, 58vw); aspect-ratio: 1/1; }
.studio-video-platform-bar {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px;
    background: rgba(255,255,255,0.02);
    border-bottom: 1px solid var(--s-border);
    flex-shrink: 0;
    font-family: 'Inter', sans-serif;
    font-size: 0.78rem; font-weight: 700;
    color: rgba(255,255,255,0.5);
    letter-spacing: 0.3px;
}
.studio-video-platform-bar .plat-dot {
    width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
}
.studio-video-platform-bar a {
    margin-left: auto; color: rgba(255,255,255,0.25);
    font-size: 0.7rem; text-decoration: none; display: flex; align-items: center; gap: 4px;
    transition: color 0.25s ease;
}
.studio-video-platform-bar a:hover { color: rgba(255,255,255,0.5); }
.studio-video-embed-wrap {
    flex: 1; position: relative; overflow: hidden;
}
.studio-video-embed-wrap iframe,
.studio-video-embed-wrap video {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%; border: none;
}
.studio-video-embed-wrap video {
    object-fit: contain; background: #000;
}
/* Platform glow */
.studio-video-card[data-platform="youtube"]   { box-shadow: 0 28px 80px rgba(255,0,0,0.12), 0 0 0 1px rgba(255,255,255,0.03); }
.studio-video-card[data-platform="tiktok"]    { box-shadow: 0 28px 80px rgba(105,201,208,0.10), 0 0 0 1px rgba(255,255,255,0.03); }
.studio-video-card[data-platform="instagram"] { box-shadow: 0 28px 80px rgba(225,48,108,0.10), 0 0 0 1px rgba(255,255,255,0.03); }
.studio-video-card[data-platform="drive"]     { box-shadow: 0 28px 80px rgba(66,133,244,0.12), 0 0 0 1px rgba(255,255,255,0.03); }
.studio-video-card[data-platform="mp4"]       { box-shadow: 0 28px 80px rgba(13,148,90,0.10), 0 0 0 1px rgba(255,255,255,0.03); }
/* Loading shimmer */
.studio-video-loading {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 12px; color: rgba(255,255,255,0.15); font-family: 'Inter', sans-serif;
    font-size: 0.8rem; pointer-events: none;
    animation: studioFadeOut 0.5s ease 1.5s forwards;
}
@keyframes studioFadeOut { to { opacity: 0; } }

/* ===== SOCIAL PREVIEW CARD ===== */
.studio-social-card {
    position: relative; width: min(280px, 44vw); aspect-ratio: 9/16;
    border-radius: 20px; overflow: hidden;
    box-shadow: 0 32px 80px rgba(0,0,0,0.85), 0 0 0 1px rgba(255,255,255,0.04);
    display: flex; flex-direction: column;
}
.studio-social-bg {
    position: absolute; inset: 0; opacity: 0.12; filter: blur(50px);
}
.studio-social-content {
    position: relative; z-index: 2; flex: 1;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 1.5rem; padding: 2rem 1.5rem;
    text-align: center;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(16px);
}
.studio-social-badge {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.72rem; font-weight: 800; letter-spacing: 1px;
    text-transform: uppercase; color: rgba(255,255,255,0.85);
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    padding: 5px 14px; border-radius: 30px;
}
.studio-social-badge i { font-size: 1rem; }
.studio-social-play-ring {
    width: 85px; height: 85px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.1);
    display: flex; align-items: center; justify-content: center;
    animation: socialPulse 2.5s ease-in-out infinite;
    position: relative;
}
.studio-social-play-ring::before {
    content: ''; position: absolute;
    width: 105px; height: 105px; border-radius: 50%;
    border: 1px solid rgba(255,255,255,0.04);
    animation: socialPulse 2.5s ease-in-out infinite 0.4s;
}
.studio-social-play-inner {
    width: 64px; height: 64px; border-radius: 50%;
    background: rgba(255,255,255,0.06);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.12);
    display: flex; align-items: center; justify-content: center;
    transition: all 0.25s ease;
}
.studio-social-play-inner:hover {
    background: rgba(255,255,255,0.15);
    transform: scale(1.08);
}
@keyframes socialPulse {
    0%,100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.06); opacity: 0.5; }
}
.studio-social-info { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.studio-social-notice {
    display: flex; align-items: center; gap: 5px;
    font-size: 0.7rem; color: rgba(255,255,255,0.3);
    font-family: 'Inter', sans-serif;
}
.studio-social-url {
    font-size: 0.62rem; color: rgba(255,255,255,0.18);
    word-break: break-all; max-width: 200px; line-height: 1.3;
    background: rgba(255,255,255,0.03); border-radius: 8px;
    padding: 5px 10px; border: 1px solid rgba(255,255,255,0.04);
}
.studio-social-cta {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 24px; border-radius: 30px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    color: white; text-decoration: none;
    font-weight: 700; font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: all 0.25s ease;
    backdrop-filter: blur(8px);
}
.studio-social-cta:hover {
    background: rgba(255,255,255,0.15);
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
}

/* ===== STUDIO CALENDAR ===== */
.studio-calendar-wrap .fc {
    --fc-page-bg-color: transparent;
    --fc-neutral-bg-color: var(--s-surface-2);
    --fc-neutral-text-color: var(--s-text-muted);
    --fc-border-color: var(--s-border);
    --fc-event-bg-color: transparent;
    --fc-event-border-color: transparent;
    --fc-today-bg-color: rgba(13, 148, 90, 0.06);
    font-family: 'Inter', sans-serif;
    color: #a1a1aa;
}
.studio-calendar-wrap .fc-toolbar-title {
    font-size: 1.4rem !important;
    font-weight: 800;
    color: var(--s-text);
    text-transform: capitalize;
}
.studio-calendar-wrap .fc-col-header-cell-cushion {
    color: var(--s-text-muted);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    padding: 0.75rem !important;
}
.studio-calendar-wrap .fc-daygrid-day-number {
    color: var(--s-text);
    font-weight: 600;
    font-size: 0.85rem;
    padding: 0.5rem !important;
}
.studio-calendar-wrap .fc-event { cursor: grab; }
.studio-calendar-wrap .fc-event:active { cursor: grabbing; }

/* ===== RESPONSIVE ===== */

/* Tablet */
@media (max-width: 992px) {
    .studio-header {
        height: auto; min-height: 48px;
        padding: 0.6rem 0.85rem;
        flex-wrap: nowrap; gap: 0.5rem;
    }
    .studio-header-left {
        flex-wrap: nowrap; width: auto; justify-content: flex-start;
        gap: 0.5rem; flex: 1; overflow: hidden;
    }
    .studio-header-right {
        flex-wrap: nowrap; width: auto; justify-content: flex-end;
        gap: 0.35rem; position: static; /* allow absolute children to position relative to overlay if needed, or relative to header */
    }
    .studio-header-sep { display: none; }
    .studio-header-title { max-width: 150px; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    
    .studio-header-actions {
        display: none;
        position: absolute;
        top: 50px;
        right: 10px;
        background: #0a0a0a;
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 12px;
        padding: 0.75rem;
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
        z-index: 1200;
        box-shadow: 0 16px 40px rgba(0,0,0,0.8);
    }
    .studio-header-actions.active { display: flex; }
    .studio-mobile-menu-btn { display: flex; align-items: center; justify-content: center; padding: 6px 10px; font-size: 1.2rem; }
    .studio-header-actions .studio-btn { justify-content: flex-start; text-align: left; }

    .studio-body {
        flex-direction: column; overflow-y: auto;
    }
    .studio-preview {
        flex: none; height: 40vh; min-height: 220px;
        border-right: none;
        border-bottom: 1px solid var(--s-border);
    }
    .studio-details {
        flex: none; min-width: 100%; max-width: 100%;
        height: auto; overflow: visible;
    }
    .studio-details::before,
    .studio-details::after { display: none; }
    .studio-details-scroll {
        padding: 1rem; overflow: visible;
    }
    .studio-thumbstrip {
        padding-bottom: 0.75rem;
    }
}

/* Mobile */
@media (max-width: 600px) {
    .studio-overlay { --s-header-h: 44px; --s-thumb-h: 88px; }
    .studio-header {
        padding: 0.5rem 0.75rem;
    }
    .studio-btn { padding: 4px 8px; font-size: 0.7rem; }
    .studio-btn-primary { padding: 4px 10px; }
    .studio-header-title { font-size: 0.8rem; max-width: 140px; }
    .studio-status-badge { font-size: 0.6rem; padding: 2px 8px; }

    .studio-preview { height: 35vh; min-height: 180px; }
    .studio-preview.is-full-height { height: auto !important; flex: 1 !important; border-bottom: none !important; }
    .studio-preview-inner { padding: 0.75rem; }
    .studio-preview-inner img.studio-main-img { border-radius: 8px; }

    .studio-details-scroll { padding: 0.75rem; }
    .studio-section { margin-bottom: 0.75rem; }
    .studio-section-header { padding: 0.5rem 0.75rem; font-size: 0.6rem; }
    .studio-section-body { padding: 0.75rem; }
    .studio-copy-text { font-size: 0.85rem; line-height: 1.65; }

    .studio-info-row { flex-direction: column; gap: 0.5rem; }

    .studio-thumbs .swiper-slide { width: 52px !important; height: 52px !important; }
    .studio-thumb-label { font-size: 0.45rem; }

    .studio-video-card.ratio-vertical  { width: min(260px, 70vw); }
    .studio-video-card.ratio-horizontal { width: min(95vw, 95vw); }
    .studio-video-card.ratio-square     { width: min(85vw, 85vw); }
    .studio-social-card { width: min(240px, 65vw); }
}
</style>

<?php $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']; ?>

<div class="studio-overlay" id="presentation-modal">
    <!-- ===== TOP HEADER ===== -->
    <div class="studio-header" id="studio-header">
        <div class="studio-header-left">
            <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo">
            <span class="studio-header-sep">|</span>
            <span class="studio-header-title" id="studio-post-title"><?php echo htmlspecialchars($monthData['brand_name']); ?></span>
            <span class="studio-status-badge" id="studio-status-badge" style="background: rgba(59,130,246,0.15); color: #58a6ff;">
                <span style="width:6px; height:6px; border-radius:50%; background:currentColor; display:inline-block;"></span>
                PORTADA
            </span>
        </div>
        <div class="studio-header-right">
            <button class="studio-mobile-menu-btn" onclick="document.getElementById('studio-header-actions').classList.toggle('active')">
                <i class="ph ph-dots-three-vertical"></i>
            </button>
            <div class="studio-header-actions" id="studio-header-actions">
                <label id="studio-reviewed-header" class="studio-btn" style="cursor:pointer; display:none; gap:6px; font-weight:600; font-size:0.8rem;">
                    <input type="checkbox" id="studio-reviewed-checkbox" onchange="toggleReviewedFromHeader(this.checked)" style="accent-color: #3fb950; width:15px; height:15px; cursor:pointer;">
                    Aprobado
                </label>
                <button class="studio-btn" onclick="toggleDrawingToolStudio(); document.getElementById('studio-header-actions').classList.remove('active');" id="studio-draw-btn">
                    <i class="ph ph-pencil-simple"></i> Dibujar
                </button>
                <button class="studio-btn" onclick="clearDrawingStudio(); document.getElementById('studio-header-actions').classList.remove('active');" id="studio-clear-draw-btn" style="display:none; color: #f85149; border-color: rgba(248,81,73,0.3);">
                    <i class="ph ph-eraser"></i> Limpiar Dibujo
                </button>
                <button class="studio-btn" onclick="addStickyNote(); document.getElementById('studio-header-actions').classList.remove('active');" id="studio-note-btn" style="display:none;">
                    <i class="ph ph-note"></i> Nota
                </button>
                <button class="studio-btn studio-btn-primary" onclick="finalizeStudioPost(); document.getElementById('studio-header-actions').classList.remove('active');" id="studio-finalize-btn" style="display:none;">
                    Finalizar Publicación
                </button>
                <button class="studio-btn studio-btn-primary" onclick="showToast('¡Listo! Todos los movimientos de fecha en el calendario se guardan automáticamente.', 'success'); document.getElementById('studio-header-actions').classList.remove('active');" id="studio-calendar-save-btn" style="display:none;">
                    Guardar Calendario
                </button>
            </div>
            <button class="studio-btn-close" onclick="closePresentation()"><i class="ph ph-x"></i></button>
        </div>
    </div>

    <!-- ===== MAIN BODY ===== -->
    <div class="studio-body">
        <!-- Left: Visual Preview (no swiper, JS-driven) -->
        <div class="studio-preview" id="studio-preview">
            <div class="studio-preview-inner" id="studio-preview-inner">
                <!-- Portada by default -->
                <div class="studio-special-slide" id="studio-portada">
                    <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" style="max-height: 140px; max-width: 70%; margin-bottom: 2.5rem; opacity: 0.9;">
                    <h1 style="color: #e6edf3; font-size: 3rem; font-weight: 800; margin: 0 0 0.5rem;"><?php echo htmlspecialchars($monthData['brand_name']); ?></h1>
                    <div style="color: #58a6ff; font-size: 1.5rem; font-weight: 600; text-transform: uppercase; letter-spacing: 3px;">
                        <?php echo $meses[$monthData['month']-1] . ' ' . $monthData['year']; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Details Panel -->
        <div class="studio-details" id="studio-details">
            <div class="studio-details-scroll" id="studio-details-scroll">
                <!-- Content injected by JS when a post is selected -->
                <div id="studio-details-content" style="color: #484f58; text-align: center; padding-top: 4rem;">
                    <i class="ph ph-cursor-click" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                    <p style="font-size: 1rem;">Selecciona una publicación del carrusel inferior para ver sus detalles.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== BOTTOM THUMBNAILS STRIP ===== -->
    <div class="studio-thumbstrip">
        <div class="studio-thumbstrip-header">
            <span class="studio-thumbstrip-label">Lista de Publicaciones de Campaña</span>
            <span class="studio-thumbstrip-label" id="studio-counter" style="color: var(--s-accent);">
                1 / <?php echo count($posts) + 2; ?>
            </span>
        </div>
        <div class="studio-thumbstrip-body">
            <div class="swiper studio-thumbs" id="studio-thumbs-swiper">
                <div class="swiper-wrapper">
                    <!-- Portada Thumb -->
                    <div class="swiper-slide" data-slide-type="portada" data-slide-index="0" onclick="goToStudioSlide(0)">
                        <div class="studio-thumb-icon" style="background: linear-gradient(135deg, #141414, #1f1f1f);"><i class="ph ph-house" style="color: #a1a1aa;"></i></div>
                        <div class="studio-thumb-label">Portada</div>
                    </div>
                    
                    <?php if (!empty($posts)): ?>
                    <?php foreach ($posts as $idx => $p):
                        $mediaStr = $p['post_type'] === 'Referencia Visual' ? $p['reference_image_link'] : $p['image_link'];
                        $mediaList = json_decode($mediaStr, true);
                        if (!is_array($mediaList) && !empty($mediaStr)) { $mediaList = [$mediaStr]; }
                        $thumbSrc = (!empty($mediaList) && !empty($mediaList[0]) && is_string($mediaList[0])) ? $mediaList[0] : '';
                        $thumbPostNum = str_pad($idx + 1, 2, '0', STR_PAD_LEFT);
                    ?>
                    <div class="swiper-slide" data-slide-type="post" data-slide-index="<?php echo $idx + 1; ?>" data-post-id="<?php echo $p['id']; ?>" onclick="goToStudioSlide(this.dataset.slideIndex*1)">
                        <?php if ($thumbSrc && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $thumbSrc)): ?>
                            <img src="<?php echo htmlspecialchars($thumbSrc); ?>">
                        <?php else: ?>
                            <div class="studio-thumb-icon" style="background: linear-gradient(135deg, #141414, #1f1f1f);">
                                <i class="ph ph-<?php echo ($thumbSrc ? 'video-camera' : 'image-square'); ?>" style="color: #52525b;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="studio-thumb-label"><?php echo $thumbPostNum; ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Social Mockup Thumbs (dynamic, inserted by JS) -->
                    <div class="swiper-slide studio-social-thumb" data-slide-type="social" data-social-platform="facebook" style="display:none;" onclick="goToStudioSlide(this.dataset.slideIndex*1)">
                        <div class="studio-thumb-icon" style="background: linear-gradient(135deg, #1a3a63, #1877F2);"><i class="ph-fill ph-facebook-logo" style="color: #fff;"></i></div>
                        <div class="studio-thumb-label">FB</div>
                    </div>
                    <div class="swiper-slide studio-social-thumb" data-slide-type="social" data-social-platform="instagram" style="display:none;" onclick="goToStudioSlide(this.dataset.slideIndex*1)">
                        <div class="studio-thumb-icon" style="background: linear-gradient(135deg, #833AB4, #E1306C);"><i class="ph-fill ph-instagram-logo" style="color: #fff;"></i></div>
                        <div class="studio-thumb-label">IG</div>
                    </div>
                    <div class="swiper-slide studio-social-thumb" data-slide-type="social" data-social-platform="tiktok" style="display:none;" onclick="goToStudioSlide(this.dataset.slideIndex*1)">
                        <div class="studio-thumb-icon" style="background: linear-gradient(135deg, #111, #333);"><i class="ph-fill ph-tiktok-logo" style="color: #69C9D0;"></i></div>
                        <div class="studio-thumb-label">TK</div>
                    </div>

                    <!-- Cierre Thumb -->
                    <div class="swiper-slide" data-slide-type="cierre" onclick="goToStudioSlide(this.dataset.slideIndex*1)">
                        <div class="studio-thumb-icon" style="background: linear-gradient(135deg, #052e16, #064e3b);"><i class="ph ph-check-circle" style="color: #10b981;"></i></div>
                        <div class="studio-thumb-label">Fin</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Post Agenda Floating Modal -->
    <div class="studio-agenda-modal" id="post-agenda-modal">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1.2rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.06);">
            <h3 style="color: #e6edf3; margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem; font-weight: 700;">
                <i class="ph ph-list-checks" style="color: #58a6ff;"></i> Agenda
            </h3>
            <button onclick="closePostAgenda()" style="background: none; border: none; color: #8b949e; cursor: pointer; font-size: 1.2rem;"><i class="ph ph-x"></i></button>
        </div>
        <div style="flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
            <input type="hidden" id="agenda-current-post-id" value="">
            <div>
                <label style="color: #8b949e; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem; display: block; letter-spacing: 0.5px;">Apuntes / Feedback</label>
                <textarea id="post-agenda-notes" onblur="savePostAgenda()" style="width: 100%; height: 120px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: #e6edf3; padding: 0.8rem; resize: none; font-family: inherit; font-size: 0.9rem; box-sizing: border-box; outline: none;" placeholder="Escribe notas para este post aquí..."></textarea>
            </div>
            <div>
                <label style="color: #8b949e; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem; display: block; letter-spacing: 0.5px;">Checklist de Tareas</label>
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                    <input type="text" id="agenda-new-task" placeholder="Nueva tarea..." style="flex: 1; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; color: #e6edf3; padding: 0.5rem 0.8rem; font-size: 0.9rem; outline: none;" onkeypress="if(event.key === 'Enter') addAgendaTask()">
                    <button onclick="addAgendaTask()" style="background: #238636; color: white; border: none; border-radius: 6px; padding: 0 1rem; cursor: pointer; font-weight: 600;"><i class="ph ph-plus"></i></button>
                </div>
                <div id="agenda-tasks-container" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Studio Mode JavaScript ===== -->
<script>
// All posts data as JS array (only active posts for studio)
const studioPosts = <?php echo json_encode(isset($activePosts) ? $activePosts : $posts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]'; ?>;
const studioLogo = <?php echo json_encode(htmlspecialchars($logo)) ?: '""'; ?>;
const studioBrand = <?php echo json_encode(htmlspecialchars($monthData['brand_name'])) ?: '""'; ?>;
const studioMonthLabel = <?php echo json_encode($meses[$monthData['month']-1] . ' ' . $monthData['year']) ?: '""'; ?>;
<?php
// Extract team members from work_order data
$woDataRaw = json_decode($monthData['data'], true) ?: [];
$teamMembers = [];
if (!empty($woDataRaw['procesos'])) {
    foreach ($woDataRaw['procesos'] as $proc) {
        if (!empty($proc['rows'])) {
            foreach ($proc['rows'] as $row) {
                if (!empty(trim($row['encargado'] ?? ''))) {
                    $teamMembers[] = trim($row['encargado']);
                }
            }
        }
    }
}
$teamMembers = array_unique($teamMembers);
?>
const studioTeamMembers = <?php echo json_encode(array_values($teamMembers)); ?>;
// Social slides state: which platforms are enabled in presentation
let studioSocialSlides = { facebook: false, instagram: false, tiktok: false };

function getEnabledSocialPlatforms() {
    return ['facebook','instagram','tiktok'].filter(p => studioSocialSlides[p]);
}

function recalcStudioSlides() {
    const enabledSocial = getEnabledSocialPlatforms();
    const totalSlides = studioPosts.length + 2 + enabledSocial.length; // portada + posts + social + cierre
    
    // Reassign data-slide-index on ALL thumb slides
    const allThumbs = document.querySelectorAll('#studio-thumbs-swiper .swiper-slide');
    let slideIdx = 0;
    allThumbs.forEach(thumb => {
        const type = thumb.dataset.slideType;
        if (type === 'social') {
            const platform = thumb.dataset.socialPlatform;
            if (studioSocialSlides[platform]) {
                thumb.style.display = '';
                thumb.dataset.slideIndex = slideIdx;
                slideIdx++;
            } else {
                thumb.style.display = 'none';
                thumb.dataset.slideIndex = '-1';
            }
        } else {
            thumb.dataset.slideIndex = slideIdx;
            slideIdx++;
        }
    });
    
    // Update swiper if initialized
    if (studioThumbsSwiper) {
        studioThumbsSwiper.update();
    }
    
    return totalSlides;
}

function getStudioTotalSlides() {
    return studioPosts.length + 2 + getEnabledSocialPlatforms().length;
}

// Toggle a social platform slide in the presentation
function toggleSocialSlideInPresentation(platform, enabled) {
    studioSocialSlides[platform] = enabled;
    recalcStudioSlides();
}

let studioCurrentSlide = 0;
let studioThumbsSwiper = null;

const studioStatusColors = {
    'Borrador':       { bg: 'rgba(59,130,246,0.15)', color: '#58a6ff' },
    'En Diseño':      { bg: 'rgba(168,85,247,0.15)', color: '#d2a8ff' },
    'Revisión':       { bg: 'rgba(245,158,11,0.15)', color: '#f0b429' },
    'En Revisión':    { bg: 'rgba(245,158,11,0.15)', color: '#f0b429' },
    'Corrección':     { bg: 'rgba(239,68,68,0.15)', color: '#f85149' },
    'Aprobado':       { bg: 'rgba(16,185,129,0.15)', color: '#3fb950' },
    'Programado':     { bg: 'rgba(6,182,212,0.15)', color: '#39d2c0' },
    'Publicado':      { bg: 'rgba(34,197,94,0.15)', color: '#3fb950' },
    'Archivado':      { bg: 'rgba(147, 51, 234, 0.15)', color: '#9333ea' },
};

function setPostStatusAjax(id, status) {
    const isArchiving = (status === 'Archivado');
    const color = isArchiving ? '#9333ea' : '#3b82f6';
    const icon = isArchiving ? 'ph-archive' : 'ph-arrow-u-up-left';
    const title = isArchiving ? '¿Archivar Publicación?' : '¿Restaurar Publicación?';
    const text = `¿Estás seguro de que deseas cambiar el estado a ${status}?`;

    showGenericConfirm(title, text, icon, color, () => {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('status', status);
        fetch('modules/month_board/ajax_update_post_status.php', { method: 'POST', body: fd })
          .then(r => r.text())
          .then(text => {
             try {
                 const res = JSON.parse(text);
                 if(res.success) location.reload();
                 else alert('Error: ' + res.error);
             } catch(e) {
                 console.log("Respuesta de servidor no es JSON válido (probablemente funcionó). Respuesta:", text);
                 location.reload();
             }
          }).catch(e => {
             console.error('Error de conexión:', e);
             location.reload();
          });
    });
}

function startPresentation() {
    document.getElementById('presentation-modal').classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Recalculate slide indices (accounts for enabled social slides)
    recalcStudioSlides();
    
    if (!studioThumbsSwiper) {
        studioThumbsSwiper = new Swiper('#studio-thumbs-swiper', {
            slidesPerView: 'auto',
            spaceBetween: 10,
            freeMode: true,
            watchSlidesProgress: true,
        });
    } else {
        studioThumbsSwiper.update();
    }
    
    goToStudioSlide(0);
}

let studioUnsavedChanges = false;

function closePresentation() {
    if (studioUnsavedChanges) {
        if (!confirm("No has guardado los cambios. ¿Deseas salir de todos modos?")) return;
    }
    document.getElementById('presentation-modal').classList.remove('active');
    document.body.style.overflow = '';
    
    if (window.needsReload) {
        window.location.reload();
    }
}

function goToStudioSlide(idx) {
    if (studioUnsavedChanges) {
        if (!confirm("Tienes cambios sin guardar en esta publicación. ¿Deseas descartarlos y cambiar?")) return;
    }
    studioUnsavedChanges = false;
    studioCurrentSlide = idx;
    
    const totalSlides = getStudioTotalSlides();
    
    // Update counter
    document.getElementById('studio-counter').textContent = (idx + 1) + ' / ' + totalSlides;
    
    // Highlight active thumb by matching data-slide-index
    const allThumbs = document.querySelectorAll('#studio-thumbs-swiper .swiper-slide');
    allThumbs.forEach(t => {
        t.classList.toggle('swiper-slide-thumb-active', t.dataset.slideIndex == idx);
    });
    
    // Scroll thumb into view
    if (studioThumbsSwiper) {
        const visibleThumbs = [...allThumbs].filter(t => t.style.display !== 'none');
        const activeIdx = visibleThumbs.findIndex(t => t.dataset.slideIndex == idx);
        if (activeIdx >= 0) studioThumbsSwiper.slideTo(Math.max(0, activeIdx - 2));
    }
    
    const preview = document.getElementById('studio-preview-inner');
    const detailsScroll = document.getElementById('studio-details-scroll');
    const detailsPanel = document.getElementById('studio-details');
    
    // Determine the slide type from the thumb
    let slideType = 'post';
    let socialPlatform = '';
    const activeThumb = document.querySelector(`#studio-thumbs-swiper .swiper-slide[data-slide-index="${idx}"]`);
    if (activeThumb) {
        slideType = activeThumb.dataset.slideType || 'post';
        socialPlatform = activeThumb.dataset.socialPlatform || '';
    }
    // Fallback: idx 0 is always portada
    if (idx === 0) slideType = 'portada';
    // Last slide is always cierre
    if (idx === totalSlides - 1) slideType = 'cierre';
    
    // Full-width slides (no details panel)
    const isFullWidth = (slideType === 'portada' || slideType === 'cierre' || slideType === 'social');
    if (isFullWidth) {
        detailsPanel.style.display = 'none';
        document.getElementById('studio-preview').classList.add('is-full-height');
    } else {
        detailsPanel.style.display = 'flex';
        document.getElementById('studio-preview').classList.remove('is-full-height');
    }
    
    if (slideType === 'portada') {
        renderPortada(preview, detailsScroll);
    } else if (slideType === 'cierre') {
        renderCierre(preview, detailsScroll);
    } else if (slideType === 'social') {
        renderSocialMockupSlide(socialPlatform, preview, detailsScroll);
    } else {
        // POST — calculate the actual post index
        // Posts start at index 1 and go up to studioPosts.length
        const postIdx = idx - 1;
        if (postIdx >= 0 && postIdx < studioPosts.length) {
            const post = studioPosts[postIdx];
            renderPost(post, preview, detailsScroll);
            initAgendaForPost(post);
        }
        
        // Reset drawing state
        const drawBtn = document.getElementById('studio-draw-btn');
        if (drawBtn) {
            drawBtn.classList.remove('active');
            drawBtn.style.background = '';
            drawBtn.style.color = '';
            drawBtn.style.borderColor = '';
        }
        activeDrawCanvas = null;
        isDrawing = false;
    }
}

// Render a social mockup slide in the presentation preview
function renderSocialMockupSlide(platform, preview, details) {
    const platformNames = { facebook: 'Facebook', instagram: 'Instagram', tiktok: 'TikTok' };
    const platformColors = { facebook: '#1877F2', instagram: '#E1306C', tiktok: '#69C9D0' };
    const platformIcons = { facebook: 'ph-facebook-logo', instagram: 'ph-instagram-logo', tiktok: 'ph-tiktok-logo' };
    
    updateHeader(platformNames[platform] + ' Preview', null, '', 'MOCKUP SOCIAL', {bg:'rgba(102,126,234,0.15)',color:'#667eea'});
    
    // Hide post tools
    document.getElementById('studio-finalize-btn').style.display = 'none';
    document.getElementById('studio-calendar-save-btn').style.display = 'none';
    document.getElementById('studio-draw-btn').style.display = 'none';
    document.getElementById('studio-clear-draw-btn').style.display = 'none';
    document.getElementById('studio-note-btn').style.display = 'none';
    document.getElementById('studio-reviewed-header').style.display = 'none';
    
    // Clone the mockup from the social profiles modal
    const sourceMockup = document.getElementById('spp-mockup-' + platform);
    if (!sourceMockup) {
        preview.innerHTML = `<div class="studio-special-slide"><h2 style="color:#e6edf3;">No hay datos de ${platformNames[platform]}</h2><p style="color:#8b949e;">Abre el modal de Perfiles y configura este mockup primero.</p></div>`;
        details.innerHTML = '';
        return;
    }
    
    // Build a read-only snapshot
    const clone = sourceMockup.cloneNode(true);
    clone.style.display = 'flex';
    clone.style.justifyContent = 'center';
    clone.removeAttribute('id');
    clone.classList.remove('spp-mockup-active');
    
    // Remove all contenteditable and file inputs from clone (read-only)
    clone.querySelectorAll('[contenteditable]').forEach(el => el.removeAttribute('contenteditable'));
    clone.querySelectorAll('input[type="file"]').forEach(el => el.remove());
    clone.querySelectorAll('.spp-upload-overlay').forEach(el => el.remove());
    clone.querySelectorAll('.spp-placeholder-hint').forEach(el => {
        if (el.previousElementSibling && el.previousElementSibling.tagName === 'IMG' && el.previousElementSibling.style.display === 'block') {
            el.remove();
        }
    });
    // Remove onclick from clickable areas
    clone.querySelectorAll('[onclick]').forEach(el => el.removeAttribute('onclick'));
    
    preview.innerHTML = `
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%; padding: 2rem; gap: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem;">
                <i class="ph-fill ${platformIcons[platform]}" style="font-size: 1.5rem; color: ${platformColors[platform]};"></i>
                <span style="color: #8b949e; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 2px;">${platformNames[platform]} Preview</span>
            </div>
            <div id="studio-social-clone-container" style="max-height: calc(100% - 60px); overflow-y: auto; display: flex; justify-content: center; width: 100%;"></div>
        </div>
    `;
    
    document.getElementById('studio-social-clone-container').appendChild(clone);
    details.innerHTML = '';
}

// ===== VIDEO PLAYER HELPERS =====
function detectVideoType(url) {
    if (!url) return null;

    // YouTube (watch, shorts, youtu.be)
    const ytMatch = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})/);
    if (ytMatch) return {
        platform: 'youtube', name: 'YouTube',
        icon: 'ph-youtube-logo', color: '#ff4444',
        embedUrl: `https://www.youtube.com/embed/${ytMatch[1]}?autoplay=0&rel=0&modestbranding=1`,
        ratio: url.includes('/shorts/') ? 'vertical' : 'horizontal', type: 'iframe'
    };

    // TikTok (standard URL with video ID, or vm.tiktok.com short link)
    const ttMatch = url.match(/tiktok\.com\/@[\w.]+\/video\/(\d+)/);
    if (ttMatch) return {
        platform: 'tiktok', name: 'TikTok',
        icon: 'ph-tiktok-logo', color: '#69C9D0',
        embedUrl: `https://www.tiktok.com/embed/v2/${ttMatch[1]}`,
        ratio: 'vertical', type: 'iframe'
    };
    // TikTok short link (vm.tiktok.com)
    if (/vm\.tiktok\.com|tiktok\.com/.test(url)) return {
        platform: 'tiktok', name: 'TikTok',
        icon: 'ph-tiktok-logo', color: '#69C9D0',
        embedUrl: url,
        ratio: 'vertical', type: 'iframe'
    };

    // Instagram Reel or Post (Meta blocks in-page playback)
    if (/instagram\.com\/(?:p|reel|tv)\//i.test(url)) return {
        platform: 'instagram', name: 'Instagram Reel',
        icon: 'ph-instagram-logo', color: '#E1306C',
        gradient: 'linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888)',
        ratio: 'vertical', type: 'social-card'
    };

    // Facebook Reel / Video / fb.watch (Meta blocks in-page playback)
    if (/facebook\.com\/(reel|watch|share\/r|share\/v|video)|fb\.watch/i.test(url)) return {
        platform: 'facebook', name: 'Facebook Reel',
        icon: 'ph-facebook-logo', color: '#1877F2',
        gradient: 'linear-gradient(135deg,#1877F2,#0a5fd8)',
        ratio: 'vertical', type: 'social-card'
    };

    // Pinterest Pin
    if (/pinterest\.com|pin\.it/i.test(url)) return {
        platform: 'pinterest', name: 'Pinterest Pin',
        icon: 'ph-pinterest-logo', color: '#E60023',
        gradient: 'linear-gradient(135deg,#E60023,#B30018)',
        ratio: 'vertical', type: 'social-card'
    };
    
    // General facebook.com link fallback
    if (/facebook\.com/i.test(url)) return {
        platform: 'facebook', name: 'Facebook',
        icon: 'ph-facebook-logo', color: '#1877F2',
        gradient: 'linear-gradient(135deg,#1877F2,#0a5fd8)',
        ratio: 'horizontal', type: 'social-card'
    };

    // Google Drive (file/d/{id}/view or open?id=)
    const driveMatch = url.match(/drive\.google\.com\/(?:file\/d\/([^\/\?&]+)|open\?id=([^&]+))/);
    if (driveMatch) {
        const fileId = driveMatch[1] || driveMatch[2];
        return {
            platform: 'drive', name: 'Google Drive',
            icon: 'ph-cloud', color: '#4285F4',
            embedUrl: `https://drive.google.com/file/d/${fileId}/preview`,
            ratio: 'horizontal', type: 'iframe'
        };
    }

    // MP4 / direct video file
    if (/\.(mp4|webm|ogg|mov)(\?.*)?$/i.test(url)) return {
        platform: 'mp4', name: 'Video',
        icon: 'ph-video', color: '#58a6ff',
        embedUrl: url,
        ratio: 'horizontal', type: 'mp4'
    };

    return null;
}

function buildVideoPlayerHTML(info, originalUrl) {
    // === Social preview card (Instagram / Facebook ?" Meta blocks in-page playback) ===
    if (info.type === 'social-card') {
        const shortUrl = originalUrl.length > 55 ? originalUrl.slice(0, 52) + '...' : originalUrl;
        return `
        <div class="studio-video-outer">
            <div class="studio-social-card" data-platform="${info.platform}">
                <!-- Gradient background -->
                <div class="studio-social-bg" style="background:${info.gradient || info.color};"></div>
                <!-- Content -->
                <div class="studio-social-content">
                    <!-- Platform badge -->
                    <div class="studio-social-badge">
                        <i class="ph ${info.icon}"></i> ${info.name}
                    </div>
                    <!-- Animated play ring -->
                    <div class="studio-social-play-ring">
                        <div class="studio-social-play-inner">
                            <i class="ph ph-play" style="font-size:2.2rem; color:white; margin-left:4px;"></i>
                        </div>
                    </div>
                    <!-- Info -->
                    <div class="studio-social-info">
                        <div class="studio-social-notice">
                            <i class="ph ph-lock-simple"></i>
                            Este video solo se puede reproducir en ${info.name.split(' ')[0]}
                        </div>
                        <div class="studio-social-url">${escHtml(shortUrl)}</div>
                    </div>
                    <!-- CTA -->
                    <a href="${escHtml(originalUrl)}" target="_blank" class="studio-social-cta">
                        <i class="ph ph-play-circle"></i> Ver video en ${info.name.split(' ')[0]}
                        <i class="ph ph-arrow-square-out" style="font-size:0.8rem; opacity:0.7;"></i>
                    </a>
                </div>
            </div>
        </div>`;
    }

    // === Standard embed (YouTube, TikTok, Drive, MP4) ===
    const content = info.type === 'mp4'
        ? `<video controls playsinline preload="metadata">
               <source src="${escHtml(info.embedUrl)}" type="video/mp4">
           </video>`
        : `<div class="studio-video-loading">
               <i class="ph ${info.icon}" style="font-size:2.5rem; color:${info.color};"></i>
               <span>Cargando ${info.name}...</span>
           </div>
           <iframe src="${escHtml(info.embedUrl)}" allowfullscreen allow="autoplay; encrypted-media; fullscreen" loading="lazy"></iframe>`;

    return `
    <div class="studio-video-outer">
        <div class="studio-video-card ratio-${info.ratio}" data-platform="${info.platform}">
            <div class="studio-video-platform-bar">
                <span class="plat-dot" style="background:${info.color};"></span>
                <i class="ph ${info.icon}" style="color:${info.color}; font-size:1rem;"></i>
                ${info.name}
                <a href="${escHtml(originalUrl)}" target="_blank" title="Abrir original">
                    <i class="ph ph-arrow-square-out"></i> Abrir
                </a>
            </div>
            <div class="studio-video-embed-wrap">
                ${content}
            </div>
        </div>
    </div>`;
}

function renderPortada(preview, details) {

    updateHeader('Portada', null, studioMonthLabel, 'PORTADA', {bg:'rgba(13,148,90,0.15)',color:'#10b981'});
    
    // Build team members HTML
    let teamHTML = '';
    if (studioTeamMembers.length > 0) {
        const avatars = studioTeamMembers.map(name => {
            const initials = name.split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
            return `<div style="display: flex; align-items: center; gap: 10px; padding: 6px 16px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); border-radius: 30px;">
                <div style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #0d945a, #044b36); display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 800; color: white; letter-spacing: 0.5px;">${initials}</div>
                <span style="font-size: 0.82rem; color: #a1a1aa; font-weight: 500;">${name}</span>
            </div>`;
        }).join('');
        teamHTML = `
            <div style="margin-top: 2.5rem; display: flex; flex-direction: column; align-items: center; gap: 12px;">
                <div style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #52525b; margin-bottom: 4px;">Equipo Asignado</div>
                <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 8px;">${avatars}</div>
            </div>
        `;
    }
    
    preview.innerHTML = `
        <div class="studio-special-slide">
            <img src="${studioLogo}" alt="Logo" style="max-height: 140px; max-width: 60%; margin-bottom: 2.5rem; opacity: 0.9; filter: drop-shadow(0 8px 24px rgba(0,0,0,0.4)); border-radius: 16px; overflow: hidden; border: 1px solid rgba(255,255,255,0.08);">
            <h1 style="color: #fafafa; font-size: clamp(2rem, 8vw, 3.2rem); font-weight: 800; margin: 0 0 0.5rem; letter-spacing: -1px; text-align: center; line-height: 1.15;">${studioBrand}</h1>
            <div style="color: #0d945a; font-size: clamp(1rem, 4vw, 1.5rem); font-weight: 600; text-transform: uppercase; letter-spacing: 4px; text-align: center;">${studioMonthLabel}</div>
            ${teamHTML}
        </div>
    `;
    
    details.innerHTML = '';
    
    document.getElementById('studio-finalize-btn').style.display = 'none';
    document.getElementById('studio-calendar-save-btn').style.display = 'none';
    document.getElementById('studio-draw-btn').style.display = 'none';
    document.getElementById('studio-clear-draw-btn').style.display = 'none';
    document.getElementById('studio-note-btn').style.display = 'none';
    document.getElementById('studio-reviewed-header').style.display = 'none';
}

function renderCalendarSlide(preview) {
    updateHeader('Calendario', null, '', 'CALENDARIO DE CONTENIDOS', {bg:'rgba(88,166,255,0.15)',color:'#58a6ff'});
    // Hide post-specific tools, show calendar save button
    document.getElementById('studio-finalize-btn').style.display = 'none';
    document.getElementById('studio-calendar-save-btn').style.display = 'inline-flex';
    document.getElementById('studio-draw-btn').style.display = 'none';
    document.getElementById('studio-clear-draw-btn').style.display = 'none';
    document.getElementById('studio-note-btn').style.display = 'none';
    document.getElementById('studio-reviewed-header').style.display = 'none';

    preview.innerHTML = `
        <div class="studio-calendar-wrap" style="width: 100%; height: 100%; display: flex; flex-direction: column; padding: 2rem;">
            <div id="studio-calendar" style="flex: 1; background: #0d1117; border-radius: 16px; border: 1px solid rgba(255,255,255,0.06); padding: 1.5rem;"></div>
        </div>
    `;

    const calEl = document.getElementById('studio-calendar');
    
    // Create events from studioPosts
    const events = studioPosts.map((p, idx) => {
        let platformIcon = 'ph-image';
        let platformColor = '#64748b';
        let imageUrl = '';
        
        let mediaStr = p.post_type === 'Referencia Visual' ? p.reference_image_link : p.image_link;
        
        try {
            const urls = JSON.parse(mediaStr);
            if (Array.isArray(urls) && urls.length > 0) imageUrl = urls[0];
        } catch(e) {
            imageUrl = mediaStr;
        }

        const vidInfo = detectVideoType(imageUrl);
        if (vidInfo) {
            platformIcon = vidInfo.icon;
            platformColor = vidInfo.color;
        } else if (p.platform) {
            if (p.platform.toLowerCase().includes('instagram')) { platformIcon = 'ph-instagram-logo'; platformColor = '#E1306C'; }
            else if (p.platform.toLowerCase().includes('facebook')) { platformIcon = 'ph-facebook-logo'; platformColor = '#1877F2'; }
            else if (p.platform.toLowerCase().includes('tiktok')) { platformIcon = 'ph-tiktok-logo'; platformColor = '#00f2fe'; }
            else if (p.platform.toLowerCase().includes('linkedin')) { platformIcon = 'ph-linkedin-logo'; platformColor = '#0A66C2'; }
        }

        let bg = 'rgba(255,255,255,0.05)';
        if (p.status) {
            bg = (studioStatusColors[p.status] || {}).bg || bg;
        }

        return {
            id: p.id,
            title: p.concept || 'Post',
            start: p.post_date ? p.post_date.replace(' ', 'T') : null,
            extendedProps: {
                imageUrl: imageUrl,
                platformIcon: platformIcon,
                platformColor: platformColor,
                statusBg: bg,
                slideIndex: idx + 1 // +1 because Portada is 0
            }
        };
    });

    const monthStr = <?php echo json_encode(str_pad($monthData['month'], 2, '0', STR_PAD_LEFT)) ?: '""'; ?>;
    const yearStr = <?php echo json_encode($monthData['year']) ?: '""'; ?>;
    const initDate = yearStr + '-' + monthStr + '-01';

    const calendar = new FullCalendar.Calendar(calEl, {
        initialView: 'dayGridMonth',
        initialDate: initDate,
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },
        height: '100%',
        events: events,
        editable: true,
        droppable: true,
        eventDisplay: 'block',
        dayCellDidMount: function(arg) {
            const m = arg.date.getMonth() + 1;
            const d = arg.date.getDate();
            const dateStr = (m < 10 ? '0'+m : m) + '-' + (d < 10 ? '0'+d : d);
            const holidays = {
                '01-01': 'Año Nuevo',
                '02-14': 'San Valentín',
                '03-08': 'Día de la Mujer',
                '05-01': 'Día del Trabajo',
                '05-10': 'Día de la Madre',
                '06-16': 'Día del Padre',
                '10-31': 'Halloween',
                '11-01': 'Todos los Santos',
                '12-25': 'Navidad',
                '12-31': 'Fin de Año'
            };
            if (holidays[dateStr]) {
                const inner = arg.el.querySelector('.fc-daygrid-day-top');
                if (inner) {
                    const badge = document.createElement('div');
                    badge.innerHTML = `<i class="ph ph-calendar-star"></i> ${holidays[dateStr]}`;
                    badge.style.cssText = 'font-size: 0.65rem; color: #f59e0b; font-weight: bold; padding: 2px 4px; background: rgba(245, 158, 11, 0.1); border-radius: 4px; margin: 2px auto 2px 2px; text-transform: uppercase; line-height: 1; text-align: left;';
                    inner.prepend(badge);
                }
            }
        },
        eventContent: function(arg) {
            const props = arg.event.extendedProps;
            let thumbHtml = '';
            
            if (props.imageUrl) {
                // Determine if it's an image or we just show an icon
                const isVideo = detectVideoType(props.imageUrl);
                if (isVideo && isVideo.type !== 'mp4') {
                    // Show a colored icon block for embeds
                    thumbHtml = `<div style="width: 100%; height: 60px; background: rgba(0,0,0,0.4); display:flex; align-items:center; justify-content:center; border-radius:4px; margin-bottom:4px;">
                                    <i class="ph ${isVideo.icon}" style="font-size: 1.5rem; color: ${isVideo.color};"></i>
                                 </div>`;
                } else {
                    // Show image thumbnail
                    thumbHtml = `<div style="width: 100%; height: 60px; background: rgba(0,0,0,0.4); border-radius:4px; margin-bottom:4px; overflow:hidden;">
                                    <img src="${props.imageUrl}" style="width:100%; height:100%; object-fit:cover;">
                                 </div>`;
                }
            }

            let html = `
                <div style="background: ${props.statusBg}; padding: 4px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); cursor: pointer;" onclick="goToStudioSlide(${props.slideIndex})">
                    ${thumbHtml}
                    <div style="display: flex; align-items: center; gap: 4px; font-size: 0.65rem; color: #c9d1d9; font-weight: 600; line-height: 1.2;">
                        <i class="ph ${props.platformIcon}" style="color: ${props.platformColor};"></i>
                        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${arg.event.title}</span>
                    </div>
                </div>
            `;
            return { html: html };
        },
        eventDrop: async function(info) {
            const newDateStr = info.event.start.toISOString().split('T')[0];
            const postId = info.event.id;
            
            // Call AJAX to update db
            const formData = new FormData();
            formData.append('post_id', postId);
            formData.append('post_date', newDateStr);

            try {
                const res = await fetch('modules/community/ajax_update_post_date.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (!data.success) {
                    showToast('Error al actualizar: ' + data.error);
                    info.revert();
                } else {
                    // Update in memory so changes are reflected in slide view
                    const post = studioPosts.find(p => p.id == postId);
                    if (post) post.post_date = newDateStr;
                    
                    // Also update main grid without reloading
                    const mainCardDate = document.getElementById('post-date-' + postId);
                    if (mainCardDate) {
                        // format to "d M" roughly
                        const d = new Date(newDateStr + 'T00:00:00');
                        const months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                        mainCardDate.innerHTML = `<i class="ph ph-calendar-blank"></i> ${d.getDate()} ${months[d.getMonth()]}`;
                    }
                }
            } catch (e) {
                showToast('Error de conexión');
                info.revert();
            }
        }
    });

    calendar.render();
}

function renderCierre(preview, details) {
    updateHeader('Cierre', null, '', 'CIERRE', {bg:'rgba(16,185,129,0.15)',color:'#3fb950'});
    
    const agencyLogo = <?php echo json_encode(htmlspecialchars(!empty($global_settings['logo_dark']) ? $global_settings['logo_dark'] : 'assets/img/default-logo.png')) ?: '""'; ?>;
    
    // Build team members HTML
    let teamHTML = '';
    if (studioTeamMembers.length > 0) {
        const avatars = studioTeamMembers.map(name => {
            const initials = name.split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
            return `<div style="display: flex; align-items: center; gap: 10px; padding: 6px 16px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); border-radius: 30px;">
                <div style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #0d945a, #044b36); display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 800; color: white; letter-spacing: 0.5px;">${initials}</div>
                <span style="font-size: 0.82rem; color: #a1a1aa; font-weight: 500;">${name}</span>
            </div>`;
        }).join('');
        teamHTML = `
            <div style="margin-top: 2.5rem; display: flex; flex-direction: column; align-items: center; gap: 12px;">
                <div style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #52525b; margin-bottom: 4px;">Equipo Asignado</div>
                <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 8px;">${avatars}</div>
            </div>
        `;
    }
    
    preview.innerHTML = `
        <div class="studio-special-slide">
            <h2 style="color: #e6edf3; font-size: 3.5rem; font-weight: 800; margin-bottom: 1rem;">¡Gracias!</h2>
            <p style="color: #8b949e; font-size: 1.3rem; margin-bottom: 3rem; max-width: 550px;">Esperamos tus comentarios para proceder con la aprobación y programación del contenido.</p>
            <img src="${agencyLogo}" alt="Agency Logo" style="max-height: 50px; max-width: 80%; opacity: 0.6;">
            ${teamHTML}
        </div>
    `;
    
    details.innerHTML = '';
    
    document.getElementById('studio-finalize-btn').style.display = 'none';
    document.getElementById('studio-calendar-save-btn').style.display = 'none';
    document.getElementById('studio-draw-btn').style.display = 'none';
    document.getElementById('studio-clear-draw-btn').style.display = 'none';
    document.getElementById('studio-note-btn').style.display = 'none';
    document.getElementById('studio-reviewed-header').style.display = 'none';
}

// Update post date from presentation mode
async function updatePostDate(postId, newDateStr) {
    if (!newDateStr) return;
    
    // Convert YYYY-MM-DDTHH:mm to YYYY-MM-DD HH:mm:00 for DB
    const dbDateStr = newDateStr.length === 16 ? newDateStr.replace('T', ' ') + ':00' : newDateStr.replace('T', ' ');

    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('post_date', dbDateStr);

    try {
        const res = await fetch('modules/community/ajax_update_post_date.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (!data.success) {
            showToast('Error al actualizar: ' + data.error);
        } else {
            // Update in memory
            const post = studioPosts.find(p => p.id == postId);
            if (post) post.post_date = dbDateStr;
            
            // Also update main grid
            const mainCardDate = document.getElementById('post-date-' + postId);
            if (mainCardDate) {
                const d = new Date(dbDateStr.replace(' ', 'T'));
                const months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                mainCardDate.innerHTML = `<i class="ph ph-calendar-blank"></i> ${d.getDate()} ${months[d.getMonth()]}`;
            }
        }
    } catch (e) {
        console.error(e);
        showToast('Error de conexión');
    }
}

function renderPost(post, preview, details) {
    const sc = studioStatusColors[post.status] || studioStatusColors['Borrador'];
    const dateFmt = new Date(post.post_date).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    
    updateHeader(post.concept, post.platform, dateFmt, post.status, sc);
    
    document.getElementById('studio-finalize-btn').style.display = 'inline-flex';
    document.getElementById('studio-calendar-save-btn').style.display = 'none';
    document.getElementById('studio-draw-btn').style.display = 'inline-flex';
    document.getElementById('studio-clear-draw-btn').style.display = post.drawing_data ? 'inline-flex' : 'none';
    document.getElementById('studio-note-btn').style.display = 'inline-flex';
    
    // Show and set Revisado in header
    const reviewedHeader = document.getElementById('studio-reviewed-header');
    reviewedHeader.style.display = 'inline-flex';
    const reviewedCheckbox = document.getElementById('studio-reviewed-checkbox');
    reviewedCheckbox.checked = !!post.reviewed;
    reviewedCheckbox.dataset.postId = post.id;
    
    // === PREVIEW (left) ===
    const mediaStr = post.post_type === 'Referencia Visual' ? post.reference_image_link : post.image_link;
    let mediaList = [];
    try { mediaList = JSON.parse(mediaStr); } catch(e) {}
    // Fallback: if parse failed (empty array) or result is not an array, treat raw string as single item
    if (!Array.isArray(mediaList) || mediaList.length === 0) {
        mediaList = mediaStr ? [mediaStr] : [];
    }
    
    let previewHTML = '';
    if (mediaList.length === 0) {
        previewHTML = `<div style="text-align: center; color: rgba(255,255,255,0.15);"><i class="ph ph-image-square" style="font-size: 6rem; margin-bottom: 1rem; display: block;"></i><span style="font-weight: 600; font-size: 1.2rem;">Sin Recurso Visual</span></div>`;
    } else if (mediaList.length > 1) {
        const carouselId = 'carousel-' + Math.random().toString(36).substr(2, 9);
        previewHTML = `
        <div class="custom-carousel" id="${carouselId}" style="position:relative; width:100%; height:100%; overflow:hidden; border-radius: 8px;">
            <div class="carousel-track" style="display:flex; overflow-x:auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; width:100%; height:100%; scrollbar-width: none;">`;
        mediaList.forEach((img, idx) => {
            const isImgFile = /\.(jpg|jpeg|png|gif|webp)$/i.test(img);
            const listJson = JSON.stringify(mediaList).replace(/"/g, '&quot;');
            const clickAttr = isImgFile ? `onclick="openLightbox(${listJson}, ${idx})" style="cursor:zoom-in; width:100%; height:100%; object-fit:contain; border-radius: 8px;" title="Click para hacer zoom"` : `style="width:100%; height:100%; object-fit:contain; border-radius: 8px;"`;
            previewHTML += `
                <div style="flex: 0 0 100%; scroll-snap-align: center; position:relative; width:100%; height:100%; overflow:hidden; display:flex; justify-content:center; align-items:center;">
                    <img src="${escHtml(img)}" ${clickAttr}>
                    <div style="position:absolute; bottom:10px; right:10px; background:rgba(0,0,0,0.6); color:white; font-size:0.7rem; padding:3px 8px; border-radius:12px; pointer-events:none;">${idx + 1} / ${mediaList.length}</div>
                </div>`;
        });
        previewHTML += `
            </div>
            <button onclick="event.stopPropagation(); document.getElementById('${carouselId}').querySelector('.carousel-track').scrollBy({left: -300, behavior: 'smooth'});" style="position:absolute; top:50%; left:5px; transform:translateY(-50%); background:rgba(255,255,255,0.8); border:none; border-radius:50%; width:30px; height:30px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(0,0,0,0.2);"><i class="ph ph-caret-left" style="color:#333; font-weight:bold;"></i></button>
            <button onclick="event.stopPropagation(); document.getElementById('${carouselId}').querySelector('.carousel-track').scrollBy({left: 300, behavior: 'smooth'});" style="position:absolute; top:50%; right:5px; transform:translateY(-50%); background:rgba(255,255,255,0.8); border:none; border-radius:50%; width:30px; height:30px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(0,0,0,0.2);"><i class="ph ph-caret-right" style="color:#333; font-weight:bold;"></i></button>
        </div>
        <style>
            #${carouselId} .carousel-track::-webkit-scrollbar { display: none; }
        </style>`;
    } else {
        const first = mediaList[0];
        const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(first);
        const videoInfo = detectVideoType(first);
        
        if (isImage) {
            previewHTML = `<div style="position:relative; display:inline-flex; align-items:center; justify-content:center; width:100%; height:100%;">
                <img class="studio-main-img" src="${escHtml(first)}" onclick="openLightbox(['${escHtml(first)}'], 0)" style="cursor:zoom-in;" title="Click para hacer zoom">
                <div style="position:absolute; bottom:12px; right:12px; background:rgba(0,0,0,0.55); color:rgba(255,255,255,0.85); font-size:0.72rem; font-weight:600; padding:4px 10px; border-radius:20px; pointer-events:none; display:flex; align-items:center; gap:5px; backdrop-filter:blur(4px); border:1px solid rgba(255,255,255,0.1);">
                    <i class="ph ph-magnifying-glass-plus"></i> Zoom
                </div>
            </div>`;
        } else if (videoInfo) {
            previewHTML = buildVideoPlayerHTML(videoInfo, first);
        } else {
            // Unknown URL ?" premium external link card
            previewHTML = `<div style="text-align:center; color:rgba(255,255,255,0.5); display:flex; flex-direction:column; align-items:center; gap:1.5rem; padding:2rem;">
                <div style="width:80px; height:80px; border-radius:50%; background:rgba(88,166,255,0.1); border:1px solid rgba(88,166,255,0.2); display:flex; align-items:center; justify-content:center;">
                    <i class="ph ph-link" style="font-size:2.5rem; color:#58a6ff;"></i>
                </div>
                <div>
                    <div style="font-size:1rem; font-weight:600; color:#e6edf3; margin-bottom:0.5rem;">Recurso Externo</div>
                    <div style="font-size:0.8rem; color:#484f58; word-break:break-all; max-width:320px;">${escHtml(first)}</div>
                </div>
                <a href="${escHtml(first)}" target="_blank" style="background:#238636; color:white; padding:0.6rem 1.5rem; border-radius:8px; text-decoration:none; font-weight:600; font-size:0.9rem; display:flex; align-items:center; gap:6px;">
                    <i class="ph ph-arrow-square-out"></i> Abrir Enlace
                </a>
            </div>`;
        }
    }
    preview.innerHTML = previewHTML;
    
    // Restore saved drawing if exists
    if (post.drawing_data) {
        restoreDrawing(preview, post.drawing_data);
    }
    
    // Restore saved sticky notes if exist
    restoreStickyNotes(preview, post);
    
    // === DETAILS (right) ===
    const postJson = JSON.stringify(post).replace(/'/g, '&#39;').replace(/</g, '\\u003c');
    
    // Reference images
    let refHTML = '';
    if (post.post_type !== 'Referencia Visual' && post.reference_image_link) {
        let refList = [];
        try { refList = JSON.parse(post.reference_image_link); } catch(e) {}
        if (!Array.isArray(refList) && post.reference_image_link) refList = [post.reference_image_link];
        if (refList && refList.length > 0 && refList[0]) {
            refHTML = `
                <div class="studio-section">
                    <div class="studio-section-header">
                        <span>Inspiración Visual</span>
                        <span style="color: #58a6ff; cursor: pointer; font-size: 0.7rem;">Añadir más</span>
                    </div>
                    <div class="studio-section-body">
                        <div class="studio-ref-grid">
                            ${refList.map((r, i) => `<img src="${escHtml(r)}" onclick="openLightbox(${JSON.stringify(refList).replace(/"/g, '&quot;')}, ${i})" style="cursor:pointer;">`).join('')}
                            <div class="studio-ref-add"><i class="ph ph-plus"></i></div>
                        </div>
                    </div>
                </div>
            `;
        }
    }
    
    // Copy text
    const copyText = (post.copy_text || '').replace(/<\/p>/g, '\n').replace(/<br\s*\/?>/g, '\n').replace(/<[^>]+>/g, '');
    const charCount = copyText.trim().length;
    
    // Design brief
    let briefHTML = '';
    if (post.design_brief) {
        const briefText = post.design_brief.replace(/<\/p>/g, '\n').replace(/<br\s*\/?>/g, '\n').replace(/<[^>]+>/g, '');
        briefHTML = `
            <div class="studio-section">
                <div class="studio-section-header"><span>Brief de Diseño</span></div>
                <div class="studio-section-body">
                    <div class="studio-copy-text" style="color: #8b949e; font-size: 0.9rem;">${escHtml(briefText)}</div>
                </div>
            </div>
        `;
    }
    
    // Revisions button
    let historyBtn = '';
    if (post.revisions && post.revisions.length > 0) {
        historyBtn = `<button class="studio-tool-btn" onclick="showHistory(${post.id})"><i class="ph ph-clock-counter-clockwise"></i> Historial</button>`;
    }
    
    details.innerHTML = `
        <!-- Info Pills -->
        <div class="studio-info-row">
            <div class="studio-info-pill" style="flex:1; cursor:pointer;" onclick="document.getElementById('studio-date-input').showPicker()">
                <span class="studio-info-pill-label">Post Date <i class="ph ph-pencil-simple" style="font-size: 0.7rem; margin-left: 4px;"></i></span>
                <span class="studio-info-pill-value" style="color: #f0b429; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph ph-calendar-blank"></i> 
                    <input type="datetime-local" id="studio-date-input" value="${post.post_date ? post.post_date.replace(' ', 'T') : ''}" 
                           onchange="updatePostDate(${post.id}, this.value)"
                           style="background:transparent; border:none; color:inherit; font-family:inherit; font-size:inherit; outline:none; font-weight:600; cursor:pointer;">
                </span>
            </div>
            <div class="studio-info-pill" style="flex:1;">
                <span class="studio-info-pill-label">Tipo</span>
                <span class="studio-info-pill-value">${escHtml(post.post_type || 'Publicación')}</span>
            </div>
        </div>
        

        ${refHTML}
        
        <!-- Copy Text -->
        <div class="studio-section">
            <div class="studio-section-header">
                <span>Texto / Copy</span>
                <span style="color: #484f58;">${charCount} / 2200</span>
            </div>
            <div class="studio-section-body">
                <div class="studio-copy-text">${escHtml(copyText)}</div>
            </div>
        </div>
        
        ${briefHTML}
        
        <!-- History Overlay (per post) -->
        ${renderHistoryOverlay(post)}
    `;
}

function renderHistoryOverlay(post) {
    if (!post.revisions || post.revisions.length === 0) return '';
    let revsHTML = '';
    post.revisions.forEach(rev => {
        const rDate = new Date(rev.created_at).toLocaleDateString('es-ES', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
        let rImgs = [];
        try { rImgs = JSON.parse(rev.image_link); } catch(e) {}
        if (!Array.isArray(rImgs) && rev.image_link) rImgs = [rev.image_link];
        
        revsHTML += `<div style="margin-bottom: 1.5rem;">
            <div style="font-size:0.85rem; color:#8b949e; margin-bottom:0.5rem; font-weight:600;">Subido el ${rDate}</div>
            <div style="display:flex; gap:1rem; overflow-x:auto;">
                ${(rImgs||[]).map(ri => /\.(jpg|jpeg|png|gif|webp)$/i.test(ri) ? `<img src="${escHtml(ri)}" style="height:120px; border-radius:8px; border:2px solid rgba(255,255,255,0.08);">` : `<div style="height:120px;width:120px;background:#161b22;border-radius:8px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.3);"><i class="ph ph-file-video" style="font-size:2rem;"></i></div>`).join('')}
            </div>
        </div>`;
    });
    
    return `<div id="history-modal-${post.id}" class="studio-history-overlay">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <h2 style="font-size:1.3rem; font-weight:700; margin:0; color:#e6edf3;"><i class="ph ph-clock-counter-clockwise"></i> Versiones Anteriores</h2>
            <button onclick="hideHistory(${post.id})" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); color:white; width:36px; height:36px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center;"><i class="ph ph-x"></i></button>
        </div>
        <div style="flex:1; overflow-y:auto;">${revsHTML}</div>
    </div>`;
}

function updateHeader(title, platform, dateStr, statusText, statusColors) {
    document.getElementById('studio-post-title').textContent = title;
    
    const badge = document.getElementById('studio-status-badge');
    badge.style.background = statusColors.bg;
    badge.style.color = statusColors.color;
    badge.innerHTML = `<span style="width:6px; height:6px; border-radius:50%; background:currentColor; display:inline-block;"></span> ${escHtml(statusText)}`;
}

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Keyboard navigation
document.addEventListener('keydown', function(event) {
    const modal = document.getElementById('presentation-modal');
    if (!modal.classList.contains('active')) return;
    
    if (event.key === 'Escape') {
        // If lightbox is open, close only the lightbox ?" don't close the presentation
        const lb = document.getElementById('studio-lightbox');
        if (lb && lb.style.display !== 'none') { closeLightbox(); return; }
        closePresentation();
        return;
    }
    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        if (studioCurrentSlide < getStudioTotalSlides() - 1) goToStudioSlide(studioCurrentSlide + 1);
    }
    if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        if (studioCurrentSlide > 0) goToStudioSlide(studioCurrentSlide - 1);
    }
});

// ===== EXISTING FUNCTIONS (preserved) =====
function toggleReviewed(postId, isChecked) {
    const fd = new FormData();
    fd.append('post_id', postId);
    fd.append('reviewed', isChecked ? 1 : 0);
    fetch('modules/month_board/ajax_toggle_reviewed.php', { method: 'POST', body: fd });
}

function toggleReviewedFromHeader(isChecked) {
    const checkbox = document.getElementById('studio-reviewed-checkbox');
    const postId = checkbox.dataset.postId;
    if (!postId) return;
    
    toggleReviewed(postId, isChecked);
    
    // Update local data
    const post = studioPosts.find(x => x.id == postId);
    if (post) {
        post.reviewed = isChecked ? 1 : 0;
        if (isChecked) {
            post.status = 'Aprobado';
            post.drawing_data = null;
            post.sticky_notes = null;
            // Update badge to Aprobado
            const badge = document.getElementById('studio-status-badge');
            const sc = studioStatusColors['Aprobado'];
            badge.style.background = sc.bg;
            badge.style.color = sc.color;
            badge.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span> APROBADO';
            // Remove drawings and sticky notes from preview
            const preview = document.getElementById('studio-preview-inner');
            if (preview) {
                preview.querySelectorAll('canvas.draw-overlay').forEach(c => c.remove());
                preview.querySelectorAll('.studio-sticky-note').forEach(n => n.remove());
            }
            document.getElementById('studio-clear-draw-btn').style.display = 'none';
        } else {
            post.status = 'En Revisión';
            // Update badge to En Revisión
            const badge = document.getElementById('studio-status-badge');
            const sc = studioStatusColors['En Revisión'];
            badge.style.background = sc.bg;
            badge.style.color = sc.color;
            badge.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span> EN REVISIÓN';
        }
    }
    
    window.needsReload = true;
}

function openPresenterNotes(postId) {
    const url = 'modules/month_board/presenter_notes.php?post_id=' + postId;
    window.open(url, 'PresenterNotes', 'width=400,height=500,left=100,top=100,menubar=no,toolbar=no,location=no,status=no');
}

function showHistory(postId) {
    const el = document.getElementById('history-modal-' + postId);
    if (el) { el.classList.add('active'); }
    else { showToast('No hay versiones anteriores.'); }
}
function hideHistory(postId) {
    const el = document.getElementById('history-modal-' + postId);
    if (el) el.classList.remove('active');
}

function toggleCommentsPanel(postObj) {
    closePresentation();
    setTimeout(() => {
        if (typeof editPost === 'function') {
            editPost(postObj);
            setTimeout(() => {
                const tabs = document.querySelectorAll('.post-modal-tabs .tab');
                if (tabs.length > 1) tabs[1].click();
            }, 100);
        }
    }, 300);
}

// Post Agenda
let currentAgendaTasks = [];
let agendaCache = {};

let currentAgendaPostId = null;

function initAgendaForPost(postObj) {
    const postId = postObj.id;
    currentAgendaPostId = postId;
    const notesEl = document.getElementById('post-agenda-notes');
    if (!notesEl) return; // Si es portada o cierre
    
    document.getElementById('agenda-current-post-id').value = postId;
    
    if (agendaCache[postId]) {
        notesEl.value = agendaCache[postId].notes;
        currentAgendaTasks = agendaCache[postId].tasks;
    } else {
        notesEl.value = postObj.presenter_notes || '';
        currentAgendaTasks = [];
        if (postObj.agenda_tasks) {
            try {
                currentAgendaTasks = typeof postObj.agenda_tasks === 'string' ? JSON.parse(postObj.agenda_tasks) : postObj.agenda_tasks;
            } catch(e) {}
        }
    }
    
    renderAgendaTasks();
}

function renderAgendaTasks() {
    const container = document.getElementById('agenda-tasks-container');
    container.innerHTML = '';
    currentAgendaTasks.forEach((task, idx) => {
        const isChecked = task.done ? 'checked' : '';
        const textStyle = task.done ? 'text-decoration: line-through; color: #484f58;' : 'color: #c9d1d9;';
        container.innerHTML += `
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; background: rgba(255,255,255,0.02); padding: 0.5rem; border-radius: 6px;">
                <input type="checkbox" onchange="toggleAgendaTask(${idx}, this.checked)" ${isChecked} style="margin-top: 0.2rem; accent-color: #3fb950;">
                <span style="flex: 1; line-height: 1.3; ${textStyle}">${task.text}</span>
                <button type="button" onclick="deleteAgendaTask(${idx})" style="background: none; border: none; color: #f85149; cursor: pointer;"><i class="ph ph-trash"></i></button>
            </label>
        `;
    });
}

function addAgendaTask() {
    const input = document.getElementById('agenda-new-task');
    const text = input.value.trim();
    if (!text) return;
    currentAgendaTasks.push({ text: text, done: false });
    input.value = '';
    renderAgendaTasks();
    studioUnsavedChanges = true;
}

function toggleAgendaTask(idx, isDone) {
    if (currentAgendaTasks[idx]) {
        currentAgendaTasks[idx].done = isDone;
        renderAgendaTasks();
        studioUnsavedChanges = true;
    }
}

function deleteAgendaTask(idx) {
    currentAgendaTasks.splice(idx, 1);
    renderAgendaTasks();
    studioUnsavedChanges = true;
}

function savePostAgenda() {
    if (!currentAgendaPostId) return;
    const postId = currentAgendaPostId;
    const notesEl = document.getElementById('post-agenda-notes');
    const notes = notesEl ? notesEl.value : '';
    
    agendaCache[postId] = { notes, tasks: JSON.parse(JSON.stringify(currentAgendaTasks)) };
    
    // Update local DB cache so changes persist locally without full reload
    const p = studioPosts.find(x => x.id == postId);
    if (p) {
        p.presenter_notes = notes;
        p.agenda_tasks = JSON.stringify(currentAgendaTasks);
    }
    
    const fd = new FormData();
    fd.append('post_id', postId);
    fd.append('notes', notes);
    fd.append('tasks', JSON.stringify(currentAgendaTasks));
    fetch('modules/month_board/ajax_save_post_agenda.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .catch(err => console.error('Error saving agenda:', err));
}

function finalizeStudioPost() {
    const input = document.getElementById('agenda-new-task');
    if (input && input.value.trim() !== '') {
        currentAgendaTasks.push({ text: input.value.trim(), done: false });
        input.value = '';
        renderAgendaTasks();
    }
    savePostAgenda();
    saveDrawingData();
    saveStickyNotes();
    studioUnsavedChanges = false;
    showToast('Publicacion guardada exitosamente.', 'success');
}

function saveDrawingData() {
    if (!currentAgendaPostId) return;
    const preview = document.getElementById('studio-preview-inner');
    const canvas = preview ? preview.querySelector('canvas.draw-overlay') : null;
    let drawingDataUrl = null;
    
    if (canvas) {
        // Check if canvas has any drawn content (not completely transparent)
        const ctx = canvas.getContext('2d');
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        let hasContent = false;
        for (let i = 3; i < imageData.data.length; i += 4) {
            if (imageData.data[i] > 0) { hasContent = true; break; }
        }
        if (hasContent) {
            drawingDataUrl = canvas.toDataURL('image/png');
        }
    }
    
    const fd = new FormData();
    fd.append('post_id', currentAgendaPostId);
    fd.append('drawing_data', drawingDataUrl || '');
    fetch('modules/month_board/ajax_save_drawing.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Update local cache
                const p = studioPosts.find(x => x.id == currentAgendaPostId);
                if (p) p.drawing_data = drawingDataUrl || null;
            }
        })
        .catch(err => console.error('Error saving drawing:', err));
}

// Drawing tool
let activeDrawCanvas = null;
let drawCtx = null;
let isDrawing = false;

function restoreDrawing(preview, dataUrl) {
    const img = new Image();
    img.onload = function() {
        const canvas = document.createElement('canvas');
        canvas.className = 'draw-overlay saved-drawing';
        canvas.width = preview.clientWidth || img.width;
        canvas.height = preview.clientHeight || img.height;
        canvas.style.position = 'absolute';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.zIndex = '40';
        canvas.style.pointerEvents = 'none';
        preview.appendChild(canvas);
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        // Show clear drawing button
        const clearBtn = document.getElementById('studio-clear-draw-btn');
        if (clearBtn) clearBtn.style.display = 'inline-flex';
    };
    img.src = dataUrl;
}

function clearDrawingStudio() {
    if (!confirm('¿Deseas borrar todos los trazos de esta publicacion?')) return;
    const preview = document.getElementById('studio-preview-inner');
    if (preview) {
        preview.querySelectorAll('canvas.draw-overlay').forEach(c => c.remove());
    }
    // Deactivate draw mode if active
    const btn = document.getElementById('studio-draw-btn');
    if (btn) {
        btn.classList.remove('active');
        btn.style.background = '';
        btn.style.color = '';
        btn.style.borderColor = '';
    }
    activeDrawCanvas = null;
    isDrawing = false;
    
    // Hide clear button
    const clearBtn = document.getElementById('studio-clear-draw-btn');
    if (clearBtn) clearBtn.style.display = 'none';
    
    // Clear from DB immediately
    if (currentAgendaPostId) {
        const fd = new FormData();
        fd.append('post_id', currentAgendaPostId);
        fd.append('drawing_data', '');
        fetch('modules/month_board/ajax_save_drawing.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .catch(err => console.error('Error clearing drawing:', err));
        // Clear local cache
        const p = studioPosts.find(x => x.id == currentAgendaPostId);
        if (p) p.drawing_data = null;
    }
    studioUnsavedChanges = false;
}

function toggleDrawingToolStudio() {
    const preview = document.getElementById('studio-preview-inner');
    const btn = document.getElementById('studio-draw-btn');
    let canvas = preview.querySelector('canvas.draw-overlay:not(.saved-drawing)');
    
    if (canvas) {
        // Deactivating draw mode: convert active canvas to saved overlay
        canvas.style.pointerEvents = 'none';
        canvas.style.cursor = 'default';
        canvas.classList.add('saved-drawing');
        btn.classList.remove('active');
        btn.style.background = '';
        btn.style.color = '';
        btn.style.borderColor = '';
        activeDrawCanvas = null;
        studioUnsavedChanges = true;
        // Show clear button since there are drawings now
        const clearBtn = document.getElementById('studio-clear-draw-btn');
        if (clearBtn) clearBtn.style.display = 'inline-flex';
    } else {
        // Activating draw mode: remove any saved overlay and create interactive canvas
        const savedCanvas = preview.querySelector('canvas.saved-drawing');
        
        canvas = document.createElement('canvas');
        canvas.className = 'draw-overlay';
        canvas.width = preview.clientWidth;
        canvas.height = preview.clientHeight;
        canvas.style.position = 'absolute';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.zIndex = '50';
        canvas.style.cursor = 'crosshair';
        preview.appendChild(canvas);
        
        drawCtx = canvas.getContext('2d');
        
        // If there was a saved drawing, load it into the new canvas first
        if (savedCanvas) {
            drawCtx.drawImage(savedCanvas, 0, 0, canvas.width, canvas.height);
            savedCanvas.remove();
        }
        
        drawCtx.strokeStyle = '#f85149';
        drawCtx.lineWidth = 4;
        drawCtx.lineCap = 'round';
        drawCtx.lineJoin = 'round';
        
        btn.classList.add('active');
        btn.style.background = 'rgba(248,81,73,0.15)';
        btn.style.color = '#f85149';
        btn.style.borderColor = 'rgba(248,81,73,0.3)';
        
        activeDrawCanvas = canvas;
        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', endDraw);
        canvas.addEventListener('mouseout', endDraw);
        canvas.addEventListener('touchstart', (e) => { e.preventDefault(); startDraw(e.touches[0]); }, {passive: false});
        canvas.addEventListener('touchmove', (e) => { e.preventDefault(); draw(e.touches[0]); }, {passive: false});
        canvas.addEventListener('touchend', endDraw);
    }
}

function startDraw(e) {
    if (!activeDrawCanvas) return;
    e.preventDefault();
    isDrawing = true;
    const rect = activeDrawCanvas.getBoundingClientRect();
    drawCtx.beginPath();
    drawCtx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
}
// Event listener for canvas aspect ratio change
document.addEventListener('DOMContentLoaded', () => {
    const ratioSelect = document.getElementById('paint-aspect-ratio');
    if (ratioSelect) {
        ratioSelect.addEventListener('change', function(e) {
            if (!fabricCanvas) return;
            const ratio = e.target.value;
            let cW = fabricCanvas.width;
            let cH = fabricCanvas.height;
            
            if (ratio === '1:1') { cW = 1080; cH = 1080; }
            else if (ratio === '4:1') { cW = 1920; cH = 480; }
            else if (ratio === '4:5') { cW = 1080; cH = 1350; }
            else if (ratio === '3:4') { cW = 1080; cH = 1440; }
            else if (ratio === '9:16') { cW = 1080; cH = 1920; }
            else if (ratio === '4:3') { cW = 1440; cH = 1080; }
            else if (ratio === '16:9') { cW = 1920; cH = 1080; }
            else return; // auto: do nothing, leave current dimensions
            
            // Calculate center difference to keep objects visually centered
            const dx = (cW - fabricCanvas.width) / 2;
            const dy = (cH - fabricCanvas.height) / 2;
            
            fabricCanvas.setWidth(cW);
            fabricCanvas.setHeight(cH);
            
            const wrap = fabricCanvas.wrapperEl;
            const p = wrap.parentElement;
            const scale = Math.min((p.clientWidth - 32) / cW, (p.clientHeight - 32) / cH, 1);
            wrap.style.width = Math.floor(cW * scale) + 'px';
            wrap.style.height = Math.floor(cH * scale) + 'px';
            fabricCanvas.calcOffset();
            
            fabricCanvas.getObjects().forEach(obj => {
                obj.set({ left: obj.left + dx, top: obj.top + dy });
                obj.setCoords();
            });
            
            fabricCanvas.renderAll();
            paintSaveHist();
        });
    }
});

function draw(e) {
    if (!isDrawing || !activeDrawCanvas) return;
    e.preventDefault();
    const rect = activeDrawCanvas.getBoundingClientRect();
    drawCtx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
    drawCtx.stroke();
}
function endDraw(e) { 
    if(e) e.preventDefault(); 
    isDrawing = false; 
}

// ===== STICKY NOTES =====
let currentStickyNotes = [];

function addStickyNote() {
    const preview = document.getElementById('studio-preview-inner');
    if (!preview) return;
    
    const noteId = 'sn-' + Date.now();
    const noteData = {
        id: noteId,
        x: 10 + Math.random() * 20,
        y: 10 + Math.random() * 20,
        text: '',
        color: '#fef08a'
    };
    currentStickyNotes.push(noteData);
    createStickyNoteEl(preview, noteData, true);
    studioUnsavedChanges = true;
}

function createStickyNoteEl(container, noteData, autoFocus) {
    const colors = ['#fef08a', '#bbf7d0', '#bfdbfe', '#fbcfe8', '#fed7aa'];
    const colorIdx = currentStickyNotes.indexOf(noteData) % colors.length;
    const bgColor = noteData.color || colors[colorIdx];
    
    const el = document.createElement('div');
    el.id = noteData.id;
    el.className = 'studio-sticky-note';
    
    // Use saved size or defaults
    const noteW = noteData.width || 160;
    const noteH = noteData.height || 130;
    
    el.style.cssText = `
        position: absolute;
        left: ${noteData.x}%;
        top: ${noteData.y}%;
        width: ${noteW}px;
        height: ${noteH}px;
        min-width: 120px;
        min-height: 80px;
        background: ${bgColor};
        border-radius: 4px;
        box-shadow: 2px 3px 12px rgba(0,0,0,0.35);
        z-index: 60;
        font-family: 'Inter', sans-serif;
        cursor: grab;
        display: flex;
        flex-direction: column;
        transform: rotate(${noteData.rotation !== undefined ? noteData.rotation : (Math.random() * 4 - 2).toFixed(1)}deg);
        overflow: hidden;
    `;
    
    // Store initial rotation in data
    if (noteData.rotation === undefined) {
        noteData.rotation = parseFloat(el.style.transform.replace('rotate(', '').replace('deg)', ''));
    }
    
    // Header bar (drag handle + color + delete)
    const header = document.createElement('div');
    header.style.cssText = 'display:flex; align-items:center; justify-content:space-between; padding:4px 6px; border-bottom:1px solid rgba(0,0,0,0.08); cursor:grab; user-select:none; flex-shrink:0;';
    header.innerHTML = `
        <div style="display:flex; gap:3px;">
            ${colors.map(c => `<span onclick="changeStickyColor('${noteData.id}','${c}')" style="width:12px;height:12px;border-radius:50%;background:${c};cursor:pointer;border:1px solid rgba(0,0,0,0.15);${c===bgColor?'box-shadow:0 0 0 2px rgba(0,0,0,0.3);':''}"></span>`).join('')}
        </div>
        <button onclick="deleteStickyNote('${noteData.id}')" style="background:none;border:none;color:#991b1b;cursor:pointer;font-size:14px;padding:0 2px;line-height:1;"><i class="ph ph-x-circle"></i></button>
    `;
    el.appendChild(header);
    
    // Text area
    const textarea = document.createElement('textarea');
    textarea.value = noteData.text;
    textarea.placeholder = 'Escribe una nota...';
    textarea.style.cssText = 'flex:1; border:none; background:transparent; resize:none; padding:6px 8px; font-size:0.75rem; line-height:1.4; color:#1a1a1a; font-family:inherit; outline:none; width:100%; box-sizing:border-box;';
    textarea.oninput = function() {
        const nd = currentStickyNotes.find(n => n.id === noteData.id);
        if (nd) nd.text = this.value;
        studioUnsavedChanges = true;
    };
    // Auto-save on blur so text persists when switching slides
    textarea.onblur = function() {
        const nd = currentStickyNotes.find(n => n.id === noteData.id);
        if (nd) nd.text = this.value;
        saveStickyNotes();
    };
    el.appendChild(textarea);
    
    // Resize handle (bottom-right corner)
    const resizeHandle = document.createElement('div');
    resizeHandle.style.cssText = 'position:absolute; bottom:0; right:0; width:16px; height:16px; cursor:se-resize; display:flex; align-items:center; justify-content:center; color:rgba(0,0,0,0.3); font-size:10px; line-height:1; user-select:none; z-index:5;';
    resizeHandle.innerHTML = '<i class="ph ph-dots-six"></i>';
    el.appendChild(resizeHandle);
    
    // Resize logic
    makeResizable(el, resizeHandle, noteData);
    
    // Drag logic
    makeDraggable(el, header, container, noteData);
    
    container.appendChild(el);
    
    if (autoFocus) {
        setTimeout(() => textarea.focus(), 100);
    }
}

function makeResizable(el, handle, noteData) {
    let isResizing = false;
    let startX, startY, startW, startH;
    
    handle.addEventListener('mousedown', function(e) {
        e.stopPropagation();
        e.preventDefault();
        isResizing = true;
        startX = e.clientX;
        startY = e.clientY;
        startW = el.offsetWidth;
        startH = el.offsetHeight;
        el.style.transform = 'rotate(0deg)'; // Straighten during resize for accuracy
        document.addEventListener('mousemove', onResize);
        document.addEventListener('mouseup', stopResize);
    });
    
    function onResize(e) {
        if (!isResizing) return;
        const newW = Math.max(120, startW + (e.clientX - startX));
        const newH = Math.max(80, startH + (e.clientY - startY));
        el.style.width = newW + 'px';
        el.style.height = newH + 'px';
    }
    
    function stopResize() {
        if (!isResizing) return;
        isResizing = false;
        // Restore rotation
        el.style.transform = `rotate(${noteData.rotation || 0}deg)`;
        noteData.width = el.offsetWidth;
        noteData.height = el.offsetHeight;
        studioUnsavedChanges = true;
        document.removeEventListener('mousemove', onResize);
        document.removeEventListener('mouseup', stopResize);
    }
}

function makeDraggable(el, handle, container, noteData) {
    let isDragging = false;
    let startX, startY, origLeft, origTop;
    
    handle.addEventListener('mousedown', startDrag);
    handle.addEventListener('touchstart', (e) => { e.preventDefault(); startDrag(e.touches[0]); }, {passive: false});
    
    function startDrag(e) {
        isDragging = true;
        el.style.cursor = 'grabbing';
        el.style.zIndex = '65';
        startX = e.clientX;
        startY = e.clientY;
        origLeft = parseFloat(el.style.left);
        origTop = parseFloat(el.style.top);
        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', endDragEvt);
        document.addEventListener('touchmove', onTouchDrag, {passive: false});
        document.addEventListener('touchend', endDragEvt);
    }
    
    function onDrag(e) {
        if (!isDragging) return;
        const rect = container.getBoundingClientRect();
        const dx = ((e.clientX - startX) / rect.width) * 100;
        const dy = ((e.clientY - startY) / rect.height) * 100;
        const newX = Math.max(0, Math.min(85, origLeft + dx));
        const newY = Math.max(0, Math.min(85, origTop + dy));
        el.style.left = newX + '%';
        el.style.top = newY + '%';
    }
    
    function onTouchDrag(e) {
        e.preventDefault();
        onDrag(e.touches[0]);
    }
    
    function endDragEvt() {
        if (!isDragging) return;
        isDragging = false;
        el.style.cursor = 'grab';
        el.style.zIndex = '60';
        noteData.x = parseFloat(el.style.left);
        noteData.y = parseFloat(el.style.top);
        studioUnsavedChanges = true;
        document.removeEventListener('mousemove', onDrag);
        document.removeEventListener('mouseup', endDragEvt);
        document.removeEventListener('touchmove', onTouchDrag);
        document.removeEventListener('touchend', endDragEvt);
    }
}

function changeStickyColor(noteId, color) {
    const nd = currentStickyNotes.find(n => n.id === noteId);
    if (nd) nd.color = color;
    const el = document.getElementById(noteId);
    if (el) el.style.background = color;
    studioUnsavedChanges = true;
}

function deleteStickyNote(noteId) {
    currentStickyNotes = currentStickyNotes.filter(n => n.id !== noteId);
    const el = document.getElementById(noteId);
    if (el) el.remove();
    studioUnsavedChanges = true;
}

function restoreStickyNotes(preview, post) {
    // Remove existing sticky notes
    preview.querySelectorAll('.studio-sticky-note').forEach(el => el.remove());
    currentStickyNotes = [];
    
    if (post.sticky_notes) {
        try {
            const notes = typeof post.sticky_notes === 'string' ? JSON.parse(post.sticky_notes) : post.sticky_notes;
            if (Array.isArray(notes)) {
                currentStickyNotes = notes;
                notes.forEach(n => createStickyNoteEl(preview, n, false));
            }
        } catch(e) {}
    }
}

function saveStickyNotes() {
    if (!currentAgendaPostId) return;
    
    // Update local cache
    const p = studioPosts.find(x => x.id == currentAgendaPostId);
    if (p) p.sticky_notes = JSON.stringify(currentStickyNotes);
    
    const fd = new FormData();
    fd.append('post_id', currentAgendaPostId);
    fd.append('sticky_notes', JSON.stringify(currentStickyNotes));
    fetch('modules/month_board/ajax_save_sticky_notes.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .catch(err => console.error('Error saving sticky notes:', err));
}

// ===== LIGHTBOX IMAGE VIEWER =====
let lightboxImages = [];
let lightboxIndex = 0;

function openLightbox(images, idx) {
    lightboxImages = images;
    lightboxIndex = idx || 0;
    const lb = document.getElementById('studio-lightbox');
    lb.style.display = 'flex';
    resetLightboxZoom();
    updateLightbox();
}

function closeLightbox() {
    document.getElementById('studio-lightbox').style.display = 'none';
    lightboxImages = [];
    resetLightboxZoom();
}

function lightboxPrev() {
    if (lightboxImages.length <= 1) return;
    lightboxIndex = (lightboxIndex - 1 + lightboxImages.length) % lightboxImages.length;
    resetLightboxZoom();
    updateLightbox();
}

function lightboxNext() {
    if (lightboxImages.length <= 1) return;
    lightboxIndex = (lightboxIndex + 1) % lightboxImages.length;
    resetLightboxZoom();
    updateLightbox();
}

function updateLightbox() {
    const img = document.getElementById('lightbox-img');
    const counter = document.getElementById('lightbox-counter');
    img.src = lightboxImages[lightboxIndex];
    counter.textContent = (lightboxIndex + 1) + ' / ' + lightboxImages.length;
    
    // Show/hide arrows
    document.getElementById('lightbox-prev').style.display = lightboxImages.length > 1 ? 'flex' : 'none';
    document.getElementById('lightbox-next').style.display = lightboxImages.length > 1 ? 'flex' : 'none';
}

// ===== LIGHTBOX ZOOM & PAN =====
let lbZoom = 1;
let lbPanX = 0, lbPanY = 0;
let lbIsPanning = false;
let lbPanStartX, lbPanStartY;

function resetLightboxZoom() {
    lbZoom = 1; lbPanX = 0; lbPanY = 0;
    applyLightboxTransform();
    updateLightboxZoomUI();
}

function lbZoomIn() {
    lbZoom = Math.min(lbZoom * 1.3, 6);
    applyLightboxTransform();
    updateLightboxZoomUI();
}

function lbZoomOut() {
    lbZoom = Math.max(lbZoom / 1.3, 0.5);
    if (lbZoom <= 1) { lbZoom = 1; lbPanX = 0; lbPanY = 0; }
    applyLightboxTransform();
    updateLightboxZoomUI();
}

function applyLightboxTransform() {
    const img = document.getElementById('lightbox-img');
    if (!img) return;
    img.style.transform = `scale(${lbZoom}) translate(${lbPanX / lbZoom}px, ${lbPanY / lbZoom}px)`;
    img.style.cursor = lbZoom > 1 ? 'grab' : 'zoom-in';
    img.style.transition = lbIsPanning ? 'none' : 'transform 0.15s ease';
}

function updateLightboxZoomUI() {
    const zoomLabel = document.getElementById('lightbox-zoom-label');
    if (zoomLabel) zoomLabel.textContent = Math.round(lbZoom * 100) + '%';
}

// Wire zoom events after DOM ready
document.addEventListener('DOMContentLoaded', function() {
    const img = document.getElementById('lightbox-img');
    if (!img) return;
    
    // Mouse-wheel zoom
    img.parentElement.addEventListener('wheel', function(e) {
        const lb = document.getElementById('studio-lightbox');
        if (!lb || lb.style.display === 'none') return;
        e.preventDefault();
        const delta = e.deltaY > 0 ? 0.85 : 1.15;
        lbZoom = Math.min(Math.max(lbZoom * delta, 0.5), 6);
        if (lbZoom <= 1) { lbZoom = 1; lbPanX = 0; lbPanY = 0; }
        applyLightboxTransform();
        updateLightboxZoomUI();
    }, { passive: false });
    
    // Drag to pan
    img.addEventListener('mousedown', function(e) {
        if (lbZoom <= 1) return;
        e.preventDefault();
        lbIsPanning = true;
        lbPanStartX = e.clientX - lbPanX;
        lbPanStartY = e.clientY - lbPanY;
        img.style.cursor = 'grabbing';
    });
    document.addEventListener('mousemove', function(e) {
        if (!lbIsPanning) return;
        lbPanX = e.clientX - lbPanStartX;
        lbPanY = e.clientY - lbPanStartY;
        applyLightboxTransform();
    });
    document.addEventListener('mouseup', function() {
        if (!lbIsPanning) return;
        lbIsPanning = false;
        applyLightboxTransform();
    });
    
    // Double-click to zoom in/reset
    img.addEventListener('dblclick', function(e) {
        if (lbZoom > 1) { resetLightboxZoom(); }
        else { lbZoomIn(); lbZoomIn(); }
    });
});

// Keyboard support for lightbox
document.addEventListener('keydown', function(e) {
    const lb = document.getElementById('studio-lightbox');
    if (!lb || lb.style.display === 'none') return;
    if (e.key === 'Escape') { closeLightbox(); e.stopPropagation(); }
    if (e.key === 'ArrowLeft') { lightboxPrev(); e.stopPropagation(); }
    if (e.key === 'ArrowRight') { lightboxNext(); e.stopPropagation(); }
    if (e.key === '+' || e.key === '=') { lbZoomIn(); e.stopPropagation(); }
    if (e.key === '-') { lbZoomOut(); e.stopPropagation(); }
    if (e.key === '0') { resetLightboxZoom(); e.stopPropagation(); }
});
</script>

<!-- Lightbox Overlay -->
<div id="studio-lightbox" style="
    display: none;
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.92);
    z-index: 9999;
    align-items: center; justify-content: center;
    flex-direction: column;
">
    <!-- Close -->
    <button onclick="closeLightbox()" style="
        position: absolute; top: 16px; right: 20px;
        background: rgba(255,255,255,0.1); border: none; color: #fff;
        width: 40px; height: 40px; border-radius: 50%;
        font-size: 1.3rem; cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: background 0.2s;
        z-index: 10;
    " onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
        <i class="ph ph-x"></i>
    </button>
    
    <!-- Counter -->
    <div id="lightbox-counter" style="
        position: absolute; top: 20px; left: 50%; transform: translateX(-50%);
        color: rgba(255,255,255,0.6); font-size: 0.85rem; font-weight: 600;
        font-family: 'Inter', sans-serif; letter-spacing: 1px;
    ">1 / 1</div>
    
    <!-- Zoom controls -->
    <div style="position:absolute; bottom:20px; left:50%; transform:translateX(-50%); display:flex; align-items:center; gap:8px; z-index:10; font-family:'Inter',sans-serif;">
        <button onclick="lbZoomOut()" title="Alejar (-)" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.15); color:#fff; width:34px; height:34px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:1.1rem; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
            <i class="ph ph-minus"></i>
        </button>
        <span id="lightbox-zoom-label" onclick="resetLightboxZoom()" title="Clic para restablecer zoom" style="color:rgba(255,255,255,0.7); font-size:0.8rem; font-weight:700; min-width:42px; text-align:center; cursor:pointer; padding:4px 8px; background:rgba(255,255,255,0.07); border-radius:12px; letter-spacing:0.5px;">100%</span>
        <button onclick="lbZoomIn()" title="Acercar (+)" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.15); color:#fff; width:34px; height:34px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:1.1rem; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
            <i class="ph ph-plus"></i>
        </button>
    </div>
    
    <!-- Prev Arrow -->
    <button id="lightbox-prev" onclick="lightboxPrev()" style="
        position: absolute; left: 20px; top: 50%; transform: translateY(-50%);
        background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1);
        color: #fff; width: 48px; height: 48px; border-radius: 50%;
        font-size: 1.4rem; cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: all 0.2s; z-index: 10;
    " onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
        <i class="ph ph-caret-left"></i>
    </button>
    
    <!-- Image -->
    <img id="lightbox-img" src="" alt="Preview" style="
        max-width: 85vw; max-height: 85vh;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        user-select: none;
        transition: opacity 0.2s;
    " onclick="event.stopPropagation()">
    
    <!-- Next Arrow -->
    <button id="lightbox-next" onclick="lightboxNext()" style="
        position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
        background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1);
        color: #fff; width: 48px; height: 48px; border-radius: 50%;
        font-size: 1.4rem; cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: all 0.2s; z-index: 10;
    " onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
        <i class="ph ph-caret-right"></i>
    </button>
    
    <!-- Open external -->
    <button onclick="window.open(lightboxImages[lightboxIndex], '_blank')" style="
        position: absolute; bottom: 20px; right: 20px;
        background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.7); padding: 8px 16px; border-radius: 8px;
        font-size: 0.8rem; cursor: pointer; font-family: 'Inter', sans-serif;
        display: flex; align-items: center; gap: 6px;
        transition: all 0.2s;
    " onmouseover="this.style.background='rgba(255,255,255,0.15)';this.style.color='#fff'" onmouseout="this.style.background='rgba(255,255,255,0.08)';this.style.color='rgba(255,255,255,0.7)'">
        <i class="ph ph-arrow-square-out"></i> Abrir original
    </button>
</div>



<!-- Modal Subir Archivos a Drive -->
<div class="modal-overlay" id="upload-drive-modal">
    <div class="modal-content" style="max-width: 550px; padding: 0; overflow: hidden; border-radius: 20px;">
        <div class="modal-header" style="background: #f8fafc; padding: 1.5rem 2rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.3rem; display: flex; align-items: center; gap: 0.75rem; color: #1e293b;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(13, 148, 90, 0.1); color: #0d945a; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    <i class="ph ph-upload-simple"></i>
                </div>
                Subir Archivos
            </h3>
            <button class="btn-icon" onclick="document.getElementById('upload-drive-modal').classList.remove('active')" style="background: white; border: 1px solid #e2e8f0; border-radius: 50%; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); cursor: pointer; position: relative; margin: 0;">
                <i class="ph ph-x" style="color: #64748b;"></i>
            </button>
        </div>
        <div class="modal-body" style="padding: 2rem;">
            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 1.75rem; line-height: 1.5; text-align: left;">
                Sube archivos pesados (>2 GB) directamente a las carpetas de Google Drive del proyecto. La subida se hace en segundo plano para no interrumpir tu trabajo.
            </p>
            
            <div style="text-align: left; margin-bottom: 1.5rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">1. Selecciona la carpeta destino:</label>
                <select id="drive-upload-folder-select" class="form-control" style="margin-top: 0.75rem; font-weight: 600; padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 2px 4px rgba(0,0,0,0.02); font-size: 0.95rem;">
                    <!-- Options populated via JS -->
                </select>
            </div>
            
            <div style="text-align: left; margin-bottom: 0.75rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">2. Arrastra los archivos aquí:</label>
            </div>
            
            <div id="drive-dropzone" style="border: 2px dashed #cbd5e1; border-radius: 16px; padding: 3.5rem 1.5rem; background: #f8fafc; cursor: pointer; transition: all 0.3s ease; position: relative;" ondragover="this.style.borderColor='#0d945a'; this.style.background='rgba(13, 148, 90, 0.04)'; this.querySelector('.ph-tray-arrow-down').style.color='#0d945a'; this.querySelector('.ph-tray-arrow-down').style.transform='translateY(-5px)'; event.preventDefault();" ondragleave="this.style.borderColor='#cbd5e1'; this.style.background='#f8fafc'; this.querySelector('.ph-tray-arrow-down').style.color='#94a3b8'; this.querySelector('.ph-tray-arrow-down').style.transform='translateY(0)';" ondrop="handleDriveDrop(event)">
                <i class="ph ph-tray-arrow-down" style="font-size: 3.5rem; color: #94a3b8; margin-bottom: 1rem; display: block; text-align: center; transition: all 0.3s ease;"></i>
                <div style="font-weight: 700; color: #1e293b; font-size: 1.15rem; text-align: center;">Arrastra y suelta tus archivos</div>
                <div style="font-size: 0.9rem; color: #64748b; margin-top: 0.5rem; text-align: center;">o haz clic para explorar</div>
                <input type="file" id="drive-file-input" multiple style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;" onchange="handleDriveFileSelect(this)">
            </div>
            
        </div>
    </div>
</div>

<!-- Upload Progress Widget (Background) -->
<div id="upload-progress-widget" style="position: fixed; bottom: 20px; right: 20px; width: 360px; background: #ffffff; border: none; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.15); z-index: 9999; display: none; flex-direction: column; overflow: hidden; font-family: 'Roboto', 'Inter', sans-serif; transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
    <div style="background: #ffffff; color: #202124; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f3f4;">
        <div id="upload-widget-title" style="font-size: 1rem; font-weight: 500;">Subiendo elementos...</div>
        <div style="display: flex; gap: 0.5rem; color: #5f6368;">
            <button type="button" onclick="document.getElementById('upload-progress-widget').style.display='none';" style="background: none; border: none; color: inherit; cursor: pointer; padding: 4px; border-radius: 50%; display: flex; align-items: center; justify-content: center;"><i class="ph ph-caret-down" style="font-size: 1.1rem;"></i></button>
            <button type="button" onclick="document.getElementById('upload-progress-widget').style.display='none';" style="background: none; border: none; color: inherit; cursor: pointer; padding: 4px; border-radius: 50%; display: flex; align-items: center; justify-content: center;"><i class="ph ph-x" style="font-size: 1.1rem;"></i></button>
        </div>
    </div>
    <div id="upload-widget-subheader" style="background: #f1f3f4; padding: 0.5rem 1.25rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: #5f6368;">
        <span id="upload-widget-time">Calculando tiempo...</span>
        <button style="background: none; border: none; color: #1a73e8; cursor: pointer; font-weight: 500; font-size: 0.85rem;">Cancelar</button>
    </div>
    <div id="upload-progress-list" style="max-height: 300px; overflow-y: auto; padding: 0.5rem 0;">
        <!-- Dynamic items -->
    </div>
</div>

<script>
let driveFoldersData = null;

function openUploadDriveModal() {
    const jsonStr = `<?php echo addslashes($monthData['drive_folders_json'] ?? ''); ?>`;
    const select = document.getElementById('drive-upload-folder-select');
    select.innerHTML = '';
    
    try { 
        if(jsonStr) driveFoldersData = JSON.parse(jsonStr); 
    } catch(e){}
    
    if (!driveFoldersData || (!driveFoldersData.root_folder && (!driveFoldersData.subfolders || driveFoldersData.subfolders.length === 0))) {
        select.innerHTML = '<option value="">No hay carpetas configuradas</option>';
        select.disabled = true;
    } else {
        select.disabled = false;
        if (driveFoldersData.root_folder && driveFoldersData.root_folder.id) {
            select.innerHTML += `<option value="${driveFoldersData.root_folder.id}">📁 ${driveFoldersData.root_folder.name}</option>`;
        }
        if (driveFoldersData.subfolders && driveFoldersData.subfolders.length > 0) {
            driveFoldersData.subfolders.forEach(f => {
                select.innerHTML += `<option value="${f.id}">📁 ${f.name}</option>`;
            });
        }
    }
    
    document.getElementById('upload-drive-modal').classList.add('active');
}

function handleDriveDrop(event) {
    event.preventDefault();
    const dz = document.getElementById('drive-dropzone');
    dz.style.borderColor = 'var(--border-color)';
    dz.style.background = 'var(--bg-surface)';
    
    if (event.dataTransfer.files && event.dataTransfer.files.length > 0) {
        processDriveFiles(event.dataTransfer.files);
    }
}

function handleDriveFileSelect(input) {
    if (input.files && input.files.length > 0) {
        processDriveFiles(input.files);
        input.value = ''; // Reset
    }
}

function processDriveFiles(files) {
    const folderId = document.getElementById('drive-upload-folder-select').value;
    if (!folderId) {
        showToast('Por favor selecciona una carpeta destino.');
        return;
    }
    
    document.getElementById('upload-drive-modal').classList.remove('active');
    document.getElementById('upload-progress-widget').style.display = 'flex';
    document.getElementById('upload-progress-widget').style.transform = 'translateY(0)';
    
    Array.from(files).forEach(file => {
        startChunkedUpload(file, folderId);
    });
}

async function startChunkedUpload(file, folderId) {
    const listId = 'upl-' + Math.random().toString(36).substr(2, 9);
    document.getElementById('upload-progress-list').innerHTML += `
        <div id="${listId}" style="padding: 0.5rem 1.25rem; display: flex; align-items: center; gap: 1rem; position: relative;">
            <i class="ph-fill ph-file" style="font-size: 1.5rem; color: #8ab4f8;"></i>
            <div style="flex: 1; min-width: 0;">
                <div style="font-size: 0.9rem; color: #3c4043; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${file.name}">${file.name}</div>
            </div>
            <div style="position: relative; width: 24px; height: 24px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                <svg id="${listId}-spinner" viewBox="0 0 36 36" style="width: 24px; height: 24px; transform: rotate(-90deg);">
                    <circle cx="18" cy="18" r="14" fill="none" stroke="#e8eaed" stroke-width="4"></circle>
                    <circle id="${listId}-bar" cx="18" cy="18" r="14" fill="none" stroke="#1a73e8" stroke-width="4" stroke-dasharray="88" stroke-dashoffset="88" style="transition: stroke-dashoffset 0.3s;"></circle>
                </svg>
                <i id="${listId}-check" class="ph-fill ph-check-circle" style="font-size: 24px; color: #188038; position: absolute; display: none;"></i>
            </div>
        </div>
    `;
    
    try {
        // 1. Get Resumable Upload URL
        const initData = new FormData();
        initData.append('file_name', file.name);
        initData.append('mime_type', file.type || 'application/octet-stream');
        initData.append('folder_id', folderId);
        
        const initRes = await fetch('modules/month_board/ajax_init_resumable_upload.php', {
            method: 'POST',
            body: initData
        });
        const initJson = await initRes.json();
        
        if (!initJson.success || !initJson.upload_url) {
            throw new Error(initJson.error || 'No se pudo iniciar la subida');
        }
        
        const uploadUrl = initJson.upload_url;
        
        // 2. Perform Chunked Upload
        const chunkSize = 5 * 1024 * 1024; // 5 MB chunks
        let start = 0;
        let fileId = null;
        
        while (start < file.size) {
            const end = Math.min(start + chunkSize, file.size);
            const chunk = file.slice(start, end);
            
            let response;
            try {
                response = await uploadChunk(uploadUrl, chunk, start, end, file.size, listId);
            } catch (networkErr) {
                throw networkErr;
            }
            
            // 308 is Resume Incomplete, 200/201 is Success
            if (response.status === 308) {
                start = end; // Move to next chunk
                const pct = start / file.size;
                const offset = 88 - (88 * pct);
                document.getElementById(`${listId}-bar`).style.strokeDashoffset = offset;
            } else if (response.status === 200 || response.status === 201) {
                const finalData = JSON.parse(response.responseText);
                fileId = finalData.id;
                document.getElementById(`${listId}-bar`).style.strokeDashoffset = 0;
                document.getElementById(`${listId}-spinner`).style.display = 'none';
                document.getElementById(`${listId}-check`).style.display = 'block';
                break;
            } else {
                throw new Error('Upload failed with status: ' + response.status);
            }
        }
        
        // 3. Finalize and set permissions
        if (fileId) {
            const finData = new FormData();
            finData.append('file_id', fileId);
            await fetch('modules/month_board/ajax_finish_resumable_upload.php', {
                method: 'POST',
                body: finData
            });
            
            // Add a complete indicator/action
            document.getElementById(listId).innerHTML = `
                <div style="padding: 0.25rem 0; display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem; color: #10b981; font-weight: 600;">
                    <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70%;" title="${file.name}"><i class="ph ph-check-circle"></i> ${file.name}</span>
                    <a href="https://drive.google.com/file/d/${fileId}/view" target="_blank" style="color: #3b82f6; text-decoration: none;">Ver en Drive</a>
                </div>
            `;
        }
        
    } catch (e) {
        console.error(e);
        const errText = e.message || 'Error desconocido';
        document.getElementById(`${listId}-spinner`).style.display = 'none';
        const errIcon = document.createElement('i');
        errIcon.className = 'ph-fill ph-warning-circle';
        errIcon.style = 'font-size: 24px; color: #d93025; position: absolute;';
        errIcon.title = errText;
        document.getElementById(`${listId}-check`).parentNode.appendChild(errIcon);
    }
    
    // Check if reload is pending and no other uploads are running
    if (window.reloadPending) {
        let isUploading = false;
        document.querySelectorAll('#upload-progress-list > div > div > span:last-child').forEach(span => {
            if (span.innerText.includes('%') && span.innerText !== '100%') isUploading = true;
        });
        if (!isUploading) {
            window.location.reload();
        }
    }
}

function uploadChunk(uploadUrl, chunk, start, end, totalSize, listId) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', uploadUrl, true);
        xhr.setRequestHeader('Content-Range', `bytes ${start}-${end - 1}/${totalSize}`);
        
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable && listId) {
                const loadedTotal = start + e.loaded;
                const pct = Math.min(1, loadedTotal / totalSize);
                const offset = 88 - (88 * pct);
                const barEl = document.getElementById(`${listId}-bar`);
                if (barEl) barEl.style.strokeDashoffset = offset;
            }
        };
        
        xhr.onload = function() {
            if (xhr.status === 308 || xhr.status === 200 || xhr.status === 201) {
                resolve({
                    status: xhr.status,
                    responseText: xhr.responseText
                });
            } else {
                reject(new Error('Drive API Error: ' + xhr.status + ' ' + xhr.responseText));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network Error to Drive. Probable CORS o conexión.'));
        };
        
        xhr.send(chunk);
    });
}

// Advertir al usuario si intenta salir mientras hay subidas activas
window.addEventListener('beforeunload', function (e) {
    if (document.getElementById('upload-progress-widget').style.display === 'flex') {
        let isUploading = false;
        document.querySelectorAll('#upload-progress-list > div > div > span:last-child').forEach(span => {
            if (span.innerText.includes('%') && span.innerText !== '100%') isUploading = true;
        });
        if (isUploading) {
            e.preventDefault();
            e.returnValue = 'Tienes archivos subiéndose a Drive. Si sales de esta página, la subida se cancelará.';
        }
    }
});
<?php if (isset($_GET['play']) && $_GET['play'] == '1'): ?>
window.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        startPresentation();
    }, 500);
});
<?php endif; ?>

<?php if (isset($_GET['open_post'])): ?>
window.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        const postId = <?php echo (int)$_GET['open_post']; ?>;
        const postObj = studioPosts.find(p => p.id == postId);
        if(postObj) openPostModal(postObj);
    }, 500);
});
<?php endif; ?>

</script>

<!-- ===== PAINT EDITOR MODAL (Fabric.js) ===== -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<style>
.paint-overlay {
    position: fixed; inset: 0; z-index: 2000;
    background: rgba(10,14,20,0.95);
    backdrop-filter: blur(8px);
    display: none; flex-direction: column;
    font-family: 'Inter', sans-serif;
}
.paint-overlay.active { display: flex; }
.paint-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.6rem 1.25rem; background: #050505;
    border-bottom: 1px solid rgba(255,255,255,0.07); flex-shrink: 0;
}
.paint-header-title { color: #e2e8f0; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
.paint-header-title i { color: #8b5cf6; font-size: 1.2rem; }
.paint-toolbar {
    display: flex; align-items: center; gap: 0.3rem;
    padding: 0.5rem 1rem; background: #161b26;
    border-bottom: 1px solid rgba(255,255,255,0.06); flex-wrap: wrap; flex-shrink: 0;
}
.paint-tool-btn {
    width: 36px; height: 36px; border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.07); background: rgba(255,255,255,0.03);
    color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 1.05rem; transition: all 0.15s;
}
.paint-tool-btn:hover { background: rgba(255,255,255,0.1); color: #e2e8f0; }
.paint-tool-btn.active { background: #4f46e5; color: white; border-color: #6366f1; }
.paint-sep { width: 1px; height: 26px; background: rgba(255,255,255,0.07); margin: 0 0.2rem; }
.paint-color-input {
    width: 36px; height: 36px; border-radius: 8px;
    border: 2px solid rgba(255,255,255,0.12); cursor: pointer; padding: 0; background: none; overflow: hidden;
}
.paint-color-input::-webkit-color-swatch-wrapper { padding: 2px; }
.paint-color-input::-webkit-color-swatch { border: none; border-radius: 5px; }
.paint-presets { display: flex; gap: 3px; align-items: center; }
.paint-preset { width: 20px; height: 20px; border-radius: 5px; cursor: pointer; border: 2px solid transparent; transition: all 0.15s; }
.paint-preset:hover { border-color: rgba(255,255,255,0.4); transform: scale(1.15); }
.paint-range-group { display: flex; align-items: center; gap: 0.35rem; color: #64748b; font-size: 0.65rem; font-weight: 700; }
.paint-range-group input[type="range"] { width: 70px; accent-color: #6366f1; cursor: pointer; }
.paint-range-group span { min-width: 28px; text-align: center; color: #94a3b8; }
.paint-canvas-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 0.75rem; overflow: hidden; }
.paint-canvas-wrap .canvas-container { border-radius: 12px !important; box-shadow: 0 8px 32px rgba(0,0,0,0.5) !important; overflow: hidden !important; }
.paint-canvas-wrap .canvas-container canvas { width: 100% !important; height: 100% !important; }
.paint-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.6rem 1.25rem; background: #12161f;
    border-top: 1px solid rgba(255,255,255,0.07); flex-shrink: 0;
}
.paint-footer-left { display: flex; align-items: center; gap: 0.5rem; color: #475569; font-size: 0.72rem; font-weight: 600; }
.paint-btn {
    padding: 0.45rem 1.1rem; border-radius: 8px; font-weight: 700; font-size: 0.8rem;
    cursor: pointer; border: none; display: flex; align-items: center; gap: 0.35rem; transition: all 0.2s;
}
.paint-btn-cancel { background: rgba(255,255,255,0.07); color: #94a3b8; }
.paint-btn-cancel:hover { background: rgba(255,255,255,0.14); color: #e2e8f0; }
.paint-btn-save { background: #10b981; color: white; }
.paint-btn-save:hover { background: #059669; }
.paint-context-bar {
    display: none; align-items: center; gap: 0.3rem;
    padding: 0.25rem 0.5rem; background: rgba(99,102,241,0.12);
    border: 1px solid rgba(99,102,241,0.25); border-radius: 8px; margin-left: 0.3rem;
}
.paint-context-bar.visible { display: flex; }
.paint-context-label { font-size: 0.6rem; color: #a5b4fc; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-right: 0.2rem; }
.paint-toolbar select option, .paint-context-bar select option { background: #1e1e2f; color: #e2e8f0; }

.paint-page-btn { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0; border-radius: 6px; padding: 4px 10px; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.2s; white-space: nowrap; }
.paint-page-btn:hover { background: rgba(255,255,255,0.15); }
.paint-page-btn.active { background: #3b82f6; border-color: #3b82f6; color: white; font-weight: bold; }
.paint-page-add { background: transparent; border: 1px dashed rgba(255,255,255,0.3); color: #a5b4fc; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.2s; }
.paint-page-add:hover { border-color: #3b82f6; color: #3b82f6; }
.paint-page-del { color: #ef4444; background: transparent; border: none; padding: 0 4px; cursor: pointer; opacity: 0.7; transition: all 0.2s; }
.paint-page-del:hover { opacity: 1; transform: scale(1.1); }
.paint-pages-manager { flex:1; display:flex; gap:0.5rem; align-items:center; overflow-x:auto; padding-right:1rem; }
.paint-pages-manager::-webkit-scrollbar { height: 4px; }
.paint-pages-manager::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

/* Layers Panel Styles */
.paint-layers-panel { width: 240px; background: rgba(5, 5, 5, 0.95); border-left: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; flex-shrink: 0; z-index: 50; }
.paint-layers-header { padding: 12px; font-size: 0.85rem; font-weight: 600; color: #f8fafc; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 6px; }
.paint-layers-list { flex: 1; overflow-y: auto; padding: 8px; display: flex; flex-direction: column; gap: 4px; }
.paint-layers-list::-webkit-scrollbar { width: 4px; }
.paint-layers-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
.paint-layer-item { display: flex; align-items: center; gap: 8px; padding: 6px 8px; background: rgba(255,255,255,0.05); border-radius: 6px; cursor: pointer; transition: background 0.2s; color: #cbd5e1; font-size: 0.75rem; user-select: none; border: 1px solid transparent; }
.paint-layer-item:hover { background: rgba(255,255,255,0.1); }
.paint-layer-item.active { background: rgba(59, 130, 246, 0.2); border-color: #3b82f6; color: #fff; }
.paint-layer-icon { font-size: 1rem; color: #94a3b8; }
.paint-layer-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.paint-layer-actions { display: flex; gap: 4px; opacity: 0; transition: opacity 0.2s; }
.paint-layer-item:hover .paint-layer-actions, .paint-layer-item.active .paint-layer-actions { opacity: 1; }
.paint-layer-action-btn { background: transparent; border: none; color: #94a3b8; cursor: pointer; padding: 2px; border-radius: 4px; display: flex; align-items: center; justify-content: center; }
.paint-layer-action-btn:hover { background: rgba(255,255,255,0.2); color: #fff; }
.paint-layer-color-tag { width: 12px; height: 12px; border-radius: 50%; cursor: pointer; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.2); }

/* Context Menu */
.paint-context-menu { position: fixed; background: #1e293b; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.5); padding: 4px 0; z-index: 9999; display: none; min-width: 160px; font-size: 0.8rem; }
.paint-context-menu.active { display: block; }
.paint-cm-item { padding: 8px 12px; color: #e2e8f0; display: flex; align-items: center; gap: 8px; cursor: pointer; transition: background 0.2s; }
.paint-cm-item:hover { background: rgba(59, 130, 246, 0.2); color: #fff; }
.paint-cm-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 4px 0; }

/* Modal Cropper Overrides for Paint */
.paint-crop-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 10000; display: none; flex-direction: column; align-items: center; justify-content: center; padding: 20px; }
.paint-crop-modal.active { display: flex; }
.paint-crop-container { width: 100%; max-width: 800px; height: 60vh; background: #000; border-radius: 8px; overflow: hidden; position: relative; }
.paint-crop-actions { margin-top: 16px; display: flex; gap: 12px; }

/* Left Sidebar Styles */
.paint-left-sidebar { width: 280px; background: rgba(5, 5, 5, 0.95); border-right: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; flex-shrink: 0; z-index: 50; }
.paint-sidebar-tabs { display: flex; border-bottom: 1px solid rgba(255,255,255,0.1); }
.paint-tab-btn { flex: 1; padding: 12px 0; background: transparent; border: none; color: #94a3b8; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; position: relative; }
.paint-tab-btn:hover { color: #e2e8f0; background: rgba(255,255,255,0.05); }
.paint-tab-btn.active { color: #3b82f6; }
.paint-tab-btn.active::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background: #3b82f6; }
.paint-tab-content { flex: 1; overflow-y: auto; padding: 16px; display: none; flex-direction: column; gap: 12px; }
.paint-tab-content.active { display: flex; }
.paint-tab-content::-webkit-scrollbar { width: 4px; }
.paint-tab-content::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

/* Widgets / Elements Cards */
.paint-widget-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 12px; cursor: grab; transition: all 0.2s; color: #e2e8f0; }
.paint-widget-card:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); transform: translateY(-2px); }
.paint-widget-card:active { cursor: grabbing; }
.paint-widget-icon { width: 36px; height: 36px; border-radius: 6px; background: rgba(59, 130, 246, 0.2); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.paint-widget-info { display: flex; flex-direction: column; gap: 2px; }
.paint-widget-title { font-size: 0.85rem; font-weight: 600; }
.paint-widget-desc { font-size: 0.7rem; color: #94a3b8; }

/* Properties Panel Elements */
.paint-prop-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
.paint-prop-label { font-size: 0.75rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.paint-prop-row { display: flex; gap: 8px; align-items: center; }
.paint-prop-input, .paint-prop-select { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0; border-radius: 6px; padding: 6px 10px; font-size: 0.8rem; width: 100%; transition: border-color 0.2s; outline: none; }
.paint-prop-input:focus, .paint-prop-select:focus { border-color: #3b82f6; }
.paint-prop-select option { background:#1e293b; color:#f8fafc; }
.paint-prop-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0; border-radius: 6px; padding: 6px; font-size: 0.8rem; cursor: pointer; transition: all 0.2s; }
.paint-prop-btn:hover { background: rgba(255,255,255,0.1); }
.paint-prop-btn.active { background: rgba(59,130,246,0.2); border-color: #3b82f6; color: #3b82f6; }

</style>

<div class="paint-overlay" id="paint-editor-modal">
    <div class="paint-header">
        <div class="paint-header-title"><i class="ph ph-paint-brush"></i> Editor de Referencia Visual</div>
        
        <div style="flex:1; display:flex; justify-content:center; align-items:center; gap:1rem;">
            <!-- Barra de Formas -->
            <div style="display:flex; gap:0.4rem; background:#161b26; padding:0.3rem 0.6rem; border-radius:8px; border:1px solid rgba(255,255,255,0.08);">
                <button class="paint-tool-btn active" data-tool="select" onclick="paintSetTool('select')" title="Seleccionar" style="width:30px; height:30px; font-size:1rem;"><i class="ph ph-cursor"></i></button>
                <div style="width:1px; background:rgba(255,255,255,0.1); margin: 0 4px;"></div>
                <button class="paint-tool-btn" data-tool="arrow" onclick="paintSetTool('arrow')" title="Flecha" style="width:30px; height:30px; font-size:1rem;"><i class="ph ph-arrow-up-right"></i></button>
                <button class="paint-tool-btn" data-tool="circle" onclick="paintSetTool('circle')" title="Círculo" style="width:30px; height:30px; font-size:1rem;"><i class="ph ph-circle"></i></button>
                <button class="paint-tool-btn" data-tool="rect" onclick="paintSetTool('rect')" title="Cuadrado" style="width:30px; height:30px; font-size:1rem;"><i class="ph ph-square"></i></button>
                <button class="paint-tool-btn" data-tool="triangle" onclick="paintSetTool('triangle')" title="Triángulo" style="width:30px; height:30px; font-size:1rem;"><i class="ph ph-triangle"></i></button>
                <button class="paint-tool-btn" data-tool="star" onclick="paintSetTool('star')" title="Estrella" style="width:30px; height:30px; font-size:1rem;"><i class="ph ph-star"></i></button>
                <button class="paint-tool-btn" data-tool="hexagon" onclick="paintSetTool('hexagon')" title="Hexágono" style="width:30px; height:30px; font-size:1rem;"><i class="ph ph-hexagon"></i></button>
                <button class="paint-tool-btn" data-tool="chat" onclick="paintSetTool('chat')" title="Burbuja de Chat" style="width:30px; height:30px; font-size:1rem;"><i class="ph ph-chat-circle"></i></button>
            </div>

            <select id="paint-aspect-ratio" style="background:#1e293b; color:#fff; border:1px solid #334155; border-radius:6px; padding:5px 12px; font-size:0.85rem; font-weight:600; cursor:pointer; outline:none; transition: all 0.2s;">
                <option value="auto">Adaptar a Imagen (Auto)</option>
                <option value="1:1" selected>1:1 (Cuadrado)</option>
                <option value="4:1">4:1 (Portada FB)</option>
                <option value="4:5">4:5 (Vertical)</option>
                <option value="3:4">3:4 (Retrato)</option>
                <option value="9:16">9:16 (Historia)</option>
                <option value="4:3">4:3 (Paisaje)</option>
                <option value="16:9">16:9 (Video)</option>
            </select>
        </div>

        <button class="paint-tool-btn" onclick="closePaintEditor()" style="width:34px;height:34px;"><i class="ph ph-x" style="font-size:1.1rem;"></i></button>
    </div>
    <!-- Left Sidebar and Canvas Container -->
    <div style="display: flex; flex: 1; overflow: hidden; position: relative; background: #000000;">
        
        <!-- Left Sidebar -->
        <div class="paint-left-sidebar" id="paint-left-sidebar">
            <div class="paint-sidebar-tabs">
                <button class="paint-tab-btn active" onclick="paintSwitchTab('elementos')" id="paint-tab-btn-elementos">Elementos</button>
                <button class="paint-tab-btn" onclick="paintSwitchTab('propiedades')" id="paint-tab-btn-propiedades">Propiedades</button>
            </div>
            
            <!-- Tab: Elementos -->
            <div class="paint-tab-content active" id="paint-tab-elementos">
                <div class="paint-widget-card" draggable="true" ondragstart="paintDragStart(event, 'text')">
                    <div class="paint-widget-icon"><i class="ph ph-text-t"></i></div>
                    <div class="paint-widget-info">
                        <span class="paint-widget-title">Texto</span>
                        <span class="paint-widget-desc">Título o subtítulo corto</span>
                    </div>
                </div>
                <div class="paint-widget-card" draggable="true" ondragstart="paintDragStart(event, 'paragraph')">
                    <div class="paint-widget-icon"><i class="ph ph-text-align-left"></i></div>
                    <div class="paint-widget-info">
                        <span class="paint-widget-title">Párrafo</span>
                        <span class="paint-widget-desc">Bloque de texto multilinea</span>
                    </div>
                </div>

                <div class="paint-widget-card" draggable="true" ondragstart="paintDragStart(event, 'note')">
                    <div class="paint-widget-icon" style="color:#eab308; background:rgba(234,179,8,0.2);"><i class="ph ph-note"></i></div>
                    <div class="paint-widget-info">
                        <span class="paint-widget-title">Nota (Post-it)</span>
                        <span class="paint-widget-desc">Anotación adhesiva</span>
                    </div>
                </div>
                <div class="paint-widget-card" onclick="document.getElementById('paint-load-file').click()" style="cursor:pointer;">
                    <div class="paint-widget-icon" style="color:#10b981; background:rgba(16,185,129,0.2);"><i class="ph ph-image"></i></div>
                    <div class="paint-widget-info">
                        <span class="paint-widget-title">Imagen</span>
                        <span class="paint-widget-desc">Subir desde dispositivo</span>
                    </div>
                </div>

                <div style="padding: 12px 16px 4px; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Stickers / Recursos</div>
                
                <div class="paint-widget-card" draggable="true" ondragstart="paintDragStart(event, 'sticker-logo-circle')">
                    <div class="paint-widget-icon" style="color:#8b5cf6; background:rgba(139,92,246,0.2);"><i class="ph ph-sticker"></i></div>
                    <div class="paint-widget-info">
                        <span class="paint-widget-title">Sticker Genérico</span>
                        <span class="paint-widget-desc">Añade y personaliza</span>
                    </div>
                </div>
                <input type="file" id="paint-load-file" style="display:none" accept="image/*" onchange="paintOnFileLoad(this)">
                <input type="file" id="paint-replace-file" style="display:none" accept="image/*" onchange="paintReplaceSelectedImage(this)">
            </div>
            
            <!-- Tab: Propiedades -->
            <div class="paint-tab-content" id="paint-tab-propiedades">
                <div style="text-align:center; color:#94a3b8; font-size:0.8rem; padding: 20px 0;" id="paint-no-selection-msg">
                    <i class="ph ph-cursor" style="font-size:2rem; margin-bottom:8px; opacity:0.5;"></i><br>
                    Selecciona un elemento en el lienzo para ver sus propiedades.
                </div>
                <div id="paint-props-container" style="display:none; flex-direction:column; gap:12px;">
                    <!-- Propiedades inyectadas por JS -->
                </div>
            </div>
        </div>
        <div class="paint-canvas-wrap" id="paint-canvas-wrap" style="flex: 1;">
            <canvas id="paint-canvas"></canvas>
            
            <!-- Loading Overlay -->
            <div id="paint-loading-overlay" style="display:none; position:absolute; inset:0; background:rgba(15,23,42,0.7); z-index:100; align-items:center; justify-content:center; flex-direction:column; color:#fff; backdrop-filter:blur(4px); border-radius:8px;">
                <div style="width: 48px; height: 48px; border: 4px solid rgba(255,255,255,0.2); border-top-color: #3b82f6; border-radius: 50%; animation: paintSpin 1s linear infinite;"></div>
                <div style="margin-top:1rem; font-weight:600; font-size:0.95rem; letter-spacing:0.5px;" id="paint-loading-text">Cargando...</div>
                <style>@keyframes paintSpin { to { transform: rotate(360deg); } }</style>
            </div>
        </div>
        
        <!-- Sidebar de Capas -->
        <div class="paint-layers-panel" id="paint-layers-panel">
            <div class="paint-layers-header">
                <i class="ph ph-stack"></i> Capas
            </div>
            <div class="paint-layers-list" id="paint-layers-list">
                <!-- Layers injected here via JS -->
            </div>
        </div>
    </div>
    <div class="paint-footer">
        <div class="paint-pages-manager" id="paint-pages-manager">
            <!-- Pages will be injected here -->
        </div>
        <div style="display:flex;gap:0.5rem; flex-shrink:0;">
            <button class="paint-btn paint-btn-cancel" onclick="closePaintEditor()"><i class="ph ph-x"></i> Cancelar</button>
            <button class="paint-btn paint-btn-save" id="paint-save-btn" onclick="paintSave()"><i class="ph ph-floppy-disk"></i> Guardar como Referencia</button>
        </div>
    </div>
</div>

<!-- Menú Contextual Right Click -->
<div class="paint-context-menu" id="paint-ctx-menu">
    <div class="paint-cm-item" onclick="paintCtxCut()"><i class="ph ph-scissors"></i> Cortar</div>
    <div class="paint-cm-item" onclick="paintCtxCopy()"><i class="ph ph-copy"></i> Copiar</div>
    <div class="paint-cm-item" onclick="paintCtxPaste()"><i class="ph ph-clipboard-text"></i> Pegar</div>
    <div class="paint-cm-divider"></div>
    <div class="paint-cm-item" onclick="paintCtxBringForward()"><i class="ph ph-intersect"></i> Traer adelante</div>
    <div class="paint-cm-item" onclick="paintCtxSendBackward()"><i class="ph ph-exclude"></i> Enviar atrás</div>
    <div class="paint-cm-divider"></div>
    <div class="paint-cm-item" onclick="paintCtxToggleLock()"><i class="ph ph-lock"></i> Bloquear/Desbloquear</div>
    <div class="paint-cm-item" onclick="paintCtxUndo()"><i class="ph ph-arrow-counter-clockwise"></i> Deshacer</div>
</div>

<!-- Modal para Cropper -->
<div class="paint-crop-modal" id="paint-crop-modal">
    <div class="paint-crop-container">
        <img id="paint-crop-img-target" src="" alt="Crop target" style="display:block; max-width:100%;">
    </div>
    <div class="paint-crop-actions">
        <button class="btn btn-outline" style="color:#fff; border-color:rgba(255,255,255,0.3);" onclick="paintCloseCropModal()">Cancelar</button>
        <button class="btn btn-primary" onclick="paintApplyCrop()">Aplicar Recorte</button>
    </div>
</div>

<script>
let fabricCanvas = null, paintCurrentTool = 'select';
let paintUndoStack = [], paintRedoStack = [];
let paintIsAdding = false, paintShapeStart = null, paintTempShape = null;

let paintPages = [];
let paintCurrentPage = 0;

function getPaintCanvasJSON() {
    if (!fabricCanvas) return '{}';
    const data = fabricCanvas.toJSON(['customType', 'customBadge', 'customShapeType', 'id', 'name', 'noteShadow', 'splitByGrapheme', 'padding']);
    data.width = fabricCanvas.width;
    data.height = fabricCanvas.height;
    return JSON.stringify(data);
}

function paintRenderPages() {
    const mgr = document.getElementById('paint-pages-manager');
    if (!mgr) return;
    let html = '';
    for (let i = 0; i < paintPages.length; i++) {
        const activeClass = i === paintCurrentPage ? 'active' : '';
        html += `<div class="paint-page-btn ${activeClass}" onclick="paintSwitchPage(${i})">
                    Pág ${i + 1} 
                    ${paintPages.length > 1 ? `<button class="paint-page-del" onclick="event.stopPropagation(); paintDeletePage(${i});"><i class="ph ph-trash"></i></button>` : ''}
                 </div>`;
    }
    html += `<button class="paint-page-add" onclick="paintAddPage()"><i class="ph ph-plus"></i> Añadir</button>`;
    mgr.innerHTML = html;
}

function paintAddPage() {
    if (fabricCanvas) {
        const vpt = fabricCanvas.viewportTransform.slice();
        fabricCanvas.setViewportTransform([1, 0, 0, 1, 0, 0]);
        paintPages[paintCurrentPage] = getPaintCanvasJSON();
        fabricCanvas.setViewportTransform(vpt);
    }
    const newPageIdx = paintPages.length;
    let w = 1080, h = 1080;
    if (fabricCanvas) { w = fabricCanvas.width; h = fabricCanvas.height; }
    paintPages.push(JSON.stringify({version: "5.3.1", objects: [], background: "#ffffff", width: w, height: h}));
    paintSwitchPage(newPageIdx);
}

function paintDeletePage(index) {
    if (paintPages.length <= 1) return;
    if (confirm('¿Eliminar esta página?')) {
        paintPages.splice(index, 1);
        if (paintCurrentPage >= paintPages.length) paintCurrentPage = paintPages.length - 1;
        paintSwitchPage(paintCurrentPage, true);
    }
}

function paintSwitchPage(index, forceLoad = false) {
    if (!forceLoad && index === paintCurrentPage) return;
    if (fabricCanvas && !forceLoad) {
        const vpt = fabricCanvas.viewportTransform.slice();
        fabricCanvas.setViewportTransform([1, 0, 0, 1, 0, 0]);
        paintPages[paintCurrentPage] = getPaintCanvasJSON();
        fabricCanvas.setViewportTransform(vpt);
    }
    paintCurrentPage = index;
    const dataStr = paintPages[paintCurrentPage];
    
    let cw = 1080, ch = 1080;
    try { const p = JSON.parse(dataStr); cw = p.width || 1080; ch = p.height || 1080; } catch(e){}
    
    initFabricCanvas(cw, ch);
    
    const ratioSelect = document.getElementById('paint-aspect-ratio');
    if (ratioSelect) {
        if (cw === 1080 && ch === 1080) ratioSelect.value = '1:1';
        else if (cw === 1080 && ch === 1350) ratioSelect.value = '4:5';
        else if (cw === 1080 && ch === 1440) ratioSelect.value = '3:4';
        else if (cw === 1920 && ch === 1080) ratioSelect.value = '16:9';
        else if (cw === 1440 && ch === 1080) ratioSelect.value = '4:3';
        else if (cw === 1080 && ch === 1920) ratioSelect.value = '9:16';
        else ratioSelect.value = 'auto';
    }

    fabricCanvas.loadFromJSON(dataStr, () => {
        const wrap = document.getElementById('paint-canvas-wrap');
        const maxW = wrap.clientWidth - 30, maxH = wrap.clientHeight - 30;
        const scale = Math.min(maxW / cw, maxH / ch, 1);
        const ct = fabricCanvas.wrapperEl;
        ct.style.width = Math.floor(cw * scale) + 'px';
        ct.style.height = Math.floor(ch * scale) + 'px';
        
        fabricCanvas.renderAll();
        paintUndoStack = []; paintRedoStack = [];
        paintSaveHist();
        paintRenderPages();
    });
}

function initFabricCanvas(cW = 1080, cH = 1080) {
    if (fabricCanvas) fabricCanvas.dispose();
    const wrap = document.getElementById('paint-canvas-wrap');
    const maxW = wrap.clientWidth - 30, maxH = wrap.clientHeight - 30;
    const scale = Math.min(maxW / cW, maxH / cH, 1);

    fabric.Textbox.prototype.splitByGrapheme = false;
    fabric.Textbox.prototype._renderBackground = (function (original) {
        return function(ctx) {
            if (this.customType === 'note') {
                const w = this.width + this.padding*2;
                const h = this.height + this.padding*2;
                const x = -this.width/2 - this.padding;
                const y = -this.height/2 - this.padding;

                ctx.save();
                
                // Shadow
                if (this.noteShadow !== false && !this.isEditing) {
                    ctx.shadowColor = 'rgba(0,0,0,0.2)';
                    ctx.shadowBlur = 12;
                    ctx.shadowOffsetX = 2;
                    ctx.shadowOffsetY = 3;
                }
                
                // Background
                ctx.fillStyle = this.backgroundColor || '#ffedd5';
                ctx.beginPath();
                if (ctx.roundRect) ctx.roundRect(x, y, w, h, 6);
                else ctx.rect(x, y, w, h);
                ctx.fill();

                // Header Top Bar
                ctx.shadowColor = 'transparent';
                ctx.fillStyle = 'rgba(0,0,0,0.08)';
                ctx.beginPath();
                if (ctx.roundRect) ctx.roundRect(x, y, w, 24, [6, 6, 0, 0]);
                else ctx.rect(x, y, w, 24);
                ctx.fill();

                // Decorative circles (like the HTML modal)
                const colors = ['#fef08a', '#bbf7d0', '#bfdbfe', '#fbcfe8', '#fed7aa'];
                let cx = x + 14;
                const cy = y + 12;
                colors.forEach(c => {
                    ctx.fillStyle = c;
                    ctx.strokeStyle = 'rgba(0,0,0,0.15)';
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.arc(cx, cy, 5, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.stroke();
                    cx += 14;
                });

                // Decorative X
                const rx = x + w - 14;
                ctx.fillStyle = 'transparent';
                ctx.strokeStyle = '#991b1b';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.arc(rx, cy, 5, 0, Math.PI * 2);
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(rx - 2, cy - 2);
                ctx.lineTo(rx + 2, cy + 2);
                ctx.moveTo(rx + 2, cy - 2);
                ctx.lineTo(rx - 2, cy + 2);
                ctx.stroke();
                
                ctx.restore();
                return;
            }
            original.call(this, ctx);
        };
    })(fabric.Textbox.prototype._renderBackground);

    fabricCanvas = new fabric.Canvas('paint-canvas', {
        width: cW, height: cH, backgroundColor: '#ffffff', selection: true, preserveObjectStacking: true,
    });
    const ct = fabricCanvas.wrapperEl;
    ct.style.width = Math.floor(cW * scale) + 'px';
    ct.style.height = Math.floor(cH * scale) + 'px';

    fabricCanvas.on('object:modified', paintSaveHist);
    fabricCanvas.on('object:added', paintOnObjectAdded);
    fabricCanvas.on('object:removed', paintSaveHist);
    fabricCanvas.on('selection:created', paintOnSel);
    fabricCanvas.on('selection:updated', paintOnSel);
    fabricCanvas.on('selection:cleared', () => {
        paintOnSel({selected: null});
    });
    fabricCanvas.on('mouse:down', paintMD);
    fabricCanvas.on('mouse:move', paintMM);
    fabricCanvas.on('mouse:up', paintMU);
    fabricCanvas.on('object:moving', paintOnObjectMoving);
    fabricCanvas.on('text:editing:exited', () => {
        const btn = document.querySelector('[data-tool="select"]');
        if (btn) btn.click();
    });

    fabricCanvas.on('after:render', function() {
        const ratioSelect = document.getElementById('paint-aspect-ratio');
        if (ratioSelect && ratioSelect.value === '4:1') {
            const ctx = fabricCanvas.contextContainer;
            ctx.save();
            
            const vpt = fabricCanvas.viewportTransform;
            ctx.transform(vpt[0], vpt[1], vpt[2], vpt[3], vpt[4], vpt[5]);
            
            const safeWidth = 1080;
            const x1 = (fabricCanvas.width - safeWidth) / 2;
            const x2 = x1 + safeWidth;
            const zoom = fabricCanvas.getZoom();
            
            ctx.strokeStyle = 'rgba(0, 255, 255, 0.7)';
            ctx.setLineDash([10, 10]);
            ctx.lineWidth = 2 / zoom;
            
            ctx.beginPath();
            ctx.moveTo(x1, 0);
            ctx.lineTo(x1, fabricCanvas.height);
            ctx.stroke();

            ctx.beginPath();
            ctx.moveTo(x2, 0);
            ctx.lineTo(x2, fabricCanvas.height);
            ctx.stroke();

            ctx.fillStyle = 'rgba(0, 255, 255, 0.7)';
            ctx.font = `${14 / zoom}px Inter`;
            ctx.fillText('Zona Segura Móvil', x1 + 10, 20 / zoom);
            
            ctx.restore();
        }
    });

    paintUndoStack = []; paintRedoStack = [];
    paintSaveHist();
}

function closePaintEditor() { 
    document.getElementById('paint-editor-modal').classList.remove('active'); 
    if (typeof fabricCanvas !== 'undefined' && fabricCanvas) {
        fabricCanvas.clear();
        paintUndoStack = [];
        paintRedoStack = [];
    }
}

async function openPaintEditor() {
    if (document.fonts) await document.fonts.load('16px "Inter"');
    document.getElementById('paint-editor-modal').classList.add('active');
    setTimeout(() => {
        const paintDataStr = document.getElementById('post-paint-data').value;
        const refVal = document.getElementById('post-reference-link').value;
        let firstUrl = '';
        try { const arr = JSON.parse(refVal); firstUrl = Array.isArray(arr) ? arr[0] : refVal; } catch(e) { firstUrl = refVal; }

        if (paintDataStr) {
            let dataArr = [];
            try { 
                const pData = JSON.parse(paintDataStr);
                if (Array.isArray(pData)) {
                    dataArr = pData;
                } else {
                    dataArr = [paintDataStr];
                }
            } catch(e) {
                dataArr = [paintDataStr];
            }
            paintPages = dataArr;
            paintCurrentPage = 0;
            paintSwitchPage(0, true);
        } else if (firstUrl) {
            // Load existing image
            const tempImg = new Image();
            tempImg.crossOrigin = 'anonymous';
            tempImg.onload = () => {
                paintPages = [ JSON.stringify({version: "5.3.1", objects: [], background: "#ffffff", width: tempImg.width, height: tempImg.height}) ];
                paintCurrentPage = 0;
                paintSwitchPage(0, true);
                fabric.Image.fromURL(firstUrl, img => {
                    img.set({left:0, top:0});
                    fabricCanvas.add(img); fabricCanvas.sendToBack(img); fabricCanvas.renderAll(); paintSaveHist();
                }, {crossOrigin: 'anonymous'});
            };
            tempImg.onerror = () => { 
                paintPages = [ JSON.stringify({version: "5.3.1", objects: [], background: "#ffffff", width: 1080, height: 1080}) ];
                paintCurrentPage = 0;
                paintSwitchPage(0, true); 
            };
            tempImg.src = firstUrl;
        } else {
            paintPages = [ JSON.stringify({version: "5.3.1", objects: [], background: "#ffffff", width: 1080, height: 1080}) ];
            paintCurrentPage = 0;
            paintSwitchPage(0, true);
        }
    }, 100);
}

function closePaintEditor() { document.getElementById('paint-editor-modal').classList.remove('active'); }


function paintOnSel(e) {
    const o = e.selected ? e.selected[0] : fabricCanvas?.getActiveObject();
    const msg = document.getElementById('paint-no-selection-msg');
    const container = document.getElementById('paint-props-container');
    
    if (!o) {
        if(msg) msg.style.display = 'block';
        if(container) container.style.display = 'none';
        paintSwitchTab('elementos');
        return;
    }
    
    if(msg) msg.style.display = 'none';
    if(container) container.style.display = 'flex';
    paintSwitchTab('propiedades');

    let html = '';

    // IMAGE PROPERTIES
    if (o.type === 'image') {
        html += `
            <div class="paint-prop-group">
                <span class="paint-prop-label">Acciones de Imagen</span>
                <div class="paint-prop-row">
                    <button class="paint-prop-btn" onclick="document.getElementById('paint-replace-file').click()"><i class="ph ph-arrows-clockwise"></i> Reemplazar</button>
                    <button class="paint-prop-btn" onclick="paintOpenCropModal()"><i class="ph ph-crop"></i> Recortar</button>
                    <button class="paint-prop-btn" onclick="paintFlipImage()"><i class="ph ph-arrows-left-right"></i> Espejo</button>
                </div>
            </div>
            <div class="paint-prop-group">
                <span class="paint-prop-label">Borde</span>
                <div class="paint-prop-row">
                    <input type="number" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore="true" class="paint-prop-input" style="width:60px" value="${o.strokeWidth || 0}" onchange="paintUpdateObjProp('strokeWidth', parseInt(this.value))">
                    <input type="color" autocomplete="off" class="paint-prop-input" style="padding:0; height:32px;" value="${o.stroke || '#000000'}" onchange="paintUpdateObjProp('stroke', this.value)">
                </div>
            </div>
            <div class="paint-prop-group">
                <span class="paint-prop-label">Sombra</span>
                <div class="paint-prop-row">
                    <button class="paint-prop-btn ${o.shadow ? 'active' : ''}" onclick="paintToggleShadow()"><i class="ph ph-drop"></i> Activar Sombra</button>
                </div>
            </div>
        `;
    }
    // TEXT & PARAGRAPH & NOTE PROPERTIES
    else if (o.type === 'i-text' || o.type === 'textbox') {
        const isNote = o.customType === 'note';

        html += `
            <div class="paint-prop-group">
                <span class="paint-prop-label">Contenido del Texto</span>
                <div class="paint-prop-row">
                    <textarea class="paint-prop-input" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" style="width:100%; height:80px; resize:vertical; padding:8px; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1); border-radius:4px; color:#e2e8f0; font-family:inherit; font-size:0.85rem;" oninput="paintUpdateObjProp('text', this.value)">${o.text === ' ' ? '' : (o.text || '')}</textarea>
                </div>
            </div>
        `;
        
        if (isNote) {
            const colors = ['#fef08a', '#bbf7d0', '#bfdbfe', '#fbcfe8', '#fed7aa'];
            html += `
                <div class="paint-prop-group">
                    <span class="paint-prop-label" style="display:flex; justify-content:space-between; align-items:center;">
                        Color y Acciones
                        <button onclick="paintDeleteSelected()" style="background:none; border:none; color:#f87171; cursor:pointer; font-size:16px; padding:0; line-height:1;" title="Eliminar Post-it"><i class="ph ph-trash"></i></button>
                    </span>
                    <div class="paint-prop-row" style="gap: 8px;">
                        ${colors.map(c => `<button class="paint-prop-btn" style="width:28px; height:28px; padding:0; border-radius:50%; background-color:${c}; border: 2px solid ${o.backgroundColor === c ? '#3b82f6' : 'rgba(255,255,255,0.2)'};" onclick="paintUpdateObjProp('backgroundColor', '${c}')"></button>`).join('')}
                        <input type="color" autocomplete="off" class="paint-prop-input" style="padding:0; height:28px; width:28px; border-radius:50%; margin-left: auto;" title="Personalizado" value="${o.backgroundColor || '#ffedd5'}" onchange="paintUpdateObjProp('backgroundColor', this.value)">
                    </div>
                </div>
                <div class="paint-prop-group">
                    <span class="paint-prop-label">Sombra del Post-it</span>
                    <div class="paint-prop-row">
                        <button class="paint-prop-btn ${o.noteShadow !== false ? 'active' : ''}" onclick="paintUpdateObjProp('noteShadow', ${o.noteShadow === false ? 'true' : 'false'})"><i class="ph ph-drop"></i> Activar Sombra</button>
                    </div>
                </div>
                <div class="paint-prop-group">
                    <span class="paint-prop-label">Etiqueta (Badge)</span>
                    <div class="paint-prop-row">
                        <select class="paint-prop-select" onchange="paintUpdateNoteBadge(this.value)">
                            <option value="">Ninguno</option>
                            <option value="importante" ${o.customBadge==='importante'?'selected':''}>Importante</option>
                            <option value="nuevo" ${o.customBadge==='nuevo'?'selected':''}>Nuevo</option>
                        </select>
                    </div>
                </div>
            `;
        }

        html += `
            <div class="paint-prop-group">
                <span class="paint-prop-label">Texto</span>
                <div class="paint-prop-row">
                    <input type="color" autocomplete="off" class="paint-prop-input" style="padding:0; height:32px; width:40px;" value="${o.fill || '#000000'}" onchange="paintUpdateObjProp('fill', this.value)">
                    <input type="number" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore="true" class="paint-prop-input" style="width:70px" value="${Math.round(o.fontSize || 32)}" onchange="paintUpdateObjProp('fontSize', parseInt(this.value))">
                    <select class="paint-prop-select" onchange="paintUpdateObjProp('fontFamily', this.value)">
                        <option value="Inter, sans-serif" ${o.fontFamily==='Inter, sans-serif'?'selected':''}>Inter</option>
                        <option value="Arial, sans-serif" ${o.fontFamily==='Arial, sans-serif'?'selected':''}>Arial</option>
                        <option value="'Times New Roman', serif" ${o.fontFamily==="'Times New Roman', serif"?'selected':''}>Times New Roman</option>
                        <option value="'Courier New', monospace" ${o.fontFamily==="'Courier New', monospace"?'selected':''}>Courier</option>
                    </select>
                </div>
                <div class="paint-prop-row">
                    <span style="font-size: 0.75rem; color:#94a3b8; display:flex; align-items:center; margin-right: 4px;" title="Color de Resalte"><i class="ph ph-highlighter"></i></span>
                    <input type="color" autocomplete="off" class="paint-prop-input" style="padding:0; height:32px; width:32px;" value="${o.textBackgroundColor && o.textBackgroundColor !== 'transparent' ? o.textBackgroundColor : '#ffff00'}" onchange="paintUpdateObjProp('textBackgroundColor', this.value)" title="Color de Resalte">
                    <button class="paint-prop-btn ${!o.textBackgroundColor || o.textBackgroundColor === 'transparent' ? 'active' : ''}" onclick="paintUpdateObjProp('textBackgroundColor', 'transparent')" title="Sin Resalte"><i class="ph ph-prohibit"></i></button>
                    <div style="flex:1;"></div>
                    <button class="paint-prop-btn ${o.fontWeight==='bold'?'active':''}" onclick="paintToggleTextProp('fontWeight', 'bold', 'normal')"><i class="ph ph-text-b"></i></button>
                    <button class="paint-prop-btn ${o.fontStyle==='italic'?'active':''}" onclick="paintToggleTextProp('fontStyle', 'italic', 'normal')"><i class="ph ph-text-italic"></i></button>
                    <button class="paint-prop-btn ${o.underline?'active':''}" onclick="paintToggleTextProp('underline', true, false)"><i class="ph ph-text-underline"></i></button>
                </div>
            </div>
            <div class="paint-prop-group">
                <span class="paint-prop-label">Párrafo y Formato Adicional</span>
                <div class="paint-prop-row">
                    <button class="paint-prop-btn ${o.textAlign==='left'?'active':''}" onclick="paintUpdateObjProp('textAlign', 'left')"><i class="ph ph-text-align-left"></i></button>
                    <button class="paint-prop-btn ${o.textAlign==='center'?'active':''}" onclick="paintUpdateObjProp('textAlign', 'center')"><i class="ph ph-text-align-center"></i></button>
                    <button class="paint-prop-btn ${o.textAlign==='right'?'active':''}" onclick="paintUpdateObjProp('textAlign', 'right')"><i class="ph ph-text-align-right"></i></button>
                    <button class="paint-prop-btn ${o.textAlign==='justify'?'active':''}" onclick="paintUpdateObjProp('textAlign', 'justify')"><i class="ph ph-text-align-justify"></i></button>
                </div>
                <div class="paint-prop-row" style="margin-top:4px;">
                    <i class="ph ph-list-dashes" style="color:#94a3b8;"></i>
                    <input type="range" class="paint-prop-input" min="0.5" max="3" step="0.1" value="${o.lineHeight||1.16}" oninput="paintUpdateObjProp('lineHeight', parseFloat(this.value))">
                </div>
                <div class="paint-prop-row" style="margin-top:8px;">
                    <button class="paint-prop-btn" onclick="paintAddBullets()" title="Convertir a Viñetas"><i class="ph ph-list-bullets"></i> Viñetas</button>
                    <button class="paint-prop-btn" onclick="paintToggleUppercase()" title="Alternar Mayúsculas"><i class="ph ph-text-a-underline"></i> aA</button>
                </div>
            </div>
        `;
    }
    // STICKER PROPERTIES
    else if (o.customType === 'sticker') {
        html += `
            <div class="paint-prop-group">
                <span class="paint-prop-label">Tipo de Sticker</span>
                <div class="paint-prop-row">
                    <select class="paint-prop-select" onchange="paintUpdateStickerVariant(this.value)">
                        <option value="sticker-logo-circle" ${o.stickerVariant === 'sticker-logo-circle' ? 'selected' : ''}>Logo (Círculo)</option>
                        <option value="sticker-logo-pill" ${o.stickerVariant === 'sticker-logo-pill' ? 'selected' : ''}>Logo (Cápsula)</option>
                        <option value="sticker-whatsapp" ${o.stickerVariant === 'sticker-whatsapp' ? 'selected' : ''}>WhatsApp</option>
                        <option value="sticker-social" ${o.stickerVariant === 'sticker-social' ? 'selected' : ''}>Redes Sociales</option>
                        <option value="sticker-web" ${o.stickerVariant === 'sticker-web' ? 'selected' : ''}>Sitio Web</option>
                        <option value="sticker-ui-button" ${o.stickerVariant === 'sticker-ui-button' ? 'selected' : ''}>UI: Botón Web</option>
                        <option value="sticker-ui-progress" ${o.stickerVariant === 'sticker-ui-progress' ? 'selected' : ''}>UI: Barra Progreso</option>
                        <option value="sticker-ui-input" ${o.stickerVariant === 'sticker-ui-input' ? 'selected' : ''}>UI: Campo Input</option>
                    </select>
                </div>
            </div>
            <div class="paint-prop-group">
                <span class="paint-prop-label">Efectos</span>
                <div class="paint-prop-row">
                    <button class="paint-prop-btn ${o.shadow ? 'active' : ''}" onclick="paintToggleShadow()"><i class="ph ph-drop"></i> Activar Sombra</button>
                </div>
            </div>
        `;
    }
    // SHAPE PROPERTIES
    else {
        const parsedFill = paintParseColor(o.fill);
        const parsedStroke = paintParseColor(o.stroke);
        const isRect = o.type === 'rect' || o.customShapeType === 'rect';

        html += `
            <div class="paint-prop-group">
                <span class="paint-prop-label">Tipo de Forma</span>
                <div class="paint-prop-row">
                    <select class="paint-prop-select" onchange="paintChangeShapeType(this.value)">
                        <option value="rect" ${o.customShapeType==='rect'?'selected':''}>Cuadrado / Rectángulo</option>
                        <option value="circle" ${o.customShapeType==='circle'||o.type==='ellipse'?'selected':''}>Círculo / Elipse</option>
                        <option value="line" ${o.customShapeType==='line'||(o.type==='line'&&!o._isArrow)?'selected':''}>Línea</option>
                        <option value="arrow" ${o.customShapeType==='arrow'||o._isArrow?'selected':''}>Flecha</option>
                    </select>
                </div>
            </div>
            <div class="paint-prop-group">
                <span class="paint-prop-label">Relleno</span>
                <div class="paint-prop-row" style="margin-bottom: 4px;">
                    <input type="color" autocomplete="off" id="paint-prop-fill-color" class="paint-prop-input" style="padding:0; height:32px; width:40px;" value="${parsedFill.hex}" onchange="paintUpdateShapeColor('fill')">
                    <div style="display:flex; align-items:center; flex:1; margin-left: 8px; font-size:12px; color:#94a3b8;" title="Opacidad de Relleno">
                        <i class="ph ph-drop-half" style="margin-right:4px;"></i>
                        <input type="range" id="paint-prop-fill-opacity" class="paint-prop-input" style="flex:1" min="0" max="1" step="0.05" value="${parsedFill.opacity}" oninput="paintUpdateShapeColor('fill')">
                    </div>
                </div>
                <span class="paint-prop-label">Trazo / Borde</span>
                <div class="paint-prop-row">
                    <input type="color" autocomplete="off" id="paint-prop-stroke-color" class="paint-prop-input" style="padding:0; height:32px; width:40px;" value="${parsedStroke.hex}" onchange="paintUpdateShapeColor('stroke')">
                    <div style="display:flex; align-items:center; flex:1; margin-left: 8px; font-size:12px; color:#94a3b8;" title="Opacidad de Trazo">
                        <i class="ph ph-drop-half" style="margin-right:4px;"></i>
                        <input type="range" id="paint-prop-stroke-opacity" class="paint-prop-input" style="flex:1" min="0" max="1" step="0.05" value="${parsedStroke.opacity}" oninput="paintUpdateShapeColor('stroke')">
                    </div>
                    <input type="number" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore="true" class="paint-prop-input" style="width:50px; margin-left:8px;" value="${o.strokeWidth || 0}" onchange="paintUpdateObjProp('strokeWidth', parseInt(this.value))">
                </div>
            </div>
            <div class="paint-prop-group">
                <span class="paint-prop-label">Efectos</span>
                <div class="paint-prop-row">
                    <button class="paint-prop-btn ${o.shadow ? 'active' : ''}" onclick="paintToggleShadow()"><i class="ph ph-drop"></i> Activar Sombra</button>
                    ${isRect ? `<div style="display:flex; align-items:center; flex:1; margin-left: 8px; font-size:12px; color:#94a3b8;" title="Borde Redondeado">
                        <i class="ph ph-corners-out" style="margin-right:4px;"></i>
                        <input type="range" class="paint-prop-input" style="flex:1" min="0" max="100" step="1" value="${o.rx || 0}" oninput="paintUpdateObjProp('rx', parseInt(this.value)); paintUpdateObjProp('ry', parseInt(this.value));">
                    </div>` : ''}
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
    paintRenderLayers();
}

function paintUpdateObjProp(prop, val) {
    const o = fabricCanvas?.getActiveObject();
    if(o) {
        if (prop === 'noteShadow') {
            o.noteShadow = val;
        } else {
            o.set(prop, val);
        }
        fabricCanvas.renderAll();
        paintSaveHist();
    }
}
function paintToggleTextProp(prop, onVal, offVal) {
    const o = fabricCanvas?.getActiveObject();
    if(o) {
        o.set(prop, o[prop] === onVal ? offVal : onVal);
        fabricCanvas.renderAll();
        paintSaveHist();
        paintOnSel({selected: [o]});
    }
}

function paintAddBullets() {
    const o = fabricCanvas?.getActiveObject();
    if(o && (o.type === 'i-text' || o.type === 'textbox')) {
        const text = o.text || '';
        const lines = text.split('\n');
        const bulleted = lines.map(line => {
            if (line.trim().startsWith('• ')) return line;
            return '• ' + line;
        }).join('\n');
        o.set('text', bulleted);
        fabricCanvas.renderAll();
        paintSaveHist();
        paintOnSel({selected: [o]});
    }
}

function paintToggleUppercase() {
    const o = fabricCanvas?.getActiveObject();
    if(o && (o.type === 'i-text' || o.type === 'textbox')) {
        const text = o.text || '';
        if (text === text.toUpperCase()) {
            // Convierte a Title Case si ya está en mayúsculas completas
            o.set('text', text.toLowerCase().replace(/(^|\s)\S/g, l => l.toUpperCase()));
        } else {
            o.set('text', text.toUpperCase());
        }
        fabricCanvas.renderAll();
        paintSaveHist();
        paintOnSel({selected: [o]});
    }
}

function paintParseColor(colorStr) {
    if (!colorStr || colorStr === 'transparent') return { hex: '#000000', opacity: 0 };
    if (colorStr.startsWith('#')) {
        if (colorStr.length === 9) { // #RRGGBBAA
            return { hex: colorStr.substring(0, 7), opacity: parseInt(colorStr.substring(7, 9), 16) / 255 };
        }
        return { hex: colorStr, opacity: 1 };
    }
    if (colorStr.startsWith('rgba')) {
        const parts = colorStr.substring(5, colorStr.length - 1).split(',');
        const r = parseInt(parts[0].trim());
        const g = parseInt(parts[1].trim());
        const b = parseInt(parts[2].trim());
        const a = parseFloat(parts[3].trim());
        const hex = "#" + (1 << 24 | r << 16 | g << 8 | b).toString(16).padStart(6, '0');
        return { hex, opacity: a };
    }
    if (colorStr.startsWith('rgb')) {
        const parts = colorStr.substring(4, colorStr.length - 1).split(',');
        const r = parseInt(parts[0].trim());
        const g = parseInt(parts[1].trim());
        const b = parseInt(parts[2].trim());
        const hex = "#" + (1 << 24 | r << 16 | g << 8 | b).toString(16).padStart(6, '0');
        return { hex, opacity: 1 };
    }
    return { hex: '#000000', opacity: 1 };
}

function paintUpdateShapeColor(type) {
    const o = fabricCanvas?.getActiveObject();
    if (!o) return;
    const hex = document.getElementById('paint-prop-' + type + '-color').value;
    const opacity = parseFloat(document.getElementById('paint-prop-' + type + '-opacity').value);
    
    if (opacity === 0) {
        o.set(type, 'transparent');
    } else if (opacity === 1) {
        o.set(type, hex);
    } else {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        o.set(type, `rgba(${r}, ${g}, ${b}, ${opacity})`);
    }
    fabricCanvas.renderAll();
    paintSaveHist();
}
function paintToggleShadow() {
    const o = fabricCanvas?.getActiveObject();
    if(o) {
        if(o.shadow) o.set('shadow', null);
        else o.set('shadow', new fabric.Shadow({color: 'rgba(0,0,0,0.5)', blur: 10, offsetX: 5, offsetY: 5}));
        fabricCanvas.renderAll();
        paintSaveHist();
        paintOnSel({selected: [o]}); // refresh panel
    }
}
function paintChangeShapeType(newType) {
    const o = fabricCanvas?.getActiveObject();
    if(!o) return;
    
    const left = o.left, top = o.top, fill = o.fill, stroke = o.stroke, strokeWidth = o.strokeWidth;
    let newObj = null;

    if (newType === 'rect') {
        newObj = new fabric.Rect({ left, top, width: 100, height: 100, fill, stroke, strokeWidth, originX:'center', originY:'center' });
    } else if (newType === 'circle') {
        newObj = new fabric.Ellipse({ left, top, rx: 50, ry: 50, fill, stroke, strokeWidth, originX:'center', originY:'center' });
    } else if (newType === 'line') {
        newObj = new fabric.Line([left-50, top, left+50, top], { stroke: stroke||fill, strokeWidth: strokeWidth||4, originX:'center', originY:'center' });
    } else if (newType === 'arrow') {
        const line = new fabric.Line([left-50, top, left+50, top], { stroke: stroke||fill, strokeWidth: strokeWidth||4, originX:'center', originY:'center' });
        const head = new fabric.Triangle({ left: left+50, top, width: 10 + (strokeWidth||4)*2, height: (10 + (strokeWidth||4)*2)*1.2, fill: stroke||fill, angle: 90, originX:'center', originY:'center' });
        newObj = new fabric.Group([line, head]);
        newObj._isArrow = true;
    }
    
    if (newObj) {
        newObj.customShapeType = newType;
        fabricCanvas.remove(o);
        fabricCanvas.add(newObj);
        fabricCanvas.setActiveObject(newObj);
        fabricCanvas.renderAll();
        paintSaveHist();
    }
}

function paintUpdateNoteBadge(val) {
    const o = fabricCanvas?.getActiveObject();
    if(!o || o.customType !== 'note') return;
    
    o.customBadge = val;
    let txt = o.text.replace(/^\[IMPORTANTE\]\n?/, '').replace(/^\[NUEVO\]\n?/, '');
    o.text = txt;
    
    fabricCanvas.renderAll();
    paintSaveHist();
}

function paintFlipImage() {
    const o = fabricCanvas?.getActiveObject();
    if (o && o.type === 'image') {
        o.set('flipX', !o.flipX);
        fabricCanvas.renderAll();
        paintSaveHist();
    }
}

function paintDeleteSelected() {
    if (!fabricCanvas) return;
    fabricCanvas.getActiveObjects().forEach(o => fabricCanvas.remove(o));
    fabricCanvas.discardActiveObject(); fabricCanvas.renderAll();
}
function paintDuplicate() {
    if (!fabricCanvas) return;
    const o = fabricCanvas.getActiveObject(); if (!o) return;
    o.clone(cl => { cl.set({ left: cl.left+30, top: cl.top+30 }); fabricCanvas.add(cl); fabricCanvas.setActiveObject(cl); fabricCanvas.renderAll(); });
}

function paintSaveHist() {
    if (!fabricCanvas) return;
    const vpt = fabricCanvas.viewportTransform.slice();
    fabricCanvas.setViewportTransform([1, 0, 0, 1, 0, 0]);
    const j = getPaintCanvasJSON();
    fabricCanvas.setViewportTransform(vpt);
    if (paintUndoStack.length && paintUndoStack[paintUndoStack.length-1] === j) return;
    paintUndoStack.push(j); if (paintUndoStack.length > 40) paintUndoStack.shift();
    paintRedoStack = [];
}
function paintOnObjectAdded(e) {
    if (e.target && e.target.customType === 'note') {
        e.target.setControlsVisibility({ mt: false, mb: false, tr: false, tl: false, br: false, bl: false });
    }
    paintSaveHist(e);
}

let paintGuideLines = [];
function paintClearGuidelines() {
    if (paintGuideLines.length > 0) {
        paintGuideLines.forEach(l => fabricCanvas.remove(l));
        paintGuideLines = [];
        fabricCanvas.renderAll();
    }
}
function paintDrawGuideline(x1, y1, x2, y2) {
    const line = new fabric.Line([x1, y1, x2, y2], { stroke: '#ef4444', strokeWidth: 1, selectable: false, evented: false, strokeDashArray: [5, 5] });
    paintGuideLines.push(line);
    fabricCanvas.add(line);
}
function paintOnObjectMoving(e) {
    paintClearGuidelines();
    const obj = e.target;
    if (!obj) return;

    const snapDist = 12;
    const canvasCx = fabricCanvas.width / 2;
    const canvasCy = fabricCanvas.height / 2;
    
    // Simplificado para calcular centros independientemente del origin
    const objCx = obj.left + (obj.originX === 'center' ? 0 : (obj.width * obj.scaleX) / 2);
    const objCy = obj.top + (obj.originY === 'center' ? 0 : (obj.height * obj.scaleY) / 2);

    let snapped = false;

    // Center snapping
    if (Math.abs(objCx - canvasCx) < snapDist) {
        obj.set({ left: canvasCx - (obj.originX === 'center' ? 0 : (obj.width * obj.scaleX) / 2) });
        paintDrawGuideline(canvasCx, 0, canvasCx, fabricCanvas.height);
        snapped = true;
    }
    if (Math.abs(objCy - canvasCy) < snapDist) {
        obj.set({ top: canvasCy - (obj.originY === 'center' ? 0 : (obj.height * obj.scaleY) / 2) });
        paintDrawGuideline(0, canvasCy, fabricCanvas.width, canvasCy);
        snapped = true;
    }

    // Object snapping
    const objects = fabricCanvas.getObjects();
    const checkLen = Math.min(objects.length, 15);
    for (let i = objects.length - 1; i >= objects.length - checkLen; i--) {
        const target = objects[i];
        if (target === obj || paintGuideLines.includes(target) || target.type === 'line' || target.id === 'crop-overlay') continue;

        const targetCx = target.left + (target.originX === 'center' ? 0 : (target.width * target.scaleX) / 2);
        const targetCy = target.top + (target.originY === 'center' ? 0 : (target.height * target.scaleY) / 2);
        
        if (Math.abs(objCx - targetCx) < snapDist) {
            obj.set({ left: targetCx - (obj.originX === 'center' ? 0 : (obj.width * obj.scaleX) / 2) });
            paintDrawGuideline(targetCx, 0, targetCx, fabricCanvas.height);
            snapped = true;
        }
        if (Math.abs(objCy - targetCy) < snapDist) {
            obj.set({ top: targetCy - (obj.originY === 'center' ? 0 : (obj.height * obj.scaleY) / 2) });
            paintDrawGuideline(0, targetCy, fabricCanvas.width, targetCy);
            snapped = true;
        }
    }
    if (snapped) fabricCanvas.renderAll();
}

function paintUndo() {
    if (paintUndoStack.length <= 1) return;
    paintRedoStack.push(paintUndoStack.pop());
    fabricCanvas.off('object:added'); fabricCanvas.off('object:modified'); fabricCanvas.off('object:removed');
    fabricCanvas.loadFromJSON(paintUndoStack[paintUndoStack.length-1], () => { fabricCanvas.renderAll(); fabricCanvas.on('object:added', paintOnObjectAdded); fabricCanvas.on('object:modified', paintSaveHist); fabricCanvas.on('object:removed', paintSaveHist); });
}
function paintRedo() {
    if (!paintRedoStack.length) return;
    const d = paintRedoStack.pop(); paintUndoStack.push(d);
    fabricCanvas.off('object:added'); fabricCanvas.off('object:modified'); fabricCanvas.off('object:removed');
    fabricCanvas.loadFromJSON(d, () => { fabricCanvas.renderAll(); fabricCanvas.on('object:added', paintOnObjectAdded); fabricCanvas.on('object:modified', paintSaveHist); fabricCanvas.on('object:removed', paintSaveHist); });
}
function paintClearAll() { if (!fabricCanvas) return; fabricCanvas.clear(); fabricCanvas.backgroundColor = '#ffffff'; fabricCanvas.renderAll(); }

function paintShowLoader(text) {
    const el = document.getElementById('paint-loading-overlay');
    if(el) {
        document.getElementById('paint-loading-text').textContent = text || 'Procesando...';
        el.style.display = 'flex';
    }
}
function paintHideLoader() {
    const el = document.getElementById('paint-loading-overlay');
    if(el) el.style.display = 'none';
}

async function paintOnFileLoad(input) {
    if (!input.files[0]) return;
    const file = input.files[0];
    
    const btn = document.querySelector('button[onclick*="paint-load-file"]');
    const oldHtml = btn ? btn.innerHTML : '';
    if(btn) { btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i>'; btn.disabled = true; }
    paintShowLoader('Subiendo imagen...');
    
    try {
        const fd = new FormData();
        fd.append('image', file);
        const monthIdInput = document.querySelector('input[name="month_id"]');
        if (monthIdInput) fd.append('month_id', monthIdInput.value);
        fd.append('post_type', 'Referencia Visual');
        
        const res = await (await fetch('modules/month_board/ajax_upload_reference.php', { method:'POST', body:fd })).json();
        if (res.success) {
            paintShowLoader('Renderizando imagen...');
            fabric.Image.fromURL(res.url, img => {
                const ratioSelect = document.getElementById('paint-aspect-ratio');
                const isAuto = !ratioSelect || ratioSelect.value === 'auto';
                if (fabricCanvas.getObjects().length === 0 && isAuto) {
                    // Si el lienzo está vacío y está en Auto, adaptarlo exactamente a la imagen
                    fabricCanvas.setWidth(img.width);
                    fabricCanvas.setHeight(img.height);
                    const wrap = fabricCanvas.wrapperEl;
                    const p = wrap.parentElement;
                    const scale = Math.min((p.clientWidth - 32) / img.width, (p.clientHeight - 32) / img.height, 1);
                    wrap.style.width = Math.floor(img.width * scale) + 'px';
                    wrap.style.height = Math.floor(img.height * scale) + 'px';
                    fabricCanvas.calcOffset();
                    img.set({ left: img.width/2, top: img.height/2, originX:'center', originY:'center', scaleX:1, scaleY:1, crossOrigin: 'anonymous' });
                } else {
                    // Si ya hay cosas, o NO está en Auto, insertarla centrada escalada sin cambiar el lienzo
                    const s = Math.min((fabricCanvas.width*0.8)/img.width, (fabricCanvas.height*0.8)/img.height, 1);
                    img.set({ left: fabricCanvas.width/2, top: fabricCanvas.height/2, originX:'center', originY:'center', scaleX:s, scaleY:s, crossOrigin: 'anonymous' });
                }
                fabricCanvas.add(img); fabricCanvas.setActiveObject(img); fabricCanvas.renderAll(); paintSaveHist();
                paintHideLoader();
            }, { crossOrigin: 'anonymous' });
        } else {
            paintHideLoader();
            showToast(res.error || 'Error al subir imagen');
        }
    } catch(e) {
        paintHideLoader();
        console.error(e);
        showToast('Error de red al insertar imagen');
    } finally {
        if(btn) { btn.innerHTML = oldHtml; btn.disabled = false; }
        input.value = '';
    }
}

function paintCreateStickerGroup(type, center) {
    let iconObj, textObj, bgObj;
    
    const textConfig = { fontSize: 24, fontWeight: 'bold', fontFamily:'Inter, sans-serif', fill: '#000000', originY:'center', top: 0 };
    const bgConfig = { fill: '#ffffff', stroke: '#e2e8f0', strokeWidth: 2, originX:'center', originY:'center', left: 0, top: 0 };

    if (type === 'sticker-logo-circle') {
        bgObj = new fabric.Circle({ ...bgConfig, radius: 40 });
        textObj = new fabric.IText('LOGO', { ...textConfig, originX:'center', left: 0 });
    } else if (type === 'sticker-logo-pill') {
        bgObj = new fabric.Rect({ ...bgConfig, width: 140, height: 60, rx: 30, ry: 30 });
        textObj = new fabric.IText('LOGO', { ...textConfig, originX:'center', left: 0 });
    } else if (type === 'sticker-whatsapp') {
        bgObj = new fabric.Rect({ ...bgConfig, width: 300, height: 60, rx: 30, ry: 30 });
        const path = 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z';
        iconObj = new fabric.Path(path, { fill: '#25D366', originX:'center', originY:'center', scaleX: 1.5, scaleY: 1.5, left: -110, top: 0 });
        textObj = new fabric.IText('+00 000 000 000', { ...textConfig, originX:'left', left: -80 });
    } else if (type === 'sticker-social') {
        bgObj = new fabric.Rect({ ...bgConfig, width: 200, height: 60, rx: 30, ry: 30 });
        const path = 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z';
        iconObj = new fabric.Path(path, { fill: '#E1306C', originX:'center', originY:'center', scaleX: 1.5, scaleY: 1.5, left: -60, top: 0 });
        textObj = new fabric.IText('@usuario', { ...textConfig, originX:'left', left: -30 });
    } else if (type === 'sticker-web') {
        bgObj = new fabric.Rect({ ...bgConfig, width: 330, height: 60, rx: 30, ry: 30 });
        const path = 'M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm6.969 5.235c-1.388-.636-2.915-1.026-4.512-1.144.33.627.618 1.3.864 2.018 1.448.337 2.766 1 3.916 1.938-.072-1.016-.318-1.97-.732-2.833zm-4.331 4.793a18.3 18.3 0 0 0-1.118-2.646c1.657.172 3.19.742 4.512 1.637-.923 1.611-2.096 3.011-3.418 4.148zm-2.65-2.646c.394 1.258.683 2.611.85 4.025-1.037.132-2.115.201-3.228.201-1.112 0-2.19-.069-3.228-.201.167-1.414.456-2.767.85-4.025 1.059-.283 2.164-.436 3.298-.436 1.134 0 2.239.153 3.298.436zm-3.228-5.31c.96 0 1.884.144 2.76.41-1.133.565-2.158 1.309-3.045 2.199a13.308 13.308 0 0 1-.587-2.584c.28-.016.562-.025.872-.025zm-2.33 5.485c.246-.718.534-1.391.864-2.018-1.597.118-3.124.508-4.512 1.144-.414.863-.66 1.817-.732 2.833 1.15-.938 2.468-1.601 3.916-1.938zm-1.042 2.215a18.303 18.303 0 0 0-1.118 2.646c-1.322-1.137-2.495-2.537-3.418-4.148 1.322-.895 2.855-1.465 4.512-1.637v.001zm5.352 8.791c-1.134 0-2.239-.153-3.298-.436-.394-1.258-.683-2.611-.85-4.025 1.037-.132 2.115-.201 3.228-.201s2.19.069 3.228.201c-.167 1.414-.456 2.767-.85 4.025-1.059.283-2.164.436-3.298.436zm-3.353.468c.282.723.633 1.406 1.04 2.032-1.071-.476-2.037-1.142-2.855-1.96a13.435 13.435 0 0 1 .458-2.673c1.096.793 2.302 1.43 3.587 1.884l-.116.033v-.001zm3.353 3.011c-1.096 0-2.146-.174-3.136-.492.368-.621.684-1.286.945-1.988a10.02 10.02 0 0 0 4.382 0c.261.702.577 1.367.945 1.988-.99.318-2.04.492-3.136.492zm1.606-2.261a13.407 13.407 0 0 0 1.04-2.032l-.116-.033a10.74 10.74 0 0 0 3.587-1.884 13.486 13.486 0 0 1 .458 2.673c-.818.818-1.784 1.484-2.855 1.96zM18.86 6.305A9.972 9.972 0 0 0 12 2.03c-.274 0-.546.012-.816.034.981 1.093 1.776 2.36 2.348 3.751 1.637-.099 3.2-.5 4.629-1.157a9.92 9.92 0 0 0 .699 1.647z';
        iconObj = new fabric.Path(path, { fill: '#0ea5e9', originX:'center', originY:'center', scaleX: 1.5, scaleY: 1.5, left: -125, top: 0 });
        textObj = new fabric.IText('www.tuempresa.com', { ...textConfig, originX:'left', left: -95 });
    } else if (type === 'sticker-ui-button') {
        bgObj = new fabric.Rect({ ...bgConfig, width: 220, height: 60, rx: 8, ry: 8, fill: '#3b82f6', stroke: null });
        textObj = new fabric.IText('Botón Web', { ...textConfig, fill: '#ffffff', originX:'center', originY:'center', left: 0, fontSize: 20 });
    } else if (type === 'sticker-ui-progress') {
        bgObj = new fabric.Rect({ ...bgConfig, width: 300, height: 24, rx: 12, ry: 12, fill: '#e2e8f0', stroke: null });
        iconObj = new fabric.Rect({ ...bgConfig, width: 150, height: 24, rx: 12, ry: 12, fill: '#3b82f6', stroke: null, left: -75 });
        textObj = new fabric.IText('50%', { ...textConfig, originX:'center', originY:'center', left: 0, fontSize: 14, fill: '#ffffff' });
    } else if (type === 'sticker-ui-input') {
        bgObj = new fabric.Rect({ ...bgConfig, width: 300, height: 60, rx: 8, ry: 8, fill: '#ffffff', stroke: '#cbd5e1', strokeWidth: 2 });
        textObj = new fabric.IText('Escribe aquí...', { ...textConfig, originX:'left', originY:'center', left: -130, fontSize: 18, fill: '#94a3b8', fontWeight: 'normal' });
    }

    if (textObj) {
        const groupElements = bgObj ? [bgObj, iconObj, textObj].filter(Boolean) : [iconObj, textObj].filter(Boolean);
        const group = new fabric.Group(groupElements);
        group.set({ left: center.x, top: center.y, originX: 'center', originY: 'center' });
        group.customType = 'sticker';
        group.stickerVariant = type;
        return group;
    }
    return null;
}

function paintUpdateStickerVariant(variant) {
    const obj = fabricCanvas.getActiveObject();
    if (!obj || obj.customType !== 'sticker') return;
    
    const center = obj.getCenterPoint();
    const scaleX = obj.scaleX;
    const scaleY = obj.scaleY;
    const angle = obj.angle;
    
    fabricCanvas.remove(obj);
    
    const newGroup = paintCreateStickerGroup(variant, center);
    if (newGroup) {
        newGroup.set({ scaleX: scaleX, scaleY: scaleY, angle: angle });
        fabricCanvas.add(newGroup);
        fabricCanvas.setActiveObject(newGroup);
        fabricCanvas.renderAll();
        paintSaveHist();
    }
}

function paintMD(opt) {
    if (paintCurrentTool === 'select' || paintCurrentTool === 'pencil') return;
    const ptr = fabricCanvas.getPointer(opt.e);
    if (fabricCanvas.findTarget(opt.e)) return;
    paintIsAdding = true; paintShapeStart = { x: ptr.x, y: ptr.y };
    
    const colorEl = document.getElementById('paint-color');
    const color = colorEl ? colorEl.value : '#3b82f6';
    
    const swEl = document.getElementById('paint-stroke-size');
    const sw = swEl ? parseInt(swEl.value) : 2;

    if (paintCurrentTool === 'text' || paintCurrentTool === 'note') {
        paintIsAdding = false;
        const isNote = paintCurrentTool === 'note';
        
        const fsEl = document.getElementById('paint-font-size');
        const fontSize = isNote ? 24 : (fsEl ? parseInt(fsEl.value) : 32);
        
        const fwEl = document.getElementById('paint-font-weight');
        const fontWeight = isNote ? 'normal' : (fwEl ? fwEl.value : 'bold');

        const t = new fabric.IText(isNote ? 'Nota' : 'Texto', { 
            left:ptr.x, top:ptr.y, fontSize: fontSize, 
            fontWeight: fontWeight, 
            fill: isNote ? '#000000' : color, backgroundColor: isNote ? '#fef08a' : 'transparent',
            padding: isNote ? 12 : 0, fontFamily:'Inter, sans-serif', editable:true 
        });
        fabricCanvas.add(t); fabricCanvas.setActiveObject(t); t.enterEditing(); t.selectAll();
        fabricCanvas.forEachObject(o => { o.selectable=true; o.evented=true; }); fabricCanvas.selection=true;
        return;
    }

    if (paintCurrentTool === 'rect') paintTempShape = new fabric.Rect({ left:ptr.x, top:ptr.y, width:1, height:1, fill:'transparent', stroke:color, strokeWidth:sw, strokeUniform:true });
    else if (paintCurrentTool === 'circle') paintTempShape = new fabric.Ellipse({ left:ptr.x, top:ptr.y, rx:1, ry:1, fill:'transparent', stroke:color, strokeWidth:sw, strokeUniform:true, originX:'center', originY:'center' });
    else if (paintCurrentTool === 'line') paintTempShape = new fabric.Line([ptr.x,ptr.y,ptr.x,ptr.y], { stroke:color, strokeWidth:sw, strokeUniform:true });
    else if (paintCurrentTool === 'arrow') { paintTempShape = new fabric.Line([ptr.x,ptr.y,ptr.x,ptr.y], { stroke:color, strokeWidth:sw, strokeUniform:true }); paintTempShape._isArrow = true; }
    else if (paintCurrentTool === 'triangle') paintTempShape = new fabric.Polygon([{x:50, y:0}, {x:100, y:100}, {x:0, y:100}], { left:ptr.x, top:ptr.y, fill:'transparent', stroke:color, strokeWidth:sw, strokeUniform:true, scaleX:0.01, scaleY:0.01, originX:'left', originY:'top' });
    else if (paintCurrentTool === 'star') paintTempShape = new fabric.Polygon([{x:50, y:0}, {x:61, y:35}, {x:98, y:35}, {x:68, y:57}, {x:79, y:91}, {x:50, y:70}, {x:21, y:91}, {x:32, y:57}, {x:2, y:35}, {x:39, y:35}], { left:ptr.x, top:ptr.y, fill:'transparent', stroke:color, strokeWidth:sw, strokeUniform:true, scaleX:0.01, scaleY:0.01, originX:'left', originY:'top' });
    else if (paintCurrentTool === 'hexagon') paintTempShape = new fabric.Polygon([{x:50, y:0}, {x:100, y:25}, {x:100, y:75}, {x:50, y:100}, {x:0, y:75}, {x:0, y:25}], { left:ptr.x, top:ptr.y, fill:'transparent', stroke:color, strokeWidth:sw, strokeUniform:true, scaleX:0.01, scaleY:0.01, originX:'left', originY:'top' });
    else if (paintCurrentTool === 'chat') {
        const pathData = "M10 10 H 90 Q 100 10 100 20 V 70 Q 100 80 90 80 H 40 L 10 100 V 80 Q 0 80 0 70 V 20 Q 0 10 10 10 Z";
        paintTempShape = new fabric.Path(pathData, { left:ptr.x, top:ptr.y, fill:'transparent', stroke:color, strokeWidth:sw, strokeUniform:true, scaleX:0.01, scaleY:0.01, originX:'left', originY:'top' });
    }

    if (paintTempShape) { paintTempShape.selectable=false; paintTempShape.evented=false; fabricCanvas.add(paintTempShape); }
}

function paintMM(opt) {
    if (!paintIsAdding || !paintTempShape) return;
    const p = fabricCanvas.getPointer(opt.e), sx = paintShapeStart.x, sy = paintShapeStart.y;
    if (paintCurrentTool === 'rect') { const w=p.x-sx, h=p.y-sy; paintTempShape.set({ left: w>0?sx:p.x, top: h>0?sy:p.y, width:Math.abs(w), height:Math.abs(h) }); }
    else if (paintCurrentTool === 'circle') { paintTempShape.set({ rx:Math.abs(p.x-sx)/2, ry:Math.abs(p.y-sy)/2, left:(sx+p.x)/2, top:(sy+p.y)/2 }); }
    else if (paintCurrentTool === 'line' || paintCurrentTool === 'arrow') { paintTempShape.set({ x2:p.x, y2:p.y }); }
    else if (['triangle', 'star', 'hexagon', 'chat'].includes(paintCurrentTool)) {
        const w = p.x - sx, h = p.y - sy;
        paintTempShape.set({
            scaleX: Math.abs(w) / 100,
            scaleY: Math.abs(h) / 100,
            left: w > 0 ? sx : p.x,
            top: h > 0 ? sy : p.y
        });
    }
    fabricCanvas.renderAll();
}

function paintMU(opt) {
    paintClearGuidelines();
    if (!paintIsAdding) return;
    paintIsAdding = false;
    if (paintTempShape?._isArrow) {
        const x1=paintTempShape.x1,y1=paintTempShape.y1,x2=paintTempShape.x2,y2=paintTempShape.y2;
        const angle=Math.atan2(y2-y1,x2-x1)*180/Math.PI, sw=paintTempShape.strokeWidth, hl=10+sw*2;
        const head = new fabric.Triangle({ left:x2, top:y2, width:hl, height:hl*1.2, fill:paintTempShape.stroke, angle:angle+90, originX:'center', originY:'center', selectable:false, evented:false });
        const line = paintTempShape; fabricCanvas.remove(line);
        const grp = new fabric.Group([line, head], { selectable:true, evented:true });
        fabricCanvas.add(grp);
        paintTempShape = null; return;
    }
    if (paintTempShape) { paintTempShape.selectable=true; paintTempShape.evented=true; paintTempShape.setCoords(); fabricCanvas.renderAll(); paintTempShape=null; }
}

let paintClipboard = null;
function paintCopy() { 
    const o=fabricCanvas?.getActiveObject(); 
    if(o){ 
        o.clone(c => paintClipboard=c); 
        try {
            const input = document.createElement('textarea');
            input.value = 'PAINT_INTERNAL_CLIPBOARD';
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        } catch(err) {}
    } 
}
function paintPaste() { 
    if(!paintClipboard || !fabricCanvas) return;
    paintClipboard.clone(c => {
        fabricCanvas.discardActiveObject();
        c.set({left:c.left+20, top:c.top+20, evented:true});
        if(c.type === 'activeSelection'){ c.canvas = fabricCanvas; c.forEachObject(obj => fabricCanvas.add(obj)); c.setCoords(); }
        else { fabricCanvas.add(c); }
        paintClipboard.top+=20; paintClipboard.left+=20;
        fabricCanvas.setActiveObject(c); fabricCanvas.renderAll(); paintSaveHist();
    });
}
function paintBringForward() { const o=fabricCanvas?.getActiveObject(); if(o){ fabricCanvas.bringForward(o); fabricCanvas.renderAll(); paintSaveHist(); } }
function paintSendBackward() { const o=fabricCanvas?.getActiveObject(); if(o){ fabricCanvas.sendBackwards(o); fabricCanvas.renderAll(); paintSaveHist(); } }
function paintReplaceSelectedImage(input) {
    const o = fabricCanvas?.getActiveObject();
    if (!o || o.type !== 'image' || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = ev => {
        o.setSrc(ev.target.result, () => {
            const s = Math.min((fabricCanvas.width*0.8)/o.width, (fabricCanvas.height*0.8)/o.height, 1);
            o.set({scaleX:s, scaleY:s}); fabricCanvas.renderAll(); paintSaveHist();
        });
    };
    reader.readAsDataURL(input.files[0]); input.value='';
}

// === CAPAS ===
function paintRenderLayers() {
    const list = document.getElementById('paint-layers-list');
    if (!list || !fabricCanvas) return;
    const objs = fabricCanvas.getObjects();
    let html = '';
    const activeObj = fabricCanvas.getActiveObject();
    
    for (let i = objs.length - 1; i >= 0; i--) {
        const o = objs[i];
        const isSel = activeObj === o;
        const icon = (o.type === 'image') ? 'ph-image' : ((o.type==='i-text' || o.type==='textbox') ? 'ph-text-t' : 'ph-shapes');
        const name = o.customName || o.type + ' ' + i;
        const color = o.customColor || '#94a3b8';
        const lockIcon = (o.selectable === false && o.evented === false && !o.isDrawingMode) ? 'ph-lock' : 'ph-lock-open';
        
        html += `<div class="paint-layer-item ${isSel?'active':''}" data-index="${i}">
                    <div class="paint-layer-color-tag" style="background:${color}" onclick="event.stopPropagation(); paintChangeLayerColor(${i});"></div>
                    <i class="ph ${icon} paint-layer-icon"></i>
                    <div class="paint-layer-name" ondblclick="event.stopPropagation(); paintRenameLayer(${i});">${name}</div>
                    <div class="paint-layer-actions">
                        <button class="paint-layer-action-btn" onclick="event.stopPropagation(); paintToggleLayerLock(${i});" title="Bloquear"><i class="ph ${lockIcon}"></i></button>
                    </div>
                 </div>`;
    }
    list.innerHTML = html;
    
    list.querySelectorAll('.paint-layer-item').forEach(el => {
        el.addEventListener('click', function(e) {
            if (e.target.closest('.paint-layer-action-btn') || e.target.closest('.paint-layer-color-tag') || e.target.closest('.paint-layer-name')) return;
            const idx = parseInt(this.dataset.index);
            const obj = fabricCanvas.getObjects()[idx];
            if (obj && obj.selectable) {
                fabricCanvas.setActiveObject(obj);
                fabricCanvas.renderAll();
            }
        });
    });
    
    if (!list._sortable) {
        list._sortable = new Sortable(list, {
            animation: 150,
            onEnd: function (evt) {
                const objs = fabricCanvas.getObjects();
                const oldIndex = objs.length - 1 - evt.oldIndex;
                const newIndex = objs.length - 1 - evt.newIndex;
                const o = objs[oldIndex];
                if (o) {
                    o.moveTo(newIndex);
                    fabricCanvas.renderAll();
                    paintSaveHist();
                    paintRenderLayers();
                }
            }
        });
    }
}

function paintToggleLayerLock(idx) {
    const obj = fabricCanvas.getObjects()[idx];
    if(obj) {
        const isLocked = !(obj.selectable || obj.evented);
        obj.selectable = isLocked;
        obj.evented = isLocked;
        fabricCanvas.discardActiveObject();
        fabricCanvas.renderAll();
        paintRenderLayers();
        paintSaveHist();
    }
}

function paintRenameLayer(idx) {
    const obj = fabricCanvas.getObjects()[idx];
    if(obj) {
        const current = obj.customName || obj.type + ' ' + idx;
        const res = prompt("Nombre de la capa:", current);
        if (res !== null && res.trim() !== '') {
            obj.customName = res.trim();
            paintRenderLayers();
            paintSaveHist();
        }
    }
}

function paintChangeLayerColor(idx) {
    const obj = fabricCanvas.getObjects()[idx];
    if(obj) {
        const colors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#000000', '#94a3b8'];
        let curIdx = colors.indexOf(obj.customColor || '#94a3b8');
        curIdx = (curIdx + 1) % colors.length;
        obj.customColor = colors[curIdx];
        paintRenderLayers();
        paintSaveHist();
    }
}

// === RIGHT CLICK MENU ===
let paintCtxTarget = null;

document.addEventListener('contextmenu', function(e) {
    const modal = document.getElementById('paint-editor-modal');
    if (!modal || !modal.classList.contains('active')) return;
    
    const wrap = document.getElementById('paint-canvas-wrap');
    if (wrap && wrap.contains(e.target)) {
        e.preventDefault();
        let targetObj = null;
        if (e.target.tagName.toLowerCase() === 'canvas') {
            // Find target manually so we can select locked objects (which have evented=false)
            const pointer = fabricCanvas.getPointer(e);
            const objs = fabricCanvas.getObjects();
            for(let i = objs.length - 1; i >= 0; i--) {
                if (objs[i].containsPoint(pointer)) {
                    targetObj = objs[i];
                    break;
                }
            }
        }
        
        paintCtxTarget = targetObj;
        
        if (targetObj && targetObj.selectable) {
            fabricCanvas.setActiveObject(targetObj);
            fabricCanvas.renderAll();
        } else if (!targetObj) {
            fabricCanvas.discardActiveObject();
            fabricCanvas.renderAll();
        }
        
        const menu = document.getElementById('paint-ctx-menu');
        menu.style.left = e.clientX + 'px';
        menu.style.top = e.clientY + 'px';
        menu.classList.add('active');
    }
});

document.addEventListener('click', function(e) {
    const menu = document.getElementById('paint-ctx-menu');
    if (menu && menu.classList.contains('active')) {
        menu.classList.remove('active');
    }
});

function paintCtxCut() { paintCopy(); paintDeleteSelected(); }
function paintCtxCopy() { paintCopy(); }
function paintCtxPaste() { paintPaste(); }
function paintCtxBringForward() { paintBringForward(); }
function paintCtxSendBackward() { paintSendBackward(); }
function paintCtxUndo() { paintUndo(); }
function paintCtxToggleLock() {
    const o = paintCtxTarget || fabricCanvas?.getActiveObject();
    if (o) {
        const isLocked = !(o.selectable || o.evented);
        o.selectable = isLocked;
        o.evented = isLocked;
        fabricCanvas.discardActiveObject();
        fabricCanvas.renderAll();
        paintSaveHist();
        paintRenderLayers();
    }
}

// === CROPPER ===
let paintCropper = null;
let paintCropOriginalObj = null;

function paintOpenCropModal() {
    const obj = fabricCanvas?.getActiveObject();
    if (!obj || obj.type !== 'image') return;
    
    paintCropOriginalObj = obj;
    const modal = document.getElementById('paint-crop-modal');
    const imgTarget = document.getElementById('paint-crop-img-target');
    
    imgTarget.src = obj.getSrc();
    modal.classList.add('active');
    
    if (paintCropper) paintCropper.destroy();
    setTimeout(() => {
        paintCropper = new Cropper(imgTarget, {
            viewMode: 1,
            autoCropArea: 1,
            background: false
        });
    }, 100);
}

function paintCloseCropModal() {
    const modal = document.getElementById('paint-crop-modal');
    modal.classList.remove('active');
    if (paintCropper) { paintCropper.destroy(); paintCropper = null; }
}

function paintApplyCrop() {
    if (!paintCropper || !paintCropOriginalObj) return;
    const canvasData = paintCropper.getCroppedCanvas();
    if (!canvasData) return;
    
    const dataUrl = canvasData.toDataURL('image/png');
    const obj = paintCropOriginalObj;
    
    obj.setSrc(dataUrl, () => {
        fabricCanvas.renderAll();
        paintSaveHist();
        paintCloseCropModal();
    });
}

function paintChangeRatio(ratio) {
    if (!fabricCanvas) return;
    let w = 1080, h = 1080;
    if (ratio === '4:5') { w = 1080; h = 1350; }
    else if (ratio === '16:9') { w = 1920; h = 1080; }
    else if (ratio === '9:16') { w = 1080; h = 1920; }
    
    fabricCanvas.setWidth(w);
    fabricCanvas.setHeight(h);
    
    const wrap = document.getElementById('paint-canvas-wrap');
    const maxW = wrap.clientWidth - 30, maxH = wrap.clientHeight - 30;
    const scale = Math.min(maxW / w, maxH / h, 1);
    const ct = fabricCanvas.wrapperEl;
    ct.style.width = Math.floor(w * scale) + 'px';
    ct.style.height = Math.floor(h * scale) + 'px';
    fabricCanvas.calcOffset();
    fabricCanvas.renderAll();
    paintSaveHist();
}

document.addEventListener('keydown', e => {
    if (!document.getElementById('paint-editor-modal').classList.contains('active')) return;
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    
    if (!e.ctrlKey && !e.altKey && !e.metaKey) {
        if(e.key.toLowerCase()==='v') document.querySelector('[data-tool="select"]')?.click();
        if(e.key.toLowerCase()==='p') document.querySelector('[data-tool="pencil"]')?.click();
        if(e.key.toLowerCase()==='t') document.querySelector('[data-tool="text"]')?.click();
        if(e.key.toLowerCase()==='n') document.querySelector('[data-tool="note"]')?.click();
        if(e.key.toLowerCase()==='r') document.querySelector('[data-tool="rect"]')?.click();
        if(e.key.toLowerCase()==='c') document.querySelector('[data-tool="circle"]')?.click();
    }

    if ((e.key === 'Delete' || e.key === 'Backspace') && fabricCanvas && !fabricCanvas.getActiveObject()?.isEditing) { paintDeleteSelected(); e.preventDefault(); }
    const isCmd = e.ctrlKey || e.metaKey;
    if (isCmd && e.key.toLowerCase() === 'z') { paintUndo(); e.preventDefault(); }
    if (isCmd && e.key.toLowerCase() === 'y') { paintRedo(); e.preventDefault(); }
    if (isCmd && e.key.toLowerCase() === 'd') { paintDuplicate(); e.preventDefault(); }
    if (isCmd && e.key.toLowerCase() === 'c') { paintCopy(); e.preventDefault(); }
    // Removed e.preventDefault() for 'v' so the native paste event triggers normally.
});

function paintCompressImage(file) {
    return new Promise((resolve) => {
        if (!file.type.startsWith('image/')) return resolve(file);
        if (file.size < 1024 * 1024) return resolve(file); // Only compress if > 1MB
        
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const cvs = document.createElement('canvas');
                let w = img.width, h = img.height;
                const MAX_DIM = 2048;
                if (w > MAX_DIM || h > MAX_DIM) {
                    if (w > h) { h = Math.round((h * MAX_DIM) / w); w = MAX_DIM; }
                    else { w = Math.round((w * MAX_DIM) / h); h = MAX_DIM; }
                }
                cvs.width = w; cvs.height = h;
                const ctx = cvs.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);
                cvs.toBlob((blob) => {
                    resolve(new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".webp", { type: 'image/webp' }));
                }, 'image/webp', 0.75);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

document.addEventListener('paste', async e => {
    if (!document.getElementById('paint-editor-modal').classList.contains('active')) return;
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    
    const cd = e.clipboardData || e.originalEvent?.clipboardData;
    if (!cd) return;

    const textData = cd.getData('text/plain');
    if (textData === 'PAINT_INTERNAL_CLIPBOARD' && paintClipboard) {
        e.preventDefault();
        paintPaste();
        return;
    }

    let pastedImage = false;
    if (cd.items) {
        for (let i = 0; i < cd.items.length; i++) {
            const item = cd.items[i];
            if (item.kind === 'file' && item.type.includes('image/')) {
                pastedImage = true;
                e.preventDefault();
                
                let blob = item.getAsFile();
                if (blob) {
                    // Try to generate a fake name for pasted images
                    blob = new File([blob], "pasted_image.png", { type: blob.type });
                    blob = await paintCompressImage(blob);
                }
                const fd = new FormData();
                fd.append('image', blob);
                const monthIdInput = document.querySelector('input[name="month_id"]');
                if (monthIdInput) fd.append('month_id', monthIdInput.value);
                fd.append('post_type', 'Referencia Visual');
                
                paintShowLoader('Pegando imagen...');
                try {
                    const res = await (await fetch('modules/month_board/ajax_upload_reference.php', { method:'POST', body:fd })).json();
                    if (res.success) {
                        paintShowLoader('Renderizando pegado...');
                        fabric.Image.fromURL(res.url, img => {
                            const ratioSelect = document.getElementById('paint-aspect-ratio');
                            const isAuto = !ratioSelect || ratioSelect.value === 'auto';
                            if (fabricCanvas.getObjects().length === 0 && isAuto) {
                                // Adaptar lienzo si está vacío y en auto
                                fabricCanvas.setWidth(img.width);
                                fabricCanvas.setHeight(img.height);
                                const wrap = fabricCanvas.wrapperEl;
                                const p = wrap.parentElement;
                                const scale = Math.min((p.clientWidth - 32) / img.width, (p.clientHeight - 32) / img.height, 1);
                                wrap.style.width = Math.floor(img.width * scale) + 'px';
                                wrap.style.height = Math.floor(img.height * scale) + 'px';
                                fabricCanvas.calcOffset();
                                img.set({ left: img.width/2, top: img.height/2, originX:'center', originY:'center', scaleX:1, scaleY:1, crossOrigin: 'anonymous' });
                            } else {
                                const s = Math.min((fabricCanvas.width*0.8)/img.width, (fabricCanvas.height*0.8)/img.height, 1);
                                img.set({ left: fabricCanvas.width/2, top: fabricCanvas.height/2, originX:'center', originY:'center', scaleX:s, scaleY:s, crossOrigin: 'anonymous' });
                            }
                            fabricCanvas.add(img); fabricCanvas.setActiveObject(img); fabricCanvas.renderAll(); paintSaveHist();
                            paintHideLoader();
                        }, { crossOrigin: 'anonymous' });
                    } else {
                        paintHideLoader();
                        showToast(res.error || 'Error al procesar pegado de imagen');
                    }
                } catch(err) { 
                    paintHideLoader();
                    console.error(err); 
                    showToast('Error de red al pegar imagen');
                }
                return;
            }
        }
    }

    if (!pastedImage) {
        const text = cd.getData('text/plain');
        if (text && text.trim().length > 0) {
            const o = fabricCanvas?.getActiveObject();
            if (o && (o.type === 'i-text' || o.type === 'textbox') && o.isEditing) {
                // let default behavior
            } else {
                e.preventDefault();
                const t = new fabric.Textbox(text, { 
                    left: fabricCanvas.width/2, top: fabricCanvas.height/2, originX:'center', originY:'center',
                    width: Math.min(fabricCanvas.width * 0.8, 600), splitByGrapheme: false,
                    fontSize: 32, fontFamily:'Inter, sans-serif', fill: '#000000', 
                    fontWeight: 'normal'
                });
                t.setControlsVisibility({ mt: false, mb: false, tr: false, tl: false, br: false, bl: false });
                fabricCanvas.add(t); fabricCanvas.setActiveObject(t); fabricCanvas.renderAll(); paintSaveHist();
            }
        }
    }
});

// Sidebar UI Logic
function paintSwitchTab(tabId) {
    document.querySelectorAll('.paint-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.paint-tab-content').forEach(content => content.classList.remove('active'));
    const btn = document.getElementById('paint-tab-btn-' + tabId);
    const content = document.getElementById('paint-tab-' + tabId);
    if(btn) btn.classList.add('active');
    if(content) content.classList.add('active');
}

// Shapes Add Logic
function paintSetTool(tool) {
    paintCurrentTool = tool;
    document.querySelectorAll('.paint-tool-btn[data-tool]').forEach(b => b.classList.remove('active'));
    const btn = document.querySelector(`.paint-tool-btn[data-tool="${tool}"]`);
    if(btn) btn.classList.add('active');
    
    if (fabricCanvas) {
        if (tool === 'select') {
            fabricCanvas.isDrawingMode = false;
            fabricCanvas.selection = true;
            fabricCanvas.forEachObject(o => o.selectable = true);
            fabricCanvas.defaultCursor = 'default';
        } else {
            fabricCanvas.isDrawingMode = false;
            fabricCanvas.selection = false;
            fabricCanvas.forEachObject(o => o.selectable = false);
            fabricCanvas.discardActiveObject();
            fabricCanvas.defaultCursor = 'crosshair';
            fabricCanvas.renderAll();
            
            // To ensure color and stroke are updated from inputs (if they exist)
            // They are fetched at paintMD time, so we just set the tool.
        }
    }
}

// Drag & Drop Logic
function paintDragStart(event, type) {
    event.dataTransfer.setData('text/plain', type);
}

// Canvas container event listeners for dropping
document.addEventListener('DOMContentLoaded', () => {
    const wrap = document.getElementById('paint-canvas-wrap');
    if (!wrap) return;
    
    wrap.addEventListener('dragover', e => {
        e.preventDefault();
    });
    
    wrap.addEventListener('drop', async e => {
        e.preventDefault();
        
        const rect = wrap.getBoundingClientRect();
        
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            let file = e.dataTransfer.files[0];
            if (file.type.includes('image/')) {
                file = await paintCompressImage(file);
                const fd = new FormData();
                fd.append('image', file);
                const monthIdInput = document.querySelector('input[name="month_id"]');
                if (monthIdInput) fd.append('month_id', monthIdInput.value);
                fd.append('post_type', 'Referencia Visual');
                
                paintShowLoader('Subiendo imagen...');
                try {
                    const res = await (await fetch('modules/month_board/ajax_upload_reference.php', { method:'POST', body:fd })).json();
                    if (res.success) {
                        paintShowLoader('Renderizando imagen...');
                        fabric.Image.fromURL(res.url, img => {
                            const pointerObj = fabricCanvas.restorePointerVpt({x: e.clientX - rect.left, y: e.clientY - rect.top});
                            const s = Math.min((fabricCanvas.width*0.8)/img.width, (fabricCanvas.height*0.8)/img.height, 1);
                            img.set({ left: pointerObj.x, top: pointerObj.y, originX:'center', originY:'center', scaleX:s, scaleY:s, crossOrigin: 'anonymous' });
                            fabricCanvas.add(img); fabricCanvas.setActiveObject(img); fabricCanvas.renderAll(); paintSaveHist();
                            paintHideLoader();
                        }, { crossOrigin: 'anonymous' });
                    } else {
                        paintHideLoader(); showToast(res.error || 'Error al subir imagen');
                    }
                } catch(err) { paintHideLoader(); console.error(err); showToast('Error de red al subir imagen'); }
                return;
            }
        }

        const type = e.dataTransfer.getData('text/plain');
        if (!type || !fabricCanvas) return;
        
        // Calculate relative coordinates in the canvas view
        const pointerObj = fabricCanvas.restorePointerVpt({x: e.clientX - rect.left, y: e.clientY - rect.top});
        
        if (type === 'text') {
            const t = new fabric.IText('Título', { 
                left: pointerObj.x, top: pointerObj.y, fontSize: 48, fontWeight: 'bold', 
                fontFamily:'Inter, sans-serif', fill: '#000000', originX:'center', originY:'center',
                lockUniScaling: true
            });
            t.setControlsVisibility({ mt: false, mb: false, ml: false, mr: false });
            fabricCanvas.add(t); fabricCanvas.setActiveObject(t); t.enterEditing(); t.selectAll();
        } else if (type === 'paragraph') {
            const t = new fabric.Textbox('Escribe tu párrafo aquí...', { 
                left: pointerObj.x, top: pointerObj.y, width: 300, fontSize: 24, 
                fontFamily:'Inter, sans-serif', fill: '#000000', originX:'center', originY:'center' 
            });
            t.setControlsVisibility({ mt: false, mb: false, tr: false, tl: false, br: false, bl: false });
            fabricCanvas.add(t); fabricCanvas.setActiveObject(t); t.enterEditing(); t.selectAll();
        } else if (type === 'shape') {
            const s = new fabric.Rect({ 
                left: pointerObj.x, top: pointerObj.y, width: 100, height: 100, 
                fill: '#3b82f6', originX:'center', originY:'center' 
            });
            s.customShapeType = 'rect';
            fabricCanvas.add(s); fabricCanvas.setActiveObject(s);
        } else if (type === 'note') {
            const t = new fabric.Textbox(' ', { 
                left: pointerObj.x, top: pointerObj.y, width: 220, fontSize: 16, 
                fontFamily:'Inter, sans-serif', fill: '#1a1a1a', backgroundColor: '#ffedd5',
                padding: 24, originX:'center', originY:'center',
                textAlign: 'left', splitByGrapheme: false, noteShadow: true,
                objectCaching: false
            });
            t.setControlsVisibility({ mt: false, mb: false, tr: false, tl: false, br: false, bl: false });
            t.customType = 'note';
            fabricCanvas.add(t); fabricCanvas.setActiveObject(t);
        } else if (type.startsWith('sticker-')) {
            const group = paintCreateStickerGroup(type, pointerObj);
            if (group) {
                fabricCanvas.add(group);
                fabricCanvas.setActiveObject(group);
            }
        }
        fabricCanvas.renderAll();
        paintSaveHist();
    });
});

async function paintSave() {
    if (!fabricCanvas) return;
    const btn = document.getElementById('paint-save-btn');
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...'; btn.disabled = true;
    
    try {
        fabricCanvas.discardActiveObject(); fabricCanvas.renderAll();
        // Temporarily reset viewport so positions are saved as absolute coordinates
        const currentVpt = fabricCanvas.viewportTransform.slice();
        fabricCanvas.setViewportTransform([1, 0, 0, 1, 0, 0]);
        paintPages[paintCurrentPage] = getPaintCanvasJSON();
        // Restore original viewport
        fabricCanvas.setViewportTransform(currentVpt);
        
        let uploadedUrls = [];
        const is_carousel = paintPages.length > 1;
        const postName = document.getElementById('post-concept')?.value || 'Post_Unnamed';
        let folder_id = '';
        const tempCanvas = new fabric.StaticCanvas(null, { enableRetinaScaling: false });

        const oldVal = document.getElementById('post-reference-link').value;
        let oldUrls = [];
        try { const a = JSON.parse(oldVal); oldUrls = Array.isArray(a) ? a : (oldVal ? [oldVal] : []); } catch(e) { oldUrls = oldVal ? [oldVal] : []; }

        for (let i = 0; i < paintPages.length; i++) {
            btn.innerHTML = `<i class="ph ph-spinner ph-spin"></i> Guardando ${i+1}/${paintPages.length}...`;
            
            const dataStr = paintPages[i];
            let cw = 1080, ch = 1080;
            try { const p = JSON.parse(dataStr); cw = p.width || 1080; ch = p.height || 1080; } catch(e){}
            tempCanvas.setWidth(cw); tempCanvas.setHeight(ch);
            
            // Parse JSON and inject identity viewport before loading
            let pageData;
            try { pageData = JSON.parse(dataStr); } catch(e) { pageData = {}; }
            // Force identity viewport in the data so no offset is applied
            pageData.viewportTransform = [1, 0, 0, 1, 0, 0];
            const cleanDataStr = JSON.stringify(pageData);
            
            await new Promise(resolve => {
                tempCanvas.loadFromJSON(cleanDataStr, () => {
                    tempCanvas.setViewportTransform([1, 0, 0, 1, 0, 0]);
                    tempCanvas.setWidth(cw);
                    tempCanvas.setHeight(ch);
                    tempCanvas.renderAll();
                    resolve();
                });
            });

            const dataUrl = tempCanvas.toDataURL({ format:'png', quality:1, multiplier:1 });

            const blob = await (await fetch(dataUrl)).blob();
            const file = new File([blob], 'paint_ref_'+Date.now()+'.png', { type:'image/png' });
            const fd = new FormData();
            fd.append('image', file);
            fd.append('month_id', document.querySelector('input[name="month_id"]').value);
            fd.append('post_type', 'Referencia Visual');
            fd.append('is_carousel', is_carousel);
            fd.append('post_name', postName);
            fd.append('parent_folder_id', folder_id);
            if (oldUrls[i]) fd.append('old_url', oldUrls[i]);

            const res = await (await fetch('modules/month_board/ajax_upload_reference.php', { method:'POST', body:fd })).json();
            if (res.success) {
                uploadedUrls.push(res.url);
                if (res.folder_id) folder_id = res.folder_id;
            } else {
                throw new Error(res.error || 'Error al guardar la página ' + (i+1));
            }
        }
        
        document.getElementById('post-reference-link').value = JSON.stringify(uploadedUrls);
        document.getElementById('post-paint-data').value = JSON.stringify(paintPages);
        const r = document.getElementById('pt_ref'); if (r) { r.checked=true; r.dispatchEvent(new Event('change')); }
        updateVideoPreview(); markDirty(); closePaintEditor(); savePost(true);
        
    } catch(err) {
        showToast(err.message);
    } finally {
        btn.innerHTML = '<i class="ph ph-floppy-disk"></i> Guardar como Referencia';
        btn.disabled = false;
    }
}

async function compressImage(file) {
    if (!file.type.startsWith('image/')) return file;
    if (file.type === 'image/gif') return file;

    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = function(event) {
            const img = new Image();
            img.src = event.target.result;
            img.onload = function() {
                const canvas = document.createElement('canvas');
                const MAX_WIDTH = 1920;
                const MAX_HEIGHT = 1920;
                let width = img.width;
                let height = img.height;

                if (width > height) {
                    if (width > MAX_WIDTH) {
                        height *= MAX_WIDTH / width;
                        width = MAX_WIDTH;
                    }
                } else {
                    if (height > MAX_HEIGHT) {
                        width *= MAX_HEIGHT / height;
                        height = MAX_HEIGHT;
                    }
                }
                
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob(function(blob) {
                    const compressedFile = new File([blob], file.name, {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                    resolve(compressedFile);
                }, 'image/jpeg', 0.85);
            }
        };
    });
}

async function callGemini(action) {
    const editor = document.getElementById('post-copy-editable');
    let text = editor.innerText.trim();
    let promptStr = '';
    let imgUrl = '';
    let concept = document.getElementById('post-concept') ? document.getElementById('post-concept').value.trim() : '';
    let pillar = document.querySelector('input[name="content_pillar"]:checked') ? document.querySelector('input[name="content_pillar"]:checked').value : '';
    let platforms = Array.from(document.querySelectorAll('input[name="platform[]"]:checked')).map(cb => cb.value);

    if (action === 'generar_desde_imagen') {
        // Buscar la imagen en la pestaña "Post Terminado"
        const postImgVal = document.getElementById('post-image-link') ? document.getElementById('post-image-link').value : '';
        let list = [];
        try {
            list = JSON.parse(postImgVal);
            if (!Array.isArray(list)) list = postImgVal ? [postImgVal] : [];
        } catch(e) {
            list = postImgVal ? [postImgVal] : [];
        }
        
        list = list.filter(u => typeof u === 'string' && u.trim() !== '');

        if (list.length === 0) {
            // Verificar si hay en referencia visual como alternativa
            const refVal = document.getElementById('post-reference-link') ? document.getElementById('post-reference-link').value : '';
            let refList = [];
            try {
                refList = JSON.parse(refVal);
                if (!Array.isArray(refList)) refList = refVal ? [refVal] : [];
            } catch(e) {
                refList = refVal ? [refVal] : [];
            }
            refList = refList.filter(u => typeof u === 'string' && u.trim() !== '');

            if (refList.length > 0) {
                if (confirm('No hay imagen subida en "Terminado", pero se encontró una en "Ref. Visual". ¿Deseas usar la imagen de referencia para generar el copy?')) {
                    imgUrl = refList[0];
                } else {
                    return;
                }
            } else {
                showToast('Sube primero la imagen en la pestaña "Terminado" para generar el copy.', 'warning');
                return;
            }
        } else {
            imgUrl = list[0];
        }
    } else if (action === 'generar') {
        promptStr = prompt('¿De qué trata la publicación? (Ej: Sorteo de fin de mes, Promoción 2x1...)');
        if (!promptStr) return;
    }

    let selText = window.getSelection().toString().trim();
    let isPartialSelection = false;
    let range = null;
    let placeholderNode = null;

    if (action !== 'generar_desde_imagen' && action !== 'generar') {
        if (selText.length > 0 && window.getSelection().rangeCount > 0 && editor.contains(window.getSelection().anchorNode)) {
            isPartialSelection = true;
            text = selText; // Only send the selected text to AI
            range = window.getSelection().getRangeAt(0);
            
            placeholderNode = document.createElement('span');
            placeholderNode.style.cssText = 'color:#10b981; background:rgba(16,185,129,0.15); border-radius:4px; padding:2px 6px; font-weight: 500; font-style: italic; display:inline-flex; align-items:center; gap:4px;';
            placeholderNode.innerHTML = '<i class="ph ph-spinner ph-spin"></i> IA pensando...';
            
            range.deleteContents();
            range.insertNode(placeholderNode);
        } else {
            if (!text || text === 'Escribe el texto de la publicación...') {
                showToast('El campo de texto está vacío. Selecciona un texto o escribe algo primero.', 'warning');
                return;
            }
        }
    }

    const originalHtml = isPartialSelection ? '' : editor.innerHTML;
    if (!isPartialSelection) {
        if (action === 'generar_desde_imagen') {
            editor.innerHTML = '<span style="color:#10b981; font-weight: 600; font-style: italic;"><i class="ph ph-spinner ph-spin"></i> Analizando la imagen terminada y redactando copy con emojis y hashtags...</span>';
        } else {
            editor.innerHTML = '<span style="color:#8b5cf6; font-weight: 500; font-style: italic;"><i class="ph ph-spinner ph-spin"></i> La IA de Gemini está pensando...</span>';
        }
    }

    try {
        const res = await fetch('ajax/gemini_generate.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: action,
                text: text,
                prompt: promptStr,
                image: imgUrl,
                concept: concept,
                pillar: pillar,
                platforms: platforms
            })
        });
        const data = await res.json();
        
        if (data.success) {
            const formatted = data.text.replace(/\n/g, '<br>');
            if (isPartialSelection && placeholderNode) {
                if (action === 'hashtags') {
                    placeholderNode.outerHTML = selText + ' <span style="color:#3b82f6; font-weight: 500;">' + formatted + '</span>';
                } else {
                    placeholderNode.outerHTML = formatted;
                }
            } else {
                if (action === 'hashtags') {
                    editor.innerHTML = originalHtml + '<br><br><span style="color:#3b82f6; font-weight: 500;">' + formatted + '</span>';
                } else {
                    editor.innerHTML = formatted;
                }
            }
            updateCopyPreview();
            markDirty();
            showToast('✨ Copy generado con IA exitosamente', 'success');
        } else {
            if (isPartialSelection && placeholderNode) {
                placeholderNode.outerHTML = selText;
            } else {
                editor.innerHTML = originalHtml;
            }
            showToast(data.error || 'Error al conectar con Gemini.', 'error');
        }
    } catch (e) {
        if (isPartialSelection && placeholderNode) {
            placeholderNode.outerHTML = selText;
        } else {
            editor.innerHTML = originalHtml;
        }
        showToast('Error de conexión con la IA.', 'error');
    }
}
</script>
<!-- Modal Previsualización Redes Sociales PRO -->
<div class="modal-overlay" id="socialPreviewModal" style="z-index: 1080; background: rgba(0,0,0,0.6);">
    <div class="modal-content" style="max-width: 1400px; width: 95vw; height: 90vh; display: flex; flex-direction: column; background: var(--bg-color, #18181b); color: var(--text-main); padding: 1.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        <div class="modal-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; flex-shrink: 0;">
            <h3 style="margin:0; font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem;"><i class="ph ph-rocket-launch"></i> Revisión antes de Publicar</h3>
            <button type="button" class="btn-icon" onclick="document.getElementById('socialPreviewModal').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        
        <div class="modal-body" style="padding-top: 1.5rem; flex: 1; overflow-y: auto; display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Controles Izquierda -->
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem; display: block;">Redes Seleccionadas</label>
                    <div id="sp-preview-platforms" style="display:flex; gap: 0.5rem; flex-wrap: wrap;"></div>
                </div>
                
                <div style="background: var(--bg-sidebar); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.5rem;">Copy de la publicación</label>
                    <textarea id="sp-copy-edit" rows="5" style="width: 100%; background: rgba(255,255,255,0.05); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 6px; padding: 0.5rem; font-family: inherit; font-size: 0.9rem; resize: none;" onkeyup="updateLivePreviewLimits()"></textarea>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                        <button type="button" onclick="optimizeCopyPreview()" style="background: linear-gradient(135deg, #8b5cf6, #d946ef); border: none; color: white; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                            <i class="ph ph-magic-wand"></i> Optimizar IA
                        </button>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                            <span id="sp-char-count">0</span>/2200 <span style="margin: 0 4px;">|</span>
                            <span id="sp-hash-count">0</span>/30 #
                        </div>
                    </div>
                </div>
                
                <div>
                    <label style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem; display: block;">Primer Comentario (Opcional, IG/FB)</label>
                    <textarea id="sp-first-comment" rows="2" placeholder="#Hashtags aquí..." style="width: 100%; background: rgba(255,255,255,0.05); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 6px; padding: 0.5rem; font-family: inherit; font-size: 0.9rem; resize: none;"></textarea>
                </div>
                
                <!-- Ajustes Avanzados -->
                <div style="border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
                    <button type="button" onclick="const b=document.getElementById('sp-advanced-body'); b.style.display = b.style.display==='none'?'block':'none';" style="width: 100%; background: rgba(255,255,255,0.02); color: var(--text-main); border: none; padding: 0.8rem 1rem; text-align: left; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="ph ph-sliders-horizontal"></i> Ajustes Avanzados de Publicación</span>
                        <i class="ph ph-caret-down"></i>
                    </button>
                    <div id="sp-advanced-body" style="display: none; padding: 1rem; background: var(--bg-sidebar); border-top: 1px solid var(--border-color);">
                        <div style="margin-bottom: 0.8rem;">
                            <label style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.3rem; display: block;"><i class="ph ph-map-pin"></i> Ubicación (Geotag)</label>
                            <input type="text" id="sp-meta-location" placeholder="Ej: Lima, Perú" style="width: 100%; background: rgba(255,255,255,0.05); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; padding: 0.4rem; font-family: inherit; font-size: 0.85rem;" onkeyup="changeMockup(document.querySelector('.sp-tab-btn.active') ? document.querySelector('.sp-tab-btn.active').innerText.replace(' ', '') === 'IG' ? 'Instagram' : (document.querySelector('.sp-tab-btn.active').innerText.replace(' ', '') === 'FB' ? 'Facebook' : 'TikTok') : 'Instagram')">
                        </div>
                        <div style="margin-bottom: 0.8rem;">
                            <label style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.3rem; display: block;"><i class="ph ph-user-plus"></i> Etiquetar Cuentas</label>
                            <input type="text" id="sp-meta-mentions" placeholder="Ej: @agencia, @socio" style="width: 100%; background: rgba(255,255,255,0.05); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; padding: 0.4rem; font-family: inherit; font-size: 0.85rem;" onkeyup="changeMockup(document.querySelector('.sp-tab-btn.active') ? document.querySelector('.sp-tab-btn.active').innerText.replace(' ', '') === 'IG' ? 'Instagram' : (document.querySelector('.sp-tab-btn.active').innerText.replace(' ', '') === 'FB' ? 'Facebook' : 'TikTok') : 'Instagram')">
                        </div>
                        <div>
                            <label style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.3rem; display: block;"><i class="ph ph-shopping-bag"></i> Etiquetar Productos (Shoppable)</label>
                            <input type="text" id="sp-meta-products" placeholder="ID o Nombre del producto" style="width: 100%; background: rgba(255,255,255,0.05); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; padding: 0.4rem; font-family: inherit; font-size: 0.85rem;" onkeyup="changeMockup(document.querySelector('.sp-tab-btn.active') ? document.querySelector('.sp-tab-btn.active').innerText.replace(' ', '') === 'IG' ? 'Instagram' : (document.querySelector('.sp-tab-btn.active').innerText.replace(' ', '') === 'FB' ? 'Facebook' : 'TikTok') : 'Instagram')">
                        </div>
                    </div>
                </div>
                
                <div style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2); padding: 1rem; border-radius: 8px;">
                    <strong><i class="ph ph-clock"></i> Programación:</strong> 
                    <input type="datetime-local" id="sp-preview-date-input" style="background: rgba(255,255,255,0.05); color: var(--text-main); border: 1px solid var(--border-color); padding: 0.3rem 0.6rem; border-radius: 4px; font-family: inherit; font-size: 0.9rem; margin-top: 0.5rem; outline: none; width: 100%;">
                </div>
            </div>
            
            <!-- Mockup Derecha -->
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #0c0c0c; border-radius: 20px; padding: 1rem; position: relative; border: 1px solid #333;">
                
                <!-- Botón de Grid -->
                <div style="position: absolute; top: 10px; left: 10px; z-index: 10;">
                    <button onclick="changeMockup('Grid')" id="sp-tab-Grid" style="background: rgba(0,0,0,0.5); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <i class="ph ph-squares-four"></i> Ver Grid
                    </button>
                </div>
                
                <!-- Selector de Mockup -->
                <div style="position: absolute; top: -15px; background: var(--bg-sidebar); border: 1px solid var(--border-color); border-radius: 20px; padding: 4px; display: flex; gap: 4px; z-index: 10;">
                    <button class="sp-tab-btn active" onclick="changeMockup('Instagram')" id="sp-tab-Instagram" style="background: #e1306c; color: white; border: none; padding: 4px 12px; border-radius: 16px; font-size: 0.8rem; cursor: pointer;"><i class="ph ph-instagram-logo"></i> IG</button>
                    <button class="sp-tab-btn" onclick="changeMockup('Facebook')" id="sp-tab-Facebook" style="background: transparent; color: var(--text-muted); border: none; padding: 4px 12px; border-radius: 16px; font-size: 0.8rem; cursor: pointer;"><i class="ph ph-facebook-logo"></i> FB</button>
                    <button class="sp-tab-btn" onclick="changeMockup('TikTok')" id="sp-tab-TikTok" style="background: transparent; color: var(--text-muted); border: none; padding: 4px 12px; border-radius: 16px; font-size: 0.8rem; cursor: pointer;"><i class="ph ph-tiktok-logo"></i> TK</button>
                    <button class="sp-tab-btn" onclick="changeMockup('Stories')" id="sp-tab-Stories" style="background: transparent; color: var(--text-muted); border: none; padding: 4px 12px; border-radius: 16px; font-size: 0.8rem; cursor: pointer;"><i class="ph ph-cards"></i> ST</button>
                </div>
                
                <!-- Toggle Safe Zones -->
                <div style="position: absolute; top: 10px; right: 10px; z-index: 10;">
                    <label style="display: flex; align-items: center; gap: 4px; font-size: 0.75rem; color: #fff; background: rgba(0,0,0,0.5); padding: 4px 8px; border-radius: 4px; cursor: pointer;">
                        <input type="checkbox" id="sp-safe-zone-toggle" onchange="toggleSafeZones()"> Zonas Seguras
                    </label>
                </div>
                
                <!-- Contenedor del Celular -->
                <div id="sp-phone-container" style="width: 260px; height: 500px; background: #fff; border-radius: 30px; border: 10px solid #222; overflow: hidden; position: relative; display: flex; flex-direction: column; box-shadow: inset 0 0 10px rgba(0,0,0,0.5);">
                    <div id="sp-safe-zone-overlay" style="display: none; position: absolute; inset: 0; pointer-events: none; z-index: 20;"></div>
                    <div id="sp-phone-screen" style="flex: 1; overflow-y: auto; background: #fff; color: #000; position: relative; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                        <!-- El interior del celular cambia según la red -->
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer" style="border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 1rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
            <div>
                <button type="button" id="btn-sp-edit-media" onclick="openMediaEditor()" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-main); padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; display: none; align-items: center; gap: 6px; font-weight: 600;">
                    <i class="ph ph-crop" id="btn-sp-edit-icon"></i> <span id="btn-sp-edit-media-text">Editar Multimedia</span>
                </button>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button type="button" onclick="document.getElementById('socialPreviewModal').classList.remove('active')" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-main); padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button type="button" id="btn-confirm-social-publish" onclick="confirmSocialPublish()" style="background: #6366f1; border: 1px solid #6366f1; color: white; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                    <i class="ph ph-check-circle"></i> Confirmar y Lanzar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editor Multimedia Interno -->
<div class="modal-overlay" id="sp-editor-modal" style="z-index: 1090; background: rgba(0,0,0,0.85); display: none;">
    <div class="modal-content" style="max-width: 900px; width: 95vw; height: 85vh; display: flex; flex-direction: column; background: #111; color: white; padding: 1rem; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.8);">
        <div class="modal-header" style="border-bottom: 1px solid #333; padding-bottom: 1rem; flex-shrink: 0; display: flex; justify-content: space-between;">
            <h3 style="margin:0; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;"><i class="ph ph-scissors"></i> <span id="sp-editor-title">Editor Multimedia</span></h3>
            <button type="button" class="btn-icon" onclick="closeMediaEditor()" style="color: white; background: transparent; border: none; font-size: 1.2rem; cursor: pointer;"><i class="ph ph-x"></i></button>
        </div>
        
        <div class="modal-body" style="flex: 1; display: flex; flex-direction: column; padding-top: 1rem; min-height: 0; overflow: hidden; position: relative;">
            <!-- Contenedor del Editor -->
            <div id="sp-editor-workspace" style="flex: 1; background: #000; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; position: relative;">
                <!-- Aquí va la imagen o el video -->
            </div>
            
            <!-- Controles Inferiores -->
            <div id="sp-editor-controls" style="margin-top: 1rem; flex-shrink: 0; background: #222; border-radius: 8px; padding: 10px; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                <!-- Controles dinámicos -->
            </div>
        </div>
        
        <div class="modal-footer" style="border-top: 1px solid #333; padding-top: 1rem; margin-top: 1rem; display: flex; justify-content: flex-end; gap: 1rem;">
            <button type="button" onclick="closeMediaEditor()" style="background: transparent; border: 1px solid #555; color: white; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;">Cancelar</button>
            <button type="button" onclick="saveMediaEditor()" id="btn-sp-editor-save" style="background: #e1306c; border: none; color: white; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; font-weight: 600;">
                <i class="ph ph-floppy-disk"></i> Aplicar Cambios
            </button>
        </div>
    </div>
</div>

<script>
let currentPreviewMedia = '';
let currentPreviewHasVideo = false;

function openSocialPreviewModal() {
    savePost(true); // Always save first
    
    // Gather data from the form
    const copyEditable = document.getElementById('post-copy-editable');
    let rawText = copyEditable ? copyEditable.innerText : (document.getElementById('post-copy') ? document.getElementById('post-copy').value : '');
    const postDate = document.getElementById('post-date') ? document.getElementById('post-date').value : '';
    
    // Gather selected platforms
    const platforms = [];
    document.querySelectorAll('input[name="platform[]"]:checked').forEach(el => platforms.push(el.value));
    
    if (platforms.length === 0) {
        alert("Selecciona al menos una red social.");
        return;
    }
    
    let imageLinks = [];
    let rawImageLink = document.getElementById('post-image-link') ? document.getElementById('post-image-link').value.trim() : '';
    try {
        if (rawImageLink.startsWith('[')) {
            const arr = JSON.parse(rawImageLink);
            if (Array.isArray(arr) && arr.length > 0) imageLinks = arr;
        } else if (rawImageLink) {
            imageLinks = [rawImageLink];
        }
    } catch(e) {}
    
    const driveImages = document.getElementById('post-drive') ? document.getElementById('post-drive').value.trim() : '';
    
    let firstLink = imageLinks.length > 0 ? imageLinks[0] : '';
    currentPreviewHasVideo = firstLink.includes('youtube.com') || firstLink.includes('youtu.be') || firstLink.includes('tiktok.com') || firstLink.includes('.mp4');
    let hasImage = imageLinks.length > 0 || driveImages !== '';
    
    if (platforms.includes('TikTok') && !currentPreviewHasVideo) {
        alert("TikTok requiere un enlace a un video (.mp4, youtube o tiktok). Por favor añade uno en el post.");
        return;
    }
    
    // Build preview
    const platformContainer = document.getElementById('sp-preview-platforms');
    platformContainer.innerHTML = '';
    platforms.forEach(p => {
        let icon = 'ph-check-circle';
        let color = '#fff';
        if(p==='Facebook') { icon='ph-facebook-logo'; color='#1877f2'; }
        else if(p==='Instagram') { icon='ph-instagram-logo'; color='#e1306c'; }
        else if(p==='TikTok') { icon='ph-tiktok-logo'; color='#000'; }
        
        platformContainer.innerHTML += `<span style="background: ${color}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; display:inline-flex; align-items:center; gap:4px;"><i class="ph ${icon}"></i> ${p}</span>`;
    });
    
    // Set copy
    document.getElementById('sp-copy-edit').value = rawText || "";
    updateLivePreviewLimits();
    
    // Set media (Carousel support)
    currentPreviewMedia = '';
    if (currentPreviewHasVideo) {
        currentPreviewMedia = `<div style="background:#111; color:white; height:100%; display:flex; flex-direction:column; justify-content:center; align-items:center;"><i class="ph ph-video-camera" style="font-size: 3rem;"></i><span style="font-size:0.8rem; margin-top:1rem;">Video Adjunto</span></div>`;
    } else if (imageLinks.length > 0) {
        if (imageLinks.length === 1) {
            currentPreviewMedia = `<img src="${imageLinks[0]}" style="width:100%; height:100%; object-fit:contain;">`;
        } else {
            let slides = '';
            imageLinks.forEach(link => {
                slides += `<img src="${link}" style="width:100%; height:100%; object-fit:contain; flex-shrink: 0; scroll-snap-align: start;">`;
            });
            let dots = imageLinks.map((_, i) => `<div style="width:6px; height:6px; border-radius:50%; background: ${i===0?'#0095f6':'rgba(255,255,255,0.5)'}; box-shadow:0 1px 2px rgba(0,0,0,0.5);"></div>`).join('');
            currentPreviewMedia = `<div style="display: flex; overflow-x: auto; scroll-snap-type: x mandatory; width: 100%; height: 100%; scrollbar-width: none;">${slides}</div>
            <div style="position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%); display: flex; gap: 4px; z-index: 5;">${dots}</div>
            <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; border-radius: 12px; padding: 2px 6px; font-size: 0.7rem; z-index: 5;">1/${imageLinks.length}</div>`;
        }
    } else if (driveImages) {
        let imgUrl = driveImages.split('||')[0];
        let isDrive = imgUrl.includes('drive.google.com');
        if (isDrive) {
            const driveMatch = imgUrl.match(/drive\.google\.com\/(?:file\/d\/([^\/\?\&]+)|open\?id=([^\&]+))/);
            if (driveMatch) {
                const fileId = driveMatch[1] || driveMatch[2];
                currentPreviewMedia = `<iframe width="100%" height="100%" src="https://drive.google.com/file/d/${fileId}/preview" frameborder="0" allowfullscreen style="border:none;"></iframe>`;
            } else {
                currentPreviewMedia = `<img src="${imgUrl}" style="width:100%; height:100%; object-fit:contain;">`;
            }
        } else {
            currentPreviewMedia = `<img src="${imgUrl}" style="width:100%; height:100%; object-fit:contain;">`;
        }
    } else {
        currentPreviewMedia = `<div style="background:#e2e8f0; color:#64748b; height:100%; display:flex; align-items:center; justify-content:center;">Sin multimedia</div>`;
    }
    
    // Set Date
    const dateInput = document.getElementById('sp-preview-date-input');
    if (postDate) {
        dateInput.value = postDate;
    } else {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        dateInput.value = now.toISOString().slice(0, 16);
    }
    
    // Toggle Editor button
    const editBtn = document.getElementById('btn-sp-edit-media');
    const editIcon = document.getElementById('btn-sp-edit-icon');
    const editText = document.getElementById('btn-sp-edit-media-text');
    if (currentPreviewHasVideo && !firstLink.includes('youtube') && !firstLink.includes('tiktok')) {
        editBtn.style.display = 'flex';
        editIcon.className = 'ph ph-image-square';
        editText.innerText = 'Extraer Portada';
    } else if (hasImage && !driveImages && !currentPreviewHasVideo) {
        editBtn.style.display = 'flex';
        editIcon.className = 'ph ph-crop';
        editText.innerText = 'Recortar Imagen';
    } else {
        editBtn.style.display = 'none';
    }
    
    // Init Mockup
    let defaultPlatform = platforms.includes('Instagram') ? 'Instagram' : platforms[0];
    changeMockup(defaultPlatform);
    
    document.getElementById('socialPreviewModal').classList.add('active');
}

function updateLivePreviewLimits() {
    const text = document.getElementById('sp-copy-edit').value;
    const chars = text.length;
    const hashes = (text.match(/#/g) || []).length;
    
    const charEl = document.getElementById('sp-char-count');
    charEl.innerText = chars;
    charEl.style.color = chars > 2200 ? '#ef4444' : 'inherit';
    
    const hashEl = document.getElementById('sp-hash-count');
    hashEl.innerText = hashes;
    hashEl.style.color = hashes > 30 ? '#ef4444' : 'inherit';
    
    const mockupText = document.getElementById('sp-mockup-text');
    if (mockupText) mockupText.innerText = text;
}

async function optimizeCopyPreview() {
    const text = document.getElementById('sp-copy-edit').value;
    if (!text) return;
    
    const btn = document.querySelector('button[onclick="optimizeCopyPreview()"]');
    const ogHtml = btn.innerHTML;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Optimizando...';
    btn.disabled = true;
    
    try {
        const res = await fetch('ajax/gemini_generate.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'mejorar', text: text, prompt: 'Optimiza el texto, añade emojis y espaciados perfectos.' })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('sp-copy-edit').value = data.text;
            updateLivePreviewLimits();
        } else {
            alert('Error al optimizar.');
        }
    } catch(e) {
        alert('Error de red al optimizar.');
    }
    btn.innerHTML = ogHtml;
    btn.disabled = false;
}

function toggleSafeZones() {
    const isChecked = document.getElementById('sp-safe-zone-toggle').checked;
    const overlay = document.getElementById('sp-safe-zone-overlay');
    if (isChecked) {
        overlay.style.display = 'block';
        overlay.innerHTML = `
            <div style="position:absolute; right:0; bottom:80px; width:60px; height:250px; background:rgba(255,0,0,0.25); border:1px dashed red;"></div>
            <div style="position:absolute; left:0; bottom:0; width:100%; height:100px; background:rgba(255,0,0,0.25); border:1px dashed red;"></div>
            <div style="position:absolute; left:0; top:0; width:100%; height:50px; background:rgba(255,0,0,0.25); border:1px dashed red;"></div>
            <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:red; font-weight:bold; text-shadow:0 0 5px white; opacity:0.6; transform: rotate(-45deg); font-size:1.5rem;">ZONA SEGURA</div>
        `;
    } else {
        overlay.style.display = 'none';
        overlay.innerHTML = '';
    }
}

function changeMockup(platform) {
    document.querySelectorAll('.sp-tab-btn').forEach(btn => {
        btn.style.background = 'transparent';
        btn.style.color = 'var(--text-muted)';
    });
    const gridBtn = document.getElementById('sp-tab-Grid');
    if(gridBtn) { gridBtn.style.background = 'rgba(0,0,0,0.5)'; gridBtn.style.color = 'white'; }
    
    const activeBtn = document.getElementById('sp-tab-' + platform);
    if (activeBtn && platform !== 'Grid') {
        if(platform==='Instagram') { activeBtn.style.background = '#e1306c'; activeBtn.style.color = '#fff'; }
        else if(platform==='Facebook') { activeBtn.style.background = '#1877f2'; activeBtn.style.color = '#fff'; }
        else if(platform==='TikTok') { activeBtn.style.background = '#000'; activeBtn.style.color = '#fff'; }
        else if(platform==='Stories') { activeBtn.style.background = 'linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%)'; activeBtn.style.color = '#fff'; }
    } else if (platform === 'Grid' && gridBtn) {
        gridBtn.style.background = '#e1306c';
    }
    
    const screen = document.getElementById('sp-phone-screen');
    const text = document.getElementById('sp-copy-edit').value;
    
    const loc = document.getElementById('sp-meta-location') ? document.getElementById('sp-meta-location').value.trim() : '';
    const locHtml = loc ? `<div style="font-size:0.7rem; color:#666; font-weight:normal; margin-top:2px;">${loc}</div>` : '';
    
    const mentions = document.getElementById('sp-meta-mentions') ? document.getElementById('sp-meta-mentions').value.trim() : '';
    const products = document.getElementById('sp-meta-products') ? document.getElementById('sp-meta-products').value.trim() : '';
    const tagOverlay = (mentions || products) && !currentPreviewHasVideo ? `<div style="position:absolute; bottom:15px; left:15px; background:rgba(0,0,0,0.7); color:white; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; z-index:10; box-shadow:0 2px 4px rgba(0,0,0,0.5); font-size:0.8rem;"><i class="ph ph-${products ? 'shopping-bag' : 'user'}"></i></div>` : '';
    
    if (platform === 'Instagram') {
        screen.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 30px; height: 30px; border-radius: 50%; background: #ddd;"></div>
                    <div style="font-weight: 600; font-size: 0.85rem; line-height: 1.1;">
                        marca_cliente
                        ${locHtml}
                    </div>
                </div>
                <i class="ph ph-dots-three" style="font-size: 1.2rem;"></i>
            </div>
            <div style="width: 100%; aspect-ratio: 4/5; background: #eee; position:relative; overflow:hidden;">
                ${currentPreviewMedia}
                ${tagOverlay}
            </div>
            <div style="padding: 10px;">
                <div style="display: flex; gap: 12px; margin-bottom: 8px;">
                    <i class="ph ph-heart" style="font-size: 1.3rem;"></i>
                    <i class="ph ph-chat-circle" style="font-size: 1.3rem;"></i>
                    <i class="ph ph-paper-plane-tilt" style="font-size: 1.3rem;"></i>
                </div>
                <span style="font-weight: 600; font-size: 0.85rem;">124 Me gusta</span>
                <div style="font-size: 0.85rem; margin-top: 4px; line-height: 1.4;">
                    <span style="font-weight: 600;">marca_cliente</span> <span id="sp-mockup-text" style="white-space: pre-wrap;">${text}</span>
                </div>
            </div>
        `;
    } else if (platform === 'Facebook') {
        screen.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 36px; height: 36px; border-radius: 50%; background: #ddd;"></div>
                    <div>
                        <div style="font-weight: 600; font-size: 0.9rem; line-height: 1.1;">
                            Marca Cliente ${loc ? `<span style="font-weight:normal; color:#65676B;"> está en </span>${loc}` : ''}
                        </div>
                        <div style="font-size: 0.75rem; color: #65676B;">Justo ahora · <i class="ph ph-globe" style="vertical-align: middle;"></i></div>
                    </div>
                </div>
                <i class="ph ph-dots-three" style="font-size: 1.2rem; color: #65676B;"></i>
            </div>
            <div style="padding: 0 12px 12px 12px; font-size: 0.9rem; white-space: pre-wrap; line-height: 1.4;" id="sp-mockup-text">${text}</div>
            <div style="width: 100%; aspect-ratio: 1/1; background: #eee; position:relative; overflow:hidden;">
                ${currentPreviewMedia}
                ${tagOverlay}
            </div>
            <div style="padding: 12px; border-top: 1px solid #ddd; display: flex; justify-content: space-around; color: #65676B;">
                <div style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600;"><i class="ph ph-thumbs-up" style="font-size: 1.2rem;"></i> Me gusta</div>
                <div style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600;"><i class="ph ph-chat-circle" style="font-size: 1.2rem;"></i> Comentar</div>
            </div>
        `;
    } else if (platform === 'TikTok') {
        screen.innerHTML = `
            <div style="position: absolute; inset: 0; background: #000;">
                ${currentPreviewMedia}
            </div>
            <div style="position: absolute; right: 8px; bottom: 80px; display: flex; flex-direction: column; gap: 16px; align-items: center; color: white; text-shadow: 0 1px 2px rgba(0,0,0,0.5);">
                <div style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid white; background: #333; position: relative;">
                    <div style="position: absolute; bottom: -6px; left: 50%; transform: translateX(-50%); background: #fe2c55; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">+</div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                    <i class="ph ph-heart" style="font-size: 2rem;"></i>
                    <span style="font-size: 0.75rem; font-weight: 600;">12K</span>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                    <i class="ph ph-chat-teardrop-dots" style="font-size: 2rem;"></i>
                    <span style="font-size: 0.75rem; font-weight: 600;">103</span>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                    <i class="ph ph-bookmark-simple" style="font-size: 2rem;"></i>
                    <span style="font-size: 0.75rem; font-weight: 600;">854</span>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                    <i class="ph ph-share-fat" style="font-size: 2rem;"></i>
                    <span style="font-size: 0.75rem; font-weight: 600;">Share</span>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 50%; border: 10px solid #222; background: #fff;"></div>
            </div>
            <div style="position: absolute; left: 12px; bottom: 20px; right: 65px; color: white; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">
                <div style="font-weight: 600; font-size: 0.95rem; margin-bottom: 4px;">@marca_cliente</div>
                <div id="sp-mockup-text" style="font-size: 0.85rem; line-height: 1.3; white-space: pre-wrap; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;">${text}</div>
                <div style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; margin-top: 8px;">
                    <i class="ph ph-music-note"></i> sonido original - Marca Cliente
                </div>
            </div>
        `;
    } else if (platform === 'Stories') {
        screen.innerHTML = `
            <div style="position: absolute; inset: 0; background: #222; overflow: hidden;">
                ${currentPreviewMedia}
            </div>
            <div style="position: absolute; top: 0; left: 0; right: 0; padding: 10px; background: linear-gradient(to bottom, rgba(0,0,0,0.5), transparent); z-index: 10;">
                <div style="display: flex; gap: 4px; margin-bottom: 10px;">
                    <div style="flex: 1; height: 2px; background: rgba(255,255,255,0.5); border-radius: 2px;"></div>
                    <div style="flex: 1; height: 2px; background: rgba(255,255,255,0.5); border-radius: 2px;"></div>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; color: white;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: #ddd; border: 2px solid white;"></div>
                        <span style="font-weight: 600; font-size: 0.85rem; text-shadow: 0 1px 2px rgba(0,0,0,0.5);">marca_cliente</span>
                        <span style="font-size: 0.75rem; opacity: 0.8; text-shadow: 0 1px 2px rgba(0,0,0,0.5);">2h</span>
                    </div>
                    <i class="ph ph-dots-three" style="font-size: 1.2rem; text-shadow: 0 1px 2px rgba(0,0,0,0.5);"></i>
                </div>
            </div>
            <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 5; pointer-events: none;">
                ${text ? `<div style="background: rgba(0,0,0,0.7); color: white; padding: 12px 18px; border-radius: 12px; font-weight: bold; text-align: center; max-width: 80%; backdrop-filter: blur(8px); font-size: 0.95rem; box-shadow: 0 4px 15px rgba(0,0,0,0.3);"><i class="ph ph-link" style="margin-right:6px; color:#38bdf8;"></i>${text.substring(0, 40)}${text.length > 40 ? '...' : ''}</div>` : ''}
            </div>
            <div style="position: absolute; bottom: 0; left: 0; right: 0; padding: 15px; display: flex; align-items: center; gap: 10px; z-index: 10; background: linear-gradient(to top, rgba(0,0,0,0.5), transparent);">
                <div style="flex: 1; border: 1px solid rgba(255,255,255,0.6); border-radius: 20px; padding: 8px 15px; color: white; font-size: 0.85rem; background: rgba(0,0,0,0.2);">Enviar mensaje...</div>
                <i class="ph ph-heart" style="color: white; font-size: 1.5rem; text-shadow: 0 1px 2px rgba(0,0,0,0.5);"></i>
                <i class="ph ph-paper-plane-tilt" style="color: white; font-size: 1.5rem; text-shadow: 0 1px 2px rgba(0,0,0,0.5);"></i>
            </div>
        `;
    } else if (platform === 'Grid') {
        let gridItems = '';
        for(let i=0; i<8; i++) {
            gridItems += `<div style="aspect-ratio: 1/1; background: #e0e0e0; border: 1px solid #fff;"></div>`;
        }
        screen.innerHTML = `
            <div style="padding: 15px 10px; display: flex; align-items: center; gap: 15px; border-bottom: 1px solid #eee; background:#fff;">
                <div style="width: 70px; height: 70px; border-radius: 50%; background: #ddd; border: 2px solid #e1306c; padding: 2px; background-clip: content-box;"></div>
                <div style="flex: 1; color:#000;">
                    <div style="font-weight: bold; font-size: 1.1rem; margin-bottom: 8px;">marca_cliente</div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem;">
                        <div style="text-align: center;"><strong style="display:block; font-size:1rem;">150</strong>posts</div>
                        <div style="text-align: center;"><strong style="display:block; font-size:1rem;">12K</strong>followers</div>
                        <div style="text-align: center;"><strong style="display:block; font-size:1rem;">300</strong>following</div>
                    </div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); background: #fff;">
                <div style="aspect-ratio: 1/1; background: #000; position: relative; border: 1px solid #fff; overflow:hidden;">
                    ${currentPreviewMedia}
                    <div style="position:absolute; top:4px; right:4px; color:white; background:rgba(0,0,0,0.6); border-radius:4px; padding:2px 4px; font-size:0.65rem; font-weight:bold; letter-spacing:1px; z-index:10;">NEW</div>
                </div>
                ${gridItems}
            </div>
        `;
    }
}

async function confirmSocialPublish() {
    const btn = document.getElementById('btn-confirm-social-publish');
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Procesando...';
    btn.disabled = true;
    
    const finalDate = document.getElementById('sp-preview-date-input').value;
    const finalCopy = document.getElementById('sp-copy-edit').value;
    const firstComment = document.getElementById('sp-first-comment').value;
    
    // Sync back to main form date
    if (document.getElementById('post-date')) {
        document.getElementById('post-date').value = finalDate;
    }
    // Sync back to editor
    const ce = document.getElementById('post-copy-editable');
    if (ce) {
        ce.innerHTML = finalCopy.replace(/\n/g, '<br>');
    } else if (document.getElementById('post-copy')) {
        document.getElementById('post-copy').value = finalCopy;
    }
    
    await savePost(true); // Save DB state
    
    let actionType = 'publish_now';
    if (finalDate) {
        const d = new Date(finalDate);
        const now = new Date();
        if (d > now) actionType = 'schedule';
    }
    
    const postId = document.getElementById('post-id') ? document.getElementById('post-id').value : 0;
    
    try {
        const formData = new URLSearchParams();
        formData.append('post_id', postId);
        formData.append('action_type', actionType);
        formData.append('first_comment', firstComment); // Pasado al backend

        const response = await fetch('ajax/social_publish.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        });
        
        const res = await response.json();
        
        btn.innerHTML = '<i class="ph ph-check-circle"></i> Confirmar y Lanzar';
        btn.disabled = false;
        
        if (res.success) {
            document.getElementById('socialPreviewModal').classList.remove('active');
            showToast(res.message || 'Procesado con éxito en redes sociales.', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            alert('Error: ' + (res.error || 'Desconocido'));
        }
    } catch(err) {
        btn.innerHTML = '<i class="ph ph-check-circle"></i> Confirmar y Lanzar';
        btn.disabled = false;
        alert('Error de conexión con el servidor.');
    }
}

let spCropper = null;
let spVideoEl = null;
let spFinalBase64 = null;

function openMediaEditor() {
    const modal = document.getElementById('sp-editor-modal');
    const workspace = document.getElementById('sp-editor-workspace');
    const controls = document.getElementById('sp-editor-controls');
    const title = document.getElementById('sp-editor-title');
    
    workspace.innerHTML = '';
    controls.innerHTML = '';
    if(spCropper) { spCropper.destroy(); spCropper = null; }
    
    let imageLinks = [];
    let rawImageLink = document.getElementById('post-image-link') ? document.getElementById('post-image-link').value.trim() : '';
    try {
        if (rawImageLink.startsWith('[')) {
            const arr = JSON.parse(rawImageLink);
            if (Array.isArray(arr) && arr.length > 0) imageLinks = arr;
        } else if (rawImageLink) {
            imageLinks = [rawImageLink];
        }
    } catch(e) {}
    
    let firstLink = imageLinks.length > 0 ? imageLinks[0] : '';
    let isVideo = firstLink.includes('youtube') || firstLink.includes('tiktok') || firstLink.includes('.mp4');
    
    if (isVideo) {
        title.innerText = 'Extraer Portada de Video';
        workspace.innerHTML = `
            <video id="sp-video-player" src="${firstLink}" style="max-width:100%; max-height:100%;" crossorigin="anonymous"></video>
            <canvas id="sp-video-canvas" style="display:none;"></canvas>
            <div id="sp-video-preview-overlay" style="position:absolute; inset:0; background:#000; display:none; align-items:center; justify-content:center;">
                <img id="sp-video-snapshot" style="max-width:100%; max-height:100%;">
            </div>
        `;
        controls.innerHTML = `
            <div style="width:100%; padding:0 20px;">
                <input type="range" id="sp-video-timeline" min="0" max="100" value="0" style="width:100%;" disabled>
            </div>
            <div style="display:flex; gap:10px; margin-top:10px; flex-wrap: wrap; justify-content: center;">
                <button type="button" onclick="spToggleVideoPlay()" id="sp-btn-play" style="background:#333; color:white; border:1px solid #555; padding:6px 12px; border-radius:4px; cursor:pointer; display:flex; align-items:center; gap:4px;"><i class="ph ph-play"></i> Reproducir</button>
                <button type="button" onclick="spCaptureVideoFrame()" style="background:#e1306c; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; display:flex; align-items:center; gap:4px;"><i class="ph ph-camera"></i> Capturar Frame</button>
            </div>
        `;
        
        spVideoEl = document.getElementById('sp-video-player');
        const timeline = document.getElementById('sp-video-timeline');
        spVideoEl.onloadedmetadata = () => {
            timeline.disabled = false;
            timeline.max = spVideoEl.duration;
        };
        spVideoEl.ontimeupdate = () => {
            if(!timeline.matches(':active')) {
                timeline.value = spVideoEl.currentTime;
            }
        };
        timeline.oninput = (e) => {
            spVideoEl.currentTime = e.target.value;
            spVideoEl.pause();
            document.getElementById('sp-btn-play').innerHTML = '<i class="ph ph-play"></i> Reproducir';
        };
        
    } else {
        title.innerText = 'Recortar Imagen';
        workspace.innerHTML = `<img id="sp-crop-image" src="${firstLink}" style="max-width:100%; max-height:100%; display:block;" crossorigin="anonymous">`;
        controls.innerHTML = `
            <button type="button" onclick="spCropper.setAspectRatio(1)" style="background:#333; color:white; border:1px solid #555; padding:6px 12px; border-radius:4px; cursor:pointer;">1:1</button>
            <button type="button" onclick="spCropper.setAspectRatio(4/5)" style="background:#333; color:white; border:1px solid #555; padding:6px 12px; border-radius:4px; cursor:pointer;">4:5</button>
            <button type="button" onclick="spCropper.setAspectRatio(9/16)" style="background:#333; color:white; border:1px solid #555; padding:6px 12px; border-radius:4px; cursor:pointer;">9:16</button>
            <button type="button" onclick="spCropper.setAspectRatio(16/9)" style="background:#333; color:white; border:1px solid #555; padding:6px 12px; border-radius:4px; cursor:pointer;">16:9</button>
            <button type="button" onclick="spCropper.setAspectRatio(NaN)" style="background:#333; color:white; border:1px solid #555; padding:6px 12px; border-radius:4px; cursor:pointer;">Libre</button>
            <div style="width:1px; background:#555; margin:0 5px;"></div>
            <button type="button" onclick="spCropper.rotate(90)" style="background:#333; color:white; border:1px solid #555; padding:6px 12px; border-radius:4px; cursor:pointer; display:flex; align-items:center; gap:4px;"><i class="ph ph-arrows-clockwise"></i> Rotar</button>
        `;
        
        setTimeout(() => {
            const img = document.getElementById('sp-crop-image');
            spCropper = new Cropper(img, {
                viewMode: 2,
                dragMode: 'move',
                autoCropArea: 1,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
            });
        }, 100);
    }
    
    modal.style.display = 'flex';
}

function spToggleVideoPlay() {
    if(!spVideoEl) return;
    const btn = document.getElementById('sp-btn-play');
    if(spVideoEl.paused) {
        spVideoEl.play();
        btn.innerHTML = '<i class="ph ph-pause"></i> Pausar';
    } else {
        spVideoEl.pause();
        btn.innerHTML = '<i class="ph ph-play"></i> Reproducir';
    }
}

function spCaptureVideoFrame() {
    if(!spVideoEl) return;
    spVideoEl.pause();
    document.getElementById('sp-btn-play').innerHTML = '<i class="ph ph-play"></i> Reproducir';
    
    const canvas = document.getElementById('sp-video-canvas');
    canvas.width = spVideoEl.videoWidth;
    canvas.height = spVideoEl.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(spVideoEl, 0, 0, canvas.width, canvas.height);
    
    spFinalBase64 = canvas.toDataURL('image/jpeg', 0.9);
    
    document.getElementById('sp-video-preview-overlay').style.display = 'flex';
    document.getElementById('sp-video-snapshot').src = spFinalBase64;
    document.getElementById('sp-editor-title').innerText = 'Frame Capturado';
}

function closeMediaEditor() {
    document.getElementById('sp-editor-modal').style.display = 'none';
    if(spCropper) { spCropper.destroy(); spCropper = null; }
    if(spVideoEl) { spVideoEl.pause(); spVideoEl.removeAttribute('src'); spVideoEl.load(); spVideoEl = null; }
    spFinalBase64 = null;
}

async function saveMediaEditor() {
    const btn = document.getElementById('btn-sp-editor-save');
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Procesando...';
    btn.disabled = true;
    
    let isVideo = spVideoEl !== null;
    
    if (!isVideo && spCropper) {
        spFinalBase64 = spCropper.getCroppedCanvas({
            maxWidth: 2000,
            maxHeight: 2000,
            fillColor: '#fff'
        }).toDataURL('image/jpeg', 0.9);
    }
    
    if (!spFinalBase64) {
        alert('No hay cambios aplicados o frame capturado.');
        btn.innerHTML = '<i class="ph ph-floppy-disk"></i> Aplicar Cambios';
        btn.disabled = false;
        return;
    }
    
    try {
        const formData = new FormData();
        const blob = await fetch(spFinalBase64).then(r => r.blob());
        formData.append('image', blob, isVideo ? 'thumbnail.jpg' : 'cropped.jpg');
        formData.append('month_id', document.querySelector('input[name="month_id"]').value);
        formData.append('post_type', 'Post Terminado');
        
        const res = await fetch('modules/month_board/ajax_upload_reference.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if(data.success && data.url) {
            let rawImageLink = document.getElementById('post-image-link') ? document.getElementById('post-image-link').value.trim() : '';
            let arr = [];
            if (rawImageLink.startsWith('[')) arr = JSON.parse(rawImageLink);
            else if (rawImageLink) arr = [rawImageLink];
            
            if (isVideo) {
                arr.push(data.url);
                alert("Miniatura guardada exitosamente en los archivos del post.");
            } else {
                if (arr.length > 0) arr[0] = data.url; 
                else arr = [data.url];
            }
            
            document.getElementById('post-image-link').value = JSON.stringify(arr);
            
            closeMediaEditor();
            
            // Reload Main Form state natively without crashing
            document.getElementById('socialPreviewModal').classList.remove('active');
            openSocialPreviewModal(); 
            
        } else {
            alert('Error al subir imagen al servidor.');
        }
    } catch(e) {
        alert('Error de red al procesar imagen.');
    }
    
    btn.innerHTML = '<i class="ph ph-floppy-disk"></i> Aplicar Cambios';
    btn.disabled = false;
}
</script>

<?php include 'social_profiles_modal.php'; ?>

<?php require_once 'includes/footer.php'; ?>
