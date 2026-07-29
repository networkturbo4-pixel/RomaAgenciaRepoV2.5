<?php
// modules/desarrollo_marca/view.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo "<div style='padding:2rem;'>ID de proyecto inválido.</div>";
    exit;
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'] ?? 0;

// Fetch project
if ($role_id == 1) {
    $stmt = $db->prepare("SELECT * FROM brand_projects WHERE id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $db->prepare("
        SELECT p.* FROM brand_projects p
        JOIN brand_project_users pu ON p.id = pu.project_id
        WHERE p.id = ? AND pu.user_id = ?
    ");
    $stmt->execute([$id, $user_id]);
}
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    echo "<div style='padding:2rem;'>Proyecto no encontrado o no tienes acceso.</div>";
    exit;
}

// Fetch form submission if linked
$form_data = [];
if (!empty($project['form_submission_id'])) {
    $stmtForm = $db->prepare("SELECT * FROM form_submissions WHERE id = ?");
    $stmtForm->execute([$project['form_submission_id']]);
    $sub = $stmtForm->fetch(PDO::FETCH_ASSOC);
    if ($sub && !empty($sub['data_json'])) {
        $form_data = json_decode($sub['data_json'], true);
    }
}

require_once 'includes/header.php';
?>

<style>
/* Reset and Base */
.view-container {
    max-width: 1400px;
    margin: 0 auto;
    font-family: 'Inter', sans-serif;
    color: var(--text-color);
}
.view-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    background: var(--bg-surface, #fff);
    padding: 1.5rem;
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.view-header h1 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.view-header h1 .btn-back {
    color: var(--text-color);
    text-decoration: none;
    padding: 0.5rem;
    border-radius: 8px;
    background: var(--bg-color, #f8fafc);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.view-header h1 .btn-back:hover {
    background: #e2e8f0;
}

/* Layout 70/30 */
.view-layout {
    display: grid;
    grid-template-columns: 7fr 3fr;
    gap: 1.5rem;
}
@media (max-width: 1024px) {
    .view-layout {
        grid-template-columns: 1fr;
    }
}

/* Common Card Styles */
.v-card {
    background: var(--bg-surface, #fff);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    margin-bottom: 1.5rem;
}
.v-card-header {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary-color, #0f172a);
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    padding-bottom: 0.75rem;
}

/* Left Column: Tareas Verticales */
.task-group {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 1rem;
    overflow: hidden;
}
.task-group-header {
    background: #f1f5f9;
    padding: 1rem;
    font-weight: 700;
    color: #334155;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e8f0;
    cursor: grab;
}
.task-list {
    padding: 1rem;
    min-height: 60px;
}
.task-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    cursor: grab;
    transition: border-color 0.2s;
}
.task-card:hover {
    border-color: #cbd5e1;
}
.task-card:last-child {
    margin-bottom: 0;
}
.task-card-title {
    font-weight: 600;
    color: #0f172a;
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
}
.task-card-desc {
    font-size: 0.8rem;
    color: #64748b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.btn-action {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 1.1rem;
    transition: color 0.2s;
    padding: 0.2rem;
}
.btn-action:hover { color: #3b82f6; }
.btn-action.del:hover { color: #ef4444; }

/* Right Column: Brief */
.brief-trigger-card {
    background: var(--bg-color, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}
.brief-trigger-card:hover {
    background: #eff6ff;
    border-color: #bfdbfe;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}

/* Right Column: Entregables */
.deliverables-placeholder {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 1rem;
}
.file-box {
    aspect-ratio: 1;
    background: var(--bg-color, #f8fafc);
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}
.file-box:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #64748b;
}
.file-box i { font-size: 1.5rem; margin-bottom: 0.25rem; }

/* Timer CSS */
.modern-timer {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(15, 23, 42, 0.75);
    color: #fff;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    transition: background 0.3s;
}
.modern-timer.expired {
    background: rgba(239, 68, 68, 0.85);
}
</style>

<div class="view-container">
    <div class="view-header">
        <h1>
            <a href="index.php?module=desarrollo_marca&action=index" class="btn-back"><i class="ph ph-arrow-left"></i></a>
            <?php echo htmlspecialchars($project['title']); ?>
        </h1>
        <div>
            <?php if (!empty($project['due_date'])): ?>
                <div class="modern-timer" data-start="<?php echo htmlspecialchars($project['start_date'] ?? ''); ?>" data-due="<?php echo htmlspecialchars($project['due_date']); ?>">
                    <i class="ph ph-hourglass-high"></i> <span class="timer-text">Calculando...</span>
                </div>
            <?php else: ?>
                <span style="background: #e0e7ff; color: #4338ca; padding: 0.4rem 1rem; border-radius: 20px; font-weight: 600; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($project['status']); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="view-layout">
        <!-- Izquierda (70%) -->
        <div class="view-main">
            <div class="v-card">
                <div class="v-card-header" style="justify-content: space-between;">
                    <div><i class="ph ph-list-dashes"></i> Lista de Tareas</div>
                    <button onclick="openGroupModal()" style="background:#10b981; color:#fff; border:none; border-radius:6px; padding:0.4rem 0.8rem; font-weight:600; font-size:0.85rem; cursor:pointer;"><i class="ph ph-plus"></i> Añadir Grupo</button>
                </div>
                <div id="task-groups-container">
                    <div style="text-align:center; padding: 2rem; color: #94a3b8;">Cargando tareas...</div>
                </div>
            </div>
        </div>

        <!-- Derecha (30%) -->
        <div class="view-sidebar">
            <div class="v-card">
                <div class="v-card-header"><i class="ph ph-file-text"></i> Resumen / Brief</div>
                <?php if (empty($form_data)): ?>
                    <div style="color:#94a3b8; font-size:0.9rem; text-align:center; padding: 2rem 0;">
                        <i class="ph ph-ghost" style="font-size:2rem; display:block; margin-bottom:0.5rem;"></i>
                        No hay formulario vinculado a este proyecto.
                    </div>
                <?php else: ?>
                    <?php 
                    $briefHtml = '';
                    foreach ($form_data as $field => $answer) {
                        if (empty($answer)) continue;
                        if (is_array($answer)) $answer = implode(", ", $answer);
                        $cleanField = htmlspecialchars(str_replace('_', ' ', ucfirst($field)));
                        $cleanAnswer = nl2br(htmlspecialchars($answer));

                        $briefHtml .= '<div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px dashed #e2e8f0;">';
                        $briefHtml .= '<div style="font-size: 0.85rem; font-weight: 700; color: #6366f1; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">' . $cleanField . '</div>';
                        $briefHtml .= '<div style="color: #334155; font-size: 1.1rem; line-height: 1.6;">' . $cleanAnswer . '</div>';
                        $briefHtml .= '</div>';
                    }
                    ?>
                    
                    <div class="brief-trigger-card" onclick="openBriefModal()">
                        <i class="ph-fill ph-notebook" style="font-size:2.5rem; color: #6366f1; margin-bottom:0.75rem;"></i>
                        <h4 style="margin:0; font-size:1.05rem; color: var(--text-main, #0f172a);">Ver Brief del Cliente</h4>
                        <p style="margin:0.5rem 0 0 0; font-size:0.85rem; color:#64748b;">Haz clic para revisar el formulario completo</p>
                    </div>

                    <style>
                        .swal2-zero-pad { padding: 0 !important; overflow: hidden !important; border-radius: 16px !important; }
                    </style>

                    <script>
                        function openBriefModal() {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    html: `
                                        <div style="text-align: left;">
                                            <div style="background: linear-gradient(135deg, #1e293b, #0f172a); padding: 2rem 2.5rem; color: #fff;">
                                                <div style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 0.25rem;">Visor de Documento</div>
                                                <div style="font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; color: #f8fafc;">
                                                    <i class="ph-fill ph-notebook" style="color: #818cf8;"></i> Respuestas del Brief
                                                </div>
                                            </div>
                                            <div style="padding: 2.5rem; max-height: 55vh; overflow-y: auto; background: #ffffff;">
                                                <?php echo addslashes(str_replace(["\r", "\n"], "", $briefHtml)); ?>
                                            </div>
                                            <div style="padding: 1.25rem 2.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: right;">
                                                <button onclick="Swal.close()" style="background: #0f172a; color: #fff; border: none; border-radius: 8px; padding: 0.6rem 1.5rem; font-weight: 600; cursor: pointer; transition: background 0.2s;">Cerrar Resumen</button>
                                            </div>
                                        </div>
                                    `,
                                    showConfirmButton: false,
                                    width: '800px',
                                    padding: '0',
                                    background: 'transparent',
                                    customClass: {
                                        popup: 'swal2-zero-pad'
                                    }
                                });
                            } else {
                                alert("No se pudo cargar el modal. Revisa que SweetAlert esté incluido.");
                            }
                        }
                    </script>
                <?php endif; ?>
            </div>

            <div class="v-card">
                <div class="v-card-header"><i class="ph ph-folder"></i> Entregables / Archivos</div>
                <div class="deliverables-placeholder">
                    <div class="file-box">
                        <i class="ph ph-image"></i>
                        Logo_v1
                    </div>
                    <div class="file-box">
                        <i class="ph ph-file-pdf"></i>
                        Manual
                    </div>
                    <div class="file-box">
                        <i class="ph ph-plus"></i>
                        Subir
                    </div>
                </div>
                <?php if (!empty($project['drive_folder_url'])): ?>
                <a href="<?php echo htmlspecialchars($project['drive_folder_url']); ?>" target="_blank" style="display:block; text-align:center; margin-top:1.5rem; color:#3b82f6; text-decoration:none; font-weight:600; background:#eff6ff; padding:0.75rem; border-radius:8px;">
                    <i class="ph-fill ph-google-drive-logo"></i> Abrir Carpeta Drive
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.polyfills.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" rel="stylesheet" type="text/css" />
<style>
.tagify {
    --tags-border-color: #e2e8f0;
    --tags-hover-border-color: #cbd5e1;
    --tags-focus-border-color: #3b82f6;
    background: #fff;
    border-radius: 6px;
}
.tagify__tag > div {
    border-radius: 4px;
}
</style>
<script>
const PROJECT_ID = <?php echo $id; ?>;
let allTags = [];

document.addEventListener('DOMContentLoaded', () => {
    loadProjectTasks();
    loadTagsForTasks();
});

function loadTagsForTasks() {
    let fd = new FormData();
    fd.append('action', 'get_tags');
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => { if(data.success) allTags = data.tags; });
}

function loadProjectTasks() {
    let formData = new FormData();
    formData.append('action', 'get_project_tasks');
    formData.append('project_id', PROJECT_ID);

    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            renderTaskGroups(data.groups);
        }
    });
}

function renderTaskGroups(groups) {
    const container = document.getElementById('task-groups-container');
    container.innerHTML = '';

    if(groups.length === 0) {
        container.innerHTML = `<div style="text-align:center; padding: 3rem; color: #94a3b8;">
            <i class="ph ph-kanban" style="font-size:3rem; margin-bottom:1rem; display:block;"></i>
            No hay grupos de tareas. ¡Añade uno para empezar!
        </div>`;
        return;
    }

    groups.forEach(g => {
        let gDiv = document.createElement('div');
        gDiv.className = 'task-group';
        gDiv.setAttribute('data-id', g.id);
        
        let tasksHtml = '';
        if(g.tasks) {
            g.tasks.forEach(t => {
                let tagsArr = [];
                try { tagsArr = JSON.parse(t.tags || '[]'); } catch(e) {}
                let tagsHtml = '';
                if(tagsArr.length > 0) {
                    tagsHtml = '<div style="display:flex; gap:0.35rem; flex-wrap:wrap; margin-bottom:0.5rem;">';
                    tagsArr.forEach(tag => {
                        let color = tag.color || '#94a3b8';
                        tagsHtml += `<span style="background:${color}; color:#fff; font-size:0.65rem; padding:0.15rem 0.5rem; border-radius:12px; font-weight:600;">${tag.value}</span>`;
                    });
                    tagsHtml += '</div>';
                }

                let statusColors = { pending: '#94a3b8', in_progress: '#3b82f6', review: '#eab308', completed: '#10b981' };
                let statusColor = statusColors[t.status] || '#94a3b8';

                let metaHtml = '';
                if(t.due_date) {
                    let isLate = (new Date(t.due_date + 'T23:59:59') < new Date()) && t.status !== 'completed';
                    let dateColor = isLate ? '#ef4444' : '#64748b';
                    metaHtml += `<div style="font-size:0.75rem; color:${dateColor}; margin-top:0.5rem; display:flex; align-items:center; gap:0.25rem; font-weight: 500;"><i class="ph ph-calendar-blank"></i> ${t.due_date}</div>`;
                }

                let safeTitle = (t.title||'').replace(/'/g, "\\'").replace(/"/g, "&quot;");
                let safeDesc = (t.description||'').replace(/'/g, "\\'").replace(/"/g, "&quot;").replace(/\n/g, "\\n");
                let safeTags = (t.tags||'[]').replace(/'/g, "\\'").replace(/"/g, "&quot;");

                tasksHtml += `
                <div class="task-card" data-id="${t.id}" style="border-left: 4px solid ${statusColor};">
                    <div style="flex:1;" onclick="openTaskModal(${t.id}, ${g.id}, '${safeTitle}', '${safeDesc}', '${t.status}', '${t.start_date||''}', '${t.due_date||''}', '${safeTags}')">
                        ${tagsHtml}
                        <div class="task-card-title">${t.title}</div>
                        ${t.description ? `<div class="task-card-desc">${t.description}</div>` : ''}
                        ${metaHtml}
                    </div>
                    <div class="task-actions">
                        <button class="btn-action del" onclick="deleteTask(${t.id})" title="Eliminar"><i class="ph ph-trash"></i></button>
                    </div>
                </div>`;
            });
        }

        gDiv.innerHTML = `
            <div class="task-group-header">
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <i class="ph ph-dots-six-vertical" style="color:#94a3b8;"></i>
                    ${g.name}
                </div>
                <div>
                    <button class="btn-action" onclick="openTaskModal(0, ${g.id})" title="Añadir Tarea"><i class="ph ph-plus"></i></button>
                    <button class="btn-action" onclick="openGroupModal(${g.id}, '${g.name}')" title="Editar Grupo"><i class="ph ph-pencil-simple"></i></button>
                    <button class="btn-action del" onclick="deleteGroup(${g.id})" title="Eliminar Grupo"><i class="ph ph-trash"></i></button>
                </div>
            </div>
            <div class="task-list" data-group="${g.id}">
                ${tasksHtml}
            </div>
        `;
        container.appendChild(gDiv);
    });

    // Init Sortable for Groups
    new Sortable(container, {
        animation: 150,
        handle: '.task-group-header',
        onEnd: function () {
            let orders = [];
            container.querySelectorAll('.task-group').forEach((el, index) => {
                orders.push({ id: el.getAttribute('data-id'), order: index });
            });
            saveGroupOrder(orders);
        }
    });

    // Init Sortable for Tasks
    document.querySelectorAll('.task-list').forEach(list => {
        new Sortable(list, {
            group: 'shared',
            animation: 150,
            onEnd: function (evt) {
                let groupId = evt.to.getAttribute('data-group');
                let orders = [];
                evt.to.querySelectorAll('.task-card').forEach((el, index) => {
                    orders.push({ id: el.getAttribute('data-id'), order: index });
                });
                saveTaskOrder(groupId, orders);
            }
        });
    });
}

function openGroupModal(id = 0, currentName = '') {
    Swal.fire({
        title: id ? 'Editar Grupo' : 'Nuevo Grupo',
        input: 'text',
        inputValue: currentName,
        inputPlaceholder: 'Ej. FASE 1: Diseño',
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#94a3b8',
        customClass: { popup: 'swal2-modern-popup' }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            let fd = new FormData();
            fd.append('action', 'save_task_group');
            fd.append('id', id);
            fd.append('project_id', PROJECT_ID);
            fd.append('name', result.value);
            fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if(d.success) loadProjectTasks(); });
        }
    });
}

function deleteGroup(id) {
    Swal.fire({
        title: '¿Eliminar grupo?',
        text: 'Se eliminarán también todas las tareas que contenga.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        customClass: { popup: 'swal2-modern-popup' }
    }).then((result) => {
        if (result.isConfirmed) {
            let fd = new FormData();
            fd.append('action', 'delete_task_group');
            fd.append('id', id);
            fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if(d.success) loadProjectTasks(); });
        }
    });
}

function openTaskModal(taskId = 0, groupId = 0, title = '', description = '', status = 'pending', startDate = '', dueDate = '', tags = '[]') {
    let modalHtml = `
        <style>
            .notion-modal {
                padding: 1.5rem 2.5rem 2.5rem 2.5rem;
                text-align: left;
                background: #fff;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            }
            .notion-topbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #f1f5f9;
                padding-bottom: 1rem;
                margin-bottom: 2rem;
            }
            .notion-breadcrumb {
                font-size: 0.85rem;
                color: #94a3b8;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .notion-breadcrumb strong { color: #334155; font-weight: 600; }
            .notion-actions {
                display: flex;
                gap: 0.5rem;
            }
            .notion-icon-btn {
                width: 32px;
                height: 32px;
                border-radius: 6px;
                border: 1px solid #e2e8f0;
                background: #fff;
                color: #64748b;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                font-size: 1.1rem;
                transition: background 0.2s;
            }
            .notion-icon-btn:hover { background: #f8fafc; color: #0f172a; }
            
            .notion-title-input {
                width: 100%;
                font-size: 2.2rem;
                font-weight: 700;
                border: none;
                outline: none;
                color: #0f172a;
                margin-bottom: 2rem;
                background: transparent;
                letter-spacing: -0.5px;
            }
            .notion-title-input::placeholder { color: #cbd5e1; }
            
            .notion-props-grid {
                display: grid;
                grid-template-columns: 140px 1fr;
                gap: 1.25rem 0;
                margin-bottom: 2.5rem;
                align-items: center;
            }
            .notion-prop-label {
                display: flex;
                align-items: center;
                gap: 0.6rem;
                color: #64748b;
                font-size: 0.95rem;
            }
            .notion-prop-label i { font-size: 1.2rem; color: #94a3b8; }
            
            .notion-prop-value {
                display: flex;
                align-items: center;
            }
            
            .notion-select, .notion-date {
                border: 1px solid transparent;
                background: transparent;
                font-size: 0.95rem;
                color: #334155;
                padding: 0.35rem 0.5rem;
                border-radius: 6px;
                outline: none;
                cursor: pointer;
                transition: background 0.2s;
            }
            .notion-select:hover, .notion-date:hover { background: #f1f5f9; }
            
            .notion-section-title {
                display: flex;
                align-items: center;
                gap: 0.6rem;
                color: #64748b;
                font-size: 0.95rem;
                margin-bottom: 1rem;
            }
            .notion-section-title i { font-size: 1.2rem; color: #94a3b8; }
            
            .notion-desc-box {
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 1.5rem;
                background: #fcfcfc;
            }
            .notion-desc-textarea {
                width: 100%;
                min-height: 180px;
                border: none;
                outline: none;
                background: transparent;
                font-size: 0.95rem;
                color: #334155;
                line-height: 1.6;
                resize: vertical;
            }
            .notion-desc-textarea::placeholder { color: #94a3b8; }
            
            .app-tagify { border: none !important; padding: 0 !important; background: transparent !important; }
            .app-tagify.tagify--focus { box-shadow: none !important; }
            
            .notion-save-row {
                margin-top: 2rem;
                display: flex;
                justify-content: flex-end;
                gap: 0.75rem;
                padding-top: 1.5rem;
            }
            .notion-btn-save {
                background: #0f172a; color: #fff; padding: 0.5rem 1.5rem; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; transition: opacity 0.2s;
            }
            .notion-btn-save:hover { opacity: 0.9; }
            .notion-btn-cancel {
                background: #fff; color: #64748b; border: 1px solid #e2e8f0; padding: 0.5rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s;
            }
            .notion-btn-cancel:hover { background: #f8fafc; }
            .swal2-zero-pad { padding: 0 !important; overflow: hidden !important; border-radius: 12px !important; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important; }
            .app-modal-actions { display: none !important; }
        </style>
        
        <div class="notion-modal">
            <div class="notion-topbar">
                <div class="notion-breadcrumb">
                    <i class="ph-bold ph-arrows-out-simple"></i> Proyectos de Desarrollo / <strong>Gestión de Tarea</strong>
                </div>
                <div class="notion-actions">
                    <button class="notion-icon-btn" onclick="Swal.close()"><i class="ph ph-x"></i></button>
                </div>
            </div>
            
            <input id="swal-task-title" class="notion-title-input" placeholder="Nombre de la tarea" value="${title}">
            
            <div class="notion-props-grid">
                <div class="notion-prop-label"><i class="ph ph-sun"></i> Status</div>
                <div class="notion-prop-value">
                    <select id="swal-task-status" class="notion-select" style="background: #fef9c3; color: #854d0e; font-weight: 600; padding: 0.35rem 0.75rem; border-radius: 12px;">
                        <option value="pending" ${status === 'pending' ? 'selected' : ''}>⏳ Pendiente</option>
                        <option value="in_progress" ${status === 'in_progress' ? 'selected' : ''}>🟡 En Proceso</option>
                        <option value="review" ${status === 'review' ? 'selected' : ''}>🟣 Revisión</option>
                        <option value="completed" ${status === 'completed' ? 'selected' : ''}>🟢 Completado</option>
                    </select>
                </div>
                
                <div class="notion-prop-label"><i class="ph ph-calendar-blank"></i> Date</div>
                <div class="notion-prop-value" style="gap: 0.5rem; color: #64748b; font-size: 0.95rem;">
                    <input type="date" id="swal-task-start" class="notion-date" value="${startDate}">
                    <i class="ph ph-arrow-right"></i>
                    <input type="date" id="swal-task-due" class="notion-date" value="${dueDate}">
                </div>
                
                <div class="notion-prop-label"><i class="ph ph-tag"></i> Tags</div>
                <div class="notion-prop-value" style="width: 100%;">
                    <input id="swal-task-tags" value='${tags}' class="app-tagify" style="width: 100%;">
                </div>
            </div>
            
            <div class="notion-section-title">
                <i class="ph ph-text-align-left"></i> Description
            </div>
            <div class="notion-desc-box">
                <textarea id="swal-task-desc" class="notion-desc-textarea" placeholder="Escribe los detalles, objetivos y requerimientos de esta tarea aquí...">${description}</textarea>
            </div>
            
            <div class="notion-save-row">
                <button onclick="Swal.close()" class="notion-btn-cancel">Cancelar</button>
                <button id="custom-save-btn" class="notion-btn-save">Guardar Cambios</button>
            </div>
        </div>
    `;

    Swal.fire({
        html: modalHtml,
        width: '800px',
        showConfirmButton: false,
        showCancelButton: false,
        customClass: { popup: 'swal2-zero-pad', actions: 'app-modal-actions' },
        didOpen: () => {
            const input = document.getElementById('swal-task-tags');
            new Tagify(input, {
                whitelist: allTags,
                enforceWhitelist: false,
                dropdown: { enabled: 0, maxItems: 15 }
            });
            
            // Handle Status Color changes dynamically
            const statusSelect = document.getElementById('swal-task-status');
            const updateStatusColor = () => {
                let val = statusSelect.value;
                if(val === 'pending') { statusSelect.style.background = '#f1f5f9'; statusSelect.style.color = '#475569'; }
                else if(val === 'in_progress') { statusSelect.style.background = '#fef9c3'; statusSelect.style.color = '#854d0e'; }
                else if(val === 'review') { statusSelect.style.background = '#f3e8ff'; statusSelect.style.color = '#7e22ce'; }
                else if(val === 'completed') { statusSelect.style.background = '#dcfce3'; statusSelect.style.color = '#166534'; }
            };
            statusSelect.addEventListener('change', updateStatusColor);
            updateStatusColor();
            
            document.getElementById('custom-save-btn').addEventListener('click', () => {
                let titleVal = document.getElementById('swal-task-title').value;
                if(!titleVal) {
                    document.getElementById('swal-task-title').focus();
                    return;
                }
                
                let fd = new FormData();
                fd.append('action', 'save_task');
                fd.append('id', taskId);
                fd.append('group_id', groupId);
                fd.append('title', titleVal);
                fd.append('description', document.getElementById('swal-task-desc').value);
                fd.append('status', document.getElementById('swal-task-status').value);
                fd.append('start_date', document.getElementById('swal-task-start').value);
                fd.append('due_date', document.getElementById('swal-task-due').value);
                fd.append('tags', document.getElementById('swal-task-tags').value);
                fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { if(d.success) loadProjectTasks(); });
                
                Swal.close();
            });
            
            if(!taskId) document.getElementById('swal-task-title').focus();
        }
    });
}

function deleteTask(id) {
    Swal.fire({
        title: '¿Eliminar tarea?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        customClass: { popup: 'swal2-modern-popup' }
    }).then((result) => {
        if (result.isConfirmed) {
            let fd = new FormData();
            fd.append('action', 'delete_task');
            fd.append('id', id);
            fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if(d.success) loadProjectTasks(); });
        }
    });
}

function saveGroupOrder(orders) {
    let fd = new FormData();
    fd.append('action', 'reorder_groups');
    fd.append('orders', JSON.stringify(orders));
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd });
}

function saveTaskOrder(groupId, orders) {
    let fd = new FormData();
    fd.append('action', 'reorder_tasks');
    fd.append('group_id', groupId);
    fd.append('orders', JSON.stringify(orders));
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: fd });
}

function updateViewTimers() {
    let now = new Date();
    document.querySelectorAll('.modern-timer').forEach(el => {
        let dueStr = el.getAttribute('data-due');
        let startStr = el.getAttribute('data-start');
        if(!dueStr) return;
        let due = new Date(dueStr + 'T23:59:59');
        let start = startStr ? new Date(startStr + 'T00:00:00') : new Date();
        let textEl = el.querySelector('.timer-text');
        let diff = 0;
        if (now < start) { diff = due - start; el.style.background = 'rgba(59, 130, 246, 0.85)'; el.classList.remove('expired'); } 
        else if (now >= start && now <= due) { diff = due - now; el.style.background = 'rgba(15, 23, 42, 0.75)'; el.classList.remove('expired'); } 
        else { el.classList.add('expired'); el.style.background = 'rgba(239, 68, 68, 0.85)'; textEl.innerHTML = 'Tiempo agotado'; return; }
        let days = Math.floor(diff / (1000 * 60 * 60 * 24));
        let hours = String(Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
        let mins = String(Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
        let secs = String(Math.floor((diff % (1000 * 60)) / 1000)).padStart(2, '0');
        textEl.innerHTML = days > 0 ? `${days}d ${hours}:${mins}:${secs}` : `${hours}:${mins}:${secs}`;
    });
}
setInterval(updateViewTimers, 1000);
updateViewTimers();
</script>

<?php require_once 'includes/footer.php'; ?>
