<?php require_once 'config/database.php'; $db = (new Database())->getConnection(); $stmt = $db->query('SELECT setting_key, setting_value FROM settings'); print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
