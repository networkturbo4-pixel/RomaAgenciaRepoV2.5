let ltLinks = [];
let ltSocialBar = [];

// =========================================================================
// Font Awesome 6 Official Brand & Social Icon Registry
// =========================================================================
const SOCIAL_BRANDS = {
    instagram: {
        name: 'Instagram',
        icon: 'fa-brands fa-instagram',
        color: '#E1306C',
        bg: 'linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%)',
        prefix: 'https://instagram.com/',
        placeholder: 'tu_usuario'
    },
    tiktok: {
        name: 'TikTok',
        icon: 'fa-brands fa-tiktok',
        color: '#000000',
        bg: '#000000',
        prefix: 'https://tiktok.com/@',
        placeholder: 'tu_usuario'
    },
    whatsapp: {
        name: 'WhatsApp',
        icon: 'fa-brands fa-whatsapp',
        color: '#25D366',
        bg: '#25D366',
        prefix: 'https://wa.me/',
        placeholder: '51987654321'
    },
    youtube: {
        name: 'YouTube',
        icon: 'fa-brands fa-youtube',
        color: '#FF0000',
        bg: '#FF0000',
        prefix: 'https://youtube.com/',
        placeholder: '@tu_canal o video'
    },
    facebook: {
        name: 'Facebook',
        icon: 'fa-brands fa-facebook-f',
        color: '#1877F2',
        bg: '#1877F2',
        prefix: 'https://facebook.com/',
        placeholder: 'tu_pagina'
    },
    x: {
        name: 'X (Twitter)',
        icon: 'fa-brands fa-x-twitter',
        color: '#000000',
        bg: '#000000',
        prefix: 'https://x.com/',
        placeholder: 'tu_usuario'
    },
    spotify: {
        name: 'Spotify',
        icon: 'fa-brands fa-spotify',
        color: '#1DB954',
        bg: '#1DB954',
        prefix: 'https://open.spotify.com/',
        placeholder: 'artista / playlist'
    },
    linkedin: {
        name: 'LinkedIn',
        icon: 'fa-brands fa-linkedin-in',
        color: '#0A66C2',
        bg: '#0A66C2',
        prefix: 'https://linkedin.com/in/',
        placeholder: 'tu_perfil'
    },
    telegram: {
        name: 'Telegram',
        icon: 'fa-brands fa-telegram',
        color: '#24A1DE',
        bg: '#24A1DE',
        prefix: 'https://t.me/',
        placeholder: 'tu_canal_o_usuario'
    },
    discord: {
        name: 'Discord',
        icon: 'fa-brands fa-discord',
        color: '#5865F2',
        bg: '#5865F2',
        prefix: 'https://discord.gg/',
        placeholder: 'invitacion'
    },
    twitch: {
        name: 'Twitch',
        icon: 'fa-brands fa-twitch',
        color: '#9146FF',
        bg: '#9146FF',
        prefix: 'https://twitch.tv/',
        placeholder: 'tu_canal'
    },
    threads: {
        name: 'Threads',
        icon: 'fa-brands fa-threads',
        color: '#000000',
        bg: '#000000',
        prefix: 'https://threads.net/@',
        placeholder: 'tu_usuario'
    },
    pinterest: {
        name: 'Pinterest',
        icon: 'fa-brands fa-pinterest-p',
        color: '#E60023',
        bg: '#E60023',
        prefix: 'https://pinterest.com/',
        placeholder: 'tu_tablero'
    },
    github: {
        name: 'GitHub',
        icon: 'fa-brands fa-github',
        color: '#24292F',
        bg: '#24292F',
        prefix: 'https://github.com/',
        placeholder: 'tu_usuario'
    },
    email: {
        name: 'Correo',
        icon: 'fa-solid fa-envelope',
        color: '#EA4335',
        bg: 'linear-gradient(135deg, #EA4335, #D93025)',
        prefix: 'mailto:',
        placeholder: 'hola@tudominio.com'
    },
    phone: {
        name: 'Teléfono',
        icon: 'fa-solid fa-phone',
        color: '#10B981',
        bg: 'linear-gradient(135deg, #10B981, #059669)',
        prefix: 'tel:',
        placeholder: '+51987654321'
    }
};

const DEFAULT_AVATAR = 'https://ui-avatars.com/api/?name=BioLink&background=6366f1&color=fff&bold=true&size=200';

// =========================================================================
// Curated SaaS Themes Database
// =========================================================================
const SAAS_THEMES = [
    {
        id: 'minimal',
        name: 'Minimal Snow',
        bg: '#ffffff',
        text: '#18181b',
        btn: '#f4f4f5',
        btnText: '#18181b',
        btnStyle: 'rounded-md',
        font: 'Inter'
    },
    {
        id: 'obsidian',
        name: 'Obsidian Pro',
        bg: '#09090b',
        text: '#f8fafc',
        btn: '#18181b',
        btnText: '#ffffff',
        btnStyle: 'rounded-md',
        font: 'Inter'
    },
    {
        id: 'sunset',
        name: 'Sunset Aura',
        bg: '#faf5ff',
        text: '#3b0764',
        btn: '#7e22ce',
        btnText: '#ffffff',
        btnStyle: 'rounded-full',
        font: 'Plus Jakarta Sans'
    },
    {
        id: 'cyber',
        name: 'Cyber Neon',
        bg: '#0b0f19',
        text: '#f1f5f9',
        btn: '#1e293b',
        btnText: '#06b6d4',
        btnStyle: 'rounded-none',
        font: 'Space Grotesk'
    },
    {
        id: 'emerald',
        name: 'Emerald Luxe',
        bg: '#064e3b',
        text: '#ecfdf5',
        btn: '#047857',
        btnText: '#ffffff',
        btnStyle: 'rounded-full',
        font: 'Playfair Display'
    },
    {
        id: 'corporate',
        name: 'Corporate Indigo',
        bg: '#f8fafc',
        text: '#0f172a',
        btn: '#4f46e5',
        btnText: '#ffffff',
        btnStyle: 'rounded-md',
        font: 'Roboto'
    },
    {
        id: 'pastel',
        name: 'Pastel Velvet',
        bg: '#fdf2f8',
        text: '#831843',
        btn: '#f472b6',
        btnText: '#ffffff',
        btnStyle: 'rounded-full',
        font: 'Outfit'
    },
    {
        id: 'glass',
        name: 'Glassmorphism',
        bg: '#0f172a',
        text: '#f8fafc',
        btn: '#1e293b',
        btnText: '#ffffff',
        btnStyle: 'rounded-md',
        font: 'Space Grotesk'
    }
];

// Helper para escapar HTML seguro en templates
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Calcular luminancia de color hex para contraste de barra de estado
function getHexLuminance(hex) {
    if (!hex) return 0.5;
    hex = hex.replace('#', '').trim();
    if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
    if (hex.length !== 6) return 0.5;
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    return (0.299 * r + 0.587 * g + 0.114 * b) / 255;
}

// Sincronizar indicadores visuales de colores
function syncColorDisplays() {
    const colors = [
        { id: 'lt_bg_color', hexId: 'lt_bg_color_hex', dotId: 'lt_bg_dot' },
        { id: 'lt_text_color', hexId: 'lt_text_color_hex', dotId: 'lt_text_dot' },
        { id: 'lt_btn_color', hexId: 'lt_btn_color_hex', dotId: 'lt_btn_dot' },
        { id: 'lt_btn_text_color', hexId: 'lt_btn_text_color_hex', dotId: 'lt_btn_text_dot' }
    ];

    colors.forEach(c => {
        const input = document.getElementById(c.id);
        const hex = document.getElementById(c.hexId);
        const dot = document.getElementById(c.dotId);
        if (input && hex) hex.innerText = input.value.toUpperCase();
        if (input && dot) dot.style.background = input.value;
    });
}

// =========================================================================
// Initialization
// =========================================================================
document.addEventListener('DOMContentLoaded', () => {
    initThemesGallery();
    initSocialPickerChips();
    initBlockChips();

    const inputs = [
        'lt_slug', 'lt_title', 'lt_bio', 'lt_verified_badge', 
        'lt_bg_color', 'lt_text_color', 'lt_btn_color', 'lt_btn_text_color', 
        'lt_btn_style', 'lt_font_family', 'lt_hide_watermark'
    ];

    inputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () => {
                syncColorDisplays();
                updateTopSlugHint();
                updateLinktreePreview();
            });
            if (el.tagName === 'SELECT' || el.type === 'checkbox') {
                el.addEventListener('change', () => {
                    syncColorDisplays();
                    updateTopSlugHint();
                    updateLinktreePreview();
                });
            }
        }
    });

    const imgInput = document.getElementById('lt_image');
    if (imgInput) {
        imgInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    window.ltPreviewImage = e.target.result;
                    const previewAvatar = document.getElementById('lt_avatar_preview_img');
                    if (previewAvatar) previewAvatar.src = e.target.result;
                    updateLinktreePreview();
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    linktreeLoadList();
    syncColorDisplays();
});

function updateTopSlugHint() {
    const slug = document.getElementById('lt_slug')?.value || 'mi-marca';
    const hint = document.getElementById('lt_top_slug_hint');
    if (hint) hint.innerText = '/l/' + slug;
}

// =========================================================================
// Theme Presets System
// =========================================================================
function initThemesGallery() {
    const container = document.getElementById('lt_themes_gallery');
    if (!container) return;
    container.innerHTML = '';

    SAAS_THEMES.forEach(t => {
        const card = document.createElement('div');
        card.className = `biolink-theme-card ${t.id === 'minimal' ? 'active' : ''}`;
        card.dataset.themeId = t.id;
        card.onclick = () => selectThemePreset(t.id);

        card.innerHTML = `
            <div class="biolink-theme-card-preview" style="background:${t.bg};">
                <div class="biolink-theme-card-mini-btn" style="background:${t.btn}; border:1px solid ${t.btnText}20;"></div>
            </div>
            <div class="biolink-theme-card-title">${t.name}</div>
            <div class="biolink-theme-check-icon"><i class="ph ph-check"></i></div>
        `;
        container.appendChild(card);
    });
}

function selectThemePreset(themeId) {
    const theme = SAAS_THEMES.find(t => t.id === themeId);
    if (!theme) return;

    document.querySelectorAll('.biolink-theme-card').forEach(c => {
        c.classList.toggle('active', c.dataset.themeId === themeId);
    });

    document.getElementById('lt_theme_preset').value = theme.id;
    document.getElementById('lt_bg_color').value = theme.bg;
    document.getElementById('lt_text_color').value = theme.text;
    document.getElementById('lt_btn_color').value = theme.btn;
    document.getElementById('lt_btn_text_color').value = theme.btnText;
    document.getElementById('lt_btn_style').value = theme.btnStyle;
    document.getElementById('lt_font_family').value = theme.font;

    syncColorDisplays();
    updateLinktreePreview();
}

window.linktreeApplyTheme = selectThemePreset;

// =========================================================================
// Social Bar System (Barra de Redes Oficiales con Font Awesome)
// =========================================================================
function initSocialPickerChips() {
    const container = document.getElementById('lt_social_picker_chips');
    if (!container) return;
    container.innerHTML = '';

    Object.keys(SOCIAL_BRANDS).forEach(k => {
        const brand = SOCIAL_BRANDS[k];
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'biolink-social-chip-btn';
        btn.onclick = () => addSocialNetwork(k);
        btn.innerHTML = `
            <i class="${brand.icon}" style="color:${brand.color}; font-size:1.15rem;"></i>
            <span>+ ${brand.name}</span>
        `;
        container.appendChild(btn);
    });
}

function addSocialNetwork(network) {
    const brand = SOCIAL_BRANDS[network];
    if (!brand) return;

    const existing = ltSocialBar.find(s => s.network === network);
    if (existing) {
        if (window.showToast) window.showToast(`${brand.name} ya está en tu barra de redes`, 'info');
        return;
    }

    ltSocialBar.push({
        network: network,
        url: ''
    });

    renderSocialBarEditor();
    updateLinktreePreview();
}

function removeSocialNetwork(index) {
    ltSocialBar.splice(index, 1);
    renderSocialBarEditor();
    updateLinktreePreview();
}

function updateSocialNetworkUrl(index, value) {
    if (ltSocialBar[index]) {
        ltSocialBar[index].url = value.trim();
        updateLinktreePreview();
    }
}

function renderSocialBarEditor() {
    const container = document.getElementById('lt_social_bar_container');
    const counter = document.getElementById('lt_social_bar_count');
    if (counter) counter.innerText = ltSocialBar.length;
    if (!container) return;

    container.innerHTML = '';

    if (ltSocialBar.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding:1.25rem; background:rgba(0,0,0,0.02); border:1.5px dashed var(--border-color); border-radius:14px; font-size:0.83rem; color:var(--text-muted);">
                Haz clic en los botones de arriba (+ Instagram, + TikTok, + WhatsApp...) para agregar tus redes a la cabecera.
            </div>
        `;
        return;
    }

    ltSocialBar.forEach((item, index) => {
        const brand = SOCIAL_BRANDS[item.network] || { name: item.network, icon: 'fa-solid fa-share-nodes', color: '#6366f1', bg: '#6366f1', placeholder: '', prefix: '' };
        
        container.innerHTML += `
            <div class="biolink-social-row-item">
                <div class="biolink-social-row-icon" style="background:${brand.bg}; color:#ffffff;">
                    <i class="${brand.icon}"></i>
                </div>
                <div style="display:flex; flex-direction:column; flex:1; min-width:0; gap:2px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:700; font-size:0.83rem; color:var(--text-main);">${brand.name}</span>
                        <span style="font-size:0.72rem; opacity:0.6; font-family:'SF Mono',monospace;">${brand.prefix}</span>
                    </div>
                    <input type="text" class="biolink-social-row-input" value="${escapeHtml(item.url)}" placeholder="${brand.placeholder}" oninput="updateSocialNetworkUrl(${index}, this.value)">
                </div>
                <button type="button" class="biolink-social-row-del" onclick="removeSocialNetwork(${index})" title="Eliminar red">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
        `;
    });
}

// =========================================================================
// Block Chips Row (Quick Add Bloques)
// =========================================================================
function initBlockChips() {
    const container = document.getElementById('lt_block_chips_row');
    if (!container) return;

    const chips = [
        { type: 'instagram', label: 'Instagram', class: 'chip-btn-instagram', icon: 'fa-brands fa-instagram', color: '#E1306C' },
        { type: 'tiktok', label: 'TikTok', class: 'chip-btn-tiktok', icon: 'fa-brands fa-tiktok', color: '#000000' },
        { type: 'whatsapp', label: 'WhatsApp', class: 'chip-btn-whatsapp', icon: 'fa-brands fa-whatsapp', color: '#25D366' },
        { type: 'youtube', label: 'YouTube', class: 'chip-btn-youtube', icon: 'fa-brands fa-youtube', color: '#FF0000' },
        { type: 'spotify', label: 'Spotify', class: 'chip-btn-spotify', icon: 'fa-brands fa-spotify', color: '#1DB954' },
        { type: 'facebook', label: 'Facebook', class: 'chip-btn-facebook', icon: 'fa-brands fa-facebook-f', color: '#1877F2' },
        { type: 'x', label: 'X (Twitter)', class: 'chip-btn-x', icon: 'fa-brands fa-x-twitter', color: '#000000' },
        { type: 'linkedin', label: 'LinkedIn', class: 'chip-btn-linkedin', icon: 'fa-brands fa-linkedin-in', color: '#0A66C2' },
        { type: 'link', label: 'Enlace Web', class: 'chip-btn-link', icon: 'fa-solid fa-globe', color: '#4f46e5' },
        { type: 'map', label: 'Mapa', class: 'chip-btn-map', icon: 'fa-solid fa-map-location-dot', color: '#d97706' },
        { type: 'text', label: 'Título / Texto', class: 'chip-btn-text', icon: 'fa-solid fa-heading', color: '#475569' },
        { type: 'faq', label: 'Preguntas FAQ', class: 'chip-btn-faq', icon: 'fa-solid fa-circle-question', color: '#9333ea' }
    ];

    container.innerHTML = '';
    chips.forEach(c => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `biolink-chip-btn ${c.class}`;
        btn.onclick = () => linktreeAddLink(c.type);
        btn.innerHTML = `<i class="${c.icon}" style="color:${c.color}; font-size:1.15rem;"></i> <span>${c.label}</span>`;
        container.appendChild(btn);
    });
}

// =========================================================================
// Link Blocks Editor
// =========================================================================
function linktreeAddLink(type = 'link') {
    let newBlock = { 
        title: '', 
        url: '', 
        type: type, 
        meta_data: { useBrandColor: true } 
    };

    if (SOCIAL_BRANDS[type]) {
        const b = SOCIAL_BRANDS[type];
        newBlock.title = b.name;
        newBlock.url = b.prefix;
        newBlock.meta_data.brand = type;
        newBlock.meta_data.useBrandColor = true;
    } else {
        switch(type) {
            case 'link':
                newBlock.title = 'Nuevo Enlace';
                newBlock.url = 'https://';
                break;
            case 'youtube':
                newBlock.title = 'Video de YouTube';
                newBlock.meta_data.videoId = '';
                break;
            case 'spotify':
                newBlock.title = 'Canción de Spotify';
                newBlock.meta_data.spotifyUrl = '';
                break;
            case 'text':
                newBlock.title = 'Título';
                newBlock.meta_data.text = 'Texto informativo para tu audiencia';
                break;
            case 'faq':
                newBlock.title = 'Pregunta Frecuente';
                newBlock.meta_data.answer = 'Respuesta detallada para tus visitantes...';
                break;
            case 'map':
                newBlock.title = 'Nuestra Ubicación';
                newBlock.meta_data.address = '';
                break;
        }
    }
    
    ltLinks.push(newBlock);
    renderLinksEditor();
    updateLinktreePreview();
}

function linktreeRemoveLink(index) {
    ltLinks.splice(index, 1);
    renderLinksEditor();
    updateLinktreePreview();
}

function linktreeUpdateLink(index, field, value) {
    if (ltLinks[index]) {
        ltLinks[index][field] = value;
        updateLinktreePreview();
    }
}

function linktreeUpdateLinkMetaData(index, field, value) {
    if (ltLinks[index]) {
        if (!ltLinks[index].meta_data) ltLinks[index].meta_data = {};
        ltLinks[index].meta_data[field] = value;
        updateLinktreePreview();
    }
}

function renderLinksEditor() {
    const container = document.getElementById('lt_links_container');
    if (!container) return;
    container.innerHTML = '';

    ltLinks.forEach((link, index) => {
        const brand = SOCIAL_BRANDS[link.type];
        let brandBadgeHtml = '';
        let typeLabel = 'Enlace';

        if (brand) {
            typeLabel = brand.name;
            brandBadgeHtml = `<div class="biolink-block-icon-badge" style="background:${brand.bg}; color:#ffffff;"><i class="${brand.icon}"></i></div>`;
        } else if (link.type === 'youtube') {
            typeLabel = 'YouTube';
            brandBadgeHtml = `<div class="biolink-block-icon-badge" style="background:#FF0000; color:#ffffff;"><i class="fa-brands fa-youtube"></i></div>`;
        } else if (link.type === 'spotify') {
            typeLabel = 'Spotify';
            brandBadgeHtml = `<div class="biolink-block-icon-badge" style="background:#1DB954; color:#ffffff;"><i class="fa-brands fa-spotify"></i></div>`;
        } else if (link.type === 'whatsapp') {
            typeLabel = 'WhatsApp';
            brandBadgeHtml = `<div class="biolink-block-icon-badge" style="background:#25D366; color:#ffffff;"><i class="fa-brands fa-whatsapp"></i></div>`;
        } else if (link.type === 'map') {
            typeLabel = 'Mapa';
            brandBadgeHtml = `<div class="biolink-block-icon-badge" style="background:#f59e0b; color:#fff;"><i class="fa-solid fa-map-location-dot"></i></div>`;
        } else if (link.type === 'text') {
            typeLabel = 'Título';
            brandBadgeHtml = `<div class="biolink-block-icon-badge" style="background:#3b82f6; color:#fff;"><i class="fa-solid fa-heading"></i></div>`;
        } else if (link.type === 'faq') {
            typeLabel = 'FAQ';
            brandBadgeHtml = `<div class="biolink-block-icon-badge" style="background:#8b5cf6; color:#fff;"><i class="fa-solid fa-circle-question"></i></div>`;
        } else {
            brandBadgeHtml = `<div class="biolink-block-icon-badge" style="background:#6366f1; color:#fff;"><i class="fa-solid fa-link"></i></div>`;
        }

        let innerHtml = '';

        if (brand || link.type === 'link' || !link.type) {
            const isBrand = !!brand;
            const useBrandColorChecked = (link.meta_data?.useBrandColor !== false) ? 'checked' : '';

            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Texto del Botón</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Ej: Visita nuestro ${typeLabel}" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">URL Destino</label>
                    <input type="url" value="${escapeHtml(link.url)}" class="biolink-input" placeholder="https://" oninput="linktreeUpdateLink(${index}, 'url', this.value)">
                </div>
                ${isBrand ? `
                <div class="biolink-toggle-row" style="margin-top:0.4rem; padding:0.6rem 0.85rem; border-radius:10px;">
                    <div>
                        <h5 style="font-size:0.82rem;">Usar Color Oficial de Marca</h5>
                        <p style="font-size:0.73rem;">Aplica los colores/degradados oficiales en el botón</p>
                    </div>
                    <label class="app-switch" style="margin:0; transform:scale(0.85); transform-origin:right center;">
                        <input type="checkbox" ${useBrandColorChecked} onchange="linktreeUpdateLinkMetaData(${index}, 'useBrandColor', this.checked)">
                        <span class="app-switch-slider"></span>
                    </label>
                </div>
                ` : ''}
            `;
        } else if (link.type === 'youtube') {
            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Título del Video (Opcional)</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Ej: Presentación Oficial" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0;">
                    <label class="biolink-label">Enlace o ID de YouTube</label>
                    <input type="text" value="${escapeHtml(link.meta_data?.videoId || '')}" class="biolink-input" placeholder="Ej: https://youtube.com/watch?v=... o ID" oninput="linktreeUpdateLinkMetaData(${index}, 'videoId', this.value)">
                </div>
            `;
        } else if (link.type === 'spotify') {
            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Título de la Canción o Playlist</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Ej: Escucha nuestro Podcast" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0;">
                    <label class="biolink-label">Enlace de Spotify</label>
                    <input type="url" value="${escapeHtml(link.meta_data?.spotifyUrl || '')}" class="biolink-input" placeholder="https://open.spotify.com/track/..." oninput="linktreeUpdateLinkMetaData(${index}, 'spotifyUrl', this.value)">
                </div>
            `;
        } else if (link.type === 'text') {
            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Encabezado</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Título o sección" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0;">
                    <label class="biolink-label">Texto Informativo</label>
                    <textarea class="biolink-textarea" rows="2" placeholder="Información adicional o comunicados..." oninput="linktreeUpdateLinkMetaData(${index}, 'text', this.value)">${escapeHtml(link.meta_data?.text || '')}</textarea>
                </div>
            `;
        } else if (link.type === 'faq') {
            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Pregunta</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Ej: ¿Cuáles son sus horarios?" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0;">
                    <label class="biolink-label">Respuesta</label>
                    <textarea class="biolink-textarea" rows="2" placeholder="Detalla la respuesta..." oninput="linktreeUpdateLinkMetaData(${index}, 'answer', this.value)">${escapeHtml(link.meta_data?.answer || '')}</textarea>
                </div>
            `;
        } else if (link.type === 'map') {
            innerHtml = `
                <div class="biolink-field" style="margin-bottom:0.5rem;">
                    <label class="biolink-label">Título de la Ubicación</label>
                    <input type="text" value="${escapeHtml(link.title)}" class="biolink-input" placeholder="Ej: Nuestra Sede Principal" oninput="linktreeUpdateLink(${index}, 'title', this.value)">
                </div>
                <div class="biolink-field" style="margin-bottom:0;">
                    <label class="biolink-label">Dirección o Nombre en Google Maps</label>
                    <input type="text" value="${escapeHtml(link.meta_data?.address || '')}" class="biolink-input" placeholder="Ej: Av. Larco 123, Miraflores, Lima" oninput="linktreeUpdateLinkMetaData(${index}, 'address', this.value)">
                </div>
            `;
        }

        // Programación de visibilidad
        const advancedHtml = `
            <details style="margin-top:0.35rem; padding-top:0.6rem; border-top:1px dashed var(--border-color);">
                <summary style="font-size:0.79rem; color:var(--text-muted); cursor:pointer; font-weight:600; outline:none; display:flex; align-items:center; gap:6px;">
                    <i class="ph ph-calendar"></i> Programación de visibilidad (Opcional)
                </summary>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-top:0.6rem;">
                    <div class="biolink-field" style="margin-bottom:0;">
                        <label class="biolink-label" style="font-size:0.75rem;">Mostrar desde:</label>
                        <input type="datetime-local" class="biolink-input" style="padding:0.45rem 0.65rem; font-size:0.82rem;" value="${link.meta_data?.start_date || ''}" oninput="linktreeUpdateLinkMetaData(${index}, 'start_date', this.value)">
                    </div>
                    <div class="biolink-field" style="margin-bottom:0;">
                        <label class="biolink-label" style="font-size:0.75rem;">Ocultar desde:</label>
                        <input type="datetime-local" class="biolink-input" style="padding:0.45rem 0.65rem; font-size:0.82rem;" value="${link.meta_data?.end_date || ''}" oninput="linktreeUpdateLinkMetaData(${index}, 'end_date', this.value)">
                    </div>
                </div>
            </details>
        `;

        container.innerHTML += `
            <div class="biolink-block-item" data-index="${index}">
                <div class="biolink-block-handle" title="Arrastra para reordenar">
                    <i class="ph ph-dots-six-vertical" style="font-size:1.3rem;"></i>
                </div>
                <div class="biolink-block-main">
                    <details class="lt-block-details" open>
                        <summary class="biolink-block-summary">
                            <div class="biolink-block-summary-left">
                                ${brandBadgeHtml}
                                <span class="biolink-block-summary-title">${escapeHtml(link.title || 'Bloque sin título')}</span>
                            </div>
                            <i class="ph ph-caret-down biolink-block-chevron"></i>
                        </summary>
                        <div class="biolink-block-body">
                            ${innerHtml}
                            ${advancedHtml}
                        </div>
                    </details>
                </div>
                <button type="button" class="biolink-block-delete-btn" onclick="linktreeRemoveLink(${index})" title="Eliminar bloque">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
        `;
    });

    if (window.Sortable) {
        new Sortable(container, {
            animation: 150,
            handle: '.biolink-block-handle',
            onEnd: function (evt) {
                const item = ltLinks.splice(evt.oldIndex, 1)[0];
                ltLinks.splice(evt.newIndex, 0, item);
                renderLinksEditor();
                updateLinktreePreview();
            }
        });
    }
}

// =========================================================================
// Real-Time Live Phone Mockup Preview
// =========================================================================
function updateLinktreePreview() {
    const slug = document.getElementById('lt_slug')?.value || 'mi-marca';
    const title = document.getElementById('lt_title')?.value || 'Nombre de la Marca';
    const bio = document.getElementById('lt_bio')?.value || '';
    const verified = document.getElementById('lt_verified_badge')?.checked || false;
    const font = document.getElementById('lt_font_family')?.value || 'Inter';
    const bgColor = document.getElementById('lt_bg_color')?.value || '#ffffff';
    const textColor = document.getElementById('lt_text_color')?.value || '#18181b';
    const btnColor = document.getElementById('lt_btn_color')?.value || '#f4f4f5';
    const btnTextColor = document.getElementById('lt_btn_text_color')?.value || '#18181b';
    const btnStyle = document.getElementById('lt_btn_style')?.value || 'rounded-md';
    const hideWatermark = document.getElementById('lt_hide_watermark')?.checked || false;

    const previewBox = document.getElementById('lt_preview_box');
    const previewContent = document.getElementById('lt_preview_content');
    const statusBar = document.getElementById('lt_phone_status_bar');
    const homeIndicator = document.getElementById('lt_home_indicator');
    const openBtn = document.getElementById('btnOpenBioLink');

    if (openBtn) {
        openBtn.href = 'l/' + encodeURIComponent(slug);
    }

    if (!previewBox || !previewContent) return;

    previewBox.style.background = bgColor;
    previewBox.style.color = textColor;
    previewBox.style.fontFamily = `"${font}", sans-serif`;

    const lum = getHexLuminance(bgColor);
    const contrastPhoneColor = lum > 0.5 ? '#18181b' : '#ffffff';
    if (statusBar) statusBar.style.color = contrastPhoneColor;
    if (homeIndicator) homeIndicator.style.color = contrastPhoneColor;

    const imgSrc = window.ltPreviewImage || (document.getElementById('lt_avatar_preview_img')?.src) || DEFAULT_AVATAR;

    let radius = '14px';
    if (btnStyle === 'rounded-full') radius = '9999px';
    if (btnStyle === 'rounded-none') radius = '0px';

    const verifiedBadgeHtml = verified ? `
        <i class="fa-solid fa-circle-check biolink-verified-badge" title="Cuenta Verificada" style="font-size:1.1rem; color:#0284c7; margin-left:4px;"></i>
    ` : '';

    // Social Bar HTML
    let socialBarHtml = '';
    if (ltSocialBar && ltSocialBar.length > 0) {
        socialBarHtml = '<div class="biolink-preview-social-bar">';
        ltSocialBar.forEach(s => {
            const brand = SOCIAL_BRANDS[s.network];
            if (brand) {
                socialBarHtml += `
                    <div class="biolink-preview-social-icon" title="${brand.name}" style="background:${brand.bg}; color:#ffffff;">
                        <i class="${brand.icon}"></i>
                    </div>
                `;
            }
        });
        socialBarHtml += '</div>';
    }

    let html = `
        <div style="margin-bottom:0.75rem; position:relative;">
            <img src="${imgSrc}" style="width:84px; height:84px; border-radius:50%; object-fit:cover; border:3px solid ${textColor}20; box-shadow:0 10px 22px rgba(0,0,0,0.14); background:rgba(0,0,0,0.05);" onerror="this.src='${DEFAULT_AVATAR}'">
        </div>
        <div style="display:flex; align-items:center; justify-content:center; gap:4px; margin-bottom:0.2rem;">
            <h3 style="font-weight:700; font-size:1.15rem; margin:0; letter-spacing:-0.015em;">${escapeHtml(title)}</h3>
            ${verifiedBadgeHtml}
        </div>
        <p style="font-size:0.8rem; font-weight:600; opacity:0.75; margin:0 0 0.55rem; font-family:'SF Mono',monospace;">@${escapeHtml(slug)}</p>
        ${bio ? `<p style="font-size:0.83rem; opacity:0.88; margin:0 0 0.65rem; white-space:pre-wrap; line-height:1.45; text-align:center; max-width:280px;">${escapeHtml(bio)}</p>` : ''}
        ${socialBarHtml}
        <div style="width:100%; display:flex; flex-direction:column; gap:0.75rem; padding-bottom:1rem;">
    `;

    ltLinks.forEach(link => {
        const brand = SOCIAL_BRANDS[link.type];
        const useBrandColor = link.meta_data?.useBrandColor !== false;

        if (brand || link.type === 'link' || !link.type) {
            let finalBg = btnColor;
            let finalTextColor = btnTextColor;
            let iconHtml = '';

            if (brand) {
                if (useBrandColor) {
                    finalBg = brand.bg;
                    finalTextColor = '#ffffff';
                    iconHtml = `<i class="${brand.icon}" style="position:absolute; left:1.15rem; font-size:1.25rem; color:#ffffff;"></i>`;
                } else {
                    iconHtml = `<i class="${brand.icon}" style="position:absolute; left:1.15rem; font-size:1.25rem; color:${brand.color};"></i>`;
                }
            } else {
                iconHtml = `<i class="fa-solid fa-link" style="position:absolute; left:1.15rem; font-size:1.1rem; opacity:0.75;"></i>`;
            }

            html += `
                <div style="width:100%; padding:0.8rem 1.25rem; background:${finalBg}; color:${finalTextColor}; border-radius:${radius}; font-weight:600; font-size:0.9rem; display:flex; align-items:center; justify-content:center; box-shadow:0 3px 10px rgba(0,0,0,0.07); border:1px solid ${finalTextColor}15; position:relative; box-sizing:border-box;">
                    ${iconHtml}
                    <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; padding:0 2rem;">${escapeHtml(link.title || 'Enlace')}</span>
                </div>
            `;
        } else if (link.type === 'youtube') {
            html += `
                <div style="width:100%; border-radius:${radius}; overflow:hidden; background:#000000; aspect-ratio:16/9; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; box-shadow:0 4px 14px rgba(0,0,0,0.25);">
                    <i class="fa-brands fa-youtube" style="font-size:2.5rem; color:#FF0000;"></i>
                    <span style="font-size:0.78rem; font-weight:600; color:#ffffff; padding:0 0.5rem; text-align:center;">${escapeHtml(link.title || 'Video de YouTube')}</span>
                </div>
            `;
        } else if (link.type === 'spotify') {
            html += `
                <div style="width:100%; border-radius:${radius}; overflow:hidden; background:#1DB954; padding:0.75rem 1rem; display:flex; align-items:center; gap:0.75rem; color:#000000; font-weight:700; box-shadow:0 4px 14px rgba(29,185,84,0.3);">
                    <i class="fa-brands fa-spotify" style="font-size:1.6rem; color:#000000;"></i>
                    <span style="font-size:0.86rem; flex:1; text-align:left; color:#000000;">${escapeHtml(link.title || 'Escuchar en Spotify')}</span>
                </div>
            `;
        } else if (link.type === 'text') {
            html += `
                <div style="width:100%; margin-top:0.35rem; text-align:center;">
                    <h5 style="font-weight:700; font-size:0.96rem; margin:0 0 0.25rem;">${escapeHtml(link.title || 'Título')}</h5>
                    <p style="font-size:0.81rem; opacity:0.85; margin:0; line-height:1.4;">${escapeHtml(link.meta_data?.text || '')}</p>
                </div>
            `;
        } else if (link.type === 'faq') {
            html += `
                <div style="width:100%; padding:0.75rem 0.95rem; background:${btnColor}; color:${btnTextColor}; border-radius:${radius}; text-align:left; box-shadow:0 2px 8px rgba(0,0,0,0.07); border-left:4px solid #6366f1;">
                    <div style="font-weight:700; font-size:0.85rem; display:flex; justify-content:space-between; align-items:center;">
                        <span>${escapeHtml(link.title || 'Pregunta Frecuente')}</span>
                        <i class="ph ph-caret-down" style="font-size:0.9rem; opacity:0.7;"></i>
                    </div>
                    ${link.meta_data?.answer ? `<div style="font-size:0.77rem; opacity:0.85; margin-top:5px; line-height:1.35;">${escapeHtml(link.meta_data.answer)}</div>` : ''}
                </div>
            `;
        } else if (link.type === 'map') {
            html += `
                <div style="width:100%; border-radius:${radius}; overflow:hidden; background:rgba(0,0,0,0.06); border:1px solid ${textColor}20; padding:0.75rem 1rem; display:flex; align-items:center; gap:0.75rem; text-align:left;">
                    <i class="fa-solid fa-map-location-dot" style="font-size:1.5rem; color:#ef4444; flex-shrink:0;"></i>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:0.85rem; font-weight:700;">${escapeHtml(link.title || 'Ubicación')}</div>
                        ${link.meta_data?.address ? `<div style="font-size:0.75rem; opacity:0.8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(link.meta_data.address)}</div>` : ''}
                    </div>
                </div>
            `;
        }
    });

    html += '</div>';

    // Watermark
    if (!hideWatermark) {
        html += `
            <div style="margin-top:auto; padding-top:1.5rem; text-align:center; font-size:0.72rem; opacity:0.6; display:flex; align-items:center; justify-content:center; gap:4px;">
                <i class="ph ph-sparkle"></i> Creado con <strong>Roma Agencia</strong>
            </div>
        `;
    }

    previewContent.innerHTML = html;
}

// =========================================================================
// Profile CRUD & Database Communication
// =========================================================================
function linktreeLoadList() {
    fetch('modules/herramientas/ajax_linktree.php?action=list')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const container = document.getElementById('linktreeList');
                if (!container) return;
                container.innerHTML = '';
                
                if (res.data.length === 0) {
                    container.innerHTML = `
                        <div style="grid-column:1/-1; text-align:center; padding:3rem 1.5rem; background:var(--bg-surface); border:1.5px dashed var(--border-color); border-radius:20px;">
                            <div style="width:64px; height:64px; border-radius:18px; background:linear-gradient(135deg,rgba(99,102,241,0.15),rgba(168,85,247,0.1)); color:var(--primary-color); display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; font-size:2rem;">
                                <i class="ph ph-link"></i>
                            </div>
                            <h4 style="margin:0 0 0.5rem; font-weight:700; color:var(--text-main); font-size:1.15rem;">No tienes perfiles BioLink</h4>
                            <p style="margin:0 0 1.5rem; color:var(--text-muted); font-size:0.88rem; max-width:380px; margin-left:auto; margin-right:auto;">Crea tu primer perfil público personalizado con redes sociales oficiales, enlaces y videos.</p>
                            <button class="btn btn-primary" onclick="linktreeNew()" style="border-radius:12px; padding:0.65rem 1.5rem; font-weight:600;"><i class="ph ph-plus"></i> Crear mi primer BioLink</button>
                        </div>
                    `;
                    return;
                }

                res.data.forEach(item => {
                    const activeChecked = item.is_active == 1 ? 'checked' : '';
                    const avatar = item.profile_image ? item.profile_image : DEFAULT_AVATAR;
                    const statusColor = item.is_active == 1 ? '#10b981' : '#71717a';
                    const statusText = item.is_active == 1 ? 'Activo' : 'Borrador';
                    const fullUrl = window.location.origin + window.location.pathname.replace('index.php', '') + 'l/' + item.slug;

                    container.innerHTML += `
                        <div class="biolink-profile-card">
                            <div class="biolink-profile-card-accent"></div>
                            
                            <div style="display:flex; align-items:center; gap:1rem;">
                                <div style="position:relative; flex-shrink:0;">
                                    <img src="${avatar}" style="width:56px; height:56px; border-radius:50%; object-fit:cover; border:2px solid var(--border-color); background:var(--bg-surface);" onerror="this.src='${DEFAULT_AVATAR}'">
                                    <span style="position:absolute; bottom:0; right:0; width:14px; height:14px; border-radius:50%; background:${statusColor}; border:2.5px solid var(--bg-card);" title="${statusText}"></span>
                                </div>
                                <div style="flex:1; min-width:0;">
                                    <h4 style="font-weight:700; font-size:1.05rem; margin:0 0 4px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(item.title)}</h4>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <a href="l/${item.slug}" target="_blank" style="font-size:0.84rem; color:var(--primary-color); text-decoration:none; font-weight:600; font-family:'SF Mono', monospace;">/l/${item.slug}</a>
                                        <button type="button" onclick="linktreeCopyText('${fullUrl}')" title="Copiar enlace" style="background:none; border:none; color:var(--text-muted); cursor:pointer; padding:2px; font-size:0.85rem; display:flex; align-items:center;"><i class="ph ph-copy"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.65rem 0.95rem; border-radius:12px; background:rgba(0,0,0,0.03); border:1px solid var(--border-color);">
                                <span style="display:flex; align-items:center; gap:6px; font-size:0.82rem; font-weight:600; color:var(--text-muted);">
                                    <i class="ph ph-eye" style="font-size:1.1rem; color:var(--primary-color);"></i> ${item.views || 0} visitas
                                </span>
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <span style="font-size:0.78rem; font-weight:600; color:var(--text-muted);">${statusText}</span>
                                    <label class="app-switch" style="margin:0; transform:scale(0.8); transform-origin:right center;">
                                        <input type="checkbox" onchange="linktreeToggleActive(${item.id}, this.checked)" ${activeChecked}>
                                        <span class="app-switch-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div style="display:flex; gap:0.6rem; margin-top:auto;">
                                <button type="button" class="btn btn-outline" style="padding:0.6rem 0.85rem; border-radius:10px; display:inline-flex; align-items:center; justify-content:center;" onclick="linktreeDownloadQR('${item.slug}', '${escapeHtml(item.title)}')" title="Descargar código QR"><i class="ph ph-qr-code" style="font-size:1.15rem;"></i></button>
                                <button type="button" class="btn btn-outline" style="flex:1; padding:0.6rem; border-radius:10px; font-weight:600; display:inline-flex; align-items:center; justify-content:center; gap:6px;" onclick="linktreeEdit(${item.id})"><i class="ph ph-pencil-simple" style="font-size:1.1rem;"></i> Editar</button>
                                <button type="button" class="btn btn-outline" style="padding:0.6rem 0.85rem; border-radius:10px; color:#ef4444; border-color:rgba(239,68,68,0.25); background:rgba(239,68,68,0.06);" onclick="linktreeDelete(${item.id})" title="Eliminar BioLink"><i class="ph ph-trash" style="font-size:1.15rem;"></i></button>
                            </div>
                        </div>
                    `;
                });
            }
        });
}

function linktreeCopyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            if (window.showToast) window.showToast('Enlace copiado al portapapeles', 'success');
        });
    } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        if (window.showToast) window.showToast('Enlace copiado al portapapeles', 'success');
    }
}

function linktreeCopyCurrentLink() {
    const slug = document.getElementById('lt_slug')?.value || 'mi-marca';
    const fullUrl = window.location.origin + window.location.pathname.replace('index.php', '') + 'l/' + slug;
    linktreeCopyText(fullUrl);
}

function linktreeDownloadQR(slug, title) {
    const fullUrl = window.location.origin + window.location.pathname.replace('index.php', '') + 'l/' + slug;
    const qrApi = `https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${encodeURIComponent(fullUrl)}`;
    
    if (window.showToast) window.showToast('Generando código QR...', 'info');
    
    fetch(qrApi)
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = `QR_BioLink_${title.replace(/\s+/g, '_')}.png`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            if (window.showToast) window.showToast('QR descargado con éxito', 'success');
        })
        .catch(() => {
            if (window.showToast) window.showToast('Error al generar QR', 'error');
        });
}

function linktreeToggleActive(id, isActive) {
    const fd = new FormData();
    fd.append('action', 'toggle_active');
    fd.append('id', id);
    fd.append('is_active', isActive ? 1 : 0);
    fetch('modules/herramientas/ajax_linktree.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                if (window.showToast) window.showToast(res.error, 'error');
            } else {
                linktreeLoadList();
            }
        });
}

function linktreeDelete(id) {
    if (!confirm('¿Estás seguro de eliminar este BioLink? Esta acción no se puede deshacer.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('modules/herramientas/ajax_linktree.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.showToast) window.showToast('BioLink eliminado correctamente', 'success');
                linktreeLoadList();
            } else {
                if (window.showToast) window.showToast(res.error, 'error');
            }
        });
}

function linktreeNew() {
    document.getElementById('lt_id').value = '';
    document.getElementById('lt_slug').value = '';
    document.getElementById('lt_title').value = '';
    document.getElementById('lt_bio').value = '';
    if (document.getElementById('lt_verified_badge')) document.getElementById('lt_verified_badge').checked = true;
    document.getElementById('lt_theme_preset').value = 'minimal';
    document.getElementById('lt_font_family').value = 'Inter';
    document.getElementById('lt_hide_watermark').checked = false;
    document.getElementById('lt_bg_color').value = '#ffffff';
    document.getElementById('lt_text_color').value = '#18181b';
    document.getElementById('lt_btn_color').value = '#f4f4f5';
    document.getElementById('lt_btn_text_color').value = '#18181b';
    document.getElementById('lt_btn_style').value = 'rounded-md';
    document.getElementById('lt_image').value = '';
    
    window.ltPreviewImage = null;
    const avatarPreview = document.getElementById('lt_avatar_preview_img');
    if (avatarPreview) avatarPreview.src = DEFAULT_AVATAR;
    
    ltLinks = [];
    ltSocialBar = [
        { network: 'instagram', url: '' },
        { network: 'whatsapp', url: '' }
    ];

    selectThemePreset('minimal');
    syncColorDisplays();
    updateTopSlugHint();
    renderSocialBarEditor();
    renderLinksEditor();
    updateLinktreePreview();
    
    document.getElementById('linktreeListSection').style.display = 'none';
    document.getElementById('linktreeEditorSection').style.display = 'block';
}

function linktreeEdit(id) {
    fetch(`modules/herramientas/ajax_linktree.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const data = res.data;
                document.getElementById('lt_id').value = data.id;
                document.getElementById('lt_slug').value = data.slug;
                document.getElementById('lt_title').value = data.title;
                document.getElementById('lt_bio').value = data.bio || '';
                
                const theme = data.theme_config || {};
                const presetId = theme.preset || 'minimal';
                document.getElementById('lt_theme_preset').value = presetId;
                document.getElementById('lt_font_family').value = theme.fontFamily || 'Inter';
                document.getElementById('lt_hide_watermark').checked = theme.hideWatermark || false;
                if (document.getElementById('lt_verified_badge')) {
                    document.getElementById('lt_verified_badge').checked = theme.verifiedBadge || false;
                }

                document.getElementById('lt_bg_color').value = theme.bgColor || '#ffffff';
                document.getElementById('lt_text_color').value = theme.textColor || '#18181b';
                document.getElementById('lt_btn_color').value = theme.btnColor || '#f4f4f5';
                document.getElementById('lt_btn_text_color').value = theme.btnTextColor || '#18181b';
                document.getElementById('lt_btn_style').value = theme.btnStyle || 'rounded-md';

                document.querySelectorAll('.biolink-theme-card').forEach(c => {
                    c.classList.toggle('active', c.dataset.themeId === presetId);
                });
                
                window.ltPreviewImage = data.profile_image ? data.profile_image : null;
                const avatarPreview = document.getElementById('lt_avatar_preview_img');
                if (avatarPreview) {
                    avatarPreview.src = data.profile_image ? data.profile_image : DEFAULT_AVATAR;
                }
                
                ltSocialBar = theme.socialBar || [];
                ltLinks = data.links || [];

                syncColorDisplays();
                updateTopSlugHint();
                renderSocialBarEditor();
                renderLinksEditor();
                updateLinktreePreview();
                
                document.getElementById('linktreeListSection').style.display = 'none';
                document.getElementById('linktreeEditorSection').style.display = 'block';
            }
        });
}

function linktreeCancel() {
    document.getElementById('linktreeEditorSection').style.display = 'none';
    document.getElementById('linktreeListSection').style.display = 'block';
}

function linktreeSave() {
    const slug = document.getElementById('lt_slug')?.value.trim();
    const title = document.getElementById('lt_title')?.value.trim();
    const saveBtn = document.getElementById('btnSaveBioLink');
    
    if (!slug || !title) {
        if (window.showToast) window.showToast('El Slug y el Título son requeridos', 'error');
        return;
    }

    const origSaveText = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
    }
    
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id', document.getElementById('lt_id').value);
    fd.append('slug', slug);
    fd.append('title', title);
    fd.append('bio', document.getElementById('lt_bio').value);
    
    const theme = {
        preset: document.getElementById('lt_theme_preset').value,
        fontFamily: document.getElementById('lt_font_family').value,
        hideWatermark: document.getElementById('lt_hide_watermark').checked,
        verifiedBadge: document.getElementById('lt_verified_badge')?.checked || false,
        bgColor: document.getElementById('lt_bg_color').value,
        textColor: document.getElementById('lt_text_color').value,
        btnColor: document.getElementById('lt_btn_color').value,
        btnTextColor: document.getElementById('lt_btn_text_color').value,
        btnStyle: document.getElementById('lt_btn_style').value,
        socialBar: ltSocialBar
    };

    fd.append('theme_config', JSON.stringify(theme));
    fd.append('links', JSON.stringify(ltLinks));
    
    const fileInput = document.getElementById('lt_image');
    if (fileInput && fileInput.files.length > 0) {
        fd.append('profile_image', fileInput.files[0]);
    }
    
    fetch('modules/herramientas/ajax_linktree.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = origSaveText;
            }
            if (res.success) {
                if (window.showToast) window.showToast('¡BioLink guardado con éxito!', 'success');
                linktreeCancel();
                linktreeLoadList();
            } else {
                if (window.showToast) window.showToast(res.error || 'Error al guardar BioLink', 'error');
            }
        })
        .catch(() => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = origSaveText;
            }
            if (window.showToast) window.showToast('Error de conexión al guardar', 'error');
        });
}
