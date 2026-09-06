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

// Fetch brands mapped to client names
$stmtBrandsClient = $db->query("
    SELECT cb.name as brand_name, c.name as client_name 
    FROM client_brands cb 
    JOIN clients c ON cb.client_id = c.id 
    ORDER BY cb.name ASC
");
$clientBrandsMapRaw = $stmtBrandsClient->fetchAll(PDO::FETCH_ASSOC);
$clientBrandsMap = [];
foreach ($clientBrandsMapRaw as $row) {
    $clientBrandsMap[$row['client_name']][] = $row['brand_name'];
}

// Fetch employees for the 'Encargado del Área' dropdown
$stmtEmployees = $db->query("SELECT id, name FROM employees ORDER BY name ASC");
$employees = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC);

// Fetch all services and features for the workflow auto-populate
$stmtServices = $db->query("SELECT id, name FROM services ORDER BY name ASC");
$servicesList = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

$stmtFeatures = $db->query("SELECT service_id, title, description FROM service_features ORDER BY id ASC");
$allFeatures = $stmtFeatures->fetchAll(PDO::FETCH_ASSOC);

// Group features by service_id
$servicesFeaturesMap = [];
foreach ($allFeatures as $f) {
    if (!isset($servicesFeaturesMap[$f['service_id']])) {
        $servicesFeaturesMap[$f['service_id']] = [];
    }
    $servicesFeaturesMap[$f['service_id']][] = [
        'title' => $f['title'],
        'description' => $f['description']
    ];
}

require_once 'includes/header.php';
?>
<!-- Quill JS CDN -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

<!-- User's Custom CSS integrated into the content wrapper -->
<style>
    .ot-container {
        font-family: 'Inter', sans-serif;
        color: var(--color-text, #1a2c3e);
        padding-bottom: 2rem;
    }

    .floating-toolbar {
        position: sticky;
        top: 0;
        background: var(--bg-surface);
        z-index: 90;
        padding: 1rem 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        border-radius: 0 0 1rem 1rem;
        border-bottom: 1px solid var(--border-color);
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
      color: var(--primary-color);
      background: var(--bg-color);
      padding: 6px;
      border-radius: 12px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    }
    .logo-area h2 {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--color-title);
      margin: 0;
    }
    .button-group {
      display: flex;
      gap: 0.9rem;
      align-items: center;
      flex-wrap: wrap;
    }
    .btn-float {
      background: var(--bg-color);
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
      color: var(--color-text);
      border: 1px solid var(--border-color);
    }
    .btn-float i {
      font-size: 1rem;
    }
    .btn-float:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 18px rgba(0,0,0,0.1);
      background: var(--bg-surface);
      border-color: var(--primary-color);
    }
    .btn-primary-float {
      background: var(--primary-color);
      color: white;
      border: none;
      box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }
    .btn-primary-float:hover {
      background: var(--primary-color);
      filter: brightness(0.9);
      transform: translateY(-2px);
      color: white;
    }

    .toast-msg {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      background: #0f766e;
      color: #ffffff;
      padding: 0.8rem 1.5rem;
      border-radius: 2rem;
      box-shadow: 0 8px 25px rgba(0,0,0,0.3);
      font-size: 0.9rem;
      font-weight: 600;
      z-index: 10000;
      animation: toastIn 0.3s cubic-bezier(0.18, 0.89, 0.32, 1.28);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    @keyframes toastIn {
      from { transform: translateY(100%) scale(0.9); opacity: 0; }
      to { transform: translateY(0) scale(1); opacity: 1; }
    }
    
    .btn-back {
        background: var(--bg-color);
        color: var(--text-muted);
        border-color: var(--border-color);
    }

    .info-cards {
      display: flex;
      flex-wrap: wrap;
      gap: 1.2rem;
      margin-bottom: 2rem;
    }
    .card-glass {
      background: var(--bg-surface);
      border-radius: 1rem;
      padding: 1rem 1.2rem;
      flex: 1 1 200px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.03);
      border: 1px solid var(--border-color);
    }
    .card-glass label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-weight: 600;
      color: var(--text-muted);
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
      color: var(--color-text);
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
      color: var(--color-title);
      margin: 0;
    }

    .process-card {
      background: var(--bg-surface);
      border-radius: 1rem;
      margin-bottom: 1.4rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.03);
      border: 1px solid var(--border-color);
      overflow: hidden;
    }
    .process-header {
      background: var(--bg-color);
      padding: 1rem 1.6rem;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--border-color);
    }
    .process-name {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      font-weight: 700;
      font-size: 1.1rem;
      color: var(--primary-color);
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
      color: var(--text-muted);
    }
    .btn-icon-ot:hover {
      background: var(--bg-color);
      color: var(--primary-color);
    }
    .danger:hover {
      color: var(--danger-color, #e05a56);
      background: rgba(224, 90, 86, 0.1);
    }
    .rows-container {
      padding: 1rem 1.2rem 1.4rem 1.2rem;
      background: var(--bg-surface);
    }
    .row-item {
      background: var(--bg-surface);
      border-radius: 12px;
      padding: 1rem 1.2rem;
      margin-bottom: 0.8rem;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 1.2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.02);
      border: 1px solid var(--border-color);
      transition: box-shadow 0.2s, transform 0.2s;
    }
    .row-item:hover {
      box-shadow: 0 6px 20px rgba(0,0,0,0.04);
      transform: translateY(-2px);
    }
    .row-field {
      flex: 2 1 180px;
      min-width: 150px;
      position: relative;
    }
    .row-field label {
      display: block;
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--text-muted);
      margin-bottom: 0.4rem;
    }
    .row-field select, .row-field textarea, .row-field input {
      width: 100%;
      border: none;
      background: var(--bg-color);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      font-size: 0.9rem;
      font-weight: 500;
      font-family: 'Inter', sans-serif;
      color: var(--color-text);
      box-shadow: inset 0 1px 3px rgba(0,0,0,0.02);
      transition: all 0.2s;
    }
    .row-field select:focus, .row-field textarea:focus, .row-field input:focus {
      background: var(--bg-surface);
      outline: none;
      box-shadow: inset 0 0 0 2px var(--primary-color), 0 4px 12px rgba(var(--primary-color-rgb, 79, 70, 229), 0.1);
    }
    .row-field textarea {
        resize: vertical;
        min-height: 44px;
        line-height: 1.4;
    }
    .remove-row-btn {
      background: var(--bg-color);
      border: none;
      font-size: 1.2rem;
      color: var(--text-muted);
      cursor: pointer;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.2s;
    }
    .remove-row-btn:hover {
      background: rgba(224, 90, 86, 0.1);
      color: var(--danger-color, #e05a56);
      transform: scale(1.05);
    }
    .add-row-btn {
      margin-top: 0.4rem;
      background: var(--bg-color);
      border: 1px dashed var(--border-color);
      border-radius: 2rem;
      padding: 0.5rem 1rem;
      font-size: 0.75rem;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      cursor: pointer;
      color: var(--primary-color);
    }
    .budget-obs {
      background: var(--bg-surface);
      border-radius: 1rem;
      padding: 1.5rem;
      margin-top: 2rem;
      display: flex;
      flex-wrap: wrap;
      gap: 2rem;
      border: 1px solid var(--border-color);
    }
    .budget-box, .obs-box {
      flex: 1;
      min-width: 250px;
      overflow: hidden;
    }
    .budget-box h4, .obs-box h4 {
      font-size: 0.8rem;
      font-weight: 700;
      text-transform: uppercase;
      color: var(--text-muted);
      margin-bottom: 0.6rem;
      margin-top: 0;
    }
    .budget-box input, .obs-box textarea {
      width: 100%;
      border-radius: 0.5rem;
      border: 1px solid var(--border-color);
      padding: 0.8rem 1rem;
      font-family: 'Inter', sans-serif;
      background: var(--bg-color);
      color: var(--color-text);
    }

    /* Modern Quill Editor Styling */
    .obs-box .ql-toolbar.ql-snow {
      border: 1px solid var(--border-color);
      border-radius: 10px 10px 0 0;
      background: var(--bg-body);
      padding: 0.5rem 0.7rem;
    }
    .obs-box .ql-toolbar.ql-snow .ql-formats button {
      width: 32px;
      height: 32px;
      border-radius: 6px;
      transition: all 0.15s ease;
    }
    .obs-box .ql-toolbar.ql-snow .ql-formats button:hover {
      background: var(--bg-surface);
    }
    .obs-box .ql-toolbar.ql-snow .ql-formats button.ql-active {
      background: var(--primary-color);
      color: white;
    }
    .obs-box .ql-toolbar.ql-snow .ql-formats button.ql-active .ql-stroke {
      stroke: white;
    }
    .obs-box .ql-toolbar.ql-snow .ql-formats button.ql-active .ql-fill {
      fill: white;
    }
    .obs-box .ql-toolbar.ql-snow .ql-stroke {
      stroke: var(--text-muted);
    }
    .obs-box .ql-toolbar.ql-snow .ql-fill {
      fill: var(--text-muted);
    }
    .obs-box .ql-container.ql-snow {
      border: 1px solid var(--border-color);
      border-top: none;
      border-radius: 0 0 10px 10px;
      font-family: 'Inter', sans-serif;
      font-size: 0.9rem;
      color: var(--color-text);
      background: var(--bg-color);
      min-height: 120px;
      transition: box-shadow 0.2s ease;
    }
    .obs-box .ql-container.ql-snow:focus-within {
      box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.15);
    }
    .obs-box .ql-editor {
      min-height: 120px;
      padding: 0.8rem 1rem;
      line-height: 1.6;
    }
    .obs-box .ql-editor.ql-blank::before {
      color: var(--text-muted);
      font-style: normal;
      opacity: 0.6;
    }

    /* Modern SweetAlert2 Popup */
    .swal-modern-popup {
      border-radius: 1rem !important;
      border: 1px solid var(--border-color) !important;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
      font-family: 'Inter', sans-serif !important;
    }
    .swal-modern-popup .swal2-title {
      font-size: 1.2rem !important;
      font-weight: 700 !important;
    }
    .swal-modern-popup .swal2-html-container {
      font-size: 0.9rem !important;
      line-height: 1.5 !important;
    }
    .swal-modern-popup .swal2-actions button {
      border-radius: 8px !important;
      font-weight: 600 !important;
      font-family: 'Inter', sans-serif !important;
      padding: 0.5rem 1.2rem !important;
      font-size: 0.9rem !important;
    }

    /* === Accordion === */
    .os-accordion {
      background: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: 1rem;
      margin-bottom: 2rem;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .os-accordion-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem 1.5rem;
      cursor: pointer;
      transition: background 0.2s;
      user-select: none;
    }
    .os-accordion-header:hover {
      background: transparent;
    }
    .os-accordion-icon {
      font-size: 1.25rem;
      color: var(--text-muted);
      transition: transform 0.3s ease;
    }
    .os-accordion.open .os-accordion-icon {
      transform: rotate(180deg);
    }
    .os-accordion-body {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.35s ease, padding 0.35s ease;
      padding: 0 1.5rem;
    }
    .os-accordion.open .os-accordion-body {
      max-height: 600px;
      padding: 0 1.5rem 1.5rem;
    }
    .os-accordion-summary {
      transition: opacity 0.2s;
    }
    .os-accordion.open .os-accordion-summary {
      opacity: 0;
      height: 0;
      margin: 0 !important;
    }

    /* Accordion inner fields */
    .os-field-group {
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
    }
    .os-field-group label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      font-weight: 600;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      gap: 0.35rem;
    }
    .os-field-input {
      width: 100%;
      padding: 0.6rem 0.9rem;
      border: 1px solid var(--border-color);
      border-radius: 0.6rem;
      font-family: 'Inter', sans-serif;
      font-size: 0.9rem;
      font-weight: 500;
      color: var(--color-text);
      background: var(--bg-color);
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .os-field-input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb, 79,70,229), 0.12);
    }
    .os-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.35rem 0.75rem;
      background: var(--bg-color);
      border: 1px solid var(--border-color);
      border-radius: 2rem;
      font-size: 0.8rem;
      font-weight: 500;
      cursor: pointer;
      color: var(--color-text);
      text-transform: none;
      letter-spacing: 0;
      transition: all 0.15s;
    }
    .os-chip:hover {
      border-color: var(--primary-color);
      background: var(--bg-surface);
    }
    .os-chip input[type="checkbox"] {
      width: auto;
      margin: 0;
      accent-color: var(--primary-color);
    }
    .os-chip:has(input:checked) {
      background: var(--primary-color);
      color: white;
      border-color: var(--primary-color);
    }
    @media (max-width: 600px) {
      .os-accordion-body > div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
      }
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
      .logo-area h2 { display: none; }
    }
    /* Dark Mode OLED */
    [data-theme="dark"] body,
    [data-theme="dark"] .ot-container {
        background-color: #000000 !important;
    }
    [data-theme="dark"] .card-glass,
    [data-theme="dark"] .process-card,
    [data-theme="dark"] .os-accordion,
    [data-theme="dark"] .budget-obs,
    [data-theme="dark"] .floating-toolbar {
        background: #0a0a0a !important;
        border-color: #262626 !important;
    }
    [data-theme="dark"] .row-item,
    [data-theme="dark"] .process-header,
    [data-theme="dark"] .rows-container,
    [data-theme="dark"] .row-field select,
    [data-theme="dark"] .row-field textarea,
    [data-theme="dark"] .row-field input,
    [data-theme="dark"] .budget-box input,
    [data-theme="dark"] .obs-box textarea,
    [data-theme="dark"] .os-field-input,
    [data-theme="dark"] .os-chip {
        background: #000000 !important;
        border-color: #262626 !important;
        color: #f4f4f5 !important;
    }
    [data-theme="dark"] .btn-float {
        background: #141414 !important;
        border-color: #262626 !important;
        color: #f4f4f5 !important;
    }
    [data-theme="dark"] .btn-primary-float {
        background: var(--primary-color) !important;
        color: #ffffff !important;
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

    <!-- Acordeón: Información del servicio -->
    <div class="os-accordion" id="accordion-info-servicio">
        <div class="os-accordion-header" onclick="toggleAccordion('accordion-info-servicio')">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: var(--primary-color); display: flex; align-items: center; justify-content: center;">
                    <i class="ph ph-clipboard-text" style="color: white; font-size: 1.1rem;"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--color-title);">Información del servicio</h3>
                    <p class="os-accordion-summary" id="info-summary" style="margin: 0; font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;"></p>
                </div>
            </div>
            <i class="ph ph-caret-down os-accordion-icon"></i>
        </div>
        <div class="os-accordion-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="os-field-group">
                    <label><i class="ph ph-buildings"></i> CLIENTE</label>
                    <select id="clienteName" class="os-field-input">
                        <option value="">Seleccionar Cliente...</option>
                        <?php foreach($clients as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['name']); ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="os-field-group">
                    <label><i class="ph ph-tag"></i> MARCA</label>
                    <select id="marcaName" class="os-field-input">
                        <option value="">Primero seleccione un cliente...</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom: 1rem;">
                <div class="os-field-group">
                    <label><i class="ph ph-globe"></i> REDES A MANEJAR</label>
                    <div id="redesManejarWrapper" style="margin-top: 0.8rem; display: flex; flex-direction: column; gap: 0.8rem;"></div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="os-field-group">
                    <label><i class="ph ph-calendar-blank"></i> FECHA DE INICIO</label>
                    <input type="date" id="fechaInicio" value="" class="os-field-input">
                </div>
                <div class="os-field-group">
                    <label><i class="ph ph-flag"></i> PRIORIDAD</label>
                    <select id="osPrioridad" class="os-field-input">
                        <option value="Baja">Baja (Verde)</option>
                        <option value="Media" selected>Media (Amarillo)</option>
                        <option value="Alta">Alta (Rojo)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="section-title">
        <div>
            <span style="color: var(--text-muted); font-size: 0.85rem; display: block; margin-top: 4px;">
                <i class="ph ph-info"></i> Agrega o elimina filas, o selecciona un servicio para autocompletar.
            </span>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-surface); padding: 0.5rem; border-radius: 0.8rem; border: 1px solid var(--border-color);">
            <i class="ph ph-briefcase" style="color: var(--primary-color); font-size: 1.2rem;"></i>
            <select id="serviceSelector" style="border: none; outline: none; background: transparent; font-family: 'Inter', sans-serif; font-size: 0.9rem; font-weight: 600; color: var(--color-text); cursor: pointer; min-width: 200px;">
                <option value="">Seleccionar un Servicio...</option>
                <?php foreach($servicesList as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div id="procesosContainer"></div>

    <div class="budget-obs">
        <div class="budget-box">
            <h4><i class="ph ph-currency-circle-dollar"></i> PRESUPUESTO ADS + CUENTA COMERCIAL</h4>
            <div style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-color); padding: 0.5rem 0.8rem; border-radius: 8px; border: 1px solid var(--border-color); transition: box-shadow 0.2s;" onfocusin="this.style.boxShadow='0 0 0 2px var(--primary-color)33'" onfocusout="this.style.boxShadow='none'">
                <select id="monedaPresupuesto" style="border: none; background: transparent; font-weight: 700; color: var(--color-title); font-size: 0.95rem; outline: none; cursor: pointer; padding-right: 0.5rem; border-right: 1px solid var(--border-color);">
                    <option value="S/">S/</option>
                    <option value="USD">USD</option>
                </select>
                <input type="text" inputmode="decimal" id="presupuestoAds" name="budget_ots_amount" placeholder="0.00" value="" autocomplete="one-time-code" data-lpignore="true" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" style="flex: 1; border: none; background: transparent; font-size: 1rem; color: var(--color-text); outline: none; padding-left: 0.5rem;">
            </div>
            <small style="color: var(--text-muted); display: block; margin-top: 0.5rem; font-size: 0.75rem;"><i class="ph ph-info"></i> Cuenta comercial ya incluida</small>
        </div>
        <div class="obs-box">
            <h4><i class="ph ph-text-align-left"></i> OBSERVACIONES ADICIONALES</h4>
            <div id="observacionesContainer" style="border-radius: 10px; min-height: 120px;"></div>
            <textarea id="observacionesGlobal" style="display:none;"></textarea>
        </div>
    </div>
    
    <div style="margin-top: 1.5rem; padding: 1.5rem; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h4 style="margin: 0; font-size: 0.95rem; color: var(--color-title); display: flex; align-items: center; gap: 0.5rem;"><i class="ph ph-faders"></i> CAMPOS PERSONALIZADOS</h4>
            <button type="button" id="add-custom-field-btn" style="background: var(--bg-body); border: 1px solid var(--border-color); color: var(--color-title); padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.3rem;"><i class="ph ph-plus"></i> Añadir Campo</button>
        </div>
        <div id="customFieldsContainer" style="display: flex; flex-direction: column; gap: 0.8rem;"></div>
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
  // Accordion toggle
  function toggleAccordion(id) {
    const el = document.getElementById(id);
    el.classList.toggle('open');
    if (!el.classList.contains('open')) {
      updateInfoSummary();
    }
  }

  const REDES_DEF = [
    { id: 'Facebook', label: 'Facebook', icon: 'ph-facebook-logo', color: '#1877F2' },
    { id: 'Instagram', label: 'Instagram', icon: 'ph-instagram-logo', color: '#E4405F' },
    { id: 'TikTok', label: 'TikTok', icon: 'ph-tiktok-logo', color: '#000000' },
    { id: 'VK', label: 'VK', icon: 'ph-users-three', color: '#4680C2' },
    { id: 'Google', label: 'Google', icon: 'ph-google-logo', color: '#DB4437' },
    { id: 'YouTube', label: 'YouTube', icon: 'ph-youtube-logo', color: '#FF0000' },
    { id: 'LinkedIn', label: 'LinkedIn', icon: 'ph-linkedin-logo', color: '#0A66C2' },
    { id: 'Web', label: 'Enlace Web', icon: 'ph-globe', color: '#577a9e' }
  ];
  let redesData = []; // [{ id: 'Facebook', url: '' }]
  let customFieldsData = []; // [{ name: '', value: '' }]

  function renderCustomFields() {
    const container = document.getElementById('customFieldsContainer');
    if (!container) return;
    
    let html = '';
    customFieldsData.forEach((cf, index) => {
      html += `
        <div style="display: flex; gap: 0.5rem; align-items: center; background: var(--bg-body); padding: 0.5rem; border-radius: 8px;">
            <input type="text" class="cf-name-input" data-index="${index}" value="${escapeHtml(cf.name)}" placeholder="Nombre (ej. Figma)" style="width: 150px; border: none; background: var(--bg-surface); border-radius: 6px; padding: 0.5rem; font-size: 0.85rem; outline: none; font-weight: 600; color: var(--color-title);">
            <input type="text" class="cf-value-input" data-index="${index}" value="${escapeHtml(cf.value)}" placeholder="Valor o Enlace" style="flex: 1; border: none; background: var(--bg-surface); border-radius: 6px; padding: 0.5rem; font-size: 0.85rem; outline: none; color: var(--color-text);">
            <button type="button" class="remove-cf-btn" data-index="${index}" style="background: none; border: none; color: var(--color-danger); cursor: pointer; padding: 0.5rem; border-radius: 6px; transition: 0.2s;" onmouseover="this.style.background='var(--color-danger)1a'" onmouseout="this.style.background='none'"><i class="ph ph-trash" style="font-size: 1.1rem;"></i></button>
        </div>
      `;
    });
    container.innerHTML = html;

    container.querySelectorAll('.cf-name-input').forEach(input => {
      input.addEventListener('input', (e) => {
        customFieldsData[e.target.dataset.index].name = e.target.value;
      });
    });
    container.querySelectorAll('.cf-value-input').forEach(input => {
      input.addEventListener('input', (e) => {
        customFieldsData[e.target.dataset.index].value = e.target.value;
      });
    });
    container.querySelectorAll('.remove-cf-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        customFieldsData.splice(e.currentTarget.dataset.index, 1);
        renderCustomFields();
      });
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
      const addCfBtn = document.getElementById('add-custom-field-btn');
      if (addCfBtn) {
          addCfBtn.addEventListener('click', () => {
              customFieldsData.push({ name: '', value: '' });
              renderCustomFields();
          });
      }
  });

  function detectNetwork(url) {
    if (!url) return 'Web';
    const u = url.toLowerCase();
    if (u.includes('facebook.com') || u.includes('fb.me')) return 'Facebook';
    if (u.includes('instagram.com')) return 'Instagram';
    if (u.includes('tiktok.com')) return 'TikTok';
    if (u.includes('vk.com')) return 'VK';
    if (u.includes('youtube.com') || u.includes('youtu.be')) return 'YouTube';
    if (u.includes('linkedin.com')) return 'LinkedIn';
    if (u.includes('google.com') || u.includes('g.page')) return 'Google';
    return 'Web';
  }

  function renderRedes() {
    const container = document.getElementById('redesManejarWrapper');
    if (!container) return;
    
    let html = '';
    
    if (redesData.length > 0) {
      html += '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 0.8rem; margin-bottom: 0.5rem;">';
      redesData.forEach((redSel, index) => {
        const def = REDES_DEF.find(d => d.id === redSel.id) || REDES_DEF.find(d => d.id === 'Web');
        html += `
          <div class="row-item" style="display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1rem; margin-bottom: 0;">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: ${def.color}; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; transition: all 0.3s; box-shadow: 0 4px 10px ${def.color}40;" title="${def.label}">
              <i class="${def.icon}"></i>
            </div>
            <input type="text" class="red-url-input" data-index="${index}" value="${escapeHtml(redSel.url)}" placeholder="https://..." style="flex: 1; border: none; background: var(--bg-color); border-radius: 8px; padding: 0.8rem 1rem; font-size: 0.95rem; color: var(--color-text); outline: none; transition: box-shadow 0.2s;" onfocus="this.style.boxShadow='0 0 0 2px var(--primary-color)33'" onblur="this.style.boxShadow='none'">
            <button type="button" class="remove-red-btn" data-index="${index}" style="background: none; border: none; color: var(--color-danger); cursor: pointer; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='var(--color-danger)1a'" onmouseout="this.style.background='none'">
              <i class="ph ph-trash" style="font-size: 1.2rem;"></i>
            </button>
          </div>
        `;
      });
      html += '</div>';
    }

    html += `
      <button type="button" id="add-red-btn" style="align-self: flex-start; background: var(--bg-color); border: 1px dashed var(--border-color); color: var(--primary-color); padding: 0.5rem 1rem; border-radius: 2rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 0.2rem;">
        <i class="ph ph-plus"></i> Añadir enlace
      </button>
    `;

    container.innerHTML = html;

    // Listeners
    const addBtn = container.querySelector('#add-red-btn');
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        redesData.push({ id: 'Web', url: '' });
        renderRedes();
      });
    }

    container.querySelectorAll('.remove-red-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const index = parseInt(e.currentTarget.dataset.index);
        redesData.splice(index, 1);
        renderRedes();
        updateInfoSummary();
      });
    });

    container.querySelectorAll('.red-url-input').forEach(input => {
      input.addEventListener('input', (e) => {
        const index = parseInt(e.currentTarget.dataset.index);
        const val = e.currentTarget.value;
        redesData[index].url = val;
        
        // Auto-detect network
        const detected = detectNetwork(val);
        if (redesData[index].id !== detected) {
          redesData[index].id = detected;
          renderRedes(); // Re-render to update icon and color
          // We need to refocus the input since we re-rendered
          setTimeout(() => {
            const inputs = document.getElementById('redesManejarWrapper').querySelectorAll('.red-url-input');
            if(inputs[index]) {
                inputs[index].focus();
                // Move cursor to end
                const len = inputs[index].value.length;
                inputs[index].setSelectionRange(len, len);
            }
          }, 0);
        }
        updateInfoSummary();
      });
    });
  }

  function updateInfoSummary() {
    const cliente = document.getElementById('clienteName').value || 'Sin cliente';
    const marca = document.getElementById('marcaName').value || '';
    const redes = redesData.map(r => r.id);
    const fecha = document.getElementById('fechaInicio').value || '';

    let parts = [cliente];
    if (marca) parts.push(marca);
    if (redes.length) parts.push(redes.join(', '));
    if (fecha) parts.push(fecha);

    const summary = document.getElementById('info-summary');
    if (summary) summary.textContent = parts.join(' · ');
  }

  document.addEventListener('DOMContentLoaded', () => {
    ['clienteName', 'marcaName', 'fechaInicio'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', updateInfoSummary);
    });
  });
</script>

<script>
  const MAIN_WORKFLOW = [
    { id: "main_workflow", nombre: "FLUJO DE TRABAJO PRINCIPAL", icono: "ph-kanban", rowsDefault: [] }
  ];
  const SERVICES_FEATURES_MAP = <?php echo json_encode($servicesFeaturesMap); ?>;

  let procesosData = [];
  const dbData = <?php echo $order_data ? $order_data : 'null'; ?>;
  const woId = '<?php echo $id; ?>';

  // Helper toast integrado con la app
  function showAppToast(message) {
    let existing = document.querySelector('.toast-msg');
    if(existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'toast-msg';
    toast.innerHTML = '<i class="ph ph-check-circle" style="font-size: 1.1rem;"></i> ' + message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
  }

  let quillGlobal = null;
  document.addEventListener('DOMContentLoaded', () => {
    quillGlobal = new Quill('#observacionesContainer', {
      theme: 'snow',
      modules: {
          toolbar: [
              ['bold', 'italic', 'underline'],
              [{ 'list': 'bullet' }],
              ['link', 'clean']
          ]
      }
    });
    quillGlobal.on('text-change', () => {
      document.getElementById('observacionesGlobal').value = quillGlobal.root.innerHTML;
    });
  });

  function captureFullState() {
    return {
      procesos: procesosData,
      cliente: document.getElementById('clienteName').value,
      marca: document.getElementById('marcaName').value,
      redes: JSON.stringify(redesData),
      fechaInicio: document.getElementById('fechaInicio').value,
      prioridad: document.getElementById('osPrioridad').value,
      presupuesto: (document.getElementById('monedaPresupuesto') ? document.getElementById('monedaPresupuesto').value + ' ' : '') + document.getElementById('presupuestoAds').value,
      observaciones: document.getElementById('observacionesGlobal').value,
      customFields: customFieldsData
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
              <div class="quill-editor" style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; min-height: 60px;"></div>
              <textarea class="descripcion-input" style="display:none;">${escapeHtml(row.descripcion)}</textarea>
            </div>
            <div class="row-actions">
              <button type="button" class="remove-row-btn" title="Eliminar fila"><i class="ph ph-x-circle"></i></button>
            </div>
          `;
          const encInput = rowDiv.querySelector('.encargado-input');
          encInput.addEventListener('input', (e) => { currentProcess.rows[rowIndex].encargado = e.target.value; });
          const quillContainer = rowDiv.querySelector('.quill-editor');
          if (quillContainer) {
              const quill = new Quill(quillContainer, {
                  theme: 'snow',
                  modules: {
                      toolbar: [
                          ['bold', 'italic', 'underline', 'link']
                      ]
                  }
              });
              // Disable link tooltip target=_blank annoyance if needed
              quill.root.innerHTML = row.descripcion;
              quill.on('text-change', function() {
                  currentProcess.rows[rowIndex].descripcion = quill.root.innerHTML;
                  rowDiv.querySelector('.descripcion-input').value = quill.root.innerHTML;
              });
          }
          const removeBtn = rowDiv.querySelector('.remove-row-btn');
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
    if(proceso) {
      Swal.fire({
        title: '¿Eliminar filas?',
        html: `Se eliminarán todas las filas en <strong>"${proceso.nombre}"</strong>.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0f766e',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="ph ph-trash"></i> Sí, eliminar',
        cancelButtonText: 'Cancelar',
        background: 'var(--bg-surface)',
        color: 'var(--color-text)',
        customClass: { popup: 'swal-modern-popup' }
      }).then((result) => {
        if (result.isConfirmed) {
          proceso.rows = [];
          renderProcesos();
          Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Filas eliminadas', showConfirmButton: false, timer: 2000, background: 'var(--bg-surface)', color: 'var(--color-text)' });
        }
      });
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
      if(state.procesos) {
          // Backward compatibility: flatten multiple old processes into the single workflow
          let mergedRows = [];
          state.procesos.forEach(p => {
              if (p.rows && p.rows.length > 0) {
                  // Only add rows that actually have content to avoid blank rows piling up
                  p.rows.forEach(r => {
                      if (r.descripcion.trim() !== '' || r.encargado.trim() !== '') {
                          mergedRows.push(r);
                      }
                  });
              }
          });
          if (mergedRows.length === 0) {
              mergedRows = [];
          }
          procesosData = [{ id: "main_workflow", nombre: "FLUJO DE TRABAJO", icono: "ph-kanban", rows: mergedRows }];
      }
      if(state.cliente) document.getElementById('clienteName').value = state.cliente;
      if(state.marca) document.getElementById('marcaName').value = state.marca;
      if(state.redes) {
          try {
              // Intenta parsear el JSON de redes (nuevo formato array de objetos)
              const parsed = JSON.parse(state.redes);
              if (Array.isArray(parsed)) {
                  redesData = parsed;
              } else {
                  throw new Error("Formato antiguo");
              }
          } catch(e) {
              // Si falla o no es array, es string separado por comas (formato antiguo)
              const arr = state.redes.split(',').map(s => s.trim());
              redesData = arr.filter(Boolean).map(net => ({ id: net, url: '' }));
          }
      }
      if(state.fechaInicio) document.getElementById('fechaInicio').value = state.fechaInicio;
      if(state.presupuesto) {
          let val = state.presupuesto;
          if (val.startsWith('S/ ')) {
             if(document.getElementById('monedaPresupuesto')) document.getElementById('monedaPresupuesto').value = 'S/';
             val = val.substring(3);
          } else if (val.startsWith('USD ')) {
             if(document.getElementById('monedaPresupuesto')) document.getElementById('monedaPresupuesto').value = 'USD';
             val = val.substring(4);
          }
          document.getElementById('presupuestoAds').value = val;
      }
      if(state.observaciones) {
          document.getElementById('observacionesGlobal').value = state.observaciones;
          if (quillGlobal) {
              quillGlobal.root.innerHTML = state.observaciones;
          } else {
              // Si quillGlobal aún no cargó (raro pero posible)
              setTimeout(() => { if (quillGlobal) quillGlobal.root.innerHTML = state.observaciones; }, 100);
          }
      }
      if(state.prioridad) document.getElementById('osPrioridad').value = state.prioridad;
      if(state.customFields && Array.isArray(state.customFields)) {
          customFieldsData = state.customFields;
          renderCustomFields();
      }
    } else {
      procesosData = MAIN_WORKFLOW.map(p => ({ id: p.id, nombre: p.nombre, icono: p.icono, rows: p.rowsDefault.map(r => ({ encargado: r.encargado, descripcion: r.descripcion })) }));
      document.getElementById('fechaInicio').value = new Date().toISOString().split('T')[0];
    }
    
    // Ensure the main workflow exists
    const existing = procesosData.find(p => p.id === "main_workflow");
    if(!existing) {
        procesosData = [{ id: "main_workflow", nombre: "FLUJO DE TRABAJO", icono: "ph-kanban", rows: [] }];
    }
    renderRedes();
    updateInfoSummary();
    renderProcesos();
  }

  // Service Selection Logic
  document.getElementById('serviceSelector').addEventListener('change', function() {
      const serviceId = this.value;
      if (!serviceId) return;
      
      const currentProcess = procesosData.find(p => p.id === "main_workflow");
      if (!currentProcess) return;

      const hasData = currentProcess.rows.some(r => r.descripcion.trim() !== '' || r.encargado.trim() !== '');
      
      if (hasData) {
          const selectEl = this;
          Swal.fire({
            title: '¿Reemplazar filas?',
            html: 'Esto reemplazará las filas actuales por las características del servicio seleccionado.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0f766e',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="ph ph-arrows-clockwise"></i> Sí, reemplazar',
            cancelButtonText: 'Cancelar',
            background: 'var(--bg-surface)',
            color: 'var(--color-text)',
            customClass: { popup: 'swal-modern-popup' }
          }).then((result) => {
            if (result.isConfirmed) {
              applyServiceFeatures(serviceId, currentProcess);
            } else {
              selectEl.value = '';
            }
          });
          return;
      }

      applyServiceFeatures(serviceId, currentProcess);
      this.value = '';
  });

  function applyServiceFeatures(serviceId, currentProcess) {
      const features = SERVICES_FEATURES_MAP[serviceId] || [];
      
      if (features.length > 0) {
          currentProcess.rows = features.map(f => ({
              encargado: "",
              descripcion: f.title + (f.description ? ": " + f.description : "")
          }));
      } else {
          currentProcess.rows = [{ encargado: "", descripcion: "Este servicio no tiene características registradas." }];
      }
      
      renderProcesos();
      showAppToast('Filas actualizadas según el servicio seleccionado.');
      document.getElementById('serviceSelector').value = '';
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

  // Handle dependent dropdown for Brands based on selected Client
  const clientBrandsMap = <?php echo json_encode($clientBrandsMap); ?>;
  
  function populateBrands(selectedClient, preselect) {
      const select = document.getElementById('marcaName');
      select.innerHTML = '';
      
      if (!selectedClient) {
          select.innerHTML = '<option value="">Primero seleccione un cliente...</option>';
          return;
      }
      
      const brandsToShow = clientBrandsMap[selectedClient] || [];
      
      if (brandsToShow.length === 0) {
          select.innerHTML = '<option value="">Sin marcas registradas</option>';
          return;
      }
      
      select.innerHTML = '<option value="">Seleccionar Marca...</option>';
      brandsToShow.forEach(brand => {
          const option = document.createElement('option');
          option.value = brand;
          option.textContent = brand;
          if (preselect && brand === preselect) option.selected = true;
          select.appendChild(option);
      });
  }
  
  document.getElementById('clienteName').addEventListener('change', function() {
      populateBrands(this.value, null);
  });

  document.getElementById('saveSnapshotBtn')?.addEventListener('click', handleSaveDB);

  window.addEventListener('DOMContentLoaded', () => {
    initData();
    // After data is loaded, populate brands based on loaded client and preselect saved brand
    setTimeout(() => {
        const clienteEl = document.getElementById('clienteName');
        const savedBrand = document.getElementById('marcaName').getAttribute('data-saved') || document.getElementById('marcaName').value;
        populateBrands(clienteEl.value, savedBrand);
    }, 500);
  });
</script>

<?php require_once 'includes/footer.php'; ?>
