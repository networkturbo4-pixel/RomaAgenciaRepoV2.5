// assets/js/modules/clients.js
const ClientModule = (function() {
    let brandsArray = [];
    let deletedBrands = [];
    let modalEl;

    function init() {
        modalEl = document.getElementById('clientModal');
    }

    function renderBrands() {
        const container = document.getElementById('brandsList');
        container.innerHTML = '';
        
        if (brandsArray.length === 0) {
            container.innerHTML = '<div style="text-align: center; color: var(--color-text); font-size: 0.875rem; padding: 1rem 0;">Sin marcas agregadas.</div>';
            return;
        }

        brandsArray.forEach((brand, index) => {
            const row = document.createElement('div');
            row.style.display = 'flex';
            row.style.alignItems = 'center';
            row.style.justifyContent = 'space-between';
            row.style.padding = '0.5rem';
            row.style.background = 'var(--bg-body)';
            row.style.border = '1px solid var(--border-color)';
            row.style.borderRadius = 'var(--radius-md)';

            let imgHtml = '<div style="width: 32px; height: 32px; background: #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin-right: 1rem;"><i class="ph ph-image"></i></div>';
            
            if (brand.file) {
                const objectUrl = URL.createObjectURL(brand.file);
                imgHtml = `<img src="${objectUrl}" style="width: 32px; height: 32px; object-fit: cover; border-radius: 4px; margin-right: 1rem;">`;
            } else if (brand.logo) {
                imgHtml = `<img src="${brand.logo}" style="width: 32px; height: 32px; object-fit: cover; border-radius: 4px; margin-right: 1rem;">`;
            }

            // Select options for services (Modern UI)
            let servicesListHtml = '';
            let selectedBadgesHtml = '';
            
            if (typeof SYSTEM_SERVICES !== 'undefined') {
                SYSTEM_SERVICES.forEach(s => {
                    const isSelected = (brand.services_ids && brand.services_ids.includes(s.id.toString()));
                    const checkedAttr = isSelected ? 'checked' : '';
                    
                    if (isSelected) {
                        selectedBadgesHtml += `<span style="background: var(--primary-bg); color: var(--primary-color); padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; margin: 2px;">${s.name}</span>`;
                    }

                    servicesListHtml += `
                        <label style="display: flex; align-items: center; gap: 8px; padding: 6px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color); margin: 0; font-size: 12px;">
                            <input type="checkbox" value="${s.id}" class="brand-service-checkbox-${index}" ${checkedAttr} onchange="ClientModule.updateCustomBrandServices(${index})">
                            <span>${s.name}</span>
                        </label>
                    `;
                });
            }
            if (!selectedBadgesHtml) {
                selectedBadgesHtml = '<span style="color: var(--text-muted); font-size: 12px; padding: 4px;">Ninguno</span>';
            }

            const hasMembershipChecked = brand.has_membership == 1 ? 'checked' : '';

            row.innerHTML = `
                <div style="display: flex; flex-direction: column; width: 100%; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; flex: 1;">
                            ${imgHtml}
                            <span style="font-weight: 600; font-size: 13px;">${brand.name}</span>
                        </div>
                        <button type="button" class="btn-icon" onclick="ClientModule.removeBrand(${index})" style="color: var(--color-danger); padding: 0.25rem;">
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; align-items: flex-start; flex-wrap: wrap; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed var(--border-color);">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="has_membership_${index}" onchange="ClientModule.updateBrand(${index}, 'has_membership', this.checked ? 1 : 0)" ${hasMembershipChecked}>
                            <label for="has_membership_${index}" style="font-size: 12px; font-weight: 500; cursor: pointer;">Tiene Membresía</label>
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Servicios Asignados</label>
                            
                            <div class="custom-multiselect" style="position: relative;">
                                <div class="custom-multiselect-header" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block'" style="border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 4px 8px; background: var(--bg-surface); cursor: pointer; min-height: 36px; display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                                    ${selectedBadgesHtml}
                                    <i class="ph ph-caret-down" style="margin-left: auto; color: var(--text-muted);"></i>
                                </div>
                                <div class="custom-multiselect-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-top: 4px; max-height: 150px; overflow-y: auto; z-index: 10; box-shadow: var(--shadow-md);">
                                    ${servicesListHtml}
                                </div>
                            </div>
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">ID de Grupo WhatsApp (Opcional)</label>
                            <input type="text" class="form-control" style="font-size: 12px; padding: 0.35rem 0.5rem; height: 36px;" value="${brand.whatsapp_group || ''}" onchange="ClientModule.updateBrand(${index}, 'whatsapp_group', this.value)" placeholder="Ej: 12345678@g.us">
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(row);
        });
    }

    return {
        init: init,
        openModal: function() {
            document.getElementById('clientForm').reset();
            document.getElementById('client_id').value = '';
            document.getElementById('client_dni').value = '';
            document.getElementById('client_drive_folder_id').value = '';
            document.getElementById('clientModalTitle').innerText = 'Nuevo Cliente';
            brandsArray = [];
            deletedBrands = [];
            renderBrands();
            modalEl.classList.add('active');
        },
        closeModal: function() {
            modalEl.classList.remove('active');
        },
        addBrand: function() {
            const nameInput = document.getElementById('new_brand_name');
            const fileInput = document.getElementById('new_brand_logo');
            
            const name = nameInput.value.trim();
            if (!name) {
                alert('El nombre de la marca es requerido.');
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
            renderBrands();
        },
        removeBrand: function(index) {
            const brand = brandsArray[index];
            if (brand.id) {
                deletedBrands.push(brand.id);
            }
            brandsArray.splice(index, 1);
            renderBrands();
        },
        updateBrand: function(index, key, value) {
            brandsArray[index][key] = value;
        },
        updateCustomBrandServices: function(index) {
            const checkboxes = document.querySelectorAll(`.brand-service-checkbox-${index}:checked`);
            const selectedOptions = Array.from(checkboxes).map(cb => cb.value);
            brandsArray[index].services_ids = selectedOptions;
            
            // Re-render to update badges
            ClientModule.renderBrandsOnly();
        },
        renderBrandsOnly: function() {
            renderBrands();
        },
        saveClient: async function() {
            const form = document.getElementById('clientForm');
            if (!form.reportValidity()) return;

            const btnSave = document.getElementById('btnSaveClient');
            btnSave.disabled = true;
            btnSave.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';

            const formData = new FormData(form);
            
            // Append brands
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
                    window.location.reload();
                } else {
                    alert('Error al guardar: ' + (data.error || 'Error desconocido'));
                    btnSave.disabled = false;
                    btnSave.innerHTML = 'Guardar Cliente';
                }
            } catch (err) {
                console.error(err);
                alert('Error de conexión.');
                btnSave.disabled = false;
                btnSave.innerHTML = 'Guardar Cliente';
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
                    document.getElementById('client_whatsapp').value = client.whatsapp;
                    document.getElementById('client_email').value = client.email;
                    document.getElementById('client_drive_folder_id').value = client.drive_folder_id || '';
                    
                    
                    // Parse services_ids from string to array if necessary
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
                    
                    document.getElementById('clientModalTitle').innerText = 'Editar Cliente';
                    renderBrands();
                    modalEl.classList.add('active');
                } else {
                    alert('No se pudo cargar la información del cliente.');
                }
            } catch (err) {
                console.error(err);
                alert('Error al obtener cliente.');
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
                        window.location.reload();
                    } else {
                        alert('Error al eliminar.');
                    }
                } catch (err) {
                    alert('Error de conexión.');
                }
            }
        }
    };
})();

document.addEventListener('DOMContentLoaded', ClientModule.init);
