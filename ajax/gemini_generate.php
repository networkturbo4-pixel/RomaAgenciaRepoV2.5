<?php
// ajax/gemini_generate.php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true) ?: [];

$action = $data['action'] ?? '';
$subaction = $data['subaction'] ?? ($action === 'romita_assistant' ? 'custom' : $action);
$text = trim($data['text'] ?? '');
$prompt = trim($data['prompt'] ?? '');
$instruction = trim($data['instruction'] ?? $prompt);
$image = trim($data['image'] ?? '');
$concept = trim($data['concept'] ?? '');
$brief = trim($data['brief'] ?? '');
$pillar = trim($data['pillar'] ?? '');
$platforms = $data['platforms'] ?? [];
$brand = trim($data['brand'] ?? '');

if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
    exit();
}

// 1. Get API Key
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
$dbKey = $stmt ? trim($stmt->fetchColumn() ?: '') : '';

$apiKeysToTry = array_values(array_filter([
    $dbKey,
    getenv('GEMINI_API_KEY') ?: '',
    'AIzaSyDIzZJ62tamjKWL73CgEORCDxzifIlIkUw',
    'AQ.Ab8RN6IMDdwCwC9tCRzve5p6Vf8te8CVRhFAjucDPSCJ9wy5Mg'
]));

if (empty($apiKeysToTry)) {
    echo json_encode(['success' => false, 'error' => 'La API Key de Gemini no está configurada. Ve a Ajustes > IA para ingresarla.']);
    exit();
}

// Helper to load and compress image for Gemini Vision
function processImageToInlineData($imagePathOrUrl) {
    if (empty($imagePathOrUrl)) return null;

    $raw = null;
    $mimeType = 'image/jpeg';

    $cleanPath = preg_replace('/^\/?/', '', $imagePathOrUrl);
    $localPath = __DIR__ . '/../' . $cleanPath;

    if (file_exists($localPath) && is_file($localPath)) {
        $raw = file_get_contents($localPath);
        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        if ($ext === 'png') $mimeType = 'image/png';
        elseif ($ext === 'webp') $mimeType = 'image/webp';
        elseif ($ext === 'gif') $mimeType = 'image/gif';
        else $mimeType = 'image/jpeg';
    } elseif (filter_var($imagePathOrUrl, FILTER_VALIDATE_URL)) {
        $chImg = curl_init($imagePathOrUrl);
        curl_setopt($chImg, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chImg, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($chImg, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($chImg, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($chImg, CURLOPT_TIMEOUT, 10);
        curl_setopt($chImg, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $raw = curl_exec($chImg);
        $cType = curl_getinfo($chImg, CURLINFO_CONTENT_TYPE);
        curl_close($chImg);
        if ($cType) {
            $mimeType = explode(';', $cType)[0];
        }
    }

    if (!$raw) return null;

    // Optimize / resize if GD is enabled
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

    return [
        "inline_data" => [
            "mime_type" => $mimeType,
            "data" => base64_encode($raw)
        ]
    ];
}

$systemInstruction = "Eres Romita, la estratega senior de contenidos, copywriting de conversión, branding y community management de Roma Agencia.
Tu especialidad es redactar publicaciones con ganchos magnéticos (Hooks), redacción clara y cautivadora, emojis estratégicos, llamado a la acción persuasivo (CTA) y hashtags optimizados.
REGLAS OBLIGATORIAS:
1. Adapta el lenguaje a la marca y a las redes sociales indicadas (Facebook, Instagram, TikTok, LinkedIn, etc.).
2. Inicia con una primera línea o gancho atractivo que detenga el scroll.
3. Estructura el cuerpo con párrafos ágiles y legibles.
4. Finaliza con un llamado a la acción (CTA) claro.
5. Devuelve ÚNICAMENTE el texto final formateado listo para publicar. NO agregues saludos, explicaciones, ni frases introductorias como 'Aquí tienes el post:' o 'Espero que te guste:'.";

$contentsParts = [];
$attachImage = false;

// Format context details
$contextInfo = [];
if (!empty($brand)) $contextInfo[] = "Marca / Cliente: " . $brand;
if (!empty($concept)) $contextInfo[] = "Concepto / Título del Post: " . $concept;
if (!empty($brief)) $contextInfo[] = "Idea Referencial / Pautas: " . $brief;
if (!empty($pillar)) $contextInfo[] = "Pilar de Contenido: " . $pillar;
if (!empty($platforms)) $contextInfo[] = "Plataformas: " . (is_array($platforms) ? implode(', ', $platforms) : $platforms);
$contextText = !empty($contextInfo) ? "--- CONTEXTO DE LA PUBLICACIÓN ---\n" . implode("\n", $contextInfo) . "\n-----------------------------------\n" : "";

if ($action === 'romita_assistant') {
    if ($subaction === 'desde_imagen') {
        $attachImage = true;
        $finalPrompt = "{$contextText}Analiza minuciosamente la imagen de la publicación adjunta (textos, estilo gráfico, promociones, producto o servicio).
Redacta un COPY COMPLETO Y PERSUASIVO para redes sociales que complemente perfectamente la pieza visual.
Estructura:
1. Gancho magnético con emoji.
2. Desarrollo del mensaje persuasivo y claro.
3. Llamado a la acción (CTA).
4. Bloque de 8 a 12 hashtags relevantes y específicos.";
    } elseif ($subaction === 'desde_concepto') {
        $finalPrompt = "{$contextText}Basándote estrictamente en el concepto, idea referencial y pilar de contenido indicados, redacta un COPY PROFESIONAL Y ATRACTIVO para redes sociales.
Estructura:
1. Gancho llamativo que capture la atención.
2. Mensaje central persuasivo estructurado con saltos de línea y emojis.
3. Llamado a la acción (CTA) claro.
4. Bloque de 8 a 12 hashtags optimizados.";
    } elseif ($subaction === 'corregir') {
        $targetText = !empty($text) ? $text : ($concept . "\n" . $brief);
        $finalPrompt = "{$contextText}Corrige la ortografía, puntuación, gramática y eleva el estilo del siguiente texto para que sea impecable, moderno y persuasivo para redes sociales, manteniendo su intención original:\n\n" . $targetText;
    } elseif ($subaction === 'hashtags') {
        $targetText = !empty($text) ? $text : ($concept . " " . $pillar);
        $finalPrompt = "{$contextText}Genera exactamente 10 a 12 hashtags de alto impacto, populares y específicos para el siguiente texto y nicho. Devuélvelos separados por espacios (ej: #hashtag1 #hashtag2), sin listas numeradas ni viñetas:\n\n" . $targetText;
    } elseif ($subaction === 'persuasivo') {
        $targetText = !empty($text) ? $text : ($concept . "\n" . $brief);
        $finalPrompt = "{$contextText}Reescribe el siguiente contenido aplicando la fórmula de copywriting persuasivo AIDA (Atención, Interés, Deseo, Acción).
Hazlo irresistible para la audiencia, con un gancho potente al inicio, desarrollo convincente, llamado a la acción concreto y hashtags al final:\n\n" . $targetText;
    } else {
        // Custom instruction
        if (!empty($image) && (stripos($instruction, 'imagen') !== false || stripos($instruction, 'foto') !== false || stripos($instruction, 'diseño') !== false)) {
            $attachImage = true;
        }
        $instText = !empty($instruction) ? "INSTRUCCIÓN DEL USUARIO: " . $instruction : "Redacta un copy persuasivo y profesional adaptado a este post.";
        $currentTextSection = !empty($text) ? "\n\nTexto actual en el editor:\n" . $text : "";
        $finalPrompt = "{$contextText}{$instText}{$currentTextSection}\n\nEntrega el post listo con gancho, cuerpo, llamado a la acción (CTA) y hashtags.";
    }

    if ($attachImage && !empty($image)) {
        $imgObj = processImageToInlineData($image);
        if ($imgObj) {
            $contentsParts[] = $imgObj;
        } elseif ($subaction === 'desde_imagen') {
            echo json_encode(['success' => false, 'error' => 'No se pudo cargar la imagen para el análisis visual. Verifica que esté subida correctamente.']);
            exit();
        }
    }

    $contentsParts[] = ["text" => $finalPrompt];

} elseif ($action === 'generar_desde_imagen') {
    if (empty($image)) {
        echo json_encode(['success' => false, 'error' => 'No se proporcionó ninguna imagen de la publicación terminada.']);
        exit();
    }
    $imgObj = processImageToInlineData($image);
    if (!$imgObj) {
        echo json_encode(['success' => false, 'error' => 'No se pudo cargar el archivo de imagen para el análisis visual.']);
        exit();
    }
    $contentsParts[] = $imgObj;
    $finalPrompt = "{$contextText}Analiza minuciosamente la imagen adjunta y redacta un COPY PROFESIONAL Y COMPLETO para redes sociales con gancho, cuerpo persuasivo, llamado a la acción y 8 a 15 hashtags.";
    $contentsParts[] = ["text" => $finalPrompt];

} elseif ($action === 'corregir') {
    $finalPrompt = "Corrige la ortografía, gramática y mejora el estilo del siguiente texto para que sea persuasivo y profesional en redes sociales:\n\n" . $text;
    $contentsParts[] = ["text" => $finalPrompt];

} elseif ($action === 'hashtags') {
    $finalPrompt = "Genera exactamente 10 hashtags altamente relevantes y optimizados para el siguiente texto. Devuélvelos separados por espacios:\n\n" . $text;
    $contentsParts[] = ["text" => $finalPrompt];

} elseif ($action === 'generar') {
    $finalPrompt = "Escribe un copy completo y atractivo para redes sociales sobre el siguiente tema: " . $prompt;
    $contentsParts[] = ["text" => $finalPrompt];

} else {
    echo json_encode(['success' => false, 'error' => 'Acción inválida']);
    exit();
}

// Model list
$modelsToTry = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-flash-latest'];
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

$breakAll = false;
foreach ($apiKeysToTry as $currentKey) {
    if ($breakAll) break;

    foreach ($modelsToTry as $modelName) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . $currentKey;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $lastError = 'Error de conexión: ' . $curlErr;
            continue;
        }

        $result = json_decode($response, true);

        if ($httpCode === 200 && isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $generatedText = trim($result['candidates'][0]['content']['parts'][0]['text']);
            $successResponse = $generatedText;
            $breakAll = true;
            break;
        } else {
            $apiError = $result['error']['message'] ?? "HTTP $httpCode";
            $lastError = $apiError;
            if ($httpCode === 404) {
                continue; // Try next model
            }
            if ($httpCode === 400 || $httpCode === 401 || $httpCode === 403) {
                break; // Try next API key
            }
        }
    }
}

if ($successResponse !== null) {
    echo json_encode(['success' => true, 'text' => $successResponse]);
} else {
    // Si la clave no está configurada o falló
    if (strpos($lastError, 'API key') !== false || strpos($lastError, 'service account') !== false || strpos($lastError, 'PERMISSION_DENIED') !== false) {
        $lastError = 'La API Key de Gemini necesita ser configurada o actualizada en Ajustes > IA para activar las funciones de Romita.';
    }
    echo json_encode(['success' => false, 'error' => $lastError]);
}
