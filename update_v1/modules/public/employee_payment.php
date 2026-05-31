<?php
// modules/public/employee_payment.php

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo "<div style='text-align:center; padding: 2rem; font-family: sans-serif;'>Enlace inválido.</div>";
    exit;
}

// Fetch payment details
$stmt = $db->prepare("SELECT ep.*, e.name as employee_name FROM employee_payments ep JOIN employees e ON ep.employee_id = e.id WHERE ep.id = ?");
$stmt->execute([$id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    echo "<div style='text-align:center; padding: 2rem; font-family: sans-serif;'>Registro de pago no encontrado o eliminado.</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Pago - <?php echo htmlspecialchars($payment['employee_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --bg-color: #f3f4f6;
            --surface: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --primary: #10b981;
        }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .card {
            background: var(--surface);
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .header {
            background: var(--primary);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .header i {
            font-size: 3.5rem;
            margin-bottom: 0.5rem;
        }
        .header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
        }
        .body {
            padding: 2rem;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px dashed var(--border-color);
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .label {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.9rem;
        }
        .value {
            font-weight: 600;
            text-align: right;
            max-width: 60%;
        }
        .amount {
            font-size: 1.5rem;
            color: var(--primary);
        }
        .btn-view {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 1rem;
            background: var(--bg-color);
            color: var(--text-main);
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            border-radius: 8px;
            margin-top: 1.5rem;
            transition: all 0.2s;
            border: 1px solid var(--border-color);
            box-sizing: border-box;
        }
        .btn-view:hover {
            background: #e5e7eb;
            color: var(--primary);
        }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <i class="ph ph-check-circle"></i>
        <h1>Registro de Pago</h1>
        <p>Comprobante generado exitosamente</p>
    </div>
    <div class="body">
        <div class="detail-row">
            <span class="label">Empleado</span>
            <span class="value"><?php echo htmlspecialchars($payment['employee_name']); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Concepto</span>
            <span class="value"><?php echo htmlspecialchars($payment['concept']); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Fecha de Pago</span>
            <span class="value"><?php echo htmlspecialchars($payment['payment_date']); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Importe Depositado</span>
            <span class="value amount">S/ <?php echo number_format($payment['amount'], 2); ?></span>
        </div>
        
        <?php if (!empty($payment['voucher_url'])): ?>
            <a href="<?php echo htmlspecialchars('/' . ltrim($payment['voucher_url'], '/')); ?>" target="_blank" class="btn-view">
                <i class="ph ph-file-pdf"></i> Ver Documento Adjunto
            </a>
        <?php else: ?>
            <div style="text-align: center; margin-top: 1.5rem; color: var(--text-muted); font-size: 0.85rem;">
                Sin archivo adjunto
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
