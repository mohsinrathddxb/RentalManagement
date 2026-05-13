<?php
$pgnm="Co- Accomodation: View Invoices";
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
    $canManageInvoices = is_admin_user();
    ensure_tenant_schema($connection);
    $currentTenant = get_logged_in_tenant_record();

    if ($canManageInvoices) {
        $sql = "
            SELECT
                i.`invoiceNumber`,
                t.`tenant_name`,
                t.`phone_number`,
                i.`tenantID`,
                i.`amountDue`,
                i.`dateOfInvoice`,
                i.`dateDue`,
                i.`status`,
                i.`comment`
            FROM `invoices` i
            LEFT JOIN `tenants` t ON i.`tenantID` = t.`tenantID`
            ORDER BY i.`dateOfInvoice` DESC, i.`invoiceNumber` DESC
        ";
    } else {
        $tenantId = $currentTenant ? (int) $currentTenant['tenantID'] : 0;
        $sql = "
            SELECT
                i.`invoiceNumber`,
                t.`tenant_name`,
                t.`phone_number`,
                i.`tenantID`,
                i.`amountDue`,
                i.`dateOfInvoice`,
                i.`dateDue`,
                i.`status`,
                i.`comment`
            FROM `invoices` i
            LEFT JOIN `tenants` t ON i.`tenantID` = t.`tenantID`
            WHERE i.`tenantID`='$tenantId'
            ORDER BY i.`dateOfInvoice` DESC, i.`invoiceNumber` DESC
        ";
    }

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
                    <li><a href="#" class="active">Invoices</a></li>
                    <?php if ($canManageInvoices) { ?><li><a href="new-invoice.php">New</a></li><?php } ?>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="white-box">
                    <?php
                    echo $error;

                    if (isset($_GET["state"]) && $_GET["state"] == 5) {
                        echo '<div class="alert alert-success"><a href="#" class="close" data-dismiss="alert" aria-label="close"></a><strong>DONE!! </strong><p>The invoice has been added successfully.</p></div>';
                    } elseif (isset($_GET["deleted"])) {
                        echo '<div class="alert alert-warning"><a href="#" class="close" data-dismiss="alert" aria-label="close"></a><strong>DELETED!! </strong><p>The invoice has been successfully deleted.</p></div>';
                    } elseif (isset($_GET["del_error"])) {
                        echo '<div class="alert alert-danger"><a href="#" class="close" data-dismiss="alert" aria-label="close"></a><strong>ERROR!! </strong><p>There was an error during the deletion of this invoice. Please try again.</p></div>';
                    }
                    ?>

                    <h3 class="box-title m-b-0">Current Invoice listing ( <x style="color: orange;"><?php echo mysqli_num_rows($query);?></x> )</h3>
                    <p class="text-muted m-b-30">Export data to Copy, CSV, Excel, PDF & Print</p>

                    <div class="table-responsive">
                        <table id="example23" class="display nowrap" cellspacing="0" width="100%">
                            <?php
                            if (mysqli_num_rows($query) == 0) {
                                echo "<i style='color:brown;'>No Invoices existing for Display:( </i> ";
                            } else {
                                echo '
                                <thead>
                                    <tr>
                                        <th>Invoice Id</th>
                                        <th>Tenant</th>
                                        <th>Phone Number</th>
                                        <th>Amount</th>
                                        <th>Date of Invoice</th>
                                        <th>Due Date</th>
                                        <th>Invoice status</th>
                                        <th>Comments</th>
                                        '.($canManageInvoices ? '<th>Actions</th>' : '').'
                                    </tr>
                                </thead>
                                <tfoot>
                                    <tr>
                                        <th>Invoice Id</th>
                                        <th>Tenant</th>
                                        <th>Phone Number</th>
                                        <th>Amount</th>
                                        <th>Date of Invoice</th>
                                        <th>Due Date</th>
                                        <th>Invoice status</th>
                                        <th>Comments</th>
                                        '.($canManageInvoices ? '<th>Actions</th>' : '').'
                                    </tr>
                                </tfoot>
                                <tbody>
                                ';
                            }

                            while ($row = mysqli_fetch_array($query)) {
                                $status = strtolower(trim($row["status"]));
                                if ($status === 'paid') {
                                    $statusLabel = '<span class="label label-success">Fully Paid</span>';
                                } elseif ($status === 'partial paid') {
                                    $statusLabel = '<span class="label label-warning">Partially Paid</span>';
                                } else {
                                    $statusLabel = '<span class="label label-danger">'.htmlspecialchars($row["status"], ENT_QUOTES, 'UTF-8').'</span>';
                                }

                                echo '
                                <tr>
                                    <td>'.$row["invoiceNumber"].'</td>
                                    <td>'.$row["tenant_name"].'</td>
                                    <td>'.$row["phone_number"].'</td>
                                    <td>'.$row["amountDue"].'</td>
                                    <td>'.$row["dateOfInvoice"].'</td>
                                    <td>'.$row["dateDue"].'</td>
                                    <td>'.$statusLabel.'</td>
                                    <td>'.$row["comment"].'</td>
                                    '.($canManageInvoices ? '<td><a href="#"><i class="fa fa-trash" data-toggle="modal" data-target="#responsive-modal'.$row["invoiceNumber"].'" title="delete" style="color:red;"></i></a></td>' : '').'

                                    '.($canManageInvoices ? '
                                    <div id="responsive-modal'.$row["invoiceNumber"].'" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                                                    <h4 class="modal-title">Are you really sure you want to permanently delete this invoice record?</h4>
                                                </div>
                                                <div class="modal-footer">
                                                    <form action="functions/del_invoice.php" method="post">
                                                        <input type="hidden" name="tenID" value="'.$row["invoiceNumber"].'"/>
                                                        <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="deleteTenant" class="btn btn-danger waves-effect waves-light">Delete and Forget</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>' : '').'
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
