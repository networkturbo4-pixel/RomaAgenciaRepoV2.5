<?php
session_start();
$_SESSION['user_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'quote_id' => 0,
    'client_name' => 'Cesar Mendoza',
    'issue_date' => '2026-05-10',
    'due_date' => '2026-05-25',
    'currency' => 'PEN',
    'status' => 'Borrador',
    'tax_rate' => 0,
    'notes' => '',
    'terms_conditions' => '',
    'show_payment_methods' => 1,
    'payment_methods_text' => '',
    'items' => [
        [
            'service_id' => null,
            'description' => 'Test',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'icon' => '',
            'gantt_start_date' => '2026-05-10',
            'gantt_duration' => 1
        ]
    ]
];
require 'modules/quotes/ajax_save_quote.php';
