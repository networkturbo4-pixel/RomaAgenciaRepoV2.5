<?php
// modules/workspace/vectorcraft.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/GoogleDriveHelper.php';

$database = new Database();
$db = $database->getConnection();

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$is_standalone_editor = isset($_GET['editor']) && $_GET['editor'] == '1';

// Función para obtener URL pública del proyecto
function getVectorcraftShareUrl($token) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $protocol = $is_https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $rootDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
    if ($rootDir === '' || $rootDir === '.') $rootDir = '';
    return "{$protocol}{$host}{$rootDir}/public_vectorcraft.php?token={$token}";
}

// ==============================================================================
// MODO 1: EDITOR VECTORIAL DIRECTO O PROYECTO SELECCIONADO
// ==============================================================================
if ($edit_id > 0 || $is_standalone_editor) {
    $current_project = null;
    if ($edit_id > 0) {
        $stmt = $db->prepare("
            SELECT p.*, u.name as creator_name 
            FROM vectorcraft_projects p 
            LEFT JOIN users u ON p.created_by = u.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$edit_id]);
        $current_project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current_project && !$is_standalone_editor) {
            header("Location: index.php?module=workspace&action=vectorcraft");
            exit();
        }

        // Si no tiene share token, generarlo
        if ($current_project && empty($current_project['share_token'])) {
            $share_token = bin2hex(random_bytes(16));
            $db->prepare("UPDATE vectorcraft_projects SET share_token = ? WHERE id = ?")->execute([$share_token, $edit_id]);
            $current_project['share_token'] = $share_token;
        }
    }

    $is_popup = !empty($_GET['popup']) && $_GET['popup'] == '1';
    $project_title = $current_project ? htmlspecialchars($current_project['title']) : "Lienzo Libre";
    $page_title = $project_title . " — VectorCraft Editor";
    $share_url = $current_project ? getVectorcraftShareUrl($current_project['share_token']) : '';
    $drive_folder_name = $current_project ? htmlspecialchars($current_project['drive_folder_name'] ?? 'Google Drive') : '';
    $drive_folder_id = $current_project ? htmlspecialchars($current_project['drive_folder_id'] ?? '') : '';
    $drive_web_link = $current_project ? htmlspecialchars($current_project['drive_web_link'] ?? '') : '';
    $has_saved_file = $current_project && !empty($current_project['file_path']) && file_exists(__DIR__ . '/../../' . $current_project['file_path']);
    $saved_file_rel = $current_project ? htmlspecialchars($current_project['file_path'] ?? '') : '';

    require_once 'includes/header.php';
    require_once 'includes/custom_drive_picker.php';

    $use_webgl = isset($_GET['webgl']);
    $iframe_src = "assets/apps/vectorcraft/index.html" . ($use_webgl ? "?webgl" : "");
?>

<style>
/* ==========================================================================
   VECTORCRAFT EDITOR STYLES
   ========================================================================== */
.vc-container {
    padding: <?php echo $is_popup ? '0.75rem 1rem' : '1.25rem 2rem 2rem 2rem'; ?>;
    max-width: 100%;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    height: <?php echo $is_popup ? '100vh' : 'auto'; ?>;
    box-sizing: border-box;
}

.vc-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: <?php echo $is_popup ? '0.75rem' : '1.25rem'; ?>;
}

.vc-title-area {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.vc-app-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #a855f7 0%, #7c3aed 50%, #4338ca 100%);
    box-shadow: 0 6px 16px -2px rgba(124, 58, 237, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: #ffffff;
    flex-shrink: 0;
}

.vc-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.vc-title-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.vc-title {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 800;
    letter-spacing: -0.4px;
    color: var(--text-main, #0f172a);
    line-height: 1.2;
}

[data-theme="dark"] .vc-title {
    color: #ffffff;
}

.vc-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.18rem 0.55rem;
    border-radius: 9999px;
    background: color-mix(in srgb, #a855f7 15%, transparent);
    color: #7c3aed;
    border: 1px solid color-mix(in srgb, #a855f7 25%, transparent);
}

.vc-badge-drive {
    background: color-mix(in srgb, #3b82f6 15%, transparent);
    color: #2563eb;
    border-color: color-mix(in srgb, #3b82f6 30%, transparent);
    text-decoration: none;
    transition: all 0.2s;
}

.vc-badge-drive:hover {
    background: color-mix(in srgb, #3b82f6 25%, transparent);
}

[data-theme="dark"] .vc-badge-drive {
    color: #60a5fa;
}

.vc-subtitle {
    margin: 0;
    font-size: 0.82rem;
    color: var(--text-muted, #64748b);
    font-weight: 500;
}

.vc-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.vc-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.85rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-surface, #ffffff);
    color: var(--text-main, #0f172a);
}

.vc-btn:hover {
    background: var(--bg-body, #f1f5f9);
    border-color: color-mix(in srgb, #7c3aed 40%, transparent);
    color: #7c3aed;
}

.vc-btn-primary {
    background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%);
    color: #ffffff !important;
    border: none;
    box-shadow: 0 4px 12px -2px rgba(124, 58, 237, 0.35);
}

.vc-btn-primary:hover {
    background: linear-gradient(135deg, #9333ea 0%, #7c3aed 100%);
    box-shadow: 0 6px 16px -2px rgba(124, 58, 237, 0.45);
    transform: translateY(-1px);
}

.vc-btn-drive {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff !important;
    border: none;
    box-shadow: 0 4px 12px -2px rgba(37, 99, 235, 0.35);
}

.vc-btn-drive:hover {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    box-shadow: 0 6px 16px -2px rgba(37, 99, 235, 0.45);
    transform: translateY(-1px);
}

.vc-btn-share {
    background: rgba(124, 58, 237, 0.12);
    color: #7c3aed;
    border-color: rgba(124, 58, 237, 0.25);
}

.vc-btn-share:hover {
    background: rgba(124, 58, 237, 0.2);
    color: #6d28d9;
}

[data-theme="dark"] .vc-btn-share {
    color: #c084fc;
}

/* Frame Wrapper */
.vc-frame-wrapper {
    position: relative;
    width: 100%;
    flex: 1;
    min-height: <?php echo $is_popup ? 'calc(100vh - 75px)' : '680px'; ?>;
    height: <?php echo $is_popup ? 'calc(100vh - 75px)' : 'calc(100vh - 210px)'; ?>;
    border-radius: var(--radius-lg, 14px);
    overflow: hidden;
    background: #262626;
    border: 1px solid var(--border-color, #e2e8f0);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
}

[data-theme="dark"] .vc-frame-wrapper {
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
}

.vc-iframe {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    background: #262626;
}

/* Floating Save Toast */
.vc-sync-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #18181b;
    color: #ffffff;
    border: 1px solid #7c3aed;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    padding: 0.85rem 1.25rem;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    display: none;
    align-items: center;
    gap: 0.6rem;
    z-index: 100000;
    animation: vcToastSlide 0.3s ease-out;
}

@keyframes vcToastSlide {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

.vc-tips-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 8px;
    padding: 0.5rem 0.85rem;
    font-size: 0.78rem;
    color: var(--text-muted, #64748b);
    margin-top: 0.75rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.vc-tips-bar strong {
    color: var(--text-main, #0f172a);
}

@media (max-width: 768px) {
    .vc-container {
        padding: 0.75rem;
    }
    .vc-frame-wrapper {
        min-height: 520px;
        height: calc(100vh - 260px);
    }
    .vc-subtitle {
        display: none;
    }
}
</style>

<div class="vc-container">
    <!-- Header / Toolbar -->
    <div class="vc-header">
        <div class="vc-title-area">
            <a href="index.php?module=workspace&action=vectorcraft" class="vc-btn" title="Volver al Catálogo">
                <i class="ph-bold ph-arrow-left"></i>
                <span class="d-none d-sm-inline">Ilustraciones</span>
            </a>
            <div class="vc-app-icon" title="VectorCraft">
                <i class="ph-bold ph-bezier-curve"></i>
            </div>
            <div class="vc-meta">
                <div class="vc-title-row">
                    <h1 class="vc-title"><?php echo $project_title; ?></h1>
                    <?php if ($current_project && !empty($drive_folder_id)): ?>
                    <a href="https://drive.google.com/drive/folders/<?php echo $drive_folder_id; ?>" target="_blank" class="vc-badge vc-badge-drive" title="Abrir carpeta en Google Drive">
                        <i class="ph-bold ph-google-drive-logo"></i> <?php echo $drive_folder_name ?: 'Google Drive'; ?>
                    </a>
                    <?php endif; ?>
                    <span class="vc-badge"><i class="ph-bold ph-cloud-check"></i> Sincronizado en Drive</span>
                </div>
                <p class="vc-subtitle">
                    Haz clic en <strong>Guardar en Drive</strong> en cualquier momento para respaldar tu diseño en tu carpeta de Google Drive.
                </p>
            </div>
        </div>

        <div class="vc-actions">
            <?php if ($edit_id > 0): ?>
            <!-- Guardar en Google Drive DIRECTO (Sin modals molestos) -->
            <button type="button" class="vc-btn vc-btn-drive" id="btnSaveDriveTop" onclick="saveToDriveDirectly()" title="Guardar ilustración inmediatamente en Google Drive">
                <i class="ph-bold ph-cloud-arrow-up"></i>
                <span id="btnSaveDriveText">Guardar en Drive</span>
            </button>

            <!-- Compartir Enlace Público -->
            <button type="button" class="vc-btn vc-btn-share" onclick="openShareModal()" title="Abrir opciones de enlace público">
                <i class="ph-bold ph-share-network"></i>
                <span>Compartir</span>
            </button>

            <!-- Restaurar en lienzo -->
            <?php if ($has_saved_file): ?>
            <button type="button" class="vc-btn" onclick="restoreSavedIllustration()" title="Restablecer el último archivo guardado en el lienzo">
                <i class="ph-bold ph-file-arrow-down"></i>
                <span class="d-none d-lg-inline">Cargar Guardado</span>
            </button>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Toggle Fullscreen API -->
            <button type="button" class="vc-btn" onclick="toggleFullscreen()" title="Pantalla completa en el navegador">
                <i class="ph-bold ph-arrows-out" id="fullscreenIcon"></i>
                <span class="d-none d-sm-inline">Pantalla Completa</span>
            </button>

            <?php if (!$is_popup): ?>
            <!-- Modo Lienzo sin barra lateral -->
            <a href="index.php?module=workspace&action=vectorcraft&<?php echo $edit_id ? "edit={$edit_id}&" : "editor=1&"; ?>popup=1<?php echo $use_webgl ? '&webgl=1' : ''; ?>" class="vc-btn" title="Modo Lienzo sin barra lateral">
                <i class="ph-bold ph-corners-out"></i>
                <span>Modo Lienzo</span>
            </a>
            <?php else: ?>
            <!-- Volver a Modo Normal -->
            <a href="index.php?module=workspace&action=vectorcraft&<?php echo $edit_id ? "edit={$edit_id}&" : "editor=1&"; ?><?php echo $use_webgl ? '&webgl=1' : ''; ?>" class="vc-btn" title="Volver a la vista con barra lateral">
                <i class="ph-bold ph-sidebar"></i>
                <span>Modo Normal</span>
            </a>
            <?php endif; ?>

            <!-- Abrir en Pestaña Nueva -->
            <a href="<?php echo $iframe_src; ?>" target="_blank" class="vc-btn vc-btn-primary" title="Abrir VectorCraft en una pestaña nueva">
                <i class="ph-bold ph-arrow-square-out"></i>
                <span>Pestaña Nueva</span>
            </a>
        </div>
    </div>

    <!-- Iframe Container -->
    <div class="vc-frame-wrapper" id="vcFrameWrapper">
        <iframe 
            id="vectorcraftIframe" 
            class="vc-iframe" 
            src="<?php echo $iframe_src; ?>" 
            title="VectorCraft Vector Illustration"
            allow="fullscreen; clipboard-read; clipboard-write" 
            allowfullscreen>
        </iframe>
    </div>

    <?php if (!$is_popup): ?>
    <!-- Bottom Help & Tips Bar -->
    <div class="vc-tips-bar">
        <div>
            <i class="ph-bold ph-google-drive-logo" style="color: #2563eb; margin-right: 4px;"></i>
            <strong>Sincronización con Drive:</strong> Todo archivo guardado se almacena en <code><?php echo $drive_folder_name ?: 'tu carpeta seleccionada'; ?></code> y genera un enlace público listo para compartir.
        </div>
        <div>
            <i class="ph-bold ph-keyboard" style="margin-right: 4px;"></i>
            <strong>Atajos:</strong> Haz clic en el lienzo: <strong>P</strong> (Pluma), <strong>V</strong> (Selección), <strong>A</strong> (Selección directa), <strong>Ctrl+Z</strong> (Deshacer).
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- FLOATING TOAST -->
<div id="vcSyncToast" class="vc-sync-toast">
    <i class="ph-bold ph-cloud-check" id="vcSyncToastIcon" style="color: #10b981; font-size: 20px;"></i>
    <span id="vcSyncToastMsg">Sincronizado con Google Drive</span>
</div>

<!-- MODAL: COMPARTIR ILUSTRACIÓN -->
<div class="modal-overlay" id="modalShare" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 100400; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: var(--bg-surface, #ffffff); border-radius: 16px; width: 100%; max-width: 520px; box-shadow: 0 20px 45px rgba(0,0,0,0.25); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main, #0f172a);">
                <i class="ph-bold ph-share-network" style="color: #7c3aed;"></i> Compartir Ilustración
            </h3>
            <button type="button" class="btn-icon" onclick="closeModal('modalShare')" style="background:none; border:none; font-size:1.25rem; cursor:pointer;"><i class="ph-bold ph-x"></i></button>
        </div>
        <div style="padding: 1.5rem;">
            <p style="font-size: 0.88rem; color: var(--text-muted, #64748b); margin-top: 0; margin-bottom: 1.25rem; line-height: 1.45;">
                Cualquier persona con este enlace podrá ver la ilustración en alta resolución y descargar el archivo original, sin necesidad de cuenta.
            </p>

            <label style="display: block; font-size: 0.82rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--text-main, #0f172a);">
                Enlace Público
            </label>
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem;">
                <input type="text" id="modalShareInput" readonly style="flex: 1; padding: 0.65rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color, #cbd5e1); font-size: 0.85rem; background: var(--bg-body, #f8fafc); color: var(--text-main, #0f172a);" value="<?php echo $share_url; ?>">
                <button type="button" class="btn btn-primary" onclick="copyFromModalShare()" style="background: #7c3aed; border: none; padding: 0 1.2rem; font-weight: 700; border-radius: 8px; color: #fff; cursor: pointer;">
                    <i class="ph-bold ph-copy"></i> Copiar
                </button>
            </div>

            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <a href="<?php echo $share_url; ?>" target="_blank" class="vc-btn" style="flex: 1; justify-content: center;">
                    <i class="ph-bold ph-arrow-square-out"></i> Abrir Enlace Público
                </a>
                <?php if (!empty($drive_web_link)): ?>
                <a href="<?php echo $drive_web_link; ?>" target="_blank" class="vc-btn" style="flex: 1; justify-content: center; color: #2563eb;">
                    <i class="ph-bold ph-google-drive-logo"></i> Ver en Drive
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const projectId = <?php echo (int)$edit_id; ?>;
const iframe = document.getElementById('vectorcraftIframe');
const wrapper = document.getElementById('vcFrameWrapper');
let lastSavedFileUrl = '<?php echo $saved_file_rel; ?>';
let autoLoadAttempted = false;

// Escuchar mensajes del iframe (exportaciones automáticas de VectorCraft)
window.addEventListener('message', function(event) {
    if (!event.data) return;

    if (event.data.type === 'VECTORCRAFT_FILE_EXPORTED') {
        const fileName = event.data.fileName || 'illustration.svg';
        const blobUrl = event.data.blobUrl;
        if (blobUrl) {
            syncBlobToGoogleDrive(blobUrl, fileName);
        }
    }
});

iframe.addEventListener('load', () => {
    try {
        const cw = iframe.contentWindow;
        if (!cw) return;

        // Escuchar inicio de Wasm para restaurar el diseño previo
        cw.addEventListener('TrunkApplicationStarted', () => {
            setTimeout(restoreSavedIllustration, 1000);
        });

        setTimeout(() => {
            if (!autoLoadAttempted) restoreSavedIllustration();
        }, 2500);

    } catch (e) {
        console.warn('Sandbox o restricción de iframe:', e);
    }
});

// Guardado DIRECTO a Google Drive con un solo clic
async function saveToDriveDirectly() {
    if (!projectId) return;

    const btn = document.getElementById('btnSaveDriveTop');
    const textSpan = document.getElementById('btnSaveDriveText');
    const originalHtml = textSpan.innerHTML;

    btn.disabled = true;
    textSpan.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Guardando...';
    showSyncToast('☁️ Guardando diseño y subiendo a Google Drive...', 'loading');

    const cw = iframe.contentWindow;
    if (cw && typeof cw.__vcCaptureCanvasBlob === 'function') {
        cw.__vcCaptureCanvasBlob(async function(blob, fileName) {
            if (!blob) {
                // Captura fallback sobre el canvas directamente
                const cd = iframe.contentDocument;
                const canvas = cd ? cd.getElementById('vectorcraft_canvas') : null;
                if (canvas) {
                    canvas.toBlob(async function(fallbackBlob) {
                        await uploadBlobToDrive(fallbackBlob, 'illustration.png');
                    }, 'image/png');
                } else {
                    finalizeButton(false, 'No se pudo acceder al lienzo');
                }
                return;
            }
            await uploadBlobToDrive(blob, fileName || 'illustration.png');
        });
    } else {
        // Fallback vía canvas toBlob
        try {
            const cd = iframe.contentDocument;
            const canvas = cd ? cd.getElementById('vectorcraft_canvas') : null;
            if (canvas) {
                canvas.toBlob(async function(canvasBlob) {
                    await uploadBlobToDrive(canvasBlob, 'illustration.png');
                }, 'image/png');
            } else {
                finalizeButton(false, 'No se pudo capturar el lienzo');
            }
        } catch(err) {
            finalizeButton(false, err.message);
        }
    }

    async function uploadBlobToDrive(blob, fileName) {
        try {
            const formData = new FormData();
            formData.append('action', 'save_to_drive');
            formData.append('id', projectId);
            formData.append('file', blob, fileName);

            const res = await fetch('ajax/ajax_vectorcraft.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                lastSavedFileUrl = data.file_path;
                finalizeButton(true);
                showSyncToast(`✅ ¡Guardado en Google Drive en "${data.drive_folder_name || 'tu carpeta'}"!`, 'success');
                
                if (document.getElementById('modalShareInput') && data.share_url) {
                    document.getElementById('modalShareInput').value = data.share_url;
                }
            } else {
                finalizeButton(false, data.message);
            }
        } catch(err) {
            finalizeButton(false, 'Error de conexión');
        }
    }

    function finalizeButton(success, errMsg = '') {
        btn.disabled = false;
        if (success) {
            textSpan.innerHTML = '<i class="ph-bold ph-check"></i> ¡Guardado!';
            setTimeout(() => { textSpan.innerHTML = originalHtml; }, 3000);
        } else {
            textSpan.innerHTML = originalHtml;
            showSyncToast('❌ Error: ' + (errMsg || 'Fallo al guardar'), 'error');
            alert(errMsg || 'Error al guardar en Google Drive');
        }
    }
}

// Sincroniza un Blob URL con Google Drive
async function syncBlobToGoogleDrive(blobUrl, fileName) {
    if (!projectId) return;

    showSyncToast('☁️ Sincronizando con Google Drive...', 'loading');

    try {
        const resp = await fetch(blobUrl);
        const blob = await resp.blob();

        const formData = new FormData();
        formData.append('action', 'save_to_drive');
        formData.append('id', projectId);
        formData.append('file', blob, fileName);

        const res = await fetch('ajax/ajax_vectorcraft.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            lastSavedFileUrl = data.file_path;
            showSyncToast(`✅ Guardado en Drive: ${data.drive_folder_name || 'Carpeta'}`, 'success');
        } else {
            showSyncToast(`⚠️ ${data.message || 'Error al sincronizar con Drive'}`, 'error');
        }
    } catch (err) {
        console.error('Error sincronizando con Google Drive:', err);
        showSyncToast('⚠️ No se pudo sincronizar automáticamente con Drive', 'error');
    }
}

// Carga el archivo guardado en VectorCraft mediante synthetic drag & drop
async function restoreSavedIllustration() {
    if (!projectId || !lastSavedFileUrl) return;
    autoLoadAttempted = true;

    try {
        const cw = iframe.contentWindow;
        const cd = iframe.contentDocument;
        if (!cw || !cd) return;

        const canvas = cd.getElementById('vectorcraft_canvas');
        if (!canvas) return;

        const response = await fetch(lastSavedFileUrl);
        if (!response.ok) return;

        const blob = await response.blob();
        const fileName = lastSavedFileUrl.split('/').pop();
        const ext = fileName.split('.').pop().toLowerCase();
        const mimeType = ext === 'svg' ? 'image/svg+xml' : 'application/octet-stream';

        const file = new File([blob], fileName, { type: mimeType });
        const dt = new DataTransfer();
        dt.items.add(file);

        const dragEventInit = {
            bubbles: true,
            cancelable: true,
            composed: true,
            dataTransfer: dt,
            clientX: cw.innerWidth / 2,
            clientY: cw.innerHeight / 2
        };

        canvas.dispatchEvent(new DragEvent('dragenter', dragEventInit));
        canvas.dispatchEvent(new DragEvent('dragover', dragEventInit));
        canvas.dispatchEvent(new DragEvent('drop', dragEventInit));

        showSyncToast('📂 Diseño anterior restaurado en el lienzo', 'success');
    } catch (e) {
        console.log('Restauración de lienzo:', e);
    }
}

// Modal de compartir robusto
function openShareModal() {
    document.getElementById('modalShare').style.display = 'flex';
}

function copyFromModalShare() {
    const input = document.getElementById('modalShareInput');
    const text = input.value;
    
    // Método universal de copiado con fallback
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showSyncToast('📋 ¡Enlace público copiado al portapapeles!', 'success');
            alert('📋 ¡Enlace público copiado al portapapeles!\n\n' + text);
        }).catch(() => {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
        showSyncToast('📋 ¡Enlace público copiado al portapapeles!', 'success');
        alert('📋 ¡Enlace público copiado al portapapeles!\n\n' + text);
    } catch (err) {
        prompt('Copia manualmente el siguiente enlace:', text);
    }
    document.body.removeChild(textArea);
}

function showSyncToast(msg, type = 'info') {
    const toast = document.getElementById('vcSyncToast');
    const msgEl = document.getElementById('vcSyncToastMsg');
    const iconEl = document.getElementById('vcSyncToastIcon');
    
    msgEl.textContent = msg;
    if (type === 'loading') {
        iconEl.className = 'ph-bold ph-spinner ph-spin';
        iconEl.style.color = '#3b82f6';
    } else if (type === 'error') {
        iconEl.className = 'ph-bold ph-warning-circle';
        iconEl.style.color = '#ef4444';
    } else {
        iconEl.className = 'ph-bold ph-cloud-check';
        iconEl.style.color = '#10b981';
    }

    toast.style.display = 'flex';
    setTimeout(() => {
        toast.style.display = 'none';
    }, 4500);
}

function toggleFullscreen() {
    const icon = document.getElementById('fullscreenIcon');
    if (!document.fullscreenElement) {
        if (wrapper.requestFullscreen) wrapper.requestFullscreen();
        else if (wrapper.webkitRequestFullscreen) wrapper.webkitRequestFullscreen();
        else if (wrapper.msRequestFullscreen) wrapper.msRequestFullscreen();
        if (icon) {
            icon.classList.remove('ph-arrows-out');
            icon.classList.add('ph-arrows-in');
        }
    } else {
        if (document.exitFullscreen) document.exitFullscreen();
        if (icon) {
            icon.classList.remove('ph-arrows-in');
            icon.classList.add('ph-arrows-out');
        }
    }
}
</script>

<?php 
    require_once 'includes/footer.php'; 
    exit();
}

// ==============================================================================
// MODO 2: DASHBOARD / CATÁLOGO DE ILUSTRACIONES VECTORCRAFT
// ==============================================================================

$page_title = "Ilustración Vectorial (VectorCraft) — Workspace";
require_once 'includes/header.php';
require_once 'includes/custom_drive_picker.php';

// Obtener todas las ilustraciones de la base de datos
$search = trim($_GET['q'] ?? '');
$params = [];
$whereClause = "";

if (!empty($search)) {
    $whereClause = "WHERE p.title LIKE ? OR p.description LIKE ? OR p.drive_folder_name LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$sql = "
    SELECT p.*, u.name as creator_name, u.avatar as creator_avatar 
    FROM vectorcraft_projects p 
    LEFT JOIN users u ON p.created_by = u.id 
    $whereClause
    ORDER BY p.updated_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_projects = count($projects);
?>

<style>
/* ==========================================================================
   DASHBOARD DE ILUSTRACIONES VECTORCRAFT
   ========================================================================== */

.vc-dash-container {
    padding: 2rem 2.25rem;
    max-width: 1400px;
    margin: 0 auto;
    animation: vcFadeIn 0.3s ease-out;
}

@keyframes vcFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.vc-dash-header {
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.vc-dash-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.74rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #7c3aed;
}

.vc-dash-title-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    margin-top: 0.35rem;
}

.vc-dash-title {
    margin: 0;
    font-size: 2.1rem;
    font-weight: 900;
    letter-spacing: -0.8px;
    color: var(--text-main, #0f172a);
    line-height: 1.15;
}

[data-theme="dark"] .vc-dash-title {
    color: #ffffff;
}

.vc-count-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: color-mix(in srgb, #7c3aed 12%, transparent);
    color: #7c3aed;
    border: 1px solid color-mix(in srgb, #7c3aed 25%, transparent);
    font-size: 0.75rem;
    font-weight: 800;
    padding: 0.22rem 0.65rem;
    border-radius: 9999px;
}

.vc-dash-desc {
    margin: 0.35rem 0 0 0;
    color: var(--text-muted, #64748b);
    font-size: 0.95rem;
}

.vc-dash-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.vc-search-box {
    position: relative;
    display: flex;
    align-items: center;
    width: 260px;
}

.vc-search-box input {
    width: 100%;
    padding: 0.55rem 0.85rem 0.55rem 2.25rem;
    border-radius: 10px;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-surface, #ffffff);
    color: var(--text-main, #0f172a);
    font-size: 0.86rem;
    outline: none;
    transition: all 0.2s;
}

.vc-search-box input:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
}

.vc-search-icon {
    position: absolute;
    left: 0.75rem;
    color: var(--text-muted, #94a3b8);
    font-size: 1rem;
    pointer-events: none;
}

.vc-main-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.58rem 1.15rem;
    border-radius: 10px;
    font-size: 0.88rem;
    font-weight: 700;
    color: #ffffff;
    background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%);
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px -2px rgba(124, 58, 237, 0.4);
    transition: all 0.2s;
    text-decoration: none;
}

.vc-main-btn:hover {
    background: linear-gradient(135deg, #9333ea 0%, #7c3aed 100%);
    box-shadow: 0 6px 18px -2px rgba(124, 58, 237, 0.5);
    transform: translateY(-1px);
    color: #ffffff;
}

.vc-secondary-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.58rem 1rem;
    border-radius: 10px;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text-main, #0f172a);
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}

.vc-secondary-btn:hover {
    background: var(--bg-body, #f8fafc);
    border-color: #7c3aed;
    color: #7c3aed;
}

/* Grilla de Ilustraciones */
.vc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.vc-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.vc-card:hover {
    transform: translateY(-4px);
    border-color: rgba(124, 58, 237, 0.35);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .vc-card {
    background: #17181c;
    border-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .vc-card:hover {
    border-color: rgba(124, 58, 237, 0.5);
    box-shadow: 0 14px 32px rgba(0, 0, 0, 0.4);
}

/* Thumbnail / Cover de la ilustración */
.vc-card-cover {
    height: 160px;
    background: linear-gradient(135deg, #18181b 0%, #09090b 100%);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    cursor: pointer;
}

.vc-cover-pattern {
    position: absolute;
    inset: 0;
    opacity: 0.18;
    background-image: radial-gradient(#a855f7 1px, transparent 1px);
    background-size: 16px 16px;
}

.vc-cover-icon {
    font-size: 54px;
    color: #a855f7;
    opacity: 0.9;
    transition: transform 0.3s;
}

.vc-card:hover .vc-cover-icon {
    transform: scale(1.1);
    color: #c084fc;
}

.vc-cover-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #ffffff;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.vc-cover-badge i {
    color: #c084fc;
}

.vc-cover-hover-btn {
    position: absolute;
    inset: 0;
    background: rgba(124, 58, 237, 0.85);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    color: #ffffff;
    opacity: 0;
    transition: opacity 0.2s;
    font-weight: 700;
    font-size: 0.88rem;
    backdrop-filter: blur(2px);
    text-decoration: none;
}

.vc-card-cover:hover .vc-cover-hover-btn {
    opacity: 1;
}

/* Card Body */
.vc-card-body {
    padding: 1.15rem 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.vc-card-title {
    margin: 0 0 0.4rem 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
    line-height: 1.3;
}

[data-theme="dark"] .vc-card-title {
    color: #ffffff;
}

.vc-card-desc {
    margin: 0;
    font-size: 0.82rem;
    color: var(--text-muted, #64748b);
    line-height: 1.45;
    flex: 1;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.vc-card-drive-info {
    margin-top: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.76rem;
    font-weight: 600;
    color: #2563eb;
    background: color-mix(in srgb, #3b82f6 10%, transparent);
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    width: fit-content;
    text-decoration: none;
}

[data-theme="dark"] .vc-card-drive-info {
    color: #60a5fa;
}

/* Card Footer */
.vc-card-footer {
    padding: 0.75rem 1.25rem;
    border-top: 1px solid var(--border-color, #f1f5f9);
    background: var(--bg-body, #fafafa);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

[data-theme="dark"] .vc-card-footer {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.05);
}

.vc-card-date {
    font-size: 0.74rem;
    color: var(--text-muted, #94a3b8);
}

.vc-card-menu-btns {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.vc-icon-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    font-size: 0.95rem;
    transition: all 0.15s;
    text-decoration: none;
}

.vc-icon-btn:hover {
    background: var(--bg-surface, #ffffff);
    color: #7c3aed;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

/* Drive selector box inside modals */
.vc-drive-box {
    background: var(--bg-body, #f8fafc);
    border: 1.5px dashed var(--border-color, #cbd5e1);
    border-radius: 10px;
    padding: 1rem;
    text-align: center;
    margin-bottom: 1.25rem;
}

[data-theme="dark"] .vc-drive-box {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.15);
}

.vc-drive-status {
    margin-top: 0.65rem;
    font-size: 0.84rem;
    font-weight: 600;
}

/* Modals */
.vc-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.vc-modal {
    background: var(--bg-surface, #ffffff);
    border-radius: 16px;
    width: 100%;
    max-width: 520px;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    animation: vcModalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

[data-theme="dark"] .vc-modal {
    background: #18191d;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

@keyframes vcModalIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.vc-modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

[data-theme="dark"] .vc-modal-header {
    border-color: rgba(255, 255, 255, 0.08);
}

.vc-modal-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .vc-modal-title {
    color: #ffffff;
}

.vc-modal-body {
    padding: 1.5rem;
}

.vc-form-group {
    margin-bottom: 1.25rem;
}

.vc-form-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 700;
    margin-bottom: 0.4rem;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .vc-form-label {
    color: #cbd5e1;
}

.vc-form-input, .vc-form-textarea, .vc-form-select {
    width: 100%;
    padding: 0.6rem 0.85rem;
    border-radius: 8px;
    border: 1px solid var(--border-color, #cbd5e1);
    background: var(--bg-surface, #ffffff);
    color: var(--text-main, #0f172a);
    font-size: 0.88rem;
    outline: none;
    box-sizing: border-box;
    transition: all 0.2s;
}

[data-theme="dark"] .vc-form-input, 
[data-theme="dark"] .vc-form-textarea, 
[data-theme="dark"] .vc-form-select {
    background: #111215;
    border-color: rgba(255, 255, 255, 0.15);
    color: #ffffff;
}

.vc-form-input:focus, .vc-form-textarea:focus, .vc-form-select:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
}

.vc-modal-footer {
    padding: 1rem 1.5rem;
    background: var(--bg-body, #fafafa);
    border-top: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

[data-theme="dark"] .vc-modal-footer {
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.08);
}
</style>

<div class="vc-dash-container">
    <!-- Header -->
    <div class="vc-dash-header">
        <div>
            <div class="vc-dash-kicker">
                <i class="ph-bold ph-bezier-curve"></i> Workspace Creativo
            </div>
            <div class="vc-dash-title-row">
                <h1 class="vc-dash-title">VectorCraft</h1>
                <span class="vc-count-pill"><i class="ph-bold ph-pen-nib"></i> <?php echo $total_projects; ?> Ilustraciones</span>
            </div>
            <p class="vc-dash-desc">Diseño e ilustración vectorial profesional en Rust y WebAssembly: sincronización directa con Google Drive y enlaces públicos para compartir.</p>
        </div>

        <div class="vc-dash-actions">
            <!-- Search -->
            <form method="GET" action="index.php" class="vc-search-box">
                <input type="hidden" name="module" value="workspace">
                <input type="hidden" name="action" value="vectorcraft">
                <i class="ph-bold ph-magnifying-glass vc-search-icon"></i>
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar por título o carpeta...">
            </form>

            <!-- New Project Button (Requires Google Drive folder) -->
            <button type="button" class="vc-main-btn" onclick="openNewModal()">
                <i class="ph-bold ph-plus-circle"></i>
                <span>Nueva Ilustración</span>
            </button>
        </div>
    </div>

    <!-- Cards Grid -->
    <div class="vc-grid">
        <?php if (empty($projects)): ?>
            <div style="text-align: center; padding: 4rem 2rem; background: var(--bg-surface, #ffffff); border: 2px dashed var(--border-color, #e2e8f0); border-radius: 16px; grid-column: 1 / -1;">
                <i class="ph-bold ph-bezier-curve" style="font-size: 64px; color: #a855f7; margin-bottom: 1rem; display: inline-block;"></i>
                <h3 style="margin: 0 0 0.5rem 0; font-size: 1.25rem;">No se encontraron ilustraciones</h3>
                <p style="margin: 0 0 1.5rem 0; color: var(--text-muted, #64748b);">Crea tu primera ilustración y selecciónale una carpeta de Google Drive para alojarla automáticamente.</p>
                <button type="button" class="vc-main-btn" onclick="openNewModal()">
                    <i class="ph-bold ph-plus"></i> Crear Ilustración en Drive
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $p): ?>
                <?php 
                    $cardShareUrl = getVectorcraftShareUrl($p['share_token'] ?? '');
                ?>
                <div class="vc-card" id="projectCard_<?php echo $p['id']; ?>">
                    <div class="vc-card-cover" onclick="window.location.href='index.php?module=workspace&action=vectorcraft&edit=<?php echo $p['id']; ?>'">
                        <div class="vc-cover-pattern"></div>
                        <i class="ph-bold ph-bezier-curve vc-cover-icon"></i>
                        <div class="vc-cover-badge">
                            <i class="ph-bold ph-vector-three"></i> <?php echo strtoupper(htmlspecialchars($p['format_type'] ?? 'SVG')); ?>
                        </div>
                        <a href="index.php?module=workspace&action=vectorcraft&edit=<?php echo $p['id']; ?>" class="vc-cover-hover-btn">
                            <i class="ph-bold ph-pencil-simple" style="font-size: 24px;"></i>
                            <span>Abrir en VectorCraft</span>
                        </a>
                    </div>
                    
                    <div class="vc-card-body">
                        <h3 class="vc-card-title"><?php echo htmlspecialchars($p['title']); ?></h3>
                        <p class="vc-card-desc"><?php echo htmlspecialchars($p['description'] ?: 'Sin notas adicionales.'); ?></p>
                        
                        <?php if (!empty($p['drive_folder_id'])): ?>
                            <a href="https://drive.google.com/drive/folders/<?php echo htmlspecialchars($p['drive_folder_id']); ?>" target="_blank" class="vc-card-drive-info" title="Abrir carpeta en Google Drive">
                                <i class="ph-bold ph-google-drive-logo"></i>
                                <span><?php echo htmlspecialchars($p['drive_folder_name'] ?: 'Carpeta en Drive'); ?></span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="vc-card-footer">
                        <span class="vc-card-date">
                            <i class="ph-bold ph-calendar-blank"></i> <?php echo date('d M Y', strtotime($p['updated_at'])); ?>
                        </span>
                        
                        <div class="vc-card-menu-btns">
                            <!-- Compartir Enlace Público -->
                            <button type="button" class="vc-icon-btn" onclick="openDashboardShareModal('<?php echo htmlspecialchars(addslashes($p['title'])); ?>', '<?php echo $cardShareUrl; ?>', '<?php echo htmlspecialchars(addslashes($p['drive_web_link'] ?? '')); ?>')" title="Compartir enlace público">
                                <i class="ph-bold ph-share-network"></i>
                            </button>

                            <!-- Abrir en Google Drive -->
                            <?php if (!empty($p['drive_web_link'])): ?>
                            <a href="<?php echo htmlspecialchars($p['drive_web_link']); ?>" target="_blank" class="vc-icon-btn" title="Ver archivo en Google Drive" style="color: #2563eb;">
                                <i class="ph-bold ph-google-drive-logo"></i>
                            </a>
                            <?php endif; ?>

                            <!-- Descargar archivo local -->
                            <?php if (!empty($p['file_path']) && file_exists(__DIR__ . '/../../' . $p['file_path'])): ?>
                            <a href="<?php echo htmlspecialchars($p['file_path']); ?>" download class="vc-icon-btn" title="Descargar archivo">
                                <i class="ph-bold ph-download-simple"></i>
                            </a>
                            <?php endif; ?>

                            <!-- Duplicar -->
                            <button type="button" class="vc-icon-btn" onclick="duplicateProject(<?php echo $p['id']; ?>)" title="Duplicar">
                                <i class="ph-bold ph-copy"></i>
                            </button>

                            <!-- Editar Metadatos -->
                            <button type="button" class="vc-icon-btn" onclick="openEditModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['title'])); ?>', '<?php echo htmlspecialchars(addslashes($p['description'] ?? '')); ?>', '<?php echo htmlspecialchars($p['format_type'] ?? 'svg'); ?>', '<?php echo htmlspecialchars(addslashes($p['drive_folder_id'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($p['drive_folder_name'] ?? '')); ?>')" title="Editar datos">
                                <i class="ph-bold ph-pencil"></i>
                            </button>

                            <!-- Eliminar -->
                            <button type="button" class="vc-icon-btn" onclick="deleteProject(<?php echo $p['id']; ?>)" title="Eliminar" style="color: #ef4444;">
                                <i class="ph-bold ph-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL: NUEVA ILUSTRACIÓN (OBLIGA A SELECCIONAR CARPETA DE DRIVE) -->
<div class="vc-modal-backdrop" id="modalNew">
    <div class="vc-modal">
        <div class="vc-modal-header">
            <h3 class="vc-modal-title"><i class="ph-bold ph-plus-circle" style="color:#7c3aed; margin-right:6px;"></i> Nueva Ilustración VectorCraft</h3>
            <button type="button" class="vc-icon-btn" onclick="closeModal('modalNew')"><i class="ph-bold ph-x"></i></button>
        </div>
        <form id="formNewProject" onsubmit="handleCreateProject(event)">
            <input type="hidden" name="drive_folder_id" id="newDriveFolderId" required>
            <input type="hidden" name="drive_folder_name" id="newDriveFolderName">

            <div class="vc-modal-body">
                <div class="vc-form-group">
                    <label class="vc-form-label">Título del Diseño / Ilustración *</label>
                    <input type="text" name="title" class="vc-form-input" placeholder="Ej. Logotipo Corporativo, Afiche Publicitario..." required autofocus>
                </div>
                <div class="vc-form-group">
                    <label class="vc-form-label">Descripción o Notas</label>
                    <textarea name="description" class="vc-form-textarea" rows="2" placeholder="Detalles de diseño, paleta o requerimientos..."></textarea>
                </div>
                <div class="vc-form-group">
                    <label class="vc-form-label">Formato Principal</label>
                    <select name="format_type" class="vc-form-select">
                        <option value="svg" selected>SVG (Scalable Vector Graphics)</option>
                        <option value="vectorcraft">VectorCraft Nativo (.vectorcraft)</option>
                        <option value="png">PNG Alta Resolución (Rasterizado)</option>
                    </select>
                </div>

                <!-- SELECTOR OBLIGATORIO DE CARPETA DE GOOGLE DRIVE -->
                <div class="vc-drive-box">
                    <label style="display:block; font-size:0.82rem; font-weight:700; margin-bottom:0.5rem; color:var(--text-main, #0f172a);">
                        Carpeta de Google Drive Destino *
                    </label>
                    <button type="button" class="btn btn-primary" onclick="openDriveFolderPickerForNew()" style="background:#2563eb; border:none; display:inline-flex; align-items:center; gap:0.45rem; padding:0.55rem 1rem; border-radius:8px; color:#fff; font-weight:700; cursor:pointer;">
                        <i class="ph-bold ph-google-drive-logo"></i> Elegir Carpeta en Google Drive
                    </button>
                    <div id="newDriveFolderDisplay" class="vc-drive-status" style="color: #ea580c;">
                        <i class="ph-bold ph-warning-circle"></i> Debes seleccionar una carpeta en Google Drive (Obligatorio)
                    </div>
                </div>
            </div>
            <div class="vc-modal-footer">
                <button type="button" class="vc-secondary-btn" onclick="closeModal('modalNew')">Cancelar</button>
                <button type="submit" class="vc-main-btn" id="btnSubmitNew">
                    <i class="ph-bold ph-check"></i> Crear y Abrir Editor
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDITAR ILUSTRACIÓN -->
<div class="vc-modal-backdrop" id="modalEdit">
    <div class="vc-modal">
        <div class="vc-modal-header">
            <h3 class="vc-modal-title"><i class="ph-bold ph-pencil" style="color:#7c3aed; margin-right:6px;"></i> Editar Detalles</h3>
            <button type="button" class="vc-icon-btn" onclick="closeModal('modalEdit')"><i class="ph-bold ph-x"></i></button>
        </div>
        <form id="formEditProject" onsubmit="handleUpdateProject(event)">
            <input type="hidden" name="id" id="editProjectId">
            <input type="hidden" name="drive_folder_id" id="editDriveFolderId">
            <input type="hidden" name="drive_folder_name" id="editDriveFolderName">

            <div class="vc-modal-body">
                <div class="vc-form-group">
                    <label class="vc-form-label">Título *</label>
                    <input type="text" name="title" id="editProjectTitle" class="vc-form-input" required>
                </div>
                <div class="vc-form-group">
                    <label class="vc-form-label">Descripción</label>
                    <textarea name="description" id="editProjectDesc" class="vc-form-textarea" rows="2"></textarea>
                </div>
                <div class="vc-form-group">
                    <label class="vc-form-label">Formato</label>
                    <select name="format_type" id="editProjectFormat" class="vc-form-select">
                        <option value="svg">SVG (Scalable Vector Graphics)</option>
                        <option value="vectorcraft">VectorCraft Nativo (.vectorcraft)</option>
                        <option value="png">PNG Alta Resolución</option>
                    </select>
                </div>

                <div class="vc-drive-box">
                    <label style="display:block; font-size:0.82rem; font-weight:700; margin-bottom:0.5rem;">
                        Carpeta de Google Drive Asignada
                    </label>
                    <button type="button" class="btn btn-outline" onclick="openDriveFolderPickerForEdit()" style="display:inline-flex; align-items:center; gap:0.45rem; padding:0.45rem 0.85rem; border-radius:8px; cursor:pointer;">
                        <i class="ph-bold ph-google-drive-logo"></i> Cambiar Carpeta en Drive
                    </button>
                    <div id="editDriveFolderDisplay" class="vc-drive-status" style="color:#2563eb;">
                        Carpeta actual: <strong id="editDriveFolderNameTxt"></strong>
                    </div>
                </div>
            </div>
            <div class="vc-modal-footer">
                <button type="button" class="vc-secondary-btn" onclick="closeModal('modalEdit')">Cancelar</button>
                <button type="submit" class="vc-main-btn">
                    <i class="ph-bold ph-check"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: COMPARTIR EN DASHBOARD -->
<div class="modal-overlay" id="modalDashboardShare" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 100400; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: var(--bg-surface, #ffffff); border-radius: 16px; width: 100%; max-width: 520px; box-shadow: 0 20px 45px rgba(0,0,0,0.25); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main, #0f172a);">
                <i class="ph-bold ph-share-network" style="color: #7c3aed;"></i> Compartir Ilustración
            </h3>
            <button type="button" class="btn-icon" onclick="closeModal('modalDashboardShare')" style="background:none; border:none; font-size:1.25rem; cursor:pointer;"><i class="ph-bold ph-x"></i></button>
        </div>
        <div style="padding: 1.5rem;">
            <h4 id="dashShareTitle" style="margin-top: 0; margin-bottom: 0.5rem; font-size: 1rem; color: var(--text-main, #0f172a);"></h4>
            <p style="font-size: 0.86rem; color: var(--text-muted, #64748b); margin-top: 0; margin-bottom: 1.25rem; line-height: 1.45;">
                Copia este enlace para enviarlo a clientes o colegas. Permite previsualizar la ilustración en alta resolución y descargar los archivos.
            </p>

            <label style="display: block; font-size: 0.82rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--text-main, #0f172a);">
                Enlace Público
            </label>
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem;">
                <input type="text" id="dashShareInput" readonly style="flex: 1; padding: 0.65rem 0.85rem; border-radius: 8px; border: 1px solid var(--border-color, #cbd5e1); font-size: 0.85rem; background: var(--bg-body, #f8fafc); color: var(--text-main, #0f172a);">
                <button type="button" class="btn btn-primary" onclick="copyFromDashboardModalShare()" style="background: #7c3aed; border: none; padding: 0 1.2rem; font-weight: 700; border-radius: 8px; color: #fff; cursor: pointer;">
                    <i class="ph-bold ph-copy"></i> Copiar
                </button>
            </div>

            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <a id="dashShareOpenLink" href="#" target="_blank" class="vc-btn" style="flex: 1; justify-content: center;">
                    <i class="ph-bold ph-arrow-square-out"></i> Abrir Enlace Público
                </a>
                <a id="dashShareDriveLink" href="#" target="_blank" class="vc-btn" style="flex: 1; justify-content: center; color: #2563eb; display: none;">
                    <i class="ph-bold ph-google-drive-logo"></i> Ver en Drive
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function openNewModal() {
    document.getElementById('modalNew').style.display = 'flex';
}

function openEditModal(id, title, desc, format, driveId, driveName) {
    document.getElementById('editProjectId').value = id;
    document.getElementById('editProjectTitle').value = title;
    document.getElementById('editProjectDesc').value = desc;
    document.getElementById('editProjectFormat').value = format || 'svg';
    document.getElementById('editDriveFolderId').value = driveId || '';
    document.getElementById('editDriveFolderName').value = driveName || '';
    document.getElementById('editDriveFolderNameTxt').textContent = driveName || 'Ninguna';
    document.getElementById('modalEdit').style.display = 'flex';
}

function openDashboardShareModal(title, shareUrl, driveUrl) {
    document.getElementById('dashShareTitle').textContent = title;
    document.getElementById('dashShareInput').value = shareUrl;
    document.getElementById('dashShareOpenLink').href = shareUrl;
    
    const driveBtn = document.getElementById('dashShareDriveLink');
    if (driveUrl) {
        driveBtn.href = driveUrl;
        driveBtn.style.display = 'inline-flex';
    } else {
        driveBtn.style.display = 'none';
    }

    document.getElementById('modalDashboardShare').style.display = 'flex';
}

function copyFromDashboardModalShare() {
    const input = document.getElementById('dashShareInput');
    const text = input.value;
    
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            alert('📋 ¡Enlace público copiado al portapapeles!\n\n' + text);
        }).catch(() => {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
        alert('📋 ¡Enlace público copiado al portapapeles!\n\n' + text);
    } catch (err) {
        prompt('Copia manualmente el enlace:', text);
    }
    document.body.removeChild(textArea);
}

// Selector de Google Drive para NUEVO proyecto
function openDriveFolderPickerForNew() {
    if (typeof cdOpenPicker === 'function') {
        cdOpenPicker(null, function(folder) {
            if (folder && folder.id) {
                document.getElementById('newDriveFolderId').value = folder.id;
                document.getElementById('newDriveFolderName').value = folder.name;
                const display = document.getElementById('newDriveFolderDisplay');
                display.style.color = '#10b981';
                display.innerHTML = `<i class="ph-bold ph-check-circle"></i> Carpeta seleccionada: <strong>${folder.name}</strong>`;
            }
        });
    } else {
        alert('El explorador de Google Drive no está disponible en este momento.');
    }
}

// Selector de Google Drive para EDITAR proyecto
function openDriveFolderPickerForEdit() {
    if (typeof cdOpenPicker === 'function') {
        cdOpenPicker(null, function(folder) {
            if (folder && folder.id) {
                document.getElementById('editDriveFolderId').value = folder.id;
                document.getElementById('editDriveFolderName').value = folder.name;
                document.getElementById('editDriveFolderNameTxt').textContent = folder.name;
            }
        });
    }
}

async function handleCreateProject(e) {
    e.preventDefault();
    const driveFolderId = document.getElementById('newDriveFolderId').value;
    if (!driveFolderId) {
        alert('⚠️ Es obligatorio seleccionar una carpeta de Google Drive para alojar esta ilustración.');
        openDriveFolderPickerForNew();
        return;
    }

    const btn = document.getElementById('btnSubmitNew');
    btn.disabled = true;
    btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Creando...';

    const formData = new FormData(e.target);
    formData.append('action', 'create');

    try {
        const res = await fetch('ajax/ajax_vectorcraft.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success && data.redirect_url) {
            window.location.href = data.redirect_url;
        } else {
            alert(data.message || 'Error al crear la ilustración');
            btn.disabled = false;
            btn.innerHTML = '<i class="ph-bold ph-check"></i> Crear y Abrir Editor';
        }
    } catch (err) {
        alert('Error de conexión al servidor');
        btn.disabled = false;
        btn.innerHTML = '<i class="ph-bold ph-check"></i> Crear y Abrir Editor';
    }
}

async function handleUpdateProject(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'update');

    try {
        const res = await fetch('ajax/ajax_vectorcraft.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error al actualizar');
        }
    } catch (err) {
        alert('Error de conexión');
    }
}

async function duplicateProject(id) {
    if (!confirm('¿Deseas duplicar esta ilustración vectorial?')) return;
    const formData = new FormData();
    formData.append('action', 'duplicate');
    formData.append('id', id);

    try {
        const res = await fetch('ajax/ajax_vectorcraft.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error al duplicar');
        }
    } catch (err) {
        alert('Error de conexión');
    }
}

async function deleteProject(id) {
    if (!confirm('¿Estás seguro de eliminar esta ilustración? Esta acción no se puede deshacer.')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    try {
        const res = await fetch('ajax/ajax_vectorcraft.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            const card = document.getElementById('projectCard_' + id);
            if (card) card.remove();
        } else {
            alert(data.message || 'Error al eliminar');
        }
    } catch (err) {
        alert('Error de conexión');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
