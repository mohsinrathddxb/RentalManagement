<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/telegram_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

api_require_admin();
ensure_tenant_schema($connection);

$input = api_get_json_input();
$tenantId = isset($input['tenant_id']) ? (int) $input['tenant_id'] : 0;
$action = isset($input['telegram_action']) ? trim((string) $input['telegram_action']) : '';

if ($tenantId <= 0 || ($action !== 'fetch_chat_id' && $action !== 'send_test')) {
    api_json(['ok' => false, 'message' => 'A valid tenant and Telegram action are required.'], 422);
}

$tenantQuery = mysqli_query($connection, "
    SELECT `tenant_name`, `telegram_username`, `telegram_chat_id`
    FROM `tenants`
    WHERE `tenantID`='$tenantId'
    LIMIT 1
");

if (!$tenantQuery || mysqli_num_rows($tenantQuery) !== 1) {
    api_json(['ok' => false, 'message' => 'Tenant record could not be found.'], 404);
}

$tenantRow = mysqli_fetch_assoc($tenantQuery);

if ($action === 'fetch_chat_id') {
    $telegramUsername = normalize_telegram_username(isset($tenantRow['telegram_username']) ? $tenantRow['telegram_username'] : '');
    if ($telegramUsername === '') {
        api_json(['ok' => false, 'message' => 'Telegram username is missing for this tenant.'], 422);
    }

    $chatId = telegram_find_chat_id_by_username($telegramUsername, 50);
    if ($chatId === '') {
        api_json([
            'ok' => false,
            'message' => 'Telegram chat ID could not be found. Ask the tenant to message your bot first, then try again.',
        ], 404);
    }

    $safeChatId = mysqli_real_escape_string($connection, $chatId);
    if (!mysqli_query($connection, "UPDATE `tenants` SET `telegram_chat_id`='$safeChatId' WHERE `tenantID`='$tenantId'")) {
        api_json(['ok' => false, 'message' => 'Telegram chat ID was found but could not be saved.'], 500);
    }

    api_json([
        'ok' => true,
        'message' => 'Telegram chat ID fetched successfully.',
        'chat_id' => $chatId,
    ]);
}

$chatId = normalize_telegram_chat_id(isset($tenantRow['telegram_chat_id']) ? $tenantRow['telegram_chat_id'] : '');
if ($chatId === '') {
    api_json(['ok' => false, 'message' => 'Telegram chat ID is missing for this tenant.'], 422);
}

$response = send_telegram_message(
    $chatId,
    'Hello ' . (isset($tenantRow['tenant_name']) ? trim((string) $tenantRow['tenant_name']) : 'Tenant') . ', this is a Telegram delivery test from Co-Living Space. Your account is ready to receive invoices and payment receipts here.'
);

if (empty($response['ok'])) {
    $message = isset($response['description']) && trim((string) $response['description']) !== ''
        ? (string) $response['description']
        : 'Telegram test message failed.';
    api_json(['ok' => false, 'message' => $message], 502);
}

api_json(['ok' => true, 'message' => 'Telegram test message sent successfully.']);
