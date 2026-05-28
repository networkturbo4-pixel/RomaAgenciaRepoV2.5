<?php
// modules/chat/index.php
require_once 'includes/header.php';

// Fetch current user avatar
$stmtAvatar = $db->prepare("SELECT avatar FROM users WHERE id = ?");
$stmtAvatar->execute([$_SESSION['user_id']]);
$currentUserAvatar = $stmtAvatar->fetchColumn();

// Fetch all users for DM/channel creation
$stmtAllUsers = $db->query("SELECT id, name, avatar FROM users ORDER BY name ASC");
$allUsers = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="modules/chat/chat.css?v=<?php echo time(); ?>">

<div id="chat-app" class="chat-app">
    <!-- Sidebar Panel -->
    <aside class="chat-sidebar" id="chat-sidebar">
        <div class="chat-sidebar-header">
            <button class="chat-icon-btn d-md-none-chat" id="btn-close-chat-sidebar" title="Cerrar"><i class="ph ph-arrow-left"></i></button>
            <h2 class="d-none d-md-block" style="font-size:1.25rem; font-weight:700;">Chat</h2>
            <button class="chat-icon-btn" title="Configuración"><i class="ph ph-gear"></i></button>
        </div>

        <div class="chat-sidebar-profile">
            <div class="chat-profile-img-wrap">
                <div class="chat-profile-img" style="background-image:url('<?php echo $currentUserAvatar ? $currentUserAvatar : 'assets/img/default-avatar.png'; ?>');">
                    <?php if (!$currentUserAvatar) echo strtoupper(substr($_SESSION['user_name'],0,1)); ?>
                </div>
                <div class="chat-profile-status-dot"></div>
            </div>
            <h3 class="chat-profile-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
            <div class="chat-profile-status-badge">
                available <i class="ph ph-caret-down"></i>
            </div>
        </div>

        <div class="chat-search-wrap">
            <i class="ph ph-magnifying-glass"></i>
            <input type="search" id="channel-search" placeholder="Search" autocomplete="off">
        </div>

        <div class="chat-sidebar-tabs">
            <button class="chat-tab-btn active" data-target="pane-canales">Canales</button>
            <button class="chat-tab-btn" data-target="pane-dms">Chats</button>
        </div>

        <div class="chat-sidebar-pane active" id="pane-canales">
            <div class="chat-section-title">
                <span>Last chats</span>
                <div style="display:flex; gap:0.5rem;">
                    <button class="chat-icon-btn-sm bg-light-green" id="btn-new-channel" title="Nuevo Canal"><i class="ph ph-plus"></i></button>
                    <button class="chat-icon-btn-sm"><i class="ph ph-dots-three-vertical"></i></button>
                </div>
            </div>
            <div id="channel-list-group" class="channel-list"></div>
        </div>

        <div class="chat-sidebar-pane" id="pane-dms">
            <div class="chat-section-title">
                <span>Direct messages</span>
                <div style="display:flex; gap:0.5rem;">
                    <button class="chat-icon-btn-sm bg-light-green" id="btn-new-dm" title="Nuevo DM"><i class="ph ph-plus"></i></button>
                </div>
            </div>
            <div id="channel-list-dm" class="channel-list"></div>
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
                <h3 id="chat-channel-name">Selecciona un chat</h3>
                <span id="chat-channel-meta" class="chat-meta" style="display:none;"></span>
            </div>
            <div class="chat-header-tabs" id="chat-header-tabs" style="display:none;">
                <button class="chat-header-tab active">Messages</button>
                <button class="chat-header-tab">Participants</button>
                <button class="chat-icon-btn-sm" id="btn-delete-channel" title="Eliminar Canal" style="color:#ef4444; margin-left:0.5rem; display:none;">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
        </div>

        <!-- Messages Area -->
        <div class="chat-messages" id="chat-messages">
            <div class="chat-empty-state" id="chat-empty-state">
                <i class="ph ph-chat-circle-dots"></i>
                <h3>Bienvenido al Chat</h3>
                <p>Selecciona un canal o conversación para empezar</p>
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
                <div class="chat-input-actions" style="margin-right: 0.5rem;">
                    <button class="chat-icon-btn-sm" id="btn-attach-file" title="Adjuntar archivo" style="color:#9ca3af;">
                        <i class="ph ph-paperclip"></i>
                    </button>
                    <button class="chat-icon-btn-sm" id="btn-share-card" title="Compartir Card" style="color:#9ca3af;">
                        <i class="ph ph-squares-four"></i>
                    </button>
                </div>
                <input type="file" id="file-input" style="display:none;">
                
                <textarea id="chat-input" placeholder="Write your message..." rows="1"></textarea>
                
                <div class="chat-input-actions">
                    <button class="chat-icon-btn-sm" style="color:#9ca3af;"><i class="ph ph-smiley"></i></button>
                    <button class="chat-send-btn" id="btn-send" title="Enviar">
                        <i class="ph ph-paper-plane-right"></i>
                    </button>
                </div>
            </div>
            <div class="chat-file-preview" id="chat-file-preview" style="display:none;">
                <span id="file-preview-name"></span>
                <button class="chat-icon-btn-sm" id="btn-remove-file"><i class="ph ph-x"></i></button>
            </div>
        </div>
    </main>
</div>

<!-- New Channel Modal -->
<div class="modal-overlay" id="new-channel-modal">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h2>Nuevo Canal</h2>
            <button class="btn-icon btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Nombre del Canal</label>
                <input type="text" id="new-ch-name" class="form-control" placeholder="ej: diseño, ventas, soporte...">
            </div>
            <div class="form-group">
                <label class="form-label">Descripción (opcional)</label>
                <input type="text" id="new-ch-desc" class="form-control" placeholder="¿De qué trata este canal?">
            </div>
            <div class="form-group">
                <label class="form-label">Miembros</label>
                <div style="max-height:200px; overflow-y:auto; border:1px solid var(--border-color); border-radius:var(--radius-md); padding:0.75rem;">
                    <?php foreach ($allUsers as $u): ?>
                    <label style="display:flex; align-items:center; gap:0.5rem; padding:0.3rem 0; cursor:pointer;">
                        <input type="checkbox" class="new-ch-member" value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $_SESSION['user_id'] ? 'checked disabled' : ''; ?>>
                        <span><?php echo htmlspecialchars($u['name']); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline btn-close-modal">Cancelar</button>
            <button class="btn btn-primary" id="btn-save-channel">Crear Canal</button>
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
                <?php endif; ?>
            </div>
            <input type="text" id="card-search-input" class="form-control" placeholder="Buscar..." style="margin-top:0.75rem;">
            <div id="card-search-results" class="card-search-results"></div>
        </div>
    </div>
</div>

<!-- Invite Link Modal -->
<div class="modal-overlay" id="invite-modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header">
            <h2><i class="ph ph-link" style="color:var(--primary-color);"></i> Link de Invitación</h2>
            <button class="btn-icon btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-muted); font-size:0.875rem; margin-bottom:1rem;">Comparte este link para que personas externas puedan ver y participar en este canal.</p>
            <div id="invite-link-area" style="display:none;">
                <div style="display:flex; gap:0.5rem;">
                    <input type="text" id="invite-link-input" class="form-control" readonly>
                    <button class="btn btn-primary" id="btn-copy-invite"><i class="ph ph-copy"></i></button>
                </div>
                <button class="btn btn-outline" id="btn-revoke-invite" style="margin-top:0.75rem; color:var(--danger-color); border-color:var(--danger-color); width:100%;">
                    <i class="ph ph-trash"></i> Revocar Link
                </button>
            </div>
            <button class="btn btn-primary" id="btn-generate-invite" style="width:100%;">
                <i class="ph ph-link"></i> Generar Link de Invitación
            </button>
        </div>
    </div>
</div>

<script>
const CURRENT_USER_ID = <?php echo $_SESSION['user_id']; ?>;
const CURRENT_USER_NAME = <?php echo json_encode($_SESSION['user_name'] ?? 'Usuario'); ?>;
const CURRENT_USER_AVATAR = <?php echo json_encode($currentUserAvatar ?: ''); ?>;
const ALL_USERS = <?php echo json_encode($allUsers); ?>;
const AVATAR_COLORS = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
const MONTH_NAMES = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
</script>
<script src="modules/chat/chat.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
