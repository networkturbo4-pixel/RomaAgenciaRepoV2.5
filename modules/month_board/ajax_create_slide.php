<?php
// modules/month_board/ajax_create_slide.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/GoogleDriveHelper.php';

$month_id = isset($_POST['month_id']) ? (int)$_POST['month_id'] : 0;
$title = isset($_POST['title']) && !empty($_POST['title']) ? trim($_POST['title']) : 'Nueva Presentación';

if ($month_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de mes inválido.']);
    exit();
}

try {
    $db = (new Database())->getConnection();
    
    // 1. Buscar si ya existe una presentación maestra en alguna publicación de este mes
    $stmtSlide = $db->prepare("SELECT drive_images FROM month_posts WHERE month_id = ? AND drive_images LIKE '%presentation%' AND drive_images != '' ORDER BY id ASC LIMIT 1");
    $stmtSlide->execute([$month_id]);
    $existingSlide = $stmtSlide->fetch(PDO::FETCH_ASSOC);

    if ($existingSlide && !empty($existingSlide['drive_images'])) {
        $masterUrl = $existingSlide['drive_images'];
        $drive = new GoogleDriveHelper();
        
        // Extraer el presentationId de la URL (ej. https://docs.google.com/presentation/d/1ABC.../edit)
        preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $masterUrl, $matches);
        
        if (isset($matches[1]) && $drive->isConfigured()) {
            $presentationId = $matches[1];
            // Agregar diapositiva en blanco
            $slideId = $drive->appendSlideToPresentation($presentationId);
            
            if ($slideId) {
                // Remover cualquier hash previo de la URL maestra por si acaso
                $cleanUrl = explode('#', $masterUrl)[0];
                $masterUrl = $cleanUrl . '#slide=id.' . $slideId;
            }
        }

        // Devolver la presentación maestra con el deep link a la nueva hoja
        echo json_encode([
            'success' => true,
            'url' => $masterUrl,
            'id' => '', 
            'is_master' => true
        ]);
        exit();
    }

    // 2. Si no existe, preparamos la creación del Documento Maestro
    $stmt = $db->prepare("SELECT month, year, drive_folder_id, drive_folders_json FROM project_months WHERE id = ?");
    $stmt->execute([$month_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $targetFolderId = null;
    $masterTitle = 'Referencias Visuales';
    
    if ($row) {
        $monthNames = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $monthNum = (int)$row['month'];
        $monthName = isset($monthNames[$monthNum]) ? $monthNames[$monthNum] : 'Mes';

        $masterTitle = "Referencias Visuales - " . $monthName . " " . $row['year'];
        $targetFolderId = $row['drive_folder_id']; // Fallback principal
        
        // Buscar subcarpeta REFERENCIAS
        if (!empty($row['drive_folders_json'])) {
            $foldersData = json_decode($row['drive_folders_json'], true);
            if (isset($foldersData['subfolders'])) {
                foreach ($foldersData['subfolders'] as $sf) {
                    if ($sf['name'] === 'REFERENCIAS') {
                        $targetFolderId = $sf['id'];
                        break;
                    }
                }
            }
        }
    }

    $drive = new GoogleDriveHelper();
    
    if (!$drive->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'Google Drive no está conectado en el sistema.']);
        exit();
    }

    // Crear el slide maestro
    $slideData = $drive->createGoogleSlide($masterTitle, $targetFolderId ?: null);

    if ($slideData && isset($slideData['webViewLink'])) {
        echo json_encode([
            'success' => true,
            'url' => $slideData['webViewLink'],
            'id' => $slideData['id']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al comunicarse con la API de Google Drive.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Excepción: ' . $e->getMessage()]);
}
