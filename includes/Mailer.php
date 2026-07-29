<?php
// includes/Mailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Incluir el autoloader de Composer si no se ha incluido
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class Mailer {
    private $db;
    private $settings;

    public function __construct($db) {
        $this->db = $db;
        $this->loadSettings();
    }

    private function loadSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
        $this->settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private function getPHPMailerInstance() {
        $mail = new PHPMailer(true);
        
        $host = $this->settings['smtp_host'] ?? '';
        $port = $this->settings['smtp_port'] ?? 587;
        $user = $this->settings['smtp_user'] ?? '';
        $pass = $this->settings['smtp_pass'] ?? '';
        $fromEmail = $this->settings['smtp_from_email'] ?? '';
        $fromName = $this->settings['smtp_from_name'] ?? '';

        if (!empty($host)) {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $user;
            $mail->Password   = $pass;
            $mail->SMTPSecure = ($port == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $port;
            // Configurar codificación utf-8
            $mail->CharSet = 'UTF-8';
        } else {
            // Si no hay SMTP configurado, usa la función mail() de PHP por defecto.
            $mail->isMail();
            $mail->CharSet = 'UTF-8';
        }

        if (!empty($fromEmail)) {
            $mail->setFrom($fromEmail, $fromName);
        }

        return $mail;
    }

    /**
     * Reemplaza variables en el HTML
     * @param string $html
     * @param array $data clave => valor (e.g. ['nombre_cliente' => 'Juan'])
     * @return string
     */
    private function parseTemplate($html, $data) {
        foreach ($data as $key => $value) {
            $html = str_replace("{{" . $key . "}}", $value, $html);
        }
        return $html;
    }

    /**
     * Enviar correo usando una plantilla de la base de datos
     */
    public function sendTemplateEmail($toEmail, $toName, $templateId, $data = []) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM email_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                throw new Exception("Template no encontrado.");
            }

            $subject = $this->parseTemplate($template['subject'], $data);
            $bodyHtml = $this->parseTemplate($template['body_html'], $data);

            return $this->sendCustomEmail($toEmail, $toName, $subject, $bodyHtml);

        } catch (Exception $e) {
            error_log("Error enviando email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar correo personalizado
     */
    public function sendCustomEmail($toEmail, $toName, $subject, $bodyHtml, $bodyText = '') {
        try {
            $mail = $this->getPHPMailerInstance();
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            
            if (empty($bodyText)) {
                $mail->AltBody = strip_tags($bodyHtml);
            } else {
                $mail->AltBody = $bodyText;
            }

            return $mail->send();
        } catch (Exception $e) {
            error_log("Error enviando email: {$mail->ErrorInfo}");
            return false;
        }
    }
}
