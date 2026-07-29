<?php
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Debes iniciar sesión con correo y contraseña primero para poder eliminar tu biometría.");
}

$db = (new Database())->getConnection();
$stmt = $db->prepare("DELETE FROM user_webauthn_credentials WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);

echo "<h2>Tus registros biométricos han sido eliminados correctamente.</h2>";
echo "<p>Ya puedes volver al dashboard y registrar tu huella nuevamente.</p>";
echo "<a href='index.php?module=dashboard&action=index'>Volver al Dashboard</a>";
?>
