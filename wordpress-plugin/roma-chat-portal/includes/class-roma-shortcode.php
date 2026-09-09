<?php
/**
 * Renderizado del Shortcode [roma_portal] con pestañas Soporte y Cotizar
 */

if (!defined('ABSPATH')) {
    exit;
}

class Roma_Chat_Shortcode {

    public function __construct() {
        add_shortcode('roma_portal', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    private function get_options() {
        return get_option('roma_cp_options', []);
    }

    public function register_assets() {
        wp_register_style(
            'roma-shortcode-css',
            ROMA_CP_URL . 'assets/css/roma-shortcode.css',
            [],
            ROMA_CP_VERSION
        );

        wp_register_script(
            'roma-shortcode-js',
            ROMA_CP_URL . 'assets/js/roma-shortcode.js',
            ['jquery'],
            ROMA_CP_VERSION,
            true
        );
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'default_tab' => 'soporte',
            'title' => 'Portal de Atención & Clientes',
            'subtitle' => 'Consulta tus servicios contratados o solicita una nueva cotización en segundos.',
        ], $atts, 'roma_portal');

        wp_enqueue_style('roma-shortcode-css');
        wp_enqueue_script('roma-shortcode-js');

        $options = $this->get_options();
        $crmUrl = rtrim($options['crm_url'] ?? 'http://localhost/CESARMENDOZA', '/');

        wp_localize_script('roma-shortcode-js', 'RomaPortalConfig', [
            'crmUrl' => $crmUrl,
            'apiUrl' => $crmUrl . '/modules/mensajes/api_widget.php',
            'apiKey' => $options['crm_app_key'] ?? '',
            'guestUrl' => $crmUrl . '/index.php?module=mensajes&action=guest'
        ]);

        $activeTab = in_array(strtolower($atts['default_tab']), ['cotizar', 'soporte']) ? strtolower($atts['default_tab']) : 'soporte';

        ob_start();
        ?>
        <div class="roma-portal-wrapper" id="roma-portal">
            <div class="roma-portal-card">
                
                <!-- Encabezado del Portal -->
                <div class="roma-portal-header">
                    <div class="roma-portal-badge">
                        <i class="ph ph-shield-check"></i> Roma CRM Conectado
                    </div>
                    <h2 class="roma-portal-title"><?php echo esc_html($atts['title']); ?></h2>
                    <p class="roma-portal-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
                </div>

                <!-- Selector de Pestañas -->
                <div class="roma-portal-tabs" role="tablist">
                    <button type="button" 
                            class="roma-tab-btn <?php echo ($activeTab === 'soporte') ? 'active' : ''; ?>" 
                            data-tab="soporte" 
                            role="tab" 
                            aria-selected="<?php echo ($activeTab === 'soporte') ? 'true' : 'false'; ?>">
                        <i class="ph ph-headset"></i>
                        <span>Soporte & Mis Servicios</span>
                    </button>
                    <button type="button" 
                            class="roma-tab-btn <?php echo ($activeTab === 'cotizar') ? 'active' : ''; ?>" 
                            data-tab="cotizar" 
                            role="tab" 
                            aria-selected="<?php echo ($activeTab === 'cotizar') ? 'true' : 'false'; ?>">
                        <i class="ph ph-lightning"></i>
                        <span>Cotizar Nuevo Servicio</span>
                    </button>
                </div>

                <!-- Contenido de Pestañas -->
                <div class="roma-portal-content">
                    
                    <!-- ========================================================= -->
                    <!-- PESTAÑA 1: SOPORTE Y CONSULTA DE SERVICIOS POR DNI/TEL    -->
                    <!-- ========================================================= -->
                    <div id="roma-tab-soporte" class="roma-tab-pane <?php echo ($activeTab === 'soporte') ? 'active' : ''; ?>" role="tabpanel">
                        
                        <div class="roma-soporte-search-box">
                            <div class="roma-search-instructions">
                                <i class="ph ph-info"></i>
                                <span>Ingresa tu <strong>DNI</strong> o <strong>número de WhatsApp</strong> registrado para ver el estado de tus marcas y servicios activos.</span>
                            </div>
                            
                            <form id="roma-lookup-form" class="roma-search-form">
                                <div class="roma-search-input-group">
                                    <i class="ph ph-magnifying-glass"></i>
                                    <input type="text" 
                                           id="roma-lookup-query" 
                                           name="query" 
                                           placeholder="Ej: 72345678 o 987654321" 
                                           required 
                                           autocomplete="off">
                                    <button type="submit" id="roma-lookup-submit" class="roma-btn-search">
                                        <span>Consultar</span>
                                        <i class="ph ph-arrow-right"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Indicador de Carga -->
                        <div id="roma-lookup-loading" class="roma-lookup-state" style="display: none;">
                            <div class="roma-spinner"></div>
                            <p>Buscando tus datos en Roma CRM...</p>
                        </div>

                        <!-- Mensaje de Error / No Encontrado -->
                        <div id="roma-lookup-error" class="roma-lookup-state roma-state-error" style="display: none;">
                            <div class="roma-error-icon"><i class="ph ph-warning-circle"></i></div>
                            <h4 id="roma-error-title">No encontrado</h4>
                            <p id="roma-error-desc">No encontramos registros asociados con los datos ingresados.</p>
                            <button type="button" class="roma-btn-inline-quote" onclick="document.querySelector('[data-tab=\'cotizar\']').click();">
                                <i class="ph ph-plus-circle"></i> Solicitar cotización como nuevo cliente
                            </button>
                        </div>

                        <!-- Resultados de la Consulta -->
                        <div id="roma-lookup-results" class="roma-lookup-results" style="display: none;">
                            
                            <!-- Tarjeta de Perfil Cliente -->
                            <div class="roma-client-profile-card">
                                <div class="roma-client-avatar">
                                    <i class="ph ph-user"></i>
                                </div>
                                <div class="roma-client-info">
                                    <h3 id="res-client-name" class="roma-client-name">-</h3>
                                    <div class="roma-client-meta">
                                        <span id="res-client-dni"><i class="ph ph-identification-card"></i> DNI: <strong>-</strong></span>
                                        <span id="res-client-phone"><i class="ph ph-whatsapp-logo"></i> <strong>-</strong></span>
                                    </div>
                                </div>
                                <div class="roma-client-action">
                                    <button type="button" id="roma-btn-direct-support" class="roma-btn-primary">
                                        <i class="ph ph-chat-teardrop-text"></i>
                                        <span>Contactar a Soporte</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Sección: Marcas Registradas -->
                            <div class="roma-section-box">
                                <h4 class="roma-section-title">
                                    <i class="ph ph-buildings"></i>
                                    <span>Tus Marcas Registradas</span>
                                    <span id="res-brands-count" class="roma-count-badge">0</span>
                                </h4>
                                <div id="res-brands-list" class="roma-brands-grid">
                                    <!-- Marcas renderizadas por JS -->
                                </div>
                            </div>

                            <!-- Sección: Servicios y Proyectos Activos -->
                            <div class="roma-section-box" id="res-project-services-section">
                                <h4 class="roma-section-title">
                                    <i class="ph ph-briefcase"></i>
                                    <span>Servicios y Proyectos en Curso</span>
                                    <span id="res-services-count" class="roma-count-badge">0</span>
                                </h4>
                                <div id="res-services-list" class="roma-services-list">
                                    <!-- Servicios renderizados por JS -->
                                </div>
                            </div>

                        </div>

                    </div>

                    <!-- ========================================================= -->
                    <!-- PESTAÑA 2: COTIZAR SERVICIO DINÁMICO                     -->
                    <!-- ========================================================= -->
                    <div id="roma-tab-cotizar" class="roma-tab-pane <?php echo ($activeTab === 'cotizar') ? 'active' : ''; ?>" role="tabpanel">
                        
                        <div class="roma-quote-intro">
                            <div class="roma-quote-icon"><i class="ph ph-sparkle"></i></div>
                            <div>
                                <h3>Solicita una cotización personalizada</h3>
                                <p>Selecciona el servicio que necesitas y nuestro equipo se contactará al instante por WhatsApp o chat en vivo.</p>
                            </div>
                        </div>

                        <form id="roma-quote-form" class="roma-portal-form">
                            <div class="roma-form-grid">
                                
                                <div class="roma-form-group">
                                    <label for="quote-name">Nombre completo o Empresa <span class="required">*</span></label>
                                    <div class="roma-input-icon">
                                        <i class="ph ph-user"></i>
                                        <input type="text" id="quote-name" name="name" placeholder="Ej. Alex Rodríguez" required>
                                    </div>
                                </div>

                                <div class="roma-form-group">
                                    <label for="quote-dni">DNI o RUC</label>
                                    <div class="roma-input-icon">
                                        <i class="ph ph-identification-card"></i>
                                        <input type="text" id="quote-dni" name="dni" placeholder="Ej. 10456789123">
                                    </div>
                                </div>

                                <div class="roma-form-group">
                                    <label for="quote-phone">WhatsApp / Teléfono <span class="required">*</span></label>
                                    <div class="roma-input-icon">
                                        <i class="ph ph-whatsapp-logo"></i>
                                        <input type="tel" id="quote-phone" name="phone" placeholder="Ej. 987654321" required>
                                    </div>
                                </div>

                                <div class="roma-form-group">
                                    <label for="quote-email">Correo Electrónico</label>
                                    <div class="roma-input-icon">
                                        <i class="ph ph-envelope-simple"></i>
                                        <input type="email" id="quote-email" name="email" placeholder="correo@ejemplo.com">
                                    </div>
                                </div>

                            </div>

                            <div class="roma-form-group">
                                <label for="quote-service">Servicio de Interés <span class="required">*</span></label>
                                <div class="roma-input-icon">
                                    <i class="ph ph-stack"></i>
                                    <select id="quote-service" name="service_id" required>
                                        <option value="">Cargando catálogo de servicios desde Roma CRM...</option>
                                    </select>
                                </div>
                                <input type="hidden" id="quote-service-name" name="service_name" value="">
                            </div>

                            <div class="roma-form-group">
                                <label for="quote-message">Detalles o Requerimientos del Proyecto</label>
                                <textarea id="quote-message" name="message" rows="3" placeholder="Cuéntanos brevemente sobre tu proyecto, objetivos, plazos o especificaciones..."></textarea>
                            </div>

                            <div class="roma-form-submit-row">
                                <button type="submit" id="quote-submit-btn" class="roma-btn-submit-quote">
                                    <i class="ph ph-paper-plane-tilt"></i>
                                    <span>Enviar Solicitud y Abrir Chat</span>
                                </button>
                                <span class="roma-privacy-hint">🔒 Tus datos están protegidos y solo se usarán para contactarte.</span>
                            </div>
                        </form>

                        <!-- Mensaje de Éxito al Cotizar -->
                        <div id="roma-quote-success" class="roma-quote-success-view" style="display: none;">
                            <div class="roma-success-icon"><i class="ph ph-check-circle"></i></div>
                            <h3>¡Solicitud Enviada con Éxito!</h3>
                            <p id="roma-quote-success-msg">Hemos registrado tu requerimiento y asignado un asesor comercial.</p>
                            
                            <div class="roma-success-actions">
                                <button type="button" id="roma-btn-open-quote-chat" class="roma-btn-primary">
                                    <i class="ph ph-chat-circle-dots"></i>
                                    <span>Continuar en el Chat en Vivo</span>
                                </button>
                                <button type="button" id="roma-btn-new-quote" class="roma-btn-outline">
                                    <i class="ph ph-arrow-counter-clockwise"></i>
                                    <span>Nueva Cotización</span>
                                </button>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Footer del Card -->
                <div class="roma-portal-footer">
                    <span>Desarrollado con Roma CRM</span>
                    <div class="roma-portal-footer-links">
                        <span><i class="ph ph-lock-key"></i> Conexión Segura</span>
                    </div>
                </div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
