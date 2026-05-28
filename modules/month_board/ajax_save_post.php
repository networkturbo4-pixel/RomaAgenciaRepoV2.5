<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/PushHelper.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $month_id = isset($_POST['month_id']) ? (int)$_POST['month_id'] : 0;
    $post_date = !empty($_POST['post_date']) ? $_POST['post_date'] : null;
    $concept = $_POST['concept'] ?? '';
    $copy_text = $_POST['copy_text'] ?? '';
    $platform = isset($_POST['platform']) ? (is_array($_POST['platform']) ? implode(', ', $_POST['platform']) : $_POST['platform']) : '';
    $status = $_POST['status'] ?? 'Borrador';
    $image_link = $_POST['image_link'] ?? '';
    $reference_image_link = $_POST['reference_image_link'] ?? '';

    $post_type = $_POST['post_type'] ?? '';
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $periodicity = $_POST['periodicity'] ?? '';
    $reminder = $_POST['reminder'] ?? '';
    $formats = isset($_POST['formats']) ? json_encode($_POST['formats']) : null;
    $design_brief = $_POST['design_brief'] ?? '';
    $visual_references = isset($_POST['visual_references']) ? json_encode($_POST['visual_references']) : null;
    $variations = isset($_POST['variations']) ? $_POST['variations'] : null;
    $drive_images = !empty($_POST['drive_images']) ? $_POST['drive_images'] : null;

    if (!$month_id || !$concept) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit();
    }


    if ($id > 0) {
        // Save old image link to history if it has changed
        if (!empty($image_link)) {
            $stmtOld = $db->prepare("SELECT image_link FROM month_posts WHERE id = ?");
            $stmtOld->execute([$id]);
            $oldImageLink = $stmtOld->fetchColumn();
            
            if ($oldImageLink && $oldImageLink !== $image_link) {
                // If it's a JSON array (like carousels), we compare the strings.
                $stmtRev = $db->prepare("INSERT INTO post_revisions (post_id, image_link) VALUES (?, ?)");
                $stmtRev->execute([$id, $oldImageLink]);
            }
        }

        $stmt = $db->prepare("UPDATE month_posts SET 
            post_date = ?, concept = ?, copy_text = ?, platform = ?, status = ?, image_link = ?, reference_image_link = ?,
            post_type = ?, end_date = ?, periodicity = ?, reminder = ?, formats = ?, design_brief = ?, visual_references = ?, variations = ?, drive_images = ?
            WHERE id = ?");
        $stmt->execute([
            $post_date, $concept, $copy_text, $platform, $status, $image_link, $reference_image_link,
            $post_type, $end_date, $periodicity, $reminder, $formats, $design_brief, $visual_references, $variations, $drive_images,
            $id
        ]);
        
        // Clear drawing annotations and sticky notes when post reaches final states
        if (in_array($status, ['Aprobado', 'Publicado'])) {
            $stmtClear = $db->prepare("UPDATE month_posts SET drawing_data = NULL, sticky_notes = NULL WHERE id = ?");
            $stmtClear->execute([$id]);
        }
        
        $actionTitle = "Post Actualizado";
        $actionBody = "Se actualizó el post: " . $concept;
        
        echo json_encode(['success' => true]);
    } else {
        $stmt = $db->prepare("INSERT INTO month_posts 
            (month_id, post_date, concept, copy_text, platform, status, image_link, reference_image_link,
             post_type, end_date, periodicity, reminder, formats, design_brief, visual_references, variations, drive_images) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $month_id, $post_date, $concept, $copy_text, $platform, $status, $image_link, $reference_image_link,
            $post_type, $end_date, $periodicity, $reminder, $formats, $design_brief, $visual_references, $variations, $drive_images
        ]);
        $newId = $db->lastInsertId();
        
        $actionTitle = "Nuevo Post en Calendario";
        $actionBody = "Se creó un post: " . $concept;
        
        echo json_encode(['success' => true, 'id' => $newId]);
    }

    // Send push notification to assigned team members
    try {
        $stmtProj = $db->prepare("SELECT p.team_members FROM project_months pm JOIN projects p ON pm.project_id = p.id WHERE pm.id = ?");
        $stmtProj->execute([$month_id]);
        $proj = $stmtProj->fetch();
        if ($proj && !empty($proj['team_members'])) {
            $assignedIds = json_decode($proj['team_members'], true) ?: [];
            $assignedIds = array_values(array_diff($assignedIds, [$_SESSION['user_id']]));
            if (!empty($assignedIds)) {
                PushHelper::sendPushNotification($db, $assignedIds, $actionTitle, $actionBody, "index.php?module=calendar", "calendar", ['module' => 'calendar']);
            }
        }
    } catch(Throwable $e) {}

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Error de servidor: ' . $e->getMessage()]);
}
?>
