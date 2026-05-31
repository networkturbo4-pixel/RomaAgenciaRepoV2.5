<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('DESCRIBE chat_reactions');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
