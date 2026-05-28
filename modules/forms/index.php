<?php
// modules/forms/index.php — List of form templates
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}
require_once 'includes/header.php';

$stmt = $db->query("SELECT ft.*, 
    (SELECT COUNT(*) FROM form_submissions WHERE template_id = ft.id) as submission_count,
    u.name as creator_name
    FROM form_templates ft 
    LEFT JOIN users u ON ft.created_by = u.id
    ORDER BY ft.created_at DESC");
$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.page-header {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    flex-wrap: wrap;
}
.header-icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: rgba(3, 98, 76, 0.1); color: var(--primary-color);
    display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
}
.forms-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem; }

.form-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
    overflow: hidden;
}
.form-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(0,0,0,0.08); }

.form-card-title { font-size: 1.15rem; font-weight: 700; color: var(--color-title); margin: 0 0 0.25rem 0; }
.form-card-desc { font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.form-card-stats { display: flex; gap: 0.75rem; }
.form-stat-box {
    flex: 1;
    background: var(--bg-color);
    border-radius: 8px;
    padding: 0.75rem;
    text-align: center;
    border: 1px solid var(--border-color);
}
.form-stat-num { font-size: 1.15rem; font-weight: 700; color: var(--color-title); line-height: 1; margin-bottom: 0.25rem; }
.form-stat-label { font-size: 0.65rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

.form-card-status {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    padding: 0.35rem 0.75rem; border-radius: 20px; letter-spacing: 0.5px;
}
.status-active { background: rgba(16,185,129,0.12); color: #059669; }
.status-draft { background: rgba(245,158,11,0.12); color: #d97706; }
.status-archived { background: rgba(100,116,139,0.12); color: #64748b; }

.form-card-actions { display: flex; gap: 0.5rem; margin-top: auto; }
.form-card-actions .btn { flex: 1; font-size: 0.8rem; padding: 0.6rem 0.75rem; display: flex; align-items: center; justify-content: center; gap: 0.3rem; border-radius: 8px; font-weight: 600; }

.empty-state { text-align: center; padding: 4rem 2rem; }
.empty-state i { font-size: 4rem; color: var(--text-muted); opacity: 0.3; margin-bottom: 1rem; }
.empty-state h3 { color: var(--color-title); margin-bottom: 0.5rem; }
.empty-state p { color: var(--text-muted); margin-bottom: 1.5rem; }

@media (max-width: 768px) {
    .forms-grid { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; align-items: stretch; text-align: center; }
    .page-header > div { flex-direction: column; justify-content: center; }
}
</style>

<div class="page-header">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div class="header-icon"><i class="ph-fill ph-note-pencil"></i></div>
        <div>
            <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--color-title);">Formularios</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Gestiona y crea plantillas de briefing para tus clientes</p>
        </div>
    </div>
    <a href="index.php?module=forms&action=builder" class="btn btn-primary" style="padding: 0.6rem 1.2rem; font-weight: 600; border-radius: 8px;">
        <i class="ph ph-plus"></i> Nuevo Formulario
    </a>
</div>

<?php if (empty($forms)): ?>
<div class="card" style="padding: 0; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: 16px;">
    <div class="empty-state">
        <i class="ph-fill ph-file-dashed"></i>
        <h3>Aún no tienes formularios</h3>
        <p>Crea tu primer formulario de brief para compartirlo y recopilar información de tus clientes fácilmente.</p>
        <a href="index.php?module=forms&action=builder" class="btn btn-primary" style="border-radius: 8px; padding: 0.6rem 1.5rem;">
            <i class="ph ph-plus"></i> Crear mi primer formulario
        </a>
    </div>
</div>
<?php else: ?>
<div class="forms-grid">
    <?php foreach($forms as $form): 
        $statusClass = 'status-' . $form['status'];
        $statusLabel = ['active' => 'Publicado', 'draft' => 'Borrador', 'archived' => 'Archivado'][$form['status']] ?? $form['status'];
        $fields = json_decode($form['fields_json'] ?: '[]', true);
    ?>
    <div class="form-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
            <div style="flex: 1;">
                <h3 class="form-card-title"><?php echo htmlspecialchars($form['title']); ?></h3>
                <p class="form-card-desc"><?php echo htmlspecialchars($form['description'] ?: 'Sin descripción adicional para este formulario.'); ?></p>
            </div>
            <span class="form-card-status <?php echo $statusClass; ?>"><i class="ph-fill <?php echo $form['status']==='active'?'ph-check-circle':($form['status']==='draft'?'ph-pencil-simple':'ph-archive'); ?>"></i> <?php echo $statusLabel; ?></span>
        </div>

        <div class="form-card-stats">
            <div class="form-stat-box">
                <div class="form-stat-num"><?php echo (int)$form['submission_count']; ?></div>
                <div class="form-stat-label">Respuestas</div>
            </div>
            <div class="form-stat-box">
                <div class="form-stat-num"><?php echo count($fields); ?></div>
                <div class="form-stat-label">Campos</div>
            </div>
            <div class="form-stat-box" style="display:flex; flex-direction:column; justify-content:center;">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;"><i class="ph ph-calendar-blank"></i> Creado el</div>
                <div style="font-size: 0.8rem; font-weight: 700; color: var(--color-title); margin-top: 2px;"><?php echo date('d/m/Y', strtotime($form['created_at'])); ?></div>
            </div>
        </div>

        <hr style="border:0; border-top: 1px solid var(--border-color); margin: 0;">

        <div class="form-card-actions">
            <a href="index.php?module=forms&action=builder&id=<?php echo $form['id']; ?>" class="btn" title="Editar" style="flex: 1; background: rgba(100, 116, 139, 0.7); color: white; border: none; padding: 0.6rem;">
                <i class="ph ph-pencil-simple" style="font-size: 1.25rem;"></i>
            </a>
            <?php if($form['status'] === 'active' && $form['public_token']): ?>
            <button class="btn" style="flex: 1; background: rgba(3, 98, 76, 0.7); color: white; border: none; padding: 0.6rem;" onclick="shareForm('<?php echo htmlspecialchars($form['public_token']); ?>', '<?php echo htmlspecialchars(addslashes($form['title'])); ?>')" title="Compartir">
                <i class="ph ph-share-network" style="font-size: 1.25rem;"></i>
            </button>
            <?php endif; ?>
            <a href="index.php?module=forms&action=submissions&id=<?php echo $form['id']; ?>" class="btn" title="Ver respuestas" style="flex: 1; background: rgba(17, 24, 39, 0.7); color: white; border: none; padding: 0.6rem;">
                <i class="ph ph-envelope-open" style="font-size: 1.25rem;"></i>
            </a>
            <button class="btn" style="flex: 1; background: rgba(239, 68, 68, 0.7); color: white; border: none; padding: 0.6rem;" onclick="deleteForm(<?php echo $form['id']; ?>)" title="Eliminar">
                <i class="ph ph-trash" style="font-size: 1.25rem;"></i>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Share Modal -->
<div class="modal-overlay" id="shareFormModal">
    <div class="modal-content" style="max-width: 480px; text-align: center;">
        <div class="modal-header" style="justify-content: center; position: relative;">
            <h2 class="modal-title">Compartir Formulario</h2>
            <button class="btn-icon close-modal" onclick="document.getElementById('shareFormModal').classList.remove('active')" style="position: absolute; right: 0;"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="padding: 2rem 1.5rem;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.5rem;">
                <i class="ph ph-link"></i>
            </div>
            <p id="shareFormTitle" style="font-weight: 700; font-size: 1.1rem; color: var(--color-title); margin-bottom: 0.5rem;"></p>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem;">Copia este enlace y envíalo a tu cliente para que rellene el formulario.</p>
            <input type="text" id="shareFormLink" class="form-control" readonly style="text-align: center; margin-bottom: 1rem; font-size: 0.85rem;">
            <div style="display: flex; gap: 0.75rem;">
                <button class="btn btn-primary" onclick="copyFormLink()" style="flex: 1;">
                    <i class="ph ph-copy"></i> Copiar Enlace
                </button>
                <a id="shareFormWhatsapp" href="#" target="_blank" class="btn btn-outline" style="flex: 1; color: #25d366; border-color: #25d366;">
                    <i class="ph ph-whatsapp-logo"></i> WhatsApp
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal-overlay" id="deleteFormModal" style="z-index: 1070;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="justify-content: center; border-bottom: none; padding-bottom: 0; margin-top: 1rem;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(239, 68, 68, 0.1); color: var(--danger-color); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto;">
                <i class="ph ph-warning"></i>
            </div>
        </div>
        <div class="modal-body" style="text-align: center; padding-top: 1rem;">
            <h3 style="margin-bottom: 0.5rem; color: var(--color-title); font-size: 1.25rem; font-weight: 600;">¿Eliminar formulario?</h3>
            <p style="margin-bottom: 0; color: var(--text-muted);">Se eliminarán también todas las respuestas. Esta acción no se puede deshacer.</p>
        </div>
        <div class="modal-footer" style="justify-content: center; border-top: none; padding-top: 0.5rem; gap: 1rem;">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('deleteFormModal').classList.remove('active')">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnConfirmDeleteForm" style="background-color: var(--danger-color); border-color: var(--danger-color);">
                Sí, eliminar
            </button>
        </div>
    </div>
</div>

<script>
function shareForm(token, title) {
    const baseUrl = window.location.origin + window.location.pathname;
    const url = baseUrl + '?module=forms&action=fill&token=' + token;
    document.getElementById('shareFormLink').value = url;
    document.getElementById('shareFormTitle').textContent = title;
    document.getElementById('shareFormWhatsapp').href = 'https://wa.me/?text=' + encodeURIComponent('Hola, por favor rellena este formulario: ' + url);
    document.getElementById('shareFormModal').classList.add('active');
}

function copyFormLink() {
    const input = document.getElementById('shareFormLink');
    input.select();
    document.execCommand('copy');
    const btn = event.currentTarget;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="ph ph-check"></i> ¡Copiado!';
    setTimeout(() => btn.innerHTML = orig, 2000);
}

let formToDeleteId = null;
function deleteForm(id) {
    formToDeleteId = id;
    document.getElementById('btnConfirmDeleteForm').onclick = async function() {
        const fd = new FormData();
        fd.append('id', formToDeleteId);
        const res = await fetch('index.php?module=forms&action=ajax_delete_template', {method:'POST', body: fd});
        const data = await res.json();
        if (data.success) window.location.reload();
        else alert(data.error || 'Error al eliminar.');
        document.getElementById('deleteFormModal').classList.remove('active');
    };
    document.getElementById('deleteFormModal').classList.add('active');
}
</script>

<?php require_once 'includes/footer.php'; ?>
