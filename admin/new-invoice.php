<?php 

$pgnm = 'Co- Accomodation: New Invoice';
$error = ' ';
$timesnap = date('Y-m-d : H:i:s');

$defaultInvoiceMonth = date('Y-m');

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

    if (isset($_POST['addInvoice'])) {
        $tenantIdRent = isset($_POST['tname']) ? uncrack($_POST['tname']) : '';
        $invoiceDueDate = isset($_POST['ddate']) ? uncrack($_POST['ddate']) : '';
        $comment = isset($_POST['comment']) ? uncrack($_POST['comment']) : '';
        $invoiceMonth = isset($_POST['invoice_month']) ? uncrack($_POST['invoice_month']) : $defaultInvoiceMonth;
        $bookingAmountCents = isset($_POST['booking_amount']) ? money_to_cents($_POST['booking_amount']) : 0;
        $depositAmountCents = isset($_POST['deposit_amount']) ? money_to_cents($_POST['deposit_amount']) : 0;

        if ($tenantIdRent === '' || strpos($tenantIdRent, '_') === false || $invoiceMonth === '' || $bookingAmountCents < 0 || $depositAmountCents < 0) {
            header('location:new-invoice.php?state=6&invoice_error=missing');
            exit();
        }

        $invoiceDate = $invoiceMonth . '-01';
        $invoiceid = 'INV' . date('YmdHis');
        $tenantId = substr($tenantIdRent, 0, strpos($tenantIdRent, '_'));
        $rentAmountCents = money_to_cents(substr($tenantIdRent, strpos($tenantIdRent, '_') + 1));
        $istatus = 'unpaid';

        $sqt = "SELECT `tenant_name`,`phone_number`,`account` FROM `tenants` WHERE `tenantID`='$tenantId' LIMIT 1";
        $queryt = mysqli_query($conn, $sqt);
        $ten_record = $queryt ? mysqli_fetch_array($queryt, MYSQLI_BOTH) : null;

        if (!$ten_record) {
            header('location:new-invoice.php?state=6&invoice_error=tenant');
            exit();
        }

        $tenantName = $ten_record['tenant_name'];
        $firstName = strpos($tenantName, ' ') !== false ? substr($tenantName, 0, strpos($tenantName, ' ')) : $tenantName;
        $phone = $ten_record['phone_number'];
        $accountCents = isset($ten_record['account']) ? money_to_cents($ten_record['account']) : 0;

        $totalAmountCents = $rentAmountCents + $bookingAmountCents + $depositAmountCents;
        $creditAppliedCents = min($accountCents, $totalAmountCents);
        $amountDueCents = max(0, $totalAmountCents - $creditAppliedCents);
        $remainingAccountCents = max(0, $accountCents - $creditAppliedCents);

        if ($amountDueCents === 0) {
            $istatus = 'paid';
        }

        $rentAmount = cents_to_money($rentAmountCents);
        $bookingAmount = cents_to_money($bookingAmountCents);
        $depositAmount = cents_to_money($depositAmountCents);
        $creditApplied = cents_to_money($creditAppliedCents);
        $totalAmount = cents_to_money($totalAmountCents);
        $amountDue = cents_to_money($amountDueCents);
        $remainingAccount = cents_to_money($remainingAccountCents);

        $sqInvoice = "INSERT INTO `invoices`
            (`invoiceNumber`,`tenantID`,`dateOfInvoice`,`dateDue`,`amountDue`,`rent_amount`,`booking_amount`,`deposit_amount`,`credit_applied`,`total_amount`,`comment`,`status`)
            VALUES
            ('$invoiceid','$tenantId','$invoiceDate','$invoiceDueDate','$amountDue','$rentAmount','$bookingAmount','$depositAmount','$creditApplied','$totalAmount','$comment','$istatus')";

        $sq_account = "UPDATE `tenants` SET `account`='$remainingAccount' WHERE `tenantID`='$tenantId'";

        $sql_transactions = "INSERT INTO `transactions` (`actor`,`time`,`description`)
            VALUES ('Admin ($username)', '$timesnap','$username added a new rental invoice ($invoiceid) for tenant ($tenantName) for month $invoiceMonth at $timesnap.')";

        $sqcheck = "SELECT * FROM `invoices` WHERE `tenantID`='$tenantId' AND `dateOfInvoice` LIKE '$invoiceMonth%'";
        $query_verify = mysqli_query($conn, $sqcheck);

        if ($query_verify && mysqli_num_rows($query_verify) < 1) {
            $mysqli->autocommit(FALSE);
            $status = true;

            $mysqli->query($sqInvoice) ? null : $status = false;
            $mysqli->query($sq_account) ? null : $status = false;
            $mysqli->query($sql_transactions) ? null : $status = false;

            if ($status) {
                $mysqli->commit();

                $finalmessage = "Greetings ".$firstName.", This is a reminder that invoice ".$invoiceid." for ".$invoiceMonth." has been issued. Total due is AED ".format_money_amount($amountDue)." by date ".$invoiceDueDate.".";
                @sendSMS($phone, $finalmessage);
                @send_invoice_to_tenant_telegram($connection, $invoiceid, (int) $tenantId);

                header('location:invoices.php?state=5');
                exit();
            } else {
                $mysqli->rollback();
                header('location:new-invoice.php?state=6');
                exit();
            }
        } else {
            header('location:new-invoice.php?state=7&invoice_month='.urlencode($invoiceMonth));
            exit();
        }
    }

    require "admin_header0.php";
    require "admin_left_panel.php";
?>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title"><?php echo 'Hey '.$username.'!';?></h4>
            </div>
            <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12"> 
                <ol class="breadcrumb">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="invoices.php">Invoices</a></li>
                    <li class="active">New</li>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div><?php echo $error; ?></div>
                <div class="white-box">
                    <?php if (isset($_GET['state']) && $_GET['state'] == 7) { ?>
                        <div class="alert alert-warning">
                            <strong>DUPLICATE INVOICE!!</strong>
                            <p>An invoice already exists for this tenant for month <?php echo htmlspecialchars(isset($_GET['invoice_month']) ? $_GET['invoice_month'] : $defaultInvoiceMonth, ENT_QUOTES, 'UTF-8'); ?>.</p>
                        </div>
                    <?php } elseif (isset($_GET['invoice_error'])) { ?>
                        <div class="alert alert-danger">
                            <strong>INVOICE ERROR!!</strong>
                            <p>Please select a tenant and invoice month, then try again.</p>
                        </div>
                    <?php } ?>

                    <h3 class="box-title m-b-0"><i class="fa fa-credit-card fa-3x"></i> Add A New Rental Invoice</h3>
                    <p class="text-muted m-b-30 font-13"> Fill in the form below: </p>

                    <div class="row">
                        <div class="col-sm-12 col-xs-12">
                            <form action="new-invoice.php" method="post">
                                <div class="form-group">
                                    <label for="tname">Choose a Tenant: *</label>
                                    <div class="input-group">
                                        <div class="input-group-addon"><i class="fa fa-user"></i></div>
                                        <select required id="tname" name="tname" class="form-control">
                                            <option value="">**Select a tenant**</option>
                                            <?php
                                            $sq1 = "
                                                SELECT
                                                    t.`tenant_name`,
                                                    t.`tenantID`,
                                                    COALESCE(hp.`rent_amount`, h.`rent_amount`) AS `rent_amount`
                                                FROM `tenants` t
                                                LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
                                                LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
                                                WHERE t.`tenant_status`='Active'
                                                ORDER BY t.`tenant_name` ASC, t.`tenantID` DESC
                                            ";
                                            $rec = mysqli_query($conn, $sq1);
                                            while ($row = mysqli_fetch_array($rec, MYSQLI_BOTH)) {
                                                $tenant = $row['tenant_name'];
                                                $tenid = $row['tenantID'].'_'.$row['rent_amount'];
                                                echo "<option value=\"$tenid\"> $tenant </option> ";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="invoice_month">Invoice Month: *</label>
                                    <div class="input-group">
                                        <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
                                        <input type="month" name="invoice_month" class="form-control" id="invoice_month" value="<?php echo htmlspecialchars(isset($_GET['invoice_month']) ? $_GET['invoice_month'] : $defaultInvoiceMonth, ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="ddate">Invoice Due Date:</label>
                                    <div class="input-group">
                                        <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
                                        <input type="date" name="ddate" class="form-control" id="ddate" placeholder="Choose Date">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="booking_amount">Booking Amount:</label>
                                    <div class="input-group">
                                        <div class="input-group-addon"><i class="fa fa-tag"></i></div>
                                        <input type="number" min="0" step="0.01" name="booking_amount" class="form-control" id="booking_amount" value="0" placeholder="Enter optional booking amount">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="deposit_amount">Security Deposit:</label>
                                    <div class="input-group">
                                        <div class="input-group-addon"><i class="fa fa-lock"></i></div>
                                        <input type="number" min="0" step="0.01" name="deposit_amount" class="form-control" id="deposit_amount" value="0" placeholder="Enter optional deposit amount">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="comment">Comment: *</label>
                                    <div class="input-group">
                                        <div class="input-group-addon"><i class="fa fa-pencil"></i></div>
                                        <textarea name="comment" id="comment" cols="6" placeholder="e.g. this is the rent for Jun 2026" style="width:100%;">This is the rent invoice for this month</textarea>
                                    </div>
                                </div>

                                <button type="submit" name="addInvoice" class="btn btn-success btn-lg waves-effect waves-light m-r-10 center">
                                    <i class="fa fa-plus-circle fa-lg"></i> Add Invoice
                                </button>
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
                        <li><div class="checkbox checkbox-info"><input id="checkbox1" type="checkbox" class="fxhdr"><label for="checkbox1"> Fix Header </label></div></li>
                        <li><div class="checkbox checkbox-warning"><input id="checkbox2" type="checkbox" checked="" class="fxsdr"><label for="checkbox2"> Fix Sidebar </label></div></li>
                        <li><div class="checkbox checkbox-success"><input id="checkbox4" type="checkbox" class="open-close"><label for="checkbox4"> Toggle Sidebar </label></div></li>
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

</body>
</html>
<?php
} else {
    header('location:../index.php');
}
?>
