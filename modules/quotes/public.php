<?php
// modules/quotes/public.php
require_once '../../config/database.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
if (!$token) {
    die("Enlace inválido o expirado.");
}

$database = new Database();
$db = $database->getConnection();

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
if ($quote['show_payment_methods'] && !empty($quote['payment_methods_text'])) {
    $pm_lines = explode("\n", trim($quote['payment_methods_text']));
}

// Fetch Global Settings for company info
$stmtSettings = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($stmtSettings->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización #<?php echo str_pad($quote['id'], 4, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($quote['client_name']); ?></title>
    <?php if(!empty($settings['favicon'])): ?>
    <link rel="icon" href="../../<?php echo htmlspecialchars($settings['favicon']); ?>">
    <?php endif; ?>
    
    <!-- Fonts and Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Frappe Gantt -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/frappe-gantt/0.6.1/frappe-gantt.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/frappe-gantt/0.6.1/frappe-gantt.min.js"></script>

    <style>
        :root {
            --primary: <?php echo htmlspecialchars($settings['primary_color'] ?? '#2563eb'); ?>;
            --primary-dark: #1d4ed8;
            --bg: #f1f5f9;
            --surface: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --header-bg: #f8fafc;
        }
        [data-theme="dark"] {
            --primary: <?php echo htmlspecialchars($settings['primary_color'] ?? '#2563eb'); ?>;
            --bg: #0f172a;
            --surface: #1e293b;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border: #334155;
            --header-bg: #1a2332;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 2rem 1rem;
            line-height: 1.5;
            -webkit-text-size-adjust: 100%;
        }
        * { box-sizing: border-box; }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .document-card {
            background: var(--surface);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid var(--border);
            margin-bottom: 2rem;
        }
        .doc-header {
            padding: 3rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid var(--border);
            background: var(--header-bg);
        }
        .company-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .company-logo-img {
            max-height: 50px;
            object-fit: contain;
        }
        .company-logo-img.logo-dark { display: none; }
        [data-theme="dark"] .company-logo-img.logo-light { display: none; }
        [data-theme="dark"] .company-logo-img.logo-dark { display: block; }
        .company-logo-fallback {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.05em;
        }
        .company-details {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }
        .company-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .company-detail-line {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .company-detail-line i {
            font-size: 0.9rem;
            color: var(--primary);
            width: 16px;
            text-align: center;
        }
        .doc-title {
            text-align: right;
        }
        .doc-title h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
            text-transform: uppercase;
        }
        .doc-title p {
            margin: 0.5rem 0 0 0;
            color: var(--text-muted);
            font-size: 1.1rem;
            font-weight: 500;
        }
        .doc-meta {
            display: flex;
            gap: 4rem;
            padding: 2rem 3rem;
            border-bottom: 1px solid var(--border);
        }
        .meta-block h3 {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0 0 0.5rem 0;
        }
        .meta-block p {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .doc-body {
            padding: 3rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        th, td {
            background: var(--surface);
        }
        th {
            text-align: left;
            padding: 1rem;
            background: #f8fafc;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border);
        }
        td {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }
        .item-desc {
            font-weight: 500;
            color: var(--text-main);
        }
        .item-desc ul {
            margin: 0.5rem 0 0;
            padding-left: 1.5rem;
            color: var(--text-muted);
            font-weight: 400;
            font-size: 0.95rem;
        }
        .totals-box {
            width: 350px;
            margin-left: auto;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
            color: var(--text-muted);
        }
        .total-row.grand-total {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 1.5rem 0;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
            background: #f8fafc;
            padding: 2rem;
            border-radius: 12px;
        }
        .info-block h4 {
            margin: 0 0 0.5rem 0;
            color: var(--text-main);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .info-block p {
            margin: 0;
            color: var(--text-muted);
            white-space: pre-wrap;
            font-size: 0.95rem;
        }

        .payment-methods {
            margin-top: 3rem;
            padding-top: 3rem;
            border-top: 1px dashed var(--border);
        }
        .pm-card {
            background: #fff;
            border: 1px solid var(--border);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .gantt-section {
            margin-top: 3rem;
            padding-top: 3rem;
            border-top: 1px dashed var(--border);
        }
        .gantt-container {
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            padding: 1rem;
            overflow-x: auto;
        }
        
        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            font-family: inherit;
        }
        .btn-print:hover {
            background: var(--primary-dark);
        }
        .btn-theme {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-main);
            cursor: pointer;
            font-size: 1.15rem;
            transition: all 0.2s;
        }
        .btn-theme:hover {
            background: var(--header-bg);
        }

        /* Gantt read-only: disable all dragging */
        .gantt-container .bar-wrapper {
            cursor: default !important;
        }
        .gantt-container .handle-group {
            display: none !important;
        }
        .gantt-container .bar {
            cursor: default !important;
        }
        .gantt-container .bar-progress {
            cursor: default !important;
        }

        /* Dark mode overrides for Gantt */
        [data-theme="dark"] .gantt-container {
            background: var(--surface);
        }
        [data-theme="dark"] .gantt .grid-header {
            fill: var(--surface);
        }
        [data-theme="dark"] .gantt .grid-row {
            fill: var(--surface);
        }
        [data-theme="dark"] .gantt .grid-row:nth-child(even) {
            fill: #253349;
        }
        [data-theme="dark"] .gantt .lower-text, 
        [data-theme="dark"] .gantt .upper-text {
            fill: var(--text-muted);
        }
        [data-theme="dark"] .gantt .row-line {
            stroke: #334155;
        }
        [data-theme="dark"] .gantt .tick {
            stroke: #334155;
        }
        [data-theme="dark"] .totals-box {
            background: var(--header-bg);
        }
        [data-theme="dark"] .info-grid {
            background: var(--header-bg);
        }
        [data-theme="dark"] .pm-card {
            background: var(--surface);
            border-color: var(--border);
        }
        [data-theme="dark"] th {
            background: var(--header-bg);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            body {
                padding: 1rem 0.5rem;
            }
            .document-card {
                border-radius: 12px;
            }

            /* Header: stack vertically, center */
            .doc-header {
                flex-direction: column;
                padding: 1.5rem;
                gap: 1.25rem;
            }
            .company-info {
                align-items: center;
                width: 100%;
            }
            .company-details {
                align-items: center;
                text-align: center;
            }
            .company-name {
                font-size: 1rem;
            }
            .doc-title {
                text-align: center;
                width: 100%;
            }
            .doc-title h1 {
                font-size: 1.75rem;
            }
            .doc-title p {
                font-size: 1rem;
            }

            /* Meta: grid layout */
            .doc-meta {
                flex-direction: column;
                gap: 1rem;
                padding: 1.25rem 1.5rem;
            }
            .meta-block {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                padding-bottom: 0.75rem;
                border-bottom: 1px solid var(--border);
            }
            .meta-block:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }
            .meta-block h3 {
                margin: 0;
                min-width: 100px;
            }
            .meta-block p {
                text-align: right;
                font-size: 1rem;
            }

            /* Body padding */
            .doc-body {
                padding: 1.25rem;
            }

            /* Table → Card layout on mobile */
            table thead {
                display: none;
            }
            table, table tbody, table tr, table td {
                display: block;
                width: 100%;
            }
            table tr {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 10px;
                padding: 1rem;
                margin-bottom: 1rem;
                position: relative;
            }
            table td {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 0.4rem 0;
                border-bottom: none;
                font-size: 0.9rem;
            }
            table td::before {
                content: attr(data-label);
                font-size: 0.7rem;
                font-weight: 700;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 0.03em;
                min-width: 90px;
                padding-top: 0.15rem;
            }
            table td.item-desc {
                flex-direction: column;
                gap: 0.25rem;
                padding-bottom: 0.75rem;
                margin-bottom: 0.5rem;
                border-bottom: 1px solid var(--border);
            }
            table td.item-desc::before {
                margin-bottom: 0.25rem;
            }

            /* Totals */
            .totals-box {
                width: 100%;
                margin-left: 0;
            }
            .total-row.grand-total {
                font-size: 1.25rem;
            }

            /* Notes & Terms */
            .info-grid {
                grid-template-columns: 1fr;
                gap: 1.25rem;
                padding: 1.25rem;
            }

            /* Payment methods */
            .pm-grid-responsive {
                grid-template-columns: 1fr !important;
            }

            /* Gantt */
            .gantt-section {
                margin-top: 2rem;
                padding-top: 2rem;
            }
            .gantt-container {
                padding: 0.5rem;
            }

            /* Section titles */
            .section-title {
                font-size: 1.1rem;
            }

            /* Toolbar */
            .toolbar-actions {
                flex-direction: row;
                gap: 0.5rem;
            }
            .btn-print {
                font-size: 0.85rem;
                padding: 0.6rem 1rem;
            }
        }

        /* Even smaller phones */
        @media (max-width: 400px) {
            .doc-header {
                padding: 1.25rem 1rem;
            }
            .doc-title h1 {
                font-size: 1.5rem;
            }
            .company-logo-img {
                max-height: 40px;
            }
            .doc-body {
                padding: 1rem;
            }
            .totals-box {
                padding: 1rem;
            }
        }

        @media print {
            body { background: white; padding: 0; color: #0f172a; }
            .container { max-width: 100%; }
            .document-card { box-shadow: none; border: none; margin: 0; border-radius: 0; }
            .btn-print, .btn-theme { display: none !important; }
            .toolbar-actions { display: none !important; }
            .gantt-container { overflow: visible; }
            table thead { display: table-header-group; }
            table, table tbody, table tr, table td { display: revert; }
            table tr { border: none; border-radius: 0; padding: 0; margin: 0; }
            table td::before { display: none; }
            table td.item-desc { flex-direction: row; border-bottom: 1px solid #e2e8f0; margin-bottom: 0; padding-bottom: 1.5rem; }
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
    <div class="toolbar-actions" style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-bottom: 1.5rem;">
        <button class="btn-theme" id="themeToggle" title="Cambiar tema">
            <i class="ph ph-moon" id="themeIconDark"></i>
            <i class="ph ph-sun" id="themeIconLight" style="display:none;"></i>
        </button>
        <button onclick="window.print()" class="btn-print"><i class="ph ph-printer"></i> Imprimir / Descargar PDF</button>
    </div>

    <div class="document-card">
        <div class="doc-header">
            <div class="company-info">
                <?php if(!empty($settings['logo_light']) && !empty($settings['logo_dark'])): ?>
                    <img src="../../<?php echo htmlspecialchars($settings['logo_light']); ?>" class="company-logo-img logo-light" alt="Logo">
                    <img src="../../<?php echo htmlspecialchars($settings['logo_dark']); ?>" class="company-logo-img logo-dark" alt="Logo">
                <?php elseif(!empty($settings['logo_light'])): ?>
                    <img src="../../<?php echo htmlspecialchars($settings['logo_light']); ?>" class="company-logo-img" alt="Logo">
                <?php else: ?>
                    <span class="company-logo-fallback"><?php echo htmlspecialchars($settings['site_name'] ?? 'Empresa'); ?></span>
                <?php endif; ?>
                <div class="company-details">
                    <span class="company-name"><?php echo htmlspecialchars($settings['company_trade_name'] ?? $settings['site_name'] ?? ''); ?></span>
                    <?php if(!empty($settings['company_ruc'])): ?>
                        <span class="company-detail-line"><i class="ph ph-identification-card"></i> RUC: <?php echo htmlspecialchars($settings['company_ruc']); ?></span>
                    <?php endif; ?>
                    <?php if(!empty($settings['company_address'])): ?>
                        <span class="company-detail-line"><i class="ph ph-map-pin"></i> <?php echo htmlspecialchars($settings['company_address']); ?></span>
                    <?php endif; ?>
                    <?php if(!empty($settings['company_email'])): ?>
                        <span class="company-detail-line"><i class="ph ph-envelope"></i> <?php echo htmlspecialchars($settings['company_email']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="doc-title">
                <h1>Cotización</h1>
                <p>#<?php echo str_pad($quote['id'], 4, '0', STR_PAD_LEFT); ?></p>
            </div>
        </div>

        <div class="doc-meta">
            <div class="meta-block" style="flex: 2;">
                <h3>Preparado para:</h3>
                <p><?php echo htmlspecialchars($quote['client_name']); ?></p>
                <?php if(isset($quote['document_number']) && $quote['document_number']) echo "<span style='color:var(--text-muted); font-size:0.9rem;'>RUC/DNI: ".htmlspecialchars($quote['document_number'])."</span>"; ?>
            </div>
            <div class="meta-block" style="flex: 1;">
                <h3>Fecha de Emisión</h3>
                <p><?php echo date('d M, Y', strtotime($quote['issue_date'])); ?></p>
            </div>
            <div class="meta-block" style="flex: 1;">
                <h3>Vencimiento</h3>
                <p><?php echo date('d M, Y', strtotime($quote['due_date'])); ?></p>
            </div>
        </div>

        <div class="doc-body">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50%;">Descripción del Servicio</th>
                        <th style="text-align: center;">Cant.</th>
                        <th style="text-align: right;">Precio Unit.</th>
                        <th style="text-align: right;">Desc.</th>
                        <th style="text-align: right;">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $i): ?>
                    <tr>
                        <td class="item-desc" data-label="Servicio"><?php echo strip_tags($i['description'], '<strong><em><b><i><u><br><ul><ol><li><p><span><font>'); ?></td>
                        <td style="text-align: center; font-weight: 500;" data-label="Cantidad"><?php echo (float)$i['quantity']; ?></td>
                        <td style="text-align: right;" data-label="Precio Unit."><?php echo $sym . ' ' . number_format($i['unit_price'], 2); ?></td>
                        <td style="text-align: right; color: #ef4444;" data-label="Descuento"><?php echo $i['discount'] > 0 ? '-' . $sym . ' ' . number_format($i['discount'], 2) : '-'; ?></td>
                        <td style="text-align: right; font-weight: 600; color: var(--text-main);" data-label="Importe"><?php echo $sym . ' ' . number_format($i['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals-box">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span style="font-weight: 600; color: var(--text-main);"><?php echo $sym . ' ' . number_format($quote['subtotal'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span>Impuestos (<?php echo $quote['subtotal'] > 0 ? (int)(($quote['tax']/$quote['subtotal'])*100) : 0; ?>%)</span>
                    <span style="font-weight: 600; color: var(--text-main);"><?php echo $sym . ' ' . number_format($quote['tax'], 2); ?></span>
                </div>
                <div class="total-row grand-total">
                    <span>TOTAL</span>
                    <span><?php echo $sym . ' ' . number_format($quote['total'], 2); ?></span>
                </div>
            </div>

            <!-- Gantt Section - always visible -->
            <div class="gantt-section" id="ganttSection">
                <h2 class="section-title"><i class="ph ph-chart-bar"></i> Cronograma del Proyecto</h2>
                <div class="gantt-container" id="gantt_here"></div>
                <div id="gantt_empty_state" style="text-align: center; padding: 3rem; color: var(--text-muted); display: none;">
                    <i class="ph ph-calendar-blank" style="font-size: 2.5rem; opacity: 0.5;"></i>
                    <p style="margin-top: 0.75rem; font-size: 0.95rem;">Sin cronograma asignado</p>
                </div>
            </div>

            <?php if($quote['show_payment_methods'] && !empty($pm_lines)): ?>
            <div class="payment-methods">
                <h2 class="section-title"><i class="ph ph-bank"></i> Información de Pago</h2>
                <div class="pm-grid-responsive" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <?php foreach($pm_lines as $line): ?>
                        <?php if(trim($line)): ?>
                            <div class="pm-card">
                                <div style="font-weight: 600; color: var(--primary);"><i class="ph ph-wallet"></i></div>
                                <div><?php echo htmlspecialchars(trim($line)); ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if(trim($quote['notes']) || trim($quote['terms_conditions'])): ?>
            <div style="margin-top: 3rem; padding-top: 3rem; border-top: 1px dashed var(--border);">
                <div class="info-grid">
                    <?php if(trim($quote['notes'])): ?>
                    <div class="info-block">
                        <h4><i class="ph ph-note"></i> Notas Adicionales</h4>
                        <p><?php echo htmlspecialchars($quote['notes']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(trim($quote['terms_conditions'])): ?>
                    <div class="info-block">
                        <h4><i class="ph ph-shield-check"></i> Términos y Condiciones</h4>
                        <p><?php echo htmlspecialchars($quote['terms_conditions']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    const itemsData = <?php echo json_encode($items); ?>;
    
    // Render Gantt if dates exist
    const ganttColors = [
        { bg: '#3b82f6' },
        { bg: '#8b5cf6' },
        { bg: '#06b6d4' },
        { bg: '#f59e0b' },
        { bg: '#10b981' },
        { bg: '#ef4444' },
        { bg: '#ec4899' },
        { bg: '#6366f1' },
        { bg: '#14b8a6' },
        { bg: '#f97316' },
    ];
    const tasks = [];
    itemsData.forEach((item, index) => {
        if (item.gantt_start_date && parseInt(item.gantt_duration) > 0) {
            let div = document.createElement('div');
            div.innerHTML = item.description;
            let text = div.textContent || div.innerText || 'Tarea ' + (index + 1);
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
        // Extra safety: completely disable pointer events on bars
        document.querySelectorAll('#gantt_here .bar-wrapper').forEach(el => {
            el.style.pointerEvents = 'none';
        });
        // Inject color CSS for each bar
        let styleEl = document.createElement('style');
        let css = '';
        ganttColors.forEach((c, i) => {
            css += `.gantt-color-${i} .bar { fill: ${c.bg} !important; }
                    .gantt-color-${i} .bar-progress { fill: ${c.bg} !important; }
                    .gantt-color-${i} .bar-label { fill: #fff !important; }
`;
        });
        styleEl.textContent = css;
        document.head.appendChild(styleEl);
    } else {
        document.getElementById('gantt_here').style.display = 'none';
        document.getElementById('gantt_empty_state').style.display = 'block';
    }

    // Theme toggle
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
</script>

</body>
</html>
