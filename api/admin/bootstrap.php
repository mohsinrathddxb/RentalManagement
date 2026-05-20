<?php

declare(strict_types=1);

require_once __DIR__ . '/../../admin/functions/db.php';
require_once __DIR__ . '/../../admin/functions/auth_helpers.php';
require_once __DIR__ . '/../../admin/functions/tenant_helpers.php';
require_once __DIR__ . '/../../admin/functions/partition_helpers.php';

$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? trim((string) $_SERVER['HTTP_ORIGIN']) : '';
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

ensure_tenant_schema($connection);
ensure_partition_tables($connection);
ensure_auth_tables($connection);

function api_json($payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function api_get_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function api_require_auth(): array
{
    if (!is_logged_in_temporary()) {
        api_json([
            'ok' => false,
            'message' => 'Authentication required.',
        ], 401);
    }

    return [
        'email' => isset($_SESSION['email']) ? (string) $_SESSION['email'] : '',
        'name' => isset($_SESSION['name']) ? (string) $_SESSION['name'] : '',
        'role' => isset($_SESSION['role']) ? (string) $_SESSION['role'] : '',
        'isAdmin' => is_admin_user(),
        'isTenant' => is_tenant_user(),
    ];
}

function api_require_admin(): array
{
    $auth = api_require_auth();
    if (!$auth['isAdmin']) {
        api_json([
            'ok' => false,
            'message' => 'Admin access required.',
        ], 403);
    }

    return $auth;
}
