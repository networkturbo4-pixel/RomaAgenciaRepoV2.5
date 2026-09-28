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

        $allowedExts = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif', 'pdf', 'eps', 'ai'];
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
