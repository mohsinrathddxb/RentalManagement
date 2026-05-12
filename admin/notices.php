<?php
    $pgnm="Co- Accomodation: Notices";
    $error=' ';

    require_once "functions/errors.php";

    ob_start();
    require_once "functions/db.php";
    require_once "functions/tenant_helpers.php";

    session_start();

    if(!isset($_SESSION['email']) || empty($_SESSION['email'])){
      header("location: login.php");
      exit;
    }

    if (is_logged_in_temporary()) {
        ensure_tenant_schema($connection);
        $canManageNotices = is_admin_user();
        $currentTenant = get_logged_in_tenant_record();

        if (!$canManageNotices && !$currentTenant) {
            header("location:index.php?restricted=1");
            exit;
        }

        if (isset($_POST['sendNotice']) && !$canManageNotices) {
            $tenantId = (int) $currentTenant['tenantID'];
            $subject = uncrack($_POST['subject']);
            $message = uncrack($_POST['message']);
            if ($subject !== '' && $message !== '') {
                $safeSubject = mysqli_real_escape_string($connection, $subject);
                $safeMessage = mysqli_real_escape_string($connection, $message);
                mysqli_query($connection, "INSERT INTO `tenant_notices` (`tenant_id`, `subject`, `message`, `sender_role`, `created_by_name`, `status`) VALUES ('$tenantId', '$safeSubject', '$safeMessage', 'Tenant', NULL, 'Open')");
                header('location:notices.php?sent=1');
                exit;
            }
        }

        if (isset($_POST['createNotice']) && $canManageNotices) {
            $recipientTenant = isset($_POST['recipient_tenant']) ? trim($_POST['recipient_tenant']) : '';
            $subject = uncrack($_POST['subject']);
            $message = uncrack($_POST['message']);
            $createdByName = isset($_SESSION['name']) ? is_username($_SESSION['name']) : 'Admin';

            if ($subject !== '' && $message !== '') {
                $safeSubject = mysqli_real_escape_string($connection, $subject);
                $safeMessage = mysqli_real_escape_string($connection, $message);
                $safeCreatedByName = mysqli_real_escape_string($connection, $createdByName);

                if ($recipientTenant === 'all') {
                    $tenantRows = mysqli_query($connection, "SELECT `tenantID` FROM `tenants` WHERE `tenant_status`='Active' AND `email` <> '' ORDER BY `tenantID` DESC");
                    if ($tenantRows) {
                        while ($tenantRow = mysqli_fetch_assoc($tenantRows)) {
                            $tenantId = (int) $tenantRow['tenantID'];
                            mysqli_query($connection, "INSERT INTO `tenant_notices` (`tenant_id`, `subject`, `message`, `sender_role`, `created_by_name`, `status`) VALUES ('$tenantId', '$safeSubject', '$safeMessage', 'Admin', '$safeCreatedByName', 'Published')");
                        }
                    }
                } else {
                    $tenantId = (int) $recipientTenant;
                    if ($tenantId > 0) {
                        mysqli_query($connection, "INSERT INTO `tenant_notices` (`tenant_id`, `subject`, `message`, `sender_role`, `created_by_name`, `status`) VALUES ('$tenantId', '$safeSubject', '$safeMessage', 'Admin', '$safeCreatedByName', 'Published')");
                    }
                }

                header('location:notices.php?created=1');
                exit;
            }
        }

        if (isset($_POST['replyNotice']) && $canManageNotices) {
            $noticeId = (int) $_POST['notice_id'];
            $status = uncrack($_POST['status']);
            $reply = uncrack($_POST['admin_reply']);
            $allowed = ['Open', 'Replied', 'Closed', 'Published'];
            if (!in_array($status, $allowed, true)) {
                $status = 'Open';
            }
            $safeReply = mysqli_real_escape_string($connection, $reply);
            mysqli_query($connection, "UPDATE `tenant_notices` SET `status`='$status', `admin_reply`='$safeReply' WHERE `notice_id`='$noticeId'");
            header('location:notices.php?updated=1');
            exit;
        }

        if ($canManageNotices) {
            $sql = "
                SELECT tn.*, t.tenant_name, t.email
                FROM `tenant_notices` tn
                LEFT JOIN `tenants` t ON tn.`tenant_id` = t.`tenantID`
                ORDER BY tn.`updated_at` DESC
            ";
            $tenantRecipients = mysqli_query($connection, "SELECT `tenantID`, `tenant_name`, `email` FROM `tenants` WHERE `tenant_status`='Active' AND `email` <> '' ORDER BY `tenant_name` ASC");
        } else {
            $tenantId = (int) $currentTenant['tenantID'];
            $sql = "SELECT * FROM `tenant_notices` WHERE `tenant_id`='$tenantId' ORDER BY `updated_at` DESC";
        }
        $notices = mysqli_query($connection, $sql);

        require "admin_header0.php";
        require "admin_left_panel.php";
?>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title"><?php echo $canManageNotices ? 'Tenant Notices' : 'Send Notice'; ?></h4>
            </div>
            <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                <ol class="breadcrumb">
                    <li><a href="index.php">Dashboard</a></li>
                    <li class="active">Notices</li>
                </ol>
            </div>
        </div>

        <?php if (isset($_GET['sent'])) { ?>
            <div class="alert alert-success"><strong>SENT!!</strong> Your notice has been sent.</div>
        <?php } elseif (isset($_GET['created'])) { ?>
            <div class="alert alert-success"><strong>CREATED!!</strong> Notice created for tenant(s) successfully.</div>
        <?php } elseif (isset($_GET['updated'])) { ?>
            <div class="alert alert-success"><strong>UPDATED!!</strong> Notice response saved.</div>
        <?php } ?>

        <?php if ($canManageNotices) { ?>
        <div class="white-box">
            <h3 class="box-title">Create Notice For Tenant</h3>
            <form action="notices.php" method="post">
                <div class="row">
                    <div class="form-group col-md-4">
                        <label>Recipient: *</label>
                        <select name="recipient_tenant" class="form-control" required>
                            <option value="">Select tenant</option>
                            <option value="all">All Active Tenants</option>
                            <?php
                                if ($tenantRecipients) {
                                    while ($tenant = mysqli_fetch_assoc($tenantRecipients)) {
                                        echo '<option value="'.(int) $tenant['tenantID'].'">'.htmlspecialchars($tenant['tenant_name'], ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars($tenant['email'], ENT_QUOTES, 'UTF-8').')</option>';
                                    }
                                }
                            ?>
                        </select>
                    </div>
                    <div class="form-group col-md-8">
                        <label>Subject: *</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label>Message: *</label>
                        <textarea name="message" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="col-md-12">
                        <button type="submit" name="createNotice" class="btn btn-success"><i class="fa fa-plus-circle"></i> Create Notice</button>
                    </div>
                </div>
            </form>
        </div>
        <?php } else { ?>
        <div class="white-box">
            <h3 class="box-title">Send Notice To Admin</h3>
            <form action="notices.php" method="post">
                <div class="row">
                    <div class="form-group col-md-6">
                        <label>Subject: *</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label>Message: *</label>
                        <textarea name="message" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="col-md-12">
                        <button type="submit" name="sendNotice" class="btn btn-success"><i class="fa fa-paper-plane"></i> Send Notice</button>
                    </div>
                </div>
            </form>
        </div>
        <?php } ?>

        <div class="white-box">
            <h3 class="box-title"><?php echo $canManageNotices ? 'Notice Inbox' : 'My Sent Notices'; ?></h3>
            <?php
            if (!$notices || mysqli_num_rows($notices) === 0) {
                echo '<i style="color:brown;">No notices yet.</i>';
            } else {
                while ($row = mysqli_fetch_assoc($notices)) {
                    $noticeId = (int) $row['notice_id'];
                    $subject = htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8');
                    $message = nl2br(htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'));
                    $status = htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8');
                    $reply = htmlspecialchars((string) $row['admin_reply'], ENT_QUOTES, 'UTF-8');
                    $senderRole = isset($row['sender_role']) ? htmlspecialchars($row['sender_role'], ENT_QUOTES, 'UTF-8') : 'Tenant';
                    $createdByName = htmlspecialchars((string) ($row['created_by_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    echo '
                        <div style="border:1px solid #e4e7ea; padding:15px; margin-bottom:15px;">
                            <h4>'.$subject.' <span class="label label-info" style="margin-left:8px;">'.$status.'</span></h4>
                            '.($canManageNotices ? '<p><strong>Direction:</strong> '.($senderRole === 'Admin' ? 'Admin to Tenant' : 'Tenant to Admin').'</p><p><strong>Tenant:</strong> '.htmlspecialchars((string) $row['tenant_name'], ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8').')</p>' : '<p><strong>From:</strong> '.($senderRole === 'Admin' ? ($createdByName !== '' ? $createdByName : 'Admin') : 'You').'</p>').'
                            <p><strong>Message:</strong><br>'.$message.'</p>
                            '.($reply !== '' ? '<p><strong>Admin Reply:</strong> '.$reply.'</p>' : '').'
                            <p><strong>Updated:</strong> '.htmlspecialchars($row['updated_at'], ENT_QUOTES, 'UTF-8').'</p>
                    ';

                    if ($canManageNotices && $senderRole !== 'Admin') {
                        echo '
                            <form action="notices.php" method="post">
                                <input type="hidden" name="notice_id" value="'.$noticeId.'">
                                <div class="row">
                                    <div class="form-group col-md-3">
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            <option value="'.$status.'" selected>'.$status.'</option>
                                            <option value="Open">Open</option>
                                            <option value="Replied">Replied</option>
                                            <option value="Closed">Closed</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-7">
                                        <label>Reply</label>
                                        <input type="text" name="admin_reply" class="form-control" value="'.$reply.'" placeholder="Reply to tenant">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label>&nbsp;</label>
                                        <button type="submit" name="replyNotice" class="btn btn-success btn-block">Save</button>
                                    </div>
                                </div>
                            </form>
                        ';
                    } elseif ($canManageNotices && $senderRole === 'Admin') {
                        echo '
                            <form action="notices.php" method="post">
                                <input type="hidden" name="notice_id" value="'.$noticeId.'">
                                <div class="row">
                                    <div class="form-group col-md-3">
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            <option value="'.$status.'" selected>'.$status.'</option>
                                            <option value="Published">Published</option>
                                            <option value="Closed">Closed</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-7">
                                        <label>Admin Note</label>
                                        <input type="text" name="admin_reply" class="form-control" value="'.$reply.'" placeholder="Optional internal note or update">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label>&nbsp;</label>
                                        <button type="submit" name="replyNotice" class="btn btn-success btn-block">Save</button>
                                    </div>
                                </div>
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
<?php
    }
?>
