<?php
// modules/workspace/deckcraft.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// Asegurar que la tabla y columnas existan
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `deckcraft_presentations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL DEFAULT 'Presentación sin título',
        `description` TEXT NULL,
        `file_path` VARCHAR(500) NULL,
        `thumbnail` VARCHAR(500) NULL,
        `theme` VARCHAR(100) DEFAULT 'default',
        `slides_count` INT DEFAULT 1,
        `client_id` INT NULL,
        `project_id` INT NULL,
        `drive_folder_id` VARCHAR(255) NULL,
        `drive_folder_name` VARCHAR(255) NULL,
        `drive_folder_url` VARCHAR(500) NULL,
        `drive_file_id` VARCHAR(255) NULL,
        `drive_file_url` VARCHAR(500) NULL,
        `share_token` VARCHAR(64) NULL,
        `is_public` TINYINT(1) DEFAULT 1,
        `created_by` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_created_by` (`created_by`),
        INDEX `idx_client_id` (`client_id`),
        INDEX `idx_drive_folder_id` (`drive_folder_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Limpiar IDs de Google Slides corruptos/huérfanos que no tienen archivo PPTX real
    $db->exec("UPDATE deckcraft_presentations SET drive_file_id = NULL, drive_file_url = NULL WHERE (file_path IS NULL OR file_path = '') AND drive_file_url LIKE '%docs.google.com/presentation%'");

    // Asegurar tokens únicos en presentaciones existentes
    $db->exec("UPDATE deckcraft_presentations SET share_token = MD5(CONCAT(id, 'deck', NOW())) WHERE share_token IS NULL OR share_token = ''");

    // Auto-sembrar presentación demo si está vacía
    $total_count = (int)$db->query("SELECT COUNT(*) FROM deckcraft_presentations")->fetchColumn();
    if ($total_count === 0) {
        $demo_token = bin2hex(random_bytes(16));
        $stmtSeed = $db->prepare("
            INSERT INTO deckcraft_presentations (title, description, theme, slides_count, drive_folder_name, share_token, is_public, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
        ");
        $stmtSeed->execute([
            'Presentación Demo — Northern Lights',
            'Plantilla de muestra oficial con diapositivas vectoriales, transiciones Morph y animaciones.',
            'sample',
            8,
            'Mi Unidad',
            $demo_token,
            $current_user_id > 0 ? $current_user_id : 1
        ]);
    }
} catch (Exception $e) {
    // Continuar si ya existe
}

// Base URL para enlaces públicos y compartir
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$protocol = $is_https ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$sys_base_url = $protocol . '://' . $host . ($script_dir ? $script_dir : '') . '/';

// ==============================================================================
// MODO 1: EDITOR DE DIAPOSITIVAS EN 100% VH Y 100% VW (PANTALLA COMPLETA)
// ==============================================================================
if ($edit_id > 0) {
    $stmt = $db->prepare("
        SELECT p.*, u.name as creator_name 
        FROM deckcraft_presentations p 
        LEFT JOIN users u ON p.created_by = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$edit_id]);
    $current_presentation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_presentation) {
        header("Location: index.php?module=workspace&action=deckcraft");
        exit();
    }

    // Asegurar token de compartir
    if (empty($current_presentation['share_token'])) {
        $newToken = bin2hex(random_bytes(16));
        $db->prepare("UPDATE deckcraft_presentations SET share_token = ? WHERE id = ?")->execute([$newToken, $edit_id]);
        $current_presentation['share_token'] = $newToken;
    }

    // Si el archivo PPTX no existe localmente pero está en Drive, descargarlo
    if ((empty($current_presentation['file_path']) || !file_exists($current_presentation['file_path'])) && !empty($current_presentation['drive_file_id'])) {
        require_once 'includes/GoogleDriveHelper.php';
        $driveHelper = new GoogleDriveHelper();
        if ($driveHelper->isConfigured()) {
            $upload_dir = 'uploads/deckcraft/';
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
            $safe_filename = 'deck_' . $edit_id . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $current_presentation['title']) . '.pptx';
            $local_dest = $upload_dir . $safe_filename;
            if ($driveHelper->downloadFile($current_presentation['drive_file_id'], $local_dest)) {
                $current_presentation['file_path'] = $local_dest;
                $db->prepare("UPDATE deckcraft_presentations SET file_path = ? WHERE id = ?")->execute([$local_dest, $edit_id]);
            }
        }
    }

    // Configurar modo popup para eliminar sidebar y bordes del CRM
    $is_popup = true;
    $allow_scroll = false;
    $page_title = htmlspecialchars($current_presentation['title']) . " — DeckCraft Editor";

    require_once 'includes/header.php';

    // Parámetros de inicio para DeckCraft
    $iframe_param = '';
    if (!empty($current_presentation['file_path']) && file_exists($current_presentation['file_path'])) {
        $iframe_param = '?load=' . urlencode('../../' . $current_presentation['file_path']);
    } elseif (isset($_GET['blank']) || $current_presentation['theme'] === 'blank') {
        $iframe_param = '?blank';
    } elseif (isset($_GET['sample']) || $current_presentation['theme'] === 'sample') {
        $iframe_param = '';
    }

    $public_share_url = $sys_base_url . "ppt/" . $current_presentation['share_token'];
    ?>
    <style>
        /* Reset para ocupar 100% estricto de pantalla sin scrolls */
        html, body {
            overflow: hidden !important;
            height: 100vh !important;
            width: 100vw !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #18191c !important;
        }

        .content-wrapper {
            padding: 0 !important;
            height: 100vh !important;
            width: 100vw !important;
            overflow: hidden !important;
        }

        /* Topbar de control superior ultrafina */
        .dc-editor-bar {
            height: 44px;
            width: 100vw;
            background: #1e1f23;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
            box-sizing: border-box;
            color: #f1f5f9;
            z-index: 1000;
        }

        .dc-bar-left {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            min-width: 0;
        }

        .dc-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.28rem 0.6rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .dc-back-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.25);
        }

        .dc-editor-logo {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 14px;
            flex-shrink: 0;
        }

        .dc-presentation-name {
            font-size: 0.92rem;
            font-weight: 700;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
        }

        .dc-drive-folder-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.72rem;
            font-weight: 600;
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.1);
            border: 1px solid rgba(56, 189, 248, 0.25);
            padding: 0.15rem 0.55rem;
            border-radius: 9999px;
            white-space: nowrap;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            text-decoration: none;
        }

        .dc-drive-folder-pill:hover {
            background: rgba(56, 189, 248, 0.2);
            color: #7dd3fc;
        }

        .dc-save-status {
            font-size: 0.72rem;
            font-weight: 600;
            color: #94a3b8;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.3s;
        }

        .dc-bar-right {
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .dc-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 600;
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.18s;
        }

        .dc-action-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.25);
        }

        .dc-action-btn-drive {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border-color: #0284c7;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.35);
        }

        .dc-action-btn-drive:hover {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            border-color: #0ea5e9;
        }

        .dc-action-btn-share {
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
            border-color: #ea580c;
            color: #ffffff !important;
        }

        .dc-action-btn-share:hover {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }

        /* Lienzo Iframe 100% de alto y ancho exacto */
        .dc-full-frame {
            width: 100vw;
            height: calc(100vh - 44px);
            border: 0;
            display: block;
            background: #262626;
            margin: 0;
            padding: 0;
        }

        /* Toast flotante de notificación */
        .dc-toast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            background: #1e1f23;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            font-size: 0.84rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            z-index: 10000;
            opacity: 0;
            transform: translateY(15px);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: none;
        }

        .dc-toast.show {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
    </style>

    <div class="dc-editor-bar">
        <div class="dc-bar-left">
            <a href="index.php?module=workspace&action=deckcraft" class="dc-back-btn" title="Volver al catálogo">
                <i class="ph-bold ph-arrow-left"></i>
                <span>Presentaciones</span>
            </a>
            <div class="dc-editor-logo" title="DeckCraft">
                <i class="ph-bold ph-projector-screen-chart"></i>
            </div>
            <span class="dc-presentation-name" id="presentationTitleDisplay">
                <?php echo htmlspecialchars($current_presentation['title']); ?>
            </span>

            <?php if (!empty($current_presentation['drive_folder_name'])): ?>
            <a href="<?php echo htmlspecialchars($current_presentation['drive_folder_url'] ?? '#'); ?>" target="_blank" class="dc-drive-folder-pill" title="Carpeta destino en Google Drive (clic para abrir)">
                <i class="ph-bold ph-google-drive-logo"></i>
                <span><?php echo htmlspecialchars($current_presentation['drive_folder_name']); ?></span>
            </a>
            <?php endif; ?>

            <span class="dc-save-status" id="dcSaveStatusBadge">
                <i class="ph-bold ph-cloud-check"></i>
                <span>Sincronizado con Drive</span>
            </span>
        </div>

        <div class="dc-bar-right">
            <!-- Botón Guardar en Google Drive -->
            <button type="button" class="dc-action-btn dc-action-btn-drive" id="btnTopbarSaveDrive" onclick="saveToDriveAction()" title="Sincronizar y guardar cambios en Google Drive">
                <i class="ph-bold ph-cloud-arrow-up"></i>
                <span>Guardar en Drive</span>
            </button>

            <!-- Botón Compartir Enlace Público -->
            <button type="button" class="dc-action-btn dc-action-btn-share" onclick="openShareModal('<?php echo $public_share_url; ?>')" title="Compartir enlace público">
                <i class="ph-bold ph-share-network"></i>
                <span class="d-none d-sm-inline">Compartir</span>
            </button>

            <!-- Descargar PPTX si existe archivo -->
            <?php if (!empty($current_presentation['file_path']) && file_exists($current_presentation['file_path'])): ?>
            <a href="<?php echo htmlspecialchars($current_presentation['file_path']); ?>" download class="dc-action-btn" id="btnDownloadPptx" title="Descargar archivo PowerPoint">
                <i class="ph-bold ph-download-simple"></i>
                <span class="d-none d-md-inline">Descargar PPTX</span>
            </a>
            <?php else: ?>
            <a href="#" download class="dc-action-btn" id="btnDownloadPptx" style="display: none;" title="Descargar archivo PowerPoint">
                <i class="ph-bold ph-download-simple"></i>
                <span class="d-none d-md-inline">Descargar PPTX</span>
            </a>
            <?php endif; ?>

            <!-- Abrir carpeta en Google Drive -->
            <?php if (!empty($current_presentation['drive_folder_url'])): ?>
            <a href="<?php echo htmlspecialchars($current_presentation['drive_folder_url']); ?>" target="_blank" class="dc-action-btn" title="Abrir carpeta en Google Drive">
                <i class="ph-bold ph-google-drive-logo"></i>
                <span class="d-none d-lg-inline">Abrir Drive</span>
            </a>
            <?php endif; ?>

            <!-- Pantalla Completa nativa -->
            <button type="button" class="dc-action-btn" onclick="toggleEditorFullscreen()" title="Pantalla Completa Nativa">
                <i class="ph-bold ph-arrows-out" id="btnFsIcon"></i>
                <span class="d-none d-xl-inline">Pantalla Completa</span>
            </button>

            <!-- Salir del editor -->
            <a href="index.php?module=workspace&action=deckcraft" class="dc-action-btn" title="Cerrar y volver">
                <i class="ph-bold ph-x"></i>
            </a>
        </div>
    </div>

    <!-- Iframe a 100vh / 100vw -->
    <iframe 
        id="deckcraftViewer"
        class="dc-full-frame"
        src="assets/apps/deckcraft/index.html<?php echo $iframe_param; ?>"
        title="DeckCraft Editor"
        allow="fullscreen; clipboard-read; clipboard-write"
        allowfullscreen>
    </iframe>

    <!-- Toast flotante para notificaciones -->
    <div class="dc-toast" id="editorToast">
        <i class="ph-bold ph-check-circle" id="toastIcon" style="color: #22c55e; font-size: 1.15rem;"></i>
        <span id="toastMsg">Cambios guardados</span>
    </div>

    <!-- ==============================================================================
         MODAL GUARDAR EN GOOGLE DRIVE (OPCIÓN DIRECTA O MANUAL)
         ============================================================================== -->
    <div class="dc-modal-overlay" id="modalSaveToDrive" style="display: none;">
        <div class="dc-modal" style="max-width: 520px;">
            <div class="dc-modal-head">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph-bold ph-google-drive-logo" style="color: #0284c7; font-size: 1.4rem;"></i>
                    <h3 class="dc-modal-title">Guardar en Google Drive</h3>
                </div>
                <button type="button" class="dc-modal-close" onclick="closeSaveToDriveModal()">&times;</button>
            </div>
            <form id="formSaveToDrive" onsubmit="handleManualSaveToDrive(event)">
                <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                <div class="dc-modal-body">
                    <div style="background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.2); padding: 0.85rem; border-radius: 10px; font-size: 0.82rem; color: #0284c7;">
                        <strong><i class="ph-bold ph-folder-notch"></i> Carpeta vinculada:</strong><br>
                        <?php echo htmlspecialchars($current_presentation['drive_folder_name'] ?? 'Google Drive'); ?>
                        <?php if (!empty($current_presentation['drive_folder_url'])): ?>
                            <a href="<?php echo htmlspecialchars($current_presentation['drive_folder_url']); ?>" target="_blank" style="color: #0284c7; text-decoration: underline; margin-left: 6px;">(Abrir carpeta ↗)</a>
                        <?php endif; ?>
                    </div>

                    <div style="background: rgba(234, 88, 12, 0.08); border: 1px solid rgba(234, 88, 12, 0.25); padding: 0.85rem; border-radius: 10px; font-size: 0.82rem; color: #ea580c; line-height: 1.5;">
                        <strong><i class="ph-bold ph-lightning"></i> Guardado Directo en DeckCraft:</strong><br>
                        Haz clic en el icono del <strong>disquete 💾</strong> arriba a la izquierda o en <strong>File &gt; Save</strong> dentro de DeckCraft. Se sincroniza de inmediato con tu Google Drive sin necesidad de subir nada.
                    </div>

                    <div>
                        <label class="dc-field-label">O si descargaste el archivo, selecciónalo aquí:</label>
                        <input type="file" id="pptxFileInput" name="pptx_file" accept=".pptx,.ppt,.pdf,.deckcraft" class="dc-input">
                    </div>
                </div>
                <div class="dc-modal-foot">
                    <button type="button" class="dc-secondary-btn" onclick="closeSaveToDriveModal()">Cerrar</button>
                    <button type="submit" class="dc-main-btn" id="btnSubmitDriveSave" style="background: #0284c7;">
                        <i class="ph-bold ph-cloud-arrow-up"></i>
                        <span>Sincronizar Archivo Seleccionado</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==============================================================================
         MODAL COMPARTIR ENLACE PÚBLICO
         ============================================================================== -->
    <div class="dc-modal-overlay" id="modalSharePresentation" style="display: none;">
        <div class="dc-modal" style="max-width: 500px;">
            <div class="dc-modal-head">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="ph-bold ph-share-network" style="color: #ea580c; font-size: 1.3rem;"></i>
                    <h3 class="dc-modal-title">Compartir Presentación</h3>
                </div>
                <button type="button" class="dc-modal-close" onclick="closeShareModal()">&times;</button>
            </div>
            <div class="dc-modal-body">
                <p style="font-size: 0.85rem; color: var(--text-muted, #64748b); margin: 0;">
                    Cualquier persona con este enlace podrá ver la presentación en pantalla completa con transiciones y animaciones (sin necesidad de iniciar sesión):
                </p>

                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                    <input type="text" id="shareUrlInput" class="dc-input" readonly value="<?php echo htmlspecialchars($public_share_url); ?>" style="font-family: monospace; font-size: 0.82rem;">
                    <button type="button" class="dc-main-btn" onclick="copyShareUrl()" style="white-space: nowrap; padding: 0.55rem 0.85rem;">
                        <i class="ph-bold ph-copy"></i>
                        <span id="copyBtnText">Copiar</span>
                    </button>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem;">
                    <a href="<?php echo htmlspecialchars($public_share_url); ?>" target="_blank" class="dc-secondary-btn" style="font-size: 0.8rem;">
                        <i class="ph-bold ph-arrow-square-out"></i>
                        <span>Abrir enlace público</span>
                    </a>
                </div>
            </div>
            <div class="dc-modal-foot">
                <button type="button" class="dc-secondary-btn" onclick="closeShareModal()">Cerrar</button>
            </div>
        </div>
    </div>

    <script>
    const dcIframe = document.getElementById('deckcraftViewer');
    let saveTimeout = null;

    // Toast flotante
    function showEditorToast(msg, isSuccess = true) {
        const toast = document.getElementById('editorToast');
        const icon = document.getElementById('toastIcon');
        const msgEl = document.getElementById('toastMsg');
        
        msgEl.innerText = msg;
        icon.className = isSuccess ? 'ph-bold ph-check-circle' : 'ph-bold ph-warning-circle';
        icon.style.color = isSuccess ? '#22c55e' : '#ef4444';
        
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3500);
    }

    function updateSaveStatusBadge(html, color = '#94a3b8') {
        const badge = document.getElementById('dcSaveStatusBadge');
        if (badge) {
            badge.innerHTML = html;
            badge.style.color = color;
        }
    }

    // 1. ESCUCHAR CAPTURA DE ARCHIVO GUARDADO DESDE EL IFRAME
    window.addEventListener('message', function(evt) {
        if (!evt.data) return;
        if (evt.data.type === 'DECKCRAFT_FILE_SAVED' && evt.data.blob) {
            if (saveTimeout) clearTimeout(saveTimeout);
            uploadPptxBlobToDrive(evt.data.blob, evt.data.filename);
        }
    });

    // 2. DISPARAR GUARDADO EN GOOGLE DRIVE DESDE EL BOTÓN SUPERIOR
    function saveToDriveAction() {
        const btn = document.getElementById('btnTopbarSaveDrive');
        btn.disabled = true;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Guardando...';
        updateSaveStatusBadge('<i class="ph-bold ph-spinner ph-spin"></i> Sincronizando con Drive...', '#38bdf8');

        // Enviar señal al iframe para invocar la exportación nativa
        if (dcIframe && dcIframe.contentWindow) {
            dcIframe.contentWindow.postMessage({ type: 'TRIGGER_SAVE' }, '*');
        }

        // Si en 1.8 segundos no se interceptó el Blob (ej. el foco en el canvas no respondió),
        // abrir modal amigable con instrucciones y fallback
        saveTimeout = setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph-bold ph-cloud-arrow-up"></i> Guardar en Drive';
            openSaveToDriveModal();
        }, 1800);
    }

    // 3. SUBIR EL BLOB A GOOGLE DRIVE VÍA AJAX
    function uploadPptxBlobToDrive(blob, filename) {
        const btn = document.getElementById('btnTopbarSaveDrive');
        btn.disabled = true;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Subiendo a Drive...';
        updateSaveStatusBadge('<i class="ph-bold ph-spinner ph-spin"></i> Subiendo a Drive...', '#38bdf8');

        const formData = new FormData();
        formData.append('action', 'save_to_drive');
        formData.append('id', '<?php echo $edit_id; ?>');
        formData.append('pptx_file', blob, filename || 'presentation.pptx');

        fetch('ajax/ajax_deckcraft.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph-bold ph-check"></i> ¡Guardado en Drive!';
            setTimeout(() => {
                btn.innerHTML = '<i class="ph-bold ph-cloud-arrow-up"></i> Guardar en Drive';
            }, 3000);

            if (data.success) {
                updateSaveStatusBadge('<i class="ph-bold ph-check-circle" style="color: #22c55e;"></i> Guardado en Drive (' + (data.updated_at || 'ahora') + ')', '#22c55e');
                showEditorToast('✓ ' + data.message, true);
                
                // Mostrar botón de descarga si se generó archivo local
                const btnDl = document.getElementById('btnDownloadPptx');
                if (btnDl && data.file_path) {
                    btnDl.href = data.file_path;
                    btnDl.style.display = 'inline-flex';
                }
                closeSaveToDriveModal();
            } else {
                updateSaveStatusBadge('<i class="ph-bold ph-warning-circle" style="color: #ef4444;"></i> Error al guardar', '#ef4444');
                alert(data.message || 'Error al guardar en Google Drive');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph-bold ph-cloud-arrow-up"></i> Guardar en Drive';
            updateSaveStatusBadge('<i class="ph-bold ph-warning-circle" style="color: #ef4444;"></i> Error de conexión', '#ef4444');
            alert('Error de conexión al sincronizar con Google Drive');
        });
    }

    // Modal Guardar
    function openSaveToDriveModal() {
        document.getElementById('modalSaveToDrive').style.display = 'flex';
    }

    function closeSaveToDriveModal() {
        document.getElementById('modalSaveToDrive').style.display = 'none';
    }

    function handleManualSaveToDrive(e) {
        e.preventDefault();
        const fileInput = document.getElementById('pptxFileInput');
        if (!fileInput.files || fileInput.files.length === 0) {
            alert('Por favor selecciona un archivo .pptx para subir');
            return;
        }

        const form = document.getElementById('formSaveToDrive');
        const formData = new FormData(form);
        formData.append('action', 'save_to_drive');

        const btn = document.getElementById('btnSubmitDriveSave');
        btn.disabled = true;
        btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Subiendo a Drive...';

        fetch('ajax/ajax_deckcraft.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph-bold ph-cloud-arrow-up"></i> Sincronizar Archivo Seleccionado';
            if (data.success) {
                closeSaveToDriveModal();
                showEditorToast('✓ ' + data.message, true);
                updateSaveStatusBadge('<i class="ph-bold ph-check-circle" style="color: #22c55e;"></i> Guardado en Drive (' + (data.updated_at || 'ahora') + ')', '#22c55e');
                const btnDl = document.getElementById('btnDownloadPptx');
                if (btnDl && data.file_path) {
                    btnDl.href = data.file_path;
                    btnDl.style.display = 'inline-flex';
                }
            } else {
                alert(data.message || 'Error al guardar en Google Drive');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph-bold ph-cloud-arrow-up"></i> Sincronizar Archivo Seleccionado';
            alert('Error de conexión al subir');
        });
    }

    // Pantalla completa
    function toggleEditorFullscreen() {
        const icon = document.getElementById('btnFsIcon');
        if (!document.fullscreenElement) {
            if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen();
            } else if (document.documentElement.webkitRequestFullscreen) {
                document.documentElement.webkitRequestFullscreen();
            }
            if (icon) {
                icon.classList.remove('ph-arrows-out');
                icon.classList.add('ph-arrows-in');
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
            if (icon) {
                icon.classList.remove('ph-arrows-in');
                icon.classList.add('ph-arrows-out');
            }
        }
    }

    function openShareModal(url) {
        document.getElementById('shareUrlInput').value = url;
        document.getElementById('modalSharePresentation').style.display = 'flex';
        document.getElementById('copyBtnText').innerText = 'Copiar';
    }

    function closeShareModal() {
        document.getElementById('modalSharePresentation').style.display = 'none';
    }

    function copyShareUrl() {
        const input = document.getElementById('shareUrlInput');
        input.select();
        navigator.clipboard.writeText(input.value).then(() => {
            document.getElementById('copyBtnText').innerText = '¡Copiado!';
            setTimeout(() => {
                const btn = document.getElementById('copyBtnText');
                if (btn) btn.innerText = 'Copiar';
            }, 2500);
        });
    }

    document.addEventListener('fullscreenchange', () => {
        const icon = document.getElementById('btnFsIcon');
        if (!document.fullscreenElement && icon) {
            icon.classList.remove('ph-arrows-in');
            icon.classList.add('ph-arrows-out');
        }
    });
    </script>
    <?php
    exit();
}

// ==============================================================================
// MODO 2: CATÁLOGO / DASHBOARD CON TODOS LOS SLIDES CREADOS
// ==============================================================================

$page_title = "Presentaciones (DeckCraft) — Workspace";
require_once 'includes/header.php';

// Obtener todas las presentaciones de la base de datos
$search = trim($_GET['q'] ?? '');
$params = [];
$whereClause = "";

if (!empty($search)) {
    $whereClause = "WHERE p.title LIKE ? OR p.description LIKE ? OR p.drive_folder_name LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$sql = "
    SELECT p.*, u.name as creator_name, u.avatar as creator_avatar 
    FROM deckcraft_presentations p 
    LEFT JOIN users u ON p.created_by = u.id 
    $whereClause
    ORDER BY p.updated_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$presentations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_presentations = count($presentations);
?>

<style>
/* ==========================================================================
   DASHBOARD DE PRESENTACIONES DECKCRAFT
   ========================================================================== */

.dc-dash-container {
    padding: 2rem 2.25rem;
    max-width: 1400px;
    margin: 0 auto;
    animation: dcFadeIn 0.3s ease-out;
}

@keyframes dcFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.dc-dash-header {
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.dc-dash-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.74rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #ea580c;
}

.dc-dash-title-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    margin-top: 0.35rem;
}

.dc-dash-title {
    margin: 0;
    font-size: 2.1rem;
    font-weight: 900;
    letter-spacing: -0.8px;
    color: var(--text-main, #0f172a);
    line-height: 1.15;
}

[data-theme="dark"] .dc-dash-title {
    color: #ffffff;
}

.dc-count-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: color-mix(in srgb, #ea580c 12%, transparent);
    color: #ea580c;
    border: 1px solid color-mix(in srgb, #ea580c 25%, transparent);
    font-size: 0.75rem;
    font-weight: 800;
    padding: 0.22rem 0.65rem;
    border-radius: 9999px;
}

.dc-dash-desc {
    margin: 0.35rem 0 0 0;
    color: var(--text-muted, #64748b);
    font-size: 0.95rem;
}

.dc-dash-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.dc-search-box {
    position: relative;
    display: flex;
    align-items: center;
    width: 260px;
}

.dc-search-box input {
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

.dc-search-box input:focus {
    border-color: #ea580c;
    box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15);
}

.dc-search-icon {
    position: absolute;
    left: 0.75rem;
    color: var(--text-muted, #94a3b8);
    font-size: 1rem;
    pointer-events: none;
}

.dc-main-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.58rem 1.15rem;
    border-radius: 10px;
    font-size: 0.88rem;
    font-weight: 700;
    color: #ffffff;
    background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px -2px rgba(234, 88, 12, 0.4);
    transition: all 0.2s;
    text-decoration: none;
}

.dc-main-btn:hover {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    box-shadow: 0 6px 18px -2px rgba(234, 88, 12, 0.5);
    transform: translateY(-1px);
    color: #ffffff;
}

.dc-secondary-btn {
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

.dc-secondary-btn:hover {
    background: var(--bg-body, #f8fafc);
    border-color: #ea580c;
    color: #ea580c;
}

/* Grilla de Presentaciones */
.dc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.dc-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.dc-card:hover {
    transform: translateY(-4px);
    border-color: rgba(234, 88, 12, 0.35);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .dc-card {
    background: #17181c;
    border-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .dc-card:hover {
    border-color: rgba(234, 88, 12, 0.5);
    box-shadow: 0 14px 32px rgba(0, 0, 0, 0.4);
}

/* Thumbnail / Cover de la presentación */
.dc-card-cover {
    height: 155px;
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    cursor: pointer;
}

.dc-cover-pattern {
    position: absolute;
    inset: 0;
    opacity: 0.15;
    background-image: radial-gradient(#ea580c 1px, transparent 1px);
    background-size: 16px 16px;
}

.dc-cover-icon {
    font-size: 52px;
    color: #f97316;
    opacity: 0.9;
    transition: transform 0.3s;
}

.dc-card:hover .dc-cover-icon {
    transform: scale(1.1);
}

.dc-cover-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.65);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s;
    backdrop-filter: blur(2px);
}

.dc-card:hover .dc-cover-overlay {
    opacity: 1;
}

.dc-open-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    background: #ea580c;
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 700;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
}

.dc-card-badge {
    position: absolute;
    top: 0.75rem;
    left: 0.75rem;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 700;
    border: 1px solid rgba(255, 255, 255, 0.15);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.dc-card-drive-badge {
    position: absolute;
    bottom: 0.75rem;
    left: 0.75rem;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    background: rgba(2, 132, 199, 0.85);
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    max-width: 80%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dc-card-body {
    padding: 1.15rem;
    display: flex;
    flex-direction: column;
    flex: 1;
}

.dc-card-title {
    margin: 0 0 0.35rem 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
    line-height: 1.3;
    cursor: pointer;
}

.dc-card-title:hover {
    color: #ea580c;
}

[data-theme="dark"] .dc-card-title {
    color: #ffffff;
}

.dc-card-desc {
    margin: 0 0 0.85rem 0;
    font-size: 0.82rem;
    color: var(--text-muted, #64748b);
    line-height: 1.45;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
}

.dc-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.85rem;
    border-top: 1px solid var(--border-color, #e2e8f0);
    font-size: 0.75rem;
    color: var(--text-muted, #64748b);
}

.dc-card-author {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.dc-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    color: #334155;
}

.dc-card-menu-btn {
    background: transparent;
    border: none;
    color: var(--text-muted, #64748b);
    padding: 0.3rem;
    cursor: pointer;
    border-radius: 6px;
    font-size: 1.05rem;
    transition: all 0.2s;
}

.dc-card-menu-btn:hover {
    background: var(--bg-body, #f1f5f9);
    color: var(--text-main, #0f172a);
}

/* Selector estilizado de Google Drive */
.dc-drive-box {
    border: 1.5px dashed #0284c7;
    background: rgba(2, 132, 199, 0.04);
    border-radius: 12px;
    padding: 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.dc-drive-selected-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg-surface, #ffffff);
    border: 1px solid rgba(2, 132, 199, 0.3);
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    gap: 0.75rem;
}

[data-theme="dark"] .dc-drive-selected-card {
    background: #17181c;
}

.dc-drive-btn-choose {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    width: 100%;
    padding: 0.65rem;
    border-radius: 8px;
    background: #0284c7;
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.dc-drive-btn-choose:hover {
    background: #0369a1;
}

/* Modales */
.dc-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.dc-modal {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 16px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    animation: dcModalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes dcModalPop {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

[data-theme="dark"] .dc-modal {
    background: #1e1f23;
    border-color: rgba(255, 255, 255, 0.1);
}

.dc-modal-head {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dc-modal-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .dc-modal-title {
    color: #ffffff;
}

.dc-modal-close {
    background: transparent;
    border: none;
    font-size: 1.2rem;
    color: var(--text-muted, #94a3b8);
    cursor: pointer;
}

.dc-modal-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.dc-field-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-main, #0f172a);
    margin-bottom: 0.35rem;
    display: block;
}

[data-theme="dark"] .dc-field-label {
    color: #e2e8f0;
}

.dc-input, .dc-textarea {
    width: 100%;
    padding: 0.6rem 0.85rem;
    border-radius: 8px;
    border: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-body, #f8fafc);
    color: var(--text-main, #0f172a);
    font-size: 0.88rem;
    outline: none;
    box-sizing: border-box;
}

[data-theme="dark"] .dc-input, [data-theme="dark"] .dc-textarea {
    background: #17181c;
    border-color: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.dc-input:focus, .dc-textarea:focus {
    border-color: #ea580c;
}

.dc-modal-foot {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background: var(--bg-body, #f8fafc);
}

[data-theme="dark"] .dc-modal-foot {
    background: #17181c;
    border-color: rgba(255, 255, 255, 0.08);
}
</style>

<div class="dc-dash-container">
    <!-- Header del Dashboard -->
    <div class="dc-dash-header">
        <div>
            <span class="dc-dash-kicker"><i class="ph-bold ph-squares-four"></i> Workspace / Presentaciones</span>
            <div class="dc-dash-title-row">
                <h1 class="dc-dash-title">DeckCraft Slides</h1>
                <span class="dc-count-pill" id="totalCountPill"><?php echo $total_presentations; ?> creadas</span>
            </div>
            <p class="dc-dash-desc">Galería de diapositivas con guardado obligatorio en Google Drive y enlace público para compartir.</p>
        </div>

        <div class="dc-dash-actions">
            <!-- Buscador -->
            <div class="dc-search-box">
                <i class="ph-bold ph-magnifying-glass dc-search-icon"></i>
                <input type="text" id="deckSearch" placeholder="Buscar presentación..." oninput="filterPresentations(this.value)">
            </div>

            <!-- Botón Crear Nueva -->
            <button type="button" class="dc-main-btn" onclick="openNewModal()">
                <i class="ph-bold ph-plus-circle"></i>
                <span>Nueva Presentación</span>
            </button>
        </div>
    </div>

    <!-- Grilla de Presentaciones -->
    <?php if ($total_presentations > 0): ?>
    <div class="dc-grid" id="presentationsGrid">
        <?php foreach ($presentations as $p): 
            $theme_label = ($p['theme'] === 'blank') ? 'En Blanco' : 'Plantilla Demo';
            $blank_param = ($p['theme'] === 'blank') ? '&blank=1' : '';
            $edit_url = "index.php?module=workspace&action=deckcraft&edit={$p['id']}{$blank_param}";
            $share_token = $p['share_token'] ?? '';
            $public_url = $sys_base_url . "ppt/" . $share_token;
        ?>
        <div class="dc-card" data-title="<?php echo htmlspecialchars(strtolower($p['title'])); ?>" data-desc="<?php echo htmlspecialchars(strtolower($p['description'] ?? '')); ?>" id="card-<?php echo $p['id']; ?>">
            <!-- Cover / Click para abrir en 100vh / 100vw -->
            <div class="dc-card-cover" onclick="window.location.href='<?php echo $edit_url; ?>'">
                <div class="dc-cover-pattern"></div>
                <div class="dc-card-badge">
                    <i class="ph-fill ph-slideshow"></i>
                    <span><?php echo $theme_label; ?></span>
                </div>

                <?php if (!empty($p['drive_folder_name'])): ?>
                <div class="dc-card-drive-badge" title="Guardado en: <?php echo htmlspecialchars($p['drive_folder_name']); ?>">
                    <i class="ph-bold ph-google-drive-logo"></i>
                    <span><?php echo htmlspecialchars($p['drive_folder_name']); ?></span>
                </div>
                <?php endif; ?>

                <i class="ph-bold ph-projector-screen-chart dc-cover-icon"></i>
                <div class="dc-cover-overlay">
                    <span class="dc-open-pill">
                        <i class="ph-bold ph-play"></i>
                        <span>Abrir (100% Pantalla)</span>
                    </span>
                </div>
            </div>

            <!-- Contenido de la tarjeta -->
            <div class="dc-card-body">
                <h3 class="dc-card-title" onclick="window.location.href='<?php echo $edit_url; ?>'">
                    <?php echo htmlspecialchars($p['title']); ?>
                </h3>
                <p class="dc-card-desc">
                    <?php echo htmlspecialchars(!empty($p['description']) ? $p['description'] : 'Sin descripción adicional.'); ?>
                </p>

                <!-- Footer con autor y menú -->
                <div class="dc-card-footer">
                    <div class="dc-card-author" title="Creador">
                        <div class="dc-avatar">
                            <?php echo strtoupper(substr($p['creator_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <span><?php echo htmlspecialchars($p['creator_name'] ?? 'Usuario'); ?></span>
                        <span>•</span>
                        <span><?php echo date('d/m/Y', strtotime($p['updated_at'])); ?></span>
                    </div>

                    <div style="display: flex; gap: 0.35rem; align-items: center;">
                        <!-- Compartir enlace público -->
                        <button type="button" class="dc-card-menu-btn" onclick="openShareModal('<?php echo $public_url; ?>')" title="Compartir enlace público" style="color: #ea580c;">
                            <i class="ph-bold ph-share-network"></i>
                        </button>

                        <!-- Carpeta Google Drive -->
                        <?php if (!empty($p['drive_folder_url'])): ?>
                        <a href="<?php echo htmlspecialchars($p['drive_folder_url']); ?>" target="_blank" class="dc-card-menu-btn" title="Abrir carpeta en Google Drive" style="color: #0284c7; text-decoration: none;">
                            <i class="ph-bold ph-google-drive-logo"></i>
                        </a>
                        <?php endif; ?>

                        <!-- Duplicar -->
                        <button type="button" class="dc-card-menu-btn" onclick="duplicatePresentation(<?php echo $p['id']; ?>)" title="Duplicar">
                            <i class="ph-bold ph-copy"></i>
                        </button>

                        <!-- Renombrar -->
                        <button type="button" class="dc-card-menu-btn" onclick="openRenameModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['title']); ?>', '<?php echo addslashes($p['description'] ?? ''); ?>')" title="Editar detalles">
                            <i class="ph-bold ph-pencil-simple"></i>
                        </button>

                        <!-- Eliminar -->
                        <button type="button" class="dc-card-menu-btn" onclick="deletePresentation(<?php echo $p['id']; ?>)" title="Eliminar" style="color: #ef4444;">
                            <i class="ph-bold ph-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Empty State -->
    <div class="dc-empty">
        <div class="dc-empty-icon">
            <i class="ph-bold ph-presentation"></i>
        </div>
        <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0 0 0.5rem 0;">Aún no tienes presentaciones</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 450px; margin: 0 auto 1.5rem auto;">
            Crea tu primera presentación seleccionando una carpeta de Google Drive para abrir el editor nativo a pantalla completa.
        </p>
        <button type="button" class="dc-main-btn" onclick="openNewModal()">
            <i class="ph-bold ph-plus-circle"></i>
            <span>Crear Presentación</span>
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- ==============================================================================
     MODAL NUEVA PRESENTACIÓN (CON OBLIGACIÓN DE CARPETA GOOGLE DRIVE)
     ============================================================================== -->
<div class="dc-modal-overlay" id="modalNewPresentation">
    <div class="dc-modal" style="max-width: 520px;">
        <div class="dc-modal-head">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="ph-bold ph-presentation" style="color: #ea580c; font-size: 1.3rem;"></i>
                <h3 class="dc-modal-title">Nueva Presentación</h3>
            </div>
            <button type="button" class="dc-modal-close" onclick="closeNewModal()">&times;</button>
        </div>
        <form id="formNewPresentation" onsubmit="handleCreatePresentation(event)">
            <div class="dc-modal-body">
                <div>
                    <label class="dc-field-label">Título de la Presentación <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="newTitle" class="dc-input" placeholder="Ej. Propuesta Comercial, Pitch Deck Q4..." required autofocus>
                </div>

                <!-- SELECTOR OBLIGATORIO DE CARPETA DE GOOGLE DRIVE -->
                <div>
                    <label class="dc-field-label" style="display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="ph-bold ph-google-drive-logo" style="color: #0284c7;"></i> Carpeta de Google Drive <span style="color: #ef4444;">* (Obligatorio)</span></span>
                    </label>

                    <div class="dc-drive-box" id="driveBoxContainer">
                        <!-- Vista cuando se seleccionó carpeta -->
                        <div class="dc-drive-selected-card" id="driveSelectedCard" style="display: none;">
                            <div style="display: flex; align-items: center; gap: 0.6rem; min-width: 0;">
                                <i class="ph-bold ph-folder-notch" style="color: #0284c7; font-size: 1.4rem; flex-shrink: 0;"></i>
                                <div style="min-width: 0;">
                                    <div id="driveFolderNameText" style="font-weight: 700; font-size: 0.85rem; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Carpeta</div>
                                    <div id="driveFolderIdText" style="font-size: 0.7rem; color: var(--text-muted);">ID: ...</div>
                                </div>
                            </div>
                            <button type="button" class="dc-secondary-btn" onclick="openDrivePickerModal()" style="font-size: 0.75rem; padding: 0.35rem 0.65rem; border-color: #0284c7; color: #0284c7;">
                                Cambiar
                            </button>
                        </div>

                        <!-- Botón para abrir el modal si aún no hay seleccionada -->
                        <button type="button" class="dc-drive-btn-choose" id="btnChooseDrive" onclick="openDrivePickerModal()">
                            <i class="ph-bold ph-folder-plus"></i>
                            <span>Seleccionar Carpeta en Google Drive</span>
                        </button>
                    </div>

                    <input type="hidden" id="newDriveFolderId" required>
                    <input type="hidden" id="newDriveFolderName">
                    <input type="hidden" id="newDriveFolderUrl">
                    <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.35rem;">
                        La presentación se almacenará y sincronizará directamente en esta carpeta de Google Drive.
                    </small>
                </div>

                <div>
                    <label class="dc-field-label">Descripción (Opcional)</label>
                    <textarea id="newDesc" class="dc-textarea" rows="2" placeholder="Breve resumen del contenido de las diapositivas..."></textarea>
                </div>

                <div>
                    <label class="dc-field-label">Tipo de Inicio</label>
                    <select id="newTheme" class="dc-input">
                        <option value="blank">Lienzo en Blanco (1 Diapositiva)</option>
                        <option value="sample">Plantilla Demo (Northern Lights - 8 Diapositivas)</option>
                    </select>
                </div>
            </div>
            <div class="dc-modal-foot">
                <button type="button" class="dc-secondary-btn" onclick="closeNewModal()">Cancelar</button>
                <button type="submit" class="dc-main-btn" id="btnSubmitNew">
                    <i class="ph-bold ph-play"></i>
                    <span>Crear y Abrir (100% Pantalla)</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==============================================================================
     MODAL RENOMBRAR / EDITAR DETALLES
     ============================================================================== -->
<div class="dc-modal-overlay" id="modalRenamePresentation">
    <div class="dc-modal">
        <div class="dc-modal-head">
            <h3 class="dc-modal-title">Editar Presentación</h3>
            <button type="button" class="dc-modal-close" onclick="closeRenameModal()">&times;</button>
        </div>
        <form id="formRenamePresentation" onsubmit="handleRenamePresentation(event)">
            <input type="hidden" id="renameId" value="">
            <div class="dc-modal-body">
                <div>
                    <label class="dc-field-label">Título</label>
                    <input type="text" id="renameTitle" class="dc-input" required>
                </div>
                <div>
                    <label class="dc-field-label">Descripción</label>
                    <textarea id="renameDesc" class="dc-textarea" rows="2"></textarea>
                </div>
            </div>
            <div class="dc-modal-foot">
                <button type="button" class="dc-secondary-btn" onclick="closeRenameModal()">Cancelar</button>
                <button type="submit" class="dc-main-btn">
                    <i class="ph-bold ph-check"></i>
                    <span>Guardar Cambios</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==============================================================================
     MODAL COMPARTIR ENLACE PÚBLICO
     ============================================================================== -->
<div class="dc-modal-overlay" id="modalSharePresentation">
    <div class="dc-modal" style="max-width: 500px;">
        <div class="dc-modal-head">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="ph-bold ph-share-network" style="color: #ea580c; font-size: 1.3rem;"></i>
                <h3 class="dc-modal-title">Enlace Público de la Presentación</h3>
            </div>
            <button type="button" class="dc-modal-close" onclick="closeShareModal()">&times;</button>
        </div>
        <div class="dc-modal-body">
            <p style="font-size: 0.85rem; color: var(--text-muted, #64748b); margin: 0;">
                Comparte este enlace con tus clientes o colegas para que vean la presentación a pantalla completa (sin necesidad de cuenta o inicio de sesión):
            </p>

            <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                <input type="text" id="shareUrlInput" class="dc-input" readonly value="" style="font-family: monospace; font-size: 0.82rem;">
                <button type="button" class="dc-main-btn" onclick="copyShareUrl()" style="white-space: nowrap; padding: 0.55rem 0.85rem;">
                    <i class="ph-bold ph-copy"></i>
                    <span id="copyBtnText">Copiar</span>
                </button>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem;">
                <a href="#" id="shareOpenLink" target="_blank" class="dc-secondary-btn" style="font-size: 0.82rem;">
                    <i class="ph-bold ph-arrow-square-out"></i>
                    <span>Ver cómo lo ve el cliente</span>
                </a>
            </div>
        </div>
        <div class="dc-modal-foot">
            <button type="button" class="dc-secondary-btn" onclick="closeShareModal()">Cerrar</button>
        </div>
    </div>
</div>

<!-- Incluir el selector oficial de Google Drive del CRM -->
<?php require_once 'includes/custom_drive_picker.php'; ?>

<script>
// Filtrar presentaciones en vivo
function filterPresentations(query) {
    const q = (query || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.dc-card');
    cards.forEach(card => {
        const title = card.getAttribute('data-title') || '';
        const desc = card.getAttribute('data-desc') || '';
        const match = title.includes(q) || desc.includes(q);
        card.style.display = match ? 'flex' : 'none';
    });
}

// Modal Nueva Presentación
function openNewModal() {
    document.getElementById('modalNewPresentation').style.display = 'flex';
    document.getElementById('newTitle').value = '';
    document.getElementById('newDesc').value = '';
    document.getElementById('newDriveFolderId').value = '';
    document.getElementById('newDriveFolderName').value = '';
    document.getElementById('newDriveFolderUrl').value = '';
    document.getElementById('driveSelectedCard').style.display = 'none';
    document.getElementById('btnChooseDrive').style.display = 'inline-flex';
    setTimeout(() => document.getElementById('newTitle').focus(), 100);
}

function closeNewModal() {
    document.getElementById('modalNewPresentation').style.display = 'none';
}

// ABRIR EL SELECTOR DE GOOGLE DRIVE DEL SISTEMA
function openDrivePickerModal() {
    if (typeof cdOpenPicker !== 'undefined') {
        cdOpenPicker(null, function(selectedFolder) {
            if (selectedFolder && selectedFolder.id) {
                document.getElementById('newDriveFolderId').value = selectedFolder.id;
                document.getElementById('newDriveFolderName').value = selectedFolder.name || 'Carpeta Google Drive';
                document.getElementById('newDriveFolderUrl').value = selectedFolder.url || `https://drive.google.com/drive/folders/${selectedFolder.id}`;
                
                document.getElementById('driveFolderNameText').innerText = selectedFolder.name || 'Carpeta seleccionada';
                document.getElementById('driveFolderIdText').innerText = 'ID: ' + selectedFolder.id;
                
                document.getElementById('driveSelectedCard').style.display = 'flex';
                document.getElementById('btnChooseDrive').style.display = 'none';
            }
        });
    } else {
        alert("El selector de Google Drive no está disponible.");
    }
}

// CREACIÓN DE PRESENTACIÓN CON VALIDACIÓN OBLIGATORIA DE GOOGLE DRIVE
function handleCreatePresentation(e) {
    e.preventDefault();
    const title = document.getElementById('newTitle').value.trim();
    const description = document.getElementById('newDesc').value.trim();
    const theme = document.getElementById('newTheme').value;
    const driveFolderId = document.getElementById('newDriveFolderId').value.trim();
    const driveFolderName = document.getElementById('newDriveFolderName').value.trim();
    const driveFolderUrl = document.getElementById('newDriveFolderUrl').value.trim();
    const btn = document.getElementById('btnSubmitNew');

    // VALIDACIÓN ESTRICTA: OBLIGATORIO ELEGIR CARPETA DE GOOGLE DRIVE
    if (!driveFolderId) {
        alert('⚠️ ATENCIÓN: Es obligatorio seleccionar una carpeta de Google Drive para crear y guardar la presentación.');
        openDrivePickerModal();
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Creando en Drive...';

    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('title', title);
    formData.append('description', description);
    formData.append('theme', theme);
    formData.append('drive_folder_id', driveFolderId);
    formData.append('drive_folder_name', driveFolderName);
    formData.append('drive_folder_url', driveFolderUrl);

    fetch('ajax/ajax_deckcraft.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.redirect_url) {
            window.location.href = data.redirect_url;
        } else {
            alert(data.message || 'Error al crear la presentación');
            btn.disabled = false;
            btn.innerHTML = '<i class="ph-bold ph-play"></i> Crear y Abrir (100% Pantalla)';
        }
    })
    .catch(err => {
        alert('Error de conexión con el servidor');
        btn.disabled = false;
        btn.innerHTML = '<i class="ph-bold ph-play"></i> Crear y Abrir (100% Pantalla)';
    });
}

// Modal Renombrar
function openRenameModal(id, title, desc) {
    document.getElementById('renameId').value = id;
    document.getElementById('renameTitle').value = title;
    document.getElementById('renameDesc').value = desc;
    document.getElementById('modalRenamePresentation').style.display = 'flex';
    setTimeout(() => document.getElementById('renameTitle').focus(), 100);
}

function closeRenameModal() {
    document.getElementById('modalRenamePresentation').style.display = 'none';
}

function handleRenamePresentation(e) {
    e.preventDefault();
    const id = document.getElementById('renameId').value;
    const title = document.getElementById('renameTitle').value.trim();
    const description = document.getElementById('renameDesc').value.trim();

    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('id', id);
    formData.append('title', title);
    formData.append('description', description);

    fetch('ajax/ajax_deckcraft.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error al actualizar');
        }
    })
    .catch(() => alert('Error de conexión'));
}

// Compartir Enlace Público
function openShareModal(url) {
    document.getElementById('shareUrlInput').value = url;
    document.getElementById('shareOpenLink').href = url;
    document.getElementById('modalSharePresentation').style.display = 'flex';
    document.getElementById('copyBtnText').innerText = 'Copiar';
}

function closeShareModal() {
    document.getElementById('modalSharePresentation').style.display = 'none';
}

function copyShareUrl() {
    const input = document.getElementById('shareUrlInput');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        document.getElementById('copyBtnText').innerText = '¡Copiado!';
        setTimeout(() => {
            const btn = document.getElementById('copyBtnText');
            if (btn) btn.innerText = 'Copiar';
        }, 2500);
    });
}

// Duplicar Presentación
function duplicatePresentation(id) {
    if (!confirm('¿Deseas duplicar esta presentación?')) return;

    const formData = new FormData();
    formData.append('action', 'duplicate');
    formData.append('id', id);

    fetch('ajax/ajax_deckcraft.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error al duplicar');
        }
    })
    .catch(() => alert('Error de conexión'));
}

// Eliminar Presentación
function deletePresentation(id) {
    if (!confirm('¿Estás seguro de que deseas eliminar esta presentación? Esta acción no se puede deshacer.')) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('ajax/ajax_deckcraft.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('card-' + id);
            if (card) card.remove();
            const remaining = document.querySelectorAll('.dc-card').length;
            const pill = document.getElementById('totalCountPill');
            if (pill) pill.innerText = remaining + ' creadas';
            if (remaining === 0) window.location.reload();
        } else {
            alert(data.message || 'Error al eliminar');
        }
    })
    .catch(() => alert('Error de conexión'));
}
</script>

<?php require_once 'includes/footer.php'; ?>
