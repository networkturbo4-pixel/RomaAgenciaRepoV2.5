<?php
session_start();
$_POST = [
    'quote_id' => '0',
    'client_name' => 'Test Client',
    'issue_date' => '2026-05-10',
    'due_date' => '2026-05-25',
    'currency' => 'PEN',
    'status' => 'Borrador',
    'tax_rate' => '18',
    'notes' => 'Test',
    'terms_conditions' => 'Test',
    'show_payment_methods' => 1,
    'payment_methods_text' => 'Test text',
    'items' => [
        [
            'service_id' => '',
            'description' => 'Test item',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'icon' => '',
            'gantt_start_date' => '2026-05-10',
            'gantt_duration' => '5'
        ]
    ]
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['user_id'] = 1;

require 'c:/xampp/htdocs/CESARMENDOZA/modules/quotes/ajax_save_quote.php';
