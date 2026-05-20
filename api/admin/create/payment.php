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
$amountPaidCents = isset($input['paidAmount']) ? money_to_cents((string) $input['paidAmount']) : 0;
$mpesaCode = isset($input['mpesa']) ? uncrack((string) $input['mpesa']) : '';
$comment = isset($input['comment']) ? uncrack((string) $input['comment']) : '';

if ($tenantId <= 0 || $invoiceNumber === '' || $amountPaidCents <= 0) {
    api_json(['ok' => false, 'message' => 'Please select an invoice first so tenant and amount details can load.'], 422);
}

$paymentDate = date('Y-m-d');
$timesnap = date('Y-m-d : H:i:s');

$tenquer = mysqli_query($conn, "SELECT `account`, `tenant_name`, `phone_number` FROM `tenants` WHERE `tenantID`='$tenantId' LIMIT 1");
$rec = $tenquer ? mysqli_fetch_array($tenquer, MYSQLI_BOTH) : null;
if (!$rec) {
    api_json(['ok' => false, 'message' => 'Tenant could not be found.'], 404);
}

$safeInvoiceNumber = mysqli_real_escape_string($connection, $invoiceNumber);
$invoiceQuery = mysqli_query($conn, "
    SELECT
        `invoiceNumber`,
        `tenantID`,
        `amountDue`,
        `rent_amount`,
        `booking_amount`,
        `deposit_amount`,
        `credit_applied`,
        `total_amount`,
        `status`
    FROM `invoices`
    WHERE `invoiceNumber`='$safeInvoiceNumber'
      AND `tenantID`='$tenantId'
    LIMIT 1
");
$invoiceRow = $invoiceQuery ? mysqli_fetch_assoc($invoiceQuery) : null;
if (!$invoiceRow) {
    api_json(['ok' => false, 'message' => 'Invoice could not be found for this tenant.'], 404);
}

$previousPaymentsQuery = mysqli_query($conn, "
    SELECT COALESCE(SUM(CAST(`amountPaid` AS DECIMAL(10,2))), 0) AS total
    FROM `payments`
    WHERE `invoiceNumber`='$safeInvoiceNumber'
");
$previousPaymentsRow = $previousPaymentsQuery ? mysqli_fetch_assoc($previousPaymentsQuery) : ['total' => 0];
$previousPaymentsCents = max(0, money_to_cents((string) $previousPaymentsRow['total']));
$beforeFinancials = summarize_invoice_financials($invoiceRow, $previousPaymentsCents);
$afterFinancials = summarize_invoice_financials($invoiceRow, $previousPaymentsCents + $amountPaidCents);
$paymentAllocation = summarize_payment_allocation([
    'rent_amount' => $invoiceRow['rent_amount'],
    'booking_amount' => $invoiceRow['booking_amount'],
    'deposit_amount' => $invoiceRow['deposit_amount'],
    'credit_applied' => $invoiceRow['credit_applied'],
    'total_amount' => $invoiceRow['total_amount'],
    'amountPaid' => cents_to_money($amountPaidCents),
    'paid_before' => cents_to_money($previousPaymentsCents),
    'paid_through_this_receipt' => cents_to_money($previousPaymentsCents + $amountPaidCents),
]);

$accountCents = isset($rec['account']) ? money_to_cents($rec['account']) : 0;
$tenantName = $rec['tenant_name'];
$firstName = strpos($tenantName, " ") !== false ? substr($tenantName, 0, strpos($tenantName, " ")) : $tenantName;
$phone = $rec['phone_number'];
$status = (string) $afterFinancials['status'];
$balanceCents = (int) $afterFinancials['remaining_due_cents'];
$amountExpectedCents = (int) $beforeFinancials['remaining_due_cents'];
$accountCents += (int) $paymentAllocation['advance_created_cents'];

$amountExpected = cents_to_money($amountExpectedCents);
$amountPaid = cents_to_money($amountPaidCents);
$balance = cents_to_money($balanceCents);
$accountBalance = cents_to_money($accountCents);
$safeMpesaCode = mysqli_real_escape_string($connection, $mpesaCode);
$safeComment = mysqli_real_escape_string($connection, $comment);
$safeTenantName = mysqli_real_escape_string($connection, $tenantName);

$sqlInv = "UPDATE `invoices` SET `amountDue`='$balance', `status`='$status' WHERE `invoiceNumber`='$safeInvoiceNumber'";
$sqlTen = "UPDATE `tenants` SET `account`='$accountBalance' WHERE `tenantID`='$tenantId'";
$sqlPayment = "INSERT INTO `payments` (`tenantID`, `invoiceNumber`, `expectedAmount`, `amountPaid`, `balance`, `mpesaCode`, `dateofPayment`, `comment`) VALUES ('$tenantId', '$safeInvoiceNumber', '$amountExpected', '$amountPaid', '$balance', '$safeMpesaCode', '$paymentDate', '$safeComment')";
$sqlTransactions = "INSERT INTO `transactions` (`actor`, `time`, `description`) VALUES ('Admin ($username)', '$timesnap', '$username added payment of ".format_money_amount($amountPaid)." for $safeTenantName, under invoice ID: $safeInvoiceNumber')";

$noticeMessage = 'A payment of AED ' . format_money_amount($amountPaid) . ' was received for invoice ' . $invoiceNumber . '.';
if ($balanceCents > 0) {
    $noticeMessage .= ' Remaining rent due is AED ' . format_money_amount($afterFinancials['rent_due']) . ', remaining deposit due is AED ' . format_money_amount($afterFinancials['deposit_due']) . ', and total remaining balance is AED ' . format_money_amount($balance) . '.';
} else {
    $noticeMessage .= ' This invoice is now fully paid.';
}
if ((float) $paymentAllocation['advance_created'] > 0) {
    $noticeMessage .= ' Extra payment of AED ' . format_money_amount($paymentAllocation['advance_created']) . ' was added to the tenant advance balance.';
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
$finalMessage = "Greetings " . $firstName . ", This is a confirmation that your payment of AED " . format_money_amount($amountPaid) . " has been received and updated.";
if ($balanceCents > 0) {
    $finalMessage .= " Remaining rent due is AED " . format_money_amount($afterFinancials['rent_due']) . ", remaining deposit due is AED " . format_money_amount($afterFinancials['deposit_due']) . ", and total remaining balance is AED " . format_money_amount($balance) . ".";
} else {
    $finalMessage .= " Your invoice is now fully paid.";
}
if ((float) $paymentAllocation['advance_created'] > 0) {
    $finalMessage .= " AED " . format_money_amount($paymentAllocation['advance_created']) . " has been added to your advance balance.";
}
$finalMessage .= " Thank you.";
@sendSMS($phone, $finalMessage);
@send_payment_receipt_to_tenant_telegram($connection, (int) $paymentId, (int) $tenantId);

api_json(['ok' => true, 'paymentID' => $paymentId]);
