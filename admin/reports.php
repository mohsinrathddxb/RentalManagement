<?php
$pgnm = "Co- Accomodation: Reports";
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
    $selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
    $selectedQuarter = isset($_GET['quarter']) ? (int) $_GET['quarter'] : (int) ceil(date('n') / 3);

    $monthlySnapshot = get_monthly_report_snapshot($connection, $selectedMonth);
    $quarterlySnapshot = get_quarterly_report_snapshot($connection, $selectedYear, $selectedQuarter);
    $roomReport = get_room_wise_report($connection, $monthlySnapshot['start'], $monthlySnapshot['end']);
    $partitionReport = get_partition_wise_report($connection, $monthlySnapshot['start'], $monthlySnapshot['end']);

    $safeMonthStart = mysqli_real_escape_string($connection, $monthlySnapshot['start']);
    $safeMonthEnd = mysqli_real_escape_string($connection, $monthlySnapshot['end']);
    $expenseBreakdown = mysqli_query($connection, "
        SELECT `category`, COALESCE(SUM(`amount`), 0) AS total
        FROM `expenses`
        WHERE `expense_date` BETWEEN '$safeMonthStart' AND '$safeMonthEnd'
        GROUP BY `category`
        ORDER BY total DESC, `category` ASC
    ");

    require "admin_header0.php";
    require "admin_left_panel.php";
?>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Finance Reports</h4>
            </div>
            <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                <ol class="breadcrumb">
                    <li><a href="index.php">Dashboard</a></li>
                    <li class="active">Reports</li>
                </ol>
            </div>
        </div>

        <div class="white-box">
            <h3 class="box-title m-b-0">Reporting Filters</h3>
            <p class="text-muted m-b-20">Measure actual earnings after expenses, by month, quarter, room, and partition.</p>

            <form action="reports.php" method="get" class="row">
                <div class="form-group col-md-4">
                    <label for="month">Monthly view</label>
                    <input type="month" name="month" id="month" class="form-control" value="<?php echo htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="year">Quarter year</label>
                    <input type="number" name="year" id="year" class="form-control" value="<?php echo (int) $selectedYear; ?>" min="2000" max="2100">
                </div>
                <div class="form-group col-md-4">
                    <label for="quarter">Quarter</label>
                    <select name="quarter" id="quarter" class="form-control">
                        <option value="1"<?php echo $selectedQuarter === 1 ? ' selected' : ''; ?>>Q1</option>
                        <option value="2"<?php echo $selectedQuarter === 2 ? ' selected' : ''; ?>>Q2</option>
                        <option value="3"<?php echo $selectedQuarter === 3 ? ' selected' : ''; ?>>Q3</option>
                        <option value="4"<?php echo $selectedQuarter === 4 ? ' selected' : ''; ?>>Q4</option>
                    </select>
                </div>
                <div class="form-group col-md-12">
                    <button type="submit" class="btn btn-primary">Refresh Reports</button>
                    <a href="new-expense.php" class="btn btn-success">Add Expense</a>
                </div>
            </form>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="white-box">
                    <h3 class="box-title">Monthly Report</h3>
                    <p class="text-muted"><?php echo htmlspecialchars($monthlySnapshot['start'] . ' to ' . $monthlySnapshot['end'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <table class="table">
                        <tr><th>Rent Collected</th><td><?php echo format_money_amount($monthlySnapshot['rent_collected']); ?> AED</td></tr>
                        <tr><th>DEWA Paid</th><td><?php echo format_money_amount($monthlySnapshot['dewa_paid']); ?> AED</td></tr>
                        <tr><th>Other Expenses</th><td><?php echo format_money_amount($monthlySnapshot['other_expenses']); ?> AED</td></tr>
                        <tr><th>Total Expenses</th><td><?php echo format_money_amount($monthlySnapshot['total_expenses']); ?> AED</td></tr>
                        <tr><th>Actual Earning</th><td><strong><?php echo format_money_amount($monthlySnapshot['net_earning']); ?> AED</strong></td></tr>
                    </table>
                </div>
            </div>

            <div class="col-md-6">
                <div class="white-box">
                    <h3 class="box-title">Quarterly Report</h3>
                    <p class="text-muted"><?php echo htmlspecialchars($quarterlySnapshot['start'] . ' to ' . $quarterlySnapshot['end'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <table class="table">
                        <tr><th>Rent Collected</th><td><?php echo format_money_amount($quarterlySnapshot['rent_collected']); ?> AED</td></tr>
                        <tr><th>DEWA Paid</th><td><?php echo format_money_amount($quarterlySnapshot['dewa_paid']); ?> AED</td></tr>
                        <tr><th>Other Expenses</th><td><?php echo format_money_amount($quarterlySnapshot['other_expenses']); ?> AED</td></tr>
                        <tr><th>Total Expenses</th><td><?php echo format_money_amount($quarterlySnapshot['total_expenses']); ?> AED</td></tr>
                        <tr><th>Actual Earning</th><td><strong><?php echo format_money_amount($quarterlySnapshot['net_earning']); ?> AED</strong></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="white-box">
            <h3 class="box-title">Expense Breakdown For Selected Month</h3>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Total (AED)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($expenseBreakdown && mysqli_num_rows($expenseBreakdown) > 0) {
                            while ($row = mysqli_fetch_assoc($expenseBreakdown)) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8') . '</td>';
                                echo '<td>' . format_money_amount($row['total']) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="2"><span class="text-muted">No expenses recorded in this period yet.</span></td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="white-box">
            <h3 class="box-title">Room-wise Actual Earnings</h3>
            <p class="text-muted">Uses the selected monthly range and direct expenses attached to each room / partition that had activity in the period.</p>
            <div class="table-responsive">
                <table id="room-report" class="display nowrap" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th>House</th>
                            <th>Partition / Room</th>
                            <th>Tenant</th>
                            <th>Rent Collected</th>
                            <th>Direct Expenses</th>
                            <th>Net Earning</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roomReport as $row) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $row['house_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['partition_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['tenant_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo format_money_amount($row['rent_collected']); ?></td>
                                <td><?php echo format_money_amount($row['direct_expenses']); ?></td>
                                <td><?php echo format_money_amount($row['net_earning']); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="white-box">
            <h3 class="box-title">Partition-wise Actual Earnings</h3>
            <p class="text-muted">Shows each partition, the rent collected in the selected monthly range, direct expenses assigned to that partition, and net earning.</p>
            <p class="text-muted">House-level expenses without a partition assignment are included in monthly and quarterly totals, but not forced into a partition row.</p>
            <div class="table-responsive">
                <table id="partition-report" class="display nowrap" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th>House</th>
                            <th>Partition</th>
                            <th>Status</th>
                            <th>Rent Collected</th>
                            <th>Direct Expenses</th>
                            <th>Net Earning</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partitionReport as $row) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $row['house_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['partition_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['partition_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo format_money_amount($row['rent_collected']); ?></td>
                                <td><?php echo format_money_amount($row['direct_expenses']); ?></td>
                                <td><?php echo format_money_amount($row['net_earning']); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php require "admin_footer.php"; ?>
</div>
<script>
$('#room-report').DataTable({
    dom: 'Bfrtip',
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
});

$('#partition-report').DataTable({
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
