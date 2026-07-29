<?php
// ajax/ajax_rooms.php — AJAX endpoint for Meeting Rooms feature
require_once '../config/database.php';
require_once '../vendor/autoload.php';

session_start();
header('Content-Type: application/json');

// Determine action from GET or POST
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Public endpoint (no auth required) ─────────────────────────────────────
if ($action === 'get_room_by_slug') {
    $slug = $_GET['slug'] ?? '';
    if (!$slug) {
        echo json_encode(['error' => 'Falta el parámetro slug.']);
        exit();
    }

    try {
        $db = (new Database())->getConnection();

        // Fetch room by slug
        $stmt = $db->prepare("
            SELECT mr.*, u.name as creator_name
            FROM meeting_rooms mr
            LEFT JOIN users u ON mr.created_by = u.id
            WHERE mr.slug = ? AND mr.is_active = 1
        ");
        $stmt->execute([$slug]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            echo json_encode(['error' => 'Sala no encontrada.']);
            exit();
        }

        // Fetch recordings for this room
        $stmtRec = $db->prepare("
            SELECT mrr.*, u.name as recorder_name
            FROM meeting_room_recordings mrr
            LEFT JOIN users u ON mrr.recorded_by = u.id
            WHERE mrr.room_id = ?
            ORDER BY mrr.recorded_at DESC
        ");
        $stmtRec->execute([$room['id']]);
        $room['recordings'] = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'room' => $room]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// ── All other endpoints require authentication ─────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$db = (new Database())->getConnection();

// ═══════════════════════════════════════════════════════════════════════════
// GET actions
// ═══════════════════════════════════════════════════════════════════════════

if ($action === 'get_rooms') {
    // ── List all active rooms with recording counts ────────────────────────
    try {
        $stmt = $db->query("
            SELECT mr.*, u.name as creator_name,
                   (SELECT COUNT(*) FROM meeting_room_recordings WHERE room_id = mr.id) as recording_count
            FROM meeting_rooms mr
            LEFT JOIN users u ON mr.created_by = u.id
            WHERE mr.is_active = 1
            ORDER BY mr.created_at ASC
        ");
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'rooms' => $rooms]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'get_room') {
    // ── Get single room with its recordings ────────────────────────────────
    $id = $_GET['id'] ?? 0;
    if (!$id) {
        echo json_encode(['error' => 'Falta el parámetro id.']);
        exit();
    }

    try {
        // Fetch the room
        $stmt = $db->prepare("
            SELECT mr.*, u.name as creator_name
            FROM meeting_rooms mr
            LEFT JOIN users u ON mr.created_by = u.id
            WHERE mr.id = ?
        ");
        $stmt->execute([$id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            echo json_encode(['error' => 'Sala no encontrada.']);
            exit();
        }

        // Fetch recordings for this room
        $stmtRec = $db->prepare("
            SELECT mrr.*, u.name as recorder_name
            FROM meeting_room_recordings mrr
            LEFT JOIN users u ON mrr.recorded_by = u.id
            WHERE mrr.room_id = ?
            ORDER BY mrr.recorded_at DESC
        ");
        $stmtRec->execute([$id]);
        $room['recordings'] = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'room' => $room]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// ═══════════════════════════════════════════════════════════════════════════
// POST actions
// ═══════════════════════════════════════════════════════════════════════════

if ($action === 'create_room') {
    // ── Create new room ────────────────────────────────────────────────────
    $name = trim($_POST['name'] ?? '');
    if (!$name) {
        echo json_encode(['error' => 'El nombre de la sala es obligatorio.']);
        exit();
    }

    $description = $_POST['description'] ?? null;
    $icon        = $_POST['icon'] ?? 'video-camera';
    $color       = $_POST['color'] ?? '#4f46e5';
    $meet_link   = $_POST['meet_link'] ?? null;
    $auto_meet   = $_POST['auto_meet'] ?? '0';
    $created_by  = $_SESSION['user_id'] ?? 1;

    // Generar y validar slug
    $slug = strtolower($name);
    $slug = preg_replace('/[áàäâ]/u', 'a', $slug);
    $slug = preg_replace('/[éèëê]/u', 'e', $slug);
    $slug = preg_replace('/[íìïî]/u', 'i', $slug);
    $slug = preg_replace('/[óòöô]/u', 'o', $slug);
    $slug = preg_replace('/[úùüû]/u', 'u', $slug);
    $slug = preg_replace('/ñ/u', 'n', $slug);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    // Verificar si el slug ya existe
    $stmtSlug = $db->prepare("SELECT id FROM meeting_rooms WHERE slug = ?");
    $stmtSlug->execute([$slug]);
    if ($stmtSlug->fetch()) {
        echo json_encode(['error' => 'Ya existe una sala con este nombre (o un enlace similar). Por favor, elige otro nombre.']);
        exit();
    }

    if ($auto_meet === '1') {
        $stmt_s = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_refresh_token')");
        $settings = $stmt_s->fetchAll(PDO::FETCH_KEY_PAIR);
        $clientId = $settings['google_client_id'] ?? '';
        $clientSecret = $settings['google_client_secret'] ?? '';
        $refreshToken = $settings['google_refresh_token'] ?? '';
        
        if (!$clientId || !$clientSecret || !$refreshToken) {
            echo json_encode(['error' => 'Las credenciales de Google API no están configuradas en el sistema.']);
            exit();
        }
        
        try {
            $client = new \Google_Client();
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
            
            if (isset($token['error'])) {
                echo json_encode(['error' => 'Error de autenticación Google: ' . ($token['error_description'] ?? $token['error'])]);
                exit();
            }
            
            $client->setAccessToken($token);
            $service = new \Google_Service_Calendar($client);

            $event = new \Google_Service_Calendar_Event([
                'summary' => 'Sala: ' . $name,
                'description' => 'Enlace de sala permanente. ' . $description,
                'start' => ['dateTime' => (new DateTime())->format(\DateTime::RFC3339), 'timeZone' => date_default_timezone_get()],
                'end' => ['dateTime' => (new DateTime('+1 year'))->format(\DateTime::RFC3339), 'timeZone' => date_default_timezone_get()],
                'conferenceData' => [
                    'createRequest' => [
                        'requestId' => uniqid(),
                        'conferenceSolutionKey' => ['type' => 'hangoutsMeet']
                    ]
                ]
            ]);

            $event = $service->events->insert('primary', $event, ['conferenceDataVersion' => 1]);
            
            if ($event->getConferenceData() && $event->getConferenceData()->getEntryPoints()) {
                foreach ($event->getConferenceData()->getEntryPoints() as $ep) {
                    if ($ep->getEntryPointType() === 'video') {
                        $meet_link = $ep->getUri();
                        break;
                    }
                }
            }
            
            if (!$meet_link) {
                echo json_encode(['error' => 'No se pudo generar el enlace de Google Meet.']);
                exit();
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'Error de Google API: ' . $e->getMessage()]);
            exit();
        }
    }

    // Auto-generate slug from name
    $slug = strtolower($name);
    $slug = preg_replace('/[áàäâ]/u', 'a', $slug);
    $slug = preg_replace('/[éèëê]/u', 'e', $slug);
    $slug = preg_replace('/[íìïî]/u', 'i', $slug);
    $slug = preg_replace('/[óòöô]/u', 'o', $slug);
    $slug = preg_replace('/[úùüû]/u', 'u', $slug);
    $slug = preg_replace('/ñ/u', 'n', $slug);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    try {
        $stmt = $db->prepare("
            INSERT INTO meeting_rooms (name, slug, description, icon, color, meet_link, created_by)
            VALUES (:name, :slug, :description, :icon, :color, :meet_link, :created_by)
        ");
        $stmt->execute([
            ':name'        => $name,
            ':slug'        => $slug,
            ':description' => $description,
            ':icon'        => $icon,
            ':color'       => $color,
            ':meet_link'   => $meet_link,
            ':created_by'  => $created_by,
        ]);

        $roomId = $db->lastInsertId();

        // Return the created room
        $stmtFetch = $db->prepare("SELECT * FROM meeting_rooms WHERE id = ?");
        $stmtFetch->execute([$roomId]);
        $room = $stmtFetch->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'room' => $room]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'update_room') {
    // ── Update existing room ───────────────────────────────────────────────
    $id   = $_POST['id'] ?? 0;
    $name = trim($_POST['name'] ?? '');

    if (!$id || !$name) {
        echo json_encode(['error' => 'Se requieren id y name.']);
        exit();
    }

    $description = $_POST['description'] ?? null;
    $icon        = $_POST['icon'] ?? 'video-camera';
    $color       = $_POST['color'] ?? '#4f46e5';
    $meet_link   = $_POST['meet_link'] ?? null;
    $auto_meet   = $_POST['auto_meet'] ?? '0';

    // Generar y validar slug
    $slug = strtolower($name);
    $slug = preg_replace('/[áàäâ]/u', 'a', $slug);
    $slug = preg_replace('/[éèëê]/u', 'e', $slug);
    $slug = preg_replace('/[íìïî]/u', 'i', $slug);
    $slug = preg_replace('/[óòöô]/u', 'o', $slug);
    $slug = preg_replace('/[úùüû]/u', 'u', $slug);
    $slug = preg_replace('/ñ/u', 'n', $slug);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    // Verificar si el slug ya existe en OTRA sala
    $stmtSlug = $db->prepare("SELECT id FROM meeting_rooms WHERE slug = ? AND id != ?");
    $stmtSlug->execute([$slug, $id]);
    if ($stmtSlug->fetch()) {
        echo json_encode(['error' => 'Ya existe otra sala con este nombre. Por favor, elige otro nombre.']);
        exit();
    }

    if ($auto_meet === '1') {
        $stmt_s = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_refresh_token')");
        $settings = $stmt_s->fetchAll(PDO::FETCH_KEY_PAIR);
        $clientId = $settings['google_client_id'] ?? '';
        $clientSecret = $settings['google_client_secret'] ?? '';
        $refreshToken = $settings['google_refresh_token'] ?? '';
        
        if (!$clientId || !$clientSecret || !$refreshToken) {
            echo json_encode(['error' => 'Las credenciales de Google API no están configuradas en el sistema.']);
            exit();
        }
        
        try {
            $client = new \Google_Client();
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
            
            if (isset($token['error'])) {
                echo json_encode(['error' => 'Error de autenticación Google: ' . ($token['error_description'] ?? $token['error'])]);
                exit();
            }
            
            $client->setAccessToken($token);
            $service = new \Google_Service_Calendar($client);

            $event = new \Google_Service_Calendar_Event([
                'summary' => 'Sala: ' . $name,
                'description' => 'Enlace de sala permanente. ' . $description,
                'start' => ['dateTime' => (new DateTime())->format(\DateTime::RFC3339), 'timeZone' => date_default_timezone_get()],
                'end' => ['dateTime' => (new DateTime('+1 year'))->format(\DateTime::RFC3339), 'timeZone' => date_default_timezone_get()],
                'conferenceData' => [
                    'createRequest' => [
                        'requestId' => uniqid(),
                        'conferenceSolutionKey' => ['type' => 'hangoutsMeet']
                    ]
                ]
            ]);

            $event = $service->events->insert('primary', $event, ['conferenceDataVersion' => 1]);
            
            if ($event->getConferenceData() && $event->getConferenceData()->getEntryPoints()) {
                foreach ($event->getConferenceData()->getEntryPoints() as $ep) {
                    if ($ep->getEntryPointType() === 'video') {
                        $meet_link = $ep->getUri();
                        break;
                    }
                }
            }
            
            if (!$meet_link) {
                echo json_encode(['error' => 'No se pudo generar el enlace de Google Meet.']);
                exit();
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'Error de Google API: ' . $e->getMessage()]);
            exit();
        }
    }

    try {
        $stmt = $db->prepare("
            UPDATE meeting_rooms
            SET name = :name, slug = :slug, description = :description,
                icon = :icon, color = :color, meet_link = :meet_link
            WHERE id = :id
        ");
        $stmt->execute([
            ':name'        => $name,
            ':slug'        => $slug,
            ':description' => $description,
            ':icon'        => $icon,
            ':color'       => $color,
            ':meet_link'   => $meet_link,
            ':id'          => $id,
        ]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'delete_room') {
    // ── Soft delete room (set is_active = 0) ───────────────────────────────
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        echo json_encode(['error' => 'Falta el parámetro id.']);
        exit();
    }

    try {
        $stmt = $db->prepare("UPDATE meeting_rooms SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'add_recording') {
    // ── Add recording to a room ────────────────────────────────────────────
    $room_id        = $_POST['room_id'] ?? 0;
    $recording_link = trim($_POST['recording_link'] ?? '');

    if (!$room_id || !$recording_link) {
        echo json_encode(['error' => 'Se requieren room_id y recording_link.']);
        exit();
    }

    $title       = $_POST['title'] ?? null;
    $notes_link  = $_POST['notes_link'] ?? null;
    $duration    = $_POST['duration'] ?? null;
    $recorded_by = $_SESSION['user_id'];

    try {
        $stmt = $db->prepare("
            INSERT INTO meeting_room_recordings (room_id, title, recording_link, notes_link, duration, recorded_by)
            VALUES (:room_id, :title, :recording_link, :notes_link, :duration, :recorded_by)
        ");
        $stmt->execute([
            ':room_id'        => $room_id,
            ':title'          => $title,
            ':recording_link' => $recording_link,
            ':notes_link'     => $notes_link,
            ':duration'       => $duration,
            ':recorded_by'    => $recorded_by,
        ]);

        $recId = $db->lastInsertId();

        // Return the created recording
        $stmtFetch = $db->prepare("SELECT * FROM meeting_room_recordings WHERE id = ?");
        $stmtFetch->execute([$recId]);
        $recording = $stmtFetch->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'recording' => $recording]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'delete_recording') {
    // ── Delete recording ───────────────────────────────────────────────────
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        echo json_encode(['error' => 'Falta el parámetro id.']);
        exit();
    }

    try {
        $stmt = $db->prepare("DELETE FROM meeting_room_recordings WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'sync_recordings') {
    // ── Sync recordings from Google Drive ───────────────────────────────────
    // We simply include the cron script and pass a defined constant so it knows
    // it's being called from AJAX and outputs JSON.
    define('INCLUDED_FROM_AJAX', true);
    require_once '../cron/sync_room_recordings.php';
    exit();
}

// ── Unknown action ─────────────────────────────────────────────────────────
echo json_encode(['error' => 'Acción no válida.']);
