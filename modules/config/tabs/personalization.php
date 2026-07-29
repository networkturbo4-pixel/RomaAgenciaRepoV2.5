<form action="index.php?module=config&action=index" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action_type" value="personalization">
    
    <div class="form-group">
        <label for="site_name">Nombre Global del Sistema</label>
        <div class="input-with-icon">
            <i class="ph ph-browser"></i>
            <input type="text" id="site_name" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" required>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-4);">
        <div class="form-group">
            <label for="primary_color">Color Primario</label>
            <div class="input-with-icon">
                <i class="ph ph-palette"></i>
                <input type="color" id="primary_color" name="primary_color" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer;" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#4f46e5'); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="secondary_color">Color Secundario</label>
            <div class="input-with-icon">
                <i class="ph ph-paint-bucket"></i>
                <input type="color" id="secondary_color" name="secondary_color" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer;" value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#10b981'); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="accent_color">Color de Énfasis</label>
            <div class="input-with-icon">
                <i class="ph ph-sparkle"></i>
                <input type="color" id="accent_color" name="accent_color" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer;" value="<?php echo htmlspecialchars($settings['accent_color'] ?? '#f59e0b'); ?>">
            </div>
        </div>
    </div>

    <div class="form-group">
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

    <h3 style="font-size: 1.125rem; font-weight: 600; margin-top: var(--space-6); margin-bottom: var(--space-4);">Identidad Visual</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-4);">
        <!-- Favicon -->
        <div class="upload-card-wrapper">
            <input type="file" id="favicon" name="favicon" accept="image/*">
            <div class="upload-card-content">
                <i class="ph ph-image"></i>
                <h4 class="upload-card-title">Favicon</h4>
                <span class="upload-card-subtitle">Click para cambiar</span>
            </div>
            <?php if(!empty($settings['favicon'])): ?>
                <div class="upload-card-preview">
                    <img src="<?php echo htmlspecialchars($settings['favicon']); ?>" alt="Favicon">
                </div>
            <?php endif; ?>
        </div>

        <!-- Logo Claro -->
        <div class="upload-card-wrapper">
            <input type="file" id="logo_light" name="logo_light" accept="image/*">
            <div class="upload-card-content">
                <i class="ph ph-sun"></i>
                <h4 class="upload-card-title">Logo (Modo Claro)</h4>
                <span class="upload-card-subtitle">Click para cambiar</span>
            </div>
            <?php if(!empty($settings['logo_light'])): ?>
                <div class="upload-card-preview">
                    <img src="<?php echo htmlspecialchars($settings['logo_light']); ?>" alt="Logo Claro">
                </div>
            <?php endif; ?>
        </div>

        <!-- Logo Oscuro -->
        <div class="upload-card-wrapper" style="background: var(--bg-surface);">
            <input type="file" id="logo_dark" name="logo_dark" accept="image/*">
            <div class="upload-card-content">
                <i class="ph ph-moon"></i>
                <h4 class="upload-card-title">Logo (Modo Oscuro)</h4>
                <span class="upload-card-subtitle">Click para cambiar</span>
            </div>
            <?php if(!empty($settings['logo_dark'])): ?>
                <div class="upload-card-preview" style="background: #1e293b;">
                    <img src="<?php echo htmlspecialchars($settings['logo_dark']); ?>" alt="Logo Oscuro">
                </div>
            <?php endif; ?>
        </div>

        <!-- Logo Colapsado -->
        <div class="upload-card-wrapper">
            <input type="file" id="logo_collapsed" name="logo_collapsed" accept="image/*">
            <div class="upload-card-content">
                <i class="ph ph-app-window"></i>
                <h4 class="upload-card-title">Logo (Icono)</h4>
                <span class="upload-card-subtitle">Para menú cerrado</span>
            </div>
            <?php if(!empty($settings['logo_collapsed'])): ?>
                <div class="upload-card-preview">
                    <img src="<?php echo htmlspecialchars($settings['logo_collapsed']); ?>" alt="Logo Colapsado">
                </div>
            <?php endif; ?>
        </div>
    </div>

    <hr style="margin: var(--space-6) 0; border: 0; border-top: 1px solid var(--border-color);">

    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: var(--space-4);">Colores de Texto y Elementos</h3>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-6);">
        <div class="card" style="padding: var(--space-4);">
            <h4 style="font-size: 1rem; margin-bottom: var(--space-4);">Modo Claro</h4>
            
            <div class="form-group">
                <label>Títulos</label>
                <div class="input-with-icon">
                    <i class="ph ph-text-h"></i>
                    <input type="color" name="color_title_light" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer;" value="<?php echo htmlspecialchars($settings['color_title_light'] ?? '#0f172a'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Textos (Frases)</label>
                <div class="input-with-icon">
                    <i class="ph ph-text-t"></i>
                    <input type="color" name="color_text_light" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer;" value="<?php echo htmlspecialchars($settings['color_text_light'] ?? '#64748b'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Enlaces (Texto)</label>
                <div class="input-with-icon">
                    <i class="ph ph-link"></i>
                    <input type="color" name="color_link_light" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer;" value="<?php echo htmlspecialchars($settings['color_link_light'] ?? '#4f46e5'); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Enlaces (Hover)</label>
                <div class="input-with-icon">
                    <i class="ph ph-link"></i>
                    <input type="color" name="color_link_hover_light" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer;" value="<?php echo htmlspecialchars($settings['color_link_hover_light'] ?? '#4338ca'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Botones (Fondo)</label>
                <div class="input-with-icon">
                    <i class="ph ph-paint-bucket"></i>
                    <input type="color" name="color_btn_bg_light" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer;" value="<?php echo htmlspecialchars($settings['color_btn_bg_light'] ?? '#4f46e5'); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Botones (Hover Fondo)</label>
                <div class="input-with-icon">
                    <i class="ph ph-paint-bucket"></i>
                    <input type="color" name="color_btn_hover_light" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer;" value="<?php echo htmlspecialchars($settings['color_btn_hover_light'] ?? '#4338ca'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Botones (Texto)</label>
                <div class="input-with-icon">
                    <i class="ph ph-hand-pointing"></i>
                    <input type="color" name="color_btn_light" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer;" value="<?php echo htmlspecialchars($settings['color_btn_light'] ?? '#ffffff'); ?>">
                </div>
            </div>
        </div>
        
        <div class="card" style="padding: var(--space-4); background: #0f172a; border-color: #1e293b;">
            <h4 style="font-size: 1rem; margin-bottom: var(--space-4); color: white;">Modo Oscuro</h4>
            
            <div class="form-group">
                <label style="color: #cbd5e1;">Títulos</label>
                <div class="input-with-icon">
                    <i class="ph ph-text-h" style="color: #94a3b8;"></i>
                    <input type="color" name="color_title_dark" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer; background: #1e293b; border-color: #334155; color: white;" value="<?php echo htmlspecialchars($settings['color_title_dark'] ?? '#f8fafc'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label style="color: #cbd5e1;">Textos (Frases)</label>
                <div class="input-with-icon">
                    <i class="ph ph-text-t" style="color: #94a3b8;"></i>
                    <input type="color" name="color_text_dark" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer; background: #1e293b; border-color: #334155; color: white;" value="<?php echo htmlspecialchars($settings['color_text_dark'] ?? '#94a3b8'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label style="color: #cbd5e1;">Enlaces (Texto)</label>
                <div class="input-with-icon">
                    <i class="ph ph-link" style="color: #94a3b8;"></i>
                    <input type="color" name="color_link_dark" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer; background: #1e293b; border-color: #334155; color: white;" value="<?php echo htmlspecialchars($settings['color_link_dark'] ?? '#60a5fa'); ?>">
                </div>
            </div>

            <div class="form-group">
                <label style="color: #cbd5e1;">Enlaces (Hover)</label>
                <div class="input-with-icon">
                    <i class="ph ph-link" style="color: #94a3b8;"></i>
                    <input type="color" name="color_link_hover_dark" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer; background: #1e293b; border-color: #334155; color: white;" value="<?php echo htmlspecialchars($settings['color_link_hover_dark'] ?? '#93c5fd'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label style="color: #cbd5e1;">Botones (Fondo)</label>
                <div class="input-with-icon">
                    <i class="ph ph-paint-bucket" style="color: #94a3b8;"></i>
                    <input type="color" name="color_btn_bg_dark" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer; background: #1e293b; border-color: #334155; color: white;" value="<?php echo htmlspecialchars($settings['color_btn_bg_dark'] ?? '#4f46e5'); ?>">
                </div>
            </div>

            <div class="form-group">
                <label style="color: #cbd5e1;">Botones (Hover Fondo)</label>
                <div class="input-with-icon">
                    <i class="ph ph-paint-bucket" style="color: #94a3b8;"></i>
                    <input type="color" name="color_btn_hover_dark" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer; background: #1e293b; border-color: #334155; color: white;" value="<?php echo htmlspecialchars($settings['color_btn_hover_dark'] ?? '#4338ca'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label style="color: #cbd5e1;">Botones (Texto)</label>
                <div class="input-with-icon">
                    <i class="ph ph-hand-pointing" style="color: #94a3b8;"></i>
                    <input type="color" name="color_btn_dark" class="form-control" style="height: 42px; padding: 0.25rem 0.875rem 0.25rem 2.5rem; cursor: pointer; background: #1e293b; border-color: #334155; color: white;" value="<?php echo htmlspecialchars($settings['color_btn_dark'] ?? '#ffffff'); ?>">
                </div>
            </div>
        </div>
    </div>

    <hr style="margin: var(--space-6) 0; border: 0; border-top: 1px solid var(--border-color);">

    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: var(--space-4);">Tipografía</h3>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
        <?php 
        $font_options = ['Inter', 'Roboto', 'Poppins', 'Outfit', 'Playfair Display', 'Montserrat', 'Lora', 'Open Sans']; 
        $font_fields = [
            'font_titles' => ['label' => 'Títulos', 'icon' => 'ph-text-h'],
            'font_text' => ['label' => 'Texto (Frase)', 'icon' => 'ph-text-t'],
            'font_links' => ['label' => 'Enlaces', 'icon' => 'ph-link'],
            'font_buttons' => ['label' => 'Botones', 'icon' => 'ph-hand-pointing']
        ];
        foreach($font_fields as $key => $field):
        ?>
        <div class="form-group">
            <label for="<?php echo $key; ?>"><?php echo $field['label']; ?></label>
            <div class="input-with-icon">
                <i class="ph <?php echo $field['icon']; ?>"></i>
                <select id="<?php echo $key; ?>" name="<?php echo $key; ?>" class="form-control font-select">
                    <?php foreach($font_options as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo ($settings[$key] ?? 'Inter') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endforeach; ?>
    </div>


    <hr style="margin: var(--space-6) 0; border: 0; border-top: 1px solid var(--border-color);">

    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: var(--space-4);">Optimización para Motores de Búsqueda (SEO)</h3>
    <div style="background: var(--surface-color); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: var(--space-4);">
        <div class="form-group">
            <label for="seo_title_suffix">Sufijo del Título (Ej. "| Gestión Integral")</label>
            <div class="input-with-icon">
                <i class="ph ph-text-aa"></i>
                <input type="text" id="seo_title_suffix" name="seo_title_suffix" class="form-control" value="<?php echo htmlspecialchars($settings['seo_title_suffix'] ?? ' | Gestión Integral para su Empresa'); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="seo_description">Meta Descripción Global</label>
            <div class="input-with-icon">
                <i class="ph ph-article"></i>
                <textarea id="seo_description" name="seo_description" class="form-control" rows="3" style="padding-left: 2.5rem;"><?php echo htmlspecialchars($settings['seo_description'] ?? 'Eleve su productividad al siguiente nivel. Gestione sus proyectos, analice datos en tiempo real y coordine a su equipo.'); ?></textarea>
            </div>
        </div>

        <div class="form-group">
            <label for="seo_keywords">Palabras Clave (Separadas por comas)</label>
            <div class="input-with-icon">
                <i class="ph ph-hash"></i>
                <input type="text" id="seo_keywords" name="seo_keywords" class="form-control" value="<?php echo htmlspecialchars($settings['seo_keywords'] ?? 'CRM, Gestión de Proyectos, Análisis de Datos, Productividad, Agencia'); ?>">
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="ph ph-floppy-disk"></i> Guardar Personalización
        </button>
    </div>
</form>

