<?php
$apiKey = 'AQ.Ab8RN6IMDdwCwC9tCRzve5p6Vf8te8CVRhFAjucDPSCJ9wy5Mg';
$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
if (isset($data['models'])) {
    foreach($data['models'] as $m) {
        echo $m['name'] . "\n";
    }
} else {
    echo "No models array found. Response:\n" . $response;
}
