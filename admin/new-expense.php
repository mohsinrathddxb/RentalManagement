<?php
$pgnm = "Co- Accomodation: Add Expense";
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

    if (isset($_POST['addExpense'])) {
        $expenseDate = isset($_POST['expense_date']) ? uncrack($_POST['expense_date']) : date('Y-m-d');
        $category = isset($_POST['category']) ? uncrack($_POST['category']) : '';
        $title = isset($_POST['title']) ? uncrack($_POST['title']) : '';
        $amount = isset($_POST['amount']) ? (float) uncrack($_POST['amount']) : 0;
        $houseId = isset($_POST['house_id']) ? (int) $_POST['house_id'] : 0;
        $partitionId = isset($_POST['partition_id']) ? (int) $_POST['partition_id'] : 0;
        $vendorName = isset($_POST['vendor_name']) ? uncrack($_POST['vendor_name']) : '';
        $notes = isset($_POST['notes']) ? uncrack($_POST['notes']) : '';
        $createdByName = isset($_SESSION['name']) ? is_username($_SESSION['name']) : 'Admin';

        $allowedCategories = get_expense_categories();
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'Other Expense';
        }

        $attachment = ['path' => '', 'kind' => '', 'error' => ''];
        if (!empty($_FILES['expense_attachment']['name'])) {
            $attachment = save_expense_attachment($_FILES['expense_attachment']);
            if ($attachment['error'] !== '') {
                header("location:new-expense.php?state=3");
                exit;
            }
        }

        if ($expenseDate !== '' && $title !== '' && $amount >= 0) {
            $safeDate = mysqli_real_escape_string($connection, $expenseDate);
            $safeCategory = mysqli_real_escape_string($connection, $category);
            $safeTitle = mysqli_real_escape_string($connection, $title);
            $safeVendor = mysqli_real_escape_string($connection, $vendorName);
            $safeNotes = mysqli_real_escape_string($connection, $notes);
            $safeAttachmentPath = mysqli_real_escape_string($connection, $attachment['path']);
            $safeAttachmentKind = mysqli_real_escape_string($connection, $attachment['kind']);
            $safeCreatedBy = mysqli_real_escape_string($connection, $createdByName);
            $safeAmount = number_format($amount, 2, '.', '');
            $timesnap = date('Y-m-d : H:i:s');

            $sql = "
                INSERT INTO `expenses`
                (`expense_date`, `category`, `title`, `amount`, `house_id`, `partition_id`, `vendor_name`, `attachment_path`, `attachment_kind`, `notes`, `created_by_name`)
                VALUES
                ('$safeDate', '$safeCategory', '$safeTitle', '$safeAmount', " . ($houseId > 0 ? "'$houseId'" : "NULL") . ", " . ($partitionId > 0 ? "'$partitionId'" : "NULL") . ", '$safeVendor', '$safeAttachmentPath', '$safeAttachmentKind', '$safeNotes', '$safeCreatedBy')
            ";

            $transactionSql = "
                INSERT INTO `transactions` (`actor`, `time`, `description`)
                VALUES ('Admin ($username)', '$timesnap', '$username added an expense of AED $safeAmount under $safeCategory: $safeTitle')
            ";

            $mysqli->autocommit(false);
            $state = true;
            $mysqli->query($sql) ? null : $state = false;
            $mysqli->query($transactionSql) ? null : $state = false;

            if ($state) {
                $mysqli->commit();
                header("location:expenses.php?state=1");
                exit;
            }

            $mysqli->rollback();
            header("location:new-expense.php?state=2");
            exit;
        }

        header("location:new-expense.php?state=2");
        exit;
    }

    $houses = get_expense_house_options($connection);
    $partitions = get_expense_partition_options($connection);

    require "admin_header0.php";
    require "admin_left_panel.php";
?>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title"><?php echo 'Hey there, ' . $username; ?></h4>
            </div>
            <div class="col-lg-9 col-sm-8 col-md-8 col-xs-12">
                <ol class="breadcrumb">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="expenses.php">Expenses</a></li>
                    <li class="active">New</li>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div><?php echo $error; ?></div>
                <div class="white-box">
                    <?php if (isset($_GET['state']) && $_GET['state'] == 2) { ?>
                        <div class="alert alert-danger">
                            <strong>ERROR!!</strong>
                            <p>The expense could not be saved. Please check the required fields and attachment type.</p>
                        </div>
                    <?php } elseif (isset($_GET['state']) && $_GET['state'] == 3) { ?>
                        <div class="alert alert-danger">
                            <strong>ATTACHMENT ERROR!!</strong>
                            <p>Upload a JPG, PNG, GIF, WEBP image, or a PDF below 10MB.</p>
                        </div>
                    <?php } ?>

                    <h3 class="box-title m-b-0"><i class="fa fa-file-text-o fa-3x"></i> Add Expense / Bill</h3>
                    <p class="text-muted m-b-30 font-13">Track DEWA, purchases, and operating costs with invoice attachments.</p>

                    <div class="row">
                        <div class="col-sm-12 col-xs-12">
                            <form action="new-expense.php" method="post" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="expense_date">Expense Date: *</label>
                                        <input type="date" name="expense_date" class="form-control" id="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label for="category">Category: *</label>
                                        <select name="category" class="form-control" id="category" required>
                                            <?php foreach (get_expense_categories() as $category) { ?>
                                                <option value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label for="amount">Amount (AED): *</label>
                                        <input type="number" min="0" step="0.01" name="amount" class="form-control" id="amount" placeholder="e.g. 245.50" required>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="title">Title / What was paid for: *</label>
                                        <input type="text" name="title" class="form-control" id="title" placeholder="e.g. DEWA April 2026 - House A" required>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="vendor_name">Vendor / Paid To:</label>
                                        <input type="text" name="vendor_name" class="form-control" id="vendor_name" placeholder="e.g. DEWA / Carrefour / Technician">
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="house_id">House:</label>
                                        <select name="house_id" class="form-control" id="house_id">
                                            <option value="">All / Not tied to a single house</option>
                                            <?php if ($houses) { while ($house = mysqli_fetch_assoc($houses)) { ?>
                                                <option value="<?php echo (int) $house['houseID']; ?>">
                                                    <?php echo htmlspecialchars($house['house_name'] . ' - ' . $house['location'], ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php } } ?>
                                        </select>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="partition_id">Partition / Room:</label>
                                        <select name="partition_id" class="form-control" id="partition_id">
                                            <option value="">General / Whole house expense</option>
                                            <?php if ($partitions) { while ($partition = mysqli_fetch_assoc($partitions)) { ?>
                                                <option value="<?php echo (int) $partition['partition_id']; ?>" data-house-id="<?php echo (int) $partition['house_id']; ?>">
                                                    <?php echo htmlspecialchars($partition['house_name'] . ' / ' . $partition['partition_number'], ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php } } ?>
                                        </select>
                                    </div>

                                    <div class="form-group col-md-12">
                                        <label for="expense_attachment">Invoice / Bill Attachment:</label>
                                        <input type="file" name="expense_attachment" class="form-control" id="expense_attachment" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
                                        <small class="text-muted">Images and PDF supported. Images above 500 KB are compressed automatically before upload.</small>
                                    </div>

                                    <div class="form-group col-md-12">
                                        <label for="notes">Notes:</label>
                                        <textarea name="notes" class="form-control" id="notes" rows="4" placeholder="Optional notes about this expense"></textarea>
                                    </div>

                                    <div class="col-md-12">
                                        <button type="submit" name="addExpense" class="btn btn-success btn-lg waves-effect waves-light">
                                            <i class="fa fa-plus-circle fa-lg"></i> Save Expense
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require "admin_footer.php"; ?>
</div>

<script>
$(document).ready(function() {
    function filterPartitionsByHouse() {
        var selectedHouse = $('#house_id').val();
        $('#partition_id option').each(function() {
            var optionHouse = $(this).data('house-id');
            if (!optionHouse || !selectedHouse || String(optionHouse) === String(selectedHouse)) {
                $(this).show();
            } else {
                if ($(this).is(':selected')) {
                    $('#partition_id').val('');
                }
                $(this).hide();
            }
        });
    }

    $('#house_id').on('change', filterPartitionsByHouse);
    filterPartitionsByHouse();
});
</script>
<?php
} else {
    header('location:login.php');
    exit;
}
?>
