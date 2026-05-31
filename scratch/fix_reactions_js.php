<?php
$fileJs = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$contentJs = file_get_contents($fileJs);

// 1. Insert quick actions into renderMessage
$searchRender = '${reactionsHtml}
                    ${isOwn ? `<div class="msg-actions"><button class="btn-delete-msg" data-id="${msg.id}" title="Eliminar para todos"><i class="ph ph-trash"></i></button></div>` : \'\'}';

$replaceRender = '${reactionsHtml}
                    <div class="msg-quick-actions">
                        <button class="reaction-quick-btn" data-msg-id="${msg.id}" title="Reaccionar"><i class="ph ph-smiley"></i></button>
                        <button class="reply-quick-btn" data-msg-id="${msg.id}" title="Responder"><i class="ph ph-arrow-u-down-left"></i></button>
                        ${isOwn ? `<button class="btn-delete-msg" data-id="${msg.id}" title="Eliminar para todos" style="color:var(--danger-color);"><i class="ph ph-trash"></i></button>` : \'\'}
                    </div>';

$contentJs = str_replace($searchRender, $replaceRender, $contentJs);

// 2. Add event listeners for the quick buttons in the main delegated click handler
$searchAddBtn = "const addBtn = e.target.closest('.reaction-add-btn');";
$replaceAddBtn = "const addBtn = e.target.closest('.reaction-add-btn') || e.target.closest('.reaction-quick-btn');
            if (e.target.closest('.reply-quick-btn')) {
                const btn = e.target.closest('.reply-quick-btn');
                const mWrap = btn.closest('.msg-bubble-wrap');
                if (mWrap) startReply(btn.dataset.msgId, mWrap.dataset.sender, mWrap.dataset.text);
            }
            const addBtnOld = e.target.closest('.reaction-add-btn');";

$contentJs = str_replace($searchAddBtn, $replaceAddBtn, $contentJs);

// 3. Fix showEmojiPicker position logic so it doesn't get clipped.
$searchPickerTop = "picker.style.top = Math.max(10, rect.top - 420) + 'px';";
$replacePickerTop = "const pickerHeight = 400;
        if (rect.top > pickerHeight + 20) {
            picker.style.top = (rect.top - pickerHeight - 10) + 'px';
        } else {
            picker.style.top = (rect.bottom + 10) + 'px';
        }";

$contentJs = str_replace($searchPickerTop, $replacePickerTop, $contentJs);

file_put_contents($fileJs, $contentJs);
echo "Fixed quick actions JS!\n";
?>
