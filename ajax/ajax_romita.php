<?php
// ajax/ajax_romita.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/GoogleDriveHelper.php';

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

function getProjectCalendarContext($db, $project_id) {
    if (!$project_id) return null;

    // 1. Obtener detalles del proyecto y orden de trabajo
    $stmt = $db->prepare("
        SELECT p.id as project_id, wo.brand_name, wo.correlativo, wo.data 
        FROM projects p 
        JOIN work_orders wo ON p.work_order_id = wo.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$project_id]);
    $proj = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proj) return null;

    $woData = json_decode($proj['data'], true) ?: [];
    $servicio = $woData['servicio'] ?? 'Marketing y Redes Sociales';
    $redes = $woData['redes'] ?? 'Instagram, Facebook';

    // 2. Meses trabajados en este proyecto
    $stmtMonths = $db->prepare("
        SELECT id, month, year, status, agenda_text 
        FROM project_months 
        WHERE project_id = ? 
        ORDER BY year DESC, month DESC
    ");
    $stmtMonths->execute([$project_id]);
    $months = $stmtMonths->fetchAll(PDO::FETCH_ASSOC);

    $monthNames = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    $monthsList = [];
    foreach ($months as $m) {
        $name = ($monthNames[$m['month']] ?? $m['month']) . ' ' . $m['year'];
        $monthsList[] = $name;
    }

    // 3. Publicaciones previas registradas
    $stmtPosts = $db->prepare("
        SELECT mp.concept, mp.copy_text, mp.platform, mp.post_type, mp.content_pillar, mp.post_date, pm.month, pm.year 
        FROM month_posts mp 
        JOIN project_months pm ON mp.month_id = pm.id 
        WHERE pm.project_id = ? 
        ORDER BY mp.post_date DESC 
        LIMIT 40
    ");
    $stmtPosts->execute([$project_id]);
    $posts = $stmtPosts->fetchAll(PDO::FETCH_ASSOC);

    $pillarsCount = [];
    $formatsCount = [];
    $conceptsList = [];

    foreach ($posts as $p) {
        if (!empty($p['content_pillar'])) {
            $pillarsCount[$p['content_pillar']] = ($pillarsCount[$p['content_pillar']] ?? 0) + 1;
        }
        if (!empty($p['post_type'])) {
            $formatsCount[$p['post_type']] = ($formatsCount[$p['post_type']] ?? 0) + 1;
        }
        if (!empty($p['concept']) && trim($p['concept']) !== '.') {
            $mName = $monthNames[$p['month']] ?? $p['month'];
            $pilar = !empty($p['content_pillar']) ? " [{$p['content_pillar']}]" : '';
            $formato = !empty($p['post_type']) ? " ({$p['post_type']})" : '';
            $conceptsList[] = "• [{$mName} {$p['year']}]{$pilar}{$formato}: \"{$p['concept']}\"";
        }
    }

    $pillarsStr = !empty($pillarsCount) ? implode(', ', array_map(function($k, $v) { return "$k: $v"; }, array_keys($pillarsCount), $pillarsCount)) : 'General';
    $formatsStr = !empty($formatsCount) ? implode(', ', array_map(function($k, $v) { return "$k: $v"; }, array_keys($formatsCount), $formatsCount)) : 'Variados';

    $ctx = "=== BASE DE CONOCIMIENTO HISTÓRICA DEL CALENDARIO: MARCA '{$proj['brand_name']}' ===\n";
    $ctx .= "Proyecto ID: #{$proj['project_id']} ({$proj['correlativo']}) | Servicio: {$servicio}\n";
    $ctx .= "Redes activas del cliente: {$redes}\n";
    $ctx .= "Meses trabajados en la plataforma: " . (empty($monthsList) ? "Ninguno aún" : implode(', ', $monthsList)) . "\n";
    $ctx .= "Total de publicaciones analizadas: " . count($posts) . "\n";
    $ctx .= "Distribución histórica de pilares de contenido: {$pillarsStr}\n";
    $ctx .= "Formatos frecuentes utilizados: {$formatsStr}\n\n";

    if (!empty($conceptsList)) {
        $ctx .= "HISTORIAL DE TEMAS Y CONCEPTOS YA PUBLICADOS (REGLA CRÍTICA: NO REPETIR ESTAS IDEAS DE CONTENIDO):\n";
        $ctx .= implode("\n", array_slice($conceptsList, 0, 30)) . "\n\n";
    }

    $ctx .= "DIRECTRICES PARA ROMITA:\n";
    $ctx .= "1. Conduce la conversación como el estratega senior de contenido y social media manager de '{$proj['brand_name']}'.\n";
    $ctx .= "2. Conoce a la perfección los pilares y temas de la marca. Si te preguntan cómo se maneja la marca o qué se ha hecho, explica los pilares y formatos usados según los datos anteriores.\n";
    $ctx .= "3. Si el usuario te pide un nuevo mes o plan de contenido:\n";
    $ctx .= "   - Propón ideas frescas basadas en los pilares pero SIN repetir conceptos ya publicados.\n";
    $ctx .= "   - Presenta la propuesta en una tabla visual ordenada: Fecha, Concepto, Pilar, Formato, Red Social, Idea de Copy y Brief visual.\n";
    $ctx .= "   - Al final de tu mensaje, si propones un plan estructurado, incluye SIEMPRE el siguiente bloque especial de datos para que la plataforma permita crearlo en el calendario con 1 solo clic:\n";
    $ctx .= "```json:calendar_plan\n";
    $ctx .= "{\n";
    $ctx .= "  \"project_id\": {$proj['project_id']},\n";
    $ctx .= "  \"month\": [numero_mes_1_al_12],\n";
    $ctx .= "  \"year\": [año_actual_o_proximo],\n";
    $ctx .= "  \"posts\": [\n";
    $ctx .= "    {\n";
    $ctx .= "      \"date\": \"YYYY-MM-DD\",\n";
    $ctx .= "      \"concept\": \"Título conciso del post\",\n";
    $ctx .= "      \"copy\": \"Texto persuasivo completo con gancho, desarrollo y llamado a la acción\",\n";
    $ctx .= "      \"platform\": \"Instagram, Facebook\",\n";
    $ctx .= "      \"post_type\": \"Reel\",\n";
    $ctx .= "      \"content_pillar\": \"Educación\",\n";
    $ctx .= "      \"design_brief\": \"Pautas visuales y recursos requeridos\"\n";
    $ctx .= "    }\n";
    $ctx .= "  ]\n";
    $ctx .= "}\n";
    $ctx .= "```\n";

    return [
        'context_text' => $ctx,
        'project_id' => $proj['project_id'],
        'brand_name' => $proj['brand_name'],
        'correlativo' => $proj['correlativo'],
        'total_months' => count($months),
        'total_posts' => count($posts),
        'months_list' => $monthsList,
        'pillars' => $pillarsCount
    ];
}

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
        $stmt_user_msg = $db->prepare("INSERT INTO romita_messages (chat_id, role, content) VALUES (?, 'user', ?)");
        $stmt_user_msg->execute([$chat_id, $message]);

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

        // Contexto temporal en tiempo real (Zona horaria de la empresa: America/Lima)
        $tz = new DateTimeZone('America/Lima');
        $now = new DateTime('now', $tz);
        $diasSemana = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        
        $diaNombre = $diasSemana[$now->format('l')] ?? $now->format('l');
        $diaNum = $now->format('d');
        $mesNum = (int)$now->format('m');
        $mesNombre = $meses[$mesNum];
        $anoActual = $now->format('Y');

        $nextMonthDate = (clone $now)->modify('+1 month');
        $nextMesNum = (int)$nextMonthDate->format('m');
        $nextMesNombre = $meses[$nextMesNum];
        $nextAno = $nextMonthDate->format('Y');

        $temporalContext = "INFORMACIÓN TEMPORAL ACTUAL EN TIEMPO REAL:\n"
            . "- Fecha actual exacta: {$diaNombre}, {$diaNum} de {$mesNombre} de {$anoActual}.\n"
            . "- Mes actual: {$mesNombre} ({$anoActual}) - Mes número {$mesNum}.\n"
            . "- Próximo mes: {$nextMesNombre} ({$nextAno}) - Mes número {$nextMesNum}.\n"
            . "- REGLA CRÍTICA DE FECHA: Sabes con total certidumbre la fecha, mes y año de hoy. Si el usuario te pide una propuesta o estrategia para 'este mes', asume directamente y sin dudar {$mesNombre} de {$anoActual}. Si pide 'el próximo mes', asume {$nextMesNombre} de {$nextAno}. NUNCA le preguntes al usuario '¿en qué mes estamos?' ni '¿qué año es?'.\n\n"
            . "REGLA DE FORMATO DE TABLAS:\n"
            . "- Si presentas propuestas, calendarios o contenidos en tabla, utiliza SIEMPRE tablas Markdown válidas y estándar con fila de encabezados y fila separadora obligatoria (ej: | :--- | :--- | :--- |).\n"
            . "- Asegúrate de incluir columnas claras como: Fecha / Día, Formato, Pilar / Objetivo, Concepto / Gancho, Copy Sugerido, y Especificación Visual.\n"
            . "- No mezcles texto suelto dentro de las celdas que rompa los saltos de línea de la tabla Markdown.";
        
        $sysInstructions[] = $temporalContext;

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

        // Si hay un Proyecto de Calendario asociado, inyectar base de conocimiento histórica
        $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        if ($project_id) {
            $projIntel = getProjectCalendarContext($db, $project_id);
            if ($projIntel) {
                $sysInstructions[] = $projIntel['context_text'];
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
        $stmt_ai_msg = $db->prepare("INSERT INTO romita_messages (chat_id, role, content) VALUES (?, 'assistant', ?)");
        $stmt_ai_msg->execute([$chat_id, $ia_response]);

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

    // Eliminar un chat
    if ($action === 'delete_chat') {
        $chat_id = $_POST['chat_id'] ?? '';
        
        $stmt_check = $db->prepare("SELECT id FROM romita_chats WHERE id = ? AND user_id = ?");
        $stmt_check->execute([$chat_id, $user_id]);
        if (!$stmt_check->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit();
        }

        $stmt = $db->prepare("DELETE FROM romita_chats WHERE id = ?");
        $stmt->execute([$chat_id]);
        echo json_encode(['success' => true]);
        exit();
    }

    // Obtener resumen de inteligencia de proyecto para badge/UI
    if ($action === 'get_project_intel') {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $intel = getProjectCalendarContext($db, $project_id);
        if ($intel) {
            echo json_encode(['success' => true, 'intel' => $intel]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Proyecto no encontrado']);
        }
        exit();
    }

    // Crear mes y publicaciones en el módulo Calendario
    if ($action === 'create_calendar_month') {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $month = (int)($_POST['month'] ?? 0);
        $year = (int)($_POST['year'] ?? 0);
        $posts_json = $_POST['posts_json'] ?? '';

        if (!$project_id || !$month || !$year) {
            echo json_encode(['success' => false, 'error' => 'Proyecto, mes y año son obligatorios']);
            exit();
        }

        // Validar proyecto
        $stmtP = $db->prepare("SELECT p.id, wo.brand_name FROM projects p JOIN work_orders wo ON p.work_order_id = wo.id WHERE p.id = ?");
        $stmtP->execute([$project_id]);
        $proj = $stmtP->fetch(PDO::FETCH_ASSOC);
        if (!$proj) {
            echo json_encode(['success' => false, 'error' => 'Proyecto no encontrado']);
            exit();
        }

        // Comprobar si el mes ya existe o crearlo
        $stmtCheck = $db->prepare("SELECT id FROM project_months WHERE project_id = ? AND month = ? AND year = ?");
        $stmtCheck->execute([$project_id, $month, $year]);
        $existingMonthId = $stmtCheck->fetchColumn();

        if ($existingMonthId) {
            $month_id = $existingMonthId;
        } else {
            $start_date = sprintf('%04d-%02d-01', $year, $month);
            $due_date = date('Y-m-t', strtotime($start_date));
            $stmtInsert = $db->prepare("INSERT INTO project_months (project_id, month, year, start_date, due_date, status) VALUES (?, ?, ?, ?, ?, 'pendiente')");
            $stmtInsert->execute([$project_id, $month, $year, $start_date, $due_date]);
            $month_id = $db->lastInsertId();
        }

        // Insertar publicaciones si se recibieron
        $createdCount = 0;
        if (!empty($posts_json)) {
            $posts = json_decode($posts_json, true);
            if (is_array($posts)) {
                $stmtPost = $db->prepare("
                    INSERT INTO month_posts 
                    (month_id, post_date, concept, copy_text, platform, status, post_type, content_pillar, design_brief) 
                    VALUES (?, ?, ?, ?, ?, 'Borrador', ?, ?, ?)
                ");

                foreach ($posts as $p) {
                    $concept = trim($p['concept'] ?? 'Publicación planificada');
                    $copy = trim($p['copy'] ?? ($p['copy_text'] ?? ''));
                    $platform = trim($p['platform'] ?? 'Instagram, Facebook');
                    $post_type = trim($p['post_type'] ?? 'Post Terminado');
                    $pillar = trim($p['content_pillar'] ?? 'Educación');
                    $brief = trim($p['design_brief'] ?? '');
                    
                    // Formatear post_date
                    $rawDate = $p['date'] ?? ($p['post_date'] ?? '');
                    if (!empty($rawDate)) {
                        $ts = strtotime($rawDate);
                        $post_date = $ts ? date('Y-m-d 10:00:00', $ts) : sprintf('%04d-%02d-01 10:00:00', $year, $month);
                    } else {
                        $post_date = sprintf('%04d-%02d-01 10:00:00', $year, $month);
                    }

                    $stmtPost->execute([$month_id, $post_date, $concept, $copy, $platform, $post_type, $pillar, $brief]);
                    $createdCount++;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'month_id' => $month_id,
            'brand_name' => $proj['brand_name'],
            'created_posts' => $createdCount,
            'redirect_url' => "index.php?module=month_board&id={$month_id}"
        ]);
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
