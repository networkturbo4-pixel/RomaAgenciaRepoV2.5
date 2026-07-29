// assets/js/drive.js

const DriveExplorer = (function() {
    let state = {
        containerId: null,
        rootFolderId: null,
        currentFolderId: null,
        breadcrumbs: [],
        readonly: false,
        onFileClick: null, // callback for when a file is clicked (to download or view)
        onFolderSelect: null // callback for when a folder is selected to be linked
    };

    let DOM = {};

    async function apiRequest(action, data = {}) {
        DOM.loader.classList.add('active');
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
            DOM.loader.classList.remove('active');
            if (!json.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error });
                return null;
            }
            return json;
        } catch (e) {
            DOM.loader.classList.remove('active');
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: e.message });
            return null;
        }
    }

    function renderBreadcrumbs() {
        DOM.breadcrumbs.innerHTML = '';
        
        // Add Root
        const rootItem = document.createElement('div');
        rootItem.className = 'drive-breadcrumb-item';
        rootItem.innerHTML = '<i class="ph ph-house"></i> Inicio';
        rootItem.onclick = () => loadFolder(state.rootFolderId);
        DOM.breadcrumbs.appendChild(rootItem);

        // We only have the immediate parent from the backend right now. 
        // For a full path, we'd need more data, but let's keep it simple.
        // The backend returns currentFolder.
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
                <div class="item-name" title="${item.name}">${item.name}</div>
            `;
            div.ondblclick = () => loadFolder(item.id);
        } else {
            // Check if it's an image
            const isImage = item.mimeType.startsWith('image/');
            const icon = isImage ? '<i class="ph ph-image"></i>' : '<i class="ph ph-file-text"></i>';
            div.innerHTML = `
                <div class="file-icon">
                    ${item.iconLink ? `<img src="${item.iconLink}" style="width:24px;height:24px;">` : icon}
                </div>
                <div class="item-name" title="${item.name}">${item.name}</div>
            `;
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
        const res = await apiRequest('list', { folderId: folderId });
        if (res) {
            state.currentFolder = res.currentFolder;
            renderBreadcrumbs();
            
            DOM.grid.innerHTML = '';
            if (!res.files || res.files.length === 0) {
                DOM.grid.innerHTML = `
                    <div class="drive-empty" style="grid-column: 1 / -1;">
                        <i class="ph ph-folder-open"></i>
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
        const { value: folderName } = await Swal.fire({
            title: 'Nueva Carpeta',
            input: 'text',
            inputPlaceholder: 'Nombre de la carpeta',
            showCancelButton: true,
            confirmButtonText: 'Crear'
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
        const { value: newName } = await Swal.fire({
            title: 'Renombrar',
            input: 'text',
            inputValue: currentContextItem.name,
            showCancelButton: true,
            confirmButtonText: 'Guardar'
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
        const result = await Swal.fire({
            title: '¿Estás seguro?',
            text: `Eliminarás "${currentContextItem.name}". Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Sí, eliminar'
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
                            ${state.onFolderSelect ? `<button class="drive-btn drive-btn-success" id="btn-select-current-${state.containerId}"><i class="ph ph-check-circle"></i> Seleccionar Actual</button>` : ''}
                            ${!state.readonly ? `<button class="drive-btn drive-btn-primary" id="btn-create-folder-${state.containerId}"><i class="ph ph-folder-plus"></i> Nueva Carpeta</button>` : ''}
                        </div>
                    </div>
                    <div class="drive-grid-container">
                        <div class="drive-grid" id="drive-grid-${state.containerId}"></div>
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
            
            if (!state.readonly) {
                DOM.contextMenu = document.getElementById(`drive-context-${state.containerId}`);
                document.body.appendChild(DOM.contextMenu); // Move to body to avoid stacking context issues
                document.getElementById(`btn-create-folder-${state.containerId}`).addEventListener('click', createFolder);
                document.getElementById(`ctx-rename-${state.containerId}`).addEventListener('click', renameItem);
                document.getElementById(`ctx-delete-${state.containerId}`).addEventListener('click', deleteItem);
                
                const selectBtn = document.getElementById(`ctx-select-${state.containerId}`);
                if (selectBtn) {
                    selectBtn.addEventListener('click', () => {
                        if (state.onFolderSelect && currentContextItem) {
                            state.onFolderSelect(currentContextItem.id, currentContextItem.name);
                            DOM.contextMenu.classList.remove('active');
                        }
                    });
                }
                
                const selectCurrentBtn = document.getElementById(`btn-select-current-${state.containerId}`);
                if (selectCurrentBtn) {
                    selectCurrentBtn.addEventListener('click', () => {
                        if (state.onFolderSelect) {
                            state.onFolderSelect(state.currentFolderId, state.currentFolder ? state.currentFolder.name : 'Carpeta Raíz');
                        }
                    });
                }
            }

            // Load initial folder
            if (!options.lazyLoad) {
                loadFolder(state.rootFolderId);
            }
        },
        openGlobalModal: function() {
            const modal = document.getElementById('global-drive-modal');
            if (modal) {
                modal.classList.add('active');
                // Ensure it's initialized if it wasn't
                if (!state.currentFolderId) {
                    loadFolder(state.rootFolderId);
                }
            }
        },
        setOnFolderSelect: function(callback) {
            state.onFolderSelect = callback;
        }
    };
})();
