<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/house_photo_helpers.php';

$auth = api_require_auth();
ensure_pic_type_column($connection);

$houseId = isset($_GET['house_id']) ? (int) $_GET['house_id'] : 0;
$houseWhere = $houseId > 0 ? " AND hp.`house_id` = '$houseId'" : '';

if ($auth['isAdmin']) {
    $sql = "
        SELECT hp.*, h.`house_name`, h.`location`
        FROM `house_partitions` hp
        LEFT JOIN `houses` h ON hp.`house_id` = h.`houseID`
        WHERE 1=1 $houseWhere
        ORDER BY
            CASE WHEN LOWER(hp.`partition_status`) = 'vacant' THEN 0 ELSE 1 END ASC,
            h.`house_name` ASC,
            hp.`partition_number` ASC,
            hp.`partition_id` ASC
    ";
} else {
    $tenant = get_logged_in_tenant_record();
    $tenantPartitionId = $tenant && isset($tenant['partition_id']) ? (int) $tenant['partition_id'] : 0;
    $tenantPartitionWhere = $tenantPartitionId > 0
        ? " OR hp.`partition_id` = '$tenantPartitionId'"
        : '';

    $sql = "
        SELECT hp.*, h.`house_name`, h.`location`
        FROM `house_partitions` hp
        LEFT JOIN `houses` h ON hp.`house_id` = h.`houseID`
        WHERE (hp.`partition_status` = 'Vacant' $tenantPartitionWhere) $houseWhere
        ORDER BY
            CASE WHEN LOWER(hp.`partition_status`) = 'vacant' THEN 0 ELSE 1 END ASC,
            h.`house_name` ASC,
            hp.`partition_number` ASC,
            hp.`partition_id` ASC
    ";
}

$result = mysqli_query($connection, $sql);
$items = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $partitionId = (int) $row['partition_id'];
        $photoCountResult = mysqli_query(
            $connection,
            "SELECT COUNT(*) AS total FROM `house_pics` WHERE `partition_id` = '$partitionId'"
        );
        $photoCount = $photoCountResult ? (int) mysqli_fetch_assoc($photoCountResult)['total'] : 0;

        $items[] = [
            'partition_id' => $partitionId,
            'house_id' => (int) $row['house_id'],
            'house_name' => isset($row['house_name']) ? (string) $row['house_name'] : '',
            'location' => isset($row['location']) ? (string) $row['location'] : '',
            'partition_number' => (string) $row['partition_number'],
            'rent_amount' => (float) $row['rent_amount'],
            'partition_status' => (string) $row['partition_status'],
            'description' => isset($row['description']) ? (string) $row['description'] : '',
            'facilities' => get_partition_facilities_array(isset($row['facilities']) ? $row['facilities'] : ''),
            'photo_count' => $photoCount,
        ];
    }
}

api_json([
    'ok' => true,
    'canManage' => $auth['isAdmin'],
    'selected_house_id' => $houseId,
    'items' => $items,
]);
