<?php
// modules/client_portal/index.php
require_once 'includes/header.php';

// Fetch clients and their portal stats
$stmt = $db->query("
    SELECT c.id, c.name, c.dni, c.portal_enabled,
           (SELECT COUNT(*) FROM client_portal_logs WHERE client_id = c.id) as login_count,
           (SELECT MAX(accessed_at) FROM client_portal_logs WHERE client_id = c.id) as last_login
    FROM clients c
    ORDER BY c.name ASC
");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']);
?>

<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="width: 56px; height: 56px; background: var(--bg-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
            <i class="ph ph-app-window" style="font-size: 1.75rem; color: var(--primary-color);"></i>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Gestión del Portal de Clientes</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Administra el acceso y visualiza la actividad de los clientes en su portal.</p>
        </div>
    </div>
    <div>
        <a href="portal.php" target="_blank" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 8px;">
            <i class="ph ph-arrow-square-out"></i> Abrir Portal
        </a>
    </div>
</div>

<div class="card" style="padding: 1.5rem;">
    <div class="table-responsive">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align: left; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">CLIENTE</th>
                    <th style="text-align: left; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">DNI (ACCESO)</th>
                    <th style="text-align: left; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">ESTADO</th>
                    <th style="text-align: left; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">ÚLTIMO ACCESO</th>
                    <th style="text-align: center; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">SESIONES</th>
                    <th style="text-align: right; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-weight: 600;">ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                <tr>
                    <td style="padding: 1rem 0; border-bottom: 1px solid var(--border-color);">
                        <div style="font-weight: 600; color: var(--color-title);"><?php echo htmlspecialchars($client['name']); ?></div>
                    </td>
                    <td style="padding: 1rem 0; border-bottom: 1px solid var(--border-color);">
                        <?php if($client['dni']): ?>
                            <span style="background: var(--bg-color); padding: 4px 8px; border-radius: 6px; font-family: monospace; font-weight: 600; font-size: 0.9rem; border: 1px solid var(--border-color);"><?php echo htmlspecialchars($client['dni']); ?></span>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-style: italic; font-size: 0.85rem;">No configurado</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 1rem 0; border-bottom: 1px solid var(--border-color);">
                        <label class="toggle-switch" style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" onchange="togglePortalStatus(<?php echo $client['id']; ?>, this.checked)" <?php echo $client['portal_enabled'] ? 'checked' : ''; ?> style="display: none;">
                            <div class="toggle-slider" style="width: 36px; height: 20px; background: <?php echo $client['portal_enabled'] ? 'var(--secondary-color)' : 'var(--border-color)'; ?>; border-radius: 20px; position: relative; transition: 0.2s;">
                                <div style="width: 16px; height: 16px; background: white; border-radius: 50%; position: absolute; top: 2px; left: <?php echo $client['portal_enabled'] ? '18px' : '2px'; ?>; transition: 0.2s;"></div>
                            </div>
                        </label>
                    </td>
                    <td style="padding: 1rem 0; border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.85rem;">
                        <?php echo $client['last_login'] ? date('d/m/Y H:i', strtotime($client['last_login'])) : 'Nunca'; ?>
                    </td>
                    <td style="padding: 1rem 0; border-bottom: 1px solid var(--border-color); text-align: center;">
                        <span style="background: rgba(79, 70, 229, 0.1); color: var(--primary-color); font-weight: 700; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">
                            <?php echo $client['login_count']; ?>
                        </span>
                    </td>
                    <td style="padding: 1rem 0; border-bottom: 1px solid var(--border-color); text-align: right;">
                        <?php if($client['dni']): ?>
                        <button class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;" onclick="generateQR('<?php echo $baseUrl; ?>/portal.php?dni=<?php echo $client['dni']; ?>', '<?php echo htmlspecialchars($client['name']); ?>')">
                            <i class="ph ph-qr-code"></i> Generar QR
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($clients)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">No hay clientes registrados en el sistema.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal QR -->
<div class="modal-overlay" id="qrModal">
    <div class="modal-content" style="max-width: 400px; text-align: center; padding: 2rem;">
        <h3 id="qrModalTitle" style="margin-bottom: 1.5rem; font-size: 1.25rem;">Acceso al Portal</h3>
        <div id="qrcode" style="display: flex; justify-content: center; margin-bottom: 1.5rem; padding: 1rem; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); display: inline-block;"></div>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">El cliente puede escanear este código para abrir su portal directamente.</p>
        <button class="btn btn-primary" style="width: 100%; justify-content: center;" onclick="closeQRModal()">Cerrar</button>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
let qrInstance = null;

function togglePortalStatus(clientId, isEnabled) {
    fetch('modules/client_portal/ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_status&client_id=${clientId}&status=${isEnabled ? 1 : 0}`
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            alert('Error al actualizar estado');
            window.location.reload();
        } else {
            // update UI smoothly
            const slider = event.target.nextElementSibling;
            if (isEnabled) {
                slider.style.background = 'var(--secondary-color)';
                slider.firstElementChild.style.left = '18px';
            } else {
                slider.style.background = 'var(--border-color)';
                slider.firstElementChild.style.left = '2px';
            }
        }
    })
    .catch(e => {
        alert('Error de conexión');
        window.location.reload();
    });
}

function generateQR(url, clientName) {
    document.getElementById('qrModalTitle').innerText = 'Portal de ' + clientName;
    const qrContainer = document.getElementById('qrcode');
    qrContainer.innerHTML = '';
    
    qrInstance = new QRCode(qrContainer, {
        text: url,
        width: 200,
        height: 200,
        colorDark : "#0f172a",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
    
    document.getElementById('qrModal').classList.add('active');
}

function closeQRModal() {
    document.getElementById('qrModal').classList.remove('active');
}
</script>

<?php require_once 'includes/footer.php'; ?>
