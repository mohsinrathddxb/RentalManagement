<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

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
            WHEN LOWER(h.`house_status`) = 'vacant' THEN 0
            WHEN COALESCE(SUM(CASE WHEN LOWER(hp.`partition_status`) = 'vacant' THEN 1 ELSE 0 END), 0) > 0 THEN 1
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
        $photoCountResult = mysqli_query($connection, "SELECT COUNT(*) AS total FROM `house_pics` WHERE `house_id`='$houseId' AND `partition_id` IS NULL");
        $photoCount = $photoCountResult ? (int) mysqli_fetch_assoc($photoCountResult)['total'] : 0;

        $rows[] = [
            'houseID' => $houseId,
            'house_name' => (string) $row['house_name'],
            'number_of_rooms' => (int) $row['number_of_rooms'],
            'rent_amount' => (float) $row['rent_amount'],
            'location' => (string) $row['location'],
            'num_of_bedrooms' => (int) $row['num_of_bedrooms'],
            'house_status' => (string) $row['house_status'],
            'partition_count' => (int) $row['partition_count'],
            'available_partition_count' => (int) $row['available_partition_count'],
            'photo_count' => $photoCount,
            'partitions_url' => 'add-partition.php?house_id=' . $houseId,
        ];
    }
}

api_json([
    'ok' => true,
    'canManage' => $auth['isAdmin'],
    'items' => $rows,
]);

