<?php
$db = new PDO('mysql:host=localhost;dbname=saas_cesar_db','root','');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== CLIENTS TABLE ===\n";
$s = $db->query('DESCRIBE clients');
while($r=$s->fetch(PDO::FETCH_ASSOC)) echo $r['Field'].' - '.$r['Type']."\n";

echo "\n=== QUOTES TABLE ===\n";
$s = $db->query('DESCRIBE quotes');
while($r=$s->fetch(PDO::FETCH_ASSOC)) echo $r['Field'].' - '.$r['Type']."\n";

echo "\n=== SERVICES TABLE ===\n";
$s = $db->query('DESCRIBE services');
while($r=$s->fetch(PDO::FETCH_ASSOC)) echo $r['Field'].' - '.$r['Type']."\n";

echo "\n=== PROJECTS (Calendar) TABLE ===\n";
$s = $db->query('DESCRIBE projects');
while($r=$s->fetch(PDO::FETCH_ASSOC)) echo $r['Field'].' - '.$r['Type']."\n";

echo "\n=== PROJECT_MONTHS TABLE ===\n";
$s = $db->query('DESCRIBE project_months');
while($r=$s->fetch(PDO::FETCH_ASSOC)) echo $r['Field'].' - '.$r['Type']."\n";

echo "\n=== MONTH_POSTS TABLE ===\n";
$s = $db->query('DESCRIBE month_posts');
while($r=$s->fetch(PDO::FETCH_ASSOC)) echo $r['Field'].' - '.$r['Type']."\n";

echo "\n=== SERVICE_CATEGORIES TABLE ===\n";
$s = $db->query('DESCRIBE service_categories');
while($r=$s->fetch(PDO::FETCH_ASSOC)) echo $r['Field'].' - '.$r['Type']."\n";

echo "\n=== SAMPLE CLIENT ===\n";
$s = $db->query('SELECT * FROM clients LIMIT 1');
$r = $s->fetch(PDO::FETCH_ASSOC);
if($r) echo json_encode($r, JSON_PRETTY_PRINT)."\n";

echo "\n=== SAMPLE QUOTE ===\n";
$s = $db->query('SELECT * FROM quotes LIMIT 1');
$r = $s->fetch(PDO::FETCH_ASSOC);
if($r) echo json_encode($r, JSON_PRETTY_PRINT)."\n";

echo "\n=== SAMPLE SERVICE ===\n";
$s = $db->query('SELECT * FROM services LIMIT 1');
$r = $s->fetch(PDO::FETCH_ASSOC);
if($r) echo json_encode($r, JSON_PRETTY_PRINT)."\n";
