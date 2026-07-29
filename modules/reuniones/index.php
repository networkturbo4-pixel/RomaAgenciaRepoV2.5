<?php
// modules/reuniones/index.php
require_once 'includes/header.php';

global $db;

// Pagination and Filtering
$search = $_GET['search'] ?? '';
$brand_id = $_GET['brand_id'] ?? '';
$status = $_GET['status'] ?? '';

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(r.motivo LIKE ? OR r.resumen LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($brand_id) {
    $where[] = "r.brand_id = ?";
    $params[] = $brand_id;
}

if ($status) {
    $where[] = "r.estado = ?";
    $params[] = $status;
} else {
    // Default: hide eliminated (trash)
    $where[] = "r.estado != 'Eliminada'";
}

$whereClause = implode(" AND ", $where);

// Fetch reuniones
$sql = "SELECT r.*, b.name as brand_name, b.logo as brand_logo, b.whatsapp_group, c.whatsapp as client_whatsapp 
        FROM reuniones r 
        LEFT JOIN client_brands b ON r.brand_id = b.id 
        LEFT JOIN clients c ON b.client_id = c.id
        WHERE $whereClause 
        ORDER BY r.fecha_hora DESC 
        LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$reuniones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch brands for filter
$stmtBrands = $db->query("SELECT id, name FROM client_brands ORDER BY name ASC");
$marcas = $stmtBrands->fetchAll(PDO::FETCH_ASSOC);

$activeFilters = ($search || $brand_id || $status) ? true : false;
$filterCount = ($search ? 1 : 0) + ($brand_id ? 1 : 0) + ($status ? 1 : 0);
?>

<style>
    /* ============ ANIMATIONS ============ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(16px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }
    @keyframes pulse-soft {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    @keyframes float-icon {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-8px); }
    }
    @keyframes fadeInCard {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ============ SEGMENTED TABS ============ */
    .reuniones-tabs {
        display: inline-flex;
        background: color-mix(in srgb, var(--border-color) 40%, transparent);
        border-radius: 12px;
        padding: 4px;
        margin-bottom: 1rem;
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
    .reuniones-tab:hover {
        color: var(--text-main);
        background: color-mix(in srgb, var(--bg-surface) 60%, transparent);
    }
    .reuniones-tab.active {
        background: var(--bg-surface);
        color: var(--primary-color);
        box-shadow: var(--shadow-sm);
    }

    /* ============ HEADER ============ */
    .reuniones-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding: 1.75rem;
        border-radius: 16px;
        background: linear-gradient(135deg, color-mix(in srgb, var(--primary-color) 4%, transparent), color-mix(in srgb, var(--secondary-color, #10b981) 3%, transparent));
        gap: 1rem;
        border-bottom: none;
    }
    .reuniones-header h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--color-title);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        animation: fadeInUp 0.4s ease-out;
    }
    .reuniones-header p {
        color: var(--text-muted);
        margin: 0.25rem 0 0 0;
        font-size: 0.9rem;
        animation: fadeInUp 0.5s ease-out;
    }

    /* ============ ACTION BUTTONS ============ */
    .reuniones-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }
    .reuniones-actions .btn {
        transition: all 0.2s ease;
    }
    .reuniones-actions .btn:hover {
        transform: scale(1.02);
    }
    .reuniones-actions .btn.btn-primary:hover {
        box-shadow: 0 0 20px color-mix(in srgb, #ea4335 30%, transparent);
    }
    .reuniones-actions .btn.btn-outline:hover {
        box-shadow: 0 0 20px color-mix(in srgb, #10b981 25%, transparent);
    }
    .reuniones-actions .btn span.btn-label {
        display: inline;
    }

    /* ============ FILTER TOGGLE ============ */
    .filter-toggle-btn {
        display: none;
        width: 100%;
        padding: 0.75rem 1rem;
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-main);
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0;
        transition: all 0.2s ease;
    }
    .filter-toggle-btn:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }
    .filter-toggle-btn .badge-count {
        background: var(--primary-color);
        color: white;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: 0.5rem;
    }
    .filter-toggle-btn i.chevron {
        transition: transform 0.3s ease;
    }
    .filter-toggle-btn.active i.chevron {
        transform: rotate(180deg);
    }

    /* ============ FILTERS CONTAINER ============ */
    .filters-container {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
        flex-wrap: wrap;
        max-height: 500px;
        opacity: 1;
        transition: max-height 0.35s ease, opacity 0.25s ease;
    }
    .filters-container .filter-field {
        flex: 1;
        min-width: 180px;
    }
    .filters-container .filter-field label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--text-muted);
    }
    .filters-container .form-control {
        border-radius: 12px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .filters-container .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary-color) 12%, transparent);
        outline: none;
    }

    /* ============ TABLE DESKTOP ============ */
    .reuniones-table {
        width: 100%;
        border-collapse: collapse;
    }
    .reuniones-table thead th {
        padding: 0.85rem 1rem;
        text-align: left;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--text-muted);
        background: var(--bg-color);
        border-bottom: 1px solid var(--border-color);
    }
    .reuniones-table tbody tr {
        border-bottom: 1px solid color-mix(in srgb, var(--border-color) 50%, transparent);
        transition: all 0.2s ease;
        box-shadow: inset 0 0 0 transparent;
    }
    .reuniones-table tbody tr:hover {
        background: linear-gradient(90deg, color-mix(in srgb, var(--primary-color) 5%, transparent), transparent 70%);
        transform: translateX(2px);
        box-shadow: inset 3px 0 0 var(--primary-color);
    }
    .reuniones-table tbody tr:hover .table-avatar {
        transform: scale(1.05);
        box-shadow: 0 2px 8px color-mix(in srgb, var(--primary-color) 15%, transparent);
    }
    .reuniones-table tbody td {
        padding: 0.85rem 1rem;
        vertical-align: middle;
    }
    .reuniones-table .col-estado { text-align: center; }
    .reuniones-table .col-enlaces { text-align: center; }
    .reuniones-table .col-acciones { text-align: right; }

    .table-avatar {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    /* ============ TAG PILLS ============ */
    .tag-pill {
        background: color-mix(in srgb, var(--primary-color) 8%, #f1f5f9);
        color: #475569;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 500;
        border: 1px solid color-mix(in srgb, var(--primary-color) 12%, #e2e8f0);
        backdrop-filter: blur(4px);
        transition: all 0.15s ease;
    }
    .tag-pill:hover {
        background: color-mix(in srgb, var(--primary-color) 14%, #f1f5f9);
    }

    /* ============ STATUS BADGES ============ */
    .status-badge {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
        transition: all 0.2s ease;
    }
    .status-programada {
        background: #dbeafe;
        color: #1e40af;
        animation: pulse-soft 2s ease-in-out infinite;
    }
    .status-completada { background: #d1fae5; color: #065f46; }
    .status-cancelada  { background: #fee2e2; color: #991b1b; }

    /* ============ ICON BUTTONS ============ */
    .icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: transparent;
        cursor: pointer;
        transition: all 0.15s ease;
        font-size: 1rem;
        color: var(--text-muted);
        text-decoration: none;
    }
    .icon-btn:hover {
        background: var(--bg-color);
        transform: scale(1.08);
    }
    .icon-btn.meet { color: #ea4335; border-color: #fecaca; }
    .icon-btn.meet:hover { background: #fef2f2; }
    .icon-btn.recording { color: #10b981; border-color: #a7f3d0; }
    .icon-btn.recording:hover { background: #ecfdf5; }
    .icon-btn.gemini { color: #8b5cf6; border-color: #ddd6fe; }
    .icon-btn.gemini:hover { background: #f5f3ff; }
    .icon-btn.copy { color: #6b7280; border-color: #e5e7eb; }
    .icon-btn.copy:hover { background: #f9fafb; }

    /* ============ MOBILE CARD VIEW ============ */
    .reuniones-mobile-cards {
        display: none;
    }
    .reunion-card {
        background: var(--bg-surface, #fff);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        transition: box-shadow 0.2s ease, transform 0.15s ease;
        animation: fadeInCard 0.35s ease-out both;
        border-left: 4px solid var(--border-color);
    }
    .reunion-card:active {
        transform: scale(0.98);
        box-shadow: 0 2px 8px color-mix(in srgb, var(--text-main) 8%, transparent);
    }
    .reunion-card.card-programada {
        border-left-color: #3b82f6;
    }
    .reunion-card.card-completada {
        border-left-color: #10b981;
    }
    .reunion-card.card-cancelada {
        border-left-color: #ef4444;
    }
    .reunion-card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .reunion-card-avatar {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        object-fit: cover;
        flex-shrink: 0;
    }
    .reunion-card-avatar-placeholder {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: var(--primary-color-light);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        font-weight: bold;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .reunion-card-title {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--color-title);
        line-height: 1.3;
    }
    .reunion-card-brand {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 0.15rem;
    }
    .reunion-card-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding-top: 0.75rem;
        border-top: 1px solid var(--border-color);
    }
    .reunion-card-date {
        font-size: 0.8rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    .reunion-card-actions {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .reunion-card-actions .icon-btn {
        min-width: 40px;
        min-height: 40px;
        width: 40px;
        height: 40px;
    }
    .reunion-card-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin-bottom: 0.75rem;
    }

    /* ============ EMPTY STATE ============ */
    .empty-state {
        padding: 4rem 2rem;
        text-align: center;
        color: var(--text-muted);
        animation: fadeInUp 0.5s ease-out;
    }
    .empty-state-icon {
        font-size: 4rem;
        color: color-mix(in srgb, var(--text-muted) 40%, transparent);
        margin-bottom: 1.25rem;
        animation: float-icon 3s ease-in-out infinite;
        display: inline-block;
    }
    .empty-state h3 {
        margin: 0 0 0.5rem 0;
        color: var(--color-title);
        font-size: 1.15rem;
    }
    .empty-state p {
        margin: 0;
        color: var(--text-muted);
        font-size: 0.9rem;
        max-width: 360px;
        margin: 0 auto;
        line-height: 1.5;
    }

    /* ============ SKELETON SHIMMER ============ */
    .sk-box {
        background: linear-gradient(90deg, var(--border-color) 25%, color-mix(in srgb, var(--border-color), white 30%) 50%, var(--border-color) 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s ease-in-out infinite;
        border-radius: 6px;
    }

    /* ============ DARK MODE ============ */
    [data-theme="dark"] .reuniones-header {
        background: linear-gradient(135deg, color-mix(in srgb, var(--primary-color) 6%, transparent), color-mix(in srgb, var(--secondary-color, #10b981) 4%, transparent));
    }
    [data-theme="dark"] .reuniones-table tbody tr:hover {
        background: linear-gradient(90deg, color-mix(in srgb, var(--primary-color) 8%, transparent), transparent 70%);
    }
    [data-theme="dark"] .reunion-card {
        background: color-mix(in srgb, var(--bg-surface) 95%, white 5%);
    }
    [data-theme="dark"] .tag-pill {
        background: color-mix(in srgb, var(--primary-color) 12%, var(--bg-color));
        color: color-mix(in srgb, var(--text-main) 80%, white);
        border-color: color-mix(in srgb, var(--primary-color) 20%, var(--border-color));
    }
    [data-theme="dark"] .status-programada {
        background: color-mix(in srgb, #3b82f6 18%, var(--bg-color));
        color: #93bbfd;
    }
    [data-theme="dark"] .status-completada {
        background: color-mix(in srgb, #10b981 18%, var(--bg-color));
        color: #6ee7b7;
    }
    [data-theme="dark"] .status-cancelada {
        background: color-mix(in srgb, #ef4444 18%, var(--bg-color));
        color: #fca5a5;
    }
    [data-theme="dark"] .sk-box {
        background: linear-gradient(90deg, var(--border-color) 25%, color-mix(in srgb, var(--border-color), white 8%) 50%, var(--border-color) 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s ease-in-out infinite;
    }
    [data-theme="dark"] .icon-btn.meet:hover { background: color-mix(in srgb, #ea4335 12%, var(--bg-color)); }
    [data-theme="dark"] .icon-btn.recording:hover { background: color-mix(in srgb, #10b981 12%, var(--bg-color)); }
    [data-theme="dark"] .icon-btn.gemini:hover { background: color-mix(in srgb, #8b5cf6 12%, var(--bg-color)); }
    [data-theme="dark"] .icon-btn.copy:hover { background: color-mix(in srgb, #6b7280 12%, var(--bg-color)); }
    [data-theme="dark"] .empty-state-icon {
        color: color-mix(in srgb, var(--text-muted) 30%, transparent);
    }
    [data-theme="dark"] .reuniones-tab.active {
        background: var(--bg-color);
    }

    /* ============ RESPONSIVE ============ */
    @media (max-width: 768px) {
        .reuniones-header {
            flex-direction: column;
            align-items: flex-start;
            padding: 1.25rem;
            border-radius: 12px;
        }
        .reuniones-header h1 {
            font-size: 1.35rem;
        }
        .reuniones-header p {
            font-size: 0.8rem;
        }
        .reuniones-actions {
            width: 100%;
        }
        .reuniones-actions .btn {
            flex: 1;
            justify-content: center;
            font-size: 0.8rem;
            padding: 0.55rem 0.5rem;
        }
        .reuniones-actions .btn span.btn-label {
            display: none;
        }

        /* Show toggle, hide filters by default */
        .filter-toggle-btn {
            display: flex;
        }
        .filters-container {
            display: none;
            flex-direction: column;
            gap: 0.75rem;
            padding-top: 0.75rem;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
        }
        .filters-container.show {
            display: flex;
            max-height: 500px;
            opacity: 1;
        }
        .filters-container .filter-field {
            min-width: 100%;
        }

        /* Hide desktop table, show mobile cards */
        .reuniones-table-wrapper {
            display: none;
        }
        .reuniones-mobile-cards {
            display: block;
        }

        .reuniones-tabs {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .reuniones-header h1 {
            font-size: 1.2rem;
        }
        .reuniones-actions .btn {
            font-size: 0.75rem;
            padding: 0.5rem 0.4rem;
        }
    }
</style>

<script>
    function copyInvitation(title, datetime, link) {
        const textToCopy = `${title}\n${datetime}\n${link}\nTe esperamos en la reunión`;
        
        navigator.clipboard.writeText(textToCopy).then(() => {
            Swal.fire({
                title: 'Copiado',
                text: 'Invitación copiada al portapapeles',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        }).catch(err => {
            console.error('Error al copiar: ', err);
            Swal.fire('Error', 'No se pudo copiar al portapapeles', 'error');
        });
    }

    function sendWhatsApp(title, datetime, link, clientPhone, groupPhone) {
        const textToCopy = `Hola, te comparto el enlace para nuestra reunión:\n\n*${title}*\n📅 ${datetime}\n🔗 ${link}\n\nTe esperamos.`;
        
        // Escape backticks in textToCopy for the onclick handler
        const escapedMsg = textToCopy.replace(/`/g, '\\`');

        const optionsHtml = `
            <div style="display:flex; flex-direction:column; gap:10px; margin-top:15px;">
                <button class="swal2-confirm swal2-styled" style="background-color: #25D366; width:100%; margin:0; display:flex; align-items:center; justify-content:center; gap:8px;" onclick="executeSendWA('${groupPhone}', \`${escapedMsg}\`)">
                    <i class="ph ph-users"></i> Enviar al Grupo del Proyecto
                </button>
                <button class="swal2-confirm swal2-styled" style="background-color: #128C7E; width:100%; margin:0; display:flex; align-items:center; justify-content:center; gap:8px;" onclick="executeSendWA('${clientPhone}', \`${escapedMsg}\`)">
                    <i class="ph ph-user"></i> Enviar al Cliente Directo
                </button>
            </div>
        `;
        
        Swal.fire({
            title: 'Enviar Invitación',
            html: optionsHtml,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Cancelar'
        });
    }

    function executeSendWA(phone, msg) {
        if (!phone || phone === 'undefined' || phone === 'null') {
            Swal.fire('Atención', 'No hay un número o ID de grupo registrado para esta opción.', 'warning');
            return;
        }
        Swal.fire({
            title: 'Enviando...',
            text: 'Por favor espera',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch('ajax/send_meet_whatsapp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: phone, message: msg })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Swal.fire('Enviado', 'La invitación se envió por WhatsApp correctamente.', 'success');
            } else {
                Swal.fire('Error', res.error || 'No se pudo enviar el mensaje', 'error');
            }
        })
        .catch(err => {
            Swal.fire('Error', 'Error de conexión', 'error');
        });
    }
</script>

<!-- Header + Filters Card -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="reuniones-header">
        <div style="width: 100%;">
            <div class="reuniones-tabs">
                <a href="index.php?module=reuniones&action=index" class="reuniones-tab active">
                    <i class="ph ph-list-bullets"></i> Historial
                </a>
                <a href="index.php?module=reuniones&action=rooms" class="reuniones-tab">
                    <i class="ph ph-buildings"></i> Salas
                </a>
            </div>
            <h1>
                <i class="ph ph-video-camera" style="color: #ea4335;"></i> Historial de Reuniones <span style="background: color-mix(in srgb, #ea4335 15%, transparent); color: #ea4335; font-size: 0.75rem; padding: 3px 8px; border-radius: 12px; vertical-align: middle; margin-left: 8px; font-weight: 700; border: 1px solid color-mix(in srgb, #ea4335 30%, transparent); text-transform: uppercase; letter-spacing: 0.5px;">Beta</span>
            </h1>
            <p>Visualiza tus reuniones, grabaciones y resúmenes de Gemini.</p>
        </div>
        <div class="reuniones-actions">
            <button onclick="syncGeminiNotes()" class="btn btn-outline" style="color: #10b981; border-color: #10b981;" id="btn-sync-notes">
                <i class="ph ph-arrows-clockwise"></i> <span class="btn-label">Sincronizar</span>
            </button>
            <button onclick="if(window.openMeetModal) window.openMeetModal();" class="btn btn-primary" style="background: #ea4335; border-color: #ea4335;">
                <i class="ph ph-calendar-plus"></i> <span class="btn-label">Programar</span>
            </button>
        </div>
    </div>

    <!-- Mobile filter toggle -->
    <button type="button" class="filter-toggle-btn" id="filter-toggle" onclick="toggleFilters()">
        <span>
            <i class="ph ph-funnel"></i> Filtros
            <?php if($activeFilters): ?>
                <span class="badge-count"><?php echo $filterCount; ?></span>
            <?php endif; ?>
        </span>
        <i class="ph ph-caret-down chevron"></i>
    </button>

    <form id="reuniones-filter-form" method="GET" action="index.php">
        <input type="hidden" name="module" value="reuniones">
        
        <div class="filters-container <?php echo $activeFilters ? 'show' : ''; ?>" id="filters-container">
            <div class="filter-field">
                <label><i class="ph ph-magnifying-glass"></i> Buscar Motivo/Resumen</label>
                <input type="text" name="search" id="filter-search" class="form-control" placeholder="Ej: Estrategia de contenidos..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-field">
                <label><i class="ph ph-buildings"></i> Marca</label>
                <select name="brand_id" id="filter-brand" class="form-control">
                    <option value="">Todas las marcas</option>
                    <?php foreach($marcas as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $brand_id == $m['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-field">
                <label><i class="ph ph-flag"></i> Estado</label>
                <select name="status" id="filter-status" class="form-control">
                    <option value="">Todos los estados</option>
                    <option value="Programada" <?php echo $status == 'Programada' ? 'selected' : ''; ?>>Programada</option>
                    <option value="Completada" <?php echo $status == 'Completada' ? 'selected' : ''; ?>>Completada</option>
                    <option value="Cancelada" <?php echo $status == 'Cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                    <option value="Eliminada" <?php echo $status == 'Eliminada' ? 'selected' : ''; ?>>Papelera (Eliminadas)</option>
                </select>
            </div>
            
            <div style="display:flex; align-items:flex-end;">
                <button type="submit" class="btn btn-outline" style="display:none;"><i class="ph ph-funnel"></i> Filtrar</button>
                <a href="index.php?module=reuniones" class="btn btn-outline" style="color:var(--text-muted); white-space:nowrap;"><i class="ph ph-x"></i> Limpiar</a>
            </div>
        </div>
    </form>
</div>

<!-- List -->
<div class="card" style="padding: 0;" id="reuniones-list-container">
    <?php if(empty($reuniones)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="ph ph-video-camera-slash"></i>
            </div>
            <h3>No se encontraron reuniones</h3>
            <p>No hay reuniones que coincidan con los filtros actuales. Intenta ajustar los criterios de búsqueda o programa una nueva reunión.</p>
        </div>
    <?php else: ?>

        <!-- Desktop Table -->
        <div class="reuniones-table-wrapper">
            <table class="reuniones-table">
                <thead>
                    <tr>
                        <th>Reunión</th>
                        <th>Fecha y Hora</th>
                        <th class="col-estado">Estado</th>
                        <th class="col-enlaces">Enlaces</th>
                        <th class="col-acciones">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($reuniones as $r): ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:0.75rem;">
                                    <?php if($r['brand_logo']): ?>
                                        <img src="<?php echo htmlspecialchars($r['brand_logo']); ?>" class="table-avatar" style="width:40px; height:40px; border-radius:8px; object-fit:cover;">
                                    <?php else: ?>
                                        <div class="table-avatar" style="width:40px; height:40px; border-radius:8px; background:var(--primary-color-light); display:flex; align-items:center; justify-content:center; color:var(--primary-color); font-weight:bold;">
                                            <?php echo substr($r['brand_name'], 0, 1); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.95rem; color: var(--color-title);">
                                            <?php echo htmlspecialchars($r['motivo']); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; margin-top:0.15rem;">
                                            <span><?php echo htmlspecialchars($r['brand_name']); ?></span>
                                            <?php if(!empty($r['tags'])): ?>
                                                <?php $tags = explode(',', $r['tags']); foreach($tags as $t): $t = trim($t); if(!$t) continue; ?>
                                                    <span class="tag-pill"><i class="ph ph-tag" style="margin-right:2px;"></i><?php echo htmlspecialchars($t); ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.9rem; font-weight: 500;">
                                    <?php echo date('d M, Y', strtotime($r['fecha_hora'])); ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?php echo date('h:i A', strtotime($r['fecha_hora'])); ?>
                                </div>
                            </td>
                            <td class="col-estado">
                                <?php
                                    $statusClass = 'status-programada';
                                    if($r['estado'] === 'Completada') $statusClass = 'status-completada';
                                    elseif($r['estado'] === 'Cancelada') $statusClass = 'status-cancelada';
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo $r['estado']; ?></span>
                            </td>
                            <td class="col-enlaces">
                                <div style="display:flex; justify-content:center; gap:0.35rem;">
                                    <?php if($r['meet_link']): ?>
                                        <a href="<?php echo htmlspecialchars($r['meet_link']); ?>" target="_blank" class="icon-btn meet" title="Abrir Google Meet">
                                            <i class="ph ph-video-camera"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if($r['recording_link']): ?>
                                        <a href="<?php echo htmlspecialchars($r['recording_link']); ?>" target="_blank" class="icon-btn recording" title="Ver Grabación">
                                            <i class="ph ph-play-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if($r['resumen']): ?>
                                        <span class="icon-btn gemini" title="Notas Gemini Guardadas" style="cursor:default;">
                                            <i class="ph ph-sparkle"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="col-acciones">
                                <div style="display: flex; gap: 0.35rem; justify-content: flex-end;">
                                    <?php if($r['meet_link']): ?>
                                    <button class="icon-btn copy" onclick="copyInvitation('<?php echo addslashes($r['motivo']); ?>', '<?php echo date('h:i A d M, Y', strtotime($r['fecha_hora'])); ?>', '<?php echo $r['meet_link']; ?>')" title="Copiar Invitación">
                                        <i class="ph ph-copy"></i>
                                    </button>
                                    <button class="icon-btn" style="color: #25D366; border-color: #25D366; background: transparent;" onmouseover="this.style.background='#dcf8c6'" onmouseout="this.style.background='transparent'" onclick="sendWhatsApp('<?php echo addslashes($r['motivo']); ?>', '<?php echo date('h:i A d M, Y', strtotime($r['fecha_hora'])); ?>', '<?php echo $r['meet_link']; ?>', '<?php echo htmlspecialchars($r['client_whatsapp'] ?? ''); ?>', '<?php echo htmlspecialchars($r['whatsapp_group'] ?? ''); ?>')" title="Enviar por WhatsApp">
                                        <i class="ph ph-whatsapp-logo"></i>
                                    </button>
                                    <?php endif; ?>
                                    <a href="index.php?module=reuniones&action=view&id=<?php echo $r['id']; ?>" class="btn btn-primary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; border-radius: 8px;">
                                        Detalle <i class="ph ph-arrow-right"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div class="reuniones-mobile-cards" style="padding: 0.75rem;">
            <?php foreach($reuniones as $idx => $r): ?>
                <?php
                    $cardStatusClass = 'card-programada';
                    if($r['estado'] === 'Completada') $cardStatusClass = 'card-completada';
                    elseif($r['estado'] === 'Cancelada') $cardStatusClass = 'card-cancelada';
                ?>
                <div class="reunion-card <?php echo $cardStatusClass; ?>" style="animation-delay: <?php echo $idx * 0.05; ?>s;">
                    <div class="reunion-card-header">
                        <?php if($r['brand_logo']): ?>
                            <img src="<?php echo htmlspecialchars($r['brand_logo']); ?>" class="reunion-card-avatar">
                        <?php else: ?>
                            <div class="reunion-card-avatar-placeholder">
                                <?php echo substr($r['brand_name'], 0, 1); ?>
                            </div>
                        <?php endif; ?>
                        <div style="flex:1; min-width:0;">
                            <div class="reunion-card-title"><?php echo htmlspecialchars($r['motivo']); ?></div>
                            <div class="reunion-card-brand"><?php echo htmlspecialchars($r['brand_name']); ?></div>
                        </div>
                        <?php
                            $statusClass = 'status-programada';
                            if($r['estado'] === 'Completada') $statusClass = 'status-completada';
                            elseif($r['estado'] === 'Cancelada') $statusClass = 'status-cancelada';
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>" style="flex-shrink:0;"><?php echo $r['estado']; ?></span>
                    </div>

                    <?php if(!empty($r['tags'])): ?>
                        <div class="reunion-card-tags">
                            <?php $tags = explode(',', $r['tags']); foreach($tags as $t): $t = trim($t); if(!$t) continue; ?>
                                <span class="tag-pill"><i class="ph ph-tag" style="margin-right:2px;"></i><?php echo htmlspecialchars($t); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="reunion-card-meta">
                        <div class="reunion-card-date">
                            <i class="ph ph-calendar-blank"></i>
                            <?php echo date('d M, Y', strtotime($r['fecha_hora'])); ?> · <?php echo date('h:i A', strtotime($r['fecha_hora'])); ?>
                        </div>
                        <div class="reunion-card-actions">
                            <?php if($r['meet_link']): ?>
                                <a href="<?php echo htmlspecialchars($r['meet_link']); ?>" target="_blank" class="icon-btn meet" title="Meet">
                                    <i class="ph ph-video-camera"></i>
                                </a>
                            <?php endif; ?>
                            <?php if($r['recording_link']): ?>
                                <a href="<?php echo htmlspecialchars($r['recording_link']); ?>" target="_blank" class="icon-btn recording" title="Grabación">
                                    <i class="ph ph-play-circle"></i>
                                </a>
                            <?php endif; ?>
                            <?php if($r['meet_link']): ?>
                                <button class="icon-btn copy" onclick="copyInvitation('<?php echo addslashes($r['motivo']); ?>', '<?php echo date('h:i A d M, Y', strtotime($r['fecha_hora'])); ?>', '<?php echo $r['meet_link']; ?>')" title="Copiar">
                                    <i class="ph ph-copy"></i>
                                </button>
                                <button class="icon-btn" style="color: #25D366; border-color: #25D366; background: transparent;" onclick="sendWhatsApp('<?php echo addslashes($r['motivo']); ?>', '<?php echo date('h:i A d M, Y', strtotime($r['fecha_hora'])); ?>', '<?php echo $r['meet_link']; ?>', '<?php echo htmlspecialchars($r['client_whatsapp'] ?? ''); ?>', '<?php echo htmlspecialchars($r['whatsapp_group'] ?? ''); ?>')" title="Enviar por WhatsApp">
                                    <i class="ph ph-whatsapp-logo"></i>
                                </button>
                            <?php endif; ?>
                            <a href="index.php?module=reuniones&action=view&id=<?php echo $r['id']; ?>" class="icon-btn" style="background:var(--primary-color); color:white; border-color:var(--primary-color);" title="Ver detalle">
                                <i class="ph ph-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<script>
function toggleFilters() {
    const container = document.getElementById('filters-container');
    const btn = document.getElementById('filter-toggle');
    container.classList.toggle('show');
    btn.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reuniones-filter-form');
    const listContainer = document.getElementById('reuniones-list-container');
    let timeout = null;

    const fetchResults = () => {
        const url = new URL(form.action, window.location.origin);
        const params = new URLSearchParams(new FormData(form));
        url.search = params.toString();

        const skeletonHtml = `
            <div style="padding: 1.5rem;">
                <div class="sk-row" style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border-color); padding-bottom:1.5rem; margin-bottom:1.5rem;">
                    <div style="display:flex; gap:1rem; width:40%;">
                        <div class="sk-box" style="width:40px; height:40px; border-radius:8px;"></div>
                        <div style="display:flex; flex-direction:column; gap:0.5rem; width:100%;">
                            <div class="sk-box" style="width:80%; height:16px;"></div>
                            <div class="sk-box" style="width:50%; height:12px;"></div>
                        </div>
                    </div>
                    <div class="sk-box" style="width:20%; height:20px; align-self:center;"></div>
                    <div class="sk-box" style="width:10%; height:30px; align-self:center;"></div>
                </div>
                <div class="sk-row" style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border-color); padding-bottom:1.5rem; margin-bottom:1.5rem;">
                    <div style="display:flex; gap:1rem; width:40%;">
                        <div class="sk-box" style="width:40px; height:40px; border-radius:8px; animation-delay:0.1s;"></div>
                        <div style="display:flex; flex-direction:column; gap:0.5rem; width:100%;">
                            <div class="sk-box" style="width:70%; height:16px; animation-delay:0.1s;"></div>
                            <div class="sk-box" style="width:60%; height:12px; animation-delay:0.1s;"></div>
                        </div>
                    </div>
                    <div class="sk-box" style="width:20%; height:20px; align-self:center; animation-delay:0.1s;"></div>
                    <div class="sk-box" style="width:10%; height:30px; align-self:center; animation-delay:0.1s;"></div>
                </div>
                <div class="sk-row" style="display:flex; justify-content:space-between; padding-bottom:1.5rem;">
                    <div style="display:flex; gap:1rem; width:40%;">
                        <div class="sk-box" style="width:40px; height:40px; border-radius:8px; animation-delay:0.2s;"></div>
                        <div style="display:flex; flex-direction:column; gap:0.5rem; width:100%;">
                            <div class="sk-box" style="width:75%; height:16px; animation-delay:0.2s;"></div>
                            <div class="sk-box" style="width:45%; height:12px; animation-delay:0.2s;"></div>
                        </div>
                    </div>
                    <div class="sk-box" style="width:20%; height:20px; align-self:center; animation-delay:0.2s;"></div>
                    <div class="sk-box" style="width:10%; height:30px; align-self:center; animation-delay:0.2s;"></div>
                </div>
            </div>
        `;
        listContainer.innerHTML = skeletonHtml;

        fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTable = doc.getElementById('reuniones-list-container');
            if (newTable) {
                listContainer.innerHTML = newTable.innerHTML;
                // Update URL without reloading
                window.history.replaceState({}, '', url.toString());
            }
        });
    };

    // Listen to changes in selects
    document.getElementById('filter-brand').addEventListener('change', fetchResults);
    document.getElementById('filter-status').addEventListener('change', fetchResults);

    // Listen to typing in search (with debounce)
    document.getElementById('filter-search').addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(fetchResults, 400);
    });

    // Prevent default form submission to keep it pure AJAX
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        fetchResults();
    });
});

async function syncGeminiNotes() {
    const btn = document.getElementById('btn-sync-notes');
    if (!btn) return;
    
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Sincronizando...';
    btn.disabled = true;

    try {
        const res = await fetch('cron/fetch_gemini_notes.php', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        let data;
        try {
            data = await res.json();
        } catch (e) {
            // Fallback en caso de que devuelva texto (errores de PHP)
            const text = await res.text();
            data = { success: false, log: [text] };
        }
        
        let logHtml = '';
        if (data.log && data.log.length > 0) {
            logHtml = '<ul style="text-align: left; background: var(--bg-color, #f8fafc); padding: 1rem 1rem 1rem 2rem; border-radius: 8px; font-size: 0.9rem; max-height: 300px; overflow-y: auto; margin: 0; color: var(--text-main, #334155); border: 1px solid var(--border-color, #e2e8f0);">';
            data.log.forEach(item => {
                logHtml += `<li style="margin-bottom: 0.5rem; line-height: 1.4;">${item}</li>`;
            });
            logHtml += '</ul>';
        } else {
            logHtml = '<p style="color: var(--text-muted); margin: 0; padding: 1rem; text-align: center;">No se encontraron nuevas actualizaciones de correos.</p>';
        }

        Swal.fire({
            title: data.success ? 'Sincronización Completada' : 'Aviso',
            html: logHtml,
            icon: data.success ? 'success' : 'warning',
            confirmButtonText: 'Continuar',
            allowOutsideClick: false
        }).then(() => {
            location.reload();
        });

    } catch (err) {
        console.error(err);
        Swal.fire('Error', 'No se pudo conectar al sincronizador', 'error');
    } finally {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
