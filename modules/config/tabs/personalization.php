<div class="pane-header">
    <div>
        <h2 class="pane-header-title">
            <i class="ph ph-palette"></i> Personalización & Marca
        </h2>
        <p class="pane-header-desc">Personaliza la identidad corporativa, logotipos oficiales, esquemas de color para ambos temas y tipografía global.</p>
    </div>
</div>

<form action="index.php?module=config&action=index" method="POST" enctype="multipart/form-data" id="form-personalization">
    <input type="hidden" name="action_type" value="personalization">
    
    <!-- CARD 1: IDENTIDAD GENERAL -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-browser"></i> Identidad General de la Plataforma</h3>
                <p class="settings-card-desc">Nombre visible en la barra de navegación, títulos de pestaña y moneda predeterminada.</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="site_name">Nombre Global del Sistema</label>
                <div class="input-with-icon">
                    <i class="ph ph-desktop"></i>
                    <input type="text" id="site_name" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" required placeholder="Ej. ROMA Agencia">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="currency">Moneda Global</label>
                <div class="input-with-icon">
                    <i class="ph ph-currency-dollar"></i>
                    <select id="currency" name="currency" class="form-control">
                        <option value="USD" <?php echo ($settings['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                        <option value="EUR" <?php echo ($settings['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                        <option value="PEN" <?php echo ($settings['currency'] ?? '') === 'PEN' ? 'selected' : ''; ?>>PEN (S/)</option>
                        <option value="MXN" <?php echo ($settings['currency'] ?? '') === 'MXN' ? 'selected' : ''; ?>>MXN ($)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- CARD 2: LOGOTIPOS Y MARCA (DROPZONES) -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-image"></i> Identidad Visual & Logotipos</h3>
                <p class="settings-card-desc">Formatos recomendados: PNG o SVG transparente. Se actualizarán en la barra superior y accesos directos.</p>
            </div>
        </div>

        <div class="brand-dropzone-grid">
            <!-- Favicon -->
            <div class="app-brand-dropzone">
                <input type="file" id="favicon" name="favicon" accept="image/*" class="brand-file-input" data-target="preview-favicon">
                <div class="brand-preview-canvas" id="preview-favicon">
                    <?php if(!empty($settings['favicon'])): ?>
                        <img src="<?php echo htmlspecialchars($settings['favicon']); ?>" alt="Favicon">
                    <?php else: ?>
                        <div class="brand-empty-state">
                            <i class="ph ph-image"></i>
                            <span style="font-size: 11px;">Sin favicon</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="brand-info">
                    <h4 class="brand-title">Favicon</h4>
                    <p class="brand-subtitle">Pestañas del navegador (ICO / PNG)</p>
                    <span class="brand-upload-btn-hint"><i class="ph ph-upload-simple"></i> Subir imagen</span>
                </div>
            </div>

            <!-- Logo Modo Claro -->
            <div class="app-brand-dropzone">
                <input type="file" id="logo_light" name="logo_light" accept="image/*" class="brand-file-input" data-target="preview-logo-light">
                <div class="brand-preview-canvas light-backdrop" id="preview-logo-light">
                    <?php if(!empty($settings['logo_light'])): ?>
                        <img src="<?php echo htmlspecialchars($settings['logo_light']); ?>" alt="Logo Claro">
                    <?php else: ?>
                        <div class="brand-empty-state">
                            <i class="ph ph-sun"></i>
                            <span style="font-size: 11px;">Logo claro</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="brand-info">
                    <h4 class="brand-title">Logo (Modo Claro)</h4>
                    <p class="brand-subtitle">Fondo blanco o transparente</p>
                    <span class="brand-upload-btn-hint"><i class="ph ph-upload-simple"></i> Subir imagen</span>
                </div>
            </div>

            <!-- Logo Modo Oscuro -->
            <div class="app-brand-dropzone">
                <input type="file" id="logo_dark" name="logo_dark" accept="image/*" class="brand-file-input" data-target="preview-logo-dark">
                <div class="brand-preview-canvas dark-backdrop" id="preview-logo-dark">
                    <?php if(!empty($settings['logo_dark'])): ?>
                        <img src="<?php echo htmlspecialchars($settings['logo_dark']); ?>" alt="Logo Oscuro">
                    <?php else: ?>
                        <div class="brand-empty-state">
                            <i class="ph ph-moon" style="color: #94a3b8;"></i>
                            <span style="font-size: 11px; color: #94a3b8;">Logo oscuro</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="brand-info">
                    <h4 class="brand-title">Logo (Modo Oscuro)</h4>
                    <p class="brand-subtitle">Para contraste sobre fondo oscuro</p>
                    <span class="brand-upload-btn-hint"><i class="ph ph-upload-simple"></i> Subir imagen</span>
                </div>
            </div>

            <!-- Logo Colapsado / Icono -->
            <div class="app-brand-dropzone">
                <input type="file" id="logo_collapsed" name="logo_collapsed" accept="image/*" class="brand-file-input" data-target="preview-logo-collapsed">
                <div class="brand-preview-canvas" id="preview-logo-collapsed">
                    <?php if(!empty($settings['logo_collapsed'])): ?>
                        <img src="<?php echo htmlspecialchars($settings['logo_collapsed']); ?>" alt="Logo Icono">
                    <?php else: ?>
                        <div class="brand-empty-state">
                            <i class="ph ph-app-window"></i>
                            <span style="font-size: 11px;">Icono app</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="brand-info">
                    <h4 class="brand-title">Logo (Icono)</h4>
                    <p class="brand-subtitle">Para sidebar colapsado y móvil</p>
                    <span class="brand-upload-btn-hint"><i class="ph ph-upload-simple"></i> Subir imagen</span>
                </div>
            </div>
        </div>
    </div>

    <!-- CARD 3: PALETA DE COLORES PRINCIPAL -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-paint-brush"></i> Paleta Cromática del Sistema</h3>
                <p class="settings-card-desc">Colores de acento, estados de éxito y alertas destacados en la plataforma.</p>
            </div>
        </div>

        <div class="color-picker-grid">
            <!-- Primario -->
            <div class="color-picker-tile">
                <div class="color-picker-label">
                    <span>Color Primario</span>
                    <i class="ph ph-palette" style="color: var(--primary-color);"></i>
                </div>
                <div class="color-input-combo">
                    <div class="color-swatch-circle" style="background-color: <?php echo htmlspecialchars($settings['primary_color'] ?? '#4f46e5'); ?>;">
                        <input type="color" name="primary_color" class="color-native-input" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#4f46e5'); ?>" required>
                    </div>
                    <input type="text" class="color-hex-text" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#4f46e5'); ?>" maxlength="7" spellcheck="false">
                    <button type="button" class="color-copy-btn" title="Copiar código"><i class="ph ph-copy"></i></button>
                </div>
            </div>

            <!-- Secundario -->
            <div class="color-picker-tile">
                <div class="color-picker-label">
                    <span>Color Secundario</span>
                    <i class="ph ph-paint-bucket" style="color: var(--secondary-color);"></i>
                </div>
                <div class="color-input-combo">
                    <div class="color-swatch-circle" style="background-color: <?php echo htmlspecialchars($settings['secondary_color'] ?? '#10b981'); ?>;">
                        <input type="color" name="secondary_color" class="color-native-input" value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#10b981'); ?>">
                    </div>
                    <input type="text" class="color-hex-text" value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#10b981'); ?>" maxlength="7" spellcheck="false">
                    <button type="button" class="color-copy-btn" title="Copiar código"><i class="ph ph-copy"></i></button>
                </div>
            </div>

            <!-- Énfasis / Acento -->
            <div class="color-picker-tile">
                <div class="color-picker-label">
                    <span>Color de Énfasis</span>
                    <i class="ph ph-sparkle" style="color: var(--warning-color);"></i>
                </div>
                <div class="color-input-combo">
                    <div class="color-swatch-circle" style="background-color: <?php echo htmlspecialchars($settings['accent_color'] ?? '#f59e0b'); ?>;">
                        <input type="color" name="accent_color" class="color-native-input" value="<?php echo htmlspecialchars($settings['accent_color'] ?? '#f59e0b'); ?>">
                    </div>
                    <input type="text" class="color-hex-text" value="<?php echo htmlspecialchars($settings['accent_color'] ?? '#f59e0b'); ?>" maxlength="7" spellcheck="false">
                    <button type="button" class="color-copy-btn" title="Copiar código"><i class="ph ph-copy"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- CARD 4: COLORES MODO CLARO Y MODO OSCURO -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
        
        <!-- MODO CLARO -->
        <div class="settings-card" style="margin-bottom: 0;">
            <div class="settings-card-header">
                <div>
                    <h3 class="settings-card-title"><i class="ph ph-sun"></i> Elementos Modo Claro</h3>
                    <p class="settings-card-desc">Contraste diurno y legibilidad estándar.</p>
                </div>
            </div>

            <div class="color-picker-grid" style="grid-template-columns: 1fr 1fr;">
                <?php
                $light_fields = [
                    'color_title_light' => ['label' => 'Títulos', 'default' => '#0f172a'],
                    'color_text_light' => ['label' => 'Textos', 'default' => '#64748b'],
                    'color_link_light' => ['label' => 'Enlaces', 'default' => '#4f46e5'],
                    'color_link_hover_light' => ['label' => 'Enlaces Hover', 'default' => '#4338ca'],
                    'color_btn_bg_light' => ['label' => 'Botones Fondo', 'default' => '#4f46e5'],
                    'color_btn_hover_light' => ['label' => 'Botones Hover', 'default' => '#4338ca'],
                    'color_btn_light' => ['label' => 'Botones Texto', 'default' => '#ffffff'],
                ];
                foreach ($light_fields as $key => $info):
                    $val = $settings[$key] ?? $info['default'];
                ?>
                <div class="color-picker-tile">
                    <div class="color-picker-label" style="font-size: 11.5px;"><?php echo $info['label']; ?></div>
                    <div class="color-input-combo">
                        <div class="color-swatch-circle" style="background-color: <?php echo htmlspecialchars($val); ?>; width: 26px; height: 26px; min-width: 26px;">
                            <input type="color" name="<?php echo $key; ?>" class="color-native-input" value="<?php echo htmlspecialchars($val); ?>">
                        </div>
                        <input type="text" class="color-hex-text" value="<?php echo htmlspecialchars($val); ?>" maxlength="7" spellcheck="false" style="font-size: 11px;">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- MODO OSCURO -->
        <div class="settings-card" style="margin-bottom: 0;">
            <div class="settings-card-header">
                <div>
                    <h3 class="settings-card-title"><i class="ph ph-moon"></i> Elementos Modo Oscuro</h3>
                    <p class="settings-card-desc">Contraste nocturno para descansar la vista.</p>
                </div>
            </div>

            <div class="color-picker-grid" style="grid-template-columns: 1fr 1fr;">
                <?php
                $dark_fields = [
                    'color_title_dark' => ['label' => 'Títulos', 'default' => '#f8fafc'],
                    'color_text_dark' => ['label' => 'Textos', 'default' => '#94a3b8'],
                    'color_link_dark' => ['label' => 'Enlaces', 'default' => '#60a5fa'],
                    'color_link_hover_dark' => ['label' => 'Enlaces Hover', 'default' => '#93c5fd'],
                    'color_btn_bg_dark' => ['label' => 'Botones Fondo', 'default' => '#4f46e5'],
                    'color_btn_hover_dark' => ['label' => 'Botones Hover', 'default' => '#4338ca'],
                    'color_btn_dark' => ['label' => 'Botones Texto', 'default' => '#ffffff'],
                ];
                foreach ($dark_fields as $key => $info):
                    $val = $settings[$key] ?? $info['default'];
                ?>
                <div class="color-picker-tile">
                    <div class="color-picker-label" style="font-size: 11.5px;"><?php echo $info['label']; ?></div>
                    <div class="color-input-combo">
                        <div class="color-swatch-circle" style="background-color: <?php echo htmlspecialchars($val); ?>; width: 26px; height: 26px; min-width: 26px;">
                            <input type="color" name="<?php echo $key; ?>" class="color-native-input" value="<?php echo htmlspecialchars($val); ?>">
                        </div>
                        <input type="text" class="color-hex-text" value="<?php echo htmlspecialchars($val); ?>" maxlength="7" spellcheck="false" style="font-size: 11px;">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- CARD 5: TIPOGRAFÍA -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-text-t"></i> Tipografía del Sistema</h3>
                <p class="settings-card-desc">Selecciona familias tipográficas optimizadas para pantallas digitales de alta resolución.</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            <?php 
            $font_options = ['Inter', 'Roboto', 'Poppins', 'Outfit', 'Playfair Display', 'Montserrat', 'Lora', 'Open Sans']; 
            $font_fields = [
                'font_titles' => ['label' => 'Títulos y Encabezados', 'icon' => 'ph-text-h'],
                'font_text' => ['label' => 'Cuerpo de Texto', 'icon' => 'ph-text-t'],
                'font_links' => ['label' => 'Enlaces y Navegación', 'icon' => 'ph-link'],
                'font_buttons' => ['label' => 'Botones y Acciones', 'icon' => 'ph-hand-pointing']
            ];
            foreach($font_fields as $key => $field):
            ?>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="<?php echo $key; ?>"><?php echo $field['label']; ?></label>
                <div class="input-with-icon">
                    <i class="ph <?php echo $field['icon']; ?>"></i>
                    <select id="<?php echo $key; ?>" name="<?php echo $key; ?>" class="form-control font-select" style="font-family: '<?php echo $settings[$key] ?? 'Inter'; ?>', sans-serif;">
                        <?php foreach($font_options as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo ($settings[$key] ?? 'Inter') === $opt ? 'selected' : ''; ?> style="font-family: '<?php echo $opt; ?>', sans-serif;">
                                <?php echo $opt; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- CARD 6: OPTIMIZACIÓN SEO -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h3 class="settings-card-title"><i class="ph ph-globe"></i> Optimización para Motores de Búsqueda (SEO)</h3>
                <p class="settings-card-desc">Configura metadatos globales para indexación y apariencia al compartir enlaces.</p>
            </div>
        </div>

        <div class="form-group">
            <label for="seo_title_suffix">Sufijo del Título en Pestañas</label>
            <div class="input-with-icon">
                <i class="ph ph-text-aa"></i>
                <input type="text" id="seo_title_suffix" name="seo_title_suffix" class="form-control" value="<?php echo htmlspecialchars($settings['seo_title_suffix'] ?? ' | Gestión Integral para su Empresa'); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="seo_description">Meta Descripción Global</label>
            <div class="input-with-icon">
                <i class="ph ph-article"></i>
                <textarea id="seo_description" name="seo_description" class="form-control" rows="2" style="padding-left: 2.75rem;"><?php echo htmlspecialchars($settings['seo_description'] ?? 'Eleve su productividad al siguiente nivel. Gestione sus proyectos, analice datos en tiempo real y coordine a su equipo.'); ?></textarea>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label for="seo_keywords">Palabras Clave (Separadas por comas)</label>
            <div class="input-with-icon">
                <i class="ph ph-hash"></i>
                <input type="text" id="seo_keywords" name="seo_keywords" class="form-control" value="<?php echo htmlspecialchars($settings['seo_keywords'] ?? 'CRM, Gestión de Proyectos, Análisis de Datos, Productividad, Agencia'); ?>">
            </div>
        </div>
    </div>

    <!-- ACTION BAR: GUARDAR -->
    <div class="settings-action-bar">
        <div class="settings-action-bar-info">
            <i class="ph ph-info"></i>
            <span>Los cambios de marca y colores se reflejan de inmediato en toda la plataforma.</span>
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; border-radius: 10px;">
            <i class="ph ph-floppy-disk"></i> Guardar Personalización
        </button>
    </div>
</form>

<script>
// Interactivity for Personalization Tab (Color Sync, Image Previews, Clipboard)
document.addEventListener('DOMContentLoaded', () => {
    // 1. Color Picker <-> Hex Input Sync
    const colorTiles = document.querySelectorAll('.color-picker-tile');
    colorTiles.forEach(tile => {
        const nativeInput = tile.querySelector('.color-native-input');
        const hexInput = tile.querySelector('.color-hex-text');
        const swatch = tile.querySelector('.color-swatch-circle');
        const copyBtn = tile.querySelector('.color-copy-btn');

        if (nativeInput && hexInput && swatch) {
            nativeInput.addEventListener('input', () => {
                hexInput.value = nativeInput.value.toUpperCase();
                swatch.style.backgroundColor = nativeInput.value;
            });

            hexInput.addEventListener('input', () => {
                let val = hexInput.value.trim();
                if (!val.startsWith('#')) val = '#' + val;
                if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                    nativeInput.value = val;
                    swatch.style.backgroundColor = val;
                }
            });

            if (copyBtn) {
                copyBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    navigator.clipboard.writeText(hexInput.value).then(() => {
                        const originalIcon = copyBtn.innerHTML;
                        copyBtn.innerHTML = '<i class="ph ph-check" style="color: #10b981;"></i>';
                        setTimeout(() => { copyBtn.innerHTML = originalIcon; }, 1500);
                    });
                });
            }
        }
    });

    // 2. Real-time Image Previews for Brand Dropzones
    const fileInputs = document.querySelectorAll('.brand-file-input');
    fileInputs.forEach(input => {
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            const targetId = input.getAttribute('data-target');
            const targetCanvas = document.getElementById(targetId);

            if (file && targetCanvas) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    targetCanvas.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-width:100%; max-height:100%; object-fit:contain; animation: settingsSlideUp 0.3s ease;">`;
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // 3. Font Select Preview Update
    const fontSelects = document.querySelectorAll('.font-select');
    fontSelects.forEach(select => {
        select.addEventListener('change', () => {
            select.style.fontFamily = `'${select.value}', sans-serif`;
        });
    });
});
</script>
