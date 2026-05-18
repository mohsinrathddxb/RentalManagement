<?php
$pgnm="Co- Accomodation: View Invoices";
$error=' ';

require_once "functions/errors.php";

ob_start();
require_once "functions/db.php";
require_once "functions/tenant_helpers.php";
require_once "functions/invoice_pdf_helpers.php";
require_once "functions/ui_column_preferences.php";

session_start();

if(!isset($_SESSION['email']) || empty($_SESSION['email'])){
    header("location: login.php");
    exit;
}

if (is_logged_in_temporary()) {
    $canManageInvoices = is_admin_user();
    ensure_tenant_schema($connection);
    ensure_invoice_pdf_columns($connection);
    ensure_ui_column_preferences_schema($connection);
    $currentTenant = get_logged_in_tenant_record();

    if ($canManageInvoices) {
        $sql = "
            SELECT
                i.`invoiceNumber`,
                t.`tenant_name`,
                t.`phone_number`,
                i.`tenantID`,
                i.`amountDue`,
                i.`total_amount`,
                i.`dateOfInvoice`,
                i.`dateDue`,
                i.`status`,
                i.`comment`,
                (
                    SELECT MAX(p.`paymentID`)
                    FROM `payments` p
                    WHERE p.`invoiceNumber` = i.`invoiceNumber`
                ) AS `latestPaymentID`
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
                i.`total_amount`,
                i.`dateOfInvoice`,
                i.`dateDue`,
                i.`status`,
                i.`comment`,
                (
                    SELECT MAX(p.`paymentID`)
                    FROM `payments` p
                    WHERE p.`invoiceNumber` = i.`invoiceNumber`
                ) AS `latestPaymentID`
            FROM `invoices` i
            LEFT JOIN `tenants` t ON i.`tenantID` = t.`tenantID`
            WHERE i.`tenantID`='$tenantId'
            ORDER BY i.`dateOfInvoice` DESC, i.`invoiceNumber` DESC
        ";
    }

    $query = mysqli_query($connection, $sql);
    $savedInvoiceColumns = get_ui_visible_columns($connection, $_SESSION['email'], 'invoices_view');
    $savedInvoiceColumnsJson = json_encode(is_array($savedInvoiceColumns) ? $savedInvoiceColumns : []);

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
                    <style>
                        @media (max-width: 767px) {
                            #example23 { width: 100% !important; }
                            #example23 th, #example23 td { white-space: normal !important; font-size: 11px; line-height: 1.35; padding: 8px 6px !important; }
                        }
                    </style>

                    <h3 class="box-title m-b-0">Current Invoice listing ( <x style="color: orange;"><?php echo mysqli_num_rows($query);?></x> )</h3>
                    <p class="text-muted m-b-30">Export data to Copy, CSV, Excel, PDF & Print</p>
                    <div class="m-b-15">
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Columns <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" id="invoice-column-toggles"></ul>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="example23" class="display nowrap" cellspacing="0" width="100%">
                            <?php
                            if (mysqli_num_rows($query) == 0) {
                                echo "<i style='color:brown;'>No Invoices existing for Display:( </i> ";
                            } else {
                                echo '
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Invoice status</th>
                                        <th>Amount</th>
                                        <th>Documents</th>
                                        <th>Invoice Id</th>
                                        <th>Phone Number</th>
                                        <th>Date of Invoice</th>
                                        <th>Due Date</th>
                                        <th>Comments</th>
                                        '.($canManageInvoices ? '<th>Actions</th>' : '').'
                                    </tr>
                                </thead>
                                <tfoot>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Invoice status</th>
                                        <th>Amount</th>
                                        <th>Documents</th>
                                        <th>Invoice Id</th>
                                        <th>Phone Number</th>
                                        <th>Date of Invoice</th>
                                        <th>Due Date</th>
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

                                $pdfMobileEmail = isset($_SESSION['email']) ? $_SESSION['email'] : '';
                                $invoiceMobileQuery = pdf_mobile_query_string($pdfMobileEmail, 'invoice', $row["invoiceNumber"]);
                                $invoiceLink = '<a class="btn btn-xs btn-info" href="invoice-pdf.php?invoice='.urlencode($row["invoiceNumber"]).$invoiceMobileQuery.'">Invoice PDF</a>';
                                $receiptLink = '';
                                if (!empty($row["latestPaymentID"])) {
                                    $receiptMobileQuery = pdf_mobile_query_string($pdfMobileEmail, 'receipt', (int) $row["latestPaymentID"]);
                                    $receiptLink = ' <a class="btn btn-xs btn-success" href="payment-receipt-pdf.php?payment='.(int) $row["latestPaymentID"].$receiptMobileQuery.'">Latest Receipt</a>';
                                }

                                echo '
                                <tr>
                                    <td>'.$row["tenant_name"].'</td>
                                    <td>'.$statusLabel.'</td>
                                    <td>'.format_money_amount($row["amountDue"]).'</td>
                                    <td>'.$invoiceLink.$receiptLink.'</td>
                                    <td>'.$row["invoiceNumber"].'</td>
                                    <td>'.$row["phone_number"].'</td>
                                    <td>'.$row["dateOfInvoice"].'</td>
                                    <td>'.$row["dateDue"].'</td>
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
    var savedInvoiceColumns = <?php echo $savedInvoiceColumnsJson; ?>;
    var isMobileInvoiceView = window.matchMedia('(max-width: 767px)').matches;
    if (isMobileInvoiceView) {
        $('#example23').removeClass('nowrap');
    }
    var invoiceTable = $('#example23').DataTable({
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        scrollX: !isMobileInvoiceView,
        autoWidth: false
    });

    if (isMobileInvoiceView) {
        invoiceTable.columns.adjust().draw(false);
    }

    if (Array.isArray(savedInvoiceColumns) && savedInvoiceColumns.length > 0) {
        invoiceTable.columns().every(function(index) {
            this.visible(savedInvoiceColumns.indexOf(index) !== -1);
        });
    }

    function escapeInvoiceHtml(value) {
        return $('<div>').text(value).html();
    }

    function renderInvoiceColumnToggles() {
        var toggleHtml = '';
        invoiceTable.columns().every(function(index) {
            var headerText = $(this.header()).text().trim();
            if (!headerText) {
                return;
            }
            var checked = this.visible() ? 'checked' : '';
            toggleHtml += '<li><a href="#" class="invoice-col-toggle" data-col="' + index + '"><label style="margin:0; font-weight:500; cursor:pointer;"><input type="checkbox" ' + checked + ' style="margin-right:8px;">' + escapeInvoiceHtml(headerText) + '</label></a></li>';
        });
        $('#invoice-column-toggles').html(toggleHtml);
    }

    renderInvoiceColumnToggles();

    var invoiceColumnSaveTimer = null;
    function saveInvoiceColumnPreference() {
        var visibleColumns = [];
        invoiceTable.columns().every(function(index) {
            if (this.visible()) {
                visibleColumns.push(index);
            }
        });

        $.ajax({
            url: 'functions/save_ui_columns.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                table_key: 'invoices_view',
                visible_columns: visibleColumns
            })
        });
    }

    function queueInvoiceColumnPreferenceSave() {
        if (invoiceColumnSaveTimer) {
            clearTimeout(invoiceColumnSaveTimer);
        }
        invoiceColumnSaveTimer = setTimeout(saveInvoiceColumnPreference, 220);
    }

    $('#invoice-column-toggles').on('click', '.invoice-col-toggle', function(event) {
        event.preventDefault();
        event.stopPropagation();
        var columnIndex = parseInt($(this).data('col'), 10);
        var column = invoiceTable.column(columnIndex);
        var visibleCount = invoiceTable.columns(':visible').count();
        if (column.visible() && visibleCount <= 1) {
            return;
        }
        column.visible(!column.visible());
        renderInvoiceColumnToggles();
        queueInvoiceColumnPreferenceSave();
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
