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
        t.`profession`,
        t.`phone_number`,
        t.`telegram_username`,
        t.`telegram_chat_id`,
        t.`tenant_address`,
        t.`tenant_home_country_address`,
        t.`tenant_country`,
        t.`start_date`,
        t.`end_date`,
        t.`dateAdmitted`,
        t.`agreement_file`,
        h.`house_name`,
        h.`houseID`,
        COALESCE(hp.`rent_amount`, h.`rent_amount`) AS `rent_amount`,
        hp.`partition_number`
    FROM `tenants` t
    LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
    LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
    WHERE t.`tenant_status` = 'Active'
    ORDER BY t.`tenantID` DESC
";

$result = mysqli_query($connection, $sql);
$rows = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = [
            'tenantID' => (int) $row['tenantID'],
            'tenant_name' => (string) $row['tenant_name'],
            'email' => (string) $row['email'],
            'ID_number' => (string) $row['ID_number'],
            'profession' => (string) $row['profession'],
            'phone_number' => (string) $row['phone_number'],
            'telegram_username' => (string) $row['telegram_username'],
            'telegram_chat_id' => (string) $row['telegram_chat_id'],
            'tenant_address' => (string) $row['tenant_address'],
            'tenant_home_country_address' => (string) $row['tenant_home_country_address'],
            'tenant_country' => (string) $row['tenant_country'],
            'start_date' => (string) $row['start_date'],
            'end_date' => (string) $row['end_date'],
            'dateAdmitted' => (string) $row['dateAdmitted'],
            'agreement_file' => (string) $row['agreement_file'],
            'house_name' => (string) $row['house_name'],
            'houseID' => (int) $row['houseID'],
            'partition_number' => (string) $row['partition_number'],
            'rent_amount' => (float) $row['rent_amount'],
        ];
    }
}

api_json([
    'ok' => true,
    'items' => $rows,
]);

