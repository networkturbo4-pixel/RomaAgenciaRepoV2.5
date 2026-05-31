<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\index.php';
$content = file_get_contents($file);

$search1 = '<button class="chat-icon-btn" id="btn-chat-bg" title="Cambiar Fondo" style="display:none;"><i class="ph ph-paint-roller"></i></button>';

$replace1 = '<button class="chat-icon-btn" id="btn-join-voice" title="Unirse al canal de voz" style="display:none; color:var(--primary-color);"><i class="ph ph-phone-call"></i></button>
                <button class="chat-icon-btn" id="btn-chat-bg" title="Cambiar Fondo" style="display:none;"><i class="ph ph-paint-roller"></i></button>';

$search2 = '<!-- Messages Area -->';

$replace2 = '<!-- Voice Active Panel -->
        <div id="voice-active-panel" style="display:none; padding:1rem; background:color-mix(in srgb, var(--primary-color) 10%, transparent); border-bottom:1px solid var(--border-color); display:none; flex-direction:column; gap:1rem;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:0.5rem; color:var(--primary-color);">
                    <i class="ph ph-speaker-high" style="animation: pulse 1.5s infinite;"></i>
                    <span style="font-weight:600; font-size:0.9rem;">Sala de Voz Activa</span>
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <button id="btn-voice-mute" class="chat-icon-btn-sm" style="background:var(--bg-color);"><i class="ph ph-microphone"></i></button>
                    <button id="btn-voice-leave" class="chat-icon-btn-sm" style="background:#ff4d4f; color:white;"><i class="ph ph-phone-disconnect"></i></button>
                </div>
            </div>
            <div id="voice-participants-list" style="display:flex; gap:0.5rem; overflow-x:auto; padding-bottom:0.5rem;">
                <!-- Participants will go here -->
            </div>
            <audio id="local-audio" autoplay muted style="display:none;"></audio>
            <div id="remote-audios" style="display:none;"></div>
        </div>

        <!-- Messages Area -->';

// apply 1
$content = str_replace($search1, $replace1, $content);

// apply 2
$content = str_replace($search2, $replace2, $content);

file_put_contents($file, $content);
echo "Added Voice UI to index.php\n";
?>
