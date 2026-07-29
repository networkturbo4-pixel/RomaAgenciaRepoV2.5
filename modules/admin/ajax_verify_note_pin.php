<?php
/**
 * ajax_verify_note_pin.php
 * Public endpoint - No authentication required
 * Verifies the access PIN for a payment note.
 * Rate limited: 5 failed attempts triggers a 15-minute lockout.
 *
 * POST JSON body: {"token": "...", "pin": "1234"}
 */

session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['token']) || !isset($input['pin'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token y PIN requeridos']);
    exit;
}

$token = $input['token'];
$pin = $input['pin'];

// --- Rate limiting via session ---
$rate_key = 'pin_attempts_' . $token;
$block_key = 'pin_blocked_until_' . $token;
$max_attempts = 5;
$block_duration = 900; // 15 minutes in seconds

// Check if currently blocked
if (isset($_SESSION[$block_key]) && time() < $_SESSION[$block_key]) {
    $remaining = $_SESSION[$block_key] - time();
    $minutes = ceil($remaining / 60);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => "Demasiados intentos. Intente de nuevo en {$minutes} minuto(s).",
        'attempts_left' => 0
    ]);
    exit;
}

// Reset block if time has passed
if (isset($_SESSION[$block_key]) && time() >= $_SESSION[$block_key]) {
    unset($_SESSION[$block_key]);
    unset($_SESSION[$rate_key]);
}

// Initialize attempt counter
if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = 0;
}

try {
    $db = (new Database())->getConnection();

    // Look up note by public_token
    $stmt = $db->prepare("SELECT id, access_pin FROM payment_notes WHERE public_token = :token LIMIT 1");
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Nota no encontrada']);
        exit;
    }

    // Verify PIN
    if ($note['access_pin'] === $pin) {
        // Reset attempts on success
        unset($_SESSION[$rate_key]);
        unset($_SESSION[$block_key]);

        echo json_encode(['success' => true]);
    } else {
        // Increment failed attempts
        $_SESSION[$rate_key]++;
        $attempts_used = $_SESSION[$rate_key];
        $attempts_left = $max_attempts - $attempts_used;

        // Block if max attempts reached
        if ($attempts_left <= 0) {
            $_SESSION[$block_key] = time() + $block_duration;
            $attempts_left = 0;
        }

        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'PIN incorrecto',
            'attempts_left' => $attempts_left
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}
