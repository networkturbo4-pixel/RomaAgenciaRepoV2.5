<?php
$isConnected = !empty($settings['drive_refresh_token']); 
$hasCredentials = !empty($settings['drive_client_id']) && !empty($settings['drive_client_secret']);

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$exactRedirectUri = $protocol . "://" . $host . $basePath . "/modules/config/drive_oauth_callback.php";
?>

<div class="pane-header">
    <div>
        <h2 class="pane-header-title">
            <i class="ph ph-google-drive-logo" style="color: #3b82f6;"></i> Integración de Google Drive
        </h2>
        <p class="pane-header-desc">Sincronización en la nube para adjuntos, respaldos automáticos y selector de archivos Google Picker.</p>
    </div>
    <div>
        <?php if ($isConnected): ?>
            <span class="integration-status-chip connected">
                <i class="ph ph-check-circle-fill"></i> Conectado con Google
            </span>
        <?php else: ?>
            <span class="integration-status-chip disconnected">
                <i class="ph ph-x-circle-fill"></i> No Conectado
            </span>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="index.php?module=config">
    <input type="hidden" name="action_type" value="drive">
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem;">
        <!-- Card 1: Frontend Picker Credentials -->
        <div class="settings-card" style="margin-bottom: 0;">
            <div class="settings-card-header">
                <div>
                    <h3 class="settings-card-title"><i class="ph ph-browser"></i> Credenciales Frontend (Picker UI)</h3>
                    <p class="settings-card-desc">Permite a los usuarios seleccionar archivos desde su Drive directamente en las tareas.</p>
                </div>
            </div>
            
            <div class="form-group">
                <label for="drive_api_key">Developer API Key</label>
                <div class="input-with-icon">
                    <i class="ph ph-key"></i>
                    <input type="text" id="drive_api_key" name="drive_api_key" class="form-control" value="<?php echo htmlspecialchars($settings['drive_api_key'] ?? ''); ?>" placeholder="AIzaSyA...">
                </div>
            </div>

            <div class="form-group">
                <label for="drive_client_id">OAuth Client ID</label>
                <div class="input-with-icon">
                    <i class="ph ph-identification-badge"></i>
                    <input type="text" id="drive_client_id" name="drive_client_id" class="form-control" value="<?php echo htmlspecialchars($settings['drive_client_id'] ?? ''); ?>" placeholder="123456789-abc.apps.googleusercontent.com">
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="drive_app_id">App ID <small class="text-muted">(Opcional)</small></label>
                <div class="input-with-icon">
                    <i class="ph ph-app-window"></i>
                    <input type="text" id="drive_app_id" name="drive_app_id" class="form-control" value="<?php echo htmlspecialchars($settings['drive_app_id'] ?? ''); ?>" placeholder="123456789012">
                </div>
            </div>
        </div>

        <!-- Card 2: Backend OAuth Connection -->
        <div class="settings-card" style="margin-bottom: 0;">
            <div class="settings-card-header">
                <div>
                    <h3 class="settings-card-title"><i class="ph ph-server"></i> Conexión Backend (OAuth 2.0)</h3>
                    <p class="settings-card-desc">Para automatizar la subida de respaldos y creación de carpetas en segundo plano.</p>
                </div>
            </div>
            
            <div class="form-group">
                <label for="drive_client_secret">OAuth Client Secret</label>
                <div class="input-with-icon">
                    <i class="ph ph-lock-key"></i>
                    <input type="password" id="drive_client_secret" name="drive_client_secret" class="form-control" value="<?php echo htmlspecialchars($settings['drive_client_secret'] ?? ''); ?>" placeholder="GOCSPX-...">
                </div>
            </div>

            <div class="form-group">
                <label>URI de Redireccionamiento Autorizado</label>
                <div class="code-copy-box">
                    <code><?php echo htmlspecialchars($exactRedirectUri); ?></code>
                    <button type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($exactRedirectUri); ?>'); this.innerHTML='<i class=\'ph ph-check\'></i> Copiado'; setTimeout(()=>this.innerHTML='<i class=\'ph ph-copy\'></i> Copiar', 1500);" title="Copiar URL">
                        <i class="ph ph-copy"></i> Copiar
                    </button>
                </div>
                <small class="text-muted" style="font-size: 11px; display: block; margin-top: 0.35rem;">Pega esta URL en tu Google Cloud Console (Pantalla de consentimiento OAuth).</small>
            </div>

            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <span style="font-size: 12px; color: var(--text-muted);">Estado del Token:</span>
                    <strong style="margin-left: 0.35rem; font-size: 12.5px;"><?php echo $isConnected ? 'Activo' : 'Inactivo'; ?></strong>
                </div>

                <?php if ($hasCredentials): ?>
                    <a href="modules/config/drive_oauth_callback.php?action=login" class="btn btn-outline btn-sm" style="border-radius: 8px; border-color: #3b82f6; color: #3b82f6; display: inline-flex; align-items: center; gap: 0.4rem;">
                        <i class="ph ph-link"></i> <?php echo $isConnected ? 'Reconectar Cuenta' : 'Conectar con Drive'; ?>
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline btn-sm" disabled style="border-radius: 8px;" title="Guarda el Client ID y Client Secret primero">
                        <i class="ph ph-link"></i> Conectar con Drive
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ACTION BAR -->
    <div class="settings-action-bar">
        <div class="settings-action-bar-info">
            <i class="ph ph-info"></i>
            <span>Guarda las credenciales antes de iniciar la vinculación con tu cuenta de Google.</span>
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 10px;">
            <i class="ph ph-floppy-disk"></i> Guardar Credenciales Drive
        </button>
    </div>
</form>
