<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\ajax.php';
$content = file_get_contents($file);

$search1 = '$stmt = $db->prepare("SELECT user_id, created_at FROM chat_messages WHERE id = ?");';
$replace1 = '$stmt = $db->prepare("SELECT user_id, message, created_at FROM chat_messages WHERE id = ?");';

$search2 = '$db->prepare("UPDATE chat_messages SET message = ?, is_edited = 1 WHERE id = ?")->execute([$newText, $messageId]);
            echo json_encode([\'success\' => true]);
            break;';

$replace2 = '// Save old message to history
            if ($msg[\'message\'] !== $newText) {
                $db->prepare("INSERT INTO chat_message_edits (message_id, old_message) VALUES (?, ?)")->execute([$messageId, $msg[\'message\']]);
            }

            $db->prepare("UPDATE chat_messages SET message = ?, is_edited = 1 WHERE id = ?")->execute([$newText, $messageId]);
            echo json_encode([\'success\' => true]);
            break;

        // 📝📝 GET EDIT HISTORY 📝📝
        case \'get_message_edits\':
            $messageId = (int)($_POST[\'message_id\'] ?? 0);
            if (!$messageId) { echo json_encode([\'success\' => false, \'error\' => \'ID inválido\']); exit(); }

            $stmt = $db->prepare("SELECT old_message, edited_at FROM chat_message_edits WHERE message_id = ? ORDER BY edited_at DESC");
            $stmt->execute([$messageId]);
            $edits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([\'success\' => true, \'edits\' => $edits]);
            break;';

$content = str_replace($search1, $replace1, $content);
$content = str_replace($search2, $replace2, $content);

file_put_contents($file, $content);
echo "Updated ajax.php";
?>
