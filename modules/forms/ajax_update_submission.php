<?php
// modules/forms/ajax_update_submission.php
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'No autorizado']); exit(); }

$id = $_POST['id'] ?? '';
$status = $_POST['status'] ?? '';
if (empty($id) || empty($status)) { echo json_encode(['success'=>false,'error'=>'Datos incompletos']); exit(); }

try {
    $db->prepare("UPDATE form_submissions SET status=? WHERE id=?")->execute([$status, $id]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
