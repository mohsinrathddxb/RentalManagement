<?php
ob_start();

require_once "functions/db.php";
require_once "functions/tenant_helpers.php";
require_once "functions/invoice_pdf_helpers.php";

session_start();

function mobile_pdf_response($statusCode, $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: text/html; charset=utf-8');

    $json = json_encode($payload);
    $safeJson = str_replace('</script', '<\/script', $json);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Preparing PDF</title></head>';
    echo '<body style="font-family:Arial,sans-serif;background:#071A2D;color:#F6F2E8;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;">';
    echo '<div style="text-align:center;padding:24px;"><h3 style="color:#C8A449;margin:0 0 10px;">Preparing PDF</h3><p style="margin:0;">Please wait...</p></div>';
    echo '<script>window.__coLivingMobilePdfPayload = ' . $safeJson . ';</script>';
    echo '</body></html>';
    exit;
}

$type = isset($_GET['type']) ? strtolower(trim((string) $_GET['type'])) : '';
$documentId = isset($_GET['id']) ? trim((string) $_GET['id']) : '';

if ($type === '' || $documentId === '') {
    mobile_pdf_response(400, [
        'success' => false,
        'message' => 'Missing PDF document request.'
    ]);
}

if (!in_array($type, ['invoice', 'receipt'], true)) {
    mobile_pdf_response(400, [
        'success' => false,
        'message' => 'Invalid PDF document type.'
    ]);
}

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    $mobileEmail = isset($_GET['mobile_user']) ? strtolower(trim((string) $_GET['mobile_user'])) : '';
    $mobileToken = isset($_GET['mobile_token']) ? trim((string) $_GET['mobile_token']) : '';
    if ($mobileEmail !== '' && pdf_mobile_token_is_valid($mobileEmail, $type, $documentId, $mobileToken)) {
        $_SESSION['email'] = $mobileEmail;
    }
}

if (!isset($_SESSION['email']) || empty($_SESSION['email']) || !is_logged_in_temporary()) {
    mobile_pdf_response(401, [
        'success' => false,
        'message' => 'Please log in again before downloading this PDF.'
    ]);
}

ensure_tenant_schema($connection);
ensure_invoice_pdf_columns($connection);

$tenantId = 0;
if (!is_admin_user()) {
    $currentTenant = get_logged_in_tenant_record();
    $tenantId = $currentTenant ? (int) $currentTenant['tenantID'] : 0;
    if ($tenantId <= 0) {
        mobile_pdf_response(403, [
            'success' => false,
            'message' => 'This account is not allowed to download the requested PDF.'
        ]);
    }
}

if ($type === 'invoice') {
    $invoiceRow = build_invoice_document_data($connection, $documentId, $tenantId);
    if (!$invoiceRow) {
        mobile_pdf_response(404, [
            'success' => false,
            'message' => 'Invoice PDF was not found.'
        ]);
    }

    $binary = generate_invoice_pdf_binary($invoiceRow);
    $filename = 'invoice-' . $invoiceRow['invoiceNumber'] . '.pdf';
} else {
    $paymentId = (int) $documentId;
    if ($paymentId <= 0) {
        mobile_pdf_response(400, [
            'success' => false,
            'message' => 'Invalid payment receipt request.'
        ]);
    }

    $paymentRow = build_payment_receipt_data($connection, $paymentId, $tenantId);
    if (!$paymentRow) {
        mobile_pdf_response(404, [
            'success' => false,
            'message' => 'Receipt PDF was not found.'
        ]);
    }

    $binary = generate_payment_receipt_pdf_binary($paymentRow);
    $filename = 'payment-receipt-' . $paymentRow['paymentID'] . '.pdf';
}

mobile_pdf_response(200, [
    'success' => true,
    'filename' => $filename,
    'content_type' => 'application/pdf',
    'data' => base64_encode($binary)
]);
