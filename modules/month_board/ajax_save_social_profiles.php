<?php
error_reporting(0);
ini_set('display_errors', 0);
require "../../config/database.php";
$db = new Database();
$conn = $db->getConnection();

$id = $_POST['month_id'] ?? 0;
$data = $_POST['data'] ?? '{}';

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE project_months SET social_profiles_data = ? WHERE id = ?");
    if ($stmt->execute([$data, $id])) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => "Database error"]);
    }
} else {
    // If ID is 0, it might be because post_max_size was exceeded and $_POST was cleared.
    $content_length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $error_msg = "ID inválido o no proporcionado.";
    if ($content_length > 0 && empty($_POST)) {
        $error_msg = "El archivo o los datos son demasiado grandes para ser guardados.";
    }
    echo json_encode(["success" => false, "error" => $error_msg]);
}
