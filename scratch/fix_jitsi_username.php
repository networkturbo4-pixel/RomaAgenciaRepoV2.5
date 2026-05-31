<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($file);

$search1 = "displayName: window.chatUserName || 'Usuario'";
$replace1 = "displayName: typeof CURRENT_USER_NAME !== 'undefined' ? CURRENT_USER_NAME : 'Usuario'";

$content = str_replace($search1, $replace1, $content);
file_put_contents($file, $content);
echo "Fixed username in Jitsi\n";
?>
