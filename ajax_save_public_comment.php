<?php
// ajax_save_public_comment.php
header('Content-Type: application/json');
require_once 'config/database.php';
require_once 'includes/PushHelper.php';

try {
    $db = (new Database())->getConnection();

    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $comment_text = $_POST['comment_text'] ?? '';

    if ($post_id <= 0 || empty($comment_text)) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit();
    }

    $image_link = '';

    // Manejo de la subida de imagen
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/comments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = time() . '_' . basename($_FILES['image_file']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
            $image_link = $targetPath;
        }
    }

    $comment_phase = $_POST['comment_phase'] ?? 'Parrilla Final';

    // Insertar el comentario
    $stmt = $db->prepare("INSERT INTO post_comments (post_id, comment_text, image_link, status, phase) VALUES (?, ?, ?, 'Pendiente', ?)");
    $stmt->execute([$post_id, $comment_text, $image_link, $comment_phase]);

    // Actualizar el estado del post a 'En Revisión (Con Cambios)'
    $stmtUpdate = $db->prepare("UPDATE month_posts SET status = 'En Revisión (Con Cambios)' WHERE id = ?");
    $stmtUpdate->execute([$post_id]);

    // Notify team members
    try {
        $stmtProj = $db->prepare("SELECT p.team_members, mp.concept FROM month_posts mp JOIN project_months pm ON mp.month_id = pm.id JOIN projects p ON pm.project_id = p.id WHERE mp.id = ?");
        $stmtProj->execute([$post_id]);
        $proj = $stmtProj->fetch();
        if ($proj && !empty($proj['team_members'])) {
            $assignedIds = json_decode($proj['team_members'], true) ?: [];
            if (!empty($assignedIds)) {
                PushHelper::sendPushNotification($db, $assignedIds, "Comentario de Cliente", "El cliente dej� un comentario en '{$proj['concept']}': {$comment_text}", "index.php?module=calendar", "calendar", ['module' => 'calendar']);
            }
        }
    } catch(Throwable $e) {}

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

