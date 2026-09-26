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

// 2. Fetch Prepts (con control de excepciones independiente)
try {
    $stmtPrepts = $db->query("SELECT id, name, tone, audience, rules FROM romita_prepts ORDER BY name ASC");
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
        <!-- Topbar / Header -->
        <header class="romita-header">
            <div class="romita-brand">
                <button class="btn-toggle-sidebar" onclick="toggleSidebar()" title="Ver Historial">
                    <i class="ph ph-sidebar-simple"></i>
                </button>
                <div class="romita-logo" style="overflow: hidden; padding: 0; background: #0a0f1d; border: 1px solid rgba(56, 189, 248, 0.3);">
                    <img src="assets/img/romita-avatar.png" alt="Romita" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div class="romita-brand-info">
                    <div class="romita-brand-title-wrap">
                        <h2 class="romita-greeting-title">
                            <span>Romita AI</span>
                            <span class="user-greeting-pill">• Hola, <?php echo $first_name; ?></span>
                        </h2>
                    </div>
                    <div class="romita-badge-wrap">
                        <span class="romita-model-badge">
                            <i class="ph ph-lightning"></i>
                            <span class="badge-text-desktop">Gemini 2.5 Flash • Web Grounding</span>
                            <span class="badge-text-mobile">Gemini 2.5</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="romita-actions">
                <!-- Buscador dentro del chat activo -->
                <div class="search-chat-container hide-mobile">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" id="chatSearch" placeholder="Buscar en mensaje..." class="search-chat-input" onkeyup="searchChat(this.value)">
                </div>
                
                <!-- Selector de Marca / Proyecto de Calendario -->
                <div class="prept-select-wrap" title="Vincular con Proyecto de Calendario o Marca Prept">
                    <i class="ph ph-briefcase prept-select-icon"></i>
                    <select id="activeBrandSelect" class="prept-select-custom" onchange="handleBrandSelection(this.value)">
                        <option value="">-- Sin Marca (Modo Libre) --</option>
                        <?php if (!empty($calendarProjects)): ?>
                            <optgroup label="Proyectos de Calendario (Historial de Meses)">
                                <?php foreach($calendarProjects as $cp): ?>
                                    <option value="project_<?php echo $cp['project_id']; ?>" 
                                            data-type="project" 
                                            data-id="<?php echo $cp['project_id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($cp['brand_name']); ?>"
                                            data-months="<?php echo $cp['total_months']; ?>"
                                            data-posts="<?php echo $cp['total_posts']; ?>">
                                        <?php echo htmlspecialchars($cp['brand_name']); ?> (<?php echo $cp['total_months']; ?> meses • <?php echo $cp['total_posts']; ?> posts)
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
                    <i class="ph ph-caret-down prept-select-caret"></i>
                </div>

                <!-- Chip de Inteligencia de Marca Activa -->
                <div class="brand-intel-chip" id="brandIntelChip" style="display:none;"></div>

                <div class="romita-header-buttons">
                    <button class="btn-romita-action" onclick="newConversation()" title="Limpiar y empezar nuevo chat">
                        <i class="ph ph-broom"></i> <span class="hide-mobile">Limpiar</span>
                    </button>

                    <?php if($is_admin): ?>
                        <button class="btn-romita-action" onclick="openPreptsModal()" title="Gestionar Marcas (Prepts)">
                            <i class="ph ph-buildings"></i> <span class="hide-mobile">Prepts</span>
                        </button>
                        <button class="btn-romita-action" onclick="openSkillsModal()" title="Configurar Habilidades (Skills)">
                            <i class="ph ph-sliders"></i> <span class="hide-mobile">Skills</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="romita-header-laser"></div>
        </header>

        <!-- Segmented Specialties Bar (Modern SaaS App Style - Zero Emojis) -->
        <div class="romita-module-specialties">
            <div class="rg-specialties-bar">
                <button type="button" class="rg-spec-tab active" data-spec="director_360" onclick="setModuleSpecialty('director_360', this)">
                    <i class="ph ph-compass"></i>
                    <span>Directora 360°</span>
                </button>
                <button type="button" class="rg-spec-tab" data-spec="community_manager" onclick="setModuleSpecialty('community_manager', this)">
                    <i class="ph ph-chat-circle-dots"></i>
                    <span>Senior CM</span>
                </button>
                <button type="button" class="rg-spec-tab" data-spec="branding" onclick="setModuleSpecialty('branding', this)">
                    <i class="ph ph-palette"></i>
                    <span>Branding</span>
                </button>
                <button type="button" class="rg-spec-tab" data-spec="marketing" onclick="setModuleSpecialty('marketing', this)">
                    <i class="ph ph-trend-up"></i>
                    <span>Marketing</span>
                </button>
                <button type="button" class="rg-spec-tab" data-spec="seo" onclick="setModuleSpecialty('seo', this)">
                    <i class="ph ph-magnifying-glass"></i>
                    <span>SEO</span>
                </button>
            </div>
        </div>

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
            <div class="romita-input-container">
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
                    </div>
                </div>

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
                    <div class="composer-hints">
                        <span><kbd class="composer-hint-badge">Shift + Enter</kbd> para salto de línea</span>
                        <span style="margin-left: 8px;"><kbd class="composer-hint-badge">/</kbd> comandos rápidos</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
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
    <div class="romita-modal-card">
        <div class="romita-modal-header">
            <h3><i class="ph ph-buildings" style="color: #8b5cf6;"></i> Gestión de Prepts (Marcas)</h3>
            <button type="button" class="btn-close-modal" onclick="document.getElementById('modal-prepts').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <div class="romita-modal-body">
            <form id="form-prept" onsubmit="event.preventDefault(); savePrept();">
                <input type="hidden" id="preptId" name="prept_id">
                <div style="margin-bottom: 0.85rem;">
                    <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Nombre de la Marca</label>
                    <input type="text" id="preptName" class="form-control" required placeholder="Ej: Roma Agencia" style="border-radius:8px;">
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Tono de voz</label>
                    <input type="text" id="preptTone" class="form-control" placeholder="Ej: Profesional, disruptivo, empático..." style="border-radius:8px;">
                </div>
                <div style="margin-bottom: 0.85rem;">
                    <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Audiencia objetivo</label>
                    <input type="text" id="preptAudience" class="form-control" placeholder="Ej: Dueños de negocios B2B en Latinoamérica" style="border-radius:8px;">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; margin-bottom:0.3rem; font-weight:600; font-size:0.82rem;">Reglas de contenido adicionales</label>
                    <textarea id="preptRules" class="form-control" rows="3" placeholder="Ej: Siempre usar lenguaje positivo, nunca mencionar competidores directamente..." style="border-radius:8px;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; border-radius:8px;"><i class="ph ph-floppy-disk"></i> Guardar Marca (Prept)</button>
            </form>
            
            <hr style="margin: 1.25rem 0; border-color: var(--romita-border);">
            
            <h4 style="font-size:0.9rem; font-weight:700; margin-bottom:0.75rem;">Marcas Existentes</h4>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 180px; overflow-y: auto;">
                <?php foreach($prepts as $p): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--romita-card-hover); padding:0.6rem 0.85rem; border-radius:8px; border:1px solid var(--romita-border);">
                        <div>
                            <strong style="font-size:0.85rem;"><?php echo htmlspecialchars($p['name']); ?></strong>
                            <div style="font-size:0.75rem; color:var(--romita-text-muted);"><?php echo htmlspecialchars($p['tone']); ?></div>
                        </div>
                        <button class="btn btn-sm btn-outline" onclick="editPrept(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', '<?php echo addslashes($p['tone']); ?>', '<?php echo addslashes($p['audience']); ?>', '<?php echo addslashes($p['rules']); ?>')" style="border-radius:6px;"><i class="ph ph-pencil"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
            sub: 'Soy Romita como Senior Community Manager. Especialista en copys magnéticos, ganchos virales, calendarios y engagement.',
            starters: [
                { icon: 'ph-lightning', title: '5 Ganchos para Reels', desc: 'Fórmulas de retención para primeros 3 segundos.', prompt: 'Dame 5 ganchos magnéticos para Reels de nuestras marcas este mes.' },
                { icon: 'ph-calendar-plus', title: 'Estructura de Calendario', desc: 'Equilibrio de pilares de venta y valor.', prompt: '¿Cómo estructurar un calendario de 12 posts balanceando venta, valor y engagement?' },
                { icon: 'ph-chats-circle', title: 'Dinámicas de Engagement', desc: 'Historias interactivas para disparar DMs.', prompt: 'Propón 4 ideas de historias interactivas para aumentar mensajes directos y respuestas.' },
                { icon: 'ph-target', title: 'Llamados a la Acción (CTA)', desc: 'Fórmulas persuasivas que no suenan a spam.', prompt: 'Dame 5 fórmulas de Call to Action (CTA) de alta conversión para publicaciones.' }
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

    function setModuleSpecialty(spec, btn) {
        currentSpecialty = spec;
        document.querySelectorAll('.romita-module-specialties .rg-spec-tab').forEach(c => c.classList.remove('active'));
        if (btn) {
            btn.classList.add('active');
            try {
                btn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            } catch(e) {}
        }
        
        const heroSub = document.getElementById('heroSubtext');
        const data = specialtyHeroData[spec] || specialtyHeroData['director_360'];
        if (heroSub && !selectedBrand) {
            heroSub.innerText = data.sub;
        }

        if (!selectedBrand) {
            renderDefaultPromptStarters();
        }
    }

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
                    chatHistory.push({role: actualRole, content: msg.content});
                    addMessageToUI(actualRole, msg.content);
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

    function renderRomitaTaskActionCard(jsonContent) {
        try {
            const data = JSON.parse(jsonContent.trim());
            const tasks = data.tasks || [];
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
                + `<span class="rac-icon-pill rac-icon-kanban"><i class="ph-bold ph-kanban"></i></span>`
                + `<div class="rac-header-titles">`
                + `<strong class="rac-title">Acción: Crear tareas en Kanban</strong>`
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
                + `<i class="ph-bold ph-plus-circle"></i> <span class="btn-text">Insertar ${tasks.length} tareas en el Kanban</span>`
                + `</button>`
                + `<button type="button" class="btn-rac-modal" onclick="openRomitaTaskEditModal('${cardId}', 0)" title="Abrir y configurar en Modal">`
                + `<i class="ph ph-sliders-horizontal"></i> <span>Abrir en Modal</span>`
                + `</button>`
                + `<a href="index.php?module=tasks" target="_blank" class="rac-link-kanban" title="Abrir módulo de tareas">`
                + `Ir al Kanban <i class="ph ph-arrow-up-right"></i>`
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
                                <span>¡Tarea "${escapeRomitaHtml(title)}" creada en el Kanban!</span>
                                <a href="index.php?module=tasks" target="_blank" class="rac-btn-view">
                                    Abrir Kanban <i class="ph ph-arrow-up-right"></i>
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
            btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Creando en Kanban...';
        }

        try {
            const formData = new FormData();
            formData.append('action', 'tool_create_tasks');
            formData.append('tasks', JSON.stringify(selectedTasks));

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
                            <a href="index.php?module=tasks" target="_blank" class="rac-btn-view">
                                Abrir Kanban <i class="ph ph-arrow-up-right"></i>
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

    function addMessageToUI(role, content) {
        const container = document.getElementById('chatStreamInner');
        const emptyState = document.getElementById('emptyState');
        if(emptyState) emptyState.style.display = 'none';

        const msgDiv = document.createElement('div');
        msgDiv.className = `romita-message ${role}`;
        
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

            preprocessed = preprocessed.replace(/```romita-action:create_tasks\s*([\s\S]*?)```/g, function(match, jsonContent) {
                const placeholder = '<!--ROMITA_ACTION_TASKS_' + actionPlaceholders.length + '-->';
                actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaTaskActionCard(jsonContent) });
                return '\n\n' + placeholder + '\n\n';
            });

            preprocessed = preprocessed.replace(/```romita-action:schedule_meeting\s*([\s\S]*?)```/g, function(match, jsonContent) {
                const placeholder = '<!--ROMITA_ACTION_MEET_' + actionPlaceholders.length + '-->';
                actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaMeetingActionCard(jsonContent) });
                return '\n\n' + placeholder + '\n\n';
            });

            preprocessed = preprocessed.replace(/```romita-action:whatsapp_message\s*([\s\S]*?)```/g, function(match, jsonContent) {
                const placeholder = '<!--ROMITA_ACTION_WA_' + actionPlaceholders.length + '-->';
                actionPlaceholders.push({ placeholder: placeholder, html: renderRomitaWhatsappActionCard(jsonContent) });
                return '\n\n' + placeholder + '\n\n';
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

            // Barra de utilidades
            const actionsBar = document.createElement('div');
            actionsBar.className = 'message-actions-bar';
            actionsBar.innerHTML = `
                <button class="btn-message-action" onclick="copyMessageText(this)" title="Copiar texto">
                    <i class="ph ph-copy"></i> <span>Copiar</span>
                </button>
            `;
            wrapper.appendChild(bubble);
            wrapper.appendChild(actionsBar);
        } else {
            const p = document.createElement('div');
            p.textContent = content;
            bubble.appendChild(p);
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

        // Efectos dinámicos en cabecera y composer
        const header = document.querySelector('.romita-header');
        if (header) header.classList.add('is-generating');
        const composer = document.querySelector('.romita-input-container');
        if (composer) composer.classList.add('is-generating');
        const sendBtn = document.getElementById('sendBtn');
        if (sendBtn) {
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i>';
        }

        // Ciclado dinámico de frases futuristas de telemetría en el composer
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
            if (statusEl) {
                statusEl.style.opacity = '0';
                setTimeout(() => {
                    if (statusEl) {
                        statusEl.innerText = statusPhrases[phraseIdx];
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
        
        if (!text) return;
        
        // Bloquear input temporalmente
        input.value = '';
        input.style.height = 'auto';
        input.disabled = true;
        btn.disabled = true;

        // Añadir mensaje del usuario a UI
        addMessageToUI('user', text);
        chatHistory.push({role: 'user', content: text});
        
        showTypingIndicator();

        // Preparar parámetros
        const payload = new URLSearchParams();
        payload.append('action', 'chat');
        payload.append('message', text);
        payload.append('specialty', currentSpecialty || 'director_360');
        payload.append('current_module', 'romita');
        if (activeSkill) {
            payload.append('skill_prompt', activeSkill.prompt);
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

        // Envío AJAX
        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: payload.toString()
        })
        .then(res => res.json())
        .then(data => {
            removeTypingIndicator();
            input.disabled = false;
            btn.disabled = false;
            input.focus();

            if(data.success) {
                if(data.chat_id) currentChatId = data.chat_id;
                addMessageToUI('assistant', data.response);
                chatHistory.push({role: 'assistant', content: data.response});
                loadChatHistoryList();
            } else {
                addMessageToUI('assistant', '<i class="ph ph-warning-circle" style="color:#ef4444;"></i> Ocurrió un error: ' + data.error);
            }
        })
        .catch(err => {
            removeTypingIndicator();
            input.disabled = false;
            btn.disabled = false;
            addMessageToUI('assistant', '<i class="ph ph-warning-circle" style="color:#ef4444;"></i> Error de conexión con el servidor.');
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

    function editPrept(id, name, tone, audience, rules) {
        document.getElementById('preptId').value = id;
        document.getElementById('preptName').value = name;
        document.getElementById('preptTone').value = tone;
        document.getElementById('preptAudience').value = audience;
        document.getElementById('preptRules').value = rules;
        document.getElementById('modal-prepts').classList.add('active');
    }

    function savePrept() {
        const id = document.getElementById('preptId').value;
        const name = document.getElementById('preptName').value.trim();
        const tone = document.getElementById('preptTone').value.trim();
        const audience = document.getElementById('preptAudience').value.trim();
        const rules = document.getElementById('preptRules').value.trim();

        if(!name) return alert('El nombre de la marca es obligatorio');

        fetch('ajax/ajax_romita.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=save_prept&id=${id}&name=${encodeURIComponent(name)}&tone=${encodeURIComponent(tone)}&audience=${encodeURIComponent(audience)}&rules=${encodeURIComponent(rules)}`
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) location.reload();
            else alert(res.error);
        });
    }
    <?php endif; ?>
</script>

<?php
require_once 'includes/footer.php';
?>
