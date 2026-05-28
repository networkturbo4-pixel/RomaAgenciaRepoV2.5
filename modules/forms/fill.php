<?php
// modules/forms/fill.php — Public form fill page (no auth required)
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
$primaryColor = $global_settings['primary_color'] ?? '#03624c';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($form['title']); ?> | <?php echo htmlspecialchars($siteName); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 30%,#f0f9ff 100%);min-height:100vh;color:#1f2937}
.form-wrapper{max-width:640px;margin:0 auto;padding:1.5rem 1rem 3rem}
.form-hero{background:white;border-radius:12px;padding:2rem;color:#111827;margin-bottom:1rem;position:relative;overflow:hidden;border:1px solid #e5e7eb;border-top:10px solid <?php echo $primaryColor; ?>;box-shadow:0 2px 10px rgba(0,0,0,.04)}
.form-hero-logo{max-height:80px;margin-bottom:1rem;object-fit:contain}
.form-hero h1{font-size:1.8rem;font-weight:400;margin-bottom:.5rem;line-height:1.2}
.form-hero p{font-size:.95rem;color:#4b5563;line-height:1.5}
.form-card{background:white;border-radius:16px;padding:1.5rem;box-shadow:0 4px 24px rgba(0,0,0,.06);margin-bottom:1rem}
.form-group{margin-bottom:1.25rem}
.form-label{display:block;font-size:.85rem;font-weight:600;color:#374151;margin-bottom:.4rem}
.form-label .req{color:#ef4444;margin-left:.15rem}
.form-input{width:100%;padding:.7rem 1rem;border:1.5px solid #e5e7eb;border-radius:10px;font-size:.9rem;font-family:inherit;background:#fafafa;transition:all .2s;color:#111827}
.form-input:focus{border-color:<?php echo $primaryColor; ?>;outline:none;box-shadow:0 0 0 3px rgba(3,98,76,.1);background:white}
.form-textarea{min-height:100px;resize:vertical}
.form-divider{border:0;border-top:2px solid #e5e7eb;margin:1.5rem 0 .75rem}
.form-section-title{font-size:1.1rem;font-weight:700;color:#111827;margin-bottom:.25rem}
.radio-group,.check-group{display:flex;flex-direction:column;gap:.5rem}
.radio-item,.check-item{display:flex;align-items:center;gap:.6rem;padding:.6rem .8rem;border:1.5px solid #e5e7eb;border-radius:10px;cursor:pointer;transition:all .15s;font-size:.9rem}
.radio-item:hover,.check-item:hover{border-color:<?php echo $primaryColor; ?>;background:rgba(3,98,76,.03)}
.radio-item input:checked+span,.check-item input:checked+span{font-weight:600;color:<?php echo $primaryColor; ?>}
.file-zone{border:2px dashed #d1d5db;border-radius:12px;padding:2rem;text-align:center;color:#9ca3af;cursor:pointer;transition:all .2s;position:relative}
.file-zone:hover{border-color:<?php echo $primaryColor; ?>;background:rgba(3,98,76,.02)}
.file-zone i{font-size:2rem;display:block;margin-bottom:.5rem}
.file-zone input[type="file"]{position:absolute;top:0;left:0;width:100%;height:100%;opacity:0;cursor:pointer;z-index:10;display:block}
.file-zone .file-name{color:<?php echo $primaryColor; ?>;font-weight:600;font-size:.85rem;margin-top:.5rem;position:relative;z-index:20;pointer-events:none}
.scale-group{display:flex;align-items:flex-end;gap:0;justify-content:center}
.scale-label{font-size:.75rem;color:#6b7280;padding:0 .5rem .7rem;text-align:center;max-width:60px}
.scale-item{display:flex;flex-direction:column;align-items:center;cursor:pointer;padding:.4rem .25rem}
.scale-item input{opacity:0;position:absolute;z-index:-1;width:0;height:0}
.scale-item .scale-circle{width:36px;height:36px;border:2px solid #d1d5db;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:600;color:#6b7280;transition:all .2s}
.scale-item:hover .scale-circle{border-color:<?php echo $primaryColor; ?>;color:<?php echo $primaryColor; ?>}
.scale-item input:checked+.scale-circle{background:<?php echo $primaryColor; ?>;border-color:<?php echo $primaryColor; ?>;color:white;transform:scale(1.1);box-shadow:0 3px 10px rgba(3,98,76,.25)}
.range-select-wrap{display:flex;gap:12px;align-items:center}
.range-select-wrap select{flex:1;padding:.7rem 1rem;border:1.5px solid #e5e7eb;border-radius:10px;font-size:.9rem;font-family:inherit;background:#fafafa;color:#111827;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;cursor:pointer;transition:all .2s}
.range-select-wrap select:focus{border-color:<?php echo $primaryColor; ?>;outline:none;box-shadow:0 0 0 3px rgba(3,98,76,.1)}
.range-select-wrap .range-dash{font-size:1.2rem;color:#d1d5db;font-weight:300}
.color-picker-wrap{display:flex;align-items:center;gap:12px}
.color-picker-wrap input[type="color"]{width:56px;height:56px;border:2px solid #e5e7eb;border-radius:12px;cursor:pointer;padding:3px;transition:all .2s}
.color-picker-wrap input[type="color"]:hover{border-color:<?php echo $primaryColor; ?>;transform:scale(1.05)}
.color-picker-wrap .color-hex{font-size:.9rem;font-weight:600;color:#374151;font-family:monospace}
.color-palette{display:flex;flex-wrap:wrap;gap:10px}
.color-swatch{position:relative;cursor:pointer}
.color-swatch input{opacity:0;position:absolute;z-index:-1;width:0;height:0}
.color-swatch .swatch{width:44px;height:44px;border-radius:12px;border:3px solid #e5e7eb;transition:all .2s;display:flex;align-items:center;justify-content:center}
.color-swatch .swatch:hover{transform:scale(1.1);box-shadow:0 4px 12px rgba(0,0,0,.15)}
.color-swatch input:checked+.swatch{border-color:#1e293b;transform:scale(1.1);box-shadow:0 4px 12px rgba(0,0,0,.2)}
.color-swatch input:focus+.swatch{box-shadow:0 0 0 3px rgba(3,98,76,.2)}
.color-swatch .swatch-check{display:none;color:white;font-size:.8rem;filter:drop-shadow(0 1px 2px rgba(0,0,0,.3))}
.color-swatch input:checked+.swatch .swatch-check{display:block}
.color-swatch .swatch-label{font-size:.55rem;text-align:center;margin-top:3px;color:#9ca3af;font-family:monospace}
.icon-card-group{display:flex;flex-direction:column;gap:8px}
.icon-card-item{position:relative;display:flex;align-items:center;gap:12px;padding:14px 16px;border:2px solid #e5e7eb;border-radius:12px;cursor:pointer;transition:all .2s;background:white}
.icon-card-item:hover{border-color:<?php echo $primaryColor; ?>;background:rgba(3,98,76,.02)}
.icon-card-item.selected{border-color:<?php echo $primaryColor; ?>;background:rgba(3,98,76,.04);box-shadow:0 2px 8px rgba(3,98,76,.1)}
.icon-card-item input{opacity:0;position:absolute;z-index:-1;width:0;height:0}
.icon-card-icon{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,rgba(3,98,76,.08),rgba(3,98,76,.15));display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:<?php echo $primaryColor; ?>;flex-shrink:0;transition:all .2s}
.icon-card-item.selected .icon-card-icon{background:<?php echo $primaryColor; ?>;color:white}
.icon-card-text{font-size:.9rem;font-weight:500;color:#374151}
.icon-card-check{margin-left:auto;width:22px;height:22px;border:2px solid #d1d5db;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0}
.icon-card-item.selected .icon-card-check{background:<?php echo $primaryColor; ?>;border-color:<?php echo $primaryColor; ?>;color:white}
.submit-btn{width:100%;padding:.85rem;background:<?php echo $primaryColor; ?>;color:white;border:none;border-radius:12px;font-weight:700;font-size:1rem;cursor:pointer;font-family:inherit;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:.5rem}
.submit-btn:hover{filter:brightness(1.1);transform:translateY(-1px);box-shadow:0 6px 20px rgba(3,98,76,.25)}
.submit-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none}
.btn-prev{padding:.85rem 1.5rem;background:#f3f4f6;color:#4b5563;border:none;border-radius:12px;font-weight:700;font-size:1rem;cursor:pointer;font-family:inherit;transition:all .2s;}
.btn-prev:hover{background:#e5e7eb}
.form-footer{text-align:center;padding:1.5rem;font-size:.75rem;color:#9ca3af}
.success-screen{text-align:center;padding:3rem 1.5rem}
.success-icon{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);color:white;display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 1.5rem;animation:pop .4s ease}
@keyframes pop{0%{transform:scale(0)}50%{transform:scale(1.15)}100%{transform:scale(1)}}
.success-screen h2{font-size:1.4rem;font-weight:800;color:#111827;margin-bottom:.5rem}
.success-screen p{color:#6b7280;line-height:1.5}
.progress-bar{height:4px;background:#e5e7eb;border-radius:2px;margin-top:1rem;overflow:hidden}
.progress-fill{height:100%;background:<?php echo $primaryColor; ?>;border-radius:2px;transition:width .3s}
@media(max-width:500px){.form-wrapper{padding:1rem .75rem 2rem}.form-hero{padding:1.5rem;border-radius:16px}.form-hero h1{font-size:1.25rem}}
</style>
</head>
<body>
<div class="form-wrapper">
    <div class="form-hero" id="formHero">
        <?php if($showLogo && $logoUrl): ?><img src="<?php echo htmlspecialchars($logoUrl); ?>" class="form-hero-logo" alt="Logo"><?php endif; ?>
        <h1><?php echo htmlspecialchars($form['title']); ?></h1>
        <?php if($form['description']): ?><p><?php echo htmlspecialchars($form['description']); ?></p><?php endif; ?>
        <div class="progress-bar"><div class="progress-fill" id="progressBar" style="width:0%"></div></div>
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
        <div class="form-step" id="step_<?php echo $sIndex; ?>" style="<?php echo $sIndex===0 ? '' : 'display:none;'; ?>">
            <?php foreach($stepFields as $field): ?>
                <?php if($field['type'] === 'user_data_magic_field'): ?>
                    <div class="form-card">
                    <div style="font-size:.8rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1rem"><i class="ph ph-user"></i> Tus datos</div>
                    <?php if($reqName): ?>
                    <div class="form-group"><label class="form-label">Tu nombre <span class="req">*</span></label><input type="text" name="respondent_name" class="form-input" placeholder="Nombre completo" required></div>
                    <?php endif; ?>
                    <?php if($reqEmail): ?>
                    <div class="form-group"><label class="form-label">Tu email <span class="req">*</span></label><input type="email" name="respondent_email" class="form-input" placeholder="correo@ejemplo.com" required></div>
                    <?php endif; ?>
                    </div>
                <?php elseif($field['type'] === 'divider'): ?>
                    <div class="form-card" style="<?php echo $isMultiStep ? 'border-top:10px solid '.$primaryColor.';border-top-left-radius:8px;border-top-right-radius:8px;' : ''; ?>">
                    <?php if(!$isMultiStep): ?><hr class="form-divider"><?php endif; ?>
                    <div class="form-section-title" <?php echo $isMultiStep ? 'style="margin-bottom:1rem;font-size:1.2rem;color:'.$primaryColor.'"' : ''; ?>><?php echo htmlspecialchars($field['label']); ?></div>
                    </div>
                <?php else: ?>
                    <div class="form-card" style="<?php echo ($field['width']??'full')==='half'?'display:inline-block;width:48%;vertical-align:top;margin-right:2%':''; ?>">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label"><?php echo htmlspecialchars($field['label']); ?> <?php if(!empty($field['required'])): ?><span class="req">*</span><?php endif; ?></label>
                        <?php if($field['type']==='text' || $field['type']==='email' || $field['type']==='phone' || $field['type']==='date'): ?>
                        <input type="<?php echo $field['type']==='phone'?'tel':$field['type']; ?>" name="field_<?php echo $field['id']; ?>" class="form-input" placeholder="<?php echo htmlspecialchars($field['placeholder']??''); ?>" <?php echo !empty($field['required'])?'required':''; ?>>
                    <?php elseif($field['type']==='textarea'): ?>
                        <textarea name="field_<?php echo $field['id']; ?>" class="form-input form-textarea" placeholder="<?php echo htmlspecialchars($field['placeholder']??''); ?>" <?php echo !empty($field['required'])?'required':''; ?>></textarea>
                    <?php elseif($field['type']==='select' || $field['type']==='checkbox'): ?>
                        <?php $isMulti = isset($field['is_multi']) ? $field['is_multi'] : ($field['type'] === 'checkbox'); ?>
                        <?php $inputType = $isMulti ? 'checkbox' : 'radio'; ?>
                        <div class="<?php echo $isMulti ? 'check-group' : 'radio-group'; ?>">
                        <?php foreach(($field['options']??[]) as $opt): ?>
                            <?php $isOther = ($opt === 'Otro'); ?>
                            <label class="<?php echo $isMulti ? 'check-item' : 'radio-item'; ?>">
                                <?php 
                                    $reqAttr = '';
                                    if(!empty($field['required']) && !$isOther) {
                                        $reqAttr = 'required data-req="true"';
                                    }
                                    $multiValidationJs = '';
                                    if($isMulti && !empty($field['required'])) {
                                        $multiValidationJs = "const group = this.closest('.check-group'); const isChecked = group.querySelectorAll('input[type=checkbox]:checked').length > 0; group.querySelectorAll('input[type=checkbox][data-req=true]').forEach(cb => cb.required = !isChecked);";
                                    }
                                    $otherValidationJs = $isOther ? "var tb=this.closest('label').querySelector('.other-input'); if(this.checked){tb.style.display='block';tb.required=true;tb.focus();}else{tb.style.display='none';tb.required=false;tb.value='';}" : '';
                                    $onchange = trim($multiValidationJs . ' ' . $otherValidationJs);
                                ?>
                                <input type="<?php echo $inputType; ?>" name="field_<?php echo $field['id']; ?><?php echo $isMulti ? '[]' : ''; ?>" value="<?php echo htmlspecialchars($opt); ?>" <?php echo $reqAttr; ?> <?php echo $onchange ? 'onchange="'.htmlspecialchars($onchange).'"' : ''; ?>>
                                <span><?php echo htmlspecialchars($opt); ?></span>
                                <?php if($isOther): ?>
                                <input type="text" name="field_<?php echo $field['id']; ?>_other" class="form-input other-input" style="display:none;margin-left:.5rem;padding:.3rem .5rem;width:auto;flex:1" placeholder="Escribe aquí...">
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                        </div>
                    <?php elseif($field['type']==='dropdown'): ?>
                        <?php $isMulti = !empty($field['is_multi']); ?>
                        <select name="field_<?php echo $field['id']; ?><?php echo $isMulti ? '[]' : ''; ?>" class="form-input" <?php echo !empty($field['required'])?'required':''; ?> <?php echo $isMulti ? 'multiple style="height:auto"' : ''; ?>>
                            <?php if(!$isMulti): ?><option value="">Seleccionar opción...</option><?php endif; ?>
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
                        <div class="file-zone" id="fz_<?php echo $field['id']; ?>">
                            <i class="ph ph-cloud-arrow-up"></i>
                            <div>Toca para subir archivo<?php echo $isMultiple ? 's' : ''; ?></div>
                            <div style="font-size:0.7rem;color:#9ca3af;margin-top:4px;">Max: <?php echo $maxSize; ?> MB <?php echo $isMultiple ? '('.$maxCount.' archivos)' : ''; ?></div>
                            <div class="file-name" id="fn_<?php echo $field['id']; ?>" style="white-space:pre-wrap;word-break:break-word;margin-top:8px;"></div>
                            <input type="file" name="file_<?php echo $field['id']; ?><?php echo $isMultiple ? '[]' : ''; ?>" <?php echo $accept; ?> <?php echo $isMultiple ? 'multiple' : ''; ?> <?php echo !empty($field['required'])?'required':''; ?> onchange="handleAsyncUpload(this, '<?php echo $field['id']; ?>', <?php echo $maxSize; ?>, <?php echo $maxCount; ?>)">
                        </div>
                    <?php elseif($field['type']==='range'): ?>
                        <?php $mn=$field['range_min']??1; $mx=$field['range_max']??5; $lMin=$field['range_label_min']??''; $lMax=$field['range_label_max']??''; ?>
                        <div class="scale-group">
                            <?php if($lMin): ?><div class="scale-label"><?php echo htmlspecialchars($lMin); ?></div><?php endif; ?>
                            <?php for($n=$mn; $n<=$mx; $n++): ?>
                            <label class="scale-item">
                                <input type="radio" name="field_<?php echo $field['id']; ?>" value="<?php echo $n; ?>" <?php echo !empty($field['required'])?'required':''; ?>>
                                <div class="scale-circle"><?php echo $n; ?></div>
                            </label>
                            <?php endfor; ?>
                            <?php if($lMax): ?><div class="scale-label"><?php echo htmlspecialchars($lMax); ?></div><?php endif; ?>
                        </div>
                    <?php elseif($field['type']==='number_range'): ?>
                        <?php $nrMin=$field['nr_min']??18; $nrMax=$field['nr_max']??65; $nrStep=$field['nr_step']??1; ?>
                        <div class="range-select-wrap">
                            <select name="field_<?php echo $field['id']; ?>_from" class="form-input" <?php echo !empty($field['required'])?'required':''; ?>>
                                <option value="">Desde</option>
                                <?php for($n=$nrMin; $n<=$nrMax; $n+=$nrStep): ?>
                                <option value="<?php echo $n; ?>"><?php echo $n; ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="range-dash">—</span>
                            <select name="field_<?php echo $field['id']; ?>_to" class="form-input" <?php echo !empty($field['required'])?'required':''; ?>>
                                <option value="">Hasta</option>
                                <?php for($n=$nrMin; $n<=$nrMax; $n+=$nrStep): ?>
                                <option value="<?php echo $n; ?>"><?php echo $n; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php elseif($field['type']==='color'): ?>
                        <?php $colorOpts=$field['color_options']??['#03624c']; ?>
                        <div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:center;">
                            <?php foreach($colorOpts as $ci => $color): ?>
                            <div style="display:flex;flex-direction:column;align-items:center;gap:.4rem;">
                                <input type="color" name="field_<?php echo $field['id']; ?>[]" class="form-input" style="padding:0;height:46px;width:60px;cursor:pointer;border-radius:12px;border:2px solid #e5e7eb;background:transparent" value="<?php echo htmlspecialchars($color); ?>" <?php echo !empty($field['required'])?'required':''; ?>>
                                <span style="font-size:0.75rem;color:#6b7280;font-weight:600">Color <?php echo $ci+1; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif($field['type']==='icon_card'): ?>
                        <?php $iconOpts=$field['icon_options']??[]; $isMulti=$field['icon_multi']??false; $inputType=$isMulti?'checkbox':'radio'; ?>
                        <div class="icon-card-group">
                            <?php foreach($iconOpts as $oi => $opt): ?>
                            <label class="icon-card-item">
                                <?php 
                                    $reqAttr = '';
                                    if(!empty($field['required'])) {
                                        $reqAttr = 'required data-req="true"';
                                    }
                                    $multiValidationJs = '';
                                    if($isMulti && !empty($field['required'])) {
                                        $multiValidationJs = "const group = this.closest('.icon-card-group'); const isChecked = group.querySelectorAll('input[type=checkbox]:checked').length > 0; group.querySelectorAll('input[type=checkbox][data-req=true]').forEach(cb => cb.required = !isChecked); ";
                                    }
                                    $syncClassJs = "var isRadio=this.type==='radio'; if(isRadio){this.closest('.icon-card-group').querySelectorAll('.icon-card-item').forEach(c=>c.classList.remove('selected'))} if(this.checked){this.closest('.icon-card-item').classList.add('selected')}else{this.closest('.icon-card-item').classList.remove('selected')}";
                                    $onchange = trim($multiValidationJs . $syncClassJs);
                                ?>
                                <input type="<?php echo $inputType; ?>" name="field_<?php echo $field['id']; ?><?php echo $isMulti?'[]':''; ?>" value="<?php echo htmlspecialchars($opt['text']); ?>" <?php echo $reqAttr; ?> onchange="<?php echo htmlspecialchars($onchange); ?>">
                                <div class="icon-card-icon"><i class="ph <?php echo htmlspecialchars($opt['icon']); ?>"></i></div>
                                <span class="icon-card-text"><?php echo htmlspecialchars($opt['text']); ?></span>
                                <div class="icon-card-check"><i class="ph ph-check" style="font-size:.7rem"></i></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <?php if($isMultiStep): ?>
            <div style="display:flex;gap:12px;margin-top:1rem">
                <?php if($sIndex > 0): ?>
                    <button type="button" class="btn-prev" onclick="goToStep(<?php echo $sIndex-1; ?>)">Atrás</button>
                <?php endif; ?>
                <?php if($sIndex < count($steps) - 1): ?>
                    <button type="button" class="submit-btn" onclick="goToStep(<?php echo $sIndex+1; ?>)" style="flex:1">Siguiente <i class="ph ph-caret-right"></i></button>
                <?php else: ?>
                    <button type="submit" class="submit-btn" id="submitBtn" style="flex:1"><i class="ph ph-paper-plane-tilt"></i> Enviar Formulario</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div> <!-- /.form-step -->
        <?php endforeach; ?>

        <?php if(!$isMultiStep): ?>
        <button type="submit" class="submit-btn" id="submitBtn">
            <i class="ph ph-paper-plane-tilt"></i> Enviar Formulario
        </button>
        <?php endif; ?>
    </form>

    <div class="form-card success-screen" id="successScreen" style="display:none">
        <div class="success-icon"><i class="ph ph-check-fat"></i></div>
        <h2>¡Formulario Enviado!</h2>
        <p>Gracias por completar el brief. Hemos recibido tu información y nos pondremos en contacto contigo pronto.</p>
        <p style="margin-top:1rem;font-weight:600;color:<?php echo $primaryColor; ?>" id="successCorrelativo"></p>
    </div>

    <div class="form-footer">Powered by <?php echo htmlspecialchars($siteName); ?></div>
</div>

<script>
let currentStepIdx = 0;
window.goToStep = function(nextIdx) {
    if (nextIdx > currentStepIdx) {
        // validate current step
        const stepEl = document.getElementById('step_' + currentStepIdx);
        if(!stepEl) return;
        const inputs = stepEl.querySelectorAll('input, select, textarea');
        let isValid = true;
        for(let i=0; i<inputs.length; i++) {
            if(!inputs[i].reportValidity()) {
                isValid = false;
                break;
            }
        }
        if(!isValid) return;
    }
    
    document.getElementById('step_' + currentStepIdx).style.display = 'none';
    document.getElementById('step_' + nextIdx).style.display = 'block';
    
    const hero = document.getElementById('formHero');
    if(hero) {
        if(nextIdx > 0) hero.style.display = 'none';
        else hero.style.display = 'block';
    }

    currentStepIdx = nextIdx;
    window.scrollTo({top: 0, behavior: 'smooth'});
    updateProgress();
};

const form=document.getElementById('publicForm');
const totalInputs=form.querySelectorAll('input,textarea,select').length;

function updateProgress() {
    let filled=0;
    form.querySelectorAll('input,textarea,select').forEach(el=>{if(el.value.trim()||el.checked) filled++});
    const pct=Math.min(100,Math.round((filled/Math.max(totalInputs,1))*100));
    document.getElementById('progressBar').style.width=pct+'%';
}

form.addEventListener('input',updateProgress);

let pendingUploads = 0;

function handleAsyncUpload(input, fieldId, maxSizeMB, maxCount) {
    var maxBytes = maxSizeMB * 1024 * 1024;
    var files = input.files;
    if(files.length > maxCount) {
        alert('No puedes subir más de ' + maxCount + ' archivo(s).');
        input.value = '';
        document.getElementById('fn_'+fieldId).innerHTML = '';
        return;
    }
    var html = '';
    var validFiles = [];
    for(var i=0; i<files.length; i++) {
        if(files[i].size > maxBytes) {
            alert('El archivo ' + files[i].name + ' excede el límite de ' + maxSizeMB + ' MB.');
            input.value = '';
            document.getElementById('fn_'+fieldId).innerHTML = '';
            return;
        }
        validFiles.push(files[i]);
        var fName = files[i].name;
        var isImg = files[i].type.startsWith('image/');
        var icon = isImg ? 'ph-image' : (fName.toLowerCase().endsWith('.pdf') ? 'ph-file-pdf' : 'ph-file-text');
        var color = isImg ? '#3b82f6' : (fName.toLowerCase().endsWith('.pdf') ? '#ef4444' : '#6b7280');
        var objUrl = '';
        if (isImg) {
            try { objUrl = (window.URL || window.webkitURL).createObjectURL(files[i]); } catch(e){}
        }
        html += '<div style="display:flex;align-items:center;gap:8px;background:rgba(0,0,0,0.02);padding:8px;border-radius:8px;border:1px solid rgba(0,0,0,0.05);margin-bottom:6px;text-align:left;position:relative;z-index:20">';
        if (isImg && objUrl) {
            html += '<img src="'+objUrl+'" style="width:36px;height:36px;border-radius:6px;object-fit:cover;border:1px solid rgba(0,0,0,0.1);">';
        } else {
            html += '<div style="width:36px;height:36px;border-radius:6px;background:rgba(0,0,0,0.05);display:flex;align-items:center;justify-content:center;color:'+color+'"><i class="ph '+icon+'" style="font-size:1.3rem;"></i></div>';
        }
        html += '<div style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.8rem;color:var(--color-title);font-weight:600;">'+fName+'<br><span id="status_'+fieldId+'_'+i+'" style="color:#f59e0b;font-size:0.7rem"><i class="ph ph-spinner ph-spin"></i> Subiendo...</span></div>';
        html += '<div style="font-size:0.7rem;color:var(--text-muted);">'+(files[i].size/1024/1024).toFixed(1)+' MB</div>';
        html += '</div>';
    }
    
    // Clear previous hidden inputs for this field
    document.querySelectorAll('input[name="temp_file_'+fieldId+'[]"]').forEach(el => el.remove());
    document.querySelectorAll('input[name="temp_name_'+fieldId+'[]"]').forEach(el => el.remove());
    
    document.getElementById('fn_'+fieldId).innerHTML = html;

    if(validFiles.length === 0) return;

    var fd = new FormData();
    for(var i=0; i<validFiles.length; i++) {
        fd.append('file_'+fieldId+'[]', validFiles[i]);
    }

    pendingUploads++;
    var submitBtn = document.getElementById('submitBtn');
    if(submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Subiendo archivos...'; }

    fetch('index.php?module=forms&action=ajax_upload_temp', {
        method: 'POST',
        body: fd
    }).then(res => res.json()).then(res => {
        if(res.success) {
            var pathsHtml = '';
            res.files.forEach(function(f, idx) {
                pathsHtml += '<input type="hidden" name="temp_file_'+fieldId+'[]" value="'+f.tmp_path+'">';
                pathsHtml += '<input type="hidden" name="temp_name_'+fieldId+'[]" value="'+f.original_name+'">';
                var statusEl = document.getElementById('status_'+fieldId+'_'+idx);
                if(statusEl) { statusEl.style.color = '#10b981'; statusEl.innerHTML = '<i class="ph ph-check-circle"></i> Listo'; }
            });
            document.getElementById('fn_'+fieldId).insertAdjacentHTML('beforeend', pathsHtml);
        } else {
            alert('Error al subir: ' + (res.error || 'Desconocido'));
            document.getElementById('fn_'+fieldId).innerHTML = '';
            input.value = '';
        }
    }).catch(err => {
        alert('Error de conexión al subir archivos');
        document.getElementById('fn_'+fieldId).innerHTML = '';
        input.value = '';
    }).finally(() => {
        pendingUploads--;
        if(pendingUploads <= 0 && submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Enviar Brief <i class="ph ph-paper-plane-tilt"></i>';
        }
    });
}

form.addEventListener('submit',async(e)=>{
    e.preventDefault();
    if(pendingUploads > 0) {
        alert('Por favor espera a que terminen de subirse los archivos.');
        return;
    }
    
    const btn=document.getElementById('submitBtn');
    btn.innerHTML='<i class="ph ph-spinner ph-spin"></i> Guardando en Drive...';
    btn.disabled=true;

    const fd=new FormData(form);
    const dataObj={};
    for(const[key,val] of fd.entries()){
        if(key.startsWith('field_') || key.startsWith('temp_file_') || key.startsWith('temp_name_')){
            if(dataObj[key]) {
                if(!Array.isArray(dataObj[key])) dataObj[key]=[dataObj[key]];
                dataObj[key].push(val);
            } else dataObj[key]=val;
        }
    }

    const submitFd=new FormData();
    submitFd.append('token','<?php echo htmlspecialchars($token); ?>');
    submitFd.append('data_json',JSON.stringify(dataObj));
    submitFd.append('respondent_name',fd.get('respondent_name')||'');
    submitFd.append('respondent_email',fd.get('respondent_email')||'');

    try{
        const res=await fetch('index.php?module=forms&action=ajax_submit_form',{method:'POST',body:submitFd});
        const data=await res.json();
        if(data.success){
            form.style.display='none';
            document.getElementById('successScreen').style.display='block';
            document.getElementById('successCorrelativo').textContent='Referencia: '+data.correlativo;
            document.getElementById('progressBar').style.width='100%';
        } else {
            alert(data.error||'Error al enviar');
            btn.innerHTML='Enviar Brief <i class="ph ph-paper-plane-tilt"></i>';
            btn.disabled=false;
        }
    }catch(err){
        alert('Error de conexión');
        btn.innerHTML='Enviar Brief <i class="ph ph-paper-plane-tilt"></i>';
        btn.disabled=false;
    }
});
</script>
</body>
</html>
