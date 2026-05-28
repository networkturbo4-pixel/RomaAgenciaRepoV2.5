<?php
$db = new PDO('mysql:host=localhost;dbname=saas_cesar_db', 'root', '');
$stmt = $db->query("SHOW TABLES");
$tables = [];
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}
echo implode("\n", $tables);
