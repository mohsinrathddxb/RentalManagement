<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/partition_helpers.php';
require_once __DIR__ . '/../../../admin/functions/house_photo_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

api_require_admin();
ensure_partition_tables($connection);

$houseId = isset($_POST['house_id']) ? (int) $_POST['house_id'] : 0;
$partitionNumber = isset($_POST['partition_number']) ? trim((string) $_POST['partition_number']) : '';
$rentAmount = isset($_POST['rent_amount']) ? (float) $_POST['rent_amount'] : -1;
$status = isset($_POST['partition_status']) ? trim((string) $_POST['partition_status']) : 'Vacant';
$description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
$facilities = isset($_POST['facilities']) ? normalize_partition_facilities($_POST['facilities']) : '';

if ($houseId <= 0 || $partitionNumber === '' || $rentAmount < 0) {
    api_json(['ok' => false, 'message' => 'House, partition name, and a valid rent amount are required.'], 422);
}

$allowedStatuses = ['Vacant', 'Occupied'];
$status = in_array($status, $allowedStatuses, true) ? $status : 'Vacant';

$statement = mysqli_prepare($connection, "
    INSERT INTO `house_partitions` (`house_id`, `partition_number`, `rent_amount`, `partition_status`, `description`, `facilities`)
    VALUES (?, ?, ?, ?, ?, ?)
");

if (!$statement) {
    api_json(['ok' => false, 'message' => 'Partition form could not be prepared.'], 500);
}

mysqli_stmt_bind_param($statement, 'isdsss', $houseId, $partitionNumber, $rentAmount, $status, $description, $facilities);

if (!mysqli_stmt_execute($statement)) {
    api_json(['ok' => false, 'message' => 'Partition could not be saved.'], 500);
}

$partitionId = (int) mysqli_insert_id($connection);
if ($partitionId <= 0) {
    $idResult = mysqli_query($connection, "SELECT MAX(`partition_id`) AS latest_partition_id FROM `house_partitions`");
    if ($idResult && ($idRow = mysqli_fetch_assoc($idResult))) {
        $partitionId = (int) $idRow['latest_partition_id'];
    }
}

$uploadedPhotos = 0;
if ($partitionId > 0 && isset($_FILES['house_photos']) && !empty($_FILES['house_photos']['name'][0])) {
    $uploadedPhotos = upload_house_photos($connection, $houseId, 'Partitions', $_FILES['house_photos'], $partitionId);
    if ($uploadedPhotos === 0) {
        api_json([
            'ok' => false,
            'message' => 'Partition saved, but the photo upload failed. Use JPG, PNG, GIF, or WEBP images below 5MB.',
        ], 422);
    }
}

api_json([
    'ok' => true,
    'partition_id' => $partitionId,
    'uploadedPhotos' => $uploadedPhotos,
]);
