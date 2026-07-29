<?php
require "../../config/database.php";
$db = new Database();
$conn = $db->getConnection();

$id = $_GET['month_id'] ?? 0;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT social_profiles_data FROM project_months WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['social_profiles_data'])) {
        echo $row['social_profiles_data'];
    } else {
        echo '{}';
    }
} else {
    echo '{}';
}
