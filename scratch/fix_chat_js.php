<?php
$file = 'c:/xampp/htdocs/CESARMENDOZA/modules/chat/chat.js';
$content = file_get_contents($file);

// 1. Rename IDs for right sidebar
$content = str_replace("'chat-right-sidebar'", "'chat-info-panel'", $content);
$content = str_replace("'btn-close-right-sidebar'", "'btn-close-info'", $content);

// 2. Adjust message rendering for modern ticks
// We used ph-checks for read, ph-check for delivered.
// Our new CSS colors .msg-status-read to blue.

// 3. Info panel layout logic
// Instead of modifying flex on main wrapper, chat-info-panel active class handles it.
// Let's find where rightSidebar is shown.
// Usually: rightSidebar.style.display = 'flex'
$content = str_replace(
    "rightSidebar.style.display = 'flex';",
    "rightSidebar.classList.add('active');",
    $content
);
$content = str_replace(
    "rightSidebar.style.display = 'none';",
    "rightSidebar.classList.remove('active');",
    $content
);

file_put_contents($file, $content);
echo "chat.js updated.\n";
