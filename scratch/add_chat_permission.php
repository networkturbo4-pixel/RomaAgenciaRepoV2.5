<?php
$db = new PDO('mysql:host=localhost;dbname=saas_cesar_db','root','');

// Add chat permission for admin and all roles that have other permissions
$stmt = $db->query("SELECT DISTINCT role_id FROM role_permissions");
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($roles as $roleId) {
    $check = $db->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND module_name = 'chat'");
    $check->execute([$roleId]);
    if ($check->fetchColumn() == 0) {
        $db->prepare("INSERT INTO role_permissions (role_id, module_name) VALUES (?, 'chat')")->execute([$roleId]);
        echo "Added chat permission for role $roleId\n";
    }
}
echo "Done!\n";
