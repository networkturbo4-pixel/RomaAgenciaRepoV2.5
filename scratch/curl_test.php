<?php
$url = 'http://localhost/CESARMENDOZA/modules/quotes/ajax_save_quote.php';
$data = [
    'quote_id' => '0',
    'client_name' => 'Test Client API',
    'issue_date' => '2026-05-10',
    'due_date' => '2026-05-25',
    'currency' => 'PEN',
    'status' => 'Borrador',
    'tax_rate' => '18',
    'notes' => 'test',
    'terms_conditions' => 'test',
    'show_payment_methods' => '1',
    'payment_methods_text' => 'test',
    'items[0][description]' => 'Test item',
    'items[0][quantity]' => '1',
    'items[0][unit_price]' => '100',
    'items[0][discount]' => '0',
    'items[0][gantt_start_date]' => '2026-05-10',
    'items[0][gantt_duration]' => '5'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Set cookie to simulate login (get a valid session ID first if needed)
// Wait, I can just temporarily comment out auth check in ajax_save_quote.php for this test!

$response = curl_exec($ch);
echo "Response: " . $response;
curl_close($ch);
