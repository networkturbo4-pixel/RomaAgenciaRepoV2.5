<?php
// modules/clients/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

// Fetch all clients
$stmt = $db->query("
    SELECT c.*, 
           GROUP_CONCAT(b.name SEPARATOR '||') as brands, 
           GROUP_CONCAT(b.logo SEPARATOR '||') as logos
    FROM clients c
    LEFT JOIN client_brands b ON c.id = b.client_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="width: 56px; height: 56px; background: var(--bg-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
            <i class="ph ph-users" style="font-size: 1.75rem; color: var(--primary-color);"></i>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Clientes</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Gestiona los clientes y sus marcas asociadas.</p>
        </div>
    </div>
    <div style="display: flex; align-items: center;">
        <button class="btn btn-primary" onclick="ClientModule.openModal()" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px;">
            <i class="ph ph-plus"></i> Nuevo Cliente
        </button>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>CLIENTE</th>
                    <th>DNI</th>
                    <th>CONTACTO</th>
                    <th>MARCAS</th>
                    <th style="text-align: right;">ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 2rem; color: var(--color-text);">No hay clientes registrados.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($clients as $client): ?>
                    <tr>
                        <td data-label="CLIENTE">
                            <div style="font-weight: 500; color: var(--color-title);"><?php echo htmlspecialchars($client['name']); ?></div>
                        </td>
                        <td data-label="DNI">
                            <div style="font-weight: 500; color: var(--text-muted);"><?php echo htmlspecialchars($client['dni'] ?? '-'); ?></div>
                        </td>
                        <td data-label="CONTACTO">
                            <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 0.25rem;">
                                <?php if ($client['email']): ?>
                                    <div><i class="ph ph-envelope-simple" style="vertical-align: middle;"></i> <?php echo htmlspecialchars($client['email']); ?></div>
                                <?php endif; ?>
                                <?php if ($client['whatsapp']): ?>
                                    <div><i class="ph ph-whatsapp-logo" style="vertical-align: middle;"></i> <?php echo htmlspecialchars($client['whatsapp']); ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="MARCAS">
                            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <?php
                                if ($client['brands']) {
                                    $brandNames = explode('||', $client['brands']);
                                    $brandLogos = explode('||', $client['logos']);
                                    foreach ($brandNames as $index => $brandName) {
                                        $logo = $brandLogos[$index] ?? '';
                                        echo '<div style="display: flex; align-items: center; gap: 4px; padding: 2px 8px; background: var(--bg-body); border-radius: 4px; font-size: 0.75rem; border: 1px solid var(--border-color);">';
                                        if ($logo) {
                                            echo '<img src="'.htmlspecialchars($logo).'" alt="logo" style="height: 16px; width: 16px; object-fit: cover; border-radius: 2px;">';
                                        } else {
                                            echo '<i class="ph ph-tag" style="color: var(--color-text);"></i>';
                                        }
                                        echo htmlspecialchars($brandName);
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<span style="color: var(--color-text); font-size: 0.875rem;">Sin marcas</span>';
                                }
                            ?>
                            </div>
                        </td>
                        <td data-label="ACCIONES" style="text-align: right;">
                            <div class="action-buttons" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                                <button class="btn-icon" onclick="ClientModule.editClient(<?php echo $client['id']; ?>)" title="Editar">
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <button class="btn-icon" onclick="ClientModule.deleteClient(<?php echo $client['id']; ?>)" title="Eliminar" style="color: var(--color-danger);">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Cliente -->
<div class="modal-overlay" id="clientModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 class="modal-title" id="clientModalTitle">Nuevo Cliente</h2>
            <button class="btn-icon close-modal" onclick="ClientModule.closeModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <form id="clientForm" onsubmit="return false;">
                <input type="hidden" name="client_id" id="client_id">
                
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
                    <div style="flex: 2; min-width: 200px;">
                        <label class="form-label">Nombre del Cliente *</label>
                        <input type="text" class="form-control" name="name" id="client_name" placeholder="Ej: Empresa S.A." required>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <label class="form-label">DNI</label>
                        <input type="text" class="form-control" name="dni" id="client_dni" placeholder="Ej: 71234567">
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <label class="form-label">WhatsApp</label>
                        <input type="text" class="form-control" name="whatsapp" id="client_whatsapp" placeholder="Ej: +1234567890">
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" name="email" id="client_email" placeholder="correo@empresa.com">
                    </div>
                </div>

                <!-- Card de Marcas -->
                <div style="padding: 1rem; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
                    <h3 style="font-size: 1rem; margin-bottom: 1rem; font-weight: 600;">Marcas Asociadas</h3>
                    
                    <div style="display: flex; gap: 0.5rem; align-items: flex-end; margin-bottom: 1rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 150px;">
                            <label class="form-label" style="font-size:0.75rem;">Nombre de Marca</label>
                            <input type="text" class="form-control" id="new_brand_name" placeholder="Ej: Mi Marca">
                        </div>
                        <div style="flex: 1; min-width: 180px;">
                            <label class="form-label" style="font-size:0.75rem;">Logotipo (Imagen)</label>
                            <input type="file" class="form-control" id="new_brand_logo" accept="image/*">
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="ClientModule.addBrand()" style="height: 42px;">
                            <i class="ph ph-plus"></i> Agregar
                        </button>
                    </div>

                    <!-- Contenedor dinámico de marcas -->
                    <div id="brandsList" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <!-- Se insertan aquí las marcas via JS -->
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
            <button class="btn btn-outline" onclick="ClientModule.closeModal()">Cancelar</button>
            <button class="btn btn-primary" id="btnSaveClient" onclick="ClientModule.saveClient()">
                <span class="btn-text">Guardar Cliente</span>
            </button>
        </div>
    </div>
</div>

<script src="assets/js/modules/clients.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
