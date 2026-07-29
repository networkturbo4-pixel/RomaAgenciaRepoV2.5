<div class="card mb-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; border: none;">
    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; color: #fff;">
        <i class="ph ph-sparkle" style="color: #8b5cf6;"></i> Configuración de Google Gemini AI
    </h2>
    <p style="margin: 0; color: #94a3b8; font-size: 0.9rem;">Integra la inteligencia artificial para automatizar resúmenes, asistir en la paleta de comandos y analizar proyectos.</p>
</div>

<form method="POST" action="index.php?module=config" class="config-form">
    <input type="hidden" name="action_type" value="ia">
    
    <div class="form-group">
        <label class="form-label" style="display:flex; align-items:center; gap:0.25rem;">
            Gemini API Key <i class="ph ph-key" style="color:var(--text-muted);"></i>
        </label>
        <input type="password" name="gemini_api_key" id="gemini_api_key" class="form-control" value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>" placeholder="AIzaSy...">
        <p class="form-text">Obtén tu API Key desde <a href="https://aistudio.google.com/" target="_blank" style="color: var(--primary-color);">Google AI Studio</a>.</p>
    </div>

    <div class="form-group" style="margin-top: 1.5rem; display: flex; gap: 0.5rem; align-items: center;">
        <button type="submit" class="btn btn-primary">
            <i class="ph ph-floppy-disk"></i> Guardar Configuración de IA
        </button>
        <?php if(!empty($settings['gemini_api_key'])): ?>
            <button type="button" class="btn btn-outline" id="btn-test-gemini" style="color: #10b981; border-color: #10b981;">
                <i class="ph ph-plugs-connected"></i> Probar Conexión
            </button>
            <span id="gemini-status" style="margin-left: 0.5rem; font-size: 0.85rem; font-weight: 500;"></span>
        <?php endif; ?>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnTest = document.getElementById('btn-test-gemini');
    if (btnTest) {
        btnTest.addEventListener('click', async () => {
            const statusEl = document.getElementById('gemini-status');
            statusEl.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Conectando...';
            statusEl.style.color = 'var(--text-muted)';
            
            const fd = new FormData();
            fd.append('query', 'Responde únicamente con la palabra "OK" si recibes este mensaje.');
            
            try {
                const res = await fetch('ajax/gemini_chat.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    statusEl.innerHTML = '<i class="ph ph-check-circle"></i> Conectado con éxito';
                    statusEl.style.color = '#10b981';
                } else {
                    statusEl.innerHTML = '<i class="ph ph-warning-circle"></i> Error de conexión: ' + (data.error || 'Clave inválida');
                    statusEl.style.color = '#ef4444';
                }
            } catch (err) {
                statusEl.innerHTML = '<i class="ph ph-warning-circle"></i> Error de red';
                statusEl.style.color = '#ef4444';
            }
        });
    }
});
</script>
