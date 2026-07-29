<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insertar Video</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #1e293b; color: #f8fafc; height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; }
        
        .card-container {
            width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 24px; position: relative;
        }

        .dropzone {
            width: 100%; max-width: 400px; padding: 40px 20px;
            border: 2px dashed #475569; border-radius: 16px;
            background: #0f172a; text-align: center;
            cursor: pointer; transition: all 0.3s ease;
            display: flex; flex-direction: column; align-items: center; gap: 12px;
        }
        .dropzone:hover, .dropzone.dragover { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
        .dropzone i { font-size: 48px; color: #94a3b8; transition: color 0.3s; }
        .dropzone:hover i, .dropzone.dragover i { color: #3b82f6; }
        .dropzone h3 { font-size: 16px; font-weight: 600; }
        .dropzone p { font-size: 12px; color: #94a3b8; }

        .divider { margin: 24px 0; font-size: 12px; color: #64748b; font-weight: 600; display: flex; align-items: center; width: 100%; max-width: 400px; }
        .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: #334155; margin: 0 12px; }

        .url-input-container { width: 100%; max-width: 400px; display: flex; gap: 8px; }
        .url-input { flex: 1; padding: 12px 16px; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: white; font-size: 14px; outline: none; transition: border 0.2s; }
        .url-input:focus { border-color: #3b82f6; }
        .btn-submit { background: #3b82f6; color: white; border: none; border-radius: 8px; padding: 0 20px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #2563eb; }

        /* Animation overlay */
        .upload-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: #1e293b; display: flex; flex-direction: column; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.3s; z-index: 10;
        }
        .upload-overlay.active { opacity: 1; pointer-events: all; }
        
        .spinner {
            width: 50px; height: 50px; border: 4px solid rgba(59, 130, 246, 0.3); border-top-color: #3b82f6;
            border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .upload-text { font-size: 16px; font-weight: 600; margin-bottom: 8px; }
        .upload-progress { font-size: 14px; color: #94a3b8; }
    </style>
</head>
<body>

<div class="card-container">
    <div class="dropzone" id="dropzone">
        <i class="ph ph-upload-simple"></i>
        <h3>Subir un Video</h3>
        <p>Arrastra y suelta aquí o haz clic para buscar (MP4, WebM)</p>
        <input type="file" id="file-input" accept="video/mp4,video/webm" style="display: none;">
    </div>

    <div class="divider">O PEGA UN ENLACE</div>

    <div class="url-input-container">
        <input type="text" id="url-input" class="url-input" placeholder="URL de YouTube, Spotify...">
        <button class="btn-submit" id="btn-url">Insertar</button>
    </div>
</div>

<div class="upload-overlay" id="upload-overlay">
    <div class="spinner"></div>
    <div class="upload-text" id="upload-text">Subiendo a Google Drive...</div>
    <div class="upload-progress" id="upload-progress">0%</div>
</div>

<script>
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('file-input');
    const urlInput = document.getElementById('url-input');
    const btnUrl = document.getElementById('btn-url');
    const overlay = document.getElementById('upload-overlay');
    const progressText = document.getElementById('upload-progress');
    const statusText = document.getElementById('upload-text');

    const iframeId = new URLSearchParams(window.location.search).get('iframeId');

    // Drag events
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false)
    });
    function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false)
    });
    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false)
    });

    dropzone.addEventListener('drop', handleDrop, false);
    dropzone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', function() {
        if (this.files.length) handleFiles(this.files);
    });

    function handleDrop(e) {
        let dt = e.dataTransfer;
        let files = dt.files;
        handleFiles(files);
    }

    function handleFiles(files) {
        let file = files[0];
        if (!file.type.startsWith('video/')) {
            alert('Por favor selecciona un archivo de video válido.');
            return;
        }
        
        // Obtener dimensiones del video antes de subir
        let videoNode = document.createElement('video');
        videoNode.preload = 'metadata';
        videoNode.onloadedmetadata = function() {
            window.URL.revokeObjectURL(videoNode.src);
            uploadFile(file, videoNode.videoWidth, videoNode.videoHeight);
        };
        videoNode.src = URL.createObjectURL(file);
    }

    function uploadFile(file, vWidth, vHeight) {
        overlay.classList.add('active');
        statusText.innerText = 'Subiendo a Google Drive...';
        
        let formData = new FormData();
        formData.append('video', file);

        let xhr = new XMLHttpRequest();
        xhr.open('POST', 'ajax/upload_whiteboard_video.php', true);

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                let percentComplete = Math.round((e.loaded / e.total) * 100);
                progressText.innerText = percentComplete + '%';
            }
        };

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    let res = JSON.parse(xhr.responseText);
                    if (res.success && res.url) {
                        statusText.innerText = '¡Video Subido!';
                        statusText.style.color = '#10b981';
                        document.querySelector('.spinner').style.display = 'none';
                        
                        setTimeout(() => {
                            sendToParent(res.url, true, vWidth, vHeight);
                        }, 500);
                    } else {
                        throw new Error(res.error || 'Error desconocido');
                    }
                } catch(err) {
                    handleError(err.message);
                }
            } else {
                handleError('Error de red al subir archivo.');
            }
        };
        
        xhr.onerror = () => handleError('Error de conexión.');
        xhr.send(formData);
    }

    function handleError(msg) {
        statusText.innerText = 'Error: ' + msg;
        statusText.style.color = '#ef4444';
        document.querySelector('.spinner').style.display = 'none';
        setTimeout(() => {
            overlay.classList.remove('active');
            statusText.style.color = '';
            document.querySelector('.spinner').style.display = 'block';
            progressText.innerText = '0%';
        }, 3000);
    }

    btnUrl.addEventListener('click', () => {
        const url = urlInput.value.trim();
        if (url) sendToParent(url, false);
    });
    urlInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') btnUrl.click();
    });

    function sendToParent(url, isUploadedVideo, vWidth = null, vHeight = null) {
        // Enviar mensaje al documento padre (whiteboard.js) con la url y el id del iframe a actualizar
        window.parent.postMessage({
            type: 'UPDATE_VIDEO_EMBED',
            iframeId: iframeId,
            url: url,
            isUploadedVideo: isUploadedVideo,
            videoWidth: vWidth,
            videoHeight: vHeight
        }, '*');
    }
</script>

</body>
</html>
