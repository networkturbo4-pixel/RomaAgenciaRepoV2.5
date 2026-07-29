<?php
// modules/chat/index.php
require_once 'includes/header.php';

// Fetch current user details for chat (avatar, vip, background, spotify)
$stmtUser = $db->prepare("SELECT avatar, is_vip, bg_preference, spotify_token FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$currentUserData = $stmtUser->fetch(PDO::FETCH_ASSOC);
$currentUserAvatar = $currentUserData['avatar'] ?? null;
$currentUserVip = $currentUserData['is_vip'] ?? 0;
$currentUserBg = $currentUserData['bg_preference'] ?? 'default';
$currentUserSpotify = $currentUserData['spotify_token'] ?? null;

// Fetch all users for DM/channel creation
$stmtAllUsers = $db->query("SELECT id, name, avatar, is_vip FROM users ORDER BY name ASC");
$allUsers = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="modules/chat/chat.css?v=<?php echo time(); ?>">

<div id="chat-app" class="chat-app">
    <!-- Sidebar Panel -->
    <aside class="chat-sidebar" id="chat-sidebar">
        <div class="chat-sidebar-header">
            <div class="chat-header-info">
                <h2>Chats</h2>
                <button class="chat-icon-btn-sm" id="btn-amoled-toggle" title="Modo AMOLED Puro"><i class="ph ph-moon-stars"></i></button>
                <button class="chat-icon-btn-sm" id="btn-chat-settings" title="Ajustes de Chat (Fondo / Spotify)"><i class="ph ph-gear"></i></button>
            </div>
            <button class="chat-icon-btn d-md-none-chat" id="btn-close-chat-sidebar" title="Cerrar"><i class="ph ph-arrow-left"></i></button>
        </div>

        <div class="chat-filters">
            <button class="chat-filter-pill active" data-filter="all">Todos</button>
            <button class="chat-filter-pill" data-filter="group">Grupos</button>
            <button class="chat-filter-pill" data-filter="direct">Directos</button>
        </div>

        <div class="chat-sidebar-pane active" id="pane-unified">
            <div class="chat-section-title">
                <span>Conversaciones</span>
                <div>
                    <button class="chat-icon-btn-sm" id="btn-new-group" title="Nuevo Grupo"><i class="ph ph-users-three"></i></button>
                    <button class="chat-icon-btn-sm" id="btn-new-dm" title="Nuevo DM"><i class="ph ph-user-plus"></i></button>
                </div>
            </div>
            <div id="channel-list-unified" class="channel-list"></div>
        </div>
    </aside>

    <!-- Main Chat Panel -->
    <main class="chat-main" id="chat-main">
        <!-- Chat Header -->
        <div class="chat-header" id="chat-header">
            <button class="chat-icon-btn d-md-none-chat" id="btn-back-chat">
                <i class="ph ph-arrow-left"></i>
            </button>
            <div class="chat-header-info">
                <div id="chat-header-avatar" class="chat-header-avatar" style="display:none;"></div>
                <div>
                    <h3 id="chat-channel-name">Selecciona un chat</h3>
                    <span id="chat-channel-meta" class="chat-meta" style="display:none;"></span>
                </div>
            </div>
            
            <div class="chat-header-actions" style="display:flex; gap:0.5rem; align-items:center;">
                <button class="chat-icon-btn-sm" id="btn-group-info" title="Información del Chat"><i class="ph ph-info"></i></button>
                <button class="chat-icon-btn" id="btn-chat-bg" title="Cambiar Fondo" style="display:none;"><i class="ph ph-paint-roller"></i></button>
            </div>
        </div>

        <!-- Pinned Messages Bar -->
        <div id="chat-pinned-bar" class="chat-pinned-bar" style="display:none;">
            <i class="ph ph-push-pin" style="color:var(--primary-color);"></i>
            <span id="pinned-bar-text" style="flex:1; font-size:0.8rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></span>
            <button class="chat-icon-btn-sm" id="btn-show-pinned" title="Ver todos"><i class="ph ph-caret-down"></i></button>
        </div>

        <!-- Typing Indicator -->
        <div id="chat-typing-indicator" class="chat-typing-indicator" style="display:none;">
            <div class="typing-dots"><span></span><span></span><span></span></div>
            <span id="typing-names"></span>
        </div>

        <!-- Jitsi Container -->
        <div id="jitsi-container" style="display:none; flex:1; width:100%; height:100%;"></div>
        
        <!-- Messages Area -->
        <div class="chat-messages" id="chat-messages" style="display:none;"></div>
        
        <!-- Skeleton Loader -->
        <div id="chat-skeleton" class="chat-skeleton" style="display:none;">
            <div class="skeleton-msg"><div class="skeleton-avatar"></div><div class="skeleton-lines"><div class="skeleton-line" style="width:30%;"></div><div class="skeleton-line" style="width:80%;"></div><div class="skeleton-line" style="width:60%;"></div></div></div>
            <div class="skeleton-msg own"><div class="skeleton-lines"><div class="skeleton-line" style="width:40%;"></div><div class="skeleton-line" style="width:70%;"></div></div></div>
            <div class="skeleton-msg"><div class="skeleton-avatar"></div><div class="skeleton-lines"><div class="skeleton-line" style="width:25%;"></div><div class="skeleton-line" style="width:90%;"></div></div></div>
        </div>
        
        <div class="chat-empty-state" id="chat-empty-state">
            <i class="ph ph-chat-circle-dots"></i>
            <h3>Bienvenido al Chat</h3>
            <p>Selecciona un canal o conversación para empezar</p>
        </div>

        <!-- Scroll to Bottom -->
        <button id="btn-scroll-bottom" class="btn-scroll-bottom" style="display:none;">
            <span id="new-msg-count" class="new-msg-count" style="display:none;">0</span>
            <i class="ph ph-caret-down"></i>
        </button>

        <!-- Drag & Drop Overlay -->
        <div id="chat-drop-overlay" class="chat-drop-overlay" style="display:none;">
            <div class="drop-overlay-content">
                <i class="ph ph-upload-simple" style="font-size:3rem;"></i>
                <p>Suelta el archivo aquí</p>
            </div>
        </div>

        <!-- Multimedia Lightbox -->
        <div id="chat-multimedia-lightbox" class="chat-multimedia-lightbox" style="display:none;">
            <div class="lightbox-header">
                <div class="lightbox-title" id="lightbox-title">Archivo</div>
                <div class="lightbox-actions">
                    <button class="chat-icon-btn" id="btn-lightbox-download" title="Descargar"><i class="ph ph-download-simple"></i></button>
                    <button class="chat-icon-btn" id="btn-lightbox-close" title="Cerrar"><i class="ph ph-x"></i></button>
                </div>
            </div>
            <div class="lightbox-body" id="lightbox-body">
                <!-- Content injected here (img, video, iframe) -->
            </div>
        </div>

        <!-- Drive Folder Picker Modal -->
        <div class="modal fade" id="drivePickerModal" tabindex="-1" style="z-index:1060;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius:16px;">
                    <div class="modal-header">
                        <h5 class="modal-title">Seleccionar Carpeta de Drive</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div id="drive-picker-breadcrumbs" style="padding:0.75rem 1rem; border-bottom:1px solid var(--border-color); font-size:0.85rem; background:var(--bg-surface);">
                            <span class="drive-breadcrumb" data-id="root" style="cursor:pointer; color:var(--primary-color);">Mi Unidad</span>
                        </div>
                        <div id="drive-picker-list" style="height:300px; overflow-y:auto; padding:0.5rem;">
                            <!-- Folders here -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btn-confirm-drive-folder">Seleccionar Aquí</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="chat-input-area" id="chat-input-area" style="display:none;">
            <div id="reply-preview-box" style="display:none;">
                <div class="reply-preview-content">
                    <div class="reply-preview-name" id="reply-preview-name"></div>
                    <div class="reply-preview-text" id="reply-preview-text"></div>
                </div>
                <button class="btn-close-reply" id="btn-close-reply"><i class="ph ph-x"></i></button>
            </div>
            <div class="chat-input-wrapper">
                <div class="chat-input-actions" style="margin-right: 0.5rem; position: relative;">
                    <button class="chat-icon-btn" id="btn-attachment-menu" title="Adjuntar">
                        <i class="ph ph-plus"></i>
                    </button>
                    <!-- Attachment Menu Popup -->
                    <div class="attachment-popup-menu" id="attachment-popup-menu">
                        <div class="attachment-menu-item" id="menu-item-document">
                            <div class="attachment-menu-icon" style="background: #7c3aed;"><i class="ph-fill ph-file-text"></i></div>
                            <span>Documento</span>
                        </div>
                        <div class="attachment-menu-item" id="menu-item-photo">
                            <div class="attachment-menu-icon" style="background: #0ea5e9;"><i class="ph-fill ph-image"></i></div>
                            <span>Fotos y videos</span>
                        </div>
                        <div class="attachment-menu-item" id="menu-item-widget">
                            <div class="attachment-menu-icon" style="background: #10b981;"><i class="ph-fill ph-squares-four"></i></div>
                            <span>Widgets</span>
                        </div>
                        <div class="attachment-menu-item" id="menu-item-poll">
                            <div class="attachment-menu-icon" style="background: #eab308;"><i class="ph-fill ph-chart-bar"></i></div>
                            <span>Encuesta</span>
                        </div>
                        <div class="attachment-menu-item" id="menu-item-task">
                            <div class="attachment-menu-icon" style="background: #f97316;"><i class="ph-fill ph-check-square"></i></div>
                            <span>Lista de Tareas</span>
                        </div>
                    </div>
                    <button class="chat-icon-btn-sm" id="btn-attach-file" style="display:none;"></button>
                    <button class="chat-icon-btn-sm" id="btn-share-card" style="display:none;"></button>
                    <button class="chat-icon-btn-sm" id="btn-create-poll-modal" style="display:none;"></button>
                    <button class="chat-icon-btn-sm" id="btn-create-task-modal" style="display:none;"></button>
                </div>
                <input type="file" id="file-input" multiple style="display:none;">
                
                <textarea id="chat-input" placeholder="Mensaje" rows="1"></textarea>
                
                <div class="chat-recording-ui" id="chat-recording-ui" style="display:none;">
                    <button class="chat-icon-btn-sm" id="btn-cancel-recording" title="Cancelar" style="color:var(--danger-color);"><i class="ph ph-trash"></i></button>
                    <div class="recording-indicator">
                        <div class="recording-dot"></div>
                        <span id="recording-time">0:00</span>
                    </div>
                    <div class="audio-waveform">
                        <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                    </div>
                </div>
                
                <div class="chat-input-actions">
                    <button class="chat-icon-btn" id="btn-emoji-picker" title="Emojis"><i class="ph ph-smiley"></i></button>
                    <button class="chat-icon-btn" id="btn-voice-msg" title="Nota de voz"><i class="ph-fill ph-microphone"></i></button>
                    <button class="chat-icon-btn btn-send-msg" id="btn-send" title="Enviar" style="display:none;">
                        <i class="ph-fill ph-paper-plane-right"></i>
                    </button>
                </div>
            </div>
            <div class="chat-file-preview" id="chat-file-preview" style="display:none;">
                <div id="file-preview-list"></div>
                <button class="chat-icon-btn-sm" id="btn-remove-file"><i class="ph ph-x"></i></button>
            </div>
        </div>
    </main>

    <!-- Right Sidebar (Chat Info) -->
    <!-- Chat Info Panel -->
    <aside class="chat-info-panel" id="chat-info-panel">
        <div class="chat-info-header">
            <button class="chat-icon-btn-sm" id="btn-close-info"><i class="ph ph-x"></i></button>
            <h3>Info. del chat</h3>
        </div>
        <div class="chat-info-body">
            <!-- Top Section (Avatar & Name) -->
            <div style="text-align:center; margin-bottom:2rem; position:relative;">
                <div id="crs-avatar-container" style="position:relative; width:140px; height:140px; margin:0 auto 1rem;">
                    <div id="crs-avatar" style="width:100%; height:100%; border-radius:50%; background:var(--primary-color); display:flex; align-items:center; justify-content:center; color:white; font-size:3.5rem; background-size:cover; background-position:center;"></div>
                    <div id="crs-avatar-edit-overlay" style="display:none; position:absolute; bottom:0; right:0; background:var(--primary-color); color:white; width:36px; height:36px; border-radius:50%; align-items:center; justify-content:center; cursor:pointer; border:2px solid var(--bg-surface); box-shadow:0 2px 5px rgba(0,0,0,0.2);" onclick="document.getElementById('crs-avatar-input').click()">
                        <i class="ph ph-camera"></i>
                    </div>
                    <input type="file" id="crs-avatar-input" accept="image/*" style="display:none;">
                </div>
                
                <div style="display:flex; align-items:center; justify-content:center; gap:0.5rem; margin-bottom:0.5rem;">
                    <h2 id="crs-name" style="margin:0; font-size:1.4rem; color:var(--text-main);">Nombre</h2>
                    <i class="ph ph-pencil-simple crs-edit-icon" id="btn-edit-crs-name" style="display:none; color:var(--text-muted); cursor:pointer;"></i>
                </div>
                <div id="crs-meta" style="color:var(--primary-color); font-size:0.9rem; font-weight:600;"></div>
            </div>
            
            <!-- Description -->
            <div id="crs-desc-section" style="margin-bottom:2rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <div style="color:var(--text-muted); font-size:0.8rem; font-weight:600; text-transform:uppercase;">Descripción</div>
                    <i class="ph ph-pencil-simple crs-edit-icon" id="btn-edit-crs-desc" style="display:none; color:var(--text-muted); cursor:pointer;"></i>
                </div>
                <div id="crs-desc" style="font-size:0.95rem; color:var(--text-main); line-height:1.5; padding:1rem; background:color-mix(in srgb, var(--primary-color) 5%, transparent); border-radius:8px;">Sin descripción</div>
            </div>

            <!-- Google Drive Config -->
            <div id="crs-drive-section" style="margin-bottom:2rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <div style="color:var(--text-muted); font-size:0.8rem; font-weight:600; text-transform:uppercase;">Almacenamiento (Google Drive)</div>
                </div>
                <div style="padding:1rem; background:var(--bg-color); border:1px solid var(--border-color); border-radius:8px;">
                    <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.75rem;">Los archivos enviados a este chat se guardarán en esta carpeta.</p>
                    <div style="display:flex; gap:0.5rem; flex-direction:column;">
                        <input type="hidden" id="crs-drive-folder-id">
                        <input type="text" id="crs-drive-folder-name" class="form-control" readonly placeholder="No configurada" style="font-size:0.85rem;">
                        <button class="btn btn-outline-primary w-100" id="btn-select-drive-folder" style="font-size:0.85rem;"><i class="ph ph-folder"></i> Seleccionar Carpeta</button>
                    </div>
                </div>
            </div>

            <!-- Public Group & Link (Groups Only) -->
            <div id="crs-public-section" style="display:none; margin-bottom:2rem;">
                <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;">
                    <input type="checkbox" id="crs-is-public" style="width:18px; height:18px; cursor:pointer;">
                    <div>
                        <div style="font-weight:600; color:var(--text-main);">Grupo Público</div>
                        <div style="font-size:0.8rem; color:var(--text-muted);">Cualquiera con el enlace podrá unirse.</div>
                    </div>
                </div>
                <div id="crs-link-area" style="display:none; padding:1rem; background:color-mix(in srgb, var(--primary-color) 10%, transparent); border-radius:8px;">
                    <div style="font-size:0.8rem; color:var(--primary-color); margin-bottom:0.5rem;">Enlace Público de Invitación</div>
                    <div style="display:flex; gap:0.5rem;">
                        <input type="text" id="crs-public-link" class="form-control" readonly style="flex:1; background:var(--bg-color); border:1px solid var(--primary-color); color:var(--primary-color); font-size:0.85rem;">
                        <button class="btn btn-primary" id="btn-copy-crs-link" title="Copiar"><i class="ph ph-copy"></i></button>
                    </div>
                </div>
            </div>

            <!-- Members (Groups Only) -->
            <div id="crs-members-section" style="display:none; margin-bottom:2rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <div style="color:var(--text-muted); font-size:0.8rem; font-weight:600; text-transform:uppercase;">Miembros</div>
                    <button class="btn-icon-sm" id="btn-crs-add-members" style="color:var(--primary-color); display:flex; align-items:center; gap:0.25rem; background:none; border:none; cursor:pointer; font-weight:600;"><i class="ph ph-user-plus"></i> Añadir</button>
                </div>
                <div id="crs-members-list" style="display:flex; flex-direction:column; gap:0.5rem;">
                    <!-- Members injected here -->
                </div>
            </div>

            <!-- Media Section -->
            <div style="margin-bottom:2rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; cursor:pointer;" title="Ver todos los archivos">
                    <div style="display:flex; align-items:center; gap:0.5rem; color:var(--text-main); font-weight:600; font-size:0.95rem;">
                        <i class="ph ph-images" style="font-size:1.2rem; color:var(--text-muted);"></i> Archivos, enlaces y documentos
                    </div>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span id="crs-media-count" style="color:var(--text-muted); font-size:0.8rem;">0</span>
                        <i class="ph ph-caret-right" style="color:var(--text-muted);"></i>
                    </div>
                </div>
                <div id="crs-media" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:0.5rem;">
                    <!-- Placeholder Media -->
                </div>
            </div>
        </div>
    </aside>
</div>

<!-- Group Manager Modal -->
<div class="modal-overlay" id="group-manager-modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h2 id="group-manager-title">Nuevo Grupo</h2>
            <button class="btn-icon btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="group-manager-id" value="">
            
            <div class="form-group" style="display:flex; flex-direction:column; align-items:center; margin-bottom:1.5rem;">
                <div id="group-avatar-preview" style="width:80px; height:80px; border-radius:50%; background:var(--bg-surface); border:2px dashed var(--border-color); display:flex; align-items:center; justify-content:center; cursor:pointer; overflow:hidden; position:relative; color:var(--text-muted); font-size:1.5rem; transition:all var(--transition-fast);">
                    <i class="ph ph-camera"></i>
                    <img id="group-avatar-img" src="" style="width:100%; height:100%; object-fit:cover; position:absolute; top:0; left:0; display:none;">
                </div>
                <input type="file" id="group-avatar-input" accept="image/*" style="display:none;">
                <button class="btn btn-outline" style="margin-top:0.5rem; padding:0.25rem 0.5rem; font-size:0.75rem;" onclick="document.getElementById('group-avatar-input').click()">Cambiar Foto</button>
            </div>

            <div class="form-group">
                <label class="form-label" style="display:block; margin-bottom:0.5rem;">Tipo de Canal</label>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.5rem; margin-bottom:1rem;">
                    
                    <label style="cursor:pointer;">
                        <input type="radio" name="group-type" value="group" checked style="display:none;">
                        <div class="type-card" style="border:2px solid var(--primary-color); border-radius:var(--radius-md); padding:1rem; text-align:center; background:color-mix(in srgb, var(--primary-color) 10%, transparent);">
                            <i class="ph ph-chat-circle-dots" style="font-size:1.5rem; color:var(--primary-color);"></i>
                            <div style="font-size:0.8rem; font-weight:600; margin-top:0.3rem;">Texto</div>
                        </div>
                    </label>
                    
                    <label style="cursor:pointer;">
                        <input type="radio" name="group-type" value="voice" style="display:none;">
                        <div class="type-card" style="border:2px solid var(--border-color); border-radius:var(--radius-md); padding:1rem; text-align:center;">
                            <i class="ph ph-headphones" style="font-size:1.5rem; color:var(--text-muted);"></i>
                            <div style="font-size:0.8rem; font-weight:600; margin-top:0.3rem; color:var(--text-muted);">Voz</div>
                        </div>
                    </label>
                    
                    <label style="cursor:pointer;">
                        <input type="radio" name="group-type" value="video" style="display:none;">
                        <div class="type-card" style="border:2px solid var(--border-color); border-radius:var(--radius-md); padding:1rem; text-align:center;">
                            <i class="ph ph-video-camera" style="font-size:1.5rem; color:var(--text-muted);"></i>
                            <div style="font-size:0.8rem; font-weight:600; margin-top:0.3rem; color:var(--text-muted);">Video (Meet)</div>
                        </div>
                    </label>
                </div>
                
                <script>
                    document.querySelectorAll('input[name="group-type"]').forEach(radio => {
                        radio.addEventListener('change', function() {
                            document.querySelectorAll('.type-card').forEach(card => {
                                card.style.borderColor = 'var(--border-color)';
                                card.style.background = 'transparent';
                                card.querySelector('i').style.color = 'var(--text-muted)';
                                card.querySelector('div').style.color = 'var(--text-muted)';
                            });
                            const card = this.nextElementSibling;
                            card.style.borderColor = 'var(--primary-color)';
                            card.style.background = 'color-mix(in srgb, var(--primary-color) 10%, transparent)';
                            card.querySelector('i').style.color = 'var(--primary-color)';
                            card.querySelector('div').style.color = 'var(--text-main)';
                        });
                    });
                </script>
            </div>
            
            <div class="form-group">
                <label class="form-label">Nombre del Grupo</label>
                <input type="text" id="group-name" class="form-control" placeholder="ej: Marketing, Soporte...">
            </div>
            <div class="form-group">
                <label class="form-label">Descripción (opcional)</label>
                <input type="text" id="group-desc" class="form-control" placeholder="¿De qué trata este grupo?">
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
                <input type="checkbox" id="group-is-public" style="width:18px; height:18px; cursor:pointer;">
                <div>
                    <label for="group-is-public" style="font-weight:600; cursor:pointer;">Grupo Público</label>
                    <div style="font-size:0.8rem; color:var(--text-muted);">Cualquiera con el enlace podrá unirse, incluso invitados externos.</div>
                </div>
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
                <input type="checkbox" id="group-requires-approval" style="width:18px; height:18px; cursor:pointer;">
                <div>
                    <label for="group-requires-approval" style="font-weight:600; cursor:pointer;">Aprobación requerida</label>
                    <div style="font-size:0.8rem; color:var(--text-muted);">Los nuevos miembros irán a una sala de espera.</div>
                </div>
            </div>
            
            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
                <input type="checkbox" id="group-is-secret" style="width:18px; height:18px; cursor:pointer;" onchange="document.getElementById('group-password-container').style.display = this.checked ? 'block' : 'none'">
                <div>
                    <label for="group-is-secret" style="font-weight:600; cursor:pointer;">Chat Secreto (Bóveda)</label>
                    <div style="font-size:0.8rem; color:var(--text-muted);">Protege este chat con una contraseña.</div>
                </div>
            </div>
            <div id="group-password-container" style="display:none; margin-bottom:1rem; margin-left:2rem;">
                <input type="text" id="group-secret-password" class="form-control" placeholder="Contraseña de la bóveda">
            </div>

            <div id="group-link-area" style="display:none; margin-bottom:1rem; padding:1rem; background:color-mix(in srgb, var(--primary-color) 10%, transparent); border-radius:var(--radius-md);">
                <label class="form-label" style="font-size:0.8rem; color:var(--primary-color);">Enlace Público de Invitación</label>
                <div style="display:flex; gap:0.5rem;">
                    <input type="text" id="group-public-link" class="form-control" readonly style="background:var(--bg-color); border:1px solid var(--primary-color); color:var(--primary-color);">
                    <button class="btn btn-primary" id="btn-copy-group-link" title="Copiar"><i class="ph ph-copy"></i></button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Miembros</label>
                <div style="max-height:200px; overflow-y:auto; border:1px solid var(--border-color); border-radius:var(--radius-md); padding:0.75rem;">
                    <?php foreach ($allUsers as $u): ?>
                    <label style="display:flex; align-items:center; gap:0.5rem; padding:0.3rem 0; cursor:pointer;">
                        <input type="checkbox" class="group-member-cb" value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $_SESSION['user_id'] ? 'checked disabled data-force-checked="1"' : ''; ?>>
                        <div class="chat-avatar-sm" style="background:<?php echo ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'][$u['id'] % 6]; ?>; width:24px; height:24px; font-size:0.7rem; display:flex; align-items:center; justify-content:center; color:white; border-radius:50%;">
                            <?php echo strtoupper(substr($u['name'],0,1)); ?>
                        </div>
                        <span><?php echo htmlspecialchars($u['name']); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display:flex; justify-content:space-between;">
            <button class="btn btn-outline" id="btn-delete-group" style="color:var(--danger-color); border-color:var(--danger-color); display:none;"><i class="ph ph-trash"></i> Eliminar</button>
            <div style="display:flex; gap:0.5rem; margin-left:auto;">
                <button class="btn btn-outline btn-close-modal">Cancelar</button>
                <button class="btn btn-primary" id="btn-save-group">Guardar Grupo</button>
            </div>
        </div>
    </div>
</div>

<!-- New DM Modal -->
<div class="modal-overlay" id="new-dm-modal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h2>Mensaje Directo</h2>
            <button class="btn-icon btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Selecciona un usuario</label>
                <div style="max-height:300px; overflow-y:auto;">
                    <?php foreach ($allUsers as $u): if ($u['id'] == $_SESSION['user_id']) continue; ?>
                    <button class="dm-user-btn" data-user-id="<?php echo $u['id']; ?>">
                        <div class="chat-avatar-sm" style="background:<?php echo ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'][$u['id'] % 6]; ?>">
                            <?php echo strtoupper(substr($u['name'],0,1)); ?>
                        </div>
                        <span><?php echo htmlspecialchars($u['name']); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Share Card Modal -->
<div class="modal-overlay" id="share-card-modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h2>Compartir en Chat</h2>
            <button class="btn-icon btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <div class="card-type-tabs">
                <?php $perms = $_SESSION['user_permissions'] ?? []; ?>
                <?php if (in_array('clients', $perms)): ?>
                <button class="card-type-tab active" data-type="client"><i class="ph ph-user"></i> Cliente</button>
                <?php endif; ?>
                <?php if (in_array('quotes', $perms)): ?>
                <button class="card-type-tab <?php echo !in_array('clients', $perms) ? 'active' : ''; ?>" data-type="quote"><i class="ph ph-file-text"></i> Cotización</button>
                <?php endif; ?>
                <?php if (in_array('services', $perms)): ?>
                <button class="card-type-tab <?php echo !in_array('clients', $perms) && !in_array('quotes', $perms) ? 'active' : ''; ?>" data-type="service"><i class="ph ph-package"></i> Servicio</button>
                <?php endif; ?>
                <?php if (in_array('calendar', $perms) || in_array('projects', $perms)): ?>
                <button class="card-type-tab <?php echo !in_array('clients', $perms) && !in_array('quotes', $perms) && !in_array('services', $perms) ? 'active' : ''; ?>" data-type="month"><i class="ph ph-calendar"></i> Mes</button>
                <button class="card-type-tab" data-type="presentation"><i class="ph ph-presentation-chart"></i> Presentación</button>
                <button class="card-type-tab" data-type="post"><i class="ph ph-image"></i> Publicación</button>
                <?php endif; ?>
                <?php if (in_array('design_tasks', $perms) || true): ?>
                <button class="card-type-tab" data-type="task"><i class="ph ph-palette"></i> Diseño</button>
                <?php endif; ?>
            </div>
            <input type="text" id="card-search-input" class="form-control" placeholder="Buscar..." style="margin-top:0.75rem;">
            <div id="card-search-results" class="card-search-results"></div>
        </div>
    </div>
</div>

<!-- Search Modal (Ctrl+K) -->
<div class="modal-overlay" id="search-modal">
    <div class="modal-content" style="max-width:560px;">
        <div class="modal-header" style="border-bottom:1px solid var(--border-color);">
            <div style="display:flex; align-items:center; gap:0.5rem; flex:1;">
                <i class="ph ph-magnifying-glass" style="font-size:1.2rem; color:var(--text-muted);"></i>
                <input type="text" id="global-search-input" class="form-control" placeholder="Buscar mensajes..." style="border:none; box-shadow:none; padding:0.5rem; font-size:1rem;">
            </div>
            <kbd style="font-size:0.7rem; background:var(--bg-color); border:1px solid var(--border-color); border-radius:4px; padding:0.15rem 0.4rem; color:var(--text-muted);">ESC</kbd>
        </div>
        <div class="modal-body" style="max-height:400px; overflow-y:auto; padding:0;">
            <div id="search-results" style="padding:0.5rem;"></div>
        </div>
    </div>
</div>

<!-- Context Menu -->
<div id="chat-context-menu" class="chat-context-menu" style="display:none;">
    <button class="ctx-item" data-action="reply"><i class="ph ph-arrow-bend-up-left"></i> Responder</button>
    <button class="ctx-item" data-action="react"><i class="ph ph-smiley"></i> Reaccionar</button>
    <button class="ctx-item" data-action="pin"><i class="ph ph-push-pin"></i> Fijar mensaje</button>
    <button class="ctx-item" data-action="copy"><i class="ph ph-copy"></i> Copiar texto</button>
    <button class="ctx-item" data-action="edit"><i class="ph ph-pencil"></i> Editar</button>
    <button class="ctx-item" data-action="delete" style="color:var(--danger-color);"><i class="ph ph-trash"></i> Eliminar</button>
</div>

<!-- Channel Context Menu -->
<div id="channel-context-menu" class="chat-context-menu" style="display:none;">
    <button class="ctx-channel-item" data-action="pin"><i class="ph ph-push-pin"></i> <span class="ctx-pin-text">Fijar chat</span></button>
    <button class="ctx-channel-item" data-action="archive"><i class="ph ph-archive"></i> Archivar</button>
    <button class="ctx-channel-item" data-action="delete" style="color:var(--danger-color);"><i class="ph ph-trash"></i> Eliminar</button>
</div>

<!-- Emoji Full Picker -->
<emoji-picker id="emoji-quick-picker" style="display:none; position:fixed; z-index:9999; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-radius: 8px;"></emoji-picker>

<!-- Image Send Preview Modal -->
<div id="image-send-modal" class="modal-overlay">
    <div class="modal-content" style="max-width: 500px; padding: 1.5rem; background: var(--bg-surface); border-radius: var(--radius-lg); color: var(--text-main);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0; color: var(--text-main);">Enviar Imagen</h3>
            <button class="btn-close-modal" onclick="closeImageSendModal()" style="background: none; border: none; cursor: pointer; font-size: 1.2rem; color: var(--text-main);"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
            <img id="image-send-preview" src="" style="max-width: 100%; max-height: 400px; border-radius: 8px; object-fit: contain; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            <div style="display: flex; width: 100%; gap: 0.5rem; align-items: center;">
                <input type="text" id="image-send-caption" class="chat-input" placeholder="Añade un comentario (opcional)..." style="flex: 1; padding: 0.8rem 1rem; border-radius: 24px; border: 1px solid var(--border-color); background: transparent; color: var(--text-main); outline: none;">
                <button class="btn-primary" id="btn-image-send-confirm" style="border-radius: 50%; width: 44px; height: 44px; padding: 0; display: flex; align-items: center; justify-content: center; background: var(--primary-color); border: none; color: white; cursor: pointer; transition: transform 0.2s;">
                    <i class="ph ph-paper-plane-right" style="font-size: 1.2rem;"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Image Lightbox -->
<div id="image-lightbox" class="lightbox-overlay" style="display:none;">
    <button class="lightbox-close" onclick="closeLightbox()"><i class="ph ph-x"></i></button>
    <button class="lightbox-prev" onclick="prevLightboxImage()"><i class="ph ph-caret-left"></i></button>
    <img id="lightbox-img" class="lightbox-img" src="" alt="Vista previa">
    <button class="lightbox-next" onclick="nextLightboxImage()"><i class="ph ph-caret-right"></i></button>
</div>

<!-- Create Poll Modal -->
<div class="modal-overlay" id="create-poll-modal">
    <div class="modal-content" style="max-width:550px; padding:2rem;">
        <div class="modal-header" style="border:none; padding:0; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.2rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
                <button class="chat-icon-btn btn-close-modal" data-target="create-poll-modal" style="margin-left:-0.5rem;"><i class="ph ph-x"></i></button>
                Crea una encuesta
            </h3>
        </div>
        
        <div class="modal-body" style="padding:0; display:flex; flex-direction:column; gap:2rem;">
            <div>
                <label style="display:block; font-weight:600; font-size:1rem; margin-bottom:1rem; color:var(--text-main);">Pregunta</label>
                <div style="position:relative;">
                    <input type="text" id="poll-question" placeholder="Haz una pregunta" class="chat-input" style="width:100%; border:1px solid color-mix(in srgb, var(--text-main) 15%, transparent); border-radius:4px; padding:0.6rem 2.5rem 0.6rem 0.75rem; background:transparent; font-size:1rem; outline:none; box-shadow:none; color:var(--text-main);">
                    <i class="ph ph-smiley" style="position:absolute; right:0.5rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:1.4rem; cursor:pointer;"></i>
                </div>
            </div>

            <div>
                <label style="display:block; font-weight:600; font-size:0.95rem; margin-bottom:0.75rem; color:var(--text-main);">Opciones</label>
                <div id="poll-options-container" style="display:flex; flex-direction:column; gap:0.5rem;">
                    <!-- Default 2 options -->
                    <div class="poll-option-row" style="position:relative; display:flex; align-items:center; gap:0.5rem;">
                        <div style="position:relative; flex:1;">
                            <input type="text" placeholder="Añade texto" class="chat-input poll-option-input" style="width:100%; border:1px solid color-mix(in srgb, var(--text-main) 15%, transparent); border-radius:4px; padding:0.6rem 2.5rem 0.6rem 0.75rem; background:transparent; font-size:0.95rem; outline:none; box-shadow:none; color:var(--text-main);">
                            <i class="ph ph-smiley" style="position:absolute; right:0.5rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:1.2rem; cursor:pointer;"></i>
                        </div>
                        <i class="ph ph-equals" style="color:var(--text-muted); cursor:grab; font-size:1.2rem;"></i>
                    </div>
                    <div class="poll-option-row" style="position:relative; display:flex; align-items:center; gap:0.5rem;">
                        <div style="position:relative; flex:1;">
                            <input type="text" placeholder="Añade texto" class="chat-input poll-option-input" style="width:100%; border:1px solid color-mix(in srgb, var(--text-main) 15%, transparent); border-radius:4px; padding:0.6rem 2.5rem 0.6rem 0.75rem; background:transparent; font-size:0.95rem; outline:none; box-shadow:none; color:var(--text-main);">
                            <i class="ph ph-smiley" style="position:absolute; right:0.5rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:1.2rem; cursor:pointer;"></i>
                        </div>
                        <i class="ph ph-equals" style="color:var(--text-muted); cursor:grab; font-size:1.2rem;"></i>
                    </div>
                </div>
            </div>
            
            
        </div>
        
        <div class="modal-footer" style="padding:1rem 0 0; border:none; display:flex; justify-content:flex-end;">
            <button class="btn btn-primary" id="btn-create-poll" style="border-radius:20px; padding:0.5rem 1.5rem; font-weight:600;"><i class="ph-bold ph-paper-plane-tilt"></i> Crear</button>
        </div>
    </div>
</div>

<script>
const CURRENT_USER_ID = <?php echo $_SESSION['user_id']; ?>;
const CURRENT_USER_NAME = <?php echo json_encode($_SESSION['user_name'] ?? 'Usuario'); ?>;
const CURRENT_USER_AVATAR = <?php echo json_encode($currentUserAvatar ?: ''); ?>;
const CURRENT_USER_VIP = <?php echo json_encode($currentUserVip); ?>;
const CURRENT_USER_BG = <?php echo json_encode($currentUserBg); ?>;
const CURRENT_USER_SPOTIFY = <?php echo json_encode($currentUserSpotify); ?>;
const ALL_USERS = <?php echo json_encode($allUsers); ?>;
const AVATAR_COLORS = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
const MONTH_NAMES = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
</script>
<script type="module" src="https://unpkg.com/emoji-picker-element@1"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<!-- Create Task Modal -->
<div class="modal-overlay" id="create-task-modal">
    <div class="modal-content" style="max-width:550px; padding:2rem;">
        <div class="modal-header" style="border:none; padding:0; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.2rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
                <button class="chat-icon-btn btn-close-modal" data-target="create-task-modal" style="margin-left:-0.5rem;"><i class="ph ph-x"></i></button>
                Lista de Tareas (To-Do)
            </h3>
        </div>
        
        <div class="modal-body" style="padding:0; display:flex; flex-direction:column; gap:2rem;">
            <div>
                <label style="display:block; font-weight:600; font-size:1rem; margin-bottom:1rem; color:var(--text-main);">Título de la lista</label>
                <div style="position:relative;">
                    <input type="text" id="task-title" placeholder="Ej. Tareas de la semana" class="chat-input" style="width:100%; border:1px solid color-mix(in srgb, var(--text-main) 15%, transparent); border-radius:4px; padding:0.6rem 0.75rem; background:transparent; font-size:1rem; outline:none; box-shadow:none; color:var(--text-main);">
                </div>
            </div>

            <div>
                <label style="display:block; font-weight:600; font-size:0.95rem; margin-bottom:0.75rem; color:var(--text-main);">Tareas</label>
                <div id="task-items-container" style="display:flex; flex-direction:column; gap:0.5rem;">
                    <div class="task-item-row" style="position:relative; display:flex; align-items:center; gap:0.5rem;">
                        <input type="text" placeholder="Añade una tarea" class="chat-input task-item-input" style="flex:1; border:1px solid color-mix(in srgb, var(--text-main) 15%, transparent); border-radius:4px; padding:0.6rem 0.75rem; background:transparent; font-size:0.95rem; outline:none; box-shadow:none; color:var(--text-main);">
                        <i class="ph ph-equals" style="color:var(--text-muted); cursor:grab; font-size:1.2rem;"></i>
                    </div>
                    <div class="task-item-row" style="position:relative; display:flex; align-items:center; gap:0.5rem;">
                        <input type="text" placeholder="Añade una tarea" class="chat-input task-item-input" style="flex:1; border:1px solid color-mix(in srgb, var(--text-main) 15%, transparent); border-radius:4px; padding:0.6rem 0.75rem; background:transparent; font-size:0.95rem; outline:none; box-shadow:none; color:var(--text-main);">
                        <i class="ph ph-equals" style="color:var(--text-muted); cursor:grab; font-size:1.2rem;"></i>
                    </div>
                </div>
            </div>
            
            <button class="btn btn-secondary" style="border-radius:20px; font-weight:600; font-size:0.9rem;" onclick="addTaskRow()"><i class="ph ph-plus"></i> Añadir tarea</button>
        </div>
        
        <div class="modal-footer" style="padding:0; margin-top:2rem; border-top:1px solid var(--border-color); padding-top:1rem; display:flex; justify-content:flex-end;">
            <button class="btn btn-primary" id="btn-send-task" style="border-radius:20px; font-weight:600; padding:0.5rem 1.5rem; display:flex; align-items:center; gap:0.5rem;">Enviar lista</button>
        </div>
    </div>
</div>

<!-- Edit History Modal -->
<div class="modal-overlay" id="edit-history-modal">
    <div class="modal-content" style="max-width:550px; padding:2rem;">
        <div class="modal-header" style="border:none; padding:0; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.2rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
                <button class="chat-icon-btn btn-close-modal" data-target="edit-history-modal" style="margin-left:-0.5rem;"><i class="ph ph-x"></i></button>
                Historial de Edición
            </h3>
        </div>
        
        <div class="modal-body" style="padding:0; max-height: 400px; overflow-y: auto;">
            <div id="edit-history-container" style="display:flex; flex-direction:column; gap:1rem;">
                <!-- History items injected here -->
            </div>
        </div>
    </div>
</div>

<!-- Chat Settings Modal (Fondo y Spotify) -->
<div class="modal-overlay" id="chat-settings-modal">
    <div class="modal-content" style="max-width:450px; padding:2rem;">
        <div class="modal-header" style="border:none; padding:0; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.2rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
                <button class="chat-icon-btn btn-close-modal" data-target="chat-settings-modal" style="margin-left:-0.5rem;"><i class="ph ph-x"></i></button>
                Ajustes de Chat
            </h3>
        </div>
        <div class="modal-body" style="padding:0; display:flex; flex-direction:column; gap:1.5rem;">
            <div>
                <label style="display:block; font-weight:600; font-size:1rem; margin-bottom:0.5rem; color:var(--text-main);">Conectar con Spotify</label>
                <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">Muestra en tu perfil lo que estás escuchando en tiempo real.</div>
                <button class="btn btn-outline" id="btn-connect-spotify" style="display:flex; align-items:center; gap:0.5rem; border-color:#1DB954; color:#1DB954; width:100%; justify-content:center;">
                    <i class="ph-fill ph-spotify-logo" style="font-size:1.2rem;"></i> <span id="spotify-btn-text">Vincular Spotify</span>
                </button>
            </div>
            <hr style="border:none; border-top:1px solid var(--border-color); margin:0;">
            <div>
                <label style="display:block; font-weight:600; font-size:1rem; margin-bottom:0.5rem; color:var(--text-main);">Fondo del Chat</label>
                <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">Elige una animación de fondo para tus chats.</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                    <button class="btn btn-outline bg-picker-btn" data-bg="default" style="justify-content:center;">Predeterminado</button>
                    <button class="btn btn-outline bg-picker-btn" data-bg="particles" style="justify-content:center;">Partículas Animadas</button>
                    <button class="btn btn-outline bg-picker-btn" data-bg="gradient" style="justify-content:center;">Gradiente Móvil</button>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Chat backgrounds container -->
    <div id="chat-bg-layer" style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:-1; pointer-events:none;"></div>

    <!-- Chat scripts -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script src="https://jitsi.riot.im/external_api.js"></script>
    <script src="modules/chat/chat.js?v=<?php echo time(); ?>"></script>
    <script src="modules/chat/voice.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
