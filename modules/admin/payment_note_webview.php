<?php
// modules/admin/payment_note_webview.php
$is_public = isset($_GET['view']) && $_GET['view'] === 'public';
$public_note_data = null;

if ($is_public) {
    // If it's public, check for the token in payment_notes
    if (isset($_GET['token'])) {
        $stmtNote = $db->prepare("SELECT * FROM payment_notes WHERE public_token = ?");
        $stmtNote->execute([$_GET['token']]);
        $note = $stmtNote->fetch(PDO::FETCH_ASSOC);
        
        if ($note) {
            $data = [
                'id' => $note['note_code'],
                'client' => $note['client_name'],
                'company' => $note['company_name'],
                'startDate' => $note['start_date'],
                'total' => $note['total'],
                'servicios' => json_decode($note['services_json'], true) ?: [],
                'cronograma' => json_decode($note['schedule_json'], true) ?: [],
                'status' => $note['status']
            ];
            $public_note_data = base64_encode(json_encode($data));
        } else {
            // Fallback for old shared links if needed
            $stmtToken = $db->prepare("SELECT data FROM shared_links WHERE token = ?");
            $stmtToken->execute([$_GET['token']]);
            $tokenData = $stmtToken->fetchColumn();
            if ($tokenData) {
                $public_note_data = $tokenData; // Base64 string
            }
        }
    }
}

require_once 'includes/header.php';

// Fetch clients for the dropdown
$stmt = $db->query("
    SELECT c.id, c.name, 
           GROUP_CONCAT(b.name SEPARATOR '||') as brands
    FROM clients c
    LEFT JOIN client_brands b ON c.id = b.client_id
    GROUP BY c.id
    ORDER BY c.name ASC
");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
/* Specific Styles for Nota de Pago Webview */
.payment-notes-container {
    --card-elevation: 0 8px 20px rgba(0, 0, 0, 0.02), 0 2px 4px rgba(0, 0, 0, 0.05);
    --border: var(--border-color);
    --accent: var(--primary-color);
    --accent-light: color-mix(in srgb, var(--primary-color) 80%, white);
    --success: var(--secondary-color);
    --pending: var(--warning-color);
    --pending-bg: color-mix(in srgb, var(--warning-color) 10%, transparent);
    --paid-bg: color-mix(in srgb, var(--secondary-color) 10%, transparent);
    --paid-text: var(--secondary-color);
    --hover-card: var(--bg-surface);
    --copy-btn: var(--bg-color);

    max-width: 100%;
    margin: 0 auto;
    width: 100%;
    animation: fadeIn 0.3s ease;
}

[data-theme="dark"] .payment-notes-container {
    --card-elevation: 0 8px 18px rgba(0, 0, 0, 0.4);
    --hover-card: var(--bg-surface);
    --copy-btn: var(--bg-color);
    --pending-bg: color-mix(in srgb, var(--warning-color) 15%, transparent);
    --paid-bg: color-mix(in srgb, var(--secondary-color) 15%, transparent);
}

.payment-notes-container .header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 32px;
    gap: 16px;
}

.payment-notes-container .brand h1 {
    font-size: 1.9rem;
    font-weight: 650;
    letter-spacing: -0.5px;
    background: linear-gradient(130deg, var(--accent), var(--accent-light));
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
}

.payment-notes-container .brand p {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-top: 6px;
    font-weight: 450;
}

.payment-notes-container .section-card {
    background: var(--bg-surface);
    border-radius: 28px;
    border: 1px solid var(--border);
    box-shadow: var(--card-elevation);
    margin-bottom: 36px;
    overflow: hidden;
    transition: all 0.2s;
}

.payment-notes-container .section-header {
    padding: 1.2rem 1.8rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    flex-wrap: wrap;
    background: var(--bg-surface);
}

.payment-notes-container .section-header h2 {
    font-size: 1.35rem;
    font-weight: 600;
    letter-spacing: -0.2px;
    margin: 0;
}

.payment-notes-container .badge-soft {
    background: var(--accent);
    color: white;
    padding: 4px 14px;
    border-radius: 40px;
    font-size: 0.7rem;
    font-weight: 500;
    opacity: 0.9;
}

.payment-notes-container .cards-grid {
    padding: 1.5rem 1.8rem;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.payment-notes-container .row-card {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 1rem 1.2rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    transition: all 0.2s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}

.payment-notes-container .row-card:hover {
    background: var(--hover-card);
    border-color: var(--accent-light);
    transform: translateY(-2px);
}

.payment-notes-container .card-info {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: center;
    flex: 3;
}

.payment-notes-container .info-field {
    display: flex;
    flex-direction: column;
    min-width: 110px;
}

.payment-notes-container .field-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.5px;
}

.payment-notes-container .field-value {
    font-weight: 600;
    font-size: 0.95rem;
    margin-top: 4px;
    color: var(--text-main);
}

.payment-notes-container .amount-highlight {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--accent);
}

.payment-notes-container .card-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.payment-notes-container .btn-icon-sm {
    background: var(--copy-btn);
    border: none;
    border-radius: 40px;
    padding: 6px 16px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: 0.1s;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
}

.payment-notes-container .btn-icon-sm:hover {
    background: var(--accent);
    color: white;
}

.payment-notes-container .status-pill {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 40px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: capitalize;
}

.payment-notes-container .status-pending {
    background: var(--pending-bg);
    color: var(--pending);
    border: 0.5px solid var(--pending);
}

.payment-notes-container .status-paid {
    background: var(--paid-bg);
    color: var(--paid-text);
    border: 0.5px solid var(--paid-text);
}

.payment-notes-container .action-bar {
    padding: 0.8rem 1.8rem 1.5rem 1.8rem;
    display: flex;
    justify-content: flex-end;
    border-top: 1px solid var(--border);
    background: var(--bg-surface);
}

.payment-notes-container .btn-primary-custom {
    background: var(--accent);
    border: none;
    padding: 10px 24px;
    border-radius: 40px;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.payment-notes-container .btn-primary-custom:hover {
    background: var(--accent-light);
    transform: scale(0.97);
}

.payment-notes-container .summary-modern {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 32px;
}

.payment-notes-container .stat-card-modern {
    background: var(--bg-surface);
    border-radius: 24px;
    border: 1px solid var(--border);
    padding: 1.2rem 1.6rem;
    flex: 1;
    min-width: 180px;
    box-shadow: var(--card-elevation);
}

.payment-notes-container .stat-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: 600;
    color: var(--text-muted);
}

.payment-notes-container .stat-number {
    font-size: 2rem;
    font-weight: 700;
    margin-top: 6px;
    color: var(--text-main);
}

.payment-notes-container .stat-highlight {
    border-left: 3px solid var(--pending);
}

.payment-notes-container .pending-number {
    color: var(--pending);
}

.payment-notes-container .payments-wrapper {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
    margin: 12px 0 8px;
}

.payment-notes-container .payment-item {
    background: var(--copy-btn);
    border-radius: 60px;
    padding: 8px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.8rem;
    border: 1px solid var(--border);
    cursor: pointer;
    transition: 0.1s;
    color: var(--text-main);
}

.payment-notes-container .payment-item:hover {
    background: var(--accent);
    color: white;
}

.payment-notes-container .payment-code {
    font-family: monospace;
    font-weight: 600;
}

.toast-msg {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--accent);
    color: white;
    padding: 10px 20px;
    border-radius: 60px;
    font-size: 0.8rem;
    font-weight: 500;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    transition: 0.1s;
}

/* Inline Inputs for editing */
.payment-notes-container .inline-input {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    color: var(--text-main);
    border-radius: var(--radius-sm);
    padding: 0.4rem 0.6rem;
    font-size: 0.85rem;
    font-family: var(--font-family);
    width: 100%;
}
.payment-notes-container .inline-input:focus {
    outline: none;
    border-color: var(--primary-color);
}

/* Service Table Card Layout */
.payment-notes-container .serv-card-table {
    display: block;
    padding: 0 !important;
    overflow: hidden;
}

.payment-notes-container .serv-table-grid {
    display: grid;
    grid-template-columns: 2fr 0.5fr 1fr 1fr;
    align-items: center;
    gap: 0;
    width: 100%;
}

.payment-notes-container .serv-col {
    padding: 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.payment-notes-container .serv-col + .serv-col {
    border-left: 1px solid var(--border-color);
}

.payment-notes-container .serv-col-num {
    text-align: right;
    align-items: flex-end;
}

.payment-notes-container .serv-desc {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-top: 2px;
}

.payment-notes-container .serv-card-table .card-actions {
    border-top: 1px solid var(--border-color);
    padding: 0.6rem 1rem;
    justify-content: flex-end;
    display: flex;
    gap: 8px;
}

@media (max-width: 700px) {
    .payment-notes-container .serv-table-grid {
        grid-template-columns: 1fr 1fr;
        grid-template-rows: auto auto;
    }
    .payment-notes-container .serv-col-name {
        grid-column: 1 / -1;
        border-left: none !important;
        border-bottom: 1px solid var(--border-color);
    }
    .payment-notes-container .serv-col + .serv-col {
        border-left: none;
    }
    .payment-notes-container .serv-col:nth-child(3) {
        border-left: 1px solid var(--border-color);
    }
    .payment-notes-container .serv-col-num {
        text-align: left;
        align-items: flex-start;
    }
}

/* Public Mode Styles */
body.public-mode {
    margin: 0 !important;
    padding: 0 !important;
    overflow-x: hidden;
}
body.public-mode .sidebar,
body.public-mode .topbar {
    display: none !important;
}
body.public-mode .main-content {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
}
body.public-mode .payment-notes-container {
    padding: 0 !important;
    margin: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
body.public-mode .payment-notes-inner {
    padding: 16px;
    padding-top: 180px !important;
    max-width: 800px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}
body.public-mode .header-flex {
    display: none !important;
}
body.public-mode #public-header-banner {
    display: block !important;
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    margin: 0 !important;
    box-sizing: border-box;
    z-index: 10 !important;
}
body.public-mode .action-bar,
body.public-mode .card-actions,
body.public-mode .btn-volver,
body.public-mode .botones-finales {
    display: none !important;
}
body.public-mode .public-readonly-text {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-main);
    display: block;
}
body.public-mode .inline-input {
    display: none !important;
}
body.public-mode .row-card.is-paid {
    opacity: 0.5;
    background-color: var(--bg-page);
    filter: grayscale(100%);
}

@media (max-width: 700px) {
    .payment-notes-container .row-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px;
    }
    .payment-notes-container .card-info {
        width: 100%;
    }
    .payment-notes-container .card-actions {
        width: 100%;
        justify-content: flex-end;
    }
    .payment-notes-container .stat-number {
        font-size: 1.5rem;
    }

    body.public-mode .payment-notes-inner {
        padding: 8px !important;
        padding-top: 170px !important;
        max-width: 100% !important;
    }

    .payment-notes-container .cards-grid {
        padding: 0.75rem 0.5rem;
        gap: 10px;
    }

    .payment-notes-container .row-card {
        border-radius: 14px;
        padding: 0.9rem 1rem;
    }
}
</style>

<div class="payment-notes-container">
  <!-- PUBLIC HEADER BANNER -->
  <div id="public-header-banner" style="display: none; background: var(--primary-color); color: white; padding: 2.5rem 1rem 3.5rem 1rem; text-align: center; border-radius: 0 0 24px 24px; margin-bottom: -24px; position: relative; z-index: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
      <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 8px; flex-wrap: wrap;">
          <h1 style="margin: 0; font-size: 2.2rem; color: white;">NOTA DE PAGO</h1>
          <span id="public-banner-id" style="background: rgba(255,255,255,0.2); padding: 4px 14px; border-radius: 40px; font-weight: 700; font-size: 0.9rem; letter-spacing: 0.5px;"></span>
      </div>
      <p style="margin: 0; opacity: 0.9; font-size: 0.85rem; font-weight: 500;">THE ROMA AGENCY CORPORACION S.A.C. &middot; RUC 20610965530</p>
  </div>

  <div class="payment-notes-inner">
  <div class="mb-3 btn-volver">
      <button class="btn btn-outline" onclick="window.history.back()" style="margin-bottom: var(--space-4);">
          <i class="ph ph-arrow-left"></i> Volver a Notas
      </button>
  </div>

  <div class="header-flex">
    <div class="brand">
      <h1>NOTA DE PAGO</h1>
      <p id="invoice-header-info">NUEVA NOTA &middot; RUC 20610965530 &middot; THE ROMA AGENCY CORPORACION S.A.C.</p>
    </div>
  </div>

  <!-- INFORMACIÓN DEL CLIENTE / PROYECTO -->
  <div class="section-card" id="client-info-card">
    <div class="section-header">
      <h2>Información del Proyecto</h2>
      <div class="badge-soft" style="background: var(--secondary-color); color: #ffffff; border: none;">Datos generales</div>
    </div>
    <div style="padding: 1.5rem 1.8rem;">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
          <div>
              <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 8px; display: block; letter-spacing: 0.5px;">Cliente / Contacto</label>
              <span id="public-client-text" class="public-readonly-text" style="display: none;"></span>
              <select id="note-client" class="inline-input" style="padding: 0.8rem 1rem; font-size: 0.95rem; border-radius: 10px; border: 1px solid var(--border); box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); width: 100%; appearance: auto; background-color: var(--bg-surface);">
                  <option value="">Selecciona un cliente...</option>
                  <?php foreach ($clients as $client): ?>
                      <option value="<?php echo htmlspecialchars($client['name']); ?>" data-brands="<?php echo htmlspecialchars($client['brands'] ?? ''); ?>">
                          <?php echo htmlspecialchars($client['name']); ?>
                      </option>
                  <?php endforeach; ?>
              </select>
          </div>
          <div>
              <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 8px; display: block; letter-spacing: 0.5px;">Empresa / Marca</label>
              <span id="public-company-text" class="public-readonly-text" style="display: none;"></span>
              <select id="note-company" class="inline-input" style="padding: 0.8rem 1rem; font-size: 0.95rem; border-radius: 10px; border: 1px solid var(--border); box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); width: 100%; appearance: auto; background-color: var(--bg-surface);">
                  <option value="">Selecciona una marca...</option>
              </select>
          </div>
          <div>
              <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 8px; display: block; letter-spacing: 0.5px;">Fecha de Inicio</label>
              <span id="public-date-text" class="public-readonly-text" style="display: none;"></span>
              <input type="date" id="note-start-date" class="inline-input" style="padding: 0.8rem 1rem; font-size: 0.95rem; border-radius: 10px; border: 1px solid var(--border); box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
          </div>
      </div>
    </div>
  </div>

  <!-- RESUMEN FINANCIERO -->
  <div class="summary-modern">
    <div class="stat-card-modern">
      <div class="stat-label">TOTAL SERVICIOS</div>
      <div class="stat-number" id="statTotalServicios">S/ 0.00</div>
      <div style="font-size:0.7rem; color:var(--text-muted)">Suma Tabla de servicios</div>
    </div>
    <div class="stat-card-modern">
      <div class="stat-label">PENDIENTE POR COBRAR</div>
      <div class="stat-number" id="statPendiente">S/ 0.00</div>
      <div style="font-size:0.7rem; color:var(--text-muted)">Cuotas con estado pendiente</div>
    </div>
    <div class="stat-card-modern stat-highlight">
      <div class="stat-label">TOTAL GENERAL + PENDIENTE</div>
      <div class="stat-number pending-number" id="statGeneral">S/ 0.00</div>
      <div style="font-size:0.7rem; color:var(--text-muted)">Servicios + Pendiente cronograma</div>
    </div>
  </div>

  <!-- TABLA 1 - SERVICIOS -->
  <div class="section-card">
    <div class="section-header">
      <h2>Servicios adicionales</h2>
      <div class="badge-soft">Item facturable</div>
    </div>
    <div class="cards-grid" id="serviciosCardsContainer">
      <!-- Aquí se renderizan dinámicamente las cards de servicios -->
    </div>
    <div class="action-bar">
      <button class="btn-primary-custom" id="agregarServicioBtn"><i class="ph ph-plus"></i> Agregar servicio</button>
    </div>
  </div>

  <!-- TABLA 2 - CRONOGRAMA DE PAGO -->
  <div class="section-card">
    <div class="section-header">
      <h2>Cronograma de pago</h2>
      <div class="badge-soft">Próximas cuotas</div>
    </div>
    <div class="cards-grid" id="cronogramaCardsContainer">
      <!-- cards de cuotas -->
    </div>
    <div class="action-bar">
      <button class="btn-primary-custom" id="agregarCuotaBtn"><i class="ph ph-plus"></i> Añadir cuota</button>
    </div>
  </div>

  <!-- MÉTODOS DE PAGO CON COPIA -->
  <div class="section-card">
    <div class="section-header">
      <h2>Métodos de pago</h2>
      <div class="badge-soft">Click para copiar</div>
    </div>
    <div style="padding: 0.5rem 1.8rem 1.8rem 1.8rem;">
      <div class="payments-wrapper" id="paymentMethodsList">
        <!-- dinámico -->
      </div>
      <div style="margin-top: 20px; font-size:0.75rem; color:var(--text-muted); border-top: 1px solid var(--border); padding-top: 14px;">
        <span>🔹 A nombre de CESAR A. MENDOZA CASTRO</span>
      </div>
    </div>
  </div>

  <!-- BOTONES DE ACCIÓN FINAL -->
  <div class="botones-finales" style="display: flex; justify-content: flex-end; gap: 16px; margin-top: 32px; margin-bottom: 40px;">
      <button class="btn btn-outline" onclick="window.location.href='index.php?module=admin&action=payment_notes'">Cancelar</button>
      <button class="btn btn-primary" id="btn-guardar-nota-total" style="padding: 12px 32px; font-size: 1rem;"><i class="ph ph-floppy-disk"></i> Guardar Nota de Pago</button>
  </div>
  </div> <!-- end payment-notes-inner -->
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const noteId = urlParams.get('id') || 'NEW';
  const isPublic = urlParams.get('view') === 'public';

  if (isPublic) {
      document.body.classList.add('public-mode');
  }

  let servicios = [];
  let cronograma = [];
  let isEditingServicio = false;
  let isEditingCuota = false;
  let editingIndexServicio = -1;
  let editingIndexCuota = -1;

  let existingNote = null;
  const rawData = urlParams.get('data'); // Fallback for old links
  const tokenData = '<?php echo $public_note_data ? htmlspecialchars($public_note_data, ENT_QUOTES) : ""; ?>';
  
  if (isPublic) {
      try {
          if (tokenData) {
              existingNote = JSON.parse(atob(tokenData));
          } else if (rawData) {
              existingNote = JSON.parse(atob(decodeURIComponent(rawData)));
          }
      } catch (e) {
          console.error("Invalid note data", e);
      }
      initWebview();
  } else {
      if (noteId !== 'NEW') {
          fetch('modules/admin/ajax_get_payment_notes.php')
              .then(res => res.json())
              .then(data => {
                  if (data.success) {
                      existingNote = data.notes.find(n => n.id === noteId);
                  }
                  initWebview();
              });
      } else {
          initWebview();
      }
  }

  function initWebview() {
      document.getElementById('note-client').addEventListener('change', function() {
      const selectedOption = this.options[this.selectedIndex];
      const brandsStr = selectedOption ? selectedOption.getAttribute('data-brands') : '';
      const companySelect = document.getElementById('note-company');
      
      companySelect.innerHTML = '<option value="">Selecciona una marca...</option>';
      
      if (brandsStr) {
          const brands = brandsStr.split('||');
          brands.forEach(brand => {
              if (brand) {
                  const opt = document.createElement('option');
                  opt.value = brand;
                  opt.textContent = brand;
                  companySelect.appendChild(opt);
              }
          });
      }
  });

  if (existingNote) {
      servicios = existingNote.servicios || [];
      cronograma = existingNote.cronograma || [];
      document.getElementById('invoice-header-info').innerHTML = `<strong>${existingNote.id}</strong> &middot; THE ROMA AGENCY CORPORACION S.A.C.`;
      
      const clientSelect = document.getElementById('note-client');
      let optionExists = Array.from(clientSelect.options).some(opt => opt.value === existingNote.client);
      if (!optionExists && existingNote.client) {
          const opt = document.createElement('option');
          opt.value = existingNote.client;
          opt.textContent = existingNote.client;
          clientSelect.appendChild(opt);
      }
      
      clientSelect.value = existingNote.client || '';
      clientSelect.dispatchEvent(new Event('change'));
      
      const companySelect = document.getElementById('note-company');
      let companyExists = Array.from(companySelect.options).some(opt => opt.value === existingNote.company);
      if (!companyExists && existingNote.company) {
          const opt = document.createElement('option');
          opt.value = existingNote.company;
          opt.textContent = existingNote.company;
          companySelect.appendChild(opt);
      }
      
      companySelect.value = existingNote.company || '';
      document.getElementById('note-start-date').value = existingNote.startDate || '';
      
      if (isPublic) {
          document.getElementById('public-banner-id').innerText = existingNote.id;
          document.getElementById('public-client-text').innerText = existingNote.client || 'Sin especificar';
          document.getElementById('public-client-text').style.display = 'block';
          document.getElementById('public-company-text').innerText = existingNote.company || 'Sin especificar';
          document.getElementById('public-company-text').style.display = 'block';
          document.getElementById('public-date-text').innerText = existingNote.startDate || 'Sin especificar';
          document.getElementById('public-date-text').style.display = 'block';
      }
  } else {
      document.getElementById('note-start-date').valueAsDate = new Date();
  }

  // Force re-render of cards and totals after loading existingNote
  renderServiciosCards();
  renderCronogramaCards();
  actualizarResumen();
  
  } // end initWebview

  const paymentMethodsData = [
    { label: "BCP SOLES", code: "19174092813024" },
    { label: "BCP DOLARES", code: "19171286876143" },
    { label: "YAPE / PLIN", code: "51 998 289 752" },
    { label: "INTERBANK", code: "898-3282259003" },
    { label: "SCOTIABANK", code: "006-0447141" }
  ];

  function totalServiciosCalc() {
    return servicios.reduce((sum, s) => sum + (s.cantidad * s.costoUnit), 0);
  }

  function totalPendienteCrono() {
    return cronograma.filter(c => {
        if (c.estado === 'pagado') return false;
        if (!c.fecha) return true;
        const today = new Date();
        today.setHours(0,0,0,0);
        const parts = c.fecha.split('-');
        const cuotaDate = new Date(parts[0], parts[1] - 1, parts[2]);
        const diffTime = cuotaDate.getTime() - today.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays > 7) {
            return false; // No activo
        }
        return true; // En proceso o Retrasado
    }).reduce((sum, c) => sum + parseFloat(c.monto || 0), 0);
  }

  function actualizarResumen() {
    const totalServ = totalServiciosCalc();
    const totalPend = totalPendienteCrono();
    let totalGeneral = totalServ + totalPend;
    
    // If no services or schedule, but existingNote has a total, keep it
    if (totalGeneral === 0 && existingNote && existingNote.total > 0 && servicios.length === 0 && cronograma.length === 0) {
        totalGeneral = parseFloat(existingNote.total);
    }
    
    document.getElementById('statTotalServicios').innerText = `S/ ${totalServ.toFixed(2)}`;
    document.getElementById('statPendiente').innerText = `S/ ${totalPend.toFixed(2)}`;
    document.getElementById('statGeneral').innerText = `S/ ${totalGeneral.toFixed(2)}`;
  }

  function renderServiciosCards() {
    const container = document.getElementById('serviciosCardsContainer');
    if (!container) return;
    container.innerHTML = '';
    
    if (servicios.length === 0 && !isEditingServicio) {
      container.innerHTML = '<div style="padding: 20px; text-align:center; color:var(--text-muted)">No hay servicios registrados</div>';
    } 

    servicios.forEach((item, idx) => {
      if (idx === editingIndexServicio) {
        const editCard = document.createElement('div');
        editCard.className = 'row-card';
        editCard.style.border = '1px solid var(--primary-color)';
        editCard.innerHTML = `
          <div class="card-info">
            <div class="info-field" style="flex:2">
              <span class="field-label">Servicio</span>
              <input type="text" id="edit-serv-name-${idx}" class="inline-input" value="${escapeHtml(item.servicio)}" style="margin-top:4px;">
            </div>
            <div class="info-field" style="flex:2">
              <span class="field-label">Descripción</span>
              <input type="text" id="edit-serv-desc-${idx}" class="inline-input" value="${escapeHtml(item.descripcion)}" style="margin-top:4px;">
            </div>
            <div class="info-field" style="flex:1; min-width: 60px;">
              <span class="field-label">Cant.</span>
              <input type="number" id="edit-serv-cant-${idx}" class="inline-input" value="${item.cantidad}" min="1" style="margin-top:4px;">
            </div>
            <div class="info-field" style="flex:1; min-width: 80px;">
              <span class="field-label">Costo U. (S/)</span>
              <input type="number" id="edit-serv-cost-${idx}" class="inline-input" value="${item.costoUnit}" step="0.01" style="margin-top:4px;">
            </div>
          </div>
          <div class="card-actions">
            <button class="btn-icon-sm" id="save-edit-serv-${idx}" style="background: var(--primary-color); color: white;"><i class="ph ph-check"></i> Guardar</button>
            <button class="btn-icon-sm" id="cancel-edit-serv-${idx}"><i class="ph ph-x"></i></button>
          </div>
        `;
        container.appendChild(editCard);

        document.getElementById(`save-edit-serv-${idx}`).addEventListener('click', () => {
           item.servicio = document.getElementById(`edit-serv-name-${idx}`).value || 'Servicio';
           item.descripcion = document.getElementById(`edit-serv-desc-${idx}`).value || '';
           item.cantidad = parseFloat(document.getElementById(`edit-serv-cant-${idx}`).value) || 1;
           item.costoUnit = parseFloat(document.getElementById(`edit-serv-cost-${idx}`).value) || 0;
           
           editingIndexServicio = -1;
           renderServiciosCards();
           actualizarResumen();
        });

        document.getElementById(`cancel-edit-serv-${idx}`).addEventListener('click', () => {
           editingIndexServicio = -1;
           renderServiciosCards();
        });
      } else {
        const totalItem = item.cantidad * item.costoUnit;
        const card = document.createElement('div');
        card.className = 'row-card serv-card-table';
        card.innerHTML = `
          <div class="serv-table-grid">
            <div class="serv-col serv-col-name">
              <span class="field-label">Servicio</span>
              <span class="field-value">${escapeHtml(item.servicio)}</span>
              ${item.descripcion ? `<span class="serv-desc">${escapeHtml(item.descripcion)}</span>` : ''}
            </div>
            <div class="serv-col serv-col-num">
              <span class="field-label">Cant.</span>
              <span class="field-value">${item.cantidad}</span>
            </div>
            <div class="serv-col serv-col-num">
              <span class="field-label">Costo Unit.</span>
              <span class="field-value">S/ ${item.costoUnit.toFixed(2)}</span>
            </div>
            <div class="serv-col serv-col-num">
              <span class="field-label">Total</span>
              <span class="field-value amount-highlight" style="font-size:1.05rem;">S/ ${totalItem.toFixed(2)}</span>
            </div>
          </div>
          <div class="card-actions">
            <button class="btn-icon-sm edit-servicio" data-idx="${idx}"><i class="ph ph-pencil"></i> Editar</button>
            <button class="btn-icon-sm delete-servicio" data-idx="${idx}"><i class="ph ph-trash"></i></button>
          </div>
        `;
        container.appendChild(card);
      }
    });

    if (isEditingServicio) {
      const editCard = document.createElement('div');
      editCard.className = 'row-card';
      editCard.style.border = '1px solid var(--primary-color)';
      editCard.innerHTML = `
        <div class="card-info">
          <div class="info-field" style="flex:2">
            <span class="field-label">Servicio</span>
            <input type="text" id="new-serv-name" class="inline-input" placeholder="Nombre del servicio" style="margin-top:4px;">
          </div>
          <div class="info-field" style="flex:2">
            <span class="field-label">Descripción</span>
            <input type="text" id="new-serv-desc" class="inline-input" placeholder="Descripción breve" style="margin-top:4px;">
          </div>
          <div class="info-field" style="flex:1; min-width: 60px;">
            <span class="field-label">Cant.</span>
            <input type="number" id="new-serv-cant" class="inline-input" value="1" min="1" style="margin-top:4px;">
          </div>
          <div class="info-field" style="flex:1; min-width: 80px;">
            <span class="field-label">Costo U. (S/)</span>
            <input type="number" id="new-serv-cost" class="inline-input" placeholder="0.00" step="0.01" style="margin-top:4px;">
          </div>
        </div>
        <div class="card-actions">
          <button class="btn-icon-sm" id="save-new-serv" style="background: var(--primary-color); color: white;"><i class="ph ph-check"></i> Guardar</button>
          <button class="btn-icon-sm" id="cancel-new-serv"><i class="ph ph-x"></i></button>
        </div>
      `;
      container.appendChild(editCard);

      document.getElementById('save-new-serv').addEventListener('click', () => {
         const name = document.getElementById('new-serv-name').value || 'Servicio sin nombre';
         const desc = document.getElementById('new-serv-desc').value || '';
         const cant = parseFloat(document.getElementById('new-serv-cant').value) || 1;
         const cost = parseFloat(document.getElementById('new-serv-cost').value) || 0;
         
         servicios.push({ servicio: name, descripcion: desc, cantidad: cant, costoUnit: cost });
         isEditingServicio = false;
         renderServiciosCards();
         actualizarResumen();
      });

      document.getElementById('cancel-new-serv').addEventListener('click', () => {
         isEditingServicio = false;
         renderServiciosCards();
      });
      
      document.getElementById('new-serv-name').focus();
    }

    document.querySelectorAll('.edit-servicio').forEach(btn => {
      btn.addEventListener('click', () => {
        editingIndexServicio = parseInt(btn.dataset.idx);
        renderServiciosCards();
      });
    });

    document.querySelectorAll('.delete-servicio').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx);
        servicios.splice(idx, 1);
        renderServiciosCards();
        actualizarResumen();
      });
    });

    actualizarResumen();
  }

  function renderCronogramaCards() {
    const container = document.getElementById('cronogramaCardsContainer');
    if (!container) return;
    container.innerHTML = '';
    
    if (cronograma.length === 0 && !isEditingCuota) {
      container.innerHTML = '<div style="padding: 20px; text-align:center; color:var(--text-muted)">No hay cuotas programadas</div>';
    } 

    cronograma.forEach((item, idx) => {
      if (idx === editingIndexCuota) {
          // ... code for editing ...
        const editCard = document.createElement('div');
        editCard.className = 'row-card';
        editCard.style.border = '1px solid var(--primary-color)';
        editCard.innerHTML = `
          <div class="card-info">
            <div class="info-field" style="flex:2">
              <span class="field-label">Concepto</span>
              <input type="text" id="edit-crono-name-${idx}" class="inline-input" value="${escapeHtml(item.servicio)}" style="margin-top:4px;">
            </div>
            <div class="info-field" style="flex:1; min-width: 80px;">
              <span class="field-label">Monto (S/)</span>
              <input type="number" id="edit-crono-cost-${idx}" class="inline-input" value="${item.monto}" step="0.01" style="margin-top:4px;">
            </div>
            <div class="info-field" style="flex:1; min-width: 120px;">
              <span class="field-label">Fecha</span>
              <input type="date" id="edit-crono-date-${idx}" class="inline-input" value="${item.fecha}" style="margin-top:4px;">
            </div>
            <div class="info-field" style="flex:1;">
              <span class="field-label">Estado</span>
              <select id="edit-crono-status-${idx}" class="inline-input" style="margin-top:4px;">
                  <option value="pendiente" ${item.estado === 'pendiente' ? 'selected' : ''}>Pendiente</option>
                  <option value="pagado" ${item.estado === 'pagado' ? 'selected' : ''}>Pagado</option>
              </select>
            </div>
          </div>
          <div class="card-actions">
            <button class="btn-icon-sm" id="save-edit-crono-${idx}" style="background: var(--primary-color); color: white;"><i class="ph ph-check"></i> Guardar</button>
            <button class="btn-icon-sm" id="cancel-edit-crono-${idx}"><i class="ph ph-x"></i></button>
          </div>
        `;
        container.appendChild(editCard);

        document.getElementById(`save-edit-crono-${idx}`).addEventListener('click', () => {
           item.servicio = document.getElementById(`edit-crono-name-${idx}`).value || 'Cuota';
           item.monto = parseFloat(document.getElementById(`edit-crono-cost-${idx}`).value) || 0;
           item.fecha = document.getElementById(`edit-crono-date-${idx}`).value;
           item.estado = document.getElementById(`edit-crono-status-${idx}`).value;
           
           editingIndexCuota = -1;
           renderCronogramaCards();
           actualizarResumen();
        });

        document.getElementById(`cancel-edit-crono-${idx}`).addEventListener('click', () => {
           editingIndexCuota = -1;
           renderCronogramaCards();
        });
      } else {
        const card = document.createElement('div');
        card.className = 'row-card';
        if (item.estado === 'pagado') {
            card.classList.add('is-paid');
        }

        let statusInfo = { text: 'En proceso', class: 'status-pending', bg: 'rgba(245, 158, 11, 0.15)', color: 'var(--warning-color)' };
        if (item.estado === 'pagado') {
            statusInfo = { text: 'Pagado', class: 'status-paid', bg: 'var(--paid-bg)', color: 'var(--paid-text)' };
        } else {
            if (item.fecha) {
                const today = new Date();
                today.setHours(0,0,0,0);
                const parts = item.fecha.split('-');
                const cuotaDate = new Date(parts[0], parts[1] - 1, parts[2]);
                const diffTime = cuotaDate.getTime() - today.getTime();
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays < -15) {
                    statusInfo = { text: 'Retrasado', class: 'status-delayed', bg: 'rgba(239, 68, 68, 0.15)', color: '#ef4444' };
                } else if (diffDays <= 7 && diffDays >= -15) {
                    statusInfo = { text: 'En proceso', class: 'status-pending', bg: 'rgba(245, 158, 11, 0.15)', color: 'var(--warning-color)' };
                } else {
                    statusInfo = { text: 'No Activo', class: 'status-inactive', bg: 'rgba(100, 116, 139, 0.15)', color: '#64748b' };
                }
            }
        }

        card.innerHTML = `
          <div class="card-info">
            <div class="info-field"><span class="field-label">Concepto</span><span class="field-value">${escapeHtml(item.servicio)}</span></div>
            <div class="info-field"><span class="field-label">Monto</span><span class="field-value amount-highlight">S/ ${parseFloat(item.monto).toFixed(2)}</span></div>
            <div class="info-field"><span class="field-label">Fecha</span><span class="field-value">${item.fecha}</span></div>
            <div class="info-field"><span class="field-label">Estado</span><span class="status-pill ${statusInfo.class}" style="background: ${statusInfo.bg}; color: ${statusInfo.color}; border: 0.5px solid ${statusInfo.color};">${statusInfo.text}</span></div>
          </div>
          <div class="card-actions">
            <button class="btn-icon-sm edit-crono" data-idx="${idx}"><i class="ph ph-pencil"></i> Editar</button>
            <button class="btn-icon-sm toggle-estado" data-idx="${idx}"><i class="ph ph-arrows-left-right"></i> ${item.estado === 'pendiente' ? 'Marcar pagado' : 'Marcar pendiente'}</button>
            <button class="btn-icon-sm delete-crono" data-idx="${idx}"><i class="ph ph-trash"></i> Eliminar</button>
          </div>
        `;
        container.appendChild(card);
      }
    });

    if (isEditingCuota) {
      const editCard = document.createElement('div');
      editCard.className = 'row-card';
      editCard.style.border = '1px solid var(--primary-color)';
      editCard.innerHTML = `
        <div class="card-info">
          <div class="info-field" style="flex:2">
            <span class="field-label">Concepto</span>
            <input type="text" id="new-crono-name" class="inline-input" placeholder="Ej. Cuota 1" style="margin-top:4px;">
          </div>
          <div class="info-field" style="flex:1; min-width: 80px;">
            <span class="field-label">Monto (S/)</span>
            <input type="number" id="new-crono-cost" class="inline-input" placeholder="0.00" step="0.01" style="margin-top:4px;">
          </div>
          <div class="info-field" style="flex:1; min-width: 120px;">
            <span class="field-label">Fecha</span>
            <input type="date" id="new-crono-date" class="inline-input" style="margin-top:4px;">
          </div>
          <div class="info-field" style="flex:1;">
            <span class="field-label">Estado</span>
            <select id="new-crono-status" class="inline-input" style="margin-top:4px;">
                <option value="pendiente">Pendiente</option>
                <option value="pagado">Pagado</option>
            </select>
          </div>
        </div>
        <div class="card-actions">
          <button class="btn-icon-sm" id="save-new-crono" style="background: var(--primary-color); color: white;"><i class="ph ph-check"></i> Guardar</button>
          <button class="btn-icon-sm" id="cancel-new-crono"><i class="ph ph-x"></i></button>
        </div>
      `;
      container.appendChild(editCard);

      document.getElementById('new-crono-date').valueAsDate = new Date();

      document.getElementById('save-new-crono').addEventListener('click', () => {
         const name = document.getElementById('new-crono-name').value || 'Cuota';
         const cost = parseFloat(document.getElementById('new-crono-cost').value) || 0;
         const date = document.getElementById('new-crono-date').value;
         const status = document.getElementById('new-crono-status').value;
         
         cronograma.push({ servicio: name, monto: cost, fecha: date, estado: status });
         isEditingCuota = false;
         renderCronogramaCards();
         actualizarResumen();
      });

      document.getElementById('cancel-new-crono').addEventListener('click', () => {
         isEditingCuota = false;
         renderCronogramaCards();
      });
      
      document.getElementById('new-crono-name').focus();
    }

    document.querySelectorAll('.edit-crono').forEach(btn => {
      btn.addEventListener('click', () => {
        editingIndexCuota = parseInt(btn.dataset.idx);
        renderCronogramaCards();
      });
    });

    document.querySelectorAll('.toggle-estado').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx);
        cronograma[idx].estado = cronograma[idx].estado === 'pendiente' ? 'pagado' : 'pendiente';
        renderCronogramaCards();
        actualizarResumen();
      });
    });

    document.querySelectorAll('.delete-crono').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx);
        cronograma.splice(idx, 1);
        renderCronogramaCards();
        actualizarResumen();
      });
    });

    actualizarResumen();
  }

  document.getElementById('agregarServicioBtn')?.addEventListener('click', () => {
      isEditingServicio = true;
      renderServiciosCards();
  });

  document.getElementById('agregarCuotaBtn')?.addEventListener('click', () => {
      isEditingCuota = true;
      renderCronogramaCards();
  });

  function initPaymentCopy() {
    const container = document.getElementById('paymentMethodsList');
    if (!container) return;
    container.innerHTML = '';
    paymentMethodsData.forEach(method => {
      const pill = document.createElement('div');
      pill.className = 'payment-item';
      pill.innerHTML = `
        <span><strong>${method.label}</strong></span>
        <span class="payment-code">${method.code}</span>
        <span class="copy-icon"><i class="ph ph-copy"></i></span>
      `;
      pill.addEventListener('click', () => {
        navigator.clipboard.writeText(method.code).then(() => {
          showToast(`Copiado: ${method.code}`);
        }).catch(() => alert("No se pudo copiar"));
      });
      container.appendChild(pill);
    });
  }

  function showToast(msg) {
    let existing = document.querySelector('.toast-msg');
    if(existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'toast-msg';
    toast.innerText = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2000);
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
      if (m === '&') return '&amp;';
      if (m === '<') return '&lt;';
      if (m === '>') return '&gt;';
      return m;
    });
  }

  renderServiciosCards();
  renderCronogramaCards();
  initPaymentCopy();

  document.getElementById('btn-guardar-nota-total').addEventListener('click', () => {
      // Auto-save any open inline form
      if (isEditingServicio) {
          const name = document.getElementById('new-serv-name')?.value;
          if (name) {
              const desc = document.getElementById('new-serv-desc')?.value || '';
              const cant = parseFloat(document.getElementById('new-serv-cant')?.value) || 1;
              const cost = parseFloat(document.getElementById('new-serv-cost')?.value) || 0;
              servicios.push({ servicio: name, descripcion: desc, cantidad: cant, costoUnit: cost });
          }
          isEditingServicio = false;
      }
      if (editingIndexServicio !== -1) {
          const idx = editingIndexServicio;
          const name = document.getElementById(`edit-serv-name-${idx}`)?.value;
          if (name) {
              servicios[idx].servicio = name;
              servicios[idx].descripcion = document.getElementById(`edit-serv-desc-${idx}`)?.value || '';
              servicios[idx].cantidad = parseFloat(document.getElementById(`edit-serv-cant-${idx}`)?.value) || 1;
              servicios[idx].costoUnit = parseFloat(document.getElementById(`edit-serv-cost-${idx}`)?.value) || 0;
          }
          editingIndexServicio = -1;
      }
      if (isEditingCuota) {
          const name = document.getElementById('new-crono-name')?.value;
          if (name) {
              const cost = parseFloat(document.getElementById('new-crono-cost')?.value) || 0;
              const date = document.getElementById('new-crono-date')?.value || '';
              const status = document.getElementById('new-crono-status')?.value || 'pendiente';
              cronograma.push({ servicio: name, monto: cost, fecha: date, estado: status });
          }
          isEditingCuota = false;
      }
      if (editingIndexCuota !== -1) {
          const idx = editingIndexCuota;
          const name = document.getElementById(`edit-crono-name-${idx}`)?.value;
          if (name) {
              cronograma[idx].servicio = name;
              cronograma[idx].monto = parseFloat(document.getElementById(`edit-crono-cost-${idx}`)?.value) || 0;
              cronograma[idx].fecha = document.getElementById(`edit-crono-date-${idx}`)?.value || '';
              cronograma[idx].estado = document.getElementById(`edit-crono-status-${idx}`)?.value || 'pendiente';
          }
          editingIndexCuota = -1;
      }

      let client = document.getElementById('note-client')?.value || 'Cliente sin nombre';
      let company = document.getElementById('note-company')?.value || 'Sin empresa';
      let startDate = document.getElementById('note-start-date')?.value || '';
      
      let totalGeneral = totalServiciosCalc() + totalPendienteCrono();
      if (totalGeneral === 0 && existingNote && existingNote.total > 0 && servicios.length === 0 && cronograma.length === 0) {
          totalGeneral = parseFloat(existingNote.total);
      }
      
      const hasPending = cronograma.some(c => c.estado === 'pendiente') || cronograma.length === 0;
      const status = hasPending ? 'PENDIENTE' : 'PAGADO';
      
      let noteToSave = {};

      if (!existingNote) {
          // Fetch the latest ID from the server or just use a placeholder to let the server generate it?
          // Actually, our PHP expects `note_code` (which is `id`).
          // We can generate one here. We'll fetch the highest ID next time, but for now let's just generate a random one or timestamp-based if we don't have currentNotes.
          const dateObj = new Date();
          const newId = 'ID-' + dateObj.getFullYear() + '-' + Math.floor(Math.random() * 10000);
          
          const formattedDate = String(dateObj.getDate()).padStart(2, '0') + '/' + String(dateObj.getMonth() + 1).padStart(2, '0') + '/' + dateObj.getFullYear();
          
          noteToSave = {
              id: newId,
              client: client,
              company: company,
              startDate: startDate,
              total: totalGeneral,
              date: formattedDate,
              status: status,
              servicios: servicios,
              cronograma: cronograma
          };
      } else {
          noteToSave = existingNote;
          noteToSave.client = client;
          noteToSave.company = company;
          noteToSave.startDate = startDate;
          noteToSave.total = totalGeneral;
          noteToSave.status = status;
          noteToSave.servicios = servicios;
          noteToSave.cronograma = cronograma;
      }
      
      const btn = document.getElementById('btn-guardar-nota-total');
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
      btn.style.opacity = '0.8';
      btn.disabled = true;

      fetch('modules/admin/ajax_save_payment_note.php', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/json'
          },
          body: JSON.stringify(noteToSave)
      })
      .then(res => res.json())
      .then(data => {
          if (data.success) {
              showToast('Nota de Pago guardada correctamente');
              btn.innerHTML = '<i class="ph ph-check"></i> Guardado';
              btn.style.backgroundColor = 'var(--secondary-color)';
              btn.style.borderColor = 'var(--secondary-color)';
              btn.style.opacity = '1';
              
              setTimeout(() => {
                  window.location.href = 'index.php?module=admin&action=payment_notes';
              }, 1200);
          } else {
              alert('Error al guardar: ' + data.error);
              btn.innerHTML = originalText;
              btn.disabled = false;
              btn.style.opacity = '1';
          }
      })
      .catch(e => {
          console.error(e);
          alert('Error de conexión');
          btn.innerHTML = originalText;
          btn.disabled = false;
          btn.style.opacity = '1';
      });
  });
});
</script>

<?php require_once 'includes/footer.php'; ?>
