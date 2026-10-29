// service-worker.js — Push Notification Handler
self.addEventListener('push', function(event) {
    let data = { title: 'Nuevo mensaje', body: '', icon: '/assets/img/default-icon.png' };
    
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const appName = data.app_name || 'ROMA';
    const rawTitle = data.title || 'Nuevo mensaje';
    const finalTitle = rawTitle.toLowerCase().includes(appName.toLowerCase()) ? rawTitle : `${appName} • ${rawTitle}`;
    const iconUrl = data.icon || 'assets/img/default-icon.png';

    const options = {
        body: data.body || '',
        icon: iconUrl,
        badge: data.badge || iconUrl,
        vibrate: [200, 100, 200],
        tag: data.tag || 'general-message',
        renotify: true,
        data: {
            url: data.url || '/'
        }
    };

    event.waitUntil(
        self.registration.showNotification(finalTitle, options).then(() => {
            // Broadcast a message to all open clients (windows) to refresh UI
            if (data.custom_data && data.custom_data.module) {
                return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clients => {
                    clients.forEach(client => {
                        client.postMessage({
                            type: 'REFRESH_MODULE',
                            module: data.custom_data.module,
                            payload: data.custom_data
                        });
                    });
                });
            }
        })
    );
});

// Handle notification click — open the URL
self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    const urlToOpen = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            // If a window is already open with that URL or similar module, focus it
            for (let client of clientList) {
                // simple check: if URL contains the same module
                const urlObj = new URL(urlToOpen, self.location.origin);
                const targetModule = urlObj.searchParams.get('module');
                
                const clientUrlObj = new URL(client.url);
                const clientModule = clientUrlObj.searchParams.get('module');

                if (targetModule && clientModule === targetModule && 'focus' in client) {
                    return client.focus();
                }
            }
            // Otherwise open a new window
            return clients.openWindow(urlToOpen);
        })
    );
});
