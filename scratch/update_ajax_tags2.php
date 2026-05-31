<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\ajax.php';
$content = file_get_contents($file);

$search2 = 'SELECT m.*, u.name as user_name, u.avatar as user_avatar,
                       r.message as reply_message, r.guest_name as reply_guest_name, ru.name as reply_user_name, r.message_type as reply_message_type, r.attachment_name as reply_attachment_name
                FROM chat_messages m';
$replace2 = 'SELECT m.*, u.name as user_name, u.avatar as user_avatar, u.chat_tags as user_tags,
                       r.message as reply_message, r.guest_name as reply_guest_name, ru.name as reply_user_name, r.message_type as reply_message_type, r.attachment_name as reply_attachment_name
                FROM chat_messages m';

$search3 = 'SELECT m.*, u.name as user_name, u.avatar as user_avatar,
                       r.message as reply_message, r.guest_name as reply_guest_name, ru.name as reply_user_name, r.message_type as reply_message_type, r.attachment_name as reply_attachment_name,
                       (SELECT COUNT(*) FROM chat_channel_members WHERE channel_id = m.channel_id AND user_id != m.user_id AND last_read_at >= m.created_at) as read_count,';
$replace3 = 'SELECT m.*, u.name as user_name, u.avatar as user_avatar, u.chat_tags as user_tags,
                       r.message as reply_message, r.guest_name as reply_guest_name, ru.name as reply_user_name, r.message_type as reply_message_type, r.attachment_name as reply_attachment_name,
                       (SELECT COUNT(*) FROM chat_channel_members WHERE channel_id = m.channel_id AND user_id != m.user_id AND last_read_at >= m.created_at) as read_count,';

$content = str_replace($search2, $replace2, $content);
$content = str_replace($search3, $replace3, $content);

file_put_contents($file, $content);
echo "Updated ajax.php for chat_tags in polling";
?>
