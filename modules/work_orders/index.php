<?php
// modules/work_orders/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

// Fetch all work orders with brand details
$stmt = $db->query("
    SELECT wo.*, 
           b.name as joined_brand_name, 
           b.logo as brand_logo
    FROM work_orders wo
    LEFT JOIN client_brands b ON wo.brand_name = b.name
    ORDER BY wo.created_at DESC
");
$work_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch brands for the select dropdown
$stmtBrands = $db->query("SELECT id, name, logo FROM client_brands ORDER BY name ASC");
$brands = $stmtBrands->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>
<style>
.wo-custom-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03), 0 1px 3px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    border: 1px solid #f0f2f5;
    font-family: 'Inter', sans-serif;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.wo-custom-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.06);
}
.wo-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}
.wo-id {
    color: #4338ca;
    font-weight: 700;
    font-size: 1.1rem;
    letter-spacing: 0.5px;
}
.wo-badge {
    background: #fef3c7;
    color: #b45309;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    text-transform: uppercase;
}
.wo-status-line {
    font-size: 0.75rem;
    color: #94a3b8;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 1.25rem;
}
.wo-status-line .dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    display: inline-block;
}
.wo-profile {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
}
.wo-profile img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid #e2e8f0;
}
.wo-avatar-placeholder {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.wo-profile-info h4 {
    margin: 0;
    font-size: 1rem;
    color: #0f172a;
    font-weight: 700;
}
.wo-profile-info p {
    margin: 0;
    font-size: 0.85rem;
    color: #64748b;
}
.wo-divider {
    height: 1px;
    background: #f1f5f9;
    margin: 0.5rem 0 1rem 0;
}
.wo-data-row {
    display: flex;
    justify-content: space-between;
}
.wo-data-col {
    display: flex;
    flex-direction: column;
}
.wo-label {
    font-size: 0.7rem;
    color: #94a3b8;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.2rem;
}
.wo-value {
    font-size: 1rem;
    color: #475569;
    font-weight: 600;
}
.wo-value-large {
    font-size: 1.4rem;
    color: #0f172a;
    font-weight: 800;
}
.wo-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: auto; /* Pushes to bottom */
}
.btn-detail {
    flex: 1;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #1e293b;
    font-weight: 600;
    font-size: 0.9rem;
    border-radius: 8px;
    padding: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-detail:hover {
    background: #f1f5f9;
}
.btn-action-square {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-share {
    background: #eef2ff;
    color: #4f46e5;
}
.btn-share:hover {
    background: #e0e7ff;
}
.btn-delete {
    background: #fef2f2;
    color: #ef4444;
}
.btn-delete:hover {
    background: #fee2e2;
}

/* Dark Mode Adaptation */
[data-theme="dark"] .wo-custom-card {
    background: var(--bg-surface);
    border-color: var(--border-color);
}
[data-theme="dark"] .wo-profile-info h4,
[data-theme="dark"] .wo-value-large {
    color: var(--color-title);
}
[data-theme="dark"] .wo-value {
    color: var(--color-text);
}
[data-theme="dark"] .btn-detail {
    background: var(--bg-body);
    border-color: var(--border-color);
    color: var(--color-text);
}
[data-theme="dark"] .btn-detail:hover {
    background: var(--bg-surface);
}
[data-theme="dark"] .wo-divider {
    background: var(--border-color);
}
[data-theme="dark"] .wo-avatar-placeholder {
    background: var(--bg-body);
}
[data-theme="dark"] .wo-badge {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
}
</style>

<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="width: 56px; height: 56px; background: var(--bg-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
            <i class="ph ph-clipboard-text" style="font-size: 1.75rem; color: var(--primary-color);"></i>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Órdenes de Servicio</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Gestiona y comparte las órdenes de servicio.</p>
        </div>
    </div>
    <div style="display: flex; align-items: center;">
        <a href="index.php?module=work_orders&action=edit" class="btn btn-primary" style="white-space: nowrap; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px;">
            <i class="ph ph-plus"></i> Nueva Orden
        </a>
    </div>
</div>

<div class="work-orders-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
    <?php if (empty($work_orders)): ?>
        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--color-text);">
            <i class="ph ph-clipboard-text" style="font-size: 3rem; margin-bottom: 1rem; color: var(--text-muted);"></i>
            <p>No hay órdenes de servicio registradas.</p>
        </div>
    <?php else: ?>
        <?php foreach ($work_orders as $wo): 
            $woData = json_decode($wo['data'], true);
            $cliente = $woData['cliente'] ?? 'Sin cliente';
            $redes = !empty($woData['redes']) ? explode(',', $woData['redes']) : [];
            $presupuesto = $woData['presupuesto'] ?? 'No definido';
            $fechaInicio = $woData['fechaInicio'] ?? '';
            $fechaFinal = $woData['fechaFinal'] ?? '';
        ?>
            <div class="wo-custom-card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 16px; display: flex; flex-direction: column; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: transform 0.2s, box-shadow 0.2s; overflow: hidden; position: relative;">
                
                <!-- Top Section (Avatar, Name, Subtitle, Badge) -->
                <div style="padding: 0.85rem 0.85rem 0 0.85rem; display: flex; gap: 0.75rem; align-items: flex-start;">
                    <!-- Avatar -->
                    <div style="width: 46px; height: 46px; border-radius: 50%; border: 2px solid var(--bg-body); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; background: var(--bg-surface);">
                        <?php if ($wo['brand_logo']): ?>
                            <img src="<?php echo htmlspecialchars($wo['brand_logo']); ?>" alt="logo" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="ph ph-user" style="font-size: 1.4rem; color: var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Info -->
                    <div style="display: flex; flex-direction: column; gap: 0.15rem; flex: 1; min-width: 0;">
                        <h3 style="margin: 0; font-size: 1.05rem; color: var(--color-title); font-weight: 700; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars($cliente); ?>
                        </h3>
                        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars($wo['brand_name'] ?? 'Marca no asignada'); ?> • <?php echo htmlspecialchars($wo['correlativo']); ?>
                        </div>
                        <div style="margin-top: 0.15rem;">
                            <span style="background: #22c55e; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.6rem; font-weight: 700; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px;">
                                ACTIVO
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Middle Section (Description / Focus Area) -->
                <div style="padding: 0.65rem 0.85rem 0.85rem 0.85rem;">
                    <!-- Focus Area (Redes) -->
                    <?php if (!empty($redes)): ?>
                    <div style="margin-bottom: 0.25rem;">
                        <div style="font-size: 0.6rem; color: var(--text-muted); font-weight: 700; letter-spacing: 1px; margin-bottom: 0.4rem; text-transform: uppercase; opacity: 0.8;">
                            REDES SOCIALES
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.35rem;">
                            <?php 
                            $maxRedes = 2;
                            $count = 0;
                            foreach ($redes as $red): 
                                $red = trim($red);
                                if (empty($red)) continue;
                                if ($count < $maxRedes):
                            ?>
                                <span style="border: 1px solid var(--border-color); color: var(--color-text); padding: 3px 8px; border-radius: 16px; font-size: 0.7rem; font-weight: 600; background: var(--bg-body);">
                                    <?php echo htmlspecialchars($red); ?>
                                </span>
                            <?php 
                                $count++;
                                else: 
                            ?>
                                <?php if ($count === $maxRedes): ?>
                                    <span style="border: 1px solid var(--border-color); color: var(--color-text); padding: 3px 8px; border-radius: 16px; font-size: 0.7rem; font-weight: 600; background: var(--bg-body);">
                                        <?php echo (count($redes) - $maxRedes); ?>+
                                    </span>
                                <?php endif; ?>
                            <?php 
                                $count++;
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Stats Section -->
                <div style="display: flex; border-top: 1px solid var(--border-color); background: var(--bg-body);">
                    <div style="flex: 1; padding: 0.65rem; display: flex; align-items: center; justify-content: center; gap: 0.4rem; border-right: 1px solid var(--border-color);">
                        <i class="ph ph-calendar-blank" style="color: var(--text-muted); font-size: 1rem;"></i>
                        <span style="color: var(--color-text); font-weight: 700; font-size: 0.8rem;">
                            <?php echo $fechaInicio ? date('d/m', strtotime($fechaInicio)) : '--/--'; ?>
                        </span>
                    </div>
                    <div style="flex: 1; padding: 0.65rem; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                        <i class="ph ph-wallet" style="color: var(--text-muted); font-size: 1rem;"></i>
                        <span style="color: var(--color-text); font-weight: 700; font-size: 0.8rem;">
                            <?php echo htmlspecialchars($presupuesto); ?>
                        </span>
                    </div>
                </div>

                <!-- Bottom Buttons -->
                <div style="padding: 0.85rem; border-top: 1px solid var(--border-color); background: var(--bg-surface); display: flex; gap: 0.5rem; align-items: center;">
                    <button type="button" onclick="WorkOrderModule.shareOrder('<?php echo htmlspecialchars($wo['public_token']); ?>'); return false;" style="width: 38px; height: 38px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.color='var(--primary-color)'; this.style.borderColor='var(--primary-color)'" onmouseout="this.style.color='var(--text-muted)'; this.style.borderColor='var(--border-color)'" title="Compartir">
                        <i class="ph ph-share-network" style="font-size: 1.1rem;"></i>
                    </button>
                    <a href="index.php?module=work_orders&action=edit&id=<?php echo $wo['id']; ?>" style="width: 38px; height: 38px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; text-decoration: none;" onmouseover="this.style.color='var(--primary-color)'; this.style.borderColor='var(--primary-color)'" onmouseout="this.style.color='var(--text-muted)'; this.style.borderColor='var(--border-color)'" title="Editar">
                        <i class="ph ph-pencil-simple" style="font-size: 1.1rem;"></i>
                    </a>
                    <button type="button" onclick="WorkOrderModule.confirmDelete(<?php echo $wo['id']; ?>); return false;" style="width: 38px; height: 38px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.color='var(--danger-color, #ef4444)'; this.style.borderColor='var(--danger-color, #ef4444)'" onmouseout="this.style.color='var(--text-muted)'; this.style.borderColor='var(--border-color)'" title="Eliminar">
                        <i class="ph ph-trash" style="font-size: 1.1rem;"></i>
                    </button>
                    <a href="index.php?module=work_orders&action=edit&id=<?php echo $wo['id']; ?>" class="btn btn-primary" style="flex: 1; border-radius: 8px; padding: 0.6rem; font-size: 0.85rem; font-weight: 600; text-align: center; justify-content: center; text-decoration: none;">
                        Ver Detalle
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>



<!-- Modal para Compartir -->
<div class="modal-overlay" id="shareModal">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <div class="modal-header" style="justify-content: center; position: relative;">
            <h2 class="modal-title">Compartir Orden</h2>
            <button class="btn-icon close-modal" onclick="document.getElementById('shareModal').classList.remove('active')" style="position: absolute; right: 0;"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="padding: 2rem 1rem;">
            <i class="ph ph-link" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
            <p style="color: var(--color-text); margin-bottom: 1rem;">Copia este enlace para compartir la vista pública de la orden de servicio con tu cliente.</p>
            <input type="text" id="shareLinkInput" class="form-control" readonly style="text-align: center; margin-bottom: 1rem;">
            <button class="btn btn-primary" onclick="WorkOrderModule.copyShareLink()" style="width: 100%;">
                <i class="ph ph-copy"></i> Copiar Enlace
            </button>
        </div>
    </div>
</div>

<!-- Modal: Eliminar Orden -->
<div id="modal-delete-order" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 class="modal-title" style="color: var(--danger-color);"><i class="ph ph-warning-circle"></i> Eliminar Orden</h2>
            <button class="btn-close-circular btn-close-modal" onclick="document.getElementById('modal-delete-order').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        
        <input type="hidden" id="delete_wo_id" value="">
        
        <div class="modal-body">
            <p style="margin-top: 1rem; color: var(--color-text);">¿Estás seguro de que deseas eliminar esta orden de servicio?</p>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Esta acción <strong>no se puede deshacer</strong> y se perderán todos los datos asociados.</p>
        </div>

        <div class="modal-footer" style="border-top: none; display: flex; gap: 0.5rem; justify-content: flex-end; padding-top: 1rem;">
            <button type="button" class="btn btn-pill btn-light btn-close-modal" onclick="document.getElementById('modal-delete-order').classList.remove('active')">Cancelar</button>
            <button type="button" id="btnConfirmDeleteWo" class="btn btn-pill" style="background: var(--danger-color); color: white;" onclick="WorkOrderModule.executeDelete()">Sí, Eliminar</button>
        </div>
    </div>
</div>

<script src="assets/js/modules/work_orders.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
