<?php
// modules/desarrollo_marca/index.php
require_once 'includes/header.php';
?>

<style>
/* 
    Modern Brand Development UI
    Following DESIGN_SYSTEM.md and modern-ui-setup
*/
:root {
    --brand-primary: var(--secondary-color, #10b981);
    --brand-secondary: var(--primary-color, #6366f1);
    --brand-bg: var(--bg-color, #09090b);
    --brand-card-bg: var(--bg-surface, #141417);
    --brand-text-main: var(--color-title, #f8fafc);
    --brand-text-muted: var(--color-text, #94a3b8);
    --brand-border: var(--border-color, rgba(255, 255, 255, 0.08));
}

[data-theme="dark"] {
    --brand-primary: var(--secondary-color, #10b981);
    --brand-secondary: var(--primary-color, #6366f1);
    --brand-bg: var(--bg-color, #09090b);
    --brand-card-bg: var(--bg-surface, #141417);
    --brand-text-main: var(--color-title, #f8fafc);
    --brand-text-muted: var(--color-text, #94a3b8);
    --brand-border: var(--border-color, rgba(255, 255, 255, 0.08));
}

.brand-container {
    padding: 1.5rem;
    max-width: 1440px;
    margin: 0 auto;
    font-family: var(--font-family, 'Inter', sans-serif);
}

/* Header Section App Style */
.brand-header {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 24px;
    padding: 1.25rem 1.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.brand-title-group {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.brand-title h1 {
    font-size: 1.45rem;
    font-weight: 700;
    color: var(--brand-text-main);
    margin: 0;
    letter-spacing: -0.3px;
}

.brand-title span {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--brand-text-muted);
}

.brand-actions .btn-primary {
    background: var(--secondary-color, #10b981);
    color: white;
    border: none;
    padding: 0.65rem 1.5rem;
    border-radius: 9999px;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px color-mix(in srgb, var(--secondary-color, #10b981) 35%, transparent);
}

.brand-actions .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--secondary-color, #10b981) 50%, transparent);
    filter: brightness(1.08);
}

/* Project Cards Grid */
.brand-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.5rem;
}
@media (max-width: 480px) {
    .brand-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
}

.brand-tabs-container {
    margin-bottom: 1.75rem;
    display: flex;
}
.brand-tabs {
    display: inline-flex;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    padding: 0.35rem;
    border-radius: 9999px;
    gap: 0.35rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.brand-tab {
    background: transparent;
    border: none;
    padding: 0.55rem 1.35rem;
    border-radius: 9999px;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--brand-text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    transition: all 0.2s ease;
}
.brand-tab:hover {
    color: var(--brand-text-main);
}
.brand-tab.active {
    background: var(--bg-color);
    color: var(--brand-text-main);
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.brand-tab.active i {
    color: var(--secondary-color, #10b981);
}

/* ==========================================================================
   Ultra Modern App Style Project Card (Light & Dark Theme)
   ========================================================================== */
.project-card {
    background: var(--bg-surface, #141417);
    border-radius: 24px;
    padding: 1.4rem;
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.25), 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    position: relative;
    overflow: hidden;
}

[data-theme="light"] .project-card {
    background: #ffffff;
    border-color: #e2e8f0;
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.06), 0 1px 3px rgba(0, 0, 0, 0.04);
}

.project-card:hover {
    transform: translateY(-4px);
    border-color: color-mix(in srgb, var(--primary-color, #6366f1) 40%, var(--border-color, rgba(255, 255, 255, 0.08)));
    box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.35), 0 0 20px color-mix(in srgb, var(--primary-color) 10%, transparent);
}

[data-theme="light"] .project-card:hover {
    box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.12), 0 0 15px color-mix(in srgb, var(--primary-color) 8%, transparent);
}

/* Card Top Bar (Status, Timer, Menu) */
.app-card-top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

.app-card-badges-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    flex: 1;
}

.app-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    border: 1px solid transparent;
}
.app-status-badge .status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.app-status-badge.active,
.app-status-badge.activo {
    background: rgba(16, 185, 129, 0.14);
    color: #10b981;
    border-color: rgba(16, 185, 129, 0.35);
}
[data-theme="light"] .app-status-badge.active,
[data-theme="light"] .app-status-badge.activo {
    background: rgba(16, 185, 129, 0.12);
    color: #047857;
    border-color: rgba(16, 185, 129, 0.3);
}
.app-status-badge.active .status-dot,
.app-status-badge.activo .status-dot {
    background: #10b981;
    box-shadow: 0 0 6px #10b981;
}

.app-status-badge.pending,
.app-status-badge.pendiente {
    background: rgba(245, 158, 11, 0.14);
    color: #f59e0b;
    border-color: rgba(245, 158, 11, 0.35);
}
[data-theme="light"] .app-status-badge.pending,
[data-theme="light"] .app-status-badge.pendiente {
    background: rgba(245, 158, 11, 0.12);
    color: #b45309;
    border-color: rgba(245, 158, 11, 0.3);
}
.app-status-badge.pending .status-dot,
.app-status-badge.pendiente .status-dot {
    background: #f59e0b;
}

.app-status-badge.completed,
.app-status-badge.completado {
    background: rgba(99, 102, 241, 0.14);
    color: #818cf8;
    border-color: rgba(99, 102, 241, 0.35);
}
[data-theme="light"] .app-status-badge.completed,
[data-theme="light"] .app-status-badge.completado {
    background: rgba(99, 102, 241, 0.12);
    color: #4338ca;
    border-color: rgba(99, 102, 241, 0.3);
}
.app-status-badge.completed .status-dot,
.app-status-badge.completado .status-dot {
    background: #818cf8;
}

.app-status-badge.archived,
.app-status-badge.archivado {
    background: rgba(148, 163, 184, 0.14);
    color: #94a3b8;
    border-color: rgba(148, 163, 184, 0.35);
}
[data-theme="light"] .app-status-badge.archived,
[data-theme="light"] .app-status-badge.archivado {
    background: rgba(100, 116, 139, 0.12);
    color: #475569;
    border-color: rgba(100, 116, 139, 0.3);
}
.app-status-badge.archived .status-dot,
.app-status-badge.archivado .status-dot {
    background: #94a3b8;
}

.modern-timer {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: rgba(245, 158, 11, 0.14);
    border: 1px solid rgba(245, 158, 11, 0.35);
    color: #fbbf24;
    padding: 0.22rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    font-variant-numeric: tabular-nums;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}
.modern-timer i {
    color: #fbbf24;
    font-size: 0.85rem;
}
[data-theme="light"] .modern-timer {
    background: #fffbeb;
    border-color: #fde68a;
    color: #b45309;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
[data-theme="light"] .modern-timer i {
    color: #d97706;
}
.modern-timer.expired {
    background: rgba(239, 68, 68, 0.18) !important;
    border-color: rgba(239, 68, 68, 0.4) !important;
    color: #ef4444 !important;
}
.modern-timer.expired i {
    color: #ef4444 !important;
}

.app-btn-more {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: var(--bg-color, #09090b);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    color: var(--text-muted, #94a3b8);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.app-btn-more:hover {
    background: var(--border-color);
    color: var(--text-main, #ffffff);
    transform: scale(1.05);
}

.btn-icon {
    background: transparent;
    border: none;
    color: var(--brand-text-muted);
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.25rem;
    border-radius: 4px;
    transition: background 0.2s;
}

.btn-icon:hover {
    background: rgba(0,0,0,0.05);
    color: var(--brand-text-main);
}
[data-theme="dark"] .btn-icon:hover { background: rgba(255,255,255,0.1); }

/* SweetAlert Modern App Style */
.swal2-modern-popup {
    border-radius: 20px !important;
    border: 1px solid rgba(0,0,0,0.05) !important;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1) !important;
}
[data-theme="dark"] .swal2-modern-popup {
    border-color: rgba(255,255,255,0.05) !important;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3) !important;
}
.swal2-modern-popup .swal2-title {
    font-size: 1.4rem !important;
    font-weight: 700 !important;
}
.swal2-modern-popup .swal2-confirm, 
.swal2-modern-popup .swal2-cancel {
    border-radius: 12px !important;
    font-weight: 600 !important;
    padding: 0.6rem 1.5rem !important;
}

/* ==========================================================================
   Modern App Style Project Card Components
   ========================================================================== */

/* Project Hero Header */
.app-card-hero {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    cursor: pointer;
}

.app-card-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-main, #ffffff);
    line-height: 1.35;
    letter-spacing: -0.3px;
    transition: color 0.2s ease;
}
.project-card:hover .app-card-title {
    color: var(--primary-color, #818cf8);
}

.app-client-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.app-client-avatar {
    width: 28px;
    height: 28px;
    border-radius: 9px;
    background: color-mix(in srgb, #f97316 18%, transparent);
    color: #fb923c;
    border: 1px solid color-mix(in srgb, #f97316 35%, transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.app-client-info {
    display: flex;
    align-items: baseline;
    gap: 0.55rem;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.app-client-name {
    font-size: 0.84rem;
    font-weight: 600;
    color: var(--text-main, #ffffff);
}
.app-client-date {
    font-size: 0.72rem;
    color: var(--text-muted, #94a3b8);
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

/* Meta: Tags & Collaborators */
.app-card-meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.65rem;
    min-height: 30px;
}
.app-card-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    flex: 1;
}

.tag-pill {
    padding: 0.2rem 0.65rem;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.2px;
    display: inline-flex;
    align-items: center;
    border: 1px solid transparent;
}

/* Assigned Users Stack */
.assigned-users-stack {
    display: inline-flex;
    align-items: center;
    flex-direction: row;
    height: 28px;
    flex-shrink: 0;
}
.assigned-users-stack .avatar-sm,
.assigned-users-stack .avatar-placeholder,
.assigned-users-stack .avatar-more {
    width: 28px;
    height: 28px;
    min-width: 28px;
    min-height: 28px;
    border-radius: 50%;
    object-fit: cover;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--bg-surface, #141417);
    margin-left: -8px;
    position: relative;
    flex-shrink: 0;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    box-sizing: border-box;
    vertical-align: middle;
    transition: transform 0.2s ease;
}
.assigned-users-stack > *:first-child {
    margin-left: 0 !important;
}
.assigned-users-stack > *:hover {
    transform: scale(1.15) translateY(-2px);
    z-index: 20 !important;
}
.assigned-users-stack .avatar-placeholder {
    background: var(--brand-secondary, #6366f1);
    color: #ffffff;
}
.assigned-users-stack .avatar-more {
    background: var(--border-color, #27272a);
    color: var(--brand-text-muted, #94a3b8);
}
.app-unassigned-pill {
    font-size: 0.72rem;
    color: var(--text-muted, #94a3b8);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: color-mix(in srgb, var(--border-color) 40%, transparent);
    padding: 0.2rem 0.55rem;
    border-radius: 8px;
    border: 1px dashed var(--border-color);
}

/* Card Progress Section */
.card-progress-section {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.95rem 1.1rem;
    border-radius: 18px;
    background: var(--bg-color, #09090b);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    transition: border-color 0.2s ease;
}
[data-theme="light"] .card-progress-section {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.card-progress-section:hover {
    border-color: color-mix(in srgb, var(--primary-color, #6366f1) 30%, var(--border-color));
}

.card-progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.progress-header-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-muted, #94a3b8);
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.progress-header-title i {
    font-size: 1rem;
    color: var(--primary-color, #6366f1);
}

.progress-percentage-badge {
    font-size: 0.78rem;
    font-weight: 800;
    padding: 0.15rem 0.6rem;
    border-radius: 9999px;
    letter-spacing: 0.3px;
    font-variant-numeric: tabular-nums;
    display: inline-flex;
    align-items: center;
    border: 1px solid transparent;
}
.progress-percentage-badge.low {
    background: rgba(148, 163, 184, 0.15);
    color: #94a3b8;
    border-color: rgba(148, 163, 184, 0.25);
}
.progress-percentage-badge.mid {
    background: color-mix(in srgb, var(--primary-color, #6366f1) 18%, transparent);
    color: #818cf8;
    border-color: color-mix(in srgb, var(--primary-color, #6366f1) 35%, transparent);
}
.progress-percentage-badge.high {
    background: color-mix(in srgb, var(--secondary-color, #10b981) 18%, transparent);
    color: #10b981;
    border-color: color-mix(in srgb, var(--secondary-color, #10b981) 35%, transparent);
}

.card-progress-track {
    width: 100%;
    height: 8px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.08);
    overflow: hidden;
    position: relative;
}
[data-theme="light"] .card-progress-track {
    background: #e2e8f0;
}

.card-progress-fill {
    height: 100%;
    border-radius: 9999px;
    background: linear-gradient(90deg, #6366f1 0%, #06b6d4 50%, #10b981 100%);
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 0 12px rgba(16, 185, 129, 0.4);
}

.card-progress-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}
.stat-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.74rem;
    color: var(--text-muted, #94a3b8);
    font-weight: 600;
    background: color-mix(in srgb, var(--bg-surface) 70%, transparent);
    padding: 0.25rem 0.6rem;
    border-radius: 8px;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
}
.stat-chip i {
    font-size: 0.95rem;
}
.stat-chip.tasks i {
    color: #818cf8;
}
.stat-chip.subtasks i {
    color: #34d399;
}
.stat-chip b {
    color: var(--text-main, #ffffff);
    font-weight: 700;
}

/* Modern Split Dates Grid */
.app-card-dates {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
}

.app-date-chip {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    background: var(--bg-color, #09090b);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    padding: 0.75rem 0.9rem;
    border-radius: 16px;
    transition: all 0.2s ease;
}
[data-theme="light"] .app-date-chip {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.app-date-chip span.label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--text-muted, #94a3b8);
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.app-date-chip span.label i {
    font-size: 0.85rem;
}
.app-date-chip.start span.label i {
    color: var(--secondary-color, #10b981);
}
.app-date-chip.due span.label i {
    color: #ef4444;
}
.app-date-chip span.value {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--text-main, #ffffff);
    letter-spacing: -0.2px;
}

/* Modern App Drive CTA */
.app-drive-cta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: color-mix(in srgb, #3b82f6 12%, var(--bg-color));
    border: 1px solid color-mix(in srgb, #3b82f6 30%, transparent);
    border-radius: 16px;
    color: #60a5fa;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.app-drive-cta:hover {
    background: color-mix(in srgb, #3b82f6 20%, var(--bg-color));
    border-color: color-mix(in srgb, #3b82f6 50%, transparent);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px color-mix(in srgb, #3b82f6 20%, transparent);
}
.drive-cta-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.drive-cta-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: color-mix(in srgb, #3b82f6 20%, transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    color: #3b82f6;
}
.drive-cta-text {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    text-align: left;
}
.drive-cta-text span {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--text-main, #ffffff);
}
.drive-cta-text small {
    font-size: 0.7rem;
    color: #93c5fd;
    font-weight: 500;
}
.drive-cta-arrow {
    font-size: 1.1rem;
    color: #60a5fa;
    opacity: 0.8;
    transition: transform 0.2s ease;
}
.app-drive-cta:hover .drive-cta-arrow {
    transform: translateX(2px) translateY(-2px);
    opacity: 1;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.85rem;
    color: var(--brand-text-muted);
}

.detail-row i {
    font-size: 1.1rem;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.detail-row.brand-tool i { color: #F24E1E; background: rgba(242, 78, 30, 0.1); } /* Figma */
.detail-row.brand-drive i { color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
.detail-row.brand-messages i { color: #ef4444; position: relative; background: rgba(239, 68, 68, 0.1); }
.detail-row.brand-date i { color: #10b981; background: rgba(16, 185, 129, 0.1); }
.detail-row.brand-due i { color: #f59e0b; background: rgba(245, 158, 11, 0.1); }
.detail-row.brand-messages .msg-dot {
    position: absolute;
    top: 0; right: 0;
    width: 6px; height: 6px;
    background: #ef4444;
    border-radius: 50%;
}

/* --- SIDEBAR DRAWER (APP STYLE) --- */
.drawer-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.drawer-overlay.active {
    opacity: 1;
    visibility: visible;
}

.drawer-panel {
    position: fixed;
    top: 0; right: -700px;
    width: 100%; max-width: 620px;
    height: 100%;
    background: var(--bg-surface, #121212);
    box-shadow: -20px 0 60px rgba(0, 0, 0, 0.5);
    z-index: 1001;
    transition: right 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
    border-top-left-radius: 28px;
    border-bottom-left-radius: 28px;
    border-left: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    overflow: hidden;
}

.drawer-overlay.active .drawer-panel {
    right: 0;
}

.drawer-header {
    padding: 1.5rem 2rem 1.25rem 2rem;
    border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-surface, #121212);
}

.drawer-header-titles {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.drawer-header-badge {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: color-mix(in srgb, var(--primary-color, #6366f1) 15%, transparent);
    color: var(--primary-color, #6366f1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.drawer-header-titles h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-main, #ffffff);
    letter-spacing: -0.3px;
}

.drawer-header-titles span {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-muted, #9ca3af);
}

.drawer-body {
    padding: 1.75rem 2rem;
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.form-group label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted, #9ca3af);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.form-control {
    padding: 0.85rem 1.15rem;
    border-radius: 14px;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    background: var(--bg-color, #0a0a0a);
    color: var(--text-main, #ffffff);
    font-family: inherit;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color, #6366f1);
    background: var(--bg-color, #0a0a0a);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 20%, transparent);
}

.form-control::placeholder {
    color: var(--text-muted);
    opacity: 0.5;
    font-weight: 400;
}

select.form-control option {
    background: var(--bg-surface, #1e1e1e);
    color: var(--text-main, #ffffff);
}

.drawer-footer {
    padding: 1.25rem 2rem;
    border-top: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.75rem;
    background: var(--bg-surface, #121212);
}

.drawer-footer .btn-secondary {
    background: var(--bg-color, #1e1e1e);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    color: var(--text-muted, #9ca3af);
    padding: 0.65rem 1.4rem;
    border-radius: 9999px;
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.drawer-footer .btn-secondary:hover {
    background: var(--border-color);
    color: var(--text-main);
}

.drawer-footer .btn-primary {
    background: var(--secondary-color, #10b981);
    color: #ffffff;
    border: none;
    padding: 0.65rem 1.75rem;
    border-radius: 9999px;
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    box-shadow: 0 4px 15px color-mix(in srgb, var(--secondary-color, #10b981) 40%, transparent);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.drawer-footer .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--secondary-color, #10b981) 55%, transparent);
    filter: brightness(1.08);
}

/* Tag Manager mini UI */
.tag-manager-wrapper {
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    border-radius: 16px;
    padding: 1rem;
}
.tag-list-editable {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}
.tag-edit-pill {
    padding: 0.35rem 0.75rem;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    background: var(--bg-surface, #1e1e1e);
    color: var(--text-main, #ffffff);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    cursor: pointer;
    transition: all 0.15s ease;
}
.tag-edit-pill.selected {
    background: var(--primary-color, #6366f1);
    color: #ffffff;
    border-color: var(--primary-color, #6366f1);
    box-shadow: 0 2px 8px color-mix(in srgb, var(--primary-color, #6366f1) 40%, transparent);
}
.add-tag-form {
    display: flex;
    gap: 0.5rem;
}
.add-tag-form input[type="color"] {
    width: 36px; height: 36px; padding: 0; border: none; border-radius: 8px; overflow: hidden; cursor: pointer;
}

/* Client Search Autocomplete App Style */
#client_results {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    background: var(--bg-surface, #18181b) !important;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.12)) !important;
    border-radius: 16px !important;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5) !important;
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
    padding: 0.4rem;
    display: none;
}

.client-search-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.15s ease;
    border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.04));
}
.client-search-item:last-child {
    border-bottom: none;
}
.client-search-item:hover {
    background: color-mix(in srgb, var(--primary-color, #6366f1) 18%, transparent) !important;
}

.client-search-avatar {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: color-mix(in srgb, #f97316 20%, transparent);
    color: #f97316;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.client-search-details {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    overflow: hidden;
}

.client-search-name {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text-main, #ffffff);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.client-search-sub {
    font-size: 0.75rem;
    color: var(--text-muted, #9ca3af);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Tagify Assigned Users Modern Badge Style */
#p_users + .tagify .tagify__tag > div {
    background: color-mix(in srgb, var(--primary-color, #6366f1) 15%, transparent) !important;
    border: 1px solid color-mix(in srgb, var(--primary-color, #6366f1) 40%, transparent) !important;
}
#p_users + .tagify .tagify__tag-text {
    color: var(--primary-color, #6366f1) !important;
    font-weight: 700 !important;
}

/* 3 dots dropdown */
.dropdown-menu {
    position: absolute;
    right: 0; top: 100%;
    background: var(--bg-surface, #1e1e1e);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    min-width: 140px;
    display: none;
    flex-direction: column;
    z-index: 10;
    overflow: hidden;
    padding: 0.3rem;
}
.dropdown-menu.show { display: flex; }
.dropdown-item {
    padding: 0.55rem 0.85rem;
    font-size: 0.85rem;
    color: var(--text-main, #ffffff);
    cursor: pointer;
    border-radius: 8px;
}
.dropdown-item:hover { background: color-mix(in srgb, var(--primary-color, #6366f1) 15%, transparent); }
.dropdown-item.danger { color: #ef4444; }

@media (max-width: 768px) {
    .drawer-panel { max-width: 100%; }
    
    .brand-container {
        padding: 1rem 0.75rem;
    }
    
    .brand-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .brand-title h1 {
        font-size: 1.4rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .brand-actions .btn-primary {
        width: 100%;
        justify-content: center;
    }
    
    .brand-tabs-container {
        width: 100%;
        overflow-x: auto;
        padding-bottom: 5px;
        -webkit-overflow-scrolling: touch;
        /* Hide scrollbar for cleaner look */
        scrollbar-width: none; 
    }
    .brand-tabs-container::-webkit-scrollbar {
        display: none;
    }
    
    .brand-tabs {
        flex-wrap: nowrap;
        white-space: nowrap;
    }
    
    .brand-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="brand-container">
    <div class="brand-header">
        <div class="brand-title-group">
            <a href="index.php?module=workspace" class="btn-app-cancel" style="padding: 0.5rem 0.85rem; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;" title="Volver">
                <i class="ph-bold ph-arrow-left" style="font-size: 1.1rem;"></i>
            </a>
            <div class="brand-title" style="display: flex; flex-direction: column; gap: 0.15rem;">
                <span>Catálogo General</span>
                <h1>Desarrollo de Marca</h1>
            </div>
        </div>
        <div class="brand-actions">
            <button class="btn-primary" onclick="openDrawer()">
                <i class="ph-bold ph-plus"></i> Nuevo Proyecto
            </button>
        </div>
    </div>

    <!-- Tabs / Filter -->
    <div class="brand-tabs-container">
        <div class="brand-tabs">
            <button class="brand-tab active" data-filter="Active" onclick="filterProjects('Active')">
                <i class="ph-bold ph-lightning"></i> Activo
            </button>
            <button class="brand-tab" data-filter="Archived" onclick="filterProjects('Archived')">
                <i class="ph-bold ph-archive-box"></i> Archivado
            </button>
        </div>
    </div>

    <!-- Container for Projects -->
    <div class="brand-grid" id="projects-container">
        <!-- Projects loaded via AJAX -->
        <div style="text-align:center; padding: 2rem; color: var(--brand-text-muted); grid-column: 1/-1;">
            Cargando proyectos...
        </div>
    </div>
</div>

<!-- Drawer Off-Canvas (App Style) -->
<div class="drawer-overlay" id="brand-drawer">
    <div class="drawer-panel" onclick="event.stopPropagation()">
        <div class="drawer-header">
            <div class="drawer-header-titles">
                <div class="drawer-header-badge">
                    <i class="ph-bold ph-folder-notch-plus"></i>
                </div>
                <div>
                    <span>Formulario de Gestión</span>
                    <h2 id="drawer-title">Crear Proyecto</h2>
                </div>
            </div>
            <button class="app-close-circle" onclick="closeDrawer()">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
        <div class="drawer-body">
            <input type="hidden" id="p_id" value="0">
            
            <div class="form-group">
                <label><i class="ph-bold ph-text-t"></i> Título del Proyecto</label>
                <input type="text" id="p_title" class="form-control" placeholder="Ej. Rediseño de Identidad Visual Corporativa">
            </div>

            <div class="form-group" style="position:relative;">
                <label><i class="ph-bold ph-user"></i> Cliente (Búsqueda AJAX)</label>
                <div style="position:relative; display:flex; align-items:center;">
                    <input type="text" id="p_client" class="form-control" placeholder="Escribe el nombre, empresa o correo..." oninput="searchClients(this.value)" onfocus="searchClients(this.value)" autocomplete="off" style="padding-left: 2.75rem; width:100%;">
                    <i class="ph-bold ph-magnifying-glass" style="position:absolute; left:1rem; color:var(--text-muted); font-size:1.15rem; pointer-events:none;"></i>
                </div>
                <input type="hidden" id="p_client_id" value="">
                <div id="client_results"></div>
            </div>
            
            <div class="form-group">
                <label><i class="ph-bold ph-users-three"></i> Miembros Asignados</label>
                <input id="p_users" class="form-control" placeholder="Asignar colaboradores del equipo...">
            </div>

            <div class="form-group">
                <label><i class="ph-bold ph-notebook"></i> Brief / Formulario Vinculado (Opcional)</label>
                <select id="p_form_submission" class="form-control">
                    <option value="">-- Sin formulario vinculado --</option>
                </select>
                <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem;">Puedes asociar las respuestas enviadas por el cliente a este proyecto.</div>
            </div>

            <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.85rem;">
                <div>
                    <label><i class="ph-bold ph-faders"></i> Estado</label>
                    <select id="p_status" class="form-control">
                        <option value="Active">Activo</option>
                        <option value="Pending">Pendiente</option>
                        <option value="Completed">Completado</option>
                        <option value="Archived">Archivado</option>
                    </select>
                </div>
                <div>
                    <label><i class="ph-bold ph-calendar"></i> Fecha Inicio</label>
                    <input type="date" id="p_start" class="form-control" onchange="calcFormDuration()">
                </div>
                <div>
                    <label><i class="ph-bold ph-calendar-check"></i> Fecha Límite</label>
                    <input type="date" id="p_due" class="form-control" onchange="calcFormDuration()">
                </div>
            </div>
            <div id="form-duration-calc" style="font-size: 0.82rem; color: var(--secondary-color, #10b981); font-weight: 600; display: flex; align-items: center; gap: 0.4rem;"></div>

            <div class="form-group">
                <label><i class="ph-bold ph-image"></i> Imágenes de Portada / Moodboard</label>
                <input type="file" id="p_cover" class="form-control" multiple accept="image/*">
                <input type="hidden" id="p_existing_covers" value="">
                <div id="p_cover_preview" style="font-size:0.8rem; color:var(--text-muted); margin-top:0.25rem;"></div>
            </div>

            <div class="form-group" style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 16px; padding: 1rem;">
                <label style="display:flex; align-items:center; gap:0.5rem; color:#3b82f6; font-size:0.88rem; margin-bottom: 0.35rem;">
                    <i class="ph-fill ph-google-drive-logo" style="font-size:1.25rem;"></i> Carpeta de Google Drive
                </label>
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.6rem;">Si dejas el campo vacío se generará automáticamente.</div>
                <div style="display:flex; gap:0.5rem;">
                    <input type="text" id="p_drive" class="form-control" placeholder="https://drive.google.com/..." style="flex:1;">
                    <input type="hidden" id="p_drive_id" value="">
                    <button class="btn-secondary" onclick="openDrivePicker()" style="color:#3b82f6; border-color:color-mix(in srgb, #3b82f6 30%, transparent); background:color-mix(in srgb, #3b82f6 12%, transparent); font-weight:600; padding: 0.6rem 1.25rem; border-radius: 12px;">Elegir</button>
                </div>
            </div>

            <div class="form-group">
                <label><i class="ph-bold ph-tag"></i> Etiquetas del Proyecto</label>
                <div class="tag-manager-wrapper" style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 16px; padding: 1rem;">
                    <div class="tag-list-editable" id="tag-selector-list" style="margin-bottom: 0.85rem;">
                        <!-- Tags rendered here -->
                    </div>
                    <div class="add-tag-form" style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="color" id="new_tag_color" value="#10b981" style="width: 36px; height: 36px; border: none; border-radius: 10px; cursor: pointer; background: transparent; padding: 0;">
                        <input type="text" id="new_tag_name" class="form-control" placeholder="Nombre de etiqueta..." style="flex:1; padding: 0.55rem 0.85rem; font-size: 0.88rem;">
                        <button class="btn-primary" onclick="createNewTag()" style="padding: 0.55rem 1rem; border-radius: 10px;"><i class="ph-bold ph-plus"></i></button>
                    </div>
                </div>
            </div>

        </div>
        <div class="drawer-footer">
            <button class="btn-secondary" onclick="closeDrawer()">Cancelar</button>
            <button class="btn-primary" onclick="saveProject()"><i class="ph-bold ph-check"></i> Guardar Proyecto</button>
        </div>
    </div>
</div>

<script>
let allTags = [];
let currentProjectTags = [];
let allProjects = [];
let systemUsers = [];
let usersTagify;

document.addEventListener('DOMContentLoaded', () => {
    loadTags();
    loadProjects();
    loadSystemUsers();
    loadFormSubmissions();
    
    // Cerrar drawer al hacer clic fuera
    document.getElementById('brand-drawer').addEventListener('click', closeDrawer);
});

function loadFormSubmissions() {
    let formData = new FormData();
    formData.append('action', 'get_form_submissions');
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success && data.submissions) {
            let select = document.getElementById('p_form_submission');
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
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            systemUsers = data.users;
            let input = document.querySelector('#p_users');
            if (input) {
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
        // Ajustamos la diferencia
        let diff = due - start;
        
        if (diff < 0) {
            calcEl.innerHTML = '<span style="color:#ef4444;"><i class="ph ph-warning"></i> La fecha límite no puede ser anterior al inicio.</span>';
        } else {
            let days = Math.ceil(diff / (1000 * 60 * 60 * 24));
            calcEl.innerHTML = `<i class="ph ph-timer"></i> Duración del proyecto: ${days} día(s)`;
            calcEl.style.color = 'var(--primary-color, var(--brand-secondary))';
        }
    } else {
        calcEl.innerHTML = '';
    }
}

function openDrawer(id = 0) {
    document.getElementById('brand-drawer').classList.add('active');
    document.getElementById('form-duration-calc').innerHTML = '';
    document.getElementById('p_id').value = id;
    document.getElementById('p_cover_preview').innerHTML = '';
    document.getElementById('p_existing_covers').value = '';
    
    // Limpiar Tagify
    if (usersTagify) usersTagify.removeAllTags();

    if (id > 0) {
        document.getElementById('drawer-title').innerText = 'Editar Proyecto';
        let p = allProjects.find(x => x.id == id);
        if(p) {
            document.getElementById('p_title').value = p.title;
            document.getElementById('p_client').value = p.client_name;
            document.getElementById('p_client_id').value = '';
            document.getElementById('p_status').value = p.status;
            document.getElementById('p_form_submission').value = p.form_submission_id || '';
            document.getElementById('p_start').value = p.start_date || '';
            document.getElementById('p_due').value = p.due_date || '';
            document.getElementById('p_drive').value = p.drive_folder_url || '';
            document.getElementById('p_drive_id').value = p.drive_folder_id || '';
            
            if (p.assigned_users && usersTagify) {
                let mappedUsers = p.assigned_users.map(u => ({ value: u.name, id: u.id, avatar: u.avatar }));
                usersTagify.addTags(mappedUsers);
            }
            document.getElementById('p_cover').value = '';
            document.getElementById('p_existing_covers').value = p.cover_image || '';
            document.getElementById('p_cover_preview').innerText = p.cover_image ? 'Imágenes actuales: ' + p.cover_image : '';
            currentProjectTags = p.tags ? p.tags.map(t => t.id) : [];
            calcFormDuration();
        }
    } else {
        document.getElementById('drawer-title').innerText = 'Crear Proyecto';
        document.getElementById('p_title').value = '';
        document.getElementById('p_client').value = '';
        document.getElementById('p_status').value = 'Active';
        document.getElementById('p_form_submission').value = '';
        document.getElementById('p_start').value = '';
        document.getElementById('p_due').value = '';
        document.getElementById('p_cover').value = '';
        document.getElementById('p_drive').value = '';
        document.getElementById('p_drive_id').value = '';
        currentProjectTags = [];
    }
    renderTagSelector();
}

function closeDrawer() {
    document.getElementById('brand-drawer').classList.remove('active');
}

function loadProjects() {
    let formData = new FormData();
    formData.append('action', 'get_projects');
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            allProjects = data.projects;
            renderProjects();
        }
    });
}

let currentFilter = 'Active';

function filterProjects(status) {
    currentFilter = status;
    document.querySelectorAll('.brand-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.brand-tab[data-filter="${status}"]`).classList.add('active');
    renderProjects();
}

function renderProjects() {
    const container = document.getElementById('projects-container');
    container.innerHTML = '';
    
    let filtered = allProjects.filter(p => {
        if (currentFilter === 'Active') return p.status === 'Active' || p.status === 'Pending';
        if (currentFilter === 'Archived') return p.status === 'Archived' || p.status === 'Completed';
        return true;
    });

    if(filtered.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding: 3rem 1.5rem; color: var(--brand-text-muted); grid-column: 1/-1; font-weight: 500; font-size: 0.95rem;">No hay proyectos en esta categoría.</div>';
        return;
    }

    filtered.forEach(p => {
        let dateStr = p.created_at ? new Date(p.created_at).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' }) : 'N/A';
        let avatarLetter = p.client_name ? p.client_name.charAt(0).toUpperCase() : 'C';
        let clientName = p.client_name || 'Cliente sin asignar';
        
        let timerHtml = p.due_date ? `<div class="modern-timer" data-start="${p.start_date || ''}" data-due="${p.due_date}"><i class="ph-bold ph-hourglass-medium"></i> <span class="timer-text">Calculando...</span></div>` : '';

        let tagsHtml = p.tags && p.tags.length > 0 ? p.tags.map(t => `<span class="tag-pill" style="background: color-mix(in srgb, ${t.color || '#6366f1'} 15%, transparent); color: ${t.color || '#6366f1'}; border: 1px solid color-mix(in srgb, ${t.color || '#6366f1'} 30%, transparent);">${t.name}</span>`).join('') : '';
        let startDateFormatted = p.start_date ? new Date(p.start_date + 'T12:00:00').toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) : 'Sin definir';
        let dueDateFormatted = p.due_date ? new Date(p.due_date + 'T12:00:00').toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) : 'Sin definir';

        let isOverdue = false;
        if (p.due_date && p.status !== 'Completed') {
            isOverdue = new Date(p.due_date + 'T23:59:59') < new Date();
        }

        const statusMap = { 'Active': 'Activo', 'Pending': 'Pendiente', 'Completed': 'Completado', 'Archived': 'Archivado' };
        let displayStatus = statusMap[p.status] || p.status || 'Activo';
        let statusKey = (p.status || 'Active').toLowerCase();
        
        // Perfectly aligned assigned users avatar stack
        let avatarsHtml = '';
        if (p.assigned_users && p.assigned_users.length > 0) {
            avatarsHtml = `<div class="assigned-users-stack">`;
            p.assigned_users.slice(0, 4).forEach((u, i) => {
                let initial = (u.name || 'U').charAt(0).toUpperCase();
                let zIndex = 10 - i;
                let safeName = (u.name || 'Usuario').replace(/"/g, '&quot;');
                if (u.avatar && u.avatar !== 'default.png') {
                    avatarsHtml += `<img src="${u.avatar}" class="avatar-sm" style="z-index:${zIndex};" title="${safeName}" alt="${safeName}" onerror="this.outerHTML='<div class=\\'avatar-sm avatar-placeholder\\' style=\\'z-index:${zIndex};\\' title=\\'${safeName}\\'>${initial}</div>'">`;
                } else {
                    avatarsHtml += `<div class="avatar-sm avatar-placeholder" style="z-index:${zIndex};" title="${safeName}">${initial}</div>`;
                }
            });
            if (p.assigned_users.length > 4) {
                avatarsHtml += `<div class="avatar-sm avatar-more" style="z-index:1;" title="${p.assigned_users.length - 4} colaboradores más">+${p.assigned_users.length - 4}</div>`;
            }
            avatarsHtml += `</div>`;
        }

        // Progress Scale Calculation and Component
        let progressVal = Math.min(100, Math.max(0, parseInt(p.progress || 0)));
        let progressBadgeClass = progressVal >= 100 ? 'high' : (progressVal > 20 ? 'mid' : 'low');
        let totalTasks = parseInt(p.total_tasks || 0);
        let completedTasks = parseInt(p.completed_tasks || 0);
        let totalSubtasks = parseInt(p.total_subtasks || 0);
        let completedSubtasks = parseInt(p.completed_subtasks || 0);

        let card = document.createElement('div');
        card.className = 'project-card';
        card.innerHTML = `
            <!-- Top Action & Status Bar -->
            <div class="app-card-top-bar">
                <div class="app-card-badges-left">
                    <span class="app-status-badge ${statusKey}">
                        <span class="status-dot"></span>
                        ${displayStatus}
                    </span>
                    ${timerHtml}
                </div>
                <div style="position:relative;">
                    <button class="app-btn-more" onclick="toggleMenu(event, ${p.id})" title="Opciones de Proyecto">
                        <i class="ph-bold ph-dots-three"></i>
                    </button>
                    <div class="dropdown-menu" id="menu-${p.id}">
                        <div class="dropdown-item" onclick="openDrawer(${p.id})"><i class="ph-bold ph-pencil-simple"></i> Editar</div>
                        <div class="dropdown-item" onclick="archiveProject(${p.id}, '${p.status === 'Archived' ? 'Active' : 'Archived'}')"><i class="ph-bold ${p.status === 'Archived' ? 'ph-arrow-u-up-left' : 'ph-archive-box'}"></i> ${p.status === 'Archived' ? 'Restaurar' : 'Archivar'}</div>
                        <div class="dropdown-item danger" onclick="deleteProject(${p.id})"><i class="ph-bold ph-trash"></i> Eliminar</div>
                    </div>
                </div>
            </div>

            <!-- Project Hero & Client -->
            <div class="app-card-hero" onclick="window.location.href='index.php?module=desarrollo_marca&action=view&id=${p.id}'" title="Abrir tablero de proyecto">
                <h3 class="app-card-title">${p.title}</h3>
                <div class="app-client-row">
                    <div class="app-client-avatar">${avatarLetter}</div>
                    <div class="app-client-info">
                        <span class="app-client-name">${clientName}</span>
                        <span class="app-client-date"><i class="ph-bold ph-calendar-plus"></i> ${dateStr}</span>
                    </div>
                </div>
            </div>

            <!-- Meta: Tags & Assigned Collaborators -->
            <div class="app-card-meta-row">
                <div class="app-card-tags">
                    ${tagsHtml ? tagsHtml : '<span style="font-size:0.72rem; color:var(--text-muted); opacity:0.6;"><i class="ph-bold ph-tag"></i> Sin etiquetas</span>'}
                </div>
                <div>
                    ${avatarsHtml ? avatarsHtml : '<span class="app-unassigned-pill"><i class="ph-bold ph-user-plus"></i> Sin asignar</span>'}
                </div>
            </div>

            <!-- Escala de Progreso App Widget -->
            <div class="card-progress-section" onclick="window.location.href='index.php?module=desarrollo_marca&action=view&id=${p.id}'" style="cursor: pointer;" title="Ver fases y tareas">
                <div class="card-progress-header">
                    <span class="progress-header-title">
                        <i class="ph-fill ph-chart-donut"></i> Escala de Progreso
                    </span>
                    <span class="progress-percentage-badge ${progressBadgeClass}">
                        ${progressVal}%
                    </span>
                </div>
                <div class="card-progress-track">
                    <div class="card-progress-fill" style="width: ${progressVal}%;"></div>
                </div>
                <div class="card-progress-stats">
                    <div class="stat-chip tasks" title="${completedTasks} de ${totalTasks} tareas completadas">
                        <i class="ph-bold ph-check-circle"></i>
                        <span><b>${completedTasks}</b>/${totalTasks} tareas</span>
                    </div>
                    <div class="stat-chip subtasks" title="${completedSubtasks} de ${totalSubtasks} subtareas completadas">
                        <i class="ph-bold ph-list-checks"></i>
                        <span><b>${completedSubtasks}</b>/${totalSubtasks} subtareas</span>
                    </div>
                </div>
            </div>

            <!-- Dates Timeline Grid -->
            <div class="app-card-dates">
                <div class="app-date-chip start">
                    <span class="label"><i class="ph-bold ph-calendar-blank"></i> Inicio</span>
                    <span class="value">${startDateFormatted}</span>
                </div>
                <div class="app-date-chip due ${isOverdue ? 'overdue' : ''}">
                    <span class="label"><i class="ph-bold ph-clock"></i> Límite</span>
                    <span class="value" style="${isOverdue ? 'color:#ef4444;' : ''}">${dueDateFormatted}</span>
                </div>
            </div>

            <!-- Google Drive Project Folder CTA -->
            ${p.drive_folder_url ? `
                <a href="${p.drive_folder_url}" target="_blank" class="app-drive-cta" onclick="event.stopPropagation()">
                    <div class="drive-cta-left">
                        <div class="drive-cta-icon"><i class="ph-fill ph-google-drive-logo"></i></div>
                        <div class="drive-cta-text">
                            <span>Carpeta de Proyecto</span>
                            <small>Abrir en Google Drive</small>
                        </div>
                    </div>
                    <i class="ph-bold ph-arrow-square-out drive-cta-arrow"></i>
                </a>
            ` : ''}
        `;
        container.appendChild(card);
    });
    updateTimers();
}

function updateTimers() {
    let now = new Date();
    document.querySelectorAll('.modern-timer').forEach(el => {
        let dueStr = el.getAttribute('data-due');
        let startStr = el.getAttribute('data-start');
        if(!dueStr) return;
        let due = new Date(dueStr + 'T23:59:59');
        let start = startStr ? new Date(startStr + 'T00:00:00') : new Date();
        let textEl = el.querySelector('.timer-text');
        let diff = 0;
        if (now < start) { diff = due - start; el.style.background = 'rgba(59, 130, 246, 0.85)'; el.classList.remove('expired'); } 
        else if (now >= start && now <= due) { diff = due - now; el.style.background = 'rgba(15, 23, 42, 0.75)'; el.classList.remove('expired'); } 
        else { el.classList.add('expired'); el.style.background = 'rgba(239, 68, 68, 0.85)'; textEl.innerHTML = 'Tiempo agotado'; return; }
        let days = Math.floor(diff / (1000 * 60 * 60 * 24));
        let hours = String(Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
        let mins = String(Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
        let secs = String(Math.floor((diff % (1000 * 60)) / 1000)).padStart(2, '0');
        textEl.innerHTML = days > 0 ? `${days}d ${hours}:${mins}:${secs}` : `${hours}:${mins}:${secs}`;
    });
}
setInterval(updateTimers, 1000);

function toggleMenu(e, id) {
    e.stopPropagation();
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
    document.getElementById('menu-'+id).classList.toggle('show');
}
document.addEventListener('click', () => document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show')));

function loadTags() {
    let formData = new FormData();
    formData.append('action', 'get_tags');
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => { if(data.success) { allTags = data.tags; renderTagSelector(); } });
}

function renderTagSelector() {
    const list = document.getElementById('tag-selector-list');
    list.innerHTML = '';
    allTags.forEach(t => {
        let isSelected = currentProjectTags.includes(t.id);
        let pill = document.createElement('div');
        pill.className = `tag-edit-pill ${isSelected ? 'selected' : ''}`;
        pill.style.borderColor = t.color;
        pill.style.background = isSelected ? t.color : 'transparent';
        pill.style.color = isSelected ? '#fff' : t.color;
        pill.innerHTML = `<span>${t.name}</span> <i class="ph ph-x" style="margin-left:4px; font-size:10px;" onclick="deleteTag(event, ${t.id})"></i>`;
        pill.onclick = () => { if(isSelected) currentProjectTags = currentProjectTags.filter(id => id !== t.id); else currentProjectTags.push(t.id); renderTagSelector(); };
        list.appendChild(pill);
    });
}

function createNewTag() {
    let name = document.getElementById('new_tag_name').value.trim();
    let color = document.getElementById('new_tag_color').value;
    if(!name) return;
    let formData = new FormData();
    formData.append('action', 'save_tag');
    formData.append('name', name);
    formData.append('color', color);
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => { if(data.success) { document.getElementById('new_tag_name').value = ''; loadTags(); } });
}

function deleteTag(e, id) {
    e.stopPropagation();
    if(!confirm("¿Eliminar etiqueta para todos los proyectos?")) return;
    let formData = new FormData();
    formData.append('action', 'delete_tag');
    formData.append('id', id);
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => { if(data.success) loadTags(); });
}

function saveProject() {
    let title = document.getElementById('p_title').value.trim();
    if(!title) { alert('Título es requerido'); return; }

    let formData = new FormData();
    formData.append('action', 'save_project');
    formData.append('id', document.getElementById('p_id').value);
    formData.append('title', title);
    formData.append('client_name', document.getElementById('p_client').value);
    formData.append('status', document.getElementById('p_status').value);
    formData.append('start_date', document.getElementById('p_start').value);
    formData.append('due_date', document.getElementById('p_due').value);
    formData.append('form_submission_id', document.getElementById('p_form_submission').value);
    formData.append('drive_folder_url', document.getElementById('p_drive').value);
    formData.append('drive_folder_id', document.getElementById('p_drive_id').value);
    formData.append('tags', JSON.stringify(currentProjectTags));
    formData.append('existing_covers', document.getElementById('p_existing_covers').value);

    let usersData = usersTagify && usersTagify.value ? usersTagify.value.map(t => t.id) : [];
    formData.append('assigned_users', JSON.stringify(usersData));

    let fileInput = document.getElementById('p_cover');
    if (fileInput.files.length > 0) {
        for (let i = 0; i < fileInput.files.length; i++) {
            formData.append('cover_files[]', fileInput.files[i]);
        }
    }

    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            closeDrawer();
            loadProjects();
        } else {
            alert("Error al guardar: " + data.message);
        }
    });
}

function deleteProject(id) {
    let modalHtml = `
        <div class="app-modal-dialog" style="text-align: left; background: var(--bg-surface, #121212); color: var(--text-main, #ffffff); border-radius: 28px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.45);">
            <div class="app-modal-header" style="padding: 1.5rem 2rem 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));">
                <div class="app-modal-title-group" style="display: flex; align-items: center; gap: 0.85rem;">
                    <div class="app-modal-icon-badge" style="width: 44px; height: 44px; border-radius: 14px; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                        <i class="ph-bold ph-trash"></i>
                    </div>
                    <div class="app-modal-titles" style="display: flex; flex-direction: column; gap: 0.15rem;">
                        <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted, #9ca3af);">Zona de Peligro</span>
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--text-main, #ffffff);">¿Eliminar Proyecto?</h3>
                    </div>
                </div>
                <button class="app-close-circle" onclick="Swal.close()" style="width: 36px; height: 36px; border-radius: 50%; background: var(--bg-color, #1e1e1e); border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08)); color: var(--text-muted, #9ca3af); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.1rem;"><i class="ph-bold ph-x"></i></button>
            </div>
            <div class="app-modal-body" style="padding: 1.75rem 2rem;">
                <p style="margin:0; font-size:0.95rem; color:var(--text-muted); line-height:1.6;">
                    Se eliminará permanentemente este proyecto y todo su registro de tareas asociadas. ¿Estás seguro de que deseas continuar?
                </p>
            </div>
            <div class="app-modal-footer" style="padding: 1.25rem 2rem; display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; border-top: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));">
                <button onclick="Swal.close()" style="background: var(--bg-color, #1e1e1e); color: var(--text-muted, #9ca3af); border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1)); padding: 0.65rem 1.4rem; border-radius: 9999px; font-weight: 600; font-size: 0.88rem; cursor: pointer;">Cancelar</button>
                <button id="swal-confirm-del-project" style="background: #ef4444; color: #fff; border: none; padding: 0.65rem 1.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.88rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);"><i class="ph-bold ph-trash"></i> Sí, Eliminar</button>
            </div>
        </div>
    `;

    Swal.fire({
        html: modalHtml,
        width: '520px',
        showConfirmButton: false,
        showCancelButton: false,
        customClass: { popup: 'swal2-zero-pad', actions: 'app-modal-actions' },
        didOpen: () => {
            document.getElementById('swal-confirm-del-project').addEventListener('click', () => {
                executeDelete(id);
                Swal.close();
            });
        }
    });
}

function executeDelete(id) {
    let formData = new FormData();
    formData.append('action', 'delete_project');
    formData.append('id', id);
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            loadProjects();
        }
    });
}

function archiveProject(id, newStatus) {
    let isArchiving = newStatus === 'Archived';
    let modalHtml = `
        <div class="app-modal-dialog" style="text-align: left; background: var(--bg-surface, #121212); color: var(--text-main, #ffffff); border-radius: 28px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.45);">
            <div class="app-modal-header" style="padding: 1.5rem 2rem 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));">
                <div class="app-modal-title-group" style="display: flex; align-items: center; gap: 0.85rem;">
                    <div class="app-modal-icon-badge" style="width: 44px; height: 44px; border-radius: 14px; background: color-mix(in srgb, var(--primary-color, #4f46e5) 15%, transparent); color: var(--primary-color, #6366f1); display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                        <i class="ph-bold ${isArchiving ? 'ph-archive' : 'ph-arrow-counter-clockwise'}"></i>
                    </div>
                    <div class="app-modal-titles" style="display: flex; flex-direction: column; gap: 0.15rem;">
                        <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted, #9ca3af);">Gestión de Estado</span>
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--text-main, #ffffff);">${isArchiving ? '¿Archivar Proyecto?' : '¿Restaurar Proyecto?'}</h3>
                    </div>
                </div>
                <button class="app-close-circle" onclick="Swal.close()" style="width: 36px; height: 36px; border-radius: 50%; background: var(--bg-color, #1e1e1e); border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08)); color: var(--text-muted, #9ca3af); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.1rem;"><i class="ph-bold ph-x"></i></button>
            </div>
            <div class="app-modal-body" style="padding: 1.75rem 2rem;">
                <p style="margin:0; font-size:0.95rem; color:var(--text-muted); line-height:1.6;">
                    ${isArchiving ? 'El proyecto se moverá a la pestaña de Archivados y dejará de aparecer en la vista activa.' : 'El proyecto volverá al tablero principal como Activo.'}
                </p>
            </div>
            <div class="app-modal-footer" style="padding: 1.25rem 2rem; display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; border-top: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));">
                <button onclick="Swal.close()" style="background: var(--bg-color, #1e1e1e); color: var(--text-muted, #9ca3af); border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1)); padding: 0.65rem 1.4rem; border-radius: 9999px; font-weight: 600; font-size: 0.88rem; cursor: pointer;">Cancelar</button>
                <button id="swal-confirm-archive-project" style="background: var(--primary-color, #4f46e5); color: #fff; border: none; padding: 0.65rem 1.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.88rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; box-shadow: 0 4px 15px color-mix(in srgb, var(--primary-color, #4f46e5) 40%, transparent);"><i class="ph-bold ph-check"></i> Confirmar</button>
            </div>
        </div>
    `;

    Swal.fire({
        html: modalHtml,
        width: '520px',
        showConfirmButton: false,
        showCancelButton: false,
        customClass: { popup: 'swal2-zero-pad', actions: 'app-modal-actions' },
        didOpen: () => {
            document.getElementById('swal-confirm-archive-project').addEventListener('click', () => {
                executeArchive(id, newStatus);
                Swal.close();
            });
        }
    });
}

function executeArchive(id, newStatus) {
    let formData = new FormData();
    formData.append('action', 'change_status');
    formData.append('id', id);
    formData.append('status', newStatus);
    fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            loadProjects();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
function openDrivePicker() {
    if (typeof cdOpenPicker !== 'undefined') {
        cdOpenPicker(null, function(selectedFolder) {
            if (selectedFolder && selectedFolder.id) {
                document.getElementById('p_drive_id').value = selectedFolder.id;
                document.getElementById('p_drive').value = selectedFolder.url || `https://drive.google.com/drive/folders/${selectedFolder.id}`;
            }
        });
    } else if (typeof openGlobalDrivePicker !== 'undefined') {
        openGlobalDrivePicker(function(selectedFolder) {
            if (selectedFolder && selectedFolder.id) {
                document.getElementById('p_drive_id').value = selectedFolder.id;
                document.getElementById('p_drive').value = selectedFolder.url || `https://drive.google.com/drive/folders/${selectedFolder.id}`;
            }
        });
    } else {
        alert("Por favor selecciona una carpeta en el Drive Explorer y copia la URL.");
    }
}

let searchClientTimer;
function searchClients(query) {
    clearTimeout(searchClientTimer);
    const resultsDiv = document.getElementById('client_results');
    if(!query || query.trim().length < 1) {
        resultsDiv.style.display = 'none';
        return;
    }
    searchClientTimer = setTimeout(() => {
        let formData = new FormData();
        formData.append('action', 'search_clients');
        formData.append('query', query.trim());
        fetch('ajax/ajax_desarrollo_marca.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(data.success && data.clients.length > 0) {
                resultsDiv.innerHTML = '';
                data.clients.forEach(c => {
                    let div = document.createElement('div');
                    div.className = 'client-search-item';
                    let initial = (c.name || 'C').charAt(0).toUpperCase();
                    let subText = c.business_name || c.email || c.phone || 'Cliente Registrado';
                    
                    div.innerHTML = `
                        <div class="client-search-avatar">
                            ${c.avatar && c.avatar !== 'default.png' ? `<img src="${c.avatar}" style="width:100%; height:100%; border-radius:10px; object-fit:cover;">` : initial}
                        </div>
                        <div class="client-search-details">
                            <div class="client-search-name">${c.name}</div>
                            <div class="client-search-sub">${subText}</div>
                        </div>
                    `;
                    div.onclick = () => {
                        document.getElementById('p_client').value = c.name;
                        document.getElementById('p_client_id').value = c.id;
                        resultsDiv.style.display = 'none';
                    };
                    resultsDiv.appendChild(div);
                });
                resultsDiv.style.display = 'block';
            } else {
                resultsDiv.innerHTML = '<div style="padding:0.75rem; text-align:center; color:var(--text-muted); font-size:0.85rem;"><i class="ph-bold ph-warning-circle"></i> No se encontraron clientes</div>';
                resultsDiv.style.display = 'block';
            }
        });
    }, 250);
}

document.addEventListener('click', function(e) {
    if(!e.target.closest('#p_client') && !e.target.closest('#client_results')) {
        let cr = document.getElementById('client_results');
        if(cr) cr.style.display = 'none';
    }
});
</script>

<?php 
// Include Google Drive Picker Modal
if (file_exists('includes/custom_drive_picker.php')) {
    require_once 'includes/custom_drive_picker.php';
} elseif (file_exists('includes/drive_modal.php')) {
    require_once 'includes/drive_modal.php';
}
?>

<?php require_once 'includes/footer.php'; ?>
