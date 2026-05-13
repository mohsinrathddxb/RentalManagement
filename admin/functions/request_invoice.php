<?php
require_once "db.php";

if (!isset($_GET['q']) || trim($_GET['q']) === '') {
    exit;
}

$invoiceNumber = mysqli_real_escape_string($connection, trim($_GET['q']));

$sql = "
    SELECT
        i.`invoiceNumber`,
        i.`tenantID`,
        i.`amountDue`,
        i.`dateDue`,
        i.`status`,
        t.`tenant_name`
    FROM `invoices` i
    LEFT JOIN `tenants` t ON i.`tenantID` = t.`tenantID`
    WHERE i.`invoiceNumber` = '$invoiceNumber'
    LIMIT 1
";

$query = mysqli_query($connection, $sql);

if (!$query || mysqli_num_rows($query) === 0) {
    echo '<div class="alert alert-danger">Invoice details could not be found.</div>';
    exit;
}

$row = mysqli_fetch_assoc($query);
$amountDue = format_money_amount($row['amountDue']);
?>
<input type="hidden" name="tenID" value="<?php echo (int) $row['tenantID']; ?>">
<input type="hidden" name="amountDue" value="<?php echo htmlspecialchars($amountDue, ENT_QUOTES, 'UTF-8'); ?>">

<div class="form-group">
    <label>Invoice Number:</label>
    <div class="input-group">
        <div class="input-group-addon"><i class="fa fa-file-text"></i></div>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['invoiceNumber'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
    </div>
</div>

<div class="form-group">
    <label>Tenant:</label>
    <div class="input-group">
        <div class="input-group-addon"><i class="fa fa-user"></i></div>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['tenant_name'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
    </div>
</div>

<div class="form-group">
    <label>Expected Amount:</label>
    <div class="input-group">
        <div class="input-group-addon"><i class="fa fa-usd"></i></div>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($amountDue, ENT_QUOTES, 'UTF-8'); ?>" readonly>
    </div>
</div>

<div class="form-group">
    <label>Tenant ID:</label>
    <div class="input-group">
        <div class="input-group-addon"><i class="fa fa-hashtag"></i></div>
        <input type="text" class="form-control" value="<?php echo (int) $row['tenantID']; ?>" readonly>
    </div>
</div>

<div class="form-group">
    <label>Due Date:</label>
    <div class="input-group">
        <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['dateDue'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
    </div>
</div>
