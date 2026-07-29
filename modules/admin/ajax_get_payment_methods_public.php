<?php
// modules/admin/ajax_get_payment_methods_public.php
// Public endpoint - no auth required
require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    $db = (new Database())->getConnection();
    $stmt = $db->query("SELECT label, code, image_url FROM payment_methods ORDER BY sort_order ASC, id ASC");
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'methods' => $methods]);
} catch (Exception $e) {
    echo json_encode(['success' => true, 'methods' => []]);
}
