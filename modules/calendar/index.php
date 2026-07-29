<?php
// modules/calendar/index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

require_once 'includes/header.php';

try {
    // Fetch all work orders for the dropdown
    $stmtWO = $db->query("SELECT id, correlativo, brand_name FROM work_orders ORDER BY id DESC");
    $workOrders = $stmtWO->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all users for team selection
    $stmtUsers = $db->query("SELECT id, name, email FROM users ORDER BY name ASC");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all projects with their work order data
    $stmtProjects = $db->query("
        SELECT p.*, w.correlativo, w.brand_name, w.data, w.public_token, w.is_archived
        FROM projects p
        JOIN work_orders w ON p.work_order_id = w.id
        ORDER BY p.id DESC
    ");
    $projects = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);

    // Get brand logos based on brand_name
    $stmtBrands = $db->query("SELECT name, logo FROM client_brands");
    $brandLogos = [];
    while ($row = $stmtBrands->fetch(PDO::FETCH_ASSOC)) {
        $brandLogos[$row['name']] = $row['logo'];
    }

    $activeProjects = [];
    $archivedProjects = [];

    $currentUserId = $_SESSION['user_id'];
    
    // Check admin status using role_id from DB (role_id 1 = admin)
    $stmtUserRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmtUserRole->execute([$currentUserId]);
    $currentRoleId = (int)$stmtUserRole->fetchColumn();
    $isAdmin = ($currentRoleId === 1);

    foreach ($projects as $proj) {
        $teamMembers = json_decode($proj['team_members'], true) ?: [];
        
        // Filter: If user is not admin, they must be in the team
        if (!$isAdmin) {
            if (!in_array((string)$currentUserId, $teamMembers) && !in_array((int)$currentUserId, $teamMembers)) {
                continue;
            }
        }

        $data = json_decode($proj['data'], true) ?: [];
        $proj['logo'] = $brandLogos[$proj['brand_name']] ?? '';
        $proj['servicio'] = $data['servicio'] ?? 'Servicio General';
        $proj['redes'] = $data['redes'] ?? '';
        
        if ($proj['status'] === 'active') {
            $activeProjects[] = $proj;
        } else {
            $archivedProjects[] = $proj;
        }
    }

} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
}
?>

<style>
    .calendar-body-padding {
        padding: 1.5rem;
    }
    .calendar-actions-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .calendar-tabs {
        display: flex;
        gap: 1rem;
    }
    @media (max-width: 768px) {
        .calendar-main-card {
            border-radius: 0; /* optional: edge-to-edge on mobile */
            border-left: none;
            border-right: none;
        }
        .calendar-body-padding {
            padding: 0.5rem;
        }
        .calendar-actions-header {
            padding: 1rem 0.5rem;
            flex-direction: column;
            align-items: stretch;
        }
        .calendar-tabs {
            width: 100%;
        }
        .calendar-tabs .btn {
            flex: 1;
            justify-content: center;
        }
        #btn-new-project {
            width: 100%;
            justify-content: center;
        }
    }

    /* Cards Grid Styles (Imported from project_board for consistency) */
    .mc-card {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        display: flex;
        flex-direction: column;
        gap: 1rem;
        transition: transform var(--transition-fast), box-shadow var(--transition-fast);
    }
    .mc-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.08);
    }
    .mc-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--color-title);
        margin: 0;
    }
    .mc-stats {
        display: flex;
        gap: 0.75rem;
    }
    .mc-stat-box {
        flex: 1;
        background: var(--bg-color);
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
        border: 1px solid var(--border-color);
    }
    [data-theme="dark"] .mc-stat-box {
        background: var(--bg-color);
    }
    .mc-stat-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .mc-divider {
        border: 0;
        border-top: 1px solid var(--border-color);
        margin: 0;
    }
    .mc-footer-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .mc-status {
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0.3rem 0.6rem;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .status-pendiente { background: rgba(245, 158, 11, 0.15); color: #d97706; }
    .status-en_progreso { background: rgba(79, 70, 229, 0.15); color: var(--primary-color); }
    .status-finalizado { background: rgba(16, 185, 129, 0.15); color: var(--secondary-color); }

    .mc-actions {
        display: flex;
        gap: 1rem;
    }
    .mc-btn-text {
        background: none;
        border: none;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0;
        text-transform: uppercase;
    }
    .mc-btn-text.text-blue { color: var(--primary-color); }
    .mc-btn-text.text-red { color: var(--danger-color); }
    
    .mc-footer-bottom {
        display: flex;
        gap: 0.5rem;
    }
    .mc-btn-enter {
        flex: 1;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.6rem;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
    }
    .mc-btn-enter:hover {
        background: var(--primary-hover);
        color: white;
    }

    /* Segmented Switcher Premium */
    .segmented-control {
        display: inline-flex;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 30px;
        border: none;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }
    .segmented-btn {
        background: transparent;
        border: none;
        padding: 0.5rem 1.5rem;
        font-size: 0.85rem;
        font-weight: 700;
        color: #64748b;
        border-radius: 26px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .segmented-btn:hover:not(.active) {
        color: var(--primary-color);
    }
    .segmented-btn.active {
        background: #ffffff;
        color: var(--primary-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    [data-theme="dark"] .segmented-control {
        background: #1e293b;
    }
    [data-theme="dark"] .segmented-btn.active {
        background: var(--primary-color);
        color: white;
    }
</style>

<!-- Title and Header removed as requested -->

<?php if (isset($error)): ?>
    <div class="alert alert-danger" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: var(--radius-md); background: #fee2e2; color: #991b1b;">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="card calendar-main-card" style="margin-bottom: 2rem; padding: 0; overflow: hidden;">
    <!-- Switcher and Actions -->
    <div class="calendar-actions-header">
        <div class="segmented-control">
            <button class="segmented-btn active" id="btn-active-projects" onclick="switchView('active')">Activos</button>
            <button class="segmented-btn" id="btn-archived-projects" onclick="switchView('archived')">Archivados</button>
        </div>
        <button class="btn btn-primary" id="btn-new-project" onclick="openNewProjectModal()">
            <i class="ph ph-plus"></i> Nuevo Proyecto
        </button>
    </div>

    <div class="calendar-body-padding">
        <!-- Active Projects Grid -->
        <div id="active-projects-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1.5rem;">
            <?php if (empty($activeProjects)): ?>
                <p class="text-muted col-span-full">No hay proyectos activos.</p>
            <?php else: ?>
                <?php foreach ($activeProjects as $project): ?>
                    <?php renderProjectCard($project); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Archived Projects Grid -->
        <div id="archived-projects-container" style="display: none; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1.5rem;">
            <?php if (empty($archivedProjects)): ?>
                <p class="text-muted col-span-full">No hay proyectos archivados.</p>
            <?php else: ?>
                <?php foreach ($archivedProjects as $project): ?>
                    <?php renderProjectCard($project); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Project Modal -->
<div class="modal-overlay" id="new-project-modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>Nuevo Proyecto</h2>
            <button class="btn-icon" onclick="closeModal('new-project-modal')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="new-project-form">
                <div class="form-group">
                    <label class="form-label">Orden de Servicio</label>
                    <select class="form-control" name="work_order_id" id="work_order_select" required onchange="fetchWorkOrderData()">
                        <option value="">Seleccione una orden de servicio...</option>
                        <?php foreach ($workOrders as $wo): ?>
                            <option value="<?php echo $wo['id']; ?>"><?php echo htmlspecialchars($wo['correlativo'] . ' - ' . $wo['brand_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="wo-details-preview" style="display: none; background: var(--bg-color); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <img id="preview-logo" src="" alt="Logo" style="width: 48px; height: 48px; object-fit: contain; border-radius: var(--radius-full); background: white; border: 1px solid var(--border-color);">
                        <div>
                            <div style="font-weight: 600;" id="preview-brand"></div>
                            <div style="font-size: 0.875rem; color: var(--text-muted);" id="preview-networks"></div>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: var(--text-muted);">Fecha de Inicio: <span id="preview-date" style="font-weight: 500; color: var(--text-main);"></span></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Asignar Equipo</label>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); padding: 1rem; border-radius: var(--radius-md);">
                        <?php foreach ($users as $user): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="team_members[]" value="<?php echo $user['id']; ?>">
                                <span><?php echo htmlspecialchars($user['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; color: #3b82f6;">
                        <i class="ph ph-google-drive-logo" style="font-size: 1.2rem;"></i> Carpeta Global del Proyecto (Opcional)
                    </label>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: -0.5rem; margin-bottom: 0.5rem;">
                        Si la dejas vacía, el sistema creará una carpeta nueva automáticamente.
                    </p>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="url" name="global_folder_link" id="inp-global-folder" class="form-control" placeholder="Enlace de la carpeta global...">
                        <input type="hidden" name="global_folder_id" id="inp-global-folder-id">
                        <button type="button" class="btn btn-outline" style="color: #3b82f6; border-color: #bfdbfe; font-weight: 600; white-space: nowrap;" onclick="promptGlobalFolder()">Elegir</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('new-project-modal')">Cancelar</button>
            <button class="btn btn-primary" onclick="saveProject()">Guardar Proyecto</button>
        </div>
    </div>
</div>

<?php require_once 'includes/custom_drive_picker.php'; ?>
<script>
    function promptGlobalFolder() {
        cdOpenPicker(null, function(folder) {
            if (!folder.url) {
                // If they picked a Shared Drive directly, it doesn't have a webViewLink in the listDrives API natively without an extra call
                // But generally they should pick a folder INSIDE the drive.
                // We'll construct a generic drive url if url is null
                folder.url = "https://drive.google.com/drive/folders/" + folder.id;
            }
            document.getElementById('inp-global-folder').value = folder.url;
            document.getElementById('inp-global-folder-id').value = folder.id;
        });
    }
</script>

<!-- Edit Project Modal -->
<div class="modal-overlay" id="edit-project-modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>Editar Proyecto</h2>
            <button class="btn-icon" onclick="closeModal('edit-project-modal')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="edit-project-form">
                <input type="hidden" name="id" id="edit-project-id">
                <div class="form-group">
                    <label class="form-label">Asignar Equipo</label>
                    <div id="edit-team-members-container" style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); padding: 1rem; border-radius: var(--radius-md);">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('edit-project-modal')">Cancelar</button>
            <button class="btn btn-primary" onclick="updateProject()">Actualizar Proyecto</button>
        </div>
    </div>
</div>

<!-- Public Work Order Iframe Modal -->
<div class="modal-overlay" id="public-wo-modal">
    <div class="modal-content" style="max-width: 1000px; height: 85vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h2>Vista Pública - Orden de Servicio</h2>
            <button class="btn-icon" onclick="closeModal('public-wo-modal')">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body" style="flex: 1; padding: 0;">
            <iframe id="public-wo-iframe" src="" style="width: 100%; height: 100%; border: none; border-radius: 0 0 var(--radius-md) var(--radius-md);"></iframe>
        </div>
    </div>
</div>

<!-- Modal Confirmar Eliminación -->
<div class="modal-overlay" id="deleteConfirmModal" style="z-index: 1070;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="justify-content: center; border-bottom: none; padding-bottom: 0; margin-top: 1rem;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(239, 68, 68, 0.1); color: var(--danger-color); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto;">
                <i class="ph ph-warning"></i>
            </div>
        </div>
        <div class="modal-body" style="text-align: center; padding-top: 1rem;">
            <h3 style="margin-bottom: 0.5rem; color: var(--color-title); font-size: 1.25rem; font-weight: 600;">¿Estás seguro?</h3>
            <p style="margin-bottom: 0;">Esta acción no se puede deshacer.</p>
        </div>
        <div class="modal-footer" style="justify-content: center; border-top: none; padding-top: 0.5rem; gap: 1rem;">
            <button type="button" class="btn btn-outline" onclick="closeModal('deleteConfirmModal')">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnConfirmDelete" style="background-color: var(--danger-color); border-color: var(--danger-color);">
                Sí, eliminar
            </button>
        </div>
    </div>
</div>

<script>
// Available users for JS to render in edit form
const systemUsers = <?php echo json_encode($users); ?>;
let projectToDeleteId = null;

function switchView(view) {
    const activeContainer = document.getElementById('active-projects-container');
    const archivedContainer = document.getElementById('archived-projects-container');
    const btnActive = document.getElementById('btn-active-projects');
    const btnArchived = document.getElementById('btn-archived-projects');

    if (view === 'active') {
        activeContainer.style.display = 'grid';
        archivedContainer.style.display = 'none';
        btnActive.classList.add('active');
        btnArchived.classList.remove('active');
    } else {
        activeContainer.style.display = 'none';
        archivedContainer.style.display = 'grid';
        btnArchived.classList.add('active');
        btnActive.classList.remove('active');
    }
}

function openNewProjectModal() {
    document.getElementById('new-project-form').reset();
    document.getElementById('wo-details-preview').style.display = 'none';
    document.getElementById('new-project-modal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function openPublicWoModal(token) {
    document.getElementById('public-wo-iframe').src = 'index.php?module=work_orders&action=public&token=' + token;
    document.getElementById('public-wo-modal').classList.add('active');
}

async function openEditProjectModal(projectId) {
    try {
        const response = await fetch(`modules/calendar/ajax_get_project.php?id=${projectId}`);
        const data = await response.json();

        if (data.success) {
            document.getElementById('edit-project-id').value = projectId;
            
            const container = document.getElementById('edit-team-members-container');
            container.innerHTML = '';
            
            systemUsers.forEach(user => {
                const isChecked = data.team_members.includes(user.id.toString()) || data.team_members.includes(user.id) ? 'checked' : '';
                container.innerHTML += `
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="team_members[]" value="${user.id}" ${isChecked}>
                        <span>${user.name}</span>
                    </label>
                `;
            });

            document.getElementById('edit-project-modal').classList.add('active');
        } else {
            alert('Error al cargar el proyecto.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

async function updateProject() {
    const form = document.getElementById('edit-project-form');
    const formData = new FormData(form);

    try {
        const response = await fetch('modules/calendar/ajax_update_project.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.error || 'Error al actualizar el proyecto.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

async function fetchWorkOrderData() {
    const woId = document.getElementById('work_order_select').value;
    const previewContainer = document.getElementById('wo-details-preview');
    if (!woId) {
        previewContainer.style.display = 'none';
        return;
    }

    try {
        const response = await fetch(`modules/calendar/ajax_get_work_order.php?id=${woId}`);
        const data = await response.json();

        if (data.success) {
            document.getElementById('preview-logo').src = data.logo || 'assets/img/default-logo.png';
            document.getElementById('preview-brand').innerText = data.brand_name;
            
            // Render networks icons if any
            let networksHtml = '';
            if (data.networks) {
                const nets = data.networks.split(',').map(n => n.trim().toLowerCase());
                nets.forEach(n => {
                    if (n.includes('facebook')) networksHtml += '<i class="ph ph-facebook-logo" style="font-size:1.2rem; color: #1877F2; margin-right:4px;"></i>';
                    else if (n.includes('instagram')) networksHtml += '<i class="ph ph-instagram-logo" style="font-size:1.2rem; color: #E4405F; margin-right:4px;"></i>';
                    else if (n.includes('tiktok')) networksHtml += '<i class="ph ph-tiktok-logo" style="font-size:1.2rem; color: #000000; margin-right:4px;"></i>';
                    else networksHtml += n + ' ';
                });
            }
            document.getElementById('preview-networks').innerHTML = networksHtml || 'Sin redes especificadas';
            document.getElementById('preview-date').innerText = data.start_date || 'No definida';

            previewContainer.style.display = 'block';
        } else {
            alert('Error al cargar datos de la orden de servicio.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red al cargar la orden de servicio.');
    }
}

async function saveProject() {
    const form = document.getElementById('new-project-form');
    const formData = new FormData(form);

    if (!formData.get('work_order_id')) {
        alert('Seleccione una orden de servicio.');
        return;
    }

    try {
        const response = await fetch('modules/calendar/ajax_save_project.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.error || 'Error al guardar el proyecto.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

async function toggleArchive(projectId, currentStatus) {
    try {
        const newStatus = currentStatus === 'active' ? 'archived' : 'active';
        const formData = new FormData();
        formData.append('id', projectId);
        formData.append('status', newStatus);

        const response = await fetch('modules/calendar/ajax_archive_project.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            alert(result.error || 'Error al actualizar el estado.');
        }
    } catch (e) {
        console.error(e);
        alert('Error de red.');
    }
}

function deleteProject(projectId) {
    projectToDeleteId = projectId;
    
    // Assign event listener to confirm button
    const confirmBtn = document.getElementById('btnConfirmDelete');
    confirmBtn.onclick = async function() {
        if (!projectToDeleteId) return;
        
        try {
            const formData = new FormData();
            formData.append('id', projectToDeleteId);

            const response = await fetch('modules/calendar/ajax_delete_project.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                window.location.reload();
            } else {
                alert(result.error || 'Error al eliminar el proyecto.');
                closeModal('deleteConfirmModal');
            }
        } catch (e) {
            console.error(e);
            alert('Error de red.');
            closeModal('deleteConfirmModal');
        }
    };
    
    document.getElementById('deleteConfirmModal').classList.add('active');
}
</script>

<?php
function renderProjectCard($project) {
    $statusText = $project['status'] === 'active' ? 'Activo' : 'Archivado';
    $statusBg = $project['status'] === 'active' ? 'color-mix(in srgb, var(--primary-color) 15%, transparent)' : 'rgba(100, 116, 139, 0.15)';
    $statusColor = $project['status'] === 'active' ? 'var(--primary-color)' : 'var(--text-muted)';

    // Team members as overlapping avatars
    $teamMembers = json_decode($project['team_members'], true) ?: [];
    global $users;
    
    $teamHtml = '<div style="display: flex; padding-left: 0.25rem;">';
    if (!empty($teamMembers)) {
        $index = 0;
        foreach ($teamMembers as $userId) {
            $userName = 'User';
            if (is_array($users)) {
                foreach ($users as $u) {
                    if ($u['id'] == $userId) {
                        $userName = explode(' ', trim($u['name']))[0];
                        break;
                    }
                }
            }
            $initial = strtoupper(substr($userName, 0, 1));
            // Deterministic color based on userId
            $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
            $bgColor = $colors[$userId % count($colors)];
            
            $marginLeft = $index === 0 ? '0' : '-10px';
            $zIndex = 10 - $index;
            
            $teamHtml .= "
            <div style='width: 30px; height: 30px; border-radius: 50%; background: {$bgColor}; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; border: 2px solid var(--bg-surface); margin-left: {$marginLeft}; z-index: {$zIndex}; position: relative; box-shadow: 0 2px 4px rgba(0,0,0,0.1);' title='" . htmlspecialchars($userName) . "'>
                {$initial}
            </div>";
            $index++;
        }
    } else {
        $teamHtml .= "<span style='color: var(--text-muted); font-size: 0.75rem; font-weight: 500;'>Sin equipo</span>";
    }
    $teamHtml .= '</div>';
    
    $logoUrl = $project['logo'] ? htmlspecialchars($project['logo']) : 'assets/img/default-logo.png';
    $otCorrelativo = isset($project['correlativo']) ? htmlspecialchars($project['correlativo']) : 'No asignada';
    $publicToken = isset($project['public_token']) ? htmlspecialchars($project['public_token']) : '';
    $isArchived = isset($project['is_archived']) && $project['is_archived'] == 1;

    $enterButtonHtml = "";
    if ($isArchived) {
        $enterButtonHtml = "<button type='button' disabled title='La orden de servicio está archivada' style='display: flex; align-items: center; justify-content: center; width: 100%; background: #e2e8f0; color: #94a3b8; padding: 0.85rem; border-radius: 12px; font-weight: 600; border: none; cursor: not-allowed;'>Entrar al Tablero</button>";
    } else {
        $enterButtonHtml = "<a href='index.php?module=project_board&id={$project['id']}' style='display: flex; align-items: center; justify-content: center; width: 100%; background: var(--primary-color); color: white; padding: 0.85rem; border-radius: 12px; font-weight: 600; text-decoration: none; transition: background 0.2s, box-shadow 0.2s;' onmouseover='this.style.background=\"var(--primary-hover)\"; this.style.boxShadow=\"0 4px 12px rgba(79, 70, 229, 0.2)\"' onmouseout='this.style.background=\"var(--primary-color)\"; this.style.boxShadow=\"none\"'>Entrar al Tablero</a>";
    }

    echo "
    <div style='background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; box-shadow: 0 4px 20px rgba(0,0,0,0.04); transition: transform 0.2s, box-shadow 0.2s;' onmouseover='this.style.transform=\"translateY(-2px)\"; this.style.boxShadow=\"0 8px 24px rgba(0,0,0,0.08)\"' onmouseout='this.style.transform=\"none\"; this.style.boxShadow=\"0 4px 20px rgba(0,0,0,0.04)\"'>
        
        <!-- Top Row: Avatar & Identity -->
        <div style='display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1.25rem;'>
            <img src='{$logoUrl}' style='width: 64px; height: 64px; border-radius: 50%; object-fit: contain; border: 2px solid var(--bg-color); padding: 4px; background: white;'>
            <div style='display: flex; flex-direction: column; gap: 0.15rem; flex: 1;'>
                <div style='font-weight: 700; font-size: 1.15rem; color: var(--color-title); line-height: 1.2;'>" . htmlspecialchars($project['brand_name']) . "</div>
                <div style='font-size: 0.85rem; color: var(--text-muted); line-height: 1.2;'>" . htmlspecialchars($project['servicio']) . "</div>
                <div style='margin-top: 0.4rem;'>
                    <button type='button' onclick='toggleArchive({$project['id']}, \"{$project['status']}\")' style='background: {$statusBg}; color: {$statusColor}; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; border: none; cursor: pointer; transition: opacity 0.2s;' onmouseover='this.style.opacity=\"0.8\"' onmouseout='this.style.opacity=\"1\"'>
                        {$statusText}
                    </button>
                </div>
            </div>
        </div>

        <!-- Middle to Bottom Section (Aligned via margin-top: auto) -->
        <div style='margin-top: auto; display: flex; flex-direction: column;'>
            <!-- DESCRIPTION -->
            <div style='margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; background: var(--bg-color); padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color);'>
                <i class='ph ph-clipboard-text' style='color: var(--primary-color); font-size: 1.1rem;'></i>
                <span style='font-size: 0.8rem; color: var(--text-muted);'>OS:</span>
                <strong style='font-size: 0.85rem; color: var(--color-title);'>{$otCorrelativo}</strong>
            </div>

            <!-- FOCUS AREA (Team Members) -->
            <div style='margin-bottom: 1.25rem;'>
                <div style='font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem;'>Equipo Asignado</div>
                {$teamHtml}
            </div>

            <!-- Stats/Actions Box -->
            <div style='display: flex; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 1.5rem; overflow: hidden;'>
                <button type='button' onclick='openPublicWoModal(\"{$publicToken}\")' title='Ver OS' style='flex: 1; text-align: center; padding: 0.65rem; border: none; background: transparent; color: var(--text-muted); display: flex; align-items: center; justify-content: center; border-right: 1px solid var(--border-color); cursor: pointer; transition: all 0.2s;' onmouseover='this.style.background=\"rgba(0,0,0,0.03)\"; this.style.color=\"var(--color-title)\"' onmouseout='this.style.background=\"transparent\"; this.style.color=\"var(--text-muted)\"'>
                    <i class='ph ph-file-text' style='font-size: 1.3rem;'></i>
                </button>
                <button type='button' onclick='openEditProjectModal({$project['id']})' title='Editar' style='flex: 1; text-align: center; padding: 0.65rem; border: none; background: transparent; color: var(--text-muted); display: flex; align-items: center; justify-content: center; border-right: 1px solid var(--border-color); cursor: pointer; transition: all 0.2s;' onmouseover='this.style.background=\"rgba(79,70,229,0.05)\"; this.style.color=\"var(--primary-color)\"' onmouseout='this.style.background=\"transparent\"; this.style.color=\"var(--text-muted)\"'>
                    <i class='ph ph-pencil' style='font-size: 1.3rem;'></i>
                </button>
                <button type='button' onclick='deleteProject({$project['id']})' title='Eliminar' style='flex: 1; text-align: center; padding: 0.65rem; border: none; background: transparent; color: var(--text-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;' onmouseover='this.style.background=\"rgba(239,68,68,0.05)\"; this.style.color=\"var(--danger-color)\"' onmouseout='this.style.background=\"transparent\"; this.style.color=\"var(--text-muted)\"'>
                    <i class='ph ph-trash' style='font-size: 1.3rem;'></i>
                </button>
            </div>

            <!-- Bottom Action -->
            <div>
                {$enterButtonHtml}
            </div>
        </div>
    </div>
    ";
}

require_once 'includes/footer.php';
?>
