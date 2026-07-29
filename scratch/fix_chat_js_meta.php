<?php
$file = 'c:/xampp/htdocs/CESARMENDOZA/modules/chat/chat.js';
$content = file_get_contents($file);

// Add msg-meta logic to renderMessages
$insert = <<<JS
                let metaHtml = '';
                const mTime = formatTime(msg.created_at);
                if (isOwn) {
                    let ticks = '<i class="ph ph-check"></i>'; // Default 1 tick
                    // Assuming if we have status, maybe it's passed or handled via poll later,
                    // but let's give it the base structure.
                    metaHtml = \`<div class="msg-meta"><span>\${mTime}</span><span class="msg-status">\${ticks}</span></div>\`;
                } else {
                    metaHtml = \`<div class="msg-meta"><span>\${mTime}</span></div>\`;
                }
JS;

$search = "let bubbleHtml = `\n                    <div class=\"msg-bubble-wrap \${isOwn ? 'own' : ''}\" data-id=\"\${msg.id}\" style=\"position:relative;\">";
$replace = $insert . "\n                " . $search;
$content = str_replace($search, $replace, $content);

$search2 = "\${reactionsHtml}\n                        </div>";
$replace2 = "\${reactionsHtml}\n                            \${metaHtml}\n                        </div>";
$content = str_replace($search2, $replace2, $content);

file_put_contents($file, $content);
echo "Added msg-meta to chat bubbles.\n";
