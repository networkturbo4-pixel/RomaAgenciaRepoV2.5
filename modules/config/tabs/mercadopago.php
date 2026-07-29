<?php
// modules/config/tabs/mercadopago.php

$mpToken = $settings['mp_access_token'] ?? '';
$mpPublicKey = $settings['mp_public_key'] ?? '';
$mpMode = $settings['mp_mode'] ?? 'sandbox';
$mpEnabled = $settings['mp_enabled'] ?? '1';

// Mask access token: show only last 4 characters
$mpTokenMasked = '';
if (!empty($mpToken)) {
    $mpTokenMasked = str_repeat('•', max(0, strlen($mpToken) - 4)) . substr($mpToken, -4);
}
?>
<div class="card-section" style="padding: 1.5rem; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
    <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem; color: #00b1ea; display: flex; align-items: center; gap: 0.5rem;">
        <i class="ph ph-credit-card"></i> Integración de Mercado Pago
    </h2>
    <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem;">
        Configura tus credenciales de Mercado Pago para habilitar pagos en línea. Obtén tus credenciales desde
        <a href="https://www.mercadopago.com.pe/developers/panel/app" target="_blank" rel="noopener" style="color: #00b1ea; text-decoration: underline;">
            el panel de desarrolladores de Mercado Pago
        </a>.
    </p>

    <form method="POST" action="index.php?module=config">
        <input type="hidden" name="action_type" value="mercadopago">

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            <!-- Credenciales -->
            <div style="background: var(--bg-color); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <h3 style="font-size: 1rem; margin-bottom: 1rem;"><i class="ph ph-key"></i> Credenciales API</h3>

                <div class="form-group">
                    <label>Access Token</label>
                    <input type="text" name="mp_access_token" class="form-control" value="<?php echo htmlspecialchars($mpToken); ?>" placeholder="TEST-xxx... o APP-xxx...">
                    <?php if (!empty($mpTokenMasked)): ?>
                        <small class="text-muted" style="display:block; margin-top:0.25rem;">Token actual: <code><?php echo $mpTokenMasked; ?></code></small>
                    <?php else: ?>
                        <small class="text-muted" style="display:block; margin-top:0.25rem;">Ingresa tu Access Token de Mercado Pago.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Public Key</label>
                    <input type="text" name="mp_public_key" class="form-control" value="<?php echo htmlspecialchars($mpPublicKey); ?>" placeholder="TEST-xxx... o APP-xxx...">
                    <small class="text-muted" style="display:block; margin-top:0.25rem;">Requerida para el checkout en el frontend.</small>
                </div>
            </div>

            <!-- Modo y Estado -->
            <div style="background: var(--bg-color); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <h3 style="font-size: 1rem; margin-bottom: 1rem;"><i class="ph ph-gear"></i> Configuración</h3>

                <div class="form-group">
                    <label>Modo de operación</label>
                    <select name="mp_mode" class="form-control">
                        <option value="sandbox" <?php echo $mpMode === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Pruebas)</option>
                        <option value="production" <?php echo $mpMode === 'production' ? 'selected' : ''; ?>>Production (Real)</option>
                    </select>
                    <small class="text-muted" style="display:block; margin-top:0.25rem;">Usa "Sandbox" para pruebas y "Production" para cobros reales.</small>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="hidden" name="mp_enabled" value="0">
                        <input type="checkbox" name="mp_enabled" value="1" <?php echo $mpEnabled === '1' ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #00b1ea;">
                        <strong style="font-size: 1rem;">Habilitar Pagos con Mercado Pago</strong>
                    </label>
                    <small class="text-muted" style="display:block; margin-top:0.25rem;">Si desactivas esta opción, el botón de pago con Mercado Pago no aparecerá en la nota de pago para el cliente.</small>
                </div>

                <div style="background: rgba(0, 177, 234, 0.1); border-left: 3px solid #00b1ea; padding: 0.75rem; margin-top: 1rem; border-radius: 0 4px 4px 0;">
                    <strong style="font-size: 0.8rem; color: #00b1ea;">💡 ¿Dónde obtengo las credenciales?</strong><br>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                        Ingresa a <a href="https://www.mercadopago.com.pe/developers/panel/app" target="_blank" rel="noopener" style="color: #00b1ea;">Mercado Pago Developers</a>,
                        selecciona tu aplicación y copia las credenciales de prueba o producción según tu necesidad.
                    </span>
                </div>

                <!-- Estado de conexión -->
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <span style="font-size: 0.85rem; font-weight: 600;">Estado:</span>
                        <span id="mp-connection-status">
                            <?php if (empty($mpToken)): ?>
                                <span style="color: #9ca3af; font-size: 0.85rem; font-weight: 700; background: rgba(156,163,175,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; margin-left: 0.5rem;">
                                    <i class="ph ph-minus-circle"></i> No configurado
                                </span>
                            <?php else: ?>
                                <span style="color: #f59e0b; font-size: 0.85rem; font-weight: 700; background: rgba(245,158,11,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; margin-left: 0.5rem;">
                                    <i class="ph ph-question"></i> Sin verificar
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <button type="button" id="btn-mp-verify" class="btn btn-outline" style="border-color: #00b1ea; color: #00b1ea;" <?php echo empty($mpToken) ? 'disabled title="Guarda tu Access Token primero"' : ''; ?>>
                        <i class="ph ph-plugs-connected"></i> Verificar Conexión
                    </button>
                </div>
            </div>
        </div>

        <div style="text-align: right;">
            <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Guardar Configuración</button>
        </div>
    </form>
</div>

<script>
async function verifyMPConnection() {
    const statusEl = document.getElementById('mp-connection-status');
    const btn = document.getElementById('btn-mp-verify');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Verificando...';
    }

    try {
        const res = await fetch('modules/config/ajax_mp_verify.php');
        const data = await res.json();

        if (data.connected) {
            statusEl.innerHTML = '<span style="color: #10b981; font-size: 0.85rem; font-weight: 700; background: rgba(16,185,129,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; margin-left: 0.5rem;">' +
                '<i class="ph ph-check-circle"></i> Conectado</span>';
        } else {
            statusEl.innerHTML = '<span style="color: #ef4444; font-size: 0.85rem; font-weight: 700; background: rgba(239,68,68,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; margin-left: 0.5rem;">' +
                '<i class="ph ph-x-circle"></i> Desconectado' + (data.error ? ' - ' + data.error : '') + '</span>';
        }
    } catch(e) {
        statusEl.innerHTML = '<span style="color: #ef4444; font-size: 0.85rem; font-weight: 700; background: rgba(239,68,68,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; margin-left: 0.5rem;">' +
            '<i class="ph ph-x-circle"></i> Error de conexión</span>';
    }

    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="ph ph-plugs-connected"></i> Verificar Conexión';
    }
}

document.getElementById('btn-mp-verify').addEventListener('click', verifyMPConnection);

// Auto-verify on page load if credentials exist
<?php if (!empty($mpToken)): ?>
document.addEventListener('DOMContentLoaded', () => {
    // Small delay to let the tab render
    setTimeout(verifyMPConnection, 500);
});
<?php endif; ?>
</script>

