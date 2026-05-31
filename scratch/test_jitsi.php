<?php
$servers = [
    'meet.jit.si',
    'jitsi.riot.im',
    'meet.element.io',
    'meet.gnome.org',
    'meet.nixnet.services',
    'jitsi.linux.it',
    'jitsi.member.fsf.org',
    'calls.disroot.org',
    'meet.kabi.tk',
    'meet.adminforge.de'
];

foreach ($servers as $server) {
    $url = "https://$server/";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    
    if ($response) {
        $headers = [];
        foreach (explode("\n", $response) as $line) {
            if (strpos(strtolower($line), 'x-frame-options') !== false || strpos(strtolower($line), 'content-security-policy') !== false) {
                $headers[] = trim($line);
            }
        }
        echo "$server: \n";
        if (empty($headers)) {
            echo "  (No X-Frame-Options or CSP found. Might allow embedding!)\n";
        } else {
            foreach ($headers as $h) {
                echo "  $h\n";
            }
        }
    } else {
        echo "$server: Timeout or failed\n";
    }
    curl_close($ch);
}
?>
