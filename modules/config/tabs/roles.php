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

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 style="font-size: 1.125rem; font-weight: 600; margin: 0;">Roles Existentes</h3>
    <?php if ($is_admin): ?>
    <button class="btn btn-primary btn-pill" data-modal-target="modal-create-role">
        <i class="ph ph-plus"></i> Crear Nuevo Rol
    </button>
    <?php endif; ?>
</div>

<div class="table-responsive mb-4">
    <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
            <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted);">
                <th style="padding: var(--space-3) 0;">ID</th>
                <th style="padding: var(--space-3) 0;">Nombre del Rol</th>
                <th style="padding: var(--space-3) 0;">Descripción</th>
                <th style="padding: var(--space-3) 0;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($roles as $role): ?>
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td data-label="ID" style="padding: var(--space-3) 0;"><?php echo $role['id']; ?></td>
                <td data-label="Nombre del Rol" style="padding: var(--space-3) 0; font-weight: 500;"><?php echo htmlspecialchars($role['name']); ?></td>
                <td data-label="Descripción" style="padding: var(--space-3) 0; color: var(--text-muted);"><?php echo htmlspecialchars($role['description']); ?></td>
                <td data-label="Acciones" style="padding: var(--space-3) 0; display: flex; gap: 0.5rem;">
                    <?php if ($is_admin): ?>
                    <button class="btn btn-outline btn-sm edit-role-btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" 
                            data-modal-target="modal-edit-role" 
                            data-id="<?php echo $role['id']; ?>" 
                            data-name="<?php echo htmlspecialchars($role['name']); ?>" 
                            data-desc="<?php echo htmlspecialchars($role['description']); ?>" 
                            data-perms='<?php echo json_encode($role['perms']); ?>'>
                        <i class="ph ph-pencil-simple"></i> Editar
                    </button>
                    <?php if($role['id'] != 1): ?>
                    <button class="btn btn-outline btn-sm delete-role-btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; border-color: #fee2e2; background: #fef2f2;" 
                            data-modal-target="modal-delete-role" 
                            data-id="<?php echo $role['id']; ?>">
                        <i class="ph ph-trash" style="color: var(--danger-color);"></i>
                    </button>
                    <?php endif; ?>
                    <?php else: ?>
                    <span style="color: var(--text-muted); font-size: 0.85rem;"><i class="ph ph-lock"></i> Solo lectura</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal: Crear Rol -->
<div id="modal-create-role" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="ph ph-shield-star"></i> Crear Nuevo Rol</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="role_create">
            
            <div class="modal-body">
                <div class="callout" style="border-color: var(--primary-color);">
                    💡 Define el nombre del rol y selecciona a qué módulos tendrá acceso dentro del sistema.
                </div>
                
                <div class="form-group">
                    <label for="role_name">Nombre del Rol</label>
                    <input type="text" id="role_name" name="role_name" class="form-control" required placeholder="Ej. Gerente de Ventas">
                </div>
                
                <div class="form-group">
                    <label for="role_desc">Descripción</label>
                    <textarea id="role_desc" name="role_desc" class="form-control" rows="2" placeholder="Breve descripción de las funciones de este rol"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Permisos de Módulos</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; background: var(--bg-color); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <?php 
                        $all_modules = [
                            'dashboard' => 'Dashboard', 'workspace' => 'Workspace', 'desarrollo_marca' => 'Desarrollo de Marca', 'clients' => 'Clientes', 'services' => 'Servicios', 'work_orders' => 'Órdenes de Servicio',
                            'calendar' => 'Calendario', 'reuniones' => 'Reuniones', 'quotes' => 'Cotizaciones', 'mensajes' => 'Mensajes', 'pizarras' => 'Pizarras',
                            'forms' => 'Formularios', 'contracts' => 'Contratos', 'romita' => 'Romita IA', 'client_portal' => 'Portal de Cliente',
                            'conexiones' => 'Conexiones', 'admin' => 'Administración', 'config' => 'Configuración', 'herramientas' => 'Herramientas'
                        ];
                        foreach($all_modules as $mod_key => $mod_name):
                        ?>
                        <label style="font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.6rem;">
                            <div class="modern-switch">
                                <input type="checkbox" name="modules[]" value="<?php echo $mod_key; ?>">
                                <span class="switch-slider"></span>
                            </div>
                            <?php echo $mod_name; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-pill btn-light btn-close-modal">Cancelar</button>
                <button type="submit" class="btn btn-pill btn-primary">Guardar Rol</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Rol -->
<div id="modal-edit-role" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="ph ph-pencil-simple"></i> Editar Rol</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="role_edit">
            <input type="hidden" name="role_id" id="edit_role_id" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_role_name">Nombre del Rol</label>
                    <input type="text" id="edit_role_name" name="role_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_role_desc">Descripción</label>
                    <textarea id="edit_role_desc" name="role_desc" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Permisos de Módulos</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; background: var(--bg-color); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <?php foreach($all_modules as $mod_key => $mod_name): ?>
                        <label style="font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.6rem;">
                            <div class="modern-switch">
                                <input type="checkbox" name="modules[]" value="<?php echo $mod_key; ?>" class="edit-perm-cb">
                                <span class="switch-slider"></span>
                            </div>
                            <?php echo $mod_name; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-pill btn-light btn-close-modal">Cancelar</button>
                <button type="submit" class="btn btn-pill btn-primary">Actualizar Rol</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Eliminar Rol -->
<div id="modal-delete-role" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 class="modal-title" style="color: var(--danger-color);"><i class="ph ph-warning-circle"></i> Eliminar Rol</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="role_delete">
            <input type="hidden" name="role_id" id="delete_role_id" value="">
            
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar este rol? Los usuarios asignados a este rol perderán sus permisos.</p>
                <p>Esta acción <strong>no se puede deshacer</strong>.</p>
            </div>

            <div class="modal-footer" style="border-top: none;">
                <button type="button" class="btn btn-pill btn-light btn-close-modal">Cancelar</button>
                <button type="submit" class="btn btn-pill" style="background: var(--danger-color); color: white;">Sí, Eliminar</button>
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
            
            // Uncheck all first
            const checkboxes = document.querySelectorAll('.edit-perm-cb');
            checkboxes.forEach(cb => cb.checked = false);
            
            // Check the ones the role has
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
