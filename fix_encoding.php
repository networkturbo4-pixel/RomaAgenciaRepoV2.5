<?php
$file = 'c:\\xampp\\htdocs\\CESARMENDOZA\\modules\\month_board\\index.php';
$content = file_get_contents($file);
$fixed = mb_convert_encoding($content, 'Windows-1252', 'UTF-8');
file_put_contents($file, $fixed);
echo "Fixed";
?>
