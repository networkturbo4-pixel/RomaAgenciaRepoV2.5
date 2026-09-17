<?php
// modules/audiovisual/index.php
require_once 'includes/header.php';
?>

<style>
/* 
    Modern Audiovisual Production UI
    Matching Design System & Modern UI Setup
*/
:root {
    --av-primary: #f59e0b;
    --av-secondary: #ef4444;
    --av-bg: var(--bg-color, #09090b);
    --av-card-bg: var(--bg-surface, #141417);
    --av-text-main: var(--color-title, #f8fafc);
    --av-text-muted: var(--color-text, #94a3b8);
    --av-border: var(--border-color, rgba(255, 255, 255, 0.08));
}

[data-theme="dark"] {
    --av-primary: #f59e0b;
    --av-secondary: #ef4444;
    --av-bg: var(--bg-color, #09090b);
    --av-card-bg: var(--bg-surface, #141417);
    --av-text-main: var(--color-title, #f8fafc);
    --av-text-muted: var(--color-text, #94a3b8);
    --av-border: var(--border-color, rgba(255, 255, 255, 0.08));
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

.btn-app-cancel {
    background: var(--bg-color);
    color: var(--text-main);
    border: 1px solid var(--border-color);
    padding: 0.55rem 0.95rem;
    border-radius: 12px;
    cursor: pointer;
    font-size: 0.88rem;
    font-weight: 600;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}
.btn-app-cancel:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.brand-title h1 {
    font-size: 1.45rem;
    font-weight: 700;
    color: var(--av-text-main);
    margin: 0;
    letter-spacing: -0.3px;
}

.brand-title span {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--av-text-muted);
}

.brand-actions .btn-primary {
    background: var(--color-title, #0f172a);
    color: var(--bg-surface, #ffffff);
    border: 1px solid var(--border-color);
    padding: 0.65rem 1.5rem;
    border-radius: 9999px;
    font-weight: 700;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

[data-theme="dark"] .brand-actions .btn-primary {
    background: #ffffff;
    color: #0f172a;
    border: none;
}

.brand-actions .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
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
    color: var(--av-text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    transition: all 0.2s ease;
}
.brand-tab:hover {
    color: var(--av-text-main);
}
.brand-tab.active {
    background: var(--bg-color);
    color: var(--av-text-main);
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.brand-tab.active i {
    color: #f59e0b;
}

/* Ultra Modern App Style Project Card */
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
    border-color: color-mix(in srgb, #f59e0b 40%, var(--border-color, rgba(255, 255, 255, 0.08)));
    box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.35), 0 0 20px color-mix(in srgb, #f59e0b 10%, transparent);
}

[data-theme="light"] .project-card:hover {
    box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.12), 0 0 15px color-mix(in srgb, #f59e0b 8%, transparent);
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
    color: #f59e0b;
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
    background: #f59e0b;
    color: #ffffff;
}
.assigned-users-stack .avatar-more {
    background: var(--border-color, #27272a);
    color: var(--av-text-muted, #94a3b8);
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
    border-color: color-mix(in srgb, #f59e0b 30%, var(--border-color));
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
    color: #f59e0b;
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
    background: color-mix(in srgb, #f59e0b 18%, transparent);
    color: #f59e0b;
    border-color: color-mix(in srgb, #f59e0b 35%, transparent);
}
.progress-percentage-badge.high {
    background: color-mix(in srgb, #10b981 18%, transparent);
    color: #10b981;
    border-color: color-mix(in srgb, #10b981 35%, transparent);
}

.card-progress-track {
    width: 100%;
    height: 7px;
    border-radius: 9999px;
    background: color-mix(in srgb, var(--border-color) 70%, transparent);
    overflow: hidden;
    position: relative;
}
.card-progress-fill {
    height: 100%;
    border-radius: 9999px;
    background: linear-gradient(90deg, #f59e0b, #ef4444);
    transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.card-progress-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.75rem;
    color: var(--text-muted, #94a3b8);
    font-weight: 600;
}
.stat-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.stat-chip i {
    font-size: 0.85rem;
}

/* Card Dates Box */
.app-card-dates-box {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
    border-radius: 16px;
    background: color-mix(in srgb, var(--bg-color, #09090b) 60%, transparent);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
}
[data-theme="light"] .app-card-dates-box {
    background: #f1f5f9;
    border-color: #e2e8f0;
}
.app-date-col {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.app-date-col.border-right {
    border-right: 1px solid var(--border-color);
    padding-right: 0.5rem;
}
.app-date-label {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-muted, #94a3b8);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.app-date-value {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-main, #ffffff);
    font-variant-numeric: tabular-nums;
}
.app-date-value.empty {
    color: var(--text-muted);
    font-weight: 500;
    font-size: 0.78rem;
}

/* Empty State */
.brand-empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-surface);
    border-radius: 24px;
    border: 1px dashed var(--border-color);
}
.brand-empty-icon {
    font-size: 3.5rem;
    color: var(--av-text-muted);
    margin-bottom: 1rem;
    display: inline-block;
}

/* Slide Drawer Styles */
.brand-drawer-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}
.brand-drawer-overlay.active {
    opacity: 1;
    visibility: visible;
}
.brand-drawer {
    position: fixed;
    top: 0;
    right: -600px;
    width: 100%;
    max-width: 580px;
    height: 100vh;
    background: var(--bg-surface);
    border-left: 1px solid var(--border-color);
    box-shadow: -10px 0 40px rgba(0,0,0,0.3);
    z-index: 10000;
    display: flex;
    flex-direction: column;
    transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
.brand-drawer-overlay.active .brand-drawer,
.brand-drawer.active {
    right: 0;
}
.drawer-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.drawer-header h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--av-text-main);
}
.drawer-body {
    padding: 2rem;
    overflow-y: auto;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.drawer-footer {
    padding: 1.25rem 2rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    background: var(--bg-surface);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.form-group label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--av-text-main);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.form-control {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    padding: 0.75rem 1rem;
    border-radius: 12px;
    color: var(--av-text-main);
    font-size: 0.92rem;
    outline: none;
    transition: border-color 0.2s;
    width: 100%;
    box-sizing: border-box;
}
.form-control:focus {
    border-color: #f59e0b;
}

.client-search-wrapper {
    position: relative;
}
.client-results-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    margin-top: 0.4rem;
    max-height: 200px;
    overflow-y: auto;
    z-index: 10;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    display: none;
}
.client-result-item {
    padding: 0.65rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: background 0.2s;
    border-bottom: 1px solid var(--border-color);
}
.client-result-item:last-child {
    border-bottom: none;
}
.client-result-item:hover {
    background: rgba(245, 158, 11, 0.1);
}

.tag-list-editable {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.tag-badge-select {
    padding: 0.35rem 0.8rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.tag-badge-select.selected {
    outline: 2px solid #ffffff;
    outline-offset: 1px;
}

/* SweetAlert Modern Popup */
.swal2-modern-popup {
    border-radius: 20px !important;
    border: 1px solid var(--border-color) !important;
    background: var(--bg-surface) !important;
    color: var(--text-main) !important;
}
</style>

<div class="brand-container">
    <!-- Header App Style -->
    <div class="brand-header">
        <div class="brand-title-group">
            <a href="index.php?module=workspace&action=index" class="btn-app-cancel" title="Volver al Workspace">
                <i class="ph-bold ph-arrow-left" style="font-size: 1.1rem;"></i>
            </a>
            <div class="brand-title">
                <span>Catálogo General</span>
                <h1>Audiovisual</h1>
            </div>
        </div>
        <div class="brand-actions">
            <button class="btn-primary" onclick="openCreateDrawer()">
                <i class="ph-bold ph-plus"></i> Nuevo Proyecto
            </button>
        </div>
    </div>

    <!-- Tabs Filtering (Active / Archived) -->
    <div class="brand-tabs-container">
        <div class="brand-tabs">
            <button class="brand-tab active" data-tab="Active" onclick="switchTab('Active')">
                <i class="ph-bold ph-lightning"></i> Activo
            </button>
            <button class="brand-tab" data-tab="Archived" onclick="switchTab('Archived')">
                <i class="ph-bold ph-archive"></i> Archivado
            </button>
        </div>
    </div>

    <!-- Projects Grid Container -->
    <div class="brand-grid" id="projects-grid">
        <!-- Rendered dynamically -->
        <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--av-text-muted);">
            <i class="ph-bold ph-spinner-gap" style="font-size: 2rem; animation: spin 1s linear infinite;"></i>
            <p style="margin-top: 0.5rem;">Cargando proyectos audiovisuales...</p>
        </div>
    </div>
</div>

<!-- Project Create/Edit Drawer Modal -->
<div class="brand-drawer-overlay" id="brand-drawer">
    <div class="brand-drawer" onclick="event.stopPropagation()">
        <div class="drawer-header">
            <h2 id="drawer-title">Nuevo Proyecto Audiovisual</h2>
            <button class="btn-app-cancel" onclick="closeDrawer()"><i class="ph-bold ph-x"></i></button>
        </div>
        <div class="drawer-body">
            <input type="hidden" id="p_id" value="0">
            <input type="hidden" id="existing_covers" value="">

            <div class="form-group">
                <label><i class="ph-bold ph-text-t"></i> Título del Proyecto *</label>
                <input type="text" id="p_title" class="form-control" placeholder="Ej: Video Comercial de Temporada, Spot Corporativo...">
            </div>

            <div class="form-group">
                <label><i class="ph-bold ph-user"></i> Cliente Asociado</label>
                <div class="client-search-wrapper">
                    <input type="text" id="p_client_search" class="form-control" placeholder="Buscar cliente por nombre o empresa..." oninput="searchClients(this.value)">
                    <input type="hidden" id="p_client_name" value="">
                    <div class="client-results-dropdown" id="client-results"></div>
                </div>
            </div>

            <div class="form-group">
                <label><i class="ph-bold ph-notebook"></i> Vincular Brief / Formulario (Opcional)</label>
                <select id="p_form_submission" class="form-control">
                    <option value="">-- Sin formulario vinculado --</option>
                </select>
                <small style="color: var(--av-text-muted); font-size: 0.75rem;">Vincula respuestas de un brief de video para visualizarlas dentro del proyecto.</small>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label><i class="ph-bold ph-calendar-blank"></i> Fecha de Inicio</label>
                    <input type="date" id="p_start" class="form-control" onchange="calcFormDuration()">
                </div>
                <div class="form-group">
                    <label><i class="ph-bold ph-calendar-check"></i> Fecha Límite</label>
                    <input type="date" id="p_due" class="form-control" onchange="calcFormDuration()">
                </div>
            </div>
            <div id="form-duration-calc" style="font-size: 0.78rem; font-weight: 600; color: var(--av-text-muted); margin-top: -0.5rem;"></div>

            <div class="form-group">
                <label><i class="ph-bold ph-flag"></i> Estado</label>
                <select id="p_status" class="form-control">
                    <option value="Active">Activo</option>
                    <option value="Pending">Pendiente</option>
                    <option value="Completed">Completado</option>
                    <option value="Archived">Archivado</option>
                </select>
            </div>

            <div class="form-group">
                <label><i class="ph-bold ph-google-drive-logo" style="color: #3b82f6;"></i> Enlace Carpeta Google Drive</label>
                <input type="url" id="p_drive_url" class="form-control" placeholder="https://drive.google.com/drive/folders/..." oninput="extractDriveId(this.value)">
                <input type="hidden" id="p_drive_id" value="">
            </div>

            <div class="form-group">
                <label><i class="ph-bold ph-users-three"></i> Miembros del Equipo Asignados</label>
                <input type="text" id="p_users" placeholder="Escribe para buscar colaboradores...">
            </div>

            <div class="form-group">
                <label><i class="ph-bold ph-image"></i> Portada / Referencias del Proyecto</label>
                <input type="file" id="p_cover_files" class="form-control" accept="image/*" multiple>
                <div id="cover-preview-container" style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem;"></div>
            </div>

            <div class="form-group">
                <label><i class="ph-bold ph-article"></i> Descripción / Notas del Proyecto</label>
                <textarea id="p_description" class="form-control" rows="3" placeholder="Detalles de la producción, objetivos del video o instrucciones..."></textarea>
            </div>

            <div class="form-group">
                <label><i class="ph-bold ph-tag"></i> Etiquetas del Proyecto</label>
                <div class="tag-manager-wrapper" style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 16px; padding: 1rem;">
                    <div class="tag-list-editable" id="tag-selector-list" style="margin-bottom: 0.85rem;">
                        <!-- Tags rendered here -->
                    </div>
                    <div class="add-tag-form" style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="color" id="new_tag_color" value="#f59e0b" style="width: 36px; height: 36px; border: none; border-radius: 10px; cursor: pointer; background: transparent; padding: 0;">
                        <input type="text" id="new_tag_name" class="form-control" placeholder="Nueva etiqueta..." style="flex:1; padding: 0.55rem 0.85rem; font-size: 0.88rem;">
                        <button class="btn-primary" onclick="createNewTag()" style="padding: 0.55rem 1rem; border-radius: 10px;"><i class="ph-bold ph-plus"></i></button>
                    </div>
                </div>
            </div>

        </div>
        <div class="drawer-footer">
            <button class="btn-app-cancel" onclick="closeDrawer()">Cancelar</button>
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
let activeTab = 'Active';

document.addEventListener('DOMContentLoaded', () => {
    loadTags();
    loadProjects();
    loadSystemUsers();
    loadFormSubmissions();
    
    // Close drawer on overlay click
    const drawerOverlay = document.getElementById('brand-drawer');
    if (drawerOverlay) {
        drawerOverlay.addEventListener('click', closeDrawer);
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
                        img.style.width = '60px';
                        img.style.height = '60px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '10px';
                        img.style.border = '1px solid var(--border-color)';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                });
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
                let clientDisplay = c.name + (c.business_name ? ` (${c.business_name})` : '');
                item.innerHTML = `
                    <div style="width:28px; height:28px; border-radius:50%; background:#f59e0b; color:white; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:0.75rem;">
                        ${c.name.charAt(0)}
                    </div>
                    <div>
                        <div style="font-weight:600; font-size:0.85rem;">${c.name}</div>
                        <div style="font-size:0.75rem; color:var(--av-text-muted);">${c.business_name || c.email || ''}</div>
                    </div>
                `;
                item.onclick = () => {
                    document.getElementById('p_client_search').value = clientDisplay;
                    document.getElementById('p_client_name').value = clientDisplay;
                    dropdown.style.display = 'none';
                };
                dropdown.appendChild(item);
            });
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
    });
}

function loadProjects() {
    let formData = new FormData();
    formData.append('action', 'get_projects');
    fetch('ajax/ajax_audiovisual.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            allProjects = data.projects;
            renderProjects();
        }
    });
}

function switchTab(tab) {
    activeTab = tab;
    document.querySelectorAll('.brand-tab').forEach(b => {
        b.classList.toggle('active', b.dataset.tab === tab);
    });
    renderProjects();
}

function renderProjects() {
    let container = document.getElementById('projects-grid');
    let filtered = allProjects.filter(p => {
        if (activeTab === 'Archived') {
            return p.status === 'Archived';
        }
        return p.status !== 'Archived';
    });

    if (filtered.length === 0) {
        container.innerHTML = `
            <div class="brand-empty-state">
                <i class="ph-bold ph-video-camera brand-empty-icon"></i>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--av-text-main); margin-bottom: 0.5rem;">
                    No hay proyectos ${activeTab === 'Archived' ? 'archivados' : 'activos'}
                </h3>
                <p style="color: var(--av-text-muted); margin-bottom: 1.5rem;">Crea tu primer proyecto audiovisual o desarchiva proyectos existentes.</p>
                <button class="btn-primary" onclick="openCreateDrawer()">
                    <i class="ph-bold ph-plus"></i> Crear Nuevo Proyecto
                </button>
            </div>
        `;
        return;
    }

    container.innerHTML = filtered.map(p => {
        let clientInitials = p.client_name ? p.client_name.charAt(0).toUpperCase() : 'C';
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
            p.assigned_users.slice(0, 3).forEach(u => {
                if (u.avatar) {
                    usersHtml += `<img src="${u.avatar}" class="avatar-sm" title="${u.name}">`;
                } else {
                    usersHtml += `<span class="avatar-placeholder" title="${u.name}">${u.name.charAt(0)}</span>`;
                }
            });
            if (p.assigned_users.length > 3) {
                usersHtml += `<span class="avatar-more">+${p.assigned_users.length - 3}</span>`;
            }
            usersHtml += '</div>';
        }

        let progress = p.progress || 0;
        let progressClass = progress < 35 ? 'low' : (progress < 75 ? 'mid' : 'high');
        let totalTasks = p.total_tasks || 0;
        let completedTasks = p.completed_tasks || 0;
        let totalSubtasks = p.total_subtasks || 0;
        let completedSubtasks = p.completed_subtasks || 0;

        // Timer calculation
        let timerHtml = '';
        if (p.due_date) {
            let due = new Date(p.due_date + 'T23:59:59');
            let now = new Date();
            let diff = due - now;
            if (diff < 0) {
                timerHtml = `<span class="modern-timer expired"><i class="ph-bold ph-hourglass-simple-low"></i> Tiempo agotado</span>`;
            } else {
                let days = Math.floor(diff / (1000 * 60 * 60 * 24));
                timerHtml = `<span class="modern-timer"><i class="ph-bold ph-timer"></i> ${days} ${days === 1 ? 'día' : 'días'}</span>`;
            }
        }

        let statusClass = (p.status || 'Active').toLowerCase();
        let statusLabel = p.status === 'Active' ? 'Activo' : (p.status === 'Completed' ? 'Completado' : (p.status === 'Pending' ? 'Pendiente' : 'Archivado'));

        return `
            <div class="project-card">
                <div class="app-card-top-bar">
                    <div class="app-card-badges-left">
                        <span class="app-status-badge ${statusClass}">
                            <span class="status-dot"></span> ${statusLabel}
                        </span>
                        ${timerHtml}
                    </div>
                    <button class="app-btn-more" onclick="openProjectMenu(${p.id}, event)" title="Opciones">
                        <i class="ph-bold ph-dots-three"></i>
                    </button>
                </div>

                <div class="app-card-hero" onclick="window.location.href='index.php?module=audiovisual&action=view&id=${p.id}'" title="Abrir tablero de proyecto">
                    <h3 class="app-card-title">${p.title}</h3>
                    <div class="app-client-row">
                        <div class="app-client-avatar">${clientInitials}</div>
                        <div class="app-client-info">
                            <span class="app-client-name">${clientDisplayName}</span>
                            <span class="app-client-date"><i class="ph-bold ph-calendar"></i> ${formattedDate}</span>
                        </div>
                    </div>
                </div>

                <div class="app-card-meta-row">
                    <div class="app-card-tags">${tagsHtml}</div>
                    ${usersHtml}
                </div>

                <div class="card-progress-section" onclick="window.location.href='index.php?module=audiovisual&action=view&id=${p.id}'" style="cursor: pointer;" title="Ver fases y tareas">
                    <div class="card-progress-header">
                        <span class="progress-header-title">
                            <i class="ph-bold ph-chart-donut"></i> Escala de Progreso
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
                            <i class="ph-bold ph-check-circle" style="color: #10b981;"></i> ${completedTasks}/${totalTasks} tareas
                        </span>
                        <span class="stat-chip">
                            <i class="ph-bold ph-list-checks" style="color: #6366f1;"></i> ${completedSubtasks}/${totalSubtasks} subtareas
                        </span>
                    </div>
                </div>

                <div class="app-card-dates-box">
                    <div class="app-date-col border-right">
                        <span class="app-date-label"><i class="ph-bold ph-calendar-blank"></i> Inicio</span>
                        <span class="app-date-value ${!p.start_date ? 'empty' : ''}">${p.start_date ? formatDateDisplay(p.start_date) : 'Sin fecha'}</span>
                    </div>
                    <div class="app-date-col">
                        <span class="app-date-label"><i class="ph-bold ph-clock"></i> Límite</span>
                        <span class="app-date-value ${!p.due_date ? 'empty' : ''}" style="${p.due_date ? 'color: #ef4444;' : ''}">${p.due_date ? formatDateDisplay(p.due_date) : 'Sin fecha'}</span>
                    </div>
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
    document.getElementById('drawer-title').innerText = 'Nuevo Proyecto Audiovisual';
    document.getElementById('p_id').value = '0';
    document.getElementById('p_title').value = '';
    document.getElementById('p_client_search').value = '';
    document.getElementById('p_client_name').value = '';
    document.getElementById('p_form_submission').value = '';
    document.getElementById('p_start').value = '';
    document.getElementById('p_due').value = '';
    document.getElementById('p_status').value = 'Active';
    document.getElementById('p_drive_url').value = '';
    document.getElementById('p_drive_id').value = '';
    document.getElementById('p_description').value = '';
    document.getElementById('form-duration-calc').innerHTML = '';
    
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
    const drawerPanel = document.querySelector('.brand-drawer');
    if (drawerPanel) drawerPanel.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function openEditDrawer(p) {
    document.getElementById('drawer-title').innerText = 'Editar Proyecto Audiovisual';
    document.getElementById('p_id').value = p.id;
    document.getElementById('p_title').value = p.title || '';
    document.getElementById('p_client_search').value = p.client_name || '';
    document.getElementById('p_client_name').value = p.client_name || '';
    document.getElementById('p_form_submission').value = p.form_submission_id || '';
    document.getElementById('p_start').value = p.start_date || '';
    document.getElementById('p_due').value = p.due_date || '';
    document.getElementById('p_status').value = p.status || 'Active';
    document.getElementById('p_drive_url').value = p.drive_folder_url || '';
    document.getElementById('p_drive_id').value = p.drive_folder_id || '';
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
                    img.style.width = '60px';
                    img.style.height = '60px';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '10px';
                    img.style.border = '1px solid var(--border-color)';
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
    const drawerPanel = document.querySelector('.brand-drawer');
    if (drawerPanel) drawerPanel.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    const overlay = document.getElementById('brand-drawer');
    if (overlay) overlay.classList.remove('active');
    const drawerPanel = document.querySelector('.brand-drawer');
    if (drawerPanel) drawerPanel.classList.remove('active');
    document.body.style.overflow = '';
}

function saveProject() {
    let id = document.getElementById('p_id').value;
    let title = document.getElementById('p_title').value.trim();
    if (!title) {
        Swal.fire('Atención', 'El título del proyecto es obligatorio', 'warning');
        return;
    }

    let clientName = document.getElementById('p_client_name').value || document.getElementById('p_client_search').value;
    let formSubmissionId = document.getElementById('p_form_submission').value;
    let startDate = document.getElementById('p_start').value;
    let dueDate = document.getElementById('p_due').value;
    let status = document.getElementById('p_status').value;
    let driveUrl = document.getElementById('p_drive_url').value;
    let driveId = document.getElementById('p_drive_id').value;
    let desc = document.getElementById('p_description').value;

    let assignedUsers = [];
    if (usersTagify) {
        assignedUsers = usersTagify.value.map(item => item.id);
    }

    let formData = new FormData();
    formData.append('action', 'save_project');
    formData.append('id', id);
    formData.append('title', title);
    formData.append('client_name', clientName);
    formData.append('form_submission_id', formSubmissionId);
    formData.append('start_date', startDate);
    formData.append('due_date', dueDate);
    formData.append('status', status);
    formData.append('drive_folder_url', driveUrl);
    formData.append('drive_folder_id', driveId);
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
