<?php
$mods = json_decode(file_get_contents('c:\xampp\htdocs\CESARMENDOZA\scratch\chat_js_mods.json'), true);
$fileJs = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($fileJs);

// Only apply the first N-1 modifications (skip the last one which broke it)
$validMods = array_slice($mods, 0, count($mods) - 1);

foreach ($validMods as $mod) {
    if (isset($mod['ReplacementChunks'])) {
        foreach ($mod['ReplacementChunks'] as $chunk) {
            if (isset($chunk['TargetContent']) && isset($chunk['ReplacementContent'])) {
                $search = $chunk['TargetContent'];
                $replace = $chunk['ReplacementContent'];
                // Replace exactly 1 time to avoid issues
                $content = str_replace($search, $replace, $content);
            }
        }
    }
}

file_put_contents($fileJs, $content);
echo "Restored chat.js with " . count($validMods) . " modifications via str_replace.\n";
echo "New size: " . strlen($content) . " bytes\n";
?>
