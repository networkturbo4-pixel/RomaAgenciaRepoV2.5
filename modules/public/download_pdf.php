<?php
// modules/public/download_pdf.php
require_once 'config/database.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$uuid = $_GET['uuid'] ?? '';
if (!$uuid) {
    die("Contrato no especificado.");
}

$stmt = $db->prepare("SELECT * FROM contracts WHERE uuid = ?");
$stmt->execute([$uuid]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract || $contract['status'] !== 'signed') {
    die("Contrato no disponible o no firmado.");
}

// Fetch Global Settings
$global_settings = [];
$stmt_set = $db->query("SELECT * FROM settings");
foreach ($stmt_set->fetchAll() as $row) {
    $global_settings[$row['setting_key']] = $row['setting_value'];
}
$site_name = htmlspecialchars($global_settings['site_name'] ?? 'Nuestra Agencia');

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Helvetica", sans-serif; font-size: 14px; line-height: 1.5; color: #333; }
        .header { text-align: center; margin-bottom: 2rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 1rem; }
        .title { font-size: 20px; font-weight: bold; text-align: center; margin-bottom: 1.5rem; }
        .body-content { margin-bottom: 2rem; text-align: justify; }
        .signature-box { border: 1px solid #10b981; padding: 1rem; margin-top: 2rem; text-align: center; background: #ecfdf5; border-radius: 8px; }
        .cert-box { border: 1px dashed #ccc; padding: 1rem; margin-top: 3rem; font-size: 11px; font-family: monospace; line-height: 1.4; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        <h2>' . $site_name . '</h2>
    </div>
    <div class="title">' . htmlspecialchars($contract['title']) . '</div>
    <div class="body-content">
        ' . $contract['body'] . '
    </div>
    <div class="signature-box">
        <h4 style="color: #059669; margin-top: 0;">DOCUMENTO FIRMADO DIGITALMENTE</h4>
        <img src="' . htmlspecialchars($contract['signature_data']) . '" style="max-height: 100px; margin-bottom: 10px;">
        <div>
            <strong>Firmante:</strong> ' . htmlspecialchars($contract['signer_name']) . '<br>
            <strong>Documento:</strong> ' . htmlspecialchars($contract['signer_document']) . '<br>
            <strong>Fecha de Firma:</strong> ' . date('d/m/Y H:i:s', strtotime($contract['signed_at'])) . '
        </div>
    </div>
    
    <div class="page-break"></div>
    <div class="header">
        <h2>' . $site_name . ' - Anexo de Certificado</h2>
    </div>
    <h3 style="text-align: center;">Certificado de Firma Electrónica</h3>
    <div class="cert-box">
        <strong>ID del Documento:</strong> ' . htmlspecialchars($contract['uuid']) . '<br>
        <strong>Fecha y Hora de Firma:</strong> ' . date('Y-m-d H:i:s', strtotime($contract['signed_at'])) . ' (Local/Servidor)<br>
        <strong>Dirección IP del Firmante:</strong> ' . htmlspecialchars($contract['signer_ip'] ?? 'No registrada') . '<br>
        <strong>Navegador (User-Agent):</strong> ' . htmlspecialchars($contract['signer_user_agent'] ?? 'No registrado') . '<br>
        <strong>Nombre del Firmante:</strong> ' . htmlspecialchars($contract['signer_name']) . '<br>
        <strong>Documento de Identidad:</strong> ' . htmlspecialchars($contract['signer_document']) . '<br>
        <strong>Huella Digital (SHA-256):</strong> ' . hash('sha256', $contract['signature_data']) . '<br>
    </div>
    <p style="font-size: 10px; text-align: center; color: #666; margin-top: 1rem;">
        Este anexo certifica la recolección de evidencia electrónica al momento de realizarse la firma del documento. Documento encriptado y protegido contra modificaciones.
    </p>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Encriptar el PDF para que no pueda ser modificado (solo lectura/impresión)
$dompdf->getCanvas()->get_cpdf()->setEncryption('', '', ['print']);

$dompdf->stream("Contrato_" . date('Ymd_His') . ".pdf", ["Attachment" => true]);
