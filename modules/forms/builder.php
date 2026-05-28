<?php
// modules/forms/builder.php — Google Forms-style Builder
if (!isset($_SESSION['user_id'])) { header("Location: index.php?module=auth&action=login"); exit(); }
$id = $_GET['id'] ?? '';
$formData = null;
if (!empty($id)) { $stmt = $db->prepare("SELECT * FROM form_templates WHERE id = ?"); $stmt->execute([$id]); $formData = $stmt->fetch(PDO::FETCH_ASSOC); }
$primaryColor = $global_settings['primary_color'] ?? '#4f46e5';
$logoLight = $global_settings['logo_light'] ?? '';
require_once 'includes/header.php';
?>
<style>
.gf-wrap{max-width:680px;margin:0 auto;padding:1.5rem 1rem 4rem;position:relative}
.gf-topbar{position:sticky;top:0;z-index:20;background:var(--bg-color);border-bottom:1px solid var(--border-color);margin:-1.5rem -1rem 1.5rem;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center;gap:.5rem}
.gf-topbar-left{display:flex;align-items:center;gap:.6rem}
.gf-topbar h2{margin:0;font-size:1rem;font-weight:700;color:var(--color-title)}

/* Title Card */
.gf-title-card{background:var(--bg-surface);border:1px solid var(--border-color);border-radius:12px;padding:0;margin-bottom:1rem;overflow:hidden;border-top:4px solid <?php echo $primaryColor; ?>}
.gf-title-card-inner{padding:1.25rem 1.5rem}
.gf-title-input{width:100%;border:none;border-bottom:2px solid transparent;font-size:1.5rem;font-weight:700;color:var(--color-title);background:transparent;font-family:inherit;padding:.25rem 0;outline:none;transition:border-color .2s}
.gf-title-input:focus{border-bottom-color:<?php echo $primaryColor; ?>}
.gf-desc-input{width:100%;border:none;border-bottom:1px solid transparent;font-size:.9rem;color:var(--text-muted);background:transparent;font-family:inherit;padding:.25rem 0;outline:none;margin-top:.5rem;resize:none;transition:border-color .2s}
.gf-desc-input:focus{border-bottom-color:var(--border-color)}

/* Question Cards */
.gf-card{background:var(--bg-surface);border:1px solid var(--border-color);border-radius:12px;padding:0;margin-bottom:.75rem;position:relative;transition:box-shadow .2s,border-color .2s;cursor:pointer}
.gf-card.active{border-color:transparent;box-shadow:0 2px 12px rgba(0,0,0,.08);border-left:4px solid <?php echo $primaryColor; ?>}
.gf-card:not(.active):hover{box-shadow:0 1px 6px rgba(0,0,0,.05)}
.gf-card-drag{display:flex;justify-content:center;padding:4px 0 0;cursor:grab;color:var(--text-muted);opacity:0;transition:opacity .15s}
.gf-card:hover .gf-card-drag,.gf-card.active .gf-card-drag{opacity:1}
.gf-card-body{padding:.75rem 1.5rem 1rem}

/* Collapsed (inactive) card */
.gf-card:not(.active) .gf-active-only{display:none}
.gf-card:not(.active) .gf-card-body{padding:.75rem 1.5rem}

/* Question header row */
.gf-q-header{display:flex;gap:.75rem;align-items:flex-start;margin-bottom:.75rem}
.gf-q-label{flex:1;font-size:.95rem;font-weight:500;color:var(--color-title);border:none;border-bottom:1px solid transparent;background:transparent;font-family:inherit;padding:.3rem 0;outline:none}
.gf-card.active .gf-q-label{background:var(--bg-color);border-radius:6px;padding:.5rem .6rem;border-bottom:2px solid <?php echo $primaryColor; ?>}
.gf-type-select{padding:.4rem .6rem;border:1px solid var(--border-color);border-radius:8px;font-size:.8rem;font-family:inherit;background:var(--bg-color);color:var(--color-text);min-width:150px;cursor:pointer}

/* Collapsed preview */
.gf-preview-hint{font-size:.85rem;color:var(--text-muted);padding:0 0 .25rem}
.gf-preview-hint input,.gf-preview-hint textarea{pointer-events:none;width:100%;border:none;border-bottom:1px solid var(--border-color);background:transparent;padding:.3rem 0;font-family:inherit;font-size:.85rem;color:var(--text-muted)}

/* Options */
.gf-opt-list{display:flex;flex-direction:column;gap:.4rem;margin-top:.5rem}
.gf-opt-row{display:flex;align-items:center;gap:.5rem}
.gf-opt-row .opt-icon{width:18px;height:18px;border:2px solid #bbb;border-radius:50%;flex-shrink:0}
.gf-opt-row.checkbox-style .opt-icon{border-radius:3px}
.gf-opt-row input{flex:1;border:none;border-bottom:1px solid var(--border-color);padding:.3rem 0;font-size:.85rem;background:transparent;font-family:inherit;color:var(--color-text);outline:none}
.gf-opt-row input:focus{border-bottom-color:<?php echo $primaryColor; ?>}
.gf-opt-row .opt-del{background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:1.1rem;padding:.15rem;opacity:0;transition:opacity .15s}
.gf-opt-row:hover .opt-del{opacity:1}
.gf-opt-row .opt-del:hover{color:var(--danger-color)}
.gf-add-opt{color:var(--text-muted);font-size:.85rem;display:flex;align-items:center;gap:.5rem;margin-top:.5rem;cursor:text}
.gf-add-opt span{color:<?php echo $primaryColor; ?>;cursor:pointer;font-weight:500}
.gf-add-opt span:hover{text-decoration:underline}

/* File zone preview */
.gf-file-preview{border:2px dashed var(--border-color);border-radius:8px;padding:1.2rem;text-align:center;color:var(--text-muted);font-size:.8rem}

/* Section divider card */
.gf-section-card{border-top:4px solid <?php echo $primaryColor; ?>}
.gf-section-title{font-size:1.1rem;font-weight:600;color:var(--color-title);border:none;border-bottom:1px solid transparent;background:transparent;font-family:inherit;padding:.3rem 0;width:100%;outline:none}
.gf-card.active .gf-section-title{border-bottom-color:<?php echo $primaryColor; ?>}
.gf-section-desc{font-size:.85rem;color:var(--text-muted);border:none;border-bottom:1px solid transparent;background:transparent;font-family:inherit;padding:.2rem 0;width:100%;outline:none;margin-top:.25rem}
.gf-card.active .gf-section-desc{border-bottom-color:var(--border-color)}

/* Card footer */
.gf-card-footer{display:flex;align-items:center;justify-content:flex-end;gap:.25rem;padding:.5rem 1.5rem .75rem;border-top:1px solid var(--border-color)}
.gf-card-footer button{background:none;border:none;cursor:pointer;padding:.4rem;border-radius:6px;color:var(--text-muted);font-size:1.15rem;transition:background .15s,color .15s}
.gf-card-footer button:hover{background:var(--bg-color);color:var(--color-title)}
.gf-card-footer .del-btn:hover{color:var(--danger-color)}
.gf-card-footer .divider-v{width:1px;height:24px;background:var(--border-color);margin:0 .5rem}
.gf-required-toggle{display:flex;align-items:center;gap:.4rem;font-size:.8rem;color:var(--color-text);font-weight:500}
.gf-toggle{position:relative;width:36px;height:20px;cursor:pointer}
.gf-toggle input{opacity:0;width:0;height:0}
.gf-toggle-slider{position:absolute;inset:0;background:#ccc;border-radius:20px;transition:.2s}
.gf-toggle-slider:before{content:'';position:absolute;height:16px;width:16px;left:2px;bottom:2px;background:#fff;border-radius:50%;transition:.2s}
.gf-toggle input:checked+.gf-toggle-slider{background:<?php echo $primaryColor; ?>}
.gf-toggle input:checked+.gf-toggle-slider:before{transform:translateX(16px)}

/* Floating sidebar toolbar — positioned via JS */
.gf-toolbar{position:fixed;top:50%;transform:translateY(-50%);display:flex;flex-direction:column;gap:2px;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:10px;padding:6px;box-shadow:0 2px 12px rgba(0,0,0,.08);z-index:15;transition:left .15s}
.gf-toolbar button{width:40px;height:40px;display:flex;align-items:center;justify-content:center;border:none;background:transparent;cursor:pointer;border-radius:8px;color:var(--text-muted);font-size:1.25rem;transition:background .15s,color .15s;position:relative}
.gf-toolbar button:hover{background:var(--bg-color);color:var(--color-title)}
.gf-toolbar button[title]:hover::after{content:attr(title);position:absolute;left:52px;background:var(--color-title);color:var(--bg-surface);font-size:.7rem;padding:.3rem .5rem;border-radius:4px;white-space:nowrap;font-weight:500;pointer-events:none}

/* Settings card */
.gf-settings{background:var(--bg-surface);border:1px solid var(--border-color);border-radius:12px;padding:1.25rem 1.5rem;margin-top:1rem}
.gf-settings h4{margin:0 0 .75rem;font-size:.85rem;font-weight:700;color:var(--color-title);display:flex;align-items:center;gap:.4rem}
.gf-settings label{display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:var(--color-text);cursor:pointer;margin-bottom:.4rem}

/* Preview Panel */
.gf-preview-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1050;display:none;justify-content:flex-end;backdrop-filter:blur(2px)}
.gf-preview-overlay.active{display:flex}
.gf-preview-drawer{width:440px;max-width:95vw;background:var(--bg-color);height:100%;display:flex;flex-direction:column;animation:gfSlideIn .25s ease;box-shadow:-8px 0 30px rgba(0,0,0,.15)}
@keyframes gfSlideIn{from{transform:translateX(100%)}to{transform:translateX(0)}}
.gf-preview-header{padding:.75rem 1rem;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;flex-shrink:0}
.gf-preview-header h3{margin:0;font-size:.85rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:.4rem}
.gf-preview-body{flex:1;overflow-y:auto;padding:1.5rem;display:flex;justify-content:center}
.gf-phone{width:375px;min-height:500px;background:#fff;border-radius:24px;box-shadow:0 8px 40px rgba(0,0,0,.12);overflow:hidden;border:1px solid #e2e8f0}
[data-theme="dark"] .gf-phone{background:#1a1a2e;border-color:#2d2d44}
.gf-phone-header{background:linear-gradient(135deg,var(--primary-color),#065f46);padding:1.5rem;color:white}
.gf-phone-header h2{margin:0;font-size:1.15rem;font-weight:700}
.gf-phone-header p{margin:.4rem 0 0;font-size:.85rem;opacity:.85}
.gf-phone-screen{padding:1.25rem}
.gf-pf{margin-bottom:1rem}
.gf-pf label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem}
[data-theme="dark"] .gf-pf label{color:#d1d5db}
.gf-pf .req{color:#ef4444}
.gf-pi{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:.55rem .75rem;font-size:.85rem;font-family:inherit;background:#f9fafb;box-sizing:border-box}
[data-theme="dark"] .gf-pi{background:#111827;border-color:#374151;color:#e5e7eb}
.gf-pt{min-height:70px;resize:vertical}
.gf-pd{border:0;border-top:2px solid #e5e7eb;margin:1.2rem 0}
.gf-ps{font-size:1rem;font-weight:700;color:#111827;margin-bottom:.25rem}
[data-theme="dark"] .gf-ps{color:#f3f4f6}
.gf-pr,.gf-pc{display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:#374151;margin:.3rem 0}
[data-theme="dark"] .gf-pr,[data-theme="dark"] .gf-pc{color:#d1d5db}
.gf-pfile{border:2px dashed #d1d5db;border-radius:10px;padding:1.2rem;text-align:center;color:#9ca3af;font-size:.8rem}
.gf-preview-btn{display:flex;align-items:center;gap:.35rem;padding:.4rem .7rem;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-surface);color:var(--color-text);cursor:pointer;font-size:.8rem;font-weight:500;font-family:inherit;transition:all .15s}
.gf-preview-btn:hover{border-color:var(--primary-color);color:var(--primary-color)}

@media(max-width:900px){
    .gf-toolbar{position:fixed;bottom:0;left:0;right:0;top:auto;transform:none;flex-direction:row;justify-content:center;border-radius:0;padding:6px 12px;border-left:none;border-right:none;border-bottom:none}
    .gf-toolbar button[title]:hover::after{display:none}
    .gf-wrap{padding-bottom:5rem}
}
</style>

<div class="gf-topbar">
    <div class="gf-topbar-left">
        <a href="index.php?module=forms&action=index" class="btn btn-outline" style="padding:.35rem .6rem"><i class="ph ph-arrow-left"></i></a>
        <h2><?php echo $formData ? 'Editar Formulario' : 'Nuevo Formulario'; ?></h2>
    </div>
    <div style="display:flex;gap:.5rem">
        <button class="gf-preview-btn" onclick="togglePreview()" title="Vista previa"><i class="ph ph-eye"></i><span class="d-none d-md-inline"> Vista Previa</span></button>
        <button class="btn btn-outline" onclick="saveForm('draft')"><i class="ph ph-floppy-disk"></i><span class="d-none d-md-inline"> Borrador</span></button>
        <button class="btn btn-primary" onclick="saveForm('active')"><i class="ph ph-rocket-launch"></i><span class="d-none d-md-inline"> Publicar</span></button>
    </div>
</div>

<form class="gf-wrap" autocomplete="off" onsubmit="return false">
    <!-- Title Card -->
    <div class="gf-title-card">
        <div class="gf-title-card-inner">
            <input type="text" class="gf-title-input" id="formTitle" placeholder="Formulario sin título" value="<?php echo htmlspecialchars($formData['title'] ?? ''); ?>" autocomplete="off">
            <textarea class="gf-desc-input" id="formDesc" rows="1" placeholder="Descripción del formulario" autocomplete="off"><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
        </div>
    </div>

    <!-- Fields rendered here -->
    <div id="fieldsList"></div>

    <!-- Settings -->
    <div class="gf-settings">
        <h4><i class="ph ph-gear"></i> Configuración</h4>
        <label><input type="checkbox" id="settShowLogo" <?php echo (json_decode($formData['settings_json'] ?? '{}', true)['show_logo'] ?? true) ? 'checked' : ''; ?>> Mostrar logo</label>
        <label><input type="checkbox" id="settRequireName" <?php echo (json_decode($formData['settings_json'] ?? '{}', true)['require_name'] ?? true) ? 'checked' : ''; ?>> Pedir nombre</label>
        <label><input type="checkbox" id="settRequireEmail" <?php echo (json_decode($formData['settings_json'] ?? '{}', true)['require_email'] ?? true) ? 'checked' : ''; ?>> Pedir email</label>
        <label><input type="checkbox" id="settMultiStep" <?php echo (json_decode($formData['settings_json'] ?? '{}', true)['multi_step'] ?? false) ? 'checked' : ''; ?>> Formulario multi-paso</label>
    </div>
</form>

<!-- Preview Drawer -->
<div class="gf-preview-overlay" id="previewOverlay" onclick="if(event.target===this)togglePreview()">
    <div class="gf-preview-drawer">
        <div class="gf-preview-header">
            <h3><i class="ph ph-device-mobile"></i> Vista Previa</h3>
            <button onclick="togglePreview()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--text-muted);padding:.3rem"><i class="ph ph-x"></i></button>
        </div>
        <div class="gf-preview-body">
            <div class="gf-phone" id="phonePreview"></div>
        </div>
    </div>
</div>

<!-- Floating Toolbar -->
<div class="gf-toolbar" id="gfToolbar">
    <button onclick="addField('text')" title="Pregunta"><i class="ph ph-plus-circle"></i></button>
    <button onclick="addField('textarea')" title="Párrafo"><i class="ph ph-text-align-left"></i></button>
    <button onclick="addField('select')" title="Varias opciones"><i class="ph ph-radio-button"></i></button>
    <button onclick="addField('checkbox')" title="Casillas"><i class="ph ph-check-square"></i></button>
    <button onclick="addField('dropdown')" title="Desplegable"><i class="ph ph-caret-down"></i></button>
    <button onclick="addField('file')" title="Subir archivos"><i class="ph ph-upload-simple"></i></button>
    <button onclick="addField('date')" title="Fecha"><i class="ph ph-calendar-blank"></i></button>
    <button onclick="addField('range')" title="Escala lineal"><i class="ph ph-dots-three-outline"></i></button>
    <button onclick="addField('number_range')" title="Rango numérico"><i class="ph ph-arrows-out-line-horizontal"></i></button>
    <button onclick="addField('color')" title="Color"><i class="ph ph-palette"></i></button>
    <button onclick="addField('icon_card')" title="Cards con icono"><i class="ph ph-cards"></i></button>
    <button onclick="addField('divider')" title="Añadir sección"><i class="ph ph-equals"></i></button>
</div>

<script>
const FORM_ID='<?php echo $id; ?>';
let fields=<?php echo $formData ? ($formData['fields_json'] ?: '[]') : '[]'; ?>;
let activeIdx=null, dragSrcIdx=null;

const TYPE_MAP={text:'Respuesta corta',textarea:'Párrafo',email:'Email',phone:'Teléfono',date:'Fecha',select:'Varias opciones',checkbox:'Casillas',dropdown:'Desplegable',file:'Subir archivos',range:'Escala lineal',number_range:'Rango numérico',color:'Color',icon_card:'Cards con icono',divider:'Sección'};
const TYPE_ICONS={text:'ph-text-aa',textarea:'ph-text-align-left',email:'ph-envelope-simple',phone:'ph-phone',date:'ph-calendar-blank',select:'ph-radio-button',checkbox:'ph-check-square',dropdown:'ph-caret-down',file:'ph-upload-simple',range:'ph-dots-three-outline',number_range:'ph-arrows-out-line-horizontal',color:'ph-palette',icon_card:'ph-cards',divider:'ph-equals'};
const ICON_LIST=['ph-star','ph-heart','ph-lightning','ph-rocket','ph-globe','ph-paint-brush','ph-megaphone','ph-camera','ph-video-camera','ph-music-note','ph-code','ph-chat-circle','ph-envelope','ph-phone','ph-map-pin','ph-clock','ph-calendar','ph-chart-line','ph-shopping-cart','ph-truck','ph-users','ph-user','ph-gear','ph-shield-check','ph-trophy','ph-flag','ph-bell','ph-book-open','ph-graduation-cap','ph-briefcase','ph-hand-pointing','ph-sparkle','ph-fire','ph-sun','ph-moon','ph-cloud'];

function uid(){return 'f_'+Math.random().toString(36).substr(2,9)}
function esc(s){const d=document.createElement('div');d.textContent=s||'';return d.innerHTML}

function addField(type){
    const defs={text:{l:'Pregunta sin título',p:'Texto de respuesta corta'},textarea:{l:'Pregunta sin título',p:'Texto de respuesta larga'},email:{l:'Correo electrónico',p:'tu@email.com'},phone:{l:'Teléfono',p:'+51 999 999 999'},date:{l:'Fecha',p:''},select:{l:'Pregunta sin título',p:'',o:['Opción 1']},checkbox:{l:'Pregunta sin título',p:'',o:['Opción 1']},dropdown:{l:'Pregunta sin título',p:'',o:['Opción 1']},file:{l:'Subir archivo',p:''},range:{l:'Escala lineal',p:''},number_range:{l:'Rango de edad',p:''},color:{l:'Elige un color',p:''},icon_card:{l:'Elige una opción',p:''},divider:{l:'Sección sin título',p:''}};
    const d=defs[type];
    const field={id:uid(),type,label:d.l,placeholder:d.p,required:false,width:'full',options:d.o?[...d.o]:undefined,description:''};
    if(type==='range'){field.range_min=1;field.range_max=5;field.range_label_min='';field.range_label_max=''}
    if(type==='number_range'){field.nr_min=18;field.nr_max=65;field.nr_step=1}
    if(type==='color'){field.color_options=['#03624c','#3b82f6','#ef4444','#f59e0b','#8b5cf6','#ec4899'];field.color_multi=true}
    if(type==='icon_card'){field.icon_options=[{icon:'ph-star',text:'Opción 1'},{icon:'ph-heart',text:'Opción 2'}];field.icon_multi=false}
    fields.push(field);
    activeIdx=fields.length-1;
    renderFields();
    // Scroll to new field
    setTimeout(()=>{const el=document.querySelector(`[data-idx="${activeIdx}"]`);if(el)el.scrollIntoView({behavior:'smooth',block:'center'})},50);
}

function setActive(idx){if(activeIdx===idx)return;activeIdx=idx;renderFields()}

function renderFields(){
    const list=document.getElementById('fieldsList');
    list.innerHTML='';
    let numSections = 1;
    fields.forEach(f=>{if(f.type==='divider') numSections++;});
    let currentSection = 1;
    fields.forEach((f,i)=>{
        const card=document.createElement('div');
        card.className='gf-card'+(i===activeIdx?' active':'')+(f.type==='divider'?' gf-section-card':'');
        card.dataset.idx=i;
        card.draggable=true;
        card.addEventListener('click',(e)=>{
            if(e.target.closest('input,select,textarea,button,label')){
                // If clicking input on inactive card, activate without full re-render
                if(activeIdx!==i){
                    document.querySelectorAll('.gf-card.active').forEach(c=>c.classList.remove('active'));
                    card.classList.add('active');
                    activeIdx=i;
                }
                return;
            }
            if(activeIdx!==i){activeIdx=i;renderFields()}
        });
        card.addEventListener('dragstart',e=>{dragSrcIdx=i;card.style.opacity='.4';e.dataTransfer.effectAllowed='move'});
        card.addEventListener('dragend',()=>{card.style.opacity='1';dragSrcIdx=null});
        card.addEventListener('dragover',e=>{e.preventDefault();e.dataTransfer.dropEffect='move'});
        card.addEventListener('drop',e=>{e.preventDefault();if(dragSrcIdx===null||dragSrcIdx===i)return;const m=fields.splice(dragSrcIdx,1)[0];fields.splice(i,0,m);activeIdx=i;renderFields()});

        let bodyHtml='';
        let badgeHtml='';
        if(f.type==='divider'){
            currentSection++;
            badgeHtml=`<div style="background:var(--primary-color,#03624c);color:white;display:inline-block;padding:6px 16px;border-radius:8px 8px 0 0;font-size:.75rem;font-weight:600;position:absolute;top:-28px;left:-1px;">Sección ${currentSection} de ${numSections}</div>`;
            card.style.marginTop='30px';
            bodyHtml=`<input class="gf-section-title" value="${esc(f.label)}" placeholder="Sección sin título" onfocus="setActive(${i})" oninput="fields[${i}].label=this.value" autocomplete="off">
            <input class="gf-section-desc" value="${esc(f.description||'')}" placeholder="Descripción (opcional)" oninput="fields[${i}].description=this.value" autocomplete="off">`;
        } else if(i===activeIdx){
            // Active editing mode
            let typeOpts='';
            ['text','textarea','select','checkbox','dropdown','email','phone','date','file','range','number_range','color','icon_card'].forEach(t=>{
                typeOpts+=`<option value="${t}" ${f.type===t?'selected':''}>${TYPE_MAP[t]}</option>`;
            });
            bodyHtml=`<div class="gf-q-header">
                <input class="gf-q-label" value="${esc(f.label)}" placeholder="Pregunta" oninput="fields[${i}].label=this.value" autocomplete="off">
                <select class="gf-type-select" onchange="fields[${i}].type=this.value;if(['select','checkbox','dropdown'].includes(this.value)&&!fields[${i}].options)fields[${i}].options=['Opción 1'];if(this.value==='color'&&!fields[${i}].color_options)fields[${i}].color_options=['#03624c'];if(this.value==='icon_card'&&!fields[${i}].icon_options)fields[${i}].icon_options=[{icon:'ph-star',text:'Opción'}];renderFields()">
                    ${typeOpts}
                </select>
            </div>`;
            bodyHtml+=renderFieldContent(f,i);
        } else {
            // Collapsed preview
            bodyHtml=`<div style="font-size:.9rem;font-weight:500;color:var(--color-title);margin-bottom:.3rem">${esc(f.label)}${f.required?'<span style="color:#ef4444;margin-left:2px">*</span>':''}</div>`;
            bodyHtml+='<div class="gf-preview-hint">'+renderCollapsedPreview(f)+'</div>';
        }

        card.innerHTML=`${badgeHtml}<div class="gf-card-drag"><i class="ph ph-dots-six"></i></div><div class="gf-card-body">${bodyHtml}</div>`;

        // Footer for active cards (not dividers - dividers get simpler footer)
        if(i===activeIdx){
            const footerHtml=f.type!=='divider'?`
            <div class="gf-card-footer gf-active-only">
                <button onclick="event.stopPropagation();dupField(${i})" title="Duplicar"><i class="ph ph-copy"></i></button>
                <button class="del-btn" onclick="event.stopPropagation();delField(${i})" title="Eliminar"><i class="ph ph-trash"></i></button>
                <div class="divider-v"></div>
                <div class="gf-required-toggle">Obligatorio
                    <label class="gf-toggle"><input type="checkbox" ${f.required?'checked':''} onchange="event.stopPropagation();fields[${i}].required=this.checked"><span class="gf-toggle-slider"></span></label>
                </div>
            </div>`:`
            <div class="gf-card-footer gf-active-only">
                <button onclick="event.stopPropagation();dupField(${i})" title="Duplicar"><i class="ph ph-copy"></i></button>
                <button class="del-btn" onclick="event.stopPropagation();delField(${i})" title="Eliminar"><i class="ph ph-trash"></i></button>
            </div>`;
            card.innerHTML+=footerHtml;
        }
        list.appendChild(card);
    });
}

function renderFieldContent(f,i){
    if(f.type==='text'||f.type==='email'||f.type==='phone')
        return `<div style="border-bottom:1px solid var(--border-color);padding:.3rem 0;font-size:.85rem;color:var(--text-muted);max-width:50%">${f.type==='text'?'Texto de respuesta corta':f.type==='email'?'correo@ejemplo.com':'+51 999 999 999'}</div>`;
    if(f.type==='textarea')
        return `<div style="border-bottom:1px solid var(--border-color);padding:.3rem 0;font-size:.85rem;color:var(--text-muted);max-width:80%">Texto de respuesta larga</div>`;
    if(f.type==='date')
        return `<div style="display:flex;align-items:center;gap:.5rem;border-bottom:1px solid var(--border-color);padding:.3rem 0;font-size:.85rem;color:var(--text-muted);max-width:50%"><i class="ph ph-calendar-blank"></i> Día / Mes / Año</div>`;
    if(f.type==='file'){
        if(typeof f.file_max_count === 'undefined') f.file_max_count = 1;
        if(typeof f.file_max_size === 'undefined') f.file_max_size = 10;
        const types = f.file_types || [];
        
        let html=`<div class="gf-active-only" style="display:flex;flex-direction:column;gap:12px;margin-bottom:1rem;padding:12px;background:var(--bg-color);border-radius:10px;border:1px solid var(--border-color)">`;
        html+=`<div>
            <label style="display:flex;align-items:center;gap:.4rem;font-size:.75rem;color:var(--text-muted);cursor:pointer;margin-bottom:8px">
                <input type="checkbox" ${f.file_restrict?'checked':''} onchange="event.stopPropagation();fields[${i}].file_restrict=this.checked;renderFields()"> Permitir solo ciertos tipos de archivos específicos
            </label>
            <div style="display:${f.file_restrict?'flex':'none'};flex-wrap:wrap;gap:10px;padding:8px;background:color-mix(in srgb,var(--bg-color) 80%, white);border-radius:6px;border:1px solid var(--border-color)">
                ${['Documento','PDF','Imagen','Video','Audio'].map(t=>`
                    <label style="font-size:.75rem;display:flex;align-items:center;gap:4px;cursor:pointer;color:var(--color-text)"><input type="checkbox" ${types.includes(t)?'checked':''} onchange="event.stopPropagation();if(this.checked){fields[${i}].file_types=(fields[${i}].file_types||[]);fields[${i}].file_types.push('${t}');}else{fields[${i}].file_types=fields[${i}].file_types.filter(x=>x!=='${t}');}renderFields()"> ${t}</label>
                `).join('')}
            </div>
        </div>`;

        html+=`<div style="display:flex;gap:1rem;flex-wrap:wrap">
            <div style="flex:1;min-width:150px">
                <label style="display:block;font-size:.7rem;font-weight:600;color:var(--text-muted);margin-bottom:4px">Cantidad máxima de archivos</label>
                <select style="width:100%;padding:6px 8px;border:1px solid var(--border-color);border-radius:6px;font-size:.8rem;background:var(--bg-color);color:var(--color-text)" onchange="event.stopPropagation();fields[${i}].file_max_count=parseInt(this.value);renderFields()">
                    ${[1,5,10].map(v=>`<option value="${v}" ${f.file_max_count===v?'selected':''}>${v}</option>`).join('')}
                </select>
            </div>
            <div style="flex:1;min-width:150px">
                <label style="display:block;font-size:.7rem;font-weight:600;color:var(--text-muted);margin-bottom:4px">Tamaño máximo de archivo</label>
                <select style="width:100%;padding:6px 8px;border:1px solid var(--border-color);border-radius:6px;font-size:.8rem;background:var(--bg-color);color:var(--color-text)" onchange="event.stopPropagation();fields[${i}].file_max_size=parseInt(this.value);renderFields()">
                    ${[1,10,100,1000].map(v=>`<option value="${v}" ${f.file_max_size===v?'selected':''}>${v} MB</option>`).join('')}
                </select>
            </div>
        </div></div>`;
        
        html+=`<div class="gf-file-preview"><i class="ph ph-cloud-arrow-up" style="font-size:1.5rem;display:block;margin-bottom:.3rem"></i>Subir archivo</div>`;
        return html;
    }
    if(f.type==='select'||f.type==='checkbox'||f.type==='dropdown'){
        if(typeof f.is_multi === 'undefined') f.is_multi = (f.type === 'checkbox');
        const isCheck = f.is_multi;
        const isDrop = f.type==='dropdown';
        let html=`<div class="gf-active-only" style="margin-bottom:.5rem"><label style="display:flex;align-items:center;gap:.4rem;font-size:.75rem;color:var(--text-muted);cursor:pointer"><input type="checkbox" ${f.is_multi?'checked':''} onchange="event.stopPropagation();fields[${i}].is_multi=this.checked;renderFields()"> Permitir múltiple selección</label></div>`;
        html+='<div class="gf-opt-list">';
        (f.options||[]).forEach((o,oi)=>{
            html+=`<div class="gf-opt-row${isCheck?' checkbox-style':''}">
                <div class="${isDrop?'':'opt-icon'}" style="${isCheck?'border-radius:3px':(isDrop?'font-size:.9rem;color:var(--text-muted);display:flex;align-items:center;justify-content:center;width:20px':'')}">${isDrop?(oi+1)+'.':''}</div>
                <input value="${esc(o)}" oninput="fields[${i}].options[${oi}]=this.value" onclick="event.stopPropagation()" autocomplete="off">
                <button class="opt-del" onclick="event.stopPropagation();fields[${i}].options.splice(${oi},1);renderFields()"><i class="ph ph-x"></i></button>
            </div>`;
        });
        html+=`</div><div class="gf-add-opt"><div class="${isDrop?'':'opt-icon'}" style="${isCheck?'border-radius:3px':(isDrop?'font-size:.9rem;color:var(--text-muted);display:flex;align-items:center;justify-content:center;width:20px':'')}">${isDrop?(f.options?f.options.length+1:1)+'.':''}</div> <span style="cursor:pointer" onclick="event.stopPropagation();if(!fields[${i}].options)fields[${i}].options=[];fields[${i}].options.push('Opción '+((fields[${i}].options||[]).length+1));renderFields()">Añadir opción</span> ${isDrop?'':`o <span style="color:var(--primary-color,#03624c);cursor:pointer" onclick="event.stopPropagation();if(!fields[${i}].options)fields[${i}].options=[];fields[${i}].options.push('Otro');renderFields()">añadir "Otro"</span>`}</div>`;
        return html;
    }
    if(f.type==='range'){
        const mn=f.range_min||1,mx=Math.min(f.range_max||5,10),lMin=f.range_label_min||'',lMax=f.range_label_max||'';
        let dots='';for(let n=mn;n<=mx;n++) dots+=`<div style="width:28px;height:28px;border:2px solid #bbb;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;color:var(--text-muted);font-weight:600;flex-shrink:0">${n}</div>`;
        let html=`<div class="gf-active-only" style="display:flex;gap:.5rem;margin-bottom:.75rem;flex-wrap:wrap">
            <div style="flex:1;min-width:60px"><label style="font-size:.7rem;font-weight:600;color:var(--text-muted)">Mín</label><input type="number" value="${mn}" min="0" max="10" style="width:100%;padding:.3rem .4rem;border:1px solid var(--border-color);border-radius:6px;font-size:.8rem;background:var(--bg-color);color:var(--color-text)" onchange="fields[${i}].range_min=Math.max(0,Math.min(10,parseInt(this.value)||0));renderFields()" autocomplete="off"></div>
            <div style="flex:1;min-width:60px"><label style="font-size:.7rem;font-weight:600;color:var(--text-muted)">Máx</label><input type="number" value="${mx}" min="1" max="10" style="width:100%;padding:.3rem .4rem;border:1px solid var(--border-color);border-radius:6px;font-size:.8rem;background:var(--bg-color);color:var(--color-text)" onchange="fields[${i}].range_max=Math.max(1,Math.min(10,parseInt(this.value)||5));renderFields()" autocomplete="off"></div>
            <div style="flex:2;min-width:80px"><label style="font-size:.7rem;font-weight:600;color:var(--text-muted)">Etiqueta mín</label><input value="${esc(lMin)}" placeholder="ej: Malo" style="width:100%;padding:.3rem .4rem;border:1px solid var(--border-color);border-radius:6px;font-size:.8rem;background:var(--bg-color);color:var(--color-text)" oninput="fields[${i}].range_label_min=this.value" autocomplete="off"></div>
            <div style="flex:2;min-width:80px"><label style="font-size:.7rem;font-weight:600;color:var(--text-muted)">Etiqueta máx</label><input value="${esc(lMax)}" placeholder="ej: Excelente" style="width:100%;padding:.3rem .4rem;border:1px solid var(--border-color);border-radius:6px;font-size:.8rem;background:var(--bg-color);color:var(--color-text)" oninput="fields[${i}].range_label_max=this.value" autocomplete="off"></div>
        </div>`;
        html+=`<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">${lMin?`<span style="font-size:.75rem;color:var(--text-muted)">${esc(lMin)}</span>`:''}<div style="display:flex;gap:4px;flex-wrap:wrap">${dots}</div>${lMax?`<span style="font-size:.75rem;color:var(--text-muted)">${esc(lMax)}</span>`:''}</div>`;
        return html;
    }
    if(f.type==='number_range'){
        const nrMin=f.nr_min??18,nrMax=f.nr_max??65,nrStep=f.nr_step??1;
        let html=`<div class="gf-active-only" style="display:flex;gap:.5rem;margin-bottom:.75rem;flex-wrap:wrap">
            <div style="flex:1;min-width:60px"><label style="font-size:.7rem;font-weight:600;color:var(--text-muted)">Desde</label><input type="number" value="${nrMin}" style="width:100%;padding:.3rem .4rem;border:1px solid var(--border-color);border-radius:6px;font-size:.8rem;background:var(--bg-color);color:var(--color-text)" onchange="fields[${i}].nr_min=parseInt(this.value)" autocomplete="off"></div>
            <div style="flex:1;min-width:60px"><label style="font-size:.7rem;font-weight:600;color:var(--text-muted)">Hasta</label><input type="number" value="${nrMax}" style="width:100%;padding:.3rem .4rem;border:1px solid var(--border-color);border-radius:6px;font-size:.8rem;background:var(--bg-color);color:var(--color-text)" onchange="fields[${i}].nr_max=parseInt(this.value)" autocomplete="off"></div>
            <div style="flex:1;min-width:60px"><label style="font-size:.7rem;font-weight:600;color:var(--text-muted)">Paso</label><input type="number" value="${nrStep}" min="1" style="width:100%;padding:.3rem .4rem;border:1px solid var(--border-color);border-radius:6px;font-size:.8rem;background:var(--bg-color);color:var(--color-text)" onchange="fields[${i}].nr_step=parseInt(this.value)||1" autocomplete="off"></div>
        </div>`;
        html+=`<div style="display:flex;align-items:center;gap:8px"><span style="font-size:.8rem;color:var(--text-muted);font-weight:600">${nrMin}</span><div style="flex:1;height:6px;background:var(--border-color);border-radius:3px;position:relative"><div style="position:absolute;left:0;top:0;height:100%;width:40%;background:var(--primary-color,#03624c);border-radius:3px"></div></div><span style="font-size:.8rem;color:var(--text-muted);font-weight:600">${nrMax}</span></div>`;
        return html;
    }
    if(f.type==='color'){
        const colors=f.color_options||['#03624c'];
        let html=`<div class="gf-active-only" style="margin-bottom:.5rem"><span style="font-size:.75rem;color:var(--text-muted);">Añade selectores. El usuario podrá cambiar estos colores libremente.</span></div>`;
        html+='<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">';
        colors.forEach((c,ci)=>{
            html+=`<div style="position:relative;display:flex;flex-direction:column;align-items:center;gap:3px">
                <input type="color" value="${c}" style="width:40px;height:40px;border:2px solid var(--border-color);border-radius:10px;cursor:pointer;padding:2px" onchange="event.stopPropagation();fields[${i}].color_options[${ci}]=this.value;renderFields()">
                <span style="font-size:.55rem;color:var(--text-muted);font-family:monospace">Color ${ci+1}</span>
                <button style="position:absolute;top:-6px;right:-6px;width:16px;height:16px;border-radius:50%;background:var(--danger-color,#ef4444);color:white;border:none;cursor:pointer;font-size:.6rem;display:flex;align-items:center;justify-content:center;line-height:1" onclick="event.stopPropagation();fields[${i}].color_options.splice(${ci},1);renderFields()">&times;</button>
            </div>`;
        });
        html+=`<button style="width:40px;height:40px;border:2px dashed var(--border-color);border-radius:10px;background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--text-muted)" onclick="event.stopPropagation();if(!fields[${i}].color_options)fields[${i}].color_options=['#03624c'];fields[${i}].color_options.push('#'+Math.floor(Math.random()*16777215).toString(16).padStart(6,'0'));renderFields()" title="Añadir color"><i class="ph ph-plus"></i></button></div>`;
        return html;
    }
    if(f.type==='icon_card'){
        const opts=f.icon_options||[];
        let html=`<div class="gf-active-only" style="margin-bottom:.5rem"><label style="display:flex;align-items:center;gap:.4rem;font-size:.75rem;color:var(--text-muted);cursor:pointer"><input type="checkbox" ${f.icon_multi?'checked':''} onchange="event.stopPropagation();fields[${i}].icon_multi=this.checked;renderFields()"> Permitir múltiple selección</label></div>`;
        html+='<div style="display:flex;flex-direction:column;gap:6px">';
        opts.forEach((o,oi)=>{
            let iconPicker=`<select style="width:42px;padding:4px;border:1px solid var(--border-color);border-radius:6px;font-size:1rem;background:var(--bg-color);cursor:pointer;text-align:center" onchange="event.stopPropagation();fields[${i}].icon_options[${oi}].icon=this.value;renderFields()">`;
            ICON_LIST.forEach(ic=>{iconPicker+=`<option value="${ic}" ${o.icon===ic?'selected':''}>${ic.replace('ph-','')}</option>`});
            iconPicker+='</select>';
            html+=`<div style="display:flex;align-items:center;gap:8px;border:1.5px solid var(--border-color);border-radius:10px;padding:8px 12px">
                <div style="width:36px;height:36px;background:color-mix(in srgb,var(--primary-color,#03624c) 12%,transparent);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--primary-color,#03624c);flex-shrink:0"><i class="ph ${o.icon}"></i></div>
                <input value="${esc(o.text)}" style="flex:1;border:none;outline:none;font-size:.85rem;background:transparent;color:var(--color-text)" oninput="fields[${i}].icon_options[${oi}].text=this.value" onclick="event.stopPropagation()" autocomplete="off">
                ${iconPicker}
                <button style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:.9rem" onclick="event.stopPropagation();fields[${i}].icon_options.splice(${oi},1);renderFields()"><i class="ph ph-x"></i></button>
            </div>`;
        });
        html+=`</div><div style="margin-top:8px"><button style="background:none;border:1px dashed var(--border-color);border-radius:8px;padding:6px 14px;cursor:pointer;font-size:.8rem;color:var(--primary-color,#03624c);font-weight:600;font-family:inherit" onclick="event.stopPropagation();if(!fields[${i}].icon_options)fields[${i}].icon_options=[{icon:'ph-star',text:'Opción 1'}];fields[${i}].icon_options.push({icon:ICON_LIST[Math.floor(Math.random()*ICON_LIST.length)],text:'Opción '+(fields[${i}].icon_options.length+1)});renderFields()"><i class="ph ph-plus"></i> Añadir card</button></div>`;
        return html;
    }
    return '';
}

function renderCollapsedPreview(f){
    if(f.type==='text'||f.type==='email'||f.type==='phone') return `<input disabled placeholder="${esc(f.placeholder||'Texto de respuesta corta')}" style="max-width:60%">`;
    if(f.type==='textarea') return `<input disabled placeholder="Texto de respuesta larga" style="max-width:80%">`;
    if(f.type==='date') return `<input disabled placeholder="Día / Mes / Año" style="max-width:50%">`;
    if(f.type==='file') return '<span style="font-size:.8rem"><i class="ph ph-upload-simple"></i> Subir archivo</span>';
    if(f.type==='select') return (f.options||[]).map(o=>`<div style="display:flex;align-items:center;gap:.4rem;margin:.2rem 0"><span style="width:14px;height:14px;border:2px solid #bbb;border-radius:50%;display:inline-block;flex-shrink:0"></span><span style="font-size:.85rem">${esc(o)}</span></div>`).join('');
    if(f.type==='checkbox') return (f.options||[]).map(o=>`<div style="display:flex;align-items:center;gap:.4rem;margin:.2rem 0"><span style="width:14px;height:14px;border:2px solid #bbb;border-radius:2px;display:inline-block;flex-shrink:0"></span><span style="font-size:.85rem">${esc(o)}</span></div>`).join('');
    if(f.type==='dropdown') return `<div style="padding:4px 8px;border:1px solid #ddd;border-radius:6px;display:inline-flex;align-items:center;gap:12px"><span style="font-size:.8rem;color:#777">1. ${esc(f.options?.[0]||'Opciones')}</span><i class="ph ph-caret-down" style="font-size:.8rem;color:#999"></i></div>`;
    if(f.type==='range'){const mn=f.range_min||1,mx=Math.min(f.range_max||5,10);let d='';for(let n=mn;n<=mx;n++)d+=`<span style="width:20px;height:20px;border:2px solid #bbb;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.6rem;color:#999;flex-shrink:0">${n}</span>`;return `<div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">${f.range_label_min?`<span style="font-size:.7rem;color:var(--text-muted)">${esc(f.range_label_min)}</span>`:''}${d}${f.range_label_max?`<span style="font-size:.7rem;color:var(--text-muted)">${esc(f.range_label_max)}</span>`:''}</div>`}
    if(f.type==='number_range') return `<div style="display:flex;align-items:center;gap:6px"><span style="font-size:.8rem;color:var(--text-muted)">${f.nr_min??18}</span><div style="height:4px;width:80px;background:#bbb;border-radius:2px"></div><span style="font-size:.8rem;color:var(--text-muted)">${f.nr_max??65}</span></div>`;
    if(f.type==='color'){const colors=f.color_options||['#03624c'];return `<div style="display:flex;gap:4px;flex-wrap:wrap">${colors.map(c=>`<div style="width:18px;height:18px;border-radius:50%;background:${c};border:2px solid #ddd"></div>`).join('')}</div>`}
    if(f.type==='icon_card') return (f.icon_options||[]).map(o=>`<div style="display:flex;align-items:center;gap:6px;border:1px solid #bbb;border-radius:8px;padding:5px 10px;margin:3px 0"><i class="ph ${o.icon}" style="font-size:.9rem;color:var(--primary-color,#03624c)"></i><span style="font-size:.8rem">${esc(o.text)}</span></div>`).join('');
    return '';
}

function dupField(idx){
    const clone=JSON.parse(JSON.stringify(fields[idx]));
    clone.id=uid();
    fields.splice(idx+1,0,clone);
    activeIdx=idx+1;
    renderFields();
}
function delField(idx){fields.splice(idx,1);if(activeIdx>=fields.length)activeIdx=fields.length-1;if(activeIdx<0)activeIdx=null;renderFields()}

async function saveForm(status){
    const title=document.getElementById('formTitle').value.trim();
    if(!title){alert('El título es obligatorio');return}
    const fd=new FormData();
    fd.append('id',FORM_ID);
    fd.append('title',title);
    fd.append('description',document.getElementById('formDesc').value);
    fd.append('fields_json',JSON.stringify(fields));
    fd.append('settings_json',JSON.stringify({
        show_logo:document.getElementById('settShowLogo').checked,
        require_name:document.getElementById('settRequireName').checked,
        require_email:document.getElementById('settRequireEmail').checked,
        multi_step:document.getElementById('settMultiStep').checked
    }));
    fd.append('status',status);
    const btn=event.currentTarget;const orig=btn.innerHTML;
    btn.innerHTML='<i class="ph ph-spinner ph-spin"></i>';btn.disabled=true;
    try{
        const res=await fetch('index.php?module=forms&action=ajax_save_template',{method:'POST',body:fd});
        const data=await res.json();
        if(data.success){
            if(data.redirect_url)window.location.href=data.redirect_url;
            else{btn.innerHTML='<i class="ph ph-check"></i> Guardado';setTimeout(()=>{btn.innerHTML=orig;btn.disabled=false},2000)}
        }else{alert(data.error||'Error');btn.innerHTML=orig;btn.disabled=false}
    }catch(e){alert('Error de conexión');btn.innerHTML=orig;btn.disabled=false}
}

// Click outside cards to deselect
document.addEventListener('click',(e)=>{if(!e.target.closest('.gf-card')&&!e.target.closest('.gf-toolbar')&&!e.target.closest('.gf-topbar')){activeIdx=null;renderFields()}});

// Auto-resize textarea
document.getElementById('formDesc').addEventListener('input',function(){this.style.height='auto';this.style.height=this.scrollHeight+'px'});

renderFields();

// Preview panel
const LOGO_URL='<?php echo htmlspecialchars($logoLight); ?>';
function togglePreview(){
    const o=document.getElementById('previewOverlay');
    if(o.classList.contains('active')){o.classList.remove('active');return}
    renderPreview();
    o.classList.add('active');
}
function renderPreview(){
    const phone=document.getElementById('phonePreview');
    if(!phone)return;
    const title=document.getElementById('formTitle').value||'Formulario sin título';
    const desc=document.getElementById('formDesc').value||'';
    const showLogo=document.getElementById('settShowLogo').checked;
    const reqName=document.getElementById('settRequireName').checked;
    const reqEmail=document.getElementById('settRequireEmail').checked;
    let logo=showLogo&&LOGO_URL?`<img src="${LOGO_URL}" style="height:28px;margin-bottom:.75rem;filter:brightness(0) invert(1)">`:'';
    let h='';
    if(reqName) h+=`<div class="gf-pf"><label>Tu nombre <span class="req">*</span></label><input class="gf-pi" placeholder="Nombre completo" disabled></div>`;
    if(reqEmail) h+=`<div class="gf-pf"><label>Tu email <span class="req">*</span></label><input class="gf-pi" placeholder="correo@ejemplo.com" disabled></div>`;
    fields.forEach(f=>{
        if(f.type==='divider'){h+=`<hr class="gf-pd"><div class="gf-ps">${esc(f.label)}</div>`;return}
        const req=f.required?'<span class="req">*</span>':'';
        let inp='';
        if(['text','email','phone','date'].includes(f.type)) inp=`<input class="gf-pi" type="${f.type==='phone'?'tel':f.type}" placeholder="${esc(f.placeholder)}" disabled>`;
        else if(f.type==='textarea') inp=`<textarea class="gf-pi gf-pt" placeholder="${esc(f.placeholder)}" disabled></textarea>`;
        else if(f.type==='select') inp=(f.options||[]).map(o=>`<div class="gf-pr"><input type="radio" disabled> ${esc(o)}</div>`).join('');
        else if(f.type==='checkbox') inp=(f.options||[]).map(o=>`<div class="gf-pc"><input type="checkbox" disabled> ${esc(o)}</div>`).join('');
        else if(f.type==='dropdown') inp=`<select class="gf-pi" disabled><option>Elegir</option>${(f.options||[]).map(o=>`<option>${esc(o)}</option>`).join('')}</select>`;
        else if(f.type==='file') inp=`<div class="gf-pfile"><i class="ph ph-cloud-arrow-up" style="font-size:1.5rem;display:block;margin-bottom:.3rem"></i>Subir archivo</div>`;
        else if(f.type==='range'){const mn=f.range_min||1,mx=Math.min(f.range_max||5,10);let dots='';for(let n=mn;n<=mx;n++)dots+=`<div style="width:24px;height:24px;border:2px solid #d1d5db;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;color:#9ca3af;font-weight:600">${n}</div>`;inp=`<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">${f.range_label_min?`<span style="font-size:.7rem;color:#9ca3af">${esc(f.range_label_min)}</span>`:''}${dots}${f.range_label_max?`<span style="font-size:.7rem;color:#9ca3af">${esc(f.range_label_max)}</span>`:''}</div>`;}
        else if(f.type==='number_range'){inp=`<div style="display:flex;gap:8px;align-items:center"><select class="gf-pi" style="flex:1" disabled><option>Desde: ${f.nr_min??18}</option></select><span style="color:#9ca3af">—</span><select class="gf-pi" style="flex:1" disabled><option>Hasta: ${f.nr_max??65}</option></select></div>`;}
        else if(f.type==='color'){const colors=f.color_options||['#03624c'];inp=`<div style="display:flex;gap:6px;flex-wrap:wrap">${colors.map(c=>`<div style="width:28px;height:28px;border-radius:50%;background:${c};border:2px solid #e5e7eb"></div>`).join('')}</div>`;}
        else if(f.type==='icon_card'){inp=(f.icon_options||[]).map(o=>`<div style="display:flex;align-items:center;gap:8px;border:1.5px solid #e5e7eb;border-radius:10px;padding:8px 12px;margin:4px 0"><div style="width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.9rem;color:#059669;background:#f0fdf4"><i class="ph ${o.icon}"></i></div><span style="font-size:.8rem">${esc(o.text)}</span></div>`).join('');}
        h+=`<div class="gf-pf"><label>${esc(f.label)} ${req}</label>${inp}</div>`;
    });
    phone.innerHTML=`<div class="gf-phone-header">${logo}<h2>${esc(title)}</h2>${desc?`<p>${esc(desc)}</p>`:''}</div><div class="gf-phone-screen">${h}<button style="width:100%;padding:.7rem;background:var(--primary-color);color:white;border:none;border-radius:10px;font-weight:700;font-size:.9rem;margin-top:1rem;font-family:inherit" disabled>Enviar Formulario</button></div>`;
}

// Position toolbar next to content area
function positionToolbar(){
    const wrap=document.querySelector('.gf-wrap');
    const tb=document.getElementById('gfToolbar');
    if(!wrap||!tb)return;
    const rect=wrap.getBoundingClientRect();
    const leftPos=rect.right+16;
    if(leftPos+60>window.innerWidth){tb.style.cssText='position:fixed;bottom:0;left:0;right:0;top:auto;transform:none;flex-direction:row;justify-content:center;border-radius:0;padding:6px 12px;border-left:none;border-right:none;border-bottom:none;z-index:15;display:flex';return}
    tb.style.cssText='';
    tb.style.left=leftPos+'px';
}
positionToolbar();
window.addEventListener('resize',positionToolbar);
setTimeout(positionToolbar,100);
</script>
<?php require_once 'includes/footer.php'; ?>
