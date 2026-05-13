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

$invoiceNumber = isset($_GET['invoice']) ? trim((string) $_GET['invoice']) : '';
if ($invoiceNumber === '') {
    header("location: invoices.php");
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

$invoiceRow = build_invoice_document_data($connection, $invoiceNumber, $tenantId);
if (!$invoiceRow) {
    header("location: invoices.php?missing_pdf=1");
    exit;
}

render_invoice_pdf($invoiceRow);
