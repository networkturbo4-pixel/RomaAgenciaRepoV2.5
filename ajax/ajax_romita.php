<?php
// ajax/ajax_romita.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/GoogleDriveHelper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$db = (new Database())->getConnection();
$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

// Obtener rol y permisos del usuario en módulos
$stmt_user = $db->prepare("SELECT u.name, u.role_id, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt_user->execute([$user_id]);
$currentUser = $stmt_user->fetch(PDO::FETCH_ASSOC);
$user_name = $currentUser['name'] ?? ($_SESSION['user_name'] ?? 'Usuario');
$role_id = (int)($currentUser['role_id'] ?? 0);
$role_name = $currentUser['role_name'] ?? 'Colaborador';
$is_admin = ($role_id === 1 || strtolower($role_name) === 'administrador');

$user_permissions = [];
if ($is_admin) {
    $user_permissions = ['all'];
} else {
    $stmt_perms = $db->prepare("SELECT module_name FROM role_permissions WHERE role_id = ?");
    $stmt_perms->execute([$role_id]);
    $user_permissions = $stmt_perms->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (!in_array('dashboard', $user_permissions)) {
        $user_permissions[] = 'dashboard';
    }
}

function getProjectCalendarContext($db, $project_id) {
    if (!$project_id) return null;

    // 1. Obtener detalles del proyecto y orden de trabajo
    $stmt = $db->prepare("
        SELECT p.id as project_id, wo.brand_name, wo.correlativo, wo.data 
        FROM projects p 
        JOIN work_orders wo ON p.work_order_id = wo.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$project_id]);
    $proj = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proj) return null;

    $woData = json_decode($proj['data'], true) ?: [];
    $servicio = $woData['servicio'] ?? 'Marketing y Redes Sociales';
    $redes = $woData['redes'] ?? 'Instagram, Facebook';

    // 2. Meses trabajados en este proyecto
    $stmtMonths = $db->prepare("
        SELECT id, month, year, status, agenda_text 
        FROM project_months 
        WHERE project_id = ? 
        ORDER BY year DESC, month DESC
    ");
    $stmtMonths->execute([$project_id]);
    $months = $stmtMonths->fetchAll(PDO::FETCH_ASSOC);

    $monthNames = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    $monthsList = [];
    foreach ($months as $m) {
        $name = ($monthNames[$m['month']] ?? $m['month']) . ' ' . $m['year'];
        $monthsList[] = $name;
    }

    // 3. Publicaciones previas registradas
    $stmtPosts = $db->prepare("
        SELECT mp.concept, mp.copy_text, mp.platform, mp.post_type, mp.content_pillar, mp.post_date, pm.month, pm.year 
        FROM month_posts mp 
        JOIN project_months pm ON mp.month_id = pm.id 
        WHERE pm.project_id = ? 
        ORDER BY mp.post_date DESC 
        LIMIT 40
    ");
    $stmtPosts->execute([$project_id]);
    $posts = $stmtPosts->fetchAll(PDO::FETCH_ASSOC);

    $pillarsCount = [];
    $formatsCount = [];
    $conceptsList = [];

    foreach ($posts as $p) {
        if (!empty($p['content_pillar'])) {
            $pillarsCount[$p['content_pillar']] = ($pillarsCount[$p['content_pillar']] ?? 0) + 1;
        }
        if (!empty($p['post_type'])) {
            $formatsCount[$p['post_type']] = ($formatsCount[$p['post_type']] ?? 0) + 1;
        }
        if (!empty($p['concept']) && trim($p['concept']) !== '.') {
            $mName = $monthNames[$p['month']] ?? $p['month'];
            $pilar = !empty($p['content_pillar']) ? " [{$p['content_pillar']}]" : '';
            $formato = !empty($p['post_type']) ? " ({$p['post_type']})" : '';
            $conceptsList[] = "• [{$mName} {$p['year']}]{$pilar}{$formato}: \"{$p['concept']}\"";
        }
    }

    $pillarsStr = !empty($pillarsCount) ? implode(', ', array_map(function($k, $v) { return "$k: $v"; }, array_keys($pillarsCount), $pillarsCount)) : 'General';
    $formatsStr = !empty($formatsCount) ? implode(', ', array_map(function($k, $v) { return "$k: $v"; }, array_keys($formatsCount), $formatsCount)) : 'Variados';

    $ctx = "=== BASE DE CONOCIMIENTO HISTÓRICA DEL CALENDARIO: MARCA '{$proj['brand_name']}' ===\n";
    $ctx .= "Proyecto ID: #{$proj['project_id']} ({$proj['correlativo']}) | Servicio: {$servicio}\n";
    $ctx .= "Redes activas del cliente: {$redes}\n";
    $ctx .= "Meses trabajados en la plataforma: " . (empty($monthsList) ? "Ninguno aún" : implode(', ', $monthsList)) . "\n";
    $ctx .= "Total de publicaciones analizadas: " . count($posts) . "\n";
    $ctx .= "Distribución histórica de pilares de contenido: {$pillarsStr}\n";
    $ctx .= "Formatos frecuentes utilizados: {$formatsStr}\n\n";

    if (!empty($conceptsList)) {
        $ctx .= "HISTORIAL DE TEMAS Y CONCEPTOS YA PUBLICADOS (REGLA CRÍTICA: NO REPETIR ESTAS IDEAS DE CONTENIDO):\n";
        $ctx .= implode("\n", array_slice($conceptsList, 0, 30)) . "\n\n";
    }

    $ctx .= "DIRECTRICES PARA ROMITA:\n";
    $ctx .= "1. Conduce la conversación como el estratega senior de contenido y social media manager de '{$proj['brand_name']}'.\n";
    $ctx .= "2. Conoce a la perfección los pilares y temas de la marca. Si te preguntan cómo se maneja la marca o qué se ha hecho, explica los pilares y formatos usados según los datos anteriores.\n";
    $ctx .= "3. Si el usuario te pide un nuevo mes o plan de contenido:\n";
    $ctx .= "   - Propón ideas frescas basadas en los pilares pero SIN repetir conceptos ya publicados.\n";
    $ctx .= "   - Presenta la propuesta en una tabla visual ordenada: Fecha, Concepto, Pilar, Formato, Red Social, Idea de Copy y Brief visual.\n";
    $ctx .= "   - Al final de tu mensaje, si propones un plan estructurado, incluye SIEMPRE el siguiente bloque especial de datos para que la plataforma permita crearlo en el calendario con 1 solo clic:\n";
    $ctx .= "```json:calendar_plan\n";
    $ctx .= "{\n";
    $ctx .= "  \"project_id\": {$proj['project_id']},\n";
    $ctx .= "  \"month\": [numero_mes_1_al_12],\n";
    $ctx .= "  \"year\": [año_actual_o_proximo],\n";
    $ctx .= "  \"posts\": [\n";
    $ctx .= "    {\n";
    $ctx .= "      \"date\": \"YYYY-MM-DD\",\n";
    $ctx .= "      \"concept\": \"Título conciso del post\",\n";
    $ctx .= "      \"copy\": \"Texto persuasivo completo con gancho, desarrollo y llamado a la acción\",\n";
    $ctx .= "      \"platform\": \"Instagram, Facebook\",\n";
    $ctx .= "      \"post_type\": \"Reel\",\n";
    $ctx .= "      \"content_pillar\": \"Educación\",\n";
    $ctx .= "      \"design_brief\": \"Pautas visuales y recursos requeridos\"\n";
    $ctx .= "    }\n";
    $ctx .= "  ]\n";
    $ctx .= "}\n";
    $ctx .= "```\n";

    return [
        'context_text' => $ctx,
        'project_id' => $proj['project_id'],
        'brand_name' => $proj['brand_name'],
        'correlativo' => $proj['correlativo'],
        'total_months' => count($months),
        'total_posts' => count($posts),
        'months_list' => $monthsList,
        'pillars' => $pillarsCount
    ];
}

function getAgencyIntelligenceContext($db) {
    try {
        $curMonth = date('Y-m');
        // Ingresos del mes
        $stmtInc = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total, COUNT(*) as count FROM finance_incomes WHERE DATE_FORMAT(fecha_pago, '%Y-%m') = ?");
        $stmtInc->execute([$curMonth]);
        $incData = $stmtInc->fetch(PDO::FETCH_ASSOC);

        // Cobros pendientes del mes
        $stmtPend = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total_pend, COUNT(*) as count_pend FROM finance_incomes WHERE LOWER(estado) = 'pendiente' AND DATE_FORMAT(fecha_pago, '%Y-%m') = ?");
        $stmtPend->execute([$curMonth]);
        $pendData = $stmtPend->fetch(PDO::FETCH_ASSOC);

        // Gastos del mes
        $stmtExp = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total, COUNT(*) as count FROM finance_expenses WHERE DATE_FORMAT(fecha, '%Y-%m') = ?");
        $stmtExp->execute([$curMonth]);
        $expData = $stmtExp->fetch(PDO::FETCH_ASSOC);

        // Proyectos activos
        $stmtProj = $db->query("SELECT COUNT(*) FROM projects WHERE status = 'active'");
        $activeProjects = (int)$stmtProj->fetchColumn();

        // Resumen de cotizaciones
        $stmtQuotes = $db->query("SELECT status, COUNT(*) as qty, COALESCE(SUM(total), 0) as total_amount FROM quotes GROUP BY status");
        $quotesSummary = $stmtQuotes ? $stmtQuotes->fetchAll(PDO::FETCH_ASSOC) : [];

        $profit = (float)$incData['total'] - (float)$expData['total'];

        $summary = "=== INTELIGENCIA FINANCIERA Y OPERATIVA EN TIEMPO REAL (ROMA AGENCIA - PERIODO {$curMonth}) ===\n";
        $summary .= "- Proyectos activos en producción: {$activeProjects}\n";
        $summary .= "- Ingresos registrados este mes: S/ " . number_format((float)$incData['total'], 2) . " ({$incData['count']} pagos registrados)\n";
        $summary .= "- Gastos operativos este mes: S/ " . number_format((float)$expData['total'], 2) . " ({$expData['count']} gastos)\n";
        $summary .= "- Utilidad neta operativa estimada: S/ " . number_format($profit, 2) . "\n";
        $summary .= "- Cuentas por cobrar pendientes este mes: S/ " . number_format((float)$pendData['total_pend'], 2) . " ({$pendData['count_pend']} cobros pendientes)\n";
        
        if (!empty($quotesSummary)) {
            $summary .= "- Estado comercial de Cotizaciones:\n";
            foreach ($quotesSummary as $qs) {
                $summary .= "  • {$qs['status']}: {$qs['qty']} cotizaciones (Total acumulado: $" . number_format((float)$qs['total_amount'], 2) . ")\n";
            }
        }
        $summary .= "\nREGLA: Utiliza estos datos verídicos del sistema cuando el usuario te pregunte por las finanzas, rentabilidad, cobros pendientes, cotizaciones ganadas o la marcha operativa de Roma Agencia.";
        return $summary;
    } catch (Exception $e) {
        return null;
    }
}

function getAgencyFullEcosystemContext($db, $current_module = '', $entity_id = 0, $role_name = 'Colaborador', $is_admin = false, $user_permissions = []) {
    $context = "=== INTELIGENCIA 360° Y OMNISCIENCIA DE TODOS LOS MÓDULOS - ROMA AGENCIA ===\n";

    $canAccess = function($mod) use ($is_admin, $user_permissions) {
        if ($is_admin) return true;
        if (in_array('all', $user_permissions)) return true;
        return in_array($mod, $user_permissions);
    };

    // 1. Contexto de pantalla en tiempo real (Conciencia situacional)
    if (!empty($current_module)) {
        $context .= "UBICACIÓN ACTUAL DEL USUARIO EN LA PLATAFORMA:\n";
        $context .= "- Módulo en pantalla: " . strtoupper($current_module) . "\n";

        if ($entity_id > 0) {
            $context .= "- Identificador del recurso activo: #{$entity_id}\n";
            try {
                if ($current_module === 'clients' && $canAccess('clients')) {
                    $stmtC = $db->prepare("SELECT c.*, GROUP_CONCAT(b.name SEPARATOR ', ') as brands FROM clients c LEFT JOIN client_brands b ON b.client_id = c.id WHERE c.id = ? GROUP BY c.id");
                    $stmtC->execute([$entity_id]);
                    $cl = $stmtC->fetch(PDO::FETCH_ASSOC);
                    if ($cl) {
                        $context .= "  • Cliente en Pantalla: '{$cl['name']}' | WhatsApp: '{$cl['whatsapp']}' | Email: '{$cl['email']}' | DNI/RUC: '{$cl['dni']}' | Portal: " . ($cl['portal_enabled'] ? 'Activado' : 'Desactivado') . "\n";
                        if (!empty($cl['brands'])) $context .= "  • Marcas asociadas: {$cl['brands']}\n";
                    }
                } elseif ($current_module === 'quotes' && $canAccess('quotes')) {
                    $stmtQ = $db->prepare("SELECT q.*, c.name as client_name FROM quotes q LEFT JOIN clients c ON q.client_id = c.id WHERE q.id = ?");
                    $stmtQ->execute([$entity_id]);
                    $qp = $stmtQ->fetch(PDO::FETCH_ASSOC);
                    if ($qp) {
                        $context .= "  • Cotización en Pantalla: #{$qp['id']} | Cliente: '{$qp['client_name']}' | Total: {$qp['currency']} {$qp['total']} | Estado: {$qp['status']} | Vence: {$qp['due_date']}\n";
                    }
                } elseif ($current_module === 'work_orders' && $canAccess('work_orders')) {
                    $stmtW = $db->prepare("SELECT id, correlativo, brand_name, data, is_archived, created_at FROM work_orders WHERE id = ?");
                    $stmtW->execute([$entity_id]);
                    $wop = $stmtW->fetch(PDO::FETCH_ASSOC);
                    if ($wop) {
                        $woData = json_decode($wop['data'] ?? '', true) ?: [];
                        $cliente = $woData['cliente'] ?? '';
                        $context .= "  • Orden de Servicio en Pantalla: {$wop['correlativo']} | Marca: '{$wop['brand_name']}'" . ($cliente ? " | Cliente: '{$cliente}'" : "") . " | Fecha: {$wop['created_at']}\n";
                    }
                } elseif ($current_module === 'contracts' && $canAccess('contracts')) {
                    $stmtCt = $db->prepare("SELECT ct.*, c.name as client_name FROM contracts ct LEFT JOIN clients c ON ct.client_id = c.id WHERE ct.id = ?");
                    $stmtCt->execute([$entity_id]);
                    $ctp = $stmtCt->fetch(PDO::FETCH_ASSOC);
                    if ($ctp) {
                        $context .= "  • Contrato en Pantalla: '{$ctp['title']}' | Cliente: '{$ctp['client_name']}' | Estado: {$ctp['status']} | Monto: S/ {$ctp['total_amount']}\n";
                    }
                } elseif (($current_module === 'reuniones' || $current_module === 'agenda') && $canAccess('reuniones')) {
                    $stmtR = $db->prepare("SELECT r.*, b.name as brand_name FROM reuniones r LEFT JOIN client_brands b ON r.brand_id = b.id WHERE r.id = ?");
                    $stmtR->execute([$entity_id]);
                    $rp = $stmtR->fetch(PDO::FETCH_ASSOC);
                    if ($rp) {
                        $context .= "  • Reunión en Pantalla: '{$rp['motivo']}' | Marca: '{$rp['brand_name']}' | Fecha/Hora: {$rp['fecha_hora']} | Estado: {$rp['estado']}\n";
                        if (!empty($rp['meet_link'])) $context .= "  • Google Meet: {$rp['meet_link']}\n";
                    }
                } elseif (($current_module === 'task_manager' || $current_module === 'tasks') && ($canAccess('task_manager') || $canAccess('tasks'))) {
                    $stmtT = $db->prepare("SELECT * FROM tm_tasks WHERE id = ?");
                    $stmtT->execute([$entity_id]);
                    $tp = $stmtT->fetch(PDO::FETCH_ASSOC);
                    if ($tp) {
                        $context .= "  • Tarea en Pantalla: '{$tp['title']}' | Prioridad: {$tp['priority']} | Estado: {$tp['status']} | Área: {$tp['area']}" . ($tp['due_date'] ? " | Vence: {$tp['due_date']}" : "") . "\n";
                    }
                } elseif ($current_module === 'services' && $canAccess('services')) {
                    $stmtS = $db->prepare("SELECT id, name, price, currency, delivery_time, status, description FROM services WHERE id = ?");
                    $stmtS->execute([$entity_id]);
                    $sp = $stmtS->fetch(PDO::FETCH_ASSOC);
                    if ($sp) {
                        $context .= "  • Servicio en Pantalla: '{$sp['name']}' | Precio: {$sp['currency']} {$sp['price']} | Entrega: {$sp['delivery_time']} | Estado: {$sp['status']}\n";
                    }
                } elseif ($current_module === 'suppliers' && $canAccess('suppliers')) {
                    $stmtSup = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
                    $stmtSup->execute([$entity_id]);
                    $supp = $stmtSup->fetch(PDO::FETCH_ASSOC);
                    if ($supp) {
                        $context .= "  • Proveedor en Pantalla: '{$supp['name']}' | Categoría: {$supp['category']} | Contacto: '{$supp['contact_name']}' | Tel: {$supp['phone']}\n";
                    }
                } elseif ($current_module === 'desarrollo_marca' && $canAccess('desarrollo_marca')) {
                    $stmtB = $db->prepare("SELECT title, client_name, status, due_date, description FROM brand_projects WHERE id = ?");
                    $stmtB->execute([$entity_id]);
                    $bp = $stmtB->fetch(PDO::FETCH_ASSOC);
                    if ($bp) {
                        $context .= "  • Proyecto de Marca Activo: '{$bp['title']}' | Cliente: '{$bp['client_name']}' | Estado: {$bp['status']} | Entrega: {$bp['due_date']}\n";
                        if (!empty($bp['description'])) $context .= "  • Resumen/Briefing: {$bp['description']}\n";
                    }
                } elseif ($current_module === 'audiovisual' && $canAccess('audiovisual')) {
                    $stmtA = $db->prepare("SELECT title, client_name, status, due_date, description FROM audiovisual_projects WHERE id = ?");
                    $stmtA->execute([$entity_id]);
                    $ap = $stmtA->fetch(PDO::FETCH_ASSOC);
                    if ($ap) {
                        $context .= "  • Proyecto Audiovisual Activo: '{$ap['title']}' | Cliente: '{$ap['client_name']}' | Estado: {$ap['status']} | Entrega: {$ap['due_date']}\n";
                        if (!empty($ap['description'])) $context .= "  • Resumen/Guión/Pautas: {$ap['description']}\n";
                    }
                } elseif ($current_module === 'pizarras' && $canAccess('pizarras')) {
                    $stmtW = $db->prepare("SELECT w.title, f.name as folder_name, w.tags FROM whiteboards w LEFT JOIN whiteboard_folders f ON w.folder_id = f.id WHERE w.id = ?");
                    $stmtW->execute([$entity_id]);
                    $wp = $stmtW->fetch(PDO::FETCH_ASSOC);
                    if ($wp) {
                        $context .= "  • Pizarra Colaborativa Activa: '{$wp['title']}' | Carpeta: '{$wp['folder_name']}'\n";
                    }
                } elseif (in_array($current_module, ['month_board', 'calendar']) && ($canAccess('calendar') || $canAccess('month_board'))) {
                    $stmtM = $db->prepare("SELECT pm.month, pm.year, w.brand_name, w.correlativo FROM project_months pm JOIN projects p ON pm.project_id = p.id JOIN work_orders w ON p.work_order_id = w.id WHERE pm.id = ?");
                    $stmtM->execute([$entity_id]);
                    $mp = $stmtM->fetch(PDO::FETCH_ASSOC);
                    if ($mp) {
                        $context .= "  • Calendario de Contenidos Activo: Marca '{$mp['brand_name']}' ({$mp['correlativo']}) | Mes: {$mp['month']}/{$mp['year']}\n";
                    }
                } elseif (($current_module === 'projects' || $current_module === 'project_board') && ($canAccess('projects') || $canAccess('project_board'))) {
                    $stmtP = $db->prepare("SELECT p.id, w.brand_name, w.correlativo, w.data FROM projects p LEFT JOIN work_orders w ON p.work_order_id = w.id WHERE p.id = ?");
                    $stmtP->execute([$entity_id]);
                    $pp = $stmtP->fetch(PDO::FETCH_ASSOC);
                    if ($pp) {
                        $woData = json_decode($pp['data'] ?? '', true) ?: [];
                        $srv = $woData['servicio'] ?? 'Desarrollo / Web';
                        $context .= "  • Proyecto Activo: '{$pp['brand_name']}' ({$pp['correlativo']}) | Servicio: {$srv}\n";
                    }
                } elseif ($current_module === 'knowledge_base' && $canAccess('knowledge_base')) {
                    $stmtKb = $db->prepare("SELECT a.title, c.name as category_name FROM kb_articles a JOIN kb_categories c ON a.category_id = c.id WHERE a.id = ?");
                    $stmtKb->execute([$entity_id]);
                    $kbArt = $stmtKb->fetch(PDO::FETCH_ASSOC);
                    if ($kbArt) {
                        $context .= "  • Artículo de Conocimiento en Pantalla: '{$kbArt['title']}' (Categoría: {$kbArt['category_name']})\n";
                    }
                }
            } catch (Exception $e) {}
        }
        $context .= "\n";
    }

    // 2. Base de Datos Centralizada y Omnisciente (Filtrada por Permisos de Rol)
    try {
        // A. Clientes y Marcas
        if ($canAccess('clients')) {
            $stmtClients = $db->query("
                SELECT c.id, c.name, c.whatsapp, c.email, c.portal_enabled,
                       GROUP_CONCAT(b.name SEPARATOR ', ') as brands
                FROM clients c
                LEFT JOIN client_brands b ON b.client_id = c.id
                GROUP BY c.id
                ORDER BY c.id DESC
                LIMIT 25
            ");
            $clients = $stmtClients ? $stmtClients->fetchAll(PDO::FETCH_ASSOC) : [];
            $totalClients = (int)$db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
            $totalBrands = (int)$db->query("SELECT COUNT(*) FROM client_brands")->fetchColumn();

            $context .= "CLIENTES & MARCAS DE ROMA AGENCIA (Total registrados: {$totalClients} clientes, {$totalBrands} marcas):\n";
            foreach ($clients as $c) {
                $bList = !empty($c['brands']) ? " | Marcas: {$c['brands']}" : "";
                $phone = !empty($c['whatsapp']) ? " | Tel/WA: {$c['whatsapp']}" : "";
                $portal = $c['portal_enabled'] ? " [Portal Activo]" : "";
                $context .= "- #{$c['id']} {$c['name']}{$bList}{$phone}{$portal}\n";
            }
            $context .= "\n";
        }

        // B. Catálogo Oficial de Servicios (Tarifario real - Solo accesible si tiene permiso en 'services')
        if ($canAccess('services')) {
            $stmtServices = $db->query("
                SELECT id, name, price, currency, delivery_time 
                FROM services 
                WHERE status = 'active' 
                ORDER BY price DESC
            ");
            $services = $stmtServices ? $stmtServices->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($services)) {
                $context .= "CATÁLOGO OFICIAL DE SERVICIOS Y TARIFAS DE LA AGENCIA:\n";
                foreach ($services as $s) {
                    $cur = !empty($s['currency']) ? $s['currency'] : 'S/';
                    $time = !empty($s['delivery_time']) ? " (Tiempo: {$s['delivery_time']})" : "";
                    $context .= "- {$s['name']}: {$cur} " . number_format((float)$s['price'], 2) . "{$time}\n";
                }
                $context .= "\n";
            }
        }

        // C. Cotizaciones y Pipeline Comercial (Solo accesible si tiene permiso en 'quotes')
        if ($canAccess('quotes')) {
            $stmtQuotes = $db->query("
                SELECT q.id, q.status, q.total, q.currency, q.due_date, c.name as client_name
                FROM quotes q
                LEFT JOIN clients c ON q.client_id = c.id
                ORDER BY q.id DESC LIMIT 10
            ");
            $quotes = $stmtQuotes ? $stmtQuotes->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($quotes)) {
                $context .= "PIPELINE COMERCIAL Y COTIZACIONES RECIENTES:\n";
                foreach ($quotes as $q) {
                    $client = !empty($q['client_name']) ? " (Cliente: {$q['client_name']})" : "";
                    $context .= "- Cotización #{$q['id']}{$client}: {$q['currency']} " . number_format((float)$q['total'], 2) . " [{$q['status']}] - Vence: {$q['due_date']}\n";
                }
                $context .= "\n";
            }
        }

        // D. Órdenes de Servicio (OT)
        if ($canAccess('work_orders')) {
            $stmtWO = $db->query("
                SELECT id, correlativo, brand_name, created_at 
                FROM work_orders 
                WHERE (is_archived IS NULL OR is_archived = 0)
                ORDER BY id DESC LIMIT 10
            ");
            $wos = $stmtWO ? $stmtWO->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($wos)) {
                $context .= "ÓRDENES DE SERVICIO (OT) ACTIVAS:\n";
                foreach ($wos as $wo) {
                    $context .= "- {$wo['correlativo']} - Marca: '{$wo['brand_name']}' (Registrada: {$wo['created_at']})\n";
                }
                $context .= "\n";
            }
        }

        // E. Calendarios & Redes Sociales
        if ($canAccess('calendar') || $canAccess('month_board')) {
            $stmtCal = $db->query("
                SELECT p.id, COALESCE(NULLIF(wo.brand_name, ''), CONCAT('Proyecto #', p.id)) as brand_name,
                       (SELECT COUNT(*) FROM month_posts mp JOIN project_months pm ON mp.month_id = pm.id WHERE pm.project_id = p.id) as total_posts
                FROM projects p
                LEFT JOIN work_orders wo ON p.work_order_id = wo.id
                WHERE (wo.is_archived IS NULL OR wo.is_archived = 0)
                ORDER BY p.id DESC LIMIT 8
            ");
            $cals = $stmtCal ? $stmtCal->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($cals)) {
                $context .= "CALENDARIOS / REDES SOCIALES ACTIVAS:\n";
                foreach ($cals as $c) {
                    $context .= "- Marca '{$c['brand_name']}': {$c['total_posts']} publicaciones gestionadas\n";
                }
                $context .= "\n";
            }
        }

        // F. Desarrollo de Marca & Audiovisual & Pizarras
        if ($canAccess('desarrollo_marca')) {
            $stmtBrands = $db->query("SELECT id, title, client_name, status, due_date FROM brand_projects WHERE status IN ('Active', 'Pending') ORDER BY id DESC LIMIT 6");
            $brands = $stmtBrands ? $stmtBrands->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($brands)) {
                $context .= "PROYECTOS DE BRANDING & IDENTIDAD:\n";
                foreach ($brands as $b) {
                    $client = !empty($b['client_name']) ? " (Cliente: {$b['client_name']})" : "";
                    $due = !empty($b['due_date']) ? " - Entrega: {$b['due_date']}" : "";
                    $context .= "- #{$b['id']} '{$b['title']}'{$client} [{$b['status']}]{$due}\n";
                }
                $context .= "\n";
            }
        }

        if ($canAccess('audiovisual')) {
            $stmtAudio = $db->query("SELECT id, title, client_name, status, due_date FROM audiovisual_projects WHERE status IN ('Active', 'Pending') ORDER BY id DESC LIMIT 6");
            $audios = $stmtAudio ? $stmtAudio->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($audios)) {
                $context .= "PROYECTOS AUDIOVISUALES (VIDEOS / PRODUCCIÓN):\n";
                foreach ($audios as $a) {
                    $client = !empty($a['client_name']) ? " (Cliente: {$a['client_name']})" : "";
                    $due = !empty($a['due_date']) ? " - Entrega: {$a['due_date']}" : "";
                    $context .= "- #{$a['id']} '{$a['title']}'{$client} [{$a['status']}]{$due}\n";
                }
                $context .= "\n";
            }
        }

        if ($canAccess('pizarras')) {
            $stmtWhite = $db->query("SELECT w.id, w.title, f.name as folder_name FROM whiteboards w LEFT JOIN whiteboard_folders f ON w.folder_id = f.id ORDER BY w.id DESC LIMIT 6");
            $whites = $stmtWhite ? $stmtWhite->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($whites)) {
                $context .= "PIZARRAS COLABORATIVAS RECIENTES:\n";
                foreach ($whites as $w) {
                    $fName = !empty($w['folder_name']) ? " [Carpeta: {$w['folder_name']}]" : "";
                    $context .= "- Pizarra #{$w['id']}: '{$w['title']}'{$fName}\n";
                }
                $context .= "\n";
            }
        }

        // G. Agenda & Reuniones (Google Meet)
        if ($canAccess('reuniones')) {
            $stmtMeet = $db->query("
                SELECT r.id, r.motivo, r.fecha_hora, r.meet_link, r.estado, b.name as brand_name
                FROM reuniones r
                LEFT JOIN client_brands b ON r.brand_id = b.id
                ORDER BY r.fecha_hora DESC LIMIT 8
            ");
            $meets = $stmtMeet ? $stmtMeet->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($meets)) {
                $context .= "AGENDA DE REUNIONES & GOOGLE MEET:\n";
                foreach ($meets as $m) {
                    $bName = !empty($m['brand_name']) ? " | Marca: '{$m['brand_name']}'" : "";
                    $link = !empty($m['meet_link']) ? " | Meet: {$m['meet_link']}" : "";
                    $context .= "- {$m['fecha_hora']} - '{$m['motivo']}'{$bName} [Estado: {$m['estado']}]{$link}\n";
                }
                $context .= "\n";
            }
        }

        // H. Tareas & Objetivos Operativos del Equipo
        if ($canAccess('task_manager') || $canAccess('tasks')) {
            $stmtTasks = $db->query("
                SELECT id, title, priority, status, area, due_date
                FROM tm_tasks
                WHERE status IN ('new', 'pending', 'overdue')
                ORDER BY (CASE WHEN priority = 'urgent' THEN 1 WHEN priority = 'high' THEN 2 WHEN priority = 'medium' THEN 3 ELSE 4 END), id DESC
                LIMIT 8
            ");
            $tasks = $stmtTasks ? $stmtTasks->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($tasks)) {
                $context .= "TAREAS & OBJETIVOS OPERATIVOS EN CURSO:\n";
                foreach ($tasks as $t) {
                    $due = !empty($t['due_date']) ? " - Vence: {$t['due_date']}" : "";
                    $context .= "- [{$t['priority']}] '{$t['title']}' (Área: {$t['area']}, Estado: {$t['status']}){$due}\n";
                }
                $context .= "\n";
            }
        }

        // I. Equipo y Colaboradores de Roma Agencia
        $stmtUsers = $db->query("
            SELECT u.id, u.name, u.email, r.name as role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY u.id ASC
        ");
        $users = $stmtUsers ? $stmtUsers->fetchAll(PDO::FETCH_ASSOC) : [];
        if (!empty($users)) {
            $context .= "EQUIPO Y ROLES DE ROMA AGENCIA:\n";
            foreach ($users as $u) {
                $rName = !empty($u['role_name']) ? $u['role_name'] : 'Colaborador';
                $context .= "- {$u['name']} ({$rName})\n";
            }
            $context .= "\n";
        }

        // J. Proveedores y Aliados Estratégicos
        if ($canAccess('suppliers')) {
            $stmtSuppliers = $db->query("
                SELECT id, name, category, contact_name, phone
                FROM suppliers
                WHERE status = 'active'
                ORDER BY name ASC LIMIT 6
            ");
            $sups = $stmtSuppliers ? $stmtSuppliers->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($sups)) {
                $context .= "PROVEEDORES Y ALIADOS ESTRATÉGICOS:\n";
                foreach ($sups as $sp) {
                    $contact = !empty($sp['contact_name']) ? " (Contacto: {$sp['contact_name']})" : "";
                    $context .= "- {$sp['name']} [{$sp['category']}]{$contact}\n";
                }
                $context .= "\n";
            }
        }

        // K. Balance Financiero Ejecutivo en Tiempo Real (Solo si tiene permiso 'admin')
        if ($canAccess('admin')) {
            $curMonth = date('Y-m');
            $stmtInc = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM finance_incomes WHERE DATE_FORMAT(fecha_pago, '%Y-%m') = ?");
            $stmtInc->execute([$curMonth]);
            $incTotal = (float)$stmtInc->fetchColumn();

            $stmtExp = $db->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM finance_expenses WHERE DATE_FORMAT(fecha, '%Y-%m') = ?");
            $stmtExp->execute([$curMonth]);
            $expTotal = (float)$stmtExp->fetchColumn();

            $stmtPend = $db->query("SELECT COALESCE(SUM(monto), 0) FROM finance_incomes WHERE LOWER(estado) = 'pendiente'");
            $pendTotal = (float)$stmtPend->fetchColumn();

            $profit = $incTotal - $expTotal;

            $context .= "BALANCE FINANCIERO EJECUTIVO (Periodo {$curMonth}):\n";
            $context .= "- Ingresos del mes: S/ " . number_format($incTotal, 2) . "\n";
            $context .= "- Gastos operativos del mes: S/ " . number_format($expTotal, 2) . "\n";
            $context .= "- Utilidad operativa del mes: S/ " . number_format($profit, 2) . "\n";
            $context .= "- Cuentas por cobrar acumuladas pendientes: S/ " . number_format($pendTotal, 2) . "\n\n";
        }

    } catch (Exception $e) {}

    // L. Reglas de Confidencialidad y Control de Acceso Estricto
    $context .= "POLÍTICA DE CONFIDENCIALIDAD Y CONTROL DE ACCESO (USUARIO: '{$role_name}'):\n";
    if ($is_admin) {
        $context .= "1. El usuario tiene rol de Administrador con acceso ilimitado a toda la información estratégica, comercial, tarifaria y financiera de Roma Agencia.\n";
    } else {
        $context .= "1. El usuario actual tiene el rol de '{$role_name}'. Módulos autorizados en el sistema: " . implode(', ', $user_permissions) . ".\n";
        $context .= "2. REGLA CRÍTICA DE CONFIDENCIALIDAD Y PRECIOS: Este usuario NO tiene autorización para ver tarifas comerciales de servicios, cotizaciones de clientes, montos de contratos ni balances financieros internos de Roma Agencia.\n";
        $context .= "3. Si el usuario te consulta directa o indirectamente sobre precios ('cuánto cobramos', 'cuál es el precio de X servicio', 'cuánto cuesta', etc.), cotizaciones o finanzas, NUNCA reveles cifras ni inventes tarifas. Responde con calidez y diplomacia ejecutiva indicando que como '{$role_name}', su perfil está enfocado en el desarrollo operativo y creativo, y que la información tarifaria y comercial está reservada para el área de administración y gerencia de Roma Agencia, recomendándole consultar directamente con César o Administración.\n";
        $context .= "4. Enfoca siempre tus respuestas y asistencia en sus áreas de trabajo permitidas (contenidos, diseño, creatividad, calendarios, ideas, proyectos y tareas operativas).\n";
    }

    $context .= "5. NUNCA inventes clientes ficticios, reuniones falsas ni servicios inexistentes. Cíñete siempre a los registros verídicos autorizados.\n";
    $context .= "6. Si el usuario está situado en una pantalla específica ('UBICACIÓN ACTUAL DEL USUARIO'), contextualiza y prioriza el recurso activo que está viendo.";
    return $context;
}

/**
 * Conecta a Romita con la Base de Conocimiento de Roma Agencia (SOPs, Procedimientos, Guías).
 */
function getKnowledgeBaseContext($db, $userQuery = '', $currentModule = '', $entity_id = 0) {
    try {
        $stmtCats = $db->query("SELECT id, name, description FROM kb_categories WHERE is_active = 1 ORDER BY order_index ASC");
        $categories = $stmtCats ? $stmtCats->fetchAll(PDO::FETCH_ASSOC) : [];
        
        $stmtArts = $db->query("
            SELECT a.id, a.title, a.slug, a.summary, a.content, a.audience, a.video_url, c.name as category_name
            FROM kb_articles a
            JOIN kb_categories c ON a.category_id = c.id
            WHERE a.status = 'published'
            ORDER BY c.order_index ASC, a.created_at DESC
        ");
        $articles = $stmtArts ? $stmtArts->fetchAll(PDO::FETCH_ASSOC) : [];

        if (empty($articles) && empty($categories)) {
            return "";
        }

        $context = "=== BASE DE CONOCIMIENTO Y PROCEDIMIENTOS OPERATIVOS (ROMA AGENCIA) ===\n";
        $context .= "Tienes conexión directa y acceso en tiempo real a la Base de Conocimiento oficial de Roma Agencia (SOPs, manuales operativos, tutoriales, políticas internas y guías paso a paso).\n\n";

        if (!empty($categories)) {
            $context .= "CATEGORÍAS DE CONOCIMIENTO REGISTRADAS:\n";
            foreach ($categories as $cat) {
                $desc = !empty($cat['description']) ? trim($cat['description']) : 'Procedimientos y documentación del área.';
                $context .= "- {$cat['name']}: {$desc}\n";
            }
            $context .= "\n";
        }

        // Palabras clave de la consulta del usuario para búsqueda semántica / relevancia
        $cleanQuery = mb_strtolower(trim($userQuery));
        $queryWords = array_filter(explode(' ', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $cleanQuery)), function($w) {
            return mb_strlen($w) >= 3;
        });

        $context .= "ARTÍCULOS Y MANUALES OFICIALES DISPONIBLES:\n";

        foreach ($articles as $art) {
            $titleLower = mb_strtolower($art['title']);
            $catLower = mb_strtolower($art['category_name']);
            
            // Limpieza del contenido HTML a texto legible
            $cleanContent = strip_tags(str_replace(['</p>', '<br>', '<br/>', '</li>'], ["\n", "\n", "\n", "\n"], $art['content']));
            $cleanContent = preg_replace("/\n\s*\n+/", "\n", trim($cleanContent));
            $contentLower = mb_strtolower($cleanContent);

            // Verificar si el artículo es especialmente relevante para la pregunta actual
            $isRelevant = false;
            if (!empty($queryWords)) {
                foreach ($queryWords as $word) {
                    if (strpos($titleLower, $word) !== false || strpos($catLower, $word) !== false || strpos($contentLower, $word) !== false) {
                        $isRelevant = true;
                        break;
                    }
                }
            }

            $context .= "• [Artículo #{$art['id']}] \"{$art['title']}\" (Área: {$art['category_name']})\n";
            if (!empty($art['summary'])) {
                $context .= "  Resumen: " . trim($art['summary']) . "\n";
            }

            // Si hay pocos artículos (<= 20) o es relevante o estamos en el módulo kb, incluir los pasos detallados
            if (count($articles) <= 20 || $isRelevant || $currentModule === 'knowledge_base' || ($entity_id > 0 && $entity_id == $art['id'])) {
                $context .= "  Procedimiento Oficial Paso a Paso:\n";
                $lines = explode("\n", $cleanContent);
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if ($trimmed !== '') {
                        $context .= "    " . $trimmed . "\n";
                    }
                }
            }
            $context .= "\n";
        }

        $context .= "REGLAS DE APLICACIÓN CON LA BASE DE CONOCIMIENTO:\n";
        $context .= "1. Si el usuario te consulta sobre cómo realizar un proceso interno, otorgar accesos a plataformas/redes de la agencia, protocolos o manuales, básate fielmente en los pasos de estos artículos oficiales.\n";
        $context .= "2. Si el artículo oficial contiene correos específicos de la empresa (ej: romacomercial22@gmail.com) o indicaciones exactas, comunícaselos al usuario con total precisión.\n";
        $context .= "3. Si el usuario te pregunta por un proceso que aún no está documentado en la Base de Conocimiento, explícale la mejor práctica recomendada para la agencia y sugiérele registrar el procedimiento en la Base de Conocimiento (index.php?module=knowledge_base).\n";
        $context .= "4. Siempre mantén una postura ejecutiva, segura y alineada a los estándares de calidad de Roma Agencia.\n";

        return $context;
    } catch (Exception $e) {
        return "";
    }
}

try {
    if ($action === 'chat') {
        $message = $_POST['message'] ?? '';
        $skill_prompt = $_POST['skill_prompt'] ?? '';
        $specialty = $_POST['specialty'] ?? 'director_360';
        $current_module = $_POST['current_module'] ?? '';
        $entity_id = (int)($_POST['entity_id'] ?? 0);
        
        if(empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Mensaje vacío']);
            exit();
        }

        // Guarda mensaje del usuario
        $chat_id = $_POST['chat_id'] ?? null;
        
        if (!$chat_id) {
            // Genera título corto del mensaje
            $title = mb_substr($message, 0, 30) . (mb_strlen($message) > 30 ? '...' : '');
            $stmt = $db->prepare("INSERT INTO romita_chats (user_id, title) VALUES (?, ?)");
            $stmt->execute([$user_id, $title]);
            $chat_id = $db->lastInsertId();
        }

        // Insertar msj usuario
        $stmt_user_msg = $db->prepare("INSERT INTO romita_messages (chat_id, role, content) VALUES (?, 'user', ?)");
        $stmt_user_msg->execute([$chat_id, $message]);

        // Recuperar contexto anterior (últimos 10 mensajes)
        $stmt_hist = $db->prepare("SELECT role, content FROM romita_messages WHERE chat_id = ? ORDER BY id ASC LIMIT 10");
        $stmt_hist->execute([$chat_id]);
        $history = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

        $contents = [];
        foreach($history as $h) {
            $geminiRole = $h['role'] === 'user' ? 'user' : 'model';
            $contents[] = [
                "role" => $geminiRole,
                "parts" => [["text" => $h['content']]]
            ];
        }

        $payload = [ "contents" => $contents ];

        // Instrucción de Sistema
        $sysInstructions = [];

        // 1. Especialidad seleccionada
        $specialtyPrompts = [
            'director_360' => "IDENTIDAD & ROL PRINCIPAL: Eres Romita, la Directora Estratégica 360° y CMO de Roma Agencia. Posees una visión integral que conecta Branding, Contenido, Redes Sociales, Desarrollo Web, Producción Audiovisual y Conversión. Respondes con liderazgo, claridad ejecutiva, visión de negocio y coordinación entre todas las áreas de la agencia.",
            'community_manager' => "IDENTIDAD & ROL PRINCIPAL: Eres Romita en tu especialidad de Senior Community Manager y Copywriter de Alto Impacto. Eres experta en psicología de audiencias, ganchos magnéticos (Hooks) que detienen el scroll, storytelling dinámico, formatos de tendencia (Reels, TikTok, Carruseles, Threads, LinkedIn) y llamadas a la acción (CTAs) que disparan el engagement.",
            'branding' => "IDENTIDAD & ROL PRINCIPAL: Eres Romita en tu especialidad de Especialista Senior en Branding y Estrategia de Marca. Eres la guardiana de la coherencia de marca, arquetipos (Jung), personalidad verbal, identidad visual, propuesta de valor única y posicionamiento en el mercado.",
            'marketing' => "IDENTIDAD & ROL PRINCIPAL: Eres Romita en tu especialidad de Especialista Senior en Growth Marketing y Conversión. Tu enfoque es 100% resultados: embudos de ventas (TOFU, MOFU, BOFU), adquisición de clientes, optimización de tasas de conversión (CRO), pauta digital (Meta Ads, Google Ads) y métricas de rendimiento (ROAS, CAC, CTR, LTV).",
            'seo' => "IDENTIDAD & ROL PRINCIPAL: Eres Romita en tu especialidad de Especialista Senior en SEO y Posicionamiento Web. Dominas la intención de búsqueda (Search Intent), arquitectura de contenidos, clusters temáticos, optimización on-page (títulos, encabezados, metadatos, enlazado interno) y estrategias para dominar las primeras posiciones de Google."
        ];

        $sysInstructions[] = $specialtyPrompts[$specialty] ?? $specialtyPrompts['director_360'];

        // 2. Contexto temporal en tiempo real (Zona horaria de la empresa: America/Lima)
        $tz = new DateTimeZone('America/Lima');
        $now = new DateTime('now', $tz);
        $diasSemana = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        
        $diaNombre = $diasSemana[$now->format('l')] ?? $now->format('l');
        $diaNum = $now->format('d');
        $mesNum = (int)$now->format('m');
        $mesNombre = $meses[$mesNum];
        $anoActual = $now->format('Y');

        $nextMonthDate = (clone $now)->modify('+1 month');
        $nextMesNum = (int)$nextMonthDate->format('m');
        $nextMesNombre = $meses[$nextMesNum];
        $nextAno = $nextMonthDate->format('Y');

        $temporalContext = "INFORMACIÓN TEMPORAL ACTUAL EN TIEMPO REAL:\n"
            . "- Fecha actual exacta: {$diaNombre}, {$diaNum} de {$mesNombre} de {$anoActual}.\n"
            . "- Mes actual: {$mesNombre} ({$anoActual}) - Mes número {$mesNum}.\n"
            . "- Próximo mes: {$nextMesNombre} ({$nextAno}) - Mes número {$nextMesNum}.\n"
            . "- REGLA CRÍTICA DE FECHA: Sabes con total certidumbre la fecha, mes y año de hoy. Si el usuario te pide una propuesta o estrategia para 'este mes', asume directamente y sin dudar {$mesNombre} de {$anoActual}. Si pide 'el próximo mes', asume {$nextMesNombre} de {$nextAno}. NUNCA le preguntes al usuario '¿en qué mes estamos?' ni '¿qué año es?'.\n\n"
            . "REGLA DE FORMATO DE TABLAS:\n"
            . "- Si presentas propuestas, calendarios o contenidos en tabla, utiliza SIEMPRE tablas Markdown válidas y estándar con fila de encabezados y fila separadora obligatoria (ej: | :--- | :--- | :--- |).\n"
            . "- Diseña tablas ejecutivas, legibles y limpias con un máximo recomendado de 4 a 6 columnas clave (ejemplo: | Fecha / Día | Marca | Formato | Pilar | Concepto & Gancho | Copy Sugerido |).\n"
            . "- Redacta los textos de cada celda de forma persuasiva, limpia y sintetizada (evita saturar una sola celda con párrafos gigantescos para garantizar una lectura ágil tanto en escritorio como en dispositivos móviles).\n"
            . "- No mezcles texto suelto ni saltos de línea crudos dentro de las celdas que rompan la estructura de la tabla Markdown.";
        
        $sysInstructions[] = $temporalContext;

        // 3. Inteligencia del Ecosistema de la Agencia (Proyectos de Marca, Web, Audiovisual, Pizarras, Calendario con RBAC)
        $sysInstructions[] = getAgencyFullEcosystemContext($db, $current_module, $entity_id, $role_name, $is_admin, $user_permissions);

        // 4. Base de Conocimiento y Procedimientos Oficiales de Roma Agencia (SOPs, guías, manuales)
        $kbContext = getKnowledgeBaseContext($db, $message, $current_module, $entity_id);
        if (!empty($kbContext)) {
            $sysInstructions[] = $kbContext;
        }

        if (!empty($skill_prompt)) {
            $sysInstructions[] = $skill_prompt;
        }

        // Si hay un Prept asociado, leer sus datos y publicaciones anteriores
        $prept_id = $_POST['prept_id'] ?? null;
        if ($prept_id) {
            $stmt = $db->prepare("SELECT name, tone, audience, rules FROM romita_prepts WHERE id = ?");
            $stmt->execute([$prept_id]);
            $prept = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($prept) {
                $preptCtx = "Eres el gestor de contenido de la marca '{$prept['name']}'.\n";
                $preptCtx .= "Tono: {$prept['tone']}\nAudiencia: {$prept['audience']}\nReglas: {$prept['rules']}\n\n";
                
                $stmt2 = $db->prepare("SELECT topic, content_summary, created_at FROM romita_prept_content WHERE prept_id = ? ORDER BY created_at DESC LIMIT 10");
                $stmt2->execute([$prept_id]);
                $pastContents = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($pastContents) > 0) {
                    $preptCtx .= "HISTORIAL DE CONTENIDOS PREVIOS (NO REPETIR ESTOS TEMAS):\n";
                    foreach($pastContents as $pc) {
                        $preptCtx .= "- Fecha: {$pc['created_at']}, Tema: {$pc['topic']}, Resumen: {$pc['content_summary']}\n";
                    }
                }
                
                $sysInstructions[] = $preptCtx;
            }
        }

        // Si hay un Proyecto de Calendario asociado, inyectar base de conocimiento histórica
        $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        if ($project_id) {
            $projIntel = getProjectCalendarContext($db, $project_id);
            if ($projIntel) {
                $sysInstructions[] = $projIntel['context_text'];
            }
        }

        // Si el usuario pregunta por finanzas, balance, proyectos o KPIs, inyectar inteligencia ejecutiva
        $msgLower = mb_strtolower($message);
        $financialKeywords = ['finanza', 'ingreso', 'gasto', 'balance', 'utilidad', 'kpi', 'cobro', 'rentabilidad', 'cuanto hemos', 'cuánto hemos', 'cotizaciones', 'como vamos', 'cómo vamos', 'rendimiento'];
        $hasFinanceIntent = false;
        foreach ($financialKeywords as $kw) {
            if (mb_strpos($msgLower, $kw) !== false) {
                $hasFinanceIntent = true;
                break;
            }
        }

        if (($is_admin || in_array('admin', $user_permissions)) && ($hasFinanceIntent || strpos($skill_prompt, 'Financiero') !== false || strpos($skill_prompt, 'Consultor') !== false)) {
            $agencyIntel = getAgencyIntelligenceContext($db);
            if ($agencyIntel) {
                $sysInstructions[] = $agencyIntel;
            }
        }

        if (count($sysInstructions) > 0) {
            $payload["system_instruction"] = [
                "parts" => [["text" => implode("\n\n---\n\n", $sysInstructions)]]
            ];
        }

        // 4. Conexión a Gemini API con multi-key y multi-model fallbacks
        $stmtKey = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
        $dbApiKey = $stmtKey ? trim($stmtKey->fetchColumn() ?: '') : '';
        $apiKeysToTry = array_values(array_filter([
            $dbApiKey,
            getenv('GEMINI_API_KEY') ?: '',
            'AIzaSyDIzZJ62tamjKWL73CgEORCDxzifIlIkUw',
            'AQ.Ab8RN6IMDdwCwC9tCRzve5p6Vf8te8CVRhFAjucDPSCJ9wy5Mg'
        ]));

        $modelsToTry = ['gemini-3.5-flash-lite', 'gemini-flash-lite-latest', 'gemini-3.8-flash', 'gemini-3.6-flash', 'gemini-3.1-flash-lite'];
        $ia_response = "";
        $lastError = "No se pudo conectar con la IA de Romita.";

        $breakOuter = false;
        foreach ($apiKeysToTry as $currentApiKey) {
            if ($breakOuter) break;

            foreach ($modelsToTry as $modelName) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . $currentApiKey;

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $response = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($curlErr) {
                    $lastError = "Error de red: " . $curlErr;
                    continue;
                }

                $responseData = json_decode($response, true);

                if ($httpcode >= 200 && $httpcode < 300 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                    $ia_response = trim($responseData['candidates'][0]['content']['parts'][0]['text']);
                    $breakOuter = true;
                    break;
                } else {
                    $lastError = $responseData['error']['message'] ?? "Error HTTP $httpcode";
                    if ($httpcode === 404) {
                        continue; // Probar otro modelo
                    }
                    if ($httpcode === 400 || $httpcode === 401 || $httpcode === 403) {
                        break; // Probar siguiente API key
                    }
                }
            }
        }

        if (empty($ia_response)) {
            // Asistente de contingencia de Romita para que el usuario no reciba un error bloqueante
            $ia_response = "👋 **¡Hola! Soy Romita**, asistente estratégica y creativa de Roma Agencia.\n\n" .
                "Actualmente estoy operando en **modo autónomo local** porque la **API Key de Gemini** aún no está configurada o se encuentra inactiva.\n\n" .
                "### 🚀 ¿Cómo activarme al 100% con IA en vivo?\n" .
                "1. Obtén tu clave gratuita en [Google AI Studio (aistudio.google.com)](https://aistudio.google.com/app/apikey).\n" .
                "2. Ve a **Ajustes > IA** en el sistema y pega tu clave allí.\n\n" .
                "⚡ *Una vez guardada la clave, podré redactar copies avanzados, analizar imágenes y responder consultas en tiempo real sin límites.*";
        }

        // Insertar msj IA
        $stmt_ai_msg = $db->prepare("INSERT INTO romita_messages (chat_id, role, content) VALUES (?, 'assistant', ?)");
        $stmt_ai_msg->execute([$chat_id, $ia_response]);

        // Si hay un Prept asociado y la IA generó contenido (por ejemplo, más de 200 caracteres), guardarlo en el historial del prept
        if ($prept_id && strlen($ia_response) > 200) {
            // Guardamos un fragmento como tema y el inicio como resumen
            $topic = mb_substr($message, 0, 100);
            $summary = mb_substr($ia_response, 0, 500) . '...';
            $stmt = $db->prepare("INSERT INTO romita_prept_content (prept_id, topic, content_summary) VALUES (?, ?, ?)");
            $stmt->execute([$prept_id, $topic, $summary]);
        }

        // Backup a Google Drive sigue intacto
        $drive = new GoogleDriveHelper();
        if ($drive->isConfigured()) {
            $folderName = "Romita_Chats";
            $files = $drive->searchFiles("name='$folderName' and mimeType='application/vnd.google-apps.folder' and trashed=false");
            
            $folderId = null;
            if ($files && count($files) > 0) {
                $folderId = $files[0]['id'];
            } else {
                $folderId = $drive->createFolder($folderName);
            }
            
            if ($folderId) {
                $date = date('Y-m-d');
                $userName = preg_replace('/[^A-Za-z0-9_]/', '', $_SESSION['user_name'] ?? 'usuario');
                if (empty($userName)) $userName = 'usuario';
                $fileName = "chat_{$userName}_{$date}.md";
                
                $logContent = "### User (" . date('H:i:s') . ")\n" . $message . "\n\n";
                $logContent .= "### Romita (" . date('H:i:s') . ")\n" . $ia_response . "\n\n---\n\n";
                
                $tmpPath = sys_get_temp_dir() . '/' . $fileName;
                
                $existingFiles = $drive->searchFiles("name='$fileName' and '$folderId' in parents and trashed=false");
                if($existingFiles && count($existingFiles) > 0) {
                    $fileId = $existingFiles[0]['id'];
                    $drive->downloadFile($fileId, $tmpPath);
                    file_put_contents($tmpPath, $logContent, FILE_APPEND);
                    $drive->deleteFile($fileId);
                } else {
                    file_put_contents($tmpPath, $logContent);
                }
                $drive->uploadFile($tmpPath, $fileName, $folderId);
                @unlink($tmpPath);
            }
        }

        echo json_encode(['success' => true, 'response' => $ia_response, 'chat_id' => $chat_id]);
        exit();
    }
    
    // Obtener lista de chats
    if ($action === 'get_chats') {
        $stmt = $db->prepare("SELECT id, title, created_at FROM romita_chats WHERE user_id = ? ORDER BY updated_at DESC LIMIT 50");
        $stmt->execute([$user_id]);
        $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'chats' => $chats]);
        exit();
    }

    // Obtener mensajes de un chat
    if ($action === 'get_messages') {
        $chat_id = $_POST['chat_id'] ?? '';
        
        // Validar propiedad
        $stmt_check = $db->prepare("SELECT id FROM romita_chats WHERE id = ? AND user_id = ?");
        $stmt_check->execute([$chat_id, $user_id]);
        if (!$stmt_check->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit();
        }

        $stmt = $db->prepare("SELECT id, role, content FROM romita_messages WHERE chat_id = ? ORDER BY id ASC");
        $stmt->execute([$chat_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit();
    }

    // Eliminar un chat
    if ($action === 'delete_chat') {
        $chat_id = $_POST['chat_id'] ?? '';
        
        $stmt_check = $db->prepare("SELECT id FROM romita_chats WHERE id = ? AND user_id = ?");
        $stmt_check->execute([$chat_id, $user_id]);
        if (!$stmt_check->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit();
        }

        $stmt = $db->prepare("DELETE FROM romita_chats WHERE id = ?");
        $stmt->execute([$chat_id]);
        echo json_encode(['success' => true]);
        exit();
    }

    // Obtener resumen de inteligencia de proyecto para badge/UI
    if ($action === 'get_project_intel') {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $intel = getProjectCalendarContext($db, $project_id);
        if ($intel) {
            echo json_encode(['success' => true, 'intel' => $intel]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Proyecto no encontrado']);
        }
        exit();
    }

    // Crear mes y publicaciones en el módulo Calendario
    if ($action === 'create_calendar_month') {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $month = (int)($_POST['month'] ?? 0);
        $year = (int)($_POST['year'] ?? 0);
        $posts_json = $_POST['posts_json'] ?? '';

        if (!$project_id || !$month || !$year) {
            echo json_encode(['success' => false, 'error' => 'Proyecto, mes y año son obligatorios']);
            exit();
        }

        // Validar proyecto
        $stmtP = $db->prepare("SELECT p.id, wo.brand_name FROM projects p JOIN work_orders wo ON p.work_order_id = wo.id WHERE p.id = ?");
        $stmtP->execute([$project_id]);
        $proj = $stmtP->fetch(PDO::FETCH_ASSOC);
        if (!$proj) {
            echo json_encode(['success' => false, 'error' => 'Proyecto no encontrado']);
            exit();
        }

        // Comprobar si el mes ya existe o crearlo
        $stmtCheck = $db->prepare("SELECT id FROM project_months WHERE project_id = ? AND month = ? AND year = ?");
        $stmtCheck->execute([$project_id, $month, $year]);
        $existingMonthId = $stmtCheck->fetchColumn();

        if ($existingMonthId) {
            $month_id = $existingMonthId;
        } else {
            $start_date = sprintf('%04d-%02d-01', $year, $month);
            $due_date = date('Y-m-t', strtotime($start_date));
            $stmtInsert = $db->prepare("INSERT INTO project_months (project_id, month, year, start_date, due_date, status) VALUES (?, ?, ?, ?, ?, 'pendiente')");
            $stmtInsert->execute([$project_id, $month, $year, $start_date, $due_date]);
            $month_id = $db->lastInsertId();
        }

        // Insertar publicaciones si se recibieron
        $createdCount = 0;
        if (!empty($posts_json)) {
            $posts = json_decode($posts_json, true);
            if (is_array($posts)) {
                $stmtPost = $db->prepare("
                    INSERT INTO month_posts 
                    (month_id, post_date, concept, copy_text, platform, status, post_type, content_pillar, design_brief) 
                    VALUES (?, ?, ?, ?, ?, 'Borrador', ?, ?, ?)
                ");

                foreach ($posts as $p) {
                    $concept = trim($p['concept'] ?? 'Publicación planificada');
                    $copy = trim($p['copy'] ?? ($p['copy_text'] ?? ''));
                    $platform = trim($p['platform'] ?? 'Instagram, Facebook');
                    $post_type = trim($p['post_type'] ?? 'Post Terminado');
                    $pillar = trim($p['content_pillar'] ?? 'Educación');
                    $brief = trim($p['design_brief'] ?? '');
                    
                    // Formatear post_date
                    $rawDate = $p['date'] ?? ($p['post_date'] ?? '');
                    if (!empty($rawDate)) {
                        $ts = strtotime($rawDate);
                        $post_date = $ts ? date('Y-m-d 10:00:00', $ts) : sprintf('%04d-%02d-01 10:00:00', $year, $month);
                    } else {
                        $post_date = sprintf('%04d-%02d-01 10:00:00', $year, $month);
                    }

                    $stmtPost->execute([$month_id, $post_date, $concept, $copy, $platform, $post_type, $pillar, $brief]);
                    $createdCount++;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'month_id' => $month_id,
            'brand_name' => $proj['brand_name'],
            'created_posts' => $createdCount,
            'redirect_url' => "index.php?module=month_board&id={$month_id}"
        ]);
        exit();
    }

    // Gestión de PREPTS
    if ($action === 'get_prepts') {
        $stmt = $db->query("SELECT * FROM romita_prepts ORDER BY name ASC");
        $prepts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'prepts' => $prepts]);
        exit();
    }

    if ($action === 'save_prept') {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit();
        }
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        $tone = $_POST['tone'] ?? '';
        $audience = $_POST['audience'] ?? '';
        $rules = $_POST['rules'] ?? '';

        if(empty($name)) {
            echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio']);
            exit();
        }

        if ($id) {
            $stmt = $db->prepare("UPDATE romita_prepts SET name=?, tone=?, audience=?, rules=? WHERE id=?");
            $stmt->execute([$name, $tone, $audience, $rules, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO romita_prepts (name, tone, audience, rules) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $tone, $audience, $rules]);
        }
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    // Acciones de Administrador
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Permisos insuficientes']);
        exit();
    }

    if ($action === 'save_skill') {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        $prompt = $_POST['prompt_base'] ?? '';
        $role = $_POST['role'] ?? 'all';
        $desc = "Skill personalizado para $name";

        if ($id) {
            $stmt = $db->prepare("UPDATE romita_skills SET name=?, description=?, prompt_base=?, allowed_role=? WHERE id=?");
            $stmt->execute([$name, $desc, $prompt, $role, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO romita_skills (name, description, prompt_base, allowed_role, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$name, $desc, $prompt, $role]);
        }
        
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'delete_skill') {
        $id = $_POST['id'] ?? '';
        if($id) {
            $stmt = $db->prepare("DELETE FROM romita_skills WHERE id = ?");
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Acción inválida']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
