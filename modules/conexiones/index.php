<?php
// modules/conexiones/index.php
require_once 'includes/header.php';

$success = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'tab-crm-api'; // Default tab prioritizes WordPress CRM API if not specified

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $db;
    $action_type = $_POST['action_type'] ?? '';

    try {
        $stmt_admin_check = $db->prepare("SELECT role_id FROM users WHERE id = ?");
        $stmt_admin_check->execute([$_SESSION['user_id']]);
        if ($stmt_admin_check->fetchColumn() != 1) {
            throw new Exception('Acceso Denegado: Solo el Administrador principal puede realizar modificaciones.');
        }

        if ($action_type === 'crm_api') {
            $active_tab = 'tab-crm-api';
            $enabled = isset($_POST['crm_api_enabled']) ? '1' : '0';
            $key = trim($_POST['crm_api_key'] ?? '');

            $stmt_check = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = :key");
            $stmt_update = $db->prepare("UPDATE settings SET setting_value = :val WHERE setting_key = :key");
            $stmt_insert = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :val)");

            // Guardar crm_api_enabled
            $stmt_check->execute([':key' => 'crm_api_enabled']);
            if ($stmt_check->fetchColumn() > 0) {
                $stmt_update->execute([':val' => $enabled, ':key' => 'crm_api_enabled']);
            } else {
                $stmt_insert->execute([':val' => $enabled, ':key' => 'crm_api_enabled']);
            }

            // Guardar crm_api_key si no está vacía
            if (!empty($key)) {
                $stmt_check->execute([':key' => 'crm_api_key']);
                if ($stmt_check->fetchColumn() > 0) {
                    $stmt_update->execute([':val' => $key, ':key' => 'crm_api_key']);
                } else {
                    $stmt_insert->execute([':val' => $key, ':key' => 'crm_api_key']);
                }
            }
            $success = 'Configuración de conexión con WordPress guardada exitosamente.';

        } elseif (in_array($action_type, ['smtp', 'whatsapp'])) {
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
}

// Fetch current settings
global $db;
$stmt = $db->query("SELECT * FROM settings WHERE setting_key LIKE 'smtp_%' OR setting_key LIKE 'jsonpe_%' OR setting_key LIKE 'crm_api_%'");
$settings_raw = $stmt->fetchAll();
$settings = [];
foreach ($settings_raw as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<style>
/* Estilos modernos y rediseñados para el módulo de Conexiones */
.roma-conn-header {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 16px;
    padding: 1.5rem 1.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 16px rgba(0,0,0,0.03);
    flex-wrap: wrap;
    gap: 1rem;
}

.roma-conn-icon-box {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(79, 70, 229, 0.15) 100%);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: var(--primary-color, #6366f1);
    font-size: 1.75rem;
}

.roma-tabs-wrapper {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 18px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    overflow: hidden;
}

.roma-tabs-nav {
    display: flex;
    background: var(--bg-color, #f8fafc);
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    padding: 8px 12px 0 12px;
    gap: 6px;
    overflow-x: auto;
}

.roma-tab-button {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 18px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    outline: none;
}

.roma-tab-button:hover {
    color: var(--color-title, #1e293b);
    background: rgba(255, 255, 255, 0.7);
}

.roma-tab-button.active {
    color: var(--primary-color, #6366f1);
    background: var(--bg-surface, #ffffff);
    border-bottom-color: var(--primary-color, #6366f1);
    box-shadow: 0 -2px 6px rgba(0,0,0,0.02);
}

.roma-tab-badge {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 10px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.roma-tab-pane {
    display: none;
    padding: 2rem;
    animation: roma-tab-fade 0.25s ease;
}

.roma-tab-pane.active {
    display: block;
}

@keyframes roma-tab-fade {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Estilos de las tarjetas internas */
.roma-setting-card {
    background: var(--bg-color, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
}

.roma-conn-hero {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.06) 0%, rgba(79, 70, 229, 0.1) 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
}

.roma-conn-hero-content {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.roma-conn-hero-icon {
    width: 48px;
    height: 48px;
    background: var(--primary-color, #6366f1);
    color: #ffffff;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    flex-shrink: 0;
}

.roma-badge-active {
    background: #dcfce7;
    color: #166534;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.roma-badge-inactive {
    background: #fee2e2;
    color: #991b1b;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
}

.roma-pulse-dot {
    width: 6px;
    height: 6px;
    background: #16a34a;
    border-radius: 50%;
    display: inline-block;
    animation: roma-pulse 1.8s infinite;
}

@keyframes roma-pulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(22, 163, 74, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
}

/* Switch Toggle */
.roma-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
    margin: 0;
}
.roma-switch input { opacity: 0; width: 0; height: 0; }
.roma-slider {
    position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
    background-color: #cbd5e1;
    transition: .3s;
    border-radius: 26px;
}
.roma-slider:before {
    position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
.roma-switch input:checked + .roma-slider {
    background-color: var(--primary-color, #6366f1);
}
.roma-switch input:checked + .roma-slider:before {
    transform: translateX(22px);
}

/* Key Box Layout */
.roma-key-input-box {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.roma-key-display {
    position: relative;
    flex: 1;
    min-width: 280px;
}

.roma-key-display input {
    padding-right: 42px;
}

.roma-btn-icon {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-muted, #64748b);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 4px;
}

.roma-key-actions {
    display: flex;
    gap: 8px;
}
</style>

<!-- Header Rediseñado -->
<div class="roma-conn-header">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div class="roma-conn-icon-box">
            <i class="ph ph-plugs-connected"></i>
        </div>
        <div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <h1 style="margin: 0; font-size: 1.4rem; font-weight: 800; color: var(--color-title, #1e293b);">Conexiones e Integraciones</h1>
                <span style="background: rgba(99, 102, 241, 0.1); color: var(--primary-color, #6366f1); font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 6px;">Roma Hub</span>
            </div>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted, #64748b); font-size: 0.85rem;">
                Administra tus claves de API para WordPress, servidores SMTP de correo y la API de WhatsApp JSON.pe.
            </p>
        </div>
    </div>
    <div style="display: flex; gap: 8px;">
        <span style="font-size: 0.8rem; color: var(--text-muted, #64748b); display: flex; align-items: center; gap: 6px; background: var(--bg-color, #f8fafc); padding: 6px 12px; border-radius: 20px; border: 1px solid var(--border-color, #e2e8f0);">
            <i class="ph ph-shield-check" style="color: #10b981;"></i> SSL / HTTPS Seguro
        </span>
    </div>
</div>

<?php if ($success): ?>
    <div style="background: #d1fae5; color: #065f46; padding: 0.9rem 1.25rem; border-radius: 12px; margin-bottom: 1.25rem; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 8px; font-weight: 500; font-size: 0.9rem;">
        <i class="ph ph-check-circle" style="font-size: 1.25rem;"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 0.9rem 1.25rem; border-radius: 12px; margin-bottom: 1.25rem; border: 1px solid #fecaca; display: flex; align-items: center; gap: 8px; font-weight: 500; font-size: 0.9rem;">
        <i class="ph ph-warning-circle" style="font-size: 1.25rem;"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Tabs Container Rediseñado -->
<div class="roma-tabs-wrapper">
    <div class="roma-tabs-nav">
        <!-- Pestaña Nueva: WordPress & CRM API -->
        <button class="roma-tab-button <?php echo $active_tab === 'tab-crm-api' ? 'active' : ''; ?>" data-tab="tab-crm-api">
            <i class="ph ph-wordpress-logo" style="font-size: 1.15rem;"></i>
            <span>WordPress & CRM API</span>
            <span class="roma-tab-badge">NUEVO</span>
        </button>

        <button class="roma-tab-button <?php echo $active_tab === 'tab-smtp' ? 'active' : ''; ?>" data-tab="tab-smtp">
            <i class="ph ph-envelope-simple" style="font-size: 1.15rem;"></i>
            <span>Servidor SMTP</span>
        </button>

        <button class="roma-tab-button <?php echo $active_tab === 'tab-templates' ? 'active' : ''; ?>" data-tab="tab-templates">
            <i class="ph ph-layout" style="font-size: 1.15rem;"></i>
            <span>Plantillas de Correo</span>
        </button>

        <button class="roma-tab-button <?php echo $active_tab === 'tab-whatsapp' ? 'active' : ''; ?>" data-tab="tab-whatsapp">
            <i class="ph ph-whatsapp-logo" style="font-size: 1.15rem;"></i>
            <span>WhatsApp API</span>
        </button>
    </div>

    <!-- Contenidos de las Pestañas -->
    <div class="roma-tabs-content">
        
        <!-- Pestaña 1: WordPress & CRM API (NUEVA) -->
        <div id="tab-crm-api" class="roma-tab-pane <?php echo $active_tab === 'tab-crm-api' ? 'active' : ''; ?>">
            <?php include 'modules/conexiones/tabs/crm_api.php'; ?>
        </div>

        <!-- Pestaña 2: SMTP -->
        <div id="tab-smtp" class="roma-tab-pane <?php echo $active_tab === 'tab-smtp' ? 'active' : ''; ?>">
            <?php include 'modules/conexiones/tabs/smtp.php'; ?>
        </div>

        <!-- Pestaña 3: Plantillas -->
        <div id="tab-templates" class="roma-tab-pane <?php echo $active_tab === 'tab-templates' ? 'active' : ''; ?>">
            <?php include 'modules/conexiones/tabs/email_templates.php'; ?>
        </div>

        <!-- Pestaña 4: WhatsApp -->
        <div id="tab-whatsapp" class="roma-tab-pane <?php echo $active_tab === 'tab-whatsapp' ? 'active' : ''; ?>">
            <?php include 'modules/conexiones/tabs/whatsapp_api.php'; ?>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.roma-tab-button');
    const tabPanes = document.querySelectorAll('.roma-tab-pane');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const targetPane = document.getElementById(btn.dataset.tab);
            if (targetPane) {
                targetPane.classList.add('active');
            }
            
            // Actualizar URL sin recargar
            const url = new URL(window.location);
            url.searchParams.set('tab', btn.dataset.tab);
            window.history.pushState({}, '', url);
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
