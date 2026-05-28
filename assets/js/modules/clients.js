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

            row.innerHTML = `
                <div style="display: flex; align-items: center; flex: 1;">
                    ${imgHtml}
                    <span style="font-weight: 500;">${brand.name}</span>
                </div>
                <button type="button" class="btn-icon" onclick="ClientModule.removeBrand(${index})" style="color: var(--color-danger); padding: 0.25rem;">
                    <i class="ph ph-trash"></i>
                </button>
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
                logo: null
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
                    
                    brandsArray = data.brands || [];
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
