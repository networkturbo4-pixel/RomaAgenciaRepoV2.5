<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT pm.id, p.id as project_id, w.brand_name as name, pm.month, pm.year, p.team_members FROM project_months pm JOIN projects p ON pm.project_id = p.id JOIN work_orders w ON p.work_order_id = w.id WHERE p.status = 'active' ORDER BY pm.id DESC");
if (!$stmt) {
    print_r($db->errorInfo());
} else {
    echo "OK";
}
