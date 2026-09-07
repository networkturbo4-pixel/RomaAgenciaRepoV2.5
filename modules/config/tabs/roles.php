<?php
global $db;
$roles_raw = $db->query("SELECT r.*, rp.module_name FROM roles r LEFT JOIN role_permissions rp ON r.id = rp.role_id")->fetchAll();
$roles = [];
foreach($roles_raw as $row) {
    if (!isset($roles[$row['id']])) {
        $roles[$row['id']] = ['id' => $row['id'], 'name' => $row['name'], 'description' => $row['description'], 'perms' => []];
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
        <p class="pane-header-desc">Define los niveles de seguridad y autorizaciones por módulo para cada miembro del equipo.</p>
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
                <th style="width: 60px;">ID</th>
                <th style="width: 200px;">Nombre del Rol</th>
                <th>Descripción</th>
                <th style="width: 180px;">Módulos Asignados</th>
                <th style="width: 120px; text-align: right;">Acciones</th>
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
                <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                        <?php if ($is_admin): ?>
                        <button type="button" class="btn btn-outline btn-sm edit-role-btn" style="padding: 0.35rem 0.65rem; border-radius: 8px;" 
                                data-modal-target="modal-edit-role" 
                                data-id="<?php echo $role['id']; ?>" 
                                data-name="<?php echo htmlspecialchars($role['name']); ?>" 
                                data-desc="<?php echo htmlspecialchars($role['description']); ?>" 
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

<!-- Modal: Crear Rol -->
<div id="modal-create-role" class="modal-overlay">
    <div class="modal-content" style="max-width: 650px; border-radius: 16px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="ph ph-shield-star"></i> Crear Nuevo Rol</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="role_create">
            
            <div class="modal-body" style="padding: 1.5rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="role_name">Nombre del Rol</label>
                        <input type="text" id="role_name" name="role_name" class="form-control" required placeholder="Ej. Gerente de Ventas">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="role_desc">Descripción del Rol</label>
                        <input type="text" id="role_desc" name="role_desc" class="form-control" placeholder="Funciones del rol">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Módulos Permitidos</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem; background: var(--bg-color); padding: 1rem; border-radius: 12px; border: 1px solid var(--border-color); max-height: 250px; overflow-y: auto;">
                        <?php 
                        $all_modules = [
                            'dashboard' => 'Dashboard', 'workspace' => 'Workspace', 'desarrollo_marca' => 'Desarrollo de Marca', 'clients' => 'Clientes', 'suppliers' => 'Proveedores', 'services' => 'Servicios', 'work_orders' => 'Órdenes de Servicio',
                            'calendar' => 'Calendario', 'reuniones' => 'Reuniones', 'quotes' => 'Cotizaciones', 'mensajes' => 'Mensajes', 'pizarras' => 'Pizarras',
                            'forms' => 'Formularios', 'contracts' => 'Contratos', 'romita' => 'Romita IA',
                            'conexiones' => 'Conexiones', 'admin' => 'Administración', 'config' => 'Configuración', 'herramientas' => 'Herramientas'
                        ];
                        foreach($all_modules as $mod_key => $mod_name):
                        ?>
                        <label style="font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.6rem; font-size: 12.5px;">
                            <input type="checkbox" name="modules[]" value="<?php echo $mod_key; ?>" style="accent-color: var(--primary-color); width: 16px; height: 16px;">
                            <?php echo $mod_name; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
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
    <div class="modal-content" style="max-width: 650px; border-radius: 18px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="ph ph-pencil-simple"></i> Editar Rol</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="role_edit">
            <input type="hidden" name="role_id" id="edit_role_id" value="">
            
            <div class="modal-body" style="padding: 1.5rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_role_name">Nombre del Rol</label>
                        <input type="text" id="edit_role_name" name="role_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_role_desc">Descripción</label>
                        <input type="text" id="edit_role_desc" name="role_desc" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Módulos Permitidos</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem; background: var(--bg-color); padding: 1rem; border-radius: 12px; border: 1px solid var(--border-color); max-height: 280px; overflow-y: auto;">
                        <?php foreach($all_modules as $mod_key => $mod_name): ?>
                        <label style="font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.6rem; font-size: 12.5px;">
                            <input type="checkbox" name="modules[]" value="<?php echo $mod_key; ?>" class="edit-perm-cb" style="accent-color: var(--primary-color); width: 16px; height: 16px;">
                            <?php echo $mod_name; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
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
    // Populate Edit Modal
    const editBtns = document.querySelectorAll('.edit-role-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            const name = btn.getAttribute('data-name');
            const desc = btn.getAttribute('data-desc');
            const perms = JSON.parse(btn.getAttribute('data-perms') || '[]');
            
            document.getElementById('edit_role_id').value = id;
            document.getElementById('edit_role_name').value = name;
            document.getElementById('edit_role_desc').value = desc;
            
            const checkboxes = document.querySelectorAll('.edit-perm-cb');
            checkboxes.forEach(cb => cb.checked = false);
            
            checkboxes.forEach(cb => {
                if (perms.includes(cb.value)) {
                    cb.checked = true;
                }
            });
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
</script>
