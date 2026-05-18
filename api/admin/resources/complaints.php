<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = api_require_auth();
    $input = api_get_json_input();
    $action = isset($input['action']) ? trim((string) $input['action']) : '';

    if ($action === 'create') {
        if ($auth['isAdmin']) {
            api_json(['ok' => false, 'message' => 'Only tenants can create complaints here.'], 403);
        }

        $tenant = get_logged_in_tenant_record();
        if (!$tenant) {
            api_json(['ok' => false, 'message' => 'Tenant record not found.'], 404);
        }

        $title = uncrack(isset($input['title']) ? (string) $input['title'] : '');
        $description = uncrack(isset($input['description']) ? (string) $input['description'] : '');

        if ($title === '' || $description === '') {
            api_json(['ok' => false, 'message' => 'Title and description are required.'], 422);
        }

        $tenantId = (int) $tenant['tenantID'];
        $houseId = isset($tenant['houseNumber']) ? (int) $tenant['houseNumber'] : 0;
        $partitionId = isset($tenant['partition_id']) ? (int) $tenant['partition_id'] : 0;
        $safeTitle = mysqli_real_escape_string($connection, $title);
        $safeDescription = mysqli_real_escape_string($connection, $description);

        mysqli_query(
            $connection,
            "INSERT INTO `tenant_complaints` (`tenant_id`, `house_id`, `partition_id`, `title`, `description`, `image_path`, `status`) VALUES ('$tenantId', '$houseId', '$partitionId', '$safeTitle', '$safeDescription', '', 'Open')"
        );

        api_json(['ok' => true]);
    }

    if ($action === 'update') {
        if (!$auth['isAdmin']) {
            api_json(['ok' => false, 'message' => 'Admin access required.'], 403);
        }

        $complaintId = (int) ($input['complaint_id'] ?? 0);
        $status = uncrack(isset($input['status']) ? (string) $input['status'] : 'Open');
        $reason = uncrack(isset($input['admin_reason']) ? (string) $input['admin_reason'] : '');
        $allowedStatuses = ['Open', 'In Progress', 'Resolved', 'Rejected'];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'Open';
        }

        $safeReason = mysqli_real_escape_string($connection, $reason);
        mysqli_query($connection, "UPDATE `tenant_complaints` SET `status`='$status', `admin_reason`='$safeReason' WHERE `complaint_id`='$complaintId'");
        api_json(['ok' => true]);
    }

    if ($action === 'reopen') {
        if ($auth['isAdmin']) {
            api_json(['ok' => false, 'message' => 'Only tenants can reopen complaints here.'], 403);
        }

        $tenant = get_logged_in_tenant_record();
        if (!$tenant) {
            api_json(['ok' => false, 'message' => 'Tenant record not found.'], 404);
        }

        $complaintId = (int) ($input['complaint_id'] ?? 0);
        $tenantId = (int) $tenant['tenantID'];
        mysqli_query(
            $connection,
            "UPDATE `tenant_complaints` SET `status`='Reopened', `reopened_count`=`reopened_count`+1, `admin_reason`=NULL WHERE `complaint_id`='$complaintId' AND `tenant_id`='$tenantId'"
        );
        api_json(['ok' => true]);
    }

    api_json(['ok' => false, 'message' => 'Unsupported action.'], 400);
}

$auth = api_require_auth();

if ($auth['isAdmin']) {
    $sql = "
        SELECT tc.*, t.`tenant_name`, t.`email`, h.`house_name`, hp.`partition_number`
        FROM `tenant_complaints` tc
        LEFT JOIN `tenants` t ON tc.`tenant_id` = t.`tenantID`
        LEFT JOIN `houses` h ON tc.`house_id` = h.`houseID`
        LEFT JOIN `house_partitions` hp ON tc.`partition_id` = hp.`partition_id`
        ORDER BY
            CASE
                WHEN LOWER(tc.`status`) IN ('open', 'reopened') THEN 0
                WHEN LOWER(tc.`status`) = 'in progress' THEN 1
                WHEN LOWER(tc.`status`) = 'resolved' THEN 2
                WHEN LOWER(tc.`status`) = 'rejected' THEN 3
                ELSE 4
            END ASC,
            tc.`updated_at` DESC
    ";
} else {
    $tenant = get_logged_in_tenant_record();
    if (!$tenant) {
        api_json(['ok' => false, 'message' => 'Tenant record not found.'], 404);
    }

    $tenantId = (int) $tenant['tenantID'];
    $sql = "
        SELECT tc.*, h.`house_name`, hp.`partition_number`
        FROM `tenant_complaints` tc
        LEFT JOIN `houses` h ON tc.`house_id` = h.`houseID`
        LEFT JOIN `house_partitions` hp ON tc.`partition_id` = hp.`partition_id`
        WHERE tc.`tenant_id`='$tenantId'
        ORDER BY
            CASE
                WHEN LOWER(tc.`status`) IN ('open', 'reopened') THEN 0
                WHEN LOWER(tc.`status`) = 'in progress' THEN 1
                WHEN LOWER(tc.`status`) = 'resolved' THEN 2
                WHEN LOWER(tc.`status`) = 'rejected' THEN 3
                ELSE 4
            END ASC,
            tc.`updated_at` DESC
    ";
}

$result = mysqli_query($connection, $sql);
$items = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = [
            'complaint_id' => (int) $row['complaint_id'],
            'tenant_id' => (int) $row['tenant_id'],
            'tenant_name' => isset($row['tenant_name']) ? (string) $row['tenant_name'] : '',
            'email' => isset($row['email']) ? (string) $row['email'] : '',
            'house_name' => isset($row['house_name']) ? (string) $row['house_name'] : '',
            'partition_number' => isset($row['partition_number']) ? (string) $row['partition_number'] : '',
            'title' => (string) $row['title'],
            'description' => (string) $row['description'],
            'status' => (string) $row['status'],
            'admin_reason' => isset($row['admin_reason']) ? (string) $row['admin_reason'] : '',
            'reopened_count' => isset($row['reopened_count']) ? (int) $row['reopened_count'] : 0,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
        ];
    }
}

api_json([
    'ok' => true,
    'canManage' => $auth['isAdmin'],
    'items' => $items,
]);
