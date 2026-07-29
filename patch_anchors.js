const fs = require('fs');
const path = 'assets/js/whiteboard.js';
let content = fs.readFileSync(path, 'utf8');

const target1 = `    canvas.on('selection:created', updateContextMenuPosition);
    canvas.on('selection:updated', updateContextMenuPosition);
    canvas.on('selection:cleared', hideContextMenu);`;

const replacement1 = `    // --- Anchor Points (Quick Connectors) ---
    const anchorsContainer = document.createElement('div');
    anchorsContainer.id = 'wb-anchors';
    anchorsContainer.style.cssText = 'position: absolute; top: 0; left: 0; pointer-events: none; z-index: 60;';
    document.getElementById('canvas-wrapper').appendChild(anchorsContainer);

    const createAnchor = (pos) => {
        const dot = document.createElement('div');
        dot.className = 'wb-anchor-dot';
        dot.dataset.pos = pos;
        dot.style.cssText = \`
            position: absolute; width: 14px; height: 14px; background: #3b82f6; 
            border: 2px solid #fff; border-radius: 50%; 
            transform: translate(-50%, -50%); cursor: crosshair;
            pointer-events: auto; display: none; box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            transition: transform 0.1s;
        \`;
        dot.onmouseenter = () => dot.style.transform = 'translate(-50%, -50%) scale(1.3)';
        dot.onmouseleave = () => dot.style.transform = 'translate(-50%, -50%) scale(1)';
        
        dot.addEventListener('mousedown', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            const activeObj = canvas.getActiveObject();
            if (!activeObj) return;
            
            currentTool = 'arrow';
            document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
            const arrowBtn = document.getElementById('tool-arrow');
            if (arrowBtn) arrowBtn.classList.add('active');

            ensureId(activeObj);
            
            isDrawingArrow = true;
            arrowStartTarget = activeObj.id;

            const rect = dot.getBoundingClientRect();
            const canvasRect = canvas.getElement().getBoundingClientRect();
            const pointerX = rect.left + rect.width / 2 - canvasRect.left;
            const pointerY = rect.top + rect.height / 2 - canvasRect.top;
            
            const vpt = canvas.viewportTransform;
            const startX = (pointerX - vpt[4]) / vpt[0];
            const startY = (pointerY - vpt[5]) / vpt[3];

            const arrowId = 'arrow_' + Date.now();

            currentArrowLine = new fabric.Line([startX, startY, startX, startY], {
                strokeWidth: 4,
                fill: currentColor,
                stroke: currentColor,
                originX: 'center',
                originY: 'center',
                selectable: true,
                evented: true,
                isArrowLine: true,
                parentArrowId: arrowId
            });
            
            currentArrowHead = new fabric.Triangle({
                width: 15, 
                height: 15, 
                fill: currentColor, 
                left: startX, 
                top: startY,
                originX: 'center',
                originY: 'center',
                selectable: true,
                evented: true,
                isArrowHead: true,
                parentArrowId: arrowId
            });
            
            canvas.add(currentArrowLine, currentArrowHead);
            
            const onMouseMove = (ev) => {
                const pointer = canvas.getPointer(ev);
                
                const snappedObj = getClosestObjectCenter(pointer);
                let endX = pointer.x;
                let endY = pointer.y;
                if (snappedObj && snappedObj !== activeObj) {
                    const center = snappedObj.getCenterPoint();
                    const pt = getLineBoundingBoxIntersection(currentArrowLine.x1, currentArrowLine.y1, center.x, center.y, snappedObj);
                    endX = pt.x;
                    endY = pt.y;
                }

                currentArrowLine.set({ x2: endX, y2: endY });
                currentArrowHead.set({ left: endX, top: endY });
                
                const dx = endX - currentArrowLine.x1;
                const dy = endY - currentArrowLine.y1;
                let angle = Math.atan2(dy, dx) * 180 / Math.PI;
                angle += 90;
                currentArrowHead.set({ angle: angle });
                
                canvas.requestRenderAll();
            };
            
            const onMouseUp = (ev) => {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                
                isDrawingArrow = false;
                
                const pointer = canvas.getPointer(ev);
                const snappedObj = getClosestObjectCenter(pointer);
                let arrowEndTarget = null;
                if (snappedObj && snappedObj !== activeObj) {
                    arrowEndTarget = ensureId(snappedObj);
                }
                
                currentArrowLine.set({
                    fromId: arrowStartTarget,
                    toId: arrowEndTarget
                });
                
                updateMagneticArrows(activeObj);
                if (snappedObj && snappedObj !== activeObj) {
                    updateMagneticArrows(snappedObj);
                }
                
                triggerSync();
            };
            
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });
        
        anchorsContainer.appendChild(dot);
        return dot;
    };

    const anchors = {
        top: createAnchor('top'),
        right: createAnchor('right'),
        bottom: createAnchor('bottom'),
        left: createAnchor('left')
    };

    function updateAnchorsPosition() {
        const activeObj = canvas.getActiveObject();
        if (!activeObj || activeObj.type === 'activeSelection' || activeObj.isArrowLine || activeObj.isArrowText || activeObj.isArrowHead) {
            Object.values(anchors).forEach(a => a.style.display = 'none');
            return;
        }

        const boundingRect = activeObj.getBoundingRect();
        
        const topX = boundingRect.left + boundingRect.width / 2;
        const topY = boundingRect.top;
        
        const rightX = boundingRect.left + boundingRect.width;
        const rightY = boundingRect.top + boundingRect.height / 2;
        
        const bottomX = boundingRect.left + boundingRect.width / 2;
        const bottomY = boundingRect.top + boundingRect.height;
        
        const leftX = boundingRect.left;
        const leftY = boundingRect.top + boundingRect.height / 2;
        
        anchors.top.style.left = topX + 'px';
        anchors.top.style.top = (topY - 12) + 'px';
        anchors.top.style.display = 'block';

        anchors.right.style.left = (rightX + 12) + 'px';
        anchors.right.style.top = rightY + 'px';
        anchors.right.style.display = 'block';

        anchors.bottom.style.left = bottomX + 'px';
        anchors.bottom.style.top = (bottomY + 12) + 'px';
        anchors.bottom.style.display = 'block';

        anchors.left.style.left = (leftX - 12) + 'px';
        anchors.left.style.top = leftY + 'px';
        anchors.left.style.display = 'block';
    }

    canvas.on('selection:created', () => { updateContextMenuPosition(); updateAnchorsPosition(); });
    canvas.on('selection:updated', () => { updateContextMenuPosition(); updateAnchorsPosition(); });
    canvas.on('selection:cleared', () => { hideContextMenu(); Object.values(anchors).forEach(a => a.style.display = 'none'); });`;

const target2 = `    canvas.on('object:moving', function(e) {
        hideContextMenu();`;

const replacement2 = `    canvas.on('object:moving', function(e) {
        hideContextMenu();
        updateAnchorsPosition();`;
        
const target3 = `    canvas.on('object:scaling', hideContextMenu);
    canvas.on('object:rotating', hideContextMenu);
    
    canvas.on('mouse:up', () => {
        if (canvas.getActiveObject()) updateContextMenuPosition();
    });`;
    
const replacement3 = `    canvas.on('object:scaling', () => { hideContextMenu(); updateAnchorsPosition(); });
    canvas.on('object:rotating', () => { hideContextMenu(); updateAnchorsPosition(); });
    
    canvas.on('mouse:up', () => {
        if (canvas.getActiveObject()) { updateContextMenuPosition(); updateAnchorsPosition(); }
    });`;

if (content.includes(target1)) { content = content.replace(target1, replacement1); console.log('1'); }
if (content.includes(target2)) { content = content.replace(target2, replacement2); console.log('2'); }
if (content.includes(target3)) { content = content.replace(target3, replacement3); console.log('3'); }

fs.writeFileSync(path, content);
console.log('patched');
