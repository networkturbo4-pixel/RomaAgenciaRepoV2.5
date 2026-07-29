<?php
// ajax/social_publish.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../config/database.php';
$db = new Database();
$pdo = $db->getConnection();

$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$action_type = isset($_POST['action_type']) ? $_POST['action_type'] : 'publish_now';

if (!$post_id) {
    echo json_encode(['success' => false, 'error' => 'Faltan parámetros (post_id)']);
    exit();
}

try {
    // 1. Fetch Post Data
    $stmt = $pdo->prepare("SELECT * FROM month_posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        echo json_encode(['success' => false, 'error' => 'Publicación no encontrada']);
        exit();
    }

    // 2. Fetch Project and Client to find Social Accounts
    $stmtProj = $pdo->prepare("
        SELECT p.id as project_id, cb.client_id 
        FROM project_months pm
        JOIN projects p ON pm.project_id = p.id
        JOIN work_orders w ON p.work_order_id = w.id
        JOIN client_brands cb ON w.brand_name = cb.name
        WHERE pm.id = ?
    ");
    $stmtProj->execute([$post['month_id']]);
    $proj = $stmtProj->fetch(PDO::FETCH_ASSOC);

    if (!$proj) {
        echo json_encode(['success' => false, 'error' => 'Proyecto/Cliente no encontrado para este mes']);
        exit();
    }

    $client_id = $proj['client_id'];

    // 3. Find connected accounts for the requested platforms
    $requested_platforms = [];
    if (strpos(strtolower($post['platform']), 'facebook') !== false) $requested_platforms[] = 'facebook';
    if (strpos(strtolower($post['platform']), 'instagram') !== false) $requested_platforms[] = 'instagram';
    if (strpos(strtolower($post['platform']), 'tiktok') !== false) $requested_platforms[] = 'tiktok';

    if (empty($requested_platforms)) {
        echo json_encode(['success' => false, 'error' => 'No hay plataformas sociales válidas seleccionadas.']);
        exit();
    }

    $stmtAcc = $pdo->prepare("SELECT * FROM client_social_accounts WHERE client_id = ?");
    $stmtAcc->execute([$client_id]);
    $accounts = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);
    
    $connected_platforms = [];
    foreach ($accounts as $acc) {
        $connected_platforms[$acc['platform']] = $acc;
    }

    // Validation: Check if the client has connected the requested platforms
    foreach ($requested_platforms as $rp) {
        if (!isset($connected_platforms[$rp])) {
            echo json_encode(['success' => false, 'error' => "La plataforma $rp no está vinculada para este cliente."]);
            exit();
        }
    }

    // 4. Sandbox Mockup Logic
    if ($action_type === 'publish_now') {
        // SIMULATE API CALL
        sleep(2); // simulate latency
        $fake_api_id = "post_" . md5(uniqid());
        
        $stmtUpdate = $pdo->prepare("UPDATE month_posts SET social_status = 'published', social_post_id = ? WHERE id = ?");
        $stmtUpdate->execute([$fake_api_id, $post_id]);
        
        echo json_encode(['success' => true, 'message' => '¡Publicado con éxito en Sandbox!']);
    } else {
        // SCHEDULE
        $stmtUpdate = $pdo->prepare("UPDATE month_posts SET social_status = 'scheduled' WHERE id = ?");
        $stmtUpdate->execute([$post_id]);
        
        echo json_encode(['success' => true, 'message' => '¡Programado con éxito! Se publicará en la fecha indicada.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
