        </div>
    </main>
</div>

<!-- Profile Modal -->
<div class="modal-overlay" id="profile-modal">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h2>Mi Perfil</h2>
            <button class="btn-icon btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <!-- Avatar Section -->
            <div style="display:flex; flex-direction:column; align-items:center; margin-bottom:1.5rem;">
                <div id="profile-avatar-preview" style="width:80px; height:80px; border-radius:50%; background:var(--primary-color); color:white; display:flex; align-items:center; justify-content:center; font-size:2rem; font-weight:700; overflow:hidden; margin-bottom:0.75rem; cursor:pointer; border:3px solid var(--border-color);" onclick="document.getElementById('profile-avatar-input').click()">
                </div>
                <input type="file" id="profile-avatar-input" accept="image/*" style="display:none;">
                <div style="display:flex; gap:0.5rem;">
                    <button class="btn btn-outline" style="font-size:0.75rem; padding:0.3rem 0.75rem;" onclick="document.getElementById('profile-avatar-input').click()">
                        <i class="ph ph-camera"></i> Cambiar foto
                    </button>
                    <button class="btn btn-outline" id="btn-remove-avatar" style="font-size:0.75rem; padding:0.3rem 0.75rem; color:var(--danger-color); border-color:var(--danger-color);">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            </div>

            <!-- Profile Fields -->
            <div class="form-group" style="margin-bottom:0.75rem;">
                <label class="form-label" style="font-size:0.8rem;">Nombre completo</label>
                <input type="text" id="profile-name" class="form-control" style="border:1px solid var(--border-color);">
            </div>
            <div class="form-group" style="margin-bottom:0.75rem;">
                <label class="form-label" style="font-size:0.8rem;">Nombre de usuario</label>
                <input type="text" id="profile-username" class="form-control" placeholder="@usuario" style="border:1px solid var(--border-color);">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:0.75rem;">
                <div class="form-group">
                    <label class="form-label" style="font-size:0.8rem;">Correo</label>
                    <input type="email" id="profile-email" class="form-control" style="border:1px solid var(--border-color);">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size:0.8rem;">Teléfono</label>
                    <input type="text" id="profile-phone" class="form-control" placeholder="+51..." style="border:1px solid var(--border-color);">
                </div>
            </div>
            <button class="btn btn-primary" id="btn-save-profile" style="width:100%; margin-bottom:1rem;">
                <i class="ph ph-floppy-disk"></i> Guardar Cambios
            </button>

            <!-- Password Section -->
            <hr style="border:0; border-top:1px solid var(--border-color); margin:1rem 0;">
            <h3 style="font-size:0.9rem; font-weight:600; margin-bottom:0.75rem;"><i class="ph ph-lock-key"></i> Cambiar Contraseña</h3>
            <div class="form-group" style="margin-bottom:0.75rem;">
                <input type="password" id="profile-current-pw" class="form-control" placeholder="Contraseña actual" style="border:1px solid var(--border-color);">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:0.75rem;">
                <input type="password" id="profile-new-pw" class="form-control" placeholder="Nueva contraseña" style="border:1px solid var(--border-color);">
                <input type="password" id="profile-confirm-pw" class="form-control" placeholder="Confirmar" style="border:1px solid var(--border-color);">
            </div>
            <button class="btn btn-outline" id="btn-change-pw" style="width:100%;">
                <i class="ph ph-key"></i> Actualizar Contraseña
            </button>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Quill.js -->
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<!-- Pusher -->
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>

<script src="assets/js/app.js?v=<?php echo filemtime('assets/js/app.js'); ?>"></script>
<script src="assets/js/push_notifications.js?v=<?php echo filemtime('assets/js/push_notifications.js'); ?>"></script>
<?php if (file_exists('assets/js/collaboration.js')): ?>
<script src="assets/js/collaboration.js?v=<?php echo filemtime('assets/js/collaboration.js'); ?>"></script>
<?php endif; ?>

<script>
// Profile Modal Logic
async function openProfileModal() {
    document.getElementById('profile-modal').classList.add('active');
    const res = await fetch('modules/config/ajax_update_profile.php', {
        method: 'POST', body: new URLSearchParams({ action: 'get_profile' })
    });
    const data = await res.json();
    if (!data.success) return;
    const u = data.user;
    document.getElementById('profile-name').value = u.name || '';
    document.getElementById('profile-username').value = u.username || '';
    document.getElementById('profile-email').value = u.email || '';
    document.getElementById('profile-phone').value = u.phone || '';
    const preview = document.getElementById('profile-avatar-preview');
    if (u.avatar) {
        preview.innerHTML = `<img src="${u.avatar}" style="width:100%;height:100%;object-fit:cover;">`;
    } else {
        preview.innerHTML = (u.name || 'U').charAt(0).toUpperCase();
    }
}

// Avatar upload
document.getElementById('profile-avatar-input').addEventListener('change', async function() {
    if (!this.files[0]) return;
    const fd = new FormData();
    fd.append('action', 'update_avatar');
    fd.append('avatar', this.files[0]);
    const res = await fetch('modules/config/ajax_update_profile.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        document.getElementById('profile-avatar-preview').innerHTML = `<img src="${data.avatar}" style="width:100%;height:100%;object-fit:cover;">`;
        document.getElementById('topbar-avatar').innerHTML = `<img src="${data.avatar}" style="width:100%;height:100%;object-fit:cover;">`;
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Foto actualizada', showConfirmButton:false, timer:2000 });
    } else {
        Swal.fire({ icon:'error', title:'Error', text: data.error });
    }
});

// Remove avatar
document.getElementById('btn-remove-avatar').addEventListener('click', async () => {
    const res = await fetch('modules/config/ajax_update_profile.php', { method:'POST', body: new URLSearchParams({ action:'remove_avatar' }) });
    if ((await res.json()).success) {
        const name = document.getElementById('profile-name').value || 'U';
        document.getElementById('profile-avatar-preview').innerHTML = name.charAt(0).toUpperCase();
        document.getElementById('topbar-avatar').innerHTML = name.charAt(0).toUpperCase();
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Foto eliminada', showConfirmButton:false, timer:2000 });
    }
});

// Save profile
document.getElementById('btn-save-profile').addEventListener('click', async () => {
    const fd = new URLSearchParams({
        action: 'update_profile',
        name: document.getElementById('profile-name').value,
        username: document.getElementById('profile-username').value,
        email: document.getElementById('profile-email').value,
        phone: document.getElementById('profile-phone').value
    });
    const res = await fetch('modules/config/ajax_update_profile.php', { method:'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Perfil actualizado', showConfirmButton:false, timer:2000 });
        // Update topbar name
        const nameSpan = document.querySelector('.user-details span:first-child');
        if (nameSpan) nameSpan.textContent = document.getElementById('profile-name').value;
    } else {
        Swal.fire({ icon:'error', title:'Error', text: data.error });
    }
});

// Change password
document.getElementById('btn-change-pw').addEventListener('click', async () => {
    const fd = new URLSearchParams({
        action: 'update_password',
        current_password: document.getElementById('profile-current-pw').value,
        new_password: document.getElementById('profile-new-pw').value,
        confirm_password: document.getElementById('profile-confirm-pw').value
    });
    const res = await fetch('modules/config/ajax_update_profile.php', { method:'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Contraseña actualizada', showConfirmButton:false, timer:2000 });
        document.getElementById('profile-current-pw').value = '';
        document.getElementById('profile-new-pw').value = '';
        document.getElementById('profile-confirm-pw').value = '';
    } else {
        Swal.fire({ icon:'error', title:'Error', text: data.error });
    }
});
</script>

<!-- Fancybox JS -->
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
    Fancybox.bind("[data-fancybox]", {
        // Your custom options
    });
</script>

<!-- Google Drive Explorer -->
<link rel="stylesheet" href="assets/css/drive.css?v=<?php echo filemtime('assets/css/drive.css'); ?>">
<script src="assets/js/drive.js?v=<?php echo filemtime('assets/js/drive.js'); ?>"></script>
<?php include 'includes/drive_modal.php'; ?>

<!-- Web Push Notifications -->
<script src="assets/js/push.js"></script>

<script>
// Sidebar Collapse Logic
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('desktop-menu-toggle');
    
    // Set data-title for tooltips
    document.querySelectorAll('.sidebar-nav .nav-item').forEach(item => {
        const span = item.querySelector('span');
        if (span) {
            item.setAttribute('data-title', span.textContent.trim());
        }
    });

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const isCollapsed = document.documentElement.classList.toggle('sidebar-is-collapsed');
            localStorage.setItem('sidebar_collapsed', isCollapsed);
        });
    }
});
</script>

</body>
</html>
