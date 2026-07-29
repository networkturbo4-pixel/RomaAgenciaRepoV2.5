<!-- modules/pizarras/components/share_modal.php -->
<style>
    .share-modal-list {
        max-height: 200px; overflow-y: auto; margin-top: 10px;
    }
    .share-user-item {
        display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9;
    }
    .share-user-item:last-child { border-bottom: none; }
    .share-user-info { display: flex; align-items: center; gap: 10px; }
    .share-user-avatar {
        width: 36px; height: 36px; border-radius: 50%; background: #3b82f6; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9rem;
    }
    .share-user-name { font-weight: 600; color: #0f172a; font-size: 0.9rem; }
    .share-user-email { color: #64748b; font-size: 0.8rem; }
    .share-role-select {
        border: 1px solid #e2e8f0; background: #f8fafc; font-size: 0.85rem; color: #475569; font-weight: 600; cursor: pointer; margin-right: 8px; border-radius: 8px; padding: 6px 10px; transition: all 0.2s; outline: none;
    }
    .share-role-select:hover { border-color: #cbd5e1; background: #f1f5f9; }
    .btn-remove-user {
        background: #fef2f2; border: 1px solid #fee2e2; color: #ef4444; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 6px; border-radius: 8px; transition: all 0.2s; outline: none;
    }
    .btn-remove-user:hover { background: #fee2e2; border-color: #fca5a5; }
    
    #share-public-role-container { display: none !important; }
    
    .share-modal-input {
        width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 0.95rem; box-sizing: border-box; outline: none; transition: all 0.2s; color: #0f172a;
    }
    .share-modal-input:focus {
        border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }
    .share-modal-file {
        width: 100%; padding: 10px 16px; border: 1px dashed #cbd5e1; border-radius: 12px; font-size: 0.9rem; box-sizing: border-box; outline: none; cursor: pointer; color: #64748b; background: #f8fafc; transition: all 0.2s;
    }
    .share-modal-file:hover {
        border-color: #10b981; background: #f0fdf4;
    }
    .share-modal-file::file-selector-button {
        background: #e2e8f0; border: none; padding: 6px 12px; border-radius: 6px; color: #334155; font-weight: 600; cursor: pointer; margin-right: 12px; transition: background 0.2s;
    }
    .share-label {
        font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block;
    }
    
    /* TomSelect Custom Modern Styles */
    .ts-control {
        border: 1px solid #cbd5e1 !important; border-radius: 12px !important; padding: 10px 16px !important; font-size: 0.95rem !important; box-shadow: none !important; transition: all 0.2s !important;
    }
    .ts-wrapper.focus .ts-control {
        border-color: #10b981 !important; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15) !important;
    }
    .ts-control input { font-size: 0.95rem !important; }
    
    /* Dark Mode Overrides for internal elements */
    [data-theme="dark"] .share-user-item { border-color: #334155 !important; }
    [data-theme="dark"] .share-user-name { color: #f8fafc !important; }
    [data-theme="dark"] .share-user-email { color: #94a3b8 !important; }
    [data-theme="dark"] .share-role-select { background: #1e293b !important; border-color: #334155 !important; color: #cbd5e1 !important; }
    [data-theme="dark"] .share-role-select:hover { background: #334155 !important; border-color: #475569 !important; }
    [data-theme="dark"] .share-role-select option { background: #1e293b; color: #f8fafc; }
    
    /* Native select Access Type */
    [data-theme="dark"] select#share-wb-access-type { color: #f8fafc !important; }
    [data-theme="dark"] select#share-wb-access-type option { background: #1e293b !important; color: #f8fafc !important; }
    [data-theme="dark"] #share-access-desc { color: #94a3b8 !important; }

    [data-theme="dark"] .btn-remove-user { background: rgba(239, 68, 68, 0.1) !important; border-color: rgba(239, 68, 68, 0.2) !important; color: #ef4444 !important; }
    [data-theme="dark"] .btn-remove-user:hover { background: rgba(239, 68, 68, 0.2) !important; border-color: rgba(239, 68, 68, 0.3) !important; }
    
    [data-theme="dark"] .share-modal-input { background: #0f172a !important; border-color: #475569 !important; color: #f8fafc !important; }
    [data-theme="dark"] .share-modal-file { background: #0f172a !important; border-color: #475569 !important; color: #94a3b8 !important; }
    [data-theme="dark"] .share-modal-file::file-selector-button { background: #334155 !important; color: #f8fafc !important; }
    [data-theme="dark"] .share-label { color: #cbd5e1 !important; }
    
    [data-theme="dark"] .ts-dropdown { background: #1e293b !important; border-color: #334155 !important; color: #f8fafc !important; }
    [data-theme="dark"] .ts-dropdown .option { color: #f8fafc !important; }
    [data-theme="dark"] .ts-dropdown .active { background: #334155 !important; }
    
    /* Panel de Acceso General */
    [data-theme="dark"] .share-general-access-panel { background: #0f172a !important; border-color: #334155 !important; }
</style>

<div class="wb-modal-overlay" id="shareWhiteboardModal">
    <div class="wb-modal" style="max-width: 550px;">
        <div class="wb-modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 id="share-modal-title-header">Compartir Pizarra</h2>
            <button class="wb-modal-close" onclick="closeShareWhiteboardModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="wb-modal-body" style="max-height: 75vh; overflow-y: auto; padding-top: 1rem;">
            <input type="hidden" id="share-wb-id">
            
            <div class="wb-form-group" style="margin-bottom: 1.5rem;" id="share-title-group">
                <label class="share-label">Nombre de la pizarra</label>
                <input type="text" id="share-wb-title" class="share-modal-input" placeholder="Ej. Brainstorming de Marketing">
            </div>

            <div class="wb-form-group" style="margin-bottom: 1.5rem;" id="share-pic-group">
                <label class="share-label">Foto de Portada (Opcional)</label>
                <input type="file" id="share-wb-profile-pic" accept="image/*" class="share-modal-file">
            </div>
            
            <div class="wb-form-group" style="margin-bottom: 1.5rem;">
                <div style="display: flex; gap: 8px; align-items: center; width: 100%;">
                    <select id="share-wb-invite-input" placeholder="Añadir personas (o correos)..." autocomplete="off" style="flex:1; width:100%;">
                        <option value="">Añadir personas (o correos)...</option>
                        <?php foreach($all_users as $u): ?>
                            <option value="USER:<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" onclick="addShareInvite()" style="background:#10b981; border:none; border-radius:12px; color:white; padding:0 20px; height:46px; font-weight:600; display:flex; align-items:center; gap:6px; cursor:pointer; transition:background 0.2s; flex-shrink: 0;"><i class="ph ph-plus" style="font-size:1.1rem;"></i> Añadir</button>
                </div>
            </div>

            <div class="share-modal-list" id="share-users-list">
                <!-- User items appended here via JS -->
            </div>
            <!-- ACCESO GENERAL -->
            <div class="wb-form-group" style="margin-bottom: 0;">
                <label class="share-label">Acceso General</label>
                <div class="share-general-access-panel" style="display: flex; align-items: center; justify-content: space-between; padding: 15px; border: 1px solid #cbd5e1; border-radius: 12px; background: #f8fafc;">
                    <div id="access-icon-bg" style="background: #e2e8f0; border-radius: 50%; width: 40px; height: 40px; display:flex; align-items:center; justify-content:center; transition: all 0.3s;">
                        <i class="ph ph-lock" id="access-icon" style="font-size: 1.5rem; color: #64748b;"></i>
                    </div>
                    <div style="flex: 1; padding: 0 15px;">
                        <select id="share-wb-access-type" class="ts-control" style="border: none !important; background: transparent !important; padding: 0 !important; font-weight: 600; font-size: 1rem; color: #0f172a; margin-bottom: 2px; cursor: pointer; appearance: auto;" onchange="toggleSharePublicRole()">
                            <option value="restricted">Restringido</option>
                            <option value="public">Cualquier persona con el enlace</option>
                        </select>
                        <div id="share-access-desc" style="font-size: 0.8rem; color: #64748b;">Solo los usuarios añadidos pueden abrir este enlace</div>
                    </div>
                    <div id="share-public-role-container" style="display: none;">
                        <select id="share-wb-public-role" class="ts-control" style="border: none !important; background: transparent !important; padding: 0 !important; font-weight: 600; color: #64748b; cursor: pointer; appearance: auto;">
                            <option value="viewer">Lector</option>
                            <option value="editor">Editor</option>
                        </select>
                    </div>
                </div>
            </div>
            
        </div>
        <div class="wb-modal-footer" style="justify-content: space-between; align-items: center;">
            <button class="wb-btn-cancel" onclick="copyShareLink()" style="display: flex; align-items: center; gap: 5px; color: #3b82f6; border-color: #3b82f6;"><i class="ph ph-link"></i> Copiar enlace</button>
            <div style="display: flex; gap: 10px;">
                <button class="wb-btn-cancel" onclick="closeShareWhiteboardModal()">Cancelar</button>
                <button class="wb-btn-save" id="btn-share-save" onclick="submitShareWhiteboard()">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<script>
let shareInviteSelect;
let currentShareUsers = [];
let currentShareMode = 'edit'; // 'create' or 'edit'

document.addEventListener('DOMContentLoaded', () => {
    shareInviteSelect = new TomSelect('#share-wb-invite-input', {
        create: true, // Allow creating new entries (emails)
        createFilter: function(input) {
            // Regex for basic email validation
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input);
        },
        placeholder: 'Añadir personas (o escribir correos)...',
        render: {
            option_create: function(data, escape) {
                return '<div class="create">Invitar correo <strong>' + escape(data.input) + '</strong>&hellip;</div>';
            }
        }
    });
});

// Custom Toast trigger function logic for the modal
function triggerShareToast(msg, icon) {
    if (typeof showToast === 'function') {
        showToast(msg, icon);
    } else {
        let container = document.getElementById('wb-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'wb-toast-container';
            container.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); display: flex; flex-direction: column; gap: 10px; z-index: 9999; pointer-events: none;';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.style.cssText = 'background: #0f172a; color: #fff; padding: 10px 20px; border-radius: 30px; font-size: 0.95rem; font-weight: 500; box-shadow: 0 4px 15px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px; opacity: 0; transform: translateY(-20px); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);';
        toast.innerHTML = `<i class="ph ${icon}" style="font-size: 1.2rem; color: #10b981;"></i> <span>${msg}</span>`;
        container.appendChild(toast);
        toast.offsetHeight;
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-20px)';
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    }
}

function toggleSharePublicRole() {
    const type = document.getElementById('share-wb-access-type').value;
    const roleContainer = document.getElementById('share-public-role-container');
    const desc = document.getElementById('share-access-desc');
    const icon = document.getElementById('access-icon');
    const iconBg = document.getElementById('access-icon-bg');
    
    if (type === 'public') {
        roleContainer.style.display = 'block';
        desc.innerText = 'Cualquier usuario de Internet con el enlace puede verlo';
        icon.className = 'ph ph-globe';
        icon.style.color = '#15803d';
        iconBg.style.background = '#dcfce7';
    } else {
        roleContainer.style.display = 'none';
        desc.innerText = 'Solo los usuarios añadidos pueden abrir este enlace';
        icon.className = 'ph ph-lock';
        icon.style.color = '#64748b';
        iconBg.style.background = '#e2e8f0';
    }
}

function openShareWhiteboardModal(mode = 'create', id = null) {
    currentShareMode = mode;
    currentShareUsers = [];
    
    // Reset Form
    document.getElementById('share-wb-id').value = id || '';
    document.getElementById('share-wb-title').value = '';
    document.getElementById('share-wb-profile-pic').value = '';
    document.getElementById('share-wb-access-type').value = 'restricted';
    document.getElementById('share-wb-public-role').value = 'viewer';
    shareInviteSelect.clear();
    shareInviteSelect.clearOptions();
    
    // Re-add default users options
    <?php foreach($all_users as $u): ?>
        shareInviteSelect.addOption({value: "USER:<?php echo $u['id']; ?>", text: "<?php echo htmlspecialchars($u['name']); ?>"});
    <?php endforeach; ?>
    
    toggleSharePublicRole();
    renderShareUsersList();
    
    const headerTitle = document.getElementById('share-modal-title-header');
    const btnSave = document.getElementById('btn-share-save');
    
    if (mode === 'create') {
        headerTitle.innerText = 'Crear Nueva Pizarra';
        btnSave.innerText = 'Crear Pizarra';
        document.getElementById('shareWhiteboardModal').classList.add('show');
    } else {
        headerTitle.innerText = 'Compartir Pizarra';
        btnSave.innerText = 'Guardar Cambios';
        
        // Fetch existing data
        fetch('ajax/ajax_whiteboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'get_share_info', id: id })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('share-wb-title').value = res.data.title;
                document.getElementById('share-wb-access-type').value = res.data.access_type;
                document.getElementById('share-wb-public-role').value = res.data.public_role;
                currentShareUsers = res.data.users; // Array of {id: 'USER:1' or 'EMAIL:a@b.c', name, email, role}
                toggleSharePublicRole();
                renderShareUsersList();
                document.getElementById('shareWhiteboardModal').classList.add('show');
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        });
    }
}

function closeShareWhiteboardModal() {
    document.getElementById('shareWhiteboardModal').classList.remove('show');
}

function addShareInvite() {
    const val = shareInviteSelect.getValue();
    if (!val) return;
    
    const role = 'editor'; // Por defecto los nuevos son editores, luego se puede cambiar en la lista
    
    // Check if already in list
    if (currentShareUsers.find(u => u.id === val)) {
        Swal.fire('Atención', 'Este usuario ya está en la lista', 'warning');
        return;
    }
    
    let name, email;
    if (val.startsWith('USER:')) {
        const option = shareInviteSelect.options[val];
        name = option ? option.text : 'Usuario';
        email = 'Usuario del sistema';
    } else {
        name = val;
        email = val;
    }
    
    currentShareUsers.push({ id: val, name: name, email: email, role: role });
    shareInviteSelect.clear();
    renderShareUsersList();
}

function removeShareUser(index) {
    currentShareUsers.splice(index, 1);
    renderShareUsersList();
}

function changeShareUserRole(index, newRole) {
    currentShareUsers[index].role = newRole;
}

function renderShareUsersList() {
    const container = document.getElementById('share-users-list');
    container.innerHTML = '';
    
    currentShareUsers.forEach((u, index) => {
        const initial = u.name.charAt(0).toUpperCase();
        const roleSelViewer = u.role === 'viewer' ? 'selected' : '';
        const roleSelEditor = u.role === 'editor' ? 'selected' : '';
        
        let selectHtml = `
            <select class="share-role-select" onchange="changeShareUserRole(${index}, this.value)">
                <option value="viewer" ${roleSelViewer}>Lector</option>
                <option value="editor" ${roleSelEditor}>Editor</option>
            </select>
        `;
        
        if (u.id === 'OWNER') {
            selectHtml = '<span style="color: #64748b; font-size: 0.85rem; font-weight: 500; margin-right:10px;">Propietario</span>';
        }
        
        const removeBtnHtml = u.id !== 'OWNER' ? `<button class="btn-remove-user" onclick="removeShareUser(${index})"><i class="ph ph-x"></i></button>` : '<div style="width:24px;"></div>';

        container.innerHTML += `
            <div class="share-user-item">
                <div class="share-user-info">
                    <div class="share-user-avatar">${initial}</div>
                    <div>
                        <div class="share-user-name">${u.name}</div>
                        <div class="share-user-email">${u.email}</div>
                    </div>
                </div>
                <div style="display:flex; align-items:center;">
                    ${selectHtml}
                    ${removeBtnHtml}
                </div>
            </div>
        `;
    });
}

function copyShareLink() {
    let id = document.getElementById('share-wb-id').value;
    if (!id && currentShareMode === 'create') {
        Swal.fire('Atención', 'Primero debes crear la pizarra para tener un enlace.', 'info');
        return;
    }
    
    const link = window.location.origin + window.location.pathname + '?module=pizarras&action=view&id=' + id;
    navigator.clipboard.writeText(link).then(() => {
        triggerShareToast('Enlace copiado al portapapeles', 'ph-link');
    });
}

function submitShareWhiteboard() {
    const id = document.getElementById('share-wb-id').value;
    const title = document.getElementById('share-wb-title').value.trim();
    if (!title) {
        Swal.fire('Atención', 'Necesitas escribir un nombre', 'warning');
        return;
    }
    
    const access_type = document.getElementById('share-wb-access-type').value;
    const public_role = document.getElementById('share-wb-public-role').value;
    const file = document.getElementById('share-wb-profile-pic').files[0];
    
    const formData = new FormData();
    formData.append('action', currentShareMode === 'create' ? 'create_unified' : 'update_unified');
    if (id) formData.append('id', id);
    formData.append('title', title);
    formData.append('access_type', access_type);
    formData.append('public_role', public_role);
    formData.append('users', JSON.stringify(currentShareUsers));
    
    if (file) formData.append('profile_pic', file);
    
    fetch('ajax/ajax_whiteboard.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            triggerShareToast('Cambios guardados correctamente', 'ph-check-circle');
            if (currentShareMode === 'create') {
                setTimeout(() => {
                    window.location.href = 'index.php?module=pizarras&action=view&id=' + res.id;
                }, 1000);
            } else {
                closeShareWhiteboardModal();
                if (document.getElementById('wb-title')) {
                    document.getElementById('wb-title').innerText = title;
                }
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Error de conexión', 'error');
    });
}
</script>
