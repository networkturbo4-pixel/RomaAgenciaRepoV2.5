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
    --brand-bg: var(--bg-color, #f8fafc);
    --brand-card-bg: var(--bg-surface, #ffffff);
    --brand-text-main: var(--color-title, #0f172a);
    --brand-text-muted: var(--color-text, #64748b);
    --brand-border: var(--border-color, #e2e8f0);
}

[data-theme="dark"] {
    --brand-primary: var(--secondary-color, #34d399);
    --brand-bg: var(--bg-color, #0f172a);
    --brand-card-bg: var(--bg-surface, #1e293b);
    --brand-text-main: var(--color-title, #f8fafc);
    --brand-text-muted: var(--color-text, #94a3b8);
    --brand-border: var(--border-color, #334155);
}

.brand-container {
    padding: var(--space-6, 1.5rem);
    max-width: 1400px;
    margin: 0 auto;
    font-family: var(--font-family, 'Inter', sans-serif);
}

/* Header Section */
.brand-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.brand-title h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--brand-text-main);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.brand-actions .btn-primary {
    background: var(--brand-secondary);
    color: white;
    border: none;
    padding: 0.6rem 1.2rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
}

.brand-actions .btn-primary:hover {
    background: var(--color-btn-hover, #4f46e5);
    transform: translateY(-1px);
    box-shadow: 0 6px 8px -1px var(--primary-bg);
}

/* Project Cards Grid */
.brand-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

.brand-tabs-container {
    margin-bottom: 2rem;
    display: flex;
}
.brand-tabs {
    display: inline-flex;
    background: var(--brand-border);
    padding: 0.35rem;
    border-radius: 12px;
    gap: 0.25rem;
}
.brand-tab {
    background: transparent;
    border: none;
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--brand-text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.brand-tab:hover {
    color: var(--brand-text-main);
}
.brand-tab.active {
    background: var(--brand-card-bg);
    color: var(--brand-text-main);
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.brand-tab.active i {
    color: var(--brand-secondary);
}

.project-card {
    background: var(--brand-card-bg);
    border-radius: 20px;
    padding: 1.25rem;
    box-shadow: 0 10px 30px -8px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    border: 1px solid rgba(0,0,0,0.03);
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
[data-theme="dark"] .project-card {
    border: 1px solid var(--brand-border);
    box-shadow: 0 10px 30px -8px rgba(0, 0, 0, 0.3);
}

.project-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.12), 0 8px 10px -4px rgba(0, 0, 0, 0.05);
}

/* Card Header: Avatar, Name, Date, Status */
.card-header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.card-client-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.client-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--brand-secondary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.client-meta {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.client-meta h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--brand-text-main);
}
.assigned-users-stack {
    display: flex;
    align-items: center;
    margin-bottom: 0.75rem;
}
.assigned-users-stack .avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid var(--brand-card-bg);
    margin-left: -10px;
    object-fit: cover;
    background: var(--brand-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--brand-text-main);
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.assigned-users-stack .avatar-sm:first-child {
    margin-left: 0;
}
.client-meta-bottom {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.client-meta-bottom span.date {
    font-size: 0.75rem;
    color: var(--brand-text-muted);
}

.status-badge {
    padding: 0.25rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(16, 185, 129, 0.1);
    color: var(--brand-primary);
}

.status-badge.pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.status-badge.completed { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
.status-badge.archived { background: rgba(100, 116, 139, 0.1); color: #64748b; }

.card-preview {
    width: 100%;
    height: 180px;
    border-radius: 12px;
    background: #f1f5f9;
    overflow: hidden;
    display: flex;
    position: relative;
}
[data-theme="dark"] .card-preview { background: #0f172a; }

.card-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.modern-timer {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    color: #fff;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    z-index: 2;
}
.modern-timer.expired {
    background: rgba(239, 68, 68, 0.85);
}

.client-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-actions-top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
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

.card-preview.split-preview {
    display: flex;
    gap: 2px;
}
.card-preview.split-preview img {
    width: 50%;
}

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

/* Card Content: Title & Tags */
.card-content h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.15rem;
    color: var(--brand-text-main);
    font-weight: 700;
    line-height: 1.3;
}
.card-content p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--brand-text-muted);
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 1rem;
}

.tag-pill {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    border: none;
    display: inline-flex;
    align-items: center;
}

/* Card Details Grid */
.card-details {
    display: flex;
    flex-direction: column;
    gap: 0;
    border-top: 1px solid rgba(0,0,0,0.04);
    padding-top: 1rem;
    margin-top: auto;
}
[data-theme="dark"] .card-details { border-top-color: rgba(255,255,255,0.05); }

.brand-dates-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-top: 0.5rem;
    background: var(--brand-bg);
    padding: 0.75rem;
    border-radius: 12px;
}
.brand-date-item {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.brand-date-item span.label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--brand-text-muted);
    font-weight: 700;
}
.brand-date-item span.value {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--brand-text-main);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.brand-date-item i {
    color: var(--brand-primary);
    font-size: 1.1rem;
}

.brand-drive-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1rem;
    background: rgba(66, 133, 244, 0.08); /* Light Google Drive Blue */
    border-radius: 12px;
    color: #1e40af;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    margin-top: 0.75rem;
    transition: background 0.2s, transform 0.1s;
}
[data-theme="dark"] .brand-drive-btn { color: #60a5fa; background: rgba(66, 133, 244, 0.15); }
.brand-drive-btn:hover {
    background: rgba(66, 133, 244, 0.15);
    transform: translateY(-1px);
}
.brand-drive-btn .left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.brand-drive-btn i.ph-google-drive-logo {
    font-size: 1.25rem;
    color: #4285F4;
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

/* --- SIDEBAR DRAWER --- */
.drawer-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.drawer-overlay.active {
    opacity: 1;
    visibility: visible;
}

.drawer-panel {
    position: fixed;
    top: 0; right: -650px;
    width: 100%; max-width: 600px;
    height: 100%;
    background: var(--brand-card-bg);
    box-shadow: -15px 0 40px rgba(0,0,0,0.12);
    z-index: 1001;
    transition: right 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
    border-top-left-radius: 24px;
    border-bottom-left-radius: 24px;
    overflow: hidden;
}

.drawer-overlay.active .drawer-panel {
    right: 0;
}

.drawer-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--brand-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.drawer-header h2 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--brand-text-main);
    letter-spacing: -0.02em;
}

.drawer-body {
    padding: 1.5rem;
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.form-group label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--brand-text-main);
    margin-bottom: 0.2rem;
}

.form-control {
    padding: 0.85rem 1.25rem;
    border-radius: 12px;
    border: 1px solid var(--brand-border);
    background: var(--bg-body, #f8fafc);
    color: var(--brand-text-main);
    font-family: inherit;
    font-size: 0.95rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
[data-theme="dark"] .form-control { background: #0f172a; }

.form-control:focus {
    outline: none;
    border-color: var(--brand-secondary);
    background: var(--brand-card-bg);
    box-shadow: 0 0 0 4px var(--primary-bg);
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

.drawer-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--brand-border);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.btn-secondary {
    background: var(--bg-body, #f8fafc);
    border: 1px solid var(--brand-border);
    color: var(--brand-text-main);
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
[data-theme="dark"] .btn-secondary { background: #0f172a; }

.btn-secondary:hover { background: var(--brand-border); }

.brand-actions .btn-primary, .drawer-footer .btn-primary {
    background: var(--brand-secondary);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 10px var(--primary-bg);
}

.brand-actions .btn-primary:hover, .drawer-footer .btn-primary:hover {
    background: var(--color-btn-hover, #4f46e5);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px var(--primary-bg);
}

/* Tag Manager mini UI */
.tag-manager-wrapper {
    border: 1px solid var(--brand-border);
    border-radius: 8px;
    padding: 0.75rem;
}
.tag-list-editable {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}
.tag-edit-pill {
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    background: rgba(99, 102, 241, 0.1);
    color: var(--brand-secondary);
    border: 1px solid rgba(99, 102, 241, 0.2);
    cursor: pointer;
}
.tag-edit-pill.selected {
    background: var(--brand-secondary);
    color: white;
}
.add-tag-form {
    display: flex;
    gap: 0.5rem;
}
.add-tag-form input[type="color"] {
    width: 36px; height: 36px; padding: 0; border: none; border-radius: 4px; overflow: hidden; cursor: pointer;
}

/* 3 dots dropdown */
.dropdown-menu {
    position: absolute;
    right: 0; top: 100%;
    background: var(--brand-card-bg);
    border: 1px solid var(--brand-border);
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    min-width: 120px;
    display: none;
    flex-direction: column;
    z-index: 10;
    overflow: hidden;
}
.dropdown-menu.show { display: flex; }
.dropdown-item {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
    color: var(--brand-text-main);
    cursor: pointer;
    border-bottom: 1px solid var(--brand-border);
}
.dropdown-item:last-child { border-bottom: none; }
.dropdown-item:hover { background: rgba(0,0,0,0.03); }
[data-theme="dark"] .dropdown-item:hover { background: rgba(255,255,255,0.05); }
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
        <div class="brand-title">
            <h1 style="display:flex; align-items:center; gap:0.5rem;">
                <a href="index.php?module=workspace" class="btn-icon" style="color:var(--brand-text-main); text-decoration:none; padding:0.4rem; background:var(--brand-card-bg); border:1px solid var(--brand-border); box-shadow:0 2px 4px rgba(0,0,0,0.02);" title="Volver">
                    <i class="ph ph-arrow-left"></i>
                </a>
                Desarrollo de Marca
            </h1>
        </div>
        <div class="brand-actions">
            <button class="btn-primary" onclick="openDrawer()">
                <i class="ph ph-plus"></i> Nuevo Proyecto
            </button>
        </div>
    </div>

    <!-- Tabs / Filter -->
    <div class="brand-tabs-container">
        <div class="brand-tabs">
            <button class="brand-tab active" data-filter="Active" onclick="filterProjects('Active')">
                <i class="ph-fill ph-lightning"></i> Activo
            </button>
            <button class="brand-tab" data-filter="Archived" onclick="filterProjects('Archived')">
                <i class="ph ph-archive-box"></i> Archivado
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

<!-- Drawer Off-Canvas -->
<div class="drawer-overlay" id="brand-drawer">
    <div class="drawer-panel" onclick="event.stopPropagation()">
        <div class="drawer-header">
            <h2 id="drawer-title">Crear Proyecto</h2>
            <button class="btn-icon" onclick="closeDrawer()">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="drawer-body">
            <input type="hidden" id="p_id" value="0">
            
            <div class="form-group">
                <label>Título del Proyecto</label>
                <input type="text" id="p_title" class="form-control" placeholder="Ej. Healthcare Landing Page Redesign">
            </div>

            <div class="form-group" style="position:relative;">
                <label>Cliente (Buscar)</label>
                <input type="text" id="p_client" class="form-control" placeholder="Buscar cliente por nombre..." onkeyup="searchClients(this.value)" autocomplete="off">
                <input type="hidden" id="p_client_id" value="">
                <div id="client_results" style="position:absolute; top:100%; left:0; right:0; background:var(--brand-card-bg); border:1px solid var(--brand-border); max-height:200px; overflow-y:auto; z-index:100; display:none; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.1);"></div>
            </div>
            
            <div class="form-group">
                <label>Usuarios Asignados</label>
                <input id="p_users" class="form-control" placeholder="Asignar miembros del equipo...">
            </div>

            <div class="form-group">
                <label>Formulario / Brief Vinculado (Opcional)</label>
                <select id="p_form_submission" class="form-control">
                    <option value="">-- Sin formulario --</option>
                </select>
                <div style="font-size:0.8rem; color:var(--brand-text-muted); margin-top:0.25rem;">Puedes vincular una respuesta del módulo de formularios como el Brief del proyecto.</div>
            </div>

            <div class="form-group" style="display: flex; gap: 1rem;">
                <div style="flex:1;">
                    <label>Estado</label>
                    <select id="p_status" class="form-control">
                        <option value="Active">Activo</option>
                        <option value="Pending">Pendiente</option>
                        <option value="Completed">Completado</option>
                        <option value="Archived">Archivado</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label>Fecha de Inicio</label>
                    <input type="date" id="p_start" class="form-control" onchange="calcFormDuration()">
                </div>
                <div style="flex:1;">
                    <label>Fecha Límite</label>
                    <input type="date" id="p_due" class="form-control" onchange="calcFormDuration()">
                </div>
            </div>
            <div id="form-duration-calc" style="font-size: 0.85rem; color: var(--brand-secondary); margin-top: 0.5rem; font-weight: 600; display: flex; align-items: center; gap: 0.4rem;"></div>

            <div class="form-group">
                <label>Imágenes de Portada</label>
                <input type="file" id="p_cover" class="form-control" multiple accept="image/*">
                <input type="hidden" id="p_existing_covers" value="">
                <div id="p_cover_preview" style="font-size:0.8rem; color:var(--brand-text-muted); margin-top:0.25rem;"></div>
            </div>

            <div class="form-group" style="margin-top: 1rem;">
                <label style="display:flex; align-items:center; gap:0.5rem; color:#3b82f6; font-size:0.95rem;">
                    <i class="ph ph-google-drive-logo" style="font-size:1.25rem;"></i> Carpeta Global del Proyecto (Opcional)
                </label>
                <div style="font-size:0.8rem; color:var(--brand-text-muted); margin-bottom:0.5rem;">Si la dejas vacía, el sistema creará una carpeta nueva automáticamente.</div>
                <div style="display:flex; gap:0.5rem;">
                    <input type="text" id="p_drive" class="form-control" placeholder="Enlace de la carpeta global..." style="flex:1;">
                    <input type="hidden" id="p_drive_id" value="">
                    <button class="btn-secondary" onclick="openDrivePicker()" style="color:#3b82f6; border-color:#bfdbfe; background:#eff6ff; font-weight:600; padding: 0.6rem 1.5rem;">Elegir</button>
                </div>
            </div>

            <div class="form-group">
                <label>Etiquetas (Selecciona las del proyecto)</label>
                <div class="tag-manager-wrapper">
                    <div class="tag-list-editable" id="tag-selector-list">
                        <!-- Tags rendered here -->
                    </div>
                    <div class="add-tag-form">
                        <input type="color" id="new_tag_color" value="#6366f1">
                        <input type="text" id="new_tag_name" class="form-control" placeholder="Nueva etiqueta" style="flex:1; padding: 0.5rem;">
                        <button class="btn-primary" onclick="createNewTag()" style="padding: 0.5rem 1rem;"><i class="ph ph-plus"></i></button>
                    </div>
                </div>
            </div>

        </div>
        <div class="drawer-footer">
            <button class="btn-secondary" onclick="closeDrawer()">Cancelar</button>
            <button class="btn-primary" onclick="saveProject()">Guardar</button>
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
        container.innerHTML = '<div style="text-align:center; padding: 2rem; color: var(--brand-text-muted); grid-column: 1/-1;">No hay proyectos en esta categoría.</div>';
        return;
    }

    filtered.forEach(p => {
        let dateStr = p.created_at ? new Date(p.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : 'N/A';
        let avatarLetter = p.client_name ? p.client_name.charAt(0).toUpperCase() : 'C';
        let clientName = p.client_name || 'Cliente sin nombre';
        
        let timerHtml = p.due_date ? `<div class="modern-timer" data-start="${p.start_date || ''}" data-due="${p.due_date}"><i class="ph ph-hourglass-high"></i> <span class="timer-text">Calculando...</span></div>` : '';
        let imagesHtml = p.cover_image ? `<div class="card-preview" style="cursor: pointer;" onclick="window.location.href='index.php?module=desarrollo_marca&action=view&id=${p.id}'" title="Entrar al Proyecto"><img src="${p.cover_image.split(',')[0]}" onerror="this.src='https://placehold.co/600x400/f1f5f9/64748b?text=Cover'">${timerHtml}</div>` : `<div class="card-preview" style="cursor: pointer;" onclick="window.location.href='index.php?module=desarrollo_marca&action=view&id=${p.id}'" title="Entrar al Proyecto"><img src="https://placehold.co/600x400/f1f5f9/64748b?text=No+Image">${timerHtml}</div>`;

        let tagsHtml = p.tags && p.tags.length > 0 ? p.tags.map(t => `<span class="tag-pill" style="background-color: ${t.color}15; color: ${t.color};">${t.name}</span>`).join('') : '';
        let startDateFormatted = p.start_date ? new Date(p.start_date + 'T12:00:00').toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) : 'Sin definir';
        let dueDateFormatted = p.due_date ? new Date(p.due_date + 'T12:00:00').toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) : 'Sin definir';

        const statusMap = { 'Active': 'Activo', 'Pending': 'Pendiente', 'Completed': 'Completado', 'Archived': 'Archivado' };
        let displayStatus = statusMap[p.status] || p.status;
        
        let avatarsHtml = '';
        if (p.assigned_users && p.assigned_users.length > 0) {
            avatarsHtml = `<div class="assigned-users-stack">`;
            p.assigned_users.slice(0, 3).forEach((u, i) => {
                let initial = u.name.charAt(0).toUpperCase();
                let zIndex = 10 - i;
                avatarsHtml += u.avatar && u.avatar !== 'default.png' ? `<img src="${u.avatar}" class="avatar-sm" style="z-index:${zIndex};" title="${u.name}">` : `<div class="avatar-sm avatar-placeholder" style="z-index:${zIndex};" title="${u.name}">${initial}</div>`;
            });
            if (p.assigned_users.length > 3) avatarsHtml += `<div class="avatar-sm avatar-more" style="z-index:1;">+${p.assigned_users.length - 3}</div>`;
            avatarsHtml += `</div>`;
        }

        let card = document.createElement('div');
        card.className = 'project-card';
        card.innerHTML = `
            <div class="card-header-top">
                <div class="card-client-info">
                    <div class="client-avatar">${avatarLetter}</div>
                    <div class="client-meta">
                        <h4>${clientName}</h4>
                        <div class="client-meta-bottom">
                            <span class="date">${dateStr}</span>
                            <span class="status-badge ${p.status.toLowerCase()}">${displayStatus}</span>
                        </div>
                    </div>
                </div>
                <div class="card-actions-top">
                    <div style="position:relative;">
                        <button class="btn-icon" onclick="toggleMenu(event, ${p.id})"><i class="ph ph-dots-three"></i></button>
                        <div class="dropdown-menu" id="menu-${p.id}">
                            <div class="dropdown-item" onclick="openDrawer(${p.id})"><i class="ph ph-pencil-simple"></i> Editar</div>
                            <div class="dropdown-item" onclick="archiveProject(${p.id}, '${p.status === 'Archived' ? 'Active' : 'Archived'}')"><i class="ph ${p.status === 'Archived' ? 'ph-arrow-u-up-left' : 'ph-archive-box'}"></i> ${p.status === 'Archived' ? 'Restaurar' : 'Archivar'}</div>
                            <div class="dropdown-item danger" onclick="deleteProject(${p.id})"><i class="ph ph-trash"></i> Eliminar</div>
                        </div>
                    </div>
                </div>
            </div>
            ${imagesHtml}
            <div class="card-content"><h3>${p.title}</h3></div>
            <div class="card-details">
                ${avatarsHtml}
                ${tagsHtml ? `<div style="display:flex; flex-wrap:wrap; gap: 0.4rem; margin-bottom: 0.75rem;">${tagsHtml}</div>` : ''}
                <div class="brand-dates-grid">
                    <div class="brand-date-item"><span class="label">Inicio</span><span class="value"><i class="ph ph-calendar-plus"></i> ${startDateFormatted}</span></div>
                    <div class="brand-date-item"><span class="label">Límite</span><span class="value"><i class="ph ph-calendar-check"></i> ${dueDateFormatted}</span></div>
                </div>
                ${p.drive_folder_url ? `<a href="${p.drive_folder_url}" target="_blank" class="brand-drive-btn"><div class="left"><i class="ph-fill ph-google-drive-logo"></i> Carpeta de Proyecto</div><i class="ph ph-caret-right" style="color: inherit; opacity: 0.5;"></i></a>` : ''}
            </div>
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
    Swal.fire({
        title: '¿Eliminar Proyecto?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: 'var(--brand-text-muted)',
        background: 'var(--brand-card-bg)',
        color: 'var(--brand-text-main)',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'swal2-modern-popup'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            executeDelete(id);
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
            if(typeof Swal !== 'undefined') Swal.fire('Eliminado', '', 'success');
            loadProjects();
        }
    });
}

function archiveProject(id, newStatus) {
    let actionText = newStatus === 'Archived' ? 'Archivar' : 'Restaurar';
    Swal.fire({
        title: `¿${actionText} Proyecto?`,
        text: newStatus === 'Archived' ? "Podrás verlo en la pestaña de Archivados." : "El proyecto volverá a estar Activo.",
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: 'var(--brand-primary)',
        cancelButtonColor: 'var(--brand-text-muted)',
        background: 'var(--brand-card-bg)',
        color: 'var(--brand-text-main)',
        confirmButtonText: 'Aceptar',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'swal2-modern-popup'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            executeArchive(id, newStatus);
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
    if(query.trim().length < 2) {
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
                    div.className = 'dropdown-item';
                    div.innerText = c.name;
                    div.onclick = () => {
                        document.getElementById('p_client').value = c.name;
                        document.getElementById('p_client_id').value = c.id;
                        resultsDiv.style.display = 'none';
                    };
                    resultsDiv.appendChild(div);
                });
                resultsDiv.style.display = 'block';
            } else {
                resultsDiv.innerHTML = '<div class="dropdown-item" style="color:var(--brand-text-muted);">No se encontraron clientes</div>';
                resultsDiv.style.display = 'block';
            }
        });
    }, 300);
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
