<?php
require 'c:/xampp/htdocs/CESARMENDOZA/config/database.php';
$db = (new Database())->getConnection();

// Add 'mensajes' to permissions
$db->query("INSERT IGNORE INTO permissions (name, description) VALUES ('mensajes', 'Acceso al módulo de mensajes')");

// Assign to all roles that have 'dashboard' (or just all roles for now)
$db->query("INSERT IGNORE INTO role_permissions (role_id, permission_id) 
            SELECT r.id, p.id 
            FROM roles r 
            CROSS JOIN permissions p 
            WHERE p.name = 'mensajes'");

echo 'Permisos de mensajes agregados a todos los roles.';
