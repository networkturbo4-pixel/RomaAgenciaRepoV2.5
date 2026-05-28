<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $project_id = isset($_GET['project_id']) && $_GET['project_id'] !== 'all' ? (int)$_GET['project_id'] : 'all';
    $month_id = isset($_GET['month_id']) && $_GET['month_id'] !== 'all' ? (int)$_GET['month_id'] : 'all';
    
    // We only fetch posts for active projects
    $query = "
        SELECT mp.*, w.brand_name 
        FROM month_posts mp
        JOIN project_months pm ON mp.month_id = pm.id
        JOIN projects p ON pm.project_id = p.id
        JOIN work_orders w ON p.work_order_id = w.id
        WHERE p.status = 'active'
    ";
    
    $params = [];
    if ($month_id !== 'all') {
        $query .= " AND pm.id = ?";
        $params[] = $month_id;
    } else if ($project_id !== 'all') {
        $query .= " AND p.id = ?";
        $params[] = $project_id;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statusColors = [
        'Borrador' => '#64748b',
        'En Revisión' => '#d97706',
        'Aprobado' => '#2563eb',
        'Publicado' => '#059669',
    ];

    $events = [];
    foreach ($posts as $post) {
        $color = $statusColors[$post['status']] ?? '#4f46e5';
        $platformIcons = [
            'Facebook' => '📘',
            'Instagram' => '📷',
            'TikTok' => '🎵',
            'LinkedIn' => '💼',
            'Twitter / X' => '🐦'
        ];
        $icon = $platformIcons[$post['platform']] ?? '🔗';
        
        // If a specific project is selected, no need to show brand_name in the title
        if ($project_id !== 'all') {
            $title = $icon . ' ' . $post['concept'];
        } else {
            $title = $icon . ' ' . $post['brand_name'] . ': ' . $post['concept'];
        }

        // Get thumbnail image
        $mediaStr = $post['post_type'] === 'Referencia Visual' ? $post['reference_image_link'] : $post['image_link'];
        $mediaList = json_decode($mediaStr, true);
        if (!is_array($mediaList) && !empty($mediaStr)) {
            $mediaList = [$mediaStr];
        }
        $thumbnailUrl = !empty($mediaList) ? $mediaList[0] : '';
        $isVideo = false;
        if (strpos($thumbnailUrl, '.mp4') !== false || strpos($thumbnailUrl, 'drive.google.com') !== false) {
            $isVideo = true;
            $thumbnailUrl = ''; // We can't show video directly as img easily without a poster
        }

        $events[] = [
            'id' => $post['id'],
            'title' => $title,
            'start' => $post['post_date'],
            'backgroundColor' => 'transparent', // We'll handle color in eventContent
            'borderColor' => 'transparent',
            'extendedProps' => [
                'month_id' => $post['month_id'],
                'platform' => $post['platform'],
                'status' => $post['status'],
                'thumbnail' => $thumbnailUrl,
                'isVideo' => $isVideo,
                'statusColor' => $color,
                'isReference' => ($post['post_type'] === 'Referencia Visual')
            ]
        ];
    }

    echo json_encode(['success' => true, 'events' => $events]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
}
?>
