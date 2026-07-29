<?php
// ajax/delete_meet.php
require_once '../config/database.php';
require_once '../vendor/autoload.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$id = $_POST['id'] ?? 0;

if (!$id) {
    echo json_encode(['error' => 'ID no proporcionado']);
    exit();
}

$db = (new Database())->getConnection();

$stmt = $db->prepare("SELECT event_id FROM reuniones WHERE id = ?");
$stmt->execute([$id]);
$reunion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reunion) {
    echo json_encode(['error' => 'Reunión no encontrada']);
    exit();
}

// Try to delete from Google Calendar if event_id exists
if ($reunion['event_id']) {
    $stmt_set = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_refresh_token')");
    $settings = $stmt_set->fetchAll(PDO::FETCH_KEY_PAIR);

    $clientId = $settings['google_client_id'] ?? '';
    $clientSecret = $settings['google_client_secret'] ?? '';
    $refreshToken = $settings['google_refresh_token'] ?? '';

    if ($clientId && $clientSecret && $refreshToken) {
        try {
            $client = new \Google_Client();
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->refreshToken($refreshToken);

            $service = new \Google_Service_Calendar($client);
            $service->events->delete('primary', $reunion['event_id']);
        } catch (Exception $e) {
            // Ignore errors if the event was already deleted manually in Google Calendar
            error_log("Error deleting from Google Calendar: " . $e->getMessage());
        }
    }
}

// Delete from DB (Soft Delete)
$stmt_del = $db->prepare("UPDATE reuniones SET estado = 'Eliminada' WHERE id = ?");
$stmt_del->execute([$id]);

echo json_encode(['success' => true]);
