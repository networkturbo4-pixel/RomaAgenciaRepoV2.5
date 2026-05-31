<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\index.php';
$content = file_get_contents($file);

$search = '<script src="modules/chat/chat.js?v=';
$replace = '<script src="modules/chat/voice.js?v=<?php echo time(); ?>"></script>
    <script src="modules/chat/chat.js?v=';

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Included voice.js properly\n";
?>
