<?php
    
    $pgnm="Co- Accomodation : View Houses";
    $error=' ';

    //require the global file for errors
    require_once "functions/errors.php";
    
    ob_start();
    require_once "functions/db.php";
    require_once "functions/tenant_helpers.php";
    require_once "functions/partition_helpers.php";
    require_once "functions/house_photo_helpers.php";
    require_once "functions/ui_column_preferences.php";

    // Initialize the session

    session_start();

    // If session variable is not set it will redirect to login page

    if(!isset($_SESSION['email']) || empty($_SESSION['email'])){

      header("location: login.php");

      exit;
    }
    if (is_logged_in_temporary()) {
        #allow access
    $canManageHouses = is_admin_user();
    ensure_tenant_schema($connection);
    ensure_partition_tables($connection);
    ensure_pic_type_column($connection);
    ensure_ui_column_preferences_schema($connection);
    $currentTenant = get_logged_in_tenant_record();
    

    $email = $_SESSION['email'];

    $sql = "
        SELECT
            h.*,
            COALESCE(SUM(CASE WHEN LOWER(hp.`partition_status`) = 'vacant' THEN 1 ELSE 0 END), 0) AS `available_partition_count`,
            COALESCE(COUNT(hp.`partition_id`), 0) AS `partition_count`
        FROM `houses` h
        LEFT JOIN `house_partitions` hp ON hp.`house_id` = h.`houseID`
        GROUP BY h.`houseID`
        ORDER BY
            CASE
                WHEN LOWER(h.`house_status`) = 'vacant' THEN 0
                WHEN COALESCE(SUM(CASE WHEN LOWER(hp.`partition_status`) = 'vacant' THEN 1 ELSE 0 END), 0) > 0 THEN 1
                ELSE 2
            END ASC,
            `available_partition_count` DESC,
            h.`house_name` ASC
    ";
    $query = mysqli_query($connection, $sql);
    $savedHouseColumns = get_ui_visible_columns($connection, $_SESSION['email'], 'houses_view');
    $savedHouseColumnsJson = json_encode(is_array($savedHouseColumns) ? $savedHouseColumns : []);
    $houseModalMarkup = [];
    
    /*******************************************************
                    introduce the admin header
    *******************************************************/
    require "admin_header0.php";

    /*******************************************************
                    Add the left panel
    *******************************************************/
    require "admin_left_panel.php";
?>

    

        <!-- Page Content -->
        <div id="page-wrapper">
            <div class="container-fluid">
                <div class="row bg-title">
                    <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                        <h4 class="page-title"><?php echo $username;?></h4> </div>
                    <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12"> 
                        <ol class="breadcrumb">
                            <li><a href="index.php">Dashboard</a></li>
                            <li><a href="#" class="active">Houses</a></li>
                            <li><a href="new-house.php">New</a></li>
                            
                        </ol>
                    </div>
                    <!-- /.col-lg-12 -->
                </div>
                <!-- /row -->
                <div class="row">
                   
                    
                    <div class="col-sm-12">
                        <div class="white-box">

                        		<?php
                                    echo $error;
                                    
									if (isset($_GET["success"])) {
										echo 
										'<div class="alert alert-success" >
					                          <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
					                         <strong>DONE!! </strong><p> The new house has been added successfully.</p>
					                    </div>'
										;
									}
                                    elseif (isset($_GET["deleted"])) {
                                        echo 
                                        '<div class="alert alert-warning" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>DELETED!! </strong><p> The house has been successfully deleted.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["del_error"])) {
                                        echo
                                        '<div class="alert alert-danger" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>ERROR!! </strong><p> There was an error during deleting this record. Please try again.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["photo_uploaded"])) {
                                        echo
                                        '<div class="alert alert-success" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>UPLOADED!! </strong><p> House photos have been uploaded successfully.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["photo_deleted"])) {
                                        echo
                                        '<div class="alert alert-warning" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>DELETED!! </strong><p> The selected house photo has been removed.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["photo_error"])) {
                                        echo
                                        '<div class="alert alert-danger" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>ERROR!! </strong><p> The photo could not be processed. Use JPG, PNG, GIF, or WEBP images below 5MB.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["house_photos"])) {
                                        echo 
                                        '<div class="alert alert-success" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>DONE!! </strong><p> The new house has been added and house photo(s) have been saved.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["house_photo_error"])) {
                                        echo 
                                        '<div class="alert alert-warning" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>PARTIAL SAVE!! </strong><p> The new house was added, but the selected house image could not be processed.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["partition_added"])) {
                                        echo
                                        '<div class="alert alert-success" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>DONE!! </strong><p> The partition has been added successfully.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["partition_updated"])) {
                                        echo
                                        '<div class="alert alert-success" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>UPDATED!! </strong><p> The partition rent/details have been updated.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["partition_deleted"])) {
                                        echo
                                        '<div class="alert alert-warning" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>DELETED!! </strong><p> The partition has been deleted.</p>
                                        </div>'
                                        ;
                                    }
                                    elseif (isset($_GET["partition_error"])) {
                                        echo
                                        '<div class="alert alert-danger" >
                                              <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                                             <strong>ERROR!! </strong><p> The partition could not be processed. Please check the partition number and rent amount.</p>
                                        </div>'
                                        ;
                                    }
                                    
								?>	

                            <style>
                                [id^="responsive-modal_edit"] .modal-dialog {
                                    width: min(960px, 92vw);
                                }
                                [id^="responsive-modal_edit"] .modal-footer {
                                    display: block;
                                    text-align: left;
                                    padding: 24px;
                                }
                                [id^="responsive-modal_edit"] .modal-footer form {
                                    display: block;
                                    width: 100%;
                                }
                                [id^="responsive-modal_edit"] .modal-footer form::after,
                                [id^="responsive-modal_edit"] .modal-footer .row::after {
                                    content: "";
                                    display: block;
                                    clear: both;
                                }
                                [id^="responsive-modal_edit"] .modal-footer > hr,
                                [id^="responsive-modal_edit"] .modal-footer > h4,
                                [id^="responsive-modal_edit"] .modal-footer > .row {
                                    clear: both;
                                }
                                [id^="responsive-modal_edit"] .modal-footer > h4 {
                                    margin: 18px 0 12px;
                                }
                                [id^="responsive-modal_edit"] .modal-footer .btn {
                                    margin-top: 6px;
                                }
                                [id^="responsive-modal_edit"] .modal-footer .input-group {
                                    width: 100%;
                                }
                                [id^="responsive-modal_edit"] .modal-footer .form-control {
                                    width: 100%;
                                }
                                [id^="responsive-modal_edit"] .modal-footer .row .col-sm-4,
                                [id^="responsive-modal_edit"] .modal-footer .row .col-sm-8,
                                [id^="responsive-modal_edit"] .modal-footer .row .col-md-4,
                                [id^="responsive-modal_edit"] .modal-footer .row .col-md-8,
                                [id^="responsive-modal_edit"] .modal-footer .row .col-md-12 {
                                    margin-bottom: 12px;
                                }
                                @media (max-width: 767px) {
                                    [id^="responsive-modal_edit"] .modal-dialog {
                                        width: auto;
                                        margin: 10px;
                                    }
                                    [id^="responsive-modal_edit"] .modal-footer {
                                        padding: 18px;
                                    }
                                    .table-responsive {
                                        border: 0;
                                    }
                                    #example23 {
                                        width: 100% !important;
                                    }
                                    #example23 th,
                                    #example23 td {
                                        white-space: normal !important;
                                        font-size: 11px;
                                        line-height: 1.35;
                                        padding: 8px 6px !important;
                                        vertical-align: middle;
                                    }
                                    #example23 .btn,
                                    #example23 .label,
                                    #example23 a {
                                        font-size: 10px;
                                    }
                                    .dataTables_wrapper .dt-buttons .btn {
                                        margin-bottom: 6px;
                                        padding: 5px 8px;
                                        font-size: 11px;
                                    }
                                    .dataTables_wrapper .dataTables_filter {
                                        float: none !important;
                                        text-align: left !important;
                                        margin-top: 8px;
                                    }
                                    .dataTables_wrapper .dataTables_filter input {
                                        width: 100px !important;
                                        margin-left: 6px !important;
                                    }
                                    .dataTables_wrapper .dataTables_paginate .paginate_button {
                                        padding: 0.2em 0.55em !important;
                                        font-size: 11px;
                                    }
                                }
                            </style>

                            <h3 class="box-title m-b-0">Current house listings ( <x style="color: orange;"><?php echo mysqli_num_rows($query);?></x> )</h3>
                            <p class="text-muted m-b-30">Export data to Copy, CSV, Excel, PDF & Print</p>
                            <div class="m-b-15">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        Columns <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu" id="house-column-toggles"></ul>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="example23" class="display nowrap" cellspacing="0" width="100%">

                                    <?php 

                                    if (mysqli_num_rows($query)==0) {
                                                    echo "<i style='color:brown;'>No houses to display :( </i> ";
                                                }
                                                else{

                                                    echo '
                                                    <thead>
                                                    <tr>
                                                        <th>House ID</th>
                                                        '.($canManageHouses ? '<th>Actions</th>' : '').'
                                                        <th>House Name</th>
                                                        <th>No. of rooms</th>
                                                        <th>Rent amount</th>
                                                        <th>Location</th>
                                                        <th>Bedrooms</th>
                                                        <th>Partitions</th>
                                                        <th>House Status</th>
                                                        <th>Photos</th>
                                                    </tr>
                                                </thead>
                                                <tfoot>
                                                    <tr>
                                                        <th>House ID</th>
                                                        '.($canManageHouses ? '<th>Actions</th>' : '').'
                                                        <th>House Name</th>
                                                        <th>No. of rooms</th>
                                                        <th>Rent amount</th>
                                                        <th>Location</th>
                                                        <th>Bedrooms</th>
                                                        <th>Partitions</th>
                                                        <th>House Status</th>
                                                        <th>Photos</th>
                                                    </tr>
                                                </tfoot>
                                                <tbody>
                                                    ';
                                                }

                                        while ($row = mysqli_fetch_array($query)) {
                                            // $id = $row["id"]
                                            $i=$row["houseID"];
                                            $photoQuery = mysqli_query($connection, "SELECT * FROM `house_pics` WHERE `house_id`='$i' AND `partition_id` IS NULL ORDER BY `pic_id` DESC");
                                            $photoCount = $photoQuery ? mysqli_num_rows($photoQuery) : 0;
                                            $photoCards = '';

                                            if ($photoCount > 0) {
                                                while ($photo = mysqli_fetch_assoc($photoQuery)) {
                                                    $picId = (int) $photo["pic_id"];
                                                    $picPath = htmlspecialchars($photo["pic_name"], ENT_QUOTES, 'UTF-8');
                                                    $picType = isset($photo["pic_type"]) ? htmlspecialchars($photo["pic_type"], ENT_QUOTES, 'UTF-8') : 'Beds';
                                                    $deletePhotoForm = $canManageHouses ? '
                                                                <form action="functions/house_photo_manage.php" method="post" style="margin-top:8px;">
                                                                    <input type="hidden" name="pic_id" value="'.$picId.'">
                                                                    <input type="hidden" name="return_to" value="houses.php">
                                                                    <button type="submit" name="deleteHousePhoto" class="btn btn-danger btn-xs" onclick="return confirm(\'Delete this photo?\');">
                                                                        <i class="fa fa-trash"></i> Delete
                                                                    </button>
                                                                </form>
                                                    ' : '';
                                                    $photoCards .= '
                                                        <div class="col-sm-4" style="margin-bottom:15px;">
                                                            <div style="border:1px solid #e4e7ea; padding:8px; min-height:190px;">
                                                                <a href="#" class="js-photo-preview" data-photo-src="'.$picPath.'" data-photo-title="'.$picType.' photo">
                                                                    <img src="'.$picPath.'" alt="'.$picType.' photo" style="width:100%; height:120px; object-fit:cover; margin-bottom:8px; cursor:pointer;">
                                                                </a>
                                                                <span class="label label-info">'.$picType.'</span>
                                                                '.$deletePhotoForm.'
                                                            </div>
                                                        </div>
                                                    ';
                                                }
                                            } else {
                                                $photoCards = '<div class="col-md-12"><i style="color:brown;">No bed or partition photos uploaded yet.</i></div>';
                                            }
                                            $photoBadge = $photoCount > 0
                                                ? '<a href="#" class="btn btn-info btn-xs js-photo-gallery" data-gallery-target="#responsive-modal_photos'.$i.'" title="View uploaded photos full screen"><i class="fa fa-image"></i> Image ('.$photoCount.')</a>'
                                                : '<span class="label label-default">No Image</span>';
                                            $partitionCounts = get_house_partition_counts($connection, $i);
                                            $partitionCount = $partitionCounts['total'];
                                            $availablePartitionCount = $partitionCounts['available'];
                                            $partitionBadgeText = $availablePartitionCount.'/'.$partitionCount;
                                            $partitionBadge = '<a href="add-partition.php?house_id='.$i.'" class="label label-info" title="Open partitions page for '.$row["house_name"].'">'.$partitionBadgeText.'</a>';
                                            $partitionRows = get_house_partitions($connection, $i);
                                            $partitionCards = '';
                                            $availablePartitionCards = '';

                                            if ($partitionRows && mysqli_num_rows($partitionRows) > 0) {
                                                while ($partition = mysqli_fetch_assoc($partitionRows)) {
                                                    $partitionId = (int) $partition['partition_id'];
                                                    $partitionNumber = htmlspecialchars($partition['partition_number'], ENT_QUOTES, 'UTF-8');
                                                    $partitionRent = htmlspecialchars($partition['rent_amount'], ENT_QUOTES, 'UTF-8');
                                                    $partitionStatus = htmlspecialchars($partition['partition_status'], ENT_QUOTES, 'UTF-8');
                                                    $partitionDescription = htmlspecialchars((string) $partition['description'], ENT_QUOTES, 'UTF-8');
                                                    $partitionFacilities = isset($partition['facilities']) ? $partition['facilities'] : '';
                                                    $isOwnPartition = $currentTenant && isset($currentTenant['partition_id']) && (int) $currentTenant['partition_id'] === $partitionId;
                                                    if (!$canManageHouses && $partitionStatus !== 'Vacant' && !$isOwnPartition) {
                                                        continue;
                                                    }
                                                    $partitionPhotoCount = (int) $partition['photo_count'];
                                                    $partitionPhotoQuery = mysqli_query($connection, "SELECT * FROM `house_pics` WHERE `partition_id`='$partitionId' ORDER BY `pic_id` DESC");
                                                    $partitionPhotoCards = '';

                                                    if ($partitionPhotoQuery && mysqli_num_rows($partitionPhotoQuery) > 0) {
                                                        while ($partitionPhoto = mysqli_fetch_assoc($partitionPhotoQuery)) {
                                                            $partitionPicId = (int) $partitionPhoto['pic_id'];
                                                            $partitionPicPath = htmlspecialchars($partitionPhoto['pic_name'], ENT_QUOTES, 'UTF-8');
                                                            $partitionDeleteForm = $canManageHouses ? '
                                                                <form action="functions/house_photo_manage.php" method="post" style="margin-top:8px;">
                                                                    <input type="hidden" name="pic_id" value="'.$partitionPicId.'">
                                                                    <input type="hidden" name="return_to" value="houses.php">
                                                                    <button type="submit" name="deleteHousePhoto" class="btn btn-danger btn-xs" onclick="return confirm(\'Delete this partition photo?\');">
                                                                        <i class="fa fa-trash"></i> Delete
                                                                    </button>
                                                                </form>
                                                            ' : '';
                                                            $partitionPhotoCards .= '
                                                                <div class="col-sm-4" style="margin-bottom:15px;">
                                                                    <div style="border:1px solid #e4e7ea; padding:8px;">
                                                                        <a href="#" class="js-photo-preview" data-photo-src="'.$partitionPicPath.'" data-photo-title="'.$partitionNumber.' photo">
                                                                            <img src="'.$partitionPicPath.'" alt="'.$partitionNumber.' photo" style="width:100%; height:110px; object-fit:cover; margin-bottom:8px; cursor:pointer;">
                                                                        </a>
                                                                        '.$partitionDeleteForm.'
                                                                    </div>
                                                                </div>
                                                            ';
                                                        }
                                                    } else {
                                                        $partitionPhotoCards = '<div class="col-md-12"><i style="color:brown;">No photos uploaded for this partition yet.</i></div>';
                                                    }

                                                    $partitionActions = $canManageHouses ? '
                                                        <button type="button" class="btn btn-info btn-xs" data-toggle="collapse" data-target="#partition-edit-'.$partitionId.'">
                                                            <i class="fa fa-edit"></i> Edit Rent
                                                        </button>
                                                        <form action="functions/partition_manage.php" method="post" style="display:inline-block; margin-left:5px;">
                                                            <input type="hidden" name="partition_id" value="'.$partitionId.'">
                                                            <button type="submit" name="deletePartition" class="btn btn-danger btn-xs" onclick="return confirm(\'Delete this partition and its photos?\');">
                                                                <i class="fa fa-trash"></i> Delete
                                                            </button>
                                                        </form>
                                                    ' : '';

                                                    $partitionEditForm = $canManageHouses ? '
                                                        <div id="partition-edit-'.$partitionId.'" class="collapse" style="margin-top:12px;">
                                                            <form action="functions/partition_manage.php" method="post">
                                                                <input type="hidden" name="partition_id" value="'.$partitionId.'">
                                                                <div class="row">
                                                                    <div class="form-group col-md-3">
                                                                        <label>Partition No./Name</label>
                                                                        <input type="text" name="partition_number" class="form-control" value="'.$partitionNumber.'" required>
                                                                    </div>
                                                                    <div class="form-group col-md-3">
                                                                        <label>Rent Amount</label>
                                                                        <input type="number" min="0" step="0.01" name="rent_amount" class="form-control" value="'.$partitionRent.'" required>
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
                                                                        <input type="text" name="description" class="form-control" value="'.$partitionDescription.'">
                                                                    </div>
                                                                    <div class="form-group col-md-12">
                                                                        <label>Facilities</label>
                                                                        <div style="border:1px solid #e4e7ea; padding:12px;">
                                                                            '.render_partition_facility_checkboxes($partitionFacilities).'
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-12">
                                                                        <button type="submit" name="editPartition" class="btn btn-success btn-sm">
                                                                            <i class="fa fa-save"></i> Update Partition
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </form>
                                                        </div>
                                                        <form action="functions/house_photo_manage.php" method="post" enctype="multipart/form-data" style="margin-top:12px;">
                                                            <input type="hidden" name="house_id" value="'.$i.'">
                                                            <input type="hidden" name="partition_id" value="'.$partitionId.'">
                                                            <input type="hidden" name="pic_type" value="Partitions">
                                                            <input type="hidden" name="return_to" value="houses.php">
                                                            <div class="row">
                                                                <div class="form-group col-md-8">
                                                                    <input type="file" name="house_photos[]" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                                                                </div>
                                                                <div class="form-group col-md-4">
                                                                    <button type="submit" name="uploadHousePhoto" class="btn btn-success btn-sm">
                                                                        <i class="fa fa-upload"></i> Upload Partition Photo(s)
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    ' : '';

                                                    $partitionCardHtml = '
                                                        <div class="panel panel-default">
                                                            <div class="panel-heading">
                                                                <strong>'.$partitionNumber.'</strong>
                                                                <span class="label label-info pull-right">'.$partitionPhotoCount.' Photos</span>
                                                            </div>
                                                            <div class="panel-body">
                                                                <p><strong>Rent Amount:</strong> '.$partitionRent.'</p>
                                                                <p><strong>Status:</strong> '.$partitionStatus.'</p>
                                                                '.($partitionDescription !== '' ? '<p><strong>Description:</strong> '.$partitionDescription.'</p>' : '').'
                                                                <p><strong>Facilities:</strong><br>'.render_partition_facilities_badges($partitionFacilities).'</p>
                                                                '.$partitionActions.'
                                                                '.$partitionEditForm.'
                                                                <hr>
                                                                <h5>Partition Photos</h5>
                                                                <div class="row">'.$partitionPhotoCards.'</div>
                                                            </div>
                                                        </div>
                                                    ';
                                                    $partitionCards .= $partitionCardHtml;
                                                    if (strtolower((string) $partition['partition_status']) === 'vacant') {
                                                        $availablePartitionCards .= $partitionCardHtml;
                                                    }
                                                }
                                                if ($partitionCards === '') {
                                                    $partitionCards = '<i style="color:brown;">No available partitions to display right now.</i>';
                                                }
                                                if ($availablePartitionCards === '') {
                                                    $availablePartitionCards = '<i style="color:brown;">No available partitions right now.</i>';
                                                }
                                            } else {
                                                $partitionCards = '<i style="color:brown;">No partitions added for this house yet.</i>';
                                                $availablePartitionCards = '<i style="color:brown;">No available partitions right now.</i>';
                                            }
                                            $addPartitionForm = $canManageHouses ? '
                                                <form action="functions/partition_manage.php" method="post">
                                                    <input type="hidden" name="house_id" value="'.$i.'">
                                                    <div class="row">
                                                        <div class="form-group col-md-3">
                                                            <label>Partition No./Name: *</label>
                                                            <input type="text" name="partition_number" class="form-control" placeholder="e.g. P1 or Room A" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label>Rent Amount: *</label>
                                                            <input type="number" min="0" step="0.01" name="rent_amount" class="form-control" placeholder="e.g. 2500" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label>Status:</label>
                                                            <select name="partition_status" class="form-control">
                                                                <option value="Vacant">Vacant</option>
                                                                <option value="Occupied">Occupied</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label>Description:</label>
                                                            <input type="text" name="description" class="form-control" placeholder="Optional">
                                                        </div>
                                                        <div class="form-group col-md-12">
                                                            <label>Facilities:</label>
                                                            <div style="border:1px solid #e4e7ea; padding:12px;">
                                                                '.render_partition_facility_checkboxes().'
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <button type="submit" name="addPartition" class="btn btn-success">
                                                                <i class="fa fa-plus"></i> Add Partition
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                                <hr>
                                            ' : '';

                                            if (!$canManageHouses) {
                                                echo '
                                                    <tr>
                                                        <td>'.$row["houseID"].'</td>
                                                        <td>'.$row["house_name"].'</td>
                                                        <td>'.$row["number_of_rooms"].'</td>
                                                        <td>'.$row["rent_amount"].'</td>
                                                        <td>'.$row["location"].'</td>
                                                        <td>'.$row["num_of_bedrooms"].'</td>
                                                        <td>'.$partitionBadge.'</td>
                                                        <td>'.$row["house_status"].'</td>
                                                        <td>'.$photoBadge.'</td>
                                                    </tr>
                                                ';

                                                if ($photoCount > 0) {
                                                    $houseModalMarkup[] = '
                                                        <div id="responsive-modal_photos'.$i.'" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="overflow-y:auto; display:none;">
                                                            <div class="modal-dialog modal-lg">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                                                                        <h4 style="text-align:center;" class="modal-title">
                                                                            <i class="fa fa-camera fa-3x"></i> Uploaded Photos for '.$row["house_name"].'
                                                                        </h4>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <h4>Uploaded Photos ('.$photoCount.')</h4>
                                                                        <div class="row">
                                                                            '.$photoCards.'
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ';
                                                }

                                                $houseModalMarkup[] = '
                                                    <div id="responsive-modal_partitions'.$i.'" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="overflow-y:auto; display:none;">
                                                        <div class="modal-dialog modal-lg">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                                                                    <h4 style="text-align:center;" class="modal-title">
                                                                        <i class="fa fa-columns fa-3x"></i> Partitions for '.$row["house_name"].'
                                                                    </h4>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <h4>Available Partitions ('.$partitionBadgeText.')</h4>
                                                                    <p class="text-muted">'.$availablePartitionCount.' partition(s) available out of '.$partitionCount.' total.</p>
                                                                    '.$availablePartitionCards.'
                                                                    '.($partitionCards !== $availablePartitionCards ? '<hr><h4>Other Visible Partitions</h4>'.$partitionCards : '').'
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ';
                                                continue;
                                            }

                                    echo '
                                    

                                        <tr>
                                            <td>'.$row["houseID"].'</td>
                                            ';

                                            if ($canManageHouses) {
                                                echo '
                                                <td>
                                                    <a href="#" class="js-house-delete-trigger" data-target="#responsive-modal'.$row["houseID"].'" title="Remove house"><i class="fa fa-trash" style="color:red;"></i></a> || 
                                                    <a href="#" class="js-house-edit-trigger" data-target="#responsive-modal_edit'.$i.'" title="Edit house"><i class="fa fa-edit" style="color:#03a9f3;"></i></a> ||
                                                    <a href="#" class="js-house-photo-trigger" data-target="#responsive-modal_photos'.$i.'" title="Upload beds / partitions photos"><i class="fa fa-camera" style="color:#00c292;"></i></a>
                                                </td>
                                                ';
                                            }

                                            echo '
                                                        <td>
                                                            <a href="#" title="Edit record" style="color:#03a9f3" data-toggle="modal" data-target="#responsive-modal_edit'.$i.'">
                                                                '.$row["house_name"].'
                                                            </a>
                                                        </td>
                                                        <td>'.$row["number_of_rooms"].'</td>
                                                        <td>'.$row["rent_amount"].'</td>
                                                        <td>'.$row["location"].'</td>
                                                        <td>'.$row["num_of_bedrooms"].'</td>
                                                        <td>'.$partitionBadge.'</td>
                                                        <td>'.$row["house_status"].'</td>
                                                        <td>'.$photoBadge.'</td>
                                                    </tr>';

                                            $houseModalMarkup[] = '
                                            <!-- /.modal to edit -->
                                            <div id="responsive-modal_edit'.$i.'" class=" modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="overflow-y:auto; display:none;">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                                                            <h4 style="text-align:center;" class="modal-title">
                                                            <i class="fa fa-user fa-3x"> </i> Edit
                                                            
                                                             '.$row["house_name"].' House details:</h4>
                                                            </div>
                                                        <div class="modal-footer">

                                                        <form action="functions/house_manage.php" method="post">
                                                        <div class="row">

                                                            <div class="form-group col-md-12">
                                                                <label for="hname">House Name: *</label>
                                                                <div class="input-group">
                                                                    <div class="input-group-addon"><i class="fa fa-home"></i></div>
                                                                    <input type="text" autofocus name="tname" class="form-control" id="hname" value="'.$row["house_name"].'" placeholder="Enter Officer\'s name" required=""> </div>
                                                            </div>

                                                            <div class="form-group col-md-12">
                                                                <label for="temail">Number of Rooms: </label>
                                                                <div class="input-group">
                                                                    <div class="input-group-addon"><i class="fa fa-home"></i></div>
                                                                    <input type="number" name="tnum" 
                                                                    value="'.$row["number_of_rooms"].'"
                                                                    onkeyup=""
                                                                    required class="form-control" id="temail" placeholder="e.g 1"> </div>
                                                            </div>

                                                            <div class="form-group col-md-12">
                                                                <label for="idnum">Rent Amount: *</label>
                                                                <div class="input-group">
                                                                    <div class="input-group-addon"><i class="fa fa-home"></i></div>
                                                                    <input type="number" min="1000" required name="rent" class="form-control" value="'.$row["rent_amount"].'" id="idnum" placeholder="e.g. 4000" onkeyup="stripnum(\'idnum\',10);" > </div>
                                                            </div>

                                                            <input type="text" value="'.$row["houseID"].'" name="hsid" readonly hidden>

                                                            <div class="form-group col-md-12">
                                                                <label for="oftype">House Status: </label>
                                                                <div class="input-group">
                                                                    <div class="input-group-addon"><i class="fa fa-home"></i></div>
                                                                    <select id="oftype" name="oftype" class="form-control">
                                                                        <option selected value="'.$row["house_status"].'">'.$row["house_status"].'</option>
                                                                        <option value="Vacant">Vacant</option>
                                                                        <option value="Occupied">Occupied</option>
                                                                    </select>
                                                                    </div>
                                                            </div>

                                                        

                                                        </div>

                                                            </form>
                                                            <hr>
                                                            <h4><i class="fa fa-camera"></i> Upload New Image</h4>
                                                            <form action="functions/house_photo_manage.php" method="post" enctype="multipart/form-data">
                                                                <input type="hidden" name="house_id" value="'.$row["houseID"].'">
                                                                <input type="hidden" name="return_to" value="houses.php">
                                                                <div class="row">
                                                                    <div class="form-group col-md-4">
                                                                        <label>Photo Type: *</label>
                                                                        <select name="pic_type" class="form-control" required>
                                                                            <option value="House">House</option>
                                                                            <option value="Beds">Beds</option>
                                                                            <option value="Partitions">Partitions</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="form-group col-md-8">
                                                                        <label>Select Photo(s): *</label>
                                                                        <input type="file" name="house_photos[]" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                                                                        <small class="text-muted">Allowed: JPG, PNG, GIF, WEBP. Max 5MB per image.</small>
                                                                    </div>
                                                                    <div class="col-md-12">
                                                                        <button type="submit" name="uploadHousePhoto" class="btn btn-success">
                                                                            <i class="fa fa-upload"></i> Upload Photo(s)
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </form>
                                                            <hr>
                                                            <h4>Current Uploaded Photos ('.$photoCount.')</h4>
                                                            <div class="row">
                                                                '.$photoCards.'
                                                            </div>
                                                            <hr>
                                                            <div class="text-left">
                                                                <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                                                                <button type="submit" name="editHouse" class="btn btn-danger waves-effect waves-light">Update Record</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div> 
                                            <!-- End Modal -->
                                            ';

                                            $houseModalMarkup[] = '
                                            <!-- /.modal to delete -->
                                            <div id="responsive-modal'.$row["houseID"].'" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="display: none;">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                                                            <h4 class="modal-title">Are you really sure you want to delete '.$row["house_name"].'\'s house record?</h4>
                                                            <h5> This action could detach all tenant records linked to this house.</h5>
                                                            </div>
                                                        <div class="modal-footer">

                                                        <form action="functions/del_house.php" method="post">
                                                        <input type="hidden" name="id" value="'.
                                                        $row["houseID"].'"/>
                                                            <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger waves-effect waves-light">Delete anyway</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div> 
                                            <!-- End Modal -->
                                            ';

                                            $houseModalMarkup[] = '
                                            <!-- /.modal to manage house photos -->
                                            <div id="responsive-modal_photos'.$i.'" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="overflow-y:auto; display:none;">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                            <h4 style="text-align:center;" class="modal-title">
                                                                <i class="fa fa-camera fa-3x"></i> Beds / Partitions Photos for '.$row["house_name"].'
                                                            </h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form action="functions/house_photo_manage.php" method="post" enctype="multipart/form-data">
                                                                <input type="hidden" name="house_id" value="'.$row["houseID"].'">
                                                                <input type="hidden" name="return_to" value="houses.php">
                                                                <div class="row">
                                                                    <div class="form-group col-md-4">
                                                                        <label>Photo Type: *</label>
                                                                        <select name="pic_type" class="form-control" required>
                                                                            <option value="House">House</option>
                                                                            <option value="Beds">Beds</option>
                                                                            <option value="Partitions">Partitions</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="form-group col-md-8">
                                                                        <label>Select Photo(s): *</label>
                                                                        <input type="file" name="house_photos[]" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                                                                        <small class="text-muted">Allowed: JPG, PNG, GIF, WEBP. Max 5MB per image.</small>
                                                                    </div>
                                                                    <div class="col-md-12">
                                                                        <button type="submit" name="uploadHousePhoto" class="btn btn-success">
                                                                            <i class="fa fa-upload"></i> Upload Photo(s)
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </form>
                                                            <hr>
                                                            <h4>Uploaded Photos ('.$photoCount.')</h4>
                                                            <div class="row">
                                                                '.$photoCards.'
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- End Modal -->
                                            ';

                                            $houseModalMarkup[] = '
                                            <!-- /.modal to manage house partitions -->
                                            <div id="responsive-modal_partitions'.$i.'" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="overflow-y:auto; display:none;">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                                                            <h4 style="text-align:center;" class="modal-title">
                                                                <i class="fa fa-columns fa-3x"></i> Partitions for '.$row["house_name"].'
                                                            </h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            '.$addPartitionForm.'
                                                            <h4>Available Partitions ('.$partitionBadgeText.')</h4>
                                                            <p class="text-muted">'.$availablePartitionCount.' partition(s) available out of '.$partitionCount.' total.</p>
                                                            '.$availablePartitionCards.'
                                                            <hr>
                                                            <h4>All Partitions ('.$partitionCount.')</h4>
                                                            '.$partitionCards.'
                                                        </div>
                                                        <div class="modal-body">
                                                            <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- End Modal -->
                                            ';

                                    }

                                    ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php echo implode("\n", $houseModalMarkup); ?>
                        </div>
                    </div>
                </div>


             


                <!-- /.row -->
                                <!-- .right-sidebar -->
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
                            <ul id="themecolors" class="m-t-20">
                                <li><b>With Light sidebar</b></li>
                                <li><a href="javascript:void(0)" theme="default" class="default-theme">1</a></li>
                                <li><a href="javascript:void(0)" theme="green" class="green-theme">2</a></li>
                                <li><a href="javascript:void(0)" theme="gray" class="yellow-theme">3</a></li>
                                <li><a href="javascript:void(0)" theme="blue" class="blue-theme working">4</a></li>
                                <li><a href="javascript:void(0)" theme="purple" class="purple-theme">5</a></li>
                                <li><a href="javascript:void(0)" theme="megna" class="megna-theme">6</a></li>
                                <li><b>With Dark sidebar</b></li>
                                <br/>
                                <li><a href="javascript:void(0)" theme="default-dark" class="default-dark-theme">7</a></li>
                                <li><a href="javascript:void(0)" theme="green-dark" class="green-dark-theme">8</a></li>
                                <li><a href="javascript:void(0)" theme="gray-dark" class="yellow-dark-theme">9</a></li>
                                <li><a href="javascript:void(0)" theme="blue-dark" class="blue-dark-theme">10</a></li>
                                <li><a href="javascript:void(0)" theme="purple-dark" class="purple-dark-theme">11</a></li>
                                <li><a href="javascript:void(0)" theme="megna-dark" class="megna-dark-theme">12</a></li>
                            </ul>
                            </div>
                    </div>
                </div>
                <!-- /.right-sidebar -->
            </div>
            <?php require "admin_footer.php"; ?>
    <div id="photo-preview-overlay" style="display:none; position:fixed; inset:0; background:rgba(7, 26, 45, 0.96); z-index:3000; padding:64px 18px 24px; overflow:auto;">
        <button type="button" id="photo-preview-close" aria-label="Close photo preview" style="position:fixed; top:14px; right:20px; z-index:3002; width:46px; height:46px; border:1px solid rgba(200, 164, 73, 0.55); border-radius:50%; background:#071A2D; color:#F6F2E8; font-size:34px; line-height:40px; cursor:pointer; box-shadow:0 10px 28px rgba(0, 0, 0, 0.35);">&times;</button>
        <div style="max-width:1180px; min-height:calc(100vh - 88px); margin:0 auto; display:flex; flex-direction:column; gap:14px;">
            <h4 id="photo-preview-title" style="color:#F6F2E8; text-align:center; margin:0 60px 0; font-weight:700;">Photo Preview</h4>
            <div style="flex:1; display:flex; align-items:center; justify-content:center; background:#071A2D; border:1px solid rgba(200, 164, 73, 0.45); border-radius:8px; padding:14px; box-shadow:0 20px 50px rgba(0, 0, 0, 0.35);">
                <img id="photo-preview-image" src="" alt="Photo preview" style="display:block; max-width:100%; max-height:calc(100vh - 190px); object-fit:contain; margin:0 auto;">
            </div>
            <div id="photo-preview-thumbs" style="display:none; gap:10px; justify-content:center; flex-wrap:wrap;"></div>
        </div>
    </div>
    <script>
    $(document).ready(function() {
        var $photoPreviewOverlay = $('#photo-preview-overlay');
        var $photoPreviewImage = $('#photo-preview-image');
        var $photoPreviewTitle = $('#photo-preview-title');
        var $photoPreviewThumbs = $('#photo-preview-thumbs');
        var photoPreviewOpen = false;

        $('.modal').removeClass('fade');
        $('.modal .close').each(function() {
            if ($(this).text().indexOf('Ã') !== -1) {
                $(this)
                    .attr('aria-label', 'Close')
                    .html('<span aria-hidden="true">&times;</span>');
            }
        });

        $(document).on('hidden.bs.modal', '.modal', function() {
            if (!$('.modal:visible').length) {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('padding-right', '');
            }
        });

        function openHouseModal(targetSelector) {
            if (!targetSelector) {
                return;
            }

            var $targetModal = $(targetSelector);
            if (!$targetModal.length) {
                return;
            }

            $targetModal.modal('show');
        }

        $(document).on('click', '.js-house-edit-trigger, .js-house-delete-trigger, .js-house-photo-trigger', function(event) {
            event.preventDefault();
            event.stopPropagation();
            openHouseModal($(this).data('target'));
        });

        function setPhotoPreviewImage(photoSrc, photoTitle) {
            if (!photoSrc) {
                return;
            }

            $photoPreviewTitle.text(photoTitle || 'Photo Preview');
            $photoPreviewImage.attr('src', photoSrc);
            $photoPreviewThumbs.find('button').removeClass('active');
            $photoPreviewThumbs.find('button').each(function() {
                if ($(this).data('photo-src') === photoSrc) {
                    $(this).addClass('active');
                }
            });
        }

        function showPhotoPreview(photoSrc, photoTitle, photos) {
            if (!photoSrc) {
                return;
            }

            photos = photos || [];
            photoPreviewOpen = true;
            $photoPreviewThumbs.empty().hide();

            if (photos.length > 1) {
                $.each(photos, function(index, photo) {
                    var $button = $('<button type="button" style="width:82px; height:62px; padding:2px; border:2px solid rgba(200, 164, 73, 0.4); border-radius:4px; background:#071A2D; cursor:pointer;"></button>');
                    var $image = $('<img alt="" style="width:100%; height:100%; object-fit:cover; display:block;">');
                    $button.attr('data-photo-src', photo.src);
                    $button.attr('data-photo-title', photo.title || 'Photo Preview');
                    $image.attr('src', photo.src);
                    $button.append($image);
                    $photoPreviewThumbs.append($button);
                });
                $photoPreviewThumbs.css('display', 'flex');
            }

            setPhotoPreviewImage(photoSrc, photoTitle);
            $photoPreviewOverlay.stop(true, true).fadeIn(120);
            $('body').addClass('photo-preview-open');
        }

        $(document).on('click', '.js-photo-preview', function(event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            var photoSrc = $(this).data('photo-src');
            var photoTitle = $(this).data('photo-title') || 'Photo Preview';
            showPhotoPreview(photoSrc, photoTitle);
        });

        $(document).on('click', '.js-photo-gallery', function(event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            var targetSelector = $(this).data('gallery-target');
            var photos = [];

            $(targetSelector).find('.js-photo-preview').each(function() {
                var src = $(this).data('photo-src');
                if (src) {
                    photos.push({
                        src: src,
                        title: $(this).data('photo-title') || 'Photo Preview'
                    });
                }
            });

            if (photos.length) {
                showPhotoPreview(photos[0].src, photos[0].title, photos);
            }
        });

        $photoPreviewThumbs.on('click', 'button', function() {
            setPhotoPreviewImage($(this).data('photo-src'), $(this).data('photo-title'));
        });

        function hidePhotoPreview() {
            if (!photoPreviewOpen) {
                return;
            }

            photoPreviewOpen = false;
            $photoPreviewOverlay.stop(true, true).fadeOut(120, function() {
                $photoPreviewImage.attr('src', '');
                $photoPreviewThumbs.empty().hide();
            });
            $('body').removeClass('photo-preview-open');
        }

        $('#photo-preview-close').on('click', function() {
            hidePhotoPreview();
        });

        $photoPreviewOverlay.on('click', function(event) {
            if (event.target === this) {
                hidePhotoPreview();
            }
        });

        $(document).on('keyup', function(event) {
            if (event.key === 'Escape' && $photoPreviewOverlay.is(':visible')) {
                hidePhotoPreview();
            }
        });

        $('#myTable').DataTable();
        $(document).ready(function() {
            var table = $('#example').DataTable({
                "columnDefs": [{
                    "visible": false,
                    "targets": 2
                }],
                "order": [
                    [2, 'asc']
                ],
                "displayLength": 10,
                "drawCallback": function(settings) {
                    var api = this.api();
                    var rows = api.rows({
                        page: 'current'
                    }).nodes();
                    var last = null;
                    api.column(2, {
                        page: 'current'
                    }).data().each(function(group, i) {
                        if (last !== group) {
                            $(rows).eq(i).before('<tr class="group"><td colspan="5">' + group + '</td></tr>');
                            last = group;
                        }
                    });
                }
            });
            // Order by the grouping
            $('#example tbody').on('click', 'tr.group', function() {
                var currentOrder = table.order()[0];
                if (currentOrder[0] === 2 && currentOrder[1] === 'asc') {
                    table.order([2, 'desc']).draw();
                } else {
                    table.order([2, 'asc']).draw();
                }
            });
        });
    });
    var savedHouseColumns = <?php echo $savedHouseColumnsJson; ?>;
    var isMobileHouseView = window.matchMedia('(max-width: 767px)').matches;
    if (isMobileHouseView) {
        $('#example23').removeClass('nowrap');
    }
    var houseTable = $('#example23').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        scrollX: !isMobileHouseView,
        autoWidth: false
    });

    if (isMobileHouseView) {
        houseTable.columns.adjust().draw(false);
    }

    if (Array.isArray(savedHouseColumns) && savedHouseColumns.length > 0) {
        houseTable.columns().every(function(index) {
            this.visible(savedHouseColumns.indexOf(index) !== -1);
        });
    }

    function escapeHouseHtml(value) {
        return $('<div>').text(value).html();
    }

    function renderHouseColumnToggles() {
        var toggleHtml = '';
        houseTable.columns().every(function(index) {
            var headerText = $(this.header()).text().trim();
            if (!headerText) {
                return;
            }
            var checked = this.visible() ? 'checked' : '';
            toggleHtml += '<li><a href="#" class="house-col-toggle" data-col="' + index + '"><label style="margin:0; font-weight:500; cursor:pointer;"><input type="checkbox" ' + checked + ' style="margin-right:8px;">' + escapeHouseHtml(headerText) + '</label></a></li>';
        });
        $('#house-column-toggles').html(toggleHtml);
    }

    renderHouseColumnToggles();

    var houseColumnSaveTimer = null;
    function saveHouseColumnPreference() {
        var visibleColumns = [];
        houseTable.columns().every(function(index) {
            if (this.visible()) {
                visibleColumns.push(index);
            }
        });

        $.ajax({
            url: 'functions/save_ui_columns.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                table_key: 'houses_view',
                visible_columns: visibleColumns
            })
        });
    }

    function queueHouseColumnPreferenceSave() {
        if (houseColumnSaveTimer) {
            clearTimeout(houseColumnSaveTimer);
        }
        houseColumnSaveTimer = setTimeout(saveHouseColumnPreference, 220);
    }

    $('#house-column-toggles').on('click', '.house-col-toggle', function(event) {
        event.preventDefault();
        event.stopPropagation();
        var columnIndex = parseInt($(this).data('col'), 10);
        var column = houseTable.column(columnIndex);
        var visibleCount = houseTable.columns(':visible').count();
        if (column.visible() && visibleCount <= 1) {
            return;
        }
        column.visible(!column.visible());
        renderHouseColumnToggles();
        queueHouseColumnPreferenceSave();
    });
    </script>
    <!--Style Switcher -->
    <script src="../plugins/bower_components/styleswitcher/jQuery.style.switcher.js"></script>
</body>

</html>
<?php
}
else{
    header('location:index.php');
}
?>
