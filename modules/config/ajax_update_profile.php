<?php
// modules/config/ajax_update_profile.php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_profile':
            $stmt = $db->prepare("SELECT id, name, username, email, phone, avatar FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'user' => $user]);
            break;

        case 'update_profile':
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio']);
                exit();
            }

            // Check username uniqueness
            if (!empty($username)) {
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $userId]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'error' => 'El nombre de usuario ya está en uso']);
                    exit();
                }
            }

            // Check email uniqueness
            if (!empty($email)) {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $userId]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'error' => 'El correo ya está en uso']);
                    exit();
                }
            }

            $stmt = $db->prepare("UPDATE users SET name = ?, username = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $username ?: null, $email, $phone ?: null, $userId]);

            // Update session name
            $_SESSION['user_name'] = $name;

            echo json_encode(['success' => true]);
            break;

        case 'update_password':
            $current = $_POST['current_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (empty($current) || empty($newPass)) {
                echo json_encode(['success' => false, 'error' => 'Todos los campos son obligatorios']);
                exit();
            }
            if ($newPass !== $confirm) {
                echo json_encode(['success' => false, 'error' => 'Las contraseñas no coinciden']);
                exit();
            }
            if (strlen($newPass) < 6) {
                echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
                exit();
            }

            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!password_verify($current, $user['password'])) {
                echo json_encode(['success' => false, 'error' => 'La contraseña actual es incorrecta']);
                exit();
            }

            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $userId]);

            echo json_encode(['success' => true]);
            break;

        case 'update_avatar':
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'No se recibió el archivo']);
                exit();
            }

            $file = $_FILES['avatar'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            
            if (!in_array($file['type'], $allowed)) {
                echo json_encode(['success' => false, 'error' => 'Formato no soportado. Usa JPG, PNG, WebP o GIF']);
                exit();
            }

            if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
                echo json_encode(['success' => false, 'error' => 'El archivo es demasiado grande (máx. 5MB)']);
                exit();
            }

            // Create uploads directory if not exists
            $uploadDir = '../../uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Delete old avatar if exists
            $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $old = $stmt->fetchColumn();
            if ($old && file_exists('../../' . $old)) {
                unlink('../../' . $old);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            $path = $uploadDir . $filename;
            $dbPath = 'uploads/avatars/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $path)) {
                echo json_encode(['success' => false, 'error' => 'Error al guardar el archivo']);
                exit();
            }

            $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$dbPath, $userId]);

            echo json_encode(['success' => true, 'avatar' => $dbPath]);
            break;

        case 'remove_avatar':
            $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $old = $stmt->fetchColumn();
            if ($old && file_exists('../../' . $old)) {
                unlink('../../' . $old);
            }

            $stmt = $db->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
            $stmt->execute([$userId]);

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
