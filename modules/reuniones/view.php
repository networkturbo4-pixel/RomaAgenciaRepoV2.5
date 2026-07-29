<?php
// modules/reuniones/view.php
require_once 'includes/header.php';

global $db;
$id = $_GET['id'] ?? 0;

$sql = "SELECT r.*, b.name as brand_name, b.logo as brand_logo 
        FROM reuniones r 
        LEFT JOIN client_brands b ON r.brand_id = b.id 
        WHERE r.id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$id]);
$reunion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reunion) {
    echo "<div class='card'><h2>Reunión no encontrada</h2></div>";
    require_once 'includes/footer.php';
    exit();
}
?>

<style>
    /* ========= ANIMATIONS ========= */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulse-soft {
        0%, 100% { opacity: 1; }
        50%      { opacity: 0.7; }
    }
    @keyframes scale-in {
        from { opacity: 0; transform: scale(0.95); }
        to   { opacity: 1; transform: scale(1); }
    }
    @keyframes glow-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.3); }
        50%      { box-shadow: 0 0 15px 5px rgba(239, 68, 68, 0.15); }
    }
    @keyframes float-icon {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-6px); }
    }
    @keyframes shimmer {
        0%   { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }
    @keyframes dot-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%      { opacity: 0.5; transform: scale(0.85); }
    }

    /* ========= LAYOUT ========= */
    .rv-layout {
        display: grid;
        grid-template-columns: 1fr 370px;
        gap: 1.75rem;
        align-items: start;
    }
    .rv-main {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        animation: fadeInUp 0.4s ease-out both;
    }
    .rv-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* ========= MAIN CARD ========= */
    .rv-main-card {
        padding: 1.75rem;
        animation: fadeInUp 0.4s ease-out both;
    }

    /* ========= HEADER ========= */
    .rv-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 1.75rem;
        padding-bottom: 1.25rem;
        border-bottom: none;
        background: linear-gradient(135deg,
            color-mix(in srgb, var(--primary-color) 4%, transparent) 0%,
            color-mix(in srgb, var(--primary-color) 1%, transparent) 100%);
        margin: -1.75rem -1.75rem 1.75rem -1.75rem;
        padding: 1.5rem 1.75rem;
        border-radius: var(--radius-lg, 12px) var(--radius-lg, 12px) 0 0;
        gap: 1rem;
        position: relative;
    }
    .rv-header::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 1.75rem;
        right: 1.75rem;
        height: 1px;
        background: linear-gradient(90deg,
            transparent 0%,
            var(--border-color) 20%,
            var(--border-color) 80%,
            transparent 100%);
    }

    .rv-header-left {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
        min-width: 0;
    }
    .rv-back-btn {
        color: var(--text-muted);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: color-mix(in srgb, var(--border-color) 50%, transparent);
        font-size: 1.1rem;
        flex-shrink: 0;
        transition: all var(--transition-fast, 0.15s ease-in-out);
        margin-top: 2px;
    }
    .rv-back-btn:hover {
        background: var(--border-color);
        color: var(--text-main);
        transform: translateX(-2px);
    }
    .rv-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-title);
        margin: 0 0 0.5rem 0;
        line-height: 1.3;
    }
    .rv-subtitle {
        color: var(--text-muted);
        margin: 0;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        flex-wrap: wrap;
    }
    .rv-info-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.25rem 0.65rem;
        background: color-mix(in srgb, var(--border-color) 50%, transparent);
        border-radius: 8px;
        font-size: 0.8rem;
        color: var(--text-muted);
        font-weight: 500;
        transition: all var(--transition-fast, 0.15s ease-in-out);
        border: 1px solid color-mix(in srgb, var(--border-color) 30%, transparent);
    }
    .rv-info-chip:hover {
        background: color-mix(in srgb, var(--border-color) 70%, transparent);
    }

    .rv-header-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
        align-self: flex-start;
    }
    .rv-header-actions .btn {
        font-size: 0.8rem;
        padding: 0.45rem 0.85rem;
        border-radius: 10px;
        transition: all 0.2s ease;
        font-weight: 600;
    }
    .rv-header-actions .btn:hover {
        transform: scale(1.04);
    }
    .rv-btn-edit {
        border-color: var(--primary-color) !important;
        color: var(--primary-color) !important;
    }
    .rv-btn-edit:hover {
        background: color-mix(in srgb, var(--primary-color) 8%, transparent) !important;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 15%, transparent);
    }
    .rv-btn-delete {
        border-color: #ef4444 !important;
        color: #ef4444 !important;
    }
    .rv-btn-delete:hover {
        background: color-mix(in srgb, #ef4444 8%, transparent) !important;
        box-shadow: 0 0 0 3px color-mix(in srgb, #ef4444 15%, transparent);
    }
    .rv-header-actions .btn-label { display: inline; }

    /* ========= CONTENT AREA ========= */
    .rv-summary-content {
        font-size: 0.95rem;
        line-height: 1.85;
        color: var(--text-main, var(--color-title));
        animation: fadeInUp 0.5s ease-out both;
    }
    .rv-empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-muted);
    }
    .rv-empty-state .rv-empty-icon {
        font-size: 2.8rem;
        margin-bottom: 1rem;
        color: color-mix(in srgb, var(--text-muted) 40%, transparent);
        display: block;
        animation: float-icon 3s ease-in-out infinite;
    }
    .rv-empty-state p {
        margin: 0.25rem 0;
    }
    .rv-notes-fallback {
        text-align: center;
        margin-bottom: 1rem;
        color: var(--text-muted);
        font-size: 0.85rem;
        animation: fadeInUp 0.4s ease-out both;
    }

    /* Notes iframe */
    .rv-notes-frame {
        width: 100%;
        height: 600px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        background: var(--bg-color, #f9fafb);
        box-shadow: var(--shadow-sm),
                    inset 0 1px 3px color-mix(in srgb, var(--border-color) 30%, transparent);
        animation: fadeInUp 0.5s ease-out 0.1s both;
    }

    /* ========= PRÓXIMOS PASOS ========= */
    .rv-pasos-card {
        padding: 1.5rem;
        animation: fadeInUp 0.5s ease-out 0.15s both;
    }
    .rv-pasos-title {
        margin: 0 0 1rem 0;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 700;
        color: var(--color-title);
    }
    .rv-pasos-title i {
        color: var(--primary-color);
        font-size: 1.2rem;
    }
    .rv-pasos-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .rv-pasos-list li {
        font-size: 0.93rem;
        line-height: 1.7;
        color: var(--text-main, var(--color-title));
        padding: 0.55rem 0.75rem;
        margin: 0 -0.75rem;
        border-radius: 8px;
        border-left: 3px solid transparent;
        transition: all 0.2s ease;
        position: relative;
    }
    .rv-pasos-list li:hover {
        background: color-mix(in srgb, var(--primary-color) 5%, transparent);
        border-left-color: var(--primary-color);
        padding-left: 1rem;
    }
    .rv-pasos-list li + li {
        margin-top: 0.15rem;
    }

    /* ========= VIDEO THEATER MODAL ========= */
    .video-theater {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.92);
        z-index: 10001;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        backdrop-filter: blur(12px);
    }
    .video-theater.active {
        display: flex;
        animation: scale-in 0.3s ease-out both;
    }
    .video-theater-header {
        width: 90%; max-width: 960px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
    }
    .video-theater-header h3 {
        color: #fff;
        margin: 0;
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
    }
    .video-theater-close {
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.15);
        color: #fff;
        width: 38px; height: 38px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        backdrop-filter: blur(10px);
    }
    .video-theater-close:hover {
        background: rgba(255,255,255,0.18);
        box-shadow: 0 0 15px rgba(255,255,255,0.1);
        transform: rotate(90deg);
    }
    .video-theater-body {
        width: 90%; max-width: 960px;
        aspect-ratio: 16/9;
        background: #000;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 25px 60px rgba(0,0,0,0.5),
                    0 0 0 1px rgba(255,255,255,0.05);
    }
    .video-theater-body iframe {
        width: 100%; height: 100%; border: 0;
    }
    .video-theater-footer {
        width: 90%; max-width: 960px;
        display: flex;
        justify-content: center;
        gap: 0.75rem;
        padding: 1rem 0;
    }
    .video-theater-footer .btn {
        font-size: 0.85rem;
        border-radius: 10px;
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.12) !important;
        color: #e2e8f0 !important;
        transition: all 0.2s ease;
        padding: 0.5rem 1rem;
    }
    .video-theater-footer .btn:hover {
        background: rgba(255,255,255,0.15);
        transform: translateY(-1px);
    }

    /* ========= RECORDING CARD — NETFLIX STYLE ========= */
    .rv-rec-card {
        padding: 1.35rem;
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #1e293b 100%);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 14px;
        color: #fff;
        animation: fadeInUp 0.5s ease-out 0.2s both;
        position: relative;
        overflow: hidden;
    }
    .rv-rec-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 1px;
        background: linear-gradient(90deg,
            transparent 0%,
            rgba(99, 102, 241, 0.4) 50%,
            transparent 100%);
    }
    .rv-rec-card h3 {
        margin: 0 0 0.85rem 0;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #f1f5f9;
        font-weight: 600;
    }
    .rv-rec-dot {
        width: 8px;
        height: 8px;
        background: #ef4444;
        border-radius: 50%;
        display: inline-block;
        animation: dot-pulse 2s ease-in-out infinite;
        box-shadow: 0 0 6px rgba(239, 68, 68, 0.4);
    }
    .rv-rec-thumb {
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        background: #000;
        aspect-ratio: 16/9;
        margin-bottom: 0.85rem;
        cursor: pointer;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .rv-rec-thumb:hover {
        transform: scale(1.02);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
    }
    .rv-rec-thumb iframe {
        width: 100%; height: 100%; border: 0;
        pointer-events: none;
        transition: transform 0.4s ease;
    }
    .rv-rec-thumb:hover iframe {
        transform: scale(1.05);
    }
    .rv-rec-thumb-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(0deg,
            rgba(0,0,0,0.6) 0%,
            rgba(0,0,0,0.2) 50%,
            rgba(0,0,0,0.1) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.3s ease;
    }
    .rv-rec-thumb:hover .rv-rec-thumb-overlay {
        background: linear-gradient(0deg,
            rgba(0,0,0,0.4) 0%,
            rgba(0,0,0,0.1) 50%,
            rgba(0,0,0,0.05) 100%);
    }
    .rv-rec-play-icon {
        width: 56px; height: 56px;
        background: rgba(255,255,255,0.95);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #0f172a;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        animation: glow-pulse 2.5s ease-in-out infinite;
    }
    .rv-rec-thumb:hover .rv-rec-play-icon {
        transform: scale(1.12);
        box-shadow: 0 6px 25px rgba(0,0,0,0.4);
    }
    .rv-rec-actions {
        display: flex;
        gap: 0.5rem;
    }
    .rv-rec-actions .btn {
        flex: 1;
        justify-content: center;
        font-size: 0.8rem;
        border-radius: 10px;
        padding: 0.5rem;
        transition: all 0.2s ease;
    }
    .rv-rec-btn-play {
        background: rgba(99, 102, 241, 0.85) !important;
        border-color: rgba(99, 102, 241, 0.9) !important;
        backdrop-filter: blur(10px);
    }
    .rv-rec-btn-play:hover {
        background: rgba(99, 102, 241, 1) !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }
    .rv-rec-btn-download {
        background: rgba(255,255,255,0.08) !important;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.15) !important;
        color: #cbd5e1 !important;
    }
    .rv-rec-btn-download:hover {
        background: rgba(255,255,255,0.15) !important;
        transform: translateY(-1px);
    }

    /* ========= SIDEBAR ========= */
    .rv-sidebar-wrapper {
        /* wrapper for mobile reordering */
    }

    /* ========= SIDEBAR DETAILS CARD ========= */
    .rv-details-card {
        padding: 1.35rem;
        animation: fadeInUp 0.4s ease-out 0.1s both;
    }
    .rv-details-card h3 {
        margin: 0 0 1rem 0;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 700;
        color: var(--color-title);
    }
    .rv-details-card h3 i {
        color: var(--primary-color);
    }
    .rv-detail-rows {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .rv-detail-row {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.7rem 0.75rem;
        border-radius: 10px;
        background: color-mix(in srgb, var(--bg-color) 60%, transparent);
        border: 1px solid color-mix(in srgb, var(--border-color) 40%, transparent);
        transition: all 0.2s ease;
    }
    .rv-detail-row:hover {
        background: color-mix(in srgb, var(--primary-color) 4%, transparent);
        border-color: color-mix(in srgb, var(--primary-color) 15%, transparent);
    }
    .rv-detail-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
    }
    .rv-detail-icon-status {
        background: color-mix(in srgb, var(--color-blue) 12%, transparent);
        color: var(--color-blue);
    }
    .rv-detail-icon-brand {
        background: color-mix(in srgb, var(--color-purple) 12%, transparent);
        color: var(--color-purple);
    }
    .rv-detail-icon-link {
        background: color-mix(in srgb, var(--color-green) 12%, transparent);
        color: var(--color-green);
    }
    .rv-detail-info {
        min-width: 0;
        flex: 1;
    }
    .rv-detail-label {
        font-size: 0.68rem;
        text-transform: uppercase;
        color: var(--text-muted);
        font-weight: 700;
        margin-bottom: 0.25rem;
        letter-spacing: 0.04em;
    }
    .rv-detail-value {
        font-size: 0.88rem;
        font-weight: 500;
        color: var(--text-main, var(--color-title));
    }

    /* Status badges */
    .rv-status {
        padding: 5px 14px;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        letter-spacing: 0.01em;
    }
    .rv-status-programada {
        background: color-mix(in srgb, var(--color-blue) 12%, transparent);
        color: var(--color-blue);
        box-shadow: 0 1px 4px color-mix(in srgb, var(--color-blue) 10%, transparent);
    }
    .rv-status-completada {
        background: color-mix(in srgb, var(--color-green) 12%, transparent);
        color: var(--color-green);
        box-shadow: 0 1px 4px color-mix(in srgb, var(--color-green) 10%, transparent);
    }
    .rv-status-cancelada {
        background: color-mix(in srgb, var(--color-red) 12%, transparent);
        color: var(--color-red);
        box-shadow: 0 1px 4px color-mix(in srgb, var(--color-red) 10%, transparent);
    }

    /* Brand logo ring */
    .rv-brand-logo {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        object-fit: cover;
        border: 2px solid color-mix(in srgb, var(--border-color) 60%, transparent);
        box-shadow: 0 1px 3px color-mix(in srgb, var(--border-color) 30%, transparent);
    }

    /* Meet link button */
    .rv-meet-link {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.65rem;
        background: color-mix(in srgb, var(--primary-color) 8%, transparent);
        border: 1px solid color-mix(in srgb, var(--primary-color) 18%, transparent);
        border-radius: 8px;
        color: var(--primary-color);
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.2s ease;
        word-break: break-all;
        max-width: 100%;
    }
    .rv-meet-link:hover {
        background: color-mix(in srgb, var(--primary-color) 14%, transparent);
        transform: translateY(-1px);
        box-shadow: 0 3px 8px color-mix(in srgb, var(--primary-color) 12%, transparent);
    }

    /* ========= ACTION BUTTONS ========= */
    .rv-action-btn {
        width: 100%;
        justify-content: center;
        font-size: 0.9rem;
    }

    /* ========= VIDEO CARD ========= */
    .rv-video-card {
        padding: 1.25rem;
        background: var(--bg-color, #f8fafc);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md, 12px);
    }
    .rv-video-card h3 {
        margin: 0 0 0.5rem 0;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* ========= MOBILE DETAILS TOGGLE ========= */
    .rv-details-toggle {
        display: none;
        width: 100%;
        padding: 0.8rem 1rem;
        background: var(--bg-surface, #fff);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        cursor: pointer;
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--text-main);
        align-items: center;
        justify-content: space-between;
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }
    .rv-details-toggle:hover {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 10%, transparent);
    }
    .rv-details-toggle i.chevron {
        transition: transform 0.3s ease;
        font-size: 1rem;
    }
    .rv-details-toggle.active i.chevron {
        transform: rotate(180deg);
    }
    .rv-toggle-badge {
        background: #10b981;
        color: white;
        font-size: 0.62rem;
        padding: 2px 7px;
        border-radius: 8px;
        margin-left: 0.4rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    /* ========= EDIT MODAL ========= */
    .rv-modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.45);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(6px);
    }
    .rv-modal-overlay.active {
        display: flex;
        animation: scale-in 0.25s ease-out both;
    }
    .rv-modal-content {
        background: var(--bg-surface, #fff);
        border-radius: 16px;
        width: 92%;
        max-width: 460px;
        padding: 1.75rem;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15),
                    0 0 0 1px color-mix(in srgb, var(--border-color) 50%, transparent);
        max-height: 90vh;
        overflow-y: auto;
        animation: fadeInUp 0.3s ease-out both;
    }
    .rv-modal-title {
        margin: 0 0 1.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--color-title, #111827);
        font-size: 1.15rem;
        font-weight: 700;
    }
    .rv-modal-title i {
        color: #ea4335;
        font-size: 1.25rem;
    }
    .rv-modal-form {
        display: flex;
        flex-direction: column;
        gap: 1.1rem;
    }
    .rv-form-label {
        display: block;
        margin-bottom: 0.45rem;
        font-weight: 600;
        font-size: 0.83rem;
        color: var(--text-main, #374151);
    }
    .rv-form-input {
        width: 100%;
        padding: 0.65rem 0.85rem;
        border: 1.5px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.88rem;
        background: var(--bg-color, #f8fafc);
        color: var(--text-main);
        transition: all 0.2s ease;
        font-family: inherit;
    }
    .rv-form-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 12%, transparent);
        background: var(--bg-surface);
    }
    .rv-form-hint {
        margin: 0.25rem 0 0 0;
        font-size: 0.7rem;
        color: var(--text-muted);
    }
    .rv-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.6rem;
        margin-top: 0.5rem;
    }
    .rv-modal-btn {
        padding: 0.55rem 1.15rem;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s ease;
        font-family: inherit;
    }
    .rv-modal-btn-cancel {
        background: color-mix(in srgb, var(--border-color) 60%, transparent);
        color: var(--text-main, #374151);
    }
    .rv-modal-btn-cancel:hover {
        background: var(--border-color);
    }
    .rv-modal-btn-submit {
        background: #ea4335;
        color: white;
        box-shadow: 0 2px 8px color-mix(in srgb, #ea4335 25%, transparent);
    }
    .rv-modal-btn-submit:hover {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px color-mix(in srgb, #ea4335 30%, transparent);
    }
    .rv-modal-btn-submit:disabled {
        opacity: 0.7;
        transform: none;
    }

    /* ========= DARK MODE ADJUSTMENTS ========= */
    [data-theme="dark"] .rv-header {
        background: linear-gradient(135deg,
            color-mix(in srgb, var(--primary-color) 6%, transparent) 0%,
            color-mix(in srgb, var(--primary-color) 2%, transparent) 100%);
    }
    [data-theme="dark"] .rv-info-chip {
        background: color-mix(in srgb, var(--border-color) 80%, transparent);
        border-color: color-mix(in srgb, var(--border-color) 60%, transparent);
    }
    [data-theme="dark"] .rv-detail-row {
        background: color-mix(in srgb, var(--border-color) 25%, transparent);
        border-color: color-mix(in srgb, var(--border-color) 50%, transparent);
    }
    [data-theme="dark"] .rv-notes-frame {
        border-color: var(--border-color);
        background: var(--bg-color);
    }
    [data-theme="dark"] .rv-rec-card {
        border-color: rgba(99, 102, 241, 0.25);
    }
    [data-theme="dark"] .rv-modal-content {
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5),
                    0 0 0 1px var(--border-color);
    }
    [data-theme="dark"] .rv-form-input {
        background: color-mix(in srgb, var(--border-color) 30%, transparent);
        border-color: var(--border-color);
    }
    [data-theme="dark"] .rv-modal-btn-cancel {
        background: color-mix(in srgb, var(--border-color) 80%, transparent);
        color: var(--text-muted);
    }
    [data-theme="dark"] .rv-details-toggle {
        background: var(--bg-surface);
    }
    [data-theme="dark"] .rv-meet-link {
        background: color-mix(in srgb, var(--primary-color) 12%, transparent);
    }

    /* ========= RESPONSIVE ========= */
    @media (max-width: 768px) {
        .rv-layout {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        .rv-title {
            font-size: 1.2rem;
        }
        .rv-subtitle {
            font-size: 0.8rem;
        }
        .rv-header {
            flex-direction: column;
            gap: 0.75rem;
            margin: -1.25rem -1.25rem 1.25rem -1.25rem;
            padding: 1.25rem;
        }
        .rv-header::after {
            left: 1.25rem;
            right: 1.25rem;
        }
        .rv-main-card {
            padding: 1.25rem;
        }
        .rv-header-actions {
            width: 100%;
        }
        .rv-header-actions .btn {
            flex: 1;
            justify-content: center;
            padding: 0.55rem 0.75rem;
            min-height: 44px;
        }
        .rv-header-actions .btn-label { display: none; }
        .rv-notes-frame {
            height: 400px;
        }

        /* Sidebar becomes collapsible */
        .rv-details-toggle {
            display: flex;
        }
        .rv-sidebar {
            display: none;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .rv-sidebar.show {
            display: flex;
        }

        /* Reorder: sidebar goes after main on mobile */
        .rv-sidebar-wrapper {
            order: -1;
        }

        /* Recording card full width prominence */
        .rv-rec-card {
            border-radius: 12px;
        }
        .rv-rec-actions .btn {
            min-height: 44px;
        }
    }

    @media (max-width: 480px) {
        .rv-title {
            font-size: 1.1rem;
        }
        .rv-notes-frame {
            height: 350px;
        }
        .rv-modal-content {
            padding: 1.25rem;
            border-radius: 14px;
        }
        .rv-modal-actions {
            flex-direction: column;
        }
        .rv-modal-btn {
            width: 100%;
            text-align: center;
            min-height: 44px;
        }
    }
</style>

<div class="rv-layout">
    
    <!-- Main Content -->
    <div class="rv-main">
        <div class="card rv-main-card">
            <div class="rv-header">
                <div class="rv-header-left">
                    <a href="index.php?module=reuniones" class="rv-back-btn" title="Volver a Reuniones">
                        <i class="ph ph-arrow-left"></i>
                    </a>
                    <div style="min-width:0;">
                        <h1 class="rv-title"><?php echo htmlspecialchars($reunion['motivo']); ?></h1>
                        <p class="rv-subtitle">
                            <span class="rv-info-chip">
                                <i class="ph ph-briefcase"></i> <?php echo htmlspecialchars($reunion['brand_name']); ?>
                            </span>
                            <span class="rv-info-chip">
                                <i class="ph ph-calendar-blank"></i> <?php echo date('d M, Y h:i A', strtotime($reunion['fecha_hora'])); ?>
                            </span>
                        </p>
                    </div>
                </div>
                <div class="rv-header-actions">
                    <button onclick="openEditMeetModal()" class="btn btn-outline rv-btn-edit">
                        <i class="ph ph-pencil-simple"></i> <span class="btn-label">Editar</span>
                    </button>
                    <button onclick="deleteMeet(<?php echo $reunion['id']; ?>)" class="btn btn-outline rv-btn-delete">
                        <i class="ph ph-trash"></i> <span class="btn-label">Eliminar</span>
                    </button>
                </div>
            </div>
            
            <?php if($reunion['resumen']): ?>
                <div class="rv-summary-content">
                    <?php echo nl2br(htmlspecialchars($reunion['resumen'])); ?>
                </div>
            <?php elseif($reunion['notes_link']): ?>
                <div class="rv-notes-fallback">
                    Google Meet no pudo generar el resumen automático para esta reunión, pero a continuación puedes ver el documento de transcripción original:
                </div>
                <div class="rv-notes-frame">
                    <iframe src="<?php echo htmlspecialchars(preg_replace('/\/edit.*?$/', '/preview', $reunion['notes_link'])); ?>" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>
                </div>
            <?php else: ?>
                <div class="rv-empty-state">
                    <i class="ph ph-hourglass rv-empty-icon"></i>
                    <p>Las notas de Gemini aún no se han procesado para esta reunión.</p>
                    <p style="font-size: 0.85rem;">Si la reunión ya terminó, el sistema extraerá las notas automáticamente cuando lleguen al correo.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if($reunion['proximos_pasos']): ?>
        <div class="card rv-pasos-card">
            <h2 class="rv-pasos-title">
                <i class="ph ph-list-checks"></i> Próximos Pasos Sugeridos
            </h2>
            <div>
                <?php 
                $pasos = explode("\n", $reunion['proximos_pasos']);
                echo "<ul class='rv-pasos-list'>";
                foreach($pasos as $paso) {
                    $p = trim($paso);
                    if($p) echo "<li>" . htmlspecialchars($p) . "</li>";
                }
                echo "</ul>";
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="rv-sidebar-wrapper">
        <!-- Mobile toggle -->
        <button type="button" class="rv-details-toggle" id="details-toggle" onclick="toggleDetails()">
            <span>
                <i class="ph ph-info"></i> Detalles y Acciones
                <?php if($reunion['recording_link']): ?>
                    <span class="rv-toggle-badge">Grabación</span>
                <?php endif; ?>
            </span>
            <i class="ph ph-caret-down chevron"></i>
        </button>

        <div class="rv-sidebar" id="rv-sidebar">
            <!-- Details card -->
            <div class="card rv-details-card">
                <h3>
                    <i class="ph ph-info"></i> Detalles
                </h3>
                
                <div class="rv-detail-rows">
                    <div class="rv-detail-row">
                        <div class="rv-detail-icon rv-detail-icon-status">
                            <i class="ph ph-flag"></i>
                        </div>
                        <div class="rv-detail-info">
                            <div class="rv-detail-label">Estado</div>
                            <div>
                                <?php 
                                    $sc = 'rv-status-programada';
                                    if($reunion['estado'] === 'Completada') $sc = 'rv-status-completada';
                                    elseif($reunion['estado'] === 'Cancelada') $sc = 'rv-status-cancelada';
                                ?>
                                <span class="rv-status <?php echo $sc; ?>"><?php echo $reunion['estado']; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="rv-detail-row">
                        <div class="rv-detail-icon rv-detail-icon-brand">
                            <i class="ph ph-buildings"></i>
                        </div>
                        <div class="rv-detail-info">
                            <div class="rv-detail-label">Marca / Cliente</div>
                            <div class="rv-detail-value" style="display:flex; align-items:center; gap:0.5rem;">
                                <?php if($reunion['brand_logo']): ?>
                                    <img src="<?php echo htmlspecialchars($reunion['brand_logo']); ?>" class="rv-brand-logo" alt="Logo">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($reunion['brand_name']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="rv-detail-row">
                        <div class="rv-detail-icon rv-detail-icon-link">
                            <i class="ph ph-video-camera"></i>
                        </div>
                        <div class="rv-detail-info">
                            <div class="rv-detail-label">Enlace Meet</div>
                            <div>
                                <?php if($reunion['meet_link']): ?>
                                    <a href="<?php echo htmlspecialchars($reunion['meet_link']); ?>" target="_blank" class="rv-meet-link">
                                        <i class="ph ph-arrow-square-out"></i>
                                        Abrir en Meet
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size: 0.85rem;">N/A</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recording card -->
            <?php if($reunion['recording_link']): 
                $embed_link = str_replace('/view', '/preview', $reunion['recording_link']);
            ?>
            <div class="rv-rec-card">
                <h3>
                    <span class="rv-rec-dot"></span>
                    <i class="ph ph-play-circle" style="color:#f87171;"></i> Grabación de la Reunión
                </h3>
                
                <div class="rv-rec-thumb" onclick="openVideoTheater()">
                    <iframe src="<?php echo htmlspecialchars($embed_link); ?>" allow="autoplay"></iframe>
                    <div class="rv-rec-thumb-overlay">
                        <div class="rv-rec-play-icon">
                            <i class="ph-fill ph-play"></i>
                        </div>
                    </div>
                </div>

                <div class="rv-rec-actions">
                    <button onclick="openVideoTheater()" class="btn btn-primary rv-rec-btn-play">
                        <i class="ph ph-play"></i> Reproducir
                    </button>
                    <a href="<?php echo htmlspecialchars($reunion['recording_link']); ?>&export=download" target="_blank" class="btn btn-outline rv-rec-btn-download">
                        <i class="ph ph-download-simple"></i> Descargar
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Video Theater Modal -->
<?php if($reunion['recording_link']): 
    $embed_link_theater = str_replace('/view', '/preview', $reunion['recording_link']);
?>
<div class="video-theater" id="video-theater">
    <div class="video-theater-header">
        <h3><i class="ph ph-play-circle"></i> <?php echo htmlspecialchars($reunion['motivo']); ?></h3>
        <button class="video-theater-close" onclick="closeVideoTheater()"><i class="ph ph-x"></i></button>
    </div>
    <div class="video-theater-body">
        <iframe id="theater-iframe" src="" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
    </div>
    <div class="video-theater-footer">
        <a href="<?php echo htmlspecialchars($reunion['recording_link']); ?>" target="_blank" class="btn btn-outline">
            <i class="ph ph-arrow-square-out"></i> Abrir en Drive
        </a>
        <a href="<?php echo htmlspecialchars($reunion['recording_link']); ?>&export=download" target="_blank" class="btn btn-outline">
            <i class="ph ph-download-simple"></i> Descargar
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Edit Meet Modal -->
<div id="edit-meet-modal" class="rv-modal-overlay">
    <div class="rv-modal-content">
        <h3 class="rv-modal-title">
            <i class="ph ph-video-camera"></i> Editar Google Meet
        </h3>
        <form id="edit-meet-form" class="rv-modal-form">
            <input type="hidden" id="edit-meet-id" value="<?php echo $reunion['id']; ?>">
            <div>
                <label class="rv-form-label">Motivo de la Reunión</label>
                <input type="text" id="edit-meet-motivo" class="form-control rv-form-input" required value="<?php echo htmlspecialchars($reunion['motivo']); ?>">
            </div>
            <div>
                <label class="rv-form-label">Marca / Cliente</label>
                <select id="edit-meet-marca" class="form-control rv-form-input" required data-current="<?php echo $reunion['brand_id']; ?>">
                    <option value="">Cargando marcas...</option>
                </select>
            </div>
            <div>
                <label class="rv-form-label">Fecha y Hora</label>
                <input type="datetime-local" id="edit-meet-fecha" class="form-control rv-form-input" required value="<?php echo date('Y-m-d\TH:i', strtotime($reunion['fecha_hora'])); ?>">
            </div>
            <div>
                <label class="rv-form-label">Invitados Extras (Emails, opcional)</label>
                <input type="text" id="edit-meet-guests" class="form-control rv-form-input" placeholder="ejemplo@correo.com, otro@correo.com" value="<?php echo htmlspecialchars($reunion['guests'] ?? ''); ?>">
                <p class="rv-form-hint">Separar correos por coma.</p>
            </div>
            <div>
                <label class="rv-form-label">Etiquetas (opcional)</label>
                <input type="text" id="edit-meet-tags" class="form-control rv-form-input" placeholder="estrategia, diseño, mensual" value="<?php echo htmlspecialchars($reunion['tags'] ?? ''); ?>">
            </div>
            <div class="rv-modal-actions">
                <button type="button" onclick="closeEditMeetModal()" class="rv-modal-btn rv-modal-btn-cancel">Cancelar</button>
                <button type="submit" id="edit-meet-submit" class="rv-modal-btn rv-modal-btn-submit">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDetails() {
    const sidebar = document.getElementById('rv-sidebar');
    const btn = document.getElementById('details-toggle');
    sidebar.classList.toggle('show');
    btn.classList.toggle('active');
}

function openVideoTheater() {
    const theater = document.getElementById('video-theater');
    const iframe = document.getElementById('theater-iframe');
    if (!theater || !iframe) return;
    
    // Set the src only when opening to avoid loading on page load
    const embedLink = '<?php echo isset($embed_link_theater) ? htmlspecialchars($embed_link_theater) : ""; ?>';
    if (embedLink) {
        iframe.src = embedLink;
    }
    theater.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeVideoTheater() {
    const theater = document.getElementById('video-theater');
    const iframe = document.getElementById('theater-iframe');
    if (!theater) return;
    
    theater.classList.remove('active');
    document.body.style.overflow = '';
    // Stop video by clearing src
    if (iframe) iframe.src = '';
}

// Close theater with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeVideoTheater();
        closeEditMeetModal();
    }
});

function deleteMeet(id) {
    if (!confirm('¿Estás seguro que deseas eliminar esta reunión? Se borrará también del calendario de Google si es posible.')) return;
    
    const data = new FormData();
    data.append('id', id);
    
    fetch('ajax/delete_meet.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if(window.showToast) window.showToast('Reunión eliminada exitosamente.', 'success');
                setTimeout(() => window.location.href = 'index.php?module=reuniones', 1000);
            } else {
                if(window.showToast) window.showToast('Error: ' + res.error, 'error');
            }
        })
        .catch(err => {
            if(window.showToast) window.showToast('Error de conexión', 'error');
        });
}

function openEditMeetModal() {
    const modal = document.getElementById('edit-meet-modal');
    modal.classList.add('active');
    
    const select = document.getElementById('edit-meet-marca');
    if (select.options.length <= 1) {
        fetch('ajax/get_all_brands.php')
            .then(r => r.json())
            .then(data => {
                select.innerHTML = '<option value="">Selecciona una marca...</option>';
                data.marcas.forEach(m => {
                    select.innerHTML += `<option value="${m.id}" ${m.id == select.dataset.current ? 'selected' : ''}>${m.name}</option>`;
                });
            });
    }
}

function closeEditMeetModal() {
    const modal = document.getElementById('edit-meet-modal');
    modal.classList.remove('active');
}

document.getElementById('edit-meet-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const btn = document.getElementById('edit-meet-submit');
    btn.disabled = true;
    btn.innerHTML = 'Guardando...';

    const data = new FormData();
    data.append('id', document.getElementById('edit-meet-id').value);
    data.append('motivo', document.getElementById('edit-meet-motivo').value);
    data.append('brand_id', document.getElementById('edit-meet-marca').value);
    const select = document.getElementById('edit-meet-marca');
    data.append('brand_name', select.options[select.selectedIndex].text);
    data.append('fecha', document.getElementById('edit-meet-fecha').value);
    data.append('guests', document.getElementById('edit-meet-guests').value);
    data.append('tags', document.getElementById('edit-meet-tags').value);

    fetch('ajax/update_meet.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if(window.showToast) window.showToast('Reunión actualizada exitosamente.', 'success');
                closeEditMeetModal();
                setTimeout(() => window.location.reload(), 1000);
            } else {
                if(window.showToast) window.showToast('Error: ' + (res.error || 'Desconocido'), 'error');
            }
        })
        .catch(err => {
            if(window.showToast) window.showToast('Error de conexión', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = 'Guardar Cambios';
        });
});

// Close modal on overlay click
document.getElementById('edit-meet-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEditMeetModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>
