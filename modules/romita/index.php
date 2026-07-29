<?php
// modules/romita/index.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'includes/header.php';
global $db;

// Verify user role
$stmt_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt_admin->execute([$_SESSION['user_id']]);
$is_admin = ($stmt_admin->fetchColumn() == 1);

// Fetch active skills
$skills = [];
try {
    // Fetch active skills for user role
    $user_role = $_SESSION['role'] ?? 'user';
    if ($is_admin) {
        $stmt = $db->query("SELECT id, name, description, prompt_base, allowed_role FROM romita_skills ORDER BY name ASC");
    } else {
        $stmt = $db->prepare("SELECT id, name, description, prompt_base, allowed_role FROM romita_skills WHERE allowed_role = 'all' OR allowed_role = ? ORDER BY name ASC");
        $stmt->execute([$user_role]);
    }
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch Prepts
    $stmtPrepts = $db->query("SELECT id, name, tone, audience, rules FROM romita_prepts ORDER BY name ASC");
    $prepts = $stmtPrepts->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

$first_name = htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]);
?>

<link rel="stylesheet" href="assets/css/romita.css?v=<?php echo filemtime('assets/css/romita.css'); ?>">
<!-- Include Marked.js for markdown parsing -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<div class="romita-container" style="flex-direction: row;">
    
    <!-- Sidebar Historial -->
    <div class="romita-sidebar" id="romitaSidebar">
        <div class="romita-sidebar-header">
            <h3>Historial</h3>
            <button class="btn-close-circular" onclick="toggleSidebar()"><i class="ph ph-x"></i></button>
        </div>
        <div class="romita-chat-list" id="chatList">
            <!-- Cargado por AJAX -->
        </div>
    </div>
    
    <!-- Main Chat Area -->
    <div class="romita-main" style="flex: 1; display: flex; flex-direction: column; position: relative;">
        <!-- Header -->
        <div class="romita-header">
            <div class="romita-brand">
                <button class="btn btn-outline btn-sm" onclick="toggleSidebar()" style="margin-right: 0.5rem;"><i class="ph ph-list"></i></button>
                <div class="romita-logo">
                    <i class="ph ph-sparkle"></i>
                </div>
                <div>
                    <h2 class="romita-greeting">Hola, <?php echo $first_name; ?> 👋</h2>
                    <p class="romita-subtitle">¿En qué puedo ayudarte hoy?</p>
                </div>
            </div>
            <div class="romita-actions" style="display:flex; gap:0.5rem;">
                <!-- Search input -->
                <div class="search-chat-container">
                    <input type="text" id="chatSearch" placeholder="Buscar en chat..." class="form-control form-control-sm" style="border-radius:20px;" onkeyup="searchChat(this.value)">
                </div>
                
                <select id="activePrept" class="form-select form-select-sm" style="border-radius:20px; width: auto;" title="Vincular a Marca (Prept)">
                    <option value="">-- Sin Marca (Prept) --</option>
                    <?php foreach($prepts as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <button class="btn btn-outline" onclick="newConversation()" style="display:flex; align-items:center; gap:0.5rem; backdrop-filter:blur(5px);" title="Nueva Conversación">
                    <i class="ph ph-broom"></i> <span class="hide-mobile">Nuevo Chat</span>
                </button>
                <?php if($is_admin): ?>
                    <button class="btn btn-outline" onclick="openPreptsModal()" style="display:flex; align-items:center; gap:0.5rem; backdrop-filter:blur(5px);">
                        <i class="ph ph-briefcase"></i> <span class="hide-mobile">Prepts</span>
                    </button>
                    <button class="btn btn-outline" onclick="openSkillsModal()" style="display:flex; align-items:center; gap:0.5rem; backdrop-filter:blur(5px);">
                        <i class="ph ph-gear"></i> <span class="hide-mobile">Skills</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

    <!-- Chat Area -->
    <div class="romita-chat-area" id="chatArea">
        <div class="romita-empty-state" id="emptyState">
            <div class="romita-empty-icon"><i class="ph ph-sparkle"></i></div>
            <h3>Soy Romita, tu asistente de IA</h3>
            <p>Selecciona una habilidad abajo o simplemente pregúntame lo que necesites.</p>
        </div>
    </div>

    <!-- Input Area -->
    <div class="romita-input-wrapper">
        <!-- Skills Suggestions -->
        <div class="romita-skills-pills" id="skillsContainer">
            <?php foreach($skills as $skill): ?>
                <?php 
                    // Determine icon based on name
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

        <!-- Chat Input -->
        <div class="romita-input-container">
            <textarea id="chatInput" class="romita-textarea" placeholder="Escribe tu mensaje aquí... (Shift + Enter para nueva línea)" rows="1" oninput="autoResize(this)" onkeydown="handleEnter(event)"></textarea>
            <button class="romita-send-btn" id="sendBtn" onclick="sendMessage()">
                <i class="ph ph-paper-plane-right"></i>
            </button>
        </div>
    </div>
    </div> <!-- End Main -->
</div> <!-- End Container -->

<?php if($is_admin): ?>
<!-- Modal de Gestión de Skills -->
<div class="modal-overlay" id="modal-skills">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ph ph-gear"></i> Gestión de Skills</h3>
            <button type="button" class="btn-close-circular" onclick="document.getElementById('modal-skills').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-skill" onsubmit="event.preventDefault(); saveSkill();">
                <input type="hidden" id="skillId" name="skill_id">
                <div class="form-group mb-3">
                    <label class="form-label">Nombre del Skill</label>
                    <input type="text" id="skillName" name="name" class="form-control" required placeholder="Ej: Traductor Técnico">
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Descripción (Breve)</label>
                    <input type="text" id="skill_desc" name="description" class="form-control" placeholder="Ej: Traduce textos IT al inglés">
                </div>
                <div class="mb-3">
                    <label>Rol Permitido</label>
                    <select id="skillRole" class="form-control">
                        <option value="all">Todos</option>
                        <option value="admin">Administradores</option>
                        <option value="ventas">Ventas</option>
                        <option value="marketing">Marketing</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Prompt Base (Instrucción de Sistema)</label>
                    <textarea id="skillPrompt" class="form-control" rows="4" placeholder="Ej: Eres un experto en... Puedes usar variables como [Tema]"></textarea>
                    <small class="text-muted">Tip: Usa corchetes para pedir variables al usuario, ej: <code>Escribe un post sobre [Tema] para [Red Social]</code>.</small>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <button type="button" class="btn btn-outline text-danger" id="btnDeleteSkill" onclick="deleteSkill()" style="display:none;"><i class="ph ph-trash"></i> Eliminar</button>
                    <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Guardar Skill</button>
                </div>
            </form>
            
            <hr style="margin: 1.5rem 0; border-color: var(--border-color);">
            
            <h4>Skills Existentes</h4>
            <div id="skillsListAdmin" style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem; max-height: 200px; overflow-y: auto;">
                <?php foreach($skills as $s): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-body); padding:0.5rem 1rem; border-radius:8px; border:1px solid var(--border-color);">
                        <div>
                            <strong><?php echo htmlspecialchars($s['name']); ?></strong>
                            <div style="font-size:0.8rem; color:var(--text-muted);"><?php echo htmlspecialchars($s['description']); ?></div>
                        </div>
                        <button class="btn btn-sm btn-outline" onclick="editSkill(<?php echo $s['id']; ?>, '<?php echo addslashes($s['name']); ?>', '<?php echo addslashes($s['prompt_base']); ?>', '<?php echo $s['allowed_role']; ?>')"><i class="ph ph-pencil"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Gestión de Prepts -->
<div class="modal-overlay" id="modal-prepts">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="ph ph-briefcase"></i> Gestión de Prepts (Marcas)</h3>
            <button type="button" class="btn-close-circular" onclick="document.getElementById('modal-prepts').classList.remove('active')"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-prept" onsubmit="event.preventDefault(); savePrept();">
                <input type="hidden" id="preptId" name="prept_id">
                <div class="form-group mb-3">
                    <label class="form-label">Nombre de la Marca</label>
                    <input type="text" id="preptName" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Tono de voz</label>
                    <input type="text" id="preptTone" class="form-control" placeholder="Ej: Profesional, cercano...">
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Audiencia</label>
                    <input type="text" id="preptAudience" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Reglas adicionales</label>
                    <textarea id="preptRules" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Guardar Prept</button>
            </form>
            <hr>
            <h4>Existentes</h4>
            <?php foreach($prepts as $p): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:0.5rem; border-bottom:1px solid #ddd;">
                    <?php echo htmlspecialchars($p['name']); ?>
                    <button class="btn btn-sm" onclick="editPrept(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', '<?php echo addslashes($p['tone']); ?>', '<?php echo addslashes($p['audience']); ?>', '<?php echo addslashes($p['rules']); ?>')">Editar</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    let activeSkill = null;
    let chatHistory = [];
    let currentChatId = null;
    
    // Auto-resize textarea
    function autoResize(el) {
        el.style.height = 'auto';
        el.style.height = (el.scrollHeight < 150 ? el.scrollHeight : 150) + 'px';
    }

    function toggleSidebar() {
        document.getElementById('romitaSidebar').classList.toggle('open');
    }
    
    function searchChat(query) {
        const text = query.toLowerCase();
        document.querySelectorAll('#chatArea .romita-message .message-bubble').forEach(b => {
            const msg = b.closest('.romita-message');
            if (text === '' || b.textContent.toLowerCase().includes(text)) {
                msg.style.display = 'flex';
            } else {
                msg.style.display = 'none';
            }
        });
    }

    // Cargar historial al iniciar
    document.addEventListener('DOMContentLoaded', () => {
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
                const list = document.getElementById('chatList');
                list.innerHTML = '';
                data.chats.forEach(chat => {
                    const div = document.createElement('div');
                    div.className = `chat-history-item ${chat.id == currentChatId ? 'active' : ''}`;
                    div.innerHTML = `<i class="ph ph-chat-text"></i> <span>${chat.title}</span>`;
                    div.onclick = () => loadChatMessages(chat.id);
                    list.appendChild(div);
                });
            }
        });
    }

    function loadChatMessages(chat_id) {
        currentChatId = chat_id;
        
        // Remove only messages, keep empty state element
        const chatArea = document.getElementById('chatArea');
        const messages = chatArea.querySelectorAll('.romita-message');
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
                if(window.innerWidth < 768) toggleSidebar(); // Close sidebar on mobile
                loadChatHistoryList(); // refresh active state
                
                data.messages.forEach(msg => {
                    chatHistory.push({role: msg.role, content: msg.content});
                    addMessageToUI(msg.role, msg.content, msg.id);
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

    function selectSkill(el) {
        if (el.classList.contains('active')) {
            el.classList.remove('active');
            activeSkill = null;
        } else {
            const rawPrompt = el.dataset.prompt;
            
            // Check for variables like [Topic]
            const regex = /\[([^\]]+)\]/g;
            let match;
            let variables = [];
            while ((match = regex.exec(rawPrompt)) !== null) {
                variables.push(match[1]);
            }
            
            let finalPrompt = rawPrompt;
            
            if (variables.length > 0) {
                // We have variables to ask the user
                for (let v of variables) {
                    let val = prompt(`Por favor ingresa un valor para: ${v}`);
                    if (val === null) {
                        // User cancelled
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
        }
    }

    function addMessageToUI(role, content) {
        const chatArea = document.getElementById('chatArea');
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
        
        const bubble = document.createElement('div');
        bubble.className = `message-bubble ${role === 'assistant' ? 'markdown-body' : ''}`;
        
        if(role === 'assistant') {
            bubble.innerHTML = marked.parse(content);
            makeTablesSortable(bubble);
        } else {
            const p = document.createElement('div');
            p.textContent = content;
            bubble.appendChild(p);
        }

        msgDiv.appendChild(role === 'user' ? bubble : avatar);
        msgDiv.appendChild(role === 'user' ? avatar : bubble);
        
        chatArea.appendChild(msgDiv);
        chatArea.scrollTop = chatArea.scrollHeight;
    }

    function showTypingIndicator() {
        const chatArea = document.getElementById('chatArea');
        const msgDiv = document.createElement('div');
        msgDiv.className = `romita-message assistant typing-msg`;
        msgDiv.id = 'typingIndicator';
        
        let aiIcon = 'ph-sparkle';
        if (activeSkill && activeSkill.icon) {
            aiIcon = activeSkill.icon;
        }
        
        msgDiv.innerHTML = `
            <div class="message-avatar ai-avatar"><i class="ph ${aiIcon}"></i></div>
            <div class="message-bubble">
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        `;
        chatArea.appendChild(msgDiv);
        chatArea.scrollTop = chatArea.scrollHeight;
    }

    function removeTypingIndicator() {
        const ind = document.getElementById('typingIndicator');
        if(ind) ind.remove();
    }

    function newConversation() {
        if(confirm('¿Estás seguro de iniciar una nueva conversación? Se limpiará la pantalla.')) {
            const chatArea = document.getElementById('chatArea');
            // Keep empty state, remove messages
            const messages = chatArea.querySelectorAll('.romita-message');
            messages.forEach(m => m.remove());
            
            const emptyState = document.getElementById('emptyState');
            if(emptyState) emptyState.style.display = 'flex';
            
            chatHistory = [];
            currentChatId = null;
            document.querySelectorAll('.skill-pill').forEach(p => p.classList.remove('active'));
            activeSkill = null;
            loadChatHistoryList();
        }
    }

    function makeTablesSortable(container) {
        const tables = container.querySelectorAll('table');
        tables.forEach(table => {
            const headers = table.querySelectorAll('th');
            headers.forEach((header, index) => {
                header.style.cursor = 'pointer';
                header.title = 'Clic para ordenar';
                header.addEventListener('click', () => {
                    const tbody = table.querySelector('tbody') || table;
                    const rows = Array.from(tbody.querySelectorAll('tr:nth-child(n+2)')); // Exclude header if in tbody
                    const isAsc = header.classList.contains('asc');
                    
                    headers.forEach(h => { h.classList.remove('asc', 'desc'); h.innerHTML = h.innerHTML.replace(' 🔼','').replace(' 🔽',''); });
                    header.classList.add(isAsc ? 'desc' : 'asc');
                    header.innerHTML += isAsc ? ' 🔽' : ' 🔼';
                    
                    rows.sort((a, b) => {
                        const aCol = a.children[index].textContent.trim();
                        const bCol = b.children[index].textContent.trim();
                        if(!isNaN(aCol) && !isNaN(bCol)) return isAsc ? bCol - aCol : aCol - bCol;
                        return isAsc ? bCol.localeCompare(aCol) : aCol.localeCompare(bCol);
                    });
                    
                    rows.forEach(row => tbody.appendChild(row));
                });
            });
        });
    }

    function sendMessage() {
        const input = document.getElementById('chatInput');
        const text = input.value.trim();
        const btn = document.getElementById('sendBtn');
        
        if (!text) return;
        
        // Disable input
        input.value = '';
        input.style.height = 'auto';
        input.disabled = true;
        btn.disabled = true;

        // Add User Message
        addMessageToUI('user', text);
        chatHistory.push({role: 'user', content: text});
        
        showTypingIndicator();

        // Prepare Payload
        const payload = new URLSearchParams();
        payload.append('action', 'chat');
        payload.append('message', text);
        if (activeSkill) {
            payload.append('skill_prompt', activeSkill.prompt);
        }

        const preptSelect = document.getElementById('activePrept');
        if (preptSelect && preptSelect.value) {
            payload.append('prept_id', preptSelect.value);
        }

        if (currentChatId) payload.append('chat_id', currentChatId);

        // Send to AJAX backend
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
                loadChatHistoryList(); // refresh to show new title
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
    }

    function saveSkill() {
        const id = document.getElementById('skillId').value;
        const name = document.getElementById('skillName').value.trim();
        const prompt = document.getElementById('skillPrompt').value.trim();
        const role = document.getElementById('skillRole').value;

        if(!name || !prompt) return alert('Llene todos los campos');

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
        const id = document.getElementById('skill_id').value;
        
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

        if(!name) return alert('El nombre es obligatorio');

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
