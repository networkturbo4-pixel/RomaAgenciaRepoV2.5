<?php
// modules/chat/public.php — Public chat view for external guests
$token = $_GET['token'] ?? '';
if (empty($token)) { echo "Token inválido."; exit(); }

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Validate token
$stmt = $db->prepare("SELECT id, name, description FROM chat_channels WHERE public_token = ?");
$stmt->execute([$token]);
$channel = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$channel) { echo "<div style='font-family:Inter,sans-serif;padding:3rem;text-align:center;'><h2>🔒 Link inválido o expirado</h2><p>Este enlace de invitación ya no está disponible.</p></div>"; exit(); }

// Fetch global settings for branding
$stmtSettings = $db->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmtSettings->fetch(PDO::FETCH_ASSOC)) { $settings[$row['setting_key']] = $row['setting_value']; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?php echo htmlspecialchars($channel['name']); ?></title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="modules/chat/chat.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:var(--bg-color); color:var(--text-main); min-height:100vh; display:flex; flex-direction:column; }
        .public-header { background:var(--bg-surface); padding:1rem 1.5rem; border-bottom:1px solid var(--border-color); display:flex; align-items:center; gap:0.75rem; }
        .public-header h2 { font-size:1.1rem; font-weight:700; }
        .public-header span { font-size:0.8rem; color:var(--text-muted); }
        .public-chat-wrap { flex:1; display:flex; flex-direction:column; max-width:800px; width:100%; margin:0 auto; background:var(--bg-surface); border-left:1px solid var(--border-color); border-right:1px solid var(--border-color); }
        .public-messages { flex:1; overflow-y:auto; padding:1rem 1.25rem; display:flex; flex-direction:column; gap:0.15rem; }
        .public-input-area { padding:0.75rem 1.25rem; background:var(--bg-surface); border-top:1px solid var(--border-color); }
        .public-input-row { display:flex; gap:0.5rem; align-items:flex-end; }
        .public-input-row textarea { flex:1; padding:0.65rem 1rem; border:1px solid var(--border-color); border-radius:24px; background:var(--bg-color); color:var(--text-main); font-family:inherit; font-size:0.875rem; resize:none; outline:none; max-height:100px; }
        .public-input-row textarea:focus { border-color:var(--primary-color); }
        /* Guest entry overlay */
        .guest-overlay { position:fixed; inset:0; background:rgba(15,23,42,0.6); display:flex; align-items:center; justify-content:center; z-index:100; backdrop-filter:blur(4px); }
        .guest-card { background:var(--bg-surface); padding:2rem; border-radius:20px; max-width:380px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.2); text-align:center; }
        .guest-card h2 { margin-bottom:0.5rem; font-size:1.25rem; }
        .guest-card p { color:var(--text-muted); font-size:0.85rem; margin-bottom:1.25rem; }
        .guest-card input { width:100%; padding:0.7rem 1rem; border:1px solid var(--border-color); border-radius:var(--radius-md); background:var(--bg-color); color:var(--text-main); font-size:0.9rem; margin-bottom:0.75rem; outline:none; }
        .guest-card input:focus { border-color:var(--primary-color); }
        .guest-card button { width:100%; padding:0.7rem; background:var(--primary-color); color:white; border:none; border-radius:var(--radius-md); font-weight:600; cursor:pointer; font-size:0.9rem; transition:background 0.2s; }
        .guest-card button:hover { background:var(--primary-hover); }
    </style>
</head>
<body>
    <!-- Guest Name Entry -->
    <div class="guest-overlay" id="guest-overlay">
        <div class="guest-card">
            <i class="ph ph-chat-circle-dots" style="font-size:2.5rem; color:var(--primary-color); margin-bottom:0.5rem;"></i>
            <h2># <?php echo htmlspecialchars($channel['name']); ?></h2>
            <p><?php echo htmlspecialchars($channel['description'] ?? 'Has sido invitado a esta conversación'); ?></p>
            <input type="text" id="guest-name-input" placeholder="Tu nombre..." autofocus>
            <button id="btn-enter-chat">Entrar al Chat</button>
        </div>
    </div>

    <div class="public-chat-wrap">
        <div class="public-header">
            <i class="ph ph-hash" style="font-size:1.2rem; color:var(--primary-color);"></i>
            <div>
                <h2><?php echo htmlspecialchars($channel['name']); ?></h2>
                <span><?php echo htmlspecialchars($channel['description'] ?? ''); ?></span>
            </div>
        </div>
        <div class="public-messages" id="pub-messages"></div>
        <div class="public-input-area">
            <div class="public-input-row">
                <textarea id="pub-input" placeholder="Escribe un mensaje..." rows="1"></textarea>
                <button class="chat-send-btn" id="pub-send"><i class="ph-fill ph-paper-plane-tilt"></i></button>
            </div>
        </div>
    </div>

<script>
const TOKEN = <?php echo json_encode($token); ?>;
const AVATAR_COLORS = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
let guestName = '';
let lastMsgId = 0;

function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
function formatTime(s) { return new Date(s).toLocaleTimeString('es-PE', {hour:'2-digit',minute:'2-digit'}); }

document.getElementById('btn-enter-chat').addEventListener('click', () => {
    const name = document.getElementById('guest-name-input').value.trim();
    if (!name) return;
    guestName = name;
    document.getElementById('guest-overlay').style.display = 'none';
    loadMessages();
    setInterval(pollMessages, 3000);
});
document.getElementById('guest-name-input').addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('btn-enter-chat').click();
});

async function loadMessages() {
    const res = await fetch('modules/chat/ajax.php', { method:'POST', body: new URLSearchParams({ action:'get_public_messages', token: TOKEN }) });
    const data = await res.json();
    if (!data.success) return;
    renderMsgs(data.messages, true);
}

async function pollMessages() {
    const res = await fetch('modules/chat/ajax.php', { method:'POST', body: new URLSearchParams({ action:'poll_public', token: TOKEN, last_id: lastMsgId }) });
    const data = await res.json();
    if (data.success && data.messages.length) renderMsgs(data.messages, false);
}

function renderMsgs(msgs, full) {
    const c = document.getElementById('pub-messages');
    if (full) c.innerHTML = '';
    msgs.forEach(m => {
        const name = m.user_name || m.guest_name || 'Anónimo';
        const isGuest = !m.user_id;
        const badge = isGuest ? '<span class="msg-guest-badge">Invitado</span>' : '';
        const uid = m.user_id || 0;
        
        let color = AVATAR_COLORS[uid % AVATAR_COLORS.length];
        if (isGuest && name) {
            let hash = 0;
            for (let i = 0; i < name.length; i++) { hash = name.charCodeAt(i) + ((hash << 5) - hash); }
            color = AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
        }

        const avatar = m.user_avatar
            ? `<div class="chat-avatar" style="background-image:url('${m.user_avatar}');background-size:cover;"></div>`
            : `<div class="chat-avatar" style="background:${color}">${name.charAt(0).toUpperCase()}</div>`;

        let content = escapeHtml(m.message || '').replace(/\n/g,'<br>');
        if (m.message_type === 'card' && m.card_data) {
            const card = typeof m.card_data === 'string' ? JSON.parse(m.card_data) : m.card_data;
            let fields = '';
            (card.fields||[]).forEach(f => { fields += `<div class="msg-card-field"><span class="msg-card-field-label">${escapeHtml(f.label)}</span><span class="msg-card-field-value">${escapeHtml(f.value)}</span></div>`; });
            content = `<div class="msg-card" style="margin-left:0;max-width:100%;"><div class="msg-card-header" style="color:${card.color||'var(--primary-color)'}"><i class="ph ph-squares-four"></i> ${escapeHtml(card.title||'Card')}</div><div class="msg-card-body">${fields}</div></div>`;
        }

        c.insertAdjacentHTML('beforeend', `
            <div class="msg-group">
                <div class="msg-group-header">${avatar}<span class="msg-sender-name">${escapeHtml(name)}</span>${badge}<span class="msg-time">${formatTime(m.created_at)}</span></div>
                <div class="msg-bubble" style="margin-left:44px;">${content}</div>
            </div>`);
        if (m.id > lastMsgId) lastMsgId = m.id;
    });
    c.scrollTop = c.scrollHeight;
}

document.getElementById('pub-send').addEventListener('click', sendMsg);
document.getElementById('pub-input').addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); } });

async function sendMsg() {
    const input = document.getElementById('pub-input');
    const msg = input.value.trim();
    if (!msg || !guestName) return;
    input.value = '';
    await fetch('modules/chat/ajax.php', { method:'POST', body: new URLSearchParams({ action:'send_public_message', token: TOKEN, guest_name: guestName, message: msg }) });
    pollMessages();
}
</script>
</body>
</html>
