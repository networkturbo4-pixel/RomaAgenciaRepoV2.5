<?php
// includes/NotificationHelper.php

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PushHelper.php';

class NotificationHelper {
    // Configuración por defecto de Pusher
    const PUSHER_KEY = 'b31f38612d61b0285c78';
    const PUSHER_SECRET = 'c0cabd7a57efdc79f42e';
    const PUSHER_APP_ID = '2156473';
    const PUSHER_CLUSTER = 'us2';

    // Mapeo de iconos por tipo de notificación (Phosphor Icons)
    private static $defaultIcons = [
        'task'      => 'ph-check-square',
        'comment'   => 'ph-chat-circle-dots',
        'meeting'   => 'ph-video-camera',
        'mention'   => 'ph-at',
        'calendar'  => 'ph-calendar-blank',
        'quote'     => 'ph-file-text',
        'client'    => 'ph-user-check',
        'success'   => 'ph-check-circle',
        'warning'   => 'ph-warning',
        'error'     => 'ph-warning-circle',
        'general'   => 'ph-bell'
    ];

    /**
     * Enviar una notificación a uno o varios usuarios.
     * 
     * @param array $data {
     *   user_id: int|array (Obligatorio) ID o IDs de los destinatarios
     *   title: string (Obligatorio) Título de la notificación
     *   message: string (Opcional) Descripción o detalle
     *   link: string (Opcional) URL de destino (default '#')
     *   type: string (Opcional) 'task', 'comment', 'meeting', 'mention', etc.
     *   icon: string (Opcional) Nombre del icono Phosphor
     *   channels: array (Opcional) ['db', 'pusher', 'push'] (default: los 3)
     *   extra: array (Opcional) Datos extra para Web Push o Pusher
     * }
     * @param PDO|null $db Conexión opcional a la BD
     * @return array IDs de notificaciones creadas en BD
     */
    public static function send(array $data, ?PDO $db = null): array {
        if (empty($data['user_id']) || empty($data['title'])) {
            return [];
        }

        if (!$db) {
            global $db;
            if (!$db) {
                $database = new Database();
                $db = $database->getConnection();
            }
        }

        $userIds = is_array($data['user_id']) ? $data['user_id'] : [$data['user_id']];
        $userIds = array_values(array_filter(array_unique(array_map('intval', $userIds))));
        if (empty($userIds)) {
            return [];
        }

        $title    = trim($data['title']);
        $message  = trim($data['message'] ?? '');
        $link     = trim($data['link'] ?? '#');
        $type     = trim($data['type'] ?? 'general');
        $icon     = trim($data['icon'] ?? (self::$defaultIcons[$type] ?? 'ph-bell'));
        $channels = $data['channels'] ?? ['db', 'pusher', 'push'];
        $extra    = $data['extra'] ?? [];

        $createdIds = [];
        $createdAt = date('Y-m-d H:i:s');
        $timeAgo = 'hace un momento';

        // 1. Guardar en Base de Datos (In-App)
        if (in_array('db', $channels)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, link, type, icon, is_read, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?)
                ");
                foreach ($userIds as $uid) {
                    $stmt->execute([$uid, $title, $message, $link, $type, $icon, $createdAt]);
                    $createdIds[$uid] = (int)$db->lastInsertId();
                }
            } catch (\Throwable $e) {
                error_log("NotificationHelper DB Error: " . $e->getMessage());
            }
        }

        // 2. Transmitir en tiempo real por Pusher WebSockets
        if (in_array('pusher', $channels)) {
            self::broadcastPusher($userIds, [
                'title'      => $title,
                'message'    => $message,
                'link'       => $link,
                'type'       => $type,
                'icon'       => $icon,
                'created_at' => $createdAt,
                'time_ago'   => $timeAgo,
                'extra'      => $extra
            ], $createdIds);
        }

        // 3. Notificación Push del Navegador (Web Push / Service Worker)
        if (in_array('push', $channels)) {
            try {
                PushHelper::sendPushNotification(
                    $db,
                    $userIds,
                    $title,
                    $message,
                    $link,
                    $type,
                    array_merge(['module' => $type], $extra)
                );
            } catch (\Throwable $e) {
                error_log("NotificationHelper Push Error: " . $e->getMessage());
            }
        }

        return $createdIds;
    }

    /**
     * Enviar evento en vivo a los canales privados de los usuarios via Pusher.
     */
    private static function broadcastPusher(array $userIds, array $payload, array $createdIds): void {
        if (!class_exists('Pusher\Pusher')) {
            return;
        }

        try {
            $options = [
                'cluster' => self::PUSHER_CLUSTER,
                'useTLS'  => true
            ];
            $pusher = new Pusher\Pusher(
                self::PUSHER_KEY,
                self::PUSHER_SECRET,
                self::PUSHER_APP_ID,
                $options
            );

            foreach ($userIds as $uid) {
                $userPayload = $payload;
                if (isset($createdIds[$uid])) {
                    $userPayload['id'] = $createdIds[$uid];
                }
                // Canal privado por usuario
                $pusher->trigger('private-user-' . $uid, 'new-notification', $userPayload);
            }
        } catch (\Throwable $e) {
            error_log("NotificationHelper Pusher Error: " . $e->getMessage());
        }
    }

    /**
     * Formateador de tiempo relativo en español (ej. "hace 5 min", "hace 1 hora").
     */
    public static function formatTimeAgo(string $datetime): string {
        $timestamp = strtotime($datetime);
        if (!$timestamp) return $datetime;

        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'hace un momento';
        } elseif ($diff < 3600) {
            $mins = max(1, floor($diff / 60));
            return "hace {$mins} " . ($mins == 1 ? 'min' : 'mins');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "hace {$hours} " . ($hours == 1 ? 'hora' : 'horas');
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return "hace {$days} " . ($days == 1 ? 'día' : 'días');
        } else {
            return date('d/m/Y', $timestamp);
        }
    }
}
