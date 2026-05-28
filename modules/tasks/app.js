const TC={tasks:[],stats:{},view:'kanban',filterSource:'all',filterUser:window.TC_IS_ADMIN?'all':'me',colors:['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#0ea5e9','#14b8a6'],statusOrder:['pending','in_progress','in_review','completed'],sLabels:{pending:'Pendiente',in_progress:'En Progreso',in_review:'En Revisión',completed:'Completada'},pollVersion:null,pollTimer:null,
init(){this.loadTasks();this.bindEvents();this.startPolling();this.initMobileSwipe();this.initPullToRefresh();this.checkOverdue();},
async loadTasks(){this.showLoading();const fd=new FormData();fd.append('action_type','get_all_tasks');fd.append('filter_source',this.filterSource);fd.append('filter_user',this.filterUser);try{const r=await fetch('modules/tasks/ajax.php',{method:'POST',body:fd});const d=await r.json();if(d.success){this.tasks=d.tasks;this.stats=d.stats;this.pollVersion=d.stats.version;this.wrappedSet=new Set(this.tasks.filter(t=>t.context?.template_source).map(t=>`${t.context.template_source}_${t.context.template_source_id}`));this.renderAll();}}catch(e){console.error(e);}},
async updateStatus(src,id,st){const c=this.tasks.find(t=>t.source===src&&t.source_id==id);const oldSt=c?c.status:null;const fd=new FormData();fd.append('action_type','update_status');fd.append('source',src);fd.append('source_id',id);fd.append('status',st);try{const r=await fetch('modules/tasks/ajax.php',{method:'POST',body:fd});const d=await r.json();if(d.success){if(st==='completed')this.confetti();this.loadTasks();}else alert(d.error||'Error');}catch(e){alert('Error de conexión');}},
renderAll(){this.renderKPIs();this.renderKanban();this.renderList();this.renderWorkload();if(this.calendar)this.renderCalendar();},
renderKPIs(){const s=this.stats;const wk=s.this_week_completed||0,lw=s.last_week_completed||0;const diff=wk-lw;const cmp=lw>0?`<div class="tc-kpi-compare ${diff>=0?'up':'down'}"><i class="ph ph-trend-${diff>=0?'up':'down'}"></i>${diff>=0?'+':''}${diff}</div>`:'';
const items=[{c:'kpi-pending',i:'ph-clock',v:s.pending,l:'Pendientes'},{c:'kpi-progress',i:'ph-spinner',v:s.in_progress,l:'En Progreso'},{c:'kpi-review',i:'ph-eye',v:s.in_review,l:'En Revisión'},{c:'kpi-done',i:'ph-check-circle',v:s.completed,l:'Completadas'},{c:'kpi-week',i:'ph-chart-line-up',v:wk,l:'Esta semana',extra:cmp}];
if(s.overdue>0)items.push({c:'kpi-overdue',i:'ph-warning',v:s.overdue,l:'Vencidas'});
document.getElementById('tc-kpis').innerHTML=items.map(k=>`<div class="tc-kpi ${k.c}"><div class="tc-kpi-icon"><i class="ph ${k.i}"></i></div><div><div class="tc-kpi-value" data-t="${k.v}">0</div><div class="tc-kpi-label">${k.l}</div>${k.extra||''}</div></div>`).join('');
document.querySelectorAll('.tc-kpi-value').forEach(el=>{let t=+el.dataset.t,c=0;if(!t){el.textContent='0';return;}const s=Math.max(1,Math.ceil(t/20));const iv=setInterval(()=>{c+=s;if(c>=t){c=t;clearInterval(iv);}el.textContent=c;},40);});},
renderKanban(){this.statusOrder.forEach(st=>{const b=document.getElementById(`tc-col-${st}`),cnt=document.getElementById(`tc-count-${st}`);if(!b)return;const ts=this.tasks.filter(t=>t.status===st && !this.wrappedSet.has(`${t.source}_${t.source_id}`));cnt.textContent=ts.length;b.innerHTML=ts.length?ts.map(t=>this.card(t)).join(''):'<div class="tc-empty"><i class="ph ph-tray"></i>Sin tareas</div>';});this.updateDots();},
card(t, isNested=false){const now=Date.now(),dMs=t.due_date?new Date(t.due_date+'T23:59:59').getTime():null,ov=dMs&&dMs<now&&t.status!=='completed',ds=dMs&&!ov&&dMs<=now+2592e5&&t.status!=='completed';

if(!isNested && t.context?.template_source) {
    const tmpl = this.tasks.find(x => x.source === t.context.template_source && x.source_id == t.context.template_source_id);
    if(tmpl) {
        let nestedHtml = this.card(tmpl, true).replace(/draggable="true"/g, '').replace(/onclick="[^"]*"/g, '');
        return `<div class="tc-card wrapper-card" draggable="true" data-src="${t.source}" data-sid="${t.source_id}" ondragstart="TC.ds(event)" ondragend="TC.de(event)" style="padding:0;background:transparent;box-shadow:none;border:none;">
            <div class="tc-create-preview-box" style="padding:1rem;margin-bottom:0;">
                <div class="tc-cp-header" style="margin-bottom:0.75rem;">
                    <h3 class="tc-cp-title-text" style="font-size:1.1rem;word-break:break-word;flex:1;margin-right:0.5rem;" onclick="TC.openDetail('${t.source}',${t.source_id})">${this.esc(t.title)}</h3>
                    <div class="tc-cp-actions">
                        <button class="tc-cp-btn confirm ${t.status === 'completed' ? 'done' : ''}" onclick="TC.updateStatus('${t.source}',${t.source_id},'completed')" style="width:26px;height:26px;font-size:0.9rem;" title="Terminar"><i class="ph ph-check"></i></button>
                    </div>
                </div>
                <div class="tc-cp-card-wrapper" style="pointer-events:none;">
                    ${nestedHtml}
                </div>
            </div>
        </div>`;
    }
}

let h=`<div class="tc-card ${ov?'is-overdue':''} ${t.is_urgent?'is-urgent':''}" ${!isNested?'draggable="true"':''} data-src="${t.source}" data-sid="${t.source_id}" onclick="TC.openDetail('${t.source}',${t.source_id})" ondragstart="TC.ds(event)" ondragend="TC.de(event)">`;
h+=`<div class="tc-card-source" style="background:${t.source_color}15;color:${t.source_color}"><i class="ph ${t.source_icon}" style="font-size:0.72rem"></i>${t.source_label}</div>`;
if(t.priority)h+=`<span class="tc-card-priority prio-${t.priority}">${t.priority}</span> `;
h+=`<div class="tc-card-title" ondblclick="event.stopPropagation();TC.inlineEdit(this,'${t.source}',${t.source_id})">${this.esc(t.title)}</div>`;
if(t.context.brand)h+=`<div class="tc-card-context"><i class="ph ph-storefront"></i>${this.esc(t.context.brand)}</div>`;
else if(t.description)h+=`<div class="tc-card-context">${this.esc(t.description.substring(0,50))}</div>`;
if(t.subtasks&&t.subtasks.total){const p=Math.round(t.subtasks.completed/t.subtasks.total*100);h+=`<div class="tc-card-progress"><div class="tc-prog-text"><span>Subtareas</span><span>${t.subtasks.completed}/${t.subtasks.total}</span></div><div class="tc-prog-bar"><div class="tc-prog-fill" style="width:${p}%"></div></div></div>`;}
h+=`<div class="tc-card-footer"><div class="tc-avatars">`;
(t.assigned_users||[]).slice(0,4).forEach(u=>{const bg=this.colors[u.id%this.colors.length];h+=`<div class="tc-avatar" style="background:${bg}" data-tooltip="${this.esc(u.name)}">${u.avatar?`<img src="${u.avatar}">`:`${u.initial}`}</div>`;});
h+='</div>';
if(t.due_date){const c=ov?'overdue':ds?'due-soon':'';const dl=new Date(t.due_date+'T00:00:00').toLocaleDateString('es-PE',{day:'2-digit',month:'short'});h+=`<span class="tc-due ${c}"><i class="ph ph-calendar-blank"></i>${dl}</span>`;}
return h+'</div></div>';},
renderList(){const tb=document.getElementById('tc-list-body');if(!tb)return;const vis=this.tasks.filter(t=>!this.wrappedSet.has(`${t.source}_${t.source_id}`));if(!vis.length){tb.innerHTML='<tr><td colspan="6" class="tc-empty">Sin tareas</td></tr>';return;}
tb.innerHTML=vis.map(t=>{const sl=this.sLabels[t.status]||t.status;const dl=t.due_date?new Date(t.due_date+'T00:00:00').toLocaleDateString('es-PE',{day:'2-digit',month:'short',year:'numeric'}):'—';
const av=(t.assigned_users||[]).map(u=>`<div class="tc-avatar" style="background:${this.colors[u.id%this.colors.length]}" data-tooltip="${this.esc(u.name)}">${u.avatar?`<img src="${u.avatar}">`:u.initial}</div>`).join('');
return `<tr style="cursor:pointer" onclick="TC.openDetail('${t.source}',${t.source_id})"><td data-label="Título"><div style="display:flex;align-items:center;gap:0.5rem"><i class="ph ${t.source_icon}" style="color:${t.source_color}"></i><span style="font-weight:600;color:var(--color-title)">${this.esc(t.title)}</span>${t.is_urgent?'🔥':''}</div></td><td data-label="Estado"><span class="tc-status-badge badge-${t.status}">${sl}</span></td><td data-label="Prioridad">${t.priority?`<span class="tc-card-priority prio-${t.priority}">${t.priority}</span>`:'—'}</td><td data-label="Fecha">${dl}</td><td data-label="Asignados"><div class="tc-avatars" style="justify-content:flex-start">${av}</div></td><td data-label="Módulo" style="font-size:0.78rem;color:var(--text-muted)">${t.source_label}</td></tr>`;}).join('');},
renderWorkload(){const el=document.getElementById('tc-workload');if(!el||!this.stats.workload)return;const wl=this.stats.workload;if(!wl.length){el.style.display='none';return;}
el.style.display='';const mx=Math.max(...wl.map(w=>w.count));const cls=['#3b82f6','#8b5cf6','#10b981','#f59e0b','#ef4444','#0ea5e9'];
el.innerHTML=`<h4><i class="ph ph-users"></i> Carga de Trabajo</h4>${wl.slice(0,6).map((w,i)=>`<div class="tc-wl-row"><div class="tc-wl-name">${this.esc(w.name)}</div><div class="tc-wl-bar"><div class="tc-wl-fill" style="width:${(w.count/mx*100)}%;background:${cls[i%cls.length]}"></div></div><div class="tc-wl-count">${w.count}</div></div>`).join('')}`;},
renderCalendar(){if(!window.FullCalendar)return;const el=document.getElementById('tc-cal');if(!el)return;
const makeEvents=()=>this.tasks.filter(t=>t.due_date && !this.wrappedSet.has(`${t.source}_${t.source_id}`)).map(t=>{const prioColors={alta:'#ef4444',media:'#f59e0b',baja:'#10b981'};return{title:t.title,start:t.due_date,extendedProps:{source:t.source,source_id:t.source_id,source_icon:t.source_icon,priority:t.priority,status:t.status,prioColor:prioColors[t.priority]||null}};});
if(this.calInstance){this.calInstance.removeAllEvents();this.calInstance.addEventSource(makeEvents());return;}
this.calInstance=new FullCalendar.Calendar(el,{initialView:'dayGridMonth',locale:'es',dayMaxEvents:3,headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,dayGridWeek'},
events:makeEvents(),
eventContent:function(arg){const p=arg.event.extendedProps;const src=p.source||'task';const completed=p.status==='completed';let prioHtml='';if(p.prioColor)prioHtml=`<span class="cal-prio" style="background:${p.prioColor}"></span>`;
return{html:`<div class="tc-cal-event src-${src} ${completed?'evt-completed':''}"><i class="ph ${p.source_icon||'ph-check-square-offset'} cal-icon"></i><span class="cal-title">${arg.event.title}</span>${prioHtml}</div>`};},
eventClick:info=>{this.openDetail(info.event.extendedProps.source,info.event.extendedProps.source_id);},
height:'auto'});this.calInstance.render();},
// Detail offcanvas
async openDetail(src,sid){const ov=document.getElementById('tc-oc-overlay'),pn=document.getElementById('tc-oc-panel'),bd=document.getElementById('tc-oc-body');pn.dataset.source=src;pn.dataset.sourceId=sid;ov.classList.add('active');pn.classList.add('active');bd.innerHTML='<div style="text-align:center;padding:3rem"><i class="ph ph-spinner ph-spin" style="font-size:2rem;color:var(--primary-color)"></i></div>';
const fd=new FormData();fd.append('action_type','get_task_details');fd.append('source',src);fd.append('source_id',sid);
try{const r=await fetch('modules/tasks/ajax.php',{method:'POST',body:fd});const d=await r.json();if(!d.success){bd.innerHTML=`<div class="tc-empty">${d.error}</div>`;return;}this.showDetail(d,src,bd);}catch(e){bd.innerHTML='<div class="tc-empty">Error</div>';}},
showDetail(data,src,bd){const t=data.task,us=data.assigned_users||[],subs=data.subtasks||[],tags=data.tags||[],allU=data.all_users||[];
const nt=this.tasks.find(x=>x.source===src&&x.source_id==t.id);const sc=nt?.source_color||'#64748b',sl=nt?.source_label||'Tarea',si=nt?.source_icon||'ph-check-square-offset',lk=nt?.link;
let h=`<div class="tc-oc-section"><div class="tc-card-source" style="background:${sc}15;color:${sc};margin-bottom:1rem"><i class="ph ${si}"></i>${sl}</div><h2 style="margin:0 0 0.5rem;font-size:1.3rem;color:var(--color-title)">${this.esc(t.title||t.brand_name||'')}</h2>`;
if(t.description)h+=`<p style="color:var(--text-muted);line-height:1.6;margin:0">${this.esc(t.description)}</p>`;
h+='</div>';
// Urgent toggle (only regular tasks)
if(src==='task'){const isU=t.is_urgent==1;h+=`<div class="tc-oc-section"><button class="tc-oc-urgent-btn ${isU?'on':'off'}" onclick="TC.toggleUrgent(${t.id})"><i class="ph ph-fire"></i>${isU?'Urgente — Clic para quitar':'Marcar como urgente'}</button></div>`;}
// Priority
if(src==='design_task'&&t.priority)h+=`<div class="tc-oc-section"><h4>Prioridad</h4><span class="tc-card-priority prio-${t.priority}" style="font-size:0.8rem;padding:0.3rem 0.7rem">${t.priority}</span></div>`;
// Tags
if(tags.length)h+=`<div class="tc-oc-section"><h4>Etiquetas</h4><div style="display:flex;gap:0.4rem;flex-wrap:wrap">${tags.map(tg=>`<span style="padding:0.25rem 0.6rem;border-radius:6px;font-size:0.78rem;font-weight:600;background:${tg.color}20;color:${tg.color}">${this.esc(tg.name)}</span>`).join('')}</div></div>`;
// Assigned users with reassign
if(src!=='project_month'){h+=`<div class="tc-oc-section"><h4>Asignados <button onclick="document.getElementById('tc-reassign').style.display=document.getElementById('tc-reassign').style.display==='none'?'block':'none'" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:0.78rem;font-weight:600"><i class="ph ph-pencil"></i> Editar</button></h4>`;
h+=`<div style="display:flex;flex-direction:column;gap:0.5rem;margin-bottom:0.5rem">`;
us.forEach(u=>{const bg=this.colors[u.id%this.colors.length];h+=`<div style="display:flex;align-items:center;gap:0.75rem"><div class="tc-avatar" style="background:${bg};margin-left:0;width:32px;height:32px;font-size:0.75rem">${u.avatar?`<img src="${u.avatar}">`:u.initial}</div><span style="font-weight:500">${this.esc(u.name)}</span></div>`;});
h+=`</div><div id="tc-reassign" style="display:none;margin-top:0.5rem"><select id="tc-reassign-sel" multiple style="width:100%;padding:0.5rem;border:1px solid rgba(150,150,150,0.2);border-radius:8px;background:var(--bg-color);color:var(--text-main);min-height:100px">`;
allU.forEach(u=>{const sel=us.some(x=>x.id===u.id)?'selected':'';h+=`<option value="${u.id}" ${sel}>${this.esc(u.name)}</option>`;});
h+=`</select><button onclick="TC.saveAssigned('${src}',${t.id})" style="margin-top:0.5rem;padding:0.4rem 1rem;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600">Guardar</button></div></div>`;}else{h+=`<div class="tc-oc-section"><h4>Equipo</h4><div style="display:flex;flex-direction:column;gap:0.5rem">`;us.forEach(u=>{const bg=this.colors[u.id%this.colors.length];h+=`<div style="display:flex;align-items:center;gap:0.75rem"><div class="tc-avatar" style="background:${bg};margin-left:0;width:32px;height:32px;font-size:0.75rem">${u.avatar?`<img src="${u.avatar}">`:u.initial}</div><span style="font-weight:500">${this.esc(u.name)}</span></div>`;});h+=`</div></div>`;}
// Subtasks
if(src!=='project_month'){const ss=src==='design_task'?'design_task':'task';h+=`<div class="tc-oc-section"><h4>Subtareas (${subs.length})</h4>`;
if(!subs.length)h+=`<p style="color:var(--text-muted);font-size:0.85rem">Sin subtareas</p>`;
else subs.forEach(s=>{const dn=s.is_completed==1;h+=`<div class="tc-oc-subtask ${dn?'done':''}"><input type="checkbox" ${dn?'checked':''} onchange="TC.toggleSub(${s.id},'${ss}')"><span style="flex:1;word-break:break-word;">${s.title}</span></div>`;});
h+=`<div style="margin-top:1rem;display:flex;gap:0.5rem;"><input type="text" id="tc-oc-new-subtask" placeholder="Añadir tarjeta (Pega con Ctrl+V)" style="flex:1;padding:0.5rem;border:1px solid rgba(150,150,150,0.2);border-radius:8px;background:var(--bg-color);color:var(--text-main);" onkeydown="if(event.key==='Enter') TC.addSubtask()"><button onclick="TC.addSubtask()" style="padding:0.5rem 1rem;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;"><i class="ph ph-plus"></i></button></div>`;
h+='</div>';}
if(lk)h+=`<div class="tc-oc-section"><a href="${lk}" class="tc-oc-link-btn"><i class="ph ph-arrow-square-out"></i>Ir al módulo</a></div>`;
bd.innerHTML=h;},
closeDetail(){document.getElementById('tc-oc-overlay').classList.remove('active');document.getElementById('tc-oc-panel').classList.remove('active');},
// Actions
async toggleSub(id,src){const fd=new FormData();fd.append('action_type','toggle_subtask');fd.append('id',id);fd.append('source',src);await fetch('modules/tasks/ajax.php',{method:'POST',body:fd});const p=document.getElementById('tc-oc-panel');if(p.dataset.source)this.openDetail(p.dataset.source,p.dataset.sourceId);this.loadTasks();},
async addSubtask(title='', imageBase64='') {
    const input = document.getElementById('tc-oc-new-subtask');
    if(input && !title) title = input.value.trim();
    if(!title && !imageBase64) return;
    
    const p = document.getElementById('tc-oc-panel');
    const src = p.dataset.source;
    const sid = p.dataset.sourceId;
    if(!src || !sid) return;
    
    const fd = new FormData();
    fd.append('action_type', 'add_subtask');
    fd.append('source', src);
    fd.append('source_id', sid);
    fd.append('title', title);
    if(imageBase64) fd.append('image', imageBase64);
    
    if(input) { input.disabled = true; input.placeholder = 'Guardando...'; }
    try {
        const r = await fetch('modules/tasks/ajax.php', { method: 'POST', body: fd });
        const d = await r.json();
        if(d.success) {
            this.openDetail(src, sid);
            this.loadTasks();
        } else {
            this.toast(d.error || 'Error al crear subtarea', 'danger');
            if(input) { input.disabled = false; input.value = title; input.focus(); }
        }
    } catch(e) {
        this.toast('Error de conexión', 'danger');
        if(input) { input.disabled = false; input.focus(); }
    }
},
async toggleUrgent(id){const fd=new FormData();fd.append('action_type','toggle_urgent');fd.append('source_id',id);await fetch('modules/tasks/ajax.php',{method:'POST',body:fd});const p=document.getElementById('tc-oc-panel');if(p.dataset.source)this.openDetail(p.dataset.source,p.dataset.sourceId);this.loadTasks();},
async saveAssigned(src,id){const sel=document.getElementById('tc-reassign-sel');const ids=[...sel.selectedOptions].map(o=>o.value);const fd=new FormData();fd.append('action_type','update_assigned');fd.append('source',src);fd.append('source_id',id);ids.forEach(i=>fd.append('user_ids[]',i));await fetch('modules/tasks/ajax.php',{method:'POST',body:fd});this.openDetail(src,id);this.loadTasks();},
// Inline edit
inlineEdit(el,src,sid){if(src==='project_month')return;el.contentEditable='true';el.focus();const orig=el.textContent;const save=async()=>{el.contentEditable='false';const nv=el.textContent.trim();if(nv&&nv!==orig){const fd=new FormData();fd.append('action_type','update_title');fd.append('source',src);fd.append('source_id',sid);fd.append('title',nv);await fetch('modules/tasks/ajax.php',{method:'POST',body:fd});}else el.textContent=orig;};
el.onblur=save;el.onkeydown=e=>{if(e.key==='Enter'){e.preventDefault();el.blur();}if(e.key==='Escape'){el.textContent=orig;el.blur();}};},
// Drag & drop
ds(e){const c=e.target.closest('.tc-card');if(!c)return;e.dataTransfer.setData('src',c.dataset.src);e.dataTransfer.setData('sid',c.dataset.sid);e.dataTransfer.effectAllowed='move';setTimeout(()=>c.classList.add('dragging'),0);},
de(e){e.target.closest('.tc-card')?.classList.remove('dragging');document.querySelectorAll('.tc-col-body').forEach(b=>b.classList.remove('drag-over'));},
dov(e){e.preventDefault();e.target.closest('.tc-col-body')?.classList.add('drag-over');},
dlv(e){const b=e.target.closest('.tc-col-body');if(b&&!b.contains(e.relatedTarget))b.classList.remove('drag-over');},
drp(e){e.preventDefault();const b=e.target.closest('.tc-col-body');if(!b)return;b.classList.remove('drag-over');const src=e.dataTransfer.getData('src'),sid=e.dataTransfer.getData('sid'),ns=b.dataset.status;if(src&&sid&&ns)this.updateStatus(src,sid,ns);},
// Confetti
confetti(){const colors=['#f59e0b','#10b981','#3b82f6','#ef4444','#8b5cf6','#ec4899'];for(let i=0;i<40;i++){const el=document.createElement('div');el.className='confetti-piece';el.style.cssText=`left:${Math.random()*100}vw;background:${colors[i%colors.length]};width:${6+Math.random()*8}px;height:${6+Math.random()*8}px;border-radius:${Math.random()>0.5?'50%':'2px'};animation:confettiFall ${1.5+Math.random()*2}s ease forwards;animation-delay:${Math.random()*0.5}s`;document.body.appendChild(el);setTimeout(()=>el.remove(),4000);}},
// View switching
switchView(v){this.view=v;document.getElementById('tc-kanban-view').style.display=v==='kanban'?'grid':'none';document.getElementById('tc-list-view').style.display=v==='list'?'block':'none';document.getElementById('tc-calendar-view').style.display=v==='calendar'?'block':'none';document.querySelectorAll('.tc-view-btn').forEach(b=>b.classList.toggle('active',b.dataset.view===v));if(v==='calendar'){this.calendar=true;this.renderCalendar();}},
setFilterSource(s,el){this.filterSource=s;document.querySelectorAll('.tc-pill[data-filter]').forEach(p=>p.classList.remove('active'));el.classList.add('active');this.loadTasks();},
setFilterUser(v){this.filterUser=v;this.loadTasks();},
// Polling (live updates)
startPolling(){this.pollTimer=setInterval(async()=>{try{const fd=new FormData();fd.append('action_type','check_version');const r=await fetch('modules/tasks/ajax.php',{method:'POST',body:fd});const d=await r.json();if(d.success&&d.version!==this.pollVersion){this.pollVersion=d.version;this.loadTasks();}}catch(e){}},20000);},
// Toast
toast(msg,type='warning'){const c=document.getElementById('tc-toasts');if(!c)return;const t=document.createElement('div');t.className=`tc-toast ${type}`;t.innerHTML=`<i class="ph ph-${type==='danger'?'warning-circle':type==='success'?'check-circle':'bell'}"></i><span style="font-size:0.85rem">${msg}</span>`;c.appendChild(t);setTimeout(()=>{t.style.animation='toastOut 0.3s ease forwards';setTimeout(()=>t.remove(),300);},5000);},
// Overdue check
checkOverdue(){setTimeout(()=>{const ov=this.tasks.filter(t=>t.due_date&&new Date(t.due_date+'T23:59:59')<new Date()&&t.status!=='completed');if(ov.length)this.toast(`Tienes ${ov.length} tarea${ov.length>1?'s':''} vencida${ov.length>1?'s':''}`,'danger');},3000);},
// Mobile swipe
initMobileSwipe(){const kb=document.getElementById('tc-kanban-view');if(!kb)return;let sx=0;kb.addEventListener('touchstart',e=>{sx=e.touches[0].clientX;},{passive:true});kb.addEventListener('scroll',()=>this.updateDots(),{passive:true});},
updateDots(){const kb=document.getElementById('tc-kanban-view');const dots=document.querySelectorAll('.tc-col-dot-indicator');if(!kb||!dots.length)return;const sw=kb.scrollWidth-kb.clientWidth;if(sw<=0)return;const ratio=kb.scrollLeft/sw;const idx=Math.round(ratio*(dots.length-1));dots.forEach((d,i)=>d.classList.toggle('active',i===idx));},
scrollToCol(i){const kb=document.getElementById('tc-kanban-view');const cols=kb?.querySelectorAll('.tc-column');if(cols&&cols[i])cols[i].scrollIntoView({behavior:'smooth',inline:'center'});},
// Pull to refresh
initPullToRefresh(){const w=document.querySelector('.content-wrapper');if(!w)return;let sy=0,pulling=false;const ptr=document.getElementById('tc-ptr');
w.addEventListener('touchstart',e=>{if(w.scrollTop===0)sy=e.touches[0].clientY;},{passive:true});
w.addEventListener('touchmove',e=>{if(!sy)return;const d=e.touches[0].clientY-sy;if(d>0&&d<150&&w.scrollTop===0){pulling=true;if(ptr){ptr.style.display='block';ptr.classList.toggle('pulling',d>60);}}},{passive:true});
w.addEventListener('touchend',()=>{if(pulling&&ptr){ptr.classList.add('refreshing');this.loadTasks().then(()=>{ptr.classList.remove('refreshing','pulling');ptr.style.display='none';});}sy=0;pulling=false;},{passive:true});},
// Loading
showLoading(){this.statusOrder.forEach(s=>{const b=document.getElementById(`tc-col-${s}`);if(b)b.innerHTML=[1,2].map(()=>'<div class="tc-skeleton" style="height:120px"></div>').join('');});},
esc(s){if(!s)return'';const d=document.createElement('div');d.textContent=s;return d.innerHTML;},

// Creation Offcanvas
openCreateOC() {
    document.getElementById('tc-create-title').value = '';
    document.getElementById('tc-create-search').value = '';
    document.getElementById('tc-create-step1').style.display = 'block';
    document.getElementById('tc-create-step2').style.display = 'none';
    
    // Filter out 'completed' tasks for templates to keep list relevant
    this.creationTemplates = this.tasks.filter(t => t.status !== 'completed');
    this.renderTemplates();
    
    document.getElementById('tc-oc-overlay').classList.add('active');
    document.getElementById('tc-create-panel').classList.add('active');
    setTimeout(() => document.getElementById('tc-create-title').focus(), 100);
},
closeCreateOC() {
    document.getElementById('tc-oc-overlay').classList.remove('active');
    document.getElementById('tc-create-panel').classList.remove('active');
},
filterTemplates(val) {
    const term = val.toLowerCase();
    this.creationTemplates = this.tasks.filter(t => 
        t.status !== 'completed' && 
        (t.title.toLowerCase().includes(term) || (t.context?.brand||'').toLowerCase().includes(term))
    );
    this.renderTemplates();
},
renderTemplates() {
    const c = document.getElementById('tc-create-templates');
    if (!this.creationTemplates.length) {
        c.innerHTML = '<div class="tc-empty" style="padding:1rem;">No hay tareas disponibles</div>';
        return;
    }
    c.innerHTML = this.creationTemplates.map(t => {
        return `<div class="tc-template-item" onclick="TC.selectTemplate('${t.source}', ${t.source_id})">
            <div style="display:flex;align-items:center;gap:0.4rem;margin-bottom:0.3rem">
                <i class="ph ${t.source_icon}" style="color:${t.source_color};font-size:0.8rem"></i>
                <span style="font-size:0.7rem;font-weight:700;color:${t.source_color};text-transform:uppercase">${t.source_label}</span>
            </div>
            <div class="tc-template-title">${this.esc(t.title)}</div>
        </div>`;
    }).join('');
},
selectTemplate(src, sid) {
    const title = document.getElementById('tc-create-title').value.trim();
    if (!title) {
        this.toast('Por favor, ingresa un título para la tarea primero', 'warning');
        document.getElementById('tc-create-title').focus();
        return;
    }
    this.selectedTemplate = this.tasks.find(t => t.source === src && t.source_id == sid);
    if (!this.selectedTemplate) return;
    
    document.getElementById('tc-create-step1').style.display = 'none';
    document.getElementById('tc-create-step2').style.display = 'block';
    
    document.getElementById('tc-cp-title').textContent = title;
    
    // Render cloned card
    const wr = document.getElementById('tc-cp-card-wrapper');
    wr.innerHTML = this.card(this.selectedTemplate).replace(/draggable="true"/g, '').replace(/onclick="[^"]*"/g, '');
},
cancelCreatePreview() {
    document.getElementById('tc-create-step2').style.display = 'none';
    document.getElementById('tc-create-step1').style.display = 'block';
    this.selectedTemplate = null;
},
async confirmCreateTemplate() {
    const title = document.getElementById('tc-cp-title').textContent;
    if(!this.selectedTemplate) return;
    
    const fd = new FormData();
    fd.append('action_type', 'create_from_template');
    fd.append('title', title);
    fd.append('template_source', this.selectedTemplate.source);
    fd.append('template_source_id', this.selectedTemplate.source_id);
    
    this.closeCreateOC();
    this.showLoading();
    
    try {
        const r = await fetch('modules/tasks/ajax.php', { method: 'POST', body: fd });
        const d = await r.json();
        if(d.success) {
            this.toast('Tarea creada exitosamente', 'success');
            this.loadTasks();
        } else {
            this.toast(d.error || 'Error al crear la tarea', 'danger');
            this.loadTasks(); // reload to clear loading
        }
    } catch(e) {
        this.toast('Error de conexión', 'danger');
        this.loadTasks();
    }
},
async confirmCreateEmpty() {
    const title = document.getElementById('tc-create-title').value.trim();
    if (!title) {
        this.toast('Por favor, ingresa un título para la tarea', 'warning');
        document.getElementById('tc-create-title').focus();
        return;
    }
    
    const fd = new FormData();
    fd.append('action_type', 'create_task');
    fd.append('title', title);
    
    this.closeCreateOC();
    this.showLoading();
    
    try {
        const r = await fetch('modules/tasks/ajax.php', { method: 'POST', body: fd });
        const d = await r.json();
        if(d.success) {
            this.toast('Tarea creada exitosamente', 'success');
            this.loadTasks();
        } else {
            this.toast(d.error || 'Error al crear la tarea', 'danger');
            this.loadTasks();
        }
    } catch(e) {
        this.toast('Error de conexión', 'danger');
        this.loadTasks();
    }
},

async deleteTask(src, id) {
    if(!confirm('¿Cancelar esta tarea?')) return;
    const fd = new FormData();
    fd.append('action_type', 'delete_task');
    fd.append('source', src);
    fd.append('source_id', id);
    try {
        const r = await fetch('modules/tasks/ajax.php', { method: 'POST', body: fd });
        const d = await r.json();
        if(d.success) {
            this.toast('Tarea cancelada', 'success');
            this.loadTasks();
        } else {
            this.toast(d.error || 'Error al eliminar', 'danger');
        }
    } catch(e) {
        this.toast('Error de conexión', 'danger');
    }
},

bindEvents(){
    document.getElementById('tc-oc-overlay')?.addEventListener('click',()=>this.closeDetail());
    
    // Paste Event Listener for creating subtasks
    document.addEventListener('paste', async (e) => {
        const p = document.getElementById('tc-oc-panel');
        if(!p || !p.classList.contains('active')) return;
        
        // If they are pasting inside an input that is not our new-subtask input, let it be.
        const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
        const activeId = document.activeElement ? document.activeElement.id : '';
        if((activeTag === 'input' || activeTag === 'textarea' || document.activeElement.isContentEditable) && activeId !== 'tc-oc-new-subtask') {
            return;
        }

        e.preventDefault(); // Stop default paste since we handle it here

        let clipboardText = (e.clipboardData || window.clipboardData).getData('text');
        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        let imageItem = null;

        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                imageItem = items[i];
                break;
            }
        }

        if (imageItem) {
            const blob = imageItem.getAsFile();
            const reader = new FileReader();
            reader.onload = (event) => {
                TC.addSubtask(clipboardText, event.target.result);
            };
            reader.readAsDataURL(blob);
        } else if (clipboardText.trim() !== '') {
            TC.addSubtask(clipboardText.trim());
        }
    });
}
};
document.addEventListener('DOMContentLoaded',()=>TC.init());
