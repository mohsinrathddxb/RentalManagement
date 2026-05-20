<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = api_get_json_input();
$email = is_email(isset($input['email']) ? (string) $input['email'] : '');
$password = isset($input['password']) ? trim((string) $input['password']) : '';
$password2 = isset($input['password2']) ? trim((string) $input['password2']) : '';

if ($email === '' || $password === '' || $password2 === '') {
    api_json(['ok' => false, 'message' => 'Email and both password fields are required.'], 422);
}

if (!auth_can_reset_password_for_email($email)) {
    api_json(['ok' => false, 'message' => 'Please verify your OTP again before resetting the password.'], 403);
}

if ($password !== $password2) {
    api_json(['ok' => false, 'message' => 'Passwords do not match.'], 422);
}

if (strlen($password) < 6) {
    api_json(['ok' => false, 'message' => 'Password should be at least 6 characters.'], 422);
}

$account = find_auth_account_by_email($connection, $email);
if (!$account) {
    auth_clear_password_reset_session();
    api_json(['ok' => false, 'message' => 'The account could not be found.'], 404);
}

$safeEmail = mysqli_real_escape_string($connection, $email);
$safeHash = mysqli_real_escape_string($connection, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
$updated = mysqli_query($connection, "UPDATE `admin` SET `password`='$safeHash' WHERE `email`='$safeEmail' LIMIT 1");

if (!$updated) {
    api_json(['ok' => false, 'message' => 'Password could not be updated.'], 500);
}

auth_mark_otp_consumed($connection, $email);
auth_clear_password_reset_session();

api_json([
    'ok' => true,
    'message' => 'Password reset successfully. You can sign in now.',
]);
