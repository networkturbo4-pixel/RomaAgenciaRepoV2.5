<?php
// modules/clients/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

// Fetch all clients
$stmt = $db->query("
    SELECT c.*, 
           GROUP_CONCAT(b.name SEPARATOR '||') as brands, 
           GROUP_CONCAT(b.logo SEPARATOR '||') as logos
    FROM clients c
    LEFT JOIN client_brands b ON c.id = b.client_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all services
$stmtServices = $db->query("SELECT id, name FROM services WHERE deleted_at IS NULL ORDER BY name ASC");
$all_services = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<script>
    const SYSTEM_SERVICES = <?php echo json_encode($all_services); ?>;
</script>

<style>
/* Client List Modern Styles */
.clients-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.clients-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.clients-header-left h1 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-main);
    margin: 0;
}

.clients-count {
    background: var(--bg-sidebar);
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: var(--radius-full);
}

/* Search & Filter Bar */
.clients-toolbar {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.clients-search {
    flex: 1;
    min-width: 220px;
    position: relative;
}

.clients-search i {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 1rem;
}

.clients-search input {
    width: 100%;
    padding: 0.55rem 0.75rem 0.55rem 2.25rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    font-size: 13px;
    font-family: 'Inter', sans-serif;
    color: var(--text-main);
    background: var(--bg-surface);
    transition: all 0.2s;
}

.clients-search input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 12%, transparent);
}

.clients-search input::placeholder {
    color: var(--text-muted);
}

.clients-view-toggle {
    display: flex;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
}

.clients-view-toggle button {
    border: none;
    background: var(--bg-surface);
    padding: 0.5rem 0.65rem;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 1rem;
    transition: all 0.2s;
    display: flex;
    align-items: center;
}

.clients-view-toggle button.active {
    background: var(--primary-color);
    color: white;
}

.clients-view-toggle button:not(:last-child) {
    border-right: 1px solid var(--border-color);
}

/* Client List Container */
.clients-list {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
    background: var(--bg-surface);
}

/* Client Row */
.client-row {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    gap: 1rem;
    border-bottom: 1px solid var(--border-color);
    transition: background 0.15s;
    cursor: pointer;
}

.client-row:last-child {
    border-bottom: none;
}

.client-row:hover {
    background: color-mix(in srgb, var(--primary-color) 4%, transparent);
}

/* Avatar */
.client-avatar {
    width: 38px;
    height: 38px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    color: white;
    flex-shrink: 0;
    text-transform: uppercase;
}

/* Client Info */
.client-info {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.client-name-block {
    min-width: 160px;
    flex: 1;
}

.client-name {
    font-weight: 600;
    font-size: 13px;
    color: var(--text-main);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.client-dni {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 1px;
}

/* Contact Details */
.client-contact {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-shrink: 0;
}

.client-contact-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 12px;
    color: var(--text-muted);
    white-space: nowrap;
}

.client-contact-item i {
    font-size: 14px;
    color: var(--text-muted);
}

.client-contact-item.whatsapp i {
    color: #25d366;
}

.client-contact-item.email i {
    color: var(--primary-color);
}

/* Brands */
.client-brands {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
}

.client-brand-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    background: var(--bg-sidebar);
    border-radius: var(--radius-full);
    font-size: 11px;
    color: var(--text-main);
    font-weight: 500;
    white-space: nowrap;
}

.client-brand-badge img {
    width: 14px;
    height: 14px;
    border-radius: 3px;
    object-fit: cover;
}

.client-brand-more {
    padding: 3px 8px;
    background: var(--bg-sidebar);
    border-radius: var(--radius-full);
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 500;
}

/* Actions */
.client-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
    opacity: 0;
    transition: opacity 0.15s;
}

.client-row:hover .client-actions {
    opacity: 1;
}

.client-actions button {
    border: none;
    background: transparent;
    cursor: pointer;
    padding: 0.35rem;
    border-radius: var(--radius-sm);
    color: var(--text-muted);
    font-size: 1rem;
    transition: all 0.15s;
    display: flex;
    align-items: center;
}

.client-actions button:hover {
    background: var(--bg-sidebar);
    color: var(--primary-color);
}

.client-actions button.delete:hover {
    color: var(--danger-color);
    background: var(--color-red-bg);
}

/* Grid View */
.clients-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.client-card {
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 1rem;
    background: var(--bg-surface);
    transition: all 0.2s;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.client-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-color: var(--primary-color);
}

.client-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.client-card-header .client-avatar {
    width: 42px;
    height: 42px;
    font-size: 15px;
}

.client-card-name {
    font-weight: 600;
    font-size: 13px;
    color: var(--text-main);
}

.client-card-dni {
    font-size: 12px;
    color: var(--text-muted);
}

.client-card-contacts {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--border-color);
}

.client-card-contact {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 12px;
    color: var(--text-muted);
}

.client-card-contact i {
    font-size: 14px;
}

.client-card-brands {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}

.client-card-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.25rem;
    margin-top: auto;
}

.client-card-actions button {
    border: none;
    background: transparent;
    cursor: pointer;
    padding: 0.35rem;
    border-radius: var(--radius-sm);
    color: var(--text-muted);
    font-size: 1rem;
    transition: all 0.15s;
    display: flex;
    align-items: center;
}

.client-card-actions button:hover {
    background: var(--bg-sidebar);
    color: var(--primary-color);
}

.client-card-actions button.delete:hover {
    color: var(--danger-color);
    background: var(--color-red-bg);
}

/* Empty State */
.clients-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-muted);
}

.clients-empty i {
    font-size: 3rem;
    color: var(--border-color);
    margin-bottom: 0.75rem;
}

.clients-empty p {
    font-size: 13px;
    margin: 0.25rem 0;
}

/* Responsive */
@media (max-width: 768px) {
    .client-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.4rem;
    }

    .client-contact {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .client-actions {
        opacity: 1;
    }
}
</style>

<!-- Header -->
<div class="clients-header">
    <div class="clients-header-left">
        <h1>Clientes</h1>
        <span class="clients-count"><?php echo count($clients); ?></span>
    </div>
    <button class="btn btn-primary" onclick="ClientModule.openModal()" style="display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 13px;">
        <i class="ph ph-plus"></i> Nuevo Cliente
    </button>
</div>

<!-- Toolbar: Search + View Toggle -->
<div class="clients-toolbar">
    <div class="clients-search">
        <i class="ph ph-magnifying-glass"></i>
        <input type="search" id="clientSearch" placeholder="Buscar por nombre, DNI, teléfono o marca..." oninput="filterClients()" autocomplete="off" readonly onfocus="this.removeAttribute('readonly')">
    </div>
    <div class="clients-view-toggle">
        <button class="active" onclick="setView('list', this)" title="Vista lista"><i class="ph ph-list"></i></button>
        <button onclick="setView('grid', this)" title="Vista tarjetas"><i class="ph ph-squares-four"></i></button>
    </div>
</div>

<!-- List View -->
<div id="clientsListView" class="clients-list">
    <?php if (empty($clients)): ?>
    <div class="clients-empty">
        <i class="ph ph-users"></i>
        <p><strong>No hay clientes registrados</strong></p>
        <p>Agrega tu primer cliente para comenzar.</p>
    </div>
    <?php else: ?>
        <?php
        // Avatar color palette
        $avatarColors = ['#4f46e5','#0891b2','#059669','#d97706','#dc2626','#7c3aed','#db2777','#2563eb','#0d9488','#ea580c'];
        foreach ($clients as $i => $client):
            $initials = mb_strtoupper(mb_substr($client['name'], 0, 2));
            $avatarColor = $avatarColors[$i % count($avatarColors)];
            $brandNames = $client['brands'] ? explode('||', $client['brands']) : [];
            $brandLogos = $client['logos'] ? explode('||', $client['logos']) : [];
            $searchData = strtolower($client['name'] . ' ' . ($client['dni'] ?? '') . ' ' . ($client['whatsapp'] ?? '') . ' ' . ($client['email'] ?? '') . ' ' . ($client['brands'] ?? ''));
        ?>
        <div class="client-row" data-search="<?php echo htmlspecialchars($searchData); ?>" onclick="ClientModule.editClient(<?php echo $client['id']; ?>)">
            <div class="client-avatar" style="background: <?php echo $avatarColor; ?>;">
                <?php echo htmlspecialchars($initials); ?>
            </div>
            <div class="client-info">
                <div class="client-name-block">
                    <div class="client-name"><?php echo htmlspecialchars($client['name']); ?></div>
                    <?php if (!empty($client['dni'])): ?>
                    <div class="client-dni">DNI: <?php echo htmlspecialchars($client['dni']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="client-contact">
                    <?php if (!empty($client['whatsapp'])): ?>
                    <div class="client-contact-item whatsapp">
                        <i class="ph ph-whatsapp-logo"></i>
                        <span><?php echo htmlspecialchars($client['whatsapp']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($client['email'])): ?>
                    <div class="client-contact-item email">
                        <i class="ph ph-envelope-simple"></i>
                        <span><?php echo htmlspecialchars($client['email']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="client-brands">
                    <?php
                    $maxShow = 2;
                    foreach (array_slice($brandNames, 0, $maxShow) as $idx => $bName):
                        $bLogo = $brandLogos[$idx] ?? '';
                    ?>
                    <div class="client-brand-badge">
                        <?php if ($bLogo): ?>
                        <img src="<?php echo htmlspecialchars($bLogo); ?>" alt="">
                        <?php endif; ?>
                        <?php echo htmlspecialchars($bName); ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($brandNames) > $maxShow): ?>
                    <div class="client-brand-more">+<?php echo count($brandNames) - $maxShow; ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="client-actions" onclick="event.stopPropagation();">
                <a href="index.php?module=clients&action=social_auth&client_id=<?php echo $client['id']; ?>" class="action-btn" title="Redes Sociales" style="background:transparent; border:none; cursor:pointer; color:var(--text-muted); display:flex; align-items:center; justify-content:center; padding:8px; border-radius:6px; transition:all 0.2s;">
                    <i class="ph ph-share-network"></i>
                </a>
                <button onclick="ClientModule.editClient(<?php echo $client['id']; ?>)" title="Editar">
                    <i class="ph ph-pencil-simple"></i>
                </button>
                <button class="delete" onclick="ClientModule.deleteClient(<?php echo $client['id']; ?>)" title="Eliminar">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Grid View (hidden by default) -->
<div id="clientsGridView" class="clients-grid" style="display: none;">
    <?php if (!empty($clients)):
        foreach ($clients as $i => $client):
            $initials = mb_strtoupper(mb_substr($client['name'], 0, 2));
            $avatarColor = $avatarColors[$i % count($avatarColors)];
            $brandNames = $client['brands'] ? explode('||', $client['brands']) : [];
            $brandLogos = $client['logos'] ? explode('||', $client['logos']) : [];
            $searchData = strtolower($client['name'] . ' ' . ($client['dni'] ?? '') . ' ' . ($client['whatsapp'] ?? '') . ' ' . ($client['email'] ?? '') . ' ' . ($client['brands'] ?? ''));
    ?>
    <div class="client-card" data-search="<?php echo htmlspecialchars($searchData); ?>">
        <div class="client-card-header">
            <div class="client-avatar" style="background: <?php echo $avatarColor; ?>;">
                <?php echo htmlspecialchars($initials); ?>
            </div>
            <div>
                <div class="client-card-name"><?php echo htmlspecialchars($client['name']); ?></div>
                <?php if (!empty($client['dni'])): ?>
                <div class="client-card-dni">DNI: <?php echo htmlspecialchars($client['dni']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="client-card-contacts">
            <?php if (!empty($client['whatsapp'])): ?>
            <div class="client-card-contact">
                <i class="ph ph-whatsapp-logo" style="color:#25d366;"></i>
                <span><?php echo htmlspecialchars($client['whatsapp']); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($client['email'])): ?>
            <div class="client-card-contact">
                <i class="ph ph-envelope-simple" style="color:var(--primary-color);"></i>
                <span><?php echo htmlspecialchars($client['email']); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($brandNames)): ?>
        <div class="client-card-brands">
            <?php foreach ($brandNames as $idx => $bName):
                $bLogo = $brandLogos[$idx] ?? '';
            ?>
            <div class="client-brand-badge">
                <?php if ($bLogo): ?>
                <img src="<?php echo htmlspecialchars($bLogo); ?>" alt="">
                <?php endif; ?>
                <?php echo htmlspecialchars($bName); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="client-card-actions">
            <a href="index.php?module=clients&action=social_auth&client_id=<?php echo $client['id']; ?>" class="action-btn" title="Redes Sociales" style="background:transparent; border:none; cursor:pointer; color:var(--text-muted); display:flex; align-items:center; justify-content:center; padding:8px; border-radius:6px; transition:all 0.2s;">
                <i class="ph ph-share-network"></i>
            </a>
            <button onclick="ClientModule.editClient(<?php echo $client['id']; ?>)" title="Editar">
                <i class="ph ph-pencil-simple"></i>
            </button>
            <button class="delete" onclick="ClientModule.deleteClient(<?php echo $client['id']; ?>)" title="Eliminar">
                <i class="ph ph-trash"></i>
            </button>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- Modal Cliente -->
<div class="modal-overlay" id="clientModal">
    <div class="modal-content" style="max-width: 900px; width: 95%;">
        <div class="modal-header">
            <h2 class="modal-title" id="clientModalTitle">Nuevo Cliente</h2>
            <button class="btn-icon close-modal" onclick="ClientModule.closeModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <form id="clientForm" onsubmit="return false;" style="display: flex; gap: 2rem; flex-wrap: wrap;">
                <input type="hidden" name="client_id" id="client_id">
                
                <!-- Left Column: Client Info -->
                <div style="flex: 1; min-width: 300px;">
                    <h3 style="font-size: 14px; font-weight: 600; margin-bottom: 1rem; color: var(--primary-color);">Datos Personales</h3>
                    
                    <div style="margin-bottom: 1rem;">
                        <label class="form-label">Nombre del Cliente *</label>
                        <input type="text" class="form-control" name="name" id="client_name" placeholder="Ej: Empresa S.A." required>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label class="form-label">DNI</label>
                        <input type="text" class="form-control" name="dni" id="client_dni" placeholder="Ej: 71234567">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label class="form-label">WhatsApp</label>
                        <input type="text" class="form-control" name="whatsapp" id="client_whatsapp" placeholder="Ej: +1234567890">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" name="email" id="client_email" placeholder="correo@empresa.com">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label class="form-label">ID de Carpeta Drive (Portal)</label>
                        <input type="text" class="form-control" name="drive_folder_id" id="client_drive_folder_id" placeholder="ID de la carpeta (ej. 1A2b3C4d5E...)">
                    </div>
                </div>

                <!-- Right Column: Brands and Memberships -->
                <div style="flex: 1.5; min-width: 350px;">
                    <div style="padding: 1.25rem; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
                        <h3 style="font-size: 14px; font-weight: 600; margin-bottom: 1rem; color: var(--primary-color);">Marcas y Servicios</h3>
                        
                        <div style="display: flex; gap: 0.75rem; align-items: flex-end; margin-bottom: 1.5rem; flex-wrap: wrap; background: var(--bg-body); padding: 1rem; border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                            <div style="flex: 1; min-width: 150px;">
                                <label class="form-label" style="font-size:12px;">Nombre de Marca</label>
                                <input type="text" class="form-control" id="new_brand_name" placeholder="Ej: Mi Marca">
                            </div>
                            <div style="flex: 1; min-width: 180px;">
                                <label class="form-label" style="font-size:12px;">Logotipo (Imagen)</label>
                                <input type="file" class="form-control" id="new_brand_logo" accept="image/*">
                            </div>
                            <button type="button" class="btn btn-primary" onclick="ClientModule.addBrand()" style="height: 38px; font-size: 13px;">
                                <i class="ph ph-plus"></i> Añadir Marca
                            </button>
                        </div>

                        <!-- Contenedor dinámico de marcas -->
                        <div id="brandsList" style="display: flex; flex-direction: column; gap: 1rem;">
                            <!-- Se insertan aquí las marcas via JS -->
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
            <button class="btn btn-outline" onclick="ClientModule.closeModal()">Cancelar</button>
            <button class="btn btn-primary" id="btnSaveClient" onclick="ClientModule.saveClient()">
                <span class="btn-text">Guardar Cliente</span>
            </button>
        </div>
    </div>
</div>

<script>
// Search filter
function filterClients() {
    const query = document.getElementById('clientSearch').value.toLowerCase().trim();
    // Filter list view
    document.querySelectorAll('.client-row').forEach(row => {
        const data = row.getAttribute('data-search') || '';
        row.style.display = data.includes(query) ? '' : 'none';
    });
    // Filter grid view
    document.querySelectorAll('.client-card').forEach(card => {
        const data = card.getAttribute('data-search') || '';
        card.style.display = data.includes(query) ? '' : 'none';
    });
}

// View toggle
function setView(view, btn) {
    document.querySelectorAll('.clients-view-toggle button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('clientsListView').style.display = view === 'list' ? '' : 'none';
    document.getElementById('clientsGridView').style.display = view === 'grid' ? '' : 'none';
}
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.hash === '#new') {
        setTimeout(() => {
            if (typeof ClientModule !== 'undefined' && ClientModule.openModal) {
                ClientModule.openModal();
            }
        }, 500);
        history.replaceState('', document.title, window.location.pathname + window.location.search);
    }
});
</script>

<script src="assets/js/modules/clients.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
