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
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <button class="btn btn-outline" id="btn-open-payment-methods" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px;">
            <i class="ph ph-credit-card"></i> Formas de Pago
        </button>
        <button class="btn btn-primary" onclick="window.location.href='index.php?module=admin&action=payment_note_webview&id=NEW'" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px;">
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
    <button class="btn btn-primary" onclick="window.location.href='index.php?module=admin&action=payment_note_webview&id=NEW'">
        <i class="ph ph-plus"></i> Crear Primera Nota
    </button>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal-overlay" id="modal-delete-note">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 class="modal-title" style="color: var(--danger-color);"><i class="ph ph-warning-circle"></i> Eliminar Nota</h2>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <p style="margin-top: 1rem; color: var(--color-text);">¿Estás seguro de que deseas eliminar la nota <strong id="delete-note-id"></strong>?</p>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Esta acción <strong>no se puede deshacer</strong> y se perderán todos los datos asociados.</p>
        </div>
        <div class="modal-footer" style="border-top: none; display: flex; gap: 0.5rem; justify-content: flex-end; padding-top: 1rem;">
            <button type="button" class="btn btn-pill btn-light btn-close-modal">Cancelar</button>
            <button type="button" id="btn-confirm-delete" class="btn btn-pill" style="background: var(--danger-color); color: white;">Sí, Eliminar</button>
        </div>
    </div>
</div>

<!-- Modal: Share Note -->
<div class="modal-overlay" id="modal-share-note">
    <div class="modal-content" style="max-width: 500px; padding: 0;">
        <div class="modal-header" style="padding: 1.5rem 1.5rem 0 1.5rem; border-bottom: none;">
            <h2 class="modal-title" style="color: var(--primary-color);"><i class="ph ph-share-network"></i> Compartir Nota</h2>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        
        <div style="padding: 1rem 1.5rem 0 1.5rem;">
            <div style="display: flex; gap: 1rem; border-bottom: 1px solid var(--border-color);">
                <button class="tab-btn active" data-target="tab-link" style="background: none; border: none; padding: 0.5rem 0.5rem 0.75rem 0.5rem; color: var(--primary-color); font-weight: 600; border-bottom: 2px solid var(--primary-color); cursor: pointer; transition: all 0.2s;"><i class="ph ph-link"></i> Enlace</button>
                <button class="tab-btn" data-target="tab-whatsapp" style="background: none; border: none; padding: 0.5rem 0.5rem 0.75rem 0.5rem; color: var(--text-muted); font-weight: 600; cursor: pointer; transition: all 0.2s;"><i class="ph ph-whatsapp-logo"></i> WhatsApp</button>
                <button class="tab-btn" data-target="tab-email" style="background: none; border: none; padding: 0.5rem 0.5rem 0.75rem 0.5rem; color: var(--text-muted); font-weight: 600; cursor: pointer; transition: all 0.2s;"><i class="ph ph-envelope-simple"></i> Correo</button>
            </div>
        </div>

        <div class="modal-body" style="padding: 1.5rem;">
            <!-- Tab: Link -->
            <div id="tab-link" class="share-tab-content">
                <p style="margin: 0 0 1rem 0; color: var(--text-muted); font-size: 0.9rem;">Copia el enlace para compartirlo manualmente con tu cliente.</p>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="share-link-input" class="form-control" readonly style="flex: 1; font-size: 0.85rem; border-radius: 8px;">
                    <button type="button" id="btn-copy-link" class="btn btn-primary" style="white-space: nowrap; border-radius: 8px;">
                        <i class="ph ph-copy"></i> Copiar
                    </button>
                </div>
            </div>

            <!-- Tab: WhatsApp -->
            <div id="tab-whatsapp" class="share-tab-content" style="display: none;">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">Número de WhatsApp (con código de país)</label>
                    <div style="display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: var(--bg-surface);">
                        <div style="padding: 0.5rem 0.75rem; background: var(--bg-color); color: var(--text-muted); font-weight: 600; border-right: 1px solid var(--border-color);">+</div>
                        <input type="text" id="share-wa-phone" class="form-control" placeholder="51999999999" style="border: none; border-radius: 0; outline: none;">
                    </div>
                    <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">Ej: 51902595959 (Sin el símbolo +)</small>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">Mensaje</label>
                    <textarea id="share-wa-msg" class="form-control" rows="5" style="resize: vertical; font-size: 0.85rem; border-radius: 8px;"></textarea>
                </div>
                <button type="button" id="btn-send-wa" class="btn btn-primary" style="width: 100%; justify-content: center; background: #25D366; border-color: #25D366; color: white;">
                    <i class="ph ph-paper-plane-right"></i> Enviar por WhatsApp
                </button>
            </div>

            <!-- Tab: Email -->
            <div id="tab-email" class="share-tab-content" style="display: none;">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">Correo del Cliente</label>
                    <input type="email" id="share-email-to" class="form-control" placeholder="cliente@correo.com" style="font-size: 0.85rem; border-radius: 8px;">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">Asunto</label>
                    <input type="text" id="share-email-subject" class="form-control" style="font-size: 0.85rem; border-radius: 8px;">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">Mensaje (Aparecerá junto con el diseño y el enlace)</label>
                    <textarea id="share-email-msg" class="form-control" rows="4" style="resize: vertical; font-size: 0.85rem; border-radius: 8px;"></textarea>
                </div>
                <button type="button" id="btn-send-email" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <i class="ph ph-envelope-simple-open"></i> Enviar Correo
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Modal: Payment Methods -->
<div class="modal-overlay" id="modal-payment-methods">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ph ph-credit-card"></i> Formas de Pago</h3>
            <button type="button" class="btn-close-circular btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="padding: 0;">
            <div style="padding: 1rem 1.5rem; background: var(--bg-color); border-bottom: 1px solid var(--border-color);">
                <p style="margin: 0; color: var(--text-muted); font-size: 0.85rem;">Configura los métodos de pago que se muestran en las notas de pago públicas.</p>
            </div>

            <!-- List of existing methods -->
            <div id="pm-list" style="max-height: 350px; overflow-y: auto;"></div>

            <!-- Add new method form -->
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); background: var(--bg-color);">
                <div style="font-weight: 700; font-size: 0.85rem; margin-bottom: 0.75rem; color: var(--primary-color);"><i class="ph ph-plus-circle"></i> Agregar Método</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div class="form-group" style="margin: 0;">
                        <input type="text" id="pm-new-label" class="form-control" placeholder="Ej: BCP SOLES" style="font-size: 0.85rem;">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <input type="text" id="pm-new-code" class="form-control" placeholder="Ej: 19174092813024" style="font-size: 0.85rem;">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr auto; gap: 0.75rem; margin-top: 0.75rem;">
                    <div class="form-group" style="margin: 0;">
                        <input type="text" id="pm-new-image" class="form-control" placeholder="URL de imagen/logo (opcional)" style="font-size: 0.85rem;">
                    </div>
                    <button type="button" id="btn-pm-add" class="btn btn-primary" style="white-space: nowrap; font-size: 0.85rem;">
                        <i class="ph ph-plus"></i> Agregar
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
    let availableClients = [];
    let noteIdCounter = 11;
    let noteToDeleteIndex = null;
    let activeNoteForShare = null;
    let activeNoteUrlForShare = '';

    // Elements
    const notesGrid = document.getElementById('notes-grid');
    const emptyState = document.getElementById('empty-state');
    const modalDeleteNote = document.getElementById('modal-delete-note');
    const btnConfirmDelete = document.getElementById('btn-confirm-delete');
    const modalShareNote = document.getElementById('modal-share-note');
    const shareLinkInput = document.getElementById('share-link-input');
    const btnCopyLink = document.getElementById('btn-copy-link');

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
        let countVencidas = 0;
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

            // Subtract abonos (advances/deposits) from balance
            let totalAbonos = 0;
            if (note.abonos && note.abonos.length > 0) {
                totalAbonos = note.abonos.reduce((sum, a) => sum + parseFloat(a.monto || 0), 0);
            }

            const balance = isPagado ? 0 : Math.max(0, computedTotal - totalAbonos);

            if (noteStatusText === 'Pagado') {
                countPagados++;
            } else if (noteStatusText === 'En proceso' || noteStatusText === 'Pendiente' || noteStatusText === 'Retrasado') {
                countPendientes++;
            }

            saldoPendienteTotal += parseFloat(balance || 0);

            // Check if note is overdue
            if (!isPagado && note.startDate) {
                const dueDays = note.due_days || 30;
                const start = new Date(note.startDate);
                const dueDate = new Date(start);
                dueDate.setDate(dueDate.getDate() + dueDays);
                const today = new Date();
                today.setHours(0,0,0,0);
                if (today > dueDate) {
                    countVencidas++;
                }
            }

            const displayDate = note.date ? note.date.split(' ')[0] : '-';

            const card = document.createElement('div');
            card.className = 'card';
            card.style.padding = '1.25rem';
            card.style.display = 'flex';
            card.style.flexDirection = 'column';
            card.style.gap = '1rem';
            card.style.height = '100%';

            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-weight: 700; color: var(--primary-color); font-size: 1.1rem;">${note.id}</span>
                    <span style="background: ${statusBg}; color: ${statusColor}; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 700;">${noteStatusText}</span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 0.5rem;" title="${note.last_viewed_at ? 'Última vez: ' + new Date(note.last_viewed_at).toLocaleString('es-PE') : 'Aún no visto'}">
                    <div style="width: 8px; height: 8px; border-radius: 50%; background-color: ${note.view_count > 0 ? 'var(--secondary-color)' : 'var(--text-muted)'};"></div>
                    <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">
                        <i class="ph ph-eye" style="font-size: 0.75rem;"></i> ${note.view_count || 0} vista${note.view_count !== 1 ? 's' : ''}
                    </span>
                    ${note.access_pin ? '<span style="font-size: 0.7rem; background: rgba(100,116,139,0.15); color: #64748b; padding: 2px 6px; border-radius: 4px;"><i class="ph ph-lock-simple"></i> PIN</span>' : ''}
                </div>

                <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                    <div style="width: 40px; height: 40px; flex-shrink: 0; border-radius: 50%; background: var(--bg-color); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                        <i class="ph ph-user" style="font-size: 1.25rem;"></i>
                    </div>
                    <div style="min-width: 0; flex: 1;">
                        <div style="font-weight: 600; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${note.client}">${note.client}</div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${note.company}">${note.company}</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between" style="margin-top: 0.5rem; gap: 1rem;">
                    <div style="min-width: 0; flex: 1;">
                        <div style="font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Monto Total</div>
                        <div style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="S/ ${parseFloat(note.total).toFixed(2)}">S/ ${parseFloat(note.total).toFixed(2)}</div>
                    </div>
                    <div style="text-align: right; min-width: 0; flex: 1;">
                        <div style="font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Fecha</div>
                        <div style="font-size: 0.95rem; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${displayDate}">${displayDate}</div>
                    </div>
                </div>

                <div style="margin-top: auto;">
                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: ${isPagado ? 'var(--secondary-color)' : '#ef4444'}; text-transform: uppercase;">Saldo Pendiente</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="S/ ${parseFloat(balance).toFixed(2)}">S/ ${parseFloat(balance).toFixed(2)}</div>
                    </div>

                    <div style="display: flex; gap: 0.5rem;">
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
                </div>
            `;
            notesGrid.appendChild(card);
        });

        if (summaryContainer) {
            let overdueAlert = '';
            if (countVencidas > 0) {
                overdueAlert = `
                    <div style="grid-column: 1 / -1; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 12px 16px; display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                        <i class="ph ph-warning-circle" style="font-size: 1.3rem; color: #dc2626;"></i>
                        <span style="font-size: 0.85rem; font-weight: 600; color: #991b1b;">⚠️ Tienes ${countVencidas} nota${countVencidas !== 1 ? 's' : ''} vencida${countVencidas !== 1 ? 's' : ''} sin pago</span>
                    </div>
                `;
            }
            summaryContainer.innerHTML = overdueAlert + `
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
                activeNoteForShare = note;
                const baseUrl = window.location.origin + window.location.pathname;
                
                shareLinkInput.value = 'Generando enlace...';
                
                // Show modal first
                modalShareNote.classList.add('active');
                
                // Reset tabs to Link
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.style.color = 'var(--text-muted)';
                    b.style.borderBottom = 'none';
                    b.classList.remove('active');
                });
                document.querySelector('.tab-btn[data-target="tab-link"]').style.color = 'var(--primary-color)';
                document.querySelector('.tab-btn[data-target="tab-link"]').style.borderBottom = '2px solid var(--primary-color)';
                document.querySelector('.tab-btn[data-target="tab-link"]').classList.add('active');
                
                document.querySelectorAll('.share-tab-content').forEach(c => c.style.display = 'none');
                document.getElementById('tab-link').style.display = 'block';

                try {
                    const basePath = window.location.origin + window.location.pathname.replace(/\/index\.php.*$/, '').replace(/\/+$/, '');
                    const publicUrl = note.public_token ? `${basePath}/np/${note.public_token}` : (baseUrl + '?module=admin&action=payment_note_webview&token=' + (note.public_token || '') + '&view=public');
                    shareLinkInput.value = publicUrl;
                    activeNoteUrlForShare = publicUrl;

                    const clientName = note.client || 'Cliente';
                    
                    let clientPhone = '';
                    let clientEmail = '';
                    if (clientName !== 'Cliente' && availableClients) {
                        const clientObj = availableClients.find(c => c.name === clientName);
                        if (clientObj) {
                            clientPhone = clientObj.whatsapp || '';
                            clientEmail = clientObj.email || '';
                            if (clientPhone.startsWith('+')) clientPhone = clientPhone.substring(1);
                        }
                    }
                    
                    // Pre-fill WhatsApp
                    document.getElementById('share-wa-phone').value = clientPhone;
                    document.getElementById('share-wa-msg').value = `Hola ${clientName},\n\nTe compartimos el enlace para visualizar tu nota de pago pendiente y los métodos disponibles:\n\n${publicUrl}\n\nQuedamos atentos a cualquier consulta.`;

                    // Pre-fill Email
                    document.getElementById('share-email-to').value = clientEmail;
                    document.getElementById('share-email-subject').value = `Nota de Pago Pendiente - ${clientName}`;
                    document.getElementById('share-email-msg').value = `Hola ${clientName},\n\nAdjuntamos el enlace para visualizar tu nota de pago. Podrás revisar el detalle y los métodos de pago disponibles.\n\nSaludos cordiales.`;

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
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error al eliminar la nota.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)' });
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

    // === SHARE MODAL TABS ===
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.style.color = 'var(--text-muted)';
                b.style.borderBottom = 'none';
                b.classList.remove('active');
            });
            btn.style.color = 'var(--primary-color)';
            btn.style.borderBottom = '2px solid var(--primary-color)';
            btn.classList.add('active');

            document.querySelectorAll('.share-tab-content').forEach(c => c.style.display = 'none');
            document.getElementById(btn.getAttribute('data-target')).style.display = 'block';
        });
    });

    // === SEND WHATSAPP ===
    document.getElementById('btn-send-wa').addEventListener('click', async () => {
        const phone = document.getElementById('share-wa-phone').value.trim();
        const msg = document.getElementById('share-wa-msg').value.trim();
        if (!phone || !msg) {
            Swal.fire({ icon: 'warning', title: 'Campos requeridos', text: 'Ingresa el número y el mensaje.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
            return;
        }

        const btn = document.getElementById('btn-send-wa');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Enviando...';
        btn.disabled = true;

        try {
            const res = await fetch('modules/admin/ajax_send_note_whatsapp.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ phone, message: msg, note_id: activeNoteForShare.id })
            });
            
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch(err) {
                console.error("Respuesta WA no es JSON válido:", text);
                throw new Error("Respuesta del servidor no válida (WA)");
            }

            if(data.success) {
                Swal.fire({ icon: 'success', title: 'Enviado', text: 'Mensaje de WhatsApp enviado correctamente.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo enviar el mensaje.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
            }
        } catch(e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red: ' + e.message, confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    });

    // === SEND EMAIL ===
    document.getElementById('btn-send-email').addEventListener('click', async () => {
        const to = document.getElementById('share-email-to').value.trim();
        const subject = document.getElementById('share-email-subject').value.trim();
        const msg = document.getElementById('share-email-msg').value.trim();
        if (!to || !subject || !msg) {
            Swal.fire({ icon: 'warning', title: 'Campos requeridos', text: 'Completa el correo, asunto y mensaje.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
            return;
        }

        const btn = document.getElementById('btn-send-email');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Enviando...';
        btn.disabled = true;

        try {
            const res = await fetch('modules/admin/ajax_send_note_email.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ to, subject, message: msg, note_id: activeNoteForShare.id, url: activeNoteUrlForShare })
            });
            
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch(err) {
                console.error("Respuesta no es JSON válido:", text);
                throw new Error("Respuesta del servidor no válida");
            }
            
            if(data.success) {
                Swal.fire({ icon: 'success', title: 'Enviado', text: 'Correo enviado correctamente.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo enviar el correo.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
            }
        } catch(e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error: ' + e.message, confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)'});
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    });

    // === PAYMENT METHODS MODAL ===
    const pmModal = document.getElementById('modal-payment-methods');
    const pmList = document.getElementById('pm-list');

    document.getElementById('btn-open-payment-methods').addEventListener('click', () => {
        pmModal.classList.add('active');
        loadPaymentMethods();
    });

    async function loadPaymentMethods() {
        pmList.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--text-muted);"><i class="ph ph-spinner ph-spin"></i> Cargando...</div>';
        try {
            const res = await fetch('modules/admin/ajax_payment_methods.php');
            const data = await res.json();
            if (data.success) {
                renderPMList(data.methods);
            } else {
                pmList.innerHTML = `<div style="padding: 2rem; text-align: center; color: var(--danger-color);"><i class="ph ph-warning-circle" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>${data.error || 'Error desconocido del servidor'}</div>`;
            }
        } catch(e) {
            pmList.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--danger-color);"><i class="ph ph-warning-circle" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>Error de red o JSON inválido</div>';
            console.error('Fetch error in loadPaymentMethods:', e);
        }
    }

    function renderPMList(methods) {
        if (methods.length === 0) {
            pmList.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--text-muted);"><i class="ph ph-credit-card" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>No hay métodos de pago configurados</div>';
            return;
        }
        pmList.innerHTML = methods.map(m => `
            <div class="pm-item" data-id="${m.id}" style="display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1.5rem; border-bottom: 1px solid var(--border-color); transition: background 0.15s;">
                <div style="width: 42px; height: 42px; border-radius: 10px; background: var(--bg-color); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                    ${m.image_url 
                        ? `<img src="${m.image_url}" alt="${m.label}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">` 
                        : `<i class="ph ph-bank" style="font-size: 1.2rem; color: var(--primary-color);"></i>`}
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main);">${m.label}</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); font-family: monospace;">${m.code}</div>
                    ${m.image_url ? `<div style="font-size: 0.7rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px;">🖼 ${m.image_url}</div>` : ''}
                </div>
                <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                    <button class="btn-pm-edit" data-id="${m.id}" data-label="${m.label}" data-code="${m.code}" data-image="${m.image_url || ''}" style="background: none; border: 1px solid var(--border-color); border-radius: 6px; padding: 0.35rem 0.5rem; cursor: pointer; color: var(--primary-color); font-size: 0.8rem;" title="Editar">
                        <i class="ph ph-pencil-simple"></i>
                    </button>
                    <button class="btn-pm-delete" data-id="${m.id}" data-label="${m.label}" style="background: none; border: 1px solid var(--border-color); border-radius: 6px; padding: 0.35rem 0.5rem; cursor: pointer; color: var(--danger-color); font-size: 0.8rem;" title="Eliminar">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');

        // Edit handlers
        pmList.querySelectorAll('.btn-pm-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const item = btn.closest('.pm-item');
                item.innerHTML = `
                    <div style="flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <input type="text" class="form-control pm-edit-label" value="${btn.dataset.label}" style="font-size: 0.85rem;" placeholder="Nombre">
                        <input type="text" class="form-control pm-edit-code" value="${btn.dataset.code}" style="font-size: 0.85rem;" placeholder="Código/Cuenta">
                        <input type="text" class="form-control pm-edit-image" value="${btn.dataset.image}" style="font-size: 0.85rem; grid-column: 1/-1;" placeholder="URL de imagen (opcional)">
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-shrink: 0; align-self: center;">
                        <button class="btn btn-primary pm-save-edit" data-id="${id}" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;"><i class="ph ph-check"></i></button>
                        <button class="btn btn-outline pm-cancel-edit" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;"><i class="ph ph-x"></i></button>
                    </div>
                `;
                item.querySelector('.pm-save-edit').addEventListener('click', async () => {
                    const label = item.querySelector('.pm-edit-label').value.trim();
                    const code = item.querySelector('.pm-edit-code').value.trim();
                    const image_url = item.querySelector('.pm-edit-image').value.trim();
                    if (!label || !code) return;
                    await fetch('modules/admin/ajax_payment_methods.php', {
                        method: 'POST',
                        headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({action:'update', id, label, code, image_url})
                    });
                    loadPaymentMethods();
                });
                item.querySelector('.pm-cancel-edit').addEventListener('click', () => loadPaymentMethods());
            });
        });

        // Delete handlers
        pmList.querySelectorAll('.btn-pm-delete').forEach(btn => {
            btn.addEventListener('click', async () => {
                const result = await Swal.fire({
                    icon: 'warning',
                    title: '¿Eliminar método?',
                    text: `¿Eliminar "${btn.dataset.label}"?`,
                    showCancelButton: true,
                    confirmButtonText: 'Sí, Eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    background: 'var(--bg-surface)',
                    color: 'var(--color-text)'
                });
                if (!result.isConfirmed) return;
                await fetch('modules/admin/ajax_payment_methods.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({action:'delete', id: btn.dataset.id})
                });
                loadPaymentMethods();
            });
        });
    }

    // Add new method
    document.getElementById('btn-pm-add').addEventListener('click', async () => {
        const label = document.getElementById('pm-new-label').value.trim();
        const code = document.getElementById('pm-new-code').value.trim();
        const image_url = document.getElementById('pm-new-image').value.trim();
        
        if (!label || !code) {
            Swal.fire({ icon: 'warning', title: 'Campos requeridos', text: 'Nombre y código/cuenta son obligatorios.', confirmButtonColor: '#0f766e', background: 'var(--bg-surface)', color: 'var(--color-text)' });
            return;
        }

        await fetch('modules/admin/ajax_payment_methods.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'add', label, code, image_url})
        });
        
        document.getElementById('pm-new-label').value = '';
        document.getElementById('pm-new-code').value = '';
        document.getElementById('pm-new-image').value = '';
        loadPaymentMethods();
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
