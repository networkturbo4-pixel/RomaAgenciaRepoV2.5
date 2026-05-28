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
  <title>Orden de Servicio <?php echo htmlspecialchars($correlativo); ?> | FlowWorks</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { background: linear-gradient(145deg, #eef2f7 0%, #e0e7ef 100%); font-family: 'Inter', sans-serif; color: #1a2c3e; padding-top: 80px; }
    
    .floating-toolbar { position: fixed; top: 0; left: 0; right: 0; background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(16px); z-index: 1000; padding: 0.8rem 2rem; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08), 0 1px 0 rgba(0,0,0,0.02); border-bottom: 1px solid rgba(100, 130, 160, 0.2); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
    .logo-area { display: flex; align-items: center; gap: 0.75rem; }
    .logo-area i { font-size: 1.8rem; color: #1f6392; background: white; padding: 8px; border-radius: 18px; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
    .logo-area h2 { font-size: 1.3rem; font-weight: 700; background: linear-gradient(135deg, #1f4e7a, #0f2c44); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .status-badge { background: #dcfce7; color: #166534; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; border: 1px solid #bbf7d0; display: inline-flex; align-items: center; gap: 0.25rem; }

    .app-container { max-width: 1400px; margin: 0 auto; padding: 0 1.5rem 2rem 1.5rem; }

    .info-cards { display: flex; flex-wrap: wrap; gap: 1.2rem; margin-bottom: 2rem; }
    .card-glass { background: white; border-radius: 1.5rem; padding: 1rem 1.6rem; flex: 1 1 200px; box-shadow: 0 5px 14px rgba(0,0,0,0.03), 0 0 0 1px rgba(0,0,0,0.02); }
    .card-glass label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; color: #6c86a0; display: block; margin-bottom: 0.3rem; }
    .card-glass .readonly-value { font-weight: 600; font-size: 0.95rem; font-family: 'Inter', monospace; color: #1a2c3e; min-height: 1.5rem; }

    .section-title { display: flex; align-items: baseline; justify-content: space-between; margin: 1.8rem 0 1.2rem 0; flex-wrap: wrap; }
    .section-title h2 { font-size: 1.4rem; font-weight: 600; color: #1e405e; }

    .process-card { background: white; border-radius: 1.3rem; margin-bottom: 1.4rem; box-shadow: 0 8px 18px rgba(0,0,0,0.05); border: 1px solid #eef2f8; overflow: hidden; }
    .process-header { background: #f9fbfe; padding: 1rem 1.6rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; border-bottom: 1px solid #e9edf2; }
    .process-name { display: flex; align-items: center; gap: 0.8rem; font-weight: 700; font-size: 1.1rem; color: #1f6392; }
    .rows-container { padding: 1rem 1.2rem 1.4rem 1.2rem; background: white; }
    .row-item { background: #ffffff; border-radius: 1rem; padding: 0.9rem 1rem; margin-bottom: 0.8rem; display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03), 0 0 0 1px #eff3f8; }
    .row-field { flex: 2 1 180px; min-width: 150px; }
    .row-field label { display: block; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; color: #86a0bc; margin-bottom: 0.2rem; }
    .readonly-box { width: 100%; border: 1px solid transparent; background: transparent; padding: 0; font-size: 0.85rem; font-family: 'Inter', sans-serif; color: #1a2c3e; }
    .budget-obs { background: #ffffffdb; backdrop-filter: blur(4px); border-radius: 1.6rem; padding: 1.2rem 1.8rem; margin-top: 2rem; display: flex; flex-wrap: wrap; gap: 2rem; border: 1px solid #eef2f8; }
    .budget-box, .obs-box { flex: 1; }
    .budget-box h4, .obs-box h4 { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #577a9e; margin-bottom: 0.6rem; }
    .budget-box .readonly-value, .obs-box .readonly-value { width: 100%; font-family: 'Inter', sans-serif; color: #1a2c3e; }
    footer { font-size: 0.7rem; text-align: center; margin-top: 2rem; color: #7892ac; }
    @media (max-width: 700px) { body { padding-top: 110px; } .floating-toolbar { flex-direction: column; align-items: stretch; padding: 0.8rem 1rem; } .row-item { flex-direction: column; align-items: stretch; } }
  </style>
</head>
<body>
<div class="floating-toolbar">
  <div class="logo-area">
    <i class="fas fa-network-wired"></i>
    <h2>OS: <?php echo htmlspecialchars($correlativo); ?></h2>
  </div>
  <div class="status-badge">
    <i class="fas fa-lock"></i> Vista de solo lectura
  </div>
</div>

<div class="app-container">
  <div class="info-cards">
    <div class="card-glass"><label><i class="fas fa-user"></i> CLIENTE</label><div class="readonly-value" id="ro_clienteName"></div></div>
    <div class="card-glass"><label><i class="fas fa-tag"></i> MARCA</label><div class="readonly-value" id="ro_marcaName"></div></div>
    <div class="card-glass"><label><i class="fas fa-globe"></i> REDES A MANEJAR</label><div class="readonly-value" id="ro_redesManejar"></div></div>
    <div class="card-glass"><label><i class="fas fa-play-circle"></i> INICIO</label><div class="readonly-value" id="ro_fechaInicio"></div></div>
    <div class="card-glass"><label><i class="fas fa-stop-circle"></i> FINAL</label><div class="readonly-value" id="ro_fechaFinal"></div></div>
  </div>

  <div class="section-title">
    <h2><i class="fas fa-tasks"></i> Flujo de trabajo</h2>
  </div>
  <div id="procesosContainer"></div>

  <div class="budget-obs">
    <div class="budget-box">
      <h4><i class="fas fa-coins"></i> PRESUPUESTO ADS + CUENTA COMERCIAL</h4>
      <div class="readonly-value" id="ro_presupuestoAds"></div>
    </div>
    <div class="obs-box">
      <h4><i class="fas fa-pen-fancy"></i> OBSERVACIONES ADICIONALES</h4>
      <div class="readonly-value" style="white-space: pre-wrap;" id="ro_observacionesGlobal"></div>
    </div>
  </div>
  <footer>Generado por FlowWorks · Documento Confidencial</footer>
</div>

<script>
  const dbData = <?php echo $order_data ? $order_data : 'null'; ?>;
  function escapeHtml(str) { if(!str) return ''; return str.replace(/[&<>]/g, function(m){ if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }

  function renderData() {
      if(!dbData) return;

      document.getElementById('ro_clienteName').textContent = dbData.cliente || '-';
      document.getElementById('ro_marcaName').textContent = dbData.marca || '-';
      document.getElementById('ro_redesManejar').textContent = dbData.redes || '-';
      document.getElementById('ro_fechaInicio').textContent = dbData.fechaInicio || '-';
      document.getElementById('ro_fechaFinal').textContent = dbData.fechaFinal || '-';
      document.getElementById('ro_presupuestoAds').textContent = dbData.presupuesto || '-';
      document.getElementById('ro_observacionesGlobal').textContent = dbData.observaciones || '-';

      const container = document.getElementById('procesosContainer');
      if(dbData.procesos && Array.isArray(dbData.procesos)) {
          dbData.procesos.forEach(proc => {
              // Si no tiene filas, no lo mostramos, o lo mostramos vacío
              if(!proc.rows || proc.rows.length === 0) return;

              const card = document.createElement('div');
              card.className = 'process-card';
              
              // Map ph icons back to fa if needed or use fa classes
              let iconoClass = proc.icono;
              if (iconoClass && iconoClass.startsWith('ph-')) {
                  // Fallback map
                  const iconMap = {
                      'ph-magnifying-glass': 'fa-search',
                      'ph-git-branch': 'fa-code-branch',
                      'ph-chart-line-up': 'fa-chart-line',
                      'ph-eye': 'fa-eye',
                      'ph-video-camera': 'fa-video'
                  };
                  iconoClass = 'fas ' + (iconMap[iconoClass] || 'fa-check');
              }

              const header = document.createElement('div');
              header.className = 'process-header';
              header.innerHTML = `
                <div class="process-name">
                  <i class="${iconoClass}"></i>
                  <span>${escapeHtml(proc.nombre)}</span>
                </div>
              `;
              
              const rowsContainer = document.createElement('div');
              rowsContainer.className = 'rows-container';

              proc.rows.forEach(row => {
                  const rowDiv = document.createElement('div');
                  rowDiv.className = 'row-item';
                  rowDiv.innerHTML = `
                    <div class="row-field">
                      <label><i class="fas fa-user-check"></i> ENCARGADO DEL ÁREA</label>
                      <div class="readonly-box">${escapeHtml(row.encargado)}</div>
                    </div>
                    <div class="row-field">
                      <label><i class="fas fa-clipboard-list"></i> DESCRIPCIÓN DEL TRABAJO</label>
                      <div class="readonly-box" style="white-space: pre-wrap;">${escapeHtml(row.descripcion)}</div>
                    </div>
                  `;
                  rowsContainer.appendChild(rowDiv);
              });

              card.appendChild(header);
              card.appendChild(rowsContainer);
              container.appendChild(card);
          });
      }
  }

  window.addEventListener('DOMContentLoaded', renderData);
</script>
</body>
</html>
