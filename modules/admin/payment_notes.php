<?php
// modules/admin/payment_notes.php
require_once 'includes/header.php';
?>

<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="width: 56px; height: 56px; background: var(--bg-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
            <i class="ph ph-receipt" style="font-size: 1.75rem; color: var(--primary-color);"></i>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Notas de Pago</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Gestiona y visualiza las notas de pago de clientes.</p>
        </div>
    </div>
    <div style="display: flex; align-items: center;">
        <button class="btn btn-primary" onclick="openNewNoteModal()" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px;">
            <i class="ph ph-plus"></i> Nueva Nota
        </button>
    </div>
</div>

<!-- Summary Cards Container -->
<div id="summary-cards-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Cards will be injected here via JS -->
</div>

<!-- Container for dynamically rendered cards -->
<div id="notes-grid" class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
    <!-- Cards will be injected here via JS -->
</div>

<!-- Empty state -->
<div id="empty-state" style="display: none; text-align: center; padding: 3rem 1rem;">
    <div style="background: var(--bg-surface); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-4); color: var(--text-muted);">
        <i class="ph ph-receipt" style="font-size: 2.5rem;"></i>
    </div>
    <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--text-main); margin-bottom: var(--space-2);">No hay notas de pago</h3>
    <p style="color: var(--text-muted); margin-bottom: var(--space-4);">Crea tu primera nota de pago para empezar a cobrar a tus clientes.</p>
    <button class="btn btn-primary" onclick="openNewNoteModal()">
        <i class="ph ph-plus"></i> Crear Primera Nota
    </button>
</div>

<!-- Modal: New Payment Note -->
<div class="modal-overlay" id="modal-new-note">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ph ph-plus"></i> Nueva Nota de Pago</h3>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Cliente / Contacto</label>
                <select id="new-note-client" class="form-control">
                    <option value="">Selecciona un cliente...</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Empresa / Marca</label>
                <select id="new-note-company" class="form-control">
                    <option value="">Selecciona una marca...</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Fecha de Inicio</label>
                    <input type="date" id="new-note-date" class="form-control">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Monto Total (S/)</label>
                    <input type="number" id="new-note-total" class="form-control" placeholder="0.00" step="0.01" min="0">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-close-modal">Cancelar</button>
            <button type="button" id="btn-create-note" class="btn btn-primary">Crear Nota</button>
        </div>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal-overlay" id="modal-delete-note">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title" style="color: var(--danger-color);"><i class="ph ph-warning-circle"></i> Eliminar Nota</h3>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <p>¿Estás seguro de que deseas eliminar la nota <strong id="delete-note-id"></strong>? Esta acción no se puede deshacer.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-close-modal">Cancelar</button>
            <button type="button" id="btn-confirm-delete" class="btn btn-primary" style="background: var(--danger-color); border-color: var(--danger-color);">Eliminar</button>
        </div>
    </div>
</div>

<!-- Modal: Share Note -->
<div class="modal-overlay" id="modal-share-note">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ph ph-share-network"></i> Compartir Nota</h3>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: var(--space-4);">Comparte este enlace con tu cliente para que pueda visualizar su nota de pago y los métodos disponibles.</p>
            <div class="form-group">
                <label>Enlace Público</label>
                <div class="d-flex" style="gap: 8px;">
                    <input type="text" id="share-link-input" class="form-control" readonly>
                    <button type="button" id="btn-copy-link" class="btn btn-primary">
                        <i class="ph ph-copy"></i> Copiar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // State
    let notes = [];
    let noteIdCounter = 11;
    let noteToDeleteIndex = null;

    // Elements
    const notesGrid = document.getElementById('notes-grid');
    const emptyState = document.getElementById('empty-state');
    const modalDeleteNote = document.getElementById('modal-delete-note');
    const btnConfirmDelete = document.getElementById('btn-confirm-delete');
    const modalShareNote = document.getElementById('modal-share-note');
    const shareLinkInput = document.getElementById('share-link-input');
    const btnCopyLink = document.getElementById('btn-copy-link');
    const modalNewNote = document.getElementById('modal-new-note');
    const btnCreateNote = document.getElementById('btn-create-note');
    
    // Clients
    let availableClients = [];

    // Default Date
    document.getElementById('new-note-date').valueAsDate = new Date();

    // Fetch and Migrate
    async function loadNotes() {
        // Auto-migrate from localStorage if exists
        const localNotes = localStorage.getItem('payment_notes');
        if (localNotes) {
            try {
                const parsed = JSON.parse(localNotes);
                if (parsed.length > 0) {
                    const res = await fetch('modules/admin/ajax_migrate_payment_notes.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ notes: parsed })
                    });
                    if (res.ok) {
                        const migrationData = await res.json();
                        if (migrationData.success === true) {
                            localStorage.removeItem('payment_notes');
                        } else {
                            console.error("Migration failed: ", migrationData.error);
                        }
                    }
                } else {
                    localStorage.removeItem('payment_notes');
                }
            } catch(e) {
                console.error('Migration error', e);
            }
        }

        try {
            const res = await fetch('modules/admin/ajax_get_payment_notes.php');
            const data = await res.json();
            if (data.success) {
                notes = data.notes;
                if (data.clients) availableClients = data.clients;
                renderNotes();
            }
        } catch(e) {
            console.error('Fetch error', e);
        }
    }

    // Render Cards
    function renderNotes() {
        notesGrid.innerHTML = '';
        const summaryContainer = document.getElementById('summary-cards-container');
        
        if (notes.length === 0) {
            notesGrid.style.display = 'none';
            if (summaryContainer) summaryContainer.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }

        notesGrid.style.display = 'grid';
        if (summaryContainer) summaryContainer.style.display = 'grid';
        emptyState.style.display = 'none';

        let totalNotas = notes.length;
        let countPendientes = 0;
        let countPagados = 0;
        let saldoPendienteTotal = 0;

        notes.forEach((note, index) => {
            let noteStatusText = 'Pagado';
            let statusBg = 'rgba(16, 185, 129, 0.15)';
            let statusColor = 'var(--secondary-color)';
            let isPagado = true;

            if (note.cronograma && note.cronograma.length > 0) {
                let hasRetrasado = false;
                let hasEnProceso = false;
                let hasNoActivo = false;
                
                note.cronograma.forEach(c => {
                    if (c.estado !== 'pagado') {
                        isPagado = false;
                        if (!c.fecha) {
                            hasEnProceso = true;
                            return;
                        }
                        const today = new Date();
                        today.setHours(0,0,0,0);
                        const parts = c.fecha.split('-');
                        const cuotaDate = new Date(parts[0], parts[1] - 1, parts[2]);
                        const diffTime = cuotaDate.getTime() - today.getTime();
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        
                        if (diffDays < -15) {
                            hasRetrasado = true;
                        } else if (diffDays <= 7 && diffDays >= -15) {
                            hasEnProceso = true;
                        } else {
                            hasNoActivo = true;
                        }
                    }
                });

                if (!isPagado) {
                    if (hasRetrasado) {
                        noteStatusText = 'Retrasado';
                        statusBg = 'rgba(239, 68, 68, 0.15)';
                        statusColor = '#ef4444';
                    } else if (hasEnProceso) {
                        noteStatusText = 'En proceso';
                        statusBg = 'rgba(245, 158, 11, 0.15)';
                        statusColor = 'var(--warning-color)';
                    } else if (hasNoActivo) {
                        noteStatusText = 'No Activo';
                        statusBg = 'rgba(100, 116, 139, 0.15)';
                        statusColor = '#64748b';
                    } else {
                        noteStatusText = 'Pendiente';
                        statusBg = 'rgba(245, 158, 11, 0.15)';
                        statusColor = 'var(--warning-color)';
                    }
                }
            } else {
                isPagado = note.status === 'PAGADO';
                if (!isPagado) {
                    noteStatusText = 'En proceso';
                    statusBg = 'rgba(245, 158, 11, 0.15)';
                    statusColor = 'var(--warning-color)';
                }
            }

            let computedTotal = parseFloat(note.total || 0);
            if ((note.servicios && note.servicios.length > 0) || (note.cronograma && note.cronograma.length > 0)) {
                let noteTotalServicios = 0;
                let noteTotalPendiente = 0;
                
                if (note.servicios) {
                    noteTotalServicios = note.servicios.reduce((sum, s) => sum + (parseFloat(s.cantidad || 0) * parseFloat(s.costoUnit || 0)), 0);
                }

                if (note.cronograma) {
                    note.cronograma.forEach(c => {
                        if (c.estado === 'pagado') return;
                        if (!c.fecha) {
                            noteTotalPendiente += parseFloat(c.monto || 0);
                            return;
                        }
                        const today = new Date();
                        today.setHours(0,0,0,0);
                        const parts = c.fecha.split('-');
                        const cuotaDate = new Date(parts[0], parts[1] - 1, parts[2]);
                        const diffTime = cuotaDate.getTime() - today.getTime();
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        
                        if (diffDays <= 7) { 
                            noteTotalPendiente += parseFloat(c.monto || 0);
                        }
                    });
                }
                computedTotal = noteTotalServicios + noteTotalPendiente;
            }

            const balance = isPagado ? 0 : computedTotal;

            if (noteStatusText === 'Pagado') {
                countPagados++;
            } else if (noteStatusText === 'En proceso' || noteStatusText === 'Pendiente' || noteStatusText === 'Retrasado') {
                countPendientes++;
            }

            saldoPendienteTotal += parseFloat(balance || 0);

            const card = document.createElement('div');
            card.className = 'card';
            card.style.padding = '1.25rem';
            card.style.display = 'flex';
            card.style.flexDirection = 'column';
            card.style.gap = '1rem';

            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-weight: 700; color: var(--primary-color); font-size: 1.1rem;">${note.id}</span>
                    <span style="background: ${statusBg}; color: ${statusColor}; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 700;">${noteStatusText}</span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 8px; height: 8px; border-radius: 50%; background-color: var(--secondary-color);"></div>
                    <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Visto</span>
                </div>

                <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--bg-color); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                        <i class="ph ph-user" style="font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; font-size: 1rem;">${note.client}</div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">${note.company}</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between" style="margin-top: 0.5rem;">
                    <div>
                        <div style="font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Monto Total</div>
                        <div style="font-size: 0.95rem; font-weight: 600; ${!isPagado ? 'text-decoration: line-through; color: var(--text-muted);' : 'color: var(--text-main);'}">S/ ${parseFloat(note.total).toFixed(2)}</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Fecha</div>
                        <div style="font-size: 0.95rem; color: var(--text-main);">${note.date}</div>
                    </div>
                </div>

                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: ${isPagado ? 'var(--secondary-color)' : '#ef4444'}; text-transform: uppercase;">Saldo Pendiente</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">S/ ${parseFloat(balance).toFixed(2)}</div>
                </div>

                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                    <a href="index.php?module=admin&action=payment_note_webview&id=${note.id}" class="btn btn-outline" style="flex: 1; background: var(--bg-color); border: none;">
                        <i class="ph ph-eye"></i> Ver Detalle
                    </a>
                    <button class="btn btn-icon btn-share" data-index="${index}" style="background: rgba(79, 70, 229, 0.1); color: var(--primary-color);">
                        <i class="ph ph-share-network"></i>
                    </button>
                    <button class="btn btn-icon btn-delete" data-index="${index}" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            `;
            notesGrid.appendChild(card);
        });

        if (summaryContainer) {
            summaryContainer.innerHTML = `
                <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px;">Total Notas</span>
                    <span style="font-size: 2rem; font-weight: 800; color: var(--color-title); margin-top: 0.5rem;">${totalNotas}</span>
                </div>
                <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px;">Pendientes</span>
                    <span style="font-size: 2rem; font-weight: 800; color: var(--warning-color); margin-top: 0.5rem;">${countPendientes}</span>
                </div>
                <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px;">Pagados</span>
                    <span style="font-size: 2rem; font-weight: 800; color: var(--secondary-color); margin-top: 0.5rem;">${countPagados}</span>
                </div>
                <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border-left: 4px solid var(--primary-color);">
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px;">Saldo Pendiente</span>
                    <span style="font-size: 2rem; font-weight: 800; color: var(--color-title); margin-top: 0.5rem;">S/ ${saldoPendienteTotal.toFixed(2)}</span>
                </div>
            `;
        }



        // Event Listeners for new buttons
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                noteToDeleteIndex = parseInt(btn.getAttribute('data-index'));
                document.getElementById('delete-note-id').innerText = notes[noteToDeleteIndex].id;
                modalDeleteNote.classList.add('active');
            });
        });

        document.querySelectorAll('.btn-share').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const idx = parseInt(btn.getAttribute('data-index'));
                const note = notes[idx];
                const baseUrl = window.location.origin + window.location.pathname;
                
                // Encode the entire note object in base64
                const encodedData = btoa(JSON.stringify(note));
                
                shareLinkInput.value = 'Generando enlace...';
                modalShareNote.classList.add('active');
                
                try {
                    // Update to copy public token instead of generating long URL
                    const publicUrl = baseUrl + '?module=admin&action=payment_note_webview&token=' + note.public_token + '&view=public';
                    shareLinkInput.value = publicUrl;
                } catch(e) {
                    shareLinkInput.value = 'Error de red';
                }
            });
        });
    }

    // Form submit removed as requested

    // Handle Delete
    btnConfirmDelete.addEventListener('click', async () => {
        if (noteToDeleteIndex !== null) {
            const noteId = notes[noteToDeleteIndex].id;
            try {
                const res = await fetch('modules/admin/ajax_delete_payment_note.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${noteId}`
                });
                const data = await res.json();
                if (data.success) {
                    notes.splice(noteToDeleteIndex, 1);
                    renderNotes();
                } else {
                    alert('Error al eliminar');
                }
            } catch(e) {
                console.error(e);
            }
            noteToDeleteIndex = null;
            modalDeleteNote.classList.remove('active');
        }
    });

    // Handle Copy Link
    btnCopyLink.addEventListener('click', () => {
        shareLinkInput.select();
        document.execCommand('copy');
        
        const originalText = btnCopyLink.innerHTML;
        btnCopyLink.innerHTML = '<i class="ph ph-check"></i> Copiado';
        btnCopyLink.style.backgroundColor = 'var(--secondary-color)';
        btnCopyLink.style.borderColor = 'var(--secondary-color)';
        
        setTimeout(() => {
            btnCopyLink.innerHTML = originalText;
            btnCopyLink.style.backgroundColor = '';
            btnCopyLink.style.borderColor = '';
        }, 2000);
    });

    // Initial Load
    loadNotes();
    window.openNewNoteModal = function() {
        // Populate clients
        const clientSelect = document.getElementById('new-note-client');
        clientSelect.innerHTML = '<option value="">Selecciona un cliente...</option>';
        availableClients.forEach(c => {
            clientSelect.innerHTML += `<option value="${c.name}" data-brands="${c.brands}">${c.name}</option>`;
        });
        
        document.getElementById('new-note-company').innerHTML = '<option value="">Selecciona una marca...</option>';
        document.getElementById('new-note-total').value = '';
        
        modalNewNote.classList.add('active');
    };

    document.getElementById('new-note-client').addEventListener('change', function() {
        const selectedOpt = this.options[this.selectedIndex];
        const brandsStr = selectedOpt ? selectedOpt.getAttribute('data-brands') : '';
        const companySelect = document.getElementById('new-note-company');
        
        companySelect.innerHTML = '<option value="">Selecciona una marca...</option>';
        if (brandsStr) {
            const brands = brandsStr.split('||');
            brands.forEach(b => {
                if(b) companySelect.innerHTML += `<option value="${b}">${b}</option>`;
            });
        }
    });

    btnCreateNote.addEventListener('click', async () => {
        const client = document.getElementById('new-note-client').value;
        const company = document.getElementById('new-note-company').value;
        const startDate = document.getElementById('new-note-date').value;
        const total = document.getElementById('new-note-total').value || 0;

        if (!client) {
            alert('Por favor selecciona un cliente.');
            return;
        }

        // Generate ID
        const dateObj = new Date();
        const newId = 'ID-' + dateObj.getFullYear() + '-' + Math.floor(Math.random() * 10000);

        const payload = {
            id: newId,
            client: client,
            company: company,
            startDate: startDate,
            total: total,
            status: 'pendiente',
            servicios: [],
            cronograma: []
        };

        const originalText = btnCreateNote.innerHTML;
        btnCreateNote.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Creando...';
        btnCreateNote.disabled = true;

        try {
            const res = await fetch('modules/admin/ajax_save_payment_note.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (data.success) {
                // Redirect to webview to continue editing
                window.location.href = `index.php?module=admin&action=payment_note_webview&id=${newId}`;
            } else {
                alert('Error al crear nota: ' + data.error);
                btnCreateNote.innerHTML = originalText;
                btnCreateNote.disabled = false;
            }
        } catch (e) {
            console.error(e);
            alert('Error de conexión');
            btnCreateNote.innerHTML = originalText;
            btnCreateNote.disabled = false;
        }
    });

});
</script>

<?php require_once 'includes/footer.php'; ?>
