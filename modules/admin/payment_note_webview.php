<?php
// modules/admin/payment_note_webview.php
$is_public = isset($_GET['view']) && $_GET['view'] === 'public';
$public_note_data = null;
$note = null;

// Determine base URL for all relative assets and AJAX requests
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$baseUrl = (!empty($global_settings['site_url'])) ? rtrim($global_settings['site_url'], '/') : ($protocol . '://' . $host . ($scriptDir ? $scriptDir : ''));

if ($is_public) {
    // If it's public, check for the token in payment_notes
    if (isset($_GET['token'])) {
        $t = trim($_GET['token']);
        $stmtNote = $db->prepare("SELECT * FROM payment_notes WHERE public_token = ? OR LEFT(public_token, 8) = ? LIMIT 1");
        $stmtNote->execute([$t, $t]);
        $note = $stmtNote->fetch(PDO::FETCH_ASSOC);
        
        if ($note) {
            $data = [
                'id' => $note['note_code'],
                'client' => $note['client_name'],
                'company' => $note['company_name'],
                'startDate' => $note['start_date'],
                'total' => $note['total'],
                'servicios' => json_decode($note['services_json'], true) ?: [],
                'cronograma' => json_decode($note['schedule_json'], true) ?: [],
                'abonos' => json_decode($note['abonos_json'] ?? '[]', true) ?: [],
                'apply_igv' => (bool)$note['apply_igv'],
                'discount_percent' => floatval($note['discount_percent']),
                'show_memberships' => (bool)($note['show_memberships'] ?? true),
                'show_advances' => (bool)($note['show_advances'] ?? false),
                'status' => $note['status'],
                'due_days' => intval($note['due_days'] ?? 30),
                'has_pin' => !empty($note['access_pin']),
                'token' => $note['public_token'],
                'voucher_url' => $note['voucher_url'] ?? null,
                'operation_number' => $note['operation_number'] ?? null,
                'voucher_uploaded_at' => $note['voucher_uploaded_at'] ?? null
            ];
            $public_note_data = base64_encode(json_encode($data));
        } else {
            // Fallback for old shared links if needed
            $stmtToken = $db->prepare("SELECT data FROM shared_links WHERE token = ? OR LEFT(token, 8) = ? LIMIT 1");
            $stmtToken->execute([$t, $t]);
            $tokenData = $stmtToken->fetchColumn();
            if ($tokenData) {
                $public_note_data = $tokenData; // Base64 string
            }
        }
    }
}

if (!$is_public) {
    require_once 'includes/header.php';

    $stmt = $db->query("
        SELECT c.id, c.name, 
               GROUP_CONCAT(b.name SEPARATOR '||') as brands,
               GROUP_CONCAT(b.has_membership SEPARATOR '||') as memberships,
               GROUP_CONCAT(COALESCE(b.services_ids, '[]') SEPARATOR '||') as services_ids
        FROM clients c
        LEFT JOIN client_brands b ON c.id = b.client_id
        GROUP BY c.id
        ORDER BY c.name ASC
    ");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all services
    $stmtServices = $db->query("SELECT id, name, description, price FROM services WHERE deleted_at IS NULL ORDER BY name ASC");
    $all_services = $stmtServices->fetchAll(PDO::FETCH_ASSOC);
} else {
    $clients = [];
    $all_services = [];
}

// Fetch MP Enabled setting
$stmtMp = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'mp_enabled'");
$mp_enabled_setting = $stmtMp->fetchColumn();
$mp_enabled = ($mp_enabled_setting === false || $mp_enabled_setting === '1');
?>

<?php if ($is_public): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <base href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Pago <?php echo htmlspecialchars($note['note_code'] ?? ''); ?> | <?php echo htmlspecialchars($global_settings['site_name'] ?? 'Roma Agencia'); ?></title>
    
    <?php if(!empty($global_settings['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($global_settings['favicon']); ?>">
    <?php endif; ?>

    <!-- Open Graph -->
    <meta property="og:title" content="Nota de Pago <?php echo htmlspecialchars($note['note_code'] ?? ''); ?> - <?php echo htmlspecialchars($note['client_name'] ?? ''); ?>">
    <meta property="og:description" content="Consulta el estado de tu nota de pago, servicios y abonos.">
    <meta property="og:type" content="website">

    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tesseract.js para OCR de Vouchers -->
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

    <!-- Anti-FOUC Script for Dark Mode -->
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
<?php else: ?>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<?php endif; ?>

<script>
    const SYSTEM_SERVICES = <?php echo json_encode($all_services); ?>;
    const MP_ENABLED = <?php echo $mp_enabled ? 'true' : 'false'; ?>;
</script>

<style>
/* Base Tokens for Both Public and Admin Webview */
:root {
    --primary-color: <?php echo htmlspecialchars($global_settings['primary_color'] ?? '#4f46e5'); ?>;
    --primary-contrast: var(--primary-color);
    --secondary-color: <?php echo htmlspecialchars($global_settings['secondary_color'] ?? '#10b981'); ?>;
    --warning-color: <?php echo htmlspecialchars($global_settings['accent_color'] ?? '#f59e0b'); ?>;
    --border-color: #e2e8f0;
    --bg-surface: #ffffff;
    --bg-body: #f8f9fc;
    --text-main: #0f172a;
    --text-muted: #64748b;
    --font-family: 'Inter', sans-serif;
}

[data-theme="dark"] {
    --primary-contrast: color-mix(in srgb, var(--primary-color), white 40%);
    --border-color: rgba(255, 255, 255, 0.08);
    --bg-surface: #0b0b0e;
    --bg-body: #000000;
    --text-main: #ffffff;
    --text-muted: #9ca3af;
}

body.public-mode {
    font-family: var(--font-family) !important;
    margin: 0 !important;
    padding: 0 !important;
    background-color: var(--bg-body) !important;
    color: var(--text-main) !important;
    min-height: 100vh;
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
}

/* Toast styling for public mode */
.toast-msg {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 99999;
    background: #09090b;
    color: #ffffff;
    padding: 12px 20px;
    border-radius: 12px;
    font-size: 0.88rem;
    font-weight: 600;
    box-shadow: 0 10px 30px rgba(0,0,0,0.35);
    border: 1px solid rgba(255, 255, 255, 0.12);
    animation: toastSlideUp 0.25s ease;
}
@keyframes toastSlideUp {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}
/* Specific Styles for Nota de Pago Webview */
.payment-notes-container {
    --card-elevation: 0 12px 30px rgba(0, 0, 0, 0.05), 0 4px 8px rgba(0, 0, 0, 0.02);
    --border: color-mix(in srgb, var(--border-color) 80%, #000 20%);
    --accent: var(--primary-color);
    --accent-light: color-mix(in srgb, var(--primary-color) 80%, white);
    --success: var(--secondary-color);
    --pending: var(--warning-color);
    --pending-bg: color-mix(in srgb, var(--warning-color) 10%, transparent);
    --paid-bg: color-mix(in srgb, var(--secondary-color) 10%, transparent);
    --paid-text: var(--secondary-color);
    --hover-card: var(--bg-surface);
    --copy-btn: var(--bg-color);

    max-width: 100%;
    margin: 0 auto;
    width: 100%;
    animation: fadeIn 0.3s ease;
}

[data-theme="dark"] .payment-notes-container {
    --card-elevation: 0 12px 30px rgba(0, 0, 0, 0.4);
    --border: color-mix(in srgb, var(--border-color) 80%, #fff 20%);
    --hover-card: var(--bg-surface);
    --copy-btn: var(--bg-color);
    --pending-bg: color-mix(in srgb, var(--warning-color) 15%, transparent);
    --paid-bg: color-mix(in srgb, var(--secondary-color) 15%, transparent);
}

.payment-notes-container .header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 32px;
    gap: 16px;
}

.payment-notes-container .brand h1 {
    font-size: 1.9rem;
    font-weight: 650;
    letter-spacing: -0.5px;
    background: linear-gradient(130deg, var(--accent), var(--accent-light));
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
}

.payment-notes-container .brand p {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-top: 6px;
    font-weight: 450;
}

.payment-notes-container .section-card {
    background: var(--bg-surface);
    border-radius: 28px;
    border: 1px solid var(--border);
    box-shadow: var(--card-elevation);
    margin-bottom: 36px;
    overflow: hidden;
    transition: all 0.2s;
}

.payment-notes-container .section-header {
    padding: 1.2rem 1.8rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    flex-wrap: wrap;
    background: var(--bg-surface);
}

.payment-notes-container .section-header h2 {
    font-size: 1.35rem;
    font-weight: 600;
    letter-spacing: -0.2px;
    margin: 0;
}

.payment-notes-container .badge-soft {
    background: var(--accent);
    color: white;
    padding: 4px 14px;
    border-radius: 40px;
    font-size: 0.7rem;
    font-weight: 500;
    opacity: 0.9;
}

.payment-notes-container .cards-grid {
    padding: 1.5rem 1.8rem;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.payment-notes-container .row-card {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 1rem 1.2rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    transition: all 0.2s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}

.payment-notes-container .row-card:hover {
    background: var(--hover-card);
    border-color: var(--accent-light);
    transform: translateY(-2px);
}

.payment-notes-container .card-info {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: center;
    flex: 3;
}

.payment-notes-container .info-field {
    display: flex;
    flex-direction: column;
    min-width: 110px;
}

.payment-notes-container .field-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.5px;
}

.payment-notes-container .field-value {
    font-weight: 600;
    font-size: 0.95rem;
    margin-top: 4px;
    color: var(--text-main);
}

.payment-notes-container .amount-highlight {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--accent);
}

.payment-notes-container .card-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.payment-notes-container .btn-icon-sm {
    background: var(--copy-btn);
    border: none;
    border-radius: 40px;
    padding: 6px 16px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: 0.1s;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
}

.payment-notes-container .btn-icon-sm:hover {
    background: var(--accent);
    color: white;
}

.payment-notes-container .status-pill {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 40px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: capitalize;
}

.payment-notes-container .status-pending {
    background: var(--pending-bg);
    color: var(--pending);
    border: 0.5px solid var(--pending);
}

.payment-notes-container .status-paid {
    background: var(--paid-bg);
    color: var(--paid-text);
    border: 0.5px solid var(--paid-text);
}

.payment-notes-container .action-bar {
    padding: 0.8rem 1.8rem 1.5rem 1.8rem;
    display: flex;
    justify-content: flex-end;
    border-top: 1px solid var(--border);
    background: var(--bg-surface);
}

.payment-notes-container .btn-primary-custom {
    background: var(--accent);
    border: none;
    padding: 10px 24px;
    border-radius: 40px;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.payment-notes-container .btn-primary-custom:hover {
    background: var(--accent-light);
    transform: scale(0.97);
}

.payment-notes-container .summary-modern {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 32px;
}

.payment-notes-container .stat-card-modern {
    background: var(--bg-surface);
    border-radius: 24px;
    border: 1px solid var(--border);
    padding: 1.2rem 1.6rem;
    flex: 1;
    min-width: 180px;
    box-shadow: var(--card-elevation);
}

.payment-notes-container .stat-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: 600;
    color: var(--text-muted);
}

.payment-notes-container .stat-number {
    font-size: 2rem;
    font-weight: 700;
    margin-top: 6px;
    color: var(--text-main);
}

.payment-notes-container .stat-highlight {
    border-left: 3px solid var(--pending);
}

.payment-notes-container .pending-number {
    color: var(--pending);
}

.payment-notes-container .payments-wrapper {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
    margin: 12px 0 8px;
}

.payment-notes-container .payment-item {
    background: var(--copy-btn);
    border-radius: 60px;
    padding: 8px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.8rem;
    border: 1px solid var(--border);
    cursor: pointer;
    transition: 0.1s;
    color: var(--text-main);
}

.payment-notes-container .payment-item:hover {
    background: var(--accent);
    color: white;
}

.payment-notes-container .payment-code {
    font-family: monospace;
    font-weight: 600;
}

.toast-msg {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #1e293b;
    color: #ffffff;
    padding: 12px 24px;
    border-radius: 60px;
    font-size: 0.85rem;
    font-weight: 600;
    z-index: 10001;
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    animation: toastIn 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}
.toast-msg::before {
    content: '✓';
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    background: #10b981;
    color: #fff;
    border-radius: 50%;
    font-size: 11px;
    font-weight: 800;
}
@keyframes toastIn {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Inline Inputs for editing */
.payment-notes-container .inline-input {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    color: var(--text-main);
    border-radius: var(--radius-sm);
    padding: 0.4rem 0.6rem;
    font-size: 0.85rem;
    font-family: var(--font-family);
    width: 100%;
}
.payment-notes-container .inline-input:focus {
    outline: none;
    border-color: var(--primary-color);
}

/* Service Table Card Layout */
.payment-notes-container .serv-card-table {
    display: block;
    padding: 0 !important;
    overflow: hidden;
}

.payment-notes-container .serv-table-grid {
    display: grid;
    grid-template-columns: 2fr 0.5fr 1fr 1fr;
    align-items: center;
    gap: 0;
    width: 100%;
}

.payment-notes-container .serv-col {
    padding: 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.payment-notes-container .serv-col + .serv-col {
    border-left: 1px solid var(--border-color);
}

.payment-notes-container .serv-col-num {
    text-align: right;
    align-items: flex-end;
}

.payment-notes-container .serv-desc {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-top: 2px;
}

.payment-notes-container .serv-card-table .card-actions {
    border-top: 1px solid var(--border-color);
    padding: 0.6rem 1rem;
    justify-content: flex-end;
    display: flex;
    gap: 8px;
}

@media (max-width: 700px) {
    .payment-notes-container .serv-table-grid {
        grid-template-columns: 1fr 1fr;
        grid-template-rows: auto auto;
    }
    .payment-notes-container .serv-col-name {
        grid-column: 1 / -1;
        border-left: none !important;
        border-bottom: 1px solid var(--border-color);
    }
    .payment-notes-container .serv-col + .serv-col {
        border-left: none;
    }
    .payment-notes-container .serv-col:nth-child(3) {
        border-left: 1px solid var(--border-color);
    }
    .payment-notes-container .serv-col-num {
        text-align: left;
        align-items: flex-start;
    }
}

/* Public Mode Styles */
body.public-mode {
    margin: 0 !important;
    padding: 0 !important;
    overflow-x: hidden;
    background: #f8f9fc !important;
}

[data-theme="dark"] body.public-mode,
[data-theme="dark"] body.public-mode .app-container,
[data-theme="dark"] body.public-mode .main-content,
[data-theme="dark"] body.public-mode .content-wrapper,
[data-theme="dark"] body.public-mode .payment-notes-container {
    background: #000000 !important;
    background-color: #000000 !important;
    color: #ffffff !important;
}

[data-theme="dark"] body.public-mode #public-header-banner {
    background: rgba(0, 0, 0, 0.9) !important;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
}

[data-theme="dark"] body.public-mode .app-card,
[data-theme="dark"] body.public-mode .app-detail-card,
[data-theme="dark"] body.public-mode .app-pm-card,
[data-theme="dark"] body.public-mode .app-voucher-card,
[data-theme="dark"] body.public-mode .section-card,
[data-theme="dark"] #modal-voucher-upload .modal-content {
    background-color: #0b0b0e !important;
    background: #0b0b0e !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6) !important;
    color: #ffffff !important;
}

[data-theme="dark"] body.public-mode .app-detail-item {
    border-bottom: 1px solid rgba(255, 255, 255, 0.06) !important;
}

[data-theme="dark"] body.public-mode .app-detail-total-area {
    background: #111116 !important;
    border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
}

[data-theme="dark"] body.public-mode .app-client-name,
[data-theme="dark"] body.public-mode .app-pm-name,
[data-theme="dark"] body.public-mode .app-detail-name,
[data-theme="dark"] body.public-mode .app-total-value {
    color: #ffffff !important;
}

[data-theme="dark"] body.public-mode .app-client-company,
[data-theme="dark"] body.public-mode .app-detail-desc,
[data-theme="dark"] body.public-mode .app-date,
[data-theme="dark"] body.public-mode .app-pm-acc,
[data-theme="dark"] body.public-mode .app-total-label {
    color: #94a3b8 !important;
}

[data-theme="dark"] body.public-mode .app-pm-icon-wrap {
    background: #181820 !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
}

[data-theme="dark"] body.public-mode .app-pm-copy {
    background: rgba(16, 185, 129, 0.15) !important;
    color: #10b981 !important;
}

[data-theme="dark"] body.public-mode .app-verification-seal {
    background: #0b0b0e !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    color: #94a3b8 !important;
}

[data-theme="dark"] body.public-mode .app-status-btn:not(.app-status-paid) {
    background: rgba(239, 68, 68, 0.12) !important;
    color: #f87171 !important;
    border-color: rgba(239, 68, 68, 0.25) !important;
}

[data-theme="dark"] body.public-mode .app-status-paid {
    background: rgba(16, 185, 129, 0.12) !important;
    color: #10b981 !important;
    border-color: rgba(16, 185, 129, 0.25) !important;
}

[data-theme="dark"] #voucher-drop-zone,
[data-theme="dark"] #voucher-preview-area {
    background: #14141a !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}

[data-theme="dark"] #voucher-operation-number {
    background: #14141a !important;
    border-color: rgba(255, 255, 255, 0.14) !important;
    color: #ffffff !important;
}
body.public-mode .sidebar,
body.public-mode .topbar,
body.public-mode .mobile-topbar,
body.public-mode .sidebar-collapse-btn {
    display: none !important;
}
body.public-mode .main-content {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
}
body.public-mode .content-wrapper {
    padding: 0 !important;
}
body.public-mode .sidebar-overlay {
    display: none !important;
}
body.public-mode .payment-notes-container {
    padding: 0 !important;
    margin: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
body.public-mode .payment-notes-inner {
    padding: 16px;
    max-width: 800px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}
body.public-mode .header-flex {
    display: none !important;
}
body.public-mode #public-header-banner {
    display: block !important;
    position: sticky;
    top: 0;
    z-index: 100;
}
body.public-mode .action-bar,
body.public-mode .card-actions,
body.public-mode .btn-volver,
body.public-mode .botones-finales {
    display: none !important;
}
body.public-mode .public-readonly-text {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-main);
    display: block;
}
body.public-mode .inline-input {
    display: none !important;
}
body.public-mode .row-card.is-paid {
    opacity: 0.5;
    background-color: var(--bg-page);
    filter: grayscale(100%);
}

@media (max-width: 700px) {
    .payment-notes-container .row-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px;
    }
    .payment-notes-container .card-info {
        width: 100%;
    }
    .payment-notes-container .card-actions {
        width: 100%;
        justify-content: flex-end;
    }
    .payment-notes-container .stat-number {
        font-size: 1.5rem;
    }

    body.public-mode .payment-notes-inner {
        padding: 0 !important;
        max-width: 100% !important;
    }

    .payment-notes-container .cards-grid {
        padding: 0.75rem 0.5rem;
        gap: 10px;
    }

    .payment-notes-container .row-card {
        border-radius: 14px;
        padding: 0.9rem 1rem;
    }
}

/* Modern Tables CSS */
.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    text-align: left;
}

.modern-table th {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: var(--text-main);
    background-color: color-mix(in srgb, var(--bg-body) 90%, #000 10%);
    font-weight: 700;
    letter-spacing: 0.5px;
    padding: 12px 16px;
    border-bottom: 2px solid var(--border);
}

.modern-table td {
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    color: var(--text-main);
    font-size: 0.95rem;
}

.modern-table tbody tr {
    transition: background-color 0.2s ease;
}

.modern-table tbody tr:hover {
    background-color: var(--hover-card);
}

.modern-table tbody tr:last-child td {
    border-bottom: none;
}

.modern-table tfoot td {
    padding: 16px;
    vertical-align: middle;
}

.table-responsive {
    overflow-x: auto;
}

body.public-mode .modern-table td:last-child,
body.public-mode .modern-table th:last-child {
    display: none; /* Hide actions in public view */
}

body.public-mode .modern-table tr.is-paid {
    opacity: 0.6;
    background-color: var(--bg-page);
}

/* --- New CSS for Grid and App-like UI --- */
.two-column-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 24px;
    align-items: start;
}
body.public-mode .two-column-layout {
    display: flex;
    justify-content: center;
}
body.public-mode .left-column,
body.public-mode #client-info-card {
    display: none;
}
body.public-mode .right-column {
    display: block;
    width: 100%;
    max-width: 800px;
}
body.public-mode .preview-container {
    padding: 0;
    background: transparent;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    position: static !important;
}

.modern-input-group {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 10px 16px;
    transition: all 0.2s;
    background: var(--bg-page);
    border: 1px solid var(--border);
    padding: 10px 14px;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    transition: 0.2s;
}

/* Custom Autocomplete CSS */
.autocomplete-container {
    position: relative;
    width: 100%;
}
.autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-top: 4px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 100;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    display: none;
}
.autocomplete-item {
    padding: 10px 14px;
    cursor: pointer;
    font-size: 0.9rem;
    color: var(--text-main);
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
}
.autocomplete-item:last-child {
    border-bottom: none;
}
.autocomplete-item:hover {
    background: var(--hover-card);
    color: var(--accent);
}

.modern-input-group:focus-within, .modern-input-group:hover {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 15%, transparent);
}

.modern-input-group label {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--text-main);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.modern-input-group select, 
.modern-input-group input[type="text"], 
.modern-input-group input[type="number"], 
.modern-input-group input[type="date"] {
    border: none;
    background: transparent;
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--text-main);
    padding: 0;
    width: 100%;
}

.modern-input-group select:focus, 
.modern-input-group input:focus {
    outline: none;
}

/* Toggle Switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  flex-shrink: 0;
}
.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}
.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--border);
  transition: .3s;
  border-radius: 24px;
}
.toggle-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .3s;
  border-radius: 50%;
  box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
input:checked + .toggle-slider {
  background-color: var(--primary-color);
}
input:checked + .toggle-slider:before {
  transform: translateX(20px);
}

.preview-container {
    background: var(--bg-surface);
    border-radius: 24px;
    border: 1px solid var(--border-color);
    box-shadow: var(--card-elevation);
    position: sticky;
    top: 24px;
    overflow: hidden;
}
.preview-header {
    background: var(--primary-color);
    color: white;
    padding: 16px 20px;
    text-align: center;
}
.preview-header h3 {
    margin: 0;
    font-size: 1.1rem;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
}
.preview-header p {
    margin: 4px 0 0;
    font-size: 0.8rem;
    opacity: 0.8;
}
.preview-content {
    padding: 20px;
}
.preview-client-info h4 {
    margin: 0 0 4px 0;
    font-size: 1.1rem;
}
.preview-client-info p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-muted);
}
.preview-divider {
    height: 1px;
    background: var(--border-color);
    margin: 16px 0;
}
.preview-section-title {
    font-size: 0.8rem;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 12px;
}
.preview-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.9rem;
}
.preview-item-name {
    color: var(--text-main);
    font-weight: 500;
}
.preview-item-price {
    font-weight: 600;
}
.preview-total-row {
    display: flex;
    justify-content: space-between;
    font-weight: 700;
    font-size: 0.95rem;
    margin-top: 12px;
}
.preview-grand-total {
    display: flex;
    justify-content: space-between;
    font-weight: 800;
    font-size: 1.2rem;
    color: var(--primary-color);
}
.badge-pagado {
    background: var(--paid-bg);
    color: var(--paid-text);
    font-size: 0.65rem;
    padding: 2px 6px;
    border-radius: 12px;
    margin-left: 6px;
    vertical-align: middle;
}
.badge-pendiente {
    background: var(--pending-bg);
    color: var(--pending);
    font-size: 0.65rem;
    padding: 2px 6px;
    border-radius: 12px;
    margin-left: 6px;
    vertical-align: middle;
}

/* New App-Like UI Styles */
.app-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
}
.app-section-title {
    font-size: 11px;
    color: #4b5563;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.app-client-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.app-client-icon {
    width: 44px;
    height: 44px;
    background: #a7f3d0;
    color: #064e3b;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 1.2rem;
}
.app-client-name {
    font-size: 14px;
    color: #047857;
    font-weight: 500;
    margin-bottom: 2px;
}
.app-client-company {
    font-size: 13px;
    color: #6b7280;
}
.app-divider {
    height: 1px;
    background: #f3f4f6;
    margin: 12px 0;
}
.app-date {
    font-size: 13px;
    color: #4b5563;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Detalle card */
.app-detail-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 24px;
}
.app-detail-item {
    padding: 14px 16px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid #f3f4f6;
}
.app-detail-item:last-child {
    border-bottom: none;
}
.app-detail-name {
    font-weight: 600;
    color: #111827;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.app-detail-desc {
    font-size: 13px;
    color: #6b7280;
    margin-top: 6px;
}
.app-badge-green {
    font-size: 10px;
    padding: 3px 8px;
    border-radius: 12px;
    font-weight: 800;
    text-transform: uppercase;
}
.app-badge-adicional {
    background: #dbeafe;
    color: #1d4ed8;
}
.app-badge-membresia {
    background: #ede9fe;
    color: #6d28d9;
}
.app-detail-price {
    font-weight: 700;
    color: #064e3b;
    font-size: 14px;
    white-space: nowrap;
    text-align: right;
    min-width: 90px;
    flex-shrink: 0;
}
.app-detail-total-area {
    background: #f9fafb;
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.app-total-label {
    color: #4b5563;
    font-size: 13px;
}
.app-total-value {
    font-size: 20px;
    font-weight: 700;
    color: #064e3b;
    letter-spacing: -0.5px;
}

/* Payment Methods */
.app-pm-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
}
.app-pm-icon-wrap {
    width: 40px;
    height: 40px;
    background: #f3f4f6;
    border-radius: 10px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 1.2rem;
    color: #111827;
    overflow: hidden;
    flex-shrink: 0;
}
.app-pm-info {
    flex: 1;
}
.app-pm-name {
    font-weight: 700;
    color: #111827;
    font-size: 14px;
    margin-bottom: 2px;
}
.app-pm-acc {
    font-size: 13px;
    color: #6b7280;
}
.app-pm-copy {
    color: #047857;
    background: #d1fae5;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 1.2rem;
    cursor: pointer;
}

/* Status Button */
.app-status-btn {
    width: 100%;
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    padding: 16px;
    border-radius: 30px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 1.05rem;
    margin-top: 32px;
}
.app-status-paid {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}


@media (max-width: 1024px) {
    .two-column-layout {
        grid-template-columns: 1fr;
    }
}

/* === FADE-IN ANIMATIONS === */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
body.public-mode .app-animate {
    opacity: 0;
    animation: fadeInUp 0.5s ease forwards;
}
body.public-mode .app-animate-delay-1 { animation-delay: 0.05s; }
body.public-mode .app-animate-delay-2 { animation-delay: 0.12s; }
body.public-mode .app-animate-delay-3 { animation-delay: 0.2s; }
body.public-mode .app-animate-delay-4 { animation-delay: 0.3s; }
body.public-mode .app-animate-delay-5 { animation-delay: 0.4s; }

/* === VERIFICATION SEAL === */
.app-verification-seal {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 28px;
    padding: 14px 20px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.app-verification-seal i {
    font-size: 16px;
    color: #047857;
}

/* === DUE DATE BADGE === */
.app-due-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    margin-top: 12px;
}
.app-due-ok {
    background: #dbeafe;
    color: #1d4ed8;
}
.app-due-warning {
    background: #fef3c7;
    color: #92400e;
}
.app-due-expired {
    background: #fef2f2;
    color: #b91c1c;
}

/* === PIN OVERLAY === */
.pin-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}
.pin-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 40px 32px;
    max-width: 360px;
    width: 90%;
    text-align: center;
    box-shadow: 0 25px 60px rgba(0,0,0,0.3);
    animation: fadeInUp 0.4s ease;
}
.pin-card-logo {
    width: 56px;
    height: 56px;
    background: #d1fae5;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 1.5rem;
    color: #047857;
}
.pin-card h2 {
    margin: 0 0 6px;
    font-size: 1.2rem;
    color: #0f172a;
    font-weight: 700;
}
.pin-card p {
    margin: 0 0 24px;
    font-size: 13px;
    color: #64748b;
}
.pin-inputs {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-bottom: 20px;
}
.pin-inputs input {
    width: 52px;
    height: 58px;
    text-align: center;
    font-size: 1.4rem;
    font-weight: 700;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    outline: none;
    color: #0f172a;
    transition: border-color 0.2s;
    -moz-appearance: textfield;
}
.pin-inputs input::-webkit-outer-spin-button,
.pin-inputs input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.pin-inputs input:focus {
    border-color: #047857;
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.15);
}
.pin-inputs input.pin-error {
    border-color: #ef4444;
    animation: shake 0.4s ease;
}
.pin-error-msg {
    color: #ef4444;
    font-size: 13px;
    font-weight: 600;
    min-height: 20px;
    margin-bottom: 12px;
}
.pin-submit-btn {
    width: 100%;
    padding: 14px;
    background: #047857;
    color: #ffffff;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s;
}
.pin-submit-btn:hover {
    background: #065f46;
}
.pin-submit-btn:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20% { transform: translateX(-8px); }
    40% { transform: translateX(8px); }
    60% { transform: translateX(-6px); }
    80% { transform: translateX(6px); }
}
</style>

<?php if ($is_public): ?>
</head>
<body class="public-mode">
<?php endif; ?>

<div class="payment-notes-container">
  <!-- PUBLIC HEADER BANNER -->
  <div id="public-header-banner" style="display: none; padding: 14px 20px; border-bottom: 1px solid var(--border-color); background: var(--bg-surface); backdrop-filter: blur(12px);">
      <div style="max-width: 800px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between;">
          <div style="display: flex; align-items: center; gap: 12px;">
              <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(79, 70, 229, 0.12); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                  <i class="ph ph-receipt"></i>
              </div>
              <div>
                  <h1 style="margin: 0; font-size: 0.95rem; font-weight: 800; color: var(--text-main); letter-spacing: 0.2px;"><?php echo htmlspecialchars($global_settings['site_name'] ?? 'THE ROMA AGENCY'); ?></h1>
                  <div id="public-banner-id" style="font-size: 0.72rem; color: var(--text-muted); margin-top: 1px; font-weight: 600;"></div>
              </div>
          </div>
          <div style="display: flex; align-items: center; gap: 8px;">
              <button type="button" class="btn btn-outline btn-sm" onclick="window.print()" style="border-radius: 8px; font-size: 0.8rem; padding: 6px 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                  <i class="ph ph-printer"></i> Imprimir
              </button>
          </div>
      </div>
  </div>

  <div class="payment-notes-inner">
  <!-- INFORMACIÓN DEL CLIENTE / PROYECTO -->
  <div class="section-card" id="client-info-card">
    <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
      <div style="display: flex; align-items: center; gap: 20px; flex: 1;">

          <div class="brand" style="display: flex; flex-direction: column;">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                NOTA DE PAGO
                <span id="invoice-header-info" style="font-size: 0.8rem; font-weight: 600; color: var(--text-main); background: var(--bg-page); padding: 6px 14px; border-radius: 40px; letter-spacing: 0;">NUEVA NOTA &middot; RUC 20610965530 &middot; THE ROMA AGENCY CORPORACION S.A.C.</span>
            </h1>
          </div>
      </div>
      <div class="botones-finales" style="display: flex; gap: 12px; align-items: center;">
          <button class="btn btn-outline" onclick="window.location.href='index.php?module=admin&action=payment_notes'" style="padding: 10px 20px; border-radius: 12px;">Cancelar</button>
          <button class="btn btn-primary" id="btn-guardar-nota-total" style="padding: 10px 24px; border-radius: 12px; font-size: 0.95rem; font-weight: 600;"><i class="ph ph-floppy-disk"></i> Guardar Nota</button>
      </div>
    </div>
    <div style="padding: 1.5rem 1.8rem;">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
          <div class="modern-input-group">
              <label><i class="ph ph-user-circle"></i> Cliente / Contacto</label>
              <span id="public-client-text" class="public-readonly-text" style="display: none;"></span>
              <select id="note-client">
                  <option value="">Selecciona un cliente...</option>
                  <?php foreach ($clients as $client): ?>
                      <option value="<?php echo htmlspecialchars($client['name']); ?>" 
                              data-brands="<?php echo htmlspecialchars($client['brands'] ?? ''); ?>"
                              data-memberships="<?php echo htmlspecialchars($client['memberships'] ?? ''); ?>"
                              data-services="<?php echo htmlspecialchars($client['services_ids'] ?? ''); ?>">
                          <?php echo htmlspecialchars($client['name']); ?>
                      </option>
                  <?php endforeach; ?>
              </select>
          </div>
          <div class="modern-input-group">
              <label><i class="ph ph-buildings"></i> Empresa / Marca</label>
              <span id="public-company-text" class="public-readonly-text" style="display: none;"></span>
              <select id="note-company">
                  <option value="">Selecciona una marca...</option>
              </select>
          </div>
          <div class="modern-input-group">
              <label><i class="ph ph-calendar-blank"></i> Fecha de Inicio</label>
              <span id="public-date-text" class="public-readonly-text" style="display: none;"></span>
              <input type="date" id="note-start-date">
          </div>
          
          <div class="modern-input-group" style="flex-direction: row; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 16px;">
              <label style="margin: 0; font-size: 0.75rem; display: flex; align-items: center; gap: 6px;"><i class="ph ph-calendar-star" style="font-size: 1.1rem; color: var(--primary-color);"></i> Mostrar Membresías</label>
              <label class="toggle-switch">
                  <input type="checkbox" id="toggle-membership" checked>
                  <span class="toggle-slider"></span>
              </label>
          </div>

          <div class="modern-input-group" style="flex-direction: row; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 16px;">
              <label style="margin: 0; font-size: 0.75rem; display: flex; align-items: center; gap: 6px;"><i class="ph ph-wallet" style="font-size: 1.1rem; color: var(--primary-color);"></i> Mostrar Adelantos</label>
              <label class="toggle-switch">
                  <input type="checkbox" id="toggle-abonos">
                  <span class="toggle-slider"></span>
              </label>
          </div>

          <div class="modern-input-group" id="admin-voucher-input-group">
              <label style="margin: 0; font-size: 0.75rem; display: flex; align-items: center; gap: 6px;"><i class="ph ph-receipt" style="font-size: 1.1rem; color: var(--primary-color);"></i> Comprobante / Voucher</label>
              <div style="display: flex; gap: 8px; align-items: center; margin-top: 6px;">
                  <input type="text" id="admin-note-op-number" placeholder="N° Operación..." style="flex: 1; padding: 6px 10px; border: 1px solid var(--border-color); border-radius: 8px; font-weight: 600; font-size: 0.85rem;" oninput="onAdminOpNumberChange(this.value)">
                  <button type="button" class="btn btn-outline btn-sm" onclick="openVoucherUploadModal()" style="border-radius: 8px; white-space: nowrap; font-weight: 600; padding: 6px 12px; display: inline-flex; align-items: center; gap: 4px;">
                      <i class="ph ph-camera"></i> Subir / OCR
                  </button>
                  <a id="admin-voucher-view-link" href="#" target="_blank" style="display: none; padding: 6px 8px; color: var(--primary-color); font-size: 1.2rem;" title="Ver Voucher Adjunto"><i class="ph ph-file-text"></i></a>
              </div>
          </div>
      </div>
    </div>
  </div>

  <div class="two-column-layout">
    <div class="left-column">

  <!-- TABLA 1 - SERVICIOS REALIZADOS -->
  <div class="section-card">
    <div class="section-header">
      <h2>Servicios Realizados</h2>
      <div class="badge-soft">Ítem facturable</div>
    </div>
    <div class="table-responsive" style="padding: 1.5rem 1.8rem;">
      <table class="modern-table" id="serviciosTable">
        <thead>
          <tr>
            <th>Servicio y Descripción</th>
            <th style="width: 100px; text-align: center;">Cant.</th>
            <th style="width: 150px; text-align: right;">Costo Unit.</th>
            <th style="width: 150px; text-align: right;">Total</th>
            <th style="width: 120px; text-align: right;">Acciones</th>
          </tr>
        </thead>
        <tbody id="serviciosCardsContainer">
          <!-- Aquí se renderizan dinámicamente las filas de servicios -->
        </tbody>

      </table>
    </div>
    <div class="action-bar">
      <button class="btn-primary-custom" id="agregarServicioBtn"><i class="ph ph-plus"></i> Agregar servicio</button>
    </div>
  </div>

  <!-- TABLA 2 - HISTORIAL DE MEMBRESÍA -->
  <div class="section-card" id="cronograma-card">
    <div class="section-header">
      <h2>Servicios Tipo Membresía</h2>
      <div class="badge-soft">Historial de pagos</div>
    </div>
    <div class="table-responsive" style="padding: 1.5rem 1.8rem;">
      <table class="modern-table" id="cronogramaTable">
        <thead>
          <tr>
            <th>Servicio</th>
            <th style="width: 150px;">Mes</th>
            <th style="width: 150px;">Fecha de Pago</th>
            <th style="width: 150px; text-align: right;">Monto</th>
            <th style="width: 120px; text-align: center;">Estado</th>
            <th style="width: 140px; text-align: right;">Acciones</th>
          </tr>
        </thead>
        <tbody id="cronogramaCardsContainer">
          <!-- filas de membresía -->
        </tbody>
      </table>
    </div>
    <div class="action-bar">
      <button class="btn-primary-custom" id="agregarCuotaBtn"><i class="ph ph-plus"></i> Añadir cuota</button>
    </div>
  </div>

  <!-- TABLA 3 - REGISTRO DE PAGOS / ADELANTOS -->
  <div class="section-card" id="abonos-card" style="display: none;">
    <div class="section-header">
      <h2>Registro de Pagos / Abonos</h2>
      <div class="badge-soft">Adelantos</div>
    </div>
    <div class="table-responsive" style="padding: 1.5rem 1.8rem;">
      <table class="modern-table" id="abonosTable">
        <thead>
          <tr>
            <th>Concepto de Pago</th>
            <th style="width: 150px;">Método / Banco</th>
            <th style="width: 150px;">Fecha</th>
            <th style="width: 150px; text-align: right;">Monto</th>
            <th style="width: 120px; text-align: right;">Acciones</th>
          </tr>
        </thead>
        <tbody id="abonosCardsContainer">
          <!-- filas de abonos -->
        </tbody>
      </table>
    </div>
    <div class="action-bar">
      <button class="btn-primary-custom" id="agregarAbonoBtn"><i class="ph ph-plus"></i> Añadir abono</button>
    </div>
  </div>

  <!-- RESUMEN FINAL -->
  <div class="section-card" id="resumen-final-card">
      <div style="padding: 1.5rem 1.8rem; background-color: var(--hover-card); border-bottom: 1px solid var(--border);">
          <div style="display: flex; flex-wrap: wrap; gap: 24px; align-items: center; justify-content: flex-end;">
              <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                  <span style="font-size: 0.85rem; color: var(--text-main);">Aplicar IGV (+18%)</span>
                  <div class="toggle-switch">
                      <input type="checkbox" id="toggle-igv">
                      <span class="slider round"></span>
                  </div>
              </label>
              
              <div style="display: flex; align-items: center; gap: 8px;">
                  <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">Descuento Global (%)</span>
                  <input type="number" id="discount-input" class="inline-input" value="0" min="0" max="100" step="1" style="width: 80px; text-align: right; background: var(--bg-page);">
              </div>
          </div>
      </div>
      <div style="padding: 1.5rem 1.8rem;">
          <table style="width: 100%; border-collapse: collapse;">
              <tr id="row-stat-subtotal" style="display: none;">
                <td style="text-align: right; font-weight: 600; text-transform: uppercase; font-size: 0.95rem; color: var(--text-muted); padding: 0.5rem 0;">Subtotal</td>
                <td style="text-align: right; font-weight: 600; font-size: 1.1rem; color: var(--text-main); padding: 0.5rem 0;" id="statSubtotal">S/ 0.00</td>
              </tr>
              <tr id="row-stat-discount" style="display: none;">
                <td style="text-align: right; font-weight: 600; text-transform: uppercase; font-size: 0.95rem; color: #ef4444; padding: 0.5rem 0;">Descuento</td>
                <td style="text-align: right; font-weight: 600; font-size: 1.1rem; color: #ef4444; padding: 0.5rem 0;" id="statDiscount">-S/ 0.00</td>
              </tr>
              <tr id="row-stat-igv" style="display: none;">
                <td style="text-align: right; font-weight: 600; text-transform: uppercase; font-size: 0.95rem; color: var(--text-muted); padding: 0.5rem 0;">IGV (18%)</td>
                <td style="text-align: right; font-weight: 600; font-size: 1.1rem; color: var(--text-main); padding: 0.5rem 0;" id="statIgv">S/ 0.00</td>
              </tr>
              <tr>
              <tr>
                <td style="text-align: right; font-weight: 700; text-transform: uppercase; font-size: 0.95rem; color: var(--text-main); padding: 0.5rem 0;">Total General + Pendiente</td>
                <td style="text-align: right; font-weight: 800; font-size: 1.25rem; color: var(--primary-color); padding: 0.5rem 0;" id="statGeneral">S/ 0.00</td>
              </tr>
              <tr id="row-stat-abonos" style="display: none;">
                <td style="text-align: right; font-weight: 700; text-transform: uppercase; font-size: 0.95rem; color: var(--success); padding: 0.5rem 0;">(-) Adelantos Depositados</td>
                <td style="text-align: right; font-weight: 800; font-size: 1.1rem; color: var(--success); padding: 0.5rem 0;" id="statAbonos">S/ 0.00</td>
              </tr>
              <tr id="row-stat-saldo" style="display: none;">
                <td style="text-align: right; font-weight: 800; text-transform: uppercase; font-size: 1.1rem; color: var(--accent); padding-top: 1rem; border-top: 2px solid var(--border-color);">Saldo Restante a Pagar</td>
                <td style="text-align: right; font-weight: 800; font-size: 1.4rem; color: var(--accent); padding-top: 1rem; border-top: 2px solid var(--border-color);" id="statSaldo">S/ 0.00</td>
              </tr>
          </table>
      </div>
  </div>

  <!-- MÉTODOS DE PAGO CON COPIA -->
  <div class="section-card">
    <div class="section-header">
      <h2>Métodos de pago</h2>
      <div class="badge-soft">Click para copiar</div>
    </div>
    <div style="padding: 0.5rem 1.8rem 1.8rem 1.8rem;">
      <div class="payments-wrapper" id="paymentMethodsList">
        <!-- dinámico -->
      </div>
      <div style="margin-top: 20px; font-size:0.75rem; color:var(--text-muted); border-top: 1px solid var(--border); padding-top: 14px;">
        <span>🔹 A nombre de CESAR A. MENDOZA CASTRO</span>
      </div>
    </div>
  </div>

  <!-- CONFIGURACIÓN AVANZADA -->
  <div class="section-card" id="config-avanzada-card">
    <div class="section-header">
      <h2><i class="ph ph-gear-six"></i> Configuración Avanzada</h2>
    </div>
    <div style="padding: 1rem 1.8rem 1.8rem;">
      <div style="display: flex; flex-wrap: wrap; gap: 24px; align-items: flex-end;">
        <div style="flex: 1; min-width: 180px;">
          <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-main); margin-bottom: 6px;">
            <i class="ph ph-calendar-x" style="color: var(--accent);"></i> Días para vencimiento
          </label>
          <input type="number" id="note-due-days" class="inline-input" value="30" min="1" max="365" style="width: 100%; background: var(--bg-page); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color);">
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Días desde la fecha de inicio para considerar la nota como vencida.</div>
        </div>
        <div style="flex: 1; min-width: 180px;">
          <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-main); margin-bottom: 6px;">
            <i class="ph ph-lock-simple" style="color: var(--accent);"></i> PIN de Acceso (opcional)
          </label>
          <input type="text" id="note-access-pin" class="inline-input" maxlength="4" pattern="[0-9]{4}" placeholder="Ej: 1234" style="width: 100%; background: var(--bg-page); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); letter-spacing: 8px; font-weight: 700; font-size: 1.1rem;">
          <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Si se define, el cliente deberá ingresar este PIN para ver la nota.</div>
        </div>
      </div>
    </div>
  </div>

    </div> <!-- end left-column -->
    <div class="right-column">
      <div class="preview-container">

         <div class="preview-content" id="public-preview-render">
            <div style="text-align: center; color: var(--text-muted); padding: 20px;">
                Cargando previsualización...
            </div>
         </div>
      </div>
    </div>
  </div> <!-- end two-column-layout -->


  </div> <!-- end payment-notes-inner -->
</div>

<!-- Modal: Subir Voucher y OCR -->
<div class="modal-overlay" id="modal-voucher-upload" style="display: none; align-items: center; justify-content: center; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 10000;">
    <div class="modal-content" style="max-width: 520px; width: 92%; border-radius: 20px; padding: 1.75rem; background: var(--bg-surface); border: 1px solid var(--border-color); box-shadow: 0 20px 50px rgba(0,0,0,0.35); animation: fadeInUp 0.3s ease;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 42px; height: 42px; border-radius: 12px; background: rgba(79, 70, 229, 0.12); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 1.35rem;">
                    <i class="ph ph-receipt"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-main);">Subir Comprobante</h3>
                    <p style="margin: 2px 0 0; font-size: 0.78rem; color: var(--text-muted);">Compresión ultra-rápida y lectura OCR automática</p>
                </div>
            </div>
            <button type="button" class="btn-close-circular" onclick="closeVoucherUploadModal()" style="border:none; background:transparent; font-size:1.2rem; cursor:pointer; color:var(--text-muted);"><i class="ph ph-x"></i></button>
        </div>

        <div style="margin-bottom: 1.25rem;">
            <!-- Drop area -->
            <div id="voucher-drop-zone" onclick="document.getElementById('voucher-file-input').click()" style="border: 2px dashed var(--border-color); border-radius: 16px; padding: 2rem 1.25rem; text-align: center; cursor: pointer; transition: all 0.2s; background: var(--bg-page);">
                <i class="ph ph-cloud-arrow-up" style="font-size: 2.5rem; color: var(--accent); margin-bottom: 8px; display: inline-block;"></i>
                <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Seleccionar imagen o tomar foto</div>
                <div style="font-size: 0.78rem; color: var(--text-muted);">Formatos soportados: JPG, PNG, WEBP o PDF</div>
            </div>
            <input type="file" id="voucher-file-input" accept="image/*,application/pdf" style="display: none;" onchange="handleVoucherFileSelected(this)">
        </div>

        <!-- Preview & OCR state -->
        <div id="voucher-preview-area" style="display: none; margin-bottom: 1.25rem; background: var(--bg-page); border-radius: 14px; padding: 12px; border: 1px solid var(--border-color);">
            <div style="display: flex; gap: 14px; align-items: center;">
                <div style="width: 72px; height: 72px; border-radius: 10px; overflow: hidden; background: #000; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <img id="voucher-preview-img" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div id="voucher-file-name" style="font-weight: 700; font-size: 0.85rem; color: var(--text-main); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">comprobante.jpg</div>
                    <div id="voucher-file-size" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Comprimiendo imagen...</div>
                    <div id="voucher-ocr-status" style="font-size: 0.78rem; font-weight: 600; color: var(--accent); margin-top: 4px;">
                        <i class="ph ph-spinner ph-spin"></i> Escaneando número de operación (OCR)...
                    </div>
                </div>
            </div>
        </div>

        <!-- Operation number input -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                <span><i class="ph ph-hash"></i> Número de Operación</span>
                <span id="ocr-badge-detected" style="display: none; font-size: 0.7rem; color: #10b981; font-weight: 700; background: rgba(16, 185, 129, 0.12); padding: 2px 8px; border-radius: 10px;">¡Detectado por OCR!</span>
            </label>
            <input type="text" id="voucher-operation-number" class="form-control" placeholder="Ej: 00129482" style="font-size: 1rem; font-weight: 700; letter-spacing: 1px; padding: 10px 14px; border-radius: 10px; width: 100%; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-main);">
            <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 5px;">Se rellena automáticamente mediante OCR. Puedes corregirlo si es necesario.</small>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-outline" onclick="closeVoucherUploadModal()" style="border-radius: 10px; padding: 8px 16px;">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btn-submit-voucher" onclick="submitVoucherUpload()" style="border-radius: 10px; font-weight: 700; padding: 8px 20px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="ph ph-check-circle"></i> Confirmar y Marcar Pagado
            </button>
        </div>
    </div>
</div>

<script>
const APP_BASE_URL = <?php echo json_encode(rtrim($baseUrl, '/') . '/'); ?>;
const IS_PUBLIC_PAGE = <?php echo $is_public ? 'true' : 'false'; ?>;

document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const noteId = urlParams.get('id') || 'NEW';
  const isPublic = IS_PUBLIC_PAGE || (urlParams.get('view') === 'public');

  if (isPublic) {
      document.body.classList.add('public-mode');
  }

  let servicios = [];
  let cronograma = [];
  let abonos = [];
  let isEditingServicio = false;
  let isEditingCuota = false;
  let isEditingAbono = false;
  let editingIndexServicio = -1;
  let editingIndexCuota = -1;
  let editingIndexAbono = -1;

  let paymentMethodsData = [];
  
  let currentVoucherUrl = '';
  let currentOperationNumber = '';
  let selectedVoucherFile = null;
  let compressedVoucherFile = null;

  // Client-Side Canvas Image Compressor
  function compressImageFile(file, maxWidth = 1200, maxHeight = 1200, quality = 0.8) {
      return new Promise((resolve) => {
          if (!file || !file.type.startsWith('image/')) {
              return resolve(file);
          }
          const reader = new FileReader();
          reader.onload = (e) => {
              const img = new Image();
              img.onload = () => {
                  let width = img.width;
                  let height = img.height;

                  if (width > maxWidth || height > maxHeight) {
                      if (width > height) {
                          height = Math.round((height * maxWidth) / width);
                          width = maxWidth;
                      } else {
                          width = Math.round((width * maxHeight) / height);
                          height = maxHeight;
                      }
                  }

                  const canvas = document.createElement('canvas');
                  canvas.width = width;
                  canvas.height = height;
                  const ctx = canvas.getContext('2d');
                  ctx.drawImage(img, 0, 0, width, height);

                  canvas.toBlob((blob) => {
                      if (!blob) {
                          resolve(file);
                          return;
                      }
                      const compressedFile = new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), {
                          type: 'image/jpeg',
                          lastModified: Date.now()
                      });
                      resolve(compressedFile);
                  }, 'image/jpeg', quality);
              };
              img.onerror = () => resolve(file);
              img.src = e.target.result;
          };
          reader.onerror = () => resolve(file);
          reader.readAsDataURL(file);
      });
  }

  // Tesseract OCR Reader for Operation Number
  async function extractOperationNumberFromImage(fileOrBlob) {
      try {
          if (!window.Tesseract) {
              console.warn('Tesseract OCR no está disponible');
              return null;
          }
          const worker = await Tesseract.createWorker('spa');
          const ret = await worker.recognize(fileOrBlob);
          await worker.terminate();

          const text = ret.data.text || '';
          console.log('Texto detectado por OCR:', text);

          // Regex patterns for Peruvian receipts (BCP, Interbank, BBVA, Yape, Plin, Scotiabank, etc.)
          const patterns = [
              /(?:n[uú]mero\s*(?:de)?\s*operaci[oó]n|n[°º.]?\s*(?:de)?\s*operaci[oó]n|nro\.?\s*operaci[oó]n|c[oó]digo\s*(?:de)?\s*operaci[oó]n|operaci[oó]n|n[°º.]?\s*op\.|op\.)\s*[:#\-]?\s*([0-9]{4,14})/i,
              /(?:ref|referencia)\s*[:#\-]?\s*([0-9]{6,12})/i,
              /\b([0-9]{6,10})\b/
          ];

          for (const pattern of patterns) {
              const match = text.match(pattern);
              if (match && match[1]) {
                  return match[1].trim();
              }
          }
          return null;
      } catch (err) {
          console.error('Error al ejecutar OCR:', err);
          return null;
      }
  }

  window.openVoucherUploadModal = function() {
      const modal = document.getElementById('modal-voucher-upload');
      if (!modal) return;
      const fileInput = document.getElementById('voucher-file-input');
      if (fileInput) fileInput.value = '';
      selectedVoucherFile = null;
      compressedVoucherFile = null;

      const previewArea = document.getElementById('voucher-preview-area');
      const dropZone = document.getElementById('voucher-drop-zone');
      const opInput = document.getElementById('voucher-operation-number');
      const ocrBadge = document.getElementById('ocr-badge-detected');

      if (previewArea) previewArea.style.display = 'none';
      if (dropZone) dropZone.style.display = 'block';
      if (opInput) opInput.value = currentOperationNumber || '';
      if (ocrBadge) ocrBadge.style.display = 'none';

      modal.style.display = 'flex';
  };

  window.closeVoucherUploadModal = function() {
      const modal = document.getElementById('modal-voucher-upload');
      if (modal) modal.style.display = 'none';
  };

  window.handleVoucherFileSelected = async function(input) {
      if (!input.files || !input.files[0]) return;
      selectedVoucherFile = input.files[0];

      const previewArea = document.getElementById('voucher-preview-area');
      const dropZone = document.getElementById('voucher-drop-zone');
      const previewImg = document.getElementById('voucher-preview-img');
      const fileNameEl = document.getElementById('voucher-file-name');
      const fileSizeEl = document.getElementById('voucher-file-size');
      const ocrStatus = document.getElementById('voucher-ocr-status');
      const opInput = document.getElementById('voucher-operation-number');
      const ocrBadge = document.getElementById('ocr-badge-detected');

      if (previewArea) previewArea.style.display = 'block';
      if (dropZone) dropZone.style.display = 'none';
      if (fileNameEl) fileNameEl.textContent = selectedVoucherFile.name;
      if (fileSizeEl) fileSizeEl.textContent = 'Comprimiendo imagen para optimizar carga...';
      if (ocrStatus) ocrStatus.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Procesando comprobante...';

      if (selectedVoucherFile.type.startsWith('image/')) {
          const reader = new FileReader();
          reader.onload = (e) => {
              if (previewImg) previewImg.src = e.target.result;
          };
          reader.readAsDataURL(selectedVoucherFile);

          // Compress image client-side
          try {
              compressedVoucherFile = await compressImageFile(selectedVoucherFile, 1200, 1200, 0.8);
              const originalKb = (selectedVoucherFile.size / 1024).toFixed(1);
              const compressedKb = (compressedVoucherFile.size / 1024).toFixed(1);
              if (fileSizeEl) fileSizeEl.textContent = `${originalKb} KB ➔ ${compressedKb} KB (Optimizado al 100%)`;
          } catch(e) {
              compressedVoucherFile = selectedVoucherFile;
          }

          // Run OCR
          if (ocrStatus) ocrStatus.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Escaneando N° de operación con OCR...';
          const detectedOp = await extractOperationNumberFromImage(compressedVoucherFile || selectedVoucherFile);

          if (detectedOp) {
              if (opInput) opInput.value = detectedOp;
              if (ocrBadge) ocrBadge.style.display = 'inline-block';
              if (ocrStatus) ocrStatus.innerHTML = `<span style="color:#10b981;"><i class="ph ph-check-circle"></i> ¡Operación detectada: <strong>${detectedOp}</strong>!</span>`;
          } else {
              if (ocrStatus) ocrStatus.innerHTML = '<span style="color:var(--text-muted);">Listo. Ingresa el número de operación si no fue detectado.</span>';
          }
      } else {
          // PDF or non-image
          if (previewImg) previewImg.src = 'assets/img/pdf-icon.png';
          if (fileSizeEl) fileSizeEl.textContent = `${(selectedVoucherFile.size / 1024).toFixed(1)} KB`;
          if (ocrStatus) ocrStatus.innerHTML = '<span style="color:var(--text-muted);">Archivo adjunto listo para enviar.</span>';
          compressedVoucherFile = selectedVoucherFile;
      }
  };

  window.submitVoucherUpload = async function() {
      const fileToUpload = compressedVoucherFile || selectedVoucherFile;
      const opNumber = (document.getElementById('voucher-operation-number')?.value || '').trim();

      if (!fileToUpload && !currentVoucherUrl) {
          alert('Por favor selecciona una foto o archivo de tu comprobante.');
          return;
      }

      const btn = document.getElementById('btn-submit-voucher');
      const orig = btn ? btn.innerHTML : '';
      if (btn) {
          btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
          btn.disabled = true;
      }

      const fd = new FormData();
      if (fileToUpload) {
          fd.append('voucher', fileToUpload);
      }
      fd.append('operation_number', opNumber);
      
      const currentToken = (existingNote && existingNote.token) || urlParams.get('token') || '';
      const currentCode = (existingNote && existingNote.id) || noteId || '';
      fd.append('token', currentToken);
      fd.append('note_code', currentCode);

      try {
          const res = await fetch(APP_BASE_URL + 'index.php?module=admin&action=ajax_upload_note_voucher', {
              method: 'POST',
              body: fd
          });
          const data = await res.json();
          if (data.success) {
              currentVoucherUrl = data.voucher_url;
              currentOperationNumber = data.operation_number;

              if (existingNote) {
                  existingNote.voucher_url = data.voucher_url;
                  existingNote.operation_number = data.operation_number;
                  existingNote.status = 'pagado';
                  (existingNote.cronograma || []).forEach(c => c.estado = 'pagado');
              }

              cronograma.forEach(c => c.estado = 'pagado');

              setDynamicFavicon(true);
              document.title = '✅ Pagada - Nota ' + (existingNote?.id || currentCode);

              updateAdminVoucherUI();
              closeVoucherUploadModal();
              renderPublicPreview();
              if (typeof showToast === 'function') {
                  showToast('¡Comprobante verificado y nota marcada como PAGADA!', 'success');
              } else {
                  alert('¡Comprobante subido con éxito y nota marcada como PAGADA!');
              }
          } else {
              alert(data.error || 'No se pudo registrar el comprobante.');
          }
      } catch (err) {
          alert('Error de conexión al subir el comprobante.');
      } finally {
          if (btn) {
              btn.innerHTML = orig;
              btn.disabled = false;
          }
      }
  };

  window.onAdminOpNumberChange = function(val) {
      currentOperationNumber = val;
      renderPublicPreview();
  };

  function updateAdminVoucherUI() {
      const opInput = document.getElementById('admin-note-op-number');
      const viewLink = document.getElementById('admin-voucher-view-link');
      if (opInput && currentOperationNumber) opInput.value = currentOperationNumber;
      if (viewLink) {
          if (currentVoucherUrl) {
              viewLink.href = currentVoucherUrl;
              viewLink.style.display = 'inline-flex';
          } else {
              viewLink.style.display = 'none';
          }
      }
  }
  
  // Load payment methods from DB
  async function loadPaymentMethodsFromDB() {
      try {
          const res = await fetch(APP_BASE_URL + 'modules/admin/ajax_get_payment_methods_public.php');
          const data = await res.json();
          if (data.success && data.methods.length > 0) {
              paymentMethodsData = data.methods;
          } else {
              // Fallback defaults
              paymentMethodsData = [
                  { label: "BCP SOLES", code: "19174092813024" },
                  { label: "BCP DOLARES", code: "19171286876143" },
                  { label: "YAPE / PLIN", code: "51 998 289 752" },
                  { label: "INTERBANK", code: "898-3282259003" },
                  { label: "SCOTIABANK", code: "006-0447141" }
              ];
          }
      } catch(e) {
          paymentMethodsData = [
              { label: "BCP SOLES", code: "19174092813024" },
              { label: "BCP DOLARES", code: "19171286876143" },
              { label: "YAPE / PLIN", code: "51 998 289 752" },
              { label: "INTERBANK", code: "898-3282259003" },
              { label: "SCOTIABANK", code: "006-0447141" }
          ];
      }
  }

  let existingNote = null;
  const rawData = urlParams.get('data'); // Fallback for old links
  const tokenData = '<?php echo $public_note_data ? htmlspecialchars($public_note_data, ENT_QUOTES) : ""; ?>';
  
  if (isPublic) {
      try {
          if (tokenData) {
              existingNote = JSON.parse(atob(tokenData));
          } else if (rawData) {
              existingNote = JSON.parse(atob(decodeURIComponent(rawData)));
          }
      } catch (e) {
          console.error("Invalid note data", e);
      }
      
      // PIN Protection
      if (existingNote && existingNote.has_pin) {
          const noteToken = existingNote.token || urlParams.get('token');
          showPinOverlay(noteToken);
      }
      
      // Track view
      const noteToken = existingNote?.token || urlParams.get('token');
      trackNoteView(noteToken);
      
      // Dynamic favicon & title
      if (existingNote) {
          const totalAbo = (existingNote.abonos || []).reduce((s, a) => s + parseFloat(a.monto || 0), 0);
          const totalServ = (existingNote.servicios || []).reduce((s, sv) => s + (sv.cantidad * sv.costoUnit), 0);
          const totalCrono = (existingNote.cronograma || []).reduce((s, c) => s + parseFloat(c.costoUnit || 0), 0);
          const isPaid = (totalServ + totalCrono - totalAbo) <= 0;
          setDynamicFavicon(isPaid);
          document.title = (isPaid ? '✅ Pagada' : '🔴 Pendiente') + ' - Nota ' + (existingNote.id || '');
      }
      
      // Load payment methods from DB, then init webview
      loadPaymentMethodsFromDB().then(() => initWebview());
  } else {
      if (noteId !== 'NEW') {
          fetch(APP_BASE_URL + 'modules/admin/ajax_get_payment_notes.php')
              .then(res => res.json())
              .then(data => {
                  if (data.success) {
                      existingNote = data.notes.find(n => n.id === noteId);
                  }
                  loadPaymentMethodsFromDB().then(() => initWebview());
              });
      } else {
          loadPaymentMethodsFromDB().then(() => initWebview());
      }
  }

  function initWebview() {
  document.getElementById('note-client').addEventListener('change', function() {
      const selectedOption = this.options[this.selectedIndex];
      const brandsStr = selectedOption ? selectedOption.getAttribute('data-brands') : '';
      const servicesStr = selectedOption ? selectedOption.getAttribute('data-services') : '';
      const membershipsStr = selectedOption ? selectedOption.getAttribute('data-memberships') : '';
      const companySelect = document.getElementById('note-company');
      
      companySelect.innerHTML = '<option value="">Selecciona una marca...</option>';
      
      if (brandsStr) {
          const brands = brandsStr.split('||');
          const services = servicesStr ? servicesStr.split('||') : [];
          const memberships = membershipsStr ? membershipsStr.split('||') : [];
          brands.forEach((brand, idx) => {
              if (brand) {
                  const opt = document.createElement('option');
                  opt.value = brand;
                  opt.textContent = brand;
                  opt.setAttribute('data-services', services[idx] || '[]');
                  opt.setAttribute('data-has-membership', memberships[idx] || '0');
                  companySelect.appendChild(opt);
              }
          });
      }
      companySelect.dispatchEvent(new Event('change'));
  });

  // Restore state if browser preserved the select value (e.g. via back button)
  const clientSelect = document.getElementById('note-client');
  if (clientSelect && clientSelect.value) {
      clientSelect.dispatchEvent(new Event('change'));
  }

  document.getElementById('note-company').addEventListener('change', function() {
      const selectedOption = this.options[this.selectedIndex];
      const hasMembership = selectedOption ? selectedOption.getAttribute('data-has-membership') : '0';
      const servicesIdsStr = selectedOption ? selectedOption.getAttribute('data-services') : '[]';
      
      let servicesIds = [];
      try {
          const parsed = JSON.parse(servicesIdsStr);
          if (Array.isArray(parsed)) {
              servicesIds = parsed;
          }
      } catch(e) {}
      
      let datalist = document.getElementById('membresias-list');
      if (!datalist) {
          datalist = document.createElement('datalist');
          datalist.id = 'membresias-list';
          document.body.appendChild(datalist);
      }
      datalist.innerHTML = '';
      
      // Add services that are assigned to this brand
      servicesIds.forEach(id => {
          const s = SYSTEM_SERVICES.find(serv => serv.id.toString() === id.toString());
          if (s) {
              const option = document.createElement('option');
              option.value = s.name;
              datalist.appendChild(option);
          }
      });
      
      // We will also use this logic in the add button
  });

  if (existingNote) {
      servicios = existingNote.servicios || [];
      cronograma = existingNote.cronograma || [];
      abonos = existingNote.abonos || [];
      document.getElementById('invoice-header-info').innerHTML = `<strong>${existingNote.id}</strong> &middot; THE ROMA AGENCY CORPORACION S.A.C.`;
      
      const clientSelect = document.getElementById('note-client');
      let optionExists = Array.from(clientSelect.options).some(opt => opt.value === existingNote.client);
      if (!optionExists && existingNote.client) {
          const opt = document.createElement('option');
          opt.value = existingNote.client;
          opt.textContent = existingNote.client;
          clientSelect.appendChild(opt);
      }
      
      clientSelect.value = existingNote.client || '';
      clientSelect.dispatchEvent(new Event('change'));
      
      const companySelect = document.getElementById('note-company');
      let companyExists = Array.from(companySelect.options).some(opt => opt.value === existingNote.company);
      if (!companyExists && existingNote.company) {
          const opt = document.createElement('option');
          opt.value = existingNote.company;
          opt.textContent = existingNote.company;
          companySelect.appendChild(opt);
      }
      
      companySelect.value = existingNote.company || '';
      document.getElementById('note-start-date').value = existingNote.startDate || '';
      
      if (isPublic) {
          document.getElementById('public-banner-id').innerText = existingNote.id;
          document.getElementById('public-client-text').innerText = existingNote.client || 'Sin especificar';
          document.getElementById('public-client-text').style.display = 'block';
          document.getElementById('public-company-text').innerText = existingNote.company || 'Sin especificar';
          document.getElementById('public-company-text').style.display = 'block';
          document.getElementById('public-date-text').innerText = existingNote.startDate || 'Sin especificar';
          document.getElementById('public-date-text').style.display = 'block';
      }
  } else {
      document.getElementById('note-start-date').valueAsDate = new Date();
  }

  const toggleMembership = document.getElementById('toggle-membership');
  const cronogramaCard = document.getElementById('cronograma-card');
  if (toggleMembership && cronogramaCard) {
      toggleMembership.addEventListener('change', (e) => {
          cronogramaCard.style.display = e.target.checked ? 'block' : 'none';
          actualizarResumen();
          renderPublicPreview();
      });
      // Set initial state
      if (existingNote) {
          toggleMembership.checked = existingNote.show_memberships !== undefined ? existingNote.show_memberships : (existingNote.cronograma && existingNote.cronograma.length > 0);
      } else {
          toggleMembership.checked = false; // default to false for new notes
      }
      // Apply initial display
      cronogramaCard.style.display = toggleMembership.checked ? 'block' : 'none';
  }

  const toggleAbonos = document.getElementById('toggle-abonos');
  const abonosCard = document.getElementById('abonos-card');
  if (toggleAbonos && abonosCard) {
      toggleAbonos.addEventListener('change', (e) => {
          abonosCard.style.display = e.target.checked ? 'block' : 'none';
          actualizarResumen();
          renderPublicPreview();
      });
      // Set initial state
      if (existingNote) {
          toggleAbonos.checked = existingNote.show_advances !== undefined ? existingNote.show_advances : (existingNote.abonos && existingNote.abonos.length > 0);
      } else {
          toggleAbonos.checked = false; // default to false for new notes
      }
      abonosCard.style.display = toggleAbonos.checked ? 'block' : 'none';
  }

  const toggleIgv = document.getElementById('toggle-igv');
  if (toggleIgv) {
      toggleIgv.addEventListener('change', () => {
          actualizarResumen();
      });
      if (existingNote) {
          toggleIgv.checked = existingNote.apply_igv;
      }
  }

  const discountInput = document.getElementById('discount-input');
  if (discountInput) {
      discountInput.addEventListener('input', () => {
          actualizarResumen();
      });
      if (existingNote && existingNote.discount_percent) {
          discountInput.value = existingNote.discount_percent;
      }
  }

  // Load due_days and access_pin
  if (existingNote) {
      const dueDaysInput = document.getElementById('note-due-days');
      if (dueDaysInput && existingNote.due_days !== undefined) {
          dueDaysInput.value = existingNote.due_days;
      }
      const pinInput = document.getElementById('note-access-pin');
      if (pinInput && existingNote.access_pin) {
          pinInput.value = existingNote.access_pin;
      }
  }

  // Force re-render of cards and totals after loading existingNote
  renderServiciosCards();
  renderCronogramaCards();
  if (typeof renderAbonosCards === 'function') renderAbonosCards();
  actualizarResumen();
  
  // Add listeners for live preview updates
  document.getElementById('note-client').addEventListener('change', renderPublicPreview);
  document.getElementById('note-company').addEventListener('change', renderPublicPreview);
  document.getElementById('note-start-date').addEventListener('change', renderPublicPreview);
  
  } // end initWebview

  function totalServiciosCalc() {
    return servicios.reduce((sum, s) => sum + (s.cantidad * s.costoUnit), 0);
  }

  function isCronoBillable(c) {
      if (!c.fecha) return true;
      const today = new Date();
      today.setHours(0,0,0,0);
      const parts = c.fecha.split('-');
      const cuotaDate = new Date(parts[0], parts[1] - 1, parts[2]);
      const diffTime = cuotaDate.getTime() - today.getTime();
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      return diffDays <= 7;
  }

  function totalPendienteCrono() {
    const toggleMembership = document.getElementById('toggle-membership');
    if (toggleMembership && !toggleMembership.checked) return 0;

    return cronograma.filter(c => c.estado !== 'pagado' && isCronoBillable(c))
                     .reduce((sum, c) => sum + parseFloat(c.monto || 0), 0);
  }

  function totalAbonosCalc() {
    const toggleAbonos = document.getElementById('toggle-abonos');
    if (toggleAbonos && !toggleAbonos.checked) return 0;
    
    return abonos.reduce((sum, a) => sum + parseFloat(a.monto || 0), 0);
  }

  function actualizarResumen() {
    const totalServ = totalServiciosCalc();
    const totalPend = totalPendienteCrono();
    let subtotal = totalServ + totalPend;
    
    // If no services or schedule, but existingNote has a total, keep it
    if (subtotal === 0 && existingNote && existingNote.total > 0 && servicios.length === 0 && cronograma.length === 0) {
        subtotal = parseFloat(existingNote.total);
    }
    
    const elTotalServicios = document.getElementById('statTotalServicios');
    if (elTotalServicios) elTotalServicios.innerText = `S/ ${totalServ.toFixed(2)}`;
    
    const elPendiente = document.getElementById('statPendiente');
    if (elPendiente) elPendiente.innerText = `S/ ${totalPend.toFixed(2)}`;
    
    const discountInput = document.getElementById('discount-input');
    const discountPercent = discountInput ? parseFloat(discountInput.value) || 0 : 0;
    const discountAmount = subtotal * (discountPercent / 100);
    
    const baseForIgv = subtotal - discountAmount;
    
    const toggleIgv = document.getElementById('toggle-igv');
    const applyIgv = toggleIgv ? toggleIgv.checked : false;
    const igvAmount = applyIgv ? baseForIgv * 0.18 : 0;
    
    const totalGeneral = baseForIgv + igvAmount;
    
    const rowSubtotal = document.getElementById('row-stat-subtotal');
    const elSubtotal = document.getElementById('statSubtotal');
    if (discountAmount > 0 || applyIgv) {
        if (rowSubtotal) rowSubtotal.style.display = 'table-row';
        if (elSubtotal) elSubtotal.innerText = `S/ ${subtotal.toFixed(2)}`;
    } else {
        if (rowSubtotal) rowSubtotal.style.display = 'none';
    }
    
    const rowDiscount = document.getElementById('row-stat-discount');
    const elDiscount = document.getElementById('statDiscount');
    if (discountAmount > 0) {
        if (rowDiscount) rowDiscount.style.display = 'table-row';
        if (elDiscount) elDiscount.innerText = `-S/ ${discountAmount.toFixed(2)}`;
    } else {
        if (rowDiscount) rowDiscount.style.display = 'none';
    }
    
    const rowIgv = document.getElementById('row-stat-igv');
    const elIgv = document.getElementById('statIgv');
    if (applyIgv) {
        if (rowIgv) rowIgv.style.display = 'table-row';
        if (elIgv) elIgv.innerText = `S/ ${igvAmount.toFixed(2)}`;
    } else {
        if (rowIgv) rowIgv.style.display = 'none';
    }

    const elGeneral = document.getElementById('statGeneral');
    if (elGeneral) elGeneral.innerText = `S/ ${totalGeneral.toFixed(2)}`;
    
    const totalAbo = totalAbonosCalc();
    const saldo = totalGeneral - totalAbo;
    
    const rowAbonos = document.getElementById('row-stat-abonos');
    const elAbonos = document.getElementById('statAbonos');
    const rowSaldo = document.getElementById('row-stat-saldo');
    const elSaldo = document.getElementById('statSaldo');
    
    const toggleAbonos = document.getElementById('toggle-abonos');
    if (toggleAbonos && toggleAbonos.checked && abonos.length > 0) {
        if (rowAbonos) rowAbonos.style.display = 'table-row';
        if (rowSaldo) rowSaldo.style.display = 'table-row';
        if (elAbonos) elAbonos.innerText = `S/ ${totalAbo.toFixed(2)}`;
        if (elSaldo) elSaldo.innerText = `S/ ${Math.max(0, saldo).toFixed(2)}`;
    } else {
        if (rowAbonos) rowAbonos.style.display = 'none';
        if (rowSaldo) rowSaldo.style.display = 'none';
    }
    
    renderPublicPreview();
  }

  function renderPublicPreview() {
    const container = document.getElementById('public-preview-render');
    if (!container) return;

    let client = document.getElementById('note-client')?.value || 'Cliente sin nombre';
    let company = document.getElementById('note-company')?.value || 'Sin empresa';
    let date = document.getElementById('note-start-date')?.value || '';
    let formattedDate = date;
    if (date) {
        const parts = date.split('-');
        const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        if (parts.length === 3) {
            formattedDate = `${parseInt(parts[2])} de ${months[parseInt(parts[1]) - 1]}, ${parts[0]}`;
        }
    }

    let html = `
        <div class="app-card app-animate app-animate-delay-1" style="padding: 14px 16px;">
            <div class="app-client-header">
                <div>
                    <div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">CLIENTE</div>
                    <div class="app-client-name">${escapeHtml(client)}</div>
                    <div class="app-client-company">${escapeHtml(company)}</div>
                </div>
                <div class="app-client-icon"><i class="ph ph-user"></i></div>
            </div>
            <div class="app-divider"></div>
            <div class="app-date">
                <i class="ph ph-calendar-blank" style="font-size: 1rem;"></i> ${formattedDate}
            </div>
        </div>
        
        <div class="app-section-title app-animate app-animate-delay-2">
            <span>DETALLE DE FACTURACIÓN</span>
            <i class="ph ph-question" style="font-size: 1.1rem; color: #047857;"></i>
        </div>
        
        <div class="app-detail-card app-animate app-animate-delay-3">
    `;
    
    let hasItems = false;
    
    if (servicios.length > 0) {
        servicios.forEach(s => {
            hasItems = true;
            html += `
                <div class="app-detail-item">
                    <div>
                        <div class="app-detail-name">
                            ${escapeHtml(s.servicio)}
                            <span class="app-badge-green app-badge-adicional">ADICIONAL</span>
                        </div>
                        <div class="app-detail-desc">${escapeHtml(s.descripcion || 'Servicios de mantenimiento mensual')}</div>
                    </div>
                    <div class="app-detail-price">S/ ${(s.cantidad * s.costoUnit).toFixed(2)}</div>
                </div>
            `;
        });
    }
    
    const toggleMembership = document.getElementById('toggle-membership');
    const includeMembership = toggleMembership ? toggleMembership.checked : true;
    
    if (includeMembership) {
        const visibleCrono = cronograma.filter(c => isCronoBillable(c));
        if(visibleCrono.length > 0) {
            visibleCrono.forEach(c => {
                hasItems = true;
                let statusBadge = c.estado === 'pagado' ? `<span class="badge-pagado" style="margin-left: 0;">Pagado</span>` : ``;
                html += `
                    <div class="app-detail-item">
                        <div>
                            <div class="app-detail-name">
                                ${escapeHtml(c.servicio)}
                                <span class="app-badge-green app-badge-membresia">MEMBRESÍA</span>
                                ${statusBadge}
                            </div>
                            <div class="app-detail-desc">Suscripción recurrente</div>
                        </div>
                        <div class="app-detail-price">S/ ${parseFloat(c.monto).toFixed(2)}</div>
                    </div>
                `;
            });
        }
    }
    
    if (!hasItems) {
        html += `<div class="app-detail-item" style="justify-content: center; color: #6b7280;">No hay ítems registrados</div>`;
    }

    const totalServ = totalServiciosCalc();
    const totalPend = totalPendienteCrono();
    let subtotal = totalServ + totalPend;
    
    if (subtotal === 0 && existingNote && existingNote.total > 0 && servicios.length === 0 && cronograma.length === 0) {
        subtotal = parseFloat(existingNote.total);
    }
    
    const discountInput = document.getElementById('discount-input');
    const discountPercent = discountInput ? parseFloat(discountInput.value) || 0 : 0;
    const discountAmount = subtotal * (discountPercent / 100);
    const baseForIgv = subtotal - discountAmount;
    const toggleIgv = document.getElementById('toggle-igv');
    const applyIgv = toggleIgv ? toggleIgv.checked : false;
    const igvAmount = applyIgv ? baseForIgv * 0.18 : 0;
    const totalGeneral = baseForIgv + igvAmount;
    
    if (discountAmount > 0 || applyIgv) {
        html += `<div class="app-detail-item" style="padding: 12px 20px;">
                    <div style="color: #6b7280; font-size: 0.95rem;">Subtotal</div>
                    <div style="font-weight: 600; color: #111827; font-size: 0.95rem;">S/ ${subtotal.toFixed(2)}</div>
                 </div>`;
    }
    if (discountAmount > 0) {
        html += `<div class="app-detail-item" style="padding: 12px 20px;">
                    <div style="color: #ef4444; font-size: 0.95rem;">Descuento (${discountPercent}%)</div>
                    <div style="font-weight: 600; color: #ef4444; font-size: 0.95rem;">-S/ ${discountAmount.toFixed(2)}</div>
                 </div>`;
    }
    if (applyIgv) {
        html += `<div class="app-detail-item" style="padding: 12px 20px;">
                    <div style="color: #6b7280; font-size: 0.95rem;">IGV (18%)</div>
                    <div style="font-weight: 600; color: #111827; font-size: 0.95rem;">S/ ${igvAmount.toFixed(2)}</div>
                 </div>`;
    }

    let totalAbo = 0;
    const toggleAbonos = document.getElementById('toggle-abonos');
    const includeAbonos = toggleAbonos ? toggleAbonos.checked : false;
    
    if (includeAbonos && abonos.length > 0) {
        abonos.forEach(a => {
            totalAbo += parseFloat(a.monto || 0);
            html += `
                <div class="app-detail-item" style="padding: 12px 20px;">
                    <div style="color: #059669; font-size: 0.95rem; font-weight: 600;">(-) Adelanto &middot; ${escapeHtml(a.metodo || '')}</div>
                    <div style="font-weight: 700; color: #059669; font-size: 0.95rem;">-S/ ${parseFloat(a.monto || 0).toFixed(2)}</div>
                </div>
            `;
        });
    }

    const saldo = Math.max(0, totalGeneral - totalAbo);
    let finalTotal = includeAbonos ? saldo : totalGeneral;
    let totalLabel = includeAbonos && abonos.length > 0 ? "Saldo a Pagar" : "Total a Pagar";

    html += `
            <div class="app-detail-total-area">
                <div class="app-total-label">${totalLabel}</div>
                <div class="app-total-value">S/ ${finalTotal.toFixed(2)}</div>
            </div>
        </div> <!-- End app-detail-card -->
    `;
    
    html += `
        <div class="app-section-title app-animate app-animate-delay-4" style="margin-top: 32px;">MÉTODOS DE PAGO</div>
        <div class="app-animate app-animate-delay-4" style="display: flex; flex-direction: column; gap: 12px;">
    `;
    
    paymentMethodsData.forEach(pm => {
        let iconClass = 'ph-bank';
        if (pm.label.toLowerCase().includes('yape') || pm.label.toLowerCase().includes('plin')) {
            iconClass = 'ph-device-mobile';
        } else if (pm.label.toLowerCase().includes('dolares')) {
            iconClass = 'ph-money';
        }
        
        const iconHtml = pm.image_url 
            ? `<img src="${pm.image_url}" alt="${pm.label}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">` 
            : `<i class="ph ${iconClass}"></i>`;
        
        html += `
            <div class="app-pm-card">
                <div class="app-pm-icon-wrap">${iconHtml}</div>
                <div class="app-pm-info">
                    <div class="app-pm-name">${pm.label}</div>
                    <div class="app-pm-acc">${pm.code}</div>
                </div>
                <div class="app-pm-copy" onclick="navigator.clipboard.writeText('${pm.code}'); showToast('Copiado al portapapeles', 'success');" title="Copiar"><i class="ph ph-copy"></i></div>
            </div>
        `;
    });
    html += `</div>`;

    // Due date badge
    let dueDays = parseInt(document.getElementById('note-due-days')?.value) || 30;
    let startDateStr = document.getElementById('note-start-date')?.value || '';
    if (startDateStr) {
        let startDate = new Date(startDateStr);
        let dueDate = new Date(startDate);
        dueDate.setDate(dueDate.getDate() + dueDays);
        let today = new Date();
        today.setHours(0,0,0,0);
        let diffMs = dueDate - today;
        let diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
        
        if (saldo > 0) {
            if (diffDays < 0) {
                html += `<div class="app-due-badge app-due-expired app-animate app-animate-delay-5"><i class="ph ph-warning-circle"></i> VENCIDA hace ${Math.abs(diffDays)} día${Math.abs(diffDays) !== 1 ? 's' : ''}</div>`;
            } else if (diffDays <= 7) {
                html += `<div class="app-due-badge app-due-warning app-animate app-animate-delay-5"><i class="ph ph-clock"></i> Vence en ${diffDays} día${diffDays !== 1 ? 's' : ''}</div>`;
            } else {
                html += `<div class="app-due-badge app-due-ok app-animate app-animate-delay-5"><i class="ph ph-calendar-check"></i> Vence en ${diffDays} días</div>`;
            }
        }
    }

    const currentVoucher = currentVoucherUrl || (existingNote && existingNote.voucher_url) || '';
    const currentOpNum = currentOperationNumber || (existingNote && existingNote.operation_number) || '';
    const isPaid = (saldo <= 0) || Boolean(currentVoucher) || (existingNote && (existingNote.status === 'pagado' || existingNote.status === 'PAGADO'));

    let btnClass = !isPaid ? "app-status-btn" : "app-status-btn app-status-paid";
    let btnText = !isPaid ? `<i class="ph ph-clock"></i> PENDIENTE DE PAGO` : `<i class="ph ph-check-circle"></i> PAGADO`;

    html += `
        <div class="${btnClass} app-animate app-animate-delay-5">
            ${btnText}
        </div>
    `;

    // Voucher Card (Verified or Upload Prompt)
    if (currentVoucher) {
        html += `
            <div class="app-voucher-card app-animate app-animate-delay-5" style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 18px; padding: 18px; margin-top: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 46px; height: 46px; border-radius: 14px; background: rgba(16, 185, 129, 0.14); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                            <i class="ph ph-seal-check"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: #10b981; letter-spacing: 0.5px;">Comprobante de Pago Verificado</div>
                            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin-top: 2px;">
                                ${currentOpNum ? 'N° Operación: <span style="color:var(--accent); font-family:monospace; font-size:1.05rem;">' + escapeHtml(currentOpNum) + '</span>' : 'Comprobante Adjunto'}
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="${escapeHtml(currentVoucher)}" target="_blank" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 6px; border-radius: 10px; font-weight: 600; padding: 8px 14px;">
                            <i class="ph ph-file-text"></i> Ver Voucher
                        </a>
                    </div>
                </div>
            </div>
        `;
    } else {
        html += `
            <div class="app-voucher-card app-animate app-animate-delay-5" style="background: var(--bg-surface); border: 1.5px dashed var(--border-color); border-radius: 18px; padding: 22px 18px; margin-top: 20px; text-align: center;">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(79, 70, 229, 0.1); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin: 0 auto 10px;">
                    <i class="ph ph-camera-plus"></i>
                </div>
                <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 4px; color: var(--text-main);">¿Ya realizaste tu transferencia o pago?</h3>
                <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 0 0 14px; max-width: 420px; margin-left: auto; margin-right: auto; line-height: 1.4;">
                    Sube una foto de tu voucher. Nuestro sistema leerá el <strong>número de operación con OCR</strong> y marcará la nota como <strong>PAGADA</strong> automáticamente.
                </p>
                <button type="button" class="btn btn-primary" onclick="openVoucherUploadModal()" style="border-radius: 12px; font-weight: 700; padding: 10px 22px; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem;">
                    <i class="ph ph-camera"></i> Subir Voucher / Comprobante
                </button>
            </div>
        `;
    }

    // Mercado Pago payment button (only in public mode and if pending)
    if (!isPaid && isPublic && MP_ENABLED) {
        html += `
            <div class="app-animate app-animate-delay-5" style="margin-top: 20px;">
                <button id="btn-mp-pay" onclick="initMercadoPagoPayment()" style="
                    width: 100%;
                    padding: 16px;
                    background: linear-gradient(135deg, #009ee3 0%, #0077b6 100%);
                    color: #ffffff;
                    border: none;
                    border-radius: 30px;
                    font-size: 1rem;
                    font-weight: 700;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                    box-shadow: 0 4px 16px rgba(0, 158, 227, 0.3);
                    transition: all 0.2s;
                ">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9v-2h2v2zm0-4H9V7h2v5zm4 4h-2v-2h2v2zm0-4h-2V7h2v5z"/></svg>
                    💳 Pagar S/ ${saldo.toFixed(2)} con Mercado Pago
                </button>
                <div id="mp-brick-container" style="margin-top: 16px; display: none;"></div>
                <div id="mp-payment-result" style="display: none; text-align: center; margin-top: 16px;"></div>
            </div>
        `;
    }

    // Verification seal
    html += `
        <div class="app-verification-seal app-animate app-animate-delay-5">
            <i class="ph ph-seal-check"></i>
            Nota verificada por The Roma Agency
            <i class="ph ph-lock-simple"></i>
        </div>
    `;

    container.innerHTML = html;
  }

  // === FAVICON DINÁMICO ===
  function setDynamicFavicon(isPaid) {
      const canvas = document.createElement('canvas');
      canvas.width = 32;
      canvas.height = 32;
      const ctx = canvas.getContext('2d');
      ctx.font = '28px serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(isPaid ? '✅' : '🔴', 16, 17);
      
      let link = document.querySelector("link[rel~='icon']");
      if (!link) {
          link = document.createElement('link');
          link.rel = 'icon';
          document.head.appendChild(link);
      }
      link.href = canvas.toDataURL('image/png');
  }

  // === TRACKING DE APERTURA ===
  function trackNoteView(token) {
      if (!token) return;
      fetch(APP_BASE_URL + 'modules/admin/ajax_track_note_view.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token: token })
      }).catch(() => {});
  }

  // === MERCADO PAGO INLINE PAYMENT ===
  let mpInitialized = false;
  window.initMercadoPagoPayment = async function() {
      const btn = document.getElementById('btn-mp-pay');
      const container = document.getElementById('mp-brick-container');
      const resultDiv = document.getElementById('mp-payment-result');
      
      if (!btn || !container) return;
      
      if (mpInitialized) {
          container.style.display = container.style.display === 'none' ? 'block' : 'none';
          return;
      }
      
      btn.disabled = true;
      btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Preparando pago...';
      
      const noteToken = existingNote?.token || new URLSearchParams(window.location.search).get('token');
      
      try {
          // Create preference
          const res = await fetch(APP_BASE_URL + 'modules/admin/ajax_create_mp_preference.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ token: noteToken })
          });
          const data = await res.json();
          
          if (!data.success) {
              btn.disabled = false;
              btn.innerHTML = '💳 Reintentar pago';
              btn.style.background = 'linear-gradient(135deg, #009ee3 0%, #0077b6 100%)';
              showToast(data.error || 'Error al iniciar pago');
              console.warn('MP Preference error:', data);
              return;
          }
          
          // Load MP SDK
          if (!window.MercadoPago) {
              const script = document.createElement('script');
              script.src = 'https://sdk.mercadopago.com/js/v2';
              script.onload = () => window.renderMPCheckout(data, btn, container, resultDiv, noteToken);
              document.head.appendChild(script);
          } else {
              window.renderMPCheckout(data, btn, container, resultDiv, noteToken);
          }
      } catch(err) {
          btn.disabled = false;
          btn.innerHTML = '💳 Error - Reintentar';
          console.error('MP Error:', err);
      }
  }
  
  window.renderMPCheckout = function(data, btn, container, resultDiv, noteToken) {
      if (typeof MercadoPago === 'undefined') {
          console.warn('MP SDK not loaded yet');
          if (btn) {
              btn.disabled = false;
              btn.innerHTML = '💳 Pagar S/ ' + (data && data.amount ? data.amount.toFixed(2) : '') + ' con Mercado Pago';
          }
          return;
      }
      const mp = new MercadoPago(data.public_key, { locale: 'es-PE' });
      
      container.style.display = 'block';
      container.innerHTML = '<div id="wallet_container"></div>';
      
      mp.bricks().create('wallet', 'wallet_container', {
          initialization: {
              preferenceId: data.preference_id,
              redirectMode: 'self'
          },
          customization: {
              texts: {
                  valueProp: 'security_details'
              }
          }
      });
      
      if (btn) {
          btn.innerHTML = '💳 Pagar S/ ' + (data && data.amount ? data.amount.toFixed(2) : '') + ' con Mercado Pago';
          btn.disabled = false;
      }
      mpInitialized = true;
  };

  // Check for MP return status
  (function checkMPReturn() {
      try {
          const params = new URLSearchParams(window.location.search);
          const mpStatus = params.get('mp_status');
          const paymentId = params.get('payment_id') || params.get('collection_id');
          
          if (mpStatus === 'approved' && isPublic) {
              const noteToken = params.get('token');
              fetch(APP_BASE_URL + 'modules/admin/ajax_mp_process_payment.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ token: noteToken, payment_id: paymentId, status: 'approved' })
              }).then(r => r.json()).then(data => {
                  if (data.success) {
                      showToast('¡Pago confirmado exitosamente!');
                  }
              }).catch(() => {});
          }
      } catch(e) { console.warn('MP return check:', e); }
  })();

  // === PIN OVERLAY ===
  function showPinOverlay(token) {
      // Check if already verified in this session
      if (sessionStorage.getItem('pin_verified_' + token)) return;
      
      const overlay = document.createElement('div');
      overlay.className = 'pin-overlay';
      overlay.id = 'pin-overlay';
      overlay.innerHTML = `
          <div class="pin-card">
              <div class="pin-card-logo"><i class="ph ph-lock-simple"></i></div>
              <h2>Nota Protegida</h2>
              <p>Ingresa el PIN de 4 dígitos para acceder a esta nota de pago.</p>
              <div class="pin-inputs">
                  <input type="number" maxlength="1" min="0" max="9" class="pin-digit" data-idx="0" autofocus>
                  <input type="number" maxlength="1" min="0" max="9" class="pin-digit" data-idx="1">
                  <input type="number" maxlength="1" min="0" max="9" class="pin-digit" data-idx="2">
                  <input type="number" maxlength="1" min="0" max="9" class="pin-digit" data-idx="3">
              </div>
              <div class="pin-error-msg" id="pin-error-msg"></div>
              <button class="pin-submit-btn" id="pin-submit-btn">Verificar PIN</button>
          </div>
      `;
      document.body.appendChild(overlay);
      
      // Hide main content
      const mainContent = document.querySelector('.payment-notes-container');
      if (mainContent) mainContent.style.display = 'none';
      
      // Auto-focus and auto-advance
      const inputs = overlay.querySelectorAll('.pin-digit');
      inputs.forEach((input, idx) => {
          input.addEventListener('input', (e) => {
              let val = e.target.value;
              if (val.length > 1) e.target.value = val.slice(-1);
              if (e.target.value && idx < 3) inputs[idx + 1].focus();
          });
          input.addEventListener('keydown', (e) => {
              if (e.key === 'Backspace' && !e.target.value && idx > 0) {
                  inputs[idx - 1].focus();
              }
              if (e.key === 'Enter') {
                  document.getElementById('pin-submit-btn').click();
              }
          });
      });
      setTimeout(() => inputs[0].focus(), 100);
      
      // Submit
      document.getElementById('pin-submit-btn').addEventListener('click', async () => {
          const pin = Array.from(inputs).map(i => i.value).join('');
          if (pin.length < 4) {
              document.getElementById('pin-error-msg').textContent = 'Ingresa los 4 dígitos';
              return;
          }
          
          const btn = document.getElementById('pin-submit-btn');
          btn.disabled = true;
          btn.textContent = 'Verificando...';
          
          try {
              const res = await fetch(APP_BASE_URL + 'modules/admin/ajax_verify_note_pin.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ token: token, pin: pin })
              });
              const data = await res.json();
              
              if (data.success) {
                  sessionStorage.setItem('pin_verified_' + token, '1');
                  overlay.remove();
                  if (mainContent) mainContent.style.display = '';
              } else {
                  document.getElementById('pin-error-msg').textContent = data.error || 'PIN incorrecto';
                  inputs.forEach(i => {
                      i.value = '';
                      i.classList.add('pin-error');
                      setTimeout(() => i.classList.remove('pin-error'), 500);
                  });
                  inputs[0].focus();
                  btn.disabled = false;
                  btn.textContent = 'Verificar PIN';
              }
          } catch (err) {
              document.getElementById('pin-error-msg').textContent = 'Error de conexión';
              btn.disabled = false;
              btn.textContent = 'Verificar PIN';
          }
      });
  }

  function setupAutocomplete(inputId, descId, costId) {
      const input = document.getElementById(inputId);
      if (!input) return;
      
      const dropdown = document.createElement('div');
      dropdown.className = 'autocomplete-dropdown';
      dropdown.style.position = 'absolute';
      dropdown.style.display = 'none';
      dropdown.style.zIndex = '9999';
      document.body.appendChild(dropdown);
      
      function renderDropdown(filterText = '') {
          dropdown.innerHTML = '';
          const lowerFilter = filterText.toLowerCase();
          const filtered = SYSTEM_SERVICES.filter(s => s.name.toLowerCase().includes(lowerFilter));
          
          if (filtered.length === 0) {
              dropdown.style.display = 'none';
              return;
          }
          
          filtered.forEach((s) => {
              const item = document.createElement('div');
              item.className = 'autocomplete-item';
              item.textContent = s.name;
              item.addEventListener('mousedown', (e) => {
                  e.preventDefault();
                  input.value = s.name;
                  dropdown.style.display = 'none';
                  if (descId) document.getElementById(descId).value = s.description || '';
                  if (costId) document.getElementById(costId).value = s.price || 0;
              });
              dropdown.appendChild(item);
          });
          
          const rect = input.getBoundingClientRect();
          dropdown.style.top = (rect.bottom + window.scrollY + 4) + 'px';
          dropdown.style.left = (rect.left + window.scrollX) + 'px';
          dropdown.style.width = rect.width + 'px';
          dropdown.style.display = 'block';
      }

      input.addEventListener('focus', () => renderDropdown(input.value));
      input.addEventListener('input', () => {
          renderDropdown(input.value);
          const s = SYSTEM_SERVICES.find(serv => serv.name.toLowerCase() === input.value.trim().toLowerCase());
          if (s) {
              if (descId) document.getElementById(descId).value = s.description || '';
              if (costId) document.getElementById(costId).value = s.price || 0;
          }
      });
      input.addEventListener('blur', () => {
          dropdown.style.display = 'none';
      });
      
      window.addEventListener('resize', () => {
          if (dropdown.style.display === 'block') {
              const rect = input.getBoundingClientRect();
              dropdown.style.top = (rect.bottom + window.scrollY + 4) + 'px';
              dropdown.style.left = (rect.left + window.scrollX) + 'px';
              dropdown.style.width = rect.width + 'px';
          }
      });
  }

  function renderServiciosCards() {
    const container = document.getElementById('serviciosCardsContainer');
    if (!container) return;
    container.innerHTML = '';
    
    if (servicios.length === 0 && !isEditingServicio) {
      container.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align:center; color:var(--text-muted)">No hay servicios registrados</td></tr>';
    } 

    servicios.forEach((item, idx) => {
      if (idx === editingIndexServicio) {
        const editRow = document.createElement('tr');
        editRow.style.backgroundColor = 'var(--hover-card)';
        editRow.innerHTML = `
          <td>
            <input type="text" id="edit-serv-name-${idx}" class="inline-input" autocomplete="off" value="${escapeHtml(item.servicio)}" style="margin-bottom:4px; width: 100%; border-bottom: 1px solid var(--border); padding-bottom: 4px;" placeholder="Escribe o selecciona un servicio...">
            <input type="text" id="edit-serv-desc-${idx}" class="inline-input" value="${escapeHtml(item.descripcion)}" placeholder="Descripción">
          </td>
          <td style="text-align: center;">
            <input type="number" id="edit-serv-cant-${idx}" class="inline-input" value="${item.cantidad}" min="1" style="width: 70px; text-align: center;">
          </td>
          <td style="text-align: right;">
            <input type="number" id="edit-serv-cost-${idx}" class="inline-input" value="${item.costoUnit}" step="0.01" style="width: 100px; text-align: right;">
          </td>
          <td style="text-align: right; font-weight: 600;">--</td>
          <td style="text-align: right;">
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
              <button class="btn-icon-sm" id="save-edit-serv-${idx}" style="background: var(--primary-color); color: white; padding: 6px 10px;"><i class="ph ph-check"></i></button>
              <button class="btn-icon-sm" id="cancel-edit-serv-${idx}" style="padding: 6px 10px;"><i class="ph ph-x"></i></button>
            </div>
          </td>
        `;
        container.appendChild(editRow);
        setupAutocomplete(`edit-serv-name-${idx}`, `edit-serv-desc-${idx}`, `edit-serv-cost-${idx}`);


        document.getElementById(`save-edit-serv-${idx}`).addEventListener('click', () => {
           const selectEl = document.getElementById(`edit-serv-name-${idx}`);
           const selectedText = selectEl.value.trim();
           item.servicio = selectedText || 'Servicio';
           item.descripcion = document.getElementById(`edit-serv-desc-${idx}`).value || '';
           item.cantidad = parseFloat(document.getElementById(`edit-serv-cant-${idx}`).value) || 1;
           item.costoUnit = parseFloat(document.getElementById(`edit-serv-cost-${idx}`).value) || 0;
           
           editingIndexServicio = -1;
           renderServiciosCards();
           actualizarResumen();
        });

        document.getElementById(`cancel-edit-serv-${idx}`).addEventListener('click', () => {
           editingIndexServicio = -1;
           renderServiciosCards();
        });
      } else {
        const totalItem = item.cantidad * item.costoUnit;
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>
            <div style="font-weight: 600; color: var(--text-main); margin-bottom: 2px;">${escapeHtml(item.servicio)}</div>
            ${item.descripcion ? `<div style="font-size: 0.8rem; color: var(--text-muted);">${escapeHtml(item.descripcion)}</div>` : ''}
          </td>
          <td style="text-align: center;">${item.cantidad}</td>
          <td style="text-align: right;">S/ ${parseFloat(item.costoUnit).toFixed(2)}</td>
          <td style="text-align: right; font-weight: 700; color: var(--primary-color);">S/ ${totalItem.toFixed(2)}</td>
          <td style="text-align: right;">
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
              <button class="btn-icon-sm edit-servicio" data-idx="${idx}" style="padding: 6px 10px;"><i class="ph ph-pencil"></i></button>
              <button class="btn-icon-sm delete-servicio" data-idx="${idx}" style="padding: 6px 10px; color: #ef4444;"><i class="ph ph-trash"></i></button>
            </div>
          </td>
        `;
        container.appendChild(row);
      }
    });

    if (isEditingServicio) {
      const newRow = document.createElement('tr');
      newRow.style.backgroundColor = 'var(--hover-card)';
      newRow.innerHTML = `
        <td>
          <input type="text" id="new-serv-name" class="inline-input" autocomplete="off" style="margin-bottom:4px; width: 100%; border-bottom: 1px solid var(--border); padding-bottom: 4px;" placeholder="Escribe o selecciona un servicio...">
          <input type="text" id="new-serv-desc" class="inline-input" placeholder="Descripción breve">
        </td>
        <td style="text-align: center;">
          <input type="number" id="new-serv-cant" class="inline-input" value="1" min="1" style="width: 70px; text-align: center;">
        </td>
        <td style="text-align: right;">
          <input type="number" id="new-serv-cost" class="inline-input" placeholder="0.00" step="0.01" style="width: 100px; text-align: right;">
        </td>
        <td style="text-align: right; font-weight: 600;">--</td>
        <td style="text-align: right;">
          <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <button class="btn-icon-sm" id="save-new-serv" style="background: var(--primary-color); color: white; padding: 6px 10px;"><i class="ph ph-check"></i></button>
            <button class="btn-icon-sm" id="cancel-new-serv" style="padding: 6px 10px;"><i class="ph ph-x"></i></button>
          </div>
        </td>
      `;
      container.appendChild(newRow);
      setupAutocomplete('new-serv-name', 'new-serv-desc', 'new-serv-cost');

      document.getElementById('save-new-serv').addEventListener('click', () => {
         const selectEl = document.getElementById('new-serv-name');
         const name = selectEl.value.trim();
         if (!name) {
             alert('Por favor ingresa o selecciona un servicio.');
             return;
         }
         const desc = document.getElementById('new-serv-desc').value || '';
         const cant = parseFloat(document.getElementById('new-serv-cant').value) || 1;
         const cost = parseFloat(document.getElementById('new-serv-cost').value) || 0;
         
         servicios.push({ servicio: name, descripcion: desc, cantidad: cant, costoUnit: cost });
         isEditingServicio = false;
         renderServiciosCards();
         actualizarResumen();
      });

      document.getElementById('cancel-new-serv').addEventListener('click', () => {
         isEditingServicio = false;
         renderServiciosCards();
      });
      
      document.getElementById('new-serv-name').focus();
    }

    document.querySelectorAll('.edit-servicio').forEach(btn => {
      btn.addEventListener('click', () => {
        editingIndexServicio = parseInt(btn.dataset.idx);
        renderServiciosCards();
      });
    });

    document.querySelectorAll('.delete-servicio').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx);
        servicios.splice(idx, 1);
        renderServiciosCards();
        actualizarResumen();
      });
    });

    actualizarResumen();
  }

  function autoFillMes(dateString, targetInputId) {
      if (!dateString) return;
      const parts = dateString.split('-');
      if (parts.length >= 2) {
          const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
          const m = parseInt(parts[1], 10);
          if (m >= 1 && m <= 12) {
              const el = document.getElementById(targetInputId);
              if (el) el.value = monthNames[m - 1];
          }
      }
  }

  function renderCronogramaCards() {
    const container = document.getElementById('cronogramaCardsContainer');
    if (!container) return;
    container.innerHTML = '';
    
    if (cronograma.length === 0 && !isEditingCuota) {
      container.innerHTML = '<tr><td colspan="6" style="padding: 20px; text-align:center; color:var(--text-muted)">No hay historial registrado</td></tr>';
    } 

    cronograma.forEach((item, idx) => {
      if (idx === editingIndexCuota) {
        const editRow = document.createElement('tr');
        editRow.style.backgroundColor = 'var(--hover-card)';
        editRow.innerHTML = `
          <td>
            <input type="text" id="edit-crono-name-${idx}" class="inline-input" value="${escapeHtml(item.servicio)}" placeholder="Servicio" style="width: 100%;">
          </td>
          <td>
            <input type="text" id="edit-crono-mes-${idx}" class="inline-input" value="${escapeHtml(item.mes || '')}" placeholder="Mes" style="width: 100%;">
          </td>
          <td>
            <input type="date" id="edit-crono-date-${idx}" class="inline-input" value="${item.fecha}" style="width: 100%;">
          </td>
          <td style="text-align: right;">
            <input type="number" id="edit-crono-cost-${idx}" class="inline-input" value="${item.monto}" step="0.01" style="width: 90px; text-align: right;">
          </td>
          <td style="text-align: center;">
            <select id="edit-crono-status-${idx}" class="inline-input" style="width: 100px;">
                <option value="pendiente" ${item.estado === 'pendiente' ? 'selected' : ''}>Pendiente</option>
                <option value="pagado" ${item.estado === 'pagado' ? 'selected' : ''}>Pagado</option>
            </select>
          </td>
          <td style="text-align: right;">
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
              <button class="btn-icon-sm" id="save-edit-crono-${idx}" style="background: var(--primary-color); color: white; padding: 6px 10px;"><i class="ph ph-check"></i></button>
              <button class="btn-icon-sm" id="cancel-edit-crono-${idx}" style="padding: 6px 10px;"><i class="ph ph-x"></i></button>
            </div>
          </td>
        `;
        container.appendChild(editRow);

        document.getElementById(`edit-crono-date-${idx}`).addEventListener('change', (e) => {
            autoFillMes(e.target.value, `edit-crono-mes-${idx}`);
        });

        document.getElementById(`save-edit-crono-${idx}`).addEventListener('click', () => {
           item.servicio = document.getElementById(`edit-crono-name-${idx}`).value || 'Membresía';
           item.mes = document.getElementById(`edit-crono-mes-${idx}`).value || '';
           item.monto = parseFloat(document.getElementById(`edit-crono-cost-${idx}`).value) || 0;
           item.fecha = document.getElementById(`edit-crono-date-${idx}`).value;
           item.estado = document.getElementById(`edit-crono-status-${idx}`).value;
           
           editingIndexCuota = -1;
           renderCronogramaCards();
           actualizarResumen();
        });

        document.getElementById(`cancel-edit-crono-${idx}`).addEventListener('click', () => {
           editingIndexCuota = -1;
           renderCronogramaCards();
        });
      } else {
        const row = document.createElement('tr');
        if (item.estado === 'pagado') {
            row.classList.add('is-paid');
        }

        let statusInfo = { text: 'En proceso', class: 'status-pending', bg: 'rgba(245, 158, 11, 0.15)', color: 'var(--warning-color)' };
        if (item.estado === 'pagado') {
            statusInfo = { text: 'Pagado', class: 'status-paid', bg: 'var(--paid-bg)', color: 'var(--paid-text)' };
        } else {
            if (item.fecha) {
                const today = new Date();
                today.setHours(0,0,0,0);
                const parts = item.fecha.split('-');
                const cuotaDate = new Date(parts[0], parts[1] - 1, parts[2]);
                const diffTime = cuotaDate.getTime() - today.getTime();
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays < -15) {
                    statusInfo = { text: 'Retrasado', class: 'status-delayed', bg: 'rgba(239, 68, 68, 0.15)', color: '#ef4444' };
                } else if (diffDays <= 7 && diffDays >= -15) {
                    statusInfo = { text: 'En proceso', class: 'status-pending', bg: 'rgba(245, 158, 11, 0.15)', color: 'var(--warning-color)' };
                } else {
                    statusInfo = { text: 'No Activo', class: 'status-inactive', bg: 'rgba(100, 116, 139, 0.15)', color: '#64748b' };
                }
            }
        }

        row.innerHTML = `
          <td><div style="font-weight: 600;">${escapeHtml(item.servicio)}</div></td>
          <td>${escapeHtml(item.mes || '-')}</td>
          <td>${item.fecha || '-'}</td>
          <td style="text-align: right; font-weight: 600;">S/ ${parseFloat(item.monto).toFixed(2)}</td>
          <td style="text-align: center;">
            <span class="status-pill ${statusInfo.class}" style="background: ${statusInfo.bg}; color: ${statusInfo.color}; border: 0.5px solid ${statusInfo.color}; display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem;">${statusInfo.text}</span>
          </td>
          <td style="text-align: right;">
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
              <button class="btn-icon-sm edit-crono" data-idx="${idx}" style="padding: 6px 10px;" title="Editar"><i class="ph ph-pencil"></i></button>
              <button class="btn-icon-sm toggle-estado" data-idx="${idx}" style="padding: 6px 10px;" title="Cambiar Estado"><i class="ph ph-arrows-left-right"></i></button>
              <button class="btn-icon-sm delete-crono" data-idx="${idx}" style="padding: 6px 10px; color: #ef4444;" title="Eliminar"><i class="ph ph-trash"></i></button>
            </div>
          </td>
        `;
        container.appendChild(row);
      }
    });

    if (isEditingCuota) {
      const newRow = document.createElement('tr');
      newRow.style.backgroundColor = 'var(--hover-card)';
      newRow.innerHTML = `
        <td>
          <input type="text" id="new-crono-name" class="inline-input" placeholder="Servicio de Membresía" style="width: 100%;" list="membresias-list">
        </td>
        <td>
          <input type="text" id="new-crono-mes" class="inline-input" placeholder="Mes" style="width: 100%;">
        </td>
        <td>
          <input type="date" id="new-crono-date" class="inline-input" style="width: 100%;">
        </td>
        <td style="text-align: right;">
          <input type="number" id="new-crono-cost" class="inline-input" placeholder="0.00" step="0.01" style="width: 90px; text-align: right;">
        </td>
        <td style="text-align: center;">
          <select id="new-crono-status" class="inline-input" style="width: 100px;">
              <option value="pendiente">Pendiente</option>
              <option value="pagado">Pagado</option>
          </select>
        </td>
        <td style="text-align: right;">
          <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <button class="btn-icon-sm" id="save-new-crono" style="background: var(--primary-color); color: white; padding: 6px 10px;"><i class="ph ph-check"></i></button>
            <button class="btn-icon-sm" id="cancel-new-crono" style="padding: 6px 10px;"><i class="ph ph-x"></i></button>
          </div>
        </td>
      `;
      container.appendChild(newRow);

      const dateInput = document.getElementById('new-crono-date');
      dateInput.valueAsDate = new Date();
      autoFillMes(dateInput.value, 'new-crono-mes');
      dateInput.addEventListener('change', (e) => {
          autoFillMes(e.target.value, 'new-crono-mes');
      });

      // Auto-fill from brand if applicable
      const selectedCompanyOpt = document.getElementById('note-company').options[document.getElementById('note-company').selectedIndex];
      if (selectedCompanyOpt) {
          const hasMembership = selectedCompanyOpt.getAttribute('data-has-membership');
          const servicesIdsStr = selectedCompanyOpt.getAttribute('data-services') || '[]';
          if (hasMembership === '1') {
              try {
                  const servicesIds = JSON.parse(servicesIdsStr);
                  if (servicesIds.length > 0) {
                      const s = SYSTEM_SERVICES.find(serv => serv.id.toString() === servicesIds[0].toString());
                      if (s) {
                          document.getElementById('new-crono-name').value = s.name;
                          // Opcionalmente rellenar el costo si el servicio tiene
                          if (s.price) document.getElementById('new-crono-cost').value = s.price;
                      }
                  }
              } catch(e) {}
          }
      }

      document.getElementById('save-new-crono').addEventListener('click', () => {
         const name = document.getElementById('new-crono-name').value || 'Membresía';
         const mes = document.getElementById('new-crono-mes').value || '';
         const cost = parseFloat(document.getElementById('new-crono-cost').value) || 0;
         const date = document.getElementById('new-crono-date').value;
         const status = document.getElementById('new-crono-status').value;
         
         cronograma.push({ servicio: name, mes: mes, monto: cost, fecha: date, estado: status });
         isEditingCuota = false;
         renderCronogramaCards();
         actualizarResumen();
      });

      document.getElementById('cancel-new-crono').addEventListener('click', () => {
         isEditingCuota = false;
         renderCronogramaCards();
      });
      
      document.getElementById('new-crono-name').focus();
    }

    document.querySelectorAll('.edit-crono').forEach(btn => {
      btn.addEventListener('click', () => {
        editingIndexCuota = parseInt(btn.dataset.idx);
        renderCronogramaCards();
      });
    });

    document.querySelectorAll('.toggle-estado').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx);
        cronograma[idx].estado = cronograma[idx].estado === 'pendiente' ? 'pagado' : 'pendiente';
        renderCronogramaCards();
        actualizarResumen();
      });
    });

    document.querySelectorAll('.delete-crono').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx);
        cronograma.splice(idx, 1);
        renderCronogramaCards();
        actualizarResumen();
      });
    });

    actualizarResumen();
  }

  document.getElementById('agregarServicioBtn')?.addEventListener('click', () => {
      isEditingServicio = true;
      renderServiciosCards();
  });

  document.getElementById('agregarCuotaBtn')?.addEventListener('click', () => {
      isEditingCuota = true;
      renderCronogramaCards();
  });

  document.getElementById('agregarAbonoBtn')?.addEventListener('click', () => {
      isEditingAbono = true;
      renderAbonosCards();
  });

  function renderAbonosCards() {
    const container = document.getElementById('abonosCardsContainer');
    if (!container) return;
    container.innerHTML = '';
    
    if (abonos.length === 0 && !isEditingAbono) {
      container.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align:center; color:var(--text-muted)">No hay adelantos registrados</td></tr>';
    } 

    abonos.forEach((item, idx) => {
      if (idx === editingIndexAbono) {
        const editRow = document.createElement('tr');
        editRow.style.backgroundColor = 'var(--hover-card)';
        editRow.innerHTML = `
          <td>
            <input type="text" id="edit-abono-name-${idx}" class="inline-input" value="${escapeHtml(item.concepto || '')}" placeholder="Concepto (ej. Adelanto 50%)" style="width: 100%;">
          </td>
          <td>
            <input type="text" id="edit-abono-method-${idx}" class="inline-input" value="${escapeHtml(item.metodo || '')}" placeholder="Método / Banco" style="width: 100%;">
          </td>
          <td>
            <input type="date" id="edit-abono-date-${idx}" class="inline-input" value="${item.fecha || ''}" style="width: 100%;">
          </td>
          <td style="text-align: right;">
            <input type="number" id="edit-abono-cost-${idx}" class="inline-input" value="${item.monto || 0}" step="0.01" style="width: 90px; text-align: right;">
          </td>
          <td style="text-align: right;">
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
              <button class="btn-icon-sm" id="save-edit-abono-${idx}" style="background: var(--success); color: white; padding: 6px 10px;"><i class="ph ph-check"></i></button>
              <button class="btn-icon-sm" id="cancel-edit-abono-${idx}" style="padding: 6px 10px;"><i class="ph ph-x"></i></button>
            </div>
          </td>
        `;
        container.appendChild(editRow);

        document.getElementById(`save-edit-abono-${idx}`).addEventListener('click', () => {
           item.concepto = document.getElementById(`edit-abono-name-${idx}`).value || 'Adelanto';
           item.metodo = document.getElementById(`edit-abono-method-${idx}`).value || '';
           item.monto = parseFloat(document.getElementById(`edit-abono-cost-${idx}`).value) || 0;
           item.fecha = document.getElementById(`edit-abono-date-${idx}`).value;
           
           editingIndexAbono = -1;
           renderAbonosCards();
           actualizarResumen();
        });

        document.getElementById(`cancel-edit-abono-${idx}`).addEventListener('click', () => {
           editingIndexAbono = -1;
           renderAbonosCards();
        });
      } else {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td><div style="font-weight: 600;">${escapeHtml(item.concepto || 'Adelanto')}</div></td>
          <td>${escapeHtml(item.metodo || '-')}</td>
          <td>${item.fecha || '-'}</td>
          <td style="text-align: right; font-weight: 600; color: var(--success);">S/ ${parseFloat(item.monto || 0).toFixed(2)}</td>
          <td style="text-align: right;">
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
              <button class="btn-icon-sm edit-abono" data-idx="${idx}" style="padding: 6px 10px;" title="Editar"><i class="ph ph-pencil"></i></button>
              <button class="btn-icon-sm delete-abono" data-idx="${idx}" style="padding: 6px 10px; color: #ef4444;" title="Eliminar"><i class="ph ph-trash"></i></button>
            </div>
          </td>
        `;
        container.appendChild(row);
      }
    });

    if (isEditingAbono) {
      const newRow = document.createElement('tr');
      newRow.style.backgroundColor = 'var(--hover-card)';
      newRow.innerHTML = `
        <td>
          <input type="text" id="new-abono-name" class="inline-input" placeholder="Concepto (ej. Adelanto)" style="width: 100%;">
        </td>
        <td>
          <input type="text" id="new-abono-method" class="inline-input" placeholder="Método / Banco" style="width: 100%;">
        </td>
        <td>
          <input type="date" id="new-abono-date" class="inline-input" style="width: 100%;">
        </td>
        <td style="text-align: right;">
          <input type="number" id="new-abono-cost" class="inline-input" placeholder="0.00" step="0.01" style="width: 90px; text-align: right;">
        </td>
        <td style="text-align: right;">
          <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <button class="btn-icon-sm" id="save-new-abono" style="background: var(--success); color: white; padding: 6px 10px;"><i class="ph ph-check"></i></button>
            <button class="btn-icon-sm" id="cancel-new-abono" style="padding: 6px 10px;"><i class="ph ph-x"></i></button>
          </div>
        </td>
      `;
      container.appendChild(newRow);

      const dateInput = document.getElementById('new-abono-date');
      dateInput.valueAsDate = new Date();

      document.getElementById('save-new-abono').addEventListener('click', () => {
         const concepto = document.getElementById('new-abono-name').value || 'Adelanto';
         const metodo = document.getElementById('new-abono-method').value || '';
         const cost = parseFloat(document.getElementById('new-abono-cost').value) || 0;
         const date = document.getElementById('new-abono-date').value;
         
         abonos.push({ concepto: concepto, metodo: metodo, monto: cost, fecha: date });
         isEditingAbono = false;
         renderAbonosCards();
         actualizarResumen();
      });

      document.getElementById('cancel-new-abono').addEventListener('click', () => {
         isEditingAbono = false;
         renderAbonosCards();
      });
      
      document.getElementById('new-abono-name').focus();
    }

    document.querySelectorAll('.edit-abono').forEach(btn => {
      btn.addEventListener('click', () => {
        editingIndexAbono = parseInt(btn.dataset.idx);
        renderAbonosCards();
      });
    });

    document.querySelectorAll('.delete-abono').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx);
        abonos.splice(idx, 1);
        renderAbonosCards();
        actualizarResumen();
      });
    });

    actualizarResumen();
  }

  function initPaymentCopy() {
    const container = document.getElementById('paymentMethodsList');
    if (!container) return;
    container.innerHTML = '';
    paymentMethodsData.forEach(method => {
      const pill = document.createElement('div');
      pill.className = 'payment-item';
      
      const imgHtml = method.image_url 
          ? `<img src="${method.image_url}" alt="${method.label}" style="width: 32px; height: 32px; border-radius: 8px; object-fit: cover; flex-shrink: 0;">` 
          : `<i class="ph ph-bank" style="font-size: 1.3rem; color: var(--accent, #4f46e5); flex-shrink: 0;"></i>`;
      
      pill.innerHTML = `
        ${imgHtml}
        <div style="flex: 1; min-width: 0;">
            <div style="font-weight: 700; font-size: 0.85rem;">${method.label}</div>
            <div style="font-size: 0.8rem; color: #64748b;">${method.code}</div>
        </div>
        <span class="copy-icon"><i class="ph ph-copy"></i></span>
      `;
      pill.addEventListener('click', () => {
        navigator.clipboard.writeText(method.code).then(() => {
          showToast(`Copiado: ${method.code}`);
        }).catch(() => alert("No se pudo copiar"));
      });
      container.appendChild(pill);
    });
  }

  function showToast(msg) {
    let existing = document.querySelector('.toast-msg');
    if(existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'toast-msg';
    toast.innerText = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2000);
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
      if (m === '&') return '&amp;';
      if (m === '<') return '&lt;';
      if (m === '>') return '&gt;';
      return m;
    });
  }

  renderServiciosCards();
  renderCronogramaCards();
  initPaymentCopy();

  document.getElementById('btn-guardar-nota-total').addEventListener('click', () => {
      // Auto-save any open inline form
      if (isEditingServicio) {
          const name = document.getElementById('new-serv-name')?.value;
          if (name) {
              const desc = document.getElementById('new-serv-desc')?.value || '';
              const cant = parseFloat(document.getElementById('new-serv-cant')?.value) || 1;
              const cost = parseFloat(document.getElementById('new-serv-cost')?.value) || 0;
              servicios.push({ servicio: name, descripcion: desc, cantidad: cant, costoUnit: cost });
          }
          isEditingServicio = false;
      }
      if (editingIndexServicio !== -1) {
          const idx = editingIndexServicio;
          const name = document.getElementById(`edit-serv-name-${idx}`)?.value;
          if (name) {
              servicios[idx].servicio = name;
              servicios[idx].descripcion = document.getElementById(`edit-serv-desc-${idx}`)?.value || '';
              servicios[idx].cantidad = parseFloat(document.getElementById(`edit-serv-cant-${idx}`)?.value) || 1;
              servicios[idx].costoUnit = parseFloat(document.getElementById(`edit-serv-cost-${idx}`)?.value) || 0;
          }
          editingIndexServicio = -1;
      }
      if (isEditingCuota) {
          const name = document.getElementById('new-crono-name')?.value;
          if (name) {
              const mes = document.getElementById('new-crono-mes')?.value || '';
              const cost = parseFloat(document.getElementById('new-crono-cost')?.value) || 0;
              const date = document.getElementById('new-crono-date')?.value || '';
              const status = document.getElementById('new-crono-status')?.value || 'pendiente';
              cronograma.push({ servicio: name, mes: mes, monto: cost, fecha: date, estado: status });
          }
          isEditingCuota = false;
      }
      if (editingIndexCuota !== -1) {
          const idx = editingIndexCuota;
          const name = document.getElementById(`edit-crono-name-${idx}`)?.value;
          if (name) {
              cronograma[idx].servicio = name;
              cronograma[idx].mes = document.getElementById(`edit-crono-mes-${idx}`)?.value || '';
              cronograma[idx].monto = parseFloat(document.getElementById(`edit-crono-cost-${idx}`)?.value) || 0;
              cronograma[idx].fecha = document.getElementById(`edit-crono-date-${idx}`)?.value || '';
              cronograma[idx].estado = document.getElementById(`edit-crono-status-${idx}`)?.value || 'pendiente';
          }
          editingIndexCuota = -1;
      }
      
      if (isEditingAbono) {
          const name = document.getElementById('new-abono-name')?.value;
          if (name) {
              const method = document.getElementById('new-abono-method')?.value || '';
              const cost = parseFloat(document.getElementById('new-abono-cost')?.value) || 0;
              const date = document.getElementById('new-abono-date')?.value || '';
              abonos.push({ concepto: name, metodo: method, monto: cost, fecha: date });
          }
          isEditingAbono = false;
      }
      if (editingIndexAbono !== -1) {
          const idx = editingIndexAbono;
          const name = document.getElementById(`edit-abono-name-${idx}`)?.value;
          if (name) {
              abonos[idx].concepto = name;
              abonos[idx].metodo = document.getElementById(`edit-abono-method-${idx}`)?.value || '';
              abonos[idx].monto = parseFloat(document.getElementById(`edit-abono-cost-${idx}`)?.value) || 0;
              abonos[idx].fecha = document.getElementById(`edit-abono-date-${idx}`)?.value || '';
          }
          editingIndexAbono = -1;
      }

      let client = document.getElementById('note-client')?.value || 'Cliente sin nombre';
      let company = document.getElementById('note-company')?.value || 'Sin empresa';
      let startDate = document.getElementById('note-start-date')?.value || '';
      
      let subtotal = totalServiciosCalc() + totalPendienteCrono();
      if (subtotal === 0 && existingNote && existingNote.total > 0 && servicios.length === 0 && cronograma.length === 0) {
          subtotal = parseFloat(existingNote.total);
      }
      
      const discountInput = document.getElementById('discount-input');
      const discountPercent = discountInput ? parseFloat(discountInput.value) || 0 : 0;
      const discountAmount = subtotal * (discountPercent / 100);
      
      const baseForIgv = subtotal - discountAmount;
      
      const toggleIgv = document.getElementById('toggle-igv');
      const applyIgv = toggleIgv ? toggleIgv.checked : false;
      const igvAmount = applyIgv ? baseForIgv * 0.18 : 0;
      
      const totalGeneral = baseForIgv + igvAmount;
      
      const hasPending = cronograma.some(c => c.estado === 'pendiente') || cronograma.length === 0;
      const isActuallyPaid = Boolean(currentVoucherUrl) || (!hasPending && cronograma.length > 0);
      const status = isActuallyPaid ? 'PAGADO' : 'PENDIENTE';
      
      let noteToSave = {};

      if (!existingNote) {
          const dateObj = new Date();
          const newId = 'ID-' + dateObj.getFullYear() + '-' + Math.floor(Math.random() * 10000);
          
          const formattedDate = String(dateObj.getDate()).padStart(2, '0') + '/' + String(dateObj.getMonth() + 1).padStart(2, '0') + '/' + dateObj.getFullYear();
          
          noteToSave = {
              id: newId,
              client: client,
              company: company,
              startDate: startDate,
              total: totalGeneral,
              date: formattedDate,
              status: status,
              servicios: servicios,
              cronograma: cronograma,
              abonos: abonos,
              apply_igv: applyIgv,
              discount_percent: discountPercent,
              show_memberships: document.getElementById('toggle-membership') ? document.getElementById('toggle-membership').checked : true,
              show_advances: document.getElementById('toggle-abonos') ? document.getElementById('toggle-abonos').checked : false,
              due_days: parseInt(document.getElementById('note-due-days')?.value) || 30,
              access_pin: document.getElementById('note-access-pin')?.value || null,
              voucher_url: currentVoucherUrl || null,
              operation_number: currentOperationNumber || null
          };
      } else {
          noteToSave = existingNote;
          noteToSave.client = client;
          noteToSave.company = company;
          noteToSave.startDate = startDate;
          noteToSave.total = totalGeneral;
          noteToSave.status = status;
          noteToSave.servicios = servicios;
          noteToSave.cronograma = cronograma;
          noteToSave.abonos = abonos;
          noteToSave.apply_igv = applyIgv;
          noteToSave.discount_percent = discountPercent;
          noteToSave.show_memberships = document.getElementById('toggle-membership') ? document.getElementById('toggle-membership').checked : true;
          noteToSave.show_advances = document.getElementById('toggle-abonos') ? document.getElementById('toggle-abonos').checked : false;
          noteToSave.due_days = parseInt(document.getElementById('note-due-days')?.value) || 30;
          noteToSave.access_pin = document.getElementById('note-access-pin')?.value || null;
          noteToSave.voucher_url = currentVoucherUrl || existingNote.voucher_url || null;
          noteToSave.operation_number = currentOperationNumber || existingNote.operation_number || null;
      }
      
      const btn = document.getElementById('btn-guardar-nota-total');
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
      btn.style.opacity = '0.8';
      btn.disabled = true;

      fetch(APP_BASE_URL + 'modules/admin/ajax_save_payment_note.php', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/json'
          },
          body: JSON.stringify(noteToSave)
      })
      .then(res => res.json())
      .then(data => {
          if (data.success) {
              showToast('Nota de Pago guardada correctamente');
              btn.innerHTML = '<i class="ph ph-check"></i> Guardado';
              btn.style.backgroundColor = 'var(--secondary-color)';
              btn.style.borderColor = 'var(--secondary-color)';
              btn.style.opacity = '1';
              
              setTimeout(() => {
                  window.location.href = 'index.php?module=admin&action=payment_notes';
              }, 1200);
          } else {
              alert('Error al guardar: ' + data.error);
              btn.innerHTML = originalText;
              btn.disabled = false;
              btn.style.opacity = '1';
          }
      })
      .catch(e => {
          console.error(e);
          alert('Error de conexión');
          btn.innerHTML = originalText;
          btn.disabled = false;
          btn.style.opacity = '1';
      });
  });
});
</script>

<?php if ($is_public): ?>
</body>
</html>
<?php else: ?>
<?php require_once 'includes/footer.php'; ?>
<?php endif; ?>
