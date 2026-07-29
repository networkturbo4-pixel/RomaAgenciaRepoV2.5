// modules/mensajes/mensajes.js

let currentChatId = null;
let pollInterval = null;
let lastMessageId = 0;
let pendingFiles = [];

let unreadInChat = 0;

const chatSyncChannel = new BroadcastChannel('chat_sync');

// Init Pusher globally
const pusher = new Pusher('b31f38612d61b0285c78', {
    cluster: 'us2',
    authEndpoint: 'ajax_pusher_auth.php'
});
let activeChatChannel = null;

chatSyncChannel.onmessage = (event) => {
    if (event.data.type === 'messages_read' && event.data.chatId === currentChatId) {
        if (unreadInChat > 0) {
            unreadInChat = 0;
            updateScrollBadge();
        }
    } else if (event.data.type === 'new_message_sent' && event.data.chatId === currentChatId) {
        pollMessages(false);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // Request notification permission
    if (window.Notification && Notification.permission !== "granted" && Notification.permission !== "denied") {
        Notification.requestPermission();
    }
    
    loadChats();
    
    // Close attachment menu if clicked outside
    document.addEventListener('click', (e) => {
        const menu = document.getElementById('msgAttachMenu');
        const btn = document.getElementById('msgBtnAttach');
        if (menu && btn && !menu.contains(e.target) && !btn.contains(e.target)) {
            menu.classList.remove('active');
        }
    });

    const msgArea = document.getElementById('msgArea');
    if (msgArea) {
        msgArea.addEventListener('scroll', () => {
            const isScrolledToBottom = msgArea.scrollHeight - msgArea.clientHeight <= msgArea.scrollTop + 50;
            const scrollBtn = document.getElementById('msgScrollToBottomBtn');
            if (scrollBtn) {
                if (!isScrolledToBottom) {
                    scrollBtn.classList.add('visible');
                } else {
                    scrollBtn.classList.remove('visible');
                    if (unreadInChat > 0) {
                        unreadInChat = 0; // Reset badge when scrolled to bottom
                        updateScrollBadge();
                        chatSyncChannel.postMessage({ type: 'messages_read', chatId: currentChatId });
                    }
                }
            }
        });
    }

    // Global keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            // Close lightbox if open
            const lb = document.getElementById('msgLightbox');
            if (lb && lb.style.display === 'flex') {
                closeLightbox();
                return;
            }
            
            // Close info panel
            const infoPanel = document.getElementById('msgInfoPanel');
            if (infoPanel && infoPanel.classList.contains('active')) {
                toggleInfoPanel();
                return;
            }
            
            // Close chat view
            if (currentChatId) {
                closeChat();
                return;
            }

            // Close modals
            let modalClosed = false;
            document.querySelectorAll('.msg-modal.active').forEach(modal => {
                modal.classList.remove('active');
                modalClosed = true;
            });
            
            if (!modalClosed && currentChatId) {
                closeChat();
            }
        }
    });

    window.addEventListener('offline', () => {
        const statusEl = document.getElementById('msgHeaderStatus');
        if (statusEl) statusEl.innerText = 'Sin conexión...';
    });

    window.addEventListener('online', () => {
        const statusEl = document.getElementById('msgHeaderStatus');
        if (statusEl) {
            statusEl.innerText = 'En línea';
            setTimeout(() => {
                if (statusEl.innerText === 'En línea') statusEl.innerText = '';
            }, 3000);
        }
        if (currentChatId) {
            pollMessages(false);
            pollChats();
        }
    });
});

function closeChat() {
    currentChatId = null;
    if (pollInterval) clearInterval(pollInterval);
    
    const emptyState = document.getElementById('msgEmptyState');
    if (emptyState) emptyState.style.display = 'flex';
    
    const chatView = document.getElementById('msgChatView');
    if (chatView) chatView.style.display = 'none';
    
    // Deselect chat item
    document.querySelectorAll('.msg-chat-item').forEach(el => el.classList.remove('active'));
    
    // Close info panel
    const infoPanel = document.getElementById('msgInfoPanel');
    if (infoPanel) infoPanel.classList.remove('active');
    
    // For mobile
    const sidebar = document.getElementById('msgSidebar');
    const main = document.getElementById('msgMain');
    if (sidebar && main) {
        sidebar.classList.remove('hidden');
        main.classList.remove('active');
    }
}

let scrollObserver = null;
function initVirtualScroller() {
    const area = document.getElementById('msgArea');
    if (!area) return;
    if (scrollObserver) scrollObserver.disconnect();
    
    scrollObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const wrap = entry.target;
            const inner = wrap.querySelector('.msg-bubble');
            if (!inner) return;

            if (entry.isIntersecting) {
                unvirtualize(wrap);
            } else {
                if (!wrap.dataset.virtHtml && inner.innerHTML !== '') {
                    wrap.style.height = wrap.offsetHeight + 'px';
                    wrap.dataset.virtHtml = inner.innerHTML;
                    inner.innerHTML = '';
                }
            }
        });
    }, {
        root: area,
        rootMargin: '1200px 0px'
    });
}

function unvirtualize(wrap) {
    if (wrap && wrap.dataset.virtHtml) {
        const inner = wrap.querySelector('.msg-bubble');
        if (inner) inner.innerHTML = wrap.dataset.virtHtml;
        wrap.style.height = '';
        wrap.dataset.virtHtml = '';
    }
}

function scrollToBottom() {
    const area = document.getElementById('msgArea');
    if (area) {
        area.scrollTo({ top: area.scrollHeight, behavior: 'smooth' });
    }
}

function updateScrollBadge() {
    const badge = document.getElementById('msgUnreadBadge');
    if (badge) {
        if (unreadInChat > 0) {
            badge.innerText = unreadInChat;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }
}

function loadChats() {
    fetch('modules/mensajes/ajax.php?action=get_chats')
        .then(res => res.json())
        .then(data => {
            const list = document.getElementById('msgChatList');
            if (!list) return; // Not in admin view
            list.innerHTML = '';
            if (data.chats && data.chats.length > 0) {
                let totalUnread = 0;
                data.chats.forEach(c => {
                    const isActive = c.id === currentChatId ? 'active' : '';
                    let avatar = c.name ? c.name.charAt(0).toUpperCase() : '#';
                    let avatarStyle = c.avatar ? `background-image:url('${c.avatar}'); background-size:cover; background-position:center;` : '';
                    let avatarText = c.avatar ? '' : avatar;
                    let unreadBadge = '';
                    if (c.unread_count > 0 && c.id !== currentChatId) {
                        totalUnread += parseInt(c.unread_count);
                        unreadBadge = `<div style="background:var(--msg-primary); color:white; border-radius:10px; padding:2px 6px; font-size:10px; font-weight:bold;">${c.unread_count}</div>`;
                    }
                    
                    list.insertAdjacentHTML('beforeend', `
                        <div id="chat-item-${c.id}" class="msg-chat-item ${isActive}" onclick="openChat(${c.id}, '${(c.name || 'Chat').replace(/'/g, "\\'")}', '${c.public_link || ''}')" oncontextmenu="handleChatContext(event, ${c.id}, '${(c.name || '').replace(/'/g, "\\'")}')">
                            <div class="msg-chat-avatar" style="${avatarStyle}">${avatarText}</div>
                            <div class="msg-chat-info">
                                <div class="msg-chat-top">
                                    <div class="msg-chat-name">${c.name || 'Chat ' + c.id}</div>
                                    ${unreadBadge}
                                </div>
                                <div class="msg-chat-preview">${c.last_message || 'Sin mensajes'}</div>
                            </div>
                        </div>
                    `);
                });
                
                // Update document title with unread badge
                if (totalUnread > 0) {
                    document.title = `(${totalUnread}) Mensajes`;
                } else {
                    document.title = 'Mensajes';
                }
            } else {
                list.innerHTML = '<div style="text-align:center; padding: 2rem; color: var(--msg-text-muted);">No hay chats</div>';
            }
        });
}

function pollChats() {
    fetch('modules/mensajes/ajax.php?action=get_chats')
        .then(res => res.json())
        .then(data => {
            if (data.chats && data.chats.length > 0) {
                let totalUnread = 0;
                data.chats.forEach(c => {
                    const item = document.getElementById('chat-item-' + c.id);
                    if (c.unread_count > 0 && c.id !== currentChatId) {
                        totalUnread += parseInt(c.unread_count);
                    }
                    if (item) {
                        const topDiv = item.querySelector('.msg-chat-top');
                        const previewDiv = item.querySelector('.msg-chat-preview');
                        
                        // Update preview
                        if (previewDiv) previewDiv.innerText = c.last_message || 'Sin mensajes';
                        
                        // Update badge
                        if (topDiv) {
                            let badge = topDiv.querySelector('.msg-chat-badge');
                            if (c.unread_count > 0 && c.id !== currentChatId) {
                                if (!badge) {
                                    badge = document.createElement('div');
                                    badge.className = 'msg-chat-badge';
                                    badge.style.cssText = 'background:var(--msg-primary); color:white; border-radius:10px; padding:2px 6px; font-size:10px; font-weight:bold; margin-left:8px;';
                                    topDiv.appendChild(badge);
                                }
                                badge.innerText = c.unread_count;
                            } else if (badge) {
                                badge.remove();
                            }
                        }
                    }
                });
                
                if (totalUnread > 0) {
                    document.title = `(${totalUnread}) Mensajes`;
                } else {
                    document.title = 'Mensajes';
                }
            }
        });
}

function handleChatContext(e, chatId, chatName) {
    e.preventDefault();
    if (confirm(`¿Estás seguro que deseas eliminar el chat "${chatName}"? Esta acción no se puede deshacer.`)) {
        fetch('modules/mensajes/ajax.php?action=delete_chat', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `chat_id=${chatId}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                if(currentChatId === chatId) {
                    currentChatId = null;
                    document.getElementById('msgChatView').style.display = 'none';
                    document.getElementById('msgEmptyState').style.display = 'flex';
                }
                loadChats();
            } else {
                alert('Error al eliminar: ' + data.error);
            }
        });
    }
}

function openChat(chatId, name, publicLink) {
    currentChatId = chatId;
    lastMessageId = 0;
    
    hidePinnedBanner();
    cancelReply();
    
    const emptyState = document.getElementById('msgEmptyState');
    if (emptyState) emptyState.style.display = 'none';
    
    const chatView = document.getElementById('msgChatView');
    if (chatView) chatView.style.display = 'flex';
    
    const headerName = document.getElementById('msgHeaderName');
    if (headerName) headerName.innerText = name || 'Chat ' + chatId;
    
    const headerAvatar = document.getElementById('msgHeaderAvatar');
    if (headerAvatar) headerAvatar.innerText = (name || '#').charAt(0).toUpperCase();
    
    const msgArea = document.getElementById('msgArea');
    if (msgArea) {
        msgArea.innerHTML = `
            <div class="msg-skeleton-chat">
                <div class="msg-skeleton-bubble"></div>
                <div class="msg-skeleton-bubble own"></div>
                <div class="msg-skeleton-bubble" style="width:40%;"></div>
                <div class="msg-skeleton-bubble own" style="width:30%;"></div>
            </div>
        `;
    }
    
    const publicLinkEl = document.getElementById('msgPublicLink');
    if (publicLinkEl) {
        if (publicLink) {
            let basePath = window.location.pathname.replace(/\/index\.php$/i, '/');
            if (!basePath.endsWith('/')) basePath += '/';
            publicLinkEl.value = window.location.origin + basePath + 'm/' + publicLink;
        } else {
            publicLinkEl.value = 'No disponible';
        }
    }
    
    // Highlight sidebar item
    document.querySelectorAll('.msg-chat-item').forEach(el => el.classList.remove('active'));
    const activeItem = document.getElementById('chat-item-' + chatId);
    if (activeItem) activeItem.classList.add('active');

    const sidebar = document.getElementById('msgSidebar');
    if (sidebar && window.innerWidth <= 992) {
        sidebar.classList.add('hidden');
    }

    if (pollInterval) clearInterval(pollInterval);
    
    if (activeChatChannel) {
        pusher.unsubscribe(activeChatChannel.name);
        activeChatChannel = null;
    }
    
    pollMessages(true);
    
    activeChatChannel = pusher.subscribe('chat-' + chatId);
    activeChatChannel.bind('refresh', function(data) {
        pollMessages(false);
        pollChats();
    });

    // Fallback slow poll just in case (every 30 seconds)
    pollInterval = setInterval(() => {
        if (navigator.onLine) {
            pollMessages(false);
            pollChats();
        }
    }, 30000);
    
    initVirtualScroller();
    loadChatInfo(chatId);
}

function loadChatInfo(chatId) {
    fetch('modules/mensajes/ajax.php?action=get_info&chat_id=' + chatId)
        .then(r => r.json())
        .then(data => {
            // Group header avatar & name
            const avatarEl = document.getElementById('msgInfoAvatar');
            const nameEl = document.getElementById('msgInfoName');
            if (avatarEl && nameEl) {
                if (data.avatar) {
                    avatarEl.style.backgroundImage = `url(${data.avatar})`;
                    avatarEl.innerText = '';
                } else {
                    avatarEl.style.backgroundImage = '';
                    avatarEl.innerText = data.name ? data.name.charAt(0).toUpperCase() : '#';
                }
                nameEl.innerText = data.name || 'Chat';
            }

            // Update header too
            const headerNameEl = document.getElementById('msgHeaderName');
            if (headerNameEl) headerNameEl.innerText = data.name || 'Chat';
            
            const headerAvEl = document.getElementById('msgHeaderAvatar');
            if (headerAvEl) {
                if (data.avatar) {
                    headerAvEl.style.backgroundImage = `url(${data.avatar})`;
                    headerAvEl.style.backgroundSize = 'cover';
                    headerAvEl.style.backgroundPosition = 'center';
                    headerAvEl.innerText = '';
                } else {
                    headerAvEl.style.backgroundImage = '';
                    headerAvEl.innerText = data.name ? data.name.charAt(0).toUpperCase() : '#';
                }
            }
            
            // Hide group-specific features if it's a direct chat
            if (data.type === 'direct') {
                const membersSec = document.getElementById('msgMembersSection');
                if (membersSec) membersSec.style.display = 'none';
                
                const linkSection = document.getElementById('msgPublicLinkSection');
                if (linkSection) linkSection.style.display = 'none';
                
                const editBtn = document.querySelector('.msg-btn-edit-group');
                if (editBtn) editBtn.style.display = 'none';
            } else {
                const membersSec = document.getElementById('msgMembersSection');
                if (membersSec) membersSec.style.display = 'block';
                
                const linkSection = document.getElementById('msgPublicLinkSection');
                if (linkSection) linkSection.style.display = 'block';
                
                const editBtn = document.querySelector('.msg-btn-edit-group');
                if (editBtn) editBtn.style.display = 'flex';
            }

            // Drive
            const driveEl = document.getElementById('msgDriveFolderName');
            if (driveEl) {
                if (data.drive_folder_id) {
                    driveEl.innerText = "Carpeta ID: " + data.drive_folder_id;
                } else {
                    driveEl.innerText = "Sin vincular";
                }
            }
            
            // Members
            const membersEl = document.getElementById('msgMembersList');
            if (membersEl) {
                let membersHtml = '';
                data.members.forEach(m => {
                    let initial = m.name ? m.name.charAt(0).toUpperCase() : '#';
                    let roleBadge = m.role === 'admin' ? '<span class="msg-badge-admin">Admin</span>' : '';
                    membersHtml += `
                    <div class="msg-member-item">
                        <div class="msg-member-avatar">${initial}</div>
                        <div class="msg-member-name">${m.name}</div>
                        ${roleBadge}
                    </div>`;
                });
                membersEl.innerHTML = membersHtml;
            }
        });

    // Load gallery
    loadGallery(chatId);
}

let currentGalleryData = { images: [], docs: [], links: [] };
let currentGalleryTab = 'media';

function loadGallery(chatId) {
    fetch('modules/mensajes/ajax.php?action=get_gallery&chat_id=' + chatId)
        .then(r => r.json())
        .then(data => {
            currentGalleryData = {
                images: data.images || [],
                docs: data.docs || [],
                links: data.links || []
            };
            renderGalleryTab();
        });
}

function switchGalleryTab(tab) {
    currentGalleryTab = tab;
    // Update button styles
    ['media', 'docs', 'links'].forEach(t => {
        const btn = document.getElementById('tab-' + t);
        if (btn) {
            if (t === tab) {
                btn.className = 'msg-btn-sm-primary';
            } else {
                btn.className = 'msg-btn-sm-outline';
            }
        }
    });
    renderGalleryTab();
}

function renderGalleryTab() {
    const grid = document.getElementById('msgGalleryGrid');
    if (!grid) return;
    
    let html = '';
    
    if (currentGalleryTab === 'media') {
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'repeat(3, 1fr)';
        grid.style.gap = '8px';
        
        if (currentGalleryData.images.length === 0) {
            html = '<div style="font-size:12px; color: var(--msg-text-muted); grid-column: 1/-1; text-align:center; padding: 1rem 0;">Sin imágenes</div>';
        } else {
            currentGalleryData.images.forEach(img => {
                let thumbUrl = img.file_url;
                let clickAction = `openLightbox('${img.type === 'video' ? 'video' : 'image'}', '${img.file_url}')`;
                if (img.file_url.includes('drive.google.com')) {
                    const driveMatch = img.file_url.match(/\/d\/([a-zA-Z0-9_-]+)/);
                    if (driveMatch) {
                        thumbUrl = `https://drive.google.com/thumbnail?id=${driveMatch[1]}&sz=w200`;
                    }
                    clickAction = `window.open('${img.file_url}', '_blank')`;
                }
                let playIcon = img.type === 'video' ? '<i class="ph-fill ph-play-circle" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white; font-size:24px; text-shadow:0 1px 4px rgba(0,0,0,0.5);"></i>' : '';
                html += `<div class="msg-gallery-item" style="background-image: url('${thumbUrl}'); position:relative;" onclick="${clickAction}">${playIcon}</div>`;
            });
        }
    } else if (currentGalleryTab === 'docs') {
        grid.style.display = 'flex';
        grid.style.flexDirection = 'column';
        grid.style.gap = '8px';
        
        if (currentGalleryData.docs.length === 0) {
            html = '<div style="font-size:12px; color: var(--msg-text-muted); text-align:center; padding: 1rem 0;">Sin documentos</div>';
        } else {
            currentGalleryData.docs.forEach(doc => {
                let ext = doc.file_name ? doc.file_name.split('.').pop().toLowerCase() : '';
                let iconClass = 'ph-file';
                if (['pdf'].includes(ext)) iconClass = 'ph-file-pdf';
                if (['doc','docx'].includes(ext)) iconClass = 'ph-file-doc';
                if (['xls','xlsx'].includes(ext)) iconClass = 'ph-file-xls';
                if (doc.type === 'audio') iconClass = 'ph-file-audio';
                
                html += `
                <a href="${doc.file_url}" target="_blank" style="display:flex; align-items:center; gap:8px; padding:8px; background:var(--msg-bg); border-radius:6px; text-decoration:none; color:var(--msg-text-main);">
                    <i class="ph ${iconClass}" style="font-size:20px; color:var(--msg-primary);"></i>
                    <div style="flex:1; overflow:hidden;">
                        <div style="font-size:12px; font-weight:600; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;">${doc.file_name}</div>
                    </div>
                </a>`;
            });
        }
    } else if (currentGalleryTab === 'links') {
        grid.style.display = 'flex';
        grid.style.flexDirection = 'column';
        grid.style.gap = '8px';
        
        if (currentGalleryData.links.length === 0) {
            html = '<div style="font-size:12px; color: var(--msg-text-muted); text-align:center; padding: 1rem 0;">Sin enlaces</div>';
        } else {
            currentGalleryData.links.forEach(link => {
                html += `
                <a href="${link.url}" target="_blank" style="display:flex; align-items:center; gap:8px; padding:8px; background:var(--msg-bg); border-radius:6px; text-decoration:none; color:var(--msg-text-main);">
                    <div style="background:var(--msg-surface); padding:4px; border-radius:4px;"><i class="ph ph-link" style="color:var(--msg-primary);"></i></div>
                    <div style="flex:1; overflow:hidden; font-size:12px; word-break:break-all;">${link.url}</div>
                </a>`;
            });
        }
    }
    
    grid.innerHTML = html;
}

function pollMessages(fullRender) {
    if (!currentChatId) return;
    
    fetch(`modules/mensajes/ajax.php?action=poll&chat_id=${currentChatId}&last_id=${lastMessageId}`)
        .then(res => res.json())
        .then(data => {
            if (data.messages && data.messages.length > 0) {
                renderMessages(data.messages, fullRender);
            } else if (fullRender) {
                const area = document.getElementById('msgArea');
                if (area) {
                    area.innerHTML = `
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--msg-text-muted); opacity:0; animation: msgIn 0.5s forwards;">
                            <i class="ph ph-hand-waving" style="font-size: 48px; color: var(--msg-primary); margin-bottom: 16px; animation: floatIcon 3s ease-in-out infinite;"></i>
                            <h3 style="margin:0 0 8px 0; color:var(--msg-text-main); font-weight:600;">¡Este chat es nuevo!</h3>
                            <p style="font-size:13px; text-align:center;">Envía tu primer mensaje para iniciar la conversación.</p>
                        </div>
                    `;
                }
            }
            if (data.receipts && data.receipts.length > 0) {
                updateReceipts(data.receipts);
            }
            if (data.reactions) {
                updateReactions(data.reactions);
            }
            const inputTypingEl = document.getElementById('msgTypingIndicator');
            const inputTextEl = document.getElementById('msgTypingText');
            const headerTypingEl = document.getElementById('msgHeaderTypingIndicator');
            const statusEl = document.getElementById('msgHeaderStatus');
            
            if (data.typing && data.typing.length > 0) {
                let text = data.typing.join(', ') + (data.typing.length > 1 ? ' están escribiendo...' : ' está escribiendo...');
                if (inputTextEl) inputTextEl.innerText = text;
                if (inputTypingEl) inputTypingEl.style.display = 'flex';
                
                if (headerTypingEl) {
                    headerTypingEl.innerText = text;
                    headerTypingEl.style.display = 'block';
                }
                if (statusEl) statusEl.style.display = 'none';
            } else {
                if (inputTypingEl) inputTypingEl.style.display = 'none';
                if (headerTypingEl) headerTypingEl.style.display = 'none';
                if (statusEl) statusEl.style.display = 'block';
            }
            if (data.deleted && data.deleted.length > 0) {
                data.deleted.forEach(id => {
                    const msgEl = document.getElementById(`msg-${id}`);
                    if (msgEl && msgEl.dataset.deleted !== '1') {
                        // Mark as deleted visually
                        msgEl.dataset.deleted = '1';
                        const bubble = msgEl.querySelector('.msg-bubble');
                        if (bubble) bubble.innerHTML = '<div style="font-style:italic; color: var(--msg-text-muted);"><i class="ph ph-prohibit"></i> Este mensaje fue eliminado.</div>';
                    }
                });
            }
        });
}

window.msgReactions = {};

function updateReactions(reactions) {
    // Save to global scope for modal
    window.msgReactions = reactions;
    
    // reactions is an object: { 'msgId': [ {emoji, name}, ... ] }
    for (const [msgId, reacts] of Object.entries(reactions)) {
        let reactionsContainer = document.getElementById(`reactions-${msgId}`);
        const msgBubble = document.getElementById(`msg-${msgId}`);
        
        if (msgBubble) {
            // Group emojis
            let emojiCounts = {};
            let tooltipNames = {};
            reacts.forEach(r => {
                emojiCounts[r.emoji] = (emojiCounts[r.emoji] || 0) + 1;
                if (!tooltipNames[r.emoji]) tooltipNames[r.emoji] = [];
                tooltipNames[r.emoji].push(r.name);
            });
            
            let reactionsHtml = `<div class="msg-reactions-row" id="reactions-${msgId}" onclick="showReactionsModal(${msgId})">`;
            for (const [emoji, count] of Object.entries(emojiCounts)) {
                let title = tooltipNames[emoji].join(', ');
                let countLabel = count > 1 ? `<span style="font-weight:bold;margin-left:2px;">${count}</span>` : '';
                reactionsHtml += `<div class="msg-reaction-pill" title="${title}">${emoji}${countLabel}</div>`;
            }
            reactionsHtml += '</div>';

            if (reactionsContainer) {
                if (reacts.length === 0) reactionsContainer.remove();
                else reactionsContainer.outerHTML = reactionsHtml;
            } else if (reacts.length > 0) {
                const bubbleInner = msgBubble.querySelector('.msg-bubble');
                if (bubbleInner) bubbleInner.insertAdjacentHTML('afterend', reactionsHtml);
            }
        }
    }
}

function showReactionsModal(msgId) {
    if (!window.msgReactions || !window.msgReactions[msgId]) return;
    const reacts = window.msgReactions[msgId];
    
    let modal = document.getElementById('msgReactionsModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'msgReactionsModal';
        modal.className = 'msg-modal-overlay';
        modal.innerHTML = `
            <div class="msg-modal" style="max-width:300px; width:100%;">
                <div class="msg-modal-header">
                    <h3>Reacciones</h3>
                    <button class="msg-btn-close" onclick="closeReactionsModal()"><i class="ph ph-x"></i></button>
                </div>
                <div class="msg-modal-body" id="msgReactionsModalList" style="max-height:300px; overflow-y:auto; padding:0;">
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    const list = document.getElementById('msgReactionsModalList');
    list.innerHTML = '';
    reacts.forEach(r => {
        list.innerHTML += `
            <div style="display:flex; align-items:center; gap:12px; padding:12px 20px; border-bottom:1px solid var(--msg-border);">
                <div style="font-size:24px;">${r.emoji}</div>
                <div style="font-weight:600; color:var(--msg-text-main); font-size:14px;">${r.name}</div>
            </div>
        `;
    });
    
    // For trigger animation
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
}

function closeReactionsModal() {
    const modal = document.getElementById('msgReactionsModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 200);
    }
}

let lastRenderedDate = null;
let lastSenderId = null;

function renderMessages(messages, fullRender) {
    const area = document.getElementById('msgArea');
    const isScrolledToBottom = area.scrollHeight - area.clientHeight <= area.scrollTop + 50;

    if (fullRender) {
        area.innerHTML = '';
        lastRenderedDate = null;
        lastSenderId = null;
    }

    let addedNew = false;
    let htmlBuffer = '';
    let newMsgIds = [];
    messages.forEach(msg => {
        // Prevent duplicates
        if (document.querySelector(`.msg-bubble-wrap[data-id="${msg.id}"]`)) return;
        addedNew = true;

        const isOwn = msg.sender_user_id == CURRENT_USER_ID;
        const senderName = msg.sender_name || 'Desconocido';
        const msgDateObj = new Date(msg.created_at);
        const time = msgDateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        // Date Separator logic
        const dateStr = msgDateObj.toDateString();
        if (dateStr !== lastRenderedDate) {
            const today = new Date();
            const yesterday = new Date(); yesterday.setDate(today.getDate() - 1);
            
            let dateLabel = msgDateObj.toLocaleDateString([], {day: 'numeric', month: 'long', year: 'numeric'});
            if (dateStr === today.toDateString()) dateLabel = 'Hoy';
            else if (dateStr === yesterday.toDateString()) dateLabel = 'Ayer';
            
            htmlBuffer += `<div class="msg-date-separator"><span>${dateLabel}</span></div>`;
            lastRenderedDate = dateStr;
            lastSenderId = null; // Reset grouping after a date change
        }

        const isFirstInGroup = lastSenderId !== msg.sender_user_id;
        lastSenderId = msg.sender_user_id;

        let contentHtml = msg.content ? DOMPurify.sanitize(marked.parse(msg.content)) : '';
        let extractedUrl = null;
        if (msg.content && !msg.is_deleted) {
            const urlRegex = /(https?:\/\/[^\s]+)/i;
            const match = msg.content.match(urlRegex);
            if (match) {
                extractedUrl = match[1];
                contentHtml += `<div id="link-preview-${msg.id}" class="msg-link-preview-container" data-url="${extractedUrl}" style="margin-top:8px;"></div>`;
            }
        }
        
        let mediaHtml = '';
        if (msg.type === 'task') {
            mediaHtml = generateTaskCardHtml(msg);
            contentHtml = '';
        } else if (msg.type === 'pendiente') {
            mediaHtml = generatePendienteCardHtml(msg);
            contentHtml = '';
        } else if (msg.type === 'whiteboard') {
            mediaHtml = generateWhiteboardCardHtml(msg);
            // contentHtml remains if there is an optional message
        } else if (msg.file_url) {
            const isDriveLink = msg.file_url.includes('drive.google.com');
            const ext = msg.file_name ? msg.file_name.split('.').pop().toLowerCase() : '';
            const isImageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext);

            if (msg.type === 'image' || isImageExt) {
                if (isDriveLink) {
                    const driveMatch = msg.file_url.match(/\/d\/([a-zA-Z0-9_-]+)/);
                    if (driveMatch) {
                        const thumbUrl = `https://drive.google.com/thumbnail?id=${driveMatch[1]}&sz=w1200`;
                        mediaHtml = `<img src="${thumbUrl}" loading="lazy" style="max-width:200px; border-radius:8px; cursor:pointer;" onclick="openLightbox('image', '${thumbUrl}')" onerror="this.outerHTML='<a href=\\&quot;${msg.file_url}\\&quot; target=\\&quot;_blank\\&quot; style=\\&quot;display:inline-flex;align-items:center;gap:6px;padding:8px 12px;background:#f4f6f8;border-radius:8px;color:var(--msg-text-main);text-decoration:none;font-size:12px;\\&quot;><i class=\\&quot;ph ph-image\\&quot;></i>${msg.file_name || 'Ver imagen'}</a>'">`;
                    } else {
                        mediaHtml = `<a href="${msg.file_url}" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:8px 12px;background:#f4f6f8;border-radius:8px;color:var(--msg-text-main);text-decoration:none;font-size:12px;"><i class="ph ph-image"></i> ${msg.file_name || 'Ver imagen'}</a>`;
                    }
                } else {
                    mediaHtml = `<img src="${msg.file_url}" loading="lazy" style="max-width:200px; border-radius:8px; cursor:pointer;" onclick="openLightbox('image', '${msg.file_url}')">`;
                }
            } else if (msg.type === 'video') {
                if (isDriveLink) {
                    mediaHtml = `<a href="${msg.file_url}" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:8px 12px;background:#f4f6f8;border-radius:8px;color:var(--msg-text-main);text-decoration:none;font-size:12px;"><i class="ph ph-video-camera"></i> ${msg.file_name || 'Ver video'}</a>`;
                } else {
                    mediaHtml = `<video src="${msg.file_url}" controls style="max-width:250px; border-radius:8px;"></video>`;
                }
            } else if (msg.type === 'audio') {
                let streamUrl = msg.file_url;
                if (isDriveLink) {
                    const driveMatch = msg.file_url.match(/\/d\/([a-zA-Z0-9_-]+)/);
                    if (driveMatch) {
                        streamUrl = `modules/mensajes/ajax.php?action=stream_drive_audio&id=${driveMatch[1]}`;
                    } else {
                        streamUrl = '';
                    }
                }
                
                if (streamUrl) {
                    mediaHtml = `
                        <div class="msg-audio-player" data-src="${streamUrl}" id="audio-player-${msg.id}">
                            <button class="msg-audio-play-btn" onclick="toggleAudioPlayback('${msg.id}')">
                                <i class="ph-fill ph-play" id="audio-icon-${msg.id}"></i>
                            </button>
                            <div class="msg-audio-track" style="position:relative; width: 150px; height: 30px; display:flex; align-items:center;">
                                <!-- Static Waveform Background -->
                                <div style="display:flex; align-items:center; gap:2px; width:100%; height:100%; opacity:0.3;">
                                    ${[20,40,60,30,80,50,90,70,40,60,100,80,50,30,50,90,60,40,70,50,30,80,40,60,90,70,40,20,50,30].map(h => `<div style="flex:1; background:currentColor; height:${h}%; border-radius:1px;"></div>`).join('')}
                                </div>
                                <!-- Active Waveform Overlay -->
                                <div id="audio-wave-active-${msg.id}" style="display:flex; align-items:center; gap:2px; width:0%; height:100%; position:absolute; top:0; left:0; overflow:hidden; white-space:nowrap;">
                                    <div style="display:flex; align-items:center; gap:2px; width:150px; height:100%; color:var(--msg-primary);">
                                        ${[20,40,60,30,80,50,90,70,40,60,100,80,50,30,50,90,60,40,70,50,30,80,40,60,90,70,40,20,50,30].map(h => `<div style="flex:1; background:currentColor; height:${h}%; border-radius:1px;"></div>`).join('')}
                                    </div>
                                </div>
                                <!-- Invisible Range Input -->
                                <input type="range" min="0" max="100" value="0" class="msg-audio-progress" id="audio-progress-${msg.id}" oninput="seekAudio('${msg.id}', this.value)" onchange="seekAudioEnd('${msg.id}')" style="position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer; margin:0;">
                            </div>
                            <div class="msg-audio-time" id="audio-time-${msg.id}">0:00</div>
                            <audio id="audio-element-${msg.id}" src="${streamUrl}" ontimeupdate="updateAudioProgress('${msg.id}')" onloadedmetadata="setAudioDuration('${msg.id}')" onended="audioEnded('${msg.id}')" style="display:none;"></audio>
                        </div>
                    `;
                } else {
                    mediaHtml = `<a href="${msg.file_url}" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:8px 12px;background:#f4f6f8;border-radius:8px;color:var(--msg-text-main);text-decoration:none;font-size:12px;"><i class="ph ph-speaker-high"></i> ${msg.file_name || 'Escuchar audio'}</a>`;
                }
            } else {
                let iconClass = 'ph-file';
                let iconColor = '#94a3b8';
                if (ext === 'pdf') { iconClass = 'ph-file-pdf'; iconColor = '#ef4444'; }
                else if (ext === 'psd') { iconClass = 'ph-file-image'; iconColor = '#3b82f6'; }
                else if (ext === 'ai') { iconClass = 'ph-file-image'; iconColor = '#f97316'; }
                else if (['doc','docx'].includes(ext)) { iconClass = 'ph-file-doc'; iconColor = '#2563eb'; }
                else if (['xls','xlsx'].includes(ext)) { iconClass = 'ph-file-xls'; iconColor = '#16a34a'; }
                else if (['zip','rar'].includes(ext)) { iconClass = 'ph-file-archive'; iconColor = '#8b5cf6'; }

                let targetBlank = 'target="_blank"';
                let clickAction = '';
                if (ext === 'pdf' && !isDriveLink) {
                    targetBlank = '';
                    clickAction = `onclick="event.preventDefault(); openLightbox('pdf', '${msg.file_url}');"`;
                }

                let pdfPreviewHtml = '';
                if (ext === 'pdf') {
                    // Only embed local PDFs to avoid Google Drive CSP frame-ancestor errors
                    let previewUrl = msg.file_url;
                    if (!isDriveLink && !previewUrl.startsWith('http')) {
                        pdfPreviewHtml = `
                            <div style="margin-top:5px; border-radius:12px; overflow:hidden; border:1px solid var(--msg-border); max-width:250px; background:var(--msg-surface); display:flex; flex-direction:column;">
                                <div style="background:var(--msg-bg); padding:6px 10px; font-size:11px; font-weight:bold; color:var(--msg-text-muted); display:flex; justify-content:space-between; align-items:center;">
                                    <span><i class="ph-fill ph-file-pdf" style="color:#ef4444;"></i> PREVIEW</span>
                                    <i class="ph ph-arrows-out-simple" style="cursor:pointer;" onclick="openLightbox('pdf', '${msg.file_url}')" title="Expandir"></i>
                                </div>
                                <iframe src="${previewUrl}" width="100%" height="200" style="border:none;"></iframe>
                            </div>
                        `;
                    }
                }

                mediaHtml = `
                    ${pdfPreviewHtml}
                    <a href="${msg.file_url}" ${targetBlank} ${clickAction} style="display:flex; align-items:center; gap:12px; padding:12px; background:var(--msg-bg); border:1px solid var(--msg-border); border-radius:12px; color:var(--msg-text-main); text-decoration:none; max-width:250px; margin-top:5px; transition:0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); cursor:pointer;" onmouseover="this.style.borderColor='var(--msg-primary)'" onmouseout="this.style.borderColor='var(--msg-border)'">
                        <div style="font-size:24px; color:${iconColor};"><i class="ph ${iconClass}"></i></div>
                        <div style="flex:1; overflow:hidden;">
                            <div style="font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${msg.file_name || 'Archivo'}</div>
                            <div style="font-size:11px; color:var(--msg-text-muted); text-transform:uppercase;">${ext || 'FILE'}</div>
                        </div>
                    </a>
                `;
            }
        }

        let ticksHtml = '';
        if (isOwn) {
            ticksHtml = `<span class="msg-ticks" id="ticks-${msg.id}" title="Enviado a las ${time}"><i class="ph ph-check"></i></span>`;
        }
        
        let tailClass = isFirstInGroup ? 'has-tail' : '';
        
        let quotedHtml = '';
        if (msg.reply_to_id && msg.reply_sender) {
            quotedHtml = `
                <div class="msg-quoted-block" onclick="scrollToMessage(${msg.reply_to_id})">
                    <div class="msg-quoted-sender">${msg.reply_sender}</div>
                    <div class="msg-quoted-text">${msg.reply_content}</div>
                </div>
            `;
        }
        
        let contentToRender = contentHtml;
        let mediaToRender = mediaHtml;
        
        if (msg.is_deleted) {
            contentToRender = '<div style="font-style:italic; color: var(--msg-text-muted);"><i class="ph ph-prohibit"></i> Este mensaje fue eliminado.</div>';
            mediaToRender = '';
            quotedHtml = '';
        }

        const cleanContentForReply = msg.content ? msg.content.replace(/"/g, '&quot;') : (msg.file_name || 'Archivo');
        const cleanContentForEdit = msg.content ? msg.content.replace(/"/g, '&quot;') : '';
        const safeSender = senderName.replace(/"/g, '&quot;');

        let editedLabel = msg.is_edited && !msg.is_deleted ? '<span style="font-size:10px; opacity:0.7; margin-right:4px;">Editado</span>' : '';
        let starHtml = msg.is_starred && !msg.is_deleted ? '<i class="ph-fill ph-star" style="color: #eab308; margin-right:4px; font-size:12px;"></i>' : '';

        let bubbleHtml = '';
        if (msg.type === 'system') {
            const sysText = msg.content ? msg.content.replace(/<[^>]*>?/gm, '') : '';
            bubbleHtml = `
                <div class="msg-system-capsule-wrap" id="msg-${msg.id}" data-id="${msg.id}" data-type="system">
                    <div class="msg-system-capsule">
                        <i class="ph-fill ph-info"></i>
                        <span>${sysText}</span>
                    </div>
                </div>
            `;
        } else {
            bubbleHtml = `
                <div class="msg-bubble-wrap ${isOwn ? 'own' : ''} ${tailClass}" 
                    data-id="${msg.id}" 
                    id="msg-${msg.id}"
                    data-own="${isOwn}"
                    data-deleted="${msg.is_deleted ? '1' : '0'}"
                    data-starred="${msg.is_starred ? '1' : '0'}"
                    data-pinned="${msg.is_pinned ? '1' : '0'}"
                    data-reply="${cleanContentForReply}"
                    data-edit="${cleanContentForEdit}"
                    data-sender="${safeSender}"
                    data-type="${msg.type}">
                    <div style="display:flex; flex-direction:column; align-items: ${isOwn ? 'flex-end' : 'flex-start'}; max-width: 100%; position: relative;">
                        ${(!isOwn && isFirstInGroup) ? `<div class="msg-sender">${senderName}</div>` : ''}
                        
                        <div class="msg-bubble">
                            ${quotedHtml}
                            ${contentToRender}
                            ${mediaToRender}
                        </div>
                        <div class="msg-meta-out">
                            ${editedLabel}
                            ${starHtml}
                            <span>${time}</span>
                            ${ticksHtml}
                        </div>
                    </div>
                </div>
            `;
        }
        htmlBuffer += bubbleHtml;
        newMsgIds.push(msg.id);
        
        if (msg.id > lastMessageId) lastMessageId = msg.id;
        
        if (!isScrolledToBottom && !fullRender && !isOwn && msg.type !== 'system') {
            unreadInChat++;
        }
        
        if (msg.is_pinned && !msg.is_deleted) {
            updatePinnedBanner(msg);
        }
    });

    if (htmlBuffer) {
        area.insertAdjacentHTML('beforeend', htmlBuffer);
        if (scrollObserver) {
            newMsgIds.forEach(id => {
                const addedWrap = document.getElementById(`msg-${id}`);
                if (addedWrap) scrollObserver.observe(addedWrap);
            });
        }
    }

    // Check if we need to hide the banner (if no messages are pinned in the full render)
    if (fullRender) {
        const anyPinned = messages.find(m => m.is_pinned && !m.is_deleted);
        if (!anyPinned) hidePinnedBanner();
    }

    if (isScrolledToBottom || fullRender) {
        area.scrollTop = area.scrollHeight;
    } else if (addedNew) {
        updateScrollBadge();
    }
    
    if (addedNew || fullRender) {
        loadLinkPreviews();
    }
    
    // Play sound and show notification if new message arrived (and not initial load)
    if (addedNew && !fullRender) {
        const newOthersMsgs = messages.filter(m => m.sender_user_id != CURRENT_USER_ID);
        if (newOthersMsgs.length > 0) {
            playNotificationSound();
            if (document.hidden && window.Notification && Notification.permission === "granted") {
                const lastMsg = newOthersMsgs[newOthersMsgs.length - 1];
                const cleanContent = lastMsg.content ? lastMsg.content.replace(/<[^>]*>?/gm, '').substring(0, 100) : 'Archivo multimedia';
                new Notification("Mensaje de " + (lastMsg.sender_name || 'Usuario'), {
                    body: cleanContent,
                    icon: 'favicon.ico'
                });
            }
        }
    }
}

function loadLinkPreviews() {
    const containers = document.querySelectorAll('.msg-link-preview-container:not(.loaded)');
    containers.forEach(container => {
        container.classList.add('loaded'); // Mark to avoid duplicate requests
        const url = container.dataset.url;
        fetch('modules/mensajes/ajax.php?action=link_preview&url=' + encodeURIComponent(url))
            .then(r => r.json())
            .then(data => {
                if (data.title) {
                    container.innerHTML = `
                        <a href="${url}" target="_blank" class="msg-link-preview-card">
                            ${data.image ? `<img src="${data.image}" class="msg-link-preview-img">` : ''}
                            <div class="msg-link-preview-info">
                                <div class="msg-link-preview-title">${data.title}</div>
                                ${data.description ? `<div class="msg-link-preview-desc">${data.description}</div>` : ''}
                                <div class="msg-link-preview-url">${data.domain}</div>
                            </div>
                        </a>
                    `;
                } else {
                    container.style.display = 'none';
                }
            })
            .catch(() => container.style.display = 'none');
    });
}

function playNotificationSound() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        
        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        oscillator.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.1); // A5
        
        gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);
        
        oscillator.start();
        oscillator.stop(audioCtx.currentTime + 0.3);
    } catch(e) {
        console.warn('Audio play failed', e);
    }
}

function updatePinnedBanner(msg) {
    let banner = document.getElementById('msgPinnedBanner');
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'msgPinnedBanner';
        // Make it a flex item rather than absolute so it pushes the chat area down smoothly
        banner.style.cssText = 'background:var(--msg-surface); border-bottom:1px solid var(--msg-border); padding:8px 16px; display:flex; align-items:center; gap:12px; cursor:pointer; z-index:9; box-shadow:0 2px 4px rgba(0,0,0,0.05);';
        
        // Find header to insert after
        const header = document.querySelector('.msg-header');
        header.parentNode.insertBefore(banner, header.nextSibling);
    }
    
    let contentPreview = msg.content ? msg.content.replace(/<[^>]*>?/gm, '').substring(0, 50) : (msg.file_name || 'Archivo adjunto');
    if (contentPreview.length === 50) contentPreview += '...';
    
    banner.innerHTML = `
        <i class="ph-fill ph-push-pin" style="color:var(--msg-primary); font-size:20px;"></i>
        <div style="flex:1; overflow:hidden;">
            <div style="font-size:11px; color:var(--msg-primary); font-weight:bold;">Mensaje Fijado</div>
            <div style="font-size:13px; color:var(--msg-text-main); white-space:nowrap; text-overflow:ellipsis; overflow:hidden;">${contentPreview}</div>
        </div>
        <button class="msg-icon-btn" onclick="event.stopPropagation(); unpinMessage(${msg.id});" style="background:var(--msg-hover);"><i class="ph ph-x"></i></button>
    `;
    banner.onclick = () => {
        const target = document.getElementById('msg-' + msg.id);
        if (target) {
            target.scrollIntoView({behavior: 'smooth', block: 'center'});
            const originalBg = target.querySelector('.msg-bubble').style.backgroundColor;
            target.querySelector('.msg-bubble').style.backgroundColor = 'var(--msg-primary)';
            setTimeout(() => target.querySelector('.msg-bubble').style.backgroundColor = originalBg, 1500);
        }
    };
    banner.style.display = 'flex';
}

function hidePinnedBanner() {
    const banner = document.getElementById('msgPinnedBanner');
    if (banner) banner.style.display = 'none';
}

function unpinMessage(msgId) {
    if (!currentChatId) return;
    fetch('modules/mensajes/ajax.php?action=pin_message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message_id=${msgId}&chat_id=${currentChatId}`
    }).then(r => r.json()).then(data => {
        if (data.success) {
            const wrap = document.querySelector(`.msg-bubble-wrap[data-id="${msgId}"]`);
            if (wrap) wrap.setAttribute('data-pinned', '0');
            hidePinnedBanner();
            showToast('Mensaje desfijado');
        }
    });
}

function updateReceipts(receipts) {
    receipts.forEach(r => {
        const tickEl = document.getElementById(`ticks-${r.message_id}`);
        if (tickEl) {
            let timeStr = '';
            if (r.time) {
                const dateObj = new Date(r.time);
                timeStr = ` el ${dateObj.toLocaleDateString()} a las ${dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}`;
            }

            if (r.status === 'read') {
                tickEl.innerHTML = '<i class="ph ph-checks"></i>';
                tickEl.className = 'msg-ticks read';
                tickEl.title = 'Leído' + timeStr;
            } else if (r.status === 'delivered' && !tickEl.classList.contains('read')) {
                tickEl.innerHTML = '<i class="ph ph-checks"></i>';
                tickEl.className = 'msg-ticks unread';
                tickEl.title = 'Entregado' + timeStr;
            }
        }
    });
}

let ctxMsgId = null;
let ctxMsgIsOwn = false;
let ctxMsgContentReply = '';
let ctxMsgContentEdit = '';
let ctxMsgSender = '';
let ctxMsgIsStarred = false;

let ctxMsgIsPinned = false;

function showContextMenu(e, msgId, isOwn, isDeleted, contentReply, contentEdit, senderName, type, isStarred, isPinned) {
    if (isDeleted) return; // No context menu for deleted messages
    e.preventDefault();
    
    ctxMsgId = msgId;
    ctxMsgIsOwn = isOwn;
    ctxMsgContentReply = contentReply;
    ctxMsgContentEdit = contentEdit;
    ctxMsgSender = senderName;
    ctxMsgType = type;
    ctxMsgIsStarred = isStarred;
    ctxMsgIsPinned = isPinned;
    
    const menu = document.getElementById('msgContextMenu');
    
    // Toggle edit/delete buttons based on ownership
    const editBtn = document.getElementById('ctxEditBtn');
    const deleteBtn = document.getElementById('ctxDeleteBtn');
    const starBtnText = document.getElementById('ctxStarBtnText');
    const starBtnIcon = document.getElementById('ctxStarBtnIcon');
    const pinBtnText = document.getElementById('ctxPinBtnText');
    const pinBtnIcon = document.getElementById('ctxPinBtnIcon');
    
    if (editBtn) editBtn.style.display = (isOwn && type === 'text') ? 'flex' : 'none';
    if (deleteBtn) deleteBtn.style.display = isOwn ? 'flex' : 'none';
    
    if (starBtnText && starBtnIcon) {
        if (isStarred) {
            starBtnText.innerText = 'Quitar destacado';
            starBtnIcon.className = 'ph-fill ph-star';
            starBtnIcon.style.color = '#eab308';
        } else {
            starBtnText.innerText = 'Destacar';
            starBtnIcon.className = 'ph ph-star';
            starBtnIcon.style.color = '';
        }
    }

    if (pinBtnText && pinBtnIcon) {
        if (isPinned) {
            pinBtnText.innerText = 'Desfijar';
            pinBtnIcon.className = 'ph-fill ph-push-pin';
            pinBtnIcon.style.color = 'var(--msg-primary)';
        } else {
            pinBtnText.innerText = 'Fijar';
            pinBtnIcon.className = 'ph ph-push-pin';
            pinBtnIcon.style.color = '';
        }
    }
    
    // Position the menu
    menu.style.display = 'block';
    
    // Position the menu based on viewport coordinates (since it's position: fixed)
    let topPos = e.clientY;
    let leftPos = e.clientX;
    
    // Adjust if it goes out of bounds
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    if (leftPos + menu.offsetWidth > viewportWidth) {
        leftPos = viewportWidth - menu.offsetWidth - 10;
    }
    
    if (topPos + menu.offsetHeight > viewportHeight) {
        topPos = viewportHeight - menu.offsetHeight - 10;
    }
    
    menu.style.top = topPos + 'px';
    menu.style.left = leftPos + 'px';
}

document.addEventListener('contextmenu', (e) => {
    const bubbleWrap = e.target.closest('.msg-bubble-wrap');
    if (bubbleWrap && document.getElementById('msgArea').contains(bubbleWrap)) {
        const msgId = bubbleWrap.getAttribute('data-id');
        const isOwn = bubbleWrap.getAttribute('data-own') === 'true';
        const isDeleted = bubbleWrap.getAttribute('data-deleted') === '1';
        const isStarred = bubbleWrap.getAttribute('data-starred') === '1';
        const isPinned = bubbleWrap.getAttribute('data-pinned') === '1';
        const contentReply = bubbleWrap.getAttribute('data-reply');
        const contentEdit = bubbleWrap.getAttribute('data-edit');
        const senderName = bubbleWrap.getAttribute('data-sender');
        const type = bubbleWrap.getAttribute('data-type');
        
        showContextMenu(e, msgId, isOwn, isDeleted, contentReply, contentEdit, senderName, type, isStarred, isPinned);
    }
});

function sendReaction(emoji) {
    if (!ctxMsgId) return;
    document.getElementById('msgContextMenu').style.display = 'none';
    
    const formData = new FormData();
    formData.append('message_id', ctxMsgId);
    formData.append('emoji', emoji);
    
    fetch('modules/mensajes/ajax.php?action=react', {
        method: 'POST',
        body: formData
    }).then(() => {
        // Force immediate poll to reflect the reaction instantly
        pollMessages(false);
    });
}

function ctxReply() {
    document.getElementById('msgContextMenu').style.display = 'none';
    setReplyTo(ctxMsgId, ctxMsgSender, ctxMsgContentReply);
}

function ctxEdit() {
    document.getElementById('msgContextMenu').style.display = 'none';
    editMessage(ctxMsgId, ctxMsgContentEdit);
}

function ctxDelete() {
    document.getElementById('msgContextMenu').style.display = 'none';
    deleteMessage(ctxMsgId);
}

function ctxPin() {
    document.getElementById('msgContextMenu').style.display = 'none';
    if (!ctxMsgId || !currentChatId) return;
    
    fetch('modules/mensajes/ajax.php?action=pin_message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message_id=${ctxMsgId}&chat_id=${currentChatId}`
    }).then(r => r.json()).then(data => {
        if (data.success) {
            const wrap = document.querySelector(`.msg-bubble-wrap[data-id="${ctxMsgId}"]`);
            if (wrap) wrap.setAttribute('data-pinned', data.is_pinned ? '1' : '0');
            
            if (data.is_pinned) {
                showToast('Mensaje fijado');
                if (wrap) {
                    const content = wrap.getAttribute('data-reply') || 'Mensaje adjunto';
                    updatePinnedBanner({ id: ctxMsgId, content: content });
                }
            } else {
                hidePinnedBanner();
                showToast('Mensaje desfijado');
            }
        }
    });
}
function ctxForward() {
    document.getElementById('msgContextMenu').style.display = 'none';
    if (!ctxMsgId) {
        alert("Error: No se ha seleccionado ningún mensaje (ctxMsgId es nulo).");
        return;
    }
    
    const modal = document.getElementById('msgForwardModal');
    if (!modal) {
        alert("Error: El modal de reenviar no existe en la página. Por favor, limpia la caché de tu navegador y recarga (Ctrl+F5).");
        return;
    }
    
    const list = document.getElementById('msgForwardChatsList');
    if (!list) {
        alert("Error: No se encontró la lista de chats en el modal.");
        return;
    }
    list.innerHTML = '<div style="text-align:center; padding:1rem;">Cargando chats...</div>';
    modal.style.display = 'flex';
    setTimeout(() => { modal.classList.add('active'); }, 10);
    
    // We already have loaded chats in window.cachedChats if we save them, but let's just fetch them or use sidebar DOM
    const chatItems = document.querySelectorAll('.msg-chat-item');
    let html = '';
    chatItems.forEach(item => {
        try {
            const onclickAttr = item.getAttribute('onclick');
            if (!onclickAttr) return;
            const match = onclickAttr.match(/\d+/);
            if (!match) return;
            
            const id = match[0];
            const nameEl = item.querySelector('.msg-chat-name');
            const name = nameEl ? nameEl.innerText : 'Chat';
            const avatarEl = item.querySelector('.msg-chat-avatar');
            const avatarHtml = avatarEl ? avatarEl.innerHTML : '';
            const avatarStyle = avatarEl ? avatarEl.getAttribute('style') || '' : '';
            
            html += `
            <div class="msg-user-item forward-chat-item" onclick="executeForward(${id})" style="cursor:pointer; display:flex; align-items:center; padding:8px; border-bottom:1px solid var(--msg-border);">
                <div class="msg-chat-avatar" style="width:30px; height:30px; font-size:14px; margin-right:10px; ${avatarStyle}">${avatarHtml}</div>
                <div style="flex:1;">
                    <div style="font-weight:600; font-size:13px; color:var(--msg-text-main);">${name}</div>
                </div>
                <i class="ph ph-paper-plane-right" style="color:var(--msg-primary);"></i>
            </div>`;
        } catch(e) { console.error('Error parsing chat item', e); }
    });
    
    if (!html) html = '<div style="text-align:center; padding:1rem; font-size:12px;">No hay otros chats disponibles.</div>';
    list.innerHTML = html;
}

function closeForwardModal() {
    const modal = document.getElementById('msgForwardModal');
    if (!modal) return;
    modal.classList.remove('active');
    setTimeout(() => { modal.style.display = 'none'; }, 250);
}

function filterForwardChats() {
    const q = document.getElementById('msgSearchForwardInput').value.toLowerCase();
    const items = document.querySelectorAll('.forward-chat-item');
    items.forEach(item => {
        const name = item.querySelector('div[style*="font-weight:600"]').innerText.toLowerCase();
        item.style.display = name.includes(q) ? 'flex' : 'none';
    });
}

function executeForward(targetChatId) {
    if (!ctxMsgId || !targetChatId) return;
    fetch('modules/mensajes/ajax.php?action=forward_message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message_id=${ctxMsgId}&target_chat_id=${targetChatId}`
    }).then(r => r.json()).then(data => {
        if (data.success) {
            closeForwardModal();
            showToast('Mensaje reenviado');
            if (currentChatId == targetChatId) {
                pollMessages(false);
            }
        }
    });
}
function ctxCopy() {
    document.getElementById('msgContextMenu').style.display = 'none';
    if (ctxMsgContentEdit) {
        // Decode basic HTML entities
        let textToCopy = ctxMsgContentEdit.replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
        
        // Use modern clipboard API if available
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textToCopy).then(() => {
                showToast('Mensaje copiado al portapapeles');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                fallbackCopy(textToCopy);
            });
        } else {
            // Fallback for non-HTTPS or unsupported browsers
            fallbackCopy(textToCopy);
        }
    }
}

function fallbackCopy(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";  // Avoid scrolling to bottom
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
        showToast('Mensaje copiado al portapapeles');
    } catch (err) {
        console.error('Fallback copy failed', err);
        showToast('No se pudo copiar el mensaje');
    }
    document.body.removeChild(textArea);
}

function showToast(msg) {
    let toast = document.getElementById('msgToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'msgToast';
        toast.style.cssText = 'position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.8); color:#fff; padding:10px 20px; border-radius:20px; font-size:14px; z-index:9999; opacity:0; transition:opacity 0.3s;';
        document.body.appendChild(toast);
    }
    toast.innerText = msg;
    toast.style.opacity = '1';
    setTimeout(() => {
        toast.style.opacity = '0';
    }, 2500);
}

function ctxStar() {
    if (!ctxMsgId) return;
    document.getElementById('msgContextMenu').style.display = 'none';
    
    const formData = new FormData();
    formData.append('message_id', ctxMsgId);
    
    fetch('modules/mensajes/ajax.php?action=star_message', {
        method: 'POST',
        body: formData
    }).then(res => res.json())
      .then(data => {
          if (data.success) {
              const msgWrap = document.getElementById('msg-' + ctxMsgId);
              if (msgWrap) {
                  const meta = msgWrap.querySelector('.msg-meta-out');
                  if (data.action === 'starred') {
                      msgWrap.setAttribute('data-starred', '1');
                      const starHtml = '<i class="ph-fill ph-star" style="color: #eab308; margin-right:4px; font-size:12px;"></i>';
                      // Insert before the time span
                      const timeSpan = meta.querySelector('span');
                      timeSpan.insertAdjacentHTML('beforebegin', starHtml);
                  } else {
                      msgWrap.setAttribute('data-starred', '0');
                      const starIcon = meta.querySelector('.ph-star');
                      if (starIcon) starIcon.remove();
                  }
              }
              showToast(data.action === 'starred' ? 'Mensaje destacado' : 'Mensaje ya no está destacado');
          }
      });
}



// Close context menu on outside click
document.addEventListener('click', (e) => {
    const menu = document.getElementById('msgContextMenu');
    if (menu && menu.style.display === 'block' && !menu.contains(e.target)) {
        menu.style.display = 'none';
    }
});

let typingTimeout = null;
let lastTypingTime = 0;

function handleTyping() {
    const now = Date.now();
    if (now - lastTypingTime > 2000 && currentChatId) {
        lastTypingTime = now;
        fetch('modules/mensajes/ajax.php?action=typing&chat_id=' + currentChatId);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const inputEl = document.getElementById('msgInput');
    if (inputEl) {
        inputEl.addEventListener('input', handleTyping);
    }
});

function handleInputKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function setReplyTo(msgId, senderName, content) {
    document.getElementById('msgReplyToId').value = msgId;
    document.getElementById('msgReplyPreviewSender').innerText = senderName;
    document.getElementById('msgReplyPreviewText').innerText = content;
    document.getElementById('msgReplyPreviewContainer').style.display = 'flex';
    document.getElementById('msgInput').focus();
}

let editingMsgId = null;

function editMessage(msgId, currentContent) {
    editingMsgId = msgId;
    const input = document.getElementById('msgInput');
    input.value = currentContent;
    input.focus();
    
    // Add visual cue
    document.getElementById('msgReplyPreviewSender').innerText = 'Editando mensaje';
    document.getElementById('msgReplyPreviewText').innerText = currentContent;
    document.getElementById('msgReplyPreviewContainer').style.display = 'flex';
}

function cancelReply() {
    document.getElementById('msgReplyToId').value = '';
    editingMsgId = null;
    document.getElementById('msgReplyPreviewContainer').style.display = 'none';
    const input = document.getElementById('msgInput');
    if (input) input.value = '';
}

function deleteMessage(msgId) {
    if (confirm('¿Estás seguro de eliminar este mensaje?')) {
        const formData = new FormData();
        formData.append('message_id', msgId);
        fetch('modules/mensajes/ajax.php?action=delete_message', {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(data => {
            if (data.success) {
                // Instantly update UI or let poll handle it
                // We'll just let poll handle it by reloading
                document.getElementById('msgArea').innerHTML = '';
                lastMessageId = 0;
                pollMessages(true);
            }
        });
    }
}

function scrollToMessage(msgId) {
    const msgEl = document.getElementById(`msg-${msgId}`);
    if (msgEl) {
        msgEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        msgEl.style.transition = 'background-color 0.5s';
        const originalBg = msgEl.style.backgroundColor;
        msgEl.style.backgroundColor = 'rgba(0,200,83,0.2)';
        setTimeout(() => {
            msgEl.style.backgroundColor = originalBg;
        }, 1500);
    }
}

function sendMessage() {
    if (!currentChatId) return;
    
    const input = document.getElementById('msgInput');
    const textContent = input.value.trim();
    
    if (!textContent && pendingFiles.length === 0) return;
    
    if (editingMsgId) {
        // Send edit request
        const formData = new FormData();
        formData.append('message_id', editingMsgId);
        formData.append('content', textContent);
        fetch('modules/mensajes/ajax.php?action=edit_message', {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(data => {
            if (data.success) {
                document.getElementById('msgArea').innerHTML = '';
                lastMessageId = 0;
                pollMessages(true);
            }
        });
        cancelReply();
        return;
    }
    
    // Check for confetti keywords
    const lowerText = textContent.toLowerCase();
    if (lowerText.includes('felicidades') || lowerText.includes('feliz cumpleaños') || lowerText.includes('congratulations') || lowerText.includes('feliz año')) {
        fireConfetti();
    }
    
    const replyToInput = document.getElementById('msgReplyToId');
    const replyToId = replyToInput ? replyToInput.value : '';

    
    input.value = '';
    input.style.height = 'auto'; // Reset auto-expand height
    const previewEl = document.getElementById('msgMarkdownPreview');
    if (previewEl) {
        previewEl.style.display = 'none';
        previewEl.innerHTML = '';
    }
    
    // Disable send button
    const sendBtn = document.querySelector('.msg-btn-send');
    if (sendBtn) { sendBtn.disabled = true; sendBtn.style.opacity = '0.5'; }

    // Haptic feedback
    if (navigator.vibrate) navigator.vibrate(20);

    const filesToSend = [...pendingFiles];
    clearFilePreview();
    cancelReply(); // Clear reply after sending
    
    let currentIndex = 0;
    
    const sendNext = () => {
        const formData = new FormData();
        formData.append('chat_id', currentChatId);
        
        // El texto solo va con el primer elemento
        if (currentIndex === 0) {
            formData.append('content', textContent);
            if (replyToId) formData.append('reply_to_id', replyToId);
        } else {
            formData.append('content', '');
        }
        
        let isFileUpload = false;
        if (filesToSend.length > 0 && currentIndex < filesToSend.length) {
            formData.append('file', filesToSend[currentIndex]);
            isFileUpload = true;
        } else if (currentIndex > 0) {
            return finishSending();
        }

        if (currentIndex === 0 && filesToSend.length === 0 && !textContent) return finishSending();
        
        // Loading bubble
        let loadingId = null;
        if (isFileUpload) {
            loadingId = 'loading-' + Date.now();
            const area = document.getElementById('msgArea');
            let loadingText = `Subiendo ${filesToSend[currentIndex].name}...`;
            let loadingIndicatorHtml = `<div class="msg-upload-spinner"></div>`;
            
            if (filesToSend[currentIndex].type.startsWith('audio')) {
                loadingText = `Guardando audio en Drive...`;
                loadingIndicatorHtml = `<i class="ph ph-cloud-arrow-up msg-spin" style="font-size:20px; color:var(--msg-primary);"></i>`;
            }
            
            const loadingHtml = `
                <div class="msg-bubble-wrap own" id="${loadingId}" data-id="loading">
                    <div style="display:flex; flex-direction:column; align-items: flex-end; max-width: 100%;">
                        <div class="msg-bubble" style="display:flex; align-items:center; gap:8px; padding:12px 16px;">
                            ${loadingIndicatorHtml}
                            <span style="font-size:12px; color: var(--msg-text-muted); font-weight:500;">${loadingText}</span>
                        </div>
                    </div>
                </div>
            `;
            area.insertAdjacentHTML('beforeend', loadingHtml);
            area.scrollTop = area.scrollHeight;
        }

        fetch('modules/mensajes/ajax.php?action=send_message', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (loadingId) {
                const el = document.getElementById(loadingId);
                if (el) el.remove();
            }
            if (!data.success) {
                alert('Error al enviar: ' + (data.error || 'Desconocido'));
            }
            
            currentIndex++;
            if (currentIndex < Math.max(1, filesToSend.length)) {
                sendNext();
            } else {
                finishSending();
            }
        })
        .catch(err => {
            if (loadingId) {
                const el = document.getElementById(loadingId);
                if (el) el.remove();
            }
            alert('Error de conexión al enviar el mensaje.');
            finishSending();
        });
    };
    
    const finishSending = () => {
        if (sendBtn) { sendBtn.disabled = false; sendBtn.style.opacity = '1'; }
        if (typeof handleInputState === 'function') handleInputState();
        pollMessages(false);
    };

    sendNext();
}

function triggerFileInput(accept) {
    const fileInput = document.getElementById('msgHiddenFileInput');
    fileInput.accept = accept;
    fileInput.click();
    document.getElementById('msgAttachMenu').classList.remove('active');
}

function compressImage(file, maxWidth, quality) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = event => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                let width = img.width;
                let height = img.height;
                if (width > maxWidth) {
                    height = Math.round((height * maxWidth) / width);
                    width = maxWidth;
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob((blob) => {
                    resolve(blob);
                }, file.type, quality);
            };
        };
    });
}

function handleFileSelect(e) {
    if (e.target.files && e.target.files.length > 0) {
        const promises = Array.from(e.target.files).map(file => {
            return new Promise(resolve => {
                if (file.type.startsWith('image/') && file.size > 500 * 1024) {
                    // Compress images larger than 500kb
                    compressImage(file, 1200, 0.8).then(blob => {
                        const newFile = new File([blob], file.name, {
                            type: file.type,
                            lastModified: Date.now()
                        });
                        pendingFiles.push(newFile);
                        resolve();
                    });
                } else {
                    pendingFiles.push(file);
                    resolve();
                }
            });
        });
        
        Promise.all(promises).then(() => {
            renderFilePreview();
        });
    }
}



function clearFilePreview() {
    pendingFiles = [];
    renderFilePreview();
    document.getElementById('msgHiddenFileInput').value = '';
}

function toggleInfoPanel() {
    const panel = document.getElementById('msgInfoPanel');
    if (panel) panel.classList.toggle('active');
}

function copyPublicLink() {
    const input = document.getElementById('msgPublicLink');
    input.select();
    document.execCommand('copy');
    showToast('Enlace copiado al portapapeles');
}

let currentLightboxImages = [];
let currentLightboxIndex = -1;

function openLightbox(type, url) {
    const lb = document.getElementById('msgLightbox');
    const body = document.getElementById('msgLightboxBody');
    const prevBtn = document.getElementById('lbPrevBtn');
    const nextBtn = document.getElementById('lbNextBtn');
    
    lb.style.display = 'flex';
    
    if (prevBtn) prevBtn.style.display = 'none';
    if (nextBtn) nextBtn.style.display = 'none';
    currentLightboxImages = [];
    currentLightboxIndex = -1;
    
    if (type === 'image') {
        resetLightboxZoom();
        body.innerHTML = `<img src="${url}">`;
        
        // Find all images in chat area
        const imageEls = Array.from(document.querySelectorAll('#msgArea [onclick^="openLightbox(\\\'image\\\'"], #msgGalleryGrid [onclick^="openLightbox(\\\'image\\\'"]'));
        
        const urls = imageEls.map(el => {
            const attr = el.getAttribute('onclick');
            const match = attr.match(/'image',\s*'([^']+)'/);
            return match ? match[1] : null;
        }).filter(u => u);
        
        // Deduplicate
        currentLightboxImages = [...new Set(urls)];
        currentLightboxIndex = currentLightboxImages.indexOf(url);
        
        if (currentLightboxImages.length > 1) {
            updateLightboxArrows();
        }
        
    } else if (type === 'video') {
        body.innerHTML = `<video src="${url}" controls autoplay></video>`;
    } else if (type === 'pdf') {
        body.innerHTML = `<iframe src="${url}" style="width:90vw; height:90vh; border:none; border-radius:12px; background:white;"></iframe>`;
    }
}

function updateLightboxArrows() {
    const prevBtn = document.getElementById('lbPrevBtn');
    const nextBtn = document.getElementById('lbNextBtn');
    if (prevBtn) prevBtn.style.display = currentLightboxIndex > 0 ? 'flex' : 'none';
    if (nextBtn) nextBtn.style.display = currentLightboxIndex < currentLightboxImages.length - 1 ? 'flex' : 'none';
}

function prevLightboxImage() {
    if (currentLightboxIndex > 0) {
        currentLightboxIndex--;
        resetLightboxZoom();
        document.getElementById('msgLightboxBody').innerHTML = `<img src="${currentLightboxImages[currentLightboxIndex]}">`;
        updateLightboxArrows();
    }
}

function nextLightboxImage() {
    if (currentLightboxIndex < currentLightboxImages.length - 1) {
        currentLightboxIndex++;
        resetLightboxZoom();
        document.getElementById('msgLightboxBody').innerHTML = `<img src="${currentLightboxImages[currentLightboxIndex]}">`;
        updateLightboxArrows();
    }
}

document.addEventListener('keydown', (e) => {
    const lb = document.getElementById('msgLightbox');
    if (lb && lb.style.display === 'flex') {
        if (e.key === 'ArrowLeft') prevLightboxImage();
        if (e.key === 'ArrowRight') nextLightboxImage();
        if (e.key === 'Escape') closeLightbox();
    }
});

function closeLightbox() {
    document.getElementById('msgLightbox').style.display = 'none';
    document.getElementById('msgLightboxBody').innerHTML = '';
}

function openNewChatModal() {
    const modal = document.getElementById('msgNewChatModal');
    modal.style.display = 'flex';
    // Small delay to allow transition
    setTimeout(() => {
        modal.classList.add('active');
        document.getElementById('newChatName').focus();
    }, 10);
}

function closeNewChatModal() {
    const modal = document.getElementById('msgNewChatModal');
    modal.classList.remove('active');
    setTimeout(() => {
        modal.style.display = 'none';
        document.getElementById('newChatName').value = '';
    }, 300);
}

function submitNewChatModal() {
    const nameInput = document.getElementById('newChatName');
    const name = nameInput.value.trim();
    if (name) {
        closeNewChatModal();
        fetch('modules/mensajes/ajax.php?action=create_chat', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `name=${encodeURIComponent(name)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadChats();
                openChat(data.chat_id, name, data.public_link);
            } else {
                alert("Error al crear chat");
            }
        });
    }
}

function openDriveSelector() {
    if (typeof DriveExplorer !== 'undefined') {
        DriveExplorer.openGlobalModal();
        DriveExplorer.setOnFolderSelect((fileId, fileName) => {
            // Save folder id
            fetch('modules/mensajes/ajax.php?action=save_drive_folder', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `chat_id=${currentChatId}&folder_id=${encodeURIComponent(fileId)}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert('Carpeta vinculada exitosamente');
                    loadChatInfo(currentChatId);
                    document.getElementById('global-drive-modal').classList.remove('active');
                } else {
                    alert('Error vinculando carpeta: ' + data.error);
                }
            });
        });
    } else {
        alert("El explorador de Drive no está disponible.");
    }
}

// ============ EDIT GROUP ============
function openEditGroupModal() {
    if (!currentChatId) return;
    const modal = document.getElementById('msgEditGroupModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);

    // Pre-fill current values
    const currentName = document.getElementById('msgInfoName').innerText;
    document.getElementById('msgEditGroupName').value = currentName;

    // Pre-fill avatar preview
    const preview = document.getElementById('msgEditAvatarPreview');
    const currentAvatar = document.getElementById('msgInfoAvatar');
    if (currentAvatar.style.backgroundImage && currentAvatar.style.backgroundImage !== '') {
        preview.style.backgroundImage = currentAvatar.style.backgroundImage;
        preview.innerHTML = '';
    } else {
        preview.style.backgroundImage = '';
        preview.innerHTML = '<i class="ph ph-camera"></i>';
    }
}

function closeEditGroupModal() {
    const modal = document.getElementById('msgEditGroupModal');
    modal.classList.remove('active');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
}

function previewAvatar(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('msgEditAvatarPreview');
        preview.style.backgroundImage = `url(${e.target.result})`;
        preview.innerHTML = '';
    };
    reader.readAsDataURL(file);
}

function saveGroupInfo(event) {
    event.preventDefault();
    if (!currentChatId) return;

    const name = document.getElementById('msgEditGroupName').value.trim();
    if (!name) return;

    const formData = new FormData();
    formData.append('chat_id', currentChatId);
    formData.append('name', name);

    const avatarInput = document.getElementById('msgEditAvatarInput');
    if (avatarInput.files.length > 0) {
        formData.append('avatar', avatarInput.files[0]);
    }

    fetch('modules/mensajes/ajax.php?action=update_chat_info', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeEditGroupModal();
            loadChatInfo(currentChatId);
            loadChats();
            avatarInput.value = '';
        } else {
            alert('Error: ' + (data.error || 'No se pudo actualizar'));
        }
    });
}

// ============ ADD USER ============
let availableUsersCache = [];

function openAddUserModal() {
    if (!currentChatId) return;
    const modal = document.getElementById('msgAddUserModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);

    document.getElementById('msgSearchUserInput').value = '';
    document.getElementById('msgAvailableUsersList').innerHTML = '<div style="text-align:center; padding:1rem; color: var(--msg-text-muted); font-size:12px;">Cargando...</div>';

    fetch('modules/mensajes/ajax.php?action=get_available_users&chat_id=' + currentChatId)
        .then(r => r.json())
        .then(data => {
            availableUsersCache = data.users || [];
            renderAvailableUsers(availableUsersCache);
        });
}

function closeAddUserModal() {
    const modal = document.getElementById('msgAddUserModal');
    modal.classList.remove('active');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
}

function renderAvailableUsers(users) {
    const list = document.getElementById('msgAvailableUsersList');
    if (users.length === 0) {
        list.innerHTML = '<div style="text-align:center; padding:1rem; color: var(--msg-text-muted); font-size:12px;">No hay usuarios disponibles</div>';
        return;
    }
    let html = '';
    users.forEach(u => {
        let initial = u.name ? u.name.charAt(0).toUpperCase() : '#';
        html += `
        <div class="msg-user-item" id="avail-user-${u.id}">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <div class="msg-member-avatar">${initial}</div>
                <div>
                    <div style="font-size:13px; font-weight:500;">${u.name}</div>
                    <div style="font-size:11px; color: var(--msg-text-muted);">${u.email}</div>
                </div>
            </div>
            <button class="msg-btn-sm-primary" onclick="addUserToChat(${u.id}, '${u.name.replace(/'/g, "\\'")}')">
                <i class="ph ph-plus"></i> A\u00f1adir
            </button>
        </div>`;
    });
    list.innerHTML = html;
}

function filterAvailableUsers() {
    const query = document.getElementById('msgSearchUserInput').value.toLowerCase();
    const filtered = availableUsersCache.filter(u => 
        u.name.toLowerCase().includes(query) || u.email.toLowerCase().includes(query)
    );
    renderAvailableUsers(filtered);
}

function addUserToChat(userId, userName) {
    fetch('modules/mensajes/ajax.php?action=add_participant', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `chat_id=${currentChatId}&user_id=${userId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Remove from list UI
            const el = document.getElementById('avail-user-' + userId);
            if (el) el.remove();
            availableUsersCache = availableUsersCache.filter(u => u.id !== userId);
            // Refresh members
            loadChatInfo(currentChatId);
        } else {
            alert('Error: ' + (data.error || 'No se pudo a\u00f1adir'));
        }
    });
}

// ============ DRAG & DROP AND PASTE ============
document.addEventListener('DOMContentLoaded', () => {
    const chatView = document.getElementById('msgChatView');
    const dragOverlay = document.getElementById('msgDragOverlay');
    let dragCounter = 0;

    if (chatView && dragOverlay) {
        chatView.addEventListener('dragenter', (e) => {
            e.preventDefault();
            if (!currentChatId) return;
            dragCounter++;
            dragOverlay.classList.add('active');
        });

        chatView.addEventListener('dragleave', (e) => {
            e.preventDefault();
            if (!currentChatId) return;
            dragCounter--;
            if (dragCounter === 0) {
                dragOverlay.classList.remove('active');
            }
        });

        chatView.addEventListener('dragover', (e) => {
            e.preventDefault();
        });

        chatView.addEventListener('drop', (e) => {
            e.preventDefault();
            dragCounter = 0;
            dragOverlay.classList.remove('active');
            
            if (!currentChatId) return;
            
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                for(let i = 0; i < e.dataTransfer.files.length; i++) {
                    const file = e.dataTransfer.files[i];
                    handleFileObject(file);
                }
            }
        });
    }

    // Paste event
    window.addEventListener('paste', (e) => {
        if (!currentChatId) return;
        
        // If they are typing in an input other than msgInput, don't hijack paste unless it's a file
        const activeElement = document.activeElement;
        const isTextInput = activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA';
        
        if (e.clipboardData.files && e.clipboardData.files.length > 0) {
            e.preventDefault();
            for(let i = 0; i < e.clipboardData.files.length; i++) {
                const file = e.clipboardData.files[i];
                handleFileObject(file);
            }
        }
    });
});

function handleFileObject(file) {
    pendingFiles.push(file);
    renderFilePreview();
    document.getElementById('msgInput').focus();
}

function removePendingFile(index) {
    pendingFiles.splice(index, 1);
    renderFilePreview();
}

function renderFilePreview() {
    const previewContainer = document.getElementById('msgFilePreviewContainer');
    if (pendingFiles.length === 0) {
        previewContainer.style.display = 'none';
        previewContainer.innerHTML = '';
        return;
    }
    
    let html = '';
    pendingFiles.forEach((file, index) => {
        let fileIcon = '<i class="ph ph-file-text"></i>';
        if (file.type.startsWith('image/')) fileIcon = '<i class="ph ph-image"></i>';
        else if (file.type.startsWith('video/')) fileIcon = '<i class="ph ph-video-camera"></i>';
        else if (file.type.startsWith('audio/')) fileIcon = '<i class="ph ph-speaker-high"></i>';
        
        let name = file.name;
        if (name.length > 20) name = name.substring(0, 17) + '...';

        html += `
            <div class="msg-file-preview-item">
                <div class="msg-file-preview-icon">${fileIcon}</div>
                <span class="msg-file-preview-name" title="${file.name}">${name}</span>
                <button class="msg-icon-btn msg-file-preview-remove" onclick="removePendingFile(${index})" title="Quitar"><i class="ph ph-x"></i></button>
            </div>
        `;
    });
    previewContainer.innerHTML = html;
    previewContainer.style.display = 'flex';
}

// Toast logic
function showToast(message) {
    let toast = document.getElementById('msgToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'msgToast';
        toast.className = 'msg-toast';
        document.body.appendChild(toast);
    }
    toast.innerHTML = `<i class="ph ph-check-circle" style="color: #22c55e; font-size:1.2rem;"></i> ${message}`;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Direct Message logic
function openDirectMessageModal() {
    const modal = document.getElementById('msgDirectMessageModal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('active');
        document.getElementById('dmSearchInput').value = '';
        document.getElementById('dmSearchInput').focus();
        document.getElementById('dmUsersList').innerHTML = '<div style="text-align:center; padding:1rem; color:var(--msg-text-muted); font-size:0.9rem;">Escribe para buscar o presiona buscar para ver todos.</div>';
    }, 10);
}

function closeDirectMessageModal() {
    const modal = document.getElementById('msgDirectMessageModal');
    modal.classList.remove('active');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function searchSystemUsers() {
    const q = document.getElementById('dmSearchInput').value;
    fetch('modules/mensajes/ajax.php?action=search_users&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('dmUsersList');
            if (!data.users || data.users.length === 0) {
                list.innerHTML = '<div style="text-align:center; padding:1rem; color:var(--msg-text-muted); font-size:0.9rem;">No se encontraron usuarios</div>';
                return;
            }
            let html = '';
            data.users.forEach(u => {
                let initial = u.name ? u.name.charAt(0).toUpperCase() : '#';
                let avatarStyle = u.avatar ? `background-image:url('${u.avatar}'); background-size:cover; background-position:center; color:transparent;` : '';
                html += `
                <div class="msg-member-item" style="cursor:pointer; padding: 0.75rem; border-radius:12px; transition:0.2s; align-items:center;" onclick="startDirectChat(${u.id}, '${(u.name||'').replace(/'/g, "\\'")}')" onmouseover="this.style.background='var(--msg-bg)'" onmouseout="this.style.background='transparent'">
                    <div class="msg-member-avatar" style="${avatarStyle}">${initial}</div>
                    <div style="flex:1;">
                        <div class="msg-member-name" style="font-size:0.9rem;">${u.name}</div>
                        <div style="font-size:0.75rem; color:var(--msg-text-muted);">${u.email || ''}</div>
                    </div>
                    <i class="ph ph-chat-text" style="color:var(--msg-primary); font-size:1.25rem;"></i>
                </div>`;
            });
            list.innerHTML = html;
        });
}

function startDirectChat(userId, userName) {
    const formData = new FormData();
    formData.append('target_id', userId);
    
    fetch('modules/mensajes/ajax.php?action=start_direct_chat', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeDirectMessageModal();
            loadChats(); // reload sidebar
            openChat(data.chat_id, userName, null); // Open the chat immediately
        } else {
            showToast('Error: ' + data.error);
        }
    });
}
// Audio Recording Logic
let mediaRecorder;
let audioChunks = [];
let recordingInterval;
let recordingSeconds = 0;
let shouldSendAudio = false;

async function toggleRecording() {
    const isRecording = mediaRecorder && mediaRecorder.state === 'recording';
    
    if (isRecording) {
        // Stop and send (toggle works as stop/send)
        shouldSendAudio = true;
        stopRecording();
    } else {
        // Start recording
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            startRecording(stream);
        } catch (err) {
            console.error('Error accessing microphone', err);
            showToast('Permiso de micrófono denegado');
        }
    }
}

function startRecording(stream) {
    mediaRecorder = new MediaRecorder(stream);
    audioChunks = [];
    shouldSendAudio = false;
    
    mediaRecorder.ondataavailable = e => {
        if (e.data.size > 0) audioChunks.push(e.data);
    };
    
    mediaRecorder.onstop = () => {
        if (shouldSendAudio && audioChunks.length > 0) {
            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            sendAudioMessage(audioBlob);
        }
        audioChunks = [];
        mediaRecorder.stream.getTracks().forEach(t => t.stop());
    };
    
    mediaRecorder.start();
    
    // UI updates
    document.getElementById('msgInput').style.display = 'none';
    const icon = document.getElementById('actionBtnIcon');
    if(icon) icon.className = 'ph-fill ph-paper-plane-tilt';
    document.getElementById('msgBtnAction').style.backgroundColor = '#ef4444';
    document.getElementById('msgRecordingUI').style.display = 'flex';
    
    recordingSeconds = 0;
    document.getElementById('msgRecordingTime').innerText = '00:00';
    recordingInterval = setInterval(() => {
        recordingSeconds++;
        const m = Math.floor(recordingSeconds / 60).toString().padStart(2, '0');
        const s = (recordingSeconds % 60).toString().padStart(2, '0');
        document.getElementById('msgRecordingTime').innerText = `${m}:${s}`;
    }, 1000);
}

function cancelRecording() {
    shouldSendAudio = false;
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        stopRecording();
    }
}

function stopRecording() {
    clearInterval(recordingInterval);
    
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
    
    // Restore UI
    document.getElementById('msgInput').style.display = 'block';
    const icon = document.getElementById('actionBtnIcon');
    if(icon) icon.className = 'ph-fill ph-microphone';
    document.getElementById('msgBtnAction').style.backgroundColor = '';
    document.getElementById('msgRecordingUI').style.display = 'none';
}

function sendAudioMessage(blob) {
    if (!currentChatId) return;
    
    const formData = new FormData();
    formData.append('chat_id', currentChatId);
    formData.append('file', blob, 'audio_' + Date.now() + '.webm');
    
    // Also include reply context if any
    const replyToId = document.getElementById('msgReplyToId').value;
    if (replyToId) {
        formData.append('reply_to_id', replyToId);
        cancelReply();
    }
    
    fetch('modules/mensajes/ajax.php?action=send_message', {
        method: 'POST',
        body: formData
    }).then(res => res.json()).then(data => {
        if (data.success) {
            pollMessages(false);
        } else {
            showToast('Error al enviar audio');
        }
    }).catch(err => {
        console.error(err);
        showToast('Error de conexión');
    });
}

// GIF Logic
function toggleGifMenu() {
    const menu = document.getElementById('msgGifMenu');
    menu.classList.toggle('active');
    if (menu.classList.contains('active')) {
        document.getElementById('msgGifSearchInput').focus();
        if (document.getElementById('msgGifResults').children.length <= 1) {
            searchGifs(true);
        }
    }
}

let gifTimeout;
function searchGifs(trending = false) {
    clearTimeout(gifTimeout);
    gifTimeout = setTimeout(() => {
        const query = document.getElementById('msgGifSearchInput').value.trim();
        const resultsEl = document.getElementById('msgGifResults');
        
        if (!trending && !query) {
            resultsEl.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:var(--msg-text-muted); font-size:12px; padding:10px;">Busca un GIF...</div>';
            return;
        }
        
        resultsEl.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:var(--msg-text-muted); font-size:12px; padding:10px;">Cargando...</div>';
        
        const apiKey = 'LIVDSRZULELA';
        const url = trending 
            ? `https://g.tenor.com/v1/trending?key=${apiKey}&limit=12` 
            : `https://g.tenor.com/v1/search?q=${encodeURIComponent(query)}&key=${apiKey}&limit=12`;
            
        fetch(url)
            .then(res => res.json())
            .then(data => {
                resultsEl.innerHTML = '';
                if (data.results && data.results.length > 0) {
                    data.results.forEach(gif => {
                        const imgUrl = gif.media[0].tinygif.url;
                        const fullUrl = gif.media[0].gif.url;
                        resultsEl.innerHTML += `
                            <img src="${imgUrl}" style="width:100%; height:80px; object-fit:cover; border-radius:4px; cursor:pointer;" onclick="sendGif('${fullUrl}')">
                        `;
                    });
                } else {
                    resultsEl.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:var(--msg-text-muted); font-size:12px; padding:10px;">No se encontraron resultados</div>';
                }
            })
            .catch(() => {
                resultsEl.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#ef4444; font-size:12px; padding:10px;">Error al cargar GIFs</div>';
            });
    }, 500);
}

function sendGif(url) {
    document.getElementById('msgGifMenu').classList.remove('active');
    
    if (!currentChatId) return;
    
    const formData = new FormData();
    formData.append('chat_id', currentChatId);
    formData.append('gif_url', url);
    
    const replyToId = document.getElementById('msgReplyToId').value;
    if (replyToId) {
        formData.append('reply_to_id', replyToId);
        cancelReply();
    }
    
    fetch('modules/mensajes/ajax.php?action=send_message', {
        method: 'POST',
        body: formData
    }).then(res => res.json()).then(data => {
        if (data.success) {
            pollMessages(false);
        } else {
            showToast('Error al enviar GIF');
        }
    }).catch(err => {
        console.error(err);
        showToast('Error de conexión');
    });
}


function handleActionBtn() {
    const isRecording = mediaRecorder && mediaRecorder.state === 'recording';
    if (isRecording) {
        toggleRecording();
        return;
    }
    
    const input = document.getElementById('msgInput').value.trim();
    if (input.length > 0) {
        sendMessage();
    } else {
        toggleRecording();
    }
}

function handleInputState() {
    const inputEl = document.getElementById('msgInput');
    let input = inputEl.value;
    
    // Quick Replies
    const shortcuts = {
        '/listo ': '¡Listo! ✅ ',
        '/ok ': 'De acuerdo 👍 ',
        '/gracias ': '¡Muchas gracias! 🙌 '
    };
    for (let k in shortcuts) {
        if (input.includes(k)) {
            inputEl.value = input.replace(k, shortcuts[k]);
            input = inputEl.value;
        }
    }

    // Slash Commands Menu
    const cmdMenu = document.getElementById('msgCommandMenu');
    if (cmdMenu) {
        if (input.startsWith('/')) {
            const cmdText = input.substring(1).toLowerCase();
            const commands = [
                { cmd: 'limpiar', desc: 'Limpiar mensajes locales', icon: 'ph-trash' },
                { cmd: 'ping', desc: 'Prueba de conexión', icon: 'ph-plugs' },
                { cmd: 'tema', desc: 'Alternar tema oscuro/claro', icon: 'ph-moon' },
                { cmd: 'ayuda', desc: 'Mostrar ayuda', icon: 'ph-question' }
            ];
            
            const filtered = commands.filter(c => c.cmd.startsWith(cmdText));
            if (filtered.length > 0 && input !== '/limpiar ' && input !== '/ping ' && input !== '/tema ' && input !== '/ayuda ') {
                let html = '';
                filtered.forEach(c => {
                    html += `<div class="msg-cmd-item" onclick="executeSlashCommand('${c.cmd}')" style="display:flex; align-items:center; gap:10px; padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--msg-border);">
                        <i class="ph ${c.icon}" style="font-size:16px; color:var(--msg-primary);"></i>
                        <div>
                            <div style="font-weight:bold; font-size:13px; color:var(--msg-text-main);">/${c.cmd}</div>
                            <div style="font-size:11px; color:var(--msg-text-muted);">${c.desc}</div>
                        </div>
                    </div>`;
                });
                cmdMenu.innerHTML = html;
                cmdMenu.style.display = 'block';
            } else {
                cmdMenu.style.display = 'none';
            }
        } else {
            cmdMenu.style.display = 'none';
        }
    }
    
    // Mentions
    const lastWord = input.split(' ').pop();
    if (lastWord.startsWith('@') && lastWord.length >= 1) {
        showMentionsMenu(lastWord.substring(1));
    } else {
        hideMentionsMenu();
    }

    const actionBtnIcon = document.getElementById('actionBtnIcon');
    if (actionBtnIcon) {
        if (input.trim().length > 0) {
            actionBtnIcon.className = 'ph-fill ph-paper-plane-right';
            document.getElementById('msgBtnAction').style.backgroundColor = '';
            
            // Send typing event
            if (!window.typingTimeout) {
                fetch('modules/mensajes/ajax.php?action=typing', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: `chat_id=${currentChatId}`
                });
            }
            clearTimeout(window.typingTimeout);
            window.typingTimeout = setTimeout(() => { window.typingTimeout = null; }, 2500);
            
        } else {
            actionBtnIcon.className = 'ph-fill ph-microphone';
            document.getElementById('msgBtnAction').style.backgroundColor = '';
        }
    }
    
    // Auto-expand textarea
    inputEl.style.height = 'auto';
    inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + 'px';
    
    // Markdown Preview
    const previewEl = document.getElementById('msgMarkdownPreview');
    if (previewEl) {
        if (input.trim().length > 0 && (input.includes('*') || input.includes('_') || input.includes('~') || input.includes('`'))) {
            previewEl.style.display = 'block';
            previewEl.innerHTML = typeof marked !== 'undefined' ? DOMPurify.sanitize(marked.parse(input)) : input;
        } else {
            previewEl.style.display = 'none';
            previewEl.innerHTML = '';
        }
    }
}

function executeSlashCommand(cmd) {
    const inputEl = document.getElementById('msgInput');
    const cmdMenu = document.getElementById('msgCommandMenu');
    
    if (cmdMenu) cmdMenu.style.display = 'none';
    
    if (cmd === 'limpiar') {
        inputEl.value = '';
        document.getElementById('msgArea').innerHTML = '<div style="text-align:center; padding: 2rem; color: var(--msg-text-muted); font-size: 13px;">Chat limpiado visualmente.</div>';
    } else if (cmd === 'ping') {
        inputEl.value = '¡Pong! 🏓';
    } else if (cmd === 'tema') {
        inputEl.value = '';
        const themeToggleBtn = document.querySelector('.theme-toggle-btn');
        if (themeToggleBtn) themeToggleBtn.click();
    } else if (cmd === 'ayuda') {
        inputEl.value = '';
        showToast('Comandos: /limpiar, /ping, /tema, /ayuda');
    }
    
    inputEl.focus();
    handleInputState();
}

function showMentionsMenu(query) {
    let menu = document.getElementById('msgMentionsMenu');
    if (!menu) {
        menu = document.createElement('div');
        menu.id = 'msgMentionsMenu';
        menu.style.cssText = 'position:absolute; bottom:100%; left:20px; background:var(--msg-surface); border:1px solid var(--msg-border); border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:100; max-height:200px; overflow-y:auto; min-width:200px; display:none; margin-bottom:10px;';
        document.getElementById('msgInputWrapper').appendChild(menu);
    }
    
    // Get members from current chat
    const membersList = document.getElementById('msgMembersList');
    if (!membersList) return;
    
    const members = Array.from(membersList.querySelectorAll('.msg-member-name')).map(el => el.innerText);
    const filtered = members.filter(m => m.toLowerCase().includes(query.toLowerCase()));
    
    if (filtered.length > 0) {
        let html = '';
        filtered.forEach(m => {
            html += `<div class="msg-mention-item" onclick="insertMention('${m}')" style="padding:8px 12px; cursor:pointer; font-size:13px; color:var(--msg-text-main); border-bottom:1px solid var(--msg-border);">${m}</div>`;
        });
        menu.innerHTML = html;
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
}

function hideMentionsMenu() {
    const menu = document.getElementById('msgMentionsMenu');
    if (menu) menu.style.display = 'none';
}

function insertMention(name) {
    const inputEl = document.getElementById('msgInput');
    let words = inputEl.value.split(' ');
    words.pop(); // remove the @ query
    words.push('@' + name + ' ');
    inputEl.value = words.join(' ');
    hideMentionsMenu();
    inputEl.focus();
    handleInputState();
}

function openWhiteboardModal() {
    const modal = document.getElementById('msgPizarraModal');
    if (modal) modal.classList.add('show');
    
    // Load whiteboards
    fetch('ajax/ajax_whiteboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'list_user_whiteboards' })
    })
    .then(r => r.json())
    .then(res => {
        const select = document.getElementById('pizarraSelectInput');
        if (res.success && select) {
            select.innerHTML = '<option value="">Selecciona o busca...</option>';
            res.whiteboards.forEach(wb => {
                const opt = document.createElement('option');
                opt.value = wb.id;
                opt.textContent = wb.title;
                opt.setAttribute('data-title', wb.title);
                select.appendChild(opt);
            });
        }
    });
}

function closeWhiteboardModal() {
    const modal = document.getElementById('msgPizarraModal');
    if (modal) modal.classList.remove('show');
    document.getElementById('pizarraSelectInput').value = '';
    document.getElementById('pizarraNewInput').value = '';
}

function submitWhiteboardAttach() {
    if (!currentChatId) {
        Swal.fire('Error', 'Debes estar en un chat', 'error');
        return;
    }
    
    const selectEl = document.getElementById('pizarraSelectInput');
    const newTitleEl = document.getElementById('pizarraNewInput');
    
    const existingId = selectEl.value;
    const newTitle = newTitleEl.value.trim();
    
    if (!existingId && !newTitle) {
        Swal.fire('Atención', 'Selecciona una pizarra existente o ingresa el nombre para crear una nueva.', 'warning');
        return;
    }
    
    if (newTitle) {
        // Create new whiteboard then send
        fetch('ajax/ajax_whiteboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', title: newTitle, assigned: [] })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                sendWhiteboardMessage(res.id, newTitle);
                closeWhiteboardModal();
            } else {
                Swal.fire('Error', res.error || 'Error al crear la pizarra', 'error');
            }
        });
    } else {
        // Send existing
        const title = selectEl.options[selectEl.selectedIndex].getAttribute('data-title');
        sendWhiteboardMessage(existingId, title);
        closeWhiteboardModal();
    }
}

function sendWhiteboardMessage(whiteboardId, title) {
    const taskData = {
        whiteboard_id: whiteboardId,
        title: title
    };
    
    const fd = new FormData();
    fd.append('chat_id', currentChatId);
    fd.append('type', 'whiteboard');
    fd.append('task_data', JSON.stringify(taskData));
    
    fetch('modules/mensajes/ajax.php?action=send_message', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            scrollToBottomOnLoad = true;
            loadMessages();
        } else {
            Swal.fire('Error', data.error, 'error');
        }
    });
}

// Close popovers when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('#msgEmojiMenu') && !e.target.closest('.msg-icon-btn[title="Emoticonos"]')) {
        const emojiMenu = document.getElementById('msgEmojiMenu');
        if (emojiMenu) emojiMenu.classList.remove('active');
    }
    if (!e.target.closest('#msgGifMenu') && !e.target.closest('.msg-attach-item:has(.ph-gif)')) {
        const gifMenu = document.getElementById('msgGifMenu');
        if (gifMenu) gifMenu.classList.remove('active');
    }
});

// Setup emoji picker
document.addEventListener('DOMContentLoaded', () => {
    const picker = document.querySelector('emoji-picker');
    if (picker) {
        picker.addEventListener('emoji-click', event => {
            const input = document.getElementById('msgInput');
            if (input) {
                const cursorPosition = input.selectionStart;
                const textBefore = input.value.substring(0, cursorPosition);
                const textAfter  = input.value.substring(cursorPosition, input.value.length);
                input.value = textBefore + event.detail.unicode + textAfter;
                input.selectionStart = cursorPosition + event.detail.unicode.length;
                input.selectionEnd = cursorPosition + event.detail.unicode.length;
                input.focus();
                handleInputState();
            }
        });
    }
});

function closeAllPopovers() {
    const attachMenu = document.getElementById('msgAttachMenu');
    const emojiMenu = document.getElementById('msgEmojiMenu');
    const gifMenu = document.getElementById('msgGifMenu');
    
    if (attachMenu) attachMenu.classList.remove('active');
    if (emojiMenu) emojiMenu.classList.remove('active');
    if (gifMenu) gifMenu.classList.remove('active');
}

// removed double click

// Swipe to Reply Logic
document.addEventListener('DOMContentLoaded', () => {
    let swipeStart = { x: 0, y: 0 };
    let swipedElement = null;
    let isSwiping = false;

    const area = document.getElementById('msgArea');
    if (!area) return;

    area.addEventListener('pointerdown', (e) => {
        // Only allow primary pointer (left click or touch)
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        
        const wrap = e.target.closest('.msg-bubble-wrap');
        // Do not intercept clicks on interactive elements inside the bubble
        if (e.target.closest('button, a, input, [onclick], .msg-task-card, .msg-gallery-item')) {
            return;
        }
        
        if (wrap) {
            swipeStart = { x: e.clientX, y: e.clientY };
            swipedElement = wrap;
            isSwiping = true;
            wrap.style.transition = 'none';
        }
    });

    area.addEventListener('pointermove', (e) => {
        if (!isSwiping || !swipedElement) return;
        
        const diffX = e.clientX - swipeStart.x;
        const diffY = Math.abs(e.clientY - swipeStart.y);
        
        // Ensure horizontal swipe (allow some vertical wiggle)
        if (diffX > 10 && diffY < 40) {
            const moveX = Math.min(diffX, 60); // Cap at 60px
            swipedElement.style.transform = `translateX(${moveX}px)`;
        } else if (diffX < 0) {
            // Prevent left swipe
            swipedElement.style.transform = `translateX(0px)`;
        }
    });

    const endSwipe = (e) => {
        if (!isSwiping || !swipedElement) return;
        isSwiping = false;
        swipedElement.style.transition = 'transform 0.3s ease';
        
        // Trigger reply if swiped enough
        const transformMatch = swipedElement.style.transform.match(/translateX\((.*?)px\)/);
        if (transformMatch && parseFloat(transformMatch[1]) >= 45) {
            if (navigator.vibrate) navigator.vibrate(20);
            const msgId = swipedElement.dataset.id;
            const senderEl = swipedElement.querySelector('.msg-sender');
            const senderName = senderEl ? senderEl.innerText : (swipedElement.classList.contains('own') ? 'Tú' : 'Usuario');
            const bubbleEl = swipedElement.querySelector('.msg-bubble');
            const content = bubbleEl ? bubbleEl.innerText : 'Mensaje adjunto';
            setReplyTo(msgId, senderName, content);
        }
        
        swipedElement.style.transform = 'translateX(0px)';
        swipedElement = null;
    };

    area.addEventListener('pointerup', endSwipe);
    area.addEventListener('pointercancel', endSwipe);
});

// ============================
// CUSTOM AUDIO PLAYER LOGIC
// ============================
window.currentPlayingAudio = null;

function formatAudioTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
}

function toggleAudioPlayback(id) {
    const audio = document.getElementById('audio-element-' + id);
    const icon = document.getElementById('audio-icon-' + id);
    if (!audio) return;
    
    if (audio.paused) {
        if (window.currentPlayingAudio && window.currentPlayingAudio !== audio) {
            window.currentPlayingAudio.pause();
            const prevId = window.currentPlayingAudio.id.replace('audio-element-', '');
            const prevIcon = document.getElementById('audio-icon-' + prevId);
            if (prevIcon) prevIcon.className = 'ph-fill ph-play';
        }
        audio.play().catch(e => {
            console.error("Audio playback error: ", e);
        });
        icon.className = 'ph-fill ph-pause';
        window.currentPlayingAudio = audio;
    } else {
        audio.pause();
        icon.className = 'ph-fill ph-play';
    }
}

function updateAudioProgress(id) {
    const audio = document.getElementById('audio-element-' + id);
    const progress = document.getElementById('audio-progress-' + id);
    const timeDisplay = document.getElementById('audio-time-' + id);
    const waveActive = document.getElementById('audio-wave-active-' + id);
    if (!audio || !progress || !timeDisplay) return;
    
    if (!audio.isSeeking) {
        let duration = audio.duration;
        let percent = 0;
        if (isFinite(duration) && duration > 0) {
            percent = (audio.currentTime / duration) * 100;
        } else {
            // For WebM without metadata, we can't calculate percent easily.
            // Just update the time.
            percent = 0;
        }
        
        progress.value = percent;
        if (waveActive) waveActive.style.width = percent + '%';
        timeDisplay.innerText = formatAudioTime(audio.currentTime);
    }
}

function setAudioDuration(id) {
    const audio = document.getElementById('audio-element-' + id);
    const timeDisplay = document.getElementById('audio-time-' + id);
    if (!audio || !timeDisplay || !isFinite(audio.duration)) return;
    timeDisplay.innerText = formatAudioTime(audio.duration);
}

function audioEnded(id) {
    const icon = document.getElementById('audio-icon-' + id);
    if (icon) icon.className = 'ph-fill ph-play';
    const audio = document.getElementById('audio-element-' + id);
    if (audio) {
        audio.currentTime = 0;
    }
    const progress = document.getElementById('audio-progress-' + id);
    if (progress) progress.value = 0;
    const waveActive = document.getElementById('audio-wave-active-' + id);
    if (waveActive) waveActive.style.width = '0%';
    const timeDisplay = document.getElementById('audio-time-' + id);
    if (timeDisplay && audio && isFinite(audio.duration)) {
        timeDisplay.innerText = formatAudioTime(audio.duration);
    }
}

function seekAudio(id, val) {
    const audio = document.getElementById('audio-element-' + id);
    const timeDisplay = document.getElementById('audio-time-' + id);
    const waveActive = document.getElementById('audio-wave-active-' + id);
    if (!audio || !timeDisplay || !isFinite(audio.duration)) return;
    audio.isSeeking = true;
    const time = (val / 100) * audio.duration;
    if (waveActive) waveActive.style.width = val + '%';
    timeDisplay.innerText = formatAudioTime(time);
}

function seekAudioEnd(id) {
    const audio = document.getElementById('audio-element-' + id);
    const progress = document.getElementById('audio-progress-' + id);
    if (!audio || !progress || !isFinite(audio.duration)) return;
    audio.currentTime = (progress.value / 100) * audio.duration;
    audio.isSeeking = false;
}

// ============================
// MESSAGE SEARCH LOGIC
// ============================
let currentSearchTerm = '';
let currentSearchIndex = 0;
let searchResults = [];

function toggleSearch() {
    const container = document.getElementById('msgSearchContainer');
    if (container.style.display === 'none') {
        container.style.display = 'block';
        document.getElementById('msgSearchInput').focus();
    } else {
        container.style.display = 'none';
        clearSearch();
    }
}

function searchMessages() {
    const query = document.getElementById('msgSearchInput').value.toLowerCase().trim();
    if (!query) {
        clearSearch();
        return;
    }
    
    if (query !== currentSearchTerm) {
        currentSearchTerm = query;
        currentSearchIndex = 0;
        searchResults = [];
        
        // Find all matches in current DOM
        const bubbles = document.querySelectorAll('.msg-bubble-wrap');
        bubbles.forEach(wrap => {
            const bubble = wrap.querySelector('.msg-bubble');
            if (bubble && bubble.innerText.toLowerCase().includes(query)) {
                searchResults.push(wrap);
            }
        });
    } else {
        currentSearchIndex++;
        if (currentSearchIndex >= searchResults.length) {
            currentSearchIndex = 0;
        }
    }
    
    if (searchResults.length > 0) {
        const wrap = searchResults[currentSearchIndex];
        wrap.scrollIntoView({behavior: 'smooth', block: 'center'});
        
        // Highlight
        const bubble = wrap.querySelector('.msg-bubble');
        if (bubble) {
            const originalBg = bubble.style.backgroundColor;
            bubble.style.backgroundColor = 'var(--msg-primary)';
            bubble.style.color = 'white';
            setTimeout(() => {
                bubble.style.backgroundColor = originalBg;
                bubble.style.color = '';
            }, 1500);
        }
    } else {
        showToast('No se encontraron resultados');
    }
}

function clearSearch() {
    document.getElementById('msgSearchInput').value = '';
    currentSearchTerm = '';
    searchResults = [];
}

let filterChatsTimeout = null;
function filterChats() {
    clearTimeout(filterChatsTimeout);
    filterChatsTimeout = setTimeout(() => {
        const input = document.getElementById('chatSearchInput');
        if (!input) return;
        const filter = input.value.toLowerCase();
        const chatItems = document.querySelectorAll('.msg-chat-item');
        
        chatItems.forEach(item => {
            const titleEl = item.querySelector('.msg-chat-name') || item.querySelector('.msg-chat-title');
            if (titleEl) {
                const title = titleEl.innerText.toLowerCase();
                if (title.includes(filter)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            }
        });
    }, 300);
}

// ============================
// SETTINGS (Phase 1)
// ============================
function openSettingsModal() {
    let modal = document.getElementById('msgSettingsModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'msgSettingsModal';
        modal.className = 'msg-modal';
        modal.innerHTML = `
            <div class="msg-modal-content" style="max-width: 450px; border-radius:20px; overflow:hidden;">
                <div class="msg-modal-header" style="background:var(--msg-surface); padding:20px; border-bottom:1px solid var(--msg-border);">
                    <h3 style="font-size:16px; margin:0;">Configuración de Apariencia</h3>
                    <button class="msg-icon-btn" onclick="closeSettingsModal()"><i class="ph ph-x"></i></button>
                </div>
                <div class="msg-modal-body" style="display:flex; flex-direction:column; gap:24px; padding:20px;">
                    
                    <div>
                        <label style="display:block; margin-bottom:12px; font-weight:600; font-size:13px; color:var(--msg-text-secondary);">Color de Acento</label>
                        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                            <div class="color-swatch" style="background:#128C7E; width:36px; height:36px; border-radius:50%; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.1); border:2px solid transparent;" onclick="setThemeColor('#128C7E')"></div>
                            <div class="color-swatch" style="background:#0088cc; width:36px; height:36px; border-radius:50%; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.1); border:2px solid transparent;" onclick="setThemeColor('#0088cc')"></div>
                            <div class="color-swatch" style="background:#7b2cbf; width:36px; height:36px; border-radius:50%; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.1); border:2px solid transparent;" onclick="setThemeColor('#7b2cbf')"></div>
                            <div class="color-swatch" style="background:#e83f6f; width:36px; height:36px; border-radius:50%; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.1); border:2px solid transparent;" onclick="setThemeColor('#e83f6f')"></div>
                            <div class="color-swatch" style="background:#ff9f1c; width:36px; height:36px; border-radius:50%; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.1); border:2px solid transparent;" onclick="setThemeColor('#ff9f1c')"></div>
                            <div class="color-swatch" style="background:#2b2d42; width:36px; height:36px; border-radius:50%; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.1); border:2px solid transparent;" onclick="setThemeColor('#2b2d42')"></div>
                            
                            <!-- Custom Color Picker -->
                            <div style="position:relative; width:36px; height:36px; border-radius:50%; overflow:hidden; border:2px solid var(--msg-border); display:flex; align-items:center; justify-content:center; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                                <input type="color" id="customColorPicker" onchange="setThemeColor(this.value)" style="position:absolute; opacity:0; width:200%; height:200%; top:-50%; left:-50%; cursor:pointer;">
                                <i class="ph ph-palette" style="font-size:18px; color:var(--msg-text-secondary); pointer-events:none;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display:flex; gap:16px;">
                        <div style="flex:1;">
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:var(--msg-text-secondary);">Tamaño de Fuente</label>
                            <select id="settingFontSize" onchange="setFontSize(this.value)" class="msg-input" style="padding:10px; border-radius:10px; background:var(--msg-bg);">
                                <option value="14px">Pequeño</option>
                                <option value="15px">Normal</option>
                                <option value="17px">Grande</option>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:var(--msg-text-secondary);">Fondo del Chat</label>
                            <select id="settingBg" onchange="setChatBg(this.value)" class="msg-input" style="padding:10px; border-radius:10px; background:var(--msg-bg);">
                                <option value="none">Por Defecto</option>
                                <option value="whatsapp">Patrón clásico</option>
                                <option value="gradient1">Gradiente Suave</option>
                                <option value="gradient2">Gradiente Noche</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Set current values
    document.getElementById('settingFontSize').value = localStorage.getItem('msgFontSize') || '15px';
    document.getElementById('settingBg').value = localStorage.getItem('msgBgPattern') || 'none';
    
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('active');
    }, 10);
}

function closeSettingsModal() {
    const modal = document.getElementById('msgSettingsModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 250);
    }
}

function setThemeColor(hex) {
    localStorage.setItem('msgThemeColor', hex);
    applyUserSettings();
}

function setFontSize(size) {
    localStorage.setItem('msgFontSize', size);
    applyUserSettings();
}

function setChatBg(pattern) {
    localStorage.setItem('msgBgPattern', pattern);
    applyUserSettings();
}

function applyUserSettings() {
    const color = localStorage.getItem('msgThemeColor');
    const fontSize = localStorage.getItem('msgFontSize');
    const bgPattern = localStorage.getItem('msgBgPattern');
    
    if (color) {
        document.documentElement.style.setProperty('--msg-primary', color);
        // Calculate a light version for backgrounds
        const hex = color.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        document.documentElement.style.setProperty('--msg-primary-light', 'rgba(' + r + ', ' + g + ', ' + b + ', 0.1)');
    }
    
    if (fontSize) {
        document.documentElement.style.setProperty('--msg-font-size', fontSize);
    }
    
    if (bgPattern) {
        const chatArea = document.getElementById('msgArea');
        if (chatArea) {
            chatArea.className = 'msg-area'; // reset
            if (bgPattern === 'whatsapp') {
                chatArea.style.backgroundImage = 'url("https://web.whatsapp.com/img/bg-chat-tile-dark_a4be512e7195b6b733d9110b408f075d.png")';
                chatArea.style.backgroundSize = '400px';
                chatArea.style.backgroundRepeat = 'repeat';
                chatArea.style.backgroundColor = 'transparent';
            } else if (bgPattern === 'gradient1') {
                chatArea.style.backgroundImage = 'linear-gradient(135deg, var(--msg-primary-light) 0%, rgba(37, 99, 235, 0.08) 100%)';
            } else if (bgPattern === 'gradient2') {
                chatArea.style.backgroundImage = 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)';
            } else {
                chatArea.style.backgroundImage = 'none';
                chatArea.style.backgroundColor = 'var(--msg-surface)';
            }
        }
    }
}

// Call on load
document.addEventListener('DOMContentLoaded', applyUserSettings);

// ============================
// LIGHTBOX ZOOM & PAN LOGIC
// ============================
let lbScale = 1;
let lbPointX = 0;
let lbPointY = 0;
let lbPanning = false;
let lbStart = { x: 0, y: 0 };
let lbInitialDistance = null;
let lbInitialScale = 1;

function resetLightboxZoom() {
    lbScale = 1;
    lbPointX = 0;
    lbPointY = 0;
    lbPanning = false;
    applyLightboxTransform(false);
}

function applyLightboxTransform(smooth = false) {
    const img = document.querySelector('#msgLightboxBody img');
    if (img) {
        img.style.transform = `translate(${lbPointX}px, ${lbPointY}px) scale(${lbScale})`;
        img.style.transition = smooth ? 'transform 0.2s ease-out' : 'none';
        img.style.transformOrigin = 'center center';
        if (lbScale > 1) {
            img.style.cursor = lbPanning ? 'grabbing' : 'grab';
        } else {
            img.style.cursor = 'default';
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const lbBody = document.getElementById('msgLightboxBody');
    if (!lbBody) return;
    
    // Mouse Wheel Zoom (PC)
    lbBody.addEventListener('wheel', (e) => {
        if (e.target.tagName !== 'IMG') return;
        e.preventDefault();
        
        const xs = (e.clientX - lbPointX) / lbScale;
        const ys = (e.clientY - lbPointY) / lbScale;
        const delta = (e.wheelDelta ? e.wheelDelta : -e.deltaY);
        
        (delta > 0) ? (lbScale *= 1.2) : (lbScale /= 1.2);
        
        if (lbScale < 1) lbScale = 1;
        if (lbScale > 10) lbScale = 10;
        
        if (lbScale === 1) {
            lbPointX = 0;
            lbPointY = 0;
        } else {
            lbPointX = e.clientX - xs * lbScale;
            lbPointY = e.clientY - ys * lbScale;
        }
        applyLightboxTransform(true);
    }, {passive: false});

    // Mouse Drag (PC)
    lbBody.addEventListener('mousedown', (e) => {
        if (e.target.tagName !== 'IMG' || lbScale === 1) return;
        e.preventDefault();
        lbStart = { x: e.clientX - lbPointX, y: e.clientY - lbPointY };
        lbPanning = true;
        applyLightboxTransform(false);
    });
    
    window.addEventListener('mouseup', () => {
        if (lbPanning) {
            lbPanning = false;
            applyLightboxTransform(false);
        }
    });
    
    window.addEventListener('mousemove', (e) => {
        if (!lbPanning || lbScale === 1) return;
        e.preventDefault();
        lbPointX = e.clientX - lbStart.x;
        lbPointY = e.clientY - lbStart.y;
        applyLightboxTransform(false);
    });

    // Touch Pinch & Pan (Mobile)
    lbBody.addEventListener('touchstart', (e) => {
        if (e.target.tagName !== 'IMG') return;
        if (e.touches.length === 2) {
            lbPanning = false;
            lbInitialDistance = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
            );
            lbInitialScale = lbScale;
        } else if (e.touches.length === 1 && lbScale > 1) {
            lbStart = { x: e.touches[0].clientX - lbPointX, y: e.touches[0].clientY - lbPointY };
            lbPanning = true;
        }
    });
    
    lbBody.addEventListener('touchmove', (e) => {
        if (e.target.tagName !== 'IMG') return;
        
        if (e.touches.length === 2) {
            e.preventDefault();
            const currentDistance = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
            );
            lbScale = lbInitialScale * (currentDistance / lbInitialDistance);
            if (lbScale < 1) lbScale = 1;
            if (lbScale > 10) lbScale = 10;
            if (lbScale === 1) {
                lbPointX = 0; lbPointY = 0;
            }
            applyLightboxTransform(false);
        } else if (e.touches.length === 1 && lbPanning && lbScale > 1) {
            e.preventDefault(); // Only prevent default if we are zoomed and panning
            lbPointX = e.touches[0].clientX - lbStart.x;
            lbPointY = e.touches[0].clientY - lbStart.y;
            applyLightboxTransform(false);
        }
    }, {passive: false});
    
    lbBody.addEventListener('touchend', (e) => {
        lbPanning = false;
        lbInitialDistance = null;
        applyLightboxTransform(false);
    });
});

// ============================
// EFFECTS (Phase 1)
// ============================
function fireConfetti() {
    if (typeof confetti === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js';
        script.onload = () => doConfetti();
        document.body.appendChild(script);
    } else {
        doConfetti();
    }
}

function doConfetti() {
    var duration = 3 * 1000;
    var end = Date.now() + duration;
    
    (function frame() {
        confetti({
            particleCount: 5,
            angle: 60,
            spread: 55,
            origin: { x: 0 },
            colors: ['#26ccff', '#a25afd', '#ff5e7e', '#88ff5a', '#fcff42', '#ffa62d', '#ff36ff']
        });
        confetti({
            particleCount: 5,
            angle: 120,
            spread: 55,
            origin: { x: 1 },
            colors: ['#26ccff', '#a25afd', '#ff5e7e', '#88ff5a', '#fcff42', '#ffa62d', '#ff36ff']
        });
    
        if (Date.now() < end) {
            requestAnimationFrame(frame);
        }
    }());
}

let currentEditingTaskId = null;

// Task Widget Logic
function openTaskModal() {
    currentEditingTaskId = null;
    const modal = document.getElementById('msgTaskModal');
    
    // Reset modal
    document.getElementById('taskTitleInput').value = '';
    document.getElementById('taskSubtitleInput').value = '';
    document.getElementById('taskDueDateInput').value = '';
    document.getElementById('taskPriorityInput').value = 'medium';
    document.getElementById('msgTaskSubtasksContainer').innerHTML = '';
    addSubtaskRow(); // Add one default empty row

    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('active');
    }, 10);
}

function openEditTaskModal(messageId, title, description, deadline, priority, subtasksB64) {
    currentEditingTaskId = messageId;
    const modal = document.getElementById('msgTaskModal');
    
    // Fill fields
    document.getElementById('taskTitleInput').value = title || '';
    document.getElementById('taskSubtitleInput').value = description || '';
    document.getElementById('taskDueDateInput').value = deadline || '';
    document.getElementById('taskPriorityInput').value = priority || 'medium';
    
    const container = document.getElementById('msgTaskSubtasksContainer');
    container.innerHTML = '';
    
    try {
        const subtasksStr = decodeURIComponent(atob(subtasksB64));
        const subtasks = JSON.parse(subtasksStr);
        if (subtasks && subtasks.length > 0) {
            subtasks.forEach(st => {
                const row = document.createElement('div');
                row.className = 'subtask-row';
                row.style.cssText = 'display:flex; align-items:center; gap:8px; margin-bottom:8px;';
                row.dataset.completed = st.completed ? "true" : "false";

                row.innerHTML = `
                    <div style="flex-shrink:0; color:var(--msg-text-muted);"><i class="ph ph-dots-six-vertical"></i></div>
                    <input type="text" class="msg-input subtask-input" value="${st.text.replace(/"/g, '&quot;')}" placeholder="Descripción de subtarea" style="flex:1;">
                    <button onclick="this.parentElement.remove()" style="background:none; border:none; color:var(--msg-text-muted); cursor:pointer;"><i class="ph ph-trash"></i></button>
                `;
                container.appendChild(row);
            });
        } else {
            addSubtaskRow();
        }
    } catch(e) {
        addSubtaskRow();
    }
    
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('active');
    }, 10);
}

function closeTaskModal() {
    const modal = document.getElementById('msgTaskModal');
    modal.classList.remove('active');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function addSubtaskRow(val = '') {
    const container = document.getElementById('msgTaskSubtasksContainer');
    const rowId = 'st-' + Date.now() + Math.random().toString(36).substr(2, 5);
    const row = document.createElement('div');
    row.id = rowId;
    row.className = 'subtask-row';
    row.dataset.completed = "false";
    row.style.cssText = 'display:flex; align-items:center; gap:8px; margin-bottom:8px;';
    row.innerHTML = `
        <div style="flex-shrink:0; color:var(--msg-text-muted);"><i class="ph ph-dots-six-vertical"></i></div>
        <input type="text" class="subtask-input form-control msg-input" placeholder="Describir subtarea..." value="${val}" style="flex:1;" autocomplete="new-password" data-lpignore="true" data-1p-ignore>
        <button class="msg-icon-btn" onclick="document.getElementById('${rowId}').remove()" style="color:#ef4444; background:none; border:none; cursor:pointer;"><i class="ph ph-trash"></i></button>
    `;
    container.appendChild(row);
}

function submitTask() {
    const title = document.getElementById('taskTitleInput').value.trim();
    const description = document.getElementById('taskSubtitleInput').value.trim();
    const deadline = document.getElementById('taskDueDateInput').value;
    const priority = document.getElementById('taskPriorityInput').value;
    
    if (!title) {
        alert("El título de la tarea es obligatorio.");
        return;
    }

    const subtasks = [];
    document.querySelectorAll('#msgTaskSubtasksContainer .subtask-row').forEach((row, index) => {
        const input = row.querySelector('.subtask-input');
        const text = input ? input.value.trim() : '';
        const isCompleted = row.dataset.completed === "true";
        if (text) {
            subtasks.push({
                id: index + 1,
                text: text,
                completed: isCompleted
            });
        }
    });
    
    const taskData = {
        title: title,
        description: description,
        deadline: deadline,
        priority: priority,
        status: 'in_progress',
        subtasks: subtasks
    };
    
    const formData = new FormData();
    formData.append('chat_id', currentChatId);
    formData.append('type', 'task');
    formData.append('task_data', JSON.stringify(taskData));
    formData.append('content', `📝 Tarea: ${title}`); // Fallback for last_message preview
    
    const replyToId = document.getElementById('msgReplyToId') ? document.getElementById('msgReplyToId').value : null;
    if (replyToId && !currentEditingTaskId) {
        formData.append('reply_to_id', replyToId);
        if (typeof cancelReply === 'function') cancelReply();
    }
    
    if (currentEditingTaskId) {
        formData.append('message_id', currentEditingTaskId);
        fetch('modules/mensajes/ajax.php?action=edit_message', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeTaskModal();
                pollMessages(false);
            } else {
                alert('Error al editar la tarea: ' + (data.error || 'Desconocido'));
            }
        });
    } else {
        fetch('modules/mensajes/ajax.php?action=send_message', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeTaskModal();
                pollMessages(false);
                setTimeout(scrollToBottom, 100);
            } else {
                alert('Error al enviar la tarea: ' + (data.error || 'Desconocido'));
            }
        });
    }
}

function generateTaskCardHtml(msg) {
    let taskData = null;
    try {
        taskData = JSON.parse(msg.task_data || '{}');
    } catch(e) {}
    
    if (!taskData) return '';

    const today = new Date();
    today.setHours(0,0,0,0);
    const dueDate = taskData.deadline ? new Date(taskData.deadline + 'T00:00:00') : null;
    const isCompleted = taskData.status === 'completed';
    
    let statusColor = '#f59e0b'; // Yellow for In Progress
    let statusText = 'En Progreso';
    
    if (isCompleted) {
        statusColor = '#10b981'; // Green
        statusText = 'Completada';
    } else if (dueDate) {
        const diffTime = dueDate.getTime() - today.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays < 0) {
            statusColor = '#ef4444'; // Red
            statusText = `Vencido`;
        } else if (diffDays <= 1) {
            statusColor = '#f97316'; // Orange
            statusText = 'Por vencer';
        }
    }

    const subtasks = taskData.subtasks || [];
    const completedCount = subtasks.filter(st => st.completed).length;
    const totalCount = subtasks.length;
    
    let progressHtml = '';
    let subtasksHtml = '';
    const uniqueAccId = 'task-acc-' + msg.id;

    if (totalCount > 0) {
        let progressPercentage = Math.round((completedCount / totalCount) * 100);
        progressHtml = `
            <div style="margin-top:12px; margin-bottom:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <span style="font-size:12px; font-weight:600; color:var(--msg-text-muted);">Progreso</span>
                    <span style="font-size:12px; font-weight:600; color:var(--msg-primary);">${progressPercentage}%</span>
                </div>
                <div style="height:6px; background:var(--msg-border); border-radius:3px; overflow:hidden;">
                    <div style="height:100%; width:${progressPercentage}%; background:var(--msg-primary); transition:width 0.4s cubic-bezier(0.4, 0, 0.2, 1); border-radius:3px;"></div>
                </div>
            </div>
        `;
        
        let rowsHtml = subtasks.map(st => {
            let textStyle = st.completed ? 'text-decoration: line-through; color: var(--msg-text-muted);' : 'color: var(--msg-text-main);';
            let iconHtml = st.completed 
                ? '<div style="width:20px; height:20px; border-radius:4px; background:#10b981; color:white; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; transition:all 0.2s;"><i class="ph ph-check"></i></div>'
                : '<div style="width:20px; height:20px; border-radius:4px; border:2px solid var(--msg-border); flex-shrink:0; cursor:pointer; transition:all 0.2s;"></div>';
            
            return `
                <div style="display:flex; align-items:flex-start; gap:10px; padding:10px; border:1px solid var(--msg-border); border-radius:8px; margin-bottom:8px; cursor:pointer; transition:all 0.2s;" onclick="toggleTaskItemStatus(${msg.id}, ${st.id}, ${!st.completed})">
                    ${iconHtml}
                    <div style="flex:1; font-size:13px; transition:all 0.2s; ${textStyle}">${st.text}</div>
                </div>
            `;
        }).join('');
        
        subtasksHtml = `
            <div style="border-top:1px solid var(--msg-border); margin-top:12px; padding-top:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <div style="font-weight:600; font-size:13px;">Subtareas <span style="font-weight:normal; color:var(--msg-text-muted);">(${completedCount}/${totalCount})</span></div>
                    <div style="cursor:pointer; font-size:12px; color:var(--msg-primary); font-weight:600;" onclick="const e=document.getElementById('${uniqueAccId}'); e.style.display=e.style.display==='none'?'block':'none'; this.innerText=e.style.display==='none'?'Ver subtareas':'Ocultar subtareas';">Ocultar subtareas</div>
                </div>
                <div id="${uniqueAccId}">
                    ${rowsHtml}
                </div>
            </div>
        `;
    }
    
    let priorityColor = 'var(--msg-border)';
    let priorityText = '';
    if (taskData.priority === 'high') { priorityColor = '#ef4444'; priorityText = 'Prioridad Alta'; }
    else if (taskData.priority === 'medium') { priorityColor = '#f59e0b'; priorityText = 'Prioridad Media'; }
    else if (taskData.priority === 'low') { priorityColor = '#10b981'; priorityText = 'Prioridad Baja'; }

    return `
        <div class="msg-task-card" style="display:flex; flex-direction:column; padding:16px; background:var(--msg-surface); border:1px solid var(--msg-border); border-top: 4px solid ${priorityColor}; border-radius:12px; min-width:300px; max-width:400px; margin-top:5px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition:all 0.3s ease;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                <div style="font-size:18px; font-weight:bold; color:var(--msg-text-main);">${taskData.title || 'Tarea'}</div>
                <div style="cursor:pointer; color:var(--msg-text-muted); padding:4px; border-radius:4px;" onclick="openEditTaskModal(${msg.id}, '${(taskData.title || '').replace(/'/g, "\\'")}', '${(taskData.description || '').replace(/'/g, "\\'")}', '${taskData.deadline || ''}', '${taskData.priority || ''}', '${btoa(encodeURIComponent(JSON.stringify(subtasks)))}')">
                    <i class="ph ph-pencil-simple" style="font-size:16px;"></i>
                </div>
            </div>
            ${taskData.description ? `<div style="font-size:13px; color:var(--msg-text-muted); margin-bottom:16px; line-height:1.4;">${taskData.description}</div>` : ''}
            
            <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:12px;">
                ${taskData.deadline ? `
                <div style="display:flex; align-items:center; gap:32px;">
                    <div style="color:var(--msg-text-muted); font-size:13px; width:60px;">Fecha</div>
                    <div style="font-size:13px; font-weight:500; display:flex; align-items:center; gap:6px;">
                        <i class="ph ph-calendar-blank"></i> ${taskData.deadline}
                    </div>
                </div>` : ''}
                
                ${priorityText ? `
                <div style="display:flex; align-items:center; gap:32px;">
                    <div style="color:var(--msg-text-muted); font-size:13px; width:60px;">Prioridad</div>
                    <div style="font-size:11px; padding:4px 8px; border-radius:6px; background:${priorityColor}20; color:${priorityColor}; font-weight:bold;">
                        ${priorityText}
                    </div>
                </div>` : ''}
                
                <div style="display:flex; align-items:center; gap:32px;">
                    <div style="color:var(--msg-text-muted); font-size:13px; width:60px;">Estado</div>
                    <div style="font-size:11px; padding:4px 8px; border-radius:6px; background:${statusColor}20; color:${statusColor}; font-weight:bold; transition:all 0.2s;">
                        ${statusText}
                    </div>
                </div>
            </div>
            
            ${progressHtml}
            ${subtasksHtml}
        </div>
    `;
}

function openPendienteModal() {
    const modal = document.getElementById('msgPendienteModal');
    
    // Reset modal
    document.getElementById('pendienteTitleInput').value = '';
    document.getElementById('pendienteSubtitleInput').value = '';
    document.getElementById('pendienteDescInput').value = '';
    document.getElementById('pendienteStatusInput').value = 'pending';
    document.getElementById('pendienteDueDateInput').value = '';
    document.getElementById('pendientePriorityInput').value = 'medium';
    document.getElementById('pendienteTypeInput').value = 'digital';
    document.getElementById('pendienteSizeInput').value = '';
    document.getElementById('pendienteRefsInput').value = '';

    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('active');
    }, 10);
}

function closePendienteModal() {
    const modal = document.getElementById('msgPendienteModal');
    modal.classList.remove('active');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function submitPendiente() {
    const title = document.getElementById('pendienteTitleInput').value.trim();
    if (!title) {
        alert("El título es obligatorio.");
        return;
    }

    const taskData = {
        title: title,
        subtitle: document.getElementById('pendienteSubtitleInput').value.trim(),
        description: document.getElementById('pendienteDescInput').value.trim(),
        status: document.getElementById('pendienteStatusInput').value,
        deadline: document.getElementById('pendienteDueDateInput').value,
        priority: document.getElementById('pendientePriorityInput').value,
        design_type: document.getElementById('pendienteTypeInput').value,
        design_size: document.getElementById('pendienteSizeInput').value
    };
    
    const formData = new FormData();
    formData.append('chat_id', currentChatId);
    formData.append('type', 'pendiente');
    formData.append('task_data', JSON.stringify(taskData));
    formData.append('content', `📌 Pendiente: ${title}`);
    
    const fileInput = document.getElementById('pendienteRefsInput');
    if (fileInput && fileInput.files.length > 0) {
        for(let i=0; i<fileInput.files.length; i++) {
            formData.append('references[]', fileInput.files[i]);
        }
    }
    
    const replyToId = document.getElementById('msgReplyToId') ? document.getElementById('msgReplyToId').value : null;
    if (replyToId) {
        formData.append('reply_to_id', replyToId);
        if (typeof cancelReply === 'function') cancelReply();
    }
    
    fetch('modules/mensajes/ajax.php?action=send_message', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closePendienteModal();
            pollMessages(false);
            setTimeout(scrollToBottom, 100);
        } else {
            alert('Error al crear pendiente: ' + (data.error || 'Desconocido'));
        }
    });
}

function generatePendienteCardHtml(msg) {
    let taskData = null;
    try {
        taskData = JSON.parse(msg.task_data || '{}');
    } catch(e) {}
    
    if (!taskData) return '';

    let priorityColor = 'var(--msg-border)';
    let priorityText = '';
    if (taskData.priority === 'high') { priorityColor = '#ef4444'; priorityText = 'Alta'; }
    else if (taskData.priority === 'medium') { priorityColor = '#f59e0b'; priorityText = 'Media'; }
    else if (taskData.priority === 'low') { priorityColor = '#10b981'; priorityText = 'Baja'; }

    let statusColor = '#f59e0b';
    let statusText = 'Pendiente';
    if (taskData.status === 'in_progress') { statusColor = '#3b82f6'; statusText = 'En progreso'; }
    else if (taskData.status === 'completed') { statusColor = '#10b981'; statusText = 'Completado'; }

    let typeText = taskData.design_type === 'impreso' ? 'Impreso' : 'Digital';

    let refsHtml = '';
    if (taskData.references && taskData.references.length > 0) {
        let itemsHtml = taskData.references.map(ref => {
            let isImage = ref.name.match(/\\.(jpg|jpeg|png|gif|webp)$/i);
            if (isImage) {
                return `<div style="width:50px; height:50px; border-radius:6px; background-image:url('${ref.url}'); background-size:cover; background-position:center; cursor:pointer; border:1px solid var(--msg-border);" onclick="openLightbox('image', '${ref.url}')" title="${ref.name}"></div>`;
            } else {
                return `<a href="${ref.url}" target="_blank" style="width:50px; height:50px; border-radius:6px; background:var(--msg-bg); display:flex; align-items:center; justify-content:center; border:1px solid var(--msg-border); color:var(--msg-primary); text-decoration:none;" title="${ref.name}"><i class="ph ph-file-text" style="font-size:24px;"></i></a>`;
            }
        }).join('');
        refsHtml = `
            <div style="margin-top:12px; border-top:1px solid var(--msg-border); padding-top:12px;">
                <div style="font-size:12px; font-weight:600; color:var(--msg-text-muted); margin-bottom:8px;">Referencias adjuntas</div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    ${itemsHtml}
                </div>
            </div>
        `;
    }

    return `
        <div class="msg-pendiente-card" style="display:flex; flex-direction:column; padding:16px; background:var(--msg-surface); border:1px solid var(--msg-border); border-left: 4px solid #8b5cf6; border-radius:12px; min-width:300px; max-width:400px; margin-top:5px; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:4px;">
                <div style="font-size:18px; font-weight:bold; color:var(--msg-text-main);">${taskData.title || 'Pendiente'}</div>
                <div style="font-size:11px; padding:4px 8px; border-radius:6px; background:${statusColor}20; color:${statusColor}; font-weight:bold;">${statusText}</div>
            </div>
            ${taskData.subtitle ? `<div style="font-size:14px; font-weight:500; color:var(--msg-text-main); margin-bottom:8px;">${taskData.subtitle}</div>` : ''}
            ${taskData.description ? `<div style="font-size:13px; color:var(--msg-text-muted); margin-bottom:12px; line-height:1.4;">${taskData.description}</div>` : ''}
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:12px; background:var(--msg-bg); padding:10px; border-radius:8px;">
                ${taskData.deadline ? `<div><div style="font-size:11px; color:var(--msg-text-muted);">Entrega</div><div style="font-size:13px; font-weight:500;">${taskData.deadline}</div></div>` : ''}
                <div><div style="font-size:11px; color:var(--msg-text-muted);">Prioridad</div><div style="font-size:13px; font-weight:500; color:${priorityColor};">${priorityText}</div></div>
                <div><div style="font-size:11px; color:var(--msg-text-muted);">Tipo</div><div style="font-size:13px; font-weight:500;">${typeText}</div></div>
                ${taskData.design_size ? `<div><div style="font-size:11px; color:var(--msg-text-muted);">Tamaño</div><div style="font-size:13px; font-weight:500;">${taskData.design_size}</div></div>` : ''}
            </div>
            
            ${refsHtml}
            
            ${taskData.status !== 'completed' ? `
            <div style="margin-top:12px; border-top:1px solid var(--msg-border); padding-top:12px; text-align:center;">
                <button style="width:100%; padding:8px; border-radius:8px; font-size:13px; font-weight:600; display:flex; align-items:center; justify-content:center; gap:6px; cursor:pointer; background:transparent; border:1px solid var(--msg-primary); color:var(--msg-primary); transition:all 0.2s;" onmouseover="this.style.background='var(--msg-primary)'; this.style.color='white';" onmouseout="this.style.background='transparent'; this.style.color='var(--msg-primary)';" onclick="markPendienteCompleted(${msg.id})">
                    <i class="ph ph-check-circle" style="font-size:18px;"></i> Marcar como Terminado
                </button>
            </div>
            ` : ''}
        </div>
    `;
}

function markPendienteCompleted(messageId) {
    if (!confirm('¿Estás seguro de marcar este pendiente como terminado?')) return;
    const formData = new FormData();
    formData.append('message_id', messageId);
    formData.append('mark_all', 'true');
    fetch('modules/mensajes/ajax.php?action=update_task_status', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(data => {
        if(data.success) {
            reloadMessageDOM(messageId);
        } else {
            alert('Error: ' + (data.error || 'Desconocido'));
        }
    });
}

function reloadMessageDOM(messageId) {
    fetch(`modules/mensajes/ajax.php?action=get_single_message&message_id=${messageId}&t=${Date.now()}`)
    .then(r => r.json())
    .then(data => {
        if (data.success && data.message) {
            const bubble = document.querySelector(`#msg-${messageId} .msg-bubble`);
            if (bubble) {
                const taskCard = bubble.querySelector('.msg-task-card');
                const pendienteCard = bubble.querySelector('.msg-pendiente-card');
                if (taskCard) {
                    const newHtml = generateTaskCardHtml(data.message);
                    const temp = document.createElement('div');
                    temp.innerHTML = newHtml;
                    const newTaskCard = temp.firstElementChild;
                    taskCard.replaceWith(newTaskCard);
                } else if (pendienteCard) {
                    const newHtml = generatePendienteCardHtml(data.message);
                    const temp = document.createElement('div');
                    temp.innerHTML = newHtml;
                    const newPendienteCard = temp.firstElementChild;
                    pendienteCard.replaceWith(newPendienteCard);
                }
            }
        }
    });
}

function toggleTaskItemStatus(messageId, subtaskId, isCompleted) {
    fetch('modules/mensajes/ajax.php?action=update_task_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message_id=${messageId}&subtask_id=${subtaskId}&is_completed=${isCompleted}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            reloadMessageDOM(messageId);
            if (data.all_completed) {
                if (!window.confetti) {
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js';
                    script.onload = () => confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });
                    document.head.appendChild(script);
                } else {
                    confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });
                }
            }
        } else {
            console.error('Error actualizando tarea:', data.error);
            alert(data.error || 'Error desconocido al actualizar la tarea.');
        }
    }).catch(err => {
        console.error('Fetch error:', err);
        alert('Error de conexión al actualizar la tarea.');
    });
}

function completeTaskMain(messageId) {
    fetch('modules/mensajes/ajax.php?action=update_task_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message_id=${messageId}&mark_all=true`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            reloadMessageDOM(messageId);
        } else {
            console.error('Error completando tarea:', data.error);
            alert(data.error || 'Error desconocido al completar la tarea.');
        }
    }).catch(err => {
        console.error('Fetch error:', err);
        alert('Error de conexión al completar la tarea.');
    });
}

function generateWhiteboardCardHtml(msg) {
    let metadata = {};
    try {
        if (typeof msg.task_data === 'string') {
            metadata = JSON.parse(msg.task_data);
        } else if (msg.task_data) {
            metadata = msg.task_data;
        }
    } catch (e) {
        console.error("Error parsing whiteboard metadata", e);
    }
    
    const whiteboardId = metadata.whiteboard_id || '';
    const title = metadata.title || 'Pizarra sin título';
    const link = `index.php?module=pizarras&action=view&id=${whiteboardId}`;
    
    return `
        <div class="msg-whiteboard-card" style="background:#ffffff; border:1px solid var(--msg-border); border-radius:12px; padding:16px; min-width:280px; box-shadow:0 4px 6px rgba(0,0,0,0.05);">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                <div style="width:40px; height:40px; border-radius:10px; background:linear-gradient(135deg, #10b981, #047857); display:flex; align-items:center; justify-content:center; color:#fff;">
                    <i class="ph ph-chalkboard" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div style="font-size:14px; font-weight:700; color:var(--msg-text-main);">${title}</div>
                    <div style="font-size:12px; color:var(--msg-text-muted);">Pizarra Colaborativa</div>
                </div>
            </div>
            
            <a href="${link}" class="msg-btn-whiteboard" style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:10px 0; background:var(--msg-surface); border:1px solid var(--msg-border); border-radius:8px; color:var(--msg-text-main); text-decoration:none; font-weight:600; transition:all 0.2s;">
                <span>Abrir Pizarra</span>
                <i class="ph ph-arrow-right"></i>
            </a>
        </div>
    `;
}



