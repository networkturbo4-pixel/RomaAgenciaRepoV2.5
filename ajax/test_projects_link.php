<?php
require '../config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('SELECT p.id as project_id, pm.id as month_id, w.brand_name 
                    FROM projects p 
                    JOIN work_orders w ON p.work_order_id = w.id
                    LEFT JOIN project_months pm ON p.id = pm.project_id
                    ORDER BY pm.id DESC
                    LIMIT 5');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
