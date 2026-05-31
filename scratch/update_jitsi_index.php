<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\index.php';
$content = file_get_contents($file);

// 1. Add Jitsi script before chat.js
$searchJitsi = '<script src="modules/chat/voice.js';
$replaceJitsi = '<script src="https://meet.jit.si/external_api.js"></script>' . "\n    " . $searchJitsi;

// 2. Add #jitsi-container and modify messages area
$searchMessages = '<!-- Messages Area -->
        <div class="chat-messages" id="chat-messages" style="display:none;"></div>';
$replaceMessages = '<!-- Jitsi Container -->
        <div id="jitsi-container" style="display:none; flex:1; width:100%; height:100%;"></div>

        <!-- Messages Area -->
        <div class="chat-messages" id="chat-messages" style="display:none;"></div>';

// 3. Add Type Selectors in Group Modal
$searchModal = '<form id="form-new-group">';
$replaceModal = '<form id="form-new-group">
            <div class="form-group">
                <label class="form-label" style="display:block; margin-bottom:0.5rem;">Tipo de Canal</label>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.5rem; margin-bottom:1rem;">
                    
                    <label style="cursor:pointer;">
                        <input type="radio" name="group-type" value="group" checked style="display:none;">
                        <div class="type-card" style="border:2px solid var(--primary-color); border-radius:var(--radius-md); padding:1rem; text-align:center; background:color-mix(in srgb, var(--primary-color) 10%, transparent);">
                            <i class="ph ph-chat-circle-dots" style="font-size:1.5rem; color:var(--primary-color);"></i>
                            <div style="font-size:0.8rem; font-weight:600; margin-top:0.3rem;">Texto</div>
                        </div>
                    </label>
                    
                    <label style="cursor:pointer;">
                        <input type="radio" name="group-type" value="voice" style="display:none;">
                        <div class="type-card" style="border:2px solid var(--border-color); border-radius:var(--radius-md); padding:1rem; text-align:center;">
                            <i class="ph ph-headphones" style="font-size:1.5rem; color:var(--text-muted);"></i>
                            <div style="font-size:0.8rem; font-weight:600; margin-top:0.3rem; color:var(--text-muted);">Voz</div>
                        </div>
                    </label>
                    
                    <label style="cursor:pointer;">
                        <input type="radio" name="group-type" value="video" style="display:none;">
                        <div class="type-card" style="border:2px solid var(--border-color); border-radius:var(--radius-md); padding:1rem; text-align:center;">
                            <i class="ph ph-video-camera" style="font-size:1.5rem; color:var(--text-muted);"></i>
                            <div style="font-size:0.8rem; font-weight:600; margin-top:0.3rem; color:var(--text-muted);">Video (Meet)</div>
                        </div>
                    </label>
                </div>
                
                <script>
                    document.querySelectorAll(\'input[name="group-type"]\').forEach(radio => {
                        radio.addEventListener(\'change\', function() {
                            document.querySelectorAll(\'.type-card\').forEach(card => {
                                card.style.borderColor = \'var(--border-color)\';
                                card.style.background = \'transparent\';
                                card.querySelector(\'i\').style.color = \'var(--text-muted)\';
                                card.querySelector(\'div\').style.color = \'var(--text-muted)\';
                            });
                            const card = this.nextElementSibling;
                            card.style.borderColor = \'var(--primary-color)\';
                            card.style.background = \'color-mix(in srgb, var(--primary-color) 10%, transparent)\';
                            card.querySelector(\'i\').style.color = \'var(--primary-color)\';
                            card.querySelector(\'div\').style.color = \'var(--text-main)\';
                        });
                    });
                </script>
            </div>';

$content = str_replace($searchJitsi, $replaceJitsi, $content);
$content = str_replace($searchMessages, $replaceMessages, $content);
$content = str_replace($searchModal, $replaceModal, $content);

file_put_contents($file, $content);
echo "Updated index.php with Jitsi integration logic\n";
?>
