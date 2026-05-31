<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($file);

$search1 = "chatInputArea.style.display = 'flex';";
$replace1 = "chatInputArea.style.display = 'block';";

$content = str_replace($search1, $replace1, $content);
file_put_contents($file, $content);
echo "Fixed chatInputArea display\n";
?>
