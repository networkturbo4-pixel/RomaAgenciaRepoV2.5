<?php
// includes/SecurityHelper.php
// Asistente criptográfico central para firmas digitales, tokens temporales y verificación

require_once __DIR__ . '/env.php';

class SecurityHelper {
    /**
     * Obtiene la clave secreta global de la aplicación
     */
    private static function getAppSecret() {
        $secret = getenv('APP_SECRET') ?: ($_ENV['APP_SECRET'] ?? null);
        if (empty($secret)) {
            // Clave de respaldo interna si no está en .env
            $secret = 'roma_agency_default_fallback_key_2026_x99_sec';
        }
        return $secret;
    }

    /**
     * Genera una firma HMAC-SHA256 robusta para un post público
     * @param int $postId
     * @param int $expiration Timestamp de expiración
     * @return string Firma hexadecimal de 64 caracteres
     */
    public static function signPost($postId, $expiration) {
        $payload = "roma_post:{$postId}:{$expiration}";
        return hash_hmac('sha256', $payload, self::getAppSecret());
    }

    /**
     * Verifica la validez de la firma de un post público
     * Soporta tanto el nuevo HMAC-SHA256 como el MD5 legado para retrocompatibilidad
     * @param int $postId
     * @param int $expiration
     * @param string $signature
     * @return bool
     */
    public static function verifyPostSignature($postId, $expiration, $signature) {
        if (empty($signature) || empty($postId) || empty($expiration)) {
            return false;
        }

        // 1. Verificación primaria con HMAC-SHA256
        $expectedHmac = self::signPost($postId, $expiration);
        if (hash_equals($expectedHmac, $signature)) {
            return true;
        }

        // 2. Retrocompatibilidad con enlaces antiguos generados con md5('ROMA_SECRET_' . $id . $exp)
        $legacySecret = 'ROMA_SECRET_' . $postId;
        $legacyExpected = md5($legacySecret . $expiration);
        if (hash_equals($legacyExpected, $signature)) {
            return true;
        }

        return false;
    }

    /**
     * Genera un token aleatorio seguro para usos generales (CSRF, invitaciones, etc.)
     * @param int $bytes
     * @return string
     */
    public static function generateRandomToken($bytes = 32) {
        return bin2hex(random_bytes($bytes));
    }
}
