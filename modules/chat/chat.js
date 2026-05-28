// modules/chat/chat.js
(function() {
    'use strict';

    let currentChannelId = null;
    let lastMessageId = 0;
    let pollTimer = null;
    let selectedFile = null;
    let currentCardType = 'client';
    let currentReplyToId = null;

    // ── DOM REFS ──
    const $ = id => document.getElementById(id);
    const channelListGroup = $('channel-list-group');
    const channelListDM = $('channel-list-dm');
    const chatMessages = $('chat-messages');
    const chatEmptyState = $('chat-empty-state');
    const chatInputArea = $('chat-input-area');
    const chatInput = $('chat-input');
    const chatHeader = $('chat-header');
    const channelName = $('chat-channel-name');
    const channelMeta = $('chat-channel-meta');
    const fileInput = $('file-input');
    const filePreview = $('chat-file-preview');
    const filePreviewName = $('file-preview-name');
    const chatSidebar = $('chat-sidebar');

    // ── HELPERS ──
    function getAvatarColor(id, name) {
        if (!id && name) {
            let hash = 0;
            for (let i = 0; i < name.length; i++) {
                hash = name.charCodeAt(i) + ((hash << 5) - hash);
            }
            return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
        }
        return AVATAR_COLORS[(id || 0) % AVATAR_COLORS.length];
    }

    function renderAvatar(user, size) {
        const cls = size === 'sm' ? 'chat-avatar-sm' : 'chat-avatar';
        if (user && user.user_avatar) {
            return `<div class="${cls}" style="background-image:url('${user.user_avatar}');background-size:cover;"></div>`;
        }
        if (user && user.avatar) {
            return `<div class="${cls}" style="background-image:url('${user.avatar}');background-size:cover;"></div>`;
        }
        const name = user?.user_name || user?.name || user?.guest_name || '?';
        const id = user?.user_id || user?.id || 0;
        return `<div class="${cls}" style="background:${getAvatarColor(id, name)}">${name.charAt(0).toUpperCase()}</div>`;
    }

    function formatTime(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
    }
    function formatDate(dateStr) {
        const d = new Date(dateStr);
        const today = new Date();
        if (d.toDateString() === today.toDateString()) return 'Hoy';
        const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);
        if (d.toDateString() === yesterday.toDateString()) return 'Ayer';
        return d.toLocaleDateString('es-PE', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function isImage(path) {
        return /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(path || '');
    }

    // ── LOAD CHANNELS ──
    async function loadChannels() {
        const res = await fetch('modules/chat/ajax.php', {
            method: 'POST', body: new URLSearchParams({ action: 'get_channels' })
        });
        const data = await res.json();
        if (!data.success) return;

        let groupHtml = '', dmHtml = '';
        data.channels.forEach(ch => {
            const badge = ch.unread_count > 0 ? `<span class="channel-badge">${ch.unread_count}</span>` : '';
            const active = ch.id == currentChannelId ? 'active' : '';
            const preview = ch.last_message ? escapeHtml(ch.last_message).substring(0, 35) + (ch.last_message.length > 35 ? '...' : '') : 'Sin mensajes';

            if (ch.type === 'group') {
                groupHtml += `
                    <div class="channel-item ${active}" data-channel-id="${ch.id}">
                        <span class="channel-item-icon"><i class="ph ph-hash"></i></span>
                        <div class="channel-item-info">
                            <div class="channel-item-name">${escapeHtml(ch.name)}</div>
                            <div class="channel-item-preview">${preview}</div>
                        </div>
                        ${badge}
                    </div>`;
            } else {
                const other = ch.other_user || {};
                const onlineDot = other.is_online ? '<span class="online-dot"></span>' : '';
                dmHtml += `
                    <div class="channel-item ${active}" data-channel-id="${ch.id}">
                        <div style="position:relative;">
                            ${renderAvatar(other, 'sm')}
                            ${onlineDot}
                        </div>
                        <div class="channel-item-info">
                            <div class="channel-item-name">${escapeHtml(other.name || 'Usuario')}</div>
                            <div class="channel-item-preview">${preview}</div>
                        </div>
                        ${badge}
                    </div>`;
            }
        });

        channelListGroup.innerHTML = groupHtml || '<div style="padding:0.5rem 1rem; font-size:0.8rem; color:var(--text-muted);">No hay canales</div>';
        channelListDM.innerHTML = dmHtml || '<div style="padding:0.5rem 1rem; font-size:0.8rem; color:var(--text-muted);">No hay conversaciones</div>';

        // Bind click events
        document.querySelectorAll('.channel-item').forEach(el => {
            el.addEventListener('click', () => openChannel(parseInt(el.dataset.channelId)));
        });

        // Auto-open last channel or URL channel
        const urlParams = new URLSearchParams(window.location.search);
        const urlCh = urlParams.get('channel');
        const savedCh = localStorage.getItem('chat_last_channel');
        
        let targetCh = urlCh ? parseInt(urlCh) : (savedCh ? parseInt(savedCh) : null);
        
        if (targetCh && !currentChannelId) {
            // Verify channel actually exists in the list before trying to open it
            const exists = data.channels.some(c => c.id == targetCh);
            if (exists) {
                openChannel(targetCh);
            }
        }
    }

    // ── OPEN CHANNEL ──
    async function openChannel(chId) {
        currentChannelId = chId;
        localStorage.setItem('chat_last_channel', chId);
        lastMessageId = 0;
        chatEmptyState.style.display = 'none';
        chatInputArea.style.display = 'block';

        // Update active state
        document.querySelectorAll('.channel-item').forEach(el => {
            el.classList.toggle('active', parseInt(el.dataset.channelId) === chId);
        });

        // En móvil, deslizamos el panel del chat principal a la vista
        if (window.innerWidth <= 768) {
            $('chat-main').classList.add('is-active');
        }

        // Load messages
        const res = await fetch('modules/chat/ajax.php', {
            method: 'POST', body: new URLSearchParams({ action: 'get_messages', channel_id: chId })
        });
        const data = await res.json();
        if (!data.success) return;

        const ch = data.channel;
        const isGroup = ch.type === 'group';
        channelName.textContent = isGroup ? `# ${ch.name}` : ch.name;
        channelMeta.textContent = `${ch.member_count} miembros · ${ch.online_count} online`;

        // Show header tabs
        if ($('chat-header-tabs')) {
            $('chat-header-tabs').style.display = 'flex';
        }
        if ($('btn-delete-channel')) {
            $('btn-delete-channel').style.display = 'block';
        }

        renderMessages(data.messages, true);
        startPolling();
    }

    // ── RENDER MESSAGES ──
    function renderMessages(messages, fullRender) {
        if (fullRender) chatMessages.innerHTML = '';

        let lastDate = fullRender ? '' : (chatMessages.dataset.lastDate || '');
        let lastUserId = fullRender ? null : parseInt(chatMessages.dataset.lastUser || '0');

        messages.forEach(msg => {
            const msgDate = formatDate(msg.created_at);
            if (msgDate !== lastDate) {
                chatMessages.insertAdjacentHTML('beforeend', `<div class="msg-date-sep">${msgDate}</div>`);
                lastDate = msgDate;
                lastUserId = null;
            }

            const isOwn = msg.user_id == CURRENT_USER_ID;
            const senderId = msg.user_id || ('guest_' + msg.guest_name);

            if (msg.message_type === 'card' && msg.card_data) {
                const card = typeof msg.card_data === 'string' ? JSON.parse(msg.card_data) : msg.card_data;
                chatMessages.insertAdjacentHTML('beforeend', renderCardMessage(msg, card, isOwn));
                lastUserId = null;
            } else {
                // Group consecutive messages from same user
                const showHeader = senderId !== lastUserId;
                let html = '';
                const senderName = msg.user_name || msg.guest_name || 'Guest';

                if (showHeader) {
                    const timeStr = formatTime(msg.created_at);
                    
                    html += `<div class="msg-group ${isOwn ? 'own' : ''}">
                        <div class="msg-group-header ${isOwn ? 'own' : ''}">
                            <span class="msg-sender-name">${isOwn ? 'You, ' + timeStr : escapeHtml(senderName) + ', ' + timeStr}</span>
                        </div>`;
                }

                const bubbleClass = isOwn ? 'msg-bubble own' : 'msg-bubble';
                let content = escapeHtml(msg.message || '').replace(/\n/g, '<br>');

                // Attachment
                let attachHtml = '';
                if (msg.attachment) {
                    if (isImage(msg.attachment)) {
                        attachHtml = `<img src="${msg.attachment}" class="msg-file-img" onclick="window.open('${msg.attachment}','_blank')">`;
                    } else {
                        attachHtml = `<br><a href="${msg.attachment}" target="_blank" class="msg-file" style="color:inherit;text-decoration:underline;"><i class="ph ph-file-arrow-down"></i> ${escapeHtml(msg.attachment_name || 'Archivo')}</a>`;
                    }
                }

                // Bloque de respuesta (Reply Quote)
                let replyHtml = '';
                if (msg.reply_to_id) {
                    const rName = escapeHtml(msg.reply_user_name || msg.reply_guest_name || 'Alguien');
                    let rText = escapeHtml(msg.reply_message || '');
                    if (msg.reply_message_type === 'file') rText = '📁 ' + escapeHtml(msg.reply_attachment_name || 'Archivo');
                    else if (msg.reply_message_type === 'card') rText = '📄 Tarjeta compartida';
                    
                    if (rText.length > 50) rText = rText.substring(0, 50) + '...';

                    replyHtml = `
                    <div class="msg-reply-quote" onclick="const el=document.querySelector('[data-id=\\'${msg.reply_to_id}\\']');if(el){el.scrollIntoView({behavior:'smooth', block:'center'});el.classList.remove('highlighted-msg');void el.offsetWidth;el.classList.add('highlighted-msg');}">
                        <div class="msg-reply-name">${rName}</div>
                        <div>${rText}</div>
                    </div>`;
                }

                let bubbleHtml = `<div class="msg-bubble-wrap ${isOwn ? 'own' : ''}" data-id="${msg.id}" data-sender="${escapeHtml(senderName)}" data-text="${escapeHtml(msg.message || 'Archivo')}">
                    ${(!isOwn && showHeader) ? renderAvatar(msg) : (!isOwn ? '<div style="min-width:36px;"></div>' : '')}
                    <div class="${bubbleClass}">
                        ${replyHtml}
                        ${content}${attachHtml}
                    </div>
                    ${isOwn ? `<div class="msg-actions"><button class="btn-delete-msg" data-id="${msg.id}" title="Eliminar para todos"><i class="ph ph-trash"></i></button></div>` : ''}
                </div>`;
                
                if (showHeader) {
                    html += bubbleHtml + '</div>';
                    chatMessages.insertAdjacentHTML('beforeend', html);
                } else {
                    // Si ya hay un grupo, añadimos la burbuja dentro del último msg-group
                    const lastGroup = chatMessages.lastElementChild;
                    if (lastGroup && lastGroup.classList.contains('msg-group')) {
                        lastGroup.insertAdjacentHTML('beforeend', bubbleHtml);
                    } else {
                        chatMessages.insertAdjacentHTML('beforeend', `<div class="msg-group ${isOwn ? 'own' : ''}">${bubbleHtml}</div>`);
                    }
                }
            }

            lastUserId = senderId;
            if (msg.id > lastMessageId) lastMessageId = msg.id;
        });

        chatMessages.dataset.lastDate = lastDate;
        chatMessages.dataset.lastUser = lastUserId;

        // Auto-scroll
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // ── RENDER CARD MESSAGE ──
    function renderCardMessage(msg, card, isOwn) {
        const typeIcons = { client: 'ph-user', quote: 'ph-file-text', service: 'ph-package', month: 'ph-calendar' };
        const typeLabels = { client: 'Cliente', quote: 'Cotización', service: 'Servicio', month: 'Mes de Calendario' };
        const typeColors = { client: '#3b82f6', quote: '#f59e0b', service: '#8b5cf6', month: '#10b981' };

        const icon = typeIcons[card.card_type] || 'ph-squares-four';
        const label = typeLabels[card.card_type] || 'Card';
        const color = card.color || typeColors[card.card_type] || 'var(--primary-color)';

        let fieldsHtml = '';
        (card.fields || []).forEach(f => {
            fieldsHtml += `<div class="msg-card-field">
                <span class="msg-card-field-label">${escapeHtml(f.label)}</span>
                <span class="msg-card-field-value">${escapeHtml(f.value)}</span>
            </div>`;
        });

        const senderName = msg.user_name || msg.guest_name || 'Sistema';
        return `
        <div class="msg-group">
            <div class="msg-group-header">
                ${isOwn ? '' : renderAvatar(msg)}
                <span class="msg-sender-name">${escapeHtml(senderName)}</span>
                <span class="msg-time">${formatTime(msg.created_at)}</span>
            </div>
            <div class="msg-card" style="margin-left:${isOwn ? 'auto' : '44px'};">
                <div class="msg-card-header" style="color:${color}; background:color-mix(in srgb, ${color} 8%, transparent);">
                    <i class="ph ${icon}"></i> ${label}
                </div>
                <div class="msg-card-body">
                    <div style="font-weight:700; margin-bottom:0.35rem; color:var(--text-main);">${escapeHtml(card.title || '')}</div>
                    ${fieldsHtml}
                </div>
                ${card.link ? `<div class="msg-card-footer"><a href="${card.link}" class="msg-card-link"><i class="ph ph-arrow-square-out"></i> Ver detalle</a></div>` : ''}
            </div>
        </div>`;
    }

    // ── SEND MESSAGE ──
    async function sendMessage() {
        const text = chatInput.value.trim();
        if (!text && !selectedFile) return;
        if (!currentChannelId) return;

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('channel_id', currentChannelId);
        formData.append('message', text);
        formData.append('message_type', 'text');
        if (currentReplyToId) {
            formData.append('reply_to_id', currentReplyToId);
        }
        if (selectedFile) formData.append('attachment', selectedFile);

        chatInput.value = '';
        chatInput.style.height = 'auto';
        clearFile();
        clearReply();

        const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success && data.message) {
            renderMessages([data.message], false);
            loadChannels(); // refresh sidebar
            triggerPush(currentChannelId, text || 'Envió un archivo');
        }
    }

    // ── POLLING ──
    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(async () => {
            if (!currentChannelId) return;
            const res = await fetch('modules/chat/ajax.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'poll_updates', channel_id: currentChannelId, last_id: lastMessageId })
            });
            const data = await res.json();
            if (data.success && data.messages.length > 0) {
                renderMessages(data.messages, false);
            }
            // Update unread badges
            if (data.unreads) {
                document.querySelectorAll('.channel-item').forEach(el => {
                    const chId = el.dataset.channelId;
                    const badge = el.querySelector('.channel-badge');
                    const count = data.unreads[chId] || 0;
                    if (parseInt(chId) === currentChannelId) return; // skip active
                    if (count > 0) {
                        if (badge) { badge.textContent = count; }
                        else { el.insertAdjacentHTML('beforeend', `<span class="channel-badge">${count}</span>`); }
                    } else if (badge) {
                        badge.remove();
                    }
                });
            }
        }, 3000);
    }

    // ── FILE HANDLING ──
    function clearFile() {
        selectedFile = null;
        fileInput.value = '';
        filePreview.style.display = 'none';
    }

    // ── CARD SHARING ──
    async function searchCards(type, query) {
        const res = await fetch('modules/chat/ajax.php', {
            method: 'POST', body: new URLSearchParams({ action: 'search_items', type: type, q: query })
        });
        const data = await res.json();
        const container = $('card-search-results');
        if (!data.success || !data.results.length) {
            container.innerHTML = '<div style="padding:1rem; text-align:center; color:var(--text-muted); font-size:0.85rem;">No se encontraron resultados</div>';
            return;
        }

        container.innerHTML = data.results.map(item => {
            let title = '', meta = '', cardData = {};

            if (type === 'client') {
                title = item.name;
                meta = `${item.whatsapp || 'Sin WhatsApp'} · ${item.email || 'Sin email'}`;
                cardData = {
                    card_type: 'client', card_id: item.id, title: item.name, color: '#3b82f6',
                    fields: [
                        { label: 'WhatsApp', value: item.whatsapp || 'N/A' },
                        { label: 'Email', value: item.email || 'N/A' }
                    ],
                    link: `index.php?module=clients&action=index`
                };
            } else if (type === 'quote') {
                title = `Cotización #${item.id}`;
                meta = `${item.client_name || 'Sin cliente'} · ${item.currency} ${parseFloat(item.total).toLocaleString()} · ${item.status}`;
                cardData = {
                    card_type: 'quote', card_id: item.id, title: `Cotización #COT-${String(item.id).padStart(3, '0')}`, color: '#f59e0b',
                    fields: [
                        { label: 'Cliente', value: item.client_name || 'N/A' },
                        { label: 'Total', value: `${item.currency} ${parseFloat(item.total).toLocaleString()}` },
                        { label: 'Estado', value: item.status },
                        { label: 'Vence', value: item.due_date || 'N/A' }
                    ],
                    link: `index.php?module=quotes&action=form&id=${item.id}`
                };
            } else if (type === 'service') {
                title = item.name;
                meta = `${item.category_name || 'Sin categoría'} · S/ ${parseFloat(item.price).toLocaleString()}`;
                cardData = {
                    card_type: 'service', card_id: item.id, title: item.name, color: '#8b5cf6',
                    fields: [
                        { label: 'Categoría', value: item.category_name || 'N/A' },
                        { label: 'Precio', value: `S/ ${parseFloat(item.price).toLocaleString()}` },
                        { label: 'Descripción', value: (item.description || '').substring(0, 60) }
                    ],
                    link: `index.php?module=services&action=index`
                };
            } else if (type === 'month') {
                title = `${item.brand_name} - ${MONTH_NAMES[item.month] || ''} ${item.year}`;
                meta = `${item.post_count || 0} publicaciones · ${item.status || 'N/A'}`;
                cardData = {
                    card_type: 'month', card_id: item.id, title: title, color: '#10b981',
                    fields: [
                        { label: 'Marca', value: item.brand_name },
                        { label: 'Mes', value: `${MONTH_NAMES[item.month] || ''} ${item.year}` },
                        { label: 'Posts', value: `${item.post_count || 0} publicaciones` },
                        { label: 'Estado', value: item.status || 'N/A' }
                    ],
                    link: `index.php?module=calendar&action=index`
                };
            }

            return `<div class="card-result-item" data-card='${JSON.stringify(cardData).replace(/'/g, "&#39;")}'>
                <div class="card-result-title">${escapeHtml(title)}</div>
                <div class="card-result-meta">${escapeHtml(meta)}</div>
            </div>`;
        }).join('');

        // Bind click to send card
        container.querySelectorAll('.card-result-item').forEach(el => {
            el.addEventListener('click', async () => {
                const cardData = JSON.parse(el.dataset.card);
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('channel_id', currentChannelId);
                formData.append('message', '');
                formData.append('message_type', 'card');
                formData.append('card_data', JSON.stringify(cardData));

                const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success && data.message) {
                    renderMessages([data.message], false);
                    loadChannels();
                }
                $('share-card-modal').classList.remove('active');
            });
        });
    }

    // ── EVENT BINDINGS ──
    function init() {
        loadChannels();

        // Send message
        $('btn-send').addEventListener('click', sendMessage);
        chatInput.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });

        // Auto-resize textarea
        chatInput.addEventListener('input', () => {
            chatInput.style.height = 'auto';
            chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
        });

        // File attach
        $('btn-attach-file').addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
            if (fileInput.files[0]) {
                selectedFile = fileInput.files[0];
                filePreviewName.textContent = selectedFile.name;
                filePreview.style.display = 'flex';
            }
        });
        $('btn-remove-file').addEventListener('click', clearFile);

        // Share card
        $('btn-share-card')?.addEventListener('click', () => {
            if (!currentChannelId) return;
            const firstTab = document.querySelector('.card-type-tab');
            if (!firstTab) return; // Si no hay permisos para ninguna
            
            // Activar la primera pestaña disponible
            document.querySelectorAll('.card-type-tab').forEach(t => t.classList.remove('active'));
            firstTab.classList.add('active');
            currentCardType = firstTab.dataset.type;

            $('share-card-modal').classList.add('active');
            $('card-search-input').value = '';
            $('card-search-results').innerHTML = '';
            searchCards(currentCardType, '');
        });

        // Card type tabs
        document.querySelectorAll('.card-type-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.card-type-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                currentCardType = tab.dataset.type;
                searchCards(currentCardType, $('card-search-input').value);
            });
        });

        $('card-search-input').addEventListener('input', function() {
            searchCards(currentCardType, this.value);
        });

        // New channel
        $('btn-new-channel')?.addEventListener('click', () => {
            $('new-ch-name').value = '';
            $('new-ch-desc').value = '';
            $('new-channel-modal').classList.add('active');
        });
        $('btn-save-channel')?.addEventListener('click', async () => {
            const name = $('new-ch-name').value.trim();
            if (!name) return;
            const members = [...document.querySelectorAll('.new-ch-member:checked')].map(cb => cb.value);
            const res = await fetch('modules/chat/ajax.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'create_channel', name, description: $('new-ch-desc').value, members: JSON.stringify(members) })
            });
            const data = await res.json();
            if (data.success) {
                $('new-channel-modal').classList.remove('active');
                await loadChannels();
                openChannel(data.channel_id);
            }
        });

        // New DM
        $('btn-new-dm')?.addEventListener('click', () => $('new-dm-modal').classList.add('active'));
        document.querySelectorAll('.dm-user-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const res = await fetch('modules/chat/ajax.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'create_dm', other_user_id: btn.dataset.userId })
                });
                const data = await res.json();
                if (data.success) {
                    $('new-dm-modal').classList.remove('active');
                    await loadChannels();
                    openChannel(data.channel_id);
                }
            });
        });

        // Invite link
        $('btn-channel-invite')?.addEventListener('click', () => {
            $('invite-modal').classList.add('active');
            // Check if channel already has a token
            const channelNameText = channelName.textContent;
            checkInviteStatus();
        });

        async function checkInviteStatus() {
            const res = await fetch('modules/chat/ajax.php', {
                method: 'POST', body: new URLSearchParams({ action: 'get_messages', channel_id: currentChannelId })
            });
            const data = await res.json();
            if (data.channel && data.channel.public_token) {
                const baseUrl = window.location.origin + window.location.pathname;
                $('invite-link-input').value = `${baseUrl}?module=chat&action=public&token=${data.channel.public_token}`;
                $('invite-link-area').style.display = 'block';
                $('btn-generate-invite').style.display = 'none';
            } else {
                $('invite-link-area').style.display = 'none';
                $('btn-generate-invite').style.display = 'block';
            }
        }

        $('btn-generate-invite')?.addEventListener('click', async () => {
            const res = await fetch('modules/chat/ajax.php', {
                method: 'POST', body: new URLSearchParams({ action: 'generate_invite', channel_id: currentChannelId })
            });
            const data = await res.json();
            if (data.success) checkInviteStatus();
        });

        $('btn-copy-invite')?.addEventListener('click', () => {
            navigator.clipboard.writeText($('invite-link-input').value);
            $('btn-copy-invite').innerHTML = '<i class="ph ph-check"></i>';
            setTimeout(() => { $('btn-copy-invite').innerHTML = '<i class="ph ph-copy"></i>'; }, 2000);
        });

        $('btn-revoke-invite')?.addEventListener('click', async () => {
            const res = await fetch('modules/chat/ajax.php', {
                method: 'POST', body: new URLSearchParams({ action: 'revoke_invite', channel_id: currentChannelId })
            });
            if ((await res.json()).success) checkInviteStatus();
        });

        // ── TABS LOGIC ──
        document.querySelectorAll('.chat-tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active from all tabs and panes
                document.querySelectorAll('.chat-tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.chat-sidebar-pane').forEach(p => p.classList.remove('active'));
                
                // Add active to clicked tab and its target pane
                this.classList.add('active');
                const targetId = this.getAttribute('data-target');
                document.getElementById(targetId).classList.add('active');
            });
        });

        // ── MOBILE BACK BUTTON ──
        if ($('btn-back-chat')) {
            $('btn-back-chat').addEventListener('click', () => {
                $('chat-main').classList.remove('is-active');
            });
        }

        // Push notifications are now global
    }

    // ── TRIGGER PUSH (after sending message) ──
    async function triggerPush(channelId, messageText) {
        try {
            await fetch('modules/chat/ajax_push.php', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'send_push',
                    channel_id: channelId,
                    sender_id: CURRENT_USER_ID,
                    sender_name: CURRENT_USER_NAME,
                    sender_avatar: CURRENT_USER_AVATAR,
                    message_body: messageText
                })
            });
        } catch (e) { /* silent fail */ }
    }

    // ── GESTIÓN DE RESPUESTAS (REPLY) ──
    function startReply(msgId, senderName, text) {
        currentReplyToId = msgId;
        $('reply-preview-name').textContent = senderName;
        // Truncate text
        $('reply-preview-text').textContent = text.length > 60 ? text.substring(0, 60) + '...' : text;
        $('reply-preview-box').style.display = 'flex';
        $('chat-input').focus();
    }

    function clearReply() {
        currentReplyToId = null;
        if($('reply-preview-box')) $('reply-preview-box').style.display = 'none';
        if($('reply-preview-name')) $('reply-preview-name').textContent = '';
        if($('reply-preview-text')) $('reply-preview-text').textContent = '';
    }

    if ($('btn-close-reply')) {
        $('btn-close-reply').addEventListener('click', clearReply);
    }

    // Doble clic para responder
    chatMessages.addEventListener('dblclick', (e) => {
        const wrap = e.target.closest('.msg-bubble-wrap');
        if (wrap && wrap.dataset.id) {
            startReply(wrap.dataset.id, wrap.dataset.sender, wrap.dataset.text);
            window.getSelection().removeAllRanges(); // Quitar selección de texto
        }
    });

    // Deslizar (Swipe left) para responder en móvil
    let touchStartX = 0;
    let touchStartY = 0;
    let swipeTarget = null;
    chatMessages.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
        touchStartY = e.changedTouches[0].screenY;
        swipeTarget = e.target.closest('.msg-bubble-wrap');
    }, {passive: true});

    chatMessages.addEventListener('touchend', (e) => {
        if (!swipeTarget) return;
        let touchEndX = e.changedTouches[0].screenX;
        let touchEndY = e.changedTouches[0].screenY;
        // Swipe a la izquierda
        if (touchStartX - touchEndX > 40 && Math.abs(touchStartY - touchEndY) < 30) {
            if (swipeTarget.dataset.id) {
                startReply(swipeTarget.dataset.id, swipeTarget.dataset.sender, swipeTarget.dataset.text);
            }
        }
    });

    // ── ELIMINAR MENSAJES Y CANALES ──
    chatMessages.addEventListener('click', async (e) => {
        const btnDelete = e.target.closest('.btn-delete-msg');
        if (btnDelete) {
            const msgId = btnDelete.dataset.id;
            const result = await Swal.fire({
                title: '¿Estás seguro?',
                text: "No podrás revertir esta acción.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });
            if (!result.isConfirmed) return;

            try {
                const fd = new FormData();
                fd.append('action', 'delete_message');
                fd.append('message_id', msgId);
                const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd }).then(r => r.json());
                if (res.success) {
                    lastMessageId = 0; // Forzar recarga completa
                    loadChannels(); // Actualizar lista
                    // Recargar mensajes actuales
                    const mRes = await fetch('modules/chat/ajax.php', {
                        method: 'POST', body: new URLSearchParams({ action: 'get_messages', channel_id: currentChannelId })
                    }).then(r => r.json());
                    if (mRes.success) renderMessages(mRes.messages, true);
                } else {
                    Swal.fire('Error', res.error || 'No autorizado', 'error');
                }
            } catch (err) { console.error(err); }
        }
    });

    if ($('btn-delete-channel')) {
        $('btn-delete-channel').addEventListener('click', async () => {
            if (!currentChannelId) return;
            
            const result = await Swal.fire({
                title: '¿Eliminar Chat?',
                text: "Todos los mensajes se perderán para todos los participantes. Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar canal',
                cancelButtonText: 'Cancelar'
            });
            if (!result.isConfirmed) return;
            
            try {
                const fd = new FormData();
                fd.append('action', 'delete_channel');
                fd.append('channel_id', currentChannelId);
                const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd }).then(r => r.json());
                if (res.success) {
                    closeChannel();
                    loadChannels();
                } else {
                    Swal.fire('Error', res.error || 'No autorizado para eliminar este chat.', 'error');
                }
            } catch (err) { console.error(err); }
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
