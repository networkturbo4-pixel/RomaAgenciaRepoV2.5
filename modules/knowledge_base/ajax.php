<?php
// modules/knowledge_base/ajax.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once 'config/database.php';
$db = (new Database())->getConnection();

$action_type = $_POST['action_type'] ?? $_GET['action_type'] ?? '';

if ($action_type === 'vote_feedback') {
    $article_id = (int)($_POST['article_id'] ?? 0);
    $vote_type = $_POST['vote_type'] ?? '';

    if (!$article_id || !in_array($vote_type, ['yes', 'no'])) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        exit();
    }

    $session_key = 'kb_voted_' . $article_id;
    if (isset($_SESSION[$session_key])) {
        // Already voted, just fetch current counts
        $stmt = $db->prepare("SELECT helpful_yes, helpful_no FROM kb_articles WHERE id = ?");
        $stmt->execute([$article_id]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'already_voted' => true,
            'helpful_yes' => (int)$counts['helpful_yes'],
            'helpful_no' => (int)$counts['helpful_no']
        ]);
        exit();
    }

    if ($vote_type === 'yes') {
        $stmt = $db->prepare("UPDATE kb_articles SET helpful_yes = helpful_yes + 1 WHERE id = ?");
    } else {
        $stmt = $db->prepare("UPDATE kb_articles SET helpful_no = helpful_no + 1 WHERE id = ?");
    }
    $stmt->execute([$article_id]);
    $_SESSION[$session_key] = $vote_type;

    $stmtCount = $db->prepare("SELECT helpful_yes, helpful_no FROM kb_articles WHERE id = ?");
    $stmtCount->execute([$article_id]);
    $counts = $stmtCount->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'helpful_yes' => (int)$counts['helpful_yes'],
        'helpful_no' => (int)$counts['helpful_no']
    ]);
    exit();
}

function kb_check_admin($db) {
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) return false;
    $stmt_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmt_admin->execute([$user_id]);
    $current_role_id = (int)$stmt_admin->fetchColumn();
    $session_role = $_SESSION['user_role'] ?? null;
    return ($current_role_id === 1 || $session_role == 1 || $session_role === 'Administrador');
}

if ($action_type === 'delete_article') {
    if (!kb_check_admin($db)) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit();
    }

    $article_id = (int)($_POST['article_id'] ?? 0);
    if (!$article_id) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit();
    }

    $stmt = $db->prepare("DELETE FROM kb_articles WHERE id = ?");
    $stmt->execute([$article_id]);

    echo json_encode(['success' => true]);
    exit();
}

if ($action_type === 'save_category') {
    if (!kb_check_admin($db)) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit();
    }

    require_once 'modules/knowledge_base/helpers.php';
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $color = trim($_POST['color'] ?? '#4f46e5');
    $icon = trim($_POST['icon'] ?? 'ph-book-open');

    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'El nombre de la categoría es obligatorio']);
        exit();
    }

    $slug = kb_slugify($name);

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE kb_categories SET name = ?, slug = ?, description = ?, color = ?, icon = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $description, $color, $icon, $id]);
        $cat_id = $id;
    } else {
        $stmt = $db->prepare("INSERT INTO kb_categories (name, slug, description, color, icon) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $description, $color, $icon]);
        $cat_id = (int)$db->lastInsertId();
    }

    $stmtCats = $db->query("
        SELECT c.*, COUNT(a.id) as total_articles
        FROM kb_categories c
        LEFT JOIN kb_articles a ON c.id = a.category_id AND a.status = 'published'
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY c.order_index ASC, c.name ASC
    ");
    $allCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'cat_id' => $cat_id,
        'cat_name' => $name,
        'categories' => $allCats
    ]);
    exit();
}

if ($action_type === 'delete_category') {
    if (!kb_check_admin($db)) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit();
    }

    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit();
    }

    $stmtCount = $db->prepare("SELECT COUNT(*) FROM kb_articles WHERE category_id = ?");
    $stmtCount->execute([$id]);
    if ($stmtCount->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'No puedes eliminar una categoría que contiene artículos activos. Reasigna o elimina los artículos primero.']);
        exit();
    }

    $stmt = $db->prepare("DELETE FROM kb_categories WHERE id = ?");
    $stmt->execute([$id]);

    $stmtCats = $db->query("
        SELECT c.*, COUNT(a.id) as total_articles
        FROM kb_categories c
        LEFT JOIN kb_articles a ON c.id = a.category_id AND a.status = 'published'
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY c.order_index ASC, c.name ASC
    ");
    $allCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'categories' => $allCats]);
    exit();
}

if ($action_type === 'get_categories') {
    $stmtCats = $db->query("
        SELECT c.*, COUNT(a.id) as total_articles
        FROM kb_categories c
        LEFT JOIN kb_articles a ON c.id = a.category_id AND a.status = 'published'
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY c.order_index ASC, c.name ASC
    ");
    $allCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'categories' => $allCats]);
    exit();
}

if ($action_type === 'upload_image') {
    if (!kb_check_admin($db)) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit();
    }

    $uploadedUrls = [];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    $targetDir = 'uploads/kb/';

    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }

    // Multiple files
    if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $files = $_FILES['images'];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExts)) {
                    $newFilename = 'kb_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
                    $dest = $targetDir . $newFilename;
                    if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                        $uploadedUrls[] = $dest;
                    }
                }
            }
        }
    }

    // Single file
    if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExts)) {
            $newFilename = 'kb_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $dest = $targetDir . $newFilename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $uploadedUrls[] = $dest;
            }
        }
    }

    if (empty($uploadedUrls)) {
        echo json_encode(['success' => false, 'error' => 'No se pudo subir ningún archivo válido. Formatos permitidos: JPG, PNG, WEBP, GIF.']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'url' => $uploadedUrls[0],
        'urls' => $uploadedUrls
    ]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
exit();
