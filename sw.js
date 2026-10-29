self.addEventListener('push', function(event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    const data = event.data ? event.data.json() : {};
    const appName = data.app_name || 'ROMA';
    const rawTitle = data.title || 'Nueva Notificación';
    const title = rawTitle.toLowerCase().includes(appName.toLowerCase()) ? rawTitle : `${appName} • ${rawTitle}`;
    const message = data.body || 'Tienes un nuevo mensaje.';
    const icon = data.icon || 'assets/img/icon-192x192.png';
    const badge = data.badge || icon;
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


// ---- Caché de recursos estáticos (stale-while-revalidate) para carga más rápida / uso offline parcial ----
const STATIC_CACHE = 'roma-static-v1';
self.addEventListener('install', function() { self.skipWaiting(); });
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(keys.filter(function(k) { return k.indexOf('roma-static-') === 0 && k !== STATIC_CACHE; })
                .map(function(k) { return caches.delete(k); }));
        }).then(function() { return self.clients.claim(); })
    );
});
self.addEventListener('fetch', function(event) {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    if (!/\/(assets|uploads)\/.+\.(css|js|png|jpg|jpeg|webp|svg|woff2?)$/i.test(url.pathname)) return;
    event.respondWith(
        caches.open(STATIC_CACHE).then(function(cache) {
            return cache.match(req).then(function(cached) {
                const network = fetch(req).then(function(res) {
                    if (res && res.status === 200) cache.put(req, res.clone());
                    return res;
                }).catch(function() { return cached; });
                return cached || network;
            });
        })
    );
});