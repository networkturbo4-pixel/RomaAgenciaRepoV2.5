<?php
/**
 * Renderizado y comportamiento de la Burbuja Flotante de Chat
 */

if (!defined('ABSPATH')) {
    exit;
}

class Roma_Chat_Widget {

    private $options;

    public function __construct() {
        $this->options = get_option('roma_cp_options', []);
        
        $enable = $this->options['enable_widget'] ?? 'yes';
        if ($enable === 'yes') {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('wp_head', [$this, 'inject_custom_styles']);
            add_action('wp_footer', [$this, 'render_widget_html']);
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'roma-widget-css',
            ROMA_CP_URL . 'assets/css/roma-widget.css',
            [],
            ROMA_CP_VERSION
        );

        wp_enqueue_script(
            'roma-widget-js',
            ROMA_CP_URL . 'assets/js/roma-widget.js',
            ['jquery'],
            ROMA_CP_VERSION,
            true
        );

        $crmUrl = rtrim($this->options['crm_url'] ?? 'http://localhost/CESARMENDOZA', '/');

        wp_localize_script('roma-widget-js', 'RomaWidgetConfig', [
            'crmUrl' => $crmUrl,
            'apiUrl' => $crmUrl . '/modules/mensajes/api_widget.php',
            'guestUrl' => $crmUrl . '/index.php?module=mensajes&action=guest',
            'pusherKey' => $this->options['pusher_key'] ?? 'b31f38612d61b0285c78',
            'pusherCluster' => $this->options['pusher_cluster'] ?? 'us2',
            'welcomeMsg' => $this->options['welcome_msg'] ?? '¡Hola! 👋 ¿En qué podemos ayudarte hoy?',
            'widgetTitle' => $this->options['widget_title'] ?? 'Roma Soporte & Ventas',
            'widgetSubtitle' => $this->options['widget_subtitle'] ?? 'Normalmente respondemos en minutos',
            'position' => $this->options['bubble_position'] ?? 'bottom-right'
        ]);
    }

    public function inject_custom_styles() {
        $primary = $this->options['primary_color'] ?? '#6366f1';
        $secondary = $this->options['secondary_color'] ?? '#4f46e5';
        $textColor = $this->options['text_color'] ?? '#ffffff';
        $font = $this->options['font_family'] ?? 'Inter, sans-serif';
        $position = $this->options['bubble_position'] ?? 'bottom-right';

        $posCss = ($position === 'bottom-left') 
            ? 'left: 24px; right: auto;' 
            : 'right: 24px; left: auto;';

        ?>
        <style id="roma-chat-custom-css">
            :root {
                --roma-primary: <?php echo esc_attr($primary); ?>;
                --roma-secondary: <?php echo esc_attr($secondary); ?>;
                --roma-text: <?php echo esc_attr($textColor); ?>;
                --roma-font: <?php echo esc_attr($font); ?>;
            }
            #roma-chat-widget {
                <?php echo $posCss; ?>
                font-family: var(--roma-font);
            }
        </style>
        <?php
    }

    public function render_widget_html() {
        $icon = $this->options['button_icon'] ?? 'ph-chat-circle-dots';
        $title = $this->options['widget_title'] ?? 'Roma Soporte & Ventas';
        $subtitle = $this->options['widget_subtitle'] ?? 'Normalmente respondemos en minutos';
        ?>
        <!-- Roma Floating Chat Widget -->
        <div id="roma-chat-widget" class="roma-widget-container" style="display: none;">
            
            <!-- Chat Window Popup -->
            <div id="roma-chat-window" class="roma-chat-window" aria-hidden="true">
                <!-- Header -->
                <div class="roma-chat-header">
                    <div class="roma-chat-header-info">
                        <div class="roma-chat-avatar">
                            <i class="ph ph-chat-circle-dots"></i>
                            <span class="roma-status-indicator" title="En línea"></span>
                        </div>
                        <div class="roma-chat-titles">
                            <h4 class="roma-chat-title"><?php echo esc_html($title); ?></h4>
                            <p class="roma-chat-status">
                                <span class="roma-pulse-dot"></span>
                                <?php echo esc_html($subtitle); ?>
                            </p>
                        </div>
                    </div>
                    <div class="roma-chat-actions">
                        <!-- Ampliar chat en pestaña nueva -->
                        <button type="button" id="roma-btn-expand" class="roma-btn-action" title="Abrir chat en pestaña nueva" aria-label="Abrir en pantalla completa">
                            <i class="ph ph-arrow-square-out"></i>
                        </button>
                        <!-- Cerrar popup -->
                        <button type="button" id="roma-btn-close" class="roma-btn-action" title="Cerrar chat" aria-label="Cerrar">
                            <i class="ph ph-x"></i>
                        </button>
                    </div>
                </div>

                <!-- Body Screens -->
                <div class="roma-chat-body">
                    
                    <!-- Pantalla 1: Formulario de Bienvenida si no ha iniciado -->
                    <div id="roma-screen-welcome" class="roma-chat-screen">
                        <div class="roma-welcome-hero">
                            <div class="roma-welcome-icon">👋</div>
                            <h3>¡Bienvenido al chat!</h3>
                            <p>Déjanos tus datos para brindarte una atención personalizada de inmediato.</p>
                        </div>
                        <form id="roma-start-chat-form" class="roma-chat-form">
                            <div class="roma-form-group">
                                <label for="roma-input-name">Nombre completo</label>
                                <input type="text" id="roma-input-name" placeholder="Ej. Juan Pérez" required autocomplete="name">
                            </div>
                            <div class="roma-form-group">
                                <label for="roma-input-phone">WhatsApp / Teléfono</label>
                                <input type="tel" id="roma-input-phone" placeholder="Ej. +51 987 654 321" required autocomplete="tel">
                            </div>
                            <div class="roma-form-group">
                                <label for="roma-input-email">Correo (opcional)</label>
                                <input type="email" id="roma-input-email" placeholder="correo@ejemplo.com" autocomplete="email">
                            </div>
                            <button type="submit" id="roma-btn-start" class="roma-btn-submit">
                                <span>Iniciar Chat</span>
                                <i class="ph ph-paper-plane-right"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Pantalla 2: Vista de Mensajes en Vivo -->
                    <div id="roma-screen-messages" class="roma-chat-screen" style="display: none;">
                        <div id="roma-messages-list" class="roma-messages-container">
                            <!-- Los mensajes se inyectan dinámicamente -->
                        </div>
                        
                        <div id="roma-typing-indicator" class="roma-typing" style="display: none;">
                            <span class="roma-typing-dot"></span>
                            <span class="roma-typing-dot"></span>
                            <span class="roma-typing-dot"></span>
                            <span>Un asesor está respondiendo...</span>
                        </div>
                    </div>
                </div>

                <!-- Footer (solo visible en pantalla de mensajes) -->
                <div id="roma-chat-footer" class="roma-chat-footer" style="display: none;">
                    <form id="roma-send-message-form" class="roma-send-box">
                        <textarea id="roma-message-input" placeholder="Escribe tu mensaje aquí..." rows="1"></textarea>
                        <button type="submit" id="roma-btn-send" class="roma-btn-send" aria-label="Enviar mensaje">
                            <svg width="18" height="18" viewBox="0 0 256 256" fill="currentColor" style="display:block; margin-left: 2px;"><path d="M227.32,28.68a16,16,0,0,0-15.66-4.08l-.15,0L19.57,82.84a16,16,0,0,0-2.49,29.8L102,154l41.3,84.87A15.86,15.86,0,0,0,157.69,248q.83,0,1.67-.09a16,16,0,0,0,14-11.49l58.2-191.93A16,16,0,0,0,227.32,28.68ZM157.69,232l-37-76.05L178.5,98.11a8,8,0,0,0-11.31-11.31L89.37,144.62,29.8,115.11,221.72,40.18Z"></path></svg>
                        </button>
                    </form>
                    <div class="roma-chat-credit">
                        Conectado con <strong>Roma CRM</strong>
                    </div>
                </div>
            </div>

            <!-- Floating Launcher Button -->
            <button type="button" id="roma-chat-bubble" class="roma-chat-bubble" aria-label="Abrir chat de soporte" title="¿Necesitas ayuda? Chatea con nosotros">
                <i class="ph <?php echo esc_attr($icon); ?> roma-icon-open"></i>
                <i class="ph ph-x roma-icon-close" style="display: none;"></i>
                <span id="roma-unread-badge" class="roma-unread-badge" style="display: none;">1</span>
            </button>
        </div>
        <?php
    }
}
