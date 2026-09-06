<?php
// modules/quotes/public.php
if (!isset($db)) {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (!$token) {
    die("Enlace inválido o expirado.");
}

// Fetch quote
$stmt = $db->prepare("SELECT q.*, c.name as client_name 
                     FROM quotes q 
                     LEFT JOIN clients c ON q.client_id = c.id 
                     WHERE q.public_token = ?");
$stmt->execute([$token]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    die("Cotización no encontrada.");
}

// Fetch items
$stmt = $db->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id ASC");
$stmt->execute([$quote['id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sym = $quote['currency'] === 'USD' ? '$' : 'S/';

// Parse bank accounts if available
$pm_lines = [];
if (!empty($quote['show_payment_methods']) && !empty($quote['payment_methods_text'])) {
    $pm_lines = explode("\n", trim($quote['payment_methods_text']));
}

// Fetch Global Settings for company info
$stmtSettings = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($stmtSettings->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Calculate clean base URL
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$protocol = $is_https ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_path = preg_replace('#/modules/quotes/?$#', '', $script_dir);
if ($base_path === '/' || $base_path === '\\') {
    $base_path = '';
}
$base_url = rtrim($protocol . $host . $base_path, '/') . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización #<?php echo str_pad($quote['id'], 4, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($quote['client_name'] ?? ''); ?></title>
    <?php if(!empty($settings['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($base_url . ltrim($settings['favicon'], '/')); ?>">
    <?php endif; ?>
    
    <!-- Fonts and Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Frappe Gantt -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/frappe-gantt/0.6.1/frappe-gantt.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/frappe-gantt/0.6.1/frappe-gantt.min.js"></script>

    <style>
        :root {
            --primary: <?php echo htmlspecialchars($settings['primary_color'] ?? '#6366f1'); ?>;
            --primary-hover: color-mix(in srgb, var(--primary) 85%, #000000);
            --primary-light: color-mix(in srgb, var(--primary) 12%, transparent);
            --bg: #f8fafc;
            --surface: #ffffff;
            --surface-elevated: #f1f5f9;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --border-focus: #cbd5e1;
            --header-bg: #f8fafc;
            --card-radius: 20px;
            --inner-radius: 14px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.04);
            --shadow-card: 0 20px 40px -15px rgba(0,0,0,0.06), 0 0 0 1px rgba(0,0,0,0.04);
            --transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        [data-theme="dark"] {
            --primary: <?php echo htmlspecialchars($settings['primary_color'] ?? '#818cf8'); ?>;
            --primary-hover: color-mix(in srgb, var(--primary) 85%, #ffffff);
            --primary-light: color-mix(in srgb, var(--primary) 18%, transparent);
            --bg: #000000;
            --surface: #0a0a0a;
            --surface-elevated: #141414;
            --text-main: #f4f4f5;
            --text-muted: #a1a1aa;
            --border: #262626;
            --border-focus: #3f3f46;
            --header-bg: #000000;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.4);
            --shadow-card: 0 25px 50px -12px rgba(0,0,0,0.8), 0 0 0 1px #262626;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            padding: 2.5rem 1rem;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
        }

        /* Top Action Bar */
        .top-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.85rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            box-shadow: var(--shadow-sm);
        }

        .brand-badge i {
            color: var(--primary);
            font-size: 1rem;
        }

        .actions-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-theme-switch {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-main);
            cursor: pointer;
            font-size: 1.2rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .btn-theme-switch:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }

        .btn-action-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 0.65rem 1.35rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 4px 14px color-mix(in srgb, var(--primary) 35%, transparent);
            font-family: inherit;
        }

        .btn-action-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px color-mix(in srgb, var(--primary) 45%, transparent);
            color: #ffffff;
        }

        /* Document Wrapper Card */
        .document-card {
            background: var(--surface);
            border-radius: var(--card-radius);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            border: 1px solid var(--border);
            margin-bottom: 2rem;
            transition: var(--transition);
        }

        /* Header section */
        .doc-header {
            padding: 2.5rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid var(--border);
            background: var(--header-bg);
            gap: 2rem;
            flex-wrap: wrap;
        }

        .company-brand {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 440px;
        }

        .company-logo-img {
            max-height: 48px;
            max-width: 220px;
            object-fit: contain;
        }

        .company-logo-img.logo-dark { display: none; }
        [data-theme="dark"] .company-logo-img.logo-light { display: none; }
        [data-theme="dark"] .company-logo-img.logo-dark { display: block; }

        .company-fallback-logo {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.03em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .company-info-list {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .company-name-title {
            font-weight: 700;
            color: var(--text-main);
            font-size: 0.95rem;
        }

        .company-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .company-info-item i {
            color: var(--primary);
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .doc-quote-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }

        .quote-badge-tag {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.3rem 0.75rem;
            border-radius: 9999px;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid color-mix(in srgb, var(--primary) 25%, transparent);
        }

        .doc-quote-number {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.03em;
            line-height: 1.1;
        }

        .status-pill {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
        }
        .status-borrador { background: color-mix(in srgb, #71717a 15%, transparent); color: #71717a; border: 1px solid color-mix(in srgb, #71717a 25%, transparent); }
        .status-enviada { background: color-mix(in srgb, #3b82f6 15%, transparent); color: #3b82f6; border: 1px solid color-mix(in srgb, #3b82f6 25%, transparent); }
        .status-aceptada { background: color-mix(in srgb, #10b981 15%, transparent); color: #10b981; border: 1px solid color-mix(in srgb, #10b981 25%, transparent); }
        .status-rechazada { background: color-mix(in srgb, #ef4444 15%, transparent); color: #ef4444; border: 1px solid color-mix(in srgb, #ef4444 25%, transparent); }

        /* Meta Cards Strip */
        .meta-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            padding: 1.75rem 3rem;
            border-bottom: 1px solid var(--border);
            background: color-mix(in srgb, var(--surface-elevated) 40%, var(--surface));
        }

        .meta-item-box {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .meta-item-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .meta-item-value {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .meta-item-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* Document Body */
        .doc-body {
            padding: 2.5rem 3rem;
        }

        /* Services Table */
        .table-responsive-wrap {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 2rem;
        }

        .services-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: var(--inner-radius);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .services-table th {
            background: var(--surface-elevated);
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 1rem 1.25rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .services-table td {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
            font-size: 0.92rem;
            color: var(--text-main);
            background: var(--surface);
        }

        .services-table tbody tr:last-child td {
            border-bottom: none;
        }

        .services-table tbody tr:hover td {
            background: color-mix(in srgb, var(--surface-elevated) 40%, var(--surface));
        }

        .service-desc-cell {
            line-height: 1.6;
        }

        .service-desc-cell strong {
            color: var(--text-main);
            font-size: 0.98rem;
        }

        .service-desc-cell ul, .service-desc-cell ol {
            margin: 0.5rem 0 0 1.25rem;
            color: var(--text-muted);
            font-size: 0.88rem;
        }

        .service-desc-cell li {
            margin-bottom: 0.25rem;
        }

        .amount-highlight {
            font-weight: 700;
            font-size: 0.98rem;
            color: var(--text-main);
        }

        .discount-tag {
            color: #ef4444;
            font-weight: 600;
            font-size: 0.88rem;
        }

        /* Totals Card */
        .totals-summary-card {
            width: 100%;
            max-width: 380px;
            margin-left: auto;
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            border-radius: var(--inner-radius);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            box-shadow: var(--shadow-sm);
        }

        .calc-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.92rem;
            color: var(--text-muted);
        }

        .calc-row-val {
            font-weight: 600;
            color: var(--text-main);
            font-size: 1rem;
        }

        .calc-divider {
            height: 1px;
            background: var(--border);
            margin: 0.25rem 0;
        }

        .calc-row.total-row {
            margin-top: 0.25rem;
            padding-top: 0.5rem;
        }

        .calc-row.total-row .calc-row-label {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.01em;
        }

        .calc-row.total-row .calc-row-val {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.02em;
        }

        /* Sections (Gantt, Payment, Notes) */
        .section-block {
            margin-top: 2.75rem;
            padding-top: 2.25rem;
            border-top: 1px dashed var(--border);
        }

        .section-header-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .section-header-title i {
            color: var(--primary);
            font-size: 1.3rem;
        }

        /* Gantt Chart Container */
        .gantt-wrapper-card {
            border: 1px solid var(--border);
            border-radius: var(--inner-radius);
            background: var(--surface);
            padding: 1.25rem;
            overflow-x: auto;
            box-shadow: var(--shadow-sm);
        }

        /* Read-only Gantt bar styling */
        .gantt-wrapper-card .bar-wrapper { cursor: default !important; pointer-events: none !important; }
        .gantt-wrapper-card .handle-group { display: none !important; }
        .gantt-wrapper-card .bar { cursor: default !important; }
        .gantt-wrapper-card .bar-progress { cursor: default !important; }

        /* Dark mode gantt */
        [data-theme="dark"] .gantt .grid-header { fill: #0a0a0a; }
        [data-theme="dark"] .gantt .grid-row { fill: #0a0a0a; }
        [data-theme="dark"] .gantt .grid-row:nth-child(even) { fill: #141414; }
        [data-theme="dark"] .gantt .lower-text, 
        [data-theme="dark"] .gantt .upper-text { fill: #a1a1aa; }
        [data-theme="dark"] .gantt .row-line,
        [data-theme="dark"] .gantt .tick { stroke: #262626; }

        /* Payment Methods Grid */
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .payment-card {
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            transition: var(--transition);
        }

        .payment-card:hover {
            border-color: color-mix(in srgb, var(--primary) 50%, var(--border));
            transform: translateY(-1px);
        }

        .payment-card-left {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .payment-icon-wrap {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .payment-text-group {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .payment-bank-name {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.04em;
        }

        .payment-account-number {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-main);
            font-family: monospace, sans-serif;
            letter-spacing: 0.02em;
        }

        .btn-copy-account {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-muted);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .btn-copy-account:hover {
            background: var(--surface);
            color: var(--primary);
            border-color: var(--primary);
        }

        /* Notes & Terms Grid */
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.25rem;
        }

        .note-card {
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .note-card-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-main);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .note-card-title i {
            color: var(--primary);
            font-size: 1rem;
        }

        .note-card-body {
            font-size: 0.88rem;
            color: var(--text-muted);
            white-space: pre-wrap;
            line-height: 1.6;
        }

        /* Footer */
        .doc-footer {
            text-align: center;
            padding: 1.5rem;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* Toast notification */
        .copy-toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--surface);
            color: var(--text-main);
            border: 1px solid var(--border);
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: all 0.25s ease;
            z-index: 9999;
        }

        .copy-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            body {
                padding: 1rem 0.5rem;
            }

            .doc-header {
                padding: 1.75rem 1.5rem;
                flex-direction: column;
                gap: 1.5rem;
            }

            .company-brand {
                max-width: 100%;
            }

            .doc-quote-meta {
                align-items: flex-start;
                width: 100%;
            }

            .doc-quote-number {
                font-size: 1.75rem;
            }

            .meta-strip {
                padding: 1.25rem 1.5rem;
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .doc-body {
                padding: 1.5rem;
            }

            .services-table thead {
                display: none;
            }

            .services-table, 
            .services-table tbody, 
            .services-table tr, 
            .services-table td {
                display: block;
                width: 100%;
            }

            .services-table {
                border: none;
                background: transparent;
            }

            .services-table tr {
                background: var(--surface-elevated);
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .services-table td {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 0.45rem 0;
                border-bottom: none;
                background: transparent !important;
            }

            .services-table td::before {
                content: attr(data-label);
                font-size: 0.72rem;
                font-weight: 700;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 0.04em;
                min-width: 100px;
            }

            .services-table td.service-desc-cell {
                flex-direction: column;
                gap: 0.35rem;
                padding-bottom: 0.75rem;
                margin-bottom: 0.5rem;
                border-bottom: 1px solid var(--border);
            }

            .services-table td.service-desc-cell::before {
                margin-bottom: 0.25rem;
            }

            .totals-summary-card {
                max-width: 100%;
            }

            .calc-row.total-row .calc-row-val {
                font-size: 1.4rem;
            }
        }

        /* Print Optimization */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
            }
            .container {
                max-width: 100% !important;
            }
            .top-action-bar,
            .btn-theme-switch,
            .btn-copy-account,
            .copy-toast {
                display: none !important;
            }
            .document-card {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
            }
            .doc-header, .meta-strip, .services-table th, .totals-summary-card, .note-card, .payment-card {
                background: #f8fafc !important;
                border-color: #e2e8f0 !important;
            }
            .calc-row.total-row .calc-row-val {
                color: #000000 !important;
            }
            .services-table thead {
                display: table-header-group !important;
            }
            .services-table, .services-table tbody, .services-table tr, .services-table td {
                display: revert !important;
            }
            .services-table td::before {
                display: none !important;
            }
        }
    </style>
    <script>
        (function() {
            var theme = localStorage.getItem('quote_theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<div class="container">
    <!-- Action Bar -->
    <div class="top-action-bar">
        <div class="brand-badge">
            <i class="ph ph-shield-check"></i>
            <span>Documento Oficial Verificado</span>
        </div>
        <div class="actions-right">
            <button class="btn-theme-switch" id="themeToggle" title="Cambiar tema (Claro / Oscuro)">
                <i class="ph ph-moon" id="themeIconDark"></i>
                <i class="ph ph-sun" id="themeIconLight" style="display:none;"></i>
            </button>
            <button onclick="window.print()" class="btn-action-primary">
                <i class="ph ph-printer"></i>
                <span>Imprimir / Descargar PDF</span>
            </button>
        </div>
    </div>

    <!-- Main Document -->
    <div class="document-card">
        <!-- Header -->
        <div class="doc-header">
            <div class="company-brand">
                <?php if(!empty($settings['logo_light']) && !empty($settings['logo_dark'])): ?>
                    <img src="<?php echo htmlspecialchars($base_url . ltrim($settings['logo_light'], '/')); ?>" class="company-logo-img logo-light" alt="Logo">
                    <img src="<?php echo htmlspecialchars($base_url . ltrim($settings['logo_dark'], '/')); ?>" class="company-logo-img logo-dark" alt="Logo">
                <?php elseif(!empty($settings['logo_light'])): ?>
                    <img src="<?php echo htmlspecialchars($base_url . ltrim($settings['logo_light'], '/')); ?>" class="company-logo-img" alt="Logo">
                <?php else: ?>
                    <div class="company-fallback-logo">
                        <i class="ph ph-file-text"></i>
                        <span><?php echo htmlspecialchars($settings['site_name'] ?? 'Empresa'); ?></span>
                    </div>
                <?php endif; ?>

                <div class="company-info-list">
                    <span class="company-name-title"><?php echo htmlspecialchars($settings['company_trade_name'] ?? $settings['site_name'] ?? ''); ?></span>
                    <?php if(!empty($settings['company_ruc'])): ?>
                        <span class="company-info-item"><i class="ph ph-identification-card"></i> RUC: <?php echo htmlspecialchars($settings['company_ruc']); ?></span>
                    <?php endif; ?>
                    <?php if(!empty($settings['company_address'])): ?>
                        <span class="company-info-item"><i class="ph ph-map-pin"></i> <?php echo htmlspecialchars($settings['company_address']); ?></span>
                    <?php endif; ?>
                    <?php if(!empty($settings['company_email'])): ?>
                        <span class="company-info-item"><i class="ph ph-envelope"></i> <?php echo htmlspecialchars($settings['company_email']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="doc-quote-meta">
                <span class="quote-badge-tag">Cotización Comercial</span>
                <div class="doc-quote-number">#<?php echo str_pad($quote['id'], 4, '0', STR_PAD_LEFT); ?></div>
                <?php if (!empty($quote['status'])): ?>
                    <span class="status-pill status-<?php echo strtolower($quote['status']); ?>">
                        <?php echo htmlspecialchars($quote['status']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Meta Details Strip -->
        <div class="meta-strip">
            <div class="meta-item-box">
                <span class="meta-item-label"><i class="ph ph-user"></i> Preparado para</span>
                <span class="meta-item-value"><?php echo htmlspecialchars($quote['client_name'] ?? 'Cliente'); ?></span>
                <?php if(!empty($quote['document_number'])): ?>
                    <span class="meta-item-sub">Doc: <?php echo htmlspecialchars($quote['document_number']); ?></span>
                <?php endif; ?>
            </div>
            <div class="meta-item-box">
                <span class="meta-item-label"><i class="ph ph-calendar-check"></i> Fecha de Emisión</span>
                <span class="meta-item-value"><?php echo date('d M, Y', strtotime($quote['issue_date'])); ?></span>
                <span class="meta-item-sub">Validez estándar</span>
            </div>
            <div class="meta-item-box">
                <span class="meta-item-label"><i class="ph ph-clock"></i> Fecha de Vencimiento</span>
                <span class="meta-item-value"><?php echo date('d M, Y', strtotime($quote['due_date'])); ?></span>
                <span class="meta-item-sub">Válido hasta las 23:59</span>
            </div>
        </div>

        <!-- Body -->
        <div class="doc-body">
            <!-- Table of Items -->
            <div class="table-responsive-wrap">
                <table class="services-table">
                    <thead>
                        <tr>
                            <th style="width: 52%;">Descripción del Servicio</th>
                            <th style="text-align: center; width: 12%;">Cant.</th>
                            <th style="text-align: right; width: 18%;">Precio Unit.</th>
                            <th style="text-align: right; width: 18%;">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $i): ?>
                        <tr>
                            <td class="service-desc-cell" data-label="Servicio">
                                <?php echo strip_tags($i['description'], '<strong><em><b><i><u><br><ul><ol><li><p><span><font>'); ?>
                            </td>
                            <td style="text-align: center; font-weight: 600;" data-label="Cantidad">
                                <?php echo (float)$i['quantity']; ?>
                            </td>
                            <td style="text-align: right;" data-label="Precio Unit.">
                                <div><?php echo $sym . ' ' . number_format($i['unit_price'], 2); ?></div>
                                <?php if($i['discount'] > 0): ?>
                                    <div class="discount-tag">-<?php echo $sym . ' ' . number_format($i['discount'], 2); ?> desc.</div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;" data-label="Importe">
                                <span class="amount-highlight"><?php echo $sym . ' ' . number_format($i['total'], 2); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Calculation Totals -->
            <div class="totals-summary-card">
                <div class="calc-row">
                    <span class="calc-row-label">Subtotal</span>
                    <span class="calc-row-val"><?php echo $sym . ' ' . number_format($quote['subtotal'], 2); ?></span>
                </div>
                <div class="calc-row">
                    <span class="calc-row-label">IGV / Impuestos (<?php echo $quote['subtotal'] > 0 ? (int)(($quote['tax']/$quote['subtotal'])*100) : 0; ?>%)</span>
                    <span class="calc-row-val"><?php echo $sym . ' ' . number_format($quote['tax'], 2); ?></span>
                </div>
                <div class="calc-divider"></div>
                <div class="calc-row total-row">
                    <span class="calc-row-label">TOTAL</span>
                    <span class="calc-row-val"><?php echo $sym . ' ' . number_format($quote['total'], 2); ?></span>
                </div>
            </div>

            <!-- Gantt Chart Section -->
            <div class="section-block" id="ganttSection">
                <h3 class="section-header-title">
                    <i class="ph ph-chart-line-up"></i>
                    Cronograma Estimado de Ejecución
                </h3>
                <div class="gantt-wrapper-card" id="gantt_here"></div>
                <div id="gantt_empty_state" style="text-align: center; padding: 2.5rem; color: var(--text-muted); display: none;">
                    <i class="ph ph-calendar-blank" style="font-size: 2rem; opacity: 0.4;"></i>
                    <p style="margin-top: 0.5rem; font-size: 0.88rem;">Sin fases o cronograma asignado para este presupuesto.</p>
                </div>
            </div>

            <!-- Payment Methods Section -->
            <?php if(!empty($quote['show_payment_methods'])): ?>
            <div class="section-block">
                <h3 class="section-header-title">
                    <i class="ph ph-credit-card"></i>
                    Cuentas y Métodos de Pago
                </h3>
                <div class="payment-grid">
                    <?php if(!empty($pm_lines)): ?>
                        <?php foreach($pm_lines as $line): ?>
                            <?php if(trim($line)): ?>
                                <?php 
                                    $parts = explode(':', trim($line), 2);
                                    $bName = count($parts) > 1 ? trim($parts[0]) : 'Cuenta';
                                    $bNum = count($parts) > 1 ? trim($parts[1]) : trim($parts[0]);
                                ?>
                                <div class="payment-card">
                                    <div class="payment-card-left">
                                        <div class="payment-icon-wrap">
                                            <i class="ph ph-bank"></i>
                                        </div>
                                        <div class="payment-text-group">
                                            <span class="payment-bank-name"><?php echo htmlspecialchars($bName); ?></span>
                                            <span class="payment-account-number"><?php echo htmlspecialchars($bNum); ?></span>
                                        </div>
                                    </div>
                                    <button class="btn-copy-account" onclick="copyNumber('<?php echo htmlspecialchars($bNum); ?>')" title="Copiar número de cuenta">
                                        <i class="ph ph-copy"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Default Bank Accounts if none custom -->
                        <div class="payment-card">
                            <div class="payment-card-left">
                                <div class="payment-icon-wrap"><i class="ph ph-bank"></i></div>
                                <div class="payment-text-group">
                                    <span class="payment-bank-name">BCP Soles</span>
                                    <span class="payment-account-number">191-74092813-0-24</span>
                                </div>
                            </div>
                            <button class="btn-copy-account" onclick="copyNumber('191-74092813-0-24')" title="Copiar"><i class="ph ph-copy"></i></button>
                        </div>
                        <div class="payment-card">
                            <div class="payment-card-left">
                                <div class="payment-icon-wrap"><i class="ph ph-device-mobile"></i></div>
                                <div class="payment-text-group">
                                    <span class="payment-bank-name">Yape / Plin</span>
                                    <span class="payment-account-number">998 289 752</span>
                                </div>
                            </div>
                            <button class="btn-copy-account" onclick="copyNumber('998289752')" title="Copiar"><i class="ph ph-copy"></i></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes & Terms -->
            <?php if(!empty(trim($quote['notes'] ?? '')) || !empty(trim($quote['terms_conditions'] ?? ''))): ?>
            <div class="section-block">
                <div class="notes-grid">
                    <?php if(!empty(trim($quote['notes'] ?? ''))): ?>
                    <div class="note-card">
                        <span class="note-card-title"><i class="ph ph-notepad"></i> Notas Adicionales</span>
                        <div class="note-card-body"><?php echo htmlspecialchars($quote['notes']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty(trim($quote['terms_conditions'] ?? ''))): ?>
                    <div class="note-card">
                        <span class="note-card-title"><i class="ph ph-file-text"></i> Términos y Condiciones</span>
                        <div class="note-card-body"><?php echo htmlspecialchars($quote['terms_conditions']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="doc-footer">
            <span>Generado con tecnología RomaAgencia SaaS &bull; Confidencial</span>
        </div>
    </div>
</div>

<!-- Copy Toast Notification -->
<div class="copy-toast" id="copyToast">
    <i class="ph ph-check-circle" style="color:#10b981; font-size:1.2rem;"></i>
    <span id="copyToastMsg">Copiado al portapapeles</span>
</div>

<script>
    const itemsData = <?php echo json_encode($items); ?>;
    
    // Gantt rendering
    const ganttColors = [
        { bg: '#6366f1' },
        { bg: '#3b82f6' },
        { bg: '#8b5cf6' },
        { bg: '#06b6d4' },
        { bg: '#10b981' },
        { bg: '#f59e0b' },
        { bg: '#ec4899' },
    ];
    const tasks = [];
    itemsData.forEach((item, index) => {
        if (item.gantt_start_date && parseInt(item.gantt_duration) > 0) {
            let div = document.createElement('div');
            div.innerHTML = item.description;
            let text = div.textContent || div.innerText || 'Fase ' + (index + 1);
            text = text.substring(0, 50).trim();
            
            let startDate = new Date(item.gantt_start_date + 'T00:00:00');
            let endDate = new Date(startDate);
            endDate.setDate(endDate.getDate() + parseInt(item.gantt_duration));
            
            tasks.push({
                id: 'task_' + index,
                name: text,
                start: startDate.toISOString().split('T')[0],
                end: endDate.toISOString().split('T')[0],
                progress: 0,
                custom_class: 'gantt-color-' + (tasks.length % ganttColors.length)
            });
        }
    });

    if (tasks.length > 0) {
        document.getElementById('gantt_here').style.display = 'block';
        document.getElementById('gantt_empty_state').style.display = 'none';
        const gantt = new Gantt("#gantt_here", tasks, {
            view_mode: 'Day',
            language: 'es',
            readonly: true
        });
        
        // CSS for custom colored bars
        let styleEl = document.createElement('style');
        let css = '';
        ganttColors.forEach((c, i) => {
            css += `.gantt-color-${i} .bar { fill: ${c.bg} !important; rx: 6px; ry: 6px; }
                    .gantt-color-${i} .bar-progress { fill: ${c.bg} !important; }
                    .gantt-color-${i} .bar-label { fill: #fff !important; font-weight: 600; }
`;
        });
        styleEl.textContent = css;
        document.head.appendChild(styleEl);
    } else {
        document.getElementById('gantt_here').style.display = 'none';
        document.getElementById('gantt_empty_state').style.display = 'block';
    }

    // Theme Switch
    const themeBtn = document.getElementById('themeToggle');
    const iconDark = document.getElementById('themeIconDark');
    const iconLight = document.getElementById('themeIconLight');

    function applyThemeIcons() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        iconDark.style.display = isDark ? 'none' : 'inline';
        iconLight.style.display = isDark ? 'inline' : 'none';
    }
    applyThemeIcons();

    themeBtn.addEventListener('click', function() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('quote_theme', 'light');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('quote_theme', 'dark');
        }
        applyThemeIcons();
    });

    // Copy to clipboard helper
    function copyNumber(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Cuenta copiada: ' + text);
        }).catch(() => {
            showToast('Error al copiar');
        });
    }

    function showToast(msg) {
        const toast = document.getElementById('copyToast');
        const msgEl = document.getElementById('copyToastMsg');
        msgEl.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 2500);
    }
</script>

</body>
</html>
