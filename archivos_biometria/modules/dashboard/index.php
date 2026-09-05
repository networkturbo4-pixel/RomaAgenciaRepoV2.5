<?php
// modules/dashboard/index.php
require_once 'includes/header.php';
global $db;

// Verify user role and permissions
$stmt_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt_admin->execute([$_SESSION['user_id']]);
$is_admin = ($stmt_admin->fetchColumn() == 1);
$user_permissions = $_SESSION['user_permissions'] ?? [];

function has_perm($module) {
    global $is_admin, $user_permissions;
    return $is_admin || in_array($module, $user_permissions);
}

// Fetch Next Meeting if permitted
$next_meet = null;
if (has_perm('reuniones')) {
    try {
        $stmt_next_meet = $db->prepare("SELECT r.*, b.name as brand_name FROM reuniones r LEFT JOIN client_brands b ON r.brand_id = b.id WHERE r.fecha_hora > NOW() AND r.estado = 'Programada' ORDER BY r.fecha_hora ASC LIMIT 1");
        $stmt_next_meet->execute();
        $next_meet = $stmt_next_meet->fetch(PDO::FETCH_ASSOC);
    } catch(Exception $e) {}
}

// Dynamic Stats
$stats = [];

if (has_perm('quotes')) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM quotes");
        $stats[] = [
            'icon' => 'ph-file-text',
            'color' => '#3b82f6',
            'value' => $stmt->fetchColumn(),
            'label' => 'Cotizaciones',
            'link' => 'index.php?module=quotes&action=index'
        ];
    } catch (Exception $e) {}
}

if (has_perm('project_board')) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM project_months");
        $stats[] = [
            'icon' => 'ph-kanban',
            'color' => '#8b5cf6',
            'value' => $stmt->fetchColumn(),
            'label' => 'Proyectos Activos',
            'link' => 'index.php?module=projects&action=index'
        ];
    } catch (Exception $e) {}
}

if (has_perm('month_board')) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM month_posts");
        $stats[] = [
            'icon' => 'ph-calendar-check',
            'color' => '#10b981',
            'value' => $stmt->fetchColumn(),
            'label' => 'Tareas del Mes',
            'link' => 'index.php?module=tasks&action=index'
        ];
    } catch (Exception $e) {}
}

if (has_perm('reuniones')) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM reuniones WHERE fecha_hora > NOW() AND estado = 'Programada'");
        $stats[] = [
            'icon' => 'ph-video-camera',
            'color' => '#f59e0b',
            'value' => $stmt->fetchColumn(),
            'label' => 'Reuniones Futuras',
            'link' => 'index.php?module=reuniones&action=index'
        ];
    } catch (Exception $e) {}
}

// Chart Data (Recent Activity Volume)
$chart_labels = [];
$chart_data = [];
$chart_title = "Actividad General";

if (has_perm('quotes')) {
    $chart_title = "Cotizaciones Emitidas (Últimos 14 días)";
    try {
        $stmt = $db->query("SELECT DATE(created_at) as date, COUNT(*) as count FROM quotes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY date ASC");
        $temp_data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $temp_data[$row['date']] = $row['count'];
        }
        // Fill empty days
        for($i=13; $i>=0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $chart_labels[] = date('d/m', strtotime($d));
            $chart_data[] = $temp_data[$d] ?? 0;
        }
    } catch(Exception $e){}
} elseif(has_perm('project_board')) {
    $chart_title = "Proyectos Creados (Últimos 14 días)";
    try {
        $stmt = $db->query("SELECT DATE(created_at) as date, COUNT(*) as count FROM project_months WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY date ASC");
        $temp_data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $temp_data[$row['date']] = $row['count'];
        }
        for($i=13; $i>=0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $chart_labels[] = date('d/m', strtotime($d));
            $chart_data[] = $temp_data[$d] ?? 0;
        }
    } catch(Exception $e){}
} else {
    for($i=6; $i>=0; $i--) {
        $chart_labels[] = date('d/m', strtotime("-$i days"));
        $chart_data[] = 0;
    }
}

// Recent Activity Feed
$recent_activity = [];
if (has_perm('quotes')) {
    try {
        $stmt = $db->query("SELECT id, 'quote' as type, created_at, status FROM quotes ORDER BY created_at DESC LIMIT 5");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recent_activity[] = [
                'title' => 'Cotización #' . str_pad($r['id'], 4, '0', STR_PAD_LEFT),
                'desc' => 'Estado: ' . $r['status'],
                'date' => $r['created_at'],
                'icon' => 'ph-file-text',
                'color' => '#3b82f6',
                'link' => 'index.php?module=quotes&action=index'
            ];
        }
    } catch(Exception $e){}
}
if (has_perm('project_board')) {
    try {
        $stmt = $db->query("SELECT id, name, created_at FROM project_months ORDER BY created_at DESC LIMIT 5");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recent_activity[] = [
                'title' => 'Proyecto: ' . $r['name'],
                'desc' => 'Nueva carpeta de proyecto',
                'date' => $r['created_at'],
                'icon' => 'ph-kanban',
                'color' => '#8b5cf6',
                'link' => 'index.php?module=projects&action=index'
            ];
        }
    } catch(Exception $e){}
}
if (has_perm('month_board')) {
    try {
        $stmt = $db->query("SELECT id, type, created_at FROM month_posts ORDER BY created_at DESC LIMIT 5");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recent_activity[] = [
                'title' => 'Nueva Tarea (' . $r['type'] . ')',
                'desc' => 'Tablero mensual actualizado',
                'date' => $r['created_at'],
                'icon' => 'ph-calendar-check',
                'color' => '#10b981',
                'link' => 'index.php?module=tasks&action=index'
            ];
        }
    } catch(Exception $e){}
}
// Sort recent activity descending
usort($recent_activity, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recent_activity = array_slice($recent_activity, 0, 7);

?>

<!-- Tailwind-like custom CSS for the Dashboard -->
<style>
    .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
    .dash-title { margin: 0; font-size: 1.75rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; }
    
    .quick-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .btn-glass { 
        background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); 
        border: 1px solid rgba(0,0,0,0.05); color: var(--text-main); 
        border-radius: 12px; padding: 0.5rem 1rem; font-weight: 600; 
        display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;
        transition: all 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }
    .btn-glass:hover { background: white; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); color: var(--primary-color); }
    [data-theme="dark"] .btn-glass { background: rgba(30,41,59,0.7); color: #f8fafc; border-color: rgba(255,255,255,0.05); }
    [data-theme="dark"] .btn-glass:hover { background: #1e293b; color: var(--primary-color); }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card {
        background: var(--surface-color); border-radius: 20px; padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid var(--border-color);
        display: flex; align-items: center; gap: 1rem; transition: transform 0.3s;
        text-decoration: none; color: inherit;
    }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.06); }
    .stat-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; }
    .stat-val { font-size: 2rem; font-weight: 800; margin: 0; line-height: 1; letter-spacing: -1px; }
    .stat-label { color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; margin-top: 0.25rem; letter-spacing: 0.5px; }

    .dashboard-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
    @media (max-width: 1024px) { .dashboard-layout { grid-template-columns: 1fr; } }
    
    .chart-card, .activity-card {
        background: var(--surface-color); border-radius: 20px; padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid var(--border-color);
    }
    .card-title { font-size: 1.1rem; font-weight: 700; margin: 0 0 1.5rem 0; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem; }
    
    .activity-list { list-style: none; padding: 0; margin: 0; }
    .activity-item { display: flex; align-items: flex-start; gap: 1rem; padding-bottom: 1.25rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); }
    .activity-item:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
    .activity-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
    .activity-details h4 { margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-main); }
    .activity-details p { margin: 0.2rem 0 0 0; font-size: 0.8rem; color: var(--text-muted); }
    .activity-time { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-left: auto; white-space: nowrap; }

    .meeting-card {
        background: linear-gradient(135deg, var(--primary-color) 0%, #1e1b4b 100%);
        border-radius: 20px; padding: 2rem; color: white; margin-bottom: 2rem;
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;
        box-shadow: 0 15px 30px rgba(var(--primary-rgb, 79, 70, 229), 0.2);
    }
    .meeting-time { font-family: 'Courier New', Courier, monospace; font-size: 2rem; font-weight: 800; background: rgba(0,0,0,0.2); padding: 0.5rem 1rem; border-radius: 12px; }
</style>

<div class="dash-header">
    <h1 class="dash-title">Hola, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?> 👋</h1>
    <div class="quick-actions">
        <?php if(has_perm('quotes')): ?>
            <a href="index.php?module=quotes&action=form" class="btn-glass"><i class="ph ph-file-plus"></i> Nueva Cotización</a>
        <?php endif; ?>
        <?php if(has_perm('projects') || has_perm('project_board')): ?>
            <a href="index.php?module=projects&action=index" class="btn-glass"><i class="ph ph-folder-plus"></i> Nuevo Proyecto</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($next_meet): ?>
<div class="meeting-card">
    <div>
        <h3 style="margin: 0 0 0.5rem 0; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
            <i class="ph ph-video-camera" style="color: #f87171;"></i> Próxima Reunión: <?php echo htmlspecialchars($next_meet['brand_name']); ?>
        </h3>
        <p style="margin: 0; font-size: 0.95rem; color: #cbd5e1; opacity: 0.9;">
            <?php echo htmlspecialchars($next_meet['motivo']); ?> &bull; <?php echo date('d M Y - h:i A', strtotime($next_meet['fecha_hora'])); ?>
        </p>
    </div>
    <div style="text-align: right;">
        <div id="meet-countdown" class="meeting-time" data-time="<?php echo $next_meet['fecha_hora']; ?>">--:--:--</div>
        <?php if ($next_meet['meet_link']): ?>
            <a href="<?php echo htmlspecialchars($next_meet['meet_link']); ?>" target="_blank" class="btn" style="background: white; color: var(--primary-color); font-weight: 700; margin-top: 1rem; border-radius: 10px;">Unirse a la Llamada</a>
        <?php endif; ?>
    </div>
</div>
<script>
    const meetTime = new Date(document.getElementById('meet-countdown').dataset.time).getTime();
    setInterval(() => {
        const now = new Date().getTime();
        const distance = meetTime - now;
        if (distance < 0) {
            document.getElementById('meet-countdown').innerHTML = "¡EN CURSO!"; return;
        }
        const d = Math.floor(distance / (1000 * 60 * 60 * 24));
        const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const s = Math.floor((distance % (1000 * 60)) / 1000);
        document.getElementById('meet-countdown').innerHTML = (d>0?d+"d ":"") + (h<10?"0":"")+h + ":" + (m<10?"0":"")+m + ":" + (s<10?"0":"")+s;
    }, 1000);
</script>
<?php else: ?>
<?php if(has_perm('reuniones')): ?>
<div class="meeting-card" style="background: linear-gradient(135deg, var(--surface-color) 0%, var(--bg-color) 100%); color: var(--text-main); border: 1px dashed var(--border-color); box-shadow: none;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--bg-color); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--text-muted); border: 1px solid var(--border-color);">
            <i class="ph ph-calendar-blank"></i>
        </div>
        <div>
            <h3 style="margin: 0 0 0.25rem 0; font-size: 1.1rem; font-weight: 700;">No hay reuniones próximas</h3>
            <p style="margin: 0; font-size: 0.9rem; color: var(--text-muted);">Tienes tu agenda libre por el momento.</p>
        </div>
    </div>
    <div>
        <a href="index.php?module=reuniones&action=index" class="btn-glass" style="background: var(--surface-color); border: 1px solid var(--border-color); color: var(--text-main);">
            <i class="ph ph-plus"></i> Agendar Reunión
        </a>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (count($stats) > 0): ?>
<div class="stats-grid">
    <?php foreach($stats as $s): ?>
    <a href="<?php echo $s['link']; ?>" class="stat-card">
        <div class="stat-icon" style="background: <?php echo $s['color']; ?>15; color: <?php echo $s['color']; ?>;">
            <i class="ph <?php echo $s['icon']; ?>"></i>
        </div>
        <div>
            <h3 class="stat-val"><?php echo number_format($s['value']); ?></h3>
            <p class="stat-label"><?php echo $s['label']; ?></p>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="dashboard-layout">
    <div class="chart-card">
        <h2 class="card-title"><i class="ph ph-chart-line-up" style="color: var(--primary-color);"></i> <?php echo $chart_title; ?></h2>
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="activityChart"></canvas>
        </div>
    </div>
    
    <div class="activity-card">
        <h2 class="card-title"><i class="ph ph-lightning" style="color: #eab308;"></i> Actividad Reciente</h2>
        <?php if(count($recent_activity) > 0): ?>
        <ul class="activity-list">
            <?php foreach($recent_activity as $act): 
                // Time ago logic
                $time_ago = strtotime($act['date']);
                $diff = time() - $time_ago;
                if ($diff < 3600) $time_str = floor($diff/60) . 'm';
                elseif ($diff < 86400) $time_str = floor($diff/3600) . 'h';
                else $time_str = floor($diff/86400) . 'd';
            ?>
            <li class="activity-item">
                <div class="activity-icon" style="background: <?php echo $act['color']; ?>15; color: <?php echo $act['color']; ?>;">
                    <i class="ph <?php echo $act['icon']; ?>"></i>
                </div>
                <div class="activity-details">
                    <a href="<?php echo $act['link']; ?>" style="text-decoration:none;"><h4><?php echo $act['title']; ?></h4></a>
                    <p><?php echo $act['desc']; ?></p>
                </div>
                <div class="activity-time">Hace <?php echo $time_str; ?></div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <div style="text-align: center; padding: 2rem 0; color: var(--text-muted);">
            <i class="ph ph-ghost" style="font-size: 2.5rem; opacity: 0.5;"></i>
            <p style="margin-top: 0.5rem;">No hay actividad reciente.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- WebAuthn Banner -->
<div class="meeting-card" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); margin-bottom:0;">
    <div>
        <h3 style="margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;"><i class="ph ph-fingerprint"></i> Inicio de Sesión Biométrico</h3>
        <p style="margin: 0.5rem 0 0 0; font-size: 13px; opacity: 0.9;">Registra tu huella digital o FaceID en este dispositivo para iniciar sesión rápidamente.</p>
    </div>
    <button onclick="registerBiometrics()" class="btn-glass" style="color: #0f172a;">Registrar Dispositivo</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function base64ToArrayBuffer(base64) {
        var binary_string = window.atob(base64);
        var len = binary_string.length;
        var bytes = new Uint8Array(len);
        for (var i = 0; i < len; i++) {
            bytes[i] = binary_string.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function arrayBufferToBase64(buffer) {
        var binary = '';
        var bytes = new Uint8Array(buffer);
        var len = bytes.byteLength;
        for (var i = 0; i < len; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    function decodeArgs(obj) {
        const prefix = '=?BINARY?B?';
        const suffix = '?=';
        if (typeof obj === 'string') {
            if (obj.startsWith(prefix) && obj.endsWith(suffix)) {
                let b64 = obj.substring(prefix.length, obj.length - suffix.length);
                b64 = b64.replace(/-/g, '+').replace(/_/g, '/');
                return base64ToArrayBuffer(b64);
            }
        } else if (typeof obj === 'object' && obj !== null) {
            for (let key in obj) {
                obj[key] = decodeArgs(obj[key]);
            }
        }
        return obj;
    }

    async function registerBiometrics() {
        if (!window.PublicKeyCredential) {
            alert("Autenticación biométrica no soportada en este navegador.");
            return;
        }

        try {
            const res = await fetch('modules/auth/webauthn_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_register_args'
            });
            const data = await res.json();
            
            if (data.error) {
                alert(data.error); return;
            }

            const args = decodeArgs(data.args);

            // Limpiar parámetros incompatibles con Google Credential Manager (Android)
            if (args.publicKey) {
                if (args.publicKey.extensions) {
                    delete args.publicKey.extensions;
                }
                if (!args.publicKey.authenticatorSelection) {
                    args.publicKey.authenticatorSelection = {};
                }
                args.publicKey.authenticatorSelection.authenticatorAttachment = 'platform';
                args.publicKey.authenticatorSelection.userVerification = 'preferred';
                args.publicKey.authenticatorSelection.residentKey = 'discouraged';
                args.publicKey.authenticatorSelection.requireResidentKey = false;
                args.publicKey.attestation = 'none';
            }

            let credential;
            try {
                credential = await navigator.credentials.create(args);
            } catch (firstErr) {
                console.warn('Primer intento falló, reintentando sin restricción de plataforma:', firstErr.message);
                if (args.publicKey && args.publicKey.authenticatorSelection) {
                    delete args.publicKey.authenticatorSelection.authenticatorAttachment;
                }
                credential = await navigator.credentials.create(args);
            }

            const registerData = new URLSearchParams();
            registerData.append('action', 'process_register');
            registerData.append('clientDataJSON', arrayBufferToBase64(credential.response.clientDataJSON));
            registerData.append('attestationObject', arrayBufferToBase64(credential.response.attestationObject));

            const verifyRes = await fetch('modules/auth/webauthn_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: registerData.toString()
            });
            
            const verifyData = await verifyRes.json();
            if (verifyData.success) {
                alert('¡Dispositivo registrado exitosamente! La próxima vez podrás ingresar con tu huella.');
            } else {
                alert(verifyData.error || 'Fallo el registro biométrico.');
            }
        } catch (e) {
            console.error('Error biométrico:', e);
            if (e.name === 'NotAllowedError') {
                // El usuario canceló
            } else if (e.name === 'NotReadableError') {
                alert('Tu dispositivo no pudo completar el registro. Intenta:\n\n1. Reiniciar el navegador Chrome\n2. Verificar que tienes huella/PIN configurado en Ajustes\n3. Actualizar Google Play Services');
            } else {
                alert('Error al registrar huella: ' + e.message);
            }
        }
    }
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('activityChart').getContext('2d');
    
    // Gradient for chart
    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(79, 70, 229, 0.5)'); // var(--primary-color) approx
    gradient.addColorStop(1, 'rgba(79, 70, 229, 0)');

    const data = {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Registros',
            data: <?php echo json_encode($chart_data); ?>,
            borderColor: '#4f46e5',
            backgroundColor: gradient,
            borderWidth: 3,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#4f46e5',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: true,
            tension: 0.4
        }]
    };

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? '#334155' : '#e2e8f0';

    new Chart(ctx, {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark ? '#1e293b' : '#fff',
                    titleColor: isDark ? '#f1f5f9' : '#0f172a',
                    bodyColor: isDark ? '#cbd5e1' : '#475569',
                    borderColor: gridColor,
                    borderWidth: 1,
                    padding: 10,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, color: textColor },
                    grid: { color: gridColor, drawBorder: false }
                },
                x: {
                    ticks: { color: textColor },
                    grid: { display: false, drawBorder: false }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
