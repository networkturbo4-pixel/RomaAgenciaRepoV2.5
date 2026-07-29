<?php
// ajax/update_meet.php
require_once '../config/database.php';
require_once '../vendor/autoload.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$id = $_POST['id'] ?? 0;
$motivo = $_POST['motivo'] ?? '';
$brand_id = $_POST['brand_id'] ?? 0;
$brand_name = $_POST['brand_name'] ?? '';
$fecha = $_POST['fecha'] ?? ''; // ISO 8601 YYYY-MM-DDTHH:mm
$guests_str = $_POST['guests'] ?? '';
$tags = $_POST['tags'] ?? '';

if (!$id || !$motivo || !$brand_id || !$fecha) {
    echo json_encode(['error' => 'Faltan datos obligatorios.']);
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

$dt = new DateTime($fecha);

if ($reunion['event_id']) {
    // Update in Google Calendar
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
            $event = $service->events->get('primary', $reunion['event_id']);

            $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            $mes = $meses[$dt->format('n') - 1];
            $anio = $dt->format('Y');
            
            $summary = $motivo . ' - ' . $brand_name . ' - ' . $mes . ' ' . $anio;

            $event->setSummary($summary);
            
            $start = new \Google_Service_Calendar_EventDateTime();
            $start->setDateTime($dt->format(\DateTime::RFC3339));
            $start->setTimeZone(date_default_timezone_get());
            $event->setStart($start);

            $end = new \Google_Service_Calendar_EventDateTime();
            $end->setDateTime((clone $dt)->modify('+1 hour')->format(\DateTime::RFC3339));
            $end->setTimeZone(date_default_timezone_get());
            $event->setEnd($end);

            // Update Color
            $colorId = (string) (($brand_id % 11) + 1);
            $event->setColorId($colorId);

            // Update Attendees
            $attendees = [];
            if (!empty($guests_str)) {
                $emails = explode(',', $guests_str);
                foreach ($emails as $email) {
                    $email = trim($email);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $attendees[] = ['email' => $email];
                    }
                }
            }
            $event->setAttendees($attendees);

            $service->events->update('primary', $reunion['event_id'], $event);

        } catch (Exception $e) {
            echo json_encode(['error' => 'Error actualizando Google Calendar: ' . $e->getMessage()]);
            exit();
        }
    }
}

// Update DB
$stmt_update = $db->prepare("UPDATE reuniones SET motivo = ?, brand_id = ?, fecha_hora = ?, guests = ?, tags = ? WHERE id = ?");
$stmt_update->execute([$motivo, $brand_id, $dt->format('Y-m-d H:i:s'), $guests_str, $tags, $id]);

echo json_encode(['success' => true]);
