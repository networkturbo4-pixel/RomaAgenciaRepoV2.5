<?php
// ajax/ajax_get_board_templates_data.php
error_reporting(0);
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

try {
    $db = (new Database())->getConnection();

    // Get all brands with logo
    $brandsStmt = $db->query("SELECT id, name, logo FROM client_brands ORDER BY name ASC");
    $brands = $brandsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all active projects (new system)
    $projectsStmt = $db->query("SELECT id, brand_id, name FROM module_projects WHERE status = 'active' ORDER BY name ASC");
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all month boards with rich stats
    $monthsQuery = "
        SELECT 
            pm.id, 
            pm.project_id, 
            pm.month, 
            pm.year,
            pm.status as month_status,
            wo.brand_name,
            wo.correlativo,
            COALESCE((SELECT COUNT(*) FROM month_posts mp WHERE mp.month_id = pm.id), 0) as post_count,
            COALESCE((SELECT COUNT(*) FROM post_comments pc JOIN month_posts mp2 ON pc.post_id = mp2.id WHERE mp2.month_id = pm.id), 0) as comment_count,
            COALESCE((SELECT COUNT(*) FROM month_posts mp WHERE mp.month_id = pm.id AND mp.status = 'Borrador'), 0) as borrador_count,
            COALESCE((SELECT COUNT(*) FROM month_posts mp WHERE mp.month_id = pm.id AND (mp.status = 'En Revisión' OR mp.status = 'En Revision')), 0) as revision_count,
            COALESCE((SELECT COUNT(*) FROM month_posts mp WHERE mp.month_id = pm.id AND mp.status = 'Aprobado'), 0) as aprobado_count,
            COALESCE((SELECT COUNT(*) FROM month_posts mp WHERE mp.month_id = pm.id AND mp.status = 'Publicado'), 0) as publicado_count
        FROM project_months pm
        JOIN projects p ON pm.project_id = p.id
        JOIN work_orders wo ON p.work_order_id = wo.id
        ORDER BY pm.year DESC, pm.month DESC
    ";
    $monthsStmt = $db->query($monthsQuery);
    $months = $monthsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate progress for each month
    foreach ($months as &$m) {
        $total = intval($m['post_count']);
        $published = intval($m['publicado_count']);
        $m['progress'] = $total > 0 ? round(($published / $total) * 100) : 0;
    }
    unset($m);

    echo json_encode([
        'success' => true,
        'brands' => $brands,
        'projects' => $projects,
        'months' => $months
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
