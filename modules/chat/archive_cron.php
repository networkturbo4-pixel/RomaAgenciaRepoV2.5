<?php
/**
 * Chat Archive Cron Job
 * Archives messages older than 6 months to a separate table.
 * 
 * Usage: php modules/chat/archive_cron.php
 * Recommended: Run daily via cron or Windows Task Scheduler
 */

require_once __DIR__ . '/../../config/database.php';

$archiveMonths = 6;
$cutoffDate = date('Y-m-d H:i:s', strtotime("-{$archiveMonths} months"));
$logFile = __DIR__ . '/../../logs/chat_archive.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) mkdir($logDir, 0755, true);

function logMsg($file, $msg) {
    file_put_contents($file, date('Y-m-d H:i:s') . " | " . $msg . "\n", FILE_APPEND);
}

try {
    logMsg($logFile, "Archive cron started. Cutoff date: {$cutoffDate}");

    // Create archive table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS chat_messages_archive LIKE chat_messages");

    // Count messages to archive
    $stmt = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE created_at < ?");
    $stmt->execute([$cutoffDate]);
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        logMsg($logFile, "No messages to archive. Exiting.");
        exit(0);
    }

    logMsg($logFile, "Found {$count} messages to archive.");

    // Copy messages to archive table
    $stmt = $db->prepare("INSERT INTO chat_messages_archive SELECT * FROM chat_messages WHERE created_at < ?");
    $stmt->execute([$cutoffDate]);
    $archived = $stmt->rowCount();

    // Move attachments to archive folder
    $archiveDir = __DIR__ . '/../../uploads/chat_archive/';
    if (!is_dir($archiveDir)) mkdir($archiveDir, 0755, true);

    $stmt = $db->prepare("SELECT attachment FROM chat_messages WHERE created_at < ? AND attachment IS NOT NULL AND attachment != ''");
    $stmt->execute([$cutoffDate]);
    $attachments = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $movedFiles = 0;

    foreach ($attachments as $path) {
        $fullPath = __DIR__ . '/../../' . $path;
        if (file_exists($fullPath)) {
            $destPath = $archiveDir . basename($path);
            if (rename($fullPath, $destPath)) {
                $movedFiles++;
            }
        }
    }

    // Delete archived messages from main table
    $stmt = $db->prepare("DELETE FROM chat_messages WHERE created_at < ?");
    $stmt->execute([$cutoffDate]);
    $deleted = $stmt->rowCount();

    // Clean up related data
    $db->prepare("DELETE FROM chat_reactions WHERE message_id NOT IN (SELECT id FROM chat_messages)")->execute();
    $db->prepare("DELETE FROM chat_pinned_messages WHERE message_id NOT IN (SELECT id FROM chat_messages)")->execute();
    $db->prepare("DELETE FROM chat_mentions WHERE message_id NOT IN (SELECT id FROM chat_messages)")->execute();

    logMsg($logFile, "Archived: {$archived} messages | Moved: {$movedFiles} files | Deleted: {$deleted} from main table.");
    logMsg($logFile, "Archive cron completed successfully.\n");

    echo "Done. Archived {$archived} messages, moved {$movedFiles} files.\n";
} catch (Exception $e) {
    logMsg($logFile, "ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
