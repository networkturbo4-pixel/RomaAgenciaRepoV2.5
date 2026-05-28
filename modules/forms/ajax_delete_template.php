<?php
// modules/forms/ajax_delete_template.php
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'No autorizado']); exit(); }

$id = $_POST['id'] ?? '';
if (empty($id)) { echo json_encode(['success'=>false,'error'=>'ID requerido']); exit(); }

try {
    $db->prepare("DELETE FROM form_templates WHERE id=?")->execute([$id]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
