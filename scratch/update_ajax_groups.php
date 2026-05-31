<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\ajax.php';
$content = file_get_contents($file);

$search1 = '            $name = trim($_POST[\'name\'] ?? \'\');
            $description = trim($_POST[\'description\'] ?? \'\');
            $isPublic = (int)($_POST[\'is_public\'] ?? 0);
            $members = json_decode($_POST[\'members\'] ?? \'[]\', true);

            if (empty($name)) {';

$replace1 = '            $name = trim($_POST[\'name\'] ?? \'\');
            $description = trim($_POST[\'description\'] ?? \'\');
            $isPublic = (int)($_POST[\'is_public\'] ?? 0);
            $requiresApproval = (int)($_POST[\'requires_approval\'] ?? 0);
            $isSecret = (int)($_POST[\'is_secret\'] ?? 0);
            $secretPassword = trim($_POST[\'secret_password\'] ?? \'\');
            $members = json_decode($_POST[\'members\'] ?? \'[]\', true);

            if (empty($name)) {';

$search2 = '            $stmt = $db->prepare("INSERT INTO chat_channels (name, type, description, created_by, is_public, public_token) VALUES (?, \'group\', ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $userId, $isPublic, $token]);';

$replace2 = '            $stmt = $db->prepare("INSERT INTO chat_channels (name, type, description, created_by, is_public, public_token, requires_approval, is_secret, secret_password) VALUES (?, \'group\', ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $userId, $isPublic, $token, $requiresApproval, $isSecret, $secretPassword]);';

$search3 = '            $isPublic = (int)($_POST[\'is_public\'] ?? 0);
            $members = json_decode($_POST[\'members\'] ?? \'[]\', true);
            $channelId = (int)($_POST[\'channel_id\'] ?? 0);';

$replace3 = '            $isPublic = (int)($_POST[\'is_public\'] ?? 0);
            $requiresApproval = (int)($_POST[\'requires_approval\'] ?? 0);
            $isSecret = (int)($_POST[\'is_secret\'] ?? 0);
            $secretPassword = trim($_POST[\'secret_password\'] ?? \'\');
            $members = json_decode($_POST[\'members\'] ?? \'[]\', true);
            $channelId = (int)($_POST[\'channel_id\'] ?? 0);';

$search4 = '            $stmt = $db->prepare("UPDATE chat_channels SET name = ?, description = ?, is_public = ? $avatarSql $tokenSql WHERE id = ?");
            $stmt->execute($params);';

$replace4 = '            $stmt = $db->prepare("UPDATE chat_channels SET name = ?, description = ?, is_public = ?, requires_approval = ?, is_secret = ?, secret_password = ? $avatarSql $tokenSql WHERE id = ?");
            
            // Insert requiresApproval, isSecret, secretPassword after isPublic
            array_splice($params, 3, 0, [$requiresApproval, $isSecret, $secretPassword]);
            
            $stmt->execute($params);';

$content = str_replace($search1, $replace1, $content);
$content = str_replace($search2, $replace2, $content);
$content = str_replace($search3, $replace3, $content);
$content = str_replace($search4, $replace4, $content);

file_put_contents($file, $content);
echo "Updated ajax.php for group options";
?>
