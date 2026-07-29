<?php
// ajax/gemini_chat.php
require_once '../config/database.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$query = $_POST['query'] ?? '';

if (empty($query)) {
    echo json_encode(['error' => 'Consulta vacía']);
    exit();
}

$db = (new Database())->getConnection();

// Get Gemini Key
$stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
$key = $stmt->fetchColumn();

if (!$key) {
    echo json_encode(['error' => 'La API Key de Gemini no está configurada en Configuración > IA.']);
    exit();
}

// --- Fetch Context for Gemini ---
$user_id = $_SESSION['user_id'];
$context = "Eres Roma AI, el asistente inteligente exclusivo del sistema CRM Roma Agencia. Responde de forma concisa, amigable y profesional.\n\n";
$context .= "--- CONTEXTO DEL SISTEMA ACTUAL ---\n";

// 1. Upcoming meetings
$stmt_meet = $db->prepare("SELECT r.motivo, r.fecha_hora, b.name as brand_name FROM reuniones r LEFT JOIN client_brands b ON r.brand_id = b.id WHERE r.estado = 'Programada' AND r.fecha_hora > NOW() ORDER BY r.fecha_hora ASC LIMIT 5");
$stmt_meet->execute();
$meetings = $stmt_meet->fetchAll(PDO::FETCH_ASSOC);
$context .= "Próximas reuniones:\n";
if ($meetings) {
    foreach($meetings as $m) {
        $context .= "- {$m['motivo']} ({$m['brand_name']}) el {$m['fecha_hora']}\n";
    }
} else {
    $context .= "No hay reuniones próximas.\n";
}

// 2. Pending Tasks for current user
$stmt_tasks = $db->prepare("SELECT title, due_date, status FROM tasks WHERE assigned_to = ? AND status != 'done' ORDER BY due_date ASC LIMIT 5");
$stmt_tasks->execute([$user_id]);
$tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);
$context .= "\nTareas pendientes del usuario actual:\n";
if ($tasks) {
    foreach($tasks as $t) {
        $context .= "- {$t['title']} (Estado: {$t['status']}, Vence: {$t['due_date']})\n";
    }
} else {
    $context .= "El usuario no tiene tareas pendientes.\n";
}

// 3. Active Projects (Brands)
$stmt_brands = $db->query("SELECT name FROM client_brands LIMIT 10");
$brands = $stmt_brands->fetchAll(PDO::FETCH_COLUMN);
$context .= "\nMarcas/Clientes activos (muestra): " . implode(", ", $brands) . "\n";

$context .= "-----------------------------------\n\n";
$context .= "Consulta del usuario: " . $query;

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => $context]
            ]
        ]
    ]
];

$ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $key);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix for XAMPP localhost SSL issues
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(['error' => 'Error de cURL al comunicarse con Gemini.', 'details' => $curl_error]);
    exit();
}

$data = json_decode($response, true);
if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
    // Format response (markdown to HTML if needed, or let frontend handle it)
    $reply = $data['candidates'][0]['content']['parts'][0]['text'];
    echo json_encode(['success' => true, 'response' => $reply]);
} else if (isset($data['error'])) {
    echo json_encode(['error' => 'Error de la API de Gemini: ' . ($data['error']['message'] ?? 'Desconocido'), 'details' => $data]);
} else {
    echo json_encode(['error' => 'Error inesperado al comunicarse con Gemini.', 'details' => $data]);
}
