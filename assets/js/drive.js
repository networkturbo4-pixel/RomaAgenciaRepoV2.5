// assets/js/drive.js

const DriveExplorer = (function() {
    let state = {
        containerId: null,
        rootFolderId: null,
        currentFolderId: null,
        currentFolder: null,
        selectedFolder: null, // { id, name }
        breadcrumbs: [],
        readonly: false,
        onFileClick: null, // callback for when a file is clicked (to download or view)
        onFolderSelect: null // callback for when a folder is selected to be linked
    };

    let DOM = {};

    function escapeHtml(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    async function apiRequest(action, data = {}) {
        if (DOM.loader) DOM.loader.classList.add('active');
        const formData = new URLSearchParams();
        formData.append('action', action);
        for (const key in data) {
            formData.append(key, data[key]);
        }

        try {
            const res = await fetch('modules/drive/ajax_drive.php', {
                method: 'POST',
                body: formData
            });
            const json = await res.json();
            if (DOM.loader) DOM.loader.classList.remove('active');
            if (!json.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.error });
                } else {
                    alert('Error: ' + json.error);
                }
                return null;
            }
            return json;
        } catch (e) {
            if (DOM.loader) DOM.loader.classList.remove('active');
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: e.message });
            } else {
                alert('Error de conexión: ' + e.message);
            }
            return null;
        }
    }

    function renderBreadcrumbs() {
        if (!DOM.breadcrumbs) return;
        DOM.breadcrumbs.innerHTML = '';
        
        // Add Root
        const rootItem = document.createElement('div');
        rootItem.className = 'drive-breadcrumb-item';
        rootItem.innerHTML = '<i class="ph ph-house"></i> Inicio';
        rootItem.onclick = () => loadFolder(state.rootFolderId);
        DOM.breadcrumbs.appendChild(rootItem);

        if (state.currentFolderId !== state.rootFolderId && state.currentFolder && state.currentFolder.name) {
            const separator = document.createElement('span');
            separator.className = 'drive-breadcrumb-separator';
            separator.innerHTML = '<i class="ph ph-caret-right"></i>';
            DOM.breadcrumbs.appendChild(separator);

            const currItem = document.createElement('div');
            currItem.className = 'drive-breadcrumb-item active';
            currItem.innerText = state.currentFolder.name;
            DOM.breadcrumbs.appendChild(currItem);
        }
    }

    function selectFolder(id, name, element) {
        if (DOM.grid) {
            const allItems = DOM.grid.querySelectorAll('.drive-item');
            allItems.forEach(el => el.classList.remove('selected'));
        }
        
        if (element) {
            element.classList.add('selected');
        }
        state.selectedFolder = { id: id, name: name };
        updateSelectionUI();
    }

    function updateSelectionUI() {
        const footer = document.getElementById(`drive-footer-${state.containerId}`);
        const badge = document.getElementById(`drive-selected-badge-${state.containerId}`);
        const openBtn = document.getElementById(`btn-open-selected-${state.containerId}`);
        const confirmBtn = document.getElementById(`btn-confirm-select-${state.containerId}`);
        const confirmText = document.getElementById(`btn-confirm-select-text-${state.containerId}`);
        const selectCurrentBtn = document.getElementById(`btn-select-current-${state.containerId}`);

        if (!state.onFolderSelect) {
            if (footer) footer.style.display = 'none';
            if (selectCurrentBtn) selectCurrentBtn.style.display = 'none';
            return;
        }

        if (footer) footer.style.display = 'flex';
        if (selectCurrentBtn) selectCurrentBtn.style.display = 'inline-flex';

        if (state.selectedFolder) {
            if (badge) {
                badge.innerHTML = `<i class="ph ph-folder-check" style="color:#10b981; font-size:1.15rem;"></i> Carpeta seleccionada: <strong>${escapeHtml(state.selectedFolder.name)}</strong>`;
            }
            if (openBtn) {
                openBtn.style.display = 'inline-flex';
            }
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.className = 'drive-btn drive-btn-success';
            }
            if (confirmText) {
                let name = state.selectedFolder.name;
                if (name.length > 22) name = name.substring(0, 20) + '...';
                confirmText.textContent = `Vincular "${name}"`;
            }
        } else {
            const currName = state.currentFolder && state.currentFolder.name ? state.currentFolder.name : 'Inicio';
            if (badge) {
                badge.innerHTML = `<i class="ph ph-folder" style="color:#3b82f6; font-size:1.15rem;"></i> Carpeta actual: <strong>${escapeHtml(currName)}</strong> <span style="font-size:12px; opacity:0.75; font-weight:normal;">(o haz clic en una carpeta para seleccionarla)</span>`;
            }
            if (openBtn) {
                openBtn.style.display = 'none';
            }
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.className = 'drive-btn drive-btn-primary';
            }
            if (confirmText) {
                let name = currName;
                if (name.length > 22) name = name.substring(0, 20) + '...';
                confirmText.textContent = `Vincular carpeta actual ("${name}")`;
            }
        }
    }

    function confirmSelection() {
        if (!state.onFolderSelect) return;
        const target = state.selectedFolder 
            || (state.currentFolder ? { id: state.currentFolderId, name: state.currentFolder.name } : { id: state.rootFolderId, name: 'Inicio' });
        
        state.onFolderSelect(target.id, target.name);
        closeModal();
    }

    function openSelectedFolder() {
        if (state.selectedFolder) {
            loadFolder(state.selectedFolder.id);
        }
    }

    function closeModal() {
        const modal = document.getElementById('global-drive-modal');
        if (modal) modal.classList.remove('active');
        state.selectedFolder = null;
        updateSelectionUI();
    }

    function renderItem(item) {
        const isFolder = item.mimeType === 'application/vnd.google-apps.folder';
        const div = document.createElement('div');
        div.className = 'drive-item';
        div.dataset.id = item.id;
        div.dataset.type = isFolder ? 'folder' : 'file';
        div.dataset.name = item.name;
        div.dataset.link = item.webViewLink;
        div.dataset.download = item.webContentLink;

        if (isFolder) {
            div.innerHTML = `
                <div class="folder-icon">
                    <div class="folder-back"></div>
                    <div class="folder-tab folder-tab-1"></div>
                    <div class="folder-tab folder-tab-2"></div>
                    <div class="folder-tab folder-tab-3"></div>
                    <div class="folder-paper"></div>
                    <div class="folder-front"></div>
                </div>
                <div class="item-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
            `;
            // Click to select
            div.onclick = (e) => {
                e.stopPropagation();
                selectFolder(item.id, item.name, div);
            };
            // Double click to open folder
            div.ondblclick = (e) => {
                e.stopPropagation();
                loadFolder(item.id);
            };
        } else {
            // Check if it's an image
            const isImage = item.mimeType.startsWith('image/');
            const icon = isImage ? '<i class="ph ph-image"></i>' : '<i class="ph ph-file-text"></i>';
            div.innerHTML = `
                <div class="file-icon">
                    ${item.iconLink ? `<img src="${item.iconLink}" style="width:24px;height:24px;">` : icon}
                </div>
                <div class="item-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
            `;
            div.onclick = (e) => {
                e.stopPropagation();
                if (DOM.grid) {
                    const allItems = DOM.grid.querySelectorAll('.drive-item');
                    allItems.forEach(el => el.classList.remove('selected'));
                }
                div.classList.add('selected');
                state.selectedFolder = null;
                updateSelectionUI();
            };
            div.ondblclick = () => {
                if (state.onFileClick) state.onFileClick(item);
                else window.open(item.webViewLink, '_blank');
            };
        }

        // Context Menu
        if (!state.readonly) {
            div.oncontextmenu = (e) => {
                e.preventDefault();
                showContextMenu(e, item);
            };
        }

        return div;
    }

    async function loadFolder(folderId) {
        state.currentFolderId = folderId;
        state.selectedFolder = null;
        updateSelectionUI();

        const res = await apiRequest('list', { folderId: folderId });
        if (res) {
            state.currentFolder = res.currentFolder;
            renderBreadcrumbs();
            updateSelectionUI();
            
            DOM.grid.innerHTML = '';
            if (!res.files || res.files.length === 0) {
                DOM.grid.innerHTML = `
                    <div class="drive-empty" style="grid-column: 1 / -1; text-align:center; padding:3rem 1rem; color:var(--text-muted, #94a3b8);">
                        <i class="ph ph-folder-open" style="font-size:3rem; margin-bottom:0.75rem; opacity:0.6;"></i>
                        <p>Esta carpeta está vacía</p>
                    </div>
                `;
            } else {
                // Render folders first, then files
                const folders = res.files.filter(f => f.mimeType === 'application/vnd.google-apps.folder');
                const files = res.files.filter(f => f.mimeType !== 'application/vnd.google-apps.folder');
                
                folders.forEach(f => DOM.grid.appendChild(renderItem(f)));
                files.forEach(f => DOM.grid.appendChild(renderItem(f)));
            }
        }
    }

    // Context Menu Logic
    let currentContextItem = null;
    function showContextMenu(e, item) {
        currentContextItem = item;
        DOM.contextMenu.classList.add('active');
        
        // Show or hide specific options based on type
        const isFolder = item.mimeType === 'application/vnd.google-apps.folder';
        const selectBtn = document.getElementById(`ctx-select-${state.containerId}`);
        if (selectBtn) {
            selectBtn.style.display = (isFolder && state.onFolderSelect) ? 'flex' : 'none';
        }
        
        // Position
        let x = e.clientX;
        let y = e.clientY;
        
        if (x + 160 > window.innerWidth) x -= 160;
        if (y + 150 > window.innerHeight) y -= 150;
        
        DOM.contextMenu.style.left = `${x}px`;
        DOM.contextMenu.style.top = `${y}px`;
    }

    document.addEventListener('click', () => {
        if (DOM.contextMenu) DOM.contextMenu.classList.remove('active');
    });

    async function createFolder() {
        if (typeof Swal === 'undefined') {
            const folderName = prompt('Nombre de la nueva carpeta:');
            if (folderName) {
                const res = await apiRequest('create_folder', {
                    parentFolderId: state.currentFolderId,
                    folderName: folderName
                });
                if (res) loadFolder(state.currentFolderId);
            }
            return;
        }

        const { value: folderName } = await Swal.fire({
            title: 'Nueva Carpeta',
            input: 'text',
            inputPlaceholder: 'Nombre de la carpeta',
            showCancelButton: true,
            confirmButtonText: 'Crear',
            cancelButtonText: 'Cancelar'
        });

        if (folderName) {
            const res = await apiRequest('create_folder', {
                parentFolderId: state.currentFolderId,
                folderName: folderName
            });
            if (res) {
                loadFolder(state.currentFolderId);
            }
        }
    }

    async function renameItem() {
        if (!currentContextItem) return;
        if (typeof Swal === 'undefined') {
            const newName = prompt('Nuevo nombre:', currentContextItem.name);
            if (newName && newName !== currentContextItem.name) {
                const res = await apiRequest('rename', {
                    fileId: currentContextItem.id,
                    newName: newName
                });
                if (res) loadFolder(state.currentFolderId);
            }
            return;
        }

        const { value: newName } = await Swal.fire({
            title: 'Renombrar',
            input: 'text',
            inputValue: currentContextItem.name,
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar'
        });

        if (newName && newName !== currentContextItem.name) {
            const res = await apiRequest('rename', {
                fileId: currentContextItem.id,
                newName: newName
            });
            if (res) {
                loadFolder(state.currentFolderId);
            }
        }
    }

    async function deleteItem() {
        if (!currentContextItem) return;
        if (typeof Swal === 'undefined') {
            if (confirm(`¿Eliminar "${currentContextItem.name}"?`)) {
                const res = await apiRequest('delete', { fileId: currentContextItem.id });
                if (res) loadFolder(state.currentFolderId);
            }
            return;
        }

        const result = await Swal.fire({
            title: '¿Estás seguro?',
            text: `Eliminarás "${currentContextItem.name}". Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            const res = await apiRequest('delete', { fileId: currentContextItem.id });
            if (res) {
                loadFolder(state.currentFolderId);
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Eliminado', showConfirmButton: false, timer: 2000 });
            }
        }
    }

    return {
        init: function(options) {
            state.containerId = options.containerId;
            state.rootFolderId = options.rootFolderId;
            state.readonly = options.readonly || false;
            state.onFileClick = options.onFileClick || null;
            state.onFolderSelect = options.onFolderSelect || null;

            const container = document.getElementById(state.containerId);
            if (!container) return;

            // Build UI
            container.innerHTML = `
                <div class="drive-explorer">
                    <div class="drive-loader" id="drive-loader-${state.containerId}">
                        <div class="spinner"></div>
                    </div>
                    <div class="drive-header">
                        <div class="drive-breadcrumbs" id="drive-breadcrumbs-${state.containerId}"></div>
                        <div class="drive-actions" id="drive-actions-${state.containerId}">
                            <button class="drive-btn drive-btn-success" id="btn-select-current-${state.containerId}" style="display:none;">
                                <i class="ph ph-check-circle"></i> Seleccionar Actual
                            </button>
                            ${!state.readonly ? `<button class="drive-btn drive-btn-primary" id="btn-create-folder-${state.containerId}"><i class="ph ph-folder-plus"></i> Nueva Carpeta</button>` : ''}
                        </div>
                    </div>
                    <div class="drive-grid-container" id="drive-grid-container-${state.containerId}">
                        <div class="drive-grid" id="drive-grid-${state.containerId}"></div>
                    </div>
                    
                    <div class="drive-footer" id="drive-footer-${state.containerId}" style="display:none;">
                        <div class="drive-selected-info" id="drive-selected-info-${state.containerId}">
                            <span class="drive-selected-badge" id="drive-selected-badge-${state.containerId}">
                                <i class="ph ph-folder"></i> Ninguna carpeta seleccionada
                            </span>
                        </div>
                        <div class="drive-footer-actions">
                            <button type="button" class="drive-btn drive-btn-secondary" id="btn-open-selected-${state.containerId}" style="display:none;" title="Entrar a esta carpeta">
                                <i class="ph ph-folder-open"></i> Abrir
                            </button>
                            <button type="button" class="drive-btn drive-btn-primary" id="btn-confirm-select-${state.containerId}">
                                <i class="ph ph-link"></i> <span id="btn-confirm-select-text-${state.containerId}">Vincular Carpeta</span>
                            </button>
                        </div>
                    </div>

                    ${!state.readonly ? `
                    <div class="drive-context-menu" id="drive-context-${state.containerId}">
                        <div class="context-menu-item" id="ctx-select-${state.containerId}" style="display:none;"><i class="ph ph-check-circle"></i> Seleccionar Carpeta</div>
                        <div class="context-menu-item" id="ctx-rename-${state.containerId}"><i class="ph ph-pencil-simple"></i> Renombrar</div>
                        <div class="context-menu-item danger" id="ctx-delete-${state.containerId}"><i class="ph ph-trash"></i> Eliminar</div>
                    </div>
                    ` : ''}
                </div>
            `;

            DOM.loader = document.getElementById(`drive-loader-${state.containerId}`);
            DOM.breadcrumbs = document.getElementById(`drive-breadcrumbs-${state.containerId}`);
            DOM.grid = document.getElementById(`drive-grid-${state.containerId}`);
            
            // Grid container background click deselects active item
            const gridContainer = document.getElementById(`drive-grid-container-${state.containerId}`);
            if (gridContainer) {
                gridContainer.addEventListener('click', (e) => {
                    if (e.target === gridContainer || e.target === DOM.grid) {
                        const allItems = DOM.grid.querySelectorAll('.drive-item');
                        allItems.forEach(el => el.classList.remove('selected'));
                        state.selectedFolder = null;
                        updateSelectionUI();
                    }
                });
            }

            // Footer buttons
            const confirmBtn = document.getElementById(`btn-confirm-select-${state.containerId}`);
            if (confirmBtn) {
                confirmBtn.addEventListener('click', confirmSelection);
            }
            const openSelectedBtn = document.getElementById(`btn-open-selected-${state.containerId}`);
            if (openSelectedBtn) {
                openSelectedBtn.addEventListener('click', openSelectedFolder);
            }
            const selectCurrentBtn = document.getElementById(`btn-select-current-${state.containerId}`);
            if (selectCurrentBtn) {
                selectCurrentBtn.addEventListener('click', () => {
                    state.selectedFolder = null;
                    confirmSelection();
                });
            }

            if (!state.readonly) {
                DOM.contextMenu = document.getElementById(`drive-context-${state.containerId}`);
                if (DOM.contextMenu) {
                    document.body.appendChild(DOM.contextMenu);
                }
                const createBtn = document.getElementById(`btn-create-folder-${state.containerId}`);
                if (createBtn) createBtn.addEventListener('click', createFolder);
                const renameBtn = document.getElementById(`ctx-rename-${state.containerId}`);
                if (renameBtn) renameBtn.addEventListener('click', renameItem);
                const delBtn = document.getElementById(`ctx-delete-${state.containerId}`);
                if (delBtn) delBtn.addEventListener('click', deleteItem);
                
                const selectCtxBtn = document.getElementById(`ctx-select-${state.containerId}`);
                if (selectCtxBtn) {
                    selectCtxBtn.addEventListener('click', () => {
                        if (state.onFolderSelect && currentContextItem) {
                            state.onFolderSelect(currentContextItem.id, currentContextItem.name);
                            if (DOM.contextMenu) DOM.contextMenu.classList.remove('active');
                            closeModal();
                        }
                    });
                }
            }

            updateSelectionUI();

            // Load initial folder
            if (!options.lazyLoad) {
                loadFolder(state.rootFolderId);
            }
        },
        openGlobalModal: function() {
            const modal = document.getElementById('global-drive-modal');
            if (modal) {
                modal.classList.add('active');
                if (!state.currentFolderId) {
                    loadFolder(state.rootFolderId);
                } else {
                    updateSelectionUI();
                }
            }
        },
        closeModal: closeModal,
        confirmSelection: confirmSelection,
        openSelectedFolder: openSelectedFolder,
        setOnFolderSelect: function(callback) {
            state.onFolderSelect = callback;
            state.selectedFolder = null;
            updateSelectionUI();
        }
    };
})();
