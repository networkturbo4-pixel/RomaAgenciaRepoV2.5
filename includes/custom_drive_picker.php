<!-- includes/custom_drive_picker.php -->
<link rel="stylesheet" href="assets/css/drive.css?v=<?php echo filemtime('assets/css/drive.css'); ?>">
<style>
/* Custom Drive Picker Styles (Dropbox Style) */
.custom-drive-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.custom-drive-overlay.active {
    display: flex;
    opacity: 1;
}
.custom-drive-modal {
    background: var(--bg-surface, #ffffff);
    width: 1200px;
    max-width: 95vw;
    height: 700px;
    max-height: 90vh;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: scale(0.95);
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.custom-drive-overlay.active .custom-drive-modal {
    transform: scale(1);
}

/* Header & Breadcrumbs */
.cd-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-color, #f8fafc);
}
.cd-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    font-weight: 500;
    color: var(--text-main, #475569);
    overflow-x: auto;
    white-space: nowrap;
}
.cd-breadcrumb-item {
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    transition: background 0.2s;
}
.cd-breadcrumb-item:hover {
    background: var(--border-color, #e2e8f0);
    color: var(--text-main, #0f172a);
}
.cd-breadcrumb-separator {
    color: var(--text-muted, #cbd5e1);
}
.cd-close-btn {
    background: none;
    border: none;
    color: var(--text-muted, #64748b);
    font-size: 1.25rem;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 50%;
    transition: all 0.2s;
    display: flex;
}
.cd-close-btn:hover {
    background: var(--border-color, #e2e8f0);
    color: var(--danger-color, #ef4444);
}

/* Body layout */
.cd-body {
    display: flex;
    flex: 1;
    overflow: hidden;
    min-height: 0;
    min-width: 0;
}

/* Sidebar */
.cd-sidebar {
    width: 240px;
    background: var(--bg-color, #f8fafc);
    border-right: 1px solid var(--border-color, #e2e8f0);
    padding: 1.5rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.cd-sidebar-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    color: var(--text-muted, #475569);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}
.cd-sidebar-item:hover {
    background: var(--border-color, #e2e8f0);
    color: var(--text-main, #0f172a);
}
.cd-sidebar-item.active {
    background: rgba(59, 130, 246, 0.1);
    color: var(--primary-color, #2563eb);
}
.cd-sidebar-item i {
    font-size: 1.25rem;
}

/* Main Content area */
.cd-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--bg-surface, #ffffff);
    position: relative;
    min-width: 0;
    min-height: 0;
}

/* Grid / List */
.cd-grid {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1rem;
    align-content: start;
}
.cd-folder-card {
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    position: relative;
    user-select: none;
}
.cd-folder-card:hover {
    border-color: var(--text-muted, #94a3b8);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}
.cd-folder-card.selected {
    border-color: var(--primary-color, #3b82f6);
    background: rgba(59, 130, 246, 0.05);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}
.cd-folder-icon {
    font-size: 3rem;
    color: var(--primary-color, #3b82f6);
}
.cd-folder-icon.shared {
    color: #8b5cf6;
}
.cd-folder-name {
    font-weight: 500;
    color: var(--text-main, #1e293b);
    font-size: 0.9rem;
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Loading State */
.cd-loader-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(var(--bg-surface-rgb, 255, 255, 255), 0.8);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10;
}
.cd-loader-overlay.active {
    display: flex;
}
.cd-spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3b82f6;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    animation: spin 1s linear infinite;
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

/* Empty State */
.cd-empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem;
    color: var(--text-muted, #94a3b8);
    display: none;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}
.cd-empty-state i {
    font-size: 3rem;
}

/* Footer */
.cd-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-color, #f8fafc);
}
.cd-selection-info {
    font-size: 0.9rem;
    color: var(--text-muted, #64748b);
    font-weight: 500;
}
.cd-actions {
    display: flex;
    gap: 0.75rem;
}

/* Responsive Design para el Modal del Picker */
@media (max-width: 992px) {
    .custom-drive-modal {
        width: 100% !important;
        height: 100vh !important;
        max-height: 100vh !important;
        max-width: 100vw !important;
        border-radius: 0 !important;
    }
    .cd-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
        padding: 1rem;
    }
    .cd-header > div:last-child {
        width: 100%;
        justify-content: space-between;
    }
    .cd-body {
        flex-direction: column !important;
    }
    .cd-sidebar {
        width: 100% !important;
        border-right: none !important;
        border-bottom: 1px solid var(--border-color, #e2e8f0) !important;
        flex-direction: row !important;
        padding: 0.5rem !important;
        overflow-x: auto !important;
        flex-shrink: 0;
    }
    .cd-sidebar-item {
        white-space: nowrap;
        padding: 0.5rem 1rem !important;
        flex: 1;
        justify-content: center;
    }
    .cd-grid {
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)) !important;
        padding: 1rem !important;
        gap: 1rem !important;
    }
    .cd-footer {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;
        align-items: flex-start;
    }
    .cd-actions {
        width: 100%;
        display: flex;
    }
    .cd-actions button {
        flex: 1;
    }
}
</style>

<div class="custom-drive-overlay" id="customDriveModal">
    <div class="custom-drive-modal">
        <!-- Header -->
        <div class="cd-header">
            <div class="cd-breadcrumbs" id="cdBreadcrumbs" style="flex:1;">
                <!-- Populated by JS -->
                <div class="cd-breadcrumb-item">Mi Unidad</div>
            </div>
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <button type="button" class="btn btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.8rem; display:flex; gap:0.25rem; align-items:center; border-radius:12px;" onclick="cdCreateFolder()">
                    <i class="ph ph-folder-plus"></i> Nueva Carpeta
                </button>
                <button class="cd-close-btn" onclick="cdClosePicker()">
                    <i class="ph ph-x"></i>
                </button>
            </div>
        </div>

        <!-- Body -->
        <div class="cd-body">
            <!-- Sidebar -->
            <div class="cd-sidebar" id="cdSidebar">
                <div class="cd-sidebar-item active" onclick="cdLoadRoot('root', this)">
                    <i class="ph ph-hard-drives"></i> Mi Unidad
                </div>
                <div class="cd-sidebar-item" onclick="cdLoadSharedDrives(this)">
                    <i class="ph ph-users-three"></i> Unidades Compartidas
                </div>
            </div>

            <!-- Main Content -->
            <div class="cd-main">
                <div class="cd-loader-overlay" id="cdLoader">
                    <div class="cd-spinner"></div>
                </div>

                <div class="cd-grid" id="cdGrid">
                    <!-- Folders populated by JS -->
                </div>

                <div class="cd-empty-state" id="cdEmpty">
                    <i class="ph ph-folder-dashed"></i>
                    <p>Esta carpeta está vacía.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="cd-footer">
            <div class="cd-selection-info" id="cdSelectionInfo">Ninguna carpeta seleccionada</div>
            <div class="cd-actions">
                <button class="btn btn-outline" onclick="cdClosePicker()">Cancelar</button>
                <button class="btn btn-primary" id="cdBtnSelect" disabled onclick="cdConfirmSelection()">Seleccionar Carpeta</button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Custom Drive Picker Controller
 */
const CD_API_ENDPOINT = 'includes/ajax_get_drive_folders.php';
let cdCurrentPath = [{id: 'root', name: 'Mi Unidad', isDrive: false}];
let cdSelectedFolder = null;
let cdCurrentDriveId = null; // null for My Drive, or the ID of the Shared Drive
let cdCallback = null;

// The global context restriction
let cdRestrictedParentId = null;

function cdOpenPicker(restrictedFolderId = null, onSelectCallback) {
    cdCallback = onSelectCallback;
    cdRestrictedParentId = restrictedFolderId;
    
    document.getElementById('customDriveModal').classList.add('active');
    
    if (cdRestrictedParentId) {
        // Hide sidebar because we are restricted to a specific folder
        document.getElementById('cdSidebar').style.display = 'none';
        cdLoadRestrictedRoot(cdRestrictedParentId);
    } else {
        document.getElementById('cdSidebar').style.display = 'flex';
        const rootItem = document.querySelector('.cd-sidebar-item'); // First item is 'Mi Unidad'
        cdLoadRoot('root', rootItem);
    }
}

function cdClosePicker() {
    document.getElementById('customDriveModal').classList.remove('active');
    cdSelectedFolder = null;
    cdUpdateSelectionUI();
}

function cdShowLoader(show) {
    document.getElementById('cdLoader').classList.toggle('active', show);
}

function cdUpdateSelectionUI() {
    const btn = document.getElementById('cdBtnSelect');
    const info = document.getElementById('cdSelectionInfo');
    
    if (cdSelectedFolder) {
        btn.disabled = false;
        info.innerHTML = `<span style="color: var(--primary-color, #2563eb); font-weight:600;"><i class="ph ph-folder"></i> ${cdSelectedFolder.name}</span> seleccionada`;
    } else {
        btn.disabled = true;
        info.innerText = 'Ninguna carpeta seleccionada';
    }
}

function cdConfirmSelection() {
    if (cdSelectedFolder && cdCallback) {
        cdCallback({
            id: cdSelectedFolder.id,
            url: cdSelectedFolder.url,
            name: cdSelectedFolder.name
        });
        cdClosePicker();
    }
}

async function cdLoadRoot(rootId, sidebarEl) {
    // Update sidebar UI
    document.querySelectorAll('.cd-sidebar-item').forEach(el => el.classList.remove('active'));
    if(sidebarEl) sidebarEl.classList.add('active');
    
    cdCurrentDriveId = null;
    cdCurrentPath = [{id: rootId, name: 'Mi Unidad', isDrive: false}];
    cdRenderBreadcrumbs();
    await cdFetchFolders(rootId, null);
}

async function cdLoadRestrictedRoot(folderId) {
    cdCurrentDriveId = null; // We might not know the drive id, API usually handles it if supportsAllDrives is true
    cdCurrentPath = [{id: folderId, name: 'Carpeta del Proyecto', isDrive: false}];
    cdRenderBreadcrumbs();
    await cdFetchFolders(folderId, null);
}

async function cdLoadSharedDrives(sidebarEl) {
    document.querySelectorAll('.cd-sidebar-item').forEach(el => el.classList.remove('active'));
    if(sidebarEl) sidebarEl.classList.add('active');
    
    cdCurrentPath = [{id: 'shared_drives_root', name: 'Unidades Compartidas', isDrive: false}];
    cdRenderBreadcrumbs();
    
    cdShowLoader(true);
    document.getElementById('cdGrid').innerHTML = '';
    document.getElementById('cdEmpty').style.display = 'none';
    cdSelectedFolder = null;
    cdUpdateSelectionUI();

    try {
        const formData = new FormData();
        formData.append('action', 'list_shared_drives');
        
        const response = await fetch(CD_API_ENDPOINT, { method: 'POST', body: formData });
        const result = await response.json();
        
        cdShowLoader(false);
        if (result.success) {
            if (result.data.length === 0) {
                document.getElementById('cdEmpty').style.display = 'flex';
            } else {
                result.data.forEach(drive => {
                    cdRenderItem(drive.id, drive.name, null, null, true);
                });
            }
        } else {
            alert('Error: ' + result.error);
        }
    } catch (e) {
        cdShowLoader(false);
        console.error(e);
        alert('Error de red al cargar unidades compartidas.');
    }
}

async function cdNavigateTo(folderId, folderName, isDrive = false) {
    if (isDrive) {
        cdCurrentDriveId = folderId;
    }
    cdCurrentPath.push({id: folderId, name: folderName, isDrive: isDrive});
    cdRenderBreadcrumbs();
    await cdFetchFolders(folderId, cdCurrentDriveId);
}

async function cdNavigateBreadcrumb(index) {
    if (index === cdCurrentPath.length - 1) return; // Already here
    
    const target = cdCurrentPath[index];
    cdCurrentPath = cdCurrentPath.slice(0, index + 1);
    cdRenderBreadcrumbs();
    
    if (target.id === 'shared_drives_root') {
        const sidebarEl = document.querySelectorAll('.cd-sidebar-item')[1];
        await cdLoadSharedDrives(sidebarEl);
    } else {
        // Re-determine drive ID by looking at the path history
        cdCurrentDriveId = null;
        for (let i = 0; i <= index; i++) {
            if (cdCurrentPath[i].isDrive) cdCurrentDriveId = cdCurrentPath[i].id;
        }
        await cdFetchFolders(target.id, cdCurrentDriveId);
    }
}

function cdRenderBreadcrumbs() {
    const container = document.getElementById('cdBreadcrumbs');
    container.innerHTML = '';
    
    cdCurrentPath.forEach((item, index) => {
        const div = document.createElement('div');
        div.className = 'cd-breadcrumb-item';
        div.innerText = item.name;
        div.onclick = () => cdNavigateBreadcrumb(index);
        
        container.appendChild(div);
        
        if (index < cdCurrentPath.length - 1) {
            const sep = document.createElement('i');
            sep.className = 'ph ph-caret-right cd-breadcrumb-separator';
            container.appendChild(sep);
        }
    });
    // scroll to end
    container.scrollLeft = container.scrollWidth;
}

async function cdFetchFolders(parentId, driveId) {
    cdShowLoader(true);
    const grid = document.getElementById('cdGrid');
    grid.innerHTML = '';
    document.getElementById('cdEmpty').style.display = 'none';
    cdSelectedFolder = null;
    cdUpdateSelectionUI();

    try {
        const formData = new FormData();
        formData.append('action', 'list_folders');
        formData.append('parent_id', parentId);
        if (driveId) formData.append('drive_id', driveId);
        
        const response = await fetch(CD_API_ENDPOINT, { method: 'POST', body: formData });
        const result = await response.json();
        
        cdShowLoader(false);
        if (result.success) {
            if (result.data.length === 0) {
                document.getElementById('cdEmpty').style.display = 'flex';
            } else {
                result.data.forEach(f => {
                    cdRenderItem(f.id, f.name, f.url, f.icon, false);
                });
            }
        } else {
            alert('Error: ' + result.error);
        }
    } catch (e) {
        cdShowLoader(false);
        console.error(e);
        alert('Error de red al cargar carpetas.');
    }
}

function cdRenderItem(id, name, url, iconUrl, isDriveItem) {
    const grid = document.getElementById('cdGrid');
    
    const card = document.createElement('div');
    card.className = 'cd-folder-card drive-item';
    
    card.innerHTML = `
        <div class="folder-icon" style="margin-bottom: 5px;">
            <div class="folder-back"></div>
            <div class="folder-tab folder-tab-1"></div>
            <div class="folder-tab folder-tab-2"></div>
            <div class="folder-tab folder-tab-3"></div>
            <div class="folder-paper"></div>
            <div class="folder-front" style="background: ${isDriveItem ? 'linear-gradient(180deg, #a78bfa 0%, #8b5cf6 100%)' : 'var(--folder-front-color)'};"></div>
        </div>
        <div class="cd-folder-name" title="${name}">${name}</div>
    `;
    
    let clickTimeout = null;
    let clickCount = 0;

    card.addEventListener('click', (e) => {
        clickCount++;
        
        if (clickCount === 1) {
            // Seleccionar (Single tap/click)
            document.querySelectorAll('.cd-folder-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            
            cdSelectedFolder = {
                id: id,
                name: name,
                url: url
            };
            cdUpdateSelectionUI();
            
            // Iniciar ventana para el doble click
            clickTimeout = setTimeout(() => {
                clickCount = 0;
            }, 350);
        } else if (clickCount === 2) {
            // Navegar adentro (Double tap/click)
            clearTimeout(clickTimeout);
            clickCount = 0;
            cdNavigateTo(id, name, isDriveItem);
        }
    });
    
    grid.appendChild(card);
}

async function cdCreateFolder() {
    const folderName = prompt('Nombre de la nueva carpeta:');
    if (!folderName) return;
    
    cdShowLoader(true);
    try {
        const parentId = cdCurrentPath[cdCurrentPath.length - 1].id;
        const formData = new FormData();
        formData.append('action', 'create_folder');
        formData.append('parent_id', parentId);
        formData.append('folder_name', folderName);
        
        const response = await fetch(CD_API_ENDPOINT, { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.success) {
            await cdFetchFolders(parentId, cdCurrentDriveId);
        } else {
            cdShowLoader(false);
            alert('Error: ' + result.error);
        }
    } catch (e) {
        cdShowLoader(false);
        console.error(e);
        alert('Error de red al crear la carpeta.');
    }
}
</script>
