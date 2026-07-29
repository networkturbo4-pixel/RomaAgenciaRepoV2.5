<?php
// modules/drive/index.php
require_once 'includes/header.php';
?>
<style>
/* ─── Drive Container ─── */
.drive-wrapper {
    padding: var(--space-5) var(--space-6);
    max-width: 1400px;
    margin: 0 auto;
    font-family: var(--font-family);
}

/* ─── Toolbar (unified header) ─── */
.drive-toolbar {
    display: flex !important;
    justify-content: space-between;
    align-items: center !important;
    margin-bottom: var(--space-8);
    background: var(--bg-surface);
    padding: 0 var(--space-6);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    height: 64px;
    box-sizing: border-box;
}

[data-theme="dark"] .drive-toolbar {
    background: rgba(30, 41, 59, 0.5);
}

.drive-toolbar-left {
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

.btn-back {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-md);
    color: var(--color-text);
    text-decoration: none;
    font-size: 1.1rem;
    transition: all 0.2s;
    background: transparent;
    border: none;
    flex-shrink: 0;
}

.btn-back:hover {
    background: var(--primary-bg);
    color: var(--primary-color);
}

.drive-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--color-title);
    margin: 0;
    white-space: nowrap;
}

.header-divider {
    width: 1px;
    height: 24px;
    background: var(--border-color);
    margin: 0 var(--space-2);
}

.drive-breadcrumbs {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: 0.95rem;
    font-weight: 500;
    flex-wrap: wrap;
}

.bc-item {
    color: var(--color-text);
    cursor: pointer;
    text-decoration: none;
    padding: var(--space-1) var(--space-2);
    border-radius: var(--radius-sm);
    transition: all 0.15s;
}
.bc-item:hover {
    background: var(--primary-bg);
    color: var(--primary-color);
}

.bc-sep {
    color: var(--text-muted);
    font-size: 0.8rem;
}

.bc-current {
    color: var(--color-title);
    font-weight: 600;
    padding: var(--space-1) var(--space-2);
}

.drive-actions {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.btn-toggle {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--color-text);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.15s;
    font-size: 1rem;
}

.btn-toggle:hover, .btn-toggle.active {
    background: var(--primary-bg);
    color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-create {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    background: var(--color-btn-bg);
    color: var(--color-btn-text);
    border: none;
    padding: var(--space-2) var(--space-4);
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-create:hover {
    background: var(--color-btn-hover);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* ─── Content ─── */
.drive-content {
    min-height: 350px;
}

/* ─── Grid View ─── */
.drive-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: var(--space-4);
}

.drive-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: var(--space-5) var(--space-3) var(--space-4);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    color: var(--color-title);
}

.drive-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    transform: translateY(-3px);
}

[data-theme="dark"] .drive-card {
    background: rgba(30, 41, 59, 0.4);
}

[data-theme="dark"] .drive-card:hover {
    background: rgba(30, 41, 59, 0.7);
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
}

/* ─── Icon Pill ─── */
.card-icon-wrap {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    margin-bottom: var(--space-3);
    transition: transform 0.25s ease;
}

.drive-card:hover .card-icon-wrap {
    transform: scale(1.08);
}

/* Icon color backgrounds */
.ic-folder  { background: #fff8e1; color: #f9a825; }
.ic-image   { background: #fce4ec; color: #e53935; }
.ic-pdf     { background: #ffebee; color: #c62828; }
.ic-doc     { background: #e3f2fd; color: #1565c0; }
.ic-excel   { background: #e8f5e9; color: #2e7d32; }
.ic-slides  { background: #fff3e0; color: #e65100; }
.ic-video   { background: #fff3e0; color: #ef6c00; }
.ic-audio   { background: #f3e5f5; color: #7b1fa2; }
.ic-zip     { background: #eceff1; color: #546e7a; }
.ic-generic { background: #e8eaf6; color: #3949ab; }

[data-theme="dark"] .ic-folder  { background: rgba(249,168,37,0.15); }
[data-theme="dark"] .ic-image   { background: rgba(229,57,53,0.15); }
[data-theme="dark"] .ic-pdf     { background: rgba(198,40,40,0.15); }
[data-theme="dark"] .ic-doc     { background: rgba(21,101,192,0.15); }
[data-theme="dark"] .ic-excel   { background: rgba(46,125,50,0.15); }
[data-theme="dark"] .ic-slides  { background: rgba(230,81,0,0.15); }
[data-theme="dark"] .ic-video   { background: rgba(239,108,0,0.15); }
[data-theme="dark"] .ic-audio   { background: rgba(123,31,162,0.15); }
[data-theme="dark"] .ic-zip     { background: rgba(84,110,122,0.15); }
[data-theme="dark"] .ic-generic { background: rgba(57,73,171,0.15); }

.card-name {
    font-size: 0.85rem;
    font-weight: 500;
    line-height: 1.35;
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    color: var(--color-title);
}

/* ─── List View ─── */
.drive-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
    background: var(--bg-surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-color);
    overflow: hidden;
}

[data-theme="dark"] .drive-list {
    background: rgba(30, 41, 59, 0.4);
}

.drive-list .drive-card {
    flex-direction: row;
    align-items: center;
    text-align: left;
    border-radius: 0;
    border: none;
    border-bottom: 1px solid var(--border-color);
    padding: var(--space-3) var(--space-4);
    gap: var(--space-3);
}

.drive-list .drive-card:last-child {
    border-bottom: none;
}

.drive-list .drive-card:hover {
    transform: none;
    background: var(--primary-bg);
}

.drive-list .card-icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    font-size: 1rem;
    margin-bottom: 0;
    flex-shrink: 0;
}

.drive-list .card-name {
    flex: 1;
    -webkit-line-clamp: 1;
}

/* ─── States ─── */
.drive-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 300px;
    color: var(--text-muted);
    gap: var(--space-3);
}

.drive-loading .spinner {
    width: 32px;
    height: 32px;
    border: 3px solid var(--border-color);
    border-top-color: var(--primary-color);
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}

.drive-empty {
    text-align: center;
    padding: var(--space-8);
    color: var(--text-muted);
}

.drive-empty i {
    font-size: 3.5rem;
    margin-bottom: var(--space-3);
    opacity: 0.3;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .drive-wrapper { padding: var(--space-4); }
    .drive-toolbar {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-3);
    }
    .drive-actions { width: 100%; justify-content: flex-end; }
    .drive-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
}
</style>

<div class="drive-wrapper">

    <!-- Unified Header -->
    <div class="drive-toolbar">
        <div class="drive-toolbar-left">
            <a href="index.php?module=workspace&action=index" class="btn-back" title="Volver a Workspace">
                <i class="ph ph-arrow-left"></i>
            </a>
            <span class="drive-title">Google Drive</span>
            <div class="header-divider"></div>
            <div class="drive-breadcrumbs" id="driveBreadcrumbs">
                <span class="bc-current"><i class="ph ph-hard-drives"></i> Mi Unidad</span>
            </div>
        </div>
        <div class="drive-actions">
            <button class="btn-toggle active" id="btnGrid" title="Cuadrícula"><i class="ph ph-squares-four"></i></button>
            <button class="btn-toggle" id="btnList" title="Lista"><i class="ph ph-list"></i></button>
            <button class="btn-create" onclick="createNewFolder()"><i class="ph ph-folder-plus"></i> Nueva Carpeta</button>
        </div>
    </div>

    <!-- Content -->
    <div class="drive-content" id="driveContent"></div>
</div>

<script>
const STATE = {
    currentFolderId: 'root',
    viewMode: 'grid',
    folderHistory: [{id: 'root', name: 'Mi Unidad'}]
};

const DOM = {
    content: document.getElementById('driveContent'),
    breadcrumbs: document.getElementById('driveBreadcrumbs'),
    btnGrid: document.getElementById('btnGrid'),
    btnList: document.getElementById('btnList')
};

DOM.btnGrid.addEventListener('click', () => setView('grid'));
DOM.btnList.addEventListener('click', () => setView('list'));

function setView(mode) {
    STATE.viewMode = mode;
    DOM.btnGrid.classList.toggle('active', mode === 'grid');
    DOM.btnList.classList.toggle('active', mode === 'list');
    if (window._driveFiles) renderFiles(window._driveFiles);
}

function renderBreadcrumbs() {
    let h = '';
    STATE.folderHistory.forEach((item, i) => {
        if (i === STATE.folderHistory.length - 1) {
            h += `<span class="bc-current">${i === 0 ? '<i class="ph ph-hard-drives"></i> ' : ''}${item.name}</span>`;
        } else {
            h += `<span class="bc-item" onclick="navTo(${i})">${i === 0 ? '<i class="ph ph-hard-drives"></i> ' : ''}${item.name}</span>`;
            h += `<span class="bc-sep"><i class="ph ph-caret-right"></i></span>`;
        }
    });
    DOM.breadcrumbs.innerHTML = h;
}

function navTo(index) {
    STATE.folderHistory = STATE.folderHistory.slice(0, index + 1);
    loadFolder(STATE.folderHistory[index].id, false);
}

function loadFolder(folderId, push = true) {
    DOM.content.innerHTML = `<div class="drive-loading"><div class="spinner"></div><span>Cargando…</span></div>`;

    const fd = new FormData();
    fd.append('action', 'list');
    fd.append('folderId', folderId);

    fetch('modules/drive/ajax_drive.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (push && data.currentFolder && folderId !== 'root') {
                if (STATE.folderHistory[STATE.folderHistory.length - 1].id !== folderId) {
                    STATE.folderHistory.push({id: folderId, name: data.currentFolder.name});
                }
            }
            STATE.currentFolderId = folderId;
            window._driveFiles = data.files;
            renderBreadcrumbs();
            renderFiles(data.files);
        } else {
            DOM.content.innerHTML = `<div class="drive-empty"><i class="ph ph-warning-circle"></i><p>${data.error}</p></div>`;
        }
    })
    .catch(() => {
        DOM.content.innerHTML = `<div class="drive-empty"><i class="ph ph-wifi-x"></i><p>Error de conexión</p></div>`;
    });
}

function iconFor(file) {
    if (file.mimeType === 'application/vnd.google-apps.folder')
        return { ph: 'ph-folder-notch-fill', cls: 'ic-folder' };

    const m = (file.mimeType || '').toLowerCase();
    const ext = (file.name || '').split('.').pop().toLowerCase();

    if (m.startsWith('image/') || ['jpg','jpeg','png','gif','webp','svg','bmp','ico'].includes(ext))
        return { ph: 'ph-image', cls: 'ic-image' };
    if (m === 'application/pdf' || ext === 'pdf')
        return { ph: 'ph-file-pdf', cls: 'ic-pdf' };
    if (m.includes('spreadsheet') || m.includes('excel') || ['xls','xlsx','csv'].includes(ext))
        return { ph: 'ph-file-xls', cls: 'ic-excel' };
    if (m.includes('document') || m.includes('word') || ['doc','docx','txt','rtf','odt'].includes(ext))
        return { ph: 'ph-file-doc', cls: 'ic-doc' };
    if (m.includes('presentation') || m.includes('powerpoint') || ['ppt','pptx','key'].includes(ext))
        return { ph: 'ph-presentation-chart', cls: 'ic-slides' };
    if (m.startsWith('video/') || ['mp4','mov','avi','mkv','webm'].includes(ext))
        return { ph: 'ph-video-camera', cls: 'ic-video' };
    if (m.startsWith('audio/') || ['mp3','wav','ogg','m4a','flac'].includes(ext))
        return { ph: 'ph-music-notes', cls: 'ic-audio' };
    if (m.includes('zip') || m.includes('compressed') || ['zip','rar','7z','tar','gz'].includes(ext))
        return { ph: 'ph-file-zip', cls: 'ic-zip' };

    return { ph: 'ph-file-text', cls: 'ic-generic' };
}

function renderFiles(files) {
    if (!files || files.length === 0) {
        DOM.content.innerHTML = `<div class="drive-empty"><i class="ph ph-folder-open"></i><p>Esta carpeta está vacía</p></div>`;
        return;
    }

    files.sort((a, b) => {
        const af = a.mimeType === 'application/vnd.google-apps.folder';
        const bf = b.mimeType === 'application/vnd.google-apps.folder';
        if (af && !bf) return -1;
        if (!af && bf) return 1;
        return a.name.localeCompare(b.name);
    });

    const cls = STATE.viewMode === 'grid' ? 'drive-grid' : 'drive-list';
    let h = `<div class="${cls}">`;

    files.forEach(f => {
        const ic = iconFor(f);
        const isFolder = f.mimeType === 'application/vnd.google-apps.folder';
        const iconHtml = `<div class="card-icon-wrap ${ic.cls}"><i class="ph ${ic.ph}"></i></div>`;

        if (isFolder) {
            h += `<div class="drive-card" onclick="loadFolder('${f.id}')">${iconHtml}<span class="card-name">${f.name}</span></div>`;
        } else {
            h += `<a class="drive-card" href="${f.webViewLink}" target="_blank">${iconHtml}<span class="card-name">${f.name}</span></a>`;
        }
    });

    h += `</div>`;
    DOM.content.innerHTML = h;
}

function createNewFolder() {
    const name = prompt("Nombre de la nueva carpeta:");
    if (!name || !name.trim()) return;

    const fd = new FormData();
    fd.append('action', 'create_folder');
    fd.append('parentFolderId', STATE.currentFolderId);
    fd.append('folderName', name.trim());

    fetch('modules/drive/ajax_drive.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => d.success ? loadFolder(STATE.currentFolderId, false) : alert("Error: " + d.error))
    .catch(() => alert("Error de conexión"));
}

document.addEventListener("DOMContentLoaded", () => loadFolder('root', false));
</script>

<?php require_once 'includes/footer.php'; ?>
