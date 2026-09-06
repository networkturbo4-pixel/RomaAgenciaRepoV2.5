        </div>
    </main>
</div>

<!-- Profile Modal (Modern Native App Style - Matching Reference) -->
<div class="modal-overlay" id="profile-modal">
    <div class="modal-content profile-app-modal">
        <!-- Top Inset Cover Banner (Dynamic Style Presets) -->
        <div class="profile-banner-wrap">
            <div class="profile-cover-banner" id="profile-cover-banner" data-cover-style="cobalt">
                <!-- Cover Style Picker Trigger Button (Top Left) -->
                <button type="button" class="profile-banner-palette-btn" id="btn-toggle-cover-picker" title="Personalizar estilo de portada">
                    <i class="ph-bold ph-palette"></i>
                    <span>Portada</span>
                </button>

                <div class="profile-banner-symbols" id="profile-banner-symbols" aria-hidden="true">
                    <span>↗</span>
                    <span>↘</span>
                    <span>↖</span>
                    <span>↙</span>
                </div>
                <div class="profile-banner-grid" aria-hidden="true"></div>
                <button type="button" class="profile-hero-close btn-close-modal" aria-label="Cerrar modal">
                    <i class="ph ph-x"></i>
                </button>
            </div>
        </div>

        <!-- Floating Cover Style Popover -->
        <div class="profile-cover-picker-popover" id="profile-cover-picker" style="display: none;">
            <div class="pcp-header">
                <div class="pcp-title">
                    <i class="ph-bold ph-palette"></i>
                    <span>Estilos de Portada</span>
                </div>
                <button type="button" class="pcp-close" id="btn-close-cover-picker" aria-label="Cerrar"><i class="ph ph-x"></i></button>
            </div>
            <div class="pcp-grid">
                <button type="button" class="pcp-item active" data-style="cobalt" title="Azul Eléctrico">
                    <span class="pcp-swatch swatch-cobalt"><span class="pcp-glyphs">↗ ↘</span></span>
                    <span class="pcp-label">Azul Eléctrico</span>
                    <span class="pcp-check"><i class="ph-bold ph-check"></i></span>
                </button>
                <button type="button" class="pcp-item" data-style="system" title="Color del Sistema">
                    <span class="pcp-swatch swatch-system"><span class="pcp-glyphs">✦ ★</span></span>
                    <span class="pcp-label">Color Sistema</span>
                    <span class="pcp-check"><i class="ph-bold ph-check"></i></span>
                </button>
                <button type="button" class="pcp-item" data-style="emerald" title="Esmeralda Tech">
                    <span class="pcp-swatch swatch-emerald"><span class="pcp-glyphs">◈ ◆</span></span>
                    <span class="pcp-label">Esmeralda</span>
                    <span class="pcp-check"><i class="ph-bold ph-check"></i></span>
                </button>
                <button type="button" class="pcp-item" data-style="dark" title="Titanium OLED">
                    <span class="pcp-swatch swatch-dark"><span class="pcp-glyphs">✕ ◇</span></span>
                    <span class="pcp-label">Titanium</span>
                    <span class="pcp-check"><i class="ph-bold ph-check"></i></span>
                </button>
                <button type="button" class="pcp-item" data-style="purple" title="Cosmic Cyber">
                    <span class="pcp-swatch swatch-purple"><span class="pcp-glyphs">✦ ↗</span></span>
                    <span class="pcp-label">Cosmic</span>
                    <span class="pcp-check"><i class="ph-bold ph-check"></i></span>
                </button>
                <button type="button" class="pcp-item" data-style="sunset" title="Sunset Coral">
                    <span class="pcp-swatch swatch-sunset"><span class="pcp-glyphs">☀ ★</span></span>
                    <span class="pcp-label">Sunset</span>
                    <span class="pcp-check"><i class="ph-bold ph-check"></i></span>
                </button>
                <button type="button" class="pcp-item" data-style="ocean" title="Océano Deep">
                    <span class="pcp-swatch swatch-ocean"><span class="pcp-glyphs">≋ ↗</span></span>
                    <span class="pcp-label">Océano</span>
                    <span class="pcp-check"><i class="ph-bold ph-check"></i></span>
                </button>
                <button type="button" class="pcp-item" data-style="mesh" title="Aurora Mesh">
                    <span class="pcp-swatch swatch-mesh"><span class="pcp-glyphs">▲ ▼</span></span>
                    <span class="pcp-label">Aurora</span>
                    <span class="pcp-check"><i class="ph-bold ph-check"></i></span>
                </button>
            </div>
        </div>

        <!-- Overlapping Profile Avatar Row & Identity -->
        <div class="profile-header-content">
            <div class="profile-avatar-row">
                <!-- Circular Avatar with Verified Badge -->
                <div class="profile-avatar-wrap">
                    <div class="profile-avatar-circle" id="profile-avatar-preview" onclick="document.getElementById('profile-avatar-input').click()" title="Haz clic para cambiar foto">
                        <span class="profile-avatar-cam"><i class="ph ph-camera"></i></span>
                    </div>
                    <span class="profile-verified-badge" title="Cuenta verificada">
                        <i class="ph-bold ph-check"></i>
                    </span>
                    <input type="file" id="profile-avatar-input" accept="image/*" style="display:none;">
                </div>

                <!-- Avatar Actions (Right aligned next to avatar) -->
                <div class="profile-avatar-actions">
                    <button type="button" class="pma-btn pma-btn-upload" onclick="document.getElementById('profile-avatar-input').click()">
                        <i class="ph ph-camera"></i> Cambiar
                    </button>
                    <button type="button" class="pma-btn pma-btn-remove" id="btn-remove-avatar">
                        <i class="ph ph-trash"></i> Quitar
                    </button>
                </div>
            </div>

            <!-- Identity: Name + Star + Subtitle -->
            <div class="profile-identity">
                <div class="profile-name-row">
                    <h3 class="profile-display-name" id="profile-display-name">Cesar Mendoza</h3>
                    <span class="profile-star-badge" title="Miembro Verificado">★</span>
                </div>
                <p class="profile-display-subtitle" id="profile-display-subtitle">Administrador | Roma Agencia</p>
            </div>
        </div>

        <!-- Segmented Tab Switcher (Modern Native App Control) -->
        <div class="profile-tabs-wrapper">
            <div class="profile-tabs-nav">
                <button type="button" class="profile-tab-btn active" data-tab="profile-info-tab">
                    <i class="ph ph-user"></i>
                    <span>Información</span>
                </button>
                <button type="button" class="profile-tab-btn" data-tab="profile-security-tab">
                    <i class="ph ph-shield-check"></i>
                    <span>Seguridad</span>
                </button>
            </div>
        </div>

        <!-- Tab: Info -->
        <div class="profile-tab-pane active" id="profile-info-tab">
            <div class="pm-field">
                <label for="profile-name">Nombre completo</label>
                <div class="pm-field-icon">
                    <i class="ph ph-user"></i>
                    <input type="text" id="profile-name" placeholder="Tu nombre completo">
                </div>
            </div>
            <div class="pm-field">
                <label for="profile-username">Nombre de usuario</label>
                <div class="pm-field-icon">
                    <i class="ph ph-at"></i>
                    <input type="text" id="profile-username" placeholder="nombre_de_usuario">
                </div>
            </div>
            <div class="pm-field-row">
                <div class="pm-field">
                    <label for="profile-email">Correo electrónico</label>
                    <div class="pm-field-icon">
                        <i class="ph ph-envelope-simple"></i>
                        <input type="email" id="profile-email" placeholder="correo@ejemplo.com">
                    </div>
                </div>
                <div class="pm-field">
                    <label for="profile-phone">Teléfono</label>
                    <div class="pm-field-icon">
                        <i class="ph ph-phone"></i>
                        <input type="text" id="profile-phone" placeholder="+51 999 999 999">
                    </div>
                </div>
            </div>
            <!-- Cover Style Selector inside Info Tab -->
            <div class="pm-cover-selection">
                <div class="pm-cover-label-row">
                    <label>Estilo de portada</label>
                    <span class="pm-cover-current-name" id="pm-cover-current-name">Azul Eléctrico</span>
                </div>
                <div class="pm-cover-swatches" id="pm-cover-swatches">
                    <button type="button" class="pm-swatch-btn swatch-cobalt active" data-style="cobalt" title="Azul Eléctrico"></button>
                    <button type="button" class="pm-swatch-btn swatch-system" data-style="system" title="Color del Sistema"></button>
                    <button type="button" class="pm-swatch-btn swatch-emerald" data-style="emerald" title="Esmeralda Tech"></button>
                    <button type="button" class="pm-swatch-btn swatch-dark" data-style="dark" title="Titanium OLED"></button>
                    <button type="button" class="pm-swatch-btn swatch-purple" data-style="purple" title="Cosmic Cyber"></button>
                    <button type="button" class="pm-swatch-btn swatch-sunset" data-style="sunset" title="Sunset Coral"></button>
                    <button type="button" class="pm-swatch-btn swatch-ocean" data-style="ocean" title="Océano Deep"></button>
                    <button type="button" class="pm-swatch-btn swatch-mesh" data-style="mesh" title="Aurora Mesh"></button>
                </div>
            </div>
            <button type="button" class="pm-save-btn" id="btn-save-profile">
                <i class="ph ph-check-circle"></i>
                <span>Guardar cambios</span>
            </button>
        </div>

        <!-- Tab: Security -->
        <div class="profile-tab-pane" id="profile-security-tab">
            <div class="pm-pw-header">
                <div class="pm-pw-header-icon"><i class="ph ph-lock-key"></i></div>
                <div class="pm-pw-header-text">
                    <h4>Seguridad de la cuenta</h4>
                    <p>Asegúrate de usar una contraseña robusta</p>
                </div>
            </div>
            <div class="pm-field">
                <label for="profile-current-pw">Contraseña actual</label>
                <div class="pm-field-pw">
                    <input type="password" id="profile-current-pw" placeholder="••••••••">
                    <button type="button" class="pm-pw-toggle" tabindex="-1" aria-label="Mostrar contraseña"><i class="ph ph-eye"></i></button>
                </div>
            </div>
            <div class="pm-field">
                <label for="profile-new-pw">Nueva contraseña</label>
                <div class="pm-field-pw">
                    <input type="password" id="profile-new-pw" placeholder="Mínimo 8 caracteres">
                    <button type="button" class="pm-pw-toggle" tabindex="-1" aria-label="Mostrar contraseña"><i class="ph ph-eye"></i></button>
                </div>
                <div class="pm-pw-strength"><div class="pm-pw-strength-bar" id="pm-pw-strength-bar"></div></div>
            </div>
            <div class="pm-field">
                <label for="profile-confirm-pw">Confirmar contraseña</label>
                <div class="pm-field-pw">
                    <input type="password" id="profile-confirm-pw" placeholder="Repite la contraseña">
                    <button type="button" class="pm-pw-toggle" tabindex="-1" aria-label="Mostrar contraseña"><i class="ph ph-eye"></i></button>
                </div>
            </div>
            <button type="button" class="pm-pw-btn" id="btn-change-pw">
                <i class="ph ph-key"></i>
                <span>Actualizar contraseña</span>
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
<!-- Flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
<!-- Tagify -->
<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>

<script src="assets/js/app.js?v=<?php echo filemtime('assets/js/app.js'); ?>"></script>
<script src="assets/js/push_notifications.js?v=<?php echo filemtime('assets/js/push_notifications.js'); ?>"></script>
<?php if (file_exists('assets/js/collaboration.js')): ?>
<script src="assets/js/collaboration.js?v=<?php echo filemtime('assets/js/collaboration.js'); ?>"></script>
<?php endif; ?>

<script>
<?php if (isset($_SESSION['user_id'])): ?>
// Profile Modal Logic
const PROFILE_COVER_STYLES = {
    cobalt: { name: 'Azul Eléctrico', symbols: '<span>↗</span><span>↘</span><span>↖</span><span>↙</span>' },
    system: { name: 'Color del Sistema', symbols: '<span>✦</span><span>★</span><span>★</span><span>✦</span>' },
    emerald: { name: 'Esmeralda Tech', symbols: '<span>◈</span><span>◆</span><span>◆</span><span>◈</span>' },
    dark: { name: 'Titanium OLED', symbols: '<span>✕</span><span>◇</span><span>◇</span><span>✕</span>' },
    purple: { name: 'Cosmic Cyber', symbols: '<span>✦</span><span>↗</span><span>↖</span><span>✦</span>' },
    sunset: { name: 'Sunset Coral', symbols: '<span>☀</span><span>★</span><span>★</span><span>☀</span>' },
    ocean: { name: 'Océano Deep', symbols: '<span>≋</span><span>↗</span><span>↖</span><span>≋</span>' },
    mesh: { name: 'Aurora Mesh', symbols: '<span>▲</span><span>▼</span><span>▲</span><span>▼</span>' }
};

let currentProfileCoverStyle = 'cobalt';
try {
    currentProfileCoverStyle = localStorage.getItem('profile_cover_style') || 'cobalt';
} catch(e) {}

function applyProfileCoverStyle(styleKey, saveToServer = true) {
    if (!PROFILE_COVER_STYLES[styleKey]) styleKey = 'cobalt';
    currentProfileCoverStyle = styleKey;
    const banner = document.getElementById('profile-cover-banner');
    if (banner) {
        banner.setAttribute('data-cover-style', styleKey);
        const symbols = document.getElementById('profile-banner-symbols');
        if (symbols && PROFILE_COVER_STYLES[styleKey]) {
            symbols.innerHTML = PROFILE_COVER_STYLES[styleKey].symbols;
        }
    }
    const currentNameEl = document.getElementById('pm-cover-current-name');
    if (currentNameEl && PROFILE_COVER_STYLES[styleKey]) {
        currentNameEl.textContent = PROFILE_COVER_STYLES[styleKey].name;
    }
    document.querySelectorAll('.pcp-item').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.style === styleKey);
    });
    document.querySelectorAll('.pm-swatch-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.style === styleKey);
    });
    try {
        localStorage.setItem('profile_cover_style', styleKey);
    } catch(e) {}

    if (saveToServer) {
        fetch('modules/config/ajax_update_profile.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'save_cover_style', cover_style: styleKey })
        }).catch(() => {});
    }
}

// Immediate initial application
applyProfileCoverStyle(currentProfileCoverStyle, false);

async function openProfileModal() {
    const modal = document.getElementById('profile-modal');
    if (!modal) return;
    modal.classList.add('active');
    // Close cover picker popover if open
    const picker = document.getElementById('profile-cover-picker');
    if (picker) picker.style.display = 'none';

    // Reset to first tab
    document.querySelectorAll('.profile-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.profile-tab-pane').forEach(p => p.classList.remove('active'));
    const firstTabBtn = document.querySelector('.profile-tab-btn[data-tab="profile-info-tab"]');
    if (firstTabBtn) firstTabBtn.classList.add('active');
    const firstTabPane = document.getElementById('profile-info-tab');
    if (firstTabPane) firstTabPane.classList.add('active');

    try {
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

        // Update header display name & subtitle
        const dispName = document.getElementById('profile-display-name');
        if (dispName) dispName.textContent = u.name || 'Mi Perfil';
        const dispSub = document.getElementById('profile-display-subtitle');
        if (dispSub) {
            const role = u.role_display || 'Administrador';
            dispSub.textContent = `${role} | Roma Agencia`;
        }

        // Apply saved cover style from DB or fallback to localStorage
        const userCover = u.profile_cover_style || currentProfileCoverStyle;
        applyProfileCoverStyle(userCover, false);

        const preview = document.getElementById('profile-avatar-preview');
        if (preview) {
            if (u.avatar) {
                preview.innerHTML = `<img src="${u.avatar}" style="width:100%;height:100%;object-fit:cover;" alt="Avatar"><span class="profile-avatar-cam"><i class="ph ph-camera"></i></span>`;
            } else {
                preview.innerHTML = `${(u.name || 'U').charAt(0).toUpperCase()}<span class="profile-avatar-cam"><i class="ph ph-camera"></i></span>`;
            }
        }
    } catch (err) {
        console.error('Error al cargar perfil:', err);
    }
}

// Cover Picker Toggle and Swatch Selection Events
document.getElementById('btn-toggle-cover-picker')?.addEventListener('click', (e) => {
    e.stopPropagation();
    const p = document.getElementById('profile-cover-picker');
    if (p) p.style.display = p.style.display === 'none' ? 'block' : 'none';
});

document.getElementById('btn-close-cover-picker')?.addEventListener('click', (e) => {
    e.stopPropagation();
    const p = document.getElementById('profile-cover-picker');
    if (p) p.style.display = 'none';
});

// Click inside style popover items
document.querySelectorAll('.pcp-item').forEach(btn => {
    btn.addEventListener('click', () => {
        applyProfileCoverStyle(btn.dataset.style, true);
    });
});

// Click inside Info tab swatches
document.querySelectorAll('.pm-swatch-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        applyProfileCoverStyle(btn.dataset.style, true);
    });
});

// Close popover when clicking elsewhere inside modal
document.querySelector('#profile-modal .profile-app-modal')?.addEventListener('click', (e) => {
    const picker = document.getElementById('profile-cover-picker');
    const trigger = document.getElementById('btn-toggle-cover-picker');
    if (picker && picker.style.display !== 'none') {
        if (!picker.contains(e.target) && !trigger?.contains(e.target)) {
            picker.style.display = 'none';
        }
    }
});

// Tab Switching
document.querySelectorAll('.profile-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.profile-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.profile-tab-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

// Password Visibility Toggle
document.querySelectorAll('.pm-pw-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = btn.previousElementSibling;
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'ph ph-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'ph ph-eye';
        }
    });
});

// Password Strength Indicator
document.getElementById('profile-new-pw').addEventListener('input', function() {
    const bar = document.getElementById('pm-pw-strength-bar');
    const val = this.value;
    bar.className = 'pm-pw-strength-bar';
    if (val.length === 0) { bar.className = 'pm-pw-strength-bar'; return; }
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    if (score <= 1) bar.classList.add('weak');
    else if (score <= 2) bar.classList.add('medium');
    else bar.classList.add('strong');
});

// Avatar upload
document.getElementById('profile-avatar-input').addEventListener('change', async function() {
    if (!this.files[0]) return;
    const fd = new FormData();
    fd.append('action', 'update_avatar');
    fd.append('avatar', this.files[0]);
    const res = await fetch('modules/config/ajax_update_profile.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        document.getElementById('profile-avatar-preview').innerHTML = `<img src="${data.avatar}" style="width:100%;height:100%;object-fit:cover;"><span class="profile-avatar-cam"><i class="ph ph-camera"></i></span>`;
        const sidebarAvatar = document.getElementById('sidebar-avatar');
        if (sidebarAvatar) sidebarAvatar.innerHTML = `<img src="${data.avatar}" style="width:100%;height:100%;object-fit:cover;">`;
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
        document.getElementById('profile-avatar-preview').innerHTML = `${name.charAt(0).toUpperCase()}<span class="profile-avatar-cam"><i class="ph ph-camera"></i></span>`;
        const sidebarAvatar = document.getElementById('sidebar-avatar');
        if (sidebarAvatar) sidebarAvatar.innerHTML = name.charAt(0).toUpperCase();
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Foto eliminada', showConfirmButton:false, timer:2000 });
    }
});

// Save profile
document.getElementById('btn-save-profile').addEventListener('click', async () => {
    const btn = document.getElementById('btn-save-profile');
    btn.classList.add('loading');
    const fd = new URLSearchParams({
        action: 'update_profile',
        name: document.getElementById('profile-name').value,
        username: document.getElementById('profile-username').value,
        email: document.getElementById('profile-email').value,
        phone: document.getElementById('profile-phone').value,
        cover_style: currentProfileCoverStyle
    });
    const res = await fetch('modules/config/ajax_update_profile.php', { method:'POST', body: fd });
    const data = await res.json();
    btn.classList.remove('loading');
    if (data.success) {
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Perfil actualizado', showConfirmButton:false, timer:2000 });
        // Update header & topbar/sidebar name
        const newName = document.getElementById('profile-name').value;
        const dispName = document.getElementById('profile-display-name');
        if (dispName) dispName.textContent = newName;
        const nameSpan = document.querySelector('.user-details span:first-child');
        if (nameSpan) nameSpan.textContent = newName;
        const sidebarName = document.querySelector('.sidebar-profile-info span:first-child');
        if (sidebarName) sidebarName.textContent = newName;
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
        document.getElementById('pm-pw-strength-bar').className = 'pm-pw-strength-bar';
    } else {
        Swal.fire({ icon:'error', title:'Error', text: data.error });
    }
});
<?php endif; ?>
</script>

<!-- Fancybox JS -->
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
    Fancybox.bind("[data-fancybox]", {
        // Your custom options
    });
</script>

<?php if (isset($_SESSION['user_id'])): ?>
<!-- Google Drive Explorer -->
<link rel="stylesheet" href="assets/css/drive.css?v=<?php echo filemtime('assets/css/drive.css'); ?>_2">
<script src="assets/js/drive.js?v=<?php echo filemtime('assets/js/drive.js'); ?>_2"></script>
<?php include 'includes/drive_modal.php'; ?>

<!-- Command Palette -->
<script src="assets/js/command_palette.js?v=<?php echo filemtime('assets/js/command_palette.js'); ?>"></script>
<?php endif; ?>

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

// Global: Force disable autocomplete on all search/filter inputs
document.querySelectorAll('input[type="text"], input[type="search"]').forEach(function(input) {
    const id = (input.id || '').toLowerCase();
    const placeholder = (input.placeholder || '').toLowerCase();
    const name = (input.name || '').toLowerCase();
    if (id.includes('search') || id.includes('filter') || id.includes('buscar') ||
        placeholder.includes('buscar') || placeholder.includes('search') || placeholder.includes('filtrar') ||
        name.includes('search') || name.includes('filter')) {
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('readonly', '');
        input.value = '';
        input.addEventListener('focus', function() {
            this.removeAttribute('readonly');
        }, { once: true });
    }
});
</script>

<?php
// Pseudo-Cron para Copias de Seguridad Automáticas
try {
    global $db;
    if (isset($_SESSION['user_id'])) {
        // Solo verificamos si el usuario activo es administrador para no comprometer la seguridad
        $stmt_check_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
        $stmt_check_admin->execute([$_SESSION['user_id']]);
        if ($stmt_check_admin->fetchColumn() == 1) {
            $stmt_bset = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'backup_frequency'");
            $backup_freq = $stmt_bset->fetchColumn() ?: 'disabled';
            
            if ($backup_freq !== 'disabled') {
                $stmt_blast = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'last_backup_time'");
                $last_backup = (int)($stmt_blast->fetchColumn() ?: 0);
                $interval = ($backup_freq === 'daily') ? 86400 : 604800; // 24 horas o 7 días
                
                if (time() - $last_backup > $interval) {
                    $stmt_btype = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'backup_auto_type'");
                    $auto_type = $stmt_btype->fetchColumn() ?: 'db';
                    
                    // Actualizamos last_backup_time inmediatamente para evitar que se dispare múltiples veces simultáneamente
                    $new_time = time();
                    $stmt_upd = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_backup_time'");
                    $stmt_upd->execute([$new_time]);
                    if ($stmt_upd->rowCount() == 0) {
                        $db->query("INSERT INTO settings (setting_key, setting_value) VALUES ('last_backup_time', '$new_time')");
                    }

                    echo "<script>
                        window.addEventListener('load', function() {
                            setTimeout(() => {
                                console.log('Ejecutando copia de seguridad automática en segundo plano...');
                                fetch('ajax/ajax_run_backup.php?type={$auto_type}&auto=1').catch(e => console.error(e));
                            }, 5000);
                        });
                    </script>";
                }
            }
        }
    }
} catch (Exception $e) {}
?>
</body>
</html>
