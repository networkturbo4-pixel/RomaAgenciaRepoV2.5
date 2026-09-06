<?php
// modules/suppliers/ajax_get_analytics.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $db = (new Database())->getConnection();

    $period_type = $_GET['period_type'] ?? 'month'; // 'month', '3m', '6m', '12m', 'all'
    $selected_month = $_GET['month'] ?? date('Y-m'); // e.g., '2026-09'

    $meses_es = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    $today = date('Y-m-d');
    $current_month_first_day = date('Y-m-01', strtotime($selected_month . '-01'));
    $current_month_last_day = date('Y-m-t', strtotime($selected_month . '-01'));

    $date_where = "";
    $params = [];
    $period_label = "";

    switch ($period_type) {
        case 'month':
            $date_where = "p.payment_date >= ? AND p.payment_date <= ?";
            $params = [$current_month_first_day, $current_month_last_day];
            $m_num = intval(date('n', strtotime($current_month_first_day)));
            $period_label = ($meses_es[$m_num] ?? date('F', strtotime($current_month_first_day))) . ' ' . date('Y', strtotime($current_month_first_day));
            break;

        case '3m':
            $start_3m = date('Y-m-01', strtotime($current_month_last_day . ' -2 months'));
            $date_where = "p.payment_date >= ? AND p.payment_date <= ?";
            $params = [$start_3m, $current_month_last_day];
            $m_s = $meses_es[intval(date('n', strtotime($start_3m)))] ?? date('M', strtotime($start_3m));
            $m_e = $meses_es[intval(date('n', strtotime($current_month_last_day)))] ?? date('M', strtotime($current_month_last_day));
            $period_label = "Últimos 3 Meses (" . substr($m_s, 0, 3) . " " . date('Y', strtotime($start_3m)) . " - " . substr($m_e, 0, 3) . " " . date('Y', strtotime($current_month_last_day)) . ")";
            break;

        case '6m':
            $start_6m = date('Y-m-01', strtotime($current_month_last_day . ' -5 months'));
            $date_where = "p.payment_date >= ? AND p.payment_date <= ?";
            $params = [$start_6m, $current_month_last_day];
            $m_s = $meses_es[intval(date('n', strtotime($start_6m)))] ?? date('M', strtotime($start_6m));
            $m_e = $meses_es[intval(date('n', strtotime($current_month_last_day)))] ?? date('M', strtotime($current_month_last_day));
            $period_label = "Últimos 6 Meses (" . substr($m_s, 0, 3) . " " . date('Y', strtotime($start_6m)) . " - " . substr($m_e, 0, 3) . " " . date('Y', strtotime($current_month_last_day)) . ")";
            break;

        case '12m':
            $start_12m = date('Y-m-01', strtotime($current_month_last_day . ' -11 months'));
            $date_where = "p.payment_date >= ? AND p.payment_date <= ?";
            $params = [$start_12m, $current_month_last_day];
            $m_s = $meses_es[intval(date('n', strtotime($start_12m)))] ?? date('M', strtotime($start_12m));
            $m_e = $meses_es[intval(date('n', strtotime($current_month_last_day)))] ?? date('M', strtotime($current_month_last_day));
            $period_label = "Últimos 12 Meses (" . substr($m_s, 0, 3) . " " . date('Y', strtotime($start_12m)) . " - " . substr($m_e, 0, 3) . " " . date('Y', strtotime($current_month_last_day)) . ")";
            break;

        case 'all':
        default:
            $date_where = "1=1";
            $params = [];
            $period_label = "Todo el Historial";
            break;
    }

    // 1. Fetch all suppliers
    $stmtSuppliers = $db->query("SELECT * FROM suppliers ORDER BY status ASC, name ASC");
    $all_suppliers = $stmtSuppliers->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch payments in the given date range
    $sqlPayments = "SELECT p.*, s.name as supplier_name, s.category as supplier_category 
                    FROM supplier_payments p 
                    JOIN suppliers s ON p.supplier_id = s.id 
                    WHERE {$date_where} 
                    ORDER BY p.payment_date DESC, p.id DESC";
    $stmtPayments = $db->prepare($sqlPayments);
    $stmtPayments->execute($params);
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch services in range (by period_month)
    $service_where = "1=1";
    $svc_params = [];
    if ($period_type === 'month') {
        $service_where = "period_month = ?";
        $svc_params = [$selected_month];
    } elseif ($period_type === '3m') {
        $svc_start = date('Y-m', strtotime($start_3m));
        $svc_end = date('Y-m', strtotime($current_month_last_day));
        $service_where = "period_month >= ? AND period_month <= ?";
        $svc_params = [$svc_start, $svc_end];
    } elseif ($period_type === '6m') {
        $svc_start = date('Y-m', strtotime($start_6m));
        $svc_end = date('Y-m', strtotime($current_month_last_day));
        $service_where = "period_month >= ? AND period_month <= ?";
        $svc_params = [$svc_start, $svc_end];
    } elseif ($period_type === '12m') {
        $svc_start = date('Y-m', strtotime($start_12m));
        $svc_end = date('Y-m', strtotime($current_month_last_day));
        $service_where = "period_month >= ? AND period_month <= ?";
        $svc_params = [$svc_start, $svc_end];
    }

    $stmtSvc = $db->prepare("SELECT * FROM supplier_services WHERE {$service_where}");
    $stmtSvc->execute($svc_params);
    $services = $stmtSvc->fetchAll(PDO::FETCH_ASSOC);

    // 4. Aggregate metrics
    $total_paid_pen = 0;
    $total_paid_usd = 0;
    $total_pending_pen = 0;
    $total_pending_usd = 0;

    $supplier_stats = [];
    foreach ($all_suppliers as $s) {
        $supplier_stats[$s['id']] = [
            'id' => $s['id'],
            'name' => $s['name'],
            'contact_name' => $s['contact_name'],
            'category' => $s['category'] ?: 'General',
            'email' => $s['email'],
            'phone' => $s['phone'],
            'tax_id' => $s['tax_id'],
            'address' => $s['address'],
            'bank_info' => $s['bank_info'],
            'notes' => $s['notes'],
            'status' => $s['status'],
            'public_token' => $s['public_token'],
            'created_at' => $s['created_at'],
            'paid_pen' => 0,
            'paid_usd' => 0,
            'pending_pen' => 0,
            'pending_usd' => 0,
            'payments_count' => 0,
            'services_count' => 0,
            'last_payment_date' => null,
            'last_payment_amount' => 0,
            'last_payment_currency' => 'PEN'
        ];
    }

    foreach ($payments as $p) {
        $sid = $p['supplier_id'];
        $amount = floatval($p['amount']);
        $curr = $p['currency'];

        if ($p['status'] === 'paid') {
            if ($curr === 'USD') {
                $total_paid_usd += $amount;
                if (isset($supplier_stats[$sid])) $supplier_stats[$sid]['paid_usd'] += $amount;
            } else {
                $total_paid_pen += $amount;
                if (isset($supplier_stats[$sid])) $supplier_stats[$sid]['paid_pen'] += $amount;
            }
        } elseif ($p['status'] === 'pending') {
            if ($curr === 'USD') {
                $total_pending_usd += $amount;
                if (isset($supplier_stats[$sid])) $supplier_stats[$sid]['pending_usd'] += $amount;
            } else {
                $total_pending_pen += $amount;
                if (isset($supplier_stats[$sid])) $supplier_stats[$sid]['pending_pen'] += $amount;
            }
        }

        if (isset($supplier_stats[$sid])) {
            $supplier_stats[$sid]['payments_count']++;
            if (!$supplier_stats[$sid]['last_payment_date'] || $p['payment_date'] > $supplier_stats[$sid]['last_payment_date']) {
                $supplier_stats[$sid]['last_payment_date'] = $p['payment_date'];
                $supplier_stats[$sid]['last_payment_amount'] = $amount;
                $supplier_stats[$sid]['last_payment_currency'] = $curr;
            }
        }
    }

    foreach ($services as $svc) {
        $sid = $svc['supplier_id'];
        if (isset($supplier_stats[$sid])) {
            $supplier_stats[$sid]['services_count']++;
        }
    }

    // Identify top supplier (by total paid normalized or PEN)
    $top_supplier = null;
    $max_paid = 0;
    foreach ($supplier_stats as $s) {
        $equiv = $s['paid_pen'] + ($s['paid_usd'] * 3.75);
        if ($equiv > $max_paid) {
            $max_paid = $equiv;
            $top_supplier = $s;
        }
    }

    // Sort suppliers list: active first, then by total paid descending
    $suppliers_list = array_values($supplier_stats);
    usort($suppliers_list, function($a, $b) {
        $equivA = $a['paid_pen'] + ($a['paid_usd'] * 3.75);
        $equivB = $b['paid_pen'] + ($b['paid_usd'] * 3.75);
        return $equivB <=> $equivA;
    });

    echo json_encode([
        'success' => true,
        'period_type' => $period_type,
        'period_label' => $period_label,
        'selected_month' => $selected_month,
        'kpis' => [
            'total_paid_pen' => $total_paid_pen,
            'total_paid_usd' => $total_paid_usd,
            'total_pending_pen' => $total_pending_pen,
            'total_pending_usd' => $total_pending_usd,
            'top_supplier' => $top_supplier ? [
                'id' => $top_supplier['id'],
                'name' => $top_supplier['name'],
                'category' => $top_supplier['category'],
                'paid_pen' => $top_supplier['paid_pen'],
                'paid_usd' => $top_supplier['paid_usd']
            ] : null,
            'active_suppliers_count' => count(array_filter($all_suppliers, fn($s) => $s['status'] === 'active')),
            'total_payments_count' => count($payments),
            'total_services_count' => count($services)
        ],
        'suppliers' => $suppliers_list,
        'payments' => $payments
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
