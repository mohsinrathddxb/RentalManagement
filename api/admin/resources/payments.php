<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/invoice_pdf_helpers.php';

api_require_admin();
ensure_invoice_pdf_columns($connection);

$sql = "
    SELECT
        p.`paymentID`,
        p.`tenantID`,
        t.`tenant_name`,
        h.`house_name`,
        p.`invoiceNumber`,
        p.`expectedAmount`,
        p.`amountPaid`,
        p.`balance`,
        p.`mpesaCode`,
        p.`dateofPayment`,
        p.`comment`
    FROM `payments` p
    LEFT JOIN `tenants` t ON p.`tenantID` = t.`tenantID`
    LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
    ORDER BY p.`paymentID` DESC
";

$result = mysqli_query($connection, $sql);
$items = [];
$baseAppUrl = 'http://localhost/Rental-house-management-system/admin/';

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $paymentId = (int) $row['paymentID'];
        $invoiceNumber = (string) $row['invoiceNumber'];

        $items[] = [
            'paymentID' => $paymentId,
            'tenantID' => (int) $row['tenantID'],
            'tenant_name' => isset($row['tenant_name']) ? (string) $row['tenant_name'] : '',
            'house_name' => isset($row['house_name']) ? (string) $row['house_name'] : '',
            'invoiceNumber' => $invoiceNumber,
            'expectedAmount' => (float) $row['expectedAmount'],
            'amountPaid' => (float) $row['amountPaid'],
            'balance' => (float) $row['balance'],
            'mpesaCode' => isset($row['mpesaCode']) ? (string) $row['mpesaCode'] : '',
            'dateofPayment' => isset($row['dateofPayment']) ? (string) $row['dateofPayment'] : '',
            'comment' => isset($row['comment']) ? (string) $row['comment'] : '',
            'invoice_pdf_url' => $baseAppUrl . 'invoice-pdf.php?invoice=' . rawurlencode($invoiceNumber),
            'receipt_pdf_url' => $baseAppUrl . 'payment-receipt-pdf.php?payment=' . $paymentId,
        ];
    }
}

api_json([
    'ok' => true,
    'items' => $items,
]);
