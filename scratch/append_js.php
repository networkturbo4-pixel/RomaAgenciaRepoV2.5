<?php
$js = <<<JS

/* ==================================================================
   Módulo de Mensajes - Lógica Nueva (Drive, Lightbox)
   ================================================================== */
(function() {
    'use strict';
    
    // Lightbox Logic
    const lightbox = document.getElementById('chat-multimedia-lightbox');
    const lightboxBody = document.getElementById('lightbox-body');
    const lightboxTitle = document.getElementById('lightbox-title');
    const btnLightboxClose = document.getElementById('btn-lightbox-close');
    const btnLightboxDownload = document.getElementById('btn-lightbox-download');
    
    let currentFileUrl = '';
    let currentFileName = '';

    function openLightbox(url, type, name) {
        currentFileUrl = url;
        currentFileName = name;
        if(lightboxTitle) lightboxTitle.innerText = name || 'Archivo';
        
        if(lightboxBody) lightboxBody.innerHTML = ''; // clear
        
        if (type === 'image') {
            const img = document.createElement('img');
            img.src = url;
            if(lightboxBody) lightboxBody.appendChild(img);
        } else if (type === 'video') {
            const vid = document.createElement('video');
            vid.src = url;
            vid.controls = true;
            vid.autoplay = true;
            if(lightboxBody) lightboxBody.appendChild(vid);
        } else if (type === 'pdf') {
            const iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.style.width = '80vw';
            iframe.style.height = '80vh';
            iframe.style.border = 'none';
            if(lightboxBody) lightboxBody.appendChild(iframe);
        }
        
        if(lightbox) lightbox.style.display = 'flex';
    }

    if (btnLightboxClose) {
        btnLightboxClose.addEventListener('click', () => {
            if(lightbox) lightbox.style.display = 'none';
            if(lightboxBody) lightboxBody.innerHTML = '';
        });
    }

    if (btnLightboxDownload) {
        btnLightboxDownload.addEventListener('click', () => {
            const a = document.createElement('a');
            a.href = currentFileUrl;
            a.download = currentFileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        });
    }

    // Intercept clicks on chat messages to open Lightbox
    document.addEventListener('click', function(e) {
        if (e.target.tagName === 'IMG' && e.target.closest('.chat-messages')) {
            openLightbox(e.target.src, 'image', 'Imagen');
        }
    });

    // Drive Picker Logic
    const btnSelectDrive = document.getElementById('btn-select-drive-folder');
    const drivePickerModal = document.getElementById('drivePickerModal');
    let bsDriveModal = null;
    
    if (btnSelectDrive && drivePickerModal) {
        bsDriveModal = new bootstrap.Modal(drivePickerModal);
        
        btnSelectDrive.addEventListener('click', () => {
            loadDriveFolder('root');
            bsDriveModal.show();
        });
    }

    async function loadDriveFolder(folderId) {
        const listContainer = document.getElementById('drive-picker-list');
        if(!listContainer) return;
        listContainer.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>';
        
        const fd = new FormData();
        fd.append('action', 'list');
        fd.append('folderId', folderId);
        
        try {
            const resp = await fetch('modules/drive/ajax_drive.php', { method: 'POST', body: fd });
            const data = await resp.json();
            
            listContainer.innerHTML = '';
            if (data.success && data.files) {
                const folders = data.files.filter(f => f.mimeType === 'application/vnd.google-apps.folder');
                if (folders.length === 0) {
                    listContainer.innerHTML = '<div class="text-center text-muted p-4">Carpeta vacía</div>';
                }
                
                folders.forEach(f => {
                    const div = document.createElement('div');
                    div.className = 'd-flex align-items-center p-2 border-bottom';
                    div.style.cursor = 'pointer';
                    div.innerHTML = `<i class="ph-fill ph-folder text-warning" style="font-size:1.5rem; margin-right:0.5rem;"></i> <span>\${f.name}</span>`;
                    
                    div.addEventListener('click', () => {
                        document.querySelectorAll('.drive-folder-item').forEach(el => el.style.background = 'transparent');
                        div.style.background = 'var(--bg-color)';
                        div.classList.add('drive-folder-item');
                        
                        document.getElementById('crs-drive-folder-id').value = f.id;
                        document.getElementById('crs-drive-folder-name').value = f.name;
                    });
                    
                    div.addEventListener('dblclick', () => {
                        loadDriveFolder(f.id);
                    });
                    
                    listContainer.appendChild(div);
                });
            } else {
                listContainer.innerHTML = `<div class="text-danger p-2">\${data.error || 'Error cargando'}</div>`;
            }
        } catch (e) {
            console.error(e);
            listContainer.innerHTML = '<div class="text-danger p-2">Error de conexión</div>';
        }
    }

    document.getElementById('btn-confirm-drive-folder')?.addEventListener('click', async () => {
        const folderId = document.getElementById('crs-drive-folder-id').value;
        const channelId = window.currentChannelId || (document.querySelector('.channel-item.active') ? document.querySelector('.channel-item.active').dataset.id : null);
        
        if (folderId && channelId && bsDriveModal) {
            bsDriveModal.hide();
            // Guardar folder en ajax_mensajes (o ajax)
            const fd = new FormData();
            fd.append('action', 'save_drive_folder');
            fd.append('channel_id', channelId);
            fd.append('folder_id', folderId);
            
            try {
                const resp = await fetch('modules/chat/ajax.php', { method: 'POST', body: fd });
                const data = await resp.json();
                if(data.success) {
                    Swal.fire({title: 'Guardado', text: 'Carpeta de Drive vinculada', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000});
                }
            } catch (e) {
                console.error(e);
            }
        }
    });

})();
JS;

file_put_contents('c:/xampp/htdocs/CESARMENDOZA/modules/chat/chat.js', "\n" . $js, FILE_APPEND);
echo "JS appended.\n";
?>
