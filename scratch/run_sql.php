<?php
require_once 'config/database.php';
$dbClass = new Database();
$db = $dbClass->getConnection();
$sql = file_get_contents('actualizacion_permisos.sql');
$db->exec($sql);
echo "Done";
