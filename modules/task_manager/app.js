// modules/task_manager/app.js — Centro Integral de Tareas, Objetivos Diarios y Conexiones
const TM = {
    tasks: [],
    projects: [],
    projectMonths: [],
    brandProjects: [],
    projectServices: [],
    currentSyncEntity: null,
    currentView: 'kanban', // 'kanban', 'daily', 'weekly', 'gantt'
    filterUser: 'me',
    filterArea: 'all',
    filterFrequency: 'all',
    filterProject: 'all',
    currentDailyDate: new Date().toISOString().substring(0, 10),
    currentDailyUser: window.TM_USER_ID || 1,
    ganttScale: 'day', // 'day', 'week'
    ganttGroupBy: 'project', // 'project', 'area', 'user', 'status', 'none'
    ganttMobileMode: 'split', // 'split', 'timeline', 'list'
    isGanttFullscreen: false,
    ganttAnchorDate: new Date(),
    dpStartDate: null,
    dpDueDate: null,
    dpDailyDate: null,
    dpObjectiveDate: null,
    dailyObjectivesData: null,
    currentAssignedUserIds: [],
    currentTags: [],
    availableTags: ['Diseño', 'Revisión', 'Web', 'Video', 'Urgente', 'Contenido', 'Campaña', 'Copywriting'],
    quillDesc: null,
    tagifyUsers: null,
    draggedElement: null,

    init: function() {
        this.currentDailyUser = window.TM_USER_ID || 1;
        this.initEditors();
        this.initDatepickers();
        this.loadContextData();
        this.loadTasks();
        setInterval(() => this.updateAllTimers(), 1000);
    },

    initEditors: function() {
        try {
            if (typeof Quill !== 'undefined' && document.getElementById('tm-desc-editor')) {
                this.quillDesc = new Quill('#tm-desc-editor', {
                    theme: 'snow',
                    placeholder: 'Añade una descripción detallada, criterios de aceptación o notas...',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            ['link', 'clean']
                        ]
                    }
                });
            }
        } catch(e) { console.warn('Quill editor error:', e); }
    },

    initDatepickers: function() {
        if (typeof AirDatepicker !== 'undefined') {
            const dpConfig = {
                timepicker: true,
                dateFormat: 'yyyy-MM-dd',
                timeFormat: 'HH:mm',
                autoClose: true,
                locale: {
                    days: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                    daysShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
                    daysMin: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
                    months: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                    monthsShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                    today: 'Hoy',
                    clear: 'Limpiar',
                    dateFormat: 'yyyy-MM-dd',
                    timeFormat: 'HH:mm',
                    firstDay: 1
                }
            };
            try {
                if (document.getElementById('tm-start-date')) {
                    this.dpStartDate = new AirDatepicker('#tm-start-date', dpConfig);
                }
                if (document.getElementById('tm-due-date')) {
                    this.dpDueDate = new AirDatepicker('#tm-due-date', dpConfig);
                }
                if (document.getElementById('tm-daily-datepicker')) {
                    this.dpDailyDate = new AirDatepicker('#tm-daily-datepicker', {
                        ...dpConfig,
                        timepicker: false,
                        onSelect: ({formattedDate}) => {
                            if (formattedDate) {
                                TM.setDailyDate(formattedDate);
                            }
                        }
                    });
                }
                if (document.getElementById('tm-objective-date-display')) {
                    this.dpObjectiveDate = new AirDatepicker('#tm-objective-date-display', {
                        ...dpConfig,
                        timepicker: false,
                        onSelect: ({formattedDate}) => {
                            if (formattedDate) {
                                TM.setObjectiveDate(formattedDate);
                            }
                        }
                    });
                }
            } catch(e) { console.warn('AirDatepicker error:', e); }
        }
    },

    escapeHtml: function(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    // ══════════════════════════════════════════════════════
    // GESTIÓN DE ASIGNADOS Y ASIGNADOS AL PROYECTO
    // ══════════════════════════════════════════════════════
    getUserById: function(uid) {
        uid = parseInt(uid, 10);
        const list = window.TM_USERS || [];
        const found = list.find(u => parseInt(u.id, 10) === uid);
        if (found) return found;
        return { id: uid, name: 'Usuario #' + uid, initial: 'U', avatar: '' };
    },

    renderAssignedUsers: function() {
        const container = document.getElementById('tm-assigned-chips');
        const countBadge = document.getElementById('tm-assigned-count');
        const hiddenInput = document.getElementById('tm-assigned-users');
        const select = document.getElementById('tm-user-select-add');

        if (!container) return;
        container.innerHTML = '';

        if (countBadge) countBadge.textContent = this.currentAssignedUserIds.length;
        if (hiddenInput) hiddenInput.value = JSON.stringify(this.currentAssignedUserIds);

        if (this.currentAssignedUserIds.length === 0) {
            container.innerHTML = '<span class="tm-assigned-empty"><i class="ph ph-user-dashed"></i> Sin miembros asignados</span>';
        } else {
            this.currentAssignedUserIds.forEach(uid => {
                const u = this.getUserById(uid);
                const chip = document.createElement('div');
                chip.className = 'tm-user-chip';
                chip.dataset.uid = u.id;
                chip.innerHTML = `
                    ${u.avatar ? `<img src="${u.avatar}" class="tm-user-chip-avatar" alt="${this.escapeHtml(u.name)}">` : `<span class="tm-user-chip-initial">${u.initial || u.name.charAt(0).toUpperCase()}</span>`}
                    <span class="tm-user-chip-name">${this.escapeHtml(u.name)}</span>
                    <button type="button" class="tm-user-chip-remove" onclick="TM.unassignUser(${u.id})" title="Desasignar a ${this.escapeHtml(u.name)}">
                        <i class="ph ph-x"></i>
                    </button>
                `;
                container.appendChild(chip);
            });
        }

        // Update select dropdown: mark already assigned
        if (select) {
            Array.from(select.options).forEach((opt, idx) => {
                if (idx === 0) return;
                const optUid = parseInt(opt.value, 10);
                const isAssigned = this.currentAssignedUserIds.includes(optUid);
                opt.disabled = isAssigned;
                const uObj = this.getUserById(optUid);
                opt.textContent = isAssigned ? `✓ ${uObj.name} (Asignado)` : uObj.name;
            });
            select.value = '';
        }

        // Refresh project members bar states
        this.updateProjectMembersBar();
    },

    assignUser: function(uid) {
        uid = parseInt(uid, 10);
        if (!uid || isNaN(uid)) return;
        if (!this.currentAssignedUserIds.includes(uid)) {
            this.currentAssignedUserIds.push(uid);
            this.renderAssignedUsers();
            this.updateProjectMembersBar();
        }
    },

    unassignUser: function(uid) {
        uid = parseInt(uid, 10);
        this.currentAssignedUserIds = this.currentAssignedUserIds.filter(id => id !== uid);
        this.renderAssignedUsers();
        this.updateProjectMembersBar();
    },

    onUserSelectChange: function(val) {
        if (!val) return;
        this.assignUser(val);
    },

    getProjectTeamMembers: function() {
        const projSelect = document.getElementById('tm-project-id');
        const brandProjSelect = document.getElementById('tm-brand-project-id');
        const areaSelect = document.getElementById('tm-area');
        const area = areaSelect ? areaSelect.value : 'general';

        if (area === 'desarrollo_marca' && brandProjSelect && brandProjSelect.value) {
            const bp = this.brandProjects.find(b => String(b.id) === String(brandProjSelect.value));
            return (bp && Array.isArray(bp.team_members)) ? bp.team_members : [];
        }

        if (projSelect && projSelect.value) {
            const p = this.projects.find(x => String(x.id) === String(projSelect.value));
            return (p && Array.isArray(p.team_members)) ? p.team_members : [];
        }

        return [];
    },

    updateProjectMembersBar: function() {
        const bar = document.getElementById('tm-project-members-bar');
        const chipsWrap = document.getElementById('tm-project-members-chips');
        if (!bar || !chipsWrap) return;

        const pMembers = this.getProjectTeamMembers();
        if (!pMembers || pMembers.length === 0) {
            bar.style.display = 'none';
            chipsWrap.innerHTML = '';
            return;
        }

        bar.style.display = 'flex';
        chipsWrap.innerHTML = '';

        pMembers.forEach(uid => {
            const u = this.getUserById(uid);
            const isAssigned = this.currentAssignedUserIds.includes(parseInt(u.id, 10));
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = `tm-pm-chip ${isAssigned ? 'is-assigned' : ''}`;
            chip.title = isAssigned ? `Quitar a ${u.name}` : `Asignar a ${u.name}`;
            chip.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleProjectMemberAssign(u.id);
            };
            chip.innerHTML = `
                ${u.avatar ? `<img src="${u.avatar}" class="tm-pm-avatar" alt="${this.escapeHtml(u.name)}">` : `<span class="tm-pm-initial">${u.initial || u.name.charAt(0).toUpperCase()}</span>`}
                <span class="tm-pm-name">${this.escapeHtml(u.name)}</span>
                <i class="ph-bold ${isAssigned ? 'ph-check' : 'ph-plus'} tm-pm-icon"></i>
            `;
            chipsWrap.appendChild(chip);
        });
    },

    toggleProjectMemberAssign: function(uid) {
        uid = parseInt(uid, 10);
        if (this.currentAssignedUserIds.includes(uid)) {
            this.unassignUser(uid);
        } else {
            this.assignUser(uid);
        }
    },

    assignAllProjectMembers: function() {
        const pMembers = this.getProjectTeamMembers();
        if (!pMembers || pMembers.length === 0) return;
        pMembers.forEach(uid => {
            const id = parseInt(uid, 10);
            if (id && !this.currentAssignedUserIds.includes(id)) {
                this.currentAssignedUserIds.push(id);
            }
        });
        this.renderAssignedUsers();
        this.updateProjectMembersBar();
    },

    // ══════════════════════════════════════════════════════
    // GESTIÓN DE ETIQUETAS (CREAR, EDITAR, BORRAR)
    // ══════════════════════════════════════════════════════
    renderTags: function() {
        const wrap = document.getElementById('tm-tags-chips-wrap');
        const countBadge = document.getElementById('tm-tags-count');
        const hiddenInput = document.getElementById('tm-tags');
        const sugWrap = document.getElementById('tm-tags-sug-pills');

        if (!wrap) return;
        wrap.innerHTML = '';

        if (countBadge) countBadge.textContent = this.currentTags.length;
        if (hiddenInput) hiddenInput.value = JSON.stringify(this.currentTags);

        if (this.currentTags.length === 0) {
            wrap.innerHTML = '<span class="tm-tags-empty"><i class="ph ph-tag-chevron"></i> Sin etiquetas asignadas</span>';
        } else {
            this.currentTags.forEach((tag, index) => {
                const chip = document.createElement('div');
                chip.className = 'tm-tag-chip';
                chip.dataset.tag = tag;
                chip.dataset.index = index;

                const textSpan = document.createElement('span');
                textSpan.className = 'tm-tag-chip-text';
                textSpan.textContent = tag;
                textSpan.title = 'Clic o doble clic para editar';
                textSpan.onclick = () => this.startEditTag(index, chip);

                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'tm-tag-edit-btn';
                editBtn.title = 'Editar nombre';
                editBtn.innerHTML = '<i class="ph ph-pencil-simple"></i>';
                editBtn.onclick = () => this.startEditTag(index, chip);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'tm-tag-chip-remove';
                removeBtn.title = 'Eliminar etiqueta';
                removeBtn.innerHTML = '<i class="ph ph-x"></i>';
                removeBtn.onclick = (e) => {
                    e.stopPropagation();
                    this.removeTag(tag);
                };

                chip.appendChild(textSpan);
                chip.appendChild(editBtn);
                chip.appendChild(removeBtn);
                wrap.appendChild(chip);
            });
        }

        // Render suggested tags pills
        if (sugWrap) {
            sugWrap.innerHTML = '';
            const allSugs = Array.from(new Set([...this.availableTags]));
            allSugs.slice(0, 10).forEach(st => {
                const isActive = this.currentTags.some(t => t.toLowerCase() === st.toLowerCase());
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `tm-tag-sug-pill ${isActive ? 'active' : ''}`;
                btn.title = isActive ? `Quitar etiqueta ${st}` : `Agregar etiqueta ${st}`;
                btn.onclick = () => this.toggleSuggestedTag(st);
                btn.innerHTML = `<i class="ph-bold ${isActive ? 'ph-check' : 'ph-plus'}"></i> ${this.escapeHtml(st)}`;
                sugWrap.appendChild(btn);
            });
        }
    },

    addTag: function(tag) {
        tag = (tag || '').trim();
        if (!tag) return;
        const exists = this.currentTags.some(t => t.toLowerCase() === tag.toLowerCase());
        if (!exists) {
            this.currentTags.push(tag);
            if (!this.availableTags.some(t => t.toLowerCase() === tag.toLowerCase())) {
                this.availableTags.push(tag);
            }
            this.renderTags();
        }
    },

    removeTag: function(tag) {
        this.currentTags = this.currentTags.filter(t => t.toLowerCase() !== tag.toLowerCase());
        this.renderTags();
    },

    addTagFromInput: function() {
        const input = document.getElementById('tm-tag-new-input');
        if (!input) return;
        const val = input.value.trim();
        if (!val) return;

        const parts = val.split(',').map(s => s.trim()).filter(Boolean);
        parts.forEach(p => this.addTag(p));
        input.value = '';
    },

    onTagInputKeydown: function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            this.addTagFromInput();
        } else if (e.key === 'Backspace' && e.target.value === '' && this.currentTags.length > 0) {
            this.removeTag(this.currentTags[this.currentTags.length - 1]);
        }
    },

    startEditTag: function(index, chipEl) {
        const oldTag = this.currentTags[index];
        if (!oldTag || !chipEl) return;

        chipEl.classList.add('is-editing');
        chipEl.innerHTML = `
            <input type="text" class="tm-tag-inline-input" value="${this.escapeHtml(oldTag)}">
            <button type="button" class="tm-tag-inline-save" title="Guardar cambios"><i class="ph-bold ph-check"></i></button>
            <button type="button" class="tm-tag-inline-cancel" title="Cancelar"><i class="ph-bold ph-x"></i></button>
        `;

        const input = chipEl.querySelector('.tm-tag-inline-input');
        const saveBtn = chipEl.querySelector('.tm-tag-inline-save');
        const cancelBtn = chipEl.querySelector('.tm-tag-inline-cancel');

        if (input) {
            input.focus();
            input.select();

            const finishEdit = (save) => {
                if (save) {
                    const newTag = input.value.trim();
                    if (!newTag) {
                        this.removeTag(oldTag);
                        return;
                    }
                    if (newTag.toLowerCase() !== oldTag.toLowerCase()) {
                        this.currentTags[index] = newTag;
                        if (!this.availableTags.some(t => t.toLowerCase() === newTag.toLowerCase())) {
                            this.availableTags.push(newTag);
                        }
                    }
                }
                this.renderTags();
            };

            input.onkeydown = (ev) => {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    finishEdit(true);
                } else if (ev.key === 'Escape') {
                    ev.preventDefault();
                    finishEdit(false);
                }
            };

            if (saveBtn) saveBtn.onclick = () => finishEdit(true);
            if (cancelBtn) cancelBtn.onclick = () => finishEdit(false);
        }
    },

    toggleSuggestedTag: function(tag) {
        const idx = this.currentTags.findIndex(t => t.toLowerCase() === tag.toLowerCase());
        if (idx >= 0) {
            this.currentTags.splice(idx, 1);
        } else {
            this.currentTags.push(tag);
        }
        this.renderTags();
    },

    // ══════════════════════════════════════════════════════
    // Context Data: Projects, Months, Brand Projects & Services
    // ══════════════════════════════════════════════════════
    loadContextData: function() {
        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action_type=get_projects_and_months'
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                this.projects = data.projects || [];
                this.projectMonths = data.project_months || [];
                this.brandProjects = data.brand_projects || [];
                this.projectServices = data.project_services || [];
                if (data.available_tags && Array.isArray(data.available_tags)) {
                    data.available_tags.forEach(t => {
                        if (t && !this.availableTags.some(ex => ex.toLowerCase() === t.toLowerCase())) {
                            this.availableTags.push(t);
                        }
                    });
                }
                if (data.users && Array.isArray(data.users) && (!window.TM_USERS || window.TM_USERS.length === 0)) {
                    window.TM_USERS = data.users;
                }
                this.populateProjectSelects();
                this.renderTags();
            }
        })
        .catch(err => console.error('Error loading context data:', err));
    },

    populateProjectSelects: function() {
        // Toolbar filter project
        const filterProjSelect = document.getElementById('tm-filter-project');
        if (filterProjSelect) {
            filterProjSelect.innerHTML = '<option value="all">Todos los Proyectos</option>';
            this.projects.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                filterProjSelect.appendChild(opt);
            });
        }

        // Modal project select
        const modalProjSelect = document.getElementById('tm-project-id');
        if (modalProjSelect) {
            modalProjSelect.innerHTML = '<option value="">-- Sin Vincular / General --</option>';
            this.projects.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                modalProjSelect.appendChild(opt);
            });
        }

        // Brand projects select
        const bpSelect = document.getElementById('tm-brand-project-id');
        if (bpSelect) {
            bpSelect.innerHTML = '<option value="">-- Seleccionar Identidad / Marca --</option>';
            this.brandProjects.forEach(bp => {
                const opt = document.createElement('option');
                opt.value = bp.id;
                opt.textContent = bp.title + (bp.client_name ? ` (${bp.client_name})` : '');
                bpSelect.appendChild(opt);
            });
        }

        // Project services select (Web & Audiovisual)
        const psSelect = document.getElementById('tm-project-service-id');
        if (psSelect) {
            psSelect.innerHTML = '<option value="">-- Seleccionar Servicio / Entregable --</option>';
            this.projectServices.forEach(ps => {
                const opt = document.createElement('option');
                opt.value = ps.id;
                opt.dataset.area = ps.area;
                opt.textContent = `${ps.project_name} · ${ps.title} (${ps.status})`;
                psSelect.appendChild(opt);
            });
        }
    },

    onProjectChange: function(projectId, selectedMonthId = null) {
        const monthSelect = document.getElementById('tm-project-month-id');
        if (!monthSelect) return;

        monthSelect.innerHTML = '<option value="">-- Seleccionar Mes de Calendario --</option>';
        if (!projectId) {
            this.refreshSyncPanelFromSelections();
            return;
        }

        // Filter active months for this project
        const matched = this.projectMonths.filter(m => String(m.project_id) === String(projectId));
        matched.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.label;
            if (selectedMonthId && String(m.id) === String(selectedMonthId)) {
                opt.selected = true;
            }
            monthSelect.appendChild(opt);
        });

        this.refreshSyncPanelFromSelections();

        // Auto-assign project members if none assigned yet
        const pMembers = this.getProjectTeamMembers();
        if (pMembers && pMembers.length > 0 && this.currentAssignedUserIds.length === 0) {
            this.currentAssignedUserIds = [...pMembers.map(id => parseInt(id, 10))];
            this.renderAssignedUsers();
        }

        this.updateProjectMembersBar();
    },

    onProjectMonthChange: function(monthId) {
        this.refreshSyncPanelFromSelections();
    },

    onBrandProjectChange: function(bpId) {
        this.refreshSyncPanelFromSelections();

        // Auto-assign brand project members if none assigned yet
        const pMembers = this.getProjectTeamMembers();
        if (pMembers && pMembers.length > 0 && this.currentAssignedUserIds.length === 0) {
            this.currentAssignedUserIds = [...pMembers.map(id => parseInt(id, 10))];
            this.renderAssignedUsers();
        }

        this.updateProjectMembersBar();
    },

    onProjectServiceChange: function(psId) {
        this.refreshSyncPanelFromSelections();
    },

    onAreaChange: function(area) {
        const brandRow = document.getElementById('row-brand-project');
        const serviceRow = document.getElementById('row-project-service');
        const accentBar = document.getElementById('tm-modal-accent');
        
        if (brandRow) {
            brandRow.style.display = (area === 'desarrollo_marca') ? 'flex' : 'none';
        }

        if (serviceRow) {
            const isServiceArea = (area === 'desarrollo_web' || area === 'audiovisual');
            serviceRow.style.display = isServiceArea ? 'flex' : 'none';
            if (isServiceArea) {
                // Filter service options by area
                const psSelect = document.getElementById('tm-project-service-id');
                if (psSelect) {
                    Array.from(psSelect.options).forEach((opt, idx) => {
                        if (idx === 0) return;
                        const optArea = opt.dataset.area;
                        opt.style.display = (!optArea || optArea === 'general' || optArea === area) ? '' : 'none';
                    });
                }
            }
        }

        this.updateProjectMembersBar();

        if (accentBar) {
            if (area === 'desarrollo_marca') {
                accentBar.style.background = 'linear-gradient(90deg, #ec4899, #8b5cf6)';
            } else if (area === 'desarrollo_web') {
                accentBar.style.background = 'linear-gradient(90deg, #0ea5e9, #2563eb)';
            } else if (area === 'audiovisual') {
                accentBar.style.background = 'linear-gradient(90deg, #f59e0b, #ef4444)';
            } else {
                accentBar.style.background = 'linear-gradient(90deg, #10b981, #3b82f6)';
            }
        }

        this.refreshSyncPanelFromSelections();
    },

    refreshSyncPanelFromSelections: function() {
        const pmId = document.getElementById('tm-project-month-id')?.value;
        const bpId = document.getElementById('tm-brand-project-id')?.value;
        const psId = document.getElementById('tm-project-service-id')?.value;
        const area = document.getElementById('tm-area')?.value;

        if (area === 'desarrollo_marca' && bpId) {
            this.updateSyncPanel('brand_project', bpId);
        } else if ((area === 'desarrollo_web' || area === 'audiovisual') && psId) {
            this.updateSyncPanel('project_service', psId);
        } else if (pmId) {
            this.updateSyncPanel('calendar_month', pmId);
        } else if (bpId) {
            this.updateSyncPanel('brand_project', bpId);
        } else if (psId) {
            this.updateSyncPanel('project_service', psId);
        } else {
            const panel = document.getElementById('tm-sync-panel');
            if (panel) panel.style.display = 'none';
            this.currentSyncEntity = null;
        }
    },

    updateSyncPanel: function(type, id) {
        const panel = document.getElementById('tm-sync-panel');
        if (!panel || !id) {
            if (panel) panel.style.display = 'none';
            this.currentSyncEntity = null;
            return;
        }

        let entity = null;
        let typeBadge = '';
        let phaseOptions = [];
        let currentPhase = '';

        if (type === 'calendar_month') {
            entity = this.projectMonths.find(m => String(m.id) === String(id));
            if (!entity) return;
            typeBadge = `Mes de Calendario · ${entity.raw_label || entity.label}`;
            phaseOptions = [
                { val: 'En Borrador', label: 'En Borrador (Parrilla)' },
                { val: 'En Revisión', label: 'En Revisión (Interna/Cliente)' },
                { val: 'Aprobado', label: 'Aprobado (Listo para pautar)' },
                { val: 'Publicado', label: 'Publicado (En redes)' }
            ];
            currentPhase = entity.content_phase || 'En Borrador';
        } else if (type === 'brand_project') {
            entity = this.brandProjects.find(b => String(b.id) === String(id));
            if (!entity) return;
            typeBadge = `Desarrollo de Marca · ${entity.title}`;
            phaseOptions = [
                { val: 'Pending', label: 'Pendiente' },
                { val: 'Active', label: 'En Proceso / Activo' },
                { val: 'Completed', label: 'Terminado / Entregado' }
            ];
            currentPhase = entity.status || 'Active';
        } else if (type === 'project_service') {
            entity = this.projectServices.find(s => String(s.id) === String(id));
            if (!entity) return;
            typeBadge = `${entity.category_name || 'Servicio'} · ${entity.title}`;
            phaseOptions = [
                { val: 'pending', label: 'Pendiente' },
                { val: 'in_progress', label: 'En Desarrollo / Producción' },
                { val: 'review', label: 'En Revisión' },
                { val: 'completed', label: 'Completado / Finalizado' }
            ];
            currentPhase = entity.status || 'pending';
        }

        if (!entity) {
            panel.style.display = 'none';
            this.currentSyncEntity = null;
            return;
        }

        this.currentSyncEntity = { type, id, ...entity, currentPhase };

        // Update UI elements
        panel.style.display = 'block';
        const badgeEl = document.getElementById('tm-sync-entity-type-badge');
        if (badgeEl) badgeEl.textContent = typeBadge;

        const deadlineEl = document.getElementById('tm-sync-parent-deadline');
        if (deadlineEl) {
            deadlineEl.textContent = entity.due_date ? entity.due_date : 'Sin fecha límite';
        }

        const timerEl = document.getElementById('tm-sync-parent-timer');
        if (timerEl) {
            timerEl.setAttribute('data-due', entity.due_date || '');
            timerEl.setAttribute('data-start', entity.start_date || '');
            timerEl.setAttribute('data-status', entity.status || '');
        }

        const phaseSelect = document.getElementById('tm-sync-phase-select');
        if (phaseSelect) {
            phaseSelect.innerHTML = '';
            phaseOptions.forEach(opt => {
                const o = document.createElement('option');
                o.value = opt.val;
                o.textContent = opt.label;
                if (opt.val === currentPhase) o.selected = true;
                phaseSelect.appendChild(o);
            });
        }

        this.checkDeadlineDrift();
        this.updateAllTimers();
    },

    syncWithProjectDeadline: function() {
        if (!this.currentSyncEntity || !this.currentSyncEntity.due_date) {
            alert("El proyecto seleccionado no tiene una fecha límite configurada.");
            return;
        }

        const parentDue = this.currentSyncEntity.due_date.substring(0, 10);
        const dueInput = document.getElementById('tm-due-date');
        if (dueInput) {
            dueInput.value = parentDue + ' 18:00';
            this.checkDeadlineDrift();
            this.updateAllTimers();
        }

        // Show brief visual feedback on the button
        const btn = document.querySelector('.tm-btn-sync-action');
        if (btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = `<i class="ph-bold ph-check"></i> ¡Sincronizado!`;
            btn.style.background = '#10b981';
            btn.style.color = '#ffffff';
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.style.background = '';
                btn.style.color = '';
            }, 1800);
        }
    },

    checkDeadlineDrift: function() {
        const driftAlert = document.getElementById('tm-sync-drift-alert');
        const dueInput = document.getElementById('tm-due-date');
        const taskTimer = document.getElementById('tm-modal-task-timer');

        if (dueInput && dueInput.value && taskTimer) {
            taskTimer.style.display = 'inline-flex';
            taskTimer.setAttribute('data-due', dueInput.value);
            const statusVal = document.getElementById('tm-status')?.value || 'new';
            taskTimer.setAttribute('data-status', statusVal);
        } else if (taskTimer) {
            taskTimer.style.display = 'none';
        }

        if (!this.currentSyncEntity || !this.currentSyncEntity.due_date || !dueInput || !dueInput.value) {
            if (driftAlert) driftAlert.style.display = 'none';
            return;
        }

        const taskDueDate = new Date(dueInput.value.replace(' ', 'T'));
        const parentDueDate = new Date(this.currentSyncEntity.due_date.substring(0, 10) + 'T23:59:59');

        if (driftAlert) {
            if (taskDueDate > parentDueDate) {
                driftAlert.style.display = 'flex';
                driftAlert.querySelector('span').innerHTML = `<strong>Desfase detectado:</strong> La fecha de esta tarea (${dueInput.value}) excede el plazo del proyecto (${this.currentSyncEntity.due_date}).`;
            } else {
                driftAlert.style.display = 'none';
            }
        }
    },

    saveEntityProcessPhase: function() {
        if (!this.currentSyncEntity) return;
        const phaseSelect = document.getElementById('tm-sync-phase-select');
        if (!phaseSelect) return;
        const newPhase = phaseSelect.value;

        const params = new URLSearchParams();
        params.append('action_type', 'update_entity_process_phase');
        params.append('entity_type', this.currentSyncEntity.type);
        params.append('entity_id', this.currentSyncEntity.id);
        params.append('new_phase', newPhase);

        const btn = document.querySelector('.tm-btn-phase-save');
        if (btn) btn.disabled = true;

        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params.toString()
        })
        .then(r => r.json())
        .then(res => {
            if (btn) btn.disabled = false;
            if (res.success) {
                // Update cached entity
                if (this.currentSyncEntity.type === 'calendar_month') {
                    const found = this.projectMonths.find(m => String(m.id) === String(this.currentSyncEntity.id));
                    if (found) found.content_phase = newPhase;
                } else if (this.currentSyncEntity.type === 'brand_project') {
                    const found = this.brandProjects.find(b => String(b.id) === String(this.currentSyncEntity.id));
                    if (found) found.status = newPhase;
                } else if (this.currentSyncEntity.type === 'project_service') {
                    const found = this.projectServices.find(s => String(s.id) === String(this.currentSyncEntity.id));
                    if (found) found.status = newPhase;
                }

                if (btn) {
                    const orig = btn.innerHTML;
                    btn.innerHTML = `<i class="ph-bold ph-check-circle"></i> ¡Guardado!`;
                    setTimeout(() => btn.innerHTML = orig, 1800);
                }

                // Refresh tasks in background to show updated chip
                this.loadTasks();
            } else {
                alert(res.error || "Error al actualizar la fase");
            }
        })
        .catch(err => {
            if (btn) btn.disabled = false;
            console.error(err);
        });
    },

    updateAllTimers: function() {
        const now = new Date();
        document.querySelectorAll('.tm-timer-pill[data-due]').forEach(el => {
            const dueStr = el.getAttribute('data-due');
            const startStr = el.getAttribute('data-start');
            const status = (el.getAttribute('data-status') || '').toLowerCase();
            const textEl = el.querySelector('.timer-text');
            const iconEl = el.querySelector('i');

            if (!dueStr || dueStr === 'null' || dueStr === '') {
                return;
            }

            if (status === 'completed' || status === 'approved' || status === 'finalizado') {
                el.className = 'tm-timer-pill completed';
                if (iconEl) iconEl.className = 'ph-fill ph-check-circle';
                if (textEl) textEl.textContent = 'Terminado';
                return;
            }

            let dueFormatted = dueStr.includes('T') ? dueStr : dueStr.replace(' ', 'T');
            if (dueFormatted.length === 10) dueFormatted += 'T23:59:59';
            const due = new Date(dueFormatted);

            if (isNaN(due.getTime())) {
                if (textEl) textEl.textContent = dueStr;
                return;
            }

            if (startStr && startStr !== 'null' && startStr !== '') {
                let startFormatted = startStr.includes('T') ? startStr : startStr.replace(' ', 'T');
                if (startFormatted.length === 10) startFormatted += 'T00:00:00';
                const start = new Date(startFormatted);
                if (now < start) {
                    el.className = 'tm-timer-pill upcoming';
                    if (iconEl) iconEl.className = 'ph-bold ph-clock-countdown';
                    const diffUpcoming = start - now;
                    const upDays = Math.floor(diffUpcoming / (1000 * 60 * 60 * 24));
                    const upHours = String(Math.floor((diffUpcoming % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
                    const upMins = String(Math.floor((diffUpcoming % (1000 * 60)) / (1000 * 60))).padStart(2, '0');
                    const upSecs = String(Math.floor((diffUpcoming % (1000 * 60)) / 1000)).padStart(2, '0');
                    if (textEl) textEl.textContent = upDays > 0 ? `Inicia en ${upDays}d ${upHours}:${upMins}:${upSecs}` : `Inicia en ${upHours}:${upMins}:${upSecs}`;
                    return;
                }
            }

            if (now > due) {
                el.className = 'tm-timer-pill expired';
                if (iconEl) iconEl.className = 'ph-fill ph-warning-circle';
                if (textEl) textEl.textContent = 'Tiempo agotado';
                return;
            }

            const diff = due - now;
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = String(Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
            const mins = String(Math.floor((diff % (1000 * 60)) / (1000 * 60))).padStart(2, '0');
            const secs = String(Math.floor((diff % (1000 * 60)) / 1000)).padStart(2, '0');

            if (days < 2) {
                el.className = 'tm-timer-pill warning';
                if (iconEl) iconEl.className = 'ph-fill ph-hourglass-medium';
            } else {
                el.className = 'tm-timer-pill active';
                if (iconEl) iconEl.className = 'ph-fill ph-hourglass-high';
            }

            if (textEl) {
                textEl.textContent = days > 0 ? `${days}d ${hours}:${mins}:${secs}` : `${hours}:${mins}:${secs}`;
            }
        });
    },

    onFrequencyChange: function(freq) {
        const isObjCheck = document.getElementById('tm-is-daily-objective');
        if (freq === 'daily' && isObjCheck && !isObjCheck.checked) {
            isObjCheck.checked = true;
            this.onDailyObjectiveToggle(true);
        }
    },

    onDailyObjectiveToggle: function(checked) {
        const card = document.getElementById('tm-objective-card');
        const panel = document.getElementById('tm-objective-date-panel');
        const badge = document.getElementById('tm-objective-badge');
        const subtext = document.getElementById('tm-objective-text');
        const hiddenInput = document.getElementById('tm-objective-date');
        const displayInput = document.getElementById('tm-objective-date-display');

        if (card) card.classList.toggle('is-active', checked);
        if (panel) panel.style.display = checked ? 'flex' : 'none';
        if (badge) badge.style.display = checked ? 'inline-flex' : 'none';

        if (checked) {
            const cur = hiddenInput && hiddenInput.value ? hiddenInput.value : '';
            const targetDate = cur || this.currentDailyDate || new Date().toISOString().substring(0, 10);
            this.setObjectiveDate(targetDate);
        } else {
            if (subtext) subtext.textContent = 'Fijar como meta principal del día';
            if (hiddenInput) hiddenInput.value = '';
            if (displayInput) displayInput.value = '';
            if (this.dpObjectiveDate) {
                try { this.dpObjectiveDate.clear(); } catch(e) {}
            }
        }
    },

    setObjectiveDate: function(dateStr) {
        const hidden = document.getElementById('tm-objective-date');
        const display = document.getElementById('tm-objective-date-display');
        const badge = document.getElementById('tm-objective-badge');
        const subtext = document.getElementById('tm-objective-text');

        if (!dateStr) {
            if (hidden) hidden.value = '';
            if (display) display.value = '';
            if (badge) badge.style.display = 'none';
            if (subtext) subtext.textContent = 'Fijar como meta principal del día';
            return;
        }

        // Clean up date string format if timestamp is passed
        dateStr = dateStr.substring(0, 10);
        if (hidden) hidden.value = dateStr;

        // Calculate today & tomorrow strings
        const todayStr = new Date().toISOString().substring(0, 10);
        const tomDate = new Date();
        tomDate.setDate(tomDate.getDate() + 1);
        const tomStr = tomDate.toISOString().substring(0, 10);

        const isToday = (dateStr === todayStr);
        const isTomorrow = (dateStr === tomStr);

        const parts = dateStr.split('-');
        let formatted = dateStr;
        if (parts.length === 3) {
            const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            formatted = `${dayNames[d.getDay()]}, ${d.getDate()} ${monthNames[d.getMonth()]} ${d.getFullYear()}`;
            if (isToday) formatted += ' • Hoy';
            else if (isTomorrow) formatted += ' • Mañana';

            if (subtext) {
                subtext.textContent = isToday ? 'Meta programada para Hoy' : (isTomorrow ? 'Meta programada para Mañana' : `Meta para el ${d.getDate()} de ${monthNames[d.getMonth()]}`);
            }
        }

        if (display) display.value = formatted;
        if (badge) badge.style.display = 'inline-flex';

        // Shortcut buttons active state
        const btnToday = document.getElementById('btn-obj-today');
        const btnTom = document.getElementById('btn-obj-tomorrow');
        const btnCustom = document.getElementById('btn-obj-custom');
        if (btnToday) btnToday.classList.toggle('active', isToday);
        if (btnTom) btnTom.classList.toggle('active', isTomorrow);
        if (btnCustom) btnCustom.classList.toggle('active', !isToday && !isTomorrow);

        if (this.dpObjectiveDate) {
            try {
                const p = dateStr.split('-');
                if (p.length === 3) {
                    this.dpObjectiveDate.selectDate(new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10)));
                }
            } catch(e) {}
        }
    },

    setObjectiveQuickDate: function(type) {
        let target = new Date();
        if (type === 'tomorrow') {
            target.setDate(target.getDate() + 1);
        }
        const y = target.getFullYear();
        const m = String(target.getMonth() + 1).padStart(2, '0');
        const day = String(target.getDate()).padStart(2, '0');
        this.setObjectiveDate(`${y}-${m}-${day}`);
    },

    openObjectiveDatePicker: function() {
        if (this.dpObjectiveDate) {
            this.dpObjectiveDate.show();
        } else {
            const display = document.getElementById('tm-objective-date-display');
            if (display) display.focus();
        }
    },

    toggleDailyObjectiveFromCard: function(event) {
        if (event.target.closest('.tm-switch') || event.target.closest('.tm-objective-date-panel') || event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON') {
            return;
        }
        const chk = document.getElementById('tm-is-daily-objective');
        if (chk) {
            chk.checked = !chk.checked;
            this.onDailyObjectiveToggle(chk.checked);
        }
    },

    // ══════════════════════════════════════════════════════
    // Tasks Loading & Rendering
    // ══════════════════════════════════════════════════════
    loadTasks: function() {
        const params = new URLSearchParams();
        params.append('action_type', 'get_all_tasks');
        params.append('filter_user', this.filterUser);
        params.append('filter_area', this.filterArea);
        params.append('filter_frequency', this.filterFrequency);

        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params.toString()
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                this.tasks = data.tasks || [];
                this.updateKPIs(data.stats);
                this.renderCurrentView();
            } else {
                console.error(data.error);
            }
        })
        .catch(err => console.error('Error loading tasks:', err));
    },

    updateKPIs: function(stats) {
        if (!stats) return;
        document.getElementById('kpi-new').innerText = stats.new || 0;
        document.getElementById('kpi-pending').innerText = (stats.pending || 0) + (stats.overdue || 0);
        document.getElementById('kpi-overdue').innerText = stats.overdue || 0;
        document.getElementById('kpi-completed').innerText = (stats.completed || 0) + (stats.approved || 0);
        
        const objRatio = `${stats.daily_objectives_completed || 0} / ${stats.daily_objectives_total || 0}`;
        document.getElementById('kpi-daily-objectives').innerText = objRatio;

        // Counters for kanban columns
        document.getElementById('count-new').innerText = stats.new || 0;
        document.getElementById('count-pending').innerText = (stats.pending || 0) + (stats.overdue || 0);
        document.getElementById('count-completed').innerText = stats.completed || 0;
        document.getElementById('count-approved').innerText = stats.approved || 0;

        // Counters for pills
        document.getElementById('count-pill-all').innerText = stats.total || 0;
        document.getElementById('count-pill-brand').innerText = stats.marca_count || 0;
        document.getElementById('count-pill-web').innerText = stats.web_count || 0;
        document.getElementById('count-pill-audio').innerText = stats.audio_count || 0;
    },

    switchView: function(viewName) {
        this.currentView = viewName;

        // Update buttons
        document.querySelectorAll('.tm-view-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === viewName);
        });

        // Toggle view containers
        document.getElementById('tm-view-kanban').style.display = (viewName === 'kanban') ? 'grid' : 'none';
        document.getElementById('tm-view-daily').style.display = (viewName === 'daily') ? 'block' : 'none';
        document.getElementById('tm-view-weekly').style.display = (viewName === 'weekly') ? 'block' : 'none';
        if (document.getElementById('tm-view-gantt')) {
            document.getElementById('tm-view-gantt').style.display = (viewName === 'gantt') ? 'block' : 'none';
        }

        this.renderCurrentView();

        if (viewName === 'gantt') {
            if (window.innerWidth <= 768) {
                this.setGanttMobileMode(this.ganttMobileMode || 'split');
            }
            setTimeout(() => {
                const todayLine = document.querySelector('.tm-gantt-today-line');
                const timelineWrapper = document.getElementById('tm-gantt-timeline-wrapper');
                if (todayLine && timelineWrapper) {
                    const offset = todayLine.offsetLeft - (timelineWrapper.clientWidth / 2.5);
                    timelineWrapper.scrollTo({ left: Math.max(0, offset), behavior: 'smooth' });
                }
            }, 120);
        }
    },

    renderCurrentView: function() {
        if (this.currentView === 'kanban') {
            this.renderKanban();
        } else if (this.currentView === 'daily') {
            this.renderDailyView();
        } else if (this.currentView === 'weekly') {
            this.renderWeeklyView();
        } else if (this.currentView === 'gantt') {
            this.renderGanttView();
        }
    },

    // ══════════════════════════════════════════════════════
    // VIEW 1: KANBAN BOARD
    // ══════════════════════════════════════════════════════
    renderKanban: function() {
        // Clear columns
        ['new', 'pending', 'completed', 'approved'].forEach(status => {
            const col = document.getElementById(`col-${status}`);
            if (col) col.innerHTML = '';
        });

        const filteredTasks = this.tasks.filter(t => {
            if (this.filterProject !== 'all' && String(t.project_id) !== String(this.filterProject)) {
                return false;
            }
            return true;
        });

        filteredTasks.forEach(t => {
            let colStatus = t.status;
            if(colStatus === 'overdue') colStatus = 'pending';
            
            const col = document.getElementById(`col-${colStatus}`);
            if(!col) return;

            const card = document.createElement('div');
            card.className = `tm-task-card ${t.status === 'overdue' ? 'is-overdue' : ''} tm-card-area-${t.area}`;
            card.draggable = true;
            card.id = `tm-task-${t.id}`;
            card.dataset.id = t.id;
            card.dataset.status = t.status;

            card.addEventListener('dragstart', this.dragStart.bind(this));
            card.addEventListener('dragend', this.dragEnd.bind(this));
            card.addEventListener('click', () => this.openEditModal(t));

            // Area Badge
            let areaBadgeHtml = '';
            if (t.area === 'desarrollo_marca') {
                areaBadgeHtml = `<span class="tm-badge tm-badge-brand"><i class="ph ph-paint-brush"></i> Marca</span>`;
            } else if (t.area === 'desarrollo_web') {
                areaBadgeHtml = `<span class="tm-badge tm-badge-web"><i class="ph ph-browser"></i> Web</span>`;
            } else if (t.area === 'audiovisual') {
                areaBadgeHtml = `<span class="tm-badge tm-badge-audio"><i class="ph ph-video-camera"></i> Audiovisual</span>`;
            }

            // Frequency & Daily Objective Badge
            let freqBadgeHtml = '';
            if (t.frequency === 'daily') {
                freqBadgeHtml = `<span class="tm-badge tm-badge-daily"><i class="ph ph-lightning"></i> Diaria</span>`;
            } else if (t.frequency === 'weekly') {
                freqBadgeHtml = `<span class="tm-badge tm-badge-weekly"><i class="ph ph-calendar-check"></i> Semanal</span>`;
            }

            let objBadgeHtml = '';
            if (t.is_daily_objective) {
                objBadgeHtml = `<span class="tm-badge tm-badge-objective"><i class="ph ph-target"></i> Meta Hoy</span>`;
            }

            // Connected Project & Calendar Month / Brand / Service & Process Phase
            let projectHtml = '';
            let phaseChipHtml = '';
            let entityDueDate = null;

            if (t.project_month_info) {
                const pm = t.project_month_info;
                entityDueDate = pm.due_date;
                projectHtml = `
                    <div class="tm-task-project-chip" title="Mes de Calendario: ${pm.label}">
                        <i class="ph ph-calendar-blank"></i> <span>${this.escapeHtml(pm.label)}</span>
                    </div>
                `;
                const safePhase = (pm.content_phase || 'En Borrador').toLowerCase().replace(/\s+/g, '-');
                phaseChipHtml = `
                    <span class="tm-phase-chip phase-${safePhase}" title="Fase de Calendario: ${pm.content_phase}">
                        <i class="ph-bold ph-git-branch"></i> ${this.escapeHtml(pm.content_phase)}
                    </span>
                `;
            } else if (t.brand_project_info) {
                const bp = t.brand_project_info;
                entityDueDate = bp.due_date;
                projectHtml = `
                    <div class="tm-task-project-chip" title="Desarrollo de Marca: ${bp.title}">
                        <i class="ph ph-paint-brush"></i> <span>${this.escapeHtml(bp.title)}</span>
                    </div>
                `;
                phaseChipHtml = `
                    <span class="tm-phase-chip phase-brand" title="Estado de Marca: ${bp.status}">
                        <i class="ph-bold ph-sparkle"></i> ${this.escapeHtml(bp.status)}
                    </span>
                `;
            } else if (t.project_service_info) {
                const ps = t.project_service_info;
                entityDueDate = ps.due_date;
                const iconClass = t.area === 'audiovisual' ? 'ph-video-camera' : 'ph-browser';
                projectHtml = `
                    <div class="tm-task-project-chip" title="Servicio: ${ps.title} (${ps.project_name})">
                        <i class="ph ${iconClass}"></i> <span>${this.escapeHtml(ps.project_name)} · ${this.escapeHtml(ps.title)}</span>
                    </div>
                `;
                phaseChipHtml = `
                    <span class="tm-phase-chip phase-service" title="Estado del Servicio: ${ps.status}">
                        <i class="ph-bold ${iconClass}"></i> ${this.escapeHtml(ps.status)}
                    </span>
                `;
            } else if (t.project_name) {
                projectHtml = `
                    <div class="tm-task-project-chip" title="Proyecto: ${t.project_name}">
                        <i class="ph ph-folder"></i> <span>${this.escapeHtml(t.project_name)}</span>
                    </div>
                `;
            }

            let badgesHtml = `
                <div class="tm-task-badges-row">
                    <span class="tm-badge tm-badge-priority-${t.priority}">${t.priority}</span>
                    ${areaBadgeHtml}
                    ${phaseChipHtml}
                    ${freqBadgeHtml}
                    ${objBadgeHtml}
                    ${t.status === 'overdue' ? '<span class="tm-badge tm-badge-overdue"><i class="ph ph-warning-circle"></i> Retrasada</span>' : ''}
                </div>
            `;

            // Subtasks Progress
            let subtasksHtml = '';
            if (t.subtasks && t.subtasks.total > 0) {
                const pct = Math.round((t.subtasks.completed / t.subtasks.total) * 100);
                subtasksHtml = `
                    <div class="tm-subtasks-progress">
                        <div class="tm-subtasks-bar"><div class="tm-subtasks-bar-fill" style="width:${pct}%"></div></div>
                        <span class="tm-subtasks-text"><i class="ph ph-check"></i> ${t.subtasks.completed}/${t.subtasks.total}</span>
                    </div>
                `;
            }

            // Users Avatars
            let usersHtml = '';
            if(t.assigned_users && t.assigned_users.length > 0) {
                usersHtml = `<div class="tm-task-users" title="${t.assigned_users.map(u => u.name).join(', ')}">`;
                const maxVisible = 4;
                const visibleUsers = t.assigned_users.slice(0, maxVisible);
                const extra = t.assigned_users.length - maxVisible;

                visibleUsers.forEach(u => {
                    const safeName = this.escapeHtml(u.name || 'Usuario');
                    const initial = u.initial || (u.name ? u.name.charAt(0).toUpperCase() : 'U');
                    if(u.avatar) {
                        usersHtml += `<div class="tm-task-user" title="${safeName}"><img src="${u.avatar}" alt="${safeName}"></div>`;
                    } else {
                        usersHtml += `<div class="tm-task-user" title="${safeName}">${initial}</div>`;
                    }
                });

                if (extra > 0) {
                    usersHtml += `<div class="tm-task-user tm-task-user-extra" title="+${extra} más">+${extra}</div>`;
                }
                usersHtml += `</div>`;
            }

            // Live Countdown Timer (uses task due_date, or falls back to parent entity due_date)
            let timerHtml = '';
            const effectiveDue = t.due_date || entityDueDate;
            if (effectiveDue) {
                timerHtml = `
                    <div class="tm-timer-pill" data-due="${effectiveDue}" data-start="${t.start_date || ''}" data-status="${t.status}" title="Límite: ${effectiveDue}">
                        <i class="ph-fill ph-hourglass-high"></i>
                        <span class="timer-text">Calculando...</span>
                    </div>
                `;
            }

            card.innerHTML = `
                ${badgesHtml}
                <h4 class="tm-task-title">${this.escapeHtml(t.title)}</h4>
                ${projectHtml}
                ${subtasksHtml}
                <div class="tm-task-footer">
                    <div class="tm-task-footer-left">
                        ${timerHtml}
                    </div>
                    <div class="tm-task-footer-right">
                        ${usersHtml}
                    </div>
                </div>
            `;
            col.appendChild(card);
        });

        this.updateAllTimers();
    },

    // ══════════════════════════════════════════════════════
    // VIEW 2: DAILY OBJECTIVES & EVALUATION
    // ══════════════════════════════════════════════════════
    renderDailyView: function() {
        this.loadDailyObjectives(this.currentDailyDate, this.currentDailyUser);
    },

    loadDailyObjectives: function(dateStr, userId) {
        const params = new URLSearchParams();
        params.append('action_type', 'get_daily_objectives');
        params.append('date', dateStr);
        params.append('user_id', userId);

        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params.toString()
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                this.dailyObjectivesData = data;
                this.renderDailyUI(data);
            }
        })
        .catch(err => console.error('Error loading daily objectives:', err));
    },

    renderDailyUI: function(data) {
        // Date Text
        const dateObj = new Date(data.date + 'T00:00:00');
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const dateFormatted = dateObj.toLocaleDateString('es-ES', options);
        document.getElementById('tm-daily-date-text').textContent = dateFormatted.charAt(0).toUpperCase() + dateFormatted.slice(1);

        // Circular Ring & Metrics
        const pct = Math.round(data.percentage || 0);
        document.getElementById('tm-daily-circle-pct').textContent = pct + '%';
        const circle = document.getElementById('tm-daily-circle-bar');
        if (circle) {
            const circumference = 2 * Math.PI * 23; // r = 23
            const offset = circumference - (pct / 100) * circumference;
            circle.style.strokeDashoffset = offset;
            circle.style.stroke = pct >= 90 ? '#10b981' : (pct >= 50 ? '#f59e0b' : '#3b82f6');
        }

        document.getElementById('tm-daily-metrics-ratio').textContent = `${data.completed} de ${data.total} objetivos cumplidos`;

        let statusTxt = 'Sin evaluar aún';
        if (data.evaluation) {
            const perfLabels = {
                'excellent': '<i class="ph-fill ph-sparkle"></i> Rendimiento Sobresaliente',
                'good': '<i class="ph-fill ph-thumbs-up"></i> Buen Desempeño',
                'average': '<i class="ph-fill ph-scales"></i> Rendimiento Regular',
                'poor': '<i class="ph-fill ph-warning"></i> Necesita Mejorar'
            };
            statusTxt = perfLabels[data.evaluation.performance_level] || 'Evaluado';
        }
        document.getElementById('tm-daily-metrics-status').innerHTML = statusTxt;

        // Render Checklist
        const listContainer = document.getElementById('tm-daily-objectives-list');
        listContainer.innerHTML = '';

        if (!data.objectives || data.objectives.length === 0) {
            listContainer.innerHTML = `
                <div class="tm-empty-state">
                    <i class="ph ph-target"></i>
                    <p>No hay objetivos fijados para este día.</p>
                    <button class="tm-btn-subtle" onclick="TM.openCreateModal('daily')">
                        <i class="ph ph-plus-circle"></i> Fijar primer objetivo de hoy
                    </button>
                </div>
            `;
        } else {
            data.objectives.forEach(task => {
                const item = document.createElement('div');
                item.className = `tm-daily-item ${task.is_completed ? 'is-completed' : ''}`;
                
                item.innerHTML = `
                    <label class="tm-custom-checkbox">
                        <input type="checkbox" ${task.is_completed ? 'checked' : ''} onchange="TM.toggleDailyCompletion(${task.id}, this)">
                        <span class="tm-checkmark"></span>
                    </label>
                    <div class="tm-daily-item-info" onclick="TM.openEditModalById(${task.id})">
                        <span class="tm-daily-item-title">${this.escapeHtml(task.title)}</span>
                        <div class="tm-daily-item-meta">
                            <span class="tm-badge tm-badge-priority-${task.priority}">${task.priority}</span>
                            <span class="tm-badge tm-badge-area">${task.area || 'general'}</span>
                            ${task.frequency === 'daily' ? '<span class="tm-badge tm-badge-daily">Diaria</span>' : ''}
                        </div>
                    </div>
                    <button class="tm-btn-icon-del" onclick="TM.removeObjectiveFromDaily(${task.id})" title="Quitar de objetivos del día">
                        <i class="ph ph-x"></i>
                    </button>
                `;
                listContainer.appendChild(item);
            });
        }

        // Render Evaluation Card
        const evalBadge = document.getElementById('tm-daily-eval-badge');
        const evalBody = document.getElementById('tm-daily-eval-body');

        if (data.evaluation) {
            evalBadge.className = 'tm-status-pill pill-success';
            evalBadge.innerHTML = '<i class="ph ph-check"></i> Evaluado';

            let starsHtml = '';
            for (let s = 1; s <= 5; s++) {
                starsHtml += s <= data.evaluation.score
                    ? '<i class="ph-fill ph-star" style="color:#f59e0b; margin-right:2px;"></i>'
                    : '<i class="ph ph-star" style="color:var(--text-muted); opacity:0.35; margin-right:2px;"></i>';
            }

            evalBody.innerHTML = `
                <div class="tm-eval-result-card">
                    <div class="tm-eval-result-stars">${starsHtml} <span style="font-size:0.85rem; font-weight:700; color:var(--text-muted); margin-left:4px;">(${data.evaluation.score}/5)</span></div>
                    <div class="tm-eval-result-level">${statusTxt}</div>
                    <div class="tm-eval-result-notes">
                        <strong>Comentarios y Reflexión:</strong>
                        <p>${this.escapeHtml(data.evaluation.evaluation_notes || 'Sin notas adicionales.')}</p>
                    </div>
                    <div class="tm-eval-result-footer">
                        <span>Evaluado por: <strong>${this.escapeHtml(data.evaluation.evaluator_name || 'Tú')}</strong></span>
                    </div>
                </div>
            `;
        } else {
            evalBadge.className = 'tm-status-pill pill-warning';
            evalBadge.textContent = 'Pendiente';
            evalBody.innerHTML = `
                <div class="tm-eval-pending-box">
                    <i class="ph ph-hourglass-high" style="font-size:2rem; color:#f59e0b; margin-bottom:0.5rem; display:block;"></i>
                    <p>Al finalizar la jornada, califica tu nivel de cumplimiento, registra aprendizajes o bloqueos para cerrar el día con claridad.</p>
                </div>
            `;
        }
    },

    changeDailyDate: function(offsetDays) {
        const d = new Date(this.currentDailyDate + 'T00:00:00');
        d.setDate(d.getDate() + offsetDays);
        this.currentDailyDate = d.toISOString().substring(0, 10);
        this.renderDailyView();
    },

    setDailyDateToday: function() {
        this.currentDailyDate = new Date().toISOString().substring(0, 10);
        this.renderDailyView();
    },

    setDailyDate: function(dateStr) {
        this.currentDailyDate = dateStr;
        this.renderDailyView();
    },

    toggleDailyCompletion: function(taskId, checkboxElem) {
        const formData = new URLSearchParams();
        formData.append('action_type', 'toggle_daily_objective');
        formData.append('task_id', taskId);
        formData.append('toggle_type', 'toggle_completion');

        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                this.loadDailyObjectives(this.currentDailyDate, this.currentDailyUser);
                this.loadTasks(); // keep kanban in sync
            } else {
                checkboxElem.checked = !checkboxElem.checked;
                alert(res.error || "Error al actualizar estado");
            }
        });
    },

    removeObjectiveFromDaily: function(taskId) {
        if(!confirm("¿Quitar esta tarea de los objetivos del día? La tarea seguirá existiendo en el tablero.")) return;
        const formData = new URLSearchParams();
        formData.append('action_type', 'toggle_daily_objective');
        formData.append('task_id', taskId);
        formData.append('toggle_type', 'mark_objective');

        fetch('modules/task_manager/ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                this.loadDailyObjectives(this.currentDailyDate, this.currentDailyUser);
                this.loadTasks();
            }
        });
    },

    // ══════════════════════════════════════════════════════
    // VIEW 3: WEEKLY PLANNER
    // ══════════════════════════════════════════════════════
    renderWeeklyView: function() {
        const container = document.getElementById('tm-weekly-grid');
        if (!container) return;
        container.innerHTML = '';

        // Calculate current week Monday to Sunday
        const curr = new Date();
        const first = curr.getDate() - (curr.getDay() === 0 ? 6 : curr.getDay() - 1); // Monday
        const monday = new Date(curr.setDate(first));

        const dayNames = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        
        for (let i = 0; i < 7; i++) {
            const dayDate = new Date(monday);
            dayDate.setDate(monday.getDate() + i);
            const dateStr = dayDate.toISOString().substring(0, 10);
            const isToday = dateStr === new Date().toISOString().substring(0, 10);

            // Filter tasks matching this date or frequency = weekly on Monday
            const dayTasks = this.tasks.filter(t => {
                if (t.due_date && t.due_date.substring(0, 10) === dateStr) return true;
                if (t.objective_date && t.objective_date === dateStr) return true;
                if (t.frequency === 'weekly' && i === 0) return true; // Weekly meta on Monday
                return false;
            });

            const col = document.createElement('div');
            col.className = `tm-weekly-col ${isToday ? 'is-today' : ''}`;

            col.innerHTML = `
                <div class="tm-weekly-col-header">
                    <h4>${dayNames[i]}</h4>
                    <span class="tm-weekly-date">${dayDate.getDate()}</span>
                </div>
                <div class="tm-weekly-col-body" id="weekly-day-${i}"></div>
            `;
            container.appendChild(col);

            const body = col.querySelector('.tm-weekly-col-body');
            if (dayTasks.length === 0) {
                body.innerHTML = `<span class="tm-empty-mini">Sin tareas</span>`;
            } else {
                dayTasks.forEach(t => {
                    const card = document.createElement('div');
                    card.className = `tm-weekly-card priority-${t.priority} ${t.status === 'completed' ? 'is-done' : ''}`;
                    card.onclick = () => this.openEditModal(t);
                    card.innerHTML = `
                        <div class="tm-weekly-card-title">${this.escapeHtml(t.title)}</div>
                        <div class="tm-weekly-card-tag">${t.area || 'General'}</div>
                    `;
                    body.appendChild(card);
                });
            }
        }
    },

    // ══════════════════════════════════════════════════════
    // VIEW 4: DIAGRAMA DE GANTT (DIARIO Y SEMANAL)
    // ══════════════════════════════════════════════════════
    setGanttScale: function(scale) {
        this.ganttScale = scale;
        document.querySelectorAll('.tm-gantt-scale-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.scale === scale);
        });
        this.renderGanttView();
    },

    setGanttGroupBy: function(groupBy) {
        this.ganttGroupBy = groupBy;
        this.renderGanttView();
    },

    setGanttMobileMode: function(mode) {
        this.ganttMobileMode = mode;
        const container = document.getElementById('tm-gantt-container');
        if (container) {
            container.classList.remove('mode-split', 'mode-timeline', 'mode-list');
            container.classList.add(`mode-${mode}`);
        }
        document.querySelectorAll('.tm-gantt-mode-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.mode === mode);
        });

        if (mode === 'timeline' || mode === 'split') {
            setTimeout(() => {
                const todayLine = document.querySelector('.tm-gantt-today-line');
                const timelineWrapper = document.getElementById('tm-gantt-timeline-wrapper');
                if (todayLine && timelineWrapper) {
                    const offset = todayLine.offsetLeft - (timelineWrapper.clientWidth / 3);
                    timelineWrapper.scrollTo({ left: Math.max(0, offset), behavior: 'smooth' });
                }
            }, 100);
        }
    },

    toggleGanttFullscreen: function() {
        this.isGanttFullscreen = !this.isGanttFullscreen;
        const ganttView = document.getElementById('tm-view-gantt');
        const btn = document.querySelector('.tm-gantt-fullscreen-btn');
        if (ganttView) {
            ganttView.classList.toggle('is-fullscreen', this.isGanttFullscreen);
        }
        if (btn) {
            btn.innerHTML = this.isGanttFullscreen 
                ? '<i class="ph ph-arrows-in-simple"></i>' 
                : '<i class="ph ph-arrows-out-simple"></i>';
        }
        setTimeout(() => {
            const todayLine = document.querySelector('.tm-gantt-today-line');
            const timelineWrapper = document.getElementById('tm-gantt-timeline-wrapper');
            if (todayLine && timelineWrapper) {
                const offset = todayLine.offsetLeft - (timelineWrapper.clientWidth / 2);
                timelineWrapper.scrollTo({ left: Math.max(0, offset), behavior: 'smooth' });
            }
        }, 150);
    },

    setGanttToday: function() {
        this.ganttAnchorDate = new Date();
        this.renderGanttView();
        setTimeout(() => {
            const todayLine = document.querySelector('.tm-gantt-today-line');
            const timelineWrapper = document.getElementById('tm-gantt-timeline-wrapper');
            if (todayLine && timelineWrapper) {
                const offset = todayLine.offsetLeft - (timelineWrapper.clientWidth / 2);
                timelineWrapper.scrollTo({ left: Math.max(0, offset), behavior: 'smooth' });
            }
        }, 100);
    },

    navGanttRange: function(direction) {
        const anchor = new Date(this.ganttAnchorDate);
        if (this.ganttScale === 'day') {
            anchor.setMonth(anchor.getMonth() + direction);
        } else {
            anchor.setDate(anchor.getDate() + (direction * 28)); // 4 semanas
        }
        this.ganttAnchorDate = anchor;
        this.renderGanttView();
    },

    renderGanttView: function() {
        const sidebarBody = document.getElementById('tm-gantt-sidebar-body');
        const timelineHeader = document.getElementById('tm-gantt-timeline-header');
        const timelineBody = document.getElementById('tm-gantt-timeline-body');
        const rangeLabel = document.getElementById('tm-gantt-range-label');
        if (!sidebarBody || !timelineHeader || !timelineBody) return;

        sidebarBody.innerHTML = '';
        timelineHeader.innerHTML = '';
        timelineBody.innerHTML = '';

        const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const dayNamesShort = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

        // 1. Filtrar tareas activas según filtros superiores
        const filteredTasks = this.tasks.filter(t => {
            if (this.filterProject !== 'all' && String(t.project_id) !== String(this.filterProject)) return false;
            return true;
        });

        // 2. Calcular límites del rango temporal
        let rangeStart, rangeEnd;
        const anchor = new Date(this.ganttAnchorDate);

        if (this.ganttScale === 'day') {
            const year = anchor.getFullYear();
            const month = anchor.getMonth();
            rangeStart = new Date(year, month, 1, 0, 0, 0);
            rangeEnd = new Date(year, month + 1, 0, 23, 59, 59);
            if (rangeLabel) {
                rangeLabel.textContent = `${monthNames[month]} ${year}`;
            }
        } else {
            // Escala Semanal: 8 semanas centradas en el ancla
            const dayOfWeek = anchor.getDay() === 0 ? 6 : anchor.getDay() - 1; // Lunes = 0
            const monday = new Date(anchor);
            monday.setDate(anchor.getDate() - dayOfWeek);
            monday.setHours(0, 0, 0, 0);

            rangeStart = new Date(monday);
            rangeStart.setDate(monday.getDate() - 14); // 2 semanas antes
            rangeEnd = new Date(rangeStart);
            rangeEnd.setDate(rangeStart.getDate() + (8 * 7) - 1); // 8 semanas en total
            rangeEnd.setHours(23, 59, 59);

            if (rangeLabel) {
                const sMonth = monthNames[rangeStart.getMonth()].substring(0, 3);
                const eMonth = monthNames[rangeEnd.getMonth()].substring(0, 3);
                rangeLabel.textContent = `${sMonth} ${rangeStart.getFullYear()} - ${eMonth} ${rangeEnd.getFullYear()} (8 Semanas)`;
            }
        }

        const totalMs = rangeEnd.getTime() - rangeStart.getTime();

        // 3. Renderizar Header de la Línea de Tiempo (Días o Semanas)
        const headerTopRow = document.createElement('div');
        headerTopRow.className = 'tm-gantt-header-top';
        const headerBottomRow = document.createElement('div');
        headerBottomRow.className = 'tm-gantt-header-bottom';

        const today = new Date();
        const todayStr = today.toISOString().substring(0, 10);

        if (this.ganttScale === 'day') {
            const numDays = Math.round((rangeEnd - rangeStart) / (1000 * 60 * 60 * 24)) + 1;
            headerTopRow.innerHTML = `<div class="tm-gantt-month-banner">${monthNames[rangeStart.getMonth()]} ${rangeStart.getFullYear()}</div>`;

            for (let d = 0; d < numDays; d++) {
                const dayDate = new Date(rangeStart);
                dayDate.setDate(rangeStart.getDate() + d);
                const dStr = dayDate.toISOString().substring(0, 10);
                const dayOfWeek = dayDate.getDay();
                const isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
                const isToday = (dStr === todayStr);

                const dayCol = document.createElement('div');
                dayCol.className = `tm-gantt-header-cell ${isWeekend ? 'is-weekend' : ''} ${isToday ? 'is-today' : ''}`;
                dayCol.style.flex = '1';
                dayCol.innerHTML = `
                    <span class="tm-gantt-day-name">${dayNamesShort[dayOfWeek]}</span>
                    <span class="tm-gantt-day-num">${String(dayDate.getDate()).padStart(2, '0')}</span>
                `;
                headerBottomRow.appendChild(dayCol);
            }
        } else {
            // Semanal
            const numWeeks = 8;
            headerTopRow.innerHTML = `<div class="tm-gantt-month-banner">Cronograma de Semanas</div>`;

            for (let w = 0; w < numWeeks; w++) {
                const wStart = new Date(rangeStart);
                wStart.setDate(rangeStart.getDate() + (w * 7));
                const wEnd = new Date(wStart);
                wEnd.setDate(wStart.getDate() + 6);

                const isCurrentWeek = (today >= wStart && today <= wEnd);
                const wCol = document.createElement('div');
                wCol.className = `tm-gantt-header-cell is-week ${isCurrentWeek ? 'is-today' : ''}`;
                wCol.style.flex = '1';
                wCol.innerHTML = `
                    <span class="tm-gantt-day-name">Semana ${this.getWeekNumber(wStart)}</span>
                    <span class="tm-gantt-day-num">${wStart.getDate()}/${wStart.getMonth()+1} - ${wEnd.getDate()}/${wEnd.getMonth()+1}</span>
                `;
                headerBottomRow.appendChild(wCol);
            }
        }

        timelineHeader.appendChild(headerTopRow);
        timelineHeader.appendChild(headerBottomRow);

        // 4. Agrupar Tareas según 'ganttGroupBy'
        const groups = this.groupTasksForGantt(filteredTasks, this.ganttGroupBy);

        if (Object.keys(groups).length === 0) {
            sidebarBody.innerHTML = `
                <div class="tm-gantt-empty">
                    <i class="ph ph-calendar-blank"></i>
                    <p>No hay tareas disponibles con los filtros actuales.</p>
                </div>
            `;
            timelineBody.innerHTML = `
                <div class="tm-gantt-empty">
                    <p>Crea una nueva tarea o ajusta los filtros superiores.</p>
                </div>
            `;
            return;
        }

        // 5. Renderizar Filas de Grupos y Tareas
        Object.entries(groups).forEach(([groupName, groupTasks]) => {
            // A. Fila de Encabezado de Grupo en Sidebar
            const groupSidebarRow = document.createElement('div');
            groupSidebarRow.className = 'tm-gantt-group-row-sidebar';
            groupSidebarRow.innerHTML = `
                <div class="tm-gantt-group-title">
                    <i class="ph-bold ph-folder"></i>
                    <span>${this.escapeHtml(groupName)}</span>
                    <span class="tm-gantt-group-badge">${groupTasks.length}</span>
                </div>
            `;
            sidebarBody.appendChild(groupSidebarRow);

            // B. Fila de Encabezado de Grupo en Timeline
            const groupTimelineRow = document.createElement('div');
            groupTimelineRow.className = 'tm-gantt-group-row-timeline';
            timelineBody.appendChild(groupTimelineRow);

            // C. Tareas del Grupo
            groupTasks.forEach(t => {
                // Fechas de la tarea
                let tStart = t.start_date ? new Date(t.start_date.replace(' ', 'T')) : null;
                let tDue = t.due_date ? new Date(t.due_date.replace(' ', 'T')) : null;

                const hasExplicitDates = Boolean(tStart || tDue);

                if (!tStart && !tDue) {
                    tStart = new Date();
                    tStart.setHours(0, 0, 0, 0);
                    tDue = new Date(tStart);
                    tDue.setDate(tStart.getDate() + 1);
                    tDue.setHours(23, 59, 59);
                } else if (!tStart) {
                    tStart = new Date(tDue);
                    tStart.setDate(tStart.getDate() - 1);
                    tStart.setHours(0, 0, 0, 0);
                } else if (!tDue) {
                    tDue = new Date(tStart);
                    tDue.setDate(tDue.getDate() + 1);
                    tDue.setHours(23, 59, 59);
                }

                const durationDays = Math.max(1, Math.round((tDue - tStart) / (1000 * 60 * 60 * 24)));

                // -- Sidebar Row --
                const taskSidebarRow = document.createElement('div');
                taskSidebarRow.className = `tm-gantt-task-row-sidebar priority-${t.priority}`;
                taskSidebarRow.title = `Editar: ${t.title}`;
                taskSidebarRow.onclick = () => this.openEditModal(t);

                // Avatar(s)
                let userHtml = '';
                if (t.assigned_users && t.assigned_users.length > 0) {
                    const u = t.assigned_users[0];
                    userHtml = `<div class="tm-gantt-user-avatar" title="${this.escapeHtml(u.name)}">${u.initial || u.name.charAt(0)}</div>`;
                } else {
                    userHtml = `<div class="tm-gantt-user-avatar is-unassigned" title="Sin Asignar"><i class="ph ph-user"></i></div>`;
                }

                // Subtask completion
                let progressPct = 0;
                if (t.status === 'completed' || t.status === 'approved') {
                    progressPct = 100;
                } else if (t.subtasks && t.subtasks.total > 0) {
                    progressPct = Math.round((t.subtasks.completed / t.subtasks.total) * 100);
                }

                const sDateFormatted = tStart ? `${tStart.getDate()}/${tStart.getMonth()+1}` : '--';
                const dDateFormatted = tDue ? `${tDue.getDate()}/${tDue.getMonth()+1}` : '--';

                taskSidebarRow.innerHTML = `
                    <div class="tm-gantt-cell-info">
                        <span class="tm-status-dot status-${t.status}"></span>
                        <span class="tm-gantt-task-title" title="${this.escapeHtml(t.title)}">${this.escapeHtml(t.title)}</span>
                    </div>
                    <div class="tm-gantt-cell-meta">
                        ${userHtml}
                        <span class="tm-gantt-dates-chip" title="Inicio: ${sDateFormatted} | Fin: ${dDateFormatted}">${durationDays}d</span>
                        <span class="tm-gantt-pct-badge ${progressPct === 100 ? 'is-done' : ''}">${progressPct}%</span>
                    </div>
                `;
                sidebarBody.appendChild(taskSidebarRow);

                // -- Timeline Row & Grid Background --
                const taskTimelineRow = document.createElement('div');
                taskTimelineRow.className = 'tm-gantt-task-row-timeline';

                // Grid background columns
                if (this.ganttScale === 'day') {
                    const numDays = Math.round((rangeEnd - rangeStart) / (1000 * 60 * 60 * 24)) + 1;
                    for (let d = 0; d < numDays; d++) {
                        const dayDate = new Date(rangeStart);
                        dayDate.setDate(rangeStart.getDate() + d);
                        const dayOfWeek = dayDate.getDay();
                        const isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
                        const isToday = (dayDate.toISOString().substring(0, 10) === todayStr);

                        const cell = document.createElement('div');
                        cell.className = `tm-gantt-bg-cell ${isWeekend ? 'is-weekend' : ''} ${isToday ? 'is-today' : ''}`;
                        taskTimelineRow.appendChild(cell);
                    }
                } else {
                    for (let w = 0; w < 8; w++) {
                        const cell = document.createElement('div');
                        cell.className = 'tm-gantt-bg-cell is-week';
                        taskTimelineRow.appendChild(cell);
                    }
                }

                // Cálculo de posición proporcional de la barra
                const taskStartMs = tStart.getTime();
                const taskDueMs = tDue.getTime();

                // Verificar si cae dentro o fuera del rango visible
                const isBeforeRange = taskDueMs < rangeStart.getTime();
                const isAfterRange = taskStartMs > rangeEnd.getTime();

                if (!isBeforeRange && !isAfterRange) {
                    const clampedStartMs = Math.max(taskStartMs, rangeStart.getTime());
                    const clampedDueMs = Math.min(taskDueMs, rangeEnd.getTime());

                    const leftPct = ((clampedStartMs - rangeStart.getTime()) / totalMs) * 100;
                    const widthPct = Math.max(((clampedDueMs - clampedStartMs) / totalMs) * 100, 1.2);

                    const isOverdue = (t.status !== 'completed' && t.status !== 'approved' && tDue < today);

                    // Barra de Gantt
                    const bar = document.createElement('div');
                    bar.className = `tm-gantt-bar area-${t.area} status-${t.status} ${isOverdue ? 'is-overdue' : ''} ${!hasExplicitDates ? 'is-inferred' : ''}`;
                    bar.style.left = `${leftPct}%`;
                    bar.style.width = `${widthPct}%`;
                    bar.dataset.taskId = t.id;

                    bar.innerHTML = `
                        <div class="tm-gantt-handle handle-left" data-handle="left" title="Arrastrar para cambiar fecha de inicio"></div>
                        <div class="tm-gantt-bar-progress" style="width: ${progressPct}%;"></div>
                        <div class="tm-gantt-bar-content">
                            <span class="tm-gantt-bar-label">${this.escapeHtml(t.title)}</span>
                            <span class="tm-gantt-bar-days">${durationDays}d</span>
                        </div>
                        <div class="tm-gantt-handle handle-right" data-handle="right" title="Arrastrar para cambiar fecha límite"></div>
                    `;

                    // Click para editar
                    bar.addEventListener('click', (e) => {
                        if (e.target.classList.contains('tm-gantt-handle')) return;
                        if (bar.dataset.justDragged === 'true') {
                            bar.dataset.justDragged = 'false';
                            return;
                        }
                        this.openEditModal(t);
                    });

                    // Eventos de Drag & Resize
                    this.attachGanttBarDrag(bar, t, rangeStart, totalMs);

                    // Tooltip en hover
                    this.attachGanttTooltip(bar, t, tStart, tDue, durationDays, progressPct);

                    taskTimelineRow.appendChild(bar);
                }

                timelineBody.appendChild(taskTimelineRow);
            });
        });

        // 6. Línea vertical de 'Hoy'
        if (today >= rangeStart && today <= rangeEnd) {
            const todayMs = today.getTime();
            const todayLeftPct = ((todayMs - rangeStart.getTime()) / totalMs) * 100;

            const todayLine = document.createElement('div');
            todayLine.className = 'tm-gantt-today-line';
            todayLine.style.left = `${todayLeftPct}%`;
            todayLine.innerHTML = `<span class="tm-gantt-today-badge">Hoy</span>`;
            timelineBody.appendChild(todayLine);
        }

        // 7. Sincronización de Scroll Vertical
        sidebarBody.onscroll = () => {
            timelineBody.scrollTop = sidebarBody.scrollTop;
        };
        timelineBody.onscroll = () => {
            sidebarBody.scrollTop = timelineBody.scrollTop;
        };
    },

    groupTasksForGantt: function(tasks, groupBy) {
        const groups = {};
        tasks.forEach(t => {
            let key = 'General';
            if (groupBy === 'project') {
                key = t.project_name || t.brand_project_title || 'Sin Proyecto';
            } else if (groupBy === 'area') {
                key = t.area_label || 'General';
            } else if (groupBy === 'user') {
                if (t.assigned_users && t.assigned_users.length > 0) {
                    key = t.assigned_users.map(u => u.name).join(', ');
                } else {
                    key = 'Sin Asignar';
                }
            } else if (groupBy === 'status') {
                const statusMap = {
                    'new': 'Nuevo',
                    'pending': 'Pendiente / En Curso',
                    'completed': 'Terminado',
                    'approved': 'Aprobado',
                    'overdue': 'Retrasado'
                };
                key = statusMap[t.status] || t.status;
            } else {
                key = 'Todas las Tareas';
            }

            if (!groups[key]) groups[key] = [];
            groups[key].push(t);
        });
        return groups;
    },

    getWeekNumber: function(d) {
        const date = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
        const dayNum = date.getUTCDay() || 7;
        date.setUTCDate(date.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
        return Math.ceil((((date - yearStart) / 86400000) + 1) / 7);
    },

    attachGanttBarDrag: function(bar, task, rangeStart, totalMs) {
        let isDragging = false;
        let dragMode = null; // 'move', 'resize-left', 'resize-right'
        let startX = 0;
        let origStartMs = task.start_date ? new Date(task.start_date.replace(' ', 'T')).getTime() : new Date().getTime();
        let origDueMs = task.due_date ? new Date(task.due_date.replace(' ', 'T')).getTime() : (origStartMs + 86400000);
        let timelineWidth = 0;

        const onMouseDown = (e) => {
            if (e.button !== 0) return; // solo click izquierdo
            const handle = e.target.closest('.tm-gantt-handle');
            if (handle) {
                dragMode = handle.dataset.handle === 'left' ? 'resize-left' : 'resize-right';
            } else {
                dragMode = 'move';
            }

            isDragging = true;
            startX = e.clientX;
            const timeline = document.getElementById('tm-gantt-timeline-body');
            timelineWidth = timeline ? timeline.clientWidth : 1000;
            bar.classList.add('is-dragging');
            document.body.style.cursor = dragMode === 'move' ? 'grabbing' : 'ew-resize';

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
            e.preventDefault();
        };

        const onMouseMove = (e) => {
            if (!isDragging) return;
            const deltaX = e.clientX - startX;
            const deltaMs = (deltaX / timelineWidth) * totalMs;

            let newStartMs = origStartMs;
            let newDueMs = origDueMs;

            if (dragMode === 'move') {
                newStartMs = origStartMs + deltaMs;
                newDueMs = origDueMs + deltaMs;
            } else if (dragMode === 'resize-left') {
                newStartMs = Math.min(origStartMs + deltaMs, origDueMs - (1000 * 60 * 60 * 4)); // al menos 4h
            } else if (dragMode === 'resize-right') {
                newDueMs = Math.max(origDueMs + deltaMs, origStartMs + (1000 * 60 * 60 * 4));
            }

            // Actualizar posición visual temporal
            const newLeftPct = ((newStartMs - rangeStart.getTime()) / totalMs) * 100;
            const newWidthPct = Math.max(((newDueMs - newStartMs) / totalMs) * 100, 1.2);
            bar.style.left = `${newLeftPct}%`;
            bar.style.width = `${newWidthPct}%`;
        };

        const onMouseUp = (e) => {
            if (!isDragging) return;
            isDragging = false;
            bar.classList.remove('is-dragging');
            document.body.style.cursor = '';
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);

            const deltaX = e.clientX - startX;
            if (Math.abs(deltaX) > 4) {
                bar.dataset.justDragged = 'true';
                const deltaMs = (deltaX / timelineWidth) * totalMs;

                let finalStartMs = origStartMs;
                let finalDueMs = origDueMs;

                if (dragMode === 'move') {
                    finalStartMs = origStartMs + deltaMs;
                    finalDueMs = origDueMs + deltaMs;
                } else if (dragMode === 'resize-left') {
                    finalStartMs = Math.min(origStartMs + deltaMs, origDueMs - (1000 * 60 * 60 * 4));
                } else if (dragMode === 'resize-right') {
                    finalDueMs = Math.max(origDueMs + deltaMs, origStartMs + (1000 * 60 * 60 * 4));
                }

                // Convertir a formato MySQL datetime YYYY-MM-DD HH:mm:ss
                const newStartStr = new Date(finalStartMs).toISOString().substring(0, 19).replace('T', ' ');
                const newDueStr = new Date(finalDueMs).toISOString().substring(0, 19).replace('T', ' ');

                TM.updateTaskDates(task.id, newStartStr, newDueStr);
            }
        };

        bar.addEventListener('mousedown', onMouseDown);
    },

    updateTaskDates: function(taskId, startDate, dueDate) {
        const formData = new URLSearchParams();
        formData.append('action_type', 'update_task_dates');
        formData.append('task_id', taskId);
        formData.append('start_date', startDate);
        formData.append('due_date', dueDate);

        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData.toString()
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // Actualizar en memoria local y re-renderizar suavemente
                const t = this.tasks.find(x => x.id === parseInt(taskId, 10));
                if (t) {
                    t.start_date = res.start_date;
                    t.due_date = res.due_date;
                }
                this.renderGanttView();
            } else {
                alert(res.error || 'Error al actualizar fechas en el cronograma');
                this.renderGanttView();
            }
        })
        .catch(err => {
            console.error('Error al actualizar fechas del Gantt:', err);
            this.renderGanttView();
        });
    },

    attachGanttTooltip: function(bar, task, tStart, tDue, durationDays, progressPct) {
        let tooltip = document.getElementById('tm-gantt-tooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.id = 'tm-gantt-tooltip';
            tooltip.className = 'tm-gantt-tooltip';
            document.body.appendChild(tooltip);
        }

        bar.addEventListener('mouseenter', (e) => {
            const sStr = tStart ? `${tStart.getDate()}/${tStart.getMonth()+1}/${tStart.getFullYear()}` : 'Sin definir';
            const dStr = tDue ? `${tDue.getDate()}/${tDue.getMonth()+1}/${tDue.getFullYear()}` : 'Sin definir';

            let userNames = 'Sin Asignar';
            if (task.assigned_users && task.assigned_users.length > 0) {
                userNames = task.assigned_users.map(u => u.name).join(', ');
            }

            tooltip.innerHTML = `
                <div class="tm-tooltip-header">
                    <strong>${this.escapeHtml(task.title)}</strong>
                    <span class="tm-status-pill status-${task.status}">${task.status}</span>
                </div>
                <div class="tm-tooltip-body">
                    <div class="tm-tooltip-row"><i class="ph ph-calendar"></i> <span>${sStr} &rarr; ${dStr} (${durationDays}d)</span></div>
                    <div class="tm-tooltip-row"><i class="ph ph-user"></i> <span>${this.escapeHtml(userNames)}</span></div>
                    ${task.project_name ? `<div class="tm-tooltip-row"><i class="ph ph-folder"></i> <span>${this.escapeHtml(task.project_name)}</span></div>` : ''}
                    <div class="tm-tooltip-row"><i class="ph ph-check-square"></i> <span>Avance: ${progressPct}%</span></div>
                </div>
            `;
            tooltip.style.display = 'block';
            this.positionGanttTooltip(e, tooltip);
        });

        bar.addEventListener('mousemove', (e) => {
            this.positionGanttTooltip(e, tooltip);
        });

        bar.addEventListener('mouseleave', () => {
            tooltip.style.display = 'none';
        });
    },

    positionGanttTooltip: function(e, tooltip) {
        const x = e.clientX + 14;
        const y = e.clientY + 14;
        tooltip.style.left = `${Math.min(x, window.innerWidth - 280)}px`;
        tooltip.style.top = `${Math.min(y, window.innerHeight - 150)}px`;
    },

    // ══════════════════════════════════════════════════════
    // Modals: Create / Edit Task
    // ══════════════════════════════════════════════════════
    openCreateModal: function(defaultFreq = 'one_time') {
        document.getElementById('form-task').reset();
        document.getElementById('tm-task-id').value = '';
        document.getElementById('tm-modal-title').textContent = 'Nueva Tarea';
        document.getElementById('tm-submit-btn').innerHTML = '<i class="ph ph-check-circle"></i> Crear Tarea';
        document.getElementById('tm-edit-actions').style.display = 'none';

        if (this.quillDesc) this.quillDesc.root.innerHTML = '';
        this.currentAssignedUserIds = [];
        this.renderAssignedUsers();
        this.currentTags = [];
        this.renderTags();
        this.updateProjectMembersBar();

        document.getElementById('tm-subtasks-list').innerHTML = '';

        if (document.getElementById('tm-project-service-id')) {
            document.getElementById('tm-project-service-id').value = '';
        }
        if (document.getElementById('tm-sync-panel')) {
            document.getElementById('tm-sync-panel').style.display = 'none';
        }
        if (document.getElementById('tm-modal-task-timer')) {
            document.getElementById('tm-modal-task-timer').style.display = 'none';
        }
        this.currentSyncEntity = null;

        // Default frequency & objective
        const freqSelect = document.getElementById('tm-frequency');
        if (freqSelect) {
            freqSelect.value = defaultFreq === 'daily' ? 'daily' : 'one_time';
            this.onFrequencyChange(freqSelect.value);
        }

        const isObjCheck = document.getElementById('tm-is-daily-objective');
        if (isObjCheck) {
            isObjCheck.checked = (defaultFreq === 'daily');
            this.onDailyObjectiveToggle(isObjCheck.checked);
            if (isObjCheck.checked) {
                this.setObjectiveDate(this.currentDailyDate || new Date().toISOString().substring(0, 10));
            } else {
                this.setObjectiveDate('');
            }
        }

        this.onAreaChange(document.getElementById('tm-area').value);
        document.getElementById('tm-start-date').value = '';
        if (this.dpStartDate) {
            this.dpStartDate.clear();
        }
        document.getElementById('tm-due-date').value = '';
        if (this.dpDueDate) {
            this.dpDueDate.clear();
        }
        document.body.style.overflow = 'hidden';
        document.getElementById('tm-modal-task').style.display = 'flex';
    },

    openEditModal: function(task) {
        document.getElementById('form-task').reset();
        document.getElementById('tm-task-id').value = task.id;
        document.getElementById('tm-task-id-badge').textContent = '#' + task.id;
        document.getElementById('tm-modal-title').textContent = 'Editar Tarea #' + task.id;
        document.getElementById('tm-submit-btn').innerHTML = '<i class="ph ph-floppy-disk"></i> Guardar Cambios';
        document.getElementById('tm-edit-actions').style.display = 'flex';

        document.getElementById('tm-title').value = task.title || '';
        document.getElementById('tm-priority').value = task.priority || 'medium';
        document.getElementById('tm-status').value = task.status || 'new';
        document.getElementById('tm-frequency').value = task.frequency || 'one_time';
        document.getElementById('tm-area').value = task.area || 'general';
        this.onAreaChange(task.area || 'general');

        // Brand project id
        if (document.getElementById('tm-brand-project-id')) {
            document.getElementById('tm-brand-project-id').value = task.brand_project_id || '';
        }

        // Project and Month cascade
        const pSelect = document.getElementById('tm-project-id');
        if (pSelect) {
            pSelect.value = task.project_id || '';
            this.onProjectChange(task.project_id, task.project_month_id);
        }

        // Project service id (Web / Audiovisual)
        if (document.getElementById('tm-project-service-id')) {
            document.getElementById('tm-project-service-id').value = task.project_service_id || '';
        }

        this.refreshSyncPanelFromSelections();
        this.checkDeadlineDrift();

        // Daily objective
        const isObjCheck = document.getElementById('tm-is-daily-objective');
        if (isObjCheck) {
            const isDaily = Boolean(task.is_daily_objective);
            isObjCheck.checked = isDaily;
            this.onDailyObjectiveToggle(isDaily);
            if (task.objective_date) {
                this.setObjectiveDate(task.objective_date.substring(0, 10));
            } else if (isDaily) {
                this.setObjectiveDate(new Date().toISOString().substring(0, 10));
            } else {
                this.setObjectiveDate('');
            }
        }

        // Start date
        document.getElementById('tm-start-date').value = task.start_date ? task.start_date.substring(0, 16) : '';
        if (this.dpStartDate && task.start_date) {
            try {
                this.dpStartDate.selectDate(new Date(task.start_date.replace(' ', 'T')));
            } catch(e) {}
        } else if (this.dpStartDate) {
            this.dpStartDate.clear();
        }

        // Due date
        document.getElementById('tm-due-date').value = task.due_date ? task.due_date.substring(0, 16) : '';
        if (this.dpDueDate && task.due_date) {
            try {
                this.dpDueDate.selectDate(new Date(task.due_date.replace(' ', 'T')));
            } catch(e) {}
        } else if (this.dpDueDate) {
            this.dpDueDate.clear();
        }
        // Setup current assigned users
        let uids = [];
        if (Array.isArray(task.assigned_users)) {
            uids = task.assigned_users.map(u => {
                if (typeof u === 'object' && u !== null) return parseInt(u.id, 10);
                return parseInt(u, 10);
            }).filter(id => !isNaN(id) && id > 0);
        }
        this.currentAssignedUserIds = uids;
        this.renderAssignedUsers();

        // Setup current tags
        let tagsArr = [];
        if (Array.isArray(task.tags)) {
            tagsArr = [...task.tags];
        } else if (typeof task.tags === 'string' && task.tags.trim()) {
            try {
                const parsed = JSON.parse(task.tags);
                if (Array.isArray(parsed)) tagsArr = parsed;
                else tagsArr = task.tags.split(',').map(s => s.trim()).filter(Boolean);
            } catch(e) {
                tagsArr = task.tags.split(',').map(s => s.trim()).filter(Boolean);
            }
        }
        this.currentTags = tagsArr;
        this.renderTags();
        this.updateProjectMembersBar();

        if (this.quillDesc) {
            this.quillDesc.root.innerHTML = task.description || '';
        }

        // Load subtasks list
        const subtasksContainer = document.getElementById('tm-subtasks-list');
        subtasksContainer.innerHTML = '';
        
        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action_type=get_task&task_id=' + task.id
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.task) {
                if (data.task.assigned_users && Array.isArray(data.task.assigned_users)) {
                    this.currentAssignedUserIds = data.task.assigned_users.map(u => {
                        if (typeof u === 'object' && u !== null) return parseInt(u.id, 10);
                        return parseInt(u, 10);
                    }).filter(id => !isNaN(id) && id > 0);
                    this.renderAssignedUsers();
                    this.updateProjectMembersBar();
                }
                if (data.task.tags && Array.isArray(data.task.tags)) {
                    this.currentTags = [...data.task.tags];
                    this.renderTags();
                }
                if (data.task.subtasks_list) {
                    data.task.subtasks_list.forEach(st => {
                        const row = document.createElement('div');
                        row.className = 'lumio-subtask-row';
                        row.innerHTML = `
                            <input type="checkbox" class="lumio-subtask-check" ${st.is_completed ? 'checked' : ''} onchange="TM.toggleSubtask(${st.id}, this)">
                            <input type="text" class="lumio-subtask-input" value="${TM.escapeHtml(st.title)}" readonly>
                        `;
                        subtasksContainer.appendChild(row);
                    });
                }
            }
        });

        document.body.style.overflow = 'hidden';
        document.getElementById('tm-modal-task').style.display = 'flex';
    },

    openEditModalById: function(taskId) {
        const t = this.tasks.find(x => x.id === taskId);
        if (t) this.openEditModal(t);
    },

    closeModal: function(id) {
        document.body.style.overflow = '';
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
        if (id === 'tm-modal-task') {
            if (this.dpStartDate) try { this.dpStartDate.hide(); } catch(e) {}
            if (this.dpDueDate) try { this.dpDueDate.hide(); } catch(e) {}
            if (this.dpObjectiveDate) try { this.dpObjectiveDate.hide(); } catch(e) {}
        }
    },

    saveTask: function(e) {
        e.preventDefault();
        const taskId = document.getElementById('tm-task-id').value;
        const isEdit = Boolean(taskId);

        const title = document.getElementById('tm-title').value.trim();
        if (!title) return;

        // Collect any pending tag typed in input
        const tagNewInput = document.getElementById('tm-tag-new-input');
        if (tagNewInput && tagNewInput.value.trim()) {
            const extra = tagNewInput.value.split(',').map(s => s.trim()).filter(Boolean);
            extra.forEach(t => {
                if (!this.currentTags.some(x => x.toLowerCase() === t.toLowerCase())) {
                    this.currentTags.push(t);
                }
            });
            tagNewInput.value = '';
        }

        const descriptionHTML = this.quillDesc ? this.quillDesc.root.innerHTML : '';

        // Subtasks
        const subtasksContainer = document.getElementById('tm-subtasks-list');
        const subtaskInputs = Array.from(subtasksContainer.querySelectorAll('.lumio-subtask-input:not([readonly])'))
                                .map(input => input.value.trim()).filter(Boolean);

        const formData = new URLSearchParams();
        formData.append('action_type', isEdit ? 'update_task_details' : 'create_task');
        if (isEdit) formData.append('task_id', taskId);

        formData.append('title', title);
        formData.append('description', descriptionHTML);
        formData.append('priority', document.getElementById('tm-priority').value);
        formData.append('status', document.getElementById('tm-status').value);
        formData.append('frequency', document.getElementById('tm-frequency').value);
        formData.append('area', document.getElementById('tm-area').value);
        formData.append('project_id', document.getElementById('tm-project-id').value);
        formData.append('project_month_id', document.getElementById('tm-project-month-id').value);
        formData.append('brand_project_id', document.getElementById('tm-brand-project-id').value);
        formData.append('project_service_id', document.getElementById('tm-project-service-id') ? document.getElementById('tm-project-service-id').value : '');

        const isObj = document.getElementById('tm-is-daily-objective').checked ? 1 : 0;
        formData.append('is_daily_objective', isObj);
        formData.append('objective_date', document.getElementById('tm-objective-date').value);

        formData.append('start_date', document.getElementById('tm-start-date').value);
        formData.append('due_date', document.getElementById('tm-due-date').value);
        formData.append('assigned_users', JSON.stringify(this.currentAssignedUserIds));
        formData.append('tags', JSON.stringify(this.currentTags));

        if (isEdit) {
            formData.append('new_subtasks', JSON.stringify(subtaskInputs));
        } else {
            formData.append('subtasks', JSON.stringify(subtaskInputs));
        }

        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                this.closeModal('tm-modal-task');
                this.loadTasks();
                if (this.currentView === 'daily') {
                    this.renderDailyView();
                }
            } else {
                alert("Error: " + data.error);
            }
        });
    },

    deleteTask: function() {
        const taskId = document.getElementById('tm-task-id').value;
        if (!taskId || !confirm("¿Estás seguro de eliminar esta tarea permanentemente?")) return;

        const formData = new URLSearchParams();
        formData.append('action_type', 'delete_task');
        formData.append('task_id', taskId);

        fetch('modules/task_manager/ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                this.closeModal('tm-modal-task');
                this.loadTasks();
            } else alert(data.error);
        });
    },

    archiveTask: function() {
        const taskId = document.getElementById('tm-task-id').value;
        if (!taskId || !confirm("¿Archivar esta tarea? Ya no aparecerá en el tablero.")) return;

        const formData = new URLSearchParams();
        formData.append('action_type', 'update_status');
        formData.append('task_id', taskId);
        formData.append('status', 'archived');

        fetch('modules/task_manager/ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                this.closeModal('tm-modal-task');
                this.loadTasks();
            } else alert(data.error);
        });
    },

    // ══════════════════════════════════════════════════════
    // Evaluation Modal Operations
    // ══════════════════════════════════════════════════════
    openDailyEvaluationModal: function() {
        const dateInput = document.getElementById('tm-eval-date');
        if (dateInput) {
            dateInput.value = this.currentDailyDate;
        }
        this.loadDailyObjectivesForEval(this.currentDailyDate, this.currentDailyUser);
        document.getElementById('tm-modal-daily-eval').style.display = 'flex';
    },

    loadDailyObjectivesForEval: function(targetDate, targetUser) {
        const dateVal = targetDate || document.getElementById('tm-eval-date').value;
        const userVal = targetUser || (document.getElementById('tm-eval-user') ? document.getElementById('tm-eval-user').value : this.currentDailyUser);

        const params = new URLSearchParams();
        params.append('action_type', 'get_daily_objectives');
        params.append('date', dateVal);
        params.append('user_id', userVal);

        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params.toString()
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                document.getElementById('tm-eval-completed-count').textContent = `${data.completed} / ${data.total}`;
                document.getElementById('tm-eval-compliance-pct').textContent = Math.round(data.percentage) + '%';
                
                const badge = document.getElementById('tm-eval-level-badge');
                if (data.percentage >= 90) {
                    badge.className = 'tm-eval-level-badge badge-excellent';
                    badge.textContent = 'Sobresaliente';
                } else if (data.percentage >= 70) {
                    badge.className = 'tm-eval-level-badge badge-good';
                    badge.textContent = 'Bueno';
                } else if (data.percentage >= 40) {
                    badge.className = 'tm-eval-level-badge badge-average';
                    badge.textContent = 'Regular';
                } else {
                    badge.className = 'tm-eval-level-badge badge-poor';
                    badge.textContent = 'En Riesgo';
                }

                // Checklist
                const checkContainer = document.getElementById('tm-eval-checklist-items');
                checkContainer.innerHTML = '';
                if (!data.objectives || data.objectives.length === 0) {
                    checkContainer.innerHTML = `<p class="tm-empty-mini">No se registraron objetivos para este día.</p>`;
                } else {
                    data.objectives.forEach(task => {
                        const item = document.createElement('div');
                        item.className = `tm-eval-item-row ${task.is_completed ? 'is-done' : ''}`;
                        item.innerHTML = `
                            <label class="tm-custom-checkbox">
                                <input type="checkbox" ${task.is_completed ? 'checked' : ''} onchange="TM.toggleDailyCompletionInEval(${task.id}, this)">
                                <span class="tm-checkmark"></span>
                            </label>
                            <span>${TM.escapeHtml(task.title)}</span>
                        `;
                        checkContainer.appendChild(item);
                    });
                }

                // Fill evaluation form if exists
                if (data.evaluation) {
                    this.setEvalScore(data.evaluation.score || 3);
                    document.getElementById('tm-eval-notes').value = data.evaluation.evaluation_notes || '';
                } else {
                    const autoScore = data.percentage >= 90 ? 5 : (data.percentage >= 70 ? 4 : (data.percentage >= 40 ? 3 : 2));
                    this.setEvalScore(autoScore);
                    document.getElementById('tm-eval-notes').value = '';
                }
            }
        });
    },

    toggleDailyCompletionInEval: function(taskId, cbElem) {
        const formData = new URLSearchParams();
        formData.append('action_type', 'toggle_daily_objective');
        formData.append('task_id', taskId);
        formData.append('toggle_type', 'toggle_completion');

        fetch('modules/task_manager/ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                this.loadDailyObjectivesForEval();
                this.loadTasks();
            } else {
                cbElem.checked = !cbElem.checked;
            }
        });
    },

    setEvalScore: function(score) {
        document.getElementById('tm-eval-score-val').value = score;
        const stars = document.querySelectorAll('#tm-stars-container .tm-star');
        stars.forEach(s => {
            const rating = parseInt(s.dataset.rating, 10);
            s.classList.toggle('active', rating <= score);
        });
        const caption = document.getElementById('tm-stars-caption');
        if (caption) {
            const captions = ['1 de 5 estrellas (Bajo)', '2 de 5 estrellas (Mejorable)', '3 de 5 estrellas (Aceptable)', '4 de 5 estrellas (Muy Bueno)', '5 de 5 estrellas (Excelente)'];
            caption.textContent = captions[score - 1] || `${score} de 5 estrellas`;
        }
    },

    submitDailyEvaluation: function() {
        const dateVal = document.getElementById('tm-eval-date').value;
        const userVal = document.getElementById('tm-eval-user') ? document.getElementById('tm-eval-user').value : this.currentDailyUser;
        const score = document.getElementById('tm-eval-score-val').value;
        const notes = document.getElementById('tm-eval-notes').value.trim();

        const formData = new URLSearchParams();
        formData.append('action_type', 'save_daily_evaluation');
        formData.append('evaluation_date', dateVal);
        formData.append('user_id', userVal);
        formData.append('score', score);
        formData.append('evaluation_notes', notes);

        fetch('modules/task_manager/ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                alert("¡Evaluación diaria guardada con éxito!");
                this.closeModal('tm-modal-daily-eval');
                this.loadDailyObjectives(this.currentDailyDate, this.currentDailyUser);
                this.loadTasks();
            } else {
                alert("Error al guardar: " + res.error);
            }
        });
    },

    toggleEvalHistory: function() {
        const sec = document.getElementById('tm-eval-history-section');
        if (!sec) return;
        const isHidden = sec.style.display === 'none';
        sec.style.display = isHidden ? 'block' : 'none';

        if (isHidden) {
            const userVal = document.getElementById('tm-eval-user') ? document.getElementById('tm-eval-user').value : this.currentDailyUser;
            fetch('modules/task_manager/ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action_type=get_daily_evaluation_history&user_id=${userVal}`
            })
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('tm-history-tbody');
                tbody.innerHTML = '';
                if (data.success && data.history) {
                    data.history.forEach(h => {
                        let stars = '';
                        for (let s = 1; s <= 5; s++) {
                            stars += s <= h.score
                                ? '<i class="ph-fill ph-star" style="color:#f59e0b; margin-right:1px;"></i>'
                                : '<i class="ph ph-star" style="color:var(--text-muted); opacity:0.35; margin-right:1px;"></i>';
                        }
                        tr.innerHTML = `
                            <td><strong>${h.evaluation_date}</strong></td>
                            <td>${this.escapeHtml(h.user_name)}</td>
                            <td>${h.completed_objectives}/${h.total_objectives}</td>
                            <td><span class="tm-badge tm-badge-weekly">${Math.round(h.compliance_percentage)}%</span></td>
                            <td>${stars}</td>
                            <td style="font-size:0.8rem; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${this.escapeHtml(h.evaluation_notes || '-')}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            });
        }
    },

    // ══════════════════════════════════════════════════════
    // Subtasks & Filter operations
    // ══════════════════════════════════════════════════════
    addSubtaskInput: function() {
        const container = document.getElementById('tm-subtasks-list');
        if (!container) return;
        const rowId = 'st-' + Date.now();
        const row = document.createElement('div');
        row.className = 'lumio-subtask-row';
        row.id = rowId;
        row.innerHTML = `
            <input type="checkbox" class="lumio-subtask-check" disabled>
            <input type="text" class="lumio-subtask-input" placeholder="Escribe una subtarea o meta específica...">
            <button type="button" class="lumio-subtask-del" onclick="TM.removeSubtaskInput('${rowId}')"><i class="ph ph-trash"></i></button>
        `;
        container.appendChild(row);
        row.querySelector('.lumio-subtask-input').focus();
    },

    removeSubtaskInput: function(rowId) {
        const r = document.getElementById(rowId);
        if (r) r.remove();
    },

    toggleSubtask: function(subtaskId, cbElem) {
        const formData = new URLSearchParams();
        formData.append('action_type', 'toggle_subtask');
        formData.append('subtask_id', subtaskId);

        fetch('modules/task_manager/ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(!res.success) cbElem.checked = !cbElem.checked;
        });
    },

    setFilterUser: function(userVal) {
        this.filterUser = userVal;
        this.currentDailyUser = userVal === 'all' || userVal === 'me' ? (window.TM_USER_ID || 1) : userVal;
        this.loadTasks();
    },

    setFilterProject: function(projVal) {
        this.filterProject = projVal;
        this.renderCurrentView();
    },

    setFilterArea: function(areaVal, btnElem) {
        this.filterArea = areaVal;
        document.querySelectorAll('.tm-pills-bar .tm-pill-btn:not(.freq-btn)').forEach(b => b.classList.remove('active'));
        if (btnElem) btnElem.classList.add('active');
        this.loadTasks();
    },

    setFilterFrequency: function(freqVal, btnElem) {
        if (this.filterFrequency === freqVal) {
            this.filterFrequency = 'all';
            btnElem.classList.remove('active');
        } else {
            this.filterFrequency = freqVal;
            document.querySelectorAll('.tm-pills-bar .freq-btn').forEach(b => b.classList.remove('active'));
            btnElem.classList.add('active');
        }
        this.loadTasks();
    },

    switchTab: function(btn) {
        const tabsNav = btn.closest('.lumio-tabs-nav');
        const body = btn.closest('.lumio-body');
        const tabIndex = btn.dataset.tab;
        
        tabsNav.querySelectorAll('.lumio-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        
        body.querySelectorAll('.lumio-tab-panel').forEach(panel => {
            if (panel.dataset.panel === tabIndex) {
                panel.style.display = 'block';
                panel.classList.add('active');
            } else {
                panel.style.display = 'none';
                panel.classList.remove('active');
            }
        });
    },

    // ══════════════════════════════════════════════════════
    // Drag and Drop Logic
    // ══════════════════════════════════════════════════════
    dragStart: function(e) {
        this.draggedElement = e.currentTarget;
        e.dataTransfer.effectAllowed = 'move';
        const target = e.currentTarget;
        setTimeout(() => { if(target) target.style.opacity = '0.5'; }, 0);
    },
    
    dragEnd: function(e) {
        e.currentTarget.style.opacity = '1';
        this.draggedElement = null;
    },

    dragOver: function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        e.currentTarget.classList.add('drag-over');
    },

    dragLeave: function(e) {
        e.currentTarget.classList.remove('drag-over');
    },

    drop: function(e) {
        e.preventDefault();
        const colBody = e.currentTarget;
        colBody.classList.remove('drag-over');
        if(!this.draggedElement) return;

        const taskId = this.draggedElement.dataset.id;
        const newStatus = colBody.dataset.status;
        const currentStatus = this.draggedElement.dataset.status;

        if(newStatus === currentStatus || (newStatus === 'pending' && currentStatus === 'overdue')) {
            return;
        }

        if(newStatus === 'approved' && !window.TM_IS_ADMIN) {
            alert("Acceso denegado: Solo los administradores pueden aprobar tareas.");
            return;
        }

        // Optimistic UI move
        colBody.appendChild(this.draggedElement);
        this.draggedElement.dataset.status = newStatus;
        if(newStatus !== 'pending' && newStatus !== 'new') {
            this.draggedElement.classList.remove('is-overdue');
        }

        const formData = new URLSearchParams();
        formData.append('action_type', 'update_status');
        formData.append('task_id', taskId);
        formData.append('status', newStatus);

        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData.toString()
        })
        .then(r => r.json())
        .then(data => {
            if(!data.success) {
                alert(data.error || "Error al mover la tarea");
                this.loadTasks();
            } else {
                if (data.completion_notice) {
                    const notice = data.completion_notice;
                    if (confirm(notice.message + "\n\n¿Deseas abrir la tarea para actualizar la fase del proyecto ahora?")) {
                        this.openEditModalById(parseInt(taskId, 10));
                    }
                }
                this.loadTasks();
            }
        });
    },

    escapeHtml: function(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    TM.init();
});

