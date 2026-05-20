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
$tenantId = isset($input['tenant_id']) ? (int) $input['tenant_id'] : 0;
$invoiceDueDate = isset($input['ddate']) ? uncrack((string) $input['ddate']) : '';
$comment = isset($input['comment']) ? uncrack((string) $input['comment']) : '';
$invoiceMonth = isset($input['invoice_month']) ? uncrack((string) $input['invoice_month']) : date('Y-m');
$depositAmountCents = isset($input['deposit_amount']) ? money_to_cents((string) $input['deposit_amount']) : 0;

if ($tenantId <= 0 || $invoiceMonth === '' || $depositAmountCents < 0) {
    api_json(['ok' => false, 'message' => 'Please select a tenant and invoice month.'], 422);
}

$invoiceDate = $invoiceMonth . '-01';
$invoiceid = 'INV' . date('YmdHis');
$queryt = mysqli_query($conn, "
    SELECT
        t.`tenant_name`,
        t.`phone_number`,
        t.`account`,
        COALESCE(hp.`rent_amount`, h.`rent_amount`, 0) AS `rent_amount`
    FROM `tenants` t
    LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
    LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
    WHERE t.`tenantID`='$tenantId'
    LIMIT 1
");
$tenRecord = $queryt ? mysqli_fetch_array($queryt, MYSQLI_BOTH) : null;
if (!$tenRecord) {
    api_json(['ok' => false, 'message' => 'Tenant could not be found.'], 404);
}

$tenantName = $tenRecord['tenant_name'];
$firstName = strpos($tenantName, ' ') !== false ? substr($tenantName, 0, strpos($tenantName, ' ')) : $tenantName;
$phone = $tenRecord['phone_number'];
$accountCents = isset($tenRecord['account']) ? money_to_cents($tenRecord['account']) : 0;
$rentAmountCents = isset($tenRecord['rent_amount']) ? money_to_cents((string) $tenRecord['rent_amount']) : 0;
$totalAmountCents = $rentAmountCents + $depositAmountCents;
$creditAppliedCents = min($accountCents, $totalAmountCents);
$remainingAccountCents = max(0, $accountCents - $creditAppliedCents);
$financials = summarize_invoice_financials([
    'rent_amount' => invoice_cents_to_float($rentAmountCents),
    'deposit_amount' => invoice_cents_to_float($depositAmountCents),
    'booking_amount' => 0,
    'credit_applied' => invoice_cents_to_float($creditAppliedCents),
    'total_amount' => invoice_cents_to_float($totalAmountCents),
    'total_paid' => 0,
]);
$istatus = (string) $financials['status'];

$rentAmount = cents_to_money($rentAmountCents);
$depositAmount = cents_to_money($depositAmountCents);
$creditApplied = cents_to_money($creditAppliedCents);
$totalAmount = cents_to_money($totalAmountCents);
$amountDue = cents_to_money($financials['remaining_due_cents']);
$remainingAccount = cents_to_money($remainingAccountCents);

$queryVerify = mysqli_query($conn, "SELECT * FROM `invoices` WHERE `tenantID`='$tenantId' AND `dateOfInvoice` LIKE '$invoiceMonth%'");
if ($queryVerify && mysqli_num_rows($queryVerify) >= 1) {
    api_json(['ok' => false, 'message' => "An invoice already exists for this tenant for month $invoiceMonth."], 409);
}

$sqInvoice = "INSERT INTO `invoices` (`invoiceNumber`,`tenantID`,`dateOfInvoice`,`dateDue`,`amountDue`,`rent_amount`,`booking_amount`,`deposit_amount`,`credit_applied`,`total_amount`,`comment`,`status`) VALUES ('$invoiceid','$tenantId','$invoiceDate','$invoiceDueDate','$amountDue','$rentAmount','0.00','$depositAmount','$creditApplied','$totalAmount','$comment','$istatus')";
$sqAccount = "UPDATE `tenants` SET `account`='$remainingAccount' WHERE `tenantID`='$tenantId'";
$timesnap = date('Y-m-d : H:i:s');
$sqlTransactions = "INSERT INTO `transactions` (`actor`,`time`,`description`) VALUES ('Admin ($username)', '$timesnap','$username added a new rental invoice ($invoiceid) for tenant ($tenantName) for month $invoiceMonth at $timesnap.')";

$mysqli->autocommit(false);
$status = true;
$mysqli->query($sqInvoice) ? null : $status = false;
$mysqli->query($sqAccount) ? null : $status = false;
$mysqli->query($sqlTransactions) ? null : $status = false;

if (!$status) {
    $mysqli->rollback();
    api_json(['ok' => false, 'message' => 'Invoice could not be created.'], 500);
}

$mysqli->commit();
$finalMessage = "Greetings ".$firstName.", This is a reminder that invoice ".$invoiceid." for ".$invoiceMonth." has been issued. Rent is AED ".format_money_amount($rentAmount).", deposit is AED ".format_money_amount($depositAmount).", and total due is AED ".format_money_amount($amountDue)." by date ".$invoiceDueDate.".";
@sendSMS($phone, $finalMessage);
@send_invoice_to_tenant_telegram($connection, $invoiceid, (int) $tenantId);

api_json(['ok' => true, 'invoiceNumber' => $invoiceid]);
