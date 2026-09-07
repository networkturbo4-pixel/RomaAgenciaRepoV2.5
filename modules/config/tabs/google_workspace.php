<?php
$isGoogleConnected = !empty($settings['google_refresh_token']); 
$hasGoogleCredentials = !empty($settings['google_client_id']) && !empty($settings['google_client_secret']);

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$exactRedirectUri = $protocol . "://" . $host . $basePath . "/modules/config/google_oauth_callback.php";
?>

<div class="pane-header">
    <div>
        <h2 class="pane-header-title">
            <i class="ph ph-google-logo" style="color: #ea4335;"></i> Google Workspace (Meet & Gmail)
        </h2>
        <p class="pane-header-desc">Habilita creación de salas en Google Meet y la ingesta automática de resúmenes de grabaciones desde Gmail.</p>
    </div>
    <div>
        <?php if ($isGoogleConnected): ?>
            <span class="integration-status-chip connected">
                <i class="ph ph-check-circle-fill"></i> Workspace Vinculado
            </span>
        <?php else: ?>
            <span class="integration-status-chip disconnected">
                <i class="ph ph-x-circle-fill"></i> Desconectado
            </span>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="index.php?module=config">
    <input type="hidden" name="action_type" value="google_workspace">
    
    <!-- CARD 1: CREDENCIALES -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-key"></i> Credenciales de Google Cloud OAuth</h3>
                <p class="settings-card-desc">Permisos para gestionar el calendario de reuniones y lectura de notas automáticas.</p>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="google_client_id">Client ID (ID de Cliente)</label>
                <div class="input-with-icon">
                    <i class="ph ph-identification-badge"></i>
                    <input type="text" id="google_client_id" name="google_client_id" class="form-control" value="<?php echo htmlspecialchars($settings['google_client_id'] ?? ''); ?>" placeholder="783998453060-f8jl...">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="google_client_secret">Client Secret (Secreto del Cliente)</label>
                <div class="input-with-icon">
                    <i class="ph ph-lock-key"></i>
                    <input type="password" id="google_client_secret" name="google_client_secret" class="form-control" value="<?php echo htmlspecialchars($settings['google_client_secret'] ?? ''); ?>" placeholder="GOCSPX-...">
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-top: 1.25rem; margin-bottom: 0;">
            <label>URI de Redireccionamiento Autorizado</label>
            <div class="code-copy-box">
                <code><?php echo htmlspecialchars($exactRedirectUri); ?></code>
                <button type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($exactRedirectUri); ?>'); this.innerHTML='<i class=\'ph ph-check\'></i> Copiado'; setTimeout(()=>this.innerHTML='<i class=\'ph ph-copy\'></i> Copiar', 1500);" title="Copiar URL">
                    <i class="ph ph-copy"></i> Copiar
                </button>
            </div>
            <small class="text-muted" style="font-size: 11px; display: block; margin-top: 0.35rem;">Copia esta URL en tu consola de Google Cloud para autorizar la redirección.</small>
        </div>

        <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
            <div>
                <span style="font-size: 12px; color: var(--text-muted);">Estado del Token OAuth:</span>
                <strong style="margin-left: 0.35rem; font-size: 12.5px;"><?php echo $isGoogleConnected ? 'Token Activo' : 'Sin vincular'; ?></strong>
            </div>

            <?php if ($hasGoogleCredentials): ?>
                <a href="modules/config/google_oauth_callback.php?action=login" class="btn btn-outline btn-sm" style="border-radius: 8px; border-color: #ea4335; color: #ea4335; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="ph ph-google-logo"></i> <?php echo $isGoogleConnected ? 'Reconectar con Google' : 'Conectar con Google'; ?>
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-outline btn-sm" disabled style="border-radius: 8px;" title="Guarda el Client ID y Client Secret primero">
                    <i class="ph ph-google-logo"></i> Conectar con Google
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- CARD 2: GMAIL & GEMINI SYNC -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-magnifying-glass"></i> Parámetros de Ingesta de Notas (Gmail)</h3>
                <p class="settings-card-desc">Filtra automáticamente los correos que contienen notas de reuniones generadas por Gemini.</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="gemini_subject_keywords">Palabras Clave en el Asunto (separadas por coma)</label>
                <?php $default_keywords = 'Notas, Grabación, Resumen, Notes, Recording, Reunión, Presentación'; ?>
                <div class="input-with-icon">
                    <i class="ph ph-tag"></i>
                    <input type="text" id="gemini_subject_keywords" name="gemini_subject_keywords" class="form-control" value="<?php echo htmlspecialchars(!empty($settings['gemini_subject_keywords']) ? $settings['gemini_subject_keywords'] : $default_keywords); ?>" placeholder="Ej: Notas, Grabación, Resumen">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="gemini_search_days">Antigüedad Máxima (Días)</label>
                <div class="input-with-icon">
                    <i class="ph ph-calendar-blank"></i>
                    <input type="number" id="gemini_search_days" name="gemini_search_days" class="form-control" value="<?php echo htmlspecialchars($settings['gemini_search_days'] ?? '2'); ?>" min="1" max="365">
                </div>
            </div>
        </div>
    </div>

    <!-- ACTION BAR -->
    <div class="settings-action-bar">
        <div class="settings-action-bar-info">
            <i class="ph ph-shield-check"></i>
            <span>Google Workspace sincronizará notas y enlaces de videollamadas con el calendario.</span>
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 10px;">
            <i class="ph ph-floppy-disk"></i> Guardar Credenciales Workspace
        </button>
    </div>
</form>
