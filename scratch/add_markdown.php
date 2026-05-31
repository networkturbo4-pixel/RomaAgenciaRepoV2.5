<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($file);

$func = '
    // Format Markdown and Media
    function formatMarkdownAndMedia(text) {
        if (!text) return "";
        // 1. YouTube
        text = text.replace(/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})(?:\S+)?/g, `<div class="media-container"><iframe width="100%" height="250" src="https://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe></div>`);
        
        // 2. MP4 / WebM
        text = text.replace(/(https?:\/\/\S+?\.(?:mp4|webm))/g, `<div class="media-container"><video width="100%" controls preload="metadata"><source src="$1"></video></div>`);

        // 3. Audio (MP3 / OGG)
        text = text.replace(/(https?:\/\/\S+?\.(?:mp3|ogg))/g, `<div class="media-container"><audio controls preload="metadata"><source src="$1"></audio></div>`);

        // 4. Markdown Bold **text**
        text = text.replace(/\*\*(.*?)\*\*/g, `<strong>$1</strong>`);
        
        // 5. Markdown Italic *text* or _text_
        text = text.replace(/\*(.*?)\*/g, `<em>$1</em>`);
        
        // 6. Markdown Strikethrough ~text~
        text = text.replace(/~(.*?)~/g, `<del>$1</del>`);
        
        // 7. Markdown Inline Code `code`
        text = text.replace(/`(.*?)`/g, `<code class="msg-inline-code">$1</code>`);
        
        // 8. Markdown Code Block ```code```
        text = text.replace(/```([\s\S]*?)```/g, `<pre class="msg-code-block"><code>$1</code></pre>`);
        
        return text;
    }
';

// Insert the function before escapeHtml
$content = str_replace('    function escapeHtml(text) {', $func . "\n    function escapeHtml(text) {", $content);

// Modify renderMessage to use formatMarkdownAndMedia
// Original: let msgHtml = escapeHtml(msg.message || '').replace(/\n/g, '<br>');
// We need to find where message text is formatted.
// It seems there are a few places. Let's find: `escapeHtml(msg.message || '')`

$searchMsg = "let msgHtml = escapeHtml(msg.message || '').replace(/\\n/g, '<br>');";
$replaceMsg = "let escaped = escapeHtml(msg.message || '');\n                  let msgHtml = formatMarkdownAndMedia(escaped).replace(/\\n/g, '<br>');";

$content = str_replace($searchMsg, $replaceMsg, $content);

file_put_contents($file, $content);
echo "Added Markdown & Media to chat.js\n";
?>
