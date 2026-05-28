<?php
// modules/calendar/ajax_delete_project.php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
    exit();
}

try {
    $db->beginTransaction();

    // Obtener los meses del proyecto
    $stmt = $db->prepare("SELECT id FROM project_months WHERE project_id = ?");
    $stmt->execute([$id]);
    $months = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($months)) {
        // Obtener los IDs de posts asociados a esos meses
        $in = str_repeat('?,', count($months) - 1) . '?';
        $stmtPostIds = $db->prepare("SELECT id FROM month_posts WHERE month_id IN ($in)");
        $stmtPostIds->execute($months);
        $postIds = $stmtPostIds->fetchAll(PDO::FETCH_COLUMN);

        // Eliminar comentarios asociados a esos posts
        if (!empty($postIds)) {
            $inPosts = str_repeat('?,', count($postIds) - 1) . '?';
            $stmtComments = $db->prepare("DELETE FROM post_comments WHERE post_id IN ($inPosts)");
            $stmtComments->execute($postIds);
        }

        // Eliminar posts asociados a esos meses
        $stmtPosts = $db->prepare("DELETE FROM month_posts WHERE month_id IN ($in)");
        $stmtPosts->execute($months);
        
        // Eliminar los meses
        $stmtDeleteMonths = $db->prepare("DELETE FROM project_months WHERE project_id = ?");
        $stmtDeleteMonths->execute([$id]);
    }

    // Finalmente, eliminar el proyecto
    $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$id]);

    $db->commit();

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
