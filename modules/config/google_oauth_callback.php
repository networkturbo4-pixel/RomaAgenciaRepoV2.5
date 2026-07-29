<?php
// modules/config/google_oauth_callback.php
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    die("No autorizado");
}

$db = (new Database())->getConnection();

// Check Admin
$stmt = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() != 1) {
    die("Solo administradores pueden configurar esto.");
}

// Fetch Client ID and Secret
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_client_id', 'google_client_secret')");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$clientId = $settings['google_client_id'] ?? '';
$clientSecret = $settings['google_client_secret'] ?? '';

if (!$clientId || !$clientSecret) {
    die("Faltan credenciales de Google Workspace en la configuración.");
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$redirectUri = $protocol . "://" . $host . $basePath . "/google_oauth_callback.php";

$client = new \Google_Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);

// Scopes required for Calendar and Gmail
$client->addScope(\Google_Service_Calendar::CALENDAR);
$client->addScope(\Google_Service_Gmail::GMAIL_READONLY);

$client->setAccessType('offline');
$client->setPrompt('consent'); // Force to return refresh token

// Si venimos a hacer login
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit();
}

// Si recibimos el código de Google
if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (isset($token['error'])) {
            die("Error obteniendo token: " . $token['error_description']);
        }

        $client->setAccessToken($token);

        // Extract Refresh Token
        $refreshToken = $client->getRefreshToken();
        
        if ($refreshToken) {
            // Save refresh token to database
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'google_refresh_token'");
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) {
                $stmt_update = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'google_refresh_token'");
                $stmt_update->execute([$refreshToken]);
            } else {
                $stmt_insert = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('google_refresh_token', ?)");
                $stmt_insert->execute([$refreshToken]);
            }
            
            // Redirect back to settings page with success
            header('Location: ../../index.php?module=config');
            exit();
        } else {
            die("Error: Google no devolvió un Refresh Token. Por favor, revoca los permisos en tu cuenta de Google (Seguridad > Conexiones de terceros) y vuelve a intentarlo.");
        }
    } catch (Exception $e) {
        die("Exception: " . $e->getMessage());
    }
}

echo "Acción inválida.";
