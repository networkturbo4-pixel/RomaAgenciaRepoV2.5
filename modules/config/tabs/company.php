<form action="index.php?module=config&action=index" method="POST">
    <input type="hidden" name="action_type" value="company">
    
    <div class="form-group">
        <label for="company_trade_name">Nombre Comercial</label>
        <div class="input-with-icon">
            <i class="ph ph-storefront"></i>
            <input type="text" id="company_trade_name" name="company_trade_name" class="form-control" value="<?php echo htmlspecialchars($settings['company_trade_name'] ?? ''); ?>" required>
        </div>
    </div>

    <div class="form-group">
        <label for="company_legal_name">Razón Social</label>
        <div class="input-with-icon">
            <i class="ph ph-buildings"></i>
            <input type="text" id="company_legal_name" name="company_legal_name" class="form-control" value="<?php echo htmlspecialchars($settings['company_legal_name'] ?? ''); ?>" required>
        </div>
    </div>

    <div class="form-group">
        <label for="company_ruc">RUC / Identificación Fiscal</label>
        <div class="input-with-icon">
            <i class="ph ph-identification-card"></i>
            <input type="text" id="company_ruc" name="company_ruc" class="form-control" value="<?php echo htmlspecialchars($settings['company_ruc'] ?? ''); ?>" required>
        </div>
    </div>

    <div class="form-group">
        <label for="company_address">Dirección</label>
        <div class="input-with-icon">
            <i class="ph ph-map-pin"></i>
            <input type="text" id="company_address" name="company_address" class="form-control" value="<?php echo htmlspecialchars($settings['company_address'] ?? ''); ?>">
        </div>
    </div>

    <div class="form-group">
        <label for="company_whatsapp">WhatsApp</label>
        <div class="input-with-icon">
            <i class="ph ph-whatsapp-logo"></i>
            <input type="text" id="company_whatsapp" name="company_whatsapp" class="form-control" value="<?php echo htmlspecialchars($settings['company_whatsapp'] ?? ''); ?>">
        </div>
    </div>

    <div class="form-group">
        <label for="company_email">Correo Electrónico de Contacto</label>
        <div class="input-with-icon">
            <i class="ph ph-envelope"></i>
            <input type="email" id="company_email" name="company_email" class="form-control" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>">
        </div>
    </div>

    <h3 style="font-size: 1rem; margin: var(--space-4) 0 var(--space-2); color: var(--text-main);">Redes Sociales</h3>
    
    <div class="social-card mb-2">
        <i class="ph ph-facebook-logo"></i>
        <input type="url" name="social_facebook" placeholder="https://facebook.com/tuempresa" value="<?php echo htmlspecialchars($settings['social_facebook'] ?? ''); ?>">
    </div>
    
    <div class="social-card mb-2">
        <i class="ph ph-instagram-logo"></i>
        <input type="url" name="social_instagram" placeholder="https://instagram.com/tuempresa" value="<?php echo htmlspecialchars($settings['social_instagram'] ?? ''); ?>">
    </div>
    
    <div class="social-card mb-4">
        <i class="ph ph-linkedin-logo"></i>
        <input type="url" name="social_linkedin" placeholder="https://linkedin.com/company/tuempresa" value="<?php echo htmlspecialchars($settings['social_linkedin'] ?? ''); ?>">
    </div>

    <div class="mt-2">
        <button type="submit" class="btn btn-primary">
            <i class="ph ph-floppy-disk"></i> Guardar Datos
        </button>
    </div>
</form>
