<?php
// modules/services/ajax_save_service.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    
    // Phase 1
    $status = trim($_POST['status'] ?? 'active');
    $delivery_time = trim($_POST['delivery_time'] ?? '');
    $price_type = trim($_POST['price_type'] ?? 'fixed');
    $visibility = trim($_POST['visibility'] ?? 'public');
    
    // Phase 2
    $video_url = trim($_POST['video_url'] ?? '');
    
    // Phase 3
    $slug = trim($_POST['slug'] ?? '');
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');

    // Advanced Fields
    $icon = trim($_POST['icon'] ?? '');
    $badge = trim($_POST['badge'] ?? '');
    $currency = trim($_POST['currency'] ?? 'USD');
    $annual_discount_percent = isset($_POST['annual_discount_percent']) && $_POST['annual_discount_percent'] !== '' ? (float)$_POST['annual_discount_percent'] : null;
    $stock_limit = isset($_POST['stock_limit']) && $_POST['stock_limit'] !== '' ? (int)$_POST['stock_limit'] : null;
    $countdown_end = !empty($_POST['countdown_end']) ? date('Y-m-d H:i:s', strtotime($_POST['countdown_end'])) : null;
    $is_combo = (isset($_POST['is_combo']) && $_POST['is_combo'] == '1') ? 1 : 0;
    $combo_ids = isset($_POST['combo_ids']) && is_array($_POST['combo_ids']) ? json_encode($_POST['combo_ids']) : null;
    $has_addons = (isset($_POST['has_addons']) && $_POST['has_addons'] == '1') ? 1 : 0;
    
    // Cover Image Handling
    $existingCover = trim($_POST['existing_cover'] ?? '');
    $coverImage = $existingCover;

    if (empty($name) || $categoryId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
        exit;
    }

    try {
        $db->beginTransaction();

        // Process File Upload for Cover
        if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/services/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileInfo = pathinfo($_FILES['cover_file']['name']);
            $ext = strtolower($fileInfo['extension']);
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $fileName = uniqid('cover_') . '.' . $ext;
                if (move_uploaded_file($_FILES['cover_file']['tmp_name'], $uploadDir . $fileName)) {
                    $coverImage = $fileName;
                    if (!empty($existingCover) && file_exists($uploadDir . $existingCover)) {
                        unlink($uploadDir . $existingCover);
                    }
                }
            }
        }

        // Handle cover removal
        if (empty($existingCover) && !isset($_FILES['cover_file']['tmp_name']) && $id) {
            $stmtCover = $db->prepare("SELECT cover_image FROM services WHERE id = ?");
            $stmtCover->execute([$id]);
            $oldCover = $stmtCover->fetchColumn();
            if ($oldCover && file_exists('../../uploads/services/' . $oldCover)) {
                unlink('../../uploads/services/' . $oldCover);
            }
            $coverImage = '';
        }

        // Process File Upload for OG Image
        $existingOgImage = trim($_POST['existing_og_image'] ?? '');
        $ogImage = $existingOgImage;
        if (isset($_FILES['og_image_file']) && $_FILES['og_image_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/services/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileInfo = pathinfo($_FILES['og_image_file']['name']);
            $ext = strtolower($fileInfo['extension']);
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $fileName = uniqid('og_') . '.' . $ext;
                if (move_uploaded_file($_FILES['og_image_file']['tmp_name'], $uploadDir . $fileName)) {
                    $ogImage = $fileName;
                    if (!empty($existingOgImage) && file_exists($uploadDir . $existingOgImage)) {
                        unlink($uploadDir . $existingOgImage);
                    }
                }
            }
        }

        if ($id) {
            // Update
            $stmt = $db->prepare("
                UPDATE services 
                SET category_id = :category_id, name = :name, description = :description, price = :price,
                    status = :status, delivery_time = :delivery_time, price_type = :price_type, 
                    visibility = :visibility, cover_image = :cover_image, video_url = :video_url,
                    slug = :slug, meta_title = :meta_title, meta_description = :meta_description,
                    icon = :icon, badge = :badge, currency = :currency, 
                    annual_discount_percent = :annual_discount_percent, stock_limit = :stock_limit,
                    countdown_end = :countdown_end, is_combo = :is_combo, combo_ids = :combo_ids,
                    og_image = :og_image, has_addons = :has_addons
                WHERE id = :id
            ");
            $stmt->execute([
                ':category_id' => $categoryId,
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':status' => $status,
                ':delivery_time' => $delivery_time,
                ':price_type' => $price_type,
                ':visibility' => $visibility,
                ':cover_image' => $coverImage,
                ':video_url' => $video_url,
                ':slug' => $slug,
                ':meta_title' => $meta_title,
                ':meta_description' => $meta_description,
                ':icon' => $icon,
                ':badge' => $badge,
                ':currency' => $currency,
                ':annual_discount_percent' => $annual_discount_percent,
                ':stock_limit' => $stock_limit,
                ':countdown_end' => $countdown_end,
                ':is_combo' => $is_combo,
                ':combo_ids' => $combo_ids,
                ':og_image' => $ogImage,
                ':has_addons' => $has_addons,
                ':id' => $id
            ]);
            $serviceId = $id;
            $message = 'Servicio actualizado correctamente';
        } else {
            // Insert
            $stmt = $db->prepare("
                INSERT INTO services (category_id, name, description, price, status, delivery_time, price_type, visibility, cover_image, video_url, slug, meta_title, meta_description, icon, badge, currency, annual_discount_percent, stock_limit, countdown_end, is_combo, combo_ids, og_image, has_addons) 
                VALUES (:category_id, :name, :description, :price, :status, :delivery_time, :price_type, :visibility, :cover_image, :video_url, :slug, :meta_title, :meta_description, :icon, :badge, :currency, :annual_discount_percent, :stock_limit, :countdown_end, :is_combo, :combo_ids, :og_image, :has_addons)
            ");
            $stmt->execute([
                ':category_id' => $categoryId,
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':status' => $status,
                ':delivery_time' => $delivery_time,
                ':price_type' => $price_type,
                ':visibility' => $visibility,
                ':cover_image' => $coverImage,
                ':video_url' => $video_url,
                ':slug' => $slug,
                ':meta_title' => $meta_title,
                ':meta_description' => $meta_description,
                ':icon' => $icon,
                ':badge' => $badge,
                ':currency' => $currency,
                ':annual_discount_percent' => $annual_discount_percent,
                ':stock_limit' => $stock_limit,
                ':countdown_end' => $countdown_end,
                ':is_combo' => $is_combo,
                ':combo_ids' => $combo_ids,
                ':og_image' => $ogImage,
                ':has_addons' => $has_addons
            ]);
            $serviceId = $db->lastInsertId();
            $message = 'Servicio creado correctamente';
        }

        // ── Lists Handlers ── //
        
        // Features & Deliverables
        $db->prepare("DELETE FROM service_features WHERE service_id = :id")->execute([':id' => $serviceId]);
        if (!empty($_POST['features'])) {
            $features = json_decode($_POST['features'], true);
            $stmtFeat = $db->prepare("INSERT INTO service_features (service_id, title, description, type, sort_order, stage) VALUES (:service_id, :title, :description, :type, :sort_order, :stage)");
            foreach ($features as $f) {
                $stmtFeat->execute([
                    ':service_id' => $serviceId,
                    ':title' => $f['title'],
                    ':description' => $f['description'],
                    ':type' => $f['type'],
                    ':sort_order' => $f['sort_order'],
                    ':stage' => $f['stage'] ?? null
                ]);
            }
        }

        // Prereqs
        $prereqs = json_decode($_POST['prereqs'] ?? '[]', true);
        $db->prepare("DELETE FROM service_prerequisites WHERE service_id = :id")->execute([':id' => $serviceId]);
        if (!empty($prereqs)) {
            $stmtPre = $db->prepare("INSERT INTO service_prerequisites (service_id, title, description, sort_order) VALUES (:service_id, :title, :description, :sort_order)");
            foreach ($prereqs as $p) {
                $stmtPre->execute([':service_id' => $serviceId, ':title' => $p['title'], ':description' => $p['description'] ?? '', ':sort_order' => $p['sort_order'] ?? 0]);
            }
        }

        // FAQs
        $faqs = json_decode($_POST['faqs'] ?? '[]', true);
        $db->prepare("DELETE FROM service_faqs WHERE service_id = :id")->execute([':id' => $serviceId]);
        if (!empty($faqs)) {
            $stmtFaq = $db->prepare("INSERT INTO service_faqs (service_id, question, answer, sort_order) VALUES (:service_id, :question, :answer, :sort_order)");
            foreach ($faqs as $f) {
                $stmtFaq->execute([':service_id' => $serviceId, ':question' => $f['question'], ':answer' => $f['answer'] ?? '', ':sort_order' => $f['sort_order'] ?? 0]);
            }
        }

        // Packages
        $packages = json_decode($_POST['packages'] ?? '[]', true);
        $db->prepare("DELETE FROM service_packages WHERE service_id = :id")->execute([':id' => $serviceId]);
        if (!empty($packages)) {
            $stmtPkg = $db->prepare("INSERT INTO service_packages (service_id, name, description, price, delivery_time, sort_order, features) VALUES (:service_id, :name, :description, :price, :delivery_time, :sort_order, :features)");
            foreach ($packages as $pkg) {
                $stmtPkg->execute([
                    ':service_id' => $serviceId, 
                    ':name' => $pkg['name'], 
                    ':description' => $pkg['description'] ?? '', 
                    ':price' => (float)($pkg['price'] ?? 0), 
                    ':delivery_time' => $pkg['delivery_time'] ?? '', 
                    ':sort_order' => $pkg['sort_order'] ?? 0,
                    ':features' => isset($pkg['features']) ? json_encode($pkg['features']) : null
                ]);
            }
        }

        // Addons
        $addons = json_decode($_POST['addons'] ?? '[]', true);
        $db->prepare("DELETE FROM service_addons WHERE service_id = :id")->execute([':id' => $serviceId]);
        if (!empty($addons)) {
            $stmtAddon = $db->prepare("INSERT INTO service_addons (service_id, name, price, type, pricing_tiers, sort_order) VALUES (:service_id, :name, :price, :type, :pricing_tiers, :sort_order)");
            foreach ($addons as $addon) {
                $stmtAddon->execute([
                    ':service_id' => $serviceId,
                    ':name' => $addon['name'],
                    ':price' => (float)($addon['price'] ?? 0),
                    ':type' => $addon['type'] ?? 'quantity',
                    ':pricing_tiers' => $addon['pricing_tiers'] ?? null,
                    ':sort_order' => $addon['sort_order'] ?? 0
                ]);
            }
        }

        // Relations
        $relations = $_POST['related_services'] ?? [];
        $db->prepare("DELETE FROM service_relations WHERE service_id = :id")->execute([':id' => $serviceId]);
        if (!empty($relations)) {
            $stmtRel = $db->prepare("INSERT INTO service_relations (service_id, related_service_id) VALUES (:service_id, :related_id)");
            foreach ($relations as $rel_id) {
                $stmtRel->execute([':service_id' => $serviceId, ':related_id' => (int)$rel_id]);
            }
        }

        // ── Gallery Handling ──
        $galleryDir = '../../uploads/services/gallery/';
        if (!is_dir($galleryDir)) mkdir($galleryDir, 0777, true);

        $existingGalleryIds = json_decode($_POST['existing_gallery'] ?? '[]', true);
        $galleryOrder = json_decode($_POST['gallery_order'] ?? '[]', true);

        // Delete images not in existingGalleryIds
        if ($id) {
            $stmtGal = $db->prepare("SELECT id, image_path FROM service_gallery WHERE service_id = ?");
            $stmtGal->execute([$id]);
            $currentGal = $stmtGal->fetchAll(PDO::FETCH_ASSOC);
            foreach ($currentGal as $g) {
                if (!in_array($g['id'], $existingGalleryIds)) {
                    if (file_exists($galleryDir . $g['image_path'])) unlink($galleryDir . $g['image_path']);
                    $db->prepare("DELETE FROM service_gallery WHERE id = ?")->execute([$g['id']]);
                }
            }
        }

        // Upload new files
        $uploadedMap = []; // maps JS temporary ID to inserted DB ID
        $maxFileSize = 10 * 1024 * 1024; // 10MB limit
        $allowedImageMime = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedPdfMime = ['application/pdf'];
        if (isset($_FILES['gallery_files'])) {
            $fileCount = count($_FILES['gallery_files']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['gallery_files']['error'][$i] === UPLOAD_ERR_OK) {
                    // Validate file size
                    if ($_FILES['gallery_files']['size'][$i] > $maxFileSize) continue;
                    
                    $ext = strtolower(pathinfo($_FILES['gallery_files']['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) continue;
                    
                    // Validate MIME type matches extension
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($_FILES['gallery_files']['tmp_name'][$i]);
                    if ($ext === 'pdf') {
                        if (!in_array($mimeType, $allowedPdfMime)) continue;
                    } else {
                        if (!in_array($mimeType, $allowedImageMime)) continue;
                    }
                    
                    $fName = uniqid('gal_') . '.' . $ext;
                    // Sanitize original name for title (remove path info, limit length)
                    $originalName = basename($_FILES['gallery_files']['name'][$i]);
                    $originalName = mb_substr($originalName, 0, 200);
                    if (move_uploaded_file($_FILES['gallery_files']['tmp_name'][$i], $galleryDir . $fName)) {
                        $mediaType = ($ext === 'pdf') ? 'pdf' : 'image';
                        $stmtInsGal = $db->prepare("INSERT INTO service_gallery (service_id, image_path, media_type, sort_order, title) VALUES (?, ?, ?, 0, ?)");
                        $stmtInsGal->execute([$serviceId, $fName, $mediaType, $originalName]);
                        $newGalId = $db->lastInsertId();
                        $uploadedMap[] = $newGalId;
                    }
                }
            }
        }

        // Insert new links (Video, Web)
        $newLinks = json_decode($_POST['gallery_links'] ?? '[]', true);
        $linkIdsMap = [];
        $allowedMediaTypes = ['video', 'web'];
        if (!empty($newLinks) && is_array($newLinks)) {
            $stmtInsLink = $db->prepare("INSERT INTO service_gallery (service_id, image_path, media_type, sort_order, title, thumbnail_url) VALUES (?, ?, ?, 0, ?, ?)");
            foreach ($newLinks as $link) {
                // Validate media_type against whitelist
                if (!isset($link['media_type']) || !in_array($link['media_type'], $allowedMediaTypes)) continue;
                if (empty($link['image_path'])) continue;
                
                // Validate URL - must be http or https
                $url = trim($link['image_path']);
                if (!preg_match('#^https?://#i', $url)) {
                    $url = 'https://' . $url;
                }
                $parsedCheck = parse_url($url);
                if (!$parsedCheck || empty($parsedCheck['host'])) continue;
                
                // Block javascript:, data:, file: schemes
                $scheme = strtolower($parsedCheck['scheme'] ?? '');
                if (!in_array($scheme, ['http', 'https'])) continue;
                
                // Limit URL length
                if (strlen($url) > 2048) continue;
                
                $title = null;
                $thumb = null;
                if ($link['media_type'] === 'web') {
                    $title = $parsedCheck['host'];
                    $thumb = 'https://www.google.com/s2/favicons?domain=' . urlencode($title) . '&sz=128';
                }
                $stmtInsLink->execute([$serviceId, $url, $link['media_type'], $title, $thumb]);
                $linkIdsMap[$link['id']] = $db->lastInsertId();
            }
        }

        // Update Sort Order for Gallery
        $newIdx = 0;
        foreach ($galleryOrder as $sortIndex => $item_id) {
            if (is_numeric($item_id)) {
                // Existing item
                $db->prepare("UPDATE service_gallery SET sort_order = ? WHERE id = ?")->execute([$sortIndex, $item_id]);
            } else if (isset($linkIdsMap[$item_id])) {
                // New link (Video/Web)
                $db->prepare("UPDATE service_gallery SET sort_order = ? WHERE id = ?")->execute([$sortIndex, $linkIdsMap[$item_id]]);
            } else {
                // New image, use from $uploadedMap
                if (isset($uploadedMap[$newIdx])) {
                    $db->prepare("UPDATE service_gallery SET sort_order = ? WHERE id = ?")->execute([$sortIndex, $uploadedMap[$newIdx]]);
                    $newIdx++;
                }
            }
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error saving service: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("General Error saving service: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
