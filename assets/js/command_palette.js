/* assets/js/command_palette.js */
document.addEventListener('DOMContentLoaded', () => {
    // Inject the CSS styles dynamically
    const style = document.createElement('style');
    style.innerHTML = `
        #command-palette-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 99999;
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding-top: 10vh;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        #command-palette-overlay.active {
            display: flex;
            opacity: 1;
        }
        #command-palette-modal {
            background: var(--bg-surface, #ffffff);
            width: 90%;
            max-width: 650px;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--border-color, #e5e7eb);
            transform: scale(0.95);
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        #command-palette-overlay.active #command-palette-modal {
            transform: scale(1);
        }
        #command-palette-input-container {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color, #e5e7eb);
        }
        #command-palette-input-container i {
            font-size: 1.25rem;
            color: var(--text-muted, #6b7280);
            margin-right: 1rem;
        }
        #command-palette-input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 1.15rem;
            background: transparent;
            color: var(--text-color, #111827);
        }
        #command-palette-results {
            max-height: 400px;
            overflow-y: auto;
            padding: 0.5rem;
        }
        .cp-item {
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            border-radius: 8px;
            color: var(--text-color, #111827);
            text-decoration: none;
            transition: background 0.1s;
        }
        .cp-item:hover, .cp-item.selected {
            background: var(--primary-color-light, rgba(59, 130, 246, 0.1));
            color: var(--primary-color, #3b82f6);
        }
        .cp-item i {
            font-size: 1.2rem;
            color: var(--text-muted, #6b7280);
        }
        .cp-item:hover i, .cp-item.selected i {
            color: var(--primary-color, #3b82f6);
        }
        .cp-item-details {
            display: flex;
            flex-direction: column;
        }
        .cp-item-title {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .cp-item-subtitle {
            font-size: 0.75rem;
            color: var(--text-muted, #6b7280);
        }
        .cp-category {
            padding: 0.5rem 1rem 0.25rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted, #6b7280);
            margin-top: 0.5rem;
        }
        .cp-category:first-child {
            margin-top: 0;
        }
        .cp-badge {
            margin-left: auto;
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            color: #4b5563;
        }
        .cp-item:hover .cp-badge, .cp-item.selected .cp-badge {
            background: #bfdbfe;
            color: #1e3a8a;
        }
    `;
    document.head.appendChild(style);

    // Create HTML structure
    const overlay = document.createElement('div');
    overlay.id = 'command-palette-overlay';
    overlay.innerHTML = `
        <div id="command-palette-modal">
            <div id="command-palette-input-container">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" id="command-palette-input" placeholder="Buscar clientes, marcas, escribir 'meet'..." autocomplete="off">
            </div>
            <div id="command-palette-results">
                <!-- Results go here -->
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    const input = document.getElementById('command-palette-input');
    const resultsContainer = document.getElementById('command-palette-results');
    let searchTimeout;
    let selectedIndex = -1;
    let currentItems = [];

    // Toggle logic
    const togglePalette = (show = true) => {
        if (show) {
            overlay.classList.add('active');
            input.value = '';
            renderDefaultItems();
            setTimeout(() => input.focus(), 50);
        } else {
            overlay.classList.remove('active');
            input.blur();
        }
    };

    // Keyboard Shortcuts (Ctrl+K or Cmd+K)
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            togglePalette(!overlay.classList.contains('active'));
        }
        if (e.key === 'Escape' && overlay.classList.contains('active')) {
            togglePalette(false);
        }
    });

    // Close on overlay click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) togglePalette(false);
    });

    // Navigation (Arrow keys)
    input.addEventListener('keydown', (e) => {
        if (currentItems.length === 0) return;
        
        const items = resultsContainer.querySelectorAll('.cp-item');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = (selectedIndex + 1) % items.length;
            updateSelection(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = (selectedIndex - 1 + items.length) % items.length;
            updateSelection(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && selectedIndex < currentItems.length) {
                executeAction(currentItems[selectedIndex]);
            } else if (currentItems.length > 0) {
                executeAction(currentItems[0]); // execute first if none selected
            }
        }
    });

    const updateSelection = (items) => {
        items.forEach((item, index) => {
            if (index === selectedIndex) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    };

    // Input changes
    input.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim().toLowerCase();
        
        if (query === '') {
            renderDefaultItems();
            return;
        }

        if (query === 'meet' || query === 'reunion' || query === 'reunión') {
            renderMeetItem();
            return;
        }

        if (query.startsWith('?') || query.startsWith('/')) {
            searchTimeout = setTimeout(() => {
                fetchAIResults(query);
            }, 500); // slightly longer debounce for AI
            return;
        }

        searchTimeout = setTimeout(() => {
            fetchResults(query);
        }, 300); // debounce
    });

    const renderMeetItem = () => {
        const items = [{
            type: 'action',
            title: 'Programar Reunión',
            subtitle: 'Agendar en Google Calendar & Meet',
            icon: 'ph-calendar-plus',
            action: 'open_meet_modal'
        }];
        currentItems = items;
        renderHTML([{ category: 'Acciones Rápidas', items }]);
    };

    const renderDefaultItems = () => {
        const historyItems = [];
        try {
            const recent = JSON.parse(localStorage.getItem('cp_recent') || '[]');
            recent.forEach(item => historyItems.push(item));
        } catch(e){}

        const items = [
            { type: 'link', title: 'Dashboard', subtitle: 'Ir al inicio', icon: 'ph-squares-four', url: 'index.php?module=dashboard' },
            { type: 'link', title: 'Calendario', subtitle: 'Ver calendario global', icon: 'ph-calendar', url: 'index.php?module=calendar' },
            { type: 'link', title: 'Configuración', subtitle: 'Ajustes del sistema', icon: 'ph-gear', url: 'index.php?module=config' },
        ];
        
        currentItems = [];
        const groups = [];
        if (historyItems.length > 0) {
            groups.push({ category: 'Vistos Recientemente', items: historyItems });
            currentItems.push(...historyItems);
        }
        groups.push({ category: 'Módulos Principales', items });
        currentItems.push(...items);
        
        renderHTML(groups);
    };

    const fetchResults = (query) => {
        // Intercept special commands
        if (query.startsWith('>')) {
            renderSystemCommands(query.substring(1).trim());
            return;
        }
        
        // Deep Links match
        const deepLinks = [
            { type: 'link', title: 'Ajustes de Correo', subtitle: 'Configuración SMTP', icon: 'ph-envelope', url: 'index.php?module=config&tab=smtp' },
            { type: 'action', title: 'Mi Perfil', subtitle: 'Ajustes de usuario', icon: 'ph-user-circle', action: 'open_profile' },
            { type: 'link', title: 'Facturación', subtitle: 'Finanzas', icon: 'ph-currency-dollar', url: 'index.php?module=admin&action=finances' },
        ];
        const matchedDeepLinks = deepLinks.filter(l => l.title.toLowerCase().includes(query));

        fetch(`ajax/search_global.php?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                currentItems = [];
                const groups = [];
                
                if (matchedDeepLinks.length > 0) {
                    groups.push({ category: 'Atajos Rápidos', items: matchedDeepLinks });
                    currentItems.push(...matchedDeepLinks);
                }

                if (data.clientes && data.clientes.length > 0) {
                    const items = data.clientes.map(c => ({
                        type: 'link',
                        title: c.name,
                        subtitle: c.email || 'Cliente',
                        icon: 'ph-user',
                        url: `index.php?module=clients&action=view&id=${c.id}`
                    }));
                    groups.push({ category: 'Clientes', items });
                    currentItems.push(...items);
                }

                if (data.marcas && data.marcas.length > 0) {
                    const items = data.marcas.map(m => ({
                        type: 'link',
                        title: m.title,
                        subtitle: m.subtitle,
                        icon: 'ph-buildings',
                        url: m.url
                    }));
                    groups.push({ category: 'Marcas / Proyectos', items });
                    currentItems.push(...items);
                }

                if (data.reuniones && data.reuniones.length > 0) {
                    const items = data.reuniones.map(r => ({
                        type: 'link',
                        title: r.title,
                        subtitle: r.subtitle,
                        icon: 'ph-video-camera',
                        url: r.url
                    }));
                    groups.push({ category: 'Reuniones', items });
                    currentItems.push(...items);
                }

                if (data.cotizaciones && data.cotizaciones.length > 0) {
                    const items = data.cotizaciones.map(c => ({
                        type: 'link',
                        title: c.title,
                        subtitle: c.subtitle,
                        icon: 'ph-file-text',
                        url: c.url
                    }));
                    groups.push({ category: 'Cotizaciones', items });
                    currentItems.push(...items);
                }

                if (data.tareas && data.tareas.length > 0) {
                    const items = data.tareas.map(t => ({
                        type: 'link',
                        title: t.title,
                        subtitle: 'Tarea',
                        icon: 'ph-check-square',
                        url: `index.php?module=tasks&action=view&id=${t.id}`
                    }));
                    groups.push({ category: 'Tareas', items });
                    currentItems.push(...items);
                }

                if (data.servicios && data.servicios.length > 0) {
                    const items = data.servicios.map(s => ({
                        type: 'link',
                        title: s.title,
                        subtitle: s.subtitle,
                        icon: 'ph-package',
                        url: s.url
                    }));
                    groups.push({ category: 'Servicios', items });
                    currentItems.push(...items);
                }

                if (groups.length === 0) {
                    resultsContainer.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--text-muted);">No se encontraron resultados</div>';
                } else {
                    renderHTML(groups);
                }
            })
            .catch(err => console.error("Error buscando:", err));
    };

    const fetchAIResults = (query) => {
        resultsContainer.innerHTML = '<div style="padding:1.5rem; text-align:center; color:var(--text-muted);"><i class="ph ph-sparkle ph-spin" style="font-size:1.5rem; color:#8b5cf6;"></i> Consultando a Roma AI...</div>';
        
        const data = new FormData();
        data.append('query', query.substring(1).trim());

        fetch('ajax/gemini_chat.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    let htmlContent = res.response
                        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                        .replace(/\n/g, '<br>');
                        
                    resultsContainer.innerHTML = `
                        <div style="padding: 1.5rem; background: var(--bg-surface);">
                            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem; color: #8b5cf6; font-weight:600;">
                                <i class="ph ph-sparkle"></i> Roma AI
                            </div>
                            <div style="color: var(--text-main); font-size: 0.95rem; line-height: 1.5;">
                                ${htmlContent}
                            </div>
                        </div>
                    `;
                } else {
                    resultsContainer.innerHTML = `<div style="padding:1.5rem; text-align:center; color:#ef4444;">${res.error || 'Error al comunicarse con Gemini.'}</div>`;
                }
                currentItems = [];
                selectedIndex = -1;
            })
            .catch(err => {
                resultsContainer.innerHTML = '<div style="padding:1.5rem; text-align:center; color:#ef4444;">Error de conexión.</div>';
            });
    };

    const renderSystemCommands = (q) => {
        const commands = [
            { type: 'action', title: 'Nueva Cotización', subtitle: 'Crear cotización rápido', icon: 'ph-plus-circle', action: 'new_quote', q: 'nueva cotizacion' },
            { type: 'action', title: 'Nuevo Cliente', subtitle: 'Añadir cliente', icon: 'ph-user-plus', action: 'new_client', q: 'nuevo cliente' },
            { type: 'action', title: 'Dark Mode', subtitle: 'Alternar tema visual', icon: 'ph-moon', action: 'toggle_theme', q: 'dark mode' },
            { type: 'action', title: 'Contraer Menú', subtitle: 'Ocultar barra lateral', icon: 'ph-arrows-in-line-vertical', action: 'toggle_menu', q: 'contraer menu' },
        ];
        
        const filtered = commands.filter(c => c.q.includes(q.toLowerCase()));
        currentItems = filtered;
        if (filtered.length > 0) {
            renderHTML([{ category: 'Comandos del Sistema', items: filtered }]);
        } else {
            resultsContainer.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--text-muted);">Comando no reconocido</div>';
        }
    };

    const renderHTML = (groups) => {
        selectedIndex = -1;
        let html = '';
        groups.forEach(group => {
            html += `<div class="cp-category">${group.category}</div>`;
            group.items.forEach(item => {
                html += `
                    <a href="javascript:void(0)" class="cp-item" data-action="${item.type}" data-val="${item.url || item.action}">
                        <i class="ph ${item.icon}"></i>
                        <div class="cp-item-details">
                            <span class="cp-item-title">${item.title}</span>
                            <span class="cp-item-subtitle">${item.subtitle}</span>
                        </div>
                        <span class="cp-badge">Ir</span>
                    </a>
                `;
            });
        });
        resultsContainer.innerHTML = html;

        // Add click events
        resultsContainer.querySelectorAll('.cp-item').forEach((el, index) => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                executeAction(currentItems[index]);
            });
        });
    };

    // Meet Modal HTML
    const meetModal = document.createElement('div');
    meetModal.id = 'cp-meet-modal';
    meetModal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px);
        z-index: 100000; display: none; align-items: center; justify-content: center;
        opacity: 0; transition: opacity 0.3s ease;
    `;
    
    meetModal.innerHTML = `
        <div class="meet-modal-content" style="background: var(--bg-surface, #ffffff); width: 95%; max-width: 480px; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; transform: translateY(20px); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #ea4335 0%, #d93025 100%); padding: 1.5rem; position: relative;">
                <button type="button" id="cp-meet-close" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.2); border: none; width: 32px; height: 32px; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s;">
                    <i class="ph ph-x" style="font-size: 1.1rem;"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="background: rgba(255,255,255,0.2); width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; backdrop-filter: blur(4px);">
                        <i class="ph ph-video-camera" style="font-size: 1.8rem;"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; color: white; font-size: 1.4rem; font-weight: 700; letter-spacing: -0.02em;">Programar Meet</h3>
                        <p style="margin: 0.2rem 0 0 0; color: rgba(255,255,255,0.8); font-size: 0.85rem;">Crea una nueva videollamada</p>
                    </div>
                </div>
            </div>
            
            <!-- Body -->
            <form id="cp-meet-form" style="padding: 1.5rem;">
                <!-- Main details -->
                <div style="background: var(--bg-color, #f8fafc); padding: 1.25rem; border-radius: 16px; margin-bottom: 1.25rem; border: 1px solid var(--border-color, #e2e8f0);">
                    <div style="margin-bottom: 1.25rem;">
                        <label style="display:flex; align-items:center; gap: 0.4rem; margin-bottom: 0.4rem; font-weight:600; font-size:0.85rem; color: var(--text-main, #334155);">
                            <i class="ph ph-text-aa" style="color: #64748b;"></i> Motivo de la Reunión
                        </label>
                        <input type="text" id="cp-meet-motivo" required placeholder="Ej: Planificación Mensual" style="width:100%; padding:0.75rem 1rem; border:1px solid var(--border-color, #cbd5e1); border-radius:10px; background: var(--bg-surface, #ffffff); color: var(--text-main); font-size: 0.95rem; box-sizing:border-box; transition: border-color 0.2s; outline: none;">
                    </div>
                    
                    <div style="margin-bottom: 1.25rem;">
                        <label style="display:flex; align-items:center; gap: 0.4rem; margin-bottom: 0.4rem; font-weight:600; font-size:0.85rem; color: var(--text-main, #334155);">
                            <i class="ph ph-buildings" style="color: #64748b;"></i> Marca / Cliente
                        </label>
                        <select id="cp-meet-marca" required style="width:100%; padding:0.75rem 1rem; border:1px solid var(--border-color, #cbd5e1); border-radius:10px; background: var(--bg-surface, #ffffff); color: var(--text-main); font-size: 0.95rem; box-sizing:border-box; appearance: none; transition: border-color 0.2s; outline: none;">
                            <option value="">Cargando marcas...</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 0;">
                        <label style="display:flex; align-items:center; gap: 0.4rem; margin-bottom: 0.4rem; font-weight:600; font-size:0.85rem; color: var(--text-main, #334155);">
                            <i class="ph ph-calendar-blank" style="color: #64748b;"></i> Fecha y Hora
                        </label>
                        <input type="text" id="cp-meet-fecha" required placeholder="Seleccionar fecha y hora..." style="width:100%; padding:0.75rem 1rem; border:1px solid var(--border-color, #cbd5e1); border-radius:10px; background: var(--bg-surface, #ffffff); color: var(--text-main); font-size: 0.95rem; box-sizing:border-box; transition: border-color 0.2s; outline: none; cursor: pointer;">
                    </div>
                </div>

                <!-- Extras -->
                <div style="margin-bottom: 1.25rem;">
                    <label style="display:flex; align-items:center; gap: 0.4rem; margin-bottom: 0.4rem; font-weight:600; font-size:0.85rem; color: var(--text-main, #334155);">
                        <i class="ph ph-users" style="color: #64748b;"></i> Invitados Extras (Opcional)
                    </label>
                    <input type="text" id="cp-meet-guests" placeholder="correo1@ejemplo.com, correo2@ejemplo.com" style="width:100%; padding:0.75rem 1rem; border:1px solid var(--border-color, #cbd5e1); border-radius:10px; background: var(--bg-surface, #ffffff); color: var(--text-main); font-size: 0.9rem; box-sizing:border-box; outline: none;">
                </div>
                <div style="margin-bottom: 1.75rem;">
                    <label style="display:flex; align-items:center; gap: 0.4rem; margin-bottom: 0.4rem; font-weight:600; font-size:0.85rem; color: var(--text-main, #334155);">
                        <i class="ph ph-tag" style="color: #64748b;"></i> Etiquetas (Opcional)
                    </label>
                    <input type="text" id="cp-meet-tags" placeholder="estrategia, diseño, mensual" style="width:100%; padding:0.75rem 1rem; border:1px solid var(--border-color, #cbd5e1); border-radius:10px; background: var(--bg-surface, #ffffff); color: var(--text-main); font-size: 0.9rem; box-sizing:border-box; outline: none;">
                </div>

                <!-- Actions -->
                <div style="display:flex; gap:0.75rem;">
                    <button type="button" id="cp-meet-cancel" style="flex: 1; padding:0.85rem; border:1px solid var(--border-color, #e2e8f0); background: transparent; color: var(--text-main, #475569); border-radius:12px; cursor:pointer; font-weight:600; font-size: 0.95rem; transition: all 0.2s;">
                        Cancelar
                    </button>
                    <button type="submit" id="cp-meet-submit" style="flex: 2; padding:0.85rem; border:none; background: #ea4335; color:white; border-radius:12px; cursor:pointer; font-weight:600; font-size: 0.95rem; box-shadow: 0 4px 12px rgba(234, 67, 53, 0.25); transition: all 0.2s; display:flex; align-items:center; justify-content:center; gap: 0.5rem;">
                        <i class="ph ph-paper-plane-right"></i> Programar
                    </button>
                </div>
            </form>
        </div>
        <style>
            #cp-meet-modal input:focus, #cp-meet-modal select:focus {
                border-color: #ea4335 !important;
                box-shadow: 0 0 0 3px rgba(234, 67, 53, 0.15) !important;
            }
            #cp-meet-close:hover {
                background: rgba(255,255,255,0.3) !important;
            }
            #cp-meet-cancel:hover {
                background: var(--bg-color, #f1f5f9) !important;
            }
            #cp-meet-submit:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(234, 67, 53, 0.3) !important;
                background: #d93025 !important;
            }
            #cp-meet-submit:active {
                transform: translateY(0);
            }
            .flatpickr-calendar {
                z-index: 100005 !important;
            }
            .tagify {
                width: 100%;
                border: 1px solid var(--border-color, #cbd5e1);
                border-radius: 10px;
                background: var(--bg-surface, #ffffff);
                padding: 0.15rem 0.5rem;
                transition: border-color 0.2s;
                font-size: 0.9rem;
            }
            .tagify.tagify--focus {
                border-color: #ea4335 !important;
                box-shadow: 0 0 0 3px rgba(234, 67, 53, 0.15) !important;
            }
            .tagify__tag {
                margin: 0.2rem;
            }
        </style>
    `;
    document.body.appendChild(meetModal);

    let brandsLoaded = false;
    window.openMeetModal = () => {
        meetModal.style.display = 'flex';
        // Add a slight delay to trigger the animation
        setTimeout(() => {
            meetModal.style.opacity = '1';
            meetModal.querySelector('.meet-modal-content').style.transform = 'translateY(0)';
        }, 10);

        // set default time to next hour
        const now = new Date();
        now.setHours(now.getHours() + 1, 0, 0, 0);
        const tzOffset = (new Date()).getTimezoneOffset() * 60000; 
        const localISOTime = (new Date(now - tzOffset)).toISOString().slice(0, 16);
        
        const fechaInput = document.getElementById('cp-meet-fecha');
        fechaInput.value = localISOTime;

        if (window.flatpickr) {
            flatpickr(fechaInput, {
                enableTime: true,
                dateFormat: "Y-m-d\\TH:i",
                locale: "es",
                time_24hr: true
            });
        }
        
        if (window.Tagify) {
            if (!meetModal.guestsTagify) {
                meetModal.guestsTagify = new Tagify(document.getElementById('cp-meet-guests'), {
                    pattern: /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/,
                    delimiters: ",| ",
                    keepInvalidTags: false
                });
            }
            if (!meetModal.tagsTagify) {
                meetModal.tagsTagify = new Tagify(document.getElementById('cp-meet-tags'), {
                    delimiters: ",| ",
                });
            }
        }

        if (!brandsLoaded) {
            fetch('ajax/get_all_brands.php')
                .then(r => r.json())
                .then(data => {
                    const select = document.getElementById('cp-meet-marca');
                    select.innerHTML = '<option value="">Selecciona una marca...</option>';
                    data.marcas.forEach(m => {
                        select.innerHTML += '<option value="' + m.id + '">' + m.name + '</option>';
                    });
                    brandsLoaded = true;
                });
        }
    };

    const closeMeetModal = () => {
        meetModal.style.opacity = '0';
        meetModal.querySelector('.meet-modal-content').style.transform = 'translateY(20px)';
        setTimeout(() => {
            meetModal.style.display = 'none';
            document.getElementById('cp-meet-form').reset();
            if (meetModal.guestsTagify) meetModal.guestsTagify.removeAllTags();
            if (meetModal.tagsTagify) meetModal.tagsTagify.removeAllTags();
        }, 300);
    };

    document.getElementById('cp-meet-cancel').addEventListener('click', closeMeetModal);
    document.getElementById('cp-meet-close').addEventListener('click', closeMeetModal);

    document.getElementById('cp-meet-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const btn = document.getElementById('cp-meet-submit');
        btn.disabled = true;
        btn.innerHTML = 'Programando...';

        const data = new FormData();
        data.append('motivo', document.getElementById('cp-meet-motivo').value);
        data.append('brand_id', document.getElementById('cp-meet-marca').value);
        const select = document.getElementById('cp-meet-marca');
        data.append('brand_name', select.options[select.selectedIndex].text);
        data.append('fecha', document.getElementById('cp-meet-fecha').value);
        
        let guestsVal = document.getElementById('cp-meet-guests').value;
        try { guestsVal = JSON.parse(guestsVal).map(t => t.value).join(','); } catch(e){}
        data.append('guests', guestsVal);

        let tagsVal = document.getElementById('cp-meet-tags').value;
        try { tagsVal = JSON.parse(tagsVal).map(t => t.value).join(','); } catch(e){}
        data.append('tags', tagsVal);

        fetch('ajax/schedule_meet.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if(window.showToast) window.showToast('Reunión programada exitosamente.', 'success');
                    meetModal.style.display = 'none';
                    document.getElementById('cp-meet-form').reset();
                    // Reload after a short delay so user can see the toast
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    if(window.showToast) window.showToast('Error: ' + (res.error || 'Desconocido'), 'error');
                }
            })
            .catch(err => {
                if(window.showToast) window.showToast('Error de conexión', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = 'Programar Meet';
            });
    });

    // Replace the old execute action logic
    const executeAction = (item) => {
        togglePalette(false);
        
        // Save to recent history
        if (item.type === 'link') {
            try {
                let recent = JSON.parse(localStorage.getItem('cp_recent') || '[]');
                recent = recent.filter(r => r.url !== item.url);
                recent.unshift(item);
                if (recent.length > 4) recent.pop();
                localStorage.setItem('cp_recent', JSON.stringify(recent));
            } catch(e){}
        }

        if (item.type === 'link') {
            window.location.href = item.url;
        } else if (item.type === 'action') {
            if (item.action === 'open_meet_modal') {
                window.openMeetModal();
            } else if (item.action === 'toggle_theme') {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                document.documentElement.setAttribute('data-theme', isDark ? 'light' : 'dark');
                localStorage.setItem('theme', isDark ? 'light' : 'dark');
            } else if (item.action === 'toggle_menu') {
                document.documentElement.classList.toggle('sidebar-is-collapsed');
                localStorage.setItem('sidebar_collapsed', document.documentElement.classList.contains('sidebar-is-collapsed'));
            } else if (item.action === 'open_profile') {
                if(typeof openProfileModal === 'function') openProfileModal();
                else window.location.href = 'index.php?module=profile';
            } else if (item.action === 'new_quote') {
                window.location.href = 'index.php?module=quotes&action=form';
            } else if (item.action === 'new_client') {
                window.location.href = 'index.php?module=clients#new';
            }
        }
    };
});
