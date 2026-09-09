/**
 * Roma Chat Widget - Real-Time Chat & Bubble Controller
 */

(function($) {
    'use strict';

    if (typeof window.RomaWidgetConfig === 'undefined') {
        console.warn('RomaWidgetConfig not found.');
        return;
    }

    const config = window.RomaWidgetConfig;
    const STORAGE_KEY = 'roma_chat_session_v1';

    let chatSession = null;
    let lastMessageId = 0;
    let pusherClient = null;
    let pusherChannel = null;
    let pollingInterval = null;
    let isWindowOpen = false;

    // Element references
    const $container = $('#roma-chat-widget');
    const $bubble = $('#roma-chat-bubble');
    const $window = $('#roma-chat-window');
    const $badge = $('#roma-unread-badge');
    const $screenWelcome = $('#roma-screen-welcome');
    const $screenMessages = $('#roma-screen-messages');
    const $footer = $('#roma-chat-footer');
    const $messagesList = $('#roma-messages-list');
    const $startForm = $('#roma-start-chat-form');
    const $sendForm = $('#roma-send-message-form');
    const $inputMessage = $('#roma-message-input');
    const $btnExpand = $('#roma-btn-expand');
    const $btnClose = $('#roma-btn-close');

    // Inicializar widget
    function init() {
        $container.show();

        // Recuperar sesión guardada
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                chatSession = JSON.parse(saved);
            }
        } catch (e) {
            chatSession = null;
        }

        bindEvents();

        if (chatSession && chatSession.chat_token) {
            showMessagesScreen();
            loadMessages(true);
            setupRealtime();
        } else {
            showWelcomeScreen();
        }
    }

    function bindEvents() {
        // Toggle abrir / cerrar
        $bubble.on('click', toggleChat);
        $btnClose.on('click', closeChat);

        // Expandir en nueva pestaña
        $btnExpand.on('click', expandChatInNewTab);

        // Formulario de inicio
        $startForm.on('submit', handleStartChat);

        // Formulario de envío de mensajes
        $sendForm.on('submit', handleSendMessage);

        // Enviar con Enter (sin Shift)
        $inputMessage.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                $sendForm.trigger('submit');
            }
        });
    }

    function toggleChat() {
        if (isWindowOpen) {
            closeChat();
        } else {
            openChat();
        }
    }

    function openChat() {
        isWindowOpen = true;
        $window.addClass('roma-active').attr('aria-hidden', 'false');
        $bubble.find('.roma-icon-open').hide();
        $bubble.find('.roma-icon-close').show();
        $badge.hide().text('0');

        if (chatSession) {
            scrollToBottom();
            setTimeout(function() {
                $inputMessage.focus();
            }, 250);
        }
    }

    function closeChat() {
        isWindowOpen = false;
        $window.removeClass('roma-active').attr('aria-hidden', 'true');
        $bubble.find('.roma-icon-open').show();
        $bubble.find('.roma-icon-close').hide();
    }

    function expandChatInNewTab() {
        if (chatSession && chatSession.chat_token) {
            const url = config.guestUrl + '&token=' + encodeURIComponent(chatSession.chat_token) + 
                        (chatSession.guest_token ? '&guest_token=' + encodeURIComponent(chatSession.guest_token) : '');
            window.open(url, '_blank');
        } else {
            // Si aún no inicia, pedir iniciar primero
            alert('Por favor ingresa tu nombre en el formulario para iniciar la conversación antes de expandir.');
        }
    }

    function showWelcomeScreen() {
        $screenWelcome.show();
        $screenMessages.hide();
        $footer.hide();
    }

    function showMessagesScreen() {
        $screenWelcome.hide();
        $screenMessages.show();
        $footer.show();
    }

    // Iniciar conversación
    function handleStartChat(e) {
        e.preventDefault();

        const name = $('#roma-input-name').val().trim();
        const phone = $('#roma-input-phone').val().trim();
        const email = $('#roma-input-email').val().trim();

        if (!name || !phone) return;

        const $btn = $('#roma-btn-start');
        $btn.prop('disabled', true).html('<span>Conectando...</span>');

        $.ajax({
            url: config.apiUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'init_chat',
                name: name,
                phone: phone,
                email: email,
                initial_message: ''
            },
            success: function(res) {
                $btn.prop('disabled', false).html('<span>Iniciar Chat</span> <i class="ph ph-paper-plane-right"></i>');

                if (res.success) {
                    chatSession = {
                        chat_id: res.chat_id,
                        chat_token: res.chat_token,
                        guest_id: res.guest_id,
                        guest_token: res.guest_token,
                        guest_name: res.guest_name
                    };
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(chatSession));

                    showMessagesScreen();
                    
                    // Render bienvenida personalizada
                    if (config.welcomeMsg) {
                        appendMessage({
                            id: 0,
                            content: config.welcomeMsg,
                            is_own: false,
                            sender: config.widgetTitle || 'Asesor Roma',
                            time: getFormattedCurrentTime()
                        });
                    }

                    setupRealtime();
                    $inputMessage.focus();
                } else {
                    alert('No se pudo iniciar el chat: ' + (res.error || 'Error desconocido'));
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<span>Iniciar Chat</span>');
                alert('Error al contactar con el servidor. Verifica tu conexión.');
            }
        });
    }

    // Configurar Pusher WebSockets y Polling
    function setupRealtime() {
        if (!chatSession || !chatSession.chat_id) return;

        // Limpiar suscripciones previas
        if (pusherClient) {
            try { pusherClient.disconnect(); } catch (e) {}
        }
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }

        // 1. Intentar Pusher si está disponible
        if (typeof window.Pusher !== 'undefined' && config.pusherKey) {
            try {
                pusherClient = new window.Pusher(config.pusherKey, {
                    cluster: config.pusherCluster || 'us2',
                    forceTLS: true
                });

                pusherChannel = pusherClient.subscribe('chat-' + chatSession.chat_id);
                pusherChannel.bind('refresh', function() {
                    loadMessages(false);
                });
            } catch (err) {
                console.warn('Roma Widget: Fallback to polling due to Pusher error:', err);
            }
        }

        // 2. Polling de respaldo cada 3.5 segundos
        pollingInterval = setInterval(function() {
            loadMessages(false);
        }, 3500);
    }

    // Cargar mensajes
    function loadMessages(isInitial) {
        if (!chatSession || !chatSession.chat_token) return;

        $.ajax({
            url: config.apiUrl,
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_messages',
                chat_token: chatSession.chat_token,
                guest_token: chatSession.guest_token,
                last_id: isInitial ? 0 : lastMessageId
            },
            success: function(res) {
                if (res.success && Array.isArray(res.messages)) {
                    if (isInitial) {
                        $messagesList.empty();
                        // Agregar bienvenida si no hay mensajes previos
                        if (res.messages.length === 0 && config.welcomeMsg) {
                            appendMessage({
                                id: 0,
                                content: config.welcomeMsg,
                                is_own: false,
                                sender: config.widgetTitle || 'Asesor Roma',
                                time: getFormattedCurrentTime()
                            });
                        }
                    }

                    let newMsgCount = 0;
                    res.messages.forEach(function(msg) {
                        if (msg.id > lastMessageId) {
                            appendMessage(msg);
                            lastMessageId = Math.max(lastMessageId, msg.id);
                            if (!msg.is_own) {
                                newMsgCount++;
                            }
                        }
                    });

                    if (res.messages.length > 0) {
                        scrollToBottom();
                    }

                    // Notificar si la ventana está cerrada
                    if (!isWindowOpen && newMsgCount > 0) {
                        let currentCount = parseInt($badge.text(), 10) || 0;
                        currentCount += newMsgCount;
                        $badge.text(currentCount).show();
                    }
                }
            }
        });
    }

    // Enviar mensaje
    function handleSendMessage(e) {
        e.preventDefault();

        const content = $inputMessage.val().trim();
        if (!content || !chatSession) return;

        $inputMessage.val('');

        // Optimistic render
        const tempId = 'temp_' + Date.now();
        const optimisticMsg = {
            id: tempId,
            content: content,
            is_own: true,
            sender: 'Tú',
            time: getFormattedCurrentTime()
        };
        appendMessage(optimisticMsg);
        scrollToBottom();

        $.ajax({
            url: config.apiUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'send_message',
                chat_token: chatSession.chat_token,
                guest_token: chatSession.guest_token,
                content: content
            },
            success: function(res) {
                if (res.success) {
                    if (res.message_id) {
                        lastMessageId = Math.max(lastMessageId, res.message_id);
                    }
                }
            },
            error: function() {
                // Indicar error en el mensaje temporal
                $('#msg-' + tempId).css('opacity', '0.6').attr('title', 'Error al enviar');
            }
        });
    }

    function appendMessage(msg) {
        const isOwn = !!msg.is_own;
        const alignClass = isOwn ? 'is-visitor' : 'is-agent';
        const msgIdAttr = msg.id ? 'id="msg-' + msg.id + '"' : '';

        // Escapar contenido para seguridad contra XSS
        const safeContent = $('<div>').text(msg.content).html().replace(/\n/g, '<br>');

        const html = `
            <div class="roma-msg-item ${alignClass}" ${msgIdAttr}>
                <span class="roma-msg-sender">${isOwn ? 'Tú' : $('<div>').text(msg.sender).html()}</span>
                <div class="roma-msg-bubble">${safeContent}</div>
                <span class="roma-msg-time">${msg.time || ''}</span>
            </div>
        `;

        $messagesList.append(html);
    }

    function scrollToBottom() {
        setTimeout(function() {
            $messagesList.scrollTop($messagesList[0].scrollHeight);
        }, 50);
    }

    function getFormattedCurrentTime() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        return `${h}:${m}`;
    }

    // Exponer API global para interactuar desde el portal (shortcode)
    window.RomaChatWidget = {
        open: openChat,
        close: closeChat,
        startWithClient: function(clientData) {
            openChat();
            if (!chatSession) {
                $('#roma-input-name').val(clientData.name || '');
                $('#roma-input-phone').val(clientData.phone || clientData.whatsapp || '');
                $('#roma-input-email').val(clientData.email || '');
                $startForm.trigger('submit');
            }
        },
        setSession: function(sessionData) {
            chatSession = sessionData;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(sessionData));
            showMessagesScreen();
            loadMessages(true);
            setupRealtime();
            openChat();
        }
    };

    // DOM Ready
    $(document).ready(init);

})(jQuery);
