<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\ajax.php';
$content = file_get_contents($file);

$search1 = 'SELECT m.*, u.name as user_name, u.avatar as user_avatar,
                       r.message as reply_message';
$replace1 = 'SELECT m.*, u.name as user_name, u.avatar as user_avatar, u.chat_tags as user_tags,
                       r.message as reply_message';

$content = str_replace($search1, $replace1, $content);

file_put_contents($file, $content);
echo "Updated ajax.php for chat_tags";
?>
