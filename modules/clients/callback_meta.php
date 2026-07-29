<?php
// modules/clients/callback_meta.php
if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$platform = isset($_GET['platform']) ? $_GET['platform'] : 'facebook';

if (!$client_id) {
    exit('Missing client ID');
}

// Sandbox Mockup
$fake_account_id = rand(100000000, 999999999);
$fake_account_name = "Página de Prueba ($platform)";
$fake_token = "mock_token_" . md5(uniqid());

// Check if already exists
$stmt = $db->prepare("SELECT id FROM client_social_accounts WHERE client_id = ? AND platform = ?");
$stmt->execute([$client_id, $platform]);
if ($stmt->rowCount() > 0) {
    // Update
    $stmtUp = $db->prepare("UPDATE client_social_accounts SET account_id = ?, account_name = ?, access_token = ? WHERE client_id = ? AND platform = ?");
    $stmtUp->execute([$fake_account_id, $fake_account_name, $fake_token, $client_id, $platform]);
} else {
    // Insert
    $stmtIns = $db->prepare("INSERT INTO client_social_accounts (client_id, platform, account_id, account_name, access_token) VALUES (?, ?, ?, ?, ?)");
    $stmtIns->execute([$client_id, $platform, $fake_account_id, $fake_account_name, $fake_token]);
}

header("Location: index.php?module=clients&action=social_auth&client_id=" . $client_id);
exit();
