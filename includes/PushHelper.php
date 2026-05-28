<?php
// includes/PushHelper.php
if (!defined('VAPID_PUBLIC_KEY')) {
    define('VAPID_PUBLIC_KEY', 'BAhu9ZcA2cypGC--dbgdXicyU_K4cvZUdRhP4nQ7Y4t8M2LN156sVAWKg1swXA6KIyjBZvZkeIKqTZxxNpdNksI');
    define('VAPID_PRIVATE_KEY', 'QaRTxhVHLghTyAGwSw63Bw3sYMqPRpZi8wmvAqR0YWA');
    define('VAPID_SUBJECT', 'mailto:admin@example.com');
}

class PushHelper {
    /**
     * Envia notificaciones push a uno o múltiples usuarios.
     * 
     * @param PDO $db Conexión a base de datos
     * @param array|int $userIds ID de usuario o array de IDs
     * @param string $title Título de la notificación
     * @param string $body Cuerpo de la notificación
     * @param string $url URL a la que se redirigirá al hacer clic
     * @param string $tag Tag para agrupar notificaciones y evitar duplicados
     * @param array $extraData Datos adicionales a enviar al service worker (ej. para refrescar UI)
     */
    public static function sendPushNotification($db, $userIds, $title, $body, $url = '/', $tag = 'general', $extraData = []) {
        if (empty($userIds)) return;
        
        if (!is_array($userIds)) {
            $userIds = [$userIds];
        }
        
        $userIds = array_filter(array_unique($userIds));
        if (empty($userIds)) return;
        
        $in = str_repeat('?,', count($userIds) - 1) . '?';
        $stmt = $db->prepare("SELECT endpoint, p256dh, auth_token FROM push_subscriptions WHERE user_id IN ($in)");
        $stmt->execute($userIds);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($subscriptions)) return;
        
        $auth = [
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY
            ]
        ];
        
        try {
            $webPush = new Minishlink\WebPush\WebPush($auth);
            
            $payloadData = [
                'title' => $title, 
                'body' => mb_substr($body, 0, 150) . (mb_strlen($body) > 150 ? '...' : ''), 
                'icon' => '/assets/img/default-icon.png', 
                'url' => $url,
                'tag' => $tag
            ];
            
            // Add extra custom data to trigger frontend events (real-time reload)
            if (!empty($extraData)) {
                $payloadData['custom_data'] = $extraData;
            }
            
            $payload = json_encode($payloadData);
            
            foreach ($subscriptions as $sub) {
                $subscription = Minishlink\WebPush\Subscription::create([
                    'endpoint' => $sub['endpoint'], 
                    'publicKey' => $sub['p256dh'], 
                    'authToken' => $sub['auth_token']
                ]);
                $webPush->queueNotification($subscription, $payload);
            }
            
            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")->execute([$report->getEndpoint()]);
                }
            }
        } catch (\Exception $e) {
            error_log("Push Error: " . $e->getMessage());
        }
    }
}
