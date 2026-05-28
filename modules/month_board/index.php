<?php
// modules/month_board/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

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
$stmtPosts = $db->prepare("SELECT * FROM month_posts WHERE month_id = ? ORDER BY post_date ASC");
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
];

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

<style>
    .board-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        background: var(--bg-surface);
        padding: 1.5rem;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .btn-back-compact {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        color: var(--text-color);
        text-decoration: none;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .btn-back-compact:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    .board-header-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 1;
    }
    .board-header-info img {
        width: 56px;
        height: 56px;
        border-radius: var(--radius-md);
        object-fit: contain;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        padding: 0.25rem;
    }
    .board-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-title);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
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
        grid-template-columns: repeat(auto-fill, minmax(280px, 380px));
        gap: 1.5rem;
    }

    .post-card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .post-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.08);
    }
    .post-image {
        height: 160px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border-color);
        position: relative;
    }
    [data-theme="dark"] .post-image {
        background: var(--bg-color);
    }
    .post-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .platform-badge {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: rgba(255,255,255,0.9);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 700;
        color: #333;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .post-body {
        padding: 1rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .post-date {
        font-size: 0.8rem;
        color: var(--text-muted);
        font-weight: 600;
    }
    .post-concept {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--color-title);
        margin: 0;
    }
    .post-copy {
        font-size: 0.9rem;
        color: var(--text-color);
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .post-footer {
        padding: 1rem;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-surface);
    }
    .post-status {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        text-transform: uppercase;
    }
    .post-actions {
        display: flex;
        gap: 0.5rem;
    }
    .btn-action {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.1rem;
        color: var(--text-muted);
        padding: 0.25rem;
        transition: color 0.2s;
    }
    .btn-action:hover {
        color: var(--primary-color);
    }
    .btn-action.delete:hover {
        color: var(--danger-color);
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
    }
</style>

<div class="board-header">
    <div style="display: flex; align-items: center; gap: 1.5rem; flex: 1; flex-wrap: wrap;">
        <a href="index.php?module=project_board&id=<?php echo $monthData['project_id']; ?>" class="btn-back-compact" title="Volver a los Meses">
            <i class="ph ph-arrow-left"></i>
        </a>
        <div class="board-header-info">
            <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo">
            <div>
                <h1 class="board-title">
                    Grilla: <?php echo $monthNames[$monthData['month']] . ' ' . $monthData['year']; ?>
                </h1>
                <p class="phase-container">
                    Fase de contenido actual: <span class="phase-badge"><?php echo htmlspecialchars($monthData['content_phase'] ?? 'En Borrador'); ?></span>
                    <span style="color: var(--text-muted); font-weight: 600; margin-left: 0.5rem; border-left: 1px solid var(--border-color); padding-left: 0.5rem;">
                        <?php echo count($posts); ?> Publicación(es)
                    </span>
                </p>
            </div>
        </div>
    </div>
    
    <div style="display: flex; gap: 0.75rem; align-items: center;">
        <button class="btn" style="background: #8b5cf6; color: white; border: none; font-weight: 600; padding: 0.5rem 0.75rem;" onclick="startPresentation()" title="Iniciar Presentación">
            <i class="ph ph-presentation-chart"></i> Iniciar Presentación
        </button>
        <button class="btn" style="background: #6366f1; color: white; border: none; font-weight: 600; padding: 0.5rem 0.75rem;" onclick="openShareModal()" title="Compartir">
            <i class="ph ph-share-network"></i>
        </button>
        <button class="btn" style="background: #10b981; color: white; border: none; font-weight: 600; padding: 0.5rem 0.75rem;" onclick="openUploadDriveModal()" title="Subir Archivos a Google Drive">
            <i class="ph ph-upload-simple"></i>
        </button>
        <button class="btn btn-primary" onclick="openPostModal()">
            <i class="ph ph-plus"></i> Añadir Publicación
        </button>
    </div>
</div>

<div class="posts-grid">
    <?php if (empty($posts)): ?>
        <div style="grid-column: 1 / -1; padding: 4rem 2rem; text-align: center; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: var(--radius-lg); color: var(--text-muted);">
            <i class="ph ph-image-square" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem; display: block;"></i>
            <h3>No hay publicaciones aún</h3>
            <p>Comienza añadiendo el primer post para este mes.</p>
        </div>
    <?php else: ?>
        <?php foreach ($posts as $p): ?>
            <?php
            $sColor = $statusColors[$p['status']] ?? $statusColors['Borrador'];
            $dateFmt = date('d M Y', strtotime($p['post_date']));
            $icons = [
                'Facebook' => 'ph-facebook-logo',
                'Instagram' => 'ph-instagram-logo',
                'TikTok' => 'ph-tiktok-logo',
                'LinkedIn' => 'ph-linkedin-logo',
                'Twitter / X' => 'ph-twitter-logo',
                'Otro' => 'ph-share-network'
            ];
            $icon = $icons[$p['platform']] ?? 'ph-share-network';
            ?>
            <div class="post-card">
                <div class="post-image" style="position: relative;">
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
                                        <img src="<?php echo htmlspecialchars($mItem); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="swiper-button-next" style="color: white; text-shadow: 0 1px 3px rgba(0,0,0,0.5); transform: scale(0.6);"></div>
                            <div class="swiper-button-prev" style="color: white; text-shadow: 0 1px 3px rgba(0,0,0,0.5); transform: scale(0.6);"></div>
                            <div class="swiper-pagination"></div>
                        </div>
                    <?php elseif (count($mediaList) === 1 && !empty($mediaList[0])): ?>
                        <?php if (strpos($mediaList[0], '.mp4') !== false || strpos($mediaList[0], 'drive.google.com') !== false): ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                                <i class="ph ph-video" style="font-size: 2rem; margin-bottom: 0.5rem; color: #3b82f6;"></i>
                                <a href="<?php echo htmlspecialchars($mediaList[0]); ?>" target="_blank" style="font-size: 0.8rem; color: #3b82f6; font-weight: bold;">Ver Video / Recurso</a>
                            </div>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($mediaList[0]); ?>" alt="Visual">
                        <?php endif; ?>
                    <?php else: ?>
                        <i class="ph ph-image-square" style="font-size: 3rem; opacity: 0.3;"></i>
                    <?php endif; ?>
                    
                    <?php if ($p['post_type'] === 'Referencia Visual'): ?>
                    <div style="position: absolute; top: 0.5rem; left: 0.5rem; background: rgba(245, 158, 11, 0.9); color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 0.25rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 10;">
                        <i class="ph ph-lightbulb"></i> Referencia
                    </div>
                    <?php endif; ?>
                    
                    <div class="platform-badge">
                        <i class="ph <?php echo $icon; ?>" style="color: #2563eb;"></i> <?php echo htmlspecialchars($p['platform']); ?>
                    </div>
                </div>
                <div class="post-body">
                    <div class="post-date">
                        <i class="ph ph-calendar-blank"></i> <?php echo $dateFmt; ?>
                    </div>
                    <h3 class="post-concept"><?php echo htmlspecialchars($p['concept']); ?></h3>
                    <div class="post-copy">
                        <?php echo nl2br(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $p['copy_text']))); ?>
                    </div>
                </div>
                <div class="post-footer">
                    <span class="post-status" style="background: <?php echo $sColor['bg']; ?>; color: <?php echo $sColor['color']; ?>;">
                        <?php echo htmlspecialchars($p['status']); ?>
                    </span>
                    <div class="post-actions">
                        <button class="btn-action" onclick="editPost(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                            <i class="ph ph-pencil-simple"></i>
                        </button>
                        <button class="btn-action delete" onclick="deletePost(<?php echo $p['id']; ?>)">
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
/* Estilos para el nuevo modal CRM */
.modal-content.crm-layout { max-width: 1400px; width: 95vw; height: 90vh; display: flex; flex-direction: row; padding: 0; overflow: hidden; background: var(--bg-color); border-radius: var(--radius-lg); }
.crm-sidebar { width: 340px; border-right: 1px solid var(--border-color); background: var(--bg-surface); padding: 1.5rem; display: flex; flex-direction: column; overflow-y: auto; flex-shrink: 0; z-index: 10; }
.crm-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg-color); position: relative; }
.crm-tabs { padding: 0 1.5rem; background: var(--bg-surface); border-bottom: 1px solid var(--border-color); display: flex; gap: 1.5rem; overflow-x: auto; flex-shrink: 0; }
.crm-tab { padding: 1rem 0; border-bottom: 2px solid transparent; cursor: pointer; font-weight: 600; color: var(--text-muted); font-size: 0.95rem; white-space: nowrap; transition: all 0.2s; }
.crm-tab:hover { color: var(--text-main); }
.crm-tab.active { border-bottom-color: #10b981; color: #10b981; }
[data-theme="dark"] .crm-tab.active { border-bottom-color: var(--warning-color); color: var(--text-main); }
.crm-tab-pane { display: none; animation: fadeIn 0.3s ease; }
.crm-tab-pane.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* Botón principal de guardado */
.btn-action-main {
    width: 100%; font-size: 1rem; padding: 0.8rem; border-radius: 8px; font-weight: 700; 
    background: #10b981; color: white; border: none; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);
    cursor: pointer; transition: all 0.2s;
}
.btn-action-main:hover { background: #059669; transform: translateY(-1px); }
[data-theme="dark"] .btn-action-main {
    background: var(--warning-color);
    color: #0f172a;
    box-shadow: 0 4px 6px rgba(245, 158, 11, 0.2);
}
[data-theme="dark"] .btn-action-main:hover { background: #d97706; }

/* Pipeline de estados */
.pipeline-stages { display: flex; background: #f1f5f9; border-radius: 20px; padding: 4px; gap: 4px; }
[data-theme="dark"] .pipeline-stages { background: #1e293b; }
.pipeline-stage { padding: 0.3rem 1rem; border-radius: 16px; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.2s; user-select: none; }
.pipeline-stage:hover { background: rgba(0,0,0,0.05); }
[data-theme="dark"] .pipeline-stage:hover { background: rgba(255,255,255,0.05); }
.pipeline-stage.active[data-status="Borrador"] { background: white; color: #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.pipeline-stage.active[data-status="En Revisión"] { background: white; color: #d97706; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.pipeline-stage.active[data-status="Aprobado"] { background: white; color: #2563eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.pipeline-stage.active[data-status="Publicado"] { background: white; color: #059669; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
[data-theme="dark"] .pipeline-stage.active { background: var(--bg-surface); }

/* Common styles */
.card-section { border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; background: var(--bg-surface); margin-bottom: 1rem; }
.section-title { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 1rem; letter-spacing: 0.5px; display: flex; align-items: center; gap: 0.5rem; }
.section-title.required::after { content: '*'; color: var(--danger-color); font-size: 0.9rem; }
.form-control { border: 1px solid #e2e8f0; background-color: var(--bg-surface); transition: all 0.2s ease; }
.form-control:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
.crm-main::-webkit-scrollbar, .crm-sidebar::-webkit-scrollbar, .modal-body::-webkit-scrollbar { width: 6px; }
.crm-main::-webkit-scrollbar-track, .crm-sidebar::-webkit-scrollbar-track { background: transparent; }
.crm-main::-webkit-scrollbar-thumb, .crm-sidebar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

/* Radio/Checkboxes estilizados como botones (Pills) */
.pill-group { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
.pill-input { display: none; }
.pill-label { padding: 0.4rem 0.8rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 0.85rem; cursor: pointer; color: var(--text-color); background: var(--bg-surface); transition: all 0.2s; user-select: none; display: flex; align-items: center; gap: 0.25rem; }
.pill-input:checked + .pill-label { background: var(--primary-color); color: white; border-color: var(--primary-color); }
.pill-input:checked + .pill-label i.ph-plus { display: none; }
.pill-input:checked + .pill-label::before { content: '\e964'; font-family: "Phosphor"; font-size: 1rem; }

/* Toggle Group */
.toggle-group { display: flex; background: #f1f5f9; padding: 0.25rem; border-radius: var(--radius-md); gap: 0.25rem; margin-bottom: 1.5rem; }
[data-theme="dark"] .toggle-group { background: #1e293b; }
.toggle-input { display: none; }
.toggle-label { flex: 1; text-align: center; padding: 0.5rem; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); border-radius: calc(var(--radius-md) - 0.25rem); cursor: pointer; transition: all 0.2s; white-space: nowrap; }
.toggle-input:checked + .toggle-label { background: white; color: #3b82f6; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
[data-theme="dark"] .toggle-input:checked + .toggle-label { background: var(--bg-surface); color: #60a5fa; }

/* Vista previa adaptativa */
.preview-container { display: flex; justify-content: center; margin-bottom: 0.5rem; }
.preview-box { background: #1e293b; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); position: relative; overflow: hidden; transition: all 0.3s ease; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
.preview-box.ratio-9-16 { width: 140px; height: 250px; }
.preview-box.ratio-1-1 { width: 250px; height: 250px; }
.preview-box.ratio-4-5 { width: 200px; height: 250px; }
.preview-box.ratio-16-9 { width: 280px; height: 157px; } 
.preview-box.ratio-auto { width: 250px; height: 140px; }
.preview-box .dot { position: absolute; bottom: 15px; left: 15px; width: 12px; height: 12px; background: #10b981; border-radius: 50%; }
.preview-actions { display: flex; justify-content: center; gap: 1rem; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; }
.preview-actions button { background: none; border: none; color: inherit; cursor: pointer; display: flex; align-items: center; gap: 0.25rem; }
.preview-actions button:hover { color: var(--primary-color); }

.form-control-sm { padding: 0.4rem 0.75rem; font-size: 0.85rem; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

/* Filas dinámicas */
.dyn-row { display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.75rem; background: var(--bg-color); padding: 0.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); }
.dyn-row-content { flex: 1; display: flex; flex-direction: column; gap: 0.5rem; }
.btn-remove-row { color: var(--danger-color); background: none; border: none; cursor: pointer; padding: 0.25rem; }

.btn-canva { background: transparent; color: #7D2AE8; border: 1px solid #7D2AE8; font-weight: 600; width: 100%; padding: 0.75rem; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; gap: 0.5rem; cursor: pointer; transition: all 0.2s; }
.btn-canva:hover { background: rgba(125, 42, 232, 0.05); }

.drive-box { border: 1px dashed var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; text-align: center; color: var(--text-muted); background: var(--bg-color); }

@media (max-width: 992px) {
    .modal-overlay { 
        padding: 0 !important; 
        align-items: flex-start !important;
        overflow: hidden !important; 
    }
    .modal-content.crm-layout,
    #slideEditorModal .modal { 
        flex-direction: column; 
        height: 100vh !important; 
        max-height: 100vh !important; 
        width: 100% !important; 
        max-width: 100% !important; 
        border-radius: 0 !important; 
        overflow-y: auto; 
        overflow-x: hidden;
        margin: 0 !important;
        transform: none !important;
    }
    .crm-sidebar { 
        width: 100%; 
        border-right: none; 
        border-bottom: 4px solid #e2e8f0; 
        padding: 1rem 0.75rem; 
        overflow: visible; 
    }
    /* Make sidebar header sticky */
    .crm-sidebar > div:first-child {
        position: sticky;
        top: -1rem;
        background: var(--bg-surface);
        padding-top: 1rem;
        padding-bottom: 0.75rem;
        z-index: 50;
        margin-top: -1rem;
        border-bottom: 1px solid var(--border-color);
        margin-left: -0.75rem;
        margin-right: -0.75rem;
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
    .crm-main { 
        overflow: visible !important; 
        padding-bottom: 2rem; 
        width: 100%; 
        flex: none !important; 
        height: auto !important; 
    }
    
    /* Wrap tabs on mobile so they don't get cut off */
    .crm-tabs { 
        padding: 0.5rem 0.75rem; 
        flex-wrap: wrap; 
        justify-content: flex-start;
        gap: 0.5rem 1rem;
    }
    
    /* Reduce side margins for the panes */
    .crm-tab-pane {
        padding: 0.75rem 0;
    }
    
    /* Full width cards on mobile */
    .card-section {
        padding: 1rem 1.25rem;
        margin-bottom: 0.5rem;
        border-radius: 0;
        border-left: none;
        border-right: none;
    }
    
    .grid-2 { grid-template-columns: 1fr !important; }
    
    /* Make the X button in sidebar always visible and better positioned on mobile */
    .crm-sidebar .d-md-none { display: flex !important; }
    
    /* Hide the main header on mobile since the sidebar has the title and X button */
    .crm-main > div:first-child { display: none !important; }
}
</style>

<!-- Modal Publicación (CRM Layout) -->
<div class="modal-overlay" id="post-modal">
    <div class="modal-content crm-layout">
        
        <form id="post-form" style="display: contents;">
            <input type="hidden" name="id" id="post-id" value="">
            <input type="hidden" name="month_id" value="<?php echo $monthId; ?>">
            <input type="hidden" name="status" id="post-status" value="Borrador">

            <!-- SIDEBAR -->
            <div class="crm-sidebar">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h2 id="post-modal-title" style="margin: 0; font-size: 1.25rem; font-weight: 700;">Añadir Publicación</h2>
                    <button type="button" class="btn-icon d-md-none" onclick="attemptCloseModal()" style="display: none;">
                        <i class="ph ph-x"></i>
                    </button>
                </div>

                <button type="button" class="btn-action-main" id="btn-save-post" onclick="savePost()" style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <i class="ph ph-floppy-disk"></i> <span id="btn-save-post-text">Guardar Publicación</span>
                </button>
                <div style="text-align: center; margin-top: -1rem; margin-bottom: 1.5rem;">
                    <span id="auto-save-indicator" style="font-size: 0.75rem; color: var(--text-muted); opacity: 0; transition: opacity 0.3s;">Guardado automático...</span>
                </div>

                <div class="form-group">
                    <label class="section-title required">Concepto / Título</label>
                    <input type="text" name="concept" id="post-concept" class="form-control" required placeholder="Ej. Promoción de Verano" style="font-size: 1rem; font-weight: 600;">
                </div>

                <div class="form-group">
                    <label class="section-title required">Red Social</label>
                    <div class="pill-group">
                        <input type="checkbox" name="platform[]" id="plat1" value="Facebook" class="pill-input">
                        <label for="plat1" class="pill-label" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;"><i class="ph ph-facebook-logo"></i> FB</label>
                        
                        <input type="checkbox" name="platform[]" id="plat2" value="Instagram" class="pill-input">
                        <label for="plat2" class="pill-label" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;"><i class="ph ph-instagram-logo"></i> IG</label>
                        
                        <input type="checkbox" name="platform[]" id="plat3" value="TikTok" class="pill-input">
                        <label for="plat3" class="pill-label" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;"><i class="ph ph-tiktok-logo"></i> TT</label>
                        
                        <input type="checkbox" name="platform[]" id="plat4" value="LinkedIn" class="pill-input">
                        <label for="plat4" class="pill-label" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;"><i class="ph ph-linkedin-logo"></i> IN</label>
                        
                        <input type="checkbox" name="platform[]" id="plat5" value="Twitter / X" class="pill-input">
                        <label for="plat5" class="pill-label" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;"><i class="ph ph-twitter-logo"></i> X</label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="section-title">Fechas y Programación</label>
                    <div style="background: var(--bg-color); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.75rem; font-weight: 600; display: block; margin-bottom: 0.25rem;">Inicio *</label>
                            <input type="datetime-local" name="post_date" id="post-date" class="form-control form-control-sm" required onchange="updateSaveButtonState()">
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="font-size: 0.75rem; font-weight: 600; display: block; margin-bottom: 0.25rem;">Entrega / Fin</label>
                            <input type="date" name="end_date" id="post-end-date" class="form-control form-control-sm">
                        </div>
                        <div class="grid-2">
                            <div>
                                <label style="font-size: 0.75rem; font-weight: 600; display: block; margin-bottom: 0.25rem;">Periodicidad</label>
                                <select name="periodicity" id="post-periodicity" class="form-control form-control-sm">
                                    <option value="">Única vez</option>
                                    <option value="Diario">Diario</option>
                                    <option value="Semanal">Semanal</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; font-weight: 600; display: block; margin-bottom: 0.25rem;">Recordatorio</label>
                                <select name="reminder" id="post-reminder" class="form-control form-control-sm">
                                    <option value="">Ninguno</option>
                                    <option value="1 dia antes">1 día</option>
                                    <option value="1 hora antes">1 h</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MAIN AREA -->
            <div class="crm-main">
                <!-- Header with Pipeline -->
                <div style="padding: 1rem 1.5rem; background: var(--bg-surface); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted);">Pipeline: <span style="color: var(--color-title);">Flujo de Publicación</span></div>
                    </div>
                    
                    <div class="pipeline-stages">
                        <div class="pipeline-stage active" data-status="Borrador" onclick="setPostStatus('Borrador')">Borrador</div>
                        <div class="pipeline-stage" data-status="En Revisión" onclick="setPostStatus('En Revisión')">En Revisión</div>
                        <div class="pipeline-stage" data-status="Aprobado" onclick="setPostStatus('Aprobado')">Aprobado</div>
                        <div class="pipeline-stage" data-status="Publicado" onclick="setPostStatus('Publicado')">Publicado</div>
                    </div>

                    <button type="button" class="btn-icon" onclick="attemptCloseModal()">
                        <i class="ph ph-x"></i>
                    </button>
                </div>

                <!-- Tabs Navigation -->
                <div class="crm-tabs">
                    <div class="crm-tab active" onclick="switchCrmTab(this, 'tab-contenido')">Contenido</div>
                    <div class="crm-tab" onclick="switchCrmTab(this, 'tab-diseno')">Diseño y Formatos</div>
                    <div class="crm-tab" onclick="switchCrmTab(this, 'tab-recursos')">Recursos y Drive</div>
                    <div class="crm-tab" onclick="switchCrmTab(this, 'tab-avances')">Avances</div>
                    <div class="crm-tab" onclick="switchCrmTab(this, 'tab-comentarios')">Comentarios del Cliente</div>
                </div>

                <!-- Tabs Content Area -->
                <div style="flex: 1; overflow-y: auto; padding: 1.5rem;">
                    
                    <!-- TAB CONTENIDO -->
                    <div id="tab-contenido" class="crm-tab-pane active">
                        <div class="grid-2" style="grid-template-columns: 1fr 320px; gap: 1.5rem; align-items: flex-start;">
                            <div>
                                <div class="card-section" style="background: var(--bg-surface); border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); height: 100%;">
                                    <div class="section-title required"><i class="ph ph-text-align-left"></i> Copy del Post</div>
                                    <textarea name="copy_text" id="post-copy" class="form-control" rows="18" required placeholder="Escribe el texto de la publicación..." oninput="updateCopyPreview()" style="font-size: 0.95rem; line-height: 1.6;"></textarea>
                                    <div style="text-align: right; margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-muted);">
                                        <span id="char-count">0</span> caracteres
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="card-section" style="background: var(--bg-surface); border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); text-align: center;">
                                    <div class="section-title" style="justify-content: center;"><i class="ph ph-devices"></i> Visual del Post</div>
                                    <div class="toggle-group" style="margin-bottom: 1.5rem;">
                                        <input type="radio" name="post_type" id="pt_ref" value="Referencia Visual" class="toggle-input" checked onchange="updateVideoPreview()">
                                        <label for="pt_ref" class="toggle-label">Ref. Visual</label>
                                        
                                        <input type="radio" name="post_type" id="pt_post" value="Post Terminado" class="toggle-input" onchange="updateVideoPreview()">
                                        <label for="pt_post" class="toggle-label">Terminado</label>
                                    </div>

                                    <div class="preview-container">
                                        <div class="preview-box ratio-9-16" id="preview-box" ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                                            <i class="ph ph-image" id="preview-icon" style="font-size: 3rem; opacity: 0.3; color: white;"></i>
                                            <div class="dot"></div>
                                            <div id="preview-text-overlay" style="position: absolute; bottom: 0; left: 0; right: 0; padding: 10px; font-size: 0.6rem; line-height: 1.2; background: rgba(0,0,0,0.6); color: white; display: none; text-align: left; max-height: 40%; overflow: hidden; text-overflow: ellipsis;"></div>
                                        </div>
                                    </div>
                                    <div class="preview-actions">
                                        <button type="button" onclick="document.getElementById('post-main-image-upload').click()"><i class="ph ph-image-square"></i> Subir Archivo</button>
                                        <button type="button" id="btn-eliminar-recurso" onclick="clearActiveTabImage();"><i class="ph ph-trash"></i> Eliminar Todo</button>
                                    </div>
                                    <input type="file" id="post-main-image-upload" style="display:none" accept="image/*,video/mp4" multiple onchange="uploadMainImage(this)">
                                    <input type="hidden" name="image_link" id="post-image-link">
                                    <input type="hidden" name="reference_image_link" id="post-reference-link">

                                    <div id="video-url-container" style="border-top: 1px dashed var(--border-color); padding-top: 1.5rem; margin-top: 1rem; text-align: left;">
                                        <div id="video-url-input-group">
                                            <label style="font-size: 0.75rem; font-weight: 700; color: #3b82f6; display: block; margin-bottom: 0.5rem;"><i class="ph ph-link"></i> ENLACE EXTERNO (YOUTUBE/DRIVE/IG)</label>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <input type="text" id="video-url-input" class="form-control form-control-sm" placeholder="Pegar URL aquí..." oninput="handleVideoUrlInput()">
                                                <button type="button" class="btn btn-outline" style="padding: 0 0.5rem;" onclick="document.getElementById('video-url-input').value=''; handleVideoUrlInput();">
                                                    <i class="ph ph-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div id="download-btn-group" style="display: none;">
                                            <button type="button" class="btn btn-primary" style="width: 100%; display: flex; justify-content: center; align-items: center; gap: 0.5rem; padding: 0.75rem; border-radius: 8px; font-weight: 600;" onclick="downloadActiveResource()"><i class="ph ph-download-simple" style="font-size: 1.2rem;"></i> Descargar Archivo</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB DISEÑO Y FORMATOS -->
                    <div id="tab-diseno" class="crm-tab-pane">
                        <div class="card-section" style="background: var(--bg-surface); border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="section-title"><i class="ph ph-squares-four"></i> Formatos Requeridos</div>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: -0.5rem; margin-bottom: 1.5rem;">Selecciona todos los formatos en los que debe adaptarse este contenido.</p>
                            
                            <div class="pill-group" style="margin-bottom: 2.5rem;">
                                <input type="checkbox" name="formats[]" id="fmt1" value="Cuadrado" class="pill-input">
                                <label for="fmt1" class="pill-label"><i class="ph ph-plus"></i> Cuadrado (1:1)</label>
                                
                                <input type="checkbox" name="formats[]" id="fmt2" value="Carrousel" class="pill-input">
                                <label for="fmt2" class="pill-label"><i class="ph ph-plus"></i> Carrousel</label>
                                
                                <input type="checkbox" name="formats[]" id="fmt3" value="Colección" class="pill-input">
                                <label for="fmt3" class="pill-label"><i class="ph ph-plus"></i> Colección</label>
                                
                                <input type="checkbox" name="formats[]" id="fmt4" value="Evento de Facebook" class="pill-input">
                                <label for="fmt4" class="pill-label"><i class="ph ph-plus"></i> Evento de Facebook</label>

                                <input type="checkbox" name="formats[]" id="fmt5" value="Historia" class="pill-input">
                                <label for="fmt5" class="pill-label"><i class="ph ph-plus"></i> Historia (9:16)</label>
                                
                                <input type="checkbox" name="formats[]" id="fmt6" value="Reel / TikTok" class="pill-input">
                                <label for="fmt6" class="pill-label"><i class="ph ph-plus"></i> Reel / TikTok</label>
                            </div>

                            <div style="border-left: 4px solid #3b82f6; padding-left: 1.5rem; margin-bottom: 2rem;">
                                <div class="section-title" style="color: #3b82f6; font-size: 0.8rem;"><i class="ph ph-palette"></i> Brief de Diseño (Instrucciones para el diseñador)</div>
                                <textarea name="design_brief" id="post-brief" style="width:100%; height: 200px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); color: var(--text-color); padding: 0.75rem; font-family: inherit; font-size: 0.9rem; resize: vertical;"></textarea>
                            </div>

                            <div class="slides-widget" style="border: 1px dashed var(--border-color); border-radius: 8px; padding: 1.5rem; margin-top: 2.5rem; background: var(--bg-color);">
                                <div style="font-size: 0.85rem; font-weight: 700; color: #eab308; display: flex; align-items: center; gap: 0.5rem; justify-content: flex-start; margin-bottom: 1.5rem;">
                                    <i class="ph ph-file-slides"></i> PRESENTACIÓN DE REFERENCIAS
                                </div>
                                
                                <div id="slides-empty-state">
                                    <button type="button" class="btn btn-outline" style="width: 100%; border: 1px dashed var(--border-color); color: var(--text-muted); font-weight: 600; padding: 2.5rem; border-radius: 8px; background: transparent;" onclick="openSlideEditor(this)">
                                        <i class="ph ph-plus" style="display:none;"></i> <span class="btn-text">Haz clic para crear un documento de Slides para referencias</span>
                                    </button>
                                </div>

                                <div id="slides-linked-state" style="display: none; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: var(--bg-surface);">
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color);">
                                        <div style="background: rgba(2, 132, 199, 0.1); color: #0ea5e9; font-size: 0.7rem; font-weight: 700; padding: 0.3rem 0.6rem; border-radius: 4px;">VINCULADO</div>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button type="button" class="btn" style="padding: 0.4rem 1rem; font-size: 0.8rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6; font-weight: 600; border: none; border-radius: 6px;" onclick="openSlideEditorModal()">Abrir Editor</button>
                                            <button type="button" class="btn" style="padding: 0.4rem 1rem; font-size: 0.8rem; background: var(--bg-color); color: var(--text-color); font-weight: 600; border: 1px solid var(--border-color); border-radius: 6px;" onclick="window.open(document.getElementById('post-drive').value, '_blank')">Pestaña Externa</button>
                                        </div>
                                    </div>
                                    <div style="height: 400px; width: 100%; background: #000; position: relative;">
                                        <iframe id="slides-iframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
                                    </div>
                                    <div style="padding: 0.5rem 1rem; border-top: 1px solid var(--border-color); background: var(--bg-color); display: flex; justify-content: space-between; align-items: center;">
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><i class="ph ph-presentation-chart"></i> Vista Previa</div>
                                        <div style="font-size: 0.8rem; color: var(--text-color); font-weight: 500; display: flex; align-items: center; gap: 0.25rem;"><i class="ph ph-google-logo"></i> Google Slides</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB RECURSOS -->
                    <div id="tab-recursos" class="crm-tab-pane">
                        <div class="grid-2" style="gap: 1.5rem; max-width: 1200px;">
                            <div>
                                <div class="card-section" style="background: var(--bg-surface); border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                                        <div class="section-title" style="margin: 0; color: #3b82f6;"><i class="ph ph-image"></i> Referencias Visuales</div>
                                        <button type="button" class="btn btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.8rem;" onclick="addRefRow()">+ Añadir</button>
                                    </div>
                                    <div id="refs-container"></div>
                                </div>
                                
                                <div class="card-section" style="background: var(--bg-surface); border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                                        <div class="section-title" style="margin: 0;"><i class="ph ph-files"></i> Otras Variaciones</div>
                                        <button type="button" class="btn btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.8rem;" onclick="addVarRow()">+ Añadir</button>
                                    </div>
                                    <div id="vars-container"></div>
                                </div>
                            </div>
                            <div>
                                <div class="card-section" style="background: var(--bg-surface); border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); height: 100%;">
                                    <div class="section-title"><i class="ph ph-google-drive-logo"></i> Recursos en Google Drive</div>
                                    <div class="drive-box" id="drive-box-container" style="background: var(--bg-color); border-width: 2px;">
                                        <i class="ph ph-google-drive-logo" style="font-size: 3rem; color: #3b82f6; margin-bottom: 1rem; display: block;"></i>
                                        <p style="margin: 0; font-size: 0.95rem; margin-bottom: 1.5rem; color: var(--text-color);">Pega un enlace de Google Drive abajo o selecciona archivos directamente desde tu cuenta.</p>
                                        <button type="button" class="btn btn-primary" onclick="loadPicker()" style="width: 100%; justify-content: center; padding: 0.75rem;"><i class="ph ph-magnifying-glass"></i> Explorar mi Drive</button>
                                    </div>
                                    <div class="form-group" style="margin-top: 1.5rem;">
                                        <label style="font-size: 0.85rem; font-weight: 600;">Enlace de Carpeta o Archivo:</label>
                                        <input type="url" name="drive_images" id="post-drive" class="form-control" placeholder="https://drive.google.com/..." oninput="handleDriveLink()" style="margin-top: 0.5rem;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB AVANCES -->
                    <div id="tab-avances" class="crm-tab-pane">
                        <div style="background: rgba(16, 185, 129, 0.05); border: 1px dashed rgba(16, 185, 129, 0.3); padding: 2rem; border-radius: 12px; text-align: center; margin-bottom: 2rem;" id="avances-paste-area">
                            <i class="ph ph-clipboard" style="font-size: 3rem; color: var(--secondary-color); margin-bottom: 1rem;"></i>
                            <h3 style="margin:0 0 0.5rem 0;">Presiona Ctrl + V para pegar avance</h3>
                            <p style="margin:0; font-size:0.85rem; color:var(--text-muted);">Haz captura de pantalla y pega aquí. Se subirá automáticamente a Drive.</p>
                            <div id="avance-upload-progress" style="margin-top: 1rem; font-size: 0.85rem; color: var(--primary-color); display: none;">Subiendo imagen...</div>
                        </div>

                        <div id="avances-gallery" class="thumb-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 1rem;">
                            <!-- Avances subidos aparecerán aquí -->
                        </div>
                        <div id="avances-empty" style="text-align:center; padding: 2rem; color:var(--text-muted);">
                            <p>No hay avances subidos todavía.</p>
                        </div>
                    </div>

                    <!-- TAB COMENTARIOS -->
                    <div id="tab-comentarios" class="crm-tab-pane">
                        <div class="card-section" style="background: var(--bg-surface); border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); min-height: 400px;">
                            <div class="section-title"><i class="ph ph-chat-circle-text"></i> Comentarios del Cliente</div>
                            <div id="comments-container">
                                <!-- Los comentarios se cargarán aquí por JS -->
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
    
    // 4. Clear TinyMCE editors (form.reset doesn't touch them)
    if (typeof tinymce !== 'undefined') {
        if (tinymce.get('post-copy')) tinymce.get('post-copy').setContent('');
        if (tinymce.get('post-brief')) tinymce.get('post-brief').setContent('');
    }
    
    // 5. Clear dynamic containers
    document.getElementById('refs-container').innerHTML = '';
    document.getElementById('vars-container').innerHTML = '';
    
    // 6. Clear video/URL inputs
    document.getElementById('video-url-input').value = '';
    document.getElementById('post-drive').value = '';
    
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
    
    document.getElementById('post-modal-title').innerText = 'Añadir Publicación';
    
    // Resetear pestañas y pipeline
    switchCrmTab(document.querySelector('.crm-tab:first-child'), 'tab-contenido');
    updatePipelineUI();
    
    document.getElementById('post-modal').classList.add('active');
    updateSaveButtonState();
    updateVideoPreview();
    updateCopyPreview();
    handleDriveLink();
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
    }
    updateVideoPreview();
}

function clearActiveTabImage(indexToRemove = -1) {
    const isRef = document.querySelector('input[name="post_type"]:checked').value === 'Referencia Visual';
    const inputEl = isRef ? document.getElementById('post-reference-link') : document.getElementById('post-image-link');
    
    if (indexToRemove === -1) {
        inputEl.value = '';
    } else {
        try {
            let list = JSON.parse(inputEl.value);
            if (Array.isArray(list)) {
                list.splice(indexToRemove, 1);
                inputEl.value = list.length > 0 ? JSON.stringify(list) : '';
            } else {
                inputEl.value = '';
            }
        } catch(e) {
            inputEl.value = '';
        }
    }
    updateVideoPreview();
    markDirty();
}

function updateVideoPreview() {
    const box = document.getElementById('preview-box');
    const dot = '<div class="dot"></div>';
    
    // Type check to differentiate reference vs final post
    let pTypeObj = document.querySelector('input[name="post_type"]:checked');
    let pType = pTypeObj ? pTypeObj.value : 'Post Terminado';
    let isRef = (pType === 'Referencia Visual');
    
    let overlayHtml = '';
    const overlayEl = document.getElementById('preview-text-overlay');
    // Only show text overlay if it's a Post Terminado
    if(overlayEl && !isRef) {
        overlayHtml = overlayEl.outerHTML;
    }
    
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
    } else {
        document.getElementById('video-url-input-group').style.display = 'none';
        document.getElementById('download-btn-group').style.display = 'block';
    }
    
    // Si no hay URL
    if (mediaList.length === 0) {
        box.className = 'preview-box ratio-auto';
        box.style.width = ''; box.style.height = ''; box.style.maxWidth = '';
        box.style.display = 'flex'; box.style.padding = '0'; box.style.gridTemplateColumns = ''; box.style.gap = '';
        box.innerHTML = `${refBadge}<i class="ph ph-image" id="preview-icon" style="font-size: 3rem; opacity: 0.3; color: white;"></i>${dot}${overlayHtml}`;
        return;
    }

    // Si son múltiples imágenes
    if (mediaList.length > 1) {
        box.className = 'preview-box';
        box.style.width = '100%';
        box.style.height = 'auto';
        box.style.maxWidth = 'none';
        box.style.padding = '10px';
        box.style.display = 'grid';
        box.style.gridTemplateColumns = 'repeat(auto-fill, minmax(80px, 1fr))';
        box.style.gap = '10px';
        box.style.alignItems = 'start';
        box.style.justifyContent = 'start';
        
        let html = refBadge;
        mediaList.forEach((url, i) => {
            html += `<div class="sortable-item" style="position:relative; width: 100%; aspect-ratio: 1; border-radius: 8px; overflow: hidden; background: #0f172a; cursor: grab;">
                        <img src="${url}" style="width:100%; height:100%; object-fit:cover;">
                        <button type="button" onclick="event.stopPropagation(); clearActiveTabImage(${i});" style="position:absolute; top:2px; right:2px; background:rgba(239,68,68,0.9); color:white; border:none; border-radius:50%; width:20px; height:20px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:10px; z-index: 20;"><i class="ph ph-x"></i></button>
                     </div>`;
        });
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
    const isVideoLink = !isDriveImage && url.match(/(youtu\.be|youtube\.com|tiktok\.com|\.mp4|drive\.google\.com|instagram\.com|facebook\.com|fb\.watch)/i);

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
            box.innerHTML = `${refBadge}<iframe width="100%" height="100%" src="https://www.youtube.com/embed/${ytMatch[1]}?autoplay=1&mute=1" frameborder="0" allowfullscreen style="border:none; border-radius:12px;"></iframe>${dot}${overlayHtml}`;
        } else if (url.toLowerCase().endsWith('.mp4')) {
            // Override container constraints for mp4
            box.className = 'preview-box';
            box.style.width = '100%';
            box.style.height = 'auto';
            box.style.maxWidth = 'none';
            box.innerHTML = `${refBadge}<video controls playsinline style="width: 100%; max-height: 600px; object-fit: contain; border-radius: 12px; background: #000; display: block;"><source src="${url}" type="video/mp4"></video>${dot}${overlayHtml}`;
        } else if (url.match(/tiktok\.com\/(?:@[\w.-]+\/video\/|v\/)?(\d+)/)) {
            const tiktokMatch = url.match(/tiktok\.com\/(?:@[\w.-]+\/video\/|v\/)?(\d+)/);
            box.innerHTML = `${refBadge}<iframe width="100%" height="100%" src="https://www.tiktok.com/embed/v2/${tiktokMatch[1]}" frameborder="0" allowfullscreen style="border:none; border-radius:12px;"></iframe>${dot}${overlayHtml}`;
        } else if (url.match(/instagram\.com\/(?:p|reel)\/([\w-]+)/)) {
            const igMatch = url.match(/instagram\.com\/(?:p|reel)\/([\w-]+)/);
            box.innerHTML = `${refBadge}<iframe width="100%" height="100%" src="https://www.instagram.com/p/${igMatch[1]}/embed/captioned" frameborder="0" scrolling="no" allowtransparency="true" style="border:none; border-radius:12px; background:white;"></iframe>${dot}${overlayHtml}`;
        } else if (url.match(/facebook\.com|fb\.watch/i)) {
            // Facebook: Meta blocks iframe embedding — show a styled preview card
            const shortFbUrl = url.length > 60 ? url.slice(0, 57) + '…' : url;
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
                ${refBadge}${dot}${overlayHtml}`;
        } else if (url.match(/drive\.google\.com\/(?:file\/d\/|open\?id=)([\w-]+)/)) {
            const driveMatch = url.match(/drive\.google\.com\/(?:file\/d\/|open\?id=)([\w-]+)/);
            box.innerHTML = `${refBadge}<iframe width="100%" height="100%" src="https://drive.google.com/file/d/${driveMatch[1]}/preview" frameborder="0" allowfullscreen style="border:none; border-radius:12px;"></iframe>${dot}${overlayHtml}`;
        } else {
            box.innerHTML = `
                <div style="text-align: center; color: white; padding: 1rem; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; background: #1e293b; border-radius: 12px;">
                    <i class="ph ph-video-camera" style="font-size: 3rem; color: #3b82f6;"></i>
                    <p style="font-size: 0.75rem; margin-top: 0.5rem; word-break: normal; opacity: 0.8; line-height: 1.4;">Video Adjunto<br>Reels Ratio (9:16)</p>
                </div>
                ${refBadge}${dot}${overlayHtml}
            `;
        }
    } else {
        // Intelligent image ratio
        box.className = 'preview-box';
        if (isRef) {
            box.style.width = '100%';
            box.style.height = 'auto';
            box.style.maxWidth = 'none';
            box.innerHTML = `${refBadge}<img src="${url}" style="width: 100%; height: auto; display: block; border-radius: 12px; object-fit: contain;">${dot}${overlayHtml}`;
        } else {
            box.style.width = 'auto';
            box.style.height = 'auto';
            box.style.maxWidth = '280px';
            box.innerHTML = `${refBadge}<img src="${url}" style="width: 100%; height: auto; max-height: 400px; display: block; border-radius: 12px; object-fit: contain;">${dot}${overlayHtml}`;
        }
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
        alert('No hay archivo subido para descargar.');
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
    document.getElementById('post-copy').value = post.copy_text;
    if (typeof tinymce !== 'undefined') {
        if (tinymce.get('post-copy')) tinymce.get('post-copy').setContent(post.copy_text || '');
    }
    document.getElementById('post-status').value = post.status;
    document.getElementById('post-image-link').value = post.image_link || '';
    document.getElementById('post-reference-link').value = post.reference_image_link || '';
    
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
    if (post.design_brief) {
        document.getElementById('post-brief').value = post.design_brief;
        if (typeof tinymce !== 'undefined' && tinymce.get('post-brief')) tinymce.get('post-brief').setContent(post.design_brief || '');
    }
    if (post.drive_images) {
        document.getElementById('post-drive').value = post.drive_images;
        handleDriveLink();
    }

    // Formatos
    let formats = [];
    try { formats = JSON.parse(post.formats || '[]'); } catch(e){}
    document.querySelectorAll('input[name="formats[]"]').forEach(chk => {
        chk.checked = formats.includes(chk.value);
    });

    // Referencias
    let refs = [];
    try { refs = JSON.parse(post.visual_references || '[]'); } catch(e){}
    let refsContainer = document.getElementById('refs-container');
    refsContainer.innerHTML = '';
    refs.forEach(r => addRefRow(r));

    // Variaciones
    let vars = [];
    try { vars = JSON.parse(post.variations || '[]'); } catch(e){}
    let varsContainer = document.getElementById('vars-container');
    varsContainer.innerHTML = '';
    vars.forEach(v => addVarRow(v.title, v.instructions));
    
    // Comentarios
    let commentsContainer = document.getElementById('comments-container');
    if (commentsContainer) {
        commentsContainer.innerHTML = '';
        if (post.comments && post.comments.length > 0) {
            post.comments.forEach(c => {
                let html = `<div style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; display: flex; justify-content: space-between;">
                        <span style="display:flex; align-items:center; gap:0.5rem;">
                            <span>${c.created_at}</span>
                            ${c.phase ? `<span style="background: #e0f2fe; color: #0369a1; padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 600; font-size: 0.7rem;">${c.phase}</span>` : ''}
                        </span>
                        <span class="status-badge" style="padding: 0.2rem 0.5rem; font-size: 0.7rem; border-radius: 4px; ${c.status === 'Levantado' ? 'background: rgba(16, 185, 129, 0.1); color: #059669;' : 'background: rgba(245, 158, 11, 0.1); color: #d97706;'}">${c.status}</span>
                    </div>
                    <p style="margin: 0 0 1rem 0; font-size: 0.95rem; white-space: pre-wrap;">${c.comment_text}</p>`;
                if (c.image_link) {
                    html += `<div style="margin-bottom: 1rem;"><a href="${c.image_link}" target="_blank"><img src="${c.image_link}" style="max-width: 100%; border-radius: 8px; max-height: 200px;"></a></div>`;
                }
                if (c.status !== 'Levantado') {
                    html += `<button type="button" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.4rem 0.8rem; color: #10b981; border-color: #10b981;" onclick="markCommentResolved(${c.id}, ${post.id})"><i class="ph ph-check-circle"></i> Marcar como Levantado</button>`;
                }
                html += `</div>`;
                commentsContainer.innerHTML += html;
            });
        } else {
            commentsContainer.innerHTML = '<p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 2rem 0;">No hay comentarios para esta publicación.</p>';
        }
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
            alert('Comentario marcado como levantado.');
            window.location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (err) {
        console.error(err);
        alert('Error de red.');
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
    const formData = new FormData();
    formData.append('image', input.files[0]);
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
            alert(res.error || 'Error subiendo imagen.');
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
        
        updateVideoPreview();
        markDirty();
    } else {
        box.innerHTML = oldHtml;
        updateVideoPreview();
    }
    
    input.value = ''; // Reset input
}

async function savePost(isAutoSave = false) {
    // Capture the post-id BEFORE triggerSave (TinyMCE can interfere with form state)
    const postIdValue = document.getElementById('post-id').value;
    
    if (typeof tinymce !== 'undefined') tinymce.triggerSave();
    
    const form = document.getElementById('post-form');
    if (!isAutoSave && !form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const formData = new FormData(form);

    // Force the correct id value (defense against stale form state)
    formData.set('id', postIdValue);

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
            if(!isAutoSave) alert(result.error || 'Error al guardar.');
        }
    } catch (e) {
        console.error(e);
        if(!isAutoSave) alert('Error de red.');
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

async function performMainImageUpload(file) {
    return new Promise((resolve) => {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('month_id', document.querySelector('input[name="month_id"]').value);
        
        const isRef = document.querySelector('input[name="post_type"]:checked').value === 'Referencia Visual';
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
                        alert(res.error || 'Error subiendo imagen.');
                        resolve(null);
                    }
                } catch(e) {
                    console.error("Parse error. Raw response:", xhr.responseText);
                    alert('Error procesando la respuesta.');
                    resolve(null);
                }
            } else {
                alert('Error de red al subir la imagen.');
                resolve(null);
            }
        };

        xhr.onerror = function() {
            alert('Error de red al subir la imagen.');
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

    const overlay = document.getElementById('preview-text-overlay');
    if (overlay) {
        if (text) {
            overlay.style.display = 'block';
            overlay.innerText = text;
        } else {
            overlay.style.display = 'none';
        }
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
            alert(result.error || 'Error al eliminar.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
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
            alert('La conexión con Google Drive no está lista. Verifica las credenciales en Ajustes.');
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
                alert('Error al crear presentación: ' + (res.error || 'Error desconocido'));
                btnTextSpan.innerText = originalText;
                iconElement.className = originalIcon;
                btnElement.disabled = false;
            }
        } catch (error) {
            alert('Error de red al intentar crear la presentación.');
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
        <div class="modal-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #fff;">
            <h3 style="margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;"><i class="ph ph-file-slides" style="color: #eab308;"></i> Editor de Referencias</h3>
            <button type="button" class="btn-icon" onclick="document.getElementById('slideEditorModal').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <div style="flex: 1; position: relative; background: #f8fafc;">
            <iframe id="slideEditorIframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
        </div>
    </div>
</div>

<!-- Modal Compartir Vista Pública -->
<div class="modal-overlay" id="shareModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3 style="margin: 0; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem;"><i class="ph ph-share-network" style="color: #6366f1;"></i> Compartir con el Cliente</h3>
            <button type="button" class="btn-icon" onclick="document.getElementById('shareModal').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="padding-top: 0.5rem;">
            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Envía este enlace a tu cliente para que revise el tablero de: <br>
                <strong style="color: var(--color-title);"><?php echo htmlspecialchars($monthData['brand_name']); ?> - <?php echo $monthNames[$monthData['month']] . ' ' . $monthData['year']; ?></strong>
            </p>

            <div style="background: var(--bg-color); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
                <div style="font-size: 0.85rem; font-weight: 600;">
                    <i class="ph ph-lock-key"></i> Proteger con PIN
                </div>
                <label class="switch">
                    <input type="checkbox" id="pin-toggle" onchange="togglePinProtection()" <?php echo !empty($monthData['pin']) ? 'checked' : ''; ?>>
                    <span class="slider round"></span>
                </label>
            </div>

            <div id="pin-container" style="display: <?php echo !empty($monthData['pin']) ? 'block' : 'none'; ?>; margin-bottom: 1.5rem; text-align: center;">
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;">El PIN actual es:</div>
                <div style="font-size: 2rem; font-weight: 800; letter-spacing: 5px; color: var(--color-title);" id="current-pin">
                    <?php echo htmlspecialchars($monthData['pin'] ?? '------'); ?>
                </div>
                <button type="button" class="btn btn-outline" style="padding: 0.2rem 0.5rem; font-size: 0.8rem; margin-top: 0.5rem;" onclick="generateNewPin()">
                    <i class="ph ph-arrows-clockwise"></i> Generar Nuevo PIN
                </button>
            </div>

            <button type="button" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.8rem; font-size: 1rem; font-weight: 700; background: #6366f1; border-color: #6366f1;" onclick="copyClientLink()">
                <i class="ph ph-copy"></i> Copiar Enlace Mágico
            </button>
        </div>
    </div>
</div>
<style>
.switch { position: relative; display: inline-block; width: 46px; height: 24px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; }
input:checked + .slider { background-color: #6366f1; }
input:focus + .slider { box-shadow: 0 0 1px #6366f1; }
input:checked + .slider:before { transform: translateX(22px); }
.slider.round { border-radius: 24px; }
.slider.round:before { border-radius: 50%; }
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
            alert('¡Enlace copiado al portapapeles!');
        });
    }

    function openSlideEditorModal() {
        const url = document.getElementById('post-drive').value;
        if (!url || !url.includes('presentation')) {
            alert('No hay una presentación vinculada.');
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>
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
    tinymce.init({
        selector: '#post-copy, #post-brief',
        height: 300,
        menubar: false,
        plugins: 'lists link code',
        toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | bullist numlist | link code',
        skin: document.documentElement.getAttribute('data-theme') === 'dark' ? 'oxide-dark' : 'oxide',
        content_css: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'default',
        setup: function (editor) {
            editor.on('change keyup', function () {
                editor.save(); // Sincronizar el contenido con el textarea original
                if (editor.id === 'post-copy') {
                    updateCopyPreview();
                    markDirty();
                }
            });
        }
    });

    // Detectar cambios de tema para actualizar el editor (opcional, requeriría recargar o destrozar la instancia)
});
</script>
<!-- ========== STUDIO MODE (Redesigned Presentation) ========== -->
<style>
/* ===== Studio Mode CSS ===== */
.studio-overlay {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: #0a0e1a; z-index: 1080; display: none; flex-direction: column;
    font-family: 'Inter', 'Segoe UI', sans-serif;
}
.studio-overlay.active { display: flex; }

/* Top Header Bar */
.studio-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 1.5rem; height: 56px; min-height: 56px;
    background: #0d1117; border-bottom: 1px solid rgba(255,255,255,0.06);
    z-index: 100;
}
.studio-header-left { display: flex; align-items: center; gap: 1rem; }
.studio-header-left img { height: 28px; }
.studio-header-title { color: #e6edf3; font-weight: 700; font-size: 1rem; }
.studio-header-sep { color: rgba(255,255,255,0.15); font-size: 1.2rem; font-weight: 300; }
.studio-status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 12px; border-radius: 20px; font-size: 0.75rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
}
.studio-header-date { color: #8b949e; font-size: 0.85rem; display: flex; align-items: center; gap: 6px; }
.studio-header-right { display: flex; align-items: center; gap: 0.75rem; }
.studio-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 8px; font-size: 0.8rem; font-weight: 600;
    cursor: pointer; border: 1px solid rgba(255,255,255,0.1); transition: all 0.2s;
    background: rgba(255,255,255,0.04); color: #c9d1d9;
}
.studio-btn:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.2); }
.studio-btn-primary {
    background: #238636 !important; border-color: #2ea043 !important; color: white !important;
}
.studio-btn-primary:hover { background: #2ea043 !important; }
.studio-btn-close {
    background: none; border: none; color: #8b949e; font-size: 1.3rem;
    cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center;
    justify-content: center; border-radius: 8px; transition: all 0.2s;
}
.studio-btn-close:hover { background: rgba(255,255,255,0.08); color: #e6edf3; }

/* Main Content */
.studio-body {
    flex: 1; display: flex; overflow: hidden; min-height: 0;
}

/* Left Column - Visual Preview */
.studio-preview {
    flex: 1.4; background: #161b22; display: flex; align-items: center;
    justify-content: center; position: relative; overflow: hidden;
    border-right: 1px solid rgba(255,255,255,0.06);
}
.studio-preview-inner {
    position: relative; width: 100%; height: 100%; display: flex;
    align-items: center; justify-content: center; padding: 2rem;
}
.studio-preview-inner img.studio-main-img {
    max-width: 100%; max-height: 100%; border-radius: 12px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.5); object-fit: contain;
    transition: transform 0.3s;
}
.studio-preview-inner .studio-grid-view {
    display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;
    width: 100%; max-width: 700px; max-height: 85vh; padding: 1rem;
}
.studio-preview-inner .studio-grid-view div {
    border-radius: 10px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    background: #1e293b;
}
.studio-preview-inner .studio-grid-view img {
    width: 100%; height: 100%; object-fit: cover; display: block;
}

/* Right Column - Details Panel */
.studio-details {
    flex: 1; background: #0d1117; color: #e6edf3; display: flex;
    flex-direction: column; overflow-y: auto; min-width: 380px; max-width: 520px;
}
.studio-details-scroll {
    flex: 1; overflow-y: auto; padding: 0.75rem 2rem 2rem 2rem;
}

/* Detail Sections */
.studio-section {
    margin-bottom: 1.5rem; background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06); border-radius: 10px;
    overflow: hidden;
}
.studio-section-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1px; color: #8b949e;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.studio-section-body { padding: 1rem; }
.studio-copy-text {
    font-size: 0.95rem; line-height: 1.7; color: #c9d1d9;
    white-space: pre-wrap;
}
.studio-ref-grid {
    display: flex; gap: 0.75rem; overflow-x: auto; padding-bottom: 0.5rem;
}
.studio-ref-grid img {
    height: 80px; width: 80px; object-fit: cover; border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.08); cursor: pointer;
    transition: transform 0.2s, border-color 0.2s;
}
.studio-ref-grid img:hover {
    transform: scale(1.08); border-color: #3b82f6;
}
.studio-ref-add {
    height: 80px; width: 80px; border-radius: 8px; display: flex;
    align-items: center; justify-content: center; font-size: 1.5rem;
    color: rgba(255,255,255,0.25); border: 2px dashed rgba(255,255,255,0.1);
    cursor: pointer; flex-shrink: 0; transition: all 0.2s;
}
.studio-ref-add:hover { border-color: #3b82f6; color: #3b82f6; }

/* Toolbar inside details */
.studio-toolbar {
    display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;
}
.studio-tool-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 6px; font-size: 0.78rem; font-weight: 600;
    cursor: pointer; border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.03); color: #8b949e; transition: all 0.15s;
}
.studio-tool-btn:hover { background: rgba(255,255,255,0.06); color: #c9d1d9; border-color: rgba(255,255,255,0.15); }
.studio-tool-btn.active { background: rgba(59,130,246,0.15); color: #58a6ff; border-color: rgba(59,130,246,0.3); }

/* Bottom Thumbnails Strip */
.studio-thumbstrip {
    height: 110px; min-height: 110px; background: #0d1117;
    border-top: 1px solid rgba(255,255,255,0.06);
    display: flex; flex-direction: column; z-index: 50;
}
.studio-thumbstrip-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 1rem; height: 28px; min-height: 28px;
}
.studio-thumbstrip-label {
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1px; color: #484f58;
}
.studio-thumbstrip-body {
    flex: 1; overflow: hidden; padding: 0 1rem 0.5rem;
}
.studio-thumbs .swiper-slide {
    width: 72px !important; height: 72px !important; flex-shrink: 0;
    border-radius: 8px; overflow: hidden; cursor: pointer;
    border: 2px solid transparent; transition: all 0.2s; opacity: 0.5;
}
.studio-thumbs .swiper-slide:hover { opacity: 0.8; }
.studio-thumbs .swiper-slide-thumb-active { border-color: #58a6ff !important; opacity: 1; box-shadow: 0 0 12px rgba(88,166,255,0.3); }
.studio-thumbs .swiper-slide img {
    width: 100%; height: 100%; object-fit: cover; display: block;
}
.studio-thumb-icon {
    width: 100%; height: 100%; display: flex; align-items: center;
    justify-content: center; background: #161b22; color: #484f58; font-size: 1.8rem;
}
.studio-thumb-label {
    position: absolute; bottom: 2px; left: 0; right: 0; text-align: center;
    font-size: 0.55rem; color: #8b949e; font-weight: 600;
    background: linear-gradient(transparent, rgba(0,0,0,0.8)); padding: 8px 2px 2px;
}

/* Post Agenda Modal (floating) */
.studio-agenda-modal {
    display: none; position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%); width: 450px; max-width: 90vw;
    max-height: 85vh; background: #161b22; border-radius: 12px;
    z-index: 1100; flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.7); border: 1px solid rgba(255,255,255,0.08);
}
.studio-agenda-modal.active { display: flex; }

/* History overlay */
.studio-history-overlay {
    display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(10, 14, 26, 0.95); z-index: 200; flex-direction: column;
    padding: 2rem; box-sizing: border-box; backdrop-filter: blur(10px);
}
.studio-history-overlay.active { display: flex; }

/* Portada / Cierre special slides */
.studio-special-slide {
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; width: 100%; text-align: center; padding: 2rem;
}

/* Info pills */
.studio-info-row {
    display: flex; gap: 1rem; margin-bottom: 1.5rem;
}
.studio-info-pill {
    display: flex; flex-direction: column; gap: 4px;
    background: rgba(255,255,255,0.03); padding: 0.6rem 1rem;
    border-radius: 8px; border: 1px solid rgba(255,255,255,0.06);
}
.studio-info-pill-label {
    font-size: 0.65rem; text-transform: uppercase; color: #484f58;
    font-weight: 700; letter-spacing: 0.5px;
}
.studio-info-pill-value {
    font-size: 0.9rem; font-weight: 600; color: #e6edf3;
    display: flex; align-items: center; gap: 4px;
}

/* Scrollbar styling */
.studio-details-scroll::-webkit-scrollbar { width: 6px; }
.studio-details-scroll::-webkit-scrollbar-track { background: transparent; }
.studio-details-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
.studio-details-scroll::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

/* ===== STUDIO VIDEO PLAYER ===== */
.studio-video-outer {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    position: relative;
}
.studio-video-card {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 24px 64px rgba(0,0,0,0.65), 0 0 0 1px rgba(255,255,255,0.05);
    background: #0d1117;
    display: flex;
    flex-direction: column;
}
.studio-video-card.ratio-vertical  { width: min(340px, 48vw); aspect-ratio: 9/16; }
.studio-video-card.ratio-horizontal { width: min(640px, 80vw); aspect-ratio: 16/9; }
.studio-video-card.ratio-square     { width: min(480px, 60vw); aspect-ratio: 1/1; }
.studio-video-platform-bar {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px;
    background: rgba(255,255,255,0.04);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
    font-family: 'Inter', sans-serif;
    font-size: 0.78rem; font-weight: 700;
    color: rgba(255,255,255,0.7);
    letter-spacing: 0.3px;
}
.studio-video-platform-bar .plat-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.studio-video-platform-bar a {
    margin-left: auto; color: rgba(255,255,255,0.35);
    font-size: 0.7rem; text-decoration: none; display: flex; align-items: center; gap: 4px;
    transition: color 0.2s;
}
.studio-video-platform-bar a:hover { color: rgba(255,255,255,0.7); }
.studio-video-embed-wrap {
    flex: 1; position: relative; overflow: hidden;
}
.studio-video-embed-wrap iframe,
.studio-video-embed-wrap video {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%;
    border: none;
}
.studio-video-embed-wrap video {
    object-fit: contain;
    background: #0d1117;
}
/* Glow effect per platform */
.studio-video-card[data-platform="youtube"]   { box-shadow: 0 24px 64px rgba(255,0,0,0.18), 0 0 0 1px rgba(255,255,255,0.05); }
.studio-video-card[data-platform="tiktok"]    { box-shadow: 0 24px 64px rgba(105,201,208,0.15), 0 0 0 1px rgba(255,255,255,0.05); }
.studio-video-card[data-platform="instagram"] { box-shadow: 0 24px 64px rgba(225,48,108,0.15), 0 0 0 1px rgba(255,255,255,0.05); }
.studio-video-card[data-platform="drive"]     { box-shadow: 0 24px 64px rgba(66,133,244,0.18), 0 0 0 1px rgba(255,255,255,0.05); }
.studio-video-card[data-platform="mp4"]       { box-shadow: 0 24px 64px rgba(88,166,255,0.14), 0 0 0 1px rgba(255,255,255,0.05); }
/* Loading shimmer */
.studio-video-loading {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 12px; color: rgba(255,255,255,0.25); font-family: 'Inter', sans-serif;
    font-size: 0.8rem; pointer-events: none;
    animation: studioFadeOut 0.5s ease 1.5s forwards;
}
@keyframes studioFadeOut { to { opacity: 0; } }

/* ===== SOCIAL PREVIEW CARD (Instagram / Facebook) ===== */
.studio-social-card {
    position: relative;
    width: min(300px, 46vw);
    aspect-ratio: 9/16;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 28px 70px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.07);
    display: flex;
    flex-direction: column;
}
.studio-social-bg {
    position: absolute; inset: 0;
    opacity: 0.18;
    filter: blur(40px);
}
.studio-social-content {
    position: relative; z-index: 2;
    flex: 1;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 1.5rem; padding: 2rem 1.5rem;
    text-align: center;
    background: rgba(10,10,15,0.72);
    backdrop-filter: blur(12px);
}
.studio-social-badge {
    display: inline-flex; align-items: center; gap: 7px;
    font-size: 0.75rem; font-weight: 800; letter-spacing: 1px;
    text-transform: uppercase; color: rgba(255,255,255,0.9);
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    padding: 5px 14px; border-radius: 30px;
}
.studio-social-badge i { font-size: 1rem; }
.studio-social-play-ring {
    width: 90px; height: 90px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.15);
    display: flex; align-items: center; justify-content: center;
    animation: socialPulse 2s ease-in-out infinite;
    position: relative;
}
.studio-social-play-ring::before {
    content: ''; position: absolute;
    width: 110px; height: 110px; border-radius: 50%;
    border: 1px solid rgba(255,255,255,0.06);
    animation: socialPulse 2s ease-in-out infinite 0.4s;
}
.studio-social-play-inner {
    width: 68px; height: 68px; border-radius: 50%;
    background: rgba(255,255,255,0.12);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s, transform 0.2s;
}
.studio-social-play-inner:hover {
    background: rgba(255,255,255,0.22);
    transform: scale(1.05);
}
@keyframes socialPulse {
    0%,100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.08); opacity: 0.6; }
}
.studio-social-info { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.studio-social-notice {
    display: flex; align-items: center; gap: 5px;
    font-size: 0.72rem; color: rgba(255,255,255,0.4);
    font-family: 'Inter', sans-serif;
}
.studio-social-url {
    font-size: 0.65rem; color: rgba(255,255,255,0.25);
    word-break: break-all; max-width: 220px; line-height: 1.3;
    background: rgba(255,255,255,0.04); border-radius: 6px;
    padding: 4px 8px; border: 1px solid rgba(255,255,255,0.07);
}
.studio-social-cta {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 24px; border-radius: 30px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.2);
    color: white; text-decoration: none;
    font-weight: 700; font-size: 0.88rem;
    font-family: 'Inter', sans-serif;
    transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
    backdrop-filter: blur(8px);
}
.studio-social-cta:hover {
    background: rgba(255,255,255,0.22);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
}
/* ===== STUDIO CALENDAR ===== */
.studio-calendar-wrap .fc {
    --fc-page-bg-color: transparent;
    --fc-neutral-bg-color: #161b22;
    --fc-neutral-text-color: #8b949e;
    --fc-border-color: rgba(255,255,255,0.08);
    --fc-event-bg-color: transparent;
    --fc-event-border-color: transparent;
    --fc-today-bg-color: rgba(88, 166, 255, 0.05);
    font-family: 'Inter', sans-serif;
    color: #c9d1d9;
}
.studio-calendar-wrap .fc-toolbar-title {
    font-size: 1.5rem !important;
    font-weight: 800;
    color: #e6edf3;
    text-transform: capitalize;
}
.studio-calendar-wrap .fc-col-header-cell-cushion {
    color: #8b949e;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.8rem;
    padding: 0.8rem !important;
}
.studio-calendar-wrap .fc-daygrid-day-number {
    color: #e6edf3;
    font-weight: 600;
    font-size: 0.9rem;
    padding: 0.5rem !important;
}
.studio-calendar-wrap .fc-event {
    cursor: grab;
}
.studio-calendar-wrap .fc-event:active {
    cursor: grabbing;
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
            <label id="studio-reviewed-header" class="studio-btn" style="cursor:pointer; display:none; gap:6px; font-weight:600; font-size:0.8rem;">
                <input type="checkbox" id="studio-reviewed-checkbox" onchange="toggleReviewedFromHeader(this.checked)" style="accent-color: #3fb950; width:15px; height:15px; cursor:pointer;">
                Revisado
            </label>
            <button class="studio-btn" onclick="toggleDrawingToolStudio()" id="studio-draw-btn">
                <i class="ph ph-pencil-simple"></i> Dibujar
            </button>
            <button class="studio-btn" onclick="clearDrawingStudio()" id="studio-clear-draw-btn" style="display:none; color: #f85149; border-color: rgba(248,81,73,0.3);">
                <i class="ph ph-eraser"></i> Limpiar Dibujo
            </button>
            <button class="studio-btn" onclick="addStickyNote()" id="studio-note-btn" style="display:none;">
                <i class="ph ph-note"></i> Nota
            </button>
            <button class="studio-btn studio-btn-primary" onclick="finalizeStudioPost()" id="studio-finalize-btn" style="display:none;">
                Finalizar Publicación
            </button>
            <button class="studio-btn studio-btn-primary" onclick="alert('¡Listo! Todos los movimientos de fecha en el calendario se guardan automáticamente.');" id="studio-calendar-save-btn" style="display:none;">
                Guardar Calendario
            </button>
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
            <span class="studio-thumbstrip-label" id="studio-counter" style="color: #58a6ff;">
                1 / <?php echo count($posts) + 2; ?>
            </span>
        </div>
        <div class="studio-thumbstrip-body">
            <div class="swiper studio-thumbs" id="studio-thumbs-swiper">
                <div class="swiper-wrapper">
                    <!-- Portada Thumb -->
                    <div class="swiper-slide" data-slide-type="portada" data-slide-index="0" onclick="goToStudioSlide(0)">
                        <div class="studio-thumb-icon"><i class="ph ph-house"></i></div>
                    </div>
                    
                    <?php if (!empty($posts)): ?>
                    <?php foreach ($posts as $idx => $p):
                        $mediaStr = $p['post_type'] === 'Referencia Visual' ? $p['reference_image_link'] : $p['image_link'];
                        $mediaList = json_decode($mediaStr, true);
                        if (!is_array($mediaList) && !empty($mediaStr)) { $mediaList = [$mediaStr]; }
                        $thumbSrc = (!empty($mediaList) && !empty($mediaList[0])) ? $mediaList[0] : '';
                    ?>
                    <div class="swiper-slide" data-slide-type="post" data-slide-index="<?php echo $idx + 1; ?>" data-post-id="<?php echo $p['id']; ?>" onclick="goToStudioSlide(<?php echo $idx + 1; ?>)">
                        <?php if ($thumbSrc && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $thumbSrc)): ?>
                            <img src="<?php echo htmlspecialchars($thumbSrc); ?>">
                        <?php else: ?>
                            <div class="studio-thumb-icon">
                                <i class="ph ph-<?php echo ($thumbSrc ? 'video-camera' : 'image-square'); ?>"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Calendar Thumb -->
                    <div class="swiper-slide" data-slide-type="calendar" data-slide-index="<?php echo count($posts) + 1; ?>" onclick="goToStudioSlide(<?php echo count($posts) + 1; ?>)">
                        <div class="studio-thumb-icon"><i class="ph ph-calendar-check" style="color: #58a6ff;"></i></div>
                    </div>

                    <!-- Cierre Thumb -->
                    <div class="swiper-slide" data-slide-type="cierre" data-slide-index="<?php echo count($posts) + 2; ?>" onclick="goToStudioSlide(<?php echo count($posts) + 2; ?>)">
                        <div class="studio-thumb-icon"><i class="ph ph-check-circle" style="color: #3fb950;"></i></div>
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
// All posts data as JS array
const studioPosts = <?php echo json_encode($posts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const studioLogo = <?php echo json_encode(htmlspecialchars($logo)); ?>;
const studioBrand = <?php echo json_encode(htmlspecialchars($monthData['brand_name'])); ?>;
const studioMonthLabel = <?php echo json_encode($meses[$monthData['month']-1] . ' ' . $monthData['year']); ?>;
const studioTotalSlides = studioPosts.length + 3; // portada + posts + calendar + cierre
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
};

function startPresentation() {
    document.getElementById('presentation-modal').classList.add('active');
    document.body.style.overflow = 'hidden';
    
    if (!studioThumbsSwiper) {
        studioThumbsSwiper = new Swiper('#studio-thumbs-swiper', {
            slidesPerView: 'auto',
            spaceBetween: 10,
            freeMode: true,
            watchSlidesProgress: true,
        });
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
}

function goToStudioSlide(idx) {
    if (studioUnsavedChanges) {
        if (!confirm("Tienes cambios sin guardar en esta publicación. ¿Deseas descartarlos y cambiar?")) return;
    }
    studioUnsavedChanges = false; // Reset for new slide
    studioCurrentSlide = idx;
    
    // Update counter
    document.getElementById('studio-counter').textContent = (idx + 1) + ' / ' + studioTotalSlides;
    
    // Highlight active thumb
    const allThumbs = document.querySelectorAll('#studio-thumbs-swiper .swiper-slide');
    allThumbs.forEach((t, i) => {
        t.classList.toggle('swiper-slide-thumb-active', i === idx);
    });
    
    // Scroll thumb into view
    if (studioThumbsSwiper && studioThumbsSwiper.slides[idx]) {
        studioThumbsSwiper.slideTo(Math.max(0, idx - 2));
    }
    
    const preview = document.getElementById('studio-preview-inner');
    const detailsScroll = document.getElementById('studio-details-scroll');
    const detailsPanel = document.getElementById('studio-details');
    
    // Most slides show details, Calendar hides it for full width
    detailsPanel.style.display = (idx === studioTotalSlides - 2) ? 'none' : 'flex';
    
    if (idx === 0) {
        // PORTADA
        renderPortada(preview, detailsScroll);
    } else if (idx === studioTotalSlides - 2) {
        // CALENDARIO
        renderCalendarSlide(preview);
    } else if (idx === studioTotalSlides - 1) {
        // CIERRE
        renderCierre(preview, detailsScroll);
    } else {
        // POST
        const post = studioPosts[idx - 1];
        renderPost(post, preview, detailsScroll);
        initAgendaForPost(post);
        
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
    // TikTok short link (vm.tiktok.com) — embed directly, TikTok will handle the redirect
    if (/vm\.tiktok\.com|tiktok\.com/.test(url)) return {
        platform: 'tiktok', name: 'TikTok',
        icon: 'ph-tiktok-logo', color: '#69C9D0',
        embedUrl: url,
        ratio: 'vertical', type: 'iframe'
    };

    // Instagram Reel or Post (Meta blocks in-page playback → social card)
    if (/instagram\.com\/(?:p|reel|tv)\//i.test(url)) return {
        platform: 'instagram', name: 'Instagram Reel',
        icon: 'ph-instagram-logo', color: '#E1306C',
        gradient: 'linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888)',
        ratio: 'vertical', type: 'social-card'
    };

    // Facebook Reel / Video / fb.watch (Meta blocks in-page playback → social card)
    if (/facebook\.com\/(reel|watch|share\/r|share\/v|video)|fb\.watch/i.test(url)) return {
        platform: 'facebook', name: 'Facebook Reel',
        icon: 'ph-facebook-logo', color: '#1877F2',
        gradient: 'linear-gradient(135deg,#1877F2,#0a5fd8)',
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
    // === Social preview card (Instagram / Facebook — Meta blocks in-page playback) ===
    if (info.type === 'social-card') {
        const shortUrl = originalUrl.length > 55 ? originalUrl.slice(0, 52) + '…' : originalUrl;
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
               <span>Cargando ${info.name}…</span>
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

    updateHeader('Portada', null, studioMonthLabel, 'PORTADA', {bg:'rgba(59,130,246,0.15)',color:'#58a6ff'});
    
    preview.innerHTML = `
        <div class="studio-special-slide">
            <img src="${studioLogo}" alt="Logo" style="max-height: 140px; max-width: 60%; margin-bottom: 2.5rem; opacity: 0.9;">
            <h1 style="color: #e6edf3; font-size: 3rem; font-weight: 800; margin: 0 0 0.5rem;">${studioBrand}</h1>
            <div style="color: #58a6ff; font-size: 1.5rem; font-weight: 600; text-transform: uppercase; letter-spacing: 3px;">${studioMonthLabel}</div>
        </div>
    `;
    
    details.innerHTML = `
        <div style="color: #484f58; text-align: center; padding-top: 4rem;">
            <i class="ph ph-presentation-chart" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
            <p style="font-size: 1rem; margin: 0;">Diapositiva de Portada</p>
            <p style="font-size: 0.85rem; color: #30363d; margin-top: 0.5rem;">Navega con las flechas ← → o haz clic en las miniaturas.</p>
        </div>
    `;
    
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

    const monthStr = <?php echo json_encode(str_pad($monthData['month'], 2, '0', STR_PAD_LEFT)); ?>;
    const yearStr = <?php echo json_encode($monthData['year']); ?>;
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
                    alert('Error al actualizar: ' + data.error);
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
                alert('Error de conexión');
                info.revert();
            }
        }
    });

    calendar.render();
}

function renderCierre(preview, details) {
    updateHeader('Cierre', null, '', 'CIERRE', {bg:'rgba(16,185,129,0.15)',color:'#3fb950'});
    
    const agencyLogo = <?php echo json_encode(htmlspecialchars($global_settings['logo_dark'] ?? 'assets/img/default-logo.png')); ?>;
    
    preview.innerHTML = `
        <div class="studio-special-slide">
            <h2 style="color: #e6edf3; font-size: 3.5rem; font-weight: 800; margin-bottom: 1rem;">¡Gracias!</h2>
            <p style="color: #8b949e; font-size: 1.3rem; margin-bottom: 3rem; max-width: 550px;">Esperamos tus comentarios para proceder con la aprobación y programación del contenido.</p>
            <img src="${agencyLogo}" alt="Agency Logo" style="max-height: 50px; max-width: 80%; opacity: 0.6;">
        </div>
    `;
    
    details.innerHTML = `
        <div style="color: #484f58; text-align: center; padding-top: 4rem;">
            <i class="ph ph-check-circle" style="font-size: 3rem; margin-bottom: 1rem; display: block; color: #3fb950;"></i>
            <p style="font-size: 1rem; margin: 0;">Diapositiva de Cierre</p>
        </div>
    `;
    
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
            alert('Error al actualizar: ' + data.error);
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
        alert('Error de conexión');
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
        previewHTML = '<div class="studio-grid-view">';
        mediaList.forEach((img, idx) => {
            const isImgFile = /\.(jpg|jpeg|png|gif|webp)$/i.test(img);
            const clickAttr = isImgFile ? `onclick="openLightbox(${JSON.stringify(mediaList)}, ${idx})" style="cursor:zoom-in;" title="Click para hacer zoom"` : '';
            previewHTML += `<div><img src="${escHtml(img)}" ${clickAttr}></div>`;
        });
        previewHTML += '</div>';
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
            // Unknown URL — premium external link card
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
        
        <!-- Agenda Card -->
        <div class="studio-section">
            <div class="studio-section-header">
                <span><i class="ph ph-list-checks" style="color: #58a6ff;"></i> Agenda</span>
            </div>
            <div class="studio-section-body" style="background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 8px;">
                <input type="hidden" id="agenda-current-post-id" value="${post.id}">
                <textarea id="post-agenda-notes" oninput="studioUnsavedChanges = true" 
                    style="width: 100%; height: 80px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; color: #e6edf3; padding: 0.6rem; resize: none; font-family: inherit; font-size: 0.85rem; box-sizing: border-box; outline: none; margin-bottom: 0.8rem;" 
                    placeholder="Escribe notas para este post aquí..."></textarea>
                
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.8rem;">
                    <input type="text" id="agenda-new-task" placeholder="Nueva tarea..." 
                        style="flex: 1; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; color: #e6edf3; padding: 0.4rem 0.6rem; font-size: 0.85rem; outline: none;" 
                        onkeypress="if(event.key === 'Enter') addAgendaTask()">
                    <button onclick="addAgendaTask()" style="background: #238636; color: white; border: none; border-radius: 6px; padding: 0 0.8rem; cursor: pointer;"><i class="ph ph-plus"></i></button>
                </div>
                
                <div id="agenda-tasks-container" style="display: flex; flex-direction: column; gap: 0.4rem;"></div>
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
        // If lightbox is open, close only the lightbox — don't close the presentation
        const lb = document.getElementById('studio-lightbox');
        if (lb && lb.style.display !== 'none') { closeLightbox(); return; }
        closePresentation();
        return;
    }
    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        if (studioCurrentSlide < studioTotalSlides - 1) goToStudioSlide(studioCurrentSlide + 1);
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
}

function openPresenterNotes(postId) {
    const url = 'modules/month_board/presenter_notes.php?post_id=' + postId;
    window.open(url, 'PresenterNotes', 'width=400,height=500,left=100,top=100,menubar=no,toolbar=no,location=no,status=no');
}

function showHistory(postId) {
    const el = document.getElementById('history-modal-' + postId);
    if (el) { el.classList.add('active'); }
    else { alert('No hay versiones anteriores.'); }
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
    alert('Publicacion guardada exitosamente.');
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
    <div class="modal-content" style="max-width: 600px; text-align: center;">
        <div class="modal-header" style="justify-content: center; border-bottom: none; padding-bottom: 0;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto;">
                <i class="ph ph-upload-simple"></i>
            </div>
            <button class="btn-icon" onclick="document.getElementById('upload-drive-modal').classList.remove('active')" style="position: absolute; right: 1rem; top: 1rem;">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body" style="padding-top: 1rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--color-title); margin-bottom: 0.5rem;">Subir Archivos</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem;">
                Sube archivos pesados (>2 GB) directamente a las carpetas de Google Drive del proyecto. La subida se hace en segundo plano para no interrumpir tu trabajo.
            </p>
            
            <div style="text-align: left; margin-bottom: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">1. Selecciona la carpeta destino:</label>
                <select id="drive-upload-folder-select" class="form-control" style="margin-top: 0.5rem; font-weight: 600;">
                    <!-- Options populated via JS -->
                </select>
            </div>
            
            <div style="text-align: left; margin-bottom: 1rem;">
                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">2. Arrastra los archivos aquí:</label>
            </div>
            
            <div id="drive-dropzone" style="border: 2px dashed var(--border-color); border-radius: 12px; padding: 3rem 1rem; background: var(--bg-surface); cursor: pointer; transition: all 0.2s; position: relative;" ondragover="this.style.borderColor='#3b82f6'; this.style.background='#eff6ff'; event.preventDefault();" ondragleave="this.style.borderColor='var(--border-color)'; this.style.background='var(--bg-surface)';" ondrop="handleDriveDrop(event)">
                <i class="ph ph-tray-arrow-down" style="font-size: 3rem; color: #94a3b8; margin-bottom: 1rem;"></i>
                <div style="font-weight: 600; color: var(--color-title); font-size: 1.1rem;">Arrastra y suelta tus archivos</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem;">o haz clic para explorar</div>
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
            select.innerHTML += `<option value="${driveFoldersData.root_folder.id}">[Carpeta Principal] ${driveFoldersData.root_folder.name}</option>`;
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
        alert('Por favor selecciona una carpeta destino.');
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
                response = await uploadChunk(uploadUrl, chunk, start, end, file.size);
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

function uploadChunk(uploadUrl, chunk, start, end, totalSize) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', uploadUrl, true);
        xhr.setRequestHeader('Content-Range', `bytes ${start}-${end - 1}/${totalSize}`);
        
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
</script>

<?php require_once 'includes/footer.php'; ?>
