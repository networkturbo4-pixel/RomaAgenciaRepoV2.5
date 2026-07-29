<?php
$file = 'c:/xampp/htdocs/CESARMENDOZA/modules/chat/ajax.php';
$content = file_get_contents($file);

$newActions = "
        // ── SAVE DRIVE FOLDER ──
        case 'save_drive_folder':
            \$channelId = (int)(\$_POST['channel_id'] ?? 0);
            \$folderId = \$_POST['folder_id'] ?? '';
            if (\$channelId > 0 && !empty(\$folderId)) {
                \$db->prepare(\"UPDATE chat_channels SET drive_folder_id = ? WHERE id = ?\")->execute([\$folderId, \$channelId]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid data']);
            }
            break;
";

// Insert before the last default or break of the main switch. We can just insert it before "default:"
$content = str_replace("        default:", $newActions . "\n        default:", $content);

file_put_contents($file, $content);
echo "Actions appended.";
