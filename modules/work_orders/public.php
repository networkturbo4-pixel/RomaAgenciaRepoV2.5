<?php
// modules/work_orders/public.php
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Token inválido.");
}

$stmt = $db->prepare("SELECT * FROM work_orders WHERE public_token = ?");
$stmt->execute([$token]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Orden de servicio no encontrada.");
}

$order_data = $order['data'];
$correlativo = $order['correlativo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>Orden de Servicio <?php echo htmlspecialchars($correlativo); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  <?php
  $stmtFav = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'favicon'");
  $faviconRow = $stmtFav->fetch(PDO::FETCH_ASSOC);
  if ($faviconRow && !empty($faviconRow['setting_value'])): ?>
  <link rel="icon" href="<?php echo htmlspecialchars($faviconRow['setting_value']); ?>">
  <?php endif; ?>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    :root {
      --primary: #0f766e;
      --primary-light: #14b8a6;
      --bg-page: #f8fafb;
      --bg-card: #ffffff;
      --text-main: #1a2c3e;
      --text-secondary: #64748b;
      --text-muted: #94a3b8;
      --border: #e5e7eb;
      --border-light: #f1f5f9;
      --card-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.03);
      --card-radius: 20px;
    }

    body {
      background: var(--bg-page);
      font-family: 'Inter', sans-serif;
      color: var(--text-main);
      padding: 0;
      -webkit-font-smoothing: antialiased;
    }

    /* === Header Banner === */
    .public-header {
      position: sticky;
      top: 0;
      z-index: 100;
      background: var(--bg-card);
      border-bottom: 1px solid var(--border);
      padding: 14px 20px;
    }
    .public-header-inner {
      max-width: 800px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .public-header h1 {
      font-size: 1rem;
      font-weight: 800;
      color: var(--primary);
      letter-spacing: 0.5px;
    }
    .public-header .header-id {
      font-size: 0.7rem;
      color: var(--text-muted);
      margin-top: 2px;
    }
    .public-header .header-actions {
      display: flex;
      gap: 8px;
    }
    .public-header .header-actions button {
      background: none;
      border: none;
      font-size: 1.3rem;
      color: var(--primary);
      cursor: pointer;
      padding: 4px;
      border-radius: 8px;
      transition: background 0.2s;
    }
    .public-header .header-actions button:hover {
      background: var(--border-light);
    }

    /* === Container === */
    .app-container {
      max-width: 800px;
      margin: 0 auto;
      padding: 24px 16px 40px 16px;
    }

    /* === Section Card === */
    .section-card {
      background: var(--bg-card);
      border-radius: var(--card-radius);
      border: 1px solid var(--border);
      box-shadow: var(--card-shadow);
      margin-bottom: 24px;
      overflow: hidden;
    }
    .section-header {
      padding: 1rem 1.5rem;
      border-bottom: 1px solid var(--border-light);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .section-header h2 {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .section-header .badge {
      background: var(--primary);
      color: white;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 0.65rem;
      font-weight: 600;
    }
    .section-body {
      padding: 1.2rem 1.5rem;
    }

    /* === Client Info Card === */
    .client-card {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .client-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: linear-gradient(135deg, #dcfce7, #bbf7d0);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .client-avatar i {
      font-size: 1.4rem;
      color: var(--primary);
    }
    .client-info h3 {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--text-main);
    }
    .client-info p {
      font-size: 0.85rem;
      color: var(--text-secondary);
      margin-top: 2px;
    }
    .client-meta {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 12px;
      padding-top: 12px;
      border-top: 1px solid var(--border-light);
      font-size: 0.85rem;
      color: var(--text-secondary);
      justify-content: space-between;
      flex-wrap: wrap;
    }
    .client-meta-left {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .client-meta i {
      color: var(--text-muted);
    }
    .client-meta-networks {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }

    /* === Networks === */
    .networks-row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 8px;
    }
    .net-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 12px;
      border-radius: 20px;
      color: white;
      font-size: 0.75rem;
      font-weight: 600;
      text-decoration: none;
      transition: opacity 0.2s;
    }
    .net-pill:hover {
      opacity: 0.85;
    }

    /* === Process Card === */
    .process-header-bar {
      padding: 1rem 1.5rem;
      border-bottom: 1px solid var(--border-light);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .process-header-bar i {
      font-size: 1.2rem;
      color: var(--primary);
    }
    .process-header-bar span {
      font-size: 1rem;
      font-weight: 700;
      color: var(--text-main);
    }
    .process-rows {
      padding: 0.8rem 1.2rem 1rem;
    }
    .process-row {
      background: var(--bg-page);
      border-radius: 14px;
      padding: 1rem 1.2rem;
      margin-bottom: 10px;
      border: 1px solid var(--border-light);
    }
    .process-row:last-child {
      margin-bottom: 0;
    }
    .row-label {
      font-size: 0.6rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--text-muted);
      margin-bottom: 4px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .row-value {
      font-size: 0.88rem;
      color: var(--text-main);
      line-height: 1.5;
    }
    .row-grid {
      display: grid;
      grid-template-columns: 1fr 2fr;
      gap: 1rem;
    }

    /* === Budget & Obs === */
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .info-block label {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--text-muted);
      margin-bottom: 6px;
    }
    .info-block .info-value {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text-main);
      line-height: 1.6;
    }
    .info-block .info-value a {
      color: #3b82f6;
      text-decoration: none;
    }
    .info-block .info-value a:hover {
      text-decoration: underline;
    }

    /* === Custom Fields === */
    .custom-fields-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 16px;
    }
    .cf-item label {
      display: block;
      font-size: 0.6rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--text-muted);
      margin-bottom: 4px;
    }
    .cf-item .cf-value {
      font-size: 0.9rem;
      color: var(--text-main);
      word-break: break-all;
    }

    /* === Footer === */
    .public-footer {
      text-align: center;
      padding: 20px;
      font-size: 0.7rem;
      color: var(--text-muted);
    }

    /* === Responsive === */
    @media (max-width: 600px) {
      .info-grid { grid-template-columns: 1fr; }
      .row-grid { grid-template-columns: 1fr; }
      .client-card { flex-direction: column; text-align: center; }
      .client-meta { justify-content: center; }
    }

    @media print {
      .public-header { position: static; }
      body { background: white; }
    }
  </style>
</head>
<body>

<!-- Header -->
<div class="public-header">
  <div class="public-header-inner">
    <div>
      <h1>ORDEN DE SERVICIO</h1>
      <div class="header-id"><?php echo htmlspecialchars($correlativo); ?></div>
    </div>
    <div class="header-actions">
      <button onclick="window.print();" title="Descargar / Imprimir">
        <i class="ph ph-download-simple"></i>
      </button>
    </div>
  </div>
</div>

<div class="app-container">

  <!-- Client Info -->
  <div class="section-card">
    <div class="section-body">
      <div class="client-card">
        <div class="client-avatar">
          <i class="ph ph-user"></i>
        </div>
        <div class="client-info">
          <h3 id="ro_clienteName">-</h3>
          <p id="ro_marcaName">-</p>
        </div>
      </div>
      <div class="client-meta">
        <div class="client-meta-left">
          <i class="ph ph-calendar-blank"></i>
          <span id="ro_fechaInicio">-</span>
          <span style="color: var(--text-muted); margin: 0 4px;">→</span>
          <span id="ro_fechaFinal">-</span>
        </div>
        <div class="client-meta-networks" id="ro_redesManejar"></div>
      </div>
    </div>
  </div>

  <!-- Workflow -->
  <div id="procesosContainer"></div>

  <!-- Budget & Observations -->
  <div class="section-card" id="budgetObsCard" style="display: none;">
    <div class="section-header">
      <h2><i class="ph ph-clipboard-text"></i> Detalles adicionales</h2>
    </div>
    <div class="section-body">
      <div class="info-grid">
        <div class="info-block" id="budgetBlock" style="display: none;">
          <label><i class="ph ph-coins"></i> Presupuesto</label>
          <div class="info-value" id="ro_presupuestoAds">-</div>
        </div>
        <div class="info-block" id="obsBlock" style="display: none;">
          <label><i class="ph ph-note-pencil"></i> Observaciones</label>
          <div class="info-value" id="ro_observacionesGlobal">-</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Custom Fields -->
  <div id="customFieldsContainerWrapper"></div>

  <div class="public-footer">Documento confidencial · Generado automáticamente</div>
</div>

<script>
  const dbData = <?php echo $order_data ? $order_data : 'null'; ?>;
  
  function escapeHtml(str) {
    if(!str) return '';
    return str.replace(/[&<>]/g, function(m){
      if(m==='&') return '&amp;';
      if(m==='<') return '&lt;';
      if(m==='>') return '&gt;';
      return m;
    });
  }

  const REDES_COLORS = {
    'Facebook': { icon: 'ph-facebook-logo', color: '#1877F2' },
    'Instagram': { icon: 'ph-instagram-logo', color: '#E4405F' },
    'TikTok': { icon: 'ph-tiktok-logo', color: '#000000' },
    'VK': { icon: 'ph-users-three', color: '#4680C2' },
    'Google': { icon: 'ph-google-logo', color: '#DB4437' },
    'YouTube': { icon: 'ph-youtube-logo', color: '#FF0000' },
    'LinkedIn': { icon: 'ph-linkedin-logo', color: '#0A66C2' },
    'Web': { icon: 'ph-globe', color: '#577a9e' }
  };

  function renderData() {
    if(!dbData) return;

    // Client
    document.getElementById('ro_clienteName').textContent = dbData.cliente || '-';
    document.getElementById('ro_marcaName').textContent = dbData.marca || '-';
    document.getElementById('ro_fechaInicio').textContent = dbData.fechaInicio || '-';
    document.getElementById('ro_fechaFinal').textContent = dbData.fechaFinal || '-';

    // Networks
    const redesContainer = document.getElementById('ro_redesManejar');
    if(dbData.redes) {
      try {
        const parsed = JSON.parse(dbData.redes);
        if (Array.isArray(parsed) && parsed.length > 0) {
          parsed.forEach(red => {
            const conf = REDES_COLORS[red.id] || { icon: 'ph-share-network', color: '#577a9e' };
            const el = document.createElement(red.url ? 'a' : 'span');
            if (red.url) { el.href = red.url; el.target = '_blank'; }
            el.style.cssText = 'display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; text-decoration: none; transition: transform 0.2s;';
            el.style.background = conf.color;
            el.title = red.id;
            el.onmouseover = function() { this.style.transform = 'scale(1.1)'; };
            el.onmouseout = function() { this.style.transform = 'scale(1)'; };
            el.innerHTML = `<i class="ph ${conf.icon}" style="color: #fff !important; font-size: 0.9rem;"></i>`;
            redesContainer.appendChild(el);
          });
        }
      } catch(e) {
        redesContainer.textContent = dbData.redes;
      }
    }

    // Budget & Observations
    const hasBudget = dbData.presupuesto && dbData.presupuesto.trim() !== '';
    const hasObs = dbData.observaciones && dbData.observaciones.trim() !== '' && dbData.observaciones !== '<p><br></p>';
    
    if (hasBudget || hasObs) {
      document.getElementById('budgetObsCard').style.display = '';
      if (hasBudget) {
        document.getElementById('budgetBlock').style.display = '';
        document.getElementById('ro_presupuestoAds').textContent = dbData.presupuesto;
      }
      if (hasObs) {
        document.getElementById('obsBlock').style.display = '';
        document.getElementById('ro_observacionesGlobal').innerHTML = dbData.observaciones;
      }
    }

    // Custom Fields
    if(dbData.customFields && Array.isArray(dbData.customFields) && dbData.customFields.length > 0) {
      const wrapper = document.getElementById('customFieldsContainerWrapper');
      let html = '<div class="section-card"><div class="section-header"><h2><i class="ph ph-faders"></i> Campos personalizados</h2></div><div class="section-body"><div class="custom-fields-grid">';
      dbData.customFields.forEach(cf => {
        let val = escapeHtml(cf.value);
        if (val.startsWith('http')) {
          val = `<a href="${val}" target="_blank">${val}</a>`;
        }
        html += `<div class="cf-item"><label>${escapeHtml(cf.name)}</label><div class="cf-value">${val}</div></div>`;
      });
      html += '</div></div></div>';
      wrapper.innerHTML = html;
    }

    // Processes / Workflow
    const container = document.getElementById('procesosContainer');
    if(dbData.procesos && Array.isArray(dbData.procesos)) {
      dbData.procesos.forEach(proc => {
        if(!proc.rows || proc.rows.length === 0) return;

        const card = document.createElement('div');
        card.className = 'section-card';

        // Icon mapping
        let iconClass = proc.icono || 'ph-clipboard-text';
        if (!iconClass.startsWith('ph-')) {
          iconClass = 'ph-clipboard-text';
        }

        card.innerHTML = `
          <div class="process-header-bar">
            <i class="ph ${iconClass}"></i>
            <span>${escapeHtml(proc.nombre)}</span>
          </div>
          <div class="process-rows" id="proc-rows-${escapeHtml(proc.id)}"></div>
        `;

        container.appendChild(card);

        const rowsEl = card.querySelector('.process-rows');
        proc.rows.forEach(row => {
          const rowDiv = document.createElement('div');
          rowDiv.className = 'process-row';
          rowDiv.innerHTML = `
            <div class="row-grid">
              <div>
                <div class="row-label"><i class="ph ph-user-circle"></i> Encargado</div>
                <div class="row-value">${escapeHtml(row.encargado) || '<span style="color: var(--text-muted);">Sin asignar</span>'}</div>
              </div>
              <div>
                <div class="row-label"><i class="ph ph-clipboard-text"></i> Descripción</div>
                <div class="row-value">${row.descripcion || '<span style="color: var(--text-muted);">-</span>'}</div>
              </div>
            </div>
          `;
          rowsEl.appendChild(rowDiv);
        });
      });
    }
  }

  window.addEventListener('DOMContentLoaded', renderData);
</script>
</body>
</html>
