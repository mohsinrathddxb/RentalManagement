<?php

require_once __DIR__ . "/invoice_pdf_helpers.php";

function normalize_telegram_username($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = ltrim($value, '@');
    $value = preg_replace('/[^A-Za-z0-9_]/', '', $value);

    return (string) $value;
}

function normalize_telegram_chat_id($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^0-9-]/', '', $value);
    if ($value === null || $value === '' || $value === '-') {
        return '';
    }

    return $value;
}

function telegram_is_configured()
{
    global $telegram_bot_token;

    return trim((string) $telegram_bot_token) !== '';
}

function telegram_api_request($method, $fields = [])
{
    global $telegram_bot_token;

    if (!telegram_is_configured()) {
        return ['ok' => false, 'description' => 'Telegram bot token is missing.'];
    }

    $ch = curl_init("https://api.telegram.org/bot" . $telegram_bot_token . "/" . $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error !== '') {
        return ['ok' => false, 'description' => $error !== '' ? $error : 'Telegram request failed.'];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'description' => 'Telegram returned invalid JSON.'];
    }

    return $decoded;
}

function telegram_get_updates($limit = 20)
{
    $limit = max(1, min(100, (int) $limit));

    $response = telegram_api_request('getUpdates', [
        'limit' => $limit
    ]);

    if (empty($response['ok']) || empty($response['result']) || !is_array($response['result'])) {
        return [];
    }

    return $response['result'];
}

function send_telegram_message($chatId, $text)
{
    $chatId = normalize_telegram_chat_id($chatId);
    if ($chatId === '' || trim((string) $text) === '') {
        return ['ok' => false, 'description' => 'Telegram chat ID or text is missing.'];
    }

    return telegram_api_request('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text
    ]);
}

function telegram_find_chat_id_by_username($username, $limit = 50)
{
    $username = strtolower(normalize_telegram_username($username));
    if ($username === '') {
        return '';
    }

    $updates = telegram_get_updates($limit);
    if (empty($updates)) {
        return '';
    }

    for ($index = count($updates) - 1; $index >= 0; $index--) {
        $message = isset($updates[$index]['message']) && is_array($updates[$index]['message']) ? $updates[$index]['message'] : [];
        $chat = isset($message['chat']) && is_array($message['chat']) ? $message['chat'] : [];
        $chatUsername = isset($chat['username']) ? strtolower((string) $chat['username']) : '';

        if ($chatUsername === $username && isset($chat['id'])) {
            return normalize_telegram_chat_id((string) $chat['id']);
        }
    }

    return '';
}

function send_telegram_document_bytes($chatId, $filename, $binaryContent, $caption = '')
{
    $chatId = normalize_telegram_chat_id($chatId);
    if ($chatId === '' || trim((string) $filename) === '' || (string) $binaryContent === '') {
        return ['ok' => false, 'description' => 'Telegram document payload is incomplete.'];
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'tgpdf_');
    if ($tempFile === false) {
        return ['ok' => false, 'description' => 'Could not create a temporary Telegram file.'];
    }

    file_put_contents($tempFile, $binaryContent);
    $curlFile = curl_file_create($tempFile, 'application/pdf', $filename);

    $response = telegram_api_request('sendDocument', [
        'chat_id' => $chatId,
        'caption' => trim((string) $caption),
        'document' => $curlFile
    ]);

    @unlink($tempFile);

    return $response;
}

function build_invoice_telegram_text($invoiceRow)
{
    $tenantName = isset($invoiceRow['tenant_name']) ? trim((string) $invoiceRow['tenant_name']) : 'Tenant';
    $firstName = $tenantName;
    if (strpos($tenantName, ' ') !== false) {
        $firstName = substr($tenantName, 0, strpos($tenantName, ' '));
    }

    $unitLabel = pdf_unit_label(
        isset($invoiceRow['house_name']) ? $invoiceRow['house_name'] : '',
        isset($invoiceRow['partition_number']) ? $invoiceRow['partition_number'] : ''
    );

    $lines = [
        "Hello " . $firstName . ",",
        "A new invoice has been issued for your stay at Co-Living Space.",
        "Invoice No: " . (string) $invoiceRow['invoiceNumber'],
        "Invoice Month: " . substr((string) $invoiceRow['dateOfInvoice'], 0, 7),
        "House / unit: " . $unitLabel,
        "Total Due: " . pdf_money(isset($invoiceRow['amountDue']) ? $invoiceRow['amountDue'] : 0)
    ];

    if (!empty($invoiceRow['dateDue'])) {
        $lines[] = "Due Date: " . (string) $invoiceRow['dateDue'];
    }

    $lines[] = "Your invoice PDF is attached.";

    return implode("\n", $lines);
}

function build_payment_telegram_text($paymentRow)
{
    $tenantName = isset($paymentRow['tenant_name']) ? trim((string) $paymentRow['tenant_name']) : 'Tenant';
    $firstName = $tenantName;
    if (strpos($tenantName, ' ') !== false) {
        $firstName = substr($tenantName, 0, strpos($tenantName, ' '));
    }

    $unitLabel = pdf_unit_label(
        isset($paymentRow['house_name']) ? $paymentRow['house_name'] : '',
        isset($paymentRow['partition_number']) ? $paymentRow['partition_number'] : ''
    );

    $balance = isset($paymentRow['balance']) ? (float) $paymentRow['balance'] : 0;
    $lines = [
        "Hello " . $firstName . ",",
        "We have recorded your payment at Co-Living Space.",
        "Invoice No: " . (string) $paymentRow['invoiceNumber'],
        "Receipt No: RCPT-" . (int) $paymentRow['paymentID'],
        "House / unit: " . $unitLabel,
        "Amount Paid: " . pdf_money(isset($paymentRow['amountPaid']) ? $paymentRow['amountPaid'] : 0),
        "Balance Due: " . pdf_money($balance)
    ];

    if ($balance <= 0) {
        $lines[] = "This invoice is now fully paid.";
    }

    $lines[] = "Your receipt PDF is attached.";

    return implode("\n", $lines);
}

function send_invoice_to_tenant_telegram($connection, $invoiceNumber, $tenantId)
{
    $tenantId = (int) $tenantId;
    if ($tenantId <= 0 || !telegram_is_configured()) {
        return false;
    }

    $invoiceRow = build_invoice_document_data($connection, $invoiceNumber, $tenantId);
    if (!$invoiceRow) {
        return false;
    }

    $chatId = isset($invoiceRow['telegram_chat_id']) ? normalize_telegram_chat_id($invoiceRow['telegram_chat_id']) : '';
    if ($chatId === '') {
        return false;
    }

    $binary = generate_invoice_pdf_binary($invoiceRow);
    if ($binary === '') {
        return false;
    }

    $textResponse = send_telegram_message($chatId, build_invoice_telegram_text($invoiceRow));
    $docResponse = send_telegram_document_bytes($chatId, 'invoice-' . $invoiceRow['invoiceNumber'] . '.pdf', $binary, 'Invoice PDF');

    return !empty($textResponse['ok']) && !empty($docResponse['ok']);
}

function send_payment_receipt_to_tenant_telegram($connection, $paymentId, $tenantId)
{
    $tenantId = (int) $tenantId;
    $paymentId = (int) $paymentId;

    if ($tenantId <= 0 || $paymentId <= 0 || !telegram_is_configured()) {
        return false;
    }

    $paymentRow = build_payment_receipt_data($connection, $paymentId, $tenantId);
    if (!$paymentRow) {
        return false;
    }

    $chatId = isset($paymentRow['telegram_chat_id']) ? normalize_telegram_chat_id($paymentRow['telegram_chat_id']) : '';
    if ($chatId === '') {
        return false;
    }

    $binary = generate_payment_receipt_pdf_binary($paymentRow);
    if ($binary === '') {
        return false;
    }

    $textResponse = send_telegram_message($chatId, build_payment_telegram_text($paymentRow));
    $docResponse = send_telegram_document_bytes($chatId, 'payment-receipt-' . (int) $paymentRow['paymentID'] . '.pdf', $binary, 'Payment receipt PDF');

    return !empty($textResponse['ok']) && !empty($docResponse['ok']);
}

function send_telegram_test_message_to_tenant($connection, $tenantId)
{
    $tenantId = (int) $tenantId;
    if ($tenantId <= 0 || !telegram_is_configured()) {
        return false;
    }

    $tenantResult = mysqli_query($connection, "
        SELECT `tenant_name`, `telegram_chat_id`
        FROM `tenants`
        WHERE `tenantID`='$tenantId'
        LIMIT 1
    ");

    if (!$tenantResult || mysqli_num_rows($tenantResult) !== 1) {
        return false;
    }

    $tenantRow = mysqli_fetch_assoc($tenantResult);
    $chatId = normalize_telegram_chat_id(isset($tenantRow['telegram_chat_id']) ? $tenantRow['telegram_chat_id'] : '');
    if ($chatId === '') {
        return false;
    }

    $tenantName = isset($tenantRow['tenant_name']) ? trim((string) $tenantRow['tenant_name']) : 'Tenant';
    $message = "Hello " . $tenantName . ", this is a Telegram delivery test from Co-Living Space. Your account is ready to receive invoices and payment receipts here.";

    $response = send_telegram_message($chatId, $message);

    return !empty($response['ok']);
}
