<?php
// modules/conexiones/builder.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    die("Acceso Denegado");
}

require_once 'config/database.php';
$db = (new Database())->getConnection();

$template_id = $_GET['id'] ?? 0;
$template = [
    'name' => 'Nueva Plantilla',
    'subject' => '',
    'body_design' => '{}' // GrapeJS JSON data
];

if ($template_id) {
    $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $template = $existing;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Constructor de Correos - <?php echo htmlspecialchars($template['name']); ?></title>
    
    <!-- GrapeJS -->
    <link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
    <script src="https://unpkg.com/grapesjs"></script>
    <!-- Preset Newsletter GrapeJS -->
    <script src="https://unpkg.com/grapesjs-preset-newsletter"></script>
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
        
        .builder-topbar {
            height: 60px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            z-index: 10;
            position: relative;
        }

        .builder-title-group {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .builder-title-group input {
            border: 1px solid #e2e8f0;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.9rem;
            outline: none;
        }
        
        .builder-title-group input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
        }

        .builder-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #cbd5e1;
            color: #475569;
        }

        .btn-outline:hover {
            background: #f1f5f9;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
        }

        #gjs {
            height: calc(100vh - 60px);
            border: none;
        }
    </style>
</head>
<body>

    <div class="builder-topbar">
        <div class="builder-title-group">
            <a href="index.php?module=conexiones&tab=tab-templates" class="btn btn-outline" style="padding: 0.5rem;">
                <i class="ph ph-arrow-left"></i>
            </a>
            <input type="text" id="tpl_name" value="<?php echo htmlspecialchars($template['name']); ?>" placeholder="Nombre de la Plantilla" style="width: 200px;">
            <input type="text" id="tpl_subject" value="<?php echo htmlspecialchars($template['subject']); ?>" placeholder="Asunto del Correo" style="width: 250px;">
        </div>
        <div class="builder-actions">
            <button class="btn btn-secondary" onclick="testTemplate()" style="background: #e2e8f0; color: #1e293b;">
                <i class="ph ph-paper-plane-tilt"></i> Enviar Prueba
            </button>
            <button class="btn btn-primary" onclick="saveTemplate()">
                <i class="ph ph-floppy-disk"></i> Guardar Plantilla
            </button>
        </div>
    </div>

    <div id="gjs"></div>

    <script>
        const editor = grapesjs.init({
            container: '#gjs',
            height: '100%',
            fromElement: true,
            storageManager: false, // We'll handle save manually
            plugins: ['grapesjs-preset-newsletter'],
            pluginsOpts: {
                'grapesjs-preset-newsletter': {
                    modalTitleImport: 'Importar template',
                }
            }
        });

        // Load existing design data if any
        const existingData = <?php echo empty($template['body_design']) ? '{}' : $template['body_design']; ?>;
        if (Object.keys(existingData).length > 0) {
            editor.loadProjectData(existingData);
        }

        async function saveTemplate() {
            const btn = document.querySelector('.btn-primary');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
            btn.disabled = true;

            const html = editor.runCommand('gjs-get-inlined-html');
            const design = editor.getProjectData();
            const name = document.getElementById('tpl_name').value;
            const subject = document.getElementById('tpl_subject').value;

            try {
                const response = await fetch('index.php?module=conexiones&action=ajax_save_template', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: <?php echo $template_id; ?>,
                        name: name,
                        subject: subject,
                        body_html: html,
                        body_design: design
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    alert("Plantilla guardada exitosamente!");
                    if (result.new_id && <?php echo $template_id; ?> === 0) {
                        window.location.href = `index.php?module=conexiones&action=builder&id=${result.new_id}`;
                    }
                } else {
                    alert("Error: " + result.error);
                }
            } catch (error) {
                console.error(error);
                alert("Ocurrió un error al guardar la plantilla.");
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }

        async function testTemplate() {
            const toEmail = prompt("Ingresa tu correo electrónico para enviarte una prueba:");
            if (!toEmail) return;

            const btn = document.querySelector('.btn-secondary');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Enviando...';
            btn.disabled = true;

            const html = editor.runCommand('gjs-get-inlined-html');
            const subject = document.getElementById('tpl_subject').value;

            try {
                const response = await fetch('index.php?module=conexiones&action=ajax_test_template', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: toEmail, html: html, subject: subject })
                });

                const result = await response.json();
                
                if (result.success) {
                    alert("¡Correo de prueba enviado con éxito!");
                } else {
                    alert("Error al enviar la prueba: " + result.error);
                }
            } catch (error) {
                console.error(error);
                alert("Ocurrió un error en la solicitud de prueba.");
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
