<?php
// modules/public/contract.php
require_once 'config/database.php';
$is_public = true;

$uuid = $_GET['uuid'] ?? '';
if (!$uuid) {
    echo "Contrato no especificado.";
    exit;
}

$stmt = $db->prepare("SELECT * FROM contracts WHERE uuid = ?");
$stmt->execute([$uuid]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    echo "Contrato no encontrado o enlace inválido.";
    exit;
}

// Fetch Global Settings
$global_settings = [];
$stmt_set = $db->query("SELECT * FROM settings");
foreach ($stmt_set->fetchAll() as $row) {
    $global_settings[$row['setting_key']] = $row['setting_value'];
}
$site_name = htmlspecialchars($global_settings['site_name'] ?? 'Nuestra Agencia');

// Handle Signature Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $contract['status'] === 'pending') {
    $signer_name = $_POST['signer_name'] ?? '';
    $signer_document = $_POST['signer_document'] ?? '';
    $signature_data = $_POST['signature_data'] ?? ''; // base64

    if (!empty($signer_name) && !empty($signer_document) && !empty($signature_data)) {
        $signer_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $signer_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmtUpdate = $db->prepare("UPDATE contracts SET status='signed', signer_name=?, signer_document=?, signature_data=?, signer_ip=?, signer_user_agent=?, signed_at=NOW() WHERE uuid=?");
        $stmtUpdate->execute([$signer_name, $signer_document, $signature_data, $signer_ip, $signer_user_agent, $uuid]);
        header("Location: index.php?module=public&action=contract&uuid=" . urlencode($uuid) . "&success=1");
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($contract['title']); ?> | <?php echo $site_name; ?></title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <!-- Signature Pad -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($global_settings['primary_color'] ?? '#4f46e5'); ?>;
            --bg-color: #f3f4f6;
            --surface-color: #ffffff;
            --text-main: #111827;
            --text-muted: #4b5563;
            --border-color: #e5e7eb;
            --radius-lg: 16px;
            --radius-md: 8px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 2rem 1rem;
            -webkit-font-smoothing: antialiased;
        }

        .document-wrapper {
            max-width: 800px;
            margin: 0 auto;
        }

        .document-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .document-header img {
            max-height: 40px;
            margin-bottom: 1rem;
        }

        .document-paper {
            background: var(--surface-color);
            padding: 3rem;
            border-radius: var(--radius-md);
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .status-ribbon {
            position: absolute;
            top: 20px;
            right: -35px;
            transform: rotate(45deg);
            padding: 5px 40px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .ribbon-pending { background: #f59e0b; }
        .ribbon-signed { background: #10b981; }
        .ribbon-cancelled { background: #ef4444; }

        .contract-body {
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            line-height: 1.6;
            color: #1e293b;
            text-align: justify;
        }
        .contract-body h3, .contract-body h4 {
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            margin-top: 1.5rem;
        }

        .signature-box {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px dashed var(--border-color);
        }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 0.5rem; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: 'Inter', sans-serif; font-size: 14px; box-sizing: border-box; }
        
        .canvas-container {
            border: 2px dashed #9ca3af;
            border-radius: var(--radius-md);
            background: #f9fafb;
            position: relative;
            margin-bottom: 0.5rem;
            touch-action: none;
        }
        canvas {
            display: block;
            width: 100%;
            height: 200px;
            border-radius: var(--radius-md);
        }
        .btn-clear {
            position: absolute;
            top: 10px;
            right: 10px;
            background: white;
            border: 1px solid var(--border-color);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            font-size: 14px;
            width: 100%;
            transition: all 0.2s;
        }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-primary:hover { opacity: 0.9; }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 14px;
        }

        .signed-stamp {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 2rem;
            padding: 2rem;
            border: 1px solid #10b981;
            border-radius: var(--radius-md);
            background: #ecfdf5;
        }
        .signed-stamp img { max-height: 150px; margin-bottom: 1rem; mix-blend-mode: multiply; }

        @media (max-width: 600px) {
            .document-paper { padding: 1.5rem; }
            .status-ribbon { right: -40px; top: 15px; padding: 5px 40px; font-size: 9px; }
        }
        
        @media print {
            body { background: white; padding: 0; }
            .document-paper { box-shadow: none; padding: 0; border: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="document-wrapper">
    <div class="document-header no-print">
        <?php if(!empty($global_settings['logo_light'])): ?>
            <img src="<?php echo htmlspecialchars($global_settings['logo_light']); ?>" alt="Logo">
        <?php else: ?>
            <h2 style="margin:0; color:var(--primary-color); font-weight:800;"><?php echo $site_name; ?></h2>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert-success no-print">
            <i class="ph-fill ph-check-circle" style="font-size: 1.25rem;"></i>
            ¡El contrato ha sido firmado exitosamente!
        </div>
    <?php endif; ?>

    <div class="document-paper">
        <?php 
            $ribbon_class = '';
            $ribbon_text = '';
            if ($contract['status'] === 'pending') { $ribbon_class = 'ribbon-pending'; $ribbon_text = 'Pendiente'; }
            elseif ($contract['status'] === 'signed') { $ribbon_class = 'ribbon-signed'; $ribbon_text = 'Firmado'; }
            elseif ($contract['status'] === 'cancelled') { $ribbon_class = 'ribbon-cancelled'; $ribbon_text = 'Cancelado'; }
        ?>
        <div class="status-ribbon <?php echo $ribbon_class; ?>"><?php echo $ribbon_text; ?></div>

        <h1 style="font-size:20px; text-align:center; margin-top:0; margin-bottom:2rem;"><?php echo htmlspecialchars($contract['title']); ?></h1>
        
        <div class="contract-body">
            <?php echo $contract['body']; ?>
        </div>

        <?php if ($contract['status'] === 'signed'): ?>
            <div class="signed-stamp">
                <div style="color: #059669; font-weight:800; text-transform:uppercase; font-size:12px; margin-bottom:1rem; letter-spacing:1px;">
                    <i class="ph-fill ph-check-circle"></i> Documento Firmado Digitalmente
                </div>
                <img src="<?php echo htmlspecialchars($contract['signature_data']); ?>" alt="Firma">
                <div style="text-align:center; font-family:'Inter', sans-serif; font-size:13px; color:var(--text-main);">
                    <strong><?php echo htmlspecialchars($contract['signer_name']); ?></strong><br>
                    Documento: <?php echo htmlspecialchars($contract['signer_document']); ?><br>
                    <span style="color:var(--text-muted); font-size:11px;">Fecha: <?php echo date('d M Y, H:i', strtotime($contract['signed_at'])); ?></span>
                </div>
            </div>

            <!-- Certificado de Firma / Auditoría -->
            <div style="page-break-before: always; margin-top: 3rem; padding-top: 2rem; border-top: 2px dashed var(--border-color);">
                <h2 style="font-size: 18px; text-align: center; margin-bottom: 1.5rem; color: var(--text-main);"><i class="ph ph-certificate"></i> Certificado de Firma Electrónica</h2>
                <div style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; font-size: 13px; font-family: monospace; color: var(--text-muted); line-height: 1.6; word-break: break-all;">
                    <strong>ID del Documento:</strong> <?php echo htmlspecialchars($contract['uuid']); ?><br>
                    <strong>Fecha y Hora de Firma:</strong> <?php echo date('Y-m-d H:i:s', strtotime($contract['signed_at'])); ?><br>
                    <strong>Dirección IP del Firmante:</strong> <?php echo htmlspecialchars($contract['signer_ip'] ?? 'No registrada'); ?><br>
                    <strong>Agente de Usuario (Navegador):</strong> <?php echo htmlspecialchars($contract['signer_user_agent'] ?? 'No registrado'); ?><br>
                    <strong>Nombre del Firmante:</strong> <?php echo htmlspecialchars($contract['signer_name']); ?><br>
                    <strong>Documento de Identidad:</strong> <?php echo htmlspecialchars($contract['signer_document']); ?><br>
                    <strong>Huella Digital (SHA-256):</strong> <?php echo hash('sha256', $contract['signature_data']); ?>
                </div>
                <p style="font-size: 11px; text-align: center; color: var(--text-muted); margin-top: 1rem;">
                    Este anexo certifica la recolección de evidencia electrónica al momento de realizarse la firma del documento.
                </p>
            </div>
            
            <div class="no-print" style="margin-top: 2rem; text-align:center; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <button onclick="window.print()" class="btn" style="background: var(--surface-color); color: var(--text-main); border: 1px solid var(--border-color); width:auto;"><i class="ph ph-printer"></i> Imprimir Normal</button>
                <a href="index.php?module=public&action=download_pdf&uuid=<?php echo urlencode($contract['uuid']); ?>" class="btn btn-primary" style="width:auto; text-decoration:none;"><i class="ph ph-lock-key"></i> Descargar PDF Seguro</a>
            </div>
            
        <?php elseif ($contract['status'] === 'pending'): ?>
            <div class="signature-box no-print">
                <h3 style="margin-top:0; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                    <i class="ph ph-pen-nib"></i> Firmar Documento
                </h3>
                
                <form method="POST" id="signatureForm" onsubmit="return submitSignature()">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label>Nombre Completo</label>
                            <input type="text" name="signer_name" class="form-control" required placeholder="Ej: Juan Pérez">
                        </div>
                        <div class="form-group">
                            <label>DNI / RUC / Pasaporte</label>
                            <input type="text" name="signer_document" class="form-control" required placeholder="Ej: 12345678">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Firma Digital (Dibuja aquí)</label>
                        <div class="canvas-container">
                            <canvas id="signatureCanvas"></canvas>
                            <button type="button" class="btn-clear" onclick="signaturePad.clear()">Limpiar</button>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem; display:flex; gap:0.5rem; align-items:flex-start;">
                        <input type="checkbox" id="agree" required style="margin-top: 3px;">
                        <label for="agree" style="font-size:13px; color:var(--text-muted); line-height:1.5;">
                            Declaro que he leído el presente documento y acepto todos sus términos y condiciones. Reconozco que esta firma digital tiene la misma validez que una firma manuscrita.
                        </label>
                    </div>

                    <input type="hidden" name="signature_data" id="signature_data">
                    <button type="submit" class="btn btn-primary"><i class="ph ph-check"></i> Firmar y Aceptar Contrato</button>
                </form>
            </div>
            
            <script>
                const canvas = document.getElementById('signatureCanvas');
                const ctx = canvas.getContext('2d');
                
                // Adjust canvas resolution for high-DPI screens
                function resizeCanvas() {
                    const ratio =  Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    ctx.scale(ratio, ratio);
                    signaturePad.clear(); // otherwise signature gets stretched
                }

                const signaturePad = new SignaturePad(canvas, {
                    penColor: "rgb(0, 0, 0)",
                    backgroundColor: "rgba(0,0,0,0)" // transparent
                });

                window.addEventListener("resize", resizeCanvas);
                resizeCanvas();
                
                function submitSignature() {
                    if (signaturePad.isEmpty()) {
                        alert("Por favor, dibuja tu firma antes de continuar.");
                        return false;
                    }
                    // Save as PNG base64
                    const dataURL = signaturePad.toDataURL("image/png");
                    document.getElementById('signature_data').value = dataURL;
                    return true;
                }
            </script>
        <?php endif; ?>
    </div>
    
    <div style="text-align:center; font-size:12px; color:var(--text-muted); margin-top:2rem;" class="no-print">
        Powered by <?php echo $site_name; ?>
    </div>
</div>

</body>
</html>
