<?php
// modules/conexiones/index.php
require_once 'includes/header.php';

$success = '';
$error = '';
$active_tab = 'tab-smtp'; // Default tab

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $db;
    $action_type = $_POST['action_type'] ?? '';

    try {
        $stmt_admin_check = $db->prepare("SELECT role_id FROM users WHERE id = ?");
        $stmt_admin_check->execute([$_SESSION['user_id']]);
        if ($stmt_admin_check->fetchColumn() != 1) {
            throw new Exception('Acceso Denegado: Solo el Administrador principal puede realizar modificaciones.');
        }

        if (in_array($action_type, ['smtp', 'whatsapp'])) {
            $active_tab = 'tab-' . $action_type;
            
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = :key");
            $stmt_update = $db->prepare("UPDATE settings SET setting_value = :val WHERE setting_key = :key");
            $stmt_insert = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :val)");
            
            foreach ($_POST as $key => $val) {
                if ($key !== 'action_type') {
                    $stmt_check->execute([':key' => $key]);
                    if ($stmt_check->fetchColumn() > 0) {
                        $stmt_update->execute([':val' => $val, ':key' => $key]);
                    } else {
                        $stmt_insert->execute([':val' => $val, ':key' => $key]);
                    }
                }
            }
            $success = 'Configuración guardada exitosamente.';
        } elseif ($action_type === 'template_delete') {
            $active_tab = 'tab-templates';
            $template_id = $_POST['template_id'] ?? 0;
            $stmt = $db->prepare("DELETE FROM email_templates WHERE id = ?");
            $stmt->execute([$template_id]);
            $success = 'Plantilla eliminada exitosamente.';
        }
    } catch(Exception $e) {
        $error = 'Error al procesar la solicitud: ' . $e->getMessage();
    }
} else {
    $active_tab = $_GET['tab'] ?? 'tab-smtp';
}

// Fetch current settings
global $db;
$stmt = $db->query("SELECT * FROM settings WHERE setting_key LIKE 'smtp_%' OR setting_key LIKE 'jsonpe_%'");
$settings_raw = $stmt->fetchAll();
$settings = [];
foreach ($settings_raw as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="width: 56px; height: 56px; background: var(--bg-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
            <i class="ph ph-plugs-connected" style="font-size: 1.75rem; color: var(--primary-color);"></i>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Conexiones e Integraciones</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Administra tus conexiones SMTP, plantillas de correo y la API de WhatsApp JSON.pe.</p>
        </div>
    </div>
</div>

<?php if ($success): ?>
    <div style="background: #d1fae5; color: #059669; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
        <i class="ph ph-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background: #fee2e2; color: #ef4444; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
        <i class="ph ph-warning-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="tabs-nav">
        <button class="tab-btn <?php echo $active_tab === 'tab-smtp' ? 'active' : ''; ?>" data-tab="tab-smtp">
            <i class="ph ph-envelope-simple"></i> Servidor SMTP
        </button>
        <button class="tab-btn <?php echo $active_tab === 'tab-templates' ? 'active' : ''; ?>" data-tab="tab-templates">
            <i class="ph ph-layout"></i> Plantillas de Correo
        </button>
        <button class="tab-btn <?php echo $active_tab === 'tab-whatsapp' ? 'active' : ''; ?>" data-tab="tab-whatsapp">
            <i class="ph ph-whatsapp-logo"></i> WhatsApp API
        </button>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content">
        <!-- Tab 1: SMTP -->
        <div id="tab-smtp" class="tab-pane <?php echo $active_tab === 'tab-smtp' ? 'active' : ''; ?>">
            <?php include 'modules/conexiones/tabs/smtp.php'; ?>
        </div>

        <!-- Tab 2: Plantillas -->
        <div id="tab-templates" class="tab-pane <?php echo $active_tab === 'tab-templates' ? 'active' : ''; ?>">
            <?php include 'modules/conexiones/tabs/email_templates.php'; ?>
        </div>

        <!-- Tab 3: WhatsApp -->
        <div id="tab-whatsapp" class="tab-pane <?php echo $active_tab === 'tab-whatsapp' ? 'active' : ''; ?>">
            <?php include 'modules/conexiones/tabs/whatsapp_api.php'; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Basic Tab Logic (assuming it's not fully handled globally)
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            document.getElementById(btn.dataset.tab).classList.add('active');
            
            // Actualizar URL
            const url = new URL(window.location);
            url.searchParams.set('tab', btn.dataset.tab);
            window.history.pushState({}, '', url);
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
