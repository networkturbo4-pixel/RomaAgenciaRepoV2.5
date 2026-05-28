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

    <div class="form-group">
        <label for="favicon">Favicon</label>
        <input type="file" id="favicon" name="favicon" class="form-control" accept="image/*">
        <?php if(!empty($settings['favicon'])): ?>
            <small class="text-muted">Actual: <img src="<?php echo htmlspecialchars($settings['favicon']); ?>" width="20" style="vertical-align: middle;"></small>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="logo_light">Logo (Modo Claro)</label>
        <input type="file" id="logo_light" name="logo_light" class="form-control" accept="image/*">
        <?php if(!empty($settings['logo_light'])): ?>
            <small class="text-muted">Actual: <img src="<?php echo htmlspecialchars($settings['logo_light']); ?>" height="30" style="vertical-align: middle;"></small>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="logo_dark">Logo (Modo Oscuro)</label>
        <input type="file" id="logo_dark" name="logo_dark" class="form-control" accept="image/*">
        <?php if(!empty($settings['logo_dark'])): ?>
            <small class="text-muted">Actual: <img src="<?php echo htmlspecialchars($settings['logo_dark']); ?>" height="30" style="vertical-align: middle; background: #333;"></small>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label for="logo_collapsed">Logo (Colapsado / Icono)</label>
        <input type="file" id="logo_collapsed" name="logo_collapsed" class="form-control" accept="image/*">
        <?php if(!empty($settings['logo_collapsed'])): ?>
            <small class="text-muted">Actual: <img src="<?php echo htmlspecialchars($settings['logo_collapsed']); ?>" height="30" style="vertical-align: middle;"></small>
        <?php endif; ?>
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

    <!-- Live Preview -->
    <div class="card mb-4" style="background: #f8fafc; border: 1px dashed var(--border-color);">
        <h4 style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: var(--space-2); text-transform: uppercase;">Previsualización</h4>
        <div id="font-preview-box" style="padding: var(--space-4); background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
            <h2 id="preview-title" style="margin-bottom: var(--space-2); color: var(--text-main);">Este es un Título de Prueba</h2>
            <p id="preview-text" style="color: var(--text-muted); margin-bottom: var(--space-4);">Este es un texto de prueba que sirve como frase o párrafo descriptivo para ver cómo luce la fuente seleccionada.</p>
            <div style="display: flex; gap: var(--space-4); align-items: center;">
                <button type="button" id="preview-btn" class="btn" style="background: var(--primary-color); color: white;">Botón de Prueba</button>
                <a href="#" id="preview-link" style="color: var(--primary-color);">Enlace de Prueba</a>
            </div>
        </div>
    </div>

    <div class="mt-2">
        <button type="submit" class="btn btn-primary">
            <i class="ph ph-floppy-disk"></i> Guardar Personalización
        </button>
    </div>
</form>

<script>
// Load Google Fonts dynamically for preview
function loadFont(fontName) {
    const id = 'font-' + fontName.replace(/\s+/g, '-').toLowerCase();
    if (!document.getElementById(id)) {
        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = 'https://fonts.googleapis.com/css2?family=' + fontName.replace(/\s+/g, '+') + ':wght@400;600&display=swap';
        document.head.appendChild(link);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const titleSelect = document.getElementById('font_titles');
    const textSelect = document.getElementById('font_text');
    const linkSelect = document.getElementById('font_links');
    const btnSelect = document.getElementById('font_buttons');
    
    const previewTitle = document.getElementById('preview-title');
    const previewText = document.getElementById('preview-text');
    const previewLink = document.getElementById('preview-link');
    const previewBtn = document.getElementById('preview-btn');
    
    function updatePreview() {
        loadFont(titleSelect.value);
        previewTitle.style.fontFamily = `"${titleSelect.value}", sans-serif`;
        
        loadFont(textSelect.value);
        previewText.style.fontFamily = `"${textSelect.value}", sans-serif`;
        
        loadFont(linkSelect.value);
        previewLink.style.fontFamily = `"${linkSelect.value}", sans-serif`;
        
        loadFont(btnSelect.value);
        previewBtn.style.fontFamily = `"${btnSelect.value}", sans-serif`;
    }

    [titleSelect, textSelect, linkSelect, btnSelect].forEach(sel => {
        sel.addEventListener('change', updatePreview);
        // Load initial font
        loadFont(sel.value);
    });
    
    // Initial update
    updatePreview();
});
</script>
