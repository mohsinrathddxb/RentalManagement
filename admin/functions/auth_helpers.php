<?php

require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function ensure_auth_tables($connection) {
    @mysqli_query($connection, "
        CREATE TABLE IF NOT EXISTS `auth_otp_requests` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `email` varchar(255) NOT NULL,
            `purpose` varchar(50) NOT NULL,
            `otp_hash` varchar(255) NOT NULL,
            `attempt_count` int(11) NOT NULL DEFAULT 0,
            `expires_at` datetime NOT NULL,
            `verified_at` datetime DEFAULT NULL,
            `consumed_at` datetime DEFAULT NULL,
            `delivery_channel` varchar(20) NOT NULL DEFAULT 'email',
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `email_purpose_created` (`email`, `purpose`, `created_at`),
            KEY `expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function find_auth_account_by_email($connection, $email) {
    $email = is_email($email);
    if ($email === '') {
        return null;
    }

    $stmt = mysqli_prepare($connection, "SELECT `id`, `name`, `role`, `email`, `tenant_id` FROM `admin` WHERE `email` = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) !== 1) {
        mysqli_stmt_close($stmt);
        return null;
    }

    mysqli_stmt_bind_result($stmt, $id, $name, $role, $resolvedEmail, $tenantId);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return [
        'id' => (int) $id,
        'name' => (string) $name,
        'role' => (string) $role,
        'email' => (string) $resolvedEmail,
        'tenant_id' => $tenantId !== null ? (int) $tenantId : null,
    ];
}

function auth_issue_email_otp($connection, $email, $purpose = 'password_reset') {
    ensure_auth_tables($connection);

    $email = is_email($email);
    if ($email === '') {
        return ['ok' => false, 'message' => 'A valid email is required.'];
    }

    $safeEmail = mysqli_real_escape_string($connection, $email);
    $safePurpose = mysqli_real_escape_string($connection, $purpose);
    @mysqli_query(
        $connection,
        "UPDATE `auth_otp_requests`
         SET `consumed_at` = NOW()
         WHERE `email` = '$safeEmail'
           AND `purpose` = '$safePurpose'
           AND `consumed_at` IS NULL
           AND `verified_at` IS NULL"
    );

    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_BCRYPT, ['cost' => 12]);
    $safeHash = mysqli_real_escape_string($connection, $otpHash);
    $expiresAt = date('Y-m-d H:i:s', time() + 600);

    $inserted = mysqli_query(
        $connection,
        "INSERT INTO `auth_otp_requests` (`email`, `purpose`, `otp_hash`, `expires_at`)
         VALUES ('$safeEmail', '$safePurpose', '$safeHash', '$expiresAt')"
    );

    if (!$inserted) {
        return ['ok' => false, 'message' => 'The OTP request could not be created.'];
    }

    $delivery = send_auth_otp_email($email, $otp);
    if (!$delivery['ok']) {
        return ['ok' => false, 'message' => $delivery['message']];
    }

    return ['ok' => true, 'delivery' => $delivery['delivery']];
}

function auth_verify_email_otp($connection, $email, $otp, $purpose = 'password_reset') {
    ensure_auth_tables($connection);

    $email = is_email($email);
    $otp = trim((string) $otp);

    if ($email === '' || $otp === '') {
        return ['ok' => false, 'message' => 'Email and OTP are required.'];
    }

    $safeEmail = mysqli_real_escape_string($connection, $email);
    $safePurpose = mysqli_real_escape_string($connection, $purpose);
    $result = mysqli_query(
        $connection,
        "SELECT * FROM `auth_otp_requests`
         WHERE `email` = '$safeEmail'
           AND `purpose` = '$safePurpose'
           AND `consumed_at` IS NULL
         ORDER BY `id` DESC
         LIMIT 1"
    );

    if (!$result || mysqli_num_rows($result) !== 1) {
        return ['ok' => false, 'message' => 'No valid OTP request was found.'];
    }

    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        return ['ok' => false, 'message' => 'No valid OTP request was found.'];
    }

    if (!empty($row['verified_at'])) {
        return ['ok' => true, 'message' => 'OTP already verified.'];
    }

    if (strtotime((string) $row['expires_at']) < time()) {
        @mysqli_query($connection, "UPDATE `auth_otp_requests` SET `consumed_at` = NOW() WHERE `id` = '" . (int) $row['id'] . "' LIMIT 1");
        return ['ok' => false, 'message' => 'This OTP has expired. Please request a new one.'];
    }

    if ((int) $row['attempt_count'] >= 5) {
        @mysqli_query($connection, "UPDATE `auth_otp_requests` SET `consumed_at` = NOW() WHERE `id` = '" . (int) $row['id'] . "' LIMIT 1");
        return ['ok' => false, 'message' => 'Too many invalid OTP attempts. Please request a new code.'];
    }

    if (!password_verify($otp, (string) $row['otp_hash'])) {
        @mysqli_query(
            $connection,
            "UPDATE `auth_otp_requests`
             SET `attempt_count` = `attempt_count` + 1
             WHERE `id` = '" . (int) $row['id'] . "' LIMIT 1"
        );
        return ['ok' => false, 'message' => 'The OTP you entered is not correct.'];
    }

    @mysqli_query(
        $connection,
        "UPDATE `auth_otp_requests`
         SET `verified_at` = NOW()
         WHERE `id` = '" . (int) $row['id'] . "' LIMIT 1"
    );

    return ['ok' => true, 'message' => 'OTP verified successfully.'];
}

function auth_mark_otp_consumed($connection, $email, $purpose = 'password_reset') {
    $email = is_email($email);
    if ($email === '') {
        return;
    }

    $safeEmail = mysqli_real_escape_string($connection, $email);
    $safePurpose = mysqli_real_escape_string($connection, $purpose);
    @mysqli_query(
        $connection,
        "UPDATE `auth_otp_requests`
         SET `consumed_at` = NOW()
         WHERE `email` = '$safeEmail'
           AND `purpose` = '$safePurpose'
           AND `verified_at` IS NOT NULL
           AND `consumed_at` IS NULL"
    );
}

function auth_set_password_reset_session($email) {
    $_SESSION['password_reset_email'] = is_email($email);
    $_SESSION['password_reset_expires_at'] = time() + 900;
}

function auth_clear_password_reset_session() {
    unset($_SESSION['password_reset_email'], $_SESSION['password_reset_expires_at']);
}

function auth_can_reset_password_for_email($email) {
    $email = is_email($email);
    $sessionEmail = isset($_SESSION['password_reset_email']) ? is_email($_SESSION['password_reset_email']) : '';
    $expiresAt = isset($_SESSION['password_reset_expires_at']) ? (int) $_SESSION['password_reset_expires_at'] : 0;

    return $email !== '' && $sessionEmail === $email && $expiresAt >= time();
}

function send_auth_otp_email($email, $otp) {
    $subject = 'Your Rental Manager OTP';
    $fromEmail = !empty($GLOBALS['app_mail_from']) ? (string) $GLOBALS['app_mail_from'] : 'no-reply@rental-manager.local';
    $fromName = !empty($GLOBALS['app_mail_from_name']) ? (string) $GLOBALS['app_mail_from_name'] : 'Rental Manager';
    $smtpHost = isset($GLOBALS['smtp_host']) ? trim((string) $GLOBALS['smtp_host']) : '';
    $smtpUsername = isset($GLOBALS['smtp_username']) ? trim((string) $GLOBALS['smtp_username']) : '';
    $smtpPassword = isset($GLOBALS['smtp_password']) ? (string) $GLOBALS['smtp_password'] : '';
    $smtpPort = isset($GLOBALS['smtp_port']) ? (int) $GLOBALS['smtp_port'] : 587;
    $smtpSecure = isset($GLOBALS['smtp_secure']) ? trim((string) $GLOBALS['smtp_secure']) : 'tls';

    if ($smtpHost === '' || $smtpUsername === '' || $smtpPassword === '' || $fromEmail === '' || $fromEmail === 'no-reply@example.com') {
        return ['ok' => false, 'delivery' => 'email', 'message' => 'SMTP is not configured yet. Please update the mail settings in admin/functions/db.php.'];
    }

    $html = '
        <div style="font-family:Segoe UI,Arial,sans-serif;line-height:1.6;color:#0f1c2d">
            <h2 style="margin-bottom:8px">Password Reset OTP</h2>
            <p>Use the one-time password below to reset your password.</p>
            <div style="display:inline-block;padding:12px 18px;border-radius:12px;background:#f3e6bf;color:#6c4f12;font-size:28px;font-weight:700;letter-spacing:0.18em">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</div>
            <p style="margin-top:18px">This code expires in 10 minutes.</p>
            <p>If you did not request this, you can ignore this email.</p>
        </div>
    ';

    try {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $smtpHost;
        $mailer->Port = $smtpPort > 0 ? $smtpPort : 587;
        $mailer->SMTPAuth = true;
        $mailer->Username = $smtpUsername;
        $mailer->Password = $smtpPassword;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($email);
        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $html;
        $mailer->AltBody = "Your Rental Manager OTP is $otp. This code expires in 10 minutes.";

        if ($smtpSecure !== '') {
            $mailer->SMTPSecure = $smtpSecure;
        }

        $mailer->send();
        return ['ok' => true, 'delivery' => 'email'];
    } catch (Exception $exception) {
        return ['ok' => false, 'delivery' => 'email', 'message' => 'OTP email could not be sent through SMTP. Check host, port, username, password, and encryption settings.'];
    }
}
