<?php
// ajax/ajax_get_month_posts.php
error_reporting(0);
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_GET['month_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing month_id']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $monthId = intval($_GET['month_id']);
    
    // Get month details
    $monthStmt = $db->prepare("SELECT pm.month, pm.year, wo.brand_name FROM project_months pm JOIN projects p ON pm.project_id = p.id JOIN work_orders wo ON p.work_order_id = wo.id WHERE pm.id = ?");
    $monthStmt->execute([$monthId]);
    $monthInfo = $monthStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get posts with ALL visual fields
    $stmt = $db->prepare("SELECT id, post_date, concept, copy_text, platform, status, image_link, reference_image_link FROM month_posts WHERE month_id = ? ORDER BY CASE WHEN post_date IS NULL OR post_date = '0000-00-00' THEN 1 ELSE 0 END ASC, post_date ASC, id ASC");
    $stmt->execute([$monthId]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'month_info' => $monthInfo,
        'posts' => $posts
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
