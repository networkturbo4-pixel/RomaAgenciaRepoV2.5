<?php
// modules/forms/submissions.php — View form submissions
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
.subs-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem}
.subs-header h1{font-size:1.3rem;font-weight:700;color:var(--color-title);margin:0;display:flex;align-items:center;gap:.5rem}
.subs-count{background:var(--primary-color);color:white;font-size:.75rem;font-weight:700;padding:.2rem .5rem;border-radius:20px;margin-left:.3rem}
.subs-table-wrap{background:var(--bg-surface);border:1px solid var(--border-color);border-radius:16px;overflow:hidden}
.subs-table{width:100%;border-collapse:collapse}
.subs-table th{background:var(--bg-color);padding:.8rem 1rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:1px solid var(--border-color)}
.subs-table td{padding:.8rem 1rem;border-bottom:1px solid var(--border-color);font-size:.85rem;color:var(--color-text);vertical-align:top}
.subs-table tr:last-child td{border-bottom:none}
.subs-table tr:hover td{background:color-mix(in srgb,var(--primary-color) 3%,transparent)}
.sub-status{font-size:.65rem;font-weight:700;padding:.2rem .5rem;border-radius:4px;text-transform:uppercase}
.sub-nuevo{background:rgba(59,130,246,.12);color:#2563eb}
.sub-revisado{background:rgba(16,185,129,.12);color:#059669}
.sub-archivado{background:rgba(100,116,139,.12);color:#64748b}
.sub-actions button{background:none;border:none;cursor:pointer;padding:.3rem;border-radius:6px;color:var(--text-muted);font-size:1rem}
.sub-actions button:hover{background:var(--bg-color);color:var(--primary-color)}
.detail-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1050;display:none;justify-content:flex-end}
.detail-overlay.active{display:flex}
.detail-panel{width:500px;max-width:90vw;background:var(--bg-surface);height:100%;overflow-y:auto;padding:2rem;box-shadow:-8px 0 30px rgba(0,0,0,.1);animation:slideIn .3s ease}
@keyframes slideIn{from{transform:translateX(100%)}to{transform:translateX(0)}}
.detail-panel h3{font-size:1.1rem;font-weight:700;color:var(--color-title);margin-bottom:1.5rem}
.detail-field{margin-bottom:1rem}
.detail-field-label{font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px;margin-bottom:.2rem}
.detail-field-value{font-size:.9rem;color:var(--color-title);line-height:1.4;word-break:break-word}
.empty-subs{text-align:center;padding:4rem 2rem;color:var(--text-muted)}
.empty-subs i{font-size:3rem;opacity:.3;margin-bottom:1rem;display:block}
@media(max-width:768px){.subs-table-wrap{overflow-x:auto}.detail-panel{width:100%}}
</style>

<div class="subs-header">
    <div style="display:flex;align-items:center;gap:.75rem">
        <a href="index.php?module=forms&action=index" class="btn btn-outline" style="padding:.4rem .7rem"><i class="ph ph-arrow-left"></i></a>
        <h1><i class="ph ph-envelope-open"></i> <?php echo htmlspecialchars($template['title']); ?> <span class="subs-count"><?php echo count($submissions); ?></span></h1>
    </div>
    <?php if($template['public_token'] && $template['status']==='active'): ?>
    <button class="btn btn-outline" onclick="shareThisForm()"><i class="ph ph-share-network"></i> Compartir</button>
    <?php endif; ?>
</div>

<?php if(empty($submissions)): ?>
<div class="card"><div class="empty-subs"><i class="ph ph-tray"></i><h3>Sin respuestas aún</h3><p>Comparte el formulario para empezar a recibir briefs.</p></div></div>
<?php else: ?>
<div class="subs-table-wrap">
    <table class="subs-table">
        <thead><tr>
            <th>Correlativo</th>
            <th>Respondente</th>
            <th>Email</th>
            <th>Mes</th>
            <th>Estado</th>
            <th>Fecha</th>
            <th></th>
        </tr></thead>
        <tbody>
        <?php foreach($submissions as $sub): ?>
        <tr>
            <td style="font-weight:700;color:var(--color-title)"><?php echo htmlspecialchars($sub['correlativo']); ?></td>
            <td><?php echo htmlspecialchars($sub['respondent_name'] ?: '—'); ?></td>
            <td><?php echo htmlspecialchars($sub['respondent_email'] ?: '—'); ?></td>
            <td><span style="font-size:.8rem"><?php echo htmlspecialchars($sub['submission_month']); ?></span></td>
            <td><span class="sub-status sub-<?php echo $sub['status']; ?>"><?php echo ucfirst($sub['status']); ?></span></td>
            <td style="font-size:.8rem;color:var(--text-muted)"><?php echo date('d M Y H:i', strtotime($sub['created_at'])); ?></td>
            <td class="sub-actions">
                <button onclick="viewDetail(<?php echo htmlspecialchars(json_encode($sub)); ?>)" title="Ver detalle"><i class="ph ph-eye"></i></button>
                <button onclick="downloadPDF(<?php echo htmlspecialchars(json_encode($sub)); ?>)" title="Descargar PDF"><i class="ph ph-file-pdf"></i></button>
                <a href="index.php?module=forms&action=view_submission&ref=<?php echo urlencode($sub['correlativo']); ?>" target="_blank" title="Ver público" style="background:none;border:none;cursor:pointer;padding:.3rem;border-radius:6px;color:var(--text-muted);font-size:1rem;text-decoration:none;display:inline-flex"><i class="ph ph-arrow-square-out"></i></a>
                <?php if($sub['status']==='nuevo'): ?>
                <button onclick="markRevisado(<?php echo $sub['id']; ?>)" title="Marcar revisado"><i class="ph ph-check-circle"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="detail-overlay" id="detailOverlay" onclick="if(event.target===this)closeDetail()">
    <div class="detail-panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
            <h3 id="detailTitle" style="margin:0"></h3>
            <div style="display:flex;gap:.5rem;align-items:center">
                <a href="#" id="detailPublicLink" target="_blank" style="background:none;border:1px solid var(--border-color);cursor:pointer;color:var(--primary-color);padding:.35rem .6rem;border-radius:6px;font-size:.8rem;font-weight:600;display:flex;align-items:center;gap:.3rem;font-family:inherit;text-decoration:none" title="Ver público"><i class="ph ph-arrow-square-out"></i> Ver</a>
                <button id="detailPdfBtn" onclick="downloadPDF(currentDetailSub)" style="background:none;border:1px solid var(--border-color);cursor:pointer;color:var(--danger-color);padding:.35rem .6rem;border-radius:6px;font-size:.8rem;font-weight:600;display:flex;align-items:center;gap:.3rem;font-family:inherit" title="Descargar PDF"><i class="ph ph-file-pdf"></i> PDF</button>
                <button onclick="closeDetail()" style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted)"><i class="ph ph-x"></i></button>
            </div>
        </div>
        <div id="detailContent"></div>
    </div>
</div>

<script>
const formFields = <?php echo json_encode($fields); ?>;
const formTitle = <?php echo json_encode($template['title']); ?>;
let currentDetailSub = null;

function viewDetail(sub) {
    currentDetailSub = sub;
    document.getElementById('detailTitle').textContent = sub.correlativo;
    document.getElementById('detailPublicLink').href = 'index.php?module=forms&action=view_submission&ref=' + encodeURIComponent(sub.correlativo);
    const data = JSON.parse(sub.data_json || '{}');
    let html = '';

    // Respondent info
    if(sub.respondent_name) html += `<div class="detail-field"><div class="detail-field-label">Nombre</div><div class="detail-field-value">${escH(sub.respondent_name)}</div></div>`;
    if(sub.respondent_email) html += `<div class="detail-field"><div class="detail-field-label">Email</div><div class="detail-field-value">${escH(sub.respondent_email)}</div></div>`;
    html += `<div class="detail-field"><div class="detail-field-label">Fecha de envío</div><div class="detail-field-value">${sub.created_at}</div></div>`;
    html += `<hr style="border:0;border-top:1px solid var(--border-color);margin:1rem 0">`;

    formFields.forEach(f => {
        if(f.type === 'divider') {
            html += `<div style="font-size:.9rem;font-weight:700;color:var(--color-title);margin:1rem 0 .5rem">${escH(f.label)}</div>`;
            return;
        }
        const key = 'field_' + f.id;
        let val = data[key];
        if(Array.isArray(val)) val = val.join(', ');
        if(!val && data[key + '[]']) val = Array.isArray(data[key+'[]']) ? data[key+'[]'].join(', ') : data[key+'[]'];

        // Check for drive file
        const driveUrl = data[key + '_drive_url'] || data['file_' + f.id + '_drive_url'];
        const fileName = data[key + '_file_name'] || data['file_' + f.id + '_file_name'];

        if(f.type === 'file' && driveUrl) {
            let urls = Array.isArray(driveUrl) ? driveUrl : [driveUrl];
            let names = Array.isArray(fileName) ? fileName : [fileName];
            let filesHtml = urls.map((url, idx) => {
                let fn = names[idx] || 'Ver archivo';
                return `<a href="${escH(url)}" target="_blank" style="color:var(--primary-color);font-weight:600;display:inline-flex;align-items:center;gap:4px;margin-right:12px;margin-bottom:4px;background:rgba(3,98,76,0.05);padding:4px 8px;border-radius:6px;border:1px solid rgba(3,98,76,0.1);"><i class="ph-fill ph-file-pdf" style="color:#ef4444"></i> ${escH(fn)}</a>`;
            }).join('');
            html += `<div class="detail-field"><div class="detail-field-label">${escH(f.label)}</div><div class="detail-field-value">${filesHtml}</div></div>`;
        } else {
            html += `<div class="detail-field"><div class="detail-field-label">${escH(f.label)}</div><div class="detail-field-value">${escH(val||'—')}</div></div>`;
        }
    });

    document.getElementById('detailContent').innerHTML = html;
    document.getElementById('detailOverlay').classList.add('active');
}

function closeDetail() { document.getElementById('detailOverlay').classList.remove('active'); }

async function markRevisado(id) {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('status', 'revisado');
    await fetch('index.php?module=forms&action=ajax_update_submission', {method:'POST', body:fd});
    window.location.reload();
}

function shareThisForm() {
    const url = window.location.origin + window.location.pathname + '?module=forms&action=fill&token=<?php echo htmlspecialchars($template['public_token']); ?>';
    navigator.clipboard.writeText(url).then(() => alert('¡Enlace copiado!')).catch(() => prompt('Copia este enlace:', url));
}

function escH(s){if(!s) return '';const d=document.createElement('div');d.textContent=s;return d.innerHTML}

function downloadPDF(sub) {
    if(!sub) return;
    const data = JSON.parse(sub.data_json || '{}');
    const brandColor = '<?php echo htmlspecialchars($global_settings['primary_color'] ?? '#03624c'); ?>';
    const siteName = '<?php echo htmlspecialchars($global_settings['site_name'] ?? 'Roma Agencia'); ?>';
    const logoUrl = '<?php echo htmlspecialchars($global_settings['logo_light'] ?? ''); ?>';
    const dateFormatted = new Date(sub.created_at).toLocaleDateString('es-ES',{day:'2-digit',month:'long',year:'numeric',hour:'2-digit',minute:'2-digit'});

    // Build field rows
    let fieldsHtml = '';
    let rowIdx = 0;
    formFields.forEach(f => {
        if(f.type === 'divider') {
            fieldsHtml += `<div style="background:${brandColor};color:white;padding:10px 20px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-top:${rowIdx>0?'8px':'0'}">${escH(f.label)}</div>`;
            rowIdx = 0;
            return;
        }
        const key = 'field_' + f.id;
        let val = data[key];
        if(Array.isArray(val)) val = val.join(', ');
        if(!val && data[key + '[]']) val = Array.isArray(data[key+'[]']) ? data[key+'[]'].join(', ') : data[key+'[]'];
        const driveUrl = data[key + '_drive_url'] || data['file_' + f.id + '_drive_url'];
        const fileName = data[key + '_file_name'] || data['file_' + f.id + '_file_name'];
        let valHtml = '';
        if(f.type === 'file' && driveUrl) {
            let urls = Array.isArray(driveUrl) ? driveUrl : [driveUrl];
            let names = Array.isArray(fileName) ? fileName : [fileName];
            valHtml = urls.map((url, idx) => {
                let fn = names[idx] || 'Ver archivo en Drive';
                return `<a href="${escH(url)}" style="color:${brandColor};font-weight:600;text-decoration:none;display:block;margin-bottom:4px;">📎 ${escH(fn)}</a>`;
            }).join('');
        }
        else valHtml = escH(val || '—');
        const bgColor = rowIdx % 2 === 0 ? '#ffffff' : '#f8fafc';
        fieldsHtml += `<div style="display:flex;background:${bgColor};border-bottom:1px solid #f1f5f9">
            <div style="width:38%;padding:12px 20px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;line-height:1.6">${escH(f.label)}${f.required?'<span style="color:#ef4444"> *</span>':''}</div>
            <div style="width:62%;padding:12px 20px;font-size:13px;color:#1e293b;line-height:1.6;word-break:break-word">${valHtml}</div>
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

    <!-- Header Banner -->
    <div style="background:linear-gradient(135deg,${brandColor},#065f46);padding:40px;color:white;position:relative;overflow:hidden">
        <div style="position:absolute;top:-30px;right:-30px;width:180px;height:180px;background:rgba(255,255,255,.06);border-radius:50%"></div>
        <div style="position:absolute;bottom:-50px;right:80px;width:120px;height:120px;background:rgba(255,255,255,.04);border-radius:50%"></div>
        ${logoUrl ? `<img src="${logoUrl}" style="height:30px;margin-bottom:16px;filter:brightness(0) invert(1)">` : ''}
        <div style="font-size:28px;font-weight:800;margin-bottom:4px;letter-spacing:-.5px">${escH(formTitle)}</div>
        <div style="font-size:14px;opacity:.8;font-weight:400">Brief de servicio</div>
    </div>

    <div style="padding:30px 40px">

        <!-- Info Cards -->
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

        <!-- Respondent Info -->
        ${sub.respondent_name || sub.respondent_email ? `
        <div style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border:1px solid #bbf7d0;border-radius:10px;padding:18px 20px;margin-bottom:24px;display:flex;align-items:center;gap:16px">
            <div style="width:48px;height:48px;background:${brandColor};border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:20px;font-weight:800;flex-shrink:0">${escH((sub.respondent_name||'?')[0].toUpperCase())}</div>
            <div>
                ${sub.respondent_name ? `<div style="font-size:15px;font-weight:700;color:#1e293b">${escH(sub.respondent_name)}</div>` : ''}
                ${sub.respondent_email ? `<div style="font-size:13px;color:#64748b;margin-top:2px">${escH(sub.respondent_email)}</div>` : ''}
            </div>
        </div>` : ''}

        <!-- Fields -->
        <div style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden">
            <div style="background:${brandColor};color:white;padding:10px 20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px">Respuestas del Brief</div>
            ${fieldsHtml}
        </div>

        <!-- Footer -->
        <div style="margin-top:36px;padding-top:20px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
            <div style="font-size:10px;color:#94a3b8">Generado el ${new Date().toLocaleDateString('es-ES',{day:'2-digit',month:'long',year:'numeric'})} • Documento confidencial</div>
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
