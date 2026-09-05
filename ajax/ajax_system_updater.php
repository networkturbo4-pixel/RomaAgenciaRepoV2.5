<?php
// ajax/ajax_system_updater.php
// API para comprobación y ejecución de actualizaciones a 1 clic desde GitHub

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión expirada.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SystemUpdater.php';

$db = (new Database())->getConnection();

// Verificar rol de Administrador
$stmt = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() != 1) {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado: Solo el Administrador puede gestionar actualizaciones.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_info';
$updater = new SystemUpdater($db);

try {
    switch ($action) {
        case 'get_info':
            $info = $updater->getLocalInfo();
            echo json_encode(['success' => true, 'data' => $info]);
            break;

        case 'check':
            $result = $updater->checkUpdates();
            echo json_encode(['success' => true, 'data' => $result, 'logs' => $updater->getLogs()]);
            break;

        case 'update':
            @set_time_limit(0);
            @ini_set('memory_limit', '1024M');
            $updateResult = $updater->runOneClickUpdate();
            echo json_encode($updateResult);
            break;

        case 'save_repo':
            $repoUrl = $_POST['repo_url'] ?? '';
            $branch = $_POST['branch'] ?? 'main';

            if (empty($repoUrl)) {
                echo json_encode(['success' => false, 'error' => 'La URL del repositorio es obligatoria.']);
                exit;
            }

            $updater->setRemoteRepository($repoUrl, $branch);
            $info = $updater->getLocalInfo();

            echo json_encode([
                'success' => true,
                'message' => 'Configuración de repositorio guardada exitosamente.',
                'data' => $info
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción desconocida.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
