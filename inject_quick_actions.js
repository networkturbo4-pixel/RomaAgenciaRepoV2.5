const fs = require('fs');
const path = require('path');

const jsFile = path.join(__dirname, 'modules/chat/chat.js');
let js = fs.readFileSync(jsFile, 'utf8');

// 1. Add quick actions HTML to renderMessages
const searchRender = `                    </div>\`;
                
                if (showHeader) {`;

const quickActionsHtml = `                    <div class="msg-quick-actions">
                        <button class="reaction-quick-btn" data-msg-id="\${msg.id}" title="Reaccionar"><i class="ph ph-smiley"></i></button>
                        <button class="reply-quick-btn" data-msg-id="\${msg.id}" title="Responder"><i class="ph ph-arrow-u-down-left"></i></button>
                        \${isOwn ? \`<button class="btn-delete-msg" onclick="deleteMessage(\${msg.id})" title="Eliminar"><i class="ph ph-trash" style="color:var(--danger-color);"></i></button>\` : ''}
                    </div>
                </div>\`;
                
                if (showHeader) {`;

if (!js.includes('class="msg-quick-actions"')) {
    js = js.replace(searchRender, quickActionsHtml);
}

// 2. Add showEmojiPicker and picker event listener inside the IIFE
const pickerLogic = `
    // QUICK ACTIONS & EMOJI PICKER
    const picker = document.getElementById('emoji-quick-picker');
    if (picker) {
        picker.addEventListener('emoji-click', async (e) => {
            const emoji = e.detail.unicode;
            const msgId = picker.dataset.msgId;
            picker.style.display = 'none';
            
            const fd = new FormData();
            fd.append('action', 'toggle_reaction');
            fd.append('message_id', msgId);
            fd.append('emoji', emoji);
            const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.success) {
                updateReactionsUI(msgId, result.reactions, result.my_reactions);
            }
        });
    }

    window.showEmojiPicker = function(anchor, msgId) {
        if (!picker) return;
        picker.dataset.msgId = msgId;
        picker.style.display = 'block';
        const rect = anchor.getBoundingClientRect();
        const pickerHeight = 400;
        if (rect.top > pickerHeight + 20) {
            picker.style.top = (rect.top - pickerHeight - 10) + 'px';
        } else {
            picker.style.top = (rect.bottom + 10) + 'px';
        }
        picker.style.left = Math.min(rect.left, window.innerWidth - 350) + 'px';
    };

    // Global click to close picker
    document.addEventListener('click', (e) => {
        if (picker && picker.style.display === 'block') {
            if (!e.target.closest('emoji-picker') && !e.target.closest('.reaction-quick-btn') && !e.target.closest('.reaction-add-btn')) {
                picker.style.display = 'none';
            }
        }
    });
`;

if (!js.includes('emoji-quick-picker')) {
    js = js.replace('})();', pickerLogic + '\n})();');
}

// 3. Add reaction quick btn handler to chatMessages delegated click
const delegatedSearch = `const reactBtn = e.target.closest('.msg-reaction-btn') || e.target.closest('.msg-reaction-badge');`;
const delegatedReplace = `
        const quickReactBtn = e.target.closest('.reaction-quick-btn');
        if (quickReactBtn) {
            showEmojiPicker(quickReactBtn, quickReactBtn.dataset.msgId);
        }

        const quickReplyBtn = e.target.closest('.reply-quick-btn');
        if (quickReplyBtn) {
            const mWrap = quickReplyBtn.closest('.msg-bubble-wrap');
            if (mWrap) {
                const sender = mWrap.querySelector('.msg-sender-name')?.textContent.split(',')[0] || 'Alguien';
                const text = mWrap.querySelector('.msg-bubble')?.textContent || 'Multimedia';
                setReply(quickReplyBtn.dataset.msgId, sender, text);
            }
        }

        const reactBtn = e.target.closest('.msg-reaction-btn') || e.target.closest('.msg-reaction-badge');`;

if (!js.includes('quickReactBtn')) {
    js = js.replace(delegatedSearch, delegatedReplace);
}

fs.writeFileSync(jsFile, js);

// 4. Update CSS
const cssFile = path.join(__dirname, 'modules/chat/chat.css');
let css = fs.readFileSync(cssFile, 'utf8');

const cssToAdd = `
.msg-quick-actions {
    position: absolute;
    top: -15px;
    right: 15px;
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    display: none;
    padding: 2px 4px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    z-index: 10;
    gap: 4px;
}
.msg-bubble-wrap.own .msg-quick-actions {
    right: auto;
    left: 15px;
}
.msg-bubble-wrap:hover .msg-quick-actions {
    display: flex;
}
.msg-quick-actions button {
    background: transparent;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 6px;
    border-radius: 6px;
    transition: all 0.2s;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.msg-quick-actions button:hover {
    background: color-mix(in srgb, var(--primary-color) 15%, transparent);
    color: var(--primary-color);
}
`;

if (!css.includes('.msg-quick-actions')) {
    fs.writeFileSync(cssFile, css + '\\n' + cssToAdd);
}

console.log('Quick actions restored.');
