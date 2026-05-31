<?php
$fileCss = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.css';
$cssAdditions = "

/* FASE 2: Quick Actions on Hover */
.msg-bubble-wrap {
    position: relative;
}
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
";

file_put_contents($fileCss, $cssAdditions, FILE_APPEND);

$fileJs = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$contentJs = file_get_contents($fileJs);

// 1. Insert quick actions into renderMessage
// Find `</div>\`;` right after `${reactionsHtml}` and `${isOwn ? ... delete button ... : ''}`
$searchRender = "                    ${reactionsHtml}
                    ${isOwn ? `<div class=\"msg-actions\"><button class=\"btn-delete-msg\" data-id=\"${msg.id}\" title=\"Eliminar para todos\"><i class=\"ph ph-trash\"></i></button></div>` : ''}
                </div>`;";

$replaceRender = "                    ${reactionsHtml}
                    <div class=\"msg-quick-actions\">
                        <button class=\"reaction-quick-btn\" data-msg-id=\"${msg.id}\" title=\"Reaccionar\"><i class=\"ph ph-smiley\"></i></button>
                        <button class=\"reply-quick-btn\" data-msg-id=\"${msg.id}\" title=\"Responder\"><i class=\"ph ph-arrow-u-down-left\"></i></button>
                        ${isOwn ? `<button class=\"btn-delete-msg\" data-id=\"${msg.id}\" title=\"Eliminar\"><i class=\"ph ph-trash\" style=\"color:var(--danger-color);\"></i></button>` : ''}
                    </div>
                </div>`;";

$contentJs = str_replace($searchRender, $replaceRender, $contentJs);

// 2. Add event listeners for the quick buttons in the main delegated click handler
// We have `chatMessages.addEventListener('click', async (e) => {`
// Search for `const addBtn = e.target.closest('.reaction-add-btn');`
$searchAddBtn = "const addBtn = e.target.closest('.reaction-add-btn');";
$replaceAddBtn = "const addBtn = e.target.closest('.reaction-add-btn') || e.target.closest('.reaction-quick-btn');
            if (e.target.closest('.reply-quick-btn')) {
                const btn = e.target.closest('.reply-quick-btn');
                const mWrap = btn.closest('.msg-bubble-wrap');
                if (mWrap) replyToMessage(btn.dataset.msgId, mWrap.dataset.sender, mWrap.dataset.text);
            }
            const addBtnOld = e.target.closest('.reaction-add-btn');";

$contentJs = str_replace($searchAddBtn, $replaceAddBtn, $contentJs);

// 3. Fix showEmojiPicker position logic so it doesn't get clipped.
// Original: picker.style.top = Math.max(10, rect.top - 420) + 'px';
$searchPickerTop = "picker.style.top = Math.max(10, rect.top - 420) + 'px';";
$replacePickerTop = "
        // Auto-position above or below depending on space
        const pickerHeight = 400;
        if (rect.top > pickerHeight + 20) {
            picker.style.top = (rect.top - pickerHeight - 10) + 'px';
        } else {
            picker.style.top = (rect.bottom + 10) + 'px';
        }
";
$contentJs = str_replace($searchPickerTop, $replacePickerTop, $contentJs);

// Also fix the other instance of picker.style.top if there are two
$contentJs = str_replace("picker.style.top = Math.max(10, rect.top - 420) + 'px';", $replacePickerTop, $contentJs);

// Also fix the fact that clicking anywhere might close it incorrectly, or maybe we just need to ensure z-index is correct.

file_put_contents($fileJs, $contentJs);
echo "Fixed quick actions and picker position.\n";
?>
