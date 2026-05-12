<?php

    $pgnm='Co- Accomodation: Add partition photos';
    $error=' ';

    ob_start();
    require_once "functions/db.php";
    require_once "functions/errors.php";
    require_once "functions/partition_helpers.php";
    require_once "functions/tenant_helpers.php";
    require_once "functions/house_photo_helpers.php";

    session_start();

    if(!isset($_SESSION['email']) || empty($_SESSION['email'])){
      header("location: login.php");
      exit;
    }

    if (is_logged_in_temporary()) {
        $canManagePartitions = is_admin_user();
        ensure_tenant_schema($connection);
        $currentTenant = get_logged_in_tenant_record();
        ensure_partition_tables($connection);
        ensure_pic_type_column($connection);

        $email = $_SESSION['email'];
        $houses = mysqli_query($connection, "SELECT * FROM `houses` ORDER BY `house_name` ASC");
        if ($canManagePartitions) {
            $partitions = mysqli_query($connection, "SELECT hp.*, h.house_name, h.location FROM `house_partitions` hp LEFT JOIN `houses` h ON hp.house_id = h.houseID ORDER BY hp.partition_id DESC");
        } else {
            $tenantPartitionId = $currentTenant && isset($currentTenant['partition_id']) ? (int) $currentTenant['partition_id'] : 0;
            $partitions = mysqli_query($connection, "
                SELECT hp.*, h.house_name, h.location
                FROM `house_partitions` hp
                LEFT JOIN `houses` h ON hp.house_id = h.houseID
                WHERE hp.`partition_status`='Vacant' ".($tenantPartitionId > 0 ? "OR hp.`partition_id`='$tenantPartitionId'" : '')."
                ORDER BY hp.partition_id DESC
            ");
        }

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
                            elseif (isset($_GET["partition_added"])) {
                                echo '<div class="alert alert-success">
                                    <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                    <strong>DONE!! </strong><p>The partition and rent amount have been saved.</p>
                                </div>';
                            }
                            elseif (isset($_GET["partition_updated"])) {
                                echo '<div class="alert alert-success">
                                    <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                    <strong>UPDATED!! </strong><p>The partition rent/details have been updated.</p>
                                </div>';
                            }
                            elseif (isset($_GET["partition_deleted"])) {
                                echo '<div class="alert alert-warning">
                                    <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                    <strong>DELETED!! </strong><p>The partition has been deleted.</p>
                                </div>';
                            }
                            elseif (isset($_GET["partition_error"])) {
                                echo '<div class="alert alert-danger">
                                    <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                    <strong>ERROR!! </strong><p>The partition could not be processed. Please check the partition number and rent amount.</p>
                                </div>';
                            }
                        ?>

                        <?php if ($canManagePartitions) { ?>
                        <div class="white-box">
                            <h3 class="box-title m-b-0"><i class="fa fa-columns fa-3x"></i> Add Partition</h3>
                            <p class="text-muted m-b-30 font-13">Select a house, add the partition rent amount, and upload optional photos.</p>

                            <form action="functions/partition_manage.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="return_to" value="add-partition.php">

                                <div class="row">
                                    <div class="form-group col-md-6">
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

                                    <div class="form-group col-md-3">
                                        <label for="partition_number">Partition No./Name: *</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-columns"></i></div>
                                            <input type="text" id="partition_number" name="partition_number" class="form-control" placeholder="e.g. P1 or Room A" required>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label for="rent_amount">Rent Amount: *</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-money"></i></div>
                                            <input type="number" min="0" step="0.01" id="rent_amount" name="rent_amount" class="form-control" placeholder="e.g. 2500" required>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label for="partition_status">Status:</label>
                                        <select id="partition_status" name="partition_status" class="form-control">
                                            <option value="Vacant">Vacant</option>
                                            <option value="Occupied">Occupied</option>
                                        </select>
                                    </div>

                                    <div class="form-group col-md-8">
                                        <label for="description">Description:</label>
                                        <input type="text" id="description" name="description" class="form-control" placeholder="Optional note">
                                    </div>

                                    <div class="form-group col-md-12">
                                        <label>Facilities:</label>
                                        <div style="border:1px solid #e4e7ea; padding:12px;">
                                            <?php echo render_partition_facility_checkboxes(); ?>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-12">
                                        <label for="house_photos">Partition Photo(s):</label>
                                        <div class="input-group">
                                            <div class="input-group-addon"><i class="fa fa-camera"></i></div>
                                            <input type="file" id="house_photos" name="house_photos[]" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                                        </div>
                                        <small class="text-muted">Optional. Allowed: JPG, PNG, GIF, WEBP. Max 5MB per image.</small>
                                    </div>
                                </div>

                                <button type="submit" name="addPartition" class="btn btn-success btn-lg waves-effect waves-light">
                                    <i class="fa fa-plus fa-lg"></i> Add Partition
                                </button>
                            </form>
                        </div>
                        <?php } ?>

                        <div class="white-box">
                            <h3 class="box-title m-b-20">House Partitions</h3>

                            <div class="row">
                                <?php
                                    if (!$partitions || mysqli_num_rows($partitions) === 0) {
                                        echo '<div class="col-md-12"><i style="color:brown;">No partitions added yet.</i></div>';
                                    }
                                    else {
                                        while ($partition = mysqli_fetch_assoc($partitions)) {
                                            $partitionId = (int) $partition['partition_id'];
                                            $partitionNumber = htmlspecialchars($partition['partition_number'], ENT_QUOTES, 'UTF-8');
                                            $rentAmount = htmlspecialchars($partition['rent_amount'], ENT_QUOTES, 'UTF-8');
                                            $partitionStatus = htmlspecialchars($partition['partition_status'], ENT_QUOTES, 'UTF-8');
                                            $description = htmlspecialchars((string) $partition['description'], ENT_QUOTES, 'UTF-8');
                                            $facilities = isset($partition['facilities']) ? $partition['facilities'] : '';
                                            $houseName = htmlspecialchars($partition['house_name'], ENT_QUOTES, 'UTF-8');
                                            $location = htmlspecialchars($partition['location'], ENT_QUOTES, 'UTF-8');
                                            $photos = mysqli_query($connection, "SELECT * FROM `house_pics` WHERE `partition_id`='$partitionId' ORDER BY `pic_id` DESC");
                                            $photoHtml = '';

                                            if ($photos && mysqli_num_rows($photos) > 0) {
                                                while ($photo = mysqli_fetch_assoc($photos)) {
                                                    $picPath = htmlspecialchars($photo['pic_name'], ENT_QUOTES, 'UTF-8');
                                                    $picId = (int) $photo['pic_id'];
                                                    $deletePhoto = $canManagePartitions ? '
                                                        <form action="functions/house_photo_manage.php" method="post" style="margin-top:8px;">
                                                            <input type="hidden" name="pic_id" value="'.$picId.'">
                                                            <input type="hidden" name="return_to" value="add-partition.php">
                                                            <button type="submit" name="deleteHousePhoto" class="btn btn-danger btn-xs" onclick="return confirm(\'Delete this partition photo?\');">
                                                                <i class="fa fa-trash"></i> Delete
                                                            </button>
                                                        </form>
                                                    ' : '';
                                                    $photoHtml .= '
                                                        <div class="col-md-3 col-sm-4" style="margin-bottom:15px;">
                                                            <div style="border:1px solid #e4e7ea; padding:8px;">
                                                                <a href="#" class="js-photo-preview" data-photo-src="'.$picPath.'" data-photo-title="'.$partitionNumber.' photo">
                                                                    <img src="'.$picPath.'" alt="'.$partitionNumber.' photo" style="width:100%; height:130px; object-fit:cover; cursor:pointer;">
                                                                </a>
                                                                '.$deletePhoto.'
                                                            </div>
                                                        </div>
                                                    ';
                                                }
                                            } else {
                                                $photoHtml = '<div class="col-md-12"><i style="color:brown;">No photos uploaded for this partition yet.</i></div>';
                                            }

                                            $adminControls = $canManagePartitions ? '
                                                <div class="m-t-10">
                                                    <button type="button" class="btn btn-info btn-sm" data-toggle="collapse" data-target="#partition-edit-'.$partitionId.'">
                                                        <i class="fa fa-edit"></i> Edit Rent / Details
                                                    </button>
                                                    <form action="functions/partition_manage.php" method="post" style="display:inline-block; margin-left:5px;">
                                                        <input type="hidden" name="partition_id" value="'.$partitionId.'">
                                                        <input type="hidden" name="return_to" value="add-partition.php">
                                                        <button type="submit" name="deletePartition" class="btn btn-danger btn-sm" onclick="return confirm(\'Delete this partition and its photos?\');">
                                                            <i class="fa fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                                <div id="partition-edit-'.$partitionId.'" class="collapse" style="margin-top:15px;">
                                                    <form action="functions/partition_manage.php" method="post">
                                                        <input type="hidden" name="partition_id" value="'.$partitionId.'">
                                                        <input type="hidden" name="return_to" value="add-partition.php">
                                                        <div class="row">
                                                            <div class="form-group col-md-3">
                                                                <label>Partition No./Name</label>
                                                                <input type="text" name="partition_number" class="form-control" value="'.$partitionNumber.'" required>
                                                            </div>
                                                            <div class="form-group col-md-3">
                                                                <label>Rent Amount</label>
                                                                <input type="number" min="0" step="0.01" name="rent_amount" class="form-control" value="'.$rentAmount.'" required>
                                                            </div>
                                                            <div class="form-group col-md-3">
                                                                <label>Status</label>
                                                                <select name="partition_status" class="form-control">
                                                                    <option value="'.$partitionStatus.'" selected>'.$partitionStatus.'</option>
                                                                    <option value="Vacant">Vacant</option>
                                                                    <option value="Occupied">Occupied</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group col-md-3">
                                                                <label>Description</label>
                                                                <input type="text" name="description" class="form-control" value="'.$description.'">
                                                            </div>
                                                            <div class="form-group col-md-12">
                                                                <label>Facilities</label>
                                                                <div style="border:1px solid #e4e7ea; padding:12px;">
                                                                    '.render_partition_facility_checkboxes($facilities).'
                                                                </div>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <button type="submit" name="editPartition" class="btn btn-success btn-sm">
                                                                    <i class="fa fa-save"></i> Update Partition
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                    <form action="functions/house_photo_manage.php" method="post" enctype="multipart/form-data" style="margin-top:12px;">
                                                        <input type="hidden" name="house_id" value="'.$partition['house_id'].'">
                                                        <input type="hidden" name="partition_id" value="'.$partitionId.'">
                                                        <input type="hidden" name="pic_type" value="Partitions">
                                                        <input type="hidden" name="return_to" value="add-partition.php">
                                                        <div class="row">
                                                            <div class="form-group col-md-8">
                                                                <input type="file" name="house_photos[]" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                                                            </div>
                                                            <div class="form-group col-md-4">
                                                                <button type="submit" name="uploadHousePhoto" class="btn btn-success btn-sm">
                                                                    <i class="fa fa-upload"></i> Upload Photo(s)
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            ' : '';

                                            echo '
                                                <div class="col-md-12" style="margin-bottom:20px;">
                                                    <div style="border:1px solid #e4e7ea; padding:15px;">
                                                        <h4>'.$partitionNumber.' <small>'.$houseName.' - '.$location.'</small></h4>
                                                        <p><strong>Rent Amount:</strong> '.$rentAmount.'</p>
                                                        <p><strong>Status:</strong> '.$partitionStatus.'</p>
                                                        '.($description !== '' ? '<p><strong>Description:</strong> '.$description.'</p>' : '').'
                                                        <p><strong>Facilities:</strong><br>'.render_partition_facilities_badges($facilities).'</p>
                                                        '.$adminControls.'
                                                        <hr>
                                                        <h5>Partition Photos</h5>
                                                        <div class="row">'.$photoHtml.'</div>
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
            <div id="photo-preview-modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                            <h4 class="modal-title" id="photo-preview-title">Photo Preview</h4>
                        </div>
                        <div class="modal-body text-center">
                            <img id="photo-preview-image" src="" alt="Photo preview" style="max-width:100%; max-height:75vh; object-fit:contain;">
                        </div>
                    </div>
                </div>
            </div>
            <script>
                $(document).on('click', '.js-photo-preview', function(event) {
                    event.preventDefault();
                    var photoSrc = $(this).data('photo-src');
                    var photoTitle = $(this).data('photo-title') || 'Photo Preview';

                    $('#photo-preview-title').text(photoTitle);
                    $('#photo-preview-image').attr('src', photoSrc);
                    $('#photo-preview-modal').modal('show');
                });

                $('#photo-preview-modal').on('hidden.bs.modal', function() {
                    $('#photo-preview-image').attr('src', '');
                });
            </script>
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
