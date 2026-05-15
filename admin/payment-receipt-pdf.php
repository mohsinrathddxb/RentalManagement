<?php
ob_start();

require_once "functions/db.php";
require_once "functions/tenant_helpers.php";
require_once "functions/invoice_pdf_helpers.php";

session_start();

$paymentId = isset($_GET['payment']) ? (int) $_GET['payment'] : 0;
if ($paymentId <= 0) {
    $fallbackLocation = is_admin_user() ? "payments.php" : "invoices.php";
    header("location: " . $fallbackLocation);
    exit;
}

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    $mobileEmail = isset($_GET['mobile_user']) ? strtolower(trim((string) $_GET['mobile_user'])) : '';
    $mobileToken = isset($_GET['mobile_token']) ? trim((string) $_GET['mobile_token']) : '';
    if ($mobileEmail !== '' && pdf_mobile_token_is_valid($mobileEmail, 'receipt', $paymentId, $mobileToken)) {
        $_SESSION['email'] = $mobileEmail;
    }
}

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

if (ob_get_length()) {
    ob_clean();
}

render_payment_receipt_pdf($paymentRow);
