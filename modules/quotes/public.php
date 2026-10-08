<?php
// modules/quotes/public.php
if (!isset($db)) {
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (!$token) {
    die("Enlace inválido o expirado.");
}

// Fetch quote
$stmt = $db->prepare("SELECT q.*, c.name as client_name 
                     FROM quotes q 
                     LEFT JOIN clients c ON q.client_id = c.id 
                     WHERE q.public_token = ?");
$stmt->execute([$token]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    die("Cotización no encontrada.");
}

// Fetch items
$stmt = $db->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id ASC");
$stmt->execute([$quote['id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sym = $quote['currency'] === 'USD' ? '$' : 'S/';
$hide_prices = !empty($quote['hide_prices']);

// Parse bank accounts if available
$pm_lines = [];
if (!empty($quote['show_payment_methods']) && !empty($quote['payment_methods_text'])) {
    $pm_lines = explode("\n", trim($quote['payment_methods_text']));
}

// Build Sequential Execution Timeline ("solo fecha de inicio hasta el fin y así consiguiente")
$timeline_phases = [];
$project_start_date = null;
$project_end_date = null;
$total_project_days = 0;

$cursor_date = !empty($quote['issue_date']) ? $quote['issue_date'] : date('Y-m-d');

foreach ($items as $idx => $it) {
    $duration = (int)($it['gantt_duration'] ?? 0);
    $has_start = !empty($it['gantt_start_date']);
    
    if ($duration > 0 || $has_start) {
        $duration = max(1, $duration);
        $phase_start_str = $has_start ? $it['gantt_start_date'] : $cursor_date;
        
        try {
            $s_dt = new DateTime($phase_start_str);
        } catch(Exception $e) {
            $s_dt = new DateTime();
        }
        
        $e_dt = clone $s_dt;
        if ($duration > 1) {
            $e_dt->modify('+' . ($duration - 1) . ' days');
        }
        
        $phase_start = $s_dt->format('Y-m-d');
        $phase_end = $e_dt->format('Y-m-d');
        
        // Clean phase title
        $raw_desc = strip_tags($it['description']);
        $lines = preg_split("/\r\n|\n|\r/", trim($raw_desc));
        $clean_title = !empty($lines[0]) ? mb_substr(trim($lines[0]), 0, 75) : ('Fase ' . (count($timeline_phases) + 1));
        
        $timeline_phases[] = [
            'num' => count($timeline_phases) + 1,
            'title' => $clean_title,
            'start' => $phase_start,
            'end' => $phase_end,
            'start_formatted' => $s_dt->format('d M, Y'),
            'end_formatted' => $e_dt->format('d M, Y'),
            'duration' => $duration
        ];
        
        // Advance cursor to next day for consecutive chaining
        $next_dt = clone $e_dt;
        $next_dt->modify('+1 day');
        $cursor_date = $next_dt->format('Y-m-d');
        
        if ($project_start_date === null || $phase_start < $project_start_date) {
            $project_start_date = $phase_start;
        }
        if ($project_end_date === null || $phase_end > $project_end_date) {
            $project_end_date = $phase_end;
        }
    }
}

if (!empty($timeline_phases) && $project_start_date && $project_end_date) {
    try {
        $ps = new DateTime($project_start_date);
        $pe = new DateTime($project_end_date);
        $total_project_days = $ps->diff($pe)->days + 1;
    } catch(Exception $e) {
        $total_project_days = 0;
    }
}

// Fetch Global Settings for company info
$stmtSettings = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($stmtSettings->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Calculate clean base URL
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$protocol = $is_https ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_path = preg_replace('#/modules/quotes/?$#', '', $script_dir);
if ($base_path === '/' || $base_path === '\\') {
    $base_path = '';
}
$base_url = rtrim($protocol . $host . $base_path, '/') . '/';

// Theme Presets & Color Logic
$theme_presets = [
    'corporate-blue' => [
        'name' => 'Azul Corporativo',
        'light' => '#2563eb',
        'dark' => '#3b82f6',
        'glow' => 'rgba(37, 99, 235, 0.35)',
        'gradient' => 'linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%)'
    ],
    'emerald' => [
        'name' => 'Esmeralda Tech',
        'light' => '#059669',
        'dark' => '#10b981',
        'glow' => 'rgba(16, 185, 129, 0.35)',
        'gradient' => 'linear-gradient(135deg, #047857 0%, #10b981 100%)'
    ],
    'violet' => [
        'name' => 'Violeta Creativo',
        'light' => '#7c3aed',
        'dark' => '#8b5cf6',
        'glow' => 'rgba(139, 92, 246, 0.35)',
        'gradient' => 'linear-gradient(135deg, #6d28d9 0%, #8b5cf6 100%)'
    ],
    'minimal-black' => [
        'name' => 'Negro Minimalista',
        'light' => '#0f172a',
        'dark' => '#f4f4f5',
        'glow' => 'rgba(255, 255, 255, 0.15)',
        'gradient' => 'linear-gradient(135deg, #27272a 0%, #09090b 100%)'
    ],
    'amber-gold' => [
        'name' => 'Ámbar Ejecutivo',
        'light' => '#d97706',
        'dark' => '#f59e0b',
        'glow' => 'rgba(245, 158, 11, 0.35)',
        'gradient' => 'linear-gradient(135deg, #b45309 0%, #f59e0b 100%)'
    ],
    'crimson' => [
        'name' => 'Carmín / Crimson',
        'light' => '#e11d48',
        'dark' => '#f43f5e',
        'glow' => 'rgba(244, 63, 94, 0.35)',
        'gradient' => 'linear-gradient(135deg, #be123c 0%, #f43f5e 100%)'
    ],
];

$selected_theme_key = !empty($quote['theme_color']) ? $quote['theme_color'] : 'corporate-blue';
$active_theme = $theme_presets[$selected_theme_key] ?? $theme_presets['corporate-blue'];

// Cover Banner calculation
$has_cover = false;
$cover_css = '';
if (!empty($quote['cover_image'])) {
    $has_cover = true;
    $cover_url = preg_match('#^https?://#i', $quote['cover_image']) ? $quote['cover_image'] : $base_url . ltrim($quote['cover_image'], '/');
    $cover_css = 'background-image: url(' . htmlspecialchars($cover_url) . '); background-size: cover; background-position: center;';
} elseif (!empty($quote['cover_gradient']) && $quote['cover_gradient'] !== 'none') {
    $has_cover = true;
    $grad_map = [
        'mesh-blue' => 'radial-gradient(at 0% 0%, #2563eb 0px, transparent 65%), radial-gradient(at 100% 100%, #6366f1 0px, transparent 65%), linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%)',
        'emerald-glow' => 'radial-gradient(at 0% 0%, #059669 0px, transparent 65%), radial-gradient(at 100% 100%, #0891b2 0px, transparent 65%), linear-gradient(135deg, #064e3b 0%, #0f172a 100%)',
        'creative-violet' => 'radial-gradient(at 0% 0%, #9333ea 0px, transparent 65%), radial-gradient(at 100% 100%, #db2777 0px, transparent 65%), linear-gradient(135deg, #581c87 0%, #0f172a 100%)',
        'sunset-gold' => 'radial-gradient(at 0% 0%, #d97706 0px, transparent 65%), radial-gradient(at 100% 100%, #dc2626 0px, transparent 65%), linear-gradient(135deg, #78350f 0%, #0f172a 100%)',
        'cyber-dark' => 'linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #090d16 100%)',
        'minimal-clean' => 'linear-gradient(135deg, #334155 0%, #1e293b 100%)',
    ];
    $cover_css = 'background: ' . ($grad_map[$quote['cover_gradient']] ?? $grad_map['mesh-blue']) . ';';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización #<?php echo str_pad($quote['id'], 4, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($quote['client_name'] ?? ''); ?></title>
    <?php if(!empty($settings['favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($base_url . ltrim($settings['favicon'], '/')); ?>">
    <?php endif; ?>
    
    <!-- Fonts and Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        :root {
            --primary: <?php echo $active_theme['light']; ?>;
            --primary-hover: color-mix(in srgb, var(--primary) 85%, #000000);
            --primary-light: color-mix(in srgb, var(--primary) 12%, transparent);
            --theme-glow: <?php echo $active_theme['glow']; ?>;
            --theme-gradient: <?php echo $active_theme['gradient']; ?>;
            --bg: #f8fafc;
            --surface: #ffffff;
            --surface-elevated: #f1f5f9;
            --surface-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --border-subtle: #f1f5f9;
            --border-focus: #cbd5e1;
            --header-bg: #f8fafc;
            --card-radius: 20px;
            --inner-radius: 12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.04);
            --shadow-card: 0 20px 40px -15px rgba(0,0,0,0.06), 0 0 0 1px rgba(0,0,0,0.04);
            --transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        [data-theme="dark"] {
            --primary: <?php echo $active_theme['dark']; ?>;
            --primary-hover: color-mix(in srgb, var(--primary) 85%, #ffffff);
            --primary-light: color-mix(in srgb, var(--primary) 18%, transparent);
            --theme-glow: <?php echo $active_theme['glow']; ?>;
            --bg: #000000;
            --surface: #0a0a0a;
            --surface-elevated: #141414;
            --surface-card: #0f0f11;
            --text-main: #f4f4f5;
            --text-muted: #a1a1aa;
            --border: #222225;
            --border-subtle: #1a1a1c;
            --border-focus: #3f3f46;
            --header-bg: #0a0a0a;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.4);
            --shadow-card: 0 25px 50px -12px rgba(0,0,0,0.8), 0 0 0 1px #222225;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            padding: 2.5rem 1rem;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .container {
            max-width: 1040px;
            margin: 0 auto;
        }

        /* Top Action Bar - Executive Modern Header */
        .top-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .top-bar-branding {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .top-folio-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 1rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 9999px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: 0.05em;
            box-shadow: var(--shadow-sm);
        }

        .folio-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--primary);
            box-shadow: 0 0 8px var(--primary);
        }

        .actions-right {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .btn-theme-switch {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-main);
            cursor: pointer;
            font-size: 1.15rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }

        .btn-theme-switch:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }

        /* Print Button - High Contrast for Light and Dark Modes */
        .btn-action-print {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #0f172a;
            color: #ffffff;
            border: 1px solid #0f172a;
            padding: 0.65rem 1.35rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.88rem;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.18);
            font-family: inherit;
            white-space: nowrap;
        }

        .btn-action-print:hover {
            background: #1e293b;
            border-color: #1e293b;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.28);
            color: #ffffff;
        }

        [data-theme="dark"] .btn-action-print {
            background: #27272a;
            color: #f4f4f5;
            border: 1px solid #52525b;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.5);
        }

        [data-theme="dark"] .btn-action-print:hover {
            background: #3f3f46;
            border-color: #71717a;
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.7);
        }

        /* Cover Banner Styles - Pure Smooth Contrast Blend */
        .doc-cover-banner {
            width: 100%;
            height: 155px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
            padding: 1.25rem 2.5rem;
        }

        .cover-banner-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.02) 0%, rgba(0,0,0,0.08) 50%, color-mix(in srgb, var(--surface) 60%, transparent) 80%, var(--surface) 100%);
            pointer-events: none;
        }

        [data-theme="dark"] .cover-banner-overlay {
            background: linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.3) 50%, color-mix(in srgb, var(--surface) 75%, transparent) 85%, var(--surface) 100%);
        }

        /* Proposal Approval & Modal Styles */
        .btn-action-approve {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #10b981;
            color: #ffffff;
            border: none;
            padding: 0.65rem 1.4rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
            font-family: inherit;
        }
        .btn-action-approve:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
            color: #ffffff;
        }
        .badge-approved-status {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 0.55rem 1rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .approval-cta-banner {
            margin: 2rem 3rem 0;
            background: color-mix(in srgb, #10b981 8%, var(--surface));
            border: 1px solid color-mix(in srgb, #10b981 25%, transparent);
            border-radius: 16px;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.25rem;
        }
        .cta-banner-text h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .cta-banner-text p {
            margin: 0.35rem 0 0;
            font-size: 0.88rem;
            color: var(--text-muted);
        }
        .approval-success-banner {
            margin: 2rem 3rem 0;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.25);
            border-radius: 16px;
            padding: 1.25rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Modal Aprobar */
        .approve-modal-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(6px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .approve-modal-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            animation: approveSlideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes approveSlideIn {
            from { opacity: 0; transform: translateY(15px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .approve-modal-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-bottom: 1px solid var(--border);
            position: relative;
        }
        .approve-icon-wrap {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .approve-modal-body {
            padding: 1.5rem;
        }
        .approve-summary-box {
            background: color-mix(in srgb, var(--surface-elevated) 60%, var(--surface));
            border: 1px dashed var(--border);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1.25rem;
        }
        .summary-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .summary-total {
            font-size: 1.75rem;
            font-weight: 800;
            color: #10b981;
            margin-top: 4px;
        }
        .approve-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-main);
            font-size: 0.95rem;
            font-family: inherit;
            box-sizing: border-box;
            outline: none;
            transition: var(--transition);
        }
        .approve-input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        .approve-checkbox-wrap {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            font-size: 0.85rem;
            color: var(--text-muted);
            cursor: pointer;
            line-height: 1.4;
        }
        .approve-checkbox-wrap input {
            margin-top: 3px;
            accent-color: #10b981;
            width: 16px; height: 16px;
        }
        .approve-modal-footer {
            padding: 1.25rem 1.5rem;
            background: color-mix(in srgb, var(--surface-elevated) 40%, var(--surface));
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        .btn-approve-cancel {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 0.65rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.88rem;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-approve-confirm {
            background: #10b981;
            border: none;
            color: #ffffff;
            padding: 0.65rem 1.35rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
        }
        .btn-close-modal {
            position: absolute;
            right: 1.25rem;
            top: 1.25rem;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-muted);
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .approval-cta-banner {
                margin: 1.5rem 1rem 0;
                padding: 1.25rem;
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .approval-cta-banner .btn-action-approve {
                width: 100%;
                justify-content: center;
            }
            .approval-success-banner {
                margin: 1.5rem 1rem 0;
                padding: 1rem;
            }
        }

        /* Document Wrapper Card */
        .document-card {
            background: var(--surface);
            border-radius: var(--card-radius);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            border: 1px solid var(--border);
            margin-bottom: 2rem;
            transition: var(--transition);
        }

        /* Header section */
        .doc-header {
            padding: 2.5rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid var(--border);
            background: var(--header-bg);
            gap: 2rem;
            flex-wrap: wrap;
        }

        .company-brand {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 440px;
        }

        .company-logo-img {
            max-height: 48px;
            max-width: 220px;
            object-fit: contain;
        }

        .company-logo-img.logo-dark { display: none; }
        [data-theme="dark"] .company-logo-img.logo-light { display: none; }
        [data-theme="dark"] .company-logo-img.logo-dark { display: block; }

        .company-fallback-logo {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.03em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .company-info-list {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .company-name-title {
            font-weight: 700;
            color: var(--text-main);
            font-size: 0.95rem;
        }

        .company-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .company-info-item i {
            color: var(--primary);
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .doc-quote-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            text-align: right;
            gap: 0.35rem;
        }

        .quote-badge-tag {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid color-mix(in srgb, var(--primary) 25%, transparent);
        }

        .doc-quote-number {
            font-size: 2.35rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.03em;
            line-height: 1.05;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            border: 1px solid transparent;
        }
        .status-pill .pulsing-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-borrador { background: rgba(148, 163, 184, 0.15); color: #94a3b8; border-color: rgba(148, 163, 184, 0.3); }
        .status-borrador .pulsing-dot { background: #94a3b8; }
        .status-enviada { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3); }
        .status-enviada .pulsing-dot { background: #60a5fa; box-shadow: 0 0 8px #60a5fa; }
        .status-aceptada { background: rgba(16, 185, 129, 0.15); color: #34d399; border-color: rgba(16, 185, 129, 0.3); }
        .status-aceptada .pulsing-dot { background: #34d399; box-shadow: 0 0 8px #34d399; }
        .status-rechazada { background: rgba(239, 68, 68, 0.15); color: #f87171; border-color: rgba(239, 68, 68, 0.3); }
        .status-rechazada .pulsing-dot { background: #f87171; }

        /* Meta Cards Strip */
        .meta-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            padding: 1.35rem 2.5rem;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            background: color-mix(in srgb, var(--surface-elevated) 60%, var(--surface));
        }

        .meta-item-box {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .meta-icon-tile {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--surface);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--primary);
            flex-shrink: 0;
            box-shadow: var(--shadow-sm);
        }

        .meta-text-group {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            min-width: 0;
        }

        .meta-item-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: block;
        }

        .meta-item-value {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.01em;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .meta-item-sub {
            font-size: 0.76rem;
            color: var(--text-muted);
            display: block;
        }

        /* Document Body */
        .doc-body {
            padding: 2.25rem 2.5rem;
        }

        /* Services Table - 100% full width, strictly responsive, no horizontal cutoff */
        .table-responsive-wrap {
            width: 100%;
            margin-bottom: 2rem;
            border-radius: var(--inner-radius);
            border: 1px solid var(--border);
            background: var(--surface);
            overflow-x: auto;
            scrollbar-width: thin;
        }

        .services-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* Ensures strict 100% layout and prevents columns from escaping */
        }

        .services-table th {
            background: var(--surface-elevated);
            color: var(--text-muted);
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.95rem 1.25rem;
            border-bottom: 1px solid var(--border);
        }

        .services-table th.col-desc {
            width: auto;
            text-align: left;
        }

        .services-table th.col-qty {
            width: 80px;
            text-align: center;
        }

        .services-table th.col-price {
            width: 135px;
            text-align: right;
        }

        .services-table th.col-total {
            width: 145px;
            text-align: right;
        }

        /* When prices are hidden: description column takes 100% full width */
        .services-table.hide-prices-table {
            width: 100%;
            table-layout: auto;
        }
        .services-table.hide-prices-table th.col-desc,
        .services-table.hide-prices-table td.service-desc-cell {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        .services-table.hide-prices-table td.service-desc-cell .quote-table-wrapper {
            width: 100% !important;
            max-width: 100% !important;
        }
        .services-table.hide-prices-table td.service-desc-cell table {
            width: 100% !important;
            min-width: 100% !important;
        }

        .services-table td {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
            font-size: 0.92rem;
            color: var(--text-main);
            background: var(--surface);
        }

        .services-table td.col-qty {
            text-align: center;
            font-weight: 600;
        }

        .services-table td.col-price {
            text-align: right;
        }

        .services-table td.col-total {
            text-align: right;
        }

        .services-table tbody tr:last-child td {
            border-bottom: none;
        }

        .services-table tbody tr:hover td {
            background: color-mix(in srgb, var(--surface-elevated) 35%, var(--surface));
        }

        .service-desc-cell {
            line-height: 1.6;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .service-desc-cell * {
            max-width: 100% !important;
            box-sizing: border-box !important;
        }

        .service-desc-cell strong {
            color: var(--text-main);
            font-size: 0.98rem;
        }

        .service-desc-cell ul, .service-desc-cell ol {
            margin: 0.5rem 0 0 1.25rem;
            color: var(--text-muted);
            font-size: 0.88rem;
        }

        .service-desc-cell li {
            margin-bottom: 0.25rem;
        }

        /* Nested Modern Tables inside Service Description */
        .service-desc-cell .quote-table-wrapper {
            margin: 0.85rem 0 0.5rem 0;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow-x: auto;
            background: var(--surface);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .service-desc-cell .quote-table-actions {
            display: none !important;
        }

        .service-desc-cell .quote-modern-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
            line-height: 1.5;
            text-align: left;
            margin: 0;
            border: none;
        }

        .service-desc-cell .quote-modern-table th {
            background: var(--surface-elevated, #f8fafc);
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border);
            border-top: none;
            border-left: none;
            border-right: none;
            white-space: nowrap;
        }

        .service-desc-cell .quote-modern-table td {
            padding: 8px 12px !important;
            font-size: 0.84rem !important;
            color: var(--text-main) !important;
            border-bottom: 1px solid var(--border) !important;
            border-top: none !important;
            border-left: none !important;
            border-right: none !important;
            background: transparent !important;
            vertical-align: middle !important;
        }

        .service-desc-cell .quote-modern-table tbody tr:last-child td {
            border-bottom: none !important;
        }

        .service-desc-cell .quote-modern-table tbody tr:hover td {
            background: color-mix(in srgb, var(--surface-elevated) 60%, var(--surface)) !important;
        }

        .amount-highlight {
            font-weight: 700;
            font-size: 0.98rem;
            color: var(--text-main);
        }

        .discount-tag {
            color: #ef4444;
            font-weight: 600;
            font-size: 0.88rem;
        }

        /* Totals Card */
        .totals-summary-card {
            width: 100%;
            max-width: 380px;
            margin-left: auto;
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            border-radius: var(--inner-radius);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            box-shadow: var(--shadow-sm);
        }

        .calc-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.92rem;
            color: var(--text-muted);
        }

        .calc-row-val {
            font-weight: 600;
            color: var(--text-main);
            font-size: 1rem;
        }

        .calc-divider {
            height: 1px;
            background: var(--border);
            margin: 0.25rem 0;
        }

        .calc-row.total-row {
            margin-top: 0.25rem;
            padding-top: 0.5rem;
        }

        .calc-row.total-row .calc-row-label {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.01em;
        }

        .calc-row.total-row .calc-row-val {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.02em;
        }

        /* Sections (Gantt, Payment, Notes) */
        .section-block {
            margin-top: 2.75rem;
            padding-top: 2.25rem;
            border-top: 1px dashed var(--border);
        }

        .section-header-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .section-header-title i {
            color: var(--primary);
            font-size: 1.3rem;
        }

        /* ==========================================================================
           Modern Sequential Execution Roadmap (Timeline)
           ========================================================================== */
        .timeline-header-wrap {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .section-header-sub {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .timeline-meta-badges {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }

        .timeline-badge-duration,
        .timeline-badge-range {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            color: var(--text-muted);
        }

        .timeline-badge-duration {
            background: var(--primary-light);
            color: var(--primary);
            border-color: color-mix(in srgb, var(--primary) 25%, transparent);
        }

        .roadmap-phases-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            position: relative;
        }

        .roadmap-phase-card {
            display: flex;
            align-items: stretch;
            gap: 1.25rem;
            position: relative;
        }

        .phase-left-milestone {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 40px;
            flex-shrink: 0;
        }

        .phase-number-chip {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: var(--surface);
            border: 2px solid var(--primary);
            color: var(--primary);
            font-size: 0.95rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 12px var(--theme-glow);
            z-index: 2;
            flex-shrink: 0;
        }

        .phase-connector-line {
            width: 2px;
            flex-grow: 1;
            background: linear-gradient(to bottom, var(--primary) 0%, var(--border) 100%);
            margin: 6px 0;
            opacity: 0.6;
        }

        .phase-card-body {
            flex-grow: 1;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .phase-card-body:hover {
            border-color: color-mix(in srgb, var(--primary) 40%, var(--border));
            transform: translateX(3px);
        }

        .phase-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .phase-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }

        .phase-duration-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--primary);
            background: var(--primary-light);
            padding: 0.25rem 0.65rem;
            border-radius: 8px;
        }

        .phase-dates-flow {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: var(--surface-elevated);
            padding: 0.65rem 1rem;
            border-radius: 10px;
            border: 1px solid var(--border-subtle);
            font-size: 0.85rem;
            flex-wrap: wrap;
        }

        .date-step {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-muted);
        }

        .date-step strong {
            color: var(--text-main);
            font-weight: 700;
        }

        .date-step i {
            color: var(--primary);
            font-size: 1rem;
        }

        .date-flow-arrow {
            color: var(--primary);
            display: flex;
            align-items: center;
            font-size: 1rem;
        }

        .phase-progress-track {
            width: 100%;
            height: 6px;
            background: var(--surface-elevated);
            border-radius: 9999px;
            overflow: hidden;
            position: relative;
            border: 1px solid var(--border-subtle);
        }

        .phase-progress-bar {
            height: 100%;
            background: var(--theme-gradient);
            border-radius: 9999px;
            transition: width 0.3s ease;
        }

        /* ==========================================================================
           Modern Corporate Payment Hub
           ========================================================================== */
        .payment-section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }

        .payment-security-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.25);
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
        }

        .payment-grid-modern {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 1.15rem;
        }

        .payment-card-modern {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .payment-card-modern:hover {
            border-color: color-mix(in srgb, var(--primary) 50%, var(--border));
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
        }

        .card-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .bank-identity {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .bank-avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .payment-card-modern.card-wallet .bank-avatar {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .bank-titles {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .bank-name-label {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .bank-type-pill {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.04em;
        }

        .btn-copy-account-modern {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 0.45rem 0.85rem;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }

        .btn-copy-account-modern:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .account-number-box {
            background: color-mix(in srgb, var(--surface-elevated) 70%, var(--surface));
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 0.85rem 1rem;
            text-align: center;
        }

        .account-code-value {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: 0.05em;
            user-select: all;
        }

        .payment-instructions-footer {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            margin-top: 1.25rem;
            padding: 0.9rem 1.15rem;
            background: color-mix(in srgb, var(--surface-elevated) 50%, var(--surface));
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 0.82rem;
            color: var(--text-muted);
            line-height: 1.45;
        }

        .payment-instructions-footer i {
            color: var(--primary);
            font-size: 1.15rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* Sub-tables Modern Desktop Styling */
        .service-desc-cell table {
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            border: 1px solid var(--border) !important;
            border-radius: 12px !important;
            overflow: hidden !important;
            margin: 1rem 0 !important;
            background: var(--surface) !important;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03) !important;
        }

        .service-desc-cell table th {
            background: var(--surface-elevated) !important;
            color: var(--text-main) !important;
            font-size: 0.78rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 0.85rem 1rem !important;
            border-bottom: 1px solid var(--border) !important;
            border-right: 1px solid var(--border-subtle) !important;
        }

        .service-desc-cell table th:last-child {
            border-right: none !important;
        }

        .service-desc-cell table td {
            padding: 0.85rem 1rem !important;
            border-bottom: 1px solid var(--border-subtle) !important;
            border-right: 1px solid var(--border-subtle) !important;
            color: var(--text-main) !important;
            font-size: 0.88rem !important;
            line-height: 1.55 !important;
            background: transparent !important;
        }

        .service-desc-cell table td:last-child {
            border-right: none !important;
        }

        .service-desc-cell table tr:last-child td {
            border-bottom: none !important;
        }

        .service-desc-cell table tr:hover td {
            background: color-mix(in srgb, var(--primary) 3%, var(--surface)) !important;
        }

        /* Notes & Terms Grid */
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.25rem;
        }

        .note-card {
            background: var(--surface-elevated);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .note-card-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-main);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .note-card-title i {
            color: var(--primary);
            font-size: 1rem;
        }

        .note-card-body {
            font-size: 0.88rem;
            color: var(--text-muted);
            white-space: pre-wrap;
            line-height: 1.6;
        }

        /* Footer */
        .doc-footer {
            text-align: center;
            padding: 1.5rem;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* Toast notification */
        .copy-toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--surface);
            color: var(--text-main);
            border: 1px solid var(--border);
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: all 0.25s ease;
            z-index: 9999;
        }

        .copy-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            body {
                padding: 1rem 0.5rem;
            }

            .doc-header {
                padding: 1.75rem 1.5rem;
                flex-direction: column;
                gap: 1.5rem;
            }

            .company-brand {
                max-width: 100%;
            }

            .doc-quote-meta {
                align-items: flex-start;
                width: 100%;
            }

            .doc-quote-number {
                font-size: 1.75rem;
            }

            .meta-strip {
                padding: 1.25rem 1.5rem;
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .doc-body {
                padding: 1.5rem;
            }

            .services-table thead {
                display: none;
            }

            .services-table, 
            .services-table tbody, 
            .services-table tr, 
            .services-table td {
                display: block;
                width: 100%;
            }

            .services-table {
                border: none;
                background: transparent;
            }

            .services-table tr {
                background: var(--surface-elevated);
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .services-table td {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 0.45rem 0;
                border-bottom: none;
                background: transparent !important;
            }

            .services-table td::before {
                content: attr(data-label);
                font-size: 0.72rem;
                font-weight: 700;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 0.04em;
                min-width: 100px;
            }

            .services-table td.service-desc-cell {
                flex-direction: column;
                gap: 0.35rem;
                padding-bottom: 0.75rem;
                margin-bottom: 0.5rem;
                border-bottom: 1px solid var(--border);
            }

            .services-table td.service-desc-cell::before {
                margin-bottom: 0.25rem;
            }

            .services-table.hide-prices-table td.service-desc-cell {
                border-bottom: none !important;
                margin-bottom: 0 !important;
                padding-bottom: 0 !important;
            }
            .services-table.hide-prices-table td.service-desc-cell::before {
                display: none !important;
            }

            /* Sub-tables Responsive Cards inside description */
            .service-desc-cell table,
            .service-desc-cell table thead,
            .service-desc-cell table tbody,
            .service-desc-cell table tr,
            .service-desc-cell table td {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            .service-desc-cell table {
                border: none !important;
                background: transparent !important;
                box-shadow: none !important;
                margin: 1rem 0 !important;
                overflow: visible !important;
            }
            .service-desc-cell table thead {
                display: none !important;
            }
            .service-desc-cell table tbody {
                display: flex !important;
                flex-direction: column !important;
                gap: 0.85rem !important;
            }
            .service-desc-cell table tr {
                background: var(--surface) !important;
                border: 1px solid var(--border) !important;
                border-radius: 14px !important;
                padding: 1.1rem !important;
                box-shadow: var(--shadow-sm) !important;
                margin-bottom: 0 !important;
            }
            .service-desc-cell table td {
                display: flex !important;
                flex-direction: column !important;
                gap: 0.3rem !important;
                padding: 0.65rem 0 !important;
                border: none !important;
                border-bottom: 1px dashed var(--border-subtle) !important;
                text-align: left !important;
            }
            .service-desc-cell table td:last-child {
                border-bottom: none !important;
                padding-bottom: 0 !important;
            }
            .service-desc-cell table td::before {
                content: attr(data-label) !important;
                font-size: 0.72rem !important;
                font-weight: 700 !important;
                color: var(--primary) !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
                display: block !important;
            }

            .totals-summary-card {
                max-width: 100%;
            }

            .calc-row.total-row .calc-row-val {
                font-size: 1.4rem;
            }
        }

        @media (max-width: 640px) {
            .top-action-bar {
                margin-bottom: 1rem;
                gap: 0.4rem;
            }
            .top-bar-branding {
                display: none;
            }
            .actions-right {
                width: 100%;
                justify-content: space-between;
                gap: 0.4rem;
            }
            .btn-theme-switch {
                width: 38px;
                height: 38px;
                font-size: 1rem;
            }
            .btn-action-print {
                padding: 0.55rem 0.8rem;
                font-size: 0.8rem;
                flex: 1;
                justify-content: center;
            }
            .btn-action-print .btn-text-full {
                display: none;
            }
            .btn-action-print .btn-text-mobile {
                display: inline !important;
            }
            .btn-action-approve {
                padding: 0.55rem 0.85rem;
                font-size: 0.8rem;
                flex: 1.1;
                justify-content: center;
            }
            .btn-action-approve .btn-text-full {
                display: none;
            }
            .btn-action-approve .btn-text-mobile {
                display: inline !important;
            }
            .timeline-header-wrap {
                flex-direction: column;
                align-items: stretch;
            }
            .phase-dates-flow {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .date-flow-arrow {
                display: none;
            }
        }

        /* Print Optimization */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
            }
            .container {
                max-width: 100% !important;
            }
            .top-action-bar,
            .btn-theme-switch,
            .btn-copy-account,
            .copy-toast {
                display: none !important;
            }
            .document-card {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
            }
            .doc-header, .meta-strip, .services-table th, .totals-summary-card, .note-card, .payment-card {
                background: #f8fafc !important;
                border-color: #e2e8f0 !important;
            }
            .calc-row.total-row .calc-row-val {
                color: #000000 !important;
            }
            .services-table thead {
                display: table-header-group !important;
            }
            .services-table, .services-table tbody, .services-table tr, .services-table td {
                display: revert !important;
            }
            .services-table td::before {
                display: none !important;
            }
            .service-desc-cell .quote-table-wrapper {
                border: 1px solid #cbd5e1 !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
            .service-desc-cell .quote-modern-table th {
                background: #f1f5f9 !important;
                color: #0f172a !important;
                border-bottom: 1px solid #cbd5e1 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .service-desc-cell .quote-modern-table td {
                color: #1e293b !important;
                border-bottom: 1px solid #e2e8f0 !important;
            }
        }
    </style>
    <script>
        (function() {
            var theme = localStorage.getItem('quote_theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<div class="container">
    <!-- Executive Top Action Bar -->
    <div class="top-action-bar">
        <div class="top-bar-branding">
            <span class="top-folio-badge">
                <span class="folio-dot"></span>
                COTIZACIÓN #<?php echo str_pad($quote['id'], 4, '0', STR_PAD_LEFT); ?>
            </span>
            <?php if (!empty($quote['client_name'])): ?>
                <span class="top-client-name">&bull; <?php echo htmlspecialchars($quote['client_name']); ?></span>
            <?php endif; ?>
        </div>
        <div class="actions-right">
            <button class="btn-theme-switch" id="themeToggle" title="Cambiar tema (Claro / Oscuro)">
                <i class="ph ph-moon" id="themeIconDark"></i>
                <i class="ph ph-sun" id="themeIconLight" style="display:none;"></i>
            </button>
            <button onclick="window.print()" class="btn-action-print" title="Imprimir o guardar como PDF">
                <i class="ph ph-printer"></i>
                <span class="btn-text-full">Imprimir / Descargar PDF</span>
                <span class="btn-text-mobile" style="display:none;">PDF</span>
            </button>
            <?php if (strtolower($quote['status']) !== 'aceptada'): ?>
                <button type="button" class="btn-action-approve" id="topApproveBtn" onclick="openApproveModal()">
                    <i class="ph-bold ph-check-circle"></i>
                    <span class="btn-text-full">Aprobar Propuesta</span>
                    <span class="btn-text-mobile" style="display:none;">Aprobar</span>
                </button>
            <?php else: ?>
                <div class="badge-approved-status">
                    <i class="ph-fill ph-check-circle"></i> Aprobada
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Document -->
    <div class="document-card">
        <?php if ($has_cover): ?>
        <div class="doc-cover-banner" style="<?php echo $cover_css; ?>">
            <div class="cover-banner-overlay"></div>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="doc-header">
            <div class="company-brand">
                <?php if(!empty($settings['logo_light']) && !empty($settings['logo_dark'])): ?>
                    <img src="<?php echo htmlspecialchars($base_url . ltrim($settings['logo_light'], '/')); ?>" class="company-logo-img logo-light" alt="Logo">
                    <img src="<?php echo htmlspecialchars($base_url . ltrim($settings['logo_dark'], '/')); ?>" class="company-logo-img logo-dark" alt="Logo">
                <?php elseif(!empty($settings['logo_light'])): ?>
                    <img src="<?php echo htmlspecialchars($base_url . ltrim($settings['logo_light'], '/')); ?>" class="company-logo-img" alt="Logo">
                <?php else: ?>
                    <div class="company-fallback-logo">
                        <i class="ph ph-file-text"></i>
                        <span><?php echo htmlspecialchars($settings['site_name'] ?? 'Empresa'); ?></span>
                    </div>
                <?php endif; ?>

                <div class="company-info-list">
                    <span class="company-name-title"><?php echo htmlspecialchars($settings['company_trade_name'] ?? $settings['site_name'] ?? ''); ?></span>
                    <?php if(!empty($settings['company_ruc'])): ?>
                        <span class="company-info-item"><i class="ph ph-identification-card"></i> RUC: <?php echo htmlspecialchars($settings['company_ruc']); ?></span>
                    <?php endif; ?>
                    <?php if(!empty($settings['company_address'])): ?>
                        <span class="company-info-item"><i class="ph ph-map-pin"></i> <?php echo htmlspecialchars($settings['company_address']); ?></span>
                    <?php endif; ?>
                    <?php if(!empty($settings['company_email'])): ?>
                        <span class="company-info-item"><i class="ph ph-envelope"></i> <?php echo htmlspecialchars($settings['company_email']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="doc-quote-meta">
                <span class="quote-badge-tag">Cotización Comercial</span>
                <div class="doc-quote-number">#<?php echo str_pad($quote['id'], 4, '0', STR_PAD_LEFT); ?></div>
                <?php if (!empty($quote['status'])): ?>
                    <span class="status-pill status-<?php echo strtolower($quote['status']); ?>">
                        <span class="pulsing-dot"></span>
                        <?php echo htmlspecialchars($quote['status']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Meta Details Strip -->
        <div class="meta-strip">
            <div class="meta-item-box">
                <div class="meta-icon-tile"><i class="ph ph-user"></i></div>
                <div class="meta-text-group">
                    <span class="meta-item-label">Preparado para</span>
                    <span class="meta-item-value"><?php echo htmlspecialchars($quote['client_name'] ?? 'Cliente'); ?></span>
                    <?php if(!empty($quote['document_number'])): ?>
                        <span class="meta-item-sub">Doc: <?php echo htmlspecialchars($quote['document_number']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="meta-item-box">
                <div class="meta-icon-tile"><i class="ph ph-calendar-check"></i></div>
                <div class="meta-text-group">
                    <span class="meta-item-label">Fecha de Emisión</span>
                    <span class="meta-item-value"><?php echo date('d M, Y', strtotime($quote['issue_date'])); ?></span>
                    <span class="meta-item-sub">Validez estándar</span>
                </div>
            </div>
            <div class="meta-item-box">
                <div class="meta-icon-tile"><i class="ph ph-clock"></i></div>
                <div class="meta-text-group">
                    <span class="meta-item-label">Fecha de Vencimiento</span>
                    <span class="meta-item-value"><?php echo date('d M, Y', strtotime($quote['due_date'])); ?></span>
                    <span class="meta-item-sub">Válido hasta las 23:59</span>
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="doc-body">
            <!-- Table of Items -->
            <div class="table-responsive-wrap">
                <table class="services-table <?php echo $hide_prices ? 'hide-prices-table' : ''; ?>">
                    <thead>
                        <tr>
                            <th class="col-desc" <?php echo $hide_prices ? 'style="width: 100%;"' : ''; ?>>Descripción del Servicio</th>
                            <?php if(!$hide_prices): ?>
                                <th class="col-qty">Cant.</th>
                                <th class="col-price">Precio Unit.</th>
                                <th class="col-total">Importe</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $i): ?>
                        <tr>
                            <td class="service-desc-cell <?php echo $hide_prices ? 'col-full-width' : ''; ?>" data-label="Servicio" <?php echo $hide_prices ? 'style="width: 100%; max-width: 100%;"' : ''; ?>>
                                <?php echo strip_tags($i['description'], '<strong><em><b><i><u><br><ul><ol><li><p><span><font><table><thead><tbody><tfoot><tr><th><td><div><style><svg>'); ?>
                            </td>
                            <?php if(!$hide_prices): ?>
                                <td class="col-qty" data-label="Cantidad">
                                    <?php echo (float)$i['quantity']; ?>
                                </td>
                                <td class="col-price" data-label="Precio Unit.">
                                    <div><?php echo $sym . ' ' . number_format($i['unit_price'], 2); ?></div>
                                    <?php if($i['discount'] > 0): ?>
                                        <div class="discount-tag">-<?php echo $sym . ' ' . number_format($i['discount'], 2); ?> desc.</div>
                                    <?php endif; ?>
                                </td>
                                <td class="col-total" data-label="Importe">
                                    <span class="amount-highlight"><?php echo $sym . ' ' . number_format($i['total'], 2); ?></span>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if(!$hide_prices): ?>
            <!-- Calculation Totals -->
            <div class="totals-summary-card">
                <div class="calc-row">
                    <span class="calc-row-label">Subtotal</span>
                    <span class="calc-row-val"><?php echo $sym . ' ' . number_format($quote['subtotal'], 2); ?></span>
                </div>
                <div class="calc-row">
                    <span class="calc-row-label">IGV / Impuestos (<?php echo $quote['subtotal'] > 0 ? (int)(($quote['tax']/$quote['subtotal'])*100) : 0; ?>%)</span>
                    <span class="calc-row-val"><?php echo $sym . ' ' . number_format($quote['tax'], 2); ?></span>
                </div>
                <div class="calc-divider"></div>
                <div class="calc-row total-row">
                    <span class="calc-row-label">TOTAL</span>
                    <span class="calc-row-val"><?php echo $sym . ' ' . number_format($quote['total'], 2); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Modern Sequential Execution Roadmap (Timeline) -->
            <?php if (!empty($timeline_phases)): ?>
            <div class="section-block" id="ganttSection">
                <div class="timeline-header-wrap">
                    <div>
                        <h3 class="section-header-title">
                            <i class="ph ph-chart-line-up"></i>
                            Cronograma de Ejecución y Entregables
                        </h3>
                        <p class="section-header-sub">Secuencia estimada de fases desde el inicio hasta la culminación del proyecto.</p>
                    </div>
                    <div class="timeline-meta-badges">
                        <span class="timeline-badge-duration">
                            <i class="ph ph-clock-countdown"></i> <?php echo $total_project_days; ?> días de ejecución total
                        </span>
                        <?php if ($project_start_date && $project_end_date): ?>
                        <span class="timeline-badge-range">
                            <i class="ph ph-calendar"></i> <?php echo (new DateTime($project_start_date))->format('d M'); ?> &rarr; <?php echo (new DateTime($project_end_date))->format('d M, Y'); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="roadmap-phases-container">
                    <?php foreach ($timeline_phases as $p): ?>
                    <div class="roadmap-phase-card">
                        <div class="phase-left-milestone">
                            <div class="phase-number-chip"><?php echo str_pad($p['num'], 2, '0', STR_PAD_LEFT); ?></div>
                            <?php if ($p['num'] < count($timeline_phases)): ?>
                                <div class="phase-connector-line"></div>
                            <?php endif; ?>
                        </div>
                        <div class="phase-card-body">
                            <div class="phase-header-row">
                                <h4 class="phase-title"><?php echo htmlspecialchars($p['title']); ?></h4>
                                <span class="phase-duration-tag">
                                    <i class="ph ph-timer"></i> <?php echo $p['duration']; ?> <?php echo $p['duration'] == 1 ? 'día' : 'días'; ?>
                                </span>
                            </div>
                            <div class="phase-dates-flow">
                                <div class="date-step start-date">
                                    <i class="ph ph-calendar-plus"></i>
                                    <span class="date-label">Inicio:</span>
                                    <strong><?php echo $p['start_formatted']; ?></strong>
                                </div>
                                <div class="date-flow-arrow">
                                    <i class="ph ph-arrow-right"></i>
                                </div>
                                <div class="date-step end-date">
                                    <i class="ph ph-flag-banner"></i>
                                    <span class="date-label">Culminación:</span>
                                    <strong><?php echo $p['end_formatted']; ?></strong>
                                </div>
                            </div>
                            <!-- Proportional visual timeline bar -->
                            <div class="phase-progress-track">
                                <?php
                                    $offset_pct = ($total_project_days > 0 && $project_start_date) ? max(0, min(95, round(((new DateTime($p['start']))->diff(new DateTime($project_start_date))->days / $total_project_days) * 100))) : 0;
                                    $width_pct = $total_project_days > 0 ? max(6, min(100 - $offset_pct, round(($p['duration'] / $total_project_days) * 100))) : 100;
                                ?>
                                <div class="phase-progress-bar" style="margin-left: <?php echo $offset_pct; ?>%; width: <?php echo $width_pct; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Modern Corporate Payment Methods Hub -->
            <?php if(!empty($quote['show_payment_methods'])): ?>
            <div class="section-block">
                <div class="payment-section-header">
                    <div>
                        <h3 class="section-header-title">
                            <i class="ph ph-credit-card"></i>
                            Cuentas y Métodos de Pago
                        </h3>
                        <p class="section-header-sub">Canales bancarios y billeteras digitales autorizadas para la formalización del abono.</p>
                    </div>
                    <div class="payment-security-badge">
                        <i class="ph-bold ph-shield-check"></i>
                        <span>Cuentas Verificadas</span>
                    </div>
                </div>

                <div class="payment-grid-modern">
                    <?php if(!empty($pm_lines)): ?>
                        <?php foreach($pm_lines as $line): ?>
                            <?php 
                                $line = trim($line);
                                if(!$line) continue;
                                
                                $parts = explode(':', $line, 2);
                                $bName = count($parts) > 1 ? trim($parts[0]) : 'Cuenta Bancaria';
                                $bNum = count($parts) > 1 ? trim($parts[1]) : trim($parts[0]);
                                
                                $lowerName = strtolower($bName);
                                $isYapePlin = strpos($lowerName, 'yape') !== false || strpos($lowerName, 'plin') !== false;
                                $isCci = strpos($lowerName, 'cci') !== false;
                                $isUsd = strpos($lowerName, 'dólar') !== false || strpos($lowerName, 'dolar') !== false || strpos($lowerName, 'usd') !== false || strpos($lowerName, '$') !== false;

                                $iconClass = $isYapePlin ? 'ph-device-mobile' : ($isCci ? 'ph-arrows-left-right' : 'ph-bank');
                                $tagText = $isYapePlin ? 'Billetera Digital' : ($isUsd ? 'Dólares ($)' : ($isCci ? 'Interbancario' : 'Soles (S/)'));
                            ?>
                            <div class="payment-card-modern <?php echo $isYapePlin ? 'card-wallet' : ''; ?>">
                                <div class="card-top-row">
                                    <div class="bank-identity">
                                        <div class="bank-avatar">
                                            <i class="ph <?php echo $iconClass; ?>"></i>
                                        </div>
                                        <div class="bank-titles">
                                            <span class="bank-name-label"><?php echo htmlspecialchars($bName); ?></span>
                                            <span class="bank-type-pill"><?php echo $tagText; ?></span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-copy-account-modern" onclick="copyNumber('<?php echo htmlspecialchars(addslashes($bNum)); ?>')" title="Copiar número de cuenta">
                                        <i class="ph ph-copy"></i>
                                        <span class="copy-label">Copiar</span>
                                    </button>
                                </div>
                                <div class="account-number-box">
                                    <span class="account-code-value"><?php echo htmlspecialchars($bNum); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Default Bank Accounts if none custom -->
                        <div class="payment-card-modern">
                            <div class="card-top-row">
                                <div class="bank-identity">
                                    <div class="bank-avatar"><i class="ph ph-bank"></i></div>
                                    <div class="bank-titles">
                                        <span class="bank-name-label">BCP Soles</span>
                                        <span class="bank-type-pill">Soles (S/)</span>
                                    </div>
                                </div>
                                <button type="button" class="btn-copy-account-modern" onclick="copyNumber('191-74092813-0-24')" title="Copiar">
                                    <i class="ph ph-copy"></i> <span class="copy-label">Copiar</span>
                                </button>
                            </div>
                            <div class="account-number-box">
                                <span class="account-code-value">191-74092813-0-24</span>
                            </div>
                        </div>
                        <div class="payment-card-modern card-wallet">
                            <div class="card-top-row">
                                <div class="bank-identity">
                                    <div class="bank-avatar"><i class="ph ph-device-mobile"></i></div>
                                    <div class="bank-titles">
                                        <span class="bank-name-label">Yape / Plin</span>
                                        <span class="bank-type-pill">Billetera Móvil</span>
                                    </div>
                                </div>
                                <button type="button" class="btn-copy-account-modern" onclick="copyNumber('998289752')" title="Copiar">
                                    <i class="ph ph-copy"></i> <span class="copy-label">Copiar</span>
                                </button>
                            </div>
                            <div class="account-number-box">
                                <span class="account-code-value">998 289 752</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="payment-instructions-footer">
                    <i class="ph ph-info"></i>
                    <span>Una vez realizada la transferencia o depósito, remite tu constancia al correo de facturación indicando la <strong>Cotización #<?php echo str_pad($quote['id'], 4, '0', STR_PAD_LEFT); ?></strong>.</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes & Terms -->
            <?php if(!empty(trim($quote['notes'] ?? '')) || !empty(trim($quote['terms_conditions'] ?? ''))): ?>
            <div class="section-block">
                <div class="notes-grid">
                    <?php if(!empty(trim($quote['notes'] ?? ''))): ?>
                    <div class="note-card">
                        <span class="note-card-title"><i class="ph ph-notepad"></i> Notas Adicionales</span>
                        <div class="note-card-body"><?php echo htmlspecialchars($quote['notes']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty(trim($quote['terms_conditions'] ?? ''))): ?>
                    <div class="note-card">
                        <span class="note-card-title"><i class="ph ph-file-text"></i> Términos y Condiciones</span>
                        <div class="note-card-body"><?php echo htmlspecialchars($quote['terms_conditions']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Bottom Approval CTA Banner -->
        <?php if (strtolower($quote['status']) !== 'aceptada'): ?>
        <div class="approval-cta-banner" id="bottomApprovalBanner">
            <div class="cta-banner-text">
                <h3>¿Listo para dar inicio a este proyecto?</h3>
                <p>Aprueba la propuesta comercial para formalizar la orden de trabajo y coordinar el inicio de inmediato.</p>
            </div>
            <button type="button" class="btn-action-approve" onclick="openApproveModal()">
                <i class="ph-bold ph-check-circle"></i>
                <span>Aprobar Propuesta Comercial</span>
            </button>
        </div>
        <?php else: ?>
        <div class="approval-success-banner" id="bottomApprovalSuccess">
            <i class="ph-fill ph-check-circle" style="font-size: 2rem; color: #10b981;"></i>
            <div>
                <h4 style="margin: 0; font-size: 1.1rem; color: #10b981; font-weight: 700;">Propuesta Aprobada Formalmente</h4>
                <p style="margin: 2px 0 0; font-size: 0.85rem; color: var(--text-muted);">Esta cotización se encuentra en proceso de ejecución y seguimiento operativo.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="doc-footer">
            <span>Generado con tecnología RomaAgencia SaaS &bull; Confidencial</span>
        </div>
    </div>
</div>

<!-- Copy Toast Notification -->
<div class="copy-toast" id="copyToast">
    <i class="ph ph-check-circle" style="color:#10b981; font-size:1.2rem;"></i>
    <span id="copyToastMsg">Copiado al portapapeles</span>
</div>

<!-- Modal Aprobar Propuesta -->
<div class="approve-modal-backdrop" id="approveModalBackdrop" style="display:none;" onclick="if(event.target === this) closeApproveModal()">
    <div class="approve-modal-card">
        <div class="approve-modal-header">
            <div class="approve-icon-wrap">
                <i class="ph-bold ph-handshake"></i>
            </div>
            <div>
                <h3 style="margin:0; font-size:1.15rem; font-weight:700; color:var(--text-main);">Aprobar Propuesta Comercial</h3>
                <p style="margin:4px 0 0; font-size:0.83rem; color:var(--text-muted);">Cotización #<?php echo str_pad($quote['id'], 4, '0', STR_PAD_LEFT); ?> &bull; <?php echo htmlspecialchars($quote['client_name'] ?? ''); ?></p>
            </div>
            <button type="button" class="btn-close-modal" onclick="closeApproveModal()"><i class="ph ph-x"></i></button>
        </div>
        <form id="approveQuoteForm" onsubmit="submitApproval(event)">
            <div class="approve-modal-body">
                <?php if(!$hide_prices): ?>
                <div class="approve-summary-box">
                    <div class="summary-label">Monto Total Acordado</div>
                    <div class="summary-total"><?php echo htmlspecialchars($quote['currency']); ?> <?php echo number_format($quote['total'], 2); ?></div>
                </div>
                <?php else: ?>
                <div class="approve-summary-box">
                    <div class="summary-label">Propuesta de Alcance y Servicios</div>
                    <div class="summary-total" style="font-size: 1.05rem; color: var(--primary);">Conforme a Especificaciones</div>
                </div>
                <?php endif; ?>

                <div style="margin-bottom: 1rem;">
                    <label style="display:block; font-size:0.84rem; font-weight:600; margin-bottom:6px; color:var(--text-main);">Nombre del Representante o Aprobador *</label>
                    <input type="text" id="approve_signer_name" class="approve-input" placeholder="Ej: Carlos Mendoza" required value="<?php echo htmlspecialchars($quote['client_name'] ?? ''); ?>">
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display:block; font-size:0.84rem; font-weight:600; margin-bottom:6px; color:var(--text-main);">DNI / RUC o Documento de Identidad (Opcional)</label>
                    <input type="text" id="approve_signer_doc" class="approve-input" placeholder="Ej: 72819203">
                </div>

                <label class="approve-checkbox-wrap">
                    <input type="checkbox" id="approve_terms_check" required checked>
                    <span>Confirmo la aceptación de los servicios<?php echo !$hide_prices ? ', montos' : ''; ?> y condiciones detallados en esta propuesta comercial.</span>
                </label>
            </div>
            <div class="approve-modal-footer">
                <button type="button" class="btn-approve-cancel" onclick="closeApproveModal()">Cancelar</button>
                <button type="submit" class="btn-approve-confirm" id="btnConfirmApprove">
                    <i class="ph-bold ph-check"></i> Confirmar y Aprobar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const itemsData = <?php echo json_encode($items); ?>;
    const publicQuoteToken = <?php echo json_encode($token); ?>;

    function openApproveModal() {
        document.getElementById('approveModalBackdrop').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeApproveModal() {
        document.getElementById('approveModalBackdrop').style.display = 'none';
        document.body.style.overflow = '';
    }

    function submitApproval(event) {
        event.preventDefault();
        const btn = document.getElementById('btnConfirmApprove');
        const signerName = document.getElementById('approve_signer_name').value.trim();
        const signerDoc = document.getElementById('approve_signer_doc').value.trim();

        if (!signerName) {
            alert('Por favor ingresa tu nombre para confirmar la aprobación.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Procesando aprobación...';

        const payload = new URLSearchParams();
        payload.append('token', publicQuoteToken);
        payload.append('signer_name', signerName);
        payload.append('signer_document', signerDoc);

        fetch('modules/quotes/ajax_public_approve.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeApproveModal();
                showToast('¡Propuesta aprobada con éxito!');
                
                // Actualizar UI
                const topBtn = document.getElementById('topApproveBtn');
                if (topBtn) {
                    topBtn.outerHTML = '<div class="badge-approved-status"><i class="ph-fill ph-check-circle"></i> Aprobada</div>';
                }
                const btmBanner = document.getElementById('bottomApprovalBanner');
                if (btmBanner) {
                    btmBanner.outerHTML = `
                        <div class="approval-success-banner" id="bottomApprovalSuccess">
                            <i class="ph-fill ph-check-circle" style="font-size: 2rem; color: #10b981;"></i>
                            <div>
                                <h4 style="margin: 0; font-size: 1.1rem; color: #10b981; font-weight: 700;">¡Propuesta Aprobada Formalmente!</h4>
                                <p style="margin: 2px 0 0; font-size: 0.85rem; color: var(--text-muted);">Muchas gracias por tu confianza. Nuestro equipo ha sido notificado y se pondrá en contacto a la brevedad.</p>
                            </div>
                        </div>
                    `;
                }
                // Actualizar pill de estado en encabezado
                document.querySelectorAll('.status-pill').forEach(pill => {
                    pill.className = 'status-pill status-aceptada';
                    pill.textContent = 'Aceptada';
                });
            } else {
                alert(data.message || 'Ocurrió un error al procesar la aprobación.');
                btn.disabled = false;
                btn.innerHTML = '<i class="ph-bold ph-check"></i> Confirmar y Aprobar';
            }
        })
        .catch(err => {
            alert('Error de conexión con el servidor. Intenta de nuevo.');
            btn.disabled = false;
            btn.innerHTML = '<i class="ph-bold ph-check"></i> Confirmar y Aprobar';
        });
    }
    
    // Setup responsive card view for sub-tables embedded in service descriptions
    function setupResponsiveCardsForTables() {
        const descCells = document.querySelectorAll('.service-desc-cell');
        descCells.forEach(cell => {
            const tables = cell.querySelectorAll('table');
            tables.forEach(table => {
                let headers = [];
                const ths = table.querySelectorAll('thead th, tr:first-child th');
                if (ths.length > 0) {
                    ths.forEach(th => headers.push(th.innerText.trim()));
                }
                
                const rows = table.querySelectorAll('tbody tr, tr:not(:first-child)');
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    cells.forEach((td, idx) => {
                        const label = headers[idx] || ('Columna ' + (idx + 1));
                        td.setAttribute('data-label', label);
                    });
                });
            });
        });
    }
    setupResponsiveCardsForTables();

    // Theme Switch
    const themeBtn = document.getElementById('themeToggle');
    const iconDark = document.getElementById('themeIconDark');
    const iconLight = document.getElementById('themeIconLight');

    function applyThemeIcons() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        iconDark.style.display = isDark ? 'none' : 'inline';
        iconLight.style.display = isDark ? 'inline' : 'none';
    }
    applyThemeIcons();

    themeBtn.addEventListener('click', function() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('quote_theme', 'light');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('quote_theme', 'dark');
        }
        applyThemeIcons();
    });

    // Copy to clipboard helper
    function copyNumber(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Cuenta copiada: ' + text);
        }).catch(() => {
            showToast('Error al copiar');
        });
    }

    function showToast(msg) {
        const toast = document.getElementById('copyToast');
        const msgEl = document.getElementById('copyToastMsg');
        msgEl.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 2500);
    }
</script>

</body>
</html>
