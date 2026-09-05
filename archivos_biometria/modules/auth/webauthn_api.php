<?php
// modules/auth/webauthn_api.php

header('Content-Type: application/json');

try {
    if (!file_exists('../../vendor/autoload.php')) {
        throw new \Exception("Vendor autoload not found");
    }
    require_once '../../vendor/autoload.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!file_exists('../../config/database.php')) {
        throw new \Exception("Database config not found");
    }
    require_once '../../config/database.php';

    $db = (new Database())->getConnection();
    if (!$db) {
        throw new \Exception("Database connection failed");
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $rpId = explode(':', $host)[0]; // Remove port if present
    $rpName = 'Roma Agencia';
    // Inicializamos con 'none' para evitar errores de atestación en Credential Manager de Android/iOS y enfocar en biometría de consumo
    $webauthn = new \lbuchs\WebAuthn\WebAuthn($rpName, $rpId, ['none']);

    if ($action === 'get_register_args') {
        if (!isset($_SESSION['user_id'])) {
            throw new \Exception('Not authenticated');
        }
        
        $userId = (string)$_SESSION['user_id'];
        $userName = $_SESSION['user_name'] ?? 'User';
        
        // residentKey='discouraged' evita que Google Credential Manager intercepte y muestre el menú de NFC/USB/passkey.
        $createArgs = $webauthn->getCreateArgs($userId, $userName, $userName, 30, 'discouraged', 'preferred', false);
        
        // Eliminar extensiones no estándar que hacen fallar a Google Credential Manager en Android
        if (isset($createArgs->publicKey->extensions)) {
            unset($createArgs->publicKey->extensions);
        }
        
        $_SESSION['webauthn_challenge'] = $webauthn->getChallenge();
        
        echo json_encode(['success' => true, 'args' => $createArgs]);
        exit;
    }

    if ($action === 'process_register') {
        if (!isset($_SESSION['user_id'])) {
            throw new \Exception('Not authenticated');
        }

        $clientDataJSON = base64_decode($_POST['clientDataJSON'] ?? '');
        $attestationObject = base64_decode($_POST['attestationObject'] ?? '');
        $challenge = $_SESSION['webauthn_challenge'] ?? '';

        $data = $webauthn->processCreate($clientDataJSON, $attestationObject, $challenge, 'preferred', true, false);
        
        $credentialId = base64_encode($data->credentialId);
        $credentialPublicKey = $data->credentialPublicKey;
        
        // Save to DB
        $stmt = $db->prepare("INSERT INTO user_webauthn_credentials (user_id, credential_id, public_key, created_at, last_used_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE public_key = ?, last_used_at = NOW()");
        $stmt->execute([$_SESSION['user_id'], $credentialId, $credentialPublicKey, $credentialPublicKey]);
        
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'get_login_args') {
        $email = $_POST['email'] ?? '';
        $credentialIds = [];
        
        if (!empty($email)) {
            $stmt = $db->prepare("SELECT c.credential_id FROM user_webauthn_credentials c JOIN users u ON c.user_id = u.id WHERE u.email = ?");
            $stmt->execute([$email]);
            while ($row = $stmt->fetch()) {
                $credentialIds[] = base64_decode($row['credential_id']);
            }
        }

        // Si mandamos $credentialIds, forzará a buscar esas llaves específicas (útil si no son descubribles).
        $getArgs = $webauthn->getGetArgs($credentialIds, 30, true, true, true, true, true, 'preferred');
        if (isset($getArgs->publicKey->extensions)) {
            unset($getArgs->publicKey->extensions);
        }
        $_SESSION['webauthn_challenge'] = $webauthn->getChallenge();
        echo json_encode(['success' => true, 'args' => $getArgs]);
        exit;
    }

    if ($action === 'process_login') {
        $clientDataJSON = base64_decode($_POST['clientDataJSON'] ?? '');
        $authenticatorData = base64_decode($_POST['authenticatorData'] ?? '');
        $signature = base64_decode($_POST['signature'] ?? '');
        $userHandle = base64_decode($_POST['userHandle'] ?? '');
        
        $rawIdInput = trim($_POST['id'] ?? '');
        // Convert from base64url to standard base64
        $normalizedId = strtr($rawIdInput, '-_', '+/');
        $remainder = strlen($normalizedId) % 4;
        if ($remainder) {
            $normalizedId .= str_repeat('=', 4 - $remainder);
        }
        $id = base64_decode($normalizedId);
        $challenge = $_SESSION['webauthn_challenge'] ?? '';

        $credentialIdEncoded = base64_encode($id);
        
        // Buscar llave pública
        $stmt = $db->prepare("SELECT user_id, public_key FROM user_webauthn_credentials WHERE credential_id = ?");
        $stmt->execute([$credentialIdEncoded]);
        $cred = $stmt->fetch();
        
        // Búsqueda alternativa por compatibilidad directa
        if (!$cred && !empty($rawIdInput)) {
            $stmt = $db->prepare("SELECT user_id, public_key FROM user_webauthn_credentials WHERE credential_id = ?");
            $stmt->execute([$rawIdInput]);
            $cred = $stmt->fetch();
        }
        
        if (!$cred) {
            throw new \Exception('Credencial biométrica no encontrada en la base de datos. Inicia sesión con correo y contraseña primero, y regístrala desde el Dashboard.');
        }

        $webauthn->processGet($clientDataJSON, $authenticatorData, $signature, $cred['public_key'], $challenge, null, 'preferred');
        
        // Registrar fecha de uso
        try {
            $upd = $db->prepare("UPDATE user_webauthn_credentials SET last_used_at = NOW(), sign_count = sign_count + 1 WHERE credential_id = ? OR credential_id = ?");
            $upd->execute([$credentialIdEncoded, $rawIdInput]);
        } catch (\Throwable $t) {}
        
        // Success login!
        $_SESSION['user_id'] = $cred['user_id'];
        // Fetch user data
        $ustmt = $db->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
        $ustmt->execute([$cred['user_id']]);
        $user = $ustmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            echo json_encode(['success' => true]);
        } else {
            throw new \Exception('Usuario no encontrado.');
        }
        exit;
    }

    throw new \Exception("Acción inválida");

} catch (\Throwable $e) {
    echo json_encode(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
    exit;
}
