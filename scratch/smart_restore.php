<?php
exec('git checkout C:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js');
$jsFile = 'C:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$js = file_get_contents($jsFile);

function normalize($str) {
    // Remove \r, convert \n to space, collapse spaces
    $str = str_replace("\r", "", $str);
    $lines = explode("\n", $str);
    $lines = array_map('trim', $lines);
    return implode("\n", $lines);
}

function applyChunk($js, $target, $replace) {
    $normJs = normalize($js);
    $normTarget = normalize($target);
    $pos = strpos($normJs, $normTarget);
    if ($pos !== false) {
        // We found it in normalized. But we need to replace in ORIGINAL!
        // To do this reliably, we can search line by line.
    }
    return false;
}

// Since fuzzy match is hard, let's just use regex to ignore leading whitespace
function flexibleReplace($js, $target, $replace) {
    $targetLines = explode("\n", str_replace("\r", "", $target));
    $regexParts = [];
    foreach ($targetLines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;
        $regexParts[] = preg_quote($trimmed, '/');
    }
    $regex = '/' . implode('\s+', $regexParts) . '/s';
    
    // We can't easily preserve the surrounding whitespace with this regex unless we capture.
    return preg_replace($regex, trim($replace), $js, 1, $count);
}

$mods = json_decode(file_get_contents('C:\xampp\htdocs\CESARMENDOZA\scratch\chat_js_mods.json'), true);
$validMods = array_slice($mods, 0, count($mods) - 1);
$success = 0;

foreach ($validMods as $mod) {
    if (isset($mod['ReplacementChunks'])) {
        $chunks = json_decode($mod['ReplacementChunks'], true);
        if (!$chunks) continue;
        foreach ($chunks as $chunk) {
            $target = $chunk['TargetContent'];
            $replace = $chunk['ReplacementContent'];
            
            // Try strict replace first
            $js2 = str_replace(str_replace("\r\n", "\n", $target), str_replace("\r\n", "\n", $replace), str_replace("\r\n", "\n", $js));
            if ($js2 !== str_replace("\r\n", "\n", $js)) {
                $js = $js2;
                $success++;
                continue;
            }
            
            // Try flexible replace
            $jsNew = flexibleReplace($js, $target, $replace);
            if ($jsNew && $jsNew !== $js) {
                $js = $jsNew;
                $success++;
                continue;
            }
            
            echo "Failed chunk: " . $mod['Description'] . "\n";
        }
    }
}
file_put_contents($jsFile, $js);
echo "Applied $success chunks.\n";
?>
