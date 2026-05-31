<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($file);

$searchJitsi = <<<'EOD'
        const ch = data.channel;
        const isGroup = ch.type === 'group';
        channelName.textContent = isGroup ? `# ${ch.name}` : ch.name;
        channelMeta.textContent = `${ch.member_count} miembros · ${ch.online_count} online`;

        // Show header tabs
        if ($('chat-header-tabs')) {
            $('chat-header-tabs').style.display = 'flex';
        }
        if ($('btn-delete-channel')) {
            $('btn-delete-channel').style.display = 'block';
        }

        renderMessages(data.messages, true);
        startPolling();
    }
EOD;

$replaceJitsi = <<<'EOD'
        const ch = data.channel;
        const isGroup = ch.type === 'group';
        channelName.textContent = isGroup ? `# ${ch.name}` : ch.name;
        channelMeta.textContent = `${ch.member_count} miembros · ${ch.online_count} online`;

        // Show header tabs
        if ($('chat-header-tabs')) {
            $('chat-header-tabs').style.display = 'flex';
        }
        if ($('btn-delete-channel')) {
            $('btn-delete-channel').style.display = 'block';
        }

        if (ch.type === 'voice' || ch.type === 'video') {
            chatMessages.style.display = 'none';
            chatInputArea.style.display = 'none';
            if ($('chat-typing-indicator')) $('chat-typing-indicator').style.display = 'none';
            if ($('chat-pinned-bar')) $('chat-pinned-bar').style.display = 'none';
            if ($('voice-active-panel')) $('voice-active-panel').style.display = 'none';
            
            const jitsiContainer = $('jitsi-container');
            jitsiContainer.style.display = 'block';
            jitsiContainer.innerHTML = '';
            
            const domain = 'meet.jit.si';
            const roomName = 'SISTEMA_ROMA_' + ch.id + '_' + (ch.public_token || 'SECRET');
            const isAudioOnly = ch.type === 'voice';
            
            const options = {
                roomName: roomName,
                width: '100%',
                height: '100%',
                parentNode: jitsiContainer,
                userInfo: {
                    displayName: window.chatUserName || 'Usuario'
                },
                configOverwrite: {
                    startWithAudioMuted: false,
                    startWithVideoMuted: isAudioOnly,
                    prejoinPageEnabled: false,
                },
                interfaceConfigOverwrite: {
                    DISABLE_VIDEO_BACKGROUND: true
                }
            };
            
            window.currentJitsiApi = new JitsiMeetExternalAPI(domain, options);
        } else {
            if (window.currentJitsiApi) {
                window.currentJitsiApi.dispose();
                window.currentJitsiApi = null;
            }
            if ($('jitsi-container')) $('jitsi-container').style.display = 'none';
            chatMessages.style.display = 'block';
            chatInputArea.style.display = 'block';
            renderMessages(data.messages, true);
            startPolling();
        }
    }
EOD;

$content = str_replace($searchJitsi, $replaceJitsi, $content);
file_put_contents($file, $content);
echo "Added Jitsi logic to openChannel\n";
?>
