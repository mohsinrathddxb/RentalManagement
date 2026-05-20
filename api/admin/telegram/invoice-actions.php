<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/telegram_helpers.php';
require_once __DIR__ . '/../../../admin/functions/invoice_pdf_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

api_require_admin();
ensure_tenant_schema($connection);
ensure_invoice_pdf_columns($connection);

$input = api_get_json_input();
$invoiceNumber = isset($input['invoiceNumber']) ? trim((string) $input['invoiceNumber']) : '';
$action = isset($input['telegram_action']) ? trim((string) $input['telegram_action']) : '';

if ($invoiceNumber === '' || $action !== 'send_invoice') {
    api_json(['ok' => false, 'message' => 'A valid invoice and Telegram action are required.'], 422);
}

$invoiceRow = build_invoice_document_data($connection, $invoiceNumber);
if (!$invoiceRow) {
    api_json(['ok' => false, 'message' => 'Invoice record could not be found.'], 404);
}

$tenantId = isset($invoiceRow['tenantID']) ? (int) $invoiceRow['tenantID'] : 0;
if ($tenantId <= 0) {
    api_json(['ok' => false, 'message' => 'This invoice is not linked to a tenant.'], 422);
}

$chatId = isset($invoiceRow['telegram_chat_id'])
    ? normalize_telegram_chat_id((string) $invoiceRow['telegram_chat_id'])
    : '';
if ($chatId === '') {
    api_json(['ok' => false, 'message' => 'Telegram chat ID is missing for this tenant.'], 422);
}

$textResponse = send_telegram_message($chatId, build_invoice_telegram_text($invoiceRow));
if (empty($textResponse['ok'])) {
    $message = isset($textResponse['description']) && trim((string) $textResponse['description']) !== ''
        ? (string) $textResponse['description']
        : 'Invoice message could not be sent on Telegram.';
    api_json(['ok' => false, 'message' => $message], 502);
}

$binary = generate_invoice_pdf_binary($invoiceRow);
if ($binary === '') {
    api_json(['ok' => false, 'message' => 'Invoice PDF could not be generated for Telegram.'], 500);
}

$docResponse = send_telegram_document_bytes(
    $chatId,
    'invoice-' . $invoiceRow['invoiceNumber'] . '.pdf',
    $binary,
    'Invoice PDF'
);

if (empty($docResponse['ok'])) {
    $message = isset($docResponse['description']) && trim((string) $docResponse['description']) !== ''
        ? (string) $docResponse['description']
        : 'Invoice PDF could not be sent on Telegram.';
    api_json(['ok' => false, 'message' => $message], 502);
}

api_json(['ok' => true, 'message' => 'Invoice sent on Telegram successfully.']);
