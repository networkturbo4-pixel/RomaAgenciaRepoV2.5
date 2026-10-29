<?php
// includes/FcmHelper.php
// Envío de notificaciones push nativas vía Firebase Cloud Messaging (HTTP v1) sin librerías externas.
// Requiere el archivo de cuenta de servicio en config/firebase-service-account.json (fuera de Git).

class FcmHelper {
    private static function loadServiceAccount(): ?array {
        $path = __DIR__ . '/../config/firebase-service-account.json';
        if (!is_file($path)) {
            $b64 = getenv('FIREBASE_SERVICE_ACCOUNT_B64');
            if ($b64) {
                $json = json_decode(base64_decode($b64), true);
                return is_array($json) ? $json : null;
            }
            return null;
        }
        $json = json_decode((string)@file_get_contents($path), true);
        return (is_array($json) && !empty($json['private_key']) && !empty($json['client_email'])) ? $json : null;
    }

    private static function b64url(string $s): string {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    /** Token OAuth2 (cacheado ~50 min en archivo temporal). */
    private static function accessToken(array $sa): ?string {
        $cacheFile = sys_get_temp_dir() . '/roma_fcm_token_' . md5($sa['client_email']) . '.json';
        if (is_file($cacheFile)) {
            $c = json_decode((string)@file_get_contents($cacheFile), true);
            if (!empty($c['token']) && ($c['exp'] ?? 0) > time() + 120) {
                return $c['token'];
            }
        }
        $now = time();
        $header = self::b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = self::b64url(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));
        $sig = '';
        if (!openssl_sign("$header.$claims", $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            return null;
        }
        $jwt = "$header.$claims." . self::b64url($sig);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string)$res, true);
        if (empty($data['access_token'])) {
            error_log('FcmHelper: no se pudo obtener access_token: ' . substr((string)$res, 0, 200));
            return null;
        }
        @file_put_contents($cacheFile, json_encode(['token' => $data['access_token'], 'exp' => $now + (int)($data['expires_in'] ?? 3500)]));
        return $data['access_token'];
    }

    public static function ensureTable(PDO $db): void {
        static $done = false;
        if ($done) return;
        $db->exec("CREATE TABLE IF NOT EXISTS fcm_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            device VARCHAR(120) DEFAULT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_token (token),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $done = true;
    }

    public static function saveToken(PDO $db, int $userId, string $token, string $device = ''): bool {
        if ($userId <= 0 || strlen($token) < 20 || strlen($token) > 255) return false;
        self::ensureTable($db);
        $stmt = $db->prepare("INSERT INTO fcm_tokens (user_id, token, device, updated_at) VALUES (?, ?, ?, NOW())
                              ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), device = VALUES(device), updated_at = NOW()");
        return $stmt->execute([$userId, $token, mb_substr($device, 0, 120)]);
    }

    public static function removeToken(PDO $db, string $token): void {
        try {
            self::ensureTable($db);
            $db->prepare("DELETE FROM fcm_tokens WHERE token = ?")->execute([$token]);
        } catch (\Throwable $e) {}
    }

    /**
     * Envía un push (mensaje de datos, prioridad alta) a todos los dispositivos de los usuarios indicados.
     * La app Android construye la notificación con el favicon y nombre del sistema.
     */
    public static function sendToUsers(PDO $db, array $userIds, array $payload): void {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if (empty($userIds)) return;

        $sa = self::loadServiceAccount();
        if (!$sa) return;

        try {
            self::ensureTable($db);
            $in = implode(',', array_fill(0, count($userIds), '?'));
            $stmt = $db->prepare("SELECT token FROM fcm_tokens WHERE user_id IN ($in)");
            $stmt->execute($userIds);
            $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return;
        }
        if (empty($tokens)) return;

        $access = self::accessToken($sa);
        if (!$access) return;

        $projectId = $sa['project_id'] ?? '';
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        // FCM exige que todos los valores de "data" sean strings
        $data = [];
        foreach ($payload as $k => $v) {
            $data[(string)$k] = is_scalar($v) ? (string)$v : json_encode($v);
        }

        foreach ($tokens as $token) {
            $body = json_encode(['message' => [
                'token'   => $token,
                'data'    => $data,
                'android' => ['priority' => 'HIGH', 'ttl' => '3600s'],
            ]]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access, 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $body,
            ]);
            $res = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 404 || $code === 400 && strpos((string)$res, 'UNREGISTERED') !== false || strpos((string)$res, 'registration-token-not-registered') !== false) {
                self::removeToken($db, $token);
            } elseif ($code >= 400) {
                error_log('FcmHelper send error ' . $code . ': ' . substr((string)$res, 0, 200));
            }
        }
    }
}
