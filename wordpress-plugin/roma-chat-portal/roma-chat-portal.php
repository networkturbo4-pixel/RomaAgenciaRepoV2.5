<?php
/**
 * Plugin Name: Roma Chat & Portal de Clientes
 * Plugin URI: https://roma.com/
 * Description: Widget de chat flotante en tiempo real conectado con Roma CRM, con soporte para expandir en nueva pestaña, personalización visual completa (colores, tipografía, posición) y shortcode [roma_portal] con consulta de servicios/marcas por DNI o teléfono y cotizador interactivo.
 * Version: 1.0.0
 * Author: Roma Agencia
 * Author URI: https://roma.com/
 * Text Domain: roma-chat-portal
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit; // Evitar acceso directo
}

define('ROMA_CP_VERSION', '1.0.0');
define('ROMA_CP_FILE', __FILE__);
define('ROMA_CP_DIR', plugin_dir_path(__FILE__));
define('ROMA_CP_URL', plugin_dir_url(__FILE__));

// Cargar Clases del Plugin
require_once ROMA_CP_DIR . 'includes/class-roma-admin.php';
require_once ROMA_CP_DIR . 'includes/class-roma-widget.php';
require_once ROMA_CP_DIR . 'includes/class-roma-shortcode.php';

/**
 * Clase principal de inicialización
 */
class Roma_Chat_Portal {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Inicializar módulos
        new Roma_Chat_Admin();
        new Roma_Chat_Widget();
        new Roma_Chat_Shortcode();

        // Hooks generales
        add_action('wp_enqueue_scripts', [$this, 'enqueue_common_assets']);
        register_activation_hook(ROMA_CP_FILE, [$this, 'activate_plugin']);
    }

    public function activate_plugin() {
        // Opciones por defecto
        $defaults = [
            'crm_url' => 'http://localhost/CESARMENDOZA',
            'pusher_key' => 'b31f38612d61b0285c78',
            'pusher_cluster' => 'us2',
            'enable_widget' => 'yes',
            'bubble_position' => 'bottom-right',
            'primary_color' => '#6366f1',
            'secondary_color' => '#4f46e5',
            'text_color' => '#ffffff',
            'font_family' => 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            'widget_title' => 'Roma Soporte & Ventas',
            'widget_subtitle' => 'Normalmente respondemos en minutos',
            'welcome_msg' => '¡Hola! 👋 ¿En qué podemos ayudarte hoy? Escríbenos y un asesor te atenderá al instante.',
            'button_icon' => 'chat',
            'enable_sound' => 'yes',
        ];

        if (!get_option('roma_cp_options')) {
            update_option('roma_cp_options', $defaults);
        }
    }

    public function enqueue_common_assets() {
        // Phosphor Icons CSS & JS para íconos modernos
        wp_enqueue_style('phosphor-icons-css', 'https://unpkg.com/@phosphor-icons/web@2.1.1/src/index.css', [], '2.1.1');
        wp_enqueue_script('phosphor-icons', 'https://unpkg.com/@phosphor-icons/web', [], '2.1.1', false);
        
        // Pusher JS para WebSockets en tiempo real
        wp_enqueue_script('pusher-js', 'https://js.pusher.com/8.2.0/pusher.min.js', [], '8.2.0', true);
    }
}

// Iniciar Plugin
add_action('plugins_loaded', function() {
    Roma_Chat_Portal::get_instance();
});
