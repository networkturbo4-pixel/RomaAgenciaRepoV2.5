<!-- modules/conexiones/tabs/crm_api.php -->
<?php
// Obtener URL base actual del CRM
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : str_replace('\\', '/', $scriptDir);
$crmBaseUrl = rtrim($protocol . $host . $scriptDir, '/');
$endpointUrl = $crmBaseUrl . '/modules/mensajes/api_widget.php';

// Si no existe aún una clave, generamos una por defecto
$appKey = $settings['crm_api_key'] ?? '';
if (empty($appKey)) {
    $appKey = 'roma_live_' . bin2hex(random_bytes(16));
    // Guardar por defecto
    try {
        global $db;
        $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('crm_api_key', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
           ->execute([$appKey, $appKey]);
        $settings['crm_api_key'] = $appKey;
    } catch (Exception $e) {}
}

$apiEnabled = ($settings['crm_api_enabled'] ?? '1') === '1';
$lastAccess = $settings['crm_api_last_access'] ?? null;
?>

<form method="POST" action="?module=conexiones&tab=tab-crm-api" class="roma-connection-form">
    <input type="hidden" name="action_type" value="crm_api">

    <!-- Hero Card -->
    <div class="roma-conn-hero">
        <div class="roma-conn-hero-content">
            <div class="roma-conn-hero-icon">
                <i class="ph ph-wordpress-logo"></i>
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px; flex-wrap: wrap;">
                    <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--color-title, #1e293b);">WordPress & API Externa (Roma Portal)</h3>
                    <?php if ($apiEnabled): ?>
                        <span class="roma-badge-active"><span class="roma-pulse-dot"></span> Conexión Habilitada</span>
                    <?php else: ?>
                        <span class="roma-badge-inactive">Conexión Pausada</span>
                    <?php endif; ?>
                </div>
                <p style="margin: 0; font-size: 0.9rem; color: var(--text-muted, #64748b); line-height: 1.45;">
                    Genera tu clave de aplicación (App Key) para conectar de forma segura el plugin de WordPress con el CRM en tiempo real.
                </p>
            </div>
        </div>
    </div>

    <!-- Toggle de Activación -->
    <div class="roma-setting-card" style="margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <label style="font-size: 0.95rem; font-weight: 700; color: var(--color-title, #1e293b); display: flex; align-items: center; gap: 6px; margin: 0;">
                    <i class="ph ph-shield-check" style="font-size: 1.2rem; color: var(--primary-color, #6366f1);"></i>
                    Estado de la Conexión API
                </label>
                <p style="margin: 3px 0 0 0; font-size: 0.85rem; color: var(--text-muted, #64748b);">
                    Permite o bloquea el acceso de las consultas provenientes del plugin de WordPress.
                </p>
            </div>
            <label class="roma-switch">
                <input type="checkbox" name="crm_api_enabled" value="1" <?php echo $apiEnabled ? 'checked' : ''; ?>>
                <span class="roma-slider"></span>
            </label>
        </div>
    </div>

    <!-- App Key Generator Section -->
    <div class="roma-setting-card" style="margin-bottom: 1.5rem;">
        <div style="margin-bottom: 1.25rem;">
            <label style="font-size: 0.95rem; font-weight: 700; color: var(--color-title, #1e293b); display: flex; align-items: center; gap: 6px;">
                <i class="ph ph-key" style="font-size: 1.2rem; color: #f59e0b;"></i>
                App Key del CRM (Clave Secreta de Acceso)
            </label>
            <p style="margin: 3px 0 0 0; font-size: 0.85rem; color: var(--text-muted, #64748b);">
                Pega esta clave en la configuración del plugin en WordPress para autenticar las peticiones de tus clientes.
            </p>
        </div>

        <div class="roma-key-input-box">
            <div class="roma-key-display">
                <input type="password" id="crm-app-key-input" name="crm_api_key" value="<?php echo htmlspecialchars($appKey); ?>" readonly class="form-control" style="font-family: monospace; font-size: 1rem; font-weight: 600; letter-spacing: 0.05em;">
                <button type="button" id="btn-toggle-key-visibility" class="roma-btn-icon" title="Mostrar / Ocultar clave">
                    <i class="ph ph-eye"></i>
                </button>
            </div>
            <div class="roma-key-actions">
                <button type="button" id="btn-copy-app-key" class="btn btn-secondary" style="display: flex; align-items: center; gap: 6px;">
                    <i class="ph ph-copy"></i>
                    <span id="copy-btn-text">Copiar App Key</span>
                </button>
                <button type="button" id="btn-regenerate-app-key" class="btn btn-outline" style="display: flex; align-items: center; gap: 6px; color: #ef4444; border-color: #fca5a5;">
                    <i class="ph ph-arrows-clockwise"></i>
                    <span>Regenerar Clave</span>
                </button>
            </div>
        </div>
        <div id="key-feedback-msg" style="margin-top: 8px; font-size: 0.85rem; font-weight: 600; display: none;"></div>
    </div>

    <!-- URLs de Conexión para WordPress -->
    <div class="roma-setting-card" style="margin-bottom: 1.5rem;">
        <h4 style="margin: 0 0 1rem 0; font-size: 0.95rem; font-weight: 700; color: var(--color-title, #1e293b); display: flex; align-items: center; gap: 6px;">
            <i class="ph ph-link" style="font-size: 1.2rem; color: var(--primary-color, #6366f1);"></i>
            Parámetros para Configurar en WordPress
        </h4>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;" class="roma-url-grid">
            <div class="form-group" style="margin: 0;">
                <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted, #64748b);">URL del CRM (Pegar en WordPress)</label>
                <div style="display: flex; gap: 6px; margin-top: 4px;">
                    <input type="text" id="crm-base-url" value="<?php echo htmlspecialchars($crmBaseUrl); ?>" readonly class="form-control" style="font-family: monospace; font-size: 0.85rem;">
                    <button type="button" class="btn btn-secondary" onclick="copyToClipboard('<?php echo addslashes($crmBaseUrl); ?>', this)" style="padding: 0 12px;" title="Copiar URL">
                        <i class="ph ph-copy"></i>
                    </button>
                </div>
            </div>

            <div class="form-group" style="margin: 0;">
                <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted, #64748b);">Endpoint de la API (api_widget.php)</label>
                <div style="display: flex; gap: 6px; margin-top: 4px;">
                    <input type="text" value="<?php echo htmlspecialchars($endpointUrl); ?>" readonly class="form-control" style="font-family: monospace; font-size: 0.85rem; color: #64748b;">
                    <button type="button" class="btn btn-secondary" onclick="copyToClipboard('<?php echo addslashes($endpointUrl); ?>', this)" style="padding: 0 12px;" title="Copiar Endpoint">
                        <i class="ph ph-copy"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Guía y Métricas -->
    <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;" class="roma-info-grid">
        <!-- Tarjeta de Instrucciones -->
        <div class="roma-setting-card">
            <h4 style="margin: 0 0 12px 0; font-size: 0.95rem; font-weight: 700; color: var(--color-title, #1e293b); display: flex; align-items: center; gap: 6px;">
                <i class="ph ph-book-open"></i> Pasos para Conectar con WordPress
            </h4>
            <ol style="margin: 0; padding-left: 20px; font-size: 0.88rem; color: var(--text-muted, #64748b); line-height: 1.6;">
                <li>Instala el plugin <strong>Roma Chat & Portal</strong> en tu WordPress.</li>
                <li>Entra al menú <strong>Roma Portal</strong> en el panel de WordPress.</li>
                <li>En <strong>URL del CRM</strong>, pega la URL base: <code><?php echo htmlspecialchars($crmBaseUrl); ?></code>.</li>
                <li>En <strong>App Key</strong>, pega la clave generada arriba.</li>
                <li>Haz clic en <strong>🔌 Probar Conexión</strong> y luego en <strong>Guardar</strong>.</li>
            </ol>
        </div>

        <!-- Tarjeta de Estado del Servicio -->
        <div class="roma-setting-card">
            <h4 style="margin: 0 0 12px 0; font-size: 0.95rem; font-weight: 700; color: var(--color-title, #1e293b); display: flex; align-items: center; gap: 6px;">
                <i class="ph ph-activity"></i> Estado de la Integración
            </h4>
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 0.85rem;">
                <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed var(--border-color, #e2e8f0); padding-bottom: 6px;">
                    <span style="color: var(--text-muted, #64748b);">WebSockets en tiempo real:</span>
                    <strong style="color: #10b981;">Pusher Activo</strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed var(--border-color, #e2e8f0); padding-bottom: 6px;">
                    <span style="color: var(--text-muted, #64748b);">CORS para peticiones:</span>
                    <strong style="color: #10b981;">Habilitado (*)</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted, #64748b);">Última actividad web:</span>
                    <strong><?php echo $lastAccess ? date('d/m/Y H:i', strtotime($lastAccess)) : 'Sin actividad registrada aún'; ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón de Guardar -->
    <div style="display: flex; justify-content: flex-end; gap: 1rem;">
        <button type="submit" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px; padding: 10px 24px; font-weight: 600;">
            <i class="ph ph-floppy-disk"></i> Guardar Cambios de Conexión
        </button>
    </div>
</form>

<script>
// Copiar texto genérico
function copyToClipboard(text, btnElement) {
    navigator.clipboard.writeText(text).then(function() {
        if (btnElement) {
            const originalHtml = btnElement.innerHTML;
            btnElement.innerHTML = '<i class="ph ph-check" style="color: #10b981;"></i>';
            setTimeout(() => { btnElement.innerHTML = originalHtml; }, 1800);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const keyInput = document.getElementById('crm-app-key-input');
    const toggleBtn = document.getElementById('btn-toggle-key-visibility');
    const copyBtn = document.getElementById('btn-copy-app-key');
    const copyText = document.getElementById('copy-btn-text');
    const regenBtn = document.getElementById('btn-regenerate-app-key');
    const feedback = document.getElementById('key-feedback-msg');

    // Ver / Ocultar clave
    if (toggleBtn && keyInput) {
        toggleBtn.addEventListener('click', function() {
            const isPassword = keyInput.type === 'password';
            keyInput.type = isPassword ? 'text' : 'password';
            toggleBtn.innerHTML = isPassword ? '<i class="ph ph-eye-slash"></i>' : '<i class="ph ph-eye"></i>';
        });
    }

    // Copiar clave
    if (copyBtn && keyInput) {
        copyBtn.addEventListener('click', function() {
            navigator.clipboard.writeText(keyInput.value).then(function() {
                copyText.textContent = '¡Copiado!';
                copyBtn.classList.remove('btn-secondary');
                copyBtn.classList.add('btn-primary');
                setTimeout(() => {
                    copyText.textContent = 'Copiar App Key';
                    copyBtn.classList.remove('btn-primary');
                    copyBtn.classList.add('btn-secondary');
                }, 2000);
            });
        });
    }

    // Regenerar clave con confirmación
    if (regenBtn && keyInput) {
        regenBtn.addEventListener('click', async function() {
            if (!confirm('¿Estás seguro de regenerar la App Key? La clave anterior dejará de funcionar y deberás actualizarla en tu WordPress.')) {
                return;
            }

            regenBtn.disabled = true;
            regenBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Generando...';

            try {
                const response = await fetch('modules/conexiones/ajax_generate_key.php', {
                    method: 'POST'
                });
                const res = await response.json();

                if (res.success && res.api_key) {
                    keyInput.value = res.api_key;
                    keyInput.type = 'text';
                    if (toggleBtn) toggleBtn.innerHTML = '<i class="ph ph-eye-slash"></i>';
                    
                    feedback.style.display = 'block';
                    feedback.style.color = '#10b981';
                    feedback.textContent = '✅ Nueva clave generada con éxito. Recuerda actualizarla en WordPress.';
                    setTimeout(() => { feedback.style.display = 'none'; }, 5000);
                } else {
                    alert('Error: ' + (res.error || 'No se pudo generar la clave'));
                }
            } catch (err) {
                alert('Error al contactar con el servidor');
            } finally {
                regenBtn.disabled = false;
                regenBtn.innerHTML = '<i class="ph ph-arrows-clockwise"></i> <span>Regenerar Clave</span>';
            }
        });
    }
});
</script>
