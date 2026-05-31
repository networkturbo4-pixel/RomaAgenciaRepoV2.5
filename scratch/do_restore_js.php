<?php
// 2. Fix chat.js
exec('git checkout C:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js');
$jsFile = 'C:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$js = file_get_contents($jsFile);
// Normalize to LF
$js = str_replace("\r\n", "\n", $js);

$mods = json_decode(file_get_contents('C:\xampp\htdocs\CESARMENDOZA\scratch\chat_js_mods.json'), true);
// Skip the last mod
$validMods = array_slice($mods, 0, count($mods) - 1);

$success = 0;
foreach ($validMods as $mod) {
    if (isset($mod['ReplacementChunks'])) {
        $chunks = json_decode($mod['ReplacementChunks'], true);
        if (!$chunks) continue;
        foreach ($chunks as $chunk) {
            $search = str_replace("\r\n", "\n", $chunk['TargetContent']);
            $replace = str_replace("\r\n", "\n", $chunk['ReplacementContent']);
            if (strpos($js, $search) !== false) {
                $js = str_replace($search, $replace, $js);
                $success++;
            } else {
                echo "Warning: Could not apply chunk for " . $mod['Description'] . "\n";
            }
        }
    }
}
file_put_contents($jsFile, $js);
echo "chat.js fixed with $success chunks applied.\n";
?>
