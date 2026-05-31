<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($file);

// 1. Añadir el Set de notificaciones y pedir permisos
$initLogic = "
    // --- FASE 2: Notificaciones y Sonidos ---
    const notifiedMessages = new Set();
    const notificationSound = new Audio('https://cdn.pixabay.com/download/audio/2021/08/04/audio_0625c1539c.mp3?filename=pop-39222.mp3');
    
    if ('Notification' in window && Notification.permission !== 'denied') {
        Notification.requestPermission();
    }

    function playNotificationSound() {
        if (localStorage.getItem('chat_mute_sound') === 'true') return;
        notificationSound.currentTime = 0;
        notificationSound.play().catch(e => console.log('Autoplay prevent', e));
    }
    // ----------------------------------------
";

// Insert at the beginning of DOMContentLoaded
$content = str_replace("document.addEventListener('DOMContentLoaded', () => {", "document.addEventListener('DOMContentLoaded', () => {" . $initLogic, $content);


// 2. Modificar fetchMessages para detectar mensajes nuevos y reproducir sonido + notificar menciones
// We need to find where lastMessageId is updated.
// It is around line 830: `if (msg.id > lastMessageId) lastMessageId = msg.id;`
// We will look for `const isOwn = msg.user_id == CURRENT_USER_ID;`

// We have inside fetchMessages loop over data.messages:
// let's insert after: `const isOwn = msg.user_id == CURRENT_USER_ID;`
$searchIsOwn = "const isOwn = msg.user_id == CURRENT_USER_ID;";
$replaceIsOwn = "const isOwn = msg.user_id == CURRENT_USER_ID;
            
            // FASE 2: Menciones y Sonidos (solo si es nuevo y no es nuestro)
            if (!fullRender && !isOwn && msg.id > lastMessageId) {
                playNotificationSound();
                
                if (msg.message && msg.message.includes('@' + CURRENT_USER_NAME)) {
                    if (!notifiedMessages.has(msg.id)) {
                        notifiedMessages.add(msg.id);
                        if ('Notification' in window && Notification.permission === 'granted') {
                            new Notification('¡Te han mencionado en ' + (currentChannelData ? currentChannelData.channel.name : 'el chat') + '!', {
                                body: (msg.user_name || 'Alguien') + ': ' + msg.message,
                                icon: 'assets/images/default-avatar.png'
                            });
                        }
                    }
                }
            }
";

$content = str_replace($searchIsOwn, $replaceIsOwn, $content);

file_put_contents($file, $content);
echo "Phase 2 logic added to chat.js\n";
?>
