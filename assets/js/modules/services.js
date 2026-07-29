// assets/js/modules/services.js

const ServiceModule = {
    init: function() {
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl + N for new service
            if (e.ctrlKey && e.key.toLowerCase() === 'n') {
                e.preventDefault();
                window.location.href = 'index.php?module=services&action=form';
            }
        });
    },

    filterServices: function() {
        const searchText = document.getElementById('searchInput').value.toLowerCase();
        const categoryId = document.getElementById('categoryFilter').value;
        const cards = document.querySelectorAll('.service-card');
        
        cards.forEach(card => {
            const name = card.getAttribute('data-name');
            const cat = card.getAttribute('data-category');
            
            const matchSearch = name.includes(searchText);
            const matchCat = categoryId === '' || cat === categoryId;
            
            if (matchSearch && matchCat) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    },

    // ── Categories ──
    openCategoryModal: function() {
        document.getElementById('categoryForm').reset();
        document.getElementById('category_id').value = '';
        document.getElementById('color_tag').value = '#4b5563';
        document.getElementById('btnCancelEditCategory').style.display = 'none';
        
        document.getElementById('categoryFormTitle').style.color = 'var(--color-title)';
        document.getElementById('categoryFormIcon').className = 'ph ph-plus-circle';
        document.getElementById('categoryFormText').textContent = 'Añadir Nueva Categoría';
        document.getElementById('btnSaveCategory').innerHTML = '<span class="btn-text">Guardar Categoría</span>';
        
        document.getElementById('categoryModal').classList.add('active');
    },
    
    closeCategoryModal: function() {
        document.getElementById('categoryModal').classList.remove('active');
    },
    
    editCategory: function(id, name, color) {
        document.getElementById('category_id').value = id;
        document.getElementById('category_name').value = name;
        document.getElementById('color_tag').value = color || '#4b5563';
        document.getElementById('btnCancelEditCategory').style.display = 'block';
        
        document.getElementById('categoryFormTitle').style.color = 'var(--primary-color)';
        document.getElementById('categoryFormIcon').className = 'ph ph-pencil-simple';
        document.getElementById('categoryFormText').textContent = 'Editando: ' + name;
        document.getElementById('btnSaveCategory').innerHTML = '<span class="btn-text">Actualizar Categoría</span>';
    },
    
    cancelEditCategory: function() {
        document.getElementById('category_id').value = '';
        document.getElementById('category_name').value = '';
        document.getElementById('color_tag').value = '#4b5563';
        document.getElementById('btnCancelEditCategory').style.display = 'none';
        
        document.getElementById('categoryFormTitle').style.color = 'var(--color-title)';
        document.getElementById('categoryFormIcon').className = 'ph ph-plus-circle';
        document.getElementById('categoryFormText').textContent = 'Añadir Nueva Categoría';
        document.getElementById('btnSaveCategory').innerHTML = '<span class="btn-text">Guardar Categoría</span>';
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
                    ServiceModule.closeDeleteModal();
                } else { 
                    alert(data.message); 
                }
            } catch (error) { 
                console.error('Error:', error); 
                alert('Error al eliminar la categoría.'); 
            } finally { 
                btnConfirm.innerHTML = 'Sí, eliminar'; 
                btnConfirm.disabled = false; 
            }
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
                // Simplest approach: reload to see changes everywhere including cards
                window.location.reload();
            } else { 
                alert('Error: ' + data.message); 
            }
        } catch (error) { 
            console.error('Error:', error); 
            alert('Error al guardar la categoría.'); 
        } finally { 
            btn.innerHTML = originalText; 
            btn.disabled = false; 
        }
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
                    const card = document.getElementById(`service-card-${id}`);
                    if (card) card.remove();
                    
                    const grid = document.getElementById('servicesGrid');
                    if (grid && grid.querySelectorAll('.service-card').length === 0) {
                        grid.innerHTML = `
                            <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: var(--radius-lg);">
                                <div style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"><i class="ph ph-briefcase"></i></div>
                                <h3 style="color: var(--color-title); font-size: 1.2rem; margin-bottom: 0.5rem;">No hay servicios registrados</h3>
                                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Comienza creando tu primer servicio para ofrecer a tus clientes.</p>
                                <a href="index.php?module=services&action=form" class="btn btn-primary"><i class="ph ph-plus"></i> Crear Servicio</a>
                            </div>`;
                    }
                    ServiceModule.closeDeleteModal();
                } else { 
                    alert(data.message); 
                }
            } catch (error) { 
                console.error('Error:', error); 
                alert('Error al eliminar el servicio.'); 
            } finally { 
                btnConfirm.innerHTML = 'Sí, eliminar'; 
                btnConfirm.disabled = false; 
            }
        };
    },

    closeDeleteModal: function() {
        document.getElementById('deleteConfirmModal').classList.remove('active');
    },

    restoreService: async function(id) {
        if (!confirm('¿Estás seguro de que deseas restaurar este servicio?')) return;
        
        try {
            const formData = new FormData();
            formData.append('id', id);
            
            const response = await fetch('modules/services/ajax_restore_service.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                // Remove card from UI or reload
                const card = document.getElementById('service-card-' + id);
                if (card) {
                    card.style.transform = 'scale(0.9)';
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 200);
                } else {
                    window.location.reload();
                }
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error de conexión.');
        }
    },
    
    copyLink: function(btn, link) {
        navigator.clipboard.writeText(link).then(() => {
            const icon = btn.querySelector('i');
            const originalClass = icon.className;
            icon.className = 'ph ph-check';
            btn.style.color = 'var(--success-color, #10b981)';
            
            setTimeout(() => {
                icon.className = originalClass;
                btn.style.color = '';
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy link: ', err);
            alert('Error al copiar el enlace.');
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    ServiceModule.init();
});
