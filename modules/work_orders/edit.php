<?php
// modules/work_orders/edit.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

$id = $_GET['id'] ?? '';
$order_data = null;
$correlativo = 'NUEVO';
$public_token = '';

if (!empty($id)) {
    $stmt = $db->prepare("SELECT * FROM work_orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order) {
        $correlativo = $order['correlativo'];
        $order_data = $order['data'];
        $public_token = $order['public_token'];
    }
}

$stmtClients = $db->query("SELECT id, name FROM clients ORDER BY name ASC");
$clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

// Fetch brands for selection if needed (though the user format has free text, we can populate a datalist)
$stmtBrands = $db->query("SELECT name FROM client_brands ORDER BY name ASC");
$brands = $stmtBrands->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees for the 'Encargado del Área' dropdown
$stmtEmployees = $db->query("SELECT id, name FROM employees ORDER BY name ASC");
$employees = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>
<!-- User's Custom CSS integrated into the content wrapper -->
<style>
    .ot-container {
        font-family: 'Inter', sans-serif;
        color: #1a2c3e;
        padding-bottom: 2rem;
    }

    .floating-toolbar {
        position: sticky;
        top: 0; /* En escritorio el topbar hace scroll, así que se pega arriba */
        background: #ffffff;
        z-index: 90;
        padding: 1rem 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        border-radius: 0 0 1rem 1rem;
        border-bottom: 1px solid rgba(100, 130, 160, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .logo-area {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .logo-area i {
      font-size: 1.5rem;
      color: #1f6392;
      background: white;
      padding: 6px;
      border-radius: 12px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    }
    .logo-area h2 {
      font-size: 1.2rem;
      font-weight: 700;
      background: linear-gradient(135deg, #1f4e7a, #0f2c44);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      margin: 0;
    }
    .button-group {
      display: flex;
      gap: 0.9rem;
      align-items: center;
      flex-wrap: wrap;
    }
    .btn-float {
      background: white;
      border: none;
      padding: 0.55rem 1.2rem;
      border-radius: 2.5rem;
      font-weight: 600;
      font-size: 0.85rem;
      font-family: 'Inter', sans-serif;
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      cursor: pointer;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
      transition: all 0.2s ease;
      color: #1f4e6e;
      border: 1px solid rgba(31, 99, 146, 0.2);
    }
    .btn-float i {
      font-size: 1rem;
    }
    .btn-float:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 18px rgba(0,0,0,0.1);
      background: #f6faff;
      border-color: #1f6392;
    }
    .btn-primary-float {
      background: #1f6392;
      color: white;
      border: none;
      box-shadow: 0 4px 10px rgba(31, 99, 146, 0.3);
    }
    .btn-primary-float:hover {
      background: #0e4f77;
      transform: translateY(-2px);
      color: white;
    }
    
    .btn-back {
        background: #f1f5f9;
        color: #475569;
        border-color: #cbd5e1;
    }

    .info-cards {
      display: flex;
      flex-wrap: wrap;
      gap: 1.2rem;
      margin-bottom: 2rem;
    }
    .card-glass {
      background: white;
      border-radius: 1rem;
      padding: 1rem 1.2rem;
      flex: 1 1 200px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.03), 0 0 0 1px rgba(0,0,0,0.02);
      border: 1px solid #eef2f8;
    }
    .card-glass label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-weight: 600;
      color: #6c86a0;
      display: block;
      margin-bottom: 0.3rem;
    }
    .card-glass input, .card-glass select {
      font-weight: 600;
      font-size: 0.95rem;
      border: none;
      background: transparent;
      width: 100%;
      outline: none;
      font-family: 'Inter', sans-serif;
      color: #1a2c3e;
      padding: 0;
    }

    .section-title {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      margin: 1.8rem 0 1.2rem 0;
      flex-wrap: wrap;
    }
    .section-title h2 {
      font-size: 1.4rem;
      font-weight: 600;
      color: #1e405e;
      margin: 0;
    }

    .process-card {
      background: white;
      border-radius: 1rem;
      margin-bottom: 1.4rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.03);
      border: 1px solid #eef2f8;
      overflow: hidden;
    }
    .process-header {
      background: #f9fbfe;
      padding: 1rem 1.6rem;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #e9edf2;
    }
    .process-name {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      font-weight: 700;
      font-size: 1.1rem;
      color: #1f6392;
    }
    .actions-buttons {
      display: flex;
      gap: 0.6rem;
    }
    .btn-icon-ot {
      background: transparent;
      border: none;
      font-size: 1rem;
      cursor: pointer;
      padding: 0.4rem 0.6rem;
      border-radius: 2rem;
      color: #6c86a0;
    }
    .btn-icon-ot:hover {
      background: #eef2fa;
      color: #1f6392;
    }
    .danger:hover {
      color: #e05a56;
      background: #fee;
    }
    .rows-container {
      padding: 1rem 1.2rem 1.4rem 1.2rem;
      background: white;
    }
    .row-item {
      background: #ffffff;
      border-radius: 0.8rem;
      padding: 0.9rem 1rem;
      margin-bottom: 0.8rem;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 1rem;
      box-shadow: 0 1px 2px rgba(0,0,0,0.03), 0 0 0 1px #eff3f8;
    }
    .row-field {
      flex: 2 1 180px;
      min-width: 150px;
    }
    .row-field label {
      display: block;
      font-size: 0.65rem;
      font-weight: 600;
      text-transform: uppercase;
      color: #86a0bc;
      margin-bottom: 0.2rem;
    }
    .row-field input, .row-field textarea {
      width: 100%;
      border: 1px solid #e2e8f0;
      background: #fefefe;
      border-radius: 0.5rem;
      padding: 0.6rem 0.8rem;
      font-size: 0.85rem;
      font-family: 'Inter', sans-serif;
    }
    .row-field input:focus, .row-field textarea:focus {
      border-color: #67b3e0;
      outline: none;
      box-shadow: 0 0 0 2px rgba(79, 155, 212, 0.2);
    }
    .remove-row-btn {
      background: none;
      border: none;
      font-size: 1.2rem;
      color: #b9c6d4;
      cursor: pointer;
      padding: 0.5rem;
      border-radius: 2rem;
    }
    .remove-row-btn:hover {
      color: #e05a56;
    }
    .add-row-btn {
      margin-top: 0.4rem;
      background: #f0f4fa;
      border: 1px dashed #9bb7d0;
      border-radius: 2rem;
      padding: 0.5rem 1rem;
      font-size: 0.75rem;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      cursor: pointer;
      color: #2c6288;
    }
    .budget-obs {
      background: white;
      border-radius: 1rem;
      padding: 1.5rem;
      margin-top: 2rem;
      display: flex;
      flex-wrap: wrap;
      gap: 2rem;
      border: 1px solid #eef2f8;
    }
    .budget-box, .obs-box {
      flex: 1;
      min-width: 250px;
    }
    .budget-box h4, .obs-box h4 {
      font-size: 0.8rem;
      font-weight: 700;
      text-transform: uppercase;
      color: #577a9e;
      margin-bottom: 0.6rem;
      margin-top: 0;
    }
    .budget-box input, .obs-box textarea {
      width: 100%;
      border-radius: 0.5rem;
      border: 1px solid #dfe6ef;
      padding: 0.8rem 1rem;
      font-family: 'Inter', sans-serif;
      background: #fefefe;
    }
    @media (max-width: 768px) {
      .floating-toolbar { 
          position: fixed;
          top: auto;
          bottom: 0;
          left: 0;
          right: 0;
          margin: 0;
          padding: 1rem 1.5rem !important;
          border-radius: 1.2rem 1.2rem 0 0;
          box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
          z-index: 1000;
          flex-direction: row;
      }
      .logo-area h2 {
          font-size: 1.1rem;
      }
      .button-group {
          gap: 0.5rem;
      }
      .btn-float {
          padding: 0.5rem 0.8rem;
          font-size: 0.8rem;
      }
      .ot-container {
          padding-bottom: 90px;
      }
    }
    @media (max-width: 500px) {
      .logo-area h2 { display: none; } /* Ocultar texto OS en pantallas muy chicas para ahorrar espacio */
    }
    /* Dark Mode Adaptations */
    [data-theme="dark"] .floating-toolbar {
        background: var(--bg-surface);
        border-color: var(--border-color);
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }
    [data-theme="dark"] .card-glass,
    [data-theme="dark"] .process-card,
    [data-theme="dark"] .budget-obs,
    [data-theme="dark"] .rows-container,
    [data-theme="dark"] .row-item {
        background: var(--bg-surface);
        border-color: var(--border-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    [data-theme="dark"] .logo-area i {
        background: var(--bg-body);
        color: var(--color-text);
    }
    [data-theme="dark"] .logo-area h2 {
        background: none;
        -webkit-text-fill-color: var(--color-title);
        color: var(--color-title);
    }
    [data-theme="dark"] .ot-container {
        color: var(--color-text);
    }
    [data-theme="dark"] .card-glass input, 
    [data-theme="dark"] .card-glass select,
    [data-theme="dark"] .budget-box input, 
    [data-theme="dark"] .obs-box textarea,
    [data-theme="dark"] .row-field input, 
    [data-theme="dark"] .row-field textarea {
        color: var(--color-text);
        background: var(--bg-body);
        border-color: var(--border-color);
    }
    [data-theme="dark"] .card-glass input:focus, 
    [data-theme="dark"] .card-glass select:focus,
    [data-theme="dark"] .budget-box input:focus, 
    [data-theme="dark"] .obs-box textarea:focus,
    [data-theme="dark"] .row-field input:focus, 
    [data-theme="dark"] .row-field textarea:focus {
        border-color: var(--primary-color);
        background: var(--bg-surface);
    }
    [data-theme="dark"] .process-header {
        background: var(--bg-body);
        border-bottom-color: var(--border-color);
    }
    [data-theme="dark"] .process-name {
        color: var(--color-title);
    }
    [data-theme="dark"] .btn-float {
        background: var(--bg-body);
        color: var(--color-text);
        border-color: var(--border-color);
    }
    [data-theme="dark"] .btn-float:hover {
        background: var(--bg-surface);
        border-color: var(--primary-color);
    }
    [data-theme="dark"] .btn-primary-float {
        background: var(--primary-color);
        color: white;
    }
    [data-theme="dark"] .btn-back {
        background: var(--bg-surface);
    }
    [data-theme="dark"] .section-title h2 {
        color: var(--color-title);
    }
    [data-theme="dark"] .add-row-btn {
        background: var(--bg-body);
        border-color: var(--border-color);
        color: var(--color-text);
    }
    [data-theme="dark"] .card-glass label,
    [data-theme="dark"] .row-field label,
    [data-theme="dark"] .budget-box h4, 
    [data-theme="dark"] .obs-box h4 {
        color: var(--text-muted);
    }
    [data-theme="dark"] .card-glass label input[type="checkbox"] {
        accent-color: var(--primary-color);
    }
</style>

<div class="ot-container">
    <div class="floating-toolbar">
        <div class="logo-area">
            <i class="ph ph-clipboard-text"></i>
            <h2>OS: <?php echo htmlspecialchars($correlativo); ?></h2>
        </div>
        <div class="button-group">
            <a href="index.php?module=work_orders&action=index" class="btn-float btn-back"><i class="ph ph-arrow-left"></i> Volver</a>
            <button id="saveSnapshotBtn" class="btn-float btn-primary-float"><i class="ph ph-floppy-disk"></i> Guardar</button>
        </div>
    </div>

    <!-- Cabecera info -->
    <div class="info-cards">
        <div class="card-glass">
            <label><i class="ph ph-buildings"></i> CLIENTE</label>
            <select id="clienteName">
                <option value="">Seleccionar Cliente...</option>
                <?php foreach($clients as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['name']); ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="card-glass">
            <label><i class="ph ph-tag"></i> MARCA</label>
            <input type="text" id="marcaName" list="brandsList" placeholder="Marca" value="">
            <datalist id="brandsList">
                <?php foreach($brands as $b): ?>
                    <option value="<?php echo htmlspecialchars($b['name']); ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="card-glass" style="flex: 1 1 100%;">
            <label><i class="ph ph-globe"></i> REDES A MANEJAR</label>
            <div id="redesContainer" style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                <?php
                $redes = ['Facebook', 'Instagram', 'TikTok', 'VK', 'Google', 'YouTube', 'LinkedIn'];
                foreach($redes as $red): ?>
                    <label style="display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.4rem 0.8rem; background: #f1f5f9; border-radius: 20px; font-size: 0.8rem; font-weight: 500; cursor: pointer; color: #475569; text-transform: none; letter-spacing: 0;">
                        <input type="checkbox" value="<?php echo $red; ?>" class="redes-cb" style="width: auto; margin: 0;"> <?php echo $red; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card-glass">
            <label><i class="ph ph-play-circle"></i> INICIO</label>
            <input type="date" id="fechaInicio" value="">
        </div>
        <div class="card-glass">
            <label><i class="ph ph-stop-circle"></i> FINAL</label>
            <input type="date" id="fechaFinal" value="">
        </div>
    </div>

    <div class="section-title">
        <h2><i class="ph ph-kanban"></i> Flujo de trabajo</h2>
        <span style="color: #6c86a0; font-size: 0.85rem;"><i class="ph ph-info"></i> Agrega o elimina filas por proceso</span>
    </div>
    
    <div id="procesosContainer"></div>

    <div class="budget-obs">
        <div class="budget-box">
            <h4><i class="ph ph-currency-circle-dollar"></i> PRESUPUESTO ADS + CUENTA COMERCIAL</h4>
            <input type="text" id="presupuestoAds" placeholder="$ / S/ presupuesto" value="">
            <small style="color: #6c86a0; display: block; margin-top: 0.5rem;">*Cuenta comercial ya incluida</small>
        </div>
        <div class="obs-box">
            <h4><i class="ph ph-text-align-left"></i> OBSERVACIONES ADICIONALES</h4>
            <textarea id="observacionesGlobal" placeholder="Detalles adicionales" rows="3"></textarea>
        </div>
    </div>
</div>

<!-- Modal para Compartir -->
<div class="modal-overlay" id="shareModal">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <div class="modal-header" style="justify-content: center; position: relative;">
            <h2 class="modal-title">Compartir Orden</h2>
            <button class="btn-icon close-modal" onclick="document.getElementById('shareModal').classList.remove('active')" style="position: absolute; right: 0;"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="padding: 2rem 1rem;">
            <i class="ph ph-link" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
            <p style="color: var(--color-text); margin-bottom: 1rem;">Copia este enlace para compartir la vista pública de esta orden de servicio con tu cliente.</p>
            <input type="text" id="shareLinkInput" class="form-control" readonly style="text-align: center; margin-bottom: 1rem;">
            <button class="btn btn-primary" onclick="copyShareLink()" style="width: 100%;">
                <i class="ph ph-copy"></i> Copiar Enlace
            </button>
        </div>
    </div>
</div>

<script>
  const PROCESSOS_BASE = [
    { id: "proc1", nombre: "PROCESO 01: AUDITORIA", icono: "ph-magnifying-glass", rowsDefault: [{encargado: "", descripcion: ""}] },
    { id: "proc2", nombre: "PROCESO 02: DESARROLLO", icono: "ph-git-branch", rowsDefault: [{encargado: "", descripcion: ""}] },
    { id: "proc3", nombre: "PROCESO 03: MEDIOS", icono: "ph-chart-line-up", rowsDefault: [{encargado: "", descripcion: ""}] },
    { id: "proc4", nombre: "PROCESO 04: SEGUIMIENTO", icono: "ph-eye", rowsDefault: [{encargado: "", descripcion: ""}] },
    { id: "proc5", nombre: "PROCESO 05: AUDIOVISUAL", icono: "ph-video-camera", rowsDefault: [{encargado: "", descripcion: ""}] }
  ];

  let procesosData = [];
  const dbData = <?php echo $order_data ? $order_data : 'null'; ?>;
  const woId = '<?php echo $id; ?>';

  // Helper toast integrado con la app
  function showAppToast(message) {
      alert(message); // Podríamos usar un componente toast custom del sistema si existe
  }

  function captureFullState() {
    return {
      procesos: procesosData,
      cliente: document.getElementById('clienteName').value,
      marca: document.getElementById('marcaName').value,
      redes: Array.from(document.querySelectorAll('.redes-cb:checked')).map(cb => cb.value).join(', '),
      fechaInicio: document.getElementById('fechaInicio').value,
      fechaFinal: document.getElementById('fechaFinal').value,
      presupuesto: document.getElementById('presupuestoAds').value,
      observaciones: document.getElementById('observacionesGlobal').value
    };
  }

  // Render completo
  function renderProcesos() {
    const container = document.getElementById('procesosContainer');
    if(!container) return;
    container.innerHTML = '';
    procesosData.forEach((proc) => {
      const card = document.createElement('div');
      card.className = 'process-card';
      card.dataset.procId = proc.id;
      const header = document.createElement('div');
      header.className = 'process-header';
      header.innerHTML = `
        <div class="process-name">
          <i class="${proc.icono}"></i>
          <span>${proc.nombre}</span>
        </div>
        <div class="actions-buttons">
          <button type="button" class="btn-icon-ot add-main-row" data-id="${proc.id}" title="Agregar fila"><i class="ph ph-plus-circle"></i> Fila</button>
          <button type="button" class="btn-icon-ot danger delete-all-rows" data-id="${proc.id}" title="Eliminar todas las filas"><i class="ph ph-trash"></i></button>
        </div>
      `;
      const rowsContainer = document.createElement('div');
      rowsContainer.className = 'rows-container';
      
      function refreshRowsContainer() {
        rowsContainer.innerHTML = '';
        const currentProcess = procesosData.find(p => p.id === proc.id);
        if(!currentProcess) return;
        currentProcess.rows.forEach((row, rowIndex) => {
          const rowDiv = document.createElement('div');
          rowDiv.className = 'row-item';
          
          let selectOptions = `<option value="">Responsable</option>`;
          const EMPLEADOS = <?php echo json_encode($employees); ?>;
          let found = false;
          EMPLEADOS.forEach(emp => {
              const isSelected = row.encargado === emp.name ? 'selected' : '';
              if (isSelected) found = true;
              selectOptions += `<option value="${escapeHtml(emp.name)}" ${isSelected}>${escapeHtml(emp.name)}</option>`;
          });
          if (row.encargado && !found) {
              selectOptions += `<option value="${escapeHtml(row.encargado)}" selected>${escapeHtml(row.encargado)} (Antiguo)</option>`;
          }

          rowDiv.innerHTML = `
            <div class="row-field">
              <label><i class="ph ph-user"></i> ENCARGADO DEL ÁREA</label>
              <select class="encargado-input">
                ${selectOptions}
              </select>
            </div>
            <div class="row-field">
              <label><i class="ph ph-text-align-left"></i> DESCRIPCIÓN DEL TRABAJO</label>
              <textarea rows="1" class="descripcion-input" placeholder="Detalle de tareas...">${escapeHtml(row.descripcion)}</textarea>
            </div>
            <div class="row-actions">
              <button type="button" class="remove-row-btn" title="Eliminar fila"><i class="ph ph-x-circle"></i></button>
            </div>
          `;
          const encInput = rowDiv.querySelector('.encargado-input');
          const descText = rowDiv.querySelector('.descripcion-input');
          const removeBtn = rowDiv.querySelector('.remove-row-btn');
          encInput.addEventListener('input', (e) => { currentProcess.rows[rowIndex].encargado = e.target.value; });
          descText.addEventListener('input', (e) => { currentProcess.rows[rowIndex].descripcion = e.target.value; });
          removeBtn.addEventListener('click', () => {
            currentProcess.rows.splice(rowIndex, 1);
            refreshRowsContainer();
            renderProcesos();
          });
          rowsContainer.appendChild(rowDiv);
        });
        const addLocalBtn = document.createElement('div');
        addLocalBtn.className = 'add-row-btn';
        addLocalBtn.innerHTML = '<i class="ph ph-plus"></i> Añadir nueva fila';
        addLocalBtn.addEventListener('click', () => {
          currentProcess.rows.push({ encargado: "", descripcion: "" });
          refreshRowsContainer();
          renderProcesos();
        });
        rowsContainer.appendChild(addLocalBtn);
      }
      refreshRowsContainer();
      card.appendChild(header);
      card.appendChild(rowsContainer);
      container.appendChild(card);
    });
    attachGlobalProcessEvents();
  }

  function attachGlobalProcessEvents() {
    document.querySelectorAll('.delete-all-rows').forEach(btn => {
      btn.removeEventListener('click', deleteAllHandler);
      btn.addEventListener('click', deleteAllHandler);
    });
    document.querySelectorAll('.add-main-row').forEach(btn => {
      btn.removeEventListener('click', addMainHandler);
      btn.addEventListener('click', addMainHandler);
    });
  }
  function deleteAllHandler(e) {
    const procId = e.currentTarget.dataset.id;
    const proceso = procesosData.find(p => p.id === procId);
    if(proceso && confirm(`Eliminar todas las filas en "${proceso.nombre}"?`)) {
      proceso.rows = [];
      renderProcesos();
    }
  }
  function addMainHandler(e) {
    const procId = e.currentTarget.dataset.id;
    const proceso = procesosData.find(p => p.id === procId);
    if(proceso) {
      proceso.rows.push({ encargado: "", descripcion: "" });
      renderProcesos();
    }
  }
  function escapeHtml(str) { if(!str) return ''; return str.replace(/[&<>]/g, function(m){ if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }

  function initData() {
    if(dbData) {
      const state = dbData;
      if(state.procesos) procesosData = state.procesos;
      if(state.cliente) document.getElementById('clienteName').value = state.cliente;
      if(state.marca) document.getElementById('marcaName').value = state.marca;
      if(state.redes) {
          const arr = state.redes.split(',').map(s => s.trim());
          document.querySelectorAll('.redes-cb').forEach(cb => {
              cb.checked = arr.includes(cb.value);
          });
      }
      if(state.fechaInicio) document.getElementById('fechaInicio').value = state.fechaInicio;
      if(state.fechaFinal) document.getElementById('fechaFinal').value = state.fechaFinal;
      if(state.presupuesto) document.getElementById('presupuestoAds').value = state.presupuesto;
      if(state.observaciones) document.getElementById('observacionesGlobal').value = state.observaciones;
    } else {
      procesosData = PROCESSOS_BASE.map(p => ({ id: p.id, nombre: p.nombre, icono: p.icono, rows: p.rowsDefault.map(r => ({ encargado: r.encargado, descripcion: r.descripcion })) }));
      document.getElementById('fechaInicio').value = new Date().toISOString().split('T')[0];
    }
    
    // Ensure all processes exist (backward compatibility)
    for(let base of PROCESSOS_BASE) {
      const existing = procesosData.find(p => p.id === base.id);
      if(!existing) procesosData.push({ id: base.id, nombre: base.nombre, icono: base.icono, rows: [{ encargado: "", descripcion: "" }] });
      else { if(!existing.icono) existing.icono = base.icono; if(!existing.nombre) existing.nombre = base.nombre; }
    }
    
    renderProcesos();
  }

  // Guardar en la base de datos
  async function handleSaveDB() {
    const btn = document.getElementById('saveSnapshotBtn');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
    btn.disabled = true;

    const state = captureFullState();
    
    const formData = new FormData();
    formData.append('work_order_id', woId);
    formData.append('marca', state.marca || state.cliente);
    formData.append('data', JSON.stringify(state));

    try {
        const response = await fetch('index.php?module=work_orders&action=ajax_save_order', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            // alert('Orden guardada correctamente.');
            if (result.redirect_url) {
                window.location.href = result.redirect_url;
            } else {
                btn.innerHTML = '<i class="ph ph-check"></i> Guardado';
                setTimeout(() => { btn.innerHTML = originalHtml; btn.disabled = false; }, 2000);
            }
        } else {
            alert(result.message || 'Error al guardar.');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión.');
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }
  }

  function shareOrder(token) {
      let currentUrl = window.location.href;
      let baseUrl = currentUrl.split('?')[0];
      const url = baseUrl + '?module=work_orders&action=public&token=' + token;
      document.getElementById('shareLinkInput').value = url;
      document.getElementById('shareModal').classList.add('active');
  }

  function copyShareLink() {
      const input = document.getElementById('shareLinkInput');
      input.select();
      document.execCommand('copy');
      alert('¡Enlace copiado al portapapeles!');
  }

  document.getElementById('saveSnapshotBtn')?.addEventListener('click', handleSaveDB);

  window.addEventListener('DOMContentLoaded', () => {
    initData();
  });
</script>

<?php require_once 'includes/footer.php'; ?>
