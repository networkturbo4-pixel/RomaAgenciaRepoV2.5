<?php
// modules/pizarras/ajax_search_pizarras.php
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$stmtRole = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmtRole->execute([$user_id]);
$role_id = $stmtRole->fetchColumn();
$is_admin = ($role_id == 1);

$query = trim($_GET['q'] ?? '');
$filter = trim($_GET['filter'] ?? 'all');
$tag = trim($_GET['tag'] ?? '');
$folder_id = isset($_GET['folder_id']) && $_GET['folder_id'] !== '' ? (int)$_GET['folder_id'] : null;
$sort = trim($_GET['sort'] ?? 'recent');

try {
    // 1. Filter whiteboards
    $where = ["(w.created_by = ? OR EXISTS(SELECT 1 FROM whiteboard_users wu WHERE wu.whiteboard_id = w.id AND wu.user_id = ?) OR ?)"];
    $params = [$user_id, $user_id, $is_admin ? 1 : 0];

    if ($folder_id) {
        $where[] = "w.folder_id = ?";
        $params[] = $folder_id;
    }

    if (!empty($query)) {
        $searchWildcard = '%' . $query . '%';
        $where[] = "(w.title LIKE ? OR w.tags LIKE ? OR f.name LIKE ? OR u.name LIKE ?)";
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
    }

    if ($filter === 'mine') {
        $where[] = "w.created_by = ?";
        $params[] = $user_id;
    } elseif ($filter === 'shared') {
        $where[] = "(w.created_by != ? AND EXISTS(SELECT 1 FROM whiteboard_users wu WHERE wu.whiteboard_id = w.id AND wu.user_id = ?))";
        $params[] = $user_id;
        $params[] = $user_id;
    } elseif ($filter === 'tagged') {
        $where[] = "(w.tags IS NOT NULL AND w.tags != '' AND w.tags != '[]')";
    }

    if (!empty($tag)) {
        $where[] = "w.tags LIKE ?";
        $params[] = '%"name":"' . $tag . '"%';
    }

    $whereClause = implode(" AND ", $where);

    $orderSql = 'ORDER BY w.updated_at DESC';
    if ($sort === 'oldest') {
        $orderSql = 'ORDER BY w.created_at ASC';
    } elseif ($sort === 'name_asc') {
        $orderSql = 'ORDER BY w.title ASC';
    } elseif ($sort === 'name_desc') {
        $orderSql = 'ORDER BY w.title DESC';
    }

    $sql = "
        SELECT w.id, w.title, w.created_by, w.created_at, w.updated_at, w.folder_id, 
               w.tags, w.thumbnail, w.profile_pic, w.access_type, w.public_role,
               u.name as creator_name, u.avatar as creator_avatar,
               f.name as folder_name, f.color as folder_color,
               (SELECT COUNT(*) FROM whiteboard_users wu WHERE wu.whiteboard_id = w.id) as user_count
        FROM whiteboards w
        LEFT JOIN users u ON w.created_by = u.id
        LEFT JOIN whiteboard_folders f ON w.folder_id = f.id
        WHERE $whereClause
        $orderSql
        LIMIT 100
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $whiteboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Filter folders (only if not viewing a specific folder already)
    $folders = [];
    if (!$folder_id) {
        $folderWhere = [];
        $folderParams = [];

        if (!empty($query)) {
            $folderWhere[] = "f.name LIKE ?";
            $folderParams[] = '%' . $query . '%';
        }

        $folderWhereClause = !empty($folderWhere) ? 'WHERE ' . implode(' AND ', $folderWhere) : '';

        $stmtFolders = $db->prepare("
            SELECT f.id, f.name, f.color, COUNT(w.id) as board_count 
            FROM whiteboard_folders f 
            LEFT JOIN whiteboards w ON f.id = w.folder_id 
            $folderWhereClause
            GROUP BY f.id, f.name, f.color 
            ORDER BY f.name ASC
        ");
        $stmtFolders->execute($folderParams);
        $folders = $stmtFolders->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'whiteboards' => $whiteboards,
        'folders' => $folders,
        'total_boards' => count($whiteboards),
        'total_folders' => count($folders),
        'is_admin' => $is_admin,
        'current_user_id' => $user_id
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
