<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = api_get_json_input();
$email = is_email(isset($input['email']) ? (string) $input['email'] : '');
$otp = trim((string) (isset($input['otp']) ? $input['otp'] : ''));

if ($email === '' || $otp === '') {
    api_json(['ok' => false, 'message' => 'Email and OTP are required.'], 422);
}

$verified = auth_verify_email_otp($connection, $email, $otp);
if (!$verified['ok']) {
    api_json(['ok' => false, 'message' => $verified['message']], 422);
}

auth_set_password_reset_session($email);

api_json([
    'ok' => true,
    'message' => 'OTP verified successfully. You can now set a new password.',
]);
