<?php
// modules/chat_moderno/index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'] ?? 'Usuario';

require_once 'includes/header.php';
?>

<!-- Import Vue Advanced Chat Web Component -->
<script src="https://cdn.jsdelivr.net/npm/vue-advanced-chat@2.0.4/dist/vue-advanced-chat.umd.js"></script>

<!-- Pusher Client -->
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

<style>
/* ==========================================================================
   LINEAR / RAYCAST ULTRA-CLEAN DARK GLASSMORPHISM
   ========================================================================== */

/* Prevenir overflow o scrollbars externas en el CRM */
body:has(.chat-linear-viewport) .content-wrapper,
.content-wrapper:has(.chat-linear-viewport),
body.module-chat_moderno .content-wrapper {
    overflow: hidden !important;
}

/* Viewport Principal con Mesh Gradient sutil estilo Linear */
.chat-linear-viewport {
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
    background: radial-gradient(800px circle at 50% 0%, rgba(99, 102, 241, 0.08) 0%, transparent 70%), #08090e;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif;
    letter-spacing: -0.015em;
    -webkit-font-smoothing: antialiased;
    box-sizing: border-box;
    width: 100%;
}

@media (min-width: 769px) {
    .chat-linear-viewport {
        margin: -1.5rem !important;
        width: calc(100% + 3rem) !important;
        height: calc(100dvh - 75px) !important;
        max-height: calc(100dvh - 75px) !important;
    }
}

@media (max-width: 768px) {
    .chat-linear-viewport {
        margin: -1rem !important;
        width: calc(100% + 2rem) !important;
        height: calc(100dvh - 52px) !important;
        max-height: calc(100dvh - 52px) !important;
    }
}

/* ==========================================================================
   TOPBAR SLIM LINEAR (CRISP & GLASS)
   ========================================================================== */

.chat-linear-header {
    height: 48px;
    background: rgba(11, 13, 20, 0.88);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    z-index: 20;
    box-sizing: border-box;
}

.chat-linear-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.chat-linear-logo-badge {
    width: 28px;
    height: 28px;
    min-width: 28px;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 15px;
    box-shadow: 0 0 14px rgba(99, 102, 241, 0.45);
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.chat-linear-title {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.chat-linear-title h2 {
    font-size: 14px;
    font-weight: 600;
    margin: 0;
    color: #f8fafc;
    letter-spacing: -0.02em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-linear-pill {
    background: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.08);
    font-size: 10px;
    padding: 1.5px 7px;
    border-radius: 999px;
    font-weight: 600;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

/* Indicador de Estado Pulsante */
.chat-linear-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 7px;
    border-radius: 999px;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.2);
    font-size: 10.5px;
    font-weight: 500;
    color: #34d399;
}

.status-dot-pulse {
    width: 5.5px;
    height: 5.5px;
    background: #10b981;
    border-radius: 50%;
    box-shadow: 0 0 6px #10b981;
    animation: livePulse 2s infinite ease-in-out;
}

@keyframes livePulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.35); opacity: 0.45; }
}

/* Acciones y Botones */
.chat-linear-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.chat-linear-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.16);
    padding: 5.5px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(79, 70, 229, 0.3);
    transition: all 0.16s cubic-bezier(0.16, 1, 0.3, 1);
    white-space: nowrap;
}

.chat-linear-btn:hover {
    background: linear-gradient(135deg, #4338ca, #4f46e5);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(79, 70, 229, 0.45);
}

.chat-linear-btn:active {
    transform: translateY(0);
}

.chat-linear-btn.ghost {
    background: rgba(255, 255, 255, 0.04);
    color: #94a3b8;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: none;
    padding: 5.5px 9px;
}

.chat-linear-btn.ghost:hover {
    background: rgba(255, 255, 255, 0.09);
    color: #f8fafc;
    border-color: rgba(255, 255, 255, 0.15);
}

/* Responsividad */
@media (max-width: 580px) {
    .chat-linear-pill,
    .chat-linear-status {
        display: none !important;
    }
    .chat-linear-header {
        padding: 0 10px;
    }
    .chat-btn-text-full {
        display: none !important;
    }
    .chat-btn-text-short {
        display: inline !important;
    }
}

@media (min-width: 581px) {
    .chat-btn-text-short {
        display: none !important;
    }
}

/* ==========================================================================
   BODY DEL CHAT (LINEAR OBSIDIAN GLASS)
   ========================================================================== */

.chat-linear-body {
    flex: 1;
    height: calc(100% - 48px);
    width: 100%;
    position: relative;
    overflow: hidden;
}

vue-advanced-chat {
    width: 100% !important;
    height: 100% !important;
    font-family: inherit !important;

    /* Base Reset */
    --chat-container-border: none;
    --chat-container-border-radius: 0px;
    --chat-container-box-shadow: none;

    /* Sidebar Canales (Raycast Void Dark) */
    --chat-sidemenu-bg-color: #0b0d14;
    --chat-sidemenu-bg-color-hover: rgba(255, 255, 255, 0.035);
    --chat-sidemenu-bg-color-active: rgba(99, 102, 241, 0.12);
    --chat-sidemenu-color-active: #ffffff;
    --chat-sidemenu-border-color-search: rgba(255, 255, 255, 0.06);

    /* Textos del Sidebar */
    --chat-room-color-username: #f8fafc;
    --chat-room-color-message: #8b9bb4;
    --chat-room-color-timestamp: #52607a;
    --chat-room-color-online: #10b981;
    --chat-room-color-offline: #475569;
    --chat-room-bg-color-badge: #6366f1;
    --chat-room-color-badge: #ffffff;

    /* Header del Chat Activo */
    --chat-header-bg-color: #0c0f18;
    --chat-header-color-name: #f8fafc;
    --chat-header-color-info: #8b9bb4;

    /* Feed Central de Mensajes */
    --chat-content-bg-color: #08090e;
    --chat-bg-color: #08090e;

    /* Burbujas de Mensaje Linear Style */
    --chat-message-bg-color: #141a29;
    --chat-message-bg-color-me: #4f46e5;
    --chat-message-color: #f8fafc;
    --chat-message-color-started: #8b9bb4;
    --chat-message-color-timestamp: rgba(255, 255, 255, 0.6);
    --chat-message-bg-color-date: rgba(255, 255, 255, 0.05);
    --chat-message-color-date: #94a3b8;

    /* Respuestas Citadas */
    --chat-message-bg-color-reply: rgba(0, 0, 0, 0.35);
    --chat-message-color-reply-username: #a5b4fc;
    --chat-message-color-reply-content: #cbd5e1;

    /* Reacciones */
    --chat-message-bg-color-reaction: rgba(255, 255, 255, 0.05);
    --chat-message-border-style-reaction: 1px solid rgba(255, 255, 255, 0.08);
    --chat-message-color-reaction-counter: #e2e8f0;
    --chat-message-bg-color-reaction-me: rgba(99, 102, 241, 0.25);
    --chat-message-border-style-reaction-me: 1px solid #6366f1;
    --chat-message-color-reaction-counter-me: #c7d2fe;

    /* Grabación de Audio */
    --chat-message-bg-color-audio-record: #ef4444;
    --chat-message-bg-color-audio-line: #4f46e5;
    --chat-message-bg-color-audio-progress: #6366f1;
    --chat-message-bg-color-audio-progress-selector: #818cf8;

    /* Footer & Input Bar Linear */
    --chat-footer-bg-color: #0c0f18;
    --chat-bg-color-input: #121624;
    --chat-color-placeholder: #52607a;
    --chat-color: #f8fafc;
    --chat-border-style-input: 1px solid rgba(255, 255, 255, 0.09);
    --chat-border-color-input-selected: #6366f1;
    --chat-color-caret: #6366f1;

    /* Iconos */
    --chat-icon-color-send: #818cf8;
    --chat-icon-color-paperclip: #8b9bb4;
    --chat-icon-color-microphone: #8b9bb4;
    --chat-icon-color-emoji: #8b9bb4;
    --chat-icon-color-checkmark: #64748b;
    --chat-icon-color-checkmark-seen: #38bdf8;
    --chat-icon-color-search: #818cf8;
    --chat-icon-color-add: #818cf8;
}

/* ==========================================================================
   TEMA CLARO (LINEAR CLEAN LIGHT)
   ========================================================================== */

vue-advanced-chat[theme="light"] {
    --chat-sidemenu-bg-color: #ffffff;
    --chat-sidemenu-bg-color-hover: #f8fafc;
    --chat-sidemenu-bg-color-active: #eef2ff;
    --chat-sidemenu-color-active: #312e81;
    --chat-sidemenu-border-color-search: #e2e8f0;

    --chat-room-color-username: #0f172a;
    --chat-room-color-message: #64748b;
    --chat-room-color-timestamp: #94a3b8;
    --chat-room-color-online: #10b981;
    --chat-room-color-offline: #cbd5e1;
    --chat-room-bg-color-badge: #4f46e5;
    --chat-room-color-badge: #ffffff;

    --chat-header-bg-color: #ffffff;
    --chat-header-color-name: #0f172a;
    --chat-header-color-info: #64748b;

    --chat-content-bg-color: #f8fafc;
    --chat-bg-color: #f8fafc;

    --chat-message-bg-color: #ffffff;
    --chat-message-bg-color-me: #4f46e5;
    --chat-message-color: #0f172a;
    --chat-message-color-started: #64748b;
    --chat-message-color-timestamp: rgba(100, 116, 139, 0.85);
    --chat-message-bg-color-date: #e2e8f0;
    --chat-message-color-date: #475569;

    --chat-message-bg-color-reply: rgba(241, 245, 249, 0.85);
    --chat-message-color-reply-username: #4338ca;
    --chat-message-color-reply-content: #475569;

    --chat-message-bg-color-reaction: #f1f5f9;
    --chat-message-border-style-reaction: 1px solid #e2e8f0;
    --chat-message-color-reaction-counter: #334155;
    --chat-message-bg-color-reaction-me: #e0e7ff;
    --chat-message-border-style-reaction-me: 1px solid #818cf8;
    --chat-message-color-reaction-counter-me: #3730a3;

    --chat-footer-bg-color: #ffffff;
    --chat-bg-color-input: #f8fafc;
    --chat-color-placeholder: #94a3b8;
    --chat-color: #0f172a;
    --chat-border-style-input: 1px solid #e2e8f0;
    --chat-border-color-input-selected: #4f46e5;
    --chat-color-caret: #4f46e5;

    --chat-icon-color-send: #4f46e5;
    --chat-icon-color-paperclip: #64748b;
    --chat-icon-color-microphone: #64748b;
    --chat-icon-color-emoji: #64748b;
    --chat-icon-color-checkmark: #94a3b8;
    --chat-icon-color-checkmark-seen: #2563eb;
    --chat-icon-color-search: #94a3b8;
    --chat-icon-color-add: #4f46e5;
}

.chat-linear-viewport.theme-light {
    background: #f8fafc;
}
.chat-linear-viewport.theme-light .chat-linear-header {
    background: rgba(255, 255, 255, 0.95);
    border-bottom: 1px solid #e2e8f0;
}
.chat-linear-viewport.theme-light .chat-linear-title h2 {
    color: #0f172a;
}
.chat-linear-viewport.theme-light .chat-linear-btn.ghost {
    background: #f1f5f9;
    color: #334155;
    border-color: #cbd5e1;
}

/* ==========================================================================
   MODAL RAYCAST COMMAND (NEW CONVERSATION)
   ========================================================================== */

.chat-raycast-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(8, 9, 14, 0.8);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}

.chat-raycast-backdrop.open {
    display: flex;
}

.chat-raycast-card {
    background: #0f131f;
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #f8fafc;
    width: 100%;
    max-width: 440px;
    border-radius: 14px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.05);
    overflow: hidden;
    animation: raycastPop 0.16s cubic-bezier(0.16, 1, 0.3, 1);
}

.chat-linear-viewport.theme-light .chat-raycast-card {
    background: #ffffff;
    color: #0f172a;
    border-color: #e2e8f0;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
}

@keyframes raycastPop {
    from { transform: scale(0.96); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.chat-raycast-header {
    padding: 14px 18px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-linear-viewport.theme-light .chat-raycast-header {
    border-bottom-color: #e2e8f0;
}

.chat-raycast-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: -0.01em;
}

.chat-raycast-close {
    background: transparent;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #64748b;
    padding: 4px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s;
}

.chat-raycast-close:hover {
    color: #f8fafc;
}

.chat-raycast-body {
    padding: 14px 18px 18px 18px;
    max-height: 420px;
    overflow-y: auto;
}

.chat-raycast-search-input {
    width: 100%;
    padding: 10px 14px;
    background: #151a2a;
    border: 1px solid rgba(255, 255, 255, 0.09);
    border-radius: 8px;
    color: white;
    font-size: 13px;
    outline: none;
    margin-bottom: 12px;
    box-sizing: border-box;
    transition: border-color 0.15s;
}

.chat-linear-viewport.theme-light .chat-raycast-search-input {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a;
}

.chat-raycast-search-input:focus {
    border-color: #6366f1;
}

.chat-user-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s;
}

.chat-user-item:hover {
    background: rgba(255, 255, 255, 0.05);
}

.chat-linear-viewport.theme-light .chat-user-item:hover {
    background: #f1f5f9;
}

.chat-user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    background: #1e293b;
    flex-shrink: 0;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.chat-user-info {
    flex: 1;
    min-width: 0;
}

.chat-user-name {
    font-weight: 600;
    font-size: 13px;
    color: inherit;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-user-email {
    font-size: 11px;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<div class="chat-linear-viewport" id="chat-viewport">
    <!-- Header Superior Linear Slim -->
    <div class="chat-linear-header">
        <div class="chat-linear-brand">
            <div class="chat-linear-logo-badge">
                <i class="ph ph-chats-circle"></i>
            </div>
            <div class="chat-linear-title">
                <h2>Mensajería</h2>
                <span class="chat-linear-pill">Linear Glass</span>
                <div class="chat-linear-status" title="Conexión en tiempo real activa">
                    <span class="status-dot-pulse"></span>
                    <span>En vivo</span>
                </div>
            </div>
        </div>
        <div class="chat-linear-actions">
            <button class="chat-linear-btn ghost" id="btn-toggle-theme" title="Alternar Modo Claro / Oscuro">
                <i class="ph ph-moon" id="theme-icon"></i>
            </button>
            <button class="chat-linear-btn" id="btn-new-chat">
                <i class="ph ph-plus-circle"></i>
                <span class="chat-btn-text-full">Nuevo Chat</span>
                <span class="chat-btn-text-short">Nuevo</span>
            </button>
        </div>
    </div>

    <!-- Web Component de Chat (Linear Theme) -->
    <div class="chat-linear-body">
        <vue-advanced-chat
            id="chat-app"
            height="100%"
            theme="dark"
            current-user-id="<?php echo htmlspecialchars((string)$current_user_id); ?>"
            show-audio="true"
            show-files="true"
            show-emojis="true"
            show-reaction-emojis="true"
            rooms-loaded="true"
            messages-loaded="true"
            responsive-breakpoint="900"
            accepted-files="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.zip"
        ></vue-advanced-chat>
    </div>
</div>

<!-- Modal Raycast (Nueva Conversación) -->
<div class="chat-raycast-backdrop" id="modal-new-chat">
    <div class="chat-raycast-card">
        <div class="chat-raycast-header">
            <h3>Nueva Conversación</h3>
            <button class="chat-raycast-close" id="btn-close-modal">&times;</button>
        </div>
        <div class="chat-raycast-body">
            <input type="text" class="chat-raycast-search-input" id="input-search-users" placeholder="Escribe el nombre o correo del compañero...">
            <div id="users-search-list">
                <!-- Se llena dinámicamente -->
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Registro del Web Component
    if (window['vue-advanced-chat'] && typeof window['vue-advanced-chat'].register === 'function') {
        window['vue-advanced-chat'].register();
    }

    const currentUserId = '<?php echo (string)$current_user_id; ?>';
    const chat = document.querySelector('#chat-app');
    const viewport = document.getElementById('chat-viewport');
    const ajaxUrl = 'modules/chat_moderno/ajax.php';

    // 2. Diccionario en Español para el UI
    const textMessagesEs = {
        ROOMS_EMPTY: 'No hay conversaciones activas',
        ROOM_EMPTY: 'Sin mensajes aún. Comienza la conversación...',
        NEW_MESSAGES: 'Nuevos mensajes',
        MESSAGE_DELETED: 'Mensaje eliminado',
        MESSAGES_EMPTY: 'No hay mensajes',
        CONVERSATION_START: 'Inicio del hilo de mensajes',
        TYPE_MESSAGE: 'Escribe un mensaje o presiona espacio...',
        SEARCH: 'Buscar canales o personas...',
        IS_ONLINE: 'en línea',
        LAST_SEEN: 'última vez ',
        IS_TYPING: 'está escribiendo...',
        CANCEL_SELECT_MESSAGE: 'Cancelar'
    };

    chat.textMessages = textMessagesEs;
    chat.roomsLoaded = true;
    chat.messagesLoaded = true;
    chat.setAttribute('rooms-loaded', 'true');
    chat.setAttribute('messages-loaded', 'true');

    // 3. Manejo de Tema (Light / Dark)
    let currentTheme = localStorage.getItem('linear_chat_theme') || 'dark';

    function setChatTheme(theme) {
        currentTheme = theme;
        localStorage.setItem('linear_chat_theme', theme);
        chat.setAttribute('theme', theme);

        const icon = document.getElementById('theme-icon');
        if (theme === 'light') {
            viewport.classList.add('theme-light');
            icon.className = 'ph ph-sun';
        } else {
            viewport.classList.remove('theme-light');
            icon.className = 'ph ph-moon';
        }
    }
    setChatTheme(currentTheme);

    document.getElementById('btn-toggle-theme').addEventListener('click', () => {
        setChatTheme(currentTheme === 'dark' ? 'light' : 'dark');
    });

    let currentRoomId = null;
    let lastRoomsHash = '';

    const isMobileDevice = () => window.innerWidth < 900;

    // 4. Carga de Salas
    async function loadRooms(selectFirst = false) {
        try {
            const res = await fetch(`${ajaxUrl}?action=get_rooms`);
            const data = await res.json();
            if (data.success && Array.isArray(data.rooms)) {
                const newHash = JSON.stringify(data.rooms);
                if (newHash !== lastRoomsHash) {
                    lastRoomsHash = newHash;
                    chat.rooms = data.rooms;
                    chat.roomsLoaded = true;
                    chat.setAttribute('rooms-loaded', 'true');
                }

                // En desktop auto-abrir la primera sala si no hay ninguna activa
                if (selectFirst && data.rooms.length > 0 && !currentRoomId && !isMobileDevice()) {
                    currentRoomId = String(data.rooms[0].roomId);
                    chat.roomId = currentRoomId;
                    loadMessages(currentRoomId);
                    subscribeToRoom(currentRoomId);
                }
            }
        } catch (err) {
            console.warn('Error al verificar conversaciones:', err);
        }
    }

    // 5. Carga de Mensajes (Desactiva spinner y renderiza al instante)
    async function loadMessages(roomId) {
        if (!roomId) return;
        currentRoomId = String(roomId);

        try {
            const res = await fetch(`${ajaxUrl}?action=get_messages&chat_id=${roomId}`);
            const data = await res.json();
            if (data.success && Array.isArray(data.messages)) {
                chat.messages = data.messages;
            } else {
                chat.messages = [];
            }
        } catch (err) {
            console.warn('Error al cargar mensajes:', err);
            chat.messages = [];
        } finally {
            chat.messagesLoaded = true;
            chat.setAttribute('messages-loaded', 'true');
        }
    }

    // 6. Eventos del Componente

    chat.addEventListener('fetch-messages', (e) => {
        const detail = e.detail?.[0] || {};
        const room = detail.room;
        if (room && room.roomId) {
            currentRoomId = String(room.roomId);
            loadMessages(currentRoomId);
            subscribeToRoom(currentRoomId);
        }
    });

    // Enviar Mensaje
    chat.addEventListener('send-message', async (e) => {
        const detail = e.detail?.[0] || {};
        const { roomId, content, files, replyMessage } = detail;

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('chat_id', roomId);
        formData.append('content', content || '');

        if (replyMessage && replyMessage._id) {
            formData.append('reply_to_id', replyMessage._id);
        }

        if (files && files.length > 0) {
            const f = files[0];
            formData.append('file', f.blob, f.name || 'archivo');
        }

        try {
            const res = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                await loadMessages(roomId);
                loadRooms();
            } else {
                alert(data.error || 'No se pudo enviar el mensaje');
            }
        } catch (err) {
            console.error('Error al enviar:', err);
        }
    });

    // Reacciones Sanitizadas
    chat.addEventListener('send-message-reaction', async (e) => {
        const detail = e.detail?.[0] || {};
        const messageId = detail.messageId;
        const reaction = detail.reaction;
        const remove = detail.remove;

        let emojiStr = '';
        if (typeof reaction === 'string') {
            emojiStr = reaction;
        } else if (reaction && typeof reaction === 'object') {
            emojiStr = reaction.unicode || reaction.name || reaction.emoji || '';
        }

        if (!emojiStr || emojiStr === 'undefined' || emojiStr.trim() === '') {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'react');
        formData.append('message_id', messageId);
        formData.append('emoji', emojiStr);
        formData.append('remove', remove ? 'true' : 'false');

        try {
            await fetch(ajaxUrl, { method: 'POST', body: formData });
            if (currentRoomId) loadMessages(currentRoomId);
        } catch (err) {
            console.error('Error al enviar reacción:', err);
        }
    });

    // Eliminar Mensaje
    chat.addEventListener('delete-message', async (e) => {
        const detail = e.detail?.[0] || {};
        const message = detail.message;
        if (!message || !message._id) return;

        if (!confirm('¿Eliminar este mensaje?')) return;

        const formData = new FormData();
        formData.append('action', 'delete_message');
        formData.append('message_id', message._id);

        try {
            const res = await fetch(ajaxUrl, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success && currentRoomId) {
                loadMessages(currentRoomId);
                loadRooms();
            }
        } catch (err) {
            console.error('Error al eliminar mensaje:', err);
        }
    });

    // Editar Mensaje
    chat.addEventListener('edit-message', async (e) => {
        const detail = e.detail?.[0] || {};
        const { messageId, newContent } = detail;
        if (!messageId) return;

        const formData = new FormData();
        formData.append('action', 'edit_message');
        formData.append('message_id', messageId);
        formData.append('content', newContent || '');

        try {
            await fetch(ajaxUrl, { method: 'POST', body: formData });
            if (currentRoomId) loadMessages(currentRoomId);
        } catch (err) {
            console.error('Error al editar:', err);
        }
    });

    // Botón '+' en salas para abrir modal
    chat.addEventListener('add-room', () => {
        openNewChatModal();
    });

    // 7. Modal Raycast (Nueva Conversación)
    const modalNewChat = document.getElementById('modal-new-chat');
    const btnNewChat = document.getElementById('btn-new-chat');
    const btnCloseModal = document.getElementById('btn-close-modal');
    const inputSearch = document.getElementById('input-search-users');
    const usersList = document.getElementById('users-search-list');

    function openNewChatModal() {
        modalNewChat.classList.add('open');
        inputSearch.value = '';
        searchUsers('');
        setTimeout(() => inputSearch.focus(), 120);
    }

    function closeNewChatModal() {
        modalNewChat.classList.remove('open');
    }

    btnNewChat.addEventListener('click', openNewChatModal);
    btnCloseModal.addEventListener('click', closeNewChatModal);
    modalNewChat.addEventListener('click', (e) => {
        if (e.target === modalNewChat) closeNewChatModal();
    });

    let searchTimer = null;
    inputSearch.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => searchUsers(inputSearch.value.trim()), 200);
    });

    async function searchUsers(q) {
        usersList.innerHTML = '<div style="padding:15px;text-align:center;color:#64748b;font-size:13px;">Buscando personas...</div>';
        try {
            const res = await fetch(`${ajaxUrl}?action=search_users&q=${encodeURIComponent(q)}`);
            const data = await res.json();
            if (data.success && data.users) {
                if (data.users.length === 0) {
                    usersList.innerHTML = '<div style="padding:15px;text-align:center;color:#64748b;font-size:13px;">No se encontraron personas.</div>';
                    return;
                }
                usersList.innerHTML = data.users.map(u => `
                    <div class="chat-user-item" data-uid="${u.id}">
                        <img src="${u.avatar}" class="chat-user-avatar" alt="${u.name}" onerror="this.src='https://ui-avatars.com/api/?name=User&background=4f46e5&color=fff'">
                        <div class="chat-user-info">
                            <div class="chat-user-name">${u.name}</div>
                            <div class="chat-user-email">${u.email || ''}</div>
                        </div>
                        <i class="ph ph-chat-circle-dots" style="color:#6366f1;font-size:18px;"></i>
                    </div>
                `).join('');

                usersList.querySelectorAll('.chat-user-item').forEach(item => {
                    item.addEventListener('click', async () => {
                        const targetId = item.getAttribute('data-uid');
                        const formData = new FormData();
                        formData.append('action', 'start_direct_chat');
                        formData.append('target_id', targetId);

                        const startRes = await fetch(ajaxUrl, { method: 'POST', body: formData });
                        const startData = await startRes.json();
                        if (startData.success && startData.chat_id) {
                            closeNewChatModal();
                            currentRoomId = String(startData.chat_id);
                            await loadRooms();
                            chat.roomId = currentRoomId;
                            loadMessages(currentRoomId);
                            subscribeToRoom(currentRoomId);
                        }
                    });
                });
            }
        } catch (err) {
            usersList.innerHTML = '<div style="padding:15px;text-align:center;color:#ef4444;font-size:13px;">Error al conectar</div>';
        }
    }

    // 8. Sincronización Pusher en Tiempo Real
    let pusherClient = null;
    try {
        if (typeof Pusher !== 'undefined') {
            pusherClient = new Pusher('b31f38612d61b0285c78', {
                cluster: 'us2',
                forceTLS: true
            });
        }
    } catch (e) {
        console.warn('Pusher:', e);
    }

    let activePusherChannel = null;
    function subscribeToRoom(roomId) {
        if (!pusherClient || !roomId) return;
        if (activePusherChannel) {
            pusherClient.unsubscribe(activePusherChannel.name);
        }
        const channelName = 'chat-' + roomId;
        activePusherChannel = pusherClient.subscribe(channelName);
        activePusherChannel.bind('refresh', () => {
            loadMessages(roomId);
            loadRooms();
        });
    }

    // Polling pasivo cada 6 segundos
    setInterval(() => {
        if (currentRoomId) {
            loadMessages(currentRoomId);
        }
        loadRooms();
    }, 6000);

    // Arranque inicial
    loadRooms(true);
});
</script>
