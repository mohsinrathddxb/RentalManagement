<?php

$pgnm = 'Co- Accomodation: New Payment';
$error = ' ';
$timesnap = date('Y-m-d : H:i:s');

ob_start();

require_once "functions/db.php";
require_once "functions/tenant_helpers.php";
require_once "functions/invoice_pdf_helpers.php";
require_once "functions/telegram_helpers.php";
require_once "functions/errors.php";

session_start();

if (is_logged_in_temporary()) {
    require_admin_user();
    ensure_tenant_schema($connection);
    ensure_invoice_pdf_columns($connection);

    if (isset($_POST['newPayment'])) {
        $tenantId = isset($_POST['tenID']) ? (int) $_POST['tenID'] : 0;
        $invoiceNumber = isset($_POST['invoiceNumber']) ? uncrack($_POST['invoiceNumber']) : '';
        $amountExpectedCents = isset($_POST['amountDue']) ? money_to_cents($_POST['amountDue']) : 0;
        $amountPaidCents = isset($_POST['paidAmount']) ? money_to_cents($_POST['paidAmount']) : 0;
        $mpesaCode = isset($_POST['mpesa']) ? uncrack($_POST['mpesa']) : '';
        $comment = isset($_POST['comment']) ? uncrack($_POST['comment']) : '';

        if ($tenantId <= 0 || $invoiceNumber === '' || $amountExpectedCents < 0 || $amountPaidCents < 0) {
            header("location:new-payment.php?state=9&payment_error=missing");
            exit();
        }

        $paymentDate = date('Y-m-d');
        $timesnap = date('Y-m-d : H:i:s');
        $rawBalanceCents = $amountExpectedCents - $amountPaidCents;
        $balanceCents = max(0, $rawBalanceCents);

        $sqtenant = "SELECT `account`, `tenant_name`, `phone_number` FROM `tenants` WHERE `tenantID`='$tenantId' LIMIT 1";
        $tenquer = mysqli_query($conn, $sqtenant);
        $rec = $tenquer ? mysqli_fetch_array($tenquer, MYSQLI_BOTH) : null;

        if (!$rec) {
            header("location:new-payment.php?state=9&payment_error=tenant");
            exit();
        }

        $accountCents = isset($rec['account']) ? money_to_cents($rec['account']) : 0;
        $tenantName = $rec['tenant_name'];
        $firstName = strpos($tenantName, " ") !== false ? substr($tenantName, 0, strpos($tenantName, " ")) : $tenantName;
        $phone = $rec['phone_number'];

        if ($balanceCents === 0) {
            $status = 'paid';
            if ($rawBalanceCents < 0) {
                $accountCents += abs($rawBalanceCents);
            }
        } else {
            $status = 'partial paid';
            $accountCents += $amountPaidCents;
        }

        $amountExpected = cents_to_money($amountExpectedCents);
        $amountPaid = cents_to_money($amountPaidCents);
        $balance = cents_to_money($balanceCents);
        $accountBalance = cents_to_money($accountCents);

        $safeInvoiceNumber = mysqli_real_escape_string($connection, $invoiceNumber);
        $safeMpesaCode = mysqli_real_escape_string($connection, $mpesaCode);
        $safeComment = mysqli_real_escape_string($connection, $comment);
        $safeTenantName = mysqli_real_escape_string($connection, $tenantName);

        $sqlInv = "UPDATE `invoices` SET `amountDue`='$balance', `status`='$status' WHERE `invoiceNumber`='$safeInvoiceNumber'";
        $sqlTen = "UPDATE `tenants` SET `account`='$accountBalance' WHERE `tenantID`='$tenantId'";
        $sqlPayment = "INSERT INTO `payments`
            (`tenantID`, `invoiceNumber`, `expectedAmount`, `amountPaid`, `balance`, `mpesaCode`, `dateofPayment`, `comment`)
            VALUES ('$tenantId', '$safeInvoiceNumber', '$amountExpected', '$amountPaid', '$balance', '$safeMpesaCode', '$paymentDate', '$safeComment')";
        $sqlTransactions = "INSERT INTO `transactions` (`actor`, `time`, `description`)
            VALUES ('Admin ($username)', '$timesnap', '$username added payment of ".format_money_amount($amountPaid)." for $safeTenantName, under invoice ID: $safeInvoiceNumber')";

        $noticeMessage = 'A payment of AED ' . format_money_amount($amountPaid) . ' was received for invoice ' . $invoiceNumber . '.';
        if ($balanceCents > 0) {
            $noticeMessage .= ' Remaining amount to pay is AED ' . format_money_amount($balance) . '.';
        } else {
            $noticeMessage .= ' This invoice is now fully paid.';
        }

        $mysqli->autocommit(FALSE);
        $state = true;

        $mysqli->query($sqlInv) ? null : $state = false;
        $mysqli->query($sqlTen) ? null : $state = false;
        $mysqli->query($sqlPayment) ? null : $state = false;
        $paymentId = $mysqli->insert_id;
        $mysqli->query($sqlTransactions) ? null : $state = false;

        if ($state && $noticeMessage !== '' && $paymentId > 0) {
            $safeNoticeMessage = mysqli_real_escape_string($connection, $noticeMessage);
            $safeCreatedBy = mysqli_real_escape_string($connection, isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin');
            $invoicePdfUrl = 'invoice-pdf.php?invoice=' . rawurlencode($invoiceNumber);
            $receiptPdfUrl = 'payment-receipt-pdf.php?payment=' . (int) $paymentId;
            $safeInvoicePdfUrl = mysqli_real_escape_string($connection, $invoicePdfUrl);
            $safeReceiptPdfUrl = mysqli_real_escape_string($connection, $receiptPdfUrl);
            $noticeSubject = $balanceCents > 0 ? 'Payment Update' : 'Payment Receipt';
            $safeNoticeSubject = mysqli_real_escape_string($connection, $noticeSubject);
            $noticeSql = "INSERT INTO `tenant_notices`
                (`tenant_id`, `subject`, `message`, `sender_role`, `created_by_name`, `document_url`, `document_label`, `secondary_document_url`, `secondary_document_label`, `status`)
                VALUES
                ('$tenantId', '$safeNoticeSubject', '$safeNoticeMessage', 'Admin', '$safeCreatedBy', '$safeInvoicePdfUrl', 'Invoice PDF', '$safeReceiptPdfUrl', 'Receipt PDF', 'Published')";
            $mysqli->query($noticeSql) ? null : $state = false;
        }

        if ($state) {
            $mysqli->commit();

            $finalMessage = "Greetings " . $firstName . ", This is a confirmation that your rent payment of AED " . format_money_amount($amountPaid) . " has been received and updated.";
            if ($balanceCents > 0) {
                $finalMessage .= " Remaining balance to pay is AED " . format_money_amount($balance) . ".";
            } else {
                $finalMessage .= " Your invoice is now fully paid.";
            }
            $finalMessage .= " Thank you.";

            @sendSMS($phone, $finalMessage);
            @send_payment_receipt_to_tenant_telegram($connection, (int) $paymentId, (int) $tenantId);

            header("location:payments.php?state=8");
            exit();
        }

        $mysqli->rollback();
        header("location:new-payment.php?state=9");
        exit();
    }

    require "admin_header0.php";
    require "admin_left_panel.php";
?>

        <div id="page-wrapper">
            <div class="container-fluid">
                <div class="row bg-title">
                    <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                        <h4 class="page-title"><?php echo 'Howdy, ' . $username . '!'; ?></h4>
                    </div>
                    <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                        <ol class="breadcrumb">
                            <li><a href="index.php">Dashboard</a></li>
                            <li><a href="payments.php">Payments</a></li>
                            <li class="active">New</li>
                        </ol>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div>
                            <?php echo $error; ?>
                        </div>
                        <div class="white-box">
                            <?php if (isset($_GET['payment_error'])) { ?>
                                <div class="alert alert-danger">
                                    <strong>PAYMENT ERROR!!</strong>
                                    <p>Please select an invoice first so tenant and amount details can load.</p>
                                </div>
                            <?php } ?>

                            <h3 class="box-title m-b-0"><i class="fa fa-money fa-3x"></i> Add A New Rent Payment</h3>
                            <p class="text-muted m-b-30 font-13"> Fill in the form below: </p>
                            <div class="row">
                                <div class="col-sm-12 col-xs-12">
                                    <form action="new-payment.php" method="post" id="paymentForm">
                                        <div class="form-group">
                                            <label for="tname">Choose a Tenant: <span style="color:red">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-user"></i></div>
                                                <select required id="tname" name="invoiceNumber" class="form-control" onchange="requestInvoice(this.value);">
                                                    <option value="">**Select a tenant**</option>
                                                    <?php
                                                    $sq1 = "
                                                        SELECT
                                                            i.`invoiceNumber`,
                                                            t.`tenant_name`,
                                                            i.`tenantID`
                                                        FROM `invoices` i
                                                        LEFT JOIN `tenants` t ON i.`tenantID` = t.`tenantID`
                                                        WHERE i.`status` IN ('unpaid', 'partial paid')
                                                        ORDER BY i.`tenantID` DESC
                                                    ";
                                                    $rec = mysqli_query($conn, $sq1);
                                                    while ($row = mysqli_fetch_array($rec, MYSQLI_BOTH)) {
                                                        $tenant = $row['tenant_name'];
                                                        $invoiceId = $row['invoiceNumber'];
                                                        echo "<option value='$invoiceId'> $tenant ($invoiceId) </option> ";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div id="txtInvoice"></div>

                                        <div class="form-group">
                                            <label for="paidAmount">Amount Paid: <span style="color:red">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-usd"></i></div>
                                                <input type="number" required min="0" step="0.01" name="paidAmount" class="form-control" id="paidAmount" placeholder="Enter Amount">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="mpesa">MPESA CODE (Optional): </label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-usd"></i></div>
                                                <input type="text" name="mpesa" class="form-control" id="mpesa" placeholder="Mpesa code e.g. XX00XXYY" style="background-color: #000; color: #fff; font-weight: 700; text-transform: uppercase;">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="comment">Comment: * </label>
                                            <div class="input-group">
                                                <div class="input-group-addon"><i class="fa fa-pencil"></i></div>
                                                <textarea required name="comment" id="comment" cols="6" placeholder="e.g. This is the rent for Jan 2021" style="width:100%;"></textarea>
                                            </div>
                                        </div>

                                        <button type="submit" name="newPayment" class="btn btn-success btn-lg waves-effect waves-light m-r-10 center"><i class="fa fa-plus-circle fa-lg"></i> Update this Payment</button>
                                    </form>
                                </div>
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
            </div>
            <footer class="footer text-center"> 2018 &copy; Company Admin </footer>
        </div>
    </div>

    <script src="../plugins/bower_components/jquery/dist/jquery.min.js"></script>
    <script src="bootstrap/dist/js/tether.min.js"></script>
    <script src="bootstrap/dist/js/bootstrap.min.js"></script>
    <script src="../plugins/bower_components/bootstrap-extension/js/bootstrap-extension.min.js"></script>
    <script src="../plugins/bower_components/sidebar-nav/dist/sidebar-nav.min.js"></script>
    <script src="js/jquery.slimscroll.js"></script>
    <script src="js/waves.js"></script>
    <script src="js/custom.min.js"></script>
    <script src="js/jasny-bootstrap.js"></script>
    <script src="../plugins/bower_components/styleswitcher/jQuery.style.switcher.js"></script>

    <script type="text/javascript">
    function requestInvoice(str)
    {
        if (str === "") {
            document.getElementById("txtInvoice").innerHTML = "";
            return;
        }

        var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange = function() {
            if (this.readyState === 4) {
                if (this.status === 200) {
                    document.getElementById("txtInvoice").innerHTML = this.responseText;
                } else {
                    document.getElementById("txtInvoice").innerHTML = '<div class="alert alert-danger">Could not load invoice details.</div>';
                }
            }
        };
        xmlhttp.open("GET", "functions/request_invoice.php?q=" + encodeURIComponent(str), true);
        xmlhttp.send();
    }

    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        var invoiceNumber = document.getElementById('tname').value;
        var tenantIdField = document.querySelector('input[name="tenID"]');
        var amountDueField = document.querySelector('input[name="amountDue"]');

        if (!invoiceNumber || !tenantIdField || !amountDueField) {
            alert('Please select an invoice first and wait for its details to load.');
            e.preventDefault();
        }
    });
    </script>

</body>
</html>
<?php
} else {
    header('location:../index.php');
}
?>
