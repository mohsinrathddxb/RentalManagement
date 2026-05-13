<?php
$pgnm="Co- Accomodation: View Payments";
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
    require_admin_user();
    ensure_tenant_schema($connection);

    $sql = "
        SELECT
            p.`paymentID`,
            p.`tenantID`,
            t.`tenant_name`,
            h.`house_name`,
            p.`invoiceNumber`,
            p.`expectedAmount`,
            p.`amountPaid`,
            p.`balance`,
            p.`mpesaCode`,
            p.`dateofPayment`,
            p.`comment`
        FROM `payments` p
        LEFT JOIN `tenants` t ON p.`tenantID` = t.`tenantID`
        LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
        ORDER BY p.`paymentID` DESC
    ";

    $query = mysqli_query($connection, $sql);

    require "admin_header0.php";
    require "admin_left_panel.php";
?>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title"> Hello <?php echo $username;?>,</h4>
            </div>
            <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12"> 
                <ol class="breadcrumb">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="#" class="active">Payments</a></li>
                    <li><a href="new-payment.php">New</a></li>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="white-box">
                    <?php
                    echo $error;

                    if (isset($_GET["state"]) && $_GET["state"] == 8) {
                        echo '<div class="alert alert-success"><a href="#" class="close" data-dismiss="alert" aria-label="close"></a><strong>DONE!! </strong><p>The payment has been updated successfully.</p></div>';
                    } elseif (isset($_GET["deleted"])) {
                        echo '<div class="alert alert-warning"><a href="#" class="close" data-dismiss="alert" aria-label="close"></a><strong>DELETED!! </strong><p>The payment has been successfully deleted.</p></div>';
                    } elseif (isset($_GET["del_error"])) {
                        echo '<div class="alert alert-danger"><a href="#" class="close" data-dismiss="alert" aria-label="close"></a><strong>ERROR!! </strong><p>There was an error during the deletion of this payment. Please try again.</p></div>';
                    }
                    ?>

                    <h3 class="box-title m-b-0">Current Payment Listing ( <x style="color: orange;"><?php echo mysqli_num_rows($query);?></x> )</h3>
                    <p class="text-muted m-b-30">Export data to Copy, CSV, Excel, PDF & Print</p>

                    <div class="table-responsive">
                        <table id="example23" class="display nowrap" cellspacing="0" width="100%">
                            <?php
                            if (mysqli_num_rows($query) == 0) {
                                echo "<i style='color:brown;'>No Payments existing for Display:( </i> ";
                            } else {
                                echo '
                                <thead>
                                    <tr>
                                        <th>Invoice No.</th>
                                        <th>Tenant</th>
                                        <th>House</th>
                                        <th>Expected Amount</th>
                                        <th>Paid Amount</th>
                                        <th>Balance</th>
                                        <th>Date Paid</th>
                                        <th>Comments</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tfoot>
                                    <tr>
                                        <th>Invoice No.</th>
                                        <th>Tenant</th>
                                        <th>House</th>
                                        <th>Expected Amount</th>
                                        <th>Paid Amount</th>
                                        <th>Balance</th>
                                        <th>Date Paid</th>
                                        <th>Comments</th>
                                        <th>Actions</th>
                                    </tr>
                                </tfoot>
                                <tbody>
                                ';
                            }

                            while ($row = mysqli_fetch_array($query)) {
                                echo '
                                <tr>
                                    <td>'.$row["invoiceNumber"].'</td>
                                    <td>'.$row["tenant_name"].'</td>
                                    <td>'.$row["house_name"].'</td>
                                    <td>'.$row["expectedAmount"].'</td>
                                    <td>'.$row["amountPaid"].'</td>
                                    <td>'.$row["balance"].'</td>
                                    <td>'.$row["dateofPayment"].'</td>
                                    <td>'.$row["comment"].'</td>
                                    <td><a href="#"><i class="fa fa-trash" data-toggle="modal" data-target="#responsive-modal'.$row["paymentID"].'" title="delete" style="color:red;"></i></a></td>

                                    <div id="responsive-modal'.$row["paymentID"].'" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                                                    <h4 class="modal-title">Are you really sure you want to permanently delete this payment record?</h4>
                                                </div>
                                                <div class="modal-footer">
                                                    <form action="payments.php" method="post">
                                                        <input type="hidden" name="tenID" value="'.$row["paymentID"].'"/>
                                                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="deleteTenant" class="btn btn-danger waves-effect waves-light">Delete and Forget</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </tr>
                                ';
                            }
                            ?>
                            </tbody>
                        </table>
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
    <?php require "admin_footer.php"; ?>
    <script>
    $('#example23').DataTable({
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
    });
    </script>
    <script src="../plugins/bower_components/styleswitcher/jQuery.style.switcher.js"></script>
</body>
</html>
<?php
} else {
    header('location:index.php');
}
?>
