<?php
// modules/audiovisual/index.php
require_once 'includes/header.php';
?>

<style>
/* ==========================================================================
   MODERN AUDIOVISUAL APP DESIGN SYSTEM
   Apple Bento UI + Glassmorphic Off-Canvas Drawer
   ========================================================================== */

/* ==========================================================================
   MODERN STARTUP APP DESIGN SYSTEM - AUDIOVISUAL
   Clean, Compact, Responsive & Unified with System Colors
   ========================================================================== */

:root {
    --av-primary: var(--primary-color, #4f46e5);
    --av-primary-hover: var(--primary-hover, #4338ca);
    --av-primary-light: color-mix(in srgb, var(--primary-color, #4f46e5) 10%, transparent);
    --av-primary-border: color-mix(in srgb, var(--primary-color, #4f46e5) 24%, transparent);
    --av-secondary: var(--secondary-color, #10b981);
    --av-accent: #6366f1;
    --av-bg: var(--bg-color, #f8fafc);
    --av-card-bg: var(--bg-surface, #ffffff);
    --av-text-main: var(--text-main, #0f172a);
    --av-text-muted: var(--text-muted, #64748b);
    --av-border: var(--border-color, #e2e8f0);
    --av-radius-card: 16px;
    --av-radius-sub: 12px;
    --av-radius-pill: 9999px;
    --av-shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
    --av-shadow-md: 0 4px 14px -2px rgba(0, 0, 0, 0.05), 0 2px 6px -1px rgba(0, 0, 0, 0.03);
    --av-shadow-hover: 0 10px 24px -4px rgba(0, 0, 0, 0.08), 0 3px 8px -2px rgba(0, 0, 0, 0.04);
}

[data-theme="dark"] {
    --av-card-bg: #141721;
    --av-text-main: #f8fafc;
    --av-text-muted: #94a3b8;
    --av-border: rgba(255, 255, 255, 0.08);
    --av-shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.25);
    --av-shadow-md: 0 6px 20px -3px rgba(0, 0, 0, 0.45);
    --av-shadow-hover: 0 14px 28px -4px rgba(0, 0, 0, 0.6);
}

.brand-container {
    padding: 1.15rem 1.4rem;
    max-width: 1400px;
    margin: 0 auto;
    font-family: var(--font-family, 'Inter', sans-serif);
}

/* --- APP HEADER BAR --- */
.brand-header {
    background: var(--av-card-bg);
    border: 1px solid var(--av-border);
    border-radius: var(--av-radius-card);
    padding: 0.85rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.1rem;
    flex-wrap: wrap;
    gap: 0.85rem;
    box-shadow: var(--av-shadow-sm);
    transition: all 0.2s ease;
}

.brand-title-group {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.brand-back-btn {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--av-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--av-text-main);
    font-size: 1.05rem;
    text-decoration: none;
    transition: all 0.2s ease;
}

[data-theme="dark"] .brand-back-btn {
    background: rgba(255, 255, 255, 0.04);
    color: #ffffff;
}

.brand-back-btn:hover {
    background: var(--av-primary);
    color: #ffffff !important;
    border-color: var(--av-primary);
    transform: translateY(-1px);
}

.brand-title-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.brand-kicker {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--av-primary);
}

.brand-kicker i {
    font-size: 0.85rem;
}

.brand-main-title {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

.brand-main-title h1 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 800;
    letter-spacing: -0.3px;
    color: var(--av-text-main);
}

.app-count-badge {
    background: var(--av-primary-light);
    color: var(--av-primary);
    border: 1px solid var(--av-primary-border);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.55rem;
    border-radius: var(--av-radius-pill);
    letter-spacing: 0.2px;
}

.brand-toolbar {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

/* App Search Box */
.app-search-box {
    position: relative;
    display: flex;
    align-items: center;
    width: 240px;
    transition: width 0.25s ease;
}

.app-search-box:focus-within {
    width: 290px;
}

.app-search-box .search-icon {
    position: absolute;
    left: 0.85rem;
    color: var(--av-text-muted);
    font-size: 0.95rem;
    pointer-events: none;
}

.app-search-box:focus-within .search-icon {
    color: var(--av-primary);
}

.app-search-input {
    width: 100%;
    padding: 0.52rem 2.2rem 0.52rem 2.4rem;
    border-radius: 10px;
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--av-border);
    color: var(--av-text-main);
    font-size: 0.84rem;
    font-weight: 500;
    outline: none;
    transition: all 0.2s ease;
}

[data-theme="dark"] .app-search-input {
    background: rgba(255, 255, 255, 0.04);
}

.app-search-input:focus {
    border-color: var(--av-primary);
    background: var(--av-card-bg);
    box-shadow: 0 0 0 3px var(--av-primary-light);
}

.app-search-box .clear-btn {
    position: absolute;
    right: 0.7rem;
    background: transparent;
    border: none;
    color: var(--av-text-muted);
    font-size: 0.88rem;
    cursor: pointer;
    padding: 0.2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.app-search-box .clear-btn:hover {
    color: var(--av-text-main);
    background: rgba(0, 0, 0, 0.06);
}

.btn-app-primary {
    background: var(--av-primary);
    color: #ffffff !important;
    border: none;
    padding: 0.55rem 1.15rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.84rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 3px 10px color-mix(in srgb, var(--av-primary) 30%, transparent);
}

.btn-app-primary:hover {
    background: var(--av-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 5px 16px color-mix(in srgb, var(--av-primary) 40%, transparent);
}

/* --- SEGMENTED TABS FILTER (APPLE STYLE) --- */
.app-segmented-container {
    margin-bottom: 1.1rem;
    display: flex;
    justify-content: flex-start;
}

.app-segmented-control {
    display: inline-flex;
    background: var(--av-card-bg);
    border: 1px solid var(--av-border);
    padding: 0.22rem;
    border-radius: 11px;
    gap: 0.25rem;
    box-shadow: var(--av-shadow-sm);
}

.segmented-tab {
    background: transparent;
    border: none;
    padding: 0.4rem 0.95rem;
    border-radius: 8px;
    color: var(--av-text-muted);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    transition: all 0.18s ease;
}

.segmented-tab i {
    font-size: 0.9rem;
}

.segmented-tab:hover {
    color: var(--av-text-main);
    background: rgba(0, 0, 0, 0.03);
}

[data-theme="dark"] .segmented-tab:hover {
    background: rgba(255, 255, 255, 0.05);
}

.segmented-tab.active {
    background: var(--av-primary);
    color: #ffffff;
    box-shadow: 0 2px 8px color-mix(in srgb, var(--av-primary) 35%, transparent);
}

[data-theme="dark"] .segmented-tab.active {
    background: var(--av-primary);
    color: #ffffff;
}

.tab-badge {
    background: rgba(0, 0, 0, 0.08);
    padding: 0.1rem 0.45rem;
    border-radius: var(--av-radius-pill);
    font-size: 0.7rem;
    font-weight: 700;
}

.segmented-tab.active .tab-badge {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* --- APPLE BENTO GRID --- */
.brand-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
    gap: 1.15rem;
}

/* Project Card Bento Style */
.project-card {
    background: var(--av-card-bg);
    border: 1px solid var(--av-border);
    border-radius: var(--av-radius-card);
    padding: 1.15rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    box-shadow: var(--av-shadow-sm);
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.project-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--av-primary);
    opacity: 0;
    transition: opacity 0.2s ease;
}

.project-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--av-shadow-hover);
    border-color: var(--av-primary-border);
}

.project-card:hover::before {
    opacity: 1;
}

/* Card Top Bar */
.app-card-top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.app-card-badges-left {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

/* Status Pill with Pulsing LED */
.app-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.22rem 0.65rem;
    border-radius: var(--av-radius-pill);
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid transparent;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.app-status-badge.active,
.app-status-badge.activo {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border-color: rgba(16, 185, 129, 0.25);
}
.app-status-badge.active .status-dot,
.app-status-badge.activo .status-dot {
    background: #10b981;
    box-shadow: 0 0 8px #10b981;
    animation: ledPulse 2s infinite;
}

.app-status-badge.pending,
.app-status-badge.pendiente {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border-color: rgba(245, 158, 11, 0.25);
}
.app-status-badge.pending .status-dot,
.app-status-badge.pendiente .status-dot {
    background: #f59e0b;
    box-shadow: 0 0 8px #f59e0b;
}

.app-status-badge.completed,
.app-status-badge.completado,
.app-status-badge.listo {
    background: rgba(99, 102, 241, 0.1);
    color: #818cf8;
    border-color: rgba(99, 102, 241, 0.25);
}
.app-status-badge.completed .status-dot,
.app-status-badge.completado .status-dot,
.app-status-badge.listo .status-dot {
    background: #818cf8;
}

.app-status-badge.archived,
.app-status-badge.archivado {
    background: rgba(148, 163, 184, 0.1);
    color: #94a3b8;
    border-color: rgba(148, 163, 184, 0.25);
}
.app-status-badge.archived .status-dot,
.app-status-badge.archivado .status-dot {
    background: #94a3b8;
}

@keyframes ledPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
    70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Timer Pill */
.modern-timer {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: rgba(59, 130, 246, 0.08);
    border: 1px solid rgba(59, 130, 246, 0.2);
    color: #2563eb;
    padding: 0.2rem 0.55rem;
    border-radius: var(--av-radius-pill);
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.2px;
}

.modern-timer.expired {
    background: rgba(239, 68, 68, 0.1) !important;
    border-color: rgba(239, 68, 68, 0.25) !important;
    color: #ef4444 !important;
}

.app-btn-more {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--av-border);
    color: var(--av-text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    transition: all 0.15s ease;
}

[data-theme="dark"] .app-btn-more {
    background: rgba(255, 255, 255, 0.04);
}

.app-btn-more:hover {
    background: var(--av-text-main);
    color: var(--av-card-bg);
}

/* Card Hero (Avatar + Title + Client) */
.app-bento-hero {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    cursor: pointer;
}

.app-av-avatar {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 1.15rem;
    font-weight: 800;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s ease;
}

.project-card:hover .app-av-avatar {
    transform: scale(1.05);
}

.app-av-grad-0 { background: linear-gradient(135deg, var(--av-primary, #4f46e5) 0%, #7c3aed 100%); }
.app-av-grad-1 { background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%); }
.app-av-grad-2 { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
.app-av-grad-3 { background: linear-gradient(135deg, #475569 0%, #0f172a 100%); }

.app-hero-info {
    flex: 1;
    overflow: hidden;
}

.app-bento-title {
    margin: 0 0 0.3rem 0;
    font-size: 1.02rem;
    font-weight: 700;
    color: var(--av-text-main);
    line-height: 1.35;
    letter-spacing: -0.2px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: color 0.18s ease;
}

.project-card:hover .app-bento-title {
    color: var(--av-primary);
}

.app-hero-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.app-client-chip,
.app-date-chip {
    font-size: 0.74rem;
    color: var(--av-text-muted);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.app-client-chip {
    color: var(--av-text-main);
}

/* Tags & Collaborators Stack */
.app-card-meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.65rem;
    min-height: 26px;
}

.app-card-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    flex: 1;
}

.tag-pill {
    padding: 0.18rem 0.55rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.2px;
    border: 1px solid transparent;
}

.assigned-users-stack {
    display: flex;
    align-items: center;
}

.avatar-sm {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--av-card-bg);
    margin-left: -6px;
    transition: transform 0.2s;
}

.avatar-placeholder {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--av-primary-light);
    color: var(--av-primary);
    border: 2px solid var(--av-card-bg);
    font-size: 0.68rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: -6px;
}

.avatar-more {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--bg-body, #f1f5f9);
    border: 2px solid var(--av-card-bg);
    color: var(--av-text-muted);
    font-size: 0.65rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: -6px;
}

.avatar-sm:hover,
.avatar-placeholder:hover {
    transform: translateY(-2px) scale(1.12);
    z-index: 5;
}

/* Progress Section Sleek Style */
.card-progress-section {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--av-radius-sub);
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--av-border);
    cursor: pointer;
    transition: all 0.2s ease;
}

[data-theme="dark"] .card-progress-section {
    background: rgba(255, 255, 255, 0.02);
}

.card-progress-section:hover {
    border-color: var(--av-primary-border);
    background: var(--av-primary-light);
}

.card-progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.progress-header-title {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--av-text-muted);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.progress-header-title i {
    font-size: 0.85rem;
    color: var(--av-primary);
}

.progress-percentage-badge {
    font-size: 0.74rem;
    font-weight: 700;
    padding: 0.12rem 0.5rem;
    border-radius: var(--av-radius-pill);
    letter-spacing: 0.2px;
    font-variant-numeric: tabular-nums;
}

.progress-percentage-badge.low {
    background: rgba(148, 163, 184, 0.12);
    color: #94a3b8;
}
.progress-percentage-badge.mid {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}
.progress-percentage-badge.high {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}

.card-progress-track {
    width: 100%;
    height: 5px;
    border-radius: var(--av-radius-pill);
    background: rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

[data-theme="dark"] .card-progress-track {
    background: rgba(255, 255, 255, 0.08);
}

.card-progress-fill {
    height: 100%;
    border-radius: var(--av-radius-pill);
    background: linear-gradient(90deg, var(--av-primary, #4f46e5) 0%, #10b981 100%);
    transition: width 0.4s ease;
}

.card-progress-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.72rem;
    color: var(--av-text-muted);
    font-weight: 600;
}

.stat-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

/* Google Drive Subfolder Chips */
.drive-sub-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.18rem 0.5rem;
    border-radius: 6px;
    font-size: 0.68rem;
    font-weight: 600;
    text-decoration: none;
    background: rgba(59, 130, 246, 0.08);
    color: #2563eb;
    border: 1px solid rgba(59, 130, 246, 0.2);
    transition: all 0.15s ease;
}

.drive-sub-chip:hover {
    background: rgba(59, 130, 246, 0.16);
    border-color: rgba(59, 130, 246, 0.35);
    transform: translateY(-1px);
}

.drive-sub-chip.finish {
    background: rgba(16, 185, 129, 0.08);
    color: #059669;
    border-color: rgba(16, 185, 129, 0.2);
}

.drive-sub-chip.finish:hover {
    background: rgba(16, 185, 129, 0.16);
}

.drive-sub-chip.pack {
    background: rgba(139, 92, 246, 0.08);
    color: #7c3aed;
    border-color: rgba(139, 92, 246, 0.2);
}

.drive-sub-chip.pack:hover {
    background: rgba(139, 92, 246, 0.16);
}

.drive-sub-chip.root {
    background: var(--av-primary-light);
    color: var(--av-primary);
    border-color: var(--av-primary-border);
    font-weight: 700;
}

.drive-sub-chip.root:hover {
    background: var(--av-primary);
    color: #ffffff;
}

.app-card-drive-box {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    padding: 0.35rem 0.5rem;
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--av-border);
    border-radius: var(--av-radius-sub);
}

[data-theme="dark"] .app-card-drive-box {
    background: rgba(255, 255, 255, 0.02);
}

.app-wo-chip {
    background: rgba(59, 130, 246, 0.1) !important;
    color: #2563eb !important;
    border: 1px solid rgba(59, 130, 246, 0.22) !important;
    padding: 0.12rem 0.45rem !important;
    border-radius: 6px !important;
    font-weight: 700 !important;
}

.due-expired {
    color: #ef4444 !important;
    font-weight: 700 !important;
}

/* Card Footer Link */
.app-bento-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.55rem;
    border-top: 1px solid var(--av-border);
    color: var(--av-text-muted);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s ease;
}

.app-bento-footer:hover {
    color: var(--av-primary);
}

.app-bento-footer i {
    font-size: 0.85rem;
    transition: transform 0.18s ease;
}

.app-bento-footer:hover i {
    transform: translateX(3px);
}

/* Empty State */
.brand-empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3.5rem 1.5rem;
    background: var(--av-card-bg);
    border-radius: var(--av-radius-card);
    border: 1.5px dashed var(--av-border);
    box-shadow: var(--av-shadow-sm);
}

.brand-empty-icon-box {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: var(--av-primary-light);
    color: var(--av-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 1rem auto;
}

/* ==========================================================================
   OFF-CANVAS DRAWER / BOTTOM SHEET
   ========================================================================== */

.brand-drawer-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.28s ease, visibility 0.28s;
}

.brand-drawer-overlay.active {
    opacity: 1;
    visibility: visible;
}

.brand-drawer {
    position: fixed;
    top: 0;
    right: -620px;
    width: 100%;
    max-width: 560px;
    height: 100vh;
    background: var(--av-card-bg);
    border-left: 1px solid var(--av-border);
    box-shadow: -15px 0 45px rgba(0, 0, 0, 0.25);
    z-index: 10000;
    display: flex;
    flex-direction: column;
    transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}

.brand-drawer-overlay.active .brand-drawer,
.brand-drawer.active {
    right: 0;
}

.mobile-drawer-handle-bar {
    display: none;
    width: 36px;
    height: 4px;
    border-radius: 9999px;
    background: var(--av-border);
    margin: 8px auto 2px auto;
}

/* Drawer Header */
.drawer-header {
    padding: 1.15rem 1.45rem;
    border-bottom: 1px solid var(--av-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--av-card-bg);
    flex-shrink: 0;
}

.drawer-header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.drawer-badge {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    background: var(--av-primary-light);
    color: var(--av-primary);
    border: 1px solid var(--av-primary-border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.drawer-header-titles {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.drawer-kicker {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--av-primary);
}

.drawer-header-titles h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--av-text-main);
    letter-spacing: -0.2px;
}

.drawer-close-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--av-border);
    color: var(--av-text-muted);
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.18s ease;
}

[data-theme="dark"] .drawer-close-btn {
    background: rgba(255, 255, 255, 0.05);
}

.drawer-close-btn:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.25);
    transform: rotate(90deg);
}

/* Drawer Body */
.drawer-body {
    padding: 1.15rem 1.45rem;
    overflow-y: auto;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.95rem;
}

.drawer-card-section {
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--av-border);
    border-radius: 14px;
    padding: 0.95rem 1.15rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    transition: border-color 0.2s;
}

[data-theme="dark"] .drawer-card-section {
    background: rgba(255, 255, 255, 0.02);
}

.drawer-card-section:hover {
    border-color: var(--av-primary-border);
}

.section-badge-header {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--av-primary);
    padding-bottom: 0.45rem;
    border-bottom: 1px dashed var(--av-border);
}

.section-badge-header i {
    font-size: 0.9rem;
}

/* Form Controls */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.form-group label {
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--av-text-main);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.form-group label i {
    color: var(--av-primary);
    font-size: 0.88rem;
}

.form-control {
    background: var(--av-card-bg);
    border: 1px solid var(--av-border);
    padding: 0.65rem 0.95rem;
    border-radius: 10px;
    color: var(--av-text-main);
    font-size: 0.88rem;
    font-weight: 500;
    outline: none;
    transition: all 0.18s ease;
    width: 100%;
    box-sizing: border-box;
    font-family: inherit;
}

.form-control:focus {
    border-color: var(--av-primary);
    box-shadow: 0 0 0 3px var(--av-primary-light);
}

.form-control::placeholder {
    color: var(--av-text-muted);
    opacity: 0.6;
}

.form-helper-text {
    font-size: 0.72rem;
    color: var(--av-text-muted);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-top: 0.15rem;
}

.dates-twin-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.duration-live-container {
    margin-top: -0.3rem;
}

/* Client Search with Glass Dropdown */
.client-search-wrapper {
    position: relative;
}

.client-results-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--av-card-bg);
    border: 1px solid var(--av-border);
    border-radius: 12px;
    margin-top: 0.35rem;
    max-height: 200px;
    overflow-y: auto;
    z-index: 100;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    display: none;
}

.client-result-item {
    padding: 0.65rem 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    cursor: pointer;
    transition: background 0.15s;
    border-bottom: 1px solid var(--av-border);
}

.client-result-item:last-child {
    border-bottom: none;
}

.client-result-item:hover {
    background: var(--av-primary-light);
}

/* Tag Manager */
.tag-manager-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.tag-list-editable {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.tag-badge-select {
    padding: 0.28rem 0.65rem;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    cursor: pointer;
    transition: all 0.18s ease;
    border: 1px solid transparent;
    user-select: none;
}

.tag-badge-select:hover {
    transform: scale(1.03);
}

.tag-badge-select.selected {
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
    filter: brightness(1.08);
}

.add-tag-form {
    display: flex;
    gap: 0.45rem;
    align-items: center;
}

.color-picker-input {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 9px;
    cursor: pointer;
    background: transparent;
    padding: 0;
}

.new-tag-input {
    flex: 1;
    padding: 0.55rem 0.85rem;
}

.btn-tag-add {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    background: var(--av-primary);
    color: #ffffff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.18s;
    flex-shrink: 0;
}

.btn-tag-add:hover {
    background: var(--av-primary-hover);
    transform: scale(1.04);
}

/* Upload Dropzone */
.upload-zone-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.file-input-hidden {
    display: none;
}

.upload-dropzone {
    border: 1.5px dashed var(--av-border);
    border-radius: 12px;
    padding: 1rem 0.85rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: var(--av-card-bg);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.upload-dropzone:hover {
    border-color: var(--av-primary);
    background: var(--av-primary-light);
}

.upload-icon {
    font-size: 1.5rem;
    color: var(--av-primary);
    margin-bottom: 0.15rem;
}

.upload-dropzone span {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--av-text-main);
}

.upload-dropzone small {
    font-size: 0.7rem;
    color: var(--av-text-muted);
}

.cover-previews-grid {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.cover-thumb-preview {
    width: 52px;
    height: 52px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid var(--av-border);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}

/* Tagify Overrides Unified with System */
.tagify {
    border-radius: 10px !important;
    border: 1px solid var(--av-border) !important;
    background: var(--av-card-bg) !important;
    padding: 0.35rem 0.55rem !important;
}

.tagify:focus-within {
    border-color: var(--av-primary) !important;
    box-shadow: 0 0 0 3px var(--av-primary-light) !important;
}

.tagify__tag {
    border-radius: 6px !important;
    background: var(--av-primary-light) !important;
    color: var(--av-primary) !important;
    font-weight: 600 !important;
}

/* Drawer Sticky Footer */
.drawer-footer {
    padding: 0.85rem 1.45rem;
    border-top: 1px solid var(--av-border);
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.65rem;
    background: var(--av-card-bg);
    flex-shrink: 0;
}

.btn-drawer-cancel {
    background: var(--bg-body, #f8fafc);
    color: var(--av-text-muted);
    border: 1px solid var(--av-border);
    padding: 0.65rem 1.25rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.84rem;
    cursor: pointer;
    transition: all 0.18s;
}

[data-theme="dark"] .btn-drawer-cancel {
    background: rgba(255, 255, 255, 0.05);
}

.btn-drawer-cancel:hover {
    background: rgba(0, 0, 0, 0.05);
    color: var(--av-text-main);
}

.btn-drawer-save {
    background: var(--av-primary);
    color: #ffffff !important;
    border: none;
    padding: 0.65rem 1.45rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.86rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    transition: all 0.18s ease;
    box-shadow: 0 3px 10px color-mix(in srgb, var(--av-primary) 30%, transparent);
}

.btn-drawer-save:hover {
    background: var(--av-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 5px 16px color-mix(in srgb, var(--av-primary) 40%, transparent);
}

/* ==========================================================================
   RESPONSIVE DESIGN BREAKPOINTS (MOBILE & TABLET)
   ========================================================================== */

@media (max-width: 768px) {
    .brand-container {
        padding: 0.5rem 0.65rem !important;
        max-width: 100% !important;
    }

    .brand-header {
        padding: 0.75rem 0.85rem !important;
        margin-bottom: 0.75rem !important;
        border-radius: 14px !important;
        gap: 0.65rem !important;
    }

    .brand-title-group {
        width: 100% !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
    }

    .brand-back-btn {
        width: 36px !important;
        height: 36px !important;
        border-radius: 9px !important;
        font-size: 1rem !important;
    }

    .brand-main-title h1 {
        font-size: 1.15rem !important;
    }

    .brand-toolbar {
        width: 100% !important;
        display: flex !important;
        gap: 0.45rem !important;
    }

    .app-search-box {
        flex: 1 !important;
        width: 100% !important;
    }

    .app-search-box:focus-within {
        width: 100% !important;
    }

    .app-search-input {
        padding: 0.48rem 1.8rem 0.48rem 2.1rem !important;
        font-size: 0.82rem !important;
        border-radius: 9px !important;
    }

    .btn-app-primary {
        padding: 0.48rem 0.85rem !important;
        font-size: 0.8rem !important;
        border-radius: 9px !important;
        white-space: nowrap !important;
    }

    .app-segmented-container {
        margin-bottom: 0.75rem !important;
        width: 100% !important;
    }

    .app-segmented-control {
        width: 100% !important;
        display: flex !important;
        overflow-x: auto !important;
        white-space: nowrap !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
        padding: 0.2rem !important;
        border-radius: 10px !important;
    }

    .app-segmented-control::-webkit-scrollbar {
        display: none !important;
    }

    .segmented-tab {
        flex: 1 !important;
        padding: 0.35rem 0.55rem !important;
        font-size: 0.75rem !important;
        justify-content: center !important;
        border-radius: 7px !important;
    }

    .brand-grid {
        grid-template-columns: 1fr !important;
        gap: 0.65rem !important;
    }

    .project-card {
        padding: 0.9rem !important;
        border-radius: 14px !important;
        gap: 0.65rem !important;
    }

    /* Mobile Bottom Sheet Drawer */
    .brand-drawer {
        top: auto !important;
        bottom: -100% !important;
        right: 0 !important;
        left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        height: auto !important;
        max-height: 92vh !important;
        border-radius: 20px 20px 0 0 !important;
        border-left: none !important;
        border-top: 1px solid var(--av-border) !important;
        box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.35) !important;
        transition: bottom 0.32s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .brand-drawer.active,
    .brand-drawer-overlay.active .brand-drawer {
        bottom: 0 !important;
        right: 0 !important;
    }

    .mobile-drawer-handle-bar {
        display: block !important;
    }

    .drawer-header {
        padding: 0.75rem 1rem !important;
    }

    .drawer-body {
        padding: 0.85rem 1rem !important;
        gap: 0.75rem !important;
    }

    .drawer-card-section {
        padding: 0.85rem 0.9rem !important;
        border-radius: 12px !important;
        gap: 0.65rem !important;
    }

    .drawer-footer {
        padding: 0.75rem 1rem !important;
        gap: 0.5rem !important;
    }

    .btn-drawer-cancel {
        padding: 0.6rem 1rem !important;
        font-size: 0.82rem !important;
        border-radius: 9px !important;
    }

    .btn-drawer-save {
        padding: 0.6rem 1.25rem !important;
        font-size: 0.82rem !important;
        border-radius: 9px !important;
        flex: 1 !important;
        justify-content: center !important;
    }

    .dates-twin-grid {
        grid-template-columns: 1fr !important;
        gap: 0.65rem !important;
    }
}
</style>

<div class="brand-container">
    <!-- Ultra-Modern Header App Style -->
    <div class="brand-header">
        <div class="brand-title-group">
            <a href="index.php?module=workspace&action=index" class="brand-back-btn" title="Volver al Workspace">
                <i class="ph-bold ph-arrow-left"></i>
            </a>
            <div class="brand-title-wrapper">
                <span class="brand-kicker"><i class="ph-fill ph-sparkle"></i> Catálogo General</span>
                <div class="brand-main-title">
                    <h1>Audiovisual</h1>
                    <span id="active-projects-badge" class="app-count-badge">0 activos</span>
                </div>
            </div>
        </div>
        <div class="brand-toolbar">
            <div class="app-search-box">
                <i class="ph-bold ph-magnifying-glass search-icon"></i>
                <input type="text" id="avSearchInput" class="app-search-input" placeholder="Buscar proyecto o cliente..." oninput="filterProjectsBySearch(this.value)">
                <button type="button" class="clear-btn" id="avSearchClear" onclick="clearAudiovisualSearch()" style="display:none;" title="Limpiar"><i class="ph-bold ph-x"></i></button>
            </div>
            <button class="btn-app-primary" onclick="openCreateDrawer()">
                <i class="ph-bold ph-plus"></i> <span>Nuevo Proyecto</span>
            </button>
        </div>
    </div>

    <!-- Segmented Tabs Filter (Apple Style) -->
    <div class="app-segmented-container">
        <div class="app-segmented-control">
            <button class="segmented-tab active" data-tab="Active" onclick="switchTab('Active')">
                <i class="ph-bold ph-lightning"></i> <span>Activos</span> <span class="tab-badge" id="badge-count-active">0</span>
            </button>
            <button class="segmented-tab" data-tab="Archived" onclick="switchTab('Archived')">
                <i class="ph-bold ph-archive"></i> <span>Archivados</span> <span class="tab-badge" id="badge-count-archived">0</span>
            </button>
            <button class="segmented-tab" data-tab="All" onclick="switchTab('All')">
                <i class="ph-bold ph-squares-four"></i> <span>Todos</span> <span class="tab-badge" id="badge-count-all">0</span>
            </button>
        </div>
    </div>

    <!-- Projects Grid Container -->
    <div class="brand-grid" id="projects-grid">
        <!-- Rendered dynamically -->
        <div style="grid-column: 1/-1; text-align: center; padding: 4rem 2rem; color: var(--av-text-muted);">
            <i class="ph-bold ph-spinner-gap" style="font-size: 2.2rem; animation: spin 1s linear infinite;"></i>
            <p style="margin-top: 0.75rem; font-weight: 600; font-size: 0.95rem;">Cargando producciones audiovisuales...</p>
        </div>
    </div>
</div>

<!-- Project Create/Edit Drawer Modal (Apple / iPadOS App Drawer) -->
<div class="brand-drawer-overlay" id="brand-drawer">
    <div class="brand-drawer" onclick="event.stopPropagation()">
        <div class="mobile-drawer-handle-bar"></div>
        <!-- Drawer Header -->
        <div class="drawer-header">
            <div class="drawer-header-left">
                <div class="drawer-badge">
                    <i class="ph-bold ph-video-camera"></i>
                </div>
                <div class="drawer-header-titles">
                    <span class="drawer-kicker">Producción Audiovisual</span>
                    <h2 id="drawer-title">Nuevo Proyecto</h2>
                </div>
            </div>
            <button type="button" class="drawer-close-btn" onclick="closeDrawer()" title="Cerrar">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>

        <!-- Drawer Body with Structured Cards -->
        <div class="drawer-body">
            <input type="hidden" id="p_id" value="0">
            <input type="hidden" id="existing_covers" value="">
            <input type="hidden" id="p_client_id" value="">
            <input type="hidden" id="p_drive_subfolders" value="">

            <!-- Card 1: Información Básica -->
            <div class="drawer-card-section">
                <div class="section-badge-header">
                    <i class="ph-bold ph-film-strip"></i> Información Principal
                </div>
                
                <div class="form-group">
                    <label><i class="ph-bold ph-text-t"></i> Título de la Producción *</label>
                    <input type="text" id="p_title" class="form-control" placeholder="Ej: Video Comercial Temporada, Spot Corporativo, Reels Pack...">
                </div>

                <div class="form-group">
                    <label><i class="ph-bold ph-buildings"></i> Cliente Asociado</label>
                    <div class="client-search-wrapper">
                        <input type="text" id="p_client_search" class="form-control" placeholder="Buscar cliente por nombre o empresa..." oninput="searchClients(this.value)" autocomplete="off">
                        <input type="hidden" id="p_client_name" value="">
                        <div class="client-results-dropdown" id="client-results"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="ph-bold ph-receipt"></i> Orden de Servicio (O.S.)</label>
                    <select id="p_work_order_id" class="form-control" onchange="onWorkOrderSelected(this.value)">
                        <option value="">-- Sin orden de servicio vinculada --</option>
                    </select>
                    <span class="form-helper-text">
                        <i class="ph-bold ph-link"></i> Vincula la orden de servicio activa del cliente para trazabilidad y entregables.
                    </span>
                </div>

                <div class="form-group">
                    <label><i class="ph-bold ph-notebook"></i> Brief Vinculado (Opcional)</label>
                    <select id="p_form_submission" class="form-control">
                        <option value="">-- Sin formulario vinculado --</option>
                    </select>
                    <span class="form-helper-text">
                        <i class="ph-bold ph-info"></i> Asocia respuestas de briefs o requerimientos recibidos del cliente.
                    </span>
                </div>
            </div>

            <!-- Card 2: Cronograma & Estado -->
            <div class="drawer-card-section">
                <div class="section-badge-header">
                    <i class="ph-bold ph-calendar-check"></i> Cronograma & Estado
                </div>

                <div class="dates-twin-grid">
                    <div class="form-group">
                        <label><i class="ph-bold ph-calendar-blank"></i> Fecha de Inicio</label>
                        <input type="date" id="p_start" class="form-control" onchange="calcFormDuration()">
                    </div>
                    <div class="form-group">
                        <label><i class="ph-bold ph-clock"></i> Fecha Límite</label>
                        <input type="date" id="p_due" class="form-control" onchange="calcFormDuration()">
                    </div>
                </div>
                <div id="form-duration-calc" class="duration-live-container"></div>

                <div class="form-group">
                    <label><i class="ph-bold ph-flag"></i> Estado del Proyecto</label>
                    <select id="p_status" class="form-control">
                        <option value="Active">Activo</option>
                        <option value="Pending">Pendiente</option>
                        <option value="Completed">Completado</option>
                        <option value="Archived">Archivado</option>
                    </select>
                </div>
            </div>

            <!-- Card 3: Recursos & Equipo -->
            <div class="drawer-card-section">
                <div class="section-badge-header">
                    <i class="ph-bold ph-users-three"></i> Equipo & Almacenamiento
                </div>

                <div class="form-group">
                    <label><i class="ph-bold ph-users"></i> Colaboradores Asignados</label>
                    <input type="text" id="p_users" placeholder="Escribe para buscar y asignar colaboradores...">
                    <span class="form-helper-text">
                        <i class="ph-bold ph-user-check"></i> Usuarios registrados en el sistema asignados a la producción.
                    </span>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; flex-wrap: wrap; gap: 0.5rem;">
                        <label style="margin: 0;"><i class="ph-bold ph-google-drive-logo" style="color: #3b82f6;"></i> Enlace Carpeta Google Drive</label>
                        <button type="button" id="btn-sync-drive-subfolders" onclick="autoCreateDriveStructure()" style="background: rgba(59, 130, 246, 0.12); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.3); font-size: 0.74rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem; transition: all 0.2s ease;">
                            <i class="ph-bold ph-sparkle"></i> Crear Estructura en Drive
                        </button>
                    </div>
                    <input type="url" id="p_drive_url" class="form-control" placeholder="https://drive.google.com/drive/folders/..." oninput="extractDriveId(this.value)">
                    <input type="hidden" id="p_drive_id" value="">
                    <div style="display:flex; flex-direction:column; gap:0.35rem; margin-top:0.35rem;">
                        <span style="font-size:0.7rem; font-weight:700; color:var(--av-text-muted); text-transform:uppercase; letter-spacing:0.4px;">
                            <i class="ph-bold ph-folders"></i> Estructura automática:
                        </span>
                        <div style="display:flex; gap:0.35rem; flex-wrap:wrap;">
                            <span class="drive-sub-chip" style="font-size:0.68rem; padding:0.18rem 0.5rem;"><i class="ph-bold ph-folders"></i> 01. Referencias</span>
                            <span class="drive-sub-chip finish" style="font-size:0.68rem; padding:0.18rem 0.5rem;"><i class="ph-bold ph-video-camera"></i> 02. Videos</span>
                            <span class="drive-sub-chip pack" style="font-size:0.68rem; padding:0.18rem 0.5rem;"><i class="ph-bold ph-package"></i> 03. Empaquetados</span>
                        </div>
                    </div>
                    <div id="drive-subfolders-badges" style="display: none; margin-top: 0.4rem; gap: 0.35rem; flex-wrap: wrap;"></div>
                </div>

                <div class="form-group">
                    <label><i class="ph-bold ph-image"></i> Portada / Referencias Visuales</label>
                    <div class="upload-zone-wrapper">
                        <label class="upload-dropzone" for="p_cover_files">
                            <i class="ph-bold ph-cloud-arrow-up upload-icon"></i>
                            <span>Seleccionar o soltar imágenes</span>
                            <small>Formatos admitidos: PNG, JPG, WEBP</small>
                        </label>
                        <input type="file" id="p_cover_files" class="file-input-hidden" accept="image/*" multiple>
                        <div id="cover-preview-container" class="cover-previews-grid"></div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Notas & Etiquetas -->
            <div class="drawer-card-section">
                <div class="section-badge-header">
                    <i class="ph-bold ph-tag"></i> Clasificación & Notas
                </div>

                <div class="form-group">
                    <label><i class="ph-bold ph-article"></i> Descripción / Requerimientos Técnicos</label>
                    <textarea id="p_description" class="form-control" rows="3" placeholder="Aspectos clave de la producción, resolución, codecs, formato (16:9, 9:16) o notas del rodaje..."></textarea>
                </div>

                <div class="form-group">
                    <label><i class="ph-bold ph-tags"></i> Etiquetas del Proyecto</label>
                    <div class="tag-manager-wrapper">
                        <div class="tag-list-editable" id="tag-selector-list">
                            <!-- Tags rendered dynamically -->
                        </div>
                        <div class="add-tag-form">
                            <input type="color" id="new_tag_color" value="#f59e0b" class="color-picker-input" title="Color de la etiqueta">
                            <input type="text" id="new_tag_name" class="form-control new-tag-input" placeholder="Nueva etiqueta (ej: 4K, Drone, Spot TV)...">
                            <button type="button" class="btn-tag-add" onclick="createNewTag()" title="Añadir Etiqueta">
                                <i class="ph-bold ph-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Frosted Sticky Drawer Footer -->
        <div class="drawer-footer">
            <button type="button" class="btn-drawer-cancel" onclick="closeDrawer()">Cancelar</button>
            <button type="button" class="btn-drawer-save" onclick="saveProject()">
                <i class="ph-bold ph-check"></i> Guardar Proyecto
            </button>
        </div>
    </div>
</div>

<script>
let allTags = [];
let currentProjectTags = [];
let allProjects = [];
let systemUsers = [];
let usersTagify;
let activeTab = 'Active';
let searchQuery = '';

document.addEventListener('DOMContentLoaded', () => {
    loadTags();
    loadProjects();
    loadSystemUsers();
    loadFormSubmissions();
    loadWorkOrders();
    
    // Close drawer on overlay click
    const drawerOverlay = document.getElementById('brand-drawer');
    if (drawerOverlay) {
        drawerOverlay.addEventListener('click', (e) => {
            if (e.target === drawerOverlay) {
                closeDrawer();
            }
        });
    }

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeDrawer();
        }
    });

    // Handle cover preview
    const coverInput = document.getElementById('p_cover_files');
    if (coverInput) {
        coverInput.addEventListener('change', function() {
            const preview = document.getElementById('cover-preview-container');
            if (!preview) return;
            preview.innerHTML = '';
            if (this.files) {
                Array.from(this.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'cover-thumb-preview';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                });
            }
        });
    }

    // Drag and drop support for dropzone
    const dropzone = document.querySelector('.upload-dropzone');
    if (dropzone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = 'var(--av-primary)';
                dropzone.style.background = 'rgba(245, 158, 11, 0.08)';
            });
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.borderColor = '';
                dropzone.style.background = '';
            });
        });
        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                const fileInput = document.getElementById('p_cover_files');
                if (fileInput) {
                    fileInput.files = dt.files;
                    const changeEvent = new Event('change');
                    fileInput.dispatchEvent(changeEvent);
                }
            }
        });
    }
});

function loadFormSubmissions() {
    let formData = new FormData();
    formData.append('action', 'get_form_submissions');
    fetch('ajax/ajax_audiovisual.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success && data.submissions) {
            let select = document.getElementById('p_form_submission');
            select.innerHTML = '<option value="">-- Sin formulario vinculado --</option>';
            data.submissions.forEach(sub => {
                let option = document.createElement('option');
                option.value = sub.id;
                let text = sub.correlativo;
                if(sub.form_name) text += ` - ${sub.form_name}`;
                if(sub.respondent_name) text += ` (${sub.respondent_name})`;
                option.text = text;
                select.appendChild(option);
            });
        }
    });
}

function loadSystemUsers() {
    let formData = new FormData();
    formData.append('action', 'get_system_users');
    fetch('ajax/ajax_audiovisual.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            systemUsers = data.users;
            let input = document.querySelector('#p_users');
            if (input && typeof Tagify !== 'undefined') {
                usersTagify = new Tagify(input, {
                    whitelist: systemUsers.map(u => ({ value: u.name, id: u.id, avatar: u.avatar })),
                    enforceWhitelist: true,
                    dropdown: {
                        enabled: 0,
                        maxItems: 20,
                        closeOnSelect: false
                    }
                });
            }
        }
    });
}

function calcFormDuration() {
    let startStr = document.getElementById('p_start').value;
    let dueStr = document.getElementById('p_due').value;
    let calcEl = document.getElementById('form-duration-calc');
    
    if (startStr && dueStr) {
        let start = new Date(startStr);
        let due = new Date(dueStr);
        let diff = due - start;
        
        if (diff < 0) {
            calcEl.innerHTML = '<span style="color:#ef4444;"><i class="ph ph-warning"></i> La fecha límite no puede ser anterior al inicio.</span>';
        } else {
            let days = Math.round(diff / (1000 * 60 * 60 * 24));
            calcEl.innerHTML = `<span style="color:#10b981;"><i class="ph ph-clock"></i> Duración estimada: ${days} ${days === 1 ? 'día' : 'días'}</span>`;
        }
    } else {
        calcEl.innerHTML = '';
    }
}

function extractDriveId(url) {
    if (!url) {
        document.getElementById('p_drive_id').value = '';
        return;
    }
    const match = url.match(/folders\/([a-zA-Z0-9_-]+)/);
    if (match && match[1]) {
        document.getElementById('p_drive_id').value = match[1];
    }
}

function loadTags() {
    let formData = new FormData();
    formData.append('action', 'get_tags');
    fetch('ajax/ajax_audiovisual.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            allTags = data.tags;
            renderTagSelector();
        }
    });
}

function renderTagSelector() {
    let container = document.getElementById('tag-selector-list');
    container.innerHTML = '';
    allTags.forEach(tag => {
        let isSelected = currentProjectTags.includes(parseInt(tag.id));
        let badge = document.createElement('span');
        badge.className = `tag-badge-select ${isSelected ? 'selected' : ''}`;
        badge.style.background = `color-mix(in srgb, ${tag.color} 20%, transparent)`;
        badge.style.color = tag.color;
        badge.style.borderColor = tag.color;
        badge.innerHTML = `<i class="ph-bold ${isSelected ? 'ph-check' : 'ph-plus'}"></i> ${tag.name}`;
        badge.onclick = () => toggleTagSelection(parseInt(tag.id));
        container.appendChild(badge);
    });
}

function toggleTagSelection(tagId) {
    let idx = currentProjectTags.indexOf(tagId);
    if(idx > -1) {
        currentProjectTags.splice(idx, 1);
    } else {
        currentProjectTags.push(tagId);
    }
    renderTagSelector();
}

function createNewTag() {
    let name = document.getElementById('new_tag_name').value.trim();
    let color = document.getElementById('new_tag_color').value;
    if(!name) return;

    let formData = new FormData();
    formData.append('action', 'save_tag');
    formData.append('name', name);
    formData.append('color', color);

    fetch('ajax/ajax_audiovisual.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            document.getElementById('new_tag_name').value = '';
            currentProjectTags.push(parseInt(data.id));
            loadTags();
        } else {
            Swal.fire('Error', data.message || 'No se pudo crear la etiqueta', 'error');
        }
    });
}

function searchClients(q) {
    let dropdown = document.getElementById('client-results');
    if(!q || q.length < 2) {
        dropdown.style.display = 'none';
        return;
    }

    let formData = new FormData();
    formData.append('action', 'search_clients');
    formData.append('query', q);

    fetch('ajax/ajax_audiovisual.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success && data.clients.length > 0) {
            dropdown.innerHTML = '';
            data.clients.forEach(c => {
                let item = document.createElement('div');
                item.className = 'client-result-item';
                let clientDisplay = c.name + (c.email ? ` (${c.email})` : '');
                item.innerHTML = `
                    <div style="width:28px; height:28px; border-radius:50%; background:#f59e0b; color:white; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:0.75rem;">
                        ${c.name.charAt(0)}
                    </div>
                    <div>
                        <div style="font-weight:600; font-size:0.85rem;">${c.name}</div>
                        <div style="font-size:0.75rem; color:var(--av-text-muted);">${c.email || c.whatsapp || ''}</div>
                    </div>
                `;
                item.onclick = () => {
                    document.getElementById('p_client_search').value = clientDisplay;
                    document.getElementById('p_client_name').value = c.name;
                    document.getElementById('p_client_id').value = c.id || '';
                    if (c.drive_folder_id && !document.getElementById('p_drive_url').value) {
                        document.getElementById('p_drive_id').value = c.drive_folder_id;
                        document.getElementById('p_drive_url').value = 'https://drive.google.com/drive/folders/' + c.drive_folder_id;
                    }
                    dropdown.style.display = 'none';
                    loadWorkOrders(c.name);
                };
                dropdown.appendChild(item);
            });
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
    });
}

function loadWorkOrders(clientName = '', selectedId = '') {
    let formData = new FormData();
    formData.append('action', 'get_work_orders');
    if (clientName) {
        formData.append('client', clientName);
    }
    fetch('ajax/ajax_audiovisual.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        let select = document.getElementById('p_work_order_id');
        if (!select) return;
        select.innerHTML = '<option value="">-- Sin orden de servicio vinculada --</option>';
        if (data.success && data.work_orders) {
            data.work_orders.forEach(wo => {
                let opt = document.createElement('option');
                opt.value = wo.id;
                let brandPart = wo.brand ? ` (${wo.brand})` : '';
                opt.text = `${wo.correlativo || ('OS #' + wo.id)} - ${wo.client || 'Sin cliente'}${brandPart}`;
                if (selectedId && String(selectedId) === String(wo.id)) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            });
        }
    })
    .catch(err => console.error('Error cargando ordenes de servicio:', err));
}

function onWorkOrderSelected(val) {
    if (!val) return;
    const select = document.getElementById('p_work_order_id');
    const optText = select.options[select.selectedIndex]?.text || '';
    const titleInput = document.getElementById('p_title');
    if (titleInput && !titleInput.value.trim() && optText) {
        const parts = optText.split(' - ');
        if (parts.length > 0) {
            titleInput.value = `Producción Audiovisual - ${parts[0]}`;
        }
    }
}

function autoCreateDriveStructure() {
    let title = document.getElementById('p_title').value.trim();
    let clientName = document.getElementById('p_client_name').value.trim() || document.getElementById('p_client_search').value.trim();
    let clientId = document.getElementById('p_client_id').value;
    let workOrderId = document.getElementById('p_work_order_id').value;

    if (!title) {
        Swal.fire('Atención', 'Por favor ingresa primero el título de la producción.', 'warning');
        return;
    }

    const btn = document.getElementById('btn-sync-drive-subfolders');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Creando en Drive...';
    btn.disabled = true;

    let formData = new FormData();
    formData.append('action', 'sync_drive_folders');
    formData.append('title', title);
    formData.append('client_name', clientName);
    formData.append('client_id', clientId);
    formData.append('work_order_id', workOrderId);

    fetch('ajax/ajax_audiovisual.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        if (data.success) {
            if (data.folder_url) {
                document.getElementById('p_drive_url').value = data.folder_url;
            }
            if (data.folder_id) {
                document.getElementById('p_drive_id').value = data.folder_id;
            }
            if (data.subfolders) {
                document.getElementById('p_drive_subfolders').value = JSON.stringify(data.subfolders);
                renderSubfolderBadges(data.subfolders);
            }
            Swal.fire({
                icon: 'success',
                title: 'Estructura en Google Drive Creada',
                html: `Se han configurado las subcarpetas de almacenamiento:<br>
                       <ul style="text-align:left; font-size:0.85rem; margin-top:0.75rem; padding-left:1.25rem;">
                         <li><b>📁 01. Referencias Audiovisuales</b></li>
                         <li><b>🎬 02. Videos Terminados</b></li>
                         <li><b>📦 03. Empaquetados de Videos</b></li>
                       </ul>`,
                customClass: { popup: 'swal2-modern-popup' }
            });
        } else {
            Swal.fire('Error en Drive', data.message || 'No se pudo sincronizar con Google Drive', 'error');
        }
    })
    .catch(err => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        Swal.fire('Error', 'Error de conexión con el servidor', 'error');
    });
}

function renderSubfolderBadges(subfolders) {
    const container = document.getElementById('drive-subfolders-badges');
    if (!container) return;
    if (!subfolders) {
        container.style.display = 'none';
        container.innerHTML = '';
        return;
    }

    let subs = typeof subfolders === 'string' ? JSON.parse(subfolders || '{}') : subfolders;
    if (!subs || Object.keys(subs).length === 0) {
        container.style.display = 'none';
        container.innerHTML = '';
        return;
    }

    const items = [
        { key: 'referencias', label: '01. Referencias', icon: 'ph-folders', color: '#3b82f6' },
        { key: 'videos_terminados', label: '02. Videos Terminados', icon: 'ph-video-camera', color: '#10b981' },
        { key: 'empaquetados', label: '03. Empaquetados', icon: 'ph-package', color: '#8b5cf6' }
    ];

    let html = '';
    items.forEach(item => {
        const sub = subs[item.key];
        if (sub && sub.url) {
            html += `
                <a href="${sub.url}" target="_blank" style="display:inline-flex; align-items:center; gap:0.35rem; padding:0.25rem 0.55rem; background:color-mix(in srgb, ${item.color} 12%, transparent); border:1px solid color-mix(in srgb, ${item.color} 30%, transparent); color:${item.color}; border-radius:6px; font-size:0.72rem; font-weight:700; text-decoration:none; transition:transform 0.15s ease;" title="Abrir ${item.label} en Google Drive">
                    <i class="ph-bold ${item.icon}"></i> ${item.label} <i class="ph-bold ph-arrow-up-right" style="font-size:0.65rem;"></i>
                </a>
            `;
        }
    });

    if (html) {
        container.innerHTML = html;
        container.style.display = 'flex';
    } else {
        container.style.display = 'none';
    }
}

function loadProjects() {
    let formData = new FormData();
    formData.append('action', 'get_projects');
    fetch('ajax/ajax_audiovisual.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            allProjects = data.projects || [];
            renderProjects();
        } else {
            console.error('Error al cargar proyectos:', data.message);
            const container = document.getElementById('projects-grid');
            if (container) {
                container.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 4rem 1.5rem; background: var(--av-surface); border-radius: 20px; border: 1px dashed var(--av-border);">
                        <i class="ph-bold ph-warning-circle" style="font-size: 2.2rem; color: #ef4444; margin-bottom: 0.75rem; display: inline-block;"></i>
                        <h4 style="margin: 0 0 0.5rem 0; font-size: 1.1rem; color: var(--av-text-main); font-weight: 700;">No se pudieron cargar los proyectos</h4>
                        <p style="margin: 0 0 1.25rem 0; font-size: 0.85rem; color: var(--av-text-muted);">${data.message || 'Error de conexión con el servidor.'}</p>
                        <button onclick="loadProjects()" class="btn-app-submit" style="padding: 0.55rem 1.25rem; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
                            <i class="ph-bold ph-arrows-clockwise"></i> Reintentar
                        </button>
                    </div>
                `;
            }
        }
    })
    .catch(err => {
        console.error('Fetch error:', err);
        const container = document.getElementById('projects-grid');
        if (container) {
            container.innerHTML = `
                <div style="grid-column: 1/-1; text-align: center; padding: 4rem 1.5rem; background: var(--av-surface); border-radius: 20px; border: 1px dashed var(--av-border);">
                    <i class="ph-bold ph-wifi-slash" style="font-size: 2.2rem; color: #ef4444; margin-bottom: 0.75rem; display: inline-block;"></i>
                    <h4 style="margin: 0 0 0.5rem 0; font-size: 1.1rem; color: var(--av-text-main); font-weight: 700;">Error de conexión</h4>
                    <p style="margin: 0 0 1.25rem 0; font-size: 0.85rem; color: var(--av-text-muted);">${err.message || 'No se pudo comunicar con el backend.'}</p>
                    <button onclick="loadProjects()" class="btn-app-submit" style="padding: 0.55rem 1.25rem; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
                        <i class="ph-bold ph-arrows-clockwise"></i> Reintentar
                    </button>
                </div>
            `;
        }
    });
}

function filterProjectsBySearch(query) {
    searchQuery = (query || '').toLowerCase().trim();
    const clearBtn = document.getElementById('avSearchClear');
    if (clearBtn) {
        clearBtn.style.display = searchQuery ? 'flex' : 'none';
    }
    renderProjects();
}

function clearAudiovisualSearch() {
    const input = document.getElementById('avSearchInput');
    if (input) input.value = '';
    filterProjectsBySearch('');
}

function switchTab(tab) {
    activeTab = tab;
    document.querySelectorAll('.segmented-tab').forEach(b => {
        b.classList.toggle('active', b.dataset.tab === tab);
    });
    renderProjects();
}

function renderProjects() {
    const container = document.getElementById('projects-grid');
    if (!container) return;

    // Calculate dynamic counts across all projects
    const activeCount = allProjects.filter(p => p.status === 'Active' || p.status === 'Pending').length;
    const archivedCount = allProjects.filter(p => p.status === 'Archived' || p.status === 'Completed').length;
    const allCount = allProjects.length;

    const elAct = document.getElementById('badge-count-active');
    const elArc = document.getElementById('badge-count-archived');
    const elAll = document.getElementById('badge-count-all');
    const elMainBadge = document.getElementById('active-projects-badge');

    if (elAct) elAct.textContent = activeCount;
    if (elArc) elArc.textContent = archivedCount;
    if (elAll) elAll.textContent = allCount;
    if (elMainBadge) elMainBadge.textContent = `${activeCount} activo${activeCount === 1 ? '' : 's'}`;

    // Filter by tab
    let filtered = allProjects.filter(p => {
        if (activeTab === 'Active') {
            return p.status === 'Active' || p.status === 'Pending';
        }
        if (activeTab === 'Archived') {
            return p.status === 'Archived' || p.status === 'Completed';
        }
        return true; // 'All'
    });

    // Filter by live search query
    if (searchQuery) {
        filtered = filtered.filter(p => {
            const titleMatch = (p.title || '').toLowerCase().includes(searchQuery);
            const clientMatch = (p.client_name || '').toLowerCase().includes(searchQuery);
            const descMatch = (p.description || '').toLowerCase().includes(searchQuery);
            const tagMatch = (p.tags || []).some(t => (t.name || '').toLowerCase().includes(searchQuery));
            return titleMatch || clientMatch || descMatch || tagMatch;
        });
    }

    if (filtered.length === 0) {
        let emptyTitle = 'No hay producciones encontradas';
        let emptySub = 'Crea tu primera producción audiovisual o ajusta los filtros de búsqueda.';
        if (searchQuery) {
            emptyTitle = 'Sin resultados';
            emptySub = `No se encontraron producciones que coincidan con "${searchQuery}".`;
        } else if (activeTab === 'Active') {
            emptyTitle = 'No hay proyectos activos';
            emptySub = 'No tienes producciones en curso actualmente.';
        } else if (activeTab === 'Archived') {
            emptyTitle = 'No hay proyectos archivados';
            emptySub = 'No hay producciones archivadas o completadas.';
        }

        container.innerHTML = `
            <div class="brand-empty-state">
                <div class="brand-empty-icon-box">
                    <i class="ph-bold ph-video-camera"></i>
                </div>
                <h3 style="font-size: 1.3rem; font-weight: 800; color: var(--av-text-main); margin-bottom: 0.5rem; letter-spacing: -0.3px;">
                    ${emptyTitle}
                </h3>
                <p style="color: var(--av-text-muted); font-size: 0.92rem; max-width: 440px; margin: 0 auto 1.75rem auto; line-height: 1.5;">
                    ${emptySub}
                </p>
                <button type="button" class="btn-app-primary" onclick="openCreateDrawer()">
                    <i class="ph-bold ph-plus"></i> Crear Nuevo Proyecto
                </button>
            </div>
        `;
        return;
    }

    container.innerHTML = filtered.map((p, idx) => {
        let clientDisplayName = p.client_name || 'Cliente sin asignar';
        let formattedDate = p.created_at ? new Date(p.created_at).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
        
        let tagsHtml = (p.tags || []).map(t => `
            <span class="tag-pill" style="background: color-mix(in srgb, ${t.color || '#f59e0b'} 15%, transparent); color: ${t.color || '#f59e0b'}; border-color: color-mix(in srgb, ${t.color || '#f59e0b'} 30%, transparent);">
                ${t.name}
            </span>
        `).join('');

        let usersHtml = '';
        if (p.assigned_users && p.assigned_users.length > 0) {
            usersHtml = '<div class="assigned-users-stack">';
            p.assigned_users.slice(0, 4).forEach((u, i) => {
                let initial = (u.name || 'U').charAt(0).toUpperCase();
                let zIndex = 10 - i;
                let safeName = (u.name || 'Usuario').replace(/"/g, '&quot;');
                if (u.avatar && u.avatar !== 'default.png') {
                    usersHtml += `<img src="${u.avatar}" class="avatar-sm" style="z-index:${zIndex};" title="${safeName}" alt="${safeName}" onerror="this.outerHTML='<div class=\\'avatar-placeholder\\' style=\\'z-index:${zIndex};\\' title=\\'${safeName}\\'>${initial}</div>'">`;
                } else {
                    usersHtml += `<div class="avatar-placeholder" style="z-index:${zIndex};" title="${safeName}">${initial}</div>`;
                }
            });
            if (p.assigned_users.length > 4) {
                usersHtml += `<div class="avatar-more" style="z-index:1;" title="${p.assigned_users.length - 4} colaboradores más">+${p.assigned_users.length - 4}</div>`;
            }
            usersHtml += '</div>';
        }

        let progress = Math.min(100, Math.max(0, parseInt(p.progress || 0)));
        let progressClass = progress < 35 ? 'low' : (progress < 75 ? 'mid' : 'high');
        let totalTasks = parseInt(p.total_tasks || 0);
        let completedTasks = parseInt(p.completed_tasks || 0);
        let totalSubtasks = parseInt(p.total_subtasks || 0);
        let completedSubtasks = parseInt(p.completed_subtasks || 0);

        // Timer calculation
        let isOverdue = false;
        let timerHtml = '';
        if (p.due_date) {
            let due = new Date(p.due_date + 'T23:59:59');
            let now = new Date();
            let diff = due - now;
            if (diff < 0) {
                isOverdue = true;
                timerHtml = `<span class="modern-timer expired"><i class="ph-bold ph-hourglass-simple-low"></i> Tiempo agotado</span>`;
            } else {
                let days = Math.floor(diff / (1000 * 60 * 60 * 24));
                timerHtml = `<span class="modern-timer"><i class="ph-bold ph-timer"></i> ${days} ${days === 1 ? 'día' : 'días'}</span>`;
            }
        }

        let statusClass = (p.status || 'Active').toLowerCase();
        let statusMap = { 'Active': 'Activo', 'Pending': 'Pendiente', 'Completed': 'Listo', 'Archived': 'Archivado' };
        let statusLabel = statusMap[p.status] || p.status || 'Activo';

        let driveBoxHtml = '';
        let subObj = null;
        if (p.drive_subfolders_json) {
            try {
                subObj = typeof p.drive_subfolders_json === 'string' ? JSON.parse(p.drive_subfolders_json) : p.drive_subfolders_json;
            } catch(e) {}
        }
        if (p.drive_folder_url) {
            driveBoxHtml = `
                <div class="app-card-drive-box" onclick="event.stopPropagation()">
                    <a href="${p.drive_folder_url}" target="_blank" class="drive-sub-chip root" title="Abrir carpeta raíz en Google Drive">
                        <i class="ph-bold ph-google-drive-logo"></i> Drive
                    </a>
                    ${subObj && subObj.referencias && subObj.referencias.url ? `<a href="${subObj.referencias.url}" target="_blank" class="drive-sub-chip" title="01. Referencias Audiovisuales"><i class="ph-bold ph-folder-notch-open"></i> Referencias</a>` : ''}
                    ${subObj && subObj.videos_terminados && subObj.videos_terminados.url ? `<a href="${subObj.videos_terminados.url}" target="_blank" class="drive-sub-chip finish" title="02. Videos Terminados"><i class="ph-bold ph-video-camera"></i> Terminados</a>` : ''}
                    ${subObj && subObj.empaquetados && subObj.empaquetados.url ? `<a href="${subObj.empaquetados.url}" target="_blank" class="drive-sub-chip pack" title="03. Empaquetados"><i class="ph-bold ph-package"></i> Empaquetados</a>` : ''}
                </div>
            `;
        }

        let cleanTitle = p.title || 'Producción Audiovisual';
        let avatarLetter = cleanTitle.charAt(0).toUpperCase();
        let gradIdx = idx % 4;

        return `
            <div class="project-card">
                <!-- Top Status Bar & Options -->
                <div class="app-card-top-bar">
                    <div class="app-card-badges-left">
                        <span class="app-status-badge ${statusClass}">
                            <span class="status-dot"></span> ${statusLabel}
                        </span>
                        ${timerHtml}
                    </div>
                    <button type="button" class="app-btn-more" onclick="openProjectMenu(${p.id}, event)" title="Opciones">
                        <i class="ph-bold ph-dots-three"></i>
                    </button>
                </div>

                <!-- Hero Section: Squircle Avatar + Title + Client -->
                <div class="app-bento-hero" onclick="window.location.href='index.php?module=audiovisual&action=view&id=${p.id}'" title="Abrir tablero de producción">
                    <div class="app-av-avatar app-av-grad-${gradIdx}">
                        ${avatarLetter}
                    </div>
                    <div class="app-hero-info">
                        <h3 class="app-bento-title">${p.title}</h3>
                        <div class="app-hero-meta">
                            <span class="app-client-chip"><i class="ph-bold ph-buildings"></i> ${clientDisplayName}</span>
                            ${p.work_order_correlativo ? `
                                <span class="app-client-chip app-wo-chip">
                                    <i class="ph-bold ph-receipt"></i> ${p.work_order_correlativo}
                                </span>
                            ` : ''}
                            ${p.due_date ? `
                                <span class="app-date-chip ${isOverdue ? 'due-expired' : ''}" title="Entrega: ${formatDateDisplay(p.due_date)}">
                                    <i class="ph-bold ph-calendar-check"></i> ${formatDateDisplay(p.due_date)}
                                </span>
                            ` : (formattedDate ? `
                                <span class="app-date-chip"><i class="ph-bold ph-calendar-blank"></i> ${formattedDate}</span>
                            ` : '')}
                        </div>
                    </div>
                </div>

                <!-- Tags & Collabs Meta Row -->
                <div class="app-card-meta-row">
                    <div class="app-card-tags">
                        ${tagsHtml ? tagsHtml : '<span class="tag-pill" style="background:rgba(148,163,184,0.12);color:var(--av-text-muted);"><i class="ph-bold ph-tag"></i> General</span>'}
                    </div>
                    ${usersHtml ? usersHtml : '<span style="font-size:0.75rem;color:var(--av-text-muted);font-weight:600;"><i class="ph-bold ph-user-plus"></i> Sin asignar</span>'}
                </div>

                <!-- Progress Section -->
                <div class="card-progress-section" onclick="window.location.href='index.php?module=audiovisual&action=view&id=${p.id}'" title="Ver fases y tareas">
                    <div class="card-progress-header">
                        <span class="progress-header-title">
                            <i class="ph-bold ph-chart-donut"></i> Progreso
                        </span>
                        <span class="progress-percentage-badge ${progressClass}">
                            ${progress}%
                        </span>
                    </div>
                    <div class="card-progress-track">
                        <div class="card-progress-fill" style="width: ${progress}%;"></div>
                    </div>
                    <div class="card-progress-stats">
                        <span class="stat-chip">
                            <i class="ph-bold ph-check-circle" style="color: #10b981;"></i> <b>${completedTasks}</b>/${totalTasks} tareas
                        </span>
                        <span class="stat-chip">
                            <i class="ph-bold ph-list-checks" style="color: #6366f1;"></i> <b>${completedSubtasks}</b>/${totalSubtasks} subtareas
                        </span>
                    </div>
                </div>

                <!-- Google Drive Links Bar -->
                ${driveBoxHtml}

                <!-- Direct Access Footer -->
                <div class="app-bento-footer" onclick="window.location.href='index.php?module=audiovisual&action=view&id=${p.id}'">
                    <span>Abrir Tablero</span>
                    <i class="ph-bold ph-arrow-right"></i>
                </div>
            </div>
        `;
    }).join('');
}

function formatDateDisplay(dStr) {
    if (!dStr) return '';
    let parts = dStr.split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dStr;
}

function openProjectMenu(id, e) {
    e.stopPropagation();
    let p = allProjects.find(item => item.id == id);
    if (!p) return;

    let isArchived = p.status === 'Archived';

    Swal.fire({
        title: p.title,
        text: 'Selecciona una acción',
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: '<i class="ph-bold ph-pencil"></i> Editar',
        denyButtonText: isArchived ? '<i class="ph-bold ph-arrow-counter-clockwise"></i> Desarchivar' : '<i class="ph-bold ph-archive"></i> Archivar',
        cancelButtonText: '<i class="ph-bold ph-trash"></i> Eliminar',
        customClass: {
            popup: 'swal2-modern-popup',
            confirmButton: 'btn-app-submit',
            denyButton: 'btn-app-cancel',
            cancelButton: 'btn-app-delete'
        }
    }).then(result => {
        if (result.isConfirmed) {
            openEditDrawer(p);
        } else if (result.isDenied) {
            toggleArchive(id, isArchived ? 'Active' : 'Archived');
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            confirmDeleteProject(id);
        }
    });
}

function toggleArchive(id, newStatus) {
    let formData = new FormData();
    formData.append('action', 'change_status');
    formData.append('id', id);
    formData.append('status', newStatus);

    fetch('ajax/ajax_audiovisual.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadProjects();
        } else {
            Swal.fire('Error', data.message || 'No se pudo actualizar el estado', 'error');
        }
    });
}

function confirmDeleteProject(id) {
    Swal.fire({
        title: '¿Eliminar proyecto?',
        text: 'Esta acción eliminará permanentemente el proyecto, sus fases y todas sus tareas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        customClass: { popup: 'swal2-modern-popup' }
    }).then(result => {
        if (result.isConfirmed) {
            let formData = new FormData();
            formData.append('action', 'delete_project');
            formData.append('id', id);
            fetch('ajax/ajax_audiovisual.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        text: 'El proyecto fue eliminado correctamente',
                        timer: 1500,
                        showConfirmButton: false,
                        customClass: { popup: 'swal2-modern-popup' }
                    });
                    loadProjects();
                } else {
                    Swal.fire('Error', data.message || 'No se pudo eliminar', 'error');
                }
            });
        }
    });
}

function openCreateDrawer() {
    document.getElementById('drawer-title').innerText = 'Nuevo Proyecto';
    document.getElementById('p_id').value = '0';
    document.getElementById('p_title').value = '';
    document.getElementById('p_client_id').value = '';
    document.getElementById('p_client_search').value = '';
    document.getElementById('p_client_name').value = '';
    document.getElementById('p_work_order_id').value = '';
    document.getElementById('p_form_submission').value = '';
    document.getElementById('p_start').value = '';
    document.getElementById('p_due').value = '';
    document.getElementById('p_status').value = 'Active';
    document.getElementById('p_drive_url').value = '';
    document.getElementById('p_drive_id').value = '';
    document.getElementById('p_drive_subfolders').value = '';
    document.getElementById('p_description').value = '';
    document.getElementById('form-duration-calc').innerHTML = '';
    renderSubfolderBadges(null);
    loadWorkOrders('');
    
    // Clear files & previews
    const coverFileInput = document.getElementById('p_cover_files');
    if (coverFileInput) coverFileInput.value = '';
    const existingCovers = document.getElementById('existing_covers');
    if (existingCovers) existingCovers.value = '';
    const previewContainer = document.getElementById('cover-preview-container');
    if (previewContainer) previewContainer.innerHTML = '';
    
    // Reset tags
    if (usersTagify) {
        try {
            usersTagify.removeAllTags();
        } catch (e) {
            console.warn('Error clearing tagify:', e);
        }
    }
    currentProjectTags = [];
    renderTagSelector();

    // Show overlay and drawer
    const overlay = document.getElementById('brand-drawer');
    if (overlay) overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function openEditDrawer(p) {
    document.getElementById('drawer-title').innerText = 'Editar Proyecto';
    document.getElementById('p_id').value = p.id;
    document.getElementById('p_title').value = p.title || '';
    document.getElementById('p_client_id').value = p.client_id || '';
    document.getElementById('p_client_search').value = p.client_name || '';
    document.getElementById('p_client_name').value = p.client_name || '';
    loadWorkOrders(p.client_name, p.work_order_id);
    document.getElementById('p_form_submission').value = p.form_submission_id || '';
    document.getElementById('p_start').value = p.start_date || '';
    document.getElementById('p_due').value = p.due_date || '';
    document.getElementById('p_status').value = p.status || 'Active';
    document.getElementById('p_drive_url').value = p.drive_folder_url || '';
    document.getElementById('p_drive_id').value = p.drive_folder_id || '';
    document.getElementById('p_drive_subfolders').value = p.drive_subfolders_json ? (typeof p.drive_subfolders_json === 'string' ? p.drive_subfolders_json : JSON.stringify(p.drive_subfolders_json)) : '';
    renderSubfolderBadges(p.drive_subfolders_json);
    document.getElementById('p_description').value = p.description || '';
    calcFormDuration();

    // Existing covers
    const coverFileInput = document.getElementById('p_cover_files');
    if (coverFileInput) coverFileInput.value = '';
    const existingCovers = document.getElementById('existing_covers');
    if (existingCovers) existingCovers.value = p.cover_image || '';
    const previewContainer = document.getElementById('cover-preview-container');
    if (previewContainer) {
        previewContainer.innerHTML = '';
        if (p.cover_image) {
            p.cover_image.split(',').forEach(c => {
                const trimmed = c.trim();
                if (trimmed) {
                    const img = document.createElement('img');
                    img.src = trimmed;
                    img.className = 'cover-thumb-preview';
                    previewContainer.appendChild(img);
                }
            });
        }
    }

    if (usersTagify) {
        try {
            usersTagify.removeAllTags();
            if (p.assigned_users && p.assigned_users.length > 0) {
                usersTagify.addTags(p.assigned_users.map(u => ({ value: u.name, id: u.id })));
            }
        } catch (e) {
            console.warn('Error setting tagify:', e);
        }
    }

    currentProjectTags = (p.tags || []).map(t => parseInt(t.id));
    renderTagSelector();

    // Show overlay and drawer
    const overlay = document.getElementById('brand-drawer');
    if (overlay) overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    const overlay = document.getElementById('brand-drawer');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
}

function saveProject() {
    let id = document.getElementById('p_id').value;
    let title = document.getElementById('p_title').value.trim();
    if (!title) {
        Swal.fire('Atención', 'El título del proyecto es obligatorio', 'warning');
        return;
    }

    let clientId = document.getElementById('p_client_id').value;
    let clientName = document.getElementById('p_client_name').value || document.getElementById('p_client_search').value;
    let workOrderId = document.getElementById('p_work_order_id').value;
    let formSubmissionId = document.getElementById('p_form_submission').value;
    let startDate = document.getElementById('p_start').value;
    let dueDate = document.getElementById('p_due').value;
    let status = document.getElementById('p_status').value;
    let driveUrl = document.getElementById('p_drive_url').value;
    let driveId = document.getElementById('p_drive_id').value;
    let driveSubfolders = document.getElementById('p_drive_subfolders').value;
    let desc = document.getElementById('p_description').value;

    let assignedUsers = [];
    if (usersTagify) {
        assignedUsers = usersTagify.value.map(item => item.id);
    }

    let formData = new FormData();
    formData.append('action', 'save_project');
    formData.append('id', id);
    formData.append('title', title);
    formData.append('client_id', clientId);
    formData.append('client_name', clientName);
    formData.append('work_order_id', workOrderId);
    formData.append('form_submission_id', formSubmissionId);
    formData.append('start_date', startDate);
    formData.append('due_date', dueDate);
    formData.append('status', status);
    formData.append('drive_folder_url', driveUrl);
    formData.append('drive_folder_id', driveId);
    formData.append('drive_subfolders_json', driveSubfolders);
    formData.append('description', desc);
    formData.append('tags', JSON.stringify(currentProjectTags));
    formData.append('assigned_users', JSON.stringify(assignedUsers));

    // Cover files
    let fileInput = document.getElementById('p_cover_files');
    if (fileInput.files.length > 0) {
        for (let i = 0; i < fileInput.files.length; i++) {
            formData.append('cover_files[]', fileInput.files[i]);
        }
    }

    fetch('ajax/ajax_audiovisual.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeDrawer();
            loadProjects();
            Swal.fire({
                icon: 'success',
                title: '¡Guardado!',
                text: 'El proyecto fue guardado exitosamente.',
                timer: 1500,
                showConfirmButton: false,
                customClass: { popup: 'swal2-modern-popup' }
            });
        } else {
            Swal.fire('Error', data.message || 'Ocurrió un error al guardar', 'error');
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
