<!-- modules/conexiones/tabs/email_templates.php -->
<?php
global $db;
$stmt_tpl = $db->query("SELECT id, name, subject, created_at FROM email_templates ORDER BY created_at DESC");
$templates = $stmt_tpl->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">Plantillas Guardadas</h2>
    <a href="?module=conexiones&action=builder" class="btn btn-primary">
        <i class="ph ph-plus"></i> Nueva Plantilla
    </a>
</div>

<?php if (empty($templates)): ?>
    <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted); background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
        <i class="ph ph-envelope-simple" style="font-size: 3rem; color: var(--border-color); margin-bottom: 1rem;"></i>
        <p>No tienes ninguna plantilla de correo creada aún.</p>
        <a href="?module=conexiones&action=builder" class="btn btn-outline" style="margin-top: 0.5rem;">Crea tu primera plantilla</a>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted); text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; text-align: left;">
                    <th style="padding: 1rem;">Nombre</th>
                    <th style="padding: 1rem;">Asunto por Defecto</th>
                    <th style="padding: 1rem;">Fecha de Creación</th>
                    <th style="padding: 1rem; text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $tpl): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem; font-weight: 500;" data-label="Nombre">
                            <?php echo htmlspecialchars($tpl['name']); ?>
                        </td>
                        <td style="padding: 1rem; color: var(--text-muted);" data-label="Asunto">
                            <?php echo htmlspecialchars($tpl['subject']); ?>
                        </td>
                        <td style="padding: 1rem; color: var(--text-muted);" data-label="Fecha">
                            <?php echo date('d/m/Y H:i', strtotime($tpl['created_at'])); ?>
                        </td>
                        <td style="padding: 1rem; text-align: right;" data-label="Acciones">
                            <a href="?module=conexiones&action=builder&id=<?php echo $tpl['id']; ?>" class="btn btn-sm btn-outline" style="margin-right: 0.5rem;">
                                <i class="ph ph-pencil-simple"></i> Editar
                            </a>
                            <form method="POST" action="?module=conexiones&tab=tab-templates" style="display: inline-block;" onsubmit="return confirm('¿Seguro que deseas eliminar esta plantilla?');">
                                <input type="hidden" name="action_type" value="template_delete">
                                <input type="hidden" name="template_id" value="<?php echo $tpl['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="ph ph-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
