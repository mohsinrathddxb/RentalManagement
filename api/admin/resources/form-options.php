<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/country_options.php';
require_once __DIR__ . '/../../../admin/functions/expense_helpers.php';
require_once __DIR__ . '/../../../admin/functions/invoice_pdf_helpers.php';
require_once __DIR__ . '/../../../admin/functions/partition_helpers.php';

$auth = api_require_auth();
ensure_invoice_pdf_columns($connection);

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

$expensePartitions = [];
$expensePartitionResult = get_expense_partition_options($connection);
if ($expensePartitionResult) {
    while ($row = mysqli_fetch_assoc($expensePartitionResult)) {
        $expensePartitions[] = [
            'partition_id' => (int) $row['partition_id'],
            'house_id' => (int) $row['house_id'],
            'partition_number' => (string) $row['partition_number'],
            'rent_amount' => 0,
            'partition_status' => '',
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
        h.`house_name`,
        hp.`partition_number`,
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
            'house_name' => isset($row['house_name']) ? (string) $row['house_name'] : '',
            'partition_number' => isset($row['partition_number']) ? (string) $row['partition_number'] : '',
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
        i.`rent_amount`,
        i.`booking_amount`,
        i.`deposit_amount`,
        i.`credit_applied`,
        i.`total_amount`,
        i.`dateDue`,
        i.`status`,
        t.`tenant_name`,
        (
            SELECT COALESCE(SUM(p.`amountPaid`), 0)
            FROM `payments` p
            WHERE p.`invoiceNumber` = i.`invoiceNumber`
        ) AS `total_paid`
    FROM `invoices` i
    LEFT JOIN `tenants` t ON i.`tenantID` = t.`tenantID`
    WHERE i.`status` IN ('unpaid', 'partial paid')
    ORDER BY i.`tenantID` DESC, i.`invoiceNumber` DESC
");
if ($invoiceResult) {
    while ($row = mysqli_fetch_assoc($invoiceResult)) {
        $financials = summarize_invoice_financials($row);
        $openInvoices[] = [
            'invoiceNumber' => (string) $row['invoiceNumber'],
            'tenantID' => (int) $row['tenantID'],
            'tenant_name' => isset($row['tenant_name']) ? (string) $row['tenant_name'] : '',
            'amountDue' => (float) $row['amountDue'],
            'rent_amount' => isset($row['rent_amount']) ? (float) $row['rent_amount'] : 0.0,
            'deposit_amount' => isset($row['deposit_amount']) ? (float) $row['deposit_amount'] : 0.0,
            'credit_applied' => isset($row['credit_applied']) ? (float) $row['credit_applied'] : 0.0,
            'total_paid' => isset($row['total_paid']) ? (float) $row['total_paid'] : 0.0,
            'rent_due_amount' => (float) $financials['rent_due'],
            'deposit_due_amount' => (float) $financials['deposit_due'],
            'dateDue' => isset($row['dateDue']) ? (string) $row['dateDue'] : '',
            'status' => (string) $financials['status'],
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
    'expense_partitions' => $expensePartitions,
    'activeTenants' => $activeTenants,
    'openInvoices' => $openInvoices,
    'locations' => $locations,
    'roles' => ['level-0', 'level-1', 'level-2', 'level-3', 'user'],
    'partition_facilities' => get_partition_facility_catalog(),
    'defaultCountry' => 'United Arab Emirates',
    'defaultPhoneCode' => '+971',
    'canManage' => $auth['isAdmin'],
]);
