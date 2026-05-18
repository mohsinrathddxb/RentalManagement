<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/country_options.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

api_require_admin();
ensure_partition_tables($connection);
ensure_tenant_schema($connection);

$input = api_get_json_input();
$house = isset($input['house']) ? uncrack((string) $input['house']) : '';
$partitionId = isset($input['partition_id']) ? (int) $input['partition_id'] : 0;
$tenantRows = isset($input['tenants']) && is_array($input['tenants']) ? $input['tenants'] : [];

if ($house === '' || strpos($house, '_') === false || $partitionId <= 0 || count($tenantRows) === 0) {
    api_json(['ok' => false, 'message' => 'House, partition, and at least one tenant are required.'], 422);
}

$dateAdmitted = date('20y-m-d');
$houseid = substr($house, 0, strpos($house, '_'));
$timesnap = date('Y-m-d : H:i:s');

$recHouse = mysqli_query($conn, "SELECT `house_name`,`rent_amount` FROM `houses` WHERE `houseID`='$houseid'");
$houseItem = $recHouse ? mysqli_fetch_array($recHouse, MYSQLI_BOTH) : null;
if (!$houseItem) {
    api_json(['ok' => false, 'message' => 'House could not be found.'], 404);
}

$recPartition = mysqli_query($conn, "SELECT `partition_number`,`rent_amount` FROM `house_partitions` WHERE `partition_id`='$partitionId' AND `house_id`='$houseid'");
$partitionItem = $recPartition ? mysqli_fetch_array($recPartition, MYSQLI_BOTH) : null;
if (!$partitionItem) {
    api_json(['ok' => false, 'message' => 'The selected partition could not be found for this house.'], 404);
}

$hsname = $houseItem['house_name'];
$partitionNumber = $partitionItem['partition_number'];
$tenantCountValue = count($tenantRows);
$sqHouses = "UPDATE `houses` SET `house_status`='Occupied' WHERE `houseID`='$houseid'";
$sqPartitionUpdate = "UPDATE `house_partitions` SET `partition_status`='Occupied' WHERE `partition_id`='$partitionId'";
$sqlTransactions = "INSERT INTO `transactions` (`actor`,`time`,`description`) VALUES ('Admin ($username)', '$timesnap','$username admitted $tenantCountValue tenant(s) to $hsname partition $partitionNumber at $timesnap')";

$mysqli->autocommit(false);
$status = true;
$dateError = false;
$accountWarning = false;

foreach ($tenantRows as $tenantRow) {
    $tname = is_username(isset($tenantRow['tname']) ? (string) $tenantRow['tname'] : '');
    $temail = is_email(isset($tenantRow['temail']) ? (string) $tenantRow['temail'] : '');
    $idnum = uncrack(isset($tenantRow['idnum']) ? (string) $tenantRow['idnum'] : '');
    $phoneCode = uncrack(isset($tenantRow['phone_code']) ? (string) $tenantRow['phone_code'] : '');
    $phoneLocal = uncrack(isset($tenantRow['phone_local']) ? (string) $tenantRow['phone_local'] : '');
    $prof = is_username(isset($tenantRow['prof']) ? (string) $tenantRow['prof'] : '');
    $telegramUsername = normalize_telegram_username(isset($tenantRow['telegram_username']) ? (string) $tenantRow['telegram_username'] : '');
    $telegramChatId = normalize_telegram_chat_id(isset($tenantRow['telegram_chat_id']) ? (string) $tenantRow['telegram_chat_id'] : '');
    $tenantAddress = uncrack(isset($tenantRow['tenant_address']) ? (string) $tenantRow['tenant_address'] : '');
    $tenantHomeCountryAddress = uncrack(isset($tenantRow['tenant_home_country_address']) ? (string) $tenantRow['tenant_home_country_address'] : '');
    $tenantCountry = is_username(isset($tenantRow['tenant_country']) ? (string) $tenantRow['tenant_country'] : '');
    $startDate = uncrack(isset($tenantRow['start_date']) ? (string) $tenantRow['start_date'] : '');
    $endDate = uncrack(isset($tenantRow['end_date']) ? (string) $tenantRow['end_date'] : '');

    if ($tname === '' || $temail === '' || $startDate === '' || $phoneCode === '') {
        $status = false;
        break;
    }

    if ($endDate !== '' && strtotime($startDate) > strtotime($endDate)) {
        $status = false;
        $dateError = true;
        break;
    }

    $phone = trim($phoneCode . ' ' . $phoneLocal);
    $sqTenants = "INSERT INTO `tenants` (`houseNumber`,`partition_id`,`tenant_name`,`email`,`ID_number`,`profession`,`phone_number`,`telegram_username`,`telegram_chat_id`,`tenant_address`,`tenant_home_country_address`,`tenant_country`,`start_date`,`end_date`,`tenant_status`,`dateAdmitted`) VALUES ('$houseid','$partitionId','$tname','$temail','$idnum','$prof','$phone','$telegramUsername','$telegramChatId','$tenantAddress','$tenantHomeCountryAddress','$tenantCountry','$startDate','$endDate','Active','$dateAdmitted')";

    if ($mysqli->query($sqTenants)) {
        $tenantId = (int) $mysqli->insert_id;
        if ($tenantId <= 0) {
            $tenantIdResult = mysqli_query($connection, "SELECT MAX(`tenantID`) AS latest_tenant_id FROM `tenants`");
            if ($tenantIdResult && ($tenantIdRow = mysqli_fetch_assoc($tenantIdResult))) {
                $tenantId = (int) $tenantIdRow['latest_tenant_id'];
            }
        }
        if ($tenantId <= 0) {
            $status = false;
            break;
        }
        if (!ensure_tenant_user_account($connection, $tenantId, $tname, $temail, $phone)) {
            $accountWarning = true;
        }
    } else {
        $status = false;
        break;
    }
}

$mysqli->query($sqHouses) ? null : $status = false;
$mysqli->query($sqPartitionUpdate) ? null : $status = false;
$mysqli->query($sqlTransactions) ? null : $status = false;

if (!$status) {
    $mysqli->rollback();
    api_json(['ok' => false, 'message' => $dateError ? 'Start date cannot be greater than end date.' : 'Tenant could not be saved.'], $dateError ? 422 : 500);
}

$mysqli->commit();
api_json(['ok' => true, 'tenant_account_warning' => $accountWarning]);
