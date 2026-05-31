<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($file);

// 1. Send `type` in create_channel
$search1 = "formData.append('is_public', $('group-is-public').checked ? 1 : 0);";
$replace1 = "formData.append('is_public', $('group-is-public').checked ? 1 : 0);
            const typeInput = document.querySelector('input[name=\"group-type\"]:checked');
            formData.append('type', typeInput ? typeInput.value : 'group');";
$content = str_replace($search1, $replace1, $content);

// 2. Render icon based on type
$search2 = "const groupIcon = isGroup ? '<i class=\"ph ph-users\" style=\"margin-right:8px; opacity:0.7;\"></i>' : '';";
$replace2 = "let groupIcon = '<i class=\"ph ph-users\" style=\"margin-right:8px; opacity:0.7;\"></i>';
            if (ch.type === 'voice') {
                groupIcon = '<i class=\"ph ph-headphones\" style=\"margin-right:8px; opacity:0.7; color:#52c41a;\"></i>';
            } else if (ch.type === 'video') {
                groupIcon = '<i class=\"ph ph-video-camera\" style=\"margin-right:8px; opacity:0.7; color:#ff4d4f;\"></i>';
            } else if (!isGroup) {
                groupIcon = '';
            }";
$content = str_replace($search2, $replace2, $content);

// 3. Jitsi logic in openChannel
$search3 = "            $('chat-messages').style.display = 'block';
            $('chat-bottom').style.display = 'flex';";

$replace3 = "            
            if (currentChannelData.type === 'voice' || currentChannelData.type === 'video') {
                $('chat-messages').style.display = 'none';
                $('chat-bottom').style.display = 'none';
                $('chat-typing-indicator').style.display = 'none';
                $('chat-pinned-bar').style.display = 'none';
                if ($('voice-active-panel')) $('voice-active-panel').style.display = 'none';
                
                const jitsiContainer = $('jitsi-container');
                jitsiContainer.style.display = 'block';
                jitsiContainer.innerHTML = '';
                
                const domain = 'meet.jit.si';
                const roomName = 'SISTEMA_ROMA_' + chId + '_' + (currentChannelData.public_token || 'SECRET');
                const isAudioOnly = currentChannelData.type === 'voice';
                
                const options = {
                    roomName: roomName,
                    width: '100%',
                    height: '100%',
                    parentNode: jitsiContainer,
                    userInfo: {
                        displayName: window.chatUserName
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
                $('chat-messages').style.display = 'block';
                $('chat-bottom').style.display = 'flex';
            }";
$content = str_replace($search3, $replace3, $content);

file_put_contents($file, $content);
echo "Updated chat.js for Jitsi integration\n";
?>
