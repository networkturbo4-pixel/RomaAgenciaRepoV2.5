<?php
// modules/config/drive_oauth_callback.php
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    die("No autorizado");
}

$db = (new Database())->getConnection();

// Fetch Client ID and Secret
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('drive_client_id', 'drive_client_secret')");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$clientId = $settings['drive_client_id'] ?? '';
$clientSecret = $settings['drive_client_secret'] ?? '';

if (!$clientId || !$clientSecret) {
    die("Faltan credenciales de OAuth en la configuración.");
}

// Redirect URI needs to exactly match what's configured in Google Cloud Console
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$redirectUri = $protocol . "://" . $host . $basePath . "/drive_oauth_callback.php";

$client = new \Google_Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope(\Google_Service_Drive::DRIVE);
$client->setAccessType('offline');
$client->setPrompt('consent'); // Force to return refresh token

// Si venimos a hacer login
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    $authUrl = $client->createAuthUrl();
    
    // Check if we want to force display the URL first
    if (!isset($_GET['confirm'])) {
        echo "<div style='font-family: sans-serif; padding: 2rem; max-width: 600px; margin: 2rem auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;'>";
        echo "<h2 style='color: #ef4444;'>¡Un último paso de verificación!</h2>";
        echo "<p>Como sigue dando error a pesar de que la URL está bien, el problema más común es que el <strong>Client ID</strong> que guardaste en el sistema no es el mismo que estás editando en Google Cloud.</p>";
        
        echo "<p><strong>1. Verifica que tu Client ID sea este:</strong></p>";
        echo "<div style='background: #1e293b; color: #fbbf24; padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; word-break: break-all; font-family: monospace;'>";
        echo htmlspecialchars($clientId);
        echo "</div>";

        echo "<p><strong>2. Y verifica que la URL en ese Client ID sea esta:</strong></p>";
        echo "<div style='background: #1e293b; color: #10b981; padding: 0.75rem; border-radius: 4px; margin-bottom: 1.5rem; word-break: break-all; font-family: monospace;'>";
        echo htmlspecialchars($redirectUri);
        echo "</div>";
        
        echo "<p>Si ambos datos coinciden EXACTAMENTE en Google Cloud, haz clic en continuar:</p>";
        echo "<a href='drive_oauth_callback.php?action=login&confirm=1' style='display: inline-block; background: #3b82f6; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 4px; font-weight: bold;'>Continuar a Google</a>";
        echo "</div>";
        exit();
    }
    
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
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'drive_refresh_token'");
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) {
                $stmt_update = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'drive_refresh_token'");
                $stmt_update->execute([$refreshToken]);
            } else {
                $stmt_insert = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('drive_refresh_token', ?)");
                $stmt_insert->execute([$refreshToken]);
            }
            
            // Redirect back to settings page with success
            header('Location: ../../index.php?module=config');
            exit();
        } else {
            die("Error: Google no devolvió un Refresh Token. Revoca los permisos en tu cuenta de Google y vuelve a intentarlo.");
        }
    } catch (Exception $e) {
        die("Exception: " . $e->getMessage());
    }
}

echo "Acción inválida.";
