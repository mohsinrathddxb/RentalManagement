<?php

ob_start();
session_start();
require_once "db.php";
require_once "house_photo_helpers.php";

if (!is_logged_in_temporary()) {
    header('Location:../login.php');
    exit();
}

require_admin_user();

function redirect_to_page($queryString) {
    $allowedPages = ['houses.php', 'add-partition.php'];
    $returnPage = isset($_POST['return_to']) && in_array($_POST['return_to'], $allowedPages, true) ? $_POST['return_to'] : 'houses.php';
    header('Location:../' . $returnPage . '?' . $queryString);
    exit();
}

ensure_pic_type_column($connection);

if (isset($_POST['uploadHousePhoto'])) {
    $houseId = filter_input(INPUT_POST, 'house_id', FILTER_VALIDATE_INT);
    $partitionId = filter_input(INPUT_POST, 'partition_id', FILTER_VALIDATE_INT);
    $picType = isset($_POST['pic_type']) ? normalize_house_pic_type($_POST['pic_type']) : 'Beds';

    if (!$houseId || empty($_FILES['house_photos']['name'][0])) {
        redirect_to_page('photo_error=missing');
    }

    $uploaded = upload_house_photos($connection, $houseId, $picType, $_FILES['house_photos'], $partitionId ?: null);

    if ($uploaded > 0) {
        redirect_to_page('photo_uploaded=' . $uploaded);
    }

    redirect_to_page('photo_error=invalid');
}

if (isset($_POST['deleteHousePhoto'])) {
    $picId = filter_input(INPUT_POST, 'pic_id', FILTER_VALIDATE_INT);

    if (!$picId) {
        redirect_to_page('photo_error=delete');
    }

    $statement = mysqli_prepare($connection, "SELECT `pic_name` FROM `house_pics` WHERE `pic_id` = ?");
    mysqli_stmt_bind_param($statement, 'i', $picId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $photo = mysqli_fetch_assoc($result);

    if ($photo) {
        $deleteStatement = mysqli_prepare($connection, "DELETE FROM `house_pics` WHERE `pic_id` = ?");
        mysqli_stmt_bind_param($deleteStatement, 'i', $picId);

        if (mysqli_stmt_execute($deleteStatement)) {
            $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $photo['pic_name']);
            if (is_file($filePath)) {
                @unlink($filePath);
            }
            redirect_to_page('photo_deleted=1');
        }
    }

    redirect_to_page('photo_error=delete');
}

redirect_to_page('photo_error=unknown');
