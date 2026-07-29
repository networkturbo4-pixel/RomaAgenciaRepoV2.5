<?php
// ajax/ajax_asistencia.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../config/database.php';
$dbClass = new Database();
$db = $dbClass->getConnection();

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$valid_actions = [
    'entrada', 'inicio_refrigerio', 'fin_refrigerio', 'salida', 'status', 
    'request_permiso', 'admin_today_status', 'get_permisos', 'update_permiso_status'
];

if (!in_array($action, $valid_actions)) {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit();
}

// Helper para verificar si es admin
$stmt_admin = $db->prepare("SELECT role_id FROM users WHERE id = ?");
$stmt_admin->execute([$user_id]);
$is_admin = ($stmt_admin->fetchColumn() == 1);

try {
    // Check if there's a record for today
    $stmt = $db->prepare("SELECT * FROM asistencias WHERE user_id = ? AND fecha = CURDATE()");
    $stmt->execute([$user_id]);
    $asistencia = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($action === 'status') {
        echo json_encode(['success' => true, 'data' => $asistencia]);
        exit();
    }

    if ($action === 'entrada') {
        if ($asistencia) {
            echo json_encode(['success' => false, 'message' => 'Ya marcaste tu entrada hoy.']);
            exit();
        }
        $stmt = $db->prepare("INSERT INTO asistencias (user_id, fecha, entrada) VALUES (?, CURDATE(), NOW())");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true, 'message' => 'Entrada registrada con éxito.']);
        exit();
    }

    if ($action === 'request_permiso') {
        $motivo = $_POST['motivo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        
        if (empty($motivo)) {
            echo json_encode(['success' => false, 'message' => 'El motivo es obligatorio.']);
            exit();
        }

        $imagenes = [];
        if (isset($_FILES['imagenes'])) {
            $upload_dir = '../uploads/permisos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_count = count($_FILES['imagenes']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['imagenes']['error'][$i] === 0) {
                    $ext = pathinfo($_FILES['imagenes']['name'][$i], PATHINFO_EXTENSION);
                    $filename = uniqid('perm_') . '_' . time() . '.' . $ext;
                    $target = $upload_dir . $filename;
                    if (move_uploaded_file($_FILES['imagenes']['tmp_name'][$i], $target)) {
                        $imagenes[] = 'uploads/permisos/' . $filename;
                    }
                }
            }
        }
        
        $imagenes_json = json_encode($imagenes);
        $stmt = $db->prepare("INSERT INTO asistencia_permisos (user_id, motivo, descripcion, imagenes_json) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $motivo, $descripcion, $imagenes_json]);
        
        echo json_encode(['success' => true, 'message' => 'Permiso solicitado correctamente.']);
        exit();
    }

    if ($action === 'admin_today_status') {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }
        // Obtener usuarios con su estado de asistencia de hoy
        $query = "
            SELECT u.id, u.name, u.email, 
                   a.entrada, a.inicio_refrigerio, a.fin_refrigerio, a.salida,
                   p.estado as estado_permiso, p.motivo as motivo_permiso
            FROM users u
            LEFT JOIN asistencias a ON u.id = a.user_id AND a.fecha = CURDATE()
            LEFT JOIN asistencia_permisos p ON u.id = p.user_id AND DATE(p.created_at) = CURDATE()
            WHERE a.id IS NOT NULL OR p.id IS NOT NULL
            ORDER BY u.name ASC
        ";
        $stmt = $db->query($query);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
        exit();
    }

    if ($action === 'get_permisos') {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }
        $query = "
            SELECT p.*, u.name as user_name 
            FROM asistencia_permisos p 
            JOIN users u ON p.user_id = u.id 
            ORDER BY p.created_at DESC
        ";
        $stmt = $db->query($query);
        $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $permisos]);
        exit();
    }

    if ($action === 'update_permiso_status') {
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }
        $permiso_id = $_POST['permiso_id'] ?? 0;
        $estado = $_POST['estado'] ?? '';
        $respuesta = $_POST['respuesta'] ?? '';
        
        if (!in_array($estado, ['Aprobado', 'Rechazado'])) {
            echo json_encode(['success' => false, 'message' => 'Estado inválido.']);
            exit();
        }
        
        $stmt = $db->prepare("UPDATE asistencia_permisos SET estado = ?, respuesta_jefe = ? WHERE id = ?");
        $stmt->execute([$estado, $respuesta, $permiso_id]);
        
        echo json_encode(['success' => true, 'message' => 'Permiso actualizado.']);
        exit();
    }

    if (!$asistencia) {
        echo json_encode(['success' => false, 'message' => 'Debes marcar tu entrada primero.']);
        exit();
    }

    if ($action === 'inicio_refrigerio') {
        if ($asistencia['inicio_refrigerio']) {
            echo json_encode(['success' => false, 'message' => 'Ya iniciaste tu refrigerio hoy.']);
            exit();
        }
        if ($asistencia['salida']) {
            echo json_encode(['success' => false, 'message' => 'Ya marcaste tu salida hoy.']);
            exit();
        }
        $stmt = $db->prepare("UPDATE asistencias SET inicio_refrigerio = NOW() WHERE id = ?");
        $stmt->execute([$asistencia['id']]);
        echo json_encode(['success' => true, 'message' => 'Inicio de refrigerio registrado.']);
        exit();
    }

    if ($action === 'fin_refrigerio') {
        if (!$asistencia['inicio_refrigerio']) {
            echo json_encode(['success' => false, 'message' => 'Debes iniciar tu refrigerio primero.']);
            exit();
        }
        if ($asistencia['fin_refrigerio']) {
            echo json_encode(['success' => false, 'message' => 'Ya finalizaste tu refrigerio hoy.']);
            exit();
        }
        if ($asistencia['salida']) {
            echo json_encode(['success' => false, 'message' => 'Ya marcaste tu salida hoy.']);
            exit();
        }
        $stmt = $db->prepare("UPDATE asistencias SET fin_refrigerio = NOW() WHERE id = ?");
        $stmt->execute([$asistencia['id']]);
        echo json_encode(['success' => true, 'message' => 'Fin de refrigerio registrado.']);
        exit();
    }

    if ($action === 'salida') {
        if ($asistencia['salida']) {
            echo json_encode(['success' => false, 'message' => 'Ya marcaste tu salida hoy.']);
            exit();
        }
        $stmt = $db->prepare("UPDATE asistencias SET salida = NOW() WHERE id = ?");
        $stmt->execute([$asistencia['id']]);
        echo json_encode(['success' => true, 'message' => 'Salida registrada con éxito.']);
        exit();
    }

} catch (Exception $e) {
    error_log("Asistencia Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor.']);
}
