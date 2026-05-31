<?php
// deploy_ftp.php
echo "Zipping files...\n";
$zipFile = 'deploy_build.zip';
if (file_exists($zipFile)) unlink($zipFile);

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
    die("Cannot open <$zipFile>\n");
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$excludePaths = [
    '/.git', '/.gemini', '/.vscode', '/.idea', '/node_modules', 
    '/deploy_build.zip', '/deploy_ftp.php', '/unzip.php',
    '/uploads/', '.mp4'
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
    
    if (!$file->isDir()) {
        $zip->addFile($filepath, $relativePath);
    } else {
        $zip->addEmptyDir($relativePath);
    }
}
$zip->close();
echo "Zipped successfully. Size: " . round(filesize($zipFile) / 1024 / 1024, 2) . " MB\n";

// Create unzip.php
$unzipCode = <<<'EOD'
<?php
$zipFile = 'deploy_build.zip';
if (!file_exists($zipFile)) die('No zip file found');
$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo(__DIR__);
    $zip->close();
    unlink($zipFile);
    unlink(__FILE__);
    echo "Extracted successfully";
} else {
    echo "Failed to extract";
}
EOD;
file_put_contents('unzip.php', $unzipCode);

// FTP Upload
$ftp_server = '204.93.224.158'; // IP from cPanel
$ftp_user = 'public_html@romaagencia.lat';
$ftp_pass = 'TheRomaAgency2026@2222';

echo "Connecting to FTP $ftp_server...\n";
$conn_id = ftp_connect($ftp_server) or die("Couldn't connect to $ftp_server"); 

if (@ftp_login($conn_id, $ftp_user, $ftp_pass)) {
    echo "Connected as $ftp_user@$ftp_server\n";
    ftp_pasv($conn_id, true);
    
    // El usuario FTP aterriza en public_html, así que entramos a la carpeta sistemasaas
    if (@ftp_chdir($conn_id, 'sistemasaas')) {
        echo "Changed directory to sistemasaas\n";
    } else {
        // Intentamos crearla si no existe
        @ftp_mkdir($conn_id, 'sistemasaas');
        @ftp_chdir($conn_id, 'sistemasaas');
        echo "Created and changed directory to sistemasaas\n";
    }
    
    echo "Uploading unzip.php...\n";
    if (ftp_put($conn_id, 'unzip.php', 'unzip.php', FTP_ASCII)) {
        echo "Successfully uploaded unzip.php\n";
    } else {
        echo "There was a problem while uploading unzip.php\n";
    }

    echo "Uploading $zipFile (this may take a minute or two)...\n";
    if (ftp_put($conn_id, $zipFile, $zipFile, FTP_BINARY)) {
        echo "Successfully uploaded $zipFile\n";
    } else {
        echo "There was a problem while uploading $zipFile\n";
    }
    
} else {
    echo "Couldn't connect as $ftp_user\n";
}
ftp_close($conn_id);

echo "Triggering extraction via HTTP...\n";
$url = 'https://romaagencia.lat/sistemasaas/unzip.php';
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);
$response = @file_get_contents($url, false, $context);
if ($response === false) {
    // Try HTTP if HTTPS fails
    $url = 'http://romaagencia.lat/sistemasaas/unzip.php';
    $response = @file_get_contents($url);
    if ($response === false) {
        echo "Failed to trigger HTTP request. Try opening $url manually.\n";
    } else {
        echo "Server response: $response\n";
    }
} else {
    echo "Server response: $response\n";
}

// Cleanup local
@unlink('unzip.php');
@unlink($zipFile);
echo "Done!\n";
?>
