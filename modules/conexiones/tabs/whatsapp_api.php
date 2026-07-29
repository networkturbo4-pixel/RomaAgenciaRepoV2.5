<!-- modules/conexiones/tabs/whatsapp_api.php -->
<form method="POST" action="?module=conexiones&tab=tab-whatsapp" class="form-grid">
    <input type="hidden" name="action_type" value="whatsapp">

    <div class="form-group" style="grid-column: 1 / -1; margin-bottom: 1rem;">
        <div style="background: #e0f2fe; color: #0369a1; padding: 1rem; border-radius: var(--radius-md); border-left: 4px solid #0ea5e9;">
            <h4 style="margin: 0 0 0.5rem 0; font-size: 1rem;"><i class="ph ph-info"></i> Integración con JSON.pe</h4>
            <p style="margin: 0; font-size: 0.9rem;">
                Esta integración utiliza la API de WhatsApp proporcionada por JSON.pe. 
                Obtén tu Token y el ID de tu Instancia desde el panel de control de <a href="https://docs.json.pe" target="_blank">JSON.pe</a>.
            </p>
        </div>
    </div>

    <div class="form-group" style="grid-column: 1 / -1;">
        <label>Token Bearer de JSON.pe</label>
        <input type="password" name="jsonpe_token" value="<?php echo htmlspecialchars($settings['jsonpe_token'] ?? ''); ?>" placeholder="ej. eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." class="form-control">
    </div>

    <div class="form-group">
        <label>ID de la Instancia</label>
        <input type="text" name="jsonpe_instance" value="<?php echo htmlspecialchars($settings['jsonpe_instance'] ?? ''); ?>" placeholder="ej. MiInstanciaSaaS" class="form-control">
    </div>

    <div class="form-group" style="grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="testWhatsApp()">
            <i class="ph ph-paper-plane-tilt"></i> Enviar Mensaje de Prueba
        </button>
        <button type="submit" class="btn btn-primary">
            <i class="ph ph-floppy-disk"></i> Guardar Configuración WhatsApp
        </button>
    </div>
</form>

<script>
async function testWhatsApp() {
    const phone = prompt("Ingresa el número de WhatsApp con código de país (ej. 51999999999):");
    if (!phone) return;

    const btn = event.currentTarget;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Probando...';
    btn.disabled = true;

    try {
        const response = await fetch('index.php?module=conexiones&action=ajax_test_whatsapp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: phone })
        });
        const result = await response.json();
        
        if (result.success) {
            alert('Mensaje de prueba enviado con éxito.');
        } else {
            alert('Error: ' + result.error);
        }
    } catch (e) {
        alert('Ocurrió un error en la solicitud.');
    } finally {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }
}
</script>
