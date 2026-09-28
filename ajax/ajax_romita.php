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

// Función de auto-sanación de esquema de Romita (resiliencia multi-entorno y multi-base de datos)
function ensureRomitaSchema($db) {
    static $checked = false;
    if ($checked || !$db) return;
    $checked = true;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM `romita_messages` LIKE 'attachment_url'")->fetch();
        if (!$checkCol) {
            $db->exec("ALTER TABLE `romita_messages` 
                ADD COLUMN `attachment_url` varchar(255) DEFAULT NULL,
                ADD COLUMN `attachment_type` varchar(50) DEFAULT NULL,
                ADD COLUMN `attachment_name` varchar(255) DEFAULT NULL,
                ADD COLUMN `feedback` tinyint(4) DEFAULT NULL");
        }
    } catch (\Throwable $e) {}

    try {
        $checkShare = $db->query("SHOW COLUMNS FROM `romita_chats` LIKE 'share_token'")->fetch();
        if (!$checkShare) {
            $db->exec("ALTER TABLE `romita_chats` ADD COLUMN `share_token` varchar(64) DEFAULT NULL, ADD KEY `idx_romita_chats_share` (`share_token`)");
        }
    } catch (\Throwable $e) {}

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `romita_user_preferences` (
          `user_id` int(11) NOT NULL,
          `response_style` varchar(50) DEFAULT 'conciso',
          `custom_instructions` text DEFAULT NULL,
          `default_specialty` varchar(50) DEFAULT 'all',
          `sound_enabled` tinyint(1) DEFAULT 1,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (\Throwable $e) {}

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `romita_super_prompts` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) DEFAULT NULL,
          `title` varchar(255) NOT NULL,
          `category` varchar(100) DEFAULT 'general',
          `prompt` text NOT NULL,
          `description` varchar(255) DEFAULT NULL,
          `icon` varchar(50) DEFAULT 'ph-lightning',
          `is_agency_template` tinyint(1) DEFAULT 0,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_user_prompts` (`user_id`),
          KEY `idx_agency_prompts` (`is_agency_template`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $count = (int)$db->query("SELECT COUNT(*) FROM `romita_super_prompts` WHERE is_agency_template = 1")->fetchColumn();
        if ($count === 0) {
            $stmtSeed = $db->prepare("INSERT INTO `romita_super_prompts` 
                (`user_id`, `title`, `category`, `prompt`, `description`, `icon`, `is_agency_template`, `created_at`) 
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
            
            $defaultPrompts = [
                [
                    null,
                    'Guion de Reels de Alta Retención (9:16)',
                    'Redes Sociales',
                    "Actúa como Director Creativo Audiovisual. Redacta un guion para un Reel de 45 segundos sobre [TEMA / PRODUCTO] para la marca activa. Divide el contenido en 4 partes con minutaje exacto:\n1. Gancho Visual & Auditivo (0-3s): ¿Qué ve y escucha el espectador para no deslizar?\n2. Retención & Quiebre (3-15s): Presenta el problema común de forma intrigante.\n3. Núcleo de Valor (15-38s): 3 soluciones prácticas paso a paso.\n4. Llamado a la Acción (38-45s): CTA claro invitando a comentar una palabra clave para recibir información.",
                    'Estructura probada: Gancho 3s + Quiebre de patrón + Valor + CTA',
                    'ph-video-camera'
                ],
                [
                    null,
                    'Carrusel B2B Educativo (10 Slides)',
                    'Contenido Educativo',
                    "Diseña el guion completo de un carrusel educativo de 10 láminas sobre [TEMA] adaptado al sector de la marca activa.\nEstructura cada slide con:\n- Título conciso (máximo 6 palabras).\n- Cuerpo de texto en viñetas directas (máximo 25 palabras).\n- Indicación de diseño gráfico / elemento visual para el equipo de diseño.\n- Slide 1: Portada magnética con promesa irresistible.\n- Slide 2: El error número 1 que todos cometen.\n- Slides 3 a 8: Metodología paso a paso desglosada.\n- Slide 9: Resumen de takeaways en 3 viñetas.\n- Slide 10: Portada final con llamado a guardar y compartir.",
                    'Formato de micro-aprendizaje para LinkedIn e Instagram',
                    'ph-slideshow'
                ],
                [
                    null,
                    'Fórmula PAS para Copywriting Publicitario',
                    'Copywriting & Ads',
                    "Genera 3 variaciones de copy publicitario para Meta Ads (Facebook/Instagram) usando la fórmula PAS (Problema - Agitación - Solución) enfocado en [OFERTA / SERVICIO].\nEstructura cada variación:\n1. Problema: El dolor real y frustrante de la audiencia objetivo.\n2. Agitación: Qué pasa si no resuelven ese problema hoy.\n3. Solución: Cómo nuestro servicio elimina el dolor de raíz.\n4. Oferta irresistible con CTA claro.\nEntrega Variación A (Enfoque directo al grano), Variación B (Enfoque storytelling testimonial), y Variación C (Enfoque de autoridad y datos).",
                    'Problema, Agitación y Solución con 3 variaciones de tono',
                    'ph-lightning'
                ],
                [
                    null,
                    'Simulador de Objeciones de Clientes (Role-Play)',
                    'Ventas & Clientes',
                    "Iniciemos una sesión de ROLE-PLAY de ventas.\nTú actuarás como un cliente escéptico y exigente interesado en contratar los servicios de nuestra agencia.\nPresenta una primera objeción realista y difícil (por ejemplo: \"Su tarifa es demasiado cara, otra agencia me cobra la mitad\" o \"No creo que las redes sociales funcionen para mi nicho\").\nQuédate en personaje. Espera mi respuesta y luego califícame del 1 al 10 con feedback constructivo sobre cómo mejorar mi argumento de venta, y luego lanza la siguiente objeción más difícil.",
                    'Entrenamiento de objeciones duras en tiempo real',
                    'ph-users-three'
                ],
                [
                    null,
                    'Auditoría Rápida de Competencia & Benchmark',
                    'Estrategia & Research',
                    "Realiza un análisis competitivo rápido tipo Benchmark para la marca activa frente a sus 3 principales competidores en el mercado.\nEntrega una tabla comparativa con:\n- Pilares de contenido principales.\n- Frecuencia y formatos dominantes (Reels, Fotos, Carruseles).\n- Tono de comunicación.\n- Calidad de comunidad y engagement promedio.\n- \"Gaps\" o brechas de oportunidad que nuestra agencia puede capitalizar para destacar.",
                    'Matriz comparativa de 3 competidores clave con brechas de oportunidad',
                    'ph-chart-bar'
                ],
                [
                    null,
                    'Plan de Contenidos 30 Días (Matriz Mensual)',
                    'Planificación',
                    "Genera la matriz estratégica de contenidos para los próximos 30 días de la marca seleccionada.\nConsidera 3 publicaciones semanales (12 en total) balanceando los 4 pilares:\n- 30% Educativo / Valor técnico.\n- 30% Autoridad / Casos de éxito y testimonios.\n- 20% Conexión humana / Behind the scenes de la agencia.\n- 20% Venta directa / Promoción de servicios.\nPresenta todo en una tabla detallada con Día, Formato (Reel, Carrusel, Estático), Objetivo, Gancho y Llamado a la Acción.",
                    'Distribución estratégica de 12 a 16 piezas para el mes',
                    'ph-calendar-check'
                ]
            ];

            foreach ($defaultPrompts as $dp) {
                $stmtSeed->execute($dp);
            }
        }
    } catch (\Throwable $e) {}
}
ensureRomitaSchema($db);

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
                } elseif ($current_module === 'forms' && $canAccess('forms')) {
                    if ($entity_id > 0) {
                        $stmtFt = $db->prepare("SELECT title, status, description FROM form_templates WHERE id = ?");
                        $stmtFt->execute([$entity_id]);
                        $ftRow = $stmtFt->fetch(PDO::FETCH_ASSOC);
                        if ($ftRow) {
                            $context .= "  • Formulario en Pantalla: '{$ftRow['title']}' [Estado: {$ftRow['status']}]\n";
                        }
                    } else {
                        $context .= "  • El usuario está navegando el Módulo de Formularios (Plantillas y Respuestas)\n";
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
                WHERE price > 0 AND name IS NOT NULL AND name != '' AND name NOT LIKE 'sdf%'
                ORDER BY price DESC
            ");
            $services = $stmtServices ? $stmtServices->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($services)) {
                $context .= "CATÁLOGO OFICIAL DE SERVICIOS Y TARIFAS DE LA AGENCIA:\n";
                foreach ($services as $s) {
                    $cur = !empty(trim($s['currency'] ?? '')) ? trim($s['currency']) : 'S/';
                    $time = !empty(trim($s['delivery_time'] ?? '')) ? " (Tiempo: {$s['delivery_time']})" : "";
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

        // K. Formularios y Briefs Registrados
        if ($canAccess('forms')) {
            $stmtForms = $db->query("
                SELECT id, title, status, 
                       (SELECT COUNT(*) FROM form_submissions WHERE template_id = form_templates.id) as total_subs
                FROM form_templates
                ORDER BY id DESC LIMIT 6
            ");
            $formsList = $stmtForms ? $stmtForms->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($formsList)) {
                $context .= "FORMULARIOS Y BRIEFS EN EL MÓDULO DE FORMULARIOS:\n";
                foreach ($formsList as $fl) {
                    $context .= "- Formulario #{$fl['id']}: '{$fl['title']}' [{$fl['status']}] ({$fl['total_subs']} respuestas registradas)\n";
                }
                $context .= "\n";
            }
        }

        // L. Balance Financiero Ejecutivo en Tiempo Real (Solo si tiene permiso 'admin')
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

/**
 * FASE 3: Lector de Enlaces Web en Vivo
 * Extrae de forma segura el título, descripción y cuerpo de una URL pública
 */
function fetchLiveUrlContent($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;
    $parsed = parse_url($url);
    if (!in_array(strtolower($parsed['scheme'] ?? ''), ['http', 'https'])) return null;
    
    // SSRF prevention: bloquear IPs locales y privadas
    $host = strtolower($parsed['host'] ?? '');
    if (in_array($host, ['localhost', '127.0.0.1', '::1']) || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host)) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 400 || empty($html)) {
        return null;
    }

    // Extraer Título
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1])));
    }

    // Extraer Meta Descripción
    $desc = '';
    if (preg_match('/<meta[^>]+name=[\'"]description[\'"][^>]+content=[\'"]([^\'"]+)[\'"]/i', $html, $m) ||
        preg_match('/<meta[^>]+property=[\'"]og:description[\'"][^>]+content=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
        $desc = trim(html_entity_decode($m[1]));
    }

    // Limpiar contenido HTML innecesario
    $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $clean = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $clean);
    $clean = preg_replace('/<svg\b[^>]*>(.*?)<\/svg>/is', '', $clean);
    $clean = preg_replace('/<nav\b[^>]*>(.*?)<\/nav>/is', '', $clean);
    $clean = preg_replace('/<footer\b[^>]*>(.*?)<\/footer>/is', '', $clean);
    $clean = strip_tags($clean);
    $clean = preg_replace('/\s+/', ' ', $clean);
    $clean = trim($clean);

    if (mb_strlen($clean) > 5000) {
        $clean = mb_substr($clean, 0, 5000) . '... [Contenido truncado para análisis]';
    }

    $result = "";
    if ($title) $result .= "Título de la página: " . $title . "\n";
    if ($desc) $result .= "Descripción: " . $desc . "\n";
    $result .= "Texto extraído del sitio web:\n" . $clean;

    return $result;
}

try {
    if ($action === 'chat') {
        $message = trim($_POST['message'] ?? '');
        $skill_prompt = $_POST['skill_prompt'] ?? '';
        $specialty = $_POST['specialty'] ?? 'director_360';
        $current_module = $_POST['current_module'] ?? '';
        $entity_id = (int)($_POST['entity_id'] ?? 0);
        
        // FASE 3: Gestión de Adjuntos Multimodales (Imágenes, PDF, Documentos)
        $attachment_url = null;
        $attachment_type = null;
        $attachment_name = null;
        $attachment_base64 = null;
        $attachment_mime = null;
        $attachment_text = null;

        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'pdf', 'txt', 'csv', 'json'];
            
            if (in_array($ext, $allowedExts)) {
                $attachment_name = basename($file['name']);
                $uploadDir = __DIR__ . '/../uploads/romita/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                $uniqueName = 'romita_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destPath = $uploadDir . $uniqueName;
                
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $attachment_url = 'uploads/romita/' . $uniqueName;
                    
                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                        $attachment_type = 'image';
                        $attachment_mime = mime_content_type($destPath) ?: ('image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
                        $attachment_base64 = base64_encode(file_get_contents($destPath));
                    } elseif ($ext === 'pdf') {
                        $attachment_type = 'pdf';
                        $attachment_mime = 'application/pdf';
                        $attachment_base64 = base64_encode(file_get_contents($destPath));
                    } elseif (in_array($ext, ['txt', 'csv', 'json'])) {
                        $attachment_type = 'text';
                        $rawContent = file_get_contents($destPath);
                        $attachment_text = mb_substr($rawContent, 0, 25000);
                    }
                }
            }
        }

        // Si el usuario no escribió texto pero adjuntó un archivo, asignar un prompt por defecto
        if (empty($message) && $attachment_url) {
            if ($attachment_type === 'image') {
                $message = "He adjuntado esta imagen/diseño publicitario. Por favor realiza una auditoría creativa integral: evalúa la legibilidad, contraste de tipografía, jerarquía visual, composición y el cumplimiento de zonas seguras para redes sociales, brindando recomendaciones prácticas.";
            } elseif ($attachment_type === 'pdf') {
                $message = "He adjuntado este documento PDF. Por favor analízalo exhaustivamente y prepárame un resumen ejecutivo estructurado con los puntos clave, datos cuantitativos y próximos pasos recomendados.";
            } elseif ($attachment_type === 'text') {
                $message = "He adjuntado este archivo de datos/texto. Por favor analiza la información contenida y preséntame un resumen estructurado con conclusiones y puntos de acción.";
            }
        }

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

        // Insertar msj usuario con metadatos de adjunto
        try {
            $stmt_user_msg = $db->prepare("INSERT INTO romita_messages (chat_id, role, content, attachment_url, attachment_type, attachment_name) VALUES (?, 'user', ?, ?, ?, ?)");
            $stmt_user_msg->execute([$chat_id, $message, $attachment_url, $attachment_type, $attachment_name]);
        } catch (\PDOException $pdoEx) {
            ensureRomitaSchema($db);
            $stmt_user_msg = $db->prepare("INSERT INTO romita_messages (chat_id, role, content, attachment_url, attachment_type, attachment_name) VALUES (?, 'user', ?, ?, ?, ?)");
            $stmt_user_msg->execute([$chat_id, $message, $attachment_url, $attachment_type, $attachment_name]);
        }

        // Recuperar contexto anterior (últimos mensajes en orden cronológico)
        try {
            $stmt_hist = $db->prepare("SELECT id, role, content FROM (
                SELECT id, role, content FROM romita_messages 
                WHERE chat_id = ? 
                ORDER BY id DESC 
                LIMIT 20
            ) sub ORDER BY id ASC");
            $stmt_hist->execute([$chat_id]);
            $history = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $history = [];
        }

        // Sanitización y armado del payload para Gemini (reglas estrictas de alternancia user -> model)
        $rawTurns = [];
        foreach ($history as $h) {
            $content = trim($h['content'] ?? '');
            if (empty($content)) continue;

            // Omitir del historial mensajes de contingencia o errores previos para no corromper el contexto
            if (strpos($content, 'modo autónomo local') !== false ||
                strpos($content, 'Límite de solicitudes') !== false ||
                strpos($content, 'Alta demanda en los servidores') !== false) {
                continue;
            }

            $rawTurns[] = [
                'role' => ($h['role'] === 'user') ? 'user' : 'model',
                'text' => $content
            ];
        }

        // Asegurar que el mensaje actual del usuario esté al final
        $totalRaw = count($rawTurns);
        if ($totalRaw === 0 || $rawTurns[$totalRaw - 1]['role'] !== 'user' || $rawTurns[$totalRaw - 1]['text'] !== $message) {
            $rawTurns[] = [
                'role' => 'user',
                'text' => $message
            ];
        }

        // Regla 1: Debe iniciar siempre con un turno 'user'
        while (!empty($rawTurns) && $rawTurns[0]['role'] !== 'user') {
            array_shift($rawTurns);
        }
        if (empty($rawTurns)) {
            $rawTurns[] = ['role' => 'user', 'text' => $message];
        }

        // Regla 2: Alternancia estricta (no permitir dos turnos seguidos del mismo rol)
        $alternatedTurns = [];
        foreach ($rawTurns as $turn) {
            if (empty($alternatedTurns)) {
                $alternatedTurns[] = $turn;
            } else {
                $prevIdx = count($alternatedTurns) - 1;
                if ($alternatedTurns[$prevIdx]['role'] === $turn['role']) {
                    $alternatedTurns[$prevIdx]['text'] .= "\n\n" . $turn['text'];
                } else {
                    $alternatedTurns[] = $turn;
                }
            }
        }

        // Regla 3: El último turno DEBE ser obligatoriamente 'user' (requisito estricto de la API de Gemini)
        if (empty($alternatedTurns) || $alternatedTurns[count($alternatedTurns) - 1]['role'] !== 'user') {
            $alternatedTurns[] = ['role' => 'user', 'text' => $message];
        }

        // Construir la estructura final de 'contents' para Gemini
        $contents = [];
        $lastAlternatedIdx = count($alternatedTurns) - 1;
        foreach ($alternatedTurns as $idx => $turn) {
            if ($idx === $lastAlternatedIdx && $turn['role'] === 'user') {
                // Adjunto multimodal o de texto en el mensaje actual
                if (!empty($attachment_base64) && !empty($attachment_mime)) {
                    $promptWithAtt = ($attachment_type === 'image' 
                        ? "IMAGEN ADJUNTA ({$attachment_name}):\n" . $turn['text']
                        : "DOCUMENTO PDF ADJUNTO ({$attachment_name}):\n" . $turn['text']);
                    $contents[] = [
                        "role" => "user",
                        "parts" => [
                            [
                                "inline_data" => [
                                    "mime_type" => $attachment_mime,
                                    "data" => $attachment_base64
                                ]
                            ],
                            ["text" => $promptWithAtt]
                        ]
                    ];
                } elseif (!empty($attachment_text)) {
                    $contents[] = [
                        "role" => "user",
                        "parts" => [["text" => "CONTENIDO DEL ARCHIVO ADJUNTO ({$attachment_name}):\n```\n{$attachment_text}\n```\n\nCONSULTA: " . $turn['text']]]
                    ];
                } else {
                    $contents[] = [
                        "role" => "user",
                        "parts" => [["text" => $turn['text']]]
                    ];
                }
            } else {
                $contents[] = [
                    "role" => $turn['role'],
                    "parts" => [["text" => $turn['text']]]
                ];
            }
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

        // 2.5 Capacidades Agénticas y Acciones con 1 Clic (Roma Actions)
        $agenticInstructions = "CAPACIDADES AGÉNTICAS Y ACCIONES CON 1 CLIC (ROMA ACTIONS):\n"
            . "Como copiloto activo de Roma Agencia, tienes la capacidad de generar bloques de acción ejecutables e interactivos para que el usuario guarde datos reales en el sistema con un solo clic. Utiliza estos bloques especiales al final de tu respuesta cuando sea pertinente:\n\n"
            . "1. CREACIÓN DE POSTS EN EL TABLERO MENSUAL (MONTH BOARD / CALENDARIO):\n"
            . "Si el usuario te pide crear publicaciones, una grilla de contenidos, o te dice explícitamente 'ponlo en el month_board' o 'crea los posts en el calendario / mes':\n"
            . "Incluye al final un bloque de acción EXACTAMENTE así:\n"
            . "```romita-action:create_month_posts\n"
            . "{\n"
            . "  \"brand_name\": \"Nombre de la Marca (ej: Victoria Specialty Coffee)\",\n"
            . "  \"month_name\": \"Mes y Año (ej: Octubre 2026)\",\n"
            . "  \"posts\": [\n"
            . "    {\n"
            . "      \"concept\": \"Concepto claro del post (ej: Reel ASMR: Día Internacional del Café)\",\n"
            . "      \"copy_text\": \"Copy persuasivo con emojis, ganchos y llamados a la acción\",\n"
            . "      \"post_date\": \"YYYY-MM-DD\",\n"
            . "      \"platform\": \"Instagram, TikTok\",\n"
            . "      \"post_type\": \"Reel\",\n"
            . "      \"content_pillar\": \"Branding\",\n"
            . "      \"design_brief\": \"Pauta visual o notas para producción\",\n"
            . "      \"status\": \"Borrador\"\n"
            . "    }\n"
            . "  ]\n"
            . "}\n"
            . "```\n"
            . "Esto inyectará los posts DIRECTAMENTE en la tabla del Month Board de esa marca y le dará al usuario un botón de 1 clic para verlos en el tablero mensual.\n\n"
            . "2. CREACIÓN DE TAREAS OPERATIVAS EN TAREAS & OBJETIVOS (TASK MANAGER):\n"
            . "Si el usuario te pide tareas operativas, pendientes, checklists o metas de equipo (que NO sean posts de redes):\n"
            . "Incluye al final un bloque exactamente así:\n"
            . "```romita-action:create_tasks\n"
            . "{\n"
            . "  \"area\": \"general\",\n"
            . "  \"tasks\": [\n"
            . "    {\"title\": \"Nombre claro y directo\", \"description\": \"Detalles breves del entregable\", \"due_date\": \"YYYY-MM-DD\", \"priority\": \"urgent|high|medium|low\", \"area\": \"general\"}\n"
            . "  ]\n"
            . "}\n"
            . "```\n"
            . "Esto guardará las tareas en el módulo 'Tareas & Objetivos' (task_manager). NOTA: NO existe ningún módulo llamado 'Centro de Tareas' ni uses nunca 'module=tasks'.\n\n"
            . "3. CREACIÓN DE FORMULARIOS Y BRIEFS EN EL MÓDULO DE FORMULARIOS (FORMS MODULE):\n"
            . "Si el usuario te pide crear un formulario, brief de cliente, encuesta de satisfacción, diagnóstico, cuestionario o evaluación usando el módulo de formularios (o te dice 'crea un formulario'):\n"
            . "Diseña la estructura completa del formulario con preguntas inteligentes y añade al final de tu respuesta EXACTAMENTE este bloque de acción:\n"
            . "```romita-action:create_form\n"
            . "{\n"
            . "  \"title\": \"Título profesional y claro del Formulario\",\n"
            . "  \"description\": \"Instrucción concisa y motivadora para los usuarios que responderán\",\n"
            . "  \"status\": \"active\",\n"
            . "  \"settings\": {\n"
            . "    \"view_style\": \"hero_cover\",\n"
            . "    \"cover_image\": \"gradient_aurora\",\n"
            . "    \"multi_step\": true,\n"
            . "    \"welcome_screen\": true,\n"
            . "    \"require_name\": true,\n"
            . "    \"require_email\": true,\n"
            . "    \"show_logo\": true\n"
            . "  },\n"
            . "  \"fields\": [\n"
            . "    {\n"
            . "      \"type\": \"divider\",\n"
            . "      \"label\": \"Nombre de la Sección (Paso 1)\"\n"
            . "    },\n"
            . "    {\n"
            . "      \"type\": \"text\",\n"
            . "      \"label\": \"¿Cuál es el nombre de tu empresa o marca?\",\n"
            . "      \"placeholder\": \"Ej: Victoria Specialty Coffee\",\n"
            . "      \"required\": true\n"
            . "    },\n"
            . "    {\n"
            . "      \"type\": \"textarea\",\n"
            . "      \"label\": \"Describe la propuesta de valor o desafío principal\",\n"
            . "      \"placeholder\": \"Escribe aquí los detalles...\",\n"
            . "      \"required\": true\n"
            . "    },\n"
            . "    {\n"
            . "      \"type\": \"select\",\n"
            . "      \"label\": \"Selecciona tu presupuesto aproximado\",\n"
            . "      \"options\": [\"Menos de $1,000\", \"$1,000 - $3,000\", \"Más de $3,000\"],\n"
            . "      \"required\": true\n"
            . "    },\n"
            . "    {\n"
            . "      \"type\": \"icon_card\",\n"
            . "      \"label\": \"¿Cuál es tu objetivo prioritario?\",\n"
            . "      \"icon_options\": [\n"
            . "        {\"icon\": \"ph-rocket\", \"text\": \"Lanzamiento Rápido\"},\n"
            . "        {\"icon\": \"ph-megaphone\", \"text\": \"Captación de Clientes\"},\n"
            . "        {\"icon\": \"ph-sparkle\", \"text\": \"Rebranding Total\"}\n"
            . "      ],\n"
            . "      \"required\": true\n"
            . "    },\n"
            . "    {\n"
            . "      \"type\": \"range\",\n"
            . "      \"label\": \"Nivel de urgencia del proyecto\",\n"
            . "      \"range_min\": 1,\n"
            . "      \"range_max\": 5,\n"
            . "      \"range_label_min\": \"Bajo\",\n"
            . "      \"range_label_max\": \"Urgente\",\n"
            . "      \"required\": false\n"
            . "    }\n"
            . "  ]\n"
            . "}\n"
            . "```\n"
            . "Tipos permitidos para 'type': 'text' (corto), 'textarea' (párrafo), 'email', 'phone', 'date', 'select' (opciones únicas), 'checkbox' (casillas múltiples), 'dropdown' (menú), 'file' (archivos), 'range' (escala), 'number_range' (rango numérico), 'color' (paleta), 'icon_card' (cards con íconos), 'divider' (separador/paso multi-step).\n"
            . "Esto insertará el formulario DIRECTAMENTE en el Módulo de Formularios de Roma Agencia y le dará al usuario un botón de 1 clic para crearlo y obtener su enlace público inmediato.\n\n"
            . "2. AGENDAMIENTO DE REUNIÓN:\n"
            . "Si se acuerda, coordina o propone una reunión o sesión de trabajo, incluye al final:\n"
            . "```romita-action:schedule_meeting\n"
            . "{\n"
            . "  \"motivo\": \"Motivo conciso de la reunión\",\n"
            . "  \"fecha_hora\": \"YYYY-MM-DD HH:MM:00\",\n"
            . "  \"meet_link\": \"https://meet.google.com/new\",\n"
            . "  \"resumen\": \"Breve resumen de acuerdos previos o temas a tratar\"\n"
            . "}\n"
            . "```\n\n"
            . "3. MENSAJE / MINUTA PARA WHATSAPP:\n"
            . "Si redactas un resumen de reunión, minuta, propuesta o mensaje para enviar al cliente por WhatsApp, incluye al final:\n"
            . "```romita-action:whatsapp_message\n"
            . "{\n"
            . "  \"recipient_name\": \"Nombre del cliente o contacto\",\n"
            . "  \"message\": \"Texto completo formateado con emojis y negritas de WhatsApp (*negrita*)\"\n"
            . "}\n"
            . "```\n\n"
            . "4. POSTS INTERACTIVOS DE REDES SOCIALES:\n"
            . "Cuando el usuario te pida redactar un post para Instagram, LinkedIn, TikTok o Facebook, además de tu explicación y recomendaciones, incluye un bloque interactivo de previsualización así:\n"
            . "```romita-action:social_post\n"
            . "{\n"
            . "  \"platform\": \"instagram\",\n"
            . "  \"account\": \"@romaagencia\",\n"
            . "  \"hook\": \"Gancho impactante de apertura\",\n"
            . "  \"caption\": \"Cuerpo del post formateado con saltos de línea y emojis\",\n"
            . "  \"hashtags\": [\"#MarketingDigital\", \"#Branding\", \"#RomaAgencia\"],\n"
            . "  \"cta\": \"Llamado a la acción claro\"\n"
            . "}\n"
            . "```\n\n"
            . "5. TABS DE VARIACIONES DE COPY / TITULARES (A/B/C):\n"
            . "Cuando el usuario te pida varias opciones, ángulos, ganchos o alternativas de copy o titulares para comparar, incluye un bloque así:\n"
            . "```romita-action:copy_variations\n"
            . "{\n"
            . "  \"topic\": \"Titular o Tema evaluado\",\n"
            . "  \"variations\": [\n"
            . "    {\"label\": \"Opción A: Persuasiva\", \"badge\": \"Alta Conversión\", \"text\": \"Texto de la propuesta A...\"},\n"
            . "    {\"label\": \"Opción B: Urgencia / FOMO\", \"badge\": \"Urgencia\", \"text\": \"Texto de la propuesta B...\"},\n"
            . "    {\"label\": \"Opción C: Storytelling\", \"badge\": \"Emocional\", \"text\": \"Texto de la propuesta C...\"}\n"
            . "  ]\n"
            . "}\n"
            . "```\n\n"
            . "6. ARTEFACTO / CANVAS PARA DOCUMENTOS COMPLETOS:\n"
            . "Si redactas un plan completo, estrategia detallada, propuesta formal, brief exhaustivo o código largo, puedes encapsular el documento para abrirlo en el panel Canvas de Roma SaaS usando:\n"
            . "```romita-canvas:title=\"Título del Documento\"\n"
            . "(Contenido completo en Markdown)\n"
            . "```\n\n"
            . "7. GENERADOR DE PROMPTS VISUALES PARA IA (MIDJOURNEY, IMAGEN 3, FLUX):\n"
            . "Cuando el usuario te pida un prompt para generar imágenes, arte conceptual, fotografía publicitaria, mockups o ideas visuales con IA, incluye al final un bloque exactamente así:\n"
            . "```romita-action:image_prompt\n"
            . "{\n"
            . "  \"concept\": \"Título claro del concepto visual\",\n"
            . "  \"prompt_en\": \"Cinematic 8k photograph of latin executive woman, softbox studio rim lighting, 85mm lens, f/1.8, photorealistic --ar 16:9 --style raw --v 6.0\",\n"
            . "  \"negative_prompt\": \"blurry, low quality, distorted text, ugly, bad anatomy\",\n"
            . "  \"style\": \"Fotografía Editorial / Publicitaria\",\n"
            . "  \"ratio\": \"16:9\",\n"
            . "  \"lighting\": \"Luz suave de estudio + recorte lateral\",\n"
            . "  \"engine\": \"Midjourney v6\"\n"
            . "}\n"
            . "```\n\n"
            . "8. AUDITORÍA VISUAL Y ANÁLISIS MULTIMODAL:\n"
            . "Cuando el usuario adjunte una imagen o diseño publicitario:\n"
            . "- Actúa como Directora Creativa y de Arte Senior.\n"
            . "- Evalúa con criterio técnico: Contraste y legibilidad de textos, punto focal, armonía cromática, pesos visuales y jerarquía tipográfica.\n"
            . "- Evalúa zonas seguras para redes (evitar textos tapados por la interfaz de Reels/TikTok 9:16 o avatar/comentarios en historias).\n"
            . "- Si te piden extraer texto (OCR), transcríbelo con total fidelidad y sin inventar palabras.\n"
            . "- Ofrece recomendaciones accionables y directas para elevar la tasa de clics y la calidad estética.\n\n"
            . "REGLA OBLIGATORIA DE ACCIONES Y TAREAS:\n"
            . "1. Cuando el usuario te pida crear o listar tareas para el Kanban, pendientes o entregables, NUNCA devuelvas bloques de código estándar como ```json ni código ```html. Usa SIEMPRE el bloque interactivo ```romita-action:create_tasks.\n"
            . "2. En los bloques de acción escribe ÚNICAMENTE el JSON puro dentro de ```romita-action:...``` (o el Markdown dentro de ```romita-canvas:...```). El sistema Roma SaaS toma automáticamente este bloque y muestra en pantalla los componentes interactivos ('Crear en Kanban', 'Abrir en Modal') para el usuario.\n"
            . "3. Fuera del bloque de acción, explica tu propuesta con tu elocuencia, calidez y visión estratégica habitual.";
        $sysInstructions[] = $agenticInstructions;

        // FASE 3: Lector de Enlaces Web en Vivo
        if (preg_match('/https?:\/\/[^\s<>"\'\)]+/i', $message, $urlMatches)) {
            $detectedUrl = $urlMatches[0];
            $liveWebContent = fetchLiveUrlContent($detectedUrl);
            if (!empty($liveWebContent)) {
                $sysInstructions[] = "--- CONTENIDO EN VIVO EXTRAÍDO DE LA URL ({$detectedUrl}) ---\n"
                    . $liveWebContent . "\n"
                    . "INSTRUCCIÓN SOBRE LA URL: Analiza este contenido real para responder a la consulta del usuario con exactitud ejecutiva.";
            }
        }

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

        // Si hay un Prept asociado, leer sus datos, manual de tono, arquetipo y palabras prohibidas (Fase 4: Brand Memories)
        $prept_id = $_POST['prept_id'] ?? null;
        if ($prept_id) {
            $stmt = $db->prepare("SELECT name, tone, archetype, audience, rules, forbidden_words FROM romita_prepts WHERE id = ?");
            $stmt->execute([$prept_id]);
            $prept = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($prept) {
                $preptCtx = "=== MANUAL DE TONO & MEMORIA DE MARCA: '{$prept['name']}' ===\n";
                if (!empty($prept['archetype'])) $preptCtx .= "- Arquetipo de Marca: {$prept['archetype']}\n";
                if (!empty($prept['tone'])) $preptCtx .= "- Tono de Voz: {$prept['tone']}\n";
                if (!empty($prept['audience'])) $preptCtx .= "- Audiencia Objetivo / Buyer Persona: {$prept['audience']}\n";
                if (!empty($prept['rules'])) $preptCtx .= "- Reglas de Estilo & Directrices: {$prept['rules']}\n";
                if (!empty($prept['forbidden_words'])) $preptCtx .= "- PALABRAS ESTRICTAMENTE PROHIBIDAS (NUNCA usar en copys ni propuestas): {$prept['forbidden_words']}\n";
                
                $stmt2 = $db->prepare("SELECT topic, content_summary, created_at FROM romita_prept_content WHERE prept_id = ? ORDER BY created_at DESC LIMIT 10");
                $stmt2->execute([$prept_id]);
                $pastContents = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($pastContents) > 0) {
                    $preptCtx .= "\nHISTORIAL DE CONTENIDOS PREVIOS (NO REPETIR ESTOS TEMAS):\n";
                    foreach($pastContents as $pc) {
                        $preptCtx .= "- Fecha: {$pc['created_at']}, Tema: {$pc['topic']}, Resumen: {$pc['content_summary']}\n";
                    }
                }
                
                $sysInstructions[] = $preptCtx;
            }
        }

        // Memoria de Preferencias del Usuario (Fase 4)
        try {
            $stmtPref = $db->prepare("SELECT response_style, custom_instructions FROM romita_user_preferences WHERE user_id = ?");
            $stmtPref->execute([$user_id]);
            $userPref = $stmtPref->fetch(PDO::FETCH_ASSOC);
            if ($userPref) {
                $prefGuidelines = [];
                $style = $userPref['response_style'] ?? 'strategic';
                if ($style === 'executive') {
                    $prefGuidelines[] = "PREFERENCIA DE RESPUESTA DEL USUARIO: Modo Ejecutivo y Breve. Respuestas sintéticas, directas al grano, viñetas operativas sin rodeos.";
                } elseif ($style === 'creative') {
                    $prefGuidelines[] = "PREFERENCIA DE RESPUESTA DEL USUARIO: Modo Creativo & Publicitario. Enfoque persuasivo, storytelling cautivador, ganchos dinámicos y lenguaje moderno de agencia creativa.";
                } else {
                    $prefGuidelines[] = "PREFERENCIA DE RESPUESTA DEL USUARIO: Modo Estratégico 360°. Presenta marcos estructurados, tablas comparativas, métricas de impacto y justificación metodológica.";
                }
                if (!empty($userPref['custom_instructions'])) {
                    $prefGuidelines[] = "INSTRUCCIONES PERMANENTES DEL USUARIO (MEMORIA):\n" . trim($userPref['custom_instructions']);
                }
                if (!empty($prefGuidelines)) {
                    $sysInstructions[] = implode("\n", $prefGuidelines);
                }
            }
        } catch(Exception $e) {}

        // Detección de Modo Role-Play de Objeciones (Fase 4)
        $msgLowerForRoleplay = mb_strtolower($message);
        if (strpos($msgLowerForRoleplay, 'role-play') !== false || strpos($msgLowerForRoleplay, 'roleplay') !== false || strpos($msgLowerForRoleplay, 'objecion') !== false || strpos($msgLowerForRoleplay, 'objeción') !== false) {
            $sysInstructions[] = "MODO ROLE-PLAY DE VENTAS ACTIVO: Actúa como un cliente exigente y escéptico. Plantea objeciones reales de negocios (precio, tiempo, satisfacción actual). Tras la respuesta del usuario, evalúa su técnica de 1 a 10 con feedback breve y lanza una contra-objeción más retadora para entrenarlo.";
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

        // 4. Inferencia de Inteligencia Artificial (Motor Híbrido: Groq Open Source + Gemini Fallback)
        $ia_response = "";
        $lastError = "No se pudo conectar con la IA de Romita.";
        $lastHttpCode = 0;

        // 4A. Motor Principal: Groq Cloud (Modelos Open Source: Qwen / Llama 3)
        $stmtGroq = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'groq_api_key'");
        $groqApiKey = $stmtGroq ? trim($stmtGroq->fetchColumn() ?: '') : '';
        if (empty($groqApiKey)) {
            $groqApiKey = trim(getenv('GROQ_API_KEY') ?: '');
        }

        // Si no hay imagen binaria que requiera visión obligatoria, intentar primero con Groq (ultra-rápido y gratis)
        if (!empty($groqApiKey) && empty($attachment_base64)) {
            $groqMessages = [];
            if (!empty($sysInstructions)) {
                $groqMessages[] = [
                    'role' => 'system',
                    'content' => implode("\n\n---\n\n", $sysInstructions)
                ];
            }
            foreach ($alternatedTurns as $idx => $turn) {
                $groqRole = ($turn['role'] === 'model') ? 'assistant' : 'user';
                $turnText = $turn['text'];
                if ($idx === $lastAlternatedIdx && !empty($attachment_text)) {
                    $turnText .= "\n\n[CONTENIDO DE ARCHIVO ADJUNTO: {$attachment_name}]\n" . $attachment_text;
                }
                $groqMessages[] = [
                    'role' => $groqRole,
                    'content' => $turnText
                ];
            }

            $groqModels = [
                'qwen/qwen3.8-27b',
                'llama-3.3-70b-versatile',
                'llama-3.1-8b-instant',
                'openai/gpt-oss-120b',
                'openai/gpt-oss-20b'
            ];

            foreach ($groqModels as $gModel) {
                $chGroq = curl_init('https://api.groq.com/openai/v1/chat/completions');
                curl_setopt($chGroq, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chGroq, CURLOPT_POST, true);
                curl_setopt($chGroq, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $groqApiKey,
                    'Content-Type: application/json'
                ]);
                $groqPayload = [
                    'model' => $gModel,
                    'messages' => $groqMessages,
                    'temperature' => 0.6,
                    'max_tokens' => 1200
                ];
                curl_setopt($chGroq, CURLOPT_POSTFIELDS, json_encode($groqPayload));
                curl_setopt($chGroq, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($chGroq, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                curl_setopt($chGroq, CURLOPT_CONNECTTIMEOUT, 6);
                curl_setopt($chGroq, CURLOPT_TIMEOUT, 20);

                $groqRes = curl_exec($chGroq);
                $groqHttp = curl_getinfo($chGroq, CURLINFO_HTTP_CODE);
                $groqErr = curl_error($chGroq);
                curl_close($chGroq);

                if ($groqHttp === 200 && !empty($groqRes)) {
                    $groqData = json_decode($groqRes, true);
                    $groqText = trim($groqData['choices'][0]['message']['content'] ?? '');
                    if (!empty($groqText)) {
                        $ia_response = $groqText;
                        break;
                    }
                }
            }
        }

        // 4B. Motor Secundario / Fallback: Google Gemini API (con multi-key y multi-model)
        $apiKeysToTry = [];
        if (empty($ia_response)) {
            $stmtKey = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
            $dbApiKeyRaw = $stmtKey ? trim($stmtKey->fetchColumn() ?: '') : '';
            
            if (!empty($dbApiKeyRaw)) {
                $splitKeys = preg_split('/[\r\n,;]+/', $dbApiKeyRaw);
                foreach ($splitKeys as $k) {
                    $k = trim($k);
                    if (!empty($k)) $apiKeysToTry[] = $k;
                }
            }
            $envKey = trim(getenv('GEMINI_API_KEY') ?: '');
            if (!empty($envKey)) {
                $apiKeysToTry[] = $envKey;
            }
            $apiKeysToTry = array_values(array_unique($apiKeysToTry));

            // Modelos ordenados por disponibilidad y estabilidad comprobada
            $modelsToTry = [
                'gemini-flash-latest',
                'gemini-2.5-flash',
                'gemini-2.0-flash',
                'gemini-1.5-flash',
                'gemini-3.6-flash',
                'gemini-3.8-flash',
                'gemini-3.7-flash',
                'gemini-3.5-flash',
                'gemini-3.5-flash-lite',
                'gemini-3.1-flash-lite'
            ];

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

                    $lastHttpCode = $httpcode;

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
                        if ($httpcode === 404 || $httpcode === 503) {
                            continue;
                        }
                        if ($httpcode === 429) {
                            break;
                        }
                        if ($httpcode === 400 || $httpcode === 401 || $httpcode === 403) {
                            break;
                        }
                    }
                }
            }
        }

        if (empty($ia_response)) {
            if (empty($apiKeysToTry) && empty($groqApiKey)) {
                // Modo contingencia: No hay ninguna clave configurada
                $ia_response = "👋 **¡Hola! Soy Romita**, asistente estratégica y creativa de Roma Agencia.\n\n" .
                    "Actualmente estoy operando en **modo autónomo local** porque las **API Keys de Groq / Gemini** aún no están configuradas en el sistema.\n\n" .
                    "### 🚀 ¿Cómo activarme al 100% con IA en vivo?\n" .
                    "1. Configura tu clave gratuita de **Groq** en [console.groq.com](https://console.groq.com) o de **Gemini** en [Google AI Studio](https://aistudio.google.com/).\n" .
                    "2. Ve a **Ajustes > IA** en el sistema y pega tu clave allí.\n\n" .
                    "⚡ *Una vez guardada la clave, podré atender consultas en tiempo real sin límites y a máxima velocidad.*";
            } elseif ($lastHttpCode === 429) {
                // Límite de tasa por minuto de Gemini Free Tier
                $ia_response = "⏳ **Límite de consultas temporalmente alcanzado**\n\n" .
                    "La cuota por minuto se ha completado temporalmente.\n\n" .
                    "💡 **Solución rápida:** Espera 15 a 30 segundos y vuelve a enviar tu mensaje. Si tu equipo utiliza Romita con mucha frecuencia, puedes registrar una segunda clave en **Ajustes > IA** para balancear la carga.";
            } elseif ($lastHttpCode === 503) {
                // Sobrecarga general temporal
                $ia_response = "⚡ **Alta demanda en los servidores de IA**\n\n" .
                    "Los servidores de IA están experimentando un pico de demanda momentáneo.\n\n" .
                    "Por favor intenta reenviar tu consulta en unos momentos.";
            } else {
                // Error descriptivo
                $ia_response = "⚠️ **No se pudo procesar la respuesta con el motor de IA** (" . htmlspecialchars($lastError) . ").\n\n" .
                    "Por favor verifica el estado de tus claves de API en **Ajustes > IA** o intenta enviar tu consulta nuevamente.";
            }
        }

        // Insertar msj IA
        $stmt_ai_msg = $db->prepare("INSERT INTO romita_messages (chat_id, role, content) VALUES (?, 'assistant', ?)");
        $stmt_ai_msg->execute([$chat_id, $ia_response]);
        $ai_msg_id = (int)$db->lastInsertId();

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

        echo json_encode([
            'success' => true,
            'response' => $ia_response,
            'chat_id' => $chat_id,
            'message_id' => $ai_msg_id,
            'attachment_url' => $attachment_url,
            'attachment_type' => $attachment_type,
            'attachment_name' => $attachment_name
        ]);
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

        try {
            $stmt = $db->prepare("SELECT id, role, content, feedback, attachment_url, attachment_type, attachment_name FROM romita_messages WHERE chat_id = ? ORDER BY id ASC");
            $stmt->execute([$chat_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $pe) {
            ensureRomitaSchema($db);
            $stmt = $db->prepare("SELECT id, role, content FROM romita_messages WHERE chat_id = ? ORDER BY id ASC");
            $stmt->execute([$chat_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit();
    }

    // Calificar mensaje de Romita (👍 / 👎 Feedback Loop)
    if ($action === 'feedback_message') {
        $message_id = (int)($_POST['message_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0); // 1 = me sirvió, -1 = no me sirvió
        if ($message_id > 0) {
            $stmt = $db->prepare("UPDATE romita_messages SET feedback = ? WHERE id = ?");
            $stmt->execute([$rating, $message_id]);
            echo json_encode(['success' => true]);
            exit();
        }
        echo json_encode(['success' => false, 'error' => 'ID de mensaje inválido']);
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
        $name = trim($_POST['name'] ?? '');
        $tone = trim($_POST['tone'] ?? '');
        $archetype = trim($_POST['archetype'] ?? '');
        $audience = trim($_POST['audience'] ?? '');
        $rules = trim($_POST['rules'] ?? '');
        $forbidden_words = trim($_POST['forbidden_words'] ?? '');

        if(empty($name)) {
            echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio']);
            exit();
        }

        if ($id) {
            $stmt = $db->prepare("UPDATE romita_prepts SET name=?, tone=?, archetype=?, audience=?, rules=?, forbidden_words=? WHERE id=?");
            $stmt->execute([$name, $tone, $archetype, $audience, $rules, $forbidden_words, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO romita_prepts (name, tone, archetype, audience, rules, forbidden_words) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $tone, $archetype, $audience, $rules, $forbidden_words]);
        }
        
        echo json_encode(['success' => true]);
        exit();
    }

    // =========================================================================
    // FASE 4: MEMORIA, PERSONALIZACIÓN Y COLABORACIÓN
    // =========================================================================

    // 1. Obtener y Guardar Memoria de Marca (Brand Memory)
    if ($action === 'get_brand_memory') {
        $prept_id = (int)($_POST['prept_id'] ?? 0);
        if (!$prept_id) {
            echo json_encode(['success' => false, 'error' => 'ID de marca no proporcionado']);
            exit();
        }
        $stmt = $db->prepare("SELECT id, name, tone, archetype, audience, rules, forbidden_words FROM romita_prepts WHERE id = ?");
        $stmt->execute([$prept_id]);
        $memory = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'memory' => $memory]);
        exit();
    }

    if ($action === 'save_brand_memory') {
        $prept_id = (int)($_POST['prept_id'] ?? 0);
        $archetype = trim($_POST['archetype'] ?? '');
        $tone = trim($_POST['tone'] ?? '');
        $audience = trim($_POST['audience'] ?? '');
        $rules = trim($_POST['rules'] ?? '');
        $forbidden_words = trim($_POST['forbidden_words'] ?? '');

        if (!$prept_id) {
            echo json_encode(['success' => false, 'error' => 'ID de marca no válido']);
            exit();
        }

        $stmt = $db->prepare("UPDATE romita_prepts SET archetype=?, tone=?, audience=?, rules=?, forbidden_words=? WHERE id=?");
        $stmt->execute([$archetype, $tone, $audience, $rules, $forbidden_words, $prept_id]);
        echo json_encode(['success' => true, 'message' => 'Manual de tono y memoria de marca actualizados.']);
        exit();
    }

    // 2. Memoria de Preferencias del Usuario (Estilo de respuesta, instrucciones permanentes)
    if ($action === 'get_user_preferences') {
        $stmt = $db->prepare("SELECT response_style, custom_instructions, default_specialty, sound_enabled FROM romita_user_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prefs) {
            $prefs = [
                'response_style' => 'strategic',
                'custom_instructions' => '',
                'default_specialty' => 'director_360',
                'sound_enabled' => 1
            ];
        }
        echo json_encode(['success' => true, 'preferences' => $prefs]);
        exit();
    }

    if ($action === 'save_user_preferences') {
        $response_style = trim($_POST['response_style'] ?? 'strategic');
        $custom_instructions = trim($_POST['custom_instructions'] ?? '');
        $default_specialty = trim($_POST['default_specialty'] ?? 'director_360');
        $sound_enabled = isset($_POST['sound_enabled']) ? (int)$_POST['sound_enabled'] : 1;

        $stmt = $db->prepare("
            INSERT INTO romita_user_preferences (user_id, response_style, custom_instructions, default_specialty, sound_enabled, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                response_style = VALUES(response_style),
                custom_instructions = VALUES(custom_instructions),
                default_specialty = VALUES(default_specialty),
                sound_enabled = VALUES(sound_enabled),
                updated_at = NOW()
        ");
        $stmt->execute([$user_id, $response_style, $custom_instructions, $default_specialty, $sound_enabled]);
        echo json_encode(['success' => true, 'message' => 'Tus preferencias de memoria se guardaron exitosamente.']);
        exit();
    }

    // 3. Biblioteca de Super-Prompts de la Agencia
    if ($action === 'get_super_prompts') {
        $category = trim($_POST['category'] ?? '');
        $query = "SELECT id, user_id, title, category, prompt, description, icon, is_agency_template, created_at FROM romita_super_prompts WHERE (is_agency_template = 1 OR user_id = ?)";
        $params = [$user_id];
        if (!empty($category) && $category !== 'all') {
            $query .= " AND category = ?";
            $params[] = $category;
        }
        $query .= " ORDER BY is_agency_template DESC, id DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $prompts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'prompts' => $prompts]);
        exit();
    }

    if ($action === 'save_super_prompt') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Estrategia');
        $prompt = trim($_POST['prompt'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'ph-sparkle');
        $is_agency = ($is_admin && !empty($_POST['is_agency_template'])) ? 1 : 0;

        if (!$title || !$prompt) {
            echo json_encode(['success' => false, 'error' => 'El título y el prompt son obligatorios']);
            exit();
        }

        $stmt = $db->prepare("INSERT INTO romita_super_prompts (user_id, title, category, prompt, description, icon, is_agency_template, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $title, $category, $prompt, $desc, $icon, $is_agency]);
        $newId = $db->lastInsertId();

        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Super-Prompt guardado en la biblioteca.']);
        exit();
    }

    if ($action === 'delete_super_prompt') {
        $promptId = (int)($_POST['id'] ?? 0);
        if (!$promptId) {
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit();
        }

        if ($is_admin) {
            $stmt = $db->prepare("DELETE FROM romita_super_prompts WHERE id = ?");
            $stmt->execute([$promptId]);
        } else {
            $stmt = $db->prepare("DELETE FROM romita_super_prompts WHERE id = ? AND user_id = ?");
            $stmt->execute([$promptId, $user_id]);
        }
        echo json_encode(['success' => true]);
        exit();
    }

    // 4. Compartir Chat con un Compañero (Generar Enlace Seguro)
    if ($action === 'generate_share_link') {
        $chat_id = (int)($_POST['chat_id'] ?? 0);
        if (!$chat_id) {
            echo json_encode(['success' => false, 'error' => 'Chat ID no proporcionado']);
            exit();
        }

        // Verificar pertenencia o admin
        $stmtCheck = $db->prepare("SELECT id, share_token, title FROM romita_chats WHERE id = ? AND (user_id = ? OR ? = 1)");
        $stmtCheck->execute([$chat_id, $user_id, $is_admin ? 1 : 0]);
        $chatRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$chatRow) {
            echo json_encode(['success' => false, 'error' => 'No se encontró la conversación o no tienes permisos.']);
            exit();
        }

        $shareToken = $chatRow['share_token'];
        if (empty($shareToken)) {
            $shareToken = bin2hex(random_bytes(16));
            $stmtUp = $db->prepare("UPDATE romita_chats SET share_token = ? WHERE id = ?");
            $stmtUp->execute([$shareToken, $chat_id]);
        }

        // Construir URL completa
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = dirname($_SERVER['PHP_SELF']); // /ajax o similar
        $appPath = preg_replace('/\/ajax$/', '', $baseUrl);
        $shareUrl = "{$protocol}://{$host}{$appPath}/index.php?module=romita&share={$shareToken}";

        echo json_encode([
            'success' => true,
            'share_token' => $shareToken,
            'share_url' => $shareUrl,
            'title' => $chatRow['title']
        ]);
        exit();
    }

    // 5. Obtener Chat Compartido por Token
    if ($action === 'get_shared_chat') {
        $token = trim($_POST['share_token'] ?? '');
        if (!$token) {
            echo json_encode(['success' => false, 'error' => 'Token no proporcionado']);
            exit();
        }

        $stmt = $db->prepare("
            SELECT rc.id, rc.title, rc.created_at, u.name as author_name 
            FROM romita_chats rc 
            JOIN users u ON rc.user_id = u.id 
            WHERE rc.share_token = ?
        ");
        $stmt->execute([$token]);
        $chat = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$chat) {
            echo json_encode(['success' => false, 'error' => 'El chat compartido no existe o fue eliminado.']);
            exit();
        }

        try {
            $stmtMsgs = $db->prepare("
                SELECT id, role, content, feedback, attachment_url, attachment_type, attachment_name, created_at 
                FROM romita_messages 
                WHERE chat_id = ? 
                ORDER BY id ASC
            ");
            $stmtMsgs->execute([$chat['id']]);
            $messages = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $pe) {
            ensureRomitaSchema($db);
            $stmtMsgs = $db->prepare("
                SELECT id, role, content, created_at 
                FROM romita_messages 
                WHERE chat_id = ? 
                ORDER BY id ASC
            ");
            $stmtMsgs->execute([$chat['id']]);
            $messages = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'chat' => $chat,
            'messages' => $messages
        ]);
        exit();
    }

    // =========================================================================
    // ACCIONES AGÉNTICAS CON 1 CLIC (ROMA ACTIONS EXECUTION)
    // =========================================================================

    // 1. Crear Publicaciones en Month Board (Tablero Mensual)
    if ($action === 'tool_create_month_posts') {
        try {
            $brandName = trim($_POST['brand_name'] ?? '');
            $monthId = (int)($_POST['month_id'] ?? 0);
            $projectId = (int)($_POST['project_id'] ?? 0);
            $postsRaw = $_POST['posts'] ?? '';
            $posts = is_array($postsRaw) ? $postsRaw : json_decode($postsRaw, true);

            if (empty($posts) || !is_array($posts)) {
                echo json_encode(['success' => false, 'error' => 'No se recibieron publicaciones válidas para registrar en el Month Board.']);
                exit();
            }

            // Si no se proporcionó month_id, resolverlo por proyecto o por nombre de marca
            if (!$monthId) {
                if (!$projectId && !empty($brandName)) {
                    $stmtP = $db->prepare("
                        SELECT p.id 
                        FROM projects p 
                        JOIN work_orders wo ON p.work_order_id = wo.id 
                        WHERE wo.brand_name LIKE ? 
                        ORDER BY p.id DESC LIMIT 1
                    ");
                    $stmtP->execute(['%' . $brandName . '%']);
                    $projectId = (int)$stmtP->fetchColumn();
                }

                if ($projectId) {
                    // Verificar si hay fecha en el primer post para vincular al mes y año correspondiente
                    $firstPostDate = $posts[0]['post_date'] ?? '';
                    $targetMonth = 0;
                    $targetYear = 0;
                    if (!empty($firstPostDate) && preg_match('/^(\d{4})-(\d{2})/', $firstPostDate, $mMatch)) {
                        $targetYear = (int)$mMatch[1];
                        $targetMonth = (int)$mMatch[2];
                    }

                    if ($targetMonth > 0 && $targetYear > 0) {
                        $stmtM = $db->prepare("SELECT id FROM project_months WHERE project_id = ? AND month = ? AND year = ?");
                        $stmtM->execute([$projectId, $targetMonth, $targetYear]);
                        $monthId = (int)$stmtM->fetchColumn();

                        // Si el mes aún no ha sido creado en el proyecto, generarlo automáticamente
                        if (!$monthId) {
                            $stmtNewM = $db->prepare("INSERT INTO project_months (project_id, month, year, status, created_at, updated_at) VALUES (?, ?, ?, 'pendiente', NOW(), NOW())");
                            $stmtNewM->execute([$projectId, $targetMonth, $targetYear]);
                            $monthId = (int)$db->lastInsertId();
                        }
                    }

                    // Si no se pudo determinar por fecha, vincular al mes activo/reciente del proyecto
                    if (!$monthId) {
                        $stmtLatestM = $db->prepare("SELECT id FROM project_months WHERE project_id = ? ORDER BY year DESC, month DESC LIMIT 1");
                        $stmtLatestM->execute([$projectId]);
                        $monthId = (int)$stmtLatestM->fetchColumn();
                    }
                }
            }

            if (!$monthId) {
                echo json_encode(['success' => false, 'error' => 'No se encontró un tablero mensual (Month Board) activo para vincular estas publicaciones. Por favor selecciona el proyecto de la marca en el selector superior.']);
                exit();
            }

            // Obtener detalles del mes para el mensaje y redirección
            $stmtMInfo = $db->prepare("
                SELECT pm.id, pm.month, pm.year, wo.brand_name 
                FROM project_months pm 
                JOIN projects p ON pm.project_id = p.id 
                JOIN work_orders wo ON p.work_order_id = wo.id 
                WHERE pm.id = ?
            ");
            $stmtMInfo->execute([$monthId]);
            $mInfo = $stmtMInfo->fetch(PDO::FETCH_ASSOC);
            $resolvedBrand = $mInfo['brand_name'] ?? ($brandName ?: 'Marca');
            $resolvedMonthText = ($mInfo ? "Mes {$mInfo['month']}/{$mInfo['year']}" : "Mes #{$monthId}");

            $createdPostIds = [];
            $stmtInsPost = $db->prepare("
                INSERT INTO month_posts (month_id, post_date, concept, copy_text, platform, status, post_type, content_pillar, design_brief, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            foreach ($posts as $p) {
                $concept = trim($p['concept'] ?? ($p['title'] ?? ''));
                if (!$concept) continue;

                // Limpiar prefijo [MONTH_BOARD: ...] si Gemini lo incluyó en el título
                $conceptClean = preg_replace('/^\[MONTH_BOARD:[^\]]+\]\s*/i', '', $concept);

                $copy = trim($p['copy_text'] ?? ($p['caption'] ?? ($p['description'] ?? '')));
                $postDate = !empty($p['post_date']) ? $p['post_date'] : date('Y-m-d H:i:s');
                $platform = !empty($p['platform']) ? (is_array($p['platform']) ? implode(', ', $p['platform']) : $p['platform']) : 'Instagram';
                $status = !empty($p['status']) ? $p['status'] : 'Borrador';
                $postType = !empty($p['post_type']) ? $p['post_type'] : (!empty($p['format']) ? $p['format'] : 'Reel');
                $pillar = !empty($p['content_pillar']) ? $p['content_pillar'] : (!empty($p['pillar']) ? $p['pillar'] : 'Branding');
                $brief = trim($p['design_brief'] ?? ($p['hook'] ?? ''));

                $stmtInsPost->execute([$monthId, $postDate, $conceptClean, $copy, $platform, $status, $postType, $pillar, $brief]);
                $createdPostIds[] = $db->lastInsertId();
            }

            if (empty($createdPostIds)) {
                echo json_encode(['success' => false, 'error' => 'No se pudo crear ninguna publicación en el Month Board.']);
                exit();
            }

            // Sincronizar tareas si existe TaskSyncHelper
            try {
                require_once __DIR__ . '/../includes/TaskSyncHelper.php';
                TaskSyncHelper::syncMonthPostsCompletion($db, $monthId);
            } catch(\Throwable $eSync) {}

            echo json_encode([
                'success' => true,
                'count' => count($createdPostIds),
                'post_ids' => $createdPostIds,
                'month_id' => $monthId,
                'brand_name' => $resolvedBrand,
                'redirect_url' => "index.php?module=month_board&action=index&id={$monthId}",
                'message' => count($createdPostIds) . (count($createdPostIds) === 1 ? ' post inyectado' : ' posts inyectados') . " exitosamente en el Month Board de {$resolvedBrand} ({$resolvedMonthText})."
            ]);
            exit();
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Error al procesar la inserción en el Month Board: ' . $e->getMessage()]);
            exit();
        }
    }

    // 2. Crear Tareas en Tareas & Objetivos (Task Manager)
    if ($action === 'tool_create_tasks') {
        $tasksRaw = $_POST['tasks'] ?? '';
        $tasks = is_array($tasksRaw) ? $tasksRaw : json_decode($tasksRaw, true);

        if (empty($tasks) || !is_array($tasks)) {
            echo json_encode(['success' => false, 'error' => 'No se recibieron tareas válidas para registrar.']);
            exit();
        }

        // Auto-detección inteligente: si las tareas son en realidad publicaciones para el month_board (ej: tienen prefijo [MONTH_BOARD:)
        $isMonthBoardContent = false;
        foreach ($tasks as $chk) {
            $tTitle = $chk['title'] ?? ($chk['concept'] ?? '');
            if (stripos($tTitle, '[MONTH_BOARD') !== false || !empty($chk['post_type']) || !empty($chk['concept'])) {
                $isMonthBoardContent = true;
                break;
            }
        }

        if ($isMonthBoardContent) {
            // Re-enrutar limpiamente hacia Month Board
            $_POST['posts'] = $tasks;
            $_POST['brand_name'] = $_POST['brand_name'] ?? '';
            $_POST['month_id'] = $_POST['month_id'] ?? 0;
            $_POST['project_id'] = $_POST['project_id'] ?? 0;
            // Ejecutar lógica de month_board
            $firstT = $tasks[0]['title'] ?? '';
            if (preg_match('/\[MONTH_BOARD:\s*([^-\]]+)/i', $firstT, $bMatch)) {
                if (empty($_POST['brand_name'])) $_POST['brand_name'] = trim($bMatch[1]);
            }
            // Invocar el bloque de month_board directamente
            $action = 'tool_create_month_posts';
            // Ejecutar tool_create_month_posts reinyectando
            $stmtP = $db->prepare("SELECT p.id FROM projects p JOIN work_orders wo ON p.work_order_id = wo.id WHERE wo.brand_name LIKE ? ORDER BY p.id DESC LIMIT 1");
            $stmtP->execute(['%' . ($_POST['brand_name'] ?? 'Victoria') . '%']);
            $resolvedProjId = (int)$stmtP->fetchColumn();

            $stmtLatestM = $db->prepare("SELECT id FROM project_months WHERE project_id = ? ORDER BY year DESC, month DESC LIMIT 1");
            $stmtLatestM->execute([$resolvedProjId]);
            $resolvedMonthId = (int)$stmtLatestM->fetchColumn();

            if ($resolvedMonthId > 0) {
                $createdPostIds = [];
                $stmtInsPost = $db->prepare("
                    INSERT INTO month_posts (month_id, post_date, concept, copy_text, platform, status, post_type, content_pillar, design_brief, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 'Instagram, TikTok', 'Borrador', 'Reel', 'Branding', ?, NOW(), NOW())
                ");

                foreach ($tasks as $p) {
                    $rawTitle = $p['title'] ?? ($p['concept'] ?? '');
                    if (!$rawTitle) continue;
                    $cleanConcept = preg_replace('/^\[MONTH_BOARD:[^\]]+\]\s*/i', '', $rawTitle);
                    $desc = $p['description'] ?? ($p['copy_text'] ?? '');
                    $pDate = (!empty($p['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $p['due_date'])) ? $p['due_date'] . ' 10:00:00' : date('Y-m-d H:i:s');
                    $stmtInsPost->execute([$resolvedMonthId, $pDate, $cleanConcept, $desc, $desc]);
                    $createdPostIds[] = $db->lastInsertId();
                }

                if (!empty($createdPostIds)) {
                    echo json_encode([
                        'success' => true,
                        'count' => count($createdPostIds),
                        'month_id' => $resolvedMonthId,
                        'redirect_url' => "index.php?module=month_board&action=index&id={$resolvedMonthId}",
                        'message' => count($createdPostIds) . " publicaciones creadas directamente en el Month Board (#{$resolvedMonthId})."
                    ]);
                    exit();
                }
            }
        }

        $createdIds = [];
        $defaultAssigned = json_encode([(string)$user_id]);

        // Guardar en tm_tasks (módulo oficial Tareas & Objetivos: task_manager)
        $stmtInsTM = $db->prepare("
            INSERT INTO tm_tasks (title, description, status, priority, frequency, area, project_id, assigned_users, created_by, due_date, start_date, is_daily_objective, created_at, updated_at)
            VALUES (?, ?, 'pending', ?, 'one_time', ?, ?, ?, ?, ?, NOW(), 0, NOW(), NOW())
        ");

        // También registrar en tasks para retrocompatibilidad defensiva
        $stmtInsLegacy = $db->prepare("
            INSERT INTO tasks (title, description, status, assigned_to, created_by, due_date, is_urgent, created_at, updated_at)
            VALUES (?, ?, 'pending', ?, ?, ?, ?, NOW(), NOW())
        ");

        $defaultArea = trim($_POST['area'] ?? 'general');
        $validAreas = ['general', 'desarrollo_marca', 'desarrollo_web', 'audiovisual', 'pizarras'];
        if (!in_array($defaultArea, $validAreas)) $defaultArea = 'general';

        $projectId = (int)($_POST['project_id'] ?? 0);

        foreach ($tasks as $t) {
            $title = trim($t['title'] ?? '');
            if (!$title) continue;

            $desc = trim($t['description'] ?? '');
            $dueDate = (!empty($t['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $t['due_date'])) ? $t['due_date'] . ' 23:59:59' : null;
            $dueDateLegacy = (!empty($t['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $t['due_date'])) ? $t['due_date'] : null;
            
            $rawPrio = strtolower(trim($t['priority'] ?? ''));
            $isUrgent = (!empty($t['is_urgent']) || $rawPrio === 'urgent' || $rawPrio === 'urgente') ? 1 : 0;
            
            $priority = 'medium';
            if ($isUrgent) {
                $priority = 'urgent';
            } elseif ($rawPrio === 'high' || $rawPrio === 'alta') {
                $priority = 'high';
            } elseif ($rawPrio === 'low' || $rawPrio === 'baja') {
                $priority = 'low';
            }

            $taskArea = trim($t['area'] ?? $defaultArea);
            if (!in_array($taskArea, $validAreas)) $taskArea = $defaultArea;

            // Inserción en tm_tasks
            try {
                $stmtInsTM->execute([$title, $desc, $priority, $taskArea, $projectId ?: null, $defaultAssigned, $user_id, $dueDate]);
                $createdIds[] = $db->lastInsertId();
            } catch (\PDOException $tmErr) {}

            // Inserción en tasks (legacy)
            try {
                $stmtInsLegacy->execute([$title, $desc, $defaultAssigned, $user_id, $dueDateLegacy, $isUrgent]);
            } catch (\PDOException $legErr) {}
        }

        if (empty($createdIds)) {
            echo json_encode(['success' => false, 'error' => 'No se pudo crear ninguna tarea.']);
            exit();
        }

        echo json_encode([
            'success' => true,
            'count' => count($createdIds),
            'task_ids' => $createdIds,
            'redirect_url' => 'index.php?module=task_manager&action=index',
            'message' => count($createdIds) . (count($createdIds) === 1 ? ' tarea creada' : ' tareas creadas') . ' exitosamente en Tareas & Objetivos.'
        ]);
        exit();
    }

    // 2. Agendar Reunión en Módulo Reuniones
    if ($action === 'tool_schedule_meeting') {
        $motivo = trim($_POST['motivo'] ?? '');
        $fechaHora = trim($_POST['fecha_hora'] ?? '');
        $meetLink = trim($_POST['meet_link'] ?? 'https://meet.google.com/new');
        $resumen = trim($_POST['resumen'] ?? '');
        $brandId = (int)($_POST['brand_id'] ?? 0);

        if (!$motivo) {
            echo json_encode(['success' => false, 'error' => 'El motivo de la reunión es obligatorio.']);
            exit();
        }

        // Si no se proporcionó marca, asignar la primera marca del sistema
        if (!$brandId) {
            $stmtBrand = $db->query("SELECT id FROM client_brands ORDER BY id ASC LIMIT 1");
            $brandId = (int)($stmtBrand ? $stmtBrand->fetchColumn() : 1);
            if (!$brandId) $brandId = 1;
        }

        // Normalizar fecha_hora
        if ($fechaHora) {
            $fechaHora = str_replace('T', ' ', $fechaHora);
            if (strlen($fechaHora) === 16) {
                $fechaHora .= ':00';
            }
        } else {
            $fechaHora = date('Y-m-d H:i:s', strtotime('+1 day 10:00:00'));
        }

        $stmtMeet = $db->prepare("
            INSERT INTO reuniones (brand_id, motivo, fecha_hora, meet_link, resumen, estado, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, 'Programada', ?, NOW())
        ");
        $stmtMeet->execute([$brandId, $motivo, $fechaHora, $meetLink, $resumen, $user_id]);
        $meetingId = $db->lastInsertId();

        echo json_encode([
            'success' => true,
            'meeting_id' => $meetingId,
            'message' => 'Reunión agendada exitosamente en la Agenda.'
        ]);
        exit();
    }

    // ==========================================
    // ACCIONES AGÉNTICAS: MÓDULO DE FORMULARIOS
    // ==========================================

    // Generar y crear formulario completo directamente con IA (Groq Cloud + Gemini Fallback)
    if ($action === 'ai_generate_form') {
        $prompt = trim($_POST['prompt'] ?? '');
        if (empty($prompt)) {
            echo json_encode(['success' => false, 'error' => 'Por favor escribe una descripción del formulario a generar.']);
            exit();
        }

        // Obtener claves de Groq y Gemini
        $stmtGroq = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'groq_api_key'");
        $groqApiKey = $stmtGroq ? trim($stmtGroq->fetchColumn() ?: '') : '';
        if (empty($groqApiKey)) {
            $groqApiKey = trim(getenv('GROQ_API_KEY') ?: '');
        }

        $stmtKey = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
        $dbApiKeyRaw = $stmtKey ? trim($stmtKey->fetchColumn() ?: '') : '';
        $geminiKeys = [];
        if (!empty($dbApiKeyRaw)) {
            $geminiKeys = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $dbApiKeyRaw))));
        }
        $envKey = trim(getenv('GEMINI_API_KEY') ?: '');
        if (!empty($envKey)) $geminiKeys[] = $envKey;

        $formPrompt = "Eres el Diseñador Experto de Formularios y Briefs de Roma Agencia CRM. Tu tarea es diseñar un formulario interactivo y altamente profesional basado en la siguiente solicitud:\n\"{$prompt}\"\n\n"
            . "Debes responder ÚNICAMENTE con un objeto JSON válido (sin texto explicativo antes ni después, sin markdown ni ```json) con la siguiente estructura exacta:\n"
            . "{\n"
            . "  \"title\": \"Título profesional del formulario\",\n"
            . "  \"description\": \"Instrucción concisa y amigable para quien responde\",\n"
            . "  \"status\": \"active\",\n"
            . "  \"settings\": {\n"
            . "    \"view_style\": \"hero_cover\",\n"
            . "    \"cover_image\": \"gradient_aurora\",\n"
            . "    \"multi_step\": true,\n"
            . "    \"welcome_screen\": true,\n"
            . "    \"require_name\": true,\n"
            . "    \"require_email\": true,\n"
            . "    \"show_logo\": true\n"
            . "  },\n"
            . "  \"fields\": [\n"
            . "    {\n"
            . "      \"type\": \"divider\",\n"
            . "      \"label\": \"Paso 1: Información General\"\n"
            . "    },\n"
            . "    {\n"
            . "      \"type\": \"text\",\n"
            . "      \"label\": \"Nombre de tu empresa o marca\",\n"
            . "      \"placeholder\": \"Ej: Mi Marca SAC\",\n"
            . "      \"required\": true\n"
            . "    },\n"
            . "    {\n"
            . "      \"type\": \"textarea\",\n"
            . "      \"label\": \"Describe el objetivo principal\",\n"
            . "      \"placeholder\": \"Detalla tu respuesta...\",\n"
            . "      \"required\": true\n"
            . "    },\n"
            . "    {\n"
            . "      \"type\": \"select\",\n"
            . "      \"label\": \"Presupuesto estimado\",\n"
            . "      \"options\": [\"Menos de $1,000\", \"$1,000 - $3,000\", \"Más de $3,000\"],\n"
            . "      \"required\": true\n"
            . "    },\n"
            . "    {\n"
            . "      \"type\": \"icon_card\",\n"
            . "      \"label\": \"Objetivo prioritario\",\n"
            . "      \"icon_options\": [\n"
            . "        {\"icon\": \"ph-rocket\", \"text\": \"Lanzamiento\"},\n"
            . "        {\"icon\": \"ph-megaphone\", \"text\": \"Ventas / Leads\"},\n"
            . "        {\"icon\": \"ph-sparkle\", \"text\": \"Posicionamiento\"}\n"
            . "      ],\n"
            . "      \"required\": true\n"
            . "    }\n"
            . "  ]\n"
            . "}\n"
            . "Genera entre 5 y 9 preguntas inteligentes y pertinentes con variedad de tipos acordes al tema (text, textarea, select, checkbox, dropdown, file, range, number_range, color, icon_card, divider).";

        $generatedJsonText = '';

        // 1. Probar Groq Cloud (Ultra-rápido)
        if (!empty($groqApiKey)) {
            $groqModels = ['qwen/qwen3.8-27b', 'openai/gpt-oss-120b', 'openai/gpt-oss-20b'];
            foreach ($groqModels as $gModel) {
                $chGroq = curl_init('https://api.groq.com/openai/v1/chat/completions');
                curl_setopt($chGroq, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chGroq, CURLOPT_POST, true);
                curl_setopt($chGroq, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $groqApiKey,
                    'Content-Type: application/json'
                ]);
                curl_setopt($chGroq, CURLOPT_POSTFIELDS, json_encode([
                    'model' => $gModel,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres un generador de datos que responde EXCLUSIVAMENTE con JSON válido sin formato markdown ni rodeos.'],
                        ['role' => 'user', 'content' => $formPrompt]
                    ],
                    'temperature' => 0.4,
                    'max_tokens' => 2500
                ]));
                curl_setopt($chGroq, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($chGroq, CURLOPT_CONNECTTIMEOUT, 8);
                curl_setopt($chGroq, CURLOPT_TIMEOUT, 30);
                $gRes = curl_exec($chGroq);
                $gCode = curl_getinfo($chGroq, CURLINFO_HTTP_CODE);
                curl_close($chGroq);

                if ($gCode === 200 && !empty($gRes)) {
                    $gData = json_decode($gRes, true);
                    $content = trim($gData['choices'][0]['message']['content'] ?? '');
                    if (!empty($content)) {
                        $generatedJsonText = $content;
                        break;
                    }
                }
            }
        }

        // 2. Fallback a Google Gemini
        if (empty($generatedJsonText) && !empty($geminiKeys)) {
            $geminiModels = ['gemini-flash-latest', 'gemini-2.5-flash', 'gemini-1.5-flash'];
            $payloadGemini = [
                'contents' => [['parts' => [['text' => $formPrompt]]]]
            ];
            foreach ($geminiKeys as $gKey) {
                if (!empty($generatedJsonText)) break;
                foreach ($geminiModels as $gemModel) {
                    $chG = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$gemModel}:generateContent?key=" . $gKey);
                    curl_setopt($chG, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chG, CURLOPT_POST, true);
                    curl_setopt($chG, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($chG, CURLOPT_POSTFIELDS, json_encode($payloadGemini));
                    curl_setopt($chG, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($chG, CURLOPT_CONNECTTIMEOUT, 8);
                    curl_setopt($chG, CURLOPT_TIMEOUT, 30);
                    $resG = curl_exec($chG);
                    $codeG = curl_getinfo($chG, CURLINFO_HTTP_CODE);
                    curl_close($chG);

                    if ($codeG === 200 && !empty($resG)) {
                        $dataG = json_decode($resG, true);
                        $txt = trim($dataG['candidates'][0]['content']['parts'][0]['text'] ?? '');
                        if (!empty($txt)) {
                            $generatedJsonText = $txt;
                            break;
                        }
                    }
                }
            }
        }

        if (empty($generatedJsonText)) {
            echo json_encode(['success' => false, 'error' => 'No se pudo conectar con los motores de IA para generar el formulario. Por favor verifica tus API Keys.']);
            exit();
        }

        // Limpiar bloques de código si la IA los incluyó
        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', trim($generatedJsonText));
        $cleanJson = preg_replace('/\s*```$/i', '', $cleanJson);
        $formData = json_decode($cleanJson, true);

        if (!$formData || !is_array($formData)) {
            if (preg_match('/\{[\s\S]*\}/', $generatedJsonText, $matches)) {
                $formData = json_decode($matches[0], true);
            }
        }

        if (!$formData || !is_array($formData)) {
            echo json_encode(['success' => false, 'error' => 'Respuesta no estructurada de la IA.', 'raw' => $generatedJsonText]);
            exit();
        }

        $_POST['title'] = $formData['title'] ?? 'Formulario Generado con IA';
        $_POST['description'] = $formData['description'] ?? '';
        $_POST['status'] = $formData['status'] ?? 'active';
        $_POST['fields'] = $formData['fields'] ?? [];
        $_POST['settings'] = $formData['settings'] ?? [];
        $action = 'tool_create_form';
    }

    // Crear formulario directamente en la base de datos (form_templates)
    if ($action === 'tool_create_form') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        if (!in_array($status, ['active', 'draft', 'archived'])) {
            $status = 'active';
        }

        // Obtener fields
        $fieldsRaw = $_POST['fields'] ?? ($_POST['fields_json'] ?? '[]');
        $fields = is_array($fieldsRaw) ? $fieldsRaw : json_decode($fieldsRaw, true);
        if (!is_array($fields)) {
            $fields = [];
        }

        // Obtener settings
        $settingsRaw = $_POST['settings'] ?? ($_POST['settings_json'] ?? '{}');
        $settings = is_array($settingsRaw) ? $settingsRaw : json_decode($settingsRaw, true);
        if (!is_array($settings)) {
            $settings = [];
        }

        // Fallback si vino un payload consolidado en 'form_data'
        if (empty($title) && !empty($_POST['form_data'])) {
            $formData = is_array($_POST['form_data']) ? $_POST['form_data'] : json_decode($_POST['form_data'], true);
            if (is_array($formData)) {
                $title = trim($formData['title'] ?? '');
                $description = trim($formData['description'] ?? '');
                $status = trim($formData['status'] ?? $status);
                if (!empty($formData['fields']) && is_array($formData['fields'])) {
                    $fields = $formData['fields'];
                }
                if (!empty($formData['settings']) && is_array($formData['settings'])) {
                    $settings = array_merge($settings, $formData['settings']);
                }
            }
        }

        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'El título del formulario es obligatorio.']);
            exit();
        }

        // Normalizar fields asegurando IDs únicos y atributos estándar
        $normalizedFields = [];
        foreach ($fields as $idx => $f) {
            if (!is_array($f)) continue;
            $fType = trim($f['type'] ?? 'text');
            $fLabel = trim($f['label'] ?? 'Pregunta sin título');
            $fPlaceholder = trim($f['placeholder'] ?? '');
            $fRequired = !empty($f['required']);
            $fWidth = in_array($f['width'] ?? '', ['half', 'full']) ? $f['width'] : 'full';
            $fId = !empty($f['id']) ? $f['id'] : ('f_' . bin2hex(random_bytes(5)));
            
            $item = [
                'id' => $fId,
                'type' => $fType,
                'label' => $fLabel,
                'placeholder' => $fPlaceholder,
                'required' => $fRequired,
                'width' => $fWidth,
                'description' => trim($f['description'] ?? '')
            ];

            if (isset($f['options']) && is_array($f['options'])) {
                $item['options'] = array_values(array_filter(array_map('trim', $f['options'])));
            } elseif (in_array($fType, ['select', 'checkbox', 'dropdown'])) {
                $item['options'] = ['Opción 1', 'Opción 2'];
            }

            if ($fType === 'range') {
                $item['range_min'] = isset($f['range_min']) ? (int)$f['range_min'] : 1;
                $item['range_max'] = isset($f['range_max']) ? (int)$f['range_max'] : 5;
                $item['range_label_min'] = trim($f['range_label_min'] ?? 'Bajo');
                $item['range_label_max'] = trim($f['range_label_max'] ?? 'Alto');
            }

            if ($fType === 'number_range') {
                $item['nr_min'] = isset($f['nr_min']) ? (int)$f['nr_min'] : 18;
                $item['nr_max'] = isset($f['nr_max']) ? (int)$f['nr_max'] : 65;
                $item['nr_step'] = isset($f['nr_step']) ? (int)$f['nr_step'] : 1;
            }

            if ($fType === 'color') {
                $item['color_options'] = !empty($f['color_options']) && is_array($f['color_options']) 
                    ? $f['color_options'] 
                    : ['#4f46e5', '#10b981', '#ef4444', '#f59e0b', '#8b5cf6'];
                $item['color_multi'] = isset($f['color_multi']) ? (bool)$f['color_multi'] : true;
            }

            if ($fType === 'icon_card') {
                $item['icon_options'] = !empty($f['icon_options']) && is_array($f['icon_options']) 
                    ? $f['icon_options'] 
                    : [['icon' => 'ph-star', 'text' => 'Opción 1'], ['icon' => 'ph-rocket', 'text' => 'Opción 2']];
                $item['icon_multi'] = isset($f['icon_multi']) ? (bool)$f['icon_multi'] : false;
            }

            if ($fType === 'image_compare') {
                $item['compare_options'] = !empty($f['compare_options']) && is_array($f['compare_options']) ? $f['compare_options'] : [];
                $item['compare_multi'] = isset($f['compare_multi']) ? (bool)$f['compare_multi'] : false;
            }

            $normalizedFields[] = $item;
        }

        // Si no se proporcionaron campos, crear campo por defecto
        if (empty($normalizedFields)) {
            $normalizedFields[] = [
                'id' => 'f_' . bin2hex(random_bytes(5)),
                'type' => 'text',
                'label' => 'Nombre completo o de la empresa',
                'placeholder' => 'Ingresa tu respuesta...',
                'required' => true,
                'width' => 'full',
                'description' => ''
            ];
        }

        // Settings por defecto modernos
        $finalSettings = array_merge([
            'show_logo' => true,
            'require_name' => true,
            'require_email' => true,
            'multi_step' => true,
            'view_style' => 'hero_cover',
            'welcome_screen' => true,
            'cover_image' => 'gradient_aurora',
            'custom_avatar' => ''
        ], $settings);

        $fieldsJson = json_encode($normalizedFields, JSON_UNESCAPED_UNICODE);
        $settingsJson = json_encode($finalSettings, JSON_UNESCAPED_UNICODE);
        $token = bin2hex(random_bytes(16));
        $userId = (int)($_SESSION['user_id'] ?? 1);

        $stmtInsert = $db->prepare("
            INSERT INTO form_templates 
            (title, description, fields_json, settings_json, public_token, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtInsert->execute([$title, $description, $fieldsJson, $settingsJson, $token, $status, $userId]);
        $newFormId = (int)$db->lastInsertId();

        // Si está publicado, intentar crear carpeta en Google Drive de forma segura
        if ($status === 'active') {
            try {
                if (file_exists(__DIR__ . '/../includes/GoogleDriveHelper.php')) {
                    require_once __DIR__ . '/../includes/GoogleDriveHelper.php';
                    $drive = new GoogleDriveHelper();
                    if ($drive->isConfigured()) {
                        $briefsFolder = null;
                        $rootFolders = $drive->listFolders('root');
                        if ($rootFolders) {
                            foreach ($rootFolders as $f) {
                                if (strtolower(trim($f->name)) === 'briefs') { $briefsFolder = $f->id; break; }
                            }
                        }
                        if (!$briefsFolder) $briefsFolder = $drive->createFolder('Briefs');
                        if ($briefsFolder) {
                            $formFolder = $drive->createFolder($title, $briefsFolder);
                            if ($formFolder) {
                                $db->prepare("UPDATE form_templates SET drive_folder_id=? WHERE id=?")->execute([$formFolder, $newFormId]);
                            }
                        }
                    }
                }
            } catch (\Throwable $eDrive) {
                error_log("Romita tool_create_form Drive Warning: " . $eDrive->getMessage());
            }
        }

        $shortToken = substr($token, 0, 8);
        $publicUrl = "f/" . $shortToken;
        $builderUrl = "index.php?module=forms&action=builder&id=" . $newFormId;

        echo json_encode([
            'success' => true,
            'id' => $newFormId,
            'title' => $title,
            'token' => $token,
            'short_token' => $shortToken,
            'total_fields' => count($normalizedFields),
            'status' => $status,
            'public_url' => $publicUrl,
            'builder_url' => $builderUrl,
            'redirect_url' => $builderUrl,
            'message' => "¡Formulario '{$title}' creado exitosamente con " . count($normalizedFields) . " preguntas!"
        ]);
        exit();
    }

    // Acciones estrictas de Administrador
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
