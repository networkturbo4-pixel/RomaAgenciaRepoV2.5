<?php
// modules/project_board/ajax_generate_month_folders.php

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/GoogleDriveHelper.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$monthId = isset($_POST['month_id']) ? (int)$_POST['month_id'] : 0;
$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$month = isset($_POST['month']) ? (int)$_POST['month'] : 0;
$year = isset($_POST['year']) ? (int)$_POST['year'] : 0;

if ($monthId) {
    $stmtM = $db->prepare("SELECT project_id, month, year FROM project_months WHERE id = ?");
    $stmtM->execute([$monthId]);
    $mData = $stmtM->fetch(PDO::FETCH_ASSOC);
    if ($mData) {
        $projectId = $mData['project_id'];
        $month = $mData['month'];
        $year = $mData['year'];
    }
}

if (!$projectId || !$month || !$year) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

$monthNames = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$monthName = $monthNames[$month] ?? '';

try {
    // 1. Obtener la carpeta raíz del proyecto
    $stmt = $db->prepare("SELECT drive_folder_id FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project || empty($project['drive_folder_id'])) {
        throw new Exception("El proyecto no tiene una carpeta de Drive configurada.");
    }

    $projectRootId = $project['drive_folder_id'];

    $driveHelper = new GoogleDriveHelper();
    if (!$driveHelper->isConfigured()) {
        throw new Exception("Google Drive no está configurado.");
    }

    // 2. Verificar si la carpeta principal ya existe en Drive
    $mainFolderName = "$monthName - $year";
    $mainFolderId = null;
    $mainFolderLink = null;
    
    $existingRootFolders = $driveHelper->listFolders($projectRootId);
    if ($existingRootFolders) {
        foreach ($existingRootFolders as $f) {
            if ($f->getName() === $mainFolderName) {
                $mainFolderId = $f->getId();
                $mainFolderLink = $f->getWebViewLink() ?? "https://drive.google.com/drive/folders/" . $mainFolderId;
                break;
            }
        }
    }

    $createdFolders = [];

    if ($mainFolderId) {
        // Ya existe, obtener sus subcarpetas
        $existingSub = $driveHelper->listFolders($mainFolderId);
        if ($existingSub) {
            foreach ($existingSub as $sf) {
                $createdFolders[] = [
                    'name' => $sf->getName(),
                    'id' => $sf->getId(),
                    'url' => $sf->getWebViewLink() ?? "https://drive.google.com/drive/folders/" . $sf->getId()
                ];
            }
        }
    } else {
        // No existe, crearla
        $mainFolderId = $driveHelper->createFolder($mainFolderName, $projectRootId);

        if (!$mainFolderId) {
            throw new Exception("No se pudo crear la carpeta principal del mes en Drive.");
        }
        
        $mainFolderLink = "https://drive.google.com/drive/folders/" . $mainFolderId;
        $driveHelper->makePublic($mainFolderId);

        // 3. Crear las 6 subcarpetas
        $subfoldersToCreate = [
            'AUDIOVISUAL',
            'DISEÑO GRAFICO',
            'EDITABLES',
            'EXTRAS',
            'FORM',
            'POST TERMINADOS',
            'REFERENCIAS'
        ];

        foreach ($subfoldersToCreate as $name) {
            $subId = $driveHelper->createFolder($name, $mainFolderId);
            if ($subId) {
                $createdFolders[] = [
                    'name' => $name,
                    'id' => $subId,
                    'url' => "https://drive.google.com/drive/folders/" . $subId
                ];
            }
        }
    }

    // Array completo incluyendo la principal
    $resultData = [
        'main_folder' => [
            'name' => $mainFolderName,
            'id' => $mainFolderId,
            'url' => $mainFolderLink
        ],
        'subfolders' => $createdFolders
    ];

    echo json_encode([
        'success' => true,
        'data' => $resultData
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
