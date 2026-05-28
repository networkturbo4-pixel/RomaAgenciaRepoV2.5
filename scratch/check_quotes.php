<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();

echo "=== TABLA: quotes ===\n";
try {
    $cols = $db->query("DESCRIBE quotes")->fetchAll(PDO::FETCH_ASSOC);
    foreach($cols as $c) {
        echo $c['Field'] . ' | ' . $c['Type'] . ' | ' . $c['Null'] . ' | ' . $c['Key'] . ' | ' . $c['Default'] . ' | ' . $c['Extra'] . "\n";
    }
} catch(Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}

echo "\n=== TABLA: quote_items ===\n";
try {
    $cols = $db->query("DESCRIBE quote_items")->fetchAll(PDO::FETCH_ASSOC);
    foreach($cols as $c) {
        echo $c['Field'] . ' | ' . $c['Type'] . ' | ' . $c['Null'] . ' | ' . $c['Key'] . ' | ' . $c['Default'] . ' | ' . $c['Extra'] . "\n";
    }
} catch(Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}

echo "\n=== TABLA: quote_gantt_tasks ===\n";
try {
    $cols = $db->query("DESCRIBE quote_gantt_tasks")->fetchAll(PDO::FETCH_ASSOC);
    foreach($cols as $c) {
        echo $c['Field'] . ' | ' . $c['Type'] . ' | ' . $c['Null'] . ' | ' . $c['Key'] . ' | ' . $c['Default'] . ' | ' . $c['Extra'] . "\n";
    }
} catch(Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}

echo "\n=== QUOTES EXISTENTES ===\n";
$count = $db->query('SELECT COUNT(*) FROM quotes')->fetchColumn();
echo "Total quotes: $count\n";
$q = $db->query('SELECT q.id, q.status, q.total, q.subtotal, c.name as client FROM quotes q LEFT JOIN clients c ON q.client_id = c.id ORDER BY q.id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
print_r($q);

echo "\n=== ARCHIVO ajax_delete_quote.php EXISTE? ===\n";
$file = __DIR__ . '/../modules/quotes/ajax_delete_quote.php';
echo file_exists($file) ? "SI existe" : "NO existe (PROBLEMA!)";
echo "\n";
