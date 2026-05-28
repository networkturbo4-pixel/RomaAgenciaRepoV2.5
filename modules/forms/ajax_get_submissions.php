<?php
// modules/forms/ajax_get_submissions.php
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'No autorizado']); exit(); }

$template_id = $_GET['template_id'] ?? '';
if (empty($template_id)) { echo json_encode(['success'=>false,'error'=>'ID requerido']); exit(); }

$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM form_submissions WHERE template_id = ?";
$params = [$template_id];

if ($status_filter) { $sql .= " AND status = ?"; $params[] = $status_filter; }
if ($search) { $sql .= " AND (respondent_name LIKE ? OR respondent_email LIKE ? OR correlativo LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$sql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);

echo json_encode(['success'=>true, 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
