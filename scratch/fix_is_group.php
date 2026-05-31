<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($file);

$search1 = "const isGroup = ch.type === 'group';";
$replace1 = "const isGroup = ch.type === 'group' || ch.type === 'voice' || ch.type === 'video';";

$content = str_replace($search1, $replace1, $content);
file_put_contents($file, $content);
echo "Fixed isGroup boolean flag in chat.js\n";
?>
