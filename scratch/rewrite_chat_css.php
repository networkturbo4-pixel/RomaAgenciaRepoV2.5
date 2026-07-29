<?php
$file = 'c:/xampp/htdocs/CESARMENDOZA/modules/chat/chat.css';
$oldCss = file_get_contents($file);

// Find where CARD MESSAGES starts to preserve widget styles
$preservePos = strpos($oldCss, '/* ═══════════════════════════════════════
   CARD MESSAGES');
if ($preservePos === false) {
    $preservePos = strpos($oldCss, 'CARD MESSAGES');
}

$preservedCss = '';
if ($preservePos !== false) {
    // Also include whatever was below it
    $preservedCss = substr($oldCss, $preservePos - 40); // backtrack a bit just in case
} else {
    // If not found, just grab the second half approximately, or nothing if we didn't find it.
    // Let's assume it is found.
}

$newCss = <<<CSS
/* ==================================================================
   Módulo de Mensajes - Luminous Design System (V2)
   ================================================================== */
:root {
    --chat-bg: #Eef2f6;
    --chat-surface: #ffffff;
    --chat-border: rgba(0, 0, 0, 0.06);
    --chat-primary: var(--primary-color, #4f46e5);
    --chat-text-main: #1e293b;
    --chat-text-muted: #64748b;
    --chat-bubble-own: #4f46e5;
    --chat-bubble-own-text: #ffffff;
    --chat-bubble-other: #ffffff;
    --chat-bubble-other-text: #1e293b;
    --chat-bubble-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
[data-theme="dark"] {
    --chat-bg: #0f172a;
    --chat-surface: #1e293b;
    --chat-border: rgba(255, 255, 255, 0.08);
    --chat-text-main: #f8fafc;
    --chat-text-muted: #94a3b8;
    --chat-bubble-own: #6366f1;
    --chat-bubble-other: #334155;
    --chat-bubble-other-text: #f8fafc;
}
.chat-app *, .chat-app *::before, .chat-app *::after { box-sizing: border-box; }

/* Base Layout */
.chat-app {
    display: flex;
    height: calc(100vh - 64px - 2rem);
    background: var(--chat-surface);
    border-radius: var(--radius-lg, 16px);
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    font-family: 'Inter', sans-serif;
    color: var(--chat-text-main);
    position: relative;
    border: 1px solid var(--chat-border);
}
.chat-sidebar {
    width: 320px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--chat-border);
    background: var(--chat-surface);
    z-index: 10;
}
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: var(--chat-bg);
    position: relative;
}
.chat-info-panel {
    width: 320px;
    flex-shrink: 0;
    display: none;
    flex-direction: column;
    border-left: 1px solid var(--chat-border);
    background: var(--chat-surface);
}
.chat-info-panel.active { display: flex; }

/* Sidebar Elements */
.chat-sidebar-header {
    padding: 1.5rem 1.5rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.chat-sidebar-header h2 { margin: 0; font-size: 1.4rem; font-weight: 700; color: var(--chat-text-main); }
.chat-search-wrap { padding: 0 1.25rem 1rem; position: relative; }
.chat-search-wrap i { position: absolute; left: 2rem; top: 50%; transform: translateY(-50%) translateY(-0.5rem); color: var(--chat-text-muted); }
.chat-search-wrap input {
    width: 100%;
    padding: 0.65rem 1rem 0.65rem 2.25rem;
    background: color-mix(in srgb, var(--chat-text-muted) 8%, transparent);
    border: none; border-radius: 20px;
    color: var(--chat-text-main);
    outline: none; transition: background 0.3s;
    font-size: 0.85rem;
}
.chat-search-wrap input:focus { background: color-mix(in srgb, var(--chat-text-muted) 12%, transparent); }

.chat-filters { padding: 0 1.25rem 0.5rem; display: flex; gap: 0.5rem; }
.chat-filter-pill {
    padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500;
    background: transparent; border: 1px solid var(--chat-border);
    color: var(--chat-text-muted); cursor: pointer; transition: all 0.2s;
}
.chat-filter-pill.active { background: color-mix(in srgb, var(--chat-primary) 10%, transparent); color: var(--chat-primary); border-color: transparent; }

.channel-list { flex: 1; overflow-y: auto; padding: 0.5rem 0.5rem; }
.channel-item {
    display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem;
    border-radius: 12px; cursor: pointer; transition: all 0.2s;
    margin-bottom: 0.2rem; border: 1px solid transparent;
}
.channel-item:hover { background: color-mix(in srgb, var(--chat-text-muted) 5%, transparent); }
.channel-item.active { background: var(--chat-primary); color: #fff; box-shadow: 0 4px 12px color-mix(in srgb, var(--chat-primary) 40%, transparent); }
.channel-item.active .channel-item-name { color: #fff; }
.channel-item.active .channel-item-preview, .channel-item.active .channel-item-time, .channel-item.active .channel-item-icon { color: rgba(255,255,255,0.8); }

.channel-item-icon { font-size: 1.2rem; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: color-mix(in srgb, var(--chat-text-muted) 10%, transparent); color: var(--chat-text-muted); }
.channel-item.active .channel-item-icon { background: rgba(255,255,255,0.2); color: #fff; }

.channel-item-info { flex: 1; min-width: 0; }
.channel-item-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.2rem; }
.channel-item-name { font-weight: 600; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--chat-text-main); }
.channel-item-time { font-size: 0.7rem; color: var(--chat-text-muted); }
.channel-item-preview { font-size: 0.8rem; color: var(--chat-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Chat Main Header (Glassmorphism) */
.chat-header {
    padding: 1rem 1.5rem;
    background: color-mix(in srgb, var(--chat-bg) 75%, transparent);
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--chat-border);
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 10;
}
.chat-header-info { display: flex; align-items: center; gap: 1rem; }
.chat-header-avatar { width: 40px; height: 40px; border-radius: 50%; background-size: cover; background-position: center; border: 2px solid var(--chat-surface); box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
.chat-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--chat-text-main); }
.chat-meta { font-size: 0.8rem; color: var(--chat-text-muted); }

/* Messages Area */
.chat-messages {
    flex: 1; overflow-y: auto; padding: 1.5rem;
    display: flex; flex-direction: column; gap: 0.5rem;
}
.msg-group { display: flex; flex-direction: column; gap: 0.2rem; margin-bottom: 1rem; max-width: 100%; min-width: 0; }
.msg-group-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem; padding-left: 45px; }
.msg-group-header.own { justify-content: flex-end; padding-left: 0; padding-right: 0px; }
.msg-sender-name { font-size: 0.75rem; font-weight: 600; color: var(--chat-text-muted); }
.msg-time { font-size: 0.7rem; color: var(--chat-text-muted); }

/* Bubble Wrappers & Avatar */
.msg-bubble-wrap { display: flex; align-items: flex-end; gap: 0.5rem; max-width: 80%; position: relative; width: fit-content; }
.msg-bubble-wrap.own { margin-left: auto; justify-content: flex-end; }

.chat-avatar-sm { width: 32px; height: 32px; border-radius: 50%; background-size: cover; flex-shrink: 0; border: 1px solid var(--chat-border); }
.msg-bubble-wrap.own .chat-avatar-sm { display: none; }

/* Modern Bubbles */
@keyframes msgIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.msg-bubble {
    padding: 0.65rem 1rem;
    background: var(--chat-bubble-other);
    color: var(--chat-bubble-other-text);
    border-radius: 18px 18px 18px 4px;
    box-shadow: var(--chat-bubble-shadow);
    font-size: 0.95rem; line-height: 1.4;
    word-break: break-word;
    position: relative;
    border: 1px solid var(--chat-border);
    animation: msgIn 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.msg-bubble-wrap.own .msg-bubble {
    background: var(--chat-bubble-own);
    color: var(--chat-bubble-own-text);
    border-radius: 18px 18px 4px 18px;
    border: none;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--chat-primary) 30%, transparent);
}

/* Message Meta & Ticks */
.msg-meta {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.7rem; color: var(--chat-text-muted);
    float: right; margin-left: 12px; margin-top: 6px;
}
.msg-bubble-wrap.own .msg-meta { color: rgba(255,255,255,0.75); }
.msg-meta i { font-size: 1.1rem; line-height: 1; }
.msg-status-read { color: #38bdf8 !important; }

/* Input Area (Floating Capsule) */
.chat-input-area {
    padding: 1rem 1.5rem 1.5rem;
    background: transparent;
    position: relative;
}
.chat-input-wrapper {
    display: flex; align-items: flex-end;
    background: var(--chat-surface);
    border-radius: 24px;
    padding: 0.5rem 0.5rem 0.5rem 1rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    border: 1px solid var(--chat-border);
}
.chat-input-wrapper textarea {
    flex: 1; border: none; background: transparent;
    resize: none; max-height: 120px; outline: none;
    font-family: inherit; font-size: 0.95rem; color: var(--chat-text-main);
    padding: 0.6rem 0;
    line-height: 1.4;
}
.chat-icon-btn {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    border: none; background: transparent; color: var(--chat-text-muted);
    cursor: pointer; font-size: 1.4rem; transition: all 0.2s; flex-shrink: 0;
}
.chat-icon-btn:hover { background: color-mix(in srgb, var(--chat-text-muted) 10%, transparent); }
.btn-send-msg {
    background: var(--chat-primary); color: #fff;
    box-shadow: 0 4px 12px color-mix(in srgb, var(--chat-primary) 40%, transparent);
}
.btn-send-msg:hover { background: color-mix(in srgb, var(--chat-primary) 80%, black); color:#fff; transform: scale(1.05); }

/* Attachment Menu Modern */
.attachment-popup-menu {
    position: absolute; bottom: calc(100% + 10px); left: 0;
    background: var(--chat-surface); border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.12);
    padding: 1.25rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;
    opacity: 0; visibility: hidden; transform: translateY(15px) scale(0.95);
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    border: 1px solid var(--chat-border); z-index: 100;
}
.attachment-popup-menu.show { opacity: 1; visibility: visible; transform: translateY(0) scale(1); }
.attachment-menu-item {
    display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
    cursor: pointer; transition: transform 0.2s;
}
.attachment-menu-item:hover { transform: translateY(-3px); }
.attachment-menu-icon {
    width: 50px; height: 50px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem; color: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.attachment-menu-item span { font-size: 0.8rem; font-weight: 500; color: var(--chat-text-main); text-align: center; }

/* Empty state */
.chat-empty-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--chat-text-muted); }
.chat-empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.4; }
.chat-empty-state h3 { font-size: 1.2rem; margin:0 0 0.5rem; font-weight: 600; color: var(--chat-text-main); }

/* Info Panel */
.chat-info-header { padding: 1.5rem; border-bottom: 1px solid var(--chat-border); display: flex; align-items: center; justify-content: space-between; }
.chat-info-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--chat-text-main); }
.chat-info-body { padding: 1.5rem; flex: 1; overflow-y: auto; }

/* Misc */
.chat-icon-btn-sm { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; background: transparent; cursor: pointer; transition: 0.2s; font-size: 1.1rem; color: var(--chat-text-muted); }
.chat-icon-btn-sm:hover { background: color-mix(in srgb, var(--chat-text-muted) 10%, transparent); color: var(--chat-text-main); }
.new-msg-count { position: absolute; top: -5px; right: -5px; background: #ef4444; color: #fff; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; font-weight: 700; border: 2px solid var(--chat-surface); }
.btn-scroll-bottom { position: absolute; bottom: 80px; right: 30px; width: 40px; height: 40px; border-radius: 50%; background: var(--chat-surface); box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid var(--chat-border); z-index: 50; color: var(--chat-text-muted); font-size: 1.2rem; }

/* Modals */
.chat-multimedia-lightbox { position: fixed; inset: 0; z-index: 1050; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); display: flex; flex-direction: column; }
.lightbox-header { padding: 1rem 1.5rem; display: flex; justify-content: space-between; color: #fff; }
.lightbox-title { font-weight: 600; font-size: 1.1rem; }
.lightbox-actions .chat-icon-btn { color: #fff; }
.lightbox-actions .chat-icon-btn:hover { background: rgba(255,255,255,0.1); }
.lightbox-body { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; overflow: hidden; }
.lightbox-body img, .lightbox-body video { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.4); }

/* Responsive */
@media (max-width: 992px) {
    .chat-sidebar { width: 100%; position: absolute; z-index: 100; height: 100%; transition: transform 0.3s cubic-bezier(0.25,0.8,0.25,1); }
    .chat-sidebar.hidden { transform: translateX(-100%); }
    .chat-info-panel { width: 100%; position: absolute; z-index: 101; height: 100%; right: 0; transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.25,0.8,0.25,1); display:flex !important; }
    .chat-info-panel.active { transform: translateX(0); }
    .chat-main { width: 100%; }
}

/* Animations */
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

CSS;

$finalCss = $newCss . "\n\n" . $preservedCss;
file_put_contents('c:/xampp/htdocs/CESARMENDOZA/modules/chat/chat.css', $finalCss);
echo "chat.css rewritten successfully preserving widget styles.\n";
