<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/house_photo_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

api_require_admin();
ensure_house_auto_increment_schema($connection);

$hname = is_username(isset($_POST['hname']) ? (string) $_POST['hname'] : '');
$numOfRooms = uncrack(isset($_POST['numOfRooms']) ? (string) $_POST['numOfRooms'] : '');
$numOfbRooms = uncrack(isset($_POST['numOfbRooms']) ? (string) $_POST['numOfbRooms'] : '');
$rent = uncrack(isset($_POST['rent']) ? (string) $_POST['rent'] : '');
$location = uncrack(isset($_POST['location']) ? (string) $_POST['location'] : '');
$status = uncrack(isset($_POST['status']) ? (string) $_POST['status'] : '');

if ($hname === '' || $numOfRooms === '' || $numOfbRooms === '' || $rent === '' || $location === '' || $status === '') {
    api_json(['ok' => false, 'message' => 'All required fields must be provided.'], 422);
}

$timesnap = date('Y-m-d : H:i:s');
$sq = "INSERT INTO `houses` (`house_name`,`number_of_rooms`,`rent_amount`,`location`,`num_of_bedrooms`,`house_status`) VALUES ('$hname','$numOfRooms','$rent','$location','$numOfbRooms','$status')";
$sqlTransactions = "INSERT INTO `transactions` (`actor`,`time`,`description`) VALUES ('Admin ($username)', '$timesnap','$username added a new house ($hname) with $numOfRooms rentable units, and $numOfbRooms bedrooms per unit located in $location')";

$mysqli->autocommit(false);
$state = true;
$newHouseId = null;

if ($mysqli->query($sq)) {
    $newHouseId = (int) $mysqli->insert_id;
    if ($newHouseId <= 0) {
        $idResult = mysqli_query($connection, "SELECT MAX(`houseID`) AS latest_house_id FROM `houses`");
        if ($idResult && ($idRow = mysqli_fetch_assoc($idResult))) {
            $newHouseId = (int) $idRow['latest_house_id'];
        }
    }
    if ($newHouseId <= 0) {
        $state = false;
    }
} else {
    $state = false;
}

$mysqli->query($sqlTransactions) ? null : $state = false;

if (!$state) {
    $mysqli->rollback();
    api_json(['ok' => false, 'message' => 'House could not be saved.'], 500);
}

$mysqli->commit();

$uploadedPhotos = 0;
if ($newHouseId > 0 && isset($_FILES['house_photos']) && !empty($_FILES['house_photos']['name'][0])) {
    $uploadedPhotos = upload_house_photos($connection, $newHouseId, 'House', $_FILES['house_photos']);
}

api_json(['ok' => true, 'houseID' => $newHouseId, 'uploadedPhotos' => $uploadedPhotos]);
