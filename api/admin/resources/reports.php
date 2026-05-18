<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../admin/functions/expense_helpers.php';

api_require_admin();
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

$breakdown = [];
if ($expenseBreakdown) {
    while ($row = mysqli_fetch_assoc($expenseBreakdown)) {
        $breakdown[] = [
            'category' => (string) $row['category'],
            'total' => (float) $row['total'],
        ];
    }
}

api_json([
    'ok' => true,
    'selectedMonth' => $selectedMonth,
    'selectedYear' => $selectedYear,
    'selectedQuarter' => $selectedQuarter,
    'monthly' => $monthlySnapshot,
    'quarterly' => $quarterlySnapshot,
    'expenseBreakdown' => $breakdown,
    'roomReport' => array_values($roomReport),
    'partitionReport' => array_values($partitionReport),
]);
