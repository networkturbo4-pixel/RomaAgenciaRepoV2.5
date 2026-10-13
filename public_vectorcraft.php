<?php
// public_vectorcraft.php
require_once 'config/database.php';

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    die("<div style='font-family:system-ui,-apple-system,sans-serif; text-align:center; padding:50px; background:#0f172a; color:#f8fafc; min-height:100vh;'>
        <h2 style='color:#ef4444;'>Enlace no válido</h2>
        <p>No se especificó un token de acceso para la ilustración.</p>
    </div>");
}

$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("
    SELECT p.*, u.name as creator_name, u.email as creator_email 
    FROM vectorcraft_projects p 
    LEFT JOIN users u ON p.created_by = u.id 
    WHERE p.share_token = ? AND (p.is_public = 1 OR p.is_public IS NULL)
");
$stmt->execute([$token]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die("<div style='font-family:system-ui,-apple-system,sans-serif; text-align:center; padding:50px; background:#0f172a; color:#f8fafc; min-height:100vh;'>
        <h2 style='color:#ef4444;'>Ilustración no encontrada o privada</h2>
        <p>El enlace público solicitado no existe o ha sido deshabilitado.</p>
    </div>");
}

$title = htmlspecialchars($project['title']);
$description = htmlspecialchars($project['description'] ?? '');
$format_type = strtolower($project['format_type'] ?? 'svg');
$file_path = $project['file_path'];
$has_file = !empty($file_path) && file_exists(__DIR__ . '/' . $file_path);
$drive_link = $project['drive_web_link'];
$folder_name = htmlspecialchars($project['drive_folder_name'] ?? '');
$updated_date = date('d M Y, H:i', strtotime($project['updated_at']));

$svg_content = '';
if ($has_file && $format_type === 'svg') {
    $svg_content = file_get_contents(__DIR__ . '/' . $file_path);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> — VectorCraft</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --primary: #7c3aed;
            --primary-light: #a855f7;
            --bg-dark: #09090b;
            --bg-surface: #18181b;
            --border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        header {
            background: rgba(24, 24, 27, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 0.85rem 2rem;
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .app-badge-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: linear-gradient(135deg, #a855f7 0%, #7c3aed 50%, #4338ca 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(124, 58, 237, 0.4);
            flex-shrink: 0;
        }

        .title-meta h1 {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }

        .title-meta .subline {
            font-size: 0.78rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2px;
        }

        .format-badge {
            background: rgba(168, 85, 247, 0.2);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.35);
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.5rem 0.95rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid var(--border);
            background: var(--bg-surface);
            color: var(--text-main);
        }

        .btn:hover {
            border-color: var(--primary);
            color: var(--primary-light);
            background: rgba(255, 255, 255, 0.05);
        }

        .btn-primary {
            background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%);
            border: none;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(124, 58, 237, 0.35);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #9333ea 0%, #7c3aed 100%);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-drive {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border-color: rgba(59, 130, 246, 0.3);
        }

        .btn-drive:hover {
            background: rgba(59, 130, 246, 0.25);
            color: #93c5fd;
            border-color: #60a5fa;
        }

        /* Main Viewer */
        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 1.5rem 2rem;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        .viewer-card {
            flex: 1;
            background: #121214;
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 550px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        .viewer-toolbar {
            background: #18181b;
            border-bottom: 1px solid var(--border);
            padding: 0.65rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .canvas-area {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #1a1a1e;
            background-image: 
                linear-gradient(45deg, #222226 25%, transparent 25%), 
                linear-gradient(-45deg, #222226 25%, transparent 25%), 
                linear-gradient(45deg, transparent 75%, #222226 75%), 
                linear-gradient(-45deg, transparent 75%, #222226 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            overflow: auto;
            padding: 2rem;
            position: relative;
        }

        .canvas-area svg {
            max-width: 90%;
            max-height: 70vh;
            filter: drop-shadow(0 15px 30px rgba(0, 0, 0, 0.6));
            border-radius: 4px;
            background: #ffffff;
            transition: transform 0.2s ease;
        }

        .canvas-area img {
            max-width: 90%;
            max-height: 70vh;
            border-radius: 8px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            object-fit: contain;
        }

        .empty-canvas {
            text-align: center;
            color: var(--text-muted);
            max-width: 450px;
        }

        .empty-canvas i {
            font-size: 56px;
            color: #a855f7;
            margin-bottom: 0.75rem;
        }

        /* Description Box */
        .desc-box {
            margin-top: 1.25rem;
            padding: 1.25rem 1.5rem;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .desc-box p {
            color: var(--text-muted);
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .drive-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            background: rgba(59, 130, 246, 0.12);
            color: #93c5fd;
            border: 1px solid rgba(59, 130, 246, 0.25);
            font-size: 0.78rem;
            font-weight: 600;
        }

        /* Toast */
        #toastNotice {
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
            z-index: 1000;
        }

        @media (max-width: 768px) {
            header, main {
                padding: 1rem;
            }
            .header-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-left">
            <div class="app-badge-icon">
                <i class="ph-bold ph-bezier-curve"></i>
            </div>
            <div class="title-meta">
                <h1><?php echo $title; ?></h1>
                <div class="subline">
                    <span class="format-badge"><?php echo strtoupper($format_type); ?></span>
                    <span>Actualizado: <?php echo $updated_date; ?></span>
                    <?php if (!empty($folder_name)): ?>
                        <span>· Carpeta: <strong><?php echo $folder_name; ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="header-actions">
            <!-- Copiar Enlace -->
            <button type="button" class="btn" onclick="copyShareUrl()" title="Copiar enlace público para compartir">
                <i class="ph-bold ph-share-network"></i>
                <span>Copiar Enlace</span>
            </button>

            <!-- Abrir en Google Drive -->
            <?php if (!empty($drive_link)): ?>
            <a href="<?php echo htmlspecialchars($drive_link); ?>" target="_blank" class="btn btn-drive" title="Abrir archivo en Google Drive">
                <i class="ph-bold ph-google-drive-logo"></i>
                <span>Ver en Drive</span>
            </a>
            <?php endif; ?>

            <!-- Descargar Archivo -->
            <?php if ($has_file): ?>
            <a href="<?php echo htmlspecialchars($file_path); ?>" download="<?php echo htmlspecialchars($title . '.' . $format_type); ?>" class="btn btn-primary" title="Descargar archivo vectorial">
                <i class="ph-bold ph-download-simple"></i>
                <span>Descargar <?php echo strtoupper($format_type); ?></span>
            </a>
            <?php endif; ?>

            <!-- Abrir en VectorCraft Editor -->
            <a href="index.php?module=workspace&action=vectorcraft&edit=<?php echo $project['id']; ?>" class="btn" title="Editar en VectorCraft (requiere iniciar sesión)">
                <i class="ph-bold ph-pencil-simple"></i>
                <span>Abrir Editor</span>
            </a>
        </div>
    </header>

    <main>
        <div class="viewer-card">
            <div class="viewer-toolbar">
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <i class="ph-bold ph-eye" style="color:#a855f7;"></i>
                    <span>Vista previa vectorial de alta definición</span>
                </div>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <button class="btn" style="padding:0.25rem 0.5rem; font-size:0.75rem;" onclick="zoomSvg(1.2)" title="Acercar">
                        <i class="ph-bold ph-magnifying-glass-plus"></i>
                    </button>
                    <button class="btn" style="padding:0.25rem 0.5rem; font-size:0.75rem;" onclick="zoomSvg(0.8)" title="Alejar">
                        <i class="ph-bold ph-magnifying-glass-minus"></i>
                    </button>
                    <button class="btn" style="padding:0.25rem 0.5rem; font-size:0.75rem;" onclick="resetZoom()" title="Restablecer tamaño">
                        <i class="ph-bold ph-arrows-counter-clockwise"></i>
                    </button>
                </div>
            </div>

            <div class="canvas-area" id="canvasArea">
                <?php if ($has_file && $format_type === 'svg'): ?>
                    <div id="svgContainer" style="display:flex; align-items:center; justify-content:center; width:100%; height:100%;">
                        <?php echo $svg_content; ?>
                    </div>
                <?php elseif ($has_file && in_array($format_type, ['png', 'jpg', 'jpeg', 'webp'])): ?>
                    <img id="imageTarget" src="<?php echo htmlspecialchars($file_path); ?>" alt="<?php echo $title; ?>">
                <?php else: ?>
                    <div class="empty-canvas">
                        <i class="ph-bold ph-bezier-curve"></i>
                        <h3 style="margin-bottom:0.5rem; color:#f8fafc;">Ilustración en proceso</h3>
                        <p>El diseño ha sido creado y vinculado en Google Drive. Cuando se guarde o exporte desde VectorCraft, la vista previa se actualizará automáticamente aquí.</p>
                        <?php if (!empty($drive_link)): ?>
                            <a href="<?php echo htmlspecialchars($drive_link); ?>" target="_blank" class="btn btn-drive" style="margin-top:1rem;">
                                <i class="ph-bold ph-google-drive-logo"></i> Abrir carpeta en Google Drive
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($description) || !empty($folder_name)): ?>
        <div class="desc-box">
            <div>
                <strong style="color:var(--text-main); font-size:0.92rem; display:block; margin-bottom:4px;">Notas del Diseño</strong>
                <p><?php echo $description ?: 'Sin notas adicionales.'; ?></p>
            </div>
            <?php if (!empty($folder_name)): ?>
            <div class="drive-badge-pill">
                <i class="ph-bold ph-google-drive-logo" style="color:#60a5fa; font-size:16px;"></i>
                <span>Alojado en Drive: <strong><?php echo $folder_name; ?></strong></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>

    <div id="toastNotice">
        <i class="ph-bold ph-check-circle" style="color:#10b981; font-size:18px;"></i>
        <span>¡Enlace copiado al portapapeles!</span>
    </div>

    <script>
        let currentZoom = 1;
        const svgEl = document.querySelector('#svgContainer svg') || document.getElementById('imageTarget');

        function zoomSvg(factor) {
            if (!svgEl) return;
            currentZoom *= factor;
            currentZoom = Math.min(Math.max(currentZoom, 0.2), 5);
            svgEl.style.transform = `scale(${currentZoom})`;
        }

        function resetZoom() {
            if (!svgEl) return;
            currentZoom = 1;
            svgEl.style.transform = `scale(1)`;
        }

        function copyShareUrl() {
            const url = window.location.href;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(() => {
                    showToast('¡Enlace público copiado al portapapeles!');
                }).catch(() => {
                    fallbackCopy(url);
                });
            } else {
                fallbackCopy(url);
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
                showToast('¡Enlace público copiado al portapapeles!');
            } catch (err) {
                prompt('Copia el siguiente enlace:', text);
            }
            document.body.removeChild(textArea);
        }

        function showToast(msg) {
            const toast = document.getElementById('toastNotice');
            if (msg) toast.querySelector('span').textContent = msg;
            toast.style.display = 'flex';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>
