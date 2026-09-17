<?php
// includes/csrf.php
// Sistema centralizado de tokens de protección CSRF (Cross-Site Request Forgery)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Obtiene o inicializa el token CSRF de la sesión actual
 * @return string
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Genera el campo oculto HTML para formularios estándar
 * @return string
 */
function csrf_field() {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Valida un token recibido contra el token en sesión
 * @param string|null $providedToken
 * @return bool
 */
function csrf_validate($providedToken = null) {
    if ($providedToken === null) {
        $providedToken = $_POST['csrf_token'] 
            ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
            ?? $_SERVER['HTTP_X_XSRF_TOKEN'] 
            ?? '';
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken) || empty($providedToken)) {
        return false;
    }

    return hash_equals($sessionToken, $providedToken);
}

/**
 * Exige validación CSRF en peticiones de modificación (POST, PUT, DELETE, PATCH)
 * Si la validación falla, responde con código 403 Forbidden y finaliza
 */
function csrf_verify_request() {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array(strtoupper($method), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
        if (!csrf_validate()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Token de seguridad inválido o sesión expirada (Error CSRF). Recargue la página e intente nuevamente.'
            ]);
            exit;
        }
    }
}
