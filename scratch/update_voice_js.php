<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\voice.js';
$content = file_get_contents($file);

$search1 = '            if (!currentChannelId) return;

            try {
                localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                document.getElementById(\'local-audio\').srcObject = localStream;
                
                currentVoiceChannelId = currentChannelId;';

$replace1 = '            const curChId = getCurrentChannelId();
            if (!curChId) return;

            try {
                localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                document.getElementById(\'local-audio\').srcObject = localStream;
                
                currentVoiceChannelId = curChId;';

$search2 = '    if (currentChannelId === currentVoiceChannelId) {
        const btnJoinVoice = document.getElementById(\'btn-join-voice\');
        if (btnJoinVoice) btnJoinVoice.style.display = \'inline-flex\';
    }';

$replace2 = '    if (getCurrentChannelId() === currentVoiceChannelId) {
        const btnJoinVoice = document.getElementById(\'btn-join-voice\');
        if (btnJoinVoice) btnJoinVoice.style.display = \'inline-flex\';
    }';

$search3 = '                    // It\'s a group
                    if (currentVoiceChannelId !== currentChannelId) {
                        btnJoinVoice.style.display = \'inline-flex\';
                    } else {
                        btnJoinVoice.style.display = \'none\'; // Already joined
                    }';

$replace3 = '                    // It\'s a group
                    if (currentVoiceChannelId !== getCurrentChannelId()) {
                        btnJoinVoice.style.display = \'inline-flex\';
                    } else {
                        btnJoinVoice.style.display = \'none\'; // Already joined
                    }';

$header = "function getCurrentChannelId() {
    const activeItem = document.querySelector('.channel-item.active');
    return activeItem ? parseInt(activeItem.dataset.channelId) : null;
}

";

$content = str_replace($search1, $replace1, $content);
$content = str_replace($search2, $replace2, $content);
$content = str_replace($search3, $replace3, $content);

$content = $header . $content;

file_put_contents($file, $content);
echo "Fixed currentChannelId in voice.js\n";
?>
