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

// Fetch brands
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

// Fetch all services and features for workflow auto-populate
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

// Fetch quotes and quote items for workflow auto-populate
$stmtQuotes = $db->query("
    SELECT q.id, q.client_id, c.name as client_name, q.client_company, q.total, q.currency, q.status, q.created_at
    FROM quotes q
    LEFT JOIN clients c ON q.client_id = c.id
    ORDER BY q.id DESC
");
$quotesList = $stmtQuotes->fetchAll(PDO::FETCH_ASSOC);

$stmtQuoteItems = $db->query("
    SELECT qi.id, qi.quote_id, qi.service_id, qi.description, qi.quantity, qi.unit_price, qi.total, s.name as service_name
    FROM quote_items qi
    LEFT JOIN services s ON qi.service_id = s.id
    ORDER BY qi.quote_id DESC, qi.id ASC
");
$allQuoteItems = $stmtQuoteItems->fetchAll(PDO::FETCH_ASSOC);

$quotesItemsMap = [];
foreach ($allQuoteItems as $qi) {
    if (!isset($quotesItemsMap[$qi['quote_id']])) {
        $quotesItemsMap[$qi['quote_id']] = [];
    }
    $quotesItemsMap[$qi['quote_id']][] = [
        'id' => $qi['id'],
        'service_id' => $qi['service_id'],
        'service_name' => $qi['service_name'] ?? '',
        'description' => $qi['description'] ?? '',
        'quantity' => $qi['quantity'] ?? 1,
        'unit_price' => $qi['unit_price'] ?? 0,
        'total' => $qi['total'] ?? 0
    ];
}

require_once 'includes/header.php';
?>
<!-- Quill JS CDN -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

<style>
/* ==========================================================================
   WORK ORDERS EDITOR - DESIGN SYSTEM (OLED DARK & HIGH-DENSITY MODERN SAAS)
   ========================================================================== */

:root {
    --wo-bg: #f8fafc;
    --wo-card: #ffffff;
    --wo-card-sub: #f8fafc;
    --wo-border: #e2e8f0;
    --wo-border-subtle: #f1f5f9;
    --wo-input-bg: #ffffff;
    --wo-text-title: #0f172a;
    --wo-text-main: #334155;
    --wo-text-muted: #64748b;
    --wo-toolbar-bg: rgba(255, 255, 255, 0.88);
    --wo-accent: var(--primary-color, #4f46e5);
    --wo-radius-sm: 8px;
    --wo-radius-md: 12px;
    --wo-radius-lg: 16px;
    --wo-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] {
    --wo-bg: #000000 !important;
    --wo-card: #0a0a0a !important;
    --wo-card-sub: #111111 !important;
    --wo-border: #1f1f1f !important;
    --wo-border-subtle: #262626 !important;
    --wo-input-bg: #080808 !important;
    --wo-text-title: #ffffff !important;
    --wo-text-main: #e2e8f0 !important;
    --wo-text-muted: #94a3b8 !important;
    --wo-toolbar-bg: rgba(10, 10, 10, 0.88) !important;
    --wo-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
}

[data-theme="dark"] body,
[data-theme="dark"] .wo-editor-container {
    background-color: #000000 !important;
}

.wo-editor-container {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 13px;
    color: var(--wo-text-main);
    max-width: 1260px;
    margin: 0 auto;
    padding: 0.5rem 0.75rem 4rem;
    box-sizing: border-box;
}

/* ==========================================================================
   STICKY FLOATING APP HEADER
   ========================================================================== */
.wo-app-header {
    position: sticky;
    top: 10px;
    z-index: 95;
    background: var(--wo-toolbar-bg);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--wo-border);
    border-radius: var(--wo-radius-lg);
    padding: 0.75rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--wo-shadow);
    transition: all 0.2s ease;
}

.wo-header-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
}

.wo-btn-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: var(--wo-radius-sm);
    background: var(--wo-card-sub);
    border: 1px solid var(--wo-border);
    color: var(--wo-text-main);
    text-decoration: none;
    font-size: 16px;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.wo-btn-back:hover {
    background: var(--wo-border);
    color: var(--wo-text-title);
    transform: translateX(-2px);
}

.wo-title-group {
    display: flex;
    flex-direction: column;
}

.wo-doc-pill {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.wo-doc-pill h2 {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--wo-text-title);
    margin: 0;
    letter-spacing: -0.01em;
}

.wo-badge-correlativo {
    background: var(--wo-card-sub);
    border: 1px solid var(--wo-border);
    color: var(--wo-accent);
    padding: 0.15rem 0.55rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    font-family: monospace;
    letter-spacing: 0.5px;
}

.wo-header-subtitle {
    font-size: 0.75rem;
    color: var(--wo-text-muted);
    margin-top: 1px;
}

.wo-header-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-shrink: 0;
}

.wo-btn-action {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1rem;
    border-radius: var(--wo-radius-sm);
    font-size: 0.825rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: inherit;
    border: 1px solid transparent;
}

.wo-btn-subtle {
    background: var(--wo-card-sub);
    border-color: var(--wo-border);
    color: var(--wo-text-main);
}
.wo-btn-subtle:hover {
    background: var(--wo-border);
    color: var(--wo-text-title);
}

.wo-btn-primary {
    background: var(--wo-accent);
    color: #ffffff;
    box-shadow: 0 2px 10px rgba(79, 70, 229, 0.25);
}
.wo-btn-primary:hover {
    filter: brightness(1.1);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
}

.wo-kbd-hint {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    padding: 2px 5px;
    font-size: 0.7rem;
    font-family: monospace;
    margin-left: 2px;
}

/* ==========================================================================
   MODULAR CARDS (CARDS OLED)
   ========================================================================== */
.wo-card {
    background: var(--wo-card);
    border: 1px solid var(--wo-border);
    border-radius: var(--wo-radius-lg);
    padding: 1.35rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--wo-shadow);
    transition: border-color 0.2s ease;
}
.wo-card:focus-within {
    border-color: rgba(99, 102, 241, 0.4);
}

.wo-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid var(--wo-border-subtle);
    flex-wrap: wrap;
    gap: 0.75rem;
}

.wo-card-title {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.wo-card-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: rgba(99, 102, 241, 0.12);
    color: var(--wo-accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}

.wo-card-title h3 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--wo-text-title);
    letter-spacing: -0.01em;
}

.wo-card-title p {
    margin: 1px 0 0;
    font-size: 0.75rem;
    color: var(--wo-text-muted);
}

/* Grid System */
.wo-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

.wo-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1.25rem;
}

/* Form Groups */
.wo-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.wo-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--wo-text-muted);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.wo-label i {
    font-size: 0.85rem;
    color: var(--wo-accent);
}

.wo-input,
.wo-select {
    width: 100%;
    padding: 0.65rem 0.9rem;
    background: var(--wo-input-bg);
    border: 1px solid var(--wo-border);
    border-radius: var(--wo-radius-sm);
    color: var(--wo-text-main);
    font-size: 0.85rem;
    font-family: inherit;
    font-weight: 500;
    outline: none;
    box-sizing: border-box;
    transition: all 0.2s ease;
}
.wo-input:focus,
.wo-select:focus {
    border-color: var(--wo-accent);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

/* Priority Modern Pills Selector */
.wo-priority-pills {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.5rem;
}

.wo-priority-pill {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.6rem 0.5rem;
    background: var(--wo-input-bg);
    border: 1px solid var(--wo-border);
    border-radius: var(--wo-radius-sm);
    color: var(--wo-text-main);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
    transition: all 0.2s ease;
}
.wo-priority-pill:hover {
    border-color: var(--wo-text-muted);
    background: var(--wo-card-sub);
}

.wo-priority-pill.active[data-priority="Baja"] {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}
.wo-priority-pill.active[data-priority="Media"] {
    border-color: #f59e0b;
    background: rgba(245, 158, 11, 0.12);
    color: #f59e0b;
}
.wo-priority-pill.active[data-priority="Alta"] {
    border-color: #ef4444;
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}

.priority-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.priority-dot.dot-green { background: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.5); }
.priority-dot.dot-yellow { background: #f59e0b; box-shadow: 0 0 8px rgba(245, 158, 11, 0.5); }
.priority-dot.dot-red { background: #ef4444; box-shadow: 0 0 8px rgba(239, 68, 68, 0.5); }

/* Channels / Social Networks modern selector */
.wo-channels-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.wo-quick-channel-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.wo-channel-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.65rem;
    border-radius: 9999px;
    border: 1px solid var(--wo-border);
    background: var(--wo-card-sub);
    color: var(--wo-text-muted);
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}
.wo-channel-btn:hover {
    border-color: var(--wo-accent);
    color: var(--wo-text-title);
    transform: translateY(-1px);
}
.wo-channel-btn i {
    font-size: 0.95rem;
}

.wo-channel-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: var(--wo-card-sub);
    border: 1px solid var(--wo-border);
    border-radius: var(--wo-radius-sm);
    padding: 0.5rem 0.75rem;
    transition: border-color 0.2s ease;
}
.wo-channel-card:focus-within {
    border-color: var(--wo-accent);
}

.wo-channel-icon-pill {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--wo-input-bg);
    border: 1px solid var(--wo-border);
    font-size: 1.1rem;
    flex-shrink: 0;
}

.wo-channel-input {
    flex: 1;
    border: none;
    background: transparent;
    color: var(--wo-text-main);
    font-size: 0.85rem;
    outline: none;
    font-family: inherit;
}
.wo-channel-input::placeholder {
    color: var(--wo-text-muted);
    opacity: 0.7;
}

.wo-btn-remove {
    background: transparent;
    border: none;
    color: var(--wo-text-muted);
    cursor: pointer;
    padding: 0.3rem;
    border-radius: 6px;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}
.wo-btn-remove:hover {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.1);
}

/* ==========================================================================
   WORKFLOW / DELIVERABLES ITEMS & IMPORTERS
   ========================================================================== */
.wo-workflow-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.wo-importer-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: var(--wo-card-sub);
    border: 1px solid var(--wo-border);
    border-radius: var(--wo-radius-sm);
    padding: 0.35rem 0.65rem;
    transition: all 0.15s ease;
}
.wo-importer-pill:hover,
.wo-importer-pill:focus-within {
    border-color: var(--wo-accent);
}
.wo-importer-pill.quote-importer i {
    color: #6366f1;
    font-size: 1.05rem;
}
.wo-importer-pill.service-importer i {
    color: #f59e0b;
    font-size: 1.05rem;
}

.wo-importer-pill select {
    border: none;
    background: transparent;
    color: var(--wo-text-main);
    font-size: 0.8rem;
    font-weight: 600;
    outline: none;
    cursor: pointer;
    font-family: inherit;
    max-width: 215px;
}
.wo-importer-pill select option {
    background: var(--wo-card);
    color: var(--wo-text-main);
}

.wo-badge-counter {
    background: rgba(99, 102, 241, 0.12);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: var(--wo-accent);
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 700;
}

.wo-deliverable-card {
    background: var(--wo-card-sub);
    border: 1px solid var(--wo-border);
    border-radius: var(--wo-radius-md);
    padding: 1.1rem 1.25rem;
    margin-bottom: 0.95rem;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.wo-deliverable-card:hover {
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
}

.wo-deliverable-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.85rem;
    flex-wrap: wrap;
}

.wo-deliverable-index {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.wo-deliverable-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.15);
    color: var(--wo-accent);
    font-size: 0.75rem;
    font-weight: 700;
    font-family: monospace;
}

.wo-deliverable-assignee {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 240px;
    flex: 1;
    max-width: 380px;
}

.wo-deliverable-body {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

/* Modern Quill inside deliverable item */
.wo-deliverable-card .ql-toolbar.ql-snow {
    border: 1px solid var(--wo-border);
    border-radius: var(--wo-radius-sm) var(--wo-radius-sm) 0 0;
    background: var(--wo-input-bg);
    padding: 0.35rem 0.5rem;
}
.wo-deliverable-card .ql-container.ql-snow {
    border: 1px solid var(--wo-border);
    border-top: none;
    border-radius: 0 0 var(--wo-radius-sm) var(--wo-radius-sm);
    font-family: inherit;
    font-size: 0.85rem;
    color: var(--wo-text-main);
    background: var(--wo-input-bg);
    min-height: 75px;
}
.wo-deliverable-card .ql-editor {
    min-height: 75px;
    padding: 0.65rem 0.85rem;
    line-height: 1.5;
}
.wo-deliverable-card .ql-editor.ql-blank::before {
    color: var(--wo-text-muted);
    font-style: normal;
    opacity: 0.6;
}

/* Empty State */
.wo-empty-state {
    text-align: center;
    padding: 2.5rem 1.5rem;
    background: var(--wo-card-sub);
    border: 1px dashed var(--wo-border);
    border-radius: var(--wo-radius-md);
}
.wo-empty-icon {
    font-size: 2.5rem;
    color: var(--wo-text-muted);
    opacity: 0.6;
    margin-bottom: 0.5rem;
}
.wo-empty-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--wo-text-title);
    margin: 0 0 0.25rem;
}
.wo-empty-desc {
    font-size: 0.8rem;
    color: var(--wo-text-muted);
    max-width: 480px;
    margin: 0 auto 1.25rem;
    line-height: 1.5;
}

/* ==========================================================================
   BUDGET & NOTES CARDS
   ========================================================================== */
.wo-budget-input-wrap {
    display: flex;
    align-items: center;
    background: var(--wo-input-bg);
    border: 1px solid var(--wo-border);
    border-radius: var(--wo-radius-sm);
    overflow: hidden;
    transition: all 0.2s ease;
}
.wo-budget-input-wrap:focus-within {
    border-color: var(--wo-accent);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.wo-currency-select {
    border: none;
    background: var(--wo-card-sub);
    color: var(--wo-text-title);
    font-weight: 700;
    font-size: 0.9rem;
    padding: 0.65rem 0.85rem;
    outline: none;
    cursor: pointer;
    border-right: 1px solid var(--wo-border);
    font-family: inherit;
}

.wo-amount-input {
    flex: 1;
    border: none;
    background: transparent;
    padding: 0.65rem 0.9rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--wo-text-title);
    outline: none;
    font-family: inherit;
}

.wo-note-banner {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-top: 0.65rem;
    font-size: 0.75rem;
    color: var(--wo-text-muted);
}
.wo-note-banner i {
    color: #10b981;
}

/* Quill Global Observaciones */
#observacionesContainer .ql-toolbar.ql-snow {
    border: 1px solid var(--wo-border);
    border-radius: var(--wo-radius-sm) var(--wo-radius-sm) 0 0;
    background: var(--wo-input-bg);
    padding: 0.45rem 0.65rem;
}
#observacionesContainer .ql-container.ql-snow {
    border: 1px solid var(--wo-border);
    border-top: none;
    border-radius: 0 0 var(--wo-radius-sm) var(--wo-radius-sm);
    font-family: inherit;
    font-size: 0.85rem;
    color: var(--wo-text-main);
    background: var(--wo-input-bg);
    min-height: 120px;
}
#observacionesContainer .ql-editor {
    min-height: 120px;
    padding: 0.85rem 1rem;
    line-height: 1.55;
}

/* ==========================================================================
   CUSTOM FIELDS (NOTION / LINEAR PROPERTIES)
   ========================================================================== */
.wo-property-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    background: var(--wo-card-sub);
    border: 1px solid var(--wo-border);
    border-radius: var(--wo-radius-sm);
    padding: 0.45rem 0.75rem;
    transition: border-color 0.2s;
}
.wo-property-row:focus-within {
    border-color: var(--wo-accent);
}

.wo-prop-name {
    width: 180px;
    border: none;
    background: transparent;
    color: var(--wo-text-title);
    font-weight: 600;
    font-size: 0.825rem;
    outline: none;
    font-family: inherit;
}

.wo-prop-separator {
    color: var(--wo-text-muted);
    opacity: 0.4;
    user-select: none;
}

.wo-prop-value {
    flex: 1;
    border: none;
    background: transparent;
    color: var(--wo-text-main);
    font-size: 0.825rem;
    outline: none;
    font-family: inherit;
}

/* ==========================================================================
   DARK THEME SPECIFIC OVERRIDES FOR QUILL
   ========================================================================== */
[data-theme="dark"] .ql-stroke {
    stroke: #94a3b8 !important;
}
[data-theme="dark"] .ql-fill {
    fill: #94a3b8 !important;
}
[data-theme="dark"] .ql-picker {
    color: #94a3b8 !important;
}
[data-theme="dark"] .ql-active .ql-stroke,
[data-theme="dark"] .ql-snow.ql-toolbar button:hover .ql-stroke {
    stroke: var(--wo-accent) !important;
}
[data-theme="dark"] .ql-active .ql-fill,
[data-theme="dark"] .ql-snow.ql-toolbar button:hover .ql-fill {
    fill: var(--wo-accent) !important;
}

/* Toast Message */
.toast-msg {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    background: #0f766e;
    color: #ffffff;
    padding: 0.75rem 1.4rem;
    border-radius: 9999px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.35);
    font-size: 0.85rem;
    font-weight: 600;
    z-index: 10000;
    animation: toastIn 0.3s cubic-bezier(0.18, 0.89, 0.32, 1.28);
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
@keyframes toastIn {
    from { transform: translateY(100%) scale(0.9); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .wo-workflow-actions {
        width: 100%;
        margin-top: 0.5rem;
    }
    .wo-importer-pill {
        flex: 1 1 200px;
    }
    .wo-importer-pill select {
        max-width: 100%;
        width: 100%;
    }
}

@media (max-width: 900px) {
    .wo-grid-2, .wo-grid-3 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .wo-app-header {
        position: fixed;
        top: auto;
        bottom: 0;
        left: 0;
        right: 0;
        margin: 0;
        border-radius: 16px 16px 0 0;
        z-index: 1000;
        padding: 0.75rem 1rem;
    }
    .wo-editor-container {
        padding-bottom: 90px;
    }
    .wo-deliverable-assignee {
        max-width: 100%;
        min-width: 100%;
    }
}
</style>

<div class="wo-editor-container">
    <!-- Sticky Floating App Header -->
    <header class="wo-app-header">
        <div class="wo-header-left">
            <a href="index.php?module=work_orders&action=index" class="wo-btn-back" title="Volver al listado">
                <i class="ph ph-arrow-left"></i>
            </a>
            <div class="wo-title-group">
                <div class="wo-doc-pill">
                    <h2>Orden de Servicio</h2>
                    <span class="wo-badge-correlativo">#<?php echo htmlspecialchars($correlativo); ?></span>
                </div>
                <span class="wo-header-subtitle" id="headerSubtitleSummary">Borrador de orden de trabajo</span>
            </div>
        </div>

        <div class="wo-header-actions">
            <?php if (!empty($public_token)): ?>
            <button type="button" class="wo-btn-action wo-btn-subtle" onclick="shareOrder('<?php echo htmlspecialchars($public_token); ?>')">
                <i class="ph ph-share-network"></i> Compartir
            </button>
            <?php endif; ?>

            <button type="button" id="saveSnapshotBtn" class="wo-btn-action wo-btn-primary">
                <i class="ph ph-floppy-disk"></i> Guardar <span class="wo-kbd-hint">Ctrl+S</span>
            </button>
        </div>
    </header>

    <!-- CARD 1: INFORMACIÓN DEL SERVICIO -->
    <div class="wo-card">
        <div class="wo-card-header">
            <div class="wo-card-title">
                <div class="wo-card-icon"><i class="ph ph-briefcase"></i></div>
                <div>
                    <h3>Información del Servicio</h3>
                    <p>Datos principales del cliente, marca y cronograma de inicio</p>
                </div>
            </div>
            <div id="quickInfoPill" style="font-size: 0.75rem; color: var(--wo-text-muted); font-weight: 500;"></div>
        </div>

        <div class="wo-grid-2" style="margin-bottom: 1.25rem;">
            <!-- Cliente -->
            <div class="wo-form-group">
                <label class="wo-label"><i class="ph ph-buildings"></i> Cliente</label>
                <select id="clienteName" class="wo-select">
                    <option value="">Seleccionar Cliente...</option>
                    <?php foreach($clients as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['name']); ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Marca -->
            <div class="wo-form-group">
                <label class="wo-label"><i class="ph ph-tag"></i> Marca Comercial</label>
                <select id="marcaName" class="wo-select">
                    <option value="">Primero seleccione un cliente...</option>
                </select>
            </div>
        </div>

        <div class="wo-grid-2" style="margin-bottom: 1.25rem;">
            <!-- Fecha de Inicio -->
            <div class="wo-form-group">
                <label class="wo-label"><i class="ph ph-calendar-blank"></i> Fecha de Inicio</label>
                <input type="date" id="fechaInicio" class="wo-input" value="">
            </div>

            <!-- Prioridad con pills interactivas -->
            <div class="wo-form-group">
                <label class="wo-label"><i class="ph ph-flag"></i> Prioridad del Proyecto</label>
                <input type="hidden" id="osPrioridad" value="Media">
                <div class="wo-priority-pills" id="priorityPillsGroup">
                    <div class="wo-priority-pill" data-priority="Baja" onclick="setPriority('Baja')">
                        <span class="priority-dot dot-green"></span> Baja
                    </div>
                    <div class="wo-priority-pill active" data-priority="Media" onclick="setPriority('Media')">
                        <span class="priority-dot dot-yellow"></span> Media
                    </div>
                    <div class="wo-priority-pill" data-priority="Alta" onclick="setPriority('Alta')">
                        <span class="priority-dot dot-red"></span> Alta
                    </div>
                </div>
            </div>
        </div>

        <!-- Redes a Manejar -->
        <div class="wo-form-group">
            <label class="wo-label"><i class="ph ph-globe"></i> Canales y Redes Sociales a Manejar</label>
            <div class="wo-channels-container">
                <div class="wo-quick-channel-pills">
                    <button type="button" class="wo-channel-btn" onclick="addNetworkPreset('Facebook')"><i class="ph ph-facebook-logo" style="color:#1877F2;"></i> + Facebook</button>
                    <button type="button" class="wo-channel-btn" onclick="addNetworkPreset('Instagram')"><i class="ph ph-instagram-logo" style="color:#E4405F;"></i> + Instagram</button>
                    <button type="button" class="wo-channel-btn" onclick="addNetworkPreset('TikTok')"><i class="ph ph-tiktok-logo"></i> + TikTok</button>
                    <button type="button" class="wo-channel-btn" onclick="addNetworkPreset('LinkedIn')"><i class="ph ph-linkedin-logo" style="color:#0A66C2;"></i> + LinkedIn</button>
                    <button type="button" class="wo-channel-btn" onclick="addNetworkPreset('YouTube')"><i class="ph ph-youtube-logo" style="color:#FF0000;"></i> + YouTube</button>
                    <button type="button" class="wo-channel-btn" onclick="addNetworkPreset('Google')"><i class="ph ph-google-logo" style="color:#DB4437;"></i> + Google</button>
                    <button type="button" class="wo-channel-btn" onclick="addNetworkPreset('Web')"><i class="ph ph-globe" style="color:#10b981;"></i> + Enlace Web</button>
                </div>
                <div id="redesManejarWrapper" style="display: flex; flex-direction: column; gap: 0.65rem;"></div>
            </div>
        </div>
    </div>

    <!-- CARD 2: FLUJO DE TRABAJO & ENTREGABLES -->
    <div class="wo-card">
        <div class="wo-card-header">
            <div class="wo-card-title">
                <div class="wo-card-icon"><i class="ph ph-kanban"></i></div>
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <h3>Flujo de Trabajo & Entregables</h3>
                        <span id="workflowBadgeCounter" class="wo-badge-counter">0 entregables</span>
                    </div>
                    <p>Tareas, entregables específicos y asignación de responsables</p>
                </div>
            </div>

            <div class="wo-workflow-actions">
                <!-- Cargar desde Cotización -->
                <div class="wo-importer-pill quote-importer" title="Importar entregables desde una cotización registrada">
                    <i class="ph ph-receipt"></i>
                    <select id="quoteSelector">
                        <option value="">Cargar desde Cotización...</option>
                        <?php foreach($quotesList as $q): ?>
                            <option value="<?php echo $q['id']; ?>" 
                                data-client="<?php echo htmlspecialchars($q['client_name'] ?? ''); ?>" 
                                data-company="<?php echo htmlspecialchars($q['client_company'] ?? ''); ?>" 
                                data-currency="<?php echo htmlspecialchars($q['currency'] ?? 'PEN'); ?>" 
                                data-total="<?php echo htmlspecialchars($q['total'] ?? '0'); ?>">
                                #COT-<?php echo str_pad($q['id'], 4, '0', STR_PAD_LEFT); ?> · <?php echo htmlspecialchars($q['client_name'] ?: ($q['client_company'] ?: 'Sin cliente')); ?> (<?php echo ($q['currency'] === 'USD' ? 'USD' : 'S/') . ' ' . number_format((float)$q['total'], 2); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Cargar desde Servicio -->
                <div class="wo-importer-pill service-importer" title="Cargar entregables predefinidos desde un servicio del catálogo">
                    <i class="ph ph-lightning"></i>
                    <select id="serviceSelector">
                        <option value="">Cargar desde Servicio...</option>
                        <?php foreach($servicesList as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="button" class="wo-btn-action wo-btn-primary add-main-row" data-id="main_workflow">
                    <i class="ph ph-plus-circle"></i> Agregar Fila
                </button>
                <button type="button" class="wo-btn-action wo-btn-subtle delete-all-rows" data-id="main_workflow" title="Limpiar todas las filas">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
        </div>

        <div id="procesosContainer"></div>
    </div>

    <!-- CARD 3 & 4: PRESUPUESTO & OBSERVACIONES (GRID 2 COLUMNAS) -->
    <div class="wo-grid-2">
        <!-- Presupuesto ADS -->
        <div class="wo-card">
            <div class="wo-card-header">
                <div class="wo-card-title">
                    <div class="wo-card-icon" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
                        <i class="ph ph-currency-circle-dollar"></i>
                    </div>
                    <div>
                        <h3>Presupuesto ADS + Pauta</h3>
                        <p>Inversión destinada a campañas y cuenta comercial</p>
                    </div>
                </div>
            </div>

            <div class="wo-form-group">
                <label class="wo-label"><i class="ph ph-coins"></i> Monto de Inversión Estimada</label>
                <div class="wo-budget-input-wrap">
                    <select id="monedaPresupuesto" class="wo-currency-select">
                        <option value="S/">S/</option>
                        <option value="USD">USD</option>
                    </select>
                    <input type="text" inputmode="decimal" id="presupuestoAds" name="budget_ots_amount" placeholder="0.00" value="" autocomplete="off" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" class="wo-amount-input">
                </div>
                <div class="wo-note-banner">
                    <i class="ph ph-check-circle"></i>
                    <span>Cuenta comercial y pauta publicitaria ya calculada/incluida.</span>
                </div>
            </div>
        </div>

        <!-- Observaciones Adicionales -->
        <div class="wo-card">
            <div class="wo-card-header">
                <div class="wo-card-title">
                    <div class="wo-card-icon" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b;">
                        <i class="ph ph-note-pencil"></i>
                    </div>
                    <div>
                        <h3>Observaciones Generales</h3>
                        <p>Términos clave, accesos, notas internas o directrices</p>
                    </div>
                </div>
            </div>

            <div class="wo-form-group">
                <div id="observacionesContainer"></div>
                <textarea id="observacionesGlobal" style="display:none;"></textarea>
            </div>
        </div>
    </div>

    <!-- CARD 5: CAMPOS PERSONALIZADOS & ENLACES RÁPIDOS -->
    <div class="wo-card">
        <div class="wo-card-header">
            <div class="wo-card-title">
                <div class="wo-card-icon" style="background: rgba(14, 165, 233, 0.12); color: #0ea5e9;">
                    <i class="ph ph-faders"></i>
                </div>
                <div>
                    <h3>Campos Personalizados (Metadatos)</h3>
                    <p>Propiedades adicionales, enlaces a Figma, Google Drive o carpetas compartidas</p>
                </div>
            </div>

            <button type="button" id="add-custom-field-btn" class="wo-btn-action wo-btn-subtle">
                <i class="ph ph-plus"></i> Añadir Propiedad
            </button>
        </div>

        <div id="customFieldsContainer" style="display: flex; flex-direction: column; gap: 0.65rem;"></div>
    </div>
</div>

<!-- Modal para Compartir -->
<div class="modal-overlay" id="shareModal">
    <div class="modal-content" style="max-width: 440px; text-align: center; border-radius: 18px;">
        <div class="modal-header" style="justify-content: center; position: relative; border-bottom: 1px solid var(--wo-border);">
            <h2 class="modal-title" style="font-size: 1.1rem; font-weight: 700;">Compartir Orden de Servicio</h2>
            <button class="btn-icon close-modal" onclick="document.getElementById('shareModal').classList.remove('active')" style="position: absolute; right: 0;">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body" style="padding: 1.8rem 1.25rem;">
            <div style="width: 56px; height: 56px; border-radius: 14px; background: rgba(99, 102, 241, 0.12); color: var(--wo-accent); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.8rem;">
                <i class="ph ph-link-simple"></i>
            </div>
            <p style="color: var(--wo-text-main); font-size: 0.85rem; margin-bottom: 1.25rem; line-height: 1.5;">
                Copia este enlace para compartir la vista pública de esta orden de servicio con tu cliente o equipo.
            </p>
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                <input type="text" id="shareLinkInput" class="wo-input" readonly style="text-align: center; font-family: monospace; font-size: 0.8rem;">
                <button type="button" class="wo-btn-action wo-btn-primary" onclick="copyShareLink()">
                    <i class="ph ph-copy"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/* ==========================================================================
   WORK ORDERS JAVASCRIPT ENGINE
   ========================================================================== */

const REDES_DEF = [
    { id: 'Facebook', label: 'Facebook', icon: 'ph-facebook-logo', color: '#1877F2' },
    { id: 'Instagram', label: 'Instagram', icon: 'ph-instagram-logo', color: '#E4405F' },
    { id: 'TikTok', label: 'TikTok', icon: 'ph-tiktok-logo', color: '#000000' },
    { id: 'VK', label: 'VK', icon: 'ph-users-three', color: '#4680C2' },
    { id: 'Google', label: 'Google', icon: 'ph-google-logo', color: '#DB4437' },
    { id: 'YouTube', label: 'YouTube', icon: 'ph-youtube-logo', color: '#FF0000' },
    { id: 'LinkedIn', label: 'LinkedIn', icon: 'ph-linkedin-logo', color: '#0A66C2' },
    { id: 'Web', label: 'Enlace Web', icon: 'ph-globe', color: '#10b981' }
];

let redesData = []; // [{ id: 'Facebook', url: '' }]
let customFieldsData = []; // [{ name: '', value: '' }]
const MAIN_WORKFLOW = [
    { id: "main_workflow", nombre: "FLUJO DE TRABAJO PRINCIPAL", icono: "ph-kanban", rowsDefault: [] }
];
const SERVICES_FEATURES_MAP = <?php echo json_encode($servicesFeaturesMap); ?>;
const QUOTES_ITEMS_MAP = <?php echo json_encode($quotesItemsMap); ?>;
const EMPLEADOS = <?php echo json_encode($employees); ?>;
const clientBrandsMap = <?php echo json_encode($clientBrandsMap); ?>;
const dbData = <?php echo $order_data ? $order_data : 'null'; ?>;
const woId = '<?php echo $id; ?>';

let procesosData = [];
let quillGlobal = null;

// Helper toast integrado
function showAppToast(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: message,
            showConfirmButton: false,
            timer: 2200,
            background: 'var(--wo-card)',
            color: 'var(--wo-text-main)'
        });
        return;
    }
    let existing = document.querySelector('.toast-msg');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'toast-msg';
    toast.innerHTML = '<i class="ph ph-check-circle" style="font-size: 1.15rem;"></i> ' + message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m){
        if(m==='&') return '&amp;';
        if(m==='<') return '&lt;';
        if(m==='>') return '&gt;';
        return m;
    });
}

// Priority management
function setPriority(priority) {
    const input = document.getElementById('osPrioridad');
    if (input) input.value = priority;
    
    document.querySelectorAll('.wo-priority-pill').forEach(pill => {
        if (pill.dataset.priority === priority) {
            pill.classList.add('active');
        } else {
            pill.classList.remove('active');
        }
    });
    updateInfoSummary();
}

// Social networks
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

function addNetworkPreset(networkId) {
    redesData.push({ id: networkId, url: '' });
    renderRedes();
    updateInfoSummary();
}

function renderRedes() {
    const container = document.getElementById('redesManejarWrapper');
    if (!container) return;

    if (redesData.length === 0) {
        container.innerHTML = '<div style="font-size: 0.75rem; color: var(--wo-text-muted); font-style: italic; padding: 0.4rem 0;">No se han agregado redes sociales aún. Haz clic en las etiquetas superiores para añadir una.</div>';
        return;
    }

    let html = '';
    redesData.forEach((redSel, index) => {
        const def = REDES_DEF.find(d => d.id === redSel.id) || REDES_DEF.find(d => d.id === 'Web');
        html += `
            <div class="wo-channel-card">
                <div class="wo-channel-icon-pill" style="color: ${def.color};">
                    <i class="ph ${def.icon}"></i>
                </div>
                <input type="text" class="wo-channel-input red-url-input" data-index="${index}" value="${escapeHtml(redSel.url)}" placeholder="Enlace o usuario de ${def.label} (ej. https://...)">
                <button type="button" class="wo-btn-remove remove-red-btn" data-index="${index}" title="Eliminar canal">
                    <i class="ph ph-x"></i>
                </button>
            </div>
        `;
    });
    container.innerHTML = html;

    container.querySelectorAll('.remove-red-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const idx = parseInt(e.currentTarget.dataset.index, 10);
            redesData.splice(idx, 1);
            renderRedes();
            updateInfoSummary();
        });
    });

    container.querySelectorAll('.red-url-input').forEach(input => {
        input.addEventListener('input', (e) => {
            const index = parseInt(e.target.dataset.index, 10);
            const val = e.target.value;
            redesData[index].url = val;

            const detected = detectNetwork(val);
            if (detected && detected !== redesData[index].id && val.length > 8) {
                redesData[index].id = detected;
                renderRedes();
                setTimeout(() => {
                    const inputs = document.getElementById('redesManejarWrapper').querySelectorAll('.red-url-input');
                    if (inputs[index]) {
                        inputs[index].focus();
                        const len = inputs[index].value.length;
                        inputs[index].setSelectionRange(len, len);
                    }
                }, 0);
            }
            updateInfoSummary();
        });
    });
}

// Custom fields
function renderCustomFields() {
    const container = document.getElementById('customFieldsContainer');
    if (!container) return;

    if (customFieldsData.length === 0) {
        container.innerHTML = '<div style="font-size: 0.75rem; color: var(--wo-text-muted); font-style: italic; padding: 0.25rem 0;">Sin propiedades personalizadas. Puedes agregar enlaces a tableros, carpetas compartidas o notas clave.</div>';
        return;
    }

    let html = '';
    customFieldsData.forEach((cf, index) => {
        html += `
            <div class="wo-property-row">
                <input type="text" class="wo-prop-name cf-name-input" data-index="${index}" value="${escapeHtml(cf.name)}" placeholder="Propiedad (ej. Figma, Drive)">
                <span class="wo-prop-separator">:</span>
                <input type="text" class="wo-prop-value cf-value-input" data-index="${index}" value="${escapeHtml(cf.value)}" placeholder="Valor, enlace o descripción...">
                ${cf.value && (cf.value.startsWith('http://') || cf.value.startsWith('https://')) ? `<a href="${escapeHtml(cf.value)}" target="_blank" rel="noopener noreferrer" class="wo-btn-remove" title="Abrir enlace" style="color: var(--wo-accent);"><i class="ph ph-arrow-square-out"></i></a>` : ''}
                <button type="button" class="wo-btn-remove remove-cf-btn" data-index="${index}" title="Eliminar propiedad">
                    <i class="ph ph-trash"></i>
                </button>
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

// Summary banner & subtitles
function updateInfoSummary() {
    const cliente = document.getElementById('clienteName').value || 'Sin cliente seleccionado';
    const marca = document.getElementById('marcaName').value || '';
    const redes = redesData.map(r => r.id);
    const fecha = document.getElementById('fechaInicio').value || '';
    const prioridad = document.getElementById('osPrioridad').value || 'Media';

    let parts = [cliente];
    if (marca) parts.push(marca);
    if (fecha) parts.push('Inicio: ' + fecha);
    parts.push('Prioridad: ' + prioridad);

    const subEl = document.getElementById('headerSubtitleSummary');
    if (subEl) subEl.textContent = parts.join(' · ');

    const quickEl = document.getElementById('quickInfoPill');
    if (quickEl) {
        quickEl.textContent = redes.length > 0 ? `${redes.length} canal(es) asignado(s)` : 'Sin canales configurados';
    }
}

// Dependent Brands logic
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

// Workflows & Deliverables Engine
function renderProcesos() {
    const container = document.getElementById('procesosContainer');
    if (!container) return;
    container.innerHTML = '';

    const currentProcess = procesosData.find(p => p.id === "main_workflow") || procesosData[0];
    if (!currentProcess) return;

    const rowCount = currentProcess.rows ? currentProcess.rows.length : 0;
    const badgeCounter = document.getElementById('workflowBadgeCounter');
    if (badgeCounter) {
        badgeCounter.textContent = `${rowCount} ${rowCount === 1 ? 'entregable' : 'entregables'}`;
    }

    if (rowCount === 0) {
        container.innerHTML = `
            <div class="wo-empty-state">
                <i class="ph ph-clipboard-text wo-empty-icon"></i>
                <h4 class="wo-empty-title">No hay entregables o tareas añadidas</h4>
                <p class="wo-empty-desc">Comienza agregando un entregable manualmente o importa desde una cotización o servicio con los selectores superiores.</p>
                <button type="button" class="wo-btn-action wo-btn-primary add-main-row" data-id="main_workflow">
                    <i class="ph ph-plus-circle"></i> Agregar Primer Entregable
                </button>
            </div>
        `;
        attachGlobalProcessEvents();
        return;
    }

    currentProcess.rows.forEach((row, rowIndex) => {
        const card = document.createElement('div');
        card.className = 'wo-deliverable-card';
        card.dataset.rowIndex = rowIndex;

        let selectOptions = `<option value="">Asignar Encargado...</option>`;
        let found = false;
        EMPLEADOS.forEach(emp => {
            const isSelected = row.encargado === emp.name ? 'selected' : '';
            if (isSelected) found = true;
            selectOptions += `<option value="${escapeHtml(emp.name)}" ${isSelected}>${escapeHtml(emp.name)}</option>`;
        });
        if (row.encargado && !found) {
            selectOptions += `<option value="${escapeHtml(row.encargado)}" selected>${escapeHtml(row.encargado)} (Antiguo)</option>`;
        }

        const formattedIndex = String(rowIndex + 1).padStart(2, '0');

        card.innerHTML = `
            <div class="wo-deliverable-top">
                <div class="wo-deliverable-index">
                    <span class="wo-deliverable-num">#${formattedIndex}</span>
                    <strong style="color: var(--wo-text-title); font-size: 0.85rem;">Entregable de Servicio</strong>
                </div>

                <div class="wo-deliverable-assignee">
                    <div style="display: flex; align-items: center; gap: 0.4rem; width: 100%;">
                        <i class="ph ph-user-circle" style="color: var(--wo-accent); font-size: 1.15rem;"></i>
                        <select class="wo-select encargado-input" style="padding: 0.45rem 0.65rem; font-size: 0.8rem;">
                            ${selectOptions}
                        </select>
                    </div>
                </div>

                <div>
                    <button type="button" class="wo-btn-remove remove-row-btn" title="Eliminar fila">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            </div>

            <div class="wo-deliverable-body">
                <label class="wo-label"><i class="ph ph-list-checks"></i> Descripción y Alcance del Trabajo</label>
                <div class="quill-editor"></div>
                <textarea class="descripcion-input" style="display:none;">${escapeHtml(row.descripcion)}</textarea>
            </div>
        `;

        // Bind Encargado
        const encInput = card.querySelector('.encargado-input');
        encInput.addEventListener('change', (e) => {
            currentProcess.rows[rowIndex].encargado = e.target.value;
        });

        // Bind Quill Editor
        const quillContainer = card.querySelector('.quill-editor');
        if (quillContainer) {
            const quill = new Quill(quillContainer, {
                theme: 'snow',
                placeholder: 'Describe las especificaciones, requisitos y entregables de esta tarea...',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        [{ 'list': 'bullet' }],
                        ['link', 'clean']
                    ]
                }
            });
            quill.root.innerHTML = row.descripcion || '';
            quill.on('text-change', function() {
                currentProcess.rows[rowIndex].descripcion = quill.root.innerHTML;
                card.querySelector('.descripcion-input').value = quill.root.innerHTML;
            });
        }

        // Bind Remove Button
        const removeBtn = card.querySelector('.remove-row-btn');
        removeBtn.addEventListener('click', () => {
            currentProcess.rows.splice(rowIndex, 1);
            renderProcesos();
        });

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
    const currentProcess = procesosData.find(p => p.id === "main_workflow") || procesosData[0];
    if (!currentProcess || !currentProcess.rows || currentProcess.rows.length === 0) return;

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '¿Eliminar todas las filas?',
            text: 'Se vaciarán todos los entregables actuales del flujo de trabajo.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="ph ph-trash"></i> Sí, vaciar',
            cancelButtonText: 'Cancelar',
            background: 'var(--wo-card)',
            color: 'var(--wo-text-main)'
        }).then((result) => {
            if (result.isConfirmed) {
                currentProcess.rows = [];
                renderProcesos();
                showAppToast('Filas eliminadas.');
            }
        });
    } else {
        if (confirm('¿Deseas eliminar todas las filas del flujo de trabajo?')) {
            currentProcess.rows = [];
            renderProcesos();
        }
    }
}

function addMainHandler(e) {
    const currentProcess = procesosData.find(p => p.id === "main_workflow") || procesosData[0];
    if (currentProcess) {
        currentProcess.rows.push({ encargado: "", descripcion: "" });
        renderProcesos();
        const container = document.getElementById('procesosContainer');
        if (container && container.lastElementChild) {
            container.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
}

// ==========================================================================
// QUOTE IMPORTER (CARGAR DESDE COTIZACIÓN)
// ==========================================================================
document.getElementById('quoteSelector')?.addEventListener('change', function() {
    const quoteId = this.value;
    if (!quoteId) return;

    const currentProcess = procesosData.find(p => p.id === "main_workflow") || procesosData[0];
    if (!currentProcess) return;

    const items = QUOTES_ITEMS_MAP[quoteId] || [];
    if (items.length === 0) {
        showAppToast('Esta cotización no contiene ítems registrados.');
        this.value = '';
        return;
    }

    const selectedOption = this.options[this.selectedIndex];
    const clientName = selectedOption.getAttribute('data-client') || '';
    const currency = selectedOption.getAttribute('data-currency') || 'PEN';
    const total = selectedOption.getAttribute('data-total') || '0';

    const newRows = items.map(it => {
        let desc = (it.description || '').trim();
        if (!desc) {
            desc = it.service_name || 'Entregable cotizado';
        }
        return {
            encargado: '',
            descripcion: desc
        };
    });

    const hasData = currentProcess.rows && currentProcess.rows.some(r => (r.descripcion && r.descripcion.trim() !== '') || (r.encargado && r.encargado.trim() !== ''));

    const applyQuoteData = (append = false) => {
        if (append) {
            currentProcess.rows = currentProcess.rows.concat(newRows);
        } else {
            currentProcess.rows = newRows;
        }

        // Auto-fill client if available
        const clientInput = document.getElementById('clienteName');
        if (clientName && (!clientInput.value || !append)) {
            for (let i = 0; i < clientInput.options.length; i++) {
                if (clientInput.options[i].value.toLowerCase() === clientName.toLowerCase()) {
                    clientInput.selectedIndex = i;
                    populateBrands(clientInput.value, null);
                    break;
                }
            }
        }

        // Auto-fill budget if not filled or on replace
        const budgetInput = document.getElementById('presupuestoAds');
        const currSelect = document.getElementById('monedaPresupuesto');
        if (total && (!budgetInput.value || budgetInput.value === '0.00' || !append)) {
            budgetInput.value = parseFloat(total).toFixed(2);
            if (currSelect) {
                currSelect.value = (currency === 'USD' ? 'USD' : 'S/');
            }
        }

        updateInfoSummary();
        renderProcesos();
        showAppToast(`Cotización #${quoteId} importada exitosamente (${newRows.length} ítems).`);
        this.value = '';
    };

    if (hasData) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: `Cargar Cotización #${quoteId}`,
                text: '¿Deseas reemplazar los entregables actuales o añadirlos al final?',
                icon: 'question',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonColor: '#4f46e5',
                denyButtonColor: '#0ea5e9',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="ph ph-arrows-clockwise"></i> Reemplazar',
                denyButtonText: '<i class="ph ph-plus-circle"></i> Añadir al final',
                cancelButtonText: 'Cancelar',
                background: 'var(--wo-card)',
                color: 'var(--wo-text-main)'
            }).then((result) => {
                if (result.isConfirmed) {
                    applyQuoteData(false);
                } else if (result.isDenied) {
                    applyQuoteData(true);
                } else {
                    this.value = '';
                }
            });
        } else {
            if (confirm('¿Deseas reemplazar los entregables actuales con los de esta cotización?')) {
                applyQuoteData(false);
            } else {
                this.value = '';
            }
        }
        return;
    }

    applyQuoteData(false);
});

// ==========================================================================
// SERVICE SELECTION LOGIC (CARGAR DESDE SERVICIO)
// ==========================================================================
document.getElementById('serviceSelector')?.addEventListener('change', function() {
    const serviceId = this.value;
    if (!serviceId) return;

    const currentProcess = procesosData.find(p => p.id === "main_workflow") || procesosData[0];
    if (!currentProcess) return;

    const features = SERVICES_FEATURES_MAP[serviceId] || [];
    let newRows = [];
    if (features.length > 0) {
        newRows = features.map(f => ({
            encargado: "",
            descripcion: f.title + (f.description ? ": " + f.description : "")
        }));
    } else {
        newRows = [{ encargado: "", descripcion: "Servicio seleccionado sin características registradas." }];
    }

    const hasData = currentProcess.rows && currentProcess.rows.some(r => (r.descripcion && r.descripcion.trim() !== '') || (r.encargado && r.encargado.trim() !== ''));

    const applyFeatures = (append = false) => {
        if (append) {
            currentProcess.rows = currentProcess.rows.concat(newRows);
        } else {
            currentProcess.rows = newRows;
        }
        renderProcesos();
        showAppToast('Entregables del servicio cargados exitosamente.');
        this.value = '';
    };

    if (hasData) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '¿Cómo cargar el servicio?',
                text: '¿Deseas reemplazar los entregables actuales o añadirlos al final?',
                icon: 'question',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonColor: '#4f46e5',
                denyButtonColor: '#0ea5e9',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="ph ph-arrows-clockwise"></i> Reemplazar',
                denyButtonText: '<i class="ph ph-plus-circle"></i> Añadir al final',
                cancelButtonText: 'Cancelar',
                background: 'var(--wo-card)',
                color: 'var(--wo-text-main)'
            }).then((result) => {
                if (result.isConfirmed) {
                    applyFeatures(false);
                } else if (result.isDenied) {
                    applyFeatures(true);
                } else {
                    this.value = '';
                }
            });
        } else {
            if (confirm('¿Reemplazar las filas actuales con las del servicio?')) {
                applyFeatures(false);
            } else {
                this.value = '';
            }
        }
        return;
    }

    applyFeatures(false);
});

// State Capture
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

// Save to DB
async function handleSaveDB() {
    const btn = document.getElementById('saveSnapshotBtn');
    if (!btn) return;

    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
    btn.disabled = true;

    const state = captureFullState();

    const formData = new FormData();
    formData.append('work_order_id', woId);
    formData.append('marca', state.marca || state.cliente || 'Sin Marca');
    formData.append('data', JSON.stringify(state));

    try {
        const response = await fetch('index.php?module=work_orders&action=ajax_save_order', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            showAppToast('Orden guardada correctamente.');
            if (result.redirect_url) {
                setTimeout(() => { window.location.href = result.redirect_url; }, 800);
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
        console.error('Error al guardar:', error);
        alert('Error de conexión con el servidor.');
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }
}

// Public sharing
function shareOrder(token) {
    let currentUrl = window.location.href;
    let baseUrl = currentUrl.split('?')[0];
    const url = baseUrl + '?module=work_orders&action=public&token=' + token;
    const input = document.getElementById('shareLinkInput');
    if (input) input.value = url;
    const modal = document.getElementById('shareModal');
    if (modal) modal.classList.add('active');
}

function copyShareLink() {
    const input = document.getElementById('shareLinkInput');
    if (!input) return;
    input.select();
    document.execCommand('copy');
    showAppToast('Enlace copiado al portapapeles');
}

// Keyboard shortcuts (Ctrl+S / Cmd+S)
window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        handleSaveDB();
    }
});

// Initialization
function initData() {
    if (dbData) {
        const state = dbData;
        if (state.procesos) {
            let mergedRows = [];
            state.procesos.forEach(p => {
                if (p.rows && p.rows.length > 0) {
                    p.rows.forEach(r => {
                        if ((r.descripcion && r.descripcion.trim() !== '') || (r.encargado && r.encargado.trim() !== '')) {
                            mergedRows.push(r);
                        }
                    });
                }
            });
            procesosData = [{ id: "main_workflow", nombre: "FLUJO DE TRABAJO", icono: "ph-kanban", rows: mergedRows }];
        }
        if (state.cliente) document.getElementById('clienteName').value = state.cliente;
        if (state.marca) {
            document.getElementById('marcaName').setAttribute('data-saved', state.marca);
        }
        if (state.redes) {
            try {
                const parsed = JSON.parse(state.redes);
                if (Array.isArray(parsed)) {
                    redesData = parsed;
                } else {
                    throw new Error("Legacy format");
                }
            } catch(e) {
                const arr = state.redes.split(',').map(s => s.trim());
                redesData = arr.filter(Boolean).map(net => ({ id: net, url: '' }));
            }
        }
        if (state.fechaInicio) document.getElementById('fechaInicio').value = state.fechaInicio;
        if (state.prioridad) {
            setPriority(state.prioridad);
        } else {
            setPriority('Media');
        }
        if (state.presupuesto) {
            let val = state.presupuesto;
            if (val.startsWith('S/ ')) {
                if (document.getElementById('monedaPresupuesto')) document.getElementById('monedaPresupuesto').value = 'S/';
                val = val.substring(3);
            } else if (val.startsWith('USD ')) {
                if (document.getElementById('monedaPresupuesto')) document.getElementById('monedaPresupuesto').value = 'USD';
                val = val.substring(4);
            }
            document.getElementById('presupuestoAds').value = val;
        }
        if (state.observaciones) {
            document.getElementById('observacionesGlobal').value = state.observaciones;
            if (quillGlobal) {
                quillGlobal.root.innerHTML = state.observaciones;
            } else {
                setTimeout(() => { if (quillGlobal) quillGlobal.root.innerHTML = state.observaciones; }, 100);
            }
        }
        if (state.customFields && Array.isArray(state.customFields)) {
            customFieldsData = state.customFields;
            renderCustomFields();
        }
    } else {
        procesosData = [{ id: "main_workflow", nombre: "FLUJO DE TRABAJO", icono: "ph-kanban", rows: [] }];
        document.getElementById('fechaInicio').value = new Date().toISOString().split('T')[0];
        setPriority('Media');
    }

    const existing = procesosData.find(p => p.id === "main_workflow");
    if (!existing) {
        procesosData = [{ id: "main_workflow", nombre: "FLUJO DE TRABAJO", icono: "ph-kanban", rows: [] }];
    }

    renderRedes();
    updateInfoSummary();
    renderProcesos();
}

// DOM Ready
document.addEventListener('DOMContentLoaded', () => {
    // Global Quill for Observaciones
    quillGlobal = new Quill('#observacionesContainer', {
        theme: 'snow',
        placeholder: 'Escribe términos, condiciones de entrega, accesos o comentarios generales...',
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

    // Custom fields add button
    const addCfBtn = document.getElementById('add-custom-field-btn');
    if (addCfBtn) {
        addCfBtn.addEventListener('click', () => {
            customFieldsData.push({ name: '', value: '' });
            renderCustomFields();
        });
    }

    // Client change event
    document.getElementById('clienteName').addEventListener('change', function() {
        populateBrands(this.value, null);
        updateInfoSummary();
    });

    // Brand & date change events
    ['marcaName', 'fechaInicio'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', updateInfoSummary);
    });

    // Save button click
    document.getElementById('saveSnapshotBtn')?.addEventListener('click', handleSaveDB);

    // Initialize data
    initData();

    // Populate brands after data restore
    setTimeout(() => {
        const clienteEl = document.getElementById('clienteName');
        const savedBrand = document.getElementById('marcaName').getAttribute('data-saved');
        populateBrands(clienteEl.value, savedBrand);
    }, 300);
});
</script>

<?php require_once 'includes/footer.php'; ?>
