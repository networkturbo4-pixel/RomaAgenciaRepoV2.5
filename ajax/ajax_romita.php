<?php
// ajax/ajax_romita.php
session_start();
require_once '../config/database.php';
require_once '../vendor/autoload.php';
require_once '../includes/GoogleDriveHelper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$db = (new Database())->getConnection();
$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

// Check admin
$stmt_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt_admin->execute([$user_id]);
$is_admin = ($stmt_admin->fetchColumn() == 1);

try {
    if ($action === 'chat') {
        $message = $_POST['message'] ?? '';
        $skill_prompt = $_POST['skill_prompt'] ?? '';
        
        if(empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Mensaje vacío']);
            exit();
        }

        // Guarda mensaje del usuario
        $chat_id = $_POST['chat_id'] ?? null;
        
        if (!$chat_id) {
            // Genera título corto del mensaje
            $title = mb_substr($message, 0, 30) . (mb_strlen($message) > 30 ? '...' : '');
            $stmt = $db->prepare("INSERT INTO romita_chats (user_id, title) VALUES (?, ?)");
            $stmt->execute([$user_id, $title]);
            $chat_id = $db->lastInsertId();
        }

        // Insertar msj usuario
        $stmt_msg = $db->prepare("INSERT INTO romita_messages (chat_id, role, content) VALUES (?, 'user', ?)");
        $stmt_msg->execute([$chat_id, $message]);

        // 1. CONEXIÓN A GOOGLE GEMINI API
        $apiKey = 'AQ.Ab8RN6IMDdwCwC9tCRzve5p6Vf8te8CVRhFAjucDPSCJ9wy5Mg';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

        // Recuperar contexto anterior (últimos 10 mensajes)
        $stmt_hist = $db->prepare("SELECT role, content FROM romita_messages WHERE chat_id = ? ORDER BY id ASC LIMIT 10");
        $stmt_hist->execute([$chat_id]);
        $history = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

        $contents = [];
        foreach($history as $h) {
            // Gemini roles: 'user' or 'model'
            $geminiRole = $h['role'] === 'user' ? 'user' : 'model';
            $contents[] = [
                "role" => $geminiRole,
                "parts" => [["text" => $h['content']]]
            ];
        }

        $payload = [ "contents" => $contents ];

        // Instrucción de Sistema
        $sysInstructions = [];
        if (!empty($skill_prompt)) {
            $sysInstructions[] = $skill_prompt;
        }

        // Si hay un Prept asociado, leer sus datos y publicaciones anteriores
        $prept_id = $_POST['prept_id'] ?? null;
        if ($prept_id) {
            $stmt = $db->prepare("SELECT name, tone, audience, rules FROM romita_prepts WHERE id = ?");
            $stmt->execute([$prept_id]);
            $prept = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($prept) {
                $preptCtx = "Eres el gestor de contenido de la marca '{$prept['name']}'.\n";
                $preptCtx .= "Tono: {$prept['tone']}\nAudiencia: {$prept['audience']}\nReglas: {$prept['rules']}\n\n";
                
                // Leer últimas 10 publicaciones para evitar repeticiones
                $stmt2 = $db->prepare("SELECT topic, content_summary, created_at FROM romita_prept_content WHERE prept_id = ? ORDER BY created_at DESC LIMIT 10");
                $stmt2->execute([$prept_id]);
                $pastContents = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($pastContents) > 0) {
                    $preptCtx .= "HISTORIAL DE CONTENIDOS PREVIOS (NO REPETIR ESTOS TEMAS):\n";
                    foreach($pastContents as $pc) {
                        $preptCtx .= "- Fecha: {$pc['created_at']}, Tema: {$pc['topic']}, Resumen: {$pc['content_summary']}\n";
                    }
                }
                
                $sysInstructions[] = $preptCtx;
            }
        }

        if (count($sysInstructions) > 0) {
            $payload["system_instruction"] = [
                "parts" => [["text" => implode("\n\n---\n\n", $sysInstructions)]]
            ];
        }

        // Búsqueda Web Nativa (Grounding)
        $payload["tools"] = [
            ["googleSearch" => new stdClass()]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $ia_response = "";
        
        if ($httpcode >= 200 && $httpcode < 300) {
            $responseData = json_decode($response, true);
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $ia_response = $responseData['candidates'][0]['content']['parts'][0]['text'];
            } else {
                $ia_response = "Lo siento, recibí una respuesta inesperada de Gemini.";
            }
        } else {
            $errorData = json_decode($response, true);
            $errMsg = $errorData['error']['message'] ?? 'Error desconocido';
            echo json_encode(['success' => false, 'error' => "Error de API ($httpcode): $errMsg"]);
            exit();
        }

        // Insertar msj IA
        $stmt_msg->execute([$chat_id, $ia_response]);

        // Si hay un Prept asociado y la IA generó contenido (por ejemplo, más de 200 caracteres), guardarlo en el historial del prept
        if ($prept_id && strlen($ia_response) > 200) {
            // Guardamos un fragmento como tema y el inicio como resumen
            $topic = mb_substr($message, 0, 100);
            $summary = mb_substr($ia_response, 0, 500) . '...';
            $stmt = $db->prepare("INSERT INTO romita_prept_content (prept_id, topic, content_summary) VALUES (?, ?, ?)");
            $stmt->execute([$prept_id, $topic, $summary]);
        }

        // Backup a Google Drive sigue intacto
        $drive = new GoogleDriveHelper();
        if ($drive->isConfigured()) {
            $folderName = "Romita_Chats";
            $files = $drive->searchFiles("name='$folderName' and mimeType='application/vnd.google-apps.folder' and trashed=false");
            
            $folderId = null;
            if ($files && count($files) > 0) {
                $folderId = $files[0]['id'];
            } else {
                $folderId = $drive->createFolder($folderName);
            }
            
            if ($folderId) {
                $date = date('Y-m-d');
                $userName = preg_replace('/[^A-Za-z0-9_]/', '', $_SESSION['user_name']);
                $fileName = "chat_{$userName}_{$date}.md";
                
                $logContent = "### User (" . date('H:i:s') . ")\n" . $message . "\n\n";
                $logContent .= "### Romita (" . date('H:i:s') . ")\n" . $ia_response . "\n\n---\n\n";
                
                $tmpPath = sys_get_temp_dir() . '/' . $fileName;
                
                $existingFiles = $drive->searchFiles("name='$fileName' and '$folderId' in parents and trashed=false");
                if($existingFiles && count($existingFiles) > 0) {
                    $fileId = $existingFiles[0]['id'];
                    $drive->downloadFile($fileId, $tmpPath);
                    file_put_contents($tmpPath, $logContent, FILE_APPEND);
                    $drive->deleteFile($fileId);
                } else {
                    file_put_contents($tmpPath, $logContent);
                }
                $drive->uploadFile($tmpPath, $fileName, $folderId);
                @unlink($tmpPath);
            }
        }

        echo json_encode(['success' => true, 'response' => $ia_response, 'chat_id' => $chat_id]);
        exit();
    }
    
    // Obtener lista de chats
    if ($action === 'get_chats') {
        $stmt = $db->prepare("SELECT id, title, created_at FROM romita_chats WHERE user_id = ? ORDER BY updated_at DESC LIMIT 50");
        $stmt->execute([$user_id]);
        $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'chats' => $chats]);
        exit();
    }

    // Obtener mensajes de un chat
    if ($action === 'get_messages') {
        $chat_id = $_POST['chat_id'] ?? '';
        
        // Validar propiedad
        $stmt_check = $db->prepare("SELECT id FROM romita_chats WHERE id = ? AND user_id = ?");
        $stmt_check->execute([$chat_id, $user_id]);
        if (!$stmt_check->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit();
        }

        $stmt = $db->prepare("SELECT id, role, content FROM romita_messages WHERE chat_id = ? ORDER BY id ASC");
        $stmt->execute([$chat_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit();
    }

    // Gestión de PREPTS
    if ($action === 'get_prepts') {
        $stmt = $db->query("SELECT * FROM romita_prepts ORDER BY name ASC");
        $prepts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'prepts' => $prepts]);
        exit();
    }

    if ($action === 'save_prept') {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit();
        }
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        $tone = $_POST['tone'] ?? '';
        $audience = $_POST['audience'] ?? '';
        $rules = $_POST['rules'] ?? '';

        if(empty($name)) {
            echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio']);
            exit();
        }

        if ($id) {
            $stmt = $db->prepare("UPDATE romita_prepts SET name=?, tone=?, audience=?, rules=? WHERE id=?");
            $stmt->execute([$name, $tone, $audience, $rules, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO romita_prepts (name, tone, audience, rules) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $tone, $audience, $rules]);
        }
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    // Acciones de Administrador
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Permisos insuficientes']);
        exit();
    }

    if ($action === 'save_skill') {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        $prompt = $_POST['prompt_base'] ?? '';
        $role = $_POST['role'] ?? 'all';
        $desc = "Skill personalizado para $name";

        if ($id) {
            $stmt = $db->prepare("UPDATE romita_skills SET name=?, description=?, prompt_base=?, allowed_role=? WHERE id=?");
            $stmt->execute([$name, $desc, $prompt, $role, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO romita_skills (name, description, prompt_base, allowed_role, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$name, $desc, $prompt, $role]);
        }
        
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'delete_skill') {
        $id = $_POST['id'] ?? '';
        if($id) {
            $stmt = $db->prepare("DELETE FROM romita_skills WHERE id = ?");
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Acción inválida']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
