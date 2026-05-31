const fs = require('fs');
const path = require('path');

const jsFile = path.join(__dirname, 'modules/chat/chat.js');
let js = fs.readFileSync(jsFile, 'utf8');

const target = `async function deleteMessage(msgId) {
    const res = await Swal.fire({
        title: '¿Eliminar mensaje?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });
    
    if (res.isConfirmed) {
        const fd = new FormData();
        fd.append('action', 'delete_message');
        fd.append('message_id', msgId);
        await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
        const el = document.getElementById('msg-' + msgId);
        if (el) el.remove();
    }
}`;

const replacement = `async function deleteMessage(msgId) {
    if (confirm('¿Eliminar mensaje?\\n\\nEsta acción no se puede deshacer.')) {
        const fd = new FormData();
        fd.append('action', 'delete_message');
        fd.append('message_id', msgId);
        await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
        
        // El elemento no usa id="msg-X", usa data-id="X"
        const el = document.querySelector(\`.msg-bubble-wrap[data-id="\${msgId}"]\`);
        if (el) {
            const group = el.closest('.msg-group');
            el.remove();
            if (group && group.querySelectorAll('.msg-bubble-wrap').length === 0) {
                group.remove();
            }
        }
    }
}`;

// Reemplazar también el channel action delete para que use confirm en lugar de Swal, si ese era el problema
const channelTarget = `const confirm = await Swal.fire({
                                title: '¿Eliminar este chat?',
                                text: 'Se eliminará de tu lista. Los mensajes no se borran para otros.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Eliminar',
                                cancelButtonText: 'Cancelar',
                                confirmButtonColor: '#ef4444'
                            });
                            if (confirm.isConfirmed) {`;
                            
const channelReplacement = `const confirmDelete = confirm('¿Eliminar este chat?\\n\\nSe eliminará de tu lista. Los mensajes no se borran para otros.');
                            if (confirmDelete) {`;

js = js.replace(target, replacement);
// We might have already stripped whitespace differently. Let's just do a manual string replace.
let success = false;
if (js.includes("async function deleteMessage(msgId) {")) {
    const startIndex = js.indexOf("async function deleteMessage(msgId) {");
    const endIndex = js.indexOf("}", js.indexOf("if (res.isConfirmed) {") + 300) + 1;
    if (startIndex !== -1 && endIndex > startIndex) {
        js = js.substring(0, startIndex) + replacement + js.substring(endIndex);
        success = true;
    }
}

if (js.includes('¿Eliminar este chat?')) {
    const sIdx = js.indexOf('const confirm = await Swal.fire({');
    const eIdx = js.indexOf('if (confirm.isConfirmed) {');
    if (sIdx !== -1 && eIdx !== -1) {
        js = js.substring(0, sIdx) + channelReplacement + js.substring(eIdx + 26);
    }
}

fs.writeFileSync(jsFile, js);
console.log('Update finished. Success:', success);
