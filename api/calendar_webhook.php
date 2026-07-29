<?php
// api/calendar_webhook.php
// Endpoint para recibir notificaciones PUSH de Google Calendar
require_once '../config/database.php';

// Google envía notificaciones con la cabecera X-Goog-Resource-State
$resourceState = $_SERVER['HTTP_X_GOOG_RESOURCE_STATE'] ?? '';
$resourceId = $_SERVER['HTTP_X_GOOG_RESOURCE_ID'] ?? '';
$channelId = $_SERVER['HTTP_X_GOOG_CHANNEL_ID'] ?? '';

if ($resourceState === 'sync') {
    // Sincronización inicial exitosa
    http_response_code(200);
    exit();
}

if ($resourceState === 'exists') {
    // Hubo una actualización en el calendario (creación, edición, eliminación)
    // Aquí el sistema debería:
    // 1. Usar $db para buscar qué usuario o marca está asociado a este $channelId
    // 2. Conectarse a Google Calendar API (con el token del sistema)
    // 3. Hacer un listado de eventos con "syncToken" para obtener solo los que cambiaron
    // 4. Actualizar la tabla `reuniones` del sistema local
    
    // NOTA: Como estamos en un entorno localhost o inicial, este webhook registrará
    // la actividad en un log para validar que Google lo está llamando.
    $log = date('Y-m-d H:i:s') . " - Actualización en Google Calendar. Channel: $channelId, Resource: $resourceId\n";
    @file_put_contents(__DIR__ . '/../scratch/webhook.log', $log, FILE_APPEND);
    
    http_response_code(200);
    exit();
}

http_response_code(200); // Always return 200 to acknowledge receipt
?>
