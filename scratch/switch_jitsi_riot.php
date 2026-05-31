<?php
$fileIndex = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\index.php';
$contentIndex = file_get_contents($fileIndex);
$contentIndex = str_replace('meet.ffmuc.net', 'jitsi.riot.im', $contentIndex);
file_put_contents($fileIndex, $contentIndex);

$fileJs = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$contentJs = file_get_contents($fileJs);
$contentJs = str_replace("const domain = 'meet.ffmuc.net';", "const domain = 'jitsi.riot.im';", $contentJs);
file_put_contents($fileJs, $contentJs);

echo "Switched to free Jitsi server (jitsi.riot.im)\n";
?>
