<?php
$baseDir = dirname(__DIR__);
$zip = new ZipArchive();
$zipFile = $baseDir . '/actualizacion_formularios.zip';

if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("No se pudo crear el archivo ZIP");
}

$files = [
    'modules/project_board/index.php',
    'modules/project_board/ajax_upload_pdf.php',
    'modules/project_board/ajax_get_project_info.php',
    'modules/project_board/ajax_link_form.php',
    'modules/project_board/ajax_generate_month_folders.php',
    'modules/design_tasks/index.php',
    'modules/design_tasks/ajax.php',
    'modules/forms/view_submission.php',
    'includes/GoogleDriveHelper.php'
];

foreach ($files as $file) {
    if (file_exists($baseDir . '/' . $file)) {
        $zip->addFile($baseDir . '/' . $file, $file);
    } else {
        echo "Missing file: $file\n";
    }
}

$zip->close();
echo "Creado correctamente en: actualizacion_formularios.zip\n";
