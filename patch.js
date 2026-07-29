const fs = require('fs');

const path = 'assets/js/whiteboard.js';
let content = fs.readFileSync(path, 'utf8');

const target1 = `    // Helpers para Flechas Magnéticas
    function ensureId(obj) {
        if (!obj) return null;
        if (!obj.id) obj.set('id', 'obj_' + Date.now() + Math.random().toString(36).substr(2, 5));
        return obj.id;
    }

    function getClosestObjectCenter(pointer, excludeObjects = []) {
        let closest = null;
        let minDist = 80; // Distancia de snapping
        
        canvas.getObjects().forEach(obj => {
            if (obj.isArrowLine || obj.isArrowHead || obj.isArrowText || obj.isFrame || obj.isComment || excludeObjects.includes(obj)) return;
            const center = obj.getCenterPoint();
            const dist = Math.sqrt(Math.pow(center.x - pointer.x, 2) + Math.pow(center.y - pointer.y, 2));
            if (dist < minDist) {
                minDist = dist;
                closest = obj;
            }
        });
        return closest;
    }

    function updateMagneticArrows(movedObj) {
        if (!movedObj || !movedObj.id) return;
        const center = movedObj.getCenterPoint();
        
        canvas.getObjects().forEach(line => {
            if (!line.isArrowLine) return;
            
            let needsUpdate = false;
            if (line.fromId === movedObj.id) {
                line.set({ x1: center.x, y1: center.y });
                needsUpdate = true;
            }
            if (line.toId === movedObj.id) {
                line.set({ x2: center.x, y2: center.y });
                needsUpdate = true;
            }
            
            if (needsUpdate) {
                const arrowId = line.parentArrowId;
                const head = canvas.getObjects().find(o => o.isArrowHead && o.parentArrowId === arrowId);
                const text = canvas.getObjects().find(o => o.isArrowText && o.parentArrowId === arrowId);
                
                if (head) {
                    head.set({ left: line.x2, top: line.y2 });
                    const dx = line.x2 - line.x1;
                    const dy = line.y2 - line.y1;
                    let angle = Math.atan2(dy, dx) * 180 / Math.PI + 90;
                    head.set({ angle: angle });
                    head.setCoords();
                }
                if (text) {
                    const midX = (line.x1 + line.x2) / 2;
                    const midY = (line.y1 + line.y2) / 2;
                    text.set({ left: midX, top: midY });
                    text.setCoords();
                }
                line.setCoords();
            }
        });
    }`;

const replacement1 = `    // Helpers para Flechas Magnéticas
    function ensureId(obj) {
        if (!obj) return null;
        if (!obj.id) obj.set('id', 'obj_' + Date.now() + Math.random().toString(36).substr(2, 5));
        return obj.id;
    }

    function getLineBoundingBoxIntersection(x1, y1, x2, y2, obj) {
        if (!obj.aCoords) obj.setCoords();
        const minX = Math.min(obj.aCoords.tl.x, obj.aCoords.tr.x, obj.aCoords.bl.x, obj.aCoords.br.x);
        const maxX = Math.max(obj.aCoords.tl.x, obj.aCoords.tr.x, obj.aCoords.bl.x, obj.aCoords.br.x);
        const minY = Math.min(obj.aCoords.tl.y, obj.aCoords.tr.y, obj.aCoords.bl.y, obj.aCoords.br.y);
        const maxY = Math.max(obj.aCoords.tl.y, obj.aCoords.tr.y, obj.aCoords.bl.y, obj.aCoords.br.y);

        const intersections = [];
        function lineIntersect(x3, y3, x4, y4) {
            const denom = (y4-y3)*(x2-x1) - (x4-x3)*(y2-y1);
            if (denom === 0) return null;
            const ua = ((x4-x3)*(y1-y3) - (y4-y3)*(x1-x3))/denom;
            const ub = ((x2-x1)*(y1-y3) - (y2-y1)*(x1-x3))/denom;
            if (ua >= 0 && ua <= 1 && ub >= 0 && ub <= 1) {
                return { x: x1 + ua*(x2-x1), y: y1 + ua*(y2-y1) };
            }
            return null;
        }

        let p = lineIntersect(minX, minY, maxX, minY);
        if (p) intersections.push(p);
        p = lineIntersect(minX, maxY, maxX, maxY);
        if (p) intersections.push(p);
        p = lineIntersect(minX, minY, minX, maxY);
        if (p) intersections.push(p);
        p = lineIntersect(maxX, minY, maxX, maxY);
        if (p) intersections.push(p);

        if (intersections.length > 0) {
            intersections.sort((a,b) => {
                const distA = Math.pow(a.x - x1, 2) + Math.pow(a.y - y1, 2);
                const distB = Math.pow(b.x - x1, 2) + Math.pow(b.y - y1, 2);
                return distA - distB;
            });
            return intersections[0];
        }
        return { x: x2, y: y2 };
    }

    function getClosestObjectCenter(pointer, excludeObjects = []) {
        let closest = null;
        let minDist = 80;
        
        canvas.getObjects().forEach(obj => {
            if (obj.isArrowLine || obj.isArrowHead || obj.isArrowText || obj.isFrame || obj.isComment || excludeObjects.includes(obj)) return;
            const center = obj.getCenterPoint();
            const dist = Math.sqrt(Math.pow(center.x - pointer.x, 2) + Math.pow(center.y - pointer.y, 2));
            if (dist < minDist) {
                minDist = dist;
                closest = obj;
            }
        });
        return closest;
    }

    function updateMagneticArrows(movedObj) {
        if (!movedObj || !movedObj.id) return;
        
        canvas.getObjects().forEach(line => {
            if (!line.isArrowLine) return;
            
            let needsUpdate = false;
            if (line.fromId === movedObj.id || line.toId === movedObj.id) {
                needsUpdate = true;
            }
            
            if (needsUpdate) {
                const fromObj = canvas.getObjects().find(o => o.id === line.fromId);
                const toObj = canvas.getObjects().find(o => o.id === line.toId);
                
                let startX = line.x1, startY = line.y1;
                let endX = line.x2, endY = line.y2;
                
                if (fromObj && toObj) {
                    const c1 = fromObj.getCenterPoint();
                    const c2 = toObj.getCenterPoint();
                    const p1 = getLineBoundingBoxIntersection(c2.x, c2.y, c1.x, c1.y, fromObj);
                    const p2 = getLineBoundingBoxIntersection(c1.x, c1.y, c2.x, c2.y, toObj);
                    startX = p1.x; startY = p1.y;
                    endX = p2.x; endY = p2.y;
                } else if (fromObj) {
                    const c1 = fromObj.getCenterPoint();
                    const p1 = getLineBoundingBoxIntersection(endX, endY, c1.x, c1.y, fromObj);
                    startX = p1.x; startY = p1.y;
                } else if (toObj) {
                    const c2 = toObj.getCenterPoint();
                    const p2 = getLineBoundingBoxIntersection(startX, startY, c2.x, c2.y, toObj);
                    endX = p2.x; endY = p2.y;
                }
                
                line.set({ x1: startX, y1: startY, x2: endX, y2: endY });
                
                const arrowId = line.parentArrowId;
                const head = canvas.getObjects().find(o => o.isArrowHead && o.parentArrowId === arrowId);
                const text = canvas.getObjects().find(o => o.isArrowText && o.parentArrowId === arrowId);
                
                if (head) {
                    head.set({ left: endX, top: endY });
                    const dx = endX - startX;
                    const dy = endY - startY;
                    let angle = Math.atan2(dy, dx) * 180 / Math.PI + 90;
                    head.set({ angle: angle });
                    head.setCoords();
                }
                if (text) {
                    const midX = (startX + endX) / 2;
                    const midY = (startY + endY) / 2;
                    text.set({ left: midX, top: midY });
                    text.setCoords();
                }
                line.setCoords();
            }
        });
    }`;

if (content.includes(target1)) {
    content = content.replace(target1, replacement1);
    console.log("Replacement 1 successful");
} else {
    console.error("Target 1 not found");
}

const target2 = `            // Snapping inicial
            const snappedObj = getClosestObjectCenter(pointer);
            let startX = pointer.x;
            let startY = pointer.y;
            if (snappedObj) {
                const center = snappedObj.getCenterPoint();
                startX = center.x;
                startY = center.y;
                arrowStartTarget = ensureId(snappedObj);
            } else {
                arrowStartTarget = null;
            }`;

const replacement2 = `            // Snapping inicial
            const snappedObj = getClosestObjectCenter(pointer);
            let startX = pointer.x;
            let startY = pointer.y;
            if (snappedObj) {
                const center = snappedObj.getCenterPoint();
                const pt = getLineBoundingBoxIntersection(pointer.x, pointer.y, center.x, center.y, snappedObj);
                startX = pt.x;
                startY = pt.y;
                arrowStartTarget = ensureId(snappedObj);
            } else {
                arrowStartTarget = null;
            }`;

if (content.includes(target2)) {
    content = content.replace(target2, replacement2);
    console.log("Replacement 2 successful");
} else {
    console.error("Target 2 not found");
}

const target3 = `            // Snapping final
            const snappedObj = getClosestObjectCenter(pointer);
            let endX = pointer.x;
            let endY = pointer.y;
            if (snappedObj) {
                const center = snappedObj.getCenterPoint();
                endX = center.x;
                endY = center.y;
            }`;

const replacement3 = `            // Snapping final
            const snappedObj = getClosestObjectCenter(pointer);
            let endX = pointer.x;
            let endY = pointer.y;
            if (snappedObj) {
                const center = snappedObj.getCenterPoint();
                const pt = getLineBoundingBoxIntersection(currentArrowLine.x1, currentArrowLine.y1, center.x, center.y, snappedObj);
                endX = pt.x;
                endY = pt.y;
            }`;

if (content.includes(target3)) {
    content = content.replace(target3, replacement3);
    console.log("Replacement 3 successful");
} else {
    console.error("Target 3 not found");
}

fs.writeFileSync(path, content);
console.log("File patched.");
