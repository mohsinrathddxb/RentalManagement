<?php

require_once "db.php";
require_once "tenant_helpers.php";
require_once "telegram_helpers.php";

session_start();

if (!is_logged_in_temporary() || !is_admin_user()) {
    header('location:../index.php?restricted=1');
    exit();
}

ensure_tenant_schema($connection);

$tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : 0;
$action = isset($_POST['telegram_action']) ? trim((string) $_POST['telegram_action']) : '';

if ($tenantId <= 0 || ($action !== 'fetch_chat_id' && $action !== 'send_test')) {
    header('location:../tenants.php?telegram_error=1');
    exit();
}

$tenantQuery = mysqli_query($connection, "
    SELECT `tenant_name`, `telegram_username`, `telegram_chat_id`
    FROM `tenants`
    WHERE `tenantID`='$tenantId'
    LIMIT 1
");

if (!$tenantQuery || mysqli_num_rows($tenantQuery) !== 1) {
    header('location:../tenants.php?telegram_missing=1');
    exit();
}

$tenantRow = mysqli_fetch_assoc($tenantQuery);

if ($action === 'fetch_chat_id') {
    $telegramUsername = normalize_telegram_username(isset($tenantRow['telegram_username']) ? $tenantRow['telegram_username'] : '');
    if ($telegramUsername === '') {
        header('location:../tenants.php?telegram_username_missing=1');
        exit();
    }

    $chatId = telegram_find_chat_id_by_username($telegramUsername, 50);
    if ($chatId === '') {
        header('location:../tenants.php?telegram_chat_not_found=1');
        exit();
    }

    $safeChatId = mysqli_real_escape_string($connection, $chatId);
    mysqli_query($connection, "UPDATE `tenants` SET `telegram_chat_id`='$safeChatId' WHERE `tenantID`='$tenantId'");

    header('location:../tenants.php?telegram_fetched=1');
    exit();
}

if ($action === 'send_test') {
    $chatId = normalize_telegram_chat_id(isset($tenantRow['telegram_chat_id']) ? $tenantRow['telegram_chat_id'] : '');
    if ($chatId === '') {
        header('location:../tenants.php?telegram_chat_missing=1');
        exit();
    }

    if (send_telegram_test_message_to_tenant($connection, $tenantId)) {
        header('location:../tenants.php?telegram_test_sent=1');
        exit();
    }

    header('location:../tenants.php?telegram_test_failed=1');
    exit();
}

header('location:../tenants.php?telegram_error=1');
exit();
