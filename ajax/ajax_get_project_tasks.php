<?php
// ajax/ajax_get_project_tasks.php
error_reporting(0);
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_GET['project_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing project_id']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    
    $projectId = intval($_GET['project_id']);
    
    // Get project services/tasks
    $stmt = $db->prepare("SELECT id, title, status, start_date, due_date FROM project_services WHERE project_id = ? ORDER BY id ASC");
    $stmt->execute([$projectId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Default kanban structure if no tasks exist
    $kanban = [
        'Pendiente' => [],
        'En Progreso' => [],
        'Completado' => []
    ];
    
    foreach ($tasks as $task) {
        $status = $task['status'] ?: 'Pendiente';
        
        // Map any unusual status to the basic 3 columns
        if (in_array($status, ['Backlog', 'To Do', 'Pendiente'])) {
            $col = 'Pendiente';
        } else if (in_array($status, ['In Progress', 'En Progreso', 'En Revisión'])) {
            $col = 'En Progreso';
        } else if (in_array($status, ['Done', 'Completado', 'Aprobado'])) {
            $col = 'Completado';
        } else {
            $col = 'Pendiente'; // default
        }
        
        $kanban[$col][] = $task;
    }
    
    echo json_encode([
        'success' => true,
        'kanban' => $kanban
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
