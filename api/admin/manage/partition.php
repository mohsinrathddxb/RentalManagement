<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/partition_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

api_require_admin();
ensure_partition_tables($connection);

$input = api_get_json_input();
$action = isset($input['action']) ? trim((string) $input['action']) : '';
$partitionId = isset($input['partition_id']) ? (int) $input['partition_id'] : 0;

if ($partitionId <= 0 || ($action !== 'update' && $action !== 'delete')) {
    api_json(['ok' => false, 'message' => 'A valid partition action is required.'], 422);
}

$lookup = mysqli_query($connection, "
    SELECT hp.`house_id`, hp.`partition_number`, hp.`partition_status`
    FROM `house_partitions` hp
    WHERE hp.`partition_id` = '$partitionId'
    LIMIT 1
");
$existing = $lookup ? mysqli_fetch_assoc($lookup) : null;
if (!$existing) {
    api_json(['ok' => false, 'message' => 'Partition could not be found.'], 404);
}

$houseId = (int) $existing['house_id'];
$activeTenantResult = mysqli_query($connection, "SELECT COUNT(*) AS total FROM `tenants` WHERE `partition_id` = '$partitionId' AND `tenant_status` = 'Active'");
$activeTenantRow = $activeTenantResult ? mysqli_fetch_assoc($activeTenantResult) : ['total' => 0];
$hasActiveTenant = (int) $activeTenantRow['total'] > 0;

if ($action === 'update') {
    $partitionNumber = isset($input['partition_number']) ? trim((string) $input['partition_number']) : '';
    $rentAmount = isset($input['rent_amount']) ? (float) $input['rent_amount'] : -1;
    $status = isset($input['partition_status']) ? trim((string) $input['partition_status']) : 'Vacant';
    $description = isset($input['description']) ? trim((string) $input['description']) : '';
    $facilities = normalize_partition_facilities(isset($input['facilities']) ? (array) $input['facilities'] : []);

    if ($partitionNumber === '' || $rentAmount < 0) {
        api_json(['ok' => false, 'message' => 'Partition name and rent are required.'], 422);
    }

    $allowedStatuses = ['Vacant', 'Occupied'];
    $status = in_array($status, $allowedStatuses, true) ? $status : 'Vacant';
    if ($hasActiveTenant) {
        $status = 'Occupied';
    }

    $statement = mysqli_prepare($connection, "
        UPDATE `house_partitions`
        SET `partition_number` = ?, `rent_amount` = ?, `partition_status` = ?, `description` = ?, `facilities` = ?
        WHERE `partition_id` = ?
    ");

    if (!$statement) {
        api_json(['ok' => false, 'message' => 'Partition update could not be prepared.'], 500);
    }

    mysqli_stmt_bind_param($statement, 'sdsssi', $partitionNumber, $rentAmount, $status, $description, $facilities, $partitionId);
    if (!mysqli_stmt_execute($statement)) {
        api_json(['ok' => false, 'message' => 'Partition could not be updated.'], 500);
    }

    sync_house_status_from_partitions($connection, $houseId, 'Vacant');
    $safePartitionNumber = mysqli_real_escape_string($connection, $partitionNumber);
    $timesnap = date('Y-m-d : H:i:s');
    mysqli_query($connection, "INSERT INTO `transactions` (`actor`,`time`,`description`) VALUES ('Admin ($username)', '$timesnap', '$username updated partition ($safePartitionNumber) at $timesnap')");
    api_json(['ok' => true, 'message' => 'Partition updated successfully.']);
}

if ($hasActiveTenant) {
    api_json([
        'ok' => false,
        'message' => 'This partition still has an active tenant. Move the tenant out first before deleting the partition.',
    ], 409);
}

$photoResult = mysqli_query($connection, "SELECT `pic_name` FROM `house_pics` WHERE `partition_id` = '$partitionId'");
if ($photoResult) {
    while ($photo = mysqli_fetch_assoc($photoResult)) {
        $picName = isset($photo['pic_name']) ? (string) $photo['pic_name'] : '';
        $filePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $picName);
        if ($picName !== '' && is_file($filePath)) {
            @unlink($filePath);
        }
    }
}

$timesnap = date('Y-m-d : H:i:s');
$safePartitionNumber = mysqli_real_escape_string($connection, (string) $existing['partition_number']);
$mysqli->autocommit(false);
$state = true;
$mysqli->query("DELETE FROM `house_pics` WHERE `partition_id` = '$partitionId'") ? null : $state = false;
$mysqli->query("DELETE FROM `house_partitions` WHERE `partition_id` = '$partitionId' LIMIT 1") ? null : $state = false;
$mysqli->query("INSERT INTO `transactions` (`actor`,`time`,`description`) VALUES ('Admin ($username)', '$timesnap', '$username deleted partition ($safePartitionNumber) at $timesnap')") ? null : $state = false;

if (!$state) {
    $mysqli->rollback();
    api_json(['ok' => false, 'message' => 'Partition could not be deleted.'], 500);
}

$mysqli->commit();
sync_house_status_from_partitions($connection, $houseId, 'Vacant');
api_json(['ok' => true, 'message' => 'Partition deleted successfully.']);
