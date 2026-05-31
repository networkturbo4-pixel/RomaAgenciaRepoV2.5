<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($file);

$searchAttach = "                // Attachment\n                let attachHtml = '';\n                if (msg.attachment) {\n                    const ext = msg.attachment.split('.').pop().toLowerCase();\n                    if (['jpg','jpeg','png','gif','webp'].includes(ext)) {";

$replaceAttach = <<<'EOD'
                // Attachment
                let attachHtml = '';
                if (msg.attachment) {
                    const ext = msg.attachment.split('.').pop().toLowerCase();
                    if (msg.message_type === 'audio' || ['mp3','wav','ogg'].includes(ext)) {
                        attachHtml = `<div class="custom-audio-player">
                            <button class="audio-play-btn" onclick="toggleAudio(this)"><i class="ph-fill ph-play"></i></button>
                            <div class="audio-progress" onclick="seekAudio(event, this)">
                                <div class="audio-progress-bar"></div>
                                <div class="audio-progress-thumb"></div>
                            </div>
                            <div class="audio-time">0:00</div>
                            <audio src="${msg.attachment}" preload="metadata"></audio>
                        </div>`;
                    } else if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
EOD;

$content = str_replace($searchAttach, $replaceAttach, $content);

// Add initAudioPlayers and toggleAudio functions
$searchFunc = "    function closeChannel() {";
$replaceFunc = <<<'EOD'
    window.toggleAudio = function(btn) {
        const player = btn.closest('.custom-audio-player');
        const audio = player.querySelector('audio');
        const icon = btn.querySelector('i');
        
        // Pause all others
        document.querySelectorAll('audio').forEach(a => {
            if (a !== audio && !a.paused) {
                a.pause();
                const otherBtn = a.closest('.custom-audio-player')?.querySelector('.audio-play-btn i');
                if (otherBtn) otherBtn.className = 'ph-fill ph-play';
            }
        });
        
        if (audio.paused) {
            audio.play();
            icon.className = 'ph-fill ph-pause';
        } else {
            audio.pause();
            icon.className = 'ph-fill ph-play';
        }
    };
    
    window.seekAudio = function(e, progressContainer) {
        const player = progressContainer.closest('.custom-audio-player');
        const audio = player.querySelector('audio');
        if (!isFinite(audio.duration)) return;
        
        const rect = progressContainer.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const width = rect.width;
        const percent = Math.max(0, Math.min(1, clickX / width));
        
        audio.currentTime = percent * audio.duration;
    };

    function initAudioPlayers() {
        const newAudios = chatMessages.querySelectorAll('.custom-audio-player:not(.bound)');
        newAudios.forEach(player => {
            player.classList.add('bound');
            const audio = player.querySelector('audio');
            const progressBar = player.querySelector('.audio-progress-bar');
            const thumb = player.querySelector('.audio-progress-thumb');
            const timeDisplay = player.querySelector('.audio-time');

            audio.addEventListener('loadedmetadata', () => {
                if(isFinite(audio.duration)) {
                    timeDisplay.textContent = formatTimeSec(Math.floor(audio.duration));
                }
            });

            audio.addEventListener('timeupdate', () => {
                if (!isFinite(audio.duration)) return;
                const percent = (audio.currentTime / audio.duration) * 100 || 0;
                progressBar.style.width = percent + '%';
                thumb.style.left = percent + '%';
                timeDisplay.textContent = formatTimeSec(Math.floor(audio.currentTime));
            });

            audio.addEventListener('ended', () => {
                const icon = player.querySelector('.audio-play-btn i');
                if (icon) icon.className = 'ph-fill ph-play';
                progressBar.style.width = '0%';
                thumb.style.left = '0%';
                if (isFinite(audio.duration)) {
                    timeDisplay.textContent = formatTimeSec(Math.floor(audio.duration));
                }
            });
        });
    }
    
    function formatTimeSec(sec) {
        const m = Math.floor(sec / 60);
        const s = Math.floor(sec % 60);
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function closeChannel() {
EOD;

$content = str_replace($searchFunc, $replaceFunc, $content);

// Also need to call initAudioPlayers after renderMessage!
$searchRenderCall = "chatMessages.scrollTop = chatMessages.scrollHeight;\n                }";
$replaceRenderCall = "chatMessages.scrollTop = chatMessages.scrollHeight;\n                }\n                initAudioPlayers();";

$content = str_replace($searchRenderCall, $replaceRenderCall, $content);

file_put_contents($file, $content);
echo "Restored audio player JS logic\n";
?>
