<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\ajax.php';
$content = file_get_contents($file);

$search1 = '            // Membership verification
            $stmtMember = $db->prepare("SELECT 1 FROM chat_channel_members WHERE channel_id = ? AND user_id = ?");
            $stmtMember->execute([$channelId, $userId]);
            if (!$stmtMember->fetch()) {
                echo json_encode([\'success\' => false, \'error\' => \'No eres miembro de este canal\']);
                exit();
            }';

$replace1 = '            // Channel verification
            $stmtCh = $db->prepare("SELECT is_secret, secret_password FROM chat_channels WHERE id = ?");
            $stmtCh->execute([$channelId]);
            $chInfo = $stmtCh->fetch(PDO::FETCH_ASSOC);

            if ($chInfo && $chInfo[\'is_secret\'] == 1) {
                $pwd = $_POST[\'password\'] ?? \'\';
                if ($pwd !== $chInfo[\'secret_password\']) {
                    echo json_encode([\'success\' => false, \'error\' => \'Contraseña incorrecta\']);
                    exit();
                }
            }

            // Membership verification
            $stmtMember = $db->prepare("SELECT 1 FROM chat_channel_members WHERE channel_id = ? AND user_id = ?");
            $stmtMember->execute([$channelId, $userId]);
            if (!$stmtMember->fetch()) {
                echo json_encode([\'success\' => false, \'error\' => \'No eres miembro de este canal\']);
                exit();
            }';

$content = str_replace($search1, $replace1, $content);
file_put_contents($file, $content);
echo "Updated ajax.php for secret channel check in get_messages";
?>
