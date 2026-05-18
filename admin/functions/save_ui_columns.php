<?php

require_once "db.php";
require_once "ui_column_preferences.php";

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in_temporary() || !is_admin_user()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$tableKey = isset($payload['table_key']) ? trim((string) $payload['table_key']) : '';
$visibleColumns = isset($payload['visible_columns']) && is_array($payload['visible_columns']) ? $payload['visible_columns'] : [];

if ($tableKey === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing table key']);
    exit;
}

$allowedTableKeys = ['tenants_view', 'invoices_view', 'payments_view', 'houses_view'];
if (!in_array($tableKey, $allowedTableKeys, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Table key not allowed']);
    exit;
}

ensure_ui_column_preferences_schema($connection);
$saved = save_ui_visible_columns($connection, $_SESSION['email'], $tableKey, $visibleColumns);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save preferences']);
    exit;
}

echo json_encode(['success' => true]);
