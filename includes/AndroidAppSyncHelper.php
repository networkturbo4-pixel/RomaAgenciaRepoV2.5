<?php
// includes/AndroidAppSyncHelper.php
// Sincroniza automáticamente el favicon y el nombre del sistema con los recursos del proyecto Android y PWA

class AndroidAppSyncHelper {
    public static function syncFromSettings(PDO $db): void {
        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_name', 'favicon', 'primary_color')");
            $cfg = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cfg[$row['setting_key']] = $row['setting_value'];
            }

            $rootDir = dirname(__DIR__);
            $siteName = trim($cfg['site_name'] ?? 'ROMA');
            if ($siteName === '') {
                $siteName = 'ROMA';
            }

            // 1. Actualizar strings.xml del proyecto Android con el nombre actual del sistema
            $stringsXmlPath = $rootDir . '/android-app/app/src/main/res/values/strings.xml';
            if (is_file($stringsXmlPath) && is_writable($stringsXmlPath)) {
                $xmlContent = @file_get_contents($stringsXmlPath);
                if ($xmlContent !== false) {
                    $escapedName = htmlspecialchars($siteName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $xmlContent = preg_replace(
                        '/<string name="app_name">.*?<\/string>/s',
                        '<string name="app_name">' . $escapedName . '</string>',
                        $xmlContent
                    );
                    @file_put_contents($stringsXmlPath, $xmlContent);
                }
            }

            // 2. Sincronizar iconos de la APK y PWA a partir del favicon del sistema si GD está disponible
            $faviconRel = $cfg['favicon'] ?? '';
            if (!empty($faviconRel) && extension_exists('gd')) {
                $faviconPath = $rootDir . '/' . ltrim($faviconRel, '/');
                if (is_file($faviconPath)) {
                    self::generateIconsFromImage($faviconPath, $rootDir);
                }
            }
        } catch (\Throwable $e) {
            error_log("AndroidAppSyncHelper Error: " . $e->getMessage());
        }
    }

    private static function generateIconsFromImage(string $srcPath, string $rootDir): void {
        $info = @getimagesize($srcPath);
        if (!$info) return;

        $mime = $info['mime'] ?? '';
        $srcImg = null;
        if ($mime === 'image/png') {
            $srcImg = @imagecreatefrompng($srcPath);
        } elseif ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            $srcImg = @imagecreatefromjpeg($srcPath);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $srcImg = @imagecreatefromwebp($srcPath);
        } elseif ($mime === 'image/gif') {
            $srcImg = @imagecreatefromgif($srcPath);
        }

        if (!$srcImg) return;

        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);

        $targets = [
            $rootDir . '/android-app/app/src/main/res/mipmap-mdpi/ic_launcher.png' => 48,
            $rootDir . '/android-app/app/src/main/res/mipmap-mdpi/ic_launcher_round.png' => 48,
            $rootDir . '/android-app/app/src/main/res/mipmap-hdpi/ic_launcher.png' => 72,
            $rootDir . '/android-app/app/src/main/res/mipmap-hdpi/ic_launcher_round.png' => 72,
            $rootDir . '/android-app/app/src/main/res/mipmap-xhdpi/ic_launcher.png' => 96,
            $rootDir . '/android-app/app/src/main/res/mipmap-xhdpi/ic_launcher_round.png' => 96,
            $rootDir . '/android-app/app/src/main/res/mipmap-xxhdpi/ic_launcher.png' => 144,
            $rootDir . '/android-app/app/src/main/res/mipmap-xxhdpi/ic_launcher_round.png' => 144,
            $rootDir . '/android-app/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png' => 192,
            $rootDir . '/android-app/app/src/main/res/mipmap-xxxhdpi/ic_launcher_round.png' => 192,
            $rootDir . '/android-app/app/src/main/res/drawable/ic_launcher_roma.png' => 256,
            $rootDir . '/assets/img/icon-192x192.png' => 192,
            $rootDir . '/assets/img/default-icon.png' => 192,
        ];

        foreach ($targets as $destPath => $size) {
            $dir = dirname($destPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $dst = imagecreatetruecolor($size, $size);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $size, $size, $transparent);
            imagealphablending($dst, true);

            // Escalar manteniendo proporción centrada con pequeño margen
            $pad = (int)round($size * 0.08);
            $avail = $size - ($pad * 2);
            $scale = min($avail / max(1, $srcW), $avail / max(1, $srcH));
            $newW = max(1, (int)round($srcW * $scale));
            $newH = max(1, (int)round($srcH * $scale));
            $dstX = (int)round(($size - $newW) / 2);
            $dstY = (int)round(($size - $newH) / 2);

            imagecopyresampled($dst, $srcImg, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);
            @imagepng($dst, $destPath, 9);
            imagedestroy($dst);
        }

        imagedestroy($srcImg);
    }
}
