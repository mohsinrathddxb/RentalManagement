<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/expense_helpers.php';

api_require_admin();
ensure_expense_tables($connection);

$selectedMonth = isset($_GET['month']) ? trim((string) $_GET['month']) : date('Y-m');
$selectedCategory = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
[$monthStart, $monthEnd] = get_month_date_range($selectedMonth);

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
    SELECT e.*, h.`house_name`, hp.`partition_number`
    FROM `expenses` e
    LEFT JOIN `houses` h ON e.`house_id` = h.`houseID`
    LEFT JOIN `house_partitions` hp ON e.`partition_id` = hp.`partition_id`
    WHERE e.`expense_date` BETWEEN '$safeMonthStart' AND '$safeMonthEnd' $categorySql
    ORDER BY e.`expense_date` DESC, e.`expense_id` DESC
");

$monthSnapshot = get_monthly_report_snapshot($connection, $selectedMonth);
$items = [];

if ($expenses) {
    while ($row = mysqli_fetch_assoc($expenses)) {
        $items[] = [
            'expense_id' => (int) $row['expense_id'],
            'expense_date' => (string) $row['expense_date'],
            'category' => (string) $row['category'],
            'title' => (string) $row['title'],
            'house_name' => isset($row['house_name']) ? (string) $row['house_name'] : '',
            'partition_number' => isset($row['partition_number']) ? (string) $row['partition_number'] : '',
            'vendor_name' => isset($row['vendor_name']) ? (string) $row['vendor_name'] : '',
            'amount' => (float) $row['amount'],
            'attachment_path' => expense_attachment_public_path(isset($row['attachment_path']) ? $row['attachment_path'] : ''),
            'attachment_kind' => isset($row['attachment_kind']) ? (string) $row['attachment_kind'] : '',
            'is_image_attachment' => expense_is_image_kind(isset($row['attachment_kind']) ? $row['attachment_kind'] : ''),
            'notes' => isset($row['notes']) ? (string) $row['notes'] : '',
        ];
    }
}

api_json([
    'ok' => true,
    'selectedMonth' => $selectedMonth,
    'selectedCategory' => $selectedCategory,
    'categories' => get_expense_categories(),
    'summary' => $monthSnapshot,
    'items' => $items,
]);
