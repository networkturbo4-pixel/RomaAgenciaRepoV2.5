<?php
// modules/quotes/ajax_public_approve.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$token = trim($_POST['token'] ?? '');
$signer_name = trim($_POST['signer_name'] ?? '');
$signer_document = trim($_POST['signer_document'] ?? '');

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token de cotización requerido']);
    exit();
}

if (empty($signer_name)) {
    echo json_encode(['success' => false, 'message' => 'Por favor ingresa tu nombre completo para confirmar la aprobación.']);
    exit();
}

try {
    $db->beginTransaction();

    // 1. Obtener la cotización con su cliente
    $stmtQuote = $db->prepare("
        SELECT q.*, c.name as client_name, c.whatsapp, c.email 
        FROM quotes q 
        LEFT JOIN clients c ON q.client_id = c.id 
        WHERE q.public_token = ?
    ");
    $stmtQuote->execute([$token]);
    $quote = $stmtQuote->fetch(PDO::FETCH_ASSOC);

    if (!$quote) {
        throw new Exception('Cotización no válida o enlace caducado.');
    }

    $quote_id = (int)$quote['id'];
    $client_name = $quote['client_name'] ?: 'Cliente';
    $client_id = $quote['client_id'];
    $total = (float)$quote['total'];
    $currency = $quote['currency'] ?: 'USD';

    // Si ya está aceptada, retornar confirmación sin duplicar
    if (strtolower($quote['status']) === 'aceptada') {
        $db->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Esta cotización ya fue aprobada previamente. ¡Gracias por tu confianza!',
            'already_approved' => true
        ]);
        exit();
    }

    // 2. Obtener los ítems de la cotización
    $stmtItems = $db->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id ASC");
    $stmtItems->execute([$quote_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $processRows = [];
    $serviceNames = [];
    foreach ($items as $item) {
        $cleanDesc = trim(strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], ' ', $item['description'] ?? '')));
        if (!empty($cleanDesc)) {
            $processRows[] = [
                'encargado' => '',
                'descripcion' => $cleanDesc
            ];
            $serviceNames[] = substr($cleanDesc, 0, 40);
        }
    }

    if (empty($processRows)) {
        $processRows[] = [
            'encargado' => '',
            'descripcion' => 'Ejecución de servicios cotizados'
        ];
    }

    $mainService = !empty($serviceNames) ? implode(' + ', array_slice($serviceNames, 0, 2)) : 'Servicios Generales';

    // 3. Generar Orden de Trabajo (work_orders)
    $stmtLastWO = $db->query("SELECT id FROM work_orders ORDER BY id DESC LIMIT 1");
    $lastWO = $stmtLastWO->fetch(PDO::FETCH_ASSOC);
    $nextWoId = $lastWO ? ($lastWO['id'] + 1) : 1;
    $correlativo = 'OS-' . str_pad($nextWoId, 4, '0', STR_PAD_LEFT);
    $woToken = bin2hex(random_bytes(16));

    $woDataObj = [
        'cliente' => $client_name,
        'marca' => $client_name,
        'fechaInicio' => date('Y-m-d'),
        'servicio' => $mainService,
        'presupuesto' => $currency . ' ' . number_format($total, 2, '.', ''),
        'observaciones' => "Aprobada online por {$signer_name}" . ($signer_document ? " (Doc: {$signer_document})" : "") . " - Cotización #" . str_pad($quote_id, 4, '0', STR_PAD_LEFT),
        'procesos' => [
            [
                'id' => 'main_workflow',
                'nombre' => 'FLUJO DE TRABAJO',
                'icono' => 'ph-kanban',
                'rows' => $processRows
            ]
        ]
    ];
    $woDataJson = json_encode($woDataObj, JSON_UNESCAPED_UNICODE);

    $stmtInsertWO = $db->prepare("
        INSERT INTO work_orders (correlativo, brand_name, data, public_token, is_archived, created_at) 
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $stmtInsertWO->execute([$correlativo, $client_name, $woDataJson, $woToken]);
    $work_order_id = $db->lastInsertId();

    // 4. Generar Proyecto Operativo (projects)
    $stmtInsertProj = $db->prepare("
        INSERT INTO projects (work_order_id, team_members, status, created_at) 
        VALUES (?, '[]', 'active', NOW())
    ");
    $stmtInsertProj->execute([$work_order_id]);
    $project_id = $db->lastInsertId();

    // 5. Actualizar Estado de la Cotización a 'Aceptada'
    $stmtUpdateQuote = $db->prepare("UPDATE quotes SET status = 'Aceptada' WHERE id = ?");
    $stmtUpdateQuote->execute([$quote_id]);

    // 6. Generar Notificación Global para Administradores
    $signerIp = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
    $notifTitle = "🎉 ¡Cotización #{$quote_id} Aprobada por Cliente!";
    $notifMsg = "{$client_name} (Firmante: {$signer_name}) aprobó la propuesta por {$currency} " . number_format($total, 2) . ". Se generó la Orden {$correlativo}.";
    $notifLink = "index.php?module=work_orders&action=edit&id={$work_order_id}";

    $stmtAdmins = $db->query("SELECT id FROM users WHERE role_id = 1");
    $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $adminId) {
        $stmtNotif = $db->prepare("
            INSERT INTO notifications (user_id, title, message, link, type, icon, is_read, created_at) 
            VALUES (?, ?, ?, ?, 'success', 'ph-check-circle', 0, NOW())
        ");
        $stmtNotif->execute([$adminId, $notifTitle, $notifMsg, $notifLink]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => '¡Propuesta aprobada con éxito! Nuestro equipo de Roma Agencia ha sido notificado y se pondrá en contacto para iniciar la ejecución.',
        'correlativo' => $correlativo
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error al procesar la aprobación: ' . $e->getMessage()]);
}
