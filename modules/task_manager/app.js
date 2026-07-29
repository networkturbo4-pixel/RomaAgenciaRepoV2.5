// modules/task_manager/app.js
const TM = {
    tasks: [],
    
    init: function() {
        this.loadTasks();
    },

    loadTasks: function() {
        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action_type=get_all_tasks'
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                this.tasks = data.tasks;
                this.updateKPIs(data.stats);
                this.renderKanban();
            } else {
                console.error(data.error);
            }
        });
    },

    updateKPIs: function(stats) {
        document.getElementById('kpi-new').innerText = stats.new || 0;
        document.getElementById('kpi-pending').innerText = stats.pending || 0;
        document.getElementById('kpi-overdue').innerText = stats.overdue || 0;
        document.getElementById('kpi-completed').innerText = stats.completed || 0;
        
        document.getElementById('count-new').innerText = stats.new || 0;
        document.getElementById('count-pending').innerText = (stats.pending || 0) + (stats.overdue || 0);
        document.getElementById('count-completed').innerText = stats.completed || 0;
        document.getElementById('count-approved').innerText = stats.approved || 0;
    },

    renderKanban: function() {
        // Limpiar columnas
        ['new', 'pending', 'completed', 'approved'].forEach(status => {
            document.getElementById(`col-${status}`).innerHTML = '';
        });

        this.tasks.forEach(t => {
            let colStatus = t.status;
            if(colStatus === 'overdue') colStatus = 'pending'; // Overdue va en la columna de pendientes visualmente
            
            const col = document.getElementById(`col-${colStatus}`);
            if(!col) return;

            const card = document.createElement('div');
            card.className = `tm-task-card ${t.status === 'overdue' ? 'is-overdue' : ''}`;
            card.draggable = true;
            card.id = `tm-task-${t.id}`;
            card.dataset.id = t.id;
            card.dataset.status = t.status;

            card.addEventListener('dragstart', this.dragStart.bind(this));
            card.addEventListener('dragend', this.dragEnd.bind(this));
            card.addEventListener('click', () => this.openEditModal(t));

            // Badges HTML
            let badgesHtml = `<span class="tm-badge tm-badge-priority-${t.priority}">${t.priority}</span>`;
            if(t.status === 'overdue') {
                badgesHtml += `<span class="tm-badge tm-badge-overdue">🚨 RETRASADO</span>`;
            }
            if(t.tags && t.tags.length > 0) {
                t.tags.forEach(tag => {
                    badgesHtml += `<span class="tm-badge" style="background:#e2e8f0;color:#475569;">${tag}</span>`;
                });
            }

            // Users HTML
            let usersHtml = '';
            if(t.assigned_users && t.assigned_users.length > 0) {
                usersHtml = `<div class="tm-task-users">`;
                t.assigned_users.forEach(u => {
                    if(u.avatar) {
                        usersHtml += `<div class="tm-task-user" title="${u.name}"><img src="${u.avatar}"></div>`;
                    } else {
                        usersHtml += `<div class="tm-task-user" title="${u.name}">${u.initial}</div>`;
                    }
                });
                usersHtml += `</div>`;
            }

            // Fechas HTML
            let dateHtml = '';
            if(t.due_date) {
                dateHtml = `<div class="tm-task-date"><i class="ph ph-calendar-blank"></i> ${new Date(t.due_date).toLocaleDateString()}</div>`;
            }

            card.innerHTML = `
                <div class="tm-task-badges">${badgesHtml}</div>
                <h4 class="tm-task-title">${t.title}</h4>
                ${dateHtml}
                ${usersHtml}
            `;
            col.appendChild(card);
        });
    },

    openCreateModal: function() {
        document.getElementById('tm-modal-create').style.display = 'flex';
        this.initTagify('tm-assigned-users', 'tagifyCreateUsers');
    },

    initTagify: function(inputId, windowRefName) {
        if (typeof Tagify === 'undefined' || window[windowRefName]) return;
        const input = document.getElementById(inputId);
        if (input) {
            window[windowRefName] = new Tagify(input, {
                whitelist: window.TM_USERS || [],
                enforceWhitelist: true,
                dropdown: {
                    enabled: 0,
                    maxItems: 20,
                    classname: "tags-look",
                    searchKeys: ["value"],
                    appendTarget: input.closest('.tm-modal-overlay') || document.body
                }
            });
        }
    },

    closeModal: function(id) {
        document.getElementById(id).style.display = 'none';
    },

    submitTask: function(e) {
        e.preventDefault();
        
        const tagsInput = document.getElementById('tm-tags').value;
        const tags = tagsInput ? tagsInput.split(',').map(t => t.trim()) : [];
        
        // Parse Tagify users
        let selUsers = [];
        const assignedUsersInput = document.getElementById('tm-assigned-users').value;
        if (assignedUsersInput) {
            try { selUsers = JSON.parse(assignedUsersInput).map(u => u.id); } catch(e){}
        }

        const selRoles = []; // We removed roles, but keeping array to not break backend

        const formData = new URLSearchParams();
        formData.append('action_type', 'create_task');
        const descriptionHTML = window.quillCreateDesc ? window.quillCreateDesc.root.innerHTML : document.getElementById('tm-desc').value;

        // Gather subtasks
        const subtasksContainer = document.getElementById('tm-subtasks-list');
        const subtaskInputs = Array.from(subtasksContainer.querySelectorAll('.lumio-subtask-input')).map(input => input.value.trim()).filter(v => v);

        formData.append('title', document.getElementById('tm-title').value);
        formData.append('description', descriptionHTML);
        formData.append('subtasks', JSON.stringify(subtaskInputs));
        formData.append('priority', document.getElementById('tm-priority').value);
        formData.append('status', document.getElementById('tm-status').value);
        formData.append('start_date', document.getElementById('tm-start-date').value);
        formData.append('due_date', document.getElementById('tm-due-date').value);
        formData.append('assigned_users', JSON.stringify(selUsers));
        formData.append('assigned_roles', JSON.stringify(selRoles));
        formData.append('tags', JSON.stringify(tags));

        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData.toString()
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                this.closeModal('tm-modal-create');
                document.getElementById('form-create-task').reset();
                if (window.tagifyCreateUsers) window.tagifyCreateUsers.removeAllTags();
                if (window.quillCreateDesc) window.quillCreateDesc.root.innerHTML = '';
                document.getElementById('tm-subtasks-list').innerHTML = '';
                this.loadTasks(); // recargar
            } else {
                alert("Error: " + data.error);
            }
        });
    },

    openEditModal: function(task) {
        document.getElementById('tm-edit-id').value = task.id;
        document.getElementById('tm-edit-id-badge').textContent = '#' + task.id;
        document.getElementById('tm-edit-title').value = task.title || '';
        
        // Load description into Quill
        if (window.quillEditDesc) {
            window.quillEditDesc.root.innerHTML = task.description || '';
        } else {
            document.getElementById('tm-edit-desc').value = task.description || '';
        }
        document.getElementById('tm-edit-priority').value = task.priority || 'medium';
        document.getElementById('tm-edit-status').value = task.status || 'new';
        document.getElementById('tm-edit-start-date').value = task.start_date ? task.start_date.substring(0, 16) : '';
        document.getElementById('tm-edit-due-date').value = task.due_date ? task.due_date.substring(0, 16) : '';
        document.getElementById('tm-edit-tags').value = (task.tags || []).join(', ');

        // We initialize tagify here after display flex to ensure layout is computed
        this.initTagify('tm-edit-assigned-users', 'tagifyEditUsers');

        // Load assigned users into Tagify
        if (window.tagifyEditUsers) {
            window.tagifyEditUsers.removeAllTags();
            if (task.assigned_users && task.assigned_users.length > 0) {
                const tagsToAdd = task.assigned_users.map(u => ({ id: u.id, value: u.name }));
                window.tagifyEditUsers.addTags(tagsToAdd);
            }
        } else {
            // Fallback just in case Tagify isn't loaded
            document.getElementById('tm-edit-assigned-users').value = '';
        }

        // Render existing subtasks in edit modal (Visual mapping only for now, full sync requires backend array)
        const subtasksList = document.getElementById('tm-edit-subtasks-list');
        if (subtasksList) {
            subtasksList.innerHTML = ''; // Limpiar
            if (task.subtasks_list && task.subtasks_list.length > 0) {
                task.subtasks_list.forEach(st => {
                    const row = document.createElement('div');
                    row.className = 'lumio-subtask-row';
                    row.innerHTML = `
                        <input type="checkbox" class="lumio-subtask-check" ${st.is_completed ? 'checked' : ''} disabled>
                        <input type="text" class="lumio-subtask-input" value="${st.title}" readonly>
                    `;
                    subtasksList.appendChild(row);
                });
            }
        }
        // Roles is tricky since get_all_tasks does not return assigned_roles right now,
        // but for a full implementation we would need to fetch the task details.
        // For now we will fetch details from ajax to populate roles correctly.
        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action_type=get_task&task_id=' + task.id
        })
        .then(r => r.json())
        .then(data => {
            if(data.success && data.task) {
                const selRoles = document.getElementById('tm-edit-assigned-roles');
                const roleIds = (data.task.assigned_roles || []).map(String);
                Array.from(selRoles.options).forEach(opt => opt.selected = roleIds.includes(opt.value));
            }
            document.getElementById('tm-modal-edit').style.display = 'flex';
        });
    },

    deleteTask: function() {
        if(!confirm("¿Estás seguro de eliminar esta tarea? Esta acción no se puede deshacer.")) return;
        const taskId = document.getElementById('tm-edit-id').value;
        const formData = new URLSearchParams();
        formData.append('action_type', 'delete_task');
        formData.append('task_id', taskId);
        fetch('modules/task_manager/ajax.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(data => {
            if(data.success) {
                this.closeModal('tm-modal-edit');
                this.loadTasks();
            } else alert(data.error);
        });
    },

    archiveTask: function() {
        if(!confirm("¿Archivar esta tarea? Ya no aparecerá en el tablero.")) return;
        const taskId = document.getElementById('tm-edit-id').value;
        const formData = new URLSearchParams();
        formData.append('action_type', 'update_status');
        formData.append('task_id', taskId);
        formData.append('status', 'archived');
        fetch('modules/task_manager/ajax.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(data => {
            if(data.success) {
                this.closeModal('tm-modal-edit');
                this.loadTasks();
            } else alert(data.error);
        });
    },

    submitEditTask: function(e) {
        e.preventDefault();
        
        // Parse Tagify users
        let selUsers = [];
        const editUsersInput = document.getElementById('tm-edit-assigned-users').value;
        if (editUsersInput) {
            try { selUsers = JSON.parse(editUsersInput).map(u => u.id); } catch(e){}
        }
        
        const selRoles = [];

        const tagsInput = document.getElementById('tm-edit-tags').value;
        const tags = tagsInput ? tagsInput.split(',').map(t => t.trim()) : [];

        const descriptionHTML = window.quillEditDesc ? window.quillEditDesc.root.innerHTML : document.getElementById('tm-edit-desc').value;
        
        // Subtasks for Edit (we just collect new ones if any were added, or overwrite completely depending on backend design)
        const editSubtasksContainer = document.getElementById('tm-edit-subtasks-list');
        const editSubtaskInputs = Array.from(editSubtasksContainer.querySelectorAll('.lumio-subtask-input:not([readonly])')).map(input => input.value.trim()).filter(v => v);

        const formData = new URLSearchParams();
        formData.append('action_type', 'update_task_details');
        formData.append('task_id', document.getElementById('tm-edit-id').value);
        formData.append('title', document.getElementById('tm-edit-title').value);
        formData.append('description', descriptionHTML);
        formData.append('new_subtasks', JSON.stringify(editSubtaskInputs));
        formData.append('priority', document.getElementById('tm-edit-priority').value);
        formData.append('status', document.getElementById('tm-edit-status').value);
        formData.append('start_date', document.getElementById('tm-edit-start-date').value);
        formData.append('due_date', document.getElementById('tm-edit-due-date').value);
        formData.append('assigned_users', JSON.stringify(selUsers));
        formData.append('assigned_roles', JSON.stringify(selRoles));
        formData.append('tags', JSON.stringify(tags));

        fetch('modules/task_manager/ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData.toString()
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                this.closeModal('tm-modal-edit');
                this.loadTasks();
            } else {
                alert("Error: " + data.error);
            }
        });
    },

    // --- Drag & Drop ---
    draggedElement: null,

    // --- Tab Switching ---
    switchTab: function(btn) {
        const tabsNav = btn.closest('.lumio-tabs-nav');
        const body = btn.closest('.lumio-body');
        const tabIndex = btn.dataset.tab;
        
        // Update active tab button
        tabsNav.querySelectorAll('.lumio-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        
        // Show/hide corresponding panels
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

    // --- Subtasks Logic ---
    addSubtaskInput: function(modalType) {
        const containerId = modalType === 'create' ? 'tm-subtasks-list' : 'tm-edit-subtasks-list';
        const container = document.getElementById(containerId);
        if (!container) return;
        
        const rowId = 'st-' + Date.now();
        const row = document.createElement('div');
        row.className = 'lumio-subtask-row';
        row.id = rowId;
        
        row.innerHTML = `
            <input type="checkbox" class="lumio-subtask-check" disabled>
            <input type="text" class="lumio-subtask-input" placeholder="Escribe una subtarea...">
            <button type="button" class="lumio-subtask-del" onclick="TM.removeSubtaskInput('${rowId}')">
                <i class="ph ph-trash"></i>
            </button>
        `;
        container.appendChild(row);
        row.querySelector('.lumio-subtask-input').focus();
    },

    removeSubtaskInput: function(rowId) {
        const row = document.getElementById(rowId);
        if (row) row.remove();
    },

    dragStart: function(e) {
        this.draggedElement = e.currentTarget;
        e.dataTransfer.effectAllowed = 'move';
        const target = e.currentTarget;
        setTimeout(() => {
            if(target) target.style.opacity = '0.5';
        }, 0);
    },
    
    dragEnd: function(e) {
        e.currentTarget.style.opacity = '1';
        this.draggedElement = null;
    },

    dragOver: function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const colBody = e.currentTarget;
        colBody.classList.add('drag-over');
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
            // si cae en la misma (pending engloba overdue)
            return;
        }

        // Si mueve a aprobado, checar si es admin (optimistic check)
        if(newStatus === 'approved' && !window.TM_IS_ADMIN) {
            alert("Acceso denegado: Solo los administradores pueden aprobar tareas.");
            return;
        }

        // Optimistic UI Update
        colBody.appendChild(this.draggedElement);
        this.draggedElement.dataset.status = newStatus;
        if(newStatus !== 'pending' && newStatus !== 'new') {
            this.draggedElement.classList.remove('is-overdue'); // remove overdue visually if completed/approved
        }

        // Ajax call
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
                this.loadTasks(); // revertir si hay error
            } else {
                this.loadTasks(); // reload to get updated stats
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    TM.init();
    
    // Initialize AirDatepicker on the custom date inputs
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
    
    if (typeof AirDatepicker !== 'undefined') {
        new AirDatepicker('#tm-start-date', dpConfig);
        new AirDatepicker('#tm-due-date', dpConfig);
        new AirDatepicker('#tm-edit-start-date', dpConfig);
        new AirDatepicker('#tm-edit-due-date', dpConfig);
    }
    
    // Initialize Quill Editors for Task Descriptions
    try {
        if (typeof Quill !== 'undefined') {
            const quillOptions = {
                theme: 'snow',
                placeholder: 'Añade una descripción detallada...',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['link', 'clean']
                    ]
                }
            };
            
            if (document.getElementById('tm-desc-editor')) {
                window.quillCreateDesc = new Quill('#tm-desc-editor', quillOptions);
            }
            if (document.getElementById('tm-edit-desc-editor')) {
                window.quillEditDesc = new Quill('#tm-edit-desc-editor', quillOptions);
            }
        }
    } catch(e) { console.warn('Quill init:', e); }
});
