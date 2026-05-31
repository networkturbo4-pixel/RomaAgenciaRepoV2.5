const fs = require('fs');
const path = require('path');

const jsFile = path.join(__dirname, 'modules/chat/chat.js');
let js = fs.readFileSync(jsFile, 'utf8');

const oldDnDTarget = `// Drag & Drop for file attachments
const chatMsgs = document.getElementById('chat-messages');
if (chatMsgs) {
    chatMsgs.addEventListener('dragover', e => {
        e.preventDefault();
        chatMsgs.classList.add('drag-over');
    });
    chatMsgs.addEventListener('dragleave', e => {
        e.preventDefault();
        chatMsgs.classList.remove('drag-over');
    });
    chatMsgs.addEventListener('drop', e => {
        e.preventDefault();
        chatMsgs.classList.remove('drag-over');
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            selectedFiles = Array.from(e.dataTransfer.files);
            const isSingleImage = selectedFiles.length === 1 && selectedFiles[0].type.startsWith('image/');
            if (isSingleImage) {
                const reader = new FileReader();
                reader.onload = e => {
                    document.getElementById('image-send-preview').src = e.target.result;
                    document.getElementById('image-send-modal').style.display = 'flex';
                };
                reader.readAsDataURL(selectedFiles[0]);
            } else {
                renderFilePreviews();
            }
        }
    });
}`;

const newDnDTarget = `// Drag & Drop for file attachments
const chatMain = document.getElementById('chat-main');
const chatMsgs = document.getElementById('chat-messages');
if (chatMain && chatMsgs) {
    chatMain.addEventListener('dragover', e => {
        e.preventDefault();
        chatMsgs.classList.add('drag-over');
    });
    chatMain.addEventListener('dragleave', e => {
        e.preventDefault();
        if (e.target === chatMain || e.relatedTarget === null) {
            chatMsgs.classList.remove('drag-over');
        }
    });
    chatMain.addEventListener('drop', e => {
        e.preventDefault();
        chatMsgs.classList.remove('drag-over');
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            selectedFiles = Array.from(e.dataTransfer.files);
            const isSingleImage = selectedFiles.length === 1 && selectedFiles[0].type.startsWith('image/');
            if (isSingleImage) {
                const reader = new FileReader();
                reader.onload = e => {
                    document.getElementById('image-send-preview').src = e.target.result;
                    document.getElementById('image-send-modal').style.display = 'flex';
                };
                reader.readAsDataURL(selectedFiles[0]);
            } else {
                renderFilePreviews();
            }
        }
    });
}`;

if (js.includes('chatMsgs.addEventListener(\'dragover\'')) {
    js = js.replace(oldDnDTarget, newDnDTarget);
    fs.writeFileSync(jsFile, js);
    console.log('Drag and Drop bound to chat-main');
} else {
    console.log('Could not find old DnD target');
}
