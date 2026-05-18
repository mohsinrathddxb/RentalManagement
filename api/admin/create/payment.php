<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/invoice_pdf_helpers.php';
require_once __DIR__ . '/../../../admin/functions/telegram_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

api_require_admin();
ensure_tenant_schema($connection);
ensure_invoice_pdf_columns($connection);

$input = api_get_json_input();
$tenantId = isset($input['tenID']) ? (int) $input['tenID'] : 0;
$invoiceNumber = isset($input['invoiceNumber']) ? uncrack((string) $input['invoiceNumber']) : '';
$amountExpectedCents = isset($input['amountDue']) ? money_to_cents((string) $input['amountDue']) : 0;
$amountPaidCents = isset($input['paidAmount']) ? money_to_cents((string) $input['paidAmount']) : 0;
$mpesaCode = isset($input['mpesa']) ? uncrack((string) $input['mpesa']) : '';
$comment = isset($input['comment']) ? uncrack((string) $input['comment']) : '';

if ($tenantId <= 0 || $invoiceNumber === '' || $amountExpectedCents < 0 || $amountPaidCents < 0) {
    api_json(['ok' => false, 'message' => 'Please select an invoice first so tenant and amount details can load.'], 422);
}

$paymentDate = date('Y-m-d');
$timesnap = date('Y-m-d : H:i:s');
$rawBalanceCents = $amountExpectedCents - $amountPaidCents;
$balanceCents = max(0, $rawBalanceCents);

$tenquer = mysqli_query($conn, "SELECT `account`, `tenant_name`, `phone_number` FROM `tenants` WHERE `tenantID`='$tenantId' LIMIT 1");
$rec = $tenquer ? mysqli_fetch_array($tenquer, MYSQLI_BOTH) : null;
if (!$rec) {
    api_json(['ok' => false, 'message' => 'Tenant could not be found.'], 404);
}

$accountCents = isset($rec['account']) ? money_to_cents($rec['account']) : 0;
$tenantName = $rec['tenant_name'];
$firstName = strpos($tenantName, " ") !== false ? substr($tenantName, 0, strpos($tenantName, " ")) : $tenantName;
$phone = $rec['phone_number'];

if ($balanceCents === 0) {
    $status = 'paid';
    if ($rawBalanceCents < 0) {
        $accountCents += abs($rawBalanceCents);
    }
} else {
    $status = 'partial paid';
    $accountCents += $amountPaidCents;
}

$amountExpected = cents_to_money($amountExpectedCents);
$amountPaid = cents_to_money($amountPaidCents);
$balance = cents_to_money($balanceCents);
$accountBalance = cents_to_money($accountCents);
$safeInvoiceNumber = mysqli_real_escape_string($connection, $invoiceNumber);
$safeMpesaCode = mysqli_real_escape_string($connection, $mpesaCode);
$safeComment = mysqli_real_escape_string($connection, $comment);
$safeTenantName = mysqli_real_escape_string($connection, $tenantName);

$sqlInv = "UPDATE `invoices` SET `amountDue`='$balance', `status`='$status' WHERE `invoiceNumber`='$safeInvoiceNumber'";
$sqlTen = "UPDATE `tenants` SET `account`='$accountBalance' WHERE `tenantID`='$tenantId'";
$sqlPayment = "INSERT INTO `payments` (`tenantID`, `invoiceNumber`, `expectedAmount`, `amountPaid`, `balance`, `mpesaCode`, `dateofPayment`, `comment`) VALUES ('$tenantId', '$safeInvoiceNumber', '$amountExpected', '$amountPaid', '$balance', '$safeMpesaCode', '$paymentDate', '$safeComment')";
$sqlTransactions = "INSERT INTO `transactions` (`actor`, `time`, `description`) VALUES ('Admin ($username)', '$timesnap', '$username added payment of ".format_money_amount($amountPaid)." for $safeTenantName, under invoice ID: $safeInvoiceNumber')";

$noticeMessage = 'A payment of AED ' . format_money_amount($amountPaid) . ' was received for invoice ' . $invoiceNumber . '.';
if ($balanceCents > 0) {
    $noticeMessage .= ' Remaining amount to pay is AED ' . format_money_amount($balance) . '.';
} else {
    $noticeMessage .= ' This invoice is now fully paid.';
}

$mysqli->autocommit(false);
$state = true;
$mysqli->query($sqlInv) ? null : $state = false;
$mysqli->query($sqlTen) ? null : $state = false;
$mysqli->query($sqlPayment) ? null : $state = false;
$paymentId = $mysqli->insert_id;
$mysqli->query($sqlTransactions) ? null : $state = false;

if ($state && $noticeMessage !== '' && $paymentId > 0) {
    $safeNoticeMessage = mysqli_real_escape_string($connection, $noticeMessage);
    $safeCreatedBy = mysqli_real_escape_string($connection, isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin');
    $invoicePdfUrl = 'invoice-pdf.php?invoice=' . rawurlencode($invoiceNumber);
    $receiptPdfUrl = 'payment-receipt-pdf.php?payment=' . (int) $paymentId;
    $safeInvoicePdfUrl = mysqli_real_escape_string($connection, $invoicePdfUrl);
    $safeReceiptPdfUrl = mysqli_real_escape_string($connection, $receiptPdfUrl);
    $noticeSubject = $balanceCents > 0 ? 'Payment Update' : 'Payment Receipt';
    $safeNoticeSubject = mysqli_real_escape_string($connection, $noticeSubject);
    $noticeSql = "INSERT INTO `tenant_notices` (`tenant_id`, `subject`, `message`, `sender_role`, `created_by_name`, `document_url`, `document_label`, `secondary_document_url`, `secondary_document_label`, `status`) VALUES ('$tenantId', '$safeNoticeSubject', '$safeNoticeMessage', 'Admin', '$safeCreatedBy', '$safeInvoicePdfUrl', 'Invoice PDF', '$safeReceiptPdfUrl', 'Receipt PDF', 'Published')";
    $mysqli->query($noticeSql) ? null : $state = false;
}

if (!$state) {
    $mysqli->rollback();
    api_json(['ok' => false, 'message' => 'Payment could not be saved.'], 500);
}

$mysqli->commit();
$finalMessage = "Greetings " . $firstName . ", This is a confirmation that your rent payment of AED " . format_money_amount($amountPaid) . " has been received and updated.";
if ($balanceCents > 0) {
    $finalMessage .= " Remaining balance to pay is AED " . format_money_amount($balance) . ".";
} else {
    $finalMessage .= " Your invoice is now fully paid.";
}
$finalMessage .= " Thank you.";
@sendSMS($phone, $finalMessage);
@send_payment_receipt_to_tenant_telegram($connection, (int) $paymentId, (int) $tenantId);

api_json(['ok' => true, 'paymentID' => $paymentId]);
