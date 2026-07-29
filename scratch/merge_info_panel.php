<?php
$file = 'c:/xampp/htdocs/CESARMENDOZA/modules/chat/index.php';
$content = file_get_contents($file);

// 1. Remove the old chat-info-panel
$start = strpos($content, '<!-- Chat Info Panel -->');
$end = strpos($content, '<!-- Right Sidebar (Chat Info) -->');
if ($start !== false && $end !== false) {
    $content = substr_replace($content, '', $start, $end - $start);
}

// 2. Rename and clean up chat-right-sidebar
$content = str_replace(
    '<aside class="chat-right-sidebar" id="chat-right-sidebar" style="display:none; width:360px; background:var(--bg-surface); border-left:1px solid var(--border-color); flex-direction:column; overflow:hidden;">',
    '<!-- Chat Info Panel -->
    <aside class="chat-info-panel" id="chat-info-panel">',
    $content
);
$content = str_replace(
    '<div class="crs-header" style="padding:1.25rem 1.5rem; display:flex; align-items:center; gap:1rem; border-bottom:1px solid var(--border-color);">',
    '<div class="chat-info-header">',
    $content
);
$content = str_replace(
    '<button class="chat-icon-btn" id="btn-close-right-sidebar" style="margin-left:-0.5rem;"><i class="ph ph-x"></i></button>',
    '<button class="chat-icon-btn-sm" id="btn-close-info"><i class="ph ph-x"></i></button>',
    $content
);
$content = str_replace(
    '<h3 style="margin:0; font-size:1.1rem; color:var(--text-main);">Info. del chat</h3>',
    '<h3>Info. del chat</h3>',
    $content
);
$content = str_replace(
    '<div class="crs-body" style="flex:1; overflow-y:auto; padding:1.5rem;">',
    '<div class="chat-info-body">',
    $content
);

file_put_contents($file, $content);
echo "Info panel merged and cleaned up.\n";
