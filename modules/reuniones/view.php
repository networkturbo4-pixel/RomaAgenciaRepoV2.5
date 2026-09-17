<?php
// modules/reuniones/view.php
// Detalle de Reunión — Rediseño moderno estilo Bento / macOS Sonoma con Gemini AI y Video Cinema
require_once 'includes/header.php';

global $db;
$id = $_GET['id'] ?? 0;

$sql = "SELECT r.*, b.name as brand_name, b.logo as brand_logo, b.whatsapp_group, c.whatsapp as client_whatsapp 
        FROM reuniones r 
        LEFT JOIN client_brands b ON r.brand_id = b.id 
        LEFT JOIN clients c ON b.client_id = c.id 
        WHERE r.id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$id]);
$reunion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reunion) {
    echo "<div style='max-width:600px; margin:4rem auto; text-align:center; padding:3rem; background:var(--bg-surface); border-radius:20px; border:1px solid var(--border-color);'>";
    echo "<i class='ph ph-warning-circle' style='font-size:3rem; color:#ef4444; margin-bottom:1rem; display:inline-block;'></i>";
    echo "<h2 style='margin:0 0 0.5rem 0;'>Reunión no encontrada</h2>";
    echo "<p style='color:var(--text-muted); margin-bottom:1.5rem;'>La reunión que buscas no existe o fue eliminada.</p>";
    echo "<a href='index.php?module=reuniones' class='btn btn-primary' style='border-radius:12px;'>Volver a Reuniones</a>";
    echo "</div>";
    require_once 'includes/footer.php';
    exit();
}

// Format date & relative time
$timestamp = strtotime($reunion['fecha_hora']);
$formattedDate = date('d M, Y', $timestamp);
$formattedTime = date('h:i A', $timestamp);

$timeDiff = $timestamp - time();
if ($timeDiff > 0 && $timeDiff < 86400 * 2) {
    $hours = round($timeDiff / 3600);
    $relativeTime = $hours <= 1 ? "Comienza pronto" : "En {$hours} horas";
    $timePillClass = "pill-upcoming";
} elseif ($timeDiff <= 0) {
    $daysAgo = round(abs($timeDiff) / 86400);
    $relativeTime = $daysAgo == 0 ? "Hoy" : "Hace {$daysAgo} día" . ($daysAgo > 1 ? "s" : "");
    $timePillClass = "pill-past";
} else {
    $days = round($timeDiff / 86400);
    $relativeTime = "En {$days} días";
    $timePillClass = "pill-upcoming";
}
?>

<!-- Phosphor Icons -->
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">

<style>
/* ==========================================================================
   REUNIÓN DETALLE — MODERN APP & BENTO DESIGN SYSTEM
   ========================================================================== */

:root {
    --rv-primary: #ea4335;
    --rv-primary-glow: rgba(234, 67, 53, 0.25);
    --rv-surface: var(--bg-surface, #ffffff);
    --rv-border: var(--border-color, rgba(140, 149, 159, 0.2));
    --rv-text-main: var(--text-main, #1e293b);
    --rv-text-muted: var(--text-muted, #64748b);
    --rv-radius-lg: 18px;
    --rv-radius-md: 12px;
    --rv-radius-sm: 8px;
    --rv-bg-subtle: color-mix(in srgb, var(--border-color, #cbd5e1) 22%, transparent);
}

[data-theme="dark"] {
    --rv-surface: var(--bg-surface, #1e222d);
    --rv-border: var(--border-color, rgba(255, 255, 255, 0.08));
    --rv-text-main: var(--text-main, #f1f5f9);
    --rv-text-muted: var(--text-muted, #94a3b8);
    --rv-bg-subtle: rgba(255, 255, 255, 0.04);
}

.rv-view-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0.5rem 0 3.5rem 0;
    animation: rvFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes rvFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes rvPulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.3); opacity: 0.7; }
}

@keyframes rvRecBlink {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}

/* ========= BREADCRUMB / NAV ========= */
.rv-top-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1.25rem;
    font-size: 0.85rem;
    color: var(--rv-text-muted);
}

.rv-breadcrumb-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--rv-text-muted);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.15s ease;
}

.rv-breadcrumb-link:hover {
    color: var(--rv-primary);
}

/* ========= HERO HEADER ========= */
.rv-hero-header {
    background: linear-gradient(135deg, 
        color-mix(in srgb, var(--rv-surface) 96%, #ea4335 4%) 0%,
        color-mix(in srgb, var(--rv-surface) 92%, #6366f1 8%) 100%);
    border: 1px solid var(--rv-border);
    border-radius: var(--rv-radius-lg);
    padding: 1.75rem 2rem;
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 8px 24px -8px rgba(0, 0, 0, 0.04);
}

.rv-hero-left {
    display: flex;
    align-items: flex-start;
    gap: 1.25rem;
    min-width: 0;
}

.rv-back-btn {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--rv-surface);
    border: 1px solid var(--rv-border);
    color: var(--rv-text-main);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    flex-shrink: 0;
    margin-top: 2px;
}

.rv-back-btn:hover {
    transform: translateX(-3px);
    background: var(--rv-bg-subtle);
    border-color: var(--rv-text-muted);
}

.rv-header-meta {
    min-width: 0;
}

.rv-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--rv-text-main);
    margin: 0 0 0.5rem 0;
    line-height: 1.25;
    letter-spacing: -0.02em;
}

.rv-pill-strip {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.rv-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.75rem;
    border-radius: 8px;
    background: var(--rv-surface);
    border: 1px solid var(--rv-border);
    font-size: 0.8rem;
    color: var(--rv-text-muted);
    font-weight: 500;
}

.rv-chip i {
    font-size: 0.95rem;
}

.rv-chip.chip-brand {
    color: var(--rv-text-main);
    font-weight: 600;
}

.rv-brand-thumb {
    width: 20px;
    height: 20px;
    border-radius: 5px;
    object-fit: cover;
}

.rv-brand-letter {
    width: 20px;
    height: 20px;
    border-radius: 5px;
    background: linear-gradient(135deg, #ea4335, #f59e0b);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
}

.rv-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.75rem;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.01em;
}

.rv-status-badge.Programada {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.25);
}

.rv-status-badge.Completada {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.25);
}

.rv-status-badge.Cancelada {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

.rv-status-badge .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    animation: rvPulse 1.5s infinite;
}

.rv-hero-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.btn-rv-action {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.6rem 1.1rem;
    border-radius: var(--rv-radius-md);
    background: var(--rv-surface);
    border: 1px solid var(--rv-border);
    color: var(--rv-text-main);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-rv-action:hover {
    background: var(--rv-bg-subtle);
    transform: translateY(-1px);
}

.btn-rv-action.btn-sync:hover {
    color: #8b5cf6;
    border-color: #8b5cf6;
    background: rgba(139, 92, 246, 0.08);
}

.btn-rv-action.btn-danger:hover {
    color: #ef4444;
    border-color: #fca5a5;
    background: #fef2f2;
}

[data-theme="dark"] .btn-rv-action.btn-danger:hover {
    background: rgba(239, 68, 68, 0.15);
}

/* ========= 2-COLUMN BENTO LAYOUT ========= */
.rv-bento-grid {
    display: grid;
    grid-template-columns: 1fr 370px;
    gap: 1.5rem;
    align-items: start;
}

/* Left Column */
.rv-left-col {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Bento Card Standard */
.rv-card {
    background: var(--rv-surface);
    border: 1px solid var(--rv-border);
    border-radius: var(--rv-radius-lg);
    padding: 1.75rem;
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.03);
    position: relative;
}

.rv-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid var(--rv-border);
}

.rv-card-title-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.rv-card-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.rv-card-icon-wrap.ai {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(236, 72, 153, 0.15));
    color: #a855f7;
}

.rv-card-icon-wrap.steps {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}

.rv-card-icon-wrap.info {
    background: rgba(59, 130, 246, 0.12);
    color: #3b82f6;
}

.rv-card-icon-wrap.cinema {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}

.rv-card-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--rv-text-main);
    margin: 0;
}

/* Gemini Notes Content */
.rv-notes-body {
    font-size: 0.93rem;
    line-height: 1.8;
    color: var(--rv-text-main);
}

.rv-notes-body p {
    margin-bottom: 1rem;
}

.rv-notes-frame-wrap {
    border-radius: var(--rv-radius-md);
    overflow: hidden;
    border: 1px solid var(--rv-border);
    background: var(--rv-bg-subtle);
    height: 540px;
}

.rv-notes-frame-wrap iframe {
    width: 100%;
    height: 100%;
    border: none;
}

.rv-empty-notes {
    text-align: center;
    padding: 3.5rem 1.5rem;
    border-radius: var(--rv-radius-md);
    background: var(--rv-bg-subtle);
    border: 1px dashed var(--rv-border);
}

.empty-ai-orb {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(236, 72, 153, 0.2));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: #a855f7;
    margin: 0 auto 1rem auto;
}

.rv-empty-notes h4 {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--rv-text-main);
    margin: 0 0 0.35rem 0;
}

.rv-empty-notes p {
    font-size: 0.85rem;
    color: var(--rv-text-muted);
    max-width: 440px;
    margin: 0 auto 1.25rem auto;
}

/* Próximos Pasos List */
.rv-steps-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.rv-step-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: var(--rv-radius-md);
    background: var(--rv-bg-subtle);
    border: 1px solid var(--rv-border);
    font-size: 0.9rem;
    color: var(--rv-text-main);
    line-height: 1.5;
    transition: all 0.2s ease;
}

.rv-step-item:hover {
    border-color: #10b981;
    background: color-mix(in srgb, #10b981 6%, var(--rv-surface));
    transform: translateX(3px);
}

.rv-step-check {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
    margin-top: 1px;
}

/* Right Column (Sidebar) */
.rv-right-col {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Meet Hero Card */
.rv-meet-card {
    background: linear-gradient(135deg, 
        color-mix(in srgb, var(--rv-surface) 95%, #ea4335 5%) 0%,
        color-mix(in srgb, var(--rv-surface) 90%, #f59e0b 10%) 100%);
    border: 1px solid var(--rv-border);
    border-radius: var(--rv-radius-lg);
    padding: 1.5rem;
    box-shadow: 0 6px 18px -4px rgba(0, 0, 0, 0.04);
}

.btn-meet-join {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    width: 100%;
    padding: 0.85rem 1.25rem;
    border-radius: var(--rv-radius-md);
    background: linear-gradient(135deg, #ea4335 0%, #dc2626 100%);
    color: #ffffff !important;
    font-size: 0.95rem;
    font-weight: 700;
    text-decoration: none;
    border: none;
    cursor: pointer;
    box-shadow: 0 6px 20px -3px rgba(234, 67, 53, 0.4);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    margin-bottom: 0.75rem;
}

.btn-meet-join:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px 0 rgba(234, 67, 53, 0.5);
    color: #ffffff;
}

.meet-sub-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.btn-sub-meet {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.55rem 0.6rem;
    border-radius: var(--rv-radius-sm);
    background: var(--rv-surface);
    border: 1px solid var(--rv-border);
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--rv-text-main);
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease;
}

.btn-sub-meet:hover {
    background: var(--rv-bg-subtle);
    border-color: var(--rv-text-muted);
}

.btn-sub-meet.whatsapp {
    color: #10b981;
}

.btn-sub-meet.whatsapp:hover {
    background: rgba(16, 185, 129, 0.1);
    border-color: #10b981;
}

/* Sidebar Info Rows */
.rv-info-rows {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.rv-info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 0.85rem;
    border-radius: var(--rv-radius-md);
    background: var(--rv-bg-subtle);
    border: 1px solid var(--rv-border);
    font-size: 0.83rem;
}

.rv-info-key {
    color: var(--rv-text-muted);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.rv-info-key i {
    font-size: 1rem;
}

.rv-info-val {
    color: var(--rv-text-main);
    font-weight: 600;
    text-align: right;
}

/* Cinema Recording Card */
.rv-cinema-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: var(--rv-radius-lg);
    padding: 1.5rem;
    color: #ffffff;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.5);
}

.cinema-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.cinema-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #f8fafc;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.rec-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.4);
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    letter-spacing: 0.04em;
}

.rec-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #ef4444;
    animation: rvRecBlink 1.5s infinite;
}

.cinema-thumb {
    position: relative;
    border-radius: var(--rv-radius-md);
    overflow: hidden;
    background: #000000;
    aspect-ratio: 16/9;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    margin-bottom: 1rem;
}

.cinema-thumb:hover {
    transform: scale(1.02);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.6);
}

.cinema-thumb iframe {
    width: 100%;
    height: 100%;
    border: none;
    pointer-events: none;
}

.cinema-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease;
}

.cinema-thumb:hover .cinema-overlay {
    background: rgba(0, 0, 0, 0.15);
}

.play-orb {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.95);
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    transition: transform 0.2s ease;
}

.cinema-thumb:hover .play-orb {
    transform: scale(1.1);
}

.cinema-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.btn-cinema {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.6rem 0.8rem;
    border-radius: var(--rv-radius-sm);
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.btn-cinema.play {
    background: #6366f1;
    color: #ffffff;
    border-color: #6366f1;
}

.btn-cinema.play:hover {
    background: #4f46e5;
}

.btn-cinema.download {
    background: rgba(255, 255, 255, 0.08);
    color: #e2e8f0;
}

.btn-cinema.download:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* ========= VIDEO THEATER MODAL ========= */
.video-theater {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.92);
    z-index: 10001;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    backdrop-filter: blur(12px);
    padding: 1.5rem;
}

.video-theater.active {
    display: flex;
}

.video-theater-header {
    width: 100%; max-width: 1000px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 0.75rem;
    margin-bottom: 0.5rem;
}

.video-theater-header h3 {
    color: #ffffff;
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.video-theater-close {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
    width: 36px; height: 36px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.video-theater-close:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: rotate(90deg);
}

.video-theater-body {
    width: 100%; max-width: 1000px;
    aspect-ratio: 16/9;
    background: #000000;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
}

.video-theater-body iframe {
    width: 100%; height: 100%; border: 0;
}

.video-theater-footer {
    width: 100%; max-width: 1000px;
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    padding-top: 1rem;
}

/* ========= EDIT MODAL ========= */
.rv-modal-overlay {
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

.rv-modal-overlay.active {
    display: flex;
}

.rv-modal-content {
    background: var(--rv-surface);
    border: 1px solid var(--rv-border);
    border-radius: 22px;
    width: 100%;
    max-width: 480px;
    padding: 2rem;
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.3);
    max-height: 90vh;
    overflow-y: auto;
    animation: rvModalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes rvModalIn {
    from { opacity: 0; transform: scale(0.96) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.modal-head h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--rv-text-main);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-field {
    margin-bottom: 1.15rem;
}

.modal-field label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--rv-text-main);
    margin-bottom: 0.4rem;
}

.modal-input-ctl {
    width: 100%;
    background: var(--rv-bg-subtle);
    border: 1px solid var(--rv-border);
    border-radius: var(--rv-radius-md);
    padding: 0.65rem 0.9rem;
    font-size: 0.88rem;
    color: var(--rv-text-main);
    outline: none;
    transition: all 0.2s ease;
    font-family: inherit;
}

.modal-input-ctl:focus {
    border-color: #ea4335;
    background: var(--rv-surface);
    box-shadow: 0 0 0 3px rgba(234, 67, 53, 0.15);
}

.modal-foot {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--rv-border);
}

/* ========= RESPONSIVE ========= */
@media (max-width: 992px) {
    .rv-bento-grid {
        grid-template-columns: 1fr;
    }
    .rv-hero-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 1.5rem;
        gap: 1.25rem;
    }
    .rv-hero-actions {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
    .btn-rv-action {
        justify-content: center;
    }
}

@media (max-width: 640px) {
    .rv-hero-actions {
        grid-template-columns: 1fr;
    }
    .rv-title {
        font-size: 1.25rem;
    }
    .rv-card {
        padding: 1.25rem;
    }
}
</style>

<div class="rv-view-wrapper">

    <!-- Top Breadcrumbs -->
    <div class="rv-top-breadcrumbs">
        <a href="index.php?module=reuniones" class="rv-breadcrumb-link">
            <i class="ph ph-video-camera"></i> Reuniones
        </a>
        <span>/</span>
        <span>Detalle de Reunión</span>
    </div>

    <!-- Hero Launchpad Header -->
    <div class="rv-hero-header">
        <div class="rv-hero-left">
            <a href="index.php?module=reuniones" class="rv-back-btn" title="Volver al Historial">
                <i class="ph ph-arrow-left"></i>
            </a>
            <div class="rv-header-meta">
                <h1 class="rv-title"><?php echo htmlspecialchars($reunion['motivo']); ?></h1>
                
                <div class="rv-pill-strip">
                    <!-- Brand Pill -->
                    <div class="rv-chip chip-brand">
                        <?php if(!empty($reunion['brand_logo']) && file_exists($reunion['brand_logo'])): ?>
                            <img src="<?php echo htmlspecialchars($reunion['brand_logo']); ?>" class="rv-brand-thumb" alt="Logo">
                        <?php else: ?>
                            <div class="rv-brand-letter">
                                <?php echo strtoupper(substr($reunion['brand_name'] ?? 'M', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($reunion['brand_name'] ?? 'General'); ?></span>
                    </div>

                    <!-- Date Pill -->
                    <div class="rv-chip">
                        <i class="ph ph-calendar-blank"></i>
                        <span><?php echo $formattedDate; ?> &bull; <?php echo $formattedTime; ?></span>
                    </div>

                    <!-- Relative Time Pill -->
                    <div class="rv-chip">
                        <i class="ph ph-clock"></i>
                        <span><?php echo $relativeTime; ?></span>
                    </div>

                    <!-- Status Badge -->
                    <div class="rv-status-badge <?php echo htmlspecialchars($reunion['estado']); ?>">
                        <span class="dot"></span>
                        <span><?php echo htmlspecialchars($reunion['estado']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rv-hero-actions">
            <button onclick="syncGeminiNotesSingle()" id="btn-sync-single" class="btn-rv-action btn-sync" title="Sincronizar notas con Gemini">
                <i class="ph ph-sparkle"></i>
                <span id="sync-text">Sincronizar Gemini</span>
            </button>
            <button onclick="openEditMeetModal()" class="btn-rv-action" title="Editar reunión">
                <i class="ph ph-pencil-simple"></i>
                <span>Editar</span>
            </button>
            <button onclick="deleteMeet(<?php echo $reunion['id']; ?>)" class="btn-rv-action btn-danger" title="Eliminar reunión">
                <i class="ph ph-trash"></i>
                <span>Eliminar</span>
            </button>
        </div>
    </div>

    <!-- Bento 2-Column Grid -->
    <div class="rv-bento-grid">
        
        <!-- Left Column: Gemini AI Summary & Próximos Pasos -->
        <div class="rv-left-col">
            
            <!-- Gemini Notes Card -->
            <div class="rv-card">
                <div class="rv-card-header">
                    <div class="rv-card-title-group">
                        <div class="rv-card-icon-wrap ai">
                            <i class="ph ph-sparkle"></i>
                        </div>
                        <div>
                            <h2 class="rv-card-title">Resumen Inteligente Gemini</h2>
                            <span style="font-size:0.75rem; color:var(--rv-text-muted);">Extracción automática de notas y acuerdos clave</span>
                        </div>
                    </div>

                    <?php if(!empty($reunion['resumen'])): ?>
                        <button onclick="copySummaryText()" class="btn-rv-action" style="padding:0.4rem 0.8rem; font-size:0.78rem;">
                            <i class="ph ph-copy"></i> Copiar Notas
                        </button>
                    <?php endif; ?>
                </div>

                <?php if(!empty($reunion['resumen'])): ?>
                    <div class="rv-notes-body" id="gemini-summary-content">
                        <?php echo nl2br(htmlspecialchars($reunion['resumen'])); ?>
                    </div>
                <?php elseif(!empty($reunion['notes_link'])): ?>
                    <div style="margin-bottom:1rem; font-size:0.85rem; color:var(--rv-text-muted); display:flex; align-items:center; gap:0.5rem;">
                        <i class="ph ph-info" style="color:#3b82f6; font-size:1.1rem;"></i>
                        Documento oficial de transcripción y notas generado por Google Meet:
                    </div>
                    <div class="rv-notes-frame-wrap">
                        <iframe src="<?php echo htmlspecialchars(preg_replace('/\/edit.*?$/', '/preview', $reunion['notes_link'])); ?>" allowfullscreen></iframe>
                    </div>
                <?php else: ?>
                    <div class="rv-empty-notes">
                        <div class="empty-ai-orb">
                            <i class="ph ph-hourglass-high"></i>
                        </div>
                        <h4>Notas aún no procesadas</h4>
                        <p>Google Meet enviará automáticamente las notas y transcripción al correo una vez finalizada la reunión.</p>
                        <button onclick="syncGeminiNotesSingle()" class="btn-rv-action btn-sync">
                            <i class="ph ph-arrows-clockwise"></i> Sincronizar Ahora
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Próximos Pasos Card (si existen) -->
            <?php if(!empty($reunion['proximos_pasos'])): ?>
            <div class="rv-card">
                <div class="rv-card-header">
                    <div class="rv-card-title-group">
                        <div class="rv-card-icon-wrap steps">
                            <i class="ph ph-check-square-offset"></i>
                        </div>
                        <div>
                            <h2 class="rv-card-title">Próximos Pasos Sugeridos</h2>
                            <span style="font-size:0.75rem; color:var(--rv-text-muted);">Compromisos y tareas asignadas</span>
                        </div>
                    </div>
                </div>

                <ul class="rv-steps-list">
                    <?php 
                    $pasos = explode("\n", $reunion['proximos_pasos']);
                    foreach($pasos as $paso):
                        $p = trim($paso);
                        if(empty($p)) continue;
                    ?>
                    <li class="rv-step-item">
                        <div class="rv-step-check">
                            <i class="ph ph-check-bold"></i>
                        </div>
                        <div><?php echo htmlspecialchars($p); ?></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

        </div>

        <!-- Right Column: Meet Launch, Details Bento & Cinema Recording -->
        <div class="rv-right-col">

            <!-- Meet Action Bento Card -->
            <div class="rv-meet-card">
                <?php if(!empty($reunion['meet_link'])): ?>
                    <a href="<?php echo htmlspecialchars($reunion['meet_link']); ?>" target="_blank" class="btn-meet-join">
                        <i class="ph ph-video-camera"></i>
                        <span>Entrar a la Videollamada</span>
                    </a>
                <?php else: ?>
                    <button class="btn-meet-join" onclick="alert('Esta reunión aún no tiene un enlace de Meet asignado.')">
                        <i class="ph ph-video-camera"></i>
                        <span>Entrar a la Videollamada</span>
                    </button>
                <?php endif; ?>

                <div class="meet-sub-actions">
                    <button type="button" class="btn-sub-meet" onclick="copyMeetLink('<?php echo htmlspecialchars($reunion['meet_link'] ?? ''); ?>')">
                        <i class="ph ph-copy"></i> Copiar Enlace
                    </button>

                    <?php 
                        $whatsappTarget = !empty($reunion['whatsapp_group']) ? $reunion['whatsapp_group'] : (!empty($reunion['client_whatsapp']) ? $reunion['client_whatsapp'] : '');
                        if(!empty($whatsappTarget)):
                    ?>
                        <button type="button" class="btn-sub-meet whatsapp" onclick="sendWhatsAppInvitation('<?php echo htmlspecialchars($whatsappTarget); ?>', '<?php echo addslashes(htmlspecialchars($reunion['motivo'])); ?>', '<?php echo date('d/m/Y h:i A', strtotime($reunion['fecha_hora'])); ?>', '<?php echo htmlspecialchars($reunion['meet_link'] ?? ''); ?>')">
                            <i class="ph ph-whatsapp-logo"></i> WhatsApp
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn-sub-meet" onclick="copyInvitation('<?php echo addslashes(htmlspecialchars($reunion['motivo'])); ?>', '<?php echo date('d/m/Y h:i A', strtotime($reunion['fecha_hora'])); ?>', '<?php echo htmlspecialchars($reunion['meet_link'] ?? ''); ?>')">
                            <i class="ph ph-share-network"></i> Compartir
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Details Bento Card -->
            <div class="rv-card">
                <div class="rv-card-header">
                    <div class="rv-card-title-group">
                        <div class="rv-card-icon-wrap info">
                            <i class="ph ph-info"></i>
                        </div>
                        <h2 class="rv-card-title">Información</h2>
                    </div>
                </div>

                <div class="rv-info-rows">
                    <div class="rv-info-row">
                        <span class="rv-info-key"><i class="ph ph-flag"></i> Estado</span>
                        <span class="rv-info-val"><?php echo htmlspecialchars($reunion['estado']); ?></span>
                    </div>

                    <div class="rv-info-row">
                        <span class="rv-info-key"><i class="ph ph-buildings"></i> Marca</span>
                        <span class="rv-info-val"><?php echo htmlspecialchars($reunion['brand_name'] ?? 'Sin asignar'); ?></span>
                    </div>

                    <div class="rv-info-row">
                        <span class="rv-info-key"><i class="ph ph-calendar"></i> Fecha</span>
                        <span class="rv-info-val"><?php echo $formattedDate; ?></span>
                    </div>

                    <div class="rv-info-row">
                        <span class="rv-info-key"><i class="ph ph-clock"></i> Hora</span>
                        <span class="rv-info-val"><?php echo $formattedTime; ?></span>
                    </div>

                    <?php if(!empty($reunion['guests'])): ?>
                    <div class="rv-info-row" style="flex-direction:column; align-items:flex-start; gap:0.35rem;">
                        <span class="rv-info-key"><i class="ph ph-users"></i> Invitados extras</span>
                        <span class="rv-info-val" style="font-size:0.78rem; text-align:left; word-break:break-all; font-weight:normal;">
                            <?php echo htmlspecialchars($reunion['guests']); ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($reunion['tags'])): ?>
                    <div class="rv-info-row" style="flex-direction:column; align-items:flex-start; gap:0.35rem;">
                        <span class="rv-info-key"><i class="ph ph-tag"></i> Etiquetas</span>
                        <div style="display:flex; gap:0.3rem; flex-wrap:wrap; margin-top:2px;">
                            <?php 
                            $tags = explode(',', $reunion['tags']);
                            foreach($tags as $t): 
                                $t = trim($t);
                                if(empty($t)) continue;
                            ?>
                                <span style="background:var(--rv-surface); border:1px solid var(--rv-border); font-size:0.72rem; padding:2px 7px; border-radius:6px; font-weight:600;">
                                    #<?php echo htmlspecialchars($t); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cinema Recording Card (if exists) -->
            <?php if(!empty($reunion['recording_link'])): 
                $embed_link = str_replace('/view', '/preview', $reunion['recording_link']);
            ?>
            <div class="rv-cinema-card">
                <div class="cinema-header">
                    <h3 class="cinema-title">
                        <i class="ph ph-film-slate" style="color:#818cf8;"></i>
                        Grabación en Drive
                    </h3>
                    <div class="rec-pill">
                        <span class="rec-dot"></span> REC
                    </div>
                </div>

                <div class="cinema-thumb" onclick="openVideoTheater()">
                    <iframe src="<?php echo htmlspecialchars($embed_link); ?>" allow="autoplay"></iframe>
                    <div class="cinema-overlay">
                        <div class="play-orb">
                            <i class="ph-fill ph-play"></i>
                        </div>
                    </div>
                </div>

                <div class="cinema-actions">
                    <button type="button" onclick="openVideoTheater()" class="btn-cinema play">
                        <i class="ph ph-play"></i> Reproducir
                    </button>
                    <a href="<?php echo htmlspecialchars($reunion['recording_link']); ?>&export=download" target="_blank" class="btn-cinema download">
                        <i class="ph ph-download-simple"></i> Descargar
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </div>

</div>

<!-- Video Theater Modal -->
<?php if(!empty($reunion['recording_link'])): 
    $embed_link_theater = str_replace('/view', '/preview', $reunion['recording_link']);
?>
<div class="video-theater" id="video-theater">
    <div class="video-theater-header">
        <h3><i class="ph ph-film-slate"></i> <?php echo htmlspecialchars($reunion['motivo']); ?></h3>
        <button class="video-theater-close" onclick="closeVideoTheater()"><i class="ph ph-x"></i></button>
    </div>
    <div class="video-theater-body">
        <iframe id="theater-iframe" src="" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
    </div>
    <div class="video-theater-footer">
        <a href="<?php echo htmlspecialchars($reunion['recording_link']); ?>" target="_blank" class="btn-rv-action" style="background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.2);">
            <i class="ph ph-arrow-square-out"></i> Abrir en Drive
        </a>
        <a href="<?php echo htmlspecialchars($reunion['recording_link']); ?>&export=download" target="_blank" class="btn-rv-action" style="background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.2);">
            <i class="ph ph-download-simple"></i> Descargar
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Edit Meet Modal -->
<div id="edit-meet-modal" class="rv-modal-overlay">
    <div class="rv-modal-content">
        <div class="modal-head">
            <h3>
                <i class="ph ph-pencil-simple" style="color: #ea4335;"></i>
                <span>Editar Google Meet</span>
            </h3>
            <button type="button" class="btn-card-icon" onclick="closeEditMeetModal()" style="border:none; background:transparent; font-size:1.2rem; cursor:pointer;"><i class="ph ph-x"></i></button>
        </div>

        <form id="edit-meet-form">
            <input type="hidden" id="edit-meet-id" value="<?php echo $reunion['id']; ?>">
            
            <div class="modal-field">
                <label>Motivo de la Reunión</label>
                <input type="text" id="edit-meet-motivo" class="modal-input-ctl" required value="<?php echo htmlspecialchars($reunion['motivo']); ?>">
            </div>

            <div class="modal-field">
                <label>Marca / Cliente</label>
                <select id="edit-meet-marca" class="modal-input-ctl" required data-current="<?php echo $reunion['brand_id']; ?>">
                    <option value="">Cargando marcas...</option>
                </select>
            </div>

            <div class="modal-field">
                <label>Fecha y Hora</label>
                <input type="datetime-local" id="edit-meet-fecha" class="modal-input-ctl" required value="<?php echo date('Y-m-d\TH:i', strtotime($reunion['fecha_hora'])); ?>">
            </div>

            <div class="modal-field">
                <label>Invitados Extras (Emails)</label>
                <input type="text" id="edit-meet-guests" class="modal-input-ctl" placeholder="ejemplo@correo.com, otro@correo.com" value="<?php echo htmlspecialchars($reunion['guests'] ?? ''); ?>">
            </div>

            <div class="modal-field">
                <label>Etiquetas</label>
                <input type="text" id="edit-meet-tags" class="modal-input-ctl" placeholder="estrategia, diseño, mensual" value="<?php echo htmlspecialchars($reunion['tags'] ?? ''); ?>">
            </div>

            <div class="modal-foot">
                <button type="button" onclick="closeEditMeetModal()" class="btn-rv-action">Cancelar</button>
                <button type="submit" id="edit-meet-submit" class="btn-rv-action" style="background: #ea4335; color: #fff; border-color: #ea4335;">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
// ========= THEATER & VIDEO =========
function openVideoTheater() {
    const theater = document.getElementById('video-theater');
    const iframe = document.getElementById('theater-iframe');
    if (!theater || !iframe) return;
    
    const embedLink = '<?php echo isset($embed_link_theater) ? htmlspecialchars($embed_link_theater) : ""; ?>';
    if (embedLink) iframe.src = embedLink;
    theater.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeVideoTheater() {
    const theater = document.getElementById('video-theater');
    const iframe = document.getElementById('theater-iframe');
    if (!theater) return;
    
    theater.classList.remove('active');
    document.body.style.overflow = '';
    if (iframe) iframe.src = '';
}

// ========= SYNC GEMINI SINGLE =========
async function syncGeminiNotesSingle() {
    const btn = document.getElementById('btn-sync-single');
    const txt = document.getElementById('sync-text');
    const icon = btn.querySelector('i');
    
    btn.disabled = true;
    if (icon) icon.className = 'ph ph-spinner ph-spin';
    if (txt) txt.textContent = 'Sincronizando...';

    try {
        const res = await fetch('cron/fetch_gemini_notes.php');
        const data = await res.json();
        if (data.status === 'success') {
            if (window.showToast) window.showToast('¡Notas sincronizadas exitosamente!', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            if (window.showToast) window.showToast('No se encontraron nuevas notas aún.', 'info');
        }
    } catch (e) {
        if (window.showToast) window.showToast('Error al conectar con el sincronizador', 'error');
    } finally {
        btn.disabled = false;
        if (icon) icon.className = 'ph ph-sparkle';
        if (txt) txt.textContent = 'Sincronizar Gemini';
    }
}

// ========= COPY ACTIONS =========
function copySummaryText() {
    const el = document.getElementById('gemini-summary-content');
    if (!el) return;
    navigator.clipboard.writeText(el.innerText).then(() => {
        if (window.showToast) window.showToast('Resumen copiado al portapapeles', 'success');
    });
}

function copyMeetLink(link) {
    if (!link) {
        if (window.showToast) window.showToast('No hay enlace de Meet disponible', 'warning');
        return;
    }
    navigator.clipboard.writeText(link).then(() => {
        if (window.showToast) window.showToast('Enlace de Google Meet copiado', 'success');
    });
}

function copyInvitation(motivo, fecha, link) {
    const msg = `📅 *Reunión:* ${motivo}\n⏰ *Fecha y Hora:* ${fecha}\n🔗 *Enlace Meet:* ${link || 'Por confirmar'}\n\n¡Te esperamos puntual!`;
    navigator.clipboard.writeText(msg).then(() => {
        if (window.showToast) window.showToast('Invitación copiada al portapapeles', 'success');
    });
}

function sendWhatsAppInvitation(phone, motivo, fecha, link) {
    const msg = encodeURIComponent(`Hola, te comparto los datos de nuestra reunión:\n\n📅 *${motivo}*\n⏰ *Fecha:* ${fecha}\n🔗 *Enlace Meet:* ${link}\n\n¡Nos vemos pronto!`);
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    window.open(`https://api.whatsapp.com/send?phone=${cleanPhone}&text=${msg}`, '_blank');
}

// ========= DELETE & EDIT MODAL =========
function deleteMeet(id) {
    if (!confirm('¿Estás seguro que deseas eliminar esta reunión? Se borrará también del calendario de Google si es posible.')) return;
    
    const data = new FormData();
    data.append('id', id);
    
    fetch('ajax/delete_meet.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.showToast) window.showToast('Reunión eliminada exitosamente', 'success');
                setTimeout(() => window.location.href = 'index.php?module=reuniones', 800);
            } else {
                if (window.showToast) window.showToast('Error: ' + res.error, 'error');
            }
        })
        .catch(err => {
            if (window.showToast) window.showToast('Error de conexión', 'error');
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
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';

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
                if (window.showToast) window.showToast('Reunión actualizada exitosamente', 'success');
                closeEditMeetModal();
                setTimeout(() => window.location.reload(), 700);
            } else {
                if (window.showToast) window.showToast('Error: ' + (res.error || 'Desconocido'), 'error');
            }
        })
        .catch(err => {
            if (window.showToast) window.showToast('Error de conexión', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = 'Guardar Cambios';
        });
});

// Modal close handlers
document.getElementById('edit-meet-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEditMeetModal();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeVideoTheater();
        closeEditMeetModal();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
