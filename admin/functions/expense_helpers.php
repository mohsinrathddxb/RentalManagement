<?php

function ensure_expense_tables($connection) {
    @mysqli_query($connection, "
        CREATE TABLE IF NOT EXISTS `expenses` (
            `expense_id` int(11) NOT NULL AUTO_INCREMENT,
            `expense_date` date NOT NULL,
            `category` varchar(50) NOT NULL,
            `title` varchar(255) NOT NULL,
            `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
            `house_id` int(11) DEFAULT NULL,
            `partition_id` int(11) DEFAULT NULL,
            `vendor_name` varchar(150) DEFAULT NULL,
            `attachment_path` text DEFAULT NULL,
            `attachment_kind` varchar(20) DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `created_by_name` varchar(150) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`expense_id`),
            KEY `expense_date` (`expense_date`),
            KEY `category` (`category`),
            KEY `house_id` (`house_id`),
            KEY `partition_id` (`partition_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function get_expense_categories() {
    return [
        'DEWA Bill',
        'Maintenance',
        'Purchase',
        'Other Expense'
    ];
}

function expense_attachment_public_path($storedPath) {
    $storedPath = trim((string) $storedPath);
    if ($storedPath === '') {
        return '';
    }

    $normalized = str_replace('\\', '/', $storedPath);
    $normalized = ltrim($normalized, '/');

    if (strpos($normalized, 'admin/') === 0) {
        return '/' . $normalized;
    }

    if (strpos($normalized, 'uploads/expense_attachments/') === 0) {
        return '/admin/' . $normalized;
    }

    return '/' . $normalized;
}

function expense_is_image_kind($attachmentKind) {
    return in_array((string) $attachmentKind, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
}

function compress_uploaded_expense_image($tmpPath, $targetPath, $mimeType, $sizeBytes) {
    $compressionThreshold = 512000;

    if ($sizeBytes <= $compressionThreshold) {
        return @move_uploaded_file($tmpPath, $targetPath);
    }

    if (!function_exists('imagecreatetruecolor')) {
        return @move_uploaded_file($tmpPath, $targetPath);
    }

    $image = null;
    if ($mimeType === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
        $image = @imagecreatefromjpeg($tmpPath);
    } elseif ($mimeType === 'image/png' && function_exists('imagecreatefrompng')) {
        $image = @imagecreatefrompng($tmpPath);
    } elseif ($mimeType === 'image/gif' && function_exists('imagecreatefromgif')) {
        $image = @imagecreatefromgif($tmpPath);
    } elseif ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $image = @imagecreatefromwebp($tmpPath);
    }

    if (!$image) {
        return @move_uploaded_file($tmpPath, $targetPath);
    }

    $result = false;
    if ($mimeType === 'image/jpeg' && function_exists('imagejpeg')) {
        $result = @imagejpeg($image, $targetPath, 72);
    } elseif ($mimeType === 'image/png' && function_exists('imagepng')) {
        @imagealphablending($image, false);
        @imagesavealpha($image, true);
        $result = @imagepng($image, $targetPath, 7);
    } elseif ($mimeType === 'image/gif' && function_exists('imagegif')) {
        $result = @imagegif($image, $targetPath);
    } elseif ($mimeType === 'image/webp' && function_exists('imagewebp')) {
        $result = @imagewebp($image, $targetPath, 72);
    }

    @imagedestroy($image);

    if (!$result) {
        return @move_uploaded_file($tmpPath, $targetPath);
    }

    return true;
}

function save_expense_attachment($file) {
    if (!isset($file['name']) || trim((string) $file['name']) === '') {
        return ['path' => '', 'kind' => '', 'error' => ''];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf'
    ];

    $mimeType = isset($file['type']) ? (string) $file['type'] : '';
    $sizeBytes = isset($file['size']) ? (int) $file['size'] : 0;
    $tmpPath = isset($file['tmp_name']) ? $file['tmp_name'] : '';

    if (!isset($allowed[$mimeType])) {
        return ['path' => '', 'kind' => '', 'error' => 'unsupported'];
    }

    if ($sizeBytes > 10485760) {
        return ['path' => '', 'kind' => '', 'error' => 'too_large'];
    }

    $uploadDir = __DIR__ . '/../uploads/expense_attachments/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $extension = $allowed[$mimeType];
    $fileName = 'expense_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
    $targetPath = $uploadDir . $fileName;
    $storedPath = 'uploads/expense_attachments/' . $fileName;
    $saved = false;

    if ($mimeType === 'application/pdf') {
        $saved = @move_uploaded_file($tmpPath, $targetPath);
    } else {
        $saved = compress_uploaded_expense_image($tmpPath, $targetPath, $mimeType, $sizeBytes);
    }

    if (!$saved) {
        return ['path' => '', 'kind' => '', 'error' => 'save_failed'];
    }

    return ['path' => $storedPath, 'kind' => $mimeType, 'error' => ''];
}

function report_sum_rent_collected($connection, $dateFrom, $dateTo) {
    $dateFrom = mysqli_real_escape_string($connection, $dateFrom);
    $dateTo = mysqli_real_escape_string($connection, $dateTo);
    $sql = "
        SELECT COALESCE(SUM(CAST(`amountPaid` AS DECIMAL(10,2))), 0) AS total
        FROM `payments`
        WHERE `dateofPayment` BETWEEN '$dateFrom' AND '$dateTo'
    ";
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_assoc($result) : ['total' => 0];
    return (float) $row['total'];
}

function report_sum_expenses($connection, $dateFrom, $dateTo, $category = '') {
    $dateFrom = mysqli_real_escape_string($connection, $dateFrom);
    $dateTo = mysqli_real_escape_string($connection, $dateTo);
    $categorySql = '';

    if ($category !== '') {
        $safeCategory = mysqli_real_escape_string($connection, $category);
        $categorySql = " AND `category`='$safeCategory'";
    }

    $sql = "
        SELECT COALESCE(SUM(`amount`), 0) AS total
        FROM `expenses`
        WHERE `expense_date` BETWEEN '$dateFrom' AND '$dateTo' $categorySql
    ";

    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_assoc($result) : ['total' => 0];
    return (float) $row['total'];
}

function get_month_date_range($yearMonth) {
    $yearMonth = trim((string) $yearMonth);
    if ($yearMonth === '' || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
        $yearMonth = date('Y-m');
    }

    $start = $yearMonth . '-01';
    $end = date('Y-m-t', strtotime($start));

    return [$start, $end];
}

function get_quarter_date_range($year, $quarter) {
    $year = (int) $year;
    $quarter = (int) $quarter;

    if ($year <= 0) {
        $year = (int) date('Y');
    }

    if ($quarter < 1 || $quarter > 4) {
        $quarter = (int) ceil(date('n') / 3);
    }

    $startMonth = (($quarter - 1) * 3) + 1;
    $start = sprintf('%04d-%02d-01', $year, $startMonth);
    $end = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $startMonth + 2)));

    return [$start, $end];
}

function get_monthly_report_snapshot($connection, $yearMonth) {
    list($start, $end) = get_month_date_range($yearMonth);

    $rentCollected = report_sum_rent_collected($connection, $start, $end);
    $dewaPaid = report_sum_expenses($connection, $start, $end, 'DEWA Bill');
    $otherExpenses = report_sum_expenses($connection, $start, $end) - $dewaPaid;

    return [
        'start' => $start,
        'end' => $end,
        'rent_collected' => $rentCollected,
        'dewa_paid' => $dewaPaid,
        'other_expenses' => $otherExpenses,
        'total_expenses' => $dewaPaid + $otherExpenses,
        'net_earning' => $rentCollected - ($dewaPaid + $otherExpenses)
    ];
}

function get_quarterly_report_snapshot($connection, $year, $quarter) {
    list($start, $end) = get_quarter_date_range($year, $quarter);

    $rentCollected = report_sum_rent_collected($connection, $start, $end);
    $dewaPaid = report_sum_expenses($connection, $start, $end, 'DEWA Bill');
    $otherExpenses = report_sum_expenses($connection, $start, $end) - $dewaPaid;

    return [
        'start' => $start,
        'end' => $end,
        'rent_collected' => $rentCollected,
        'dewa_paid' => $dewaPaid,
        'other_expenses' => $otherExpenses,
        'total_expenses' => $dewaPaid + $otherExpenses,
        'net_earning' => $rentCollected - ($dewaPaid + $otherExpenses)
    ];
}

function get_house_wise_report($connection, $dateFrom, $dateTo) {
    $dateFrom = mysqli_real_escape_string($connection, $dateFrom);
    $dateTo = mysqli_real_escape_string($connection, $dateTo);

    $sql = "
        SELECT
            h.`houseID`,
            h.`house_name`,
            COALESCE(SUM(CAST(p.`amountPaid` AS DECIMAL(10,2))), 0) AS `rent_collected`,
            COALESCE(SUM(
                CASE
                    WHEN CAST(t.`account` AS DECIMAL(10,2)) > 0 THEN CAST(t.`account` AS DECIMAL(10,2))
                    ELSE 0
                END
            ), 0) AS `advance_amount`,
            COALESCE((
                SELECT SUM(
                    CASE
                        WHEN CAST(i.`amountDue` AS DECIMAL(10,2)) > 0 THEN CAST(i.`amountDue` AS DECIMAL(10,2))
                        ELSE 0
                    END
                )
                FROM `invoices` i
                INNER JOIN `tenants` it ON i.`tenantID` = it.`tenantID`
                WHERE it.`houseNumber` = h.`houseID`
                  AND it.`tenant_status` = 'Active'
            ), 0) AS `current_due_amount`,
            COALESCE((
                SELECT COUNT(*)
                FROM `invoices` i2
                INNER JOIN `tenants` it2 ON i2.`tenantID` = it2.`tenantID`
                WHERE it2.`houseNumber` = h.`houseID`
                  AND it2.`tenant_status` = 'Active'
            ), 0) AS `invoice_count`,
            (
                SELECT COALESCE(SUM(e.`amount`), 0)
                FROM `expenses` e
                WHERE e.`house_id` = h.`houseID`
                  AND e.`expense_date` BETWEEN '$dateFrom' AND '$dateTo'
            ) AS `direct_expenses`
        FROM `houses` h
        LEFT JOIN `tenants` t ON t.`houseNumber` = h.`houseID` AND t.`tenant_status` = 'Active'
        LEFT JOIN `payments` p
            ON p.`tenantID` = t.`tenantID`
           AND p.`dateofPayment` BETWEEN '$dateFrom' AND '$dateTo'
        GROUP BY h.`houseID`, h.`house_name`
        HAVING `rent_collected` > 0 OR `direct_expenses` > 0
        ORDER BY h.`house_name` ASC, h.`houseID` ASC
    ";

    $result = mysqli_query($connection, $sql);
    $rows = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rentCollected = (float) $row['rent_collected'];
            $directExpenses = (float) $row['direct_expenses'];
            $currentDueAmount = (float) $row['current_due_amount'];
            $advanceAmount = (float) $row['advance_amount'];
            $invoiceCount = (int) $row['invoice_count'];
            $statusLabel = 'Up to Date';
            $statusAmount = 0.0;

            if ($currentDueAmount > 0) {
                $statusLabel = 'Due';
                $statusAmount = $currentDueAmount;
            } elseif ($advanceAmount > 0) {
                $statusLabel = 'Advance';
                $statusAmount = $advanceAmount;
            } elseif ($invoiceCount > 0) {
                $statusLabel = 'Fully Paid';
            }

            $rows[] = [
                'houseID' => (int) $row['houseID'],
                'house_name' => isset($row['house_name']) ? (string) $row['house_name'] : '',
                'rent_collected' => $rentCollected,
                'direct_expenses' => $directExpenses,
                'net_earning' => $rentCollected - $directExpenses,
                'current_due_amount' => $currentDueAmount,
                'advance_amount' => $advanceAmount,
                'invoice_count' => $invoiceCount,
                'payment_status_label' => $statusLabel,
                'payment_status_amount' => $statusAmount,
            ];
        }
    }

    return $rows;
}

function get_partition_wise_report($connection, $dateFrom, $dateTo) {
    $dateFrom = mysqli_real_escape_string($connection, $dateFrom);
    $dateTo = mysqli_real_escape_string($connection, $dateTo);

    $sql = "
        SELECT
            hp.`partition_id`,
            hp.`partition_number`,
            hp.`partition_status`,
            h.`house_name`,
            COALESCE((
                SELECT SUM(CAST(p.`amountPaid` AS DECIMAL(10,2)))
                FROM `payments` p
                INNER JOIN `tenants` t ON p.`tenantID` = t.`tenantID`
                WHERE t.`partition_id` = hp.`partition_id`
                  AND p.`dateofPayment` BETWEEN '$dateFrom' AND '$dateTo'
            ), 0) AS `rent_collected`,
            COALESCE((
                SELECT SUM(e.`amount`)
                FROM `expenses` e
                WHERE e.`partition_id` = hp.`partition_id`
                  AND e.`expense_date` BETWEEN '$dateFrom' AND '$dateTo'
            ), 0) AS `direct_expenses`
        FROM `house_partitions` hp
        LEFT JOIN `houses` h ON hp.`house_id` = h.`houseID`
        ORDER BY h.`house_name` ASC, hp.`partition_number` ASC
    ";

    $result = mysqli_query($connection, $sql);
    $rows = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row['rent_collected'] = (float) $row['rent_collected'];
            $row['direct_expenses'] = (float) $row['direct_expenses'];
            $row['net_earning'] = $row['rent_collected'] - $row['direct_expenses'];
            $rows[] = $row;
        }
    }

    return $rows;
}

function get_tenant_wise_collection_report($connection, $dateFrom, $dateTo) {
    $dateFrom = mysqli_real_escape_string($connection, $dateFrom);
    $dateTo = mysqli_real_escape_string($connection, $dateTo);

    $sql = "
        SELECT
            t.`tenantID`,
            t.`tenant_name`,
            t.`email`,
            t.`account`,
            h.`house_name`,
            hp.`partition_number`,
            COALESCE(SUM(CAST(p.`amountPaid` AS DECIMAL(10,2))), 0) AS `rent_collected`,
            COUNT(DISTINCT p.`paymentID`) AS `payment_count`,
            COALESCE((
                SELECT SUM(
                    CASE
                        WHEN CAST(i2.`amountDue` AS DECIMAL(10,2)) > 0 THEN CAST(i2.`amountDue` AS DECIMAL(10,2))
                        ELSE 0
                    END
                )
                FROM `invoices` i2
                WHERE i2.`tenantID` = t.`tenantID`
            ), 0) AS `current_due_amount`,
            COALESCE((
                SELECT COUNT(*)
                FROM `invoices` i3
                WHERE i3.`tenantID` = t.`tenantID`
            ), 0) AS `invoice_count`
        FROM `tenants` t
        LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
        LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
        LEFT JOIN `payments` p
            ON p.`tenantID` = t.`tenantID`
           AND p.`dateofPayment` BETWEEN '$dateFrom' AND '$dateTo'
        WHERE t.`tenant_status` = 'Active'
        GROUP BY
            t.`tenantID`,
            t.`tenant_name`,
            t.`email`,
            h.`house_name`,
            hp.`partition_number`
        HAVING `rent_collected` > 0 OR `payment_count` > 0
        ORDER BY `rent_collected` DESC, t.`tenant_name` ASC, t.`tenantID` DESC
    ";

    $result = mysqli_query($connection, $sql);
    $rows = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $currentDueAmount = (float) $row['current_due_amount'];
            $advanceAmount = max(0, (float) $row['account']);
            $invoiceCount = (int) $row['invoice_count'];
            $statusLabel = 'Up to Date';
            $statusAmount = 0.0;

            if ($currentDueAmount > 0) {
                $statusLabel = 'Due';
                $statusAmount = $currentDueAmount;
            } elseif ($advanceAmount > 0) {
                $statusLabel = 'Advance';
                $statusAmount = $advanceAmount;
            } elseif ($invoiceCount > 0) {
                $statusLabel = 'Fully Paid';
            }

            $rows[] = [
                'tenantID' => (int) $row['tenantID'],
                'tenant_name' => isset($row['tenant_name']) ? (string) $row['tenant_name'] : '',
                'email' => isset($row['email']) ? (string) $row['email'] : '',
                'house_name' => isset($row['house_name']) ? (string) $row['house_name'] : '',
                'partition_number' => isset($row['partition_number']) ? (string) $row['partition_number'] : '',
                'rent_collected' => (float) $row['rent_collected'],
                'payment_count' => (int) $row['payment_count'],
                'current_due_amount' => $currentDueAmount,
                'advance_amount' => $advanceAmount,
                'invoice_count' => $invoiceCount,
                'payment_status_label' => $statusLabel,
                'payment_status_amount' => $statusAmount,
            ];
        }
    }

    return $rows;
}

function get_expense_house_options($connection) {
    return mysqli_query($connection, "SELECT `houseID`, `house_name`, `location` FROM `houses` ORDER BY `house_name` ASC");
}

function get_expense_partition_options($connection) {
    return mysqli_query($connection, "
        SELECT hp.`partition_id`, hp.`partition_number`, hp.`house_id`, h.`house_name`
        FROM `house_partitions` hp
        LEFT JOIN `houses` h ON hp.`house_id` = h.`houseID`
        ORDER BY h.`house_name` ASC, hp.`partition_number` ASC
    ");
}

?>
