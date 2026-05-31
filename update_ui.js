const fs = require('fs');
const path = require('path');

// 1. Update index.php
const indexFile = path.join(__dirname, 'modules/chat/index.php');
let index = fs.readFileSync(indexFile, 'utf8');

const infoPanelHtml = `        <!-- Chat Info Panel -->
        <div id="chat-info-panel" class="chat-info-panel">
            <div class="info-panel-header">
                <button class="chat-icon-btn-sm" id="btn-close-info"><i class="ph ph-x"></i></button>
                <span>Info. del chat</span>
            </div>
            <div class="info-panel-body">
                <div class="info-panel-avatar">
                    <div id="info-panel-icon" class="info-avatar-circle"><i class="ph ph-users"></i></div>
                    <h3 id="info-panel-name">Canal</h3>
                    <p id="info-panel-desc" class="info-desc">Sin descripción</p>
                </div>
                <div class="info-section">
                    <div class="info-section-header" id="info-media-header">
                        <span><i class="ph ph-image"></i> Medios</span>
                        <span id="info-media-count" class="info-count">0</span>
                    </div>
                    <div id="info-media-grid" class="info-media-grid"></div>
                </div>
                <div class="info-section">
                    <div class="info-section-header">
                        <span><i class="ph ph-file-text"></i> Documentos</span>
                        <span id="info-docs-count" class="info-count">0</span>
                    </div>
                    <div id="info-docs-list" class="info-docs-list"></div>
                </div>
                <div class="info-section">
                    <div class="info-section-header">
                        <span><i class="ph ph-link"></i> Enlaces</span>
                        <span id="info-links-count" class="info-count">0</span>
                    </div>
                    <div id="info-links-list" class="info-links-list"></div>
                </div>
                <div class="info-section">
                    <div class="info-section-header">
                        <span><i class="ph ph-push-pin"></i> Mensajes fijados</span>
                        <span id="info-pinned-count" class="info-count">0</span>
                    </div>
                    <div id="info-pinned-list" class="info-pinned-list"></div>
                </div>
                <div class="info-section">
                    <div class="info-section-header">
                        <span><i class="ph ph-users-three"></i> Miembros</span>
                        <span id="info-members-count" class="info-count">0</span>
                    </div>
                    <div id="info-members-list" class="info-members-list"></div>
                </div>
            </div>
        </div>
    </div>`;

if (!index.includes('id="chat-info-panel"')) {
    index = index.replace('    </div>\r\n</div>\r\n<script', infoPanelHtml + '\r\n</div>\r\n<script');
    index = index.replace('    </div>\n</div>\n<script', infoPanelHtml + '\n</div>\n<script');
    fs.writeFileSync(indexFile, index);
}

// Make button visible in header
index = index.replace('id="btn-group-info" title="Información del Chat" style="display:none;', 'id="btn-group-info" title="Información del Chat" style="');
fs.writeFileSync(indexFile, index);

// 2. Update chat.css
const cssFile = path.join(__dirname, 'modules/chat/chat.css');
let css = fs.readFileSync(cssFile, 'utf8');

const cssAdditions = `
/* Drag & Drop */
.chat-messages.drag-over { position: relative; }
.chat-messages.drag-over::after {
    content: 'Suelta los archivos aquí para adjuntar';
    position: absolute;
    inset: 0;
    background: rgba(99, 102, 241, 0.1);
    border: 3px dashed #6366f1;
    border-radius: 12px;
    z-index: 100;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.5rem;
    color: #6366f1;
}

/* Info Panel */
.chat-info-panel {
    width: 0;
    overflow: hidden;
    background: var(--bg-surface);
    border-left: 1px solid var(--border-color);
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}
.chat-info-panel.active { width: 340px; }
.info-panel-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    font-weight: 700;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.info-panel-body {
    flex: 1;
    overflow-y: auto;
    padding-bottom: 2rem;
}
.info-panel-avatar {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.5rem 1rem;
    gap: 0.5rem;
}
.info-avatar-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #10b981;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
}
.info-panel-avatar h3 { margin: 0; font-size: 1.15rem; }
.info-desc { margin: 0; font-size: 0.8rem; color: var(--text-muted); text-align: center; }
.info-section { border-top: 1px solid var(--border-color); }
.info-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.85rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-main);
    cursor: pointer;
}
.info-section-header:hover { background: rgba(0,0,0,0.02); }
.info-section-header i { margin-right: 0.4rem; color: var(--primary-color); }
.info-count { color: var(--text-muted); font-weight: 500; }
.info-media-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 3px;
    padding: 0 3px 3px;
}
.info-media-item {
    aspect-ratio: 1;
    overflow: hidden;
    cursor: pointer;
    border-radius: 4px;
}
.info-media-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.2s;
}
.info-media-item:hover img { transform: scale(1.05); }
.info-doc-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 1rem;
    font-size: 0.82rem;
    border-bottom: 1px solid var(--border-color);
    cursor: pointer;
}
.info-doc-item:hover { background: rgba(0,0,0,0.02); }
.info-doc-icon {
    width: 36px; height: 36px;
    border-radius: 8px;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: #10b981;
    flex-shrink: 0;
}
.info-doc-name { flex: 1; font-weight: 500; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.info-link-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
    color: var(--primary-color);
    text-decoration: none;
    border-bottom: 1px solid var(--border-color);
}
.info-link-item:hover { background: rgba(0,0,0,0.02); }
.info-pinned-item {
    padding: 0.6rem 1rem;
    font-size: 0.82rem;
    border-bottom: 1px solid var(--border-color);
}
.info-pinned-item .pinned-user { font-weight: 600; color: var(--text-main); }
.info-pinned-item .pinned-text { color: var(--text-muted); margin-top: 0.15rem; }
.info-member-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.55rem 1rem;
    font-size: 0.85rem;
    border-bottom: 1px solid var(--border-color);
}
.info-member-name { font-weight: 500; }
.info-member-online { width: 8px; height: 8px; border-radius: 50%; background: #10b981; margin-left: auto; }

/* Dark mode tweaks */
html.dark .info-doc-icon, [data-theme='dark'] .info-doc-icon { background: rgba(255,255,255,0.08); }
html.dark .info-section-header:hover, [data-theme='dark'] .info-section-header:hover { background: rgba(255,255,255,0.04); }
html.dark .info-doc-item:hover, html.dark .info-link-item:hover, [data-theme='dark'] .info-doc-item:hover, [data-theme='dark'] .info-link-item:hover { background: rgba(255,255,255,0.04); }

@media (max-width: 768px) {
    .chat-info-panel.active {
        position: fixed;
        top: 0; right: 0; bottom: 0;
        width: 100%;
        z-index: 1000;
    }
}
`;
if (!css.includes('.chat-info-panel')) {
    fs.appendFileSync(cssFile, cssAdditions);
}

// 3. Update chat.js
const jsFile = path.join(__dirname, 'modules/chat/chat.js');
let js = fs.readFileSync(jsFile, 'utf8');

// A. Info Panel logic
const infoPanelJs = `
// ── INFO PANEL ──
async function openInfoPanel() {
    if (!currentChannelId) return;
    const panel = $('chat-info-panel');
    if(panel) panel.classList.add('active');

    // Set header name
    const elName = $('chat-channel-name');
    if (elName) $('info-panel-name').textContent = elName.textContent;
    
    // Set desc
    const meta = $('chat-channel-meta');
    if (meta) {
        $('info-panel-desc').textContent = meta.textContent || 'Sin descripción';
        // Get icon from channel list
        const chItem = document.querySelector('.chat-channel-item.active');
        if (chItem) {
            const avatar = chItem.querySelector('.channel-avatar img');
            const icon = chItem.querySelector('.channel-avatar');
            if (avatar) {
                $('info-panel-icon').innerHTML = \`<img src="\${avatar.src}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">\`;
                $('info-panel-icon').style.background = 'none';
            } else if (icon) {
                $('info-panel-icon').innerHTML = icon.innerHTML;
                $('info-panel-icon').style.background = icon.style.background || '#10b981';
            }
        }
    }

    const fd = new FormData();
    fd.append('action', 'get_channel_media');
    fd.append('channel_id', currentChannelId);
    try {
        const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) return;

        // Media
        $('info-media-count').textContent = data.media.length;
        $('info-media-grid').innerHTML = data.media.map(m => 
            \`<div class="info-media-item" onclick="window.open('\${m.attachment}','_blank')"><img src="\${m.attachment}" loading="lazy"></div>\`
        ).join('');

        // Docs
        $('info-docs-count').textContent = data.docs.length;
        $('info-docs-list').innerHTML = data.docs.map(d => {
            const name = d.attachment_name || d.attachment.split('/').pop();
            return \`<div class="info-doc-item" onclick="window.open('\${d.attachment}','_blank')">
                <div class="info-doc-icon"><i class="ph ph-file-text"></i></div>
                <span class="info-doc-name">\${escapeHtml(name)}</span>
            </div>\`;
        }).join('');

        // Links
        $('info-links-count').textContent = data.links.length;
        $('info-links-list').innerHTML = data.links.map(l => {
            const urlMatch = l.message.match(/https?:\\/\\/[^\\s]+/);
            const url = urlMatch ? urlMatch[0] : '#';
            return \`<a class="info-link-item" href="\${url}" target="_blank"><i class="ph ph-link"></i> \${escapeHtml(url.substring(0, 45))}\${url.length > 45 ? '...' : ''}</a>\`;
        }).join('');

        // Pinned
        $('info-pinned-count').textContent = data.pinned.length;
        $('info-pinned-list').innerHTML = data.pinned.map(p => 
            \`<div class="info-pinned-item"><div class="pinned-user">\${escapeHtml(p.user_name || 'Usuario')}</div><div class="pinned-text">\${escapeHtml((p.message || 'Archivo adjunto').substring(0, 80))}</div></div>\`
        ).join('');

        // Members
        $('info-members-count').textContent = data.members.length;
        $('info-members-list').innerHTML = data.members.map(m => {
            const av = m.avatar ? \`<img src="\${m.avatar}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">\` : \`<div style="width:32px;height:32px;border-radius:50%;background:#10b981;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.8rem;">\${(m.name||'U').charAt(0).toUpperCase()}</div>\`;
            return \`<div class="info-member-item">\${av}<span class="info-member-name">\${escapeHtml(m.name)}</span>\${m.is_online ? '<div class="info-member-online"></div>' : ''}</div>\`;
        }).join('');
    } catch (e) {
        console.error(e);
    }
}
function closeInfoPanel() {
    $('chat-info-panel')?.classList.remove('active');
}
`;

if (!js.includes('function openInfoPanel()')) {
    js += '\n' + infoPanelJs;
}
if (!js.includes('btn-group-info')?.addEventListener) {
    js += `
if ($('btn-group-info')) $('btn-group-info').addEventListener('click', openInfoPanel);
if ($('btn-close-info')) $('btn-close-info').addEventListener('click', closeInfoPanel);
`;
}

// B. Drag and Drop
const dndLogic = `
// Drag & Drop for file attachments
const chatMsgs = $('chat-messages');
if (chatMsgs) {
    chatMsgs.addEventListener('dragover', e => {
        e.preventDefault();
        chatMsgs.classList.add('drag-over');
    });
    chatMsgs.addEventListener('dragleave', e => {
        e.preventDefault();
        chatMsgs.classList.remove('drag-over');
    });
    chatMsgs.addEventListener('drop', e => {
        e.preventDefault();
        chatMsgs.classList.remove('drag-over');
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            selectedFiles = Array.from(e.dataTransfer.files);
            const isSingleImage = selectedFiles.length === 1 && selectedFiles[0].type.startsWith('image/');
            if (isSingleImage) {
                const reader = new FileReader();
                reader.onload = e => {
                    $('image-send-preview').src = e.target.result;
                    $('image-send-modal').style.display = 'flex';
                };
                reader.readAsDataURL(selectedFiles[0]);
            } else {
                renderFilePreviews();
            }
        }
    });
}
`;
if (!js.includes("chatMsgs.addEventListener('dragover'")) {
    js += '\n' + dndLogic;
}

// C. Pin message modal logic
// We need to find the exact function handlePinMessage (which is likely in a case block or function)
// It usually calls fetch(...) with fd.append('action', 'pin_message');
// Since it's deep inside a context menu handler in chat.js, we will just redefine the handler logic or replace the fetch call.
let pinTargetMatch = js.match(/fd\.append\('action', 'pin_message'\);\\s+fd\.append\('message_id', msgId\);/);
if (pinTargetMatch) {
    const replacementPin = `const { value: duration } = await Swal.fire({
            title: 'Fijar mensaje',
            text: '¿Por cuánto tiempo deseas fijarlo?',
            input: 'select',
            inputOptions: {
                '1h': '1 hora',
                '6h': '6 horas',
                '24h': '24 horas',
                '7d': '7 días',
                'permanent': 'Permanente'
            },
            inputValue: 'permanent',
            showCancelButton: true,
            confirmButtonText: 'Fijar',
            cancelButtonText: 'Cancelar'
        });
        if (!duration) return;
        fd.append('action', 'pin_message');
        fd.append('message_id', msgId);
        fd.append('pin_duration', duration);`;
    js = js.replace("fd.append('action', 'pin_message');\n                fd.append('message_id', msgId);", replacementPin);
    js = js.replace("fd.append('action', 'pin_message');\r\n                fd.append('message_id', msgId);", replacementPin);
}

fs.writeFileSync(jsFile, js);
console.log('Frontend UI updated successfully.');
