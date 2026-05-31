<?php
$db = new PDO('mysql:host=localhost;dbname=saas_cesar_db;charset=utf8mb4', 'root', '');
$db->exec("UPDATE users SET chat_tags = 'Admin,Soporte' WHERE id = 1");
$db->exec("UPDATE users SET chat_tags = 'Desarrollador' WHERE id = 2");
$db->exec("UPDATE users SET chat_tags = 'Moderador,VIP' WHERE id = 3");
echo "Tags added to users 1, 2, 3\n";
?>
