const fs = require('fs');
const path = require('path');

const jsFile = path.join(__dirname, 'modules/chat/chat.js');
let js = fs.readFileSync(jsFile, 'utf8');

// 1. Reemplazar channel delete Swal
const channelSwal = `const confirm = await Swal.fire({
                                title: '¿Eliminar este chat?',
                                text: 'Se eliminará de tu lista. Los mensajes no se borran para otros.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Eliminar',
                                cancelButtonText: 'Cancelar',
                                confirmButtonColor: '#ef4444'
                            });
                            if (confirm.isConfirmed) {`;

const channelConfirm = `if (confirm('¿Eliminar este chat?\\n\\nSe eliminará de tu lista. Los mensajes no se borran para otros.')) {`;

js = js.replace(channelSwal, channelConfirm);

// 2. Reemplazar deleteMessage Swal
const msgSwal = `const res = await Swal.fire({
        title: '¿Eliminar mensaje?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });
    
    if (res.isConfirmed) {`;

const msgConfirm = `if (confirm('¿Eliminar mensaje?\\n\\nEsta acción no se puede deshacer.')) {`;

js = js.replace(msgSwal, msgConfirm);

fs.writeFileSync(jsFile, js);
console.log('Update completed.');
