<?php

$pgnm = 'Co- Accomodation: Add a new house';
$error = ' ';

ob_start();

require_once "functions/db.php";
require_once "functions/house_photo_helpers.php";
require_once "functions/errors.php";

session_start();

if (is_logged_in_temporary()) {
    require_admin_user();
    ensure_house_auto_increment_schema($connection);

    if (isset($_POST['submit'])) {
        $hname = is_username($_POST['hname']);
        $numOfRooms = uncrack($_POST['numOfRooms']);
        $numOfbRooms = uncrack($_POST['numOfbRooms']);
        $rent = uncrack($_POST['rent']);
        $location = uncrack($_POST['location']);
        $status = uncrack($_POST['status']);

        $timesnap = date('Y-m-d : H:i:s');

        $sq = "INSERT INTO `houses`
            (`house_name`,`number_of_rooms`,`rent_amount`,`location`,`num_of_bedrooms`,`house_status`)
            VALUES
            ('$hname','$numOfRooms','$rent','$location','$numOfbRooms','$status')";

        $sql_transactions = "INSERT INTO `transactions` (`actor`,`time`,`description`)
            VALUES ('Admin ($username)', '$timesnap','$username added a new house ($hname) with $numOfRooms rentable units, and $numOfbRooms bedrooms per unit located in $location')";

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

        $mysqli->query($sql_transactions) ? null : $state = false;

        if ($state) {
            $mysqli->commit();

            $uploadedPhotos = 0;
            if ($newHouseId > 0 && !empty($_FILES['house_photos']['name'][0])) {
                $uploadedPhotos = upload_house_photos($connection, $newHouseId, 'House', $_FILES['house_photos']);
            }

            if (!empty($_FILES['house_photos']['name'][0]) && $uploadedPhotos === 0) {
                header('location:houses.php?state=1&house_photo_error=1');
                exit;
            }

            header('location:houses.php?state=1&house_photos=' . $uploadedPhotos);
            exit;
        } else {
            $mysqli->rollback();
            header('location:new-house.php?state=2');
            exit;
        }
    }

    require "admin_header0.php";
    require "admin_left_panel.php";
    ?>

    <div id="page-wrapper">
        <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title"><?php echo 'Hey there, ' . $username; ?></h4>
                </div>
                <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                    <ol class="breadcrumb">
                        <li><a href="index.php">Dashboard</a></li>
                        <li><a href="houses.php">Houses</a></li>
                        <li class="active">New</li>
                    </ol>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div>
                        <?php echo $error; ?>
                    </div>
                    <style>
                        .new-house-page .white-box {
                            padding: 24px;
                        }
                        .new-house-form-wrap {
                            width: 75%;
                            max-width: 1100px;
                            margin: 0;
                        }
                        .new-house-form-wrap .form-control {
                            height: 48px;
                            min-height: 48px;
                            font-size: 15px;
                        }
                        .new-house-form-wrap .input-group-addon {
                            min-width: 40px;
                            height: 48px;
                            vertical-align: middle;
                        }
                        .new-house-footer-wrap {
                            clear: both;
                            width: 100%;
                            display: block;
                        }
                        .new-house-footer-spacer {
                            clear: both;
                            width: 100%;
                            height: 24px;
                        }
                        .footer {
                            left: 0 !important;
                        }
                        @media (max-width: 991px) {
                            .new-house-form-wrap {
                                width: 100%;
                            }
                        }
                        @media (max-width: 767px) {
                            .new-house-page .white-box {
                                padding: 16px 14px;
                            }
                            .new-house-page .box-title {
                                line-height: 1.35;
                            }
                            .new-house-form-wrap .btn {
                                margin-bottom: 8px;
                            }
                        }
                    </style>
                    <div class="white-box new-house-page">
                        <h3 class="box-title m-b-0"><i class="fa fa-institution fa-3x"></i> Add A New House</h3>
                        <p class="text-muted m-b-30 font-13">Fill in the form below:</p>
                        <div class="row">
                            <div class="col-sm-12 col-xs-12">
                                <div class="new-house-form-wrap">
                                <form action="new-house.php" method="post" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="hname">House Name: *</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-pencil"></i></div>
                                            <input type="text" name="hname" class="form-control" id="hname" placeholder="Enter house name" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="numOfRooms">Number of rooms (rentable units): *</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-pencil"></i></div>
                                            <input type="number" min="1" name="numOfRooms" class="form-control" id="numOfRooms" placeholder="How many rooms?" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="numOfbRooms">Number of bedrooms (per unit): *</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-bed"></i></div>
                                            <input type="number" min="0" name="numOfbRooms" class="form-control" id="numOfbRooms" placeholder="Bedrooms. e.g. 0" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="rent">Rent amount (PM): *</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-usd"></i></div>
                                            <input type="number" min="0" name="rent" class="form-control" id="rent" placeholder="Rent per month. e.g. 3000" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="location">Location: *</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-map-marker"></i></div>
                                            <input type="text" name="location" class="form-control" id="location" placeholder="Enter location" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="status">House Status: *</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-home"></i></div>
                                            <select name="status" class="form-control" id="status" required>
                                                <option value="Vacant">Vacant</option>
                                                <option value="Occupied">Occupied</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="house_photos">House Photo(s):</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-camera"></i></div>
                                            <input type="file" name="house_photos[]" class="form-control" id="house_photos" accept=".jpg,.jpeg,.png,.gif,.webp" multiple>
                                        </div>
                                        <small class="text-muted">JPG, PNG, GIF, WEBP. Max 5MB each.</small>
                                    </div>

                                    <button type="submit" name="submit" class="btn btn-success waves-effect waves-light m-r-10">Save House</button>
                                    <button type="reset" class="btn btn-inverse waves-effect waves-light">Reset</button>
                                </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="new-house-footer-spacer"></div>
            <?php require "admin_footer.php"; ?>

    <?php
} else {
    header('location:login.php');
    exit;
}
?>
