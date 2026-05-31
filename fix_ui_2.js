const fs = require('fs');
const path = require('path');

const jsFile = path.join(__dirname, 'modules/chat/chat.js');
let js = fs.readFileSync(jsFile, 'utf8');

// Add deleteMessage if it doesn't exist
const deleteMsgJs = `
async function deleteMessage(msgId) {
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
        
        try {
            const resp = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                // Remove from DOM
                const bubble = document.querySelector(\`.msg-bubble-wrap[data-id="\${msgId}"]\`);
                if (bubble) {
                    const group = bubble.closest('.msg-group');
                    bubble.remove();
                    // If group is empty, remove group
                    if (group && group.querySelectorAll('.msg-bubble-wrap').length === 0) {
                        group.remove();
                    }
                }
                Swal.fire({ title: 'Eliminado', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
            } else {
                Swal.fire('Error', data.error || 'No se pudo eliminar', 'error');
            }
        } catch (e) {
            console.error(e);
        }
    }
}
`;

if (!js.includes('function deleteMessage(')) {
    js += '\n' + deleteMsgJs;
}

// Ensure drag events are fully suppressed on the window
const windowDrag = `
window.addEventListener("dragover", function(e){ e.preventDefault(); }, false);
window.addEventListener("drop", function(e){ e.preventDefault(); }, false);
`;
if (!js.includes('window.addEventListener("dragover"')) {
    js += '\n' + windowDrag;
}

fs.writeFileSync(jsFile, js);
console.log('Final fixes applied.');
