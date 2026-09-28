<?php
// modules/brand_guidelines/ajax.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Helper for JSON response
function bg_json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// 1. VERIFY PASSWORD (Public / Guest access for private manuals)
if ($action === 'verify_password') {
    $guidelineId = (int)($_POST['guideline_id'] ?? 0);
    $inputPassword = trim($_POST['password'] ?? '');

    if (!$guidelineId) {
        bg_json_response(['success' => false, 'message' => 'Manual no especificado.'], 400);
    }

    $stmt = $db->prepare("SELECT id, access_password FROM brand_guidelines WHERE id = ?");
    $stmt->execute([$guidelineId]);
    $bg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bg) {
        bg_json_response(['success' => false, 'message' => 'Manual no encontrado.'], 404);
    }

    $storedPass = (string)$bg['access_password'];
    $isValid = false;

    // Direct PIN or password check (support both plain PINs and hashed passwords)
    if ($inputPassword === $storedPass || (password_get_info($storedPass)['algo'] && password_verify($inputPassword, $storedPass))) {
        $isValid = true;
    }

    if ($isValid) {
        $_SESSION['bg_unlocked_' . $guidelineId] = true;
        bg_json_response(['success' => true, 'message' => 'Acceso concedido']);
    } else {
        bg_json_response(['success' => false, 'message' => 'Contraseña o PIN incorrecto. Por favor verifica e intenta nuevamente.']);
    }
}

// For all administrative actions, enforce CRM user login
if (!isset($_SESSION['user_id'])) {
    bg_json_response(['success' => false, 'message' => 'No autorizado. Debes iniciar sesión en el CRM.'], 401);
}

$userId = $_SESSION['user_id'];

// GOOGLE DRIVE: LIST FILES & FOLDERS
if ($action === 'drive_list') {
    require_once __DIR__ . '/../../includes/GoogleDriveHelper.php';
    $drive = new GoogleDriveHelper();

    if (!$drive->isConfigured()) {
        bg_json_response(['success' => false, 'error' => 'Google Drive no está configurado en el sistema.'], 500);
    }

    $folderId = trim($_POST['folder_id'] ?? ($_GET['folder_id'] ?? 'root'));
    $search = trim($_POST['search'] ?? ($_GET['search'] ?? ''));

    if (!empty($search)) {
        $q = "name contains '" . addslashes($search) . "' and trashed = false";
        $rawFiles = $drive->searchFiles($q);
    } else {
        $rawFiles = $drive->listFiles($folderId);
    }

    if (!is_array($rawFiles)) {
        bg_json_response(['success' => false, 'error' => 'No se pudieron obtener los archivos de Google Drive.']);
    }

    $folders = [];
    $files = [];

    foreach ($rawFiles as $f) {
        $isFolder = ($f['mimeType'] === 'application/vnd.google-apps.folder');
        $item = [
            'id' => $f['id'],
            'name' => $f['name'],
            'mimeType' => $f['mimeType'],
            'isFolder' => $isFolder,
            'proxyUrl' => 'ajax/drive_proxy.php?id=' . $f['id'],
            'thumbnail' => $f['thumbnailLink'] ?? ('ajax/drive_proxy.php?id=' . $f['id'])
        ];

        if ($isFolder) {
            $folders[] = $item;
        } else {
            $files[] = $item;
        }
    }

    $folderName = 'Mi Unidad';
    if ($folderId !== 'root') {
        $folderInfo = $drive->getFolderInfo($folderId);
        if ($folderInfo) $folderName = $folderInfo->getName();
    }

    bg_json_response([
        'success' => true,
        'currentFolder' => [
            'id' => $folderId,
            'name' => $folderName
        ],
        'folders' => $folders,
        'files' => $files
    ]);
}

// GOOGLE DRIVE: UPLOAD FILE TO DRIVE
if ($action === 'drive_upload') {
    require_once __DIR__ . '/../../includes/GoogleDriveHelper.php';
    $drive = new GoogleDriveHelper();

    if (!$drive->isConfigured()) {
        bg_json_response(['success' => false, 'error' => 'Google Drive no está configurado.'], 500);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        bg_json_response(['success' => false, 'error' => 'No se recibió ningún archivo válido.'], 400);
    }

    $folderId = trim($_POST['folder_id'] ?? 'root');
    if (empty($folderId)) $folderId = 'root';

    $tmpPath = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];

    $result = $drive->uploadFile($tmpPath, $fileName, $folderId !== 'root' ? $folderId : null);

    if ($result && !empty($result['id'])) {
        $fileId = $result['id'];
        $drive->makePublicViewer($fileId);

        bg_json_response([
            'success' => true,
            'fileId' => $fileId,
            'url' => 'ajax/drive_proxy.php?id=' . $fileId,
            'name' => $fileName
        ]);
    } else {
        bg_json_response(['success' => false, 'error' => 'Error al subir el archivo a Google Drive.'], 500);
    }
}

// 2. CHECK SLUG AVAILABILITY
if ($action === 'check_slug') {
    $rawSlug = trim($_POST['slug'] ?? '');
    $currentId = (int)($_POST['current_id'] ?? 0);

    $cleanSlug = bg_slugify($rawSlug);
    $stmt = $db->prepare("SELECT id FROM brand_guidelines WHERE slug = ? AND id != ?");
    $stmt->execute([$cleanSlug, $currentId]);
    $exists = $stmt->fetch();

    if ($exists) {
        $suggested = $cleanSlug . '-' . rand(10, 99);
        bg_json_response(['available' => false, 'slug' => $cleanSlug, 'suggested' => $suggested]);
    } else {
        bg_json_response(['available' => true, 'slug' => $cleanSlug]);
    }
}

// 3. TOGGLE PRIVACY
if ($action === 'toggle_privacy') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        bg_json_response(['success' => false, 'message' => 'ID no válido'], 400);
    }

    $stmt = $db->prepare("SELECT is_public FROM brand_guidelines WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        bg_json_response(['success' => false, 'message' => 'Manual no encontrado'], 404);
    }

    $newStatus = $current['is_public'] == 1 ? 0 : 1;
    $upd = $db->prepare("UPDATE brand_guidelines SET is_public = ? WHERE id = ?");
    $upd->execute([$newStatus, $id]);

    bg_json_response([
        'success' => true, 
        'is_public' => $newStatus,
        'message' => $newStatus == 1 ? 'El manual ahora es PÚBLICO' : 'El manual ahora es PRIVADO'
    ]);
}

// 4. DUPLICATE MANUAL
if ($action === 'duplicate') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        bg_json_response(['success' => false, 'message' => 'ID no válido'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM brand_guidelines WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orig) {
        bg_json_response(['success' => false, 'message' => 'Manual original no encontrado'], 404);
    }

    $newName = $orig['brand_name'] . ' (Copia)';
    $newSlug = bg_slugify($orig['slug'] . '-copia-' . rand(10, 99));

    // Ensure slug uniqueness
    $stmtChk = $db->prepare("SELECT COUNT(*) FROM brand_guidelines WHERE slug = ?");
    $stmtChk->execute([$newSlug]);
    if ($stmtChk->fetchColumn() > 0) {
        $newSlug = bg_slugify($orig['slug'] . '-' . substr(md5(uniqid()), 0, 5));
    }

    $ins = $db->prepare("
        INSERT INTO brand_guidelines (
            client_id, brand_name, slug, tagline, description, mission, vision, values_json, tone_of_voice,
            logo_primary, logo_primary_dark, logo_symbol, logo_variations_json, icons_json,
            safe_zone_rules, min_size_rules, incorrect_uses_json, colors_json, fonts_json,
            applications_json, allow_asset_download, is_public, access_password, created_by
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?
        )
    ");

    $ins->execute([
        $orig['client_id'],
        $newName,
        $newSlug,
        $orig['tagline'],
        $orig['description'],
        $orig['mission'],
        $orig['vision'],
        $orig['values_json'],
        $orig['tone_of_voice'],
        $orig['logo_primary'],
        $orig['logo_primary_dark'],
        $orig['logo_symbol'],
        $orig['logo_variations_json'],
        $orig['icons_json'],
        $orig['safe_zone_rules'],
        $orig['min_size_rules'],
        $orig['incorrect_uses_json'],
        $orig['colors_json'],
        $orig['fonts_json'],
        $orig['applications_json'],
        $orig['allow_asset_download'],
        $orig['is_public'],
        $orig['access_password'],
        $userId
    ]);

    $newId = $db->lastInsertId();
    bg_json_response([
        'success' => true, 
        'new_id' => $newId, 
        'message' => 'Manual duplicado con éxito como "' . $newName . '"'
    ]);
}

// 5. DELETE MANUAL
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        bg_json_response(['success' => false, 'message' => 'ID no válido'], 400);
    }

    $stmt = $db->prepare("DELETE FROM brand_guidelines WHERE id = ?");
    $stmt->execute([$id]);

    bg_json_response(['success' => true, 'message' => 'Manual de marca eliminado correctamente.']);
}

// 6. SAVE / CREATE / UPDATE BRAND GUIDELINE
if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $brandName = trim($_POST['brand_name'] ?? '');
    $clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
    $rawSlug = trim($_POST['slug'] ?? '');
    $tagline = trim($_POST['tagline'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $mission = trim($_POST['mission'] ?? '');
    $vision = trim($_POST['vision'] ?? '');
    $valuesJson = $_POST['values_json'] ?? '[]';
    $toneOfVoice = trim($_POST['tone_of_voice'] ?? '');

    $safeZoneRules = trim($_POST['safe_zone_rules'] ?? '');
    $minSizeRules = trim($_POST['min_size_rules'] ?? '');
    $incorrectUsesJson = $_POST['incorrect_uses_json'] ?? '[]';
    $colorsJson = $_POST['colors_json'] ?? '[]';
    $fontsJson = $_POST['fonts_json'] ?? '[]';
    $applicationsJson = $_POST['applications_json'] ?? '[]';

    $isPublic = isset($_POST['is_public']) ? (int)$_POST['is_public'] : 1;
    $accessPassword = trim($_POST['access_password'] ?? '');
    $allowAssetDownload = isset($_POST['allow_asset_download']) ? (int)$_POST['allow_asset_download'] : 1;

    if (empty($brandName)) {
        bg_json_response(['success' => false, 'message' => 'El nombre de la marca es obligatorio.'], 400);
    }

    // Determine clean slug
    $slug = !empty($rawSlug) ? bg_slugify($rawSlug) : bg_slugify($brandName);

    // Verify slug uniqueness
    $stmtSlug = $db->prepare("SELECT id FROM brand_guidelines WHERE slug = ? AND id != ?");
    $stmtSlug->execute([$slug, $id]);
    if ($stmtSlug->fetch()) {
        $slug = $slug . '-' . rand(10, 999);
    }

    // Existing data if updating
    $existing = null;
    if ($id > 0) {
        $stmtEx = $db->prepare("SELECT * FROM brand_guidelines WHERE id = ?");
        $stmtEx->execute([$id]);
        $existing = $stmtEx->fetch(PDO::FETCH_ASSOC);
    }

    // Handle File Uploads
    // 1. Logo Primary
    $logoPrimary = !empty($_POST['logo_primary_url']) ? trim($_POST['logo_primary_url']) : ($existing['logo_primary'] ?? null);
    if (isset($_FILES['logo_primary']) && $_FILES['logo_primary']['error'] === UPLOAD_ERR_OK) {
        $up = bg_handle_upload($_FILES['logo_primary'], 'logos');
        if ($up) $logoPrimary = $up;
    } elseif (isset($_POST['remove_logo_primary']) && $_POST['remove_logo_primary'] == '1') {
        $logoPrimary = null;
    }

    // 2. Logo Primary Dark (for dark backgrounds)
    $logoPrimaryDark = !empty($_POST['logo_primary_dark_url']) ? trim($_POST['logo_primary_dark_url']) : ($existing['logo_primary_dark'] ?? null);
    if (isset($_FILES['logo_primary_dark']) && $_FILES['logo_primary_dark']['error'] === UPLOAD_ERR_OK) {
        $up = bg_handle_upload($_FILES['logo_primary_dark'], 'logos');
        if ($up) $logoPrimaryDark = $up;
    } elseif (isset($_POST['remove_logo_primary_dark']) && $_POST['remove_logo_primary_dark'] == '1') {
        $logoPrimaryDark = null;
    }

    // 3. Logo Symbol / Isotype
    $logoSymbol = !empty($_POST['logo_symbol_url']) ? trim($_POST['logo_symbol_url']) : ($existing['logo_symbol'] ?? null);
    if (isset($_FILES['logo_symbol']) && $_FILES['logo_symbol']['error'] === UPLOAD_ERR_OK) {
        $up = bg_handle_upload($_FILES['logo_symbol'], 'logos');
        if ($up) $logoSymbol = $up;
    } elseif (isset($_POST['remove_logo_symbol']) && $_POST['remove_logo_symbol'] == '1') {
        $logoSymbol = null;
    }

    // 4. Logo Variations JSON + files
    // Existing variations
    $existingVariations = !empty($existing['logo_variations_json']) ? json_decode($existing['logo_variations_json'], true) : [];
    if (!is_array($existingVariations)) $existingVariations = [];

    $postedVariations = !empty($_POST['logo_variations_data']) ? json_decode($_POST['logo_variations_data'], true) : [];
    if (!is_array($postedVariations)) $postedVariations = [];

    $finalVariations = [];
    foreach ($postedVariations as $idx => $varItem) {
        $fileKey = 'variation_file_' . $idx;
        $fileUrl = $varItem['url'] ?? '';

        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $up = bg_handle_upload($_FILES[$fileKey], 'variations');
            if ($up) $fileUrl = $up;
        }

        if (!empty($varItem['name'])) {
            $finalVariations[] = [
                'name' => trim($varItem['name']),
                'desc' => trim($varItem['desc'] ?? ''),
                'bg_type' => $varItem['bg_type'] ?? 'light',
                'url' => $fileUrl
            ];
        }
    }
    $finalVariationsJson = json_encode($finalVariations, JSON_UNESCAPED_UNICODE);

    // 5. Icons JSON + files
    $postedIcons = !empty($_POST['icons_data']) ? json_decode($_POST['icons_data'], true) : [];
    if (!is_array($postedIcons)) $postedIcons = [];

    $finalIcons = [];
    foreach ($postedIcons as $idx => $icoItem) {
        $fileKey = 'icon_file_' . $idx;
        $fileUrl = $icoItem['url'] ?? '';

        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $up = bg_handle_upload($_FILES[$fileKey], 'icons');
            if ($up) $fileUrl = $up;
        }

        if (!empty($icoItem['name'])) {
            $finalIcons[] = [
                'name' => trim($icoItem['name']),
                'type' => trim($icoItem['type'] ?? 'Icono'),
                'desc' => trim($icoItem['desc'] ?? ''),
                'url' => $fileUrl
            ];
        }
    }
    $finalIconsJson = json_encode($finalIcons, JSON_UNESCAPED_UNICODE);

    // 6. Applications / Mockups JSON + files
    $postedApps = !empty($_POST['applications_data']) ? json_decode($_POST['applications_data'], true) : [];
    if (!is_array($postedApps)) $postedApps = [];

    $finalApps = [];
    foreach ($postedApps as $idx => $appItem) {
        $fileKey = 'application_file_' . $idx;
        $fileUrl = $appItem['image_url'] ?? '';

        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $up = bg_handle_upload($_FILES[$fileKey], 'applications');
            if ($up) $fileUrl = $up;
        }

        if (!empty($appItem['title']) || !empty($fileUrl)) {
            $finalApps[] = [
                'title' => trim($appItem['title'] ?? 'Aplicación de Marca'),
                'desc' => trim($appItem['desc'] ?? ''),
                'image_url' => $fileUrl
            ];
        }
    }
    $finalAppsJson = json_encode($finalApps, JSON_UNESCAPED_UNICODE);

    // Clean Colors JSON: ensure valid array
    $decodedColors = json_decode($colorsJson, true);
    if (!is_array($decodedColors)) $decodedColors = [];
    $finalColors = [];
    foreach ($decodedColors as $c) {
        if (!empty($c['hex'])) {
            $hex = trim($c['hex']);
            if (substr($hex, 0, 1) !== '#') $hex = '#' . $hex;
            $rgbCalc = bg_hex_to_rgb($hex)['str'];
            $cmykCalc = bg_hex_to_cmyk($hex)['str'];

            $finalColors[] = [
                'name' => trim($c['name'] ?? 'Color'),
                'role' => trim($c['role'] ?? 'Primario'),
                'hex' => strtoupper($hex),
                'rgb' => !empty($c['rgb']) ? trim($c['rgb']) : $rgbCalc,
                'cmyk' => !empty($c['cmyk']) ? trim($c['cmyk']) : $cmykCalc,
                'pantone' => trim($c['pantone'] ?? '')
            ];
        }
    }
    $finalColorsJson = json_encode($finalColors, JSON_UNESCAPED_UNICODE);

    // Clean Fonts JSON
    $decodedFonts = json_decode($fontsJson, true);
    if (!is_array($decodedFonts)) $decodedFonts = [];
    $finalFontsJson = json_encode($decodedFonts, JSON_UNESCAPED_UNICODE);

    // Clean Values JSON
    $decodedValues = json_decode($valuesJson, true);
    if (!is_array($decodedValues)) $decodedValues = [];
    $finalValuesJson = json_encode($decodedValues, JSON_UNESCAPED_UNICODE);

    // Clean Incorrect Uses JSON
    $decodedIncorrect = json_decode($incorrectUsesJson, true);
    if (!is_array($decodedIncorrect)) $decodedIncorrect = [];
    $finalIncorrectJson = json_encode($decodedIncorrect, JSON_UNESCAPED_UNICODE);

    if ($id > 0) {
        // UPDATE
        $sql = "
            UPDATE brand_guidelines SET
                client_id = ?,
                brand_name = ?,
                slug = ?,
                tagline = ?,
                description = ?,
                mission = ?,
                vision = ?,
                values_json = ?,
                tone_of_voice = ?,
                logo_primary = ?,
                logo_primary_dark = ?,
                logo_symbol = ?,
                logo_variations_json = ?,
                icons_json = ?,
                safe_zone_rules = ?,
                min_size_rules = ?,
                incorrect_uses_json = ?,
                colors_json = ?,
                fonts_json = ?,
                applications_json = ?,
                allow_asset_download = ?,
                is_public = ?,
                access_password = ?
            WHERE id = ?
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            $clientId,
            $brandName,
            $slug,
            $tagline,
            $description,
            $mission,
            $vision,
            $finalValuesJson,
            $toneOfVoice,
            $logoPrimary,
            $logoPrimaryDark,
            $logoSymbol,
            $finalVariationsJson,
            $finalIconsJson,
            $safeZoneRules,
            $minSizeRules,
            $finalIncorrectJson,
            $finalColorsJson,
            $finalFontsJson,
            $finalAppsJson,
            $allowAssetDownload,
            $isPublic,
            $accessPassword,
            $id
        ]);

        bg_json_response([
            'success' => true,
            'id' => $id,
            'slug' => $slug,
            'message' => '¡Manual de marca "' . $brandName . '" actualizado exitosamente!'
        ]);
    } else {
        // INSERT
        $sql = "
            INSERT INTO brand_guidelines (
                client_id, brand_name, slug, tagline, description, mission, vision, values_json, tone_of_voice,
                logo_primary, logo_primary_dark, logo_symbol, logo_variations_json, icons_json,
                safe_zone_rules, min_size_rules, incorrect_uses_json, colors_json, fonts_json,
                applications_json, allow_asset_download, is_public, access_password, created_by
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            $clientId,
            $brandName,
            $slug,
            $tagline,
            $description,
            $mission,
            $vision,
            $finalValuesJson,
            $toneOfVoice,
            $logoPrimary,
            $logoPrimaryDark,
            $logoSymbol,
            $finalVariationsJson,
            $finalIconsJson,
            $safeZoneRules,
            $minSizeRules,
            $finalIncorrectJson,
            $finalColorsJson,
            $finalFontsJson,
            $finalAppsJson,
            $allowAssetDownload,
            $isPublic,
            $accessPassword,
            $userId
        ]);

        $newId = $db->lastInsertId();

        bg_json_response([
            'success' => true,
            'id' => $newId,
            'slug' => $slug,
            'message' => '¡Manual de marca "' . $brandName . '" creado exitosamente!'
        ]);
    }
}

// Action not found
bg_json_response(['success' => false, 'message' => 'Acción no válida o no soportada.'], 400);
