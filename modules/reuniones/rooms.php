<?php
// modules/reuniones/rooms.php
// Salas de Reunión — Vista principal con grid de salas y presencia en tiempo real
require_once 'includes/header.php';

global $db;

// Fetch all active rooms
$sql = "SELECT mr.*, u.name as creator_name, 
               (SELECT COUNT(*) FROM meeting_room_recordings WHERE room_id = mr.id) as recording_count
        FROM meeting_rooms mr
        LEFT JOIN users u ON mr.created_by = u.id
        WHERE mr.is_active = 1
        ORDER BY mr.created_at ASC";
$stmt = $db->query($sql);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get site URL for public links
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
?>

<style>
    /* ========= ANIMATIONS ========= */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes pulse-soft { 0%,100% { opacity: 1; } 50% { opacity: 0.7; } }
    @keyframes live-pulse { 
        0% { box-shadow: 0 0 0 0 var(--room-glow); } 
        70% { box-shadow: 0 0 0 12px transparent; } 
        100% { box-shadow: 0 0 0 0 transparent; } 
    }
    @keyframes live-dot { 0%,100% { transform: scale(1); } 50% { transform: scale(1.3); } }
    @keyframes float-icon { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
    @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
    @keyframes breathe { 0%,100% { opacity: 0.4; } 50% { opacity: 0.8; } }

    /* ========= TABS (same as index.php) ========= */
    .reuniones-tabs {
        display: inline-flex;
        background: color-mix(in srgb, var(--border-color) 40%, transparent);
        border-radius: 12px;
        padding: 4px;
        margin-bottom: 1.25rem;
        gap: 2px;
    }
    .reuniones-tab {
        padding: 0.5rem 1.25rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.4rem;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    .reuniones-tab:hover { color: var(--text-main); background: color-mix(in srgb, var(--bg-surface) 60%, transparent); }
    .reuniones-tab.active {
        background: var(--bg-surface);
        color: var(--primary-color);
        box-shadow: var(--shadow-sm);
    }
    .reuniones-tab .live-count {
        background: #ef4444;
        color: white;
        font-size: 0.65rem;
        padding: 1px 6px;
        border-radius: 8px;
        font-weight: 700;
        animation: pulse-soft 2s ease-in-out infinite;
    }

    /* ========= HEADER ========= */
    .rooms-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
        padding: 1.75rem;
        background: linear-gradient(135deg, color-mix(in srgb, var(--primary-color) 4%, transparent), color-mix(in srgb, #ec4899 3%, transparent));
        border-radius: 16px;
        border: 1px solid var(--border-color);
        animation: fadeInUp 0.4s ease-out;
    }
    .rooms-header h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--color-title);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .rooms-header p {
        color: var(--text-muted);
        margin: 0.25rem 0 0 0;
        font-size: 0.9rem;
    }
    .rooms-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }
    .rooms-actions .btn {
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .rooms-actions .btn:hover {
        transform: translateY(-1px) scale(1.02);
    }
    .rooms-actions .btn-primary:hover {
        box-shadow: 0 0 20px color-mix(in srgb, var(--primary-color) 30%, transparent);
    }

    /* ========= ROOMS GRID ========= */
    .rooms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.25rem;
    }

    /* ========= ROOM CARD ========= */
    .room-card {
        --room-color: #4f46e5;
        --room-glow: color-mix(in srgb, var(--room-color) 40%, transparent);
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1.5rem;
        transition: all 0.3s ease;
        animation: fadeInUp 0.5s ease-out both;
        position: relative;
        overflow: hidden;
    }
    .room-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--room-color);
        opacity: 0.6;
        transition: opacity 0.3s ease;
    }
    .room-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
        border-color: color-mix(in srgb, var(--room-color) 30%, var(--border-color));
    }
    .room-card:hover::before { opacity: 1; }

    /* Staggered animation */
    .room-card:nth-child(1) { animation-delay: 0s; }
    .room-card:nth-child(2) { animation-delay: 0.05s; }
    .room-card:nth-child(3) { animation-delay: 0.1s; }
    .room-card:nth-child(4) { animation-delay: 0.15s; }
    .room-card:nth-child(5) { animation-delay: 0.2s; }
    .room-card:nth-child(6) { animation-delay: 0.25s; }

    /* LIVE state */
    .room-card.is-live {
        border-color: var(--room-color);
        animation: fadeInUp 0.5s ease-out both, live-pulse 2s infinite;
    }
    .room-card.is-live::before { opacity: 1; height: 4px; }

    .room-card-header {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .room-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        color: white;
        background: var(--room-color);
        flex-shrink: 0;
        box-shadow: 0 4px 12px color-mix(in srgb, var(--room-color) 30%, transparent);
        transition: transform 0.2s ease;
    }
    .room-card:hover .room-card-icon { transform: scale(1.05); }

    .room-card-info { flex: 1; min-width: 0; }
    .room-card-name {
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--color-title);
        margin: 0;
        line-height: 1.3;
    }
    .room-card-desc {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin: 0.15rem 0 0 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Live badge */
    .room-live-badge {
        display: none;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.65rem;
        background: #fef2f2;
        color: #dc2626;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        flex-shrink: 0;
    }
    .room-live-badge .dot {
        width: 7px;
        height: 7px;
        background: #ef4444;
        border-radius: 50%;
        animation: live-dot 1s ease-in-out infinite;
    }
    .room-card.is-live .room-live-badge { display: inline-flex; }

    [data-theme="dark"] .room-live-badge {
        background: rgba(239, 68, 68, 0.15);
        color: #f87171;
    }

    /* Presence avatars */
    .room-presence {
        display: flex;
        align-items: center;
        min-height: 36px;
        margin-bottom: 1rem;
        gap: 0.5rem;
    }
    .room-presence-avatars {
        display: flex;
        align-items: center;
    }
    .room-presence-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 2px solid var(--bg-surface);
        background: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        margin-left: -8px;
        overflow: hidden;
        transition: transform 0.2s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .room-presence-avatar:first-child { margin-left: 0; }
    .room-presence-avatar:hover { transform: scale(1.15); z-index: 2; }
    .room-presence-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .room-presence-text {
        font-size: 0.75rem;
        color: var(--text-muted);
        font-weight: 500;
    }
    .room-presence-empty {
        font-size: 0.8rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    .room-presence-empty i { font-size: 1rem; }

    /* Recording count */
    .room-stats {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding-top: 0.75rem;
        margin-top: auto;
        border-top: 1px solid var(--border-color);
        font-size: 0.8rem;
        color: var(--text-muted);
    }
    .room-stat {
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    /* Card actions */
    .room-card-actions {
        display: flex;
        gap: 0.4rem;
        margin-top: 1rem;
    }
    .room-card-actions .btn {
        flex: 1;
        justify-content: center;
        font-size: 0.8rem;
        border-radius: 10px;
        padding: 0.55rem 0.5rem;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .room-card-actions .btn:hover {
        transform: translateY(-1px);
    }
    .btn-join {
        background: var(--room-color) !important;
        color: white !important;
        border: none !important;
    }
    .btn-join:hover {
        box-shadow: 0 4px 15px color-mix(in srgb, var(--room-color) 40%, transparent);
    }

    /* ========= EMPTY STATE ========= */
    .rooms-empty {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-muted);
        animation: fadeInUp 0.5s ease-out;
    }
    .rooms-empty i {
        font-size: 4rem;
        color: color-mix(in srgb, var(--text-muted) 40%, transparent);
        margin-bottom: 1rem;
        animation: float-icon 3s ease-in-out infinite;
    }
    .rooms-empty h3 {
        margin: 0 0 0.5rem 0;
        color: var(--color-title);
        font-size: 1.2rem;
    }
    .rooms-empty p {
        margin: 0;
        font-size: 0.9rem;
    }

    /* ========= CREATE ROOM MODAL ========= */
    .room-modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }
    .room-modal-overlay.active { display: flex; }
    .room-modal {
        background: var(--bg-surface, #fff);
        border-radius: 20px;
        width: 92%;
        max-width: 480px;
        padding: 1.75rem;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        max-height: 90vh;
        overflow-y: auto;
        animation: scale-in 0.25s ease-out;
    }
    @keyframes scale-in { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

    .room-modal h3 {
        margin: 0 0 1.5rem 0;
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--color-title);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .room-modal .form-group {
        margin-bottom: 1.1rem;
    }
    .room-modal label {
        display: block;
        margin-bottom: 0.4rem;
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--text-main);
    }
    .room-modal .form-control {
        border-radius: 10px;
        padding: 0.65rem 0.85rem;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }
    .room-modal .form-control:focus {
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary-color) 12%, transparent);
    }
    .room-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    .room-modal-footer .btn {
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.55rem 1.1rem;
    }

    /* Color picker */
    .color-options {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .color-option {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .color-option:hover { transform: scale(1.1); }
    .color-option.selected {
        border-color: var(--color-title);
        box-shadow: 0 0 0 2px var(--bg-surface), 0 0 0 4px var(--color-title);
    }
    .color-option i { color: white; font-size: 0.85rem; display: none; }
    .color-option.selected i { display: block; }

    /* Icon picker */
    .icon-options {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .icon-option {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        cursor: pointer;
        border: 1px solid var(--border-color);
        background: var(--bg-color);
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        color: var(--text-muted);
    }
    .icon-option:hover { border-color: var(--primary-color); color: var(--primary-color); }
    .icon-option.selected {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    /* ========= RESPONSIVE ========= */
    @media (max-width: 768px) {
        .rooms-header {
            flex-direction: column;
            gap: 1rem;
            padding: 1.25rem;
        }
        .rooms-header h1 { font-size: 1.3rem; }
        .rooms-actions { width: 100%; }
        .rooms-actions .btn { flex: 1; justify-content: center; }
        .rooms-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        .room-card { padding: 1.25rem; }
        .room-card-actions .btn { padding: 0.65rem; }
    }

    @media (max-width: 480px) {
        .rooms-header h1 { font-size: 1.15rem; }
        .reuniones-tabs {
            width: 100%;
        }
        .reuniones-tab {
            flex: 1;
            justify-content: center;
        }
    }
</style>

<!-- Tabs -->
<div class="reuniones-tabs">
    <a href="index.php?module=reuniones&action=index" class="reuniones-tab">
        <i class="ph ph-list-bullets"></i> Historial
    </a>
    <a href="index.php?module=reuniones&action=rooms" class="reuniones-tab active">
        <i class="ph ph-buildings"></i> Salas
        <span class="live-count" id="total-live-count" style="display:none;">0</span>
    </a>
</div>

<!-- Header -->
<div class="rooms-header">
    <div>
        <h1>
            <i class="ph ph-buildings" style="color: var(--primary-color);"></i> Salas de Reunión <span style="background: color-mix(in srgb, var(--primary-color) 15%, transparent); color: var(--primary-color); font-size: 0.75rem; padding: 3px 8px; border-radius: 12px; vertical-align: middle; margin-left: 8px; font-weight: 700; border: 1px solid color-mix(in srgb, var(--primary-color) 30%, transparent); text-transform: uppercase; letter-spacing: 0.5px;">Beta</span>
        </h1>
        <p>Espacios permanentes para tu equipo. Únete o crea una sala nueva.</p>
    </div>
    <div class="rooms-actions">
        <button onclick="openCreateRoomModal()" class="btn btn-primary" style="background: var(--primary-color); border-color: var(--primary-color);">
            <i class="ph ph-plus-circle"></i> <span class="btn-label">Crear Sala</span>
        </button>
    </div>
</div>

<!-- Rooms Grid -->
<?php if (empty($rooms)): ?>
    <div class="rooms-empty">
        <i class="ph ph-buildings"></i>
        <h3>No hay salas creadas</h3>
        <p>Crea tu primera sala de reunión para que tu equipo pueda conectarse.</p>
    </div>
<?php else: ?>
    <div class="rooms-grid" id="rooms-grid">
        <?php foreach($rooms as $room): ?>
        <div class="room-card" 
             id="room-<?php echo $room['id']; ?>"
             style="--room-color: <?php echo htmlspecialchars($room['color']); ?>; --room-glow: color-mix(in srgb, <?php echo htmlspecialchars($room['color']); ?> 40%, transparent);"
             data-room-id="<?php echo $room['id']; ?>">
            
            <div class="room-card-header">
                <div class="room-card-icon" style="background: <?php echo htmlspecialchars($room['color']); ?>;">
                    <i class="ph ph-<?php echo htmlspecialchars($room['icon']); ?>"></i>
                </div>
                <div class="room-card-info">
                    <h3 class="room-card-name"><?php echo htmlspecialchars($room['name']); ?></h3>
                    <?php if($room['description']): ?>
                        <p class="room-card-desc"><?php echo htmlspecialchars($room['description']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="room-live-badge">
                    <span class="dot"></span> EN VIVO
                </div>
            </div>

            <!-- Presence area (populated by Pusher) -->
            <div class="room-presence" id="room-presence-<?php echo $room['id']; ?>">
                <div class="room-presence-empty">
                    <i class="ph ph-user-circle-dashed"></i> Sala disponible
                </div>
            </div>

            <!-- Stats -->
            <div class="room-stats">
                <div class="room-stat">
                    <i class="ph ph-play-circle"></i>
                    <span><?php echo $room['recording_count']; ?> grabaciones</span>
                </div>
                <div class="room-stat" id="room-members-count-<?php echo $room['id']; ?>">
                    <i class="ph ph-users"></i>
                    <span>0 conectados</span>
                </div>
            </div>

            <!-- Actions -->
            <div class="room-card-actions">
                <?php if($room['meet_link']): ?>
                    <a href="<?php echo htmlspecialchars($room['meet_link']); ?>" target="_blank" class="btn btn-join" style="background: <?php echo htmlspecialchars($room['color']); ?>;">
                        <i class="ph ph-video-camera"></i> Unirse
                    </a>
                <?php else: ?>
                    <button class="btn btn-join" style="background: <?php echo htmlspecialchars($room['color']); ?>;" onclick="alert('Esta sala no tiene enlace Meet configurado. Edítala para agregar uno.')">
                        <i class="ph ph-video-camera"></i> Unirse
                    </button>
                <?php endif; ?>
                <a href="index.php?module=reuniones&action=room_detail&id=<?php echo $room['id']; ?>" class="btn btn-outline" style="border-radius: 10px;">
                    <i class="ph ph-clock-counter-clockwise"></i> Historial
                </a>
                <button class="btn btn-outline" style="border-radius: 10px;" onclick="copyPublicLink('<?php echo htmlspecialchars($room['slug']); ?>', '<?php echo htmlspecialchars($room['name']); ?>')" title="Compartir">
                    <i class="ph ph-share-network"></i>
                </button>
                <button class="btn btn-outline" style="border-radius: 10px;" onclick="openEditRoomModal(<?php echo $room['id']; ?>, '<?php echo addslashes(htmlspecialchars($room['name'])); ?>', '<?php echo addslashes(htmlspecialchars($room['description'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($room['meet_link'] ?? '')); ?>', '<?php echo htmlspecialchars($room['color']); ?>', '<?php echo htmlspecialchars($room['icon']); ?>')" title="Editar">
                    <i class="ph ph-pencil-simple"></i>
                </button>
                <button class="btn btn-outline" style="border-radius: 10px; color: #ef4444; border-color: #fca5a5;" onclick="deleteRoom(<?php echo $room['id']; ?>, '<?php echo addslashes(htmlspecialchars($room['name'])); ?>')" title="Eliminar">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Create/Edit Room Modal -->
<div class="room-modal-overlay" id="room-modal">
    <div class="room-modal">
        <h3>
            <i class="ph ph-plus-circle" style="color: var(--primary-color);"></i>
            <span id="room-modal-title">Crear Sala</span>
        </h3>
        <form id="room-form">
            <input type="hidden" id="room-edit-id" value="">
            
            <div class="form-group">
                <label>Nombre de la Sala *</label>
                <input type="text" id="room-name" class="form-control" placeholder="Ej: Sala de Estrategia" required>
            </div>

            <div class="form-group">
                <label>Descripción (opcional)</label>
                <input type="text" id="room-desc" class="form-control" placeholder="Breve descripción del propósito">
            </div>

            <div class="form-group">
                <label>Enlace Google Meet</label>
                <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.5rem;">
                    <label style="display:flex; align-items:center; gap: 0.3rem; font-weight:normal; font-size:0.85rem; margin:0; cursor:pointer;">
                        <input type="radio" name="meet_option" value="manual" checked onchange="toggleMeetInput(this.value)"> Ingresar manualmente
                    </label>
                    <label style="display:flex; align-items:center; gap: 0.3rem; font-weight:normal; font-size:0.85rem; margin:0; cursor:pointer;">
                        <input type="radio" name="meet_option" value="auto" onchange="toggleMeetInput(this.value)"> Generar automáticamente (Público)
                    </label>
                </div>
                <input type="url" id="room-meet-link" class="form-control" placeholder="https://meet.google.com/xxx-xxxx-xxx">
                <p id="room-meet-hint" style="margin: 0.3rem 0 0 0; font-size: 0.7rem; color: var(--text-muted);">Si lo dejas vacío, los participantes deberán crear la reunión manualmente.</p>
            </div>

            <div class="form-group">
                <label>Color</label>
                <div class="color-options" id="color-options">
                    <div class="color-option selected" data-color="#4f46e5" style="background: #4f46e5;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-option" data-color="#6366f1" style="background: #6366f1;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-option" data-color="#8b5cf6" style="background: #8b5cf6;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-option" data-color="#ec4899" style="background: #ec4899;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-option" data-color="#ef4444" style="background: #ef4444;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-option" data-color="#f59e0b" style="background: #f59e0b;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-option" data-color="#10b981" style="background: #10b981;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-option" data-color="#3b82f6" style="background: #3b82f6;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-option" data-color="#0ea5e9" style="background: #0ea5e9;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                </div>
            </div>

            <div class="form-group">
                <label>Ícono</label>
                <div class="icon-options" id="icon-options">
                    <div class="icon-option selected" data-icon="video-camera" onclick="selectIcon(this)"><i class="ph ph-video-camera"></i></div>
                    <div class="icon-option" data-icon="strategy" onclick="selectIcon(this)"><i class="ph ph-strategy"></i></div>
                    <div class="icon-option" data-icon="paint-brush" onclick="selectIcon(this)"><i class="ph ph-paint-brush"></i></div>
                    <div class="icon-option" data-icon="coffee" onclick="selectIcon(this)"><i class="ph ph-coffee"></i></div>
                    <div class="icon-option" data-icon="rocket-launch" onclick="selectIcon(this)"><i class="ph ph-rocket-launch"></i></div>
                    <div class="icon-option" data-icon="megaphone" onclick="selectIcon(this)"><i class="ph ph-megaphone"></i></div>
                    <div class="icon-option" data-icon="code" onclick="selectIcon(this)"><i class="ph ph-code"></i></div>
                    <div class="icon-option" data-icon="presentation-chart" onclick="selectIcon(this)"><i class="ph ph-presentation-chart"></i></div>
                    <div class="icon-option" data-icon="headset" onclick="selectIcon(this)"><i class="ph ph-headset"></i></div>
                    <div class="icon-option" data-icon="chalkboard-teacher" onclick="selectIcon(this)"><i class="ph ph-chalkboard-teacher"></i></div>
                    <div class="icon-option" data-icon="brain" onclick="selectIcon(this)"><i class="ph ph-brain"></i></div>
                    <div class="icon-option" data-icon="lightbulb" onclick="selectIcon(this)"><i class="ph ph-lightbulb"></i></div>
                </div>
            </div>

            <div class="room-modal-footer">
                <button type="button" onclick="closeRoomModal()" class="btn btn-outline">Cancelar</button>
                <button type="submit" id="room-submit-btn" class="btn btn-primary" style="background: var(--primary-color); border-color: var(--primary-color);">Crear Sala</button>
            </div>
        </form>
    </div>
</div>

<script>
// ========= PUSHER REAL-TIME PRESENCE =========
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Pusher === 'undefined') return;

    const pusher = new Pusher('b31f38612d61b0285c78', {
        cluster: 'us2',
        authEndpoint: 'ajax_pusher_auth.php'
    });

    let totalLive = 0;

    // Subscribe to each room's presence channel
    document.querySelectorAll('.room-card').forEach(card => {
        const roomId = card.dataset.roomId;
        const channel = pusher.subscribe(`presence-room-${roomId}`);

        channel.bind('pusher:subscription_succeeded', (members) => {
            updateRoomPresence(roomId, members);
        });

        channel.bind('pusher:member_added', (member) => {
            updateRoomPresence(roomId, channel.members);
            if (window.showToast) window.showToast(`${member.info.name} entró a la sala`, 'info');
        });

        channel.bind('pusher:member_removed', (member) => {
            updateRoomPresence(roomId, channel.members);
        });
    });

    function updateRoomPresence(roomId, members) {
        const card = document.getElementById(`room-${roomId}`);
        const presenceEl = document.getElementById(`room-presence-${roomId}`);
        const countEl = document.getElementById(`room-members-count-${roomId}`);
        if (!card || !presenceEl) return;

        let count = 0;
        let avatarsHtml = '<div class="room-presence-avatars">';
        
        members.each((member) => {
            count++;
            if (member.info.avatar) {
                avatarsHtml += `<div class="room-presence-avatar" title="${member.info.name}"><img src="${member.info.avatar}" alt="${member.info.name}"></div>`;
            } else {
                avatarsHtml += `<div class="room-presence-avatar" title="${member.info.name}" style="background: ${card.style.getPropertyValue('--room-color') || '#4f46e5'}">${(member.info.name || 'U').charAt(0).toUpperCase()}</div>`;
            }
        });
        avatarsHtml += '</div>';

        if (count > 0) {
            card.classList.add('is-live');
            presenceEl.innerHTML = avatarsHtml + `<span class="room-presence-text">${count} persona${count > 1 ? 's' : ''} conectada${count > 1 ? 's' : ''}</span>`;
        } else {
            card.classList.remove('is-live');
            presenceEl.innerHTML = '<div class="room-presence-empty"><i class="ph ph-user-circle-dashed"></i> Sala disponible</div>';
        }

        if (countEl) {
            countEl.innerHTML = `<i class="ph ph-users"></i><span>${count} conectados</span>`;
        }

        // Update total live count
        totalLive = 0;
        document.querySelectorAll('.room-card.is-live').forEach(() => totalLive++);
        const badge = document.getElementById('total-live-count');
        if (badge) {
            if (totalLive > 0) {
                badge.textContent = totalLive;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        }
    }
});

// ========= MODAL FUNCTIONS =========
function openCreateRoomModal() {
    document.getElementById('room-edit-id').value = '';
    document.getElementById('room-name').value = '';
    document.getElementById('room-desc').value = '';
    document.getElementById('room-meet-link').value = '';
    document.querySelector('input[name="meet_option"][value="manual"]').checked = true;
    toggleMeetInput('manual');
    document.getElementById('room-modal-title').textContent = 'Crear Sala';
    document.getElementById('room-submit-btn').textContent = 'Crear Sala';
    
    // Reset color/icon selection
    document.querySelectorAll('.color-option').forEach((el, i) => {
        el.classList.toggle('selected', i === 0);
    });
    document.querySelectorAll('.icon-option').forEach((el, i) => {
        el.classList.toggle('selected', i === 0);
    });
    
    document.getElementById('room-modal').classList.add('active');
}

function openEditRoomModal(id, name, desc, meetLink, color, icon) {
    document.getElementById('room-edit-id').value = id;
    document.getElementById('room-name').value = name;
    document.getElementById('room-desc').value = desc || '';
    document.getElementById('room-meet-link').value = meetLink || '';
    document.querySelector('input[name="meet_option"][value="manual"]').checked = true;
    toggleMeetInput('manual');
    document.getElementById('room-modal-title').textContent = 'Editar Sala';
    document.getElementById('room-submit-btn').textContent = 'Guardar Cambios';
    
    // Select the right color
    document.querySelectorAll('.color-option').forEach(el => {
        el.classList.toggle('selected', el.dataset.color === color);
    });
    
    // Select the right icon
    document.querySelectorAll('.icon-option').forEach(el => {
        el.classList.toggle('selected', el.dataset.icon === icon);
    });
    
    document.getElementById('room-modal').classList.add('active');
}

function closeRoomModal() {
    document.getElementById('room-modal').classList.remove('active');
}

function selectColor(el) {
    document.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
}

function selectIcon(el) {
    document.querySelectorAll('.icon-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
}

function toggleMeetInput(value) {
    const input = document.getElementById('room-meet-link');
    const hint = document.getElementById('room-meet-hint');
    if (value === 'auto') {
        input.style.display = 'none';
        hint.innerHTML = '<i class="ph ph-magic-wand" style="color:var(--primary-color);"></i> Se generará un nuevo enlace de Google Meet automáticamente al guardar. Todos los participantes de la sala podrán unirse sin pedir permiso.';
    } else {
        input.style.display = 'block';
        hint.textContent = 'Si lo dejas vacío, los participantes deberán crear la reunión manualmente.';
    }
}

// ========= FORM SUBMIT =========
document.getElementById('room-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('room-submit-btn');
    const editId = document.getElementById('room-edit-id').value;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';

    const selectedColor = document.querySelector('.color-option.selected')?.dataset.color || '#4f46e5';
    const selectedIcon = document.querySelector('.icon-option.selected')?.dataset.icon || 'video-camera';

    const data = new FormData();
    data.append('action', editId ? 'update_room' : 'create_room');
    if (editId) data.append('id', editId);
    data.append('name', document.getElementById('room-name').value);
    data.append('description', document.getElementById('room-desc').value);
    const meetOption = document.querySelector('input[name="meet_option"]:checked').value;
    data.append('meet_link', meetOption === 'auto' ? '' : document.getElementById('room-meet-link').value);
    data.append('auto_meet', meetOption === 'auto' ? '1' : '0');
    data.append('color', selectedColor);
    data.append('icon', selectedIcon);

    try {
        const res = await fetch('ajax/ajax_rooms.php', { method: 'POST', body: data });
        const result = await res.json();
        
        if (result.success) {
            if (window.showToast) window.showToast(editId ? 'Sala actualizada' : 'Sala creada exitosamente', 'success');
            closeRoomModal();
            setTimeout(() => location.reload(), 800);
        } else {
            if (window.showToast) window.showToast('Error: ' + (result.error || 'Desconocido'), 'error');
        }
    } catch (err) {
        if (window.showToast) window.showToast('Error de conexión', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = editId ? 'Guardar Cambios' : 'Crear Sala';
    }
});

// ========= UTILITY =========
function copyPublicLink(slug, name) {
    const url = `${window.location.origin}${window.location.pathname.replace('index.php', '')}public_room.php?slug=${slug}`;
    navigator.clipboard.writeText(url).then(() => {
        if (window.showToast) window.showToast(`Enlace público de "${name}" copiado al portapapeles`, 'success');
    }).catch(() => {
        // Fallback
        prompt('Copia este enlace:', url);
    });
}

function deleteRoom(id, name) {
    if (!confirm(`¿Estás seguro que deseas eliminar la sala "${name}"? Las grabaciones asociadas se conservarán.`)) return;
    
    const data = new FormData();
    data.append('action', 'delete_room');
    data.append('id', id);

    fetch('ajax/ajax_rooms.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.showToast) window.showToast('Sala eliminada', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                if (window.showToast) window.showToast('Error: ' + res.error, 'error');
            }
        })
        .catch(() => {
            if (window.showToast) window.showToast('Error de conexión', 'error');
        });
}

// Close modal on click outside
document.getElementById('room-modal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('room-modal')) closeRoomModal();
});

// Close modal with Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeRoomModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>
