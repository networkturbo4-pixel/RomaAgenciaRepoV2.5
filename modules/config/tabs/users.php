<?php
global $db;
$users = $db->query("SELECT u.id, u.name, u.email, u.created_at, r.name as role_name, u.role_id, u.password FROM users u LEFT JOIN roles r ON u.role_id = r.id")->fetchAll();
$roles = $db->query("SELECT * FROM roles")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 style="font-size: 1.125rem; font-weight: 600; margin: 0;">Usuarios del Sistema</h3>
    <?php if ($is_admin): ?>
    <button class="btn btn-primary btn-pill" data-modal-target="modal-create-user">
        <i class="ph ph-user-plus"></i> Crear Usuario
    </button>
    <?php endif; ?>
</div>

<div class="table-responsive mb-4">
    <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
            <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-muted);">
                <th style="padding: var(--space-3) 0;">Nombre</th>
                <th style="padding: var(--space-3) 0;">Email</th>
                <th style="padding: var(--space-3) 0;">Rol</th>
                <th style="padding: var(--space-3) 0;">Acceso</th>
                <th style="padding: var(--space-3) 0;">Fecha Creación</th>
                <th style="padding: var(--space-3) 0;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $user): ?>
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td data-label="Nombre" style="padding: var(--space-3) 0; font-weight: 500;"><?php echo htmlspecialchars($user['name']); ?></td>
                <td data-label="Email" style="padding: var(--space-3) 0; color: var(--text-muted);"><?php echo htmlspecialchars($user['email']); ?></td>
                <td data-label="Rol" style="padding: var(--space-3) 0;"><span class="badge-role"><?php echo htmlspecialchars($user['role_name'] ?? 'Sin Rol'); ?></span></td>
                <td data-label="Acceso" style="padding: var(--space-3) 0;">
                    <?php if($user['password']): ?>
                        <i class="ph ph-lock-key" style="color: var(--secondary-color);" title="Con contraseña"></i>
                    <?php else: ?>
                        <i class="ph ph-link" style="color: var(--warning-color);" title="Sin contraseña (Magic Link)"></i>
                    <?php endif; ?>
                </td>
                <td data-label="Fecha Creación" style="padding: var(--space-3) 0; color: var(--text-muted); font-size: 0.875rem;"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                <td data-label="Acciones" style="padding: var(--space-3) 0; display: flex; gap: 0.5rem;">
                    <?php if ($is_admin): ?>
                    <button class="btn btn-outline btn-sm edit-user-btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" 
                            data-modal-target="modal-edit-user" 
                            data-id="<?php echo $user['id']; ?>" 
                            data-name="<?php echo htmlspecialchars($user['name']); ?>" 
                            data-email="<?php echo htmlspecialchars($user['email']); ?>" 
                            data-role="<?php echo $user['role_id'] ?? 1; ?>">
                        <i class="ph ph-pencil-simple"></i> Editar
                    </button>
                    <?php if($user['id'] != $_SESSION['user_id']): ?>
                    <button class="btn btn-outline btn-sm delete-user-btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; border-color: #fee2e2; background: #fef2f2;" 
                            data-modal-target="modal-delete-user" 
                            data-id="<?php echo $user['id']; ?>">
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

<!-- Modal: Crear Usuario -->
<div id="modal-create-user" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="ph ph-user-plus"></i> Crear Nuevo Usuario</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="user_create">
            
            <div class="modal-body">
                <div class="callout" style="border-color: var(--secondary-color);">
                    ✨ Ingresa los datos básicos. Si omites la contraseña, el usuario iniciará sesión a través de un Magic Link enviado a su correo.
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="user_name">Nombre Completo</label>
                        <div class="input-with-icon">
                            <i class="ph ph-user"></i>
                            <input type="text" id="user_name" name="user_name" class="form-control" required placeholder="Ej. Ana Pérez">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_email">Correo Electrónico</label>
                        <div class="input-with-icon">
                            <i class="ph ph-envelope"></i>
                            <input type="email" id="user_email" name="user_email" class="form-control" required placeholder="ana@ejemplo.com">
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="user_role">Rol Asignado</label>
                        <div class="input-with-icon">
                            <i class="ph ph-shield-star"></i>
                            <select id="user_role" name="user_role" class="form-control" required>
                                <?php foreach($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="user_password">Contraseña <span class="text-muted" style="font-weight: normal;">(Opcional)</span></label>
                        <div class="input-with-icon">
                            <i class="ph ph-lock-key"></i>
                            <input type="password" id="user_password" name="user_password" class="form-control" placeholder="Dejar en blanco para Magic Link">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-pill btn-light btn-close-modal">Cancelar</button>
                <button type="submit" class="btn btn-pill btn-primary">Guardar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Usuario -->
<div id="modal-edit-user" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="ph ph-pencil-simple"></i> Editar Usuario</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="user_edit">
            <input type="hidden" name="user_id" id="edit_user_id" value="">
            
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="edit_user_name">Nombre Completo</label>
                        <div class="input-with-icon">
                            <i class="ph ph-user"></i>
                            <input type="text" id="edit_user_name" name="user_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_user_email">Correo Electrónico</label>
                        <div class="input-with-icon">
                            <i class="ph ph-envelope"></i>
                            <input type="email" id="edit_user_email" name="user_email" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label for="edit_user_role">Rol Asignado</label>
                        <div class="input-with-icon">
                            <i class="ph ph-shield-star"></i>
                            <select id="edit_user_role" name="user_role" class="form-control" required>
                                <?php foreach($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_user_password">Nueva Contraseña <span class="text-muted" style="font-weight: normal;">(Opcional)</span></label>
                        <div class="input-with-icon">
                            <i class="ph ph-lock-key"></i>
                            <input type="password" id="edit_user_password" name="user_password" class="form-control" placeholder="Dejar en blanco para no cambiar">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-pill btn-light btn-close-modal">Cancelar</button>
                <button type="submit" class="btn btn-pill btn-primary">Actualizar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Eliminar Usuario -->
<div id="modal-delete-user" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 class="modal-title" style="color: var(--danger-color);"><i class="ph ph-warning-circle"></i> Eliminar Usuario</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="user_delete">
            <input type="hidden" name="user_id" id="delete_user_id" value="">
            
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar este usuario del sistema?</p>
                <p>Esta acción <strong>no se puede deshacer</strong> y el usuario no podrá volver a iniciar sesión.</p>
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
    const editBtns = document.querySelectorAll('.edit-user-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            const name = btn.getAttribute('data-name');
            const email = btn.getAttribute('data-email');
            const role = btn.getAttribute('data-role');
            
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_user_name').value = name;
            document.getElementById('edit_user_email').value = email;
            document.getElementById('edit_user_role').value = role;
            document.getElementById('edit_user_password').value = ''; // clear password field
        });
    });

    // Populate Delete Modal
    const deleteBtns = document.querySelectorAll('.delete-user-btn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('delete_user_id').value = btn.getAttribute('data-id');
        });
    });
});
</script>
