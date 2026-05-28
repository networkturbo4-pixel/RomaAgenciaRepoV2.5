<?php
// modules/quotes/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

// Fetch quotes for the datatable
$quotes = [];
try {
    $stmt = $db->query("
        SELECT q.*, c.name as client_name 
        FROM quotes q 
        LEFT JOIN clients c ON q.client_id = c.id 
        ORDER BY q.created_at DESC
    ");
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once 'includes/header.php';
?>

<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="width: 56px; height: 56px; background: var(--bg-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
            <i class="ph ph-file-text" style="font-size: 1.75rem; color: var(--primary-color);"></i>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Cotizaciones</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Gestiona las cotizaciones y presupuestos.</p>
        </div>
    </div>
    <div style="display: flex; align-items: center;">
        <a href="index.php?module=quotes&action=form" class="btn btn-primary" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px;">
            <i class="ph ph-plus"></i> Nueva Cotización
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table" id="quotesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Fecha Emisión</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotes as $q): ?>
                    <tr>
                        <td data-label="ID">#<?php echo str_pad($q['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td data-label="CLIENTE"><?php echo htmlspecialchars($q['client_name']); ?></td>
                        <td data-label="FECHA EMISIÓN"><?php echo date('d/m/Y', strtotime($q['issue_date'])); ?></td>
                        <td data-label="VENCIMIENTO"><?php echo date('d/m/Y', strtotime($q['due_date'])); ?></td>
                        <td data-label="ESTADO">
                            <span class="badge status-<?php echo strtolower($q['status']); ?>">
                                <?php echo htmlspecialchars($q['status']); ?>
                            </span>
                        </td>
                        <td data-label="TOTAL"><?php echo htmlspecialchars($q['currency']) . ' ' . number_format($q['total'], 2); ?></td>
                        <td data-label="ACCIONES" style="text-align: right;">
                            <a href="index.php?module=quotes&action=form&id=<?php echo $q['id']; ?>" class="btn-icon" title="Editar">
                                <i class="ph ph-pencil-simple"></i>
                            </a>
                            <a href="modules/quotes/public.php?token=<?php echo $q['public_token']; ?>" target="_blank" class="btn-icon" title="Vista Pública">
                                <i class="ph ph-eye"></i>
                            </a>
                            <button class="btn-icon" style="color: var(--color-danger);" onclick="deleteQuote(<?php echo $q['id']; ?>)" title="Eliminar">
                                <i class="ph ph-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- DataTables initialization -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .status-borrador { background: #e2e8f0; color: #475569; padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .status-enviada { background: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .status-aceptada { background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .status-rechazada { background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .status-expirada { background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }

    /* Custom DataTables Modern Theme */
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        outline: none;
        background: #f8fafc;
        transition: all 0.2s;
        margin-left: 0.5rem;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #3b82f6;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 0.35rem 1.5rem 0.35rem 0.5rem;
        background: #f8fafc;
        outline: none;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 8px !important;
        border: 1px solid transparent !important;
        padding: 0.5rem 1rem !important;
        margin: 0 2px !important;
        background: transparent !important;
        color: #475569 !important;
        transition: all 0.2s;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f1f5f9 !important;
        border-color: #e2e8f0 !important;
        color: #0f172a !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current, 
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: #3b82f6 !important;
        color: #fff !important;
        border-color: #3b82f6 !important;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
    }
    table.dataTable thead th, table.dataTable thead td {
        border-bottom: 2px solid #e2e8f0 !important;
        color: #64748b;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1rem 0.5rem !important;
    }
    table.dataTable.no-footer {
        border-bottom: 1px solid #e2e8f0 !important;
    }
    table.dataTable tbody td {
        border-bottom: 1px solid #e2e8f0;
        padding: 1.25rem 0.5rem !important;
        color: #334155;
        vertical-align: middle;
    }
    table.dataTable tbody tr:hover {
        background-color: #f8fafc !important;
    }
    .dataTables_wrapper .dataTables_info {
        color: #64748b !important;
        font-size: 0.85rem;
        padding-top: 1.5rem !important;
    }
    .dataTables_wrapper .dataTables_paginate {
        padding-top: 1rem !important;
    }
    
    /* Enhance specific columns */
    table.dataTable tbody td:first-child {
        font-weight: 700;
        color: #0f172a;
    }
    table.dataTable tbody td:nth-child(2) {
        font-weight: 600;
    }
    .btn-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        margin-left: 0.25rem;
        transition: all 0.2s;
        text-decoration: none;
    }
    .btn-icon:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    /* ========== DARK MODE ========== */
    [data-theme="dark"] .dataTables_wrapper .dataTables_filter input {
        background: var(--bg-surface);
        border-color: var(--border-color);
        color: #e2e8f0;
    }
    [data-theme="dark"] .dataTables_wrapper .dataTables_filter input:focus {
        background: #0f172a;
        border-color: var(--primary-color);
    }
    [data-theme="dark"] .dataTables_wrapper .dataTables_length select {
        background: var(--bg-surface);
        border-color: var(--border-color);
        color: #e2e8f0;
    }
    [data-theme="dark"] table.dataTable thead th,
    [data-theme="dark"] table.dataTable thead td {
        border-bottom-color: var(--border-color) !important;
        color: #64748b;
    }
    [data-theme="dark"] table.dataTable.no-footer {
        border-bottom-color: var(--border-color) !important;
    }
    [data-theme="dark"] table.dataTable tbody td {
        border-bottom-color: var(--border-color);
        color: #cbd5e1;
    }
    [data-theme="dark"] table.dataTable tbody td:first-child {
        color: #f1f5f9;
    }
    [data-theme="dark"] table.dataTable tbody tr:hover {
        background-color: #253349 !important;
    }
    [data-theme="dark"] .dataTables_wrapper .dataTables_info {
        color: #64748b !important;
    }
    [data-theme="dark"] .dataTables_wrapper .dataTables_paginate .paginate_button {
        color: #94a3b8 !important;
    }
    [data-theme="dark"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #253349 !important;
        border-color: var(--border-color) !important;
        color: #f1f5f9 !important;
    }
    [data-theme="dark"] .btn-icon {
        background: var(--bg-surface);
        border-color: var(--border-color);
        color: #94a3b8;
    }
    [data-theme="dark"] .btn-icon:hover {
        background: #253349;
        color: #f1f5f9;
    }
</style>

<script>
$(document).ready(function() {
    $('#quotesTable').DataTable({
        language: {
            "decimal": "",
            "emptyTable": "No hay datos disponibles en la tabla",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
            "infoEmpty": "Mostrando 0 a 0 de 0 registros",
            "infoFiltered": "(filtrado de _MAX_ registros totales)",
            "infoPostFix": "",
            "thousands": ",",
            "lengthMenu": "Mostrar _MENU_ registros",
            "loadingRecords": "Cargando...",
            "processing": "Procesando...",
            "search": "Buscar:",
            "zeroRecords": "No se encontraron resultados",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        order: [[0, 'desc']]
    });
});

function deleteQuote(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "No podrás revertir esta acción.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('modules/quotes/ajax_delete_quote.php', { id: id }, function(response) {
                if (response.success) {
                    Swal.fire('Eliminado', 'La cotización ha sido eliminada.', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }, 'json');
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
