// assets/js/notifications.js
(function() {
    'use strict';

    let isPopoverOpen = false;
    let currentUnreadCount = 0;
    let notificationsCache = [];
    let isFetching = false;

    // Elementos del DOM
    let desktopBtn, mobileBtn, popover, badgeDesktop, badgeMobile, notifList, notifCountText, markAllBtn, pushBanner;

    function init() {
        desktopBtn     = document.getElementById('desktopNotifBtn');
        mobileBtn      = document.getElementById('mobileNotifBtn');
        popover        = document.getElementById('notifPopover');
        badgeDesktop   = document.getElementById('notifBadgeDesktop');
        badgeMobile    = document.getElementById('notifBadgeMobile');
        notifList      = document.getElementById('notifList');
        notifCountText = document.getElementById('notifCountText');
        markAllBtn     = document.getElementById('notifMarkAllBtn');
        pushBanner     = document.getElementById('notifPushBanner');

        if (!popover) return;

        // Listeners para botones de campana
        if (desktopBtn) {
            desktopBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                togglePopover('desktop');
            });
        }

        if (mobileBtn) {
            mobileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                togglePopover('mobile');
            });
        }

        // Marcar todas como leídas
        if (markAllBtn) {
            markAllBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                markAllAsRead();
            });
        }

        // Cerrar al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (isPopoverOpen && !popover.contains(e.target) && 
                (!desktopBtn || !desktopBtn.contains(e.target)) && 
                (!mobileBtn || !mobileBtn.contains(e.target))) {
                closePopover();
            }
        });

        // Cerrar con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isPopoverOpen) {
                closePopover();
            }
        });

        // Revisar estado de Web Push para mostrar el banner si aplica
        checkPushPermissionBanner();

        // Obtener conteo inicial
        fetchUnreadCount();

        // Conectar a Pusher en tiempo real
        initPusher();

        // Polling de respaldo cada 60 segundos
        setInterval(fetchUnreadCount, 60000);
    }

    function checkPushPermissionBanner() {
        if (!pushBanner) return;
        if ('Notification' in window && Notification.permission === 'default' && typeof window.subscribeToPush === 'function') {
            pushBanner.style.display = 'flex';
        } else {
            pushBanner.style.display = 'none';
        }
    }

    window.requestPushFromBanner = function() {
        if (typeof window.subscribeToPush === 'function') {
            window.subscribeToPush().then(() => {
                checkPushPermissionBanner();
            }).catch(() => {
                checkPushPermissionBanner();
            });
        }
    };

    function togglePopover(mode) {
        if (isPopoverOpen) {
            closePopover();
        } else {
            openPopover(mode);
        }
    }

    function openPopover(mode) {
        isPopoverOpen = true;
        popover.classList.add('active');

        if (mode === 'desktop') {
            popover.classList.add('desktop-mode');
        } else {
            popover.classList.remove('desktop-mode');
        }

        // Cargar lista fresca de notificaciones
        fetchNotifications();
    }

    function closePopover() {
        isPopoverOpen = false;
        popover.classList.remove('active');
    }

    function updateBadge(newCount, animate = false) {
        currentUnreadCount = Math.max(0, parseInt(newCount, 10) || 0);

        [badgeDesktop, badgeMobile].forEach(badge => {
            if (!badge) return;
            if (currentUnreadCount > 0) {
                badge.textContent = currentUnreadCount > 99 ? '99+' : currentUnreadCount;
                badge.style.display = 'inline-block';
                if (animate) {
                    badge.classList.remove('pulse');
                    void badge.offsetWidth; // trigger reflow
                    badge.classList.add('pulse');
                }
            } else {
                badge.style.display = 'none';
                badge.classList.remove('pulse');
            }
        });

        if (notifCountText) {
            notifCountText.textContent = currentUnreadCount > 0 ? `${currentUnreadCount} nuevas` : '0 nuevas';
        }
    }

    function fetchUnreadCount() {
        fetch('ajax/notifications.php?action=get_unread_count')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateBadge(data.count);
                }
            })
            .catch(err => console.debug('Notif count fetch error:', err));
    }

    function fetchNotifications() {
        if (isFetching || !notifList) return;
        isFetching = true;

        notifList.innerHTML = `
            <div style="padding: 2rem; text-align: center; color: var(--text-muted);">
                <i class="ph ph-spinner ph-spin" style="font-size: 1.8rem;"></i>
                <p style="margin-top: 0.5rem; font-size: 0.85rem;">Cargando notificaciones...</p>
            </div>
        `;

        fetch('ajax/notifications.php?action=get_notifications')
            .then(res => res.json())
            .then(data => {
                isFetching = false;
                if (data.success) {
                    notificationsCache = data.notifications || [];
                    updateBadge(data.unread_count);
                    renderNotifications(notificationsCache);
                } else {
                    notifList.innerHTML = `<div class="notif-empty"><p>${data.error || 'Error al cargar'}</p></div>`;
                }
            })
            .catch(err => {
                isFetching = false;
                notifList.innerHTML = `<div class="notif-empty"><p>No se pudieron cargar las notificaciones</p></div>`;
            });
    }

    function renderNotifications(items) {
        if (!notifList) return;

        if (!items || items.length === 0) {
            notifList.innerHTML = `
                <div class="notif-empty">
                    <i class="ph ph-bell-simple-slash"></i>
                    <p>No tienes notificaciones pendientes</p>
                </div>
            `;
            return;
        }

        notifList.innerHTML = '';
        items.forEach(item => {
            const li = document.createElement('li');
            li.className = `notif-item ${item.is_read ? '' : 'unread'}`;
            li.dataset.id = item.id;
            li.dataset.link = item.link || '#';

            const iconTypeClass = getIconTypeClass(item.type);
            const iconName = item.icon || 'ph-bell';

            li.innerHTML = `
                <div class="notif-icon-box ${iconTypeClass}">
                    <i class="ph ${iconName}"></i>
                </div>
                <div class="notif-content">
                    <div class="notif-title">${item.title}</div>
                    ${item.message ? `<div class="notif-body">${item.message}</div>` : ''}
                    <span class="notif-time">${item.time_ago || ''}</span>
                </div>
            `;

            li.addEventListener('click', () => handleNotificationClick(item, li));
            notifList.appendChild(li);
        });
    }

    function getIconTypeClass(type) {
        const types = {
            task: 'notif-icon-task',
            comment: 'notif-icon-comment',
            meeting: 'notif-icon-meeting',
            mention: 'notif-icon-mention',
            calendar: 'notif-icon-calendar',
            quote: 'notif-icon-quote'
        };
        return types[type] || 'notif-icon-general';
    }

    function handleNotificationClick(item, element) {
        // Si no está leída, marcarla en servidor
        if (!item.is_read) {
            item.is_read = 1;
            element.classList.remove('unread');
            updateBadge(currentUnreadCount - 1);

            fetch('ajax/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_as_read&id=${item.id}`
            }).catch(e => console.debug('Error marking read:', e));
        }

        closePopover();

        // Redirigir si tiene un enlace válido
        if (item.link && item.link !== '#' && item.link !== '') {
            window.location.href = item.link;
        }
    }

    function markAllAsRead() {
        fetch('ajax/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_all_read'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateBadge(0);
                if (notifList) {
                    notifList.querySelectorAll('.notif-item.unread').forEach(el => {
                        el.classList.remove('unread');
                    });
                }
                notificationsCache.forEach(item => { item.is_read = 1; });
            }
        })
        .catch(err => console.debug('Error marking all as read:', err));
    }

    // Pusher WebSocket en tiempo real
    function initPusher() {
        if (typeof Pusher === 'undefined' || !window.CURRENT_USER_ID) {
            return;
        }

        try {
            // Reutilizar instancia global de Pusher si ya existe en la página
            let pusherClient = window.pusherInstance;
            if (!pusherClient) {
                pusherClient = new Pusher('b31f38612d61b0285c78', {
                    cluster: 'us2',
                    authEndpoint: 'ajax_pusher_auth.php'
                });
                window.pusherInstance = pusherClient;
            }

            const channelName = 'private-user-' + window.CURRENT_USER_ID;
            const channel = pusherClient.subscribe(channelName);

            channel.bind('new-notification', (data) => {
                // 1. Reproducir sonido si existe
                if (typeof window.playNotificationSound === 'function') {
                    try { window.playNotificationSound(); } catch(e) {}
                }

                // 2. Mostrar toast flotante en pantalla
                if (typeof window.showToast === 'function') {
                    window.showToast(`${data.title}: ${data.message || ''}`, 'info');
                }

                // 3. Incrementar el badge con animación
                updateBadge(currentUnreadCount + 1, true);

                // 4. Si el popover está abierto, insertar elemento arriba
                if (isPopoverOpen && notifList) {
                    prependNewNotification(data);
                }
            });
        } catch (e) {
            console.debug('Pusher notif setup skipped:', e);
        }
    }

    function prependNewNotification(item) {
        if (!notifList) return;

        // Quitar estado vacío si existía
        const emptyState = notifList.querySelector('.notif-empty');
        if (emptyState) emptyState.remove();

        const li = document.createElement('li');
        li.className = 'notif-item unread';
        li.dataset.id = item.id || 0;
        li.dataset.link = item.link || '#';

        const iconTypeClass = getIconTypeClass(item.type);
        const iconName = item.icon || 'ph-bell';

        li.innerHTML = `
            <div class="notif-icon-box ${iconTypeClass}">
                <i class="ph ${iconName}"></i>
            </div>
            <div class="notif-content">
                <div class="notif-title">${item.title}</div>
                ${item.message ? `<div class="notif-body">${item.message}</div>` : ''}
                <span class="notif-time">${item.time_ago || 'hace un momento'}</span>
            </div>
        `;

        li.addEventListener('click', () => handleNotificationClick(item, li));
        notifList.insertBefore(li, notifList.firstChild);
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
