<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = api_get_json_input();
$email = is_email(isset($input['email']) ? (string) $input['email'] : '');

if ($email === '') {
    api_json(['ok' => false, 'message' => 'Please enter your registered email address.'], 422);
}

$account = find_auth_account_by_email($connection, $email);
if (!$account) {
    api_json([
        'ok' => true,
        'message' => 'If this email is registered, an OTP has been sent.',
    ]);
}

$issued = auth_issue_email_otp($connection, $email);
if (!$issued['ok']) {
    api_json(['ok' => false, 'message' => $issued['message']], 500);
}

api_json([
    'ok' => true,
    'message' => 'If this email is registered, an OTP has been sent.',
]);
