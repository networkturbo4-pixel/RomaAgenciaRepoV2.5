<?php
// modules/project_board/ajax_link_form.php

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no válido']);
    exit();
}

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$submissionId = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;

if (!$projectId || !$submissionId) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Check if submission is already linked
    $stmtCheck = $db->prepare("SELECT id FROM project_attachments WHERE project_id = ? AND submission_id = ? AND type = 'form'");
    $stmtCheck->execute([$projectId, $submissionId]);
    if ($stmtCheck->fetchColumn()) {
        throw new Exception("Este formulario ya está vinculado a este proyecto.");
    }

    $stmtInsert = $db->prepare("
        INSERT INTO project_attachments (project_id, type, submission_id) 
        VALUES (?, 'form', ?)
    ");
    $stmtInsert->execute([$projectId, $submissionId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
