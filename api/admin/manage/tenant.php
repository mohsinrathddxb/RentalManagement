<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/tenant_helpers.php';
require_once __DIR__ . '/../../../admin/functions/partition_helpers.php';
require_once __DIR__ . '/../../../admin/functions/telegram_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

api_require_admin();
ensure_tenant_schema($connection);
ensure_partition_tables($connection);

$input = api_get_json_input();
$action = isset($input['action']) ? trim((string) $input['action']) : '';
$tenantId = isset($input['tenantID']) ? (int) $input['tenantID'] : 0;

if ($tenantId <= 0 || ($action !== 'update' && $action !== 'delete')) {
    api_json(['ok' => false, 'message' => 'A valid tenant action is required.'], 422);
}

$tenantQuery = mysqli_query($connection, "
    SELECT `tenantID`, `tenant_name`, `houseNumber`, `partition_id`, `start_date`
    FROM `tenants`
    WHERE `tenantID` = '$tenantId'
      AND `tenant_status` = 'Active'
    LIMIT 1
");
$tenantRow = $tenantQuery ? mysqli_fetch_assoc($tenantQuery) : null;
if (!$tenantRow) {
    api_json(['ok' => false, 'message' => 'Active tenant could not be found.'], 404);
}

$currentHouseId = (int) $tenantRow['houseNumber'];
$currentPartitionId = (int) $tenantRow['partition_id'];

if ($action === 'update') {
    $houseId = isset($input['house_id']) ? (int) $input['house_id'] : 0;
    $partitionId = isset($input['partition_id']) ? (int) $input['partition_id'] : 0;
    $tenantName = is_username(isset($input['tname']) ? (string) $input['tname'] : '');
    $email = is_email(isset($input['temail']) ? (string) $input['temail'] : '');
    $idNumber = uncrack(isset($input['idnum']) ? (string) $input['idnum'] : '');
    $phoneCode = uncrack(isset($input['phone_code']) ? (string) $input['phone_code'] : '');
    $phoneLocal = uncrack(isset($input['phone_local']) ? (string) $input['phone_local'] : '');
    $profession = is_username(isset($input['prof']) ? (string) $input['prof'] : '');
    $telegramUsername = normalize_telegram_username(isset($input['telegram_username']) ? (string) $input['telegram_username'] : '');
    $telegramChatId = normalize_telegram_chat_id(isset($input['telegram_chat_id']) ? (string) $input['telegram_chat_id'] : '');
    $tenantAddress = uncrack(isset($input['tenant_address']) ? (string) $input['tenant_address'] : '');
    $homeCountryAddress = uncrack(isset($input['tenant_home_country_address']) ? (string) $input['tenant_home_country_address'] : '');
    $tenantCountry = is_username(isset($input['tenant_country']) ? (string) $input['tenant_country'] : '');
    $startDate = uncrack(isset($input['start_date']) ? (string) $input['start_date'] : '');
    $endDate = uncrack(isset($input['end_date']) ? (string) $input['end_date'] : '');
    $phoneNumber = trim($phoneCode . ' ' . $phoneLocal);

    if ($houseId <= 0 || $partitionId <= 0 || $tenantName === '' || $email === '' || $phoneCode === '' || $tenantCountry === '' || $startDate === '') {
        api_json(['ok' => false, 'message' => 'Tenant, stay, phone code, and country details are required.'], 422);
    }

    if ($endDate !== '' && strtotime($startDate) > strtotime($endDate)) {
        api_json(['ok' => false, 'message' => 'End date cannot be earlier than the start date.'], 422);
    }

    $partitionLookup = mysqli_query($connection, "
        SELECT `partition_id`, `house_id`, `partition_status`
        FROM `house_partitions`
        WHERE `partition_id` = '$partitionId'
          AND `house_id` = '$houseId'
        LIMIT 1
    ");
    $partitionRow = $partitionLookup ? mysqli_fetch_assoc($partitionLookup) : null;
    if (!$partitionRow) {
        api_json(['ok' => false, 'message' => 'Selected partition does not belong to the selected house.'], 422);
    }

    if ($partitionId !== $currentPartitionId) {
        $activeOnTargetResult = mysqli_query($connection, "SELECT COUNT(*) AS total FROM `tenants` WHERE `partition_id` = '$partitionId' AND `tenant_status` = 'Active'");
        $activeOnTargetRow = $activeOnTargetResult ? mysqli_fetch_assoc($activeOnTargetResult) : ['total' => 0];
        if ((int) $activeOnTargetRow['total'] > 0) {
            api_json(['ok' => false, 'message' => 'Selected partition already has an active tenant.'], 409);
        }
    }

    $safeTenantName = mysqli_real_escape_string($connection, $tenantName);
    $safeEmail = mysqli_real_escape_string($connection, $email);
    $safeIdNumber = mysqli_real_escape_string($connection, $idNumber);
    $safePhoneNumber = mysqli_real_escape_string($connection, $phoneNumber);
    $safeProfession = mysqli_real_escape_string($connection, $profession);
    $safeTelegramUsername = mysqli_real_escape_string($connection, $telegramUsername);
    $safeTelegramChatId = mysqli_real_escape_string($connection, $telegramChatId);
    $safeTenantAddress = mysqli_real_escape_string($connection, $tenantAddress);
    $safeHomeCountryAddress = mysqli_real_escape_string($connection, $homeCountryAddress);
    $safeTenantCountry = mysqli_real_escape_string($connection, $tenantCountry);
    $safeStartDate = mysqli_real_escape_string($connection, $startDate);
    $safeEndDate = mysqli_real_escape_string($connection, $endDate);
    $timesnap = date('Y-m-d : H:i:s');

    $sql = "
        UPDATE `tenants`
        SET
            `houseNumber` = '$houseId',
            `partition_id` = '$partitionId',
            `tenant_name` = '$safeTenantName',
            `email` = '$safeEmail',
            `ID_number` = '$safeIdNumber',
            `phone_number` = '$safePhoneNumber',
            `telegram_username` = '$safeTelegramUsername',
            `telegram_chat_id` = '$safeTelegramChatId',
            `profession` = '$safeProfession',
            `tenant_address` = '$safeTenantAddress',
            `tenant_home_country_address` = '$safeHomeCountryAddress',
            `tenant_country` = '$safeTenantCountry',
            `start_date` = '$safeStartDate',
            `end_date` = '$safeEndDate'
        WHERE `tenantID` = '$tenantId'
        LIMIT 1
    ";
    $transactionSql = "INSERT INTO `transactions` (`actor`,`time`,`description`) VALUES ('Admin ($username)', '$timesnap', '$username updated tenant ($safeTenantName) at $timesnap')";

    $mysqli->autocommit(false);
    $state = true;
    $mysqli->query($sql) ? null : $state = false;

    if ($state && !ensure_tenant_user_account($connection, $tenantId, $tenantName, $email, $phoneNumber)) {
        $state = false;
    }

    if ($state && $partitionId !== $currentPartitionId) {
        $mysqli->query("UPDATE `house_partitions` SET `partition_status`='Occupied' WHERE `partition_id`='$partitionId'") ? null : $state = false;
        $activeOldPartitionResult = $mysqli->query("SELECT COUNT(*) AS total FROM `tenants` WHERE `partition_id`='$currentPartitionId' AND `tenant_status`='Active'");
        $activeOldPartitionRow = $activeOldPartitionResult ? $activeOldPartitionResult->fetch_assoc() : ['total' => 0];
        $oldPartitionStatus = ((int) $activeOldPartitionRow['total'] > 0) ? 'Occupied' : 'Vacant';
        $mysqli->query("UPDATE `house_partitions` SET `partition_status`='$oldPartitionStatus' WHERE `partition_id`='$currentPartitionId'") ? null : $state = false;
    }

    if ($state && $partitionId === $currentPartitionId) {
        $mysqli->query("UPDATE `house_partitions` SET `partition_status`='Occupied' WHERE `partition_id`='$partitionId'") ? null : $state = false;
    }

    $mysqli->query($transactionSql) ? null : $state = false;

    if (!$state) {
        $mysqli->rollback();
        api_json(['ok' => false, 'message' => 'Tenant could not be updated.'], 500);
    }

    $mysqli->commit();
    sync_house_status_from_partitions($connection, $currentHouseId, 'Vacant');
    sync_house_status_from_partitions($connection, $houseId, 'Vacant');
    api_json(['ok' => true, 'message' => 'Tenant updated successfully.']);
}

$exitDate = uncrack(isset($input['exit_date']) ? (string) $input['exit_date'] : '');
$startDateForExit = isset($tenantRow['start_date']) ? (string) $tenantRow['start_date'] : '';
if ($exitDate === '') {
    api_json(['ok' => false, 'message' => 'Exit date is required to move out this tenant.'], 422);
}

if ($startDateForExit !== '' && strtotime($exitDate) < strtotime($startDateForExit)) {
    api_json(['ok' => false, 'message' => 'Exit date cannot be earlier than the tenant start date.'], 422);
}

$safeExitDate = mysqli_real_escape_string($connection, $exitDate);
$safeTenantName = mysqli_real_escape_string($connection, (string) $tenantRow['tenant_name']);
$timesnap = date('Y-m-d : H:i:s');
$mysqli->autocommit(false);
$state = true;
$mysqli->query("UPDATE `tenants` SET `tenant_status`='Deleted&Moved_Out', `exit_date`='$safeExitDate' WHERE `tenantID`='$tenantId' LIMIT 1") ? null : $state = false;
$mysqli->query("INSERT INTO `transactions` (`actor`,`time`,`description`) VALUES ('Admin ($username)', '$timesnap', '$username moved out tenant ($safeTenantName) with exit date $safeExitDate at $timesnap')") ? null : $state = false;

if ($state) {
    $activePartitionResult = $mysqli->query("SELECT COUNT(*) AS total FROM `tenants` WHERE `partition_id`='$currentPartitionId' AND `tenant_status`='Active'");
    $activePartitionRow = $activePartitionResult ? $activePartitionResult->fetch_assoc() : ['total' => 0];
    $partitionStatus = ((int) $activePartitionRow['total'] > 0) ? 'Occupied' : 'Vacant';
    $mysqli->query("UPDATE `house_partitions` SET `partition_status`='$partitionStatus' WHERE `partition_id`='$currentPartitionId'") ? null : $state = false;
}

if (!$state) {
    $mysqli->rollback();
    api_json(['ok' => false, 'message' => 'Tenant could not be moved out.'], 500);
}

$mysqli->commit();
sync_house_status_from_partitions($connection, $currentHouseId, 'Vacant');
api_json(['ok' => true, 'message' => 'Tenant moved out successfully.']);
