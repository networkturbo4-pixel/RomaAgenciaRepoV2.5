<?php
global $db;
$roles_raw = $db->query("SELECT r.*, rp.module_name FROM roles r LEFT JOIN role_permissions rp ON r.id = rp.role_id")->fetchAll();
$roles = [];
foreach($roles_raw as $row) {
    if (!isset($roles[$row['id']])) {
        $roles[$row['id']] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'requires_attendance' => (int)($row['requires_attendance'] ?? 1),
            'perms' => []
        ];
    }
    if ($row['module_name']) {
        $roles[$row['id']]['perms'][] = $row['module_name'];
    }
}
?>

<div class="pane-header">
    <div>
        <h2 class="pane-header-title">
            <i class="ph ph-shield-check"></i> Roles y Permisos de Acceso
        </h2>
        <p class="pane-header-desc">Define los niveles de seguridad, módulos autorizados y control de asistencia para cada rol.</p>
    </div>
    <?php if ($is_admin): ?>
    <button type="button" class="btn btn-primary" data-modal-target="modal-create-role" style="display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 10px; padding: 0.55rem 1.15rem; font-weight: 600;">
        <i class="ph ph-plus-circle"></i> Crear Nuevo Rol
    </button>
    <?php endif; ?>
</div>

<div class="app-table-wrapper mb-4">
    <table class="app-table">
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th style="width: 170px;">Nombre del Rol</th>
                <th>Descripción</th>
                <th style="width: 130px;">Módulos</th>
                <th style="width: 190px;">Control Asistencia</th>
                <th style="width: 90px; text-align: right;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($roles as $role): ?>
            <tr>
                <td style="font-weight: 600; color: var(--text-muted);">#<?php echo $role['id']; ?></td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.65rem;">
                        <div style="width: 32px; height: 32px; border-radius: 8px; background: color-mix(in srgb, var(--primary-color) 12%, transparent); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                            <i class="ph ph-shield"></i>
                        </div>
                        <strong style="color: var(--color-title);"><?php echo htmlspecialchars($role['name']); ?></strong>
                    </div>
                </td>
                <td style="color: var(--text-muted); font-size: 12.5px;">
                    <?php echo htmlspecialchars($role['description'] ?: 'Sin descripción'); ?>
                </td>
                <td>
                    <span class="badge-role" style="gap: 0.35rem;">
                        <i class="ph ph-squares-four"></i>
                        <?php echo count($role['perms']); ?> módulos
                    </span>
                </td>
                <td>
                    <?php if ($role['id'] == 1 || $role['name'] === 'Administrador'): ?>
                        <span class="badge-attendance-exempt" title="Administrador exento de control de asistencia">
                            <i class="ph ph-shield-check"></i> Exento (Admin)
                        </span>
                    <?php else: ?>
                        <label class="attendance-toggle-pill" title="Clic para alternar entre Horario Fijo y Sin Asistencia">
                            <span class="modern-switch-ios">
                                <input type="checkbox" class="role-attendance-toggle" 
                                       data-role-id="<?php echo $role['id']; ?>" 
                                       <?php echo $role['requires_attendance'] == 1 ? 'checked' : ''; ?>
                                       <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                <span class="slider-ios"></span>
                            </span>
                            <span class="attendance-text-state <?php echo $role['requires_attendance'] == 1 ? 'is-fixed' : 'is-free'; ?>" id="role-att-label-<?php echo $role['id']; ?>">
                                <?php if ($role['requires_attendance'] == 1): ?>
                                    <i class="ph ph-clock"></i> Horario Fijo
                                <?php else: ?>
                                    <i class="ph ph-infinity"></i> Sin Asistencia
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endif; ?>
                </td>
                <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                        <?php if ($is_admin): ?>
                        <button type="button" class="btn btn-outline btn-sm edit-role-btn" style="padding: 0.35rem 0.65rem; border-radius: 8px;" 
                                data-modal-target="modal-edit-role" 
                                data-id="<?php echo $role['id']; ?>" 
                                data-name="<?php echo htmlspecialchars($role['name']); ?>" 
                                data-desc="<?php echo htmlspecialchars($role['description']); ?>" 
                                data-attendance="<?php echo $role['requires_attendance']; ?>"
                                data-perms='<?php echo json_encode($role['perms']); ?>'
                                title="Editar Rol">
                            <i class="ph ph-pencil-simple"></i>
                        </button>
                        <?php if($role['id'] != 1): ?>
                        <button type="button" class="btn btn-outline btn-sm delete-role-btn" style="padding: 0.35rem 0.65rem; border-radius: 8px; color: var(--danger-color); border-color: color-mix(in srgb, var(--danger-color) 30%, transparent);" 
                                data-modal-target="modal-delete-role" 
                                data-id="<?php echo $role['id']; ?>"
                                title="Eliminar Rol">
                            <i class="ph ph-trash"></i>
                        </button>
                        <?php endif; ?>
                        <?php else: ?>
                        <span style="color: var(--text-muted); font-size: 11px;"><i class="ph ph-lock"></i> Lectura</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.perm-card-label:hover {
    border-color: var(--primary-color) !important;
    background: color-mix(in srgb, var(--primary-color) 6%, var(--bg-surface)) !important;
    transform: translateY(-1px);
}
.perm-card-label:active {
    transform: translateY(0);
}
</style>

<?php 
$all_modules = [
    // Operaciones & Gestión
    'dashboard'         => ['name' => 'Dashboard', 'icon' => 'ph-squares-four'],
    'workspace'         => ['name' => 'Workspace', 'icon' => 'ph-briefcase'],
    'task_manager'      => ['name' => 'Tareas & Objetivos', 'icon' => 'ph-check-square-offset'],
    'projects'          => ['name' => 'Proyectos', 'icon' => 'ph-kanban'],
    'project_board'     => ['name' => 'Tablero de Proyectos', 'icon' => 'ph-presentation-chart'],
    'month_board'       => ['name' => 'Tablero Mensual', 'icon' => 'ph-calendar-plus'],
    'community'         => ['name' => 'Community Manager', 'icon' => 'ph-share-network'],
    
    // Comunicación
    'mensajes'          => ['name' => 'Mensajes', 'icon' => 'ph-chat-circle-dots'],
    'whatsapp'          => ['name' => 'WhatsApp', 'icon' => 'ph-whatsapp-logo'],
    
    // Comercial & Clientes
    'clients'           => ['name' => 'Clientes', 'icon' => 'ph-users'],
    'suppliers'         => ['name' => 'Proveedores', 'icon' => 'ph-buildings'],
    'quotes'            => ['name' => 'Cotizaciones', 'icon' => 'ph-file-text'],
    'services'          => ['name' => 'Servicios', 'icon' => 'ph-package'],
    'work_orders'       => ['name' => 'Órdenes de Servicio', 'icon' => 'ph-clipboard-text'],
    'calendar'          => ['name' => 'Calendario', 'icon' => 'ph-calendar'],
    'forms'             => ['name' => 'Formularios', 'icon' => 'ph-note-pencil'],
    'contracts'         => ['name' => 'Contratos', 'icon' => 'ph-signature'],
    'client_portal'     => ['name' => 'Portal de Clientes', 'icon' => 'ph-app-window'],
    
    // Creatividad & Herramientas
    'desarrollo_marca'  => ['name' => 'Desarrollo de Marca', 'icon' => 'ph-paint-brush-broad'],
    'knowledge_base'    => ['name' => 'Base de Conocimiento', 'icon' => 'ph-book-open'],
    'romita'            => ['name' => 'Romita IA', 'icon' => 'ph-sparkle'],
    'pizarras'          => ['name' => 'Pizarras', 'icon' => 'ph-chalkboard'],
    'reuniones'         => ['name' => 'Reuniones', 'icon' => 'ph-video-camera'],
    'drive'             => ['name' => 'Google Drive', 'icon' => 'ph-hard-drives'],
    'herramientas'      => ['name' => 'Herramientas', 'icon' => 'ph-wrench'],
    
    // Especializados & Complementarios
    'design_tasks'      => ['name' => 'Tareas de Diseño', 'icon' => 'ph-palette'],
    'tasks'             => ['name' => 'Centro de Tareas (v1)', 'icon' => 'ph-list-checks'],
    'chat'              => ['name' => 'Chat Interno', 'icon' => 'ph-chats-teardrop'],
    
    // Sistema & Administración
    'conexiones'        => ['name' => 'Conexiones', 'icon' => 'ph-plugs-connected'],
    'admin'             => ['name' => 'Administración', 'icon' => 'ph-shield-check'],
    'config'            => ['name' => 'Configuración', 'icon' => 'ph-gear']
];
?>

<!-- Modal: Crear Rol -->
<div id="modal-create-role" class="modal-overlay">
    <div class="modal-content" style="max-width: 680px; border-radius: 18px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="ph ph-shield-star"></i> Crear Nuevo Rol</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="role_create">
            
            <div class="modal-body" style="padding: 1.5rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="role_name" style="font-weight: 600; font-size: 13px;">Nombre del Rol</label>
                        <input type="text" id="role_name" name="role_name" class="form-control" required placeholder="Ej. Gerente de Ventas">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="role_desc" style="font-weight: 600; font-size: 13px;">Descripción del Rol</label>
                        <input type="text" id="role_desc" name="role_desc" class="form-control" placeholder="Funciones del rol">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
                        <label style="margin: 0; font-weight: 600; font-size: 13px;">Módulos Permitidos (<?php echo count($all_modules); ?>)</label>
                        <div style="display: flex; gap: 0.4rem;">
                            <button type="button" id="btn-select-all-create" class="btn btn-xs" style="font-size: 11px; padding: 3px 9px; border-radius: 6px; background: var(--bg-surface); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="ph ph-checks"></i> Todos
                            </button>
                            <button type="button" id="btn-deselect-all-create" class="btn btn-xs" style="font-size: 11px; padding: 3px 9px; border-radius: 6px; background: var(--bg-surface); border: 1px solid var(--border-color); color: var(--text-muted); font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="ph ph-x"></i> Ninguno
                            </button>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; background: var(--bg-color); padding: 0.85rem; border-radius: 12px; border: 1px solid var(--border-color); max-height: 310px; overflow-y: auto;">
                        <?php foreach($all_modules as $mod_key => $mod_data): 
                            $mod_name = is_array($mod_data) ? $mod_data['name'] : $mod_data;
                            $mod_icon = is_array($mod_data) ? $mod_data['icon'] : 'ph-cube';
                        ?>
                        <label class="perm-card-label" style="font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.55rem; font-size: 12px; padding: 0.45rem 0.65rem; border-radius: 8px; background: var(--bg-surface); border: 1px solid var(--border-color); transition: all 0.15s ease; user-select: none;">
                            <input type="checkbox" name="modules[]" value="<?php echo $mod_key; ?>" class="create-perm-cb" style="accent-color: var(--primary-color); width: 15px; height: 15px; flex-shrink: 0; cursor: pointer;">
                            <i class="ph <?php echo $mod_icon; ?>" style="font-size: 15px; color: var(--primary-color); flex-shrink: 0;"></i>
                            <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-main);"><?php echo htmlspecialchars($mod_name); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Control de Asistencia Switcher -->
                <div class="attendance-setting-card">
                    <div class="attendance-setting-info">
                        <div class="attendance-setting-title">
                            <i class="ph ph-clock-user" style="color: var(--primary-color);"></i> Control de Asistencia y Horario Fijo
                        </div>
                        <div class="attendance-setting-desc">
                            Activa para exigir horario laboral y marcación obligatoria. Desactiva para permitir acceso libre sin asistencia.
                        </div>
                    </div>
                    <label class="modern-switch-ios">
                        <input type="checkbox" name="requires_attendance" id="create_role_attendance" value="1" checked>
                        <span class="slider-ios"></span>
                    </label>
                </div>
            </div>

            <div class="modal-footer" style="padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-light btn-close-modal" style="border-radius: 8px;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 8px; font-weight: 600;">Guardar Rol</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Rol -->
<div id="modal-edit-role" class="modal-overlay">
    <div class="modal-content" style="max-width: 680px; border-radius: 18px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="ph ph-pencil-simple"></i> Editar Rol</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="role_edit">
            <input type="hidden" name="role_id" id="edit_role_id" value="">
            
            <div class="modal-body" style="padding: 1.5rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_role_name" style="font-weight: 600; font-size: 13px;">Nombre del Rol</label>
                        <input type="text" id="edit_role_name" name="role_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_role_desc" style="font-weight: 600; font-size: 13px;">Descripción</label>
                        <input type="text" id="edit_role_desc" name="role_desc" class="form-control">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
                        <label style="margin: 0; font-weight: 600; font-size: 13px;">Módulos Permitidos (<?php echo count($all_modules); ?>)</label>
                        <div style="display: flex; gap: 0.4rem;">
                            <button type="button" id="btn-select-all-edit" class="btn btn-xs" style="font-size: 11px; padding: 3px 9px; border-radius: 6px; background: var(--bg-surface); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="ph ph-checks"></i> Todos
                            </button>
                            <button type="button" id="btn-deselect-all-edit" class="btn btn-xs" style="font-size: 11px; padding: 3px 9px; border-radius: 6px; background: var(--bg-surface); border: 1px solid var(--border-color); color: var(--text-muted); font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="ph ph-x"></i> Ninguno
                            </button>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; background: var(--bg-color); padding: 0.85rem; border-radius: 12px; border: 1px solid var(--border-color); max-height: 310px; overflow-y: auto;">
                        <?php foreach($all_modules as $mod_key => $mod_data): 
                            $mod_name = is_array($mod_data) ? $mod_data['name'] : $mod_data;
                            $mod_icon = is_array($mod_data) ? $mod_data['icon'] : 'ph-cube';
                        ?>
                        <label class="perm-card-label" style="font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.55rem; font-size: 12px; padding: 0.45rem 0.65rem; border-radius: 8px; background: var(--bg-surface); border: 1px solid var(--border-color); transition: all 0.15s ease; user-select: none;">
                            <input type="checkbox" name="modules[]" value="<?php echo $mod_key; ?>" class="edit-perm-cb" style="accent-color: var(--primary-color); width: 15px; height: 15px; flex-shrink: 0; cursor: pointer;">
                            <i class="ph <?php echo $mod_icon; ?>" style="font-size: 15px; color: var(--primary-color); flex-shrink: 0;"></i>
                            <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-main);"><?php echo htmlspecialchars($mod_name); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Control de Asistencia Switcher en Edición -->
                <div class="attendance-setting-card" id="edit-role-attendance-box">
                    <div class="attendance-setting-info">
                        <div class="attendance-setting-title">
                            <i class="ph ph-clock-user" style="color: var(--primary-color);"></i> Control de Asistencia y Horario Fijo
                        </div>
                        <div class="attendance-setting-desc">
                            Activa para exigir horario laboral y marcación obligatoria. Desactiva para permitir acceso libre sin asistencia.
                        </div>
                    </div>
                    <label class="modern-switch-ios">
                        <input type="checkbox" name="requires_attendance" id="edit_role_attendance" value="1">
                        <span class="slider-ios"></span>
                    </label>
                </div>
            </div>

            <div class="modal-footer" style="padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-light btn-close-modal" style="border-radius: 8px;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 8px; font-weight: 600;">Actualizar Rol</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Eliminar Rol -->
<div id="modal-delete-role" class="modal-overlay">
    <div class="modal-content" style="max-width: 420px; border-radius: 16px;">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 class="modal-title" style="color: var(--danger-color);"><i class="ph ph-warning-circle"></i> Eliminar Rol</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="role_delete">
            <input type="hidden" name="role_id" id="delete_role_id" value="">
            
            <div class="modal-body">
                <p style="margin: 0 0 0.5rem 0; color: var(--text-main);">¿Estás seguro de que deseas eliminar este rol? Los usuarios asignados perderán los permisos asociados.</p>
                <p style="color: var(--danger-color); font-weight: 600; font-size: 12px;">Esta acción no se puede deshacer.</p>
            </div>

            <div class="modal-footer" style="border-top: none;">
                <button type="button" class="btn btn-light btn-close-modal" style="border-radius: 8px;">Cancelar</button>
                <button type="submit" class="btn" style="background: var(--danger-color); color: white; border-radius: 8px; font-weight: 600;">Sí, Eliminar Rol</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Quick select/deselect for Create Modal
    const btnSelectAllCreate = document.getElementById('btn-select-all-create');
    const btnDeselectAllCreate = document.getElementById('btn-deselect-all-create');
    if (btnSelectAllCreate) {
        btnSelectAllCreate.addEventListener('click', () => {
            document.querySelectorAll('.create-perm-cb').forEach(cb => cb.checked = true);
        });
    }
    if (btnDeselectAllCreate) {
        btnDeselectAllCreate.addEventListener('click', () => {
            document.querySelectorAll('.create-perm-cb').forEach(cb => cb.checked = false);
        });
    }

    // Quick select/deselect for Edit Modal
    const btnSelectAllEdit = document.getElementById('btn-select-all-edit');
    const btnDeselectAllEdit = document.getElementById('btn-deselect-all-edit');
    if (btnSelectAllEdit) {
        btnSelectAllEdit.addEventListener('click', () => {
            document.querySelectorAll('.edit-perm-cb').forEach(cb => cb.checked = true);
        });
    }
    if (btnDeselectAllEdit) {
        btnDeselectAllEdit.addEventListener('click', () => {
            document.querySelectorAll('.edit-perm-cb').forEach(cb => cb.checked = false);
        });
    }

    // Populate Edit Modal
    const editBtns = document.querySelectorAll('.edit-role-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            const name = btn.getAttribute('data-name');
            const desc = btn.getAttribute('data-desc');
            const att = btn.getAttribute('data-attendance') || '1';
            const perms = JSON.parse(btn.getAttribute('data-perms') || '[]');
            
            document.getElementById('edit_role_id').value = id;
            document.getElementById('edit_role_name').value = name;
            document.getElementById('edit_role_desc').value = desc;
            
            const attSwitch = document.getElementById('edit_role_attendance');
            const attBox = document.getElementById('edit-role-attendance-box');
            if (attSwitch) {
                attSwitch.checked = (att == '1');
            }
            if (attBox) {
                attBox.style.display = (id == '1') ? 'none' : 'flex';
            }
            
            const checkboxes = document.querySelectorAll('.edit-perm-cb');
            checkboxes.forEach(cb => {
                cb.checked = perms.includes(cb.value);
            });
        });
    });

    // AJAX Switcher en la Tabla de Roles
    document.querySelectorAll('.role-attendance-toggle').forEach(toggle => {
        toggle.addEventListener('change', async function() {
            const roleId = this.getAttribute('data-role-id');
            const isChecked = this.checked ? 1 : 0;
            const labelEl = document.getElementById(`role-att-label-${roleId}`);
            
            if (labelEl) {
                labelEl.className = `attendance-text-state ${isChecked ? 'is-fixed' : 'is-free'}`;
                labelEl.innerHTML = isChecked 
                    ? '<i class="ph ph-clock"></i> Horario Fijo' 
                    : '<i class="ph ph-infinity"></i> Sin Asistencia';
            }
            
            try {
                const formData = new FormData();
                formData.append('action_type', 'ajax_toggle_role_attendance');
                formData.append('role_id', roleId);
                formData.append('status', isChecked);
                
                const res = await fetch('index.php?module=config', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showConfigToast(isChecked ? 'Rol configurado: Horario Fijo' : 'Rol configurado: Sin Asistencia (Libre)');
                } else {
                    alert(data.error || 'Error al actualizar asistencia.');
                    this.checked = !this.checked;
                    if (labelEl) {
                        labelEl.className = `attendance-text-state ${this.checked ? 'is-fixed' : 'is-free'}`;
                        labelEl.innerHTML = this.checked 
                            ? '<i class="ph ph-clock"></i> Horario Fijo' 
                            : '<i class="ph ph-infinity"></i> Sin Asistencia';
                    }
                }
            } catch (err) {
                console.error(err);
                alert(err.message || 'Error al conectar con el servidor.');
                this.checked = !this.checked;
                if (labelEl) {
                    labelEl.className = `attendance-text-state ${this.checked ? 'is-fixed' : 'is-free'}`;
                    labelEl.innerHTML = this.checked 
                        ? '<i class="ph ph-clock"></i> Horario Fijo' 
                        : '<i class="ph ph-infinity"></i> Sin Asistencia';
                }
            }
        });
    });

    // Populate Delete Modal
    const deleteBtns = document.querySelectorAll('.delete-role-btn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('delete_role_id').value = btn.getAttribute('data-id');
        });
    });
});

function showConfigToast(msg) {
    let toast = document.getElementById('configToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'configToast';
        toast.className = 'config-toast-msg';
        document.body.appendChild(toast);
    }
    toast.innerHTML = `<i class="ph ph-check-circle" style="color:#10b981; font-size:16px;"></i> ${msg}`;
    toast.classList.add('show');
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => {
        toast.classList.remove('show');
    }, 2500);
}
</script>
