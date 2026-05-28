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
    // Es buena práctica inicializarlo con un botón si los permisos no están dados.
    // Pero si ya están dados, o para Android/Desktop, lo lanzamos.
    if (Notification.permission === 'granted') {
        initPushNotifications();
    }
});

// Exponemos la función globalmente para poder llamarla desde un botón en la UI
window.subscribeToPush = initPushNotifications;

