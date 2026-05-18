<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_admin();

$sql = "
    SELECT
        t.`tenantID`,
        t.`tenant_name`,
        t.`email`,
        t.`ID_number`,
        t.`phone_number`,
        t.`tenant_country`,
        t.`start_date`,
        t.`end_date`,
        t.`exit_date`,
        t.`tenant_status`,
        h.`house_name`,
        COALESCE(hp.`rent_amount`, h.`rent_amount`) AS `rent_amount`,
        hp.`partition_number`
    FROM `tenants` t
    LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
    LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
    WHERE t.`tenant_status` = 'Deleted&Moved_Out'
    ORDER BY t.`tenantID` DESC
";

$result = mysqli_query($connection, $sql);
$items = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = [
            'tenantID' => (int) $row['tenantID'],
            'tenant_name' => (string) $row['tenant_name'],
            'house_name' => isset($row['house_name']) ? (string) $row['house_name'] : '',
            'partition_number' => isset($row['partition_number']) ? (string) $row['partition_number'] : '',
            'email' => (string) $row['email'],
            'ID_number' => (string) $row['ID_number'],
            'phone_number' => (string) $row['phone_number'],
            'tenant_country' => (string) $row['tenant_country'],
            'rent_amount' => (float) $row['rent_amount'],
            'start_date' => (string) $row['start_date'],
            'end_date' => (string) $row['end_date'],
            'exit_date' => (string) $row['exit_date'],
            'tenant_status' => (string) $row['tenant_status'],
        ];
    }
}

api_json(['ok' => true, 'items' => $items]);
