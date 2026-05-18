<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/expense_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

api_require_admin();
ensure_expense_tables($connection);

$expenseDate = isset($_POST['expense_date']) ? uncrack((string) $_POST['expense_date']) : date('Y-m-d');
$category = isset($_POST['category']) ? uncrack((string) $_POST['category']) : '';
$title = isset($_POST['title']) ? uncrack((string) $_POST['title']) : '';
$amount = isset($_POST['amount']) ? (float) uncrack((string) $_POST['amount']) : 0;
$houseId = isset($_POST['house_id']) ? (int) $_POST['house_id'] : 0;
$partitionId = isset($_POST['partition_id']) ? (int) $_POST['partition_id'] : 0;
$vendorName = isset($_POST['vendor_name']) ? uncrack((string) $_POST['vendor_name']) : '';
$notes = isset($_POST['notes']) ? uncrack((string) $_POST['notes']) : '';
$createdByName = isset($_SESSION['name']) ? is_username($_SESSION['name']) : 'Admin';

$allowedCategories = get_expense_categories();
if (!in_array($category, $allowedCategories, true)) {
    $category = 'Other Expense';
}

$attachment = ['path' => '', 'kind' => '', 'error' => ''];
if (isset($_FILES['expense_attachment']) && !empty($_FILES['expense_attachment']['name'])) {
    $attachment = save_expense_attachment($_FILES['expense_attachment']);
    if ($attachment['error'] !== '') {
        api_json(['ok' => false, 'message' => 'Upload a JPG, PNG, GIF, WEBP image, or a PDF below 10MB.'], 422);
    }
}

if ($expenseDate === '' || $title === '' || $amount < 0) {
    api_json(['ok' => false, 'message' => 'Required expense fields are missing.'], 422);
}

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

$sql = "INSERT INTO `expenses` (`expense_date`, `category`, `title`, `amount`, `house_id`, `partition_id`, `vendor_name`, `attachment_path`, `attachment_kind`, `notes`, `created_by_name`) VALUES ('$safeDate', '$safeCategory', '$safeTitle', '$safeAmount', " . ($houseId > 0 ? "'$houseId'" : "NULL") . ", " . ($partitionId > 0 ? "'$partitionId'" : "NULL") . ", '$safeVendor', '$safeAttachmentPath', '$safeAttachmentKind', '$safeNotes', '$safeCreatedBy')";
$transactionSql = "INSERT INTO `transactions` (`actor`, `time`, `description`) VALUES ('Admin ($username)', '$timesnap', '$username added an expense of AED $safeAmount under $safeCategory: $safeTitle')";

$mysqli->autocommit(false);
$state = true;
$mysqli->query($sql) ? null : $state = false;
$expenseId = $mysqli->insert_id;
$mysqli->query($transactionSql) ? null : $state = false;

if (!$state) {
    $mysqli->rollback();
    api_json(['ok' => false, 'message' => 'Expense could not be saved.'], 500);
}

$mysqli->commit();
api_json(['ok' => true, 'expense_id' => $expenseId]);
