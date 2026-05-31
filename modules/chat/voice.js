function getCurrentChannelId() {
    const activeItem = document.querySelector('.channel-item.active');
    return activeItem ? parseInt(activeItem.dataset.channelId) : null;
}

// voice.js - WebRTC Voice Rooms implementation

let localStream = null;
let peerConnections = {}; // { userId: RTCPeerConnection }
let voiceSyncTimer = null;
let currentVoiceChannelId = null;
let myPeerId = null;

const rtcConfig = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:global.stun.twilio.com:3478' }
    ]
};

document.addEventListener('DOMContentLoaded', () => {
    const btnJoinVoice = document.getElementById('btn-join-voice');
    const btnVoiceLeave = document.getElementById('btn-voice-leave');
    const btnVoiceMute = document.getElementById('btn-voice-mute');
    const voiceActivePanel = document.getElementById('voice-active-panel');

    if (btnJoinVoice) {
        btnJoinVoice.addEventListener('click', async () => {
            if (currentVoiceChannelId) return; // Already in a call
            const curChId = getCurrentChannelId();
            if (!curChId) return;

            try {
                localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                document.getElementById('local-audio').srcObject = localStream;
                
                currentVoiceChannelId = curChId;
                myPeerId = 'peer_' + Math.random().toString(36).substr(2, 9);
                
                voiceActivePanel.style.display = 'flex';
                btnJoinVoice.style.display = 'none';

                // Tell server we joined
                await fetch('modules/chat/ajax.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'voice_join', channel_id: currentVoiceChannelId, peer_id: myPeerId })
                });

                startVoiceSync();

            } catch (err) {
                console.error("Error accessing mic:", err);
                Swal.fire('Error', 'No se pudo acceder al micrófono', 'error');
            }
        });
    }

    if (btnVoiceLeave) {
        btnVoiceLeave.addEventListener('click', leaveVoiceRoom);
    }

    if (btnVoiceMute) {
        btnVoiceMute.addEventListener('click', () => {
            if (localStream) {
                const audioTrack = localStream.getAudioTracks()[0];
                if (audioTrack) {
                    audioTrack.enabled = !audioTrack.enabled;
                    btnVoiceMute.innerHTML = audioTrack.enabled ? '<i class="ph ph-microphone"></i>' : '<i class="ph ph-microphone-slash"></i>';
                    btnVoiceMute.style.color = audioTrack.enabled ? 'var(--text-main)' : '#ff4d4f';
                }
            }
        });
    }
});

let lastVoiceSignalId = 0;

function startVoiceSync() {
    if (voiceSyncTimer) clearInterval(voiceSyncTimer);
    voiceSyncTimer = setInterval(async () => {
        if (!currentVoiceChannelId) return;
        try {
            const res = await fetch('modules/chat/ajax.php', {
                method: 'POST',
                body: new URLSearchParams({ 
                    action: 'voice_sync', 
                    channel_id: currentVoiceChannelId,
                    last_signal_id: lastVoiceSignalId
                })
            });
            const data = await res.json();
            if (data.success) {
                renderVoiceParticipants(data.participants);
                processSignals(data.signals);
            }
        } catch (e) {
            console.error("Voice sync error:", e);
        }
    }, 2500); // Check every 2.5s for signals and pings
}

function renderVoiceParticipants(participants) {
    const container = document.getElementById('voice-participants-list');
    container.innerHTML = '';
    
    // Check if we need to initiate connections to anyone new
    participants.forEach(p => {
        if (p.peer_id !== myPeerId && !peerConnections[p.user_id]) {
            // We don't have a connection with this peer yet.
            // Convention: The user with higher ID creates the offer to avoid collision.
            if (window.chatUserId > p.user_id) {
                createPeerConnection(p.user_id, true);
            } else {
                createPeerConnection(p.user_id, false);
            }
        }

        // Render UI
        const avatarStr = p.avatar ? `<div style="width:32px;height:32px;border-radius:50%;background-image:url('${p.avatar}');background-size:cover;"></div>` : `<div style="width:32px;height:32px;border-radius:50%;background:var(--primary-color);color:white;display:flex;align-items:center;justify-content:center;font-weight:bold;">${p.name.charAt(0).toUpperCase()}</div>`;
        
        container.innerHTML += `
            <div style="display:flex; flex-direction:column; align-items:center; position:relative; min-width:40px;" title="${p.name}">
                ${avatarStr}
                <div style="position:absolute; bottom:-5px; right:-5px; background:var(--bg-color); border-radius:50%; padding:2px;">
                    <i class="ph ${p.is_muted == 1 ? 'ph-microphone-slash' : 'ph-microphone'}" style="font-size:0.7rem; color:${p.is_muted == 1 ? '#ff4d4f' : '#52c41a'}"></i>
                </div>
            </div>
        `;
    });
}

function processSignals(signals) {
    signals.forEach(async (sig) => {
        lastVoiceSignalId = Math.max(lastVoiceSignalId, sig.id);
        const senderId = sig.sender_id;
        const type = sig.signal_type;
        const payload = JSON.parse(sig.payload);

        let pc = peerConnections[senderId];
        if (!pc) {
            pc = createPeerConnection(senderId, false);
        }

        if (type === 'offer') {
            await pc.setRemoteDescription(new RTCSessionDescription(payload));
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            sendSignal(senderId, 'answer', pc.localDescription);
        } else if (type === 'answer') {
            await pc.setRemoteDescription(new RTCSessionDescription(payload));
        } else if (type === 'ice') {
            try {
                await pc.addIceCandidate(new RTCIceCandidate(payload));
            } catch (e) {
                console.error("Error adding ice candidate", e);
            }
        }
    });
}

function createPeerConnection(remoteUserId, isOffer) {
    const pc = new RTCPeerConnection(rtcConfig);
    peerConnections[remoteUserId] = pc;

    if (localStream) {
        localStream.getTracks().forEach(track => {
            pc.addTrack(track, localStream);
        });
    }

    pc.onicecandidate = (event) => {
        if (event.candidate) {
            sendSignal(remoteUserId, 'ice', event.candidate);
        }
    };

    pc.ontrack = (event) => {
        let audioEl = document.getElementById('remote-audio-' + remoteUserId);
        if (!audioEl) {
            audioEl = document.createElement('audio');
            audioEl.id = 'remote-audio-' + remoteUserId;
            audioEl.autoplay = true;
            document.getElementById('remote-audios').appendChild(audioEl);
        }
        audioEl.srcObject = event.streams[0];
    };

    pc.onconnectionstatechange = () => {
        if (pc.connectionState === 'disconnected' || pc.connectionState === 'failed' || pc.connectionState === 'closed') {
            cleanupPeer(remoteUserId);
        }
    };

    if (isOffer) {
        pc.createOffer().then(offer => {
            return pc.setLocalDescription(offer);
        }).then(() => {
            sendSignal(remoteUserId, 'offer', pc.localDescription);
        });
    }

    return pc;
}

function sendSignal(receiverId, type, payload) {
    fetch('modules/chat/ajax.php', {
        method: 'POST',
        body: new URLSearchParams({
            action: 'voice_signal',
            channel_id: currentVoiceChannelId,
            receiver_id: receiverId,
            signal_type: type,
            payload: JSON.stringify(payload)
        })
    });
}

function cleanupPeer(userId) {
    if (peerConnections[userId]) {
        peerConnections[userId].close();
        delete peerConnections[userId];
    }
    const audioEl = document.getElementById('remote-audio-' + userId);
    if (audioEl) audioEl.remove();
}

async function leaveVoiceRoom() {
    if (!currentVoiceChannelId) return;

    if (voiceSyncTimer) clearInterval(voiceSyncTimer);
    voiceSyncTimer = null;

    Object.keys(peerConnections).forEach(uid => cleanupPeer(uid));

    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
        localStream = null;
    }

    document.getElementById('voice-active-panel').style.display = 'none';
    if (getCurrentChannelId() === currentVoiceChannelId) {
        const btnJoinVoice = document.getElementById('btn-join-voice');
        if (btnJoinVoice) btnJoinVoice.style.display = 'inline-flex';
    }

    const cid = currentVoiceChannelId;
    currentVoiceChannelId = null;
    myPeerId = null;

    await fetch('modules/chat/ajax.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'voice_leave', channel_id: cid })
    });
}

// Hook into chat.js channel switching to show/hide join button
document.addEventListener('DOMContentLoaded', () => {
    // Intercept or monkey patch openChannel?
    // Let's just poll for currentChannelId changes or define a global hook.
    // In chat.js, `window.onChannelOpened` can be called.
    const origOpen = window.openChannel; // It's not global. We need a way to detect channel switch.
    
    // Using MutationObserver on the chat-channel-name as a hacky but effective way to detect channel switches
    const targetNode = document.getElementById('chat-channel-name');
    if (targetNode) {
        const observer = new MutationObserver(() => {
            const btnJoinVoice = document.getElementById('btn-join-voice');
            if (!btnJoinVoice) return;
            
            // Only show for groups!
            // Wait, we can look at the channelsData or just assume if btn-group-info is visible
            setTimeout(() => {
                const btnGroupInfo = document.getElementById('btn-group-info');
                if (btnGroupInfo && btnGroupInfo.style.display !== 'none') {
                    // It's a group
                    if (currentVoiceChannelId !== getCurrentChannelId()) {
                        btnJoinVoice.style.display = 'inline-flex';
                    } else {
                        btnJoinVoice.style.display = 'none'; // Already joined
                    }
                } else {
                    btnJoinVoice.style.display = 'none'; // Direct message
                }
            }, 100);
        });
        observer.observe(targetNode, { childList: true, characterData: true, subtree: true });
    }
});
