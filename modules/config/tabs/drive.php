<div class="card-section" style="padding: 1.5rem; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
    <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem; color: #3b82f6; display: flex; align-items: center; gap: 0.5rem;">
        <i class="ph ph-google-drive-logo"></i> Integración de Google Drive
    </h2>
    <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem;">
        Configura las credenciales de Google Cloud para habilitar la subida automática de archivos, creación de carpetas y el selector de interfaz (Google Picker).
    </p>

    <form method="POST" action="index.php?module=config">
        <input type="hidden" name="action_type" value="drive">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            <!-- UI / Picker Credentials -->
            <div style="background: var(--bg-color); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <h3 style="font-size: 1rem; margin-bottom: 1rem;"><i class="ph ph-browser"></i> Credenciales Frontend (Picker UI)</h3>
                
                <div class="form-group">
                    <label>Developer API Key</label>
                    <input type="text" name="drive_api_key" class="form-control" value="<?php echo htmlspecialchars($settings['drive_api_key'] ?? ''); ?>" placeholder="AIzaSyA...">
                    <small class="text-muted" style="display:block; margin-top:0.25rem;">Requerida para que el Picker funcione.</small>
                </div>

                <div class="form-group">
                    <label>OAuth Client ID</label>
                    <input type="text" name="drive_client_id" class="form-control" value="<?php echo htmlspecialchars($settings['drive_client_id'] ?? ''); ?>" placeholder="123456789-abc.apps.googleusercontent.com">
                    <small class="text-muted" style="display:block; margin-top:0.25rem;">Para identificar tu aplicación frente a los usuarios.</small>
                </div>
                
                <div class="form-group">
                    <label>App ID (Opcional)</label>
                    <input type="text" name="drive_app_id" class="form-control" value="<?php echo htmlspecialchars($settings['drive_app_id'] ?? ''); ?>" placeholder="123456789012">
                </div>
            </div>

            <!-- Backend / OAuth 2.0 Credentials -->
            <div style="background: var(--bg-color); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <h3 style="font-size: 1rem; margin-bottom: 1rem;"><i class="ph ph-server"></i> Conexión Backend (OAuth 2.0)</h3>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">Para automatizar la subida de recursos y creación de carpetas, conecta tu cuenta de Google.</p>
                
                <div class="form-group">
                    <label>OAuth Client Secret</label>
                    <input type="password" name="drive_client_secret" class="form-control" value="<?php echo htmlspecialchars($settings['drive_client_secret'] ?? ''); ?>" placeholder="GOCSPX-...">
                    <small class="text-muted" style="display:block; margin-top:0.25rem;">Lo obtienes junto con tu Client ID.</small>
                </div>

                <?php 
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
                $exactRedirectUri = $protocol . "://" . $host . $basePath . "/modules/config/drive_oauth_callback.php";
                ?>
                <div style="background: rgba(59, 130, 246, 0.1); border-left: 3px solid #3b82f6; padding: 0.75rem; margin-top: 1rem; border-radius: 0 4px 4px 0;">
                    <strong style="font-size: 0.8rem; color: #3b82f6;">URI de redireccionamiento autorizados:</strong><br>
                    <code style="font-size: 0.75rem; user-select: all; cursor: pointer;"><?php echo htmlspecialchars($exactRedirectUri); ?></code>
                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">Copia esta URL exacta y pégala en tu Google Cloud Console para evitar el error "redirect_uri_mismatch".</div>
                </div>

                <?php 
                $isConnected = !empty($settings['drive_refresh_token']); 
                $hasCredentials = !empty($settings['drive_client_id']) && !empty($settings['drive_client_secret']);
                ?>
                
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <span style="font-size: 0.85rem; font-weight: 600;">Estado:</span>
                        <?php if ($isConnected): ?>
                            <span style="color: #10b981; font-size: 0.85rem; font-weight: 700; background: rgba(16,185,129,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; margin-left: 0.5rem;"><i class="ph ph-check-circle"></i> Conectado</span>
                        <?php else: ?>
                            <span style="color: #ef4444; font-size: 0.85rem; font-weight: 700; background: rgba(239,68,68,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; margin-left: 0.5rem;"><i class="ph ph-x-circle"></i> Desconectado</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($hasCredentials): ?>
                        <a href="modules/config/drive_oauth_callback.php?action=login" class="btn btn-outline" style="border-color: #3b82f6; color: #3b82f6;">
                            <i class="ph ph-link"></i> <?php echo $isConnected ? 'Reconectar' : 'Conectar con Drive'; ?>
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline" disabled title="Guarda el Client ID y Client Secret primero"><i class="ph ph-link"></i> Conectar con Drive</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="text-align: right;">
            <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Guardar Credenciales</button>
        </div>
    </form>
</div>
