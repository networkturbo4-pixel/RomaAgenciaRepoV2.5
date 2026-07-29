<?php
// modules/contracts/templates.php
if (!isset($_SESSION['user_id']) || !in_array('contracts', $_SESSION['user_permissions'] ?? [])) {
    header("Location: index.php?module=auth&action=login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = $_POST['action_type'] ?? '';
    
    if ($action_type === 'save_template') {
        $id = $_POST['template_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $target_type = $_POST['target_type'] ?? 'client';
        $body = $_POST['body'] ?? '';
        
        if ($id) {
            $stmt = $db->prepare("UPDATE contract_templates SET name=?, target_type=?, body=? WHERE id=?");
            $stmt->execute([$name, $target_type, $body, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO contract_templates (name, target_type, body) VALUES (?, ?, ?)");
            $stmt->execute([$name, $target_type, $body]);
        }
        header("Location: index.php?module=contracts&action=templates&success=1");
        exit;
    }
    
    if ($action_type === 'delete_template') {
        $id = $_POST['template_id'] ?? 0;
        $db->prepare("DELETE FROM contract_templates WHERE id=?")->execute([$id]);
        header("Location: index.php?module=contracts&action=templates&deleted=1");
        exit;
    }
}

$stmt = $db->query("SELECT * FROM contract_templates ORDER BY name ASC");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include 'includes/header.php'; ?>
<!-- Include CKEditor 4 Full -->
<script src="https://cdn.ckeditor.com/4.22.1/full/ckeditor.js"></script>

<style>
    .split-layout {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 1.5rem;
        height: calc(100vh - 120px);
    }
    
    .templates-sidebar {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .sidebar-header {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--bg-body);
    }
    
    .tpl-list {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .tpl-item {
        padding: 1rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
        background: var(--bg-body);
        cursor: pointer;
        transition: all 0.2s;
    }
    .tpl-item:hover, .tpl-item.active {
        border-color: var(--primary-color);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1);
    }
    .tpl-item.active {
        background: var(--primary-color);
    }
    .tpl-item.active .tpl-name { color: white; }
    .tpl-item.active .tpl-meta { color: rgba(255,255,255,0.8); }
    .tpl-item.active .tpl-type { background: rgba(255,255,255,0.2); color: white; }
    
    .tpl-name {
        font-weight: 600;
        font-size: 14px;
        color: var(--color-title);
        margin-bottom: 0.25rem;
    }
    .tpl-meta {
        font-size: 12px;
        color: var(--text-muted);
    }
    .tpl-type {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        margin-top: 0.5rem;
    }
    .type-client { background: rgba(67, 56, 202, 0.1); color: #4338ca; }
    .type-staff { background: rgba(126, 34, 206, 0.1); color: #7e22ce; }
    .type-general { background: rgba(75, 85, 99, 0.1); color: #4b5563; }
    
    .editor-pane {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .editor-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        background: var(--bg-body);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .editor-body {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
        background: #f8fafc;
    }
    [data-theme="dark"] .editor-body { background: var(--bg-body); }
    
    .magic-vars {
        background: var(--bg-body);
        padding: 1rem;
        border-radius: var(--radius-md);
        border: 1px dashed var(--border-color);
        font-size: 12px;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
    }
    
    @media (max-width: 900px) {
        .split-layout {
            grid-template-columns: 1fr;
            height: auto;
        }
        .templates-sidebar { max-height: 300px; }
        .editor-pane { min-height: 600px; }
    }
</style>

<div class="main-content" style="padding-bottom: 0;">
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 48px; height: 48px; background: var(--bg-body); border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
                <i class="ph ph-files" style="font-size: 1.5rem; color: var(--primary-color);"></i>
            </div>
            <div>
                <h1 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--color-title);">Plantillas de Contrato</h1>
                <p style="margin: 0.1rem 0 0 0; color: var(--text-muted); font-size: 0.8rem;">Gestiona los textos base para tus contratos.</p>
            </div>
        </div>
        <div>
            <a href="index.php?module=contracts&action=index" class="btn btn-outline" style="padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none;">
                <i class="ph ph-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="split-layout">
        <!-- Sidebar -->
        <div class="templates-sidebar">
            <div class="sidebar-header">
                <h3 style="margin:0; font-size: 14px; font-weight: 600;">Mis Plantillas</h3>
                <button class="btn" onclick="newTemplate()" style="padding: 0.4rem 0.75rem; font-size: 12px; border-radius: 6px; border: 2px solid var(--primary-color); color: var(--primary-color); background: var(--bg-body); font-weight: 700; transition: all 0.2s; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.05);" onmouseover="this.style.background='var(--primary-color)'; this.style.color='white';" onmouseout="this.style.background='var(--bg-body)'; this.style.color='var(--primary-color)';">
                    <i class="ph ph-plus" style="font-weight: bold;"></i> NUEVA
                </button>
            </div>
            <div class="tpl-list">
                <?php if(empty($templates)): ?>
                    <div style="text-align: center; padding: 2rem 1rem; color: var(--text-muted);">
                        <i class="ph ph-files" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                        <p style="font-size: 13px; margin: 0;">No hay plantillas.<br>Crea una para empezar.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($templates as $tpl): ?>
                        <div class="tpl-item" id="tpl-item-<?php echo $tpl['id']; ?>" onclick="editTemplate(<?php echo htmlspecialchars(json_encode($tpl)); ?>)">
                            <div class="tpl-name"><?php echo htmlspecialchars($tpl['name']); ?></div>
                            <div class="tpl-meta">Modificado: <?php echo date('d M, Y', strtotime($tpl['updated_at'])); ?></div>
                            <?php 
                                $typeLabel = 'General'; $typeClass = 'type-general';
                                if($tpl['target_type'] == 'client') { $typeLabel = 'Cliente'; $typeClass = 'type-client'; }
                                if($tpl['target_type'] == 'staff') { $typeLabel = 'Personal'; $typeClass = 'type-staff'; }
                            ?>
                            <div class="tpl-type <?php echo $typeClass; ?>"><?php echo $typeLabel; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Editor -->
        <div class="editor-pane">
            <form id="templateForm" method="POST" onsubmit="return syncEditor()" style="display:flex; flex-direction:column; height:100%;">
                <input type="hidden" name="action_type" value="save_template">
                <input type="hidden" name="template_id" id="template_id" value="">
                
                <div class="editor-header">
                    <div style="display: flex; gap: 1rem; flex: 1;">
                        <div style="flex: 2;">
                            <label style="font-size:11px; font-weight:600; color:var(--text-muted); display:block; margin-bottom:0.25rem;">Nombre de la Plantilla</label>
                            <input type="text" name="name" id="tpl_name" class="form-control" style="padding:0.4rem 0.75rem; font-size:13px;" required placeholder="Ej: Contrato de Servicio">
                        </div>
                        <div style="flex: 1;">
                            <label style="font-size:11px; font-weight:600; color:var(--text-muted); display:block; margin-bottom:0.25rem;">Destinatario</label>
                            <select name="target_type" id="tpl_type" class="form-control" style="padding:0.4rem 0.75rem; font-size:13px;">
                                <option value="client">Cliente</option>
                                <option value="staff">Personal (RRHH)</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-left: 1.5rem; display: flex; gap: 0.5rem;">
                        <button type="button" class="btn btn-outline" id="btnDelete" onclick="deleteCurrentTemplate()" style="padding: 0.4rem 0.75rem; font-size: 13px; display: none; color: #dc2626; border-color: #fca5a5;">
                            <i class="ph ph-trash"></i>
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnSave" style="padding: 0.4rem 1.5rem; font-size: 13px;" disabled>
                            <i class="ph ph-floppy-disk"></i> Guardar
                        </button>
                    </div>
                </div>
                
                <div class="editor-body">
                    <div class="magic-vars">
                        <strong style="color:var(--color-title);">Variables Mágicas:</strong> Escríbelas en tu documento para que se llenen automáticamente.<br>
                        <code>[NOMBRE_AGENCIA]</code>, <code>[NOMBRE_CLIENTE]</code>, <code>[DNI_CLIENTE]</code>, <code>[NOMBRE_SERVICIO]</code>, <code>[PRECIO_TOTAL]</code>, <code>[NOMBRE_PERSONAL]</code>, <code>[DNI_PERSONAL]</code><br>
                        <strong style="color:var(--color-title); margin-top:0.5rem; display:inline-block;">Fecha y Lugar:</strong><br>
                        <code>[DÍA]</code>, <code>[MES]</code>, <code>[AÑO]</code>, <code>[CIUDAD]</code><br>
                        <strong style="color:var(--color-title); margin-top:0.5rem; display:inline-block;">Tablas Dinámicas:</strong><br>
                        <code>[TABLA_ENTREGABLES]</code> (Genera tabla de entregables del servicio)<br>
                        <code>[TABLA_CARACTERISTICAS]</code> (Genera tabla de características del servicio)
                    </div>
                    
                    <div id="editor_wrapper" style="pointer-events: none; opacity: 0.5;">
                        <textarea id="tpl_body" name="body"></textarea>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action_type" value="delete_template">
    <input type="hidden" name="template_id" id="delete_template_id">
</form>

<script>
let editorInstance;

document.addEventListener("DOMContentLoaded", function() {
    CKEDITOR.addCss('body { font-family: "Inter", sans-serif; font-size: 13px; } p { margin-bottom: 0.5rem; }');
    editorInstance = CKEDITOR.replace('tpl_body', {
        language: 'es',
        height: 500,
        versionCheck: false,
        contentsCss: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap',
        uiColor: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
        removePlugins: 'exportpdf',
        toolbarCanCollapse: false
    });
});

function syncEditor() {
    let content = CKEDITOR.instances.tpl_body.getData();
    if (content.trim() === '') {
        alert("El cuerpo del contrato no puede estar vacío.");
        return false;
    }
    return true;
}

function clearActiveItems() {
    document.querySelectorAll('.tpl-item').forEach(el => el.classList.remove('active'));
}

function newTemplate() {
    clearActiveItems();
    document.getElementById('template_id').value = '';
    document.getElementById('tpl_name').value = '';
    document.getElementById('tpl_type').value = 'client';
    
    document.getElementById('editor_wrapper').style.pointerEvents = 'auto';
    document.getElementById('editor_wrapper').style.opacity = '1';
    
    if(CKEDITOR.instances.tpl_body) CKEDITOR.instances.tpl_body.setData('');
    
    document.getElementById('btnSave').disabled = false;
    document.getElementById('btnDelete').style.display = 'none';
    document.getElementById('tpl_name').focus();
}

function editTemplate(tpl) {
    clearActiveItems();
    document.getElementById('tpl-item-' + tpl.id).classList.add('active');
    
    document.getElementById('template_id').value = tpl.id;
    document.getElementById('tpl_name').value = tpl.name;
    document.getElementById('tpl_type').value = tpl.target_type;
    
    document.getElementById('editor_wrapper').style.pointerEvents = 'auto';
    document.getElementById('editor_wrapper').style.opacity = '1';
    
    if(CKEDITOR.instances.tpl_body) CKEDITOR.instances.tpl_body.setData(tpl.body);
    
    document.getElementById('btnSave').disabled = false;
    document.getElementById('btnDelete').style.display = 'inline-flex';
}

function deleteCurrentTemplate() {
    if (confirm("¿Estás seguro de eliminar esta plantilla?")) {
        document.getElementById('delete_template_id').value = document.getElementById('template_id').value;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
