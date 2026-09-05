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
                document.querySelectorAll('.custom-multiselect-dropdown').forEach(dd => {
                    dd.style.display = 'none';
                });
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

    // Modal & Brands Logic
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

            // Services multi-select items
            let servicesListHtml = '';
            let selectedBadgesHtml = '';
            
            if (typeof SYSTEM_SERVICES !== 'undefined' && Array.isArray(SYSTEM_SERVICES)) {
                SYSTEM_SERVICES.forEach(s => {
                    const isSelected = (brand.services_ids && brand.services_ids.includes(s.id.toString()));
                    const checkedAttr = isSelected ? 'checked' : '';
                    
                    if (isSelected) {
                        selectedBadgesHtml += `<span class="service-selected-pill">${escapeHtml(s.name)}</span>`;
                    }

                    servicesListHtml += `
                        <label class="custom-multiselect-option">
                            <input type="checkbox" value="${s.id}" class="brand-service-checkbox-${index}" ${checkedAttr} onchange="ClientModule.updateCustomBrandServices(${index})">
                            <span>${escapeHtml(s.name)}</span>
                        </label>
                    `;
                });
            }
            if (!selectedBadgesHtml) {
                selectedBadgesHtml = '<span class="service-none-text">Sin servicios asignados</span>';
            }

            const isChecked = brand.has_membership == 1 ? 'checked' : '';

            card.innerHTML = `
                <div class="brand-card-top">
                    <div class="brand-identity">
                        ${imgHtml}
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
                        <div class="custom-multiselect">
                            <div class="custom-multiselect-header" onclick="ClientModule.toggleMultiselect(this)">
                                <div class="badges-wrapper">${selectedBadgesHtml}</div>
                                <i class="ph ph-caret-down caret-icon"></i>
                            </div>
                            <div class="custom-multiselect-dropdown" style="display: none;">
                                ${servicesListHtml}
                            </div>
                        </div>
                    </div>

                    <div class="brand-whatsapp-field">
                        <label class="field-micro-label">ID Grupo WhatsApp (Opcional)</label>
                        <div class="input-with-icon-mini">
                            <i class="ph ph-users-three"></i>
                            <input type="text" class="form-control-sm" value="${escapeHtml(brand.whatsapp_group || '')}" onchange="ClientModule.updateBrand(${index}, 'whatsapp_group', this.value)" placeholder="ej. 12345678@g.us">
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
    }

    function toggleMultiselect(headerEl) {
        const dropdown = headerEl.nextElementSibling;
        const isOpened = dropdown.style.display === 'block';
        // Close others
        document.querySelectorAll('.custom-multiselect-dropdown').forEach(dd => dd.style.display = 'none');
        dropdown.style.display = isOpened ? 'none' : 'block';
    }

    function previewNewBrandLogo(input) {
        const previewContainer = document.getElementById('newBrandLogoPreview');
        if (!previewContainer) return;
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const url = URL.createObjectURL(file);
            previewContainer.innerHTML = `<img src="${url}" style="width:100%;height:100%;object-fit:cover;border-radius:var(--radius-sm);">`;
            previewContainer.style.display = 'flex';
        } else {
            previewContainer.innerHTML = '';
            previewContainer.style.display = 'none';
        }
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
        previewNewBrandLogo: previewNewBrandLogo,

        openModal: function() {
            document.getElementById('clientForm').reset();
            document.getElementById('client_id').value = '';
            document.getElementById('client_dni').value = '';
            document.getElementById('client_drive_folder_id').value = '';
            document.getElementById('clientModalTitle').innerHTML = '<i class="ph ph-user-plus"></i> <span>Nuevo Cliente</span>';
            
            const previewContainer = document.getElementById('newBrandLogoPreview');
            if (previewContainer) {
                previewContainer.innerHTML = '';
                previewContainer.style.display = 'none';
            }

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
        },

        addBrand: function() {
            const nameInput = document.getElementById('new_brand_name');
            const fileInput = document.getElementById('new_brand_logo');
            
            const name = nameInput.value.trim();
            if (!name) {
                nameInput.focus();
                nameInput.classList.add('shake-error');
                setTimeout(() => nameInput.classList.remove('shake-error'), 500);
                return;
            }

            const file = fileInput.files[0] || null;

            brandsArray.push({
                id: null,
                name: name,
                file: file,
                logo: null,
                has_membership: 0,
                services_ids: [],
                whatsapp_group: null
            });

            nameInput.value = '';
            fileInput.value = '';
            
            const previewContainer = document.getElementById('newBrandLogoPreview');
            if (previewContainer) {
                previewContainer.innerHTML = '';
                previewContainer.style.display = 'none';
            }

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
            const checkboxes = document.querySelectorAll(`.brand-service-checkbox-${index}:checked`);
            const selectedOptions = Array.from(checkboxes).map(cb => cb.value);
            if (brandsArray[index]) {
                brandsArray[index].services_ids = selectedOptions;
            }
            renderBrands();
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
                    document.getElementById('client_drive_folder_id').value = client.drive_folder_id || '';
                    
                    brandsArray = data.brands || [];
                    brandsArray.forEach(b => {
                        if (typeof b.services_ids === 'string') {
                            try {
                                b.services_ids = JSON.parse(b.services_ids) || [];
                            } catch(e) {
                                b.services_ids = [];
                            }
                        }
                        if (!b.services_ids) b.services_ids = [];
                    });

                    deletedBrands = [];
                    
                    document.getElementById('clientModalTitle').innerHTML = '<i class="ph ph-pencil-simple-line"></i> <span>Editar Cliente</span>';
                    
                    const previewContainer = document.getElementById('newBrandLogoPreview');
                    if (previewContainer) {
                        previewContainer.innerHTML = '';
                        previewContainer.style.display = 'none';
                    }

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
