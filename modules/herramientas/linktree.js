let ltLinks = [];

// Escuchar cambios en los inputs para actualizar la vista previa en vivo
document.addEventListener('DOMContentLoaded', () => {
    const inputs = ['lt_slug', 'lt_title', 'lt_bio', 'lt_bg_color', 'lt_text_color', 'lt_btn_color', 'lt_btn_text_color', 'lt_btn_style', 'lt_font_family'];
    inputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateLinktreePreview);
        if (el && el.tagName === 'SELECT') el.addEventListener('change', updateLinktreePreview);
    });

    const imgInput = document.getElementById('lt_image');
    if (imgInput) {
        imgInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    window.ltPreviewImage = e.target.result;
                    updateLinktreePreview();
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    // Inicializar lista
    linktreeLoadList();
});

function linktreeLoadList() {
    fetch('modules/herramientas/ajax_linktree.php?action=list')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const container = document.getElementById('linktreeList');
                container.innerHTML = '';
                if(res.data.length === 0) {
                    container.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:2rem;opacity:0.6;">No hay perfiles BioLink creados.</div>';
                    return;
                }
                res.data.forEach(item => {
                    const activeChecked = item.is_active == 1 ? 'checked' : '';
                    container.innerHTML += `
                        <div style="display:flex;flex-direction:column;gap:1.25rem; background:var(--bg-card); padding:1.5rem; border-radius:16px; border:1px solid var(--border-color); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); transition: all 0.2s ease; position:relative; overflow:hidden;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 20px -5px rgba(0, 0, 0, 0.08)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03)'">
                            <!-- Decoración superior -->
                            <div style="position:absolute; top:0; left:0; width:100%; height:4px; background:linear-gradient(90deg, var(--primary-color), #34d399);"></div>
                            
                            <div style="display:flex;align-items:center;gap:1.25rem;">
                                <div style="position:relative;">
                                    <img src="${item.profile_image ? item.profile_image : 'assets/images/default-avatar.png'}" style="width:56px;height:56px;border-radius:50%;object-fit:cover; border:2px solid var(--border-color); padding:2px; background:var(--bg-body);">
                                    <div style="position:absolute; bottom:0; right:0; width:14px; height:14px; background:${item.is_active == 1 ? '#10b981' : '#9ca3af'}; border-radius:50%; border:2px solid var(--bg-card);"></div>
                                </div>
                                <div style="flex:1;">
                                    <h4 style="font-weight:700;margin:0;font-size:1.1rem;color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.title}</h4>
                                    <a href="l/${item.slug}" target="_blank" style="font-size:0.85rem;color:var(--primary-color);text-decoration:none;display:inline-block;margin-top:2px;font-weight:500;">/l/${item.slug}</a>
                                </div>
                            </div>

                            <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.85rem;color:var(--text-muted); background:var(--bg-body); padding:0.75rem 1rem; border-radius:8px;">
                                <span style="display:flex;align-items:center;gap:4px;font-weight:500;"><i class="ph ph-eye" style="font-size:1.1rem;color:var(--primary-color);"></i> ${item.views} Vistas</span>
                                <div style="display:flex;align-items:center;gap:0.5rem">
                                    <span style="font-weight:500;">Público</span>
                                    <label class="app-switch" style="transform: scale(0.8);margin:0; transform-origin:right;">
                                        <input type="checkbox" onchange="linktreeToggleActive(${item.id}, this.checked)" ${activeChecked}>
                                        <span class="app-switch-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div style="display:flex;gap:0.75rem;margin-top:auto;">
                                <button class="btn btn-outline" style="padding:0.6rem 0.8rem; border-radius:8px; display:flex; justify-content:center; align-items:center;" onclick="linktreeDownloadQR('${item.slug}', '${item.title}')" title="Descargar QR"><i class="ph ph-qr-code" style="font-size:1.1rem;"></i></button>
                                <button class="btn btn-outline" style="flex:1;padding:0.6rem; border-radius:8px; font-weight:600; display:flex; justify-content:center; gap:6px; align-items:center;" onclick="linktreeEdit(${item.id})"><i class="ph ph-pencil-simple" style="font-size:1.1rem;"></i> Editar</button>
                                <button class="btn btn-outline" style="padding:0.6rem 0.8rem;color:#ef4444;border-color:transparent;background:#fef2f2;border-radius:8px;" onclick="linktreeDelete(${item.id})" title="Eliminar"><i class="ph ph-trash" style="font-size:1.1rem;"></i></button>
                            </div>
                        </div>
                    `;
                });
            }
        });
}

function linktreeDownloadQR(slug, title) {
    const fullUrl = window.location.origin + window.location.pathname.replace('index.php', '') + 'l/' + slug;
    const qrApi = `https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${encodeURIComponent(fullUrl)}`;
    
    if(window.showToast) window.showToast('Generando QR...', 'info');
    
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
            if(window.showToast) window.showToast('QR Descargado con éxito', 'success');
        })
        .catch(() => {
            if(window.showToast) window.showToast('Error al generar QR', 'error');
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
            if(!res.success) {
                if(window.showToast) window.showToast(res.error, 'error');
            }
        });
}

function linktreeDelete(id) {
    if(!confirm('¿Estás seguro de eliminar este perfil? Esta acción no se puede deshacer.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('modules/herramientas/ajax_linktree.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                if(window.showToast) window.showToast('Perfil eliminado', 'success');
                linktreeLoadList();
            } else {
                if(window.showToast) window.showToast(res.error, 'error');
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
    ltLinks = [];
    renderLinksEditor();
    updateLinktreePreview();
    
    document.getElementById('linktreeListSection').style.display = 'none';
    document.getElementById('linktreeEditorSection').style.display = 'block';
}

function linktreeEdit(id) {
    fetch(`modules/herramientas/ajax_linktree.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                const data = res.data;
                document.getElementById('lt_id').value = data.id;
                document.getElementById('lt_slug').value = data.slug;
                document.getElementById('lt_title').value = data.title;
                document.getElementById('lt_bio').value = data.bio;
                
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
                
                ltLinks = data.links || [];
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
    ltLinks[index][field] = value;
    updateLinktreePreview();
}

function linktreeUpdateLinkMetaData(index, field, value) {
    if (!ltLinks[index].meta_data) ltLinks[index].meta_data = {};
    ltLinks[index].meta_data[field] = value;
    updateLinktreePreview();
}

function renderLinksEditor() {
    const container = document.getElementById('lt_links_container');
    container.innerHTML = '';
    ltLinks.forEach((link, index) => {
        let typeIcon = 'ph-link';
        if (link.type === 'youtube') typeIcon = 'ph-youtube-logo';
        if (link.type === 'spotify') typeIcon = 'ph-spotify-logo';
        if (link.type === 'text') typeIcon = 'ph-text-t';
        if (link.type === 'faq') typeIcon = 'ph-question';
        if (link.type === 'whatsapp') typeIcon = 'ph-whatsapp-logo';
        if (link.type === 'map') typeIcon = 'ph-map-pin';
        
        let innerHtml = '';
        
        if (link.type === 'link' || !link.type) {
            innerHtml = `
                <input type="text" value="${link.title}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.9rem;border-radius:8px;font-weight:500;" placeholder="Título del enlace" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                <input type="url" value="${link.url}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.85rem;border-radius:8px;color:var(--text-muted);" placeholder="https://" oninput="linktreeUpdateLink(${index}, 'url', this.value)">
            `;
        } else if (link.type === 'youtube') {
            innerHtml = `
                <input type="text" value="${link.title}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.9rem;border-radius:8px;font-weight:500;" placeholder="Título (Opcional)" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                <input type="text" value="${link.meta_data?.videoId || ''}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.85rem;border-radius:8px;" placeholder="ID de YouTube (Ej: dQw4w9WgXcQ)" oninput="linktreeUpdateLinkMetaData(${index}, 'videoId', this.value)">
            `;
        } else if (link.type === 'spotify') {
            innerHtml = `
                <input type="text" value="${link.title}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.9rem;border-radius:8px;font-weight:500;" placeholder="Título (Opcional)" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                <input type="url" value="${link.meta_data?.spotifyUrl || ''}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.85rem;border-radius:8px;" placeholder="URL de la canción o playlist" oninput="linktreeUpdateLinkMetaData(${index}, 'spotifyUrl', this.value)">
            `;
        } else if (link.type === 'text') {
            innerHtml = `
                <input type="text" value="${link.title}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.9rem;border-radius:8px;font-weight:700;" placeholder="Encabezado" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                <textarea class="qr-field-textarea" style="padding:0.5rem 0.75rem;font-size:0.85rem;border-radius:8px;min-height:60px;" placeholder="Texto descriptivo" oninput="linktreeUpdateLinkMetaData(${index}, 'text', this.value)">${link.meta_data?.text || ''}</textarea>
            `;
        } else if (link.type === 'faq') {
            innerHtml = `
                <input type="text" value="${link.title}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.9rem;border-radius:8px;font-weight:500;" placeholder="Pregunta" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                <textarea class="qr-field-textarea" style="padding:0.5rem 0.75rem;font-size:0.85rem;border-radius:8px;min-height:60px;" placeholder="Respuesta" oninput="linktreeUpdateLinkMetaData(${index}, 'answer', this.value)">${link.meta_data?.answer || ''}</textarea>
            `;
        } else if (link.type === 'whatsapp') {
            innerHtml = `
                <input type="text" value="${link.title}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.9rem;border-radius:8px;font-weight:500;" placeholder="Título del enlace" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                <input type="text" value="${link.meta_data?.phone || ''}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.85rem;border-radius:8px;color:var(--text-muted);" placeholder="Número con código de país (Ej: 51987654321)" oninput="linktreeUpdateLinkMetaData(${index}, 'phone', this.value)">
                <input type="text" value="${link.meta_data?.message || ''}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.85rem;border-radius:8px;color:var(--text-muted);" placeholder="Mensaje predeterminado (Opcional)" oninput="linktreeUpdateLinkMetaData(${index}, 'message', this.value)">
            `;
        } else if (link.type === 'map') {
            innerHtml = `
                <input type="text" value="${link.title}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.9rem;border-radius:8px;font-weight:500;" placeholder="Título del mapa" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                <input type="text" value="${link.meta_data?.address || ''}" class="qr-field-input" style="padding:0.5rem 0.75rem;font-size:0.85rem;border-radius:8px;color:var(--text-muted);" placeholder="Dirección exacta o coordenadas (Ej: Lima, Perú)" oninput="linktreeUpdateLinkMetaData(${index}, 'address', this.value)">
            `;
        }
        
        // Advanced scheduling section
        const advancedHtml = `
            <details style="margin-top:0.5rem; border-top:1px dashed var(--border-color); padding-top:0.5rem;">
                <summary style="font-size:0.8rem; color:var(--text-muted); cursor:pointer; font-weight:500; outline:none;">
                    <i class="ph ph-calendar"></i> Programar Enlace (Opcional)
                </summary>
                <div style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                    <div style="flex:1;">
                        <label style="font-size:0.75rem; color:var(--text-muted);">Mostrar desde:</label>
                        <input type="datetime-local" class="qr-field-input" style="padding:0.4rem; font-size:0.8rem; border-radius:6px;" value="${link.meta_data?.start_date || ''}" oninput="linktreeUpdateLinkMetaData(${index}, 'start_date', this.value)">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:0.75rem; color:var(--text-muted);">Ocultar desde:</label>
                        <input type="datetime-local" class="qr-field-input" style="padding:0.4rem; font-size:0.8rem; border-radius:6px;" value="${link.meta_data?.end_date || ''}" oninput="linktreeUpdateLinkMetaData(${index}, 'end_date', this.value)">
                    </div>
                </div>
            </details>
        `;
        
        // Custom labels for the block summary
        let typeLabel = 'Enlace';
        if (link.type === 'youtube') typeLabel = 'YouTube';
        if (link.type === 'spotify') typeLabel = 'Spotify';
        if (link.type === 'text') typeLabel = 'Título';
        if (link.type === 'faq') typeLabel = 'FAQ';
        if (link.type === 'whatsapp') typeLabel = 'WhatsApp';
        if (link.type === 'map') typeLabel = 'Mapa';

        container.innerHTML += `
            <div style="display:flex;gap:1rem;align-items:flex-start;padding:1rem;background:var(--bg-body);border-radius:12px;border:1px solid var(--border-color); box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: transform 0.2s;">
                <div class="drag-handle" style="color:var(--text-muted); padding: 0.5rem; display:flex; flex-direction:column; gap:8px; align-items:center; cursor: grab;">
                    <i class="ph ph-dots-six-vertical" style="font-size:1.25rem;"></i>
                    <i class="ph ${typeIcon}" style="font-size:1.25rem; color:var(--primary-color);"></i>
                </div>
                <div style="flex:1;display:flex;flex-direction:column;gap:0.5rem;min-width:0;">
                    <details style="width:100%;" class="lt-block-details">
                        <summary style="outline:none; cursor:pointer; font-weight:600; font-size:0.9rem; padding:0.5rem 0; border-bottom:1px solid var(--border-color); user-select:none; display:flex; align-items:center; justify-content:space-between;">
                            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                ${link.title || 'Bloque sin título'} 
                                <span style="font-size:0.75rem; color:var(--text-muted); font-weight:normal; margin-left:0.5rem; padding:0.1rem 0.4rem; background:rgba(0,0,0,0.05); border-radius:4px;">${typeLabel}</span>
                            </span>
                        </summary>
                        <div style="display:flex;flex-direction:column;gap:0.75rem; margin-top:1rem; padding-bottom:0.5rem;">
                            ${innerHtml}
                            ${advancedHtml}
                        </div>
                    </details>
                </div>
                <button class="btn btn-outline" style="padding:0.75rem;color:#ef4444;border-color:transparent;border-radius:8px;background:#fef2f2;" onclick="linktreeRemoveLink(${index})"><i class="ph ph-trash" style="font-size:1.1rem;"></i></button>
            </div>
        `;
    });
    
    if (window.Sortable) {
        new Sortable(container, {
            animation: 150,
            handle: '.drag-handle',
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
    const slug = document.getElementById('lt_slug').value || 'mi-marca';
    const title = document.getElementById('lt_title').value || 'Título';
    const bio = document.getElementById('lt_bio').value;
    const font = document.getElementById('lt_font_family').value || 'Inter';
    const bgColor = document.getElementById('lt_bg_color').value;
    const textColor = document.getElementById('lt_text_color').value;
    const btnColor = document.getElementById('lt_btn_color').value;
    const btnTextColor = document.getElementById('lt_btn_text_color').value;
    const btnStyle = document.getElementById('lt_btn_style').value;
    
    const previewBox = document.getElementById('lt_preview_box');
    const previewContent = document.getElementById('lt_preview_content');
    
    previewBox.style.background = bgColor;
    previewBox.style.color = textColor;
    previewBox.style.fontFamily = `"${font}", sans-serif`;
    
    const imgSrc = window.ltPreviewImage || 'assets/images/default-avatar.png';
    
    let html = `
        <img src="${imgSrc}" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid ${textColor}20;margin-bottom:1rem;">
        <h3 style="font-weight:700;font-size:1.1rem;margin-bottom:0.25rem;">@${slug}</h3>
        <h4 style="font-weight:600;font-size:0.9rem;opacity:0.8;margin-bottom:0.5rem;">${title}</h4>
        <p style="font-size:0.8rem;opacity:0.9;margin-bottom:1.5rem;white-space:pre-wrap;">${bio}</p>
        <div style="width:100%;display:flex;flex-direction:column;gap:0.75rem;overflow-y:auto;padding-bottom:2rem;">
    `;
    
    let radius = '0.5rem';
    if(btnStyle === 'rounded-full') radius = '9999px';
    if(btnStyle === 'rounded-none') radius = '0';
    
    ltLinks.forEach(link => {
        if (link.type === 'link' || !link.type) {
            html += `
                <div style="width:100%;padding:0.75rem;background:${btnColor};color:${btnTextColor};border-radius:${radius};font-weight:600;font-size:0.85rem;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                    ${link.title || 'Enlace'}
                </div>
            `;
        } else if (link.type === 'youtube') {
            html += `
                <div style="width:100%; border-radius:${radius}; overflow:hidden; background:#000; aspect-ratio:16/9; display:flex; align-items:center; justify-content:center;">
                    <i class="ph ph-youtube-logo" style="font-size:2rem;color:#ff0000;"></i>
                </div>
            `;
        } else if (link.type === 'spotify') {
            html += `
                <div style="width:100%; border-radius:${radius}; overflow:hidden; background:#1ed760; padding:0.75rem; display:flex; align-items:center; gap:0.5rem; color:#fff;">
                    <i class="ph ph-spotify-logo" style="font-size:1.5rem;"></i>
                    <span style="font-size:0.85rem; font-weight:600;">${link.title || 'Spotify'}</span>
                </div>
            `;
        } else if (link.type === 'text') {
            html += `
                <div style="width:100%; margin-top:0.5rem;">
                    <h5 style="font-weight:700;font-size:1rem;margin-bottom:0.25rem;">${link.title || 'Título'}</h5>
                    <p style="font-size:0.8rem;opacity:0.9;">${link.meta_data?.text || ''}</p>
                </div>
            `;
        } else if (link.type === 'faq') {
            html += `
                <div style="width:100%;padding:0.75rem;background:${btnColor};color:${btnTextColor};border-radius:${radius};text-align:left;box-shadow:0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid var(--primary-color);">
                    <div style="font-weight:600;font-size:0.85rem;">${link.title || 'Pregunta Frecuente'}</div>
                </div>
            `;
        } else if (link.type === 'whatsapp') {
            html += `
                <div style="width:100%;padding:0.75rem;background:#25D366;color:#fff;border-radius:${radius};font-weight:600;font-size:0.85rem;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.1); display:flex; align-items:center; justify-content:center; gap:0.5rem;">
                    <i class="ph ph-whatsapp-logo" style="font-size:1.25rem;"></i>
                    ${link.title || 'WhatsApp'}
                </div>
            `;
        } else if (link.type === 'map') {
            html += `
                <div style="width:100%; border-radius:${radius}; overflow:hidden; background:#e5e7eb; aspect-ratio:16/9; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#4b5563;">
                    <i class="ph ph-map-pin" style="font-size:2rem;color:#ef4444;margin-bottom:0.5rem;"></i>
                    <span style="font-size:0.8rem; font-weight:600;">${link.title || 'Ubicación'}</span>
                </div>
            `;
        }
    });
    
    html += '</div>';
    previewContent.innerHTML = html;
}

function linktreeSave() {
    const slug = document.getElementById('lt_slug').value.trim();
    const title = document.getElementById('lt_title').value.trim();
    if(!slug || !title) {
        if(window.showToast) window.showToast('Slug y Título son requeridos', 'error');
        return;
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
    if(fileInput.files.length > 0) {
        fd.append('profile_image', fileInput.files[0]);
    }
    
    fetch('modules/herramientas/ajax_linktree.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                if(window.showToast) window.showToast('BioLink guardado con éxito', 'success');
                linktreeCancel();
                linktreeLoadList();
            } else {
                if(window.showToast) window.showToast(res.error, 'error');
            }
        });
}

function linktreeApplyTheme(preset) {
    if(preset === 'custom') return;
    
    let bg, text, btn, btnText, btnStyle, font;
    
    switch(preset) {
        case 'cyberpunk':
            bg = '#0f172a'; text = '#f8fafc'; btn = '#0f172a'; btnText = '#22d3ee'; btnStyle = 'rounded-none'; font = 'Space Grotesk';
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
    
    document.getElementById('lt_font_family').value = font;
    document.getElementById('lt_bg_color').value = bg;
    document.getElementById('lt_text_color').value = text;
    document.getElementById('lt_btn_color').value = btn;
    document.getElementById('lt_btn_text_color').value = btnText;
    document.getElementById('lt_btn_style').value = btnStyle;
    
    updateLinktreePreview();
}
