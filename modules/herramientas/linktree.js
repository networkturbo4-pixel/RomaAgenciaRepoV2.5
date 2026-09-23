let ltLinks = [];

// Helper para escapar HTML seguro en templates
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Calcular luminancia de color hex para contraste de barra de estado en el mockup
function getHexLuminance(hex) {
    if (!hex) return 0.5;
    hex = hex.replace('#', '').trim();
    if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
    if (hex.length !== 6) return 0.5;
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    return (0.299 * r + 0.587 * g + 0.114 * b) / 255;
}

// Sincronizar indicadores visuales de colores (dots y tags hex)
function syncColorDisplays() {
    const colors = [
        { id: 'lt_bg_color', hexId: 'lt_bg_color_hex', dotId: 'lt_bg_dot' },
        { id: 'lt_text_color', hexId: 'lt_text_color_hex', dotId: 'lt_text_dot' },
        { id: 'lt_btn_color', hexId: 'lt_btn_color_hex', dotId: 'lt_btn_dot' },
        { id: 'lt_btn_text_color', hexId: 'lt_btn_text_color_hex', dotId: 'lt_btn_text_dot' }
    ];

    colors.forEach(c => {
        const input = document.getElementById(c.id);
        const hex = document.getElementById(c.hexId);
        const dot = document.getElementById(c.dotId);
        if (input && hex) hex.innerText = input.value.toUpperCase();
        if (input && dot) dot.style.background = input.value;
    });
}

// Escuchar cambios en los inputs para actualizar la vista previa en vivo
document.addEventListener('DOMContentLoaded', () => {
    const inputs = ['lt_slug', 'lt_title', 'lt_bio', 'lt_bg_color', 'lt_text_color', 'lt_btn_color', 'lt_btn_text_color', 'lt_btn_style', 'lt_font_family', 'lt_hide_watermark'];
    inputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () => {
                syncColorDisplays();
                updateLinktreePreview();
            });
            if (el.tagName === 'SELECT' || el.type === 'checkbox') {
                el.addEventListener('change', () => {
                    syncColorDisplays();
                    updateLinktreePreview();
                });
            }
        }
    });

    const imgInput = document.getElementById('lt_image');
    if (imgInput) {
        imgInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    window.ltPreviewImage = e.target.result;
                    const previewAvatar = document.getElementById('lt_avatar_preview_img');
                    if (previewAvatar) previewAvatar.src = e.target.result;
                    updateLinktreePreview();
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    // Inicializar lista de BioLinks
    linktreeLoadList();
    syncColorDisplays();
});

function linktreeLoadList() {
    fetch('modules/herramientas/ajax_linktree.php?action=list')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const container = document.getElementById('linktreeList');
                if (!container) return;
                container.innerHTML = '';
                
                if (res.data.length === 0) {
                    container.innerHTML = `
                        <div style="grid-column:1/-1; text-align:center; padding:3rem 1.5rem; background:var(--bg-surface); border:1.5px dashed var(--border-color); border-radius:20px;">
                            <div style="width:64px; height:64px; border-radius:18px; background:linear-gradient(135deg,rgba(99,102,241,0.15),rgba(168,85,247,0.1)); color:var(--primary-color); display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; font-size:2rem;">
                                <i class="ph ph-link"></i>
                            </div>
                            <h4 style="margin:0 0 0.5rem; font-weight:700; color:var(--text-main); font-size:1.15rem;">No tienes perfiles BioLink</h4>
                            <p style="margin:0 0 1.5rem; color:var(--text-muted); font-size:0.88rem; max-width:380px; margin-left:auto; margin-right:auto;">Crea tu primer perfil público tipo Linktree personalizado con enlaces, WhatsApp, videos y más.</p>
                            <button class="btn btn-primary" onclick="linktreeNew()" style="border-radius:12px; padding:0.65rem 1.5rem; font-weight:600;"><i class="ph ph-plus"></i> Crear mi primer BioLink</button>
                        </div>
                    `;
                    return;
                }

                res.data.forEach(item => {
                    const activeChecked = item.is_active == 1 ? 'checked' : '';
                    const avatar = item.profile_image ? item.profile_image : 'assets/images/default-avatar.png';
                    const statusColor = item.is_active == 1 ? '#10b981' : '#71717a';
                    const statusText = item.is_active == 1 ? 'Activo' : 'Borrador';
                    const fullUrl = window.location.origin + window.location.pathname.replace('index.php', '') + 'l/' + item.slug;

                    container.innerHTML += `
                        <div class="biolink-profile-card">
                            <div class="biolink-profile-card-accent"></div>
                            
                            <div style="display:flex; align-items:center; gap:1rem;">
                                <div style="position:relative; flex-shrink:0;">
                                    <img src="${avatar}" style="width:56px; height:56px; border-radius:50%; object-fit:cover; border:2px solid var(--border-color); background:var(--bg-surface);">
                                    <span style="position:absolute; bottom:0; right:0; width:14px; height:14px; border-radius:50%; background:${statusColor}; border:2.5px solid var(--bg-card);" title="${statusText}"></span>
                                </div>
                                <div style="flex:1; min-width:0;">
                                    <h4 style="font-weight:700; font-size:1.05rem; margin:0 0 4px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(item.title)}</h4>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <a href="l/${item.slug}" target="_blank" style="font-size:0.84rem; color:var(--primary-color); text-decoration:none; font-weight:600; font-family:'SF Mono', monospace;">/l/${item.slug}</a>
                                        <button type="button" onclick="linktreeCopyText('${fullUrl}')" title="Copiar enlace" style="background:none; border:none; color:var(--text-muted); cursor:pointer; padding:2px; font-size:0.85rem; display:flex; align-items:center;"><i class="ph ph-copy"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.65rem 0.95rem; border-radius:12px; background:rgba(0,0,0,0.03); border:1px solid var(--border-color);">
                                <span style="display:flex; align-items:center; gap:6px; font-size:0.82rem; font-weight:600; color:var(--text-muted);">
                                    <i class="ph ph-eye" style="font-size:1.1rem; color:var(--primary-color);"></i> ${item.views || 0} visitas
                                </span>
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <span style="font-size:0.78rem; font-weight:600; color:var(--text-muted);">${statusText}</span>
                                    <label class="app-switch" style="margin:0; transform:scale(0.8); transform-origin:right center;">
                                        <input type="checkbox" onchange="linktreeToggleActive(${item.id}, this.checked)" ${activeChecked}>
                                        <span class="app-switch-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div style="display:flex; gap:0.6rem; margin-top:auto;">
                                <button type="button" class="btn btn-outline" style="padding:0.6rem 0.85rem; border-radius:10px; display:inline-flex; align-items:center; justify-content:center;" onclick="linktreeDownloadQR('${item.slug}', '${escapeHtml(item.title)}')" title="Descargar código QR"><i class="ph ph-qr-code" style="font-size:1.15rem;"></i></button>
                                <button type="button" class="btn btn-outline" style="flex:1; padding:0.6rem; border-radius:10px; font-weight:600; display:inline-flex; align-items:center; justify-content:center; gap:6px;" onclick="linktreeEdit(${item.id})"><i class="ph ph-pencil-simple" style="font-size:1.1rem;"></i> Editar</button>
                                <button type="button" class="btn btn-outline" style="padding:0.6rem 0.85rem; border-radius:10px; color:#ef4444; border-color:rgba(239,68,68,0.25); background:rgba(239,68,68,0.06);" onclick="linktreeDelete(${item.id})" title="Eliminar BioLink"><i class="ph ph-trash" style="font-size:1.15rem;"></i></button>
                            </div>
                        </div>
                    `;
                });
            }
        });
}

function linktreeCopyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            if (window.showToast) window.showToast('Enlace copiado al portapapeles', 'success');
        });
    } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        if (window.showToast) window.showToast('Enlace copiado al portapapeles', 'success');
    }
}

function linktreeCopyCurrentLink() {
    const slug = document.getElementById('lt_slug')?.value || 'mi-marca';
    const fullUrl = window.location.origin + window.location.pathname.replace('index.php', '') + 'l/' + slug;
    linktreeCopyText(fullUrl);
}

function linktreeDownloadQR(slug, title) {
    const fullUrl = window.location.origin + window.location.pathname.replace('index.php', '') + 'l/' + slug;
    const qrApi = `https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${encodeURIComponent(fullUrl)}`;
    
    if (window.showToast) window.showToast('Generando código QR...', 'info');
    
    fetch(qrApi)
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = `QR_BioLink_${title.replace(/\s+/g, '_')}.png`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            if (window.showToast) window.showToast('QR descargado con éxito', 'success');
        })
        .catch(() => {
            if (window.showToast) window.showToast('Error al generar QR', 'error');
        });
}

function linktreeToggleActive(id, isActive) {
    const fd = new FormData();
    fd.append('action', 'toggle_active');
    fd.append('id', id);
    fd.append('is_active', isActive ? 1 : 0);
    fetch('modules/herramientas/ajax_linktree.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                if (window.showToast) window.showToast(res.error, 'error');
            } else {
                linktreeLoadList();
            }
        });
}

function linktreeDelete(id) {
    if (!confirm('¿Estás seguro de eliminar este BioLink? Esta acción no se puede deshacer.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('modules/herramientas/ajax_linktree.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.showToast) window.showToast('BioLink eliminado correctamente', 'success');
                linktreeLoadList();
            } else {
                if (window.showToast) window.showToast(res.error, 'error');
            }
        });
}

function linktreeNew() {
    document.getElementById('lt_id').value = '';
    document.getElementById('lt_slug').value = '';
    document.getElementById('lt_title').value = '';
    document.getElementById('lt_bio').value = '';
    document.getElementById('lt_theme_preset').value = 'custom';
    document.getElementById('lt_font_family').value = 'Inter';
    document.getElementById('lt_hide_watermark').checked = false;
    document.getElementById('lt_bg_color').value = '#f4f4f5';
    document.getElementById('lt_text_color').value = '#18181b';
    document.getElementById('lt_btn_color').value = '#ffffff';
    document.getElementById('lt_btn_text_color').value = '#18181b';
    document.getElementById('lt_btn_style').value = 'rounded-md';
    document.getElementById('lt_image').value = '';
    
    window.ltPreviewImage = null;
    const avatarPreview = document.getElementById('lt_avatar_preview_img');
    if (avatarPreview) avatarPreview.src = 'assets/images/default-avatar.png';
    
    ltLinks = [];
    syncColorDisplays();
    renderLinksEditor();
    updateLinktreePreview();
    
    document.getElementById('linktreeListSection').style.display = 'none';
    document.getElementById('linktreeEditorSection').style.display = 'block';
}

function linktreeEdit(id) {
    fetch(`modules/herramientas/ajax_linktree.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const data = res.data;
                document.getElementById('lt_id').value = data.id;
                document.getElementById('lt_slug').value = data.slug;
                document.getElementById('lt_title').value = data.title;
                document.getElementById('lt_bio').value = data.bio || '';
                
                const theme = data.theme_config || {};
                document.getElementById('lt_theme_preset').value = theme.preset || 'custom';
                document.getElementById('lt_font_family').value = theme.fontFamily || 'Inter';
                document.getElementById('lt_hide_watermark').checked = theme.hideWatermark || false;
                document.getElementById('lt_bg_color').value = theme.bgColor || '#f4f4f5';
                document.getElementById('lt_text_color').value = theme.textColor || '#18181b';
                document.getElementById('lt_btn_color').value = theme.btnColor || '#ffffff';
                document.getElementById('lt_btn_text_color').value = theme.btnTextColor || '#18181b';
                document.getElementById('lt_btn_style').value = theme.btnStyle || 'rounded-md';
                
                window.ltPreviewImage = data.profile_image ? data.profile_image : null;
                const avatarPreview = document.getElementById('lt_avatar_preview_img');
                if (avatarPreview) {
                    avatarPreview.src = data.profile_image ? data.profile_image : 'assets/images/default-avatar.png';
                }
                
                ltLinks = data.links || [];
                syncColorDisplays();
                renderLinksEditor();
                updateLinktreePreview();
                
                document.getElementById('linktreeListSection').style.display = 'none';
                document.getElementById('linktreeEditorSection').style.display = 'block';
            }
        });
}

function linktreeCancel() {
    document.getElementById('linktreeEditorSection').style.display = 'none';
    document.getElementById('linktreeListSection').style.display = 'block';
}

function linktreeAddLink(type = 'link') {
    let newBlock = { title: '', url: '', type: type, meta_data: {} };
    
    switch(type) {
        case 'link':
            newBlock.title = 'Nuevo Enlace';
            newBlock.url = 'https://';
            break;
        case 'youtube':
            newBlock.title = 'Video de YouTube';
            newBlock.meta_data.videoId = '';
            break;
        case 'spotify':
            newBlock.title = 'Canción de Spotify';
            newBlock.meta_data.spotifyUrl = '';
            break;
        case 'text':
            newBlock.title = 'Título';
            newBlock.meta_data.text = 'Texto descriptivo';
            break;
        case 'faq':
            newBlock.title = 'Pregunta Frecuente';
            newBlock.meta_data.answer = 'Respuesta...';
            break;
        case 'whatsapp':
            newBlock.title = 'WhatsApp';
            newBlock.meta_data.phone = '';
            newBlock.meta_data.message = '';
            break;
        case 'map':
            newBlock.title = 'Nuestra Ubicación';
            newBlock.meta_data.address = '';
            break;
    }
    
    ltLinks.push(newBlock);
    renderLinksEditor();
    updateLinktreePreview();
}

function linktreeRemoveLink(index) {
    ltLinks.splice(index, 1);
    renderLinksEditor();
    updateLinktreePreview();
}

function linktreeUpdateLink(index, field, value) {
    if (ltLinks[index]) {
        ltLinks[index][field] = value;
        updateLinktreePreview();
    }
}

function linktreeUpdateLinkMetaData(index, field, value) {
    if (ltLinks[index]) {
        if (!ltLinks[index].meta_data) ltLinks[index].meta_data = {};
        ltLinks[index].meta_data[field] = value;
        updateLinktreePreview();
    }
}

function renderLinksEditor() {
    const container = document.getElementById('lt_links_container');
    if (!container) return;
    container.innerHTML = '';

    ltLinks.forEach((link, index) => {
        let typeIcon = 'ph-link';
        let typeLabel = 'Enlace';
        let pillClass = 'chip-btn-link';

        if (link.type === 'youtube') {
            typeIcon = 'ph-youtube-logo';
            typeLabel = 'YouTube';
            pillClass = 'chip-btn-youtube';
        } else if (link.type === 'spotify') {
            typeIcon = 'ph-spotify-logo';
            typeLabel = 'Spotify';
            pillClass = 'chip-btn-spotify';
        } else if (link.type === 'text') {
            typeIcon = 'ph-text-t';
            typeLabel = 'Título';
            pillClass = 'chip-btn-text';
        } else if (link.type === 'faq') {
            typeIcon = 'ph-question';
            typeLabel = 'FAQ';
            pillClass = 'chip-btn-faq';
        } else if (link.type === 'whatsapp') {
            typeIcon = 'ph-whatsapp-logo';
            typeLabel = 'WhatsApp';
            pillClass = 'chip-btn-whatsapp';
        } else if (link.type === 'map') {
            typeIcon = 'ph-map-pin';
            typeLabel = 'Mapa';
            pillClass = 'chip-btn-map';
        }
        
        let innerHtml = '';
        
        if (link.type === 'link' || !link.type) {
            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Texto del Enlace</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Ej: Visita nuestro sitio web" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0;">
                    <label class="biolink-label">URL Destino</label>
                    <input type="url" value="${escapeHtml(link.url)}" class="biolink-input" placeholder="https://ejemplo.com" oninput="linktreeUpdateLink(${index}, 'url', this.value)">
                </div>
            `;
        } else if (link.type === 'youtube') {
            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Título del Video (Opcional)</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Ej: Nuevo video de presentación" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0;">
                    <label class="biolink-label">ID de YouTube o Enlace</label>
                    <input type="text" value="${escapeHtml(link.meta_data?.videoId || '')}" class="biolink-input" placeholder="Ej: dQw4w9WgXcQ o enlace completo" oninput="linktreeUpdateLinkMetaData(${index}, 'videoId', this.value)">
                </div>
            `;
        } else if (link.type === 'spotify') {
            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Título de la Canción o Playlist</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Ej: Escucha nuestro podcast" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0;">
                    <label class="biolink-label">URL o URI de Spotify</label>
                    <input type="url" value="${escapeHtml(link.meta_data?.spotifyUrl || '')}" class="biolink-input" placeholder="https://open.spotify.com/track/..." oninput="linktreeUpdateLinkMetaData(${index}, 'spotifyUrl', this.value)">
                </div>
            `;
        } else if (link.type === 'text') {
            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Encabezado</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Título o sección" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0;">
                    <label class="biolink-label">Texto Descriptivo</label>
                    <textarea class="biolink-textarea" rows="2" placeholder="Información adicional, avisos o comunicados..." oninput="linktreeUpdateLinkMetaData(${index}, 'text', this.value)">${escapeHtml(link.meta_data?.text || '')}</textarea>
                </div>
            `;
        } else if (link.type === 'faq') {
            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Pregunta</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Ej: ¿Cuáles son las formas de pago?" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0;">
                    <label class="biolink-label">Respuesta</label>
                    <textarea class="biolink-textarea" rows="2" placeholder="Detalla la respuesta clara para tus clientes..." oninput="linktreeUpdateLinkMetaData(${index}, 'answer', this.value)">${escapeHtml(link.meta_data?.answer || '')}</textarea>
                </div>
            `;
        } else if (link.type === 'whatsapp') {
            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Texto del Botón</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Ej: Chatear por WhatsApp" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Número con código de país</label>
                    <input type="text" value="${escapeHtml(link.meta_data?.phone || '')}" class="biolink-input" placeholder="Ej: 51987654321" oninput="linktreeUpdateLinkMetaData(${index}, 'phone', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0;">
                    <label class="biolink-label">Mensaje predeterminado (Opcional)</label>
                    <input type="text" value="${escapeHtml(link.meta_data?.message || '')}" class="biolink-input" placeholder="Hola, me gustaría más información..." oninput="linktreeUpdateLinkMetaData(${index}, 'message', this.value)">
                </div>
            `;
        } else if (link.type === 'map') {
            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Título de la Ubicación</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Ej: Nuestra Sede Principal" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0;">
                    <label class="biolink-label">Dirección o Enlace a Maps</label>
                    <input type="text" value="${escapeHtml(link.meta_data?.address || '')}" class="biolink-input" placeholder="Ej: Av. Larco 123, Miraflores, Lima" oninput="linktreeUpdateLinkMetaData(${index}, 'address', this.value)">
                </div>
            `;
        }
        
        // Advanced scheduling section
        const advancedHtml = `
            <details style="margin-top:0.35rem; padding-top:0.6rem; border-top:1px dashed var(--border-color);">
                <summary style="font-size:0.8rem; color:var(--text-muted); cursor:pointer; font-weight:600; outline:none; display:flex; align-items:center; gap:6px;">
                    <i class="ph ph-calendar"></i> Programación de visibilidad (Opcional)
                </summary>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-top:0.6rem;">
                    <div class="biolink-field" style="margin-bottom:0;">
                        <label class="biolink-label" style="font-size:0.75rem;">Mostrar desde:</label>
                        <input type="datetime-local" class="biolink-input" style="padding:0.45rem 0.65rem; font-size:0.82rem;" value="${link.meta_data?.start_date || ''}" oninput="linktreeUpdateLinkMetaData(${index}, 'start_date', this.value)">
                    </div>
                    <div class="biolink-field" style="margin-bottom:0;">
                        <label class="biolink-label" style="font-size:0.75rem;">Ocultar desde:</label>
                        <input type="datetime-local" class="biolink-input" style="padding:0.45rem 0.65rem; font-size:0.82rem;" value="${link.meta_data?.end_date || ''}" oninput="linktreeUpdateLinkMetaData(${index}, 'end_date', this.value)">
                    </div>
                </div>
            </details>
        `;

        container.innerHTML += `
            <div class="biolink-block-item" data-index="${index}">
                <div class="biolink-block-handle" title="Arrastra para reordenar">
                    <i class="ph ph-dots-six-vertical" style="font-size:1.3rem;"></i>
                </div>
                <div class="biolink-block-main">
                    <details class="lt-block-details" open>
                        <summary class="biolink-block-summary">
                            <div class="biolink-block-summary-left">
                                <span class="biolink-block-pill ${pillClass}">${typeLabel}</span>
                                <span class="biolink-block-summary-title">${escapeHtml(link.title || 'Bloque sin título')}</span>
                            </div>
                            <i class="ph ph-caret-down biolink-block-chevron"></i>
                        </summary>
                        <div class="biolink-block-body">
                            ${innerHtml}
                            ${advancedHtml}
                        </div>
                    </details>
                </div>
                <button type="button" class="biolink-block-delete-btn" onclick="linktreeRemoveLink(${index})" title="Eliminar bloque">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
        `;
    });
    
    if (window.Sortable) {
        new Sortable(container, {
            animation: 150,
            handle: '.biolink-block-handle',
            onEnd: function (evt) {
                const item = ltLinks.splice(evt.oldIndex, 1)[0];
                ltLinks.splice(evt.newIndex, 0, item);
                renderLinksEditor();
                updateLinktreePreview();
            }
        });
    }
}

function updateLinktreePreview() {
    const slug = document.getElementById('lt_slug')?.value || 'mi-marca';
    const title = document.getElementById('lt_title')?.value || 'Nombre de la Marca';
    const bio = document.getElementById('lt_bio')?.value || '';
    const font = document.getElementById('lt_font_family')?.value || 'Inter';
    const bgColor = document.getElementById('lt_bg_color')?.value || '#f4f4f5';
    const textColor = document.getElementById('lt_text_color')?.value || '#18181b';
    const btnColor = document.getElementById('lt_btn_color')?.value || '#ffffff';
    const btnTextColor = document.getElementById('lt_btn_text_color')?.value || '#18181b';
    const btnStyle = document.getElementById('lt_btn_style')?.value || 'rounded-md';
    const hideWatermark = document.getElementById('lt_hide_watermark')?.checked || false;
    
    const previewBox = document.getElementById('lt_preview_box');
    const previewContent = document.getElementById('lt_preview_content');
    const statusBar = document.getElementById('lt_phone_status_bar');
    const homeIndicator = document.getElementById('lt_home_indicator');
    const openBtn = document.getElementById('btnOpenBioLink');

    if (openBtn) {
        openBtn.href = 'l/' + encodeURIComponent(slug);
    }

    if (!previewBox || !previewContent) return;

    // Ajustar color del fondo y texto del mockup
    previewBox.style.background = bgColor;
    previewBox.style.color = textColor;
    previewBox.style.fontFamily = `"${font}", sans-serif`;

    // Adaptar contraste de la barra de estado móvil según la luminancia del fondo
    const lum = getHexLuminance(bgColor);
    const contrastPhoneColor = lum > 0.5 ? '#18181b' : '#ffffff';
    if (statusBar) statusBar.style.color = contrastPhoneColor;
    if (homeIndicator) homeIndicator.style.color = contrastPhoneColor;

    const imgSrc = window.ltPreviewImage || (document.getElementById('lt_avatar_preview_img')?.src) || 'assets/images/default-avatar.png';

    let radius = '12px';
    if (btnStyle === 'rounded-full') radius = '9999px';
    if (btnStyle === 'rounded-none') radius = '0px';

    let html = `
        <div style="margin-bottom:0.75rem; position:relative;">
            <img src="${imgSrc}" style="width:78px; height:78px; border-radius:50%; object-fit:cover; border:3px solid ${textColor}25; box-shadow:0 8px 16px rgba(0,0,0,0.12); background:rgba(0,0,0,0.05);">
        </div>
        <h3 style="font-weight:700; font-size:1.1rem; margin:0 0 0.2rem; letter-spacing:-0.01em;">${escapeHtml(title)}</h3>
        <p style="font-size:0.8rem; font-weight:600; opacity:0.75; margin:0 0 0.65rem; font-family:'SF Mono',monospace;">@${escapeHtml(slug)}</p>
        ${bio ? `<p style="font-size:0.82rem; opacity:0.88; margin:0 0 1.5rem; white-space:pre-wrap; line-height:1.45; text-align:center; max-width:280px;">${escapeHtml(bio)}</p>` : '<div style="margin-bottom:1rem;"></div>'}
        <div style="width:100%; display:flex; flex-direction:column; gap:0.75rem; padding-bottom:1rem;">
    `;

    ltLinks.forEach(link => {
        if (link.type === 'link' || !link.type) {
            html += `
                <div style="width:100%; padding:0.8rem 1rem; background:${btnColor}; color:${btnTextColor}; border-radius:${radius}; font-weight:600; font-size:0.88rem; text-align:center; box-shadow:0 3px 10px rgba(0,0,0,0.08); transition:transform 0.15s ease; border:1px solid ${btnTextColor}15;">
                    ${escapeHtml(link.title || 'Enlace')}
                </div>
            `;
        } else if (link.type === 'youtube') {
            html += `
                <div style="width:100%; border-radius:${radius}; overflow:hidden; background:#000000; aspect-ratio:16/9; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; box-shadow:0 4px 12px rgba(0,0,0,0.2);">
                    <i class="ph ph-youtube-logo" style="font-size:2.4rem; color:#ef4444;"></i>
                    <span style="font-size:0.78rem; font-weight:600; color:#ffffff; padding:0 0.5rem; text-align:center;">${escapeHtml(link.title || 'Video de YouTube')}</span>
                </div>
            `;
        } else if (link.type === 'spotify') {
            html += `
                <div style="width:100%; border-radius:${radius}; overflow:hidden; background:#10b981; padding:0.75rem 1rem; display:flex; align-items:center; gap:0.75rem; color:#ffffff; box-shadow:0 4px 14px rgba(16,185,129,0.3);">
                    <i class="ph ph-spotify-logo" style="font-size:1.6rem;"></i>
                    <span style="font-size:0.86rem; font-weight:600; flex:1; text-align:left;">${escapeHtml(link.title || 'Escuchar en Spotify')}</span>
                </div>
            `;
        } else if (link.type === 'text') {
            html += `
                <div style="width:100%; margin-top:0.35rem; text-align:center;">
                    <h5 style="font-weight:700; font-size:0.95rem; margin:0 0 0.25rem;">${escapeHtml(link.title || 'Título')}</h5>
                    <p style="font-size:0.8rem; opacity:0.85; margin:0; line-height:1.4;">${escapeHtml(link.meta_data?.text || '')}</p>
                </div>
            `;
        } else if (link.type === 'faq') {
            html += `
                <div style="width:100%; padding:0.75rem 0.95rem; background:${btnColor}; color:${btnTextColor}; border-radius:${radius}; text-align:left; box-shadow:0 2px 8px rgba(0,0,0,0.08); border-left:4px solid #6366f1;">
                    <div style="font-weight:700; font-size:0.84rem; display:flex; justify-content:space-between; align-items:center;">
                        <span>${escapeHtml(link.title || 'Pregunta Frecuente')}</span>
                        <i class="ph ph-caret-down" style="font-size:0.9rem; opacity:0.7;"></i>
                    </div>
                    ${link.meta_data?.answer ? `<div style="font-size:0.76rem; opacity:0.8; margin-top:4px; line-height:1.35;">${escapeHtml(link.meta_data.answer)}</div>` : ''}
                </div>
            `;
        } else if (link.type === 'whatsapp') {
            html += `
                <div style="width:100%; padding:0.8rem 1rem; background:#22c55e; color:#ffffff; border-radius:${radius}; font-weight:600; font-size:0.88rem; text-align:center; box-shadow:0 4px 14px rgba(34,197,94,0.3); display:flex; align-items:center; justify-content:center; gap:0.6rem;">
                    <i class="ph ph-whatsapp-logo" style="font-size:1.35rem;"></i>
                    <span>${escapeHtml(link.title || 'WhatsApp')}</span>
                </div>
            `;
        } else if (link.type === 'map') {
            html += `
                <div style="width:100%; border-radius:${radius}; overflow:hidden; background:rgba(0,0,0,0.06); border:1px solid ${textColor}20; padding:0.75rem 1rem; display:flex; align-items:center; gap:0.75rem; text-align:left;">
                    <i class="ph ph-map-pin" style="font-size:1.5rem; color:#ef4444; flex-shrink:0;"></i>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:0.84rem; font-weight:700;">${escapeHtml(link.title || 'Ubicación')}</div>
                        ${link.meta_data?.address ? `<div style="font-size:0.74rem; opacity:0.8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(link.meta_data.address)}</div>` : ''}
                    </div>
                </div>
            `;
        }
    });

    html += '</div>';

    // Watermark
    if (!hideWatermark) {
        html += `
            <div style="margin-top:auto; padding-top:1.5rem; text-align:center; font-size:0.72rem; opacity:0.6; display:flex; align-items:center; justify-content:center; gap:4px;">
                <i class="ph ph-sparkle"></i> Creado con <strong>Roma Agencia</strong>
            </div>
        `;
    }

    previewContent.innerHTML = html;
}

function linktreeSave() {
    const slug = document.getElementById('lt_slug')?.value.trim();
    const title = document.getElementById('lt_title')?.value.trim();
    const saveBtn = document.getElementById('btnSaveBioLink');
    
    if (!slug || !title) {
        if (window.showToast) window.showToast('El Slug y el Título son requeridos', 'error');
        return;
    }

    const origSaveText = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
    }
    
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id', document.getElementById('lt_id').value);
    fd.append('slug', slug);
    fd.append('title', title);
    fd.append('bio', document.getElementById('lt_bio').value);
    
    const theme = {
        preset: document.getElementById('lt_theme_preset').value,
        fontFamily: document.getElementById('lt_font_family').value,
        hideWatermark: document.getElementById('lt_hide_watermark').checked,
        bgColor: document.getElementById('lt_bg_color').value,
        textColor: document.getElementById('lt_text_color').value,
        btnColor: document.getElementById('lt_btn_color').value,
        btnTextColor: document.getElementById('lt_btn_text_color').value,
        btnStyle: document.getElementById('lt_btn_style').value
    };
    fd.append('theme_config', JSON.stringify(theme));
    fd.append('links', JSON.stringify(ltLinks));
    
    const fileInput = document.getElementById('lt_image');
    if (fileInput && fileInput.files.length > 0) {
        fd.append('profile_image', fileInput.files[0]);
    }
    
    fetch('modules/herramientas/ajax_linktree.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = origSaveText;
            }
            if (res.success) {
                if (window.showToast) window.showToast('BioLink guardado con éxito', 'success');
                linktreeCancel();
                linktreeLoadList();
            } else {
                if (window.showToast) window.showToast(res.error || 'Error al guardar BioLink', 'error');
            }
        })
        .catch(() => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = origSaveText;
            }
            if (window.showToast) window.showToast('Error de conexión al guardar', 'error');
        });
}

function linktreeApplyTheme(preset) {
    if (preset === 'custom') return;
    
    let bg, text, btn, btnText, btnStyle, font;
    
    switch(preset) {
        case 'cyberpunk':
            bg = '#0f172a'; text = '#f8fafc'; btn = '#1e293b'; btnText = '#22d3ee'; btnStyle = 'rounded-none'; font = 'Space Grotesk';
            break;
        case 'minimal':
            bg = '#ffffff'; text = '#18181b'; btn = '#f4f4f5'; btnText = '#18181b'; btnStyle = 'rounded-md'; font = 'Inter';
            break;
        case 'pastel':
            bg = '#fdf4ff'; text = '#701a75'; btn = '#f0abfc'; btnText = '#4a044e'; btnStyle = 'rounded-full'; font = 'Playfair Display';
            break;
        case 'corporate':
            bg = '#f8fafc'; text = '#0f172a'; btn = '#0284c7'; btnText = '#ffffff'; btnStyle = 'rounded-md'; font = 'Roboto';
            break;
    }
    
    if (font) document.getElementById('lt_font_family').value = font;
    if (bg) document.getElementById('lt_bg_color').value = bg;
    if (text) document.getElementById('lt_text_color').value = text;
    if (btn) document.getElementById('lt_btn_color').value = btn;
    if (btnText) document.getElementById('lt_btn_text_color').value = btnText;
    if (btnStyle) document.getElementById('lt_btn_style').value = btnStyle;
    
    syncColorDisplays();
    updateLinktreePreview();
}
