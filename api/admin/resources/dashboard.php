<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$auth = api_require_auth();

if ($auth['isAdmin']) {
    $housesCount = (int) mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) AS total FROM `houses`"))['total'];
    $tenantsCount = (int) mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) AS total FROM `tenants` WHERE `tenant_status`='Active'"))['total'];
    $invoicesCount = (int) mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) AS total FROM `invoices`"))['total'];
    $paymentsCount = (int) mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) AS total FROM `payments`"))['total'];

    $month = date('Y-m');
    $monthCollectionsResult = mysqli_query($connection, "SELECT COALESCE(SUM(`amountPaid`), 0) AS total FROM `payments` WHERE `dateofPayment` LIKE '%$month%'");
    $monthCollections = $monthCollectionsResult ? (float) mysqli_fetch_assoc($monthCollectionsResult)['total'] : 0.0;

    $pendingInvoicesResult = mysqli_query($connection, "SELECT COALESCE(SUM(`amountDue`), 0) AS total FROM `invoices` WHERE LOWER(`status`) <> 'paid'");
    $pendingInvoices = $pendingInvoicesResult ? (float) mysqli_fetch_assoc($pendingInvoicesResult)['total'] : 0.0;

    api_json([
        'ok' => true,
        'mode' => 'admin',
        'stats' => [
            'houses' => $housesCount,
            'tenants' => $tenantsCount,
            'invoices' => $invoicesCount,
            'payments' => $paymentsCount,
        ],
        'finance' => [
            'month' => $month,
            'monthCollections' => $monthCollections,
            'pendingInvoices' => $pendingInvoices,
        ],
    ]);
}

$tenant = get_logged_in_tenant_record();
if (!$tenant) {
    api_json(['ok' => false, 'message' => 'Tenant record not found.'], 404);
}

$tenantId = (int) $tenant['tenantID'];
$myInvoicesResult = mysqli_query($connection, "SELECT COUNT(*) AS total FROM `invoices` WHERE `tenantID`='$tenantId'");
$myComplaintsResult = mysqli_query($connection, "SELECT COUNT(*) AS total FROM `tenant_complaints` WHERE `tenant_id`='$tenantId'");

api_json([
    'ok' => true,
    'mode' => 'tenant',
    'stay' => [
        'house' => isset($tenant['house_name']) ? (string) $tenant['house_name'] : '',
        'partition' => isset($tenant['partition_number']) ? (string) $tenant['partition_number'] : '',
        'rent' => isset($tenant['rent_amount']) ? (string) $tenant['rent_amount'] : '',
    ],
    'stats' => [
        'invoices' => $myInvoicesResult ? (int) mysqli_fetch_assoc($myInvoicesResult)['total'] : 0,
        'complaints' => $myComplaintsResult ? (int) mysqli_fetch_assoc($myComplaintsResult)['total'] : 0,
    ],
]);

