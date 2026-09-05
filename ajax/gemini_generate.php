<?php
// ajax/gemini_generate.php
require_once __DIR__ . '/../config/database.php';

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
$image = $data['image'] ?? '';
$concept = $data['concept'] ?? '';
$pillar = $data['pillar'] ?? '';
$platforms = $data['platforms'] ?? [];

if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
    exit();
}

// Get API Key from database settings or fallback
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
$dbKey = $stmt ? $stmt->fetchColumn() : null;
$apiKey = !empty($dbKey) ? trim($dbKey) : 'AIzaSyDIzZJ62tamjKWL73CgEORCDxzifIlIkUw';

if (empty($apiKey)) {
    echo json_encode(['success' => false, 'error' => 'La API Key de Gemini no está configurada en Ajustes > IA.']);
    exit();
}

$systemInstruction = "Eres un experto Director Creativo, Copywriter y Social Media Strategist de alto nivel. Tu especialidad es analizar piezas gráficas y diseñar publicaciones cautivadoras, profesionales y altamente efectivas para redes sociales. Usa un tono cercano, persuasivo y moderno, integrando emoticones (emojis) estratégicos y hashtags óptimos. Devuelve directamente el texto final sin introducciones explicativas.";

$contentsParts = [];

if ($action === 'generar_desde_imagen') {
    if (empty($image)) {
        echo json_encode(['success' => false, 'error' => 'No se proporcionó ninguna imagen de la publicación terminada.']);
        exit();
    }

    // Process image into optimized inline base64 data
    $imgData = null;
    $mimeType = 'image/jpeg';

    $cleanPath = preg_replace('/^\/?/', '', $image);
    $localPath = __DIR__ . '/../' . $cleanPath;

    if (file_exists($localPath) && is_file($localPath)) {
        $raw = file_get_contents($localPath);
        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        if ($ext === 'png') $mimeType = 'image/png';
        elseif ($ext === 'webp') $mimeType = 'image/webp';
        elseif ($ext === 'gif') $mimeType = 'image/gif';
        else $mimeType = 'image/jpeg';
        
        // Optimize/Resize with GD if GD is available and file is large
        if (function_exists('imagecreatefromstring')) {
            $srcImg = @imagecreatefromstring($raw);
            if ($srcImg) {
                $w = imagesx($srcImg);
                $h = imagesy($srcImg);
                $maxDim = 1200;
                if ($w > $maxDim || $h > $maxDim) {
                    $scale = min($maxDim / $w, $maxDim / $h);
                    $newW = (int)round($w * $scale);
                    $newH = (int)round($h * $scale);
                    $dstImg = imagecreatetruecolor($newW, $newH);
                    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                    ob_start();
                    imagejpeg($dstImg, null, 82);
                    $raw = ob_get_clean();
                    $mimeType = 'image/jpeg';
                    imagedestroy($dstImg);
                }
                imagedestroy($srcImg);
            }
        }
        $imgData = base64_encode($raw);
    } elseif (filter_var($image, FILTER_VALIDATE_URL)) {
        $chImg = curl_init($image);
        curl_setopt($chImg, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chImg, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($chImg, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($chImg, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($chImg, CURLOPT_TIMEOUT, 12);
        curl_setopt($chImg, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $raw = curl_exec($chImg);
        $cType = curl_getinfo($chImg, CURLINFO_CONTENT_TYPE);
        curl_close($chImg);
        if ($raw) {
            if (function_exists('imagecreatefromstring')) {
                $srcImg = @imagecreatefromstring($raw);
                if ($srcImg) {
                    $w = imagesx($srcImg);
                    $h = imagesy($srcImg);
                    $maxDim = 1200;
                    if ($w > $maxDim || $h > $maxDim) {
                        $scale = min($maxDim / $w, $maxDim / $h);
                        $newW = (int)round($w * $scale);
                        $newH = (int)round($h * $scale);
                        $dstImg = imagecreatetruecolor($newW, $newH);
                        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                        ob_start();
                        imagejpeg($dstImg, null, 82);
                        $raw = ob_get_clean();
                        $mimeType = 'image/jpeg';
                        imagedestroy($dstImg);
                    }
                    imagedestroy($srcImg);
                }
            }
            $imgData = base64_encode($raw);
            if ($cType && $mimeType !== 'image/jpeg') {
                $mimeType = explode(';', $cType)[0];
            }
        }
    }

    if (!$imgData) {
        echo json_encode(['success' => false, 'error' => 'No se pudo cargar el archivo de imagen para el análisis visual.']);
        exit();
    }

    $contentsParts[] = [
        "inline_data" => [
            "mime_type" => $mimeType,
            "data" => $imgData
        ]
    ];

    $contextInfo = [];
    if (!empty($concept)) $contextInfo[] = "Concepto/Título: " . $concept;
    if (!empty($pillar)) $contextInfo[] = "Pilar de contenido: " . $pillar;
    if (!empty($platforms)) $contextInfo[] = "Plataformas: " . (is_array($platforms) ? implode(', ', $platforms) : $platforms);
    if (!empty($prompt)) $contextInfo[] = "Indicación extra: " . $prompt;

    $extraContext = !empty($contextInfo) ? "\n\nDetalles del post:\n" . implode("\n", $contextInfo) : "";

    $finalPrompt = "Analiza minuciosamente la imagen adjunta (textos en la imagen, producto o servicio, promociones o beneficios destacados, estilo visual) y redacta un COPY PROFESIONAL Y COMPLETO para redes sociales.$extraContext

Instrucciones de formato:
1. ✨ Gancho Inicial: Una primera línea magnética y atractiva con emojis adecuados.
2. 📖 Cuerpo de la Publicación: Explica el mensaje clave de la imagen de forma persuasiva, clara y agradable para la lectura (usa saltos de línea y emojis bien ubicados).
3. 🚀 Llamado a la Acción (CTA): Un llamado claro y directo a la acción (ej: 'Escríbenos al DM', 'Haz clic en el enlace', 'Comenta abajo', etc.).
4. 🏷️ Sección de Hashtags: Al final, incluye un bloque de 8 a 15 hashtags relevantes, populares y específicos relacionados con el tema de la imagen y la industria.

Devuelve directamente el texto final listo para copiar y publicar (sin saludos ni frases como 'Aquí tienes el post:').";

    $contentsParts[] = ["text" => $finalPrompt];

} elseif ($action === 'corregir') {
    $finalPrompt = "Corrige la ortografía, gramática y mejora el estilo del siguiente texto para que sea persuasivo y profesional en redes sociales, manteniendo su esencia original:\n\n" . $text;
    $contentsParts[] = ["text" => $finalPrompt];
} elseif ($action === 'hashtags') {
    $finalPrompt = "Genera exactamente 10 hashtags altamente relevantes, populares y optimizados para el siguiente texto. Devuélvelos separados por espacios (ej. #hashtag1 #hashtag2), sin numeración ni viñetas:\n\n" . $text;
    $contentsParts[] = ["text" => $finalPrompt];
} elseif ($action === 'generar') {
    $finalPrompt = "Escribe un copy completo y atractivo para redes sociales sobre el siguiente tema o instrucción. Incluye emojis adecuados y un llamado a la acción (CTA) al final:\n\n" . $prompt;
    $contentsParts[] = ["text" => $finalPrompt];
} else {
    echo json_encode(['success' => false, 'error' => 'Acción inválida']);
    exit();
}

// Preferred Gemini model
$modelsToTry = ['gemini-3.6-flash', 'gemini-3.5-flash', 'gemini-flash-latest', 'gemini-3.7-flash'];
$successResponse = null;
$lastError = 'Error al procesar la solicitud con Gemini';

$payload = [
    "system_instruction" => [
        "parts" => [
            ["text" => $systemInstruction]
        ]
    ],
    "contents" => [
        [
            "parts" => $contentsParts
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 2048
    ]
];

foreach ($modelsToTry as $modelName) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . $apiKey;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        $lastError = 'Error de conexión: ' . $curlErr;
        break; // Stop loop on timeout/network failure
    }

    $result = json_decode($response, true);

    if ($httpCode === 200 && isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $generatedText = trim($result['candidates'][0]['content']['parts'][0]['text']);
        $successResponse = $generatedText;
        break;
    } else {
        $apiError = $result['error']['message'] ?? "HTTP $httpCode";
        if (strpos($apiError, 'API key not valid') !== false || strpos($apiError, 'leaked') !== false || strpos($apiError, 'API_KEY_INVALID') !== false) {
            $lastError = 'La API Key de Gemini es inválida o fue revocada por Google. Por favor ingresa una API Key válida en Ajustes > IA.';
            break;
        }
        $lastError = $apiError;
        if ($httpCode === 404) {
            continue;
        } else {
            break;
        }
    }
}

if ($successResponse !== null) {
    echo json_encode(['success' => true, 'text' => $successResponse]);
} else {
    echo json_encode(['success' => false, 'error' => $lastError]);
}
