<?php
// modules/admin/ajax_get_employees.php
// DB connection is handled by index.php

try {
    $stmt = $db->query("SELECT * FROM employees ORDER BY created_at DESC");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate initials and color dynamically
    $colors = ['#3b82f6', '#1e40af', '#475569', '#2563eb', '#1d4ed8', '#1e3a8a', '#10b981', '#059669', '#8b5cf6'];
    foreach ($employees as &$emp) {
        $parts = explode(' ', $emp['name']);
        $initials = '';
        if (count($parts) >= 2) {
            $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        } else {
            $initials = strtoupper(substr($emp['name'], 0, 2));
        }
        $emp['initials'] = $initials;
        
        // Consistent color based on ID
        $emp['color'] = $colors[$emp['id'] % count($colors)];
    }

    echo json_encode(['success' => true, 'data' => $employees]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
