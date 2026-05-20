<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = api_get_json_input();
$fullName = is_username(isset($input['full_name']) ? (string) $input['full_name'] : '');
$email = is_email(isset($input['email']) ? (string) $input['email'] : '');
$phoneNumber = trim(uncrack(isset($input['phone_number']) ? (string) $input['phone_number'] : ''));
$country = is_username(isset($input['country']) ? (string) $input['country'] : '');
$idNumber = uncrack(isset($input['id_number']) ? (string) $input['id_number'] : '');
$tenantAddress = uncrack(isset($input['tenant_address']) ? (string) $input['tenant_address'] : '');
$tenantHomeCountryAddress = uncrack(isset($input['tenant_home_country_address']) ? (string) $input['tenant_home_country_address'] : '');
$profession = is_username(isset($input['profession']) ? (string) $input['profession'] : '');
$password = isset($input['password']) ? trim((string) $input['password']) : '';
$password2 = isset($input['password2']) ? trim((string) $input['password2']) : '';

if ($fullName === '' || $email === '' || $phoneNumber === '' || $country === '' || $idNumber === '' || $tenantAddress === '' || $tenantHomeCountryAddress === '' || $password === '' || $password2 === '') {
    api_json(['ok' => false, 'message' => 'Please complete all required fields.'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_json(['ok' => false, 'message' => 'Please enter a valid email address.'], 422);
}

if ($password !== $password2) {
    api_json(['ok' => false, 'message' => 'Passwords do not match.'], 422);
}

if (strlen($password) < 6) {
    api_json(['ok' => false, 'message' => 'Password should be at least 6 characters.'], 422);
}

$safeEmail = mysqli_real_escape_string($connection, $email);
$existingAccount = mysqli_query($connection, "SELECT `id` FROM `admin` WHERE `email`='$safeEmail' LIMIT 1");
if ($existingAccount && mysqli_num_rows($existingAccount) > 0) {
    api_json(['ok' => false, 'message' => 'An account with that email already exists.'], 409);
}

$existingTenant = mysqli_query($connection, "SELECT `tenantID` FROM `tenants` WHERE `email`='$safeEmail' ORDER BY `tenantID` DESC LIMIT 1");
if ($existingTenant && mysqli_num_rows($existingTenant) > 0) {
    api_json(['ok' => false, 'message' => 'A tenant with that email already exists.'], 409);
}

$dateAdmitted = date('20y-m-d');
$startDate = date('Y-m-d');
$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$safeName = mysqli_real_escape_string($connection, $fullName);
$safePhone = mysqli_real_escape_string($connection, $phoneNumber);
$safeCountry = mysqli_real_escape_string($connection, $country);
$safeIdNumber = mysqli_real_escape_string($connection, $idNumber);
$safeTenantAddress = mysqli_real_escape_string($connection, $tenantAddress);
$safeHomeAddress = mysqli_real_escape_string($connection, $tenantHomeCountryAddress);
$safeProfession = mysqli_real_escape_string($connection, $profession);

$mysqli->autocommit(false);

$tenantInserted = $mysqli->query("
    INSERT INTO `tenants`
    (`houseNumber`,`partition_id`,`tenant_name`,`email`,`ID_number`,`profession`,`phone_number`,`telegram_username`,`telegram_chat_id`,`tenant_address`,`tenant_home_country_address`,`tenant_country`,`start_date`,`end_date`,`tenant_status`,`dateAdmitted`)
    VALUES
    ('0', NULL, '$safeName', '$safeEmail', '$safeIdNumber', '$safeProfession', '$safePhone', '', '', '$safeTenantAddress', '$safeHomeAddress', '$safeCountry', '$startDate', '', 'Active', '$dateAdmitted')
");

if (!$tenantInserted) {
    $mysqli->rollback();
    api_json(['ok' => false, 'message' => 'The account could not be created right now.'], 500);
}

$tenantId = (int) $mysqli->insert_id;
if ($tenantId <= 0) {
    $tenantIdResult = mysqli_query($connection, "SELECT MAX(`tenantID`) AS latest_tenant_id FROM `tenants`");
    if ($tenantIdResult && ($tenantIdRow = mysqli_fetch_assoc($tenantIdResult))) {
        $tenantId = (int) $tenantIdRow['latest_tenant_id'];
    }
}

if ($tenantId <= 0 || !ensure_tenant_user_account($connection, $tenantId, $fullName, $email, $phoneNumber, $passwordHash)) {
    $mysqli->rollback();
    api_json(['ok' => false, 'message' => 'The login account could not be created.'], 500);
}

$mysqli->commit();

api_json([
    'ok' => true,
    'message' => 'Your account has been created successfully. You can sign in now.',
]);
