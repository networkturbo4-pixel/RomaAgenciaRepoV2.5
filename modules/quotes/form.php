<?php
// modules/quotes/form.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?module=auth&action=login");
    exit();
}

// Self-healing migration for customization columns
try {
    $existing_cols = $db->query("SHOW COLUMNS FROM quotes")->fetchAll(PDO::FETCH_COLUMN);
    $missing_cols = [];
    if (!in_array('theme_color', $existing_cols)) $missing_cols[] = "ADD COLUMN `theme_color` VARCHAR(50) DEFAULT 'corporate-blue'";
    if (!in_array('cover_image', $existing_cols)) $missing_cols[] = "ADD COLUMN `cover_image` VARCHAR(255) NULL";
    if (!in_array('cover_gradient', $existing_cols)) $missing_cols[] = "ADD COLUMN `cover_gradient` VARCHAR(100) DEFAULT 'mesh-blue'";
    
    if (!empty($missing_cols)) {
        $db->exec("ALTER TABLE `quotes` " . implode(', ', $missing_cols));
    }
} catch (Exception $e) {}

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
/* ==========================================================================
   MODERN QUOTES FORM APP - DESIGN SYSTEM & OLED DARK THEME
   ========================================================================== */

:root {
    --quote-bg: #f8fafc;
    --quote-card: #ffffff;
    --quote-card-sub: #f8fafc;
    --quote-border: #e2e8f0;
    --quote-border-subtle: #f1f5f9;
    --quote-input-bg: #ffffff;
    --quote-text-title: #0f172a;
    --quote-text-main: #334155;
    --quote-text-muted: #64748b;
    --quote-toolbar-bg: rgba(255, 255, 255, 0.92);
    --quote-radius-sm: 8px;
    --quote-radius-md: 12px;
    --quote-radius-lg: 16px;
    --quote-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

[data-theme="dark"] {
    --quote-bg: #000000 !important;
    --quote-card: #0a0a0a !important;
    --quote-card-sub: #111111 !important;
    --quote-border: #1f1f1f !important;
    --quote-border-subtle: #262626 !important;
    --quote-input-bg: #080808 !important;
    --quote-text-title: #ffffff !important;
    --quote-text-main: #e2e8f0 !important;
    --quote-text-muted: #94a3b8 !important;
    --quote-toolbar-bg: rgba(8, 8, 8, 0.92) !important;
    --quote-shadow: none !important;
}

[data-theme="dark"] .quote-form-container {
    background-color: #000000 !important;
}

/* Base Container */
.quote-form-container {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 13px;
    line-height: 1.5;
    color: var(--quote-text-main);
    max-width: 1240px;
    margin: 0 auto;
    padding: 0.5rem 0.75rem 4rem;
    box-sizing: border-box;
}

/* Sticky Floating App Bar */
.quote-app-header {
    position: sticky;
    top: 10px;
    z-index: 95;
    background: var(--quote-toolbar-bg);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--quote-border);
    border-radius: var(--quote-radius-lg);
    padding: 0.75rem 1.25rem;
    margin-bottom: 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--quote-shadow);
    transition: all 0.2s ease;
}

.quote-header-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
}

.btn-back-circle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--quote-card-sub);
    border: 1px solid var(--quote-border);
    color: var(--quote-text-main);
    text-decoration: none;
    font-size: 16px;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.btn-back-circle:hover {
    background: var(--quote-border);
    color: var(--quote-text-title);
    transform: translateX(-2px);
}

.quote-title-group {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.quote-title-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.quote-title-text {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--quote-text-title);
    white-space: nowrap;
    letter-spacing: -0.02em;
}

.quote-code-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.5rem;
    font-size: 11px;
    font-weight: 700;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.12);
    color: #818cf8;
    border: 1px solid rgba(99, 102, 241, 0.2);
    letter-spacing: 0.5px;
}

.quote-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.2rem 0.65rem;
    font-size: 11px;
    font-weight: 600;
    border-radius: 9999px;
    background: var(--quote-card-sub);
    border: 1px solid var(--quote-border);
    color: var(--quote-text-main);
}
.quote-status-pill .dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #94a3b8;
}

.quote-status-pill.status-borrador .dot { background: #f59e0b; }
.quote-status-pill.status-enviada .dot { background: #3b82f6; box-shadow: 0 0 8px #3b82f6; }
.quote-status-pill.status-aceptada .dot { background: #10b981; box-shadow: 0 0 8px #10b981; }
.quote-status-pill.status-rechazada .dot { background: #ef4444; }

.quote-subtitle-text {
    margin: 0;
    font-size: 11.5px;
    color: var(--quote-text-muted);
    display: block;
}

.quote-header-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-shrink: 0;
}

/* Action Buttons */
.btn-app-secondary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1rem;
    font-size: 12.5px;
    font-weight: 600;
    border-radius: 10px;
    background: var(--quote-card-sub);
    border: 1px solid var(--quote-border);
    color: var(--quote-text-main);
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    min-height: 40px;
}
.btn-app-secondary:hover {
    background: var(--quote-border);
    color: var(--quote-text-title);
}

.btn-app-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.25rem;
    font-size: 12.5px;
    font-weight: 600;
    border-radius: 10px;
    background: var(--primary-color, #4f46e5);
    color: #ffffff;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--primary-color, #4f46e5) 35%, transparent);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    min-height: 40px;
}
.btn-app-primary:hover {
    filter: brightness(1.08);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px color-mix(in srgb, var(--primary-color, #4f46e5) 45%, transparent);
}
.btn-app-primary:active {
    transform: translateY(0);
}
.btn-app-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Cards (Glass / Surface) */
.app-section-card {
    background: var(--quote-card);
    border: 1px solid var(--quote-border);
    border-radius: var(--quote-radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.25rem;
    box-shadow: var(--quote-shadow);
    transition: border-color 0.2s ease;
}

/* Card Headers */
.card-header-app {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--quote-border);
    position: relative;
}
.card-icon-tile {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.card-icon-tile.icon-indigo { background: rgba(99, 102, 241, 0.14); color: #818cf8; }
.card-icon-tile.icon-emerald { background: rgba(16, 185, 129, 0.14); color: #34d399; }
.card-icon-tile.icon-purple { background: rgba(168, 85, 247, 0.14); color: #c084fc; }
.card-icon-tile.icon-orange { background: rgba(249, 115, 22, 0.14); color: #fb923c; }

.card-title-content {
    flex: 1;
    min-width: 0;
}
.card-title-content h3 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--quote-text-title);
    letter-spacing: -0.01em;
}
.card-title-content p {
    margin: 0.15rem 0 0;
    font-size: 11.5px;
    color: var(--quote-text-muted);
}

.mobile-swipe-badge {
    font-size: 10px;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    background: var(--quote-card-sub);
    border: 1px solid var(--quote-border);
    color: var(--quote-text-muted);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

/* Inputs & Form Elements */
.field-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--quote-text-muted);
    margin-bottom: 0.4rem;
}
.field-label span.optional-note {
    text-transform: none;
    font-weight: normal;
    color: var(--quote-text-muted);
    opacity: 0.8;
}

.app-input {
    width: 100%;
    background: var(--quote-input-bg);
    border: 1px solid var(--quote-border);
    border-radius: var(--quote-radius-sm);
    padding: 0.6rem 0.85rem;
    font-size: 13px;
    color: var(--quote-text-main);
    transition: all 0.2s ease;
    box-sizing: border-box;
    outline: none;
    min-height: 40px;
}
.app-input:focus {
    border-color: var(--primary-color, #4f46e5);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
}
.app-input::placeholder {
    color: var(--quote-text-muted);
    opacity: 0.6;
}

/* General Data Responsive Grid */
.general-data-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
.grid-col-full {
    grid-column: 1 / -1;
}

/* Catalog Import Bar */
.catalog-import-bar {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}
.catalog-select-wrapper {
    flex: 1;
    min-width: 240px;
    position: relative;
    display: flex;
    align-items: center;
}
.catalog-select-icon {
    position: absolute;
    left: 12px;
    font-size: 1rem;
    color: var(--quote-text-muted);
    pointer-events: none;
}
.catalog-select {
    padding-left: 2.25rem;
    cursor: pointer;
}
.btn-import-catalog {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.6rem 1.15rem;
    font-size: 12.5px;
    font-weight: 600;
    border-radius: var(--quote-radius-sm);
    background: var(--quote-card-sub);
    border: 1px solid var(--quote-border);
    color: var(--quote-text-main);
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: 40px;
    white-space: nowrap;
}
.btn-import-catalog:hover {
    background: var(--quote-border);
    color: var(--quote-text-title);
    border-color: var(--quote-border-subtle);
}

/* Items List & Item Card */
.items-list-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.item-card {
    background: var(--quote-card-sub);
    border: 1px solid var(--quote-border);
    border-radius: var(--quote-radius-md);
    padding: 1.15rem;
    position: relative;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.item-card:hover {
    border-color: var(--quote-border-subtle);
}

.item-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
    flex-wrap: wrap;
}

.item-header-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.partida-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.6rem;
    font-size: 11px;
    font-weight: 700;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.12);
    color: #818cf8;
    border: 1px solid rgba(99, 102, 241, 0.2);
    letter-spacing: 0.3px;
}

.item-icon-select {
    font-size: 12px;
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    background: var(--quote-input-bg);
    border: 1px solid var(--quote-border);
    color: var(--quote-text-main);
    cursor: pointer;
    min-height: 30px;
    outline: none;
}
.item-icon-select:focus {
    border-color: var(--primary-color, #4f46e5);
}

.btn-delete-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    font-size: 12px;
    font-weight: 600;
    border-radius: 6px;
    background: transparent;
    border: 1px solid transparent;
    color: #ef4444;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-delete-item:hover {
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.25);
    color: #f87171;
}

/* WYSIWYG Editor */
.item-editor-container {
    border: 1px solid var(--quote-border);
    border-radius: var(--quote-radius-sm);
    margin-bottom: 0.85rem;
    overflow: hidden;
    background: var(--quote-card);
}

.item-editor-toolbar {
    background: var(--quote-card-sub);
    border-bottom: 1px solid var(--quote-border);
    padding: 0.35rem 0.5rem;
    display: flex;
    align-items: center;
    gap: 3px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.item-editor-toolbar::-webkit-scrollbar {
    display: none;
}

.editor-btn {
    background: transparent;
    border: none;
    color: var(--quote-text-muted);
    cursor: pointer;
    padding: 0.35rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    font-size: 1.05rem;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.editor-btn:hover {
    background: var(--quote-border);
    color: var(--quote-text-title);
}

.editor-font-select {
    border: 1px solid var(--quote-border);
    border-radius: 6px;
    padding: 0.2rem 0.4rem;
    font-size: 11.5px;
    color: var(--quote-text-muted);
    background: var(--quote-input-bg);
    cursor: pointer;
    outline: none;
    height: 28px;
    flex-shrink: 0;
}

.editor-divider {
    width: 1px;
    height: 1.15rem;
    background: var(--quote-border);
    margin: 0 0.25rem;
    flex-shrink: 0;
}

.item-textarea {
    width: 100%;
    border: none;
    padding: 0.75rem 0.85rem;
    font-family: inherit;
    font-size: 13px;
    line-height: 1.6;
    resize: vertical;
    min-height: 85px;
    color: var(--quote-text-main);
    background: transparent;
    outline: none;
    box-sizing: border-box;
}
.item-textarea ul, .item-textarea ol {
    padding-left: 1.4rem;
    margin: 0.4rem 0;
}
.item-textarea li {
    margin-bottom: 0.2rem;
}

/* Modern Quote Tables inside Partidas */
.quote-table-wrapper {
    margin: 0.85rem 0;
    border: 1px solid var(--quote-border);
    border-radius: var(--quote-radius-md, 10px);
    background: var(--quote-card);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    overflow: hidden;
    position: relative;
    user-select: text;
}

.quote-table-actions {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 10px;
    background: var(--quote-card-sub);
    border-bottom: 1px solid var(--quote-border);
    font-size: 11px;
    user-select: none;
    flex-wrap: wrap;
}

.btn-tbl-action {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid var(--quote-border);
    background: var(--quote-card);
    color: var(--quote-text-main);
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    line-height: 1.2;
}

.btn-tbl-action:hover {
    background: var(--quote-border);
    color: var(--quote-text-title);
}

.btn-tbl-action.btn-tbl-del:hover {
    background: #fee2e2;
    color: #b91c1c;
    border-color: #fca5a5;
}

.btn-tbl-action.btn-tbl-danger {
    margin-left: auto;
    color: #ef4444;
}

.btn-tbl-action.btn-tbl-danger:hover {
    background: #ef4444;
    color: #ffffff;
    border-color: #ef4444;
}

.quote-modern-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
    line-height: 1.5;
    text-align: left;
    background: transparent;
    margin: 0;
}

.quote-modern-table th {
    background: var(--quote-card-sub);
    color: var(--quote-text-title);
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 8px 12px;
    border-bottom: 1px solid var(--quote-border);
    white-space: nowrap;
}

.quote-modern-table td {
    padding: 8px 12px;
    color: var(--quote-text-main);
    border-bottom: 1px solid var(--quote-border-subtle);
    vertical-align: middle;
    transition: background 0.1s ease;
    min-width: 60px;
    outline: none;
}

.quote-modern-table td:focus,
.quote-modern-table th:focus {
    background: rgba(14, 165, 233, 0.06);
    box-shadow: inset 0 0 0 1.5px #0ea5e9;
}

.quote-modern-table tbody tr:last-child td {
    border-bottom: none;
}

.quote-modern-table tbody tr:hover td {
    background: rgba(0, 0, 0, 0.015);
}

[data-theme="dark"] .quote-modern-table tbody tr:hover td {
    background: rgba(255, 255, 255, 0.03);
}

[data-theme="dark"] .quote-modern-table td:focus,
[data-theme="dark"] .quote-modern-table th:focus {
    background: rgba(14, 165, 233, 0.12);
}

/* Preset Cards in SweetAlert */
.table-preset-card {
    transition: all 0.15s ease;
}
.table-preset-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

/* Theme and Cover Selector in Form */
.quote-theme-selector-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 0.65rem;
}
.quote-theme-card {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.6rem 0.8rem;
    border-radius: var(--quote-radius-sm);
    background: var(--quote-card);
    border: 1px solid var(--quote-border);
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
}
.quote-theme-card:hover {
    border-color: var(--quote-border-subtle);
    background: var(--quote-card-sub);
    transform: translateY(-1px);
}
.quote-theme-card.active {
    border-color: #0ea5e9;
    background: var(--quote-card-sub);
    box-shadow: 0 0 0 1.5px #0ea5e9;
}
.theme-card-color-dot {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 11px;
    flex-shrink: 0;
}
.theme-card-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.theme-card-title {
    font-size: 12px;
    font-weight: 600;
    color: var(--quote-text-title);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.theme-card-desc {
    font-size: 10px;
    color: var(--quote-text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.cover-live-preview-box {
    width: 100%;
    height: 95px;
    border-radius: var(--quote-radius-sm);
    position: relative;
    overflow: hidden;
    border: 1px solid var(--quote-border);
    transition: all 0.3s ease;
    background-size: cover;
    background-position: center;
}
.cover-preview-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.2) 100%);
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    padding: 0.65rem 0.85rem;
}
.cover-preview-tag {
    font-size: 11px;
    font-weight: 600;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 4px;
    text-shadow: 0 1px 3px rgba(0,0,0,0.6);
}
.cover-quick-actions {
    display: flex;
    gap: 6px;
}
.btn-cover-action {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    background: rgba(255,255,255,0.9);
    color: #0f172a;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.15s ease;
    text-decoration: none;
}
.btn-cover-action:hover {
    background: #ffffff;
    transform: translateY(-1px);
}
.btn-cover-action.btn-cover-remove {
    background: rgba(0,0,0,0.55);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.2);
}
.btn-cover-action.btn-cover-remove:hover {
    background: rgba(239, 68, 68, 0.85);
}

.gradient-presets-strip {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 0.65rem;
    flex-wrap: wrap;
}
.presets-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--quote-text-muted);
}
.btn-gradient-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 9px;
    border-radius: 20px;
    border: 1px solid var(--quote-border);
    background: var(--quote-card);
    color: var(--quote-text-main);
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}
.btn-gradient-chip:hover {
    background: var(--quote-card-sub);
    border-color: #0ea5e9;
}
.btn-gradient-chip.active {
    border-color: #0ea5e9;
    background: var(--quote-card-sub);
    font-weight: 600;
}
.chip-color-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.chip-color-dot.grad-mesh-blue { background: linear-gradient(135deg, #2563eb, #8b5cf6); }
.chip-color-dot.grad-emerald-glow { background: linear-gradient(135deg, #10b981, #06b6d4); }
.chip-color-dot.grad-creative-violet { background: linear-gradient(135deg, #8b5cf6, #ec4899); }
.chip-color-dot.grad-sunset-gold { background: linear-gradient(135deg, #f59e0b, #ef4444); }
.chip-color-dot.grad-cyber-dark { background: linear-gradient(135deg, #18181b, #3f3f46); }
.chip-color-dot.grad-minimal-clean { background: linear-gradient(135deg, #64748b, #94a3b8); }

/* Metrics & Schedule Layout (Responsive Core) */
.item-metrics-layout {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 0.85rem;
    align-items: stretch;
}

.item-amounts-box {
    background: var(--quote-card);
    border: 1px solid var(--quote-border);
    border-radius: var(--quote-radius-sm);
    padding: 0.75rem 0.9rem;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.65rem;
    align-items: flex-end;
}

.item-schedule-box {
    background: var(--quote-card);
    border: 1px solid var(--quote-border);
    border-radius: var(--quote-radius-sm);
    padding: 0.75rem 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.schedule-header {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 10.5px;
    font-weight: 700;
    color: var(--quote-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.schedule-inputs-row {
    display: grid;
    grid-template-columns: 1fr 75px;
    gap: 0.5rem;
    align-items: flex-end;
}

.item-mini-label {
    display: block;
    font-size: 10.5px;
    font-weight: 700;
    color: var(--quote-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 0.3rem;
    white-space: nowrap;
}

.item-total-display {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--quote-text-title);
    min-height: 40px;
    display: flex;
    align-items: center;
    white-space: nowrap;
}

/* Add Item Button */
.btn-add-partida {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: 2px dashed var(--quote-border);
    border-radius: var(--quote-radius-md);
    padding: 0.9rem;
    background: transparent;
    color: var(--primary-color, #4f46e5);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-bottom: 1.25rem;
}
.btn-add-partida:hover {
    border-color: var(--primary-color, #4f46e5);
    background: rgba(99, 102, 241, 0.04);
}

/* Totals Card */
.totals-container {
    background: var(--quote-card-sub);
    border: 1px solid var(--quote-border);
    border-radius: var(--quote-radius-md);
    padding: 1.25rem 1.5rem;
    margin-left: auto;
    max-width: 380px;
    box-sizing: border-box;
}
.totals-breakdown {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.totals-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
.totals-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--quote-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.tax-rate-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.tax-input {
    width: 65px;
    height: 30px;
    min-height: 30px;
    padding: 0.2rem 0.4rem;
    text-align: center;
    font-weight: 700;
}
.totals-val {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--quote-text-title);
    white-space: nowrap;
}
.totals-divider {
    height: 1px;
    background: var(--quote-border);
    margin: 0.25rem 0;
}
.grand-total-row .totals-label {
    font-size: 13px;
    color: var(--quote-text-title);
}
.grand-total-val {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary-color, #4f46e5);
    white-space: nowrap;
}

/* Gantt Chart Styling */
.gantt-scroll-container {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: var(--quote-radius-sm);
    padding: 0.5rem 0;
}

.gantt-empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--quote-text-muted);
}
.empty-icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--quote-card-sub);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--quote-text-muted);
    margin-bottom: 0.75rem;
}
.gantt-empty-state h4 {
    margin: 0 0 0.25rem;
    font-size: 0.95rem;
    color: var(--quote-text-title);
}
.gantt-empty-state p {
    margin: 0;
    font-size: 12px;
}

/* Frappe Gantt Dark Mode Overrides */
[data-theme="dark"] #gantt_here .gantt .grid-header { fill: #0a0a0a; stroke: #1f1f1f; }
[data-theme="dark"] #gantt_here .gantt .grid-row { fill: #000000; }
[data-theme="dark"] #gantt_here .gantt .grid-row:nth-child(even) { fill: #0a0a0a; }
[data-theme="dark"] #gantt_here .gantt .lower-text,
[data-theme="dark"] #gantt_here .gantt .upper-text { fill: #94a3b8; font-size: 11px; }
[data-theme="dark"] #gantt_here .gantt .row-line,
[data-theme="dark"] #gantt_here .gantt .tick { stroke: #1f1f1f; }
[data-theme="dark"] #gantt_here .gantt .today-highlight { fill: rgba(99, 102, 241, 0.12); }

/* Notes & Conditions Grid */
.notes-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}
.app-textarea {
    resize: vertical;
    min-height: 100px;
    line-height: 1.6;
}

/* Payment Methods Toggle */
.payment-methods-toggle-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    background: var(--quote-card-sub);
    border: 1px solid var(--quote-border);
    border-radius: var(--quote-radius-md);
    margin-bottom: 1.25rem;
    gap: 1rem;
}
.toggle-content {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.toggle-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(99, 102, 241, 0.12);
    color: #818cf8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.toggle-title {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: var(--quote-text-title);
}
.toggle-subtitle {
    display: block;
    font-size: 11.5px;
    color: var(--quote-text-muted);
}

/* Modern Switch */
.ios-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
    cursor: pointer;
}
.ios-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.ios-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: var(--quote-border);
    transition: 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    border-radius: 24px;
}
.ios-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.ios-switch input:checked + .ios-slider {
    background-color: var(--primary-color, #4f46e5);
}
.ios-switch input:checked + .ios-slider:before {
    transform: translateX(20px);
}

/* Bank Grid */
.bank-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 0.85rem;
    margin-bottom: 1rem;
}
.bank-card {
    display: flex;
    align-items: center;
    background: var(--quote-card-sub);
    border: 1px solid var(--quote-border);
    border-radius: var(--quote-radius-md);
    padding: 0.85rem 1rem;
    gap: 0.85rem;
    transition: all 0.2s ease;
}
.bank-card:focus-within {
    border-color: var(--primary-color, #4f46e5);
}
.bank-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: white;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    flex-shrink: 0;
}
.bank-icon.bcp { background: #ff7800; }
.bank-icon.yape { background: #742284; }
.bank-icon.ibk { background: #007a33; }
.bank-icon.sco { background: #ed1c24; }

.bank-details {
    flex: 1;
    min-width: 0;
}
.bank-name {
    font-size: 11px;
    color: var(--quote-text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 0.15rem;
}
.bank-account {
    font-size: 1.05rem;
    color: var(--quote-text-title);
    font-weight: 800;
    width: 100%;
    border: none;
    outline: none;
    background: transparent;
    padding: 0;
    letter-spacing: 0.5px;
}

.bank-owner-box {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0.85rem;
    background: var(--quote-card-sub);
    border: 1px solid var(--quote-border);
    border-radius: var(--quote-radius-sm);
    font-size: 12px;
    color: var(--quote-text-muted);
    flex-wrap: wrap;
}
.bank-owner-input {
    border: none;
    outline: none;
    background: transparent;
    font-weight: 700;
    color: var(--quote-text-title);
    flex: 1;
    min-width: 180px;
    font-size: 12.5px;
}

/* ==========================================================================
   RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES)
   ========================================================================== */

@media (max-width: 992px) {
    .general-data-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .notes-grid {
        grid-template-columns: 1fr;
    }
    .item-metrics-layout {
        grid-template-columns: 1fr;
    }
    .totals-container {
        max-width: 100%;
    }
}

@media (max-width: 640px) {
    .quote-form-container {
        padding: 0.35rem 0.5rem 5rem;
    }
    .quote-app-header {
        top: 5px;
        padding: 0.65rem 0.85rem;
        margin-bottom: 0.85rem;
        border-radius: var(--quote-radius-md);
    }
    .quote-title-text {
        font-size: 1rem;
    }
    .quote-subtitle-text {
        display: none;
    }
    .btn-app-secondary .btn-text {
        display: none;
    }
    .btn-app-secondary {
        padding: 0.55rem;
        width: 38px;
        justify-content: center;
    }
    .btn-app-primary {
        padding: 0.55rem 0.85rem;
        font-size: 12px;
    }

    .app-section-card {
        padding: 1rem;
        border-radius: var(--quote-radius-md);
        margin-bottom: 0.85rem;
    }
    .general-data-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .catalog-import-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .btn-import-catalog {
        justify-content: center;
    }

    .item-card {
        padding: 0.85rem;
    }
    .item-amounts-box {
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
    .amounts-col.total-col {
        grid-column: span 1;
    }

    .bank-grid {
        grid-template-columns: 1fr;
    }
    .bank-account {
        font-size: 0.95rem;
    }
}
</style>

<div class="quote-form-container">
    <!-- Sticky Floating App Header -->
    <header class="quote-app-header">
        <div class="quote-header-left">
            <a href="index.php?module=quotes&action=index" class="btn-back-circle" title="Volver a Cotizaciones">
                <i class="ph ph-arrow-left"></i>
            </a>
            <div class="quote-title-group">
                <div class="quote-title-row">
                    <h1 class="quote-title-text">
                        <?php echo $id ? 'Editar Cotización' : 'Nueva Cotización'; ?>
                    </h1>
                    <?php if ($id): ?>
                        <span class="quote-code-chip">#<?php echo str_pad($id, 4, '0', STR_PAD_LEFT); ?></span>
                    <?php endif; ?>
                    <span class="quote-status-pill status-<?php echo strtolower($quote['status'] ?? 'borrador'); ?>" id="toolbarStatusPill">
                        <span class="dot"></span>
                        <span id="toolbarStatusText"><?php echo htmlspecialchars($quote['status'] ?? 'Borrador'); ?></span>
                    </span>
                </div>
                <span class="quote-subtitle-text">Gestión comercial, entregables y cronograma de servicios</span>
            </div>
        </div>
        <div class="quote-header-actions">
            <a href="index.php?module=quotes&action=index" class="btn-app-secondary" title="Volver al listado">
                <i class="ph ph-arrow-left"></i>
                <span class="btn-text">Volver</span>
            </a>
            <button id="btnSaveQuote" class="btn-app-primary">
                <i class="ph ph-floppy-disk"></i>
                <span>Guardar Cotización</span>
            </button>
        </div>
    </header>

    <form id="quoteForm" onsubmit="return false;">
        <input type="hidden" id="quote_id" value="<?php echo $id; ?>">

        <!-- 1. Datos Generales -->
        <section class="app-section-card">
            <div class="card-header-app">
                <div class="card-icon-tile icon-indigo">
                    <i class="ph ph-info"></i>
                </div>
                <div class="card-title-content">
                    <h3>Datos Generales</h3>
                    <p>Identificación del cliente, divisa y fechas de vigencia de la cotización</p>
                </div>
            </div>

            <div class="general-data-grid">
                <!-- Cliente (Full row) -->
                <div class="grid-col-full">
                    <label class="field-label" for="client_name">
                        CLIENTE * <span class="optional-note">(Seleccione del catálogo o escriba un nombre nuevo)</span>
                    </label>
                    <input type="text" id="client_name" class="app-input" list="clientsList" value="<?php echo $quote ? htmlspecialchars(current(array_filter($clients, function($c) use($quote) { return $c['id'] == $quote['client_id']; }))['name'] ?? '') : ''; ?>" placeholder="Escribir o buscar cliente..." required autocomplete="off">
                    <datalist id="clientsList">
                        <?php foreach($clients as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['name']); ?>" data-id="<?php echo $c['id']; ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <!-- Moneda -->
                <div>
                    <label class="field-label" for="currency">MONEDA</label>
                    <select id="currency" class="app-input">
                        <option value="PEN" <?php echo (!$quote || $quote['currency'] === 'PEN') ? 'selected' : ''; ?>>PEN (S/)</option>
                        <option value="USD" <?php echo ($quote && $quote['currency'] === 'USD') ? 'selected' : ''; ?>>USD ($)</option>
                    </select>
                </div>

                <!-- Estado -->
                <div>
                    <label class="field-label" for="status">ESTADO</label>
                    <select id="status" class="app-input">
                        <option value="Borrador" <?php echo ($quote && $quote['status'] === 'Borrador') ? 'selected' : ''; ?>>Borrador</option>
                        <option value="Enviada" <?php echo ($quote && $quote['status'] === 'Enviada') ? 'selected' : ''; ?>>Enviada</option>
                        <option value="Aceptada" <?php echo ($quote && $quote['status'] === 'Aceptada') ? 'selected' : ''; ?>>Aceptada</option>
                        <option value="Rechazada" <?php echo ($quote && $quote['status'] === 'Rechazada') ? 'selected' : ''; ?>>Rechazada</option>
                    </select>
                </div>

                <!-- Fecha de Emisión -->
                <div>
                    <label class="field-label" for="issue_date">FECHA DE EMISIÓN</label>
                    <input type="date" id="issue_date" class="app-input" value="<?php echo $quote ? $quote['issue_date'] : date('Y-m-d'); ?>">
                </div>

                <!-- Fecha de Vencimiento -->
                <div>
                    <label class="field-label" for="due_date">FECHA DE VENCIMIENTO</label>
                    <input type="date" id="due_date" class="app-input" value="<?php echo $quote ? $quote['due_date'] : date('Y-m-d', strtotime('+15 days')); ?>">
                </div>

                <!-- Selector de Tema de Color y Portada -->
                <div class="grid-col-full" style="margin-top: 0.5rem; padding-top: 1rem; border-top: 1px dashed var(--quote-border);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <span class="field-label" style="margin-bottom: 0.2rem;">ESTILO VISUAL & MARCA DE LA PROPUESTA</span>
                            <span style="font-size: 11.5px; color: var(--quote-text-muted);">Personaliza el color de acento y la portada ejecutiva visible para el cliente</span>
                        </div>
                    </div>

                    <!-- Color Themes Grid -->
                    <div class="quote-theme-selector-grid">
                        <?php 
                        $current_theme = $quote['theme_color'] ?? 'corporate-blue';
                        $themes_available = [
                            'corporate-blue' => ['label' => 'Azul Corporativo', 'color' => '#2563eb', 'sub' => 'Tecnología & Finanzas'],
                            'emerald' => ['label' => 'Esmeralda Tech', 'color' => '#10b981', 'sub' => 'Crecimiento & Innovación'],
                            'violet' => ['label' => 'Violeta Creativo', 'color' => '#8b5cf6', 'sub' => 'Agencia & Diseño'],
                            'minimal-black' => ['label' => 'Negro Minimalista', 'color' => '#18181b', 'sub' => 'Lujo & Consultoría'],
                            'amber-gold' => ['label' => 'Ámbar Ejecutivo', 'color' => '#f59e0b', 'sub' => 'Prestigio Comercial'],
                            'crimson' => ['label' => 'Carmín / Crimson', 'color' => '#f43f5e', 'sub' => 'Impacto & Marca'],
                        ];
                        foreach($themes_available as $tKey => $tData): 
                            $isActive = ($current_theme === $tKey);
                        ?>
                            <div class="quote-theme-card <?php echo $isActive ? 'active' : ''; ?>" onclick="selectQuoteTheme('<?php echo $tKey; ?>', this)">
                                <div class="theme-card-color-dot" style="background: <?php echo $tData['color']; ?>; box-shadow: 0 0 10px <?php echo $tData['color']; ?>66;">
                                    <i class="ph-bold ph-check" style="<?php echo $isActive ? 'display:inline-block;' : 'display:none;'; ?>"></i>
                                </div>
                                <div class="theme-card-text">
                                    <span class="theme-card-title"><?php echo $tData['label']; ?></span>
                                    <span class="theme-card-desc"><?php echo $tData['sub']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="theme_color" value="<?php echo htmlspecialchars($current_theme); ?>">

                    <!-- Cover Banner Selector -->
                    <div class="quote-cover-settings-box" style="margin-top: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                            <label class="field-label" style="margin-bottom:0;">PORTADA O BANNER DE CABECERA (OPCIONAL)</label>
                            <span id="coverBadgeText" style="font-size: 11px; padding: 2px 8px; border-radius: 6px; background: var(--quote-card-sub); border: 1px solid var(--quote-border); color: var(--quote-text-muted);">
                                <?php echo !empty($quote['cover_image']) ? 'Imagen personalizada activa' : (!empty($quote['cover_gradient']) && $quote['cover_gradient'] !== 'none' ? 'Degradado estético activo' : 'Sin portada'); ?>
                            </span>
                        </div>

                        <!-- Live Banner Preview Bar -->
                        <div id="coverLivePreview" class="cover-live-preview-box">
                            <div class="cover-preview-overlay">
                                <span class="cover-preview-tag"><i class="ph ph-sparkle"></i> Vista Previa del Banner</span>
                                <div class="cover-quick-actions">
                                    <label class="btn-cover-action" for="coverFileInput" title="Subir imagen desde PC">
                                        <i class="ph ph-upload-simple"></i> Subir Imagen
                                    </label>
                                    <button type="button" class="btn-cover-action btn-cover-remove" onclick="removeCoverBanner()" title="Quitar Portada">
                                        <i class="ph ph-x"></i> Sin Portada
                                    </button>
                                </div>
                            </div>
                        </div>
                        <input type="file" id="coverFileInput" style="display:none;" accept="image/*" onchange="uploadCoverFile(this)">

                        <!-- Gradient Presets Pills -->
                        <div class="gradient-presets-strip">
                            <span class="presets-label">Degradados:</span>
                            <?php 
                            $current_grad = $quote['cover_gradient'] ?? 'mesh-blue';
                            $grad_presets = [
                                'mesh-blue' => 'Mesh Azul',
                                'emerald-glow' => 'Emerald Glow',
                                'creative-violet' => 'Violeta Deep',
                                'sunset-gold' => 'Sunset Gold',
                                'cyber-dark' => 'Cyber Dark',
                                'minimal-clean' => 'Minimal Slate',
                            ];
                            foreach($grad_presets as $gKey => $gName): 
                                $isGradActive = (empty($quote['cover_image']) && $current_grad === $gKey);
                            ?>
                                <button type="button" class="btn-gradient-chip <?php echo $isGradActive ? 'active' : ''; ?>" data-grad="<?php echo $gKey; ?>" onclick="selectCoverGradient('<?php echo $gKey; ?>', this)">
                                    <span class="chip-color-dot grad-<?php echo $gKey; ?>"></span>
                                    <span><?php echo $gName; ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <input type="hidden" id="cover_gradient" value="<?php echo htmlspecialchars($current_grad); ?>">
                        <input type="hidden" id="cover_image" value="<?php echo htmlspecialchars($quote['cover_image'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. Detalles de Servicios (Partidas) -->
        <section class="app-section-card">
            <div class="card-header-app">
                <div class="card-icon-tile icon-emerald">
                    <i class="ph ph-list-numbers"></i>
                </div>
                <div class="card-title-content">
                    <h3>Detalles de Servicios (Partidas)</h3>
                    <p>Especificación de servicios, importes unitarios, descuentos y programación</p>
                </div>
            </div>

            <!-- Selector de Catálogo -->
            <div class="catalog-import-bar">
                <div class="catalog-select-wrapper">
                    <i class="ph ph-sparkle catalog-select-icon"></i>
                    <select id="serviceSelector" class="app-input catalog-select">
                        <option value="">Importar desde catálogo de servicios...</option>
                        <?php foreach($services as $s): ?>
                            <option value="<?php echo $s['id']; ?>" data-price="<?php echo $s['price']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn-import-catalog" onclick="addServiceFromCatalog()">
                    <i class="ph ph-download-simple"></i>
                    <span>Importar Servicio</span>
                </button>
            </div>

            <!-- Listado dinámico de partidas -->
            <div id="itemsContainer" class="items-list-container">
                <!-- Generado por JavaScript -->
            </div>

            <!-- Botón Añadir Fila -->
            <button type="button" class="btn-add-partida" onclick="addEmptyRow()">
                <i class="ph ph-plus-circle" style="font-size: 1.25rem;"></i>
                <span>Añadir Nueva Partida</span>
            </button>

            <!-- Desglose de Totales -->
            <div class="totals-container">
                <div class="totals-breakdown">
                    <div class="totals-row">
                        <span class="totals-label">SUBTOTAL:</span>
                        <span id="calcSubtotal" class="totals-val">0.00</span>
                    </div>
                    <div class="totals-row">
                        <span class="totals-label tax-rate-label">
                            IGV/TAX (%):
                            <input type="number" id="tax_rate" class="app-input tax-input" value="<?php echo $quote ? ($quote['subtotal'] > 0 ? (int)(($quote['tax']/$quote['subtotal'])*100) : 0) : 0; ?>" onchange="calculateTotals()">
                        </span>
                        <span id="calcTax" class="totals-val">0.00</span>
                    </div>
                    <div class="totals-divider"></div>
                    <div class="totals-row grand-total-row">
                        <span class="totals-label">TOTAL:</span>
                        <span id="calcTotal" class="grand-total-val">0.00</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. Cronograma del Proyecto (Gantt) -->
        <section class="app-section-card" id="ganttSectionContainer">
            <div class="card-header-app">
                <div class="card-icon-tile icon-purple">
                    <i class="ph ph-calendar"></i>
                </div>
                <div class="card-title-content">
                    <h3>Cronograma del Proyecto (Gantt)</h3>
                    <p>Línea temporal dinámica generada a partir de las fechas y duración de cada partida</p>
                </div>
                <span class="mobile-swipe-badge d-md-none"><i class="ph ph-arrows-horizontal"></i> Desliza horizontal</span>
            </div>

            <div class="gantt-scroll-container">
                <div id="gantt_here"></div>
                <div id="gantt_empty_state" class="gantt-empty-state">
                    <div class="empty-icon-circle">
                        <i class="ph ph-chart-bar"></i>
                    </div>
                    <h4>Sin cronograma registrado</h4>
                    <p>Establece fechas de inicio y días de duración en las partidas para visualizar el diagrama interactivo.</p>
                </div>
            </div>
        </section>

        <!-- 4. Notas y Condiciones -->
        <section class="app-section-card">
            <div class="card-header-app">
                <div class="card-icon-tile icon-orange">
                    <i class="ph ph-note-pencil"></i>
                </div>
                <div class="card-title-content">
                    <h3>Notas y Condiciones</h3>
                    <p>Información visible al cliente, términos comerciales y cuentas bancarias</p>
                </div>
            </div>

            <div class="notes-grid">
                <div>
                    <label class="field-label" for="notes">NOTAS ADICIONALES</label>
                    <textarea id="notes" class="app-input app-textarea" rows="4" placeholder="Observaciones o notas visibles para el cliente..."><?php echo $quote ? htmlspecialchars($quote['notes']) : ''; ?></textarea>
                </div>
                <div>
                    <label class="field-label" for="terms_conditions">TÉRMINOS Y CONDICIONES</label>
                    <textarea id="terms_conditions" class="app-input app-textarea" rows="4" placeholder="Ej: Válido por 15 días..."><?php echo $quote ? htmlspecialchars($quote['terms_conditions']) : "1. La presente cotización tiene una validez de 15 días.\n2. Para iniciar el proyecto se requiere un abono del 50% y el saldo contra entrega.\n3. Los tiempos de entrega corren a partir de la recepción de todo el material necesario."; ?></textarea>
                </div>
            </div>

            <!-- Switch Métodos de Pago -->
            <div class="payment-methods-toggle-box">
                <div class="toggle-content">
                    <div class="toggle-icon"><i class="ph ph-credit-card"></i></div>
                    <div>
                        <span class="toggle-title">Mostrar Métodos de Pago</span>
                        <span class="toggle-subtitle">Incluir las cuentas bancarias en la cotización generada</span>
                    </div>
                </div>
                <label class="ios-switch">
                    <input type="checkbox" id="show_payment_methods" <?php echo ($quote && $quote['show_payment_methods']) ? 'checked' : ''; ?>>
                    <span class="ios-slider"></span>
                </label>
            </div>

            <!-- Cuentas Bancarias -->
            <div id="payment_methods_container" style="display: <?php echo ($quote && $quote['show_payment_methods']) ? 'block' : 'none'; ?>;">
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
                <div class="bank-owner-box">
                    <span><i class="ph ph-user"></i> Cuentas a nombre de:</span>
                    <input type="text" id="bank_owner" value="Cesar A. Mendoza Castro" class="bank-owner-input" placeholder="Nombre del titular...">
                </div>
                <textarea id="payment_methods_text" style="display:none;"></textarea>
            </div>
        </section>
    </form>
</div>

<script>
let ganttChart = null;
let itemsData = <?php echo json_encode($quote_items); ?>;
if (!Array.isArray(itemsData)) itemsData = [];

// Toggle payment methods
document.getElementById('show_payment_methods').addEventListener('change', function() {
    document.getElementById('payment_methods_container').style.display = this.checked ? 'block' : 'none';
});

// Update Status Pill in Header dynamically
document.getElementById('status').addEventListener('change', function() {
    const val = this.value;
    const pill = document.getElementById('toolbarStatusPill');
    const text = document.getElementById('toolbarStatusText');
    if (pill && text) {
        pill.className = 'quote-status-pill status-' + val.toLowerCase();
        text.textContent = val;
    }
});

const iconsList = [
    {val: '', text: 'Sin ícono'},
    {val: 'ph-code', text: 'Código / Desarrollo'},
    {val: 'ph-megaphone', text: 'Marketing / Ads'},
    {val: 'ph-paint-brush', text: 'Diseño Gráfico'},
    {val: 'ph-video-camera', text: 'Audiovisual'},
    {val: 'ph-device-mobile', text: 'Móvil / App'},
    {val: 'ph-chart-line-up', text: 'SEO / Analítica'},
    {val: 'ph-database', text: 'Cloud / Servidores'},
];

function generateIconSelect(selectedValue) {
    let html = `<select class="item-icon-select item-icon" onchange="syncData()">`;
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
                div.innerHTML = item.description || '';
                let text = div.textContent || div.innerText || 'Tarea ' + (index + 1);
                text = text.substring(0, 40).trim() || 'Partida ' + (index + 1);
                
                let startDate = new Date(item.gantt_start_date + 'T00:00:00');
                if (isNaN(startDate.getTime())) return;
                
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
                    const idx = parseInt(task.id.replace('task_', ''));
                    if (isNaN(idx) || !itemsData[idx]) return;

                    const newStart = new Date(start);
                    const yyyy = newStart.getFullYear();
                    const mm = String(newStart.getMonth() + 1).padStart(2, '0');
                    const dd = String(newStart.getDate()).padStart(2, '0');
                    const newStartStr = `${yyyy}-${mm}-${dd}`;

                    const newEnd = new Date(end);
                    const diffMs = newEnd.getTime() - newStart.getTime();
                    const diffDays = Math.max(1, Math.round(diffMs / (1000 * 60 * 60 * 24)));

                    itemsData[idx].gantt_start_date = newStartStr;
                    itemsData[idx].gantt_duration = diffDays;

                    const cards = document.querySelectorAll('.item-card');
                    if (cards[idx]) {
                        cards[idx].querySelector('.item-start').value = newStartStr;
                        cards[idx].querySelector('.item-duration').value = diffDays;
                    }
                }
            });

            // Inject color styles for Gantt
            let styleEl = document.getElementById('gantt-colors-style');
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'gantt-colors-style';
                document.head.appendChild(styleEl);
            }
            let css = '';
            ganttColors.forEach((c, i) => {
                css += `.gantt-color-${i} .bar { fill: ${c.bg} !important; rx: 4px; ry: 4px; }
                        .gantt-color-${i} .bar-progress { fill: ${c.bg} !important; }
                        .gantt-color-${i} .bar-label { fill: #ffffff !important; font-weight: 600; font-size: 11px; }
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
        if (!itemsData[index]) return;
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

/* ==========================================================================
   MODERN TABLE BUILDER & EXCEL / SHEETS PASTE SYSTEM
   ========================================================================== */
let activeEditor = null;
let currentTableTargetIndex = null;
let savedRange = null;

function saveSelection() {
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
        savedRange = sel.getRangeAt(0).cloneRange();
    }
}

function restoreSelection() {
    if (savedRange) {
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(savedRange);
    }
}

function escapeHtmlEntities(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function showPasteToast(msg) {
    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
        });
        Toast.fire({
            icon: 'success',
            title: msg
        });
    }
}

function sanitizeTableCellContent(node) {
    if (!node) return '&nbsp;';
    const clone = node.cloneNode(true);
    const unwanted = clone.querySelectorAll('script, style, link, meta, xml, object, embed, iframe');
    unwanted.forEach(el => el.remove());

    const all = clone.querySelectorAll('*');
    all.forEach(el => {
        const attrs = Array.from(el.attributes);
        attrs.forEach(attr => {
            if (attr.name !== 'href' && attr.name !== 'target') {
                el.removeAttribute(attr.name);
            }
        });
    });

    let html = clone.innerHTML.trim();
    html = html.replace(/&nbsp;/g, ' ').trim();
    return html || '&nbsp;';
}

function buildTableHtmlSkeleton(theadHtml, tbodyHtml) {
    return `<div class="quote-table-wrapper" contenteditable="false">` +
        `<div class="quote-table-actions">` +
            `<button type="button" class="btn-tbl-action" onclick="addTableRow(this)" title="Añadir Fila"><i class="ph ph-plus"></i> Fila</button>` +
            `<button type="button" class="btn-tbl-action" onclick="addTableColumn(this)" title="Añadir Columna"><i class="ph ph-plus"></i> Col</button>` +
            `<button type="button" class="btn-tbl-action btn-tbl-del" onclick="deleteTableRow(this)" title="Eliminar Fila"><i class="ph ph-minus"></i> Fila</button>` +
            `<button type="button" class="btn-tbl-action btn-tbl-del" onclick="deleteTableColumn(this)" title="Eliminar Columna"><i class="ph ph-minus"></i> Col</button>` +
            `<button type="button" class="btn-tbl-action btn-tbl-danger" onclick="removeModernTable(this)" title="Eliminar Tabla"><i class="ph ph-trash"></i></button>` +
        `</div>` +
        `<table class="quote-modern-table" contenteditable="true">` +
            (theadHtml ? `<thead>${theadHtml}</thead>` : '') +
            `<tbody>${tbodyHtml}</tbody>` +
        `</table>` +
    `</div><p><br></p>`;
}

function cleanAndModernizeHtmlTable(html) {
    try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const table = doc.querySelector('table');
        if (!table) return '';

        const allRows = Array.from(table.querySelectorAll('tr'));
        if (allRows.length === 0) return '';

        let theadRows = [];
        let tbodyRows = [];

        const thead = table.querySelector('thead');
        if (thead) {
            theadRows = Array.from(thead.querySelectorAll('tr'));
        }

        const tbody = table.querySelector('tbody');
        if (tbody) {
            tbodyRows = Array.from(tbody.querySelectorAll('tr'));
        } else {
            tbodyRows = allRows;
        }

        if (theadRows.length === 0 && tbodyRows.length > 1) {
            theadRows = [tbodyRows[0]];
            tbodyRows = tbodyRows.slice(1);
        } else if (theadRows.length === 0 && tbodyRows.length === 1) {
            theadRows = [tbodyRows[0]];
            tbodyRows = [];
        }

        let theadHtml = '';
        if (theadRows.length > 0) {
            theadRows.forEach(tr => {
                theadHtml += '<tr>';
                const cells = Array.from(tr.querySelectorAll('th, td'));
                cells.forEach(cell => {
                    const clean = sanitizeTableCellContent(cell);
                    theadHtml += `<th>${clean}</th>`;
                });
                theadHtml += '</tr>';
            });
        }

        let tbodyHtml = '';
        tbodyRows.forEach(tr => {
            tbodyHtml += '<tr>';
            const cells = Array.from(tr.querySelectorAll('th, td'));
            cells.forEach(cell => {
                const clean = sanitizeTableCellContent(cell);
                tbodyHtml += `<td>${clean}</td>`;
            });
            tbodyHtml += '</tr>';
        });

        return buildTableHtmlSkeleton(theadHtml, tbodyHtml);
    } catch(err) {
        console.error("Error cleaning HTML table:", err);
        return '';
    }
}

function convertTsvToModernTable(tsv) {
    try {
        const rawLines = tsv.trim().split(/\r?\n/).filter(line => line.trim().length > 0);
        if (rawLines.length === 0) return null;
        if (!rawLines.some(l => l.includes('\t'))) return null;

        const rowsData = rawLines.map(line => line.split('\t'));
        const maxCols = Math.max(...rowsData.map(r => r.length));
        if (maxCols < 1) return null;

        let theadHtml = '<tr>';
        for (let c = 0; c < maxCols; c++) {
            const val = (rowsData[0][c] || '').trim();
            theadHtml += `<th>${escapeHtmlEntities(val) || '&nbsp;'}</th>`;
        }
        theadHtml += '</tr>';

        let tbodyHtml = '';
        if (rowsData.length > 1) {
            for (let r = 1; r < rowsData.length; r++) {
                tbodyHtml += '<tr>';
                for (let c = 0; c < maxCols; c++) {
                    const val = (rowsData[r][c] || '').trim();
                    tbodyHtml += `<td>${escapeHtmlEntities(val) || '&nbsp;'}</td>`;
                }
                tbodyHtml += '</tr>';
            }
        } else {
            tbodyHtml += '<tr>';
            for (let c = 0; c < maxCols; c++) {
                tbodyHtml += `<td>&nbsp;</td>`;
            }
            tbodyHtml += '</tr>';
        }

        return buildTableHtmlSkeleton(theadHtml, tbodyHtml);
    } catch(err) {
        console.error("Error converting TSV to table:", err);
        return null;
    }
}

function insertHtmlAtCursorOrAppend(html, targetTextarea) {
    if (!targetTextarea) {
        targetTextarea = activeEditor || document.querySelector('.item-textarea');
    }
    if (!targetTextarea) return;

    targetTextarea.focus();
    restoreSelection();

    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
        const range = sel.getRangeAt(0);
        let container = range.commonAncestorContainer;
        while (container && container !== targetTextarea && container.parentNode) {
            container = container.parentNode;
        }

        if (container === targetTextarea) {
            range.deleteContents();
            const el = document.createElement("div");
            el.innerHTML = html;
            const frag = document.createDocumentFragment();
            let nodeToAdd, lastNode;
            while ((nodeToAdd = el.firstChild)) {
                lastNode = frag.appendChild(nodeToAdd);
            }
            range.insertNode(frag);

            if (lastNode) {
                const newRange = document.createRange();
                newRange.setStartAfter(lastNode);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
            return;
        }
    }

    targetTextarea.insertAdjacentHTML('beforeend', html);
}

function handleEditorPaste(e, textarea, index) {
    const clipboardData = e.clipboardData || window.clipboardData;
    if (!clipboardData) return;

    const htmlData = clipboardData.getData('text/html');
    const textData = clipboardData.getData('text/plain');

    if (htmlData && (htmlData.toLowerCase().includes('<table') || (htmlData.toLowerCase().includes('<tr') && htmlData.toLowerCase().includes('<td')))) {
        e.preventDefault();
        const cleanedHtml = cleanAndModernizeHtmlTable(htmlData);
        if (cleanedHtml) {
            insertHtmlAtCursorOrAppend(cleanedHtml, textarea);
            syncData();
            showPasteToast('¡Tabla pegada y adaptada con estilo moderno!');
            return;
        }
    }

    if (textData && textData.includes('\t') && (textData.includes('\n') || textData.includes('\r'))) {
        e.preventDefault();
        const tableHtml = convertTsvToModernTable(textData);
        if (tableHtml) {
            insertHtmlAtCursorOrAppend(tableHtml, textarea);
            syncData();
            showPasteToast('¡Celdas de Excel convertidas en tabla moderna!');
            return;
        }
    }
}

function handleEditorKeyDown(e) {
    if (e.key === 'Tab') {
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount) return;
        const cell = sel.anchorNode ? (sel.anchorNode.nodeType === 1 ? sel.anchorNode.closest('td, th') : sel.anchorNode.parentElement.closest('td, th')) : null;
        if (cell) {
            e.preventDefault();
            const table = cell.closest('table');
            const cells = Array.from(table.querySelectorAll('th, td'));
            const idx = cells.indexOf(cell);
            if (e.shiftKey) {
                if (idx > 0) {
                    cells[idx - 1].focus();
                }
            } else {
                if (idx < cells.length - 1) {
                    cells[idx + 1].focus();
                } else {
                    const wrapper = table.closest('.quote-table-wrapper');
                    const addBtn = wrapper ? wrapper.querySelector('.btn-tbl-action') : null;
                    if (addBtn) {
                        addTableRow(addBtn);
                        const newCells = Array.from(table.querySelectorAll('th, td'));
                        if (newCells.length > cells.length) {
                            newCells[cells.length].focus();
                        }
                    }
                }
            }
        }
    }
}

function removeModernTable(btn) {
    const wrapper = btn.closest('.quote-table-wrapper');
    if (wrapper) {
        wrapper.remove();
        syncData();
    }
}

function addTableRow(btn) {
    const wrapper = btn.closest('.quote-table-wrapper');
    if (!wrapper) return;
    const table = wrapper.querySelector('.quote-modern-table');
    if (!table) return;

    let tbody = table.querySelector('tbody');
    if (!tbody) {
        tbody = document.createElement('tbody');
        table.appendChild(tbody);
    }

    let colCount = 2;
    const firstRow = table.querySelector('tr');
    if (firstRow) {
        colCount = firstRow.querySelectorAll('th, td').length;
    }

    const tr = document.createElement('tr');
    for (let i = 0; i < colCount; i++) {
        const td = document.createElement('td');
        td.innerHTML = '&nbsp;';
        tr.appendChild(td);
    }
    tbody.appendChild(tr);
    syncData();
    tr.querySelector('td').focus();
}

function deleteTableRow(btn) {
    const wrapper = btn.closest('.quote-table-wrapper');
    if (!wrapper) return;
    const table = wrapper.querySelector('.quote-modern-table');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    if (tbody) {
        const rows = tbody.querySelectorAll('tr');
        if (rows.length > 1) {
            rows[rows.length - 1].remove();
            syncData();
        } else if (rows.length === 1) {
            rows[0].remove();
            syncData();
        }
    }
}

function addTableColumn(btn) {
    const wrapper = btn.closest('.quote-table-wrapper');
    if (!wrapper) return;
    const table = wrapper.querySelector('.quote-modern-table');
    if (!table) return;

    const theadRows = table.querySelectorAll('thead tr');
    theadRows.forEach(tr => {
        const th = document.createElement('th');
        th.innerText = 'Columna';
        tr.appendChild(th);
    });

    const tbodyRows = table.querySelectorAll('tbody tr');
    tbodyRows.forEach(tr => {
        const td = document.createElement('td');
        td.innerHTML = '&nbsp;';
        tr.appendChild(td);
    });
    syncData();
}

function deleteTableColumn(btn) {
    const wrapper = btn.closest('.quote-table-wrapper');
    if (!wrapper) return;
    const table = wrapper.querySelector('.quote-modern-table');
    if (!table) return;

    const firstRow = table.querySelector('tr');
    if (!firstRow || firstRow.querySelectorAll('th, td').length <= 1) {
        Swal.fire({
            icon: 'info',
            title: 'Mínimo 1 columna',
            text: 'La tabla no puede tener menos de una columna.',
            timer: 2000,
            showConfirmButton: false
        });
        return;
    }

    table.querySelectorAll('tr').forEach(tr => {
        const cells = tr.querySelectorAll('th, td');
        if (cells.length > 0) {
            cells[cells.length - 1].remove();
        }
    });
    syncData();
}

window.selectedTablePreset = 'custom';
window.selectPreset = function(preset, cardEl) {
    window.selectedTablePreset = preset;
    document.querySelectorAll('.table-preset-card').forEach(el => {
        el.style.border = '1px solid var(--quote-border)';
    });
    cardEl.style.border = '2px solid #0ea5e9';
    const customBox = document.getElementById('customTableDims');
    if (customBox) {
        customBox.style.display = (preset === 'custom') ? 'flex' : 'none';
    }
};

function insertConfiguredTable(config) {
    let theadHtml = '';
    let tbodyHtml = '';

    if (config.preset === 'deliverables') {
        theadHtml = '<tr><th>Hito / Fase</th><th>Entregable Clave</th><th>Formato / Medio</th><th>Plazo Estimado</th></tr>';
        tbodyHtml = '<tr><td>Fase 1: Estrategia</td><td>Briefing & Wireframes</td><td>Figma / PDF</td><td>5 días laborables</td></tr>' +
                    '<tr><td>Fase 2: Ejecución</td><td>Prototipo Funcional</td><td>Demo Online</td><td>10 días laborables</td></tr>' +
                    '<tr><td>Fase 3: Entrega</td><td>Despliegue & Capacitación</td><td>Acceso Producción</td><td>3 días laborables</td></tr>';
    } else if (config.preset === 'specs') {
        theadHtml = '<tr><th>Módulo / Componente</th><th>Especificación Técnica</th><th>Alcance / Detalle</th></tr>';
        tbodyHtml = '<tr><td>Frontend</td><td>Next.js / Tailwind CSS</td><td>Diseño 100% responsivo y dark mode</td></tr>' +
                    '<tr><td>Backend & API</td><td>PHP / Node REST API</td><td>Autenticación JWT y roles</td></tr>' +
                    '<tr><td>Infraestructura</td><td>Cloudflare / VPS SSD</td><td>SSL, backups automáticos diarios</td></tr>';
    } else if (config.preset === 'pricing') {
        theadHtml = '<tr><th>Concepto / Tarea</th><th>Cant.</th><th>Precio Unit.</th><th>Subtotal</th></tr>';
        tbodyHtml = '<tr><td>Configuración de Servidor</td><td>1</td><td>S/ 250.00</td><td>S/ 250.00</td></tr>' +
                    '<tr><td>Integración Pasarela de Pagos</td><td>1</td><td>S/ 450.00</td><td>S/ 450.00</td></tr>';
    } else {
        const cols = Math.min(Math.max(config.cols, 1), 10);
        const rows = Math.min(Math.max(config.rows, 1), 25);

        theadHtml = '<tr>';
        for (let c = 1; c <= cols; c++) {
            theadHtml += `<th>Columna ${c}</th>`;
        }
        theadHtml += '</tr>';

        for (let r = 1; r <= rows; r++) {
            tbodyHtml += '<tr>';
            for (let c = 1; c <= cols; c++) {
                tbodyHtml += `<td>Dato ${r}.${c}</td>`;
            }
            tbodyHtml += '</tr>';
        }
    }

    const outTable = buildTableHtmlSkeleton(theadHtml, tbodyHtml);
    const target = activeEditor || (currentTableTargetIndex !== null ? document.querySelectorAll('.item-card')[currentTableTargetIndex]?.querySelector('.item-textarea') : null) || document.querySelector('.item-textarea');
    insertHtmlAtCursorOrAppend(outTable, target);
    syncData();
}

function openModernTableModal(index) {
    currentTableTargetIndex = index;
    const cards = document.querySelectorAll('.item-card');
    if (cards[index]) {
        activeEditor = cards[index].querySelector('.item-textarea');
    }
    window.selectedTablePreset = 'custom';

    Swal.fire({
        title: '<div style="display:flex;align-items:center;gap:8px;font-size:1.15rem;font-weight:700;"><i class="ph ph-table" style="color:#0ea5e9;"></i> Insertar Tabla Moderna</div>',
        html: `
            <div style="text-align: left; font-size: 13px; color: var(--quote-text-main);">
                <p style="margin-bottom: 12px; color: var(--quote-text-muted);">
                    Selecciona una plantilla prediseñada o configura dimensiones personalizadas:
                </p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px;">
                    <div class="table-preset-card active" onclick="selectPreset('custom', this)" style="border: 2px solid #0ea5e9; border-radius: 8px; padding: 10px; cursor: pointer; background: var(--quote-card-sub);">
                        <strong style="display: block; font-size: 12.5px;">🛠️ Personalizada</strong>
                        <span style="font-size: 11px; color: var(--quote-text-muted);">Elige filas y columnas</span>
                    </div>
                    <div class="table-preset-card" onclick="selectPreset('deliverables', this)" style="border: 1px solid var(--quote-border); border-radius: 8px; padding: 10px; cursor: pointer; background: var(--quote-card-sub);">
                        <strong style="display: block; font-size: 12.5px;">🚀 Entregables & Plazos</strong>
                        <span style="font-size: 11px; color: var(--quote-text-muted);">Fase, Entregable, Plazo</span>
                    </div>
                    <div class="table-preset-card" onclick="selectPreset('specs', this)" style="border: 1px solid var(--quote-border); border-radius: 8px; padding: 10px; cursor: pointer; background: var(--quote-card-sub);">
                        <strong style="display: block; font-size: 12.5px;">⚙️ Especificaciones</strong>
                        <span style="font-size: 11px; color: var(--quote-text-muted);">Módulo, Detalle, Alcance</span>
                    </div>
                    <div class="table-preset-card" onclick="selectPreset('pricing', this)" style="border: 1px solid var(--quote-border); border-radius: 8px; padding: 10px; cursor: pointer; background: var(--quote-card-sub);">
                        <strong style="display: block; font-size: 12.5px;">💰 Desglose Económico</strong>
                        <span style="font-size: 11px; color: var(--quote-text-muted);">Ítem, Cantidad, Precio</span>
                    </div>
                </div>

                <div id="customTableDims" style="display: flex; gap: 10px; margin-bottom: 12px;">
                    <div style="flex: 1;">
                        <label style="display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">Columnas:</label>
                        <input type="number" id="tblCols" class="app-input" value="3" min="1" max="8" style="width: 100%;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">Filas de Datos:</label>
                        <input type="number" id="tblRows" class="app-input" value="3" min="1" max="15" style="width: 100%;">
                    </div>
                </div>

                <div style="background: rgba(14, 165, 233, 0.08); border-left: 3px solid #0ea5e9; padding: 8px 12px; border-radius: 4px; font-size: 11.5px; line-height: 1.4;">
                    💡 <strong>Pro Tip:</strong> También puedes copiar cualquier tabla o rango de celdas desde <strong>Excel o Google Sheets</strong> (Ctrl+C) y pegarla directamente con (Ctrl+V). Se convertirá automáticamente a este diseño moderno.
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="ph ph-plus-circle"></i> Insertar Tabla',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0ea5e9',
        focusConfirm: false,
        preConfirm: () => {
            return {
                preset: window.selectedTablePreset || 'custom',
                cols: parseInt(document.getElementById('tblCols').value) || 3,
                rows: parseInt(document.getElementById('tblRows').value) || 3
            };
        }
    }).then(result => {
        if (result.isConfirmed && result.value) {
            insertConfiguredTable(result.value);
        }
    });
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
                <div class="item-header-left">
                    <span class="partida-badge">Partida #${index + 1}</span>
                    ${generateIconSelect(item.icon || '')}
                </div>
                <button type="button" class="btn-delete-item" onclick="removeItem(${index})" title="Eliminar Partida">
                    <i class="ph ph-trash"></i>
                    <span>Eliminar</span>
                </button>
            </div>
            
            <div class="item-editor-container">
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
                    <button type="button" class="editor-btn" title="Negrita (Ctrl+B)" onclick="document.execCommand('bold', false, null)">
                        <i class="ph ph-text-bolder"></i>
                    </button>
                    <button type="button" class="editor-btn" title="Cursiva (Ctrl+I)" onclick="document.execCommand('italic', false, null)">
                        <i class="ph ph-text-italic"></i>
                    </button>
                    <button type="button" class="editor-btn" title="Subrayado (Ctrl+U)" onclick="document.execCommand('underline', false, null)">
                        <i class="ph ph-text-underline"></i>
                    </button>
                    <button type="button" class="editor-btn" title="Resaltar Texto" onclick="toggleHighlight()">
                        <i class="ph ph-highlighter"></i>
                    </button>
                    <div class="editor-divider"></div>
                    <button type="button" class="editor-btn" title="Lista con Viñetas" onclick="document.execCommand('insertUnorderedList', false, null)">
                        <i class="ph ph-list-bullets"></i>
                    </button>
                    <button type="button" class="editor-btn" title="Lista Numerada" onclick="document.execCommand('insertOrderedList', false, null)">
                        <i class="ph ph-list-numbers"></i>
                    </button>
                    <div class="editor-divider"></div>
                    <button type="button" class="editor-btn btn-table-trigger" title="Insertar o Pegar Tabla Moderna" onclick="openModernTableModal(${index})">
                        <i class="ph ph-table"></i>
                    </button>
                </div>
                <div class="item-textarea" contenteditable="true" onblur="syncData()" placeholder="Describe el servicio detalladamente...">${item.description || ''}</div>
            </div>

            <div class="item-metrics-layout">
                <!-- Montos Box -->
                <div class="item-amounts-box">
                    <div class="amounts-col">
                        <label class="item-mini-label">CANTIDAD</label>
                        <input type="number" class="app-input item-qty" value="${qty}" min="1" step="0.01" onchange="syncData()" onkeyup="syncData()">
                    </div>
                    <div class="amounts-col">
                        <label class="item-mini-label">PRECIO UNIT.</label>
                        <input type="number" class="app-input item-price" value="${price.toFixed(2)}" min="0" step="0.01" onchange="syncData()" onkeyup="syncData()">
                    </div>
                    <div class="amounts-col">
                        <label class="item-mini-label">DESC. (${sym})</label>
                        <input type="number" class="app-input item-disc" value="${disc.toFixed(2)}" min="0" step="0.01" onchange="syncData()" onkeyup="syncData()">
                    </div>
                    <div class="amounts-col total-col">
                        <label class="item-mini-label">IMPORTE</label>
                        <div class="item-total-display">${sym} ${total.toFixed(2)}</div>
                    </div>
                </div>

                <!-- Gantt Schedule Box -->
                <div class="item-schedule-box">
                    <div class="schedule-header">
                        <i class="ph ph-calendar-blank"></i>
                        <span>CRONOGRAMA (GANTT)</span>
                    </div>
                    <div class="schedule-inputs-row">
                        <div class="schedule-col">
                            <label class="item-mini-label">INICIO</label>
                            <input type="date" class="app-input item-start" value="${item.gantt_start_date || ''}" onchange="syncData()">
                        </div>
                        <div class="schedule-col duration-col">
                            <label class="item-mini-label">DÍAS</label>
                            <input type="number" class="app-input item-duration" value="${item.gantt_duration || 0}" min="0" onchange="syncData()" onkeyup="syncData()">
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(card);

        const textarea = card.querySelector('.item-textarea');
        textarea.addEventListener('paste', (e) => handleEditorPaste(e, textarea, index));
        textarea.addEventListener('focus', () => { activeEditor = textarea; currentTableTargetIndex = index; saveSelection(); });
        textarea.addEventListener('keyup', () => { saveSelection(); syncData(); });
        textarea.addEventListener('mouseup', saveSelection);
        textarea.addEventListener('keydown', handleEditorKeyDown);
    });

    calculateTotals(subtotal);
}

function removeItem(index) {
    syncData();
    itemsData.splice(index, 1);
    renderItems();
    renderGantt();
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
    if (!sel.value) return;
    const opt = sel.options[sel.selectedIndex];
    const sId = opt.value;
    const name = opt.text;
    const price = parseFloat(opt.dataset.price || 0);

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
    renderGantt();
}

function calculateTotals(subtotal) {
    if (subtotal === undefined) {
        subtotal = 0;
        itemsData.forEach(item => subtotal += parseFloat(item.total || 0));
    }
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;

    const sym = getCurrencySymbol();

    document.getElementById('calcSubtotal').innerText = sym + ' ' + subtotal.toFixed(2);
    document.getElementById('calcTax').innerText = sym + ' ' + tax.toFixed(2);
    document.getElementById('calcTotal').innerText = sym + ' ' + total.toFixed(2);
}

/* Theme & Cover Handlers */
const gradientMap = {
    'mesh-blue': 'radial-gradient(at 0% 0%, #2563eb 0px, transparent 65%), radial-gradient(at 100% 100%, #6366f1 0px, transparent 65%), linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%)',
    'emerald-glow': 'radial-gradient(at 0% 0%, #059669 0px, transparent 65%), radial-gradient(at 100% 100%, #0891b2 0px, transparent 65%), linear-gradient(135deg, #064e3b 0%, #0f172a 100%)',
    'creative-violet': 'radial-gradient(at 0% 0%, #9333ea 0px, transparent 65%), radial-gradient(at 100% 100%, #db2777 0px, transparent 65%), linear-gradient(135deg, #581c87 0%, #0f172a 100%)',
    'sunset-gold': 'radial-gradient(at 0% 0%, #d97706 0px, transparent 65%), radial-gradient(at 100% 100%, #dc2626 0px, transparent 65%), linear-gradient(135deg, #78350f 0%, #0f172a 100%)',
    'cyber-dark': 'linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #090d16 100%)',
    'minimal-clean': 'linear-gradient(135deg, #334155 0%, #1e293b 100%)',
    'none': '#0f172a'
};

function updateCoverPreview() {
    const preview = document.getElementById('coverLivePreview');
    if (!preview) return;
    const imgVal = document.getElementById('cover_image').value.trim();
    const gradVal = document.getElementById('cover_gradient').value;
    const badge = document.getElementById('coverBadgeText');

    if (imgVal) {
        preview.style.background = `url(${imgVal}) center / cover no-repeat`;
        if (badge) badge.textContent = 'Imagen personalizada activa';
    } else if (gradVal && gradVal !== 'none') {
        preview.style.background = gradientMap[gradVal] || gradientMap['mesh-blue'];
        if (badge) badge.textContent = 'Degradado ' + gradVal + ' activo';
    } else {
        preview.style.background = '#1e293b';
        if (badge) badge.textContent = 'Sin portada';
    }
}

function selectQuoteTheme(themeKey, cardEl) {
    document.getElementById('theme_color').value = themeKey;
    document.querySelectorAll('.quote-theme-card').forEach(c => {
        c.classList.remove('active');
        const icon = c.querySelector('.theme-card-color-dot i');
        if (icon) icon.style.display = 'none';
    });
    cardEl.classList.add('active');
    const activeIcon = cardEl.querySelector('.theme-card-color-dot i');
    if (activeIcon) activeIcon.style.display = 'inline-block';
}

function selectCoverGradient(gradKey, btnEl) {
    document.getElementById('cover_gradient').value = gradKey;
    document.getElementById('cover_image').value = '';
    document.querySelectorAll('.btn-gradient-chip').forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');
    updateCoverPreview();
}

function removeCoverBanner() {
    document.getElementById('cover_gradient').value = 'none';
    document.getElementById('cover_image').value = '';
    document.querySelectorAll('.btn-gradient-chip').forEach(b => b.classList.remove('active'));
    updateCoverPreview();
}

function uploadCoverFile(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const formData = new FormData();
    formData.append('cover_file', file);

    const badge = document.getElementById('coverBadgeText');
    if (badge) badge.textContent = 'Subiendo imagen...';

    fetch('modules/quotes/ajax_upload_cover.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.url) {
            document.getElementById('cover_image').value = data.url;
            document.querySelectorAll('.btn-gradient-chip').forEach(b => b.classList.remove('active'));
            updateCoverPreview();
            showPasteToast('¡Portada subida con éxito!');
        } else {
            Swal.fire('Error', data.message || 'Error al subir la imagen', 'error');
            updateCoverPreview();
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Fallo de conexión al subir la imagen', 'error');
        updateCoverPreview();
    });
}

// Initial bootstrap
document.addEventListener('DOMContentLoaded', () => {
    if (itemsData.length === 0 && !document.getElementById('quote_id').value) {
        addEmptyRow();
    } else {
        renderItems();
    }
    renderGantt();
    updateCoverPreview();
});

// Save Logic
$('#btnSaveQuote').on('click', function(e) {
    e.preventDefault();
    const btn = $(this);
    const oHtml = btn.html();

    try {
        syncData();

        const client_name = $('#client_name').val().trim();
        if (!client_name) {
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
                if (acc.trim() !== '') pm_text += name + ": " + acc.trim() + "\n";
            }
        });
        
        let ownerElem = document.getElementById('bank_owner');
        let owner = ownerElem ? ownerElem.value.trim() : '';
        if (owner) pm_text += "\nA nombre de " + owner;
        
        let pmTextElem = document.getElementById('payment_methods_text');
        if (pmTextElem) pmTextElem.value = pm_text.trim();

        const payload = {
            quote_id: $('#quote_id').val(),
            client_name: client_name,
            issue_date: $('#issue_date').val(),
            due_date: $('#due_date').val(),
            currency: $('#currency').val(),
            status: $('#status').val(),
            theme_color: $('#theme_color').val() || 'corporate-blue',
            cover_image: $('#cover_image').val() || '',
            cover_gradient: $('#cover_gradient').val() || 'mesh-blue',
            tax_rate: $('#tax_rate').val(),
            notes: $('#notes').val(),
            terms_conditions: $('#terms_conditions').val(),
            show_payment_methods: $('#show_payment_methods').is(':checked') ? 1 : 0,
            payment_methods_text: pmTextElem ? pmTextElem.value : '',
            items: itemsData
        };

        btn.html('<i class="ph ph-spinner ph-spin"></i> Guardando...').prop('disabled', true);

        $.post('modules/quotes/ajax_save_quote.php', payload, function(res) {
            if (res && res.success) {
                Swal.fire({
                    title: '¡Guardado!',
                    text: res.message,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'index.php?module=quotes&action=index';
                });
            } else {
                Swal.fire('Error', (res && res.message) ? res.message : 'Respuesta desconocida del servidor', 'error');
                btn.html(oHtml).prop('disabled', false);
            }
        }, 'json').fail(function(xhr) {
            console.error("AJAX Fail:", xhr.responseText);
            Swal.fire('Error de Conexión', 'Hubo un problema guardando los datos. Revisa la consola.', 'error');
            btn.html(oHtml).prop('disabled', false);
        });

    } catch(err) {
        console.error("JS Execution Error:", err);
        alert("Ocurrió un error en el formulario: " + err.message);
        btn.html(oHtml).prop('disabled', false);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
