<?php
// modules/public/employee_history.php

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo "<div style='text-align:center; padding: 2rem; font-family: sans-serif;'>Enlace inválido.</div>";
    exit;
}

// Fetch employee details
$stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    echo "<div style='text-align:center; padding: 2rem; font-family: sans-serif;'>Empleado no encontrado.</div>";
    exit;
}

// Fetch payment history
$stmt = $db->prepare("SELECT * FROM employee_payments WHERE employee_id = ? ORDER BY payment_date DESC, created_at DESC");
$stmt->execute([$id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Global settings for company branding
$company_name = $global_settings['site_name'] ?? 'Empresa';
$company_logo = $global_settings['logo_light'] ?? ($global_settings['logo_dark'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Pagos - <?php echo htmlspecialchars($employee['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --bg-color: #f1f5f9;
            --surface: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --primary: #3b82f6;
            --primary-light: #eff6ff;
            --success: #10b981;
            --warning: #f59e0b;
        }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            padding: 2rem;
            display: flex;
            justify-content: center;
        }
        .document-a4 {
            background: var(--surface);
            width: 100%;
            max-width: 850px;
            min-height: 1100px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            box-sizing: border-box;
            position: relative;
        }
        .header-brand {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1.5rem;
            margin-bottom: 3rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 2rem;
            flex-wrap: wrap;
        }
        .company-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .company-info img {
            max-height: 45px;
            max-width: 250px;
            object-fit: contain;
            border-radius: 4px;
        }
        .company-details h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 0.25rem 0;
            color: var(--text-main);
        }
        .company-details p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .document-title {
            text-align: right;
        }
        .document-title h2 {
            margin: 0;
            color: var(--text-muted);
            font-size: 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .document-title p {
            margin: 0.5rem 0 0 0;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--primary);
        }
        .emp-info-box {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .emp-detail {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .emp-detail span {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 600;
        }
        .emp-detail strong {
            font-size: 1rem;
            color: var(--text-main);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }
        th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        .amount {
            font-weight: 600;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pagado { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .status-pendiente { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        
        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.35rem 0.75rem;
            background: transparent;
            color: var(--primary);
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid var(--primary);
            transition: all 0.2s;
        }
        .btn-view:hover {
            background: var(--primary);
            color: white;
        }
        @media (max-width: 640px) {
            body { padding: 1rem 0.5rem; }
            .document-a4 { padding: 1.5rem; min-height: auto; }
            .header-brand { flex-direction: column; text-align: left; }
            .document-title { text-align: left; margin-top: 1rem; }
            .emp-info-box { grid-template-columns: 1fr; gap: 1.5rem; }
            th, td { padding: 0.75rem 0.5rem; font-size: 0.8rem; }
            .company-info { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .company-info img { max-width: 200px; }
        }
        @media print {
            body { background: white; padding: 0; }
            .document-a4 { box-shadow: none; max-width: 100%; padding: 0; }
            .btn-view { display: none; }
        }
    </style>
</head>
<body>

<div class="document-a4">
    <div class="header-brand">
        <div class="company-info">
            <?php if (!empty($company_logo)): ?>
                <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="Logo">
            <?php else: ?>
                <div style="width: 50px; height: 50px; background: var(--primary); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="ph ph-buildings" style="font-size: 2rem;"></i>
                </div>
            <?php endif; ?>
        </div>
        <div class="document-title">
            <h2>Historial de Pagos</h2>
            <p>Generado: <?php echo date('d/m/Y'); ?></p>
        </div>
    </div>

    <div class="emp-info-box">
        <div class="emp-detail">
            <span>Empleado</span>
            <strong><?php echo htmlspecialchars($employee['name']); ?></strong>
        </div>
        <div class="emp-detail">
            <span>DNI / Identificación</span>
            <strong><?php echo htmlspecialchars($employee['dni'] ?? 'No registrado'); ?></strong>
        </div>
        <div class="emp-detail">
            <span>Rol</span>
            <strong><?php echo htmlspecialchars($employee['role']); ?></strong>
        </div>
        <div class="emp-detail">
            <span>Departamento</span>
            <strong><?php echo htmlspecialchars($employee['department']); ?></strong>
        </div>
    </div>

    <?php if (count($payments) > 0): ?>
        <div style="overflow-x: auto; width: 100%;">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Pago Extra</th>
                        <th>Monto Total</th>
                        <th>Estado</th>
                        <th style="text-align: right;">Comprobante</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?php echo htmlspecialchars($pay['payment_date']); ?></td>
                            <td style="min-width: 120px;"><?php echo htmlspecialchars($pay['concept']); ?></td>
                            <td style="white-space: nowrap;">
                                <?php 
                                $extra = floatval($pay['extra_payment'] ?? 0);
                                echo $extra > 0 ? '+ S/ ' . number_format($extra, 2) : '-';
                                ?>
                            </td>
                            <td class="amount" style="white-space: nowrap;">S/ <?php echo number_format($pay['amount'], 2); ?></td>
                            <td style="white-space: nowrap;">
                                <?php 
                                $st = strtolower($pay['status'] ?? 'pagado');
                                $badgeClass = $st === 'pendiente' ? 'status-pendiente' : 'status-pagado';
                                $icon = $st === 'pendiente' ? 'ph-clock' : 'ph-check-circle';
                                ?>
                                <span class="status-badge <?php echo $badgeClass; ?>">
                                    <i class="ph <?php echo $icon; ?>"></i> <?php echo ucfirst($pay['status'] ?? 'Pagado'); ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if (!empty($pay['voucher_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($pay['voucher_url']); ?>" target="_blank" class="btn-view">
                                        <i class="ph ph-file-pdf"></i> Ver
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 3rem; color: var(--text-muted); background: #f8fafc; border: 1px dashed var(--border-color); border-radius: 8px;">
            <i class="ph ph-receipt" style="font-size: 3rem; margin-bottom: 1rem; color: var(--border-color);"></i>
            <p style="margin:0;">No hay registros de pago para este empleado.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
