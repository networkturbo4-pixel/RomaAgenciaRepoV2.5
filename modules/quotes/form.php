<?php
// modules/quotes/form.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quote = null;
$quote_items = [];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM quotes WHERE id = ?");
    $stmt->execute([$id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($quote) {
        $stmtItems = $db->prepare("SELECT * FROM quote_items WHERE quote_id = ?");
        $stmtItems->execute([$id]);
        $quote_items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Fetch lists
$clients = $db->query("SELECT id, name FROM clients ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$services = $db->query("SELECT id, name, price FROM services ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/frappe-gantt/0.6.1/frappe-gantt.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/frappe-gantt/0.6.1/frappe-gantt.min.js"></script>
<style>
    .quote-form-container {
        font-family: 'Inter', sans-serif;
        color: var(--color-title);
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
        margin-bottom: 1.5rem;
    }
    .card-glass {
        background: var(--bg-surface);
        border-radius: 16px;
        padding: 1.75rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        border: 1px solid var(--border-color);
        margin-bottom: 1.5rem;
    }
    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: var(--color-title);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.55rem 1.1rem;
        font-size: 13px;
        font-weight: 600;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        text-decoration: none;
        border: none;
    }
    .btn-primary {
        background: var(--primary-color);
        color: #ffffff;
        box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color) 30%, transparent);
    }
    .btn-primary:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
        box-shadow: 0 6px 16px color-mix(in srgb, var(--primary-color) 40%, transparent);
    }
    .btn-outline {
        background: transparent;
        color: var(--text-main);
        border: 1px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        color: var(--text-main);
    }
    
    /* New Item Card styles */
    .item-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1.25rem;
        margin-bottom: 1rem;
        position: relative;
    }
    .item-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    .item-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .item-editor-container {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        margin-bottom: 1rem;
        overflow: hidden;
    }
    .item-editor-toolbar {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.5rem;
        display: flex;
        gap: 0.5rem;
    }
    .item-editor-toolbar button {
        background: none;
        border: none;
        color: #64748b;
        cursor: pointer;
        padding: 0.4rem;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    .item-editor-toolbar button:hover {
        background: #e2e8f0;
        color: #0f172a;
    }
    .item-textarea {
        width: 100%;
        border: none;
        padding: 0.75rem;
        font-family: inherit;
        font-size: 0.9rem;
        resize: vertical;
        min-height: 80px;
        color: #334155;
    }
    .item-textarea:focus {
        outline: none;
    }
    .item-textarea ul, .item-textarea ol {
        padding-left: 1.5rem;
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
    }
    .item-textarea li {
        margin-bottom: 0.25rem;
    }
    .item-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
    }
    .item-col {
        flex: 1;
        min-width: 100px;
    }
    .item-col.small {
        max-width: 120px;
    }
    .item-input {
        width: 100%;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 0.5rem;
        font-size: 0.9rem;
        color: #334155;
    }
    .item-input:focus {
        border-color: var(--primary-color);
        outline: none;
    }
    .item-total-display {
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
    }
    .btn-remove-item {
        color: #ef4444;
        background: none;
        border: none;
        font-size: 1.25rem;
        cursor: pointer;
        padding: 0.25rem 0.5rem;
        margin-top: 1rem;
    }
    .btn-remove-item:hover {
        color: #b91c1c;
    }
    .item-gantt-section {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px dashed #e2e8f0;
        display: flex;
        gap: 1rem;
    }
    .item-gantt-col {
        flex: 1;
    }
    .add-partida-btn {
        color: var(--primary-color);
        background: none;
        border: none;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0;
        cursor: pointer;
    }
    .add-partida-btn:hover {
        opacity: 0.8;
    }
    
    .bank-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1rem;
    }
    .bank-card {
        display: flex;
        align-items: center;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        background: #fff;
    }
    .bank-icon {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        color: white;
        margin-right: 1rem;
        font-size: 0.85rem;
    }
    .bank-icon.bcp { background: #ff7800; }
    .bank-icon.yape { background: #742284; }
    .bank-icon.ibk { background: #007a33; }
    .bank-icon.sco { background: #ed1c24; }
    
    .bank-details {
        flex: 1;
    }
    .bank-name {
        font-size: 0.7rem;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 0.2rem;
    }
    .bank-account {
        font-size: 1.1rem;
        color: #0f172a;
        font-weight: 800;
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        padding: 0;
    }

    .totals-container {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }
    .calc-value {
        color: #334155;
    }
    .totals-divider {
        background: #cbd5e1;
    }

    /* ========== DARK MODE OVERRIDES ========== */
    [data-theme="dark"] .item-card {
        background: var(--bg-surface);
        border-color: var(--border-color);
    }
    [data-theme="dark"] .item-editor-container {
        border-color: var(--border-color);
    }
    [data-theme="dark"] .item-editor-toolbar {
        background: #0a0a0a;
        border-bottom-color: var(--border-color);
    }
    [data-theme="dark"] .item-editor-toolbar button {
        color: #94a3b8;
    }
    [data-theme="dark"] .item-editor-toolbar button:hover {
        background: #262626;
        color: #f1f5f9;
    }
    [data-theme="dark"] .item-editor-toolbar select {
        background: #0a0a0a;
        color: #94a3b8;
        border-color: var(--border-color);
    }
    [data-theme="dark"] .item-textarea {
        color: #e2e8f0;
        background: var(--bg-surface);
    }
    [data-theme="dark"] .item-textarea::placeholder {
        color: #475569;
    }
    [data-theme="dark"] .item-input {
        background: #0a0a0a;
        border-color: var(--border-color);
        color: #e2e8f0;
    }
    [data-theme="dark"] .item-input:focus {
        border-color: var(--primary-color);
    }
    [data-theme="dark"] .item-label {
        color: #64748b;
    }
    [data-theme="dark"] .item-total-display {
        color: #f1f5f9;
    }
    [data-theme="dark"] .item-gantt-section {
        border-top-color: #262626;
    }

    /* Bank cards */
    [data-theme="dark"] .bank-card {
        background: var(--bg-surface);
        border-color: var(--border-color);
    }
    [data-theme="dark"] .bank-name {
        color: #94a3b8;
    }
    [data-theme="dark"] .bank-account {
        color: #f1f5f9;
    }

    /* Totals section */
    [data-theme="dark"] .quote-form-container .fw-bold {
        color: #f1f5f9;
    }
    [data-theme="dark"] .quote-form-container .text-dark {
        color: #e2e8f0 !important;
    }

    /* Totals container dark mode */
    [data-theme="dark"] .totals-container {
        background: #0a0a0a;
        border-color: var(--border-color);
    }
    [data-theme="dark"] .calc-value {
        color: #e2e8f0;
    }
    [data-theme="dark"] .totals-divider {
        background: #262626;
    }

    /* Payment methods toggle area */
    [data-theme="dark"] .form-check-label {
        color: #e2e8f0;
    }

    /* Gantt empty state */
    [data-theme="dark"] #gantt_empty_state {
        color: #475569;
    }

    /* Contenteditable areas */
    [data-theme="dark"] [contenteditable] {
        color: #e2e8f0;
    }

    /* Select dropdowns */
    [data-theme="dark"] .form-select,
    [data-theme="dark"] .form-control,
    [data-theme="dark"] select,
    [data-theme="dark"] .quote-form-container select {
        background: #0a0a0a;
        color: #e2e8f0;
        border-color: var(--border-color);
    }

    /* Textarea for notes */
    [data-theme="dark"] textarea.item-input,
    [data-theme="dark"] textarea.item-textarea {
        background: #0a0a0a;
        color: #e2e8f0;
        border-color: var(--border-color);
    }

    /* Add partida button area */
    [data-theme="dark"] .add-partida-btn {
        color: var(--primary-color);
    }

    /* Inline text inputs (bank owner, etc) */
    [data-theme="dark"] input[style*="background:transparent"] {
        color: #94a3b8 !important;
    }

    /* Item inner containers (montos & gantt sub-cards) */
    .item-amounts-card,
    .item-gantt-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1rem;
    }
    [data-theme="dark"] .item-amounts-card,
    [data-theme="dark"] .item-gantt-card {
        background: #0a0a0a;
        border-color: var(--border-color);
    }

    /* Editor toolbar font-size select */
    .editor-font-select {
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 0.15rem 0.25rem;
        font-size: 0.85rem;
        color: #475569;
        background: #fff;
        cursor: pointer;
        outline: none;
    }
    [data-theme="dark"] .editor-font-select {
        background: #0a0a0a;
        color: #94a3b8;
        border-color: var(--border-color);
    }

    /* Editor toolbar divider */
    .editor-divider {
        width: 1px;
        height: 1.25rem;
        background: #cbd5e1;
        margin: 0 0.25rem;
    }
    [data-theme="dark"] .editor-divider {
        background: #475569;
    }

    /* Item total display (in JS-rendered cards) */
    .item-total-display {
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
    }
    [data-theme="dark"] .item-total-display {
        color: #f1f5f9;
    }

    /* Bank owner text */
    .bank-owner-input {
        border: none;
        outline: none;
        background: transparent;
        font-weight: 600;
        color: #64748b;
        width: 300px;
    }
    [data-theme="dark"] .bank-owner-input {
        color: #94a3b8;
    }
    [data-theme="dark"] .bank-owner-label {
        color: #64748b;
    }

    /* Gantt chart dark mode */
    [data-theme="dark"] #gantt_here .gantt .grid-header {
        fill: var(--bg-surface);
    }
    [data-theme="dark"] #gantt_here .gantt .grid-row {
        fill: var(--bg-surface);
    }
    [data-theme="dark"] #gantt_here .gantt .grid-row:nth-child(even) {
        fill: #262626;
    }
    [data-theme="dark"] #gantt_here .gantt .lower-text,
    [data-theme="dark"] #gantt_here .gantt .upper-text {
        fill: #94a3b8;
    }
    [data-theme="dark"] #gantt_here .gantt .row-line,
    [data-theme="dark"] #gantt_here .gantt .tick {
        stroke: #262626;
    }
    [data-theme="dark"] #gantt_here .gantt .today-highlight {
        fill: rgba(99, 102, 241, 0.15);
    }
</style>

<div class="quote-form-container">
    <div class="floating-toolbar">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="ph ph-file-text" style="font-size: 1.5rem; color: var(--primary-color);"></i>
            <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700;"><?php echo $id ? 'Editar Cotización #' . str_pad($id, 4, '0', STR_PAD_LEFT) : 'Nueva Cotización'; ?></h2>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="index.php?module=quotes&action=index" class="btn btn-outline"><i class="ph ph-arrow-left"></i> Volver</a>
            <button id="btnSaveQuote" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Guardar Cotización</button>
        </div>
    </div>

    <form id="quoteForm">
        <input type="hidden" id="quote_id" value="<?php echo $id; ?>">
        
        <div class="row">
            <div class="col-12">
                <div class="card-glass">
                    <div class="section-title"><i class="ph ph-info"></i> Datos Generales</div>
                    
                    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                        <!-- Fila 1: Cliente -->
                        <div>
                            <label class="form-label item-label">CLIENTE * <span style="text-transform:none; font-weight:normal; color:#94a3b8;">(Seleccione o escriba uno nuevo)</span></label>
                            <input type="text" id="client_name" class="item-input" list="clientsList" value="<?php echo $quote ? htmlspecialchars(current(array_filter($clients, function($c) use($quote) { return $c['id'] == $quote['client_id']; }))['name'] ?? '') : ''; ?>" placeholder="Escribir nombre del cliente..." required>
                            <datalist id="clientsList">
                                <?php foreach($clients as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['name']); ?>" data-id="<?php echo $c['id']; ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <!-- Fila 2: Moneda y Estado -->
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <label class="form-label item-label">MONEDA</label>
                                <select id="currency" class="item-input">
                                    <option value="USD" <?php echo ($quote && $quote['currency'] === 'USD') ? 'selected' : ''; ?>>USD ($)</option>
                                    <option value="PEN" <?php echo ($quote && $quote['currency'] === 'PEN') ? 'selected' : ''; ?>>PEN (S/)</option>
                                </select>
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <label class="form-label item-label">ESTADO</label>
                                <select id="status" class="item-input">
                                    <option value="Borrador" <?php echo ($quote && $quote['status'] === 'Borrador') ? 'selected' : ''; ?>>Borrador</option>
                                    <option value="Enviada" <?php echo ($quote && $quote['status'] === 'Enviada') ? 'selected' : ''; ?>>Enviada</option>
                                    <option value="Aceptada" <?php echo ($quote && $quote['status'] === 'Aceptada') ? 'selected' : ''; ?>>Aceptada</option>
                                    <option value="Rechazada" <?php echo ($quote && $quote['status'] === 'Rechazada') ? 'selected' : ''; ?>>Rechazada</option>
                                </select>
                            </div>
                        </div>

                        <!-- Fila 3: Fechas -->
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <label class="form-label item-label">FECHA DE EMISIÓN</label>
                                <input type="date" id="issue_date" class="item-input" value="<?php echo $quote ? $quote['issue_date'] : date('Y-m-d'); ?>">
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <label class="form-label item-label">FECHA DE VENCIMIENTO</label>
                                <input type="date" id="due_date" class="item-input" value="<?php echo $quote ? $quote['due_date'] : date('Y-m-d', strtotime('+15 days')); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-glass">
                    <div class="section-title"><i class="ph ph-list-numbers"></i> Detalles de Servicios (Partidas)</div>
                    
                    <div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center;">
                        <select id="serviceSelector" class="item-input" style="max-width: 350px; flex: 1;">
                            <option value="">Importar desde catálogo...</option>
                            <?php foreach($services as $s): ?>
                                <option value="<?php echo $s['id']; ?>" data-price="<?php echo $s['price']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-primary" onclick="addServiceFromCatalog()" style="border-radius: 8px; padding: 0.5rem 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="ph ph-download-simple"></i> Importar
                        </button>
                    </div>

                    <div id="itemsContainer">
                        <!-- JS populated items -->
                    </div>

                    <button type="button" class="add-partida-btn" onclick="addEmptyRow()" style="width: 100%; justify-content: center; border: 2px dashed var(--border-color); border-radius: 8px; padding: 1rem; color: var(--text-muted); margin-bottom: 1.5rem; transition: all 0.2s ease;">
                        <i class="ph ph-plus-circle" style="font-size: 1.25rem;"></i> Añadir Nueva Partida
                    </button>

                    <div class="totals-container" style="border-radius: 12px; padding: 1.5rem;">
                        <div style="display: flex; flex-direction: column; gap: 1rem; align-items: flex-end;">
                            <div style="display: flex; justify-content: space-between; width: 100%; max-width: 350px; align-items: center;">
                                <span class="item-label text-dark m-0">SUBTOTAL:</span>
                                <span id="calcSubtotal" class="fw-bold calc-value" style="font-size: 1.15rem;">0.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; width: 100%; max-width: 350px; align-items: center;">
                                <span class="item-label text-dark d-flex align-items-center gap-2 m-0">
                                    IGV/TAX (%): 
                                    <input type="number" id="tax_rate" class="item-input" style="width: 80px; padding: 0.25rem 0.5rem; text-align: center;" value="<?php echo $quote ? ($quote['subtotal'] > 0 ? (int)(($quote['tax']/$quote['subtotal'])*100) : 0) : 0; ?>" onchange="calculateTotals()">
                                </span>
                                <span id="calcTax" class="fw-bold calc-value" style="font-size: 1.15rem;">0.00</span>
                            </div>
                            <div class="totals-divider" style="width: 100%; max-width: 350px; height: 1px;"></div>
                            <div style="display: flex; justify-content: space-between; width: 100%; max-width: 350px; align-items: center;">
                                <span class="item-label text-dark m-0" style="font-size: 0.9rem;">TOTAL:</span>
                                <span id="calcTotal" class="fw-bold text-primary" style="font-size: 1.75rem;">0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-4">
                <!-- Gantt Section - always visible -->
                <div class="card-glass" id="ganttSectionContainer">
                    <div class="section-title"><i class="ph ph-calendar"></i> 3. Cronograma del Proyecto</div>
                    <p class="text-muted" style="font-size: 0.9rem;">Asigna fechas de inicio y duración en días a cada servicio para generar el diagrama de Gantt.</p>
                    <div id="gantt_here" style="width: 100%; overflow-x: auto;"></div>
                    <div id="gantt_empty_state" style="text-align: center; padding: 3rem; color: #94a3b8;">
                        <i class="ph ph-chart-bar" style="font-size: 2rem;"></i>
                        <p class="mt-2">Agrega servicios con fechas para ver el diagrama</p>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-4">
                <div class="card-glass">
                    <div class="section-title"><i class="ph ph-note-pencil"></i> Notas y Condiciones</div>
                    
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                        <div class="mb-4" style="flex: 1; min-width: 300px;">
                            <label class="form-label item-label">NOTAS ADICIONALES</label>
                            <textarea id="notes" class="item-input item-textarea" rows="4" placeholder="Notas visibles para el cliente..."><?php echo $quote ? htmlspecialchars($quote['notes']) : ''; ?></textarea>
                        </div>

                        <div class="mb-4" style="flex: 1; min-width: 300px;">
                            <label class="form-label item-label">TÉRMINOS Y CONDICIONES</label>
                            <textarea id="terms_conditions" class="item-input item-textarea" rows="4" placeholder="Ej: Válido por 15 días..."><?php echo $quote ? htmlspecialchars($quote['terms_conditions']) : "1. La presente cotización tiene una validez de 15 días.\n2. Para iniciar el proyecto se requiere un abono del 50% y el saldo contra entrega.\n3. Los tiempos de entrega corren a partir de la recepción de todo el material necesario."; ?></textarea>
                        </div>
                    </div>

                    <div class="totals-container" style="border-radius: 8px; padding: 1rem; margin-top: 2.5rem; margin-bottom: 2.5rem;">
                        <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" id="show_payment_methods" <?php echo ($quote && $quote['show_payment_methods']) ? 'checked' : ''; ?> style="width: 2.5rem; height: 1.25rem;">
                            <label class="form-check-label item-label m-0 text-dark" for="show_payment_methods" style="cursor: pointer;">Mostrar Métodos de Pago</label>
                        </div>
                    </div>

                    <div class="mb-3" id="payment_methods_container" style="display: <?php echo ($quote && $quote['show_payment_methods']) ? 'block' : 'none'; ?>">
                        <div class="bank-grid" id="payment_methods_grid">
                            <!-- BCP SOLES -->
                            <div class="bank-card">
                                <div class="bank-icon bcp">BCP</div>
                                <div class="bank-details">
                                    <div class="bank-name">BCP SOLES</div>
                                    <input type="text" class="bank-account" value="191-74092813-0-24">
                                </div>
                            </div>
                            <!-- BCP DOLARES -->
                            <div class="bank-card">
                                <div class="bank-icon bcp">BCP</div>
                                <div class="bank-details">
                                    <div class="bank-name">BCP DÓLARES</div>
                                    <input type="text" class="bank-account" value="191-71286876-1-43">
                                </div>
                            </div>
                            <!-- YAPE -->
                            <div class="bank-card">
                                <div class="bank-icon yape">YAPE</div>
                                <div class="bank-details">
                                    <div class="bank-name">YAPE / PLIN</div>
                                    <input type="text" class="bank-account" value="51 998 289 752">
                                </div>
                            </div>
                            <!-- INTERBANK -->
                            <div class="bank-card">
                                <div class="bank-icon ibk">IBK</div>
                                <div class="bank-details">
                                    <div class="bank-name">INTERBANK</div>
                                    <input type="text" class="bank-account" value="898-3282259003">
                                </div>
                            </div>
                            <!-- SCOTIABANK -->
                            <div class="bank-card">
                                <div class="bank-icon sco">SCO</div>
                                <div class="bank-details">
                                    <div class="bank-name">SCOTIABANK</div>
                                    <input type="text" class="bank-account" value="006-0447141">
                                </div>
                            </div>
                        </div>
                        <div class="bank-owner-label" style="margin-top: 1rem; font-size: 0.85rem; padding-left: 0.5rem; color: #94a3b8;">
                            A nombre de <input type="text" id="bank_owner" value="Cesar A. Mendoza Castro" class="bank-owner-input">
                        </div>
                        <textarea id="payment_methods_text" style="display:none;"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </form>


</div>

<script>
let ganttChart = null;
let itemsData = <?php echo json_encode($quote_items); ?>;
if(itemsData.length === 0) itemsData = [];

document.getElementById('show_payment_methods').addEventListener('change', function() {
    document.getElementById('payment_methods_container').style.display = this.checked ? 'block' : 'none';
});

const iconsList = [
    {val: '', text: 'Sin ícono'},
    {val: 'ph-code', text: 'Código / Desarrollo'},
    {val: 'ph-megaphone', text: 'Marketing'},
    {val: 'ph-paint-brush', text: 'Diseño'},
    {val: 'ph-video-camera', text: 'Audiovisual'},
    {val: 'ph-device-mobile', text: 'Móvil'},
    {val: 'ph-chart-line-up', text: 'SEO / Ads'},
    {val: 'ph-database', text: 'Servidores'},
];

function generateIconSelect(selectedValue) {
    let html = `<select class="form-select form-select-sm item-icon" style="width: auto;" onchange="syncData()">`;
    iconsList.forEach(ic => {
        const sel = (ic.val === selectedValue) ? 'selected' : '';
        html += `<option value="${ic.val}" ${sel}>${ic.text}</option>`;
    });
    html += `</select>`;
    return html;
}

function getCurrencySymbol() {
    return document.getElementById('currency').value === 'PEN' ? 'S/' : '$';
}

function toggleHighlight() {
    let color = document.queryCommandValue('backColor');
    if (color && color !== 'transparent' && color !== 'rgba(0, 0, 0, 0)' && color !== 'rgb(255, 255, 255)') {
        document.execCommand('hiliteColor', false, 'transparent');
        document.execCommand('backColor', false, 'transparent');
    } else {
        document.execCommand('hiliteColor', false, '#fef08a');
        document.execCommand('backColor', false, '#fef08a');
    }
}

document.getElementById('currency').addEventListener('change', function() {
    renderItems();
});

function renderGantt() {
    if (typeof Gantt === 'undefined') return;
    try {
        const ganttColors = [
            { bg: '#3b82f6', bgLight: '#93bbfd' },
            { bg: '#8b5cf6', bgLight: '#c4b5fd' },
            { bg: '#06b6d4', bgLight: '#67e8f9' },
            { bg: '#f59e0b', bgLight: '#fcd34d' },
            { bg: '#10b981', bgLight: '#6ee7b7' },
            { bg: '#ef4444', bgLight: '#fca5a5' },
            { bg: '#ec4899', bgLight: '#f9a8d4' },
            { bg: '#6366f1', bgLight: '#a5b4fc' },
            { bg: '#14b8a6', bgLight: '#5eead4' },
            { bg: '#f97316', bgLight: '#fdba74' },
        ];
        const tasks = [];
        itemsData.forEach((item, index) => {
            if (item.gantt_start_date && parseInt(item.gantt_duration) > 0) {
                let div = document.createElement('div');
                div.innerHTML = item.description;
                let text = div.textContent || div.innerText || 'Tarea ' + (index + 1);
                text = text.substring(0, 50).trim() || 'Tarea ' + (index + 1);
                
                let startDate = new Date(item.gantt_start_date + 'T00:00:00');
                if (isNaN(startDate.getTime())) return; // Salta fechas inválidas
                
                let endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + parseInt(item.gantt_duration));
                
                tasks.push({
                    id: 'task_' + index,
                    name: text,
                    start: startDate.toISOString().split('T')[0],
                    end: endDate.toISOString().split('T')[0],
                    progress: 0,
                    custom_class: 'gantt-color-' + (tasks.length % ganttColors.length)
                });
            }
        });

        const container = document.getElementById('ganttSectionContainer');
        const emptyState = document.getElementById('gantt_empty_state');
        const ganttEl = document.getElementById('gantt_here');


        if (tasks.length > 0) {
            emptyState.style.display = 'none';
            ganttEl.style.display = 'block';
            ganttEl.innerHTML = '';
            ganttChart = new Gantt("#gantt_here", tasks, {
                view_mode: 'Day',
                language: 'es',
                on_date_change: function(task, start, end) {
                    // Extract item index from task id (e.g. 'task_0' → 0)
                    const idx = parseInt(task.id.replace('task_', ''));
                    if (isNaN(idx) || !itemsData[idx]) return;

                    // Calculate new start date (YYYY-MM-DD)
                    const newStart = new Date(start);
                    const yyyy = newStart.getFullYear();
                    const mm = String(newStart.getMonth() + 1).padStart(2, '0');
                    const dd = String(newStart.getDate()).padStart(2, '0');
                    const newStartStr = `${yyyy}-${mm}-${dd}`;

                    // Calculate duration in days
                    const newEnd = new Date(end);
                    const diffMs = newEnd.getTime() - newStart.getTime();
                    const diffDays = Math.max(1, Math.round(diffMs / (1000 * 60 * 60 * 24)));

                    // Update the data model
                    itemsData[idx].gantt_start_date = newStartStr;
                    itemsData[idx].gantt_duration = diffDays;

                    // Update the visible form inputs without re-rendering Gantt
                    const cards = document.querySelectorAll('.item-card');
                    if (cards[idx]) {
                        cards[idx].querySelector('.item-start').value = newStartStr;
                        cards[idx].querySelector('.item-duration').value = diffDays;
                    }
                }
            });
            // Inject color CSS for each bar
            let styleEl = document.getElementById('gantt-colors-style');
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'gantt-colors-style';
                document.head.appendChild(styleEl);
            }
            let css = '';
            ganttColors.forEach((c, i) => {
                css += `.gantt-color-${i} .bar { fill: ${c.bg} !important; }
                        .gantt-color-${i} .bar-progress { fill: ${c.bg} !important; }
                        .gantt-color-${i} .bar-label { fill: #fff !important; }
`;
            });
            styleEl.textContent = css;
        } else {
            ganttEl.style.display = 'none';
            emptyState.style.display = 'block';
        }
    } catch(e) {
        console.error("Gantt error:", e);
    }
}

function syncData() {
    const cards = document.querySelectorAll('.item-card');
    let subtotal = 0;
    const sym = getCurrencySymbol();

    cards.forEach((card, index) => {
        itemsData[index].icon = card.querySelector('.item-icon').value;
        itemsData[index].description = card.querySelector('.item-textarea').innerHTML;
        
        itemsData[index].quantity = parseFloat(card.querySelector('.item-qty').value) || 0;
        itemsData[index].unit_price = parseFloat(card.querySelector('.item-price').value) || 0;
        itemsData[index].discount = parseFloat(card.querySelector('.item-disc').value) || 0;
        itemsData[index].gantt_start_date = card.querySelector('.item-start').value;
        itemsData[index].gantt_duration = parseInt(card.querySelector('.item-duration').value) || 0;
        
        const totalItem = (itemsData[index].quantity * itemsData[index].unit_price) - itemsData[index].discount;
        itemsData[index].total = totalItem;
        subtotal += totalItem;

        card.querySelector('.item-total-display').innerText = sym + ' ' + totalItem.toFixed(2);
    });

    calculateTotals(subtotal);
    renderGantt();
}

function renderItems() {
    const container = document.getElementById('itemsContainer');
    container.innerHTML = '';
    
    let subtotal = 0;
    const sym = getCurrencySymbol();

    itemsData.forEach((item, index) => {
        const qty = parseFloat(item.quantity) || 1;
        const price = parseFloat(item.unit_price) || 0;
        const disc = parseFloat(item.discount) || 0;
        const total = (qty * price) - disc;
        subtotal += total;

        const card = document.createElement('div');
        card.className = 'item-card';
        card.innerHTML = `
            <div class="item-card-header">
                <span class="item-label">DESCRIPCIÓN DEL SERVICIO</span>
                ${generateIconSelect(item.icon || '')}
            </div>
            
            <div class="item-editor-container" style="margin-bottom: 1rem;">
                <div class="item-editor-toolbar">
                    <select class="editor-font-select" onchange="document.execCommand('fontSize', false, this.value); this.selectedIndex=0;">
                        <option value="">Tamaño</option>
                        <option value="1">Muy Pequeño</option>
                        <option value="2">Pequeño</option>
                        <option value="3">Normal</option>
                        <option value="4">Grande</option>
                        <option value="5">Muy Grande</option>
                    </select>
                    <div class="editor-divider"></div>
                    <button type="button" title="Negrita (Ctrl+B)" onclick="document.execCommand('bold', false, null)">
                        <i class="ph ph-text-bolder" style="font-size: 1.15rem;"></i>
                    </button>
                    <button type="button" title="Cursiva (Ctrl+I)" onclick="document.execCommand('italic', false, null)">
                        <i class="ph ph-text-italic" style="font-size: 1.15rem;"></i>
                    </button>
                    <button type="button" title="Subrayado (Ctrl+U)" onclick="document.execCommand('underline', false, null)">
                        <i class="ph ph-text-underline" style="font-size: 1.15rem;"></i>
                    </button>
                    <button type="button" title="Resaltar Texto" onclick="toggleHighlight()">
                        <i class="ph ph-highlighter" style="font-size: 1.15rem;"></i>
                    </button>
                    <div class="editor-divider"></div>
                    <button type="button" title="Lista con Viñetas" onclick="document.execCommand('insertUnorderedList', false, null)">
                        <i class="ph ph-list-bullets" style="font-size: 1.15rem;"></i>
                    </button>
                    <button type="button" title="Lista Numerada" onclick="document.execCommand('insertOrderedList', false, null)">
                        <i class="ph ph-list-numbers" style="font-size: 1.15rem;"></i>
                    </button>
                </div>
                <div class="item-textarea" contenteditable="true" onblur="syncData()" placeholder="Describe el servicio detalladamente...">${item.description || ''}</div>
            </div>

            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <!-- Montos Card -->
                <div class="item-amounts-card" style="flex: 1; min-width: 300px;">
                    <div class="item-row" style="margin: 0; align-items: flex-end;">
                        <div class="item-col small">
                            <span class="item-label" style="display: block; margin-bottom: 0.5rem;">CANT.</span>
                            <input type="number" class="item-input item-qty" value="${qty}" min="1" step="0.01" onchange="syncData()" onkeyup="syncData()">
                        </div>
                        <div class="item-col small">
                            <span class="item-label" style="display: block; margin-bottom: 0.5rem;">PRECIO UNIT.</span>
                            <input type="number" class="item-input item-price" value="${price.toFixed(2)}" min="0" step="0.01" onchange="syncData()" onkeyup="syncData()">
                        </div>
                        <div class="item-col small">
                            <span class="item-label" style="display: block; margin-bottom: 0.5rem;">DESC. (${sym})</span>
                            <input type="number" class="item-input item-disc" value="${disc.toFixed(2)}" min="0" step="0.01" onchange="syncData()" onkeyup="syncData()">
                        </div>
                        <div class="item-col">
                            <span class="item-label" style="display: block; margin-bottom: 0.5rem;">IMPORTE</span>
                            <div class="item-total-display">${sym} ${total.toFixed(2)}</div>
                        </div>
                    </div>
                </div>

                <!-- Gantt Card -->
                <div class="item-gantt-card" style="width: 250px;">
                    <span class="item-label" style="display: block; margin-bottom: 0.5rem;">CRONOGRAMA (GANTT)</span>
                    <div style="display: flex; gap: 0.75rem;">
                        <div style="flex: 1;">
                            <span class="item-label" style="font-size: 0.65rem; display: block; margin-bottom: 0.25rem;">FECHA DE INICIO</span>
                            <input type="date" class="item-input item-start" value="${item.gantt_start_date || ''}" onchange="syncData()">
                        </div>
                        <div style="width: 80px;">
                            <span class="item-label" style="font-size: 0.65rem; display: block; margin-bottom: 0.25rem;">DURACIÓN (DÍAS)</span>
                            <input type="number" class="item-input item-duration" value="${item.gantt_duration || 0}" min="0" onchange="syncData()" onkeyup="syncData()">
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="button" class="btn-remove-item" onclick="removeItem(${index})"><i class="ph ph-x"></i></button>
        `;
        container.appendChild(card);
    });

    calculateTotals(subtotal);
}

function removeItem(index) {
    syncData(); // Sync first so we don't lose typed data in other cards
    itemsData.splice(index, 1);
    renderItems();
}

function addEmptyRow() {
    syncData();
    itemsData.push({ 
        service_id: null, 
        icon: '', 
        description: '', 
        quantity: 1, 
        unit_price: 0, 
        discount: 0, 
        total: 0,
        gantt_start_date: document.getElementById('issue_date').value,
        gantt_duration: 1
    });
    renderItems();
}

async function addServiceFromCatalog() {
    syncData();
    const sel = document.getElementById('serviceSelector');
    if(!sel.value) return;
    const opt = sel.options[sel.selectedIndex];
    const sId = opt.value;
    const name = opt.text;
    const price = parseFloat(opt.dataset.price || 0);

    // Fetch full service data with features/deliverables
    let descHtml = `<strong>${name}</strong>`;
    try {
        const res = await fetch(`modules/services/ajax_get_service.php?id=${sId}`);
        const data = await res.json();
        if (data.success && data.data) {
            const svc = data.data;
            if (svc.description) {
                descHtml += `<br>${svc.description}`;
            }
            const features = (svc.features || []).filter(f => f.type !== 'deliverable');
            const deliverables = (svc.features || []).filter(f => f.type === 'deliverable');

            if (features.length > 0) {
                descHtml += `<br><br><strong>Características:</strong><ul>`;
                features.forEach(f => {
                    descHtml += `<li><strong>${f.title}</strong>`;
                    if (f.description) descHtml += ` — ${f.description}`;
                    descHtml += `</li>`;
                });
                descHtml += `</ul>`;
            }
            if (deliverables.length > 0) {
                descHtml += `<strong>Entregables:</strong><ul>`;
                deliverables.forEach(d => {
                    descHtml += `<li><strong>${d.title}</strong>`;
                    if (d.description) descHtml += ` — ${d.description}`;
                    descHtml += `</li>`;
                });
                descHtml += `</ul>`;
            }
        }
    } catch(e) {
        console.error('Error fetching service details:', e);
    }

    itemsData.push({ 
        service_id: sId, 
        icon: '', 
        description: descHtml, 
        quantity: 1, 
        unit_price: price, 
        discount: 0, 
        total: price,
        gantt_start_date: document.getElementById('issue_date').value,
        gantt_duration: 1
    });
    sel.value = '';
    renderItems();
}

function calculateTotals(subtotal) {
    if (subtotal === undefined) {
        subtotal = 0;
        itemsData.forEach(item => subtotal += parseFloat(item.total));
    }
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;

    const sym = getCurrencySymbol();

    document.getElementById('calcSubtotal').innerText = sym + ' ' + subtotal.toFixed(2);
    document.getElementById('calcTax').innerText = sym + ' ' + tax.toFixed(2);
    document.getElementById('calcTotal').innerText = sym + ' ' + total.toFixed(2);
}

// Initialization
document.addEventListener('DOMContentLoaded', () => {
    if(itemsData.length === 0 && !document.getElementById('quote_id').value) {
        addEmptyRow();
    } else {
        renderItems();
    }
});

// Save Logic
$('#btnSaveQuote').on('click', function(e) {
    e.preventDefault();
    const btn = $(this);
    const oText = btn.html();

    try {
        syncData(); // Ensure latest data is synced

        const client_name = $('#client_name').val().trim();
        if(!client_name) {
            Swal.fire('Atención', 'Debe escribir o seleccionar un cliente.', 'warning');
            return; 
        }

        let pm_text = "";
        document.querySelectorAll('.bank-card').forEach(card => {
            let nameElem = card.querySelector('.bank-name');
            let accElem = card.querySelector('.bank-account');
            if (nameElem && accElem) {
                let name = nameElem.innerText;
                let acc = accElem.value;
                if(acc.trim() !== '') pm_text += name + ": " + acc.trim() + "\n";
            }
        });
        
        let ownerElem = document.getElementById('bank_owner');
        let owner = ownerElem ? ownerElem.value.trim() : '';
        if(owner) pm_text += "\nA nombre de " + owner;
        
        let pmTextElem = document.getElementById('payment_methods_text');
        if (pmTextElem) pmTextElem.value = pm_text.trim();

        const payload = {
            quote_id: $('#quote_id').val(),
            client_name: client_name,
            issue_date: $('#issue_date').val(),
            due_date: $('#due_date').val(),
            currency: $('#currency').val(),
            status: $('#status').val(),
            tax_rate: $('#tax_rate').val(),
            notes: $('#notes').val(),
            terms_conditions: $('#terms_conditions').val(),
            show_payment_methods: $('#show_payment_methods').is(':checked') ? 1 : 0,
            payment_methods_text: pmTextElem ? pmTextElem.value : '',
            items: itemsData
        };

        btn.html('<i class="ph ph-spinner ph-spin"></i> Guardando...').prop('disabled', true);

        $.post('modules/quotes/ajax_save_quote.php', payload, function(res) {
            if(res && res.success) {
                Swal.fire({
                    title: 'Guardado',
                    text: res.message,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'index.php?module=quotes&action=index';
                });
            } else {
                Swal.fire('Error', (res && res.message) ? res.message : 'Respuesta desconocida', 'error');
                btn.html(oText).prop('disabled', false);
            }
        }, 'json').fail(function(xhr) {
            console.error("AJAX Fail:", xhr.responseText);
            Swal.fire('Error de Conexión', 'Hubo un problema guardando los datos. Revisa la consola.', 'error');
            btn.html(oText).prop('disabled', false);
        });

    } catch(err) {
        console.error("JS Execution Error:", err);
        alert("Ocurrió un error en el formulario: " + err.message);
        btn.html(oText).prop('disabled', false);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
