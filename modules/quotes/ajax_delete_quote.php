<?php
// modules/quotes/ajax_delete_quote.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            throw new Exception('ID de cotización inválido.');
        }

        $db->beginTransaction();

        // Delete related gantt tasks
        $stmt = $db->prepare("DELETE FROM quote_gantt_tasks WHERE quote_id = ?");
        $stmt->execute([$id]);

        // Delete related items
        $stmt = $db->prepare("DELETE FROM quote_items WHERE quote_id = ?");
        $stmt->execute([$id]);

        // Delete the quote itself
        $stmt = $db->prepare("DELETE FROM quotes WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Cotización no encontrada.');
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Cotización eliminada exitosamente.']);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
