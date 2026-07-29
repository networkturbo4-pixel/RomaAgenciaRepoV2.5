<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit();
}

$db = (new Database())->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $stmt = $db->query("SELECT id, title, slug, profile_image, views, is_active FROM linktrees ORDER BY id DESC");
            $linktrees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $linktrees]);
            break;

        case 'get':
            $id = $_GET['id'] ?? 0;
            $stmt = $db->prepare("SELECT * FROM linktrees WHERE id = ?");
            $stmt->execute([$id]);
            $linktree = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($linktree) {
                $stmtLinks = $db->prepare("SELECT * FROM linktree_links WHERE linktree_id = ? ORDER BY sort_order ASC");
                $stmtLinks->execute([$id]);
                $rawLinks = $stmtLinks->fetchAll(PDO::FETCH_ASSOC);
                $linktree['links'] = array_map(function($l) {
                    if(!empty($l['meta_data'])) $l['meta_data'] = json_decode($l['meta_data'], true);
                    return $l;
                }, $rawLinks);
                
                if($linktree['theme_config']) {
                    $linktree['theme_config'] = json_decode($linktree['theme_config'], true);
                }
                echo json_encode(['success' => true, 'data' => $linktree]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No encontrado']);
            }
            break;

        case 'save':
            $id = $_POST['id'] ?? 0;
            $title = $_POST['title'] ?? '';
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['slug'] ?? '')));
            $bio = $_POST['bio'] ?? '';
            $theme_config = $_POST['theme_config'] ?? '{}';
            
            // Check slug unique
            if ($id) {
                $stmtCheck = $db->prepare("SELECT id FROM linktrees WHERE slug = ? AND id != ?");
                $stmtCheck->execute([$slug, $id]);
            } else {
                $stmtCheck = $db->prepare("SELECT id FROM linktrees WHERE slug = ?");
                $stmtCheck->execute([$slug]);
            }
            
            if ($stmtCheck->fetchColumn()) {
                echo json_encode(['success' => false, 'error' => 'El slug ya está en uso']);
                exit();
            }

            // Image Upload
            $profile_image = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
                $uploadDir = '../../uploads/linktrees/' . $slug . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $filename)) {
                    $profile_image = 'uploads/linktrees/' . $slug . '/' . $filename;
                }
            }

            if ($id) {
                // Update
                if ($profile_image) {
                    $stmt = $db->prepare("UPDATE linktrees SET title=?, slug=?, bio=?, theme_config=?, profile_image=? WHERE id=?");
                    $stmt->execute([$title, $slug, $bio, $theme_config, $profile_image, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE linktrees SET title=?, slug=?, bio=?, theme_config=? WHERE id=?");
                    $stmt->execute([$title, $slug, $bio, $theme_config, $id]);
                }
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO linktrees (user_id, title, slug, bio, theme_config, profile_image) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $title, $slug, $bio, $theme_config, $profile_image]);
                $id = $db->lastInsertId();
            }

            // Save Links
            $links_json = $_POST['links'] ?? '[]';
            $links = json_decode($links_json, true);
            
            // Delete existing links
            $stmtDel = $db->prepare("DELETE FROM linktree_links WHERE linktree_id = ?");
            $stmtDel->execute([$id]);

            if (is_array($links)) {
                $stmtInsertLink = $db->prepare("INSERT INTO linktree_links (linktree_id, title, url, sort_order, type, meta_data) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($links as $index => $link) {
                    $type = $link['type'] ?? 'link';
                    $meta_data = isset($link['meta_data']) ? json_encode($link['meta_data']) : null;
                    $stmtInsertLink->execute([$id, $link['title'], $link['url'] ?? '', $index, $type, $meta_data]);
                }
            }

            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'toggle_active':
            $id = $_POST['id'] ?? 0;
            $is_active = $_POST['is_active'] ?? 1;
            $stmt = $db->prepare("UPDATE linktrees SET is_active = ? WHERE id = ?");
            $stmt->execute([$is_active, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? 0;
            $stmt = $db->prepare("DELETE FROM linktrees WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción desconocida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
