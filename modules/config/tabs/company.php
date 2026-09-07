<div class="pane-header">
    <div>
        <h2 class="pane-header-title">
            <i class="ph ph-buildings"></i> Datos de la Empresa
        </h2>
        <p class="pane-header-desc">Administra los datos comerciales, identificación tributaria y canales oficiales de comunicación con clientes.</p>
    </div>
</div>

<form action="index.php?module=config&action=index" method="POST">
    <input type="hidden" name="action_type" value="company">
    
    <!-- CARD 1: DATOS FISCALES Y COMERCIALES -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-identification-card"></i> Identificación Fiscal y Comercial</h3>
                <p class="settings-card-desc">Datos oficiales emitidos en cotizaciones, contratos y documentos legales.</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="company_trade_name">Nombre Comercial</label>
                <div class="input-with-icon">
                    <i class="ph ph-storefront"></i>
                    <input type="text" id="company_trade_name" name="company_trade_name" class="form-control" value="<?php echo htmlspecialchars($settings['company_trade_name'] ?? ''); ?>" required placeholder="Ej. ROMA Agencia Digital">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="company_legal_name">Razón Social</label>
                <div class="input-with-icon">
                    <i class="ph ph-buildings"></i>
                    <input type="text" id="company_legal_name" name="company_legal_name" class="form-control" value="<?php echo htmlspecialchars($settings['company_legal_name'] ?? ''); ?>" required placeholder="Ej. ROMA SOLUCIONES INTEGRALES S.A.C.">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="company_ruc">RUC / Identificación Fiscal</label>
                <div class="input-with-icon">
                    <i class="ph ph-barcode"></i>
                    <input type="text" id="company_ruc" name="company_ruc" class="form-control" value="<?php echo htmlspecialchars($settings['company_ruc'] ?? ''); ?>" required placeholder="Ej. 20601234567">
                </div>
            </div>
        </div>
    </div>

    <!-- CARD 2: CONTACTO Y UBICACIÓN -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-phone-call"></i> Contacto y Ubicación Física</h3>
                <p class="settings-card-desc">Canales donde tus clientes o proveedores pueden contactar a la administración.</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="company_email">Correo Electrónico Oficial</label>
                <div class="input-with-icon">
                    <i class="ph ph-envelope-simple"></i>
                    <input type="email" id="company_email" name="company_email" class="form-control" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" placeholder="contacto@tuempresa.com">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="company_whatsapp">WhatsApp Corporativo</label>
                <div class="input-with-icon">
                    <i class="ph ph-whatsapp-logo" style="color: #25D366;"></i>
                    <input type="text" id="company_whatsapp" name="company_whatsapp" class="form-control" value="<?php echo htmlspecialchars($settings['company_whatsapp'] ?? ''); ?>" placeholder="+51 987 654 321">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0; grid-column: 1 / -1;">
                <label for="company_address">Dirección Fiscal / Sede Principal</label>
                <div class="input-with-icon">
                    <i class="ph ph-map-pin"></i>
                    <input type="text" id="company_address" name="company_address" class="form-control" value="<?php echo htmlspecialchars($settings['company_address'] ?? ''); ?>" placeholder="Av. Principal 123, Of. 402 - Lima, Perú">
                </div>
            </div>
        </div>
    </div>

    <!-- CARD 3: REDES SOCIALES -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-share-network"></i> Presencia Digital y Redes Sociales</h3>
                <p class="settings-card-desc">Enlaces a tus canales digitales vinculados en el portal de clientes y firmas de correo.</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
            <div class="social-card">
                <i class="ph ph-facebook-logo" style="color: #1877F2;"></i>
                <input type="url" name="social_facebook" placeholder="https://facebook.com/tuempresa" value="<?php echo htmlspecialchars($settings['social_facebook'] ?? ''); ?>">
            </div>
            
            <div class="social-card">
                <i class="ph ph-instagram-logo" style="color: #E4405F;"></i>
                <input type="url" name="social_instagram" placeholder="https://instagram.com/tuempresa" value="<?php echo htmlspecialchars($settings['social_instagram'] ?? ''); ?>">
            </div>
            
            <div class="social-card">
                <i class="ph ph-linkedin-logo" style="color: #0A66C2;"></i>
                <input type="url" name="social_linkedin" placeholder="https://linkedin.com/company/tuempresa" value="<?php echo htmlspecialchars($settings['social_linkedin'] ?? ''); ?>">
            </div>
        </div>
    </div>

    <!-- ACTION BAR -->
    <div class="settings-action-bar">
        <div class="settings-action-bar-info">
            <i class="ph ph-shield-check"></i>
            <span>Los datos son utilizados para generar cotizaciones y contratos con validez legal.</span>
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 10px;">
            <i class="ph ph-floppy-disk"></i> Guardar Datos de la Empresa
        </button>
    </div>
</form>
