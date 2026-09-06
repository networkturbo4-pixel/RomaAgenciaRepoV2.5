<?php
// modules/forms/view_submission.php — Modern Public View of a submitted brief
if (!isset($db)) {
    require_once __DIR__ . '/../../config/database.php';
    $db = (new Database())->getConnection();
}

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
$brandColor = $global_settings['primary_color'] ?? '#4f46e5';
$siteName = $global_settings['site_name'] ?? 'Roma Agencia';
$logoUrl = $global_settings['logo_light'] ?? '';
$dateFormatted = date('d \d\e F \d\e Y, H:i', strtotime($sub['created_at']));

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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web"></script>
<style>
:root {
    --primary-color: <?php echo $brandColor; ?>;
    --primary-light: color-mix(in srgb, var(--primary-color) 10%, transparent);
    --bg-page: #f8fafc;
    --bg-card: #ffffff;
    --border-color: #e2e8f0;
    --text-title: #0f172a;
    --text-body: #334155;
    --text-muted: #64748b;
}

@media (prefers-color-scheme: dark) {
    :root {
        --bg-page: #09090b;
        --bg-card: #18181b;
        --border-color: #27272a;
        --text-title: #f8fafc;
        --text-body: #e2e8f0;
        --text-muted: #94a3b8;
    }
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--bg-page);
    color: var(--text-body);
    min-height: 100vh;
    font-size: 13px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}

.brief-page {
    max-width: 780px;
    margin: 0 auto;
    padding: 1.5rem 1.25rem 4rem;
}

/* Header Banner */
.brief-header {
    background: linear-gradient(135deg, var(--primary-color), color-mix(in srgb, var(--primary-color) 70%, black));
    padding: 2.5rem 2rem;
    color: #ffffff;
    border-radius: 20px;
    margin-bottom: 1.5rem;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
}

.brief-header img {
    height: 32px;
    margin-bottom: 1rem;
    filter: brightness(0) invert(1);
    display: block;
}

.brief-header h1 {
    font-size: 1.6rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
    line-height: 1.3;
}

.brief-header p {
    font-size: 0.875rem;
    opacity: 0.85;
}

/* Info Cards Grid */
.info-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px;
    margin-bottom: 1.25rem;
}

.info-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1rem 1.25rem;
}

.info-card-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 4px;
}

.info-card-value {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-title);
}

.info-card-value.accent {
    color: var(--primary-color);
    font-family: monospace;
}

/* Respondent Card */
.respondent-card {
    background: var(--primary-light);
    border: 1px solid color-mix(in srgb, var(--primary-color) 20%, transparent);
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.respondent-avatar {
    width: 46px;
    height: 46px;
    background: var(--primary-color);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 1.2rem;
    font-weight: 800;
    flex-shrink: 0;
}

.respondent-info h3 {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-title);
    margin-bottom: 2px;
}

.respondent-info p {
    font-size: 0.8125rem;
    color: var(--text-muted);
}

/* Fields Table */
.fields-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.fields-section-header {
    background: var(--primary-color);
    color: #ffffff;
    padding: 0.75rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.field-row {
    display: flex;
    border-bottom: 1px solid var(--border-color);
}

.field-row:last-child {
    border-bottom: none;
}

.field-label {
    width: 36%;
    padding: 0.9rem 1.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    line-height: 1.5;
}

.field-value {
    width: 64%;
    padding: 0.9rem 1.25rem;
    font-size: 0.875rem;
    color: var(--text-title);
    line-height: 1.5;
    word-break: break-word;
    font-weight: 500;
}

.field-divider {
    background: var(--primary-color);
    color: #ffffff;
    padding: 0.6rem 1.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

/* Compare Tool View */
.view-compare-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.75rem;
    width: 100%;
}
.view-compare-card {
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 0.75rem;
    background: var(--bg-card);
    position: relative;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.view-compare-card.is-selected {
    border-color: var(--primary-color);
    background: var(--primary-light);
}
.view-compare-card.is-correct {
    border-color: #10b981;
}
.view-compare-badges {
    display: flex;
    gap: 4px;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
}
.view-badge-selected {
    background: var(--primary-color);
    color: #fff;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.view-badge-correct {
    background: #10b981;
    color: #fff;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.view-compare-img {
    width: 100%;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}
.view-compare-icon {
    font-size: 2.2rem;
    color: var(--primary-color);
    text-align: center;
    padding: 0.75rem 0;
}
.view-compare-title {
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--text-title);
}
.view-compare-desc {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Print Button */
.print-btn {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--primary-color);
    color: #ffffff;
    border: none;
    padding: 0.75rem 1.25rem;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    transition: all 0.2s ease;
    z-index: 10;
}

.print-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.08);
}

.brief-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 0;
    border-top: 1px solid var(--border-color);
    font-size: 0.75rem;
    color: var(--text-muted);
}

@media print {
    body { background: #ffffff !important; }
    .print-btn { display: none !important; }
    @page { margin: 0; size: A4; }
}

@media (max-width: 600px) {
    .field-row { flex-direction: column; }
    .field-label, .field-value { width: 100%; padding: 0.6rem 1rem; }
    .field-label { padding-bottom: 0; }
}
</style>
</head>
<body>

<div class="brief-page">
    <div class="brief-header">
        <?php if(!empty($logoUrl)): ?>
        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo">
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($sub['form_title']); ?></h1>
        <p>Brief de servicio registrado</p>
    </div>

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
            <div class="info-card-value"><?php echo ucfirst($sub['status']); ?></div>
        </div>
    </div>

    <?php if(!empty($sub['respondent_name']) || !empty($sub['respondent_email'])): ?>
    <div class="respondent-card">
        <div class="respondent-avatar"><?php echo strtoupper(mb_substr($sub['respondent_name'] ?: '?', 0, 1)); ?></div>
        <div class="respondent-info">
            <?php if($sub['respondent_name']): ?><h3><?php echo htmlspecialchars($sub['respondent_name']); ?></h3><?php endif; ?>
            <?php if($sub['respondent_email']): ?><p><?php echo htmlspecialchars($sub['respondent_email']); ?></p><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="fields-card">
        <div class="fields-section-header">
            <i class="ph-bold ph-clipboard-text"></i> Respuestas del Brief
        </div>
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
        <div class="field-row">
            <div class="field-label">
                <?php echo htmlspecialchars($f['label']); ?>
            </div>
            <div class="field-value">
                <?php if($f['type'] === 'file' && !empty($driveUrl)): ?>
                    <?php 
                        $urls = is_array($driveUrl) ? $driveUrl : [$driveUrl];
                        $names = is_array($fileName) ? $fileName : [$fileName];
                        foreach($urls as $idx => $url):
                            $fn = $names[$idx] ?? 'Ver archivo';
                    ?>
                        <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" style="color: var(--primary-color); font-weight:600; display:inline-flex; align-items:center; gap:6px; margin:2px 8px 2px 0; background:var(--primary-light); padding:6px 10px; border-radius:8px; text-decoration:none;">
                            <i class="ph-bold ph-paperclip"></i> 
                            <?php echo htmlspecialchars($fn); ?>
                        </a>
                    <?php endforeach; ?>
                <?php elseif($f['type'] === 'image_compare' && !empty($f['compare_options'])): ?>
                    <?php 
                        $selectedVals = is_array($val) ? $val : (is_string($val) ? array_map('trim', explode(',', $val)) : []);
                    ?>
                    <div class="view-compare-grid">
                        <?php foreach($f['compare_options'] as $opt): 
                            $optTitle = $opt['title'] ?: 'Opción';
                            $isSelected = in_array($optTitle, $selectedVals);
                            if(!$isSelected && empty($opt['is_correct'])) continue;
                        ?>
                        <div class="view-compare-card <?php echo $isSelected ? 'is-selected' : ''; ?> <?php echo !empty($opt['is_correct']) ? 'is-correct' : ''; ?>">
                            <div class="view-compare-badges">
                                <?php if($isSelected): ?>
                                    <span class="view-badge-selected"><i class="ph-bold ph-check"></i> Elegida</span>
                                <?php endif; ?>
                                <?php if(!empty($opt['is_correct'])): ?>
                                    <span class="view-badge-correct"><i class="ph-bold ph-seal-check"></i> Correcta</span>
                                <?php endif; ?>
                            </div>
                            <?php if(($opt['opt_type'] ?? 'image') === 'image' && !empty($opt['image'])): ?>
                                <img src="<?php echo htmlspecialchars($opt['image']); ?>" alt="<?php echo htmlspecialchars($optTitle); ?>" class="view-compare-img">
                            <?php elseif(($opt['opt_type'] ?? '') === 'icon' && !empty($opt['icon'])): ?>
                                <div class="view-compare-icon"><i class="<?php echo htmlspecialchars($opt['icon']); ?>"></i></div>
                            <?php endif; ?>
                            <div class="view-compare-info">
                                <div class="view-compare-title"><?php echo htmlspecialchars($optTitle); ?></div>
                                <?php if(!empty($opt['desc'])): ?>
                                    <div class="view-compare-desc"><?php echo htmlspecialchars($opt['desc']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php echo htmlspecialchars($val ?: '—'); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php $rowIdx++; endforeach; ?>
    </div>

    <div class="brief-footer">
        <div>Generado por <?php echo htmlspecialchars($siteName); ?></div>
        <div><?php echo date('Y'); ?></div>
    </div>
</div>

<button class="print-btn" onclick="window.print()">
    <i class="ph-bold ph-printer"></i> Imprimir / Guardar PDF
</button>

</body>
</html>

