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

<div class="pane-header">
    <div>
        <h2 class="pane-header-title">
            <i class="ph ph-credit-card" style="color: #00b1ea;"></i> Integración de Mercado Pago
        </h2>
        <p class="pane-header-desc">Habilita cobros con tarjetas de crédito, débito y billeteras digitales en las notas de pago.</p>
    </div>
    <div>
        <span id="mp-connection-status">
            <?php if (empty($mpToken)): ?>
                <span class="integration-status-chip disconnected">
                    <i class="ph ph-minus-circle"></i> No configurado
                </span>
            <?php else: ?>
                <span class="integration-status-chip" style="background: rgba(245,158,11,0.12); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3);">
                    <i class="ph ph-circle-notch"></i> Verificando...
                </span>
            <?php endif; ?>
        </span>
    </div>
</div>

<form method="POST" action="index.php?module=config">
    <input type="hidden" name="action_type" value="mercadopago">

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem;">
        <!-- Card 1: Credenciales API -->
        <div class="settings-card" style="margin-bottom: 0;">
            <div class="settings-card-header">
                <div>
                    <h3 class="settings-card-title"><i class="ph ph-key"></i> Credenciales de la API</h3>
                    <p class="settings-card-desc">Obtén tus claves desde el panel oficial de Mercado Pago Developers.</p>
                </div>
            </div>

            <div class="form-group">
                <label for="mp_access_token">Access Token (Privado)</label>
                <div class="input-with-icon">
                    <i class="ph ph-lock-key"></i>
                    <input type="password" id="mp_access_token" name="mp_access_token" class="form-control" value="<?php echo htmlspecialchars($mpToken); ?>" placeholder="TEST-xxx... o APP-xxx...">
                </div>
                <?php if (!empty($mpTokenMasked)): ?>
                    <small class="text-muted" style="display:block; margin-top:0.35rem; font-size: 11px;">Token activo: <code><?php echo $mpTokenMasked; ?></code></small>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="mp_public_key">Public Key (Frontend)</label>
                <div class="input-with-icon">
                    <i class="ph ph-browser"></i>
                    <input type="text" id="mp_public_key" name="mp_public_key" class="form-control" value="<?php echo htmlspecialchars($mpPublicKey); ?>" placeholder="TEST-xxx... o APP-xxx...">
                </div>
                <small class="text-muted" style="display:block; margin-top:0.35rem; font-size: 11px;">Requerida para renderizar el checkout seguro.</small>
            </div>
        </div>

        <!-- Card 2: Modo y Opciones -->
        <div class="settings-card" style="margin-bottom: 0;">
            <div class="settings-card-header">
                <div>
                    <h3 class="settings-card-title"><i class="ph ph-gear"></i> Modo y Activación</h3>
                    <p class="settings-card-desc">Controla si los pagos están activos para tus clientes finales.</p>
                </div>
            </div>

            <div class="form-group">
                <label for="mp_mode">Entorno de Operación</label>
                <div class="input-with-icon">
                    <i class="ph ph-sliders"></i>
                    <select id="mp_mode" name="mp_mode" class="form-control">
                        <option value="sandbox" <?php echo $mpMode === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Modo Pruebas)</option>
                        <option value="production" <?php echo $mpMode === 'production' ? 'selected' : ''; ?>>Producción (Cobros Reales)</option>
                    </select>
                </div>
                <small class="text-muted" style="display:block; margin-top:0.35rem; font-size: 11px;">Asegúrate de usar credenciales de producción para cobros reales.</small>
            </div>

            <div class="form-group" style="margin-top: 1.25rem;">
                <label style="display: flex; align-items: center; gap: 0.65rem; cursor: pointer;">
                    <input type="hidden" name="mp_enabled" value="0">
                    <input type="checkbox" name="mp_enabled" value="1" <?php echo $mpEnabled === '1' ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #00b1ea;">
                    <span style="font-size: 13px; font-weight: 600; color: var(--color-title);">Habilitar Pasarela de Pagos en Notas</span>
                </label>
            </div>

            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 12px; color: var(--text-muted);">Prueba de API:</span>
                <button type="button" id="btn-mp-verify" class="btn btn-outline btn-sm" style="border-radius: 8px; border-color: #00b1ea; color: #00b1ea; display: inline-flex; align-items: center; gap: 0.4rem;" <?php echo empty($mpToken) ? 'disabled title="Ingresa un Access Token"' : ''; ?>>
                    <i class="ph ph-plugs-connected"></i> Probar Conexión
                </button>
            </div>
        </div>
    </div>

    <!-- ACTION BAR -->
    <div class="settings-action-bar">
        <div class="settings-action-bar-info">
            <i class="ph ph-shield-check"></i>
            <span>Las credenciales se cifran y utilizan exclusivamente para verificar los cobros.</span>
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 10px;">
            <i class="ph ph-floppy-disk"></i> Guardar Mercado Pago
        </button>
    </div>
</form>

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
            statusEl.innerHTML = '<span class="integration-status-chip connected"><i class="ph ph-check-circle-fill"></i> Conectado con Éxito</span>';
        } else {
            statusEl.innerHTML = '<span class="integration-status-chip disconnected"><i class="ph ph-x-circle-fill"></i> Error de Credenciales</span>';
        }
    } catch(e) {
        statusEl.innerHTML = '<span class="integration-status-chip disconnected"><i class="ph ph-x-circle-fill"></i> Error de red</span>';
    }

    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="ph ph-plugs-connected"></i> Probar Conexión';
    }
}

const btnVerify = document.getElementById('btn-mp-verify');
if (btnVerify) btnVerify.addEventListener('click', verifyMPConnection);

<?php if (!empty($mpToken)): ?>
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(verifyMPConnection, 400);
});
<?php endif; ?>
</script>
