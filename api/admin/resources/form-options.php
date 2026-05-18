<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/country_options.php';
require_once __DIR__ . '/../../../admin/functions/expense_helpers.php';
require_once __DIR__ . '/../../../admin/functions/partition_helpers.php';

$auth = api_require_auth();

$houses = [];
$houseResult = mysqli_query($connection, "SELECT `houseID`, `house_name`, `number_of_rooms`, `location`, `rent_amount`, `house_status` FROM `houses` ORDER BY `house_name` ASC, `houseID` DESC");
if ($houseResult) {
    while ($row = mysqli_fetch_assoc($houseResult)) {
        $houses[] = [
            'houseID' => (int) $row['houseID'],
            'house_name' => (string) $row['house_name'],
            'number_of_rooms' => (int) $row['number_of_rooms'],
            'location' => (string) $row['location'],
            'rent_amount' => (float) $row['rent_amount'],
            'house_status' => (string) $row['house_status'],
        ];
    }
}

$partitions = [];
$partitionResult = mysqli_query($connection, "SELECT hp.`partition_id`, hp.`house_id`, hp.`partition_number`, hp.`rent_amount`, hp.`partition_status`, h.`house_name` FROM `house_partitions` hp LEFT JOIN `houses` h ON hp.`house_id`=h.`houseID` ORDER BY h.`house_name`, hp.`partition_number`");
if ($partitionResult) {
    while ($row = mysqli_fetch_assoc($partitionResult)) {
        $partitions[] = [
            'partition_id' => (int) $row['partition_id'],
            'house_id' => (int) $row['house_id'],
            'partition_number' => (string) $row['partition_number'],
            'rent_amount' => (float) $row['rent_amount'],
            'partition_status' => (string) $row['partition_status'],
            'house_name' => isset($row['house_name']) ? (string) $row['house_name'] : '',
        ];
    }
}

$activeTenants = [];
$tenantResult = mysqli_query($connection, "
    SELECT
        t.`tenantID`,
        t.`tenant_name`,
        t.`email`,
        COALESCE(hp.`rent_amount`, h.`rent_amount`) AS `rent_amount`
    FROM `tenants` t
    LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
    LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
    WHERE t.`tenant_status`='Active'
    ORDER BY t.`tenant_name` ASC, t.`tenantID` DESC
");
if ($tenantResult) {
    while ($row = mysqli_fetch_assoc($tenantResult)) {
        $activeTenants[] = [
            'tenantID' => (int) $row['tenantID'],
            'tenant_name' => (string) $row['tenant_name'],
            'email' => (string) $row['email'],
            'rent_amount' => (float) $row['rent_amount'],
        ];
    }
}

$openInvoices = [];
$invoiceResult = mysqli_query($connection, "
    SELECT
        i.`invoiceNumber`,
        i.`tenantID`,
        i.`amountDue`,
        i.`dateDue`,
        i.`status`,
        t.`tenant_name`
    FROM `invoices` i
    LEFT JOIN `tenants` t ON i.`tenantID` = t.`tenantID`
    WHERE i.`status` IN ('unpaid', 'partial paid')
    ORDER BY i.`tenantID` DESC, i.`invoiceNumber` DESC
");
if ($invoiceResult) {
    while ($row = mysqli_fetch_assoc($invoiceResult)) {
        $openInvoices[] = [
            'invoiceNumber' => (string) $row['invoiceNumber'],
            'tenantID' => (int) $row['tenantID'],
            'tenant_name' => isset($row['tenant_name']) ? (string) $row['tenant_name'] : '',
            'amountDue' => (float) $row['amountDue'],
            'dateDue' => isset($row['dateDue']) ? (string) $row['dateDue'] : '',
            'status' => isset($row['status']) ? (string) $row['status'] : '',
        ];
    }
}

$locations = [];
$locationsResult = @mysqli_query($connection, "SELECT `id`, `location_name`, `geo_id` FROM `locations` ORDER BY `location_name` ASC, `id` DESC");
if ($locationsResult) {
    while ($row = mysqli_fetch_assoc($locationsResult)) {
        $locations[] = [
            'id' => (int) $row['id'],
            'location_name' => (string) $row['location_name'],
            'geo_id' => isset($row['geo_id']) ? (string) $row['geo_id'] : '',
        ];
    }
}

api_json([
    'ok' => true,
    'countries' => get_country_records(),
    'expense_categories' => get_expense_categories(),
    'houses' => $houses,
    'partitions' => $partitions,
    'activeTenants' => $activeTenants,
    'openInvoices' => $openInvoices,
    'locations' => $locations,
    'roles' => ['level-0', 'level-1', 'level-2', 'level-3', 'user'],
    'partition_facilities' => get_partition_facility_catalog(),
    'defaultCountry' => 'United Arab Emirates',
    'defaultPhoneCode' => '+971',
    'canManage' => $auth['isAdmin'],
]);
