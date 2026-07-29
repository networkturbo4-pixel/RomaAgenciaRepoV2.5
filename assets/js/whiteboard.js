// assets/js/whiteboard.js
(function() {
    if (typeof window.WHITEBOARD_ID === 'undefined') {
        console.error("WHITEBOARD_ID no está definido.");
        return;
    }

    const boardId = window.WHITEBOARD_ID;
    
    // 1. Inicializar Fabric.js
    const canvasContainer = document.getElementById('canvas-wrapper');
    const canvasElement = document.getElementById('whiteboard');
    
    // Configurar tamaño inicial
    canvasElement.width = canvasContainer.clientWidth;
    canvasElement.height = canvasContainer.clientHeight;
    
    const isViewer = window.USER_IS_VIEWER === true;

    const canvas = new fabric.Canvas('whiteboard', {
        isDrawingMode: false,
        backgroundColor: 'transparent',
        selection: !isViewer,
        preserveObjectStacking: true,
        targetFindTolerance: 2, // Reducido para evitar selección por error al hacer clic en vacío
        perPixelTargetFind: true // Más preciso (evita clics en esquinas invisibles)
    });

    // Función auxiliar para obtener el centro de la vista actual
    function getViewportCenter(canvasObj) {
        const vpt = canvasObj.viewportTransform;
        const zoom = canvasObj.getZoom();
        const canvasEl = canvasObj.getElement();
        const centerX = (-vpt[4] + canvasEl.clientWidth / 2) / zoom;
        const centerY = (-vpt[5] + canvasEl.clientHeight / 2) / zoom;
        return { x: centerX, y: centerY };
    }

    if (isViewer) {
        // Ocultar botones que modifican la pizarra, dejar solo Mano, Cursor y Zoom
        const toolsToHide = [
            'tool-draw', 'tool-highlighter', 'tool-eraser', 'tool-text',
            'tool-sticky', 'tool-arrow', 'tool-shape', 'tool-frame',
            'tool-embed', 'wb-color', 'btn-manual-save', 'btn-presentation'
        ];
        toolsToHide.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });

        // Hide specific panels
        const sb = document.querySelector('.wb-sidebar-toggle-group');
        if (sb) sb.style.display = 'none';
        
        // Hide separator lines in toolbar
        document.querySelectorAll('.wb-floating-toolbar > div[style*="width: 1px"]').forEach(sep => {
            sep.style.display = 'none';
        });
        
        // El lector no debe ver el boton de compartir tampoco, que está en top-right. 
        // Si hay un botón de compartir (ej. abrirShareWhiteboardModal), está oculto por PHP para viewers normalmente, pero por si acaso.
    }
    
    const CANVAS_EXTRA_PROPS = [
        'id', 'padding', 'splitByGrapheme', 'linkUrl', 'isComment', 'commentId', 
        'thread', 'isIframe', 'iframeId', 'iframeUrl', 'isFrame', 'linkTo',
        'locked', 'lockMovementX', 'lockMovementY', 'lockScalingX', 'lockScalingY', 
        'lockRotation', 'hasControls', 'hoverCursor', 'isArrowLine', 'isArrowHead', 
        'isArrowText', 'parentArrowId', 'fromId', 'toId', 'isShape', 'isComponent',
        'pdfFileName'
    ];

    // Lazy Loading Scanner
    setInterval(() => {
        if (!canvas) return;
        
        function getLazyImages(objects) {
            let images = [];
            objects.forEach(obj => {
                if (obj.type === 'image' && obj._isLazy && !obj._isLoaded) {
                    images.push(obj);
                } else if (obj.type === 'group' && obj.getObjects) {
                    images = images.concat(getLazyImages(obj.getObjects()));
                }
            });
            return images;
        }

        getLazyImages(canvas.getObjects()).forEach(img => {
            // For grouped objects, we might want to check the group's visibility instead,
            // but isOnScreen() generally works or we can check the parent group.
            const target = img.group || img;
            if (target.isOnScreen()) {
                img._isLoaded = true;
                img.setSrc(img._originalSrc, () => {
                    if (img.group) {
                        img.group.set('dirty', true);
                        img.group.setCoords();
                    }
                    canvas.requestRenderAll();
                }, { crossOrigin: 'anonymous' });
            }
        });
    }, 300);

    // Override fabric.Image.fromObject to enable lazy loading
    const originalImageFromObject = fabric.Image.fromObject;
    fabric.Image.fromObject = function(object, callback) {
        if (object.src && (object.src.startsWith('data:') || object.src.includes('avatar'))) {
            return originalImageFromObject(object, callback);
        }
        
        const originalSrc = object.src;
        object.src = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='; // Transparent pixel
        
        fabric.util.loadImage(object.src, function(img) {
            const instance = new fabric.Image(img, object);
            instance._originalSrc = originalSrc;
            instance._isLazy = true;
            instance._isLoaded = false;
            callback && callback(instance);
        }, null, object.crossOrigin);
    };

    // Override fabric.Image.prototype.toObject to prevent saving transparent pixels
    const originalImageToObject = fabric.Image.prototype.toObject;
    fabric.Image.prototype.toObject = function(propertiesToInclude) {
        const obj = originalImageToObject.call(this, propertiesToInclude);
        if (this._isLazy && !this._isLoaded && this._originalSrc) {
            obj.src = this._originalSrc;
        }
        return obj;
    };

    function enforceLocks() {
        canvas.getObjects().forEach(obj => {
            if (obj.locked || isViewer) {
                obj.set({
                    lockMovementX: true,
                    lockMovementY: true,
                    lockScalingX: true,
                    lockScalingY: true,
                    lockRotation: true,
                    hasControls: false,
                    selectable: !isViewer,
                    evented: !isViewer,
                    hoverCursor: isViewer ? 'default' : 'not-allowed',
                    editable: !isViewer
                });
            }
        });
    }
    
    window.addEventListener('resize', () => {
        canvas.setWidth(canvasContainer.clientWidth);
        canvas.setHeight(canvasContainer.clientHeight);
        canvas.calcOffset();
        canvas.requestRenderAll();
    });

    // Corrección del offset del ratón al hacer scroll en la página o mover sidebars
    window.addEventListener('scroll', () => canvas.calcOffset(), true);
    canvasContainer.addEventListener('mouseenter', () => canvas.calcOffset());

    // --- Sistema de Toasts (Notificaciones) ---
    window.showToast = function(message, icon = 'ph-info') {
        const container = document.getElementById('wb-toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = 'wb-toast';
        toast.innerHTML = `<i class="ph ${icon}"></i> <span>${message}</span>`;
        container.appendChild(toast);
        
        // Reflow for transition
        toast.offsetHeight;
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };

    // --- Sistema de Animación de Carga (Upload) ---
    window.showUploadLoader = function(text = "Subiendo imagen...") {
        let loader = document.getElementById('wb-upload-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'wb-upload-loader';
            loader.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(15, 23, 42, 0.85);
                backdrop-filter: blur(8px);
                color: white;
                padding: 20px 32px;
                border-radius: 16px;
                display: flex;
                align-items: center;
                gap: 16px;
                font-family: 'Inter', sans-serif;
                font-size: 15px;
                font-weight: 600;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                z-index: 1000;
                opacity: 0;
                transition: opacity 0.3s ease;
                pointer-events: none;
            `;
            loader.innerHTML = `
                <svg class="wb-spinner" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <style>
                        .wb-spinner { animation: wb-spin 1s linear infinite; }
                        @keyframes wb-spin { 100% { transform: rotate(360deg); } }
                    </style>
                    <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.2)" stroke-width="3"></circle>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke="white" stroke-width="3" stroke-linecap="round"></path>
                </svg>
                <span id="wb-upload-loader-text"></span>
            `;
            document.getElementById('canvas-wrapper').appendChild(loader);
        }
        document.getElementById('wb-upload-loader-text').innerText = text;
        // Force reflow
        loader.offsetHeight;
        loader.style.opacity = '1';
    };

    window.hideUploadLoader = function() {
        const loader = document.getElementById('wb-upload-loader');
        if (loader) {
            loader.style.opacity = '0';
        }
    };

    // --- Fondo Personalizable ---
    window.changeCanvasBackground = function(type) {
        const container = document.getElementById('canvas-wrapper');
        if (!container) return;
        
        container.style.backgroundImage = 'none';
        container.style.backgroundColor = '#f8fafc'; // Default light

        if (document.documentElement.getAttribute('data-theme') === 'dark') {
            container.style.backgroundColor = '#0f172a'; // Default dark
            if (type === 'dots') {
                const svg = `<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg"><circle cx="2" cy="2" r="1.5" fill="#334155"/></svg>`;
                container.style.backgroundImage = `url('data:image/svg+xml;base64,${btoa(svg)}')`;
            }
            else if (type === 'grid') container.style.backgroundImage = 'linear-gradient(#1e293b 1px, transparent 1px), linear-gradient(90deg, #1e293b 1px, transparent 1px)';
            else if (type === 'lines') container.style.backgroundImage = 'linear-gradient(#1e293b 1px, transparent 1px)';
        } else {
            if (type === 'dots') {
                const svg = `<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg"><circle cx="2" cy="2" r="1.5" fill="#cbd5e1"/></svg>`;
                container.style.backgroundImage = `url('data:image/svg+xml;base64,${btoa(svg)}')`;
            }
            else if (type === 'grid') container.style.backgroundImage = 'linear-gradient(#e2e8f0 1px, transparent 1px), linear-gradient(90deg, #e2e8f0 1px, transparent 1px)';
            else if (type === 'lines') container.style.backgroundImage = 'linear-gradient(#e2e8f0 1px, transparent 1px)';
        }

        if (type === 'dots') container.style.backgroundSize = '20px 20px';
        else if (type === 'grid') container.style.backgroundSize = '20px 20px';
        else if (type === 'lines') container.style.backgroundSize = '100% 24px';
        
        // Guardar el tipo actual para que el observer lo pueda usar
        window.currentCanvasBgType = type;
    };
    
    // Observador para actualizar el fondo de la pizarra si se cambia el modo oscuro
    const themeObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'data-theme') {
                if(window.currentCanvasBgType) {
                    window.changeCanvasBackground(window.currentCanvasBgType);
                } else {
                    window.changeCanvasBackground('dots'); // Default
                }

                // Ajustar contraste de elementos sin fondo (textos y bordes de formas)
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                const defaultDarkText = '#f8fafc';
                const defaultLightText = '#334155';
                const defaultBlack = '#000000';
                const defaultWhite = '#ffffff';

                canvas.getObjects().forEach(obj => {
                    // Texto normal (sin fondo tipo post-it)
                    if ((obj.type === 'i-text' || obj.type === 'textbox') && (!obj.backgroundColor || obj.backgroundColor === 'transparent')) {
                        if (isDark && (obj.fill === defaultLightText || obj.fill === defaultBlack)) {
                            obj.set('fill', defaultDarkText);
                        } else if (!isDark && (obj.fill === defaultDarkText || obj.fill === defaultWhite)) {
                            obj.set('fill', defaultLightText);
                        }
                    }
                    // Formas geométricas (borde)
                    if (obj.isShape) {
                        if (isDark && (obj.stroke === defaultLightText || obj.stroke === defaultBlack)) {
                            obj.set('stroke', defaultDarkText);
                        } else if (!isDark && (obj.stroke === defaultDarkText || obj.stroke === defaultWhite)) {
                            obj.set('stroke', defaultLightText);
                        }
                    }
                    // Flechas y conectores
                    if (obj.isArrowLine || obj.isArrowHead || obj.isArrowText) {
                        const color = obj.fill || obj.stroke;
                        if (isDark && (color === defaultLightText || color === defaultBlack)) {
                            if (obj.fill) obj.set('fill', defaultDarkText);
                            if (obj.stroke) obj.set('stroke', defaultDarkText);
                        } else if (!isDark && (color === defaultDarkText || color === defaultWhite)) {
                            if (obj.fill) obj.set('fill', defaultLightText);
                            if (obj.stroke) obj.set('stroke', defaultLightText);
                        }
                    }
                });
                canvas.requestRenderAll();
                if (typeof triggerSync === 'function') triggerSync();
            }
        });
    });
    themeObserver.observe(document.documentElement, { attributes: true });
    
    // Inicializar el fondo al cargar la página
    window.changeCanvasBackground('dots');

    // --- Modo Presentación ---
    const btnPresentation = document.getElementById('btn-presentation');
    let isPresentationMode = false;
    if (btnPresentation) {
        btnPresentation.addEventListener('click', () => {
            isPresentationMode = !isPresentationMode;
            const uiLayer = document.querySelector('.wb-ui-layer');
            const btnIcon = btnPresentation.querySelector('i');
            
            if (isPresentationMode) {
                uiLayer.classList.add('presentation-mode');
                btnIcon.classList.replace('ph-presentation-chart', 'ph-x-square');
                showToast('Modo Presentación Activado (Presiona Esc para salir)', 'ph-presentation-chart');
                
                // Deshabilitar interacción
                canvas.selection = false;
                canvas.getObjects().forEach(obj => {
                    obj._prevEvented = obj.evented;
                    obj.evented = false;
                });
                canvas.discardActiveObject();
                hideContextMenu();
            } else {
                uiLayer.classList.remove('presentation-mode');
                btnIcon.classList.replace('ph-x-square', 'ph-presentation-chart');
                
                // Restaurar interacción
                canvas.selection = true;
                canvas.getObjects().forEach(obj => {
                    if (obj.hasOwnProperty('_prevEvented')) {
                        obj.evented = obj._prevEvented;
                        delete obj._prevEvented;
                    } else {
                        obj.evented = true;
                    }
                });
            }
            canvas.requestRenderAll();
        });
        
        // Escape para salir
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isPresentationMode) {
                btnPresentation.click();
            }
        });
    }

    // Parche para que el color de fondo de las notas (Textbox) respete el padding
    fabric.Textbox.prototype._renderBackground = function(ctx) {
        if (!this.backgroundColor) {
            return;
        }
        var dim = this._getNonTransformedDimensions();
        var pad = this.padding || 0;
        ctx.fillStyle = this.backgroundColor;
        ctx.fillRect(
            -dim.x / 2 - pad,
            -dim.y / 2 - pad,
            dim.x + pad * 2,
            dim.y + pad * 2
        );
    };

    let currentTool = 'select'; // select, draw, text, sticky, shape
    let currentShapeType = 'rect'; // rect, circle, triangle
    let currentColor = '#000000';
    let isUpdatingFromPusher = false;
    let currentUserSessionId = null;

    // 2. Herramientas de la interfaz
    const btnSelect = document.getElementById('tool-select');
    const btnPan = document.getElementById('tool-pan');
    const btnDraw = document.getElementById('tool-draw');
    
    // Controles de Zoom
    const btnZoomIn = document.getElementById('btn-zoom-in');
    const btnZoomOut = document.getElementById('btn-zoom-out');
    const zoomLevelText = document.getElementById('zoom-level-text');
    const btnHighlighter = document.getElementById('tool-highlighter');
    const btnEraser = document.getElementById('tool-eraser');
    const btnText = document.getElementById('tool-text');
    const btnSticky = document.getElementById('tool-sticky');
    const btnArrow = document.getElementById('tool-arrow');
    const btnComment = document.getElementById('tool-comment');
    const btnFrame = document.getElementById('tool-frame');
    const btnShape = document.getElementById('tool-shape');
    const shapePresetsMenu = document.getElementById('shape-presets');
    const btnEmbed = document.getElementById('tool-embed');
    const colorPicker = document.getElementById('wb-color');
    const btnClear = document.getElementById('tool-clear');
    const statusIndicator = document.getElementById('wb-status');

    function setActiveTool(btn, toolName) {
        document.querySelectorAll('.wb-tool-btn').forEach(b => b.classList.remove('active'));
        if (btnSelect) btnSelect.classList.remove('active');
        if (btnPan) btnPan.classList.remove('active');
        if (btnDraw) btnDraw.classList.remove('active');
        if (btnText) btnText.classList.remove('active');
        if (btnSticky) btnSticky.classList.remove('active');
        if (btnArrow) btnArrow.classList.remove('active');
        if (btnComment) btnComment.classList.remove('active');
        if (btnFrame) btnFrame.classList.remove('active');
        if (btnShape) btnShape.classList.remove('active');
        if (btnEmbed) btnEmbed.classList.remove('active');
        
        btn.classList.add('active');
        const previousTool = currentTool;
        currentTool = toolName;
        
        // Lógica para modo dibujo o resaltador
        canvas.isDrawingMode = (toolName === 'draw' || toolName === 'highlighter');
        if (toolName === 'draw') {
            canvas.freeDrawingBrush.color = currentColor;
            canvas.freeDrawingBrush.width = 4;
        } else if (toolName === 'highlighter') {
            canvas.freeDrawingBrush.color = currentColor;
            canvas.freeDrawingBrush.width = 24; // Trazo grueso de resaltador
        }
        
        canvas.defaultCursor = (toolName === 'select') ? 'default' : (toolName === 'pan' ? 'grab' : 'crosshair');
        canvas.selection = (toolName === 'select');

        // Lógica para deshabilitar selección en modo Mano (Pan)
        if (toolName === 'pan') {
            canvas.getObjects().forEach(obj => {
                if (!obj.hasOwnProperty('_prevEvented')) {
                    obj._prevEvented = obj.evented;
                    obj._prevSelectable = obj.selectable;
                }
                obj.evented = false;
                obj.selectable = false;
            });
            canvas.discardActiveObject();
            if (typeof hideContextMenu === 'function') hideContextMenu();
            canvas.requestRenderAll();
        } else if (previousTool === 'pan') {
            canvas.getObjects().forEach(obj => {
                if (obj.hasOwnProperty('_prevEvented')) {
                    obj.evented = obj._prevEvented;
                    obj.selectable = obj._prevSelectable;
                    delete obj._prevEvented;
                    delete obj._prevSelectable;
                }
            });
            canvas.requestRenderAll();
        }
    }

    // --- Toggle Sidebars ---
    const toggleSidebarBtn = document.getElementById('toggle-sidebar-btn');
    const templatesSidebar = document.getElementById('templates-sidebar');
    const toggleComponentsBtn = document.getElementById('toggle-components-btn');
    const componentsSidebar = document.getElementById('components-sidebar');
    
    function refreshCanvasLayout() {
        setTimeout(() => {
            if (canvasWrapper) {
                canvas.setWidth(canvasWrapper.clientWidth);
                canvas.setHeight(canvasWrapper.clientHeight);
            }
            canvas.calcOffset();
            canvas.requestRenderAll();
        }, 350);
    }

    // --- Dynamic Templates Sidebar Setup ---
    let tplDataCache = null;
    let isTplSidebarInitialized = false;
    const MONTH_NAMES = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

    function buildMonthMiniCard(m) {
        const monthName = MONTH_NAMES[parseInt(m.month) - 1] || m.month;
        const total = parseInt(m.post_count) || 0;
        const comments = parseInt(m.comment_count) || 0;
        const progress = parseInt(m.progress) || 0;
        const b = parseInt(m.borrador_count) || 0;
        const r = parseInt(m.revision_count) || 0;
        const a = parseInt(m.aprobado_count) || 0;
        const p = parseInt(m.publicado_count) || 0;
        const statusBadge = m.month_status || 'EN PROGRESO';

        const card = document.createElement('div');
        card.className = 'tpl-mini-board-card';
        card.style.cssText = 'background:#fff; border-radius:12px; padding:14px; box-shadow:0 1px 4px rgba(0,0,0,0.06); border:1.5px solid #e2e8f0; cursor:pointer; transition:all 0.2s; position:relative;';
        
        card.innerHTML = `
            <div style="font-size:14px; font-weight:700; color:#0f172a; margin-bottom:10px;">${monthName} ${m.year}</div>
            <div style="display:flex; gap:8px; margin-bottom:10px;">
                <div style="flex:1; text-align:center; padding:6px; background:#f8fafc; border-radius:6px; border:1px solid #e2e8f0;">
                    <div style="font-size:18px; font-weight:800; color:#0f172a;">${total}</div>
                    <div style="font-size:9px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Posts</div>
                </div>
                <div style="flex:1; text-align:center; padding:6px; background:#f8fafc; border-radius:6px; border:1px solid #e2e8f0;">
                    <div style="font-size:18px; font-weight:800; color:#0f172a;">${comments}</div>
                    <div style="font-size:9px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Comentarios</div>
                </div>
            </div>
            <div style="margin-bottom:6px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
                    <span style="font-size:9px; font-weight:700; color:#64748b; text-transform:uppercase;">Progreso</span>
                    <span style="font-size:9px; font-weight:700; color:#64748b;">${progress}%</span>
                </div>
                <div style="height:5px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                    <div style="height:100%; width:${progress}%; background: linear-gradient(90deg, #0d9488, #14b8a6); border-radius:3px; transition:width 0.4s;"></div>
                </div>
            </div>
            <div style="display:flex; gap:4px; margin-bottom:10px;">
                <div style="flex:1; text-align:center;">
                    <div style="font-size:13px; font-weight:700; color:#64748b;">${b}</div>
                    <div style="font-size:7px; font-weight:600; color:#94a3b8; text-transform:uppercase;">Borrador</div>
                </div>
                <div style="flex:1; text-align:center;">
                    <div style="font-size:13px; font-weight:700; color:#f97316;">${r}</div>
                    <div style="font-size:7px; font-weight:600; color:#94a3b8; text-transform:uppercase;">Revisión</div>
                </div>
                <div style="flex:1; text-align:center;">
                    <div style="font-size:13px; font-weight:700; color:#64748b;">${a}</div>
                    <div style="font-size:7px; font-weight:600; color:#94a3b8; text-transform:uppercase;">Aprobado</div>
                </div>
                <div style="flex:1; text-align:center;">
                    <div style="font-size:13px; font-weight:700; color:#64748b;">${p}</div>
                    <div style="font-size:7px; font-weight:600; color:#94a3b8; text-transform:uppercase;">Publicado</div>
                </div>
            </div>
            <div style="display:flex; gap:6px;">
                <button class="tpl-insert-btn" data-action="project_board" style="flex:1; padding:7px 0; border:none; border-radius:6px; background:#0d9488; color:#fff; font-size:11px; font-weight:700; cursor:pointer; transition:background 0.2s; display:flex; align-items:center; justify-content:center; gap:4px;">
                    <i class="ph ph-kanban" style="font-size:13px;"></i> Project Board
                </button>
                <button class="tpl-insert-btn" data-action="month_board" style="flex:1; padding:7px 0; border:none; border-radius:6px; background:#7c3aed; color:#fff; font-size:11px; font-weight:700; cursor:pointer; transition:background 0.2s; display:flex; align-items:center; justify-content:center; gap:4px;">
                    <i class="ph ph-calendar-blank" style="font-size:13px;"></i> Month Board
                </button>
            </div>
        `;

        // Hover effect
        card.addEventListener('mouseenter', () => { card.style.borderColor = '#0d9488'; card.style.boxShadow = '0 4px 12px rgba(13,148,136,0.15)'; });
        card.addEventListener('mouseleave', () => { card.style.borderColor = '#e2e8f0'; card.style.boxShadow = '0 1px 4px rgba(0,0,0,0.06)'; });

        // Button click handlers
        card.querySelectorAll('.tpl-insert-btn').forEach(btn => {
            btn.addEventListener('mouseenter', () => { btn.style.filter = 'brightness(1.15)'; });
            btn.addEventListener('mouseleave', () => { btn.style.filter = 'none'; });
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = btn.dataset.action;
                const title = monthName + ' ' + m.year;
                
                // Close sidebar
                if (templatesSidebar) templatesSidebar.classList.add('closed');
                
                // Calculate center of canvas
                const vpt = canvas.viewportTransform;
                const cx = (-vpt[4] + canvas.width / 2) / vpt[0];
                const cy = (-vpt[5] + canvas.height / 2) / vpt[3];
                
                if (action === 'month_board') {
                    insertMonthBoardOnCanvas(m.id, title, cx, cy);
                } else {
                    insertProjectBoardOnCanvas(m, title, cx, cy);
                }
            });
        });

        return card;
    }

    function insertProjectBoardOnCanvas(monthData, title, cx, cy) {
        if (typeof setStatus === 'function') setStatus('Insertando Project Board...', '#0d9488');
        
        const monthName = MONTH_NAMES[parseInt(monthData.month) - 1] || monthData.month;
        const total = parseInt(monthData.post_count) || 0;
        const comments = parseInt(monthData.comment_count) || 0;
        const progress = parseInt(monthData.progress) || 0;
        const b = parseInt(monthData.borrador_count) || 0;
        const r = parseInt(monthData.revision_count) || 0;
        const a = parseInt(monthData.aprobado_count) || 0;
        const p = parseInt(monthData.publicado_count) || 0;
        
        const CARD_W = 340;
        const objects = [];
        
        // Card background
        const bg = new fabric.Rect({
            width: CARD_W, height: 260,
            fill: '#ffffff', rx: 16, ry: 16,
            shadow: new fabric.Shadow({ color: 'rgba(0,0,0,0.1)', blur: 20, offsetX: 0, offsetY: 4 })
        });
        objects.push(bg);
        
        // Title
        const titleText = new fabric.Textbox(monthName + ' ' + monthData.year, {
            left: 24, top: 20, width: CARD_W - 48,
            fontSize: 22, fontFamily: 'Inter', fontWeight: 'bold', fill: '#0f172a',
            editable: false, splitByGrapheme: false
        });
        objects.push(titleText);
        
        // Stats boxes
        const postBox = new fabric.Rect({ left: 24, top: 55, width: 130, height: 50, fill: '#f8fafc', rx: 8, ry: 8, stroke: '#e2e8f0', strokeWidth: 1 });
        const postNum = new fabric.Text(String(total), { left: 60, top: 60, fontSize: 22, fontFamily: 'Inter', fontWeight: 'bold', fill: '#0f172a', originX: 'center' });
        const postLabel = new fabric.Text('POSTS', { left: 60, top: 82, fontSize: 10, fontFamily: 'Inter', fontWeight: '600', fill: '#94a3b8', originX: 'center' });
        
        const commBox = new fabric.Rect({ left: 164, top: 55, width: 130, height: 50, fill: '#f8fafc', rx: 8, ry: 8, stroke: '#e2e8f0', strokeWidth: 1 });
        const commNum = new fabric.Text(String(comments), { left: 200, top: 60, fontSize: 22, fontFamily: 'Inter', fontWeight: 'bold', fill: '#0f172a', originX: 'center' });
        const commLabel = new fabric.Text('COMENTARIOS', { left: 200, top: 82, fontSize: 10, fontFamily: 'Inter', fontWeight: '600', fill: '#94a3b8', originX: 'center' });
        
        objects.push(postBox, postNum, postLabel, commBox, commNum, commLabel);
        
        // Progress bar bg
        const progBg = new fabric.Rect({ left: 24, top: 125, width: CARD_W - 48, height: 6, fill: '#e2e8f0', rx: 3, ry: 3 });
        objects.push(progBg);
        
        // Progress bar fill
        if (progress > 0) {
            const progFill = new fabric.Rect({ left: 24, top: 125, width: (CARD_W - 48) * (progress / 100), height: 6, fill: '#14b8a6', rx: 3, ry: 3 });
            objects.push(progFill);
        }
        
        // Progress label
        const progLabel = new fabric.Text('PROGRESO', { left: 24, top: 115, fontSize: 8, fontFamily: 'Inter', fontWeight: '700', fill: '#64748b' });
        const progPct = new fabric.Text(progress + '%', { left: CARD_W - 24, top: 115, fontSize: 8, fontFamily: 'Inter', fontWeight: '700', fill: '#64748b', originX: 'right' });
        objects.push(progLabel, progPct);
        
        // Status row
        const statusData = [
            { count: b, label: 'BORRADOR', color: '#64748b' },
            { count: r, label: 'REVISIÓN', color: '#f97316' },
            { count: a, label: 'APROBADO', color: '#64748b' },
            { count: p, label: 'PUBLICADO', color: '#64748b' }
        ];
        const colW = (CARD_W - 48) / 4;
        statusData.forEach((s, i) => {
            const sx = 24 + (i * colW) + colW / 2;
            objects.push(new fabric.Text(String(s.count), {
                left: sx, top: 148, fontSize: 16, fontFamily: 'Inter', fontWeight: 'bold', fill: s.color, originX: 'center'
            }));
            objects.push(new fabric.Text(s.label, {
                left: sx, top: 168, fontSize: 7, fontFamily: 'Inter', fontWeight: '700', fill: '#94a3b8', originX: 'center'
            }));
        });
        
        // Status badge
        const badgeBg = new fabric.Rect({ left: 24, top: 195, width: 90, height: 22, fill: '#ecfdf5', rx: 4, ry: 4, stroke: '#6ee7b7', strokeWidth: 1 });
        const badgeText = new fabric.Text(monthData.month_status || 'EN PROGRESO', { left: 69, top: 200, fontSize: 9, fontFamily: 'Inter', fontWeight: '700', fill: '#059669', originX: 'center' });
        objects.push(badgeBg, badgeText);
        
        // Entrar button
        const btnBg = new fabric.Rect({ left: 24, top: 225, width: CARD_W - 48, height: 30, fill: '#0d9488', rx: 6, ry: 6 });
        const btnText = new fabric.Text('✏ Entrar', { left: CARD_W / 2, top: 231, fontSize: 12, fontFamily: 'Inter', fontWeight: 'bold', fill: '#ffffff', originX: 'center' });
        objects.push(btnBg, btnText);
        
        const group = new fabric.Group(objects, {
            left: cx - CARD_W / 2, top: cy - 130,
            isComponent: true, objectCaching: false
        });
        ensureId(group);
        canvas.add(group);
        canvas.setActiveObject(group);
        canvas.requestRenderAll();
        triggerSync();
        broadcastDelta(group, 'added');
        if (typeof setStatus === 'function') setStatus('Project Board insertado ✓', '#10b981');
    }

    // Helper: strip HTML tags
    function stripHtml(html) {
        if (!html) return '';
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return (tmp.textContent || tmp.innerText || '').trim();
    }
    
    // Helper: format date nicely
    function formatPostDate(dateStr) {
        if (!dateStr || dateStr === '0000-00-00' || dateStr === '0000-00-00 00:00:00') return '';
        try {
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
        } catch(e) { return dateStr; }
    }
    
    // Helper: resolve image URL (handle Drive links)
    function resolveImageUrl(url) {
        if (!url) return null;
        // Handle JSON arrays (carousels) — take first image
        if (url.startsWith('[')) {
            try {
                const arr = JSON.parse(url);
                if (arr.length > 0) url = arr[0];
                else return null;
            } catch(e) { return null; }
        }
        if (!url || url === 'null' || url === '') return null;
        
        // Google Drive links → use proxy
        if (url.includes('drive.google.com')) {
            const match = url.match(/[-\w]{25,}/);
            if (match) return 'ajax/drive_proxy.php?id=' + match[0];
        }
        // If it's already a direct URL, use as-is
        return url;
    }
    
    // Helper: load a fabric image (returns Promise)
    function loadFabricImage(url, maxW, maxH) {
        return new Promise((resolve) => {
            if (!url) return resolve(null);
            fabric.Image.fromURL(url, (img) => {
                if (img && img.width > 0 && img.height > 0) {
                    // Scale to fit maxW x maxH
                    const scale = Math.min(maxW / img.width, maxH / img.height);
                    img.set({ scaleX: scale, scaleY: scale, objectCaching: false });
                    resolve(img);
                } else {
                    resolve(null);
                }
            }, { crossOrigin: 'anonymous' });
        });
    }

    async function insertMonthBoardOnCanvas(monthId, title, cx, cy) {
        if (typeof setStatus === 'function') setStatus('Cargando Month Board...', '#7c3aed');
        
        try {
            const response = await fetch('ajax/ajax_get_month_posts.php?month_id=' + monthId);
            const res = await response.json();
            if (!res.success) throw new Error(res.error || 'Error');
            
            const posts = res.posts || [];
            const brandName = (res.month_info && res.month_info.brand_name) || '';
            
            // Layout constants
            const CARD_W = 240;
            const CARD_GAP = 20;
            const CARD_PADDING = 16;
            const IMG_W = CARD_W - CARD_PADDING * 2;
            const IMG_H = 160;
            const HEADER_H = 60;
            const totalWidth = Math.max(posts.length * CARD_W + (posts.length - 1) * CARD_GAP, 300);
            const startX = cx - totalWidth / 2;
            const startY = cy;
            
            // Pre-load all images in parallel
            if (typeof setStatus === 'function') setStatus('Cargando imágenes...', '#7c3aed');
            const imagePromises = posts.map(post => {
                const imgUrl = resolveImageUrl(post.image_link) || resolveImageUrl(post.reference_image_link);
                return loadFabricImage(imgUrl, IMG_W, IMG_H);
            });
            const loadedImages = await Promise.all(imagePromises);
            
            const objects = [];
            
            // ── Header Bar ──
            const headerBg = new fabric.Rect({
                left: startX - 20, top: startY - HEADER_H - 10,
                width: totalWidth + 40, height: HEADER_H,
                fill: '#ffffff', rx: 12, ry: 12,
                shadow: new fabric.Shadow({ color: 'rgba(0,0,0,0.06)', blur: 10, offsetX: 0, offsetY: 2 })
            });
            objects.push(headerBg);
            
            const headerTitle = new fabric.Text(title, {
                left: startX, top: startY - HEADER_H + 8,
                fontSize: 24, fontFamily: 'Inter', fontWeight: 'bold', fill: '#0f172a'
            });
            objects.push(headerTitle);
            
            if (brandName) {
                const brandLabel = new fabric.Text(brandName, {
                    left: startX, top: startY - HEADER_H + 38,
                    fontSize: 12, fontFamily: 'Inter', fontWeight: '500', fill: '#64748b'
                });
                objects.push(brandLabel);
            }
            
            // ── Post Cards ──
            let maxCardH = 0;
            
            posts.forEach((post, idx) => {
                const cardX = startX + idx * (CARD_W + CARD_GAP);
                let curY = startY;
                const cardObjs = [];
                
                // Card background
                const cardBg = new fabric.Rect({
                    left: cardX, top: curY,
                    width: CARD_W, height: 400,
                    fill: '#ffffff', rx: 12, ry: 12,
                    shadow: new fabric.Shadow({ color: 'rgba(0,0,0,0.06)', blur: 12, offsetX: 0, offsetY: 3 }),
                    stroke: '#e2e8f0', strokeWidth: 1
                });
                cardObjs.push(cardBg);
                
                // Accent line
                const accentLine = new fabric.Rect({
                    left: cardX, top: curY,
                    width: CARD_W, height: 4,
                    fill: '#0d9488', rx: 12, ry: 12
                });
                cardObjs.push(accentLine);
                curY += 12;
                
                // Post number
                const postNum = new fabric.Text('Post ' + String(idx + 1).padStart(2, '0'), {
                    left: cardX + CARD_PADDING, top: curY,
                    fontSize: 14, fontFamily: 'Inter', fontWeight: 'bold', fill: '#0d9488'
                });
                cardObjs.push(postNum);
                curY += 26;
                
                // Image area
                const loadedImg = loadedImages[idx];
                if (loadedImg) {
                    // Center the image in the area
                    const imgW = loadedImg.width * loadedImg.scaleX;
                    const imgH = loadedImg.height * loadedImg.scaleY;
                    loadedImg.set({
                        left: cardX + CARD_PADDING + (IMG_W - imgW) / 2,
                        top: curY + (IMG_H - imgH) / 2
                    });
                    // Image background container
                    const imgContainer = new fabric.Rect({
                        left: cardX + CARD_PADDING, top: curY,
                        width: IMG_W, height: IMG_H,
                        fill: '#f1f5f9', rx: 8, ry: 8
                    });
                    cardObjs.push(imgContainer, loadedImg);
                } else {
                    // Placeholder
                    const imgBg = new fabric.Rect({
                        left: cardX + CARD_PADDING, top: curY,
                        width: IMG_W, height: IMG_H,
                        fill: '#f1f5f9', rx: 8, ry: 8
                    });
                    const imgIcon = new fabric.Text('🖼', {
                        left: cardX + CARD_W / 2, top: curY + IMG_H / 2 - 14,
                        fontSize: 28, originX: 'center'
                    });
                    cardObjs.push(imgBg, imgIcon);
                }
                curY += IMG_H + 8;
                
                // REFERENCIA GRÁFICA label
                if (post.reference_image_link) {
                    const refBg = new fabric.Rect({
                        left: cardX + CARD_PADDING, top: curY,
                        width: IMG_W, height: 24,
                        fill: '#0d9488', rx: 4, ry: 4
                    });
                    const refText = new fabric.Text('REFERENCIA GRÁFICA', {
                        left: cardX + CARD_W / 2, top: curY + 5,
                        fontSize: 10, fontFamily: 'Inter', fontWeight: 'bold', fill: '#ffffff',
                        originX: 'center'
                    });
                    cardObjs.push(refBg, refText);
                    curY += 32;
                }
                
                // Date + Status row
                curY += 4;
                const niceDate = formatPostDate(post.post_date);
                if (niceDate) {
                    const dateText = new fabric.Text('📅 ' + niceDate, {
                        left: cardX + CARD_PADDING, top: curY + 1,
                        fontSize: 11, fontFamily: 'Inter', fill: '#64748b'
                    });
                    cardObjs.push(dateText);
                }
                
                // Status badge
                const status = post.status || 'Borrador';
                const statusColors = {
                    'Borrador': { bg: '#f1f5f9', text: '#64748b', border: '#cbd5e1' },
                    'En Revisión': { bg: '#fff7ed', text: '#ea580c', border: '#fdba74' },
                    'Aprobado': { bg: '#ecfdf5', text: '#059669', border: '#6ee7b7' },
                    'Publicado': { bg: '#ecfdf5', text: '#047857', border: '#34d399' }
                };
                const sc = statusColors[status] || statusColors['Borrador'];
                
                const badgeBg = new fabric.Rect({
                    left: cardX + CARD_W - CARD_PADDING - 80, top: curY - 2,
                    width: 80, height: 20,
                    fill: sc.bg, rx: 10, ry: 10,
                    stroke: sc.border, strokeWidth: 1
                });
                const badgeText = new fabric.Text(status.toUpperCase(), {
                    left: cardX + CARD_W - CARD_PADDING - 40, top: curY + 2,
                    fontSize: 8, fontFamily: 'Inter', fontWeight: 'bold', fill: sc.text,
                    originX: 'center'
                });
                cardObjs.push(badgeBg, badgeText);
                curY += 24;
                
                // Concept title
                const conceptText = new fabric.Textbox(post.concept || 'Sin título', {
                    left: cardX + CARD_PADDING, top: curY,
                    width: CARD_W - CARD_PADDING * 2,
                    fontSize: 13, fontFamily: 'Inter', fontWeight: '700', fill: '#0f172a',
                    editable: false, splitByGrapheme: false
                });
                cardObjs.push(conceptText);
                curY += conceptText.height + 4;
                
                // Copy text (stripped of HTML)
                const cleanCopy = stripHtml(post.copy_text || '').substring(0, 100);
                if (cleanCopy) {
                    const copyText = new fabric.Textbox(cleanCopy + (stripHtml(post.copy_text || '').length > 100 ? '...' : ''), {
                        left: cardX + CARD_PADDING, top: curY,
                        width: CARD_W - CARD_PADDING * 2,
                        fontSize: 11, fontFamily: 'Inter', fill: '#94a3b8',
                        editable: false, splitByGrapheme: false
                    });
                    cardObjs.push(copyText);
                    curY += copyText.height + 4;
                }
                
                curY += CARD_PADDING;
                
                const finalH = curY - startY;
                cardBg.set({ height: finalH });
                if (finalH > maxCardH) maxCardH = finalH;
                
                objects.push(...cardObjs);
            });
            
            // Equalize card heights
            objects.forEach(obj => {
                if (obj.type === 'rect' && obj.width === CARD_W && obj.stroke === '#e2e8f0' && obj.fill === '#ffffff') {
                    obj.set({ height: maxCardH });
                }
            });
            
            const group = new fabric.Group(objects, {
                left: startX - 20, top: startY - HEADER_H - 10,
                isComponent: true
            });
            ensureId(group);
            canvas.add(group);
            canvas.setActiveObject(group);
            canvas.requestRenderAll();
            triggerSync();
            broadcastDelta(group, 'added');
            if (typeof setStatus === 'function') setStatus('Month Board insertado ✓', '#10b981');
        } catch (err) {
            console.error('Error inserting month board', err);
            if (typeof setStatus === 'function') setStatus('Error al cargar mes', '#ef4444');
        }
    }

    async function initTemplatesSidebar() {
        if (isTplSidebarInitialized) return;
        isTplSidebarInitialized = true;
        
        try {
            const res = await fetch('ajax/ajax_get_board_templates_data.php');
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Error loading data');
            
            tplDataCache = data;
            const brandSelect = document.getElementById('tpl-brand-select');
            const container = document.getElementById('tpl-boards-container');
            
            if (!brandSelect || !container) return;
            
            brandSelect.innerHTML = '<option value="">Seleccionar marca...</option>';
            data.brands.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.name;
                opt.textContent = b.name;
                opt.dataset.id = b.id;
                if (b.logo) opt.dataset.logo = b.logo;
                brandSelect.appendChild(opt);
            });
            
            brandSelect.addEventListener('change', function() {
                const brandName = this.value;
                container.innerHTML = '';
                
                if (!brandName) return;
                
                const brandMonths = data.months.filter(m => m.brand_name === brandName);
                
                if (brandMonths.length === 0) {
                    container.innerHTML = '<div style="text-align:center; padding:20px; color:#94a3b8; font-size:13px;">No hay meses para esta marca</div>';
                    return;
                }
                
                brandMonths.forEach(m => {
                    container.appendChild(buildMonthMiniCard(m));
                });
            });
        } catch (e) {
            console.error('Error loading template data', e);
        }
    }

    if (toggleSidebarBtn && templatesSidebar) {
        toggleSidebarBtn.addEventListener('click', () => {
            if (componentsSidebar && !componentsSidebar.classList.contains('closed')) {
                componentsSidebar.classList.add('closed');
            }
            templatesSidebar.classList.toggle('closed');
            if (!templatesSidebar.classList.contains('closed')) {
                initTemplatesSidebar();
            }
            refreshCanvasLayout();
        });
    }
    
    if (toggleComponentsBtn && componentsSidebar) {
        toggleComponentsBtn.addEventListener('click', () => {
            if (templatesSidebar && !templatesSidebar.classList.contains('closed')) {
                templatesSidebar.classList.add('closed');
            }
            componentsSidebar.classList.toggle('closed');
            refreshCanvasLayout();
        });
    }

    if (btnSelect) btnSelect.addEventListener('click', () => setActiveTool(btnSelect, 'select'));
    if (btnPan) btnPan.addEventListener('click', () => setActiveTool(btnPan, 'pan'));
    if (btnDraw) btnDraw.addEventListener('click', () => setActiveTool(btnDraw, 'draw'));
    if (btnHighlighter) btnHighlighter.addEventListener('click', () => setActiveTool(btnHighlighter, 'highlighter'));
    if (btnEraser) btnEraser.addEventListener('click', () => setActiveTool(btnEraser, 'eraser'));
    if (btnText) btnText.addEventListener('click', () => setActiveTool(btnText, 'text'));
    if (btnArrow) btnArrow.addEventListener('click', () => setActiveTool(btnArrow, 'arrow'));
    if (btnComment) btnComment.addEventListener('click', () => setActiveTool(btnComment, 'comment'));
    if (btnShape) {
        btnShape.addEventListener('click', () => {
            if (currentTool === 'shape') {
                shapePresetsMenu.style.display = (shapePresetsMenu.style.display === 'flex') ? 'none' : 'flex';
            } else {
                setActiveTool(btnShape, 'shape');
            }
        });
    }

    // Ocultar menús desplegables si se hace clic fuera
    document.addEventListener('click', (e) => {
        if (btnShape && shapePresetsMenu && !btnShape.contains(e.target) && !shapePresetsMenu.contains(e.target)) {
            shapePresetsMenu.style.display = 'none';
        }
    });

    if (shapePresetsMenu) {
        shapePresetsMenu.querySelectorAll('.shape-preset-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                currentShapeType = btn.getAttribute('data-shape');
                shapePresetsMenu.style.display = 'none';
                setActiveTool(btnShape, 'shape');
            });
        });
    }
    
    colorPicker.addEventListener('change', (e) => {
        currentColor = e.target.value;
        if (canvas.isDrawingMode) {
            canvas.freeDrawingBrush.color = currentColor;
        }
        // Change color of selected object if text or rect
        const activeObj = canvas.getActiveObject();
        if (activeObj) {
            if (activeObj.type === 'i-text' || activeObj.type === 'textbox') {
                activeObj.set('fill', currentColor);
            } else if (activeObj.type === 'rect') {
                activeObj.set('fill', currentColor);
            } else if (activeObj.type === 'path') {
                activeObj.set('stroke', currentColor);
            }
            canvas.requestRenderAll();
            triggerSync();
        }
    });

    canvas.on('path:created', function(opt) {
        if (opt.path) {
            opt.path.set({ id: 'path_' + Date.now() });
            
            if (currentTool === 'highlighter') {
                opt.path.set({
                    opacity: 0.4,
                    globalCompositeOperation: 'multiply' // Esto hace el efecto real de resaltador sobre texto
                });
            }
            triggerSync();
        }
    });

    btnText.addEventListener('click', () => {
        setActiveTool(btnText, 'text');
        const center = getViewportCenter(canvas);
        const text = new fabric.Textbox('Doble clic para editar', {
            left: center.x,
            top: center.y,
            originX: 'center',
            originY: 'center',
            width: 300,
            fontFamily: 'Inter, sans-serif',
            fill: currentColor,
            fontSize: 24
        });
        canvas.add(text);
        canvas.setActiveObject(text);
        triggerSync();
        setActiveTool(btnSelect, 'select'); // Auto revert to select
    });

    btnSticky.addEventListener('click', () => {
        setActiveTool(btnSticky, 'sticky');
        
        // Colores pastel aleatorios para notas adhesivas
        const colors = ['#fef08a', '#bbf7d0', '#bfdbfe', '#fbcfe8', '#fed7aa'];
        const randomColor = colors[Math.floor(Math.random() * colors.length)];
        
        const center = getViewportCenter(canvas);
        const note = new fabric.Textbox('Escribe tu nota...', {
            left: center.x + (Math.random() * 40 - 20),
            top: center.y + (Math.random() * 40 - 20),
            originX: 'center',
            originY: 'center',
            width: 150,
            backgroundColor: randomColor,
            fill: '#334155',
            fontSize: 18,
            fontFamily: 'Inter, sans-serif',
            padding: 15,
            textAlign: 'center',
            shadow: new fabric.Shadow({
                color: 'rgba(0,0,0,0.15)',
                blur: 10,
                offsetX: 2,
                offsetY: 2
            })
        });
        canvas.add(note);
        canvas.setActiveObject(note);
        
        setActiveTool(btnSelect, 'select');
        triggerSync();
    });

    // --- Lógica de Marcos Inteligentes ---
    const framePresetsMenu = document.getElementById('frame-presets');
    let frameMode = 'free'; // 'free' o 'preset'

    if (btnFrame && framePresetsMenu) {
        btnFrame.addEventListener('mouseenter', () => {
            framePresetsMenu.style.display = 'flex';
        });
        btnFrame.parentElement.addEventListener('mouseleave', () => {
            framePresetsMenu.style.display = 'none';
        });

        document.querySelectorAll('.frame-preset-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const w = btn.getAttribute('data-width');
                if (w === 'free') {
                    frameMode = 'free';
                    setActiveTool(btnFrame, 'frame');
                } else {
                    // Crear marco predefinido en el centro de la pantalla
                    const width = parseInt(w);
                    const height = parseInt(btn.getAttribute('data-height'));
                    
                    const center = canvas.getVpCenter();
                    const frame = new fabric.Rect({
                        left: center.x - (width/2),
                        top: center.y - (height/2),
                        width: width,
                        height: height,
                        fill: '#ffffff',
                        strokeWidth: 0,
                        shadow: new fabric.Shadow({ color: 'rgba(0,0,0,0.08)', blur: 15, offsetX: 0, offsetY: 4 }),
                        selectable: true,
                        isFrame: true,
                        rx: 8, ry: 8
                    });
                    
                    canvas.add(frame);
                    frame.sendToBack();
                    canvas.setActiveObject(frame);
                    setActiveTool(btnSelect, 'select'); // Volver al puntero
                    triggerSync();
                }
                framePresetsMenu.style.display = 'none';
            });
        });
    }

    if (btnFrame) {
        btnFrame.addEventListener('click', () => {
            frameMode = 'free';
            setActiveTool(btnFrame, 'frame');
        });
    }

    // Dibujo de Marcos (Frames) forma libre
    let isDrawingFrame = false;
    let currentFrame = null;
    let origX, origY;
    
    // Variables para flechas
    let isDrawingArrow = false;
    let currentArrowLine = null;
    let currentArrowHead = null;
    let arrowStartTarget = null;
    
    // Variables para Borrador
    let isErasing = false;
    
    function removeArrowDependencies(obj) {
        if (!obj.id) return;
        const arrowsToRemove = [];
        canvas.getObjects().forEach(o => {
            if (o.isArrowLine && (o.fromId === obj.id || o.toId === obj.id)) {
                arrowsToRemove.push(o.parentArrowId);
            }
        });
        canvas.getObjects().forEach(o => {
            if (arrowsToRemove.includes(o.parentArrowId)) {
                canvas.remove(o);
            }
        });
    }

    function eraseObjectAt(e) {
        const target = canvas.findTarget(e);
        if (target && !target.isFrame && !target.locked && !target.isArrowText && !target.isArrowHead) {
            canvas.remove(target);
            removeArrowDependencies(target);
            triggerSync();
        } else if (target && (target.isArrowText || target.isArrowHead || target.isArrowLine)) {
            // Si borras cualquier parte de la flecha, se borra entera
            const arrowId = target.parentArrowId;
            canvas.getObjects().forEach(o => {
                if (o.parentArrowId === arrowId) canvas.remove(o);
            });
            triggerSync();
        }
    }
    
    // Helpers para Flechas Magnéticas
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
                    let angle = Math.atan2(dy, dx) * 180 / Math.PI;
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
    }
    
    // Variables para panning (Espacio + Arrastrar)
    let isSpaceDown = false;
    let isPanning = false;
    let lastPanX, lastPanY;
    let isDrawingShape = false;
    let currentShape = null;
    let isUserInteracting = false;

    canvas.on('mouse:dblclick', function(o) {
        if (o.target && o.target.linkUrl) {
            const url = o.target.linkUrl;
            // If it's a drive_proxy file, trigger a proper download
            if (url.includes('drive_proxy.php') && o.target.pdfFileName) {
                const downloadUrl = url + '&dl=1&name=' + encodeURIComponent(o.target.pdfFileName);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = o.target.pdfFileName;
                a.target = '_blank';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            } else {
                window.open(url, '_blank');
            }
            return;
        }
        
        if (o.target && o.target.type === 'group' && o.target.isRotulo) {
            const group = o.target;
            const objs = group.getObjects();
            const fLogo = objs.find(ob => ob.rotuloField === 'logo');
            const fTitle = objs.find(ob => ob.rotuloField === 'title');
            const fSize = objs.find(ob => ob.rotuloField === 'size');
            const fFormat = objs.find(ob => ob.rotuloField === 'format');
            const fDesc = objs.find(ob => ob.rotuloField === 'desc');

            let currentTitle = fTitle ? fTitle.text : '';
            let currentSize = fSize ? fSize.text.replace('Tamaño: ', '') : '';
            let currentFormat = fFormat ? fFormat.text.replace('Formato: ', '') : '';
            let currentDesc = fDesc ? fDesc.text : '';

            Swal.fire({
                title: 'Configurar Rótulo',
                html: `
                    <style>
                        .rot-form { text-align: left; font-family: 'Inter', sans-serif; }
                        .rot-row { display: flex; gap: 12px; margin-bottom: 16px; }
                        .rot-col { flex: 1; }
                        .rot-label { display: block; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
                        .rot-input { width: 100%; box-sizing: border-box; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; font-size: 14px; color: #0f172a; transition: all 0.2s; outline: none; }
                        .rot-input:focus { border-color: #3b82f6; background: #ffffff; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
                        textarea.rot-input { resize: none; height: 80px; }
                        .swal2-popup.rot-swal { border-radius: 16px !important; padding-bottom: 1.5rem !important; }
                        .swal2-title.rot-title { font-size: 18px !important; font-weight: 700 !important; color: #0f172a !important; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px !important; }
                        .rot-btn-confirm { background: #3b82f6 !important; color: #fff !important; border-radius: 8px !important; font-weight: 600 !important; padding: 10px 24px !important; border: none !important; margin: 0 5px; box-shadow: 0 2px 4px rgba(59,130,246,0.2) !important; cursor: pointer; }
                        .rot-btn-cancel { background: #f1f5f9 !important; color: #475569 !important; border-radius: 8px !important; font-weight: 600 !important; padding: 10px 24px !important; border: none !important; margin: 0 5px; cursor: pointer; }
                        .rot-btn-confirm:hover { background: #2563eb !important; }
                        .rot-btn-cancel:hover { background: #e2e8f0 !important; }
                    </style>
                    <div class="rot-form">
                        <div class="rot-row">
                            <div class="rot-col">
                                <label class="rot-label">Formato</label>
                                <select id="swal-rot-format" class="rot-input">
                                    <option value="Digital" ${currentFormat === 'Digital' ? 'selected' : ''}>Digital</option>
                                    <option value="Impreso" ${currentFormat === 'Impreso' ? 'selected' : ''}>Impreso</option>
                                </select>
                            </div>
                        </div>
                        <div class="rot-row">
                            <div class="rot-col">
                                <label class="rot-label">Nombre del Proyecto</label>
                                <input id="swal-rot-title" class="rot-input" value="${currentTitle}">
                            </div>
                        </div>
                        <div class="rot-row">
                            <div class="rot-col">
                                <label class="rot-label">Tamaño (Resolución)</label>
                                <select id="swal-rot-size" class="rot-input" style="margin-bottom: 8px;"></select>
                                <input id="swal-rot-size-custom" class="rot-input" value="${currentSize}" placeholder="Ej. 1080 x 1080 px o Tamaño A4" style="display: none;">
                            </div>
                        </div>
                        <div class="rot-row" style="margin-bottom: 0;">
                            <div class="rot-col">
                                <label class="rot-label">Descripción</label>
                                <textarea id="swal-rot-desc" class="rot-input">${currentDesc}</textarea>
                            </div>
                        </div>
                    </div>
                `,
                customClass: {
                    popup: 'rot-swal',
                    title: 'rot-title',
                    confirmButton: 'rot-btn-confirm',
                    cancelButton: 'rot-btn-cancel'
                },
                buttonsStyling: false,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Guardar cambios',
                cancelButtonText: 'Cancelar',
                didOpen: () => {
                    const formatSelect = document.getElementById('swal-rot-format');
                    const sizeSelect = document.getElementById('swal-rot-size');
                    const sizeCustom = document.getElementById('swal-rot-size-custom');
                    
                    const optsDigital = [
                        'HD (720p)',
                        'Full HD (FHD / 1080p): 1920 x 1080 px',
                        'QHD / WQHD (2K): 2560 x 1440 píxeles',
                        'Ultra HD (4K / UHD): 3840 x 2160 px',
                        '8K: 7680 x 4320 px',
                        'Reels 1080 x 1920 px',
                        'Post en relación 1:1',
                        'Post en relación 4:3',
                        'Post en relación 5:4',
                        'Post en relación 4:5',
                        'Personalizado'
                    ];

                    const optsImpreso = [
                        'Tamaño A4',
                        'Tamaño A5',
                        'Tamaño A3',
                        'Tamaño A2',
                        'Tamaño A1',
                        'Tamaño Oficio',
                        'Tamaño Medio oficio',
                        'Tamaño 1/4 de Oficio',
                        'Personalizado'
                    ];

                    const updateSizes = () => {
                        const isDigital = formatSelect.value === 'Digital';
                        const opts = isDigital ? optsDigital : optsImpreso;
                        sizeSelect.innerHTML = '';
                        let matchFound = false;
                        
                        opts.forEach(opt => {
                            const option = document.createElement('option');
                            option.value = opt;
                            option.textContent = opt;
                            // check exact match or if current size contains the option name
                            if (opt === '${currentSize}') {
                                option.selected = true;
                                matchFound = true;
                            }
                            sizeSelect.appendChild(option);
                        });
                        
                        if (!matchFound && '${currentSize}' !== '') {
                            sizeSelect.value = 'Personalizado';
                        }
                        
                        if (sizeSelect.value === 'Personalizado') {
                            sizeCustom.style.display = 'block';
                        } else {
                            sizeCustom.style.display = 'none';
                        }
                    };

                    formatSelect.addEventListener('change', updateSizes);
                    sizeSelect.addEventListener('change', () => {
                        if (sizeSelect.value === 'Personalizado') {
                            sizeCustom.style.display = 'block';
                            if(!sizeCustom.value || optsDigital.includes(sizeCustom.value) || optsImpreso.includes(sizeCustom.value)){
                                sizeCustom.value = '';
                            }
                            sizeCustom.focus();
                        } else {
                            sizeCustom.style.display = 'none';
                        }
                    });

                    updateSizes();
                },
                preConfirm: () => {
                    let sizeVal = document.getElementById('swal-rot-size').value;
                    if (sizeVal === 'Personalizado') {
                        sizeVal = document.getElementById('swal-rot-size-custom').value;
                    }
                    return {
                        title: document.getElementById('swal-rot-title').value,
                        size: sizeVal,
                        format: document.getElementById('swal-rot-format').value,
                        desc: document.getElementById('swal-rot-desc').value
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const vals = result.value;
                    let cardObj = objs.find(ob => ob.rotuloField === 'card');
                    let oldHeight = cardObj ? cardObj.height : 260;
                    
                    let yCursor = -50;
                    
                    if(fTitle) {
                        fTitle.set({ text: vals.title, splitByGrapheme: true, top: yCursor, width: 360, left: -180 });
                        yCursor += fTitle.getScaledHeight() + 15;
                    }

                    if(fSize) {
                        fSize.set({ text: 'Tamaño: ' + vals.size, splitByGrapheme: true, top: yCursor, width: 360, left: -180 });
                        yCursor += fSize.getScaledHeight() + 15;
                    }

                    if(fFormat) {
                        let formatStr = 'Formato: ' + vals.format;
                        fFormat.set({ text: formatStr, splitByGrapheme: true, top: yCursor, width: 340, left: -170, textAlign: 'left' });
                        let sBg = objs.find(ob => ob.rotuloField === 'formatBg');
                        if(sBg) sBg.set({ top: yCursor - 7, width: 360, left: -180 });
                        yCursor += fFormat.getScaledHeight() + 15;
                    }

                    let sep = objs.find(ob => ob.rotuloField === 'sep');
                    if(sep) sep.set({ top: yCursor, width: 360, left: -180 });

                    yCursor += 15;

                    if(fDesc) {
                        fDesc.set({ text: vals.desc, splitByGrapheme: true, top: yCursor, width: 360, left: -180 });
                        yCursor += fDesc.getScaledHeight() + 20;
                    }
                    
                    let newHeight = yCursor + 130;
                    if(newHeight < 260) newHeight = 260;
                    
                    if(cardObj) cardObj.set({ height: newHeight, width: 400, left: -200 });
                    
                    // Restablecer escalas por si el usuario lo deformó previamente
                    group.set({ scaleX: 1, scaleY: 1 });
                    
                    if (group._calcBounds) group._calcBounds();
                    group.set('dirty', true);
                    
                    if (newHeight !== oldHeight) {
                        // Fix jump issue by moving the group top down by half the difference
                        group.set('top', group.top + (newHeight - oldHeight) / 2);
                    }
                    
                    group.setCoords();
                    canvas.requestRenderAll();
                    if(typeof triggerSync === 'function') triggerSync();
                }
            });
            return;
        }

        if (o.target && (o.target.type === 'textbox' || o.target.type === 'i-text' || o.target.type === 'text')) {
            o.target.enterEditing();
            o.target.selectAll();
            canvas.requestRenderAll();
        } else if (o.target && o.target.isIframe) {
            // Habilitar interacción con el iframe temporalmente
            const iframeEl = document.getElementById(o.target.iframeId);
            if (iframeEl) {
                iframeEl.style.pointerEvents = 'auto';
                if(typeof showToast === 'function') showToast('Iframe interactivo (Sal del video para moverlo)', 'ph-youtube-logo');
            }
        }
    });

    canvas.on('mouse:down', function(o) {
        isUserInteracting = true;
        if (templatesSidebar && !templatesSidebar.classList.contains('closed')) templatesSidebar.classList.add('closed');
        if (componentsSidebar && !componentsSidebar.classList.contains('closed')) componentsSidebar.classList.add('closed');
        
        if (isSpaceDown || currentTool === 'pan') {
            isPanning = true;
            canvas.defaultCursor = 'grabbing';
            var e = o.e;
            lastPanX = e.clientX;
            lastPanY = e.clientY;
            return;
        }
        if (currentTool === 'eraser') {
            isErasing = true;
            eraseObjectAt(o.e);
            return;
        }
        if (currentTool === 'arrow') {
            isDrawingArrow = true;
            const pointer = canvas.getPointer(o.e);
            
            // Snapping inicial
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
            }
            
            const points = [startX, startY, startX, startY];
            currentArrowLine = new fabric.Line(points, {
                strokeWidth: 3,
                fill: currentColor,
                stroke: currentColor,
                originX: 'center',
                originY: 'center',
                selectable: true,
                evented: true,
                lockMovementX: false,
                lockMovementY: false,
                lockScalingX: true,
                lockScalingY: true,
                lockRotation: true,
                hasControls: false,
                isArrowLine: true,
                parentArrowId: 'arrow_' + Date.now()
            });
            
            currentArrowHead = new fabric.Polygon([
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
            });
            
            canvas.add(currentArrowLine, currentArrowHead);
            return;
        }

        if (currentTool === 'shape') {
            isDrawingShape = true;
            var pointer = canvas.getPointer(o.e);
            origX = pointer.x;
            origY = pointer.y;
            
            let shapeConfig = {
                left: origX,
                top: origY,
                originX: 'left',
                originY: 'top',
                fill: currentColor,
                stroke: '#334155',
                strokeWidth: 2,
                selectable: true,
                isShape: true,
            };
            
            if (currentShapeType === 'rect') {
                shapeConfig.width = 0;
                shapeConfig.height = 0;
                shapeConfig.rx = 4;
                shapeConfig.ry = 4;
                currentShape = new fabric.Rect(shapeConfig);
            } else if (currentShapeType === 'circle') {
                shapeConfig.rx = 0;
                shapeConfig.ry = 0;
                currentShape = new fabric.Ellipse(shapeConfig);
            } else if (currentShapeType === 'triangle') {
                shapeConfig.width = 0;
                shapeConfig.height = 0;
                currentShape = new fabric.Triangle(shapeConfig);
            }
            
            canvas.add(currentShape);
            return;
        }

        if (currentTool !== 'frame' || frameMode !== 'free') return;
        isDrawingFrame = true;
        var pointer = canvas.getPointer(o.e);
        origX = pointer.x;
        origY = pointer.y;
        currentFrame = new fabric.Rect({
            left: origX,
            top: origY,
            originX: 'left',
            originY: 'top',
            width: pointer.x - origX,
            height: pointer.y - origY,
            fill: '#ffffff', // Fondo blanco
            strokeWidth: 0,  // Sin borde
            shadow: new fabric.Shadow({ color: 'rgba(0,0,0,0.08)', blur: 15, offsetX: 0, offsetY: 4 }),
            selectable: true,
            isFrame: true, // Custom flag
            rx: 8, ry: 8
        });
        canvas.add(currentFrame);
    });

    canvas.on('mouse:move', function(o) {
        if (isPanning) {
            var e = o.e;
            var deltaX = e.clientX - lastPanX;
            var deltaY = e.clientY - lastPanY;
            var vpt = canvas.viewportTransform;
            vpt[4] += deltaX;
            vpt[5] += deltaY;
            canvas.requestRenderAll();
            
            // Actualizar posiciones de elementos HTML sobre el canvas
            updateAnchorsPosition();
            updateContextMenuPosition();
            
            lastPanX = e.clientX;
            lastPanY = e.clientY;
            return;
        }
        
        // Throttling inteligente basado en la cantidad de usuarios
        let cursorThrottleMs = 50;
        if (channel && channel.members && channel.members.count > 10) {
            cursorThrottleMs = 150;
        } else if (channel && channel.members && channel.members.count > 5) {
            cursorThrottleMs = 100;
        }

        // Enviar coordenadas de cursor
        if (Date.now() - lastCursorSendTime > cursorThrottleMs && channel && currentUserSessionId && channel.members.me) {
            const pointer = canvas.getPointer(o.e);
            
            let actionText = '';
            let actionIcon = '';
            const activeObj = canvas.getActiveObject();
            
            if (currentTool === 'draw') { actionText = 'Dibujando'; actionIcon = '✏️'; }
            else if (currentTool === 'highlighter') { actionText = 'Resaltando'; actionIcon = '🖍️'; }
            else if (currentTool === 'eraser') { actionText = 'Borrando'; actionIcon = '🧽'; }
            else if (currentTool === 'arrow') { actionText = 'Conectando'; actionIcon = '↗️'; }
            else if (currentTool === 'text') { actionText = 'Texto'; actionIcon = '📝'; }
            else if (currentTool === 'sticky') { actionText = 'Nota'; actionIcon = '🟨'; }
            else if (currentTool === 'frame') { actionText = 'Marco'; actionIcon = '🖼️'; }
            else if (currentTool === 'select') {
                if (activeObj) {
                    if (activeObj.isEditing) {
                        actionText = 'Escribiendo...'; actionIcon = '⌨️';
                    } else {
                        actionText = 'Moviendo objeto'; actionIcon = '🖐️';
                    }
                }
            }

            channel.trigger('client-cursor-move', {
                userId: currentUserSessionId,
                name: channel.members.me.info.name || 'Usuario',
                avatar: channel.members.me.info.avatar,
                x: pointer.x,
                y: pointer.y,
                actionText: actionText,
                actionIcon: actionIcon
            });
            lastCursorSendTime = Date.now();
        }

        if (isDrawingArrow && currentArrowLine && currentArrowHead) {
            const pointer = canvas.getPointer(o.e);
            
            // Snapping final
            const snappedObj = getClosestObjectCenter(pointer);
            let endX = pointer.x;
            let endY = pointer.y;
            if (snappedObj) {
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
            currentArrowHead.set({ angle: angle });
            
            canvas.requestRenderAll();
            return;
        }
        
        if (isErasing) {
            eraseObjectAt(o.e);
            return;
        }

        if (isDrawingShape && currentShape) {
            var pointer = canvas.getPointer(o.e);
            if (origX > pointer.x) { currentShape.set({ left: pointer.x }); } else { currentShape.set({ left: origX }); }
            if (origY > pointer.y) { currentShape.set({ top: pointer.y }); } else { currentShape.set({ top: origY }); }
            
            if (currentShapeType === 'rect' || currentShapeType === 'triangle') {
                currentShape.set({ width: Math.abs(origX - pointer.x) });
                currentShape.set({ height: Math.abs(origY - pointer.y) });
            } else if (currentShapeType === 'circle') {
                currentShape.set({ rx: Math.abs(origX - pointer.x) / 2 });
                currentShape.set({ ry: Math.abs(origY - pointer.y) / 2 });
            }
            canvas.requestRenderAll();
            return;
        }

        if (!isDrawingFrame) return;
        var pointer = canvas.getPointer(o.e);
        if(origX > pointer.x){
            currentFrame.set({ left: Math.abs(pointer.x) });
        }
        if(origY > pointer.y){
            currentFrame.set({ top: Math.abs(pointer.y) });
        }
        currentFrame.set({ width: Math.abs(origX - pointer.x) });
        currentFrame.set({ height: Math.abs(origY - pointer.y) });
        canvas.requestRenderAll();
    });

    canvas.on('mouse:up', function(o) {
        isUserInteracting = false;
        clearSnapLines();
        
        // Limpieza automática de trazos microscópicos accidentales
        const objects = canvas.getObjects();
        let cleaned = false;
        objects.forEach(obj => {
            if (obj.type === 'path' && obj.width < 3 && obj.height < 3) {
                canvas.remove(obj);
                cleaned = true;
            }
        });
        if (cleaned) canvas.requestRenderAll();
        
        if (isPanning) {
            isPanning = false;
            canvas.defaultCursor = (currentTool === 'pan') ? 'grab' : ((currentTool === 'select') ? 'default' : 'crosshair');
            return;
        }
        if (isErasing) {
            isErasing = false;
            return;
        }
        if (isDrawingArrow) {
            isDrawingArrow = false;
            
            const pointer = canvas.getPointer(o.e);
            const snappedObj = getClosestObjectCenter(pointer);
            const arrowEndTarget = snappedObj ? ensureId(snappedObj) : null;
            
            currentArrowLine.set({
                fromId: arrowStartTarget,
                toId: arrowEndTarget
            });
            
            triggerSync();
            setActiveTool(btnSelect, 'select');
            return;
        }

        if (isDrawingShape) {
            isDrawingShape = false;
            if (currentShape) {
                currentShape.setCoords();
                canvas.setActiveObject(currentShape);
                triggerSync();
            }
            setActiveTool(btnSelect, 'select');
            return;
        }

        if (!isDrawingFrame) return;
        isDrawingFrame = false;
        currentFrame.setCoords();
        currentFrame.sendToBack(); // Enviar al fondo para que actúe como mesa de trabajo
        canvas.setActiveObject(currentFrame);
        triggerSync();
        setActiveTool(btnSelect, 'select'); // Auto revert to select
    });

    function updateZoomTextUI() {
        if (zoomLevelText) zoomLevelText.innerText = Math.round(canvas.getZoom() * 100) + '%';
    }

    if (btnZoomIn) {
        btnZoomIn.addEventListener('click', () => {
            let zoom = canvas.getZoom();
            zoom *= 1.2;
            if (zoom > 5) zoom = 5;
            canvas.zoomToPoint({ x: canvas.width / 2, y: canvas.height / 2 }, zoom);
            updateZoomTextUI();
            updateAnchorsPosition();
            updateContextMenuPosition();
            canvas.requestRenderAll();
        });
    }

    if (btnZoomOut) {
        btnZoomOut.addEventListener('click', () => {
            let zoom = canvas.getZoom();
            zoom /= 1.2;
            if (zoom < 0.1) zoom = 0.1;
            canvas.zoomToPoint({ x: canvas.width / 2, y: canvas.height / 2 }, zoom);
            updateZoomTextUI();
            updateAnchorsPosition();
            updateContextMenuPosition();
            canvas.requestRenderAll();
        });
    }

    if (zoomLevelText) {
        zoomLevelText.addEventListener('click', () => {
            canvas.setZoom(1);
            canvas.viewportTransform[4] = 0;
            canvas.viewportTransform[5] = 0;
            updateZoomTextUI();
            updateAnchorsPosition();
            updateContextMenuPosition();
            canvas.requestRenderAll();
        });
    }

    // Evento para Zoom con Ctrl + Rueda del Ratón y Pan con Trackpad
    canvas.on('mouse:wheel', function(opt) {
        if (opt.e.ctrlKey || opt.e.metaKey) {
            opt.e.preventDefault();
            opt.e.stopPropagation();
            
            var delta = opt.e.deltaY;
            var zoom = canvas.getZoom();
            zoom *= 0.999 ** delta;
            if (zoom > 5) zoom = 5;
            if (zoom < 0.1) zoom = 0.1;
            canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
            
            updateZoomTextUI();
            
            // Actualizar posiciones
            updateAnchorsPosition();
            updateContextMenuPosition();
        } else {
            // Pan normal (Trackpad o Rueda normal sin Ctrl)
            opt.e.preventDefault();
            opt.e.stopPropagation();
            var vpt = canvas.viewportTransform;
            vpt[4] -= opt.e.deltaX;
            vpt[5] -= opt.e.deltaY;
            canvas.requestRenderAll();
            
            // Actualizar posiciones
            updateAnchorsPosition();
            updateContextMenuPosition();
        }
    });
    if (btnClear) {
        btnClear.addEventListener('click', () => {
            Swal.fire({
                title: '¿Limpiar todo?',
                text: "Se borrarán todos los elementos del lienzo.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, limpiar'
            }).then((result) => {
                if (result.isConfirmed) {
                    canvas.clear();
                    triggerSync();
                }
            });
        });
    }
    const btnExportPdf = document.getElementById('tool-export-pdf');
    if (btnExportPdf) {
        btnExportPdf.addEventListener('click', () => {
            if (canvas.getObjects().length === 0) {
                Swal.fire('Pizarra vacía', 'No hay elementos para exportar.', 'info');
                return;
            }
            
            // Deseleccionar para ocultar controles
            canvas.discardActiveObject();
            canvas.requestRenderAll();
            
            let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
            canvas.getObjects().forEach(obj => {
                const br = obj.getBoundingRect();
                if (br.left < minX) minX = br.left;
                if (br.top < minY) minY = br.top;
                if (br.left + br.width > maxX) maxX = br.left + br.width;
                if (br.top + br.height > maxY) maxY = br.top + br.height;
            });

            const padding = 40;
            const width = (maxX - minX) + padding * 2;
            const height = (maxY - minY) + padding * 2;

            const dataUrl = canvas.toDataURL({
                format: 'jpeg',
                quality: 1,
                left: minX - padding,
                top: minY - padding,
                width: width,
                height: height,
                multiplier: 2 // Retina display quality
            });

            const orientation = width > height ? 'l' : 'p';
            const doc = new window.jspdf.jsPDF({
                orientation: orientation,
                unit: 'px',
                format: [width, height]
            });

            doc.addImage(dataUrl, 'JPEG', 0, 0, width, height);
            doc.save('Pizarra_Whiteboard.pdf');
        });
    }

    // Manejar atajos de teclado (Borrar, Copiar, Pegar, Duplicar, Panning, Agrupar, Bloquear)
    let _clipboard = null;

    window.addEventListener('keydown', (e) => {
        // Evitar si estamos escribiendo en un input, textarea o dentro de un Textbox en edición
        const isInput = e.target.tagName.toLowerCase() === 'input' || e.target.tagName.toLowerCase() === 'textarea';
        const activeObj = canvas.getActiveObject();
        const isEditingText = activeObj && activeObj.isEditing;

        if (!isInput && !isEditingText && (e.ctrlKey || e.metaKey) && !isViewer) {
            if (e.key.toLowerCase() === 'z') {
                e.preventDefault();
                doUndo();
                return;
            }
            if (e.key.toLowerCase() === 'y') {
                e.preventDefault();
                doRedo();
                return;
            }
        }

        // Spacebar para Panning
        if (e.code === 'Space' && !isInput && !isEditingText) {
            if (!isSpaceDown) {
                isSpaceDown = true;
                canvas.defaultCursor = 'grab';
                canvas.selection = false;
                canvas.discardActiveObject();
                canvas.getObjects().forEach(obj => {
                    if (!obj.hasOwnProperty('_prevEvented')) {
                        obj._prevEvented = obj.evented;
                    }
                    obj.evented = false;
                });
                canvas.requestRenderAll();
            }
            e.preventDefault(); // Previene scroll de la página
            return;
        }
        
        if (isInput || isEditingText) return;

        // --- ATAJOS DE HERRAMIENTAS DIRECTAS ---
        if (!e.ctrlKey && !e.metaKey && !e.altKey && !e.shiftKey) {
            const key = e.key.toLowerCase();
            if (key === 'v') { if (btnSelect) btnSelect.click(); return; }
            if (key === 't') { if (btnText) btnText.click(); return; }
            if (key === 'm') { if (btnFrame) btnFrame.click(); return; }
            if (key === 'p' || key === 'd') { if (btnDraw) btnDraw.click(); return; } // Pen/Draw
            if (key === 'h') { if (btnHighlighter) btnHighlighter.click(); return; } // Highlighter
            if (key === 'e') { if (btnEraser) btnEraser.click(); return; } // Eraser
        }

        // --- AGRUPAR (Ctrl + G) ---
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key.toLowerCase() === 'g') {
            e.preventDefault();
            if (activeObj && activeObj.type === 'activeSelection') {
                activeObj.toGroup();
                canvas.requestRenderAll();
                triggerSync();
            }
            return;
        }

        // --- DESAGRUPAR (Ctrl + Shift + G) ---
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'g') {
            e.preventDefault();
            if (activeObj && activeObj.type === 'group' && !activeObj.isFrame && !activeObj.isIframe && !activeObj.isComment) {
                activeObj.toActiveSelection();
                canvas.requestRenderAll();
                triggerSync();
            }
            return;
        }

        // Eliminar (Delete / Backspace)
        if ((e.key === 'Delete' || e.key === 'Backspace') && !isViewer) {
            if (canvas.getActiveObjects().length > 0) {
                const activeObjects = canvas.getActiveObjects().filter(obj => !obj.locked);
                if (activeObjects.length > 0) {
                    activeObjects.forEach(obj => {
                        canvas.remove(obj);
                        if (typeof removeArrowDependencies === 'function') removeArrowDependencies(obj);
                    });
                    canvas.discardActiveObject();
                    if (typeof hideContextMenu === 'function') hideContextMenu();
                    triggerSync();
                }
            }
        }
        
        // Atributos adicionales a clonar para no perder el formato
        const extraProps = CANVAS_EXTRA_PROPS;

        // Ctrl+C y Ctrl+V ahora se manejan en los eventos nativos 'copy' y 'paste'
        // para soportar pegar en aplicaciones externas como WhatsApp.
        
        // Duplicar (Ctrl + D)
        if (e.ctrlKey && (e.key === 'd' || e.key === 'D')) {
            e.preventDefault(); // Evitar agregar a marcadores
            if (activeObj) {
                activeObj.clone((cloned) => {
                    canvas.discardActiveObject();
                    cloned.set({
                        left: cloned.left + 20,
                        top: cloned.top + 20,
                        evented: true,
                    });
                    if (cloned.type === 'activeSelection') {
                        cloned.canvas = canvas;
                        cloned.forEachObject((obj) => canvas.add(obj));
                        cloned.setCoords();
                    } else {
                        canvas.add(cloned);
                    }
                    canvas.setActiveObject(cloned);
                    canvas.requestRenderAll();
                    triggerSync();
                }, extraProps);
            }
        }
    });

    window.addEventListener('keyup', (e) => {
        if (e.code === 'Space') {
            isSpaceDown = false;
            isPanning = false;
            canvas.defaultCursor = currentTool === 'select' ? 'default' : (currentTool === 'text' || currentTool === 'sticky' ? 'crosshair' : 'default');
            canvas.selection = currentTool === 'select';
            canvas.getObjects().forEach(obj => {
                if (obj.hasOwnProperty('_prevEvented')) {
                    obj.evented = obj._prevEvented;
                    delete obj._prevEvented;
                } else {
                    obj.evented = true;
                }
            });
            canvas.requestRenderAll();
        }
    });

    // Failsafe por si el navegador pierde el foco mientras la barra espaciadora estaba presionada
    window.addEventListener('blur', () => {
        if (isSpaceDown) {
            isSpaceDown = false;
            isPanning = false;
            canvas.defaultCursor = 'default';
            canvas.selection = true;
            canvas.getObjects().forEach(obj => {
                if (obj.hasOwnProperty('_prevEvented')) {
                    obj.evented = obj._prevEvented;
                    delete obj._prevEvented;
                } else {
                    obj.evented = true;
                }
            });
            canvas.requestRenderAll();
        }
    });

    // --- EVENTO DE COPIAR (Soporte para apps externas) ---
    window.addEventListener('copy', (e) => {
        const isInput = e.target.tagName.toLowerCase() === 'input' || e.target.tagName.toLowerCase() === 'textarea';
        const activeObj = canvas.getActiveObject();
        if (isInput || (activeObj && activeObj.isEditing)) return;

        if (activeObj) {
            e.preventDefault();
            
            // 1. Clonado interno para mantener las propiedades de Fabric (ej. edición de texto) al pegar dentro de la app
            activeObj.clone((cloned) => {
                _clipboard = cloned;
            }, CANVAS_EXTRA_PROPS);

            // 2. Marcar texto para saber que es una copia interna
            e.clipboardData.setData('text/plain', 'WB_INTERNAL_COPY');

            // 3. Exportar como imagen al sistema operativo (para WhatsApp, Telegram, etc.)
            try {
                if (navigator.clipboard && navigator.clipboard.write) {
                    // Generar imagen con buena resolución
                    const dataUrl = activeObj.toDataURL({ format: 'png', quality: 1, multiplier: 2 });
                    fetch(dataUrl).then(res => res.blob()).then(blob => {
                        navigator.clipboard.write([
                            new ClipboardItem({ 
                                'image/png': blob,
                                'text/plain': new Blob(['WB_INTERNAL_COPY'], { type: 'text/plain' })
                            })
                        ]).then(() => {
                            if(typeof showToast === 'function') showToast('Copiado (disponible para otras apps)', 'ph-copy');
                        }).catch(err => console.log('Clipboard write prevent', err));
                    });
                }
            } catch (err) {
                console.log("CORS o error exportando imagen", err);
            }
        }
    });

    // --- EVENTO DE PEGAR ---
    window.addEventListener('paste', (e) => {
        // No interceptar si estamos escribiendo en un input/textarea
        const isInput = e.target.tagName.toLowerCase() === 'input' || e.target.tagName.toLowerCase() === 'textarea';
        if (isInput) return;

        // No interceptar si estamos editando texto en el canvas
        const activeObj = canvas.getActiveObject();
        if (activeObj && activeObj.isEditing) return;

        // Comprobar si es nuestra copia interna
        const pastedText = e.clipboardData && e.clipboardData.getData('text/plain');
        if (pastedText === 'WB_INTERNAL_COPY' && _clipboard) {
            e.preventDefault();
            e.stopPropagation();
            _clipboard.clone((clonedObj) => {
                canvas.discardActiveObject();
                clonedObj.set({ left: clonedObj.left + 20, top: clonedObj.top + 20, evented: true });
                if (clonedObj.type === 'activeSelection') {
                    clonedObj.canvas = canvas;
                    clonedObj.forEachObject((obj) => canvas.add(obj));
                    clonedObj.setCoords();
                } else {
                    canvas.add(clonedObj);
                }
                _clipboard.top += 20;
                _clipboard.left += 20;
                canvas.setActiveObject(clonedObj);
                canvas.requestRenderAll();
                triggerSync();
            }, CANVAS_EXTRA_PROPS);
            return;
        }

        const items = e.clipboardData && e.clipboardData.items;
        if (!items) return;

        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                e.preventDefault();
                e.stopPropagation();

                const blob = items[i].getAsFile();
                if (!blob) continue;

                const formData = new FormData();
                formData.append('image', blob);

                if (typeof showUploadLoader === 'function') {
                    showUploadLoader('Subiendo imagen...');
                }

                fetch('ajax/upload_whiteboard_image.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.url) {
                        fabric.Image.fromURL(res.url, function(img) {
                            const vpt = canvas.viewportTransform;
                            const zoom = canvas.getZoom();
                            const canvasEl = canvas.getElement();
                            const centerX = (-vpt[4] + canvasEl.clientWidth / 2) / zoom;
                            const centerY = (-vpt[5] + canvasEl.clientHeight / 2) / zoom;

                            const maxDim = 800;
                            let scale = 1;
                            if (img.width > maxDim || img.height > maxDim) {
                                scale = maxDim / Math.max(img.width, img.height);
                            }

                            img.set({
                                left: centerX,
                                top: centerY,
                                originX: 'center',
                                originY: 'center',
                                scaleX: scale,
                                scaleY: scale
                            });

                            canvas.add(img);
                            canvas.setActiveObject(img);
                            canvas.requestRenderAll();
                            triggerSync();

                            if (typeof setStatus === 'function') {
                                setStatus('Imagen pegada ✓', '#10b981');
                            }
                            if (typeof hideUploadLoader === 'function') hideUploadLoader();
                        }, { crossOrigin: 'anonymous' });
                    } else {
                        if (typeof setStatus === 'function') setStatus('Error subiendo imagen', '#ef4444');
                        if (typeof hideUploadLoader === 'function') hideUploadLoader();
                        alert(res.error || 'Error subiendo imagen a Google Drive');
                    }
                })
                .catch(err => {
                    console.error('Error pasting image', err);
                    if (typeof setStatus === 'function') setStatus('Error subiendo imagen', '#ef4444');
                    if (typeof hideUploadLoader === 'function') hideUploadLoader();
                });
                return; // Solo procesar la primera imagen
            }
        }

        // Si no se pegó una imagen, intentar pegar texto (la variable pastedText ya se obtuvo arriba)
        if (pastedText && pastedText.trim().length > 0) {
            let hasImage = false;
            if (items) {
                for (let i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) hasImage = true;
                }
            }
            if (!hasImage) {
                e.preventDefault();
                e.stopPropagation();
                
                if (pastedText.trim().match(/^https?:\/\/.+/)) {
                    createLinkCard(pastedText.trim());
                    return;
                }
                
                const vpt = canvas.viewportTransform;
                const zoom = canvas.getZoom();
                const canvasEl = canvas.getElement();
                const centerX = (-vpt[4] + canvasEl.clientWidth / 2) / zoom;
                const centerY = (-vpt[5] + canvasEl.clientHeight / 2) / zoom;

                const textObj = new fabric.Textbox(pastedText, {
                    left: centerX,
                    top: centerY,
                    width: Math.min(500, (canvasEl.clientWidth / zoom) - 40),
                    originX: 'center',
                    originY: 'center',
                    fontFamily: 'Inter, sans-serif',
                    fill: currentColor || '#334155',
                    fontSize: 24
                });
                
                canvas.add(textObj);
                canvas.setActiveObject(textObj);
                canvas.requestRenderAll();
                triggerSync();
                
                if (typeof setStatus === 'function') {
                    setStatus('Texto pegado ✓', '#10b981');
                }
            }
        }
    });

    // 3. Sincronización (AJAX y Pusher)
    function setStatus(text, color = '#10b981') {
        statusIndicator.innerHTML = `<span class="status-dot" style="width:8px;height:8px;border-radius:50%;background:${color};display:inline-block;"></span> ${text}`;
    }

    let hasUnsavedChanges = false;
    const btnManualSave = document.getElementById('btn-manual-save');
    const txtManualSave = document.getElementById('manual-save-text');

    function markUnsavedChanges() {
        if (isUpdatingFromPusher) return;
        hasUnsavedChanges = true;
        if (btnManualSave && txtManualSave) {
            btnManualSave.style.background = '#3b82f6';
            btnManualSave.style.color = '#ffffff';
            btnManualSave.disabled = false;
            btnManualSave.style.cursor = 'pointer';
            txtManualSave.innerText = 'Guardar cambios';
        }
    }

    if (btnManualSave) {
        btnManualSave.addEventListener('click', () => {
            if (hasUnsavedChanges) {
                btnManualSave.style.background = '#f59e0b';
                btnManualSave.style.color = '#ffffff';
                if (txtManualSave) txtManualSave.innerText = 'Guardando...';
                saveStateToDB();
            }
        });
    }

    function saveStateToDB() {
        if (isUpdatingFromPusher) return;

        // Auto-borrado de trazos microscópicos (compresión)
        const objects = canvas.getObjects();
        objects.forEach(obj => {
            if (obj.type === 'path' && obj.width < 3 && obj.height < 3) {
                canvas.remove(obj);
            }
        });

        setStatus('Guardando...', '#f59e0b');
        const extraProps = CANVAS_EXTRA_PROPS;
        const jsonObj = canvas.toJSON(extraProps);
        jsonObj.vpt = canvas.viewportTransform;
        const json = JSON.stringify(jsonObj);
        
        // Generar miniatura ultra ligera
        const thumbnail = canvas.toDataURL({
            format: 'jpeg',
            quality: 0.2,
            multiplier: 0.1
        });
        
        fetch('ajax/ajax_whiteboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save', id: boardId, content: json, thumbnail: thumbnail })
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                setStatus('Sincronizado', '#10b981');
                hasUnsavedChanges = false;
                if (btnManualSave && txtManualSave) {
                    btnManualSave.style.background = '#e2e8f0';
                    btnManualSave.style.color = '#64748b';
                    btnManualSave.disabled = true;
                    btnManualSave.style.cursor = 'default';
                    txtManualSave.innerText = 'Guardado';
                }
            }
            else {
                setStatus('Error al guardar', '#ef4444');
                if (txtManualSave) txtManualSave.innerText = 'Error';
            }
        }).catch(() => {
            setStatus('Error de red', '#ef4444');
            if (txtManualSave) txtManualSave.innerText = 'Error';
        });
    }

    function broadcastCanvasChange() {
        if (isUpdatingFromPusher) return;
        
        // 2. Transmitir evento a Pusher (Throttled, Ultraligero, solo un ping)
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
                        // NO ENVIAMOS canvasData AQUI PARA EVITAR EL LIMITE DE 10KB
                    }
                })
            }).catch(e => console.error("Error broadcast Pusher", e));
        }
    }

    // --- History Logic (Undo/Redo) ---
    let stateHistory = [];
    let redoHistory = [];
    let isHistoryOperating = false;
    let isInitializing = true;

    function saveHistoryState() {
        if (isHistoryOperating || isUpdatingFromPusher) return;
        const jsonObj = canvas.toJSON(CANVAS_EXTRA_PROPS);
        jsonObj.vpt = canvas.viewportTransform;
        const json = JSON.stringify(jsonObj);
        if (stateHistory.length > 0 && stateHistory[stateHistory.length - 1] === json) return;
        
        stateHistory.push(json);
        if (stateHistory.length > 15) stateHistory.shift();
        if (!isInitializing) redoHistory = [];
    }

    function doUndo() {
        if (stateHistory.length <= 1) return;
        isHistoryOperating = true;
        redoHistory.push(stateHistory.pop());
        const previousState = stateHistory[stateHistory.length - 1];
        
        canvas.loadFromJSON(cleanCanvasJSON(previousState), () => {
            enforceLocks();
            const parsed = JSON.parse(previousState);
            if (parsed.vpt) canvas.setViewportTransform(parsed.vpt);
            canvas.requestRenderAll();
            isHistoryOperating = false;
            triggerSync();
        });
    }

    function doRedo() {
        if (redoHistory.length === 0) return;
        isHistoryOperating = true;
        const nextState = redoHistory.pop();
        stateHistory.push(nextState);
        
        canvas.loadFromJSON(cleanCanvasJSON(nextState), () => {
            enforceLocks();
            const parsed = JSON.parse(nextState);
            if (parsed.vpt) canvas.setViewportTransform(parsed.vpt);
            canvas.requestRenderAll();
            isHistoryOperating = false;
            triggerSync();
        });
    }

    const btnUndo = document.getElementById('tool-undo');
    const btnRedo = document.getElementById('tool-redo');
    if (btnUndo) btnUndo.addEventListener('click', doUndo);
    if (btnRedo) btnRedo.addEventListener('click', doRedo);

    let pusherTimeout;
    function triggerSync() {
        if (isUpdatingFromPusher) return;
        
        markUnsavedChanges();
        
        clearTimeout(pusherTimeout);
        pusherTimeout = setTimeout(() => {
            broadcastCanvasChange();
        }, 1000);
    }

    function broadcastDelta(obj, action) {
        if (isUpdatingFromPusher || !channel || !currentUserSessionId) return;
        if (!obj || obj.type === 'activeSelection') return;
        
        fetch('ajax/ajax_whiteboard_pusher.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'trigger',
                board_id: boardId,
                event: 'canvas-delta',
                data: {
                    userId: currentUserSessionId,
                    action: action,
                    objData: obj.toJSON(CANVAS_EXTRA_PROPS)
                }
            })
        }).catch(e => console.error("Error broadcast delta", e));
    }

    function handleObjectChange() {
        saveHistoryState();
        triggerSync();
    }

    // Eventos de Fabric para disparar sincronización Delta
    canvas.on('object:modified', (e) => {
        if (e.target && e.target.isGuide) return;
        if (e.target && !isUpdatingFromPusher) {
            if (e.target.type === 'activeSelection') {
                e.target.forEachObject(obj => broadcastDelta(obj, 'modified'));
            } else {
                broadcastDelta(e.target, 'modified');
            }
        }
        handleObjectChange();
    });
    canvas.on('object:added', (e) => {
        if (e.target && e.target.isGuide) return;
        if (e.target && !e.target.id && !isUpdatingFromPusher) {
            e.target.id = 'obj_' + Date.now() + '_' + Math.floor(Math.random()*1000);
        }
        if (e.target && !isUpdatingFromPusher) {
            broadcastDelta(e.target, 'added');
        }
        handleObjectChange();
    });
    canvas.on('object:removed', (e) => {
        if (e.target && e.target.isGuide) return;
        if (e.target && !isUpdatingFromPusher) {
            if (e.target.type === 'activeSelection') {
                e.target.forEachObject(obj => broadcastDelta(obj, 'removed'));
            } else {
                broadcastDelta(e.target, 'removed');
            }
        }
        handleObjectChange();
    });
    canvas.on('path:created', (e) => {
        if (e.path && !e.path.id && !isUpdatingFromPusher) {
            e.path.id = 'path_' + Date.now() + '_' + Math.floor(Math.random()*1000);
            broadcastDelta(e.path, 'added');
        }
        handleObjectChange();
    });

    // 4. Cargar estado inicial
    function cleanCanvasJSON(jsonStr) {
        if (!jsonStr) return jsonStr;
        try {
            let obj = typeof jsonStr === 'string' ? JSON.parse(jsonStr) : jsonStr;
            function removeNulls(node) {
                if (node && node.objects && Array.isArray(node.objects)) {
                    node.objects = node.objects.filter(o => o !== null && o !== undefined);
                    node.objects.forEach(removeNulls);
                }
            }
            removeNulls(obj);
            return obj;
        } catch(e) {
            console.error("Error cleaning JSON", e);
            return jsonStr;
        }
    }

    fetch('ajax/ajax_whiteboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'load', id: boardId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.content) {
            isUpdatingFromPusher = true; // prevent re-trigger
            canvas.loadFromJSON(cleanCanvasJSON(res.content), () => {
                // Eliminar lineas guia guardadas por error
                const oldGuides = canvas.getObjects('line').filter(o => o.stroke === '#ef4444' && o.strokeDashArray && o.strokeDashArray[0] === 5 && o.strokeWidth === 1);
                oldGuides.forEach(o => canvas.remove(o));

                enforceLocks();
                const parsed = JSON.parse(res.content);
                if (parsed.vpt) canvas.setViewportTransform(parsed.vpt);
                canvas.requestRenderAll();
                isUpdatingFromPusher = false;
                isInitializing = false;
                saveHistoryState();
            });
        } else {
            isInitializing = false;
            saveHistoryState();
        }
    });

    // 5. Configurar Pusher
    // Usamos las claves que se ven en ajax_pusher_auth.php
    Pusher.logToConsole = true;

    const pusher = new Pusher('b31f38612d61b0285c78', {
        cluster: 'us2',
        authEndpoint: 'ajax_pusher_auth.php'
    });

    // Pusher requires a presence channel to use authEndpoint, but for simple broadcast 
    // a public or private channel might suffice. Since ajax_pusher_auth handles presence, we use presence
    const channel = pusher.subscribe('presence-whiteboard-' + boardId);

    channel.bind('pusher:subscription_succeeded', (members) => {
        currentUserSessionId = members.myID;
        console.log("Suscrito a la pizarra. Miembros online: ", members.count);
        updateActiveUsers(members);
    });

    channel.bind('pusher:member_added', (member) => {
        updateActiveUsers(channel.members);
    });

    channel.bind('pusher:member_removed', (member) => {
        updateActiveUsers(channel.members);
        removeCursor(member.id);
    });

    // Avatars
    const activeUsersContainer = document.getElementById('wb-active-users');
    function updateActiveUsers(members) {
        if (!activeUsersContainer) return;
        activeUsersContainer.innerHTML = '';
        members.each((member) => {
            const name = member.info?.name || 'Usuario';
            const avatar = member.info?.avatar;
            const div = document.createElement('div');
            div.style.cssText = `width:32px;height:32px;border-radius:50%;background:#10b981;color:#fff;
                                 display:flex;align-items:center;justify-content:center;font-size:12px;
                                 border:2px solid #fff;margin-left:-10px;position:relative; overflow:hidden; box-shadow:0 2px 4px rgba(0,0,0,0.1);`;
            div.title = name;
            
            // Mark myself to differentiate if needed
            if (member.id === currentUserSessionId) {
                div.style.borderColor = '#3b82f6';
                div.title = name + ' (Tú)';
            }
            
            if (avatar) {
                div.innerHTML = `<img src="${avatar}" style="width:100%;height:100%;object-fit:cover;">`;
            } else {
                div.innerText = name.substring(0, 2).toUpperCase();
            }
            activeUsersContainer.appendChild(div);
        });
    }

    // Cursores en tiempo real
    const cursorsLayer = document.createElement('div');
    cursorsLayer.style.cssText = 'position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:50; overflow:hidden;';
    document.getElementById('canvas-wrapper').appendChild(cursorsLayer);

    const activeCursors = {};
    const cursorTimeouts = {};
    const cursorColors = ['#ef4444', '#f97316', '#84cc16', '#06b6d4', '#8b5cf6', '#ec4899'];

    function updateCursor(id, name, avatar, x, y, actionText = '', actionIcon = '') {
        if (!activeCursors[id]) {
            const cursorColor = cursorColors[Object.keys(activeCursors).length % cursorColors.length];
            const div = document.createElement('div');
            div.style.cssText = `position:absolute; transition: transform 0.1s linear, opacity 0.3s ease; transform-origin: 0 0; display:flex; align-items:flex-start; pointer-events:none; opacity: 1;`;
            
            let avatarHtml = '';
            if (avatar) {
                avatarHtml = `<img src="${avatar}" style="width:24px; height:24px; border-radius:50%; border:2px solid ${cursorColor}; object-fit:cover;">`;
            } else {
                avatarHtml = `<div style="width:24px; height:24px; border-radius:50%; background:${cursorColor}; color:#fff; border:2px solid #fff; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:bold;">${name.substring(0,2).toUpperCase()}</div>`;
            }

            div.innerHTML = `
                <svg width="24" height="30" viewBox="0 0 24 30" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0px 2px 2px rgba(0,0,0,0.2));">
                    <path d="M4 4L19 19H11L7 27L4 4Z" fill="${cursorColor}" stroke="white" stroke-width="2" stroke-linejoin="round"/>
                </svg>
                <div style="display:flex; flex-direction:column; align-items:flex-start; margin-left: 2px; margin-top: 15px; gap:4px;">
                    ${avatarHtml}
                    <div style="background:${cursorColor}; color:#fff; font-size:11px; padding:2px 8px; border-radius:12px; font-weight:600; white-space:nowrap; box-shadow:0 2px 5px rgba(0,0,0,0.15);">
                        ${name}
                    </div>
                    <div class="cursor-action-indicator" style="display:none; background:#ffffffcc; backdrop-filter:blur(4px); color:#1e293b; font-size:10px; padding:2px 8px; border-radius:10px; font-weight:600; white-space:nowrap; box-shadow:0 2px 5px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;">
                    </div>
                </div>
            `;
            cursorsLayer.appendChild(div);
            activeCursors[id] = div;
        }
        
        const actionIndicator = activeCursors[id].querySelector('.cursor-action-indicator');
        if (actionIndicator) {
            if (actionText) {
                actionIndicator.innerHTML = `${actionIcon} ${actionText}`;
                actionIndicator.style.display = 'block';
            } else {
                actionIndicator.style.display = 'none';
            }
        }
        
        const vpt = canvas.viewportTransform;
        const domX = x * vpt[0] + y * vpt[2] + vpt[4];
        const domY = x * vpt[1] + y * vpt[3] + vpt[5];

        activeCursors[id].style.transform = `translate(${domX}px, ${domY}px)`;
        activeCursors[id].style.opacity = '1';

        if (cursorTimeouts[id]) {
            clearTimeout(cursorTimeouts[id]);
        }

        cursorTimeouts[id] = setTimeout(() => {
            if (activeCursors[id]) {
                activeCursors[id].style.opacity = '0';
            }
        }, 5000);
    }

    function removeCursor(id) {
        if (activeCursors[id]) {
            activeCursors[id].remove();
            delete activeCursors[id];
        }
        if (cursorTimeouts[id]) {
            clearTimeout(cursorTimeouts[id]);
            delete cursorTimeouts[id];
        }
    }

    channel.bind('client-cursor-move', (data) => {
        updateCursor(data.userId, data.name, data.avatar, data.x, data.y, data.actionText, data.actionIcon);
    });

    let lastCursorSendTime = 0;

    channel.bind('canvas-delta', (data) => {
        if (data.userId === currentUserSessionId) return;
        if (!data.objData || !data.objData.id) return;
        
        isUpdatingFromPusher = true;
        setStatus('Sincronizando...', '#3b82f6');
        
        const action = data.action;
        const objData = data.objData;
        const id = objData.id;
        
        let existingObj = null;
        const objects = canvas.getObjects();
        for (let i=0; i<objects.length; i++) {
            if (objects[i].id === id) {
                existingObj = objects[i];
                break;
            }
        }
        
        if (action === 'removed') {
            if (existingObj) {
                canvas.remove(existingObj);
                canvas.requestRenderAll();
            }
            setTimeout(() => { isUpdatingFromPusher = false; setStatus('Sincronizado', '#10b981'); }, 50);
        } else if (action === 'added' || action === 'modified') {
            fabric.util.enlivenObjects([objData], function(enlivenedObjects) {
                if (enlivenedObjects && enlivenedObjects.length > 0) {
                    const newObj = enlivenedObjects[0];
                    if (existingObj) {
                        const idx = canvas.getObjects().indexOf(existingObj);
                        canvas.remove(existingObj);
                        canvas.insertAt(newObj, idx, false);
                    } else {
                        canvas.add(newObj);
                    }
                    enforceLocks();
                    canvas.requestRenderAll();
                }
                setTimeout(() => { isUpdatingFromPusher = false; setStatus('Sincronizado', '#10b981'); }, 50);
            });
        } else {
            setTimeout(() => { isUpdatingFromPusher = false; }, 50);
        }
    });

    // 6. Menú Contextual Flotante y Estilos Avanzados
    const ctxMenu = document.getElementById('wb-context-menu');
    const ctxFront = document.getElementById('ctx-front');
    const ctxBack = document.getElementById('ctx-back');
    const ctxLock = document.getElementById('ctx-lock');
    const ctxDelete = document.getElementById('ctx-delete');
    const ctxBringFront = document.getElementById('ctx-bring-front');
    const ctxSendBack = document.getElementById('ctx-send-back');
    const ctxDuplicate = document.getElementById('ctx-duplicate');
    const ctxGroup = document.getElementById('ctx-group');

    function updateContextMenuPosition() {
        const activeObj = canvas.getActiveObject();
        if (!activeObj || !ctxMenu || activeObj.isComment || activeObj.isComponent) {
            hideContextMenu();
            return;
        }
        
        // Actualizar el estado del icono del candado
        if (ctxLock) {
            if (activeObj.locked) {
                ctxLock.innerHTML = '<i class="ph ph-lock-key-open"></i>'; 
                ctxLock.title = 'Desbloquear';
                ctxLock.style.color = '#ef4444';
            } else {
                ctxLock.innerHTML = '<i class="ph ph-lock-key"></i>'; 
                ctxLock.title = 'Bloquear';
                ctxLock.style.color = 'inherit';
            }
        }
        
        const ctxMultiAlign = document.getElementById('ctx-multi-align');
        if (ctxMultiAlign) {
            ctxMultiAlign.style.display = (activeObj.type === 'activeSelection') ? 'flex' : 'none';
        }
        
        if (ctxGroup) {
            if (activeObj.type === 'activeSelection') {
                ctxGroup.style.display = 'block';
                ctxGroup.title = 'Agrupar';
                ctxGroup.innerHTML = '<i class="ph ph-intersect"></i>';
            } else if (activeObj.type === 'group' && !activeObj.isComponent && !activeObj.isFrame) {
                ctxGroup.style.display = 'block';
                ctxGroup.title = 'Desagrupar';
                ctxGroup.innerHTML = '<i class="ph ph-exclude"></i>';
            } else {
                ctxGroup.style.display = 'none';
            }
        }
        
        const ctxCrop = document.getElementById('ctx-crop');
        if (ctxCrop) {
            ctxCrop.style.display = (activeObj.type === 'image') ? 'block' : 'none';
        }
        
        const ctxInteract = document.getElementById('ctx-interact');
        if (ctxInteract) {
            ctxInteract.style.display = (activeObj.isIframe) ? 'block' : 'none';
        }
        
        const ctxCopyImage = document.getElementById('ctx-copy-image');
        if (ctxCopyImage) {
            ctxCopyImage.style.display = 'block'; // Mostrar por defecto para objetos copiables
        }

        const ctxLockIcon = document.getElementById('ctx-lock-icon');
        if (ctxLockIcon) {
            ctxLockIcon.className = activeObj.locked ? 'ph ph-lock-key' : 'ph ph-lock-key-open';
            ctxLockIcon.style.color = activeObj.locked ? '#ef4444' : '#64748b';
        }
        
        const ctxShapeControls = document.getElementById('ctx-shape-controls');
        if (ctxShapeControls) {
            if (activeObj.isShape) {
                ctxShapeControls.style.display = 'flex';
                
                // Actualizar valores de los inputs
                const fillInput = document.getElementById('ctx-shape-fill');
                const strokeInput = document.getElementById('ctx-shape-stroke');
                
                if (fillInput) {
                    if (activeObj.fill === 'transparent' || !activeObj.fill) {
                        fillInput.value = '#ffffff'; // Color dummy si es transparente
                    } else {
                        fillInput.value = activeObj.fill;
                    }
                }
                
                if (strokeInput) {
                    if (activeObj.stroke === 'transparent' || !activeObj.stroke) {
                        strokeInput.value = '#000000'; // Color dummy si es transparente
                    } else {
                        strokeInput.value = activeObj.stroke;
                    }
                }
            } else {
                ctxShapeControls.style.display = 'none';
            }
        }

        const ctxTextControls = document.getElementById('ctx-text-controls');
        if (ctxTextControls) {
            // Ocultar herramientas de texto si es un marco o un iframe
            ctxTextControls.style.display = (activeObj.isFrame || activeObj.isIframe) ? 'none' : 'flex';
        }
        
        const ctxColors = document.getElementById('ctx-colors');
        if (ctxColors) {
            // Ocultar paleta de colores si es un iframe
            ctxColors.style.display = (activeObj.isIframe) ? 'none' : 'flex';
        }

        const boundingRect = activeObj.getBoundingRect();
        ctxMenu.style.display = 'flex';
        
        const menuHeight = ctxMenu.offsetHeight || 40;
        const menuWidth = ctxMenu.offsetWidth || 300;
        let top = boundingRect.top - menuHeight - 10;
        if (top < 0) top = boundingRect.top + boundingRect.height + 10;
        
        let left = boundingRect.left + (boundingRect.width / 2) - (menuWidth / 2);
        ctxMenu.style.left = Math.max(0, left) + 'px';
        ctxMenu.style.top = top + 'px';

        // Set current font
        if (activeObj.fontFamily && document.getElementById('ctx-font')) {
            document.getElementById('ctx-font').value = activeObj.fontFamily;
        }
    }

    function hideContextMenu() {
        if (ctxMenu) ctxMenu.style.display = 'none';
    }

    // Lógica del menú de fondos
    const btnBgToggle = document.getElementById('btn-bg-toggle');
    const bgDropdownMenu = document.getElementById('bg-dropdown-menu');
    if (btnBgToggle && bgDropdownMenu) {
        btnBgToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            bgDropdownMenu.style.display = bgDropdownMenu.style.display === 'none' ? 'flex' : 'none';
        });
        document.addEventListener('click', () => {
            bgDropdownMenu.style.display = 'none';
        });
        bgDropdownMenu.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    // --- Anchor Points (Quick Connectors) ---
    const anchorsContainer = document.createElement('div');
    anchorsContainer.id = 'wb-anchors';
    anchorsContainer.style.cssText = 'position: absolute; top: 0; left: 0; pointer-events: none; z-index: 60;';
    document.getElementById('canvas-wrapper').appendChild(anchorsContainer);

    const createAnchor = (pos) => {
        const dot = document.createElement('div');
        dot.className = 'wb-anchor-dot';
        dot.dataset.pos = pos;
        dot.style.cssText = `
            position: absolute; width: 14px; height: 14px; background: #3b82f6; 
            border: 2px solid #fff; border-radius: 50%; 
            transform: translate(-50%, -50%); cursor: crosshair;
            pointer-events: auto; display: none; box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            transition: transform 0.1s;
        `;
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
                strokeWidth: 3,
                fill: currentColor,
                stroke: currentColor,
                originX: 'center',
                originY: 'center',
                selectable: true,
                evented: true,
                lockMovementX: false,
                lockMovementY: false,
                lockScalingX: true,
                lockScalingY: true,
                lockRotation: true,
                hasControls: false,
                isArrowLine: true,
                parentArrowId: arrowId
            });
            
            currentArrowHead = null; // Solo una linea conectando para el anclaje
            canvas.add(currentArrowLine);
            
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
                
                // Return to select tool
                currentTool = 'select';
                document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
                const selectBtn = document.getElementById('tool-select');
                if (selectBtn) selectBtn.classList.add('active');
                canvas.defaultCursor = 'default';
                
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
        if (!activeObj || activeObj.type === 'activeSelection' || activeObj.isArrowLine || activeObj.isArrowText || activeObj.isArrowHead || !activeObj.isComponent) {
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

    function handleSelection() {
        let activeObj = canvas.getActiveObject();
        if (activeObj && activeObj.type === 'activeSelection') {
            const lockedObjects = activeObj.getObjects().filter(obj => obj.locked);
            if (lockedObjects.length > 0) {
                lockedObjects.forEach(obj => {
                    activeObj.removeWithUpdate(obj);
                });
                if (activeObj.getObjects().length === 0) {
                    canvas.discardActiveObject();
                    activeObj = null;
                }
                canvas.requestRenderAll();
            }
        }
        
        updateContextMenuPosition();
        updateAnchorsPosition();
    }

    canvas.on('selection:created', handleSelection);
    canvas.on('selection:updated', handleSelection);
    canvas.on('selection:cleared', () => { hideContextMenu(); Object.values(anchors).forEach(a => a.style.display = 'none'); });
    
    // Lógica para Marcos Semánticos (Arrastrar contenido interior)
    let frameChildren = [];
    let frameStartPos = { x: 0, y: 0 };

    canvas.on('mouse:down', function(e) {
        if (e.target && e.target.isFrame) {
            frameStartPos = { left: e.target.left, top: e.target.top };
            
            // Encontrar todos los objetos que están dentro de este marco
            const bound = e.target.getBoundingRect();
            frameChildren = canvas.getObjects().filter(obj => {
                if (obj === e.target || obj.isFrame) return false;
                const objBound = obj.getBoundingRect();
                // Verifica si el centro del objeto está dentro del marco
                const center = obj.getCenterPoint();
                return center.x >= bound.left && center.x <= bound.left + bound.width &&
                       center.y >= bound.top && center.y <= bound.top + bound.height;
            });
        } else {
            frameChildren = [];
        }
    });

    let snapLines = [];
    const SNAP_DISTANCE = 8;
    
    function clearSnapLines() {
        if(snapLines.length > 0) {
            snapLines.forEach(l => canvas.remove(l));
            snapLines = [];
        }
    }

    function createSnapLine(x1, y1, x2, y2) {
        const line = new fabric.Line([x1, y1, x2, y2], { stroke: '#ef4444', strokeWidth: 1, strokeDashArray: [5, 5], selectable: false, evented: false, isGuide: true, excludeFromExport: true });
        canvas.add(line);
        snapLines.push(line);
    }

    function handleSmartSnapping(activeObj) {
        clearSnapLines();
        if (activeObj.isGuide || activeObj.isArrowLine || activeObj.isArrowText) return;
        
        let objLeft = activeObj.left;
        let objTop = activeObj.top;
        let width = activeObj.width * activeObj.scaleX;
        let height = activeObj.height * activeObj.scaleY;
        let objRight = objLeft + width;
        let objBottom = objTop + height;
        let objCenterX = objLeft + width / 2;
        let objCenterY = objTop + height / 2;
        
        let snappedX = false; let snappedY = false;
        const objects = canvas.getObjects().filter(o => o !== activeObj && !o.isGuide && !o.isArrowLine && !o.isArrowText);
        
        for (let i = 0; i < objects.length; i++) {
            const t = objects[i];
            const tLeft = t.left; const tTop = t.top;
            const tWidth = t.width * t.scaleX; const tHeight = t.height * t.scaleY;
            const tRight = tLeft + tWidth; const tBottom = tTop + tHeight;
            const tCenterX = tLeft + tWidth / 2; const tCenterY = tTop + tHeight / 2;

            const cW = canvas.width * 2; const cH = canvas.height * 2;

            if (!snappedX) {
                if (Math.abs(objLeft - tLeft) < SNAP_DISTANCE) { activeObj.set({left: tLeft}); createSnapLine(tLeft, -cH, tLeft, cH); snappedX = true; }
                else if (Math.abs(objRight - tRight) < SNAP_DISTANCE) { activeObj.set({left: tRight - width}); createSnapLine(tRight, -cH, tRight, cH); snappedX = true; }
                else if (Math.abs(objCenterX - tCenterX) < SNAP_DISTANCE) { activeObj.set({left: tCenterX - width / 2}); createSnapLine(tCenterX, -cH, tCenterX, cH); snappedX = true; }
            }
            if (!snappedY) {
                if (Math.abs(objTop - tTop) < SNAP_DISTANCE) { activeObj.set({top: tTop}); createSnapLine(-cW, tTop, cW, tTop); snappedY = true; }
                else if (Math.abs(objBottom - tBottom) < SNAP_DISTANCE) { activeObj.set({top: tBottom - height}); createSnapLine(-cW, tBottom, cW, tBottom); snappedY = true; }
                else if (Math.abs(objCenterY - tCenterY) < SNAP_DISTANCE) { activeObj.set({top: tCenterY - height / 2}); createSnapLine(-cW, tCenterY, cW, tCenterY); snappedY = true; }
            }
            if (snappedX && snappedY) break;
        }
    }

    canvas.on('object:moving', function(e) {
        hideContextMenu();
        updateAnchorsPosition();
        handleSmartSnapping(e.target);
        
        // Mover los hijos si estamos moviendo un marco
        if (e.target && e.target.isFrame && frameChildren.length > 0) {
            /* 
            // Desactivado a petición del usuario para mover libremente
            const dx = e.target.left - frameStartPos.left;
            const dy = e.target.top - frameStartPos.top;
            
            frameChildren.forEach(child => {
                child.set({
                    left: child.left + dx,
                    top: child.top + dy
                });
                child.setCoords();
            });
            */
            frameStartPos = { left: e.target.left, top: e.target.top };
        }
        
        // Si movemos una línea de flecha manualmente
        if (e.target && e.target.isArrowLine) {
            e.target.set({ fromId: null, toId: null }); // Romper el anclaje si el usuario lo mueve manualmente
            const head = canvas.getObjects().find(o => o.isArrowHead && o.parentArrowId === e.target.parentArrowId);
            if (head) {
                const p = e.target.calcLinePoints();
                const m = e.target.calcTransformMatrix();
                const p1 = fabric.util.transformPoint({ x: p.x1, y: p.y1 }, m);
                const p2 = fabric.util.transformPoint({ x: p.x2, y: p.y2 }, m);
                head.set({ left: p2.x, top: p2.y });
                let angle = Math.atan2(p2.y - p1.y, p2.x - p1.x) * 180 / Math.PI;
                head.set({ angle: angle });
            }
        }
        
        // Actualizar flechas magnéticas
        ensureId(e.target);
        updateMagneticArrows(e.target);
        if (e.target.isFrame && frameChildren.length > 0) {
            frameChildren.forEach(child => {
                ensureId(child);
                updateMagneticArrows(child);
            });
        }
    });

    function syncArrowHead(target) {
        if (target && target.isArrowLine) {
            const head = canvas.getObjects().find(o => o.isArrowHead && o.parentArrowId === target.parentArrowId);
            if (head) {
                const p = target.calcLinePoints();
                const m = target.calcTransformMatrix();
                const p1 = fabric.util.transformPoint({ x: p.x1, y: p.y1 }, m);
                const p2 = fabric.util.transformPoint({ x: p.x2, y: p.y2 }, m);
                head.set({ left: p2.x, top: p2.y });
                let angle = Math.atan2(p2.y - p1.y, p2.x - p1.x) * 180 / Math.PI;
                head.set({ angle: angle });
            }
        }
    }
    
    canvas.on('mouse:up', () => {
        if (canvas.getActiveObject()) { updateContextMenuPosition(); updateAnchorsPosition(); }
    });

    // Color background
    document.querySelectorAll('.ctx-color').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const color = e.target.getAttribute('data-color');
            const activeObj = canvas.getActiveObject();
            if (activeObj && (activeObj.type === 'textbox' || activeObj.type === 'i-text')) {
                activeObj.set('backgroundColor', color === 'transparent' ? '' : color);
                canvas.requestRenderAll();
                triggerSync();
            } else if (activeObj && activeObj.type === 'rect') {
                // If it's a frame or normal rect, change fill
                activeObj.set('fill', color === 'transparent' ? 'transparent' : color);
                canvas.requestRenderAll();
                triggerSync();
            }
        });
    });

    // Align text
    document.querySelectorAll('.ctx-align').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const align = e.currentTarget.getAttribute('data-align');
            const activeObj = canvas.getActiveObject();
            if (activeObj && activeObj.type === 'textbox') {
                activeObj.set('textAlign', align);
                canvas.requestRenderAll();
                triggerSync();
            }
        });
    });

    // Font Family
    const fontSelect = document.getElementById('ctx-font');
    if (fontSelect) {
        fontSelect.addEventListener('change', (e) => {
            const activeObj = canvas.getActiveObject();
            if (activeObj && (activeObj.type === 'textbox' || activeObj.type === 'i-text')) {
                activeObj.set('fontFamily', e.target.value);
                canvas.requestRenderAll();
                triggerSync();
            }
        });
    }

    // Bring to front / Send to back
    if (ctxFront) {
        ctxFront.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                canvas.bringToFront(activeObj);
                canvas.requestRenderAll();
                triggerSync();
            }
        });
    }

    if (ctxBack) {
        ctxBack.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                canvas.sendToBack(activeObj);
                canvas.requestRenderAll();
                triggerSync();
            }
        });
    }

    // --- LÓGICA DE BLOQUEO DE OBJETOS (LOCK) ---
    if (ctxLock) {
        ctxLock.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                const isLocked = !activeObj.locked;
                
                if (activeObj.type === 'activeSelection') {
                    activeObj.forEachObject(obj => {
                        obj.set({
                            locked: true, // Si hay múltiples, forzamos bloqueo
                            lockMovementX: true,
                            lockMovementY: true,
                            lockScalingX: true,
                            lockScalingY: true,
                            lockRotation: true,
                            hasControls: false,
                            hoverCursor: 'not-allowed'
                        });
                    });
                    // Para evitar comportamientos anómalos, limpiamos selección tras el bloqueo masivo
                    canvas.discardActiveObject();
                    hideContextMenu();
                } else {
                    activeObj.set({
                        locked: isLocked,
                        lockMovementX: isLocked,
                        lockMovementY: isLocked,
                        lockScalingX: isLocked,
                        lockScalingY: isLocked,
                        lockRotation: isLocked,
                        hasControls: !isLocked, // Ocultar los cuadraditos de escalado
                        hoverCursor: isLocked ? 'not-allowed' : null
                    });
                    updateContextMenuPosition(); // Refrescar el icono
                }
                
                canvas.requestRenderAll();
                triggerSync();
            }
        });
    }

    if (ctxDelete) {
        ctxDelete.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                const activeObjects = canvas.getActiveObjects().filter(obj => !obj.locked);
                if (activeObjects.length > 0) {
                    activeObjects.forEach(obj => {
                        canvas.remove(obj);
                        if (typeof removeArrowDependencies === 'function') removeArrowDependencies(obj);
                    });
                    canvas.discardActiveObject();
                    hideContextMenu();
                    triggerSync();
                }
            }
        });
    }

    const ctxInteract = document.getElementById('ctx-interact');
    if (ctxInteract) {
        ctxInteract.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj && activeObj.isIframe) {
                const iframeEl = document.getElementById(activeObj.iframeId);
                if (iframeEl) {
                    iframeEl.style.pointerEvents = 'auto';
                    if(typeof showToast === 'function') showToast('Modo interactivo (Sal del video para moverlo)', 'ph-youtube-logo');
                    hideContextMenu();
                }
            }
        });
    }

    const ctxCopyImage = document.getElementById('ctx-copy-image');
    if (ctxCopyImage) {
        ctxCopyImage.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                try {
                    const dataUrl = activeObj.toDataURL({ format: 'png', quality: 1, multiplier: 2 });
                    fetch(dataUrl)
                        .then(res => res.blob())
                        .then(blob => navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]))
                        .then(() => {
                            if(typeof showToast === 'function') showToast('Imagen copiada al portapapeles', 'ph-check-circle');
                            hideContextMenu();
                        })
                        .catch(err => {
                            console.error("Error al copiar imagen", err);
                            if(typeof showToast === 'function') showToast('Error al copiar: No soportado por el navegador', 'ph-warning');
                        });
                } catch (e) {
                    console.error("Error de canvas contaminado (CORS)", e);
                    if(typeof showToast === 'function') showToast('Error: El elemento contiene imágenes externas (CORS)', 'ph-warning');
                }
            }
        });
    }

    const ctxLockBtns = document.querySelectorAll('#ctx-lock');
    ctxLockBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                activeObj.locked = !activeObj.locked;
                if (activeObj.locked) {
                    activeObj.lockMovementX = true;
                    activeObj.lockMovementY = true;
                    activeObj.lockRotation = true;
                    activeObj.lockScalingX = true;
                    activeObj.lockScalingY = true;
                    activeObj.hasControls = false;
                    if(typeof showToast === 'function') showToast('Objeto bloqueado', 'ph-lock-key');
                } else {
                    activeObj.lockMovementX = false;
                    activeObj.lockMovementY = false;
                    activeObj.lockRotation = false;
                    activeObj.lockScalingX = false;
                    activeObj.lockScalingY = false;
                    activeObj.hasControls = true;
                    if(typeof showToast === 'function') showToast('Objeto desbloqueado', 'ph-lock-key-open');
                }
                
                // Update icon visually without closing menu
                const ctxLockIcons = document.querySelectorAll('#ctx-lock-icon, #ctx-lock .ph-lock-key, #ctx-lock .ph-lock-key-open');
                ctxLockIcons.forEach(icon => {
                    icon.className = activeObj.locked ? 'ph ph-lock-key' : 'ph ph-lock-key-open';
                    icon.style.color = activeObj.locked ? '#ef4444' : '#64748b';
                });

                canvas.requestRenderAll();
                triggerSync();
                broadcastDelta(activeObj, 'modified');
            }
        });
    });

    // ctxGroup is already defined at the top
    if (ctxGroup) {
        ctxGroup.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                if (activeObj.type === 'activeSelection') {
                    activeObj.toGroup();
                    if(typeof showToast === 'function') showToast('Elementos agrupados', 'ph-intersect');
                } else if (activeObj.type === 'group' && !activeObj.isFrame && !activeObj.isIframe && !activeObj.isComment) {
                    activeObj.toActiveSelection();
                    if(typeof showToast === 'function') showToast('Elementos desagrupados', 'ph-squares-four');
                }
                canvas.requestRenderAll();
                triggerSync();
                hideContextMenu();
            }
        });
    }

    if (ctxDuplicate) {
        ctxDuplicate.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                activeObj.clone((cloned) => {
                    canvas.discardActiveObject();
                    cloned.set({
                        left: cloned.left + 20,
                        top: cloned.top + 20,
                        evented: true,
                    });
                    if (cloned.type === 'activeSelection') {
                        cloned.canvas = canvas;
                        cloned.forEachObject((obj) => canvas.add(obj));
                        cloned.setCoords();
                    } else {
                        canvas.add(cloned);
                    }
                    canvas.setActiveObject(cloned);
                    canvas.requestRenderAll();
                    updateContextMenuPosition();
                    triggerSync();
                }, CANVAS_EXTRA_PROPS);
            }
        });
    }

    if (ctxBringFront) {
        ctxBringFront.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                activeObj.bringToFront();
                canvas.requestRenderAll();
                updateContextMenuPosition();
                triggerSync();
            }
        });
    }

    // Controles de forma
    const ctxShapeFill = document.getElementById('ctx-shape-fill');
    const ctxShapeFillTransparent = document.getElementById('ctx-shape-fill-transparent');
    const ctxShapeStroke = document.getElementById('ctx-shape-stroke');
    const ctxShapeStrokeTransparent = document.getElementById('ctx-shape-stroke-transparent');

    if (ctxShapeFill) {
        ctxShapeFill.addEventListener('input', (e) => {
            const activeObj = canvas.getActiveObject();
            if (activeObj && activeObj.isShape) {
                activeObj.set({ fill: e.target.value });
                canvas.requestRenderAll();
                triggerSync();
            }
        });
    }

    if (ctxShapeFillTransparent) {
        ctxShapeFillTransparent.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj && activeObj.isShape) {
                activeObj.set({ fill: 'transparent' });
                if (ctxShapeFill) ctxShapeFill.value = '#ffffff';
                canvas.requestRenderAll();
                triggerSync();
            }
        });
    }

    if (ctxShapeStroke) {
        ctxShapeStroke.addEventListener('input', (e) => {
            const activeObj = canvas.getActiveObject();
            if (activeObj && activeObj.isShape) {
                activeObj.set({ stroke: e.target.value, strokeWidth: activeObj.strokeWidth || 2 });
                canvas.requestRenderAll();
                triggerSync();
            }
        });
    }

    if (ctxShapeStrokeTransparent) {
        ctxShapeStrokeTransparent.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj && activeObj.isShape) {
                activeObj.set({ stroke: 'transparent', strokeWidth: 0 });
                if (ctxShapeStroke) ctxShapeStroke.value = '#000000';
                canvas.requestRenderAll();
                triggerSync();
            }
        });
    }

    const ctxCrop = document.getElementById('ctx-crop');
    if (ctxCrop) {
        ctxCrop.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj && activeObj.type === 'image') {
                hideContextMenu();
                startCrop(activeObj);
            }
        });
    }

    // --- LÓGICA DE MENÚ CLICK DERECHO (CONTEXT MENU) ---
    const rcMenu = document.getElementById('wb-right-click-menu');
    const rcCopy = document.getElementById('rc-copy');
    const rcPaste = document.getElementById('rc-paste');
    const rcDuplicate = document.getElementById('rc-duplicate');
    const rcDelete = document.getElementById('rc-delete');
    const rcDownload = document.getElementById('rc-download');
    
    let customClipboard = null;
    let rightClickTarget = null;
    let rightClickPos = { x: 0, y: 0 };

    if (rcMenu) {
        document.addEventListener('mousedown', (e) => {
            if (!rcMenu.contains(e.target)) {
                rcMenu.style.display = 'none';
            }
        });

        canvas.wrapperEl.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            const pointer = canvas.getPointer(e);
            rightClickPos = { x: pointer.x, y: pointer.y };
            
            const target = canvas.findTarget(e, false);
            rightClickTarget = target;
            
            if (target) {
                if (!target.locked) canvas.setActiveObject(target);
                rcCopy.style.display = 'flex';
                rcDuplicate.style.display = 'flex';
                rcDownload.style.display = 'flex';
                rcDelete.style.display = 'flex';
                
                if (target.locked) {
                    rcDelete.classList.add('disabled');
                    rcDelete.style.color = '#94a3b8';
                } else {
                    rcDelete.classList.remove('disabled');
                    rcDelete.style.color = '#ef4444';
                }
            } else {
                canvas.discardActiveObject();
                rcCopy.style.display = 'none';
                rcDuplicate.style.display = 'none';
                rcDownload.style.display = 'none';
                rcDelete.style.display = 'none';
            }
            
            if (customClipboard) {
                rcPaste.classList.remove('disabled');
                rcPaste.style.opacity = '1';
            } else {
                rcPaste.classList.add('disabled');
                rcPaste.style.opacity = '0.5';
            }

            canvas.requestRenderAll();

            const rect = canvasContainer.getBoundingClientRect();
            let left = e.clientX - rect.left;
            let top = e.clientY - rect.top;
            
            rcMenu.style.display = 'flex';
            
            setTimeout(() => {
                const menuRect = rcMenu.getBoundingClientRect();
                if (left + menuRect.width > rect.width) left = rect.width - menuRect.width - 10;
                if (top + menuRect.height > rect.height) top = rect.height - menuRect.height - 10;
                rcMenu.style.left = left + 'px';
                rcMenu.style.top = top + 'px';
            }, 0);
        });

        if(rcCopy) rcCopy.addEventListener('click', () => {
            if (rightClickTarget) {
                rightClickTarget.clone((cloned) => { customClipboard = cloned; }, CANVAS_EXTRA_PROPS);
            }
            rcMenu.style.display = 'none';
        });

        if(rcPaste) rcPaste.addEventListener('click', () => {
            if (customClipboard) {
                customClipboard.clone((clonedObj) => {
                    canvas.discardActiveObject();
                    clonedObj.set({
                        left: rightClickPos.x,
                        top: rightClickPos.y,
                        evented: true,
                    });
                    if (clonedObj.type === 'activeSelection') {
                        clonedObj.canvas = canvas;
                        clonedObj.forEachObject((obj) => canvas.add(obj));
                        clonedObj.setCoords();
                    } else {
                        canvas.add(clonedObj);
                    }
                    canvas.setActiveObject(clonedObj);
                    canvas.requestRenderAll();
                    triggerSync();
                }, CANVAS_EXTRA_PROPS);
            }
            rcMenu.style.display = 'none';
        });

        if(rcDuplicate) rcDuplicate.addEventListener('click', () => {
            if (rightClickTarget) {
                rightClickTarget.clone((clonedObj) => {
                    canvas.discardActiveObject();
                    clonedObj.set({
                        left: clonedObj.left + 20,
                        top: clonedObj.top + 20,
                        evented: true,
                    });
                    if (clonedObj.type === 'activeSelection') {
                        clonedObj.canvas = canvas;
                        clonedObj.forEachObject((obj) => canvas.add(obj));
                        clonedObj.setCoords();
                    } else {
                        canvas.add(clonedObj);
                    }
                    canvas.setActiveObject(clonedObj);
                    canvas.requestRenderAll();
                    triggerSync();
                }, CANVAS_EXTRA_PROPS);
            }
            rcMenu.style.display = 'none';
        });

        if(rcDelete) rcDelete.addEventListener('click', () => {
            if (rightClickTarget && !rightClickTarget.locked) {
                canvas.remove(rightClickTarget);
                if (typeof removeArrowDependencies === 'function') removeArrowDependencies(rightClickTarget);
                canvas.discardActiveObject();
                triggerSync();
            }
            rcMenu.style.display = 'none';
        });

        if(rcDownload) rcDownload.addEventListener('click', () => {
            if (rightClickTarget) {
                let bg = '#ffffff';
                if (rightClickTarget.type === 'textbox' && rightClickTarget.backgroundColor) {
                    bg = rightClickTarget.backgroundColor;
                } else if (rightClickTarget.type === 'rect' && rightClickTarget.fill && rightClickTarget.fill !== 'transparent') {
                    bg = rightClickTarget.fill;
                }
                const dataURL = rightClickTarget.toDataURL({
                    format: 'png',
                    quality: 1,
                    multiplier: 3,
                    backgroundColor: bg
                });
                const link = document.createElement('a');
                link.download = 'elemento-exportado.png';
                link.href = dataURL;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
            rcMenu.style.display = 'none';
        });
    }

    let currentCropImage = null;
    let cropRect = null;
    let cropConfirmBtn = null;
    let cropCancelBtn = null;

    let originalCropAngle = 0;
    let originalCropOriginX = 'center';
    let originalCropOriginY = 'center';
    let originalCropLeft = 0;
    let originalCropTop = 0;

    function startCrop(imageObj) {
        if (currentCropImage) return; // Ya estamos recortando
        currentCropImage = imageObj;
        
        // Guardamos las propiedades originales
        originalCropAngle = imageObj.angle || 0;
        originalCropOriginX = imageObj.originX;
        originalCropOriginY = imageObj.originY;
        originalCropLeft = imageObj.left;
        originalCropTop = imageObj.top;
        
        // Reset a 0 grados para facilitar las matemáticas y evitar problemas de rotación
        imageObj.set({ angle: 0 });
        imageObj.setCoords();
        
        // Forzamos temporalmente a origin left/top sin mover la imagen visualmente
        const bound = imageObj.getBoundingRect();
        
        imageObj.set({
            originX: 'left',
            originY: 'top',
            left: bound.left,
            top: bound.top,
            selectable: false,
            evented: false
        });
        imageObj.setCoords();
        canvas.discardActiveObject();
        
        // Añadimos un overlay oscuro
        const overlay = new fabric.Rect({
            left: bound.left,
            top: bound.top,
            width: bound.width,
            height: bound.height,
            fill: 'rgba(0,0,0,0.5)',
            selectable: false,
            evented: false,
            originX: 'left',
            originY: 'top'
        });
        canvas.add(overlay);
        
        // Añadimos el rectangulo de recorte
        cropRect = new fabric.Rect({
            left: bound.left,
            top: bound.top,
            width: bound.width,
            height: bound.height,
            fill: 'transparent',
            stroke: '#10b981',
            strokeWidth: 2,
            strokeDashArray: [5, 5],
            transparentCorners: false,
            cornerColor: '#10b981',
            cornerSize: 12,
            originX: 'left',
            originY: 'top',
            hasRotatingPoint: false
        });
        
        canvas.add(cropRect);
        canvas.setActiveObject(cropRect);
        
        // Mostramos el botón de confirmación flotante
        cropConfirmBtn = document.createElement('button');
        cropConfirmBtn.innerHTML = '<i class="ph ph-check"></i> Aplicar Recorte';
        cropConfirmBtn.className = 'tool-btn';
        cropConfirmBtn.style.cssText = 'position:absolute; top:20px; left:50%; transform:translateX(-50%); z-index:100; background:#10b981; color:white; padding:8px 16px; border-radius:8px; display:flex; align-items:center; gap:8px; font-weight:bold; box-shadow:0 4px 12px rgba(0,0,0,0.2); border:none; cursor:pointer;';
        
        cropCancelBtn = document.createElement('button');
        cropCancelBtn.innerHTML = '<i class="ph ph-x"></i>';
        cropCancelBtn.style.cssText = 'position:absolute; top:20px; left:calc(50% + 100px); z-index:100; background:#ef4444; color:white; padding:8px; border-radius:8px; display:flex; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.2); border:none; cursor:pointer; margin-left:10px;';
        
        document.getElementById('canvas-wrapper').appendChild(cropConfirmBtn);
        document.getElementById('canvas-wrapper').appendChild(cropCancelBtn);
        
        cropConfirmBtn.onclick = () => {
            applyCrop(overlay);
        };
        
        cropCancelBtn.onclick = () => {
            canvas.remove(cropRect);
            canvas.remove(overlay);
            
            // Restauramos estado original
            imageObj.set({ 
                originX: originalCropOriginX,
                originY: originalCropOriginY,
                left: originalCropLeft,
                top: originalCropTop,
                angle: originalCropAngle,
                selectable: true, 
                evented: true 
            });
            imageObj.setCoords();
            
            currentCropImage = null;
            if (cropConfirmBtn) cropConfirmBtn.remove();
            if (cropCancelBtn) cropCancelBtn.remove();
            canvas.requestRenderAll();
        };
        
        canvas.requestRenderAll();
    }

    function applyCrop(overlay) {
        if (!currentCropImage || !cropRect) return;
        
        const img = currentCropImage;
        const rect = cropRect;
        
        // Calculamos la posición y tamaño relativos al original (ahora ambos son origin: left/top y angle: 0)
        const cropX = (rect.left - img.left) / img.scaleX;
        const cropY = (rect.top - img.top) / img.scaleY;
        const cropWidth = (rect.width * rect.scaleX) / img.scaleX;
        const cropHeight = (rect.height * rect.scaleY) / img.scaleY;
        
        // Asegurar que el width/height tengan sentido y no sean <= 0
        if(cropWidth <= 0 || cropHeight <= 0) return;
        
        // Creamos un canvas temporal para recortar la imagen real
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = cropWidth;
        tempCanvas.height = cropHeight;
        const tempCtx = tempCanvas.getContext('2d');
        
        try {
            tempCtx.drawImage(
                img.getElement(),
                cropX, cropY, cropWidth, cropHeight,
                0, 0, cropWidth, cropHeight
            );
            
            const croppedDataUrl = tempCanvas.toDataURL('image/png');
            
            fabric.Image.fromURL(croppedDataUrl, (newImg) => {
                // Para restaurar el ángulo correctamente, debemos ajustar el punto de anclaje (center)
                const centerX = rect.left + (rect.width * rect.scaleX) / 2;
                const centerY = rect.top + (rect.height * rect.scaleY) / 2;
                
                newImg.set({
                    originX: 'center',
                    originY: 'center',
                    left: centerX,
                    top: centerY,
                    scaleX: img.scaleX, 
                    scaleY: img.scaleY,
                    angle: originalCropAngle
                });
                
                canvas.remove(img);
                canvas.remove(rect);
                canvas.remove(overlay);
                
                canvas.add(newImg);
                canvas.setActiveObject(newImg);
                
                if (cropConfirmBtn) cropConfirmBtn.remove();
                if (cropCancelBtn) cropCancelBtn.remove();
                currentCropImage = null;
                
                triggerSync();
            });
        } catch (e) {
            console.error("Error cropping image: ", e);
            if (cropCancelBtn) cropCancelBtn.click();
        }
    }

    if (ctxSendBack) {
        ctxSendBack.addEventListener('click', () => {
            const activeObj = canvas.getActiveObject();
            if (activeObj) {
                activeObj.sendToBack();
                canvas.requestRenderAll();
                updateContextMenuPosition();
                handleObjectChange();
            }
        });
    }


    document.querySelectorAll('.ctx-obj-align').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const alignType = e.currentTarget.getAttribute('data-align');
            const activeObj = canvas.getActiveObject();
            if (activeObj && activeObj.type === 'activeSelection') {
                const objs = activeObj.getObjects();
                objs.forEach(obj => {
                    const objW = obj.getScaledWidth();
                    const objH = obj.getScaledHeight();
                    const groupW = activeObj.width;
                    const groupH = activeObj.height;
                    
                    const leftEdge = -groupW / 2;
                    const rightEdge = groupW / 2;
                    const topEdge = -groupH / 2;
                    const bottomEdge = groupH / 2;
                    
                    if (alignType === 'left') obj.set({ left: leftEdge + (obj.originX === 'center' ? objW / 2 : 0) });
                    if (alignType === 'center') obj.set({ left: (obj.originX === 'left' ? -objW / 2 : 0) });
                    if (alignType === 'right') obj.set({ left: rightEdge - (obj.originX === 'center' ? objW / 2 : objW) });
                    
                    if (alignType === 'top') obj.set({ top: topEdge + (obj.originY === 'center' ? objH / 2 : 0) });
                    if (alignType === 'middle') obj.set({ top: (obj.originY === 'top' ? -objH / 2 : 0) });
                    if (alignType === 'bottom') obj.set({ top: bottomEdge - (obj.originY === 'center' ? objH / 2 : objH) });
                    
                    obj.setCoords();
                });
                canvas.requestRenderAll();
                handleObjectChange();
            }
        });
    });

    // Underline
    document.getElementById('ctx-underline').addEventListener('click', () => {
        const activeObj = canvas.getActiveObject();
        if (activeObj && activeObj.isEditing) {
            activeObj.setSelectionStyles({ underline: true });
            canvas.requestRenderAll();
            triggerSync();
        } else if (activeObj) {
            activeObj.set('underline', !activeObj.underline);
            canvas.requestRenderAll();
            triggerSync();
        }
    });

    // Hyperlink
    document.getElementById('ctx-link').addEventListener('click', () => {
        const activeObj = canvas.getActiveObject();
        if (activeObj && activeObj.isEditing) {
            const url = prompt("Ingresa la URL del enlace (incluye http:// o https://):");
            if (url) {
                activeObj.setSelectionStyles({ 
                    underline: true, 
                    fill: '#2563eb', 
                    linkUrl: url 
                });
                canvas.requestRenderAll();
                triggerSync();
            }
        } else {
            Swal.fire('Atención', 'Haz doble clic en el texto y selecciona la palabra a la que quieres agregar el enlace.', 'info');
        }
    });

    // Open Hyperlink on click
    canvas.on('mouse:down', (options) => {
        if (options.target && options.target.type === 'textbox' && !options.target.isEditing) {
            try {
                const pointer = canvas.getPointer(options.e);
                const loc = options.target.get2DCursorLocation(pointer.x, pointer.y);
                const style = options.target.styles[loc.lineIndex] && options.target.styles[loc.lineIndex][loc.charIndex];
                if (style && style.linkUrl) {
                    window.open(style.linkUrl, '_blank');
                }
            } catch (err) {}
        }
    });

    // Mentions (@)
    const mentionsDropdown = document.getElementById('wb-mentions-dropdown');
    let mentionSearchMode = false;
    let mentionStartIndex = -1;
    let currentTextbox = null;

    canvas.on('text:changed', (e) => {
        const obj = e.target;
        if (!obj) return;
        
        const text = obj.text;
        const cursor = obj.selectionStart;
        
        if (text[cursor - 1] === '@') {
            mentionSearchMode = true;
            mentionStartIndex = cursor;
            currentTextbox = obj;
            showMentionsDropdown(obj, cursor, "");
        } else if (mentionSearchMode) {
            if (cursor < mentionStartIndex || text[cursor - 1] === ' ' || text[cursor - 1] === '\n') {
                mentionSearchMode = false;
                mentionsDropdown.style.display = 'none';
            } else {
                const query = text.substring(mentionStartIndex, cursor);
                showMentionsDropdown(obj, cursor, query);
            }
        }
    });
    
    function showMentionsDropdown(obj, cursor, query) {
        fetch(`ajax/ajax_whiteboard_users.php?q=${encodeURIComponent(query)}`)
            .then(r => r.json())
            .then(users => {
                if (users.length === 0) {
                    mentionsDropdown.style.display = 'none';
                    return;
                }
                mentionsDropdown.innerHTML = '';
                users.forEach(u => {
                    const div = document.createElement('div');
                    div.className = 'mention-item';
                    div.innerHTML = `<i class="ph ph-user"></i> ${u.name}`;
                    div.onclick = () => insertMention(u);
                    mentionsDropdown.appendChild(div);
                });
                
                const boundingRect = obj.getBoundingRect();
                mentionsDropdown.style.left = boundingRect.left + 'px';
                mentionsDropdown.style.top = (boundingRect.top + boundingRect.height + 5) + 'px';
                mentionsDropdown.style.display = 'block';
            })
            .catch(err => console.error(err));
    }

    function insertMention(user) {
        if (!currentTextbox) return;
        
        const text = currentTextbox.text;
        const before = text.substring(0, mentionStartIndex - 1);
        const after = text.substring(currentTextbox.selectionStart);
        const mentionText = `@${user.name} `;
        
        currentTextbox.text = before + mentionText + after;
        
        currentTextbox.selectionStart = before.length;
        currentTextbox.selectionEnd = before.length + mentionText.length - 1;
        
        currentTextbox.setSelectionStyles({
            fontWeight: 'bold',
            fill: '#0ea5e9'
        });
        
        currentTextbox.selectionStart = before.length + mentionText.length;
        currentTextbox.selectionEnd = currentTextbox.selectionStart;
        
        mentionSearchMode = false;
        mentionsDropdown.style.display = 'none';
        canvas.requestRenderAll();
        triggerSync();
    }

    // --- 7. Sidebar Drag & Drop Templates & Image Drop ---
    // Re-select templates in case they change (though they are static, just to be safe)
    const canvasWrapper = document.getElementById('canvas-wrapper');
    document.addEventListener('dragstart', (e) => {
        const card = e.target.closest('.wb-template-card');
        if (!card) return;
        
        // Retrasar el ocultamiento del sidebar ligeramente para que el navegador no cancele el arrastre
        setTimeout(() => {
            if (templatesSidebar) templatesSidebar.classList.add('closed');
            if (componentsSidebar) componentsSidebar.classList.add('closed');
        }, 50);
        
        const type = card.getAttribute('data-type');
        const color = card.getAttribute('data-color') || '';
        const title = card.getAttribute('data-title') || '';
        const id = card.getAttribute('data-id') || '';
        
        e.dataTransfer.setData('text/plain', JSON.stringify({ type, color, title, id }));
        e.dataTransfer.effectAllowed = 'copy';
    });

    if (canvasWrapper) {
        canvasWrapper.addEventListener('dragenter', (e) => {
            e.preventDefault();
        });
        canvasWrapper.addEventListener('dragover', (e) => {
            e.preventDefault(); // Necesario para permitir el drop
            e.dataTransfer.dropEffect = 'copy';
        });

        canvasWrapper.addEventListener('drop', (e) => {
            e.preventDefault();
            
            const rect = canvasWrapper.getBoundingClientRect();
            const pointerX = e.clientX - rect.left;
            const pointerY = e.clientY - rect.top;
            
            // Convertir coordenadas DOM a coordenadas del canvas (considerando zoom y pan)
            const vpt = canvas.viewportTransform;
            const canvasX = (pointerX - vpt[4]) / vpt[0];
            const canvasY = (pointerY - vpt[5]) / vpt[3];

            // 1. Verificar si se están soltando archivos locales (Imágenes)
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                const file = e.dataTransfer.files[0];
                if (file.type.startsWith('image/')) {
                    const formData = new FormData();
                    formData.append('image', file);

                    if (typeof showUploadLoader === 'function') {
                        showUploadLoader('Subiendo imagen...');
                    }

                    fetch('ajax/upload_whiteboard_image.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success && res.url) {
                            fabric.Image.fromURL(res.url, function(img) {
                                img.set({ left: canvasX, top: canvasY });
                                if (img.width > 500) img.scaleToWidth(500);
                                canvas.add(img);
                                canvas.setActiveObject(img);
                                triggerSync();
                                if (typeof setStatus === 'function') setStatus('Imagen insertada ✓', '#10b981');
                                if (typeof hideUploadLoader === 'function') hideUploadLoader();
                            }, { crossOrigin: 'anonymous' });
                        } else {
                            if (typeof setStatus === 'function') setStatus('Error subiendo imagen', '#ef4444');
                            if (typeof hideUploadLoader === 'function') hideUploadLoader();
                            alert(res.error || 'Error subiendo imagen a Google Drive');
                        }
                    })
                    .catch(err => {
                        console.error('Error subiendo imagen drop', err);
                        if (typeof setStatus === 'function') setStatus('Error subiendo imagen', '#ef4444');
                        if (typeof hideUploadLoader === 'function') hideUploadLoader();
                    });
                    return;
                } else if (file.type === 'application/pdf') {
                    if (typeof setStatus === 'function') setStatus('Procesando PDF...', '#3b82f6');
                    
                    const pdfBlobUrl = URL.createObjectURL(file);
                    
                    pdfjsLib.getDocument(pdfBlobUrl).promise.then(pdf => {
                        return pdf.getPage(1);
                    }).then(page => {
                        const scale = 1.5;
                        const viewport = page.getViewport({ scale: scale });
                        
                        const canvasPdf = document.createElement('canvas');
                        const context = canvasPdf.getContext('2d');
                        canvasPdf.height = viewport.height;
                        canvasPdf.width = viewport.width;
                        
                        page.render({ canvasContext: context, viewport: viewport }).promise.then(() => {
                            URL.revokeObjectURL(pdfBlobUrl);
                            canvasPdf.toBlob(function(blob) {
                                const formData = new FormData();
                                formData.append('image', blob, 'pdf-page.png');
                                
                                fetch('ajax/upload_whiteboard_image.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(r => r.json())
                                .then(res => {
                                    if (res.success && res.url) {
                                        fabric.Image.fromURL(res.url, function(img) {
                                            if (!img || img.width === 0 || img.height === 0) {
                                                createPdfFallbackCard(file.name, canvasX, canvasY);
                                                return;
                                            }
                                            
                                            img.set({ left: 10, top: 35, objectCaching: false });
                                            if (img.width > 600) img.scaleToWidth(600);
                                            
                                            const bg = new fabric.Rect({
                                                width: img.getScaledWidth() + 20,
                                                height: img.getScaledHeight() + 45,
                                                fill: '#ffffff', rx: 8, ry: 8,
                                                shadow: new fabric.Shadow({ color: 'rgba(0,0,0,0.08)', blur: 16, offsetX: 0, offsetY: 6 }),
                                                objectCaching: false
                                            });
                                            
                                            const title = new fabric.Textbox('📄 ' + (file.name || 'PDF Document'), {
                                                left: 10, top: 8,
                                                fontSize: 12, fontFamily: 'Inter', fontWeight: '600', fill: '#dc2626',
                                                width: img.getScaledWidth(), editable: false,
                                                objectCaching: false
                                            });
                                            
                                            const group = new fabric.Group([bg, title, img], {
                                                left: canvasX, top: canvasY,
                                                isComponent: true, objectCaching: false
                                            });
                                            
                                            canvas.add(group);
                                            canvas.setActiveObject(group);
                                            triggerSync();
                                            broadcastDelta(group, 'added');
                                            if (typeof setStatus === 'function') setStatus('PDF insertado ✓', '#10b981');
                                        }, { crossOrigin: 'anonymous' });
                                    }
                                });
                            }, 'image/png', 0.85);
                        });
                    }).catch(err => {
                        URL.revokeObjectURL(pdfBlobUrl);
                        console.warn('pdf.js no pudo parsear este PDF, creando tarjeta de referencia:', err);
                        createPdfFallbackCard(file.name, canvasX, canvasY);
                    });
                    
                    // Fallback: create a modern PDF reference card
                    function createPdfFallbackCard(fileName, x, y) {
                        const formData = new FormData();
                        formData.append('image', file, fileName);
                        
                        fetch('ajax/upload_whiteboard_image.php', {
                            method: 'POST',
                            body: formData
                        }).then(r => r.json()).then(res => {
                            const pdfUrl = (res.success && res.url) ? res.url : null;
                            const fileSizeKB = (file.size / 1024);
                            const sizeLabel = fileSizeKB > 1024 
                                ? (fileSizeKB / 1024).toFixed(1) + ' MB' 
                                : fileSizeKB.toFixed(0) + ' KB';
                            
                            const CARD_W = 320;
                            const CARD_H = 100;
                            const objects = [];
                            
                            // Card background
                            const bg = new fabric.Rect({
                                width: CARD_W, height: CARD_H,
                                fill: '#ffffff', rx: 12, ry: 12,
                                shadow: new fabric.Shadow({ color: 'rgba(15,23,42,0.08)', blur: 20, offsetX: 0, offsetY: 6 }),
                                objectCaching: false
                            });
                            objects.push(bg);
                            
                            // PDF icon background (red square)
                            const iconBg = new fabric.Rect({
                                width: 52, height: 52,
                                left: 20, top: 24,
                                fill: '#dc2626', rx: 12, ry: 12,
                                objectCaching: false
                            });
                            objects.push(iconBg);
                            
                            // PDF label inside icon
                            const iconLabel = new fabric.Textbox('PDF', {
                                left: 25, top: 38,
                                width: 42,
                                fontSize: 14, fontFamily: 'Inter',
                                fontWeight: '800', fill: '#ffffff',
                                textAlign: 'center',
                                editable: false, objectCaching: false
                            });
                            objects.push(iconLabel);
                            
                            // File name
                            const cleanName = (fileName || 'Documento PDF').replace(/\.pdf$/i, '');
                            const nameText = new fabric.Textbox(cleanName, {
                                left: 84, top: 22, width: CARD_W - 104,
                                fontSize: 14, fontFamily: 'Inter',
                                fontWeight: '700', fill: '#0f172a',
                                editable: false, splitByGrapheme: false,
                                objectCaching: false
                            });
                            objects.push(nameText);
                            
                            // File meta: size + type
                            const metaText = new fabric.Textbox(sizeLabel + '  •  Documento PDF', {
                                left: 84, top: nameText.top + nameText.height + 4,
                                width: CARD_W - 104,
                                fontSize: 11, fontFamily: 'Inter',
                                fontWeight: '400', fill: '#94a3b8',
                                editable: false, splitByGrapheme: false,
                                objectCaching: false
                            });
                            objects.push(metaText);
                            
                            // Download hint
                            if (pdfUrl) {
                                const dlText = new fabric.Textbox('⬇  Doble clic para descargar', {
                                    left: 84, top: metaText.top + metaText.height + 6,
                                    width: CARD_W - 104,
                                    fontSize: 11, fontFamily: 'Inter',
                                    fontWeight: '600', fill: '#3b82f6',
                                    editable: false, splitByGrapheme: false,
                                    objectCaching: false
                                });
                                objects.push(dlText);
                                bg.set({ height: Math.max(CARD_H, dlText.top + dlText.height + 20) });
                            }
                            
                            // Left accent bar
                            const accent = new fabric.Rect({
                                width: 4, height: bg.height - 24,
                                left: 2, top: 12,
                                fill: '#dc2626', rx: 2, ry: 2,
                                objectCaching: false
                            });
                            objects.push(accent);
                            
                            const group = new fabric.Group(objects, {
                                left: x, top: y,
                                isComponent: true, objectCaching: false,
                                hoverCursor: 'pointer',
                                linkUrl: pdfUrl || null,
                                pdfFileName: fileName || 'documento.pdf'
                            });
                            
                            canvas.add(group);
                            canvas.setActiveObject(group);
                            canvas.requestRenderAll();
                            triggerSync();
                            broadcastDelta(group, 'added');
                            if (typeof showToast === 'function') showToast('PDF adjuntado ✓', 'ph-file-pdf');
                        }).catch(() => {
                            if (typeof setStatus === 'function') setStatus('Error procesando PDF', '#ef4444');
                        });
                    }
                    
                    return;
                }
            }

            // 2. Verificar si se está soltando una plantilla del sidebar
            try {
                const dataStr = e.dataTransfer.getData('text/plain');
                if (!dataStr) return;
                const data = JSON.parse(dataStr);
                
                if (data.type === 'sticky') {
                    const noteText = data.title + '\n\nEscribe tu idea...';
                    const note = new fabric.Textbox(noteText, {
                        left: canvasX, top: canvasY, width: 150,
                        backgroundColor: data.color, fill: '#334155', fontSize: 18, fontFamily: 'Inter',
                        padding: 15, textAlign: 'center',
                        shadow: new fabric.Shadow({ color: 'rgba(0,0,0,0.15)', blur: 10, offsetX: 2, offsetY: 2 })
                    });
                    note.setSelectionStyles({ fontWeight: 'bold' }, 0, data.title.length);
                    canvas.add(note); canvas.setActiveObject(note);
                } else if (data.type === 'project_board') {
                    if (typeof setStatus === 'function') setStatus('Cargando tareas del proyecto...', '#3b82f6');
                    
                    fetch('ajax/ajax_get_project_tasks.php?project_id=' + data.id)
                        .then(r => r.json())
                        .then(res => {
                            if (!res.success) throw new Error(res.error || 'Error fetching tasks');
                            
                            const kanbanWidth = 960;
                            const colWidth = 300;
                            const colGap = 20;
                            const headerHeight = 50;
                            let maxColHeight = 150; // min height
                            
                            const objects = [];
                            
                            // Board Title
                            const boardTitle = new fabric.Textbox('Project: ' + data.title, {
                                left: canvasX, top: canvasY, width: kanbanWidth,
                                fontSize: 24, fontFamily: 'Inter', fontWeight: 'bold', fill: '#0f172a',
                                editable: false, splitByGrapheme: false
                            });
                            objects.push(boardTitle);
                            
                            const boardStartY = canvasY + boardTitle.height + 20;
                            
                            // Columns
                            const cols = ['Pendiente', 'En Progreso', 'Completado'];
                            const colColors = ['#e2e8f0', '#bae6fd', '#bbf7d0']; // Gray, Blue, Green bg
                            const headerColors = ['#94a3b8', '#38bdf8', '#4ade80']; // Header lines
                            
                            cols.forEach((colName, idx) => {
                                const colX = canvasX + (idx * (colWidth + colGap));
                                
                                // Calculate column height based on cards
                                const tasks = res.kanban[colName] || [];
                                let currentY = boardStartY + headerHeight + 15;
                                
                                const cardObjects = [];
                                
                                tasks.forEach(task => {
                                    const cardBg = new fabric.Rect({
                                        left: colX + 15, top: currentY,
                                        width: colWidth - 30, height: 60, // approximate, will adjust
                                        fill: '#ffffff', rx: 6, ry: 6,
                                        shadow: new fabric.Shadow({ color: 'rgba(0,0,0,0.05)', blur: 4, offsetX: 0, offsetY: 2 })
                                    });
                                    
                                    const cardTitle = new fabric.Textbox(task.title, {
                                        left: colX + 25, top: currentY + 12,
                                        width: colWidth - 50,
                                        fontSize: 14, fontFamily: 'Inter', fontWeight: '600', fill: '#1e293b',
                                        splitByGrapheme: false, editable: false
                                    });
                                    
                                    const cardDate = new fabric.Textbox(task.due_date || 'Sin fecha', {
                                        left: colX + 25, top: currentY + 12 + cardTitle.height + 8,
                                        width: colWidth - 50,
                                        fontSize: 11, fontFamily: 'Inter', fill: '#64748b',
                                        splitByGrapheme: false, editable: false
                                    });
                                    
                                    cardBg.set({ height: 12 + cardTitle.height + 8 + cardDate.height + 12 });
                                    currentY += cardBg.height + 12;
                                    
                                    cardObjects.push(cardBg, cardTitle, cardDate);
                                });
                                
                                const colHeight = Math.max(150, currentY - boardStartY + 15);
                                if (colHeight > maxColHeight) maxColHeight = colHeight;
                                
                                // Column Background
                                const colBg = new fabric.Rect({
                                    left: colX, top: boardStartY,
                                    width: colWidth, height: colHeight,
                                    fill: colColors[idx], rx: 8, ry: 8
                                });
                                
                                // Column Header Line
                                const colHeaderLine = new fabric.Rect({
                                    left: colX, top: boardStartY,
                                    width: colWidth, height: 6,
                                    fill: headerColors[idx], rx: 8, ry: 8
                                });
                                
                                // Column Title
                                const colTitleText = new fabric.Textbox(colName.toUpperCase() + ' (' + tasks.length + ')', {
                                    left: colX + 15, top: boardStartY + 20,
                                    width: colWidth - 30,
                                    fontSize: 13, fontFamily: 'Inter', fontWeight: 'bold', fill: '#475569',
                                    editable: false, splitByGrapheme: false
                                });
                                
                                objects.push(colBg, colHeaderLine, colTitleText, ...cardObjects);
                            });
                            
                            // Adjust all column backgrounds to maxColHeight for uniform look
                            objects.forEach(obj => {
                                if (obj.type === 'rect' && obj.width === colWidth && obj.height !== 6 && obj.height >= 150) {
                                    obj.set({ height: maxColHeight });
                                }
                            });
                            
                            const group = new fabric.Group(objects, {
                                left: canvasX, top: canvasY,
                                isComponent: true
                            });
                            ensureId(group);
                            canvas.add(group); canvas.setActiveObject(group);
                            canvas.requestRenderAll();
                            triggerSync();
                            broadcastDelta(group, 'added');
                            if (typeof setStatus === 'function') setStatus('Tablero de proyecto insertado ✓', '#10b981');
                        })
                        .catch(err => {
                            console.error('Error fetching project tasks', err);
                            if (typeof setStatus === 'function') setStatus('Error al cargar proyecto', '#ef4444');
                        });
                        
                } else if (data.type === 'month_board') {
                    insertMonthBoardOnCanvas(data.id, data.title || 'Month Board', canvasX, canvasY);
                        
                } else if (data.type === 'wireframe') {
                    const browserBar = new fabric.Rect({ width: 500, height: 40, fill: '#cbd5e1', top: 0, left: 0, rx: 8, ry: 8 });
                    const browserBody = new fabric.Rect({ width: 500, height: 350, fill: '#f8fafc', top: 20, left: 0, stroke: '#cbd5e1', strokeWidth: 2, rx: 8, ry: 8 });
                    const headerTxt = new fabric.Text('Wireframe Window', { left: 20, top: 10, fontSize: 16, fontFamily: 'Inter', fill: '#475569' });
                    const group = new fabric.Group([browserBody, browserBar, headerTxt], { left: canvasX, top: canvasY });
                    ensureId(group);
                    group.isComponent = true;
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'funnel') {
                    const p1 = new fabric.Rect({ width: 300, height: 60, fill: '#60a5fa', top: 0, left: -150, rx: 4, ry: 4 });
                    const p2 = new fabric.Rect({ width: 220, height: 60, fill: '#3b82f6', top: 70, left: -110, rx: 4, ry: 4 });
                    const p3 = new fabric.Rect({ width: 140, height: 60, fill: '#2563eb', top: 140, left: -70, rx: 4, ry: 4 });
                    const p4 = new fabric.Rect({ width: 80, height: 60, fill: '#1d4ed8', top: 210, left: -40, rx: 4, ry: 4 });
                    const group = new fabric.Group([p1, p2, p3, p4], { left: canvasX, top: canvasY });
                    ensureId(group);
                    group.isComponent = true;
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'journey') {
                    const bg = new fabric.Rect({ width: 600, height: 400, fill: '#ffffff', stroke: '#cbd5e1', strokeWidth: 2, rx: 8, ry: 8 });
                    const title = new fabric.Text('User Journey Map', { left: 20, top: 20, fontSize: 24, fontFamily: 'Inter', fontWeight: 'bold' });
                    const group = new fabric.Group([bg, title], { left: canvasX, top: canvasY });
                    ensureId(group);
                    group.isComponent = true;
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'decision') {
                    const poly = new fabric.Polygon([
                        {x: 50, y: 0}, {x: 100, y: 50}, {x: 50, y: 100}, {x: 0, y: 50}
                    ], {
                        fill: '#fef08a', stroke: '#eab308', strokeWidth: 2,
                        left: canvasX, top: canvasY, originX: 'center', originY: 'center'
                    });
                    const text = new fabric.IText('¿Condición?', {
                        left: canvasX, top: canvasY, originX: 'center', originY: 'center',
                        fontSize: 14, fontFamily: 'Inter', fontWeight: 'bold'
                    });
                    const group = new fabric.Group([poly, text], { left: canvasX, top: canvasY, originX: 'center', originY: 'center' });
                    ensureId(group);
                    group.isComponent = true;
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'database') {
                    const body = new fabric.Rect({ width: 80, height: 80, fill: '#f1f5f9', stroke: '#94a3b8', strokeWidth: 2, top: 15, left: 0 });
                    const topEllipse = new fabric.Ellipse({ rx: 40, ry: 15, fill: '#f1f5f9', stroke: '#94a3b8', strokeWidth: 2, top: 0, left: 0 });
                    const midLine = new fabric.Ellipse({ rx: 40, ry: 15, fill: 'transparent', stroke: '#94a3b8', strokeWidth: 2, top: 25, left: 0 });
                    const bottomEllipse = new fabric.Ellipse({ rx: 40, ry: 15, fill: '#f1f5f9', stroke: '#94a3b8', strokeWidth: 2, top: 80, left: 0 });
                    const group = new fabric.Group([bottomEllipse, body, topEllipse, midLine], { left: canvasX, top: canvasY, originX: 'center', originY: 'center' });
                    ensureId(group);
                    group.isComponent = true;
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'kanban') {
                    const bg = new fabric.Rect({ width: 700, height: 400, fill: '#f8fafc', stroke: '#cbd5e1', strokeWidth: 2, rx: 8, ry: 8, left: -350, top: -200 });
                    const col1 = new fabric.Rect({ width: 200, height: 360, fill: '#e2e8f0', rx: 4, ry: 4, left: -330, top: -180 });
                    const col2 = new fabric.Rect({ width: 200, height: 360, fill: '#e2e8f0', rx: 4, ry: 4, left: -100, top: -180 });
                    const col3 = new fabric.Rect({ width: 200, height: 360, fill: '#e2e8f0', rx: 4, ry: 4, left: 130, top: -180 });
                    
                    const t1 = new fabric.Text('POR HACER', { left: -275, top: -165, fontSize: 16, fontFamily: 'Inter', fontWeight: 'bold', fill: '#475569' });
                    const t2 = new fabric.Text('HACIENDO', { left: -40, top: -165, fontSize: 16, fontFamily: 'Inter', fontWeight: 'bold', fill: '#475569' });
                    const t3 = new fabric.Text('HECHO', { left: 200, top: -165, fontSize: 16, fontFamily: 'Inter', fontWeight: 'bold', fill: '#475569' });
                    
                    const group = new fabric.Group([bg, col1, col2, col3, t1, t2, t3], { left: canvasX, top: canvasY });
                    ensureId(group);
                    group.isComponent = true;
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'whatsapp') {
                    const bubble = new fabric.Rect({ width: 250, height: 80, fill: '#dcf8c6', rx: 8, ry: 8, left: -125, top: -40, stroke: '#b2d89b', strokeWidth: 1 });
                    const text = new fabric.Text('Mensaje de WhatsApp', { left: -110, top: -25, fontSize: 16, fontFamily: 'Inter', fill: '#333' });
                    const time = new fabric.Text('12:00 PM', { left: 55, top: 15, fontSize: 12, fontFamily: 'Inter', fill: '#888' });
                    const group = new fabric.Group([bubble, text, time], { left: canvasX, top: canvasY });
                    ensureId(group);
                    group.isComponent = true;
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'ecommerce') {
                    const card = new fabric.Rect({ width: 200, height: 300, fill: '#ffffff', rx: 8, ry: 8, left: -100, top: -150, stroke: '#cbd5e1', strokeWidth: 1 });
                    const img = new fabric.Rect({ width: 200, height: 150, fill: '#e2e8f0', left: -100, top: -150, rx: 8, ry: 8 });
                    const title = new fabric.Text('Producto', { left: -85, top: 15, fontSize: 18, fontFamily: 'Inter', fontWeight: 'bold', fill: '#1e293b' });
                    const price = new fabric.Text('$99.99', { left: -85, top: 45, fontSize: 16, fontFamily: 'Inter', fill: '#22c55e', fontWeight: 'bold' });
                    const btn = new fabric.Rect({ width: 170, height: 40, fill: '#3b82f6', rx: 4, ry: 4, left: -85, top: 90 });
                    const btnText = new fabric.Text('Añadir al carrito', { left: -65, top: 100, fontSize: 14, fontFamily: 'Inter', fill: '#ffffff', fontWeight: 'bold' });
                    const group = new fabric.Group([card, img, title, price, btn, btnText], { left: canvasX, top: canvasY });
                    ensureId(group);
                    group.isComponent = true;
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'ad-image') {
                    const card = new fabric.Rect({ width: 300, height: 250, fill: '#ffffff', rx: 8, ry: 8, left: -150, top: -125, stroke: '#cbd5e1', strokeWidth: 1 });
                    const img = new fabric.Rect({ width: 300, height: 150, fill: '#e2e8f0', left: -150, top: -125, rx: 8, ry: 8 });
                    const title = new fabric.Text('Título del Anuncio', { left: -135, top: 40, fontSize: 18, fontFamily: 'Inter', fontWeight: 'bold', fill: '#1e293b' });
                    const desc = new fabric.Text('Descripción corta de la oferta', { left: -135, top: 65, fontSize: 14, fontFamily: 'Inter', fill: '#64748b' });
                    const btn = new fabric.Rect({ width: 100, height: 30, fill: '#2563eb', rx: 4, ry: 4, left: 35, top: 80 });
                    const btnText = new fabric.Text('Comprar', { left: 55, top: 87, fontSize: 12, fontFamily: 'Inter', fill: '#ffffff', fontWeight: 'bold' });
                    const group = new fabric.Group([card, img, title, desc, btn, btnText], { left: canvasX, top: canvasY });
                    ensureId(group);
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'ad-video') {
                    const card = new fabric.Rect({ width: 320, height: 280, fill: '#ffffff', rx: 8, ry: 8, left: -160, top: -140, stroke: '#cbd5e1', strokeWidth: 1 });
                    const vid = new fabric.Rect({ width: 320, height: 180, fill: '#1e293b', left: -160, top: -140, rx: 8, ry: 8 });
                    const playBtn = new fabric.Circle({ radius: 30, fill: 'rgba(255,255,255,0.8)', left: -30, top: -80 });
                    const playTriangle = new fabric.Polygon([{x: 0, y: -10}, {x: 15, y: 0}, {x: 0, y: 10}], { fill: '#1e293b', left: -5, top: -50 });
                    const title = new fabric.Text('Video Promocional', { left: -145, top: 55, fontSize: 18, fontFamily: 'Inter', fontWeight: 'bold', fill: '#1e293b' });
                    const btn = new fabric.Rect({ width: 120, height: 35, fill: '#22c55e', rx: 4, ry: 4, left: 25, top: 90 });
                    const btnText = new fabric.Text('Ver más', { left: 55, top: 99, fontSize: 14, fontFamily: 'Inter', fill: '#ffffff', fontWeight: 'bold' });
                    const group = new fabric.Group([card, vid, playBtn, playTriangle, title, btn, btnText], { left: canvasX, top: canvasY });
                    ensureId(group);
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'landing-page') {
                    const bg = new fabric.Rect({ width: 400, height: 600, fill: '#ffffff', rx: 8, ry: 8, left: -200, top: -300, stroke: '#cbd5e1', strokeWidth: 1 });
                    const header = new fabric.Rect({ width: 400, height: 50, fill: '#f8fafc', left: -200, top: -300, rx: 8, ry: 8 });
                    const logo = new fabric.Rect({ width: 80, height: 20, fill: '#cbd5e1', left: -180, top: -285, rx: 4, ry: 4 });
                    const nav = new fabric.Rect({ width: 120, height: 15, fill: '#e2e8f0', left: 60, top: -282, rx: 4, ry: 4 });
                    const hero = new fabric.Rect({ width: 400, height: 250, fill: '#e2e8f0', left: -200, top: -250 });
                    const heroTitle = new fabric.Text('Gran Titular Aquí', { left: -90, top: -160, fontSize: 24, fontFamily: 'Inter', fontWeight: 'bold', fill: '#1e293b' });
                    const heroBtn = new fabric.Rect({ width: 140, height: 45, fill: '#3b82f6', left: -70, top: -100, rx: 6, ry: 6 });
                    const f1 = new fabric.Rect({ width: 110, height: 120, fill: '#f1f5f9', left: -180, top: 30, rx: 8, ry: 8 });
                    const f2 = new fabric.Rect({ width: 110, height: 120, fill: '#f1f5f9', left: -55, top: 30, rx: 8, ry: 8 });
                    const f3 = new fabric.Rect({ width: 110, height: 120, fill: '#f1f5f9', left: 70, top: 30, rx: 8, ry: 8 });
                    const group = new fabric.Group([bg, header, logo, nav, hero, heroTitle, heroBtn, f1, f2, f3], { left: canvasX, top: canvasY });
                    ensureId(group);
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'social-facebook') {
                    const card = new fabric.Rect({ width: 350, height: 350, fill: '#ffffff', rx: 8, ry: 8, left: -175, top: -175, stroke: '#e2e8f0', strokeWidth: 1 });
                    const avatar = new fabric.Circle({ radius: 20, fill: '#1877F2', left: -160, top: -160 });
                    const name = new fabric.Text('Nombre de la Página', { left: -110, top: -155, fontSize: 16, fontFamily: 'Inter', fontWeight: 'bold', fill: '#1c1e21' });
                    const time = new fabric.Text('2 h • Público', { left: -110, top: -135, fontSize: 12, fontFamily: 'Inter', fill: '#65676b' });
                    const text = new fabric.Text('Este es un post de ejemplo en Facebook.', { left: -160, top: -100, fontSize: 14, fontFamily: 'Inter', fill: '#1c1e21' });
                    const img = new fabric.Rect({ width: 350, height: 200, fill: '#e2e8f0', left: -175, top: -60 });
                    const likeBar = new fabric.Rect({ width: 350, height: 40, fill: '#f8fafc', left: -175, top: 135, borderBottomLeftRadius: 8, borderBottomRightRadius: 8 });
                    const group = new fabric.Group([card, avatar, name, time, text, img, likeBar], { left: canvasX, top: canvasY });
                    ensureId(group);
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'social-instagram') {
                    const card = new fabric.Rect({ width: 350, height: 450, fill: '#ffffff', rx: 8, ry: 8, left: -175, top: -225, stroke: '#e2e8f0', strokeWidth: 1 });
                    const avatar = new fabric.Circle({ radius: 18, fill: '#cbd5e1', left: -160, top: -210 });
                    const name = new fabric.Text('usuario_instagram', { left: -115, top: -205, fontSize: 14, fontFamily: 'Inter', fontWeight: 'bold', fill: '#262626' });
                    const img = new fabric.Rect({ width: 350, height: 350, fill: '#f1f5f9', left: -175, top: -165 });
                    const actions = new fabric.Text('♡  💬  ➦', { left: -160, top: 195, fontSize: 20, fontFamily: 'Inter', fill: '#262626' });
                    const group = new fabric.Group([card, avatar, name, img, actions], { left: canvasX, top: canvasY });
                    ensureId(group);
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'social-tiktok') {
                    const card = new fabric.Rect({ width: 280, height: 500, fill: '#111111', rx: 16, ry: 16, left: -140, top: -250 });
                    const avatar = new fabric.Circle({ radius: 20, fill: '#ffffff', left: 90, top: 0 });
                    const like = new fabric.Circle({ radius: 15, fill: '#333333', left: 95, top: 60 });
                    const comment = new fabric.Circle({ radius: 15, fill: '#333333', left: 95, top: 110 });
                    const share = new fabric.Circle({ radius: 15, fill: '#333333', left: 95, top: 160 });
                    const name = new fabric.Text('@usuario', { left: -120, top: 180, fontSize: 16, fontFamily: 'Inter', fontWeight: 'bold', fill: '#ffffff' });
                    const text = new fabric.Text('Descripción del video #tiktok', { left: -120, top: 205, fontSize: 14, fontFamily: 'Inter', fill: '#ffffff' });
                    const group = new fabric.Group([card, avatar, like, comment, share, name, text], { left: canvasX, top: canvasY });
                    ensureId(group);
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'social-youtube') {
                    const card = new fabric.Rect({ width: 450, height: 320, fill: '#ffffff', rx: 8, ry: 8, left: -225, top: -160, stroke: '#e2e8f0', strokeWidth: 1 });
                    const vid = new fabric.Rect({ width: 450, height: 250, fill: '#000000', left: -225, top: -160, rx: 8, ry: 8 });
                    const playBar = new fabric.Rect({ width: 450, height: 4, fill: '#FF0000', left: -225, top: 86 });
                    const avatar = new fabric.Circle({ radius: 20, fill: '#cbd5e1', left: -210, top: 105 });
                    const title = new fabric.Text('Título del Video de YouTube', { left: -160, top: 105, fontSize: 18, fontFamily: 'Inter', fontWeight: 'bold', fill: '#0f0f0f' });
                    const views = new fabric.Text('1.2 M vistas • hace 2 días', { left: -160, top: 130, fontSize: 14, fontFamily: 'Inter', fill: '#606060' });
                    const group = new fabric.Group([card, vid, playBar, avatar, title, views], { left: canvasX, top: canvasY });
                    ensureId(group);
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'social-google') {
                    const card = new fabric.Rect({ width: 400, height: 120, fill: '#ffffff', left: -200, top: -60, stroke: '#e2e8f0', strokeWidth: 1, rx: 4, ry: 4 });
                    const url = new fabric.Text('https://www.ejemplo.com › ...', { left: -180, top: -45, fontSize: 14, fontFamily: 'Inter', fill: '#4d5156' });
                    const title = new fabric.Text('Título del Resultado de Búsqueda', { left: -180, top: -20, fontSize: 20, fontFamily: 'Inter', fill: '#1a0dab' });
                    const desc = new fabric.Text('Descripción relevante que aparece en los resultados...', { left: -180, top: 10, fontSize: 14, fontFamily: 'Inter', fill: '#4d5156' });
                    const group = new fabric.Group([card, url, title, desc], { left: canvasX, top: canvasY });
                    ensureId(group);
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'social-linkedin') {
                    const card = new fabric.Rect({ width: 350, height: 250, fill: '#ffffff', rx: 8, ry: 8, left: -175, top: -125, stroke: '#e2e8f0', strokeWidth: 1 });
                    const avatar = new fabric.Circle({ radius: 24, fill: '#cbd5e1', left: -160, top: -110 });
                    const name = new fabric.Text('Profesional LinkedIn', { left: -100, top: -110, fontSize: 16, fontFamily: 'Inter', fontWeight: 'bold', fill: '#000000' });
                    const job = new fabric.Text('Cargo actual en Empresa', { left: -100, top: -90, fontSize: 12, fontFamily: 'Inter', fill: '#666666' });
                    const connectBtn = new fabric.Rect({ width: 90, height: 30, fill: '#ffffff', stroke: '#0a66c2', strokeWidth: 1, rx: 15, ry: 15, left: 70, top: -105 });
                    const connectTxt = new fabric.Text('+ Conectar', { left: 82, top: -98, fontSize: 12, fontFamily: 'Inter', fontWeight: 'bold', fill: '#0a66c2' });
                    const text = new fabric.Text('Compartiendo una actualización...', { left: -160, top: -40, fontSize: 14, fontFamily: 'Inter', fill: '#000000' });
                    const img = new fabric.Rect({ width: 350, height: 120, fill: '#f3f2ef', left: -175, top: 5 });
                    const group = new fabric.Group([card, avatar, name, job, connectBtn, connectTxt, text, img], { left: canvasX, top: canvasY });
                    ensureId(group);
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'whatsapp-group') {
                    const card = new fabric.Rect({ width: 300, height: 400, fill: '#e5ddd5', rx: 12, ry: 12, left: -150, top: -200, stroke: '#cbd5e1', strokeWidth: 1 });
                    const header = new fabric.Rect({ width: 300, height: 60, fill: '#075E54', left: -150, top: -200, rx: 12, ry: 12 });
                    const title = new fabric.Text('Grupo de Trabajo', { left: -90, top: -180, fontSize: 16, fontFamily: 'Inter', fontWeight: 'bold', fill: '#ffffff' });
                    const b1 = new fabric.Rect({ width: 200, height: 70, fill: '#ffffff', rx: 8, ry: 8, left: -130, top: -120 });
                    const n1 = new fabric.Text('Juan', { left: -120, top: -115, fontSize: 12, fontFamily: 'Inter', fontWeight: 'bold', fill: '#34B7F1' });
                    const t1 = new fabric.Text('Hola a todos!', { left: -120, top: -95, fontSize: 14, fontFamily: 'Inter', fill: '#000000' });
                    const b2 = new fabric.Rect({ width: 180, height: 50, fill: '#dcf8c6', rx: 8, ry: 8, left: -40, top: -30 });
                    const t2 = new fabric.Text('Hola Juan, qué tal?', { left: -30, top: -15, fontSize: 14, fontFamily: 'Inter', fill: '#000000' });
                    const b3 = new fabric.Rect({ width: 160, height: 70, fill: '#ffffff', rx: 8, ry: 8, left: -130, top: 40 });
                    const n3 = new fabric.Text('María', { left: -120, top: 45, fontSize: 12, fontFamily: 'Inter', fontWeight: 'bold', fill: '#ff7a59' });
                    const group = new fabric.Group([card, header, title, b1, n1, t1, b2, t2, b3, n3], { left: canvasX, top: canvasY });
                    ensureId(group);
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'ai') {
                    const card = new fabric.Rect({ width: 320, height: 400, fill: '#ffffff', rx: 12, ry: 12, left: -160, top: -200, stroke: '#c4b5fd', strokeWidth: 1 });
                    const header = new fabric.Rect({ width: 320, height: 60, fill: '#f3f0ff', left: -160, top: -200, rx: 12, ry: 12 });
                    const title = new fabric.Text('✨ Asistente IA', { left: -140, top: -180, fontSize: 18, fontFamily: 'Inter', fontWeight: 'bold', fill: '#8b5cf6' });
                    const b1 = new fabric.Rect({ width: 220, height: 60, fill: '#f1f5f9', rx: 12, ry: 12, left: -140, top: -120 });
                    const t1 = new fabric.Text('Hola, ¿en qué te ayudo?', { left: -125, top: -100, fontSize: 14, fontFamily: 'Inter', fill: '#334155' });
                    const b2 = new fabric.Rect({ width: 180, height: 60, fill: '#8b5cf6', rx: 12, ry: 12, left: -40, top: -40 });
                    const t2 = new fabric.Text('Genera una idea...', { left: -25, top: -20, fontSize: 14, fontFamily: 'Inter', fill: '#ffffff' });
                    const input = new fabric.Rect({ width: 280, height: 40, fill: '#ffffff', rx: 20, ry: 20, left: -140, top: 140, stroke: '#cbd5e1', strokeWidth: 1 });
                    const inputText = new fabric.Text('Escribe un prompt...', { left: -120, top: 152, fontSize: 14, fontFamily: 'Inter', fill: '#94a3b8' });
                    const group = new fabric.Group([card, header, title, b1, t1, b2, t2, input, inputText], { left: canvasX, top: canvasY });
                    ensureId(group);
                    canvas.add(group); canvas.setActiveObject(group);
                } else if (data.type === 'rotulo') {
                    const card = new fabric.Rect({ width: 400, height: 260, fill: '#ffffff', rx: 16, ry: 16, left: -200, top: -130, shadow: new fabric.Shadow({ color: 'rgba(0,0,0,0.1)', blur: 15, offsetX: 0, offsetY: 8 }), rotuloField: 'card' });
                    const header = new fabric.Rect({ width: 400, height: 60, fill: '#0f172a', left: -200, top: -130, rx: 16, ry: 16, rotuloField: 'header' });
                    const headerSquareBottom = new fabric.Rect({ width: 400, height: 20, fill: '#0f172a', left: -200, top: -90 });
                    
                    const headerTxt = new fabric.Text('RÓTULO DE PROYECTO', { left: -130, top: -108, fontSize: 14, fontFamily: 'Inter', fontWeight: '800', fill: '#cbd5e1' });
                    
                    const projTitle = new fabric.Textbox('Nombre del Proyecto', { left: -180, top: -50, width: 360, fontSize: 26, fontFamily: 'Inter', fontWeight: 'bold', fill: '#0f172a', rotuloField: 'title', editable: false, splitByGrapheme: true });
                    
                    const sizeLabel = new fabric.Textbox('Tamaño: 1080 x 1080 px', { left: -180, top: -10, width: 360, fontSize: 14, fontFamily: 'Inter', fill: '#475569', fontWeight: '500', rotuloField: 'size', editable: false, splitByGrapheme: true });
                    
                    const switcherBg = new fabric.Rect({ width: 360, height: 32, fill: '#f1f5f9', rx: 8, ry: 8, left: -180, top: 15, stroke: '#e2e8f0', strokeWidth: 1, rotuloField: 'formatBg' });
                    const switcherTxt = new fabric.Textbox('Formato: Digital', { left: -170, top: 22, width: 340, fontSize: 13, fontFamily: 'Inter', fontWeight: 'bold', fill: '#0f172a', textAlign: 'left', rotuloField: 'format', editable: false, splitByGrapheme: true });
                    
                    const sep = new fabric.Rect({ width: 360, height: 1, fill: '#e2e8f0', left: -180, top: 60, rotuloField: 'sep' });
                    
                    const descTxt = new fabric.Textbox('Descripción u observaciones del proyecto. \n(Doble clic para editar este Rótulo)', { left: -180, top: 75, width: 360, fontSize: 14, fontFamily: 'Inter', fill: '#64748b', lineHeight: 1.4, rotuloField: 'desc', editable: false, splitByGrapheme: true });
                    
                    let systemFaviconUrl = 'assets/img/default-logo.png';
                    let faviconEl = document.querySelector('link[rel="icon"], link[rel="shortcut icon"]');
                    if (faviconEl && faviconEl.href) systemFaviconUrl = faviconEl.href;
                    
                    const createGroup = (logoObj) => {
                        const group = new fabric.Group([card, header, headerSquareBottom, logoObj, headerTxt, projTitle, sizeLabel, switcherBg, switcherTxt, sep, descTxt], { left: canvasX, top: canvasY, isRotulo: true, lockScalingX: true, lockScalingY: true });
                        ensureId(group);
                        group.isComponent = true;
                        canvas.add(group); canvas.setActiveObject(group);
                        triggerSync();
                    };
                    
                    fabric.Image.fromURL(systemFaviconUrl, function(img) {
                        if(img && !img.isError) {
                            img.scaleToWidth(36);
                            img.set({ 
                                left: -180, 
                                top: -118, 
                                rotuloField: 'logoImg'
                            });
                            createGroup(img);
                        } else {
                            const fallbackLogo = new fabric.Circle({ radius: 18, fill: '#3b82f6', left: -180, top: -118, rotuloField: 'logoImg' });
                            createGroup(fallbackLogo);
                        }
                    }, { crossOrigin: 'anonymous' });
                    
                    return; // Prevent synchronous triggerSync() since image loading is async
                }
                triggerSync();
            } catch (err) {
                // ignorar si el payload no es json
            }
        });
    }

    // --- Formas Mágicas ---
    canvas.on('path:created', function(e) {
        if (currentTool !== 'draw') return;
        // ... (resto del código igual) ...
        const path = e.path;
        const pathData = path.path;
        if (!pathData || pathData.length < 5) return;
        
        // Obtener el punto de inicio y el punto final del trazo
        const startPoint = { x: pathData[0][1], y: pathData[0][2] };
        const lastCmd = pathData[pathData.length - 1];
        let endPoint = { x: startPoint.x, y: startPoint.y };
        
        if (lastCmd[0] === 'Q' || lastCmd[0] === 'L') {
            endPoint = { x: lastCmd[lastCmd.length - 2], y: lastCmd[lastCmd.length - 1] };
        }
        
        const dist = Math.sqrt(Math.pow(endPoint.x - startPoint.x, 2) + Math.pow(endPoint.y - startPoint.y, 2));
        const boundingRect = path.getBoundingRect();
        
        if (dist < 50 && boundingRect.width > 20 && boundingRect.height > 20) {
            let newObj = null;
            const aspectRatio = boundingRect.width / boundingRect.height;
            
            if (aspectRatio > 0.8 && aspectRatio < 1.25) {
                const radius = Math.max(boundingRect.width, boundingRect.height) / 2;
                newObj = new fabric.Circle({
                    radius: radius, left: boundingRect.left + boundingRect.width / 2, top: boundingRect.top + boundingRect.height / 2,
                    originX: 'center', originY: 'center', fill: 'transparent', stroke: path.stroke, strokeWidth: path.strokeWidth
                });
            } else {
                newObj = new fabric.Rect({
                    width: boundingRect.width, height: boundingRect.height,
                    left: boundingRect.left + boundingRect.width / 2, top: boundingRect.top + boundingRect.height / 2,
                    originX: 'center', originY: 'center', fill: 'transparent', stroke: path.stroke, strokeWidth: path.strokeWidth, rx: 8, ry: 8
                });
            }
            if (newObj) {
                setTimeout(() => {
                    canvas.remove(path); canvas.add(newObj); canvas.setActiveObject(newObj); canvas.requestRenderAll(); triggerSync();
                }, 200);
            }
        }
    });

    // --- Comentarios (Popover) ---
    const commentPopover = document.getElementById('wb-comment-popover');
    const commentThreadView = document.getElementById('comment-thread-view');
    const commentMessagesList = document.getElementById('comment-messages-list');
    const commentInput = document.getElementById('comment-popover-input');
    const commentSendBtn = document.getElementById('comment-popover-send');
    const commentLine = document.getElementById('wb-comment-line');
    let activeCommentPin = null;

    function closePopover() {
        if (!commentPopover) return;
        commentPopover.style.transform = 'scale(0.95)';
        commentPopover.style.opacity = '0';
        setTimeout(() => commentPopover.style.display = 'none', 200);
        activeCommentPin = null;
    }
    
    // Cerrar si se da click en otro lado (si no es el popover ni un pin)
    canvas.on('mouse:down', function(o) {
        if (currentTool !== 'comment' && (!o.target || !o.target.isComment)) {
            closePopover();
        }
    });

    function positionPopover(pin) {
        if (!commentPopover || !pin) return;
        
        // Obtener posición absoluta del pin en la pantalla
        const canvasWrapper = document.getElementById('canvas-wrapper');
        const vpt = canvas.viewportTransform;
        
        // El centro del pin
        const point = fabric.util.transformPoint({ x: pin.left, y: pin.top }, vpt);
        
        // Posicionar el popover centrado debajo del pin
        const popoverLeft = point.x - 160; // 320px / 2 = 160px para centrar
        const popoverTop = point.y + 20; // Separación inferior
        
        commentPopover.style.left = popoverLeft + 'px';
        commentPopover.style.top = popoverTop + 'px';
        
        // Mostrar línea conectora (Oculta si no se desea línea en este diseño)
        commentLine.style.display = 'none';
    }

    // Actualizar posición del popover e Iframes al hacer pan, zoom o render
    canvas.on('after:render', function() {
        // Popover de comentarios
        if (activeCommentPin && commentPopover.style.opacity === '1') {
            positionPopover(activeCommentPin);
        }
        
        // Sincronizar Iframes
        syncIframes();
    });

    // --- Lógica de Iframes Embeds (Tarea 8) ---
    const iframeLayer = document.getElementById('iframe-layer');
    
    function parseEmbedUrl(url) {
        let embedUrl = url;
        try {
            if (url.includes('youtube.com/watch')) {
                const videoId = new URL(url).searchParams.get('v');
                embedUrl = `https://www.youtube.com/embed/${videoId}`;
            } else if (url.includes('youtu.be/')) {
                const videoId = url.split('youtu.be/')[1].split('?')[0];
                embedUrl = `https://www.youtube.com/embed/${videoId}`;
            } else if (url.includes('youtube.com/shorts/')) {
                const videoId = url.split('youtube.com/shorts/')[1].split('?')[0];
                embedUrl = `https://www.youtube.com/embed/${videoId}`;
            } else if (url.includes('tiktok.com')) {
                const match = url.match(/video\/(\d+)/);
                if (match && match[1]) {
                    embedUrl = `https://www.tiktok.com/embed/v2/${match[1]}`;
                }
            } else if (url.includes('instagram.com/reel/') || url.includes('instagram.com/p/')) {
                let cleanUrl = url.split('?')[0];
                if (!cleanUrl.endsWith('/')) cleanUrl += '/';
                embedUrl = cleanUrl + 'embed';
            } else if (url.includes('facebook.com/reel/') || url.includes('fb.watch/')) {
                embedUrl = `https://www.facebook.com/plugins/video.php?href=${encodeURIComponent(url)}&show_text=false`;
            } else if (url.includes('spotify.com/track')) {
                embedUrl = url.replace('spotify.com/track', 'open.spotify.com/embed/track');
            } else if (url.includes('spotify.com/playlist')) {
                embedUrl = url.replace('spotify.com/playlist', 'open.spotify.com/embed/playlist');
            }
        } catch (e) {
            console.error("Error parseando URL de embed:", e);
        }
        return embedUrl;
    }

    function syncIframes() {
        if (!iframeLayer) return;
        
        const canvasObjects = canvas.getObjects();
        const iframeObjects = canvasObjects.filter(obj => obj.isIframe);
        
        // Limpiar iframes que ya no existen en el canvas
        const currentIframeIds = iframeObjects.map(obj => obj.iframeId);
        Array.from(iframeLayer.children).forEach(child => {
            if (!currentIframeIds.includes(child.id)) {
                child.remove();
            }
        });
        
        const vpt = canvas.viewportTransform;
        const zoom = canvas.getZoom();
        
        iframeObjects.forEach(obj => {
            let iframeEl = document.getElementById(obj.iframeId);
            
            if (!iframeEl) {
                iframeEl = document.createElement('iframe');
                iframeEl.id = obj.iframeId;
                iframeEl.src = obj.iframeUrl;
                iframeEl.frameBorder = "0";
                iframeEl.allow = "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture";
                iframeEl.allowFullscreen = true;
                iframeEl.style.position = 'absolute';
                iframeEl.style.pointerEvents = 'none'; // Desactivado por defecto para permitir mover el grupo
                iframeLayer.appendChild(iframeEl);
                
                // Permitir interactuar con el iframe al hacer doble clic
                iframeEl.addEventListener('mouseleave', () => {
                    iframeEl.style.pointerEvents = 'none';
                });
            }
            
            // Calcular posición y escala
            const bound = obj.getBoundingRect(true, true);
            const point = fabric.util.transformPoint({ x: obj.left, y: obj.top }, vpt);
            
            // Ajustar origen (top/left real renderizado en canvas)
            const realLeft = obj.originX === 'center' ? point.x - ((obj.width * obj.scaleX * zoom) / 2) : point.x;
            const realTop = obj.originY === 'center' ? point.y - ((obj.height * obj.scaleY * zoom) / 2) : point.y;
            
            iframeEl.style.left = realLeft + 'px';
            // Desplazar 30px (escalados) hacia abajo para dejar libre la barra superior de arrastre
            iframeEl.style.top = (realTop + (30 * obj.scaleY * zoom)) + 'px';
            
            iframeEl.style.width = obj.width + 'px';
            iframeEl.style.height = (obj.height - 30) + 'px';
            
            iframeEl.style.transformOrigin = '0 0';
            iframeEl.style.transform = `scale(${obj.scaleX * zoom}, ${obj.scaleY * zoom})`;
            
            // Rotación si la tuviera
            if (obj.angle) {
                iframeEl.style.transform += ` rotate(${obj.angle}deg)`;
            }
        });
    }

    if (btnEmbed) {
        btnEmbed.addEventListener('click', () => {
            setActiveTool(btnSelect, 'select');
            
            const iframeId = 'iframe_' + Date.now();
            const embedUrl = 'embed_card.php?iframeId=' + iframeId;
            
            const defaultWidth = 400;
            const defaultHeight = 350; // Size for the upload card
            
            const bg = new fabric.Rect({
                width: defaultWidth,
                height: defaultHeight,
                fill: '#1e293b',
                rx: 8, ry: 8
            });
            
            const titleBar = new fabric.Text("≡ Arrastrar de aquí (Subir o URL)", {
                fontSize: 12,
                fill: '#94a3b8',
                left: 10,
                top: 8,
                fontFamily: 'Inter, sans-serif'
            });
            
            const center = getViewportCenter(canvas);
            const group = new fabric.Group([bg, titleBar], {
                left: center.x,
                top: center.y,
                originX: 'center',
                originY: 'center',
                isIframe: true,
                iframeId: iframeId,
                iframeUrl: embedUrl
            });
            
            ensureId(group);
            canvas.add(group);
            canvas.setActiveObject(group);
            triggerSync();
        });
    }

    // Escuchar mensajes de los iframes (ej. embed_card.php enviando un nuevo video)
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'UPDATE_VIDEO_EMBED') {
            const iframeId = event.data.iframeId;
            const rawUrl = event.data.url;
            const isUploaded = event.data.isUploadedVideo;
            const vWidth = event.data.videoWidth;
            const vHeight = event.data.videoHeight;
            
            let finalUrl = '';
            let newWidth = 560;
            let newHeight = 315;
            
            if (isUploaded) {
                finalUrl = 'video_player.php?src=' + encodeURIComponent(rawUrl);
                
                if (vWidth && vHeight) {
                    const maxWidth = 640;
                    const maxHeight = 640;
                    let aspectRatio = vWidth / vHeight;
                    
                    if (vWidth > vHeight) {
                        // Landscape
                        newWidth = Math.min(vWidth, maxWidth);
                        newHeight = newWidth / aspectRatio;
                    } else {
                        // Portrait / Vertical
                        newHeight = Math.min(vHeight, maxHeight);
                        newWidth = newHeight * aspectRatio;
                    }
                } else {
                    newWidth = 640;
                    newHeight = 360;
                }
            } else {
                finalUrl = parseEmbedUrl(rawUrl);
                const isSpotify = finalUrl.includes('spotify.com');
                const isVerticalVideo = finalUrl.includes('tiktok.com') || rawUrl.includes('youtube.com/shorts/') || rawUrl.includes('instagram.com/reel/') || rawUrl.includes('facebook.com/reel/');
                
                if (isSpotify) {
                    newWidth = 300;
                    newHeight = 380;
                } else if (isVerticalVideo) {
                    newWidth = 340;
                    newHeight = 600;
                }
            }
            newHeight += 30; // Espacio para la barra superior
            
            // Buscar el objeto en el canvas y actualizarlo
            const obj = canvas.getObjects().find(o => o.isIframe && o.iframeId === iframeId);
            if (obj) {
                obj.iframeUrl = finalUrl;
                
                // Actualizar dimensiones
                const bgRect = obj.item(0);
                const titleText = obj.item(1);
                
                // En un grupo de Fabric.js, las posiciones son relativas al centro del grupo.
                bgRect.set({
                    width: newWidth,
                    height: newHeight,
                    left: -newWidth / 2,
                    top: -newHeight / 2
                });
                
                titleText.set({
                    left: (-newWidth / 2) + 10,
                    top: (-newHeight / 2) + 8
                });
                
                obj.set({ width: newWidth, height: newHeight });
                if (obj._calcBounds) obj._calcBounds();
                obj.set('dirty', true);
                obj.setCoords();
                
                // Actualizar el DOM del iframe si existe
                const iframeEl = document.getElementById(iframeId);
                if (iframeEl) {
                    iframeEl.src = finalUrl;
                }
                
                canvas.requestRenderAll();
                triggerSync();
            }
        }
    });

    function renderThread(pin) {
        if (!pin.thread || pin.thread.length === 0) {
            commentThreadView.style.display = 'none';
            return;
        }
        
        commentThreadView.style.display = 'flex';
        commentMessagesList.innerHTML = '';
        
        pin.thread.forEach((msg, index) => {
            const date = new Date(msg.timestamp).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
            const initial = msg.author.charAt(0).toUpperCase();
            
            // Avatar real vs placeholder
            let avatarHtml = `<div style="width: 24px; height: 24px; border-radius: 50%; background: #6366f1; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.7rem; flex-shrink: 0;">${initial}</div>`;
            if (msg.avatar && msg.avatar !== 'default_avatar.png') {
                avatarHtml = `<img src="assets/images/avatars/${msg.avatar}" style="width:24px; height:24px; border-radius:50%; object-fit:cover;" onerror="this.outerHTML='<div style=\\'width:24px;height:24px;border-radius:50%;background:#6366f1;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:0.7rem;\\'>${initial}</div>'">`;
            }
            
            commentMessagesList.innerHTML += `
                <div class="comment-msg-item" style="padding:10px 16px; display:flex; gap:10px;">
                    ${avatarHtml}
                    <div style="flex:1;">
                        <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:2px;">
                            <strong style="font-size:0.85rem; color:#1e293b;">${msg.author}</strong>
                            <span class="msg-date" style="font-size:0.7rem; color:#94a3b8;">${date}</span>
                        </div>
                        <div class="msg-text" style="font-size:0.85rem; color:#334155; line-height:1.4;">${msg.text}</div>
                    </div>
                </div>
            `;
        });
        commentMessagesList.scrollTop = commentMessagesList.scrollHeight;
    }

    if (commentSendBtn) {
        commentSendBtn.addEventListener('click', () => {
            if (!activeCommentPin || !commentInput.value.trim()) return;
            const text = commentInput.value.trim();
            const author = (channel && channel.members.me) ? (channel.members.me.info.name || 'Usuario') : 'Usuario';
            const avatar = (channel && channel.members.me) ? channel.members.me.info.avatar : null;
            
            if (!activeCommentPin.thread) activeCommentPin.thread = [];
            activeCommentPin.thread.push({
                author: author,
                avatar: avatar,
                text: text,
                timestamp: Date.now()
            });
            
            commentInput.value = '';
            renderThread(activeCommentPin);
            triggerSync();
        });
    }

    // Permitir enviar con Enter (Shift+Enter para salto de línea)
    if (commentInput) {
        commentInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                commentSendBtn.click();
            }
        });
    }

    // Botón para eliminar comentario
    const deleteCommentBtn = document.getElementById('delete-comment-btn');
    if (deleteCommentBtn) {
        deleteCommentBtn.addEventListener('click', () => {
            if (activeCommentPin) {
                canvas.remove(activeCommentPin);
                triggerSync();
                closePopover();
            }
        });
    }
    
    // Cambiar color del pin
    document.querySelectorAll('.comment-color-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!activeCommentPin) return;
            const color = e.target.getAttribute('data-color');
            activeCommentPin.set('fill', color !== '#94a3b8' ? color : '#94a3b8');
            canvas.requestRenderAll();
            triggerSync();
        });
    });

    // Interceptar click para agregar o abrir pin
    canvas.on('mouse:down', function(o) {
        // 1. Abrir hilo si se da clic en un pin existente
        if (o.target && o.target.isComment) {
            activeCommentPin = o.target;
            commentPopover.style.display = 'block';
            
            // Forzar reflow
            void commentPopover.offsetWidth;
            
            commentPopover.style.transform = 'scale(1)';
            commentPopover.style.opacity = '1';
            positionPopover(activeCommentPin);
            renderThread(activeCommentPin);
            setTimeout(() => commentInput.focus(), 100);
            return;
        }
        
        // 2. Crear pin nuevo
        if (currentTool === 'comment') {
            const pointer = canvas.getPointer(o.e);
            
            // Pin de círculo pequeño tipo Figma
            const pin = new fabric.Circle({
                radius: 8,
                fill: '#94a3b8',
                stroke: '#ffffff',
                strokeWidth: 2,
                shadow: new fabric.Shadow({ color: 'rgba(0,0,0,0.15)', blur: 4, offsetX: 0, offsetY: 2 }),
                left: pointer.x, 
                top: pointer.y, 
                originX: 'center',
                originY: 'center',
                selectable: true, 
                hasControls: false,
                hasBorders: false,
                isComment: true, 
                commentId: 'cmt_' + Date.now(), 
                thread: []
            });
            
            canvas.add(pin);
            triggerSync();
            
            // Auto abrir
            activeCommentPin = pin;
            commentPopover.style.display = 'block';
            void commentPopover.offsetWidth;
            commentPopover.style.transform = 'scale(1)';
            commentPopover.style.opacity = '1';
            positionPopover(activeCommentPin);
            renderThread(activeCommentPin);
            setTimeout(() => commentInput.focus(), 100);
            
            // Volver a herramienta seleccionar
            setActiveTool(btnSelect, 'select');
        }
    });

    // Soporte Táctil Avanzado (Pinch to Zoom & Touch Pan)
    let touchStartDistance = 0;
    let touchStartZoom = 1;
    let touchStartPanX = 0;
    let touchStartPanY = 0;
    let touchLastX = 0;
    let touchLastY = 0;
    
    canvasContainer.addEventListener('touchstart', function(e) {
        if (e.touches.length === 2) {
            e.preventDefault();
            const dx = e.touches[0].clientX - e.touches[1].clientX;
            const dy = e.touches[0].clientY - e.touches[1].clientY;
            touchStartDistance = Math.sqrt(dx * dx + dy * dy);
            touchStartZoom = canvas.getZoom();
            
            touchStartPanX = (e.touches[0].clientX + e.touches[1].clientX) / 2;
            touchStartPanY = (e.touches[0].clientY + e.touches[1].clientY) / 2;
        } else if (e.touches.length === 1 && currentTool === 'pan') {
            touchLastX = e.touches[0].clientX;
            touchLastY = e.touches[0].clientY;
        }
    }, {passive: false});

    canvasContainer.addEventListener('touchmove', function(e) {
        if (e.touches.length === 2) {
            e.preventDefault();
            const dx = e.touches[0].clientX - e.touches[1].clientX;
            const dy = e.touches[0].clientY - e.touches[1].clientY;
            const dist = Math.sqrt(dx * dx + dy * dy);
            
            const scale = dist / touchStartDistance;
            let newZoom = touchStartZoom * scale;
            if (newZoom > 20) newZoom = 20;
            if (newZoom < 0.1) newZoom = 0.1;
            
            const centerX = (e.touches[0].clientX + e.touches[1].clientX) / 2;
            const centerY = (e.touches[0].clientY + e.touches[1].clientY) / 2;
            
            canvas.zoomToPoint({ x: centerX, y: centerY }, newZoom);
            
            const panX = centerX - touchStartPanX;
            const panY = centerY - touchStartPanY;
            
            const vpt = canvas.viewportTransform;
            vpt[4] += panX;
            vpt[5] += panY;
            canvas.requestRenderAll();
            
            touchStartPanX = centerX;
            touchStartPanY = centerY;
            
            updateAnchorsPosition();
            updateContextMenuPosition();
        } else if (e.touches.length === 1 && currentTool === 'pan') {
            e.preventDefault();
            const deltaX = e.touches[0].clientX - touchLastX;
            const deltaY = e.touches[0].clientY - touchLastY;
            const vpt = canvas.viewportTransform;
            vpt[4] += deltaX;
            vpt[5] += deltaY;
            canvas.requestRenderAll();
            touchLastX = e.touches[0].clientX;
            touchLastY = e.touches[0].clientY;
            
            updateAnchorsPosition();
            updateContextMenuPosition();
        }
    }, {passive: false});



    function createLinkCard(url) {
        if(typeof showToast === 'function') showToast('Generando vista previa...', 'ph-spinner ph-spin');
        
        fetch('ajax/ajax_link_preview.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: url })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const center = getViewportCenter(canvas);
                
                function buildCard(validImg) {
                    const CARD_W = 340;
                    const PAD = 16;
                    const ACCENT_W = 4;
                    const CONTENT_LEFT = ACCENT_W + PAD;
                    const CONTENT_W = CARD_W - CONTENT_LEFT - PAD;
                    const objects = [];
                    let currentY = 0;
                    
                    let domain = '';
                    try { domain = new URL(url).hostname; } catch(e) { domain = url; }
                    
                    // === OG Image (full width, top, if available) ===
                    if (validImg) {
                        validImg.scaleToWidth(CARD_W);
                        validImg.set({ top: 0, left: 0, objectCaching: false });
                        currentY = validImg.getScaledHeight();
                        objects.push(validImg);
                    }
                    
                    const contentStartY = currentY;
                    currentY += PAD;
                    
                    // === Favicon + Domain header row ===
                    function buildContent(faviconImg) {
                        if (faviconImg && faviconImg.width > 0) {
                            faviconImg.scaleToHeight(18);
                            faviconImg.set({ left: CONTENT_LEFT, top: currentY, objectCaching: false });
                            objects.push(faviconImg);
                            
                            const domainHeader = new fabric.Textbox(domain, {
                                left: CONTENT_LEFT + 24, top: currentY + 1,
                                width: CONTENT_W - 28,
                                fontSize: 11,
                                fontFamily: 'Inter, sans-serif',
                                fontWeight: '500',
                                fill: '#94a3b8',
                                editable: false,
                                splitByGrapheme: false,
                                objectCaching: false
                            });
                            objects.push(domainHeader);
                            currentY += Math.max(18, domainHeader.height) + 10;
                        } else {
                            const domainHeader = new fabric.Textbox(domain, {
                                left: CONTENT_LEFT, top: currentY,
                                width: CONTENT_W,
                                fontSize: 11,
                                fontFamily: 'Inter, sans-serif',
                                fontWeight: '500',
                                fill: '#94a3b8',
                                editable: false,
                                splitByGrapheme: false,
                                objectCaching: false
                            });
                            objects.push(domainHeader);
                            currentY += domainHeader.height + 10;
                        }
                        
                        // === Title ===
                        const titleText = new fabric.Textbox(data.title || domain, {
                            left: CONTENT_LEFT, top: currentY,
                            width: CONTENT_W,
                            fontSize: 15,
                            lineHeight: 1.25,
                            fontFamily: 'Inter, sans-serif',
                            fontWeight: '700',
                            fill: '#0f172a',
                            editable: false,
                            splitByGrapheme: false,
                            objectCaching: false
                        });
                        objects.push(titleText);
                        currentY += titleText.height + 6;
                        
                        // === Description ===
                        if (data.description) {
                            const descText = new fabric.Textbox(data.description, {
                                left: CONTENT_LEFT, top: currentY,
                                width: CONTENT_W,
                                fontSize: 12,
                                lineHeight: 1.35,
                                fontFamily: 'Inter, sans-serif',
                                fill: '#64748b',
                                editable: false,
                                splitByGrapheme: false,
                                objectCaching: false
                            });
                            objects.push(descText);
                            currentY += descText.height + 10;
                        } else {
                            currentY += 6;
                        }
                        
                        // === Separator line ===
                        const sep = new fabric.Line(
                            [CONTENT_LEFT, currentY, CONTENT_LEFT + CONTENT_W, currentY],
                            { stroke: '#f1f5f9', strokeWidth: 1, objectCaching: false }
                        );
                        objects.push(sep);
                        currentY += 10;
                        
                        // === Footer: "Abrir enlace" action ===
                        const linkIcon = new fabric.Textbox('🔗', {
                            left: CONTENT_LEFT, top: currentY - 1,
                            width: 20,
                            fontSize: 12,
                            editable: false,
                            objectCaching: false
                        });
                        objects.push(linkIcon);
                        
                        const actionText = new fabric.Textbox('Doble clic para abrir  ↗', {
                            left: CONTENT_LEFT + 22, top: currentY,
                            width: CONTENT_W - 26,
                            fontSize: 11,
                            fontFamily: 'Inter, sans-serif',
                            fontWeight: '600',
                            fill: '#3b82f6',
                            editable: false,
                            splitByGrapheme: false,
                            objectCaching: false
                        });
                        objects.push(actionText);
                        currentY += actionText.height + PAD;
                        
                        // === Background (white card) ===
                        const bg = new fabric.Rect({
                            width: CARD_W, height: currentY,
                            left: 0, top: contentStartY,
                            fill: '#ffffff',
                            rx: validImg ? 0 : 10,
                            ry: validImg ? 0 : 10,
                            objectCaching: false
                        });
                        
                        // === Outer card with shadow ===
                        const outerBg = new fabric.Rect({
                            width: CARD_W, height: contentStartY + currentY,
                            left: 0, top: 0,
                            fill: '#ffffff',
                            rx: 10, ry: 10,
                            shadow: new fabric.Shadow({
                                color: 'rgba(15,23,42,0.08)',
                                blur: 20,
                                offsetX: 0,
                                offsetY: 8
                            }),
                            objectCaching: false
                        });
                        
                        // === Left accent bar ===
                        const accent = new fabric.Rect({
                            width: ACCENT_W,
                            height: currentY - 2,
                            left: 1,
                            top: contentStartY + 1,
                            fill: '#3b82f6',
                            rx: 2, ry: 2,
                            objectCaching: false
                        });
                        
                        // === Bottom rounded corners overlay (when image present) ===
                        const bottomRound = new fabric.Rect({
                            width: CARD_W,
                            height: 12,
                            left: 0,
                            top: contentStartY + currentY - 12,
                            fill: '#ffffff',
                            rx: 10, ry: 10,
                            objectCaching: false
                        });
                        
                        // Build final objects array: shadow bg first, then image, then white bg, then accent, then content
                        const finalObjects = [outerBg];
                        if (validImg) finalObjects.push(validImg);
                        finalObjects.push(bg);
                        if (validImg) finalObjects.push(bottomRound);
                        finalObjects.push(accent);
                        
                        // Add all content objects (skip validImg since already added)
                        objects.forEach(obj => {
                            if (obj !== validImg) finalObjects.push(obj);
                        });
                        
                        const group = new fabric.Group(finalObjects, {
                            left: center.x,
                            top: center.y,
                            originX: 'center',
                            originY: 'center',
                            linkUrl: url,
                            isComponent: true,
                            objectCaching: false,
                            hoverCursor: 'pointer'
                        });
                        
                        canvas.add(group);
                        canvas.setActiveObject(group);
                        canvas.requestRenderAll();
                        triggerSync();
                        broadcastDelta(group, 'added');
                        if (typeof showToast === 'function') showToast('Enlace agregado ✓', 'ph-link');
                    }
                    
                    // Load favicon from server-side base64
                    if (data.favicon) {
                        fabric.Image.fromURL(data.favicon, function(faviconImg) {
                            if (faviconImg && faviconImg.width > 0) {
                                buildContent(faviconImg);
                            } else {
                                buildContent(null);
                            }
                        });
                    } else {
                        buildContent(null);
                    }
                }
                
                if (data.image) {
                    // Pre-check with a native Image to avoid 0x0 fabric crash
                    const testImg = new Image();
                    testImg.crossOrigin = 'anonymous';
                    testImg.onload = function() {
                        if (testImg.naturalWidth > 0 && testImg.naturalHeight > 0) {
                            fabric.Image.fromURL(data.image, function(fImg) {
                                if (fImg && fImg.width > 0 && fImg.height > 0) {
                                    buildCard(fImg);
                                } else {
                                    buildCard(null);
                                }
                            }, { crossOrigin: 'anonymous' });
                        } else {
                            buildCard(null);
                        }
                    };
                    testImg.onerror = function() {
                        buildCard(null);
                    };
                    testImg.src = data.image;
                } else {
                    buildCard(null);
                }
            } else {
                if(typeof showToast === 'function') showToast('No se pudo generar la vista previa', 'ph-warning');
            }
        })
        .catch(() => {
            if(typeof showToast === 'function') showToast('Error al generar la vista previa', 'ph-warning');
        });
    }

})();
