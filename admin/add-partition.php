<?php

    $pgnm='Nyumbani: Add partition photos';
    $error=' ';

    ob_start();
    require_once "functions/db.php";
    require_once "functions/errors.php";

    session_start();

    if(!isset($_SESSION['email']) || empty($_SESSION['email'])){
      header("location: login.php");
      exit;
    }

    if (is_logged_in_temporary()) {
        require_admin_user();

        $email = $_SESSION['email'];
        $houses = mysqli_query($connection, "SELECT * FROM `houses` ORDER BY `house_name` ASC");
        $partitionPhotos = mysqli_query($connection, "SELECT hp.*, h.house_name FROM `house_pics` hp LEFT JOIN `houses` h ON hp.house_id = h.houseID WHERE hp.pic_type='Partitions' ORDER BY hp.pic_id DESC");

        require "admin_header0.php";
        require "admin_left_panel.php";
?>

        <div id="page-wrapper">
            <div class="container-fluid">
                <div class="row bg-title">
                    <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                        <h4 class="page-title"><?php echo 'Hey there, '.$username;?></h4>
                    </div>
                    <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                        <ol class="breadcrumb">
                            <li><a href="index.php">Dashboard</a></li>
                            <li><a href="houses.php">Houses</a></li>
                            <li class="active">Add Partition</li>
                        </ol>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <?php
                            echo $error;

                            if (isset($_GET["photo_uploaded"])) {
                                echo '<div class="alert alert-success">
                                    <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                    <strong>UPLOADED!! </strong><p> Partition photo(s) uploaded successfully.</p>
                                </div>';
                            }
                            elseif (isset($_GET["photo_deleted"])) {
                                echo '<div class="alert alert-warning">
                                    <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                    <strong>DELETED!! </strong><p> The selected partition photo has been removed.</p>
                                </div>';
                            }
                            elseif (isset($_GET["photo_error"])) {
                                echo '<div class="alert alert-danger">
                                    <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                    <strong>ERROR!! </strong><p>The partition photo could not be processed. Use JPG, PNG, GIF, or WEBP images below 5MB.</p>
                                </div>';
                            }
                        ?>

                        <div class="white-box">
                            <h3 class="box-title m-b-0"><i class="fa fa-columns fa-3x"></i> Add Partition Photos</h3>
                            <p class="text-muted m-b-30 font-13">Select a house and upload photos for its partitions.</p>

                            <form action="functions/house_photo_manage.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="pic_type" value="Partitions">
                                <input type="hidden" name="return_to" value="add-partition.php">

                                <div class="form-group">
                                    <label for="house_id">House: *</label>
                                    <div class="input-group">
                                        <div class="input-group-addon"><i class="fa fa-building"></i></div>
                                        <select id="house_id" name="house_id" class="form-control" required>
                                            <option value="">** Select house **</option>
                                            <?php
                                                if ($houses && mysqli_num_rows($houses) > 0) {
                                                    while ($house = mysqli_fetch_array($houses, MYSQLI_BOTH)) {
                                                        echo '<option value="'.$house['houseID'].'">'.$house['house_name'].' - '.$house['location'].'</option>';
                                                    }
                                                }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="house_photos">Partition Photo(s): *</label>
                                    <div class="input-group">
                                        <div class="input-group-addon"><i class="fa fa-camera"></i></div>
                                        <input type="file" id="house_photos" name="house_photos[]" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                                    </div>
                                    <small class="text-muted">Allowed: JPG, PNG, GIF, WEBP. Max 5MB per image.</small>
                                </div>

                                <button type="submit" name="uploadHousePhoto" class="btn btn-success btn-lg waves-effect waves-light">
                                    <i class="fa fa-upload fa-lg"></i> Upload Partition Photo(s)
                                </button>
                            </form>
                        </div>

                        <div class="white-box">
                            <h3 class="box-title m-b-20">Uploaded Partition Photos</h3>

                            <div class="row">
                                <?php
                                    if (!$partitionPhotos || mysqli_num_rows($partitionPhotos) === 0) {
                                        echo '<div class="col-md-12"><i style="color:brown;">No partition photos uploaded yet.</i></div>';
                                    }
                                    else {
                                        while ($photo = mysqli_fetch_assoc($partitionPhotos)) {
                                            echo '
                                                <div class="col-md-3 col-sm-4" style="margin-bottom:20px;">
                                                    <div style="border:1px solid #e4e7ea; padding:10px; min-height:245px;">
                                                        <img src="'.$photo['pic_name'].'" alt="Partition photo" style="width:100%; height:150px; object-fit:cover; margin-bottom:10px;">
                                                        <h5 style="min-height:36px;">'.$photo['house_name'].'</h5>
                                                        <span class="label label-info">Partitions</span>
                                                        <form action="functions/house_photo_manage.php" method="post" style="margin-top:10px;">
                                                            <input type="hidden" name="pic_id" value="'.$photo['pic_id'].'">
                                                            <input type="hidden" name="return_to" value="add-partition.php">
                                                            <button type="submit" name="deleteHousePhoto" class="btn btn-danger btn-sm" onclick="return confirm(\'Delete this partition photo?\');">
                                                                <i class="fa fa-trash"></i> Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            ';
                                        }
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="right-sidebar">
                    <div class="slimscrollright">
                        <div class="rpanel-title"> Service Panel <span><i class="ti-close right-side-toggle"></i></span> </div>
                        <div class="r-panel-body">
                            <ul>
                                <li><b>Layout Options</b></li>
                                <li>
                                    <div class="checkbox checkbox-info">
                                        <input id="checkbox1" type="checkbox" class="fxhdr">
                                        <label for="checkbox1"> Fix Header </label>
                                    </div>
                                </li>
                                <li>
                                    <div class="checkbox checkbox-warning">
                                        <input id="checkbox2" type="checkbox" checked="" class="fxsdr">
                                        <label for="checkbox2"> Fix Sidebar </label>
                                    </div>
                                </li>
                                <li>
                                    <div class="checkbox checkbox-success">
                                        <input id="checkbox4" type="checkbox" class="open-close">
                                        <label for="checkbox4"> Toggle Sidebar </label>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php require "admin_footer.php"; ?>
            <script src="../plugins/bower_components/styleswitcher/jQuery.style.switcher.js"></script>
        </div>
    </div>
</body>
</html>
<?php
    }
    else{
        header('location:../index.php');
    }
?>
