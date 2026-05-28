<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();
print_r($db->query('SELECT id, client_id, total FROM quotes ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC));
