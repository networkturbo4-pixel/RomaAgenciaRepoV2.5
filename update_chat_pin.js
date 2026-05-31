const fs = require('fs');
const path = require('path');

const jsFile = path.join(__dirname, 'modules/chat/chat.js');
let js = fs.readFileSync(jsFile, 'utf8');

// 1. Add loadPinnedMessages function at the end
const pinnedFunc = `
async function loadPinnedMessages() {
    if (!currentChannelId) return;
    const fd = new FormData();
    fd.append('action', 'get_pinned_messages');
    fd.append('channel_id', currentChannelId);
    try {
        const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
        const data = await res.json();
        const bar = document.getElementById('chat-pinned-bar');
        const textSpan = document.getElementById('pinned-bar-text');
        
        if (data.success && data.pinned && data.pinned.length > 0) {
            const first = data.pinned[0];
            const msgText = first.message || first.attachment_name || 'Archivo adjunto';
            let summary = document.createElement('div').textContent = msgText; // Basic escape
            if (data.pinned.length > 1) {
                summary += \` (y \${data.pinned.length - 1} más)\`;
            }
            textSpan.innerHTML = \`<b>Mensaje fijado:</b> \${summary}\`;
            bar.style.display = 'flex';
        } else {
            bar.style.display = 'none';
        }
    } catch(e) { console.error(e); }
}
`;
if (!js.includes('loadPinnedMessages()')) {
    js += pinnedFunc;
}

// 2. Call loadPinnedMessages() inside openChannel
const openChannelTarget = `        loadMessages(true);
        startPolling();`;
const openChannelReplacement = `        loadMessages(true);
        loadPinnedMessages();
        startPolling();`;
if (!js.includes('loadPinnedMessages();\n        startPolling();')) {
    js = js.replace(openChannelTarget, openChannelReplacement);
}

// 3. Call loadPinnedMessages() when pinned
const pinTarget = `                            if (data.success) {
                                Swal.fire({ title: 'Fijado', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
                            }`;
const pinReplacement = `                            if (data.success) {
                                Swal.fire({ title: 'Fijado', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
                                loadPinnedMessages();
                            }`;
if (js.includes(pinTarget)) {
    js = js.replace(pinTarget, pinReplacement);
}

fs.writeFileSync(jsFile, js);
console.log('chat.js patched for pinning');
