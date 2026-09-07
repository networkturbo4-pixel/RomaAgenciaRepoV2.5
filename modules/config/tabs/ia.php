<?php
$hasGemini = !empty($settings['gemini_api_key']);
?>

<div class="pane-header">
    <div>
        <h2 class="pane-header-title">
            <i class="ph ph-sparkle" style="color: #8b5cf6;"></i> Romita IA (Google Gemini)
        </h2>
        <p class="pane-header-desc">Motor de inteligencia artificial para generación de contenidos, respuestas de soporte y análisis inteligente.</p>
    </div>
    <div>
        <?php if ($hasGemini): ?>
            <span class="integration-status-chip" style="background: rgba(139,92,246,0.12); color: #8b5cf6; border: 1px solid rgba(139,92,246,0.3);">
                <i class="ph ph-sparkle-fill"></i> Gemini Activo
            </span>
        <?php else: ?>
            <span class="integration-status-chip disconnected">
                <i class="ph ph-x-circle-fill"></i> Sin API Key
            </span>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="index.php?module=config">
    <input type="hidden" name="action_type" value="ia">
    
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-key"></i> Clave de API de Google Gemini</h3>
                <p class="settings-card-desc">Genera tu clave gratuita o de pago en Google AI Studio para activar las capacidades de Romita IA.</p>
            </div>
        </div>

        <div class="form-group">
            <label for="gemini_api_key">Gemini API Key</label>
            <div class="input-with-icon">
                <i class="ph ph-sparkle" style="color: #8b5cf6;"></i>
                <input type="password" name="gemini_api_key" id="gemini_api_key" class="form-control" value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>" placeholder="AIzaSy...">
            </div>
            <small class="text-muted" style="display:block; margin-top:0.35rem; font-size: 11.5px;">
                Obtén tu clave de forma gratuita en <a href="https://aistudio.google.com/" target="_blank" rel="noopener" style="color: var(--primary-color); text-decoration: underline;">Google AI Studio</a>.
            </small>
        </div>

        <?php if($hasGemini): ?>
        <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <span style="font-size: 12.5px; color: var(--text-muted);">Comprobación de conectividad:</span>
                <span id="gemini-status" style="margin-left: 0.5rem; font-size: 12.5px; font-weight: 600;"></span>
            </div>
            <button type="button" class="btn btn-outline btn-sm" id="btn-test-gemini" style="border-radius: 8px; color: #8b5cf6; border-color: #8b5cf6; display: inline-flex; align-items: center; gap: 0.4rem;">
                <i class="ph ph-plugs-connected"></i> Probar Conexión con Gemini
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- ACTION BAR -->
    <div class="settings-action-bar">
        <div class="settings-action-bar-info">
            <i class="ph ph-shield-check"></i>
            <span>La API Key es resguardada de forma segura y solo se invoca en llamadas al servidor.</span>
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 10px;">
            <i class="ph ph-floppy-disk"></i> Guardar Configuración IA
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnTest = document.getElementById('btn-test-gemini');
    if (btnTest) {
        btnTest.addEventListener('click', async () => {
            const statusEl = document.getElementById('gemini-status');
            statusEl.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Conectando con Gemini...';
            statusEl.style.color = 'var(--text-muted)';
            btnTest.disabled = true;
            
            const fd = new FormData();
            fd.append('query', 'Responde únicamente con la palabra "OK" si recibes este mensaje.');
            
            try {
                const res = await fetch('ajax/gemini_chat.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    statusEl.innerHTML = '<span style="color: #10b981;"><i class="ph ph-check-circle"></i> Conectado con éxito</span>';
                } else {
                    statusEl.innerHTML = '<span style="color: #ef4444;"><i class="ph ph-warning-circle"></i> ' + (data.error || 'Clave inválida') + '</span>';
                }
            } catch (err) {
                statusEl.innerHTML = '<span style="color: #ef4444;"><i class="ph ph-warning-circle"></i> Error de red</span>';
            } finally {
                btnTest.disabled = false;
            }
        });
    }
});
</script>
