<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\index.php';
$content = file_get_contents($file);

// Remove voice.js inclusion
$content = preg_replace('/<script src="modules\/chat\/voice\.js\?v=.*?"><\/script>\s*/s', '', $content);

// Remove the voice-active-panel
$content = preg_replace('/<!-- Voice Active Panel -->.*?<!-- Messages Area -->/s', '<!-- Messages Area -->', $content);

// Remove btn-join-voice
$content = preg_replace('/<button class="chat-icon-btn" id="btn-join-voice".*?<\/button>/s', '', $content);

file_put_contents($file, $content);
echo "Cleaned up old WebRTC UI\n";
?>
