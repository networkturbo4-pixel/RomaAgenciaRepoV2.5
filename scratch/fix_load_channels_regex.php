<?php
$file = 'c:\xampp\htdocs\CESARMENDOZA\modules\chat\chat.js';
$content = file_get_contents($file);

// Replace DOM REFS
$content = preg_replace("/const channelListGroup = \\$\('channel-list-group'\);\\s*const channelListDM = \\$\('channel-list-dm'\);/", "const channelListUnified = $('channel-list-unified');", $content);

// Replace loadChannels
$patternLoad = "/async function loadChannels\(\) \{.*channelListDM\.innerHTML = [^;]+;/sU";
$replaceLoad = <<<'EOD'
    let currentFilter = 'all';

    async function loadChannels() {
        const res = await fetch('modules/chat/ajax.php', {
            method: 'POST', body: new URLSearchParams({ action: 'get_channels' })
        });
        const data = await res.json();
        if (!data.success) return;

        let html = '';
        data.channels.forEach(ch => {
            const isGroup = ch.type === 'group' || ch.type === 'voice' || ch.type === 'video';
            
            if (currentFilter === 'group' && !isGroup) return;
            if (currentFilter === 'direct' && isGroup) return;

            const badge = ch.unread_count > 0 ? `<span class="channel-badge">${ch.unread_count}</span>` : '';
            const active = ch.id == currentChannelId ? 'active' : '';
            const preview = ch.last_message ? escapeHtml(ch.last_message).substring(0, 35) + (ch.last_message.length > 35 ? '...' : '') : 'Sin mensajes';

            let groupIcon = '<i class="ph ph-hash"></i>';
            if (ch.type === 'voice') groupIcon = '<i class="ph ph-headphones" style="color:#52c41a;"></i>';
            else if (ch.type === 'video') groupIcon = '<i class="ph ph-video-camera" style="color:#ff4d4f;"></i>';

            const isPublicIcon = ch.is_public ? '<i class="ph ph-globe" style="margin-left:4px; font-size:0.8rem; opacity:0.7;" title="Público"></i>' : '';
            const isSecretIcon = ch.is_secret ? '<i class="ph ph-lock-key" style="margin-left:4px; font-size:0.8rem; color:#ff4d4f;" title="Secreto"></i>' : '';
            const pinnedIcon = ch.is_pinned == 1 ? '<i class="ph ph-push-pin" style="margin-left:auto; color:var(--text-muted); font-size:0.8rem;"></i>' : '';

            if (isGroup) {
                html += `
                    <div class="channel-item ${active}" data-channel-id="${ch.id}">
                        <span class="channel-item-icon">${groupIcon}</span>
                        <div class="channel-item-info">
                            <div class="channel-item-name">${escapeHtml(ch.name)}${isPublicIcon}${isSecretIcon}</div>
                            <div class="channel-item-preview">${preview}</div>
                        </div>
                        ${badge}
                        ${pinnedIcon}
                    </div>`;
            } else {
                const other = ch.other_user || {};
                const onlineDot = other.is_online ? '<span class="online-dot"></span>' : '';
                html += `
                    <div class="channel-item ${active}" data-channel-id="${ch.id}">
                        <div style="position:relative;">
                            ${renderAvatar(other, 'sm')}
                            ${onlineDot}
                        </div>
                        <div class="channel-item-info">
                            <div class="channel-item-name">${escapeHtml(other.name || 'Usuario')}</div>
                            <div class="channel-item-preview">${preview}</div>
                        </div>
                        ${badge}
                        ${pinnedIcon}
                    </div>`;
            }
        });

        if (channelListUnified) {
            channelListUnified.innerHTML = html || '<div style="padding:0.5rem 1rem; font-size:0.8rem; color:var(--text-muted);">No hay canales</div>';
        }
EOD;

$content = preg_replace($patternLoad, $replaceLoad, $content);

// Check if currentFilter logic was added to init, if not, add it
if (strpos($content, "btn.dataset.filter") === false) {
    $searchInit = "loadChannels();";
    $replaceInit = <<<'EOD'
        loadChannels();

        document.querySelectorAll('.chat-filter-pill').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.chat-filter-pill').forEach(b => {
                    b.classList.remove('active');
                    b.style.background = 'transparent';
                    b.style.color = 'var(--text-muted)';
                });
                btn.classList.add('active');
                btn.style.background = 'var(--bg-color)';
                btn.style.color = 'var(--text-main)';
                currentFilter = btn.dataset.filter;
                loadChannels();
            });
        });
EOD;
    $content = preg_replace("/loadChannels\(\);/", $replaceInit, $content, 1); // only first match inside init()
}

file_put_contents($file, $content);
echo "Fixed loadChannels with regex\n";
?>
