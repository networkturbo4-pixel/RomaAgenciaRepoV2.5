<?php
/**
 * Panel de Administración y Configuración para Roma Chat & Portal
 */

if (!defined('ABSPATH')) {
    exit;
}

class Roma_Chat_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_roma_cp_test_connection', [$this, 'ajax_test_connection']);
    }

    public function add_settings_page() {
        add_menu_page(
            'Roma Chat & Portal',
            'Roma Portal',
            'manage_options',
            'roma-chat-portal',
            [$this, 'render_admin_page'],
            'dashicons-format-chat',
            28
        );
    }

    public function register_settings() {
        register_setting('roma_cp_group', 'roma_cp_options', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_options']
        ]);
    }

    public function sanitize_options($input) {
        $clean = [];
        $clean['crm_url'] = esc_url_raw(rtrim($input['crm_url'] ?? '', '/'));
        $clean['crm_app_key'] = sanitize_text_field($input['crm_app_key'] ?? '');
        $clean['pusher_key'] = sanitize_text_field($input['pusher_key'] ?? '');
        $clean['pusher_cluster'] = sanitize_text_field($input['pusher_cluster'] ?? 'us2');
        $clean['enable_widget'] = isset($input['enable_widget']) && $input['enable_widget'] === 'yes' ? 'yes' : 'no';
        $clean['bubble_position'] = in_array($input['bubble_position'] ?? '', ['bottom-right', 'bottom-left']) ? $input['bubble_position'] : 'bottom-right';
        $clean['primary_color'] = sanitize_hex_color($input['primary_color'] ?? '#6366f1') ?: '#6366f1';
        $clean['secondary_color'] = sanitize_hex_color($input['secondary_color'] ?? '#4f46e5') ?: '#4f46e5';
        $clean['text_color'] = sanitize_hex_color($input['text_color'] ?? '#ffffff') ?: '#ffffff';
        $clean['font_family'] = sanitize_text_field($input['font_family'] ?? 'Inter, sans-serif');
        $clean['button_icon'] = sanitize_text_field($input['button_icon'] ?? 'ph-chat-circle-dots');
        $clean['widget_title'] = sanitize_text_field($input['widget_title'] ?? 'Roma Soporte & Ventas');
        $clean['widget_subtitle'] = sanitize_text_field($input['widget_subtitle'] ?? 'Normalmente respondemos en minutos');
        $clean['welcome_msg'] = sanitize_textarea_field($input['welcome_msg'] ?? '');
        return $clean;
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_roma-chat-portal') {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_script('phosphor-icons-admin', 'https://unpkg.com/@phosphor-icons/web', [], '2.1.1', false);
    }

    public function ajax_test_connection() {
        check_ajax_referer('roma_cp_test_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado']);
        }

        $crm_url = esc_url_raw(rtrim($_POST['crm_url'] ?? '', '/'));
        $app_key = sanitize_text_field($_POST['crm_app_key'] ?? '');

        if (empty($crm_url)) {
            wp_send_json_error(['message' => 'Ingresa la URL del CRM']);
        }

        $apiUrl = $crm_url . '/modules/mensajes/api_widget.php?action=get_services';
        $args = [
            'timeout' => 8,
            'sslverify' => false,
            'headers' => [
                'X-Roma-Api-Key' => $app_key
            ]
        ];
        $response = wp_remote_get($apiUrl, $args);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Error de conexión: ' . $response->get_error_message()]);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data && isset($data['success']) && $data['success'] === true) {
            $count = isset($data['services']) ? count($data['services']) : 0;
            wp_send_json_success([
                'message' => "¡Conexión exitosa con Roma CRM! Se obtuvieron {$count} servicios activos disponibles."
            ]);
        } else {
            wp_send_json_error([
                'message' => 'El servidor respondió pero ocurrió un error: ' . ($data['error'] ?? 'Respuesta inesperada')
            ]);
        }
    }

    public function render_admin_page() {
        $options = get_option('roma_cp_options', []);
        $crm_url = $options['crm_url'] ?? 'http://localhost/CESARMENDOZA';
        $crm_app_key = $options['crm_app_key'] ?? '';
        $pusher_key = $options['pusher_key'] ?? 'b31f38612d61b0285c78';
        $pusher_cluster = $options['pusher_cluster'] ?? 'us2';
        $enable_widget = ($options['enable_widget'] ?? 'yes') === 'yes';
        $bubble_position = $options['bubble_position'] ?? 'bottom-right';
        $primary_color = $options['primary_color'] ?? '#6366f1';
        $secondary_color = $options['secondary_color'] ?? '#4f46e5';
        $text_color = $options['text_color'] ?? '#ffffff';
        $font_family = $options['font_family'] ?? 'Inter, sans-serif';
        $button_icon = $options['button_icon'] ?? 'ph-chat-circle-dots';
        $widget_title = $options['widget_title'] ?? 'Roma Soporte & Ventas';
        $widget_subtitle = $options['widget_subtitle'] ?? 'Normalmente respondemos en minutos';
        $welcome_msg = $options['welcome_msg'] ?? '¡Hola! 👋 ¿En qué podemos ayudarte hoy? Escríbenos y un asesor te atenderá al instante.';
        ?>
        <div class="wrap roma-admin-wrap" style="max-width: 1080px; margin-top: 25px;">
            <div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: #fff; padding: 28px 32px; border-radius: 16px; margin-bottom: 25px; box-shadow: 0 10px 25px rgba(30,27,75,0.2);">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                            <span style="font-size: 28px; background: rgba(255,255,255,0.15); padding: 8px 12px; border-radius: 10px;">💬</span>
                            <h1 style="color: #fff; margin: 0; font-size: 26px; font-weight: 700; display: inline-block;">Roma Chat & Portal</h1>
                        </div>
                        <p style="color: #c7d2fe; margin: 0; font-size: 14px;">Configura la integración en tiempo real del chat flotante y el portal de autoservicio para tus clientes.</p>
                    </div>
                    <div>
                        <span style="background: rgba(99, 102, 241, 0.4); border: 1px solid rgba(165, 180, 252, 0.3); color: #e0e7ff; padding: 6px 14px; border-radius: 30px; font-size: 13px; font-weight: 600;">
                            v1.0.0 Conectado a Roma CRM
                        </span>
                    </div>
                </div>
            </div>

            <?php settings_errors(); ?>

            <form method="post" action="options.php" id="roma-settings-form">
                <?php settings_fields('roma_cp_group'); ?>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                    <!-- Columna Izquierda: Ajustes -->
                    <div style="display: flex; flex-direction: column; gap: 24px;">
                        
                        <!-- Tarjeta 1: Conexión CRM -->
                        <div style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                            <h2 style="font-size: 18px; font-weight: 600; margin-top: 0; margin-bottom: 16px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                                🌐 Conexión con Roma CRM
                            </h2>
                            <p style="color: #64748b; font-size: 13px; margin-top: 0; margin-bottom: 20px;">
                                Especifica la URL base donde se encuentra alojado tu sistema Roma CRM y la App Key generada en el módulo de Conexiones del CRM.
                            </p>

                            <table class="form-table" style="margin-top: 0;">
                                <tr>
                                    <th scope="row" style="width: 200px;"><label for="crm_url">URL del CRM</label></th>
                                    <td>
                                        <input type="url" id="crm_url" name="roma_cp_options[crm_url]" value="<?php echo esc_attr($crm_url); ?>" class="regular-text" style="width: 100%; max-width: 480px;" placeholder="https://romaagencia.lat/" required>
                                        <p class="description">Ejemplo: <code>https://romaagencia.lat/</code> o en local <code>http://localhost/CESARMENDOZA</code></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="crm_app_key">App Key del CRM</label></th>
                                    <td>
                                        <div style="display: flex; gap: 8px; max-width: 480px;">
                                            <input type="password" id="crm_app_key" name="roma_cp_options[crm_app_key]" value="<?php echo esc_attr($crm_app_key); ?>" class="regular-text" style="width: 100%; font-family: monospace;" placeholder="roma_live_...">
                                            <button type="button" id="btn-toggle-admin-key" class="button" title="Ver / Ocultar clave">👁️</button>
                                        </div>
                                        <p class="description">Clave generada en tu CRM en <strong>Conexiones &gt; WordPress &amp; CRM API</strong>.</p>
                                        <div style="margin-top: 10px;">
                                            <button type="button" id="btn-test-conn" class="button button-secondary">
                                                🔌 Probar Conexión
                                            </button>
                                            <span id="conn-test-result" style="margin-left: 10px; font-size: 13px; font-weight: 500;"></span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="pusher_key">Pusher App Key</label></th>
                                    <td>
                                        <input type="text" id="pusher_key" name="roma_cp_options[pusher_key]" value="<?php echo esc_attr($pusher_key); ?>" class="regular-text" style="width: 100%; max-width: 320px;">
                                        <p class="description">Clave pública de Pusher para mensajería instantánea sin recargar la página.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="pusher_cluster">Pusher Cluster</label></th>
                                    <td>
                                        <input type="text" id="pusher_cluster" name="roma_cp_options[pusher_cluster]" value="<?php echo esc_attr($pusher_cluster); ?>" class="small-text" style="width: 120px;">
                                        <p class="description">Por defecto: <code>us2</code></p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Tarjeta 2: Apariencia del Chat Flotante -->
                        <div style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                            <h2 style="font-size: 18px; font-weight: 600; margin-top: 0; margin-bottom: 16px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                                🎨 Personalización de la Burbuja de Chat
                            </h2>

                            <table class="form-table" style="margin-top: 0;">
                                <tr>
                                    <th scope="row">Activar Widget Flotante</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="roma_cp_options[enable_widget]" value="yes" <?php checked($enable_widget, true); ?>>
                                            Mostrar la burbuja de chat flotante en todas las páginas públicas del sitio
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="bubble_position">Posición en Pantalla</label></th>
                                    <td>
                                        <select id="bubble_position" name="roma_cp_options[bubble_position]">
                                            <option value="bottom-right" <?php selected($bubble_position, 'bottom-right'); ?>>Abajo a la Derecha (Predeterminado)</option>
                                            <option value="bottom-left" <?php selected($bubble_position, 'bottom-left'); ?>>Abajo a la Izquierda</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Colores del Widget</th>
                                    <td>
                                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                            <div>
                                                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: #475569;">Color Primario</label>
                                                <input type="text" name="roma_cp_options[primary_color]" value="<?php echo esc_attr($primary_color); ?>" class="roma-color-picker" data-default-color="#6366f1">
                                            </div>
                                            <div>
                                                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: #475569;">Gradiente / Secundario</label>
                                                <input type="text" name="roma_cp_options[secondary_color]" value="<?php echo esc_attr($secondary_color); ?>" class="roma-color-picker" data-default-color="#4f46e5">
                                            </div>
                                            <div>
                                                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: #475569;">Color de Texto/Ícono</label>
                                                <input type="text" name="roma_cp_options[text_color]" value="<?php echo esc_attr($text_color); ?>" class="roma-color-picker" data-default-color="#ffffff">
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="font_family">Tipografía</label></th>
                                    <td>
                                        <select id="font_family" name="roma_cp_options[font_family]" style="width: 100%; max-width: 340px;">
                                            <option value="Inter, sans-serif" <?php selected($font_family, 'Inter, sans-serif'); ?>>Inter (Moderno y Limpio)</option>
                                            <option value="'Plus Jakarta Sans', sans-serif" <?php selected($font_family, "'Plus Jakarta Sans', sans-serif"); ?>>Plus Jakarta Sans (Elegante)</option>
                                            <option value="'Poppins', sans-serif" <?php selected($font_family, "'Poppins', sans-serif"); ?>>Poppins (Amigable y Redondo)</option>
                                            <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" <?php selected($font_family, "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"); ?>>Sistema Nativo</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="button_icon">Ícono de la Burbuja</label></th>
                                    <td>
                                        <select id="button_icon" name="roma_cp_options[button_icon]" style="width: 100%; max-width: 340px;">
                                            <option value="ph-chat-circle-dots" <?php selected($button_icon, 'ph-chat-circle-dots'); ?>>💬 Chat Circular (ph-chat-circle-dots)</option>
                                            <option value="ph-chat-teardrop-dots" <?php selected($button_icon, 'ph-chat-teardrop-dots'); ?>>🗨️ Burbuja Teardrop (ph-chat-teardrop-dots)</option>
                                            <option value="ph-headset" <?php selected($button_icon, 'ph-headset'); ?>>🎧 Soporte / Headset (ph-headset)</option>
                                            <option value="ph-sparkle" <?php selected($button_icon, 'ph-sparkle'); ?>>✨ Estrella / Sparkle (ph-sparkle)</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Tarjeta 3: Textos del Chat -->
                        <div style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                            <h2 style="font-size: 18px; font-weight: 600; margin-top: 0; margin-bottom: 16px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                                ✍️ Textos y Mensajes de Bienvenida
                            </h2>

                            <table class="form-table" style="margin-top: 0;">
                                <tr>
                                    <th scope="row"><label for="widget_title">Título del Encabezado</label></th>
                                    <td>
                                        <input type="text" id="widget_title" name="roma_cp_options[widget_title]" value="<?php echo esc_attr($widget_title); ?>" class="regular-text" style="width: 100%; max-width: 420px;">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="widget_subtitle">Subtítulo / Estado</label></th>
                                    <td>
                                        <input type="text" id="widget_subtitle" name="roma_cp_options[widget_subtitle]" value="<?php echo esc_attr($widget_subtitle); ?>" class="regular-text" style="width: 100%; max-width: 420px;">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="welcome_msg">Mensaje de Saludo Automático</label></th>
                                    <td>
                                        <textarea id="welcome_msg" name="roma_cp_options[welcome_msg]" rows="3" class="large-text" style="width: 100%; max-width: 480px;"><?php echo esc_textarea($welcome_msg); ?></textarea>
                                        <p class="description">Primer mensaje que se mostrará en la ventana del chat al abrirlo.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div style="margin-top: 10px;">
                            <?php submit_button('Guardar Toda la Configuración', 'primary button-hero'); ?>
                        </div>
                    </div>

                    <!-- Columna Derecha: Guía de Shortcode y Documentación -->
                    <div>
                        <!-- Shortcode Info -->
                        <div style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 20px;">
                            <h3 style="margin-top: 0; font-size: 16px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                                📦 Shortcode para Formularios
                            </h3>
                            <p style="font-size: 13px; color: #64748b; line-height: 1.5;">
                                Inserta el portal de soporte y cotización interactivo en cualquier página o entrada de WordPress usando:
                            </p>
                            
                            <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 12px; font-family: monospace; font-size: 14px; color: #4338ca; font-weight: bold; text-align: center; margin-bottom: 15px; user-select: all; cursor: pointer;" title="Haz clic para seleccionar">
                                [roma_portal]
                            </div>

                            <h4 style="font-size: 13px; font-weight: 700; margin: 15px 0 8px 0; color: #334155;">¿Qué incluye el portal?</h4>
                            <ul style="font-size: 13px; color: #475569; padding-left: 18px; margin: 0; line-height: 1.6;">
                                <li><strong>Pestaña 1 (Soporte):</strong> El cliente digita su DNI o Teléfono y el sistema consulta al CRM mostrando su nombre, marcas asignadas con logos, estado de membresía y servicios contratados.</li>
                                <li><strong>Pestaña 2 (Cotizar Servicio):</strong> Formulario dinámico que trae los servicios en tiempo real desde el CRM y registra la cotización con apertura de chat.</li>
                            </ul>

                            <h4 style="font-size: 13px; font-weight: 700; margin: 15px 0 8px 0; color: #334155;">Atributos opcionales:</h4>
                            <code style="display: block; background: #f1f5f9; padding: 6px 10px; border-radius: 6px; font-size: 12px; margin-bottom: 6px;">[roma_portal default_tab="cotizar"]</code>
                            <code style="display: block; background: #f1f5f9; padding: 6px 10px; border-radius: 6px; font-size: 12px;">[roma_portal title="Centro de Atención Roma"]</code>
                        </div>

                        <!-- Live Preview Card -->
                        <div style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                            <h3 style="margin-top: 0; font-size: 16px; font-weight: 700; color: #1e293b;">
                                🖥️ Vista Previa del Botón
                            </h3>
                            <div style="background: #f8fafc; border-radius: 10px; height: 160px; position: relative; border: 1px solid #e2e8f0; display: flex; align-items: flex-end; justify-content: flex-end; padding: 20px;">
                                <div style="position: absolute; top: 12px; left: 14px; font-size: 11px; color: #94a3b8; font-weight: 600; text-transform: uppercase;">
                                    Simulación en pantalla
                                </div>
                                <div id="preview-bubble" style="background: linear-gradient(135deg, <?php echo esc_attr($primary_color); ?>, <?php echo esc_attr($secondary_color); ?>); width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: <?php echo esc_attr($text_color); ?>; font-size: 26px; box-shadow: 0 8px 20px rgba(99,102,241,0.4); cursor: pointer;">
                                    <i class="ph <?php echo esc_attr($button_icon); ?>"></i>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Inicializar Color Pickers
            $('.roma-color-picker').wpColorPicker({
                change: function(event, ui) {
                    var pColor = $('input[name="roma_cp_options[primary_color]"]').val();
                    var sColor = $('input[name="roma_cp_options[secondary_color]"]').val();
                    var tColor = $('input[name="roma_cp_options[text_color]"]').val();
                    $('#preview-bubble').css({
                        'background': 'linear-gradient(135deg, ' + pColor + ', ' + sColor + ')',
                        'color': tColor
                    });
                }
            });

            // Cambiar icono en preview
            $('#button_icon').on('change', function() {
                var iconClass = $(this).val();
                $('#preview-bubble i').attr('class', 'ph ' + iconClass);
            });

            // Toggle ver/ocultar clave
            $('#btn-toggle-admin-key').on('click', function(e) {
                e.preventDefault();
                var $keyInput = $('#crm_app_key');
                var isPassword = $keyInput.attr('type') === 'password';
                $keyInput.attr('type', isPassword ? 'text' : 'password');
            });

            // Botón de Test de Conexión
            $('#btn-test-conn').on('click', function(e) {
                e.preventDefault();
                var crmUrl = $('#crm_url').val();
                var crmAppKey = $('#crm_app_key').val();
                var $status = $('#conn-test-result');
                
                $status.html('<span style="color: #6366f1;">⏳ Conectando con Roma CRM...</span>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'roma_cp_test_connection',
                        crm_url: crmUrl,
                        crm_app_key: crmAppKey,
                        nonce: '<?php echo wp_create_nonce("roma_cp_test_nonce"); ?>'
                    },
                    success: function(res) {
                        if (res.success) {
                            $status.html('<span style="color: #16a34a;">✅ ' + res.data.message + '</span>');
                        } else {
                            $status.html('<span style="color: #dc2626;">❌ ' + res.data.message + '</span>');
                        }
                    },
                    error: function() {
                        $status.html('<span style="color: #dc2626;">❌ No se pudo conectar al endpoint del CRM. Verifica la URL y la App Key.</span>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
