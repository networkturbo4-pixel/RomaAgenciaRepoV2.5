<?php
// modules/romita/index.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'includes/header.php';
global $db;

// Verify user role
$stmt_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt_admin->execute([$_SESSION['user_id']]);
$is_admin = ($stmt_admin->fetchColumn() == 1);

// Fetch active skills, prepts, and calendar projects
$skills = [];
$prepts = [];
$calendarProjects = [];
$user_role = $_SESSION['role'] ?? 'user';

// 1. Fetch Skills (con control de excepciones independiente)
try {
    if ($is_admin) {
        $stmt = $db->query("SELECT id, name, description, prompt_base, allowed_role FROM romita_skills WHERE is_active = 1 ORDER BY name ASC");
    } else {
        $stmt = $db->prepare("SELECT id, name, description, prompt_base, allowed_role FROM romita_skills WHERE is_active = 1 AND (allowed_role = 'all' OR allowed_role = ?) ORDER BY name ASC");
        $stmt->execute([$user_role]);
    }
    if ($stmt) $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $skills = [];
}

// 2. Fetch Prepts con Manual de Tono y Memoria de Marca (Fase 4)
try {
    $stmtPrepts = $db->query("SELECT id, name, tone, archetype, audience, rules, forbidden_words FROM romita_prepts ORDER BY name ASC");
    if ($stmtPrepts) $prepts = $stmtPrepts->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $prepts = [];
}

// 3. Fetch Calendar Projects (Resiliente: independiente de romita_skills/prepts y con fallback seguro)
try {
    // Intento A: Con métricas completas de meses y posts
    $stmtProjects = $db->query("
        SELECT p.id as project_id, 
               COALESCE(NULLIF(wo.brand_name, ''), CONCAT('Proyecto #', p.id)) as brand_name, 
               wo.correlativo,
               (SELECT COUNT(*) FROM project_months pm WHERE pm.project_id = p.id) as total_months,
               (SELECT COUNT(*) FROM month_posts mp JOIN project_months pm ON mp.month_id = pm.id WHERE pm.project_id = p.id) as total_posts
        FROM projects p
        LEFT JOIN work_orders wo ON p.work_order_id = wo.id
        WHERE (wo.is_archived IS NULL OR wo.is_archived = 0)
        ORDER BY brand_name ASC
    ");
    if ($stmtProjects) {
        $calendarProjects = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(Exception $e) {
    // Intento B (Fallback): Si project_months o month_posts fallan en producción
    try {
        $stmtFallback = $db->query("
            SELECT p.id as project_id, 
                   COALESCE(NULLIF(wo.brand_name, ''), CONCAT('Proyecto #', p.id)) as brand_name, 
                   wo.correlativo, 
                   0 as total_months, 
                   0 as total_posts
            FROM projects p
            LEFT JOIN work_orders wo ON p.work_order_id = wo.id
            WHERE (wo.is_archived IS NULL OR wo.is_archived = 0)
            ORDER BY brand_name ASC
        ");
        if ($stmtFallback) {
            $calendarProjects = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(Exception $e2) {
        // Intento C (Respaldo directo de tabla projects):
        try {
            $stmtSimple = $db->query("SELECT id as project_id, CONCAT('Proyecto #', id) as brand_name, '' as correlativo, 0 as total_months, 0 as total_posts FROM projects ORDER BY id DESC");
            if ($stmtSimple) $calendarProjects = $stmtSimple->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e3) {
            $calendarProjects = [];
        }
    }
}

$first_name = htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'Usuario')[0]);
$hour = (int)date('H');
$time_greeting = ($hour >= 5 && $hour < 12) ? 'Buenos días' : (($hour >= 12 && $hour < 19) ? 'Buenas tardes' : 'Buenas noches');

// Fase 4: Carga de Chat Compartido si existe token
$share_token = trim($_GET['share'] ?? '');
$sharedChat = null;
$sharedMessages = [];
if (!empty($share_token)) {
    try {
        $stmtShare = $db->prepare("
            SELECT rc.id, rc.title, rc.created_at, u.name as author_name 
            FROM romita_chats rc 
            JOIN users u ON rc.user_id = u.id 
            WHERE rc.share_token = ?
        ");
        $stmtShare->execute([$share_token]);
        $sharedChat = $stmtShare->fetch(PDO::FETCH_ASSOC);

        if ($sharedChat) {
            $stmtMsgs = $db->prepare("
                SELECT id, role, content, feedback, attachment_url, attachment_type, attachment_name, created_at 
                FROM romita_messages 
                WHERE chat_id = ? 
                ORDER BY id ASC
            ");
            $stmtMsgs->execute([$sharedChat['id']]);
            $sharedMessages = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(Exception $e) {}
}

// Fase 4: Memoria de Preferencias del Usuario
$userPrefs = null;
try {
    $stmtPref = $db->prepare("SELECT response_style, custom_instructions, default_specialty, sound_enabled FROM romita_user_preferences WHERE user_id = ?");
    $stmtPref->execute([$_SESSION['user_id']]);
    $userPrefs = $stmtPref->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {}
if (!$userPrefs) {
    $userPrefs = [
        'response_style' => 'strategic',
        'custom_instructions' => '',
        'default_specialty' => 'director_360',
        'sound_enabled' => 1
    ];
}

// Fase 4: Biblioteca de Super-Prompts (Auto-Creación, Auto-Seeding y Fallback Resiliente para Producción)
$defaultAgencyPrompts = [
    [
        'id' => 1,
        'user_id' => null,
        'title' => 'Guion de Reels de Alta Retención (9:16)',
        'category' => 'Redes Sociales',
        'prompt' => "Actúa como Director Creativo Audiovisual. Redacta un guion para un Reel de 45 segundos sobre [TEMA / PRODUCTO] para la marca activa. Divide el contenido en 4 partes con minutaje exacto:\n1. Gancho Visual & Auditivo (0-3s): ¿Qué ve y escucha el espectador para no deslizar?\n2. Retención & Quiebre (3-15s): Presenta el problema común de forma intrigante.\n3. Núcleo de Valor (15-38s): 3 soluciones prácticas paso a paso.\n4. Llamado a la Acción (38-45s): CTA claro invitando a comentar una palabra clave para recibir información.",
        'description' => 'Estructura probada: Gancho 3s + Quiebre de patrón + Valor + CTA',
        'icon' => 'ph-video-camera',
        'is_agency_template' => 1
    ],
    [
        'id' => 2,
        'user_id' => null,
        'title' => 'Carrusel B2B Educativo (10 Slides)',
        'category' => 'Contenido Educativo',
        'prompt' => "Diseña el guion completo de un carrusel educativo de 10 láminas sobre [TEMA] adaptado al sector de la marca activa.\nEstructura cada slide con:\n- Título conciso (máximo 6 palabras).\n- Cuerpo de texto en viñetas directas (máximo 25 palabras).\n- Indicación de diseño gráfico / elemento visual para el equipo de diseño.\n- Slide 1: Portada magnética con promesa irresistible.\n- Slide 2: El error número 1 que todos cometen.\n- Slides 3 a 8: Metodología paso a paso desglosada.\n- Slide 9: Resumen de takeaways en 3 viñetas.\n- Slide 10: Portada final con llamado a guardar y compartir.",
        'description' => 'Formato de micro-aprendizaje para LinkedIn e Instagram',
        'icon' => 'ph-slideshow',
        'is_agency_template' => 1
    ],
    [
        'id' => 3,
        'user_id' => null,
        'title' => 'Fórmula PAS para Copywriting Publicitario',
        'category' => 'Copywriting & Ads',
        'prompt' => "Genera 3 variaciones de copy publicitario para Meta Ads (Facebook/Instagram) usando la fórmula PAS (Problema - Agitación - Solución) enfocado en [OFERTA / SERVICIO].\nEstructura cada variación:\n1. Problema: El dolor real y frustrante de la audiencia objetivo.\n2. Agitación: Qué pasa si no resuelven ese problema hoy.\n3. Solución: Cómo nuestro servicio elimina el dolor de raíz.\n4. Oferta irresistible con CTA claro.\nEntrega Variación A (Enfoque directo al grano), Variación B (Enfoque storytelling testimonial), y Variación C (Enfoque de autoridad y datos).",
        'description' => 'Problema, Agitación y Solución con 3 variaciones de tono',
        'icon' => 'ph-lightning',
        'is_agency_template' => 1
    ],
    [
        'id' => 4,
        'user_id' => null,
        'title' => 'Simulador de Objeciones de Clientes (Role-Play)',
        'category' => 'Ventas & Clientes',
        'prompt' => "Iniciemos una sesión de ROLE-PLAY de ventas.\nTú actuarás como un cliente escéptico y exigente interesado en contratar los servicios de nuestra agencia.\nPresenta una primera objeción realista y difícil (por ejemplo: \"Su tarifa es demasiado cara, otra agencia me cobra la mitad\" o \"No creo que las redes sociales funcionen para mi nicho\").\nQuédate en personaje. Espera mi respuesta y luego califícame del 1 al 10 con feedback constructivo sobre cómo mejorar mi argumento de venta, y luego lanza la siguiente objeción más difícil.",
        'description' => 'Entrenamiento de objeciones duras en tiempo real',
        'icon' => 'ph-users-three',
        'is_agency_template' => 1
    ],
    [
        'id' => 5,
        'user_id' => null,
        'title' => 'Auditoría Rápida de Competencia & Benchmark',
        'category' => 'Estrategia & Research',
        'prompt' => "Realiza un análisis competitivo rápido tipo Benchmark para la marca activa frente a sus 3 principales competidores en el mercado.\nEntrega una tabla comparativa con:\n- Pilares de contenido principales.\n- Frecuencia y formatos dominantes (Reels, Fotos, Carruseles).\n- Tono de comunicación.\n- Calidad de comunidad y engagement promedio.\n- \"Gaps\" o brechas de oportunidad que nuestra agencia puede capitalizar para destacar.",
        'description' => 'Matriz comparativa de 3 competidores clave con brechas de oportunidad',
        'icon' => 'ph-chart-bar',
        'is_agency_template' => 1
    ],
    [
        'id' => 6,
        'user_id' => null,
        'title' => 'Plan de Contenidos 30 Días (Matriz Mensual)',
        'category' => 'Planificación',
        'prompt' => "Genera la matriz estratégica de contenidos para los próximos 30 días de la marca seleccionada.\nConsidera 3 publicaciones semanales (12 en total) balanceando los 4 pilares:\n- 30% Educativo / Valor técnico.\n- 30% Autoridad / Casos de éxito y testimonios.\n- 20% Conexión humana / Behind the scenes de la agencia.\n- 20% Venta directa / Promoción de servicios.\nPresenta todo en una tabla detallada con Día, Formato (Reel, Carrusel, Estático), Objetivo, Gancho y Llamado a la Acción.",
        'description' => 'Distribución estratégica de 12 a 16 piezas para el mes',
        'icon' => 'ph-calendar-check',
        'is_agency_template' => 1
    ]
];

$superPrompts = [];
try {
    // 1. Asegurar tabla en caso no exista en producción
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

    // 2. Si la tabla está vacía en producción, auto-sembrar las 6 plantillas oficiales
    $countAgency = (int)$db->query("SELECT COUNT(*) FROM `romita_super_prompts` WHERE is_agency_template = 1")->fetchColumn();
    if ($countAgency === 0) {
        $stmtSeed = $db->prepare("INSERT INTO `romita_super_prompts` 
            (`user_id`, `title`, `category`, `prompt`, `description`, `icon`, `is_agency_template`, `created_at`) 
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
        foreach ($defaultAgencyPrompts as $dp) {
            $stmtSeed->execute([$dp['user_id'], $dp['title'], $dp['category'], $dp['prompt'], $dp['description'], $dp['icon']]);
        }
    }

    // 3. Consultar los super-prompts
    $stmtSP = $db->prepare("SELECT * FROM romita_super_prompts WHERE (is_agency_template = 1 OR user_id = ?) ORDER BY is_agency_template DESC, id DESC");
    $stmtSP->execute([$_SESSION['user_id']]);
    $superPrompts = $stmtSP->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// Si por alguna razón la BD falló o devolvió 0 registros, usar el fallback seguro en memoria
if (empty($superPrompts)) {
    $superPrompts = $defaultAgencyPrompts;
}
?>

<link rel="stylesheet" href="assets/css/romita.css?v=<?php echo file_exists('assets/css/romita.css') ? filemtime('assets/css/romita.css') : time(); ?>">
<style>
    /* Forzar modo App completa de Romita AI sin doble scroll ni espacios vacíos */
    .content-wrapper {
        padding: 0 !important;
        margin: 0 !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
        height: calc(100vh - 60px) !important;
        height: calc(100dvh - 60px) !important;
    }
    
    @media (max-width: 768px) {
        .content-wrapper {
            padding: 0 !important;
            padding-top: 52px !important; /* Altura del topbar móvil fijo de RomaAgencia */
            margin: 0 !important;
            height: 100vh !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            box-sizing: border-box !important;
        }
        .romita-container {
            height: calc(100dvh - 52px) !important;
            height: calc(100vh - 52px) !important;
            max-height: calc(100dvh - 52px) !important;
        }
    }
</style>
<!-- Marked.js para formateo de Markdown -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<div class="romita-container">
    
    <!-- Backdrop oscuro para móvil cuando se abre el sidebar -->
    <div class="romita-sidebar-backdrop" id="romitaSidebarBackdrop" onclick="toggleSidebar()"></div>

    <!-- Sidebar Historial de Conversaciones -->
    <aside class="romita-sidebar" id="romitaSidebar">
        <div class="romita-sidebar-header">
            <div class="romita-sidebar-title">
                <i class="ph ph-clock-counter-clockwise"></i>
                <span>Historial de Chats</span>
            </div>
            <button class="btn-close-modal" onclick="toggleSidebar()" title="Cerrar"><i class="ph ph-x"></i></button>
        </div>

        <div class="romita-sidebar-actions">
            <button class="btn-new-chat-primary" onclick="newConversation()">
                <i class="ph ph-plus-circle"></i> Nueva Conversación
            </button>
            <div class="sidebar-search-box">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" class="sidebar-search-input" id="sidebarChatSearch" placeholder="Filtrar historial..." oninput="filterSidebarChats(this.value)">
            </div>
        </div>

        <div class="romita-chat-list" id="chatList">
            <!-- Cargado por AJAX -->
        </div>
    </aside>
    
    <!-- Área de Chat Principal -->
    <main class="romita-main">
        <!-- Topbar / Header Moderno & Minimalista -->
        <header class="romita-header romita-header-modern">
            <div class="romita-header-left">
                <button class="btn-toggle-sidebar" onclick="toggleSidebar()" title="Ver Historial de Chats">
                    <i class="ph ph-sidebar-simple"></i>
                </button>
            </div>

            <!-- Derecha: Acciones limpias y compactas -->
            <div class="romita-header-right">
                <!-- Buscador inline desplegable -->
                <div class="romita-inline-search-wrap" id="romitaInlineSearchWrap">
                    <button type="button" class="btn-romita-icon-action" id="btnToggleSearch" onclick="toggleHeaderSearch()" title="Buscar en esta conversación">
                        <i class="ph ph-magnifying-glass"></i>
                    </button>
                    <div class="romita-inline-search-bar" id="romitaInlineSearchBar" style="display:none;">
                        <i class="ph ph-magnifying-glass search-bar-icon"></i>
                        <input type="text" id="chatSearch" placeholder="Buscar en conversación..." class="search-bar-input" onkeyup="searchChat(this.value)">
                        <button type="button" class="btn-search-clear" onclick="clearAndCloseSearch()" title="Cerrar búsqueda"><i class="ph ph-x"></i></button>
                    </div>
                </div>

                <!-- Botón Nuevo Chat Destacado -->
                <button type="button" class="btn-romita-new-chat-pill" onclick="newConversation()" title="Iniciar conversación limpia">
                    <i class="ph-bold ph-plus"></i>
                    <span class="hide-mobile">Nuevo Chat</span>
                </button>

                <!-- Menú Desplegable de Opciones y Herramientas (···) -->
                <div class="romita-options-menu-wrap">
                    <button type="button" class="btn-romita-icon-action" id="btnRomitaOptions" onclick="toggleRomitaOptionsMenu(event)" title="Más herramientas y opciones">
                        <i class="ph-bold ph-dots-three-vertical"></i>
                    </button>
                    
                    <div class="romita-options-dropdown" id="romitaOptionsDropdown" style="display:none;" onclick="event.stopPropagation()">
                        <div class="rod-header">
                            <span>Herramientas de Romita</span>
                        </div>
                        <button type="button" class="rod-item" id="btnShareChat" onclick="shareCurrentChat(); closeRomitaOptionsMenu();">
                            <i class="ph ph-share-network"></i>
                            <div class="rod-item-info">
                                <span class="rod-item-title">Compartir conversación</span>
                                <span class="rod-item-desc">Generar link público de lectura</span>
                            </div>
                        </button>
                        <button type="button" class="rod-item" id="btnExportPdf" onclick="exportChatToPdf(); closeRomitaOptionsMenu();">
                            <i class="ph ph-file-pdf"></i>
                            <div class="rod-item-info">
                                <span class="rod-item-title">Exportar a PDF</span>
                                <span class="rod-item-desc">Descargar resumen membretado</span>
                            </div>
                        </button>
                        <button type="button" class="rod-item" id="btnUserPrefs" onclick="openUserPrefsModal(); closeRomitaOptionsMenu();">
                            <i class="ph ph-gear-six"></i>
                            <div class="rod-item-info">
                                <span class="rod-item-title">Memoria y Preferencias</span>
                                <span class="rod-item-desc">Tono de respuesta y contexto</span>
                            </div>
                        </button>
                        <button type="button" class="rod-item rod-sound-item" id="romitaSoundToggle" onclick="toggleRomitaSound()">
                            <i class="ph ph-speaker-high" id="menuSoundIcon"></i>
                            <div class="rod-item-info">
                                <span class="rod-item-title">Efectos de sonido</span>
                                <span class="rod-item-desc" id="menuSoundSub">Sonido activado</span>
                            </div>
                        </button>

                        <?php if($is_admin): ?>
                            <div class="rod-divider"></div>
                            <div class="rod-header"><span>Administración</span></div>
                            <button type="button" class="rod-item" onclick="openPreptsModal(); closeRomitaOptionsMenu();">
                                <i class="ph ph-buildings"></i>
                                <div class="rod-item-info">
                                    <span class="rod-item-title">Gestión de Marcas (Prepts)</span>
                                    <span class="rod-item-desc">Manuales de tono y reglas</span>
                                </div>
                            </button>
                            <button type="button" class="rod-item" onclick="openSkillsModal(); closeRomitaOptionsMenu();">
                                <i class="ph ph-sliders"></i>
                                <div class="rod-item-info">
                                    <span class="rod-item-title">Configurar Habilidades (Skills)</span>
                                    <span class="rod-item-desc">Especialidades y prompts base</span>
                                </div>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($sharedChat): ?>
        <!-- Banner de Visualización de Chat Compartido (Fase 4) -->
        <div class="romita-shared-notice-banner" id="sharedNoticeBanner">
            <div class="rsnb-left">
                <div class="rsnb-icon"><i class="ph-bold ph-share-network"></i></div>
                <div>
                    <strong>Conversación compartida por <?php echo htmlspecialchars($sharedChat['author_name']); ?>:</strong>
                    <span><?php echo htmlspecialchars($sharedChat['title']); ?></span>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <button type="button" class="rsnb-btn" onclick="cloneSharedChat(<?php echo $sharedChat['id']; ?>)">
                    <i class="ph-bold ph-copy-simple"></i> Guardar en mis chats
                </button>
                <a href="index.php?module=romita&action=index" class="rsnb-btn" title="Cerrar visor y nuevo chat">
                    <i class="ph ph-x"></i> Salir
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Feed del Chat -->
        <div class="romita-chat-area" id="chatArea">
            <div class="chat-stream-inner" id="chatStreamInner">
                <!-- Empty State Hero -->
                <div class="romita-empty-state" id="emptyState">
                    <div class="romita-hero-glow">
                        <div class="romita-hero-icon" style="background: #0a0f1d; border: 1.5px solid rgba(56, 189, 248, 0.35); overflow: hidden; padding: 0;">
                            <img src="assets/img/romita-avatar.png" alt="Romita" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    </div>
                    <h3 class="romita-hero-title" id="heroGreeting">¡<?php echo $time_greeting; ?>, <?php echo $first_name; ?>!</h3>
                    <p class="romita-hero-sub" id="heroSubtext">Soy Romita, tu asistente inteligente en ROMA SaaS. Conozco el historial de tus proyectos y calendarios para generar contenido fundamentado.</p>

                    <!-- Tarjetas de Sugerencias Rápidas Dinámicas -->
                    <div class="romita-prompt-grid" id="promptGrid">
                        <!-- Renderizado dinámico según marca o predeterminado -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Composer Flotante (Área de Entrada) -->
        <div class="romita-input-wrapper">
            <!-- Carrusel de Skills Disponibles -->
            <div class="romita-skills-pills" id="skillsContainer">
                <?php foreach($skills as $skill): ?>
                    <?php 
                        $nameLower = strtolower($skill['name']);
                        $icon = 'ph-lightning';
                        if (strpos($nameLower, 'dev') !== false || strpos($nameLower, 'código') !== false || strpos($nameLower, 'code') !== false) $icon = 'ph-code';
                        elseif (strpos($nameLower, 'venta') !== false || strpos($nameLower, 'sales') !== false) $icon = 'ph-briefcase';
                        elseif (strpos($nameLower, 'marketing') !== false || strpos($nameLower, 'seo') !== false) $icon = 'ph-megaphone';
                        elseif (strpos($nameLower, 'diseño') !== false || strpos($nameLower, 'design') !== false) $icon = 'ph-palette';
                        elseif (strpos($nameLower, 'finanza') !== false) $icon = 'ph-currency-dollar';
                    ?>
                    <div class="skill-pill" data-id="<?php echo $skill['id']; ?>" data-prompt="<?php echo htmlspecialchars($skill['prompt_base']); ?>" data-icon="<?php echo $icon; ?>" onclick="selectSkill(this)">
                        <i class="ph <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($skill['name']); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Caja de Entrada -->
            <div class="romita-input-container romita-composer rg-composer-card" id="romitaComposerCard">
                <!-- Slash Commands Autocomplete Menu -->
                <div class="rg-slash-menu" id="rg-slash-menu" style="display:none;" onclick="event.stopPropagation()">
                    <div class="rg-slash-header">
                        <span class="rg-slash-title"><i class="ph-bold ph-lightning"></i> Comandos Rápidos</span>
                        <span class="rg-slash-tip"><kbd>↑</kbd> <kbd>↓</kbd> navegar <kbd>Enter</kbd> seleccionar <kbd>Esc</kbd> cerrar</span>
                    </div>
                    <div class="rg-slash-items" id="rg-slash-items">
                        <button type="button" class="rg-slash-item active" data-cmd="/tarea" data-prompt="Estructura un plan de acción con tareas concretas para el Kanban del proyecto actual..." onclick="selectRomitaModuleSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#2563eb;"><i class="ph-bold ph-kanban"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/tarea</span>
                                <span class="rg-slash-desc">Planificar entregables y crear tareas para el Kanban con 1 clic</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/reunion" data-prompt="Diseña una agenda ejecutiva y estructura de minuta para una reunión de alineación con el cliente..." onclick="selectRomitaModuleSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#7c3aed;"><i class="ph-bold ph-calendar-plus"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/reunion</span>
                                <span class="rg-slash-desc">Agendar reunión de trabajo y generar estructura de minuta</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/whatsapp" data-prompt="Redacta un mensaje persuasivo y cordial para enviar por WhatsApp al cliente resumiendo..." onclick="selectRomitaModuleSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#16a34a;"><i class="ph-bold ph-whatsapp-logo"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/whatsapp</span>
                                <span class="rg-slash-desc">Redactar mensaje o minuta lista para enviar por WhatsApp</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/brief" data-prompt="Inicia una entrevista guiada paso a paso para levantar los requerimientos del brief..." onclick="selectRomitaModuleSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#f59e0b;"><i class="ph-bold ph-notepad"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/brief</span>
                                <span class="rg-slash-desc">Levantamiento de brief conversacional guiado paso a paso</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/auditoria" data-prompt="Realiza una auditoría completa de calidad (QA) y checklist técnico antes de la entrega..." onclick="selectRomitaModuleSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#ec4899;"><i class="ph-bold ph-shield-check"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/auditoria</span>
                                <span class="rg-slash-desc">Checklist de control de calidad y revisión pre-entrega</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/campaña" data-prompt="Estructura una campaña publicitaria en Meta Ads con embudo TOFU-MOFU-BOFU..." onclick="selectRomitaModuleSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#06b6d4;"><i class="ph-bold ph-funnel"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/campaña</span>
                                <span class="rg-slash-desc">Estructurar campaña de pauta con ganchos y públicos</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/copy" data-prompt="Redacta un copy persuasivo completo con gancho magnético, desarrollo persuasivo (método AIDA), llamado a la acción (CTA) y hashtags optimizados para: " onclick="selectRomitaModuleSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:linear-gradient(135deg, #1d4ed8, #38bdf8);"><i class="ph-bold ph-feather"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/copy</span>
                                <span class="rg-slash-desc">Redacción de copy persuasivo con método AIDA y gancho magnético</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/monthboard" data-prompt="Genera 4 publicaciones estratégicas para el Month Board con concepto, copy completo, formato (Reel/Post), pilar y hashtags listas para inyectar con 1 clic para: " onclick="selectRomitaModuleSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:linear-gradient(135deg, #059669, #0284c7);"><i class="ph-bold ph-calendar-check"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/monthboard</span>
                                <span class="rg-slash-desc">Generar publicaciones estructuradas para el Month Board</span>
                            </div>
                        </button>
                        <button type="button" class="rg-slash-item" data-cmd="/hashtags" data-prompt="Investiga y genera 10 a 12 hashtags estratégicos de alto impacto y alcance para: " onclick="selectRomitaModuleSlashCommand(this)">
                            <span class="rg-slash-icon" style="background:#60a5fa;"><i class="ph-bold ph-hash"></i></span>
                            <div class="rg-slash-text">
                                <span class="rg-slash-name">/hashtags</span>
                                <span class="rg-slash-desc">10 hashtags estratégicos optimizados por nicho y tema</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Previsualizador de archivo adjunto (Fase 3: Multimodalidad) -->
                <div id="romitaModuleAttachmentPreview" class="romita-attachment-preview-box" style="display:none;">
                    <div class="romita-attachment-preview-inner">
                        <div id="romitaModuleAttachmentMedia"></div>
                        <div class="romita-att-info">
                            <span class="romita-att-name" id="romitaModuleAttachmentName"></span>
                            <span class="romita-att-size" id="romitaModuleAttachmentSize"></span>
                        </div>
                        <button type="button" class="romita-att-remove" onclick="removeRomitaModuleAttachment()" title="Quitar archivo adjunto">
                            <i class="ph ph-x"></i>
                        </button>
                    </div>
                    <div class="romita-vision-chips" id="romitaModuleVisionChips" style="display:none;">
                        <span class="rvc-chip" onclick="applyRomitaModuleVisionPrompt('Audita la legibilidad, contraste y jerarquía visual de esta imagen.')"><i class="ph ph-eye"></i> Auditar diseño</span>
                        <span class="rvc-chip" onclick="applyRomitaModuleVisionPrompt('Verifica si los elementos clave cumplen las zonas seguras para Reels / Stories 9:16 e Instagram.')"><i class="ph ph-bounding-box"></i> Zonas seguras (9:16)</span>
                        <span class="rvc-chip" onclick="applyRomitaModuleVisionPrompt('Extrae todo el texto que aparece en esta imagen y transcríbelo de forma estructurada.')"><i class="ph ph-text-aa"></i> Extraer texto (OCR)</span>
                        <span class="rvc-chip" onclick="applyRomitaModuleVisionPrompt('Genera un prompt cinematográfico en inglés para recrear este estilo visual en Midjourney v6.')"><i class="ph ph-sparkle"></i> Prompt Midjourney</span>
                    </div>
                </div>

                <input type="file" id="romitaModuleFileInput" accept="image/*,.pdf,.txt,.csv,.json" style="display:none;" onchange="handleRomitaModuleFileSelect(this)" />

                <textarea id="chatInput" class="romita-textarea" placeholder="Escribe tu mensaje, pide un plan o usa / para comandos..." rows="1" oninput="autoResize(this)" onkeydown="handleEnter(event)"></textarea>
                
                <!-- In-Composer Generating Indicator Row -->
                <div class="rg-input-generating-row" id="romita-module-generating-row">
                    <div class="rg-thinking-header">
                        <div class="rg-thinking-sparkle-pill">
                            <i class="ph-bold ph-sparkle" id="romita-module-sparkle-icon"></i>
                        </div>
                        <span id="romita-module-status-text" class="rg-thinking-status-text">Romita está procesando el contexto...</span>
                        <div class="rg-thinking-waveform" aria-hidden="true">
                            <span class="rg-wave-bar"></span>
                            <span class="rg-wave-bar"></span>
                            <span class="rg-wave-bar"></span>
                            <span class="rg-wave-bar"></span>
                        </div>
                    </div>
                    <div class="rg-thinking-laser-track">
                        <div class="rg-thinking-laser-bar"></div>
                    </div>
                </div>

                <div class="romita-composer-bottom">
                    <div class="rg-composer-tools-left">
                        <!-- Selector de Especialidad Flotante en el Composer (Estilo Imagen 3) -->
                        <div class="rg-specialty-picker-wrap">
                            <button type="button" class="rg-specialty-pill-btn" id="module-specialty-trigger-btn" onclick="toggleModuleSpecialtyMenu(event)" aria-expanded="false" title="Cambiar especialidad de Romita">
                                <i class="ph ph-compass" id="module-trigger-icon"></i>
                                <span id="module-trigger-label">Directora 360°</span>
                                <i class="ph-bold ph-caret-down rg-caret"></i>
                            </button>
                            <!-- Menú Emergente de Especialidades -->
                            <div class="rg-specialties-popover" id="module-specialties-popover" style="display:none;" onclick="event.stopPropagation()">
                                <button type="button" class="rg-popover-item active" data-spec="director_360" onclick="selectModuleSpecialty('director_360', 'Directora 360°', 'ph-compass')">
                                    <span class="rg-popover-item-icon"><i class="ph ph-compass"></i></span>
                                    <div class="rg-popover-item-content">
                                        <span class="rg-popover-item-title">Directora 360°</span>
                                        <span class="rg-popover-item-desc">Visión integral y coordinación de proyectos</span>
                                    </div>
                                    <span class="rg-popover-item-check"><i class="ph-bold ph-check"></i></span>
                                </button>
                                <button type="button" class="rg-popover-item" data-spec="community_manager" onclick="selectModuleSpecialty('community_manager', 'Senior CM', 'ph-chat-circle-dots')">
                                    <span class="rg-popover-item-icon"><i class="ph ph-chat-circle-dots"></i></span>
                                    <div class="rg-popover-item-content">
                                        <span class="rg-popover-item-title">Senior CM</span>
                                        <span class="rg-popover-item-desc">Copywriting, redes y tono de voz</span>
                                    </div>
                                    <span class="rg-popover-item-check"><i class="ph-bold ph-check"></i></span>
                                </button>
                                <button type="button" class="rg-popover-item" data-spec="branding" onclick="selectModuleSpecialty('branding', 'Branding', 'ph-palette')">
                                    <span class="rg-popover-item-icon"><i class="ph ph-palette"></i></span>
                                    <div class="rg-popover-item-content">
                                        <span class="rg-popover-item-title">Branding</span>
                                        <span class="rg-popover-item-desc">Manual de marca y dirección visual</span>
                                    </div>
                                    <span class="rg-popover-item-check"><i class="ph-bold ph-check"></i></span>
                                </button>
                                <button type="button" class="rg-popover-item" data-spec="marketing" onclick="selectModuleSpecialty('marketing', 'Marketing & Growth', 'ph-trend-up')">
                                    <span class="rg-popover-item-icon"><i class="ph ph-trend-up"></i></span>
                                    <div class="rg-popover-item-content">
                                        <span class="rg-popover-item-title">Marketing & Growth</span>
                                        <span class="rg-popover-item-desc">Embudos, pauta digital y conversión</span>
                                    </div>
                                    <span class="rg-popover-item-check"><i class="ph-bold ph-check"></i></span>
                                </button>
                                <button type="button" class="rg-popover-item" data-spec="seo" onclick="selectModuleSpecialty('seo', 'Especialista SEO', 'ph-magnifying-glass')">
                                    <span class="rg-popover-item-icon"><i class="ph ph-magnifying-glass"></i></span>
                                    <div class="rg-popover-item-content">
                                        <span class="rg-popover-item-title">Especialista SEO</span>
                                        <span class="rg-popover-item-desc">Posicionamiento y optimización de búsqueda</span>
                                    </div>
                                    <span class="rg-popover-item-check"><i class="ph-bold ph-check"></i></span>
                                </button>
                            </div>
                        </div>

                        <!-- Biblioteca de Super-Prompts (Fase 4) -->
                        <button type="button" class="rg-superprompts-btn" onclick="openSuperPromptsModal()" title="Biblioteca de Super-Prompts de la Agencia">
                            <i class="ph-bold ph-sparkle"></i> <span class="hide-mobile">Super-Prompts</span>
                        </button>
                    </div>

                    <!-- Selector Deslizable de Marca / Proyecto en Barra Inferior (Reemplaza instrucciones de comandos) -->
                    <div class="romita-pill-selector-wrap" title="Elegir qué marca o proyecto trabajar">
                        <i class="ph ph-briefcase romita-pill-icon"></i>
                        <select id="activeBrandSelect" class="romita-pill-select" onchange="handleBrandSelection(this.value)">
                            <option value="">Sin Marca (Modo Libre)</option>
                            <?php if (!empty($calendarProjects)): ?>
                                <optgroup label="Proyectos de Calendario (Historial de Meses)">
                                    <?php foreach($calendarProjects as $cp): ?>
                                        <option value="project_<?php echo $cp['project_id']; ?>" 
                                                data-type="project" 
                                                data-id="<?php echo $cp['project_id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($cp['brand_name']); ?>"
                                                data-months="<?php echo $cp['total_months']; ?>"
                                                data-posts="<?php echo $cp['total_posts']; ?>">
                                            <?php echo htmlspecialchars($cp['brand_name']); ?> (<?php echo $cp['total_months']; ?>m • <?php echo $cp['total_posts']; ?>p)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($prepts)): ?>
                                <optgroup label="Marcas Prepts (Instrucción Manual)">
                                    <?php foreach($prepts as $p): ?>
                                        <option value="prept_<?php echo $p['id']; ?>" 
                                                data-type="prept" 
                                                data-id="<?php echo $p['id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($p['name']); ?>">
                                            <?php echo htmlspecialchars($p['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <i class="ph ph-caret-down romita-pill-caret"></i>
                    </div>

                    <!-- Chip de Inteligencia de Marca Activa -->
                    <div class="brand-intel-chip" id="brandIntelChip" style="display:none;"></div>


                    <div style="display: flex; align-items: center; gap: 8px; margin-left: auto;">
                        <button type="button" class="rg-attach-btn" id="btn-romita-attach" onclick="document.getElementById('romitaModuleFileInput').click()" title="Adjuntar imagen o documento (PDF, CSV, TXT)">
                            <i class="ph ph-paperclip"></i>
                        </button>
                        <button type="button" class="rg-mic-btn" id="btn-romita-mic" onclick="toggleRomitaModuleVoiceRecognition()" title="Dictar por voz (Español)">
                            <i class="ph ph-microphone"></i>
                        </button>
                        <button class="romita-send-btn" id="sendBtn" onclick="sendMessage()" title="Enviar mensaje">
                            <i class="ph ph-paper-plane-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Panel Lateral Romita Canvas / Artefactos (Split View) -->
    <section class="romita-canvas-panel" id="romitaCanvasPanel">
        <div class="rcp-header">
            <div class="rcp-header-left">
                <div class="rcp-header-icon"><i class="ph-bold ph-article"></i></div>
                <div class="rcp-title-wrap">
                    <h3 class="rcp-title" id="rcpTitle">Documento Romita</h3>
                    <span class="rcp-subtitle">Artefacto de Trabajo • Roma Canvas</span>
                </div>
            </div>
            <div class="rcp-header-actions">
                <button type="button" class="rcp-btn-action" id="rcpBtnEdit" onclick="toggleCanvasEditMode()" title="Editar texto">
                    <i class="ph ph-pencil-simple"></i> <span class="hide-mobile">Editar</span>
                </button>
                <button type="button" class="rcp-btn-action" onclick="copyCanvasContent()" title="Copiar todo">
                    <i class="ph ph-copy"></i> <span class="hide-mobile">Copiar</span>
                </button>
                <button type="button" class="rcp-btn-action" onclick="downloadCanvasFile('md')" title="Descargar como Markdown">
                    <i class="ph ph-download-simple"></i> <span class="hide-mobile">Descargar</span>
                </button>
                <button type="button" class="rcp-btn-close" onclick="closeRomitaCanvas()" title="Cerrar Canvas">
                    <i class="ph ph-x"></i>
                </button>
            </div>
        </div>
        <div class="rcp-body">
            <div class="rcp-body-view markdown-body" id="rcpBodyView"></div>
            <textarea class="rcp-body-edit" id="rcpBodyEdit" style="display:none;" oninput="onCanvasEditChange()" placeholder="Escribe o edita el contenido aquí..."></textarea>
        </div>
        <div class="rcp-footer">
            <span id="rcpWordCount">0 palabras • 0 caracteres</span>
            <span><i class="ph ph-sparkle"></i> Romita Canvas</span>
        </div>
    </section>
</div>

<!-- Modal de Creación y Edición de Tareas de Romita AI -->
<div id="romita-task-modal-overlay" class="romita-task-modal-overlay" style="display:none;" onclick="handleRomitaTaskModalOverlayClick(event)">
    <div class="romita-task-modal-card" onclick="event.stopPropagation()">
        <div class="rac-modal-header">
            <div class="rac-modal-header-left">
                <span class="rac-icon-pill rac-icon-kanban"><i class="ph-bold ph-kanban"></i></span>
                <div>
                    <h3 class="rac-modal-title" id="romita-task-modal-heading">Configurar Tarea en Kanban</h3>
                    <span class="rac-modal-sub">Edita los detalles antes de insertarla en el tablero</span>
                </div>
            </div>
            <button type="button" class="rac-modal-close" onclick="closeRomitaTaskEditModal()" title="Cerrar"><i class="ph ph-x"></i></button>
        </div>
        <div class="rac-modal-body">
            <input type="hidden" id="rtm-card-id" value="">
            <input type="hidden" id="rtm-task-index" value="0">
            
            <div class="rac-form-group">
                <label for="rtm-title" class="rac-form-label">Título de la Tarea <span class="rac-required">*</span></label>
                <input type="text" id="rtm-title" class="rac-form-input" placeholder="Ej: Diseñar una tarjeta de presentación...">
            </div>

            <div class="rac-form-group">
                <label for="rtm-desc" class="rac-form-label">Descripción / Entregables</label>
                <textarea id="rtm-desc" class="rac-form-textarea" rows="3" placeholder="Detalles, especificaciones o enlaces..."></textarea>
            </div>

            <div class="rac-form-row">
                <div class="rac-form-group" style="flex:1;">
                    <label for="rtm-due-date" class="rac-form-label"><i class="ph ph-calendar"></i> Fecha Límite</label>
                    <input type="date" id="rtm-due-date" class="rac-form-input">
                </div>
                <div class="rac-form-group" style="flex:1;">
                    <label class="rac-form-label"><i class="ph ph-warning"></i> Prioridad</label>
                    <label class="rac-urgent-toggle" for="rtm-urgent">
                        <input type="checkbox" id="rtm-urgent" class="rac-toggle-check">
                        <span class="rac-toggle-label">Marcar como Urgente</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="rac-modal-footer">
            <button type="button" class="btn-rac-modal-cancel" onclick="closeRomitaTaskEditModal()">Cancelar</button>
            <button type="button" class="btn-rac-modal-update" onclick="saveRomitaTaskChangesToCard()">
                <i class="ph ph-check"></i> Actualizar en Chat
            </button>
            <button type="button" class="btn-rac-execute" id="btn-rtm-create-now" onclick="createRomitaTaskDirectlyFromModal()">
                <i class="ph-bold ph-plus-circle"></i> Crear en Kanban Ahora
            </button>
        </div>
    </div>
</div>

<?php if($is_admin): ?>
<!-- Modal de Gestión de Skills -->
<div class="romita-modal-overlay" id="modal-skills">
    <div class="romita-modal-card">
        <div class="romita-modal-header">
            <h3><i class="ph ph-sliders" style="color: #6366f1;"></i> Gestión de Skills</h3>
            <button type="button" class="btn-close-modal" onclick="document.getElementById('modal-skills').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <div class="romita-modal-body">
            <form id="form-skill" onsubmit="event.preventDefault(); saveSkill();">
                <input type="hidden" id="skillId" name="skill_id">
                <div style="margin-bottom: 0.85rem;">
                    <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Nombre del Skill</label>
                    <input type="text" id="skillName" name="name" class="form-control" required placeholder="Ej: Especialista SEO" style="border-radius:8px;">
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Rol Permitido</label>
                    <select id="skillRole" class="form-control" style="border-radius:8px;">
                        <option value="all">Todos los usuarios</option>
                        <option value="admin">Solo Administradores</option>
                        <option value="ventas">Área de Ventas</option>
                        <option value="marketing">Área de Marketing</option>
                    </select>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Prompt Base (Instrucción de Sistema)</label>
                    <textarea id="skillPrompt" class="form-control" rows="4" placeholder="Ej: Eres un experto en... Puedes usar variables como [Tema] para pedírselas al usuario al hacer clic." style="border-radius:8px;"></textarea>
                    <small style="color:var(--romita-text-muted); font-size:0.75rem; display:block; margin-top:0.3rem;">Tip: Usa corchetes para solicitar datos dinámicos, ej: <code>Redacta un post sobre [Tema] para [Red Social]</code>.</small>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <button type="button" class="btn btn-outline text-danger" id="btnDeleteSkill" onclick="deleteSkill()" style="display:none; border-radius:8px;"><i class="ph ph-trash"></i> Eliminar</button>
                    <button type="submit" class="btn btn-primary" style="border-radius:8px; margin-left:auto;"><i class="ph ph-floppy-disk"></i> Guardar Skill</button>
                </div>
            </form>
            
            <hr style="margin: 1.25rem 0; border-color: var(--romita-border);">
            
            <h4 style="font-size:0.9rem; font-weight:700; margin-bottom:0.75rem;">Skills Configurados</h4>
            <div id="skillsListAdmin" style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 200px; overflow-y: auto;">
                <?php foreach($skills as $s): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--romita-card-hover); padding:0.6rem 0.85rem; border-radius:8px; border:1px solid var(--romita-border);">
                        <div>
                            <strong style="font-size:0.85rem;"><?php echo htmlspecialchars($s['name']); ?></strong>
                            <div style="font-size:0.75rem; color:var(--romita-text-muted);"><?php echo htmlspecialchars($s['description']); ?></div>
                        </div>
                        <button class="btn btn-sm btn-outline" onclick="editSkill(<?php echo $s['id']; ?>, '<?php echo addslashes($s['name']); ?>', '<?php echo addslashes($s['prompt_base']); ?>', '<?php echo $s['allowed_role']; ?>')" style="border-radius:6px;"><i class="ph ph-pencil"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Gestión de Prepts -->
<div class="romita-modal-overlay" id="modal-prepts">
    <div class="romita-modal-card" style="max-width: 580px;">
        <div class="romita-modal-header">
            <h3><i class="ph ph-buildings" style="color: #8b5cf6;"></i> Manual de Tono & Memoria de Marca (Prepts)</h3>
            <button type="button" class="btn-close-modal" onclick="document.getElementById('modal-prepts').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <div class="romita-modal-body">
            <form id="form-prept" onsubmit="event.preventDefault(); savePrept();">
                <input type="hidden" id="preptId" name="prept_id">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 0.85rem;">
                    <div>
                        <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Nombre de la Marca <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="preptName" class="form-control" required placeholder="Ej: Roma Agencia" style="border-radius:8px;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Arquetipo de Marca</label>
                        <input type="text" id="preptArchetype" class="form-control" placeholder="Ej: El Sabio, El Creador, El Héroe..." style="border-radius:8px;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 0.85rem;">
                    <div>
                        <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Tono de voz</label>
                        <input type="text" id="preptTone" class="form-control" placeholder="Ej: Profesional, disruptivo, empático..." style="border-radius:8px;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Audiencia objetivo</label>
                        <input type="text" id="preptAudience" class="form-control" placeholder="Ej: Emprendedores B2B, CEOs..." style="border-radius:8px;">
                    </div>
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Palabras Prohibidas (Blacklist Romita)</label>
                    <input type="text" id="preptForbiddenWords" class="form-control" placeholder="Ej: barato, gratis, el mejor del mundo, 100% garantizado (separadas por comas)" style="border-radius:8px;">
                    <small style="color:var(--romita-text-muted); font-size:0.73rem; display:block; margin-top:2px;">Romita NUNCA usará estas palabras en copys ni propuestas para esta marca.</small>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Reglas de contenido adicionales</label>
                    <textarea id="preptRules" class="form-control" rows="3" placeholder="Ej: Siempre usar lenguaje positivo, usar datos verídicos, nunca comparar con competidores..." style="border-radius:8px;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; border-radius:8px;"><i class="ph ph-floppy-disk"></i> Guardar Memoria de Marca</button>
            </form>
            
            <hr style="margin: 1.25rem 0; border-color: var(--romita-border);">
            
            <h4 style="font-size:0.9rem; font-weight:700; margin-bottom:0.75rem;">Marcas Registradas</h4>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 180px; overflow-y: auto;">
                <?php foreach($prepts as $p): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--romita-card-hover); padding:0.6rem 0.85rem; border-radius:8px; border:1px solid var(--romita-border);">
                        <div>
                            <strong style="font-size:0.85rem;"><?php echo htmlspecialchars($p['name']); ?></strong>
                            <div style="font-size:0.75rem; color:var(--romita-text-muted);">
                                <?php if (!empty($p['archetype'])): ?><span class="badge" style="background:rgba(99,102,241,0.15); color:#6366f1; padding:2px 6px; border-radius:4px; font-size:0.68rem; margin-right:4px;"><?php echo htmlspecialchars($p['archetype']); ?></span><?php endif; ?>
                                <?php echo htmlspecialchars($p['tone'] ?: 'Sin tono especificado'); ?>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline" onclick="editPrept(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', '<?php echo addslashes($p['tone'] ?? ''); ?>', '<?php echo addslashes($p['archetype'] ?? ''); ?>', '<?php echo addslashes($p['audience'] ?? ''); ?>', '<?php echo addslashes($p['rules'] ?? ''); ?>', '<?php echo addslashes($p['forbidden_words'] ?? ''); ?>')" style="border-radius:6px;"><i class="ph ph-pencil"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Biblioteca de Super-Prompts de la Agencia (Fase 4) -->
<div class="romita-modal-overlay" id="modal-super-prompts">
    <div class="romita-modal-card" style="max-width: 820px; width: 95%;">
        <div class="romita-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg, #1d4ed8, #38bdf8); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.15rem; box-shadow: 0 4px 12px rgba(56, 189, 248, 0.25);">
                    <i class="ph-bold ph-sparkle"></i>
                </div>
                <div>
                    <h3 style="margin:0; font-size:1.05rem;">Biblioteca de Super-Prompts</h3>
                    <span style="font-size:0.75rem; color:var(--romita-text-muted);">Plantillas probadas de alto rendimiento para el equipo de ROMA</span>
                </div>
            </div>
            <button type="button" class="btn-close-modal" onclick="closeSuperPromptsModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="romita-modal-body" style="padding: 16px 22px;">
            <!-- Buscador y Filtro por Categoría -->
            <div style="display:flex; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                <div style="position:relative; flex:1; min-width:220px;">
                    <i class="ph ph-magnifying-glass" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--romita-text-muted);"></i>
                    <input type="text" id="spSearchInput" placeholder="Buscar super-prompt por palabra clave..." class="form-control" style="padding-left:36px; border-radius:9999px; height:38px; font-size:0.85rem;" onkeyup="filterSuperPrompts()">
                </div>
                <button type="button" class="btn btn-outline" onclick="toggleCreatePromptForm()" style="border-radius:9999px; height:38px; font-size:0.82rem; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                    <i class="ph-bold ph-plus"></i> Guardar Nuevo Prompt
                </button>
            </div>

            <!-- Formulario Colapsable para Crear Nuevo Prompt -->
            <div id="spCreateFormWrap" style="display:none; background:var(--romita-card-hover); border:1px solid var(--romita-border); border-radius:12px; padding:14px; margin-bottom:14px;">
                <h5 style="margin:0 0 10px 0; font-size:0.88rem; font-weight:700;"><i class="ph-bold ph-floppy-disk"></i> Nuevo Super-Prompt</h5>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                    <input type="text" id="newSpTitle" class="form-control" placeholder="Título del Prompt (Ej: Auditoría SEO Técnica)">
                    <select id="newSpCategory" class="form-control">
                        <option value="Redes Sociales">Redes Sociales</option>
                        <option value="Contenido Educativo">Contenido Educativo</option>
                        <option value="Copywriting & Ads">Copywriting & Ads</option>
                        <option value="Estrategia & Research">Estrategia & Research</option>
                        <option value="Ventas & Clientes">Ventas & Clientes</option>
                        <option value="Planificación">Planificación</option>
                    </select>
                </div>
                <textarea id="newSpPrompt" class="form-control" rows="3" placeholder="Instrucción detallada para Romita. Puedes usar [Corchetes] para variables..." style="margin-bottom:10px;"></textarea>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="toggleCreatePromptForm()">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-primary" onclick="saveNewSuperPrompt()"><i class="ph ph-check"></i> Guardar</button>
                </div>
            </div>

            <!-- Tabs de Filtro de Categoría -->
            <div class="sp-filter-tabs">
                <button type="button" class="sp-tab-btn active" data-cat="all" onclick="filterCategorySp('all', this)">Todos</button>
                <button type="button" class="sp-tab-btn" data-cat="Redes Sociales" onclick="filterCategorySp('Redes Sociales', this)">Redes Sociales</button>
                <button type="button" class="sp-tab-btn" data-cat="Contenido Educativo" onclick="filterCategorySp('Contenido Educativo', this)">Contenido Educativo</button>
                <button type="button" class="sp-tab-btn" data-cat="Copywriting & Ads" onclick="filterCategorySp('Copywriting & Ads', this)">Copy & Ads</button>
                <button type="button" class="sp-tab-btn" data-cat="Ventas & Clientes" onclick="filterCategorySp('Ventas & Clientes', this)">Ventas & Role-Play</button>
                <button type="button" class="sp-tab-btn" data-cat="Estrategia & Research" onclick="filterCategorySp('Estrategia & Research', this)">Estrategia</button>
                <button type="button" class="sp-tab-btn" data-cat="Planificación" onclick="filterCategorySp('Planificación', this)">Planificación</button>
            </div>

            <!-- Grid de Prompts -->
            <div class="superprompt-grid" id="superPromptsList">
                <?php foreach($superPrompts as $sp): ?>
                <div class="superprompt-card" data-category="<?php echo htmlspecialchars($sp['category']); ?>" data-title="<?php echo htmlspecialchars(strtolower($sp['title'])); ?>">
                    <div>
                        <div class="sp-header">
                            <div class="sp-icon-box"><i class="ph <?php echo htmlspecialchars($sp['icon'] ?: 'ph-sparkle'); ?>"></i></div>
                            <div class="sp-meta">
                                <span class="sp-category-pill"><?php echo htmlspecialchars($sp['category']); ?></span>
                                <h4 class="sp-title"><?php echo htmlspecialchars($sp['title']); ?></h4>
                            </div>
                        </div>
                        <p class="sp-desc"><?php echo htmlspecialchars($sp['description'] ?: substr($sp['prompt'], 0, 90) . '...'); ?></p>
                    </div>
                    <div class="sp-actions">
                        <span style="font-size:0.7rem; color:var(--romita-text-muted);">
                            <?php echo !empty($sp['is_agency_template']) ? 'Plantilla Agencia' : 'Mi Prompt'; ?>
                        </span>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <button type="button" class="btn-sp-copy" onclick="copySuperPromptText(<?php echo htmlspecialchars(json_encode($sp['prompt'])); ?>)" title="Copiar al portapapeles">
                                <i class="ph ph-copy"></i>
                            </button>
                            <button type="button" class="btn-sp-use" onclick="useSuperPromptText(<?php echo htmlspecialchars(json_encode($sp['prompt'])); ?>)">
                                <i class="ph-bold ph-arrow-elbow-down-right"></i> Usar Prompt
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Preferencias de Usuario & Memoria Permanente (Fase 4) -->
<div class="romita-modal-overlay" id="modal-user-prefs">
    <div class="romita-modal-card" style="max-width: 560px; width: 95%;">
        <div class="romita-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg, #4f46e5, #06b6d4); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.15rem;">
                    <i class="ph-bold ph-brain"></i>
                </div>
                <div>
                    <h3 style="margin:0; font-size:1.05rem;">Preferencias y Memoria de Romita</h3>
                    <span style="font-size:0.75rem; color:var(--romita-text-muted);">Personaliza cómo Romita responde y recuerda tus preferencias</span>
                </div>
            </div>
            <button type="button" class="btn-close-modal" onclick="closeUserPrefsModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="romita-modal-body" style="padding: 18px 22px;">
            <div style="margin-bottom: 1.25rem;">
                <label style="display:block; margin-bottom:0.4rem; font-weight:700; font-size:0.85rem;">Estilo de Respuesta Predeterminado</label>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <label style="display:flex; align-items:flex-start; gap:10px; padding:10px 14px; border:1.5px solid var(--romita-border); border-radius:10px; cursor:pointer; background:var(--romita-bg);">
                        <input type="radio" name="pref_response_style" value="strategic" <?php echo ($userPrefs['response_style'] === 'strategic') ? 'checked' : ''; ?> style="margin-top:3px;">
                        <div>
                            <strong style="font-size:0.85rem; color:var(--romita-text); display:block;">📐 Modo Estratégico 360° (Recomendado)</strong>
                            <span style="font-size:0.75rem; color:var(--romita-text-muted);">Tablas ordenables, marcos de trabajo (AIDA/PAS), pasos fundamentados y visión integral.</span>
                        </div>
                    </label>
                    <label style="display:flex; align-items:flex-start; gap:10px; padding:10px 14px; border:1.5px solid var(--romita-border); border-radius:10px; cursor:pointer; background:var(--romita-bg);">
                        <input type="radio" name="pref_response_style" value="executive" <?php echo ($userPrefs['response_style'] === 'executive') ? 'checked' : ''; ?> style="margin-top:3px;">
                        <div>
                            <strong style="font-size:0.85rem; color:var(--romita-text); display:block;">⚡ Modo Ejecutivo y Breve</strong>
                            <span style="font-size:0.75rem; color:var(--romita-text-muted);">Ultra-sintético, viñetas de alta densidad, conclusiones operativas directas sin rodeos.</span>
                        </div>
                    </label>
                    <label style="display:flex; align-items:flex-start; gap:10px; padding:10px 14px; border:1.5px solid var(--romita-border); border-radius:10px; cursor:pointer; background:var(--romita-bg);">
                        <input type="radio" name="pref_response_style" value="creative" <?php echo ($userPrefs['response_style'] === 'creative') ? 'checked' : ''; ?> style="margin-top:3px;">
                        <div>
                            <strong style="font-size:0.85rem; color:var(--romita-text); display:block;">✨ Modo Creativo & Publicitario</strong>
                            <span style="font-size:0.75rem; color:var(--romita-text-muted);">Enfoque persuasivo de agencia, copywriting cautivador, ganchos dinámicos y storytelling.</span>
                        </div>
                    </label>
                </div>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label style="display:block; margin-bottom:0.4rem; font-weight:700; font-size:0.85rem;">Instrucciones Permanentes para Romita (Memoria)</label>
                <textarea id="prefCustomInstructions" class="form-control" rows="4" placeholder="Ej: Háblame siempre de tú. Prefiero que los copys de Instagram lleven hashtags al final. Cuando pida un reel incluye siempre un gancho de 3 segundos." style="border-radius:10px; font-size:0.83rem;"><?php echo htmlspecialchars($userPrefs['custom_instructions'] ?? ''); ?></textarea>
                <small style="color:var(--romita-text-muted); font-size:0.74rem; display:block; margin-top:4px;">Romita recordará estas directrices en todas tus conversaciones futuras.</small>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-outline" onclick="closeUserPrefsModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveUserPreferences()"><i class="ph ph-floppy-disk"></i> Guardar Preferencias</button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentSpecialty = 'director_360';
    let romitaModuleStatusInterval = null;
    let activeSkill = null;
    let chatHistory = [];
    let currentChatId = null;
    let allCachedChats = [];
    let selectedBrand = null;
    
    // Auto-ajuste de altura de textarea y monitor de slash commands
    function autoResize(el) {
        el.style.height = 'auto';
        el.style.height = (el.scrollHeight < 180 ? el.scrollHeight : 180) + 'px';
        handleRomitaModuleInputChanged(el);
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('romitaSidebar');
        const backdrop = document.getElementById('romitaSidebarBackdrop');
        const isOpen = sidebar.classList.toggle('open');
        if (backdrop) {
            backdrop.classList.toggle('active', isOpen);
        }
    }

    function usePromptStarter(template) {
        const input = document.getElementById('chatInput');
        input.value = template;
        autoResize(input);
        input.focus();
        const firstBracket = template.indexOf('[');
        const lastBracket = template.indexOf(']');
        if (firstBracket !== -1 && lastBracket !== -1) {
            input.setSelectionRange(firstBracket, lastBracket + 1);
        }
    }

    // Manejador del Selector de Marcas y Proyectos
    function handleBrandSelection(val) {
        const select = document.getElementById('activeBrandSelect');
        const chip = document.getElementById('brandIntelChip');
        
        if (!val) {
            selectedBrand = null;
            chip.style.display = 'none';
            renderDefaultPromptStarters();
            return;
        }

        const opt = select.options[select.selectedIndex];
        const type = opt.dataset.type; // 'project' or 'prept'
        const id = opt.dataset.id;
        const name = opt.dataset.name;
        const months = opt.dataset.months || '0';
        const posts = opt.dataset.posts || '0';

        selectedBrand = { type, id, name, months, posts };

        if (type === 'project') {
            chip.style.display = 'inline-flex';
            chip.innerHTML = `<i class="ph ph-calendar-check"></i> <span><strong>${name}</strong>: ${months} meses • ${posts} posts</span>`;
            renderBrandPromptStarters(name);
        } else {
            chip.style.display = 'inline-flex';
            chip.innerHTML = `<i class="ph ph-briefcase"></i> <span>Marca: <strong>${name}</strong></span>`;
            renderBrandPromptStarters(name);
        }
    }

    // Renderizar prompt starters según contexto de marca
    function renderBrandPromptStarters(brandName) {
        const grid = document.getElementById('promptGrid');
        if (!grid) return;

        grid.innerHTML = `
            <div class="prompt-starter-card" onclick="usePromptStarter('Analiza el histórico de publicaciones de ${brandName}: ¿qué pilares, formatos y temas se han trabajado en los meses previos?')">
                <div class="prompt-starter-icon analysis">
                    <i class="ph ph-chart-donut"></i>
                </div>
                <div class="prompt-starter-details">
                    <h4>Auditar Calendario de ${brandName}</h4>
                    <p>Revisa qué se ha publicado, pilares cubiertos y oportunidades detectadas.</p>
                </div>
            </div>

            <div class="prompt-starter-card" onclick="usePromptStarter('Genera una propuesta de calendario para el próximo mes de ${brandName} con 8 publicaciones estratégicas sin repetir temas anteriores.')">
                <div class="prompt-starter-icon copywriting">
                    <i class="ph ph-calendar-star"></i>
                </div>
                <div class="prompt-starter-details">
                    <h4>Planificar Próximo Mes</h4>
                    <p>Ideas de contenido frescas alineadas con los pilares de ${brandName}.</p>
                </div>
            </div>

            <div class="prompt-starter-card" onclick="usePromptStarter('Escribe 3 copys persuasivos con ganchos al estilo de ${brandName} para promocionar [Servicio / Novedad].')">
                <div class="prompt-starter-icon sales">
                    <i class="ph ph-feather"></i>
                </div>
                <div class="prompt-starter-details">
                    <h4>Redactar Copys para ${brandName}</h4>
                    <p>Copys completos con llamadas a la acción acordes a su tono habitual.</p>
                </div>
            </div>

            <div class="prompt-starter-card" onclick="usePromptStarter('Crea un plan mensual completo para ${brandName} en [Mes / Año] estructurado para crearlo en el calendario.')">
                <div class="prompt-starter-icon automation">
                    <i class="ph ph-rocket-launch"></i>
                </div>
                <div class="prompt-starter-details">
                    <h4>Crear Mes en Calendario</h4>
                    <p>Estructura publicaciones listas para guardar directamente en el proyecto.</p>
                </div>
            </div>
        `;
    }

    const specialtyHeroData = {
        'director_360': {
            sub: 'Soy Romita en rol de Directora 360°. Coordino branding, contenido, desarrollo web, finanzas y performance con visión ejecutiva.',
            starters: [
                { icon: 'ph-kanban', title: 'Auditoría 360° de Proyectos', desc: 'Revisión global de producción, entregables y estados en la agencia.', prompt: '¿Cuál es el estado general de los proyectos activos y prioridades en la agencia?' },
                { icon: 'ph-lightbulb', title: 'Estrategia Multicanal', desc: 'Conexión de branding, redes, web y audiovisual.', prompt: 'Diseña una estrategia multicanal integrada conectando branding, contenido y performance.' },
                { icon: 'ph-funnel', title: 'Embudo de Conversión', desc: 'Estructura estratégica TOFU-MOFU-BOFU.', prompt: '¿Cómo podemos estructurar un embudo comercial TOFU-MOFU-BOFU de alta conversión?' },
                { icon: 'ph-chart-line-up', title: 'Optimización de Crecimiento', desc: 'Palancas de rentabilidad y mejora continua.', prompt: '¿Cuáles son las 4 palancas operativas y estratégicas clave para optimizar la rentabilidad de las cuentas?' }
            ]
        },
        'community_manager': {
            sub: 'Soy Romita como Senior Community Manager & Copywriter. Especialista en copys magnéticos, ganchos virales, método AIDA, hashtags estratégicos y publicaciones para Month Board.',
            starters: [
                { icon: 'ph-feather', title: 'Copy Persuasivo (Método AIDA)', desc: 'Gancho magnético, cuerpo persuasivo y CTA claro.', prompt: 'Redacta un copy persuasivo aplicando la fórmula AIDA (Atención, Interés, Deseo, Acción) para un post sobre: ' },
                { icon: 'ph-calendar-check', title: 'Generar Posts para Month Board', desc: 'Inyección estructurada de publicaciones con 1 clic.', prompt: 'Genera 4 publicaciones estratégicas con concepto, copy completo, formato, pilar y hashtags para el Month Board de ' },
                { icon: 'ph-hash', title: '10 Hashtags Estratégicos', desc: 'Optimizados para el tema, nicho y redes.', prompt: 'Genera exactamente 10 a 12 hashtags estratégicos de alto impacto y alcance para: ' },
                { icon: 'ph-lightning', title: '5 Ganchos para Reels', desc: 'Fórmulas de retención para primeros 3 segundos.', prompt: 'Dame 5 ganchos magnéticos para Reels de nuestras marcas este mes.' }
            ]
        },
        'branding': {
            sub: 'Soy Romita como Especialista en Branding. Construcción de identidad, arquetipos de marca, tono de voz y coherencia visual.',
            starters: [
                { icon: 'ph-paint-brush-broad', title: 'Arquetipo de Marca', desc: 'Definición de personalidad y valores de marca.', prompt: '¿Cómo definir el arquetipo de personalidad para una de nuestras marcas?' },
                { icon: 'ph-megaphone', title: 'Tono y Voz de Marca', desc: 'Guía de comunicación y vocabulario clave.', prompt: 'Estructura una guía de tono de voz: qué decimos, cómo lo decimos y qué evitamos.' },
                { icon: 'ph-eye', title: 'Auditoría de Identidad', desc: 'Revisión de consistencia visual.', prompt: '¿Qué elementos debemos auditar para garantizar coherencia en manual de marca?' },
                { icon: 'ph-book-open', title: 'Storytelling Corporativo', desc: 'Narrativa del origen y propuesta de valor.', prompt: '¿Cómo redactar un manifiesto de marca inspirador y memorable?' }
            ]
        },
        'marketing': {
            sub: 'Soy Romita como Growth Marketer. Embudos de adquisición, pauta publicitaria (Ads), métricas de rendimiento y CRO.',
            starters: [
                { icon: 'ph-funnel', title: 'Embudo de Ventas (Funnels)', desc: 'Flujo completo de lead a cliente recurrente.', prompt: 'Diseña un embudo de ventas TOFU-MOFU-BOFU con oferta gancho y retargeting.' },
                { icon: 'ph-currency-dollar', title: 'Estrategia de Meta Ads', desc: 'Estructura de campañas ABO/CBO y audiencias.', prompt: '¿Cómo estructurar una campaña de Meta Ads rentable para captar clientes calificados?' },
                { icon: 'ph-chart-pie-slice', title: 'Optimización de CRO', desc: 'Mejora de conversión en páginas de destino.', prompt: '¿Cuáles son los 5 puntos críticos para aumentar la tasa de conversión en una landing page?' },
                { icon: 'ph-arrows-clockwise', title: 'Reactivación de Clientes', desc: 'Secuencia de remarketing por WhatsApp.', prompt: 'Crea una secuencia de 3 mensajes para reactivar cotizaciones o leads antiguos.' }
            ]
        },
        'seo': {
            sub: 'Soy Romita como Especialista en SEO. Posicionamiento orgánico en Google, intención de búsqueda y arquitectura web.',
            starters: [
                { icon: 'ph-magnifying-glass', title: 'Keyword Research', desc: 'Palabras clave transaccionales de alta intención.', prompt: '¿Cómo investigar palabras clave transaccionales para los servicios de la agencia?' },
                { icon: 'ph-article', title: 'Optimización On-Page', desc: 'Estructura de H1, H2, meta title y URLs.', prompt: 'Dame una checklist de optimización SEO On-Page para un artículo o servicio.' },
                { icon: 'ph-tree-structure', title: 'Topic Clusters', desc: 'Arquitectura de pilares y enlaces internos.', prompt: 'Explica cómo armar una estrategia de Topic Clusters para posicionar en Google.' },
                { icon: 'ph-speedometer', title: 'SEO Técnico Básico', desc: 'Velocidad, Core Web Vitals y schema markup.', prompt: '¿Qué aspectos técnicos de SEO debemos auditar antes de lanzar una página web?' }
            ]
        }
    };

    function toggleModuleSpecialtyMenu(event) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        const popover = document.getElementById('module-specialties-popover');
        const trigger = document.getElementById('module-specialty-trigger-btn');
        if (!popover) return;
        const isVisible = popover.style.display !== 'none';
        if (isVisible) {
            closeModuleSpecialtyMenu();
        } else {
            popover.style.display = 'flex';
            if (trigger) trigger.classList.add('is-active');
        }
    }

    function closeModuleSpecialtyMenu() {
        const popover = document.getElementById('module-specialties-popover');
        const trigger = document.getElementById('module-specialty-trigger-btn');
        if (popover) popover.style.display = 'none';
        if (trigger) trigger.classList.remove('is-active');
    }

    function selectModuleSpecialty(spec, name, icon) {
        currentSpecialty = spec;
        const triggerLabel = document.getElementById('module-trigger-label');
        const triggerIcon = document.getElementById('module-trigger-icon');
        if (triggerLabel) triggerLabel.textContent = name;
        if (triggerIcon) triggerIcon.className = 'ph ' + icon;

        document.querySelectorAll('#module-specialties-popover .rg-popover-item').forEach(item => {
            if (item.dataset.spec === spec) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        closeModuleSpecialtyMenu();

        const heroSub = document.getElementById('heroSubtext');
        const data = specialtyHeroData[spec] || specialtyHeroData['director_360'];
        if (heroSub && !selectedBrand) {
            heroSub.innerText = data.sub;
        }

        if (!selectedBrand) {
            renderDefaultPromptStarters();
        }
    }

    // Cerrar popover al hacer clic fuera
    document.addEventListener('click', function(e) {
        const popover = document.getElementById('module-specialties-popover');
        const trigger = document.getElementById('module-specialty-trigger-btn');
        if (popover && popover.style.display !== 'none') {
            if (!popover.contains(e.target) && !trigger.contains(e.target)) {
                closeModuleSpecialtyMenu();
            }
        }
    });

    function renderDefaultPromptStarters() {
        const grid = document.getElementById('promptGrid');
        if (!grid) return;

        const data = specialtyHeroData[currentSpecialty] || specialtyHeroData['director_360'];
        grid.innerHTML = data.starters.map(s => `
            <div class="prompt-starter-card" onclick="usePromptStarter('${s.prompt.replace(/'/g, "\\'")}')">
                <div class="prompt-starter-icon analysis">
                    <i class="ph ${s.icon}"></i>
                </div>
                <div class="prompt-starter-details">
                    <h4>${s.title}</h4>
                    <p>${s.desc}</p>
                </div>
            </div>
        `).join('');
    }

    // Búsqueda en conversación activa
    function searchChat(query) {
        const text = query.toLowerCase().trim();
        document.querySelectorAll('#chatStreamInner .romita-message').forEach(msg => {
            const bubble = msg.querySelector('.message-bubble');
            if (!bubble) return;
            if (text === '' || bubble.textContent.toLowerCase().includes(text)) {
                msg.style.display = 'flex';
            } else {
                msg.style.display = 'none';
            }
        });
    }

    // Control del buscador inline desplegable en el header moderno
    function toggleHeaderSearch() {
        const bar = document.getElementById('romitaInlineSearchBar');
        const input = document.getElementById('chatSearch');
        if (!bar) return;
        if (bar.style.display === 'none' || !bar.style.display) {
            bar.style.display = 'flex';
            if (input) {
                input.focus();
                input.select();
            }
        } else {
            clearAndCloseSearch();
        }
    }

    function clearAndCloseSearch() {
        const bar = document.getElementById('romitaInlineSearchBar');
        const input = document.getElementById('chatSearch');
        if (input) {
            input.value = '';
            searchChat('');
        }
        if (bar) bar.style.display = 'none';
    }

    // Control del menú de opciones y herramientas (···) en el header moderno
    function toggleRomitaOptionsMenu(e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('romitaOptionsDropdown');
        if (!menu) return;
        const isOpen = menu.style.display === 'block';
        menu.style.display = isOpen ? 'none' : 'block';
    }

    function closeRomitaOptionsMenu() {
        const menu = document.getElementById('romitaOptionsDropdown');
        if (menu) menu.style.display = 'none';
    }

    // Cerrar menús al hacer clic fuera
    document.addEventListener('click', function(e) {
        const menuWrap = document.querySelector('.romita-options-menu-wrap');
        if (menuWrap && !menuWrap.contains(e.target)) {
            closeRomitaOptionsMenu();
        }
        const searchWrap = document.getElementById('romitaInlineSearchWrap');
        if (searchWrap && !searchWrap.contains(e.target)) {
            const input = document.getElementById('chatSearch');
            if (input && input.value.trim() === '') {
                clearAndCloseSearch();
            }
        }
    });

    // Filtro en vivo del historial en el sidebar
    function filterSidebarChats(query) {
        const text = query.toLowerCase().trim();
        const items = document.querySelectorAll('#chatList .chat-history-item');
        items.forEach(item => {
            const title = item.querySelector('.chat-history-title')?.textContent.toLowerCase() || '';
            item.style.display = (text === '' || title.includes(text)) ? 'flex' : 'none';
        });
    }

    // Cargar historial al iniciar
    document.addEventListener('DOMContentLoaded', () => {
        renderDefaultPromptStarters();
        loadChatHistoryList();
        updateRomitaSoundButtons();
        initRomitaModuleMultimodalListeners();

        <?php if (!empty($sharedChat) && !empty($sharedMessages)): ?>
        loadSharedChatMessages(<?php echo json_encode($sharedMessages); ?>, <?php echo (int)$sharedChat['id']; ?>);
        <?php endif; ?>

        <?php if (!empty($userPrefs['default_specialty']) && $userPrefs['default_specialty'] !== 'director_360'): ?>
        const defSpec = '<?php echo addslashes($userPrefs['default_specialty']); ?>';
        const specItem = document.querySelector(`#module-specialties-popover .rg-popover-item[data-spec="${defSpec}"]`);
        if (specItem) {
            const specTitle = specItem.querySelector('.rg-popover-item-title').textContent.trim();
            const specIconEl = specItem.querySelector('.rg-popover-item-icon i');
            const specIcon = specIconEl ? specIconEl.className.replace('ph ', '') : 'ph-sparkle';
            selectModuleSpecialty(defSpec, specTitle, specIcon);
        }
        <?php endif; ?>

        document.addEventListener('click', function(e) {
            const slashMenu = document.getElementById('rg-slash-menu');
            if (slashMenu && slashMenu.style.display !== 'none' && !slashMenu.contains(e.target) && e.target.id !== 'chatInput') {
                slashMenu.style.display = 'none';
            }
        });
    });

    function loadChatHistoryList() {
        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_chats'
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                allCachedChats = data.chats;
                renderChatList(data.chats);
            }
        });
    }

    function renderChatList(chats) {
        const list = document.getElementById('chatList');
        list.innerHTML = '';
        if (chats.length === 0) {
            list.innerHTML = '<div style="padding:1.5rem; text-align:center; color:var(--romita-text-muted); font-size:0.8rem;">No hay chats previos</div>';
            return;
        }

        chats.forEach(chat => {
            const div = document.createElement('div');
            div.className = `chat-history-item ${chat.id == currentChatId ? 'active' : ''}`;
            div.innerHTML = `
                <div class="chat-history-content">
                    <i class="ph ph-chat-teardrop-text"></i>
                    <span class="chat-history-title" title="${chat.title}">${chat.title}</span>
                </div>
                <button class="chat-history-delete" onclick="deleteChat(${chat.id}, event)" title="Eliminar conversación">
                    <i class="ph ph-trash"></i>
                </button>
            `;
            div.onclick = (e) => {
                if(!e.target.closest('.chat-history-delete')) {
                    loadChatMessages(chat.id);
                }
            };
            list.appendChild(div);
        });
    }

    function deleteChat(chatId, event) {
        if (event) event.stopPropagation();
        if (!confirm('¿Estás seguro de eliminar esta conversación permanentemente?')) return;

        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=delete_chat&chat_id=${chatId}`
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                if (currentChatId == chatId) {
                    resetToEmptyState();
                }
                loadChatHistoryList();
                if(window.showToast) window.showToast('Conversación eliminada', 'info');
            } else {
                alert(data.error || 'No se pudo eliminar el chat');
            }
        });
    }

    function resetToEmptyState() {
        const container = document.getElementById('chatStreamInner');
        const messages = container.querySelectorAll('.romita-message');
        messages.forEach(m => m.remove());
        const emptyState = document.getElementById('emptyState');
        if(emptyState) emptyState.style.display = 'flex';
        chatHistory = [];
        currentChatId = null;
        document.querySelectorAll('.skill-pill').forEach(p => p.classList.remove('active'));
        activeSkill = null;

        if (window.innerWidth < 768) {
            document.getElementById('romitaSidebar').classList.remove('open');
            const backdrop = document.getElementById('romitaSidebarBackdrop');
            if (backdrop) backdrop.classList.remove('active');
        }
    }

    function loadChatMessages(chat_id) {
        currentChatId = chat_id;
        
        const container = document.getElementById('chatStreamInner');
        const messages = container.querySelectorAll('.romita-message');
        messages.forEach(m => m.remove());
        
        const emptyState = document.getElementById('emptyState');
        if(emptyState) emptyState.style.display = 'none';

        chatHistory = [];
        
        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_messages&chat_id=${chat_id}`
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                if(window.innerWidth < 768) {
                    document.getElementById('romitaSidebar').classList.remove('open');
                    const backdrop = document.getElementById('romitaSidebarBackdrop');
                    if (backdrop) backdrop.classList.remove('active');
                }
                loadChatHistoryList();
                
                data.messages.forEach(msg => {
                    let actualRole = msg.role;
                    // Fallback de seguridad para mensajes que pudieran venir como user por registros antiguos
                    if (actualRole === 'user' && (
                        msg.content.includes('"project_id"') || 
                        msg.content.includes('|---|') || 
                        msg.content.includes('| :---') ||
                        msg.content.startsWith('¡Hola') || 
                        msg.content.startsWith('¡Excelente') ||
                        msg.content.startsWith('¡Perfecto') ||
                        msg.content.startsWith('Como tu experto') ||
                        msg.content.length > 300
                    )) {
                        actualRole = 'assistant';
                    }
                    chatHistory.push({role: actualRole, content: msg.content, id: msg.id});
                    addMessageToUI(actualRole, msg.content, msg.id, msg.feedback, msg.attachment_url, msg.attachment_type, msg.attachment_name);
                });
            }
        });
    }

    function handleEnter(e) {
        const menu = document.getElementById('rg-slash-menu');
        const isMenuOpen = menu && menu.style.display !== 'none';

        if (isMenuOpen) {
            const visibleItems = Array.from(menu.querySelectorAll('.rg-slash-item')).filter(it => it.style.display !== 'none');
            let activeIdx = visibleItems.findIndex(it => it.classList.contains('active'));

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (visibleItems.length > 0) {
                    if (activeIdx >= 0) visibleItems[activeIdx].classList.remove('active');
                    activeIdx = (activeIdx + 1) % visibleItems.length;
                    visibleItems[activeIdx].classList.add('active');
                    visibleItems[activeIdx].scrollIntoView({ block: 'nearest' });
                }
                return;
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (visibleItems.length > 0) {
                    if (activeIdx >= 0) visibleItems[activeIdx].classList.remove('active');
                    activeIdx = (activeIdx - 1 + visibleItems.length) % visibleItems.length;
                    visibleItems[activeIdx].classList.add('active');
                    visibleItems[activeIdx].scrollIntoView({ block: 'nearest' });
                }
                return;
            } else if (e.key === 'Enter' || e.key === 'Tab') {
                if (visibleItems.length > 0 && activeIdx >= 0) {
                    e.preventDefault();
                    selectRomitaModuleSlashCommand(visibleItems[activeIdx]);
                    return;
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                menu.style.display = 'none';
                return;
            }
        }

        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }

    function handleRomitaModuleInputChanged(el) {
        const val = el.value.trim();
        const menu = document.getElementById('rg-slash-menu');
        if (!menu) return;

        if (val.startsWith('/')) {
            menu.style.display = 'block';
            const filter = val.toLowerCase();
            const items = menu.querySelectorAll('.rg-slash-item');
            let hasVisible = false;
            items.forEach(item => {
                const cmd = item.getAttribute('data-cmd') || '';
                const desc = item.textContent.toLowerCase();
                if (cmd.startsWith(filter) || desc.includes(filter.replace('/', ''))) {
                    item.style.display = 'flex';
                    hasVisible = true;
                } else {
                    item.style.display = 'none';
                }
            });

            // Set first visible item as active
            items.forEach(it => it.classList.remove('active'));
            const firstVisible = Array.from(items).find(it => it.style.display !== 'none');
            if (firstVisible) firstVisible.classList.add('active');

            if (!hasVisible) {
                menu.style.display = 'none';
            }
        } else {
            menu.style.display = 'none';
        }
    }

    function selectRomitaModuleSlashCommand(btn) {
        const prompt = btn.getAttribute('data-prompt') || '';
        const input = document.getElementById('chatInput');
        const menu = document.getElementById('rg-slash-menu');
        if (menu) menu.style.display = 'none';
        if (input) {
            input.value = prompt;
            autoResize(input);
            input.focus();
        }
    }

    // Reconocimiento de Voz Nativo (Web Speech API) para Módulo Romita
    let romitaModuleSpeechRecognition = null;
    let romitaModuleIsListening = false;

    function toggleRomitaModuleVoiceRecognition() {
        const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
        const micBtn = document.getElementById('btn-romita-mic');
        const input = document.getElementById('chatInput');

        if (!SpeechRec) {
            alert('Tu navegador no soporta reconocimiento de voz nativo. Te recomendamos usar Google Chrome o Microsoft Edge.');
            return;
        }

        if (romitaModuleIsListening && romitaModuleSpeechRecognition) {
            romitaModuleSpeechRecognition.stop();
            return;
        }

        try {
            romitaModuleSpeechRecognition = new SpeechRec();
            romitaModuleSpeechRecognition.lang = 'es-PE';
            romitaModuleSpeechRecognition.continuous = true;
            romitaModuleSpeechRecognition.interimResults = true;

            romitaModuleSpeechRecognition.onstart = function() {
                romitaModuleIsListening = true;
                if (micBtn) {
                    micBtn.classList.add('is-recording');
                    micBtn.innerHTML = '<i class="ph-fill ph-microphone"></i>';
                    micBtn.title = 'Escuchando... Haz clic para detener';
                }
            };

            romitaModuleSpeechRecognition.onresult = function(event) {
                let finalTranscript = '';
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript;
                    }
                }
                if (finalTranscript && input) {
                    const cur = input.value.trim();
                    input.value = cur ? (cur + ' ' + finalTranscript.trim()) : finalTranscript.trim();
                    autoResize(input);
                }
            };

            romitaModuleSpeechRecognition.onerror = function(event) {
                console.warn('Speech recognition error:', event.error);
                stopRomitaModuleVoiceRecognition();
            };

            romitaModuleSpeechRecognition.onend = function() {
                stopRomitaModuleVoiceRecognition();
            };

            romitaModuleSpeechRecognition.start();
        } catch (e) {
            console.warn('Error starting speech:', e);
            stopRomitaModuleVoiceRecognition();
        }
    }

    function stopRomitaModuleVoiceRecognition() {
        romitaModuleIsListening = false;
        const micBtn = document.getElementById('btn-romita-mic');
        if (micBtn) {
            micBtn.classList.remove('is-recording');
            micBtn.innerHTML = '<i class="ph ph-microphone"></i>';
            micBtn.title = 'Dictar por voz (Español)';
        }
    }

    // FASE 3: Gestión de Adjuntos Multimodales (Imágenes, PDF, Documentos)
    let romitaModuleCurrentAttachment = null;
    let romitaModuleAttachmentType = null;

    function handleRomitaModuleFileSelect(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        setRomitaModuleAttachment(file);
    }

    function setRomitaModuleAttachment(file) {
        if (file.size > 20 * 1024 * 1024) {
            alert('El archivo excede el tamaño máximo permitido (20MB).');
            return;
        }

        romitaModuleCurrentAttachment = file;
        const ext = file.name.split('.').pop().toLowerCase();
        const isImg = ['png', 'jpg', 'jpeg', 'webp', 'gif'].includes(ext);
        const isPdf = ext === 'pdf';
        romitaModuleAttachmentType = isImg ? 'image' : (isPdf ? 'pdf' : 'text');

        const box = document.getElementById('romitaModuleAttachmentPreview');
        const media = document.getElementById('romitaModuleAttachmentMedia');
        const nameEl = document.getElementById('romitaModuleAttachmentName');
        const sizeEl = document.getElementById('romitaModuleAttachmentSize');
        const chipsEl = document.getElementById('romitaModuleVisionChips');

        if (nameEl) nameEl.innerText = file.name;
        if (sizeEl) sizeEl.innerText = (file.size / 1024 > 1024) ? (file.size / (1024*1024)).toFixed(1) + ' MB' : Math.round(file.size / 1024) + ' KB';

        if (media) {
            if (isImg) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    media.innerHTML = `<img src="${e.target.result}" class="romita-att-thumb" alt="Preview" />`;
                };
                reader.readAsDataURL(file);
            } else {
                const icon = isPdf ? 'ph-file-pdf' : 'ph-file-text';
                const color = isPdf ? '#ef4444' : '#2563eb';
                media.innerHTML = `<div class="romita-att-icon-badge" style="color:${color};"><i class="ph ${icon}"></i></div>`;
            }
        }

        if (chipsEl) {
            chipsEl.style.display = isImg ? 'flex' : 'none';
        }

        if (box) box.style.display = 'block';
    }

    function removeRomitaModuleAttachment() {
        romitaModuleCurrentAttachment = null;
        romitaModuleAttachmentType = null;
        const box = document.getElementById('romitaModuleAttachmentPreview');
        if (box) box.style.display = 'none';
        const fileInput = document.getElementById('romitaModuleFileInput');
        if (fileInput) fileInput.value = '';
    }

    function applyRomitaModuleVisionPrompt(promptText) {
        const input = document.getElementById('chatInput');
        if (input) {
            input.value = promptText;
            autoResize(input);
            input.focus();
        }
    }

    // Roma Actions (Cards interactivas)
    function escapeRomitaHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safeParseJson(raw) {
        if (!raw) return null;
        let clean = String(raw).trim();
        clean = clean.replace(/^```[a-z0-9_-]*\s*/i, '').replace(/```\s*$/, '').trim();
        clean = clean.replace(/,\s*([}\]])/g, '$1');
        try {
            return JSON.parse(clean);
        } catch (e) {
            try {
                return JSON.parse(clean.replace(/'/g, '"'));
            } catch (e2) {
                return null;
            }
        }
    }

    function renderRomitaMonthBoardActionCard(jsonContent) {
        try {
            const data = safeParseJson(jsonContent);
            if (!data) return `<pre><code>${jsonContent}</code></pre>`;
            let posts = data.posts || [];
            if (!posts.length && data.tasks) posts = data.tasks;
            if (!posts.length && Array.isArray(data)) posts = data;
            if (!posts.length && (data.concept || data.title)) posts = [data];
            if (!posts.length) return '';

            const brandName = data.brand_name || (selectedBrand ? selectedBrand.name : 'Marca');
            const monthName = data.month_name || 'Tablero Mensual';
            const monthId = data.month_id || '';
            const projectId = data.project_id || (selectedBrand && selectedBrand.type === 'project' ? selectedBrand.id : '');

            const cardId = 'rac-mb-' + Math.random().toString(36).substr(2, 9);
            const encodedData = encodeURIComponent(JSON.stringify(posts));

            let postsHtml = '';
            posts.forEach((p, i) => {
                let concept = p.concept || p.title || `Publicación #${i + 1}`;
                concept = concept.replace(/^\[MONTH_BOARD:[^\]]+\]\s*/i, '');

                const postType = p.post_type || p.format || 'Reel';
                const pillar = p.content_pillar || p.pillar || 'Branding';
                const postDate = p.post_date || p.due_date || '';
                const platform = p.platform || 'Instagram';
                const copyText = p.copy_text || p.caption || p.description || '';

                const typeBadge = `<span class="rac-badge rac-badge-type" style="background:rgba(59,130,246,0.12); color:#2563eb; border:1px solid rgba(59,130,246,0.25);"><i class="ph-bold ph-video-camera"></i> ${escapeRomitaHtml(postType)}</span>`;
                const pillarBadge = `<span class="rac-badge rac-badge-pillar" style="background:rgba(168,85,247,0.12); color:#7c3aed; border:1px solid rgba(168,85,247,0.25);"><i class="ph ph-target"></i> ${escapeRomitaHtml(pillar)}</span>`;
                const dateBadge = postDate ? `<span class="rac-badge rac-badge-date"><i class="ph ph-calendar"></i> ${escapeRomitaHtml(postDate)}</span>` : '';
                const platformBadge = `<span class="rac-badge rac-badge-platform" style="background:rgba(236,72,153,0.12); color:#db2777; border:1px solid rgba(236,72,153,0.25);"><i class="ph ph-share-network"></i> ${escapeRomitaHtml(platform)}</span>`;

                postsHtml += `<div class="rac-task-item rac-mb-item" id="${cardId}-item-${i}">`
                    + `<input type="checkbox" id="${cardId}-p-${i}" class="rac-task-check" checked data-post-index="${i}" onchange="updateRomitaActionMonthPostCount('${cardId}')">`
                    + `<div class="rac-task-content">`
                    + `<div class="rac-task-header">`
                    + `<span class="rac-task-title" id="${cardId}-title-${i}">${escapeRomitaHtml(concept)}</span>`
                    + `<div class="rac-task-tags">`
                    + typeBadge
                    + pillarBadge
                    + dateBadge
                    + platformBadge
                    + `</div>`
                    + `</div>`
                    + `<p class="rac-task-desc" id="${cardId}-desc-${i}" style="${copyText ? '' : 'display:none;'}">${escapeRomitaHtml(copyText)}</p>`
                    + `</div>`
                    + `</div>`;
            });

            const targetLink = monthId ? `index.php?module=month_board&action=index&id=${monthId}` : `index.php?module=calendar&action=index`;

            return `<div class="romita-action-card rac-monthboard-card" id="${cardId}" data-raw-posts="${encodedData}" data-brand-name="${escapeRomitaHtml(brandName)}" data-month-id="${monthId}" data-project-id="${projectId}">`
                + `<div class="rac-header">`
                + `<div class="rac-header-left">`
                + `<span class="rac-icon-pill rac-icon-month-board" style="background:linear-gradient(135deg,#10b981,#0284c7); color:#fff;"><i class="ph-bold ph-calendar-check"></i></span>`
                + `<div class="rac-header-titles">`
                + `<strong class="rac-title">Acción: Crear posts en Month Board</strong>`
                + `<span class="rac-sub" id="${cardId}-sub">${escapeRomitaHtml(brandName)} • ${posts.length} posts listos para el tablero mensual</span>`
                + `</div>`
                + `</div>`
                + `<span class="rac-chip-status" style="background:rgba(16,185,129,0.15); color:#059669; border-color:rgba(16,185,129,0.3);"><i class="ph-bold ph-sparkle"></i> Month Board</span>`
                + `</div>`
                + `<div class="rac-body">`
                + `<div class="rac-tasks-list">${postsHtml}</div>`
                + `</div>`
                + `<div class="rac-footer">`
                + `<button type="button" class="btn-rac-execute btn-rac-execute-mb" style="background:linear-gradient(135deg,#059669,#0284c7);" onclick="executeRomitaCreateMonthPosts('${cardId}')">`
                + `<i class="ph-bold ph-plus-circle"></i> <span class="btn-text">Insertar ${posts.length} posts en Month Board</span>`
                + `</button>`
                + `<a href="${targetLink}" target="_blank" class="rac-link-kanban" title="Abrir tablero mensual">`
                + `Ir al Month Board <i class="ph ph-arrow-up-right"></i>`
                + `</a>`
                + `</div>`
                + `</div>`;
        } catch (e) {
            console.warn('Error parsing create_month_posts action:', e);
            return `<pre><code>${jsonContent}</code></pre>`;
        }
    }

    function renderRomitaTaskActionCard(jsonContent) {
        try {
            const data = safeParseJson(jsonContent);
            if (!data) return `<pre><code>${jsonContent}</code></pre>`;

            // Redirección inteligente: si las tareas son publicaciones para el Month Board
            if (data.posts || data.target_module === 'month_board' || (Array.isArray(data.tasks) && data.tasks.some(t => (t.title && t.title.includes('[MONTH_BOARD')) || t.post_type || t.concept))) {
                return renderRomitaMonthBoardActionCard(jsonContent);
            }

            let tasks = [];
            if (Array.isArray(data)) {
                tasks = data;
            } else if (Array.isArray(data.tasks)) {
                tasks = data.tasks;
            } else if (data.title) {
                tasks = [data];
            }
            if (!tasks.length) return '';

            const cardId = 'rac-' + Math.random().toString(36).substr(2, 9);
            const encodedData = encodeURIComponent(JSON.stringify(tasks));

            let tasksHtml = '';
            tasks.forEach((t, i) => {
                const urgentBadge = t.is_urgent ? `<span class="rac-badge rac-badge-urgent" id="${cardId}-urgent-badge-${i}"><i class="ph-bold ph-warning"></i> Urgente</span>` : `<span id="${cardId}-urgent-badge-${i}"></span>`;
                const dueBadge = t.due_date ? `<span class="rac-badge rac-badge-date" id="${cardId}-due-badge-${i}"><i class="ph ph-calendar"></i> ${escapeRomitaHtml(t.due_date)}</span>` : `<span id="${cardId}-due-badge-${i}"></span>`;
                
                tasksHtml += `<div class="rac-task-item" id="${cardId}-item-${i}">`
                    + `<input type="checkbox" id="${cardId}-t-${i}" class="rac-task-check" checked data-task-index="${i}" onchange="updateRomitaActionTaskCount('${cardId}')">`
                    + `<div class="rac-task-content">`
                    + `<div class="rac-task-header">`
                    + `<span class="rac-task-title" id="${cardId}-title-${i}">${escapeRomitaHtml(t.title)}</span>`
                    + `<div class="rac-task-tags">`
                    + urgentBadge
                    + dueBadge
                    + `<button type="button" class="rac-btn-edit" onclick="event.preventDefault(); event.stopPropagation(); openRomitaTaskEditModal('${cardId}', ${i})" title="Editar tarea"><i class="ph ph-pencil-simple"></i> <span>Editar</span></button>`
                    + `</div>`
                    + `</div>`
                    + `<p class="rac-task-desc" id="${cardId}-desc-${i}" style="${t.description ? '' : 'display:none;'}">${escapeRomitaHtml(t.description || '')}</p>`
                    + `</div>`
                    + `</div>`;
            });

            return `<div class="romita-action-card rac-tasks-card" id="${cardId}" data-raw-tasks="${encodedData}">`
                + `<div class="rac-header">`
                + `<div class="rac-header-left">`
                + `<span class="rac-icon-pill rac-icon-kanban" style="background:linear-gradient(135deg,#3b82f6,#6366f1); color:#fff;"><i class="ph-bold ph-check-square-offset"></i></span>`
                + `<div class="rac-header-titles">`
                + `<strong class="rac-title">Acción: Crear tareas en Tareas & Objetivos</strong>`
                + `<span class="rac-sub" id="${cardId}-sub">${tasks.length} tareas listas para asignar</span>`
                + `</div>`
                + `</div>`
                + `<span class="rac-chip-status"><i class="ph-bold ph-sparkle"></i> IA Copilot</span>`
                + `</div>`
                + `<div class="rac-body">`
                + `<div class="rac-tasks-list">${tasksHtml}</div>`
                + `</div>`
                + `<div class="rac-footer">`
                + `<button type="button" class="btn-rac-execute" onclick="executeRomitaCreateTasks('${cardId}')">`
                + `<i class="ph-bold ph-plus-circle"></i> <span class="btn-text">Insertar ${tasks.length} tareas en Tareas & Objetivos</span>`
                + `</button>`
                + `<button type="button" class="btn-rac-modal" onclick="openRomitaTaskEditModal('${cardId}', 0)" title="Abrir y configurar en Modal">`
                + `<i class="ph ph-sliders-horizontal"></i> <span>Abrir en Modal</span>`
                + `</button>`
                + `<a href="index.php?module=task_manager&action=index" target="_blank" class="rac-link-kanban" title="Abrir Tareas & Objetivos">`
                + `Ir a Tareas & Objetivos <i class="ph ph-arrow-up-right"></i>`
                + `</a>`
                + `</div>`
                + `</div>`;
        } catch (e) {
            console.warn('Error parsing create_tasks action:', e);
            return `<pre><code>${jsonContent}</code></pre>`;
        }
    }

    function renderRomitaMeetingActionCard(jsonContent) {
        try {
            const data = JSON.parse(jsonContent.trim());
            const cardId = 'rac-m-' + Math.random().toString(36).substr(2, 9);
            const encodedData = encodeURIComponent(JSON.stringify(data));

            return `<div class="romita-action-card rac-meeting-card" id="${cardId}" data-raw-meeting="${encodedData}">`
                + `<div class="rac-header">`
                + `<div class="rac-header-left">`
                + `<span class="rac-icon-pill rac-icon-meeting"><i class="ph-bold ph-calendar-plus"></i></span>`
                + `<div class="rac-header-titles">`
                + `<strong class="rac-title">Acción: Agendar Reunión</strong>`
                + `<span class="rac-sub">Programación en agenda de Roma</span>`
                + `</div>`
                + `</div>`
                + `<span class="rac-chip-status"><i class="ph-bold ph-calendar-check"></i> Agenda</span>`
                + `</div>`
                + `<div class="rac-body">`
                + `<div class="rac-meeting-details">`
                + `<div class="rac-detail-row"><span class="rac-label"><i class="ph ph-notepad"></i> Motivo:</span><span class="rac-value"><strong>${escapeRomitaHtml(data.motivo || 'Sesión de trabajo')}</strong></span></div>`
                + `<div class="rac-detail-row"><span class="rac-label"><i class="ph ph-clock"></i> Fecha y Hora:</span><span class="rac-value rac-highlight-date">${escapeRomitaHtml(data.fecha_hora || 'Pendiente por coordinar')}</span></div>`
                + (data.meet_link ? `<div class="rac-detail-row"><span class="rac-label"><i class="ph ph-video-camera"></i> Meet:</span><span class="rac-value"><a href="${escapeRomitaHtml(data.meet_link)}" target="_blank" class="rac-meet-link">${escapeRomitaHtml(data.meet_link)}</a></span></div>` : '')
                + (data.resumen ? `<div class="rac-detail-row"><span class="rac-label"><i class="ph ph-text-align-left"></i> Resumen:</span><span class="rac-value">${escapeRomitaHtml(data.resumen)}</span></div>` : '')
                + `</div>`
                + `</div>`
                + `<div class="rac-footer">`
                + `<button type="button" class="btn-rac-execute btn-rac-meeting" onclick="executeRomitaScheduleMeeting('${cardId}')"><i class="ph-bold ph-calendar-plus"></i> <span class="btn-text">Guardar en Agenda de Reuniones</span></button>`
                + `<a href="index.php?module=reuniones" target="_blank" class="rac-link-kanban" title="Abrir agenda de reuniones">Ver Agenda <i class="ph ph-arrow-up-right"></i></a>`
                + `</div>`
                + `</div>`;
        } catch (e) {
            return `<pre><code>${jsonContent}</code></pre>`;
        }
    }

    function renderRomitaWhatsappActionCard(jsonContent) {
        try {
            const data = JSON.parse(jsonContent.trim());
            const cardId = 'rac-w-' + Math.random().toString(36).substr(2, 9);
            const msg = data.message || '';
            const recipient = data.recipient_name || 'Cliente';
            const waUrl = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(msg);
            const safeEncodedMsg = encodeURIComponent(msg);

            return `<div class="romita-action-card rac-whatsapp-card" id="${cardId}">`
                + `<div class="rac-header">`
                + `<div class="rac-header-left">`
                + `<span class="rac-icon-pill rac-icon-whatsapp"><i class="ph-bold ph-whatsapp-logo"></i></span>`
                + `<div class="rac-header-titles">`
                + `<strong class="rac-title">Mensaje listo para WhatsApp</strong>`
                + `<span class="rac-sub">Para: ${escapeRomitaHtml(recipient)}</span>`
                + `</div>`
                + `</div>`
                + `<span class="rac-chip-status rac-chip-wa"><i class="ph-bold ph-paper-plane-tilt"></i> WhatsApp</span>`
                + `</div>`
                + `<div class="rac-body">`
                + `<div class="rac-wa-balloon"><div class="rac-wa-balloon-inner">${escapeRomitaHtml(msg).replace(/\n/g, '<br>')}</div></div>`
                + `</div>`
                + `<div class="rac-footer">`
                + `<a href="${waUrl}" target="_blank" class="btn-rac-execute btn-rac-wa"><i class="ph-bold ph-whatsapp-logo"></i> <span class="btn-text">Enviar por WhatsApp</span></a>`
                + `<button type="button" class="btn-rac-copy" onclick="copyActionCardText(this, '${safeEncodedMsg}')"><i class="ph ph-copy"></i> Copiar texto</button>`
                + `</div>`
                + `</div>`;
        } catch (e) {
            return `<pre><code>${jsonContent}</code></pre>`;
        }
    }

    // FASE 2: Renderizar Tarjeta Interactiva de Post para Redes Sociales
    function renderRomitaSocialCard(jsonContent) {
        try {
            const data = JSON.parse(jsonContent.trim());
            const platform = (data.platform || 'instagram').toLowerCase();
            const account = data.account || '@romaagencia';
            const hook = data.hook || '';
            const caption = data.caption || data.body || '';
            const hashtags = Array.isArray(data.hashtags) ? data.hashtags : [];
            const cta = data.cta || '';
            const cardId = 'rsc-' + Math.random().toString(36).substr(2, 9);
            
            let platformName = 'Instagram';
            let platformIcon = 'ph ph-instagram-logo';
            if (platform === 'linkedin') { platformName = 'LinkedIn'; platformIcon = 'ph ph-linkedin-logo'; }
            else if (platform === 'tiktok') { platformName = 'TikTok'; platformIcon = 'ph ph-tiktok-logo'; }
            else if (platform === 'facebook') { platformName = 'Facebook'; platformIcon = 'ph ph-facebook-logo'; }

            const fullCopy = (hook ? hook + '\n\n' : '') + caption + (cta ? '\n\n' + cta : '') + (hashtags.length ? '\n\n' + hashtags.join(' ') : '');
            const tagsText = hashtags.join(' ');
            const encodedFullCopy = encodeURIComponent(fullCopy);

            return `
            <div class="rac-social-card" id="${cardId}">
                <div class="rac-social-header">
                    <span class="rac-platform-badge ${platform}">
                        <i class="${platformIcon}"></i> ${platformName} Post
                    </span>
                    <span class="rac-social-account">${escapeRomitaHtml(account)}</span>
                </div>
                <div class="rac-social-body">
                    ${hook ? `<div class="rac-social-hook-box"><i class="ph-bold ph-lightning"></i> ${escapeRomitaHtml(hook)}</div>` : ''}
                    <div class="rac-social-caption-box">${escapeRomitaHtml(caption)}</div>
                    ${cta ? `<div style="font-weight:600; font-size:0.8rem; color:#2563eb; margin:6px 0;"><i class="ph-bold ph-arrow-right"></i> ${escapeRomitaHtml(cta)}</div>` : ''}
                    ${hashtags.length ? `
                    <div class="rac-social-tags-box">
                        ${hashtags.map(t => `<span class="rac-tag-chip" onclick="copySnippetToClipboard('${encodeURIComponent(t)}', this)" title="Copiar tag">${escapeRomitaHtml(t)}</span>`).join('')}
                    </div>` : ''}
                </div>
                <div class="rac-social-footer">
                    ${hashtags.length ? `
                    <button type="button" class="btn-rac-modal" onclick="copySnippetToClipboard('${encodeURIComponent(tagsText)}', this)">
                        <i class="ph ph-hash"></i> Copiar Hashtags
                    </button>` : ''}
                    <button type="button" class="btn-rac-modal" onclick="copySnippetToClipboard('${encodedFullCopy}', this)">
                        <i class="ph ph-copy"></i> Copiar Post Completo
                    </button>
                    <button type="button" class="btn-rac-modal" style="background:#2563eb; color:#ffffff; border-color:#2563eb;" onclick="openCanvasWithContent('${escapeRomitaHtml(platformName)} Post', '${encodedFullCopy}')">
                        <i class="ph ph-article"></i> Abrir en Canvas
                    </button>
                </div>
            </div>`;
        } catch(e) {
            console.warn('Error parsing social card:', e);
            return `<pre><code>${jsonContent}</code></pre>`;
        }
    }

    // FASE 2: Renderizar Tabs de Variaciones de Copy (A/B/C)
    function renderRomitaVariationsCard(jsonContent) {
        try {
            const data = JSON.parse(jsonContent.trim());
            const topic = data.topic || 'Variaciones de Copy';
            const variations = Array.isArray(data.variations) ? data.variations : [];
            if (!variations.length) return `<pre><code>${jsonContent}</code></pre>`;

            const cardId = 'rvc-' + Math.random().toString(36).substr(2, 9);
            let tabsHtml = '';
            let panesHtml = '';

            variations.forEach((v, idx) => {
                const isActive = idx === 0 ? 'active' : '';
                const tabLabel = v.label || `Opción ${idx + 1}`;
                const badge = v.badge || '';
                const text = v.text || v.content || '';
                const encodedText = encodeURIComponent(text);

                tabsHtml += `<button type="button" class="rac-tab-btn ${isActive}" onclick="switchRomitaVariation('${cardId}', ${idx})">${escapeRomitaHtml(tabLabel)}</button>`;
                
                panesHtml += `
                <div class="rac-var-pane ${isActive}" id="${cardId}-pane-${idx}">
                    ${badge ? `<span class="rac-var-badge">${escapeRomitaHtml(badge)}</span>` : ''}
                    <div class="rac-var-content">${escapeRomitaHtml(text)}</div>
                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                        <button type="button" class="btn-rac-modal" onclick="copySnippetToClipboard('${encodedText}', this)">
                            <i class="ph ph-copy"></i> Copiar esta opción
                        </button>
                        <button type="button" class="btn-rac-modal" onclick="openCanvasWithContent('${escapeRomitaHtml(tabLabel)}', '${encodedText}')">
                            <i class="ph ph-article"></i> Ver en Canvas
                        </button>
                    </div>
                </div>`;
            });

            return `
            <div class="rac-variations-card" id="${cardId}">
                <div class="rac-var-header">
                    <div class="rac-var-title"><i class="ph ph-git-fork"></i> ${escapeRomitaHtml(topic)}</div>
                    <span style="font-size:0.72rem; color:var(--romita-text-muted);">${variations.length} opciones</span>
                </div>
                <div class="rac-var-tabs">${tabsHtml}</div>
                <div class="rac-var-panes">${panesHtml}</div>
            </div>`;
        } catch(e) {
            console.warn('Error parsing variations card:', e);
            return `<pre><code>${jsonContent}</code></pre>`;
        }
    }

    function switchRomitaVariation(cardId, targetIdx) {
        const card = document.getElementById(cardId);
        if (!card) return;
        const tabs = card.querySelectorAll('.rac-tab-btn');
        const panes = card.querySelectorAll('.rac-var-pane');
        tabs.forEach((t, i) => t.classList.toggle('active', i === targetIdx));
        panes.forEach((p, i) => p.classList.toggle('active', i === targetIdx));
    }

    // FASE 2: Renderizar Trigger de Romita Canvas
    function renderRomitaCanvasTrigger(title, markdownContent) {
        const safeTitle = title || 'Documento de Trabajo';
        const encodedContent = encodeURIComponent(markdownContent);
        return `
        <div class="rac-canvas-trigger-card" onclick="openCanvasWithContent('${escapeRomitaHtml(safeTitle)}', '${encodedContent}')">
            <div class="rac-canvas-trigger-left">
                <div class="rac-canvas-trigger-icon"><i class="ph-bold ph-article"></i></div>
                <div>
                    <h4 class="rac-canvas-trigger-title">${escapeRomitaHtml(safeTitle)}</h4>
                    <span class="rac-canvas-trigger-desc">Documento estructurado listo para editar, copiar o exportar</span>
                </div>
            </div>
            <button type="button" class="rac-canvas-trigger-btn">
                <i class="ph ph-arrow-square-out"></i> Abrir en Canvas
            </button>
        </div>`;
    }

    // FASE 3: Renderizar Tarjeta de Prompt Visual (Midjourney, Imagen 3, FLUX)
    function renderRomitaImagePromptCard(jsonContent) {
        try {
            const cleanJson = jsonContent.trim().replace(/^```json/i, '').replace(/```$/i, '').trim();
            const data = JSON.parse(cleanJson);
            const cardId = 'ripc-' + Math.random().toString(36).substr(2, 9);
            const concept = data.concept || 'Concepto Visual IA';
            const promptEn = data.prompt_en || data.prompt || '';
            const negPrompt = data.negative_prompt || '';
            const engine = data.engine || 'Midjourney v6';
            const ratio = data.ratio || '16:9';
            const style = data.style || 'Fotografía Profesional';
            const lighting = data.lighting || '';
            const encodedPrompt = encodeURIComponent(promptEn);

            return `
            <div class="rac-image-prompt-card" id="${cardId}">
                <div class="rac-imgp-header">
                    <div class="rac-imgp-title-wrap">
                        <div class="rac-imgp-sparkle"><i class="ph-bold ph-camera"></i></div>
                        <h4 class="rac-imgp-title">${escapeRomitaHtml(concept)}</h4>
                    </div>
                    <span class="rac-imgp-badge engine"><i class="ph ph-cpu"></i> ${escapeRomitaHtml(engine)}</span>
                </div>
                <div class="rac-imgp-badges">
                    <span class="rac-imgp-badge"><i class="ph ph-aspect-ratio"></i> ${escapeRomitaHtml(ratio)}</span>
                    <span class="rac-imgp-badge"><i class="ph ph-paint-brush"></i> ${escapeRomitaHtml(style)}</span>
                    ${lighting ? `<span class="rac-imgp-badge"><i class="ph ph-sun"></i> ${escapeRomitaHtml(lighting)}</span>` : ''}
                </div>
                <div class="rac-imgp-body">
                    <div class="rac-imgp-prompt-box">${escapeRomitaHtml(promptEn)}</div>
                    ${negPrompt ? `<div class="rac-imgp-negative-box"><strong>Negative:</strong> ${escapeRomitaHtml(negPrompt)}</div>` : ''}
                </div>
                <div class="rac-imgp-footer">
                    <button type="button" class="btn-rac-prompt-copy" onclick="copySnippetToClipboard('${encodedPrompt}', this)">
                        <i class="ph ph-copy"></i> Copiar Prompt en Inglés
                    </button>
                    <button type="button" class="btn-rac-modal" onclick="openCanvasWithContent('${escapeRomitaHtml(concept)}', '${encodedPrompt}')">
                        <i class="ph ph-article"></i> Abrir en Canvas
                    </button>
                </div>
            </div>`;
        } catch(e) {
            console.warn('Error rendering image prompt card:', e);
            return `<pre><code>${jsonContent}</code></pre>`;
        }
    }

    // FASE 2: Controladores de Romita Canvas
    let currentCanvasTitle = 'Documento';
    let currentCanvasRaw = '';
    let isCanvasEditMode = false;

    function openCanvasWithContent(title, encodedOrRaw) {
        currentCanvasTitle = title || 'Documento Romita';
        currentCanvasRaw = encodedOrRaw.includes('%') ? decodeURIComponent(encodedOrRaw) : encodedOrRaw;
        
        document.querySelectorAll('.rcp-title').forEach(el => el.innerText = currentCanvasTitle);
        
        const viewEl = document.getElementById('rcpBodyView');
        if (viewEl) {
            if (typeof marked !== 'undefined' && typeof marked.parse === 'function') {
                viewEl.innerHTML = marked.parse(currentCanvasRaw);
            } else {
                viewEl.innerHTML = renderRomitaMarkdown(currentCanvasRaw);
            }
        }
        
        const editEl = document.getElementById('rcpBodyEdit');
        if (editEl) editEl.value = currentCanvasRaw;

        isCanvasEditMode = false;
        updateCanvasViewMode();
        updateCanvasCounts();

        const romitaContainer = document.querySelector('.romita-container');
        if (romitaContainer) romitaContainer.classList.add('canvas-open');

        const modalDrawer = document.getElementById('romitaModalCanvasDrawer');
        if (modalDrawer) {
            modalDrawer.style.display = 'flex';
            setTimeout(() => modalDrawer.classList.add('open'), 10);
        }
    }

    function closeRomitaCanvas() {
        const romitaContainer = document.querySelector('.romita-container');
        if (romitaContainer) romitaContainer.classList.remove('canvas-open');

        const modalDrawer = document.getElementById('romitaModalCanvasDrawer');
        if (modalDrawer) {
            modalDrawer.classList.remove('open');
            setTimeout(() => {
                if (!modalDrawer.classList.contains('open')) {
                    modalDrawer.style.display = 'none';
                }
            }, 300);
        }
    }

    function toggleCanvasEditMode() {
        isCanvasEditMode = !isCanvasEditMode;
        if (!isCanvasEditMode) {
            const editEl = document.getElementById('rcpBodyEdit');
            if (editEl) {
                currentCanvasRaw = editEl.value;
                const viewEl = document.getElementById('rcpBodyView');
                if (viewEl) {
                    if (typeof marked !== 'undefined' && typeof marked.parse === 'function') {
                        viewEl.innerHTML = marked.parse(currentCanvasRaw);
                    } else {
                        viewEl.innerHTML = renderRomitaMarkdown(currentCanvasRaw);
                    }
                }
            }
        }
        updateCanvasViewMode();
        updateCanvasCounts();
    }

    function updateCanvasViewMode() {
        const viewEl = document.getElementById('rcpBodyView');
        const editEl = document.getElementById('rcpBodyEdit');
        const btnEdit = document.getElementById('rcpBtnEdit');

        if (viewEl && editEl) {
            viewEl.style.display = isCanvasEditMode ? 'none' : 'block';
            editEl.style.display = isCanvasEditMode ? 'block' : 'none';
        }

        if (btnEdit) {
            btnEdit.classList.toggle('active-edit', isCanvasEditMode);
            btnEdit.innerHTML = isCanvasEditMode ? 
                '<i class="ph ph-check"></i> <span class="hide-mobile">Ver Vista Previa</span>' : 
                '<i class="ph ph-pencil-simple"></i> <span class="hide-mobile">Editar</span>';
        }
    }

    function updateCanvasCounts() {
        const text = isCanvasEditMode ? 
            (document.getElementById('rcpBodyEdit')?.value || '') : 
            currentCanvasRaw;
        const words = text.trim() ? text.trim().split(/\s+/).length : 0;
        const chars = text.length;
        const countEl = document.getElementById('rcpWordCount');
        if (countEl) countEl.innerText = `${words} palabras • ${chars} caracteres`;
    }

    function onCanvasEditChange() {
        updateCanvasCounts();
    }

    function copyCanvasContent() {
        const text = isCanvasEditMode ? 
            (document.getElementById('rcpBodyEdit')?.value || '') : 
            currentCanvasRaw;
        navigator.clipboard.writeText(text).then(() => {
            if (window.showToast) window.showToast('¡Contenido del Canvas copiado al portapapeles!', 'success');
            else alert('¡Contenido del Canvas copiado al portapapeles!');
        });
    }

    function downloadCanvasFile(ext = 'md') {
        const text = isCanvasEditMode ? 
            (document.getElementById('rcpBodyEdit')?.value || '') : 
            currentCanvasRaw;
        const blob = new Blob([text], { type: 'text/markdown;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const cleanTitle = currentCanvasTitle.toLowerCase().replace(/[^a-z0-9]/g, '_').substring(0, 30);
        a.download = `${cleanTitle || 'documento'}.${ext}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function copySnippetToClipboard(encodedText, btn) {
        const text = decodeURIComponent(encodedText);
        navigator.clipboard.writeText(text).then(() => {
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="ph-bold ph-check" style="color:#10b981;"></i> ¡Copiado!';
            setTimeout(() => btn.innerHTML = origHtml, 2000);
        });
    }

    function openMessageInCanvas(btn) {
        const bubble = btn.closest('.romita-message')?.querySelector('.message-bubble') ||
                       btn.closest('.rg-msg')?.querySelector('.rg-msg-bubble');
        if (!bubble) return;
        const text = bubble.innerText.trim();
        openCanvasWithContent('Respuesta de Romita', text);
    }

    // FASE 2: Calificación / Feedback de Respuestas (👍 / 👎 Feedback Loop)
    async function sendRomitaFeedback(messageId, rating, btn) {
        const group = btn.closest('.rma-feedback-group');
        if (!group) return;
        const upBtn = group.querySelector('.rma-thumb-up');
        const downBtn = group.querySelector('.rma-thumb-down');
        
        const isAlreadyActive = (rating === 1 && upBtn.classList.contains('active-up')) || 
                                (rating === -1 && downBtn.classList.contains('active-down'));
        const newRating = isAlreadyActive ? 0 : rating;
        
        upBtn.classList.toggle('active-up', newRating === 1);
        downBtn.classList.toggle('active-down', newRating === -1);
        
        if (messageId && messageId > 0) {
            try {
                const fd = new FormData();
                fd.append('action', 'feedback_message');
                fd.append('message_id', messageId);
                fd.append('rating', newRating);
                await fetch('ajax/ajax_romita.php', { method: 'POST', body: fd });
            } catch(e) {
                console.warn('Feedback error:', e);
            }
        }
    }

    // FASE 2: Micro-Audio de Notificación con Web Audio API (Cero dependencias)
    function playRomitaChime() {
        if (localStorage.getItem('romita_sound_enabled') === '0') return;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            if (ctx.state === 'suspended') ctx.resume();
            const now = ctx.currentTime;
            
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, now); // D5
            osc1.frequency.exponentialRampToValueAtTime(880, now + 0.12); // A5
            
            gain1.gain.setValueAtTime(0.001, now);
            gain1.gain.linearRampToValueAtTime(0.12, now + 0.03);
            gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.45);
            
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(880, now + 0.08); // A5
            osc2.frequency.exponentialRampToValueAtTime(1174.66, now + 0.22); // D6
            
            gain2.gain.setValueAtTime(0.001, now + 0.08);
            gain2.gain.linearRampToValueAtTime(0.1, now + 0.12);
            gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.55);
            
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            
            osc1.start(now);
            osc1.stop(now + 0.46);
            osc2.start(now + 0.08);
            osc2.stop(now + 0.56);
        } catch (e) {
            console.warn('Audio chime error:', e);
        }
    }

    function toggleRomitaSound() {
        const isMuted = localStorage.getItem('romita_sound_enabled') === '0';
        const newState = isMuted ? '1' : '0';
        localStorage.setItem('romita_sound_enabled', newState);
        updateRomitaSoundButtons();
        if (newState === '1') playRomitaChime();
    }

    function updateRomitaSoundButtons() {
        const isMuted = localStorage.getItem('romita_sound_enabled') === '0';
        
        // Elemento del menú de opciones (···)
        const soundItem = document.getElementById('romitaSoundToggle');
        if (soundItem) {
            const iconEl = document.getElementById('menuSoundIcon') || soundItem.querySelector('i');
            const subEl = document.getElementById('menuSoundSub') || soundItem.querySelector('.rod-item-desc');
            if (isMuted) {
                soundItem.classList.add('sound-muted');
                if (iconEl) iconEl.className = 'ph ph-speaker-simple-slash';
                if (subEl) subEl.textContent = 'Silenciado (Clic para activar)';
                soundItem.title = 'Sonido silenciado (Clic para activar)';
            } else {
                soundItem.classList.remove('sound-muted');
                if (iconEl) iconEl.className = 'ph ph-speaker-high';
                if (subEl) subEl.textContent = 'Sonido activado (Clic para silenciar)';
                soundItem.title = 'Sonido activado (Clic para silenciar)';
            }
        }

        // Para cualquier otro botón con clase .btn-romita-sound
        document.querySelectorAll('.btn-romita-sound').forEach(btn => {
            if (btn.id === 'romitaSoundToggle') return;
            const iconEl = btn.querySelector('i');
            if (isMuted) {
                btn.classList.add('sound-muted');
                if (iconEl) iconEl.className = 'ph ph-speaker-simple-slash';
                btn.title = 'Sonido silenciado (Clic para activar)';
            } else {
                btn.classList.remove('sound-muted');
                if (iconEl) iconEl.className = 'ph ph-speaker-high';
                btn.title = 'Sonido activado (Clic para silenciar)';
            }
        });
    }

    function updateRomitaActionTaskCount(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;
        const checks = card.querySelectorAll('.rac-task-check:checked');
        const total = card.querySelectorAll('.rac-task-check').length;
        const subEl = document.getElementById(cardId + '-sub');
        if (subEl) subEl.innerText = `${checks.length} de ${total} tareas seleccionadas`;
        const btn = card.querySelector('.btn-rac-execute .btn-text');
        if (btn) btn.innerText = `Insertar ${checks.length} tareas en el Kanban`;
    }

    function copyActionCardText(btn, encodedText) {
        const text = decodeURIComponent(encodedText);
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="ph-bold ph-check"></i> ¡Copiado!';
            btn.style.color = '#16a34a';
            setTimeout(() => {
                btn.innerHTML = orig;
                btn.style.color = '';
            }, 1800);
        });
    }

    function openRomitaTaskEditModal(cardId, taskIndex) {
        const card = document.getElementById(cardId);
        if (!card) return;

        const rawData = card.getAttribute('data-raw-tasks');
        if (!rawData) return;

        const tasks = JSON.parse(decodeURIComponent(rawData));
        const t = tasks[taskIndex] || tasks[0];
        if (!t) return;

        const overlay = document.getElementById('romita-task-modal-overlay');
        if (!overlay) return;

        document.getElementById('rtm-card-id').value = cardId;
        document.getElementById('rtm-task-index').value = taskIndex;
        document.getElementById('rtm-title').value = t.title || '';
        document.getElementById('rtm-desc').value = t.description || '';
        document.getElementById('rtm-due-date').value = t.due_date || '';
        document.getElementById('rtm-urgent').checked = !!t.is_urgent;

        const heading = document.getElementById('romita-task-modal-heading');
        if (heading) heading.innerText = tasks.length > 1 ? `Configurar Tarea #${taskIndex + 1} de ${tasks.length}` : 'Configurar Tarea en Kanban';

        overlay.style.display = 'flex';
        setTimeout(() => {
            const titleInput = document.getElementById('rtm-title');
            if (titleInput) titleInput.focus();
        }, 120);
    }

    function closeRomitaTaskEditModal() {
        const overlay = document.getElementById('romita-task-modal-overlay');
        if (overlay) overlay.style.display = 'none';
    }

    function handleRomitaTaskModalOverlayClick(e) {
        if (e.target && e.target.id === 'romita-task-modal-overlay') {
            closeRomitaTaskEditModal();
        }
    }

    function saveRomitaTaskChangesToCard() {
        const cardId = document.getElementById('rtm-card-id').value;
        const taskIndex = parseInt(document.getElementById('rtm-task-index').value) || 0;
        const card = document.getElementById(cardId);
        if (!card) return closeRomitaTaskEditModal();

        const title = document.getElementById('rtm-title').value.trim();
        if (!title) {
            alert('Por favor escribe un título para la tarea.');
            return;
        }
        const desc = document.getElementById('rtm-desc').value.trim();
        const dueDate = document.getElementById('rtm-due-date').value;
        const isUrgent = document.getElementById('rtm-urgent').checked ? 1 : 0;

        const rawData = card.getAttribute('data-raw-tasks');
        if (!rawData) return closeRomitaTaskEditModal();

        const tasks = JSON.parse(decodeURIComponent(rawData));
        if (tasks[taskIndex]) {
            tasks[taskIndex].title = title;
            tasks[taskIndex].description = desc;
            tasks[taskIndex].due_date = dueDate;
            tasks[taskIndex].is_urgent = isUrgent;
        }

        card.setAttribute('data-raw-tasks', encodeURIComponent(JSON.stringify(tasks)));

        // Actualizar vista visual en la tarjeta del chat
        const titleEl = document.getElementById(`${cardId}-title-${taskIndex}`);
        if (titleEl) titleEl.innerText = title;

        const descEl = document.getElementById(`${cardId}-desc-${taskIndex}`);
        if (descEl) {
            descEl.innerText = desc;
            descEl.style.display = desc ? 'block' : 'none';
        }

        const urgentBadgeEl = document.getElementById(`${cardId}-urgent-badge-${taskIndex}`);
        if (urgentBadgeEl) {
            urgentBadgeEl.innerHTML = isUrgent ? '<span class="rac-badge rac-badge-urgent"><i class="ph-bold ph-warning"></i> Urgente</span>' : '';
        }

        const dueBadgeEl = document.getElementById(`${cardId}-due-badge-${taskIndex}`);
        if (dueBadgeEl) {
            dueBadgeEl.innerHTML = dueDate ? `<span class="rac-badge rac-badge-date"><i class="ph ph-calendar"></i> ${escapeRomitaHtml(dueDate)}</span>` : '';
        }

        closeRomitaTaskEditModal();
    }

    async function createRomitaTaskDirectlyFromModal() {
        const cardId = document.getElementById('rtm-card-id').value;
        const taskIndex = parseInt(document.getElementById('rtm-task-index').value) || 0;
        const title = document.getElementById('rtm-title').value.trim();
        if (!title) {
            alert('Por favor escribe un título para la tarea.');
            return;
        }
        const desc = document.getElementById('rtm-desc').value.trim();
        const dueDate = document.getElementById('rtm-due-date').value;
        const isUrgent = document.getElementById('rtm-urgent').checked ? 1 : 0;

        const btn = document.getElementById('btn-rtm-create-now');
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
        }

        const taskToSave = [{
            title: title,
            description: desc,
            due_date: dueDate,
            is_urgent: isUrgent
        }];

        try {
            const formData = new FormData();
            formData.append('action', 'tool_create_tasks');
            formData.append('tasks', JSON.stringify(taskToSave));

            const res = await fetch('ajax/ajax_romita.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                closeRomitaTaskEditModal();
                const card = document.getElementById(cardId);
                if (card) {
                    const footer = card.querySelector('.rac-footer');
                    if (footer) {
                        footer.innerHTML = `
                            <div class="rac-success-banner">
                                <i class="ph-fill ph-check-circle"></i>
                                <span>¡Tarea "${escapeRomitaHtml(title)}" creada en Tareas & Objetivos!</span>
                                <a href="index.php?module=task_manager&action=index" target="_blank" class="rac-btn-view">
                                    Abrir Tareas & Objetivos <i class="ph ph-arrow-up-right"></i>
                                </a>
                            </div>
                        `;
                    }
                }
            } else {
                alert(data.error || 'Error al crear tarea');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            }
        } catch (err) {
            alert('Error de conexión');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        }
    }

    function updateRomitaActionMonthPostCount(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;
        const total = card.querySelectorAll('.rac-task-check').length;
        const checked = card.querySelectorAll('.rac-task-check:checked').length;
        const sub = document.getElementById(cardId + '-sub');
        const btn = card.querySelector('.btn-rac-execute');
        const brandName = card.getAttribute('data-brand-name') || 'Marca';
        if (sub) sub.innerText = `${brandName} • ${checked} de ${total} posts seleccionados`;
        if (btn) {
            btn.querySelector('.btn-text').innerText = checked > 0 ? `Insertar ${checked} posts en Month Board` : 'Selecciona al menos 1 post';
            btn.disabled = checked === 0;
        }
    }

    async function executeRomitaCreateMonthPosts(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;

        const rawData = card.getAttribute('data-raw-posts');
        if (!rawData) return;

        const allPosts = JSON.parse(decodeURIComponent(rawData));
        const checkboxes = card.querySelectorAll('.rac-task-check:checked');
        const selectedIndices = Array.from(checkboxes).map(c => parseInt(c.getAttribute('data-post-index')));
        const selectedPosts = allPosts.filter((_, i) => selectedIndices.includes(i));

        if (!selectedPosts.length) {
            alert('Por favor selecciona al menos una publicación para inyectar.');
            return;
        }

        const brandName = card.getAttribute('data-brand-name') || '';
        const monthId = card.getAttribute('data-month-id') || '';
        const projectId = card.getAttribute('data-project-id') || (selectedBrand && selectedBrand.type === 'project' ? selectedBrand.id : '');

        const btn = card.querySelector('.btn-rac-execute');
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Inyectando en Month Board...';
        }

        try {
            const formData = new FormData();
            formData.append('action', 'tool_create_month_posts');
            formData.append('brand_name', brandName);
            formData.append('month_id', monthId);
            formData.append('project_id', projectId);
            formData.append('posts', JSON.stringify(selectedPosts));

            const res = await fetch('ajax/ajax_romita.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                card.querySelectorAll('.rac-task-check').forEach(c => c.disabled = true);
                const footer = card.querySelector('.rac-footer');
                if (footer) {
                    footer.innerHTML = `
                        <div class="rac-success-banner" style="background: rgba(16,185,129,0.12); border-color: rgba(16,185,129,0.3); color:#059669;">
                            <i class="ph-fill ph-check-circle" style="color:#10b981; font-size:1.4rem;"></i>
                            <span>¡${data.count} ${data.count === 1 ? 'post inyectado' : 'posts inyectados'} exitosamente en el Month Board!</span>
                            <a href="${data.redirect_url || 'index.php?module=calendar&action=index'}" target="_blank" class="rac-btn-view" style="background:#059669;">
                                Abrir Month Board <i class="ph ph-arrow-up-right"></i>
                            </a>
                        </div>
                    `;
                }
            } else {
                alert(data.error || 'Error al inyectar posts en Month Board');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            }
        } catch (err) {
            alert('Error de conexión con el servidor');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        }
    }

    async function executeRomitaCreateTasks(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;

        const rawData = card.getAttribute('data-raw-tasks');
        if (!rawData) return;

        const allTasks = JSON.parse(decodeURIComponent(rawData));
        const checkboxes = card.querySelectorAll('.rac-task-check:checked');
        const selectedIndices = Array.from(checkboxes).map(c => parseInt(c.getAttribute('data-task-index')));
        const selectedTasks = allTasks.filter((_, i) => selectedIndices.includes(i));

        if (!selectedTasks.length) {
            alert('Por favor selecciona al menos una tarea para crear.');
            return;
        }

        const btn = card.querySelector('.btn-rac-execute');
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Creando en Tareas & Objetivos...';
        }

        try {
            const formData = new FormData();
            formData.append('action', 'tool_create_tasks');
            formData.append('tasks', JSON.stringify(selectedTasks));
            if (selectedBrand && selectedBrand.type === 'project') {
                formData.append('project_id', selectedBrand.id);
            }

            const res = await fetch('ajax/ajax_romita.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                card.querySelectorAll('.rac-task-check').forEach(c => c.disabled = true);
                const footer = card.querySelector('.rac-footer');
                if (footer) {
                    footer.innerHTML = `
                        <div class="rac-success-banner">
                            <i class="ph-fill ph-check-circle"></i>
                            <span>¡${data.count} ${data.count === 1 ? 'tarea creada' : 'tareas creadas'} exitosamente!</span>
                            <a href="${data.redirect_url || 'index.php?module=task_manager&action=index'}" target="_blank" class="rac-btn-view">
                                Abrir Tareas & Objetivos <i class="ph ph-arrow-up-right"></i>
                            </a>
                        </div>
                    `;
                }
            } else {
                alert(data.error || 'Error al crear tareas');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            }
        } catch (err) {
            alert('Error de conexión');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        }
    }

    async function executeRomitaScheduleMeeting(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;

        const rawData = card.getAttribute('data-raw-meeting');
        if (!rawData) return;
        const meetingData = JSON.parse(decodeURIComponent(rawData));

        const btn = card.querySelector('.btn-rac-execute');
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando reunión...';
        }

        try {
            const formData = new FormData();
            formData.append('action', 'tool_schedule_meeting');
            formData.append('motivo', meetingData.motivo || '');
            formData.append('fecha_hora', meetingData.fecha_hora || '');
            formData.append('meet_link', meetingData.meet_link || '');
            formData.append('resumen', meetingData.resumen || '');
            if (meetingData.brand_id) formData.append('brand_id', meetingData.brand_id);

            const res = await fetch('ajax/ajax_romita.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                const footer = card.querySelector('.rac-footer');
                if (footer) {
                    footer.innerHTML = `
                        <div class="rac-success-banner">
                            <i class="ph-fill ph-check-circle"></i>
                            <span>¡Reunión agendada exitosamente!</span>
                            <a href="index.php?module=reuniones" target="_blank" class="rac-btn-view">
                                Ver en Agenda <i class="ph ph-arrow-up-right"></i>
                            </a>
                        </div>
                    `;
                }
            } else {
                alert(data.error || 'Error al agendar reunión');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            }
        } catch (err) {
            alert('Error de conexión');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        }
    }

    // Preprocesador para reparar tablas Markdown que omitan fila separadora
    function preprocessMarkdownTables(text) {
        if (!text || !text.includes('|')) return text;
        const lines = text.split('\n');
        const result = [];
        
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            result.push(line);
            
            const trimmed = line.trim();
            const isPipeRow = trimmed.startsWith('|') && trimmed.endsWith('|');
            
            if (isPipeRow && i + 1 < lines.length) {
                const nextTrimmed = lines[i + 1].trim();
                const nextIsPipeRow = nextTrimmed.startsWith('|') && nextTrimmed.endsWith('|');
                const nextIsSeparator = /^\|(\s*:?-+:?\s*\|)+$/.test(nextTrimmed);
                
                const prevTrimmed = i > 0 ? lines[i - 1].trim() : '';
                const prevIsPipeRow = prevTrimmed.startsWith('|') && prevTrimmed.endsWith('|');
                
                // Si es la primera fila de una tabla y no hay fila separadora debajo
                if (!prevIsPipeRow && nextIsPipeRow && !nextIsSeparator) {
                    const colCount = trimmed.split('|').length - 2;
                    if (colCount > 1) {
                        result.push('|' + ' :--- |'.repeat(colCount));
                    }
                }
            }
        }
        return result.join('\n');
    }

    function selectSkill(el) {
        if (el.classList.contains('active')) {
            el.classList.remove('active');
            activeSkill = null;
        } else {
            const rawPrompt = el.dataset.prompt;
            
            const regex = /\[([^\]]+)\]/g;
            let match;
            let variables = [];
            while ((match = regex.exec(rawPrompt)) !== null) {
                variables.push(match[1]);
            }
            
            let finalPrompt = rawPrompt;
            
            if (variables.length > 0) {
                for (let v of variables) {
                    let val = prompt(`Por favor ingresa un valor para: ${v}`);
                    if (val === null) {
                        return;
                    }
                    finalPrompt = finalPrompt.replace(`[${v}]`, val);
                }
            }
            
            document.querySelectorAll('.skill-pill').forEach(p => p.classList.remove('active'));
            el.classList.add('active');
            activeSkill = {
                id: el.dataset.id,
                prompt: finalPrompt,
                icon: el.dataset.icon || 'ph-sparkle'
            };
            
            document.getElementById('chatInput').focus();
        }
    }

    function addMessageToUI(role, content, msgId = null, feedbackVal = null, attachmentUrl = null, attachmentType = null, attachmentName = null) {
        const container = document.getElementById('chatStreamInner');
        const emptyState = document.getElementById('emptyState');
        if(emptyState) emptyState.style.display = 'none';

        const msgDiv = document.createElement('div');
        msgDiv.className = `romita-message ${role}`;
        if (msgId) msgDiv.setAttribute('data-msg-id', msgId);
        
        let aiIcon = 'ph-sparkle';
        if (role === 'assistant' && activeSkill && activeSkill.icon) {
            aiIcon = activeSkill.icon;
        }
        
        const avatar = document.createElement('div');
        avatar.className = `message-avatar ${role === 'user' ? 'user-avatar' : 'ai-avatar'}`;
        avatar.innerHTML = role === 'user' ? '<i class="ph ph-user"></i>' : (activeSkill && activeSkill.icon && activeSkill.icon !== 'ph-sparkle' ? `<i class="ph ${activeSkill.icon}"></i>` : `<img src="assets/img/romita-avatar.png" alt="Romita" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`);
        
        const wrapper = document.createElement('div');
        wrapper.className = 'message-wrapper';

        const bubble = document.createElement('div');
        bubble.className = `message-bubble ${role === 'assistant' ? 'markdown-body' : ''}`;
        
        if(role === 'assistant') {
            let preprocessed = preprocessMarkdownTables(content);

            // Lista de bloques agénticos interceptados antes de pasar por marked.parse()
            const actionPlaceholders = [];

            // 0. Publicaciones en Month Board (Tablero Mensual)
            preprocessed = preprocessed.replace(/```(?:romita[-_]?action:?|action:)?create_month_posts?\s*([\s\S]*?)```/gi, function(match, jsonContent) {
                const placeholder = '<!--ROMITA_ACTION_MB_' + actionPlaceholders.length + '-->';
                actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaMonthBoardActionCard(jsonContent) });
                return '\n\n' + placeholder + '\n\n';
            });

            // 1. Tareas (acepta cualquier variación de create_tasks, create_task, romita-action, romita_action)
            preprocessed = preprocessed.replace(/```(?:romita[-_]?action:?|action:)?create_tasks?\s*([\s\S]*?)```/gi, function(match, jsonContent) {
                const placeholder = '<!--ROMITA_ACTION_TASKS_' + actionPlaceholders.length + '-->';
                actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaTaskActionCard(jsonContent) });
                return '\n\n' + placeholder + '\n\n';
            });

            // 2. Reuniones
            preprocessed = preprocessed.replace(/```(?:romita[-_]?action:?|action:)?schedule_meetings?\s*([\s\S]*?)```/gi, function(match, jsonContent) {
                const placeholder = '<!--ROMITA_ACTION_MEET_' + actionPlaceholders.length + '-->';
                actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaMeetingActionCard(jsonContent) });
                return '\n\n' + placeholder + '\n\n';
            });

            // 3. WhatsApp
            preprocessed = preprocessed.replace(/```(?:romita[-_]?action:?|action:)?whatsapp_messages?\s*([\s\S]*?)```/gi, function(match, jsonContent) {
                const placeholder = '<!--ROMITA_ACTION_WA_' + actionPlaceholders.length + '-->';
                actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaWhatsappActionCard(jsonContent) });
                return '\n\n' + placeholder + '\n\n';
            });

            // 4. FASE 2: Interceptar Posts de Redes Sociales
            preprocessed = preprocessed.replace(/```(?:romita[-_]?action:?|action:)?social_posts?\s*([\s\S]*?)```/gi, function(match, jsonContent) {
                const placeholder = '<!--ROMITA_ACTION_SOCIAL_' + actionPlaceholders.length + '-->';
                actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaSocialCard(jsonContent) });
                return '\n\n' + placeholder + '\n\n';
            });

            // 5. FASE 2: Interceptar Tabs de Variaciones de Copy
            preprocessed = preprocessed.replace(/```(?:romita[-_]?action:?|action:)?copy_variations?\s*([\s\S]*?)```/gi, function(match, jsonContent) {
                const placeholder = '<!--ROMITA_ACTION_VARS_' + actionPlaceholders.length + '-->';
                actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaVariationsCard(jsonContent) });
                return '\n\n' + placeholder + '\n\n';
            });

            // 6. FASE 2: Interceptar Bloque Canvas
            preprocessed = preprocessed.replace(/```romita-canvas:title="([^"]+)"\s*([\s\S]*?)```/gi, function(match, title, canvasBody) {
                const placeholder = '<!--ROMITA_ACTION_CANVAS_' + actionPlaceholders.length + '-->';
                actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaCanvasTrigger(title, canvasBody) });
                return '\n\n' + placeholder + '\n\n';
            });

            // 7. FASE 3: Interceptar Prompts Visuales (Midjourney / FLUX)
            preprocessed = preprocessed.replace(/```(?:romita[-_]?action:?|action:)?image_prompts?\s*([\s\S]*?)```/gi, function(match, jsonContent) {
                const placeholder = '<!--ROMITA_ACTION_IMGP_' + actionPlaceholders.length + '-->';
                actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaImagePromptCard(jsonContent) });
                return '\n\n' + placeholder + '\n\n';
            });

            // 8. RED DE SEGURIDAD AGÉNTICA: Si Gemini devolvió un bloque ```json ... ``` estándar con tareas u objetos de acción
            preprocessed = preprocessed.replace(/```(?:json)?\s*(\{[\s\S]*?\})\s*```/gi, function(match, innerJson) {
                const parsed = safeParseJson(innerJson);
                if (!parsed) return match;
                if (parsed.posts || parsed.target_module === 'month_board' || (parsed.tasks && Array.isArray(parsed.tasks) && parsed.tasks.some(t => (t.title && t.title.includes('[MONTH_BOARD')) || t.post_type || t.concept))) {
                    const placeholder = '<!--ROMITA_ACTION_MB_' + actionPlaceholders.length + '-->';
                    actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaMonthBoardActionCard(innerJson) });
                    return '\n\n' + placeholder + '\n\n';
                }
                if (parsed.tasks || (parsed.title && (parsed.due_date || parsed.is_urgent !== undefined || parsed.prioridad || parsed.responsable))) {
                    const placeholder = '<!--ROMITA_ACTION_TASKS_' + actionPlaceholders.length + '-->';
                    actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaTaskActionCard(innerJson) });
                    return '\n\n' + placeholder + '\n\n';
                }
                if (parsed.meet_link || (parsed.motivo && parsed.fecha_hora)) {
                    const placeholder = '<!--ROMITA_ACTION_MEET_' + actionPlaceholders.length + '-->';
                    actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaMeetingActionCard(innerJson) });
                    return '\n\n' + placeholder + '\n\n';
                }
                if (parsed.recipient_name && parsed.message) {
                    const placeholder = '<!--ROMITA_ACTION_WA_' + actionPlaceholders.length + '-->';
                    actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaWhatsappActionCard(innerJson) });
                    return '\n\n' + placeholder + '\n\n';
                }
                if (parsed.platform && parsed.caption) {
                    const placeholder = '<!--ROMITA_ACTION_SOCIAL_' + actionPlaceholders.length + '-->';
                    actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaSocialCard(innerJson) });
                    return '\n\n' + placeholder + '\n\n';
                }
                if (parsed.variations && Array.isArray(parsed.variations)) {
                    const placeholder = '<!--ROMITA_ACTION_VARS_' + actionPlaceholders.length + '-->';
                    actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaVariationsCard(innerJson) });
                    return '\n\n' + placeholder + '\n\n';
                }
                if (parsed.prompt_en || (parsed.concept && parsed.engine)) {
                    const placeholder = '<!--ROMITA_ACTION_IMGP_' + actionPlaceholders.length + '-->';
                    actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaImagePromptCard(innerJson) });
                    return '\n\n' + placeholder + '\n\n';
                }
                return match;
            });

            let rendered = marked.parse(preprocessed);

            // Inyectar de vuelta las tarjetas de acción limpias sin ser alteradas ni convertidas a código por marked
            actionPlaceholders.forEach(item => {
                const pRegex = new RegExp('<p>\\s*' + item.placeholder + '\\s*<\\/p>', 'g');
                if (pRegex.test(rendered)) {
                    rendered = rendered.replace(pRegex, item.html);
                } else {
                    rendered = rendered.split(item.placeholder).join(item.html);
                }
            });

            bubble.innerHTML = rendered;
            processAssistantFormatting(bubble);

            // Barra de utilidades y Feedback Loop (Fase 2)
            const actionsBar = document.createElement('div');
            actionsBar.className = 'message-actions-bar';
            const feedbackHtml = msgId ? `
                <div class="rma-feedback-group">
                    <button type="button" class="rma-btn rma-thumb-up ${feedbackVal == 1 ? 'active-up' : ''}" onclick="sendRomitaFeedback(${msgId}, 1, this)" title="Me sirvió esta respuesta">
                        <i class="ph ph-thumbs-up"></i>
                    </button>
                    <button type="button" class="rma-btn rma-thumb-down ${feedbackVal == -1 ? 'active-down' : ''}" onclick="sendRomitaFeedback(${msgId}, -1, this)" title="No me sirvió esta respuesta">
                        <i class="ph ph-thumbs-down"></i>
                    </button>
                </div>
            ` : `<div class="rma-feedback-group"></div>`;

            actionsBar.innerHTML = `
                ${feedbackHtml}
                <div style="display:flex; align-items:center; gap:6px;">
                    <button type="button" class="rma-btn" onclick="copyMessageText(this)" title="Copiar texto">
                        <i class="ph ph-copy"></i> <span>Copiar</span>
                    </button>
                    <button type="button" class="rma-btn rma-btn-canvas" onclick="openMessageInCanvas(this)" title="Abrir en Romita Canvas">
                        <i class="ph ph-article"></i> <span>Canvas</span>
                    </button>
                </div>
            `;
            wrapper.appendChild(bubble);
            wrapper.appendChild(actionsBar);
        } else {
            if (attachmentUrl) {
                const attWrap = document.createElement('div');
                attWrap.className = 'romita-msg-attachment-container';
                if (attachmentType === 'image') {
                    attWrap.innerHTML = `
                        <div class="romita-msg-attachment-img-wrap" onclick="window.open('${escapeRomitaHtml(attachmentUrl)}', '_blank')">
                            <img src="${escapeRomitaHtml(attachmentUrl)}" alt="${escapeRomitaHtml(attachmentName || 'Imagen adjunta')}" class="romita-attached-image" />
                            <span class="romita-attachment-label"><i class="ph ph-image"></i> ${escapeRomitaHtml(attachmentName || 'Imagen')}</span>
                        </div>`;
                } else {
                    const iconClass = attachmentType === 'pdf' ? 'ph-file-pdf' : 'ph-file-text';
                    const iconColor = attachmentType === 'pdf' ? '#ef4444' : '#3b82f6';
                    attWrap.innerHTML = `
                        <a href="${escapeRomitaHtml(attachmentUrl)}" target="_blank" class="romita-msg-attachment-file">
                            <i class="ph ${iconClass}" style="color:${iconColor}; font-size:1.6rem;"></i>
                            <div class="romita-attachment-info">
                                <span class="romita-attachment-filename">${escapeRomitaHtml(attachmentName || 'Archivo adjunto')}</span>
                                <span class="romita-attachment-filedesc">Clic para abrir archivo</span>
                            </div>
                        </a>`;
                }
                bubble.appendChild(attWrap);
            }
            if (content) {
                const p = document.createElement('div');
                p.textContent = content;
                bubble.appendChild(p);
            }
            wrapper.appendChild(bubble);
        }

        msgDiv.appendChild(avatar);
        msgDiv.appendChild(wrapper);
        
        container.appendChild(msgDiv);
        
        const chatArea = document.getElementById('chatArea');
        chatArea.scrollTop = chatArea.scrollHeight;
    }

    // Copiar tabla como TSV (directamente compatible con Excel / Google Sheets)
    function copyTableToClipboard(btn) {
        const container = btn.closest('.romita-table-container');
        if (!container) return;
        const table = container.querySelector('table');
        if (!table) return;

        let tsv = [];
        const rows = table.querySelectorAll('tr');
        rows.forEach(r => {
            let cols = [];
            r.querySelectorAll('th, td').forEach(c => {
                let text = c.innerText.trim().replace(/\r?\n|\r/g, ' ').replace(/\t/g, ' ');
                cols.push(text);
            });
            tsv.push(cols.join('\t'));
        });

        const textToCopy = tsv.join('\n');
        navigator.clipboard.writeText(textToCopy).then(() => {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="ph ph-check" style="color:#10b981;"></i> <span>¡Copiado para Excel!</span>';
            setTimeout(() => {
                btn.innerHTML = originalHTML;
            }, 2200);
        }).catch(() => {
            const textarea = document.createElement('textarea');
            textarea.value = textToCopy;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert('Tabla copiada al portapapeles');
        });
    }

    // Alternar pantalla completa para tablas anchas
    function toggleTableFullscreen(btn) {
        const container = btn.closest('.romita-table-container');
        if (!container) return;
        
        container.classList.toggle('is-fullscreen');
        const icon = btn.querySelector('i');
        if (container.classList.contains('is-fullscreen')) {
            if (icon) icon.className = 'ph ph-corners-in';
            document.body.style.overflow = 'hidden';
        } else {
            if (icon) icon.className = 'ph ph-arrows-out-simple';
            document.body.style.overflow = '';
        }
    }

    // Procesar formateo avanzado para bloques de código, tablas y planes de calendario
    function processAssistantFormatting(container) {
        // 1. Detectar bloque de plan de calendario para convertirlo en tarjeta de acción interactiva
        const allCodes = container.querySelectorAll('pre code');
        allCodes.forEach(code => {
            const text = code.textContent.trim();
            if (text.includes('"project_id"') && text.includes('"posts"') && (text.includes('"month"') || text.includes('"year"'))) {
                try {
                    const startIdx = text.indexOf('{');
                    const endIdx = text.lastIndexOf('}');
                    if (startIdx !== -1 && endIdx !== -1) {
                        const jsonStr = text.substring(startIdx, endIdx + 1);
                        const plan = JSON.parse(jsonStr);
                        if (plan.project_id && plan.posts && Array.isArray(plan.posts) && plan.posts.length > 0) {
                            renderCalendarPlanActionCard(code.parentElement, plan);
                        }
                    }
                } catch(e) {
                    console.warn('No se pudo parsear calendario JSON:', e);
                }
            }
        });

        // 2. Estilizar bloques de código normales
        const codeBlocks = container.querySelectorAll('pre code');
        codeBlocks.forEach(code => {
            const pre = code.parentElement;
            if (pre.parentElement.classList.contains('code-block-wrapper') || pre.style.display === 'none') return;

            let lang = 'CÓDIGO';
            const classes = code.className.split(' ');
            for (let c of classes) {
                if (c.startsWith('language-')) {
                    lang = c.replace('language-', '').toUpperCase();
                    break;
                }
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'code-block-wrapper';
            wrapper.innerHTML = `
                <div class="code-block-header">
                    <span>${lang}</span>
                    <button class="btn-copy-code" onclick="copySnippet(this)">
                        <i class="ph ph-copy"></i> Copiar
                    </button>
                </div>
            `;
            pre.parentNode.insertBefore(wrapper, pre);
            wrapper.appendChild(pre);
        });

        // 3. Tablas ordenables, responsivas con toolbar y exportación
        const tables = container.querySelectorAll('table');
        tables.forEach(table => {
            if (table.closest('.romita-table-container')) return;

            const rowsCount = table.querySelectorAll('tbody tr').length || Math.max(0, table.querySelectorAll('tr').length - 1);
            const firstRow = table.querySelector('thead tr') || table.querySelector('tr');
            const colsCount = firstRow ? firstRow.querySelectorAll('th, td').length : 0;

            const card = document.createElement('div');
            card.className = 'romita-table-container';
            card.innerHTML = `
                <div class="table-toolbar">
                    <div class="table-toolbar-left">
                        <i class="ph ph-table"></i>
                        <span class="table-info-badge">${rowsCount} filas • ${colsCount} columnas</span>
                        <span class="table-scroll-hint"><i class="ph ph-arrows-left-right"></i> Desliza</span>
                    </div>
                    <div class="table-toolbar-actions">
                        <button type="button" class="btn-table-action" onclick="copyTableToClipboard(this)" title="Copiar como tabla (compatible con Excel / Google Sheets)">
                            <i class="ph ph-file-csv"></i> <span class="btn-text-full">Copiar para Excel</span><span class="btn-text-short">Excel</span>
                        </button>
                        <button type="button" class="btn-table-action" onclick="toggleTableFullscreen(this)" title="Pantalla completa">
                            <i class="ph ph-arrows-out-simple"></i>
                        </button>
                    </div>
                </div>
                <div class="table-responsive-wrapper"></div>
            `;

            table.parentNode.insertBefore(card, table);
            const scrollWrap = card.querySelector('.table-responsive-wrapper');
            scrollWrap.appendChild(table);

            const headers = table.querySelectorAll('th');
            headers.forEach((header, index) => {
                const titleText = (header.textContent || '').trim().toLowerCase();
                let colClass = '';
                if (titleText.includes('copy') || titleText.includes('descrip') || titleText.includes('especific') || titleText.includes('guion') || titleText.includes('texto') || titleText.includes('contenido')) {
                    colClass = 'col-wide';
                } else if (titleText.includes('gancho') || titleText.includes('hook') || titleText.includes('concepto') || titleText.includes('pilar') || titleText.includes('idea') || titleText.includes('objetivo')) {
                    colClass = 'col-medium';
                } else if ((titleText.includes('fecha') || titleText.includes('día') || titleText.includes('dia') || titleText === 'id' || titleText.includes('estado') || titleText.includes('n°')) && !titleText.includes('anuncio') && !titleText.includes('formato')) {
                    colClass = 'col-compact';
                }
                
                if (colClass) {
                    header.classList.add(colClass);
                    const allRows = table.querySelectorAll('tbody tr, tr');
                    allRows.forEach(r => {
                        if (r.children[index] && r.children[index].tagName !== 'TH') {
                            r.children[index].classList.add(colClass);
                        }
                    });
                }

                header.style.cursor = 'pointer';
                header.title = 'Clic para ordenar por esta columna';
                header.addEventListener('click', () => {
                    const tbody = table.querySelector('tbody') || table;
                    const rows = Array.from(tbody.querySelectorAll('tr:nth-child(n+2)'));
                    const isAsc = header.classList.contains('asc');
                    
                    headers.forEach(h => { h.classList.remove('asc', 'desc'); h.innerHTML = h.innerHTML.replace(' 🔼','').replace(' 🔽',''); });
                    header.classList.add(isAsc ? 'desc' : 'asc');
                    header.innerHTML += isAsc ? ' 🔽' : ' 🔼';
                    
                    rows.sort((a, b) => {
                        const aCol = a.children[index] ? a.children[index].textContent.trim() : '';
                        const bCol = b.children[index] ? b.children[index].textContent.trim() : '';
                        if(!isNaN(aCol) && !isNaN(bCol) && aCol !== '' && bCol !== '') return isAsc ? bCol - aCol : aCol - bCol;
                        return isAsc ? bCol.localeCompare(aCol) : aCol.localeCompare(bCol);
                    });
                    
                    rows.forEach(row => tbody.appendChild(row));
                });
            });
        });
    }

    // Renderizar tarjeta de acción para crear mes en Calendario
    function renderCalendarPlanActionCard(preElement, plan) {
        const monthNames = [
            "", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
            "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
        ];
        const mName = monthNames[parseInt(plan.month)] || `Mes ${plan.month}`;
        const postsCount = plan.posts.length;

        let postsHtml = '';
        plan.posts.slice(0, 5).forEach((p, idx) => {
            const dateStr = p.date ? p.date : `Día ${idx + 1}`;
            const pilar = p.content_pillar || 'General';
            const formato = p.post_type || 'Post';
            postsHtml += `
                <div class="cpac-preview-item">
                    <div class="cpac-preview-item-left">
                        <span class="cpac-preview-item-badge">${formato}</span>
                        <strong style="color:var(--romita-text);">${p.concept}</strong>
                    </div>
                    <span class="cpac-preview-item-date">${dateStr}</span>
                </div>
            `;
        });
        if (postsCount > 5) {
            postsHtml += `<div style="text-align:center; font-size:0.75rem; color:var(--romita-text-muted); padding:0.25rem;">+ ${postsCount - 5} publicaciones adicionales en el plan</div>`;
        }

        const card = document.createElement('div');
        card.className = 'calendar-plan-action-card';
        card.innerHTML = `
            <div class="cpac-header">
                <div class="cpac-icon"><i class="ph ph-calendar-plus"></i></div>
                <div class="cpac-title-wrap">
                    <h4>Plan Listo para Módulo Calendario</h4>
                    <p>${mName} ${plan.year} • ${postsCount} publicaciones listas con copys y formatos</p>
                </div>
            </div>
            <div class="cpac-body">
                <div class="cpac-preview-list">
                    ${postsHtml}
                </div>
            </div>
            <div class="cpac-footer">
                <button class="btn-create-month-action" onclick="executeCreateMonth(this, ${plan.project_id}, ${plan.month}, ${plan.year})">
                    <i class="ph ph-rocket-launch"></i> Crear Mes y Publicaciones en Calendario
                </button>
            </div>
        `;

        // Almacenar el payload del plan en memoria global
        window['calendarPlanData_' + plan.project_id + '_' + plan.month + '_' + plan.year] = plan.posts;

        // Ocultar bloque de código crudo e insertar tarjeta interactiva
        preElement.style.display = 'none';
        preElement.parentNode.insertBefore(card, preElement.nextSibling);
    }

    // Ejecutar la creación del mes y publicaciones en el módulo Calendario
    function executeCreateMonth(btn, projectId, month, year) {
        const posts = window['calendarPlanData_' + projectId + '_' + month + '_' + year] || [];
        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Creando en Calendario...';

        const payload = new URLSearchParams();
        payload.append('action', 'create_calendar_month');
        payload.append('project_id', projectId);
        payload.append('month', month);
        payload.append('year', year);
        payload.append('posts_json', JSON.stringify(posts));

        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: payload.toString()
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const footer = btn.closest('.cpac-footer');
                footer.innerHTML = `
                    <div class="cpac-success-box">
                        <div class="cpac-success-left">
                            <i class="ph ph-check-circle" style="font-size:1.4rem;"></i>
                            <div>
                                <div>¡Mes creado exitosamente en ${res.brand_name}!</div>
                                <div style="font-size:0.75rem; font-weight:normal; opacity:0.85;">Se guardaron ${res.created_posts} publicaciones en estado Borrador</div>
                            </div>
                        </div>
                        <a href="${res.redirect_url}" target="_blank" class="btn-open-month-board">
                            <i class="ph ph-arrow-square-out"></i> Abrir Tablero
                        </a>
                    </div>
                `;
                if (window.showToast) {
                    window.showToast(`¡Mes y ${res.created_posts} publicaciones creadas en Calendario!`, 'success');
                }
            } else {
                alert(res.error || 'No se pudo crear el mes');
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-rocket-launch"></i> Reintentar Creación';
            }
        })
        .catch(err => {
            alert('Error de conexión con el servidor');
            btn.disabled = false;
            btn.innerHTML = '<i class="ph ph-rocket-launch"></i> Reintentar Creación';
        });
    }

    function copySnippet(btn) {
        const pre = btn.closest('.code-block-wrapper').querySelector('pre');
        if (!pre) return;
        navigator.clipboard.writeText(pre.innerText).then(() => {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="ph ph-check" style="color:#10b981;"></i> ¡Copiado!';
            setTimeout(() => { btn.innerHTML = originalHTML; }, 2000);
        });
    }

    function copyMessageText(btn) {
        const bubble = btn.closest('.message-wrapper').querySelector('.message-bubble');
        if (!bubble) return;
        navigator.clipboard.writeText(bubble.innerText).then(() => {
            btn.classList.add('copied');
            const span = btn.querySelector('span');
            const icon = btn.querySelector('i');
            if (span) span.textContent = '¡Copiado!';
            if (icon) icon.className = 'ph ph-check';
            if (window.showToast) window.showToast('Mensaje copiado al portapapeles', 'success');
            setTimeout(() => {
                btn.classList.remove('copied');
                if (span) span.textContent = 'Copiar';
                if (icon) icon.className = 'ph ph-copy';
            }, 2000);
        });
    }

    function showTypingIndicator() {
        let aiIcon = 'ph-sparkle';
        if (activeSkill && activeSkill.icon) {
            aiIcon = activeSkill.icon;
        }
        const sparkleIconEl = document.getElementById('romita-module-sparkle-icon');
        if (sparkleIconEl) {
            sparkleIconEl.className = 'ph-bold ' + aiIcon;
        }

        // Solo iluminar y activar telemetría en la caja de mensaje (Composer)
        const composer = document.querySelector('.romita-input-container') || document.getElementById('romitaComposerCard');
        if (composer) composer.classList.add('is-generating');
        const sendBtn = document.getElementById('sendBtn');
        if (sendBtn) {
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i>';
        }

        // Ciclado dinámico de frases futuristas de telemetría solo en composer
        const statusPhrases = [
            'Romita está procesando el contexto...',
            'Consultando base de proyectos y ecosistema...',
            'Analizando pilares estratégicos y métricas...',
            'Sintetizando propuesta de alto impacto...',
            'Generando respuesta final...'
        ];
        let phraseIdx = 0;
        const statusEl = document.getElementById('romita-module-status-text');
        if (statusEl) statusEl.innerText = statusPhrases[0];

        if (romitaModuleStatusInterval) clearInterval(romitaModuleStatusInterval);
        romitaModuleStatusInterval = setInterval(() => {
            phraseIdx = (phraseIdx + 1) % statusPhrases.length;
            const currentPhrase = statusPhrases[phraseIdx];
            if (statusEl) {
                statusEl.style.opacity = '0';
                setTimeout(() => {
                    if (statusEl) {
                        statusEl.innerText = currentPhrase;
                        statusEl.style.opacity = '1';
                    }
                }, 180);
            }
        }, 2200);
    }

    function removeTypingIndicator() {
        if (romitaModuleStatusInterval) {
            clearInterval(romitaModuleStatusInterval);
            romitaModuleStatusInterval = null;
        }
        const ind = document.getElementById('typingIndicator');
        if (ind) ind.remove();

        const header = document.querySelector('.romita-header');
        if (header) header.classList.remove('is-generating');
        const composer = document.querySelector('.romita-input-container');
        if (composer) composer.classList.remove('is-generating');
        const sendBtn = document.getElementById('sendBtn');
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="ph ph-paper-plane-right"></i>';
        }
        const input = document.getElementById('chatInput');
        if (input) input.focus();
    }

    function newConversation() {
        if(confirm('¿Deseas iniciar una nueva conversación? Se limpiará la vista actual.')) {
            resetToEmptyState();
            loadChatHistoryList();
        }
    }

    function sendMessage() {
        const input = document.getElementById('chatInput');
        const text = input.value.trim();
        const btn = document.getElementById('sendBtn');
        const attachmentToSend = romitaModuleCurrentAttachment;
        const attachmentTypeToSend = romitaModuleAttachmentType;
        
        if (!text && !attachmentToSend) return;
        
        // Preparar preview del mensaje de usuario
        const tempAttUrl = attachmentToSend ? URL.createObjectURL(attachmentToSend) : null;
        const tempAttName = attachmentToSend ? attachmentToSend.name : null;

        // Limpiar input y adjunto
        input.value = '';
        input.style.height = 'auto';
        input.disabled = true;
        btn.disabled = true;
        removeRomitaModuleAttachment();

        // Añadir mensaje del usuario a UI con adjunto si existe
        addMessageToUI('user', text, null, null, tempAttUrl, attachmentTypeToSend, tempAttName);
        chatHistory.push({role: 'user', content: text});
        
        showTypingIndicator();

        // Preparar parámetros multipart con FormData
        const payload = new FormData();
        payload.append('action', 'chat');
        payload.append('message', text);
        payload.append('specialty', currentSpecialty || 'director_360');
        payload.append('current_module', 'romita');
        if (activeSkill) {
            payload.append('skill_prompt', activeSkill.prompt);
        }

        if (attachmentToSend) {
            payload.append('attachment', attachmentToSend);
        }

        // Vincular con proyecto de calendario o marca prept seleccionada
        if (selectedBrand) {
            if (selectedBrand.type === 'project') {
                payload.append('project_id', selectedBrand.id);
            } else if (selectedBrand.type === 'prept') {
                payload.append('prept_id', selectedBrand.id);
            }
        }

        if (currentChatId) payload.append('chat_id', currentChatId);

        // Envío AJAX multipart
        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            body: payload
        })
        .then(res => res.json())
        .then(data => {
            removeTypingIndicator();
            input.disabled = false;
            btn.disabled = false;
            input.focus();

            if(data.success) {
                if(data.chat_id) currentChatId = data.chat_id;
                addMessageToUI('assistant', data.response, data.message_id);
                chatHistory.push({role: 'assistant', content: data.response, id: data.message_id});
                loadChatHistoryList();
                playRomitaChime();
            } else {
                addMessageToUI('assistant', '<i class="ph ph-warning-circle" style="color:#ef4444;"></i> Ocurrió un error: ' + (data.error || 'Desconocido'));
            }
        })
        .catch(err => {
            removeTypingIndicator();
            input.disabled = false;
            btn.disabled = false;
            addMessageToUI('assistant', '<i class="ph ph-warning-circle" style="color:#ef4444;"></i> Error de conexión con el servidor.');
        });
    }

    // FASE 3: Listeners de Drag & Drop y Pegado de Portapapeles (Ctrl+V)
    function initRomitaModuleMultimodalListeners() {
        const composer = document.getElementById('romitaComposerCard') || document.querySelector('.romita-input-container') || document.querySelector('.romita-composer');
        const input = document.getElementById('chatInput');
        // 1. Drag & Drop directo sobre el Compositor y en la Ventana (Estilo Burbuja Romita)
        let dragCounter = 0;

        window.addEventListener('dragenter', e => {
            if (e.dataTransfer && e.dataTransfer.types && Array.from(e.dataTransfer.types).includes('Files')) {
                dragCounter++;
                if (composer) composer.classList.add('drag-over');
            }
        });

        window.addEventListener('dragleave', e => {
            dragCounter--;
            if (dragCounter <= 0) {
                dragCounter = 0;
                if (composer) composer.classList.remove('drag-over');
            }
        });

        window.addEventListener('dragover', e => {
            e.preventDefault();
        });

        window.addEventListener('drop', e => {
            e.preventDefault();
            dragCounter = 0;
            if (composer) composer.classList.remove('drag-over');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                setRomitaModuleAttachment(e.dataTransfer.files[0]);
            }
        });

        // 2. Drag & Drop directo sobre el Compositor
        if (composer) {
            ['dragenter', 'dragover'].forEach(evt => {
                composer.addEventListener(evt, e => {
                    e.preventDefault();
                    e.stopPropagation();
                    composer.classList.add('drag-over');
                });
            });
            ['dragleave', 'drop'].forEach(evt => {
                composer.addEventListener(evt, e => {
                    e.preventDefault();
                    e.stopPropagation();
                    composer.classList.remove('drag-over');
                });
            });
            composer.addEventListener('drop', e => {
                e.preventDefault();
                e.stopPropagation();
                composer.classList.remove('drag-over');
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                    setRomitaModuleAttachment(e.dataTransfer.files[0]);
                }
            });
        }

        // 3. Pegado de Portapapeles (Ctrl+V) tanto en el Textarea como Global
        const handlePasteEvent = (e) => {
            if (e.clipboardData && e.clipboardData.items) {
                for (let i = 0; i < e.clipboardData.items.length; i++) {
                    const item = e.clipboardData.items[i];
                    if (item.kind === 'file') {
                        const file = item.getAsFile();
                        if (file) {
                            e.preventDefault();
                            setRomitaModuleAttachment(file);
                            break;
                        }
                    }
                }
            }
        };

        if (input) {
            input.addEventListener('paste', handlePasteEvent);
        }
        document.addEventListener('paste', e => {
            if (e.target !== input && !e.target.matches('input, textarea')) {
                handlePasteEvent(e);
            }
        });
    }

    <?php if($is_admin): ?>
    function openSkillsModal() {
        document.getElementById('form-skill').reset();
        document.getElementById('skillId').value = '';
        document.getElementById('btnDeleteSkill').style.display = 'none';
        document.getElementById('modal-skills').classList.add('active');
    }

    function editSkill(id, name, prompt, role) {
        document.getElementById('skillId').value = id;
        document.getElementById('skillName').value = name;
        document.getElementById('skillPrompt').value = prompt;
        document.getElementById('skillRole').value = role || 'all';
        document.getElementById('btnDeleteSkill').style.display = 'inline-flex';
        document.getElementById('modal-skills').classList.add('active');
    }

    function saveSkill() {
        const id = document.getElementById('skillId').value;
        const name = document.getElementById('skillName').value.trim();
        const prompt = document.getElementById('skillPrompt').value.trim();
        const role = document.getElementById('skillRole').value;

        if(!name || !prompt) return alert('Llene todos los campos requeridos');

        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=save_skill&id=${id}&name=${encodeURIComponent(name)}&prompt_base=${encodeURIComponent(prompt)}&role=${encodeURIComponent(role)}`
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) location.reload();
            else alert(res.error);
        });
    }

    function deleteSkill() {
        if(!confirm('¿Estás seguro de eliminar este skill?')) return;
        const id = document.getElementById('skillId').value;
        
        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=delete_skill&id=${id}`
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                window.location.reload();
            } else {
                alert('Error al eliminar: ' + res.error);
            }
        });
    }

    function openPreptsModal() {
        document.getElementById('form-prept').reset();
        document.getElementById('preptId').value = '';
        document.getElementById('modal-prepts').classList.add('active');
    }

    function editPrept(id, name, tone, archetype, audience, rules, forbidden_words) {
        document.getElementById('preptId').value = id;
        document.getElementById('preptName').value = name;
        document.getElementById('preptTone').value = tone || '';
        document.getElementById('preptArchetype').value = archetype || '';
        document.getElementById('preptAudience').value = audience || '';
        document.getElementById('preptRules').value = rules || '';
        document.getElementById('preptForbiddenWords').value = forbidden_words || '';
        document.getElementById('modal-prepts').classList.add('active');
    }

    function savePrept() {
        const id = document.getElementById('preptId').value;
        const name = document.getElementById('preptName').value.trim();
        const tone = document.getElementById('preptTone').value.trim();
        const archetype = document.getElementById('preptArchetype').value.trim();
        const audience = document.getElementById('preptAudience').value.trim();
        const rules = document.getElementById('preptRules').value.trim();
        const forbidden_words = document.getElementById('preptForbiddenWords').value.trim();

        if(!name) return alert('El nombre de la marca es obligatorio');

        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=save_prept&id=${id}&name=${encodeURIComponent(name)}&tone=${encodeURIComponent(tone)}&archetype=${encodeURIComponent(archetype)}&audience=${encodeURIComponent(audience)}&rules=${encodeURIComponent(rules)}&forbidden_words=${encodeURIComponent(forbidden_words)}`
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) location.reload();
            else alert(res.error);
        });
    }
    <?php endif; ?>

    // =========================================================================
    // FASE 4: FUNCIONES JS DE MEMORIA, SUPER-PROMPTS, SHARING & EXPORTACIÓN
    // =========================================================================

    // 1. Compartir Chat Activo
    function shareCurrentChat() {
        if (!currentChatId) {
            alert('Para compartir una conversación, primero inicia un chat o selecciona uno del historial.');
            return;
        }

        const btn = document.getElementById('btnShareChat');
        if (btn) btn.disabled = true;

        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=generate_share_link&chat_id=${currentChatId}`
        })
        .then(r => r.json())
        .then(res => {
            if (btn) btn.disabled = false;
            if (res.success && res.share_url) {
                navigator.clipboard.writeText(res.share_url).then(() => {
                    if (window.showToast) {
                        window.showToast('¡Enlace copiado al portapapeles! Tus compañeros pueden abrirlo para ver este chat.', 'success');
                    } else {
                        alert('¡Enlace de chat generado y copiado al portapapeles!\n\n' + res.share_url);
                    }
                }).catch(() => {
                    prompt('Copia este enlace para compartir la conversación con tu equipo:', res.share_url);
                });
            } else {
                alert(res.error || 'No se pudo generar el enlace para compartir.');
            }
        })
        .catch(err => {
            if (btn) btn.disabled = false;
            alert('Error de conexión al generar el enlace de compartir.');
        });
    }

    // 2. Clonar / Importar Chat Compartido a Mis Chats
    function cloneSharedChat(chatId) {
        if (!confirm('¿Deseas clonar este chat compartido para continuar la conversación en tus propios chats?')) return;
        
        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_messages&chat_id=${chatId}`
        })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.messages && res.messages.length > 0) {
                currentChatId = null;
                const container = document.getElementById('chatStreamInner');
                const messages = container.querySelectorAll('.romita-message');
                messages.forEach(m => m.remove());
                const emptyState = document.getElementById('emptyState');
                if(emptyState) emptyState.style.display = 'none';

                chatHistory = [];
                res.messages.forEach(msg => {
                    chatHistory.push({role: msg.role, content: msg.content});
                    addMessageToUI(msg.role, msg.content, null, null, msg.attachment_url, msg.attachment_type, msg.attachment_name);
                });

                const banner = document.getElementById('sharedNoticeBanner');
                if (banner) banner.style.display = 'none';

                window.history.replaceState({}, document.title, 'index.php?module=romita&action=index');
                if (window.showToast) window.showToast('¡Chat clonado! Ahora puedes continuar interactuando con Romita.', 'success');
            } else {
                alert('No se pudieron obtener los mensajes para clonar.');
            }
        });
    }

    // 3. Exportación Formal a PDF con Membrete Corporativo
    function exportChatToPdf() {
        const streamInner = document.getElementById('chatStreamInner');
        if (!streamInner || streamInner.querySelectorAll('.romita-message').length === 0) {
            alert('No hay mensajes para exportar en esta conversación.');
            return;
        }

        window.print();
    }

    // 4. Modal de Preferencias del Usuario & Memoria Permanente
    function openUserPrefsModal() {
        const modal = document.getElementById('modal-user-prefs');
        if (modal) modal.classList.add('active');
    }

    function closeUserPrefsModal() {
        const modal = document.getElementById('modal-user-prefs');
        if (modal) modal.classList.remove('active');
    }

    function saveUserPreferences() {
        const selectedRadio = document.querySelector('input[name="pref_response_style"]:checked');
        const style = selectedRadio ? selectedRadio.value : 'strategic';
        const instructions = document.getElementById('prefCustomInstructions').value.trim();

        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=save_user_preferences&response_style=${encodeURIComponent(style)}&custom_instructions=${encodeURIComponent(instructions)}&default_specialty=${encodeURIComponent(currentSpecialty)}`
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closeUserPrefsModal();
                if (window.showToast) {
                    window.showToast('¡Preferencias de memoria guardadas! Romita adaptará su estilo en tus próximas interacciones.', 'success');
                } else {
                    alert('Preferencias guardadas exitosamente.');
                }
            } else {
                alert(res.error || 'Error al guardar las preferencias.');
            }
        });
    }

    // 5. Modal de Biblioteca de Super-Prompts
    function openSuperPromptsModal() {
        const modal = document.getElementById('modal-super-prompts');
        if (modal) modal.classList.add('active');
    }

    function closeSuperPromptsModal() {
        const modal = document.getElementById('modal-super-prompts');
        if (modal) modal.classList.remove('active');
    }

    function filterSuperPrompts() {
        const query = (document.getElementById('spSearchInput').value || '').toLowerCase().trim();
        const activeCatBtn = document.querySelector('.sp-tab-btn.active');
        const cat = activeCatBtn ? activeCatBtn.dataset.cat : 'all';

        document.querySelectorAll('#superPromptsList .superprompt-card').forEach(card => {
            const cardTitle = (card.dataset.title || '').toLowerCase();
            const cardCat = card.dataset.category || '';
            const matchQuery = !query || cardTitle.includes(query) || (card.innerText || '').toLowerCase().includes(query);
            const matchCat = (cat === 'all' || cardCat === cat);

            card.style.display = (matchQuery && matchCat) ? 'flex' : 'none';
        });
    }

    function filterCategorySp(category, btn) {
        document.querySelectorAll('.sp-tab-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        filterSuperPrompts();
    }

    function useSuperPromptText(promptText) {
        closeSuperPromptsModal();
        usePromptStarter(promptText);
    }

    function copySuperPromptText(promptText) {
        navigator.clipboard.writeText(promptText).then(() => {
            if (window.showToast) {
                window.showToast('Prompt copiado al portapapeles.', 'info');
            } else {
                alert('Prompt copiado al portapapeles.');
            }
        });
    }

    function toggleCreatePromptForm() {
        const wrap = document.getElementById('spCreateFormWrap');
        if (wrap) {
            wrap.style.display = (wrap.style.display === 'none') ? 'block' : 'none';
            if (wrap.style.display === 'block') {
                document.getElementById('newSpTitle').focus();
            }
        }
    }

    function saveNewSuperPrompt() {
        const title = document.getElementById('newSpTitle').value.trim();
        const category = document.getElementById('newSpCategory').value;
        const prompt = document.getElementById('newSpPrompt').value.trim();

        if (!title || !prompt) {
            alert('Por favor completa el título y el prompt.');
            return;
        }

        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=save_super_prompt&title=${encodeURIComponent(title)}&category=${encodeURIComponent(category)}&prompt=${encodeURIComponent(prompt)}`
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                location.reload();
            } else {
                alert(res.error || 'Error al guardar el super-prompt.');
            }
        });
    }

    // 6. Carga automática de mensajes compartidos si existe token
    function loadSharedChatMessages(messages, chatId) {
        currentChatId = chatId;
        const emptyState = document.getElementById('emptyState');
        if(emptyState) emptyState.style.display = 'none';

        messages.forEach(msg => {
            let actualRole = msg.role;
            if (actualRole === 'user' && (
                msg.content.includes('"project_id"') || 
                msg.content.includes('|---|') || 
                msg.content.includes('| :---') ||
                msg.content.startsWith('¡Hola') || 
                msg.content.startsWith('¡Excelente') ||
                msg.content.startsWith('Como tu experto') ||
                msg.content.length > 300
            )) {
                actualRole = 'assistant';
            }
            chatHistory.push({role: actualRole, content: msg.content, id: msg.id});
            addMessageToUI(actualRole, msg.content, msg.id, msg.feedback, msg.attachment_url, msg.attachment_type, msg.attachment_name);
        });
    }
</script>

<?php
require_once 'includes/footer.php';
?>
