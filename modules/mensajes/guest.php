<?php
// modules/mensajes/guest.php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$token = $_GET['token'] ?? '';

if (empty($token)) {
    echo "Enlace inválido.";
    exit;
}

// Find chat by public link
$stmt = $db->prepare("SELECT * FROM msg_chats WHERE public_link = ?");
$stmt->execute([$token]);
$chat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chat) {
    echo "El chat no existe o el enlace ha caducado.";
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle guest login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guest_name'])) {
    $guest_name = trim($_POST['guest_name']);
    if (!empty($guest_name)) {
        $guest_token = bin2hex(random_bytes(16));
        $stmt = $db->prepare("INSERT INTO msg_guests (name, token) VALUES (?, ?)");
        $stmt->execute([$guest_name, $guest_token]);
        $guest_id = $db->lastInsertId();
        
        $_SESSION['guest_id'] = $guest_id;
        $_SESSION['guest_name'] = $guest_name;
        
        // Add to participants
        $stmt = $db->prepare("INSERT INTO msg_participants (chat_id, guest_id, role) VALUES (?, ?, 'member')");
        $stmt->execute([$chat['id'], $guest_id]);
        
        // Broadcast join message
        $join_msg = $guest_name . " se ha unido al chat mediante el enlace público.";
        $stmt = $db->prepare("INSERT INTO msg_messages (chat_id, sender_guest_id, content, type) VALUES (?, ?, ?, 'system')");
        $stmt->execute([$chat['id'], $guest_id, $join_msg]);
        
        header("Location: index.php?module=mensajes&action=guest&token=" . $token);
        exit;
    }
}

$is_logged_in = isset($_SESSION['guest_id']) || isset($_SESSION['user_id']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?php echo htmlspecialchars($chat['name']); ?></title>
    
    <?php
        $base_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        if ($base_dir == '/') $base_dir = '';
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$base_dir/";
    ?>
    <base href="<?php echo $base_url; ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>
    <link rel="stylesheet" href="modules/mensajes/mensajes_v5.css?v=<?php echo time(); ?>">
    <style>
        body { background: var(--msg-bg); font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .guest-login-card { background: var(--msg-surface); padding: 2rem; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .msg-app { width: 100%; height: 100vh; border-radius: 0; border: none; }
        @media (max-width: 992px) {
            .msg-app { top: 0 !important; height: 100vh !important; height: 100dvh !important; }
        }
    </style>
</head>
<body data-theme="light">
    <!-- Re-use the Chat UI (Blurred if not logged in) -->
    <div class="msg-app <?php echo !$is_logged_in ? 'blurred-bg' : ''; ?>" id="msgApp">
        <main class="msg-main">
            <div class="msg-view" id="msgChatView" style="display:flex; flex-direction:column; height: 100%;">
                <header class="msg-header">
                    <div class="msg-header-info">
                        <div class="msg-chat-avatar"><?php echo strtoupper(substr($chat['name'], 0, 1)); ?></div>
                        <div class="msg-header-title">
                            <h3 id="msgHeaderName"><?php echo htmlspecialchars($chat['name']); ?></h3>
                            <div id="msgTypingIndicator" style="font-size:11px; color: var(--msg-primary); display:none; font-weight:bold;"></div>
                            <div class="msg-header-meta" id="msgHeaderStatus">Invitado</div>
                        </div>
                    </div>
                <div class="msg-header-actions">
                    <button class="msg-icon-btn" onclick="toggleSearch()" title="Buscar"><i class="ph ph-magnifying-glass"></i></button>
                    <button class="msg-icon-btn" onclick="toggleInfoPanel()" title="Info del Chat"><i class="ph ph-info"></i></button>
                    <button class="msg-icon-btn" onclick="toggleInfoPanel()" title="Configuración"><i class="ph ph-gear"></i></button>
                </div>
            </header>
            
            <!-- Message Search Bar -->
            <div id="msgSearchContainer" style="display:none; padding:10px; background:var(--msg-surface); border-bottom:1px solid var(--msg-border); box-shadow:0 2px 8px rgba(0,0,0,0.05); z-index:10;">
                <div style="display:flex; gap:8px;">
                    <input type="text" id="msgSearchInput" placeholder="Buscar mensajes..." style="flex:1; border:1px solid var(--msg-border); border-radius:8px; padding:8px 12px; font-size:13px; outline:none; background:var(--msg-bg); color:var(--msg-text-main);" onkeyup="if(event.key === 'Enter') searchMessages()">
                    <button class="msg-btn-primary" onclick="searchMessages()">Buscar</button>
                    <button class="msg-btn-outline" onclick="toggleSearch()"><i class="ph ph-x"></i></button>
                </div>
            </div>
            
            <div class="msg-area" id="msgArea">
                <!-- Dummy messages for blurred background effect -->
                    <?php if (!$is_logged_in): ?>
                    <div class="msg-bubble-wrap">
                        <div style="display:flex; flex-direction:column; align-items: flex-start; max-width: 100%;">
                            <div class="msg-sender">Sistema</div>
                            <div class="msg-bubble">¡Hola! Únete al chat para ver los mensajes.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Scroll to Bottom FAB -->
                <button id="msgScrollToBottomBtn" class="msg-scroll-bottom-btn" onclick="scrollToBottom()" title="Ir al último mensaje">
                    <i class="ph ph-caret-down"></i>
                    <span id="msgUnreadBadge" class="msg-unread-badge" style="display:none;">0</span>
                </button>
                
                <div class="msg-input-area">
                    
                    <div id="msgContextMenu" class="msg-context-menu" style="display:none;">
                        <div class="msg-ctx-reactions">
                            <span onclick="sendReaction('👍')">👍</span>
                            <span onclick="sendReaction('❤️')">❤️</span>
                            <span onclick="sendReaction('😂')">😂</span>
                            <span onclick="sendReaction('😮')">😮</span>
                            <span onclick="sendReaction('😢')">😢</span>
                            <span onclick="sendReaction('🙏')">🙏</span>
                        </div>
                        <div class="msg-ctx-menu-items">
                            <div class="msg-ctx-item" onclick="ctxReply()"><i class="ph ph-arrow-u-up-left"></i> Responder</div>
                        <div class="msg-ctx-item" onclick="ctxStar()"><i class="ph ph-star" id="ctxStarBtnIcon"></i> <span id="ctxStarBtnText">Destacar</span></div>
                        <div class="msg-ctx-item" onclick="ctxPin()"><i class="ph ph-push-pin" id="ctxPinBtnIcon"></i> <span id="ctxPinBtnText">Fijar</span></div>
                        <div class="msg-ctx-item" id="ctxEditBtn" onclick="ctxEdit()"><i class="ph ph-pencil-simple"></i> Editar</div>
                        <div class="msg-ctx-item" onclick="ctxCopy()"><i class="ph ph-copy"></i> Copiar</div>
                        <div class="msg-ctx-item" onclick="ctxForward()"><i class="ph ph-share-fat"></i> Reenviar</div>
                        <div class="msg-ctx-item" onclick="ctxSelect()"><i class="ph ph-check-square-offset"></i> Seleccionar</div>
                        <div class="msg-ctx-item ctx-danger" id="ctxDeleteBtn" onclick="ctxDelete()"><i class="ph ph-trash"></i> Eliminar</div>
                        </div>
                    </div>
                    
                    <div id="msgFilePreviewContainer" style="display:none;"></div>
                    <input type="hidden" id="msgReplyToId">
                    <div id="msgReplyPreviewContainer" style="display:none;" class="msg-reply-preview">
                        <div class="msg-reply-preview-content">
                            <div class="msg-reply-sender" id="msgReplyPreviewSender">Nombre</div>
                            <div class="msg-reply-text" id="msgReplyPreviewText">Mensaje...</div>
                        </div>
                        <button class="msg-icon-btn" onclick="cancelReply()"><i class="ph ph-x"></i></button>
                    </div>
                    <div class="msg-input-wrapper" id="msgInputWrapper">
                        <button class="msg-icon-btn" title="Emoticonos" style="color: #94a3b8;" onclick="document.getElementById('msgEmojiMenu').classList.toggle('active')" <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
                            <i class="ph ph-smiley"></i>
                        </button>
                        
                        <!-- Emoji Popover -->
                        <div class="msg-emoji-popover" id="msgEmojiMenu">
                            <emoji-picker class="light"></emoji-picker>
                        </div>
                        <button class="msg-icon-btn" id="msgBtnAttach" onclick="document.getElementById('msgAttachMenu').classList.toggle('active')" title="Adjuntar" style="color: #94a3b8;" <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
                            <i class="ph ph-image"></i>
                        </button>
                        <div id="msgMarkdownPreview" class="msg-markdown-preview" style="display:none; padding:10px; background:var(--msg-bubble-own); color:var(--msg-bubble-own-text); border-radius:8px; margin-bottom:8px; font-size:14px; max-height:100px; overflow-y:auto;"></div>
                        <div id="msgCommandMenu" class="msg-command-menu" style="display:none; position:absolute; bottom:100%; left:20px; background:var(--msg-surface); border:1px solid var(--msg-border); border-radius:12px; box-shadow:0 -4px 15px rgba(0,0,0,0.1); width:250px; z-index:1000; overflow:hidden; margin-bottom:10px;"></div>
                        <textarea id="msgInput" rows="1" placeholder="Escribe un mensaje..." onfocus="closeAllPopovers()" onkeydown="handleInputKeydown(event)" oninput="handleInputState()" <?php echo !$is_logged_in ? 'disabled' : ''; ?>></textarea>
                        
                        <!-- Recording UI (Hidden by default) -->
                        <div id="msgRecordingUI" style="display:none; flex:1; align-items:center; gap:10px; color:#ef4444; font-weight:600; padding-left:10px;">
                            <span class="recording-dot"></span>
                            <span id="msgRecordingTime">00:00</span>
                            <div style="flex:1;"></div>
                            <button class="msg-icon-btn" onclick="cancelRecording()" style="color:#ef4444;" title="Cancelar"><i class="ph ph-trash"></i></button>
                        </div>

                        <button class="msg-btn-send" id="msgBtnAction" onclick="handleActionBtn()" <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
                            <i id="actionBtnIcon" class="ph-fill ph-microphone"></i>
                        </button>
                    </div>
                    
                    <!-- Attachment Popover -->
                    <div class="msg-attach-menu" id="msgAttachMenu">
                        <div class="msg-attach-item" onclick="triggerFileInput('*/*')">
                            <div class="msg-attach-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);"><i class="ph ph-file-text"></i></div>
                            <span>Documento</span>
                        </div>
                        <div class="msg-attach-item" onclick="triggerFileInput('image/*,video/*')">
                            <div class="msg-attach-icon" style="background: linear-gradient(135deg, #ec4899, #db2777);"><i class="ph ph-image"></i></div>
                            <span>Foto / Video</span>
                        </div>
                        <div class="msg-attach-item" onclick="toggleGifMenu(); document.getElementById('msgAttachMenu').classList.remove('active');">
                            <div class="msg-attach-icon" style="background: linear-gradient(135deg, #10b981, #059669);"><i class="ph ph-gif"></i></div>
                            <span>GIF</span>
                        </div>
                    </div>

                    <!-- GIF Popover -->
                <div class="msg-gif-popover" id="msgGifMenu">
                    <div class="msg-gif-header">
                        <input type="text" id="msgGifSearchInput" placeholder="Buscar GIFs..." onkeyup="searchGifs()">
                    </div>
                    <div id="msgGifResults" class="msg-gif-results">
                        <div class="msg-gif-empty">Buscar en Tenor...</div>
                    </div>
                </div>

                    <input type="file" id="msgHiddenFileInput" style="display:none;" onchange="handleFileSelect(event)" multiple>
                </div>
            </div>
        </main>
    </div>

    <?php if (!$is_logged_in): ?>
    <style>
        .blurred-bg { 
            filter: blur(14px) brightness(0.95); 
            pointer-events: none; 
            user-select: none; 
            transform: scale(1.02);
            transition: all 0.3s ease;
        }
        [data-theme="dark"] .blurred-bg {
            filter: blur(14px) brightness(0.7);
        }
        .guest-overlay { 
            position: fixed; inset: 0; 
            display: flex; align-items: center; justify-content: center; 
            z-index: 1050; 
            background: rgba(15, 23, 42, 0.4); 
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .guest-login-card {
            background: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 3rem 2.5rem;
            border-radius: 28px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.12), 0 0 0 1px rgba(255,255,255,0.2) inset;
            width: 100%;
            max-width: 420px;
            text-align: center;
            animation: cardEntrance 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            position: relative;
        }
        [data-theme="dark"] .guest-login-card {
            background: rgba(34, 46, 53, 0.85) !important;
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 30px 60px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.05) inset;
        }
        @keyframes cardEntrance {
            0% { transform: translateY(40px) scale(0.9); opacity: 0; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }
        .guest-icon-wrapper {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, var(--msg-primary), #34d399);
            border-radius: 24px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem auto;
            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.3);
            transform: rotate(-6deg);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .guest-login-card:hover .guest-icon-wrapper {
            transform: rotate(0deg) scale(1.08);
        }
        .guest-icon-wrapper i {
            font-size: 2.5rem;
            color: #ffffff;
        }
        .guest-input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .guest-input-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--msg-text-muted);
            font-size: 1.3rem;
            transition: color 0.3s ease;
        }
        .guest-input-group input {
            padding-left: 54px !important;
            height: 56px;
            border-radius: 16px;
            background: var(--msg-bg);
            border: 2px solid transparent !important;
            font-size: 15px;
            font-weight: 500;
            color: var(--msg-text-main);
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
            width: 100%;
        }
        .guest-input-group input:focus {
            border-color: var(--msg-primary) !important;
            background: var(--msg-surface);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15) !important;
            outline: none;
        }
        .guest-input-group input:focus + i {
            color: var(--msg-primary);
        }
        .btn-guest-submit {
            height: 56px;
            border-radius: 16px;
            background: var(--msg-primary);
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
            border: none;
            width: 100%;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
        }
        .btn-guest-submit:hover {
            background: #059669;
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.35);
        }
        .btn-guest-submit:active {
            transform: translateY(0);
        }
    </style>
    <div class="guest-overlay">
        <div class="guest-login-card">
            <div class="guest-icon-wrapper">
                <i class="ph-fill ph-chats-teardrop"></i>
            </div>
            <h3 style="margin-bottom: 0.5rem; font-weight: 800; color: var(--msg-text-main); letter-spacing: -0.5px; font-size: 1.75rem;">Únete al Chat</h3>
            <p style="color: var(--msg-text-muted); font-size: 0.95rem; margin-bottom: 2rem; line-height: 1.6;">
                Has sido invitado al grupo <strong style="color: var(--msg-text-main);"><?php echo htmlspecialchars($chat['name']); ?></strong>.<br>¿Cómo te llamas?
            </p>
            <form method="POST">
                <div class="guest-input-group">
                    <i class="ph ph-user"></i>
                    <input type="text" name="guest_name" class="form-control" placeholder="Escribe tu nombre aquí..." required autocomplete="off">
                </div>
                <button type="submit" class="btn-guest-submit">
                    Comenzar <i class="ph-bold ph-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- Forward Modal -->
    <div class="msg-modal" id="msgForwardModal">
        <div class="msg-modal-content" style="max-width: 400px;">
            <div class="msg-modal-header">
                <h3>Reenviar a...</h3>
                <button class="msg-icon-btn" onclick="closeForwardModal()"><i class="ph ph-x"></i></button>
            </div>
            <div class="msg-modal-body">
                <div style="margin-bottom: 1rem;">
                    <input type="text" id="msgSearchForwardInput" placeholder="Buscar chat..." onkeyup="filterForwardChats()" class="form-control" style="border-radius:12px; font-size:13px; padding:0.6rem;">
                </div>
                <div class="msg-users-list" id="msgForwardChatsList" style="max-height: 250px; overflow-y:auto;">
                    <!-- Chat items here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Lightbox Modal -->
    <div class="msg-lightbox" id="msgLightbox" style="display:none;">
        <div class="msg-lightbox-header" style="z-index: 1002; position:relative;">
            <span class="msg-lightbox-title" id="msgLightboxTitle">Visor</span>
            <div class="msg-lightbox-actions">
                <button class="msg-icon-btn" onclick="closeLightbox()"><i class="ph ph-x"></i></button>
            </div>
        </div>
        <div class="msg-lightbox-nav" id="lbPrevBtn" onclick="prevLightboxImage()" style="display:none; position:absolute; left:20px; top:50%; transform:translateY(-50%); font-size:32px; color:white; cursor:pointer; background:rgba(0,0,0,0.5); border-radius:50%; padding:10px; z-index:1001;"><i class="ph ph-caret-left"></i></div>
        <div class="msg-lightbox-nav" id="lbNextBtn" onclick="nextLightboxImage()" style="display:none; position:absolute; right:20px; top:50%; transform:translateY(-50%); font-size:32px; color:white; cursor:pointer; background:rgba(0,0,0,0.5); border-radius:50%; padding:10px; z-index:1001;"><i class="ph ph-caret-right"></i></div>
        <div class="msg-lightbox-body" id="msgLightboxBody" style="z-index: 1000; position:relative;"></div>
    </div>

    <script>
        const guestChatId = <?php echo $chat['id']; ?>;
        const CURRENT_USER_ID = <?php echo $_SESSION['user_id'] ?? 'null'; ?>;
        const CURRENT_GUEST_ID = <?php echo $_SESSION['guest_id'] ?? 'null'; ?>;
        const CURRENT_USER_NAME = <?php echo json_encode($_SESSION['guest_name'] ?? 'Guest'); ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js?v=<?php echo time(); ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js?v=<?php echo time(); ?>"></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="modules/mensajes/mensajes_v5.js?v=<?php echo time(); ?>"></script>
    <script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@1.21.3/index.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('contextmenu', e => {
            if (!e.target.closest('#msgArea') && !e.target.closest('.msg-chat-item')) {
                e.preventDefault();
            }
        });
        // Override for guest init
        document.addEventListener('DOMContentLoaded', () => {
            openChat(guestChatId, <?php echo json_encode($chat['name']); ?>, null);
            document.getElementById('msgSidebar')?.remove();
            document.getElementById('msgInfoPanel')?.remove();
        });
    </script>
    <?php endif; ?>
</body>
</html>
