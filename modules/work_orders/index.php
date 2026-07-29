<?php
// modules/work_orders/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

// Fetch all work orders with brand details
$view = isset($_GET['view']) && $_GET['view'] === 'archived' ? 1 : 0;

$stmt = $db->prepare("
    SELECT wo.*, 
           b.name as joined_brand_name, 
           b.logo as brand_logo
    FROM work_orders wo
    LEFT JOIN client_brands b ON wo.brand_name = b.name
    WHERE wo.is_archived = :is_archived
    ORDER BY wo.created_at DESC
");
$stmt->execute([':is_archived' => $view]);
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
    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; justify-content: flex-end;">
        <div style="background: var(--bg-body); padding: 0.3rem; border-radius: 8px; display: flex; gap: 0.2rem; border: 1px solid var(--border-color);">
            <a href="index.php?module=work_orders" style="padding: 0.4rem 0.8rem; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; <?php echo $view === 0 ? 'background: var(--bg-surface); color: var(--color-title); box-shadow: 0 1px 2px rgba(0,0,0,0.05);' : 'color: var(--text-muted);'; ?>">Activas</a>
            <a href="index.php?module=work_orders&view=archived" style="padding: 0.4rem 0.8rem; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; <?php echo $view === 1 ? 'background: var(--bg-surface); color: var(--color-title); box-shadow: 0 1px 2px rgba(0,0,0,0.05);' : 'color: var(--text-muted);'; ?>">Archivadas</a>
        </div>
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
            $redesRaw = $woData['redes'] ?? '';
            $redes = [];
            if (!empty($redesRaw)) {
                $decoded = json_decode($redesRaw, true);
                if (is_array($decoded)) {
                    $redes = $decoded;
                } else {
                    $arr = array_filter(array_map('trim', explode(',', $redesRaw)));
                    $redes = array_map(function($r) { return ['id' => $r, 'url' => '']; }, $arr);
                }
            }
            $presupuesto = $woData['presupuesto'] ?? 'No definido';
            $fechaInicio = $woData['fechaInicio'] ?? '';
            $fechaFinal = $woData['fechaFinal'] ?? '';
            $prioridad = $woData['prioridad'] ?? 'Media'; // Alta, Media, Baja

            $encargados = [];
            if (!empty($woData['procesos'])) {
                foreach ($woData['procesos'] as $proc) {
                    if (!empty($proc['rows'])) {
                        foreach ($proc['rows'] as $r) {
                            $enc = trim($r['encargado'] ?? '');
                            if ($enc && !in_array($enc, $encargados)) {
                                $encargados[] = $enc;
                            }
                        }
                    }
                }
            }
        ?>
            <div class="wo-custom-card" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 20px; display: flex; flex-direction: column; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden; position: relative;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)'; this.style.borderColor='var(--primary-color)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03)'; this.style.borderColor='var(--border-color)';">
                
                <!-- Top Section -->
                <div style="padding: 1.2rem 1.2rem 0 1.2rem; display: flex; gap: 1rem; align-items: flex-start;">
                    <!-- Avatar -->
                    <div style="width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <?php 
                        $initial = strtoupper(mb_substr($cliente, 0, 1));
                        $colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#0ea5e9'];
                        $color = $colors[strlen($cliente) % count($colors)];
                        ?>
                        <?php if ($wo['brand_logo']): ?>
                            <img src="<?php echo htmlspecialchars($wo['brand_logo']); ?>" alt="logo" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; background: <?php echo $color; ?>; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800; font-family: 'Inter', sans-serif;">
                                <?php echo htmlspecialchars($initial); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Info -->
                    <div style="display: flex; flex-direction: column; gap: 0.2rem; flex: 1; min-width: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem;">
                            <h3 style="margin: 0; font-size: 1.1rem; color: var(--color-title); font-weight: 800; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: 'Inter', sans-serif;">
                                <?php echo htmlspecialchars($cliente); ?>
                            </h3>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars($wo['brand_name'] ?? 'Sin Marca'); ?> <span style="opacity: 0.5; margin: 0 4px;">•</span> <?php echo htmlspecialchars($wo['correlativo']); ?>
                        </div>
                    </div>
                </div>

                <!-- Middle Section (Networks) -->
                <div style="padding: 1rem 1.2rem; flex: 1;">
                    <?php if (!empty($redes)): ?>
                    <div>
                        <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 700; letter-spacing: 1px; margin-bottom: 0.5rem; text-transform: uppercase;">
                            Canales
                        </div>
                        <style>
                        .net-icon-white { color: #ffffff !important; }
                        .net-icon-white i, .net-icon-white svg, .net-icon-white path { color: #ffffff !important; fill: #ffffff !important; stroke: #ffffff !important; }
                        </style>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.35rem;">
                            <?php 
                            $maxRedes = 4;
                            $count = 0;
                            $REDES_FA_MAP = [
                                'Facebook' => ['icon' => 'ph-facebook-logo', 'color' => '#1877F2'],
                                'Instagram' => ['icon' => 'ph-instagram-logo', 'color' => '#E4405F'],
                                'TikTok' => ['icon' => 'ph-tiktok-logo', 'color' => '#000000'],
                                'VK' => ['icon' => 'ph-users-three', 'color' => '#4680C2'],
                                'Google' => ['icon' => 'ph-google-logo', 'color' => '#DB4437'],
                                'YouTube' => ['icon' => 'ph-youtube-logo', 'color' => '#FF0000'],
                                'LinkedIn' => ['icon' => 'ph-linkedin-logo', 'color' => '#0A66C2'],
                                'Web' => ['icon' => 'ph-globe', 'color' => '#577a9e']
                            ];

                            foreach ($redes as $redObj): 
                                $netId = $redObj['id'] ?? '';
                                if (empty($netId)) continue;
                                
                                if ($count < $maxRedes):
                                    $conf = $REDES_FA_MAP[$netId] ?? ['icon' => 'ph-share-network', 'color' => '#577a9e'];
                                    $url = $redObj['url'] ?? '';
                                    $tagHtml = $url ? '<a href="'.htmlspecialchars($url).'" target="_blank" class="net-icon-white" ' : '<span class="net-icon-white" ';
                                    $tagClose = $url ? '</a>' : '</span>';
                            ?>
                                <?php echo $tagHtml; ?> style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: <?php echo $conf['color']; ?>; color: #ffffff !important; border-radius: 50%; font-size: 1.1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.15); transition: transform 0.2s;" title="<?php echo htmlspecialchars($netId); ?>" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                    <i class="ph <?php echo $conf['icon']; ?>" style="color: #ffffff !important;"></i>
                                <?php echo $tagClose; ?>
                            <?php 
                                $count++;
                                else: 
                            ?>
                                <?php if ($count === $maxRedes): ?>
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: var(--bg-body); color: var(--text-muted); font-size: 0.75rem; font-weight: 700; border: 1px solid var(--border-color);" title="Más canales">
                                        +<?php echo (count($redes) - $maxRedes); ?>
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

                <!-- 4 Icon Buttons -->
                <div style="padding: 0 1.2rem 0.8rem 1.2rem; display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.6rem;">
                    <button type="button" onclick="WorkOrderModule.shareOrder('<?php echo htmlspecialchars($wo['public_token']); ?>'); return false;" style="height: 46px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.color='#0f766e'; this.style.borderColor='#0f766e'; this.style.background='rgba(15, 118, 110, 0.1)';" onmouseout="this.style.color='var(--text-muted)'; this.style.borderColor='var(--border-color)'; this.style.background='var(--bg-surface)';" title="Compartir">
                        <i class="ph ph-share-network" style="font-size: 1.2rem;"></i>
                    </button>
                    <a href="index.php?module=work_orders&action=edit&id=<?php echo $wo['id']; ?>" style="height: 46px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; text-decoration: none;" onmouseover="this.style.color='#0f766e'; this.style.borderColor='#0f766e'; this.style.background='rgba(15, 118, 110, 0.1)';" onmouseout="this.style.color='var(--text-muted)'; this.style.borderColor='var(--border-color)'; this.style.background='var(--bg-surface)';" title="Editar">
                        <i class="ph ph-pencil-simple" style="font-size: 1.2rem;"></i>
                    </a>
                    <button type="button" onclick="WorkOrderModule.archiveOrder(<?php echo $wo['id']; ?>); return false;" style="height: 46px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.color='#0f766e'; this.style.borderColor='#0f766e'; this.style.background='rgba(15, 118, 110, 0.1)';" onmouseout="this.style.color='var(--text-muted)'; this.style.borderColor='var(--border-color)'; this.style.background='var(--bg-surface)';" title="Archivar">
                        <i class="ph ph-archive-box" style="font-size: 1.2rem;"></i>
                    </button>
                    <button type="button" onclick="WorkOrderModule.confirmDelete(<?php echo $wo['id']; ?>); return false;" style="height: 46px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--danger-color, #dc2626); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.color='var(--danger-color, #dc2626)'; this.style.borderColor='var(--danger-color, #dc2626)'; this.style.background='rgba(220, 38, 38, 0.1)';" onmouseout="this.style.color='var(--danger-color, #dc2626)'; this.style.borderColor='var(--border-color)'; this.style.background='var(--bg-surface)';" title="Eliminar">
                        <i class="ph ph-trash" style="font-size: 1.2rem;"></i>
                    </button>
                </div>

                <!-- Main Action Button -->
                <div style="padding: 0 1.2rem 1.2rem 1.2rem;">
                    <a href="index.php?module=work_orders&action=edit&id=<?php echo $wo['id']; ?>" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 46px; border-radius: 12px; background: #0f766e; color: white; font-size: 0.95rem; font-weight: 600; text-decoration: none; transition: all 0.2s; box-shadow: 0 2px 4px rgba(15, 118, 110, 0.2);" onmouseover="this.style.background='#0d9488'; this.style.boxShadow='0 4px 8px rgba(15, 118, 110, 0.3)';" onmouseout="this.style.background='#0f766e'; this.style.boxShadow='0 2px 4px rgba(15, 118, 110, 0.2)';">
                        Ver Detalle
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>



<!-- Modal para Compartir -->
<div class="modal-overlay" id="shareModal">
    <div class="modal-content" style="max-width: 420px;">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 class="modal-title" style="color: var(--primary-color);"><i class="ph ph-share-network"></i> Compartir Orden</h2>
            <button class="btn-close-circular btn-close-modal" onclick="document.getElementById('shareModal').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <p style="margin-top: 1rem; color: var(--color-text);">Copia este enlace para compartir la vista pública de la orden de servicio con tu cliente.</p>
            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <input type="text" id="shareLinkInput" class="form-control" readonly style="flex: 1; font-size: 0.8rem; border-radius: 10px;">
                <button class="btn btn-pill" style="background: var(--primary-color); color: white; white-space: nowrap;" onclick="WorkOrderModule.copyShareLink()">
                    <i class="ph ph-copy"></i> Copiar
                </button>
            </div>
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

<!-- Modal: Archivar Orden -->
<div id="modal-archive-order" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <h2 class="modal-title" style="color: var(--primary-color);"><i class="ph ph-archive-box"></i> Archivar Orden</h2>
            <button class="btn-close-circular btn-close-modal" onclick="document.getElementById('modal-archive-order').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        
        <input type="hidden" id="archive_wo_id" value="">
        
        <div class="modal-body">
            <p style="margin-top: 1rem; color: var(--color-text);">¿Estás seguro de que deseas archivar esta orden de servicio?</p>
            <p style="color: var(--text-muted); font-size: 0.875rem;">La orden se moverá a la sección de <strong>archivadas</strong> y podrás restaurarla en cualquier momento.</p>
        </div>

        <div class="modal-footer" style="border-top: none; display: flex; gap: 0.5rem; justify-content: flex-end; padding-top: 1rem;">
            <button type="button" class="btn btn-pill btn-light btn-close-modal" onclick="document.getElementById('modal-archive-order').classList.remove('active')">Cancelar</button>
            <button type="button" id="btnConfirmArchiveWo" class="btn btn-pill" style="background: var(--primary-color); color: white;" onclick="WorkOrderModule.executeArchive()">Sí, Archivar</button>
        </div>
    </div>
</div>

<script src="assets/js/modules/work_orders.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
