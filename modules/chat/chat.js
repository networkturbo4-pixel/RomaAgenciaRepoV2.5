// modules/chat/chat.js
(function() {
    'use strict';

    let currentChannelId = null;
    let lastMessageId = 0;
    let pollTimer = null;
    let selectedFiles = [];
    let currentCardType = 'client';
    let currentReplyToId = null;

    // Grabación de voz asíncrona
    let mediaRecorder = null;
    let audioChunks = [];
    let recordingTimer = null;
    let recordingSeconds = 0;
    let isRecordingCancelled = false;

    // ── DOM REFS ──
    const $ = id => document.getElementById(id);
    const channelListUnified = $('channel-list-unified');
    const chatMessages = $('chat-messages');
    const chatEmptyState = $('chat-empty-state');
    const chatInputArea = $('chat-input-area');
    const chatInput = $('chat-input');
    const chatHeader = $('chat-header');
    const channelName = document.getElementById('chat-channel-name');
    const channelMeta = document.getElementById('chat-channel-meta');
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

    function isAudio(path) {
        return /\.(mp3|wav|ogg|webm|m4a)$/i.test(path || '');
    }

    // ── LOAD CHANNELS ──
    async function loadChannels() {
        const res = await fetch('modules/chat/ajax.php', {
            method: 'POST', body: new URLSearchParams({ action: 'get_channels' })
        });
        const data = await res.json();
        if (!data.success) return;

        let currentFilter = document.querySelector('.chat-filter-pill.active')?.dataset.filter || 'all';
        let unifiedHtml = '';
        data.channels.forEach(ch => {
            const isGroup = ch.type === 'group';
            if (currentFilter === 'group' && !isGroup) return;
            if (currentFilter === 'direct' && isGroup) return;

            const badge = ch.unread_count > 0 ? `<span class="channel-badge">${ch.unread_count}</span>` : '';
            const active = ch.id == currentChannelId ? 'active' : '';
            const preview = ch.last_message ? escapeHtml(ch.last_message).substring(0, 35) + (ch.last_message.length > 35 ? '...' : '') : 'Sin mensajes';

            if (isGroup) {
                const isPublicIcon = ch.is_public ? '<i class="ph ph-globe" style="margin-left:4px; font-size:0.8rem; opacity:0.7;" title="Público"></i>' : '';
                const avatarUrl = ch.avatar ? (ch.avatar.startsWith('http') ? ch.avatar : 'uploads/chat_avatars/' + ch.avatar) : null;
                const avatarHtml = avatarUrl 
                    ? `<div class="channel-item-icon" style="background-image:url('${avatarUrl}'); background-size:cover; border-radius:50%; border:none;"></div>` 
                    : `<div class="channel-item-icon" style="background:${getAvatarColor(ch.id, ch.name)}; color:#fff; display:flex; align-items:center; justify-content:center; border:none; font-weight:bold; border-radius:50%;">${escapeHtml(ch.name.charAt(0).toUpperCase())}</div>`;
                
                unifiedHtml += `
                    <div class="channel-item ${active}" data-channel-id="${ch.id}">
                        ${avatarHtml}
                        <div class="channel-item-info">
                            <div class="channel-item-name">${escapeHtml(ch.name)}${isPublicIcon}</div>
                            <div class="channel-item-preview">${preview}</div>
                        </div>
                        ${badge}
                        ${ch.is_pinned == 1 ? '<i class="ph ph-push-pin" style="margin-left:auto; color:var(--text-muted); font-size:0.8rem;"></i>' : ''}
                    </div>`;
            } else {
                const other = ch.other_user || {};
                const onlineDot = other.is_online ? '<span class="online-dot"></span>' : '';
                unifiedHtml += `
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
                        ${ch.is_pinned == 1 ? '<i class="ph ph-push-pin" style="margin-left:auto; color:var(--text-muted); font-size:0.8rem;"></i>' : ''}
                    </div>`;
            }
        });

        if (channelListUnified) {
            channelListUnified.innerHTML = unifiedHtml || '<div style="padding:0.5rem 1rem; font-size:0.8rem; color:var(--text-muted);">No hay canales</div>';
        }

        // Bind click events
        document.querySelectorAll('.channel-item').forEach(el => {
            el.addEventListener('click', () => openChannel(parseInt(el.dataset.channelId)));

            // Right-click context menu for channels
            el.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const chId = el.dataset.channelId;
                const isPinned = el.querySelector('.ph-push-pin') !== null;

                // Remove existing menu
                document.querySelector('.channel-ctx-menu')?.remove();

                const ch = data.channels.find(c => c.id == chId);
                const isGroup = ch && ch.type === 'group';
                const menu = document.createElement('div');
                menu.className = 'channel-ctx-menu';
                menu.innerHTML = `
                    ${isGroup ? `
                    <div class="channel-ctx-option" data-action="edit">
                        <i class="ph ph-pencil-simple"></i>
                        <span>Editar grupo</span>
                    </div>` : ''}
                    <div class="channel-ctx-option" data-action="${isPinned ? 'unpin' : 'pin'}">
                        <i class="ph ph-push-pin"></i>
                        <span>${isPinned ? 'Desfijar chat' : 'Fijar chat'}</span>
                    </div>
                    <div class="channel-ctx-option danger" data-action="delete">
                        <i class="ph ph-trash"></i>
                        <span>Eliminar chat</span>
                    </div>
                `;
                document.body.appendChild(menu);

                // Position
                const menuW = 180, menuH = 90;
                let left = e.clientX;
                let top = e.clientY;
                if (left + menuW > window.innerWidth) left = window.innerWidth - menuW - 8;
                if (top + menuH > window.innerHeight) top = window.innerHeight - menuH - 8;
                menu.style.left = left + 'px';
                menu.style.top = top + 'px';

                // Bind actions
                menu.querySelectorAll('.channel-ctx-option').forEach(opt => {
                    opt.addEventListener('click', async () => {
                        const action = opt.dataset.action;
                        menu.remove();

                        if (action === 'pin' || action === 'unpin') {
                            const fd = new FormData();
                            fd.append('action', 'channel_action');
                            fd.append('channel_id', chId);
                            fd.append('type', action);
                            await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
                            loadChannels();
                        } else if (action === 'edit') {
                            const fd = new FormData();
                            fd.append('action', 'get_channel');
                            fd.append('channel_id', chId);
                            const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
                            const data = await res.json();
                            if (data.success) {
                                const chInfo = data.channel;
                                $('group-manager-title').textContent = 'Editar Grupo';
                                $('group-manager-id').value = chInfo.id;
                                $('group-name').value = chInfo.name;
                                $('group-desc').value = chInfo.description || '';
                                if($('group-is-public')) $('group-is-public').checked = parseInt(chInfo.is_public) === 1;
                                if($('group-requires-approval')) $('group-requires-approval').checked = parseInt(chInfo.requires_approval) === 1;
                                if($('group-is-secret')) $('group-is-secret').checked = parseInt(chInfo.is_secret) === 1;
                                if($('group-secret-password')) $('group-secret-password').value = '';

                                if (chInfo.avatar) {
                                    $('group-avatar-img').src = chInfo.avatar.startsWith('http') ? chInfo.avatar : 'uploads/chat_avatars/' + chInfo.avatar; // assuming avatar path logic is handled somewhere or just the raw DB value
                                    $('group-avatar-img').style.display = 'block';
                                } else {
                                    $('group-avatar-img').src = '';
                                    $('group-avatar-img').style.display = 'none';
                                }

                                document.querySelectorAll('.group-member-cb').forEach(cb => {
                                    cb.checked = data.members.includes(cb.value);
                                });

                                $('group-manager-modal').style.display = '';
                                $('group-manager-modal').classList.add('active');
                            } else {
                                alert(data.error || 'Error al cargar datos del grupo');
                            }
                        } else if (action === 'delete') {
                            const confirmDelete = confirm('¿Eliminar este chat?\n\nSe eliminará de tu lista. Los mensajes no se borran para otros.');
                            if (confirmDelete) {
                                const fd = new FormData();
                                fd.append('action', 'delete_channel');
                                fd.append('channel_id', chId);
                                await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
                                if (parseInt(chId) === currentChannelId) {
                                    currentChannelId = null;
                                    chatMessages.innerHTML = '';
                                    chatEmptyState.style.display = 'flex';
                                    chatInputArea.style.display = 'none';
                                }
                                loadChannels();
                            }
                        }
                    });
                });

                // Close on outside click
                const closeMenu = (ev) => {
                    if (!menu.contains(ev.target)) {
                        menu.remove();
                        document.removeEventListener('click', closeMenu);
                    }
                };
                setTimeout(() => document.addEventListener('click', closeMenu), 10);
            });
        });

        // Auto-open URL channel
        const urlParams = new URLSearchParams(window.location.search);
        const urlCh = urlParams.get('channel');
        
        let targetCh = urlCh ? parseInt(urlCh) : null;
        
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
        chatMessages.style.display = 'flex';

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
        channelName.textContent = ch.name;
        channelMeta.textContent = `${ch.member_count} miembros · ${ch.online_count} online`;
        channelMeta.style.display = 'block';
        
        if (isGroup) {
            // Header Image
            if (ch.avatar) {
                const avatarUrl = ch.avatar.startsWith('http') ? ch.avatar : 'uploads/chat_avatars/' + ch.avatar;
                $('chat-header-avatar').style.backgroundImage = `url('${avatarUrl}')`;
                $('chat-header-avatar').style.backgroundColor = 'transparent';
                $('chat-header-avatar').style.display = 'block';
                $('chat-header-avatar').innerHTML = '';
            } else {
                $('chat-header-avatar').style.backgroundImage = 'none';
                $('chat-header-avatar').style.backgroundColor = getAvatarColor(ch.id, ch.name);
                $('chat-header-avatar').style.color = '#fff';
                $('chat-header-avatar').style.display = 'flex';
                $('chat-header-avatar').style.alignItems = 'center';
                $('chat-header-avatar').style.justifyContent = 'center';
                $('chat-header-avatar').style.fontWeight = 'bold';
                $('chat-header-avatar').innerHTML = escapeHtml(ch.name.charAt(0).toUpperCase());
            }
        } else {
            // Direct message avatar
            $('chat-header-avatar').style.display = 'none';
        }

        // Show header info button for groups
        if (isGroup) {
            $('btn-group-info').style.display = 'block';
        } else {
            $('btn-group-info').style.display = 'none';
        }

        // Populate Right Sidebar (Members & Media)
        if (data.channel_members) {
            const memList = $('crs-members-list');
            if (memList) {
                memList.innerHTML = '';
                data.channel_members.forEach(m => {
                    const avatarStr = m.avatar ? `background-image:url('${m.avatar}')` : `background-color:#DFDFEB`;
                    const onlineDot = m.is_online ? `<div class="online-dot"></div>` : '';
                    const vipBadge = m.is_vip ? `<i class="ph-fill ph-star" style="color:var(--warning-color); font-size:0.8rem;" title="VIP"></i>` : '';
                    memList.insertAdjacentHTML('beforeend', `
                        <div style="display:flex; align-items:center; gap:0.5rem; padding:0.25rem 0;">
                            <div class="chat-avatar-sm" style="${avatarStr}; position:relative; color:#fff;">
                                ${!m.avatar ? escapeHtml(m.name.charAt(0).toUpperCase()) : ''}
                                ${onlineDot}
                            </div>
                            <div style="flex:1; min-width:0; display:flex; align-items:center; gap:0.25rem;">
                                <span style="font-size:0.85rem; font-weight:600; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(m.name)}</span>
                                ${vipBadge}
                            </div>
                        </div>
                    `);
                });
            }
        }

        if (data.channel_media) {
            const mediaList = $('crs-media');
            const mediaCount = $('crs-media-count');
            if (mediaList && mediaCount) {
                mediaList.innerHTML = '';
                mediaCount.textContent = data.channel_media.length;
                data.channel_media.forEach(m => {
                    if (m.attachment.match(/\.(jpeg|jpg|gif|png|webp)$/i)) {
                        mediaList.insertAdjacentHTML('beforeend', `
                            <div style="width:100%; aspect-ratio:1; border-radius:8px; overflow:hidden; background:url('${m.attachment}') center/cover; cursor:pointer;" onclick="openLightbox('${m.attachment}')"></div>
                        `);
                    } else {
                        // Document icon
                        mediaList.insertAdjacentHTML('beforeend', `
                            <a href="${m.attachment}" target="_blank" style="width:100%; aspect-ratio:1; border-radius:8px; overflow:hidden; background:var(--bg-color); display:flex; align-items:center; justify-content:center; text-decoration:none; border:1px solid var(--border-color);">
                                <i class="ph ph-file-text" style="font-size:1.5rem; color:var(--primary-color);"></i>
                            </a>
                        `);
                    }
                });
            }
        }

        renderMessages(data.messages, true);
        
        // Hide sidebar if empty on load (optional)
        if (!isGroup) {
            $('chat-info-panel').style.display = 'none';
        }
        startPolling();
    }

    // ── CHAT INFO PANEL ──
    async function openInfoPanel() {
        if (!currentChannelId) return;
        const panel = document.getElementById('chat-info-panel');
        panel.classList.add('active');

        // Set channel name
        document.getElementById('info-panel-name').textContent = $('chat-channel-name').textContent;

        const fd = new FormData();
        fd.append('action', 'get_channel_media');
        fd.append('channel_id', currentChannelId);
        const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) return;

        // Media grid
        document.getElementById('info-media-count').textContent = data.media.length;
        document.getElementById('info-media-grid').innerHTML = data.media.map(m => 
            `<div class="info-media-item"><img src="${m.attachment}" loading="lazy" onclick="window.open('${m.attachment}','_blank')"></div>`
        ).join('');

        // Documents
        document.getElementById('info-docs-count').textContent = data.docs.length;
        document.getElementById('info-docs-list').innerHTML = data.docs.map(d => {
            const name = d.attachment_name || d.attachment.split('/').pop();
            return `<div class="info-doc-item" onclick="window.open('${d.attachment}','_blank')">
                <div class="info-doc-icon"><i class="ph ph-file-text"></i></div>
                <span class="info-doc-name">${escapeHtml(name)}</span>
            </div>`;
        }).join('');

        // Links
        document.getElementById('info-links-count').textContent = data.links.length;
        document.getElementById('info-links-list').innerHTML = data.links.map(l => {
            const urlMatch = l.message.match(/https?:\/\/[^\s]+/);
            const url = urlMatch ? urlMatch[0] : '#';
            return `<a class="info-link-item" href="${url}" target="_blank"><i class="ph ph-link"></i> ${escapeHtml(url.substring(0, 45))}${url.length > 45 ? '...' : ''}</a>`;
        }).join('');

        // Pinned
        document.getElementById('info-pinned-count').textContent = data.pinned.length;
        document.getElementById('info-pinned-list').innerHTML = data.pinned.map(p => 
            `<div class="info-pinned-item"><div class="pinned-user">${escapeHtml(p.user_name || 'Usuario')}</div><div class="pinned-text">${escapeHtml((p.message || 'Archivo adjunto').substring(0, 80))}</div></div>`
        ).join('');

        // Members
        document.getElementById('info-members-count').textContent = data.members.length;
        document.getElementById('info-members-list').innerHTML = data.members.map(m => {
            const av = m.avatar ? `<img src="${m.avatar}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">` : `<div style="width:32px;height:32px;border-radius:50%;background:#10b981;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.8rem;">${(m.name||'U').charAt(0).toUpperCase()}</div>`;
            return `<div class="info-member-item">${av}<span class="info-member-name">${escapeHtml(m.name)}</span>${m.is_online ? '<div class="info-member-online"></div>' : ''}</div>`;
        }).join('');
    }

    function closeInfoPanel() {
        document.getElementById('chat-info-panel')?.classList.remove('active');
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
            } else if (msg.message_type === 'poll' && msg.card_data) {
                const poll = typeof msg.card_data === 'string' ? JSON.parse(msg.card_data) : msg.card_data;
                chatMessages.insertAdjacentHTML('beforeend', renderPollMessage(msg, poll, isOwn));
                lastUserId = null;
            } else if (msg.message_type === 'task' && msg.card_data) {
                const task = typeof msg.card_data === 'string' ? JSON.parse(msg.card_data) : msg.card_data;
                chatMessages.insertAdjacentHTML('beforeend', renderTaskMessage(msg, task, isOwn));
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
                            <span class="msg-sender-name">${isOwn ? 'Tú, ' + timeStr : escapeHtml(senderName) + ', ' + timeStr}</span>
                        </div>`;
                }

                let bubbleClass = isOwn ? 'msg-bubble own' : 'msg-bubble';
                if (msg.is_vip == 1) bubbleClass += ' vip-bubble';
                
                // Parse markdown & sanitize
                let parsedText = marked.parse(msg.message || '');
                let content = DOMPurify.sanitize(parsedText);
                
                // Regex para multimedia
                const ytRegex = /(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w\-]+)(?:\S+)?/g;
                const tiktokRegex = /https?:\/\/(?:www\.)?tiktok\.com\/@[\w.-]+\/video\/(\d+)/g;
                const mp4Regex = /(https?:\/\/\S+\.mp4)/g;
                
                content = content.replace(ytRegex, '<div class="video-container"><iframe src="https://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe></div>');
                content = content.replace(tiktokRegex, '<div class="video-container"><iframe src="https://www.tiktok.com/embed/v2/$1" frameborder="0" allowfullscreen></iframe></div>');
                content = content.replace(mp4Regex, '<div class="video-container"><video controls src="$1" style="max-width:100%; border-radius:8px;"></video></div>');


                // Mentions Regex
                const mentionRegex = /@(\w+)/g;
                content = content.replace(mentionRegex, '<span class="mention" style="color:var(--primary-color); font-weight:700;">@$1</span>');

                // Attachment
                let attachHtml = '';
                if (msg.attachment) {
                    if (isImage(msg.attachment)) {
                        attachHtml = `<img src="${msg.attachment}" class="msg-file-img" onclick="openLightbox('${msg.attachment}')">`;
                    } else if (isAudio(msg.attachment)) {
                        let barsHtml = '';
                        const seed = msg.id || 1;
                        for(let i=0; i<35; i++) {
                            const h = 20 + ((Math.sin(seed * i) * 0.5 + 0.5) * 50) + (Math.random() * 30);
                            barsHtml += `<div class="wa-bar" style="height: ${Math.min(100, Math.max(15, h))}%;"></div>`;
                        }
                        const avatarSrc = escapeHtml(msg.user_avatar || 'assets/default_avatar.png');
                        attachHtml = `
                            <div class="wa-audio-player">
                                <div class="wa-audio-avatar">
                                    <img src="${avatarSrc}" onerror="this.src='assets/default_avatar.png'">
                                    <i class="ph-fill ph-microphone wa-audio-mic"></i>
                                </div>
                                <button class="wa-play-btn" onclick="toggleWaAudio(this)"><i class="ph-fill ph-play"></i></button>
                                <div class="wa-audio-waveform-wrapper">
                                    <div class="wa-audio-waveform" onclick="seekWaAudio(event, this)">
                                        <div class="wa-waveform-knob"></div>
                                        ${barsHtml}
                                    </div>
                                    <div class="wa-audio-time">0:00</div>
                                </div>
                                <audio src="${msg.attachment}" preload="metadata" onloadedmetadata="initWaAudio(this)" ontimeupdate="updateWaAudio(this)" onended="resetWaAudio(this)"></audio>
                            </div>
                        `;
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

                // Reactions
                let reactionsHtml = '';
                if (msg.reactions && msg.reactions.length > 0) {
                    reactionsHtml = '<div class="msg-reactions-list">';
                    msg.reactions.forEach(r => {
                        const isVoted = msg.my_reactions && msg.my_reactions.includes(r.emoji);
                        reactionsHtml += `<div class="msg-reaction-badge ${isVoted ? 'voted' : ''}" data-msg-id="${msg.id}" data-emoji="${r.emoji}">
                            <span>${r.emoji}</span><span>${r.count}</span>
                        </div>`;
                    });
                    reactionsHtml += '</div>';
                }

                // Wrapper
                                let metaHtml = '';
                const mTime = formatTime(msg.created_at);
                if (isOwn) {
                    let ticks = '<i class="ph ph-check"></i>'; // Default 1 tick
                    // Assuming if we have status, maybe it's passed or handled via poll later,
                    // but let's give it the base structure.
                    metaHtml = `<div class="msg-meta"><span>${mTime}</span><span class="msg-status">${ticks}</span></div>`;
                } else {
                    metaHtml = `<div class="msg-meta"><span>${mTime}</span></div>`;
                }
                let bubbleHtml = `
                    <div class="msg-bubble-wrap ${isOwn ? 'own' : ''}" data-id="${msg.id}" style="position:relative;">
                        ${(!isOwn && showHeader) ? renderAvatar(msg) : (!isOwn ? '<div style="min-width:36px;"></div>' : '')}
                        <div class="${bubbleClass}">
                            ${replyHtml}
                            ${content}
                            ${attachHtml}
                            ${!isOwn ? `
                                <div class="msg-actions">
                                    <button class="chat-icon-btn-sm" onclick="setReply(${msg.id}, '${escapeHtml(senderName)}', '${escapeHtml(msg.message || 'Multimedia')}')" title="Responder"><i class="ph ph-arrow-u-up-left"></i></button>
                                </div>
                            ` : `
                                <div class="msg-actions">
                                    <button class="btn-delete-msg" onclick="deleteMessage(${msg.id})" title="Eliminar"><i class="ph ph-trash"></i></button>
                                </div>
                            `}
                            ${reactionsHtml}
                            ${metaHtml}
                        </div>
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

            // Confetti Check (only on new messages)
            if (!fullRender && msg.message) {
                const text = msg.message.toLowerCase();
                if (text.includes('felicidades') || text.includes('feliz cumple') || text.includes('bravo') || text.includes('excelente')) {
                    triggerConfetti();
                }
            }
            
            // Notification sound check
            if (!fullRender && !isOwn) {
                playNotificationSound();
            }

            lastUserId = senderId;
            if (msg.id > lastMessageId) lastMessageId = msg.id;
        });

        chatMessages.dataset.lastDate = lastDate;
        chatMessages.dataset.lastUser = lastUserId;

        // Initialize background setting
        if (CURRENT_USER_BG && CURRENT_USER_BG !== 'default') {
            applyChatBackground(CURRENT_USER_BG);
        }

        // Auto-scroll
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // ── DELEGATED EVENT LISTENERS (Polls & Tasks) ──
    chatMessages.addEventListener('click', async (e) => {
        // Poll Option Click
        const pollOption = e.target.closest('.wcard-poll-option');
        if (pollOption) {
            const msgId = pollOption.dataset.msgId;
            const idx = pollOption.dataset.idx;
            const formData = new FormData();
            formData.append('action', 'vote_poll');
            formData.append('message_id', msgId);
            formData.append('option_index', idx);
            formData.append('allow_multiple', false);
            
            try {
                const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: formData });
                const text = await res.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch(e) {
                    Swal.fire('Error JS', 'El servidor respondió con texto inválido: ' + text.substring(0, 50), 'error');
                    console.error('Invalid JSON from server:', text);
                    return;
                }
                
                if (result.success) {
                    updatePollUI(msgId, result.poll_votes, result.my_votes);
                    Swal.fire({title: 'Voto Registrado', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500});
                } else {
                    Swal.fire('Error al votar', result.error || 'Desconocido', 'error');
                }
            } catch (err) {
                Swal.fire('Error de Conexión', err.message, 'error');
                console.error(err);
            }
        }

        // Task Item Click
        const taskItem = e.target.closest('.task-item');
        if (taskItem) {
            const msgId = taskItem.dataset.msgId;
            const idx = taskItem.dataset.idx;
            
            const formData = new FormData();
            formData.append('action', 'toggle_task');
            formData.append('message_id', msgId);
            formData.append('task_index', idx);
            
            const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success && result.items) {
                updateTaskUI(msgId, result.items);
            }
        }

        // Reaction Button or Badge Click
        const reactBtn = e.target.closest('.msg-reaction-btn') || e.target.closest('.msg-reaction-badge');
        if (reactBtn) {
            const msgId = reactBtn.dataset.msgId;
            const emoji = reactBtn.dataset.emoji;
            const fd = new FormData();
            fd.append('action', 'toggle_reaction');
            fd.append('message_id', msgId);
            fd.append('emoji', emoji);
            const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.success) {
                updateReactionsUI(msgId, result.reactions, result.my_reactions);
            }
        }
    });

    // ── CONTEXT MENU (iMessage style) ──
    let ctxOverlay = null;
    let ctxMenu = null;
    let longPressTimer = null;
    let longPressFired = false;

    function closeContextMenu() {
        if (ctxMenu) { ctxMenu.classList.remove('show'); ctxMenu.remove(); ctxMenu = null; }
        if (ctxOverlay) ctxOverlay.style.display = 'none';
    }

    function openContextMenu(bubble, posX, posY) {
        const msgId = bubble.dataset.id;
        const isOwn = bubble.classList.contains('own');
        const bubbleEl = bubble.querySelector('.msg-bubble');
        const textContent = bubbleEl ? bubbleEl.innerText : '';
        const msgTime = bubble.querySelector('.msg-time')?.innerText || '';

        // Create overlay once
        if (!ctxOverlay) {
            ctxOverlay = document.createElement('div');
            ctxOverlay.className = 'chat-ctx-overlay';
            ctxOverlay.addEventListener('click', closeContextMenu);
            document.body.appendChild(ctxOverlay);
        }

        // Remove old menu
        if (ctxMenu) ctxMenu.remove();

        // Build new menu
        ctxMenu = document.createElement('div');
        ctxMenu.className = 'chat-context-menu';
        ctxMenu.innerHTML = `
            <div class="chat-ctx-reactions">
                <button class="ctx-react-btn" data-emoji="❤️">❤️</button>
                <button class="ctx-react-btn" data-emoji="😂">😂</button>
                <button class="ctx-react-btn" data-emoji="😮">😮</button>
                <button class="ctx-react-btn" data-emoji="😢">😢</button>
                <button class="ctx-react-btn" data-emoji="👍">👍</button>
                <button class="ctx-react-btn ctx-react-plus"><i class="ph ph-plus-circle"></i></button>
            </div>
            <div class="ctx-emoji-picker" id="ctx-emoji-picker"></div>
            <div class="chat-ctx-card">
                <div class="ctx-card-time">${msgTime}</div>
                <ul class="chat-ctx-options">
                    <li data-action="reply"><span>Responder</span><i class="ph ph-arrow-bend-up-left"></i></li>
                    <li data-action="forward"><span>Reenviar</span><i class="ph ph-share-fat"></i></li>
                    <li data-action="copy"><span>Copiar</span><i class="ph ph-copy"></i></li>
                    <li data-action="pin"><span>Fijar</span><i class="ph ph-push-pin"></i></li>
                    <li data-action="delete" class="danger" style="display:${isOwn ? 'flex' : 'none'}"><span>Eliminar</span><i class="ph ph-trash"></i></li>
                </ul>
            </div>
        `;

        // Build emoji picker grid
        const allEmojis = [
            '😀','😃','😄','😁','😆','😅','🤣','😂','🙂','😊',
            '😇','🥰','😍','🤩','😘','😗','😚','😙','🥲','😋',
            '😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🫡',
            '🤐','🤨','😐','😑','😶','🫥','😏','😒','🙄','😬',
            '😮‍💨','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕',
            '🤢','🤮','🥵','🥶','🥴','😵','🤯','🤠','🥳','🥸',
            '😎','🤓','🧐','😕','🫤','😟','🙁','☹️','😮','😯',
            '😲','😳','🥺','🥹','😦','😧','😨','😰','😥','😢',
            '😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤',
            '😡','😠','🤬','😈','👿','💀','☠️','💩','🤡','👹',
            '❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔',
            '❤️‍🔥','💕','💞','💓','💗','💖','💘','💝','💟','♥️',
            '👍','👎','👏','🙌','🤝','🤲','🤜','🤛','✊','👊',
            '🫶','🙏','✌️','🤞','🫰','🤟','🤘','🤙','👋','🫱',
            '🔥','⭐','🌟','💫','🎉','🎊','🎈','🎁','🏆','🥇'
        ];
        const pickerEl = ctxMenu.querySelector('#ctx-emoji-picker');
        let gridHtml = '<div class="ctx-emoji-picker-grid">';
        allEmojis.forEach(em => {
            gridHtml += `<button class="picker-emoji-btn" data-emoji="${em}">${em}</button>`;
        });
        gridHtml += '</div>';
        pickerEl.innerHTML = gridHtml;

        document.body.appendChild(ctxMenu);
        ctxOverlay.style.display = 'block';

        // Smart positioning
        requestAnimationFrame(() => {
            const menuW = ctxMenu.offsetWidth;
            const menuH = ctxMenu.offsetHeight;
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const pad = 12;

            // Horizontal: try to center on click, clamp to screen
            let left = posX - menuW / 2;
            if (left + menuW > vw - pad) left = vw - menuW - pad;
            if (left < pad) left = pad;

            // Vertical: prefer above click point, fall back to below
            let top = posY - menuH - 10;
            if (top < pad) top = posY + 10;
            if (top + menuH > vh - pad) top = vh - menuH - pad;

            ctxMenu.style.left = left + 'px';
            ctxMenu.style.top = top + 'px';
            ctxMenu.classList.add('show');
        });

        // Bind reaction clicks
        ctxMenu.querySelectorAll('.ctx-react-btn[data-emoji]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const emoji = btn.dataset.emoji;
                const fd = new FormData();
                fd.append('action', 'toggle_reaction');
                fd.append('message_id', msgId);
                fd.append('emoji', emoji);
                closeContextMenu();
                const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
                const result = await res.json();
                if (result.success) updateReactionsUI(msgId, result.reactions, result.my_reactions);
            });
        });

        // Bind option clicks
        ctxMenu.querySelectorAll('[data-action]').forEach(li => {
            li.addEventListener('click', () => {
                const action = li.dataset.action;
                if (action === 'reply') {
                    const sender = isOwn ? 'Tú' : (bubble.closest('.msg-group')?.querySelector('.msg-sender-name')?.innerText || 'Usuario');
                    setReply(msgId, sender, textContent);
                } else if (action === 'copy') {
                    navigator.clipboard.writeText(textContent);
                    Swal.fire({ title: 'Copiado', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
                } else if (action === 'delete') {
                    deleteMessage(msgId);
                } else if (action === 'pin') {
                    closeContextMenu();
                    Swal.fire({
                        title: 'Selecciona por cuánto tiempo quieres fijar el mensaje',
                        html: `
                            <p style="text-align: left; color: #a0aeb6; font-size: 0.95rem; margin-bottom: 1.5rem; margin-top: 0;">Puedes desfijarlo en cualquier momento.</p>
                            <div class="pin-options" style="text-align: left; display: flex; flex-direction: column; gap: 1rem;">
                                <label class="pin-radio" style="display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                                    <input type="radio" name="pin_dur" value="24h" style="width: 20px; height: 20px; accent-color: #25d366;">
                                    <span style="color: #e9edef; font-size: 1rem;">24 horas</span>
                                </label>
                                <label class="pin-radio" style="display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                                    <input type="radio" name="pin_dur" value="7d" checked style="width: 20px; height: 20px; accent-color: #25d366;">
                                    <span style="color: #e9edef; font-size: 1rem;">7 días</span>
                                </label>
                                <label class="pin-radio" style="display: flex; align-items: center; gap: 1rem; cursor: pointer;">
                                    <input type="radio" name="pin_dur" value="30d" style="width: 20px; height: 20px; accent-color: #25d366;">
                                    <span style="color: #e9edef; font-size: 1rem;">30 días</span>
                                </label>
                            </div>
                        `,
                        background: '#1f2c34', // WhatsApp dark bg
                        color: '#e9edef',
                        showCancelButton: true,
                        confirmButtonText: 'Fijar',
                        cancelButtonText: 'Cancelar',
                        buttonsStyling: false,
                        customClass: {
                            confirmButton: 'wa-btn wa-btn-primary',
                            cancelButton: 'wa-btn wa-btn-cancel',
                            popup: 'wa-popup',
                            title: 'wa-title'
                        },
                        preConfirm: () => {
                            const checked = document.querySelector('input[name="pin_dur"]:checked');
                            return checked ? checked.value : '7d';
                        }
                    }).then(async (result) => {
                        if (result.isConfirmed) {
                            const fd = new FormData();
                            fd.append('action', 'pin_message');
                            fd.append('message_id', msgId);
                            fd.append('channel_id', currentChannelId);
                            fd.append('pin_duration', result.value);
                            const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
                            const data = await res.json();
                            if (data.success) {
                                Swal.fire({ title: 'Fijado', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
                                loadPinnedMessages();
                            }
                        }
                    });
                    return; // exit to avoid closeContextMenu() again
                }
                closeContextMenu();
            });
        });

        // Bind "+" button to toggle emoji picker
        ctxMenu.querySelector('.ctx-react-plus').addEventListener('click', () => {
            pickerEl.classList.toggle('open');
        });

        // Bind picker emoji clicks
        pickerEl.querySelectorAll('.picker-emoji-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const emoji = btn.dataset.emoji;
                const fd = new FormData();
                fd.append('action', 'toggle_reaction');
                fd.append('message_id', msgId);
                fd.append('emoji', emoji);
                closeContextMenu();
                const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
                const result = await res.json();
                if (result.success) updateReactionsUI(msgId, result.reactions, result.my_reactions);
            });
        });
    }

    // Desktop: right-click
    chatMessages.addEventListener('contextmenu', (e) => {
        const bubble = e.target.closest('.msg-bubble-wrap');
        if (!bubble) return;
        e.preventDefault();
        openContextMenu(bubble, e.clientX, e.clientY);
    });

    // Mobile: long-press (500ms)
    chatMessages.addEventListener('touchstart', (e) => {
        const bubble = e.target.closest('.msg-bubble-wrap');
        if (!bubble) return;
        longPressFired = false;
        longPressTimer = setTimeout(() => {
            longPressFired = true;
            const touch = e.touches[0];
            openContextMenu(bubble, touch.clientX, touch.clientY);
            // Vibrate if supported
            if (navigator.vibrate) navigator.vibrate(30);
        }, 500);
    }, { passive: true });

    chatMessages.addEventListener('touchmove', () => {
        clearTimeout(longPressTimer);
    }, { passive: true });

    chatMessages.addEventListener('touchend', (e) => {
        clearTimeout(longPressTimer);
        if (longPressFired) {
            e.preventDefault();
            longPressFired = false;
        }
    });

    // ── MEDIA REPLACEMENTS / UTILS ──
    function applyChatBackground(type) {
        const layer = document.getElementById('chat-bg-layer');
        if (!layer) return;
        if (type === 'particles') {
            layer.innerHTML = '<div class="bg-particles"></div>';
        } else if (type === 'gradient') {
            layer.innerHTML = '<div class="bg-gradient-move"></div>';
        } else {
            layer.innerHTML = '';
        }
    }

    // ── RENDER CARD MESSAGE ──
    function renderCardMessage(msg, card, isOwn) {
        const typeIcons = { client: 'ph-user', quote: 'ph-file-text', service: 'ph-package', month: 'ph-calendar' };
        const typeLabels = { client: 'Cliente', quote: 'Cotización', service: 'Servicio', month: 'Calendario' };
        const typeColors = { client: '#3b82f6', quote: '#f59e0b', service: '#8b5cf6', month: '#10b981' };

        const icon = typeIcons[card.card_type] || 'ph-squares-four';
        const label = typeLabels[card.card_type] || 'Card';
        const color = card.color || typeColors[card.card_type] || '#8b5cf6';

        let fieldsHtml = '';
        (card.fields || []).forEach(f => {
            fieldsHtml += `<div class="wcard-field">
                <span class="wcard-field-label">${escapeHtml(f.label)}</span>
                <span class="wcard-field-value">${escapeHtml(f.value)}</span>
            </div>`;
        });

        // Tags from fields (first 2)
        let tagsHtml = '';
        const tagFields = (card.fields || []).slice(0, 2);
        if (tagFields.length > 0) {
            tagsHtml = '<div class="wcard-tags">' + tagFields.map(f => `<span class="wcard-tag">#${escapeHtml(f.value)}</span>`).join('') + '</div>';
        }

        const senderName = msg.user_name || msg.guest_name || 'Sistema';
        return `
        <div class="msg-group">
            <div class="msg-group-header">
                ${isOwn ? '' : renderAvatar(msg)}
                <span class="msg-sender-name">${escapeHtml(senderName)}</span>
                <span class="msg-time">${formatTime(msg.created_at)}</span>
            </div>
            <div class="wcard" style="margin-left:${isOwn ? 'auto' : '44px'};">
                <div class="wcard-header">
                    <div class="wcard-icon" style="color:${color};">
                        <i class="ph ${icon}"></i>
                    </div>
                    <span class="wcard-label">${label}</span>
                </div>
                <div class="wcard-divider"></div>
                <div class="wcard-body">
                    <div class="wcard-title">${escapeHtml(card.title || '')}</div>
                    ${fieldsHtml}
                </div>
                ${card.link ? `<div class="wcard-footer"><a href="${card.link}" class="wcard-link"><i class="ph ph-arrow-square-out"></i> Ver detalle</a></div>` : ''}
            </div>
        </div>`;
    }

    // ── RENDER POLL MESSAGE ──
    function renderPollMessage(msg, poll, isOwn) {
        const senderName = msg.user_name || msg.guest_name || 'Sistema';
        let optionsHtml = '';
        
        let totalVotes = 0;
        if (msg.poll_votes) {
            msg.poll_votes.forEach(pv => totalVotes += parseInt(pv.count));
        }

        (poll.options || []).forEach((opt, idx) => {
            let count = 0;
            let users = '';
            let isVoted = false;
            if (msg.poll_votes) {
                const pv = msg.poll_votes.find(p => parseInt(p.option_index) === idx);
                if (pv) {
                    count = parseInt(pv.count);
                    users = pv.users;
                }
            }
            if (msg.my_votes && msg.my_votes.map(String).includes(idx.toString())) {
                isVoted = true;
            }
            const percent = totalVotes > 0 ? Math.round((count / totalVotes) * 100) : 0;

            optionsHtml += `
            <div class="wcard-poll-option ${isVoted ? 'voted' : ''}" data-msg-id="${msg.id}" data-idx="${idx}" title="${users}">
                <div class="wcard-poll-bar" style="width:${percent}%;"></div>
                <div class="wcard-poll-content">
                    <span class="wcard-poll-text">${escapeHtml(opt)}</span>
                    <span class="wcard-poll-count">${count} (${percent}%)</span>
                </div>
            </div>`;
        });

        return `
        <div class="msg-group">
            <div class="msg-group-header" ${isOwn ? 'style="justify-content:flex-end;"' : ''}>
                ${isOwn ? '' : renderAvatar(msg)}
                <span class="msg-sender-name">${escapeHtml(senderName)}</span>
                <span class="msg-time">${formatTime(msg.created_at)}</span>
            </div>
            <div class="wcard" style="margin-left:${isOwn ? 'auto' : '44px'};">
                <div class="wcard-header">
                    <div class="wcard-icon" style="color:#8b5cf6;"><i class="ph ph-chart-bar"></i></div>
                    <span class="wcard-label">Encuesta</span>
                </div>
                <div class="wcard-divider"></div>
                <div class="wcard-body">
                    <div class="wcard-title">${escapeHtml(poll.question)}</div>
                    <div class="wcard-poll-options">${optionsHtml}</div>
                    <div class="wcard-stat-line"><i class="ph ph-users"></i> ${totalVotes} votos</div>
                </div>
            </div>
        </div>`;
    }

    // ── RENDER TASK MESSAGE ──
    function renderTaskMessage(msg, task, isOwn) {
        const senderName = msg.user_name || msg.guest_name || 'Sistema';
        let itemsHtml = '';
        let completedCount = 0;
        const totalItems = (task.items || []).length;
        
        // Format date
        const dateObj = new Date(msg.created_at);
        const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const dateStr = `${dayNames[dateObj.getDay()]} ${dateObj.getDate()} ${monthNames[dateObj.getMonth()]}, ${dateObj.getFullYear()}`;

        (task.items || []).forEach((item, idx) => {
            const isCompleted = item.completed || item.done;
            if (isCompleted) completedCount++;
            itemsHtml += `
            <div class="wtask-item task-item" data-msg-id="${msg.id}" data-idx="${idx}">
                <div class="wtask-item-info">
                    <span class="wtask-item-text ${isCompleted ? 'done' : ''}">${escapeHtml(item.text)}</span>
                </div>
                <div class="wtask-item-check ${isCompleted ? 'done' : ''}">
                    ${isCompleted ? '<i class="ph-fill ph-check-circle"></i>' : '<div class="wtask-circle"></div>'}
                </div>
            </div>`;
        });

        return `
        <div class="msg-group">
            <div class="msg-group-header" ${isOwn ? 'style="justify-content:flex-end;"' : ''}>
                ${isOwn ? '' : renderAvatar(msg)}
                <span class="msg-sender-name">${escapeHtml(senderName)}</span>
                <span class="msg-time">${formatTime(msg.created_at)}</span>
            </div>
            <div class="wtask-card" data-msg-id="${msg.id}" style="margin-left:${isOwn ? 'auto' : '44px'};">
                <div class="wtask-header">
                    <div class="wtask-title">${escapeHtml(task.title)}</div>
                </div>
                <div class="wtask-meta">
                    <span class="wtask-meta-item"><i class="ph ph-calendar-blank"></i> ${dateStr}</span>
                    <span class="wtask-meta-item"><i class="ph ph-list-checks"></i> ${completedCount}/${totalItems}</span>
                </div>
                <div class="wtask-list">
                    ${itemsHtml}
                </div>
            </div>
        </div>`;
    }

    // ── SEND MESSAGE ──
    async function sendMessage() {
        const text = chatInput.value.trim();
        if (!text && selectedFiles.length === 0) return;
        if (!currentChannelId) return;

        const replyId = currentReplyToId;
        chatInput.value = '';
        chatInput.style.height = 'auto';
        $('btn-send').style.display = 'none';
        $('btn-voice-msg').style.display = 'block';
        
        let filesToSend = [...selectedFiles];
        clearFile();
        clearReply();

        // Si hay archivos, enviamos uno por uno. Si no hay archivos, enviamos solo texto.
        if (filesToSend.length > 0) {
            for (let i = 0; i < filesToSend.length; i++) {
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('channel_id', currentChannelId);
                // Si hay texto, lo enviamos solo con el primer archivo para no repetirlo
                formData.append('message', i === 0 ? text : '');
                formData.append('message_type', 'text');
                if (replyId && i === 0) formData.append('reply_to_id', replyId);
                
                // Comprimir si es imagen antes de enviar
                let file = filesToSend[i];
                if (file.type.startsWith('image/')) {
                    file = await compressImage(file);
                }
                formData.append('attachment', file);

                const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success && data.message) {
                    renderMessages([data.message], false);
                }
            }
        } else {
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('channel_id', currentChannelId);
            formData.append('message', text);
            formData.append('message_type', 'text');
            if (replyId) formData.append('reply_to_id', replyId);

            const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success && data.message) {
                renderMessages([data.message], false);
            }
        }
        
        loadChannels();
        triggerPush(currentChannelId, text || 'Envió un archivo');
    }

    // Canvas Compression Util
    function compressImage(file) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = event => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const MAX_WIDTH = 1280;
                    const MAX_HEIGHT = 1280;
                    let width = img.width;
                    let height = img.height;
                    
                    if (width > height && width > MAX_WIDTH) {
                        height *= MAX_WIDTH / width;
                        width = MAX_WIDTH;
                    } else if (height > MAX_WIDTH) {
                        width *= MAX_HEIGHT / height;
                        height = MAX_HEIGHT;
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    canvas.toBlob((blob) => {
                        const newFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".webp", {
                            type: 'image/webp',
                            lastModified: Date.now()
                        });
                        resolve(newFile);
                    }, 'image/webp', 0.8); // 80% quality
                };
            };
        });
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
            // Update message read receipts
            if (data.statuses) {
                data.statuses.forEach(s => {
                    const bubble = document.querySelector(`.msg-bubble-wrap[data-id="${s.id}"]`);
                    if (bubble) {
                        const statusSpan = bubble.querySelector('.msg-meta > span[class^="msg-status"]');
                        if (statusSpan) {
                            const rCount = parseInt(s.read_count || 0);
                            const tCount = parseInt(s.total_other_members || 0);
                            
                            if (tCount > 0 && rCount === tCount) {
                                statusSpan.className = 'msg-status-read';
                                statusSpan.innerHTML = '<i class="ph ph-checks"></i>';
                            } else if (tCount > 0 && rCount > 0) {
                                statusSpan.className = 'msg-status-delivered';
                                statusSpan.innerHTML = '<i class="ph ph-checks"></i>';
                            }
                        }
                    }
                });
            }
            // Update reactions in real-time for all users
            if (data.reactions_update) {
                Object.keys(data.reactions_update).forEach(msgId => {
                    const reactions = data.reactions_update[msgId];
                    const myReactions = data.my_reactions_update?.[msgId] || [];
                    updateReactionsUI(msgId, reactions, myReactions);
                });
                // Also remove reactions from messages that no longer have any
                document.querySelectorAll('.msg-bubble-wrap[data-id]').forEach(wrap => {
                    const mid = wrap.dataset.id;
                    if (!data.reactions_update[mid]) {
                        const list = wrap.querySelector('.msg-reactions-list');
                        if (list) list.remove();
                    }
                });
            }
            // Update polls in real-time for all users
            if (data.poll_updates) {
                Object.keys(data.poll_updates).forEach(msgId => {
                    const pollVotes = data.poll_updates[msgId];
                    const myVotes = data.my_poll_votes?.[msgId] || [];
                    updatePollUI(msgId, pollVotes, myVotes);
                });
            }
        }, 1500);
    }

    // ── UPDATE REACTIONS UI ──
    function updateReactionsUI(msgId, reactions, myReactions) {
        const wrap = document.querySelector(`.msg-bubble-wrap[data-id="${msgId}"]`);
        if (!wrap) return;

        // Remove old reactions
        const oldList = wrap.querySelector('.msg-reactions-list');
        if (oldList) oldList.remove();

        // Build new reactions HTML
        if (!reactions || reactions.length === 0) return;

        let html = '<div class="msg-reactions-list">';
        reactions.forEach(r => {
            const isVoted = myReactions && myReactions.includes(r.emoji);
            html += `<div class="msg-reaction-badge ${isVoted ? 'voted' : ''}" data-msg-id="${msgId}" data-emoji="${r.emoji}">
                <span>${r.emoji}</span><span>${r.count}</span>
            </div>`;
        });
        html += '</div>';

        const bubble = wrap.querySelector('.msg-bubble');
        if (bubble) bubble.insertAdjacentHTML('beforeend', html);
        else wrap.insertAdjacentHTML('beforeend', html);
    }

    
    // ── UPDATE POLL UI ──
    function updatePollUI(msgId, pollVotes, myVotes) {
        let totalVotes = 0;
        (pollVotes || []).forEach(pv => totalVotes += parseInt(pv.count));

        const optionEls = document.querySelectorAll(`.wcard-poll-option[data-msg-id="${msgId}"]`);
        
        optionEls.forEach(optEl => {
            const idx = optEl.dataset.idx;
            let count = 0;
            let users = '';
            const pv = (pollVotes || []).find(p => parseInt(p.option_index) === parseInt(idx));
            if (pv) {
                count = parseInt(pv.count);
                users = pv.users || '';
            }
            
            let percent = totalVotes > 0 ? Math.round((count / totalVotes) * 100) : 0;
            
            optEl.title = users;
            
            const isVoted = myVotes && myVotes.map(String).includes(idx.toString());
            optEl.classList.toggle('voted', isVoted);
            
            const countSpan = optEl.querySelector('.wcard-poll-count');
            if (countSpan) {
                countSpan.textContent = `${count} (${percent}%)`;
            }

            // update progress
            const bar = optEl.querySelector('.wcard-poll-bar');
            if (bar) bar.style.width = percent + '%';
        });
        
        // update total votes safely
        const containers = document.querySelectorAll(`.wcard-poll-option[data-msg-id="${msgId}"]`);
        const processedContainers = new Set();
        containers.forEach(el => {
            const optionsContainer = el.closest('.wcard-poll-options');
            if (optionsContainer && !processedContainers.has(optionsContainer)) {
                processedContainers.add(optionsContainer);
                const statLine = optionsContainer.parentElement.nextElementSibling?.querySelector('.wcard-stat-line');
                if (statLine) {
                    statLine.innerHTML = `<i class="ph ph-users"></i> ${totalVotes} votos`;
                }
            }
        });
    }

    // ── UPDATE TASK UI ──
    function updateTaskUI(msgId, items) {
        const taskItems = document.querySelectorAll(`.wtask-item[data-msg-id="${msgId}"]`);
        if (!taskItems.length) return;

        const card = document.querySelector(`.wtask-card[data-msg-id="${msgId}"]`);
        let completedCount = 0;

        taskItems.forEach((el, idx) => {
            const item = items[idx];
            if (!item) return;
            const isCompleted = item.completed;
            if (isCompleted) completedCount++;

            const text = el.querySelector('.wtask-item-text');
            const checkWrap = el.querySelector('.wtask-item-check');

            text.className = `wtask-item-text ${isCompleted ? 'done' : ''}`;
            checkWrap.className = `wtask-item-check ${isCompleted ? 'done' : ''}`;
            checkWrap.innerHTML = isCompleted 
                ? '<i class="ph-fill ph-check-circle"></i>' 
                : '<div class="wtask-circle"></div>';
        });

        // Update meta counter
        const metaItems = card?.querySelectorAll('.wtask-meta-item');
        if (metaItems && metaItems[1]) {
            metaItems[1].innerHTML = `<i class="ph ph-list-checks"></i> ${completedCount}/${items.length}`;
        }
    }

    // ── FILE HANDLING ──
    function clearFile() {
        selectedFiles = [];
        fileInput.value = '';
        filePreview.style.display = 'none';
        $('file-preview-list').innerHTML = '';
        const imgPreview = document.getElementById('image-send-preview');
        if (imgPreview) imgPreview.src = '';
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

        document.querySelectorAll('.chat-filter-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                document.querySelectorAll('.chat-filter-pill').forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                loadChannels();
            });
        });

        // Send message
        $('btn-send').addEventListener('click', sendMessage);
        chatInput.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });

        // Auto-resize textarea
        chatInput.addEventListener('input', () => {
            chatInput.style.height = 'auto';
            chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
            
            // Toggle Mic / Send button
            if (chatInput.value.trim().length > 0) {
                $('btn-voice-msg').style.display = 'none';
                $('btn-send').style.display = 'block';
            } else {
                $('btn-voice-msg').style.display = 'block';
                $('btn-send').style.display = 'none';
            }
        });

        // File attach
        // Attachment Menu Logic
        const btnAttachmentMenu = $('btn-attachment-menu');
        const attachmentPopupMenu = $('attachment-popup-menu');
        if (btnAttachmentMenu && attachmentPopupMenu) {
            btnAttachmentMenu.addEventListener('click', (e) => {
                e.stopPropagation();
                attachmentPopupMenu.classList.toggle('active');
            });
            document.addEventListener('click', (e) => {
                if (!attachmentPopupMenu.contains(e.target) && !btnAttachmentMenu.contains(e.target)) {
                    attachmentPopupMenu.classList.remove('active');
                }
            });
            
            $('menu-item-document')?.addEventListener('click', () => {
                fileInput.accept = '*/*';
                fileInput.click();
                attachmentPopupMenu.classList.remove('active');
            });
            $('menu-item-photo')?.addEventListener('click', () => {
                fileInput.accept = 'image/*,video/*';
                fileInput.click();
                attachmentPopupMenu.classList.remove('active');
            });
            $('menu-item-widget')?.addEventListener('click', () => {
                $('btn-share-card')?.click();
                attachmentPopupMenu.classList.remove('active');
            });
            $('menu-item-poll')?.addEventListener('click', () => {
                $('btn-create-poll-modal')?.click();
                attachmentPopupMenu.classList.remove('active');
            });
            $('menu-item-task')?.addEventListener('click', () => {
                $('btn-create-task-modal')?.click();
                attachmentPopupMenu.classList.remove('active');
            });
        }

        $('btn-attach-file').addEventListener('click', () => { fileInput.accept = '*/*'; fileInput.click(); });
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                selectedFiles = Array.from(fileInput.files);
                
                // Show modal if it's a single image, for quicker sending
                if (selectedFiles.length === 1 && selectedFiles[0].type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        $('image-send-preview').src = e.target.result;
                        document.getElementById('image-send-modal').classList.add('active');
                    };
                    reader.readAsDataURL(selectedFiles[0]);
                } else {
                    renderFilePreviews();
                }
            }
        });
        
        function renderFilePreviews() {
            if (selectedFiles.length === 0) {
                filePreview.style.display = 'none';
                return;
            }
            $('file-preview-list').innerHTML = selectedFiles.map((f, idx) => `
                <div style="background:var(--bg-color); padding:0.2rem 0.5rem; border-radius:4px; display:flex; gap:0.5rem; align-items:center;">
                    <span style="font-size:0.8rem; white-space:nowrap;">${f.name}</span>
                    <i class="ph ph-x" style="cursor:pointer; color:var(--danger-color);" onclick="removeSelectedFile(${idx})"></i>
                </div>
            `).join('');
            filePreview.style.display = 'flex';
        }
        
        window.removeSelectedFile = (idx) => {
            selectedFiles.splice(idx, 1);
            renderFilePreviews();
        };

        $('btn-remove-file').addEventListener('click', clearFile);
        
        // Image Send Modal Confirm
        $('btn-image-send-confirm')?.addEventListener('click', () => {
            chatInput.value = $('image-send-caption').value;
            $('image-send-modal').classList.remove('active');
            $('image-send-modal').style.display = 'none';
            $('image-send-caption').value = '';
            sendMessage();
        });

        // Intercept paste for images
        chatInput.addEventListener('paste', e => {
            const items = (e.clipboardData || e.originalEvent.clipboardData).items;
            for (let index in items) {
                const item = items[index];
                if (item.kind === 'file' && item.type.startsWith('image/')) {
                    e.preventDefault();
                    const blob = item.getAsFile();
                    // Create a File object from the blob
                    const pastedFile = new File([blob], "PastedImage.png", { type: "image/png" });
                    
                    // Use DataTransfer to programmatically set files on the file input
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(pastedFile);
                    fileInput.files = dataTransfer.files;
                    
                    // Trigger the change event
                    fileInput.dispatchEvent(new Event('change'));
                }
            }
        });

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

        // ── VOICE RECORDING LOGIC ──
        $('btn-voice-msg').addEventListener('click', async () => {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                // Parar y Enviar
                mediaRecorder.stop();
                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];
                isRecordingCancelled = false;

                mediaRecorder.addEventListener("dataavailable", event => {
                    audioChunks.push(event.data);
                });

                mediaRecorder.addEventListener("stop", async () => {
                    clearInterval(recordingTimer);
                    stream.getTracks().forEach(track => track.stop());
                    
                    // Reset UI
                    $('chat-recording-ui').style.display = 'none';
                    chatInput.style.display = 'block';
                    $('btn-voice-msg').innerHTML = '<i class="ph-fill ph-microphone"></i>';
                    $('btn-voice-msg').style.color = '';

                    // Comprobar si se canceló explícitamente (audioChunks = nulo/vacío extra)
                    if (audioChunks.length === 0 || isRecordingCancelled) return;

                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    const audioFile = new File([audioBlob], `voice_${Date.now()}.webm`, { type: 'audio/webm' });
                    
                    selectedFiles = [audioFile];
                    sendMessage();
                });

                mediaRecorder.start();
                
                // Update UI for recording
                $('chat-input').style.display = 'none';
                $('chat-recording-ui').style.display = 'flex';
                $('btn-voice-msg').innerHTML = '<i class="ph-fill ph-paper-plane-tilt"></i>';
                $('btn-voice-msg').style.color = 'var(--primary-color)';

                recordingSeconds = 0;
                $('recording-time').textContent = '0:00';
                recordingTimer = setInterval(() => {
                    recordingSeconds++;
                    const m = Math.floor(recordingSeconds / 60);
                    const s = recordingSeconds % 60;
                    $('recording-time').textContent = `${m}:${s.toString().padStart(2, '0')}`;
                }, 1000);

            } catch (err) {
                console.error("No se pudo acceder al micrófono:", err);
                Swal.fire('Error', 'No se pudo acceder al micrófono', 'error');
            }
        });

        $('btn-cancel-recording')?.addEventListener('click', () => {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                isRecordingCancelled = true;
                audioChunks = []; // vaciamos para que el evento stop no envíe
                mediaRecorder.stop();
                $('chat-recording-ui').style.display = 'none';
                $('chat-input').style.display = 'block';
                $('btn-voice-msg').innerHTML = '<i class="ph-fill ph-microphone"></i>';
                $('btn-voice-msg').style.color = '';
            }
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

        // ── CHAT SETTINGS MODAL ──
        $('btn-chat-settings')?.addEventListener('click', () => {
            $('chat-settings-modal').classList.add('active');
            
            // Spotify Button status
            if (CURRENT_USER_SPOTIFY) {
                $('spotify-btn-text').textContent = 'Conectado';
                $('btn-connect-spotify').style.backgroundColor = '#1DB954';
                $('btn-connect-spotify').style.color = '#fff';
            }
        });

        // ── RIGHT SIDEBAR (CHAT INFO) ──
        $('btn-group-info')?.addEventListener('click', openInfoPanel);
        $('btn-close-info')?.addEventListener('click', closeInfoPanel);

        $('btn-close-info')?.addEventListener('click', () => {
            $('chat-info-panel').style.display = 'none';
        });

        document.querySelectorAll('.bg-picker-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const bg = e.target.dataset.bg;
                applyChatBackground(bg);
                // Aquí se podría hacer un fetch para guardarlo en DB, pero por ahora lo aplicamos local
                const formData = new FormData();
                formData.append('action', 'save_bg_preference');
                formData.append('bg', bg);
                await fetch('modules/chat/ajax.php', { method: 'POST', body: formData });
            });
        });

        $('btn-connect-spotify')?.addEventListener('click', () => {
            if (CURRENT_USER_SPOTIFY) {
                Swal.fire('Spotify', 'Tu cuenta ya está vinculada.', 'info');
                return;
            }
            // Simulación de OAuth de Spotify
            Swal.fire({
                title: 'Conectar Spotify',
                text: 'Te redirigiremos a Spotify para autorizar.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Autorizar'
            }).then(async (res) => {
                if (res.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'link_spotify');
                    await fetch('modules/chat/ajax.php', { method: 'POST', body: formData });
                    Swal.fire('¡Conectado!', 'Se ha vinculado tu cuenta de Spotify.', 'success').then(() => location.reload());
                }
            });
        });

        // ── POLLS LOGIC ──
        $('btn-create-poll-modal')?.addEventListener('click', () => {
            $('create-poll-modal').classList.add('active');
        });

        $('btn-create-poll')?.addEventListener('click', async () => {
            const question = $('poll-question').value.trim();
            const optionInputs = document.querySelectorAll('.poll-option-input');
            const options = Array.from(optionInputs).map(inp => inp.value.trim()).filter(v => v);
            const multi = false;

            if (!question || options.length < 2) {
                Swal.fire('Error', 'Debes añadir una pregunta y al menos dos opciones.', 'error');
                return;
            }

            const cardData = JSON.stringify({
                question: question,
                options: options,
                allow_multiple: multi
            });

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('channel_id', currentChannelId);
            formData.append('message_type', 'poll');
            formData.append('card_data', cardData);

            await fetch('modules/chat/ajax.php', { method: 'POST', body: formData });
            
            $('create-poll-modal').classList.remove('active');
            $('poll-question').value = '';
            optionInputs.forEach(i => i.value = '');
            loadChannels();
        });

        // ── TASKS LOGIC ──
        $('btn-create-task-modal')?.addEventListener('click', () => {
            $('create-task-modal').classList.add('active');
        });

        $('btn-send-task')?.addEventListener('click', async () => {
            const title = $('task-title').value.trim();
            const itemInputs = document.querySelectorAll('.task-item-input');
            const items = Array.from(itemInputs).map(inp => inp.value.trim()).filter(v => v);

            if (!title || items.length === 0) {
                Swal.fire('Error', 'Debes añadir un título y al menos una tarea.', 'error');
                return;
            }

            const cardData = JSON.stringify({
                title: title,
                items: items.map(i => ({ text: i, completed: false }))
            });

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('channel_id', currentChannelId);
            formData.append('message_type', 'task');
            formData.append('card_data', cardData);

            await fetch('modules/chat/ajax.php', { method: 'POST', body: formData });
            
            $('create-task-modal').classList.remove('active');
            $('task-title').value = '';
            itemInputs.forEach(i => i.value = '');
            loadChannels();
        });

        // Push notifications are now global
    }

    // Global Functions for Modals
    window.addTaskRow = () => {
        const container = document.getElementById('task-items-container');
        const div = document.createElement('div');
        div.className = 'task-item-row';
        div.style.position = 'relative';
        div.style.display = 'flex';
        div.style.alignItems = 'center';
        div.style.gap = '0.5rem';
        div.innerHTML = `
            <input type="text" placeholder="Añade una tarea" class="chat-input task-item-input" style="flex:1; border-bottom:1px solid var(--border-color); border-radius:0; background:transparent; padding-right:2rem;">
            <i class="ph ph-x" style="color:var(--danger-color); cursor:pointer;" onclick="this.parentElement.remove()"></i>
        `;
        container.appendChild(div);
    };

    window.addPollOption = () => {
        const container = document.getElementById('poll-options-container');
        const div = document.createElement('div');
        div.style.position = 'relative';
        div.style.display = 'flex';
        div.style.alignItems = 'center';
        div.style.gap = '0.5rem';
        div.innerHTML = `
            <input type="text" placeholder="Opción" class="chat-input poll-option-input" style="flex:1; border-bottom:1px solid var(--border-color); border-radius:0; background:transparent; padding-right:2rem;">
            <i class="ph ph-x" style="color:var(--danger-color); cursor:pointer;" onclick="this.parentElement.remove()"></i>
        `;
        container.appendChild(div);
    };

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
    window.setReply = function(msgId, senderName, text) {
        startReply(msgId, senderName, text);
    };

    function startReply(msgId, senderName, text) {
        text = text || '';
        senderName = senderName || 'User';
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
    // ── WHATSAPP AUDIO PLAYER LOGIC ──
    window.toggleWaAudio = function(btn) {
        const player = btn.closest('.wa-audio-player');
        const audio = player.querySelector('audio');
        const icon = btn.querySelector('i');
        
        if (audio.paused) {
            document.querySelectorAll('.wa-audio-player audio').forEach(a => {
                if(a !== audio && !a.paused) { 
                    a.pause(); 
                    a.parentElement.querySelector('.wa-play-btn i').className = 'ph-fill ph-play'; 
                }
            });
            audio.play();
            icon.className = 'ph-fill ph-pause';
        } else {
            audio.pause();
            icon.className = 'ph-fill ph-play';
        }
    };

    window.seekWaAudio = function(e, container) {
        const player = container.closest('.wa-audio-player');
        const audio = player.querySelector('audio');
        const rect = container.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const percent = clickX / rect.width;
        if (audio.duration) {
            audio.currentTime = Math.max(0, Math.min(1, percent)) * audio.duration;
        }
    };

    window.updateWaAudio = function(audio) {
        const player = audio.closest('.wa-audio-player');
        if(!player) return;
        const bars = player.querySelectorAll('.wa-bar');
        const knob = player.querySelector('.wa-waveform-knob');
        const timeEl = player.querySelector('.wa-audio-time');
        
        const percent = audio.currentTime / (audio.duration || 1);
        knob.style.left = (percent * 100) + '%';
        
        const activeCount = Math.floor(percent * bars.length);
        bars.forEach((bar, index) => {
            if (index < activeCount) bar.classList.add('active');
            else bar.classList.remove('active');
        });

        const curM = Math.floor(audio.currentTime / 60);
        const curS = Math.floor(audio.currentTime % 60);
        timeEl.textContent = `${curM}:${curS.toString().padStart(2, '0')}`;
    };

    window.initWaAudio = function(audio) {
        const player = audio.closest('.wa-audio-player');
        if(!player) return;
        const timeEl = player.querySelector('.wa-audio-time');
        const durM = Math.floor(audio.duration / 60);
        const durS = Math.floor(audio.duration % 60);
        if (!isNaN(durM)) {
            timeEl.textContent = `${durM}:${durS.toString().padStart(2, '0')}`;
        }
    };

    window.resetWaAudio = function(audio) {
        const player = audio.closest('.wa-audio-player');
        if(!player) return;
        const icon = player.querySelector('.wa-play-btn i');
        icon.className = 'ph-fill ph-play';
        player.querySelectorAll('.wa-bar').forEach(b => b.classList.remove('active'));
        const knob = player.querySelector('.wa-waveform-knob');
        if(knob) knob.style.left = '0%';
        initWaAudio(audio); // reset to duration
    };

// Prevent default drag behaviors globally to stop browser from opening files
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    document.addEventListener(eventName, preventDefaults, false);
});
function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

// Drag & Drop for file attachments
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
                    document.getElementById('image-send-modal').classList.add('active');
                };
                reader.readAsDataURL(selectedFiles[0]);
            } else {
                renderFilePreviews();
            }
        }
    });
}


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
                if (data.pinned.length > 1) summary += ` (y ${data.pinned.length - 1} más)`;
                textSpan.innerHTML = `<b>Mensaje fijado:</b> ${summary}`;
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
        $('group-manager-modal').style.display = ''; // Clear inline display if any
        $('group-manager-modal').classList.add('active');
    });

    $('btn-save-group')?.addEventListener('click', async () => {
        try {
            const name = $('group-name').value.trim();
            if (!name) return alert('El nombre es obligatorio');
            const desc = $('group-desc').value.trim();
            const isPublic = $('group-is-public').checked ? 1 : 0;
            const fd = new FormData();
            fd.append('action', 'create_channel'); 
            const chId = $('group-manager-id')?.value;
            if (chId) fd.append('channel_id', chId);
            fd.append('name', name);
            fd.append('description', desc);
            fd.append('type', 'group');
            fd.append('is_public', isPublic);
            
            const requiresApproval = $('group-requires-approval')?.checked ? 1 : 0;
            const isSecret = $('group-is-secret')?.checked ? 1 : 0;
            const secretPassword = $('group-secret-password')?.value || '';
            
            fd.append('requires_approval', requiresApproval);
            fd.append('is_secret', isSecret);
            if (isSecret) fd.append('secret_password', secretPassword);
            
            const avatarFile = $('group-avatar-input')?.files[0];
            if (avatarFile) fd.append('avatar', avatarFile);

            const memberCbs = document.querySelectorAll('.group-member-cb:checked:not([disabled])');
            const members = Array.from(memberCbs).map(cb => cb.value);
            fd.append('members', JSON.stringify(members));

            const res = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                $('group-manager-modal').classList.remove('active');
                loadChannels();
            } else {
                alert(data.error || 'Error al crear grupo (Backend)');
            }
        } catch (err) {
            alert('Error JS al crear grupo: ' + err.message);
            console.error(err);
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
            if (modal) {
                modal.classList.remove('active');
                modal.style.display = ''; // Clear any inline style
            }
        });
    });

    document.getElementById('info-panel-close')?.addEventListener('click', () => {
        document.getElementById('chat-info-panel').classList.remove('active');
    });

    // ── GLOBAL EMOJI PICKER BINDING ──
    let currentEmojiTarget = null;
    const emojiQuickPicker = document.getElementById('emoji-quick-picker');

    document.addEventListener('click', (e) => {
        // Emoji picker toggle
        const smileyBtn = e.target.closest('.ph-smiley');
        if (smileyBtn) {
            // Check if it's our target button in main chat, or in modals
            if (smileyBtn.closest('#btn-emoji-picker')) {
                currentEmojiTarget = document.getElementById('chat-input');
            } else if (smileyBtn.closest('.modal-content')) {
                // Modals (Poll, Task)
                currentEmojiTarget = smileyBtn.closest('div').querySelector('input');
            }
            
            if (currentEmojiTarget && emojiQuickPicker) {
                const rect = smileyBtn.getBoundingClientRect();
                emojiQuickPicker.style.display = 'block';
                
                let top = rect.bottom + 10;
                let left = rect.left - 300 + rect.width; 
                if (left < 10) left = 10;
                if (top + 400 > window.innerHeight) {
                    top = rect.top - 410; // Show above
                }
                
                emojiQuickPicker.style.top = top + 'px';
                emojiQuickPicker.style.left = left + 'px';
                e.preventDefault();
                e.stopPropagation();
            }
        } else if (emojiQuickPicker && !e.target.closest('emoji-picker')) {
            emojiQuickPicker.style.display = 'none';
            currentEmojiTarget = null;
        }
    });

    if (emojiQuickPicker) {
        emojiQuickPicker.addEventListener('emoji-click', event => {
            if (currentEmojiTarget) {
                const emoji = event.detail.unicode;
                const start = currentEmojiTarget.selectionStart || currentEmojiTarget.value.length;
                const end = currentEmojiTarget.selectionEnd || currentEmojiTarget.value.length;
                
                const text = currentEmojiTarget.value;
                currentEmojiTarget.value = text.slice(0, start) + emoji + text.slice(end);
                
                currentEmojiTarget.selectionStart = currentEmojiTarget.selectionEnd = start + emoji.length;
                currentEmojiTarget.focus();
            }
        });
    }

})();


// LIGHTBOX
window.openLightbox = function(src) {
    let overlay = document.getElementById('chat-lightbox');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'chat-lightbox';
        overlay.className = 'lightbox-overlay';
        overlay.innerHTML = `
            <button class="lightbox-close" onclick="document.getElementById('chat-lightbox').style.display='none'"><i class="ph ph-x"></i></button>
            <img class="lightbox-img" id="chat-lightbox-img" src="">
        `;
        document.body.appendChild(overlay);
    }
    document.getElementById('chat-lightbox-img').src = src;
    overlay.style.display = 'flex';
};

// NOTIFICATION SOUND
window.playNotificationSound = function() {
    try {
        const audio = new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg');
        audio.volume = 0.5;
        audio.play().catch(e => console.log('Audio autoplay prevented'));
    } catch(e) {}
};







async function deleteMessage(msgId) {
    if (confirm('¿Eliminar mensaje?\n\nEsta acción no se puede deshacer.')) {
        const fd = new FormData();
        fd.append('action', 'delete_message');
        fd.append('message_id', msgId);
        
        try {
            const resp = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                // Remove from DOM
                const bubble = document.querySelector(`.msg-bubble-wrap[data-id="${msgId}"]`);
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


window.addEventListener("dragover", function(e){ e.preventDefault(); }, false);
window.addEventListener("drop", function(e){ e.preventDefault(); }, false);


/* ==================================================================
   Módulo de Mensajes - Lógica Nueva (Drive, Lightbox)
   ================================================================== */
(function() {
    'use strict';
    
    // Lightbox Logic
    const lightbox = document.getElementById('chat-multimedia-lightbox');
    const lightboxBody = document.getElementById('lightbox-body');
    const lightboxTitle = document.getElementById('lightbox-title');
    const btnLightboxClose = document.getElementById('btn-lightbox-close');
    const btnLightboxDownload = document.getElementById('btn-lightbox-download');
    
    let currentFileUrl = '';
    let currentFileName = '';

    function openLightbox(url, type, name) {
        currentFileUrl = url;
        currentFileName = name;
        if(lightboxTitle) lightboxTitle.innerText = name || 'Archivo';
        
        if(lightboxBody) lightboxBody.innerHTML = ''; // clear
        
        if (type === 'image') {
            const img = document.createElement('img');
            img.src = url;
            if(lightboxBody) lightboxBody.appendChild(img);
        } else if (type === 'video') {
            const vid = document.createElement('video');
            vid.src = url;
            vid.controls = true;
            vid.autoplay = true;
            if(lightboxBody) lightboxBody.appendChild(vid);
        } else if (type === 'pdf') {
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.style.width = '80vw';
            iframe.style.height = '80vh';
            iframe.style.border = 'none';
            if(lightboxBody) lightboxBody.appendChild(iframe);
        }
        
        if(lightbox) lightbox.style.display = 'flex';
    }

    if (btnLightboxClose) {
        btnLightboxClose.addEventListener('click', () => {
            if(lightbox) lightbox.style.display = 'none';
            if(lightboxBody) lightboxBody.innerHTML = '';
        });
    }

    if (btnLightboxDownload) {
        btnLightboxDownload.addEventListener('click', () => {
            const a = document.createElement('a');
            a.href = currentFileUrl;
            a.download = currentFileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        });
    }

    // Intercept clicks on chat messages to open Lightbox
    document.addEventListener('click', function(e) {
        if (e.target.tagName === 'IMG' && e.target.closest('.chat-messages')) {
            openLightbox(e.target.src, 'image', 'Imagen');
        }
    });

    // Drive Picker Logic
    const btnSelectDrive = document.getElementById('btn-select-drive-folder');
    const drivePickerModal = document.getElementById('drivePickerModal');
    
    function showDriveModal() {
        if(drivePickerModal) {
            drivePickerModal.style.display = 'block';
            drivePickerModal.classList.add('show');
            drivePickerModal.style.background = 'rgba(0,0,0,0.5)';
        }
    }
    
    function hideDriveModal() {
        if(drivePickerModal) {
            drivePickerModal.style.display = 'none';
            drivePickerModal.classList.remove('show');
        }
    }

    if (btnSelectDrive && drivePickerModal) {
        btnSelectDrive.addEventListener('click', () => {
            loadDriveFolder('root');
            showDriveModal();
        });
        
        // Add dismiss listeners
        drivePickerModal.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', hideDriveModal);
        });
    }

    async function loadDriveFolder(folderId) {
        const listContainer = document.getElementById('drive-picker-list');
        if(!listContainer) return;
        listContainer.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>';
        
        const fd = new FormData();
        fd.append('action', 'list');
        fd.append('folderId', folderId);
        
        try {
            const resp = await fetch('modules/drive/ajax_drive.php', { method: 'POST', body: fd });
            const data = await resp.json();
            
            listContainer.innerHTML = '';
            if (data.success && data.files) {
                const folders = data.files.filter(f => f.mimeType === 'application/vnd.google-apps.folder');
                if (folders.length === 0) {
                    listContainer.innerHTML = '<div class="text-center text-muted p-4">Carpeta vacía</div>';
                }
                
                folders.forEach(f => {
                    const div = document.createElement('div');
                    div.className = 'd-flex align-items-center p-2 border-bottom';
                    div.style.cursor = 'pointer';
                    div.innerHTML = `<i class="ph-fill ph-folder text-warning" style="font-size:1.5rem; margin-right:0.5rem;"></i> <span>${f.name}</span>`;
                    
                    div.addEventListener('click', () => {
                        document.querySelectorAll('.drive-folder-item').forEach(el => el.style.background = 'transparent');
                        div.style.background = 'var(--bg-color)';
                        div.classList.add('drive-folder-item');
                        
                        document.getElementById('crs-drive-folder-id').value = f.id;
                        document.getElementById('crs-drive-folder-name').value = f.name;
                    });
                    
                    div.addEventListener('dblclick', () => {
                        loadDriveFolder(f.id);
                    });
                    
                    listContainer.appendChild(div);
                });
            } else {
                listContainer.innerHTML = `<div class="text-danger p-2">${data.error || 'Error cargando'}</div>`;
            }
        } catch (e) {
            console.error(e);
            listContainer.innerHTML = '<div class="text-danger p-2">Error de conexión</div>';
        }
    }

    document.getElementById('btn-confirm-drive-folder')?.addEventListener('click', async () => {
        const folderId = document.getElementById('crs-drive-folder-id').value;
        const channelId = window.currentChannelId || (document.querySelector('.channel-item.active') ? document.querySelector('.channel-item.active').dataset.id : null);
        
        if (folderId && channelId) {
            hideDriveModal();
            // Guardar folder en ajax_mensajes (o ajax)
            const fd = new FormData();
            fd.append('action', 'save_drive_folder');
            fd.append('channel_id', channelId);
            fd.append('folder_id', folderId);
            
            try {
                const resp = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
                const data = await resp.json();
                if(data.success) {
                    Swal.fire({title: 'Guardado', text: 'Carpeta de Drive vinculada', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000});
                }
            } catch (e) {
                console.error(e);
            }
        }
    });

})();