<?php
// Generate VAPID keys using native openssl
$config = array(
    "curve_name" => "prime256v1",
    "private_key_type" => OPENSSL_KEYTYPE_EC,
);

$res = openssl_pkey_new($config);
if (!$res) {
    // Try with explicit openssl config
    $config['config'] = 'C:/xampp/apache/conf/openssl.cnf';
    $res = openssl_pkey_new($config);
}

if ($res) {
    $details = openssl_pkey_get_details($res);
    $x = rtrim(strtr(base64_encode($details['ec']['x']), '+/', '-_'), '=');
    $y = rtrim(strtr(base64_encode($details['ec']['y']), '+/', '-_'), '=');
    $d = rtrim(strtr(base64_encode($details['ec']['d']), '+/', '-_'), '=');
    
    // Uncompressed public key = 0x04 + x + y
    $publicKey = rtrim(strtr(base64_encode("\x04" . $details['ec']['x'] . $details['ec']['y']), '+/', '-_'), '=');
    $privateKey = $d;
    
    echo "PUBLIC: " . $publicKey . "\n";
    echo "PRIVATE: " . $privateKey . "\n";
} else {
    // Fallback: use pre-generated test keys
    echo "OpenSSL not available. Using pre-generated keys.\n";
    echo "PUBLIC: BEL5KJMVPWVbXqJLQm-_e5-dEh4b7NPOW60U4lYRGzB2WW3lyp35xZKN_eLb6g0S6FXPW5GEhFH0h0jDVYkHjo\n";
    echo "PRIVATE: 2RV-nPz0WyuP2PD7WFBH7rSYVY0nQN5A6qOu_kX5tbo\n";
}
