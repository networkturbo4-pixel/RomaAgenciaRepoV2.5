<?php
// modules/brand_guidelines/index.php
require_once 'includes/header.php';
require_once 'modules/brand_guidelines/helpers.php';

// Fetch all brand guidelines with optional client info
$stmt = $db->query("
    SELECT bg.*, c.name as client_name 
    FROM brand_guidelines bg
    LEFT JOIN clients c ON bg.client_id = c.id
    ORDER BY bg.updated_at DESC
");
$guidelines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate metrics
$totalCount = count($guidelines);
$publicCount = 0;
$privateCount = 0;
$totalViews = 0;

foreach ($guidelines as $g) {
    if ($g['is_public'] == 1) {
        $publicCount++;
    } else {
        $privateCount++;
    }
    $totalViews += (int)($g['views_count'] ?? 0);
}

$baseUrl = bg_get_base_url();
?>

<style>
/* ==========================================================================
   BRAND GUIDELINES DASHBOARD STYLING
   ========================================================================== */
.bg-container {
    padding: 2rem 2.25rem;
    max-width: 1440px;
    margin: 0 auto;
    animation: bgFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes bgFadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Header */
.bg-header-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.bg-header-info {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.bg-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--primary-color, #262ecf);
}

.bg-title-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.bg-header-title {
    margin: 0;
    font-size: 2.15rem;
    font-weight: 900;
    letter-spacing: -0.8px;
    color: var(--text-main, #0f172a);
    line-height: 1.15;
}

[data-theme="dark"] .bg-header-title {
    color: #ffffff;
}

.bg-header-subtitle {
    margin: 0;
    color: var(--text-muted, #64748b);
    font-size: 0.95rem;
    font-weight: 500;
}

.bg-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    background: var(--color-btn-bg, var(--primary-color, #262ecf));
    color: var(--color-btn-text, #ffffff) !important;
    font-size: 0.92rem;
    font-weight: 700;
    padding: 0.75rem 1.45rem;
    border-radius: 14px;
    border: none;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 6px 18px -4px color-mix(in srgb, var(--primary-color, #262ecf) 45%, transparent);
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.bg-btn-primary:hover {
    transform: translateY(-2px) scale(1.02);
    background: var(--color-btn-hover, var(--primary-hover, #1f25a6));
    box-shadow: 0 10px 24px -4px color-mix(in srgb, var(--primary-color, #262ecf) 60%, transparent);
    color: #ffffff !important;
}

/* Stats Row */
.bg-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.bg-stat-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 18px;
    padding: 1.25rem 1.4rem;
    display: flex;
    align-items: center;
    gap: 1.15rem;
    box-shadow: 0 4px 14px -2px rgba(0, 0, 0, 0.03);
    transition: all 0.25s ease;
}

[data-theme="dark"] .bg-stat-card {
    background: #14161f;
    border-color: rgba(255, 255, 255, 0.08);
}

.bg-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px -3px rgba(0, 0, 0, 0.08);
}

.bg-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.bg-stat-icon.total {
    background: color-mix(in srgb, var(--primary-color, #262ecf) 14%, transparent);
    color: var(--primary-color, #262ecf);
}
.bg-stat-icon.public {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}
.bg-stat-icon.private {
    background: rgba(245, 158, 11, 0.12);
    color: #f59e0b;
}
.bg-stat-icon.views {
    background: rgba(99, 102, 241, 0.12);
    color: #6366f1;
}

.bg-stat-value {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
    line-height: 1.2;
}

[data-theme="dark"] .bg-stat-value {
    color: #ffffff;
}

.bg-stat-label {
    font-size: 0.78rem;
    color: var(--text-muted, #64748b);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Filters Button Trigger & Badge */
.bg-btn-filter-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    background: var(--bg-surface, #ffffff);
    color: var(--text-main, #0f172a);
    font-size: 0.92rem;
    font-weight: 700;
    padding: 0.72rem 1.25rem;
    border-radius: 14px;
    border: 1px solid var(--border-color, #cbd5e1);
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}
[data-theme="dark"] .bg-btn-filter-trigger {
    background: #1e2230;
    border-color: rgba(255, 255, 255, 0.12);
    color: #f8fafc;
}
.bg-btn-filter-trigger:hover {
    background: var(--bg-body, #f8fafc);
    border-color: var(--primary-color, #262ecf);
    color: var(--primary-color, #262ecf);
    transform: translateY(-1px);
}
.bg-filter-badge {
    background: var(--primary-color, #262ecf);
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    line-height: 1;
}

/* Off-canvas Filter Drawer */
.bg-drawer-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    z-index: 99999;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}
.bg-drawer-overlay.active {
    opacity: 1;
    visibility: visible;
}
.bg-drawer-content {
    position: fixed;
    top: 0;
    right: 0;
    width: 100%;
    max-width: 440px;
    height: 100vh;
    background: var(--bg-surface, #ffffff);
    box-shadow: -15px 0 45px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
    z-index: 100000;
    transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
[data-theme="dark"] .bg-drawer-content {
    background: #141622;
    border-left: 1px solid rgba(255, 255, 255, 0.08);
}
.bg-drawer-overlay.active .bg-drawer-content {
    transform: translateX(0);
}
.bg-drawer-header {
    padding: 1.35rem 1.5rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
[data-theme="dark"] .bg-drawer-header {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}
.bg-drawer-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.bg-drawer-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: color-mix(in srgb, var(--primary-color, #262ecf) 12%, transparent);
    color: var(--primary-color, #262ecf);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
.bg-drawer-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
}
[data-theme="dark"] .bg-drawer-title {
    color: #ffffff;
}
.bg-drawer-subtitle {
    font-size: 0.76rem;
    color: var(--text-muted, #64748b);
}
.bg-drawer-body {
    padding: 1.5rem;
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1.35rem;
}
.bg-drawer-field {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.bg-drawer-label {
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted, #64748b);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.bg-drawer-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.bg-drawer-input-wrap input {
    width: 100%;
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #cbd5e1);
    border-radius: 12px;
    padding: 0.75rem 2.25rem 0.75rem 1rem;
    font-size: 0.92rem;
    color: var(--text-main, #0f172a);
    outline: none;
    transition: border-color 0.2s;
}
[data-theme="dark"] .bg-drawer-input-wrap input {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.12);
    color: #ffffff;
}
.bg-drawer-input-wrap input:focus {
    border-color: var(--primary-color, #262ecf);
}
.bg-input-clear-btn {
    position: absolute;
    right: 0.75rem;
    background: transparent;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.bg-drawer-chips {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.bg-chip {
    padding: 0.5rem 0.85rem;
    border-radius: 10px;
    border: 1px solid var(--border-color, #cbd5e1);
    background: var(--bg-body, #f8fafc);
    color: var(--text-muted, #64748b);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.2s;
}
[data-theme="dark"] .bg-chip {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.1);
    color: #94a3b8;
}
.bg-chip.active {
    background: var(--primary-color, #262ecf);
    border-color: var(--primary-color, #262ecf);
    color: #ffffff !important;
}
.bg-drawer-select {
    width: 100%;
    background-color: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #cbd5e1);
    border-radius: 12px;
    padding: 0.75rem 2.5rem 0.75rem 1rem;
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text-main, #0f172a);
    outline: none;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 256 256'%3E%3Cpath fill='%2364748b' d='M213.66,101.66l-80,80a8,8 0,0,1-11.32,0l-80-80A8,8 0,0,1,53.66,90.34L128,164.69l74.34-74.35a8,8 0,0,1,11.32,11.32Z'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.85rem center;
    background-size: 16px 16px;
}
.bg-drawer-select option {
    background-color: #ffffff;
    color: #0f172a;
    padding: 0.5rem;
}
[data-theme="dark"] .bg-drawer-select {
    background-color: #1a1e2d;
    border-color: rgba(255, 255, 255, 0.14);
    color: #f8fafc;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 256 256'%3E%3Cpath fill='%2394a3b8' d='M213.66,101.66l-80,80a8,8 0,0,1-11.32,0l-80-80A8,8 0,0,1,53.66,90.34L128,164.69l74.34-74.35a8,8 0,0,1,11.32,11.32Z'%3E%3C/path%3E%3C/svg%3E");
}
[data-theme="dark"] .bg-drawer-select option {
    background-color: #1a1e2d;
    color: #f8fafc;
}
.bg-drawer-footer {
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    gap: 0.75rem;
    background: var(--bg-surface, #ffffff);
}
[data-theme="dark"] .bg-drawer-footer {
    border-top-color: rgba(255, 255, 255, 0.08);
    background: #141622;
}
.bg-btn-drawer-reset {
    flex: 1;
    background: transparent;
    border: 1px solid var(--border-color, #cbd5e1);
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: all 0.2s;
}
[data-theme="dark"] .bg-btn-drawer-reset {
    border-color: rgba(255, 255, 255, 0.12);
    color: #94a3b8;
}
.bg-btn-drawer-reset:hover {
    background: rgba(239, 68, 68, 0.08);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.3);
}
.bg-btn-drawer-apply {
    flex: 1.4;
    background: var(--primary-color, #262ecf);
    color: #ffffff;
    border: none;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    box-shadow: 0 4px 14px color-mix(in srgb, var(--primary-color, #262ecf) 35%, transparent);
    transition: all 0.2s;
}
.bg-btn-drawer-apply:hover {
    background: var(--primary-hover, #1f25a6);
    transform: translateY(-1px);
}

/* Grid of Brand Guidelines Cards */
.bg-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

/* Redesigned Brand Card */
.bg-card {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 20px;
    padding: 1.35rem 1.4rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1.15rem;
    box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.04);
    transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}
[data-theme="dark"] .bg-card {
    background: #141724;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
}
.bg-card:hover {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--primary-color, #262ecf) 40%, transparent);
    box-shadow: 0 14px 32px -4px rgba(0, 0, 0, 0.1);
}
[data-theme="dark"] .bg-card:hover {
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 14px 32px -4px rgba(0, 0, 0, 0.65);
}
.bg-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
}
.bg-logo-preview {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    padding: 6px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}
[data-theme="dark"] .bg-logo-preview {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.1);
}
.bg-logo-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
.bg-logo-initial {
    font-size: 1.6rem;
    font-weight: 900;
    background: linear-gradient(135deg, #ec4899 0%, #8b5cf6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.bg-card-top-right {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.bg-views-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted, #64748b);
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    padding: 0.25rem 0.55rem;
    border-radius: 8px;
}
[data-theme="dark"] .bg-views-pill {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
}
.bg-badge-privacy {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.3rem 0.7rem;
    border-radius: 9999px;
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}
.bg-badge-privacy.public {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.28);
}
.bg-badge-privacy.public:hover {
    background: rgba(16, 185, 129, 0.22);
}
.bg-badge-privacy.private {
    background: rgba(245, 158, 11, 0.12);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.28);
}
.bg-badge-privacy.private:hover {
    background: rgba(245, 158, 11, 0.22);
}
.bg-card-content {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.bg-card-title {
    font-size: 1.18rem;
    font-weight: 800;
    margin: 0;
    color: var(--text-main, #0f172a);
    letter-spacing: -0.3px;
    line-height: 1.25;
}
[data-theme="dark"] .bg-card-title {
    color: #ffffff;
}
.bg-card-client {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-muted, #64748b);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.bg-card-tagline {
    font-size: 0.8rem;
    font-weight: 500;
    color: #ec4899;
    margin: 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
[data-theme="dark"] .bg-card-tagline {
    color: #f472b6;
}
.bg-card-swatches {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.35rem;
}
.bg-swatch-dot {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid var(--bg-surface, #ffffff);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
    flex-shrink: 0;
    transition: transform 0.2s;
    cursor: pointer;
}
.bg-swatch-dot:hover {
    transform: scale(1.25);
    z-index: 2;
}
.bg-shortlink-pill {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg-body, #f8fafc);
    border: 1px dashed var(--border-color, #cbd5e1);
    border-radius: 10px;
    padding: 0.4rem 0.75rem;
    font-size: 0.76rem;
    color: var(--text-muted, #64748b);
    margin-top: 0.4rem;
    cursor: pointer;
    transition: all 0.2s;
}
[data-theme="dark"] .bg-shortlink-pill {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.12);
}
.bg-shortlink-pill:hover {
    background: color-mix(in srgb, var(--primary-color, #262ecf) 10%, transparent);
    border-color: var(--primary-color, #262ecf);
    color: var(--text-main, #0f172a);
}
.bg-shortlink-pill span {
    font-family: monospace;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.bg-card-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.85rem;
    border-top: 1px solid var(--border-color, #e2e8f0);
    gap: 0.5rem;
}
[data-theme="dark"] .bg-card-actions {
    border-top-color: rgba(255, 255, 255, 0.08);
}
.bg-btn-view {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: color-mix(in srgb, var(--primary-color, #262ecf) 12%, transparent);
    color: var(--primary-color, #262ecf) !important;
    border: 1px solid color-mix(in srgb, var(--primary-color, #262ecf) 25%, transparent);
    font-size: 0.84rem;
    font-weight: 700;
    padding: 0.45rem 0.95rem;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s;
}
.bg-btn-view:hover {
    background: var(--primary-color, #262ecf);
    border-color: var(--primary-color, #262ecf);
    color: #ffffff !important;
    transform: translateY(-1px);
}
.bg-action-group {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.bg-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    border: 1px solid var(--border-color, #e2e8f0);
    background: transparent;
    color: var(--text-muted, #64748b);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.2s;
    text-decoration: none;
}
[data-theme="dark"] .bg-icon-btn {
    border-color: rgba(255, 255, 255, 0.08);
}
.bg-icon-btn:hover {
    background: var(--bg-body, #f8fafc);
    color: var(--text-main, #0f172a);
    transform: translateY(-1px);
}
[data-theme="dark"] .bg-icon-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}
.bg-icon-btn.danger:hover {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.3);
}

/* Responsive Rules Mobile */
@media (max-width: 768px) {
    .bg-container {
        padding: 0.85rem 0.75rem 2rem !important;
    }
    .bg-header-wrap {
        margin-bottom: 1.15rem !important;
        gap: 0.85rem !important;
    }
    .bg-header-title {
        font-size: 1.65rem !important;
    }
    .bg-stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.65rem !important;
        margin-bottom: 1.25rem !important;
    }
    .bg-stat-card {
        padding: 0.85rem !important;
        border-radius: 14px !important;
        gap: 0.65rem !important;
    }
    .bg-stat-icon {
        width: 38px !important;
        height: 38px !important;
        font-size: 20px !important;
        border-radius: 10px !important;
    }
    .bg-stat-value {
        font-size: 1.3rem !important;
    }
    .bg-stat-label {
        font-size: 0.65rem !important;
        letter-spacing: 0.2px !important;
    }
    .bg-grid {
        grid-template-columns: 1fr !important;
        gap: 1rem !important;
    }
    .bg-drawer-content {
        max-width: 100% !important;
    }
}

/* Empty State */
.bg-empty-state {
    text-align: center;
    padding: 4.5rem 2rem;
    background: var(--bg-surface, #ffffff);
    border-radius: 24px;
    border: 2px dashed var(--border-color, #e2e8f0);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
}

[data-theme="dark"] .bg-empty-state {
    background: #14161f;
    border-color: rgba(255, 255, 255, 0.1);
}

.bg-empty-icon {
    width: 76px;
    height: 76px;
    border-radius: 22px;
    background: linear-gradient(135deg, rgba(236, 72, 153, 0.15) 0%, rgba(139, 92, 246, 0.15) 100%);
    color: #ec4899;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 38px;
}

.bg-empty-title {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .bg-empty-title {
    color: #ffffff;
}

.bg-empty-desc {
    max-width: 480px;
    color: var(--text-muted, #64748b);
    font-size: 0.95rem;
    margin: 0;
    line-height: 1.5;
}

/* Modal Sharing */
.bg-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
}

.bg-modal-overlay.active {
    display: flex;
}

.bg-modal-content {
    background: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 24px;
    padding: 2rem;
    max-width: 520px;
    width: 100%;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: bgModalZoom 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

[data-theme="dark"] .bg-modal-content {
    background: #141721;
    border-color: rgba(255, 255, 255, 0.1);
}

@keyframes bgModalZoom {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

.bg-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.bg-modal-title {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
}

[data-theme="dark"] .bg-modal-title {
    color: #ffffff;
}

.bg-close-btn {
    background: transparent;
    border: none;
    font-size: 1.35rem;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    border-radius: 8px;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.bg-close-btn:hover {
    color: var(--text-main, #0f172a);
    background: var(--bg-body, #f8fafc);
}

.bg-qr-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--bg-body, #f8fafc);
    border-radius: 16px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color, #e2e8f0);
}

[data-theme="dark"] .bg-qr-wrap {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.08);
}

.bg-qr-img {
    width: 160px;
    height: 160px;
    border-radius: 12px;
    background: white;
    padding: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.bg-input-copy-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.bg-copy-input {
    flex: 1;
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 12px;
    padding: 0.65rem 1rem;
    font-size: 0.88rem;
    font-family: monospace;
    color: var(--text-main, #0f172a);
    outline: none;
}

[data-theme="dark"] .bg-copy-input {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.bg-copy-btn {
    background: var(--primary-color, #4f46e5);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 0.65rem 1.15rem;
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.2s;
}

.bg-copy-btn:hover {
    opacity: 0.92;
    transform: translateY(-1px);
}
</style>

<div class="bg-container">
    <!-- Header -->
    <div class="bg-header-wrap">
        <div class="bg-header-info">
            <span class="bg-kicker"><i class="ph-bold ph-paint-brush-broad"></i> Identidad Corporativa</span>
            <div class="bg-title-row">
                <h1 class="bg-header-title">Brand Guidelines</h1>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <button type="button" class="bg-btn-filter-trigger" onclick="openFiltersDrawer()">
                <i class="ph-bold ph-funnel"></i>
                <span>Filtros</span>
                <span id="activeFiltersBadge" class="bg-filter-badge" style="display:none;">0</span>
            </button>
            <a href="index.php?module=brand_guidelines&action=edit" class="bg-btn-primary">
                <i class="ph-bold ph-plus-circle" style="font-size: 1.15rem;"></i>
                <span>Crear Manual de Marca</span>
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="bg-stats-grid">
        <div class="bg-stat-card">
            <div class="bg-stat-icon total">
                <i class="ph-bold ph-book-bookmark"></i>
            </div>
            <div>
                <div class="bg-stat-value"><?php echo $totalCount; ?></div>
                <div class="bg-stat-label">Total Manuales</div>
            </div>
        </div>

        <div class="bg-stat-card">
            <div class="bg-stat-icon public">
                <i class="ph-bold ph-globe"></i>
            </div>
            <div>
                <div class="bg-stat-value"><?php echo $publicCount; ?></div>
                <div class="bg-stat-label">Públicos</div>
            </div>
        </div>

        <div class="bg-stat-card">
            <div class="bg-stat-icon private">
                <i class="ph-bold ph-lock-key"></i>
            </div>
            <div>
                <div class="bg-stat-value"><?php echo $privateCount; ?></div>
                <div class="bg-stat-label">Protegidos con PIN</div>
            </div>
        </div>

        <div class="bg-stat-card">
            <div class="bg-stat-icon views">
                <i class="ph-bold ph-eye"></i>
            </div>
            <div>
                <div class="bg-stat-value"><?php echo number_format($totalViews); ?></div>
                <div class="bg-stat-label">Visualizaciones</div>
            </div>
        </div>
    </div>

    <!-- Grid of Guidelines -->
    <?php if (empty($guidelines)): ?>
        <div class="bg-empty-state">
            <div class="bg-empty-icon">
                <i class="ph-bold ph-paint-brush-broad"></i>
            </div>
            <h3 class="bg-empty-title">Aún no has creado ningún manual de marca</h3>
            <p class="bg-empty-desc">
                Crea tu primer manual en segundos: sube el logotipo principal, sus variaciones, iconos, define la paleta cromática con códigos HEX/RGB/CMYK, y genera un manual interactivo con enlace corto y exportable a PDF.
            </p>
            <a href="index.php?module=brand_guidelines&action=edit" class="bg-btn-primary" style="margin-top: 0.5rem;">
                <i class="ph-bold ph-plus-circle" style="font-size: 1.15rem;"></i>
                <span>Crear mi primer Manual de Marca</span>
            </a>
        </div>
    <?php else: ?>
        <div class="bg-grid" id="bgGrid">
            <?php foreach ($guidelines as $g): 
                $colors = !empty($g['colors_json']) ? json_decode($g['colors_json'], true) : [];
                if (!is_array($colors)) $colors = [];
                $shortUrl = bg_get_short_url($g['slug']);
                $paramUrl = bg_get_param_url($g['slug']);
                $initial = mb_substr($g['brand_name'], 0, 1, 'UTF-8');
            ?>
            <div class="bg-card" 
                 data-id="<?php echo $g['id']; ?>"
                 data-title="<?php echo htmlspecialchars(mb_strtolower($g['brand_name'], 'UTF-8')); ?>" 
                 data-client="<?php echo htmlspecialchars(mb_strtolower($g['client_name'] ?? '', 'UTF-8')); ?>"
                 data-privacy="<?php echo $g['is_public'] == 1 ? 'public' : 'private'; ?>"
                 data-views="<?php echo (int)($g['views_count'] ?? 0); ?>"
                 id="bg-card-<?php echo $g['id']; ?>">
                
                <div>
                    <!-- Card Top -->
                    <div class="bg-card-top">
                        <div class="bg-logo-preview" title="<?php echo htmlspecialchars($g['brand_name']); ?>">
                            <?php if (!empty($g['logo_primary']) && file_exists(__DIR__ . '/../../' . $g['logo_primary'])): ?>
                                <img src="<?php echo htmlspecialchars($g['logo_primary']); ?>" alt="<?php echo htmlspecialchars($g['brand_name']); ?>">
                            <?php else: ?>
                                <span class="bg-logo-initial"><?php echo htmlspecialchars($initial); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="bg-card-top-right">
                            <span class="bg-views-pill" title="Visualizaciones acumuladas">
                                <i class="ph-bold ph-eye"></i> <?php echo number_format((int)$g['views_count']); ?>
                            </span>

                            <span class="bg-badge-privacy <?php echo $g['is_public'] == 1 ? 'public' : 'private'; ?>" 
                                  onclick="togglePrivacy(<?php echo $g['id']; ?>)" 
                                  title="Haz clic para cambiar visibilidad">
                                <i class="ph-bold <?php echo $g['is_public'] == 1 ? 'ph-globe' : 'ph-lock-key'; ?>"></i>
                                <span id="badge-text-<?php echo $g['id']; ?>"><?php echo $g['is_public'] == 1 ? 'Público' : 'Protegido'; ?></span>
                            </span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="bg-card-content" style="margin-top: 1rem;">
                        <h3 class="bg-card-title"><?php echo htmlspecialchars($g['brand_name']); ?></h3>

                        <?php if (!empty($g['client_name'])): ?>
                            <span class="bg-card-client">
                                <i class="ph ph-buildings"></i> <?php echo htmlspecialchars($g['client_name']); ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($g['tagline'])): ?>
                            <p class="bg-card-tagline"><?php echo htmlspecialchars($g['tagline']); ?></p>
                        <?php elseif (!empty($g['description'])): ?>
                            <p class="bg-card-desc"><?php echo htmlspecialchars($g['description']); ?></p>
                        <?php endif; ?>

                        <!-- Color Swatches preview -->
                        <?php if (!empty($colors)): ?>
                            <div class="bg-card-swatches" title="Paleta de colores">
                                <?php 
                                $previewColors = array_slice($colors, 0, 5);
                                foreach ($previewColors as $col): 
                                    $hex = htmlspecialchars($col['hex'] ?? '#000000');
                                    $colName = htmlspecialchars($col['name'] ?? $hex);
                                ?>
                                    <div class="bg-swatch-dot" 
                                         style="background: <?php echo $hex; ?>;" 
                                         title="<?php echo $colName . ' (' . $hex . ')'; ?>"
                                         onclick="copyToClipboard('<?php echo $hex; ?>', 'Código <?php echo $hex; ?> copiado')"></div>
                                <?php endforeach; ?>
                                <?php if (count($colors) > 5): ?>
                                    <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700; margin-left: 0.2rem;">+<?php echo count($colors) - 5; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Short Link Copy Pill -->
                        <div class="bg-shortlink-pill" 
                             onclick="copyToClipboard('<?php echo htmlspecialchars($shortUrl); ?>', 'Enlace copiado al portapapeles')"
                             title="Copiar enlace corto">
                            <span>/b/<?php echo htmlspecialchars($g['slug']); ?></span>
                            <i class="ph-bold ph-copy" style="color: var(--primary-color, #262ecf);"></i>
                        </div>
                    </div>
                </div>

                <!-- Card Footer Actions -->
                <div class="bg-card-actions">
                    <a href="index.php?module=brand_guidelines&action=view&slug=<?php echo urlencode($g['slug']); ?>" 
                       target="_blank" 
                       class="bg-btn-view"
                       title="Ver manual de marca interactivo">
                        <i class="ph-bold ph-arrow-square-out"></i>
                        <span>Ver Manual</span>
                    </a>

                    <div class="bg-action-group">
                        <button type="button" 
                                class="bg-icon-btn" 
                                title="Compartir enlace / Código QR" 
                                onclick="openShareModal(<?php echo htmlspecialchars(json_encode([
                                    'id' => $g['id'],
                                    'name' => $g['brand_name'],
                                    'slug' => $g['slug'],
                                    'shortUrl' => $shortUrl,
                                    'paramUrl' => $paramUrl,
                                    'isPublic' => (int)$g['is_public']
                                ])); ?>)">
                            <i class="ph-bold ph-share-network"></i>
                        </button>

                        <a href="index.php?module=brand_guidelines&action=pdf&id=<?php echo $g['id']; ?>&download=1" 
                           class="bg-icon-btn" 
                           title="Descargar PDF">
                            <i class="ph-bold ph-file-pdf"></i>
                        </a>

                        <a href="index.php?module=brand_guidelines&action=edit&id=<?php echo $g['id']; ?>" 
                           class="bg-icon-btn" 
                           title="Editar manual">
                            <i class="ph-bold ph-pencil-simple"></i>
                        </a>

                        <button type="button" 
                                class="bg-icon-btn" 
                                title="Duplicar manual" 
                                onclick="duplicateManual(<?php echo $g['id']; ?>, '<?php echo htmlspecialchars(addslashes($g['brand_name'])); ?>')">
                            <i class="ph-bold ph-copy-simple"></i>
                        </button>

                        <button type="button" 
                                class="bg-icon-btn danger" 
                                title="Eliminar manual" 
                                onclick="deleteManual(<?php echo $g['id']; ?>, '<?php echo htmlspecialchars(addslashes($g['brand_name'])); ?>')">
                            <i class="ph-bold ph-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Off-canvas Filter Drawer -->
<div class="bg-drawer-overlay" id="filterDrawerOverlay" onclick="if(event.target === this) closeFiltersDrawer()">
    <div class="bg-drawer-content" id="filterDrawer">
        <div class="bg-drawer-header">
            <div class="bg-drawer-title-wrap">
                <div class="bg-drawer-icon"><i class="ph-bold ph-funnel"></i></div>
                <div>
                    <h3 class="bg-drawer-title">Filtros de Búsqueda</h3>
                    <span class="bg-drawer-subtitle">Filtra y ordena tus manuales</span>
                </div>
            </div>
            <button type="button" class="bg-close-btn" onclick="closeFiltersDrawer()" title="Cerrar filtros">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>

        <div class="bg-drawer-body">
            <!-- Buscar por texto -->
            <div class="bg-drawer-field">
                <label class="bg-drawer-label"><i class="ph-bold ph-magnifying-glass"></i> Buscar por Marca o Cliente</label>
                <div class="bg-drawer-input-wrap">
                    <input type="text" id="bgSearchInput" placeholder="Escribe el nombre de la marca o cliente..." oninput="applyFilters()">
                    <button type="button" class="bg-input-clear-btn" id="clearSearchBtn" onclick="clearSearchInput()" style="display:none;" title="Limpiar búsqueda">
                        <i class="ph-bold ph-x"></i>
                    </button>
                </div>
            </div>

            <!-- Estado / Privacidad -->
            <div class="bg-drawer-field">
                <label class="bg-drawer-label"><i class="ph-bold ph-shield-check"></i> Estado de Acceso</label>
                <div class="bg-drawer-chips">
                    <button type="button" class="bg-chip active" data-filter="all" onclick="selectPrivacyFilter('all')">Todos</button>
                    <button type="button" class="bg-chip" data-filter="public" onclick="selectPrivacyFilter('public')">
                        <i class="ph-bold ph-globe"></i> Públicos
                    </button>
                    <button type="button" class="bg-chip" data-filter="private" onclick="selectPrivacyFilter('private')">
                        <i class="ph-bold ph-lock-key"></i> Protegidos (PIN)
                    </button>
                </div>
                <input type="hidden" id="bgPrivacyFilter" value="all">
            </div>

            <!-- Cliente Asociado -->
            <div class="bg-drawer-field">
                <label class="bg-drawer-label"><i class="ph-bold ph-buildings"></i> Cliente Asociado</label>
                <select id="bgClientFilter" class="bg-drawer-select" onchange="applyFilters()">
                    <option value="all">Todos los clientes</option>
                    <?php 
                    $clientsList = [];
                    foreach ($guidelines as $item) {
                        if (!empty($item['client_name']) && !in_array($item['client_name'], $clientsList)) {
                            $clientsList[] = $item['client_name'];
                        }
                    }
                    sort($clientsList);
                    foreach ($clientsList as $clName): ?>
                        <option value="<?php echo htmlspecialchars(mb_strtolower($clName, 'UTF-8')); ?>">
                            <?php echo htmlspecialchars($clName); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Ordenar por -->
            <div class="bg-drawer-field">
                <label class="bg-drawer-label"><i class="ph-bold ph-sort-ascending"></i> Ordenar por</label>
                <select id="bgSortOrder" class="bg-drawer-select" onchange="applyFilters()">
                    <option value="recent">Más recientes primero</option>
                    <option value="oldest">Más antiguos primero</option>
                    <option value="name_asc">Nombre de marca (A - Z)</option>
                    <option value="name_desc">Nombre de marca (Z - A)</option>
                    <option value="views">Más visualizaciones</option>
                </select>
            </div>
        </div>

        <div class="bg-drawer-footer">
            <button type="button" class="bg-btn-drawer-reset" onclick="resetAllFilters()">
                <i class="ph-bold ph-arrow-counter-clockwise"></i> Limpiar Filtros
            </button>
            <button type="button" class="bg-btn-drawer-apply" onclick="closeFiltersDrawer()">
                <span>Ver Resultados (<span id="resultsCount"><?php echo count($guidelines); ?></span>)</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal Compartir Enlace y QR -->
<div class="bg-modal-overlay" id="shareModalOverlay" onclick="if(event.target === this) closeShareModal()">
    <div class="bg-modal-content">
        <div class="bg-modal-header">
            <div style="display:flex; align-items:center; gap:0.6rem;">
                <div style="width:36px; height:36px; border-radius:10px; background:rgba(236, 72, 153, 0.15); color:#ec4899; display:flex; align-items:center; justify-content:center; font-size:18px;">
                    <i class="ph-bold ph-share-network"></i>
                </div>
                <div>
                    <h3 class="bg-modal-title" id="shareModalBrandName">Compartir Manual</h3>
                    <span style="font-size:0.78rem; color:var(--text-muted);" id="shareModalPrivacyLabel">Acceso</span>
                </div>
            </div>
            <button type="button" class="bg-close-btn" onclick="closeShareModal()"><i class="ph-bold ph-x"></i></button>
        </div>

        <!-- QR Code -->
        <div class="bg-qr-wrap">
            <img src="" id="shareModalQrImg" class="bg-qr-img" alt="Código QR">
            <span style="font-size:0.75rem; color:var(--text-muted); margin-top:0.75rem; font-weight:600;">Escanea con tu celular para abrir el manual</span>
        </div>

        <!-- Enlace Corto -->
        <label style="font-size:0.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:0.35rem; display:block;">Enlace Corto Amigable</label>
        <div class="bg-input-copy-group">
            <input type="text" id="shareModalShortUrl" class="bg-copy-input" readonly>
            <button type="button" class="bg-copy-btn" onclick="copyModalUrl('shareModalShortUrl')">
                <i class="ph-bold ph-copy"></i> Copiar
            </button>
        </div>

        <!-- Enlace Directo Alternativo -->
        <label style="font-size:0.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:0.35rem; display:block;">Enlace Directo Parámetro</label>
        <div class="bg-input-copy-group">
            <input type="text" id="shareModalParamUrl" class="bg-copy-input" readonly>
            <button type="button" class="bg-copy-btn" onclick="copyModalUrl('shareModalParamUrl')">
                <i class="ph-bold ph-copy"></i> Copiar
            </button>
        </div>

        <!-- WhatsApp Quick Share -->
        <div style="display:flex; gap:0.75rem; margin-top:1.25rem;">
            <a href="#" id="shareModalWhatsAppBtn" target="_blank" class="btn" style="flex:1; background:#25D366; color:white; border-radius:12px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:0.5rem; padding:0.65rem;">
                <i class="ph-bold ph-whatsapp-logo" style="font-size:1.2rem;"></i> Compartir por WhatsApp
            </a>
            <button type="button" class="btn btn-secondary" onclick="closeShareModal()" style="border-radius:12px; font-weight:600;">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Filter Drawer controls
function openFiltersDrawer() {
    const overlay = document.getElementById('filterDrawerOverlay');
    if (overlay) {
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeFiltersDrawer() {
    const overlay = document.getElementById('filterDrawerOverlay');
    if (overlay) {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFiltersDrawer();
    }
});

function selectPrivacyFilter(val) {
    const hidden = document.getElementById('bgPrivacyFilter');
    if (hidden) hidden.value = val;
    document.querySelectorAll('.bg-chip').forEach(chip => {
        chip.classList.toggle('active', chip.getAttribute('data-filter') === val);
    });
    applyFilters();
}

function clearSearchInput() {
    const input = document.getElementById('bgSearchInput');
    if (input) {
        input.value = '';
        input.focus();
        applyFilters();
    }
}

function resetAllFilters() {
    const searchInput = document.getElementById('bgSearchInput');
    if (searchInput) searchInput.value = '';
    selectPrivacyFilter('all');
    const clientSelect = document.getElementById('bgClientFilter');
    if (clientSelect) clientSelect.value = 'all';
    const sortSelect = document.getElementById('bgSortOrder');
    if (sortSelect) sortSelect.value = 'recent';
    applyFilters();
}

function applyFilters() {
    const searchInput = document.getElementById('bgSearchInput');
    const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const clearBtn = document.getElementById('clearSearchBtn');
    if (clearBtn) clearBtn.style.display = query ? 'flex' : 'none';

    const privacyEl = document.getElementById('bgPrivacyFilter');
    const privacy = privacyEl ? privacyEl.value : 'all';
    const clientEl = document.getElementById('bgClientFilter');
    const clientFilter = clientEl ? clientEl.value : 'all';
    const sortEl = document.getElementById('bgSortOrder');
    const sortOrder = sortEl ? sortEl.value : 'recent';

    // Calculate active filter count
    let activeCount = 0;
    if (query) activeCount++;
    if (privacy !== 'all') activeCount++;
    if (clientFilter !== 'all') activeCount++;
    if (sortOrder !== 'recent') activeCount++;

    const badge = document.getElementById('activeFiltersBadge');
    if (badge) {
        badge.textContent = activeCount;
        badge.style.display = activeCount > 0 ? 'inline-block' : 'none';
    }

    const cardsContainer = document.getElementById('bgGrid');
    const cards = Array.from(document.querySelectorAll('.bg-card'));
    let visibleCount = 0;

    cards.forEach(card => {
        const title = card.getAttribute('data-title') || '';
        const client = card.getAttribute('data-client') || '';
        const cardPrivacy = card.getAttribute('data-privacy') || '';

        const matchesQuery = !query || title.includes(query) || client.includes(query);
        const matchesPrivacy = (privacy === 'all') || (cardPrivacy === privacy);
        const matchesClient = (clientFilter === 'all') || (client === clientFilter);

        if (matchesQuery && matchesPrivacy && matchesClient) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Handle sorting
    if (cardsContainer && cards.length > 1) {
        cards.sort((a, b) => {
            if (sortOrder === 'name_asc') {
                return (a.getAttribute('data-title') || '').localeCompare(b.getAttribute('data-title') || '');
            } else if (sortOrder === 'name_desc') {
                return (b.getAttribute('data-title') || '').localeCompare(a.getAttribute('data-title') || '');
            } else if (sortOrder === 'views') {
                return parseInt(b.getAttribute('data-views') || '0', 10) - parseInt(a.getAttribute('data-views') || '0', 10);
            } else if (sortOrder === 'oldest') {
                return parseInt(a.getAttribute('data-id') || '0', 10) - parseInt(b.getAttribute('data-id') || '0', 10);
            } else { // recent
                return parseInt(b.getAttribute('data-id') || '0', 10) - parseInt(a.getAttribute('data-id') || '0', 10);
            }
        });
        cards.forEach(card => cardsContainer.appendChild(card));
    }

    const resultsCountEl = document.getElementById('resultsCount');
    if (resultsCountEl) resultsCountEl.textContent = visibleCount;
}
window.filterBrandCards = applyFilters;

// Copy to Clipboard with Toast
function copyToClipboard(text, message) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showToast(message || 'Copiado al portapapeles');
        }).catch(err => {
            fallbackCopy(text, message);
        });
    } else {
        fallbackCopy(text, message);
    }
}

function fallbackCopy(text, message) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
        showToast(message || 'Copiado al portapapeles');
    } catch (err) {
        prompt('Copia manualmente este enlace:', text);
    }
    document.body.removeChild(textArea);
}

function showToast(title) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'bottom-end',
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#ffffff' : '#0f172a'
    });
    Toast.fire({
        icon: 'success',
        title: title
    });
}

// Toggle Privacy
function togglePrivacy(id) {
    fetch('modules/brand_guidelines/ajax.php?action=toggle_privacy', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            const card = document.getElementById('bg-card-' + id);
            const badge = card.querySelector('.bg-badge-privacy');
            const badgeText = document.getElementById('badge-text-' + id);
            const icon = badge.querySelector('i');

            if (data.is_public == 1) {
                card.setAttribute('data-privacy', 'public');
                badge.className = 'bg-badge-privacy public';
                badgeText.textContent = 'Público';
                icon.className = 'ph-bold ph-globe';
            } else {
                card.setAttribute('data-privacy', 'private');
                badge.className = 'bg-badge-privacy private';
                badgeText.textContent = 'Protegido';
                icon.className = 'ph-bold ph-lock-key';
            }
        } else {
            Swal.fire('Error', data.message || 'No se pudo cambiar la privacidad', 'error');
        }
    })
    .catch(err => {
        Swal.fire('Error', 'Error de conexión al servidor', 'error');
    });
}

// Duplicate Manual
function duplicateManual(id, name) {
    Swal.fire({
        title: '¿Duplicar manual de marca?',
        text: `Se creará una copia completa de "${name}" lista para editar.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, duplicar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ec4899'
    }).then(result => {
        if (result.isConfirmed) {
            Swal.showLoading();
            fetch('modules/brand_guidelines/ajax.php?action=duplicate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('¡Duplicado!', data.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', data.message || 'No se pudo duplicar', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }
    });
}

// Delete Manual
function deleteManual(id, name) {
    Swal.fire({
        title: '¿Eliminar manual de marca?',
        text: `¿Estás seguro de que deseas eliminar permanentemente el manual "${name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444'
    }).then(result => {
        if (result.isConfirmed) {
            fetch('modules/brand_guidelines/ajax.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Manual de marca eliminado');
                    const card = document.getElementById('bg-card-' + id);
                    if (card) {
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => card.remove(), 300);
                    }
                } else {
                    Swal.fire('Error', data.message || 'No se pudo eliminar', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }
    });
}

// Share Modal Management
function openShareModal(data) {
    document.getElementById('shareModalBrandName').textContent = data.name;
    document.getElementById('shareModalPrivacyLabel').textContent = data.isPublic == 1 ? '🌐 Modo Público (Cualquiera con el enlace)' : '🔒 Modo Privado (Requiere PIN/Contraseña)';
    document.getElementById('shareModalShortUrl').value = data.shortUrl;
    document.getElementById('shareModalParamUrl').value = data.paramUrl;

    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(data.shortUrl);
    document.getElementById('shareModalQrImg').src = qrUrl;

    const waText = encodeURIComponent(`Hola! Aquí tienes el Manual de Marca de ${data.name}: ${data.shortUrl}`);
    document.getElementById('shareModalWhatsAppBtn').href = 'https://api.whatsapp.com/send?text=' + waText;

    document.getElementById('shareModalOverlay').classList.add('active');
}

function closeShareModal() {
    document.getElementById('shareModalOverlay').classList.remove('active');
}

function copyModalUrl(inputId) {
    const input = document.getElementById(inputId);
    copyToClipboard(input.value, 'Enlace copiado exitosamente');
}
</script>

<?php require_once 'includes/footer.php'; ?>
