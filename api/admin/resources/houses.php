<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/house_photo_helpers.php';

$auth = api_require_auth();

$sql = "
    SELECT
        h.*,
        COALESCE(SUM(CASE WHEN LOWER(hp.`partition_status`) = 'vacant' THEN 1 ELSE 0 END), 0) AS `available_partition_count`,
        COALESCE(COUNT(hp.`partition_id`), 0) AS `partition_count`
    FROM `houses` h
    LEFT JOIN `house_partitions` hp ON hp.`house_id` = h.`houseID`
    GROUP BY h.`houseID`
    ORDER BY
        CASE
            WHEN COALESCE(COUNT(hp.`partition_id`), 0) > 0
                AND COALESCE(SUM(CASE WHEN LOWER(hp.`partition_status`) = 'vacant' THEN 1 ELSE 0 END), 0) > 0 THEN 0
            WHEN LOWER(h.`house_status`) = 'vacant' THEN 1
            ELSE 2
        END ASC,
        `available_partition_count` DESC,
        h.`house_name` ASC
";

$result = mysqli_query($connection, $sql);
$rows = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $houseId = (int) $row['houseID'];
        $photoUrls = [];
        $photoResult = mysqli_query(
            $connection,
            "SELECT `pic_name` FROM `house_pics` WHERE `house_id`='$houseId' AND `partition_id` IS NULL ORDER BY `pic_id` DESC"
        );

        if ($photoResult) {
            while ($photo = mysqli_fetch_assoc($photoResult)) {
                $photoUrl = house_photo_public_path(isset($photo['pic_name']) ? $photo['pic_name'] : '');
                if ($photoUrl !== '') {
                    $photoUrls[] = $photoUrl;
                }
            }
        }

        $availablePartitionCount = (int) $row['available_partition_count'];
        $partitionCount = (int) $row['partition_count'];
        $computedStatus = $partitionCount > 0
            ? ($availablePartitionCount > 0 ? 'Vacant' : 'Occupied')
            : (string) $row['house_status'];

        $rows[] = [
            'houseID' => $houseId,
            'house_name' => (string) $row['house_name'],
            'number_of_rooms' => (int) $row['number_of_rooms'],
            'rent_amount' => (float) $row['rent_amount'],
            'location' => (string) $row['location'],
            'num_of_bedrooms' => (int) $row['num_of_bedrooms'],
            'house_status' => $computedStatus,
            'partition_count' => $partitionCount,
            'available_partition_count' => $availablePartitionCount,
            'photo_count' => count($photoUrls),
            'photo_urls' => $photoUrls,
            'partitions_url' => 'add-partition.php?house_id=' . $houseId,
        ];
    }
}

api_json([
    'ok' => true,
    'canManage' => $auth['isAdmin'],
    'items' => $rows,
]);
