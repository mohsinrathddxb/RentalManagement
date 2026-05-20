<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/invoice_pdf_helpers.php';

$auth = api_require_auth();
ensure_invoice_pdf_columns($connection);

if ($auth['isAdmin']) {
    $sql = "
        SELECT
            i.`invoiceNumber`,
            t.`tenant_name`,
            t.`phone_number`,
            i.`tenantID`,
            i.`amountDue`,
            i.`rent_amount`,
            i.`booking_amount`,
            i.`deposit_amount`,
            i.`credit_applied`,
            i.`total_amount`,
            i.`dateOfInvoice`,
            i.`dateDue`,
            i.`status`,
            i.`comment`,
            (
                SELECT COALESCE(SUM(p.`amountPaid`), 0)
                FROM `payments` p
                WHERE p.`invoiceNumber` = i.`invoiceNumber`
            ) AS `total_paid`,
            (
                SELECT MAX(p.`paymentID`)
                FROM `payments` p
                WHERE p.`invoiceNumber` = i.`invoiceNumber`
            ) AS `latestPaymentID`
        FROM `invoices` i
        LEFT JOIN `tenants` t ON i.`tenantID` = t.`tenantID`
        ORDER BY i.`dateOfInvoice` DESC, i.`invoiceNumber` DESC
    ";
} else {
    $tenant = get_logged_in_tenant_record();
    $tenantId = $tenant ? (int) $tenant['tenantID'] : 0;
    $sql = "
        SELECT
            i.`invoiceNumber`,
            t.`tenant_name`,
            t.`phone_number`,
            i.`tenantID`,
            i.`amountDue`,
            i.`rent_amount`,
            i.`booking_amount`,
            i.`deposit_amount`,
            i.`credit_applied`,
            i.`total_amount`,
            i.`dateOfInvoice`,
            i.`dateDue`,
            i.`status`,
            i.`comment`,
            (
                SELECT COALESCE(SUM(p.`amountPaid`), 0)
                FROM `payments` p
                WHERE p.`invoiceNumber` = i.`invoiceNumber`
            ) AS `total_paid`,
            (
                SELECT MAX(p.`paymentID`)
                FROM `payments` p
                WHERE p.`invoiceNumber` = i.`invoiceNumber`
            ) AS `latestPaymentID`
        FROM `invoices` i
        LEFT JOIN `tenants` t ON i.`tenantID` = t.`tenantID`
        WHERE i.`tenantID` = '$tenantId'
        ORDER BY i.`dateOfInvoice` DESC, i.`invoiceNumber` DESC
    ";
}

$result = mysqli_query($connection, $sql);
$items = [];
$baseAppUrl = 'http://localhost/Rental-house-management-system/admin/';

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $invoiceNumber = (string) $row['invoiceNumber'];
        $latestPaymentId = isset($row['latestPaymentID']) ? (int) $row['latestPaymentID'] : 0;
        $financials = summarize_invoice_financials($row);

        $items[] = [
            'invoiceNumber' => $invoiceNumber,
            'tenant_name' => isset($row['tenant_name']) ? (string) $row['tenant_name'] : '',
            'phone_number' => isset($row['phone_number']) ? (string) $row['phone_number'] : '',
            'tenantID' => (int) $row['tenantID'],
            'amountDue' => (float) $row['amountDue'],
            'rent_amount' => isset($row['rent_amount']) ? (float) $row['rent_amount'] : 0.0,
            'deposit_amount' => isset($row['deposit_amount']) ? (float) $row['deposit_amount'] : 0.0,
            'credit_applied' => isset($row['credit_applied']) ? (float) $row['credit_applied'] : 0.0,
            'total_paid' => isset($row['total_paid']) ? (float) $row['total_paid'] : 0.0,
            'rent_due_amount' => (float) $financials['rent_due'],
            'deposit_due_amount' => (float) $financials['deposit_due'],
            'total_amount' => (float) $row['total_amount'],
            'dateOfInvoice' => isset($row['dateOfInvoice']) ? (string) $row['dateOfInvoice'] : '',
            'dateDue' => isset($row['dateDue']) ? (string) $row['dateDue'] : '',
            'status' => (string) $financials['status'],
            'comment' => isset($row['comment']) ? (string) $row['comment'] : '',
            'latestPaymentID' => $latestPaymentId,
            'invoice_pdf_url' => $baseAppUrl . 'invoice-pdf.php?invoice=' . rawurlencode($invoiceNumber),
            'receipt_pdf_url' => $latestPaymentId > 0
                ? $baseAppUrl . 'payment-receipt-pdf.php?payment=' . $latestPaymentId
                : '',
        ];
    }
}

api_json([
    'ok' => true,
    'canManage' => $auth['isAdmin'],
    'items' => $items,
]);
