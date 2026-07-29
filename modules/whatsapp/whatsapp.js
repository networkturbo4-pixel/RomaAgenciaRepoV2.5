(function() {
    let currentContactId = null;
    let currentContactJid = null;
    let pollInterval = null;
    let qrPollInterval = null;
    let lastMessageTimestamp = 0;

    const $ = id => document.getElementById(id);

    // Initial load
    checkStatus();

    // Event Listeners
    if ($('btn-wa-send')) $('btn-wa-send').addEventListener('click', sendMessage);
    if ($('wa-message-input')) $('wa-message-input').addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });
    
    if ($('btn-wa-attach')) $('btn-wa-attach').addEventListener('click', () => {
        $('wa-file-input').click();
    });

    if ($('wa-file-input')) $('wa-file-input').addEventListener('change', async function() {
        if (this.files.length > 0) {
            await sendMediaMessage(this.files[0]);
            this.value = ''; // clear input
        }
    });
    
    if ($('btn-wa-info')) $('btn-wa-info').addEventListener('click', () => {
        $('wa-info-panel').style.display = 'flex';
        $('wa-main').style.marginRight = '0';
    });
    if ($('btn-wa-close-info')) $('btn-wa-close-info').addEventListener('click', () => {
        $('wa-info-panel').style.display = 'none';
    });

    if ($('btn-wa-assign')) $('btn-wa-assign').addEventListener('click', () => {
        $('wa-info-panel').style.display = 'flex';
        $('wa-assign-select').focus();
    });

    if ($('wa-assign-select')) $('wa-assign-select').addEventListener('change', async function() {
        if (!currentContactId) return;
        const userId = this.value;
        const fd = new FormData();
        fd.append('action', 'assign_chat');
        fd.append('contact_id', currentContactId);
        fd.append('user_id', userId);

        try {
            await fetch('modules/whatsapp/ajax.php', { method: 'POST', body: fd });
            loadContacts(); // refresh list
            updateAssignmentBadge(userId);
        } catch (e) {
            console.error(e);
        }
    });

    if ($('btn-manage-labels')) $('btn-manage-labels').addEventListener('click', loadAllLabels);

    async function checkStatus() {
        try {
            const res = await fetch(WA_BRIDGE_URL + '/api/status');
            const data = await res.json();
            
            const statusInd = document.querySelector('.status-indicator');
            const statusText = document.querySelector('.connection-status span');

            if (data.status === 'CONNECTED' || data.status === 'AUTHENTICATED') {
                $('wa-qr-overlay').style.display = 'none';
                statusInd.className = 'status-indicator connected';
                statusText.innerText = 'Conectado';
                if (qrPollInterval) clearInterval(qrPollInterval);
                
                loadContacts();
                if (!pollInterval) {
                    pollInterval = setInterval(loadContacts, 3000);
                }
            } else if (data.status === 'QR_READY' || data.qr) {
                $('wa-qr-overlay').style.display = 'flex';
                statusInd.className = 'status-indicator qr';
                statusText.innerText = 'Esperando QR';
                fetchQR();
                if (!qrPollInterval) qrPollInterval = setInterval(fetchQR, 2000);
            } else {
                $('wa-qr-overlay').style.display = 'flex';
                statusInd.className = 'status-indicator';
                statusText.innerText = 'Desconectado';
                $('qr-code-wrapper').innerHTML = '<div class="qr-loader">Iniciando WhatsApp Bridge...</div>';
                fetchQR();
                if (!qrPollInterval) qrPollInterval = setInterval(fetchQR, 3000);
            }
        } catch (e) {
            console.error('Error checking status', e);
            const statusInd = document.querySelector('.status-indicator');
            const statusText = document.querySelector('.connection-status span');
            $('wa-qr-overlay').style.display = 'flex';
            statusInd.className = 'status-indicator';
            statusText.innerText = 'Servidor Caído';
            $('qr-code-wrapper').innerHTML = `
                <div class="qr-loader" style="color:#ef4444; padding:1rem; text-align:center;">
                    <b>¡Error de conexión!</b><br>
                    El microservicio de Node.js no está corriendo o está bloqueado por el firewall.<br><br>
                    Asegúrate de ejecutar <code>node app.js</code> en la carpeta <code>whatsapp_service</code>.
                </div>`;
        }
    }

    async function fetchQR() {
        try {
            const res = await fetch(WA_BRIDGE_URL + '/api/status');
            const data = await res.json();
            
            if (data.status === 'CONNECTED' || data.status === 'AUTHENTICATED') {
                checkStatus(); 
            } else if (data.qr) {
                // QR is base64 string from qrcode-terminal? No, we need an actual image.
                // Wait, whatsapp.js has to generate data URL. 
                // We'll use an external API to generate image from string or Node can generate it.
                // Actually if wa-bridge just sets qrCodeData = qr (string), we can use a JS library to render it.
                // Let's use Google Charts API for quick QR rendering
                const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(data.qr)}`;
                $('qr-code-wrapper').innerHTML = `<img src="${qrUrl}" alt="QR Code">`;
                $('qr-status-text').innerText = 'Escanea este código con tu teléfono';
            }
        } catch (e) {
            console.log('Bridge might be down', e);
            $('qr-code-wrapper').innerHTML = '<div class="qr-loader" style="color:red;">No se puede conectar al servidor de WhatsApp (Puerto 3200). Asegúrate de que wa-bridge está corriendo.</div>';
        }
    }

    async function loadContacts() {
        try {
            const res = await fetch(WA_BRIDGE_URL + '/api/chats');
            const data = await res.json();
            
            if (res.ok) {
                renderContacts(data);
                if (currentContactId) {
                    loadMessages(currentContactId, true); // true = silent refresh
                }
            }
        } catch (e) {}
    }

    function renderContacts(contacts) {
        const list = $('wa-contact-list');
        list.innerHTML = '';
        
        contacts.forEach(c => {
            const div = document.createElement('div');
            div.className = `wa-contact-item ${currentContactId == c.id ? 'active' : ''}`;
            div.onclick = () => selectContact(c);
            
            let timeStr = '';
            if (c.timestamp) {
                const d = new Date(c.timestamp * 1000);
                timeStr = d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
            }
            
            const initial = c.name ? c.name.charAt(0).toUpperCase() : '?';

            div.innerHTML = `
                <div class="wa-avatar">${initial}</div>
                <div class="wa-contact-info">
                    <div class="wa-contact-top">
                        <div class="wa-contact-name">${c.name || c.id.split('@')[0]}</div>
                        <div class="wa-contact-time">${timeStr}</div>
                    </div>
                    <div class="wa-contact-bottom">${c.unreadCount > 0 ? c.unreadCount + ' mensajes nuevos' : ''}</div>
                </div>
            `;
            list.appendChild(div);
        });
    }

    function selectContact(c) {
        currentContactId = c.id;
        
        $('wa-empty-state').style.display = 'none';
        $('wa-header').style.display = 'flex';
        $('wa-messages').style.display = 'flex';
        $('wa-input-area').style.display = 'flex';
        
        const phoneClean = c.id.split('@')[0];
        $('wa-channel-name').innerText = c.name || phoneClean;
        $('wa-channel-status').innerText = phoneClean;
        currentContactJid = c.id;
        
        $('wa-header-avatar').innerText = (c.name ? c.name.charAt(0).toUpperCase() : '?');
        
        // Info panel
        $('wa-info-name').innerText = c.name || phoneClean;
        $('wa-info-phone').innerText = phoneClean;
        
        // highlight in list
        document.querySelectorAll('.wa-contact-item').forEach(el => el.classList.remove('active'));
        
        loadMessages(c.id);
    }

    function updateAssignmentBadge(userId) {
        if (!userId || userId == 0) {
            $('wa-assignment-badge').style.display = 'none';
            $('wa-assign-select').value = "";
        } else {
            // Find name
            const user = ALL_USERS.find(u => u.id == userId);
            if (user) {
                $('wa-assignment-badge').style.display = 'flex';
                $('wa-assigned-name').innerText = `Asignado a: ${user.name.split(' ')[0]}`;
                $('wa-assign-select').value = userId;
            }
        }
    }

    function renderLabels(labels) {
        const cont = $('wa-labels-container');
        cont.innerHTML = '';
        labels.forEach(l => {
            cont.innerHTML += `<span class="wa-badge" style="background:${l.color}">${l.name}</span>`;
        });
    }

    async function loadMessages(contactId, silent = false) {
        try {
            const res = await fetch(WA_BRIDGE_URL + '/api/chats/' + encodeURIComponent(contactId) + '/messages');
            const data = await res.json();
            
            if (res.ok) {
                const msgCont = $('wa-messages');
                
                // Check if we need to auto scroll
                const isAtBottom = msgCont.scrollHeight - msgCont.scrollTop <= msgCont.clientHeight + 50;
                
                msgCont.innerHTML = '';
                
                data.reverse().forEach(m => {
                    const div = document.createElement('div');
                    const direction = m.fromMe ? 'out' : 'in';
                    div.className = `msg-bubble ${direction}`;
                    
                    let timeStr = '';
                    if (m.timestamp) {
                        const d = new Date(m.timestamp * 1000); // timestamp is usually in seconds from wa web
                        timeStr = d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
                    }
                    
                    let content = m.body || '';
                    
                    let statusIcon = '';
                    if (direction === 'out') {
                        statusIcon = '<i class="ph ph-check msg-status"></i>';
                    }

                    div.innerHTML = `
                        <div class="msg-text">${content}</div>
                        <div class="msg-meta">
                            <span>${timeStr}</span>
                            ${statusIcon}
                        </div>
                    `;
                    msgCont.appendChild(div);
                });
                
                if (!silent || isAtBottom) {
                    msgCont.scrollTop = msgCont.scrollHeight;
                }
            }
        } catch(e) { console.error('Error loading messages', e); }
    }

    async function sendMessage() {
        const input = $('wa-message-input');
        const text = input.value.trim();
        if (!text || !currentContactId) return;
        
        input.value = '';
        
        // Find JID
        const contactJid = currentContactJid;

        try {
            const res = await fetch(WA_BRIDGE_URL + '/api/send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    number: contactJid,
                    message: text
                })
            });
            
            const data = await res.json();
            if (data.success) {
                // Instantly append message to DOM because loadMessages is broken
                const msgCont = $('wa-messages');
                const div = document.createElement('div');
                div.className = `msg-bubble out`;
                const d = new Date();
                const timeStr = d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
                div.innerHTML = `
                    <div class="msg-text">${text}</div>
                    <div class="msg-meta">
                        <span>${timeStr}</span>
                        <i class="ph ph-check msg-status"></i>
                    </div>
                `;
                msgCont.appendChild(div);
                msgCont.scrollTop = msgCont.scrollHeight;
            } else {
                Swal.fire('Error', data.error || 'No se pudo enviar el mensaje', 'error');
            }
        } catch (e) {
            console.error('Error sending', e);
        }
    }

    async function sendMediaMessage(file) {
        if (!currentContactJid) return;
        
        const input = $('wa-message-input');
        const text = input.value.trim();
        input.value = '';

        const fd = new FormData();
        fd.append('file', file);
        fd.append('to', currentContactJid);
        fd.append('body', text);
        fd.append('userId', CURRENT_USER_ID);

        // Show a temporary loading state
        Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        try {
            const res = await fetch(WA_BRIDGE_URL + '/api/send-media', {
                method: 'POST',
                body: fd
            });
            
            const data = await res.json();
            Swal.close();
            if (data.success) {
                // Instantly append message to DOM
                const msgCont = $('wa-messages');
                const div = document.createElement('div');
                div.className = `msg-bubble out`;
                const d = new Date();
                const timeStr = d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
                div.innerHTML = `
                    <div class="msg-text">
                        <i class="ph ph-image"></i> Archivo multimedia enviado <br>
                        ${text}
                    </div>
                    <div class="msg-meta">
                        <span>${timeStr}</span>
                        <i class="ph ph-check msg-status"></i>
                    </div>
                `;
                msgCont.appendChild(div);
                msgCont.scrollTop = msgCont.scrollHeight;
            } else {
                Swal.fire('Error', data.error || 'No se pudo enviar el archivo', 'error');
            }
        } catch (e) {
            Swal.close();
            console.error('Error sending media', e);
            Swal.fire('Error', 'No se pudo conectar al servidor', 'error');
        }
    }

    // Label Management Modal
    async function loadAllLabels() {
        $('wa-labels-modal').style.display = 'flex';
        const res = await fetch('modules/whatsapp/ajax.php?action=get_all_labels');
        const data = await res.json();
        
        if (data.success) {
            const list = $('wa-all-labels-list');
            list.innerHTML = '';
            
            // Need current contact labels to show checks
            // We get them from the UI state since we rendered them
            const currentLabels = Array.from($('wa-labels-container').children).map(c => c.innerText);

            data.labels.forEach(l => {
                const isChecked = currentLabels.includes(l.name);
                list.innerHTML += `
                    <div class="label-item">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <div style="width:12px; height:12px; border-radius:50%; background:${l.color};"></div>
                            <span>${l.name}</span>
                        </div>
                        <input type="checkbox" ${isChecked ? 'checked' : ''} onchange="toggleLabel(${l.id})">
                    </div>
                `;
            });
        }
    }

    window.toggleLabel = async function(labelId) {
        if (!currentContactId) return;
        const fd = new FormData();
        fd.append('action', 'toggle_label');
        fd.append('contact_id', currentContactId);
        fd.append('label_id', labelId);
        
        await fetch('modules/whatsapp/ajax.php', { method: 'POST', body: fd });
        loadContacts(); // reload labels
    };

    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal-overlay').style.display = 'none';
        });
    });

})();
