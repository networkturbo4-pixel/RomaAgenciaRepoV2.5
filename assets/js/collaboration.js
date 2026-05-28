/**
 * Real-time collaboration using Pusher
 */

document.addEventListener('DOMContentLoaded', () => {
    // Check if Pusher is defined and we have user info
    if (typeof Pusher === 'undefined' || typeof currentUserId === 'undefined') return;

    // Use actual Pusher credentials
    const pusher = new Pusher('b31f38612d61b0285c78', {
        cluster: 'us2',
        authEndpoint: 'ajax_pusher_auth.php'
    });

    // Detect which module we're in based on URL
    const urlParams = new URLSearchParams(window.location.search);
    const module = urlParams.get('module');
    
    if (module !== 'design_tasks' && module !== 'calendar' && module !== 'month_board') return;

    // Subscribe to a presence channel for the current module
    // If we're inside a specific task/post modal, we could use a specific channel, 
    // but for now, we use a module-wide presence channel or a channel based on the project/month ID.
    let channelName = `presence-${module}`;
    if (urlParams.get('id')) {
        channelName += `-${urlParams.get('id')}`;
    }

    const channel = pusher.subscribe(channelName);
    
    const cursors = {}; // Store cursor elements by user ID

    // Create a container for cursors
    const cursorsContainer = document.createElement('div');
    cursorsContainer.id = 'collaboration-cursors';
    cursorsContainer.style.position = 'fixed';
    cursorsContainer.style.top = '0';
    cursorsContainer.style.left = '0';
    cursorsContainer.style.width = '100vw';
    cursorsContainer.style.height = '100vh';
    cursorsContainer.style.pointerEvents = 'none';
    cursorsContainer.style.zIndex = '9999';
    document.body.appendChild(cursorsContainer);

    // Create topbar avatars container
    const topbar = document.querySelector('.topbar-actions');
    let presenceContainer = document.getElementById('presence-avatars');
    if (topbar && !presenceContainer) {
        presenceContainer = document.createElement('div');
        presenceContainer.id = 'presence-avatars';
        presenceContainer.style.display = 'flex';
        presenceContainer.style.alignItems = 'center';
        presenceContainer.style.marginRight = '1rem';
        topbar.insertBefore(presenceContainer, topbar.firstChild);
    }

    channel.bind('pusher:subscription_succeeded', (members) => {
        updatePresenceAvatars(members);
    });

    channel.bind('pusher:member_added', (member) => {
        updatePresenceAvatars(channel.members);
        showToastNotification(`${member.info.name} se ha conectado`);
    });

    channel.bind('pusher:member_removed', (member) => {
        updatePresenceAvatars(channel.members);
        removeCursor(member.id);
    });

    // Handle incoming cursor movements
    channel.bind('client-cursor-move', (data) => {
        if (data.userId === currentUserId) return; // Ignore own movements
        updateCursor(data);
    });

    // Throttle cursor movement sending
    let lastSendTime = 0;
    const sendInterval = 50; // ms

    document.addEventListener('mousemove', (e) => {
        const now = Date.now();
        if (now - lastSendTime > sendInterval) {
            // Calculate relative position based on viewport
            const relX = e.clientX / window.innerWidth;
            const relY = e.clientY / window.innerHeight;
            
            // Need a known user name, let's try to get it from topbar
            const userNameSpan = document.querySelector('.user-details span:first-child');
            const userName = userNameSpan ? userNameSpan.textContent : 'Usuario';

            channel.trigger('client-cursor-move', {
                userId: currentUserId,
                userName: userName,
                x: relX,
                y: relY
            });
            lastSendTime = now;
        }
    });

    function updateCursor(data) {
        let cursorEl = cursors[data.userId];
        if (!cursorEl) {
            // Create new cursor
            cursorEl = document.createElement('div');
            cursorEl.className = 'collab-cursor';
            
            // Random color based on userId
            const colors = ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899'];
            const color = colors[data.userId % colors.length];

            cursorEl.innerHTML = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="${color}" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0px 2px 4px rgba(0,0,0,0.3));">
                    <path d="M5.5 3.21V20.8C5.5 21.6 6.38 22.09 7.06 21.68L11.43 19.04C11.66 18.9 11.94 18.86 12.2 18.92L17.76 20.31C18.53 20.5 19.26 19.78 19.07 19L14.61 7.21C14.34 6.51 13.59 6.2 12.98 6.55L9.61 8.5C9.38 8.63 9.1 8.67 8.84 8.6L6.59 8.04C5.97 7.89 5.5 8.35 5.5 8.95V3.21Z" stroke="white" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
                <div class="cursor-label" style="background:${color}; color:white; font-size:0.7rem; font-weight:600; padding:2px 6px; border-radius:4px; margin-top:-5px; margin-left:15px; white-space:nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">${data.userName}</div>
            `;
            cursorEl.style.position = 'absolute';
            cursorEl.style.transition = 'transform 0.1s linear';
            cursorsContainer.appendChild(cursorEl);
            cursors[data.userId] = cursorEl;
        }

        // Convert relative to absolute
        const absX = data.x * window.innerWidth;
        const absY = data.y * window.innerHeight;

        cursorEl.style.transform = `translate(${absX}px, ${absY}px)`;
    }

    function removeCursor(userId) {
        const cursorEl = cursors[userId];
        if (cursorEl) {
            cursorEl.remove();
            delete cursors[userId];
        }
    }

    function updatePresenceAvatars(members) {
        if (!presenceContainer) return;
        presenceContainer.innerHTML = '';
        
        let count = 0;
        members.each((member) => {
            if (member.id == currentUserId) return; // Skip self

            const avatar = document.createElement('div');
            avatar.title = member.info.name || 'Usuario';
            avatar.style.width = '32px';
            avatar.style.height = '32px';
            avatar.style.borderRadius = '50%';
            avatar.style.background = '#3b82f6';
            avatar.style.color = 'white';
            avatar.style.display = 'flex';
            avatar.style.alignItems = 'center';
            avatar.style.justifyContent = 'center';
            avatar.style.fontWeight = 'bold';
            avatar.style.fontSize = '0.85rem';
            avatar.style.marginLeft = '-8px'; // Overlap effect
            avatar.style.border = '2px solid white';
            avatar.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
            avatar.style.zIndex = 100 - count;

            if (member.info.avatar) {
                avatar.innerHTML = `<img src="${member.info.avatar}" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">`;
            } else {
                avatar.textContent = avatar.title.charAt(0).toUpperCase();
            }

            presenceContainer.appendChild(avatar);
            count++;
        });

        if (count > 0) {
            const separator = document.createElement('div');
            separator.style.width = '1px';
            separator.style.height = '24px';
            separator.style.background = 'var(--border-color)';
            separator.style.margin = '0 1rem';
            presenceContainer.appendChild(separator);
        }
    }

    function showToastNotification(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'bottom-start',
                icon: 'info',
                title: msg,
                showConfirmButton: false,
                timer: 3000
            });
        }
    }
});
