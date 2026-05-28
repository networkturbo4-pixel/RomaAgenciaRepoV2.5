<?php
$postId = 2; // test with a real ID
$notes = "testing notes";
$tasks = '[{"text":"task","done":false}]';

require_once 'c:/xampp/htdocs/CESARMENDOZA/config/database.php';
$db = (new Database())->getConnection();

try {
    $stmt = $db->prepare("UPDATE month_posts SET presenter_notes = ?, agenda_tasks = ? WHERE id = ?");
    $res = $stmt->execute([$notes, $tasks, $postId]);
    echo "Result: " . ($res ? "success" : "failed");
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
