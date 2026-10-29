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
            $siteName = trim($cfg['site_name'] ?? 'Roma Agencia');
            if ($siteName === '') {
                $siteName = 'Roma Agencia';
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
            if (!empty($faviconRel) && function_exists('imagecreatefrompng')) {
                $faviconPath = $rootDir . '/' . ltrim($faviconRel, '/');
                if (is_file($faviconPath)) {
                    self::generateIconsFromImage($faviconPath, $rootDir);
                }
            }
        } catch (\Throwable $e) {
            error_log("AndroidAppSyncHelper Error: " . $e->getMessage());
        }
    }

    public static function generateIconsFromImage(string $srcPath, string $rootDir): void {
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
        $isSquare = (abs($srcW - $srcH) <= max(10, (int)round(max($srcW, $srcH) * 0.05)));

        $targets = [
            $rootDir . '/android-app/app/src/main/res/mipmap-mdpi/ic_launcher.png' => [48, false],
            $rootDir . '/android-app/app/src/main/res/mipmap-mdpi/ic_launcher_round.png' => [48, true],
            $rootDir . '/android-app/app/src/main/res/mipmap-hdpi/ic_launcher.png' => [72, false],
            $rootDir . '/android-app/app/src/main/res/mipmap-hdpi/ic_launcher_round.png' => [72, true],
            $rootDir . '/android-app/app/src/main/res/mipmap-xhdpi/ic_launcher.png' => [96, false],
            $rootDir . '/android-app/app/src/main/res/mipmap-xhdpi/ic_launcher_round.png' => [96, true],
            $rootDir . '/android-app/app/src/main/res/mipmap-xxhdpi/ic_launcher.png' => [144, false],
            $rootDir . '/android-app/app/src/main/res/mipmap-xxhdpi/ic_launcher_round.png' => [144, true],
            $rootDir . '/android-app/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png' => [192, false],
            $rootDir . '/android-app/app/src/main/res/mipmap-xxxhdpi/ic_launcher_round.png' => [192, true],
            $rootDir . '/android-app/app/src/main/res/drawable/ic_launcher_roma.png' => [256, false],
            $rootDir . '/assets/img/icon-192x192.png' => [192, false],
            $rootDir . '/assets/img/default-icon.png' => [192, false],
        ];

        foreach ($targets as $destPath => [$size, $isRound]) {
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

            // Si el icono ya es cuadrado (como el favicon de producción), ocupar el 100% sin márgenes vacíos
            $pad = $isSquare ? 0 : (int)round($size * 0.06);
            $avail = $size - ($pad * 2);
            $scale = min($avail / max(1, $srcW), $avail / max(1, $srcH));
            $newW = max(1, (int)round($srcW * $scale));
            $newH = max(1, (int)round($srcH * $scale));
            $dstX = (int)round(($size - $newW) / 2);
            $dstY = (int)round(($size - $newH) / 2);

            imagecopyresampled($dst, $srcImg, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);

            if ($isRound && $isSquare) {
                imagealphablending($dst, false);
                $r = $size / 2.0;
                $cx = $r - 0.5;
                $cy = $r - 0.5;
                for ($y = 0; $y < $size; $y++) {
                    for ($x = 0; $x < $size; $x++) {
                        $dist = sqrt(($x - $cx) * ($x - $cx) + ($y - $cy) * ($y - $cy));
                        if ($dist > $r) {
                            imagesetpixel($dst, $x, $y, $transparent);
                        }
                    }
                }
            }

            @imagepng($dst, $destPath, 9);
            imagedestroy($dst);
        }

        imagedestroy($srcImg);
    }
}
