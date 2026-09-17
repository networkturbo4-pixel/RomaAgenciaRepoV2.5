<?php
// modules/quotes/ajax_convert_quote.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$quote_id = (int)($_POST['quote_id'] ?? 0);
if ($quote_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de cotización inválido']);
    exit();
}

try {
    $db->beginTransaction();

    // 1. Obtener la cotización con su cliente
    $stmtQuote = $db->prepare("
        SELECT q.*, c.name as client_name, c.whatsapp, c.email 
        FROM quotes q 
        LEFT JOIN clients c ON q.client_id = c.id 
        WHERE q.id = ?
    ");
    $stmtQuote->execute([$quote_id]);
    $quote = $stmtQuote->fetch(PDO::FETCH_ASSOC);

    if (!$quote) {
        throw new Exception('Cotización no encontrada.');
    }

    $client_name = $quote['client_name'] ?: 'Cliente Desconocido';
    $client_id = $quote['client_id'];
    $total = (float)$quote['total'];
    $currency = $quote['currency'] ?: 'USD';

    // 2. Obtener los ítems de la cotización
    $stmtItems = $db->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id ASC");
    $stmtItems->execute([$quote_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // Preparar filas de procesos para la orden de trabajo
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
        'observaciones' => "Generada automáticamente a partir de la Cotización #" . str_pad($quote_id, 4, '0', STR_PAD_LEFT),
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

    // 5. Generar Borrador de Contrato (contracts)
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $contractBody = "<p>Contrato de Prestación de Servicios Comerciales celebrado entre Roma Agencia y <strong>" . htmlspecialchars($client_name) . "</strong>.</p>";
    $contractBody .= "<p><strong>Servicios Acordados:</strong> " . htmlspecialchars($mainService) . "</p>";
    $contractBody .= "<p><strong>Monto Total:</strong> " . htmlspecialchars($currency) . " " . number_format($total, 2) . "</p>";
    $contractBody .= "<p>Generado con base en la Cotización #" . str_pad($quote_id, 4, '0', STR_PAD_LEFT) . ".</p>";

    $stmtInsertContract = $db->prepare("
        INSERT INTO contracts (uuid, client_id, title, body, status, total_amount, created_at) 
        VALUES (?, ?, ?, ?, 'draft', ?, NOW())
    ");
    $stmtInsertContract->execute([
        $uuid,
        $client_id,
        "Contrato - " . $client_name . " (" . $correlativo . ")",
        $contractBody,
        $total
    ]);
    $contract_id = $db->lastInsertId();

    // 6. Actualizar Estado de la Cotización a 'Aceptada'
    $stmtUpdateQuote = $db->prepare("UPDATE quotes SET status = 'Aceptada' WHERE id = ?");
    $stmtUpdateQuote->execute([$quote_id]);

    // 7. Generar Notificación Global para Administradores
    $notifTitle = "¡Cotización #{$quote_id} Convertida a Orden!";
    $notifMsg = "Se generó exitosamente la Orden {$correlativo} y el Proyecto para el cliente {$client_name}.";
    $notifLink = "index.php?module=work_orders&action=edit&id={$work_order_id}";

    $stmtAdmins = $db->query("SELECT id FROM users WHERE role_id = 1");
    $admins = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $adminId) {
        $stmtNotif = $db->prepare("
            INSERT INTO notifications (user_id, title, message, link, type, icon, is_read, created_at) 
            VALUES (?, ?, ?, ?, 'success', 'ph-rocket-launch', 0, NOW())
        ");
        $stmtNotif->execute([$adminId, $notifTitle, $notifMsg, $notifLink]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "¡Cotización convertida con éxito! Se creó la Orden {$correlativo} y su Proyecto correspondiente.",
        'work_order_id' => $work_order_id,
        'correlativo' => $correlativo,
        'project_id' => $project_id,
        'contract_id' => $contract_id,
        'redirect_url' => "index.php?module=work_orders&action=edit&id={$work_order_id}"
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
