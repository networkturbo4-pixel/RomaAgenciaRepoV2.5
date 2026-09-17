<?php
// modules/reuniones/rooms.php
// Salas de Reunión — Vista principal moderna estilo Bento / App macOS con presencia en tiempo real
require_once 'includes/header.php';

global $db;

// Fetch all active rooms with recordings count
$sql = "SELECT mr.*, u.name as creator_name, 
               (SELECT COUNT(*) FROM meeting_room_recordings WHERE room_id = mr.id) as recording_count
        FROM meeting_rooms mr
        LEFT JOIN users u ON mr.created_by = u.id
        WHERE mr.is_active = 1
        ORDER BY mr.created_at ASC";
$stmt = $db->query($sql);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics calculation
$totalRooms = count($rooms);
$totalRecordings = array_sum(array_column($rooms, 'recording_count'));
$readyMeetCount = count(array_filter($rooms, function($r) { return !empty($r['meet_link']); }));

// Get site URL for public links
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
?>

<!-- Phosphor Icons & Google Fonts (loaded if not already in header) -->
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">

<style>
/* ==========================================================================
   REUNIONES / SALAS APP STYLING — BENTO & MACOS SONOMA DESIGN SYSTEM
   ========================================================================== */

:root {
    --rm-primary: #6366f1;
    --rm-primary-glow: rgba(99, 102, 241, 0.25);
    --rm-surface: var(--bg-surface, #ffffff);
    --rm-border: var(--border-color, rgba(140, 149, 159, 0.2));
    --rm-text-main: var(--text-main, #1e293b);
    --rm-text-muted: var(--text-muted, #64748b);
    --rm-radius-lg: 18px;
    --rm-radius-md: 12px;
    --rm-radius-sm: 8px;
    --rm-bg-subtle: color-mix(in srgb, var(--border-color, #cbd5e1) 25%, transparent);
}

[data-theme="dark"] {
    --rm-surface: var(--bg-surface, #1e222d);
    --rm-border: var(--border-color, rgba(255, 255, 255, 0.08));
    --rm-text-main: var(--text-main, #f1f5f9);
    --rm-text-muted: var(--text-muted, #94a3b8);
    --rm-bg-subtle: rgba(255, 255, 255, 0.04);
}

.reuniones-page-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0.5rem 0 3rem 0;
    animation: rmFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes rmFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes livePulseRing {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

@keyframes liveGlowPulse {
    0%, 100% { box-shadow: 0 0 0 0 var(--card-glow); }
    50% { box-shadow: 0 0 20px 4px var(--card-glow); }
}

@keyframes liveDotPulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.3); opacity: 0.7; }
}

/* ========= SEGMENTED NAVIGATION ========= */
.reuniones-segmented-nav {
    display: inline-flex;
    align-items: center;
    background: var(--rm-bg-subtle);
    padding: 4px;
    border-radius: 14px;
    border: 1px solid var(--rm-border);
    margin-bottom: 1.5rem;
    gap: 4px;
}

.reuniones-nav-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.15rem;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--rm-text-muted);
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

.reuniones-nav-item i {
    font-size: 1.05rem;
}

.reuniones-nav-item:hover {
    color: var(--rm-text-main);
    background: color-mix(in srgb, var(--rm-surface) 60%, transparent);
}

.reuniones-nav-item.active {
    background: var(--rm-surface);
    color: var(--rm-primary);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
}

[data-theme="dark"] .reuniones-nav-item.active {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.nav-badge {
    background: color-mix(in srgb, var(--rm-text-muted) 15%, transparent);
    color: var(--rm-text-muted);
    font-size: 0.7rem;
    padding: 2px 7px;
    border-radius: 999px;
    font-weight: 700;
}

.reuniones-nav-item.active .nav-badge {
    background: color-mix(in srgb, var(--rm-primary) 12%, transparent);
    color: var(--rm-primary);
}

.nav-badge.live-pulse-badge {
    background: #ef4444;
    color: #ffffff;
    animation: liveDotPulse 2s infinite;
}

/* ========= HERO / LAUNCHPAD BANNER ========= */
.reuniones-hero-card {
    background: linear-gradient(135deg, 
        color-mix(in srgb, var(--rm-surface) 95%, #4f46e5 5%) 0%,
        color-mix(in srgb, var(--rm-surface) 90%, #8b5cf6 10%) 100%);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-lg);
    padding: 1.75rem 2rem;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
}

.reuniones-hero-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 320px;
    height: 320px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.12) 0%, transparent 70%);
    pointer-events: none;
}

.hero-left {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    z-index: 1;
}

.hero-icon-squircle {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 1.75rem;
    box-shadow: 0 8px 24px -4px rgba(99, 102, 241, 0.4);
    flex-shrink: 0;
}

.hero-title-group h1 {
    font-size: 1.45rem;
    font-weight: 700;
    color: var(--rm-text-main);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    letter-spacing: -0.02em;
}

.hero-tag {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 3px 8px;
    border-radius: 6px;
    background: color-mix(in srgb, #6366f1 14%, transparent);
    color: #6366f1;
    border: 1px solid color-mix(in srgb, #6366f1 25%, transparent);
}

.hero-title-group p {
    font-size: 0.85rem;
    color: var(--rm-text-muted);
    margin: 0.3rem 0 0 0;
}

.hero-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    z-index: 1;
    flex-shrink: 0;
}

.btn-hero-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #ffffff;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.65rem 1.25rem;
    border-radius: var(--rm-radius-md);
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 16px -2px rgba(99, 102, 241, 0.4);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    text-decoration: none;
}

.btn-hero-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(99, 102, 241, 0.5);
    color: #ffffff;
}

.btn-hero-secondary {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: var(--rm-surface);
    color: var(--rm-text-main);
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.65rem 1.15rem;
    border-radius: var(--rm-radius-md);
    border: 1px solid var(--rm-border);
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-hero-secondary:hover {
    background: var(--rm-bg-subtle);
    color: var(--rm-text-main);
    transform: translateY(-1px);
}

/* ========= BENTO MICRO STATS STRIP ========= */
.reuniones-bento-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.75rem;
}

.bento-stat-card {
    background: var(--rm-surface);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-md);
    padding: 1.1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.bento-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -6px rgba(0, 0, 0, 0.06);
}

.stat-icon-capsule {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.stat-icon-capsule.total { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
.stat-icon-capsule.live { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.stat-icon-capsule.recordings { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.stat-icon-capsule.ready { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

.stat-info-wrap {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--rm-text-muted);
}

.stat-value {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--rm-text-main);
    line-height: 1.2;
    margin-top: 0.15rem;
}

/* ========= ROOMS GRID & CARDS ========= */
.rooms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.25rem;
}

.room-bento-card {
    --card-color: #6366f1;
    --card-glow: color-mix(in srgb, var(--card-color) 25%, transparent);
    background: var(--rm-surface);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-lg);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.03);
}

.room-bento-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--card-color);
    opacity: 0.8;
    transition: all 0.2s ease;
}

.room-bento-card:hover {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--card-color) 40%, var(--rm-border));
    box-shadow: 0 12px 28px -6px rgba(0, 0, 0, 0.08), 0 0 0 1px color-mix(in srgb, var(--card-color) 20%, transparent);
}

.room-bento-card:hover::before {
    opacity: 1;
    height: 5px;
}

/* LIVE state active */
.room-bento-card.is-live {
    border-color: var(--card-color);
    animation: liveGlowPulse 2.5s infinite;
}

.room-bento-card.is-live::before {
    opacity: 1;
    height: 5px;
}

/* Card Header */
.room-header {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    margin-bottom: 1.1rem;
}

.room-squircle-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    color: #ffffff;
    background: var(--card-color);
    flex-shrink: 0;
    box-shadow: 0 6px 16px -2px color-mix(in srgb, var(--card-color) 40%, transparent);
    transition: transform 0.2s ease;
}

.room-bento-card:hover .room-squircle-icon {
    transform: scale(1.05);
}

.room-header-meta {
    flex: 1;
    min-width: 0;
}

.room-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--rm-text-main);
    margin: 0;
    line-height: 1.25;
    letter-spacing: -0.01em;
}

.room-description {
    font-size: 0.8rem;
    color: var(--rm-text-muted);
    margin: 0.3rem 0 0 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.room-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.25rem 0.65rem;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    flex-shrink: 0;
}

.room-status-badge.badge-live {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    display: none;
}

[data-theme="dark"] .room-status-badge.badge-live {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.3);
}

.room-bento-card.is-live .room-status-badge.badge-live {
    display: inline-flex;
}

.room-status-badge.badge-idle {
    background: var(--rm-bg-subtle);
    color: var(--rm-text-muted);
    border: 1px solid var(--rm-border);
}

.room-bento-card.is-live .room-status-badge.badge-idle {
    display: none;
}

.pulsing-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    animation: liveDotPulse 1.2s infinite;
}

/* Presence Section */
.room-presence-section {
    background: var(--rm-bg-subtle);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-md);
    padding: 0.65rem 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.1rem;
    min-height: 44px;
}

.presence-avatars-wrap {
    display: flex;
    align-items: center;
}

.presence-avatar-circle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid var(--rm-surface);
    background: var(--card-color);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
    margin-left: -6px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease;
}

.presence-avatar-circle:first-child {
    margin-left: 0;
}

.presence-avatar-circle:hover {
    transform: scale(1.15);
    z-index: 5;
}

.presence-avatar-circle img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.presence-label {
    font-size: 0.78rem;
    color: var(--rm-text-muted);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.presence-label i {
    font-size: 0.95rem;
}

/* Stats Pill Row */
.room-meta-chips {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding-top: 0.85rem;
    border-top: 1px solid var(--rm-border);
    margin-bottom: 1.1rem;
    font-size: 0.78rem;
    color: var(--rm-text-muted);
}

.meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.meta-chip i {
    font-size: 0.95rem;
    color: var(--card-color);
}

/* Action Buttons */
.room-actions-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.btn-room-join {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.65rem 1rem;
    border-radius: var(--rm-radius-md);
    background: var(--card-color);
    color: #ffffff !important;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px -2px color-mix(in srgb, var(--card-color) 40%, transparent);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.btn-room-join:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px 0 color-mix(in srgb, var(--card-color) 50%, transparent);
    color: #ffffff;
}

.room-secondary-actions {
    display: flex;
    gap: 0.4rem;
}

.btn-card-subtle {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.5rem 0.6rem;
    border-radius: var(--rm-radius-sm);
    background: var(--rm-bg-subtle);
    border: 1px solid var(--rm-border);
    color: var(--rm-text-muted);
    font-size: 0.78rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease;
}

.btn-card-subtle:hover {
    background: color-mix(in srgb, var(--card-color) 10%, transparent);
    color: var(--card-color);
    border-color: color-mix(in srgb, var(--card-color) 25%, transparent);
}

.btn-card-icon {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--rm-radius-sm);
    background: var(--rm-bg-subtle);
    border: 1px solid var(--rm-border);
    color: var(--rm-text-muted);
    cursor: pointer;
    font-size: 0.95rem;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.btn-card-icon:hover {
    background: var(--rm-surface);
    color: var(--rm-text-main);
    border-color: var(--rm-text-muted);
    transform: translateY(-1px);
}

.btn-card-icon.btn-danger:hover {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}

[data-theme="dark"] .btn-card-icon.btn-danger:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.4);
}

/* ========= EMPTY STATE ========= */
.rooms-empty-state {
    text-align: center;
    padding: 4.5rem 2rem;
    background: var(--rm-surface);
    border: 1px dashed var(--rm-border);
    border-radius: var(--rm-radius-lg);
    margin-top: 1rem;
}

.empty-icon-wrap {
    width: 72px;
    height: 72px;
    border-radius: 22px;
    background: color-mix(in srgb, #6366f1 10%, transparent);
    color: #6366f1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    margin: 0 auto 1.25rem auto;
}

.rooms-empty-state h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--rm-text-main);
    margin: 0 0 0.4rem 0;
}

.rooms-empty-state p {
    font-size: 0.88rem;
    color: var(--rm-text-muted);
    margin: 0 0 1.5rem 0;
    max-width: 420px;
    margin-left: auto;
    margin-right: auto;
}

/* ========= MODAL ========= */
.room-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.55);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(8px);
    padding: 1.5rem;
}

.room-modal-overlay.active {
    display: flex;
}

.room-modal {
    background: var(--rm-surface);
    border: 1px solid var(--rm-border);
    border-radius: 22px;
    width: 100%;
    max-width: 500px;
    padding: 2rem;
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.3);
    max-height: 90vh;
    overflow-y: auto;
    animation: rmModalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes rmModalIn {
    from { opacity: 0; transform: scale(0.96) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-header-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.modal-header-wrap h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--rm-text-main);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-header-wrap .close-btn {
    background: var(--rm-bg-subtle);
    border: 1px solid var(--rm-border);
    width: 32px;
    height: 32px;
    border-radius: 8px;
    color: var(--rm-text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.2s ease;
}

.modal-header-wrap .close-btn:hover {
    background: var(--rm-surface);
    color: var(--rm-text-main);
}

.modal-group {
    margin-bottom: 1.2rem;
}

.modal-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--rm-text-main);
    margin-bottom: 0.4rem;
}

.modal-input {
    width: 100%;
    background: var(--rm-bg-subtle);
    border: 1px solid var(--rm-border);
    border-radius: var(--rm-radius-md);
    padding: 0.65rem 0.9rem;
    font-size: 0.88rem;
    color: var(--rm-text-main);
    outline: none;
    transition: all 0.2s ease;
    font-family: inherit;
}

.modal-input:focus {
    border-color: #6366f1;
    background: var(--rm-surface);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

/* Palette swatches */
.color-picker-grid {
    display: flex;
    gap: 0.55rem;
    flex-wrap: wrap;
}

.color-swatch {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    cursor: pointer;
    border: 2px solid transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 0.85rem;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.color-swatch:hover {
    transform: scale(1.1);
}

.color-swatch.selected {
    border-color: var(--rm-text-main);
    box-shadow: 0 0 0 2px var(--rm-surface), 0 0 0 4px var(--rm-text-main);
    transform: scale(1.05);
}

.color-swatch i {
    display: none;
}

.color-swatch.selected i {
    display: block;
}

/* Icon picker */
.icon-picker-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 0.5rem;
}

.icon-swatch {
    height: 40px;
    border-radius: 10px;
    background: var(--rm-bg-subtle);
    border: 1px solid var(--rm-border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    color: var(--rm-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

.icon-swatch:hover {
    color: #6366f1;
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.08);
}

.icon-swatch.selected {
    background: #6366f1;
    color: #ffffff;
    border-color: #6366f1;
    box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.4);
}

.modal-footer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--rm-border);
}

/* ========= RESPONSIVE ========= */
@media (max-width: 992px) {
    .reuniones-bento-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .reuniones-hero-card {
        flex-direction: column;
        align-items: flex-start;
        padding: 1.35rem;
        gap: 1.25rem;
    }
    .hero-actions {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr;
    }
    .btn-hero-primary, .btn-hero-secondary {
        width: 100%;
        justify-content: center;
    }
    .reuniones-segmented-nav {
        width: 100%;
    }
    .reuniones-nav-item {
        flex: 1;
        justify-content: center;
        padding: 0.55rem 0.75rem;
    }
    .rooms-grid {
        grid-template-columns: 1fr;
    }
    .icon-picker-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 480px) {
    .reuniones-bento-stats {
        grid-template-columns: 1fr;
    }
    .room-bento-card {
        padding: 1.25rem;
    }
}
</style>

<div class="reuniones-page-wrapper">

    <!-- Segmented Nav (Historial vs Salas) -->
    <div class="reuniones-segmented-nav">
        <a href="index.php?module=reuniones&action=index" class="reuniones-nav-item">
            <i class="ph ph-video-camera"></i>
            <span>Historial de Reuniones</span>
        </a>
        <a href="index.php?module=reuniones&action=rooms" class="reuniones-nav-item active">
            <i class="ph ph-buildings"></i>
            <span>Salas Permanentes</span>
            <span class="nav-badge live-pulse-badge" id="total-live-count" style="display:none;">0</span>
        </a>
    </div>

    <!-- Hero Launchpad Card -->
    <div class="reuniones-hero-card">
        <div class="hero-left">
            <div class="hero-icon-squircle">
                <i class="ph ph-buildings"></i>
            </div>
            <div class="hero-title-group">
                <h1>
                    Salas de Reunión
                    <span class="hero-tag">En vivo</span>
                </h1>
                <p>Espacios virtuales colaborativos para tu equipo y clientes con presencia en tiempo real.</p>
            </div>
        </div>
        <div class="hero-actions">
            <button onclick="openCreateRoomModal()" class="btn-hero-primary">
                <i class="ph ph-plus-circle"></i>
                <span>Crear Sala</span>
            </button>
            <a href="index.php?module=reuniones&action=index" class="btn-hero-secondary">
                <i class="ph ph-list-bullets"></i>
                <span>Ver Historial</span>
            </a>
        </div>
    </div>

    <!-- Bento Micro Stats Strip -->
    <div class="reuniones-bento-stats">
        <div class="bento-stat-card">
            <div class="stat-icon-capsule total">
                <i class="ph ph-buildings"></i>
            </div>
            <div class="stat-info-wrap">
                <span class="stat-label">Total Salas</span>
                <span class="stat-value"><?php echo $totalRooms; ?></span>
            </div>
        </div>
        <div class="bento-stat-card">
            <div class="stat-icon-capsule live">
                <i class="ph ph-broadcast"></i>
            </div>
            <div class="stat-info-wrap">
                <span class="stat-label">Salas En Vivo</span>
                <span class="stat-value" id="stat-live-count">0</span>
            </div>
        </div>
        <div class="bento-stat-card">
            <div class="stat-icon-capsule recordings">
                <i class="ph ph-video"></i>
            </div>
            <div class="stat-info-wrap">
                <span class="stat-label">Grabaciones</span>
                <span class="stat-value"><?php echo $totalRecordings; ?></span>
            </div>
        </div>
        <div class="bento-stat-card">
            <div class="stat-icon-capsule ready">
                <i class="ph ph-check-circle"></i>
            </div>
            <div class="stat-info-wrap">
                <span class="stat-label">Meet Listos</span>
                <span class="stat-value"><?php echo $readyMeetCount; ?></span>
            </div>
        </div>
    </div>

    <!-- Rooms Bento Grid -->
    <?php if (empty($rooms)): ?>
        <div class="rooms-empty-state">
            <div class="empty-icon-wrap">
                <i class="ph ph-buildings"></i>
            </div>
            <h3>No hay salas creadas aún</h3>
            <p>Crea tu primera sala de reunión para que tu equipo y clientes puedan conectarse en cualquier momento.</p>
            <button onclick="openCreateRoomModal()" class="btn-hero-primary" style="margin:0 auto;">
                <i class="ph ph-plus-circle"></i> Crear Primera Sala
            </button>
        </div>
    <?php else: ?>
        <div class="rooms-grid" id="rooms-grid">
            <?php foreach($rooms as $room): ?>
            <div class="room-bento-card" 
                 id="room-<?php echo $room['id']; ?>"
                 style="--card-color: <?php echo htmlspecialchars($room['color'] ?? '#6366f1'); ?>;"
                 data-room-id="<?php echo $room['id']; ?>">
                
                <div>
                    <!-- Header -->
                    <div class="room-header">
                        <div class="room-squircle-icon" style="background: <?php echo htmlspecialchars($room['color'] ?? '#6366f1'); ?>;">
                            <i class="ph ph-<?php echo htmlspecialchars($room['icon'] ?? 'video-camera'); ?>"></i>
                        </div>
                        <div class="room-header-meta">
                            <h3 class="room-title"><?php echo htmlspecialchars($room['name']); ?></h3>
                            <?php if(!empty($room['description'])): ?>
                                <p class="room-description"><?php echo htmlspecialchars($room['description']); ?></p>
                            <?php else: ?>
                                <p class="room-description" style="font-style:italic; opacity:0.6;">Sin descripción asignada</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Dynamic Status -->
                        <div class="room-status-badge badge-live">
                            <span class="pulsing-dot"></span> EN VIVO
                        </div>
                        <div class="room-status-badge badge-idle">
                            Libre
                        </div>
                    </div>

                    <!-- Real-Time Presence section (updated by Pusher) -->
                    <div class="room-presence-section" id="room-presence-<?php echo $room['id']; ?>">
                        <div class="presence-label">
                            <i class="ph ph-user-circle-dashed"></i>
                            <span>Sala disponible</span>
                        </div>
                    </div>

                    <!-- Meta Chips -->
                    <div class="room-meta-chips">
                        <div class="meta-chip">
                            <i class="ph ph-play-circle"></i>
                            <span><?php echo (int)$room['recording_count']; ?> grabaciones</span>
                        </div>
                        <div class="meta-chip" id="room-members-count-<?php echo $room['id']; ?>">
                            <i class="ph ph-users"></i>
                            <span>0 conectados</span>
                        </div>
                    </div>
                </div>

                <!-- Actions Group -->
                <div class="room-actions-group">
                    <?php if(!empty($room['meet_link'])): ?>
                        <a href="<?php echo htmlspecialchars($room['meet_link']); ?>" target="_blank" class="btn-room-join">
                            <i class="ph ph-video-camera"></i>
                            <span>Unirse a la Sala</span>
                        </a>
                    <?php else: ?>
                        <button class="btn-room-join" onclick="alert('Esta sala no tiene enlace Meet configurado. Edítala para agregar uno.')">
                            <i class="ph ph-video-camera"></i>
                            <span>Unirse a la Sala</span>
                        </button>
                    <?php endif; ?>

                    <div class="room-secondary-actions">
                        <a href="index.php?module=reuniones&action=room_detail&id=<?php echo $room['id']; ?>" class="btn-card-subtle" title="Historial de grabaciones">
                            <i class="ph ph-clock-counter-clockwise"></i>
                            <span>Historial</span>
                        </a>
                        <button type="button" class="btn-card-icon" onclick="copyPublicLink('<?php echo htmlspecialchars($room['slug']); ?>', '<?php echo htmlspecialchars(addslashes($room['name'])); ?>')" title="Compartir enlace público">
                            <i class="ph ph-share-network"></i>
                        </button>
                        <button type="button" class="btn-card-icon" onclick="openEditRoomModal(<?php echo $room['id']; ?>, '<?php echo addslashes(htmlspecialchars($room['name'])); ?>', '<?php echo addslashes(htmlspecialchars($room['description'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($room['meet_link'] ?? '')); ?>', '<?php echo htmlspecialchars($room['color'] ?? '#6366f1'); ?>', '<?php echo htmlspecialchars($room['icon'] ?? 'video-camera'); ?>')" title="Editar sala">
                            <i class="ph ph-pencil-simple"></i>
                        </button>
                        <button type="button" class="btn-card-icon btn-danger" onclick="deleteRoom(<?php echo $room['id']; ?>, '<?php echo addslashes(htmlspecialchars($room['name'])); ?>')" title="Eliminar sala">
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- Create / Edit Room Modal -->
<div class="room-modal-overlay" id="room-modal">
    <div class="room-modal">
        <div class="modal-header-wrap">
            <h3>
                <i class="ph ph-buildings" style="color: #6366f1;"></i>
                <span id="room-modal-title">Crear Sala</span>
            </h3>
            <button type="button" class="close-btn" onclick="closeRoomModal()"><i class="ph ph-x"></i></button>
        </div>

        <form id="room-form">
            <input type="hidden" id="room-edit-id" value="">
            
            <div class="modal-group">
                <label>Nombre de la Sala *</label>
                <input type="text" id="room-name" class="modal-input" placeholder="Ej: Sala de Estrategia & Diseño" required>
            </div>

            <div class="modal-group">
                <label>Descripción (Opcional)</label>
                <input type="text" id="room-desc" class="modal-input" placeholder="Breve propósito de la sala">
            </div>

            <div class="modal-group">
                <label>Enlace Google Meet</label>
                <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.6rem;">
                    <label style="display:flex; align-items:center; gap: 0.35rem; font-weight:normal; font-size:0.82rem; margin:0; cursor:pointer;">
                        <input type="radio" name="meet_option" value="manual" checked onchange="toggleMeetInput(this.value)"> Ingresar manualmente
                    </label>
                    <label style="display:flex; align-items:center; gap: 0.35rem; font-weight:normal; font-size:0.82rem; margin:0; cursor:pointer;">
                        <input type="radio" name="meet_option" value="auto" onchange="toggleMeetInput(this.value)"> Generar automáticamente
                    </label>
                </div>
                <input type="url" id="room-meet-link" class="modal-input" placeholder="https://meet.google.com/xxx-xxxx-xxx">
                <p id="room-meet-hint" style="margin: 0.35rem 0 0 0; font-size: 0.72rem; color: var(--rm-text-muted);">
                    Si lo dejas vacío, los participantes deberán ingresar su propio enlace de reunión.
                </p>
            </div>

            <div class="modal-group">
                <label>Color de Identidad</label>
                <div class="color-picker-grid" id="color-options">
                    <div class="color-swatch selected" data-color="#6366f1" style="background: #6366f1;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-swatch" data-color="#4f46e5" style="background: #4f46e5;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-swatch" data-color="#8b5cf6" style="background: #8b5cf6;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-swatch" data-color="#ec4899" style="background: #ec4899;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-swatch" data-color="#ef4444" style="background: #ef4444;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-swatch" data-color="#f59e0b" style="background: #f59e0b;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-swatch" data-color="#10b981" style="background: #10b981;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-swatch" data-color="#0ea5e9" style="background: #0ea5e9;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                    <div class="color-swatch" data-color="#14b8a6" style="background: #14b8a6;" onclick="selectColor(this)"><i class="ph ph-check"></i></div>
                </div>
            </div>

            <div class="modal-group">
                <label>Ícono de Sala</label>
                <div class="icon-picker-grid" id="icon-options">
                    <div class="icon-swatch selected" data-icon="video-camera" onclick="selectIcon(this)"><i class="ph ph-video-camera"></i></div>
                    <div class="icon-swatch" data-icon="strategy" onclick="selectIcon(this)"><i class="ph ph-strategy"></i></div>
                    <div class="icon-swatch" data-icon="paint-brush" onclick="selectIcon(this)"><i class="ph ph-paint-brush"></i></div>
                    <div class="icon-swatch" data-icon="coffee" onclick="selectIcon(this)"><i class="ph ph-coffee"></i></div>
                    <div class="icon-swatch" data-icon="rocket-launch" onclick="selectIcon(this)"><i class="ph ph-rocket-launch"></i></div>
                    <div class="icon-swatch" data-icon="megaphone" onclick="selectIcon(this)"><i class="ph ph-megaphone"></i></div>
                    <div class="icon-swatch" data-icon="code" onclick="selectIcon(this)"><i class="ph ph-code"></i></div>
                    <div class="icon-swatch" data-icon="presentation-chart" onclick="selectIcon(this)"><i class="ph ph-presentation-chart"></i></div>
                    <div class="icon-swatch" data-icon="headset" onclick="selectIcon(this)"><i class="ph ph-headset"></i></div>
                    <div class="icon-swatch" data-icon="chalkboard-teacher" onclick="selectIcon(this)"><i class="ph ph-chalkboard-teacher"></i></div>
                    <div class="icon-swatch" data-icon="brain" onclick="selectIcon(this)"><i class="ph ph-brain"></i></div>
                    <div class="icon-swatch" data-icon="lightbulb" onclick="selectIcon(this)"><i class="ph ph-lightbulb"></i></div>
                </div>
            </div>

            <div class="modal-footer-actions">
                <button type="button" onclick="closeRoomModal()" class="btn-hero-secondary">Cancelar</button>
                <button type="submit" id="room-submit-btn" class="btn-hero-primary">Crear Sala</button>
            </div>
        </form>
    </div>
</div>

<script>
// ========= PUSHER REAL-TIME PRESENCE =========
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Pusher === 'undefined') return;

    const pusher = new Pusher('b31f38612d61b0285c78', {
        cluster: 'us2',
        authEndpoint: 'ajax_pusher_auth.php'
    });

    let totalLive = 0;

    // Subscribe to each room's presence channel
    document.querySelectorAll('.room-bento-card').forEach(card => {
        const roomId = card.dataset.roomId;
        const channel = pusher.subscribe(`presence-room-${roomId}`);

        channel.bind('pusher:subscription_succeeded', (members) => {
            updateRoomPresence(roomId, members);
        });

        channel.bind('pusher:member_added', (member) => {
            updateRoomPresence(roomId, channel.members);
            if (window.showToast) window.showToast(`${member.info.name} entró a la sala`, 'info');
        });

        channel.bind('pusher:member_removed', (member) => {
            updateRoomPresence(roomId, channel.members);
        });
    });

    function updateRoomPresence(roomId, members) {
        const card = document.getElementById(`room-${roomId}`);
        const presenceEl = document.getElementById(`room-presence-${roomId}`);
        const countEl = document.getElementById(`room-members-count-${roomId}`);
        if (!card || !presenceEl) return;

        let count = 0;
        let avatarsHtml = '<div class="presence-avatars-wrap">';
        
        members.each((member) => {
            count++;
            if (member.info.avatar) {
                avatarsHtml += `<div class="presence-avatar-circle" title="${member.info.name}"><img src="${member.info.avatar}" alt="${member.info.name}"></div>`;
            } else {
                avatarsHtml += `<div class="presence-avatar-circle" title="${member.info.name}" style="background: ${card.style.getPropertyValue('--card-color') || '#6366f1'}">${(member.info.name || 'U').charAt(0).toUpperCase()}</div>`;
            }
        });
        avatarsHtml += '</div>';

        if (count > 0) {
            card.classList.add('is-live');
            presenceEl.innerHTML = avatarsHtml + `<span class="presence-label" style="color:#ef4444; font-weight:600;"><span class="pulsing-dot" style="background:#ef4444;"></span> ${count} en llamada</span>`;
        } else {
            card.classList.remove('is-live');
            presenceEl.innerHTML = '<div class="presence-label"><i class="ph ph-user-circle-dashed"></i> <span>Sala disponible</span></div>';
        }

        if (countEl) {
            countEl.innerHTML = `<i class="ph ph-users"></i><span>${count} conectados</span>`;
        }

        // Update live stats & nav badge
        totalLive = document.querySelectorAll('.room-bento-card.is-live').length;
        const statLiveCount = document.getElementById('stat-live-count');
        if (statLiveCount) statLiveCount.textContent = totalLive;

        const badge = document.getElementById('total-live-count');
        if (badge) {
            if (totalLive > 0) {
                badge.textContent = totalLive;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        }
    }
});

// ========= MODAL FUNCTIONS =========
function openCreateRoomModal() {
    document.getElementById('room-edit-id').value = '';
    document.getElementById('room-name').value = '';
    document.getElementById('room-desc').value = '';
    document.getElementById('room-meet-link').value = '';
    document.querySelector('input[name="meet_option"][value="manual"]').checked = true;
    toggleMeetInput('manual');
    document.getElementById('room-modal-title').textContent = 'Crear Sala';
    document.getElementById('room-submit-btn').textContent = 'Crear Sala';
    
    // Reset selection to default
    document.querySelectorAll('.color-swatch').forEach((el, i) => {
        el.classList.toggle('selected', i === 0);
    });
    document.querySelectorAll('.icon-swatch').forEach((el, i) => {
        el.classList.toggle('selected', i === 0);
    });
    
    document.getElementById('room-modal').classList.add('active');
}

function openEditRoomModal(id, name, desc, meetLink, color, icon) {
    document.getElementById('room-edit-id').value = id;
    document.getElementById('room-name').value = name;
    document.getElementById('room-desc').value = desc || '';
    document.getElementById('room-meet-link').value = meetLink || '';
    document.querySelector('input[name="meet_option"][value="manual"]').checked = true;
    toggleMeetInput('manual');
    document.getElementById('room-modal-title').textContent = 'Editar Sala';
    document.getElementById('room-submit-btn').textContent = 'Guardar Cambios';
    
    // Select matching color
    document.querySelectorAll('.color-swatch').forEach(el => {
        el.classList.toggle('selected', el.dataset.color.toLowerCase() === (color || '').toLowerCase());
    });
    
    // Select matching icon
    document.querySelectorAll('.icon-swatch').forEach(el => {
        el.classList.toggle('selected', el.dataset.icon === icon);
    });
    
    document.getElementById('room-modal').classList.add('active');
}

function closeRoomModal() {
    document.getElementById('room-modal').classList.remove('active');
}

function selectColor(el) {
    document.querySelectorAll('.color-swatch').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
}

function selectIcon(el) {
    document.querySelectorAll('.icon-swatch').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
}

function toggleMeetInput(value) {
    const input = document.getElementById('room-meet-link');
    const hint = document.getElementById('room-meet-hint');
    if (value === 'auto') {
        input.style.display = 'none';
        hint.innerHTML = '<i class="ph ph-magic-wand" style="color:#6366f1;"></i> Se generará un nuevo enlace de Google Meet automáticamente al guardar.';
    } else {
        input.style.display = 'block';
        hint.textContent = 'Si lo dejas vacío, los participantes deberán ingresar su propio enlace de reunión.';
    }
}

// ========= FORM SUBMIT =========
document.getElementById('room-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('room-submit-btn');
    const editId = document.getElementById('room-edit-id').value;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';

    const selectedColor = document.querySelector('.color-swatch.selected')?.dataset.color || '#6366f1';
    const selectedIcon = document.querySelector('.icon-swatch.selected')?.dataset.icon || 'video-camera';

    const data = new FormData();
    data.append('action', editId ? 'update_room' : 'create_room');
    if (editId) data.append('id', editId);
    data.append('name', document.getElementById('room-name').value);
    data.append('description', document.getElementById('room-desc').value);
    const meetOption = document.querySelector('input[name="meet_option"]:checked').value;
    data.append('meet_link', meetOption === 'auto' ? '' : document.getElementById('room-meet-link').value);
    data.append('auto_meet', meetOption === 'auto' ? '1' : '0');
    data.append('color', selectedColor);
    data.append('icon', selectedIcon);

    try {
        const res = await fetch('ajax/ajax_rooms.php', { method: 'POST', body: data });
        const result = await res.json();
        
        if (result.success) {
            if (window.showToast) window.showToast(editId ? 'Sala actualizada' : 'Sala creada exitosamente', 'success');
            closeRoomModal();
            setTimeout(() => location.reload(), 600);
        } else {
            if (window.showToast) window.showToast('Error: ' + (result.error || 'Desconocido'), 'error');
        }
    } catch (err) {
        if (window.showToast) window.showToast('Error de conexión con el servidor', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = editId ? 'Guardar Cambios' : 'Crear Sala';
    }
});

// ========= UTILITY =========
function copyPublicLink(slug, name) {
    const url = `${window.location.origin}${window.location.pathname.replace('index.php', '')}public_room.php?slug=${slug}`;
    navigator.clipboard.writeText(url).then(() => {
        if (window.showToast) window.showToast(`Enlace público de "${name}" copiado al portapapeles`, 'success');
    }).catch(() => {
        prompt('Copia este enlace:', url);
    });
}

function deleteRoom(id, name) {
    if (!confirm(`¿Estás seguro que deseas eliminar la sala "${name}"? Las grabaciones asociadas se conservarán.`)) return;
    
    const data = new FormData();
    data.append('action', 'delete_room');
    data.append('id', id);

    fetch('ajax/ajax_rooms.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.showToast) window.showToast('Sala eliminada correctamente', 'success');
                setTimeout(() => location.reload(), 600);
            } else {
                if (window.showToast) window.showToast('Error: ' + res.error, 'error');
            }
        })
        .catch(() => {
            if (window.showToast) window.showToast('Error de red al intentar eliminar la sala', 'error');
        });
}

// Modal closing helpers
document.getElementById('room-modal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('room-modal')) closeRoomModal();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeRoomModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>
