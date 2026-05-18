<?php
$pgnm = "Co- Accomodation: Notices";
$error = ' ';

require_once "functions/errors.php";

ob_start();
require_once "functions/db.php";
require_once "functions/tenant_helpers.php";
require_once "functions/partition_helpers.php";

session_start();

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header("location: login.php");
    exit;
}

if (is_logged_in_temporary()) {
    ensure_partition_tables($connection);
    ensure_tenant_schema($connection);
    ensure_missing_tenant_user_accounts($connection);

    $canManageNotices = is_admin_user();
    $currentTenant = get_logged_in_tenant_record();
    $selectedTenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : 0;

    if (!$canManageNotices && !$currentTenant) {
        header("location:index.php?restricted=1");
        exit;
    }

    if (!$canManageNotices) {
        $selectedTenantId = (int) $currentTenant['tenantID'];
    }

    if (isset($_POST['sendNotice']) && !$canManageNotices) {
        $tenantId = (int) $currentTenant['tenantID'];
        $subject = uncrack($_POST['subject']);
        $message = uncrack($_POST['message']);

        if ($subject !== '' && $message !== '') {
            $safeSubject = mysqli_real_escape_string($connection, $subject);
            $safeMessage = mysqli_real_escape_string($connection, $message);

            mysqli_query($connection, "
                INSERT INTO `tenant_notices`
                (`tenant_id`, `subject`, `message`, `sender_role`, `created_by_name`, `status`)
                VALUES
                ('$tenantId', '$safeSubject', '$safeMessage', 'Tenant', NULL, 'Open')
            ");

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
                $tenantRows = mysqli_query($connection, "
                    SELECT `tenantID`
                    FROM `tenants`
                    WHERE `tenant_status`='Active'
                    ORDER BY `tenantID` DESC
                ");

                if ($tenantRows) {
                    while ($tenantRow = mysqli_fetch_assoc($tenantRows)) {
                        $tenantId = (int) $tenantRow['tenantID'];
                        mysqli_query($connection, "
                            INSERT INTO `tenant_notices`
                            (`tenant_id`, `subject`, `message`, `sender_role`, `created_by_name`, `status`)
                            VALUES
                            ('$tenantId', '$safeSubject', '$safeMessage', 'Admin', '$safeCreatedByName', 'Published')
                        ");
                    }
                }
            } else {
                $tenantId = (int) $recipientTenant;
                if ($tenantId > 0) {
                    mysqli_query($connection, "
                        INSERT INTO `tenant_notices`
                        (`tenant_id`, `subject`, `message`, `sender_role`, `created_by_name`, `status`)
                        VALUES
                        ('$tenantId', '$safeSubject', '$safeMessage', 'Admin', '$safeCreatedByName', 'Published')
                    ");
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
            SELECT
                tn.*,
                t.tenant_name,
                t.email,
                h.house_name,
                hp.partition_number
            FROM `tenant_notices` tn
            LEFT JOIN `tenants` t ON tn.`tenant_id` = t.`tenantID`
            LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
            LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
            ORDER BY COALESCE(tn.`updated_at`, tn.`created_at`) DESC, tn.`notice_id` DESC
        ";

        $tenantRecipients = mysqli_query($connection, "
            SELECT
                t.`tenantID`,
                t.`tenant_name`,
                t.`email`,
                h.`house_name`,
                hp.`partition_number`
            FROM `tenants` t
            LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
            LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
            WHERE t.`tenant_status`='Active'
            ORDER BY t.`tenant_name` ASC, t.`tenantID` DESC
        ");
    } else {
        $tenantId = (int) $currentTenant['tenantID'];
        $sql = "SELECT * FROM `tenant_notices` WHERE `tenant_id`='$tenantId' ORDER BY COALESCE(`updated_at`, `created_at`) DESC, `notice_id` DESC";
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

        <style>
            .notices-page .white-box {
                padding: 24px;
            }
            .create-notice-row {
                display: grid;
                grid-template-columns: 460px minmax(560px, 1fr);
                gap: 16px;
                align-items: end;
            }
            .create-recipient-field,
            .create-subject-field {
                width: 100%;
                min-width: 0;
            }
            .create-recipient-field {
                min-width: 280px;
            }
            .create-recipient-field select.form-control {
                min-width: 280px;
            }
            .create-notice-row .form-control {
                width: 100% !important;
                height: 48px !important;
                min-height: 48px !important;
                font-size: 16px !important;
            }
            .create-notice-row textarea.form-control {
                min-height: 110px !important;
                height: 110px !important;
                resize: vertical;
            }
            .create-notice-row .btn.btn-success {
                min-height: 48px;
                padding: 10px 18px;
                font-size: 15px;
            }
            .notice-edit-row {
                display: grid;
                grid-template-columns: 240px minmax(320px, 1fr) 170px;
                gap: 12px;
                align-items: flex-end;
            }
            .notice-edit-field {
                min-width: 0;
            }
            .notice-edit-actions {
                width: 170px;
            }
            .notice-edit-field .form-control {
                width: 100% !important;
                height: 44px !important;
                min-height: 44px !important;
                padding-top: 10px !important;
                padding-bottom: 10px !important;
                line-height: 1.2 !important;
            }
            @media (max-width: 991px) {
                .create-notice-row {
                    grid-template-columns: 1fr;
                }
                .notice-edit-row {
                    grid-template-columns: 1fr;
                }
                .notice-edit-actions {
                    width: 100%;
                }
            }
            @media (max-width: 767px) {
                .notices-page .white-box {
                    padding: 16px 14px;
                }
                .notices-page .box-title {
                    line-height: 1.35;
                }
                .create-notice-row .btn.btn-success,
                .notice-edit-actions .btn {
                    width: 100%;
                }
            }
        </style>

        <?php if ($canManageNotices) { ?>
        <div class="white-box notices-page">
            <h3 class="box-title">Create Notice For Tenant</h3>
            <form action="notices.php" method="post">
                <div class="row create-notice-row">
                    <div class="form-group col-md-4 create-recipient-field">
                        <label>Recipient: *</label>
                        <select name="recipient_tenant" class="form-control" required>
                            <option value="">Select tenant</option>
                            <option value="all"<?php echo $selectedTenantId === 0 ? ' selected' : ''; ?>>All Active Tenants</option>
                            <?php
                            if ($tenantRecipients) {
                                while ($tenant = mysqli_fetch_assoc($tenantRecipients)) {
                                    $tenantIdOption = (int) $tenant['tenantID'];
                                    $selectedAttr = $selectedTenantId === $tenantIdOption ? ' selected' : '';
                                    $tenantLabel = htmlspecialchars($tenant['tenant_name'], ENT_QUOTES, 'UTF-8');
                                    $emailLabel = trim((string) $tenant['email']) !== '' ? ' (' . htmlspecialchars($tenant['email'], ENT_QUOTES, 'UTF-8') . ')' : '';
                                    $houseLabel = trim((string) $tenant['house_name']) !== '' ? ' - ' . htmlspecialchars($tenant['house_name'], ENT_QUOTES, 'UTF-8') : '';
                                    $partitionLabel = trim((string) $tenant['partition_number']) !== '' ? ' / ' . htmlspecialchars($tenant['partition_number'], ENT_QUOTES, 'UTF-8') : '';

                                    echo '<option value="' . $tenantIdOption . '"' . $selectedAttr . '>' . $tenantLabel . $emailLabel . $houseLabel . $partitionLabel . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group col-md-8 create-subject-field">
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
        <div class="white-box notices-page">
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

        <div class="white-box notices-page">
            <h3 class="box-title"><?php echo $canManageNotices ? 'Notice Inbox' : 'My Sent Notices'; ?></h3>
            <?php
            if (!$notices || mysqli_num_rows($notices) === 0) {
                echo '<i style="color:brown;">No notices yet.</i>';
            } else {
                echo '<div class="notice-list-wrap">';
                while ($row = mysqli_fetch_assoc($notices)) {
                    $noticeId = (int) $row['notice_id'];
                    $subject = htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8');
                    $message = nl2br(htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'));
                    $status = htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8');
                    $reply = htmlspecialchars((string) $row['admin_reply'], ENT_QUOTES, 'UTF-8');
                    $senderRole = isset($row['sender_role']) ? htmlspecialchars($row['sender_role'], ENT_QUOTES, 'UTF-8') : 'Tenant';
                    $createdByName = htmlspecialchars((string) ($row['created_by_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $documentUrl = trim((string) ($row['document_url'] ?? ''));
                    $documentLabel = trim((string) ($row['document_label'] ?? ''));
                    $secondaryDocumentUrl = trim((string) ($row['secondary_document_url'] ?? ''));
                    $secondaryDocumentLabel = trim((string) ($row['secondary_document_label'] ?? ''));
                    $documentsHtml = '';

                    if ($documentUrl !== '' && $documentLabel !== '') {
                        $documentsHtml .= '<a class="btn btn-xs btn-info" href="' . htmlspecialchars($documentUrl, ENT_QUOTES, 'UTF-8') . '">'
                            . htmlspecialchars($documentLabel, ENT_QUOTES, 'UTF-8') . '</a> ';
                    }

                    if ($secondaryDocumentUrl !== '' && $secondaryDocumentLabel !== '') {
                        $documentsHtml .= '<a class="btn btn-xs btn-success" href="' . htmlspecialchars($secondaryDocumentUrl, ENT_QUOTES, 'UTF-8') . '">'
                            . htmlspecialchars($secondaryDocumentLabel, ENT_QUOTES, 'UTF-8') . '</a>';
                    }

                    echo '
                        <div style="border:1px solid #e4e7ea; border-radius:8px; padding:12px 14px; margin-bottom:12px;">
                            <div class="row" style="margin-bottom:8px;">
                                <div class="col-md-7 col-sm-7 col-xs-12">
                                    <h4 style="margin:0;">'.$subject.' <span class="label label-info" style="margin-left:8px;">'.$status.'</span></h4>
                                </div>
                                <div class="col-md-5 col-sm-5 col-xs-12 text-right">
                                    <strong>Updated:</strong> '.htmlspecialchars((string) $row['updated_at'], ENT_QUOTES, 'UTF-8').'
                                </div>
                            </div>
                            <div class="row" style="margin-bottom:8px;">
                                <div class="col-md-12">
                                    '.($canManageNotices
                                        ? '<strong>Direction:</strong> '.($senderRole === 'Admin' ? 'Admin to Tenant' : 'Tenant to Admin')
                                          .' &nbsp; | &nbsp; <strong>Tenant:</strong> '.htmlspecialchars((string) $row['tenant_name'], ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8').')'
                                          .' &nbsp; | &nbsp; <strong>Stay:</strong> '.htmlspecialchars((string) $row['house_name'], ENT_QUOTES, 'UTF-8').' / '.htmlspecialchars((string) $row['partition_number'], ENT_QUOTES, 'UTF-8')
                                        : '<strong>From:</strong> '.($senderRole === 'Admin' ? ($createdByName !== '' ? $createdByName : 'Admin') : 'You')).'
                                </div>
                            </div>
                            <div style="margin-bottom:8px;"><strong>Message:</strong> '.$message.'</div>
                            '.($documentsHtml !== '' ? '<div style="margin-bottom:8px;"><strong>Documents:</strong> '.$documentsHtml.'</div>' : '').'
                            '.($reply !== '' ? '<div style="margin-bottom:8px;"><strong>Admin Reply:</strong> '.$reply.'</div>' : '').'
                    ';

                    if ($canManageNotices && $senderRole !== 'Admin') {
                        echo '
                            <form action="notices.php" method="post">
                                <input type="hidden" name="notice_id" value="'.$noticeId.'">
                                <div class="notice-edit-row">
                                    <div class="form-group notice-edit-field">
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            <option value="'.$status.'" selected>'.$status.'</option>
                                            <option value="Open">Open</option>
                                            <option value="Replied">Replied</option>
                                            <option value="Closed">Closed</option>
                                        </select>
                                    </div>
                                    <div class="form-group notice-edit-field">
                                        <label>Reply</label>
                                        <input type="text" name="admin_reply" class="form-control" value="'.$reply.'" placeholder="Reply to tenant">
                                    </div>
                                    <div class="form-group notice-edit-actions">
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
                                <div class="notice-edit-row">
                                    <div class="form-group notice-edit-field">
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            <option value="'.$status.'" selected>'.$status.'</option>
                                            <option value="Published">Published</option>
                                            <option value="Closed">Closed</option>
                                        </select>
                                    </div>
                                    <div class="form-group notice-edit-field">
                                        <label>Admin Note</label>
                                        <input type="text" name="admin_reply" class="form-control" value="'.$reply.'" placeholder="Optional internal note or update">
                                    </div>
                                    <div class="form-group notice-edit-actions">
                                        <label>&nbsp;</label>
                                        <button type="submit" name="replyNotice" class="btn btn-success btn-block">Save</button>
                                    </div>
                                </div>
                            </form>
                        ';
                    }

                    echo '</div>';
                }
                echo '</div>';
            }
            ?>
        </div>
    </div>
    <?php require "admin_footer.php"; ?>
</div>
<?php
}
?>
