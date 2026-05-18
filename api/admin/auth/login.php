<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = api_get_json_input();
$email = isset($input['email']) ? trim((string) $input['email']) : '';
$password = isset($input['password']) ? trim((string) $input['password']) : '';

if ($email === '' || $password === '') {
    api_json([
        'ok' => false,
        'message' => 'Email and password are required.',
        'errors' => [
            'email' => $email === '' ? 'Please enter an email address.' : '',
            'password' => $password === '' ? 'Please enter your password.' : '',
        ],
    ], 422);
}

$tenantEmail = mysqli_real_escape_string($connection, is_email($email));
$tenantResult = mysqli_query($connection, "SELECT `tenantID`, `tenant_name`, `email`, `phone_number` FROM `tenants` WHERE `email`='$tenantEmail' AND `tenant_status`='Active' ORDER BY `tenantID` DESC LIMIT 1");
if ($tenantResult && mysqli_num_rows($tenantResult) === 1) {
    $tenantRow = mysqli_fetch_assoc($tenantResult);
    ensure_tenant_user_account($connection, (int) $tenantRow['tenantID'], $tenantRow['tenant_name'], $tenantRow['email'], $tenantRow['phone_number']);
}

$sql = "SELECT id, name, role, email, password, tenant_id FROM admin WHERE email = ?";
$stmt = mysqli_prepare($connection, $sql);

if (!$stmt) {
    api_json(['ok' => false, 'message' => 'Unable to prepare login request.'], 500);
}

mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) !== 1) {
    mysqli_stmt_close($stmt);
    api_json(['ok' => false, 'message' => 'No account found with that email.'], 401);
}

mysqli_stmt_bind_result($stmt, $adminId, $name, $role, $resolvedEmail, $hashedPassword, $tenantId);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$isValidPassword = password_verify($password, $hashedPassword);

if (!$isValidPassword && $role === 'user') {
    $tenantPhoneResult = false;
    if (!empty($tenantId)) {
        $tenantPhoneResult = mysqli_query($connection, "SELECT `phone_number` FROM `tenants` WHERE `tenantID`='" . (int) $tenantId . "' LIMIT 1");
    } else {
        $safeEmailFallback = mysqli_real_escape_string($connection, $resolvedEmail);
        $tenantPhoneResult = mysqli_query($connection, "SELECT `phone_number` FROM `tenants` WHERE `email`='$safeEmailFallback' AND `tenant_status`='Active' ORDER BY `tenantID` DESC LIMIT 1");
    }

    if ($tenantPhoneResult && mysqli_num_rows($tenantPhoneResult) === 1) {
        $tenantPhoneRow = mysqli_fetch_assoc($tenantPhoneResult);
        $normalizedEnteredPassword = normalize_phone_for_login($password);
        $normalizedTenantPhone = normalize_phone_for_login($tenantPhoneRow['phone_number']);

        if ($normalizedEnteredPassword !== '' && $normalizedTenantPhone !== '' && ($normalizedEnteredPassword === $normalizedTenantPhone || ltrim($normalizedEnteredPassword, '+') === ltrim($normalizedTenantPhone, '+'))) {
            $isValidPassword = true;
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $safeHash = mysqli_real_escape_string($connection, $newHash);
            mysqli_query($connection, "UPDATE `admin` SET `password`='$safeHash' WHERE `id`='" . (int) $adminId . "'");
        }
    }
}

if (!$isValidPassword) {
    api_json(['ok' => false, 'message' => 'The password you entered was not valid.'], 401);
}

$_SESSION['email'] = $resolvedEmail;
$_SESSION['name'] = $name;
$_SESSION['role'] = $role;

$tenant = get_logged_in_tenant_record();

api_json([
    'ok' => true,
    'user' => [
        'email' => $resolvedEmail,
        'name' => $name,
        'role' => $role,
        'isAdmin' => is_admin_user(),
        'isTenant' => is_tenant_user(),
        'tenant' => $tenant ? [
            'tenantID' => (int) $tenant['tenantID'],
            'tenant_name' => (string) $tenant['tenant_name'],
            'house_name' => isset($tenant['house_name']) ? (string) $tenant['house_name'] : '',
            'partition_number' => isset($tenant['partition_number']) ? (string) $tenant['partition_number'] : '',
        ] : null,
    ],
]);

