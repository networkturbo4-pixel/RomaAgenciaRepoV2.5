// assets/js/modules/service_form.js

const ServiceFormModule = {
    features: [],
    deliverables: [],
    faqs: [],
    prereqs: [],
    packages: [],
    gallery: [], // existing gallery items from DB
    newGalleryFiles: [], // new files to upload
    addons: [], // new addons array
    sortableInstances: {},

    init: function() {
        if (typeof INITIAL_SERVICE_DATA !== 'undefined') {
            this.features = INITIAL_SERVICE_DATA.features || [];
            this.deliverables = INITIAL_SERVICE_DATA.deliverables || [];
            this.faqs = INITIAL_SERVICE_DATA.faqs || [];
            this.prereqs = INITIAL_SERVICE_DATA.prereqs || [];
            this.packages = INITIAL_SERVICE_DATA.packages || [];
            this.packages.forEach(p => {
                if (typeof p.features === 'string') {
                    try { p.features = JSON.parse(p.features); } catch(e) { p.features = []; }
                }
                if (!p.features) p.features = [];
            });
            this.gallery = INITIAL_SERVICE_DATA.gallery || [];
            this.addons = INITIAL_SERVICE_DATA.addons || [];
        }
        
        this.renderAllLists();
        this.renderPackages();
        this.renderGallery();
        this.renderAddons();
        this.initSortables();
        this.updatePreview();
        this.updateSEOPreview();
        this.handlePriceTypeChange();

        // Escape key to cancel
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                window.location.href = 'index.php?module=services';
            }
        });

        // Mantener la sesión activa (Keep-alive) para evitar que expire mientras se llena el formulario largo
        setInterval(() => {
            fetch('index.php?module=dashboard&action=index', { method: 'HEAD' }).catch(() => {});
        }, 10 * 60 * 1000); // 10 minutos
    },

    // ── Tabs ──
    switchTab: function(tabId) {
        document.querySelectorAll('.svc-tab').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.svc-tab-content').forEach(content => content.classList.remove('active'));
        
        event.currentTarget.classList.add('active');
        document.getElementById(tabId).classList.add('active');
    },

    // ── Live Preview ──
    updatePreview: function() {
        // Name
        const name = document.getElementById('service_name').value;
        document.getElementById('prevTitle').textContent = name || 'Título del Servicio';
        
        // Category
        const catSelect = document.getElementById('service_category');
        if (catSelect.selectedIndex > 0) {
            const option = catSelect.options[catSelect.selectedIndex];
            document.getElementById('prevCatName').textContent = option.getAttribute('data-name');
            const color = option.getAttribute('data-color') || '#6b7280';
            const catElem = document.getElementById('prevCategory');
            catElem.style.borderColor = color;
            catElem.style.color = color;
        } else {
            document.getElementById('prevCatName').textContent = 'Sin categoría';
            document.getElementById('prevCategory').style.borderColor = 'var(--border-color)';
            document.getElementById('prevCategory').style.color = 'var(--text-muted)';
        }

        // Status Badge
        const status = document.getElementById('service_status').value;
        const badge = document.getElementById('prevBadge');
        if (status === 'active') { badge.textContent = 'Activo'; badge.style.background = 'rgba(34, 197, 94, 0.9)'; }
        if (status === 'paused') { badge.textContent = 'Pausado'; badge.style.background = 'rgba(234, 179, 8, 0.9)'; }
        if (status === 'out_of_stock') { badge.textContent = 'Agotado'; badge.style.background = 'rgba(239, 68, 68, 0.9)'; }

        // Price
        let price = document.getElementById('service_price').value;
        const priceType = document.getElementById('price_type').value;
        
        if (priceType === 'packages' && this.packages.length > 0) {
            // Find the lowest price among packages
            const lowest = Math.min(...this.packages.map(p => parseFloat(p.price || 0)));
            price = lowest;
            document.getElementById('prevPriceLabel').style.display = 'block';
            document.getElementById('prevPriceLabel').textContent = 'Desde (Paquetes)';
        } else if (priceType === 'monthly') {
            document.getElementById('prevPriceLabel').style.display = 'block';
            document.getElementById('prevPriceLabel').textContent = 'Mensual / Recurrente';
        } else {
            document.getElementById('prevPriceLabel').style.display = priceType === 'from' ? 'block' : 'none';
            document.getElementById('prevPriceLabel').textContent = 'Desde';
        }
        
        document.getElementById('prevPrice').textContent = parseFloat(price || 0).toFixed(2);
        
        // Description
        const desc = document.getElementById('service_description').value;
        if (desc) {
            document.getElementById('prevDesc').innerHTML = desc.replace(/\n/g, '<br>');
        } else {
            document.getElementById('prevDesc').innerHTML = '<span style="color: var(--text-muted); font-style: italic;">Sin descripción</span>';
        }
    },

    updateSEOPreview: function() {
        const slug = document.getElementById('slug').value || 'slug-del-servicio';
        const title = document.getElementById('meta_title').value || document.getElementById('service_name').value || 'Título del Servicio';
        const desc = document.getElementById('meta_description').value || document.getElementById('service_description').value || 'Descripción de tu servicio en Google...';
        const siteName = (typeof INITIAL_SERVICE_DATA !== 'undefined' && INITIAL_SERVICE_DATA.site_name) ? INITIAL_SERVICE_DATA.site_name : 'Tu Agencia';
        
        document.getElementById('seoPreviewSlug').textContent = slug.toLowerCase().replace(/[^a-z0-9-]/g, '-');
        document.getElementById('seoPreviewTitle').textContent = title + ' | ' + siteName;
        document.getElementById('seoPreviewDesc').textContent = desc;
    },

    // ── Price Types & Packages ──
    handlePriceTypeChange: function() {
        const type = document.getElementById('price_type').value;
        if (type === 'packages') {
            document.getElementById('singlePriceFields').style.display = 'none';
            document.getElementById('packagesPriceFields').style.display = 'block';
            // Visibility is forced public or private independently, but usually packages don't affect visibility field itself.
        } else {
            document.getElementById('singlePriceFields').style.display = 'block';
            document.getElementById('packagesPriceFields').style.display = 'none';
        }
        this.updatePreview();
    },

    toggleCombo: function() {
        const isCombo = document.getElementById('is_combo').checked;
        const comboSelection = document.getElementById('combo_selection');
        if (isCombo) {
            comboSelection.style.display = 'block';
        } else {
            comboSelection.style.display = 'none';
        }
    },

    // ── Addons (Extras) ──
    toggleAddons: function() {
        const hasAddons = document.getElementById('has_addons').checked;
        const addonsContainer = document.getElementById('addonsContainer');
        if (hasAddons) {
            addonsContainer.style.display = 'block';
            if (this.addons.length === 0) {
                this.addAddon(); // Add one default blank
            }
        } else {
            addonsContainer.style.display = 'none';
        }
    },

    renderAddons: function() {
        const list = document.getElementById('addonsList');
        if (!list) return;
        list.innerHTML = '';
        this.addons.forEach((addon, index) => {
            let tiersHtml = '';
            let parsedTiers = [];
            try {
                parsedTiers = typeof addon.pricing_tiers === 'string' ? JSON.parse(addon.pricing_tiers) : (addon.pricing_tiers || []);
            } catch(e) {}
            
            if (addon.type === 'quantity') {
                tiersHtml = `
                    <div style="margin-top: 0.75rem; border-top: 1px dashed var(--border-color); padding-top: 0.75rem;">
                        <label class="svc-label" style="font-size: 0.75rem;">Escalas de Precio (Descuentos por volumen)</label>
                        <div id="addonTiers_${index}" style="margin-bottom: 0.5rem;">
                            ${parsedTiers.map((tier, tIdx) => `
                                <div style="display:flex; gap:0.5rem; margin-bottom: 0.25rem;">
                                    <input type="number" min="2" class="svc-input" style="padding:0.4rem; font-size:0.8rem;" placeholder="Mín. cant" value="${tier.min_qty}" onchange="ServiceFormModule.updateAddonTier(${index}, ${tIdx}, 'min_qty', this.value)">
                                    <input type="number" step="0.01" class="svc-input" style="padding:0.4rem; font-size:0.8rem;" placeholder="Precio c/u" value="${tier.price}" onchange="ServiceFormModule.updateAddonTier(${index}, ${tIdx}, 'price', this.value)">
                                    <button type="button" class="btn-icon" style="color:var(--color-danger); width:30px; height:30px;" onclick="ServiceFormModule.removeAddonTier(${index}, ${tIdx})"><i class="ph ph-trash"></i></button>
                                </div>
                            `).join('')}
                        </div>
                        <button type="button" class="btn btn-outline" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;" onclick="ServiceFormModule.addAddonTier(${index})"><i class="ph ph-plus"></i> Añadir escala</button>
                    </div>
                `;
            }

            const html = `
                <div class="svc-list-item" data-id="${index}">
                    <div class="svc-item-drag handle-addon"><i class="ph ph-dots-six-vertical"></i></div>
                    <div style="flex-grow: 1; min-width: 0;">
                        <div style="display: grid; grid-template-columns: 1fr 100px 120px; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <input type="text" class="svc-input" placeholder="Nombre (Ej: Diseño Extra)" value="${addon.name || ''}" onchange="ServiceFormModule.updateAddon(${index}, 'name', this.value)">
                            <input type="number" step="0.01" class="svc-input" placeholder="Precio Base" value="${addon.price || ''}" onchange="ServiceFormModule.updateAddon(${index}, 'price', this.value)">
                            <select class="svc-input" onchange="ServiceFormModule.updateAddon(${index}, 'type', this.value)">
                                <option value="quantity" ${addon.type === 'quantity' ? 'selected' : ''}>Cantidad (-/+)</option>
                                <option value="checkbox" ${addon.type === 'checkbox' ? 'selected' : ''}>Casilla (Sí/No)</option>
                            </select>
                        </div>
                        ${tiersHtml}
                    </div>
                    <button type="button" class="btn-icon" style="color: var(--color-danger); margin-left: 0.5rem;" onclick="ServiceFormModule.removeAddon(${index})">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            `;
            list.insertAdjacentHTML('beforeend', html);
        });
    },

    addAddon: function() {
        this.addons.push({ name: '', price: '0.00', type: 'quantity', pricing_tiers: [] });
        this.renderAddons();
    },

    removeAddon: function(index) {
        this.addons.splice(index, 1);
        this.renderAddons();
    },

    updateAddon: function(index, field, value) {
        if (!this.addons[index]) return;
        this.addons[index][field] = value;
        if (field === 'type') this.renderAddons(); // re-render to show/hide tiers
    },

    addAddonTier: function(index) {
        if (!this.addons[index]) return;
        let tiers = [];
        try { tiers = typeof this.addons[index].pricing_tiers === 'string' ? JSON.parse(this.addons[index].pricing_tiers) : (this.addons[index].pricing_tiers || []); } catch(e) {}
        tiers.push({ min_qty: 2, price: this.addons[index].price || 0 });
        this.addons[index].pricing_tiers = tiers;
        this.renderAddons();
    },

    updateAddonTier: function(addonIndex, tierIndex, field, value) {
        if (!this.addons[addonIndex]) return;
        let tiers = typeof this.addons[addonIndex].pricing_tiers === 'string' ? JSON.parse(this.addons[addonIndex].pricing_tiers) : (this.addons[addonIndex].pricing_tiers || []);
        if (tiers[tierIndex]) {
            tiers[tierIndex][field] = value;
            this.addons[addonIndex].pricing_tiers = tiers;
        }
    },

    removeAddonTier: function(addonIndex, tierIndex) {
        if (!this.addons[addonIndex]) return;
        let tiers = typeof this.addons[addonIndex].pricing_tiers === 'string' ? JSON.parse(this.addons[addonIndex].pricing_tiers) : (this.addons[addonIndex].pricing_tiers || []);
        tiers.splice(tierIndex, 1);
        this.addons[addonIndex].pricing_tiers = tiers;
        this.renderAddons();
    },

    renderPackages: function() {
        const list = document.getElementById('packagesList');
        if (!list) return;
        list.innerHTML = '';
        
        if (this.packages.length === 0) {
            list.innerHTML = `<div style="text-align:center; padding:1rem; color:var(--text-muted); font-size:0.9rem;">No has creado ningún paquete.</div>`;
            return;
        }
        
        this.packages.forEach((pkg, i) => {
            let featuresHtml = '';
            let pkgFeatures = pkg.features || [];
            if (pkgFeatures.length > 0) {
                featuresHtml = `<div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                    ${pkgFeatures.map((feat, fIdx) => `
                        <div style="background: var(--bg-body); border: 1px solid var(--border-color); border-radius: 4px; padding: 2px 8px; font-size: 0.8rem; display: flex; align-items: center; gap: 4px;">
                            <span style="color: var(--primary-color);">•</span> ${feat}
                            <button type="button" class="btn-icon" style="padding: 0; width: 16px; height: 16px; min-width: 0;" onclick="ServiceFormModule.removePackageFeature(${i}, ${fIdx})"><i class="ph ph-x"></i></button>
                        </div>
                    `).join('')}
                </div>`;
            }

            const div = document.createElement('div');
            div.className = 'svc-list-item';
            div.innerHTML = `
                <div class="svc-item-drag" title="Arrastrar para reordenar"><i class="ph ph-list"></i></div>
                <div style="flex:1; margin-right: 1rem;">
                    <div style="display: grid; grid-template-columns: 1fr 100px; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <input type="text" class="svc-input" placeholder="Nombre (Ej: Básico)" value="${pkg.name}" onchange="ServiceFormModule.updatePackage(${i}, 'name', this.value)" style="padding: 0.4rem 0.5rem;">
                        <input type="number" step="0.01" class="svc-input" placeholder="Precio" value="${pkg.price}" onchange="ServiceFormModule.updatePackage(${i}, 'price', this.value)" style="padding: 0.4rem 0.5rem;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 100px; gap: 0.5rem;">
                        <textarea class="svc-input" placeholder="Descripción breve del paquete" onchange="ServiceFormModule.updatePackage(${i}, 'description', this.value)" style="padding: 0.4rem 0.5rem; min-height: 40px; resize: vertical;">${pkg.description || ''}</textarea>
                        <input type="text" class="svc-input" placeholder="Tiempo (Ej: 3 Días)" value="${pkg.delivery_time || ''}" onchange="ServiceFormModule.updatePackage(${i}, 'delivery_time', this.value)" style="padding: 0.4rem 0.5rem;">
                    </div>
                    <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed var(--border-color);">
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" class="svc-input" placeholder="Añadir característica (Ej: 3 Revisiones)... y presiona Enter" id="pkg_feat_input_${i}" onkeypress="if(event.key === 'Enter') { event.preventDefault(); const val = this.value; this.value=''; ServiceFormModule.addPackageFeature(${i}, val); }" style="padding: 0.3rem 0.5rem; font-size: 0.85rem;">
                            <button type="button" class="btn btn-outline" style="padding: 0.3rem 0.5rem;" onclick="const inp = document.getElementById('pkg_feat_input_${i}'); const val = inp.value; inp.value=''; ServiceFormModule.addPackageFeature(${i}, val);"><i class="ph ph-plus"></i></button>
                        </div>
                        ${featuresHtml}
                    </div>
                </div>
                <button type="button" class="btn-icon" onclick="ServiceFormModule.removePackage(${i})" title="Eliminar" style="color:var(--color-danger);"><i class="ph ph-trash"></i></button>
            `;
            list.appendChild(div);
        });
    },

    addPackageFeature: function(pkgIndex, featureText) {
        if (!featureText || !featureText.trim()) return;
        if (!this.packages[pkgIndex]) return;
        this.packages[pkgIndex].features = this.packages[pkgIndex].features || [];
        this.packages[pkgIndex].features.push(featureText.trim());
        this.renderPackages();
        
        // Restore focus to the input so the user can keep typing more features
        setTimeout(() => {
            const input = document.getElementById('pkg_feat_input_' + pkgIndex);
            if (input) input.focus();
        }, 10);
    },

    removePackageFeature: function(pkgIndex, featureIndex) {
        if (!this.packages[pkgIndex] || !this.packages[pkgIndex].features) return;
        this.packages[pkgIndex].features.splice(featureIndex, 1);
        this.renderPackages();
    },

    addPackage: function() {
        this.packages.push({ name: '', description: '', price: '0.00', delivery_time: '', features: [] });
        this.renderPackages();
        this.updatePreview();
    },

    removePackage: function(index) {
        this.packages.splice(index, 1);
        this.renderPackages();
        this.updatePreview();
    },

    updatePackage: function(index, field, value) {
        this.packages[index][field] = value;
        if (field === 'price') this.updatePreview();
    },

    // ── Cover Image ──
    handleCoverUpload: function(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const src = e.target.result;
                document.getElementById('coverPreview').src = src;
                document.getElementById('coverPreview').style.display = 'block';
                document.getElementById('coverPlaceholder').style.display = 'none';
                document.getElementById('btnRemoveCover').style.display = 'block';
                
                // Update Live Preview
                document.getElementById('prevCover').style.backgroundImage = `url('${src}')`;
                document.getElementById('prevCoverPlaceholder').style.display = 'none';
            }
            
            reader.readAsDataURL(file);
        }
    },

    removeCoverImage: function() {
        document.getElementById('cover_file').value = '';
        document.getElementById('existing_cover').value = '';
        document.getElementById('coverPreview').src = '';
        document.getElementById('coverPreview').style.display = 'none';
        document.getElementById('coverPlaceholder').style.display = 'block';
        document.getElementById('btnRemoveCover').style.display = 'none';
        
        // Update Live Preview
        document.getElementById('prevCover').style.backgroundImage = 'none';
        document.getElementById('prevCoverPlaceholder').style.display = 'flex';
    },

    // ── Gallery Images ──
    handleGalleryUpload: function(input) {
        if (input.files) {
            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                const id = 'new_' + Math.random().toString(36).substr(2, 9);
                this.newGalleryFiles.push({ id: id, file: file });
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.gallery.push({ id: id, isNew: true, src: e.target.result });
                    this.renderGallery();
                };
                reader.readAsDataURL(file);
            }
        }
        // Reset input to allow uploading same file again if needed
        input.value = '';
    },

    renderGallery: function() {
        const grid = document.getElementById('galleryGrid');
        if (!grid) return;
        
        // Remove existing items (except the upload button)
        const items = grid.querySelectorAll('.gallery-item');
        items.forEach(item => item.remove());
        
        const uploadBtn = grid.querySelector('.gallery-upload-btn');
        
        this.gallery.forEach((item, i) => {
            const div = document.createElement('div');
            div.className = 'gallery-item';
            
            let previewHtml = '';
            if (item.media_type === 'video' || (item.image_path && item.image_path.includes('youtube.com'))) {
                const ytId = item.image_path.match(/[?&]v=([^&]+)/);
                const vidId = ytId ? ytId[1] : '';
                if (vidId) {
                    previewHtml = `<div style="position:relative; width:100%; height:100%; background:#000; display:flex; align-items:center; justify-content:center;"><i class="ph-fill ph-play-circle" style="color:white; font-size:2rem; z-index:2; position:absolute;"></i><img src="https://img.youtube.com/vi/${vidId}/mqdefault.jpg" style="opacity:0.6;" alt="Video"></div>`;
                } else {
                    previewHtml = `<div style="position:relative; width:100%; height:100%; background:#f0f0f0; display:flex; flex-direction:column; align-items:center; justify-content:center;"><i class="ph ph-video-camera" style="color:var(--text-muted); font-size:2.5rem; margin-bottom:0.5rem;"></i><span style="font-size:0.7rem; color:var(--text-muted); text-align:center; padding:0 4px; word-break:break-all;">${item.image_path}</span></div>`;
                }
            } else if (item.media_type === 'pdf') {
                const pdfTitle = item.title || (item.isNew ? item.image_path : 'Documento PDF');
                previewHtml = `<div style="position:relative; width:100%; height:100%; background:#f8d7da; display:flex; flex-direction:column; align-items:center; justify-content:center; padding: 0.5rem; box-sizing: border-box;"><i class="ph-fill ph-file-pdf" style="color:#dc3545; font-size:2.5rem; margin-bottom:0.5rem;"></i><span style="font-size:0.7rem; color:#dc3545; text-align:center; word-break:break-all; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">${pdfTitle}</span></div>`;
            } else if (item.media_type === 'web') {
                const webTitle = item.title || item.image_path;
                let thumb = item.thumbnail_url;
                if (!thumb) {
                    try {
                        const urlObj = new URL(item.image_path.startsWith('http') ? item.image_path : 'http://' + item.image_path);
                        thumb = `https://www.google.com/s2/favicons?domain=${urlObj.hostname}&sz=64`;
                    } catch(e) {
                        thumb = `https://www.google.com/s2/favicons?domain=${item.image_path}&sz=64`;
                    }
                }
                previewHtml = `<div style="position:relative; width:100%; height:100%; background:#cce5ff; display:flex; flex-direction:column; align-items:center; justify-content:center; padding: 0.5rem; box-sizing: border-box;"><img src="${thumb}" style="width:32px; height:32px; margin-bottom:0.5rem; border-radius:6px; background:#fff; padding:2px;"><span style="font-size:0.7rem; color:#004085; text-align:center; word-break:break-all; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; font-weight:600;">${webTitle}</span></div>`;
            } else {
                previewHtml = `<img src="${item.isNew ? item.src : 'uploads/services/gallery/' + item.image_path}" alt="Gallery Image">`;
            }

            div.innerHTML = `
                ${previewHtml}
                <button type="button" class="remove-btn" onclick="ServiceFormModule.removeGalleryItem(${i})"><i class="ph ph-x"></i></button>
            `;
            grid.insertBefore(div, uploadBtn);
        });
    },

    toggleGalleryMediaType: function() {
        const type = document.getElementById('gallery_media_type').value;
        const urlInput = document.getElementById('gallery_media_url');
        const fileInput = document.getElementById('gallery_media_file');
        if (type === 'pdf') {
            urlInput.style.display = 'none';
            fileInput.style.display = 'block';
        } else {
            urlInput.style.display = 'block';
            fileInput.style.display = 'none';
        }
    },

    addMediaToGallery: function() {
        const type = document.getElementById('gallery_media_type').value;
        const id = 'new_media_' + Math.random().toString(36).substr(2, 9);
        
        if (type === 'pdf') {
            const input = document.getElementById('gallery_media_file');
            if (input.files && input.files.length > 0) {
                const file = input.files[0];
                // Validate PDF file
                if (file.type !== 'application/pdf') {
                    alert('El archivo debe ser un PDF válido');
                    return;
                }
                if (file.size > 10 * 1024 * 1024) {
                    alert('El archivo PDF no debe superar los 10MB');
                    return;
                }
                this.newGalleryFiles.push({ id: id, file: file, type: 'pdf' });
                
                // Add to gallery state
                this.gallery.push({ id: id, isNew: true, media_type: 'pdf', image_path: file.name });
                input.value = '';
                this.renderGallery();
            } else {
                alert('Por favor selecciona un archivo PDF');
            }
        } else {
            const input = document.getElementById('gallery_media_url');
            let url = input.value.trim();
            if (!url) return;
            
            // Block dangerous schemes
            const lowerUrl = url.toLowerCase().replace(/\s/g, '');
            if (lowerUrl.startsWith('javascript:') || lowerUrl.startsWith('data:') || lowerUrl.startsWith('file:') || lowerUrl.startsWith('vbscript:')) {
                alert('URL no permitida por seguridad');
                return;
            }
            
            // Ensure http/https prefix
            if (!url.match(/^https?:\/\//i)) {
                url = 'https://' + url;
            }
            
            // Validate URL format
            try {
                new URL(url);
            } catch (e) {
                alert('Por favor ingresa una URL válida (ej: https://ejemplo.com)');
                return;
            }

            this.gallery.push({ id: id, isNew: true, media_type: type, image_path: url });
            input.value = '';
            this.renderGallery();
        }
    },

    removeGalleryItem: function(index) {
        const item = this.gallery[index];
        if (item.isNew) {
            // Remove from new files array
            this.newGalleryFiles = this.newGalleryFiles.filter(f => f.id !== item.id);
        }
        this.gallery.splice(index, 1);
        this.renderGallery();
    },

    // ── Sortable Initialization ──
    initSortables: function() {
        if (typeof Sortable !== 'undefined') {
            this.createSortable('featuresList', this.features);
            this.createSortable('deliverablesList', this.deliverables);
            this.createSortable('prereqsList', this.prereqs);
            this.createSortable('faqsList', this.faqs);
            this.createSortable('packagesList', this.packages);
            
            const grid = document.getElementById('galleryGrid');
            if (grid) {
                this.sortableInstances['galleryGrid'] = new Sortable(grid, {
                    animation: 150,
                    filter: '.gallery-upload-btn',
                    onMove: function (evt) {
                        return evt.related.className.indexOf('gallery-upload-btn') === -1;
                    },
                    onEnd: (evt) => {
                        if (evt.oldIndex !== undefined && evt.newIndex !== undefined) {
                            const movedItem = this.gallery.splice(evt.oldIndex, 1)[0];
                            // Insert at new index
                            this.gallery.splice(evt.newIndex, 0, movedItem);
                        }
                    }
                });
            }
        }
    },

    createSortable: function(elementId, arrayRef) {
        const el = document.getElementById(elementId);
        if (el) {
            this.sortableInstances[elementId] = new Sortable(el, {
                animation: 150,
                handle: '.svc-item-drag',
                ghostClass: 'sortable-ghost',
                onEnd: (evt) => {
                    const movedItem = arrayRef.splice(evt.oldIndex, 1)[0];
                    arrayRef.splice(evt.newIndex, 0, movedItem);
                }
            });
        }
    },

    // ── Generic List Rendering ──
    renderAllLists: function() {
        this.renderList('featuresList', this.features, 'Sin características', 'ph-check-circle', 'feature');
        this.renderList('deliverablesList', this.deliverables, 'Sin entregables', 'ph-package', 'deliverable');
        this.renderList('prereqsList', this.prereqs, 'Sin requisitos previos', 'ph-clipboard-text', 'prereq');
        this.renderList('faqsList', this.faqs, 'Sin preguntas frecuentes', 'ph-question', 'faq');
    },

    renderList: function(containerId, arrayData, emptyMsg, iconClass, type) {
        const list = document.getElementById(containerId);
        if (!list) return;
        list.innerHTML = '';
        
        if (arrayData.length === 0) {
            list.innerHTML = `<div style="text-align:center; padding:1rem; color:var(--text-muted); font-size:0.9rem;">${emptyMsg}</div>`;
            return;
        }
        
        arrayData.forEach((item, i) => {
            const title = type === 'faq' ? item.question : item.title;
            const desc = type === 'faq' ? item.answer : item.description;

            const div = document.createElement('div');
            div.className = 'svc-list-item';
            div.innerHTML = `
                <div class="svc-item-drag" title="Arrastrar para reordenar"><i class="ph ph-list"></i></div>
                <div style="flex:1; margin-right: 1rem;">
                    <div class="svc-item-title"><i class="ph ${iconClass}" style="color:var(--primary-color); margin-right:4px;"></i> ${title} ${type === 'deliverable' && item.stage ? `<span style="font-size:0.7rem; background:var(--border-color); padding:2px 6px; border-radius:10px; margin-left:6px; color:var(--text-muted);">${item.stage}</span>` : ''}</div>
                    ${desc ? `<div class="svc-item-desc">${desc.replace(/\n/g, '<br>')}</div>` : ''}
                </div>
                <button type="button" class="btn-icon" onclick="ServiceFormModule.removeItem('${type}', ${i})" title="Eliminar" style="color:var(--color-danger); padding-top: 2px;"><i class="ph ph-trash"></i></button>
            `;
            list.appendChild(div);
        });
    },

    removeItem: function(type, index) {
        if (type === 'feature') this.features.splice(index, 1);
        if (type === 'deliverable') this.deliverables.splice(index, 1);
        if (type === 'prereq') this.prereqs.splice(index, 1);
        if (type === 'faq') this.faqs.splice(index, 1);
        this.renderAllLists();
    },

    // ── Add Item Helpers ──
    addFeature: function() {
        const t = document.getElementById('feature_title');
        const d = document.getElementById('feature_desc');
        if (!t.value.trim()) { alert('Ingresa un título'); t.focus(); return; }
        this.features.push({ title: t.value.trim(), description: d.value.trim() });
        t.value = ''; d.value = '';
        this.renderAllLists(); t.focus();
    },

    addDeliverable: function() {
        const t = document.getElementById('deliverable_title');
        const s = document.getElementById('deliverable_stage');
        const d = document.getElementById('deliverable_desc');
        if (!t.value.trim()) { alert('Ingresa un título'); t.focus(); return; }
        this.deliverables.push({ title: t.value.trim(), stage: s ? s.value.trim() : '', description: d.value.trim() });
        t.value = ''; d.value = ''; if(s) s.value = '';
        this.renderAllLists(); t.focus();
    },

    addPrereq: function() {
        const t = document.getElementById('prereq_title');
        const d = document.getElementById('prereq_desc');
        if (!t.value.trim()) { alert('Ingresa un título'); t.focus(); return; }
        this.prereqs.push({ title: t.value.trim(), description: d.value.trim() });
        t.value = ''; d.value = '';
        this.renderAllLists(); t.focus();
    },

    addFAQ: function() {
        const q = document.getElementById('faq_q');
        const a = document.getElementById('faq_a');
        if (!q.value.trim()) { alert('Ingresa una pregunta'); q.focus(); return; }
        this.faqs.push({ question: q.value.trim(), answer: a.value.trim() });
        q.value = ''; a.value = '';
        this.renderAllLists(); q.focus();
    },

    // ── Save Service ──
    saveService: async function() {
        const form = document.getElementById('serviceForm');
        if (!form.checkValidity()) { 
            form.reportValidity(); 
            const firstInvalid = form.querySelector(':invalid');
            if (firstInvalid) {
                const tabPane = firstInvalid.closest('.svc-tab-content');
                if (tabPane) this.switchTab(tabPane.id);
            }
            return; 
        }

        const formData = new FormData(form);
        
        // Features & Deliverables
        const allFeatures = [
            ...this.features.map((f, i) => ({ title: f.title, description: f.description || '', type: 'feature', sort_order: i, stage: null })),
            ...this.deliverables.map((d, i) => ({ title: d.title, description: d.description || '', type: 'deliverable', sort_order: i + this.features.length, stage: d.stage || null }))
        ];
        formData.append('features', JSON.stringify(allFeatures));
        
        // Prerequisites, FAQs, Packages, Addons
        formData.append('prereqs', JSON.stringify(this.prereqs.map((p, i) => ({ ...p, sort_order: i }))));
        formData.append('faqs', JSON.stringify(this.faqs.map((f, i) => ({ ...f, sort_order: i }))));
        formData.append('packages', JSON.stringify(this.packages.map((p, i) => ({ ...p, sort_order: i }))));
        
        // Addons: ensure pricing_tiers is a string if not already
        const formattedAddons = this.addons.map((a, i) => {
            return {
                ...a,
                sort_order: i,
                pricing_tiers: typeof a.pricing_tiers === 'string' ? a.pricing_tiers : JSON.stringify(a.pricing_tiers || [])
            };
        });
        formData.append('addons', JSON.stringify(formattedAddons));
        
        // Existing Gallery mapping
        const existingGalleryIds = this.gallery.filter(g => !g.isNew).map(g => g.id);
        formData.append('existing_gallery', JSON.stringify(existingGalleryIds));

        // New Gallery Files
        this.newGalleryFiles.forEach((fileObj) => {
            // Check if file is still in gallery array
            const stillExists = this.gallery.some(g => g.id === fileObj.id);
            if (stillExists) {
                formData.append('gallery_files[]', fileObj.file);
            }
        });

        // New Links (Video, Web)
        const newLinks = this.gallery.filter(g => g.isNew && (g.media_type === 'video' || g.media_type === 'web'));
        formData.append('gallery_links', JSON.stringify(newLinks));

        // The final order of the gallery
        const galleryOrder = this.gallery.map(g => g.id);
        formData.append('gallery_order', JSON.stringify(galleryOrder));

        const btn = document.getElementById('btnSaveService');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
        btn.disabled = true;

        try {
            const response = await fetch('modules/services/ajax_save_service.php', { 
                method: 'POST', 
                body: formData 
            });
            const data = await response.json();
            
            if (data.success) {
                window.location.href = 'index.php?module=services';
            } else { 
                if (data.message === 'No autorizado') {
                    alert('Tu sesión ha expirado o no está autorizada. Por favor, inicia sesión de nuevo para continuar.');
                    window.location.href = 'index.php?module=auth&action=login';
                    return;
                }
                alert('Error: ' + data.message); 
                btn.innerHTML = originalText; 
                btn.disabled = false;
            }
        } catch (error) { 
            console.error('Error:', error); 
            alert('Error de conexión al guardar el servicio.'); 
            btn.innerHTML = originalText; 
            btn.disabled = false;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    ServiceFormModule.init();
    
    const existingCover = document.getElementById('existing_cover').value;
    if (existingCover) {
        document.getElementById('prevCover').style.backgroundImage = `url('uploads/services/${existingCover}')`;
        document.getElementById('prevCoverPlaceholder').style.display = 'none';
    }
});
