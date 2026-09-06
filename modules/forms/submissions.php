<?php
// modules/forms/submissions.php — Modern View Form Submissions
if (!isset($_SESSION['user_id'])) { header("Location: index.php?module=auth&action=login"); exit(); }

$template_id = $_GET['id'] ?? '';
if (empty($template_id)) { header("Location: index.php?module=forms&action=index"); exit(); }

$stmt = $db->prepare("SELECT * FROM form_templates WHERE id = ?");
$stmt->execute([$template_id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$template) { die("Formulario no encontrado."); }

$fields = json_decode($template['fields_json'] ?: '[]', true);

$stmtSubs = $db->prepare("SELECT * FROM form_submissions WHERE template_id = ? ORDER BY created_at DESC");
$stmtSubs->execute([$template_id]);
$submissions = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>
<style>
.subs-page-wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0.5rem 0 3rem;
}

.subs-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.subs-topbar-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.subs-btn-back {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    color: var(--color-title);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 1.1rem;
    transition: all 0.15s ease;
}

.subs-btn-back:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.subs-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--color-title);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.subs-badge-count {
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    color: var(--primary-color);
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
}

/* Modern Table Card */
.subs-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.02);
}

.subs-table {
    width: 100%;
    border-collapse: collapse;
}

.subs-table th {
    background: color-mix(in srgb, var(--bg-surface) 50%, var(--bg-color));
    padding: 0.85rem 1.25rem;
    text-align: left;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border-color);
}

.subs-table td {
    padding: 0.95rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.85rem;
    color: var(--color-text);
    vertical-align: middle;
}

.subs-table tr:last-child td {
    border-bottom: none;
}

.subs-table tr:hover td {
    background: color-mix(in srgb, var(--primary-color) 4%, transparent);
}

.subs-status-pill {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.25rem 0.6rem;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    display: inline-block;
}

.status-nuevo { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.status-revisado { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.status-archivado { background: rgba(100, 116, 139, 0.12); color: #64748b; }

.subs-action-btn {
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    padding: 0.35rem;
    border-radius: 8px;
    color: var(--text-muted);
    font-size: 1.1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
    text-decoration: none;
}

.subs-action-btn:hover {
    background: var(--bg-color);
    border-color: var(--border-color);
    color: var(--primary-color);
}

/* Empty State */
.subs-empty-state {
    text-align: center;
    padding: 4.5rem 1.5rem;
    color: var(--text-muted);
}

.subs-empty-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: color-mix(in srgb, var(--primary-color) 10%, transparent);
    color: var(--primary-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 1rem;
}

/* Detail Slide-in Drawer */
.detail-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 1050;
    display: none;
    justify-content: flex-end;
}

.detail-overlay.active {
    display: flex;
}

.detail-panel {
    width: 540px;
    max-width: 95vw;
    background: var(--bg-surface);
    height: 100%;
    overflow-y: auto;
    padding: 2rem;
    box-shadow: -8px 0 30px rgba(0, 0, 0, 0.1);
    animation: drawerSlide 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}

@keyframes drawerSlide {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

.detail-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 1.5rem;
}

.detail-field-box {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 0.85rem;
}

.detail-field-lbl {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 0.35rem;
}

.detail-field-val {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--color-title);
    line-height: 1.5;
    word-break: break-word;
}
</style>

<div class="subs-page-wrap">
    <div class="subs-topbar">
        <div class="subs-topbar-left">
            <a href="index.php?module=forms&action=index" class="subs-btn-back" title="Volver a formularios">
                <i class="ph-bold ph-arrow-left"></i>
            </a>
            <h1 class="subs-title">
                <?php echo htmlspecialchars($template['title']); ?>
                <span class="subs-badge-count"><?php echo count($submissions); ?> respuestas</span>
            </h1>
        </div>
        <?php if($template['public_token'] && $template['status']==='active'): ?>
        <button class="btn btn-outline" onclick="shareThisForm()" style="border-radius:10px; font-weight:600; font-size:0.8125rem;">
            <i class="ph-bold ph-share-network"></i> Compartir Enlace
        </button>
        <?php endif; ?>
    </div>

    <?php if(empty($submissions)): ?>
    <div class="subs-card">
        <div class="subs-empty-state">
            <div class="subs-empty-icon"><i class="ph-bold ph-tray"></i></div>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-title); margin-bottom: 0.4rem;">Sin respuestas todavía</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); max-width: 360px; margin: 0 auto 1.25rem;">Comparte el enlace del formulario con tus clientes para comenzar a recibir briefs.</p>
            <?php if($template['public_token'] && $template['status']==='active'): ?>
            <button class="btn btn-primary" onclick="shareThisForm()" style="border-radius:10px; font-weight:600;">
                <i class="ph-bold ph-share-network"></i> Compartir Formulario
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="subs-card">
        <div style="overflow-x: auto;">
            <table class="subs-table">
                <thead>
                    <tr>
                        <th>Correlativo</th>
                        <th>Respondente</th>
                        <th>Email</th>
                        <th>Mes</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($submissions as $sub): ?>
                <tr>
                    <td style="font-weight:700; color:var(--color-title); font-family:monospace; font-size:0.85rem;">
                        <?php echo htmlspecialchars($sub['correlativo']); ?>
                    </td>
                    <td style="font-weight:600; color:var(--color-title);">
                        <?php echo htmlspecialchars($sub['respondent_name'] ?: '—'); ?>
                    </td>
                    <td style="color:var(--text-muted);">
                        <?php echo htmlspecialchars($sub['respondent_email'] ?: '—'); ?>
                    </td>
                    <td>
                        <span style="font-size: 0.8rem; font-weight: 500;"><?php echo htmlspecialchars($sub['submission_month']); ?></span>
                    </td>
                    <td>
                        <span class="subs-status-pill status-<?php echo $sub['status']; ?>">
                            <?php echo ucfirst($sub['status']); ?>
                        </span>
                    </td>
                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                        <?php echo date('d M Y, H:i', strtotime($sub['created_at'])); ?>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 4px;">
                            <button class="subs-action-btn" onclick="viewDetail(<?php echo htmlspecialchars(json_encode($sub)); ?>)" title="Ver respuestas">
                                <i class="ph-bold ph-eye"></i>
                            </button>
                            <button class="subs-action-btn" onclick="downloadPDF(<?php echo htmlspecialchars(json_encode($sub)); ?>)" title="Exportar PDF">
                                <i class="ph-bold ph-file-pdf"></i>
                            </button>
                            <a href="index.php?module=forms&action=view_submission&ref=<?php echo urlencode($sub['correlativo']); ?>" target="_blank" class="subs-action-btn" title="Ver enlace público">
                                <i class="ph-bold ph-arrow-square-out"></i>
                            </a>
                            <?php if($sub['status']==='nuevo'): ?>
                            <button class="subs-action-btn" onclick="markRevisado(<?php echo $sub['id']; ?>)" title="Marcar como revisado" style="color:#10b981;">
                                <i class="ph-bold ph-check-circle"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Slide-in Detail Drawer -->
<div class="detail-overlay" id="detailOverlay" onclick="if(event.target===this)closeDetail()">
    <div class="detail-panel">
        <div class="detail-panel-header">
            <div>
                <h3 id="detailTitle" style="margin:0; font-size:1.15rem; font-weight:700; color:var(--color-title);"></h3>
                <span id="detailDate" style="font-size:0.78rem; color:var(--text-muted);"></span>
            </div>
            <div style="display:flex; gap:0.4rem; align-items:center;">
                <a href="#" id="detailPublicLink" target="_blank" class="btn btn-outline btn-sm" style="border-radius:8px;" title="Ver público">
                    <i class="ph-bold ph-arrow-square-out"></i> Ver
                </a>
                <button id="detailPdfBtn" onclick="downloadPDF(currentDetailSub)" class="btn btn-outline btn-sm" style="border-radius:8px; color:#ef4444; border-color:rgba(239,68,68,0.3);" title="Descargar PDF">
                    <i class="ph-bold ph-file-pdf"></i> PDF
                </button>
                <button onclick="closeDetail()" class="subs-action-btn" style="font-size:1.25rem;">
                    <i class="ph-bold ph-x"></i>
                </button>
            </div>
        </div>
        <div id="detailContent" style="flex:1; overflow-y:auto;"></div>
    </div>
</div>

<script>
const formFields = <?php echo json_encode($fields); ?>;
const formTitle = <?php echo json_encode($template['title']); ?>;
let currentDetailSub = null;

function viewDetail(sub) {
    currentDetailSub = sub;
    document.getElementById('detailTitle').textContent = sub.correlativo;
    document.getElementById('detailDate').textContent = new Date(sub.created_at).toLocaleDateString('es-ES', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
    document.getElementById('detailPublicLink').href = 'index.php?module=forms&action=view_submission&ref=' + encodeURIComponent(sub.correlativo);
    
    const data = JSON.parse(sub.data_json || '{}');
    let html = '';

    // Contact info box
    if (sub.respondent_name || sub.respondent_email) {
        html += `
        <div style="background:color-mix(in srgb, var(--primary-color) 8%, transparent); border:1px solid color-mix(in srgb, var(--primary-color) 20%, transparent); border-radius:12px; padding:1rem; margin-bottom:1.25rem;">
            <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; color:var(--primary-color); letter-spacing:0.5px; margin-bottom:0.4rem;">Datos del Contacto</div>
            ${sub.respondent_name ? `<div style="font-weight:700; font-size:0.95rem; color:var(--color-title);">${escH(sub.respondent_name)}</div>` : ''}
            ${sub.respondent_email ? `<div style="font-size:0.85rem; color:var(--text-muted);">${escH(sub.respondent_email)}</div>` : ''}
        </div>`;
    }

    formFields.forEach(f => {
        if (f.type === 'divider') {
            html += `<h4 style="font-size:0.95rem; font-weight:700; color:var(--color-title); margin:1.25rem 0 0.5rem; padding-bottom:0.35rem; border-bottom:1px solid var(--border-color);">${escH(f.label)}</h4>`;
            return;
        }
        const key = 'field_' + f.id;
        let val = data[key];
        if (Array.isArray(val)) val = val.join(', ');
        if (!val && data[key + '[]']) val = Array.isArray(data[key+'[]']) ? data[key+'[]'].join(', ') : data[key+'[]'];

        const driveUrl = data[key + '_drive_url'] || data['file_' + f.id + '_drive_url'];
        const fileName = data[key + '_file_name'] || data['file_' + f.id + '_file_name'];

        if (f.type === 'file' && driveUrl) {
            let urls = Array.isArray(driveUrl) ? driveUrl : [driveUrl];
            let names = Array.isArray(fileName) ? fileName : [fileName];
            let filesHtml = urls.map((url, idx) => {
                let fn = names[idx] || 'Ver archivo';
                return `<a href="${escH(url)}" target="_blank" style="color:var(--primary-color); font-weight:600; display:inline-flex; align-items:center; gap:6px; margin:4px 8px 4px 0; background:var(--bg-surface); padding:6px 10px; border-radius:8px; border:1px solid var(--border-color); text-decoration:none; font-size:0.8rem;"><i class="ph-bold ph-paperclip"></i> ${escH(fn)}</a>`;
            }).join('');
            html += `<div class="detail-field-box"><div class="detail-field-lbl">${escH(f.label)}</div><div class="detail-field-val">${filesHtml}</div></div>`;
        } else if (f.type === 'image_compare' && f.compare_options) {
            const selectedVals = Array.isArray(data[key]) ? data[key] : (val ? val.split(',').map(s => s.trim()) : []);
            let compareHtml = '<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 10px; margin-top: 6px;">';
            f.compare_options.forEach(opt => {
                const optTitle = opt.title || 'Opción';
                const isSelected = selectedVals.includes(optTitle);
                if (!isSelected && !opt.is_correct) return;
                compareHtml += `
                <div style="border: 2px solid ${isSelected ? (opt.is_correct ? '#10b981' : 'var(--primary-color)') : 'var(--border-color)'}; background: ${isSelected ? 'color-mix(in srgb, var(--primary-color) 8%, transparent)' : 'var(--bg-surface)'}; border-radius: 10px; padding: 10px; position: relative;">
                    <div style="display:flex; gap:4px; margin-bottom:6px; flex-wrap:wrap;">
                        ${isSelected ? '<span style="background:var(--primary-color); color:#fff; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px;"><i class="ph-bold ph-check"></i> Elegida</span>' : ''}
                        ${opt.is_correct ? '<span style="background:#10b981; color:#fff; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px;"><i class="ph-bold ph-seal-check"></i> Correcta</span>' : ''}
                    </div>
                    ${opt.opt_type === 'image' && opt.image ? `<img src="${escH(opt.image)}" style="width:100%; height:75px; object-fit:cover; border-radius:6px; margin-bottom:6px;">` : ''}
                    ${opt.opt_type === 'icon' ? `<div style="font-size:1.8rem; text-align:center; padding:6px 0; color:var(--primary-color);"><i class="${escH(opt.icon || 'ph-bold ph-check-circle')}"></i></div>` : ''}
                    <div style="font-weight:700; font-size:0.85rem; color:var(--color-title);">${escH(optTitle)}</div>
                    ${opt.desc ? `<div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">${escH(opt.desc)}</div>` : ''}
                </div>`;
            });
            compareHtml += '</div>';
            html += `<div class="detail-field-box"><div class="detail-field-lbl">${escH(f.label)}</div><div class="detail-field-val">${compareHtml}</div></div>`;
        } else {
            html += `<div class="detail-field-box"><div class="detail-field-lbl">${escH(f.label)}</div><div class="detail-field-val">${escH(val || '—')}</div></div>`;
        }
    });

    document.getElementById('detailContent').innerHTML = html;
    document.getElementById('detailOverlay').classList.add('active');
}

function closeDetail() { 
    document.getElementById('detailOverlay').classList.remove('active'); 
}

async function markRevisado(id) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('status', 'revisado');
    await fetch('index.php?module=forms&action=ajax_update_submission', { method: 'POST', body: fd });
    window.location.reload();
}

function shareThisForm() {
    const token = '<?php echo htmlspecialchars($template['public_token'] ?? ''); ?>';
    const shortToken = (token && token.length > 8) ? token.substring(0, 8) : token;
    const basePath = window.location.pathname.replace(/\/index\.php$/, '').replace(/\/$/, '');
    const url = window.location.origin + (basePath ? basePath : '') + '/f/' + shortToken;
    navigator.clipboard.writeText(url).then(() => alert('¡Enlace corto del formulario copiado al portapapeles!\n' + url)).catch(() => prompt('Copia este enlace:', url));
}

function escH(s) { 
    if (!s) return ''; 
    const d = document.createElement('div'); 
    d.textContent = s; 
    return d.innerHTML; 
}

function downloadPDF(sub) {
    if (!sub) return;
    const data = JSON.parse(sub.data_json || '{}');
    const brandColor = '<?php echo htmlspecialchars($global_settings['primary_color'] ?? '#4f46e5'); ?>';
    const siteName = '<?php echo htmlspecialchars($global_settings['site_name'] ?? 'Roma Agencia'); ?>';
    const logoUrl = '<?php echo htmlspecialchars($global_settings['logo_light'] ?? ''); ?>';
    const dateFormatted = new Date(sub.created_at).toLocaleDateString('es-ES', { day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit' });

    let fieldsHtml = '';
    let rowIdx = 0;
    formFields.forEach(f => {
        if (f.type === 'divider') {
            fieldsHtml += `<div style="background:${brandColor}; color:white; padding:10px 20px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-top:${rowIdx>0?'12px':'0'}">${escH(f.label)}</div>`;
            rowIdx = 0;
            return;
        }
        const key = 'field_' + f.id;
        let val = data[key];
        if (Array.isArray(val)) val = val.join(', ');
        if (!val && data[key + '[]']) val = Array.isArray(data[key+'[]']) ? data[key+'[]'].join(', ') : data[key+'[]'];
        const driveUrl = data[key + '_drive_url'] || data['file_' + f.id + '_drive_url'];
        const fileName = data[key + '_file_name'] || data['file_' + f.id + '_file_name'];
        let valHtml = '';
        if (f.type === 'file' && driveUrl) {
            let urls = Array.isArray(driveUrl) ? driveUrl : [driveUrl];
            let names = Array.isArray(fileName) ? fileName : [fileName];
            valHtml = urls.map((url, idx) => {
                let fn = names[idx] || 'Ver archivo';
                return `<a href="${escH(url)}" style="color:${brandColor}; font-weight:600; text-decoration:none; display:block; margin-bottom:4px;">📎 ${escH(fn)}</a>`;
            }).join('');
        } else if (f.type === 'image_compare' && f.compare_options) {
            const selectedVals = Array.isArray(data[key]) ? data[key] : (val ? val.split(',').map(s => s.trim()) : []);
            valHtml = '<div style="display:flex; flex-wrap:wrap; gap:8px;">';
            f.compare_options.forEach(opt => {
                const optTitle = opt.title || 'Opción';
                const isSelected = selectedVals.includes(optTitle);
                if (isSelected) {
                    valHtml += `<div style="border:1.5px solid ${opt.is_correct ? '#10b981' : brandColor}; border-radius:6px; padding:6px 10px; background:#f8fafc; font-size:12px;">
                        ${opt.opt_type === 'image' && opt.image ? `<img src="${escH(opt.image)}" style="max-height:40px; border-radius:4px; display:block; margin-bottom:4px;">` : ''}
                        <strong>${escH(optTitle)}</strong> ${opt.is_correct ? '<span style="color:#10b981; font-weight:700;">✓ Correcta</span>' : ''}
                    </div>`;
                }
            });
            valHtml += '</div>';
        } else {
            valHtml = escH(val || '—');
        }
        const bgColor = rowIdx % 2 === 0 ? '#ffffff' : '#f8fafc';
        fieldsHtml += `<div style="display:flex; background:${bgColor}; border-bottom:1px solid #f1f5f9">
            <div style="width:38%; padding:12px 20px; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.5px; line-height:1.6">${escH(f.label)}${f.required?'<span style="color:#ef4444"> *</span>':''}</div>
            <div style="width:62%; padding:12px 20px; font-size:13px; color:#1e293b; line-height:1.6; word-break:break-word">${valHtml}</div>
        </div>`;
        rowIdx++;
    });

    const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>${escH(sub.correlativo)} - ${escH(formTitle)}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page{size:A4;margin:0}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',Arial,sans-serif;color:#1e293b;background:#fff}
        @media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
    </style></head><body>

    <div style="background:linear-gradient(135deg,${brandColor},#312e81);padding:40px;color:white;position:relative;overflow:hidden">
        ${logoUrl ? `<img src="${logoUrl}" style="height:30px;margin-bottom:16px;filter:brightness(0) invert(1)">` : ''}
        <div style="font-size:26px;font-weight:800;margin-bottom:4px;letter-spacing:-.5px">${escH(formTitle)}</div>
        <div style="font-size:13px;opacity:.8;font-weight:400">Brief de respuesta del cliente</div>
    </div>

    <div style="padding:30px 40px">
        <div style="display:flex;gap:12px;margin-bottom:28px;flex-wrap:wrap">
            <div style="flex:1;min-width:140px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px">
                <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Correlativo</div>
                <div style="font-size:16px;font-weight:800;color:${brandColor}">${escH(sub.correlativo)}</div>
            </div>
            <div style="flex:1;min-width:140px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px">
                <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Fecha</div>
                <div style="font-size:13px;font-weight:600;color:#334155">${dateFormatted}</div>
            </div>
            <div style="flex:1;min-width:140px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px">
                <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Período</div>
                <div style="font-size:13px;font-weight:600;color:#334155">${escH(sub.submission_month)}</div>
            </div>
        </div>

        ${sub.respondent_name || sub.respondent_email ? `
        <div style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border:1px solid #bbf7d0;border-radius:10px;padding:18px 20px;margin-bottom:24px;display:flex;align-items:center;gap:16px">
            <div style="width:44px;height:44px;background:${brandColor};border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;font-weight:800;flex-shrink:0">${escH((sub.respondent_name||'?')[0].toUpperCase())}</div>
            <div>
                ${sub.respondent_name ? `<div style="font-size:15px;font-weight:700;color:#1e293b">${escH(sub.respondent_name)}</div>` : ''}
                ${sub.respondent_email ? `<div style="font-size:13px;color:#64748b;margin-top:2px">${escH(sub.respondent_email)}</div>` : ''}
            </div>
        </div>` : ''}

        <div style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden">
            <div style="background:${brandColor};color:white;padding:10px 20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px">Respuestas Registradas</div>
            ${fieldsHtml}
        </div>

        <div style="margin-top:36px;padding-top:20px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
            <div style="font-size:10px;color:#94a3b8">Generado el ${new Date().toLocaleDateString('es-ES',{day:'2-digit',month:'long',year:'numeric'})} • Documento de sistema</div>
            <div style="font-size:10px;font-weight:700;color:#94a3b8">${siteName}</div>
        </div>
    </div>
    </body></html>`;

    const printWin = window.open('', '_blank', 'width=800,height=600');
    printWin.document.write(html);
    printWin.document.close();
    printWin.focus();
    setTimeout(() => { printWin.print(); }, 400);
}
</script>
<?php require_once 'includes/footer.php'; ?>

