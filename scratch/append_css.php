<?php
$css = <<<CSS

/* ==================================================================
   Módulo de Mensajes - Nuevos Estilos (Lightbox, Drive, Voice)
   ================================================================== */

.chat-multimedia-lightbox {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 2000;
    display: flex;
    flex-direction: column;
}
.chat-multimedia-lightbox .lightbox-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    color: white;
    background: linear-gradient(to bottom, rgba(0,0,0,0.7) 0%, transparent 100%);
}
.chat-multimedia-lightbox .lightbox-title {
    font-weight: 600;
    font-size: 1.1rem;
}
.chat-multimedia-lightbox .lightbox-actions {
    display: flex;
    gap: 0.5rem;
}
.chat-multimedia-lightbox .lightbox-actions button {
    color: white;
    background: rgba(255,255,255,0.1);
    border: none;
    border-radius: 50%;
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1.2rem;
    transition: background 0.2s;
}
.chat-multimedia-lightbox .lightbox-actions button:hover {
    background: rgba(255,255,255,0.2);
}
.chat-multimedia-lightbox .lightbox-body {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    overflow: hidden;
}
.chat-multimedia-lightbox .lightbox-body img,
.chat-multimedia-lightbox .lightbox-body video,
.chat-multimedia-lightbox .lightbox-body iframe {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}

/* Estado de Mensajes (Ticks) */
.msg-status-tick {
    font-size: 0.8rem;
    margin-left: 0.3rem;
    color: var(--text-muted);
}
.msg-status-tick.status-delivered {
    color: var(--text-muted); /* 2 grises */
}
.msg-status-tick.status-read {
    color: #3b82f6; /* 2 azules */
}

/* Audio Player modern */
.modern-audio-player {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(0,0,0,0.05);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    min-width: 200px;
}
.modern-audio-player button {
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 50%;
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
}
.modern-audio-player input[type=range] {
    flex: 1;
    accent-color: var(--primary-color);
}
.modern-audio-player span {
    font-size: 0.75rem;
    color: var(--text-muted);
}
CSS;

file_put_contents('c:/xampp/htdocs/CESARMENDOZA/modules/chat/chat.css', "\n" . $css, FILE_APPEND);
echo "CSS appended.\n";
?>
