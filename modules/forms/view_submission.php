<?php
// modules/forms/view_submission.php — Public view of a filled brief (same design as PDF)
require_once '../../config/database.php';
$db = (new Database())->getConnection();

$correlativo = $_GET['ref'] ?? '';
$id = $_GET['id'] ?? '';

if (empty($correlativo) && empty($id)) { die("Referencia no válida."); }

if (!empty($id)) {
    $stmt = $db->prepare("SELECT s.*, t.title as form_title, t.fields_json, t.settings_json 
        FROM form_submissions s 
        JOIN form_templates t ON s.template_id = t.id 
        WHERE s.id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $db->prepare("SELECT s.*, t.title as form_title, t.fields_json, t.settings_json 
        FROM form_submissions s 
        JOIN form_templates t ON s.template_id = t.id 
        WHERE s.correlativo = ?");
    $stmt->execute([$correlativo]);
}

$sub = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sub) { die("Brief no encontrado."); }

$fields = json_decode($sub['fields_json'] ?: '[]', true);
$data = json_decode($sub['data_json'] ?: '{}', true);
$settings = json_decode($sub['settings_json'] ?: '{}', true);
$brandColor = $global_settings['primary_color'] ?? '#03624c';
$siteName = $global_settings['site_name'] ?? 'Roma Agencia';
$logoUrl = $global_settings['logo_light'] ?? '';
$dateFormatted = date('d \d\e F \d\e Y, H:i', strtotime($sub['created_at']));

// Spanish month names
$meses_en = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$meses_es = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$dateFormatted = str_replace($meses_en, $meses_es, $dateFormatted);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($sub['correlativo']); ?> — <?php echo htmlspecialchars($sub['form_title']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f1f5f9;color:#1e293b;min-height:100vh}
.brief-page{max-width:780px;margin:0 auto;padding:0 1rem 3rem}

/* Header Banner */
.brief-header{background:linear-gradient(135deg,<?php echo $brandColor; ?>,#065f46);padding:48px 40px 40px;color:white;position:relative;overflow:hidden;border-radius:0 0 24px 24px;margin-bottom:2rem}
.brief-header::before{content:'';position:absolute;top:-40px;right:-40px;width:200px;height:200px;background:rgba(255,255,255,.06);border-radius:50%}
.brief-header::after{content:'';position:absolute;bottom:-60px;right:100px;width:140px;height:140px;background:rgba(255,255,255,.04);border-radius:50%}
.brief-header img{height:32px;margin-bottom:16px;filter:brightness(0) invert(1)}
.brief-header h1{font-size:1.8rem;font-weight:800;margin-bottom:6px;letter-spacing:-.5px;position:relative;z-index:1}
.brief-header p{font-size:.95rem;opacity:.8;font-weight:400;position:relative;z-index:1}

/* Info Cards */
.info-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:1.5rem}
.info-card{background:white;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;transition:transform .15s,box-shadow .15s}
.info-card:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.06)}
.info-card-label{font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:6px}
.info-card-value{font-size:1rem;font-weight:700;color:#1e293b}
.info-card-value.accent{color:<?php echo $brandColor; ?>;font-size:1.15rem}

/* Respondent Card */
.respondent-card{background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border:1px solid #bbf7d0;border-radius:14px;padding:20px 24px;margin-bottom:1.5rem;display:flex;align-items:center;gap:18px}
.respondent-avatar{width:56px;height:56px;background:<?php echo $brandColor; ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1.4rem;font-weight:800;flex-shrink:0;box-shadow:0 4px 12px rgba(3,98,76,.2)}
.respondent-info h3{font-size:1.05rem;font-weight:700;color:#1e293b;margin-bottom:2px}
.respondent-info p{font-size:.85rem;color:#64748b}

/* Fields Table */
.fields-card{background:white;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;margin-bottom:1.5rem}
.fields-section-header{background:<?php echo $brandColor; ?>;color:white;padding:12px 24px;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;display:flex;align-items:center;gap:8px}
.fields-section-header i{font-size:1rem}
.field-row{display:flex;border-bottom:1px solid #f1f5f9;transition:background .15s}
.field-row:last-child{border-bottom:none}
.field-row:hover{background:#f8fafc}
.field-label{width:38%;padding:14px 24px;font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;line-height:1.7;display:flex;align-items:flex-start;gap:4px}
.field-label .req{color:#ef4444;font-size:.8rem}
.field-value{width:62%;padding:14px 24px;font-size:.9rem;color:#1e293b;line-height:1.7;word-break:break-word}
.field-value a{color:<?php echo $brandColor; ?>;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.field-value a:hover{text-decoration:underline}
.field-value.empty{color:#cbd5e1;font-style:italic}
.field-divider{background:<?php echo $brandColor; ?>;color:white;padding:11px 24px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px}
.field-row-alt{background:#f8fafc}

/* Status Badge */
.status-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.status-nuevo{background:rgba(59,130,246,.1);color:#2563eb}
.status-revisado{background:rgba(16,185,129,.1);color:#059669}
.status-archivado{background:rgba(100,116,139,.1);color:#64748b}

/* Footer */
.brief-footer{display:flex;justify-content:space-between;align-items:center;padding:20px 0;border-top:1px solid #e2e8f0;font-size:.75rem;color:#94a3b8}
.brief-footer a{color:<?php echo $brandColor; ?>;text-decoration:none;font-weight:600}

/* Print Button */
.print-btn{position:fixed;bottom:24px;right:24px;background:<?php echo $brandColor; ?>;color:white;border:none;padding:14px 24px;border-radius:14px;font-weight:700;font-size:.85rem;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:8px;box-shadow:0 8px 24px rgba(3,98,76,.25);transition:all .2s;z-index:10}
.print-btn:hover{transform:translateY(-2px);box-shadow:0 12px 32px rgba(3,98,76,.35)}

@media print{
    body{background:white}
    .brief-header{border-radius:0;margin-bottom:1rem}
    .print-btn{display:none!important}
    .info-card:hover,.field-row:hover{transform:none;box-shadow:none;background:inherit}
    @page{margin:0;size:A4}
}
@media(max-width:600px){
    .brief-header{padding:32px 24px 28px;border-radius:0 0 16px 16px}
    .brief-header h1{font-size:1.4rem}
    .field-label,.field-value{padding:10px 16px}
    .field-label{width:100%;padding-bottom:0}
    .field-value{width:100%}
    .field-row{flex-direction:column}
    .info-cards{grid-template-columns:1fr 1fr}
    .respondent-card{padding:16px}
}
</style>
</head>
<body>

<div class="brief-page">
    <!-- Header -->
    <div class="brief-header">
        <?php if(!empty($logoUrl)): ?>
        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo">
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($sub['form_title']); ?></h1>
        <p>Brief de servicio</p>
    </div>

    <!-- Info Cards -->
    <div class="info-cards">
        <div class="info-card">
            <div class="info-card-label">Correlativo</div>
            <div class="info-card-value accent"><?php echo htmlspecialchars($sub['correlativo']); ?></div>
        </div>
        <div class="info-card">
            <div class="info-card-label">Fecha de envío</div>
            <div class="info-card-value"><?php echo $dateFormatted; ?></div>
        </div>
        <div class="info-card">
            <div class="info-card-label">Período</div>
            <div class="info-card-value"><?php echo htmlspecialchars($sub['submission_month']); ?></div>
        </div>
        <div class="info-card">
            <div class="info-card-label">Estado</div>
            <div class="info-card-value"><span class="status-badge status-<?php echo $sub['status']; ?>"><?php echo ucfirst($sub['status']); ?></span></div>
        </div>
    </div>

    <!-- Respondent -->
    <?php if(!empty($sub['respondent_name']) || !empty($sub['respondent_email'])): ?>
    <div class="respondent-card">
        <div class="respondent-avatar"><?php echo strtoupper(mb_substr($sub['respondent_name'] ?: '?', 0, 1)); ?></div>
        <div class="respondent-info">
            <?php if($sub['respondent_name']): ?><h3><?php echo htmlspecialchars($sub['respondent_name']); ?></h3><?php endif; ?>
            <?php if($sub['respondent_email']): ?><p><?php echo htmlspecialchars($sub['respondent_email']); ?></p><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fields -->
    <div class="fields-card">
        <div class="fields-section-header"><i class="ph ph-clipboard-text"></i> Respuestas del Brief</div>
        <?php 
        $rowIdx = 0;
        foreach($fields as $f): 
            if($f['type'] === 'divider'): ?>
                <div class="field-divider"><?php echo htmlspecialchars($f['label']); ?></div>
            <?php $rowIdx = 0; continue; endif;
            
            $key = 'field_' . $f['id'];
            $val = $data[$key] ?? null;
            if(is_array($val)) $val = implode(', ', $val);
            if(!$val && isset($data[$key . '[]'])) {
                $val = is_array($data[$key.'[]']) ? implode(', ', $data[$key.'[]']) : $data[$key.'[]'];
            }
            $driveUrl = $data[$key . '_drive_url'] ?? $data['file_' . $f['id'] . '_drive_url'] ?? '';
            $fileName = $data[$key . '_file_name'] ?? $data['file_' . $f['id'] . '_file_name'] ?? '';
        ?>
        <div class="field-row <?php echo $rowIdx % 2 !== 0 ? 'field-row-alt' : ''; ?>">
            <div class="field-label">
                <?php echo htmlspecialchars($f['label']); ?>
                <?php if(!empty($f['required'])): ?><span class="req">*</span><?php endif; ?>
            </div>
            <div class="field-value <?php echo empty($val) && empty($driveUrl) ? 'empty' : ''; ?>">
                <?php if($f['type'] === 'file' && !empty($driveUrl)): ?>
                    <?php 
                        $urls = is_array($driveUrl) ? $driveUrl : [$driveUrl];
                        $names = is_array($fileName) ? $fileName : [$fileName];
                        foreach($urls as $idx => $url):
                            $fn = $names[$idx] ?? 'Ver archivo';
                    ?>
                        <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" style="display:inline-flex; align-items:center; gap:6px; margin-bottom:4px; margin-right:12px; background:rgba(3,98,76,0.05); padding:6px 12px; border-radius:8px; border:1px solid rgba(3,98,76,0.1);">
                            <i class="ph-fill ph-file-pdf" style="color:#ef4444; font-size:1.1rem;"></i> 
                            <?php echo htmlspecialchars($fn); ?>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php echo htmlspecialchars($val ?: 'Sin respuesta'); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php $rowIdx++; endforeach; ?>
    </div>

    <!-- Footer -->
    <div class="brief-footer">
        <div>Generado por <?php echo htmlspecialchars($siteName); ?> • Documento confidencial</div>
        <div><?php echo date('Y'); ?></div>
    </div>
</div>

<!-- Print/Download Button -->
<button class="print-btn" onclick="window.print()">
    <i class="ph ph-printer"></i> Imprimir / PDF
</button>

</body>
</html>
