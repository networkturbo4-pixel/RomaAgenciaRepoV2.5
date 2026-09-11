<?php
require_once 'includes/header.php';
?>
<style>
<?php 
    $css_path = __DIR__ . '/../../assets/css/herramientas.css';
    if (file_exists($css_path)) {
        echo file_get_contents($css_path); 
    }
?>
/* BioLink App-Style Redesign */
.biolink-card {
    background: var(--bg-card, #ffffff);
    padding: 1.5rem;
    border-radius: 16px;
    border: 1px solid var(--border-color, #e5e7eb);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.02), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.biolink-card:hover {
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.04), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
}
.biolink-input {
    width: 100%;
    padding: 0.875rem 1rem;
    border-radius: 12px;
    border: 1.5px solid var(--border-color, #e5e7eb);
    background: var(--bg-body, #f9fafb);
    color: var(--text-main, #111827);
    font-size: 0.95rem;
    font-family: inherit;
    transition: all 0.25s ease;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);
    outline: none;
}
.biolink-input:focus {
    border-color: var(--primary-color, #4f46e5);
    background: var(--bg-card, #ffffff);
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
}
.biolink-input-group {
    display: flex;
    align-items: center;
    border: 1.5px solid var(--border-color, #e5e7eb);
    border-radius: 12px;
    overflow: hidden;
    background: var(--bg-body, #f9fafb);
    transition: all 0.25s ease;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);
}
.biolink-input-group:focus-within {
    border-color: var(--primary-color, #4f46e5);
    background: var(--bg-card, #ffffff);
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
}
.biolink-input-group span {
    padding: 0.875rem 1rem;
    font-size: 0.95rem;
    color: var(--text-muted, #6b7280);
    border-right: 1px solid var(--border-color, #e5e7eb);
    background: var(--bg-surface, #f3f4f6);
}
.biolink-input-group input {
    flex: 1;
    border: none;
    padding: 0.875rem 1rem;
    background: transparent;
    color: var(--text-main, #111827);
    font-size: 0.95rem;
    outline: none;
    box-shadow: none;
}
.biolink-textarea {
    width: 100%;
    padding: 0.875rem 1rem;
    border-radius: 12px;
    border: 1.5px solid var(--border-color, #e5e7eb);
    background: var(--bg-body, #f9fafb);
    color: var(--text-main, #111827);
    font-size: 0.95rem;
    font-family: inherit;
    transition: all 0.25s ease;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);
    outline: none;
    min-height: 100px;
    resize: vertical;
}
.biolink-textarea:focus {
    border-color: var(--primary-color, #4f46e5);
    background: var(--bg-card, #ffffff);
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
}
/* Fix: no transform animations on linktree tab */
@keyframes fadeInOnly {
    from { opacity: 0; }
    to { opacity: 1; }
}
.herr-tab-content[data-tool-content="linktree"].active {
    animation: fadeInOnly 0.3s ease;
}
/* BioLink Editor Layout: left scrolls, right fixed */
.biolink-editor-layout {
    display: flex;
    gap: 2rem;
    height: calc(100vh - 180px);
    min-height: 500px;
    padding: 1rem 0;
}
.biolink-editor-left {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    padding-right: 0.5rem;
    min-width: 0;
}
.biolink-editor-left::-webkit-scrollbar {
    width: 5px;
}
.biolink-editor-left::-webkit-scrollbar-track {
    background: transparent;
}
.biolink-editor-left::-webkit-scrollbar-thumb {
    background: var(--border-color, #e5e7eb);
    border-radius: 999px;
}
.biolink-editor-left::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted, #9ca3af);
}
.biolink-editor-right {
    flex-shrink: 0;
    display: flex;
    align-items: flex-start;
    justify-content: center;
}
</style>

<!-- Module Header -->
<div class="herr-header">
    <i class="ph ph-wrench"></i>
    <h2>Herramientas</h2>
</div>

<!-- Tool Tabs -->
<div class="herr-tabs">
    <button class="herr-tab active" data-tool="paleta"><i class="ph ph-palette"></i> Generador de Paletas</button>
    <button class="herr-tab" data-tool="qr"><i class="ph ph-qr-code"></i> Generador de Códigos</button>
    <button class="herr-tab" data-tool="linktree"><i class="ph ph-link"></i> BioLinks</button>
</div>

<!-- Tab Content: Generador de Paletas -->
<div class="herr-tab-content active" data-tool-content="paleta">

    <!-- Controls Bar — Estilo App Moderna -->
    <div class="paleta-controls">
        <!-- Color Seeds (Primario y Secundario) -->
        <div class="paleta-picker-group">
            <!-- Primario -->
            <div class="paleta-chip-card">
                <span class="paleta-chip-mini-tag">COLOR PRIMARIO</span>
                <div class="paleta-chip-box">
                    <label class="paleta-color-preview-wrap" title="Haz clic para abrir el selector de color">
                        <input type="color" id="colorPicker" value="#0c36a6" class="paleta-color-native-input">
                        <span class="paleta-color-dot" id="primaryColorDot" style="background:#0c36a6"></span>
                    </label>
                    <input type="text" id="hexInput" value="#0C36A6" maxlength="7" placeholder="#000000" class="paleta-chip-hex" autocomplete="off" spellcheck="false">
                </div>
            </div>

            <!-- Secundario -->
            <div class="paleta-chip-card">
                <div class="paleta-chip-card-header">
                    <span class="paleta-chip-mini-tag">COLOR SECUNDARIO</span>
                    <label class="app-switch" title="Activar / Desactivar color secundario">
                        <input type="checkbox" id="enableSecondary" checked>
                        <span class="app-switch-slider"></span>
                    </label>
                </div>
                <div class="paleta-chip-box" id="secondaryColorControls">
                    <label class="paleta-color-preview-wrap" title="Haz clic para abrir el selector de color secundario">
                        <input type="color" id="secondaryColorPicker" value="#10b981" class="paleta-color-native-input">
                        <span class="paleta-color-dot" id="secondaryColorDot" style="background:#10b981"></span>
                    </label>
                    <input type="text" id="secondaryHexInput" value="#10B981" maxlength="7" placeholder="#000000" class="paleta-chip-hex" autocomplete="off" spellcheck="false">
                </div>
            </div>
        </div>

        <!-- Armonía Cromática -->
        <div class="paleta-harmony-group">
            <span class="paleta-chip-mini-tag">ARMONÍA CROMÁTICA</span>
            <div class="paleta-harmony-box">
                <i class="ph ph-circles-three"></i>
                <select id="harmonyMode" class="paleta-harmony-select">
                    <option value="auto" selected>Auto (Inteligente)</option>
                    <option value="complementary">Complementario (180°)</option>
                    <option value="analogous">Análogo (±30°)</option>
                    <option value="triadic">Tríada (120°)</option>
                    <option value="split">Split-Complementario</option>
                </select>
            </div>
        </div>

        <!-- Botones de Acción de la App -->
        <div class="paleta-actions">
            <button type="button" class="btn btn-outline paleta-btn-random" id="btnRandom" title="Atajo: Barra espaciadora">
                <i class="ph ph-shuffle"></i> <span>Aleatorio</span> <span class="kbd-hint">Espacio</span>
            </button>
            <button type="button" class="btn btn-primary paleta-btn-save" id="btnSave">
                <i class="ph ph-floppy-disk"></i> <span>Guardar</span>
            </button>
            <div class="export-dropdown">
                <button type="button" class="btn btn-outline" id="btnExport">
                    <i class="ph ph-export"></i> <span>Exportar</span> <i class="ph ph-caret-down"></i>
                </button>
                <div class="export-dropdown-menu" id="exportMenu">
                    <button type="button" class="export-dropdown-item" data-export="css"><i class="ph ph-file-css"></i> Variables CSS (:root)</button>
                    <button type="button" class="export-dropdown-item" data-export="json"><i class="ph ph-file-js"></i> Tokens JSON</button>
                    <button type="button" class="export-dropdown-item" data-export="tailwind"><i class="ph ph-wind"></i> Configuración Tailwind CSS</button>
                    <div style="height:1px;background:var(--border-color);margin:4px 0"></div>
                    <button type="button" class="export-dropdown-item" data-export="png"><i class="ph ph-image"></i> Paleta en Imagen (PNG HD)</button>
                    <button type="button" class="export-dropdown-item" data-export="pdf"><i class="ph ph-file-pdf"></i> Guía de Color (PDF)</button>
                </div>
            </div>
            <button type="button" class="btn btn-outline" id="btnToggleSidebar" title="Ver paletas guardadas">
                <i class="ph ph-bookmark-simple"></i> <span>Colección</span> <span class="paleta-count-badge" id="savedPalettesCount">0</span>
            </button>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="paleta-layout">
        <div class="paleta-main">
            <!-- Color Scales -->
            <div id="scalesContainer"></div>

            <!-- Preview Tabs -->
            <div class="preview-tabs">
                <button class="preview-tab active" data-preview="cards"><i class="ph ph-cards"></i> Cards</button>
                <button class="preview-tab" data-preview="buttons"><i class="ph ph-cursor-click"></i> Buttons</button>
                <button class="preview-tab" data-preview="dashboard"><i class="ph ph-chart-pie-slice"></i> Dashboard</button>
                <button class="preview-tab" data-preview="typography"><i class="ph ph-text-aa"></i> Typography</button>
                <button class="preview-tab" data-preview="badges"><i class="ph ph-seal-check"></i> Badges</button>
                <button class="preview-tab" data-preview="gradients"><i class="ph ph-gradient"></i> Gradients</button>
            </div>
            <div class="preview-content" id="previewContent"></div>
        </div>

        <!-- Saved Palettes Modern Section (moved to bottom of main layout) -->
        <div class="paleta-saved-section" id="paletaSavedSection">
            <div class="paleta-saved-header">
                <i class="ph ph-bookmark-simple"></i> Paletas Guardadas
            </div>
            <div id="savedPalettes" class="paleta-saved-grid"></div>
        </div>
    </div> <!-- /.paleta-layout -->

</div><!-- /.herr-tab-content (Paletas) -->

<!-- Tab Content: Generador QR y Barras -->
<div class="herr-tab-content" data-tool-content="qr">
    <div class="qr-layout">
        
        <!-- Controles Laterales (Configuración) -->
        <div class="qr-sidebar">
            
            <!-- Switcher de Tipo de Código -->
            <div class="qr-type-switcher">
                <button type="button" class="qr-tab-btn active" data-type="qr">
                    <i class="ph ph-qr-code"></i> Código QR
                </button>
                <button type="button" class="qr-tab-btn" data-type="barcode">
                    <i class="ph ph-barcode"></i> Código de Barras
                </button>
            </div>

            <!-- ======================================================== -->
            <!-- CONTROLES QR                                             -->
            <!-- ======================================================== -->
            <div id="qrControlsContainer">
                
                <!-- Card 1: Tipo y Formato QR -->
                <div class="qr-card">
                    <div class="qr-card-header">
                        <span class="qr-card-title"><i class="ph ph-sliders-horizontal"></i> Tipo y Formato QR</span>
                        <span class="qr-card-badge">Dinámico</span>
                    </div>

                    <div class="qr-field-group">
                        <label class="qr-field-label">Contenido / Destino</label>
                        <select id="qrFormatSelect" class="qr-field-select">
                            <option value="url">Enlace / URL</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="telegram">Telegram</option>
                            <option value="mailto">Correo Electrónico</option>
                            <option value="event">Evento (Calendario)</option>
                            <option value="wifi">Red Wi-Fi</option>
                            <option value="vcard">Contacto (vCard)</option>
                            <option value="geo">Geolocalización</option>
                            <option value="text">Texto Libre</option>
                        </select>
                    </div>

                    <!-- Inputs dinámicos QR -->
                    <div id="qrDynamicInputs">
                        <!-- URL Input (Default) -->
                        <div class="qr-input-group active" data-qr-input="url">
                            <label class="qr-field-label qr-field-label--sub">Introduce el enlace (URL)</label>
                            <div class="bc-input-box">
                                <span class="bc-input-icon"><i class="ph ph-link"></i></span>
                                <input type="url" 
                                       id="qrInputUrl" 
                                       placeholder="https://ejemplo.com" 
                                       class="qr-field-input" 
                                       value="https://romaagencia.com" 
                                       autocomplete="off" 
                                       data-lpignore="true" 
                                       data-1p-ignore="true" 
                                       data-bwignore="true" 
                                       data-form-type="other"
                                       aria-autocomplete="none" 
                                       spellcheck="false">
                                <div class="bc-input-actions">
                                    <button type="button" class="bc-action-btn" id="btnClearQrUrl" title="Limpiar enlace">
                                        <i class="ph ph-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- WhatsApp Input -->
                        <div class="qr-input-group" data-qr-input="whatsapp" style="display:none">
                            <div class="qr-field-group">
                                <label class="qr-field-label qr-field-label--sub">Teléfono (con código de país)</label>
                                <div class="bc-input-box">
                                    <span class="bc-input-icon"><i class="ph ph-whatsapp-logo" style="color:#22c55e"></i></span>
                                    <input type="tel" 
                                           id="qrInputWaPhone" 
                                           placeholder="Ej: +51987654321" 
                                           class="qr-field-input" 
                                           autocomplete="off" 
                                           data-lpignore="true" 
                                           data-1p-ignore="true" 
                                           data-bwignore="true" 
                                           data-form-type="other">
                                </div>
                            </div>
                            <div class="qr-field-group" style="margin-top:0.6rem">
                                <label class="qr-field-label qr-field-label--sub">Mensaje Predefinido (Opcional)</label>
                                <textarea id="qrInputWaText" placeholder="Hola, me gustaría solicitar más información..." class="qr-field-textarea" style="min-height:75px" autocomplete="off" data-lpignore="true"></textarea>
                            </div>
                        </div>

                        <!-- Telegram Input -->
                        <div class="qr-input-group" data-qr-input="telegram" style="display:none">
                            <div class="qr-field-group">
                                <label class="qr-field-label qr-field-label--sub">Usuario, Grupo o Canal (@)</label>
                                <div class="bc-input-box">
                                    <span class="bc-input-icon"><i class="ph ph-telegram-logo" style="color:#0284c7"></i></span>
                                    <input type="text" 
                                           id="qrInputTgUser" 
                                           placeholder="romaagencia" 
                                           class="qr-field-input" 
                                           autocomplete="off" 
                                           data-lpignore="true" 
                                           data-1p-ignore="true" 
                                           data-bwignore="true" 
                                           data-form-type="other">
                                </div>
                            </div>
                        </div>

                        <!-- Correo Input -->
                        <div class="qr-input-group" data-qr-input="mailto" style="display:none">
                            <div class="qr-field-group">
                                <label class="qr-field-label qr-field-label--sub">Destinatario</label>
                                <div class="bc-input-box">
                                    <span class="bc-input-icon"><i class="ph ph-envelope"></i></span>
                                    <input type="email" 
                                           id="qrInputMailTo" 
                                           placeholder="contacto@romaagencia.com" 
                                           class="qr-field-input" 
                                           autocomplete="off" 
                                           data-lpignore="true" 
                                           data-1p-ignore="true" 
                                           data-bwignore="true" 
                                           data-form-type="other">
                                </div>
                            </div>
                            <div class="qr-field-group" style="margin-top:0.5rem">
                                <label class="qr-field-label qr-field-label--sub">Asunto</label>
                                <div class="bc-input-box">
                                    <span class="bc-input-icon"><i class="ph ph-chat-circle-dots"></i></span>
                                    <input type="text" 
                                           id="qrInputMailSubj" 
                                           placeholder="Consulta de servicios y cotización" 
                                           class="qr-field-input" 
                                           autocomplete="off" 
                                           data-lpignore="true" 
                                           data-1p-ignore="true" 
                                           data-bwignore="true" 
                                           data-form-type="other">
                                </div>
                            </div>
                            <div class="qr-field-group" style="margin-top:0.5rem">
                                <label class="qr-field-label qr-field-label--sub">Mensaje</label>
                                <textarea id="qrInputMailBody" placeholder="Escribe aquí tu mensaje..." class="qr-field-textarea" style="min-height:70px" autocomplete="off" data-lpignore="true"></textarea>
                            </div>
                        </div>

                        <!-- Evento Input -->
                        <div class="qr-input-group" data-qr-input="event" style="display:none">
                            <div class="qr-field-group">
                                <label class="qr-field-label qr-field-label--sub">Título del Evento</label>
                                <div class="bc-input-box">
                                    <span class="bc-input-icon"><i class="ph ph-calendar-check"></i></span>
                                    <input type="text" 
                                           id="qrInputEvtTitle" 
                                           placeholder="Reunión de Estrategia Roma" 
                                           class="qr-field-input" 
                                           autocomplete="off" 
                                           data-lpignore="true" 
                                           data-1p-ignore="true" 
                                           data-bwignore="true" 
                                           data-form-type="other">
                                </div>
                            </div>
                            <div class="qr-field-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin:0.5rem 0;">
                                <div>
                                    <label class="qr-field-label qr-field-label--sub">Inicio</label>
                                    <input type="datetime-local" id="qrInputEvtStart" class="qr-field-input">
                                </div>
                                <div>
                                    <label class="qr-field-label qr-field-label--sub">Fin</label>
                                    <input type="datetime-local" id="qrInputEvtEnd" class="qr-field-input">
                                </div>
                            </div>
                            <div class="qr-field-group">
                                <label class="qr-field-label qr-field-label--sub">Ubicación</label>
                                <div class="bc-input-box">
                                    <span class="bc-input-icon"><i class="ph ph-map-pin"></i></span>
                                    <input type="text" 
                                           id="qrInputEvtLoc" 
                                           placeholder="Oficina Principal Roma" 
                                           class="qr-field-input" 
                                           autocomplete="off" 
                                           data-lpignore="true" 
                                           data-1p-ignore="true" 
                                           data-bwignore="true" 
                                           data-form-type="other">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Wi-Fi Input -->
                        <div class="qr-input-group" data-qr-input="wifi" style="display:none">
                            <div class="qr-field-group">
                                <label class="qr-field-label qr-field-label--sub">Nombre de la red (SSID)</label>
                                <div class="bc-input-box">
                                    <span class="bc-input-icon"><i class="ph ph-wifi-high"></i></span>
                                    <input type="text" 
                                           id="qrInputWifiSsid" 
                                           placeholder="Mi Red WiFi" 
                                           class="qr-field-input" 
                                           autocomplete="off" 
                                           data-lpignore="true" 
                                           data-1p-ignore="true" 
                                           data-bwignore="true" 
                                           data-form-type="other">
                                </div>
                            </div>
                            <div class="qr-field-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-top:0.5rem">
                                <div class="qr-field-group">
                                    <label class="qr-field-label qr-field-label--sub">Clave de Red</label>
                                    <div class="bc-input-box">
                                        <span class="bc-input-icon"><i class="ph ph-key"></i></span>
                                        <input type="password" 
                                               id="qrInputWifiPass" 
                                               name="qr_wifi_key_secret_nonce" 
                                               placeholder="Contraseña" 
                                               class="qr-field-input" 
                                               autocomplete="off" 
                                               data-lpignore="true" 
                                               data-1p-ignore="true" 
                                               data-bwignore="true" 
                                               data-form-type="other">
                                        <div class="bc-input-actions">
                                            <button type="button" class="bc-action-btn" id="btnToggleWifiPass" title="Mostrar u ocultar contraseña">
                                                <i class="ph ph-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="qr-field-group">
                                    <label class="qr-field-label qr-field-label--sub">Seguridad</label>
                                    <select id="qrInputWifiType" class="qr-field-select">
                                        <option value="WPA" selected>WPA / WPA2</option>
                                        <option value="WEP">WEP</option>
                                        <option value="nopass">Sin contraseña (Abierta)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- vCard Input (Rediseñado en Cuadrícula 2 Columnas Elegante) -->
                        <div class="qr-input-group" data-qr-input="vcard" style="display:none">
                            <div class="qr-vcard-grid">
                                <!-- Fila 1: Nombre Completo -->
                                <div class="qr-field-group qr-vcard-full">
                                    <label class="qr-field-label qr-field-label--sub">Nombre Completo</label>
                                    <div class="bc-input-box">
                                        <span class="bc-input-icon"><i class="ph ph-user"></i></span>
                                        <input type="text" 
                                               id="qrInputVcardName" 
                                               value="Juan Pérez" 
                                               placeholder="Ej: Juan Pérez" 
                                               class="qr-field-input" 
                                               autocomplete="off" 
                                               data-lpignore="true" 
                                               data-1p-ignore="true" 
                                               data-bwignore="true" 
                                               data-form-type="other">
                                    </div>
                                </div>

                                <!-- Fila 2: Teléfono & Email -->
                                <div class="qr-field-group">
                                    <label class="qr-field-label qr-field-label--sub">Teléfono Móvil</label>
                                    <div class="bc-input-box">
                                        <span class="bc-input-icon"><i class="ph ph-phone"></i></span>
                                        <input type="tel" 
                                               id="qrInputVcardPhone" 
                                               value="+51 987 654 321" 
                                               placeholder="Ej: +51 987 654 321" 
                                               class="qr-field-input" 
                                               autocomplete="off" 
                                               data-lpignore="true" 
                                               data-1p-ignore="true" 
                                               data-bwignore="true" 
                                               data-form-type="other">
                                    </div>
                                </div>

                                <div class="qr-field-group">
                                    <label class="qr-field-label qr-field-label--sub">Correo Electrónico</label>
                                    <div class="bc-input-box">
                                        <span class="bc-input-icon"><i class="ph ph-envelope"></i></span>
                                        <input type="email" 
                                               id="qrInputVcardEmail" 
                                               value="juan@romaagencia.com" 
                                               placeholder="juan@romaagencia.com" 
                                               class="qr-field-input" 
                                               autocomplete="off" 
                                               data-lpignore="true" 
                                               data-1p-ignore="true" 
                                               data-bwignore="true" 
                                               data-form-type="other">
                                    </div>
                                </div>

                                <!-- Fila 3: Empresa & Cargo -->
                                <div class="qr-field-group">
                                    <label class="qr-field-label qr-field-label--sub">Empresa / Organización</label>
                                    <div class="bc-input-box">
                                        <span class="bc-input-icon"><i class="ph ph-buildings"></i></span>
                                        <input type="text" 
                                               id="qrInputVcardOrg" 
                                               value="Roma Agencia" 
                                               placeholder="Ej: Roma Agencia" 
                                               class="qr-field-input" 
                                               autocomplete="off" 
                                               data-lpignore="true" 
                                               data-1p-ignore="true" 
                                               data-bwignore="true" 
                                               data-form-type="other">
                                    </div>
                                </div>

                                <div class="qr-field-group">
                                    <label class="qr-field-label qr-field-label--sub">Cargo / Puesto (Opcional)</label>
                                    <div class="bc-input-box">
                                        <span class="bc-input-icon"><i class="ph ph-briefcase"></i></span>
                                        <input type="text" 
                                               id="qrInputVcardTitle" 
                                               placeholder="Ej: Director Creativo" 
                                               class="qr-field-input" 
                                               autocomplete="off" 
                                               data-lpignore="true" 
                                               data-1p-ignore="true" 
                                               data-bwignore="true" 
                                               data-form-type="other">
                                    </div>
                                </div>

                                <!-- Fila 4: Sitio Web / Enlace -->
                                <div class="qr-field-group qr-vcard-full">
                                    <label class="qr-field-label qr-field-label--sub">Sitio Web / Enlace (Opcional)</label>
                                    <div class="bc-input-box">
                                        <span class="bc-input-icon"><i class="ph ph-globe"></i></span>
                                        <input type="url" 
                                               id="qrInputVcardUrl" 
                                               placeholder="https://romaagencia.com" 
                                               class="qr-field-input" 
                                               autocomplete="off" 
                                               data-lpignore="true" 
                                               data-1p-ignore="true" 
                                               data-bwignore="true" 
                                               data-form-type="other">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Geolocalización Input -->
                        <div class="qr-input-group" data-qr-input="geo" style="display:none">
                            <div class="qr-field-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem">
                                <div class="qr-field-group">
                                    <label class="qr-field-label qr-field-label--sub">Latitud</label>
                                    <div class="bc-input-box">
                                        <span class="bc-input-icon"><i class="ph ph-map-pin"></i></span>
                                        <input type="text" id="qrInputGeoLat" placeholder="-12.0464" class="qr-field-input" autocomplete="off" data-lpignore="true">
                                    </div>
                                </div>
                                <div class="qr-field-group">
                                    <label class="qr-field-label qr-field-label--sub">Longitud</label>
                                    <div class="bc-input-box">
                                        <span class="bc-input-icon"><i class="ph ph-map-pin"></i></span>
                                        <input type="text" id="qrInputGeoLng" placeholder="-77.0428" class="qr-field-input" autocomplete="off" data-lpignore="true">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Texto Libre Input -->
                        <div class="qr-input-group" data-qr-input="text" style="display:none">
                            <label class="qr-field-label qr-field-label--sub">Texto a Codificar</label>
                            <textarea id="qrInputText" placeholder="Escribe tu texto aquí..." class="qr-field-textarea" style="min-height:95px" autocomplete="off" data-lpignore="true"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Estilo Visual del QR -->
                <div class="qr-card">
                    <div class="qr-card-header">
                        <span class="qr-card-title"><i class="ph ph-paint-brush"></i> Personalización QR</span>
                    </div>

                    <div class="qr-option-row">
                        <span class="qr-option-label">Color Principal</span>
                        <div class="qr-color-group">
                            <input type="color" id="qrColorDark" value="#000000" class="qr-color-picker">
                            <input type="text" id="qrColorDarkHex" value="#000000" maxlength="7" class="qr-field-input qr-color-hex">
                        </div>
                    </div>
                    
                    <div class="qr-option-row">
                        <span class="qr-option-label">Color Fondo</span>
                        <div class="qr-color-group">
                            <input type="color" id="qrColorLight" value="#ffffff" class="qr-color-picker">
                            <input type="text" id="qrColorLightHex" value="#ffffff" maxlength="7" class="qr-field-input qr-color-hex">
                        </div>
                    </div>

                    <div class="qr-option-row">
                        <span class="qr-option-label">Estilo de Puntos</span>
                        <select id="qrDotsStyle" class="qr-field-select" style="max-width:140px">
                            <option value="square" selected>Cuadrado</option>
                            <option value="dots">Puntos</option>
                            <option value="rounded">Redondeado</option>
                            <option value="classy">Elegante</option>
                        </select>
                    </div>
                    
                    <div class="qr-option-row">
                        <span class="qr-option-label">Estilo Esquinas</span>
                        <select id="qrCornersStyle" class="qr-field-select" style="max-width:140px">
                            <option value="square" selected>Cuadrado</option>
                            <option value="dot">Punto</option>
                            <option value="extra-rounded">Súper Redondo</option>
                        </select>
                    </div>

                    <div class="qr-option-row">
                        <span class="qr-option-label">Nivel Corrección</span>
                        <select id="qrErrorLevel" class="qr-field-select" style="max-width:140px">
                            <option value="L">L (7%)</option>
                            <option value="M">M (15%)</option>
                            <option value="Q">Q (25%)</option>
                            <option value="H" selected>H (30% Recomendado)</option>
                        </select>
                    </div>

                    <div class="qr-option-row" style="border-top:1px dashed var(--border-color);padding-top:0.6rem;margin-top:0.4rem">
                        <span class="qr-option-label" style="font-weight:600">Añadir Marco</span>
                        <select id="qrFrameSelect" class="qr-field-select" style="max-width:140px">
                            <option value="none" selected>Sin Marco</option>
                            <option value="scan-me">"Escanea Aquí"</option>
                            <option value="menu">"Ver Menú"</option>
                        </select>
                    </div>
                </div>

                <!-- Card 3: Logo Central QR -->
                <div class="qr-card">
                    <div class="qr-card-header">
                        <span class="qr-card-title"><i class="ph ph-image"></i> Logo Central (Opcional)</span>
                    </div>
                    
                    <input type="file" id="qrLogoInput" accept="image/png, image/jpeg, image/svg+xml" class="qr-field-input" style="padding:0.4rem">
                    
                    <div id="qrLogoColorContainer" class="qr-option-row" style="display:none;margin-top:0.5rem">
                        <span class="qr-option-label">Color del Logo (SVG)</span>
                        <input type="color" id="qrLogoColor" value="#000000" class="qr-color-picker">
                    </div>

                    <div id="qrLogoSizeContainer" class="qr-range-row" style="display:none;margin-top:0.5rem">
                        <div class="qr-range-header">
                            <span class="qr-range-label">Tamaño del Logo</span>
                            <span class="qr-range-value" id="qrLogoSizeValue">0.4</span>
                        </div>
                        <input type="range" id="qrLogoSize" min="0.1" max="0.5" step="0.05" value="0.4" class="qr-range-input">
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- CONTROLES CÓDIGO DE BARRAS                               -->
            <!-- ======================================================== -->
            <div id="barcodeControlsContainer" style="display:none">
                
                <!-- Card 1: Formato y Valor con Generador Inteligente -->
                <div class="qr-card">
                    <div class="qr-card-header">
                        <span class="qr-card-title"><i class="ph ph-barcode"></i> Formato y Valor</span>
                        <span class="qr-card-badge" id="barcodeFormatBadge">CODE128</span>
                    </div>

                    <div class="qr-field-group">
                        <label class="qr-field-label">Formato de Barras</label>
                        <select id="barcodeFormatSelect" class="qr-field-select">
                            <option value="CODE128" selected>CODE128 (Universal - Letras y Números)</option>
                            <option value="EAN13">EAN-13 (Comercial / Retail Internacional)</option>
                            <option value="UPC">UPC-A (Comercial EE.UU. / Retail)</option>
                            <option value="CODE39">CODE39 (Alfanumérico Industrial)</option>
                        </select>
                    </div>

                    <div class="qr-field-group" style="margin-top:0.75rem">
                        <label class="qr-field-label">
                            <span>Valor del Código</span>
                            <span id="barcodeCharCount" style="font-size:0.7rem;color:var(--text-muted);font-weight:normal">16 caracteres</span>
                        </label>
                        
                        <!-- Input con Atributos Estrictos Anti-Autofill del Navegador -->
                        <div class="bc-input-box">
                            <span class="bc-input-icon"><i class="ph ph-hash"></i></span>
                            <input type="text" 
                                   id="barcodeInputVal" 
                                   name="barcode_custom_code_payload" 
                                   value="Roma Agencia 2025" 
                                   placeholder="Ej: 123456789012" 
                                   autocomplete="off" 
                                   data-lpignore="true" 
                                   data-1p-ignore="true" 
                                   data-bwignore="true" 
                                   data-form-type="other" 
                                   aria-autocomplete="none" 
                                   spellcheck="false" 
                                   autocorrect="off" 
                                   autocapitalize="off">
                            <div class="bc-input-actions">
                                <button type="button" class="bc-action-btn" id="btnCopyBarcodeVal" title="Copiar valor al portapapeles">
                                    <i class="ph ph-copy"></i>
                                </button>
                                <button type="button" class="bc-action-btn" id="btnClearBarcodeVal" title="Limpiar campo">
                                    <i class="ph ph-x"></i>
                                </button>
                            </div>
                        </div>
                        <small class="bc-hint" id="barcodeHint">Admite texto y números.</small>
                    </div>

                    <!-- Generador Inteligente con Opción de Número Inicial -->
                    <div class="bc-generator-section">
                        <div class="bc-gen-header">
                            <span class="bc-gen-title"><i class="ph ph-sparkle"></i> Generador Inteligente</span>
                            <div class="bc-mode-pills">
                                <button type="button" class="bc-mode-pill active" id="btnModeSeq">Secuencial (+1)</button>
                                <button type="button" class="bc-mode-pill" id="btnModeRand">Aleatorio</button>
                            </div>
                        </div>

                        <!-- Panel Secuencial (Por defecto) -->
                        <div id="bcSeqPanel" class="bc-seq-controls">
                            <div class="bc-seq-inputs">
                                <div>
                                    <label class="bc-field-mini-label">Número Inicial / Base:</label>
                                    <input type="number" 
                                           id="barcodeStartNum" 
                                           value="1000" 
                                           min="0" 
                                           class="qr-field-input" 
                                           autocomplete="off" 
                                           data-lpignore="true" 
                                           data-1p-ignore="true" 
                                           data-bwignore="true" 
                                           data-form-type="other">
                                </div>
                                <div>
                                    <label class="bc-field-mini-label" id="barcodePrefixLabel">Prefijo (Opcional):</label>
                                    <input type="text" 
                                           id="barcodePrefix" 
                                           placeholder="Ej: ROM-, PRD-" 
                                           class="qr-field-input" 
                                           autocomplete="off" 
                                           data-lpignore="true" 
                                           data-1p-ignore="true" 
                                           data-bwignore="true" 
                                           data-form-type="other">
                                </div>
                            </div>

                            <div class="bc-seq-actions">
                                <button type="button" class="bc-btn-generate-seq" id="btnGenerateNextBarcode" title="Genera el código con el número inicial y aumenta +1 para la siguiente generación">
                                    <i class="ph ph-lightning"></i> Generar / Siguiente (+1)
                                </button>
                                <button type="button" class="bc-step-btn" id="btnStepDownBarcode" title="Restar 1 al contador">
                                    <i class="ph ph-minus"></i>
                                </button>
                                <button type="button" class="bc-step-btn" id="btnStepUpBarcode" title="Sumar 1 al contador">
                                    <i class="ph ph-plus"></i>
                                </button>
                                <button type="button" class="bc-step-btn" id="btnResetSeqBarcode" title="Restablecer contador a 1000">
                                    <i class="ph ph-arrow-counter-clockwise"></i>
                                </button>
                            </div>

                            <div class="bc-hint-box" id="bcSeqHint">
                                <i class="ph ph-info"></i>
                                <span><b>Secuencial:</b> Toma tu número inicial, genera el código y calcula el checksum oficial si usas EAN-13/UPC.</span>
                            </div>
                        </div>

                        <!-- Panel Aleatorio con Número Inicial -->
                        <div id="bcRandPanel" class="bc-seq-controls" style="display:none">
                            <div class="bc-seq-inputs">
                                <div>
                                    <label class="bc-field-mini-label" id="barcodeRandStartLabel">Iniciar con Número / Prefijo:</label>
                                    <input type="text" 
                                           id="barcodeRandStartNum" 
                                           value="775" 
                                           placeholder="Ej: 775, 100, 84..." 
                                           class="qr-field-input" 
                                           autocomplete="off" 
                                           data-lpignore="true" 
                                           data-1p-ignore="true" 
                                           data-bwignore="true" 
                                           data-form-type="other"
                                           style="font-family:monospace;font-weight:600">
                                </div>
                                <div id="barcodeRandLenGroup">
                                    <label class="bc-field-mini-label" id="barcodeRandLenLabel">Longitud Total:</label>
                                    <input type="number" 
                                           id="barcodeRandLen" 
                                           value="13" 
                                           min="4" 
                                           max="30" 
                                           class="qr-field-input" 
                                           autocomplete="off" 
                                           data-lpignore="true"
                                           data-1p-ignore="true" 
                                           data-bwignore="true" 
                                           data-form-type="other"
                                           style="text-align:center;font-weight:600">
                                </div>
                            </div>

                            <div class="bc-seq-actions">
                                <button type="button" class="bc-btn-generate-seq" id="btnRandomBarcode" title="Genera un código aleatorio que comienza con el número indicado">
                                    <i class="ph ph-shuffle"></i> Generar Aleatorio con Inicio
                                </button>
                            </div>

                            <div class="bc-hint-box" id="bcRandHint">
                                <i class="ph ph-info"></i>
                                <span><b>Aleatorio:</b> Inicia con <b id="bcRandStartDisplay">775</b> y completa los dígitos restantes al azar con checksum válido.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Generación Secuencial Masiva (Lote ZIP) -->
                <div class="qr-card">
                    <div class="qr-card-header">
                        <span class="qr-card-title"><i class="ph ph-stack"></i> Generación Masiva (Lote)</span>
                        <span class="bc-bulk-badge" id="barcodeBulkCountBadge">51 códigos listos</span>
                    </div>

                    <div class="bc-bulk-section">
                        <div class="bc-bulk-row">
                            <div>
                                <label class="bc-field-mini-label">Desde:</label>
                                <input type="number" id="barcodeSeqStart" placeholder="100" value="100" class="qr-field-input">
                            </div>
                            <div class="bc-bulk-arrow">➔</div>
                            <div>
                                <label class="bc-field-mini-label">Hasta:</label>
                                <input type="number" id="barcodeSeqEnd" placeholder="150" value="150" class="qr-field-input">
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline bc-bulk-btn" id="btnBulkBarcode">
                            <i class="ph ph-file-zip"></i> Descargar ZIP con Códigos SVG
                        </button>
                    </div>
                </div>

                <!-- Card 3: Personalización Visual de Barras -->
                <div class="qr-card">
                    <div class="qr-card-header">
                        <span class="qr-card-title"><i class="ph ph-sliders"></i> Personalización de Barras</span>
                    </div>
                    
                    <div class="qr-option-row">
                        <span class="qr-option-label">Color de Barras</span>
                        <div class="qr-color-group">
                            <input type="color" id="barcodeLineColor" value="#000000" class="qr-color-picker">
                            <input type="text" id="barcodeLineColorHex" value="#000000" maxlength="7" class="qr-field-input qr-color-hex">
                        </div>
                    </div>
                    
                    <div class="qr-option-row">
                        <span class="qr-option-label">Color de Fondo</span>
                        <div class="qr-color-group">
                            <input type="color" id="barcodeBgColor" value="#ffffff" class="qr-color-picker">
                            <input type="text" id="barcodeBgColorHex" value="#ffffff" maxlength="7" class="qr-field-input qr-color-hex">
                        </div>
                    </div>

                    <div class="qr-option-row" style="border-top:1px dashed var(--border-color);padding-top:0.5rem;margin-top:0.4rem">
                        <span class="qr-option-label">Mostrar Texto Numérico</span>
                        <label class="app-switch qr-switch-sm">
                            <input type="checkbox" id="barcodeShowText" checked>
                            <span class="app-switch-slider"></span>
                        </label>
                    </div>

                    <div id="barcodeTypographyControls" style="display:flex; flex-direction:column; gap:0.65rem; margin-top:0.4rem; padding-top:0.5rem; border-top:1px dashed var(--border-color);">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.6rem;">
                            <div>
                                <label class="bc-field-mini-label" style="display:flex; align-items:center; gap:4px;">
                                    <i class="ph ph-text-t"></i> Tipografía:
                                </label>
                                <select id="barcodeFontFamily" class="qr-field-select" style="font-size:0.8rem; padding:0.45rem 0.5rem; font-weight:600;">
                                    <option value="Inter" selected>Inter (Recomendada)</option>
                                    <option value="Roboto">Roboto</option>
                                    <option value="Montserrat">Montserrat</option>
                                    <option value="Arial">Arial</option>
                                    <option value="monospace">Courier / Monospace</option>
                                </select>
                            </div>
                            <div>
                                <label class="bc-field-mini-label" style="display:flex; align-items:center; gap:4px;">
                                    <i class="ph ph-text-b"></i> Grosor / Peso:
                                </label>
                                <select id="barcodeFontWeight" class="qr-field-select" style="font-size:0.8rem; padding:0.45rem 0.5rem; font-weight:600;">
                                    <option value="600" selected>Semi-Negrita (600)</option>
                                    <option value="700">Negrita (700)</option>
                                    <option value="500">Media (500)</option>
                                    <option value="400">Regular (400)</option>
                                </select>
                            </div>
                        </div>

                        <div class="qr-range-row" style="margin-top:0.15rem">
                            <div class="qr-range-header">
                                <span class="qr-range-label">Tamaño del Texto</span>
                                <span class="qr-range-value" id="barcodeFontSizeValue">20px</span>
                            </div>
                            <input type="range" id="barcodeFontSize" min="10" max="40" step="1" value="20" class="qr-range-input">
                        </div>
                    </div>
                </div>
            </div>

        </div> <!-- /.qr-sidebar -->

        <!-- Columna Derecha (Lienzo de Vista Previa & Exportación) -->
        <div class="qr-preview-area">
            
            <!-- Lienzo de Vista Previa -->
            <div class="qr-stage-card">
                <div class="qr-stage-header">
                    <div class="qr-stage-info">
                        <span class="stage-pill" id="stageCodeTypeBadge"><i class="ph ph-qr-code"></i> QR</span>
                        <span class="stage-subpill" id="stageCodeLenBadge">Listo</span>
                    </div>
                    <div class="qr-stage-actions">
                        <button type="button" class="qr-stage-btn" id="btnCopyRawValue" title="Copiar el texto / enlace actual">
                            <i class="ph ph-copy"></i> Copiar Texto
                        </button>
                    </div>
                </div>

                <div class="qr-stage-body">
                    <!-- Contenedor del Canvas QR -->
                    <div id="qrFrameWrapper" class="qr-frame-wrapper">
                        <div id="qrFrameHeader" class="qr-frame-header" style="display:none">ESCANEA AQUÍ</div>
                        <div id="qrCanvasContainer"></div>
                    </div>

                    <!-- Contenedor SVG Barcode -->
                    <div id="barcodeRenderBox" class="qr-barcode-box" style="display:none">
                        <svg id="barcodeSvgContainer"></svg>
                    </div>
                </div>
            </div>

            <!-- Panel de Exportación -->
            <div class="qr-export-panel">
                <div class="qr-export-header">
                    <span class="qr-export-title"><i class="ph ph-download-simple"></i> Opciones de Exportación</span>
                    <div class="qr-dpi-control">
                        <span>Calidad:</span>
                        <select id="exportDpiSelect" class="qr-export-dpi">
                            <option value="1">1x (Web)</option>
                            <option value="2" selected>2x (Alta Definición)</option>
                            <option value="4">4x (Impresión 300 DPI)</option>
                        </select>
                    </div>
                </div>

                <div class="qr-export-grid">
                    <button type="button" class="qr-export-btn qr-btn-primary-glow" id="btnDownloadCodePNG" title="Descargar como imagen PNG de alta resolución">
                        <i class="ph ph-image"></i> Descargar PNG
                    </button>
                    <button type="button" class="btn btn-outline qr-export-btn" id="btnDownloadCodeSVG" title="Descargar archivo vectorial SVG">
                        <i class="ph ph-file-svg"></i> SVG (Vector)
                    </button>
                    <button type="button" class="btn btn-outline qr-export-btn" id="btnDownloadCodePDF" title="Generar hoja de impresión en PDF">
                        <i class="ph ph-file-pdf"></i> PDF (Folleto)
                    </button>
                    <button type="button" class="btn btn-outline qr-export-btn" id="btnCopyClipboard" title="Copiar imagen directamente al portapapeles">
                        <i class="ph ph-copy-simple"></i> Copiar Imagen
                    </button>
                    <button type="button" class="btn btn-outline qr-export-btn" id="btnShareLink" title="Crear enlace público compartible">
                        <i class="ph ph-share-network"></i> Compartir
                    </button>
                </div>
            </div>

            <!-- Panel de Historial Reciente -->
            <div class="qr-history-panel">
                <div class="qr-history-header">
                    <span class="qr-history-title"><i class="ph ph-clock-counter-clockwise"></i> Historial Reciente</span>
                    <button type="button" class="qr-history-clear-btn" id="btnClearHistory">
                        <i class="ph ph-trash"></i> Limpiar Historial
                    </button>
                </div>
                <div id="qrHistoryList" class="qr-history-list">
                    <!-- History items will be inserted dynamically -->
                </div>
            </div>

        </div> <!-- /.qr-preview-area -->

    </div> <!-- /.qr-layout -->
</div><!-- /.herr-tab-content (QR/Barras) -->

    <!-- Save Modal -->
    <div class="save-modal-overlay" id="saveModal">
        <div class="save-modal">
            <h3><i class="ph ph-floppy-disk"></i> Guardar Paleta</h3>
            <input type="text" id="paletteName" placeholder="Nombre de la paleta..." autofocus>
            <div class="save-modal-actions">
                <button class="btn btn-outline" id="btnCancelSave">Cancelar</button>
                <button class="btn btn-primary" id="btnConfirmSave"><i class="ph ph-check"></i> Guardar</button>
            </div>
        </div>
    </div>

    <!-- Export Image/PDF Modal -->
    <div class="save-modal-overlay" id="exportModal">
        <div class="save-modal save-modal--wide">
            <h3><i class="ph ph-export"></i> Opciones de Exportación</h3>
            <p class="modal-description">Selecciona qué previsualizaciones deseas incluir en tu archivo descargable.</p>
            <div class="modal-options-list">
                
                <div class="modal-option-row">
                    <span class="modal-option-label">Incluir Cards</span>
                    <label class="app-switch">
                        <input type="checkbox" id="expCards" checked>
                        <span class="app-switch-slider"></span>
                    </label>
                </div>

                <div class="modal-option-row">
                    <span class="modal-option-label">Incluir Buttons</span>
                    <label class="app-switch">
                        <input type="checkbox" id="expButtons" checked>
                        <span class="app-switch-slider"></span>
                    </label>
                </div>

                <div class="modal-option-row">
                    <span class="modal-option-label">Incluir Dashboard</span>
                    <label class="app-switch">
                        <input type="checkbox" id="expDashboard">
                        <span class="app-switch-slider"></span>
                    </label>
                </div>

                <div class="modal-option-row">
                    <span class="modal-option-label">Incluir Typography</span>
                    <label class="app-switch">
                        <input type="checkbox" id="expTypography">
                        <span class="app-switch-slider"></span>
                    </label>
                </div>

                <div class="modal-option-row">
                    <span class="modal-option-label">Incluir Badges</span>
                    <label class="app-switch">
                        <input type="checkbox" id="expBadges">
                        <span class="app-switch-slider"></span>
                    </label>
                </div>

                <div class="modal-option-row modal-option-row--last">
                    <span class="modal-option-label">Incluir Gradients</span>
                    <label class="app-switch">
                        <input type="checkbox" id="expGradients">
                        <span class="app-switch-slider"></span>
                    </label>
                </div>

            </div>
            <div class="save-modal-actions">
                <button class="btn btn-outline" id="btnCancelExport">Cancelar</button>
                <button class="btn btn-primary" id="btnConfirmExport"><i class="ph ph-download-simple"></i> Generar</button>
            </div>
        </div>
    </div>

<!-- Tab Content: BioLinks -->
<div class="herr-tab-content" data-tool-content="linktree">
    <div class="paleta-layout" style="gap:2rem;">
        
        <!-- Lista de perfiles -->
        <div class="paleta-main" style="flex:1" id="linktreeListSection">
            <div class="paleta-saved-header" style="justify-content:space-between">
                <div><i class="ph ph-link"></i> Perfiles BioLink</div>
                <button class="btn btn-primary" onclick="linktreeNew()"><i class="ph ph-plus"></i> Nuevo</button>
            </div>
            <div id="linktreeList" class="paleta-saved-grid">
                <!-- Se llenará con JS -->
            </div>
        </div>

        <!-- Editor de perfil -->
        <div class="paleta-main" style="flex:2; display:none;" id="linktreeEditorSection">
            <div class="paleta-saved-header" style="justify-content:space-between; margin-bottom:1rem">
                <div><i class="ph ph-pencil-simple"></i> Editor de BioLink</div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-outline" onclick="linktreeCancel()"><i class="ph ph-arrow-left"></i> Volver</button>
                    <button class="btn btn-primary" onclick="linktreeSave()"><i class="ph ph-floppy-disk"></i> Guardar</button>
                </div>
            </div>

            <div class="biolink-editor-layout">
                <div class="biolink-editor-left">
                    <!-- Campos del perfil -->
                    <div class="biolink-card">
                        <h4 style="margin-bottom:1.5rem;font-weight:700;font-size:1.1rem;color:var(--text-main);"><i class="ph ph-user-circle"></i> Información Básica</h4>
                        <input type="hidden" id="lt_id" value="">
                        
                        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.25rem;">
                            <label style="font-weight:600;font-size:0.85rem;color:var(--text-muted)">Slug (URL Corta) *</label>
                            <div class="biolink-input-group">
                                <span>/l/</span>
                                <input type="text" id="lt_slug" placeholder="mi-marca">
                            </div>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.25rem;">
                            <label style="font-weight:600;font-size:0.85rem;color:var(--text-muted)">Título *</label>
                            <input type="text" id="lt_title" class="biolink-input" placeholder="Nombre de la Marca">
                        </div>

                        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.25rem;">
                            <label style="font-weight:600;font-size:0.85rem;color:var(--text-muted)">Biografía</label>
                            <textarea id="lt_bio" class="biolink-textarea" placeholder="Descripción corta..."></textarea>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            <label style="font-weight:600;font-size:0.85rem;color:var(--text-muted)">Imagen de Perfil</label>
                            <input type="file" id="lt_image" class="biolink-input" style="padding:0.5rem; border:1.5px dashed var(--border-color); cursor:pointer;" accept="image/*">
                        </div>
                    </div>

                    <!-- Personalización -->
                    <div class="biolink-card">
                        <h4 style="margin-bottom:1.5rem;font-weight:700;font-size:1.1rem;color:var(--text-main);"><i class="ph ph-paint-brush"></i> Tema y Diseño</h4>
                        
                        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.25rem;">
                            <label style="font-size:0.85rem; font-weight:600; color:var(--text-muted)">Tema Predefinido</label>
                            <select id="lt_theme_preset" class="biolink-input" onchange="linktreeApplyTheme(this.value)">
                                <option value="custom">Personalizado</option>
                                <option value="cyberpunk">Neón Oscuro (Cyberpunk)</option>
                                <option value="minimal">Minimalista Blanco</option>
                                <option value="pastel">Pastel Elegante</option>
                                <option value="corporate">Corporativo Azul</option>
                            </select>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.25rem;">
                            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                <label style="font-size:0.85rem; font-weight:600; color:var(--text-muted)">Tipografía</label>
                                <select id="lt_font_family" class="biolink-input">
                                    <option value="Inter">Inter (Moderna)</option>
                                    <option value="Roboto">Roboto (Clásica)</option>
                                    <option value="Playfair Display">Playfair (Elegante)</option>
                                    <option value="Space Grotesk">Space Grotesk (Tech)</option>
                                    <option value="Comic Neue">Comic Neue (Divertida)</option>
                                </select>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                <label style="font-size:0.85rem; font-weight:600; color:var(--text-muted)">Formato del Botón</label>
                                <select id="lt_btn_style" class="biolink-input">
                                    <option value="rounded-md">Redondeado (Suave)</option>
                                    <option value="rounded-full">Píldora (Redondo total)</option>
                                    <option value="rounded-none">Cuadrado</option>
                                </select>
                            </div>
                        </div>

                        <label style="font-size:0.85rem; font-weight:600; display:block; margin-bottom:0.75rem; color:var(--text-muted)">Colores Personalizados</label>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.5rem;">
                            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                <label style="font-size:0.85rem; font-weight:500;">Color de Fondo</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="color" id="lt_bg_color" value="#f4f4f5" class="qr-color-picker" style="width:40px;height:40px;border-radius:8px;padding:2px;">
                                </div>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                <label style="font-size:0.85rem; font-weight:500;">Texto General</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="color" id="lt_text_color" value="#18181b" class="qr-color-picker" style="width:40px;height:40px;border-radius:8px;padding:2px;">
                                </div>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                <label style="font-size:0.85rem; font-weight:500;">Fondo Botón</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="color" id="lt_btn_color" value="#ffffff" class="qr-color-picker" style="width:40px;height:40px;border-radius:8px;padding:2px;">
                                </div>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                <label style="font-size:0.85rem; font-weight:500;">Texto Botón</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="color" id="lt_btn_text_color" value="#18181b" class="qr-color-picker" style="width:40px;height:40px;border-radius:8px;padding:2px;">
                                </div>
                            </div>
                        </div>

                        <div style="display:flex; align-items:center; justify-content:space-between; padding-top:1rem; border-top:1px solid var(--border-color);">
                            <div>
                                <h5 style="font-weight:600; font-size:0.9rem; margin:0;">Ocultar Marca de Agua</h5>
                                <p style="font-size:0.75rem; color:var(--text-muted); margin:0;">Elimina el texto "Creado por Roma Agencia" al final de la página.</p>
                            </div>
                            <label class="app-switch" style="margin:0;">
                                <input type="checkbox" id="lt_hide_watermark">
                                <span class="app-switch-slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Enlaces -->
                    <div class="biolink-card">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
                            <h4 style="font-weight:700;font-size:1.1rem;color:var(--text-main);margin:0;"><i class="ph ph-squares-four"></i> Contenido / Bloques</h4>
                        </div>
                        <div style="display:flex; gap:0.5rem; margin-bottom:1.5rem; flex-wrap:wrap;">
                            <button class="btn btn-outline" style="padding:6px 14px;font-size:0.85rem; border-radius:12px;" onclick="linktreeAddLink('link')"><i class="ph ph-link"></i> Enlace</button>
                            <button class="btn btn-outline" style="padding:6px 14px;font-size:0.85rem; border-radius:12px;" onclick="linktreeAddLink('whatsapp')"><i class="ph ph-whatsapp-logo"></i> WhatsApp</button>
                            <button class="btn btn-outline" style="padding:6px 14px;font-size:0.85rem; border-radius:12px;" onclick="linktreeAddLink('youtube')"><i class="ph ph-youtube-logo"></i> YouTube</button>
                            <button class="btn btn-outline" style="padding:6px 14px;font-size:0.85rem; border-radius:12px;" onclick="linktreeAddLink('spotify')"><i class="ph ph-spotify-logo"></i> Spotify</button>
                            <button class="btn btn-outline" style="padding:6px 14px;font-size:0.85rem; border-radius:12px;" onclick="linktreeAddLink('map')"><i class="ph ph-map-pin"></i> Mapa</button>
                            <button class="btn btn-outline" style="padding:6px 14px;font-size:0.85rem; border-radius:12px;" onclick="linktreeAddLink('text')"><i class="ph ph-text-t"></i> Título</button>
                            <button class="btn btn-outline" style="padding:6px 14px;font-size:0.85rem; border-radius:12px;" onclick="linktreeAddLink('faq')"><i class="ph ph-question"></i> FAQ</button>
                        </div>
                        
                        <div id="lt_links_container" style="display:flex; flex-direction:column; gap:0.75rem;">
                            <!-- Enlaces dinámicos -->
                        </div>
                    </div>
                </div>

                <!-- Preview (Mockup de celular) -->
                <div class="biolink-editor-right" id="lt_preview_wrapper">
                    <div style="width:340px; border: 12px solid #1f2937; border-radius: 2.5rem; height: 700px; overflow:hidden; position:relative; background:#f4f4f5; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), inset 0 0 0 2px #374151;" id="lt_preview_box">
                        <!-- Notch -->
                        <div style="position:absolute; top:0; left:50%; transform:translateX(-50%); width:120px; height:24px; background:#1f2937; border-bottom-left-radius:16px; border-bottom-right-radius:16px; z-index:10;"></div>
                        <!-- Botones laterales simulados -->
                        <div style="position:absolute; top:120px; left:-14px; width:4px; height:40px; background:#374151; border-radius: 4px 0 0 4px;"></div>
                        <div style="position:absolute; top:180px; left:-14px; width:4px; height:60px; background:#374151; border-radius: 4px 0 0 4px;"></div>
                        <div style="position:absolute; top:140px; right:-14px; width:4px; height:60px; background:#374151; border-radius: 0 4px 4px 0;"></div>
                        
                        <div style="padding:3.5rem 1.25rem 2rem; text-align:center; height:100%; display:flex; flex-direction:column; align-items:center;" id="lt_preview_content">
                            <!-- Generado en vivo -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- External Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
(function() {
    'use strict';

    // =========================================================================
    // State
    // =========================================================================
    let currentHex = '#0c36a6';
    let currentSecondaryHex = '#10b981';
    let currentScale = {};
    let currentSecondaryScale = {};
    let currentPreview = 'cards';

    // =========================================================================
    // Color Conversion
    // =========================================================================
    function hexToRgb(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
        return {
            r: parseInt(hex.substring(0, 2), 16),
            g: parseInt(hex.substring(2, 4), 16),
            b: parseInt(hex.substring(4, 6), 16)
        };
    }

    function rgbToHex(r, g, b) {
        return '#' + [r, g, b].map(function(x) {
            var h = Math.round(Math.max(0, Math.min(255, x))).toString(16);
            return h.length === 1 ? '0' + h : h;
        }).join('');
    }

    function rgbToHsl(r, g, b) {
        r /= 255; g /= 255; b /= 255;
        var max = Math.max(r, g, b), min = Math.min(r, g, b);
        var h, s, l = (max + min) / 2;
        if (max === min) {
            h = s = 0;
        } else {
            var d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            switch (max) {
                case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
                case g: h = ((b - r) / d + 2) / 6; break;
                case b: h = ((r - g) / d + 4) / 6; break;
            }
        }
        return { h: h * 360, s: s * 100, l: l * 100 };
    }

    function hslToRgb(h, s, l) {
        h /= 360; s /= 100; l /= 100;
        var r, g, b;
        if (s === 0) {
            r = g = b = l;
        } else {
            function hue2rgb(p, q, t) {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1/6) return p + (q - p) * 6 * t;
                if (t < 1/2) return q;
                if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                return p;
            }
            var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            var p = 2 * l - q;
            r = hue2rgb(p, q, h + 1/3);
            g = hue2rgb(p, q, h);
            b = hue2rgb(p, q, h - 1/3);
        }
        return { r: Math.round(r * 255), g: Math.round(g * 255), b: Math.round(b * 255) };
    }

    function hexToHsl(hex) {
        var rgb = hexToRgb(hex);
        return rgbToHsl(rgb.r, rgb.g, rgb.b);
    }

    function hslToHex(h, s, l) {
        var rgb = hslToRgb(h, s, l);
        return rgbToHex(rgb.r, rgb.g, rgb.b);
    }

    // =========================================================================
    // Scale Generation
    // =========================================================================
    var shadeConfig = [
        { shade: 50,  l: 97, sFactor: 0.80 },
        { shade: 100, l: 94, sFactor: 0.85 },
        { shade: 200, l: 86, sFactor: 0.90 },
        { shade: 300, l: 76, sFactor: 0.95 },
        { shade: 400, l: 64, sFactor: 1.00 },
        { shade: 500, l: 50, sFactor: 1.00 },
        { shade: 600, l: 40, sFactor: 1.00 },
        { shade: 700, l: 32, sFactor: 0.95 },
        { shade: 800, l: 24, sFactor: 0.90 },
        { shade: 900, l: 16, sFactor: 0.85 },
        { shade: 950, l: 9,  sFactor: 0.80 }
    ];

    function generateScale(hex) {
        var hsl = hexToHsl(hex);
        var scale = {};
        shadeConfig.forEach(function(cfg) {
            scale[cfg.shade] = hslToHex(hsl.h, Math.min(100, hsl.s * cfg.sFactor), cfg.l);
        });
        return scale;
    }

    function findClosestShade(hex, scale) {
        var hsl = hexToHsl(hex);
        var closest = 500;
        var minDiff = Infinity;
        Object.keys(scale).forEach(function(shade) {
            var shadeHsl = hexToHsl(scale[shade]);
            var diff = Math.abs(shadeHsl.l - hsl.l);
            if (diff < minDiff) {
                minDiff = diff;
                closest = parseInt(shade);
            }
        });
        return closest;
    }

    // =========================================================================
    // Color Harmony
    // =========================================================================
    function getHarmonyColors(hex, mode) {
        var hsl = hexToHsl(hex);
        switch (mode) {
            case 'complementary': return [hslToHex((hsl.h + 180) % 360, hsl.s, hsl.l)];
            case 'analogous': return [hslToHex((hsl.h + 330) % 360, hsl.s, hsl.l), hslToHex((hsl.h + 30) % 360, hsl.s, hsl.l)];
            case 'triadic': return [hslToHex((hsl.h + 120) % 360, hsl.s, hsl.l), hslToHex((hsl.h + 240) % 360, hsl.s, hsl.l)];
            case 'split': return [hslToHex((hsl.h + 150) % 360, hsl.s, hsl.l), hslToHex((hsl.h + 210) % 360, hsl.s, hsl.l)];
            default: return [];
        }
    }

    var harmonyLabels = {
        'complementary': ['Complementario'],
        'analogous': ['Análogo 1', 'Análogo 2'],
        'triadic': ['Tríada 1', 'Tríada 2'],
        'split': ['Split 1', 'Split 2']
    };

    // =========================================================================
    // WCAG Contrast
    // =========================================================================
    function getRelativeLuminance(hex) {
        var rgb = hexToRgb(hex);
        var vals = [rgb.r, rgb.g, rgb.b].map(function(c) {
            c = c / 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * vals[0] + 0.7152 * vals[1] + 0.0722 * vals[2];
    }

    function getContrastRatio(hex1, hex2) {
        var l1 = getRelativeLuminance(hex1);
        var l2 = getRelativeLuminance(hex2);
        var lighter = Math.max(l1, l2);
        var darker = Math.min(l1, l2);
        return (lighter + 0.05) / (darker + 0.05);
    }

    function getWcagLevel(ratio) {
        if (ratio >= 7) return 'AAA';
        if (ratio >= 4.5) return 'AA';
        return 'Fail';
    }

    // =========================================================================
    // Clipboard
    // =========================================================================
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                if (window.showToast) window.showToast('Copiado al portapapeles', 'success');
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        if (window.showToast) window.showToast('Copiado al portapapeles', 'success');
    }

    // =========================================================================
    // Export Functions
    // =========================================================================
    function exportAsCSS(scale) {
        var css = ':root {\n';
        Object.keys(scale).forEach(function(shade) {
            css += '  --color-primary-' + shade + ': ' + scale[shade] + ';\n';
        });
        css += '}';
        return css;
    }

    function exportAsJSON(scale) {
        return JSON.stringify(scale, null, 2);
    }

    function exportAsTailwind(scale) {
        var config = 'module.exports = {\n  theme: {\n    extend: {\n      colors: {\n        primary: {\n';
        Object.keys(scale).forEach(function(shade) {
            config += "          " + shade + ": '" + scale[shade] + "',\n";
        });
        config += '        }\n      }\n    }\n  }\n}';
        return config;
    }

    let pendingExportType = null;
    function handleExport(type) {
        if (type === 'png' || type === 'pdf') {
            pendingExportType = type;
            document.getElementById('exportModal').classList.add('active');
            return;
        }

        var output;
        switch (type) {
            case 'css': output = exportAsCSS(currentScale); break;
            case 'json': output = exportAsJSON(currentScale); break;
            case 'tailwind': output = exportAsTailwind(currentScale); break;
            default: return;
        }
        copyToClipboard(output);
    }

    // =========================================================================
    // Render Scales
    // =========================================================================
    function isLightColor(hex) {
        var hsl = hexToHsl(hex);
        return hsl.l > 55;
    }

    function renderScaleRow(label, scale, baseHex, isPrimary) {
        var closestShade = isPrimary ? findClosestShade(baseHex, scale) : -1;
        var html = '<div class="paleta-scale">';
        html += '<div class="paleta-scale-header">';
        html += '  <div class="paleta-scale-info">';
        html += '    <span class="paleta-scale-title"><i class="ph ph-swatch"></i> Escala ' + label + '</span>';
        html += '    <span class="paleta-scale-seed-tag">Base: ' + baseHex.toUpperCase() + '</span>';
        html += '  </div>';
        html += '</div>';
        html += '<div class="paleta-scale-row">';

        shadeConfig.forEach(function(cfg) {
            var color = scale[cfg.shade];
            var isActive = (cfg.shade === closestShade);
            var textClass = isLightColor(color) ? ' dark-text' : ' light-text';
            var activeClass = isActive ? ' active' : '';
            html += '<div class="paleta-swatch' + activeClass + textClass + '" style="background-color:' + color + '" data-hex="' + color + '" title="Clic para copiar ' + color + '">';
            html += '  <div class="paleta-swatch-top">';
            html += '    <span class="paleta-swatch-shade">' + cfg.shade + '</span>';
            if (isActive) html += '    <span class="paleta-swatch-base-badge">BASE</span>';
            html += '  </div>';
            html += '  <div class="paleta-swatch-bottom">';
            html += '    <span class="paleta-swatch-hex">' + color.toUpperCase() + '</span>';
            html += '    <i class="ph ph-copy paleta-swatch-copy-icon"></i>';
            html += '  </div>';
            html += '</div>';
        });

        html += '</div></div>';
        return html;
    }

    function renderScales(baseHex, secHex, harmonyMode) {
        var container = document.getElementById('scalesContainer');
        var scale = generateScale(baseHex);
        var secScale = generateScale(secHex);
        var html = renderScaleRow('Primary', scale, baseHex, true);
        
        var isSecondaryEnabled = document.getElementById('enableSecondary').checked;
        if (isSecondaryEnabled) {
            html += renderScaleRow('Secondary', secScale, secHex, true);
        }

        if (harmonyMode !== 'auto') {
            var harmonyColors = getHarmonyColors(baseHex, harmonyMode);
            var labels = harmonyLabels[harmonyMode] || [];
            harmonyColors.forEach(function(hColor, i) {
                var hScale = generateScale(hColor);
                html += renderScaleRow(labels[i] || ('Harmony ' + (i + 1)), hScale, hColor, false);
            });
        }
        
        container.innerHTML = html;

        // Attach click handlers to swatches with feedback
        container.querySelectorAll('.paleta-swatch').forEach(function(swatch) {
            swatch.addEventListener('click', function() {
                var hex = this.getAttribute('data-hex').toUpperCase();
                copyToClipboard(hex);
                showNoticeToast('Color ' + hex + ' copiado al portapapeles');
            });
        });
    }



    // =========================================================================
    // Preview Renderers
    // =========================================================================

    // --- Cards Preview ---
    function renderCardsPreview(scale, secScale) {
        if (!secScale) secScale = scale; // fallback
        return '<div class="preview-cards-grid">' +

            // Card 1: Expense Tracker
            '<div class="preview-card-demo" style="overflow:hidden;background:var(--bg-surface);border-radius:var(--radius-md);border:1px solid var(--border-color);box-shadow:var(--shadow-sm)">' +
                '<div style="height:4px;background:' + scale[600] + '"></div>' +
                '<div style="padding:20px">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">' +
                        '<span style="font-weight:600;font-size:0.9rem;color:var(--text-main)">Resumen de Gastos</span>' +
                        '<span style="font-size:0.75rem;color:var(--text-muted)">Este mes</span>' +
                    '</div>' +
                    '<h2 style="font-size:2rem;font-weight:700;color:' + secScale[700] + ';margin:0">Heading 2 <span style="font-size:1rem;color:var(--text-muted);font-weight:400;margin-left:8px">Section Titles</span></h2>' +
                    '<div style="display:flex;align-items:flex-end;gap:4px;height:48px;margin-bottom:16px">' +
                        '<div style="flex:1;border-radius:3px 3px 0 0;background:' + scale[200] + ';height:30%"></div>' +
                        '<div style="flex:1;border-radius:3px 3px 0 0;background:' + scale[300] + ';height:55%"></div>' +
                        '<div style="flex:1;border-radius:3px 3px 0 0;background:' + scale[400] + ';height:70%"></div>' +
                        '<div style="flex:1;border-radius:3px 3px 0 0;background:' + scale[600] + ';height:100%"></div>' +
                        '<div style="flex:1;border-radius:3px 3px 0 0;background:' + scale[500] + ';height:85%"></div>' +
                    '</div>' +
                    '<div style="display:flex;align-items:center;gap:8px">' +
                        '<span style="padding:4px 12px;border-radius:999px;background:' + secScale[100] + ';color:' + secScale[700] + ';font-size:0.75rem;font-weight:600"><i class="ph ph-trend-up" style="margin-right:4px"></i>+12.5%</span>' +
                        '<span style="font-size:0.75rem;color:var(--text-muted)">vs mes anterior</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +

            // Card 2: Metric Card
            '<div class="preview-card-demo" style="border-left:4px solid ' + secScale[500] + ';overflow:hidden">' +
                '<div style="padding:20px">' +
                    '<div style="display:flex;align-items:flex-start;gap:14px">' +
                        '<div style="width:44px;height:44px;border-radius:12px;background:' + secScale[100] + ';display:flex;align-items:center;justify-content:center;flex-shrink:0">' +
                            '<i class="ph ph-chart-line-up" style="font-size:1.3rem;color:' + secScale[600] + '"></i>' +
                        '</div>' +
                        '<div style="flex:1">' +
                            '<div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:4px">Ventas del Mes</div>' +
                            '<div style="font-size:1.6rem;font-weight:700;color:' + scale[700] + ';line-height:1.2">1,284</div>' +
                            '<div style="margin-top:8px;display:flex;align-items:center;gap:6px">' +
                                '<span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;background:' + secScale[100] + ';color:' + secScale[700] + ';font-size:0.7rem;font-weight:600"><i class="ph ph-arrow-up" style="margin-right:2px"></i>+8.3%</span>' +
                                '<span style="font-size:0.7rem;color:var(--text-muted)">vs anterior</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +

            // Card 3: Testimonial
            '<div class="preview-card-demo" style="overflow:hidden">' +
                '<div style="padding:20px">' +
                    '<div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">' +
                        '<div style="width:44px;height:44px;border-radius:50%;background:' + secScale[200] + ';display:flex;align-items:center;justify-content:center;font-weight:700;color:' + secScale[700] + ';font-size:1rem">CM</div>' +
                        '<div>' +
                            '<div style="font-weight:600;font-size:0.9rem;color:var(--text-main)">César Mendoza</div>' +
                            '<div style="font-size:0.75rem;color:var(--text-muted)">Product Manager</div>' +
                        '</div>' +
                    '</div>' +
                    '<p style="font-size:0.85rem;color:var(--text-main);line-height:1.6;margin:0 0 14px;font-style:italic">"Una herramienta increíble que ha transformado nuestro flujo de trabajo. Los resultados son impresionantes."</p>' +
                    '<div style="display:flex;gap:2px">' +
                        '<i class="ph-fill ph-star" style="color:' + secScale[400] + ';font-size:1rem"></i>' +
                        '<i class="ph-fill ph-star" style="color:' + secScale[400] + ';font-size:1rem"></i>' +
                        '<i class="ph-fill ph-star" style="color:' + secScale[400] + ';font-size:1rem"></i>' +
                        '<i class="ph-fill ph-star" style="color:' + secScale[400] + ';font-size:1rem"></i>' +
                        '<i class="ph-fill ph-star" style="color:' + secScale[400] + ';font-size:1rem"></i>' +
                    '</div>' +
                '</div>' +
            '</div>' +

        '</div>';
    }

    // --- Buttons Preview ---
    function renderButtonsPreview(scale, secScale) {
        if (!secScale) secScale = scale;
        return '<div style="display:flex;flex-direction:column;gap:24px">' +

            '<div>' +
                '<div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px">Variantes</div>' +
                '<div class="preview-buttons-grid">' +
                    '<span class="demo-btn primary" style="background:' + scale[600] + ';color:#fff">Primary</span>' +
                    '<span class="demo-btn secondary" style="background:' + secScale[100] + ';color:' + secScale[700] + '">Secondary</span>' +
                    '<span class="demo-btn outline" style="border-color:' + scale[500] + ';color:' + scale[600] + '">Outline</span>' +
                    '<span class="demo-btn ghost" style="color:' + scale[600] + '">Ghost</span>' +
                    '<span class="demo-btn danger" style="background:#ef4444;color:#fff">Danger</span>' +
                '</div>' +
            '</div>' +

            '<div>' +
                '<div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px">Tamaños</div>' +
                '<div class="preview-buttons-grid" style="align-items:center">' +
                    '<span class="demo-btn sm primary" style="background:' + scale[600] + ';color:#fff">Small</span>' +
                    '<span class="demo-btn primary" style="background:' + scale[600] + ';color:#fff">Medium</span>' +
                    '<span class="demo-btn lg primary" style="background:' + scale[600] + ';color:#fff">Large</span>' +
                '</div>' +
            '</div>' +

            '<div>' +
                '<div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px">Con Icono & Estados</div>' +
                '<div class="preview-buttons-grid">' +
                    '<span class="demo-btn primary" style="background:' + scale[600] + ';color:#fff"><i class="ph ph-plus"></i> Crear Nuevo</span>' +
                    '<span class="demo-btn primary" style="background:' + scale[400] + ';color:#fff"><span style="display:inline-block;width:14px;height:14px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite"></span> Cargando...</span>' +
                    '<span class="demo-btn primary" style="background:' + scale[300] + ';color:' + scale[100] + ';opacity:0.6;cursor:not-allowed">Deshabilitado</span>' +
                    '<span class="demo-btn outline" style="border-color:' + scale[500] + ';color:' + scale[600] + '"><i class="ph ph-download-simple"></i> Descargar</span>' +
                    '<span class="demo-btn sm secondary" style="background:' + secScale[100] + ';color:' + secScale[700] + '">Small</span>' +
                    '<span class="demo-btn secondary" style="background:' + secScale[100] + ';color:' + secScale[700] + '"><i class="ph ph-pencil-simple"></i> Editar</span>' +
                '</div>' +
            '</div>' +

        '</div>' +
        '<style>@keyframes spin{to{transform:rotate(360deg)}}</style>';
    }

    // --- Dashboard Preview ---
    function renderDashboardPreview(scale, secScale) {
        if (!secScale) secScale = scale;
        return '<div style="display:flex;flex-direction:column;gap:20px">' +

            // Stat cards row
            '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">' +
                '<div class="demo-stat-card" style="background:var(--bg-surface);padding:16px;border-radius:var(--radius-md);border:1px solid var(--border-color)">' +
                    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">' +
                        '<div style="width:32px;height:32px;border-radius:8px;background:' + scale[100] + ';display:flex;align-items:center;justify-content:center"><i class="ph ph-currency-dollar" style="color:' + scale[600] + '"></i></div>' +
                        '<span style="font-size:0.75rem;color:var(--text-muted)">Ingresos</span>' +
                    '</div>' +
                    '<div style="font-size:1.3rem;font-weight:700;color:' + scale[700] + '">$45,231</div>' +
                    '<span style="font-size:0.7rem;color:#16a34a;font-weight:600">↑ +20.1%</span>' +
                '</div>' +
                '<div class="demo-stat-card" style="background:var(--bg-surface);padding:16px;border-radius:var(--radius-md);border:1px solid var(--border-color)">' +
                    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">' +
                        '<div style="width:32px;height:32px;border-radius:8px;background:' + secScale[100] + ';display:flex;align-items:center;justify-content:center"><i class="ph ph-users" style="color:' + secScale[600] + '"></i></div>' +
                        '<span style="font-size:0.75rem;color:var(--text-muted)">Usuarios</span>' +
                    '</div>' +
                    '<div style="font-size:1.3rem;font-weight:700;color:' + scale[700] + '">2,350</div>' +
                    '<span style="font-size:0.7rem;color:#16a34a;font-weight:600">↑ +10.5%</span>' +
                '</div>' +
                '<div class="demo-stat-card" style="background:var(--bg-surface);padding:16px;border-radius:var(--radius-md);border:1px solid var(--border-color)">' +
                    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">' +
                        '<div style="width:32px;height:32px;border-radius:8px;background:' + secScale[100] + ';display:flex;align-items:center;justify-content:center"><i class="ph ph-shopping-cart" style="color:' + secScale[600] + '"></i></div>' +
                        '<span style="font-size:0.75rem;color:var(--text-muted)">Órdenes</span>' +
                    '</div>' +
                    '<div style="font-size:1.3rem;font-weight:700;color:' + scale[700] + '">12,234</div>' +
                    '<span style="font-size:0.7rem;color:#16a34a;font-weight:600">↑ +19%</span>' +
                '</div>' +
            '</div>' +

            // Charts row
            '<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:16px">' +
                // Donut chart
                '<div style="background:var(--bg-surface);padding:20px;border-radius:var(--radius-md);border:1px solid var(--border-color)">' +
                    '<div style="font-size:0.85rem;font-weight:600;color:var(--text-main);margin-bottom:16px">Distribución</div>' +
                    '<div style="display:flex;align-items:center;gap:20px">' +
                        '<div class="demo-donut" style="background:conic-gradient(' + scale[500] + ' 0% 45%,' + scale[300] + ' 45% 70%,' + secScale[400] + ' 70% 85%,' + secScale[200] + ' 85% 100%);width:110px;height:110px;border-radius:50%;position:relative;flex-shrink:0">' +
                            '<div class="demo-donut-center" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:60px;height:60px;border-radius:50%;background:var(--bg-surface)"></div>' +
                        '</div>' +
                        '<div style="display:flex;flex-direction:column;gap:6px;font-size:0.75rem">' +
                            '<div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:3px;background:' + scale[500] + '"></span><span style="color:var(--text-muted)">Ventas 45%</span></div>' +
                            '<div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:3px;background:' + scale[300] + '"></span><span style="color:var(--text-muted)">Marketing 25%</span></div>' +
                            '<div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:3px;background:' + secScale[400] + '"></span><span style="color:var(--text-muted)">Operaciones 15%</span></div>' +
                            '<div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:3px;background:' + secScale[200] + '"></span><span style="color:var(--text-muted)">Otros 15%</span></div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +

                // Bar chart
                '<div style="background:var(--bg-surface);padding:20px;border-radius:var(--radius-md);border:1px solid var(--border-color)">' +
                    '<div style="font-size:0.85rem;font-weight:600;color:var(--text-main);margin-bottom:16px">Ventas Mensuales</div>' +
                    '<div class="demo-bar-chart" style="display:flex;align-items:flex-end;gap:8px;height:100px">' +
                        '<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">' +
                            '<div style="width:100%;border-radius:4px 4px 0 0;background:' + scale[300] + ';height:40px"></div>' +
                            '<span style="font-size:0.65rem;color:var(--text-muted)">Ene</span>' +
                        '</div>' +
                        '<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">' +
                            '<div style="width:100%;border-radius:4px 4px 0 0;background:' + scale[400] + ';height:55px"></div>' +
                            '<span style="font-size:0.65rem;color:var(--text-muted)">Feb</span>' +
                        '</div>' +
                        '<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">' +
                            '<div style="width:100%;border-radius:4px 4px 0 0;background:' + secScale[300] + ';height:45px"></div>' +
                            '<span style="font-size:0.65rem;color:var(--text-muted)">Mar</span>' +
                        '</div>' +
                        '<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">' +
                            '<div style="width:100%;border-radius:4px 4px 0 0;background:' + scale[500] + ';height:75px"></div>' +
                            '<span style="font-size:0.65rem;color:var(--text-muted)">Abr</span>' +
                        '</div>' +
                        '<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">' +
                            '<div style="width:100%;border-radius:4px 4px 0 0;background:' + secScale[500] + ';height:90px"></div>' +
                            '<span style="font-size:0.65rem;color:var(--text-muted)">May</span>' +
                        '</div>' +
                        '<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">' +
                            '<div style="width:100%;border-radius:4px 4px 0 0;background:' + scale[600] + ';height:100px"></div>' +
                            '<span style="font-size:0.65rem;color:var(--text-muted)">Jun</span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +

        '</div>';
    }

    // --- Typography Preview ---
    function renderTypographyPreview(scale, secScale) {
        if (!secScale) secScale = scale;
        return '<div style="display:flex;flex-direction:column;gap:16px;max-width:700px">' +
            '<h1 style="margin:0;font-size:2rem;font-weight:800;color:' + scale[900] + ';line-height:1.2">Heading Nivel 1 — Títulos Principales</h1>' +
            '<h2 style="margin:0;font-size:1.5rem;font-weight:700;color:' + secScale[700] + ';line-height:1.3">Heading Nivel 2 — Secciones (Secondary)</h2>' +
            '<h3 style="margin:0;font-size:1.25rem;font-weight:600;color:' + scale[700] + ';line-height:1.4">Heading Nivel 3 — Subsecciones</h3>' +
            '<h4 style="margin:0;font-size:1.1rem;font-weight:600;color:' + secScale[600] + ';line-height:1.4">Heading Nivel 4 — Detalles (Secondary)</h4>' +
            '<p style="margin:0;font-size:0.95rem;line-height:1.7;color:var(--text-main)">Este es un párrafo de ejemplo que muestra cómo se verá el texto del cuerpo con la paleta de colores seleccionada. Puedes usar <span style="background:' + scale[100] + ';color:' + scale[800] + ';padding:2px 6px;border-radius:4px;font-weight:500">texto resaltado</span> para enfatizar contenido importante dentro de los párrafos de tu aplicación.</p>' +
            '<p style="margin:0;font-size:0.95rem;line-height:1.7;color:var(--text-main)">También <a href="#" style="color:' + secScale[600] + ';text-decoration:none;font-weight:500;border-bottom:1px dashed ' + secScale[600] + '">Enlace Secundario <i class="ph ph-arrow-right"></i></a> que se integran con tu paleta.</p>' +
            '<blockquote style="margin:0;padding:12px 20px;border-left:4px solid ' + scale[300] + ';background:' + scale[50] + ';border-radius:0 var(--radius-sm) var(--radius-sm) 0;font-style:italic;color:' + scale[800] + ';font-size:0.9rem;line-height:1.6">"La simplicidad es la sofisticación definitiva. Un buen diseño de color hace que la interfaz sea intuitiva y accesible para todos los usuarios."</blockquote>' +
            '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
                '<code style="padding:4px 10px;background:' + secScale[100] + ';color:' + secScale[800] + ';border-radius:6px;font-size:0.8rem;font-family:monospace">--secondary-500</code>' +
                '<code style="padding:4px 10px;background:' + scale[100] + ';color:' + scale[800] + ';border-radius:6px;font-size:0.8rem;font-family:monospace">font-weight: 600</code>' +
                '<code style="padding:4px 10px;background:' + scale[100] + ';color:' + scale[800] + ';border-radius:6px;font-size:0.8rem;font-family:monospace">border-radius: 8px</code>' +
            '</div>' +
        '</div>';
    }

    // --- Badges Preview ---
    function renderBadgesPreview(scale, secScale) {
        if (!secScale) secScale = scale;
        return '<div style="display:flex;flex-direction:column;gap:24px">' +

            '<div>' +
                '<div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px">Estado</div>' +
                '<div style="display:flex;flex-wrap:wrap;gap:8px">' +
                    '<span class="demo-badge" style="background:#dcfce7;color:#15803d;padding:4px 12px">● Activo</span>' +
                    '<span class="demo-badge" style="background:' + secScale[100] + ';color:' + secScale[700] + ';padding:4px 12px">● Pendiente (Secundario)</span>' +
                    '<span class="demo-badge" style="background:#fef2f2;color:#dc2626;padding:4px 12px">● Error</span>' +
                    '<span class="demo-badge" style="background:#eff6ff;color:#2563eb;padding:4px 12px">● Info</span>' +
                    '<span class="demo-badge" style="background:#fefce8;color:#ca8a04;padding:4px 12px">● Advertencia</span>' +
                '</div>' +
            '</div>' +

            '<div>' +
                '<div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px">Prioridad</div>' +
                '<div style="display:flex;flex-wrap:wrap;gap:8px">' +
                    '<span class="demo-badge" style="background:' + scale[600] + ';color:#fff;padding:4px 12px">Urgente</span>' +
                    '<span class="demo-badge" style="background:' + scale[500] + ';color:#fff;padding:4px 12px">Alta</span>' +
                    '<span class="demo-badge" style="background:' + secScale[600] + ';color:#fff;padding:4px 12px">Media (Secundario)</span>' +
                    '<span class="demo-badge" style="background:' + scale[100] + ';color:' + scale[700] + ';padding:4px 12px">Baja</span>' +
                '</div>' +
            '</div>' +

            '<div>' +
                '<div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px">Con Icono</div>' +
                '<div style="display:flex;flex-wrap:wrap;gap:8px">' +
                    '<span class="demo-badge" style="background:' + secScale[100] + ';color:' + secScale[700] + ';padding:4px 12px;display:inline-flex;align-items:center;gap:4px"><i class="ph ph-check-circle"></i> Completado (Secundario)</span>' +
                    '<span class="demo-badge" style="background:' + scale[100] + ';color:' + scale[700] + ';padding:4px 12px;display:inline-flex;align-items:center;gap:4px"><i class="ph ph-clock"></i> En Progreso</span>' +
                    '<span class="demo-badge" style="background:' + secScale[100] + ';color:' + secScale[700] + ';padding:4px 12px;display:inline-flex;align-items:center;gap:4px"><i class="ph ph-star"></i> Destacado (Secundario)</span>' +
                    '<span class="demo-badge" style="background:' + scale[100] + ';color:' + scale[700] + ';padding:4px 12px;display:inline-flex;align-items:center;gap:4px"><i class="ph ph-lightning"></i> Express</span>' +
                '</div>' +
            '</div>' +

            '<div>' +
                '<div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px">Estilos de Borde</div>' +
                '<div style="display:flex;flex-wrap:wrap;gap:8px">' +
                    '<span class="demo-badge" style="border:1.5px solid ' + secScale[500] + ';color:' + secScale[600] + ';padding:4px 12px;background:transparent">Outlined (Secundario)</span>' +
                    '<span class="demo-badge" style="border:1.5px solid ' + scale[300] + ';color:' + scale[600] + ';padding:4px 12px;background:' + scale[50] + '">Soft</span>' +
                    '<span class="demo-badge" style="background:' + scale[700] + ';color:#fff;padding:4px 12px;border-radius:4px">Squared</span>' +
                    '<span class="demo-badge" style="background:' + secScale[600] + ';color:#fff;padding:6px 16px;font-size:0.85rem">Large (Secundario)</span>' +
                '</div>' +
            '</div>' +

        '</div>';
    }

    // --- Gradients Preview ---
    function renderGradientsPreview(scale, secScale) {
        if (!secScale) secScale = scale;
        return '<div style="display:flex;flex-direction:column;gap:24px">' +
            '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">' +
            '<div style="height:100px;border-radius:var(--radius-md);background:linear-gradient(135deg, ' + scale[500] + ' 0%, ' + secScale[500] + ' 100%)"></div>' +
            '<div class="demo-gradient-card" style="height:90px;border-radius:var(--radius-md);background:linear-gradient(135deg,' + scale[400] + ',' + scale[700] + ');display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:0.85rem;text-shadow:0 1px 3px rgba(0,0,0,0.2)">Primary 400 → 700</div>' +
            '<div class="demo-gradient-card" style="height:90px;border-radius:var(--radius-md);background:linear-gradient(135deg,' + secScale[300] + ',' + secScale[600] + ');display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:0.85rem;text-shadow:0 1px 3px rgba(0,0,0,0.2)">Secondary 300 → 600</div>' +
            '<div class="demo-gradient-card" style="height:90px;border-radius:var(--radius-md);background:linear-gradient(to right,' + scale[100] + ',' + secScale[500] + ',' + scale[900] + ');display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:0.85rem;text-shadow:0 1px 3px rgba(0,0,0,0.2)">Mixed Spectrum →</div>' +
            '<div class="demo-gradient-card" style="height:90px;border-radius:var(--radius-md);background:linear-gradient(45deg,' + secScale[500] + ',' + scale[300] + ',' + secScale[500] + ');display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:0.85rem;text-shadow:0 1px 3px rgba(0,0,0,0.2)">Secondary 500 → Primary 300</div>' +
            '<div class="demo-gradient-card" style="height:90px;border-radius:var(--radius-md);background:radial-gradient(circle at 30% 40%,' + secScale[300] + ',' + scale[700] + ');display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:0.85rem;text-shadow:0 1px 3px rgba(0,0,0,0.2)">Radial Mixed</div>' +
            '<div class="demo-gradient-card" style="height:90px;border-radius:var(--radius-md);background:linear-gradient(90deg,' + scale[600] + ',' + scale[400] + ',' + scale[200] + ');display:flex;align-items:center;justify-content:center;color:' + scale[900] + ';font-weight:600;font-size:0.85rem">Primary Horizontal</div>' +
            '<div class="demo-gradient-card" style="height:90px;border-radius:var(--radius-md);background:radial-gradient(ellipse at top,' + secScale[400] + ',' + secScale[800] + ');display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:0.85rem;text-shadow:0 1px 3px rgba(0,0,0,0.2)">Secondary Radial</div>' +
            '</div></div>';
    }

    // =========================================================================
    // Preview Dispatcher
    // =========================================================================
    var previewRenderers = {
        cards: renderCardsPreview,
        buttons: renderButtonsPreview,
        dashboard: renderDashboardPreview,
        typography: renderTypographyPreview,
        badges: renderBadgesPreview,
        gradients: renderGradientsPreview
    };

    function renderCurrentPreview() {
        var container = document.getElementById('previewContent');
        var renderer = previewRenderers[currentPreview];
        if (renderer) {
            container.innerHTML = renderer(currentScale, currentSecondaryScale);
        }
    }

    // =========================================================================
    // Saved Palettes
    // =========================================================================
    function renderSavedPalettes(palettes) {
        var container = document.getElementById('savedPalettes');
        if (!palettes || palettes.length === 0) {
            container.innerHTML = '<div style="text-align:center;padding:16px;color:var(--text-muted);font-size:0.85rem"><i class="ph ph-bookmark-simple" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.5"></i>No hay paletas guardadas</div>';
            return;
        }

        var html = '';
        palettes.forEach(function(palette) {
            var paletteData = {};
            try { paletteData = JSON.parse(palette.palette_data); } catch(e) {}
            var dots = '';
            var shades = [50, 200, 400, 500, 600, 800, 950];
            var scaleData = paletteData.primary || paletteData;
            shades.forEach(function(s) {
                if (scaleData[s]) {
                    dots += '<span style="width:14px;height:14px;border-radius:50%;background:' + scaleData[s] + ';display:inline-block;border:1px solid rgba(0,0,0,0.1)"></span>';
                }
            });

            html += '<div class="saved-palette-item" data-id="' + palette.id + '" data-color="' + (palette.primary_color || '') + '" data-secondary="' + (palette.secondary_color || '') + '" data-harmony="' + (palette.harmony_mode || 'auto') + '">' +
                '<div class="saved-palette-colors" style="display:flex;gap:2px">' + dots + '</div>' +
                '<span class="saved-palette-name">' + (palette.name || 'Sin nombre') + '</span>' +
                '<button class="saved-palette-delete" data-id="' + palette.id + '" title="Eliminar"><i class="ph ph-trash"></i></button>' +
            '</div>';
        });

        container.innerHTML = html;

        // Attach load handlers
        container.querySelectorAll('.saved-palette-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                if (e.target.closest('.saved-palette-delete')) return;
                var color = this.getAttribute('data-color');
                var secondaryColor = this.getAttribute('data-secondary');
                var harmony = this.getAttribute('data-harmony') || 'auto';
                if (color) {
                    currentHex = color;
                    document.getElementById('colorPicker').value = color;
                    document.getElementById('hexInput').value = color;
                    
                    if (secondaryColor) {
                        currentSecondaryHex = secondaryColor;
                        document.getElementById('secondaryColorPicker').value = secondaryColor;
                        document.getElementById('secondaryHexInput').value = secondaryColor;
                    }
                    
                    document.getElementById('harmonyMode').value = harmony;
                    updateAll();
                    
                    // Close sidebar automatically when loading a palette
                    document.getElementById('paletaSidebar').classList.remove('active');
                    document.getElementById('sidebarOverlay').classList.remove('active');

                    if (window.showToast) window.showToast('Paleta cargada', 'info');
                }
            });
        });

        // Attach delete handlers
        container.querySelectorAll('.saved-palette-delete').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                deletePalette(this.getAttribute('data-id'));
            });
        });
    }

    // =========================================================================
    // AJAX
    // =========================================================================
    function savePalette(name, hex, secHex, harmonyMode) {
        var scale = generateScale(hex);
        var secScale = generateScale(secHex);
        var combinedData = { primary: scale, secondary: secScale };
        fetch('modules/herramientas/ajax_save_palette.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: name,
                primary_color: hex,
                secondary_color: secHex,
                harmony_mode: harmonyMode,
                palette_data: JSON.stringify(combinedData)
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (window.showToast) window.showToast('Paleta guardada correctamente', 'success');
                loadPalettes();
            } else {
                if (window.showToast) window.showToast(data.message || 'Error al guardar', 'error');
            }
        })
        .catch(function() {
            if (window.showToast) window.showToast('Error de conexión', 'error');
        });
    }

    function loadPalettes() {
        fetch('modules/herramientas/ajax_get_palettes.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                renderSavedPalettes(data.palettes || []);
            }
        })
        .catch(function() {});
    }

    function deletePalette(id) {
        if (!confirm('¿Eliminar esta paleta?')) return;
        fetch('modules/herramientas/ajax_delete_palette.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (window.showToast) window.showToast('Paleta eliminada', 'success');
                loadPalettes();
            }
        })
        .catch(function() {});
    }

    // =========================================================================
    // Update All
    // =========================================================================
    function updateAll() {
        var hex = document.getElementById('hexInput').value;
        var secHex = document.getElementById('secondaryHexInput').value;
        var mode = document.getElementById('harmonyMode').value;
        var isSecondaryEnabled = document.getElementById('enableSecondary').checked;

        currentScale = generateScale(hex);
        currentHex = hex;

        // Sync preview dots
        var pDot = document.getElementById('primaryColorDot');
        if (pDot) pDot.style.background = hex;
        var sDot = document.getElementById('secondaryColorDot');
        if (sDot) sDot.style.background = secHex;
        
        if (isSecondaryEnabled) {
            currentSecondaryScale = generateScale(secHex);
            currentSecondaryHex = secHex;
            document.getElementById('secondaryColorControls').style.opacity = '1';
            document.getElementById('secondaryColorPicker').disabled = false;
            document.getElementById('secondaryHexInput').disabled = false;
        } else {
            // Fallback to primary color for components if secondary is disabled
            currentSecondaryScale = Object.assign({}, currentScale);
            currentSecondaryHex = currentHex;
            document.getElementById('secondaryColorControls').style.opacity = '0.5';
            document.getElementById('secondaryColorPicker').disabled = true;
            document.getElementById('secondaryHexInput').disabled = true;
        }

        renderScales(hex, secHex, mode);
        renderCurrentPreview();

        // Update saved palettes badge count
        var saved = JSON.parse(localStorage.getItem('saved_palettes') || '[]');
        var countEl = document.getElementById('savedPalettesCount');
        if (countEl) countEl.textContent = saved.length;
    }

    // =========================================================================
    // Random Color
    // =========================================================================
    function generateRandomColor() {
        var hex = '#' + Math.floor(Math.random() * 16777215).toString(16).padStart(6, '0');
        return hex;
    }

    function triggerRandom() {
        var hex = generateRandomColor();
        var secHex = generateRandomColor();
        currentHex = hex;
        currentSecondaryHex = secHex;
        document.getElementById('colorPicker').value = hex;
        document.getElementById('hexInput').value = hex;
        document.getElementById('secondaryColorPicker').value = secHex;
        document.getElementById('secondaryHexInput').value = secHex;
        updateAll();
    }

    // =========================================================================
    // Image / PDF Export Generation
    // =========================================================================
    function generateImageOrPDF(type) {
        var btn = document.getElementById('btnConfirmExport');
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner"></i> Generando...';
        btn.disabled = true;

        var tempDiv = document.createElement('div');
        tempDiv.style.position = 'absolute';
        tempDiv.style.left = '-9999px';
        tempDiv.style.top = '0';
        tempDiv.style.width = '1000px';
        tempDiv.style.padding = '40px';
        tempDiv.style.background = '#ffffff';
        tempDiv.style.display = 'flex';
        tempDiv.style.flexDirection = 'column';
        tempDiv.style.gap = '40px';
        
        var palettesClone = document.getElementById('scalesContainer').cloneNode(true);
        tempDiv.appendChild(palettesClone);

        var toExport = [];
        if (document.getElementById('expCards').checked) toExport.push({ key: 'cards', title: 'Cards' });
        if (document.getElementById('expButtons').checked) toExport.push({ key: 'buttons', title: 'Botones' });
        if (document.getElementById('expDashboard').checked) toExport.push({ key: 'dashboard', title: 'Dashboard' });
        if (document.getElementById('expTypography').checked) toExport.push({ key: 'typography', title: 'Tipografía' });
        if (document.getElementById('expBadges').checked) toExport.push({ key: 'badges', title: 'Badges' });
        if (document.getElementById('expGradients').checked) toExport.push({ key: 'gradients', title: 'Gradientes' });

        toExport.forEach(function(item) {
            var renderer = previewRenderers[item.key];
            if (renderer) {
                var section = document.createElement('div');
                section.innerHTML = '<h3 style="font-family:system-ui,sans-serif;margin-bottom:20px;padding-bottom:10px;border-bottom:1px solid #e2e8f0;color:#0f172a">' + item.title + '</h3>';
                var contentWrapper = document.createElement('div');
                contentWrapper.innerHTML = renderer(currentScale, currentSecondaryScale);
                section.appendChild(contentWrapper);
                tempDiv.appendChild(section);
            }
        });

        document.body.appendChild(tempDiv);

        if (typeof html2canvas === 'undefined') {
            if (window.showToast) window.showToast('La librería de captura no cargó correctamente.', 'error');
            document.body.removeChild(tempDiv);
            btn.innerHTML = originalText;
            btn.disabled = false;
            return;
        }

        html2canvas(tempDiv, { scale: 2, useCORS: true, backgroundColor: '#ffffff' }).then(function(canvas) {
            document.body.removeChild(tempDiv);
            btn.innerHTML = originalText;
            btn.disabled = false;
            document.getElementById('exportModal').classList.remove('active');

            if (type === 'png') {
                var link = document.createElement('a');
                link.download = 'paleta-export.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            } else if (type === 'pdf') {
                var imgData = canvas.toDataURL('image/jpeg', 1.0);
                var pdf = new jspdf.jsPDF('p', 'mm', 'a4');
                var pdfWidth = pdf.internal.pageSize.getWidth();
                var pdfHeight = (canvas.height * pdfWidth) / canvas.width;
                
                pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
                pdf.save('paleta-export.pdf');
            }
        }).catch(function(err) {
            document.body.removeChild(tempDiv);
            btn.innerHTML = originalText;
            btn.disabled = false;
            if (window.showToast) window.showToast('Error al exportar', 'error');
        });
    }

    // =========================================================================
    // Event Handlers
    // =========================================================================
    document.addEventListener('DOMContentLoaded', function() {

        // Color picker
        document.getElementById('colorPicker').addEventListener('input', function() {
            document.getElementById('hexInput').value = this.value;
            currentHex = this.value;
            updateAll();
        });

        // Hex input
        document.getElementById('hexInput').addEventListener('input', function() {
            var val = this.value;
            if (!val.startsWith('#')) val = '#' + val;
            val = val.substring(0, 7);
            if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                document.getElementById('colorPicker').value = val;
                currentHex = val;
                this.value = val;
                updateAll();
            }
        });

        // Secondary Hex input
        document.getElementById('secondaryHexInput').addEventListener('input', function() {
            var val = this.value;
            if (!val.startsWith('#')) val = '#' + val;
            val = val.substring(0, 7);
            if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                document.getElementById('secondaryColorPicker').value = val;
                currentSecondaryHex = val;
                this.value = val;
                updateAll();
            }
        });

        // Color Picker secondary
        document.getElementById('secondaryColorPicker').addEventListener('input', function() {
            document.getElementById('secondaryHexInput').value = this.value;
            currentSecondaryHex = this.value;
            updateAll();
        });

        // Scroll to Saved Palettes
        document.getElementById('btnToggleSidebar').addEventListener('click', function() {
            var section = document.getElementById('paletaSavedSection');
            if (section) {
                section.scrollIntoView({ behavior: 'smooth' });
            }
        });

        // Toggle Secondary Enable
        document.getElementById('enableSecondary').addEventListener('change', function() {
            updateAll();
        });

        // Random button
        document.getElementById('btnRandom').addEventListener('click', triggerRandom);

        // Spacebar shortcut
        document.addEventListener('keydown', function(e) {
            var tag = document.activeElement.tagName.toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
            if (e.key === ' ' || e.code === 'Space') {
                e.preventDefault();
                triggerRandom();
            }
        });

        // Harmony mode
        document.getElementById('harmonyMode').addEventListener('change', function() {
            updateAll();
        });

        // Preview tabs
        document.querySelectorAll('.preview-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.preview-tab').forEach(function(t) { t.classList.remove('active'); });
                this.classList.add('active');
                currentPreview = this.getAttribute('data-preview');
                renderCurrentPreview();
            });
        });

        // Tool tabs
        document.querySelectorAll('.herr-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                var tool = this.getAttribute('data-tool');
                document.querySelectorAll('.herr-tab').forEach(function(t) { t.classList.remove('active'); });
                document.querySelectorAll('.herr-tab-content').forEach(function(c) { c.classList.remove('active'); });
                this.classList.add('active');
                var content = document.querySelector('[data-tool-content="' + tool + '"]');
                if (content) content.classList.add('active');
            });
        });

        // Save button
        document.getElementById('btnSave').addEventListener('click', function() {
            document.getElementById('saveModal').classList.add('active');
            document.getElementById('paletteName').value = '';
            setTimeout(function() {
                document.getElementById('paletteName').focus();
            }, 100);
        });

        // Cancel save
        document.getElementById('btnCancelSave').addEventListener('click', function() {
            document.getElementById('saveModal').classList.remove('active');
        });

        // Confirm save
        document.getElementById('btnConfirmSave').addEventListener('click', function() {
            var name = document.getElementById('paletteName').value.trim();
            if (!name) {
                if (window.showToast) window.showToast('Ingresa un nombre para la paleta', 'warning');
                return;
            }
            var mode = document.getElementById('harmonyMode').value;
            savePalette(name, currentHex, currentSecondaryHex, mode);
            document.getElementById('saveModal').classList.remove('active');
        });

        // Enter key in name input
        document.getElementById('paletteName').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('btnConfirmSave').click();
            }
        });

        // Save modal overlay click
        document.getElementById('saveModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });

        // Export Modals logic
        document.getElementById('btnCancelExport').addEventListener('click', function() {
            document.getElementById('exportModal').classList.remove('active');
        });

        document.getElementById('btnConfirmExport').addEventListener('click', function() {
            if (pendingExportType) {
                generateImageOrPDF(pendingExportType);
            }
        });

        // Export dropdown toggle
        document.getElementById('btnExport').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('exportMenu').classList.toggle('active');
        });

        // Export dropdown items
        document.querySelectorAll('.export-dropdown-item').forEach(function(item) {
            item.addEventListener('click', function() {
                handleExport(this.getAttribute('data-export'));
                document.getElementById('exportMenu').classList.remove('active');
            });
        });

        // Close dropdown on outside click
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.export-dropdown')) {
                document.getElementById('exportMenu').classList.remove('active');
            }
        });

        // Escape key closes modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('saveModal').classList.remove('active');
                document.getElementById('exportModal').classList.remove('active');
                document.getElementById('exportMenu').classList.remove('active');
            }
        });

        // =====================================================================
        // QR & Barcode Generator Logic
        // =====================================================================
        let currentCodeType = 'qr'; // 'qr' or 'barcode'
        let qrCodeInstance = null;

        // Toast Helper
        function showNoticeToast(msg, icon = 'success') {
            if (typeof Swal !== 'undefined') {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2200,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.style.borderRadius = '12px';
                    }
                });
                Toast.fire({
                    icon: icon,
                    title: msg
                });
            } else if (window.showToast) {
                window.showToast(msg, icon);
            }
        }

        // Checksum Calculation Helpers
        function calculateEAN13Checksum(twelveDigits) {
            var str = twelveDigits.toString().padStart(12, '0').substring(0, 12);
            var sum = 0;
            for (var i = 0; i < 12; i++) {
                var d = parseInt(str.charAt(i), 10) || 0;
                sum += (i % 2 === 0) ? d : d * 3;
            }
            return (10 - (sum % 10)) % 10;
        }

        function calculateUPCChecksum(elevenDigits) {
            var str = elevenDigits.toString().padStart(11, '0').substring(0, 11);
            var sum = 0;
            for (var i = 0; i < 11; i++) {
                var d = parseInt(str.charAt(i), 10) || 0;
                sum += (i % 2 === 0) ? d * 3 : d;
            }
            return (10 - (sum % 10)) % 10;
        }

        // Tabs QR / Barcode Switcher
        document.querySelectorAll('.qr-tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.qr-tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                currentCodeType = this.getAttribute('data-type');
                
                if (currentCodeType === 'qr') {
                    document.getElementById('qrControlsContainer').style.display = 'block';
                    document.getElementById('barcodeControlsContainer').style.display = 'none';
                    document.getElementById('qrFrameWrapper').style.display = 'flex';
                    document.getElementById('barcodeRenderBox').style.display = 'none';
                    generateQRCode();
                } else {
                    document.getElementById('qrControlsContainer').style.display = 'none';
                    document.getElementById('barcodeControlsContainer').style.display = 'block';
                    document.getElementById('qrFrameWrapper').style.display = 'none';
                    document.getElementById('barcodeRenderBox').style.display = 'flex';
                    generateBarcode();
                }
            });
        });

        // QR Format Select
        document.getElementById('qrFormatSelect').addEventListener('change', function() {
            var format = this.value;
            document.querySelectorAll('.qr-input-group').forEach(el => {
                el.style.display = 'none';
                el.classList.remove('active');
            });
            var activeGroup = document.querySelector('.qr-input-group[data-qr-input="' + format + '"]');
            if (activeGroup) {
                activeGroup.style.display = 'block';
                activeGroup.classList.add('active');
            }
            generateQRCode();
        });

        // Event listeners for all QR inputs
        const qrInputs = [
            'qrInputUrl', 'qrInputWifiSsid', 'qrInputWifiPass', 'qrInputWifiType',
            'qrInputVcardName', 'qrInputVcardPhone', 'qrInputVcardEmail', 'qrInputVcardOrg',
            'qrInputVcardTitle', 'qrInputVcardUrl',
            'qrInputGeoLat', 'qrInputGeoLng', 'qrInputText', 
            'qrColorDark', 'qrColorLight', 'qrErrorLevel',
            'qrDotsStyle', 'qrCornersStyle',
            'qrInputWaPhone', 'qrInputWaText',
            'qrInputTgUser', 
            'qrInputMailTo', 'qrInputMailSubj', 'qrInputMailBody',
            'qrInputEvtTitle', 'qrInputEvtStart', 'qrInputEvtEnd', 'qrInputEvtLoc'
        ];
        qrInputs.forEach(id => {
            let el = document.getElementById(id);
            if(el) {
                el.addEventListener('input', generateQRCode);
                el.addEventListener('change', generateQRCode);
            }
        });

        // Clear QR URL button
        document.getElementById('btnClearQrUrl')?.addEventListener('click', function() {
            var input = document.getElementById('qrInputUrl');
            if (input) {
                input.value = '';
                generateQRCode();
                input.focus();
            }
        });

        // Wi-Fi Password Toggle Visibility
        document.getElementById('btnToggleWifiPass')?.addEventListener('click', function() {
            var input = document.getElementById('qrInputWifiPass');
            var icon = this.querySelector('i');
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) icon.className = 'ph ph-eye-slash';
            } else {
                input.type = 'password';
                if (icon) icon.className = 'ph ph-eye';
            }
        });

        // Sync QR Color Pickers with Hex Inputs
        const qrColorDark = document.getElementById('qrColorDark');
        const qrColorDarkHex = document.getElementById('qrColorDarkHex');
        if (qrColorDark && qrColorDarkHex) {
            qrColorDark.addEventListener('input', function() { qrColorDarkHex.value = this.value; generateQRCode(); });
            qrColorDarkHex.addEventListener('input', function() {
                if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                    qrColorDark.value = this.value;
                    generateQRCode();
                }
            });
        }

        const qrColorLight = document.getElementById('qrColorLight');
        const qrColorLightHex = document.getElementById('qrColorLightHex');
        if (qrColorLight && qrColorLightHex) {
            qrColorLight.addEventListener('input', function() { qrColorLightHex.value = this.value; generateQRCode(); });
            qrColorLightHex.addEventListener('input', function() {
                if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                    qrColorLight.value = this.value;
                    generateQRCode();
                }
            });
        }

        let qrLogoDataUrl = ''; // Stores base64 or Data URI of logo
        let qrLogoIsSvg = false; // Flag if it's SVG
        let qrLogoOriginalSvg = ''; // Stores raw SVG text

        // Handle file upload
        let logoInput = document.getElementById('qrLogoInput');
        if(logoInput) {
            logoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) {
                    qrLogoDataUrl = '';
                    qrLogoIsSvg = false;
                    qrLogoOriginalSvg = '';
                    document.getElementById('qrLogoColorContainer').style.display = 'none';
                    generateQRCode();
                    return;
                }

                const isSvg = file.type === 'image/svg+xml';
                qrLogoIsSvg = isSvg;
                document.getElementById('qrLogoColorContainer').style.display = isSvg ? 'flex' : 'none';
                document.getElementById('qrLogoSizeContainer').style.display = 'flex';

                const reader = new FileReader();
                if (isSvg) {
                    reader.onload = function(evt) {
                        qrLogoOriginalSvg = evt.target.result;
                        processSvgLogoColor();
                    };
                    reader.readAsText(file);
                } else {
                    reader.onload = function(evt) {
                        qrLogoDataUrl = evt.target.result;
                        generateQRCode();
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        let logoColorInput = document.getElementById('qrLogoColor');
        if (logoColorInput) {
            logoColorInput.addEventListener('input', function() {
                if (qrLogoIsSvg && qrLogoOriginalSvg) {
                    processSvgLogoColor();
                }
            });
        }

        let logoSizeInput = document.getElementById('qrLogoSize');
        if (logoSizeInput) {
            logoSizeInput.addEventListener('input', function() {
                document.getElementById('qrLogoSizeValue').textContent = this.value;
                generateQRCode();
            });
        }

        let frameSelect = document.getElementById('qrFrameSelect');
        if (frameSelect) {
            frameSelect.addEventListener('change', function() {
                generateQRCode();
            });
        }

        function processSvgLogoColor() {
            if (!qrLogoOriginalSvg) return;
            let parser = new DOMParser();
            let doc = parser.parseFromString(qrLogoOriginalSvg, "image/svg+xml");
            let svg = doc.documentElement;
            let color = document.getElementById('qrLogoColor').value;
            
            let els = svg.querySelectorAll('*');
            els.forEach(el => {
                if (el.hasAttribute('fill') && el.getAttribute('fill') !== 'none') el.setAttribute('fill', color);
                if (el.hasAttribute('stroke') && el.getAttribute('stroke') !== 'none') el.setAttribute('stroke', color);
            });
            if (!svg.hasAttribute('fill') || svg.getAttribute('fill') !== 'none') {
                svg.setAttribute('fill', color);
            }

            let serializer = new XMLSerializer();
            let newSvg = serializer.serializeToString(svg);
            let encoded = encodeURIComponent(newSvg);
            qrLogoDataUrl = "data:image/svg+xml;charset=utf-8," + encoded;
            generateQRCode();
        }

        function getQRText() {
            var format = document.getElementById('qrFormatSelect').value;
            if (format === 'url') return document.getElementById('qrInputUrl').value || ' ';
            
            if (format === 'whatsapp') {
                var phone = document.getElementById('qrInputWaPhone').value.replace(/[^0-9+]/g, '');
                var text = encodeURIComponent(document.getElementById('qrInputWaText').value);
                return `https://wa.me/${phone}?text=${text}`;
            }
            if (format === 'telegram') {
                var user = document.getElementById('qrInputTgUser').value.replace('@', '');
                return `tg://resolve?domain=${user}`;
            }
            if (format === 'mailto') {
                var to = document.getElementById('qrInputMailTo').value;
                var subj = encodeURIComponent(document.getElementById('qrInputMailSubj').value);
                var body = encodeURIComponent(document.getElementById('qrInputMailBody').value);
                return `mailto:${to}?subject=${subj}&body=${body}`;
            }
            if (format === 'event') {
                var title = document.getElementById('qrInputEvtTitle').value || 'Evento';
                var start = document.getElementById('qrInputEvtStart').value;
                var end = document.getElementById('qrInputEvtEnd').value;
                var loc = document.getElementById('qrInputEvtLoc').value;
                
                var formatDt = (dtStr) => {
                    if(!dtStr) return '';
                    let d = new Date(dtStr);
                    if(isNaN(d)) return '';
                    return d.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
                };
                
                var dtStart = formatDt(start);
                var dtEnd = formatDt(end);
                
                return `BEGIN:VEVENT\nSUMMARY:${title}\nDTSTART:${dtStart}\nDTEND:${dtEnd}\nLOCATION:${loc}\nEND:VEVENT`;
            }
            
            if (format === 'wifi') {
                var ssid = document.getElementById('qrInputWifiSsid').value;
                var pass = document.getElementById('qrInputWifiPass').value;
                var type = document.getElementById('qrInputWifiType').value;
                return `WIFI:T:${type};S:${ssid};P:${pass};;`;
            }
            if (format === 'vcard') {
                var name = document.getElementById('qrInputVcardName')?.value || '';
                var phone = document.getElementById('qrInputVcardPhone')?.value || '';
                var email = document.getElementById('qrInputVcardEmail')?.value || '';
                var org = document.getElementById('qrInputVcardOrg')?.value || '';
                var title = document.getElementById('qrInputVcardTitle')?.value || '';
                var url = document.getElementById('qrInputVcardUrl')?.value || '';
                
                var vcard = `BEGIN:VCARD\nVERSION:3.0\nN:${name}\nFN:${name}\nTEL;TYPE=CELL:${phone}\nEMAIL:${email}\nORG:${org}`;
                if (title) vcard += `\nTITLE:${title}`;
                if (url) vcard += `\nURL:${url}`;
                vcard += `\nEND:VCARD`;
                return vcard;
            }
            if (format === 'geo') {
                var lat = document.getElementById('qrInputGeoLat').value;
                var lng = document.getElementById('qrInputGeoLng').value;
                return `geo:${lat},${lng}`;
            }
            if (format === 'text') {
                return document.getElementById('qrInputText').value || ' ';
            }
            return ' ';
        }

        // Helper function for Dark Mode Warning
        function getLuminance(hex) {
            if (!hex || hex.length < 6) return 0;
            var c = hex.replace('#', '');      
            var rgb = parseInt(c, 16);   
            var r = (rgb >> 16) & 0xff; 
            var g = (rgb >>  8) & 0xff; 
            var b = (rgb >>  0) & 0xff; 
            return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        }

        function checkContrast(colorDark, colorLight) {
            var lumaDark = getLuminance(colorDark);
            var lumaLight = getLuminance(colorLight);
            var warningEl = document.getElementById('contrastWarning');
            if(lumaDark > lumaLight) {
                if(!warningEl) {
                    var el = document.createElement('div');
                    el.id = 'contrastWarning';
                    el.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                    el.style.color = '#ef4444';
                    el.style.padding = '10px 14px';
                    el.style.borderRadius = '10px';
                    el.style.marginBottom = '12px';
                    el.style.fontSize = '0.8rem';
                    el.style.fontWeight = '500';
                    el.style.border = '1px solid rgba(239, 68, 68, 0.25)';
                    el.innerHTML = '<i class="ph ph-warning"></i> <b>Cuidado:</b> Estás usando un color claro para las barras/código y oscuro para el fondo. Muchos lectores ópticos no podrán escanearlo.';
                    var previewArea = document.querySelector('.qr-preview-area');
                    if (previewArea) previewArea.insertBefore(el, previewArea.firstChild);
                }
            } else {
                if(warningEl) warningEl.remove();
            }
        }

        function generateQRCode() {
            var container = document.getElementById('qrCanvasContainer');
            if (!container) return;
            container.innerHTML = ''; // Clear previous
            
            var text = getQRText();
            var colorDark = document.getElementById('qrColorDark').value;
            var colorLight = document.getElementById('qrColorLight').value;
            var errorLevel = document.getElementById('qrErrorLevel').value;
            var dotsStyle = document.getElementById('qrDotsStyle').value;
            var cornersStyle = document.getElementById('qrCornersStyle').value;
            
            checkContrast(colorDark, colorLight);
            
            var logoSize = document.getElementById('qrLogoSize') ? parseFloat(document.getElementById('qrLogoSize').value) : 0.4;
            
            var frameSelect = document.getElementById('qrFrameSelect');
            var frameHeader = document.getElementById('qrFrameHeader');
            var frameWrapper = document.getElementById('qrFrameWrapper');
            
            if (frameSelect && frameSelect.value !== 'none') {
                if (frameHeader) {
                    frameHeader.style.display = 'block';
                    frameHeader.textContent = frameSelect.options[frameSelect.selectedIndex].text;
                }
                if (frameWrapper) frameWrapper.style.border = `4px solid ${colorDark}`;
            } else if (frameHeader) {
                frameHeader.style.display = 'none';
                if(frameWrapper) frameWrapper.style.border = 'none';
            }

            if (typeof QRCodeStyling !== 'undefined') {
                qrCodeInstance = new QRCodeStyling({
                    width: 280,
                    height: 280,
                    data: text,
                    image: qrLogoDataUrl,
                    dotsOptions: {
                        color: colorDark,
                        type: dotsStyle
                    },
                    cornersSquareOptions: {
                        type: cornersStyle,
                        color: colorDark
                    },
                    backgroundOptions: {
                        color: colorLight,
                    },
                    imageOptions: {
                        crossOrigin: "anonymous",
                        margin: 8,
                        imageSize: logoSize
                    },
                    qrOptions: {
                        errorCorrectionLevel: errorLevel
                    }
                });
                qrCodeInstance.append(container);
            }

            // Update Stage Header
            var stageTypeBadge = document.getElementById('stageCodeTypeBadge');
            if (stageTypeBadge) {
                var qrFmt = document.getElementById('qrFormatSelect')?.value.toUpperCase() || 'URL';
                stageTypeBadge.innerHTML = `<i class="ph ph-qr-code"></i> QR · ${qrFmt}`;
            }
            var stageLenBadge = document.getElementById('stageCodeLenBadge');
            if (stageLenBadge) {
                stageLenBadge.textContent = `${text.trim().length} caracteres`;
            }
        }

        // =====================================================================
        // Barcode Generator & Sequential Tools
        // =====================================================================
        const barcodeInputs = [
            'barcodeFormatSelect', 'barcodeInputVal', 'barcodeLineColor', 'barcodeBgColor', 'barcodeShowText', 'barcodeFontSize',
            'barcodeFontFamily', 'barcodeFontWeight'
        ];
        barcodeInputs.forEach(id => {
            let el = document.getElementById(id);
            if(el) {
                if(id === 'barcodeFontSize') {
                    el.addEventListener('input', function() {
                        document.getElementById('barcodeFontSizeValue').textContent = this.value + 'px';
                        generateBarcode();
                    });
                } else if(id === 'barcodeShowText') {
                    el.addEventListener('change', function() {
                        var typo = document.getElementById('barcodeTypographyControls');
                        if (typo) typo.style.display = this.checked ? 'flex' : 'none';
                        generateBarcode();
                    });
                } else {
                    el.addEventListener('input', generateBarcode);
                    el.addEventListener('change', generateBarcode);
                }
            }
        });

        // Sync Barcode Color Pickers with Hex Inputs
        const barcodeLineColor = document.getElementById('barcodeLineColor');
        const barcodeLineColorHex = document.getElementById('barcodeLineColorHex');
        if (barcodeLineColor && barcodeLineColorHex) {
            barcodeLineColor.addEventListener('input', function() { barcodeLineColorHex.value = this.value; generateBarcode(); });
            barcodeLineColorHex.addEventListener('input', function() {
                if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                    barcodeLineColor.value = this.value;
                    generateBarcode();
                }
            });
        }

        const barcodeBgColor = document.getElementById('barcodeBgColor');
        const barcodeBgColorHex = document.getElementById('barcodeBgColorHex');
        if (barcodeBgColor && barcodeBgColorHex) {
            barcodeBgColor.addEventListener('input', function() { barcodeBgColorHex.value = this.value; generateBarcode(); });
            barcodeBgColorHex.addEventListener('input', function() {
                if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                    barcodeBgColor.value = this.value;
                    generateBarcode();
                }
            });
        }

        // Generator Mode Toggle (Secuencial vs Aleatorio)
        document.getElementById('btnModeSeq')?.addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('btnModeRand')?.classList.remove('active');
            document.getElementById('bcSeqPanel').style.display = 'flex';
            document.getElementById('bcRandPanel').style.display = 'none';
        });

        document.getElementById('btnModeRand')?.addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('btnModeSeq')?.classList.remove('active');
            document.getElementById('bcSeqPanel').style.display = 'none';
            document.getElementById('bcRandPanel').style.display = 'flex';
        });

        // Live preview of starting digits in random hint
        document.getElementById('barcodeRandStartNum')?.addEventListener('input', function() {
            var display = document.getElementById('bcRandStartDisplay');
            if (display) {
                display.textContent = this.value.trim() || 'tu número';
            }
        });

        // Step buttons for starting number
        document.getElementById('btnStepUpBarcode')?.addEventListener('click', function() {
            var input = document.getElementById('barcodeStartNum');
            input.value = (parseInt(input.value) || 0) + 1;
        });

        document.getElementById('btnStepDownBarcode')?.addEventListener('click', function() {
            var input = document.getElementById('barcodeStartNum');
            var val = parseInt(input.value) || 0;
            if (val > 0) input.value = val - 1;
        });

        document.getElementById('btnResetSeqBarcode')?.addEventListener('click', function() {
            document.getElementById('barcodeStartNum').value = 1000;
            showNoticeToast('Contador restablecido a 1000', 'info');
        });

        // Generate next code from start number
        function generateNextSequentialBarcode() {
            var format = document.getElementById('barcodeFormatSelect').value;
            var startNumInput = document.getElementById('barcodeStartNum');
            var prefixInput = document.getElementById('barcodePrefix');
            var prefix = prefixInput ? prefixInput.value.trim() : '';
            var currentNum = parseInt(startNumInput.value);
            if (isNaN(currentNum)) currentNum = 1000;

            var finalCode = '';

            if (format === 'EAN13') {
                // Requires 12 digits + 1 checksum digit = 13 digits
                var numStr = currentNum.toString();
                if (numStr.length > 12) {
                    numStr = numStr.substring(0, 12);
                } else {
                    numStr = numStr.padStart(12, '0');
                }
                var chk = calculateEAN13Checksum(numStr);
                finalCode = numStr + chk;
            } else if (format === 'UPC') {
                // Requires 11 digits + 1 checksum digit = 12 digits
                var numStr = currentNum.toString();
                if (numStr.length > 11) {
                    numStr = numStr.substring(0, 11);
                } else {
                    numStr = numStr.padStart(11, '0');
                }
                var chk = calculateUPCChecksum(numStr);
                finalCode = numStr + chk;
            } else if (format === 'CODE39') {
                var cleanPrefix = prefix.toUpperCase().replace(/[^A-Z0-9\-\.\ \$\/\+\%]/g, '');
                finalCode = (cleanPrefix ? cleanPrefix + '-' : '') + currentNum;
            } else {
                // CODE128 (default)
                finalCode = (prefix ? prefix : '') + currentNum;
            }

            document.getElementById('barcodeInputVal').value = finalCode;
            generateBarcode();

            // Auto-increment for next click
            startNumInput.value = currentNum + 1;
            showNoticeToast('Código generado: ' + finalCode);
        }
        document.getElementById('btnGenerateNextBarcode')?.addEventListener('click', generateNextSequentialBarcode);

        // Random Barcode Value Generator (Starting with user-defined number/prefix)
        document.getElementById('btnRandomBarcode')?.addEventListener('click', function() {
            var format = document.getElementById('barcodeFormatSelect').value;
            var input = document.getElementById('barcodeInputVal');
            var rawStart = (document.getElementById('barcodeRandStartNum')?.value || '').toString().trim();
            var lenInput = document.getElementById('barcodeRandLen');
            var val = '';
            
            if (format === 'EAN13') {
                // Strictly 13 digits: 12 data digits + 1 checksum digit
                var digits = rawStart.replace(/\D/g, '');
                if (!digits) digits = '775';
                if (digits.length >= 12) {
                    digits = digits.substring(0, 12);
                } else {
                    while (digits.length < 12) {
                        digits += Math.floor(Math.random() * 10);
                    }
                }
                val = digits + calculateEAN13Checksum(digits);
            } else if (format === 'UPC') {
                // Strictly 12 digits: 11 data digits + 1 checksum digit
                var digits = rawStart.replace(/\D/g, '');
                if (!digits) digits = '012';
                if (digits.length >= 11) {
                    digits = digits.substring(0, 11);
                } else {
                    while (digits.length < 11) {
                        digits += Math.floor(Math.random() * 10);
                    }
                }
                val = digits + calculateUPCChecksum(digits);
            } else if (format === 'CODE39') {
                var cleanStart = rawStart.toUpperCase().replace(/[^A-Z0-9\-\.\ \$\/\+\%]/g, '');
                if (!cleanStart) cleanStart = '775';
                var targetLen = parseInt(lenInput?.value) || 10;
                if (targetLen < cleanStart.length + 2) targetLen = cleanStart.length + 4;
                var isDigits = /^\d+$/.test(cleanStart);
                var chars = isDigits ? '0123456789' : '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                val = cleanStart;
                while (val.length < targetLen) {
                    val += chars.charAt(Math.floor(Math.random() * chars.length));
                }
            } else {
                // CODE128 (default)
                var cleanStart = rawStart;
                if (!cleanStart) cleanStart = '775';
                var targetLen = parseInt(lenInput?.value) || 12;
                if (targetLen < cleanStart.length + 2) targetLen = cleanStart.length + 5;
                var isDigits = /^\d+$/.test(cleanStart);
                var chars = isDigits ? '0123456789' : '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                val = cleanStart;
                while (val.length < targetLen) {
                    val += chars.charAt(Math.floor(Math.random() * chars.length));
                }
            }
            
            input.value = val;
            generateBarcode();
            showNoticeToast('Aleatorio generado iniciando en: ' + (rawStart || 'automático'));
        });

        // Copy Barcode Value
        document.getElementById('btnCopyBarcodeVal')?.addEventListener('click', function() {
            var val = document.getElementById('barcodeInputVal').value;
            if (!val) return;
            navigator.clipboard.writeText(val).then(() => {
                showNoticeToast('¡Valor copiado al portapapeles!');
            });
        });

        // Clear Barcode Value
        document.getElementById('btnClearBarcodeVal')?.addEventListener('click', function() {
            document.getElementById('barcodeInputVal').value = '';
            generateBarcode();
            document.getElementById('barcodeInputVal').focus();
        });

        // Copy Raw Value from Stage
        document.getElementById('btnCopyRawValue')?.addEventListener('click', function() {
            var val = currentCodeType === 'qr' ? getQRText() : document.getElementById('barcodeInputVal').value;
            if (!val || val === ' ') return;
            navigator.clipboard.writeText(val).then(() => {
                showNoticeToast('¡Texto copiado al portapapeles!');
            });
        });

        // Bulk Counter Live Update
        function updateBulkCount() {
            var start = parseInt(document.getElementById('barcodeSeqStart').value);
            var end = parseInt(document.getElementById('barcodeSeqEnd').value);
            var badge = document.getElementById('barcodeBulkCountBadge');
            if (!badge) return;
            if (!isNaN(start) && !isNaN(end) && end >= start) {
                var count = end - start + 1;
                badge.innerHTML = `<i class="ph ph-check-circle"></i> ${count} código${count > 1 ? 's' : ''} listo${count > 1 ? 's' : ''}`;
                badge.style.color = 'var(--primary-color)';
            } else {
                badge.innerHTML = `<i class="ph ph-warning"></i> Rango no válido`;
                badge.style.color = '#ef4444';
            }
        }
        document.getElementById('barcodeSeqStart')?.addEventListener('input', updateBulkCount);
        document.getElementById('barcodeSeqEnd')?.addEventListener('input', updateBulkCount);

        function applyBarcodeTypography(svgEl, fontFamily, fontWeight, fontSize) {
            if (!svgEl) return;
            fontFamily = fontFamily || 'Inter';
            fontWeight = fontWeight || '600';
            fontSize = fontSize || 20;

            svgEl.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            svgEl.setAttribute('version', '1.1');

            var oldStyle = svgEl.querySelector('style[data-barcode-font]');
            if (oldStyle) oldStyle.remove();

            var defs = svgEl.querySelector('defs');
            if (!defs) {
                defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
                svgEl.insertBefore(defs, svgEl.firstChild);
            }

            var styleEl = document.createElementNS('http://www.w3.org/2000/svg', 'style');
            styleEl.setAttribute('type', 'text/css');
            styleEl.setAttribute('data-barcode-font', 'true');
            styleEl.textContent = 
                "@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');\n" +
                "@font-face {\n" +
                "  font-family: 'Inter';\n" +
                "  font-style: normal;\n" +
                "  font-weight: " + fontWeight + ";\n" +
                "  src: local('Inter SemiBold'), local('Inter-SemiBold'), local('Inter Bold'), local('Inter-Bold'), local('Inter');\n" +
                "}\n" +
                "text {\n" +
                "  font-family: '" + fontFamily + "', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;\n" +
                "  font-weight: " + fontWeight + " !important;\n" +
                "  letter-spacing: 0.5px;\n" +
                "}";
            defs.appendChild(styleEl);

            var textElements = svgEl.querySelectorAll('text');
            textElements.forEach(function(txt) {
                txt.setAttribute('font-family', fontFamily);
                txt.setAttribute('font-weight', fontWeight);
                txt.setAttribute('font-size', fontSize + 'px');
                txt.setAttribute('letter-spacing', '0.5px');
                txt.style.fontFamily = "'" + fontFamily + "', -apple-system, BlinkMacSystemFont, sans-serif";
                txt.style.fontWeight = fontWeight;
                txt.style.fontSize = fontSize + 'px';
                txt.style.letterSpacing = '0.5px';
            });
        }

        function generateBarcode() {
            var format = document.getElementById('barcodeFormatSelect').value;
            var rawVal = document.getElementById('barcodeInputVal').value;
            var val = rawVal || ' ';
            var lineColor = document.getElementById('barcodeLineColor').value;
            var bgColor = document.getElementById('barcodeBgColor').value;
            var showText = document.getElementById('barcodeShowText').checked;
            var fontSize = document.getElementById('barcodeFontSize') ? parseInt(document.getElementById('barcodeFontSize').value) : 20;
            var fontFamily = document.getElementById('barcodeFontFamily') ? document.getElementById('barcodeFontFamily').value : 'Inter';
            var fontWeight = document.getElementById('barcodeFontWeight') ? document.getElementById('barcodeFontWeight').value : '600';
            var fontOptionVal = (fontWeight === 'bold' || parseInt(fontWeight) >= 600) ? 'bold' : '';
            var hintEl = document.getElementById('barcodeHint');

            checkContrast(lineColor, bgColor);

            // Update hint based on format
            var hints = {
                'CODE128': 'Admite texto y números (Recomendado para cualquier uso comercial).',
                'EAN13': 'Requiere exactamente 13 dígitos numéricos (calcula automáticamente checksum con el generador).',
                'UPC': 'Requiere exactamente 12 dígitos numéricos (comercial US).',
                'CODE39': 'Admite letras mayúsculas, números y símbolos permitidos.'
            };
            if (hintEl) hintEl.textContent = hints[format] || 'Admite texto y números.';

            // Adapt prefix input if format is numeric only
            var prefixInput = document.getElementById('barcodePrefix');
            var prefixLabel = document.getElementById('barcodePrefixLabel');
            if (format === 'EAN13' || format === 'UPC') {
                if (prefixInput) {
                    prefixInput.disabled = true;
                    prefixInput.placeholder = 'No aplicable (Solo numérico)';
                }
                if (prefixLabel) prefixLabel.textContent = 'Prefijo (Solo numérico):';
            } else {
                if (prefixInput) {
                    prefixInput.disabled = false;
                    prefixInput.placeholder = 'Ej: ROM-, PRD-';
                }
                if (prefixLabel) prefixLabel.textContent = 'Prefijo (Opcional):';
            }

            // Adapt random generator controls based on format
            var randLenInput = document.getElementById('barcodeRandLen');
            var randLenLabel = document.getElementById('barcodeRandLenLabel');
            var randStartInput = document.getElementById('barcodeRandStartNum');
            var randStartLabel = document.getElementById('barcodeRandStartLabel');
            var randDisplay = document.getElementById('bcRandStartDisplay');
            var currentRandStart = randStartInput ? (randStartInput.value.trim() || '775') : '775';
            if (randDisplay) randDisplay.textContent = currentRandStart;

            if (randLenInput && randLenLabel) {
                if (format === 'EAN13') {
                    randLenInput.value = '13';
                    randLenInput.disabled = true;
                    randLenLabel.textContent = 'Longitud (Fijo 13):';
                    if (randStartLabel) randStartLabel.textContent = 'Iniciar con Dígitos:';
                    if (randStartInput) randStartInput.placeholder = 'Ej: 775, 100, 84...';
                } else if (format === 'UPC') {
                    randLenInput.value = '12';
                    randLenInput.disabled = true;
                    randLenLabel.textContent = 'Longitud (Fijo 12):';
                    if (randStartLabel) randStartLabel.textContent = 'Iniciar con Dígitos:';
                    if (randStartInput) randStartInput.placeholder = 'Ej: 012, 78, 10...';
                } else if (format === 'CODE39') {
                    randLenInput.disabled = false;
                    if (randLenInput.value === '13' || randLenInput.value === '12') randLenInput.value = '10';
                    randLenLabel.textContent = 'Longitud Total:';
                    if (randStartLabel) randStartLabel.textContent = 'Iniciar con Número / Prefijo:';
                    if (randStartInput) randStartInput.placeholder = 'Ej: 775, ROM, ART...';
                } else {
                    // CODE128
                    randLenInput.disabled = false;
                    if (randLenInput.value === '13' || randLenInput.value === '10') randLenInput.value = '12';
                    randLenLabel.textContent = 'Longitud Total:';
                    if (randStartLabel) randStartLabel.textContent = 'Iniciar con Número / Prefijo:';
                    if (randStartInput) randStartInput.placeholder = 'Ej: 775, ROM-, 100...';
                }
            }

            // Update header badges
            var formatBadge = document.getElementById('barcodeFormatBadge');
            if (formatBadge) formatBadge.textContent = format;

            var charCount = document.getElementById('barcodeCharCount');
            if (charCount) charCount.textContent = `${rawVal.length} caracteres`;

            var stageTypeBadge = document.getElementById('stageCodeTypeBadge');
            if (stageTypeBadge) stageTypeBadge.innerHTML = `<i class="ph ph-barcode"></i> Barras · ${format}`;

            var stageLenBadge = document.getElementById('stageCodeLenBadge');
            if (stageLenBadge) stageLenBadge.textContent = `${rawVal.length} caracteres`;

            // Adjust Barcode Box background to match user selection
            var renderBox = document.getElementById('barcodeRenderBox');
            if (renderBox) renderBox.style.backgroundColor = bgColor;

            try {
                if (typeof JsBarcode !== 'undefined') {
                    JsBarcode("#barcodeSvgContainer", val, {
                        format: format,
                        lineColor: lineColor,
                        background: bgColor,
                        displayValue: showText,
                        fontSize: fontSize,
                        font: fontFamily,
                        fontOptions: fontOptionVal,
                        width: 2,
                        height: 90,
                        margin: 8
                    });

                    var svgEl = document.getElementById('barcodeSvgContainer');
                    if (svgEl && showText) {
                        applyBarcodeTypography(svgEl, fontFamily, fontWeight, fontSize);
                    }
                }
            } catch (e) {
                // Show friendly error in the SVG container
                var svgEl = document.getElementById('barcodeSvgContainer');
                if (svgEl) {
                    svgEl.innerHTML = '';
                    svgEl.removeAttribute('viewBox');
                    svgEl.setAttribute('width', '100%');
                    svgEl.setAttribute('height', '90');
                    
                    var ns = 'http://www.w3.org/2000/svg';
                    var text = document.createElementNS(ns, 'text');
                    text.setAttribute('x', '50%');
                    text.setAttribute('y', '50%');
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('dominant-baseline', 'middle');
                    text.setAttribute('fill', '#ef4444');
                    text.setAttribute('font-size', '14');
                    text.setAttribute('font-weight', '600');
                    text.setAttribute('font-family', 'sans-serif');
                    text.textContent = '⚠ Valor no válido para ' + format;
                    
                    svgEl.appendChild(text);
                }
            }
        }

        function getTargetCanvasOrSvg(callback) {
            var dpi = parseInt(document.getElementById('exportDpiSelect').value || 1);
            if (currentCodeType === 'qr') {
                var frameSelect = document.getElementById('qrFrameSelect');
                if (frameSelect && frameSelect.value !== 'none') {
                    var wrapper = document.getElementById('qrFrameWrapper');
                    html2canvas(wrapper, { scale: dpi, backgroundColor: null }).then(function(canvas) {
                        callback(canvas, null);
                    });
                } else {
                    var originalSize = 280;
                    qrCodeInstance.update({ width: originalSize * dpi, height: originalSize * dpi });
                    setTimeout(() => {
                        var canvas = document.querySelector('#qrCanvasContainer canvas');
                        callback(canvas, null);
                        qrCodeInstance.update({ width: originalSize, height: originalSize });
                    }, 100);
                }
            } else {
                var svg = document.getElementById('barcodeSvgContainer');
                if (!svg || svg.innerHTML.includes('ef4444')) {
                    Swal.fire({
                        title: 'Código Inválido',
                        text: 'No se puede exportar un código con formato inválido.',
                        icon: 'warning',
                        confirmButtonText: 'Entendido'
                    });
                    return;
                }

                var showText = document.getElementById('barcodeShowText') ? document.getElementById('barcodeShowText').checked : true;
                var fontFamily = document.getElementById('barcodeFontFamily') ? document.getElementById('barcodeFontFamily').value : 'Inter';
                var fontWeight = document.getElementById('barcodeFontWeight') ? document.getElementById('barcodeFontWeight').value : '600';
                var fontSize = document.getElementById('barcodeFontSize') ? parseInt(document.getElementById('barcodeFontSize').value) : 20;
                if (showText) {
                    applyBarcodeTypography(svg, fontFamily, fontWeight, fontSize);
                }

                var xml = new XMLSerializer().serializeToString(svg);
                var svg64 = btoa(unescape(encodeURIComponent(xml)));
                var image64 = 'data:image/svg+xml;base64,' + svg64;
                var img = new Image();
                img.onload = function() {
                    var canvas = document.createElement('canvas');
                    canvas.width = svg.getBoundingClientRect().width * dpi;
                    canvas.height = svg.getBoundingClientRect().height * dpi;
                    var ctx = canvas.getContext('2d');
                    ctx.scale(dpi, dpi);
                    ctx.drawImage(img, 0, 0);
                    callback(canvas, xml);
                };
                img.src = image64;
            }
        }

        // Download SVG
        document.getElementById('btnDownloadCodeSVG').addEventListener('click', function() {
            if (currentCodeType === 'qr') {
                qrCodeInstance.download({ name: "codigo-qr", extension: "svg" });
            } else {
                var svg = document.getElementById('barcodeSvgContainer');
                if (!svg || svg.innerHTML.includes('ef4444')) {
                    Swal.fire({
                        title: 'Código Inválido',
                        text: 'No se puede exportar un código con formato inválido.',
                        icon: 'warning',
                        confirmButtonText: 'Entendido'
                    });
                    return;
                }

                var showText = document.getElementById('barcodeShowText') ? document.getElementById('barcodeShowText').checked : true;
                var fontFamily = document.getElementById('barcodeFontFamily') ? document.getElementById('barcodeFontFamily').value : 'Inter';
                var fontWeight = document.getElementById('barcodeFontWeight') ? document.getElementById('barcodeFontWeight').value : '600';
                var fontSize = document.getElementById('barcodeFontSize') ? parseInt(document.getElementById('barcodeFontSize').value) : 20;
                if (showText) {
                    applyBarcodeTypography(svg, fontFamily, fontWeight, fontSize);
                }

                var xml = new XMLSerializer().serializeToString(svg);
                var xmlHeader = '<' + '?xml version="1.0" encoding="UTF-8"?>\n';
                if (!xml.startsWith('<' + '?xml')) {
                    xml = xmlHeader + xml;
                }
                var blob = new Blob([xml], {type: 'image/svg+xml;charset=utf-8'});
                saveAs(blob, 'codigo-barras.svg');
            }
        });

        // Download PNG
        document.getElementById('btnDownloadCodePNG').addEventListener('click', function() {
            getTargetCanvasOrSvg(function(canvas) {
                if(!canvas) return;
                canvas.toBlob(function(blob) {
                    saveAs(blob, currentCodeType === 'qr' ? 'codigo-qr.png' : 'codigo-barras.png');
                });
            });
        });

        // Download PDF
        document.getElementById('btnDownloadCodePDF').addEventListener('click', function() {
            getTargetCanvasOrSvg(function(canvas) {
                if(!canvas) return;
                var imgData = canvas.toDataURL('image/png');
                var pdf = new jspdf.jsPDF('p', 'mm', 'a4');
                var pdfWidth = pdf.internal.pageSize.getWidth();
                var pdfHeight = (canvas.height * pdfWidth) / canvas.width;
                if(pdfHeight > pdf.internal.pageSize.getHeight()) {
                    pdfHeight = pdf.internal.pageSize.getHeight() - 20;
                    pdfWidth = (canvas.width * pdfHeight) / canvas.height;
                }
                var x = (pdf.internal.pageSize.getWidth() - pdfWidth) / 2;
                var y = (pdf.internal.pageSize.getHeight() - pdfHeight) / 2;
                pdf.addImage(imgData, 'PNG', x, y, pdfWidth, pdfHeight);
                pdf.save(currentCodeType === 'qr' ? 'codigo-qr.pdf' : 'codigo-barras.pdf');
            });
        });

        // Copy to Clipboard
        document.getElementById('btnCopyClipboard').addEventListener('click', function() {
            getTargetCanvasOrSvg(function(canvas) {
                if(!canvas) return;
                canvas.toBlob(function(blob) {
                    try {
                        navigator.clipboard.write([new ClipboardItem({'image/png': blob})]).then(function() {
                            showNoticeToast('¡Imagen copiada al portapapeles!');
                        });
                    } catch(e) {
                        Swal.fire({
                            title: 'Copiado no soportado',
                            text: 'Tu navegador no permite copiar imágenes binarias directamente. Puedes descargar el archivo PNG.',
                            icon: 'info',
                            confirmButtonText: 'Entendido'
                        });
                    }
                });
            });
        });

        // Bulk Barcode Generation
        var btnBulk = document.getElementById('btnBulkBarcode');
        if(btnBulk) {
            btnBulk.addEventListener('click', function() {
                var start = parseInt(document.getElementById('barcodeSeqStart').value);
                var end = parseInt(document.getElementById('barcodeSeqEnd').value);
                if(isNaN(start) || isNaN(end) || start > end || (end - start) > 500) {
                    Swal.fire({
                        title: 'Rango Inválido',
                        text: 'El rango debe ser válido y tener como máximo 500 códigos a la vez.',
                        icon: 'warning',
                        confirmButtonText: 'Entendido'
                    });
                    return;
                }
                
                var format = document.getElementById('barcodeFormatSelect').value;
                var lineColor = document.getElementById('barcodeLineColor').value;
                var bgColor = document.getElementById('barcodeBgColor').value;
                var showText = document.getElementById('barcodeShowText').checked;
                var fontSize = document.getElementById('barcodeFontSize') ? parseInt(document.getElementById('barcodeFontSize').value) : 20;
                var fontFamily = document.getElementById('barcodeFontFamily') ? document.getElementById('barcodeFontFamily').value : 'Inter';
                var fontWeight = document.getElementById('barcodeFontWeight') ? document.getElementById('barcodeFontWeight').value : '600';
                var fontOptionVal = (fontWeight === 'bold' || parseInt(fontWeight) >= 600) ? 'bold' : '';
                
                var zip = new JSZip();
                var folder = zip.folder("codigos_barras");
                
                var dummySvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                document.body.appendChild(dummySvg);
                
                try {
                    for(var i = start; i <= end; i++) {
                        var val = '';
                        if(format === 'UPC') {
                            var numStr = i.toString().padStart(11, '0').substring(0, 11);
                            val = numStr + calculateUPCChecksum(numStr);
                        } else if(format === 'EAN13') {
                            var numStr = i.toString().padStart(12, '0').substring(0, 12);
                            val = numStr + calculateEAN13Checksum(numStr);
                        } else {
                            val = i.toString();
                        }
                        
                        JsBarcode(dummySvg, val, {
                            format: format,
                            lineColor: lineColor,
                            background: bgColor,
                            displayValue: showText,
                            fontSize: fontSize,
                            font: fontFamily,
                            fontOptions: fontOptionVal,
                            width: 2,
                            height: 90,
                            margin: 8
                        });

                        if (showText) {
                            applyBarcodeTypography(dummySvg, fontFamily, fontWeight, fontSize);
                        }

                        var xml = new XMLSerializer().serializeToString(dummySvg);
                        var xmlHeader = '<' + '?xml version="1.0" encoding="UTF-8"?>\n';
                        if (!xml.startsWith('<' + '?xml')) {
                            xml = xmlHeader + xml;
                        }
                        folder.file(val + ".svg", xml);
                    }
                    
                    zip.generateAsync({type:"blob"}).then(function(content) {
                        saveAs(content, "codigos_barras_masivos.zip");
                        document.body.removeChild(dummySvg);
                        showNoticeToast('¡Archivo ZIP descargado con éxito!');
                    });
                } catch(e) {
                    Swal.fire({
                        title: 'Error en Generación',
                        text: 'Ocurrió un error al generar los códigos masivos. Verifica los parámetros.',
                        icon: 'error',
                        confirmButtonText: 'Entendido'
                    });
                    if(document.body.contains(dummySvg)) document.body.removeChild(dummySvg);
                }
            });
        }
        
        // Share Link Logic
        document.getElementById('btnShareLink').addEventListener('click', function() {
            var config = captureConfig();
            fetch('api_qr_share.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({config: config})
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    navigator.clipboard.writeText(data.link).then(() => {
                        Swal.fire({
                            title: '¡Enlace Generado!',
                            text: 'El enlace directo ha sido copiado a tu portapapeles.',
                            icon: 'success',
                            confirmButtonText: 'Genial'
                        });
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: 'No se pudo generar el enlace: ' + data.message,
                        icon: 'error',
                        confirmButtonText: 'Entendido'
                    });
                }
            })
            .catch(err => {
                Swal.fire({
                    title: 'Error de Conexión',
                    text: 'Ocurrió un error de conexión al intentar generar el enlace.',
                    icon: 'error',
                    confirmButtonText: 'Entendido'
                });
            });
        });

        // History & Config Logic
        function captureConfig() {
            var conf = {
                type: currentCodeType,
                qrFormat: document.getElementById('qrFormatSelect').value,
                barcodeFormat: document.getElementById('barcodeFormatSelect').value,
                timestamp: new Date().getTime()
            };
            qrInputs.forEach(id => {
                let el = document.getElementById(id);
                if(el && el.type !== 'file') conf[id] = el.type === 'checkbox' ? el.checked : el.value;
            });
            barcodeInputs.forEach(id => {
                let el = document.getElementById(id);
                if(el) conf[id] = el.type === 'checkbox' ? el.checked : el.value;
            });
            var frameSelect = document.getElementById('qrFrameSelect');
            if(frameSelect) conf.qrFrameSelect = frameSelect.value;
            return conf;
        }

        function applyConfig(conf) {
            if(!conf) return;
            var targetBtn = document.querySelector('.qr-type-switcher button[data-type="' + conf.type + '"]');
            if(targetBtn) targetBtn.click();
            
            if(conf.qrFormat) {
                document.getElementById('qrFormatSelect').value = conf.qrFormat;
                document.getElementById('qrFormatSelect').dispatchEvent(new Event('change'));
            }
            if(conf.barcodeFormat) {
                document.getElementById('barcodeFormatSelect').value = conf.barcodeFormat;
            }
            
            Object.keys(conf).forEach(k => {
                let el = document.getElementById(k);
                if(el && el.type !== 'file') {
                    if(el.type === 'checkbox') el.checked = conf[k];
                    else el.value = conf[k];
                }
            });
            
            if(conf.type === 'qr') generateQRCode();
            else generateBarcode();

            showNoticeToast('Configuración restaurada desde historial', 'info');
        }

        function saveToHistory() {
            var conf = captureConfig();
            var hist = JSON.parse(localStorage.getItem('qr_history') || '[]');
            if(hist.length > 0) {
                var last = hist[0];
                if(last.type === conf.type && last.qrFormat === conf.qrFormat && last.barcodeFormat === conf.barcodeFormat) {
                    var isSame = true;
                    var inputs = conf.type === 'qr' ? qrInputs : barcodeInputs;
                    inputs.forEach(id => { if(last[id] != conf[id] && id !== 'timestamp') isSame = false; });
                    if(isSame) return; 
                }
            }
            hist.unshift(conf);
            if(hist.length > 12) hist.pop();
            localStorage.setItem('qr_history', JSON.stringify(hist));
            renderHistory();
        }

        function renderHistory() {
            var list = document.getElementById('qrHistoryList');
            if(!list) return;
            list.innerHTML = '';
            var hist = JSON.parse(localStorage.getItem('qr_history') || '[]');
            if(hist.length === 0) {
                list.innerHTML = '<div style="font-size:0.8rem;color:var(--text-muted);text-align:center;padding:16px"><i class="ph ph-clock" style="font-size:1.4rem;display:block;margin-bottom:4px;opacity:0.6"></i>No hay códigos en el historial reciente.</div>';
                return;
            }
            hist.forEach((item) => {
                var card = document.createElement('div');
                card.className = 'qr-history-card';
                card.title = 'Haz clic para cargar esta configuración';
                
                var isQr = item.type === 'qr';
                var iconClass = isQr ? 'ph ph-qr-code' : 'ph ph-barcode';
                var title = isQr ? 'QR · ' + (item.qrFormat || 'URL').toUpperCase() : 'Barras · ' + (item.barcodeFormat || 'CODE128');
                var valPreview = isQr ? (item.qrInputUrl || item.qrInputText || item.qrInputWaPhone || 'QR Configurado') : (item.barcodeInputVal || 'Código de barras');
                var date = new Date(item.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                
                card.innerHTML = `
                    <div class="qr-history-left">
                        <div class="qr-history-icon-badge"><i class="${iconClass}"></i></div>
                        <div class="qr-history-meta">
                            <span class="qr-history-tag">${title}</span>
                            <span class="qr-history-val">${valPreview}</span>
                        </div>
                    </div>
                    <span class="qr-history-time">${date}</span>
                `;
                card.onclick = function() { applyConfig(item); };
                list.appendChild(card);
            });
        }
        
        document.getElementById('btnClearHistory')?.addEventListener('click', function() {
            localStorage.removeItem('qr_history');
            renderHistory();
            showNoticeToast('Historial vaciado', 'info');
        });

        // =====================================================================
        // Init
        // =====================================================================
        document.getElementById('colorPicker').value = currentHex;
        document.getElementById('hexInput').value = currentHex;
        updateAll();
        loadPalettes();
        renderHistory();
        updateBulkCount();
        
        // Auto-save history periodically
        setInterval(saveToHistory, 6000);
        
        // Load from Share Link if URL has ?qr=HASH
        var urlParams = new URLSearchParams(window.location.search);
        var qrHash = urlParams.get('qr');
        if(qrHash) {
            fetch('api_qr_share.php?hash=' + qrHash)
            .then(res => res.json())
            .then(data => {
                if(data.success && data.config) {
                    applyConfig(data.config);
                } else {
                    Swal.fire({
                        title: 'Enlace Inválido',
                        text: 'El enlace compartido de este código es inválido o ha expirado.',
                        icon: 'warning',
                        confirmButtonText: 'Entendido'
                    });
                }
            });
        } else {
            setTimeout(() => { generateQRCode(); generateBarcode(); }, 350);
        }

    }); // DOMContentLoaded

})(); // IIFE
</script>
<script src="modules/herramientas/linktree.js"></script>

<?php require_once 'includes/footer.php'; ?>
