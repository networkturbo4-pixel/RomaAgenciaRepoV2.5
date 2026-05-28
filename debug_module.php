<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . "\n\n";

// Simulate the main index.php environment
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "No session - simulating login for test...\n";
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
}

// Set the GET parameter
$_GET['module'] = 'month_board';
$_GET['id'] = 23;

// Try to load database
echo "Loading database...\n";
try {
    require_once __DIR__ . '/config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "Database OK\n";
} catch(Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
    exit;
}

// Try to include the module
echo "\nAttempting to load month_board module...\n";
echo "File size: " . filesize(__DIR__ . '/modules/month_board/index.php') . " bytes\n";
echo "---\n\n";

try {
    // Check for common issues first
    $content = file_get_contents(__DIR__ . '/modules/month_board/index.php');
    
    // Check for null bytes
    if (strpos($content, "\0") !== false) {
        echo "ERROR: File contains null bytes!\n";
    }
    
    // Check encoding
    $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'ASCII'], true);
    echo "File encoding: " . ($encoding ?: 'unknown') . "\n";
    
    // Check for common PHP issues
    if (preg_match('/\?\>[\s\S]*\<\?php/', $content)) {
        echo "WARNING: File has multiple PHP open/close tags\n";
    }
    
    // Try to find the specific line causing issues
    echo "\nTrying to include the file now...\n";
    flush();
    
    include __DIR__ . '/modules/month_board/index.php';
    
} catch(Error $e) {
    echo "\n\nFATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
} catch(Exception $e) {
    echo "\n\nEXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n\nDone.";
echo "</pre>";
?>
