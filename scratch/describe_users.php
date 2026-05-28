<?php
$db = new PDO('mysql:host=localhost;dbname=saas_cesar_db','root','');
$s = $db->query('DESCRIBE users');
while($r=$s->fetch(PDO::FETCH_ASSOC)){
    echo $r['Field'].' - '.$r['Type']."\n";
}
echo "\n--- SHOW TABLES ---\n";
$s2 = $db->query('SHOW TABLES');
while($r2=$s2->fetch(PDO::FETCH_NUM)){
    echo $r2[0]."\n";
}
echo "\n--- SAMPLE USERS ---\n";
$s3 = $db->query('SELECT id, name, email, role_id FROM users LIMIT 5');
while($r3=$s3->fetch(PDO::FETCH_ASSOC)){
    echo json_encode($r3)."\n";
}
