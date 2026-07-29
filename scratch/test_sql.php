<?php
require 'c:\xampp\htdocs\CESARMENDOZA\config\database.php';
$db = (new Database())->getConnection();
$user_id = 3;
$is_admin = false;

$whereClause = "1=1 AND w.folder_id IS NULL";
$params = [];

$sql = "SELECT w.id, w.title, w.created_by, w.created_at, w.updated_at, w.folder_id, w.tags, w.thumbnail, w.profile_pic, u.name as creator_name, u.avatar as creator_avatar 
        FROM whiteboards w 
        LEFT JOIN users u ON w.created_by = u.id 
        LEFT JOIN whiteboard_users wu ON w.id = wu.whiteboard_id AND wu.user_id = ?
        WHERE $whereClause AND (w.created_by = ? OR wu.user_id IS NOT NULL OR ?)
        ORDER BY w.updated_at DESC 
        LIMIT 100";

$params[] = $user_id; // First ? (in ON clause)
$params[] = $user_id; // Second ? (w.created_by)
$params[] = $is_admin ? 1 : 0; // Third ?

echo "SQL: $sql\n";
print_r($params);

$stmt = $db->prepare($sql);
$stmt->execute($params);
$whiteboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($whiteboards);
