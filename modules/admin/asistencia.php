<?php
// modules/admin/asistencia.php
require_once 'includes/header.php';
global $db;

// Restrict to admins
$stmt_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt_admin->execute([$_SESSION['user_id']]);
$is_admin = ($stmt_admin->fetchColumn() == 1);

// ── Filter Parameters ──────────────────────────────────────────────
$filter_user   = $_GET['user_id']  ?? '';
$filter_period = $_GET['period']   ?? 'semanal';
$filter_from   = $_GET['from']     ?? '';
$filter_to     = $_GET['to']       ?? '';

// Calculate date range based on period
$today = new DateTime();

if ($filter_period === 'personalizado' && $filter_from && $filter_to) {
    $date_from = $filter_from;
    $date_to   = $filter_to;
} else {
    switch ($filter_period) {
        case 'quincenal':
            $date_from = (clone $today)->modify('-14 days')->format('Y-m-d');
            break;
        case 'mensual':
            $date_from = (clone $today)->modify('-30 days')->format('Y-m-d');
            break;
        case 'semanal':
        default:
            $date_from = (clone $today)->modify('-6 days')->format('Y-m-d');
            $filter_period = 'semanal';
            break;
    }
    $date_to = $today->format('Y-m-d');
}

// ── Build Query ────────────────────────────────────────────────────
$where_clauses = ["a.fecha BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if ($filter_user) {
    $where_clauses[] = "a.user_id = ?";
    $params[] = $filter_user;
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

$query = "
    SELECT a.*, u.name as user_name, u.email as user_email, e.work_start, e.work_end
    FROM asistencias a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN employees e ON u.email = e.email
    $where_sql
    ORDER BY a.fecha DESC, u.name ASC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Compute Per-Person Summaries ───────────────────────────────────
$person_summaries = [];
$chart_dates_set = [];

foreach ($asistencias as $row) {
    $uid = $row['user_id'];
    if (!isset($person_summaries[$uid])) {
        $person_summaries[$uid] = [
            'name'             => $row['user_name'],
            'email'            => $row['user_email'],
            'total_worked'     => 0,
            'total_expected'   => 0,
            'days_present'     => 0,
            'days_late'        => 0,
            'days_incomplete'  => 0,
            'daily_worked'     => [],
        ];
    }

    $worked_sec = 0;
    if ($row['salida'] && $row['entrada']) {
        $ent = strtotime($row['entrada']);
        $sal = strtotime($row['salida']);
        $diff = $sal - $ent;
        $ref  = 0;
        if ($row['inicio_refrigerio'] && $row['fin_refrigerio']) {
            $ref = strtotime($row['fin_refrigerio']) - strtotime($row['inicio_refrigerio']);
        }
        $worked_sec = max(0, $diff - $ref);
        $person_summaries[$uid]['days_present']++;
    } elseif ($row['entrada']) {
        $person_summaries[$uid]['days_incomplete']++;
    }

    $person_summaries[$uid]['total_worked'] += $worked_sec;

    // Expected seconds
    $expected_sec = 0;
    if (!empty($row['work_start']) && !empty($row['work_end'])) {
        $exp_ent = strtotime($row['fecha'] . ' ' . $row['work_start']);
        $exp_sal = strtotime($row['fecha'] . ' ' . $row['work_end']);
        $expected_sec = max(0, ($exp_sal - $exp_ent) - 3600);
        $person_summaries[$uid]['total_expected'] += $expected_sec;
    }

    // Late?
    if ($row['entrada'] && !empty($row['work_start'])) {
        $scheduled = strtotime($row['fecha'] . ' ' . $row['work_start']);
        $actual = strtotime($row['entrada']);
        if ($actual > $scheduled + 300) { // 5 min grace
            $person_summaries[$uid]['days_late']++;
        }
    }

    // For chart data
    $dateKey = $row['fecha'];
    $chart_dates_set[$dateKey] = true;
    $person_summaries[$uid]['daily_worked'][$dateKey] = round($worked_sec / 3600, 2);
}

// Global totals
$global_worked   = 0;
$global_expected = 0;
$global_present  = 0;
$global_late     = 0;

foreach ($person_summaries as $ps) {
    $global_worked   += $ps['total_worked'];
    $global_expected += $ps['total_expected'];
    $global_present  += $ps['days_present'];
    $global_late     += $ps['days_late'];
}

$g_hours_worked  = floor($global_worked / 3600);
$g_mins_worked   = floor(($global_worked % 3600) / 60);
$g_hours_expected = floor($global_expected / 3600);
$g_compliance    = $global_expected > 0 ? min(100, round(($global_worked / $global_expected) * 100)) : 0;

// Fetch users for filter
$users_stmt = $db->query("SELECT id, name FROM users ORDER BY name ASC");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Chart labels (all dates in range, sorted)
$chart_labels = array_keys($chart_dates_set);
sort($chart_labels);
$chart_labels_formatted = array_map(function($d) { return date('d/m', strtotime($d)); }, $chart_labels);

// Period labels for display
$period_labels = [
    'semanal'       => 'Última Semana',
    'quincenal'     => 'Última Quincena',
    'mensual'       => 'Último Mes',
    'personalizado' => 'Personalizado',
];
$period_display = $period_labels[$filter_period] ?? 'Semanal';
?>

<style>
/* ── Period Tabs ────────────────────────────── */
.period-tabs {
    display: flex;
    gap: 0;
    background: var(--bg-body);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 4px;
    width: fit-content;
}
.period-tab {
    padding: 0.5rem 1.1rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    border: none;
    background: transparent;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
}
.period-tab:hover {
    color: var(--text-main);
    background: var(--bg-surface);
}
.period-tab.active {
    background: var(--primary-color);
    color: white;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
}
.period-tab i {
    font-size: 1rem;
}

/* ── Summary Cards ──────────────────────────── */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.summary-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.25s ease;
}
.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.06);
}
.summary-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.summary-value {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1;
}
.summary-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-top: 0.25rem;
}

/* ── Person Cards ───────────────────────────── */
.person-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.person-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1.25rem;
    transition: all 0.25s ease;
}
.person-card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    border-color: var(--primary-color);
}
.person-card-header {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    margin-bottom: 1rem;
}
.person-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.95rem;
    color: white;
    flex-shrink: 0;
}
.person-card-name {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-main);
}
.person-card-email {
    font-size: 0.78rem;
    color: var(--text-muted);
}
.person-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
}
.person-stat {
    text-align: center;
    background: var(--bg-body);
    border-radius: 8px;
    padding: 0.6rem 0.4rem;
}
.person-stat-value {
    font-weight: 800;
    font-size: 1rem;
    color: var(--text-main);
    line-height: 1;
}
.person-stat-label {
    font-size: 0.65rem;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    margin-top: 0.25rem;
}
.compliance-bar {
    width: 100%;
    height: 6px;
    background: var(--bg-body);
    border-radius: 3px;
    overflow: hidden;
    margin-top: 0.85rem;
}
.compliance-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.8s ease;
}

/* ── Custom Date Range ──────────────────────── */
.custom-range {
    display: none;
    align-items: flex-end;
    gap: 0.75rem;
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: var(--bg-body);
    border-radius: 10px;
    border: 1px solid var(--border-color);
}
.custom-range.show {
    display: flex;
}
.custom-range .form-group {
    margin-bottom: 0;
}

/* ── Attendance Detail Table ────────────────── */
.attendance-detail-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.attendance-detail-table thead th {
    background: var(--bg-body);
    padding: 0.75rem 1rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.attendance-detail-table tbody td {
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    color: var(--text-main);
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}
.attendance-detail-table tbody tr:last-child td {
    border-bottom: none;
}
.attendance-detail-table tbody tr:hover {
    background: var(--bg-body);
}

.time-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 500;
}
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.65rem;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 600;
}
.status-complete {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}
.status-active {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}
.status-late {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

/* ── Filter area ────────────────────────────── */
.filter-area {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 1.25rem;
    margin-bottom: 2rem;
}
.filter-row {
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    flex-wrap: wrap;
}
.date-range-display {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-muted);
    background: var(--bg-body);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}
.date-range-display i {
    color: var(--primary-color);
}

/* ── Responsive ─────────────────────────────── */
@media (max-width: 768px) {
    .period-tabs {
        flex-wrap: wrap;
    }
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .person-cards-grid {
        grid-template-columns: 1fr;
    }
    .person-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<!-- ── Header ─────────────────────────────────────── -->
<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <a href="index.php?module=admin&action=rrhh" class="btn-action-icon" style="background: var(--bg-color); border: 1px solid var(--border-color); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: var(--text-main); text-decoration: none;">
            <i class="ph ph-arrow-left" style="font-size: 1.2rem;"></i>
        </a>
        <div style="width: 56px; height: 56px; background: var(--bg-color); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
            <i class="ph ph-clock" style="font-size: 1.75rem; color: var(--primary-color);"></i>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Historial de Asistencias</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">
                Monitorea los horarios de entrada, refrigerio y salida del personal &mdash; <?php echo htmlspecialchars($period_display); ?>
            </p>
        </div>
    </div>
    <div class="date-range-display">
        <i class="ph ph-calendar-blank"></i>
        <?php echo date('d/m/Y', strtotime($date_from)); ?> &mdash; <?php echo date('d/m/Y', strtotime($date_to)); ?>
    </div>
</div>

<!-- ── Filters ────────────────────────────────────── -->
<div class="filter-area">
    <form method="GET" action="index.php" id="filterForm">
        <input type="hidden" name="module" value="admin">
        <input type="hidden" name="action" value="asistencia">

        <div class="filter-row">
            <!-- Period Tabs -->
            <div>
                <label class="form-label" style="margin-bottom: 0.4rem; font-size: 0.8rem;">Período</label>
                <div class="period-tabs">
                    <button type="button" class="period-tab <?php echo $filter_period === 'semanal' ? 'active' : ''; ?>" data-period="semanal" onclick="selectPeriod('semanal')">
                        <i class="ph ph-calendar-blank"></i> Semanal
                    </button>
                    <button type="button" class="period-tab <?php echo $filter_period === 'quincenal' ? 'active' : ''; ?>" data-period="quincenal" onclick="selectPeriod('quincenal')">
                        <i class="ph ph-calendar"></i> Quincenal
                    </button>
                    <button type="button" class="period-tab <?php echo $filter_period === 'mensual' ? 'active' : ''; ?>" data-period="mensual" onclick="selectPeriod('mensual')">
                        <i class="ph ph-calendar-dots"></i> Mensual
                    </button>
                    <button type="button" class="period-tab <?php echo $filter_period === 'personalizado' ? 'active' : ''; ?>" data-period="personalizado" onclick="selectPeriod('personalizado')">
                        <i class="ph ph-sliders-horizontal"></i> Personalizado
                    </button>
                </div>
                <input type="hidden" name="period" id="periodInput" value="<?php echo htmlspecialchars($filter_period); ?>">
            </div>

            <!-- Employee Select -->
            <div class="form-group" style="margin-bottom: 0; min-width: 220px;">
                <label class="form-label" style="margin-bottom: 0.4rem; font-size: 0.8rem;">Empleado</label>
                <select name="user_id" class="form-control" style="width: 100%;">
                    <option value="">Todos los empleados</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filter_user == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Action Buttons -->
            <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.4rem; border-radius: 10px; font-weight: 600;">
                <i class="ph ph-funnel"></i> Filtrar
            </button>
            <a href="index.php?module=admin&action=asistencia" class="btn btn-outline" style="padding: 0.6rem 1.2rem; border-radius: 10px;">
                Limpiar
            </a>
        </div>

        <!-- Custom Date Range (hidden unless personalizado) -->
        <div class="custom-range <?php echo $filter_period === 'personalizado' ? 'show' : ''; ?>" id="customRange">
            <div class="form-group">
                <label class="form-label" style="font-size: 0.8rem;">Desde</label>
                <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($filter_from ?: $date_from); ?>" style="width: auto;">
            </div>
            <div class="form-group">
                <label class="form-label" style="font-size: 0.8rem;">Hasta</label>
                <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($filter_to ?: $date_to); ?>" style="width: auto;">
            </div>
        </div>
    </form>
</div>

<?php if (count($asistencias) > 0): ?>

<!-- ── Global Summary ─────────────────────────────── -->
<div class="summary-grid">
    <div class="summary-card">
        <div class="summary-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
            <i class="ph ph-clock"></i>
        </div>
        <div>
            <div class="summary-value"><?php echo $g_hours_worked; ?>h <?php echo $g_mins_worked; ?>m</div>
            <div class="summary-label">Horas Trabajadas</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
            <i class="ph ph-calendar-check"></i>
        </div>
        <div>
            <div class="summary-value"><?php echo $g_hours_expected; ?>h</div>
            <div class="summary-label">Horas Esperadas</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
            <i class="ph ph-chart-line-up"></i>
        </div>
        <div>
            <div class="summary-value"><?php echo $g_compliance; ?>%</div>
            <div class="summary-label">Cumplimiento</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
            <i class="ph ph-warning-circle"></i>
        </div>
        <div>
            <div class="summary-value"><?php echo $global_late; ?></div>
            <div class="summary-label">Llegadas Tarde</div>
        </div>
    </div>
</div>

<!-- ── Per-Person Summary Cards ───────────────────── -->
<?php if (count($person_summaries) > 1 || !$filter_user): ?>
<h3 style="margin: 0 0 1rem 0; font-size: 1.1rem; font-weight: 700; color: var(--color-title); display: flex; align-items: center; gap: 0.5rem;">
    <i class="ph ph-users-three" style="color: var(--primary-color);"></i> Resumen por Empleado
</h3>
<div class="person-cards-grid">
    <?php
    $avatar_colors = ['#10b981','#3b82f6','#8b5cf6','#f59e0b','#ef4444','#ec4899','#06b6d4','#f97316'];
    $idx = 0;
    foreach ($person_summaries as $uid => $ps):
        $initials = '';
        $parts = explode(' ', $ps['name']);
        foreach ($parts as $p) { $initials .= mb_strtoupper(mb_substr($p, 0, 1)); if (strlen($initials) >= 2) break; }
        $color = $avatar_colors[$idx % count($avatar_colors)];
        $ps_hours = floor($ps['total_worked'] / 3600);
        $ps_mins  = floor(($ps['total_worked'] % 3600) / 60);
        $ps_compliance = $ps['total_expected'] > 0 ? min(100, round(($ps['total_worked'] / $ps['total_expected']) * 100)) : 0;
        $bar_color = $ps_compliance >= 90 ? '#10b981' : ($ps_compliance >= 70 ? '#f59e0b' : '#ef4444');
        $idx++;
    ?>
    <div class="person-card">
        <div class="person-card-header">
            <div class="person-avatar" style="background: <?php echo $color; ?>;"><?php echo $initials; ?></div>
            <div>
                <div class="person-card-name"><?php echo htmlspecialchars($ps['name']); ?></div>
                <div class="person-card-email"><?php echo htmlspecialchars($ps['email']); ?></div>
            </div>
        </div>
        <div class="person-stats">
            <div class="person-stat">
                <div class="person-stat-value"><?php echo $ps_hours; ?>h <?php echo $ps_mins; ?>m</div>
                <div class="person-stat-label">Trabajadas</div>
            </div>
            <div class="person-stat">
                <div class="person-stat-value"><?php echo $ps['days_present']; ?></div>
                <div class="person-stat-label">Días</div>
            </div>
            <div class="person-stat">
                <div class="person-stat-value" style="color: #f59e0b;"><?php echo $ps['days_late']; ?></div>
                <div class="person-stat-label">Tarde</div>
            </div>
            <div class="person-stat">
                <div class="person-stat-value"><?php echo $ps_compliance; ?>%</div>
                <div class="person-stat-label">Cumpl.</div>
            </div>
        </div>
        <div class="compliance-bar">
            <div class="compliance-fill" style="width: <?php echo $ps_compliance; ?>%; background: <?php echo $bar_color; ?>;"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Chart ───────────────────────────────────────── -->
<div class="card" style="margin-bottom: 2rem;">
    <h3 style="margin: 0 0 1rem 0; font-size: 1.05rem; display: flex; align-items: center; gap: 0.5rem; font-weight: 700;">
        <i class="ph ph-chart-bar" style="color: var(--primary-color);"></i> Horas por Día
        <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 400; margin-left: auto;">
            <?php echo date('d/m', strtotime($date_from)); ?> – <?php echo date('d/m', strtotime($date_to)); ?>
        </span>
    </h3>
    <div style="height: 300px; width: 100%;">
        <canvas id="attendanceChart"></canvas>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const labels = <?php echo json_encode($chart_labels_formatted); ?>;
    const datasets = [];
    const colors = ['#10b981','#3b82f6','#8b5cf6','#f59e0b','#ef4444','#ec4899','#06b6d4','#f97316'];
    const personData = <?php
        $chart_data = [];
        $ci = 0;
        foreach ($person_summaries as $uid => $ps) {
            $data = [];
            foreach ($chart_labels as $dl) {
                $data[] = $ps['daily_worked'][$dl] ?? 0;
            }
            $chart_data[] = [
                'label' => $ps['name'],
                'data'  => $data,
                'color' => $avatar_colors[$ci % count($avatar_colors)],
            ];
            $ci++;
        }
        echo json_encode($chart_data);
    ?>;

    personData.forEach((p, i) => {
        datasets.push({
            label: p.label,
            data: p.data,
            backgroundColor: p.color + '90',
            borderColor: p.color,
            borderWidth: 2,
            borderRadius: 4,
            barPercentage: personData.length > 1 ? 0.7 : 0.5,
        });
    });

    const ctx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { borderDash: [2,4], color: 'rgba(0,0,0,0.06)' },
                    title: { display: true, text: 'Horas', font: { size: 11, weight: '600' }, color: '#94a3b8' }
                },
                x: { grid: { display: false } }
            },
            plugins: {
                legend: {
                    display: personData.length > 1,
                    position: 'top',
                    labels: { usePointStyle: true, boxWidth: 8, font: { size: 12 } }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    padding: 12,
                    cornerRadius: 8,
                    titleFont: { weight: '600' },
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y}h`
                    }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>

<!-- ── Detailed Table ─────────────────────────────── -->
<div class="card">
    <h3 style="margin: 0 0 1rem 0; font-size: 1.05rem; display: flex; align-items: center; gap: 0.5rem; font-weight: 700;">
        <i class="ph ph-list-dashes" style="color: var(--primary-color);"></i> Detalle de Registros
        <?php if (count($asistencias) > 0): ?>
        <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 400; margin-left: auto;">
            <?php echo count($asistencias); ?> registro<?php echo count($asistencias) !== 1 ? 's' : ''; ?>
        </span>
        <?php endif; ?>
    </h3>
    <div class="table-responsive">
        <table class="attendance-detail-table">
            <thead>
                <tr>
                    <th>EMPLEADO</th>
                    <th>FECHA</th>
                    <th>ENTRADA</th>
                    <th>INICIO REFRIG.</th>
                    <th>FIN REFRIG.</th>
                    <th>SALIDA</th>
                    <th>ESTADO / HORAS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($asistencias) === 0): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class="ph ph-empty" style="font-size: 2rem; display: block; margin-bottom: 0.5rem; opacity: 0.4;"></i>
                            No se encontraron registros de asistencia para el período seleccionado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $prev_date = '';
                    foreach ($asistencias as $row):
                        $is_new_date = ($row['fecha'] !== $prev_date);
                        $prev_date = $row['fecha'];

                        // Calculate status
                        $is_late = false;
                        if ($row['entrada'] && !empty($row['work_start'])) {
                            $scheduled = strtotime($row['fecha'] . ' ' . $row['work_start']);
                            $actual = strtotime($row['entrada']);
                            $is_late = ($actual > $scheduled + 300);
                        }
                    ?>
                    <?php if ($is_new_date): ?>
                    <tr>
                        <td colspan="7" style="background: var(--bg-body); padding: 0.5rem 1rem; font-weight: 700; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-color);">
                            <i class="ph ph-calendar-blank" style="margin-right: 0.3rem;"></i>
                            <?php
                            $dateObj = new DateTime($row['fecha']);
                            $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                            echo $dias[(int)$dateObj->format('w')] . ', ' . $dateObj->format('d/m/Y');
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($row['user_name']); ?></div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['user_email']); ?></div>
                        </td>
                        <td style="white-space: nowrap;"><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                        <td>
                            <?php if ($row['entrada']): ?>
                                <span class="time-badge">
                                    <i class="ph ph-sign-in" style="color: <?php echo $is_late ? '#f59e0b' : '#10b981'; ?>;"></i>
                                    <?php echo date('H:i', strtotime($row['entrada'])); ?>
                                    <?php if ($is_late): ?>
                                        <span style="font-size: 0.7rem; color: #f59e0b; font-weight: 600;">(tarde)</span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['inicio_refrigerio']): ?>
                                <span class="time-badge"><i class="ph ph-coffee" style="color: #f59e0b;"></i> <?php echo date('H:i', strtotime($row['inicio_refrigerio'])); ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['fin_refrigerio']): ?>
                                <span class="time-badge"><i class="ph ph-play" style="color: var(--primary-color);"></i> <?php echo date('H:i', strtotime($row['fin_refrigerio'])); ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['salida']): ?>
                                <span class="time-badge"><i class="ph ph-sign-out" style="color: var(--danger-color, #ef4444);"></i> <?php echo date('H:i', strtotime($row['salida'])); ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                                if ($row['work_start'] && $row['work_end']) {
                                    $hs = substr($row['work_start'],0,5);
                                    $he = substr($row['work_end'],0,5);
                                    echo "<div style='font-size:0.72rem; color:var(--text-muted); margin-bottom:0.2rem;'>Horario: {$hs} - {$he}</div>";
                                }
                                if ($row['salida'] && $row['entrada']) {
                                    $ent = strtotime($row['entrada']);
                                    $sal = strtotime($row['salida']);
                                    $diff_total = $sal - $ent;
                                    $ref_diff = 0;
                                    if ($row['inicio_refrigerio'] && $row['fin_refrigerio']) {
                                        $ref_diff = strtotime($row['fin_refrigerio']) - strtotime($row['inicio_refrigerio']);
                                    }
                                    $worked_seconds = $diff_total - $ref_diff;
                                    $hours = floor($worked_seconds / 3600);
                                    $minutes = floor(($worked_seconds % 3600) / 60);

                                    if ($is_late) {
                                        echo "<span class='status-pill status-late'><i class='ph ph-warning-circle'></i> {$hours}h {$minutes}m</span>";
                                    } else {
                                        echo "<span class='status-pill status-complete'><i class='ph ph-check-circle'></i> {$hours}h {$minutes}m</span>";
                                    }
                                } elseif ($row['entrada']) {
                                    echo "<span class='status-pill status-active'><i class='ph ph-circle-notch'></i> En curso</span>";
                                } else {
                                    echo "–";
                                }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function selectPeriod(period) {
    document.getElementById('periodInput').value = period;
    document.querySelectorAll('.period-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.period-tab[data-period="${period}"]`).classList.add('active');

    const customRange = document.getElementById('customRange');
    if (period === 'personalizado') {
        customRange.classList.add('show');
    } else {
        customRange.classList.remove('show');
        // Auto-submit on non-custom period selection
        document.getElementById('filterForm').submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
