// assets/js/push_notifications.js
(function() {
    async function initPushNotifications() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
        
        try {
            const basePath = window.location.pathname.includes('/CESARMENDOZA/') ? '/CESARMENDOZA/' : '/';
            const registration = await navigator.serviceWorker.register(basePath + 'service-worker.js');
            
            // Check if already subscribed
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                // Already subscribed, maybe we don't need to re-send to server unless necessary,
                // but to keep it simple, we assume the server has it.
                return;
            }

            // Get VAPID key
            const res = await fetch('modules/chat/ajax_push.php', {
                method: 'POST', body: new URLSearchParams({ action: 'get_vapid_key' })
            });
            const data = await res.json();
            if (!data.success) return;

            // Request permission
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') return;

            // Subscribe
            const newSub = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(data.key)
            });

            const subJson = newSub.toJSON();
            await fetch('modules/chat/ajax_push.php', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'subscribe',
                    endpoint: subJson.endpoint,
                    p256dh: subJson.keys.p256dh,
                    auth: subJson.keys.auth
                })
            });
            
            console.log("Suscrito a Push Notifications con éxito.");
        } catch (e) {
            console.log('Push notification setup skipped:', e.message);
        }
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // Listen to messages from the ServiceWorker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', event => {
            if (event.data && event.data.type === 'REFRESH_MODULE') {
                const moduleToRefresh = event.data.module;
                const urlParams = new URLSearchParams(window.location.search);
                const currentModule = urlParams.get('module');
                
                // If the user is currently looking at the module that received an update
                if (currentModule === moduleToRefresh || (currentModule === 'project_board' && moduleToRefresh === 'design_tasks')) {
                    // Trigger a custom event that specific modules can listen to
                    const refreshEvent = new CustomEvent('app:refresh_data', { detail: event.data });
                    window.dispatchEvent(refreshEvent);
                    
                    // General fallback: if the module defined a global reload function, call it.
                    if (moduleToRefresh === 'design_tasks' && typeof fetchTasks === 'function') {
                        fetchTasks(); // Reload kanban board
                    } else if (moduleToRefresh === 'tasks' && typeof TC !== 'undefined' && typeof TC.loadTasks === 'function') {
                        TC.loadTasks(); // Reload task center
                    } else if (moduleToRefresh === 'calendar') {
                        window.location.reload(); // Calendar/month board requires reload
                    }
                }
            }
        });
    }

    // Initialize after a short delay to not block page load
    window.addEventListener('load', () => {
        setTimeout(initPushNotifications, 2000);
    });

})();
