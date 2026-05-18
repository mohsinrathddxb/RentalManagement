<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (!is_logged_in_temporary()) {
    api_json([
        'ok' => true,
        'authenticated' => false,
        'user' => null,
    ]);
}

$auth = api_require_auth();
$tenant = get_logged_in_tenant_record();

api_json([
    'ok' => true,
    'authenticated' => true,
    'user' => [
        'email' => $auth['email'],
        'name' => $auth['name'],
        'role' => $auth['role'],
        'isAdmin' => $auth['isAdmin'],
        'isTenant' => $auth['isTenant'],
        'tenant' => $tenant ? [
            'tenantID' => (int) $tenant['tenantID'],
            'tenant_name' => (string) $tenant['tenant_name'],
            'house_name' => isset($tenant['house_name']) ? (string) $tenant['house_name'] : '',
            'partition_number' => isset($tenant['partition_number']) ? (string) $tenant['partition_number'] : '',
            'rent_amount' => isset($tenant['rent_amount']) ? (string) $tenant['rent_amount'] : '',
        ] : null,
    ],
]);
