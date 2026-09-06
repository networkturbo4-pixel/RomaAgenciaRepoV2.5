<?php
// modules/suppliers/public.php
if (!isset($db)) {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    die("<div style='text-align:center; padding:3rem; font-family:sans-serif; background:#000; color:#fff; min-height:100vh;'><h2>Acceso Inválido</h2><p>El enlace del proveedor es incorrecto o ha expirado.</p></div>");
}

// Fetch supplier by token
$stmt = $db->prepare("SELECT * FROM suppliers WHERE public_token = ?");
$stmt->execute([$token]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supplier) {
    die("<div style='text-align:center; padding:3rem; font-family:sans-serif; background:#000; color:#fff; min-height:100vh;'><h2>Proveedor No Encontrado</h2><p>No se encontró ningún proveedor asociado a este enlace.</p></div>");
}

// Global settings
$stmtSettings = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($stmtSettings->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Fetch all payments for this supplier
$stmtPay = $db->prepare("SELECT * FROM supplier_payments WHERE supplier_id = ? ORDER BY payment_date DESC, id DESC");
$stmtPay->execute([$supplier['id']]);
$payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

// Fetch all services for this supplier
$stmtSvc = $db->prepare("SELECT * FROM supplier_services WHERE supplier_id = ? ORDER BY period_month DESC, service_date DESC, id DESC");
$stmtSvc->execute([$supplier['id']]);
$services = $stmtSvc->fetchAll(PDO::FETCH_ASSOC);

// Available months from payments and services
$available_months = [];
$current_m = date('Y-m');
$available_months[$current_m] = true;

foreach ($payments as $p) {
    if (!empty($p['period_month'])) $available_months[$p['period_month']] = true;
}
foreach ($services as $s) {
    if (!empty($s['period_month'])) $available_months[$s['period_month']] = true;
}
krsort($available_months);

// Selected month from GET or default to current
$selected_month = trim($_GET['month'] ?? $current_m);

// Calculate totals
$total_paid_pen = 0;
$total_paid_usd = 0;
$month_paid_pen = 0;
$month_paid_usd = 0;
$month_services_count = 0;

foreach ($payments as $p) {
    if ($p['status'] === 'paid') {
        if ($p['currency'] === 'USD') {
            $total_paid_usd += floatval($p['amount']);
            if ($p['period_month'] === $selected_month) $month_paid_usd += floatval($p['amount']);
        } else {
            $total_paid_pen += floatval($p['amount']);
            if ($p['period_month'] === $selected_month) $month_paid_pen += floatval($p['amount']);
        }
    }
}

foreach ($services as $s) {
    if ($s['period_month'] === $selected_month) {
        $month_services_count++;
    }
}

// Calculate base url
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$protocol = $is_https ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_path = preg_replace('#/modules/suppliers/?$#', '', $script_dir);
if ($base_path === '/' || $base_path === '\\') $base_path = '';
$base_url = rtrim($protocol . $host . $base_path, '/') . '/';
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Portal de Proveedor - <?php echo htmlspecialchars($supplier['name']); ?></title>
    
    <!-- Anti-FOUC Script for Dark Mode -->
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'light') {
                document.documentElement.removeAttribute('data-theme');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <?php if(!empty($settings['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($base_url . ltrim($settings['favicon'], '/')); ?>">
    <?php endif; ?>

    <!-- Fonts & Phosphor Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: <?php echo htmlspecialchars($settings['primary_color'] ?? '#6366f1'); ?>;
            --primary-bg: color-mix(in srgb, var(--primary) 10%, transparent);
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --bg-inset: #f1f5f9;
            --border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --color-title: #0f172a;
            --shadow: 0 4px 20px -4px rgba(0,0,0,0.05), 0 0 0 1px rgba(0,0,0,0.03);
            --radius-lg: 18px;
            --radius-md: 12px;
            font-size: 13px;
        }

        /* Pure Deep Black for Dark Mode */
        [data-theme="dark"] {
            --bg-page: #000000;
            --bg-card: #0a0a0a;
            --bg-inset: #111111;
            --border: #1f1f1f;
            --text-main: #f8fafc;
            --text-muted: #8e8e93;
            --color-title: #ffffff;
            --shadow: 0 4px 30px rgba(0,0,0,0.85);
            --primary-bg: color-mix(in srgb, var(--primary) 16%, transparent);
        }

        body {
            background: var(--bg-page);
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            padding: 0;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* Header Navigation */
        .portal-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 0.85rem 1.5rem;
            backdrop-filter: blur(10px);
        }
        .portal-header-inner {
            max-width: 1050px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .portal-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .portal-brand img {
            max-height: 26px;
            object-fit: contain;
        }
        .portal-brand span {
            font-weight: 800;
            font-size: 1.15rem;
            color: var(--primary);
            letter-spacing: -0.5px;
        }

        .theme-toggle-btn {
            background: var(--bg-inset);
            border: 1px solid var(--border);
            color: var(--text-main);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .theme-toggle-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Container */
        .portal-container {
            max-width: 1050px;
            margin: 0 auto;
            padding: 1.75rem 1.25rem 4rem 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Section Cards */
        .section-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }

        /* Supplier Profile Banner */
        .supplier-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.25rem;
        }
        .sup-avatar {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: var(--primary-bg);
            color: var(--primary);
            font-size: 1.6rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid color-mix(in srgb, var(--primary) 25%, transparent);
            flex-shrink: 0;
        }
        .sup-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--color-title);
            margin-bottom: 0.25rem;
        }
        .sup-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            background: var(--primary-bg);
            color: var(--primary);
        }

        /* Summary KPIs */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1rem;
            margin-top: 1.25rem;
        }
        .kpi-item {
            background: var(--bg-inset);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1rem 1.25rem;
        }
        .kpi-item-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }
        .kpi-item-val {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--color-title);
            margin-top: 0.25rem;
        }

        /* Month Navigation Bar */
        .month-filter-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
            margin-bottom: 1.25rem;
        }
        .month-select {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-inset);
            color: var(--text-main);
            font-size: 0.85rem;
            font-weight: 600;
            outline: none;
            cursor: pointer;
        }

        /* Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 1rem;
        }
        .service-card {
            background: var(--bg-inset);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 0.75rem;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .service-card:hover {
            transform: translateY(-2px);
            border-color: color-mix(in srgb, var(--primary) 40%, var(--border));
        }
        .svc-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--color-title);
        }
        .svc-desc {
            font-size: 0.82rem;
            color: var(--text-muted);
            line-height: 1.4;
        }
        .svc-status-pill {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
        }
        .svc-status-delivered { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .svc-status-approved { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .svc-status-in_progress { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }

        /* Payments Table */
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.85rem;
        }
        .payments-table th {
            padding: 0.75rem 1rem;
            border-bottom: 2px solid var(--border);
            color: var(--text-muted);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .payments-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border);
        }
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 600;
            font-size: 0.82rem;
            padding: 0.55rem 1.15rem;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--primary);
            color: #ffffff;
        }
        .btn-primary:hover {
            filter: brightness(1.1);
            box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 35%, transparent);
        }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-main);
        }
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        .btn-sm {
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
        }

        /* Image Lightbox Viewer */
        #lightbox-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(12px);
            z-index: 999999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 1.5rem;
        }
        #lightbox-overlay.active {
            display: flex;
        }
        .lb-controls {
            position: absolute;
            top: 1.25rem;
            right: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            z-index: 1000000;
        }
        .lb-btn {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.25);
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .lb-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.08);
        }
        .lb-img {
            max-width: 90vw;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.8);
            transition: transform 0.2s ease;
        }

        @media (max-width: 768px) {
            .portal-container { padding: 1rem 0.75rem 3rem 0.75rem; }
            .section-card { padding: 1.25rem; }
            .sup-title { font-size: 1.25rem; }
        }
    </style>
</head>
<body>

<!-- Header Navigation -->
<header class="portal-header">
    <div class="portal-header-inner">
        <div class="portal-brand">
            <?php if(!empty($settings['logo_light'])): ?>
                <img src="<?php echo htmlspecialchars($base_url . ltrim($settings['logo_light'], '/')); ?>" alt="Logo">
            <?php else: ?>
                <span><?php echo htmlspecialchars($settings['site_name'] ?? 'ROMA SaaS'); ?></span>
            <?php endif; ?>
            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 500; border-left: 1px solid var(--border); padding-left: 0.75rem;">Portal de Proveedor</span>
        </div>
        <button class="theme-toggle-btn" id="theme-toggle" title="Cambiar tema claro/oscuro">
            <i class="ph ph-moon" id="theme-icon"></i>
        </button>
    </div>
</header>

<main class="portal-container">

    <!-- Hero / Supplier Overview Card -->
    <div class="section-card">
        <div class="supplier-hero">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="sup-avatar"><?php echo strtoupper(substr($supplier['name'], 0, 1)); ?></div>
                <div>
                    <h1 class="sup-title"><?php echo htmlspecialchars($supplier['name']); ?></h1>
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <span class="sup-badge"><?php echo htmlspecialchars($supplier['category'] ?: 'General'); ?></span>
                        <?php if($supplier['tax_id']): ?>
                        <span style="font-size: 0.78rem; color: var(--text-muted);"><i class="ph ph-identification-card"></i> RUC: <strong><?php echo htmlspecialchars($supplier['tax_id']); ?></strong></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPIs Summary -->
        <div class="kpi-grid">
            <div class="kpi-item">
                <div class="kpi-item-label">Pagado este Mes (<?php echo $selected_month; ?>)</div>
                <div class="kpi-item-val" style="color: #10b981;">S/ <?php echo number_format($month_paid_pen, 2); ?></div>
                <?php if($month_paid_usd > 0): ?>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">$ <?php echo number_format($month_paid_usd, 2); ?> USD</div>
                <?php endif; ?>
            </div>

            <div class="kpi-item">
                <div class="kpi-item-label">Total Histórico Pagado</div>
                <div class="kpi-item-val">S/ <?php echo number_format($total_paid_pen, 2); ?></div>
                <?php if($total_paid_usd > 0): ?>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">$ <?php echo number_format($total_paid_usd, 2); ?> USD</div>
                <?php endif; ?>
            </div>

            <div class="kpi-item">
                <div class="kpi-item-label">Servicios en <?php echo $selected_month; ?></div>
                <div class="kpi-item-val"><?php echo $month_services_count; ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Entregables registrados</div>
            </div>
        </div>

        <!-- Bank Account Info Callout if provided -->
        <?php if(!empty($supplier['bank_info'])): ?>
        <div style="margin-top: 1.25rem; background: var(--bg-inset); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1rem; display: flex; align-items: flex-start; gap: 0.75rem;">
            <i class="ph ph-bank" style="font-size: 1.35rem; color: var(--primary); margin-top: 2px;"></i>
            <div>
                <div style="font-weight: 700; font-size: 0.8rem; text-transform: uppercase; color: var(--primary);">Cuentas Registradas para Pago / Depósito</div>
                <div style="font-size: 0.82rem; margin-top: 0.25rem; color: var(--text-main); font-family: monospace; white-space: pre-line;"><?php echo htmlspecialchars($supplier['bank_info']); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Month Navigation & Services Summary Card -->
    <div class="section-card">
        <div class="month-filter-bar">
            <div>
                <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--color-title);"><i class="ph ph-briefcase"></i> Resumen de Servicios del Mes</h2>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">Entregables y servicios prestados correspondientes al periodo seleccionado.</p>
            </div>
            
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <label style="font-weight: 600; font-size: 0.8rem; color: var(--text-muted);">Periodo:</label>
                <select class="month-select" onchange="window.location.href='index.php?module=suppliers&action=public&token=<?php echo urlencode($token); ?>&month=' + this.value;">
                    <?php foreach($available_months as $m => $dummy): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m === $selected_month ? 'selected' : ''; ?>><?php echo $m; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php
        $month_services = array_filter($services, fn($s) => $s['period_month'] === $selected_month);
        ?>

        <?php if(empty($month_services)): ?>
            <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                <i class="ph ph-folder-notch-open" style="font-size: 2.2rem; margin-bottom: 0.5rem; display: block;"></i>
                <div style="font-weight: 600;">No hay servicios registrados en <?php echo htmlspecialchars($selected_month); ?></div>
                <div style="font-size: 0.78rem; margin-top: 2px;">Selecciona otro periodo en el selector superior para ver meses anteriores.</div>
            </div>
        <?php else: ?>
            <div class="services-grid">
                <?php foreach($month_services as $svc): 
                    $status_class = 'svc-status-delivered';
                    $status_text = 'Entregado';
                    if ($svc['status'] === 'approved') { $status_class = 'svc-status-approved'; $status_text = 'Aprobado'; }
                    elseif ($svc['status'] === 'in_progress') { $status_class = 'svc-status-in_progress'; $status_text = 'En Progreso'; }
                ?>
                <div class="service-card">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.4rem;">
                            <h3 class="svc-title"><?php echo htmlspecialchars($svc['service_title']); ?></h3>
                            <span class="svc-status-pill <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </div>
                        <?php if(!empty($svc['description'])): ?>
                        <div class="svc-desc"><?php echo nl2br(htmlspecialchars($svc['description'])); ?></div>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px dashed var(--border); padding-top: 0.6rem; margin-top: auto;">
                        <span style="font-size: 0.75rem; color: var(--text-muted);"><i class="ph ph-calendar"></i> <?php echo htmlspecialchars($svc['service_date'] ?: $svc['period_month']); ?></span>
                        <strong style="font-size: 0.95rem; color: var(--color-title);"><?php echo $svc['currency'] === 'USD' ? '$' : 'S/'; ?> <?php echo number_format($svc['amount'], 2); ?></strong>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payments & Vouchers History Card -->
    <div class="section-card">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--color-title);"><i class="ph ph-receipt"></i> Historial de Pagos & Vouchers</h2>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">Comprobantes de pago y transferencias realizadas.</p>
            </div>
        </div>

        <?php if(empty($payments)): ?>
            <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                <i class="ph ph-receipt" style="font-size: 2.2rem; margin-bottom: 0.5rem; display: block;"></i>
                <div style="font-weight: 600;">Aún no se han registrado pagos para este proveedor.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Periodo</th>
                            <th style="text-align: right;">Monto</th>
                            <th>Método</th>
                            <th style="text-align: center;">Estado</th>
                            <th style="text-align: center;">Voucher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $p): 
                            $isPaid = $p['status'] === 'paid';
                            $hasVoucher = !empty($p['voucher_url']);
                        ?>
                        <tr>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($p['payment_date']); ?></td>
                            <td>
                                <strong style="color: var(--color-title);"><?php echo htmlspecialchars($p['concept']); ?></strong>
                                <?php if(!empty($p['reference_number'])): ?>
                                <div style="font-size: 0.72rem; color: var(--text-muted);">Ref: <?php echo htmlspecialchars($p['reference_number']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--text-muted);"><?php echo htmlspecialchars($p['period_month']); ?></td>
                            <td style="text-align: right; font-weight: 800; color: var(--color-title);">
                                <?php echo $p['currency'] === 'USD' ? '$' : 'S/'; ?> <?php echo number_format($p['amount'], 2); ?>
                            </td>
                            <td style="color: var(--text-muted);"><?php echo htmlspecialchars($p['payment_method'] ?: 'Transferencia'); ?></td>
                            <td style="text-align: center;">
                                <?php if($isPaid): ?>
                                <span style="display:inline-block; font-size:0.7rem; font-weight:700; color:#10b981; background:rgba(16,185,129,0.15); padding:0.2rem 0.55rem; border-radius:9999px;">PAGADO</span>
                                <?php else: ?>
                                <span style="display:inline-block; font-size:0.7rem; font-weight:700; color:#f59e0b; background:rgba(245,158,11,0.15); padding:0.2rem 0.55rem; border-radius:9999px;">PENDIENTE</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if($hasVoucher): ?>
                                    <button class="btn btn-outline btn-sm" onclick="openLightbox('<?php echo htmlspecialchars($base_url . ltrim($p['voucher_url'], '/')); ?>')" style="color: var(--primary);">
                                        <i class="ph ph-image"></i> Ver Voucher
                                    </button>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);"><i class="ph ph-minus"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- Image Lightbox Viewer Overlay -->
<div id="lightbox-overlay" onclick="if(event.target === this) closeLightbox();">
    <div class="lb-controls">
        <button type="button" class="lb-btn" onclick="zoomImg(0.2)" title="Acercar"><i class="ph ph-magnifying-glass-plus"></i></button>
        <button type="button" class="lb-btn" onclick="zoomImg(-0.2)" title="Alejar"><i class="ph ph-magnifying-glass-minus"></i></button>
        <button type="button" class="lb-btn" onclick="rotateImg()" title="Rotar 90°"><i class="ph ph-arrow-clockwise"></i></button>
        <a id="lb-download" href="#" download class="lb-btn" title="Descargar"><i class="ph ph-download-simple"></i></a>
        <button type="button" class="lb-btn" onclick="closeLightbox()" title="Cerrar"><i class="ph ph-x"></i></button>
    </div>
    <img id="lb-img" class="lb-img" src="" alt="Voucher">
</div>

<script>
// Dark Mode Toggle
const themeBtn = document.getElementById('theme-toggle');
const themeIcon = document.getElementById('theme-icon');

function updateThemeIcon() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (themeIcon) {
        themeIcon.className = isDark ? 'ph ph-sun' : 'ph ph-moon';
    }
}
updateThemeIcon();

themeBtn?.addEventListener('click', () => {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('theme', 'light');
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
    }
    updateThemeIcon();
});

// Image Lightbox Viewer
let currentScale = 1;
let currentDeg = 0;

function openLightbox(src) {
    if (!src) return;
    const ext = src.split('.').pop().toLowerCase();
    if (ext === 'pdf') {
        window.open(src, '_blank');
        return;
    }

    currentScale = 1;
    currentDeg = 0;
    const overlay = document.getElementById('lightbox-overlay');
    const img = document.getElementById('lb-img');
    const dl = document.getElementById('lb-download');

    if (img && overlay) {
        img.src = src;
        img.style.transform = `scale(1) rotate(0deg)`;
        if (dl) dl.href = src;
        overlay.classList.add('active');
    }
}

function closeLightbox() {
    const overlay = document.getElementById('lightbox-overlay');
    if (overlay) overlay.classList.remove('active');
}

function zoomImg(delta) {
    currentScale = Math.max(0.4, Math.min(3.5, currentScale + delta));
    applyImgTransform();
}

function rotateImg() {
    currentDeg = (currentDeg + 90) % 360;
    applyImgTransform();
}

function applyImgTransform() {
    const img = document.getElementById('lb-img');
    if (img) img.style.transform = `scale(${currentScale}) rotate(${currentDeg}deg)`;
}

// Escape key to close modals
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeLightbox();
    }
});
</script>

</body>
</html>
