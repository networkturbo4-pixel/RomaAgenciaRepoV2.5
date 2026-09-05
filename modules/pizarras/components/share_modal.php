<!-- modules/pizarras/components/share_modal.php -->
<style>
    /* =========================================================
       MODAL COMPARTIR PIZARRA - MODERN SAAS REDESIGN
       ========================================================= */
    .share-modal-dialog {
        max-width: 580px !important;
        width: 94% !important;
        border-radius: 20px !important;
        background: var(--bg-surface, #ffffff);
        border: 1px solid var(--border-color, #e2e8f0);
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25) !important;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .share-saas-header {
        padding: 1.25rem 1.75rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--border-color, #f1f5f9);
        background: var(--bg-surface, #ffffff);
    }

    .share-header-content {
        display: flex;
        align-items: center;
        gap: 0.85rem;
    }

    .share-header-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: color-mix(in srgb, var(--primary-color, #3b82f6) 12%, transparent);
        color: var(--primary-color, #3b82f6);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }

    .share-header-content h2 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text-main, #0f172a);
        line-height: 1.2;
    }

    .share-header-sub {
        margin: 3px 0 0 0;
        font-size: 0.8rem;
        color: var(--text-muted, #64748b);
        line-height: 1.3;
    }

    .share-modal-body-custom {
        padding: 1.5rem 1.75rem;
        max-height: 72vh;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        scrollbar-width: thin;
        scrollbar-color: var(--border-color, #cbd5e1) transparent;
    }

    .share-modal-body-custom::-webkit-scrollbar {
        width: 6px;
    }
    .share-modal-body-custom::-webkit-scrollbar-thumb {
        background: var(--border-color, #cbd5e1);
        border-radius: 6px;
    }

    .share-form-group {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
    }

    .share-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-muted, #475569);
        text-transform: uppercase;
        letter-spacing: 0.6px;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        margin: 0;
    }

    .share-label i {
        font-size: 0.95rem;
        color: var(--primary-color, #3b82f6);
    }

    .share-modal-input {
        width: 100%;
        padding: 10px 14px;
        border: 1.5px solid var(--border-color, #cbd5e1);
        border-radius: 12px;
        font-size: 0.92rem;
        color: var(--text-main, #0f172a);
        background: var(--bg-surface, #ffffff);
        outline: none;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .share-modal-input:focus {
        border-color: var(--primary-color, #3b82f6);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color, #3b82f6) 15%, transparent);
    }

    /* Cover dropzone */
    .share-file-hidden {
        display: none !important;
    }

    .share-cover-dropzone {
        border: 1.5px dashed var(--border-color, #cbd5e1);
        border-radius: 12px;
        background: color-mix(in srgb, var(--border-color, #e2e8f0) 25%, transparent);
        padding: 14px 18px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 56px;
    }

    .share-cover-dropzone:hover {
        border-color: var(--primary-color, #3b82f6);
        background: color-mix(in srgb, var(--primary-color, #3b82f6) 6%, transparent);
    }

    .cover-placeholder {
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--text-muted, #64748b);
    }

    .cover-placeholder i {
        font-size: 1.6rem;
        color: var(--primary-color, #3b82f6);
    }

    .cover-text-main {
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--text-main, #1e293b);
        display: block;
    }

    .cover-text-sub {
        font-size: 0.75rem;
        color: var(--text-muted, #64748b);
    }

    .cover-preview-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        gap: 12px;
    }

    .cover-preview-box img {
        height: 48px;
        max-width: 140px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid var(--border-color, #cbd5e1);
    }

    .btn-remove-cover {
        background: #fef2f2;
        border: 1px solid #fee2e2;
        color: #ef4444;
        cursor: pointer;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.2s;
    }

    .btn-remove-cover:hover {
        background: #fee2e2;
        border-color: #fca5a5;
    }

    /* Invite row */
    .share-invite-row {
        display: flex;
        gap: 8px;
        align-items: stretch;
        width: 100%;
    }

    .share-select-wrap {
        flex: 1;
        min-width: 0;
    }

    .share-btn-add {
        background: var(--primary-color, #3b82f6);
        color: #ffffff;
        border: none;
        border-radius: 12px;
        padding: 0 16px;
        height: 44px;
        font-size: 0.88rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
        box-shadow: 0 2px 8px color-mix(in srgb, var(--primary-color, #3b82f6) 30%, transparent);
    }

    .share-btn-add:hover {
        background: var(--primary-hover, #2563eb);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px color-mix(in srgb, var(--primary-color, #3b82f6) 40%, transparent);
    }

    /* Users Section */
    .share-users-section {
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 14px;
        background: color-mix(in srgb, var(--border-color, #f1f5f9) 35%, transparent);
        padding: 12px 14px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .share-users-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border-color, #e2e8f0);
    }

    .share-users-count-badge {
        font-size: 0.72rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 99px;
        background: color-mix(in srgb, var(--primary-color, #3b82f6) 15%, transparent);
        color: var(--primary-color, #3b82f6);
    }

    .share-modal-list {
        max-height: 190px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 6px;
        scrollbar-width: thin;
    }

    .share-user-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 10px;
        background: var(--bg-surface, #ffffff);
        border: 1px solid var(--border-color, #f1f5f9);
        border-radius: 10px;
        transition: background 0.15s ease;
    }

    .share-user-item:hover {
        background: color-mix(in srgb, var(--primary-color, #3b82f6) 4%, var(--bg-surface, #ffffff));
        border-color: color-mix(in srgb, var(--primary-color, #3b82f6) 20%, var(--border-color, #e2e8f0));
    }

    .share-user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        flex: 1;
    }

    .share-user-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .share-user-text {
        min-width: 0;
        overflow: hidden;
    }

    .share-user-name {
        font-weight: 600;
        color: var(--text-main, #0f172a);
        font-size: 0.85rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .share-user-email {
        color: var(--text-muted, #64748b);
        font-size: 0.75rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .share-user-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }

    .share-owner-badge {
        font-size: 0.75rem;
        font-weight: 600;
        color: #d97706;
        background: #fef3c7;
        padding: 4px 10px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid #fde68a;
    }

    .share-role-select {
        border: 1.5px solid var(--border-color, #cbd5e1);
        background: var(--bg-surface, #ffffff);
        font-size: 0.8rem;
        color: var(--text-main, #475569);
        font-weight: 600;
        cursor: pointer;
        border-radius: 8px;
        padding: 5px 8px;
        transition: all 0.2s;
        outline: none;
    }

    .share-role-select:hover, .share-role-select:focus {
        border-color: var(--primary-color, #3b82f6);
    }

    .btn-remove-user {
        background: #fef2f2;
        border: 1px solid #fee2e2;
        color: #ef4444;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        transition: all 0.2s;
        outline: none;
    }

    .btn-remove-user:hover {
        background: #fee2e2;
        border-color: #fca5a5;
        transform: scale(1.05);
    }

    .share-empty-users {
        text-align: center;
        padding: 16px 8px;
        color: var(--text-muted, #94a3b8);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }

    .share-empty-users i {
        font-size: 1.8rem;
        color: var(--text-muted, #cbd5e1);
    }

    .share-empty-users p {
        margin: 0;
        font-size: 0.8rem;
    }

    /* General Access Card */
    .share-general-access-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border: 1.5px solid var(--border-color, #e2e8f0);
        border-radius: 14px;
        background: color-mix(in srgb, var(--border-color, #f8fafc) 60%, transparent);
        transition: all 0.2s ease;
    }

    .access-icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .access-icon-circle.restricted {
        background: #f1f5f9;
        color: #64748b;
    }

    .access-icon-circle.public {
        background: #dcfce7;
        color: #16a34a;
    }

    .access-info-wrap {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .share-access-select {
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
        font-weight: 700;
        font-size: 0.92rem;
        color: var(--text-main, #0f172a);
        cursor: pointer;
        outline: none;
    }

    .access-desc-text {
        font-size: 0.76rem;
        color: var(--text-muted, #64748b);
        line-height: 1.2;
    }

    .access-public-role-wrap {
        flex-shrink: 0;
    }

    /* Modal Footer */
    .share-saas-footer {
        padding: 1.15rem 1.75rem;
        border-top: 1px solid var(--border-color, #f1f5f9);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        background: var(--bg-surface, #ffffff);
        flex-wrap: wrap;
    }

    .share-btn-copy-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--primary-color, #3b82f6);
        background: color-mix(in srgb, var(--primary-color, #3b82f6) 10%, transparent);
        border: 1.5px solid color-mix(in srgb, var(--primary-color, #3b82f6) 30%, transparent);
        padding: 8px 16px;
        border-radius: 99px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .share-btn-copy-link:hover {
        background: color-mix(in srgb, var(--primary-color, #3b82f6) 18%, transparent);
        border-color: var(--primary-color, #3b82f6);
        transform: translateY(-1px);
    }

    .share-btn-copy-link.copied {
        background: #dcfce7;
        color: #16a34a;
        border-color: #86efac;
    }

    .share-footer-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* TomSelect Custom SaaS Styles */
    .ts-control {
        border: 1.5px solid var(--border-color, #cbd5e1) !important;
        border-radius: 12px !important;
        padding: 9px 14px !important;
        font-size: 0.88rem !important;
        box-shadow: none !important;
        background: var(--bg-surface, #ffffff) !important;
        color: var(--text-main, #0f172a) !important;
        transition: all 0.2s !important;
        min-height: 44px !important;
    }

    .ts-wrapper.focus .ts-control {
        border-color: var(--primary-color, #3b82f6) !important;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color, #3b82f6) 15%, transparent) !important;
    }

    .ts-dropdown {
        border-radius: 12px !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.12) !important;
        border: 1px solid var(--border-color, #cbd5e1) !important;
        z-index: 1050 !important;
    }

    /* =========================================================
    /* =========================================================
       DARK MODE SUPPORT (FONDO NEGRO / OLED TRUE BLACK)
       ========================================================= */
    [data-theme="dark"] .wb-modal-overlay {
        background: rgba(0, 0, 0, 0.82) !important;
    }
    [data-theme="dark"] .share-modal-dialog {
        background: #000000 !important;
        border-color: #262626 !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.85) !important;
    }
    [data-theme="dark"] .share-saas-header {
        background: #000000 !important;
        border-color: #1f1f23 !important;
    }
    [data-theme="dark"] .share-saas-footer {
        background: #000000 !important;
        border-color: #1f1f23 !important;
    }
    [data-theme="dark"] .share-modal-body-custom {
        background: #000000 !important;
    }
    [data-theme="dark"] .share-header-content h2 {
        color: #ffffff !important;
    }
    [data-theme="dark"] .share-header-sub {
        color: #a1a1aa !important;
    }
    [data-theme="dark"] .share-label {
        color: #a1a1aa !important;
    }
    [data-theme="dark"] .share-modal-input {
        background: #0c0c0e !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    [data-theme="dark"] .share-modal-input:focus {
        border-color: var(--primary-color, #3b82f6) !important;
        background: #000000 !important;
    }
    [data-theme="dark"] .share-cover-dropzone {
        background: #0c0c0e !important;
        border-color: #27272a !important;
    }
    [data-theme="dark"] .share-cover-dropzone:hover {
        background: #141416 !important;
        border-color: var(--primary-color, #3b82f6) !important;
    }
    [data-theme="dark"] .cover-text-main {
        color: #ffffff !important;
    }
    [data-theme="dark"] .cover-text-sub {
        color: #71717a !important;
    }
    [data-theme="dark"] .share-users-section {
        background: #09090b !important;
        border-color: #1f1f23 !important;
    }
    [data-theme="dark"] .share-users-header {
        border-color: #1f1f23 !important;
    }
    [data-theme="dark"] .share-user-item {
        background: #121214 !important;
        border-color: #222226 !important;
    }
    [data-theme="dark"] .share-user-item:hover {
        background: #1a1a1e !important;
        border-color: #2e2e34 !important;
    }
    [data-theme="dark"] .share-user-name {
        color: #ffffff !important;
    }
    [data-theme="dark"] .share-user-email {
        color: #a1a1aa !important;
    }
    [data-theme="dark"] .share-role-select {
        background: #0c0c0e !important;
        border-color: #27272a !important;
        color: #e4e4e7 !important;
    }
    [data-theme="dark"] .share-role-select:hover, [data-theme="dark"] .share-role-select:focus {
        border-color: var(--primary-color, #3b82f6) !important;
        background: #121214 !important;
    }
    [data-theme="dark"] .share-role-select option {
        background: #0c0c0e !important;
        color: #ffffff !important;
    }
    [data-theme="dark"] .share-owner-badge {
        background: rgba(217, 119, 6, 0.2) !important;
        color: #fbbf24 !important;
        border-color: rgba(217, 119, 6, 0.4) !important;
    }
    [data-theme="dark"] .btn-remove-user {
        background: rgba(239, 68, 68, 0.12) !important;
        border-color: rgba(239, 68, 68, 0.25) !important;
        color: #f87171 !important;
    }
    [data-theme="dark"] .btn-remove-user:hover {
        background: rgba(239, 68, 68, 0.22) !important;
        border-color: rgba(239, 68, 68, 0.4) !important;
    }
    [data-theme="dark"] .share-general-access-card {
        background: #09090b !important;
        border-color: #1f1f23 !important;
    }
    [data-theme="dark"] .access-icon-circle.restricted {
        background: #18181b !important;
        color: #a1a1aa !important;
    }
    [data-theme="dark"] .access-icon-circle.public {
        background: rgba(22, 163, 74, 0.2) !important;
        color: #4ade80 !important;
    }
    [data-theme="dark"] .share-access-select {
        color: #ffffff !important;
    }
    [data-theme="dark"] .share-access-select option {
        background: #0c0c0e !important;
        color: #ffffff !important;
    }
    [data-theme="dark"] .access-desc-text {
        color: #a1a1aa !important;
    }
    [data-theme="dark"] .wb-modal-close {
        color: #a1a1aa !important;
    }
    [data-theme="dark"] .wb-modal-close:hover {
        background: #1f1f23 !important;
        color: #ffffff !important;
    }
    [data-theme="dark"] .wb-btn-cancel {
        color: #e4e4e7 !important;
        border-color: #27272a !important;
        background: transparent !important;
    }
    [data-theme="dark"] .wb-btn-cancel:hover {
        background: #18181b !important;
        color: #ffffff !important;
    }
    [data-theme="dark"] .ts-control {
        background: #0c0c0e !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    [data-theme="dark"] .ts-control input {
        color: #ffffff !important;
    }
    [data-theme="dark"] .ts-dropdown {
        background: #0c0c0e !important;
        border-color: #27272a !important;
        color: #ffffff !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.7) !important;
    }
    [data-theme="dark"] .ts-dropdown .option {
        color: #e4e4e7 !important;
    }
    [data-theme="dark"] .ts-dropdown .option:hover, [data-theme="dark"] .ts-dropdown .active {
        background: #1f1f23 !important;
        color: #ffffff !important;
    }
    [data-theme="dark"] .share-empty-users i {
        color: #3f3f46 !important;
    }
    [data-theme="dark"] .share-empty-users p {
        color: #71717a !important;
    }
</style>

<div class="wb-modal-overlay" id="shareWhiteboardModal">
    <div class="wb-modal share-modal-dialog">
        <!-- Modal Header -->
        <div class="wb-modal-header share-saas-header">
            <div class="share-header-content">
                <div class="share-header-icon">
                    <i class="ph ph-share-network"></i>
                </div>
                <div>
                    <h2 id="share-modal-title-header">Compartir Pizarra</h2>
                    <p class="share-header-sub" id="share-modal-desc">Colabora en tiempo real y gestiona permisos de tu equipo.</p>
                </div>
            </div>
            <button class="wb-modal-close" onclick="closeShareWhiteboardModal()" title="Cerrar"><i class="ph ph-x"></i></button>
        </div>

        <!-- Modal Body -->
        <div class="wb-modal-body share-modal-body-custom">
            <input type="hidden" id="share-wb-id">
            
            <!-- Whiteboard Title -->
            <div class="share-form-group" id="share-title-group">
                <label class="share-label"><i class="ph ph-text-t"></i> Nombre de la pizarra</label>
                <input type="text" id="share-wb-title" class="share-modal-input" placeholder="Ej. Estrategia de Marketing, Wireframe MVP...">
            </div>

            <!-- Cover Image (Optional) -->
            <div class="share-form-group" id="share-pic-group">
                <label class="share-label"><i class="ph ph-image"></i> Portada (Opcional)</label>
                <input type="file" id="share-wb-profile-pic" accept="image/*" class="share-file-hidden" onchange="handleCoverPicChange(event)">
                <div class="share-cover-dropzone" onclick="document.getElementById('share-wb-profile-pic').click()">
                    <div class="cover-preview-box" id="share-cover-preview-box" style="display: none;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <img id="share-cover-preview-img" src="" alt="Portada">
                            <span style="font-size:0.8rem; font-weight:600; color:var(--text-main);" id="share-cover-name">Imagen seleccionada</span>
                        </div>
                        <button type="button" class="btn-remove-cover" onclick="event.stopPropagation(); removeCoverPic();">
                            <i class="ph ph-trash"></i> Quitar
                        </button>
                    </div>
                    <div class="cover-placeholder" id="share-cover-placeholder">
                        <i class="ph ph-cloud-arrow-up"></i>
                        <div>
                            <span class="cover-text-main">Haz clic para subir una portada</span>
                            <span class="cover-text-sub">PNG, JPG, WebP hasta 5MB</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Invite Input Row -->
            <div class="share-form-group">
                <label class="share-label"><i class="ph ph-user-plus"></i> Invitar colaboradores</label>
                <div class="share-invite-row">
                    <div class="share-select-wrap">
                        <select id="share-wb-invite-input" placeholder="Buscar usuario o escribir correo..." autocomplete="off">
                            <option value="">Buscar usuario o escribir correo...</option>
                            <?php foreach($all_users as $u): ?>
                                <option value="USER:<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="share-btn-add" onclick="addShareInvite()">
                        <i class="ph ph-plus-bold"></i>
                        <span>Añadir</span>
                    </button>
                </div>
            </div>

            <!-- Users Section with Access -->
            <div class="share-users-section">
                <div class="share-users-header">
                    <span class="share-label" style="margin-bottom:0;"><i class="ph ph-users"></i> Personas con acceso</span>
                    <span class="share-users-count-badge" id="share-users-count">0</span>
                </div>
                <div class="share-modal-list" id="share-users-list">
                    <!-- Dynamic user rows appended via JS -->
                </div>
            </div>

            <!-- General Access Panel -->
            <div class="share-form-group" style="margin-bottom: 0;">
                <label class="share-label"><i class="ph ph-shield-check"></i> Acceso General</label>
                <div class="share-general-access-card">
                    <div id="access-icon-bg" class="access-icon-circle restricted">
                        <i class="ph ph-lock-key" id="access-icon"></i>
                    </div>
                    <div class="access-info-wrap">
                        <select id="share-wb-access-type" class="share-access-select" onchange="toggleSharePublicRole()">
                            <option value="restricted">Restringido</option>
                            <option value="public">Cualquier persona con el enlace</option>
                        </select>
                        <div id="share-access-desc" class="access-desc-text">Solo los usuarios añadidos pueden abrir este enlace</div>
                    </div>
                    <div id="share-public-role-container" class="access-public-role-wrap" style="display: none;">
                        <select id="share-wb-public-role" class="share-role-select">
                            <option value="viewer">Lector</option>
                            <option value="editor">Editor</option>
                        </select>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Modal Footer -->
        <div class="wb-modal-footer share-saas-footer">
            <button type="button" class="share-btn-copy-link" id="btn-copy-share-link" onclick="copyShareLink()">
                <i class="ph ph-link" id="copy-link-icon"></i>
                <span id="copy-link-text">Copiar enlace</span>
            </button>
            <div class="share-footer-actions">
                <button type="button" class="wb-btn-cancel" onclick="closeShareWhiteboardModal()">Cancelar</button>
                <button type="button" class="wb-btn-save" id="btn-share-save" onclick="submitShareWhiteboard()">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<script>
let shareInviteSelect;
let currentShareUsers = [];
let currentShareMode = 'edit'; // 'create' or 'edit'

document.addEventListener('DOMContentLoaded', () => {
    shareInviteSelect = new TomSelect('#share-wb-invite-input', {
        create: true,
        createFilter: function(input) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input);
        },
        placeholder: 'Buscar usuario o ingresar correo...',
        render: {
            option_create: function(data, escape) {
                return '<div class="create" style="padding: 8px 12px; font-size: 0.85rem;">Invitar correo: <strong>' + escape(data.input) + '</strong>&hellip;</div>';
            }
        }
    });
});

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function handleCoverPicChange(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewBox = document.getElementById('share-cover-preview-box');
            const previewImg = document.getElementById('share-cover-preview-img');
            const placeholder = document.getElementById('share-cover-placeholder');
            const nameEl = document.getElementById('share-cover-name');
            if (previewBox && previewImg && placeholder) {
                previewImg.src = e.target.result;
                if (nameEl) nameEl.innerText = file.name.length > 25 ? file.name.substring(0, 22) + '...' : file.name;
                previewBox.style.display = 'flex';
                placeholder.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);
    }
}

function removeCoverPic() {
    const input = document.getElementById('share-wb-profile-pic');
    if (input) input.value = '';
    const previewBox = document.getElementById('share-cover-preview-box');
    const placeholder = document.getElementById('share-cover-placeholder');
    if (previewBox) previewBox.style.display = 'none';
    if (placeholder) placeholder.style.display = 'flex';
}

function triggerShareToast(msg, icon) {
    if (typeof showToast === 'function') {
        showToast(msg, icon);
    } else {
        let container = document.getElementById('wb-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'wb-toast-container';
            container.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); display: flex; flex-direction: column; gap: 10px; z-index: 9999; pointer-events: none;';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.style.cssText = 'background: #000000; border: 1px solid #27272a; color: #fff; padding: 10px 20px; border-radius: 30px; font-size: 0.95rem; font-weight: 500; box-shadow: 0 4px 15px rgba(0,0,0,0.5); display: flex; align-items: center; gap: 10px; opacity: 0; transform: translateY(-20px); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);';
        toast.innerHTML = `<i class="ph ${icon}" style="font-size: 1.2rem; color: #10b981;"></i> <span>${msg}</span>`;
        container.appendChild(toast);
        toast.offsetHeight;
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-20px)';
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    }
}

function toggleSharePublicRole() {
    const type = document.getElementById('share-wb-access-type').value;
    const roleContainer = document.getElementById('share-public-role-container');
    const desc = document.getElementById('share-access-desc');
    const icon = document.getElementById('access-icon');
    const iconBg = document.getElementById('access-icon-bg');
    
    if (type === 'public') {
        roleContainer.style.display = 'block';
        desc.innerText = 'Cualquier usuario de Internet con el enlace puede acceder';
        icon.className = 'ph ph-globe';
        iconBg.className = 'access-icon-circle public';
    } else {
        roleContainer.style.display = 'none';
        desc.innerText = 'Solo los usuarios añadidos pueden abrir este enlace';
        icon.className = 'ph ph-lock-key';
        iconBg.className = 'access-icon-circle restricted';
    }
}

function openShareWhiteboardModal(mode = 'create', id = null) {
    currentShareMode = mode;
    currentShareUsers = [];
    
    // Reset Form
    document.getElementById('share-wb-id').value = id || '';
    document.getElementById('share-wb-title').value = '';
    removeCoverPic();
    document.getElementById('share-wb-access-type').value = 'restricted';
    document.getElementById('share-wb-public-role').value = 'viewer';
    
    if (shareInviteSelect) {
        shareInviteSelect.clear();
        shareInviteSelect.clearOptions();
        <?php foreach($all_users as $u): ?>
            shareInviteSelect.addOption({value: "USER:<?php echo $u['id']; ?>", text: "<?php echo htmlspecialchars($u['name']); ?>"});
        <?php endforeach; ?>
    }
    
    toggleSharePublicRole();
    renderShareUsersList();
    
    const headerTitle = document.getElementById('share-modal-title-header');
    const headerDesc = document.getElementById('share-modal-desc');
    const btnSave = document.getElementById('btn-share-save');
    const btnCopy = document.getElementById('btn-copy-share-link');
    
    if (mode === 'create') {
        headerTitle.innerText = 'Crear Nueva Pizarra';
        headerDesc.innerText = 'Asigna un nombre, portada y colaboradores para comenzar.';
        btnSave.innerText = 'Crear Pizarra';
        if (btnCopy) btnCopy.style.display = 'none';
        document.getElementById('shareWhiteboardModal').classList.add('show');
    } else {
        headerTitle.innerText = 'Compartir Pizarra';
        headerDesc.innerText = 'Colabora en tiempo real y gestiona permisos de tu equipo.';
        btnSave.innerText = 'Guardar Cambios';
        if (btnCopy) btnCopy.style.display = 'inline-flex';
        
        // Fetch existing data
        fetch('ajax/ajax_whiteboard.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'get_share_info', id: id })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('share-wb-title').value = res.data.title;
                document.getElementById('share-wb-access-type').value = res.data.access_type;
                document.getElementById('share-wb-public-role').value = res.data.public_role;
                currentShareUsers = res.data.users || [];
                toggleSharePublicRole();
                renderShareUsersList();
                document.getElementById('shareWhiteboardModal').classList.add('show');
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'No se pudo cargar la información de compartir.', 'error');
        });
    }
}

function closeShareWhiteboardModal() {
    document.getElementById('shareWhiteboardModal').classList.remove('show');
}

function addShareInvite() {
    const val = shareInviteSelect.getValue();
    if (!val) return;
    
    const role = 'editor';
    
    if (currentShareUsers.find(u => u.id === val)) {
        Swal.fire('Atención', 'Este usuario ya está en la lista de colaboradores', 'warning');
        return;
    }
    
    let name, email;
    if (val.startsWith('USER:')) {
        const option = shareInviteSelect.options[val];
        name = option ? option.text : 'Usuario';
        email = 'Usuario del sistema';
    } else {
        name = val;
        email = val;
    }
    
    currentShareUsers.push({ id: val, name: name, email: email, role: role });
    shareInviteSelect.clear();
    renderShareUsersList();
}

function removeShareUser(index) {
    currentShareUsers.splice(index, 1);
    renderShareUsersList();
}

function changeShareUserRole(index, newRole) {
    if (currentShareUsers[index]) {
        currentShareUsers[index].role = newRole;
    }
}

function renderShareUsersList() {
    const container = document.getElementById('share-users-list');
    const countBadge = document.getElementById('share-users-count');
    if (countBadge) countBadge.innerText = currentShareUsers.length;
    container.innerHTML = '';
    
    if (currentShareUsers.length === 0) {
        container.innerHTML = `
            <div class="share-empty-users">
                <i class="ph ph-users-three"></i>
                <p>No hay colaboradores añadidos aún</p>
            </div>
        `;
        return;
    }
    
    const avatarGradients = [
        'linear-gradient(135deg, #3b82f6, #1d4ed8)',
        'linear-gradient(135deg, #8b5cf6, #6d28d9)',
        'linear-gradient(135deg, #10b981, #047857)',
        'linear-gradient(135deg, #f59e0b, #d97706)',
        'linear-gradient(135deg, #ec4899, #be185d)',
        'linear-gradient(135deg, #06b6d4, #0e7490)'
    ];
    
    currentShareUsers.forEach((u, index) => {
        const initial = (u.name || 'U').charAt(0).toUpperCase();
        const grad = avatarGradients[index % avatarGradients.length];
        const isOwner = u.id === 'OWNER';
        
        let roleHtml = '';
        if (isOwner) {
            roleHtml = `
                <span class="share-owner-badge">
                    <i class="ph-fill ph-crown"></i> Propietario
                </span>
            `;
        } else {
            const roleSelViewer = u.role === 'viewer' ? 'selected' : '';
            const roleSelEditor = u.role === 'editor' ? 'selected' : '';
            roleHtml = `
                <select class="share-role-select" onchange="changeShareUserRole(${index}, this.value)">
                    <option value="viewer" ${roleSelViewer}>Lector</option>
                    <option value="editor" ${roleSelEditor}>Editor</option>
                </select>
            `;
        }
        
        const removeBtnHtml = !isOwner ? `
            <button type="button" class="btn-remove-user" onclick="removeShareUser(${index})" title="Quitar acceso">
                <i class="ph ph-trash"></i>
            </button>
        ` : '<div style="width: 30px;"></div>';

        const item = document.createElement('div');
        item.className = 'share-user-item';
        item.innerHTML = `
            <div class="share-user-info">
                <div class="share-user-avatar" style="background: ${grad};">${initial}</div>
                <div class="share-user-text">
                    <div class="share-user-name">${escapeHtml(u.name)}</div>
                    <div class="share-user-email">${escapeHtml(u.email || '')}</div>
                </div>
            </div>
            <div class="share-user-actions">
                ${roleHtml}
                ${removeBtnHtml}
            </div>
        `;
        container.appendChild(item);
    });
}

function copyShareLink() {
    const id = document.getElementById('share-wb-id').value;
    if (!id && currentShareMode === 'create') {
        Swal.fire('Atención', 'Primero debes crear la pizarra para tener un enlace disponible.', 'info');
        return;
    }
    
    const link = window.location.origin + window.location.pathname + '?module=pizarras&action=view&id=' + id;
    navigator.clipboard.writeText(link).then(() => {
        const btn = document.getElementById('btn-copy-share-link');
        const icon = document.getElementById('copy-link-icon');
        const text = document.getElementById('copy-link-text');
        
        if (btn && icon && text) {
            const prevIcon = icon.className;
            const prevText = text.innerText;
            icon.className = 'ph-bold ph-check';
            text.innerText = '¡Enlace copiado!';
            btn.classList.add('copied');
            
            setTimeout(() => {
                icon.className = prevIcon;
                text.innerText = prevText;
                btn.classList.remove('copied');
            }, 2500);
        }
        triggerShareToast('Enlace copiado al portapapeles', 'ph-link');
    }).catch(() => {
        triggerShareToast('Error al copiar el enlace', 'ph-warning');
    });
}

function submitShareWhiteboard() {
    const id = document.getElementById('share-wb-id').value;
    const title = document.getElementById('share-wb-title').value.trim();
    if (!title) {
        Swal.fire('Atención', 'Por favor ingresa un nombre para la pizarra', 'warning');
        return;
    }
    
    const access_type = document.getElementById('share-wb-access-type').value;
    const public_role = document.getElementById('share-wb-public-role').value;
    const file = document.getElementById('share-wb-profile-pic').files[0];
    
    const formData = new FormData();
    formData.append('action', currentShareMode === 'create' ? 'create_unified' : 'update_unified');
    if (id) formData.append('id', id);
    formData.append('title', title);
    formData.append('access_type', access_type);
    formData.append('public_role', public_role);
    formData.append('users', JSON.stringify(currentShareUsers));
    
    if (file) formData.append('profile_pic', file);
    
    const btnSave = document.getElementById('btn-share-save');
    const originalText = btnSave.innerHTML;
    btnSave.disabled = true;
    btnSave.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
    
    fetch('ajax/ajax_whiteboard.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        btnSave.disabled = false;
        btnSave.innerHTML = originalText;
        if (res.success) {
            triggerShareToast('Cambios guardados correctamente', 'ph-check-circle');
            if (currentShareMode === 'create') {
                setTimeout(() => {
                    window.location.href = 'index.php?module=pizarras&action=view&id=' + res.id;
                }, 800);
            } else {
                closeShareWhiteboardModal();
                if (document.getElementById('wb-title')) {
                    document.getElementById('wb-title').innerText = title;
                }
                setTimeout(() => {
                    window.location.reload();
                }, 800);
            }
        } else {
            Swal.fire('Error', res.error || 'Error al guardar cambios', 'error');
        }
    })
    .catch(err => {
        btnSave.disabled = false;
        btnSave.innerHTML = originalText;
        console.error(err);
        Swal.fire('Error', 'Error de conexión con el servidor', 'error');
    });
}
</script>
