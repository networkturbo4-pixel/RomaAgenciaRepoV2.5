<?php
global $db;
$users = $db->query("SELECT u.id, u.name, u.email, u.created_at, r.name as role_name, u.role_id, u.password FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.id ASC")->fetchAll();
$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
?>

<div class="pane-header">
    <div>
        <h2 class="pane-header-title">
            <i class="ph ph-users"></i> Gestión de Usuarios
        </h2>
        <p class="pane-header-desc">Administra los usuarios con acceso al sistema, sus credenciales y roles asignados.</p>
    </div>
    <?php if ($is_admin): ?>
    <button type="button" class="btn btn-primary" data-modal-target="modal-create-user" style="display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 10px; padding: 0.55rem 1.15rem; font-weight: 600;">
        <i class="ph ph-user-plus"></i> Crear Nuevo Usuario
    </button>
    <?php endif; ?>
</div>

<div class="app-table-wrapper mb-4">
    <table class="app-table">
        <thead>
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Usuario</th>
                <th style="width: 170px;">Rol Asignado</th>
                <th style="width: 150px;">Método de Acceso</th>
                <th style="width: 130px;">Fecha Alta</th>
                <th style="width: 120px; text-align: right;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $user): 
                $initials = strtoupper(substr($user['name'], 0, 2));
            ?>
            <tr>
                <td style="font-weight: 600; color: var(--text-muted);">#<?php echo $user['id']; ?></td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, var(--primary-color), #8b5cf6); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; letter-spacing: 0.05em; box-shadow: 0 2px 6px color-mix(in srgb, var(--primary-color) 30%, transparent);">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                        <div>
                            <strong style="color: var(--color-title); display: block; font-size: 13px;"><?php echo htmlspecialchars($user['name']); ?></strong>
                            <span style="color: var(--text-muted); font-size: 12px;"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge-role">
                        <i class="ph ph-shield-star" style="margin-right: 0.25rem;"></i>
                        <?php echo htmlspecialchars($user['role_name'] ?? 'Sin Rol'); ?>
                    </span>
                </td>
                <td>
                    <?php if($user['password']): ?>
                        <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 12px; color: #10b981; font-weight: 500;">
                            <i class="ph ph-lock-key"></i> Contraseña
                        </span>
                    <?php else: ?>
                        <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 12px; color: var(--warning-color); font-weight: 500;">
                            <i class="ph ph-link"></i> Magic Link
                        </span>
                    <?php endif; ?>
                </td>
                <td style="color: var(--text-muted); font-size: 12px;">
                    <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                </td>
                <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 0.4rem; justify-content: flex-end;">
                        <?php if ($is_admin): ?>
                        <button type="button" class="btn btn-outline btn-sm edit-user-btn" style="padding: 0.35rem 0.65rem; border-radius: 8px;" 
                                data-modal-target="modal-edit-user" 
                                data-id="<?php echo $user['id']; ?>" 
                                data-name="<?php echo htmlspecialchars($user['name']); ?>" 
                                data-email="<?php echo htmlspecialchars($user['email']); ?>" 
                                data-role="<?php echo $user['role_id'] ?? 1; ?>"
                                title="Editar Usuario">
                            <i class="ph ph-pencil-simple"></i>
                        </button>
                        <?php if($user['id'] != $_SESSION['user_id']): ?>
                        <button type="button" class="btn btn-outline btn-sm delete-user-btn" style="padding: 0.35rem 0.65rem; border-radius: 8px; color: var(--danger-color); border-color: color-mix(in srgb, var(--danger-color) 30%, transparent);" 
                                data-modal-target="modal-delete-user" 
                                data-id="<?php echo $user['id']; ?>"
                                title="Eliminar Usuario">
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

<!-- Modal: Crear Usuario -->
<div id="modal-create-user" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px; border-radius: 18px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="ph ph-user-plus"></i> Crear Nuevo Usuario</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="user_create">
            
            <div class="modal-body" style="padding: 1.5rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="user_name">Nombre Completo</label>
                        <div class="input-with-icon">
                            <i class="ph ph-user"></i>
                            <input type="text" id="user_name" name="user_name" class="form-control" required placeholder="Ej. Ana Pérez">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="user_email">Correo Electrónico</label>
                        <div class="input-with-icon">
                            <i class="ph ph-envelope"></i>
                            <input type="email" id="user_email" name="user_email" class="form-control" required placeholder="ana@ejemplo.com">
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
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

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="user_password">Contraseña <small class="text-muted">(Opcional)</small></label>
                        <div class="input-with-icon">
                            <i class="ph ph-lock-key"></i>
                            <input type="password" id="user_password" name="user_password" class="form-control" placeholder="En blanco = Magic Link">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-light btn-close-modal" style="border-radius: 8px;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 8px; font-weight: 600;">Guardar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Usuario -->
<div id="modal-edit-user" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px; border-radius: 18px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="ph ph-pencil-simple"></i> Editar Usuario</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="user_edit">
            <input type="hidden" name="user_id" id="edit_user_id" value="">
            
            <div class="modal-body" style="padding: 1.5rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_user_name">Nombre Completo</label>
                        <div class="input-with-icon">
                            <i class="ph ph-user"></i>
                            <input type="text" id="edit_user_name" name="user_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_user_email">Correo Electrónico</label>
                        <div class="input-with-icon">
                            <i class="ph ph-envelope"></i>
                            <input type="email" id="edit_user_email" name="user_email" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
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

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_user_password">Nueva Contraseña <small class="text-muted">(Opcional)</small></label>
                        <div class="input-with-icon">
                            <i class="ph ph-lock-key"></i>
                            <input type="password" id="edit_user_password" name="user_password" class="form-control" placeholder="Dejar en blanco para mantener">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-light btn-close-modal" style="border-radius: 8px;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 8px; font-weight: 600;">Actualizar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Eliminar Usuario -->
<div id="modal-delete-user" class="modal-overlay">
    <div class="modal-content" style="max-width: 420px; border-radius: 16px;">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 class="modal-title" style="color: var(--danger-color);"><i class="ph ph-warning-circle"></i> Eliminar Usuario</h2>
            <button class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <form action="index.php?module=config&action=index" method="POST">
            <input type="hidden" name="action_type" value="user_delete">
            <input type="hidden" name="user_id" id="delete_user_id" value="">
            
            <div class="modal-body">
                <p style="margin: 0 0 0.5rem 0; color: var(--text-main);">¿Estás seguro de que deseas eliminar este usuario? Perderá acceso inmediato a la plataforma.</p>
                <p style="color: var(--danger-color); font-weight: 600; font-size: 12px;">Esta acción no se puede deshacer.</p>
            </div>

            <div class="modal-footer" style="border-top: none;">
                <button type="button" class="btn btn-light btn-close-modal" style="border-radius: 8px;">Cancelar</button>
                <button type="submit" class="btn" style="background: var(--danger-color); color: white; border-radius: 8px; font-weight: 600;">Sí, Eliminar Usuario</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Populate Edit Modal
    const editUserBtns = document.querySelectorAll('.edit-user-btn');
    editUserBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit_user_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_user_name').value = btn.getAttribute('data-name');
            document.getElementById('edit_user_email').value = btn.getAttribute('data-email');
            document.getElementById('edit_user_role').value = btn.getAttribute('data-role');
            document.getElementById('edit_user_password').value = '';
        });
    });

    // Populate Delete Modal
    const deleteUserBtns = document.querySelectorAll('.delete-user-btn');
    deleteUserBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('delete_user_id').value = btn.getAttribute('data-id');
        });
    });
});
</script>
