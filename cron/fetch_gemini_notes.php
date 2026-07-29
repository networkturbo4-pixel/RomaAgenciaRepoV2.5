<?php
// cron/fetch_gemini_notes.php
/**
 * Run this script via cron every 15-30 minutes:
 * php /path/to/CESARMENDOZA/cron/fetch_gemini_notes.php
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$is_web = (php_sapi_name() !== 'cli');
if ($is_web) {
    session_start();
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit();
    }
}

$db = (new Database())->getConnection();
$log_output = [];

// Get settings
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_refresh_token', 'gemini_subject_keywords', 'gemini_search_days')");
$settings_raw = $stmt->fetchAll();
$settings = [];
foreach ($settings_raw as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$clientId = $settings['google_client_id'] ?? '';
$clientSecret = $settings['google_client_secret'] ?? '';
$refreshToken = $settings['google_refresh_token'] ?? '';

if (!$clientId || !$clientSecret || !$refreshToken) {
    if ($is_web) echo json_encode(['success' => false, 'error' => 'Falta configuración de Google Workspace.']);
    else echo "Falta configuración de Google Workspace.\n";
    exit();
}

try {
    $client = new \Google_Client();
    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->refreshToken($refreshToken);

    $service = new \Google_Service_Gmail($client);

    // Search for unread emails from Gemini with meeting notes
    // Search for emails from Gemini or Google Meet with notes/recordings in the last 2 days
    // We don't rely only on is:unread because the user might open it first.
    
    $keywords_setting = $settings['gemini_subject_keywords'] ?? 'Notas, Grabación, Resumen, Notes, Recording, Reunión, Presentación';
    $keywords = array_filter(array_map('trim', explode(',', $keywords_setting)));
    $subject_parts = [];
    foreach ($keywords as $kw) {
        if (!empty($kw)) {
            $subject_parts[] = 'subject:"' . $kw . '"';
        }
    }
    $search_query = empty($subject_parts) ? '' : '(' . implode(' OR ', $subject_parts) . ')';
    
    $search_days = $settings['gemini_search_days'] ?? '2';
    
    $query = $search_query . ' newer_than:' . $search_days . 'd'; 

    $messagesResponse = $service->users_messages->listUsersMessages('me', ['q' => $query, 'maxResults' => 10]);
    $messages = $messagesResponse->getMessages();

    if (empty($messages)) {
        if ($is_web) echo json_encode(['success' => true, 'log' => ['No hay correos nuevos de notas.']]);
        else echo "No hay correos nuevos de notas.\n";
        exit();
    }

    foreach ($messages as $message) {
        $msgId = $message->getId();
        $msg = $service->users_messages->get('me', $msgId, ['format' => 'full']);
        $payload = $msg->getPayload();
        
        // Extract Subject
        $subject = '';
        foreach ($payload->getHeaders() as $header) {
            if ($header->getName() === 'Subject') {
                $subject = $header->getValue();
                break;
            }
        }

        // Subject format: Notas de "Reunión Motivo - Marca - Mes Año" 
        // We will extract whatever is inside the quotes (both standard and smart quotes) if available
        $meetingTitle = '';
        if (preg_match('/[“"”\'](.+?)[“"”\']/u', $subject, $matches)) {
            $meetingTitle = $matches[1];
        } else {
            // Fallback: remove common prefixes using regex so we only match at the beginning
            $meetingTitle = preg_replace('/^(Notas de la reunión:|Notas de|Grabación de la reunión:|Grabación de|Resumen de|Meeting Recording:|Meeting notes:)\s*/i', '', $subject);
            $meetingTitle = trim($meetingTitle);
        }

        // Find the meeting in DB. We try to match the exact summary or just the motif
        // In our DB we have `motivo` and `brand_id`. We don't save the exact generated title, 
        // but we can match by looking if the email title contains the `motivo`.
        // To be safe, we order by ID DESC to get the most recent matching meeting.
        $stmt_find = $db->prepare("
            SELECT r.id, r.motivo, r.estado, r.resumen, r.proximos_pasos, r.notes_link, r.recording_link, b.name as brand_name 
            FROM reuniones r
            LEFT JOIN client_brands b ON r.brand_id = b.id
            WHERE (? LIKE CONCAT('%', r.motivo, '%') OR r.motivo LIKE CONCAT('%', ?, '%')) AND r.estado != 'Eliminada'
            ORDER BY r.id DESC LIMIT 1
        ");
        $stmt_find->execute([$meetingTitle, $meetingTitle]);
        $reunion = $stmt_find->fetch(PDO::FETCH_ASSOC);

        if (!$reunion) {
            $log_output[] = "No se encontró reunión en DB para el correo: $subject";
            continue;
        }

        // If already completed and has BOTH notes and recording, skip to avoid unnecessary processing
        if ($reunion['estado'] === 'Completada' && !empty($reunion['recording_link']) && !empty($reunion['notes_link']) && !empty($reunion['resumen'])) {
            $log_output[] = "La reunión ID {$reunion['id']} ya estaba completamente procesada.";
            continue;
        }

        // Extract body
        $body = '';
        $parts = $payload->getParts();
        if (empty($parts)) {
            $body = base64_decode(strtr($payload->getBody()->getData(), '-_', '+/'));
        } else {
            foreach ($parts as $part) {
                if ($part->getMimeType() === 'text/html') {
                    $body = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                    break;
                }
            }
            if (!$body && isset($parts[0])) { // fallback to plain text
                $body = base64_decode(strtr($parts[0]->getBody()->getData(), '-_', '+/'));
            }
        }

        // Parse HTML/Text for data
        $resumen = '';
        $proximosPasos = '';
        $notesLink = '';
        $recordingLink = '';

        // 1. Links extraction (heuristic based on common Google emails)
        // Usually buttons have links like https://docs.google.com/document/d/... for notes
        // and https://drive.google.com/file/d/... for recordings.
        if (preg_match('/href="([^"]+docs\.google\.com[^"]+)"[^>]*>.*?Abrir notas/si', $body, $m) ||
            preg_match('/href="([^"]+docs\.google\.com[^"]+)"/si', $body, $m)) {
            $notesLink = $m[1];
        }

        if (preg_match('/href="([^"]+drive\.google\.com[^"]+)"[^>]*>.*?Grabación/si', $body, $m) ||
            preg_match('/href="([^"]+drive\.google\.com\/file\/d\/[^"]+)"/si', $body, $m)) {
            $recordingLink = $m[1];
        }

        // 2. Text extraction (very rough heuristic for HTML emails)
        // Strip tags but keep some structure
        $plainText = strip_tags(str_replace(['<br>', '<br/>', '</p>', '</div>'], "\n", $body));
        $lines = explode("\n", $plainText);
        
        $inResumen = false;
        $inPasos = false;
        $resumenLines = [];
        $pasosLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match('/^Resumen/i', $line)) {
                $inResumen = true;
                $inPasos = false;
                continue;
            }
            if (preg_match('/^Próximos pasos/i', $line)) {
                $inResumen = false;
                $inPasos = true;
                continue;
            }
            // Stop parsing if we hit footer or other common bottom sections
            if (preg_match('/^Abrir notas de la reunión/i', $line) || preg_match('/^Grabación/i', $line)) {
                $inResumen = false;
                $inPasos = false;
            }

            if ($inResumen) {
                $resumenLines[] = $line;
            } elseif ($inPasos) {
                $pasosLines[] = $line;
            }
        }

        $resumen = implode("\n", $resumenLines);
        $proximosPasos = implode("\n", $pasosLines);

        // Merge with existing data so we don't overwrite if one email arrives before the other
        $finalResumen = !empty($resumen) ? $resumen : $reunion['resumen'];
        $finalPasos = !empty($proximosPasos) ? $proximosPasos : $reunion['proximos_pasos'];
        $finalNotesLink = !empty($notesLink) ? $notesLink : $reunion['notes_link'];
        $finalRecordingLink = !empty($recordingLink) ? $recordingLink : $reunion['recording_link'];

        // Update DB
        $stmt_update = $db->prepare("
            UPDATE reuniones 
            SET resumen = ?, proximos_pasos = ?, notes_link = ?, recording_link = ?, estado = 'Completada'
            WHERE id = ?
        ");
        $stmt_update->execute([$finalResumen, $finalPasos, $finalNotesLink, $finalRecordingLink, $reunion['id']]);

        // We cannot mark as read because we only requested GMAIL_READONLY scope.
        // That's fine, we already check if the meeting is 'Completada' to avoid duplicate processing.
        
        $log_output[] = "Procesada reunión ID {$reunion['id']} (Motivo: {$reunion['motivo']})";
    }

    if ($is_web) {
        echo json_encode(['success' => true, 'log' => $log_output]);
    } else {
        foreach($log_output as $l) echo $l . "\n";
    }

} catch (Exception $e) {
    if ($is_web) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
