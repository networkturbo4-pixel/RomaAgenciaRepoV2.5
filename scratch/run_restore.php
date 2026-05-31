<?php
$mods = json_decode(file_get_contents('C:\xampp\htdocs\CESARMENDOZA\scratch\chat_js_mods.json'), true);
$fileJs = 'C:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
// Restore to git state first
exec('git checkout modules/chat/chat.js');
$content = file_get_contents($fileJs);
$successCount = 0;
$failCount = 0;
foreach ($mods as $index => $mod) {
    if (isset($mod['ReplacementChunks'])) {
        $chunks = json_decode($mod['ReplacementChunks'], true);
        if ($chunks) {
            foreach ($chunks as $cIdx => $chunk) {
                if (isset($chunk['TargetContent']) && isset($chunk['ReplacementContent'])) {
                    $search = $chunk['TargetContent'];
                    $replace = $chunk['ReplacementContent'];
                    if (strpos($content, $search) !== false) {
                        $content = str_replace($search, $replace, $content);
                        $successCount++;
                    } else {
                        $failCount++;
                        echo "Failed to find chunk $cIdx in mod $index\n";
                        echo substr($search, 0, 50) . "...\n";
                    }
                }
            }
        }
    }
}
file_put_contents($fileJs, $content);
echo "Restored chat.js. Success: $successCount, Failed: $failCount\n";
?>
