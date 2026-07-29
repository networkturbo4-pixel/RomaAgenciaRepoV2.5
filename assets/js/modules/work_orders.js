// assets/js/modules/work_orders.js

const WorkOrderModule = {
    modal: document.getElementById('workOrderModal'),
    form: document.getElementById('workOrderForm'),
    title: document.getElementById('workOrderModalTitle'),

    openModal: function() {
        this.form.reset();
        document.getElementById('work_order_id').value = '';
        this.title.textContent = 'Nueva Orden de Servicio';
        this.modal.classList.add('active');
    },

    closeModal: function() {
        this.modal.classList.remove('active');
    },

    saveOrder: async function() {
        if (!this.form.checkValidity()) {
            this.form.reportValidity();
            return;
        }

        const formData = new FormData(this.form);
        const btn = document.getElementById('btnSaveWorkOrder');
        const btnText = btn.querySelector('.btn-text');
        const originalText = btnText.textContent;

        try {
            btn.disabled = true;
            btnText.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';

            const response = await fetch('index.php?module=work_orders&action=ajax_save_order', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                location.reload();
            } else {
                alert(result.message || 'Error al guardar la orden.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error de conexión.');
        } finally {
            btn.disabled = false;
            btnText.textContent = originalText;
        }
    },

    editOrder: async function(id) {
        try {
            const response = await fetch(`index.php?module=work_orders&action=ajax_get_order&id=${id}`);
            const result = await response.json();

            if (result.success) {
                const data = result.data;
                document.getElementById('work_order_id').value = data.id;
                document.getElementById('brand_id').value = data.brand_id;
                document.getElementById('wo_service').value = data.service;
                
                this.title.textContent = `Editar Orden de Servicio: ${data.correlativo}`;
                this.modal.classList.add('active');
            } else {
                alert(result.message || 'Error al obtener datos.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error de conexión.');
        }
    },

    confirmDelete: function(id) {
        document.getElementById('delete_wo_id').value = id;
        document.getElementById('modal-delete-order').classList.add('active');
    },

    executeDelete: async function() {
        const id = document.getElementById('delete_wo_id').value;
        const btn = document.getElementById('btnConfirmDeleteWo');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Eliminando...';
        btn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('id', id);

            const response = await fetch('index.php?module=work_orders&action=ajax_delete_order', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                location.reload();
            } else {
                alert(result.message || 'Error al eliminar la orden.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error de conexión.');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    },

    shareOrder: function(token) {
        const url = window.location.origin + window.location.pathname + '?module=work_orders&action=public&token=' + token;
        document.getElementById('shareLinkInput').value = url;
        document.getElementById('shareModal').classList.add('active');
    },

    copyShareLink: function() {
        const input = document.getElementById('shareLinkInput');
        input.select();
        document.execCommand('copy');
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: '¡Enlace copiado al portapapeles!',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        } else {
            alert('¡Enlace copiado al portapapeles!');
        }
    },

    archiveOrder: function(id) {
        document.getElementById('archive_wo_id').value = id;
        document.getElementById('modal-archive-order').classList.add('active');
    },

    executeArchive: function() {
        const id = document.getElementById('archive_wo_id').value;
        const btn = document.getElementById('btnConfirmArchiveWo');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Procesando...';
        btn.disabled = true;

        fetch('modules/work_orders/ajax_archive.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('modal-archive-order').classList.remove('active');
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: data.is_archived === 1 ? 'Orden archivada' : 'Orden restaurada',
                        showConfirmButton: false,
                        timer: 2500,
                        background: 'var(--bg-surface)',
                        color: 'var(--color-text)'
                    });
                }
                setTimeout(() => window.location.reload(), 1000);
            } else {
                btn.innerHTML = originalText;
                btn.disabled = false;
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'No se pudo modificar el estado.',
                        confirmButtonColor: '#0f766e',
                        background: 'var(--bg-surface)',
                        color: 'var(--color-text)'
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            btn.innerHTML = originalText;
            btn.disabled = false;
            document.getElementById('modal-archive-order').classList.remove('active');
        });
    }
};
