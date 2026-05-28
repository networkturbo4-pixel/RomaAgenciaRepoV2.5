self.addEventListener('push', function(event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    const data = event.data ? event.data.json() : {};
    const title = data.title || 'Nueva Notificación';
    const message = data.body || 'Tienes un nuevo mensaje.';
    const icon = data.icon || '/assets/img/icon-192x192.png';
    const badge = data.badge || '/assets/img/badge-72x72.png';
    const url = data.url || '/';

    const options = {
        body: message,
        icon: icon,
        badge: badge,
        data: { url: url },
        vibrate: [100, 50, 100],
        requireInteraction: false
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const urlToOpen = event.notification.data.url;
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(windowClients) {
            for (let i = 0; i < windowClients.length; i++) {
                let client = windowClients[i];
                if (client.url === urlToOpen && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});
