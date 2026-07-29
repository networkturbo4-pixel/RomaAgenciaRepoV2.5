<?php
$file = 'public_board.php';
$content = file_get_contents($file);

// 1. Add CSS for switcher and lightbox
$cssToAdd = <<<CSS
        /* MEDIA SWITCHER */
        .media-switcher {
            display: flex;
            background: var(--surface);
            border-radius: 30px;
            padding: 4px;
            margin-bottom: 1rem;
        }
        .media-switch-btn {
            flex: 1;
            padding: 0.6rem;
            border-radius: 26px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.2s;
        }
        .media-switch-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .media-pane { display: none; }
        .media-pane.active { display: block; animation: fadeIn 0.3s; }
        
        /* LIGHTBOX */
        .lightbox-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }
        .lightbox-modal.active { display: flex; animation: fadeIn 0.3s; }
        .lightbox-img {
            max-width: 90%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            background: rgba(255,255,255,0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .lightbox-close:hover { background: rgba(255,255,255,0.4); }
CSS;

$content = str_replace('/* TABS */', $cssToAdd . "\n\n        /* TABS */", $content);

// 2. Update renderPreviewBox Swiper slide
$swiperSlideSearch = <<<HTML
<div class="swiper-slide" style="display: flex; align-items: center; justify-content: center; background: var(--surface-hover); border: 1px solid var(--border-color); border-radius: 12px;">
                        <img src="'.htmlspecialchars(\$mItem).'" style="width: 100%; height: 100%; object-fit: contain;">
                      </div>
HTML;
$swiperSlideReplace = <<<HTML
<div class="swiper-slide" style="display: flex; align-items: center; justify-content: center; background: var(--surface-hover); border: 1px solid var(--border-color); border-radius: 12px; cursor: pointer;" onclick="openLightbox(''.htmlspecialchars(\$mItem).'')">
                        <img src="'.htmlspecialchars(\$mItem).'" style="width: 100%; height: 100%; object-fit: contain;">
                      </div>
HTML;
$content = str_replace($swiperSlideSearch, $swiperSlideReplace, $content);

// 3. Update renderPreviewBox Single Image
$singleImgSearch = <<<HTML
return '<img src="'.htmlspecialchars(\$url).'" style="width: 100%; height: auto; max-height: 400px; display: block; border-radius: 12px; object-fit: contain; background: var(--surface-hover); border: 1px solid var(--border-color);">';
HTML;
$singleImgReplace = <<<HTML
return '<img src="'.htmlspecialchars(\$url).'" style="width: 100%; height: auto; max-height: 400px; display: block; border-radius: 12px; object-fit: contain; background: var(--surface-hover); border: 1px solid var(--border-color); cursor: pointer;" onclick="openLightbox(\''.htmlspecialchars(\$url).'\')">';
HTML;
$content = str_replace($singleImgSearch, $singleImgReplace, $content);


// 4. Update HTML Structure for MULTIMEDIA tab
$multimediaSearch = <<<HTML
                    <!-- MULTIMEDIA -->
                    <div class="tab-content" id="media-<?php echo \$p['id']; ?>">
                        <div class="col-title">Multimedia</div>
                        <div class="content-box" style="padding: 0; overflow: hidden; border: none; background: transparent; display:block;">
                            <?php echo renderPreviewBox(\$p['image_link'], false); ?>
                        </div>
                    </div>
HTML;
$multimediaReplace = <<<HTML
                    <!-- MULTIMEDIA -->
                    <div class="tab-content" id="media-<?php echo \$p['id']; ?>">
                        <div class="col-title">Multimedia</div>
                        <div class="media-switcher">
                            <button type="button" class="media-switch-btn active" onclick="switchMedia(this, 'ref-<?php echo \$p['id']; ?>', 'final-<?php echo \$p['id']; ?>')">Referencia gráfica</button>
                            <button type="button" class="media-switch-btn" onclick="switchMedia(this, 'final-<?php echo \$p['id']; ?>', 'ref-<?php echo \$p['id']; ?>')">Post Terminado</button>
                        </div>
                        <div class="content-box" style="padding: 0; overflow: hidden; border: none; background: transparent; display:block;">
                            <div class="media-pane active" id="ref-<?php echo \$p['id']; ?>">
                                <?php echo renderPreviewBox(\$p['reference_image_link'] ?? null, true); ?>
                            </div>
                            <div class="media-pane" id="final-<?php echo \$p['id']; ?>">
                                <?php if(empty(\$p['image_link'])): ?>
                                    <div style="background:var(--surface); display:flex; flex-direction:column; align-items:center; justify-content:center; height:250px; border-radius:12px; color:var(--text-muted); opacity: 0.6; border: 1px solid var(--border-color);">
                                        <i class="ph ph-image" style="font-size:3rem; margin-bottom:1rem;"></i>
                                        <p style="font-size: 13px; font-weight:600;">Aún no se ha subido el diseño final.</p>
                                    </div>
                                <?php else: ?>
                                    <?php echo renderPreviewBox(\$p['image_link'], false); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
HTML;
$content = str_replace($multimediaSearch, $multimediaReplace, $content);

// 5. Add Lightbox HTML and Script
$lightboxHtml = <<<HTML
<!-- LIGHTBOX MODAL -->
<div class="lightbox-modal" id="lightbox" onclick="closeLightbox(event)">
    <div class="lightbox-close"><i class="ph ph-x"></i></div>
    <img src="" class="lightbox-img" id="lightbox-img">
</div>
HTML;

$jsToAdd = <<<JS
    // Lightbox Logic
    function openLightbox(src) {
        document.getElementById('lightbox-img').src = src;
        document.getElementById('lightbox').classList.add('active');
    }
    function closeLightbox(e) {
        if(e.target.id === 'lightbox' || e.target.closest('.lightbox-close')) {
            document.getElementById('lightbox').classList.remove('active');
        }
    }
    
    // Switch Media Logic
    function switchMedia(btn, showId, hideId) {
        const switcher = btn.closest('.media-switcher');
        switcher.querySelectorAll('.media-switch-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        document.getElementById(hideId).classList.remove('active');
        document.getElementById(showId).classList.add('active');
    }
JS;

$content = str_replace('</body>', $lightboxHtml . "\n</body>", $content);
$content = str_replace('// Initialize Swiper', $jsToAdd . "\n\n    // Initialize Swiper", $content);

file_put_contents($file, $content);
echo "Changes applied successfully!";
