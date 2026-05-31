<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\index.php';
$content = file_get_contents($file);

$search1 = '<script src="chat.js?v=';

$replace1 = '<script src="voice.js?v=<?= time() ?>"></script>
    <script src="chat.js?v=';

$content = str_replace($search1, $replace1, $content);

file_put_contents($file, $content);
echo "Included voice.js in index.php\n";
?>
