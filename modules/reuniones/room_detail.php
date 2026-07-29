<?php
// modules/reuniones/room_detail.php
// Detalle de una Sala de Reunión con historial de grabaciones y presencia en vivo
require_once 'includes/header.php';

global $db;
$id = $_GET['id'] ?? 0;

$sql = "SELECT mr.*, u.name as creator_name 
        FROM meeting_rooms mr 
        LEFT JOIN users u ON mr.created_by = u.id 
        WHERE mr.id = ? AND mr.is_active = 1";
$stmt = $db->prepare($sql);
$stmt->execute([$id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    echo "<div class='card' style='padding:2rem; text-align:center;'><h2>Sala no encontrada</h2><a href='index.php?module=reuniones&action=rooms'>Volver a Salas</a></div>";
    require_once 'includes/footer.php';
    exit();
}

// Fetch recordings
$sqlRec = "SELECT mrr.*, u.name as recorder_name 
           FROM meeting_room_recordings mrr 
           LEFT JOIN users u ON mrr.recorded_by = u.id 
           WHERE mrr.room_id = ? 
           ORDER BY mrr.recorded_at DESC";
$stmtRec = $db->prepare($sqlRec);
$stmtRec->execute([$id]);
$recordings = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
?>

<style>
    /* ========= ANIMATIONS ========= */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes scale-in { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    @keyframes live-dot { 0%,100% { transform: scale(1); } 50% { transform: scale(1.3); } }
    @keyframes live-pulse { 
        0% { box-shadow: 0 0 0 0 var(--room-glow); } 
        70% { box-shadow: 0 0 0 12px transparent; } 
        100% { box-shadow: 0 0 0 0 transparent; } 
    }
    @keyframes float-icon { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }

    /* ========= LAYOUT ========= */
    .rd-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 1.5rem;
        align-items: start;
        --room-color: <?php echo htmlspecialchars($room['color']); ?>;
        --room-glow: color-mix(in srgb, var(--room-color) 40%, transparent);
    }

    /* ========= HEADER ========= */
    .rd-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.75rem;
        background: linear-gradient(135deg, color-mix(in srgb, var(--room-color) 6%, transparent), color-mix(in srgb, var(--room-color) 2%, transparent) 70%, transparent);
        border-radius: 16px;
        border: 1px solid var(--border-color);
        animation: fadeInUp 0.4s ease-out;
        grid-column: 1 / -1;
    }
    .rd-header-left {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
        min-width: 0;
    }
    .rd-back-btn {
        color: var(--text-muted);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        font-size: 1.1rem;
        flex-shrink: 0;
        transition: all 0.2s ease;
    }
    .rd-back-btn:hover { border-color: var(--room-color); color: var(--room-color); }
    .rd-room-icon {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        background: var(--room-color);
        flex-shrink: 0;
        box-shadow: 0 4px 12px color-mix(in srgb, var(--room-color) 30%, transparent);
    }
    .rd-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-title);
        margin: 0;
    }
    .rd-subtitle {
        color: var(--text-muted);
        margin: 0.25rem 0 0 0;
        font-size: 0.85rem;
    }
    .rd-header-actions {
        display: flex;
        gap: 0.4rem;
        flex-shrink: 0;
    }
    .rd-header-actions .btn {
        border-radius: 10px;
        font-size: 0.8rem;
        padding: 0.5rem 0.85rem;
        transition: all 0.2s ease;
    }
    .rd-header-actions .btn:hover { transform: translateY(-1px); }

    /* ========= LIVE STATUS PANEL ========= */
    .rd-live-panel {
        padding: 1.25rem;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        background: var(--bg-surface);
        animation: fadeInUp 0.5s ease-out 0.1s both;
    }
    .rd-live-panel.is-live {
        border-color: var(--room-color);
        animation: fadeInUp 0.5s ease-out 0.1s both, live-pulse 2s infinite;
    }
    .rd-live-panel h3 {
        margin: 0 0 1rem 0;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--color-title);
    }
    .rd-live-badge {
        display: none;
        align-items: center;
        gap: 0.3rem;
        padding: 0.2rem 0.55rem;
        background: #fef2f2;
        color: #dc2626;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .rd-live-badge .dot {
        width: 6px; height: 6px;
        background: #ef4444;
        border-radius: 50%;
        animation: live-dot 1s ease-in-out infinite;
    }
    .rd-live-panel.is-live .rd-live-badge { display: inline-flex; }
    [data-theme="dark"] .rd-live-badge { background: rgba(239,68,68,0.15); color: #f87171; }

    .rd-live-members {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }
    .rd-live-member {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.5rem;
        border-radius: 10px;
        transition: background 0.15s ease;
    }
    .rd-live-member:hover { background: var(--bg-color); }
    .rd-live-member-avatar {
        width: 36px; height: 36px;
        border-radius: 50%;
        background: var(--room-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        overflow: hidden;
        flex-shrink: 0;
    }
    .rd-live-member-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .rd-live-member-name { font-weight: 600; font-size: 0.9rem; color: var(--color-title); }
    .rd-live-empty {
        text-align: center;
        padding: 1.5rem;
        color: var(--text-muted);
        font-size: 0.85rem;
    }
    .rd-live-empty i { font-size: 2rem; margin-bottom: 0.5rem; display: block; color: var(--border-color); }

    /* Join button */
    .rd-join-btn {
        width: 100%;
        margin-top: 1rem;
        padding: 0.65rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        background: var(--room-color) !important;
        color: white !important;
        border: none;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .rd-join-btn:hover { box-shadow: 0 4px 15px color-mix(in srgb, var(--room-color) 40%, transparent); transform: translateY(-1px); }

    /* ========= PUBLIC LINK ========= */
    .rd-public-link {
        padding: 1rem 1.25rem;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--bg-color);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 0.75rem;
    }
    .rd-public-link input {
        flex: 1;
        border: none;
        background: transparent;
        font-size: 0.8rem;
        color: var(--text-muted);
        outline: none;
        font-family: monospace;
    }
    .rd-public-link button {
        border: none;
        background: var(--primary-color);
        color: white;
        padding: 0.4rem 0.75rem;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .rd-public-link button:hover { opacity: 0.9; }

    /* ========= RECORDINGS LIST ========= */
    .rd-recordings {
        animation: fadeInUp 0.5s ease-out 0.15s both;
    }
    .rd-recordings-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
    }
    .rd-recordings-header h2 {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--color-title);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .rd-recordings-count {
        background: color-mix(in srgb, var(--primary-color) 10%, transparent);
        color: var(--primary-color);
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.2rem 0.6rem;
        border-radius: 8px;
    }

    .rd-rec-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        border: 1px solid var(--border-color);
        border-radius: 14px;
        background: var(--bg-surface);
        margin-bottom: 0.75rem;
        transition: all 0.2s ease;
    }
    .rd-rec-item:hover {
        border-color: color-mix(in srgb, var(--room-color) 30%, var(--border-color));
        transform: translateX(3px);
        box-shadow: var(--shadow-sm);
    }
    .rd-rec-thumb {
        width: 120px;
        height: 68px;
        border-radius: 10px;
        overflow: hidden;
        background: #0f172a;
        flex-shrink: 0;
        position: relative;
        cursor: pointer;
    }
    .rd-rec-thumb iframe {
        width: 100%; height: 100%; border: 0;
        pointer-events: none;
        transform: scale(1.01);
    }
    .rd-rec-thumb-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }
    .rd-rec-item:hover .rd-rec-thumb-overlay { background: rgba(0,0,0,0.2); }
    .rd-rec-play {
        width: 36px; height: 36px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: #0f172a;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        transition: transform 0.2s;
    }
    .rd-rec-item:hover .rd-rec-play { transform: scale(1.1); }

    .rd-rec-info { flex: 1; min-width: 0; }
    .rd-rec-title {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--color-title);
        margin: 0 0 0.2rem 0;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .rd-rec-meta {
        font-size: 0.8rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .rd-rec-meta span {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    .rd-rec-actions {
        display: flex;
        gap: 0.35rem;
        flex-shrink: 0;
    }
    .rd-rec-actions .icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px; height: 34px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        background: transparent;
        cursor: pointer;
        transition: all 0.15s ease;
        font-size: 1rem;
        color: var(--text-muted);
        text-decoration: none;
    }
    .rd-rec-actions .icon-btn:hover { background: var(--bg-color); color: var(--color-title); }

    /* ========= ADD RECORDING FORM ========= */
    .rd-add-rec {
        padding: 1.25rem;
        border-radius: 14px;
        border: 1px dashed var(--border-color);
        background: color-mix(in srgb, var(--bg-color) 50%, transparent);
        margin-top: 0.5rem;
        display: none;
        animation: fadeInUp 0.3s ease-out;
    }
    .rd-add-rec.show { display: block; }
    .rd-add-rec h4 {
        margin: 0 0 1rem 0;
        font-size: 0.95rem;
        color: var(--color-title);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .rd-add-rec .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .rd-add-rec .form-control {
        border-radius: 10px;
    }
    .rd-add-rec .form-full { grid-column: 1 / -1; }

    /* ========= EMPTY STATE ========= */
    .rd-rec-empty {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-muted);
    }
    .rd-rec-empty i { font-size: 3rem; color: var(--border-color); margin-bottom: 0.75rem; animation: float-icon 3s ease-in-out infinite; }
    .rd-rec-empty h3 { margin: 0 0 0.25rem 0; font-size: 1rem; color: var(--color-title); }
    .rd-rec-empty p { margin: 0; font-size: 0.85rem; }

    /* ========= VIDEO THEATER ========= */
    .rd-theater {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.92);
        z-index: 10001;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        backdrop-filter: blur(8px);
    }
    .rd-theater.active { display: flex; animation: scale-in 0.3s ease-out; }
    .rd-theater-header {
        width: 90%; max-width: 960px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
    }
    .rd-theater-header h3 { color: #fff; margin: 0; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    .rd-theater-close {
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        color: #fff;
        width: 38px; height: 38px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .rd-theater-close:hover { background: rgba(255,255,255,0.2); }
    .rd-theater-body {
        width: 90%; max-width: 960px;
        aspect-ratio: 16/9;
        background: #000;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 25px 60px rgba(0,0,0,0.5);
    }
    .rd-theater-body iframe { width: 100%; height: 100%; border: 0; }

    /* ========= RESPONSIVE ========= */
    @media (max-width: 768px) {
        .rd-layout { grid-template-columns: 1fr; }
        .rd-header { flex-direction: column; gap: 1rem; grid-column: 1; }
        .rd-header-actions { width: 100%; }
        .rd-header-actions .btn { flex: 1; }
        .rd-rec-item { flex-direction: column; align-items: stretch; text-align: center; }
        .rd-rec-thumb { width: 100%; height: 160px; }
        .rd-rec-actions { justify-content: center; }
        .rd-add-rec .form-row { grid-template-columns: 1fr; }
    }
</style>

<div class="rd-layout">
    <!-- Header (spans full width) -->
    <div class="rd-header">
        <div class="rd-header-left">
            <a href="index.php?module=reuniones&action=rooms" class="rd-back-btn" title="Volver a Salas">
                <i class="ph ph-arrow-left"></i>
            </a>
            <div class="rd-room-icon" style="background: <?php echo htmlspecialchars($room['color']); ?>;">
                <i class="ph ph-<?php echo htmlspecialchars($room['icon']); ?>"></i>
            </div>
            <div style="min-width:0;">
                <h1 class="rd-title"><?php echo htmlspecialchars($room['name']); ?></h1>
                <p class="rd-subtitle">
                    <?php if($room['description']): ?>
                        <?php echo htmlspecialchars($room['description']); ?>
                    <?php else: ?>
                        Sala de reuniones · Creada por <?php echo htmlspecialchars($room['creator_name'] ?? 'Sistema'); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="rd-header-actions">
            <button onclick="openEditRoomFromDetail()" class="btn btn-outline" style="border-color: var(--primary-color); color: var(--primary-color);">
                <i class="ph ph-pencil-simple"></i> Editar
            </button>
            <button onclick="deleteRoomFromDetail(<?php echo $room['id']; ?>, '<?php echo addslashes($room['name']); ?>')" class="btn btn-outline" style="border-color: #ef4444; color: #ef4444;">
                <i class="ph ph-trash"></i> Eliminar
            </button>
        </div>
    </div>

    <!-- Main: Recordings -->
    <div class="rd-recordings">
        <div class="card" style="padding: 1.5rem;">
            <div class="rd-recordings-header">
                <h2>
                    <i class="ph ph-play-circle" style="color: var(--room-color);"></i> Historial de Grabaciones
                    <span class="rd-recordings-count"><?php echo count($recordings); ?></span>
                </h2>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-primary" id="btn-sync-recordings" style="border-radius: 10px; font-size: 0.8rem; background: var(--primary-color); border-color: var(--primary-color);" onclick="syncRecordings()">
                        <i class="ph ph-arrows-clockwise"></i> Sincronizar
                    </button>
                    <button class="btn btn-outline" style="border-radius: 10px; font-size: 0.8rem;" onclick="toggleAddRecording()">
                        <i class="ph ph-plus"></i> Agregar
                    </button>
                </div>
            </div>

            <!-- Add Recording Form -->
            <div class="rd-add-rec" id="add-rec-form">
                <h4><i class="ph ph-plus-circle" style="color: var(--room-color);"></i> Agregar Grabación</h4>
                <form id="new-recording-form">
                    <input type="hidden" name="action" value="add_recording">
                    <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                    <div class="form-row">
                        <div class="form-full">
                            <label style="font-size:0.8rem; font-weight:600; margin-bottom:0.3rem;">Enlace de Grabación (Google Drive) *</label>
                            <input type="url" name="recording_link" class="form-control" placeholder="https://drive.google.com/file/d/..." required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; margin-bottom:0.3rem;">Título (opcional)</label>
                            <input type="text" name="title" class="form-control" placeholder="Ej: Reunión de estrategia">
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; margin-bottom:0.3rem;">Duración (opcional)</label>
                            <input type="text" name="duration" class="form-control" placeholder="45 min">
                        </div>
                    </div>
                    <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                        <button type="button" onclick="toggleAddRecording()" class="btn btn-outline" style="border-radius:10px; font-size:0.8rem;">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="add-rec-btn" style="border-radius:10px; font-size:0.8rem; background: var(--room-color); border-color: var(--room-color);">
                            <i class="ph ph-plus"></i> Agregar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Recordings List -->
            <?php if(empty($recordings)): ?>
                <div class="rd-rec-empty">
                    <i class="ph ph-film-strip"></i>
                    <h3>Sin grabaciones aún</h3>
                    <p>Agrega grabaciones de Google Drive para mantener un historial de esta sala.</p>
                </div>
            <?php else: ?>
                <?php foreach($recordings as $rec): 
                    $embed = str_replace('/view', '/preview', $rec['recording_link']);
                ?>
                <div class="rd-rec-item">
                    <div class="rd-rec-thumb" onclick="openTheater('<?php echo htmlspecialchars($embed); ?>', '<?php echo addslashes($rec['title'] ?? $room['name']); ?>')">
                        <iframe src="<?php echo htmlspecialchars($embed); ?>" loading="lazy"></iframe>
                        <div class="rd-rec-thumb-overlay">
                            <div class="rd-rec-play">
                                <i class="ph-fill ph-play"></i>
                            </div>
                        </div>
                    </div>
                    <div class="rd-rec-info">
                        <h4 class="rd-rec-title"><?php echo htmlspecialchars($rec['title'] ?: 'Grabación #' . $rec['id']); ?></h4>
                        <div class="rd-rec-meta">
                            <span><i class="ph ph-calendar-blank"></i> <?php echo date('d M, Y · h:i A', strtotime($rec['recorded_at'])); ?></span>
                            <?php if($rec['duration']): ?>
                                <span><i class="ph ph-timer"></i> <?php echo htmlspecialchars($rec['duration']); ?></span>
                            <?php endif; ?>
                            <?php if($rec['recorder_name']): ?>
                                <span><i class="ph ph-user"></i> <?php echo htmlspecialchars($rec['recorder_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="rd-rec-actions">
                        <button class="icon-btn" onclick="openTheater('<?php echo htmlspecialchars($embed); ?>', '<?php echo addslashes($rec['title'] ?? $room['name']); ?>')" title="Reproducir">
                            <i class="ph ph-play"></i>
                        </button>
                        <a href="<?php echo htmlspecialchars($rec['recording_link']); ?>" target="_blank" class="icon-btn" title="Abrir en Drive">
                            <i class="ph ph-arrow-square-out"></i>
                        </a>
                        <button class="icon-btn" onclick="deleteRecording(<?php echo $rec['id']; ?>)" title="Eliminar" style="color: #ef4444;">
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Live Panel -->
        <div class="rd-live-panel" id="rd-live-panel">
            <h3>
                <i class="ph ph-users" style="color: var(--room-color);"></i> En la Sala
                <div class="rd-live-badge">
                    <span class="dot"></span> EN VIVO
                </div>
            </h3>
            <div class="rd-live-members" id="rd-live-members">
                <div class="rd-live-empty">
                    <i class="ph ph-user-circle-dashed"></i>
                    Nadie está en la sala ahora
                </div>
            </div>
            
            <?php if($room['meet_link']): ?>
                <a href="<?php echo htmlspecialchars($room['meet_link']); ?>" target="_blank" class="btn rd-join-btn">
                    <i class="ph ph-video-camera"></i> Unirse a la Sala
                </a>
            <?php endif; ?>

            <!-- Public link -->
            <div class="rd-public-link">
                <i class="ph ph-link" style="color: var(--text-muted); font-size: 1.1rem; flex-shrink: 0;"></i>
                <input type="text" readonly id="public-link-input" value="<?php echo $base_url; ?>/public_room.php?slug=<?php echo htmlspecialchars($room['slug']); ?>">
                <button onclick="copyPublicLink()"><i class="ph ph-copy"></i> Copiar</button>
            </div>
        </div>
    </div>
</div>

<!-- Video Theater -->
<div class="rd-theater" id="rd-theater">
    <div class="rd-theater-header">
        <h3><i class="ph ph-play-circle"></i> <span id="theater-title"></span></h3>
        <button class="rd-theater-close" onclick="closeTheater()"><i class="ph ph-x"></i></button>
    </div>
    <div class="rd-theater-body">
        <iframe id="theater-iframe" src="" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
    </div>
</div>

<script>
// ========= PUSHER PRESENCE =========
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Pusher === 'undefined') return;

    const pusher = new Pusher('b31f38612d61b0285c78', {
        cluster: 'us2',
        authEndpoint: 'ajax_pusher_auth.php'
    });

    const roomId = <?php echo $room['id']; ?>;
    const channel = pusher.subscribe(`presence-room-${roomId}`);

    channel.bind('pusher:subscription_succeeded', (members) => updatePresence(members));
    channel.bind('pusher:member_added', (member) => {
        updatePresence(channel.members);
        if (window.showToast) window.showToast(`${member.info.name} entró a la sala`, 'info');
    });
    channel.bind('pusher:member_removed', (member) => {
        updatePresence(channel.members);
    });

    function updatePresence(members) {
        const panel = document.getElementById('rd-live-panel');
        const container = document.getElementById('rd-live-members');
        let count = 0;
        let html = '';

        members.each((member) => {
            count++;
            const avatar = member.info.avatar 
                ? `<img src="${member.info.avatar}" alt="${member.info.name}">` 
                : (member.info.name || 'U').charAt(0).toUpperCase();
            
            html += `<div class="rd-live-member">
                <div class="rd-live-member-avatar">${avatar}</div>
                <span class="rd-live-member-name">${member.info.name || 'Usuario'}</span>
            </div>`;
        });

        if (count > 0) {
            panel.classList.add('is-live');
            container.innerHTML = html;
        } else {
            panel.classList.remove('is-live');
            container.innerHTML = '<div class="rd-live-empty"><i class="ph ph-user-circle-dashed"></i>Nadie está en la sala ahora</div>';
        }
    }
});

// ========= THEATER =========
function openTheater(embedUrl, title) {
    const theater = document.getElementById('rd-theater');
    document.getElementById('theater-iframe').src = embedUrl;
    document.getElementById('theater-title').textContent = title;
    theater.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeTheater() {
    const theater = document.getElementById('rd-theater');
    theater.classList.remove('active');
    document.getElementById('theater-iframe').src = '';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeTheater(); });

// ========= ADD RECORDING =========
function toggleAddRecording() {
    document.getElementById('add-rec-form').classList.toggle('show');
}

document.getElementById('new-recording-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('add-rec-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Agregando...';

    try {
        const res = await fetch('ajax/ajax_rooms.php', { method: 'POST', body: new FormData(e.target) });
        const data = await res.json();
        if (data.success) {
            if (window.showToast) window.showToast('Grabación agregada', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            if (window.showToast) window.showToast('Error: ' + (data.error || 'Desconocido'), 'error');
        }
    } catch (err) {
        if (window.showToast) window.showToast('Error de conexión', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ph ph-plus"></i> Agregar';
    }
});

// ========= DELETE RECORDING =========
function deleteRecording(id) {
    if (!confirm('¿Eliminar esta grabación?')) return;
    const data = new FormData();
    data.append('action', 'delete_recording');
    data.append('id', id);
    
    fetch('ajax/ajax_rooms.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.showToast) window.showToast('Grabación eliminada', 'success');
                setTimeout(() => location.reload(), 500);
            }
        });
}

// ========= ROOM ACTIONS =========
function openEditRoomFromDetail() {
    // Redirect to rooms page and trigger edit (simpler approach)
    const room = <?php echo json_encode($room); ?>;
    // Use SweetAlert for inline edit
    Swal.fire({
        title: 'Editar Sala',
        html: `
            <div style="text-align:left;">
                <label style="display:block;margin-bottom:0.3rem;font-weight:600;font-size:0.85rem;">Nombre</label>
                <input type="text" id="swal-name" class="swal2-input" value="${room.name}" style="margin:0 0 0.75rem 0;width:100%;">
                <label style="display:block;margin-bottom:0.3rem;font-weight:600;font-size:0.85rem;">Descripción</label>
                <input type="text" id="swal-desc" class="swal2-input" value="${room.description || ''}" style="margin:0 0 0.75rem 0;width:100%;">
                <label style="display:block;margin-bottom:0.3rem;font-weight:600;font-size:0.85rem;">Enlace Meet</label>
                <div style="margin-bottom: 0.5rem; display: flex; gap: 1rem;">
                    <label style="font-weight:normal; font-size: 0.85rem;"><input type="radio" name="swal_meet_option" value="manual" checked onchange="document.getElementById('swal-meet').style.display='block'"> Manual</label>
                    <label style="font-weight:normal; font-size: 0.85rem;"><input type="radio" name="swal_meet_option" value="auto" onchange="document.getElementById('swal-meet').style.display='none'"> Auto-generar (Público)</label>
                </div>
                <input type="url" id="swal-meet" class="swal2-input" value="${room.meet_link || ''}" style="margin:0;width:100%;">
            </div>
        `,
        confirmButtonText: 'Guardar',
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const data = new FormData();
            data.append('action', 'update_room');
            data.append('id', room.id);
            data.append('name', document.getElementById('swal-name').value);
            data.append('description', document.getElementById('swal-desc').value);
            const meetOption = document.querySelector('input[name="swal_meet_option"]:checked').value;
            data.append('meet_link', meetOption === 'auto' ? '' : document.getElementById('swal-meet').value);
            data.append('auto_meet', meetOption === 'auto' ? '1' : '0');
            data.append('color', room.color);
            data.append('icon', room.icon);
            return fetch('ajax/ajax_rooms.php', { method: 'POST', body: data }).then(r => r.json());
        }
    }).then(result => {
        if (result.isConfirmed && result.value.success) {
            if (window.showToast) window.showToast('Sala actualizada', 'success');
            setTimeout(() => location.reload(), 800);
        }
    });
}

function deleteRoomFromDetail(id, name) {
    if (!confirm(`¿Eliminar la sala "${name}"?`)) return;
    const data = new FormData();
    data.append('action', 'delete_room');
    data.append('id', id);
    fetch('ajax/ajax_rooms.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.showToast) window.showToast('Sala eliminada', 'success');
                setTimeout(() => window.location.href = 'index.php?module=reuniones&action=rooms', 800);
            }
        });
}

function copyPublicLink() {
    const input = document.getElementById('public-link-input');
    navigator.clipboard.writeText(input.value).then(() => {
        if (window.showToast) window.showToast('Enlace público copiado', 'success');
    });
}


async function syncRecordings() {
    const btn = document.getElementById('btn-sync-recordings');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Sincronizando...';
    btn.disabled = true;

    try {
        const data = new FormData();
        data.append('action', 'sync_recordings');
        
        const res = await fetch('ajax/ajax_rooms.php', { method: 'POST', body: data });
        const result = await res.json();
        
        if (result.success) {
            let msg = result.log && result.log.length > 0 ? result.log.join('\n') : 'Sincronización completada';
            // Use sweetalert if available, otherwise fallback to alert/toast
            if (typeof Swal !== 'undefined') {
                Swal.fire('Sincronización', msg, 'info').then(() => {
                    location.reload();
                });
            } else {
                alert(msg);
                location.reload();
            }
        } else {
            if (window.showToast) window.showToast('Error: ' + result.error, 'error');
            else alert('Error: ' + result.error);
        }
    } catch (e) {
        if (window.showToast) window.showToast('Error de conexión', 'error');
        else alert('Error de conexión');
    } finally {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
