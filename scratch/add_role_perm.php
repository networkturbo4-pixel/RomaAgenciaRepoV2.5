<?php
require 'c:/xampp/htdocs/CESARMENDOZA/config/database.php';
$db = (new Database())->getConnection();
$db->query("INSERT IGNORE INTO role_permissions (role_id, module_name) SELECT id, 'mensajes' FROM roles");
echo 'Permisos de mensajes asignados a roles.';
