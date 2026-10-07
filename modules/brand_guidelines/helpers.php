<?php
// modules/brand_guidelines/helpers.php

if (!function_exists('bg_slugify')) {
    function bg_slugify($text) {
        // Convert to lowercase and transliterate
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        // Replace non-alphanumeric characters with hyphens
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim($text, '-');
        return !empty($text) ? $text : 'marca-' . substr(md5(uniqid()), 0, 6);
    }
}

if (!function_exists('bg_hex_to_rgb')) {
    function bg_hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
        } elseif (strlen($hex) === 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        } else {
            return ['r' => 0, 'g' => 0, 'b' => 0, 'str' => '0, 0, 0'];
        }
        return ['r' => $r, 'g' => $g, 'b' => $b, 'str' => "{$r}, {$g}, {$b}"];
    }
}

if (!function_exists('bg_hex_to_cmyk')) {
    function bg_hex_to_cmyk($hex) {
        $rgb = bg_hex_to_rgb($hex);
        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;

        $k = 1 - max($r, $g, $b);
        if ($k == 1) {
            return ['c' => 0, 'm' => 0, 'y' => 0, 'k' => 100, 'str' => 'C:0 M:0 Y:0 K:100'];
        }
        $c = round(((1 - $r - $k) / (1 - $k)) * 100);
        $m = round(((1 - $g - $k) / (1 - $k)) * 100);
        $y = round(((1 - $b - $k) / (1 - $k)) * 100);
        $k = round($k * 100);

        return ['c' => $c, 'm' => $m, 'y' => $y, 'k' => $k, 'str' => "C:{$c} M:{$m} Y:{$y} K:{$k}"];
    }
}

if (!function_exists('bg_get_base_url')) {
    function bg_get_base_url() {
        global $global_settings;
        if (!empty($global_settings['site_url'])) {
            return rtrim($global_settings['site_url'], '/');
        }
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $protocol = $is_https ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        return $protocol . '://' . $host . ($scriptDir ? $scriptDir : '');
    }
}

if (!function_exists('bg_get_short_url')) {
    function bg_get_short_url($slug) {
        $baseUrl = bg_get_base_url();
        // Check if Apache mod_rewrite is active or use standard fallback
        return $baseUrl . '/b/' . urlencode($slug);
    }
}

if (!function_exists('bg_get_param_url')) {
    function bg_get_param_url($slug) {
        $baseUrl = bg_get_base_url();
        return $baseUrl . '/index.php?bg=' . urlencode($slug);
    }
}

if (!function_exists('bg_handle_upload')) {
    function bg_handle_upload($fileArray, $subDir = '') {
        if (!isset($fileArray) || $fileArray['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowedExts = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif', 'pdf', 'eps', 'ai', 'ttf', 'otf', 'woff', 'woff2'];
        $ext = strtolower(pathinfo($fileArray['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            return null;
        }

        $targetDir = __DIR__ . '/../../uploads/brand_guidelines/' . ($subDir ? trim($subDir, '/') . '/' : '');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileArray['name'], PATHINFO_FILENAME));
        $newFilename = $cleanName . '_' . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
        $targetPath = $targetDir . $newFilename;

        if (move_uploaded_file($fileArray['tmp_name'], $targetPath)) {
            return 'uploads/brand_guidelines/' . ($subDir ? trim($subDir, '/') . '/' : '') . $newFilename;
        }

        return null;
    }
}

if (!function_exists('bg_asset_url')) {
    function bg_asset_url($path) {
        if (empty($path)) return '';
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, 'data:') === 0) {
            return $path;
        }
        $baseUrl = rtrim(bg_get_base_url(), '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('bg_img_to_base64')) {
    function bg_img_to_base64($relativePath, $maxDim = 1000) {
        if (empty($relativePath)) return null;

        // If already base64 data uri
        if (strpos($relativePath, 'data:') === 0) {
            return $relativePath;
        }

        $cacheDir = __DIR__ . '/../../uploads/brand_guidelines/cache/';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        // Helper to optimize raster image to max dimension
        $optimizeRaster = function($srcPath) use ($maxDim, $cacheDir) {
            if (!file_exists($srcPath)) return null;
            $info = @getimagesize($srcPath);
            if (!$info) return null;
            $w = $info[0];
            $h = $info[1];
            $mime = $info['mime'] ?? 'image/png';

            // If already within reasonable dimensions, return directly
            if ($w <= $maxDim && $h <= $maxDim) {
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($srcPath));
            }

            // Check cache
            $cacheFile = $cacheDir . 'thumb_' . $maxDim . '_' . md5($srcPath . filemtime($srcPath)) . '.png';
            if (file_exists($cacheFile)) {
                return 'data:image/png;base64,' . base64_encode(file_get_contents($cacheFile));
            }

            // Calculate scaled dimensions
            if ($w > $h) {
                $nw = $maxDim;
                $nh = (int)round(($h * $maxDim) / $w);
            } else {
                $nh = $maxDim;
                $nw = (int)round(($w * $maxDim) / $h);
            }

            $srcImg = null;
            if ($mime === 'image/png') {
                $srcImg = @imagecreatefrompng($srcPath);
            } elseif ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                $srcImg = @imagecreatefromjpeg($srcPath);
            } elseif ($mime === 'image/webp') {
                $srcImg = @imagecreatefromwebp($srcPath);
            }

            if (!$srcImg) {
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($srcPath));
            }

            $dstImg = imagecreatetruecolor($nw, $nh);
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            $trans = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
            imagefilledrectangle($dstImg, 0, 0, $nw, $nh, $trans);

            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($srcImg);

            imagepng($dstImg, $cacheFile, 6);
            imagedestroy($dstImg);

            if (file_exists($cacheFile)) {
                return 'data:image/png;base64,' . base64_encode(file_get_contents($cacheFile));
            }
            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($srcPath));
        };

        // 1. Google Drive proxy URL
        if (strpos($relativePath, 'drive_proxy.php') !== false) {
            $parts = parse_url($relativePath);
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $q);
                if (!empty($q['id'])) {
                    require_once __DIR__ . '/../../includes/GoogleDriveHelper.php';
                    $drive = new GoogleDriveHelper();
                    if ($drive->isConfigured()) {
                        $content = $drive->streamFile($q['id']);
                        if ($content) {
                            $tempFile = tempnam(sys_get_temp_dir(), 'bg_drv_');
                            file_put_contents($tempFile, $content);
                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            $mime = $finfo->buffer($content) ?: 'image/png';
                            if ($mime === 'image/svg+xml') {
                                @unlink($tempFile);
                                return 'data:' . $mime . ';base64,' . base64_encode($content);
                            }
                            $opt = $optimizeRaster($tempFile);
                            @unlink($tempFile);
                            return $opt ?: ('data:' . $mime . ';base64,' . base64_encode($content));
                        }
                    }
                }
            }
        }

        // 2. Local File
        $clean = str_replace(['../', '..\\'], '', $relativePath);
        $fullPath = realpath(__DIR__ . '/../../' . ltrim($clean, '/'));
        if ($fullPath && file_exists($fullPath)) {
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            if ($ext === 'svg') {
                return 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($fullPath));
            }
            return $optimizeRaster($fullPath);
        }

        // 3. Remote URL fallback
        if (strpos($relativePath, 'http://') === 0 || strpos($relativePath, 'https://') === 0) {
            $ctx = stream_context_create([
                "ssl" => [
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ]
            ]);
            $data = @file_get_contents($relativePath, false, $ctx);
            if ($data) {
                $tempFile = tempnam(sys_get_temp_dir(), 'bg_rem_');
                file_put_contents($tempFile, $data);
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->buffer($data) ?: 'image/png';
                if ($mime === 'image/svg+xml') {
                    @unlink($tempFile);
                    return 'data:' . $mime . ';base64,' . base64_encode($data);
                }
                $opt = $optimizeRaster($tempFile);
                @unlink($tempFile);
                return $opt ?: ('data:' . $mime . ';base64,' . base64_encode($data));
            }
        }

        return null;
    }
}

if (!function_exists('bg_get_system_settings')) {
    function bg_get_system_settings($db) {
        $settings = [];
        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {}
        return $settings;
    }
}

if (!function_exists('bg_ensure_proposals_columns')) {
    function bg_ensure_proposals_columns($db) {
        if (!$db) return;
        try {
            $col = $db->query("SHOW COLUMNS FROM brand_guidelines LIKE 'show_proposals'")->fetch();
            if (!$col) {
                @$db->exec("ALTER TABLE brand_guidelines ADD COLUMN show_proposals TINYINT(1) NOT NULL DEFAULT 0 AFTER applications_json");
            }
            $colJson = $db->query("SHOW COLUMNS FROM brand_guidelines LIKE 'logo_proposals_json'")->fetch();
            if (!$colJson) {
                @$db->exec("ALTER TABLE brand_guidelines ADD COLUMN logo_proposals_json LONGTEXT DEFAULT NULL AFTER show_proposals");
            }
        } catch (Throwable $e) {}
    }
}


