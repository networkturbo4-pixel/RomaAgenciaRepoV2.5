// assets/js/push.js

const publicVapidKey = 'BAhu9ZcA2cypGC--dbgdXicyU_K4cvZUdRhP4nQ7Y4t8M2LN156sVAWKg1swXA6KIyjBZvZkeIKqTZxxNpdNksI';

/**
 * Función auxiliar para convertir la clave VAPID de base64 a Uint8Array
 */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

/**
 * Inicializa y registra el Service Worker y la suscripción
 */
async function initPushNotifications() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.warn('Push messaging no está soportado en este navegador.');
        return;
    }

    try {
        const registration = await navigator.serviceWorker.register('sw.js');
        console.log('Service Worker registrado con éxito:', registration.scope);

        // Si el usuario ya aceptó permisos o los vamos a pedir:
        const permission = await Notification.requestPermission();
        
        if (permission === 'granted') {
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicVapidKey)
            });

            // Enviar suscripción al backend
            await fetch('ajax_push_subscribe.php', {
                method: 'POST',
                body: JSON.stringify(subscription),
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            console.log('Suscripción Push enviada al servidor.');
        } else {
            console.warn('Permiso para notificaciones denegado.');
        }
    } catch (error) {
        console.error('Error al registrar el Service Worker o suscribir Push:', error);
    }
}

// Iniciar automáticamente si el usuario ya ha dado permisos previamente, 
// o si es un navegador de escritorio, podemos pedirlo. 
// En iOS, el usuario debe haber añadido la web al Home Screen.
document.addEventListener('DOMContentLoaded', () => {
    if (Notification.permission === 'granted') {
        initPushNotifications();
    }
    
    // Iniciar el poller local de reuniones
    startMeetingAlarm();
});

// Exponemos la función globalmente para poder llamarla desde un botón en la UI
window.subscribeToPush = initPushNotifications;

/**
 * Alarma Local de Reuniones
 */
function startMeetingAlarm() {
    const checkMeetings = async () => {
        if (Notification.permission !== 'granted') return;
        
        try {
            const res = await fetch('ajax/check_meetings.php');
            const data = await res.json();
            
            if (data.success && data.meetings && data.meetings.length > 0) {
                data.meetings.forEach(meet => {
                    // Check if we already notified for this meeting
                    const notified = JSON.parse(localStorage.getItem('notified_meetings') || '[]');
                    if (!notified.includes(meet.id)) {
                        
                        // Enviar notificación a través de Service Worker o Notification API
                        if ('serviceWorker' in navigator) {
                            navigator.serviceWorker.ready.then(registration => {
                                registration.showNotification('¡Reunión en 5 minutos!', {
                                    body: `La reunión de ${meet.brand_name} ("${meet.motivo}") está a punto de empezar.`,
                                    icon: '/assets/img/icon-192x192.png',
                                    vibrate: [200, 100, 200],
                                    data: { url: meet.meet_link || '/' }
                                });
                            });
                        } else {
                            new Notification('¡Reunión en 5 minutos!', {
                                body: `La reunión de ${meet.brand_name} ("${meet.motivo}") está a punto de empezar.`,
                                icon: '/assets/img/icon-192x192.png'
                            });
                        }
                        
                        notified.push(meet.id);
                        localStorage.setItem('notified_meetings', JSON.stringify(notified));
                    }
                });
            }
        } catch (error) {
            console.error('Error verificando reuniones:', error);
        }
    };

    // Check every 60 seconds
    setInterval(checkMeetings, 60000);
    // initial check
    setTimeout(checkMeetings, 2000);
}

