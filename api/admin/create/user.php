<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

api_require_admin();
$input = api_get_json_input();
$email = is_email(isset($input['email']) ? (string) $input['email'] : '');
$uname = is_username(isset($input['uname']) ? (string) $input['uname'] : '');
$role = uncrack(isset($input['role']) ? (string) $input['role'] : '');
$password = isset($input['password']) ? (string) $input['password'] : '';
$password2 = isset($input['password2']) ? (string) $input['password2'] : '';
$allowedRoles = ['level-0', 'level-1', 'level-2', 'level-3', 'user'];
if (!in_array($role, $allowedRoles, true)) {
    $role = 'user';
}

if ($email === '' || $uname === '' || $password === '' || $password2 === '') {
    api_json(['ok' => false, 'message' => 'All required fields must be provided.'], 422);
}
if ($password !== $password2) {
    api_json(['ok' => false, 'message' => 'Passwords do not match.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_json(['ok' => false, 'message' => 'Invalid email format.'], 422);
}
$exists = mysqli_query($conn, "SELECT `id` FROM `admin` WHERE `email` = '$email'");
if ($exists && mysqli_num_rows($exists) > 0) {
    api_json(['ok' => false, 'message' => 'An account with that email already exists.'], 409);
}

$hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $db->prepare("INSERT INTO `admin` (`email`, `password`,`name`,`role`) VALUES (:email, :password, :name, :role)");
try {
    $stmt->execute([
        ':email' => $email,
        ':password' => $hashedPassword,
        ':name' => $uname,
        ':role' => $role,
    ]);
    api_json(['ok' => true]);
} catch (Exception $e) {
    api_json(['ok' => false, 'message' => 'Admin could not be created.'], 500);
}
