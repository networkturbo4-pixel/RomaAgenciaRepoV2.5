<?php
// includes/GoogleAuthenticator.php

class GoogleAuthenticator {
    private static $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Genera una clave secreta Base32 aleatoria de 16 caracteres.
     */
    public static function generateSecret($length = 16) {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Decodifica una cadena Base32 a binario.
     */
    public static function base32Decode($b32) {
        $b32 = strtoupper(trim($b32));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';
        for ($i = 0; $i < strlen($b32); $i++) {
            $val = strpos(self::$base32chars, $b32[$i]);
            if ($val === false) continue;
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }

    /**
     * Genera el código OTP de 6 dígitos para un timeSlice dado (por defecto 30 segundos actuales).
     */
    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }
        $secretKey = self::base32Decode($secret);
        // Pack time en formato big-endian 64-bit
        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashpart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashpart)[1] & 0x7FFFFFFF;
        return str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica un código de 6 dígitos con ventana de discrepancia de +/- 1 paso (30s).
     */
    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }
        $currentTimeSlice = floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals((string)$calculatedCode, (string)$code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Genera la URI otpauth estándar para Google Authenticator.
     */
    public static function getOtpAuthUrl($accountName, $secret, $issuer = 'ROMA SaaS') {
        return "otpauth://totp/" . rawurlencode($issuer . ":" . $accountName) . "?secret=" . rawurlencode($secret) . "&issuer=" . rawurlencode($issuer);
    }

    /**
     * Genera URL de imagen de código QR.
     */
    public static function getQrImageUrl($accountName, $secret, $issuer = 'ROMA SaaS') {
        $otpUrl = self::getOtpAuthUrl($accountName, $secret, $issuer);
        return "https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=4&data=" . urlencode($otpUrl);
    }
}
