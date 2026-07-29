<?php
// modules/clients/social_auth.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

if (!$client_id) {
    echo "ID de cliente no proporcionado.";
    exit();
}

// Fetch client
$stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    echo "Cliente no encontrado.";
    exit();
}

// Fetch connected accounts
$stmtAcc = $db->prepare("SELECT * FROM client_social_accounts WHERE client_id = ?");
$stmtAcc->execute([$client_id]);
$accountsRaw = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);

$accounts = [
    'facebook' => null,
    'instagram' => null,
    'tiktok' => null
];

foreach ($accountsRaw as $acc) {
    $accounts[$acc['platform']] = $acc;
}

require_once 'includes/header.php';
?>

<style>
.social-auth-header {
    margin-bottom: 2rem;
}
.social-auth-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.social-auth-header p {
    color: var(--text-muted);
    margin-top: 0.5rem;
}

.social-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.social-card {
    background: var(--bg-panel);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}

.social-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.social-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}
.social-icon.facebook { background: #1877F2; }
.social-icon.instagram { background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); }
.social-icon.tiktok { background: #000000; }

.social-title {
    font-weight: 600;
    font-size: 1.1rem;
    color: var(--text-main);
}
.social-status {
    font-size: 0.85rem;
    color: var(--text-muted);
}

.social-connected {
    background: rgba(46, 204, 113, 0.1);
    color: #2ecc71;
    padding: 0.5rem;
    border-radius: 8px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-connect {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-connect:hover {
    filter: brightness(1.1);
    color: white;
}

.btn-disconnect {
    background: transparent;
    color: #e74c3c;
    border: 1px solid #e74c3c;
    padding: 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-disconnect:hover {
    background: #e74c3c;
    color: white;
}
</style>

<div class="social-auth-header">
    <h1><i class="ph ph-plugs-connected"></i> Conexiones Sociales</h1>
    <p>Gestiona las integraciones de publicación directa para el cliente <strong><?= htmlspecialchars($client['name']) ?></strong>.</p>
</div>

<div class="social-grid">
    <!-- FACEBOOK -->
    <div class="social-card">
        <div class="social-card-header">
            <div class="social-icon facebook"><i class="ph ph-facebook-logo"></i></div>
            <div>
                <div class="social-title">Facebook Page</div>
                <div class="social-status">Publicar imágenes y videos</div>
            </div>
        </div>
        
        <?php if ($accounts['facebook']): ?>
            <div class="social-connected">
                <i class="ph ph-check-circle"></i> Conectado: <?= htmlspecialchars($accounts['facebook']['account_name']) ?>
            </div>
            <a href="#" onclick="disconnectSocial(<?= $client_id ?>, 'facebook')" class="btn-disconnect">Desconectar</a>
        <?php else: ?>
            <a href="index.php?module=clients&action=callback_meta&client_id=<?= $client_id ?>&platform=facebook" class="btn-connect">Conectar con Facebook</a>
        <?php endif; ?>
    </div>

    <!-- INSTAGRAM -->
    <div class="social-card">
        <div class="social-card-header">
            <div class="social-icon instagram"><i class="ph ph-instagram-logo"></i></div>
            <div>
                <div class="social-title">Instagram</div>
                <div class="social-status">Publicar posts y Reels</div>
            </div>
        </div>
        
        <?php if ($accounts['instagram']): ?>
            <div class="social-connected">
                <i class="ph ph-check-circle"></i> Conectado: <?= htmlspecialchars($accounts['instagram']['account_name']) ?>
            </div>
            <a href="#" onclick="disconnectSocial(<?= $client_id ?>, 'instagram')" class="btn-disconnect">Desconectar</a>
        <?php else: ?>
            <a href="index.php?module=clients&action=callback_meta&client_id=<?= $client_id ?>&platform=instagram" class="btn-connect">Conectar con Instagram</a>
        <?php endif; ?>
    </div>

    <!-- TIKTOK -->
    <div class="social-card">
        <div class="social-card-header">
            <div class="social-icon tiktok"><i class="ph ph-tiktok-logo"></i></div>
            <div>
                <div class="social-title">TikTok</div>
                <div class="social-status">Publicar videos directamente</div>
            </div>
        </div>
        
        <?php if ($accounts['tiktok']): ?>
            <div class="social-connected">
                <i class="ph ph-check-circle"></i> Conectado: <?= htmlspecialchars($accounts['tiktok']['account_name']) ?>
            </div>
            <a href="#" onclick="disconnectSocial(<?= $client_id ?>, 'tiktok')" class="btn-disconnect">Desconectar</a>
        <?php else: ?>
            <a href="index.php?module=clients&action=callback_tiktok&client_id=<?= $client_id ?>" class="btn-connect">Conectar con TikTok</a>
        <?php endif; ?>
    </div>
</div>

<script>
function disconnectSocial(clientId, platform) {
    if (confirm('¿Estás seguro de desconectar esta cuenta? Ya no se podrá publicar automáticamente en ella.')) {
        $.post('index.php?module=clients&action=ajax_disconnect_social', {
            client_id: clientId,
            platform: platform
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert('Error al desconectar: ' + (res.error || 'Desconocido'));
            }
        }, 'json');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
