=== Roma Chat & Portal de Clientes ===
Contributors: Roma Agencia
Tags: chat, crm, soporte, cotizador, live chat, clientes, marcas
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.4
License: GPLv2 or later

Widget flotante de chat en tiempo real conectado con Roma CRM y portal interactivo de autoservicio con consulta de servicios/marcas por DNI o teléfono y cotizador interactivo.

== DESCRIPCIÓN ==

Roma Chat & Portal conecta tu sitio web de WordPress directamente con tu sistema Roma CRM.

Funcionalidades principales:
* **Burbuja Flotante de Chat en Vivo:** Chatea en tiempo real con agentes del CRM con soporte de WebSockets (Pusher) y respaldo de sondeo inteligente.
* **Ampliar Chat en Nueva Pestaña:** Botón dedicado que permite al visitante expandir la conversación a pantalla completa en una nueva pestaña sin perder el hilo ni su sesión.
* **Personalización Total:** Configura colores primarios, secundarios, color de texto, posición (abajo a la derecha o abajo a la izquierda), tipografías e íconos desde el panel de administración de WordPress en tiempo real.
* **Shortcode [roma_portal]:** Módulo moderno de dos pestañas:
  - **Pestaña Soporte:** El cliente ingresa su DNI o teléfono registrado y el sistema consulta en tiempo real al CRM mostrando su nombre, marcas registradas, logotipos, estado de membresía y servicios contratados en curso, con acceso directo para iniciar chat con un asesor.
  - **Pestaña Cotizar Servicio:** Formulario dinámico que obtiene el catálogo de servicios activo directamente desde la base de datos de Roma CRM y genera la cotización instantáneamente abriendo la conversación con el equipo comercial.

== INSTALACIÓN ==

1. Sube el archivo comprimido `roma-chat-portal.zip` a través de tu panel de WordPress en **Plugins > Añadir nuevo > Subir plugin**.
2. Haz clic en **Activar**.
3. Ve al menú **Roma Portal** en la barra lateral de administración.
4. Ingresa la URL de tu CRM (ejemplo: `https://crm.tudominio.com` o en local `http://localhost/CESARMENDOZA`).
5. Guarda los cambios y ¡listo!

== SHORTCODES ==

Para insertar el portal en cualquier página o entrada:
`[roma_portal]`

Opciones adicionales:
* `[roma_portal default_tab="cotizar"]` - Abre directamente la pestaña de cotizaciones.
* `[roma_portal title="Centro de Atención Roma"]` - Personaliza el título del encabezado.
