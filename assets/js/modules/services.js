// assets/js/modules/services.js

const ServiceModule = {
    features: [],
    deliverables: [],

    init: function() {},

    openServiceModal: function() {
        document.getElementById('serviceForm').reset();
        document.getElementById('service_id').value = '';
        this.features = [];
        this.deliverables = [];
        this.renderFeatures();
        this.renderDeliverables();
        document.getElementById('serviceModalTitle').innerText = 'Nuevo Servicio';
        document.getElementById('serviceModal').classList.add('active');
    },

    closeServiceModal: function() {
        document.getElementById('serviceModal').classList.remove('active');
    },

    // ── Features ──
    addFeature: function() {
        const titleInput = document.getElementById('feature_title');
        const descInput = document.getElementById('feature_desc');
        const title = titleInput.value.trim();
        const description = descInput.value.trim();
        if (!title) { alert('Escribe una característica.'); return; }
        this.features.push({ title, description });
        titleInput.value = '';
        descInput.value = '';
        this.renderFeatures();
    },

    removeFeature: function(index) {
        this.features.splice(index, 1);
        this.renderFeatures();
    },

    renderFeatures: function() {
        const list = document.getElementById('featuresList');
        list.innerHTML = '';
        if (this.features.length === 0) {
            list.innerHTML = '<span style="color:var(--text-muted);font-size:0.8rem;">Sin características.</span>';
            return;
        }
        this.features.forEach((f, i) => {
            const item = document.createElement('div');
            item.className = 'svc-feature-item';
            item.innerHTML = `
                <div style="flex:1;">
                    <div style="font-size:0.85rem;font-weight:600;color:var(--color-title);">${f.title}</div>
                    ${f.description ? `<div style="font-size:0.78rem;color:var(--text-muted);margin-top:0.2rem;">${f.description}</div>` : ''}
                </div>
                <button type="button" class="btn-icon" onclick="ServiceModule.removeFeature(${i})" title="Eliminar" style="color:var(--color-danger);flex-shrink:0;"><i class="ph ph-x"></i></button>
            `;
            list.appendChild(item);
        });
    },

    // ── Deliverables ──
    addDeliverable: function() {
        const titleInput = document.getElementById('deliverable_title');
        const descInput = document.getElementById('deliverable_desc');
        const title = titleInput.value.trim();
        const description = descInput.value.trim();
        if (!title) { alert('Escribe un entregable.'); return; }
        this.deliverables.push({ title, description });
        titleInput.value = '';
        descInput.value = '';
        this.renderDeliverables();
    },

    removeDeliverable: function(index) {
        this.deliverables.splice(index, 1);
        this.renderDeliverables();
    },

    renderDeliverables: function() {
        const list = document.getElementById('deliverablesList');
        list.innerHTML = '';
        if (this.deliverables.length === 0) {
            list.innerHTML = '<span style="color:var(--text-muted);font-size:0.8rem;">Sin entregables.</span>';
            return;
        }
        this.deliverables.forEach((d, i) => {
            const item = document.createElement('div');
            item.className = 'svc-feature-item';
            item.innerHTML = `
                <div style="flex:1;">
                    <div style="font-size:0.85rem;font-weight:600;color:var(--color-title);">${d.title}</div>
                    ${d.description ? `<div style="font-size:0.78rem;color:var(--text-muted);margin-top:0.2rem;">${d.description}</div>` : ''}
                </div>
                <button type="button" class="btn-icon" onclick="ServiceModule.removeDeliverable(${i})" title="Eliminar" style="color:var(--color-danger);flex-shrink:0;"><i class="ph ph-x"></i></button>
            `;
            list.appendChild(item);
        });
    },

    // ── Categories ──
    openCategoryModal: function() {
        document.getElementById('categoryForm').reset();
        document.getElementById('category_id').value = '';
        document.getElementById('btnCancelEditCategory').style.display = 'none';
        document.getElementById('categoryModal').classList.add('active');
    },
    closeCategoryModal: function() {
        document.getElementById('categoryModal').classList.remove('active');
    },
    editCategory: function(id, name) {
        document.getElementById('category_id').value = id;
        document.getElementById('category_name').value = name;
        document.getElementById('btnCancelEditCategory').style.display = 'block';
    },
    cancelEditCategory: function() {
        document.getElementById('category_id').value = '';
        document.getElementById('category_name').value = '';
        document.getElementById('btnCancelEditCategory').style.display = 'none';
    },

    deleteCategory: function(id) {
        document.getElementById('deleteConfirmModal').classList.add('active');
        const btnConfirm = document.getElementById('btnConfirmDelete');
        btnConfirm.onclick = async () => {
            btnConfirm.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Eliminando...';
            btnConfirm.disabled = true;
            try {
                const formData = new FormData();
                formData.append('id', id);
                const response = await fetch('modules/services/ajax_delete_category.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    const row = document.getElementById(`cat-row-${id}`);
                    if (row) row.remove();
                    const select = document.getElementById('service_category');
                    for (let i = 0; i < select.options.length; i++) {
                        if (select.options[i].value == id) { select.remove(i); break; }
                    }
                    ServiceModule.closeDeleteModal();
                } else { alert(data.message); }
            } catch (error) { console.error('Error:', error); alert('Error al eliminar la categoría.'); }
            finally { btnConfirm.innerHTML = 'Sí, eliminar'; btnConfirm.disabled = false; }
        };
    },

    saveCategory: async function() {
        const form = document.getElementById('categoryForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const formData = new FormData(form);
        const btn = document.getElementById('btnSaveCategory');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i>';
        btn.disabled = true;
        try {
            const response = await fetch('modules/services/ajax_save_category.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                if (data.is_update) {
                    const cell = document.getElementById(`cat-name-${data.category.id}`);
                    if (cell) cell.innerText = data.category.name;
                    const select = document.getElementById('service_category');
                    for (let i = 0; i < select.options.length; i++) {
                        if (select.options[i].value == data.category.id) { select.options[i].text = data.category.name; break; }
                    }
                } else {
                    const tbody = document.getElementById('categoriesTableBody');
                    const noDataRow = tbody.querySelector('td[colspan]');
                    if (noDataRow) noDataRow.parentElement.remove();
                    const tr = document.createElement('tr');
                    tr.id = `cat-row-${data.category.id}`;
                    tr.innerHTML = `
                        <td id="cat-name-${data.category.id}">${data.category.name}</td>
                        <td style="text-align: right;">
                            <button class="btn-icon" onclick="ServiceModule.editCategory(${data.category.id}, '${data.category.name.replace(/'/g, "\\'")}')" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                            <button class="btn-icon" onclick="ServiceModule.deleteCategory(${data.category.id})" title="Eliminar" style="color: var(--color-danger);"><i class="ph ph-trash"></i></button>
                        </td>`;
                    tbody.appendChild(tr);
                    const select = document.getElementById('service_category');
                    const option = document.createElement('option');
                    option.value = data.category.id;
                    option.text = data.category.name;
                    select.appendChild(option);
                }
                this.cancelEditCategory();
                alert('¡Éxito! ' + data.message);
            } else { alert('Error: ' + data.message); }
        } catch (error) { console.error('Error:', error); alert('Error al guardar la categoría.'); }
        finally { btn.innerHTML = originalText; btn.disabled = false; }
    },

    // ── Save Service ──
    saveService: async function() {
        const form = document.getElementById('serviceForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const formData = new FormData(form);
        // Combine features + deliverables into single JSON (with type flag)
        const allFeatures = [
            ...this.features.map(f => ({ title: f.title, description: f.description || '', type: 'feature' })),
            ...this.deliverables.map(d => ({ title: d.title, description: d.description || '', type: 'deliverable' }))
        ];
        formData.append('features', JSON.stringify(allFeatures));

        const btn = document.getElementById('btnSaveService');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
        btn.disabled = true;
        try {
            const response = await fetch('modules/services/ajax_save_service.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                this.closeServiceModal();
                if (data.is_update) {
                    document.getElementById(`service-name-${data.service.id}`).innerText = data.service.name;
                    document.getElementById(`service-desc-${data.service.id}`).innerText = data.service.description || '';
                    document.getElementById(`service-cat-${data.service.id}`).innerText = data.service.category_name;
                    document.getElementById(`service-price-${data.service.id}`).innerText = 'USD ' + parseFloat(data.service.price).toFixed(2);
                } else {
                    const tbody = document.querySelector('.card .table tbody');
                    const noDataRow = tbody.querySelector('td[colspan]');
                    if (noDataRow) noDataRow.parentElement.remove();
                    const tr = document.createElement('tr');
                    tr.id = `service-row-${data.service.id}`;
                    tr.innerHTML = `
                        <td data-label="SERVICIO">
                            <div style="font-weight:500;color:var(--color-title);" id="service-name-${data.service.id}">${data.service.name}</div>
                            <div style="font-size:0.875rem;color:var(--color-text);margin-top:0.25rem;" id="service-desc-${data.service.id}">${data.service.description || ''}</div>
                        </td>
                        <td data-label="CATEGORÍA">
                            <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;background:var(--bg-body);border-radius:4px;font-size:0.75rem;border:1px solid var(--border-color);">
                                <i class="ph ph-folder" style="color:var(--color-text);"></i>
                                <span id="service-cat-${data.service.id}">${data.service.category_name}</span>
                            </span>
                        </td>
                        <td data-label="PRECIO">
                            <div style="font-weight:600;color:var(--primary-color);" id="service-price-${data.service.id}">USD ${parseFloat(data.service.price).toFixed(2)}</div>
                        </td>
                        <td data-label="ACCIONES" style="text-align:right;">
                            <div class="action-buttons" style="display:flex;justify-content:flex-end;gap:0.5rem;">
                                <button class="btn-icon" onclick="ServiceModule.editService(${data.service.id})" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                                <button class="btn-icon" onclick="ServiceModule.deleteService(${data.service.id})" title="Eliminar" style="color:var(--color-danger);"><i class="ph ph-trash"></i></button>
                            </div>
                        </td>`;
                    tbody.insertBefore(tr, tbody.firstChild);
                }
                alert('¡Éxito! ' + data.message);
            } else { alert('Error: ' + data.message); }
        } catch (error) { console.error('Error:', error); alert('Error al guardar el servicio.'); }
        finally { btn.innerHTML = originalText; btn.disabled = false; }
    },

    // ── Edit Service ──
    editService: async function(id) {
        try {
            const response = await fetch(`modules/services/ajax_get_service.php?id=${id}`);
            const data = await response.json();
            if (data.success) {
                const service = data.data;
                document.getElementById('service_id').value = service.id;
                document.getElementById('service_name').value = service.name;
                document.getElementById('service_category').value = service.category_id;
                document.getElementById('service_description').value = service.description;
                document.getElementById('service_price').value = service.price;

                // Split features by type
                const allFeatures = service.features || [];
                this.features = allFeatures.filter(f => f.type !== 'deliverable');
                this.deliverables = allFeatures.filter(f => f.type === 'deliverable');
                this.renderFeatures();
                this.renderDeliverables();

                document.getElementById('serviceModalTitle').innerText = 'Editar Servicio';
                document.getElementById('serviceModal').classList.add('active');
            } else { alert('Error: ' + data.message); }
        } catch (error) { console.error('Error:', error); alert('Error al obtener los datos del servicio.'); }
    },

    // ── Delete Service ──
    deleteService: function(id) {
        document.getElementById('deleteConfirmModal').classList.add('active');
        const btnConfirm = document.getElementById('btnConfirmDelete');
        btnConfirm.onclick = async () => {
            btnConfirm.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Eliminando...';
            btnConfirm.disabled = true;
            try {
                const formData = new FormData();
                formData.append('id', id);
                const response = await fetch('modules/services/ajax_delete_service.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    const row = document.getElementById(`service-row-${id}`);
                    if (row) row.remove();
                    const tbody = document.querySelector('.card .table tbody');
                    if (tbody && tbody.children.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--color-text);">No hay servicios registrados.</td></tr>';
                    }
                    ServiceModule.closeDeleteModal();
                } else { alert(data.message); }
            } catch (error) { console.error('Error:', error); alert('Error al eliminar el servicio.'); }
            finally { btnConfirm.innerHTML = 'Sí, eliminar'; btnConfirm.disabled = false; }
        };
    },

    closeDeleteModal: function() {
        document.getElementById('deleteConfirmModal').classList.remove('active');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    ServiceModule.init();
});
