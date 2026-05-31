const fs = require('fs');
const path = require('path');

// 1. Fix ajax.php for get_channel_media
const ajaxFile = path.join(__dirname, 'modules/chat/ajax.php');
let php = fs.readFileSync(ajaxFile, 'utf8');
if (!php.includes("case 'get_channel_media':")) {
    const mediaCode = `        case 'get_channel_media':
            $channelId = (int)($_POST['channel_id'] ?? 0);
            
            $stmt = $db->prepare("SELECT attachment, attachment_name FROM chat_messages WHERE channel_id = ? AND attachment IS NOT NULL AND attachment != '' AND attachment RLIKE '\\\\.(jpg|jpeg|png|gif|webp)$' ORDER BY id DESC LIMIT 20");
            $stmt->execute([$channelId]);
            $media = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT attachment, attachment_name FROM chat_messages WHERE channel_id = ? AND attachment IS NOT NULL AND attachment != '' AND attachment NOT RLIKE '\\\\.(jpg|jpeg|png|gif|webp|mp3|wav|ogg)$' ORDER BY id DESC LIMIT 20");
            $stmt->execute([$channelId]);
            $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT id, message, created_at FROM chat_messages WHERE channel_id = ? AND message LIKE '%http%' ORDER BY id DESC LIMIT 20");
            $stmt->execute([$channelId]);
            $rawLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $links = [];
            foreach ($rawLinks as $msg) {
                if (preg_match_all('/(https?:\\\\/\\\\/[^\\\\s<]+)/', $msg['message'], $matches)) {
                    foreach ($matches[1] as $url) {
                        $links[] = ['url' => $url, 'domain' => parse_url($url, PHP_URL_HOST), 'created_at' => $msg['created_at']];
                    }
                }
            }
            echo json_encode(['success' => true, 'media' => $media, 'docs' => $docs, 'links' => array_slice($links, 0, 20)]);
            break;
            
        default:`;
    php = php.replace('default:', mediaCode);
    fs.writeFileSync(ajaxFile, php);
}

// 2. Fix chat.js (move loadPinnedMessages and add missing listeners)
const jsFile = path.join(__dirname, 'modules/chat/chat.js');
let js = fs.readFileSync(jsFile, 'utf8');

// A. Extract loadPinnedMessages from outside and remove it
const regexPinned = /async function loadPinnedMessages\(\) \{[\s\S]*?\} catch\(e\) \{ console\.error\(e\); \}\n\}/;
const match = js.match(regexPinned);
if (match) {
    js = js.replace(match[0], '');
}

// B. Define the missing logic that must be inside IIFE
const missingLogic = `
    // LOAD PINNED
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
                let summary = document.createElement('div');
                summary.textContent = msgText;
                summary = summary.innerHTML;
                if (data.pinned.length > 1) summary += \` (y \${data.pinned.length - 1} más)\`;
                textSpan.innerHTML = \`<b>Mensaje fijado:</b> \${summary}\`;
                bar.style.display = 'flex';
            } else {
                bar.style.display = 'none';
            }
        } catch(e) { console.error(e); }
    }

    // GROUP MANAGER LOGIC
    $('btn-new-group')?.addEventListener('click', () => {
        $('group-manager-title').textContent = 'Nuevo Grupo';
        $('group-manager-id').value = '';
        $('group-name').value = '';
        $('group-desc').value = '';
        $('group-is-public').checked = false;
        $('group-avatar-img').src = '';
        $('group-avatar-img').style.display = 'none';
        $('group-manager-modal').style.display = 'flex';
    });

    $('btn-save-group')?.addEventListener('click', async () => {
        const name = $('group-name').value.trim();
        if (!name) return alert('El nombre es obligatorio');
        const desc = $('group-desc').value.trim();
        const isPublic = $('group-is-public').checked ? 1 : 0;
        const fd = new FormData();
        fd.append('action', 'create_channel'); // actually create_group but usually create_channel
        fd.append('name', name);
        fd.append('description', desc);
        fd.append('type', 'group');
        fd.append('is_public', isPublic);
        
        const avatarFile = $('group-avatar-input').files[0];
        if (avatarFile) fd.append('avatar', avatarFile);

        const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            $('group-manager-modal').style.display = 'none';
            loadChannels();
        } else {
            alert(data.error || 'Error al crear grupo');
        }
    });

    $('group-avatar-preview')?.addEventListener('click', () => $('group-avatar-input').click());
    $('group-avatar-input')?.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                $('group-avatar-img').src = e.target.result;
                $('group-avatar-img').style.display = 'block';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal-overlay');
            if (modal) modal.style.display = 'none';
        });
    });

    document.getElementById('info-panel-close')?.addEventListener('click', () => {
        document.getElementById('chat-info-panel').classList.remove('active');
    });

    // POLL AND TASK EVENTS DELEGATION
    chatMessages.addEventListener('click', async (e) => {
        const pollOpt = e.target.closest('.wcard-poll-option');
        if (pollOpt) {
            const msgId = pollOpt.dataset.msgId;
            const idx = pollOpt.dataset.idx;
            const fd = new FormData();
            fd.append('action', 'vote_poll');
            fd.append('message_id', msgId);
            fd.append('option_index', idx);
            const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) updatePollUI(msgId, data.votes, data.my_votes);
        }

        const taskCheck = e.target.closest('.wcard-task-check');
        if (taskCheck) {
            const msgId = taskCheck.dataset.msgId;
            const idx = taskCheck.dataset.idx;
            const fd = new FormData();
            fd.append('action', 'toggle_task');
            fd.append('message_id', msgId);
            fd.append('item_index', idx);
            const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) updateTaskUI(msgId, data.items);
        }
    });

})();
`;

// Replace the IIFE end with our logic
js = js.replace('})();', missingLogic);

fs.writeFileSync(jsFile, js);
console.log('Fix applied successfully.');
