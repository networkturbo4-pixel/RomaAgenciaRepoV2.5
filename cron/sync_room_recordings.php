<?php
// cron/sync_room_recordings.php
/**
 * Run this script via cron every 15-30 minutes:
 * php /path/to/CESARMENDOZA/cron/sync_room_recordings.php
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/GoogleDriveHelper.php';

$is_web = (php_sapi_name() !== 'cli');
if ($is_web && !defined('INCLUDED_FROM_AJAX')) {
    session_start();
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit();
    }
}

$db = (new Database())->getConnection();
$log_output = [];

$drive = new GoogleDriveHelper();
if (!$drive->isConfigured()) {
    if ($is_web) echo json_encode(['success' => false, 'error' => 'Google Drive no está configurado.']);
    else echo "Google Drive no está configurado.\n";
    exit();
}

try {
    // 1. Fetch active meeting rooms that have a meet_link
    $stmt = $db->query("SELECT id, name, meet_link FROM meeting_rooms WHERE is_active = 1 AND meet_link IS NOT NULL AND meet_link != ''");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rooms)) {
        if ($is_web) echo json_encode(['success' => true, 'log' => ['No hay salas activas con enlace de Meet.']]);
        else echo "No hay salas activas con enlace de Meet.\n";
        exit();
    }

    // Prepare a map of Meet Codes to Room IDs
    $codeToRoom = [];
    foreach ($rooms as $room) {
        // Extract meet code (e.g. abc-defg-hij)
        if (preg_match('/meet\.google\.com\/([a-z0-9\-]+)/i', $room['meet_link'], $matches)) {
            $code = $matches[1];
            $codeToRoom[$code] = $room;
        }
    }

    if (empty($codeToRoom)) {
        $msg = 'No se pudieron extraer códigos de Meet de las salas.';
        if ($is_web) echo json_encode(['success' => true, 'log' => [$msg]]);
        else echo $msg . "\n";
        exit();
    }

    // 2. Query Google Drive for recent video files
    // Searching for files modified in the last 7 days and containing 'video/' mimeType
    $sevenDaysAgo = (new DateTime('-7 days'))->format(\DateTime::RFC3339);
    $query = "mimeType contains 'video/' and trashed=false and modifiedTime > '$sevenDaysAgo'";
    $files = $drive->searchFiles($query);

    if ($files === false) {
        throw new Exception("Error al consultar archivos en Google Drive.");
    }

    if (empty($files)) {
        $msg = 'No se encontraron grabaciones recientes en Google Drive.';
        if ($is_web) echo json_encode(['success' => true, 'log' => [$msg]]);
        else echo $msg . "\n";
        exit();
    }

    // 3. Process files and link to rooms
    $processedCount = 0;
    foreach ($files as $file) {
        $fileName = $file['name'];
        $fileId = $file['id'];
        $webViewLink = $file['webViewLink'];

        // Check if the filename contains any of the known meet codes
        foreach ($codeToRoom as $code => $room) {
            // Google Meet recordings typically include the meet code in the filename if ad-hoc,
            // or the Event Title if scheduled via Calendar. Our event titles are "Sala: [Room Name]".
            $roomNameMatch = "Sala: " . $room['name'];
            if (stripos($fileName, $code) !== false || stripos($fileName, $roomNameMatch) !== false || stripos($fileName, $room['name']) !== false) {
                // Found a match! Check if it's already in the DB
                $stmt_check = $db->prepare("SELECT id FROM meeting_room_recordings WHERE recording_link LIKE ?");
                $stmt_check->execute(["%$fileId%"]); // check if link contains file ID
                
                if ($stmt_check->fetch()) {
                    // Already added
                    break;
                }

                // Make the file publicly viewable
                $drive->makePublicViewer($fileId);

                // Insert into DB
                $stmt_insert = $db->prepare("
                    INSERT INTO meeting_room_recordings (room_id, title, recording_link, duration, recorded_by)
                    VALUES (:room_id, :title, :recording_link, :duration, :recorded_by)
                ");
                
                // Try to extract a clean title from the filename (e.g. remove "Meet - " and the code)
                $cleanTitle = preg_replace('/Meet\s*-\s*[a-z0-9\-]+\s*/i', '', $fileName);
                $cleanTitle = trim(str_replace('.mp4', '', $cleanTitle));
                if (empty($cleanTitle)) $cleanTitle = 'Grabación ' . date('d/m/Y');

                $stmt_insert->execute([
                    ':room_id' => $room['id'],
                    ':title' => $cleanTitle,
                    ':recording_link' => $webViewLink,
                    ':duration' => null,
                    ':recorded_by' => null // System generated
                ]);

                $log_output[] = "Grabación '$fileName' añadida a la sala '{$room['name']}'";
                $processedCount++;
                break; // Stop checking codes for this file
            }
        }
    }

    if ($processedCount === 0) {
        $log_output[] = "No se encontraron nuevas grabaciones para sincronizar.";
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
?>
