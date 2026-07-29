<?php
// modules/contracts/index.php
if (!isset($_SESSION['user_id']) || !in_array('contracts', $_SESSION['user_permissions'] ?? [])) {
    header("Location: index.php?module=auth&action=login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'delete_contract') {
    $delete_uuid = $_POST['delete_uuid'] ?? '';
    if ($delete_uuid) {
        $stmtDel = $db->prepare("DELETE FROM contracts WHERE uuid = ?");
        $stmtDel->execute([$delete_uuid]);
        header("Location: index.php?module=contracts&action=index&msg=deleted");
        exit;
    }
}

// Fetch global settings
$stmt = $db->query("SELECT * FROM settings");

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$service_filter = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
$folder_filter = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;

// Fetch services and folders for filters
$services = $db->query("SELECT id, name FROM services WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$folders = $db->query("SELECT * FROM contract_folders ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$query = "
    SELECT c.*, 
           t.name as template_name, 
           cl.name as client_name, 
           u.name as staff_name,
           s.name as service_name,
           f.name as folder_name
    FROM contracts c
    LEFT JOIN contract_templates t ON c.template_id = t.id
    LEFT JOIN clients cl ON c.client_id = cl.id
    LEFT JOIN users u ON c.staff_id = u.id
    LEFT JOIN services s ON c.service_id = s.id
    LEFT JOIN contract_folders f ON c.folder_id = f.id
    WHERE 1=1
";
$params = [];

if ($search) {
    $query .= " AND (c.title LIKE ? OR cl.name LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $query .= " AND c.status = ?";
    $params[] = $status_filter;
}

if ($service_filter > 0) {
    $query .= " AND c.service_id = ?";
    $params[] = $service_filter;
}

if ($folder_filter > 0) {
    $query .= " AND c.folder_id = ?";
    $params[] = $folder_filter;
}

$query .= " ORDER BY c.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusBadge($status) {
    switch ($status) {
        case 'draft': return '<span class="status-badge status-draft"><i class="ph ph-pencil-simple"></i> Borrador</span>';
        case 'pending': return '<span class="status-badge status-pending"><i class="ph ph-clock"></i> Pendiente</span>';
        case 'signed': return '<span class="status-badge status-signed"><i class="ph ph-check-circle"></i> Firmado</span>';
        case 'cancelled': return '<span class="status-badge status-cancelled"><i class="ph ph-x-circle"></i> Cancelado</span>';
        default: return '';
    }
}
?>

<?php include 'includes/header.php'; ?>

<style>
    .filters-bar {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        background: var(--bg-surface);
        padding: 1rem;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        flex-wrap: wrap;
    }
    .filters-bar input, .filters-bar select {
        padding: 0.6rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--bg-body);
        color: var(--color-title);
        outline: none;
        font-family: inherit;
    }
    .filters-bar input:focus, .filters-bar select:focus {
        border-color: var(--primary-color);
    }
    .search-box { flex: 1; min-width: 250px; position: relative; }
    .search-box i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
    .search-box input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; }
    
    .status-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 12px; font-weight: 600; }
    .status-draft { background: rgba(107, 114, 128, 0.1); color: #4b5563; }
    .status-pending { background: rgba(245, 158, 11, 0.1); color: #d97706; }
    .status-signed { background: rgba(16, 185, 129, 0.1); color: #059669; }
    .status-cancelled { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
    
    .contract-card { background: var(--bg-surface); border-radius: var(--radius-lg); padding: var(--space-4); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02); border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: var(--space-3); transition: transform 0.2s, box-shadow 0.2s; }
    .contract-card:hover { transform: translateY(-2px); box-shadow: 0 12px 20px rgba(0, 0, 0, 0.05); }
    [data-theme="dark"] .contract-card { box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
    [data-theme="dark"] .contract-card:hover { box-shadow: 0 12px 20px rgba(0,0,0,0.4); }
    
    .cc-header { display: flex; justify-content: space-between; align-items: flex-start; }
    .cc-title { font-size: 1.15rem; font-weight: 700; color: var(--color-title); margin-bottom: 0.25rem; }
    .cc-meta { font-size: 13px; color: var(--text-muted); display: flex; gap: 1rem; flex-wrap: wrap; }
    
    .cc-entity { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.5rem; background: var(--bg-body); border-radius: var(--radius-md); font-size: 13px; font-weight: 600; border: 1px solid var(--border-color); color: var(--color-title); }
    .cc-client i { color: var(--primary-color); }
    .cc-staff i { color: #8b5cf6; }
    
    .cc-footer { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: var(--space-3); border-top: 1px solid var(--border-color); }
    .contracts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
    .btn-icon-text { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.75rem; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; transition: all 0.2s; border: none; }
    .btn-view { background: var(--primary-color); color: white; }
    .btn-view:hover { background: var(--primary-dark); }
    .btn-copy { background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); }
    .btn-copy:hover { background: var(--bg-body); color: var(--color-title); }
</style>

<div class="main-content">
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 1.25rem;">
            <div style="width: 56px; height: 56px; background: var(--bg-body); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
                <i class="ph ph-signature" style="font-size: 1.75rem; color: var(--primary-color);"></i>
            </div>
            <div>
                <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Contratos Digitales</h1>
                <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Gestiona los contratos con tus clientes y equipo.</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <a href="index.php?module=contracts&action=templates" class="btn btn-outline" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none;">
                <i class="ph ph-files"></i> Plantillas
            </a>
            <a href="index.php?module=contracts&action=form" class="btn btn-primary" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none;">
                <i class="ph ph-plus"></i> Nuevo Contrato
            </a>
        </div>
    </div>

    <form class="filters-bar" method="GET" action="index.php">
        <input type="hidden" name="module" value="contracts">
        <input type="hidden" name="action" value="index">
        
        <div class="search-box">
            <i class="ph ph-magnifying-glass"></i>
            <input type="text" name="search" placeholder="Buscar por título, cliente o personal..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <select name="status" style="width: 150px;" onchange="this.form.submit()">
            <option value="">Estado</option>
            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Borrador</option>
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pendiente de Firma</option>
            <option value="signed" <?php echo $status_filter === 'signed' ? 'selected' : ''; ?>>Firmado</option>
            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
        </select>
        
        <select name="service_id" style="width: 150px;" onchange="this.form.submit()">
            <option value="0">Servicio</option>
            <?php foreach ($services as $srv): ?>
                <option value="<?php echo $srv['id']; ?>" <?php echo $service_filter == $srv['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($srv['name']); ?></option>
            <?php endforeach; ?>
        </select>
        
        <select name="folder_id" style="width: 150px;" onchange="this.form.submit()">
            <option value="0">Carpeta</option>
            <?php foreach ($folders as $f): ?>
                <option value="<?php echo $f['id']; ?>" <?php echo $folder_filter == $f['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($f['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (empty($contracts)): ?>
        <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: var(--radius-lg);">
            <div style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"><i class="ph ph-signature"></i></div>
            <h3 style="color: var(--color-title); font-size: 1.2rem; margin-bottom: 0.5rem;">No hay contratos</h3>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Aún no has generado ningún contrato digital.</p>
            <a href="index.php?module=contracts&action=form" class="btn btn-primary"><i class="ph ph-plus"></i> Crear el primero</a>
        </div>
    <?php else: ?>
        <div class="contracts-grid">
            <?php foreach ($contracts as $c): ?>
                <div class="contract-card">
                    <div class="cc-header">
                        <div>
                            <div class="cc-title"><?php echo htmlspecialchars($c['title']); ?></div>
                            <div class="cc-meta">
                                <span><i class="ph ph-calendar-blank"></i> <?php echo date('d M, Y', strtotime($c['created_at'])); ?></span>
                                <?php if ($c['signed_at']): ?>
                                    <span style="color:#059669;"><i class="ph ph-check-square-offset"></i> Firmado el <?php echo date('d M', strtotime($c['signed_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php echo getStatusBadge($c['status']); ?>
                    </div>
                    
                    <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                        <?php if ($c['client_id']): ?>
                            <div class="cc-entity cc-client"><i class="ph-fill ph-user-circle"></i> Cliente: <?php echo htmlspecialchars($c['client_name']); ?></div>
                        <?php endif; ?>
                        <?php if ($c['staff_id']): ?>
                            <div class="cc-entity cc-staff"><i class="ph-fill ph-identification-card"></i> Personal: <?php echo htmlspecialchars($c['staff_name']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="cc-footer">
                        <?php if ($c['status'] === 'pending'): ?>
                            <button class="btn-icon-text btn-copy" onclick="copyLink('<?php echo htmlspecialchars($global_settings['site_url'] ?? 'http://localhost/CESARMENDOZA'); ?>/index.php?module=public&action=contract&uuid=<?php echo $c['uuid']; ?>')">
                                <i class="ph ph-link"></i> Copiar Link
                            </button>
                        <?php else: ?>
                            <div></div> <!-- spacer -->
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <button type="button" class="btn-icon-text" style="color: #dc2626; border: 1px solid #fca5a5; background: transparent; padding: 0.5rem 0.6rem;" onclick="deleteContract('<?php echo $c['uuid']; ?>')" title="Eliminar Contrato">
                                <i class="ph ph-trash"></i>
                            </button>
                            <a href="index.php?module=public&action=contract&uuid=<?php echo $c['uuid']; ?>" target="_blank" class="btn-icon-text btn-view">
                                <i class="ph ph-eye"></i> Ver
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<form id="deleteContractForm" method="POST" style="display: none;">
    <input type="hidden" name="action_type" value="delete_contract">
    <input type="hidden" name="delete_uuid" id="delete_uuid">
</form>

<script>
function deleteContract(uuid) {
    if (confirm("¿Estás seguro de eliminar este contrato? Esta acción es irreversible.")) {
        document.getElementById('delete_uuid').value = uuid;
        document.getElementById('deleteContractForm').submit();
    }
}

function copyLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert("Enlace copiado al portapapeles. ¡Envíalo por WhatsApp!");
    }).catch(err => {
        console.error('Error al copiar: ', err);
    });
}
</script>

<?php include 'includes/footer.php'; ?>
