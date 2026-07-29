const fs = require('fs');
const path = 'assets/js/whiteboard.js';
let content = fs.readFileSync(path, 'utf8');

const target1 = `    let syncTimeout;
    function triggerSync() {
        if (isUpdatingFromPusher) return;
        
        clearTimeout(syncTimeout);
        setStatus('Sincronizando...', '#f59e0b');
        syncTimeout = setTimeout(() => {
            broadcastCanvasChange();
            saveStateToDB();
        }, 800); // debounce
    }

    function saveStateToDB() {
        if (isUpdatingFromPusher) return;
        setStatus('Guardando...', '#f59e0b');
        const extraProps = ['id', 'padding', 'splitByGrapheme', 'linkUrl', 'isComment', 'commentId', 'thread', 'isIframe', 'iframeId', 'iframeUrl'];
        const json = JSON.stringify(canvas.toJSON(extraProps));
        fetch('ajax/ajax_whiteboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save', id: boardId, content: json })
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) setStatus('Sincronizado', '#10b981');
            else setStatus('Error al guardar', '#ef4444');
        }).catch(() => setStatus('Error de red', '#ef4444'));
    }

    function broadcastCanvasChange() {
        if (isUpdatingFromPusher) return;

        // Custom fields we want Fabric to include in the JSON
        const extraProps = ['id', 'padding', 'splitByGrapheme', 'linkUrl', 'isComment', 'commentId', 'thread', 'isIframe', 'iframeId', 'iframeUrl'];
        const json = JSON.stringify(canvas.toJSON(extraProps));

        // 1. Guardar en DB mediante AJAX (Throttled or unthrottled depending on logic)
        // A simple fetch can be triggered here if needed.
        
        // 2. Transmitir evento a Pusher (Throttled)
        if (channel && currentUserSessionId) {
            fetch('ajax/ajax_whiteboard_pusher.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'trigger',
                    board_id: boardId,
                    event: 'canvas-updated',
                    data: {
                        userId: currentUserSessionId,
                        canvasData: json
                    }
                })
            }).catch(e => console.error("Error broadcast Pusher", e));
        }
    }`;

const replacement1 = `    let syncTimeout;
    function triggerSync() {
        if (isUpdatingFromPusher) return;
        
        clearTimeout(syncTimeout);
        setStatus('Sincronizando...', '#f59e0b');
        syncTimeout = setTimeout(() => {
            saveStateToDB();
        }, 800); // debounce
    }

    function saveStateToDB() {
        if (isUpdatingFromPusher) return;
        setStatus('Guardando...', '#f59e0b');
        const extraProps = ['id', 'padding', 'splitByGrapheme', 'linkUrl', 'isComment', 'commentId', 'thread', 'isIframe', 'iframeId', 'iframeUrl'];
        const json = JSON.stringify(canvas.toJSON(extraProps));
        fetch('ajax/ajax_whiteboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save', id: boardId, content: json })
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                setStatus('Sincronizado', '#10b981');
                broadcastCanvasChange(); // Trigger pusher after successful save
            } else {
                setStatus('Error al guardar', '#ef4444');
            }
        }).catch(() => setStatus('Error de red', '#ef4444'));
    }

    function broadcastCanvasChange() {
        if (isUpdatingFromPusher) return;

        // 2. Transmitir evento a Pusher ultra-ligero (Solo un "ping")
        if (channel && currentUserSessionId) {
            fetch('ajax/ajax_whiteboard_pusher.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'trigger',
                    board_id: boardId,
                    event: 'canvas-updated',
                    data: {
                        userId: currentUserSessionId
                    }
                })
            }).catch(e => console.error("Error broadcast Pusher", e));
        }
    }`;

const target2 = `    channel.bind('canvas-updated', (data) => {
        if (data.userId === currentUserSessionId) return; // ignore own

        isUpdatingFromPusher = true;
        
        // Remove frame events while loading
        canvas.off('object:added');
        canvas.off('object:modified');
        canvas.off('object:removed');

        canvas.loadFromJSON(data.canvasData, function() {
            canvas.renderAll();
            isUpdatingFromPusher = false;
            setStatus('Recibiendo cambios...', '#3b82f6');
            
            // Re-attach
            canvas.on('object:modified', handleObjectChange);
            canvas.on('object:added', handleObjectChange);
            canvas.on('object:removed', handleObjectChange);
            canvas.on('path:created', handleObjectChange);
            setStatus('Sincronizado', '#10b981');
        });
    });`;

const replacement2 = `    channel.bind('canvas-updated', (data) => {
        if (data.userId === currentUserSessionId) return; // ignore own

        isUpdatingFromPusher = true;
        setStatus('Recibiendo cambios...', '#3b82f6');
        
        // Desconectar eventos temporalmente
        canvas.off('object:added');
        canvas.off('object:modified');
        canvas.off('object:removed');
        canvas.off('path:created');

        // Descargar estado mas reciente desde la DB
        fetch('ajax/ajax_whiteboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'load', id: boardId })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.content) {
                canvas.loadFromJSON(res.content, function() {
                    canvas.renderAll();
                    
                    // Reconectar eventos
                    canvas.on('object:modified', handleObjectChange);
                    canvas.on('object:added', handleObjectChange);
                    canvas.on('object:removed', handleObjectChange);
                    canvas.on('path:created', handleObjectChange);
                    
                    isUpdatingFromPusher = false;
                    setStatus('Sincronizado', '#10b981');
                    saveHistoryState();
                });
            } else {
                isUpdatingFromPusher = false;
                setStatus('Error al sincronizar', '#ef4444');
                // Reconectar de todos modos
                canvas.on('object:modified', handleObjectChange);
                canvas.on('object:added', handleObjectChange);
                canvas.on('object:removed', handleObjectChange);
                canvas.on('path:created', handleObjectChange);
            }
        })
        .catch(err => {
            isUpdatingFromPusher = false;
            setStatus('Error de red', '#ef4444');
            // Reconectar de todos modos
            canvas.on('object:modified', handleObjectChange);
            canvas.on('object:added', handleObjectChange);
            canvas.on('object:removed', handleObjectChange);
            canvas.on('path:created', handleObjectChange);
        });
    });`;

if (content.includes(target1)) {
    content = content.replace(target1, replacement1);
    console.log("Replacement 1 successful");
} else {
    console.error("Target 1 not found");
}

if (content.includes(target2)) {
    content = content.replace(target2, replacement2);
    console.log("Replacement 2 successful");
} else {
    console.error("Target 2 not found");
}

fs.writeFileSync(path, content);
console.log("File patched.");
