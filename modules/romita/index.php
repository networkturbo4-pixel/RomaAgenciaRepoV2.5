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
                <div class="romita-logo">
                    <i class="ph ph-sparkle"></i>
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
                            <span class="badge-text-desktop">Gemini 2.5 Flash • Web Grounding 🌐</span>
                            <span class="badge-text-mobile">Gemini 2.5 🌐</span>
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
                            <optgroup label="📅 Proyectos de Calendario (Historial de Meses)">
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
                            <optgroup label="🏷️ Marcas Prepts (Instrucción Manual)">
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
        </header>

        <!-- Feed del Chat -->
        <div class="romita-chat-area" id="chatArea">
            <div class="chat-stream-inner" id="chatStreamInner">
                <!-- Empty State Hero -->
                <div class="romita-empty-state" id="emptyState">
                    <div class="romita-hero-glow">
                        <div class="romita-hero-icon">
                            <i class="ph ph-sparkle"></i>
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
                <textarea id="chatInput" class="romita-textarea" placeholder="Escribe tu mensaje o pide un plan para una marca..." rows="1" oninput="autoResize(this)" onkeydown="handleEnter(event)"></textarea>
                
                <div class="romita-composer-bottom">
                    <div class="composer-hints">
                        <span><kbd class="composer-hint-badge">Shift + Enter</kbd> para salto de línea</span>
                    </div>
                    <button class="romita-send-btn" id="sendBtn" onclick="sendMessage()" title="Enviar mensaje">
                        <i class="ph ph-paper-plane-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </main>
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
    let activeSkill = null;
    let chatHistory = [];
    let currentChatId = null;
    let allCachedChats = [];
    let selectedBrand = null;
    
    // Auto-ajuste de altura de textarea
    function autoResize(el) {
        el.style.height = 'auto';
        el.style.height = (el.scrollHeight < 180 ? el.scrollHeight : 180) + 'px';
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

    function renderDefaultPromptStarters() {
        const grid = document.getElementById('promptGrid');
        if (!grid) return;

        grid.innerHTML = `
            <div class="prompt-starter-card" onclick="usePromptStarter('Escribe 3 propuestas de copys persuasivos para redes sociales sobre [Tema / Producto] enfocados en llamar a la acción.')">
                <div class="prompt-starter-icon copywriting">
                    <i class="ph ph-pencil-line"></i>
                </div>
                <div class="prompt-starter-details">
                    <h4>Copywriting Persuasivo</h4>
                    <p>Crea copys de alto impacto para Instagram, Facebook o LinkedIn.</p>
                </div>
            </div>

            <div class="prompt-starter-card" onclick="usePromptStarter('Diseña un guión estratégico para responder a clientes que dicen que el precio de [Servicio] es elevado.')">
                <div class="prompt-starter-icon sales">
                    <i class="ph ph-handshake"></i>
                </div>
                <div class="prompt-starter-details">
                    <h4>Estrategia Comercial</h4>
                    <p>Manejo de objeciones y guiones de venta profesionales.</p>
                </div>
            </div>

            <div class="prompt-starter-card" onclick="usePromptStarter('Sintetiza y extrae los 5 puntos clave más relevantes sobre las tendencias actuales de...')">
                <div class="prompt-starter-icon analysis">
                    <i class="ph ph-chart-bar"></i>
                </div>
                <div class="prompt-starter-details">
                    <h4>Análisis & Síntesis</h4>
                    <p>Resume textos, investiga con Google y estructura conclusiones.</p>
                </div>
            </div>

            <div class="prompt-starter-card" onclick="usePromptStarter('Ayúdame a redactar una propuesta de servicios detallada para [Cliente / Proyecto] con fases y entregables.')">
                <div class="prompt-starter-icon automation">
                    <i class="ph ph-briefcase"></i>
                </div>
                <div class="prompt-starter-details">
                    <h4>Propuesta de Servicios</h4>
                    <p>Estructura cotizaciones, fases de proyecto y condiciones comerciales.</p>
                </div>
            </div>
        `;
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
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
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
        avatar.innerHTML = role === 'user' ? '<i class="ph ph-user"></i>' : `<i class="ph ${aiIcon}"></i>`;
        
        const wrapper = document.createElement('div');
        wrapper.className = 'message-wrapper';

        const bubble = document.createElement('div');
        bubble.className = `message-bubble ${role === 'assistant' ? 'markdown-body' : ''}`;
        
        if(role === 'assistant') {
            const preprocessed = preprocessMarkdownTables(content);
            bubble.innerHTML = marked.parse(preprocessed);
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
            const colsCount = table.querySelectorAll('tr:first-child th, tr:first-child td').length;

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
                            <i class="ph ph-file-csv"></i> <span>Copiar para Excel</span>
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
        const container = document.getElementById('chatStreamInner');
        const msgDiv = document.createElement('div');
        msgDiv.className = 'romita-message assistant typing-msg';
        msgDiv.id = 'typingIndicator';
        
        let aiIcon = 'ph-sparkle';
        if (activeSkill && activeSkill.icon) {
            aiIcon = activeSkill.icon;
        }
        
        msgDiv.innerHTML = `
            <div class="message-avatar ai-avatar"><i class="ph ${aiIcon}"></i></div>
            <div class="message-wrapper">
                <div class="typing-bubble">
                    <span class="typing-text">Romita está pensando</span>
                    <div class="typing-dots">
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(msgDiv);
        const chatArea = document.getElementById('chatArea');
        chatArea.scrollTop = chatArea.scrollHeight;
    }

    function removeTypingIndicator() {
        const ind = document.getElementById('typingIndicator');
        if(ind) ind.remove();
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
                addMessageToUI('assistant', '⚠️ Ocurrió un error: ' + data.error);
            }
        })
        .catch(err => {
            removeTypingIndicator();
            input.disabled = false;
            btn.disabled = false;
            addMessageToUI('assistant', '⚠️ Error de conexión con el servidor.');
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
