<?php
echo "Copiando archivos a la carpeta produccion_romaagencia...\n";
$targetDir = __DIR__ . '/produccion_romaagencia';

if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
} else {
    echo "La carpeta ya existe. Limpiando...\n";
    // Simple limpieza rápida para evitar mezclar
    // (Omitida por simplicidad, si ya existe asume que copia encima)
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$excludePaths = [
    '/.git', '/.gemini', '/.vscode', '/.idea', '/node_modules', 
    '/deploy_build.zip', '/deploy_ftp.php', '/unzip.php',
    '/produccion_romaagencia.zip', '/uploads', '.mp4',
    '/create_zip.php', '/create_folder.php', '/produccion_romaagencia'
];

foreach ($iterator as $file) {
    $filepath = $file->getRealPath();
    $relativePath = substr($filepath, strlen(__DIR__) + 1);
    $relativePath = str_replace('\\', '/', $relativePath);
    
    $skip = false;
    foreach ($excludePaths as $exclude) {
        if (strpos('/' . $relativePath, $exclude) === 0 || strpos('/' . $relativePath, $exclude . '/') !== false) {
            $skip = true;
            break;
        }
    }
    
    if ($skip) continue;
    
    $targetPath = $targetDir . '/' . $relativePath;
    
    if ($file->isDir()) {
        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0777, true);
        }
    } else {
        copy($filepath, $targetPath);
    }
}
echo "Copia finalizada. Los archivos listos para producción están en la carpeta 'produccion_romaagencia'.\n";
?>
