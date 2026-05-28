<?php
// modules/project_board/ajax_get_project_info.php

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';

header('Content-Type: application/json');

$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if (!$projectId) {
    echo json_encode(['success' => false, 'error' => 'ID de proyecto no válido']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Get forms (submissions)
    $stmtForms = $db->prepare("
        SELECT pa.id, s.id as submission_id, s.correlativo, s.respondent_name, t.title as form_title, pa.created_at
        FROM project_attachments pa
        JOIN form_submissions s ON pa.submission_id = s.id
        JOIN form_templates t ON s.template_id = t.id
        WHERE pa.project_id = ? AND pa.type = 'form'
        ORDER BY pa.created_at DESC
    ");
    $stmtForms->execute([$projectId]);
    $forms = $stmtForms->fetchAll(PDO::FETCH_ASSOC);

    // Get PDFs
    $stmtPdfs = $db->prepare("
        SELECT id, file_name, file_path as url, created_at
        FROM project_attachments
        WHERE project_id = ? AND type = 'pdf'
        ORDER BY created_at DESC
    ");
    $stmtPdfs->execute([$projectId]);
    $pdfs = $stmtPdfs->fetchAll(PDO::FETCH_ASSOC);

    // Get available forms to link (excluding already linked ones)
    $stmtAvailable = $db->prepare("
        SELECT s.id, s.correlativo, s.respondent_name, t.title as form_title
        FROM form_submissions s 
        JOIN form_templates t ON s.template_id = t.id
        WHERE s.id NOT IN (
            SELECT submission_id FROM project_attachments WHERE project_id = ? AND type = 'form' AND submission_id IS NOT NULL
        )
        ORDER BY s.created_at DESC
    ");
    $stmtAvailable->execute([$projectId]);
    $availableForms = $stmtAvailable->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'forms' => $forms,
        'pdfs' => $pdfs,
        'available_forms' => $availableForms
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
