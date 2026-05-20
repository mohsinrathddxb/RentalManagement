<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/partition_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

api_require_admin();
ensure_house_auto_increment_schema($connection);
ensure_partition_tables($connection);

$input = api_get_json_input();
$action = isset($input['action']) ? trim((string) $input['action']) : '';
$houseId = isset($input['houseID']) ? (int) $input['houseID'] : 0;

if ($houseId <= 0 || ($action !== 'update' && $action !== 'delete')) {
    api_json(['ok' => false, 'message' => 'A valid house action is required.'], 422);
}

if ($action === 'update') {
    $houseName = is_username(isset($input['hname']) ? (string) $input['hname'] : '');
    $numberOfRooms = isset($input['numOfRooms']) ? (int) $input['numOfRooms'] : -1;
    $numOfBedrooms = isset($input['numOfbRooms']) ? (int) $input['numOfbRooms'] : -1;
    $rentAmount = isset($input['rent']) ? (float) $input['rent'] : -1;
    $location = uncrack(isset($input['location']) ? (string) $input['location'] : '');
    $status = uncrack(isset($input['status']) ? (string) $input['status'] : '');

    if ($houseName === '' || $numberOfRooms < 0 || $numOfBedrooms < 0 || $rentAmount < 0 || $location === '' || $status === '') {
        api_json(['ok' => false, 'message' => 'All house fields are required.'], 422);
    }

    $allowedStatuses = ['Vacant', 'Occupied'];
    $status = in_array($status, $allowedStatuses, true) ? $status : 'Vacant';
    $partitionCountResult = mysqli_query($connection, "SELECT COUNT(*) AS total FROM `house_partitions` WHERE `house_id` = '$houseId'");
    $partitionCountRow = $partitionCountResult ? mysqli_fetch_assoc($partitionCountResult) : ['total' => 0];
    if ((int) $partitionCountRow['total'] > 0) {
        $status = get_house_occupancy_status($connection, $houseId, $status);
    }

    $safeHouseName = mysqli_real_escape_string($connection, $houseName);
    $safeLocation = mysqli_real_escape_string($connection, $location);
    $safeStatus = mysqli_real_escape_string($connection, $status);
    $timesnap = date('Y-m-d : H:i:s');
    $sql = "
        UPDATE `houses`
        SET
            `house_name` = '$safeHouseName',
            `number_of_rooms` = '$numberOfRooms',
            `rent_amount` = '$rentAmount',
            `location` = '$safeLocation',
            `num_of_bedrooms` = '$numOfBedrooms',
            `house_status` = '$safeStatus'
        WHERE `houseID` = '$houseId'
        LIMIT 1
    ";
    $logSql = "INSERT INTO `transactions` (`actor`,`time`,`description`) VALUES ('Admin ($username)', '$timesnap', '$username updated house ($safeHouseName) at $timesnap')";

    $mysqli->autocommit(false);
    $state = true;
    $mysqli->query($sql) ? null : $state = false;
    $mysqli->query($logSql) ? null : $state = false;

    if (!$state) {
        $mysqli->rollback();
        api_json(['ok' => false, 'message' => 'House could not be updated.'], 500);
    }

    $mysqli->commit();
    api_json(['ok' => true, 'message' => 'House updated successfully.']);
}

$activeTenantResult = mysqli_query($connection, "SELECT COUNT(*) AS total FROM `tenants` WHERE `houseNumber` = '$houseId' AND `tenant_status` = 'Active'");
$activeTenantRow = $activeTenantResult ? mysqli_fetch_assoc($activeTenantResult) : ['total' => 0];
$partitionResult = mysqli_query($connection, "SELECT COUNT(*) AS total FROM `house_partitions` WHERE `house_id` = '$houseId'");
$partitionRow = $partitionResult ? mysqli_fetch_assoc($partitionResult) : ['total' => 0];

if ((int) $activeTenantRow['total'] > 0 || (int) $partitionRow['total'] > 0) {
    api_json([
        'ok' => false,
        'message' => 'This house still has active tenants or partitions. Remove them first before deleting the house.',
    ], 409);
}

$safeHouseId = mysqli_real_escape_string($connection, (string) $houseId);
$timesnap = date('Y-m-d : H:i:s');
$houseLookup = mysqli_query($connection, "SELECT `house_name` FROM `houses` WHERE `houseID` = '$safeHouseId' LIMIT 1");
$houseRow = $houseLookup ? mysqli_fetch_assoc($houseLookup) : ['house_name' => 'House'];
$houseName = isset($houseRow['house_name']) ? mysqli_real_escape_string($connection, (string) $houseRow['house_name']) : 'House';

$photosResult = mysqli_query($connection, "SELECT `pic_name` FROM `house_pics` WHERE `house_id` = '$safeHouseId'");
if ($photosResult) {
    while ($photo = mysqli_fetch_assoc($photosResult)) {
        $picName = isset($photo['pic_name']) ? (string) $photo['pic_name'] : '';
        $filePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $picName);
        if ($picName !== '' && is_file($filePath)) {
            @unlink($filePath);
        }
    }
}

$mysqli->autocommit(false);
$state = true;
$mysqli->query("DELETE FROM `house_pics` WHERE `house_id` = '$safeHouseId'") ? null : $state = false;
$mysqli->query("DELETE FROM `houses` WHERE `houseID` = '$safeHouseId' LIMIT 1") ? null : $state = false;
$mysqli->query("INSERT INTO `transactions` (`actor`,`time`,`description`) VALUES ('Admin ($username)', '$timesnap', '$username deleted house ($houseName) at $timesnap')") ? null : $state = false;

if (!$state) {
    $mysqli->rollback();
    api_json(['ok' => false, 'message' => 'House could not be deleted.'], 500);
}

$mysqli->commit();
api_json(['ok' => true, 'message' => 'House deleted successfully.']);
