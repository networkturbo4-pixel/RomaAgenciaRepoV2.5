<?php
// modules/mensajes/index.php

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit;
}
$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'] ?? 'Usuario';

// Get user permissions
$perms = $_SESSION['user_permissions'] ?? [];

$is_popup = isset($_GET['popup']) && $_GET['popup'] == '1';

include 'includes/header.php';
?>
<!-- Luminous Messages App -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web"></script>
<link rel="stylesheet" href="modules/mensajes/mensajes_v5.css?v=<?php echo mt_rand(); ?>">

<?php if ($is_popup): ?>
<style>
    .msg-app {
        height: 100vh !important;
        border-radius: 0 !important;
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 12px !important;
    }
    .content-wrapper {
        padding: 0 !important;
    }
</style>
<?php endif; ?>

<div class="msg-app" id="msgApp">
    <!-- Sidebar -->
    <aside class="msg-sidebar" id="msgSidebar">
        <div class="msg-sidebar-header">
            <div style="display:flex; align-items:center; gap:8px;">
                <h2>Mensajes</h2>
                <span style="background: var(--msg-primary-light); color: var(--msg-primary); font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; letter-spacing: 0.5px;">BETA</span>
            </div>
            <div style="display:flex; gap:0.25rem;">
                <button class="msg-icon-btn" onclick="openSettingsModal()" title="Configuración">
                    <i class="ph ph-gear"></i>
                </button>
                <button class="msg-icon-btn" onclick="openDirectMessageModal()" title="Directorio de Usuarios">
                    <i class="ph ph-users"></i>
                </button>
                <button class="msg-icon-btn" onclick="openNewChatModal()" title="Nuevo Chat Grupal">
                    <i class="ph ph-plus"></i>
                </button>
            </div>
        </div>
        
        <div class="msg-search">
            <i class="ph ph-magnifying-glass"></i>
            <input type="text" id="chatSearchInput" placeholder="Buscar chats..." onkeyup="filterChats()">
        </div>
        
        <div class="msg-chat-list" id="msgChatList">
            <!-- Chats loaded via JS -->
            <div style="text-align:center; padding: 2rem; color: var(--msg-text-muted);">Cargando...</div>
        </div>
    </aside>

    <!-- Main Chat Area -->
    <main class="msg-main">
        <!-- Empty State -->
        <div class="msg-empty" id="msgEmptyState">
            <div class="msg-empty-illustration">
                <i class="ph ph-chat-teardrop-dots"></i>
                <div class="msg-empty-circle msg-empty-circle-1"></div>
                <div class="msg-empty-circle msg-empty-circle-2"></div>
            </div>
            <h3>Bienvenido a Mensajes</h3>
            <p>Selecciona una conversación para empezar a chatear</p>
        </div>

        <!-- Chat View -->
        <div class="msg-view" id="msgChatView" style="display:none; flex:1; flex-direction:column; height: 100%; position: relative;">
            
            <!-- Drag Overlay -->
            <div class="msg-drag-overlay" id="msgDragOverlay">
                <i class="ph ph-upload-simple"></i>
                <h3>Suelta el archivo aquí</h3>
            </div>

            <header class="msg-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <!-- Back button for mobile -->
                    <button class="msg-icon-btn d-lg-none" onclick="document.getElementById('msgSidebar').classList.remove('hidden')" title="Volver" style="margin-right:-8px;"><i class="ph ph-arrow-left"></i></button>
                    <div class="msg-header-info" onclick="toggleInfoPanel()" style="padding-left:4px;">
                        <div class="msg-chat-avatar" id="msgHeaderAvatar">#</div>
                        <div class="msg-header-title">
                            <h3 id="msgHeaderName">Chat Name</h3>
                            <div id="msgHeaderTypingIndicator" style="font-size:11px; color: var(--msg-primary); display:none; font-weight:bold;"></div>
                            <div class="msg-header-meta" id="msgHeaderStatus">...</div>
                        </div>
                    </div>
                </div>
                <div class="msg-header-actions">
                    <?php if (!$is_popup): ?>
                    <button class="msg-icon-btn d-none d-md-flex" onclick="openChatPopup()" title="Abrir en nueva ventana"><i class="ph ph-app-window"></i></button>
                    <?php endif; ?>
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
                <!-- Messages go here -->
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
                <div id="msgReplyPreviewContainer" style="display:none;" class="msg-reply-preview">
                    <div class="msg-reply-preview-content">
                        <div class="msg-reply-sender" id="msgReplyPreviewSender">Nombre</div>
                        <div class="msg-reply-text" id="msgReplyPreviewText">Mensaje...</div>
                    </div>
                    <button class="msg-icon-btn" onclick="cancelReply()"><i class="ph ph-x"></i></button>
                    <input type="hidden" id="msgReplyToId" value="">
                </div>
                
                <div id="msgTypingIndicator" style="display:none; padding: 8px 16px; align-items: center; gap: 8px; font-size:12px; color:var(--msg-text-muted); background:var(--msg-surface);">
                    <div class="msg-typing-dots">
                        <span></span><span></span><span></span>
                    </div>
                    <span id="msgTypingText" style="font-weight: 500;"></span>
                </div>
                
                    <div class="msg-input-wrapper" id="msgInputWrapper">
                        <button class="msg-icon-btn" title="Emoticonos" style="color: #94a3b8;" onclick="document.getElementById('msgEmojiMenu').classList.toggle('active')">
                            <i class="ph ph-smiley"></i>
                        </button>
                        
                        <!-- Emoji Popover -->
                        <div class="msg-emoji-popover" id="msgEmojiMenu">
                            <emoji-picker class="light"></emoji-picker>
                        </div>
                        <button class="msg-icon-btn" id="msgBtnAttach" onclick="document.getElementById('msgAttachMenu').classList.toggle('active')" title="Adjuntar" style="color: #94a3b8;">
                            <i class="ph ph-image"></i>
                        </button>
                    <div id="msgMarkdownPreview" class="msg-markdown-preview" style="display:none; padding:10px; background:var(--msg-bubble-own); color:var(--msg-bubble-own-text); border-radius:8px; margin-bottom:8px; font-size:14px; max-height:100px; overflow-y:auto;"></div>
                    <div id="msgCommandMenu" class="msg-command-menu" style="display:none; position:absolute; bottom:100%; left:20px; background:var(--msg-surface); border:1px solid var(--msg-border); border-radius:12px; box-shadow:0 -4px 15px rgba(0,0,0,0.1); width:250px; z-index:1000; overflow:hidden; margin-bottom:10px;"></div>
                    <textarea id="msgInput" rows="1" placeholder="Escribe un mensaje..." onfocus="closeAllPopovers()" onkeydown="handleInputKeydown(event)" oninput="handleInputState()"></textarea>
                    
                    <!-- Recording UI (Hidden by default) -->
                    <div id="msgRecordingUI" style="display:none; flex:1; align-items:center; gap:10px; color:#ef4444; font-weight:600; padding-left:10px;">
                        <span class="recording-dot"></span>
                        <span id="msgRecordingTime">00:00</span>
                        <div style="flex:1;"></div>
                        <button class="msg-icon-btn" onclick="cancelRecording()" style="color:#ef4444;" title="Cancelar"><i class="ph ph-trash"></i></button>
                    </div>

                    <button class="msg-btn-send" id="msgBtnAction" onclick="handleActionBtn()">
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
                    <div class="msg-attach-item" onclick="openTaskModal(); document.getElementById('msgAttachMenu').classList.remove('active');">
                        <div class="msg-attach-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);"><i class="ph ph-check-square"></i></div>
                        <span>Tarea</span>
                    </div>
                    <div class="msg-attach-item" onclick="openPendienteModal(); document.getElementById('msgAttachMenu').classList.remove('active');">
                        <div class="msg-attach-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);"><i class="ph ph-list-checks"></i></div>
                        <span>Pendientes</span>
                    </div>
                    <div class="msg-attach-item" onclick="openWhiteboardModal(); document.getElementById('msgAttachMenu').classList.remove('active');">
                        <div class="msg-attach-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);"><i class="ph ph-chalkboard"></i></div>
                        <span>Pizarra</span>
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

                <input type="file" id="msgHiddenFileInput" style="display:none;" multiple onchange="handleFileSelect(event)">
            </div>
        </div>
    </main>

    <!-- Info Panel -->
    <aside class="msg-info-panel" id="msgInfoPanel">
        <div class="msg-info-header">
            <h3>Info del Chat</h3>
            <button class="msg-icon-btn" onclick="toggleInfoPanel()"><i class="ph ph-x"></i></button>
        </div>
        <div class="msg-info-body">
            
            <div class="msg-info-group-header">
                <div class="msg-info-group-avatar" id="msgInfoAvatar">#</div>
                <div class="msg-info-group-name" id="msgInfoName">Nombre del Grupo</div>
                <button class="msg-btn-edit-group" onclick="openEditGroupModal()" title="Editar Grupo"><i class="ph ph-pencil-simple"></i></button>
            </div>

            <!-- Drive Integration -->
            <details class="msg-info-details">
                <summary class="msg-info-summary">
                    <div style="display:flex; align-items:center; gap:0.5rem;"><i class="ph ph-cloud" style="color: #3b82f6;"></i> Integraciones</div>
                    <i class="ph ph-caret-down summary-icon"></i>
                </summary>
                <div class="msg-info-card drive-card" style="margin-top: 0.5rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;"><i class="ph ph-google-drive-logo" style="color:#4285F4;"></i> <span style="font-weight:600; font-size:13px;">Google Drive</span></div>
                    <div class="msg-info-card-text">Carpeta vinculada:</div>
                    <div class="msg-info-card-value" id="msgDriveFolderName">Sin vincular</div>
                    <button class="msg-btn-outline-primary w-100" onclick="openDriveSelector()">Vincular Carpeta</button>
                </div>
            </details>
            
            <details class="msg-info-details" id="msgMembersSection" open>
                <summary class="msg-info-summary">
                    <div style="display:flex; align-items:center; gap:0.5rem;"><i class="ph ph-users-three"></i> Miembros</div>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <button id="msgAddMemberBtn" class="msg-btn-sm-primary" onclick="event.preventDefault(); openAddUserModal()" style="padding: 2px 8px; font-size:10px;"><i class="ph ph-plus"></i> Añadir</button>
                        <i class="ph ph-caret-down summary-icon"></i>
                    </div>
                </summary>
                <div class="msg-members-list" id="msgMembersList" style="margin-top: 0.5rem;"></div>
            </details>

            <details class="msg-info-details" open>
                <summary class="msg-info-summary">
                    <div style="display:flex; align-items:center; gap:0.5rem;"><i class="ph ph-image-square"></i> Archivos y Enlaces</div>
                    <i class="ph ph-caret-down summary-icon"></i>
                </summary>
                <div style="display:flex; gap:8px; margin-top:12px; margin-bottom:12px;">
                    <button id="tab-media" class="msg-btn-sm-primary" onclick="switchGalleryTab('media')" style="flex:1;">Media</button>
                    <button id="tab-docs" class="msg-btn-sm-outline" onclick="switchGalleryTab('docs')" style="flex:1;">Docs</button>
                    <button id="tab-links" class="msg-btn-sm-outline" onclick="switchGalleryTab('links')" style="flex:1;">Links</button>
                </div>
                <div class="msg-gallery-grid" id="msgGalleryGrid"></div>
            </details>
            
            <details class="msg-info-details" id="msgPublicLinkSection">
                <summary class="msg-info-summary">
                    <div style="display:flex; align-items:center; gap:0.5rem;"><i class="ph ph-link"></i> Enlace de Invitado</div>
                    <i class="ph ph-caret-down summary-icon"></i>
                </summary>
                <div class="msg-input-group" style="margin-top: 0.5rem;">
                    <input type="text" id="msgPublicLink" readonly>
                    <button class="msg-btn-primary-icon" onclick="copyPublicLink()" title="Copiar enlace"><i class="ph ph-copy"></i></button>
                </div>
            </details>
        </div>
    </aside>
</div>

<!-- Modals -->

<!-- Edit Group Modal -->
<div class="msg-modal" id="msgEditGroupModal">
    <div class="msg-modal-content" style="max-width: 400px;">
        <div class="msg-modal-header">
            <h3>Editar Grupo</h3>
            <button class="msg-icon-btn" onclick="closeEditGroupModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="msg-modal-body">
            <form id="msgEditGroupForm" onsubmit="saveGroupInfo(event)">
                <div style="text-align: center; margin-bottom: 1rem;">
                    <div class="msg-edit-avatar-preview" id="msgEditAvatarPreview" onclick="document.getElementById('msgEditAvatarInput').click()">
                        <i class="ph ph-camera"></i>
                    </div>
                    <input type="file" id="msgEditAvatarInput" accept="image/*" style="display: none;" onchange="previewAvatar(event)">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 12px; font-weight:600; margin-bottom:0.25rem; display:block;">Nombre del Grupo</label>
                    <input type="text" id="msgEditGroupName" class="form-control" required style="border-radius:12px; font-size:13px; padding:0.6rem;">
                </div>
                <button type="submit" class="msg-btn-primary w-100" style="padding:0.6rem; border-radius:12px;">Guardar Cambios</button>
            </form>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="msg-modal" id="msgAddUserModal">
    <div class="msg-modal-content" style="max-width: 400px;">
        <div class="msg-modal-header">
            <h3>Añadir Usuario</h3>
            <button class="msg-icon-btn" onclick="closeAddUserModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="msg-modal-body">
            <div style="margin-bottom: 1rem;">
                <input type="text" id="msgSearchUserInput" placeholder="Buscar usuario..." onkeyup="filterAvailableUsers()" class="form-control" style="border-radius:12px; font-size:13px; padding:0.6rem;">
            </div>
            <div class="msg-users-list" id="msgAvailableUsersList" style="max-height: 250px; overflow-y:auto;">
                <!-- User items here -->
            </div>
        </div>
    </div>
</div>

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

<!-- New Chat Modal -->
<div class="msg-modal-overlay" id="msgNewChatModal" style="display:none;">
    <div class="msg-modal">
        <div class="msg-modal-header">
            <h3>Nuevo Chat Grupal</h3>
            <button class="msg-icon-btn" onclick="closeNewChatModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="msg-modal-body">
            <div class="msg-form-group">
                <label for="newChatName">Nombre del grupo</label>
                <input type="text" id="newChatName" placeholder="Ej. Equipo de Diseño" autocomplete="off" onkeydown="if(event.key === 'Enter') submitNewChatModal()">
            </div>
        </div>
        <div class="msg-modal-footer">
            <button class="msg-btn-secondary" onclick="closeNewChatModal()">Cancelar</button>
            <button class="msg-btn-primary" onclick="submitNewChatModal()">Crear Chat</button>
        </div>
    </div>
</div>

<!-- Direct Message Modal -->
<div class="msg-modal-overlay" id="msgDirectMessageModal" style="display:none;">
    <div class="msg-modal" style="max-width: 450px;">
        <div class="msg-modal-header">
            <h3>Directorio de Usuarios</h3>
            <button class="msg-icon-btn" onclick="closeDirectMessageModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="msg-modal-body">
            <div class="msg-form-group" style="position: relative;">
                <i class="ph ph-magnifying-glass" style="position:absolute; left:12px; top:12px; color:var(--msg-text-muted);"></i>
                <input type="text" id="dmSearchInput" placeholder="Buscar usuario..." onkeyup="searchSystemUsers()" autocomplete="off" style="padding-left:36px;">
            </div>
            <div id="dmUsersList" class="msg-users-list" style="max-height: 300px; overflow-y:auto; margin-top: 1rem;">
                <div style="text-align:center; padding:1rem; color:var(--msg-text-muted); font-size:0.9rem;">Escribe para buscar o presiona buscar para ver todos.</div>
            </div>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div class="msg-modal-overlay" id="msgTaskModal" style="display:none;">
    <div class="msg-modal">
        <div class="msg-modal-header">
            <h3>Crear Tarea</h3>
            <button class="msg-icon-btn" onclick="closeTaskModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="msg-modal-body" style="max-height:60vh; overflow-y:auto;">
            <!-- Autofill Trap -->
            <input type="text" name="fake_username_trap" style="opacity:0; position:absolute; top:-100px; left:-100px; height:0; width:0; z-index:-1;" tabindex="-1" autocomplete="username">
            <input type="password" name="fake_password_trap" style="opacity:0; position:absolute; top:-100px; left:-100px; height:0; width:0; z-index:-1;" tabindex="-1" autocomplete="current-password">
            
            <div class="msg-form-group">
                <label for="taskTitleInput">Título de la tarea</label>
                <input type="text" id="taskTitleInput" placeholder="Ej. Rediseño de UI" autocomplete="new-password" data-lpignore="true" data-1p-ignore>
            </div>
            <div class="msg-form-group">
                <label for="taskSubtitleInput">Descripción (opcional)</label>
                <textarea id="taskSubtitleInput" placeholder="Ej. Se requiere una interfaz moderna..." rows="2" style="width:100%; border:1px solid var(--msg-border); border-radius:8px; padding:8px 12px; font-family:inherit; font-size:13px;" autocomplete="off" data-lpignore="true" data-1p-ignore></textarea>
            </div>
            <div class="msg-form-group">
                <label for="taskDueDateInput">Fecha de entrega</label>
                <input type="date" id="taskDueDateInput">
            </div>
            <div class="msg-form-group">
                <label for="taskPriorityInput">Prioridad</label>
                <select id="taskPriorityInput" style="width:100%; border:1px solid var(--msg-border); border-radius:8px; padding:8px 12px; font-family:inherit; font-size:13px; background:var(--msg-surface); color:var(--msg-text-main);">
                    <option value="low">Baja (Verde)</option>
                    <option value="medium" selected>Media (Amarillo)</option>
                    <option value="high">Alta (Rojo)</option>
                </select>
            </div>
            
            <div class="msg-form-group" style="margin-top: 16px;">
                <label style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Subtareas</span>
                    <button class="msg-btn-sm-outline" onclick="addSubtaskRow()"><i class="ph ph-plus"></i> Añadir</button>
                </label>
                <div id="msgTaskSubtasksContainer" style="display:flex; flex-direction:column; gap:8px; margin-top:8px;">
                    <!-- Subtask rows will go here -->
                </div>
            </div>
        </div>
        <div class="msg-modal-footer">
            <button class="msg-btn-secondary" onclick="closeTaskModal()">Cancelar</button>
            <button class="msg-btn-primary" onclick="submitTask()">Enviar Tarea</button>
        </div>
    </div>
</div>

<!-- Pendiente Modal -->
<div class="msg-modal-overlay" id="msgPendienteModal" style="display:none;">
    <div class="msg-modal" style="max-width:600px;">
        <div class="msg-modal-header">
            <h3><i class="ph ph-list-checks" style="color:#8b5cf6;"></i> Crear Pendiente</h3>
            <button class="msg-icon-btn" onclick="closePendienteModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="msg-modal-body" style="max-height:60vh; overflow-y:auto;">
            <!-- Autofill Trap -->
            <input type="text" name="fake_username_trap" style="opacity:0; position:absolute; top:-100px; left:-100px; height:0; width:0; z-index:-1;" tabindex="-1" autocomplete="username">
            <input type="password" name="fake_password_trap" style="opacity:0; position:absolute; top:-100px; left:-100px; height:0; width:0; z-index:-1;" tabindex="-1" autocomplete="current-password">
            
            <div class="msg-form-group" style="margin-bottom:12px;">
                <label for="pendienteTitleInput">Título</label>
                <input type="text" id="pendienteTitleInput" placeholder="Ej. Nuevo diseño de logo" autocomplete="off">
            </div>
            <div class="msg-form-group" style="margin-bottom:12px;">
                <label for="pendienteSubtitleInput">Subtítulo</label>
                <input type="text" id="pendienteSubtitleInput" placeholder="Ej. Logo para la marca principal" autocomplete="off">
            </div>
            <div class="msg-form-group" style="margin-bottom:12px;">
                <label for="pendienteDescInput">Descripción</label>
                <textarea id="pendienteDescInput" placeholder="Descripción detallada del pendiente..." rows="3" style="width:100%; border:1px solid var(--msg-border); border-radius:8px; padding:8px 12px; font-family:inherit; font-size:13px; background:var(--msg-bg); color:var(--msg-text-main); outline:none;"></textarea>
            </div>
            <div class="pendiente-grid-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div class="msg-form-group">
                    <label for="pendienteStatusInput">Estado</label>
                    <select id="pendienteStatusInput" style="width:100%; border:1px solid var(--msg-border); border-radius:8px; padding:8px 12px; font-family:inherit; font-size:13px; background:var(--msg-bg); color:var(--msg-text-main); outline:none;">
                        <option value="pending">Pendiente</option>
                        <option value="in_progress">En progreso</option>
                        <option value="completed">Completado</option>
                    </select>
                </div>
                <div class="msg-form-group">
                    <label for="pendienteDueDateInput">Fecha de entrega</label>
                    <input type="date" id="pendienteDueDateInput" style="width:100%; border:1px solid var(--msg-border); border-radius:8px; padding:8px 12px; font-family:inherit; font-size:13px; background:var(--msg-bg); color:var(--msg-text-main); outline:none;">
                </div>
            </div>
            <div class="pendiente-grid-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div class="msg-form-group">
                    <label for="pendientePriorityInput">Prioridad</label>
                    <select id="pendientePriorityInput" style="width:100%; border:1px solid var(--msg-border); border-radius:8px; padding:8px 12px; font-family:inherit; font-size:13px; background:var(--msg-bg); color:var(--msg-text-main); outline:none;">
                        <option value="low">Baja</option>
                        <option value="medium" selected>Media</option>
                        <option value="high">Alta</option>
                    </select>
                </div>
                <div class="msg-form-group">
                    <label for="pendienteTypeInput">Tipo (Impreso/Digital)</label>
                    <select id="pendienteTypeInput" style="width:100%; border:1px solid var(--msg-border); border-radius:8px; padding:8px 12px; font-family:inherit; font-size:13px; background:var(--msg-bg); color:var(--msg-text-main); outline:none;">
                        <option value="digital" selected>Digital</option>
                        <option value="impreso">Impreso</option>
                    </select>
                </div>
            </div>
            <div class="msg-form-group" style="margin-bottom:12px;">
                <label for="pendienteSizeInput">Tamaño del diseño</label>
                <input type="text" id="pendienteSizeInput" placeholder="Ej. 1080x1080px, A4, etc." autocomplete="off">
            </div>
            <div class="msg-form-group">
                <label for="pendienteRefsInput">Referencias (imágenes o documentos)</label>
                <input type="file" id="pendienteRefsInput" multiple style="width:100%; font-size:13px;" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
            </div>
        </div>
        <div class="msg-modal-footer">
            <button class="msg-btn-secondary" onclick="closePendienteModal()">Cancelar</button>
            <button class="msg-btn-primary" onclick="submitPendiente()" style="background:linear-gradient(135deg, #8b5cf6, #7c3aed);">Crear Pendiente</button>
        </div>
    </div>
</div>

<!-- Whiteboard (Pizarra) Modal -->
<div class="msg-modal-overlay" id="msgPizarraModal" style="display:none;">
    <div class="msg-modal">
        <div class="msg-modal-header">
            <h3>Enviar Pizarra</h3>
            <button class="msg-modal-close" onclick="closeWhiteboardModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="msg-modal-body">
            <div class="msg-form-group" style="margin-bottom:12px;">
                <label>Selecciona una pizarra existente</label>
                <select id="pizarraSelectInput" style="width:100%; padding:10px; border-radius:8px; border:1px solid var(--msg-border);">
                    <option value="">Cargando pizarras...</option>
                </select>
            </div>
            
            <div style="text-align:center; margin: 16px 0; color: var(--msg-text-muted); font-size: 13px;">— O —</div>
            
            <div class="msg-form-group" style="margin-bottom:12px;">
                <label for="pizarraNewInput">Crear Nueva Pizarra</label>
                <input type="text" id="pizarraNewInput" placeholder="Nombre de la nueva pizarra..." autocomplete="off">
            </div>
        </div>
        <div class="msg-modal-footer">
            <button class="msg-btn-secondary" onclick="closeWhiteboardModal()">Cancelar</button>
            <button class="msg-btn-primary" onclick="submitWhiteboardAttach()" style="background:linear-gradient(135deg, #06b6d4, #0891b2);">Enviar Pizarra</button>
        </div>
    </div>
</div>

<script>
    const CURRENT_USER_ID = <?php echo $current_user_id; ?>;
    const CURRENT_USER_NAME = <?php echo json_encode($current_user_name); ?>;

</script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script><script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script src="modules/mensajes/mensajes_v5.js?v=<?php echo time(); ?>"></script>
<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@1.21.3/index.js"></script>
<script>
document.addEventListener('contextmenu', e => {
    if (!e.target.closest('#msgArea') && !e.target.closest('.msg-chat-item')) {
        e.preventDefault();
    }
});

function openChatPopup() {
    const url = 'index.php?module=mensajes&action=index&popup=1';
    window.open(url, 'RomaMensajesPopup', 'width=1000,height=700,menubar=no,toolbar=no,location=no,status=no');
}
</script>

<?php include 'includes/footer.php'; ?>
