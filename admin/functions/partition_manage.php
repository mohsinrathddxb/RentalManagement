<?php

ob_start();
session_start();
require_once "db.php";
require_once "partition_helpers.php";
require_once "house_photo_helpers.php";

if (!is_logged_in_temporary()) {
    header('Location:../login.php');
    exit();
}

require_admin_user();
ensure_partition_tables($connection);

function redirect_partition($queryString) {
    $allowedPages = ['houses.php', 'add-partition.php'];
    $returnPage = isset($_POST['return_to']) && in_array($_POST['return_to'], $allowedPages, true) ? $_POST['return_to'] : 'houses.php';
    header('Location:../' . $returnPage . '?' . $queryString);
    exit();
}

if (isset($_POST['addPartition'])) {
    $houseId = filter_input(INPUT_POST, 'house_id', FILTER_VALIDATE_INT);
    $partitionNumber = isset($_POST['partition_number']) ? trim($_POST['partition_number']) : '';
    $rentAmount = filter_input(INPUT_POST, 'rent_amount', FILTER_VALIDATE_FLOAT);
    $status = isset($_POST['partition_status']) ? trim($_POST['partition_status']) : 'Vacant';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $facilities = isset($_POST['facilities']) ? normalize_partition_facilities($_POST['facilities']) : '';

    if (!$houseId || $partitionNumber === '' || $rentAmount === false || $rentAmount < 0) {
        redirect_partition('partition_error=invalid');
    }

    $allowedStatuses = ['Vacant', 'Occupied'];
    $status = in_array($status, $allowedStatuses, true) ? $status : 'Vacant';

    $statement = mysqli_prepare($connection, "
        INSERT INTO `house_partitions` (`house_id`, `partition_number`, `rent_amount`, `partition_status`, `description`, `facilities`)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($statement, 'isdsss', $houseId, $partitionNumber, $rentAmount, $status, $description, $facilities);

    if (mysqli_stmt_execute($statement)) {
        $partitionId = mysqli_insert_id($connection);

        if (!empty($_FILES['house_photos']['name'][0])) {
            $uploaded = upload_house_photos($connection, $houseId, 'Partitions', $_FILES['house_photos'], $partitionId);
            if ($uploaded === 0) {
                redirect_partition('partition_added=1&photo_error=invalid');
            }
        }

        redirect_partition('partition_added=1');
    }

    redirect_partition('partition_error=save');
}

if (isset($_POST['editPartition'])) {
    $partitionId = filter_input(INPUT_POST, 'partition_id', FILTER_VALIDATE_INT);
    $partitionNumber = isset($_POST['partition_number']) ? trim($_POST['partition_number']) : '';
    $rentAmount = filter_input(INPUT_POST, 'rent_amount', FILTER_VALIDATE_FLOAT);
    $status = isset($_POST['partition_status']) ? trim($_POST['partition_status']) : 'Vacant';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $facilities = isset($_POST['facilities']) ? normalize_partition_facilities($_POST['facilities']) : '';

    if (!$partitionId || $partitionNumber === '' || $rentAmount === false || $rentAmount < 0) {
        redirect_partition('partition_error=invalid');
    }

    $allowedStatuses = ['Vacant', 'Occupied'];
    $status = in_array($status, $allowedStatuses, true) ? $status : 'Vacant';

    $statement = mysqli_prepare($connection, "
        UPDATE `house_partitions`
        SET `partition_number` = ?, `rent_amount` = ?, `partition_status` = ?, `description` = ?, `facilities` = ?
        WHERE `partition_id` = ?
    ");
    mysqli_stmt_bind_param($statement, 'sdsssi', $partitionNumber, $rentAmount, $status, $description, $facilities, $partitionId);

    if (mysqli_stmt_execute($statement)) {
        redirect_partition('partition_updated=1');
    }

    redirect_partition('partition_error=update');
}

if (isset($_POST['deletePartition'])) {
    $partitionId = filter_input(INPUT_POST, 'partition_id', FILTER_VALIDATE_INT);

    if (!$partitionId) {
        redirect_partition('partition_error=delete');
    }

    $photos = mysqli_prepare($connection, "SELECT `pic_name` FROM `house_pics` WHERE `partition_id` = ?");
    mysqli_stmt_bind_param($photos, 'i', $partitionId);
    mysqli_stmt_execute($photos);
    $photoResult = mysqli_stmt_get_result($photos);

    $deletePhotos = mysqli_prepare($connection, "DELETE FROM `house_pics` WHERE `partition_id` = ?");
    mysqli_stmt_bind_param($deletePhotos, 'i', $partitionId);
    mysqli_stmt_execute($deletePhotos);

    while ($photo = mysqli_fetch_assoc($photoResult)) {
        $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $photo['pic_name']);
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    $statement = mysqli_prepare($connection, "DELETE FROM `house_partitions` WHERE `partition_id` = ?");
    mysqli_stmt_bind_param($statement, 'i', $partitionId);

    if (mysqli_stmt_execute($statement)) {
        redirect_partition('partition_deleted=1');
    }

    redirect_partition('partition_error=delete');
}

redirect_partition('partition_error=unknown');

?>
