<?php
// 1. Fix chat.css
$cssFile = 'C:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.css';
$css = file_get_contents($cssFile);
$pos = strpos($css, '/* FASE 2: Quick Actions on Hover */');
if ($pos !== false) {
    $css = substr($css, 0, $pos);
    file_put_contents($cssFile, trim($css) . "\n");
    echo "chat.css fixed.\n";
} else {
    echo "chat.css already fixed or missing marker.\n";
}

// 2. Fix chat.js
exec('git checkout C:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js');
$mods = json_decode(file_get_contents('C:\xampp\htdocs\CESARMENDOZA\scratch\chat_js_mods.json'), true);
$jsFile = 'C:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$js = file_get_contents($jsFile);

// Skip the last mod (the quick actions one that broke everything)
$validMods = array_slice($mods, 0, count($mods) - 1);

$success = 0;
foreach ($validMods as $mod) {
    if (isset($mod['ReplacementChunks'])) {
        $chunks = json_decode($mod['ReplacementChunks'], true);
        if (!$chunks) continue;
        foreach ($chunks as $chunk) {
            $search = $chunk['TargetContent'];
            $replace = $chunk['ReplacementContent'];
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
