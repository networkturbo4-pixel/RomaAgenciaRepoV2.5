<?php
$db = new PDO('mysql:host=localhost;dbname=saas_cesar_db', 'root', '');
$stmt = $db->query("DESCRIBE push_subscriptions");
$tables = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $tables[] = $row['Field'] . ' ' . $row['Type'];
}
echo implode("\n", $tables);
