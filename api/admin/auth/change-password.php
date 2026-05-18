<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$auth = api_require_auth();
$input = api_get_json_input();
$password = isset($input['password']) ? trim((string) $input['password']) : '';
$password2 = isset($input['password2']) ? trim((string) $input['password2']) : '';

if ($password === '' || $password2 === '') {
    api_json(['ok' => false, 'message' => 'Both password fields are required.'], 422);
}

if ($password !== $password2) {
    api_json(['ok' => false, 'message' => 'Passwords do not match.'], 422);
}

if (strlen($password) < 6) {
    api_json(['ok' => false, 'message' => 'Password should be at least 6 characters.'], 422);
}

$safeEmail = mysqli_real_escape_string($connection, $auth['email']);
$newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$safeHash = mysqli_real_escape_string($connection, $newHash);
$updated = mysqli_query($connection, "UPDATE `admin` SET `password`='$safeHash' WHERE `email`='$safeEmail' LIMIT 1");

if (!$updated) {
    api_json(['ok' => false, 'message' => 'Password could not be updated.'], 500);
}

api_json(['ok' => true, 'message' => 'Password updated successfully.']);
