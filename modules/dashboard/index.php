<?php
// modules/dashboard/index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// 1. Fetch Layout Order
$layout_order = [];
try {
    $stmt_layout = $db->prepare("SELECT dashboard_layout FROM users WHERE id = ?");
    if ($stmt_layout) {
        $stmt_layout->execute([$_SESSION['user_id']]);
        $saved_layout_json = $stmt_layout->fetchColumn();
        $layout_order = $saved_layout_json ? json_decode($saved_layout_json, true) : [];
    }
} catch(Exception $e) {}

// 2. Fetch Next Meeting
$next_meet = null;
if (has_perm('reuniones')) {
    try {
        $stmt_next_meet = $db->prepare("SELECT r.*, b.name as brand_name FROM reuniones r LEFT JOIN client_brands b ON r.brand_id = b.id WHERE r.fecha_hora > NOW() AND r.estado = 'Programada' ORDER BY r.fecha_hora ASC LIMIT 1");
        $stmt_next_meet->execute();
        $next_meet = $stmt_next_meet->fetch(PDO::FETCH_ASSOC);
    } catch(Exception $e) {}
}

// 3. Dynamic Stats
$stats = [];
if (has_perm('quotes')) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM quotes");
        $stats[] = ['icon' => 'ph-file-text', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.1)', 'value' => $stmt->fetchColumn(), 'label' => 'Cotizaciones', 'link' => 'index.php?module=quotes&action=index'];
    } catch (Exception $e) {}
}
if (has_perm('project_board')) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM project_months");
        $stats[] = ['icon' => 'ph-kanban', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.1)', 'value' => $stmt->fetchColumn(), 'label' => 'Proyectos Activos', 'link' => 'index.php?module=projects&action=index'];
    } catch (Exception $e) {}
}
if (has_perm('month_board')) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM month_posts");
        $stats[] = ['icon' => 'ph-calendar-check', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.1)', 'value' => $stmt->fetchColumn(), 'label' => 'Tareas del Mes', 'link' => 'index.php?module=tasks&action=index'];
    } catch (Exception $e) {}
}
if (has_perm('reuniones')) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM reuniones WHERE fecha_hora > NOW() AND estado = 'Programada'");
        $stats[] = ['icon' => 'ph-video-camera', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)', 'value' => $stmt->fetchColumn(), 'label' => 'Reuniones Futuras', 'link' => 'index.php?module=reuniones&action=index'];
    } catch (Exception $e) {}
}

// 4. Chart Data
$chart_labels = [];
$chart_data = [];
$chart_title = "Actividad General";

if (has_perm('quotes')) {
    $chart_title = "Cotizaciones Emitidas (Últimos 14 días)";
    try {
        $stmt = $db->query("SELECT DATE(created_at) as date, COUNT(*) as count FROM quotes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY date ASC");
        $temp_data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $temp_data[$row['date']] = $row['count']; }
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
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $temp_data[$row['date']] = $row['count']; }
        for($i=13; $i>=0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $chart_labels[] = date('d/m', strtotime($d));
            $chart_data[] = $temp_data[$d] ?? 0;
        }
    } catch(Exception $e){}
}

// 5. Active Projects Calendar Progress
$active_projects = [];
try {
    $stmt_proj = $db->query("
        SELECT pm.id, p.id as project_id, w.brand_name as name, pm.month, pm.year, p.team_members
        FROM project_months pm 
        JOIN projects p ON pm.project_id = p.id 
        JOIN work_orders w ON p.work_order_id = w.id
        WHERE p.status = 'active' 
        ORDER BY pm.id DESC
    ");
    $projects_res = $stmt_proj ? $stmt_proj->fetchAll(PDO::FETCH_ASSOC) : [];
    $meses_nombres = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    $current_uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    $is_session_admin = (isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'admin');
    $can_see_all = $is_admin || $is_session_admin || has_perm('project_board') || has_perm('projects');

    $processed_projects = [];
    foreach($projects_res as $p) {
        // Solo mostrar un mes (el más reciente) por cada proyecto
        if (in_array($p['project_id'], $processed_projects)) {
            continue;
        }
        
        $mem = json_decode($p['team_members'], true) ?: [];
        $is_assigned = in_array((string)$current_uid, $mem) || in_array($current_uid, $mem);
        
        // Filter to only show projects the current user is assigned to
        if (!$is_assigned) {
            continue;
        }
        $processed_projects[] = $p['project_id'];
        $prog = ['total_posts' => 0, 'aprobado' => 0, 'borrador' => 0, 'revision' => 0, 'publicado' => 0];
        try {
            $stmt_prog = $db->prepare("SELECT 
                COUNT(mp.id) as total_posts,
                SUM(CASE WHEN mp.status = 'Aprobado' THEN 1 ELSE 0 END) as aprobado,
                SUM(CASE WHEN mp.status = 'Borrador' THEN 1 ELSE 0 END) as borrador,
                SUM(CASE WHEN mp.status LIKE '%Revisión%' THEN 1 ELSE 0 END) as revision,
                SUM(CASE WHEN mp.social_status = 'published' THEN 1 ELSE 0 END) as publicado
                FROM month_posts mp
                WHERE mp.month_id = ?");
            if ($stmt_prog) {
                $stmt_prog->execute([$p['id']]);
                $prog_fetch = $stmt_prog->fetch(PDO::FETCH_ASSOC);
                if ($prog_fetch) $prog = $prog_fetch;
            }
        } catch(Exception $e) {}

        $total_comments = 0;
        try {
            $stmt_comments = $db->prepare("SELECT COUNT(pc.id) FROM post_comments pc JOIN month_posts mp ON pc.post_id = mp.id WHERE mp.month_id = ?");
            if ($stmt_comments) {
                $stmt_comments->execute([$p['id']]);
                $total_comments = $stmt_comments->fetchColumn();
            }
        } catch(Exception $e) {}

            // Safely fetch board_count to prevent fatal error if whiteboard_folders doesn't exist
            $board_count = 0;
            try {
                $stmt_bc = $db->prepare("SELECT COUNT(*) FROM whiteboard_folders WHERE name = ?");
                if ($stmt_bc) {
                    $stmt_bc->execute([$p['name']]);
                    $board_count = $stmt_bc->fetchColumn();
                }
            } catch(Exception $e) {}

            $mes_nombre = isset($meses_nombres[$p['month'] - 1]) ? $meses_nombres[$p['month'] - 1] : 'Mes';
            $active_projects[] = [
                'id' => $p['id'],
                'project_id' => $p['project_id'],
                'name' => $p['name'] ? $p['name'] : 'Proyecto sin nombre',
                'month' => $mes_nombre . ' ' . $p['year'],
                'total' => (int)$prog['total_posts'],
                'aprobado' => (int)$prog['aprobado'],
                'borrador' => (int)$prog['borrador'],
                'revision' => (int)$prog['revision'],
                'publicado' => (int)$prog['publicado'],
                'comments' => (int)$total_comments,
                'board_count' => (int)$board_count,
                'progress' => ($prog['total_posts'] > 0) ? round((((int)$prog['aprobado'] + (int)$prog['publicado']) / $prog['total_posts']) * 100) : 0
            ];
        }
    } catch(Exception $e){}

// 6. Contextual greeting
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = 'Buenos días';
    $greeting_icon = '☀️';
} elseif ($hour >= 12 && $hour < 19) {
    $greeting = 'Buenas tardes';
    $greeting_icon = '🌤️';
} else {
    $greeting = 'Buenas noches';
    $greeting_icon = '🌙';
}
$first_name = htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]);

// 7. Summary counts for subtitle
$pending_meetings = 0;
$pending_tasks = 0;
try {
    if (has_perm('reuniones')) {
        $stmt_pm = $db->query("SELECT COUNT(*) FROM reuniones WHERE fecha_hora > NOW() AND estado = 'Programada'");
        $pending_meetings = $stmt_pm->fetchColumn();
    }
} catch(Exception $e) {}
try {
    if (has_perm('month_board')) {
        $stmt_pt = $db->query("SELECT COUNT(*) FROM month_posts WHERE status != 'Aprobado'");
        $pending_tasks = $stmt_pt->fetchColumn();
    }
} catch(Exception $e) {}

?>

<link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo filemtime('assets/css/dashboard.css'); ?>">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dashboard-page-wrapper">
    
    <!-- Hero Section -->
    <div class="dash-hero">
        <div class="dash-avatar-wrapper">
            <img src="<?php echo htmlspecialchars(isset($global_settings['favicon']) && $global_settings['favicon'] ? $global_settings['favicon'] : 'assets/img/icon-192x192.png'); ?>" alt="Logo" class="dash-hero-logo">
        </div>
        <h1 class="dash-hero-title">Hola, <?php echo $first_name; ?> <span class="wave">👋</span></h1>
        <?php
            $motivational_phrases = [
                "Cada nuevo día es una página en blanco. Escribe una gran historia hoy.",
                "El éxito es la suma de pequeños esfuerzos repetidos día tras día. ¡A brillar hoy!",
                "No cuentes los días, haz que los días cuenten. ¡Ve con todo!",
                "La mejor manera de predecir el futuro es creándolo hoy.",
                "Cree en ti mismo y en todo lo que eres. Hay algo dentro de ti que es invencible.",
                "Tu actitud determina tu altitud. ¡Vuela alto hoy!",
                "Empieza donde estás. Usa lo que tienes. Haz lo que puedas."
            ];
            $day_of_year = (int)date('z');
            $daily_phrase = $motivational_phrases[$day_of_year % count($motivational_phrases)];
        ?>
        <p class="dash-hero-motivational">"<?php echo $daily_phrase; ?>"</p>
    </div>

    <!-- Dashboard Grid -->
    <div class="dashboard-grid modern-grid" id="dashboardGrid">
        
        <!-- Widget 1: Biometric -->
        <div class="widget widget-biometric-premium modern-card" data-id="widget-biometric" style="animation-delay: 0s;">
            <div class="badge-beta-premium">BETA</div>
            <div class="bio-premium-bg"></div>
            <div class="bio-premium-content">
                <div class="bio-premium-header">
                    <div class="icon-circle bio-premium-icon"><i class="ph ph-fingerprint"></i></div>
                    <h3>Biometría</h3>
                </div>
                <div class="bio-premium-body">
                    <p>Ingresa seguro y rápido usando tu huella dactilar.</p>
                    <button onclick="registerBiometrics()" class="btn-glass-bio">
                        <span>Activar ahora</span> <i class="ph ph-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Widget 2: Asistencia -->
        <div class="widget widget-asistencia modern-card" data-id="widget-asistencia" style="animation-delay: 0.1s;">
            <div class="asistencia-header">
                <div class="asistencia-date" id="live-date">...</div>
                <div class="asistencia-status-pill" id="asistencia-status-text">
                    <span class="status-dot"></span>Cargando...
                </div>
            </div>
            <div class="asistencia-time" id="live-clock">--:--</div>
            <div class="asistencia-actions" id="asistencia-buttons"></div>
        </div>

        <!-- Widget 3: Reuniones -->
        <div class="widget widget-reuniones modern-card" data-id="widget-reuniones" style="animation-delay: 0.2s;">
            <div class="widget-header">
                <h3 class="widget-title">
                    <div class="icon-circle meet-icon"><i class="ph ph-video-camera"></i></div>
                    Próxima Reunión
                </h3>
            </div>
            <?php if ($next_meet): ?>
                <div class="meet-info modern-meet">
                    <h4 class="meet-brand"><?php echo htmlspecialchars($next_meet['brand_name']); ?></h4>
                    <p class="meet-motivo"><?php echo htmlspecialchars($next_meet['motivo']); ?></p>
                    <div id="meet-countdown" class="meet-countdown modern-countdown" data-time="<?php echo $next_meet['fecha_hora']; ?>">
                        <span class="countdown-value">--:--:--</span>
                    </div>
                </div>
                <?php if ($next_meet['meet_link']): ?>
                    <a href="<?php echo htmlspecialchars($next_meet['meet_link']); ?>" target="_blank" class="btn-modern-action btn-meet">
                        Unirse <i class="ph ph-arrow-right"></i>
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <div class="meet-empty modern-empty">
                    <div class="empty-icon-circle"><i class="ph ph-calendar-blank"></i></div>
                    <p>Sin reuniones hoy</p>
                    <a href="index.php?module=reuniones&action=index" class="btn-modern-action btn-outline-modern">
                        Agendar
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Widget 4: Mensajes -->
        <div class="widget widget-mensajes-premium modern-card" data-id="widget-mensajes" style="animation-delay: 0.3s;">
            <div class="msg-premium-bg"></div>
            <div class="msg-premium-content">
                <div class="msg-premium-header">
                    <div class="icon-circle msg-premium-icon"><i class="ph ph-chat-circle-dots"></i></div>
                    <h3>Mensajes</h3>
                </div>
                <div class="msg-premium-body">
                    <p>Comunícate en tiempo real con tu equipo de trabajo.</p>
                    <a href="index.php?module=mensajes&action=index" class="btn-glass-msg">
                        <span>Abrir Chat</span> <i class="ph ph-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
    </div> <!-- end modern-grid -->

    <?php if($is_admin): ?>
    <!-- Widget de Asistencia Admin (Full Width) -->
    <div class="admin-asistencia-section" style="animation-delay: 0.3s; margin-top: 2rem;">
        <div class="section-header">
            <h2>Estado de Personal (Hoy)</h2>
            <div class="quick-actions">
                <button class="btn-glass" onclick="AdminAsistencia.loadPermisos()"><i class="ph ph-bell-ringing"></i> Ver Permisos</button>
                <button class="btn-glass" onclick="AdminAsistencia.loadStatus()"><i class="ph ph-arrows-clockwise"></i> Actualizar</button>
            </div>
        </div>
        
        <div class="card" style="background: var(--bg-surface); padding: 1rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
            <div class="table-responsive">
                <table class="table" style="margin: 0; min-width: 100%;">
                    <thead>
                        <tr>
                            <th>EMPLEADO</th>
                            <th>ESTADO</th>
                            <th>ENTRADA</th>
                            <th>REFRIGERIO (I/F)</th>
                            <th>SALIDA</th>
                        </tr>
                    </thead>
                    <tbody id="admin-asistencia-tbody">
                        <tr><td colspan="5" style="text-align: center;">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <!-- Widget 4: Proyectos (Full Width) -->
    <div class="projects-full-section" style="animation-delay: 0.3s;">
        <div class="section-header">
            <h2>Proyectos Asignados</h2>
        </div>
        
        <div class="projects-grid">
            <?php if(count($active_projects) > 0): ?>
                <?php foreach($active_projects as $idx => $ap): ?>
                <a href="index.php?module=project_board&id=<?php echo $ap['project_id']; ?>" class="modern-proj-card">
                    <div class="proj-card-top">
                        <div class="proj-brand-info">
                            <div class="proj-avatar"><?php echo strtoupper(substr($ap['name'], 0, 1)); ?></div>
                            <div>
                                <h4><?php echo htmlspecialchars($ap['name']); ?></h4>
                                <span class="proj-date"><?php echo $ap['month']; ?></span>
                            </div>
                        </div>
                        <?php if($ap['progress'] < 100): ?>
                            <span class="badge-status badge-pending">En curso</span>
                        <?php else: ?>
                            <span class="badge-status badge-completed">Completado</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="proj-stats-modern">
                        <div class="stat-item"><i class="ph ph-files"></i> <?php echo $ap['total']; ?> posts</div>
                        <div class="stat-item"><i class="ph ph-chat-circle"></i> <?php echo $ap['comments']; ?> msgs</div>
                        <?php if(isset($ap['board_count']) && $ap['board_count'] > 0): ?>
                        <div class="stat-item"><i class="ph ph-chalkboard"></i> <?php echo $ap['board_count']; ?> pizar.</div>
                        <?php endif; ?>
                    </div>

                    <div class="prog-container-modern">
                        <div class="prog-labels">
                            <span>Avance</span>
                            <span><?php echo $ap['progress']; ?>%</span>
                        </div>
                        <div class="prog-bar-wrapper">
                            <?php if($ap['total'] > 0): ?>
                                <?php 
                                    $pubW = round(($ap['publicado'] / $ap['total']) * 100, 1);
                                    $aprW = round(($ap['aprobado'] / $ap['total']) * 100, 1);
                                    $revW = round(($ap['revision'] / $ap['total']) * 100, 1);
                                    $borW = round(($ap['borrador'] / $ap['total']) * 100, 1);
                                ?>
                                <?php if($pubW > 0): ?><div class="bar-seg publicado" style="width: <?php echo $pubW; ?>%;"></div><?php endif; ?>
                                <?php if($aprW > 0): ?><div class="bar-seg aprobado" style="width: <?php echo $aprW; ?>%;"></div><?php endif; ?>
                                <?php if($revW > 0): ?><div class="bar-seg revision" style="width: <?php echo $revW; ?>%;"></div><?php endif; ?>
                                <?php if($borW > 0): ?><div class="bar-seg borrador" style="width: <?php echo $borW; ?>%;"></div><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="modern-empty-state">
                    <div class="empty-icon-circle"><i class="ph ph-folder-open"></i></div>
                    <p>No tienes proyectos asignados activos.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div> <!-- end dashboard-page-wrapper -->

<!-- Modal Solicitud Permiso -->
<div class="modal-overlay" id="modal-permiso">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ph ph-hand-palm"></i> Solicitar Permiso</h3>
            <button type="button" class="btn-close-circular" onclick="document.getElementById('modal-permiso').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-permiso" onsubmit="event.preventDefault(); AsistenciaWidget.submitPermiso();">
                <div class="form-group mb-3">
                    <label class="form-label">Motivo</label>
                    <select name="motivo" class="form-control" required>
                        <option value="">Selecciona un motivo</option>
                        <option value="Salud">Salud</option>
                        <option value="Personal">Personal</option>
                        <option value="Familiar">Familiar</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Descripción del Motivo</label>
                    <textarea name="descripcion" class="form-control" rows="3" required></textarea>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Adjuntar Imágenes (Opcional)</label>
                    <input type="file" name="imagenes[]" class="form-control" accept="image/*" multiple>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="ph ph-paper-plane-right"></i> Enviar Solicitud</button>
            </form>
        </div>
    </div>
</div>

<?php if($is_admin): ?>
<!-- Modal Visor de Permisos -->
<div class="modal-overlay" id="modal-visor-permisos">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ph ph-files"></i> Solicitudes de Permiso</h3>
            <button type="button" class="btn-close-circular" onclick="document.getElementById('modal-visor-permisos').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="overflow-y: auto; flex: 1;">
            <div id="visor-permisos-list" style="display: flex; flex-direction: column; gap: 1rem;">
                <!-- Permisos generados via JS -->
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<script>
    // --- SortableJS Initialization ---
    document.addEventListener('DOMContentLoaded', function() {
        const grid = document.getElementById('dashboardGrid');
        
        // Reorder based on saved layout from PHP
        const savedLayout = <?php echo json_encode($layout_order); ?>;
        if (savedLayout && savedLayout.length > 0) {
            const widgets = Array.from(grid.children);
            savedLayout.forEach(id => {
                const widget = widgets.find(w => w.getAttribute('data-id') === id);
                if (widget) grid.appendChild(widget);
            });
        }

        new Sortable(grid, {
            animation: 350,
            easing: "cubic-bezier(1, 0, 0, 1)",
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            forceFallback: true,
            fallbackClass: 'sortable-drag',
            onEnd: function() {
                const newOrder = Array.from(grid.children).map(w => w.getAttribute('data-id'));
                fetch('ajax/ajax_save_dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'layout=' + encodeURIComponent(JSON.stringify(newOrder))
                });
            }
        });
    });

    // --- Live Clock for Asistencia ---
    function updateClock() {
        const now = new Date();
        const dias = ['DOMINGO','LUNES','MARTES','MIÉRCOLES','JUEVES','VIERNES','SÁBADO'];
        const meses = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
        
        document.getElementById('live-date').innerText = `${dias[now.getDay()]}, ${now.getDate()} DE ${meses[now.getMonth()]}`;
        
        let h = now.getHours();
        let m = now.getMinutes();
        let s = now.getSeconds();
        let ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12; h = h ? h : 12; 
        m = m < 10 ? '0'+m : m;
        s = s < 10 ? '0'+s : s;
        
        const clockEl = document.getElementById('live-clock');
        clockEl.innerHTML = `${h}:${m}:${s} <span class="ampm">${ampm}</span>`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // --- Asistencia Widget Logic ---
    const AsistenciaWidget = {
        loadStatus: function() {
            fetch('ajax/ajax_asistencia.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=status'
            })
            .then(r => r.json())
            .then(res => { if (res.success) this.renderButtons(res.data); });
        },
        renderButtons: function(data) {
            const container = document.getElementById('asistencia-buttons');
            const statusText = document.getElementById('asistencia-status-text');
            container.innerHTML = '';
            
            const btnPermiso = document.createElement('button');
            btnPermiso.className = 'btn-asistencia';
            btnPermiso.style.background = 'rgba(255, 255, 255, 0.2)';
            btnPermiso.style.marginRight = '0.5rem';
            btnPermiso.innerHTML = `<i class="ph ph-hand-palm"></i> Permiso`;
            btnPermiso.onclick = () => document.getElementById('modal-permiso').classList.add('active');
            container.appendChild(btnPermiso);
            
            const createBtn = (label, action, icon, isDanger=false) => {
                const btn = document.createElement('button');
                btn.className = 'btn-asistencia' + (isDanger ? ' btn-danger' : '');
                btn.innerHTML = `<i class="ph ${icon}"></i> ${label}`;
                btn.onclick = () => this.mark(action);
                container.appendChild(btn);
            };

            const formatTime = (dtStr) => dtStr ? dtStr.split(' ')[1].substring(0, 5) : '';

            if (!data) {
                statusText.innerHTML = `<span class="status-dot"></span> Esperando entrada`;
                createBtn('Marcar Entrada', 'entrada', 'ph-sign-in');
            } else if (data.salida) {
                statusText.innerHTML = `<span class="status-dot" style="background: #94a3b8; box-shadow: 0 0 8px rgba(148,163,184,0.4);"></span> Jornada Terminada`;
            } else if (!data.inicio_refrigerio) {
                statusText.innerHTML = `<span class="status-dot"></span> Trabajando · Inició ${formatTime(data.entrada)}`;
                createBtn('Refrigerio', 'inicio_refrigerio', 'ph-coffee');
                createBtn('Salida', 'salida', 'ph-sign-out', true);
            } else if (!data.fin_refrigerio) {
                statusText.innerHTML = `<span class="status-dot" style="background: #fbbf24; box-shadow: 0 0 8px rgba(251,191,36,0.4);"></span> En Refrigerio`;
                createBtn('Fin Refrigerio', 'fin_refrigerio', 'ph-play');
            } else {
                statusText.innerHTML = `<span class="status-dot"></span> Trabajando · Ref. terminado`;
                createBtn('Marcar Salida', 'salida', 'ph-sign-out', true);
            }
        },
        mark: function(action) {
            fetch('ajax/ajax_asistencia.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: `action=${action}` })
            .then(r => r.json())
            .then(res => { if(res.success) this.loadStatus(); else alert(res.message); })
            .catch(e => alert('Error de conexión'));
        },
        submitPermiso: function() {
            const form = document.getElementById('form-permiso');
            const formData = new FormData(form);
            formData.append('action', 'request_permiso');
            
            fetch('ajax/ajax_asistencia.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    alert('Permiso solicitado correctamente.');
                    document.getElementById('modal-permiso').classList.remove('active');
                    form.reset();
                } else {
                    alert(res.message);
                }
            })
            .catch(e => alert('Error de conexión'));
        }
    };
    document.addEventListener('DOMContentLoaded', () => AsistenciaWidget.loadStatus());

    <?php if($is_admin): ?>
    const AdminAsistencia = {
        loadStatus: function() {
            fetch('ajax/ajax_asistencia.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=admin_today_status'
            })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    const tbody = document.getElementById('admin-asistencia-tbody');
                    tbody.innerHTML = '';
                    if(res.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">No hay personal activo.</td></tr>';
                        return;
                    }
                    res.data.forEach(u => {
                        let estadoHtml = '';
                        if(u.salida) estadoHtml = '<span class="badge-status badge-completed">Salida</span>';
                        else if(u.fin_refrigerio) estadoHtml = '<span class="badge-status badge-pending">En Jornada (Ref. Terminado)</span>';
                        else if(u.inicio_refrigerio) estadoHtml = '<span class="badge-status" style="background: #f59e0b; color: white;">En Refrigerio</span>';
                        else if(u.entrada) estadoHtml = '<span class="badge-status badge-pending">En Jornada</span>';
                        else if(u.estado_permiso) {
                            let color = u.estado_permiso === 'Aprobado' ? '#10b981' : (u.estado_permiso === 'Rechazado' ? '#ef4444' : '#f59e0b');
                            estadoHtml = `<span class="badge-status" style="background: ${color}; color: white;">Permiso: ${u.estado_permiso}</span> <br><small>${u.motivo_permiso}</small>`;
                        }
                        else estadoHtml = '<span class="badge-status" style="background: var(--border-color); color: var(--text-muted);">Sin Asistencia</span>';

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td data-label="EMPLEADO"><b>${u.name}</b><br><small>${u.email}</small></td>
                            <td data-label="ESTADO">${estadoHtml}</td>
                            <td data-label="ENTRADA">${u.entrada ? u.entrada.split(' ')[1] : '-'}</td>
                            <td data-label="REFRIGERIO">${u.inicio_refrigerio ? u.inicio_refrigerio.split(' ')[1] : '-'} / ${u.fin_refrigerio ? u.fin_refrigerio.split(' ')[1] : '-'}</td>
                            <td data-label="SALIDA">${u.salida ? u.salida.split(' ')[1] : '-'}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
        },
        loadPermisos: function() {
            fetch('ajax/ajax_asistencia.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_permisos'
            })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    const list = document.getElementById('visor-permisos-list');
                    list.innerHTML = '';
                    if(res.data.length === 0) {
                        list.innerHTML = '<p style="text-align:center; color:var(--text-muted);">No hay permisos solicitados.</p>';
                    } else {
                        res.data.forEach(p => {
                            let imgs = '';
                            if(p.imagenes_json) {
                                try {
                                    const arr = JSON.parse(p.imagenes_json);
                                    if(arr.length > 0) {
                                        imgs = '<div style="display:flex; gap:0.5rem; margin-top:0.5rem; overflow-x:auto;">' + arr.map(src => `<a href="${src}" target="_blank"><img src="${src}" style="height: 80px; border-radius: 8px; border: 1px solid var(--border-color); object-fit: cover;"></a>`).join('') + '</div>';
                                    }
                                } catch(e){}
                            }
                            
                            let estadoBadge = '';
                            if (p.estado === 'Aprobado') estadoBadge = '<span style="color: #10b981; font-weight: bold;">Aprobado</span>';
                            else if (p.estado === 'Rechazado') estadoBadge = '<span style="color: #ef4444; font-weight: bold;">Rechazado</span>';
                            else estadoBadge = '<span style="color: #f59e0b; font-weight: bold;">Pendiente</span>';

                            let actions = p.estado === 'Pendiente' ? `
                                <div style="display:flex; gap:0.5rem; margin-top: 1rem;">
                                    <input type="text" id="resp_${p.id}" class="form-control" placeholder="Respuesta del jefe (Opcional)" style="flex:1; padding: 0.3rem 0.5rem;">
                                    <button class="btn btn-primary" onclick="AdminAsistencia.updatePermiso(${p.id}, 'Aprobado')">Aprobar</button>
                                    <button class="btn btn-outline" style="color: #ef4444; border-color: #ef4444;" onclick="AdminAsistencia.updatePermiso(${p.id}, 'Rechazado')">Rechazar</button>
                                </div>
                            ` : `<p style="margin-top: 0.5rem; color: var(--text-muted); font-size: 0.85rem;">Respuesta: ${p.respuesta_jefe || 'Sin respuesta'}</p>`;

                            const item = document.createElement('div');
                            item.style.cssText = 'background: var(--bg-body); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color);';
                            item.innerHTML = `
                                <div style="display:flex; justify-content:space-between; margin-bottom: 0.5rem;">
                                    <strong>${p.user_name}</strong>
                                    <span>${estadoBadge}</span>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">${p.created_at} | Motivo: <b>${p.motivo}</b></div>
                                <p style="margin:0; font-size: 0.95rem;">${p.descripcion}</p>
                                ${imgs}
                                ${actions}
                            `;
                            list.appendChild(item);
                        });
                    }
                    document.getElementById('modal-visor-permisos').classList.add('active');
                }
            });
        },
        updatePermiso: function(id, estado) {
            const resp = document.getElementById('resp_' + id).value;
            const fd = new FormData();
            fd.append('action', 'update_permiso_status');
            fd.append('permiso_id', id);
            fd.append('estado', estado);
            fd.append('respuesta', resp);
            
            fetch('ajax/ajax_asistencia.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    this.loadPermisos();
                } else {
                    alert(res.message);
                }
            });
        }
    };
    
    document.addEventListener('DOMContentLoaded', () => {
        AdminAsistencia.loadStatus();
        setInterval(() => AdminAsistencia.loadStatus(), 60000); // refresh every minute
    });
    <?php endif; ?>

    // --- Meeting Countdown ---
    const meetEl = document.getElementById('meet-countdown');
    if(meetEl) {
        const meetTime = new Date(meetEl.dataset.time).getTime();
        const countdownVal = meetEl.querySelector('.countdown-value');
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = meetTime - now;
            if (distance < 0) { 
                if (countdownVal) countdownVal.innerHTML = "¡EN CURSO!";
                else meetEl.innerHTML = `<i class="ph ph-timer" style="font-size: 1.1rem; opacity: 0.7;"></i> ¡EN CURSO!`;
                return; 
            }
            const d = Math.floor(distance / (1000 * 60 * 60 * 24));
            const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((distance % (1000 * 60)) / 1000);
            const text = (d>0?d+"d ":"") + (h<10?"0":"")+h + ":" + (m<10?"0":"")+m + ":" + (s<10?"0":"")+s;
            if (countdownVal) countdownVal.textContent = text;
            else meetEl.textContent = text;
        }
        updateCountdown();
        setInterval(updateCountdown, 1000);
    }

    // --- CountUp Animation for Stats ---
    document.addEventListener('DOMContentLoaded', function() {
        const counters = document.querySelectorAll('.mini-stat h4[data-count]');
        
        const animateCount = (el) => {
            const target = parseInt(el.dataset.count);
            if (isNaN(target)) { el.textContent = '0'; return; }
            
            const duration = 1200;
            const startTime = performance.now();
            const formatter = new Intl.NumberFormat();
            
            function step(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                // Ease-out cubic
                const easedProgress = 1 - Math.pow(1 - progress, 3);
                const current = Math.round(easedProgress * target);
                el.textContent = formatter.format(current);
                
                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            }
            requestAnimationFrame(step);
        };

        // Use Intersection Observer to trigger animation when visible
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCount(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            counters.forEach(counter => observer.observe(counter));
        } else {
            counters.forEach(counter => animateCount(counter));
        }
    });

    // --- Slider Dots Navigation ---
    document.addEventListener('DOMContentLoaded', function() {
        const slider = document.getElementById('projectSlider');
        const dots = document.querySelectorAll('#sliderDots .slider-dot');
        
        if (slider && dots.length > 0) {
            // Click on dot -> scroll to project
            dots.forEach(dot => {
                dot.addEventListener('click', function() {
                    const idx = parseInt(this.dataset.index);
                    const items = slider.querySelectorAll('.ios-slider-item');
                    if (items[idx]) {
                        items[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    }
                });
            });

            // Update active dot on scroll
            let scrollTimeout;
            slider.addEventListener('scroll', function() {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    const items = slider.querySelectorAll('.ios-slider-item');
                    const sliderRect = slider.getBoundingClientRect();
                    const sliderCenter = sliderRect.left + sliderRect.width / 2;
                    
                    let closestIdx = 0;
                    let closestDist = Infinity;
                    
                    items.forEach((item, i) => {
                        const itemRect = item.getBoundingClientRect();
                        const itemCenter = itemRect.left + itemRect.width / 2;
                        const dist = Math.abs(itemCenter - sliderCenter);
                        if (dist < closestDist) {
                            closestDist = dist;
                            closestIdx = i;
                        }
                    });
                    
                    dots.forEach((d, i) => {
                        d.classList.toggle('active', i === closestIdx);
                    });
                }, 50);
            });
        }
    });

    // --- Activity Chart ---
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('activityChart');
        if(!ctx) return;
        const chartCtx = ctx.getContext('2d');
        
        // Create gradient
        let gradient = chartCtx.createLinearGradient(0, 0, 0, 300);
        
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const textColor = isDark ? '#94a3b8' : '#94a3b8';
        const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.04)';
        
        const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#4f46e5';
        
        gradient.addColorStop(0, isDark ? 'rgba(99, 102, 241, 0.3)' : 'rgba(79, 70, 229, 0.15)');
        gradient.addColorStop(1, 'rgba(79, 70, 229, 0)');

        new Chart(chartCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Registros',
                    data: <?php echo json_encode($chart_data); ?>,
                    borderColor: primaryColor,
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    pointBackgroundColor: isDark ? '#1e1b4b' : '#fff',
                    pointBorderColor: primaryColor,
                    pointBorderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? '#1e293b' : '#0f172a',
                        titleColor: '#fff',
                        bodyColor: '#cbd5e1',
                        borderColor: isDark ? '#334155' : '#1e293b',
                        borderWidth: 1,
                        cornerRadius: 10,
                        padding: 12,
                        displayColors: false,
                        titleFont: { weight: '700', size: 13 },
                        bodyFont: { size: 12 }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        ticks: { color: textColor, precision: 0, font: { size: 11 } }, 
                        grid: { color: gridColor, drawBorder: false },
                        border: { display: false }
                    },
                    x: { 
                        ticks: { color: textColor, font: { size: 11 }, maxRotation: 0 }, 
                        grid: { display: false, drawBorder: false },
                        border: { display: false }
                    }
                }
            }
        });
    });

    // --- WebAuthn ---
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
            const credential = await navigator.credentials.create(args);

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
            console.error(e);
            if (e.name !== 'NotAllowedError') {
                alert('Error al registrar huella: ' + e.message);
            }
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
