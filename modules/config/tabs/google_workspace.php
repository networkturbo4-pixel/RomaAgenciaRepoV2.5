<div class="card-section" style="padding: 1.5rem; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
    <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem; color: #ea4335; display: flex; align-items: center; gap: 0.5rem;">
        <i class="ph ph-google-logo"></i> Integración Google Workspace (Meet & Gmail)
    </h2>
    <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem;">
        Configura las credenciales de Google Cloud para habilitar la creación automática de reuniones en Google Meet y la ingesta de notas de Gemini desde Gmail.
    </p>

    <form method="POST" action="index.php?module=config">
        <input type="hidden" name="action_type" value="google_workspace">
        
        <div style="background: var(--bg-color); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; margin-bottom: 1rem;"><i class="ph ph-key"></i> Credenciales de la API</h3>
            
            <div class="form-group">
                <label>ID de cliente (Client ID)</label>
                <input type="text" name="google_client_id" class="form-control" value="<?php echo htmlspecialchars($settings['google_client_id'] ?? ''); ?>" placeholder="Ej: 783998453060-f8jl...">
            </div>

            <div class="form-group">
                <label>Secreto del cliente (Client Secret)</label>
                <input type="password" name="google_client_secret" class="form-control" value="<?php echo htmlspecialchars($settings['google_client_secret'] ?? ''); ?>" placeholder="GOCSPX-...">
            </div>

            <?php 
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
            $exactRedirectUri = $protocol . "://" . $host . $basePath . "/modules/config/google_oauth_callback.php";
            ?>
            <div style="background: rgba(234, 67, 53, 0.1); border-left: 3px solid #ea4335; padding: 0.75rem; margin-top: 1rem; border-radius: 0 4px 4px 0;">
                <strong style="font-size: 0.8rem; color: #ea4335;">URI de redireccionamiento autorizados (Cópialo a Google Cloud):</strong><br>
                <code style="font-size: 0.75rem; user-select: all; cursor: pointer;"><?php echo htmlspecialchars($exactRedirectUri); ?></code>
            </div>

            <?php 
            $isGoogleConnected = !empty($settings['google_refresh_token']); 
            $hasGoogleCredentials = !empty($settings['google_client_id']) && !empty($settings['google_client_secret']);
            ?>
            
            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <span style="font-size: 0.85rem; font-weight: 600;">Estado de Conexión:</span>
                    <?php if ($isGoogleConnected): ?>
                        <span style="color: #10b981; font-size: 0.85rem; font-weight: 700; background: rgba(16,185,129,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; margin-left: 0.5rem;"><i class="ph ph-check-circle"></i> Conectado (Token Activo)</span>
                    <?php else: ?>
                        <span style="color: #ef4444; font-size: 0.85rem; font-weight: 700; background: rgba(239,68,68,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; margin-left: 0.5rem;"><i class="ph ph-x-circle"></i> Desconectado</span>
                    <?php endif; ?>
                </div>
                
                <?php if ($hasGoogleCredentials): ?>
                    <a href="modules/config/google_oauth_callback.php?action=login" class="btn btn-outline" style="border-color: #ea4335; color: #ea4335;">
                        <i class="ph ph-google-logo"></i> <?php echo $isGoogleConnected ? 'Reconectar con Google' : 'Conectar con Google'; ?>
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline" disabled title="Guarda el Client ID y Client Secret primero"><i class="ph ph-google-logo"></i> Conectar con Google</button>
                <?php endif; ?>
            </div>
        </div>

        <div style="background: var(--bg-color); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; margin-bottom: 1rem;"><i class="ph ph-magnifying-glass"></i> Opciones de Búsqueda de Gemini (Gmail)</h3>
            
            <div class="form-group">
                <label>Palabras clave en el Asunto del correo (separadas por comas)</label>
                <?php $default_keywords = 'Notas, Grabación, Resumen, Notes, Recording, Reunión, Presentación'; ?>
                <input type="text" name="gemini_subject_keywords" class="form-control" value="<?php echo htmlspecialchars(!empty($settings['gemini_subject_keywords']) ? $settings['gemini_subject_keywords'] : $default_keywords); ?>" placeholder='Ej: Notas, Reunión, Presentación'>
                <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Define las palabras clave que el sistema buscará en los asuntos de los correos en Gmail para sincronizar las grabaciones.</small>
            </div>

            <div class="form-group" style="margin-top: 1rem;">
                <label>Antigüedad Máxima de los Correos (Días)</label>
                <input type="number" name="gemini_search_days" class="form-control" value="<?php echo htmlspecialchars($settings['gemini_search_days'] ?? '2'); ?>" min="1" max="365">
                <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Por defecto se buscan correos de los últimos 2 días para optimizar la sincronización.</small>
            </div>
        </div>

        <div style="text-align: right;">
            <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Guardar Credenciales y Configuración</button>
        </div>
    </form>
</div>
