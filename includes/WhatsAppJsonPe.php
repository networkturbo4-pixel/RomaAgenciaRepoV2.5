<?php
// includes/WhatsAppJsonPe.php

class WhatsAppJsonPe {
    private $db;
    private $token;
    private $instance;
    private $baseUrl = 'https://api.whatsapp.json.pe';

    public function __construct($db) {
        $this->db = $db;
        $this->loadSettings();
    }

    private function loadSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('jsonpe_token', 'jsonpe_instance')");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $this->token = $settings['jsonpe_token'] ?? '';
        $this->instance = $settings['jsonpe_instance'] ?? '';
    }

    /**
     * Envía un mensaje de texto a un número de WhatsApp
     * @param string $phone Número de teléfono con código de país (ej. 51999999999)
     * @param string $message Mensaje de texto a enviar
     * @return array|bool Decoded JSON response or false on failure
     */
    public function sendMessage($phone, $message) {
        if (empty($this->token) || empty($this->instance)) {
            error_log("WhatsAppJsonPe Error: Token o Instancia no configurados.");
            return false;
        }

        $endpoint = $this->baseUrl . "/send/text";
        
        $payload = json_encode([
            'number' => $phone,
            'text' => $message
        ]);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("WhatsAppJsonPe CURL Error: " . $error);
            return false;
        }

        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return $decodedResponse;
        } else {
            error_log("WhatsAppJsonPe API Error HTTP {$httpCode}: " . $response);
            return false;
        }
    }
}
