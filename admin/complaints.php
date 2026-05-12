<?php
    $pgnm="Co- Accomodation: Complaints";
    $error=' ';

    require_once "functions/errors.php";

    ob_start();
    require_once "functions/db.php";
    require_once "functions/tenant_helpers.php";
    require_once "functions/partition_helpers.php";

    session_start();

    if(!isset($_SESSION['email']) || empty($_SESSION['email'])){
      header("location: login.php");
      exit;
    }

    if (is_logged_in_temporary()) {
        ensure_tenant_schema($connection);
        ensure_partition_tables($connection);
        $canManageComplaints = is_admin_user();
        $currentTenant = get_logged_in_tenant_record();

        if (!$canManageComplaints && !$currentTenant) {
            header("location:index.php?restricted=1");
            exit;
        }

        if (isset($_POST['submitComplaint']) && !$canManageComplaints) {
            $tenantId = (int) $currentTenant['tenantID'];
            $houseId = (int) $currentTenant['houseNumber'];
            $partitionId = isset($currentTenant['partition_id']) ? (int) $currentTenant['partition_id'] : 0;
            $title = uncrack($_POST['title']);
            $description = uncrack($_POST['description']);
            $imagePath = '';

            if ($title !== '' && $description !== '') {
                if (!empty($_FILES['complaint_image']['name'])) {
                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                    if (isset($allowed[$_FILES['complaint_image']['type']]) && (int) $_FILES['complaint_image']['size'] <= 5242880) {
                        $uploadDir = __DIR__ . '/../images/complaints/';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0777, true);
                        }
                        $extension = $allowed[$_FILES['complaint_image']['type']];
                        $fileName = 'complaint_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
                        $target = $uploadDir . $fileName;
                        if (@move_uploaded_file($_FILES['complaint_image']['tmp_name'], $target)) {
                            $imagePath = 'images/complaints/' . $fileName;
                        }
                    }
                }

                $safeTitle = mysqli_real_escape_string($connection, $title);
                $safeDescription = mysqli_real_escape_string($connection, $description);
                $safeImagePath = mysqli_real_escape_string($connection, $imagePath);
                mysqli_query($connection, "
                    INSERT INTO `tenant_complaints`
                    (`tenant_id`, `house_id`, `partition_id`, `title`, `description`, `image_path`, `status`)
                    VALUES ('$tenantId', '$houseId', '$partitionId', '$safeTitle', '$safeDescription', '$safeImagePath', 'Open')
                ");
                header("location:complaints.php?submitted=1");
                exit;
            }
        }

        if (isset($_POST['updateComplaint']) && $canManageComplaints) {
            $complaintId = (int) $_POST['complaint_id'];
            $status = uncrack($_POST['status']);
            $reason = uncrack($_POST['admin_reason']);
            $allowedStatuses = ['Open', 'In Progress', 'Resolved', 'Rejected'];
            if (!in_array($status, $allowedStatuses, true)) {
                $status = 'Open';
            }
            $safeReason = mysqli_real_escape_string($connection, $reason);
            mysqli_query($connection, "UPDATE `tenant_complaints` SET `status`='$status', `admin_reason`='$safeReason' WHERE `complaint_id`='$complaintId'");
            header("location:complaints.php?updated=1");
            exit;
        }

        if (isset($_POST['reopenComplaint']) && !$canManageComplaints) {
            $complaintId = (int) $_POST['complaint_id'];
            $tenantId = (int) $currentTenant['tenantID'];
            mysqli_query($connection, "
                UPDATE `tenant_complaints`
                SET `status`='Reopened', `reopened_count`=`reopened_count`+1, `admin_reason`=NULL
                WHERE `complaint_id`='$complaintId' AND `tenant_id`='$tenantId'
            ");
            header("location:complaints.php?reopened=1");
            exit;
        }

        if ($canManageComplaints) {
            $sql = "
                SELECT tc.*, t.tenant_name, t.email, h.house_name, hp.partition_number
                FROM `tenant_complaints` tc
                LEFT JOIN `tenants` t ON tc.`tenant_id` = t.`tenantID`
                LEFT JOIN `houses` h ON tc.`house_id` = h.`houseID`
                LEFT JOIN `house_partitions` hp ON tc.`partition_id` = hp.`partition_id`
                ORDER BY tc.`updated_at` DESC
            ";
        } else {
            $tenantId = (int) $currentTenant['tenantID'];
            $sql = "
                SELECT tc.*, h.house_name, hp.partition_number
                FROM `tenant_complaints` tc
                LEFT JOIN `houses` h ON tc.`house_id` = h.`houseID`
                LEFT JOIN `house_partitions` hp ON tc.`partition_id` = hp.`partition_id`
                WHERE tc.`tenant_id`='$tenantId'
                ORDER BY tc.`updated_at` DESC
            ";
        }
        $complaints = mysqli_query($connection, $sql);

        require "admin_header0.php";
        require "admin_left_panel.php";
?>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title"><?php echo $canManageComplaints ? 'Complaint Desk' : 'My Complaints'; ?></h4>
            </div>
            <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                <ol class="breadcrumb">
                    <li><a href="index.php">Dashboard</a></li>
                    <li class="active">Complaints</li>
                </ol>
            </div>
        </div>

        <?php if (isset($_GET['submitted'])) { ?>
            <div class="alert alert-success"><strong>DONE!!</strong> Complaint submitted successfully.</div>
        <?php } elseif (isset($_GET['updated'])) { ?>
            <div class="alert alert-success"><strong>UPDATED!!</strong> Complaint status updated.</div>
        <?php } elseif (isset($_GET['reopened'])) { ?>
            <div class="alert alert-warning"><strong>REOPENED!!</strong> Complaint reopened successfully.</div>
        <?php } ?>

        <?php if (!$canManageComplaints) { ?>
        <div class="white-box">
            <h3 class="box-title">Log A Complaint</h3>
            <form action="complaints.php" method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="form-group col-md-6">
                        <label>Title: *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Issue Image:</label>
                        <input type="file" name="complaint_image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="text-muted">Optional. JPG, PNG, GIF, WEBP. Max 5MB.</small>
                    </div>
                    <div class="form-group col-md-12">
                        <label>Description: *</label>
                        <textarea name="description" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="col-md-12">
                        <button type="submit" name="submitComplaint" class="btn btn-success"><i class="fa fa-plus-circle"></i> Submit Complaint</button>
                    </div>
                </div>
            </form>
        </div>
        <?php } ?>

        <div class="white-box">
            <h3 class="box-title"><?php echo $canManageComplaints ? 'Tenant Complaints' : 'Complaint History'; ?></h3>
            <?php
                if (!$complaints || mysqli_num_rows($complaints) === 0) {
                    echo '<i style="color:brown;">No complaints found.</i>';
                } else {
                    while ($row = mysqli_fetch_assoc($complaints)) {
                        $complaintId = (int) $row['complaint_id'];
                        $status = htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8');
                        $reason = htmlspecialchars((string) $row['admin_reason'], ENT_QUOTES, 'UTF-8');
                        $title = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
                        $description = nl2br(htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8'));
                        $imagePath = htmlspecialchars((string) $row['image_path'], ENT_QUOTES, 'UTF-8');
                        echo '
                            <div style="border:1px solid #e4e7ea; padding:15px; margin-bottom:15px;">
                                <h4>'.$title.' <span class="label label-info" style="margin-left:8px;">'.$status.'</span></h4>
                                '.($canManageComplaints ? '<p><strong>Tenant:</strong> '.htmlspecialchars($row['tenant_name'], ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8').')</p>' : '').'
                                <p><strong>House:</strong> '.htmlspecialchars((string) $row['house_name'], ENT_QUOTES, 'UTF-8').'</p>
                                <p><strong>Partition:</strong> '.htmlspecialchars((string) $row['partition_number'], ENT_QUOTES, 'UTF-8').'</p>
                                <p><strong>Description:</strong><br>'.$description.'</p>
                                '.($imagePath !== '' ? '<p><strong>Issue Image:</strong><br><a href="#" class="js-photo-preview" data-photo-src="'.$imagePath.'" data-photo-title="'.$title.'"><img src="'.$imagePath.'" style="max-width:220px; height:auto; cursor:pointer; border:1px solid #e4e7ea;"></a></p>' : '').'
                                '.($reason !== '' ? '<p><strong>Admin Reason:</strong> '.$reason.'</p>' : '').'
                                <p><strong>Updated:</strong> '.htmlspecialchars($row['updated_at'], ENT_QUOTES, 'UTF-8').'</p>
                        ';

                        if ($canManageComplaints) {
                            echo '
                                <form action="complaints.php" method="post">
                                    <input type="hidden" name="complaint_id" value="'.$complaintId.'">
                                    <div class="row">
                                        <div class="form-group col-md-3">
                                            <label>Status</label>
                                            <select name="status" class="form-control">
                                                <option value="'.$status.'" selected>'.$status.'</option>
                                                <option value="Open">Open</option>
                                                <option value="In Progress">In Progress</option>
                                                <option value="Resolved">Resolved</option>
                                                <option value="Rejected">Rejected</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-7">
                                            <label>Reason / Update</label>
                                            <input type="text" name="admin_reason" class="form-control" value="'.$reason.'" placeholder="Why resolved, rejected, or what is in progress">
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label>&nbsp;</label>
                                            <button type="submit" name="updateComplaint" class="btn btn-success btn-block">Save</button>
                                        </div>
                                    </div>
                                </form>
                            ';
                        } elseif (!in_array($status, ['Open', 'Reopened'], true)) {
                            echo '
                                <form action="complaints.php" method="post">
                                    <input type="hidden" name="complaint_id" value="'.$complaintId.'">
                                    <button type="submit" name="reopenComplaint" class="btn btn-warning">Reopen Complaint</button>
                                </form>
                            ';
                        }

                        echo '</div>';
                    }
                }
            ?>
        </div>
    </div>
    <?php require "admin_footer.php"; ?>
</div>

<div id="photo-preview-modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="photo-preview-title">Complaint Image</h4>
            </div>
            <div class="modal-body text-center">
                <img id="photo-preview-image" src="" alt="" style="max-width:100%; height:auto;">
            </div>
        </div>
    </div>
</div>

<script>
$(document).on('click', '.js-photo-preview', function(event) {
    event.preventDefault();
    $('#photo-preview-image').attr('src', $(this).data('photo-src'));
    $('#photo-preview-title').text($(this).data('photo-title'));
    $('#photo-preview-modal').modal('show');
});
</script>
<?php
    }
?>
