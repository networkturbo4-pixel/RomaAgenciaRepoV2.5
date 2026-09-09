<?php
// modules/mensajes/api_widget.php
// API REST pública para el Plugin de WordPress (Roma Chat & Portal)

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Roma-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión con la base de datos']);
    exit;
}

// =========================================================================
// VALIDACIÓN DE APP KEY (ROMA CRM <-> WORDPRESS)
// =========================================================================
$stmtKey = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('crm_api_key', 'crm_api_enabled')");
$stmtKey->execute();
$apiSettings = $stmtKey->fetchAll(PDO::FETCH_KEY_PAIR);

$expectedKey = $apiSettings['crm_api_key'] ?? '';
$apiEnabled = ($apiSettings['crm_api_enabled'] ?? '1') === '1';

if (!$apiEnabled) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'La API para WordPress está desactivada en el módulo de Conexiones de Roma CRM.']);
    exit;
}

// Obtener clave enviada (cabecera HTTP_X_ROMA_API_KEY o GET/POST api_key)
$providedKey = $_SERVER['HTTP_X_ROMA_API_KEY'] ?? $_REQUEST['api_key'] ?? '';

if (!empty($expectedKey)) {
    if (empty($providedKey) || !hash_equals($expectedKey, $providedKey)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'App Key inválida o no proporcionada. Por favor verifica la App Key en los ajustes de WordPress (Roma Portal).']);
        exit;
    }
}

// Actualizar timestamp de última conexión exitosa
try {
    $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('crm_api_last_access', NOW()) ON DUPLICATE KEY UPDATE setting_value = NOW()")->execute();
} catch (Exception $e) {}

// Pusher Setup (para notificaciones en tiempo real)
$pusher = null;
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    try {
        $pusher_options = array('cluster' => 'us2', 'useTLS' => true);
        $pusher = new Pusher\Pusher('b31f38612d61b0285c78', 'c0cabd7a57efdc79f42e', '2156473', $pusher_options);
    } catch (Exception $e) {
        $pusher = null;
    }
}

function notifyPusher($chat_id) {
    global $pusher;
    if ($pusher) {
        try {
            $pusher->trigger('chat-' . $chat_id, 'refresh', ['time' => time()]);
        } catch (Exception $e) {}
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // =========================================================================
    // 1. CONSULTA DE CLIENTE POR DNI O TELÉFONO (SOPORTE)
    // =========================================================================
    case 'lookup_client':
        $query = trim($_GET['query'] ?? $_POST['query'] ?? '');
        if (empty($query)) {
            echo json_encode(['success' => false, 'error' => 'Por favor ingresa tu DNI o número de teléfono']);
            exit;
        }

        $cleanQ = preg_replace('/[^0-9]/', '', $query);

        // Buscar en clientes por DNI exacto, WhatsApp o teléfono
        $stmt = $db->prepare("
            SELECT id, name, dni, whatsapp, email, created_at, portal_enabled 
            FROM clients 
            WHERE dni = ? 
               OR (LENGTH(?) >= 6 AND dni = ?)
               OR whatsapp LIKE ? 
               OR (LENGTH(?) >= 6 AND whatsapp LIKE ?)
            LIMIT 1
        ");
        $likeClean = '%' . $cleanQ . '%';
        $likeRaw = '%' . $query . '%';
        $stmt->execute([$query, $cleanQ, $cleanQ, $likeRaw, $cleanQ, $likeClean]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            echo json_encode([
                'success' => false,
                'found' => false,
                'message' => 'No encontramos registros con el DNI o teléfono ingresado. Si eres cliente nuevo o necesitas asistencia, puedes contactarnos por el chat o cotizar un servicio.'
            ]);
            exit;
        }

        // Obtener marcas del cliente
        $bStmt = $db->prepare("
            SELECT id, name, logo, has_membership, services_ids, whatsapp_group, color 
            FROM client_brands 
            WHERE client_id = ?
        ");
        $bStmt->execute([$client['id']]);
        $brands = $bStmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener nombres de servicios asignados a las marcas
        foreach ($brands as &$brand) {
            $serviceList = [];
            $servicesIds = [];

            if (!empty($brand['services_ids'])) {
                $decoded = json_decode($brand['services_ids'], true);
                if (is_array($decoded)) {
                    $servicesIds = $decoded;
                } else {
                    $servicesIds = array_filter(array_map('trim', explode(',', $brand['services_ids'])));
                }
            }

            if (!empty($servicesIds)) {
                $placeholders = implode(',', array_fill(0, count($servicesIds), '?'));
                $sStmt = $db->prepare("SELECT id, name, description, price, currency, category_id FROM services WHERE id IN ($placeholders) AND deleted_at IS NULL");
                $sStmt->execute($servicesIds);
                $serviceList = $sStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $brand['services'] = $serviceList;
        }
        unset($brand);

        // Proyectos / servicios activos en project_services
        $projectServices = [];
        try {
            $pStmt = $db->prepare("
                SELECT ps.id, ps.title, ps.description, ps.start_date, ps.due_date, ps.status, s.name as service_name
                FROM project_services ps
                JOIN projects p ON ps.project_id = p.id
                LEFT JOIN services s ON ps.service_id = s.id
                WHERE p.work_order_id IN (
                    SELECT id FROM work_orders WHERE client_id = ?
                )
                ORDER BY ps.id DESC LIMIT 10
            ");
            $pStmt->execute([$client['id']]);
            $projectServices = $pStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'found' => true,
            'client' => [
                'id' => $client['id'],
                'name' => $client['name'],
                'dni' => $client['dni'],
                'whatsapp' => $client['whatsapp'],
                'email' => $client['email'],
                'portal_enabled' => (bool)$client['portal_enabled']
            ],
            'brands' => $brands,
            'project_services' => $projectServices
        ]);
        break;

    // =========================================================================
    // 2. CATÁLOGO DE SERVICIOS PARA COTIZAR
    // =========================================================================
    case 'get_services':
        try {
            $stmt = $db->query("
                SELECT id, name, description, price, currency, category_id, delivery_time, badge
                FROM services 
                WHERE deleted_at IS NULL 
                  AND (status = 'active' OR status IS NULL) 
                ORDER BY name ASC
            ");
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'services' => $services]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // =========================================================================
    // 3. ENVIAR SOLICITUD DE COTIZACIÓN
    // =========================================================================
    case 'submit_quote':
        $name = trim($_POST['name'] ?? '');
        $dni = trim($_POST['dni'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $service_id = trim($_POST['service_id'] ?? '');
        $service_name = trim($_POST['service_name'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || empty($phone)) {
            echo json_encode(['success' => false, 'error' => 'Nombre y teléfono son obligatorios']);
            exit;
        }

        try {
            // 1. Buscar o registrar cliente prospecto
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            $checkStmt = $db->prepare("SELECT id FROM clients WHERE (dni != '' AND dni = ?) OR whatsapp LIKE ? LIMIT 1");
            $checkStmt->execute([$dni, "%$cleanPhone%"]);
            $clientId = $checkStmt->fetchColumn();

            if (!$clientId) {
                $cInsert = $db->prepare("INSERT INTO clients (name, dni, whatsapp, email, created_at) VALUES (?, ?, ?, ?, NOW())");
                $cInsert->execute([$name, $dni, $phone, $email]);
                $clientId = $db->lastInsertId();
            }

            // 2. Resolver nombre del servicio si vino solo id
            if (empty($service_name) && !empty($service_id)) {
                $sStmt = $db->prepare("SELECT name FROM services WHERE id = ?");
                $sStmt->execute([$service_id]);
                $service_name = $sStmt->fetchColumn() ?: 'Servicio General';
            }
            if (empty($service_name)) {
                $service_name = 'Consulta General / Personalizado';
            }

            // 3. Registrar la cotización en la tabla quotes si existe
            $quoteId = null;
            try {
                $qStmt = $db->prepare("
                    INSERT INTO quotes (client_id, issue_date, status, notes, created_at) 
                    VALUES (?, CURDATE(), 'Borrador', ?, NOW())
                ");
                $quoteNotes = "Cotización solicitada desde WordPress.\nServicio: {$service_name}\nDetalles: {$message}";
                $qStmt->execute([$clientId, $quoteNotes]);
                $quoteId = $db->lastInsertId();
            } catch (Exception $e) {}

            // 4. Crear conversación en el módulo de mensajes (CRM)
            $chatToken = bin2hex(random_bytes(16));
            $chatName = "Cotización: " . $name . " (" . $service_name . ")";
            
            $chatStmt = $db->prepare("
                INSERT INTO msg_chats (type, name, public_link, description, created_at) 
                VALUES ('direct', ?, ?, ?, NOW())
            ");
            $chatStmt->execute([$chatName, $chatToken, "Contacto: $phone | Email: $email"]);
            $chatId = $db->lastInsertId();

            // Crear guest para el cliente
            $guestToken = bin2hex(random_bytes(16));
            $gStmt = $db->prepare("INSERT INTO msg_guests (name, token, created_at) VALUES (?, ?, NOW())");
            $gStmt->execute([$name, $guestToken]);
            $guestId = $db->lastInsertId();

            // Vincular participante
            $pStmt = $db->prepare("INSERT INTO msg_participants (chat_id, guest_id, role) VALUES (?, ?, 'member')");
            $pStmt->execute([$chatId, $guestId]);

            // Mensaje inicial formateado en el chat
            $msgContent = "📋 NUEVA SOLICITUD DE COTIZACIÓN DESDE LA WEB\n\n"
                        . "👤 Nombre: {$name}\n"
                        . (!empty($dni) ? "🆔 DNI/RUC: {$dni}\n" : "")
                        . "📱 WhatsApp/Tel: {$phone}\n"
                        . (!empty($email) ? "✉️ Email: {$email}\n" : "")
                        . "💼 Servicio de interés: {$service_name}\n"
                        . (!empty($message) ? "📝 Mensaje / Requerimiento:\n{$message}\n" : "");

            $mStmt = $db->prepare("INSERT INTO msg_messages (chat_id, sender_guest_id, content, type, created_at) VALUES (?, ?, ?, 'text', NOW())");
            $mStmt->execute([$chatId, $guestId, $msgContent]);

            notifyPusher($chatId);

            echo json_encode([
                'success' => true,
                'message' => '¡Tu solicitud ha sido recibida con éxito! Nuestro equipo se pondrá en contacto contigo a la brevedad.',
                'chat_id' => $chatId,
                'chat_token' => $chatToken,
                'guest_token' => $guestToken,
                'quote_id' => $quoteId
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // =========================================================================
    // 4. INICIAR CHAT DESDE LA BURBUJA FLOTANTE (WEB VISITOR)
    // =========================================================================
    case 'init_chat':
        $name = trim($_POST['name'] ?? 'Visitante Web');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $initial_message = trim($_POST['initial_message'] ?? '');

        try {
            $guestToken = bin2hex(random_bytes(16));
            $gStmt = $db->prepare("INSERT INTO msg_guests (name, token, created_at) VALUES (?, ?, NOW())");
            $gStmt->execute([$name, $guestToken]);
            $guestId = $db->lastInsertId();

            $chatToken = bin2hex(random_bytes(16));
            $chatTitle = "Web: " . $name . ($phone ? " ($phone)" : "");
            
            $cStmt = $db->prepare("
                INSERT INTO msg_chats (type, name, public_link, description, created_at) 
                VALUES ('direct', ?, ?, ?, NOW())
            ");
            $cStmt->execute([$chatTitle, $chatToken, "Visitante desde WordPress web" . ($email ? " - $email" : "")]);
            $chatId = $db->lastInsertId();

            $pStmt = $db->prepare("INSERT INTO msg_participants (chat_id, guest_id, role) VALUES (?, ?, 'member')");
            $pStmt->execute([$chatId, $guestId]);

            // Mensaje inicial si lo escribió al abrir
            if (!empty($initial_message)) {
                $mStmt = $db->prepare("INSERT INTO msg_messages (chat_id, sender_guest_id, content, type, created_at) VALUES (?, ?, ?, 'text', NOW())");
                $mStmt->execute([$chatId, $guestId, $initial_message]);
                notifyPusher($chatId);
            }

            echo json_encode([
                'success' => true,
                'chat_id' => $chatId,
                'chat_token' => $chatToken,
                'guest_id' => $guestId,
                'guest_token' => $guestToken,
                'guest_name' => $name,
                'pusher' => [
                    'key' => 'b31f38612d61b0285c78',
                    'cluster' => 'us2',
                    'channel' => 'chat-' . $chatId
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // =========================================================================
    // 5. OBTENER MENSAJES DEL CHAT
    // =========================================================================
    case 'get_messages':
        $chatToken = trim($_GET['chat_token'] ?? $_POST['chat_token'] ?? '');
        $guestToken = trim($_GET['guest_token'] ?? $_POST['guest_token'] ?? '');
        $lastId = (int)($_GET['last_id'] ?? $_POST['last_id'] ?? 0);

        if (empty($chatToken)) {
            echo json_encode(['success' => false, 'error' => 'Token de chat no proporcionado']);
            exit;
        }

        // Validar chat
        $cStmt = $db->prepare("SELECT id, name FROM msg_chats WHERE public_link = ?");
        $cStmt->execute([$chatToken]);
        $chat = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$chat) {
            echo json_encode(['success' => false, 'error' => 'Conversación no encontrada']);
            exit;
        }

        // Obtener guest_id del token si existe
        $guestId = null;
        if (!empty($guestToken)) {
            $gStmt = $db->prepare("SELECT id FROM msg_guests WHERE token = ?");
            $gStmt->execute([$guestToken]);
            $guestId = $gStmt->fetchColumn();
        }

        $mStmt = $db->prepare("
            SELECT m.id, m.content, m.type, m.file_url, m.file_name, m.created_at,
                   m.sender_user_id, m.sender_guest_id,
                   COALESCE(u.name, 'Agente Roma') as sender_user_name,
                   COALESCE(g.name, 'Visitante') as sender_guest_name
            FROM msg_messages m
            LEFT JOIN users u ON m.sender_user_id = u.id
            LEFT JOIN msg_guests g ON m.sender_guest_id = g.id
            WHERE m.chat_id = ? AND m.id > ? AND (m.is_deleted = 0 OR m.is_deleted IS NULL)
            ORDER BY m.id ASC
        ");
        $mStmt->execute([$chat['id'], $lastId]);
        $rows = $mStmt->fetchAll(PDO::FETCH_ASSOC);

        $messages = [];
        foreach ($rows as $row) {
            $isOwn = false;
            if ($guestId && $row['sender_guest_id'] == $guestId) {
                $isOwn = true;
            } elseif (!$guestId && $row['sender_guest_id'] != null) {
                $isOwn = true;
            }

            $messages[] = [
                'id' => (int)$row['id'],
                'content' => $row['content'],
                'type' => $row['type'],
                'file_url' => $row['file_url'],
                'file_name' => $row['file_name'],
                'is_own' => $isOwn,
                'sender' => $isOwn ? 'Tú' : ($row['sender_user_name'] ?: 'Agente Roma'),
                'time' => date('H:i', strtotime($row['created_at']))
            ];
        }

        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'chat_name' => $chat['name']
        ]);
        break;

    // =========================================================================
    // 6. ENVIAR MENSAJE DESDE EL WIDGET
    // =========================================================================
    case 'send_message':
        $chatToken = trim($_POST['chat_token'] ?? '');
        $guestToken = trim($_POST['guest_token'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (empty($chatToken) || empty($content)) {
            echo json_encode(['success' => false, 'error' => 'Contenido o chat inválido']);
            exit;
        }

        $cStmt = $db->prepare("SELECT id FROM msg_chats WHERE public_link = ?");
        $cStmt->execute([$chatToken]);
        $chatId = $cStmt->fetchColumn();

        if (!$chatId) {
            echo json_encode(['success' => false, 'error' => 'Chat no encontrado']);
            exit;
        }

        $guestId = null;
        if (!empty($guestToken)) {
            $gStmt = $db->prepare("SELECT id FROM msg_guests WHERE token = ?");
            $gStmt->execute([$guestToken]);
            $guestId = $gStmt->fetchColumn();
        }

        $mStmt = $db->prepare("
            INSERT INTO msg_messages (chat_id, sender_guest_id, content, type, created_at) 
            VALUES (?, ?, ?, 'text', NOW())
        ");
        $mStmt->execute([$chatId, $guestId, $content]);
        $msgId = $db->lastInsertId();

        notifyPusher($chatId);

        echo json_encode([
            'success' => true,
            'message_id' => $msgId,
            'time' => date('H:i')
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
        break;
}
