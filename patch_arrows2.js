const fs = require('fs');
const path = 'assets/js/whiteboard.js';
let content = fs.readFileSync(path, 'utf8');

// 1. Fix Arrow Tool Head selectable & styling
const arrowHeadToolTarget = `            currentArrowHead = new fabric.Triangle({
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
                parentArrowId: currentArrowLine.parentArrowId
            });`;

const arrowHeadToolReplacement = `            currentArrowHead = new fabric.Polygon([
                {x: -10, y: -8}, {x: 2, y: 0}, {x: -10, y: 8}, {x: -6, y: 0}
            ], {
                fill: currentColor, 
                left: startX, 
                top: startY,
                originX: 'center',
                originY: 'center',
                selectable: false,
                evented: false,
                isArrowHead: true,
                parentArrowId: currentArrowLine.parentArrowId
            });`;

// 2. Fix Arrow Tool Line selectable
const arrowLineToolTarget = `            currentArrowLine = new fabric.Line(points, {
                strokeWidth: 4,
                fill: currentColor,
                stroke: currentColor,
                originX: 'center',
                originY: 'center',
                selectable: true,
                evented: true, // Ahora sí son clickeables independientemente
                isArrowLine: true,
                parentArrowId: 'arrow_' + Date.now()
            });`;

const arrowLineToolReplacement = `            currentArrowLine = new fabric.Line(points, {
                strokeWidth: 3,
                fill: currentColor,
                stroke: currentColor,
                originX: 'center',
                originY: 'center',
                selectable: true,
                evented: true,
                lockMovementX: true,
                lockMovementY: true,
                lockScalingX: true,
                lockScalingY: true,
                lockRotation: true,
                hasControls: false,
                isArrowLine: true,
                parentArrowId: 'arrow_' + Date.now()
            });`;

// 3. Fix Anchor Arrow Line styling & lock
const anchorLineTarget = `            currentArrowLine = new fabric.Line([startX, startY, startX, startY], {
                strokeWidth: 4,
                fill: currentColor,
                stroke: currentColor,
                originX: 'center',
                originY: 'center',
                selectable: true,
                evented: true,
                isArrowLine: true,
                parentArrowId: arrowId
            });`;

const anchorLineReplacement = `            currentArrowLine = new fabric.Line([startX, startY, startX, startY], {
                strokeWidth: 3,
                fill: currentColor,
                stroke: currentColor,
                originX: 'center',
                originY: 'center',
                selectable: true,
                evented: true,
                lockMovementX: true,
                lockMovementY: true,
                lockScalingX: true,
                lockScalingY: true,
                lockRotation: true,
                hasControls: false,
                isArrowLine: true,
                parentArrowId: arrowId
            });`;

// 4. Fix Anchor Arrow Head styling & lock
const anchorHeadTarget = `            currentArrowHead = new fabric.Triangle({
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
            });`;

const anchorHeadReplacement = `            currentArrowHead = new fabric.Polygon([
                {x: -10, y: -8}, {x: 2, y: 0}, {x: -10, y: 8}, {x: -6, y: 0}
            ], {
                fill: currentColor, 
                left: startX, 
                top: startY,
                originX: 'center',
                originY: 'center',
                selectable: false,
                evented: false,
                isArrowHead: true,
                parentArrowId: arrowId
            });`;

// 5. Restore Select tool after drawing from Anchor
const anchorMouseUpTarget = `                updateMagneticArrows(activeObj);
                if (snappedObj && snappedObj !== activeObj) {
                    updateMagneticArrows(snappedObj);
                }
                
                triggerSync();
            };`;

const anchorMouseUpReplacement = `                updateMagneticArrows(activeObj);
                if (snappedObj && snappedObj !== activeObj) {
                    updateMagneticArrows(snappedObj);
                }
                
                // Return to select tool
                currentTool = 'select';
                document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
                const selectBtn = document.getElementById('tool-select');
                if (selectBtn) selectBtn.classList.add('active');
                canvas.defaultCursor = 'default';
                
                triggerSync();
            };`;
            
// 6. In mouse move of tool and anchor, triangle orientation logic has 90 deg offset, polygon does not need it if drawn pointing right.
// The polygon is pointing right, so angle = Math.atan2(dy, dx) * 180 / Math.PI; is correct without +90.
const angleToolTarget = `            const dx = endX - currentArrowLine.x1;
            const dy = endY - currentArrowLine.y1;
            let angle = Math.atan2(dy, dx) * 180 / Math.PI;
            angle += 90; // Adjust for fabric triangle orientation
            currentArrowHead.set({ angle: angle });`;

const angleToolReplacement = `            const dx = endX - currentArrowLine.x1;
            const dy = endY - currentArrowLine.y1;
            let angle = Math.atan2(dy, dx) * 180 / Math.PI;
            currentArrowHead.set({ angle: angle });`;

const angleAnchorTarget = `                const dx = endX - currentArrowLine.x1;
                const dy = endY - currentArrowLine.y1;
                let angle = Math.atan2(dy, dx) * 180 / Math.PI;
                angle += 90;
                currentArrowHead.set({ angle: angle });`;

const angleAnchorReplacement = `                const dx = endX - currentArrowLine.x1;
                const dy = endY - currentArrowLine.y1;
                let angle = Math.atan2(dy, dx) * 180 / Math.PI;
                currentArrowHead.set({ angle: angle });`;
                
const angleUpdateTarget = `                    const dx = endX - startX;
                    const dy = endY - startY;
                    let angle = Math.atan2(dy, dx) * 180 / Math.PI + 90;
                    head.set({ angle: angle });`;

const angleUpdateReplacement = `                    const dx = endX - startX;
                    const dy = endY - startY;
                    let angle = Math.atan2(dy, dx) * 180 / Math.PI;
                    head.set({ angle: angle });`;

if (content.includes(arrowHeadToolTarget)) { content = content.replace(arrowHeadToolTarget, arrowHeadToolReplacement); console.log("1"); }
if (content.includes(arrowLineToolTarget)) { content = content.replace(arrowLineToolTarget, arrowLineToolReplacement); console.log("2"); }
if (content.includes(anchorLineTarget)) { content = content.replace(anchorLineTarget, anchorLineReplacement); console.log("3"); }
if (content.includes(anchorHeadTarget)) { content = content.replace(anchorHeadTarget, anchorHeadReplacement); console.log("4"); }
if (content.includes(anchorMouseUpTarget)) { content = content.replace(anchorMouseUpTarget, anchorMouseUpReplacement); console.log("5"); }
if (content.includes(angleToolTarget)) { content = content.replace(angleToolTarget, angleToolReplacement); console.log("6"); }
if (content.includes(angleAnchorTarget)) { content = content.replace(angleAnchorTarget, angleAnchorReplacement); console.log("7"); }
if (content.includes(angleUpdateTarget)) { content = content.replace(angleUpdateTarget, angleUpdateReplacement); console.log("8"); }

fs.writeFileSync(path, content);
console.log('patched');
