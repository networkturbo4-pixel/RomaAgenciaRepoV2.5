<?php
// ajax/gemini_generate.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';
$text = $data['text'] ?? '';
$prompt = $data['prompt'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
    exit();
}

// API Key extraída de la imagen del usuario
$apiKey = 'AIzaSyDIzZJ62tamjKWL73CgEORCDxzifIlIkUw';

$systemInstruction = "Eres un experto copywriter y Social Media Manager. Tu objetivo es escribir, corregir y optimizar copys para redes sociales. Sé directo y no uses introducciones como 'Aquí tienes el texto:' o explicaciones extra, simplemente devuelve el texto final sin rodéos.";

$finalPrompt = "";
if ($action === 'corregir') {
    $finalPrompt = "Corrige la ortografía, gramática y mejora el estilo del siguiente texto para que sea persuasivo y profesional en redes sociales, manteniendo su esencia original:\n\n" . $text;
} elseif ($action === 'hashtags') {
    $finalPrompt = "Genera exactamente 10 hashtags altamente relevantes, populares y optimizados para el siguiente texto. Devuélvelos separados por espacios (ej. #hashtag1 #hashtag2), sin numeración ni viñetas:\n\n" . $text;
} elseif ($action === 'generar') {
    $finalPrompt = "Escribe un copy completo y atractivo para redes sociales sobre el siguiente tema o instrucción. Incluye emojis adecuados y un llamado a la acción (CTA) al final:\n\n" . $prompt;
} else {
    echo json_encode(['success' => false, 'error' => 'Acción inválida']);
    exit();
}

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

$payload = [
    "system_instruction" => [
        "parts" => [
            ["text" => $systemInstruction]
        ]
    ],
    "contents" => [
        [
            "parts" => [
                ["text" => $finalPrompt]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 1024
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $error]);
    exit();
}

$result = json_decode($response, true);

if ($httpCode !== 200) {
    $apiError = $result['error']['message'] ?? 'Error en la API de Gemini';
    echo json_encode(['success' => false, 'error' => 'Error API: ' . $apiError]);
    exit();
}

if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    $generatedText = trim($result['candidates'][0]['content']['parts'][0]['text']);
    echo json_encode(['success' => true, 'text' => $generatedText]);
} else {
    echo json_encode(['success' => false, 'error' => 'No se pudo generar el contenido']);
}
