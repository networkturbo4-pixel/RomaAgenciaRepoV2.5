<!-- modules/conexiones/tabs/smtp.php -->
<form method="POST" action="?module=conexiones&tab=tab-smtp" class="form-grid">
    <input type="hidden" name="action_type" value="smtp">

    <div class="form-group">
        <label>Servidor SMTP (Host)</label>
        <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" placeholder="ej. smtp.gmail.com" class="form-control">
    </div>

    <div class="form-group">
        <label>Puerto SMTP</label>
        <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>" placeholder="587 o 465" class="form-control">
    </div>

    <div class="form-group">
        <label>Usuario (Correo Electrónico)</label>
        <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>" placeholder="ej. hola@miempresa.com" class="form-control">
    </div>

    <div class="form-group">
        <label>Contraseña / App Password</label>
        <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>" class="form-control">
        <small class="form-text text-muted">Si usas Gmail, genera una contraseña de aplicación.</small>
    </div>

    <div class="form-group">
        <label>Correo Remitente (From Email)</label>
        <input type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>" placeholder="ej. no-reply@miempresa.com" class="form-control">
    </div>

    <div class="form-group">
        <label>Nombre Remitente (From Name)</label>
        <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? ''); ?>" placeholder="ej. Mi Empresa S.A." class="form-control">
    </div>

    <div class="form-group" style="grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem;">
        <button type="button" class="btn btn-secondary" onclick="testSmtp()">
            <i class="ph ph-paper-plane-tilt"></i> Probar Conexión
        </button>
        <button type="submit" class="btn btn-primary">
            <i class="ph ph-floppy-disk"></i> Guardar Configuración SMTP
        </button>
    </div>
</form>

<script>
async function testSmtp() {
    const toEmail = prompt("Ingresa un correo para recibir la prueba:");
    if (!toEmail) return;

    const btn = event.currentTarget;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Probando...';
    btn.disabled = true;

    try {
        const response = await fetch('index.php?module=conexiones&action=ajax_test_smtp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: toEmail })
        });
        const result = await response.json();
        
        if (result.success) {
            alert('Correo de prueba enviado con éxito.');
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
