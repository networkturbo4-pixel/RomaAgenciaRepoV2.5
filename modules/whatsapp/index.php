<?php
// modules/whatsapp/index.php
require_once 'includes/header.php';

// Fetch current user details
$stmtUser = $db->prepare("SELECT avatar FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$currentUserData = $stmtUser->fetch(PDO::FETCH_ASSOC);
$currentUserAvatar = $currentUserData['avatar'] ?? null;

// Fetch all users for assignments
$stmtAllUsers = $db->query("SELECT id, name, avatar FROM users ORDER BY name ASC");
$allUsers = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);

$allUsersObj = [];
foreach($allUsers as $u) {
    $allUsersObj[$u['id']] = $u;
}
?>

<link rel="stylesheet" href="modules/whatsapp/whatsapp.css?v=<?php echo time(); ?>">

<div id="wa-app" class="wa-app">
    <!-- QR Screen Overlay -->
    <div id="wa-qr-overlay" style="display: flex;">
        <div class="qr-container">
            <div class="qr-header">
                <i class="ph ph-whatsapp-logo" style="font-size: 2rem; color: #25D366;"></i>
                <h2>Vincular WhatsApp</h2>
                <p>Abre WhatsApp en tu teléfono, ve a Dispositivos vinculados y escanea el QR.</p>
            </div>
            <div id="qr-code-wrapper" class="qr-code-wrapper">
                <div class="qr-loader">Conectando con el servidor...</div>
            </div>
            <div class="qr-status" id="qr-status-text">Esperando QR...</div>
        </div>
    </div>

    <!-- Sidebar Panel -->
    <aside class="wa-sidebar" id="wa-sidebar">
        <div class="wa-sidebar-header">
            <h2>WhatsApp</h2>
            <div class="connection-status" id="wa-connection-status">
                <div class="status-indicator"></div>
                <span>Conectado</span>
            </div>
        </div>

        <!-- Search hidden by request
        <div class="wa-search">
            <div class="search-input-wrapper">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" id="wa-search-input" placeholder="Buscar o empezar un chat nuevo">
            </div>
        </div>
        -->

        <div id="wa-contact-list" class="wa-contact-list">
            <!-- Contacts will be rendered here -->
        </div>
    </aside>

    <!-- Main Chat Panel -->
    <main class="wa-main" id="wa-main">
        <!-- Default State -->
        <div class="wa-empty-state" id="wa-empty-state">
            <i class="ph ph-whatsapp-logo"></i>
            <h3>Roma Chat</h3>
            <p>Selecciona un chat para empezar a enviar mensajes</p>
        </div>

        <!-- Chat Header -->
        <div class="wa-header" id="wa-header" style="display: none;">
            <div class="wa-header-info">
                <div id="wa-header-avatar" class="wa-avatar"></div>
                <div class="wa-header-text">
                    <h3 id="wa-channel-name">Nombre del contacto</h3>
                    <span id="wa-channel-status" class="wa-status"></span>
                </div>
            </div>
            <div class="wa-header-actions">
                <div id="wa-assignment-badge" class="wa-assignment-badge" style="display: none;">
                    <i class="ph ph-user"></i> <span id="wa-assigned-name">Asignado a: César</span>
                </div>
                <button class="wa-icon-btn" id="btn-wa-assign" title="Asignar chat"><i class="ph ph-user-plus"></i></button>
                <button class="wa-icon-btn" id="btn-wa-info" title="Info del contacto"><i class="ph ph-info"></i></button>
            </div>
        </div>

        <!-- Messages Area -->
        <div class="wa-messages" id="wa-messages" style="display: none;"></div>
        
        <button id="btn-wa-scroll-bottom" class="btn-scroll-bottom" style="display:none;">
            <i class="ph ph-caret-down"></i>
        </button>

        <!-- Input Area -->
        <div class="wa-input-area" id="wa-input-area" style="display: none;">
            <button class="wa-icon-btn" id="btn-wa-attach"><i class="ph ph-paperclip"></i></button>
            <input type="file" id="wa-file-input" style="display:none;">
            <input type="text" id="wa-message-input" placeholder="Escribe un mensaje" autocomplete="off">
            <button class="wa-icon-btn send-btn" id="btn-wa-send"><i class="ph-fill ph-paper-plane-right"></i></button>
        </div>
    </main>

    <!-- Right Info Panel -->
    <aside class="wa-info-panel" id="wa-info-panel" style="display: none;">
        <div class="wa-info-header">
            <button class="wa-icon-btn" id="btn-wa-close-info"><i class="ph ph-x"></i></button>
            <h3>Info. del contacto</h3>
        </div>
        <div class="wa-info-body">
            <div class="wa-info-profile">
                <div id="wa-info-avatar" class="wa-info-avatar-large"></div>
                <h2 id="wa-info-name">Nombre</h2>
                <p id="wa-info-phone">+52 1 234 567 8900</p>
            </div>
            
            <div class="wa-info-section">
                <h4>Etiquetas</h4>
                <div id="wa-labels-container" class="wa-labels-container"></div>
                <button class="btn btn-outline btn-sm" id="btn-manage-labels" style="width:100%; margin-top:0.5rem;"><i class="ph ph-tag"></i> Administrar etiquetas</button>
            </div>
            
            <div class="wa-info-section">
                <h4>Asignación</h4>
                <div id="wa-assign-container">
                    <select id="wa-assign-select" class="form-control">
                        <option value="">Sin asignar</option>
                        <?php foreach($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </aside>
</div>

<!-- Label Management Modal -->
<div class="modal-overlay" id="wa-labels-modal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header">
            <h2>Etiquetas del chat</h2>
            <button class="btn-icon btn-close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <div id="wa-all-labels-list" class="wa-labels-list"></div>
        </div>
    </div>
</div>

<script>
const CURRENT_USER_ID = <?php echo $_SESSION['user_id']; ?>;
const WA_BRIDGE_URL = 'http://localhost:3001';
const ALL_USERS = <?php echo json_encode($allUsersObj); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="modules/whatsapp/whatsapp.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer.php'; ?>
