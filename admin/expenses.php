<?php
$pgnm = "Co- Accomodation: Expenses";
$error = ' ';

ob_start();

require_once "functions/db.php";
require_once "functions/errors.php";
require_once "functions/tenant_helpers.php";
require_once "functions/partition_helpers.php";
require_once "functions/expense_helpers.php";

session_start();

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header("location: login.php");
    exit;
}

if (is_logged_in_temporary()) {
    require_admin_user();
    ensure_tenant_schema($connection);
    ensure_partition_tables($connection);
    ensure_expense_tables($connection);

    $selectedMonth = isset($_GET['month']) ? trim((string) $_GET['month']) : date('Y-m');
    list($monthStart, $monthEnd) = get_month_date_range($selectedMonth);
    $selectedCategory = isset($_GET['category']) ? trim((string) $_GET['category']) : '';

    $categorySql = '';
    if ($selectedCategory !== '' && in_array($selectedCategory, get_expense_categories(), true)) {
        $safeCategory = mysqli_real_escape_string($connection, $selectedCategory);
        $categorySql = " AND e.`category`='$safeCategory'";
    } else {
        $selectedCategory = '';
    }

    $safeMonthStart = mysqli_real_escape_string($connection, $monthStart);
    $safeMonthEnd = mysqli_real_escape_string($connection, $monthEnd);

    $expenses = mysqli_query($connection, "
        SELECT
            e.*,
            h.`house_name`,
            hp.`partition_number`
        FROM `expenses` e
        LEFT JOIN `houses` h ON e.`house_id` = h.`houseID`
        LEFT JOIN `house_partitions` hp ON e.`partition_id` = hp.`partition_id`
        WHERE e.`expense_date` BETWEEN '$safeMonthStart' AND '$safeMonthEnd' $categorySql
        ORDER BY e.`expense_date` DESC, e.`expense_id` DESC
    ");

    $monthSnapshot = get_monthly_report_snapshot($connection, $selectedMonth);

    require "admin_header0.php";
    require "admin_left_panel.php";
?>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Expense Register</h4>
            </div>
            <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                <ol class="breadcrumb">
                    <li><a href="index.php">Dashboard</a></li>
                    <li class="active">Expenses</li>
                    <li><a href="new-expense.php">New</a></li>
                </ol>
            </div>
        </div>

        <?php if (isset($_GET['state']) && $_GET['state'] == 1) { ?>
            <div class="alert alert-success"><strong>DONE!!</strong> Expense saved successfully.</div>
        <?php } ?>

        <div class="white-box">
            <div class="row">
                <div class="col-md-8">
                    <h3 class="box-title m-b-0">Expenses</h3>
                    <p class="text-muted m-b-20">Track DEWA, maintenance, purchases, and other operating costs.</p>
                </div>
                <div class="col-md-4 text-right">
                    <a href="new-expense.php" class="btn btn-success"><i class="fa fa-plus-circle"></i> Add Expense</a>
                    <a href="reports.php" class="btn btn-info"><i class="fa fa-bar-chart"></i> View Reports</a>
                </div>
            </div>

            <form action="expenses.php" method="get" class="row" style="margin-bottom:20px;">
                <div class="form-group col-md-4">
                    <label for="month">Month</label>
                    <input type="month" name="month" id="month" class="form-control" value="<?php echo htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="category">Category</label>
                    <select name="category" id="category" class="form-control">
                        <option value="">All categories</option>
                        <?php foreach (get_expense_categories() as $category) { ?>
                            <option value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selectedCategory === $category ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                    </div>
                </div>
            </form>

            <div class="row" style="margin-bottom:20px;">
                <div class="col-md-3">
                    <div style="border:1px solid #e4e7ea; padding:15px;">
                        <strong>Rent Collected</strong>
                        <div style="font-size:2em;"><?php echo format_money_amount($monthSnapshot['rent_collected']); ?> <small>AED</small></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div style="border:1px solid #e4e7ea; padding:15px;">
                        <strong>DEWA Paid</strong>
                        <div style="font-size:2em;"><?php echo format_money_amount($monthSnapshot['dewa_paid']); ?> <small>AED</small></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div style="border:1px solid #e4e7ea; padding:15px;">
                        <strong>Other Expenses</strong>
                        <div style="font-size:2em;"><?php echo format_money_amount($monthSnapshot['other_expenses']); ?> <small>AED</small></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div style="border:1px solid #e4e7ea; padding:15px;">
                        <strong>Net Earning</strong>
                        <div style="font-size:2em;"><?php echo format_money_amount($monthSnapshot['net_earning']); ?> <small>AED</small></div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="expense-table" class="display nowrap" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Title</th>
                            <th>House</th>
                            <th>Partition / Room</th>
                            <th>Vendor</th>
                            <th>Amount (AED)</th>
                            <th>Attachment</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($expenses) {
                            while ($row = mysqli_fetch_assoc($expenses)) {
                                $attachmentUrl = expense_attachment_public_path($row['attachment_path']);
                                $attachmentHtml = '<span class="text-muted">None</span>';
                                if ($attachmentUrl !== '') {
                                    $label = expense_is_image_kind($row['attachment_kind']) ? 'View Image' : 'View PDF';
                                    $attachmentHtml = '<a href="' . htmlspecialchars($attachmentUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-xs btn-info">' . $label . '</a>';
                                }

                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['expense_date'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td>' . htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td>' . htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td>' . htmlspecialchars((string) $row['house_name'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td>' . htmlspecialchars((string) $row['partition_number'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td>' . htmlspecialchars((string) $row['vendor_name'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td>' . format_money_amount($row['amount']) . '</td>';
                                echo '<td>' . $attachmentHtml . '</td>';
                                echo '<td>' . htmlspecialchars((string) $row['notes'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php require "admin_footer.php"; ?>
</div>
<script>
$('#expense-table').DataTable({
    dom: 'Bfrtip',
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
});
</script>
<?php
} else {
    header('location:login.php');
    exit;
}
?>
