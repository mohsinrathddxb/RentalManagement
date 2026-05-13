<?php
ob_start();

require_once "functions/db.php";
require_once "functions/tenant_helpers.php";
require_once "functions/invoice_pdf_helpers.php";

session_start();

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header("location: login.php");
    exit;
}

if (!is_logged_in_temporary()) {
    header("location: login.php");
    exit;
}

ensure_tenant_schema($connection);
ensure_invoice_pdf_columns($connection);

$paymentId = isset($_GET['payment']) ? (int) $_GET['payment'] : 0;
if ($paymentId <= 0) {
    $fallbackLocation = is_admin_user() ? "payments.php" : "invoices.php";
    header("location: " . $fallbackLocation);
    exit;
}

$tenantId = 0;
if (!is_admin_user()) {
    $currentTenant = get_logged_in_tenant_record();
    $tenantId = $currentTenant ? (int) $currentTenant['tenantID'] : 0;
    if ($tenantId <= 0) {
        header("location: index.php?restricted=1");
        exit;
    }
}

$paymentRow = build_payment_receipt_data($connection, $paymentId, $tenantId);
if (!$paymentRow) {
    $fallbackLocation = is_admin_user() ? "payments.php" : "invoices.php";
    header("location: " . $fallbackLocation . "?missing_pdf=1");
    exit;
}

render_payment_receipt_pdf($paymentRow);
