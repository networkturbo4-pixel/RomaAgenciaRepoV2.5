<div class="modal-overlay" id="global-drive-modal">
    <div class="modal-content drive-modal-responsive" style="display: flex; flex-direction: column; padding: 0; overflow: hidden; background: var(--bg-surface, #1e293b);">
        <div class="modal-header drive-modal-header-responsive" style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color, #334155); display: flex; justify-content: space-between; align-items: center; background: transparent;">
            <h2 style="display: flex; align-items: center; gap: 0.75rem; margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--text-main, #f8fafc);">
                <i class="ph ph-google-drive-logo" style="color: #3b82f6; font-size: 1.5rem;"></i> Explorador de Archivos
            </h2>
            <button class="btn-icon" onclick="document.getElementById('global-drive-modal').classList.remove('active')" style="background: transparent; border: none; font-size: 1.5rem; color: var(--text-muted, #94a3b8); cursor: pointer;">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="modal-body" style="padding: 0; flex: 1; overflow: hidden; background: transparent;" id="global-drive-container">
            <!-- Drive Explorer UI will be injected here by JS -->
        </div>
    </div>
</div>

<style>
    /* Estilos Responsivos y Forzado de Colores */
    .drive-modal-responsive {
        width: 90%;
        max-width: 1000px;
        height: 85vh;
        border-radius: 12px;
    }
    
    #global-drive-container .drive-explorer,
    #global-drive-container .drive-grid-container,
    #global-drive-container .drive-header {
        background: transparent !important;
        border-radius: 0 !important;
    }

    #global-drive-container .drive-breadcrumb-item {
        color: var(--text-main, #f8fafc) !important;
    }
    
    #global-drive-container .drive-breadcrumb-separator {
        color: var(--text-muted, #94a3b8) !important;
    }

    @media (max-width: 768px) {
        .drive-modal-responsive {
            width: 100%;
            height: 100vh;
            max-height: 100vh;
            border-radius: 0;
            margin: 0;
        }
        .drive-modal-header-responsive {
            padding: 1rem !important;
        }
        #global-drive-container .drive-header {
            padding: 1rem !important;
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        #global-drive-container .drive-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof DriveExplorer !== 'undefined') {
            DriveExplorer.init({
                containerId: 'global-drive-container',
                rootFolderId: '1xC-3ZPK0mDew934BNB5hgHbfjlCg8PLR', // Carpeta EQUIPO (Global)
                readonly: false,
                lazyLoad: true
            });
        }
    });
</script>
