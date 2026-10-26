// assets/js/modules/clients.js
const ClientModule = (function() {
    let brandsArray = [];
    let deletedBrands = [];
    let modalEl;
    let currentView = localStorage.getItem('roma_clients_view') || 'list';
    let currentFilter = 'all';
    let currentSort = 'recent';
    let searchDebounceTimer = null;
    let isSearching = false;

    const avatarColors = ['#4f46e5','#0891b2','#059669','#d97706','#dc2626','#7c3aed','#db2777','#2563eb','#0d9488','#ea580c'];

    function getAvatarColor(str) {
        if (!str) return avatarColors[0];
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        return avatarColors[Math.abs(hash) % avatarColors.length];
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    function init() {
        modalEl = document.getElementById('clientModal');

        // Setup dropzone and clipboard paste handlers
        setupDragAndDrop();
        setupPasteListener();

        // Apply initial view (list or grid)
        const savedView = localStorage.getItem('roma_clients_view') || 'list';
        setView(savedView, document.querySelector(`.clients-view-toggle button[data-view="${savedView}"]`));

        // Setup global keyboard shortcut: Ctrl+K or / to focus search
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                const searchInput = document.getElementById('clientSearch');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            } else if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                const searchInput = document.getElementById('clientSearch');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            } else if (e.key === 'Escape') {
                if (modalEl && modalEl.classList.contains('active')) {
                    closeModal();
                }
            }
        });

        // Close multiselects when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.custom-multiselect')) {
                closeAllMultiselects();
            }
        });
    }

    // AJAX Live Search with Debounce
    function onSearchInput(val) {
        const clearBtn = document.getElementById('searchClearBtn');
        if (clearBtn) {
            clearBtn.style.display = val.trim().length > 0 ? 'flex' : 'none';
        }

        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            performAjaxSearch();
        }, 250);
    }

    function clearSearch() {
        const searchInput = document.getElementById('clientSearch');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
        const clearBtn = document.getElementById('searchClearBtn');
        if (clearBtn) {
            clearBtn.style.display = 'none';
        }
        performAjaxSearch();
    }

    function setFilter(filter, el) {
        currentFilter = filter;
        document.querySelectorAll('.client-filter-pill').forEach(pill => pill.classList.remove('active'));
        if (el) el.classList.add('active');
        performAjaxSearch();
    }

    function setSort(sortVal) {
        currentSort = sortVal;
        performAjaxSearch();
    }

    async function performAjaxSearch() {
        const searchInput = document.getElementById('clientSearch');
        const query = searchInput ? searchInput.value.trim() : '';
        const spinner = document.getElementById('searchSpinner');

        if (spinner) spinner.style.display = 'flex';
        isSearching = true;

        try {
            const url = `index.php?module=clients&action=ajax_search_clients&q=${encodeURIComponent(query)}&filter=${encodeURIComponent(currentFilter)}&sort=${encodeURIComponent(currentSort)}`;
            const res = await fetch(url);
            const data = await res.json();

            if (data.success) {
                renderClients(data.clients, query);
                updateResultCounts(data.total, query);
            }
        } catch (err) {
            console.error('Error in AJAX search:', err);
        } finally {
            if (spinner) spinner.style.display = 'none';
            isSearching = false;
        }
    }

    function updateResultCounts(total, query) {
        const countBadge = document.getElementById('searchResultCount');
        if (countBadge) {
            if (query || currentFilter !== 'all') {
                countBadge.textContent = `${total} resultado${total === 1 ? '' : 's'}`;
                countBadge.style.display = 'inline-flex';
            } else {
                countBadge.textContent = `${total} clientes`;
                countBadge.style.display = 'inline-flex';
            }
        }
    }

    function setView(view, btn) {
        currentView = view || 'list';
        localStorage.setItem('roma_clients_view', currentView);

        document.querySelectorAll('.clients-view-toggle button').forEach(b => b.classList.remove('active'));
        if (btn) {
            btn.classList.add('active');
        } else {
            const activeBtn = document.querySelector(`.clients-view-toggle button[data-view="${currentView}"]`);
            if (activeBtn) activeBtn.classList.add('active');
        }

        const listView = document.getElementById('clientsListView');
        const gridView = document.getElementById('clientsGridView');

        if (listView && gridView) {
            if (currentView === 'list') {
                listView.style.display = 'flex';
                gridView.style.display = 'none';
            } else {
                listView.style.display = 'none';
                gridView.style.display = 'grid';
            }
        }
    }

    // Render Clients in both List and Grid views
    function renderClients(clients, query) {
        const listView = document.getElementById('clientsListView');
        const gridView = document.getElementById('clientsGridView');

        if (!listView || !gridView) return;

        if (!clients || clients.length === 0) {
            const emptyHtml = `
                <div class="clients-empty-state">
                    <div class="empty-icon-circle">
                        <i class="ph ph-users-three"></i>
                    </div>
                    <h3>No se encontraron clientes</h3>
                    <p>${query ? `No hay resultados que coincidan con "${escapeHtml(query)}".` : 'No hay clientes registrados con los filtros seleccionados.'}</p>
                    <div class="empty-actions">
                        ${query || currentFilter !== 'all' ? `
                            <button type="button" class="btn btn-outline" onclick="ClientModule.resetFilters()">
                                <i class="ph ph-arrow-counter-clockwise"></i> Restablecer filtros
                            </button>
                        ` : `
                            <button type="button" class="btn btn-primary" onclick="ClientModule.openModal()">
                                <i class="ph ph-plus"></i> Registrar Primer Cliente
                            </button>
                        `}
                    </div>
                </div>
            `;
            listView.innerHTML = emptyHtml;
            gridView.innerHTML = emptyHtml;
            return;
        }

        // Render List View
        let listHtml = '';
        clients.forEach((client) => {
            const initials = escapeHtml(client.name.substring(0, 2).toUpperCase());
            const avatarBg = getAvatarColor(client.name);
            const brandNames = client.brands ? client.brands.split('||') : [];
            const brandLogos = client.logos ? client.logos.split('||') : [];
            const brandMemberships = client.memberships ? client.memberships.split('||') : [];
            const hasAnyMembership = brandMemberships.some(m => m === '1' || m === 1);
            const cleanWa = (client.whatsapp || '').replace(/[^0-9]/g, '');

            let brandsBadgesHtml = '';
            if (brandNames.length > 0) {
                const maxShow = 3;
                brandNames.slice(0, maxShow).forEach((bName, idx) => {
                    const bLogo = brandLogos[idx] || '';
                    const hasM = brandMemberships[idx] === '1';
                    brandsBadgesHtml += `
                        <span class="client-brand-chip ${hasM ? 'with-membership' : ''}" title="${escapeHtml(bName)}">
                            ${bLogo ? `<img src="${escapeHtml(bLogo)}" alt="" class="brand-chip-img">` : `<i class="ph ph-briefcase"></i>`}
                            <span class="brand-chip-name">${escapeHtml(bName)}</span>
                            ${hasM ? `<i class="ph-fill ph-star brand-star-badge" title="Membresía Activa"></i>` : ''}
                        </span>
                    `;
                });
                if (brandNames.length > maxShow) {
                    brandsBadgesHtml += `<span class="client-brand-more">+${brandNames.length - maxShow}</span>`;
                }
            } else {
                brandsBadgesHtml = `<span class="no-brands-label"><i class="ph ph-minus"></i> Sin marcas</span>`;
            }

            listHtml += `
                <div class="client-row-card" onclick="ClientModule.editClient(${client.id})">
                    <div class="client-row-avatar-col">
                        <div class="client-saas-avatar" style="background: ${avatarBg};">
                            ${initials}
                        </div>
                    </div>

                    <div class="client-row-name-col">
                        <div class="client-main-name">
                            <span>${escapeHtml(client.name)}</span>
                            ${hasAnyMembership ? `<span class="membership-pill-badge"><i class="ph-fill ph-star"></i> Membresía</span>` : ''}
                        </div>
                        <div class="client-meta-pills">
                            ${client.dni ? `<span class="client-dni-badge"><i class="ph ph-identification-card"></i> ${escapeHtml(client.dni)}</span>` : ''}
                            ${(client.portal_enabled == 1 || client.portal_enabled === '1') ? `
                                <span class="client-portal-badge" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 10.5px; font-weight: 600; color: #10b981; background: color-mix(in srgb, #10b981 12%, transparent); padding: 1px 6px; border-radius: 4px; border: 1px solid color-mix(in srgb, #10b981 25%, transparent);" title="Portal de cliente activo">
                                    <i class="ph-fill ph-check-circle"></i> Portal Activo
                                </span>
                            ` : ''}
                            ${client.drive_folder_id ? `
                                <a href="https://drive.google.com/drive/folders/${escapeHtml(client.drive_folder_id)}" target="_blank" onclick="event.stopPropagation();" class="client-drive-link" title="Abrir Google Drive">
                                    <i class="ph ph-google-drive-logo"></i> Portal Drive
                                </a>
                            ` : ''}
                        </div>
                    </div>

                    <div class="client-row-contact-col">
                        ${client.whatsapp ? `
                            <a href="https://wa.me/${cleanWa}" target="_blank" onclick="event.stopPropagation();" class="contact-pill whatsapp-pill" title="Conversar en WhatsApp">
                                <i class="ph-fill ph-whatsapp-logo"></i>
                                <span>${escapeHtml(client.whatsapp)}</span>
                            </a>
                        ` : `<span class="contact-empty-tag"><i class="ph ph-whatsapp-logo"></i> Sin WhatsApp</span>`}
                        
                        ${client.email ? `
                            <a href="mailto:${escapeHtml(client.email)}" onclick="event.stopPropagation();" class="contact-pill email-pill" title="${escapeHtml(client.email)}">
                                <i class="ph ph-envelope-simple"></i>
                                <span>${escapeHtml(client.email)}</span>
                            </a>
                        ` : ''}
                    </div>

                    <div class="client-row-brands-col">
                        <div class="client-brands-container">
                            ${brandsBadgesHtml}
                        </div>
                    </div>

                    <div class="client-row-actions-col" onclick="event.stopPropagation();">
                        <a href="index.php?module=clients&action=social_auth&client_id=${client.id}" class="action-btn-saas" title="Conexiones y Redes Sociales">
                            <i class="ph ph-share-network"></i>
                        </a>
                        <button type="button" class="action-btn-saas" onclick="ClientModule.editClient(${client.id})" title="Editar Cliente">
                            <i class="ph ph-pencil-simple"></i>
                        </button>
                        <button type="button" class="action-btn-saas delete-btn" onclick="ClientModule.deleteClient(${client.id})" title="Eliminar Cliente">
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        listView.innerHTML = listHtml;

        // Render Grid View
        let gridHtml = '';
        clients.forEach((client) => {
            const initials = escapeHtml(client.name.substring(0, 2).toUpperCase());
            const avatarBg = getAvatarColor(client.name);
            const brandNames = client.brands ? client.brands.split('||') : [];
            const brandLogos = client.logos ? client.logos.split('||') : [];
            const brandMemberships = client.memberships ? client.memberships.split('||') : [];
            const hasAnyMembership = brandMemberships.some(m => m === '1' || m === 1);
            const cleanWa = (client.whatsapp || '').replace(/[^0-9]/g, '');

            let brandsBadgesHtml = '';
            if (brandNames.length > 0) {
                brandNames.forEach((bName, idx) => {
                    const bLogo = brandLogos[idx] || '';
                    const hasM = brandMemberships[idx] === '1';
                    brandsBadgesHtml += `
                        <span class="client-brand-chip ${hasM ? 'with-membership' : ''}">
                            ${bLogo ? `<img src="${escapeHtml(bLogo)}" alt="" class="brand-chip-img">` : `<i class="ph ph-briefcase"></i>`}
                            <span class="brand-chip-name">${escapeHtml(bName)}</span>
                            ${hasM ? `<i class="ph-fill ph-star brand-star-badge" title="Membresía"></i>` : ''}
                        </span>
                    `;
                });
            } else {
                brandsBadgesHtml = `<span class="no-brands-label"><i class="ph ph-minus"></i> Sin marcas asociadas</span>`;
            }

            gridHtml += `
                <div class="client-grid-card ${hasAnyMembership ? 'has-membership' : ''}" onclick="ClientModule.editClient(${client.id})">
                    <div class="grid-card-header">
                        <div class="client-saas-avatar" style="background: ${avatarBg};">
                            ${initials}
                        </div>
                        <div class="grid-card-title-group">
                            <div class="grid-card-name">${escapeHtml(client.name)}</div>
                            <div class="grid-card-badges">
                                ${client.dni ? `<span class="client-dni-badge"><i class="ph ph-identification-card"></i> ${escapeHtml(client.dni)}</span>` : ''}
                                ${(client.portal_enabled == 1 || client.portal_enabled === '1') ? `
                                    <span class="client-portal-badge" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 10px; font-weight: 600; color: #10b981; background: color-mix(in srgb, #10b981 12%, transparent); padding: 1px 5px; border-radius: 4px; border: 1px solid color-mix(in srgb, #10b981 25%, transparent);" title="Portal de cliente activo">
                                        <i class="ph-fill ph-check-circle"></i> Portal
                                    </span>
                                ` : ''}
                                ${hasAnyMembership ? `<span class="membership-pill-badge"><i class="ph-fill ph-star"></i> Membresía</span>` : ''}
                            </div>
                        </div>
                    </div>

                    <div class="grid-card-contacts">
                        ${client.whatsapp ? `
                            <a href="https://wa.me/${cleanWa}" target="_blank" onclick="event.stopPropagation();" class="contact-pill whatsapp-pill">
                                <i class="ph-fill ph-whatsapp-logo"></i>
                                <span>${escapeHtml(client.whatsapp)}</span>
                            </a>
                        ` : ''}
                        ${client.email ? `
                            <a href="mailto:${escapeHtml(client.email)}" onclick="event.stopPropagation();" class="contact-pill email-pill">
                                <i class="ph ph-envelope-simple"></i>
                                <span>${escapeHtml(client.email)}</span>
                            </a>
                        ` : ''}
                        ${client.drive_folder_id ? `
                            <a href="https://drive.google.com/drive/folders/${escapeHtml(client.drive_folder_id)}" target="_blank" onclick="event.stopPropagation();" class="client-drive-link">
                                <i class="ph ph-google-drive-logo"></i> Google Drive
                            </a>
                        ` : ''}
                    </div>

                    <div class="grid-card-brands-section">
                        <div class="grid-brands-title">Marcas Asociadas (${brandNames.length})</div>
                        <div class="grid-brands-list">
                            ${brandsBadgesHtml}
                        </div>
                    </div>

                    <div class="grid-card-footer" onclick="event.stopPropagation();">
                        <a href="index.php?module=clients&action=social_auth&client_id=${client.id}" class="action-btn-saas" title="Redes Sociales">
                            <i class="ph ph-share-network"></i>
                        </a>
                        <div class="grid-footer-actions">
                            <button type="button" class="action-btn-saas" onclick="ClientModule.editClient(${client.id})" title="Editar">
                                <i class="ph ph-pencil-simple"></i>
                            </button>
                            <button type="button" class="action-btn-saas delete-btn" onclick="ClientModule.deleteClient(${client.id})" title="Eliminar">
                                <i class="ph ph-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        gridView.innerHTML = gridHtml;
    }

    function resetFilters() {
        const searchInput = document.getElementById('clientSearch');
        if (searchInput) searchInput.value = '';
        const clearBtn = document.getElementById('searchClearBtn');
        if (clearBtn) clearBtn.style.display = 'none';

        currentFilter = 'all';
        document.querySelectorAll('.client-filter-pill').forEach(p => {
            p.classList.toggle('active', p.dataset.filter === 'all');
        });

        performAjaxSearch();
    }

    // Draft logo state for new brand
    let draftLogo = {
        file: null,
        url: null,
        previewUrl: null,
        sourceType: null, // 'file' | 'clipboard' | 'url'
        name: ''
    };

    let currentEditingBrandIndex = null;

    function dataURLtoFile(dataurl, filename) {
        try {
            const arr = dataurl.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            while (n--) {
                u8arr[n] = bstr.charCodeAt(n);
            }
            return new File([u8arr], filename, { type: mime });
        } catch(e) {
            console.error('Error parsing dataURL:', e);
            return null;
        }
    }

    function setDraftLogoFile(file, sourceType = 'file') {
        if (!file) return;
        if (file.type && !file.type.startsWith('image/')) {
            alert('Por favor selecciona un archivo de imagen válido (PNG, JPG, WEBP, SVG).');
            return;
        }
        draftLogo.file = file;
        draftLogo.url = null;
        draftLogo.previewUrl = URL.createObjectURL(file);
        draftLogo.sourceType = sourceType;
        draftLogo.name = file.name || (sourceType === 'clipboard' ? 'Captura_Portapapeles.png' : 'logotipo.png');
        renderDraftLogoUI();
    }

    function setDraftLogoUrl(url) {
        if (!url) return;
        draftLogo.file = null;
        draftLogo.url = url;
        draftLogo.previewUrl = url;
        draftLogo.sourceType = 'url';
        try {
            const parsed = new URL(url);
            draftLogo.name = parsed.pathname.split('/').pop() || parsed.hostname;
        } catch(e) {
            draftLogo.name = url.length > 32 ? url.substring(0, 32) + '...' : url;
        }
        renderDraftLogoUI();
    }

    function clearDraftLogo() {
        draftLogo = {
            file: null,
            url: null,
            previewUrl: null,
            sourceType: null,
            name: ''
        };
        const fileInput = document.getElementById('new_brand_logo_file');
        if (fileInput) fileInput.value = '';
        const urlInput = document.getElementById('brand_logo_url_input');
        if (urlInput) urlInput.value = '';
        const urlBar = document.getElementById('brandLogoUrlBar');
        if (urlBar) urlBar.style.display = 'none';
        renderDraftLogoUI();
    }

    function renderDraftLogoUI() {
        const emptyZone = document.getElementById('brandLogoEmptyZone');
        const previewCard = document.getElementById('brandLogoPreviewCard');
        const previewImg = document.getElementById('brandLogoPreviewImg');
        const sourceBadge = document.getElementById('brandLogoSourceBadge');
        const fileNameEl = document.getElementById('brandLogoFileName');
        const urlBar = document.getElementById('brandLogoUrlBar');

        if (!emptyZone || !previewCard) return;

        if (draftLogo.previewUrl) {
            emptyZone.style.display = 'none';
            if (urlBar) urlBar.style.display = 'none';
            previewCard.style.display = 'flex';
            if (previewImg) previewImg.src = draftLogo.previewUrl;
            if (fileNameEl) fileNameEl.textContent = draftLogo.name || 'Logotipo cargado';

            if (sourceBadge) {
                if (draftLogo.sourceType === 'clipboard') {
                    sourceBadge.innerHTML = '<i class="ph ph-clipboard-text"></i> Portapapeles';
                    sourceBadge.className = 'preview-source-badge clipboard';
                } else if (draftLogo.sourceType === 'url') {
                    sourceBadge.innerHTML = '<i class="ph ph-link"></i> Enlace Web';
                    sourceBadge.className = 'preview-source-badge url';
                } else {
                    sourceBadge.innerHTML = '<i class="ph ph-file-image"></i> Archivo local';
                    sourceBadge.className = 'preview-source-badge file';
                }
            }
        } else {
            emptyZone.style.display = 'flex';
            previewCard.style.display = 'none';
            if (previewImg) previewImg.src = '';
        }
    }

    function toggleLogoUrlInput(show = null) {
        const urlBar = document.getElementById('brandLogoUrlBar');
        if (!urlBar) return;
        const willShow = show !== null ? show : (urlBar.style.display === 'none' || !urlBar.style.display);
        urlBar.style.display = willShow ? 'flex' : 'none';
        if (willShow) {
            const input = document.getElementById('brand_logo_url_input');
            if (input) {
                input.focus();
                input.select();
            }
        }
    }

    function applyLogoUrl() {
        const input = document.getElementById('brand_logo_url_input');
        const val = input ? input.value.trim() : '';
        if (!val) {
            if (input) input.focus();
            return;
        }
        setDraftLogoUrl(val);
    }

    function handleLogoFileInput(input) {
        if (input.files && input.files[0]) {
            setDraftLogoFile(input.files[0], 'file');
        }
    }

    async function pasteLogoFromClipboard() {
        try {
            if (navigator.clipboard && navigator.clipboard.read) {
                const items = await navigator.clipboard.read();
                for (const item of items) {
                    for (const type of item.types) {
                        if (type.startsWith('image/')) {
                            const blob = await item.getType(type);
                            const file = new File([blob], 'clipboard_' + Date.now() + '.png', { type: blob.type || 'image/png' });
                            setDraftLogoFile(file, 'clipboard');
                            return;
                        }
                    }
                }
            }
            if (navigator.clipboard && navigator.clipboard.readText) {
                const text = await navigator.clipboard.readText();
                if (text) {
                    const trimmed = text.trim();
                    if (trimmed.startsWith('data:image/')) {
                        const file = dataURLtoFile(trimmed, 'clipboard_' + Date.now() + '.png');
                        if (file) setDraftLogoFile(file, 'clipboard');
                        return;
                    }
                    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
                        setDraftLogoUrl(trimmed);
                        return;
                    }
                }
            }
            alert('No se detectó ninguna imagen en el portapapeles. Copia una imagen o captura de pantalla (Ctrl+C / Win+Shift+S) y vuelve a intentar, o simplemente presiona Ctrl+V.');
        } catch (err) {
            console.warn('Clipboard read error:', err);
            alert('Haz clic en el recuadro del logotipo y presiona Ctrl+V para pegar la imagen.');
        }
    }

    function setupDragAndDrop() {
        const dropZone = document.getElementById('brandLogoEmptyZone');
        if (!dropZone) return;

        ['dragenter', 'dragover'].forEach(name => {
            dropZone.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(name => {
            dropZone.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.remove('dragover');
            });
        });

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                setDraftLogoFile(dt.files[0], 'file');
            }
        });
    }

    function setupPasteListener() {
        document.addEventListener('paste', (e) => {
            if (!modalEl || !modalEl.classList.contains('active')) return;

            const target = e.target;
            const isOtherTextInput = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA') && 
                target.id !== 'brand_logo_url_input' && target.id !== 'change_brand_logo_url_input';

            const clipboardData = e.clipboardData || window.clipboardData;
            if (!clipboardData) return;

            // 1. Try to find image blob in clipboard items
            const items = clipboardData.items;
            let imageFound = false;
            if (items) {
                for (let i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        const blob = items[i].getAsFile();
                        if (blob) {
                            imageFound = true;
                            e.preventDefault();
                            const file = new File([blob], 'clipboard_' + Date.now() + '.png', { type: blob.type || 'image/png' });
                            
                            const changeModal = document.getElementById('changeLogoModal');
                            if (changeModal && changeModal.classList.contains('active') && currentEditingBrandIndex !== null) {
                                updateExistingBrandLogo(currentEditingBrandIndex, file, null);
                                closeChangeLogoModal();
                            } else {
                                setDraftLogoFile(file, 'clipboard');
                            }
                            return;
                        }
                    }
                }
            }

            // 2. If no image blob and user is NOT typing in another text input (or is in URL input)
            if (!imageFound && (!isOtherTextInput || (target && (target.id === 'brand_logo_url_input' || target.id === 'change_brand_logo_url_input')))) {
                const text = clipboardData.getData('text/plain');
                if (text) {
                    const trimmed = text.trim();
                    if (trimmed.startsWith('data:image/')) {
                        e.preventDefault();
                        const file = dataURLtoFile(trimmed, 'clipboard_' + Date.now() + '.png');
                        if (file) {
                            const changeModal = document.getElementById('changeLogoModal');
                            if (changeModal && changeModal.classList.contains('active') && currentEditingBrandIndex !== null) {
                                updateExistingBrandLogo(currentEditingBrandIndex, file, null);
                                closeChangeLogoModal();
                            } else {
                                setDraftLogoFile(file, 'clipboard');
                            }
                        }
                    } else if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
                        if (target.id === 'brand_logo_url_input' || target.id === 'change_brand_logo_url_input' || /\.(png|jpe?g|webp|svg|gif)(\?.*)?$/i.test(trimmed)) {
                            e.preventDefault();
                            const changeModal = document.getElementById('changeLogoModal');
                            if (changeModal && changeModal.classList.contains('active') && currentEditingBrandIndex !== null) {
                                updateExistingBrandLogo(currentEditingBrandIndex, null, trimmed);
                                closeChangeLogoModal();
                            } else {
                                setDraftLogoUrl(trimmed);
                            }
                        }
                    }
                }
            }
        });
    }

    // Modal & Brands Logic
    function getSystemServices() {
        if (typeof window.SYSTEM_SERVICES !== 'undefined' && Array.isArray(window.SYSTEM_SERVICES)) {
            return window.SYSTEM_SERVICES;
        }
        if (typeof SYSTEM_SERVICES !== 'undefined' && Array.isArray(SYSTEM_SERVICES)) {
            return SYSTEM_SERVICES;
        }
        return [];
    }

    function renderServicesBadgesHtml(brandIndex, servicesIds) {
        if (!Array.isArray(servicesIds) || servicesIds.length === 0) {
            return '<span class="service-none-text"><i class="ph ph-plus-circle"></i> Asignar servicios...</span>';
        }
        const sysServices = getSystemServices();
        let html = '';
        servicesIds.forEach(id => {
            const found = sysServices.find(s => String(s.id) === String(id));
            const name = found ? found.name : `Servicio #${id}`;
            html += `
                <span class="service-selected-pill">
                    <span>${escapeHtml(name)}</span>
                    <button type="button" class="pill-remove-btn" onclick="ClientModule.removeBrandService(${brandIndex}, '${id}', event)" title="Quitar">&times;</button>
                </span>
            `;
        });
        return html;
    }

    function closeAllMultiselects() {
        document.querySelectorAll('.custom-multiselect-dropdown').forEach(dd => {
            dd.style.display = 'none';
        });
        document.querySelectorAll('.custom-multiselect').forEach(ms => {
            ms.classList.remove('open');
        });
    }

    function toggleMultiselect(brandIndex, event) {
        if (event) {
            event.stopPropagation();
        }
        const parent = document.getElementById('customMultiselect_' + brandIndex);
        const dropdown = document.getElementById('customMultiselectDropdown_' + brandIndex);
        if (!dropdown || !parent) return;

        const isOpened = dropdown.style.display === 'flex';
        closeAllMultiselects();
        if (!isOpened) {
            dropdown.style.display = 'flex';
            parent.classList.add('open');
            const searchInput = dropdown.querySelector('.multiselect-search-box input');
            if (searchInput) {
                searchInput.value = '';
                filterServicesList(brandIndex, '');
                setTimeout(() => searchInput.focus(), 60);
            }
        }
    }

    function onServiceToggle(brandIndex, serviceId, isChecked, checkboxEl) {
        const brand = brandsArray[brandIndex];
        if (!brand) return;
        if (!Array.isArray(brand.services_ids)) brand.services_ids = [];
        brand.services_ids = brand.services_ids.map(id => String(id));

        const strId = String(serviceId);
        if (isChecked) {
            if (!brand.services_ids.includes(strId)) {
                brand.services_ids.push(strId);
            }
        } else {
            brand.services_ids = brand.services_ids.filter(id => id !== strId);
        }

        if (checkboxEl && checkboxEl.closest('.custom-multiselect-option')) {
            checkboxEl.closest('.custom-multiselect-option').classList.toggle('selected', isChecked);
        }

        const badgesWrapper = document.getElementById('brandServicesBadges_' + brandIndex);
        if (badgesWrapper) {
            badgesWrapper.innerHTML = renderServicesBadgesHtml(brandIndex, brand.services_ids);
        }

        const countEl = document.getElementById('servicesCount_' + brandIndex);
        if (countEl) {
            countEl.textContent = `${brand.services_ids.length} seleccionados`;
        }
    }

    function removeBrandService(brandIndex, serviceId, event) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        const brand = brandsArray[brandIndex];
        if (!brand) return;
        if (!Array.isArray(brand.services_ids)) brand.services_ids = [];
        const strId = String(serviceId);
        brand.services_ids = brand.services_ids.filter(id => String(id) !== strId);

        const cb = document.querySelector(`.brand-service-cb-${brandIndex}[value="${strId}"]`);
        if (cb) {
            cb.checked = false;
            if (cb.closest('.custom-multiselect-option')) {
                cb.closest('.custom-multiselect-option').classList.remove('selected');
            }
        }

        const badgesWrapper = document.getElementById('brandServicesBadges_' + brandIndex);
        if (badgesWrapper) {
            badgesWrapper.innerHTML = renderServicesBadgesHtml(brandIndex, brand.services_ids);
        }

        const countEl = document.getElementById('servicesCount_' + brandIndex);
        if (countEl) {
            countEl.textContent = `${brand.services_ids.length} seleccionados`;
        }
    }

    function filterServicesList(brandIndex, query) {
        const list = document.getElementById('servicesOptionsList_' + brandIndex);
        if (!list) return;
        const q = (query || '').toLowerCase().trim();
        const options = list.querySelectorAll('.custom-multiselect-option');
        options.forEach(opt => {
            const name = opt.getAttribute('data-name') || '';
            opt.style.display = (!q || name.includes(q)) ? 'flex' : 'none';
        });
    }

    function renderBrands() {
        const container = document.getElementById('brandsList');
        if (!container) return;
        container.innerHTML = '';
        
        if (brandsArray.length === 0) {
            container.innerHTML = `
                <div class="brands-empty-state">
                    <i class="ph ph-briefcase"></i>
                    <span>No hay marcas registradas para este cliente.</span>
                </div>
            `;
            return;
        }

        const sysServices = getSystemServices();

        brandsArray.forEach((brand, index) => {
            const card = document.createElement('div');
            card.className = 'brand-editor-card';

            let imgHtml = `
                <div class="brand-preview-avatar default">
                    <i class="ph ph-image"></i>
                </div>
            `;
            
            if (brand.file) {
                const objectUrl = URL.createObjectURL(brand.file);
                imgHtml = `<img src="${objectUrl}" class="brand-preview-avatar">`;
            } else if (brand.logo) {
                imgHtml = `<img src="${escapeHtml(brand.logo)}" class="brand-preview-avatar">`;
            }

            // Normalize services_ids
            if (!Array.isArray(brand.services_ids)) {
                brand.services_ids = [];
            }
            brand.services_ids = brand.services_ids.map(id => String(id));

            // Generate options list
            let servicesListHtml = '';
            sysServices.forEach(s => {
                const isSelected = brand.services_ids.some(id => String(id) === String(s.id));
                const checkedAttr = isSelected ? 'checked' : '';
                const selectedClass = isSelected ? 'selected' : '';

                servicesListHtml += `
                    <label class="custom-multiselect-option ${selectedClass}" data-name="${escapeHtml(s.name).toLowerCase()}">
                        <input type="checkbox" value="${s.id}" class="brand-service-cb-${index}" ${checkedAttr} onchange="ClientModule.onServiceToggle(${index}, '${s.id}', this.checked, this)">
                        <span>${escapeHtml(s.name)}</span>
                    </label>
                `;
            });

            const isChecked = brand.has_membership == 1 ? 'checked' : '';

            card.innerHTML = `
                <div class="brand-card-top">
                    <div class="brand-identity">
                        <div class="brand-avatar-clickable-wrap" onclick="ClientModule.openChangeLogoModal(${index})" title="Clic para cambiar o pegar nuevo logotipo">
                            ${imgHtml}
                            <div class="avatar-hover-overlay">
                                <i class="ph ph-camera"></i>
                            </div>
                        </div>
                        <div class="brand-name-wrap">
                            <span class="brand-card-title">${escapeHtml(brand.name)}</span>
                            <span class="brand-card-subtitle">${brand.has_membership == 1 ? 'Membresía Activa' : 'Membresía Inactiva'}</span>
                        </div>
                    </div>
                    <button type="button" class="brand-delete-btn" onclick="ClientModule.removeBrand(${index})" title="Eliminar marca">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
                
                <div class="brand-card-controls">
                    <div class="brand-membership-switch-wrap">
                        <label class="modern-toggle-switch">
                            <input type="checkbox" id="has_membership_${index}" onchange="ClientModule.updateBrand(${index}, 'has_membership', this.checked ? 1 : 0)" ${isChecked}>
                            <span class="toggle-slider"></span>
                        </label>
                        <label for="has_membership_${index}" class="toggle-label">Tiene Membresía</label>
                    </div>

                    <div class="brand-services-field">
                        <label class="field-micro-label">Servicios Contratados</label>
                        <div class="custom-multiselect" id="customMultiselect_${index}">
                            <div class="custom-multiselect-header" onclick="ClientModule.toggleMultiselect(${index}, event)">
                                <div class="badges-wrapper" id="brandServicesBadges_${index}">${renderServicesBadgesHtml(index, brand.services_ids)}</div>
                                <i class="ph ph-caret-down caret-icon"></i>
                            </div>
                            <div class="custom-multiselect-dropdown" id="customMultiselectDropdown_${index}" style="display: none;" onclick="event.stopPropagation();">
                                <div class="multiselect-search-box">
                                    <i class="ph ph-magnifying-glass"></i>
                                    <input type="text" placeholder="Filtrar servicios..." oninput="ClientModule.filterServicesList(${index}, this.value)">
                                </div>
                                <div class="multiselect-options-list" id="servicesOptionsList_${index}">
                                    ${servicesListHtml}
                                </div>
                                <div class="multiselect-dropdown-footer">
                                    <span class="services-count-text" id="servicesCount_${index}">${brand.services_ids.length} seleccionados</span>
                                    <button type="button" class="btn-dropdown-done" onclick="ClientModule.closeAllMultiselects()">Listo</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
    }

    function onPortalToggleChange(checkbox) {
        const box = document.getElementById('portalToggleCard');
        const pill = document.getElementById('portalStatusPill');
        if (checkbox.checked) {
            if (box) box.classList.add('active');
            if (pill) {
                pill.textContent = 'Activo';
                pill.classList.add('active');
            }
        } else {
            if (box) box.classList.remove('active');
            if (pill) {
                pill.textContent = 'Desactivado';
                pill.classList.remove('active');
            }
        }
    }

    function openChangeLogoModal(index) {
        currentEditingBrandIndex = index;
        const brand = brandsArray[index];
        if (!brand) return;
        const titleEl = document.getElementById('changeLogoModalBrandTitle');
        if (titleEl) titleEl.textContent = `Logotipo: ${brand.name || 'Marca'}`;
        const urlInput = document.getElementById('change_brand_logo_url_input');
        if (urlInput) urlInput.value = (typeof brand.logo === 'string' && brand.logo.startsWith('http')) ? brand.logo : '';
        const modal = document.getElementById('changeLogoModal');
        if (modal) modal.classList.add('active');
    }

    function closeChangeLogoModal() {
        const modal = document.getElementById('changeLogoModal');
        if (modal) modal.classList.remove('active');
        currentEditingBrandIndex = null;
    }

    function handleChangeBrandLogoFile(input) {
        if (input.files && input.files[0] && currentEditingBrandIndex !== null) {
            updateExistingBrandLogo(currentEditingBrandIndex, input.files[0], null);
            input.value = '';
            closeChangeLogoModal();
        }
    }

    async function pasteLogoFromClipboardForExisting() {
        if (currentEditingBrandIndex === null) return;
        try {
            if (navigator.clipboard && navigator.clipboard.read) {
                const items = await navigator.clipboard.read();
                for (const item of items) {
                    for (const type of item.types) {
                        if (type.startsWith('image/')) {
                            const blob = await item.getType(type);
                            const file = new File([blob], 'clipboard_' + Date.now() + '.png', { type: blob.type || 'image/png' });
                            updateExistingBrandLogo(currentEditingBrandIndex, file, null);
                            closeChangeLogoModal();
                            return;
                        }
                    }
                }
            }
            if (navigator.clipboard && navigator.clipboard.readText) {
                const text = await navigator.clipboard.readText();
                if (text) {
                    const trimmed = text.trim();
                    if (trimmed.startsWith('data:image/')) {
                        const file = dataURLtoFile(trimmed, 'clipboard_' + Date.now() + '.png');
                        if (file) {
                            updateExistingBrandLogo(currentEditingBrandIndex, file, null);
                            closeChangeLogoModal();
                            return;
                        }
                    }
                    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
                        updateExistingBrandLogo(currentEditingBrandIndex, null, trimmed);
                        closeChangeLogoModal();
                        return;
                    }
                }
            }
            alert('No se detectó ninguna imagen en el portapapeles.');
        } catch (err) {
            console.warn('Clipboard read error:', err);
            alert('Presiona Ctrl+V con una imagen en el portapapeles.');
        }
    }

    function applyChangeLogoUrl() {
        if (currentEditingBrandIndex === null) return;
        const input = document.getElementById('change_brand_logo_url_input');
        const val = input ? input.value.trim() : '';
        if (!val) return;
        updateExistingBrandLogo(currentEditingBrandIndex, null, val);
        closeChangeLogoModal();
    }

    function removeExistingBrandLogo() {
        if (currentEditingBrandIndex === null) return;
        updateExistingBrandLogo(currentEditingBrandIndex, null, null);
        closeChangeLogoModal();
    }

    function updateExistingBrandLogo(index, file, logoUrl) {
        if (!brandsArray[index]) return;
        brandsArray[index].file = file;
        brandsArray[index].logo = logoUrl;
        renderBrands();
    }

    return {
        init: init,
        onSearchInput: onSearchInput,
        clearSearch: clearSearch,
        setFilter: setFilter,
        setSort: setSort,
        setView: setView,
        resetFilters: resetFilters,
        toggleMultiselect: toggleMultiselect,
        closeAllMultiselects: closeAllMultiselects,
        onServiceToggle: onServiceToggle,
        removeBrandService: removeBrandService,
        filterServicesList: filterServicesList,
        onPortalToggleChange: onPortalToggleChange,
        handleLogoFileInput: handleLogoFileInput,
        pasteLogoFromClipboard: pasteLogoFromClipboard,
        toggleLogoUrlInput: toggleLogoUrlInput,
        applyLogoUrl: applyLogoUrl,
        clearDraftLogo: clearDraftLogo,
        openChangeLogoModal: openChangeLogoModal,
        closeChangeLogoModal: closeChangeLogoModal,
        handleChangeBrandLogoFile: handleChangeBrandLogoFile,
        pasteLogoFromClipboardForExisting: pasteLogoFromClipboardForExisting,
        applyChangeLogoUrl: applyChangeLogoUrl,
        removeExistingBrandLogo: removeExistingBrandLogo,

        openModal: function() {
            document.getElementById('clientForm').reset();
            document.getElementById('client_id').value = '';
            document.getElementById('client_dni').value = '';
            
            const portalSwitch = document.getElementById('client_portal_enabled');
            if (portalSwitch) {
                portalSwitch.checked = false;
                onPortalToggleChange(portalSwitch);
            }

            const titleEl = document.getElementById('clientModalTitle');
            if (titleEl) titleEl.textContent = 'Nuevo Cliente';
            const subEl = document.getElementById('clientModalSubtitle');
            if (subEl) subEl.textContent = 'Registra los datos comerciales y gestiona sus marcas';
            const badgeEl = document.getElementById('clientModalIconBadge');
            if (badgeEl) badgeEl.innerHTML = '<i class="ph ph-user-plus"></i>';
            
            clearDraftLogo();

            brandsArray = [];
            deletedBrands = [];
            renderBrands();
            modalEl.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            setTimeout(() => {
                const nameInput = document.getElementById('client_name');
                if (nameInput) nameInput.focus();
            }, 100);
        },

        closeModal: function() {
            if (modalEl) {
                modalEl.classList.remove('active');
                document.body.style.overflow = '';
            }
            closeChangeLogoModal();
        },

        addBrand: function() {
            const nameInput = document.getElementById('new_brand_name');
            const name = nameInput.value.trim();
            if (!name) {
                nameInput.focus();
                nameInput.classList.add('shake-error');
                setTimeout(() => nameInput.classList.remove('shake-error'), 500);
                return;
            }

            brandsArray.push({
                id: null,
                name: name,
                file: draftLogo.file,
                logo: draftLogo.url,
                has_membership: 0,
                services_ids: [],
                whatsapp_group: null
            });

            nameInput.value = '';
            clearDraftLogo();
            renderBrands();
        },

        removeBrand: function(index) {
            const brand = brandsArray[index];
            if (brand && brand.id) {
                deletedBrands.push(brand.id);
            }
            brandsArray.splice(index, 1);
            renderBrands();
        },

        updateBrand: function(index, key, value) {
            if (brandsArray[index]) {
                brandsArray[index][key] = value;
                // update subtitle text live
                if (key === 'has_membership') {
                    const card = document.querySelectorAll('.brand-editor-card')[index];
                    if (card) {
                        const sub = card.querySelector('.brand-card-subtitle');
                        if (sub) sub.textContent = value == 1 ? 'Membresía Activa' : 'Membresía Inactiva';
                    }
                }
            }
        },

        updateCustomBrandServices: function(index) {
            const checkboxes = document.querySelectorAll(`.brand-service-cb-${index}:checked`);
            const selectedOptions = Array.from(checkboxes).map(cb => cb.value);
            if (brandsArray[index]) {
                brandsArray[index].services_ids = selectedOptions;
                const badges = document.getElementById('brandServicesBadges_' + index);
                if (badges) badges.innerHTML = renderServicesBadgesHtml(index, brandsArray[index].services_ids);
                const count = document.getElementById('servicesCount_' + index);
                if (count) count.textContent = `${brandsArray[index].services_ids.length} seleccionados`;
            }
        },

        renderBrandsOnly: function() {
            renderBrands();
        },

        saveClient: async function() {
            const form = document.getElementById('clientForm');
            if (!form.reportValidity()) return;

            const btnSave = document.getElementById('btnSaveClient');
            const originalHtml = btnSave.innerHTML;
            btnSave.disabled = true;
            btnSave.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';

            const formData = new FormData(form);
            formData.append('deleted_brands', JSON.stringify(deletedBrands));
            const portalSwitch = document.getElementById('client_portal_enabled');
            formData.set('portal_enabled', (portalSwitch && portalSwitch.checked) ? '1' : '0');
            
            brandsArray.forEach((brand, index) => {
                formData.append(`brands[${index}][id]`, brand.id || '');
                formData.append(`brands[${index}][name]`, brand.name);
                if (brand.file) {
                    formData.append(`brands_files_${index}`, brand.file);
                }
                formData.append(`brands[${index}][logo]`, brand.logo || '');
                formData.append(`brands[${index}][has_membership]`, brand.has_membership || 0);
                formData.append(`brands[${index}][services_ids]`, JSON.stringify(brand.services_ids || []));
                formData.append(`brands[${index}][whatsapp_group]`, brand.whatsapp_group || '');
            });

            try {
                const response = await fetch('index.php?module=clients&action=ajax_save_client', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    ClientModule.closeModal();
                    // Refrescar resultados con AJAX instantáneamente sin recargar la página completa
                    performAjaxSearch();
                } else {
                    alert('Error al guardar: ' + (data.error || 'Error desconocido'));
                    btnSave.disabled = false;
                    btnSave.innerHTML = originalHtml;
                }
            } catch (err) {
                console.error(err);
                alert('Error de conexión al servidor.');
                btnSave.disabled = false;
                btnSave.innerHTML = originalHtml;
            }
        },

        editClient: async function(id) {
            try {
                const response = await fetch(`index.php?module=clients&action=ajax_get_client&id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    const client = data.client;
                    document.getElementById('client_id').value = client.id;
                    document.getElementById('client_name').value = client.name;
                    document.getElementById('client_dni').value = client.dni || '';
                    document.getElementById('client_whatsapp').value = client.whatsapp || '';
                    document.getElementById('client_email').value = client.email || '';
                    
                    const portalSwitch = document.getElementById('client_portal_enabled');
                    if (portalSwitch) {
                        portalSwitch.checked = (client.portal_enabled == 1 || client.portal_enabled === '1');
                        onPortalToggleChange(portalSwitch);
                    }
                    
                    brandsArray = data.brands || [];
                    brandsArray.forEach(b => {
                        if (typeof b.services_ids === 'string') {
                            try {
                                b.services_ids = JSON.parse(b.services_ids) || [];
                            } catch(e) {
                                b.services_ids = [];
                            }
                        }
                        if (!Array.isArray(b.services_ids)) {
                            b.services_ids = [];
                        }
                        b.services_ids = b.services_ids.map(id => String(id));
                    });

                    deletedBrands = [];
                    
                    const titleEl = document.getElementById('clientModalTitle');
                    if (titleEl) titleEl.textContent = 'Editar Cliente';
                    const subEl = document.getElementById('clientModalSubtitle');
                    if (subEl) subEl.textContent = 'Actualiza los datos y marcas de este cliente';
                    const badgeEl = document.getElementById('clientModalIconBadge');
                    if (badgeEl) badgeEl.innerHTML = '<i class="ph ph-pencil-simple-line"></i>';
                    
                    clearDraftLogo();

                    renderBrands();
                    modalEl.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    alert('No se pudo cargar la información del cliente.');
                }
            } catch (err) {
                console.error(err);
                alert('Error al obtener los datos del cliente.');
            }
        },

        deleteClient: async function(id) {
            if (confirm('¿Estás seguro de eliminar este cliente? Se eliminarán también todas sus marcas asociadas.')) {
                try {
                    const response = await fetch('index.php?module=clients&action=ajax_delete_client', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `id=${id}`
                    });
                    const data = await response.json();
                    if (data.success) {
                        // Refrescar resultados mediante AJAX instantáneo
                        performAjaxSearch();
                    } else {
                        alert('Error al eliminar: ' + (data.error || 'No se pudo eliminar el cliente.'));
                    }
                } catch (err) {
                    alert('Error de conexión.');
                }
            }
        }
    };
})();

document.addEventListener('DOMContentLoaded', ClientModule.init);
