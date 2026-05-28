$postParams = @{
    quote_id = '0'
    client_name = 'Test Client'
    issue_date = '2026-05-10'
    due_date = '2026-05-25'
    currency = 'PEN'
    status = 'Borrador'
    tax_rate = '18'
    notes = 'Test'
    terms_conditions = 'Test'
    show_payment_methods = '1'
    payment_methods_text = 'Test text'
    'items[0][service_id]' = ''
    'items[0][description]' = 'Test item'
    'items[0][quantity]' = '1'
    'items[0][unit_price]' = '100'
    'items[0][discount]' = '0'
    'items[0][gantt_start_date]' = '2026-05-10'
    'items[0][gantt_duration]' = '5'
}

$response = Invoke-WebRequest -Uri "http://localhost/CESARMENDOZA/modules/quotes/ajax_save_quote.php" -Method Post -Body $postParams
$response.Content
