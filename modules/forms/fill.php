<?php
// modules/forms/fill.php — Public form fill page (Modern App UI)
$token = $_GET['token'] ?? '';
if (empty($token)) { die("Enlace inválido."); }

$stmt = $db->prepare("SELECT * FROM form_templates WHERE public_token = ? AND status = 'active'");
$stmt->execute([$token]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$form) { die("Formulario no encontrado o no está activo."); }

$fields = json_decode($form['fields_json'] ?: '[]', true);
$settings = json_decode($form['settings_json'] ?: '{}', true);
$showLogo = $settings['show_logo'] ?? true;
$reqName = $settings['require_name'] ?? true;
$reqEmail = $settings['require_email'] ?? true;

$logoUrl = $global_settings['logo_light'] ?? '';
$siteName = $global_settings['site_name'] ?? 'Roma Agencia';
$primaryColor = $global_settings['primary_color'] ?? '#4f46e5';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($form['title']); ?> | <?php echo htmlspecialchars($siteName); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web"></script>
<style>
:root {
    --primary-color: <?php echo $primaryColor; ?>;
    --primary-hover: color-mix(in srgb, var(--primary-color) 88%, black);
    --primary-light: color-mix(in srgb, var(--primary-color) 12%, transparent);
    --primary-glow: color-mix(in srgb, var(--primary-color) 30%, transparent);
    
    --bg-page: #f8fafc;
    --bg-card: #ffffff;
    --border-color: #e2e8f0;
    --text-title: #0f172a;
    --text-body: #334155;
    --text-muted: #64748b;
    --input-bg: #f8fafc;
}

@media (prefers-color-scheme: dark) {
    :root {
        --bg-page: #09090b;
        --bg-card: #18181b;
        --border-color: #27272a;
        --text-title: #f8fafc;
        --text-body: #e2e8f0;
        --text-muted: #94a3b8;
        --input-bg: #121214;
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

.fill-wrapper {
    max-width: 680px;
    margin: 0 auto;
    padding: 2rem 1.25rem 4rem;
}

/* Floating Top Progress Bar */
.fill-progress-bar-wrap {
    position: sticky;
    top: 0;
    z-index: 50;
    background: color-mix(in srgb, var(--bg-page) 85%, transparent);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    margin: -2rem -1.25rem 1.5rem;
    padding: 0.75rem 1.25rem 0.5rem;
}

.fill-progress-track {
    height: 6px;
    background: var(--border-color);
    border-radius: 99px;
    overflow: hidden;
    position: relative;
}

.fill-progress-fill {
    height: 100%;
    background: var(--primary-color);
    border-radius: 99px;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 0 10px var(--primary-glow);
}

/* Hero Header Card */
.fill-hero-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 2.25rem 2rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    position: relative;
    overflow: hidden;
    border-top: 6px solid var(--primary-color);
}

.fill-hero-logo {
    max-height: 52px;
    max-width: 180px;
    object-fit: contain;
    margin-bottom: 1.25rem;
    display: block;
}

.fill-hero-title {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-title);
    line-height: 1.25;
    margin-bottom: 0.5rem;
}

.fill-hero-desc {
    font-size: 0.875rem;
    color: var(--text-muted);
    line-height: 1.6;
}

/* Question Cards */
.fill-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 18px;
    padding: 1.5rem 1.75rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.fill-card:focus-within {
    border-color: color-mix(in srgb, var(--primary-color) 45%, var(--border-color));
    box-shadow: 0 6px 24px var(--primary-light);
}

.fill-label {
    display: block;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-title);
    margin-bottom: 0.75rem;
    line-height: 1.4;
}

.fill-label .req-star {
    color: #ef4444;
    margin-left: 0.2rem;
}

/* Input Styles */
.fill-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1.5px solid var(--border-color);
    border-radius: 12px;
    font-size: 0.875rem;
    font-family: inherit;
    background: var(--input-bg);
    color: var(--text-title);
    transition: all 0.2s ease;
    outline: none;
}

.fill-input:focus {
    border-color: var(--primary-color);
    background: var(--bg-card);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.fill-textarea {
    min-height: 110px;
    resize: vertical;
}

/* Radio & Checkbox Groups */
.fill-options-group {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.fill-opt-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border: 1.5px solid var(--border-color);
    border-radius: 12px;
    background: var(--input-bg);
    color: var(--text-title);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
}

.fill-opt-label:hover {
    border-color: var(--primary-color);
    background: var(--primary-light);
}

.fill-opt-label.selected {
    border-color: var(--primary-color);
    background: var(--primary-light);
    color: var(--primary-color);
    font-weight: 600;
}

.fill-opt-label input[type="radio"],
.fill-opt-label input[type="checkbox"] {
    accent-color: var(--primary-color);
    width: 18px;
    height: 18px;
    cursor: pointer;
}

/* File Upload Dropzone */
.fill-file-dropzone {
    border: 2px dashed var(--border-color);
    border-radius: 14px;
    padding: 2rem 1.5rem;
    text-align: center;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    background: var(--input-bg);
}

.fill-file-dropzone:hover {
    border-color: var(--primary-color);
    background: var(--primary-light);
}

.fill-file-dropzone i {
    font-size: 2.2rem;
    color: var(--primary-color);
    display: block;
    margin-bottom: 0.5rem;
}

.fill-file-dropzone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 10;
    width: 100%;
    height: 100%;
}

/* Scale Rating Group */
.fill-scale-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding: 0.5rem 0;
}

.fill-scale-item {
    cursor: pointer;
}

.fill-scale-item input {
    display: none;
}

.fill-scale-pill {
    width: 44px;
    height: 44px;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-title);
    background: var(--input-bg);
    transition: all 0.15s ease;
}

.fill-scale-item:hover .fill-scale-pill {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.fill-scale-item input:checked + .fill-scale-pill {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: #ffffff;
    transform: scale(1.08);
    box-shadow: 0 4px 14px var(--primary-glow);
}

/* Number Range */
.fill-range-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Color Swatches */
.fill-color-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}

.fill-color-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
}

.fill-color-swatch {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    border: 2px solid var(--border-color);
    padding: 3px;
    cursor: pointer;
    background: var(--bg-card);
    transition: transform 0.15s ease;
}

.fill-color-swatch:hover {
    transform: scale(1.06);
    border-color: var(--primary-color);
}

/* Interactive Icon Cards */
.fill-icon-cards-grid {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.fill-icon-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.9rem 1.1rem;
    border: 1.5px solid var(--border-color);
    border-radius: 14px;
    background: var(--input-bg);
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
}

.fill-icon-card:hover {
    border-color: var(--primary-color);
    background: var(--primary-light);
}

.fill-icon-card.selected {
    border-color: var(--primary-color);
    background: var(--primary-light);
    box-shadow: 0 4px 16px var(--primary-light);
}

.fill-icon-badge {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: var(--primary-light);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.fill-icon-card.selected .fill-icon-badge {
    background: var(--primary-color);
    color: #ffffff;
}

.fill-icon-text {
    flex: 1;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-title);
}

.fill-icon-check {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: transparent;
    transition: all 0.2s ease;
}

.fill-icon-card.selected .fill-icon-check {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: #ffffff;
}

/* Action Buttons */
.fill-actions-row {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.fill-btn-submit {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.9rem 1.5rem;
    background: var(--primary-color);
    color: #ffffff;
    border: none;
    border-radius: 14px;
    font-size: 0.95rem;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 16px var(--primary-glow);
}

.fill-btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px var(--primary-glow);
    filter: brightness(1.08);
}

.fill-btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.fill-btn-prev {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.9rem 1.25rem;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    color: var(--text-title);
    border-radius: 14px;
    font-size: 0.9rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.15s ease;
}

.fill-btn-prev:hover {
    border-color: var(--text-title);
}

/* Section Header */
.fill-section-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-title);
    margin-bottom: 0.35rem;
}

/* Success Screen */
.fill-success-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 3.5rem 2rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
}

.fill-success-icon {
    width: 72px;
    height: 72px;
    border-radius: 20px;
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    margin-bottom: 1.25rem;
}

.fill-success-card h2 {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--text-title);
    margin-bottom: 0.5rem;
}

.fill-success-card p {
    color: var(--text-muted);
    font-size: 0.875rem;
    max-width: 440px;
    margin: 0 auto 1.5rem;
    line-height: 1.6;
}

.fill-correlativo-badge {
    display: inline-block;
    background: var(--primary-light);
    color: var(--primary-color);
    font-weight: 700;
    font-size: 0.85rem;
    padding: 0.4rem 0.9rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    font-family: monospace;
}

.fill-footer {
    text-align: center;
    margin-top: 2rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}
</style>
</head>
<body>

<div class="fill-wrapper">
    <!-- Top Floating Progress Bar -->
    <div class="fill-progress-bar-wrap">
        <div class="fill-progress-track">
            <div class="fill-progress-fill" id="progressBar" style="width: 0%;"></div>
        </div>
    </div>

    <!-- Form Hero Card -->
    <div class="fill-hero-card" id="formHero">
        <?php if($showLogo && $logoUrl): ?>
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" class="fill-hero-logo" alt="<?php echo htmlspecialchars($siteName); ?>">
        <?php endif; ?>
        <h1 class="fill-hero-title"><?php echo htmlspecialchars($form['title']); ?></h1>
        <?php if($form['description']): ?>
            <p class="fill-hero-desc"><?php echo htmlspecialchars($form['description']); ?></p>
        <?php endif; ?>
    </div>

    <form id="publicForm" enctype="multipart/form-data">
        <?php
        $isMultiStep = $settings['multi_step'] ?? false;
        $steps = [];
        $currentStep = [];
        if ($reqName || $reqEmail) {
            $currentStep[] = ['type' => 'user_data_magic_field'];
        }
        foreach ($fields as $field) {
            if ($isMultiStep && $field['type'] === 'divider') {
                if (!empty($currentStep)) $steps[] = $currentStep;
                $currentStep = [$field];
            } else {
                $currentStep[] = $field;
            }
        }
        if (!empty($currentStep)) $steps[] = $currentStep;
        if (empty($steps)) $steps[] = [];
        ?>

        <?php foreach($steps as $sIndex => $stepFields): ?>
        <div class="form-step" id="step_<?php echo $sIndex; ?>" style="<?php echo $sIndex === 0 ? '' : 'display:none;'; ?>">
            <?php foreach($stepFields as $field): ?>
                <?php if($field['type'] === 'user_data_magic_field'): ?>
                    <div class="fill-card">
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.35rem;">
                            <i class="ph-bold ph-user-circle" style="color: var(--primary-color); font-size: 1rem;"></i> Datos de Contacto
                        </div>
                        <?php if($reqName): ?>
                        <div style="margin-bottom: 1rem;">
                            <label class="fill-label">Tu nombre completo <span class="req-star">*</span></label>
                            <input type="text" name="respondent_name" class="fill-input" placeholder="Ingresa tu nombre..." required>
                        </div>
                        <?php endif; ?>
                        <?php if($reqEmail): ?>
                        <div>
                            <label class="fill-label">Correo electrónico <span class="req-star">*</span></label>
                            <input type="email" name="respondent_email" class="fill-input" placeholder="tu@empresa.com" required>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php elseif($field['type'] === 'divider'): ?>
                    <div class="fill-card" style="border-top: 4px solid var(--primary-color);">
                        <h3 class="fill-section-title"><?php echo htmlspecialchars($field['label']); ?></h3>
                        <?php if(!empty($field['description'])): ?>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;"><?php echo htmlspecialchars($field['description']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="fill-card">
                        <label class="fill-label">
                            <?php echo htmlspecialchars($field['label']); ?>
                            <?php if(!empty($field['required'])): ?><span class="req-star">*</span><?php endif; ?>
                        </label>

                        <?php if($field['type']==='text' || $field['type']==='email' || $field['type']==='phone' || $field['type']==='date'): ?>
                            <input type="<?php echo $field['type']==='phone'?'tel':$field['type']; ?>" name="field_<?php echo $field['id']; ?>" class="fill-input" placeholder="<?php echo htmlspecialchars($field['placeholder']??''); ?>" <?php echo !empty($field['required'])?'required':''; ?>>

                        <?php elseif($field['type']==='textarea'): ?>
                            <textarea name="field_<?php echo $field['id']; ?>" class="fill-input fill-textarea" placeholder="<?php echo htmlspecialchars($field['placeholder']??''); ?>" <?php echo !empty($field['required'])?'required':''; ?>></textarea>

                        <?php elseif($field['type']==='select' || $field['type']==='checkbox'): ?>
                            <?php 
                                $isMulti = isset($field['is_multi']) ? $field['is_multi'] : ($field['type'] === 'checkbox');
                                $inputType = $isMulti ? 'checkbox' : 'radio';
                            ?>
                            <div class="fill-options-group">
                                <?php foreach(($field['options']??[]) as $opt): ?>
                                    <?php $isOther = ($opt === 'Otro'); ?>
                                    <label class="fill-opt-label">
                                        <input type="<?php echo $inputType; ?>" name="field_<?php echo $field['id']; ?><?php echo $isMulti ? '[]' : ''; ?>" value="<?php echo htmlspecialchars($opt); ?>" <?php echo !empty($field['required']) && !$isOther ? 'required data-req="true"' : ''; ?> onchange="handleOptionChange(this)">
                                        <span><?php echo htmlspecialchars($opt); ?></span>
                                        <?php if($isOther): ?>
                                            <input type="text" name="field_<?php echo $field['id']; ?>_other" class="fill-input other-input" style="display:none; margin-left: 0.5rem; padding: 0.35rem 0.6rem; width: auto; flex: 1;" placeholder="Escribe aquí...">
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif($field['type']==='dropdown'): ?>
                            <?php $isMulti = !empty($field['is_multi']); ?>
                            <select name="field_<?php echo $field['id']; ?><?php echo $isMulti ? '[]' : ''; ?>" class="fill-input" <?php echo !empty($field['required'])?'required':''; ?> <?php echo $isMulti ? 'multiple style="height:auto"' : ''; ?>>
                                <?php if(!$isMulti): ?><option value="">Selecciona una opción...</option><?php endif; ?>
                                <?php foreach(($field['options']??[]) as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif($field['type']==='file'): ?>
                            <?php 
                                $maxCount = $field['file_max_count'] ?? 1;
                                $maxSize = $field['file_max_size'] ?? 10;
                                $accept = '';
                                if(!empty($field['file_restrict']) && !empty($field['file_types'])) {
                                    $mimes = [];
                                    foreach($field['file_types'] as $t) {
                                        if($t === 'Documento') $mimes[] = '.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt';
                                        if($t === 'PDF') $mimes[] = '.pdf';
                                        if($t === 'Imagen') $mimes[] = 'image/*';
                                        if($t === 'Video') $mimes[] = 'video/*';
                                        if($t === 'Audio') $mimes[] = 'audio/*';
                                    }
                                    $accept = 'accept="' . implode(',', $mimes) . '"';
                                }
                                $isMultiple = $maxCount > 1;
                            ?>
                            <div class="fill-file-dropzone" id="fz_<?php echo $field['id']; ?>">
                                <i class="ph-bold ph-cloud-arrow-up"></i>
                                <div style="font-weight: 600; color: var(--text-title); margin-bottom: 2px;">Toca o arrastra tus archivos aquí</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Máximo <?php echo $maxSize; ?> MB <?php echo $isMultiple ? '('.$maxCount.' archivos)' : ''; ?></div>
                                <div id="fn_<?php echo $field['id']; ?>" style="margin-top: 10px;"></div>
                                <input type="file" name="file_<?php echo $field['id']; ?><?php echo $isMultiple ? '[]' : ''; ?>" <?php echo $accept; ?> <?php echo $isMultiple ? 'multiple' : ''; ?> <?php echo !empty($field['required'])?'required':''; ?> onchange="handleAsyncUpload(this, '<?php echo $field['id']; ?>', <?php echo $maxSize; ?>, <?php echo $maxCount; ?>)">
                            </div>

                        <?php elseif($field['type']==='range'): ?>
                            <?php $mn=$field['range_min']??1; $mx=$field['range_max']??5; $lMin=$field['range_label_min']??''; $lMax=$field['range_label_max']??''; ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem; color:var(--text-muted); margin-bottom:0.4rem;">
                                <span><?php echo htmlspecialchars($lMin); ?></span>
                                <span><?php echo htmlspecialchars($lMax); ?></span>
                            </div>
                            <div class="fill-scale-wrap">
                                <?php for($n=$mn; $n<=$mx; $n++): ?>
                                <label class="fill-scale-item">
                                    <input type="radio" name="field_<?php echo $field['id']; ?>" value="<?php echo $n; ?>" <?php echo !empty($field['required'])?'required':''; ?>>
                                    <div class="fill-scale-pill"><?php echo $n; ?></div>
                                </label>
                                <?php endfor; ?>
                            </div>

                        <?php elseif($field['type']==='number_range'): ?>
                            <?php $nrMin=$field['nr_min']??18; $nrMax=$field['nr_max']??65; $nrStep=$field['nr_step']??1; ?>
                            <div class="fill-range-row">
                                <select name="field_<?php echo $field['id']; ?>_from" class="fill-input" <?php echo !empty($field['required'])?'required':''; ?>>
                                    <option value="">Desde</option>
                                    <?php for($n=$nrMin; $n<=$nrMax; $n+=$nrStep): ?>
                                    <option value="<?php echo $n; ?>"><?php echo $n; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span style="font-weight: 700; color: var(--text-muted);">—</span>
                                <select name="field_<?php echo $field['id']; ?>_to" class="fill-input" <?php echo !empty($field['required'])?'required':''; ?>>
                                    <option value="">Hasta</option>
                                    <?php for($n=$nrMin; $n<=$nrMax; $n+=$nrStep): ?>
                                    <option value="<?php echo $n; ?>"><?php echo $n; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                        <?php elseif($field['type']==='color'): ?>
                            <?php $colorOpts=$field['color_options']??['#4f46e5']; ?>
                            <div class="fill-color-grid">
                                <?php foreach($colorOpts as $ci => $color): ?>
                                <div class="fill-color-item">
                                    <input type="color" name="field_<?php echo $field['id']; ?>[]" class="fill-color-swatch" value="<?php echo htmlspecialchars($color); ?>" <?php echo !empty($field['required'])?'required':''; ?>>
                                    <span style="font-size: 0.7rem; color: var(--text-muted); font-family: monospace;"><?php echo htmlspecialchars($color); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif($field['type']==='icon_card'): ?>
                            <?php 
                                $iconOpts=$field['icon_options']??[]; 
                                $isMulti=$field['icon_multi']??false; 
                                $inputType=$isMulti?'checkbox':'radio'; 
                            ?>
                            <div class="fill-icon-cards-grid">
                                <?php foreach($iconOpts as $oi => $opt): ?>
                                <label class="fill-icon-card">
                                    <input type="<?php echo $inputType; ?>" name="field_<?php echo $field['id']; ?><?php echo $isMulti?'[]':''; ?>" value="<?php echo htmlspecialchars($opt['text']); ?>" <?php echo !empty($field['required']) ? 'required data-req="true"' : ''; ?> style="display:none;" onchange="handleIconCardChange(this, <?php echo $isMulti ? 'true' : 'false'; ?>)">
                                    <div class="fill-icon-badge"><i class="ph-bold <?php echo htmlspecialchars($opt['icon']); ?>"></i></div>
                                    <span class="fill-icon-text"><?php echo htmlspecialchars($opt['text']); ?></span>
                                    <div class="fill-icon-check"><i class="ph-bold ph-check" style="font-size: 0.75rem;"></i></div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="fill-actions-row">
                <?php if($isMultiStep && $sIndex > 0): ?>
                    <button type="button" class="fill-btn-prev" onclick="goToStep(<?php echo $sIndex-1; ?>)">
                        <i class="ph-bold ph-arrow-left"></i> Atrás
                    </button>
                <?php endif; ?>

                <?php if($isMultiStep && $sIndex < count($steps) - 1): ?>
                    <button type="button" class="fill-btn-submit" onclick="goToStep(<?php echo $sIndex+1; ?>)">
                        Siguiente <i class="ph-bold ph-arrow-right"></i>
                    </button>
                <?php else: ?>
                    <button type="submit" class="fill-btn-submit" id="submitBtn">
                        <i class="ph-bold ph-paper-plane-tilt"></i> Enviar Formulario
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </form>

    <!-- Success Screen -->
    <div class="fill-success-card" id="successScreen" style="display: none;">
        <div class="fill-success-icon">
            <i class="ph-bold ph-check"></i>
        </div>
        <h2>¡Formulario Enviado con Éxito!</h2>
        <p>Gracias por enviarnos tu información. Hemos recibido todas tus respuestas y nuestro equipo se pondrá en contacto contigo a la brevedad.</p>
        <div class="fill-correlativo-badge" id="successCorrelativo"></div>
    </div>

    <div class="fill-footer">
        Desarrollado con ROMA SaaS
    </div>
</div>

<script>
let currentStepIdx = 0;

function handleOptionChange(input) {
    const group = input.closest('.fill-options-group');
    if (!group) return;
    if (input.type === 'radio') {
        group.querySelectorAll('.fill-opt-label').forEach(lbl => lbl.classList.remove('selected'));
        if (input.checked) input.closest('.fill-opt-label').classList.add('selected');
    } else {
        input.closest('.fill-opt-label').classList.toggle('selected', input.checked);
        const reqBoxes = group.querySelectorAll('input[data-req="true"]');
        const anyChecked = group.querySelectorAll('input[type="checkbox"]:checked').length > 0;
        reqBoxes.forEach(b => b.required = !anyChecked);
    }

    const otherInput = input.closest('.fill-opt-label').querySelector('.other-input');
    if (otherInput) {
        if (input.checked) {
            otherInput.style.display = 'block';
            otherInput.required = true;
            otherInput.focus();
        } else {
            otherInput.style.display = 'none';
            otherInput.required = false;
            otherInput.value = '';
        }
    }
    updateProgress();
}

function handleIconCardChange(input, isMulti) {
    const card = input.closest('.fill-icon-card');
    const container = input.closest('.fill-icon-cards-grid');
    if (!isMulti) {
        container.querySelectorAll('.fill-icon-card').forEach(c => c.classList.remove('selected'));
        if (input.checked) card.classList.add('selected');
    } else {
        card.classList.toggle('selected', input.checked);
        const reqBoxes = container.querySelectorAll('input[data-req="true"]');
        const anyChecked = container.querySelectorAll('input[type="checkbox"]:checked').length > 0;
        reqBoxes.forEach(b => b.required = !anyChecked);
    }
    updateProgress();
}

window.goToStep = function(nextIdx) {
    if (nextIdx > currentStepIdx) {
        const stepEl = document.getElementById('step_' + currentStepIdx);
        if (!stepEl) return;
        const inputs = stepEl.querySelectorAll('input, select, textarea');
        for (let i = 0; i < inputs.length; i++) {
            if (!inputs[i].reportValidity()) return;
        }
    }
    document.getElementById('step_' + currentStepIdx).style.display = 'none';
    document.getElementById('step_' + nextIdx).style.display = 'block';
    
    const hero = document.getElementById('formHero');
    if (hero) hero.style.display = (nextIdx > 0) ? 'none' : 'block';

    currentStepIdx = nextIdx;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    updateProgress();
};

const form = document.getElementById('publicForm');
const totalInputs = form.querySelectorAll('input,textarea,select').length;

function updateProgress() {
    let filled = 0;
    form.querySelectorAll('input,textarea,select').forEach(el => {
        if (el.value.trim() || el.checked) filled++;
    });
    const pct = Math.min(100, Math.round((filled / Math.max(totalInputs, 1)) * 100));
    document.getElementById('progressBar').style.width = pct + '%';
}

form.addEventListener('input', updateProgress);

let pendingUploads = 0;

function handleAsyncUpload(input, fieldId, maxSizeMB, maxCount) {
    const maxBytes = maxSizeMB * 1024 * 1024;
    const files = input.files;
    if (files.length > maxCount) {
        alert('No puedes subir más de ' + maxCount + ' archivo(s).');
        input.value = '';
        document.getElementById('fn_' + fieldId).innerHTML = '';
        return;
    }
    let html = '';
    const validFiles = [];
    for (let i = 0; i < files.length; i++) {
        if (files[i].size > maxBytes) {
            alert('El archivo ' + files[i].name + ' excede el límite de ' + maxSizeMB + ' MB.');
            input.value = '';
            document.getElementById('fn_' + fieldId).innerHTML = '';
            return;
        }
        validFiles.push(files[i]);
        const fName = files[i].name;
        const isImg = files[i].type.startsWith('image/');
        const icon = isImg ? 'ph-image' : (fName.toLowerCase().endsWith('.pdf') ? 'ph-file-pdf' : 'ph-file-text');
        
        html += `
        <div style="display:flex; align-items:center; gap:8px; background:var(--bg-card); padding:8px 12px; border-radius:10px; border:1px solid var(--border-color); margin-bottom:6px; text-align:left;">
            <div style="width:32px; height:32px; border-radius:8px; background:var(--primary-light); color:var(--primary-color); display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                <i class="ph-bold ${icon}"></i>
            </div>
            <div style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:0.8rem; font-weight:600; color:var(--text-title);">
                ${fName}<br>
                <span id="status_${fieldId}_${i}" style="color:#f59e0b; font-size:0.7rem;"><i class="ph-bold ph-spinner ph-spin"></i> Subiendo...</span>
            </div>
            <div style="font-size:0.7rem; color:var(--text-muted);">${(files[i].size/1024/1024).toFixed(1)} MB</div>
        </div>`;
    }

    document.querySelectorAll(`input[name="temp_file_${fieldId}[]"]`).forEach(el => el.remove());
    document.querySelectorAll(`input[name="temp_name_${fieldId}[]"]`).forEach(el => el.remove());
    document.getElementById('fn_' + fieldId).innerHTML = html;

    if (validFiles.length === 0) return;

    const fd = new FormData();
    for (let i = 0; i < validFiles.length; i++) {
        fd.append('file_' + fieldId + '[]', validFiles[i]);
    }

    pendingUploads++;
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Subiendo archivos...';
    }

    fetch('index.php?module=forms&action=ajax_upload_temp', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            let pathsHtml = '';
            res.files.forEach((f, idx) => {
                pathsHtml += `<input type="hidden" name="temp_file_${fieldId}[]" value="${f.tmp_path}">`;
                pathsHtml += `<input type="hidden" name="temp_name_${fieldId}[]" value="${f.original_name}">`;
                const statusEl = document.getElementById(`status_${fieldId}_${idx}`);
                if (statusEl) {
                    statusEl.style.color = '#10b981';
                    statusEl.innerHTML = '<i class="ph-bold ph-check-circle"></i> Listo';
                }
            });
            document.getElementById('fn_' + fieldId).insertAdjacentHTML('beforeend', pathsHtml);
        } else {
            alert('Error al subir archivos: ' + (res.error || 'Desconocido'));
            document.getElementById('fn_' + fieldId).innerHTML = '';
            input.value = '';
        }
    })
    .catch(err => {
        alert('Error de conexión al subir archivos');
        document.getElementById('fn_' + fieldId).innerHTML = '';
        input.value = '';
    })
    .finally(() => {
        pendingUploads--;
        if (pendingUploads <= 0 && submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ph-bold ph-paper-plane-tilt"></i> Enviar Formulario';
        }
    });
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (pendingUploads > 0) {
        alert('Por favor espera a que terminen de subirse los archivos.');
        return;
    }

    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Procesando y enviando...';
    btn.disabled = true;

    const fd = new FormData(form);
    const dataObj = {};
    for (const [key, val] of fd.entries()) {
        if (key.startsWith('field_') || key.startsWith('temp_file_') || key.startsWith('temp_name_')) {
            if (dataObj[key]) {
                if (!Array.isArray(dataObj[key])) dataObj[key] = [dataObj[key]];
                dataObj[key].push(val);
            } else {
                dataObj[key] = val;
            }
        }
    }

    const submitFd = new FormData();
    submitFd.append('token', '<?php echo htmlspecialchars($token); ?>');
    submitFd.append('data_json', JSON.stringify(dataObj));
    submitFd.append('respondent_name', fd.get('respondent_name') || '');
    submitFd.append('respondent_email', fd.get('respondent_email') || '');

    try {
        const res = await fetch('index.php?module=forms&action=ajax_submit_form', { method: 'POST', body: submitFd });
        const data = await res.json();
        if (data.success) {
            form.style.display = 'none';
            document.getElementById('formHero').style.display = 'none';
            document.getElementById('successScreen').style.display = 'block';
            document.getElementById('successCorrelativo').textContent = 'Referencia: ' + data.correlativo;
            document.getElementById('progressBar').style.width = '100%';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            alert(data.error || 'Error al enviar');
            btn.innerHTML = '<i class="ph-bold ph-paper-plane-tilt"></i> Enviar Formulario';
            btn.disabled = false;
        }
    } catch (err) {
        alert('Error de conexión');
        btn.innerHTML = '<i class="ph-bold ph-paper-plane-tilt"></i> Enviar Formulario';
        btn.disabled = false;
    }
});
</script>
</body>
</html>

