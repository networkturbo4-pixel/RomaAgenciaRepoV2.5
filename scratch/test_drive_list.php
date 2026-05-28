<?php
require_once 'config/database.php';
require_once 'includes/GoogleDriveHelper.php';

$db = (new Database())->getConnection();
$driveHelper = new GoogleDriveHelper();

$stmt = $db->query("SELECT drive_folder_id FROM projects WHERE drive_folder_id IS NOT NULL LIMIT 1");
$projectRootId = $stmt->fetchColumn();

if ($projectRootId) {
    $existingFolders = $driveHelper->listFolders($projectRootId);
    echo "Count: " . count($existingFolders) . "\n";
    if ($existingFolders) {
        foreach ($existingFolders as $f) {
            echo "Class: " . get_class($f) . "\n";
            echo "Name (prop): " . (isset($f->name) ? $f->name : 'N/A') . "\n";
            echo "Name (method): " . (method_exists($f, 'getName') ? $f->getName() : 'N/A') . "\n";
            break;
        }
    }
} else {
    echo "No project root found.";
}
