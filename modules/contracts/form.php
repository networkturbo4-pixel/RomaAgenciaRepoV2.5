<?php
// modules/contracts/form.php
if (!isset($_SESSION['user_id']) || !in_array('contracts', $_SESSION['user_permissions'] ?? [])) {
    header("Location: index.php?module=auth&action=login");
    exit;
}

// Fetch Global Settings
$stmt = $db->query("SELECT * FROM settings");
$global_settings_raw = $stmt->fetchAll();
$global_settings = [];
foreach ($global_settings_raw as $row) {
    $global_settings[$row['setting_key']] = $row['setting_value'];
}
$site_name = $global_settings['site_name'] ?? 'Nuestra Agencia';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_id = $_POST['template_id'] ?? null;
    $target_type = $_POST['target_type'] ?? 'client';
    $title = $_POST['title'] ?? 'Contrato sin título';
    $total_amount = !empty($_POST['total_amount']) ? $_POST['total_amount'] : null;
    
    $client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
    $staff_id = !empty($_POST['staff_id']) ? $_POST['staff_id'] : null;
    $service_id = !empty($_POST['service_id']) ? $_POST['service_id'] : null;

    // Fetch template
    $stmtTpl = $db->prepare("SELECT * FROM contract_templates WHERE id = ?");
    $stmtTpl->execute([$template_id]);
    $template = $stmtTpl->fetch(PDO::FETCH_ASSOC);
    
    if ($template) {
        $body = $template['body'];
        
        // Always replace generic variables
        $body = str_replace('[NOMBRE_AGENCIA]', $site_name, $body);

        // Replace specific variables
        if ($target_type === 'client' && $client_id) {
            $stmtC = $db->prepare("SELECT * FROM clients WHERE id = ?");
            $stmtC->execute([$client_id]);
            $client = $stmtC->fetch(PDO::FETCH_ASSOC);
            if ($client) {
                $body = str_replace('[NOMBRE_CLIENTE]', $client['name'], $body);
                $body = str_replace('[DNI_CLIENTE]', $client['document_number'] ?? '____________', $body);
            }
            if ($service_id) {
                $stmtS = $db->prepare("SELECT * FROM services WHERE id = ?");
                $stmtS->execute([$service_id]);
                $service = $stmtS->fetch(PDO::FETCH_ASSOC);
                if ($service) {
                    $body = str_replace('[NOMBRE_SERVICIO]', $service['name'], $body);
                    
                    // Fetch Features and Deliverables
                    $stmtF = $db->prepare("SELECT * FROM service_features WHERE service_id = ? ORDER BY type ASC, sort_order ASC");
                    $stmtF->execute([$service_id]);
                    $serviceFeatures = $stmtF->fetchAll(PDO::FETCH_ASSOC);
                    
                    $deliverablesHtml = '';
                    $featuresHtml = '';
                    
                    $delivs = array_filter($serviceFeatures, fn($f) => $f['type'] === 'deliverable');
                    $feats = array_filter($serviceFeatures, fn($f) => $f['type'] !== 'deliverable');
                    
                    // Build Deliverables Table
                    if (count($delivs) > 0) {
                        $deliverablesHtml .= '<table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse: collapse; border: 1px solid #e5e7eb; margin-bottom: 1rem;">';
                        $deliverablesHtml .= '<thead><tr><th style="background-color:#f9fafb; text-align:left; border: 1px solid #e5e7eb; width:35%;">Entregable</th><th style="background-color:#f9fafb; text-align:left; border: 1px solid #e5e7eb;">Descripción</th></tr></thead><tbody>';
                        foreach($delivs as $d) {
                            $deliverablesHtml .= '<tr><td style="border: 1px solid #e5e7eb;"><strong>' . htmlspecialchars($d['title'] ?? '') . '</strong></td><td style="border: 1px solid #e5e7eb;">' . nl2br(htmlspecialchars($d['description'] ?? '')) . '</td></tr>';
                        }
                        $deliverablesHtml .= '</tbody></table>';
                    } else {
                        $deliverablesHtml = '<p><em>No hay entregables específicos definidos.</em></p>';
                    }
                    
                    // Build Features Table
                    if (count($feats) > 0) {
                        $featuresHtml .= '<table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse: collapse; border: 1px solid #e5e7eb; margin-bottom: 1rem;">';
                        $featuresHtml .= '<thead><tr><th style="background-color:#f9fafb; text-align:left; border: 1px solid #e5e7eb; width:35%;">Característica</th><th style="background-color:#f9fafb; text-align:left; border: 1px solid #e5e7eb;">Detalle</th></tr></thead><tbody>';
                        foreach($feats as $f) {
                            $featuresHtml .= '<tr><td style="border: 1px solid #e5e7eb;"><strong>' . htmlspecialchars($f['title'] ?? '') . '</strong></td><td style="border: 1px solid #e5e7eb;">' . nl2br(htmlspecialchars($f['description'] ?? '')) . '</td></tr>';
                        }
                        $featuresHtml .= '</tbody></table>';
                    } else {
                        $featuresHtml = '<p><em>No hay características específicas definidas.</em></p>';
                    }
                    
                    $body = str_replace('[TABLA_ENTREGABLES]', $deliverablesHtml, $body);
                    $body = str_replace('[TABLA_CARACTERISTICAS]', $featuresHtml, $body);

                }
            } else {
                $body = str_replace('[NOMBRE_SERVICIO]', 'los servicios acordados', $body);
                $body = str_replace('[TABLA_ENTREGABLES]', '', $body);
                $body = str_replace('[TABLA_CARACTERISTICAS]', '', $body);
            }
            if ($total_amount) {
                $currency = $global_settings['currency'] ?? 'USD';
                $body = str_replace('[PRECIO_TOTAL]', $currency . ' ' . number_format($total_amount, 2), $body);
            } else {
                $body = str_replace('[PRECIO_TOTAL]', 'la cantidad acordada', $body);
            }
        } elseif ($target_type === 'staff' && $staff_id) {
            $stmtS = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmtS->execute([$staff_id]);
            $staff = $stmtS->fetch(PDO::FETCH_ASSOC);
            if ($staff) {
                $body = str_replace('[NOMBRE_PERSONAL]', $staff['name'], $body);
                $body = str_replace('[DNI_PERSONAL]', $staff['email'], $body); // Using email as DNI placeholder if DNI not available
            }
        }

        function generateUUID() {
            return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
                mt_rand( 0, 0xffff ),
                mt_rand( 0, 0x0fff ) | 0x4000,
                mt_rand( 0, 0x3fff ) | 0x8000,
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
            );
        }
        
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $dia = date('d');
        $mes = $meses[date('n') - 1];
        $ano = date('Y');
        $ciudad = $_POST['city'] ?? $global_settings['company_city'] ?? 'Lima';

        $body = str_replace('[DÍA]', $dia, $body);
        $body = str_replace('[MES]', $mes, $body);
        $body = str_replace('[AÑO]', $ano, $body);
        $body = str_replace('[CIUDAD]', $ciudad, $body);

        $uuid = generateUUID();
        $folder_id = !empty($_POST['folder_id']) ? $_POST['folder_id'] : null;

        $stmt = $db->prepare("INSERT INTO contracts (uuid, template_id, client_id, staff_id, service_id, title, body, status, total_amount, folder_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
        $stmt->execute([
            $uuid,
            $template_id,
            $target_type === 'client' ? $client_id : null,
            $target_type === 'staff' ? $staff_id : null,
            $service_id,
            $title,
            $body,
            $total_amount,
            $folder_id
        ]);

        header("Location: index.php?module=contracts&action=index&success=1");
        exit;
    }
}

// Data for forms
$templates = $db->query("SELECT * FROM contract_templates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$clients = $db->query("SELECT * FROM clients ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$staffList = $db->query("SELECT * FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$services = $db->query("SELECT * FROM services WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$folders = $db->query("SELECT * FROM contract_folders ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include 'includes/header.php'; ?>

<div class="main-content">
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 1.25rem;">
            <div style="width: 56px; height: 56px; background: var(--bg-body); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
                <i class="ph ph-file-plus" style="font-size: 1.75rem; color: var(--primary-color);"></i>
            </div>
            <div>
                <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Nuevo Contrato</h1>
                <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Genera un contrato a partir de una plantilla.</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <a href="index.php?module=contracts&action=index" class="btn btn-outline" style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none;">
                <i class="ph ph-arrow-left"></i> Volver a Contratos
            </a>
        </div>
    </div>

    <div style="background:var(--bg-surface); padding:2rem; border-radius:var(--radius-lg); box-shadow:0 4px 6px rgba(0,0,0,0.02); border:1px solid var(--border-color); max-width: 800px;">
        <form method="POST">
            <div class="form-group">
                <label>Título del Contrato (Interno)</label>
                <input type="text" name="title" class="form-control" placeholder="Ej: Contrato Web - Empresa SAC" required>
            </div>

            <div class="form-group">
                <label>Carpeta (Opcional)</label>
                <select name="folder_id" class="form-control">
                    <option value="">Sin carpeta</option>
                    <?php foreach($folders as $f): ?>
                        <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Plantilla a utilizar</label>
                <select name="template_id" id="template_id" class="form-control" required onchange="updateForm()">
                    <option value="">Selecciona una plantilla...</option>
                    <?php foreach($templates as $t): ?>
                        <option value="<?php echo $t['id']; ?>" data-type="<?php echo $t['target_type']; ?>">
                            <?php echo htmlspecialchars($t['name']); ?> (<?php echo $t['target_type']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="target_type" id="target_type" value="client">
            </div>

            <!-- Client Section -->
            <div id="section_client" style="display:none; margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid var(--border-color);">
                <h4>Datos del Cliente</h4>
                <div class="form-group mt-3">
                    <label>Cliente</label>
                    <select name="client_id" class="form-control">
                        <option value="">Selecciona un cliente...</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Servicio Relacionado (Opcional)</label>
                    <select name="service_id" id="service_id" class="form-control" onchange="updateServicePrice()">
                        <option value="" data-price="">Selecciona un servicio...</option>
                        <?php foreach($services as $s): ?>
                            <option value="<?php echo $s['id']; ?>" data-price="<?php echo htmlspecialchars($s['price'] ?? '0.00'); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto Total (Opcional, para variable [PRECIO_TOTAL])</label>
                    <input type="number" step="0.01" name="total_amount" id="total_amount" class="form-control" placeholder="0.00">
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label>Ciudad de Emisión (Para variable [CIUDAD])</label>
                    <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($global_settings['company_city'] ?? 'Lima'); ?>">
                </div>
            </div>

            <!-- Staff Section -->
            <div id="section_staff" style="display:none; margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid var(--border-color);">
                <h4>Datos del Personal (RRHH)</h4>
                <div class="form-group mt-3">
                    <label>Miembro del equipo</label>
                    <select name="staff_id" class="form-control">
                        <option value="">Selecciona al trabajador...</option>
                        <?php foreach($staffList as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mt-4" style="text-align:right;">
                <button type="submit" class="btn btn-primary" id="btnSubmit" disabled>Generar Contrato</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateServicePrice() {
    const select = document.getElementById('service_id');
    const option = select.options[select.selectedIndex];
    if (option && option.value) {
        const price = option.getAttribute('data-price');
        document.getElementById('total_amount').value = price;
    }
}

function updateForm() {
    const select = document.getElementById('template_id');
    const option = select.options[select.selectedIndex];
    const type = option ? option.getAttribute('data-type') : '';
    
    document.getElementById('target_type').value = type;
    document.getElementById('section_client').style.display = 'none';
    document.getElementById('section_staff').style.display = 'none';
    document.getElementById('btnSubmit').disabled = false;

    if (type === 'client') {
        document.getElementById('section_client').style.display = 'block';
    } else if (type === 'staff') {
        document.getElementById('section_staff').style.display = 'block';
    } else if (!type) {
        document.getElementById('btnSubmit').disabled = true;
    }
}
</script>

<?php include 'includes/footer.php'; ?>
