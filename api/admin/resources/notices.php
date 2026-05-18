<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = api_require_auth();
    $input = api_get_json_input();
    $action = isset($input['action']) ? trim((string) $input['action']) : '';

    if ($action === 'create') {
        $subject = uncrack(isset($input['subject']) ? (string) $input['subject'] : '');
        $message = uncrack(isset($input['message']) ? (string) $input['message'] : '');

        if ($subject === '' || $message === '') {
            api_json(['ok' => false, 'message' => 'Subject and message are required.'], 422);
        }

        $safeSubject = mysqli_real_escape_string($connection, $subject);
        $safeMessage = mysqli_real_escape_string($connection, $message);

        if ($auth['isAdmin']) {
            $recipientTenant = isset($input['recipient_tenant']) ? trim((string) $input['recipient_tenant']) : '';
            $createdByName = isset($_SESSION['name']) ? is_username($_SESSION['name']) : 'Admin';
            $safeCreatedByName = mysqli_real_escape_string($connection, $createdByName);

            if ($recipientTenant === 'all') {
                $tenantRows = mysqli_query($connection, "SELECT `tenantID` FROM `tenants` WHERE `tenant_status`='Active' ORDER BY `tenantID` DESC");
                if ($tenantRows) {
                    while ($tenantRow = mysqli_fetch_assoc($tenantRows)) {
                        $tenantId = (int) $tenantRow['tenantID'];
                        mysqli_query($connection, "INSERT INTO `tenant_notices` (`tenant_id`, `subject`, `message`, `sender_role`, `created_by_name`, `status`) VALUES ('$tenantId', '$safeSubject', '$safeMessage', 'Admin', '$safeCreatedByName', 'Published')");
                    }
                }
            } else {
                $tenantId = (int) $recipientTenant;
                if ($tenantId > 0) {
                    mysqli_query($connection, "INSERT INTO `tenant_notices` (`tenant_id`, `subject`, `message`, `sender_role`, `created_by_name`, `status`) VALUES ('$tenantId', '$safeSubject', '$safeMessage', 'Admin', '$safeCreatedByName', 'Published')");
                }
            }
        } else {
            $tenant = get_logged_in_tenant_record();
            if (!$tenant) {
                api_json(['ok' => false, 'message' => 'Tenant record not found.'], 404);
            }
            $tenantId = (int) $tenant['tenantID'];
            mysqli_query($connection, "INSERT INTO `tenant_notices` (`tenant_id`, `subject`, `message`, `sender_role`, `created_by_name`, `status`) VALUES ('$tenantId', '$safeSubject', '$safeMessage', 'Tenant', NULL, 'Open')");
        }

        api_json(['ok' => true]);
    }

    if ($action === 'update') {
        if (!$auth['isAdmin']) {
            api_json(['ok' => false, 'message' => 'Admin access required.'], 403);
        }

        $noticeId = (int) ($input['notice_id'] ?? 0);
        $status = uncrack(isset($input['status']) ? (string) $input['status'] : 'Open');
        $reply = uncrack(isset($input['admin_reply']) ? (string) $input['admin_reply'] : '');
        $allowed = ['Open', 'Replied', 'Closed', 'Published'];
        if (!in_array($status, $allowed, true)) {
            $status = 'Open';
        }

        $safeReply = mysqli_real_escape_string($connection, $reply);
        mysqli_query($connection, "UPDATE `tenant_notices` SET `status`='$status', `admin_reply`='$safeReply' WHERE `notice_id`='$noticeId'");
        api_json(['ok' => true]);
    }

    api_json(['ok' => false, 'message' => 'Unsupported action.'], 400);
}

$auth = api_require_auth();

if ($auth['isAdmin']) {
    $sql = "
        SELECT
            tn.*,
            t.`tenant_name`,
            t.`email`,
            h.`house_name`,
            hp.`partition_number`
        FROM `tenant_notices` tn
        LEFT JOIN `tenants` t ON tn.`tenant_id` = t.`tenantID`
        LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
        LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
        ORDER BY COALESCE(tn.`updated_at`, tn.`created_at`) DESC, tn.`notice_id` DESC
    ";

    $tenantRecipients = mysqli_query($connection, "
        SELECT
            t.`tenantID`,
            t.`tenant_name`,
            t.`email`,
            h.`house_name`,
            hp.`partition_number`
        FROM `tenants` t
        LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
        LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
        WHERE t.`tenant_status`='Active'
        ORDER BY t.`tenant_name` ASC, t.`tenantID` DESC
    ");
} else {
    $tenant = get_logged_in_tenant_record();
    if (!$tenant) {
        api_json(['ok' => false, 'message' => 'Tenant record not found.'], 404);
    }
    $tenantId = (int) $tenant['tenantID'];
    $sql = "SELECT * FROM `tenant_notices` WHERE `tenant_id`='$tenantId' ORDER BY COALESCE(`updated_at`, `created_at`) DESC, `notice_id` DESC";
    $tenantRecipients = false;
}

$result = mysqli_query($connection, $sql);
$items = [];
$recipients = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = [
            'notice_id' => (int) $row['notice_id'],
            'tenant_id' => (int) $row['tenant_id'],
            'tenant_name' => isset($row['tenant_name']) ? (string) $row['tenant_name'] : '',
            'email' => isset($row['email']) ? (string) $row['email'] : '',
            'house_name' => isset($row['house_name']) ? (string) $row['house_name'] : '',
            'partition_number' => isset($row['partition_number']) ? (string) $row['partition_number'] : '',
            'subject' => (string) $row['subject'],
            'message' => (string) $row['message'],
            'sender_role' => isset($row['sender_role']) ? (string) $row['sender_role'] : '',
            'created_by_name' => isset($row['created_by_name']) ? (string) $row['created_by_name'] : '',
            'status' => isset($row['status']) ? (string) $row['status'] : '',
            'admin_reply' => isset($row['admin_reply']) ? (string) $row['admin_reply'] : '',
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        ];
    }
}

if ($tenantRecipients) {
    while ($recipient = mysqli_fetch_assoc($tenantRecipients)) {
        $recipients[] = [
            'tenantID' => (int) $recipient['tenantID'],
            'tenant_name' => (string) $recipient['tenant_name'],
            'email' => (string) $recipient['email'],
            'house_name' => isset($recipient['house_name']) ? (string) $recipient['house_name'] : '',
            'partition_number' => isset($recipient['partition_number']) ? (string) $recipient['partition_number'] : '',
        ];
    }
}

api_json([
    'ok' => true,
    'canManage' => $auth['isAdmin'],
    'items' => $items,
    'recipients' => $recipients,
]);
