<?php
// 1. Modificar chat.js
$fileJs = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$contentJs = file_get_contents($fileJs);

$searchJs = "const bubbleClass = (isOwn ? 'msg-bubble own' : 'msg-bubble') + (isImageOnly ? ' image-only' : '') + (isAudioOnly ? ' audio-only' : '');";
$replaceJs = "const isAdminMsg = currentChannelData && currentChannelData.channel && msg.user_id == currentChannelData.channel.created_by;
                const bubbleClass = (isOwn ? 'msg-bubble own' : 'msg-bubble') + (isImageOnly ? ' image-only' : '') + (isAudioOnly ? ' audio-only' : '') + (isAdminMsg ? ' msg-admin' : '');";

$contentJs = str_replace($searchJs, $replaceJs, $contentJs);
file_put_contents($fileJs, $contentJs);

// 2. Modificar chat.css
$fileCss = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.css';
$cssAdditions = "

/* --- Fase 1: Markdown, Medios y UI --- */
.msg-inline-code {
    background-color: rgba(100, 100, 100, 0.2);
    padding: 0.15rem 0.3rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.9em;
}
.msg-code-block {
    background-color: #1e1e1e;
    color: #d4d4d4;
    padding: 0.8rem;
    border-radius: 6px;
    font-family: monospace;
    font-size: 0.85em;
    overflow-x: auto;
    margin: 0.5rem 0;
}
.media-container {
    margin-top: 0.5rem;
    border-radius: 8px;
    overflow: hidden;
    background: #000;
}
.media-container iframe, .media-container video, .media-container audio {
    display: block;
    max-width: 100%;
}
.msg-bubble.msg-admin {
    border: 1px solid rgba(255, 215, 0, 0.5);
    box-shadow: 0 0 10px rgba(255, 215, 0, 0.1);
    position: relative;
}
.msg-bubble.msg-admin::after {
    content: '👑';
    position: absolute;
    top: -10px;
    right: -10px;
    font-size: 14px;
    background: white;
    border-radius: 50%;
    padding: 2px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
[data-theme='dark'] .msg-bubble.msg-admin::after {
    background: #2a2b32;
}

/* Fondos Dinámicos de Chat */
.bg-particles {
    background-image: radial-gradient(circle, rgba(255,255,255,0.05) 2px, transparent 2px);
    background-size: 30px 30px;
}
";

file_put_contents($fileCss, $cssAdditions, FILE_APPEND);

echo "Applied VIP bubbles and CSS logic.\n";
?>
