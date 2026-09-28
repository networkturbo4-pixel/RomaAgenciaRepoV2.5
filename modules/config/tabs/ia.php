<?php
$hasGroq = !empty($settings['groq_api_key']);
$hasGemini = !empty($settings['gemini_api_key']);
?>

<div class="pane-header">
    <div>
        <h2 class="pane-header-title">
            <i class="ph ph-sparkle" style="color: #f59e0b;"></i> Romita IA (Groq Cloud & Google Gemini)
        </h2>
        <p class="pane-header-desc">Motor híbrido de inteligencia artificial para WhatsApp, atención al cliente y CRM. Combina la velocidad de modelos Open Source con la versatilidad de Gemini.</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <?php if ($hasGroq): ?>
            <span class="integration-status-chip" style="background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3);">
                <i class="ph ph-lightning-fill"></i> Groq Activo (Open Source)
            </span>
        <?php else: ?>
            <span class="integration-status-chip disconnected">
                <i class="ph ph-x-circle-fill"></i> Sin Groq
            </span>
        <?php endif; ?>

        <?php if ($hasGemini): ?>
            <span class="integration-status-chip" style="background: rgba(139,92,246,0.12); color: #8b5cf6; border: 1px solid rgba(139,92,246,0.3);">
                <i class="ph ph-sparkle-fill"></i> Gemini Activo
            </span>
        <?php else: ?>
            <span class="integration-status-chip disconnected">
                <i class="ph ph-x-circle-fill"></i> Sin Gemini
            </span>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="index.php?module=config">
    <input type="hidden" name="action_type" value="ia">
    
    <!-- CARD 1: GROQ CLOUD (MOTOR OPEN SOURCE PRINCIPAL) -->
    <div class="settings-card" style="margin-bottom: 1.5rem;">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-lightning" style="color: #f59e0b;"></i> Motor Principal: Groq Cloud (Llama 3 / Qwen Open Source)</h3>
                <p class="settings-card-desc">Ejecución ultra-rápida (menos de 0.5s), sin consumo de RAM ni CPU en tu cPanel y con cuota gratuita generosa.</p>
            </div>
        </div>

        <div class="form-group">
            <label for="groq_api_key">Groq API Key</label>
            <div class="input-with-icon">
                <i class="ph ph-key" style="color: #f59e0b;"></i>
                <input type="password" name="groq_api_key" id="groq_api_key" class="form-control" value="<?php echo htmlspecialchars($settings['groq_api_key'] ?? ''); ?>" placeholder="gsk_...">
            </div>
            <small class="text-muted" style="display:block; margin-top:0.35rem; font-size: 11.5px;">
                Obtén tu clave gratuita en <a href="https://console.groq.com/keys" target="_blank" rel="noopener" style="color: var(--primary-color); text-decoration: underline;">Groq Console</a>. Los modelos Open Source se ejecutan en unidades LPU de alta velocidad.
            </small>
        </div>

        <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <span style="font-size: 12.5px; color: var(--text-muted);">Comprobación de conectividad Groq:</span>
                <span id="groq-status" style="margin-left: 0.5rem; font-size: 12.5px; font-weight: 600;"></span>
            </div>
            <button type="button" class="btn btn-outline btn-sm" id="btn-test-groq" style="border-radius: 8px; color: #d97706; border-color: #d97706; display: inline-flex; align-items: center; gap: 0.4rem;">
                <i class="ph ph-lightning"></i> Probar Conexión con Groq
            </button>
        </div>
    </div>

    <!-- CARD 2: GOOGLE GEMINI (MOTOR MULTIMODAL & CONTINGENCIA) -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-sparkle" style="color: #8b5cf6;"></i> Motor de Respaldo & Multimodal: Google Gemini</h3>
                <p class="settings-card-desc">Se activa automáticamente si se adjuntan imágenes o en caso de que la cuota de Groq se agote momentáneamente.</p>
            </div>
        </div>

        <div class="form-group">
            <label for="gemini_api_key">Gemini API Key (o múltiples claves separadas por coma)</label>
            <div class="input-with-icon">
                <i class="ph ph-sparkle" style="color: #8b5cf6;"></i>
                <input type="password" name="gemini_api_key" id="gemini_api_key" class="form-control" value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>" placeholder="AIzaSy... (o clave1, clave2)">
            </div>
            <small class="text-muted" style="display:block; margin-top:0.35rem; font-size: 11.5px;">
                Obtén tu clave de forma gratuita en <a href="https://aistudio.google.com/" target="_blank" rel="noopener" style="color: var(--primary-color); text-decoration: underline;">Google AI Studio</a>. Puedes ingresar una o varias claves separadas por comas.
            </small>
        </div>

        <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <span style="font-size: 12.5px; color: var(--text-muted);">Comprobación de conectividad Gemini:</span>
                <span id="gemini-status" style="margin-left: 0.5rem; font-size: 12.5px; font-weight: 600;"></span>
            </div>
            <button type="button" class="btn btn-outline btn-sm" id="btn-test-gemini" style="border-radius: 8px; color: #8b5cf6; border-color: #8b5cf6; display: inline-flex; align-items: center; gap: 0.4rem;">
                <i class="ph ph-plugs-connected"></i> Probar Conexión con Gemini
            </button>
        </div>
    </div>

    <!-- ACTION BAR -->
    <div class="settings-action-bar">
        <div class="settings-action-bar-info">
            <i class="ph ph-shield-check"></i>
            <span>Las claves de API son resguardadas de forma segura y solo se invocan en llamadas del servidor.</span>
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 10px;">
            <i class="ph ph-floppy-disk"></i> Guardar Configuración IA
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Test Groq
    const btnTestGroq = document.getElementById('btn-test-groq');
    if (btnTestGroq) {
        btnTestGroq.addEventListener('click', async () => {
            const statusEl = document.getElementById('groq-status');
            statusEl.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Probando Groq...';
            statusEl.style.color = 'var(--text-muted)';
            btnTestGroq.disabled = true;
            
            const fd = new FormData();
            fd.append('query', 'Responde únicamente con la palabra "OK" si recibes este mensaje.');
            fd.append('provider', 'groq');
            
            try {
                const res = await fetch('ajax/gemini_chat.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    statusEl.innerHTML = '<span style="color: #10b981;"><i class="ph ph-check-circle"></i> Conectado (' + (data.provider || 'Groq') + ')</span>';
                } else {
                    statusEl.innerHTML = '<span style="color: #ef4444;"><i class="ph ph-warning-circle"></i> ' + (data.error || 'Clave inválida') + '</span>';
                }
            } catch (err) {
                statusEl.innerHTML = '<span style="color: #ef4444;"><i class="ph ph-warning-circle"></i> Error de red</span>';
            } finally {
                btnTestGroq.disabled = false;
            }
        });
    }

    // Test Gemini
    const btnTestGemini = document.getElementById('btn-test-gemini');
    if (btnTestGemini) {
        btnTestGemini.addEventListener('click', async () => {
            const statusEl = document.getElementById('gemini-status');
            statusEl.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Probando Gemini...';
            statusEl.style.color = 'var(--text-muted)';
            btnTestGemini.disabled = true;
            
            const fd = new FormData();
            fd.append('query', 'Responde únicamente con la palabra "OK" si recibes este mensaje.');
            fd.append('provider', 'gemini');
            
            try {
                const res = await fetch('ajax/gemini_chat.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    statusEl.innerHTML = '<span style="color: #10b981;"><i class="ph ph-check-circle"></i> Conectado (' + (data.provider || 'Gemini') + ')</span>';
                } else {
                    statusEl.innerHTML = '<span style="color: #ef4444;"><i class="ph ph-warning-circle"></i> ' + (data.error || 'Clave inválida') + '</span>';
                }
            } catch (err) {
                statusEl.innerHTML = '<span style="color: #ef4444;"><i class="ph ph-warning-circle"></i> Error de red</span>';
            } finally {
                btnTestGemini.disabled = false;
            }
        });
    }
});
</script>
