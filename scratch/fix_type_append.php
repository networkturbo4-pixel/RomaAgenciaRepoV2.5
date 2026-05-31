<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($file);

$search1 = "            fd.append('is_public', isPublic);";
$replace1 = "            fd.append('is_public', isPublic);
            const typeInput = document.querySelector('input[name=\"group-type\"]:checked');
            fd.append('type', typeInput ? typeInput.value : 'group');";

$content = str_replace($search1, $replace1, $content);
file_put_contents($file, $content);
echo "Fixed type appending in chat.js\n";
?>
