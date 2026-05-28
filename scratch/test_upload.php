<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['project_id'] = 1;
$_FILES['pdf_file'] = [
    'name' => 'test.pdf',
    'type' => 'application/pdf',
    'tmp_name' => __DIR__ . '/test.pdf',
    'error' => UPLOAD_ERR_OK,
    'size' => 1024
];
file_put_contents(__DIR__ . '/test.pdf', 'dummy pdf content');

session_start();
$_SESSION['user_id'] = 1;

chdir(__DIR__ . '/../modules/project_board');
ob_start();
include 'ajax_upload_pdf.php';
$output = ob_get_clean();

echo "OUTPUT:\n";
echo $output;
