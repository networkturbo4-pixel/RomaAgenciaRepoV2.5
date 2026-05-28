
    function getContrastColor(hexColor) {
        if(!hexColor) return '#000000';
        if(hexColor.startsWith('#')) hexColor = hexColor.substring(1);
        if(hexColor.length === 3) hexColor = hexColor.split('').map(c => c+c).join('');
        let r = parseInt(hexColor.substr(0, 2), 16);
        let g = parseInt(hexColor.substr(2, 2), 16);
        let b = parseInt(hexColor.substr(4, 2), 16);
        let yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
        return (yiq >= 128) ? '#000000' : '#ffffff';
    }
    let allTasks = [];
    let systemUsers = [];
    let calendar;
    let tomSelectAssign;
    let tomSelectTags;
    let subtaskIndex = 0;
    let foldersGenerated = false;
    let quillEditor;

    let localMainReferences = [];
    let localSubtaskReferences = {};

    const currentUserId = 1;
    const isAdmin = 1;

    document.addEventListener('DOMContentLoaded', () => {
        Fancybox.bind("[data-fancybox]", {});

        // Initialize TomSelect
        tomSelectAssign = new TomSelect('#task-assign', {
            plugins: ['remove_button'],
            placeholder: 'Seleccionar...',
        });

        tomSelectTags = new TomSelect('#task-tags', {
            plugins: ['remove_button'],
            create: true,
            persist: false,
            placeholder: 'Etiquetas (ej: Logo, Web)...'
        });

        // Global Drag & Drop over offcanvas
        const ocPanel = document.getElementById('task-offcanvas');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            ocPanel.addEventListener(eventName, preventDefaults, false);
        });
        function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            ocPanel.addEventListener(eventName, () => {
                if (foldersGenerated) ocPanel.style.boxShadow = 'inset 0 0 0 3px var(--primary-color)';
            }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            ocPanel.addEventListener(eventName, () => {
                ocPanel.style.boxShadow = 'none';
            }, false);
        });
        
        ocPanel.addEventListener('drop', (e) => {
            if (!foldersGenerated) return;
            let dt = e.dataTransfer;
            let files = dt.files;
            if (files.length > 0) {
                const inp = document.getElementById('inp-main-ref');
                const dTrans = new DataTransfer();
                for(let i=0; i<inp.files.length; i++) dTrans.items.add(inp.files[i]);
                for(let i=0; i<files.length; i++) dTrans.items.add(files[i]);
                inp.files = dTrans.files;
                handleLocalFiles(inp.files, 'main');
                switchOcTab('details', document.querySelector('.oc-nav-item')); 
            }
        }, false);

        // Initialize Quill Editor
        quillEditor = new Quill('#task-desc-editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'header': [1, 2, 3, false] }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link', 'clean']
                ]
            },
            placeholder: 'Instrucciones generales, detalles y especificaciones...'
        });

        // Title input listener to enable/disable generate folders button
        document.getElementById('task-title').addEventListener('input', function(e) {
            const btn = document.getElementById('generate-btn-element');
            if (e.target.value.trim() !== '') {
                btn.disabled = false;
                btn.title = '';
            } else {
                btn.disabled = true;
                btn.title = 'Debes ingresar un título para generar las carpetas';
            }
        });

        fetchUsers().then(() => {
            fetchTasks();
        });
        initSortable();
        initCalendar();

        document.addEventListener('paste', handlePaste);
        document.getElementById('inp-main-ref').addEventListener('change', function(e) {
            handleLocalFiles(e.target.files, 'main');
        });
    });

    function toggleViewFormBtn(selectEl) {
        const btn = document.getElementById('btn-view-form');
        if (selectEl.value) {
            btn.style.display = 'inline-flex';
        } else {
            btn.style.display = 'none';
        }
    }

    function openLinkedForm() {
        const selectEl = document.getElementById('task-linked-form');
        const selectedOption = selectEl.options[selectEl.selectedIndex];
        if (!selectedOption || !selectedOption.value) return;

        const submissionId = selectedOption.value;
        const title = selectedOption.text;

        if (submissionId) {
            const iframe = document.getElementById('dt-form-iframe');
            iframe.src = `modules/forms/view_submission.php?id=${submissionId}&mode=iframe`;
            document.getElementById('dt-form-modal-title').textContent = title;
            document.getElementById('dt-form-modal').classList.add('active');
        }
    }

    function handlePaste(e) {
        if (!document.getElementById('task-offcanvas').classList.contains('active')) return;
        if (!foldersGenerated) return; // Prevent paste if folders aren't generated

        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        let files = [];
        for (let index in items) {
            const item = items[index];
            if (item.kind === 'file' && item.type.startsWith('image/')) {
                const blob = item.getAsFile();
                const file = new File([blob], "Pasted_Image_" + Date.now() + ".png", { type: item.type });
                files.push(file);
            }
        }
        
        if (files.length > 0) {
            e.preventDefault();
            const activeEl = document.activeElement;
            let targetSubtaskId = null;
            if (activeEl && activeEl.closest('.st-card')) {
                const stCard = activeEl.closest('.st-card');
                const fileInp = stCard.querySelector('.st-file-inp');
                if (fileInp) {
                    const match = fileInp.name.match(/st_files_(\d+)/);
                    if (match) targetSubtaskId = parseInt(match[1]);
                }
            }

            if (document.getElementById('tab-avances').classList.contains('active')) {
                uploadAvance(files[0]);
            } else if (targetSubtaskId !== null) {
                handleLocalFiles(files, 'subtask', targetSubtaskId);
            } else {
                handleLocalFiles(files, 'main');
                switchOcTab('details', document.querySelectorAll('.oc-nav-item')[0]);
            }
        }
    }

    async function uploadAvance(file) {
        const driveFolderId = document.getElementById('task-drive-folder').value;
        const taskId = document.getElementById('task-id').value;
        if (!driveFolderId) {
            alert('Debes generar la estructura de carpetas en la pestaña "Archivos" antes de subir avances.');
            return;
        }

        const progressEl = document.getElementById('avance-upload-progress');
        progressEl.style.display = 'block';

        try {
            const fd = new FormData();
            fd.append('action', 'upload_avance');
            fd.append('task_id', taskId);
            fd.append('drive_folder_id', driveFolderId);
            fd.append('avance_image', file);

            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                if (!taskId) {
                    // Pending attachment to be saved when task is saved
                    const hiddenInp = document.createElement('input');
                    hiddenInp.type = 'hidden';
                    hiddenInp.name = 'pending_avances[]';
                    hiddenInp.value = JSON.stringify(data.attachment);
                    document.getElementById('task-form').appendChild(hiddenInp);
                }
                renderAvance(data.attachment);
            } else {
                alert(data.error || 'Error al subir avance');
            }
        } catch(e) {
            console.error(e);
            alert('Error de red');
        } finally {
            progressEl.style.display = 'none';
        }
    }

    function renderAvance(att) {
        const gallery = document.getElementById('avances-gallery');
        document.getElementById('avances-empty').style.display = 'none';
        gallery.innerHTML += renderAvanceCard(att);
    }

    function handleLocalFiles(files, type, subtaskId = null) {
        const dataTransfer = new DataTransfer();
        
        if (type === 'main') {
            for(let f of files) localMainReferences.push(f);
            localMainReferences.forEach(f => dataTransfer.items.add(f));
            document.getElementById('inp-main-ref').files = dataTransfer.files;
            renderLocalPreviews('main');
        } else {
            if (!localSubtaskReferences[subtaskId]) localSubtaskReferences[subtaskId] = [];
            for(let f of files) localSubtaskReferences[subtaskId].push(f);
            localSubtaskReferences[subtaskId].forEach(f => dataTransfer.items.add(f));
            const inp = document.querySelector(`input[name="st_files_${subtaskId}[]"]`);
            if (inp) inp.files = dataTransfer.files;
            renderLocalPreviews('subtask', subtaskId);
        }
    }

    function removeLocalFile(index, type, subtaskId = null) {
        const dataTransfer = new DataTransfer();
        if (type === 'main') {
            localMainReferences.splice(index, 1);
            localMainReferences.forEach(f => dataTransfer.items.add(f));
            document.getElementById('inp-main-ref').files = dataTransfer.files;
            renderLocalPreviews('main');
        } else {
            localSubtaskReferences[subtaskId].splice(index, 1);
            localSubtaskReferences[subtaskId].forEach(f => dataTransfer.items.add(f));
            const inp = document.querySelector(`input[name="st_files_${subtaskId}[]"]`);
            if (inp) inp.files = dataTransfer.files;
            renderLocalPreviews('subtask', subtaskId);
        }
    }

    function renderLocalPreviews(type, subtaskId = null) {
        let container, files;
        if (type === 'main') {
            container = document.getElementById('ref-images-container');
            files = localMainReferences;
        } else {
            container = document.getElementById(`st-local-previews-${subtaskId}`);
            files = localSubtaskReferences[subtaskId] || [];
        }

        let localContainer = container.querySelector('.local-previews-grid');
        if (!localContainer) {
            localContainer = document.createElement('div');
            localContainer.className = 'thumb-grid local-previews-grid';
            localContainer.style.marginTop = '0.5rem';
            container.appendChild(localContainer);
        }
        
        localContainer.innerHTML = '';
        files.forEach((file, index) => {
            const url = URL.createObjectURL(file);
            localContainer.innerHTML += `
                <div class="thumb-item">
                    <a href="${url}" data-fancybox="gallery">
                        <img src="${url}" alt="Preview">
                    </a>
                    <button type="button" class="thumb-btn-del" onclick="removeLocalFile(${index}, '${type}', ${subtaskId})"><i class="ph ph-x"></i></button>
                </div>
            `;
        });
    }

    async function fetchUsers() {
        try {
            const res = await fetch('modules/design_tasks/ajax.php?action=fetch_users');
            const data = await res.json();
            if(data.success) {
                systemUsers = data.data;
                systemUsers.forEach(u => {
                    tomSelectAssign.addOption({value: u.id, text: u.name});
                });
            }
        } catch(e) { console.error(e); }
    }

    async function fetchTasks() {
        try {
            const res = await fetch('modules/design_tasks/ajax.php?action=fetch_tasks');
            const data = await res.json();
            if(data.success) {
                allTasks = data.data;
                renderKanban();
                renderCalendar();
            }
        } catch(e) { console.error(e); }
    }

    function switchView(view) {
        document.getElementById('tab-kanban').classList.remove('active');
        document.getElementById('tab-calendar').classList.remove('active');
        document.getElementById('tab-list').classList.remove('active');
        document.getElementById('tab-trash').classList.remove('active');
        
        document.getElementById('dt-kanban-view').style.display = 'none';
        document.getElementById('dt-calendar-view').style.display = 'none';
        document.getElementById('dt-list-view').style.display = 'none';
        document.getElementById('dt-trash-view').style.display = 'none';

        if (view === 'kanban') {
            document.getElementById('tab-kanban').classList.add('active');
            document.getElementById('dt-kanban-view').style.display = 'flex';
        } else if (view === 'list') {
            document.getElementById('tab-list').classList.add('active');
            document.getElementById('dt-list-view').style.display = 'block';
        } else if (view === 'trash') {
            document.getElementById('tab-trash').classList.add('active');
            document.getElementById('dt-trash-view').style.display = 'block';
            fetchTrash();
        } else {
            document.getElementById('tab-calendar').classList.add('active');
            document.getElementById('dt-calendar-view').style.display = 'block';
            calendar.render(); 
        }
    }

    function renderKanban() {
        const cols = ['Pendiente', 'En progreso', 'En revisión', 'Terminado'];
        cols.forEach(status => {
            document.getElementById('col-' + status).innerHTML = '';
            document.getElementById('count-' + status).innerText = '0';
        });

        const counts = {'Pendiente':0, 'En progreso':0, 'En revisión':0, 'Terminado':0};

        allTasks.forEach(task => {
            if (counts[task.status] !== undefined) {
                const el = document.createElement('div');
                el.className = 'dt-task';
                el.dataset.id = task.id;
                
                // Urgent Task Check
                let isUrgent = false;
                if (task.due_date && task.status !== 'Terminado') {
                    const dueTime = new Date(task.due_date).getTime();
                    const now = new Date().getTime();
                    const hoursLeft = (dueTime - now) / (1000 * 60 * 60);
                    if (hoursLeft > 0 && hoursLeft <= 2) {
                        isUrgent = true;
                    } else if (hoursLeft < 0) {
                        isUrgent = true; // Overdue is also urgent
                    }
                }
                if (isUrgent) el.classList.add('task-urgent');
                
                // Cover Image Check
                let coverHtml = '';
                const refs = task.attachments.filter(a => a.attachment_type === 'reference');
                // Find the latest reference that is an image
                const imageRefs = refs.filter(a => {
                    let ext = (a.file_name || '').split('.').pop().toLowerCase();
                    return ['jpg','jpeg','png','gif','webp'].includes(ext);
                });
                
                if (imageRefs.length > 0) {
                    const latestRef = imageRefs[imageRefs.length - 1];
                    const fileId = getDriveFileId(latestRef.file_path);
                    if (fileId) {
                        coverHtml = `<div style="width:100%; aspect-ratio: 16/9; background-image: url('https://drive.google.com/thumbnail?id=${fileId}&sz=w800'); background-size: cover; background-position: center;"></div>`;
                    }
                }
                
                let avatarsHtml = '<div style="display:flex;">';
                let listAvatarsHtml = '<div style="display:flex;">';
                if(task.assigned_to && task.assigned_to.length > 0) {
                    task.assigned_to.forEach((uid, idx) => {
                        const u = systemUsers.find(x => x.id == uid);
                        if(u) {
                            const initial = u.name.charAt(0).toUpperCase();
                            const ml = idx > 0 ? '-10px' : '0';
                            
                            // Check if avatar image exists, otherwise use initials
                            let avatarContent = `<div style="width:24px; height:24px; border-radius:50%; background:var(--primary-color); color:white; display:flex; align-items:center; justify-content:center; font-size:10px; border:2px solid var(--bg-surface); margin-left:${ml};" title="${u.name}">${initial}</div>`;
                            if (u.avatar && u.avatar.trim() !== '') {
                                avatarContent = `<img src="${u.avatar}" style="width:24px; height:24px; border-radius:50%; object-fit:cover; border:2px solid var(--bg-surface); margin-left:${ml};" title="${u.name}">`;
                            }
                            
                            avatarsHtml += avatarContent;
                            listAvatarsHtml += avatarContent;
                        }
                    });
                } else {
                    avatarsHtml += '<span style="font-size:10px; color:var(--text-muted);">Sin asignar</span>';
                    listAvatarsHtml += '<span style="font-size:10px; color:var(--text-muted);">Sin asignar</span>';
                }
                avatarsHtml += '</div>';
                listAvatarsHtml += '</div>';

                const designAtts = task.attachments.filter(a => a.attachment_type === 'design');
                let attachmentsIcon = designAtts.length > 0 ? `<i class="ph ph-paperclip"></i> ${designAtts.length}` : '';
                
                let subtasksInfo = '';
                if(task.subtasks.length > 0) {
                    const completed = task.subtasks.filter(s => s.is_completed == 1).length;
                    const percent = Math.round((completed / task.subtasks.length) * 100);
                    subtasksInfo = `
                        <div style="width: 100%; margin-top: 0.5rem; margin-bottom: 0.5rem;">
                            <div style="display:flex; justify-content:space-between; font-size:0.75rem; margin-bottom:0.25rem;">
                                <span><i class="ph ph-check-square"></i> ${completed}/${task.subtasks.length}</span>
                                <span>${percent}%</span>
                            </div>
                            <div style="width:100%; height:4px; background:rgba(150,150,150,0.2); border-radius:2px; overflow:hidden;">
                                <div style="height:100%; width:${percent}%; background:var(--primary-color); border-radius:2px;"></div>
                            </div>
                        </div>
                    `;
                }

                let dateFormatted = '';
                if(task.due_date) {
                    const d = new Date(task.due_date);
                    dateFormatted = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                }

                let tagsHtml = '';
                let primaryColor = '#8b5cf6'; // fallback default
                if (task.tags && task.tags.length > 0) {
                    primaryColor = task.tags[0].color;
                }
                
                // Exactly matching Dribbble style: very solid pastel background, vibrant text
                // We mix the color heavily with the surface color to get a rich pastel block
                let bgTint = `color-mix(in srgb, ${primaryColor} 20%, var(--bg-surface))`;
                let dotBg = `color-mix(in srgb, ${primaryColor} 25%, var(--bg-surface))`;
                let tagBg = `rgba(255, 255, 255, 0.5)`; // "white" pills
                
                if (task.tags && task.tags.length > 0) {
                    tagsHtml = '<div style="display:flex; flex-wrap:wrap; gap:0.5rem;">';
                    task.tags.forEach(t => {
                        tagsHtml += `<span class="dt-task-tag" style="--tag-color:${t.color}; --tag-contrast:${getContrastColor(t.color)}; padding:0.35rem 0.75rem; border-radius:10px; font-size:0.8rem; font-weight:700; box-shadow: 0 1px 2px rgba(0,0,0,0.05); letter-spacing:-0.01em;">#${t.name}</span>`;
                    });
                    tagsHtml += '</div>';
                }

                // Subtasks Miniatures & Dot Progress
                let newSubtasksInfo = '';
                if(task.subtasks && task.subtasks.length > 0) {
                    const completed = task.subtasks.filter(s => s.is_completed == 1).length;
                    const total = task.subtasks.length;
                    const percent = Math.round((completed / total) * 100);
                    
                    // Checklist Preview (max 4)
                    let checklistHtml = '<div style="margin-top: 1.5rem; margin-bottom: 1.5rem; display:flex; flex-direction:column; gap:0.6rem;">';
                    task.subtasks.slice(0, 4).forEach(s => {
                        let isComp = s.is_completed == 1;
                        let iconBox = isComp 
                            ? `<div style="width:18px; height:18px; border-radius:50%; background:${primaryColor}; color:${tagBg}; display:flex; align-items:center; justify-content:center; font-size:10px;"><i class="ph ph-check"></i></div>`
                            : `<div style="width:18px; height:18px; border-radius:50%; border:2px solid color-mix(in srgb, ${primaryColor} 40%, transparent); display:flex; align-items:center; justify-content:center;"></div>`;
                        
                        checklistHtml += `
                            <div style="display:flex; align-items:center; gap:0.6rem;">
                                ${iconBox}
                                <span style="font-size:0.9rem; font-weight:700; color:${primaryColor}; opacity:${isComp ? '0.7' : '1'};">${s.title}</span>
                            </div>
                        `;
                    });
                    checklistHtml += '</div>';
                    
                    let dotsHtml = '';
                    for(let i=0; i<12; i++) { // Fixed 12 dots to match the design vibe
                        let expectedPercent = (i / 11) * 100;
                        let dotColor = percent >= expectedPercent ? primaryColor : dotBg;
                        dotsHtml += `<div style="width:16px; height:16px; border-radius:50%; background:${dotColor};"></div>`;
                    }

                    newSubtasksInfo = `
                        ${checklistHtml}
                        <div style="width: 100%; margin-top: 1rem; margin-bottom: 0.5rem;">
                            <div style="display:flex; justify-content:space-between; font-size:0.95rem; font-weight:700; color:${primaryColor}; margin-bottom:0.75rem;">
                                <span>Progress</span>
                                <span>${percent}%</span>
                            </div>
                            <div style="display:flex; gap:6px; flex-wrap:wrap; width:100%; align-items:center; justify-content:space-between;">
                                ${dotsHtml}
                            </div>
                        </div>
                    `;
                }

                let linksHtml = '';
                if (task.external_links && task.external_links.trim() !== '') {
                    const lines = task.external_links.split('\n').filter(l => l.trim() !== '');
                    if (lines.length > 0) {
                        linksHtml = '<div style="display:flex; gap:0.4rem; margin-bottom:0.5rem;">';
                        lines.forEach(l => {
                            let icon = 'ph-link';
                            if (l.toLowerCase().includes('figma.com')) icon = 'ph-figma-logo';
                            else if (l.toLowerCase().includes('canva.com')) icon = 'ph-paint-brush';
                            
                            linksHtml += `<a href="${l.trim()}" target="_blank" onclick="event.stopPropagation()" style="width:28px; height:28px; border-radius:50%; background:${tagBg}; display:flex; align-items:center; justify-content:center; color:${primaryColor}; text-decoration:none; box-shadow:0 2px 4px rgba(0,0,0,0.05); font-size:14px;" title="${l.trim()}"><i class="ph ${icon}"></i></a>`;
                        });
                        linksHtml += '</div>';
                    }
                }
                
                // Wrap avatars in pill box
                let finalAvatarsHtml = avatarsHtml;
                if(finalAvatarsHtml.includes('<img') || finalAvatarsHtml.includes('<div')) {
                    // Extracting inner avatars since avatarsHtml comes wrapped in `<div style="display:flex;">` from earlier code
                    // Wait, earlier code generated avatarsHtml with <div style="display:flex;"> inside it.
                    // Let's just wrap it in a pill.
                    finalAvatarsHtml = `<div style="background:${tagBg}; padding:4px 6px 4px 4px; border-radius:20px; display:flex; align-items:center; box-shadow:0 2px 4px rgba(0,0,0,0.05);">${avatarsHtml.replace('<div style="display:flex;">', '').replace('</div>', '')}</div>`;
                }

                // Dribbble-style card container overrides
                el.style.backgroundColor = bgTint;
                el.style.border = '1px solid transparent';
                el.style.borderRadius = '24px'; // Softer, more organic corners like Dribbble
                el.style.boxShadow = `0 4px 15px color-mix(in srgb, ${primaryColor} 10%, transparent)`;

                el.innerHTML = `
                    <div style="padding: 1.5rem;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem;">
                            ${tagsHtml}
                            <div style="width:28px; height:28px; border-radius:10px; background:${tagBg}; color:${primaryColor}; display:flex; align-items:center; justify-content:center; font-size:16px; box-shadow:0 2px 4px rgba(0,0,0,0.05);"><i class="ph ph-dots-three-vertical"></i></div>
                        </div>
                        
                        <div class="dt-task-title" style="font-size:1.35rem; font-weight:800; color:${primaryColor}; margin-bottom:1rem; line-height:1.2; letter-spacing:-0.02em;">${task.title}</div>
                        
                        ${coverHtml ? `<div style="margin-bottom:1.5rem; border-radius:14px; overflow:hidden; box-shadow:0 6px 15px rgba(0,0,0,0.08); border: 2px solid rgba(255,255,255,0.4);">${coverHtml}</div>` : ''}
                        
                        ${task.description && task.description.trim() !== '' ? `<div style="font-size:0.95rem; color:${primaryColor}; margin-bottom:1rem; font-weight:700; opacity:0.9; line-height:1.4;">${task.description.replace(/<[^>]*>?/gm, '').substring(0, 100)}${task.description.length > 100 ? '...' : ''}</div>` : ''}
                        ${dateFormatted ? `<div style="font-size:0.85rem; color:${primaryColor}; margin-bottom:0.75rem; font-weight:700; opacity:0.9;"><i class="ph ph-clock"></i> ${dateFormatted}</div>` : ''}
                        
                        ${linksHtml}
                        
                        <div style="display:flex; gap:0.5rem; margin-bottom:0.5rem; align-items:center;">
                            <span class="badge-priority ${task.priority}" style="background:${tagBg}; color:${primaryColor}; box-shadow:0 2px 4px rgba(0,0,0,0.05); padding:0.35rem 0.75rem; border-radius:10px; font-weight:700;">${task.priority}</span>
                            ${task.timer_running == 1 ? '<span style="display:flex; align-items:center; gap:0.25rem; font-size:0.75rem; color:#10b981; font-weight:700;"><div style="width:8px; height:8px; border-radius:50%; background:#10b981; animation:pulse-urgent 1.5s infinite;"></div> Tracking...</span>' : ''}
                        </div>
                        
                        ${newSubtasksInfo}
                        
                        <div class="dt-task-meta" style="margin-top: 1.5rem; display:flex; justify-content:space-between; align-items:center;">
                            ${finalAvatarsHtml}
                            
                            <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.9rem; font-weight:700; color:${primaryColor};">
                                ${attachmentsIcon ? `<div style="background:${tagBg}; padding:0.4rem 0.8rem; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,0.05); display:flex; align-items:center; gap:6px;">${attachmentsIcon}</div>` : ''}
                                
                                <div style="background:${tagBg}; padding:0.4rem 0.8rem; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,0.05); display:flex; align-items:center; gap:6px;">
                                    <button type="button" class="btn-icon" style="padding: 0; width: 18px; height: 18px; background: transparent; color: inherit; border:none;" onclick="event.stopPropagation(); toggleTimer(${task.id}, ${task.timer_running})" title="${task.timer_running == 1 ? 'Detener Tiempo' : 'Iniciar Tiempo'}">
                                        <i class="ph ${task.timer_running == 1 ? 'ph-stop' : 'ph-play'}" style="font-size:18px;"></i>
                                    </button>
                                    <span class="${task.timer_running == 1 ? 'live-timer' : ''}" data-started="${task.timer_started_at || ''}" data-spent="${task.time_spent || 0}">${formatTimeSpent(task.time_spent, task.timer_running, task.timer_started_at)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                el.onclick = () => openEditModal(task);
                document.getElementById('col-' + task.status).appendChild(el);
                counts[task.status]++;
            }
        });

        cols.forEach(status => {
            document.getElementById('count-' + status).innerText = counts[status];
        });

        renderListView();
    }

    function renderListView() {
        const tbody = document.getElementById('dt-list-body');
        tbody.innerHTML = '';
        if (allTasks.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">No hay tareas</td></tr>';
            return;
        }

        allTasks.forEach(task => {
            let dateFormatted = task.due_date ? new Date(task.due_date).toLocaleDateString() : '-';
            let timerBtn = `
                <button type="button" class="btn-icon" style="padding: 0.2rem; width: 24px; height: 24px; background: ${task.timer_running == 1 ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)'}; color: ${task.timer_running == 1 ? '#ef4444' : '#10b981'}; border-radius: 4px;" onclick="toggleTimer(${task.id}, ${task.timer_running})" title="${task.timer_running == 1 ? 'Detener Tiempo' : 'Iniciar Tiempo'}">
                    <i class="ph ${task.timer_running == 1 ? 'ph-stop' : 'ph-play'}"></i>
                </button>
            `;

            let row = document.createElement('tr');
            row.style.borderBottom = '1px solid rgba(150,150,150,0.1)';
            row.innerHTML = `
                <td data-label="T�tulo" style="padding:1rem; cursor:pointer;" onclick="openEditModalById(${task.id})"><div style="font-weight:600; text-align:right;">${task.title}</div></td>
                <td data-label="Estado" style="padding:1rem;"><span style="font-size:0.8rem; background:rgba(150,150,150,0.1); padding:0.2rem 0.5rem; border-radius:4px;">${task.status}</span></td>
                <td data-label="Prioridad" style="padding:1rem;"><span class="badge-priority ${task.priority}">${task.priority}</span></td>
                <td data-label="Fecha" style="padding:1rem;">${dateFormatted}</td>
                <td data-label="Asignados" style="padding:1rem;"><div style="display:flex; justify-content:flex-end;">
                    ${task.assigned_to.map(uid => {
                        const u = systemUsers.find(x => x.id == uid);
                        if(!u) return '';
                        if (u.avatar) return `<img src="${u.avatar}" style="width:24px; height:24px; border-radius:50%; object-fit:cover; margin-right:2px;" title="${u.name}">`;
                        return `<div style="width:24px; height:24px; border-radius:50%; background:var(--primary-color); color:white; display:flex; align-items:center; justify-content:center; font-size:10px; margin-right:2px;" title="${u.name}">${u.name.charAt(0)}</div>`;
                    }).join('')}
                </div></td>
                <td data-label="Tiempo" style="padding:1rem;">
                    <div style="display:flex; align-items:center; justify-content:flex-end; gap:0.5rem;">
                        ${timerBtn}
                        <span class="${task.timer_running == 1 ? 'live-timer' : ''}" data-started="${task.timer_started_at || ''}" data-spent="${task.time_spent || 0}" style="font-size:0.85rem; font-weight:600;">${formatTimeSpent(task.time_spent, task.timer_running, task.timer_started_at)}</span>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    function openEditModalById(id) {
        const task = allTasks.find(t => t.id == id);
        if (task) openEditModal(task);
    }

    function initSortable() {
        const cols = ['Pendiente', 'En progreso', 'En revisión', 'Terminado'];
        cols.forEach(status => {
            const el = document.getElementById('col-' + status);
            new Sortable(el, {
                group: 'kanban',
                animation: 150,
                onEnd: function (evt) {
                    const itemEl = evt.item;
                    const newStatus = evt.to.dataset.status;
                    const taskId = itemEl.dataset.id;
                    
                    const task = allTasks.find(t => t.id == taskId);
                    if(task && task.status !== newStatus) {
                        task.status = newStatus;
                        updateTaskStatus(taskId, newStatus);
                        renderKanban(); 
                    }
                },
            });
        });
    }

    async function updateTaskStatus(id, status) {
        try {
            const fd = new FormData();
            fd.append('action', 'update_status');
            fd.append('id', id);
            fd.append('status', status);
            await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
        } catch(e) { console.error(e); }
    }

    function initCalendar() {
        const calendarEl = document.getElementById('calendar');
        const isMobile = window.innerWidth < 768;
        
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: isMobile ? 'listMonth' : 'dayGridMonth',
            locale: 'es',
            editable: true,
            droppable: true,
            eventDisplay: 'block',
            dayMaxEvents: 3, // Add limit to prevent infinite cell stretching
            contentHeight: 'auto', // Prevent rows from stretching to fit an aspect ratio
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: isMobile ? '' : 'dayGridMonth,timeGridWeek'
            },
            events: [],
            eventContent: function(arg) {
                const task = arg.event.extendedProps.task;
                if(!task) return;

                let tagColors = task.tags ? task.tags.map(t => t.color) : [];
                let primaryColor = tagColors.length > 0 ? tagColors[0] : '#8b5cf6';
                let bgTint = `color-mix(in srgb, ${primaryColor} 20%, var(--bg-surface))`;
                let dotBg = `color-mix(in srgb, ${primaryColor} 25%, var(--bg-surface))`;
                let tagBg = `rgba(255, 255, 255, 0.5)`; // 50% opacity white

                let timeStr = 'Sin fecha';
                if (task.due_date) {
                    const d = new Date(task.due_date);
                    timeStr = d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                }
                
                // Tags
                let tagsHtml = '';
                if (task.tags && task.tags.length > 0) {
                    tagsHtml = '<div style="display:flex; flex-wrap:wrap; gap:0.25rem; margin-bottom:0.5rem;">';
                    task.tags.forEach(t => {
                        tagsHtml += `<span class="dt-task-tag" style="--tag-color:${t.color}; --tag-contrast:${getContrastColor(t.color)}; padding:0.2rem 0.5rem; border-radius:8px; font-size:0.65rem; font-weight:700; box-shadow: 0 1px 2px rgba(0,0,0,0.05); letter-spacing:-0.01em;">#${t.name}</span>`;
                    });
                    tagsHtml += '</div>';
                }
                
                // Checklist Preview (max 2 for calendar to save space)
                let subtasksHtml = '';
                if(task.subtasks && task.subtasks.length > 0) {
                    let checklistHtml = '<div style="margin-top: 0.5rem; margin-bottom: 0.5rem; display:flex; flex-direction:column; gap:0.3rem;">';
                    task.subtasks.slice(0, 2).forEach(s => {
                        let isComp = s.is_completed == 1;
                        let iconBox = isComp 
                            ? `<div style="width:12px; height:12px; border-radius:50%; background:${primaryColor}; color:${tagBg}; display:flex; align-items:center; justify-content:center; font-size:8px;"><i class="ph ph-check"></i></div>`
                            : `<div style="width:12px; height:12px; border-radius:50%; border:1px solid color-mix(in srgb, ${primaryColor} 40%, transparent); display:flex; align-items:center; justify-content:center;"></div>`;
                        
                        checklistHtml += `
                            <div style="display:flex; align-items:center; gap:0.4rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                ${iconBox}
                                <span style="font-size:0.75rem; font-weight:600; color:${primaryColor}; opacity:${isComp ? '0.7' : '1'}; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${s.title}</span>
                            </div>
                        `;
                    });
                    if (task.subtasks.length > 2) {
                        checklistHtml += `<div style="font-size:0.7rem; color:${primaryColor}; opacity:0.8; margin-left:1.2rem;">+${task.subtasks.length - 2} m�s</div>`;
                    }
                    checklistHtml += '</div>';

                    // Progress Dots
                    const completed = task.subtasks.filter(s => s.is_completed == 1).length;
                    const total = task.subtasks.length;
                    const percent = Math.round((completed / total) * 100);
                    
                    let dotsHtml = '';
                    for(let i=0; i<10; i++) {
                        let expectedPercent = (i / 9) * 100;
                        let dotColor = percent >= expectedPercent ? primaryColor : dotBg;
                        dotsHtml += `<div style="width:8px; height:8px; border-radius:50%; background:${dotColor};"></div>`;
                    }

                    subtasksHtml = `
                        ${checklistHtml}
                        <div style="width: 100%; margin-top: 0.5rem; margin-bottom: 0.25rem;">
                            <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:700; color:${primaryColor}; margin-bottom:0.4rem;">
                                <span>Progress</span>
                                <span>${percent}%</span>
                            </div>
                            <div style="display:flex; gap:3px; flex-wrap:wrap; width:100%; align-items:center; justify-content:space-between;">
                                ${dotsHtml}
                            </div>
                        </div>
                    `;
                }

                let avatarsHtml = '';
                let extraAvatars = '';
                if(task.assigned_to && task.assigned_to.length > 0) {
                    task.assigned_to.slice(0, 3).forEach((uid, idx) => {
                        const u = systemUsers.find(x => x.id == uid);
                        if(u) {
                            const initial = u.name.charAt(0).toUpperCase();
                            const ml = idx > 0 ? '-6px' : '0';
                            if (u.avatar && u.avatar.trim() !== '') {
                                avatarsHtml += `<img src="${u.avatar}" style="width:18px; height:18px; border-radius:50%; object-fit:cover; border:2px solid ${tagBg}; margin-left:${ml};" title="${u.name}">`;
                            } else {
                                avatarsHtml += `<div style="width:18px; height:18px; border-radius:50%; background:var(--primary-color); color:white; display:flex; align-items:center; justify-content:center; font-size:9px; border:2px solid ${tagBg}; margin-left:${ml};" title="${u.name}">${initial}</div>`;
                            }
                        }
                    });
                    if(task.assigned_to.length > 3) {
                        extraAvatars = `<span style="font-size:9px; color:${primaryColor}; font-weight:600; margin-left:4px;">+${task.assigned_to.length - 3}</span>`;
                    }
                }

                // Wrap avatars
                let finalAvatarsHtml = avatarsHtml;
                if(finalAvatarsHtml.includes('<img') || finalAvatarsHtml.includes('<div')) {
                    finalAvatarsHtml = `<div class="dt-avatar-wrap" style="padding:2px 4px 2px 2px; border-radius:12px; display:flex; align-items:center; box-shadow:0 1px 2px rgba(0,0,0,0.05);">${avatarsHtml.replace('<div style="display:flex;">', '').replace('</div>', '')}</div>`;
                }

                let html = `
                    <div style="background:${bgTint}; padding:0.75rem; border-radius:12px; border:1px solid transparent; box-shadow:0 2px 6px color-mix(in srgb, ${primaryColor} 5%, transparent); cursor:pointer;">
                        ${tagsHtml}
                        <div style="font-weight:800; color:${primaryColor}; font-size:0.9rem; margin-bottom:0.25rem; white-space:normal; line-height:1.2; letter-spacing:-0.01em;">${task.title}</div>
                        
                        ${subtasksHtml}
                        
                        <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:0.75rem;">
                            ${finalAvatarsHtml}
                            <span style="font-size:0.7rem; font-weight:700; color:${primaryColor}; opacity:0.9; background:${tagBg}; padding:0.2rem 0.5rem; border-radius:8px; box-shadow:0 1px 2px rgba(0,0,0,0.05);"><i class="ph ph-clock"></i> ${timeStr}</span>
                        </div>
                    </div>
                `;
                return { html: html };
            },
            eventDrop: async function(info) {
                const taskId = info.event.id;
                const newDateStr = info.event.start.toISOString().slice(0, 19).replace('T', ' ');
                
                const task = allTasks.find(t => t.id == taskId);
                if(task) task.due_date = newDateStr;
                
                try {
                    const fd = new FormData();
                    fd.append('action', 'update_date');
                    fd.append('id', taskId);
                    fd.append('due_date', newDateStr);
                    await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
                    renderKanban();
                } catch(e) {
                    console.error(e);
                    info.revert();
                }
            },
            eventClick: function(info) {
                const task = allTasks.find(t => t.id == info.event.id);
                if(task) openEditModal(task);
            }
        });
        calendar.render(); 
        checkCalendarView();
    }

    function checkCalendarView() {
        if (!calendar) return;
        if (window.innerWidth < 768) {
            if (calendar.view.type !== 'listMonth') {
                calendar.changeView('listMonth');
            }
        } else {
            if (calendar.view.type !== 'dayGridMonth') {
                calendar.changeView('dayGridMonth');
            }
        }
    }
    
    window.addEventListener('resize', checkCalendarView);

    function renderCalendar() {
        calendar.removeAllEvents();
        const events = allTasks.filter(t => t.due_date).map(t => {
            return {
                id: t.id,
                title: t.title,
                start: t.due_date,
                extendedProps: { task: t }
            };
        });
        calendar.addEventSource(events);
    }

    function switchOcTab(tabName, el) {
        document.querySelectorAll('.oc-nav-item').forEach(n => n.classList.remove('active'));
        el.classList.add('active');
        document.querySelectorAll('.oc-tab-pane').forEach(p => p.classList.remove('active'));
        document.getElementById('tab-' + tabName).classList.add('active');
    }

    function setDriveFolder(folder) {
        document.getElementById('task-drive-folder').value = folder.id;
        document.getElementById('selected-folder-info').innerHTML = `<i class="ph ph-check-circle text-success"></i> Conectado a: <strong>${folder.name}</strong>`;
        
        if (!foldersGenerated) {
            document.getElementById('btn-generate-folders').style.display = 'block';
            document.getElementById('upload-areas').style.display = 'none';
        } else {
            document.getElementById('btn-generate-folders').style.display = 'none';
            document.getElementById('upload-areas').style.display = 'block';
        }
        document.getElementById('no-drive-warning').style.display = 'none';
    }

    async function generateFolderStructure() {
        const driveFolderId = document.getElementById('task-drive-folder').value;
        if(!driveFolderId) return;

        const btn = document.getElementById('generate-btn-element');
        const oldText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Generando...';
        btn.disabled = true;

        try {
            const fd = new FormData();
            fd.append('action', 'generate_folder_structure');
            fd.append('drive_folder_id', driveFolderId);
            fd.append('task_title', document.getElementById('task-title').value);
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                if (data.new_folder_id) {
                    document.getElementById('task-drive-folder').value = data.new_folder_id;
                    document.getElementById('selected-folder-info').innerHTML = '<i class="ph ph-folder text-primary"></i> Carpeta de tarea generada y vinculada.';
                }
                foldersGenerated = true;
                document.getElementById('btn-generate-folders').style.display = 'none';
                document.getElementById('upload-areas').style.display = 'block';
                document.getElementById('inp-main-ref').disabled = false;
                document.getElementById('warn-main-ref').style.display = 'none';
                document.querySelectorAll('.st-file-inp').forEach(inp => inp.disabled = false);
            } else {
                alert(data.error || 'Error al generar carpetas');
            }
        } catch(e) {
            console.error(e);
            alert('Error de red');
        }
        btn.innerHTML = oldText;
        btn.disabled = false;
    }

    function initSubtasksSortable() {
        const container = document.getElementById('subtasks-container');
        new Sortable(container, {
            animation: 150,
            handle: '.st-card'
        });
    }

    function openTaskModal(taskId = null) {
        document.getElementById('task-form').reset();
        document.getElementById('task-id').value = '';
        if (quillEditor) quillEditor.root.innerHTML = '';
        document.getElementById('task-drive-folder').value = '';
        document.getElementById('task-client-id').value = '';
        document.getElementById('task-linked-form').value = '';
        toggleViewFormBtn(document.getElementById('task-linked-form'));
        document.getElementById('btn-delete-task').style.display = 'none';
        document.getElementById('btn-clone-task').style.display = 'none';
        tomSelectAssign.clear();
        document.getElementById('subtasks-container').innerHTML = '';
        document.getElementById('subtasks-empty').style.display = 'block';
        subtaskIndex = 0;
        
        initSubtasksSortable();
        document.getElementById('modal-title').innerText = taskId ? 'Editar Tarea' : 'Nueva Tarea';
        document.getElementById('status-group').style.display = 'none';
        
        document.getElementById('prio-media').checked = true;
        
        localMainReferences = [];
        localSubtaskReferences = {};
        
        document.getElementById('ref-images-container').innerHTML = '';
        document.getElementById('existing-design-files').innerHTML = '';
        document.getElementById('avances-gallery').innerHTML = '';
        document.getElementById('avances-empty').style.display = 'block';
        
        foldersGenerated = false;
        document.getElementById('task-drive-folder').value = '';
        document.getElementById('selected-folder-info').innerHTML = '<i class="ph ph-warning-circle text-warning"></i> Ninguna carpeta seleccionada';
        document.getElementById('upload-areas').style.display = 'none';
        document.getElementById('btn-generate-folders').style.display = 'none';
        document.getElementById('no-drive-warning').style.display = 'block';
        
        document.getElementById('inp-main-ref').disabled = true;
        document.getElementById('warn-main-ref').style.display = 'block';
        
        document.getElementById('oc-overlay').classList.add('active');
        document.getElementById('task-offcanvas').classList.add('active');
        switchOcTab('details', document.querySelector('.oc-nav-item')); 
    }

    function getDriveFileId(url) {
        if(!url) return null;
        let match = url.match(/\/d\/(.+?)\//);
        if (match) return match[1];
        match = url.match(/id=([^&]+)/);
        if (match) return match[1];
        return null;
    }

    window.handleImageError = function(img, id, url, name) {
        const parent = img.closest('.thumb-item');
        if (parent) {
            parent.innerHTML = `
                <div class="file-card" style="margin:0; width:100%; height:100%; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; padding:0.5rem;">
                    <i class="ph ph-image-broken" style="font-size:2rem; color:var(--text-muted); margin-bottom:0.5rem;"></i>
                    <a href="${url}" target="_blank" style="font-size:0.7rem; color:var(--primary-color); word-break:break-all;">Ver Original</a>
                </div>
                <button type="button" class="thumb-btn-del" onclick="deleteAttachment(${id})"><i class="ph ph-x"></i></button>
            `;
        }
    };

    function renderReferenceCard(att) {
        const fileId = getDriveFileId(att.file_path);
        let name = att.file_name || 'Archivo';
        let ext = name.split('.').pop().toLowerCase();
        let isImage = ['jpg','jpeg','png','gif','webp', 'heic'].includes(ext);

        let dateStr = '';
        if (att.created_at) {
            const d = new Date(att.created_at);
            // Si la fecha es válida, formatearla
            if (!isNaN(d.getTime())) {
                const day = d.getDate().toString().padStart(2, '0');
                const month = (d.getMonth() + 1).toString().padStart(2, '0');
                const hours = d.getHours() % 12 || 12;
                const minutes = d.getMinutes().toString().padStart(2, '0');
                const ampm = d.getHours() >= 12 ? 'PM' : 'AM';
                dateStr = `<div style="position:absolute; bottom:5px; left:5px; background:rgba(0,0,0,0.65); color:#fff; font-size:0.65rem; padding:0.2rem 0.4rem; border-radius:4px; pointer-events:none; z-index:5;"><i class="ph ph-clock"></i> ${day}/${month} - ${hours}:${minutes} ${ampm}</div>`;
            }
        }

        if (isImage && fileId) {
            // sz=w300 instead of w800 for much faster loading
            const thumbUrl = `https://drive.google.com/thumbnail?id=${fileId}&sz=w300`;
            const fullUrl = `https://drive.google.com/thumbnail?id=${fileId}&sz=w1920`;
            return `
                <div class="thumb-item" id="att-${att.id}" onmouseenter="this.querySelector('.pin-btn').style.opacity='1'" onmouseleave="this.querySelector('.pin-btn').style.opacity='0'">
                    <a href="${fullUrl}" data-fancybox="gallery">
                        <img src="${thumbUrl}" alt="Referencia" onerror="handleImageError(this, ${att.id}, '${att.file_path}', '${name}')">
                    </a>
                    <button type="button" class="btn btn-primary pin-btn" style="position:absolute; top:5px; left:5px; padding:0.2rem 0.5rem; font-size:0.75rem; border-radius:4px; opacity:0; transition:opacity 0.2s; z-index:5;" onclick="openPinModal(${att.id}, '${fullUrl}')">
                        <i class="ph ph-chat-circle"></i> Pines
                    </button>
                    ${dateStr}
                    <button type="button" class="thumb-btn-del" onclick="deleteAttachment(${att.id})"><i class="ph ph-x"></i></button>
                </div>
            `;
        }

        return renderFileCard(att);
    }

    function renderAvanceCard(att) {
        const fileId = getDriveFileId(att.file_path);
        let name = att.file_name || 'Avance';
        
        let dateStr = 'Recién subido';
        let timeStr = '';
        if (att.created_at) {
            const d = new Date(att.created_at);
            if (!isNaN(d.getTime())) {
                const day = d.getDate().toString().padStart(2, '0');
                const month = (d.getMonth() + 1).toString().padStart(2, '0');
                const year = d.getFullYear();
                const hours = d.getHours() % 12 || 12;
                const minutes = d.getMinutes().toString().padStart(2, '0');
                const ampm = d.getHours() >= 12 ? 'PM' : 'AM';
                dateStr = `${day}/${month}/${year}`;
                timeStr = `${hours}:${minutes} ${ampm}`;
            }
        }

        const thumbUrl = fileId ? `https://drive.google.com/thumbnail?id=${fileId}&sz=w300` : '';
        const fullUrl = fileId ? `https://drive.google.com/thumbnail?id=${fileId}&sz=w1920` : att.file_path;

        return `
            <div id="att-${att.id}" style="display:flex; align-items:center; background:var(--card-bg); border:1px solid var(--border-color); border-radius:12px; padding:1rem; gap:1.5rem; position:relative; overflow:hidden; transition:border-color 0.2s;">
                <!-- Miniatura a la izquierda -->
                <a href="${fullUrl}" data-fancybox="gallery" style="flex-shrink:0; width:120px; height:80px; border-radius:8px; overflow:hidden; background:var(--bg-color); display:flex; align-items:center; justify-content:center;">
                    ${thumbUrl ? `<img src="${thumbUrl}" alt="Avance" style="width:100%; height:100%; object-fit:cover;" onerror="this.outerHTML='<i class=\\'ph ph-image\\' style=\\'font-size:2rem; color:var(--text-muted)\\'></i>'">` : `<i class="ph ph-image" style="font-size:2rem; color:var(--text-muted)"></i>`}
                </a>
                
                <!-- Info a la derecha -->
                <div style="flex:1; display:flex; flex-direction:column; justify-content:center;">
                    <div style="font-size:1rem; font-weight:600; color:var(--text-color); margin-bottom:0.25rem;">${name}</div>
                    <div style="display:flex; align-items:center; gap:1rem; color:var(--text-muted); font-size:0.85rem;">
                        <span style="display:flex; align-items:center; gap:0.3rem;"><i class="ph ph-calendar"></i> ${dateStr}</span>
                        ${timeStr ? `<span style="display:flex; align-items:center; gap:0.3rem;"><i class="ph ph-clock"></i> ${timeStr}</span>` : ''}
                    </div>
                </div>

                <button type="button" class="btn-icon" style="color:var(--danger-color); position:absolute; right:1rem; top:50%; transform:translateY(-50%);" onclick="deleteAttachment(${att.id})">
                    <i class="ph ph-trash" style="font-size:1.2rem;"></i>
                </button>
            </div>
        `;
    }

    function renderFileCard(att) {
        let name = att.file_name || 'Archivo';
        let ext = name.split('.').pop().toLowerCase();
        let icon = 'ph-file';
        if(['jpg','jpeg','png','gif','webp'].includes(ext)) icon = 'ph-image';
        if(['pdf'].includes(ext)) icon = 'ph-file-pdf';
        if(['zip','rar','7z'].includes(ext)) icon = 'ph-file-zip';

        return `
            <div class="file-card" id="att-${att.id}">
                <div class="file-info">
                    <i class="ph ${icon} file-icon" style="color:#3b82f6;"></i>
                    <div>
                        <div style="font-weight:600; color:var(--color-title); font-size:0.9rem;">${name}</div>
                        <a href="${att.file_path}" target="_blank" style="font-size:0.75rem; color:var(--primary-color);">Abrir archivo <i class="ph ph-arrow-square-out"></i></a>
                    </div>
                </div>
                <button type="button" class="btn btn-outline" style="color:var(--danger-color); border-color:var(--danger-color); padding: 0.2rem 0.5rem;" onclick="deleteAttachment(${att.id})">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
        `;
    }

    function openEditModal(task) {
        openTaskModal(task.id); 
        
        const d = document.getElementById('btn-delete-task');
        const c = document.getElementById('btn-clone-task');
        if(task) {
            document.getElementById('modal-title').innerText = 'Editar Tarea';
            document.getElementById('task-id').value = task.id;
            document.getElementById('task-title').value = task.title;
            if (quillEditor) quillEditor.clipboard.dangerouslyPasteHTML(task.description || '');
            document.getElementById('task-external-links').value = task.external_links || '';
            document.getElementById('task-client-id').value = task.client_id || '';
            if(task.priority) document.querySelector(`input[name="priority"][value="${task.priority}"]`).checked = true;
            if(task.status) {
                const statusRadio = document.querySelector(`input[name="status"][value="${task.status}"]`);
                if(statusRadio) statusRadio.checked = true;
            }
            if(task.linked_submission_id) {
                document.getElementById('task-linked-form').value = task.linked_submission_id;
                toggleViewFormBtn(document.getElementById('task-linked-form'));
            }
            document.getElementById('status-group').style.display = 'block';
            if (task.due_date) {
                document.getElementById('task-due-date').value = task.due_date.replace(' ', 'T').substring(0, 16);
            }
            if (isAdmin || task.created_by == currentUserId) d.style.display = 'flex';
            c.style.display = 'flex';
        }

        if (task.assigned_to) {
            tomSelectAssign.setValue(task.assigned_to);
        }

        if (task.tags) {
            tomSelectTags.clear();
            tomSelectTags.clearOptions();
            task.tags.forEach(t => {
                tomSelectTags.addOption({value: t.name, text: t.name});
            });
            tomSelectTags.setValue(task.tags.map(t => t.name));
        }

        if (task.drive_folder_id) {
            foldersGenerated = true; // Assume generated if folder exists on edit
            document.getElementById('task-drive-folder').value = task.drive_folder_id;
            document.getElementById('selected-folder-info').innerHTML = `<i class="ph ph-check-circle text-success"></i> Conectado a carpeta (ID: ${task.drive_folder_id.substring(0,8)}...)`;
            document.getElementById('upload-areas').style.display = 'block';
            document.getElementById('btn-generate-folders').style.display = 'none';
            document.getElementById('no-drive-warning').style.display = 'none';
            
            document.getElementById('inp-main-ref').disabled = false;
            document.getElementById('warn-main-ref').style.display = 'none';
        }

        // Subtasks
        const stContainer = document.getElementById('subtasks-container');
        stContainer.innerHTML = '';
        task.subtasks.forEach(st => {
            const stAttachments = task.attachments.filter(a => a.subtask_id == st.id);
            addSubtaskCard(st.id, st.title, st.description, st.is_completed, stAttachments, st.due_date);
        });

        // Main References
        const refContainer = document.getElementById('ref-images-container');
        refContainer.innerHTML = '<div class="thumb-grid server-previews-grid"></div>';
        const serverGrid = refContainer.querySelector('.server-previews-grid');
        
        const mainRefs = task.attachments.filter(a => a.attachment_type === 'reference');
        mainRefs.forEach(att => {
            serverGrid.innerHTML += renderReferenceCard(att);
        });

        // Design Files
        const desContainer = document.getElementById('existing-design-files');
        desContainer.innerHTML = '';
        const designFiles = task.attachments.filter(a => a.attachment_type === 'design');
        designFiles.forEach(att => {
            desContainer.innerHTML += renderFileCard(att);
        });

        // Avances
        const avancesContainer = document.getElementById('avances-gallery');
        const avancesEmpty = document.getElementById('avances-empty');
        avancesContainer.innerHTML = '';
        const avancesFiles = task.attachments.filter(a => a.attachment_type === 'avance');
        if (avancesFiles.length > 0) {
            avancesEmpty.style.display = 'none';
            avancesFiles.forEach(att => {
                avancesContainer.innerHTML += renderAvanceCard(att);
            });
        } else {
            avancesEmpty.style.display = 'block';
        }
    }

    function checkSubtasksEmpty() {
        const container = document.getElementById('subtasks-container');
        const emptyState = document.getElementById('subtasks-empty');
        if (container.children.length === 0) {
            emptyState.style.display = 'block';
        } else {
            emptyState.style.display = 'none';
        }
    }

    async function cloneTask() {
        const taskId = document.getElementById('task-id').value;
        if (!taskId) return;
        
        if (confirm('¿Estás seguro de duplicar esta tarea y todas sus subtareas?')) {
            try {
                const fd = new FormData();
                fd.append('action', 'clone_task');
                fd.append('task_id', taskId);
                const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
                const data = await res.json();
                if(data.success) {
                    closeTaskModal();
                    fetchTasks(); // Reload tasks
                } else {
                    alert(data.error || 'Error al clonar la tarea');
                }
            } catch(e) { console.error(e); }
        }
    }

    function addSubtaskCard(id = '', title = '', desc = '', isCompleted = 0, attachments = [], dueDate = null) {
        const container = document.getElementById('subtasks-container');
        const div = document.createElement('div');
        div.className = 'st-card';
        const checked = isCompleted == 1 ? 'checked' : '';
        const idx = subtaskIndex++;
        const dueDateFormatted = dueDate ? dueDate.replace(' ', 'T').substring(0, 16) : '';
        
        let attHtml = '';
        if (attachments.length > 0) {
            attHtml = '<div class="thumb-grid server-previews-grid" style="margin-top:0.5rem;">';
            attachments.forEach(att => {
                attHtml += renderReferenceCard(att);
            });
            attHtml += '</div>';
        }
        
        const disabledState = foldersGenerated ? '' : 'disabled';

        div.innerHTML = `
            <input type="hidden" name="st_ids[]" value="${id}">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div style="display:flex; align-items:center; gap:0.5rem; flex:1;">
                    <input type="hidden" name="st_comps[]" value="${isCompleted}">
                    <input type="checkbox" ${checked} onchange="this.previousElementSibling.value = this.checked ? '1' : '0'" style="width: 20px; height: 20px; cursor: pointer;">
                    <input type="text" class="form-control" name="st_titles[]" value="${title}" placeholder="Título de la subtarea" required style="font-weight:600; font-size:1.05rem;">
                </div>
                <div style="display:flex; gap:0.5rem; margin-left:1rem;">
                    ${id ? `<button type="button" class="btn-icon" style="color:var(--primary-color);" onclick="convertSubtaskToTask(${id})" title="Convertir a Tarea"><i class="ph ph-share-network"></i></button>` : ''}
                    <button type="button" class="btn-icon text-red" onclick="this.closest('.st-card').remove(); checkSubtasksEmpty();" title="Eliminar Subtarea"><i class="ph ph-trash"></i></button>
                </div>
            </div>
            <div style="display:flex; gap:1rem; margin-top:0.5rem;">
                <textarea class="form-control" name="st_descs[]" rows="2" placeholder="Descripción de la subtarea..." style="flex:1;">${desc}</textarea>
                <div style="width: 200px;">
                    <input type="datetime-local" class="form-control" name="st_due_dates[]" value="${dueDateFormatted}" style="font-size:0.8rem; padding:0.5rem;">
                </div>
            </div>
            <div style="margin-top:0.5rem;">
                <label class="form-label" style="font-size:0.8rem; font-weight:600;"><i class="ph ph-paperclip"></i> Subir Referencias (Subtarea)</label>
                <input type="file" class="form-control st-file-inp" name="st_files_${idx}[]" multiple accept="*/*" ${disabledState} style="font-size:0.8rem; padding:0.25rem;" onchange="handleLocalFiles(this.files, 'subtask', ${idx})">
                ${attHtml}
                <div id="st-local-previews-${idx}"></div>
            </div>
        `;
        container.appendChild(div);
        checkSubtasksEmpty();
    }

    async function saveTask() {
        // Sync Quill editor to hidden input
        document.getElementById('task-desc').value = quillEditor.root.innerHTML;

        const form = document.getElementById('task-form');
        if(!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const hasFiles = document.querySelector('input[type="file"][name="main_references[]"]').files.length > 0 || 
                         document.querySelector('input[type="file"][name="design_files[]"]').files.length > 0;
        const hasDrive = document.getElementById('task-drive-folder').value !== '';
        
        if (hasFiles && (!hasDrive || !foldersGenerated)) {
            alert('Has seleccionado archivos pero no has generado las carpetas en Drive. Por favor selecciona la carpeta destino en la pestaña "Archivos" y haz clic en "Generar Estructura".');
            switchOcTab('files', document.querySelectorAll('.oc-nav-item')[2]);
            return;
        }

        const fd = new FormData(form);
        fd.append('action', 'save_task');

        let hasStFiles = false;
        document.querySelectorAll('.st-file-inp').forEach(inp => {
            if (inp.files.length > 0) hasStFiles = true;
        });
        if (hasStFiles && (!hasDrive || !foldersGenerated)) {
            alert('Has seleccionado referencias en las subtareas pero no has generado las carpetas en Drive. Ve a la pestaña "Archivos".');
            switchOcTab('files', document.querySelectorAll('.oc-nav-item')[2]);
            return;
        }

        const btn = document.querySelector('.oc-footer .btn-primary');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-check"></i> Iniciando...';
        btn.disabled = true;

        // Close modal immediately so user can continue working
        setTimeout(() => {
            closeTaskModal();
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 500);

        // Show global toast
        const toast = document.getElementById('global-upload-toast');
        const fill = toast.querySelector('.progress-bar-fill');
        const status = toast.querySelector('.toast-status');
        
        toast.style.display = 'flex';
        fill.style.width = '0%';
        status.innerText = 'Subiendo archivos... 0%';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'modules/design_tasks/ajax.php', true);

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                fill.style.width = percentComplete + '%';
                status.innerText = `Subiendo archivos... ${percentComplete}%`;
            }
        };

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    let text = xhr.responseText.trim();
                    if (text.indexOf('{') !== -1) {
                        text = text.substring(text.indexOf('{'), text.lastIndexOf('}') + 1);
                    }
                    const data = JSON.parse(text);
                    if (data.success) {
                        status.innerText = '¡Guardado correctamente!';
                        fill.style.background = '#10b981';
                        setTimeout(() => {
                            toast.style.display = 'none';
                            fill.style.background = 'var(--primary-color)';
                            fetchTasks();
                        }, 2000);
                    } else {
                        status.innerText = 'Error al guardar';
                        fill.style.background = '#ef4444';
                        alert(data.error || 'Error al guardar');
                        setTimeout(() => toast.style.display = 'none', 3000);
                    }
                } catch (e) {
                    console.error("Parse error. Raw response:", xhr.responseText);
                    status.innerText = 'Error de formato';
                    fill.style.background = '#ef4444';
                    setTimeout(() => toast.style.display = 'none', 3000);
                }
            } else {
                status.innerText = 'Error de conexión';
                fill.style.background = '#ef4444';
                setTimeout(() => toast.style.display = 'none', 3000);
            }
        };

        xhr.onerror = function() {
            status.innerText = 'Error de red';
            fill.style.background = '#ef4444';
            setTimeout(() => toast.style.display = 'none', 3000);
        };

        xhr.send(fd);
    }

    function deleteTask() {
        document.getElementById('delete-confirm-modal').classList.add('active');
    }
    function closeDeleteConfirm() {
        document.getElementById('delete-confirm-modal').classList.remove('active');
    }
    async function confirmDeleteTask() {
        closeDeleteConfirm();
        const id = document.getElementById('task-id').value;
        const fd = new FormData();
        fd.append('action', 'delete_task');
        fd.append('id', id);

        try {
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                closeTaskModal();
                fetchTasks();
            }
        } catch(e) { console.error(e); }
    }

    async function deleteAttachment(id) {
        if(!confirm('¿Eliminar archivo? (Se quitará del sistema, pero seguirá existiendo en Drive si no lo borras manualmente)')) return;
        const fd = new FormData();
        fd.append('action', 'delete_attachment');
        fd.append('id', id);

        try {
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                const el = document.getElementById('att-'+id);
                if(el) el.remove();
                allTasks.forEach(t => {
                    t.attachments = t.attachments.filter(a => a.id != id);
                });
            }
        } catch(e) { console.error(e); }
    }

    function closeTaskModal() {
        document.getElementById('oc-overlay').classList.remove('active');
        document.getElementById('task-offcanvas').classList.remove('active');
        document.getElementById('task-form').reset();
        document.getElementById('task-id').value = '';
        if (tomSelectAssign) tomSelectAssign.clear();
        if (tomSelectTags) {
            tomSelectTags.clear();
            tomSelectTags.clearOptions();
        }
    }

    async function convertSubtaskToTask(subtaskId) {
        if (!confirm('¿Convertir esta subtarea en una tarea principal? Se moverán sus archivos.')) return;
        try {
            const fd = new FormData();
            fd.append('action', 'convert_subtask_to_task');
            fd.append('subtask_id', subtaskId);
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                closeTaskModal();
                fetchTasks();
            } else {
                alert(data.error);
            }
        } catch(e) { console.error(e); }
    }

    async function toggleTimer(taskId, currentRunningState) {
        event.stopPropagation(); // prevent opening the modal
        try {
            const fd = new FormData();
            fd.append('action', 'update_timer');
            fd.append('task_id', taskId);
            fd.append('type', currentRunningState == 1 ? 'stop' : 'start');
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                fetchTasks();
            } else {
                alert(data.error);
            }
        } catch(e) { console.error(e); }
    }

    function formatTimeSpent(seconds, isRunning, startedAt) {
        let total = parseInt(seconds) || 0;
        if (isRunning && startedAt) {
            const start = new Date(startedAt.replace(' ', 'T')).getTime();
            const now = new Date().getTime();
            total += Math.floor((now - start) / 1000);
        }
        if (!total) return '0s';
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;
        
        if (h > 0) return `${h}h ${m}m`;
        if (m > 0) return `${m}m ${s}s`;
        return `${s}s`;
    }

    async function fetchTrash() {
        try {
            const res = await fetch('modules/design_tasks/ajax.php?action=fetch_trash');
            const data = await res.json();
            const tbody = document.getElementById('dt-trash-body');
            tbody.innerHTML = '';
            
            if(data.success && data.data.length > 0) {
                data.data.forEach(task => {
                    const dateFormatted = task.deleted_at ? new Date(task.deleted_at).toLocaleString() : '-';
                    tbody.innerHTML += `
                        <tr style="border-bottom:1px solid rgba(150,150,150,0.1);">
                            <td style="padding:1rem; font-weight:600; text-decoration:line-through; color:var(--text-muted);">${task.title}</td>
                            <td style="padding:1rem;">${task.status}</td>
                            <td style="padding:1rem;">${dateFormatted}</td>
                            <td style="padding:1rem; text-align:right;">
                                <button type="button" class="btn btn-primary" style="padding:0.25rem 0.5rem; font-size:0.8rem; margin-right:0.5rem;" onclick="restoreTask(${task.id})"><i class="ph ph-arrow-counter-clockwise"></i> Restaurar</button>
                                <button type="button" class="btn btn-danger" style="padding:0.25rem 0.5rem; font-size:0.8rem;" onclick="forceDeleteTask(${task.id})"><i class="ph ph-trash"></i> Eliminar</button>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-muted);">La papelera está vacía</td></tr>';
            }
        } catch(e) { console.error(e); }
    }

    async function restoreTask(id) {
        if(!confirm('¿Restaurar esta tarea? Volverá al tablero.')) return;
        const fd = new FormData();
        fd.append('action', 'restore_task');
        fd.append('id', id);
        try {
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                fetchTrash();
                fetchTasks();
            }
        } catch(e) { console.error(e); }
    }

    async function forceDeleteTask(id) {
        if(!confirm('¿Eliminar PERMANENTEMENTE esta tarea? Esta acción NO se puede deshacer.')) return;
        const fd = new FormData();
        fd.append('action', 'force_delete_task');
        fd.append('id', id);
        try {
            const res = await fetch('modules/design_tasks/ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                fetchTrash();
            }
        } catch(e) { console.error(e); }
    }

    setInterval(() => {
        document.querySelectorAll('.live-timer').forEach(el => {
            const startedAt = el.dataset.started;
            const spent = parseInt(el.dataset.spent) || 0;
            if (startedAt) {
                const start = new Date(startedAt.replace(' ', 'T')).getTime();
                const now = new Date().getTime();
                const total = spent + Math.floor((now - start) / 1000);
                
                const h = Math.floor(total / 3600);
                const m = Math.floor((total % 3600) / 60);
                const s = total % 60;
                
                let text = `${s}s`;
                if (h > 0) text = `${h}h ${m}m ${s}s`;
                else if (m > 0) text = `${m}m ${s}s`;
                
                el.innerText = text;
            }
        });
    }, 1000);
    // ==========================================
    // IMAGE PIN MODAL LOGIC
    // ==========================================
    let currentPinAttachmentId = null;

    async function openPinModal(attachmentId, imgSrc) {
        currentPinAttachmentId = attachmentId;
        document.getElementById('pin-modal').style.display = 'flex';
        document.getElementById('pin-img').src = imgSrc;
        await loadPins(attachmentId);
    }

    function closePinModal() {
        document.getElementById('pin-modal').style.display = 'none';
        currentPinAttachmentId = null;
    }

    async function loadPins(attachmentId) {
        document.querySelectorAll('.img-pin').forEach(p => p.remove());
        try {
            const res = await fetch(`modules/design_tasks/ajax.php?action=fetch_pins&attachment_id=${attachmentId}`);
            const data = await res.json();
            if(data.success && data.data) {
                data.data.forEach(pin => {
                    renderPin(pin.id, pin.x_pos, pin.y_pos, pin.comment, pin.user_name, pin.user_avatar);
                });
            }
        } catch(e) { console.error(e); }
    }

    function renderPin(id, x, y, comment, userName, userAvatar) {
        const wrapper = document.getElementById('pin-img-wrapper');
        const pinHtml = document.createElement('div');
        pinHtml.className = 'img-pin';
        pinHtml.style.position = 'absolute';
        pinHtml.style.left = `${x}%`;
        pinHtml.style.top = `${y}%`;
        pinHtml.style.transform = 'translate(-50%, -50%)';
        pinHtml.style.cursor = 'pointer';
        pinHtml.style.zIndex = '10';
        
        let avatarStr = `<div style="width:28px;height:28px;border-radius:50%;background:var(--primary-color);color:white;display:flex;align-items:center;justify-content:center;font-size:12px;border:2px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.5);">${userName.charAt(0)}</div>`;
        if (userAvatar) {
            avatarStr = `<img src="${userAvatar}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;border:2px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.5);">`;
        }
        
        pinHtml.innerHTML = `
            ${avatarStr}
            <div class="pin-tooltip" style="display:none; position:absolute; top:35px; left:50%; transform:translateX(-50%); background:white; color:var(--text-main); padding:0.75rem; border-radius:8px; font-size:0.85rem; width:200px; text-align:left; box-shadow:0 10px 25px rgba(0,0,0,0.3); z-index:20;">
                <strong style="display:block; margin-bottom:0.25rem;">${userName}</strong>${comment}
            </div>
        `;
        
        pinHtml.onmouseenter = () => pinHtml.querySelector('.pin-tooltip').style.display = 'block';
        pinHtml.onmouseleave = () => pinHtml.querySelector('.pin-tooltip').style.display = 'none';
        
        wrapper.appendChild(pinHtml);
    }

    async function handleImageClick(e) {
        if (!currentPinAttachmentId) return;
        
        const rect = e.target.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        
        const comment = prompt("Añade un comentario en esta zona de la imagen:");
        if (comment && comment.trim() !== '') {
            const fd = new FormData();
            fd.append('action', 'save_pin');
            fd.append('attachment_id', currentPinAttachmentId);
            fd.append('x', x);
            fd.append('y', y);
            fd.append('comment', comment);
            
            try {
                const res = await fetch('modules/design_tasks/ajax.php', {method: 'POST', body: fd});
                const data = await res.json();
                if(data.success) {
                    const curUser = systemUsers.find(u => u.id == currentUserId);
                    renderPin(data.id, x, y, comment, curUser ? curUser.name : 'Yo', curUser ? curUser.avatar : '');
                }
            } catch(e) { console.error(e); }
        }
    }
    
    // ==========================================
    // TAGS MANAGER LOGIC
    // ==========================================
    let masterTags = [];
    
    async function loadMasterTags() {
        try {
            const res = await fetch('modules/design_tasks/ajax.php?action=fetch_master_tags');
            const data = await res.json();
            if(data.success) {
                masterTags = data.data;
                // Update TomSelect options
                if (tomSelectTags) {
                    tomSelectTags.clearOptions();
                    masterTags.forEach(t => {
                        tomSelectTags.addOption({value: t.name, text: t.name});
                    });
                }
                renderTagsList();
            }
        } catch(e) { console.error(e); }
    }

    function openTagsManager() {
        document.getElementById('tags-manager-modal').style.display = 'flex';
        loadMasterTags();
    }

    function closeTagsManager() {
        document.getElementById('tags-manager-modal').style.display = 'none';
        document.getElementById('tag-form').reset();
        document.getElementById('tag-id').value = '';
    }

    function renderTagsList() {
        const container = document.getElementById('tags-list');
        container.innerHTML = '';
        if (masterTags.length === 0) {
            container.innerHTML = '<div style="text-align:center; color:var(--text-muted); font-size:0.85rem;">No hay etiquetas creadas.</div>';
            return;
        }
        
        masterTags.forEach(tag => {
            const el = document.createElement('div');
            el.style.display = 'flex';
            el.style.justifyContent = 'space-between';
            el.style.alignItems = 'center';
            el.style.padding = '0.5rem';
            el.style.background = 'var(--bg-color)';
            el.style.borderRadius = '6px';
            el.style.border = '1px solid rgba(150,150,150,0.1)';
            
            el.innerHTML = `
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <div style="width:16px; height:16px; border-radius:4px; background:${tag.color};"></div>
                    <span style="font-weight:600; font-size:0.9rem;">${tag.name}</span>
                </div>
                <div style="display:flex; gap:0.25rem;">
                    <button type="button" class="btn-icon" style="width:24px; height:24px; font-size:0.8rem;" onclick="editMasterTag(${tag.id}, '${tag.name.replace(/'/g, "\\'")}', '${tag.color}')"><i class="ph ph-pencil"></i></button>
                    <button type="button" class="btn-icon text-red" style="width:24px; height:24px; font-size:0.8rem;" onclick="deleteMasterTag(${tag.id})"><i class="ph ph-trash"></i></button>
                </div>
            `;
            container.appendChild(el);
        });
    }

    function editMasterTag(id, name, color) {
        document.getElementById('tag-id').value = id;
        document.getElementById('tag-name').value = name;
        document.getElementById('tag-color').value = color;
    }

    async function saveMasterTag() {
        const fd = new FormData();
        fd.append('action', 'save_master_tag');
        fd.append('id', document.getElementById('tag-id').value);
        fd.append('name', document.getElementById('tag-name').value);
        fd.append('color', document.getElementById('tag-color').value);
        
        try {
            const res = await fetch('modules/design_tasks/ajax.php', {method: 'POST', body: fd});
            const data = await res.json();
            if(data.success) {
                document.getElementById('tag-form').reset();
                document.getElementById('tag-id').value = '';
                loadMasterTags();
            } else {
                alert(data.error);
            }
        } catch(e) { console.error(e); }
    }

    async function deleteMasterTag(id) {
        if(!confirm('¿Eliminar esta etiqueta maestra?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_master_tag');
        fd.append('id', id);
        try {
            const res = await fetch('modules/design_tasks/ajax.php', {method: 'POST', body: fd});
            const data = await res.json();
            if(data.success) {
                loadMasterTags();
            }
        } catch(e) { console.error(e); }
    }

    // Call loadMasterTags on init
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => { loadMasterTags(); }, 500);
    });
