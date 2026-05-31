<?php
$mods = json_decode(file_get_contents('c:\xampp\htdocs\CESARMENDOZA\scratch\chat_js_mods.json'), true);
$fileJs = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($fileJs);

$validMods = array_slice($mods, 0, count($mods) - 1);
$successCount = 0;
$failCount = 0;

foreach ($validMods as $index => $mod) {
    if (isset($mod['ReplacementChunks'])) {
        foreach ($mod['ReplacementChunks'] as $cIdx => $chunk) {
            if (isset($chunk['TargetContent']) && isset($chunk['ReplacementContent'])) {
                $search = $chunk['TargetContent'];
                $replace = $chunk['ReplacementContent'];
                if (strpos($content, $search) !== false) {
                    $content = str_replace($search, $replace, $content);
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
        }
    }
}

file_put_contents($fileJs, $content);
echo "Restored chat.js. Success: $successCount, Failed: $failCount\n";
echo "New size: " . strlen($content) . " bytes\n";
?>
