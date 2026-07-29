<?php
// ajax/schedule_meet.php
require_once '../config/database.php';
require_once '../vendor/autoload.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$motivo = $_POST['motivo'] ?? '';
$brand_id = $_POST['brand_id'] ?? 0;
$brand_name = $_POST['brand_name'] ?? '';
$fecha = $_POST['fecha'] ?? ''; // ISO 8601 YYYY-MM-DDTHH:mm
$guests_str = $_POST['guests'] ?? '';
$tags = $_POST['tags'] ?? '';
$created_by = $_SESSION['user_id'];

if (!$motivo || !$brand_id || !$fecha) {
    echo json_encode(['error' => 'Faltan datos obligatorios.']);
    exit();
}

$db = (new Database())->getConnection();

// Get settings
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_refresh_token')");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$clientId = $settings['google_client_id'] ?? '';
$clientSecret = $settings['google_client_secret'] ?? '';
$refreshToken = $settings['google_refresh_token'] ?? '';

if (!$clientId || !$clientSecret || !$refreshToken) {
    echo json_encode(['error' => 'La API de Google no está configurada o conectada.']);
    exit();
}

try {
    $client = new \Google_Client();
    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->refreshToken($refreshToken);

    $service = new \Google_Service_Calendar($client);

    // Parse date and format title: (Motivo) + (Marca) + (mes + año)
    $dt = new DateTime($fecha);
    $mesAnio = strftime('%B %Y', $dt->getTimestamp()); // Will output something like "June 2026"
    // Better manual translation for spanish if strftime locale is not set
    $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $mes = $meses[$dt->format('n') - 1];
    $anio = $dt->format('Y');
    
    $summary = $motivo . ' - ' . $brand_name . ' - ' . $mes . ' ' . $anio;

    // Process attendees
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

    // Assign colorId based on brand_id (1-11)
    $colorId = (string) (($brand_id % 11) + 1);

    $event = new \Google_Service_Calendar_Event([
        'summary' => $summary,
        'description' => 'Reunión programada desde el sistema Roma.',
        'colorId' => $colorId,
        'start' => [
            'dateTime' => $dt->format(\DateTime::RFC3339),
            'timeZone' => date_default_timezone_get(),
        ],
        'end' => [
            // +1 hour default
            'dateTime' => (clone $dt)->modify('+1 hour')->format(\DateTime::RFC3339),
            'timeZone' => date_default_timezone_get(),
        ],
        'attendees' => $attendees,
        'conferenceData' => [
            'createRequest' => [
                'requestId' => uniqid(),
                'conferenceSolutionKey' => [
                    'type' => 'hangoutsMeet'
                ]
            ]
        ]
    ]);

    $calendarId = 'primary';
    $event = $service->events->insert($calendarId, $event, ['conferenceDataVersion' => 1]);

    $meetLink = '';
    if ($event->getConferenceData() && $event->getConferenceData()->getEntryPoints()) {
        foreach ($event->getConferenceData()->getEntryPoints() as $ep) {
            if ($ep->getEntryPointType() === 'video') {
                $meetLink = $ep->getUri();
                break;
            }
        }
    }

    if (!$meetLink) {
        $meetLink = $event->getHtmlLink(); // Fallback to calendar event link
    }

    // Save to DB
    $stmt_insert = $db->prepare("INSERT INTO reuniones (brand_id, motivo, fecha_hora, meet_link, event_id, estado, created_by, guests, tags) VALUES (?, ?, ?, ?, ?, 'Programada', ?, ?, ?)");
    $stmt_insert->execute([$brand_id, $motivo, $dt->format('Y-m-d H:i:s'), $meetLink, $event->getId(), $created_by, $guests_str, $tags]);

    echo json_encode([
        'success' => true,
        'meet_link' => $meetLink,
        'title' => $summary
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
