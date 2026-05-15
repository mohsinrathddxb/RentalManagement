<?php

class SimplePdfDocument {
    private $pages = [];
    private $currentPage = -1;

    public function addPage($width = 595.28, $height = 841.89) {
        $this->pages[] = [
            'width' => $width,
            'height' => $height,
            'content' => ''
        ];
        $this->currentPage = count($this->pages) - 1;
    }

    public function text($x, $y, $text, $size = 12, $font = 'regular', $color = [0, 0, 0]) {
        if ($this->currentPage < 0) {
            $this->addPage();
        }

        $fontKey = $font === 'bold' ? 'F2' : 'F1';
        $pageHeight = $this->pages[$this->currentPage]['height'];
        $escaped = $this->escapeText($text);
        $pdfY = $pageHeight - $y;
        $rgb = $this->normalizeColor($color);

        $this->pages[$this->currentPage]['content'] .= sprintf(
            "BT /%s %.2F Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $fontKey,
            $size,
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $pdfY,
            $escaped
        );
    }

    public function line($x1, $y1, $x2, $y2, $width = 1, $color = [0, 0, 0]) {
        if ($this->currentPage < 0) {
            $this->addPage();
        }

        $pageHeight = $this->pages[$this->currentPage]['height'];
        $rgb = $this->normalizeColor($color);
        $pdfY1 = $pageHeight - $y1;
        $pdfY2 = $pageHeight - $y2;

        $this->pages[$this->currentPage]['content'] .= sprintf(
            "q %.2F w %.3F %.3F %.3F RG %.2F %.2F m %.2F %.2F l S Q\n",
            $width,
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x1,
            $pdfY1,
            $x2,
            $pdfY2
        );
    }

    public function rect($x, $y, $width, $height, $style = 'S', $lineColor = [0, 0, 0], $fillColor = [255, 255, 255], $lineWidth = 1) {
        if ($this->currentPage < 0) {
            $this->addPage();
        }

        $pageHeight = $this->pages[$this->currentPage]['height'];
        $strokeRgb = $this->normalizeColor($lineColor);
        $fillRgb = $this->normalizeColor($fillColor);
        $pdfY = $pageHeight - $y - $height;
        $operator = 'S';

        if ($style === 'F') {
            $operator = 'f';
        } elseif ($style === 'B') {
            $operator = 'B';
        }

        $this->pages[$this->currentPage]['content'] .= sprintf(
            "q %.2F w %.3F %.3F %.3F RG %.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re %s Q\n",
            $lineWidth,
            $strokeRgb[0],
            $strokeRgb[1],
            $strokeRgb[2],
            $fillRgb[0],
            $fillRgb[1],
            $fillRgb[2],
            $x,
            $pdfY,
            $width,
            $height,
            $operator
        );
    }

    public function render() {
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $pageObjectIds = [];
        $contentObjectIds = [];
        $nextId = 5;

        foreach ($this->pages as $page) {
            $contentId = $nextId++;
            $pageId = $nextId++;

            $stream = $page['content'];
            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
            $objects[$pageId] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>",
                $page['width'],
                $page['height'],
                $contentId
            );

            $contentObjectIds[] = $contentId;
            $pageObjectIds[] = $pageId;
        }

        $kids = [];
        foreach ($pageObjectIds as $pageId) {
            $kids[] = $pageId . " 0 R";
        }

        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($pageObjectIds) . " >>";
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $objectId => $objectBody) {
            $offsets[$objectId] = strlen($pdf);
            $pdf .= $objectId . " 0 obj\n" . $objectBody . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $objectCount = max(array_keys($objects));

        $pdf .= "xref\n";
        $pdf .= "0 " . ($objectCount + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $objectCount; $i++) {
            $offset = isset($offsets[$i]) ? $offsets[$i] : 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n";
        $pdf .= "<< /Size " . ($objectCount + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n";
        $pdf .= $xrefOffset . "\n";
        $pdf .= "%%EOF";

        return $pdf;
    }

    public function output($filename) {
        if (headers_sent()) {
            return false;
        }

        $pdf = $this->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));

        echo $pdf;
        return true;
    }

    private function escapeText($text) {
        $text = (string) $text;
        $text = str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", ' ', ' '], $text);
        return preg_replace('/[^\x20-\x7E]/', '', $text);
    }

    private function normalizeColor($color) {
        return [
            max(0, min(255, (int) $color[0])) / 255,
            max(0, min(255, (int) $color[1])) / 255,
            max(0, min(255, (int) $color[2])) / 255
        ];
    }
}

function ensure_invoice_pdf_columns($connection) {
    $columns = [
        'rent_amount' => "ALTER TABLE `invoices` ADD COLUMN `rent_amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `amountDue`",
        'booking_amount' => "ALTER TABLE `invoices` ADD COLUMN `booking_amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `rent_amount`",
        'deposit_amount' => "ALTER TABLE `invoices` ADD COLUMN `deposit_amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `booking_amount`",
        'credit_applied' => "ALTER TABLE `invoices` ADD COLUMN `credit_applied` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `deposit_amount`",
        'total_amount' => "ALTER TABLE `invoices` ADD COLUMN `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `credit_applied`"
    ];

    foreach ($columns as $column => $sql) {
        $result = mysqli_query($connection, "SHOW COLUMNS FROM `invoices` LIKE '$column'");
        if ($result && mysqli_num_rows($result) === 0) {
            @mysqli_query($connection, $sql);
        }
    }

    @mysqli_query($connection, "
        UPDATE `invoices` i
        SET
            i.`total_amount` = CASE
                WHEN i.`total_amount` = 0 THEN COALESCE(
                    (
                        SELECT MAX(CAST(p.`expectedAmount` AS DECIMAL(10,2)))
                        FROM `payments` p
                        WHERE p.`invoiceNumber` = i.`invoiceNumber`
                    ),
                    i.`amountDue`
                )
                ELSE i.`total_amount`
            END,
            i.`rent_amount` = CASE
                WHEN i.`rent_amount` = 0 THEN COALESCE(
                    (
                        SELECT MAX(CAST(p.`expectedAmount` AS DECIMAL(10,2)))
                        FROM `payments` p
                        WHERE p.`invoiceNumber` = i.`invoiceNumber`
                    ),
                    i.`amountDue`
                ) - i.`booking_amount` - i.`deposit_amount`
                ELSE i.`rent_amount`
            END
    ");
}

function pdf_mobile_token_secret() {
    global $telegram_bot_token, $database;

    return hash('sha256', (string) $telegram_bot_token . '|' . (string) $database . '|co-living-space-pdf');
}

function pdf_mobile_token($email, $type, $documentId) {
    $email = strtolower(trim((string) $email));
    $type = strtolower(trim((string) $type));
    $documentId = trim((string) $documentId);

    if ($email === '' || $type === '' || $documentId === '') {
        return '';
    }

    return hash_hmac('sha256', $email . '|' . $type . '|' . $documentId, pdf_mobile_token_secret());
}

function pdf_mobile_query_string($email, $type, $documentId) {
    $token = pdf_mobile_token($email, $type, $documentId);
    if ($token === '') {
        return '';
    }

    return '&mobile_user=' . rawurlencode(strtolower(trim((string) $email))) . '&mobile_token=' . rawurlencode($token);
}

function pdf_mobile_token_is_valid($email, $type, $documentId, $token) {
    $expected = pdf_mobile_token($email, $type, $documentId);
    return $expected !== '' && hash_equals($expected, (string) $token);
}

function get_invoice_line_items($invoiceRow) {
    $lineItems = [];

    $rentAmount = isset($invoiceRow['rent_amount']) ? (float) $invoiceRow['rent_amount'] : 0;
    $bookingAmount = isset($invoiceRow['booking_amount']) ? (float) $invoiceRow['booking_amount'] : 0;
    $depositAmount = isset($invoiceRow['deposit_amount']) ? (float) $invoiceRow['deposit_amount'] : 0;
    $creditApplied = isset($invoiceRow['credit_applied']) ? (float) $invoiceRow['credit_applied'] : 0;
    $fallbackTotal = isset($invoiceRow['total_amount']) ? (float) $invoiceRow['total_amount'] : (float) $invoiceRow['amountDue'];

    if ($rentAmount > 0) {
        $lineItems[] = ['description' => 'Monthly rent', 'quantity' => 1, 'unit_price' => $rentAmount, 'amount' => $rentAmount];
    }

    if ($bookingAmount > 0) {
        $lineItems[] = ['description' => 'Booking amount', 'quantity' => 1, 'unit_price' => $bookingAmount, 'amount' => $bookingAmount];
    }

    if ($depositAmount > 0) {
        $lineItems[] = ['description' => 'Security deposit', 'quantity' => 1, 'unit_price' => $depositAmount, 'amount' => $depositAmount];
    }

    if (empty($lineItems)) {
        $lineItems[] = ['description' => 'Rental charges', 'quantity' => 1, 'unit_price' => $fallbackTotal, 'amount' => $fallbackTotal];
    }

    if ($creditApplied > 0) {
        $lineItems[] = ['description' => 'Account credit applied', 'quantity' => 1, 'unit_price' => $creditApplied * -1, 'amount' => $creditApplied * -1];
    }

    return $lineItems;
}

function build_invoice_document_data($connection, $invoiceNumber, $tenantId = 0) {
    $invoiceNumber = mysqli_real_escape_string($connection, trim((string) $invoiceNumber));
    $tenantWhere = $tenantId > 0 ? " AND i.`tenantID`='" . (int) $tenantId . "'" : '';

    $sql = "
        SELECT
            i.*,
            t.`tenant_name`,
            t.`phone_number`,
            t.`email`,
            t.`telegram_username`,
            t.`telegram_chat_id`,
            t.`tenant_address`,
            t.`tenant_home_country_address`,
            t.`tenant_country`,
            h.`house_name`,
            hp.`partition_number`,
            COALESCE(hp.`rent_amount`, h.`rent_amount`, i.`rent_amount`) AS `current_rent_amount`,
            (
                SELECT COALESCE(SUM(p.`amountPaid`), 0)
                FROM `payments` p
                WHERE p.`invoiceNumber` = i.`invoiceNumber`
            ) AS `total_paid`,
            (
                SELECT MAX(p.`paymentID`)
                FROM `payments` p
                WHERE p.`invoiceNumber` = i.`invoiceNumber`
            ) AS `latest_payment_id`
        FROM `invoices` i
        LEFT JOIN `tenants` t ON i.`tenantID` = t.`tenantID`
        LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
        LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
        WHERE i.`invoiceNumber` = '$invoiceNumber' $tenantWhere
        LIMIT 1
    ";

    $query = mysqli_query($connection, $sql);
    if (!$query || mysqli_num_rows($query) === 0) {
        return null;
    }

    $row = mysqli_fetch_assoc($query);
    $row['line_items'] = get_invoice_line_items($row);
    return $row;
}

function build_payment_receipt_data($connection, $paymentId, $tenantId = 0) {
    $paymentId = (int) $paymentId;
    $tenantWhere = $tenantId > 0 ? " AND p.`tenantID`='" . (int) $tenantId . "'" : '';

    $sql = "
        SELECT
            p.*,
            i.`dateOfInvoice`,
            i.`dateDue`,
            i.`status` AS `invoice_status`,
            i.`rent_amount`,
            i.`booking_amount`,
            i.`deposit_amount`,
            i.`credit_applied`,
            i.`total_amount`,
            t.`tenant_name`,
            t.`phone_number`,
            t.`email`,
            t.`telegram_username`,
            t.`telegram_chat_id`,
            t.`tenant_address`,
            t.`tenant_home_country_address`,
            t.`tenant_country`,
            h.`house_name`,
            hp.`partition_number`,
            (
                SELECT COALESCE(SUM(p2.`amountPaid`), 0)
                FROM `payments` p2
                WHERE p2.`invoiceNumber` = p.`invoiceNumber`
                  AND p2.`paymentID` < p.`paymentID`
            ) AS `paid_before`,
            (
                SELECT COALESCE(SUM(p3.`amountPaid`), 0)
                FROM `payments` p3
                WHERE p3.`invoiceNumber` = p.`invoiceNumber`
                  AND p3.`paymentID` <= p.`paymentID`
            ) AS `paid_through_this_receipt`
        FROM `payments` p
        LEFT JOIN `invoices` i ON p.`invoiceNumber` = i.`invoiceNumber`
        LEFT JOIN `tenants` t ON p.`tenantID` = t.`tenantID`
        LEFT JOIN `houses` h ON t.`houseNumber` = h.`houseID`
        LEFT JOIN `house_partitions` hp ON t.`partition_id` = hp.`partition_id`
        WHERE p.`paymentID` = '$paymentId' $tenantWhere
        LIMIT 1
    ";

    $query = mysqli_query($connection, $sql);
    if (!$query || mysqli_num_rows($query) === 0) {
        return null;
    }

    $row = mysqli_fetch_assoc($query);
    $row['line_items'] = get_invoice_line_items($row);
    return $row;
}

function pdf_money($value) {
    return 'AED ' . format_money_amount($value);
}

function pdf_unit_label($houseName, $partitionNumber) {
    $houseName = trim((string) $houseName);
    $partitionNumber = trim((string) $partitionNumber);

    if ($houseName !== '' && $partitionNumber !== '') {
        return $houseName . ' / ' . $partitionNumber;
    }

    if ($houseName !== '') {
        return $houseName;
    }

    if ($partitionNumber !== '') {
        return $partitionNumber;
    }

    return 'Not specified';
}

function pdf_estimate_text_width($text, $fontSize) {
    return strlen((string) $text) * ($fontSize * 0.55);
}

function pdf_center_text_x($text, $fontSize, $boxX, $boxWidth) {
    $estimatedWidth = pdf_estimate_text_width($text, $fontSize);
    return $boxX + max(0, (($boxWidth - $estimatedWidth) / 2));
}

function pdf_wrap_text($text, $maxChars = 46) {
    $text = trim((string) $text);
    if ($text === '') {
        return [];
    }

    $wrapped = wordwrap($text, $maxChars, "\n", true);
    return explode("\n", $wrapped);
}

function pdf_draw_text_block($pdf, $x, $y, $lines, $size = 11, $font = 'regular', $color = [0, 0, 0], $lineHeight = 15) {
    $currentY = $y;
    foreach ($lines as $line) {
        $pdf->text($x, $currentY, $line, $size, $font, $color);
        $currentY += $lineHeight;
    }
    return $currentY;
}

function pdf_draw_brand_logo($pdf, $x, $y, $scale = 0.42) {
    $gold = [200, 164, 73];
    $goldLight = [238, 204, 110];
    $goldDark = [162, 124, 42];
    $linen = [250, 244, 231];

    $sx = function ($value) use ($x, $scale) {
        return $x + ($value * $scale);
    };
    $sy = function ($value) use ($y, $scale) {
        return $y + ($value * $scale);
    };
    $sw = function ($value) use ($scale) {
        return $value * $scale;
    };

    $pdf->line($sx(84), $sy(126), $sx(188), $sy(24), $sw(18), $gold);
    $pdf->line($sx(188), $sy(24), $sx(296), $sy(126), $sw(18), $gold);
    $pdf->line($sx(95), $sy(126), $sx(188), $sy(36), $sw(5), $goldLight);
    $pdf->line($sx(188), $sy(36), $sx(286), $sy(126), $sw(5), $goldLight);
    $pdf->line($sx(72), $sy(134), $sx(88), $sy(134), $sw(12), $gold);
    $pdf->line($sx(288), $sy(134), $sx(306), $sy(134), $sw(12), $gold);
    $pdf->line($sx(84), $sy(134), $sx(84), $sy(266), $sw(18), $gold);
    $pdf->line($sx(84), $sy(266), $sx(202), $sy(266), $sw(18), $goldDark);
    $pdf->line($sx(296), $sy(134), $sx(296), $sy(246), $sw(18), $gold);
    $pdf->line($sx(296), $sy(246), $sx(248), $sy(246), $sw(18), $goldDark);
    $pdf->line($sx(296), $sy(154), $sx(324), $sy(184), $sw(18), $gold);

    $pdf->line($sx(130), $sy(176), $sx(188), $sy(118), $sw(7), $goldLight);
    $pdf->line($sx(188), $sy(118), $sx(248), $sy(176), $sw(7), $gold);
    $pdf->line($sx(134), $sy(176), $sx(244), $sy(176), $sw(5), $goldDark);
    $pdf->line($sx(136), $sy(176), $sx(136), $sy(248), $sw(5), $goldLight);
    $pdf->line($sx(244), $sy(176), $sx(244), $sy(248), $sw(5), $goldDark);
    $pdf->rect($sx(140), $sy(204), $sw(104), $sw(44), 'B', $goldDark, $linen, $sw(2));
    $pdf->rect($sx(142), $sy(226), $sw(102), $sw(34), 'B', $goldDark, [236, 220, 184], $sw(2));
    $pdf->rect($sx(146), $sy(208), $sw(48), $sw(22), 'B', $gold, [255, 248, 231], $sw(1.5));

    $pdf->text(pdf_center_text_x('CO-LIVING SPACE', 13, $sx(0), $sw(380)), $sy(338), 'CO-LIVING SPACE', 13, 'bold', $gold);
    $pdf->line($sx(166), $sy(360), $sx(214), $sy(360), $sw(1.2), $gold);
}

function build_invoice_pdf_document($invoiceRow) {
    $pdf = new SimplePdfDocument();
    $pdf->addPage();

    $left = 48;
    $top = 56;
    $pageWidth = 595.28;
    $right = $pageWidth - 48;
    $brandBlue = [7, 26, 45];
    $brandGold = [200, 164, 73];
    $softGray = [246, 242, 232];
    $darkGray = [76, 84, 96];
    $logoBoxX = $right - 160;
    $logoBoxY = $top - 18;

    $tenantAddressLines = [];
    if (!empty($invoiceRow['tenant_name'])) {
        $tenantAddressLines[] = $invoiceRow['tenant_name'];
    }
    if (!empty($invoiceRow['tenant_address'])) {
        $tenantAddressLines[] = $invoiceRow['tenant_address'];
    }
    if (!empty($invoiceRow['tenant_home_country_address'])) {
        $tenantAddressLines[] = $invoiceRow['tenant_home_country_address'];
    }
    if (!empty($invoiceRow['tenant_country'])) {
        $tenantAddressLines[] = $invoiceRow['tenant_country'];
    }
    if (!empty($invoiceRow['phone_number'])) {
        $tenantAddressLines[] = $invoiceRow['phone_number'];
    }

    $lineItems = $invoiceRow['line_items'];
    $totalAmount = isset($invoiceRow['total_amount']) ? (float) $invoiceRow['total_amount'] : (float) $invoiceRow['amountDue'];
    $amountDue = isset($invoiceRow['amountDue']) ? (float) $invoiceRow['amountDue'] : 0;
    $totalPaid = isset($invoiceRow['total_paid']) ? (float) $invoiceRow['total_paid'] : 0;
    $creditApplied = isset($invoiceRow['credit_applied']) ? (float) $invoiceRow['credit_applied'] : 0;

    $pdf->text($left, $top, 'INVOICE', 24, 'bold', $brandBlue);
    pdf_draw_brand_logo($pdf, $logoBoxX, $logoBoxY, 0.32);

    $pdf->text($left, $top + 60, 'Co-Living Space Rental Management', 11, 'bold', $brandGold);
    $pdf->text($left, $top + 76, 'Generated from your local rental management system', 10, 'regular', $darkGray);

    $pdf->text($left, $top + 120, 'BILL TO', 11, 'bold');
    pdf_draw_text_block($pdf, $left, $top + 138, $tenantAddressLines, 11, 'regular', [0, 0, 0], 14);

    $metaX = 388;
    $metaY = $top + 120;
    $pdf->text($metaX, $metaY, 'Invoice No.:', 11, 'regular');
    $pdf->text($metaX + 95, $metaY, (string) $invoiceRow['invoiceNumber'], 11, 'bold');
    $pdf->text($metaX, $metaY + 18, 'Issue date:', 11, 'regular');
    $pdf->text($metaX + 95, $metaY + 18, (string) $invoiceRow['dateOfInvoice'], 11, 'bold');
    $pdf->text($metaX, $metaY + 36, 'Due date:', 11, 'regular');
    $pdf->text($metaX + 95, $metaY + 36, (string) $invoiceRow['dateDue'], 11, 'bold');
    $pdf->text($metaX, $metaY + 54, 'House / unit:', 11, 'regular');
    $pdf->text($metaX + 95, $metaY + 54, pdf_unit_label(isset($invoiceRow['house_name']) ? $invoiceRow['house_name'] : '', isset($invoiceRow['partition_number']) ? $invoiceRow['partition_number'] : ''), 11, 'bold');

    $tableTop = $top + 230;
    $colX = [$left, 285, 395, 500];
    $pdf->rect($left - 6, $tableTop - 16, 500, 26, 'B', $brandBlue, $brandBlue, 0.8);
    $pdf->text($left + 4, $tableTop, 'DESCRIPTION', 10, 'bold', [246, 242, 232]);
    $pdf->text($colX[1] - 2, $tableTop, 'QTY', 10, 'bold', [246, 242, 232]);
    $pdf->text($colX[2] - 2, $tableTop, 'UNIT PRICE', 10, 'bold', [246, 242, 232]);
    $pdf->text($colX[3] - 2, $tableTop, 'AMOUNT', 10, 'bold', [246, 242, 232]);

    $rowY = $tableTop + 28;
    foreach ($lineItems as $item) {
        $pdf->line($left - 6, $rowY + 10, $right - 18, $rowY + 10, 0.5, [230, 232, 236]);
        $pdf->text($left + 4, $rowY, $item['description'], 11);
        $pdf->text($colX[1] + 10, $rowY, (string) $item['quantity'], 11);
        $pdf->text($colX[2] - 2, $rowY, pdf_money($item['unit_price']), 11);
        $pdf->text($colX[3] - 2, $rowY, pdf_money($item['amount']), 11);
        $rowY += 22;
    }

    $summaryTop = $rowY + 16;
    $summaryX = 280;
    $summaryWidth = 214;
    $summaryValueX = $summaryX + 136;
    $pdf->rect($summaryX, $summaryTop, $summaryWidth, 28, 'B', [220, 223, 228], $softGray, 0.8);
    $pdf->text($summaryX + 10, $summaryTop + 18, 'TOTAL CHARGES', 10, 'bold');
    $pdf->text($summaryValueX, $summaryTop + 18, pdf_money($totalAmount), 10, 'bold');

    $pdf->rect($summaryX, $summaryTop + 28, $summaryWidth, 28, 'B', [220, 223, 228], [250, 250, 250], 0.8);
    $pdf->text($summaryX + 10, $summaryTop + 46, 'PAID TO DATE', 10, 'bold');
    $pdf->text($summaryValueX, $summaryTop + 46, pdf_money($totalPaid), 10, 'bold');

    if ($creditApplied > 0) {
        $pdf->rect($summaryX, $summaryTop + 56, $summaryWidth, 28, 'B', [220, 223, 228], [250, 250, 250], 0.8);
        $pdf->text($summaryX + 10, $summaryTop + 74, 'CREDIT APPLIED', 10, 'bold');
        $pdf->text($summaryValueX, $summaryTop + 74, pdf_money($creditApplied), 10, 'bold');
        $summaryFooterTop = $summaryTop + 84;
    } else {
        $summaryFooterTop = $summaryTop + 56;
    }

    $pdf->rect($summaryX, $summaryFooterTop, $summaryWidth, 34, 'B', $brandBlue, $brandBlue, 0.8);
    $pdf->text($summaryX + 10, $summaryFooterTop + 22, 'TOTAL DUE', 11, 'bold', [255, 255, 255]);
    $pdf->text($summaryValueX, $summaryFooterTop + 22, pdf_money($amountDue), 11, 'bold', [255, 255, 255]);

    $footerTop = $summaryFooterTop + 72;
    $pdf->text($left, $footerTop, 'Invoice status: ' . ucwords((string) $invoiceRow['status']), 10, 'bold', $darkGray);

    $commentLines = pdf_wrap_text(isset($invoiceRow['comment']) ? $invoiceRow['comment'] : '', 80);
    if (!empty($commentLines)) {
        $pdf->text($left, $footerTop + 22, 'Notes', 10, 'bold', $darkGray);
        pdf_draw_text_block($pdf, $left, $footerTop + 38, $commentLines, 10, 'regular', $darkGray, 13);
    }

    return $pdf;
}

function render_invoice_pdf($invoiceRow) {
    $pdf = build_invoice_pdf_document($invoiceRow);
    return $pdf->output('invoice-' . $invoiceRow['invoiceNumber'] . '.pdf');
}

function generate_invoice_pdf_binary($invoiceRow) {
    $pdf = build_invoice_pdf_document($invoiceRow);
    return $pdf->render();
}

function build_payment_receipt_pdf_document($paymentRow) {
    $pdf = new SimplePdfDocument();
    $pdf->addPage();

    $left = 48;
    $top = 56;
    $pageWidth = 595.28;
    $right = $pageWidth - 48;
    $brandBlue = [7, 26, 45];
    $brandGold = [200, 164, 73];
    $softGray = [246, 242, 232];
    $darkGray = [76, 84, 96];
    $logoBoxX = $right - 160;
    $logoBoxY = $top - 18;

    $tenantAddressLines = [];
    if (!empty($paymentRow['tenant_name'])) {
        $tenantAddressLines[] = $paymentRow['tenant_name'];
    }
    if (!empty($paymentRow['tenant_address'])) {
        $tenantAddressLines[] = $paymentRow['tenant_address'];
    }
    if (!empty($paymentRow['tenant_home_country_address'])) {
        $tenantAddressLines[] = $paymentRow['tenant_home_country_address'];
    }
    if (!empty($paymentRow['tenant_country'])) {
        $tenantAddressLines[] = $paymentRow['tenant_country'];
    }
    if (!empty($paymentRow['phone_number'])) {
        $tenantAddressLines[] = $paymentRow['phone_number'];
    }

    $lineItems = $paymentRow['line_items'];
    $paymentAmount = (float) $paymentRow['amountPaid'];
    $remainingBalance = (float) $paymentRow['balance'];
    $invoiceTotal = isset($paymentRow['total_amount']) ? (float) $paymentRow['total_amount'] : (float) $paymentRow['expectedAmount'];
    $paidBefore = isset($paymentRow['paid_before']) ? (float) $paymentRow['paid_before'] : 0;
    $paidThrough = isset($paymentRow['paid_through_this_receipt']) ? (float) $paymentRow['paid_through_this_receipt'] : $paymentAmount;

    $pdf->text($left, $top, 'PAYMENT RECEIPT', 24, 'bold', $brandBlue);
    pdf_draw_brand_logo($pdf, $logoBoxX, $logoBoxY, 0.32);

    $pdf->text($left, $top + 60, 'Receipt for rental payment received', 11, 'bold', $brandGold);
    $pdf->text($left, $top + 76, 'Generated from your local rental management system', 10, 'regular', $darkGray);

    $pdf->text($left, $top + 120, 'RECEIVED FROM', 11, 'bold');
    pdf_draw_text_block($pdf, $left, $top + 138, $tenantAddressLines, 11, 'regular', [0, 0, 0], 14);

    $metaX = 388;
    $metaY = $top + 120;
    $pdf->text($metaX, $metaY, 'Receipt No.:', 11, 'regular');
    $pdf->text($metaX + 95, $metaY, 'RCPT-' . $paymentRow['paymentID'], 11, 'bold');
    $pdf->text($metaX, $metaY + 18, 'Invoice No.:', 11, 'regular');
    $pdf->text($metaX + 95, $metaY + 18, (string) $paymentRow['invoiceNumber'], 11, 'bold');
    $pdf->text($metaX, $metaY + 36, 'Paid date:', 11, 'regular');
    $pdf->text($metaX + 95, $metaY + 36, (string) $paymentRow['dateofPayment'], 11, 'bold');
    $pdf->text($metaX, $metaY + 54, 'House / unit:', 11, 'regular');
    $pdf->text($metaX + 95, $metaY + 54, pdf_unit_label(isset($paymentRow['house_name']) ? $paymentRow['house_name'] : '', isset($paymentRow['partition_number']) ? $paymentRow['partition_number'] : ''), 11, 'bold');

    $tableTop = $top + 230;
    $colX = [$left, 285, 395, 500];
    $pdf->rect($left - 6, $tableTop - 16, 500, 26, 'B', $brandBlue, $brandBlue, 0.8);
    $pdf->text($left + 4, $tableTop, 'DESCRIPTION', 10, 'bold', [246, 242, 232]);
    $pdf->text($colX[1] - 2, $tableTop, 'QTY', 10, 'bold', [246, 242, 232]);
    $pdf->text($colX[2] - 2, $tableTop, 'UNIT PRICE', 10, 'bold', [246, 242, 232]);
    $pdf->text($colX[3] - 2, $tableTop, 'AMOUNT', 10, 'bold', [246, 242, 232]);

    $rowY = $tableTop + 28;
    foreach ($lineItems as $item) {
        $pdf->line($left - 6, $rowY + 10, $right - 18, $rowY + 10, 0.5, [230, 232, 236]);
        $pdf->text($left + 4, $rowY, $item['description'], 11);
        $pdf->text($colX[1] + 10, $rowY, (string) $item['quantity'], 11);
        $pdf->text($colX[2] - 2, $rowY, pdf_money($item['unit_price']), 11);
        $pdf->text($colX[3] - 2, $rowY, pdf_money($item['amount']), 11);
        $rowY += 22;
    }

    $summaryTop = $rowY + 16;
    $summaryX = 266;
    $summaryWidth = 228;
    $summaryValueX = $summaryX + 148;
    $summaryRows = [
        ['label' => 'INVOICE TOTAL', 'value' => pdf_money($invoiceTotal), 'fill' => $softGray, 'textColor' => [0, 0, 0]],
        ['label' => 'PAID BEFORE', 'value' => pdf_money($paidBefore), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
        ['label' => 'THIS PAYMENT', 'value' => pdf_money($paymentAmount), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
        ['label' => 'PAID TO DATE', 'value' => pdf_money($paidThrough), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
        ['label' => 'BALANCE DUE', 'value' => pdf_money($remainingBalance), 'fill' => $brandBlue, 'textColor' => [255, 255, 255]]
    ];

    $rowIndex = 0;
    foreach ($summaryRows as $summaryRow) {
        $blockY = $summaryTop + ($rowIndex * 28);
        $pdf->rect($summaryX, $blockY, $summaryWidth, 28, 'B', [220, 223, 228], $summaryRow['fill'], 0.8);
        $pdf->text($summaryX + 10, $blockY + 18, $summaryRow['label'], 10, 'bold', $summaryRow['textColor']);
        $pdf->text($summaryValueX, $blockY + 18, $summaryRow['value'], 10, 'bold', $summaryRow['textColor']);
        $rowIndex++;
    }

    $footerTop = $summaryTop + ($rowIndex * 28) + 28;
    $statusLine = 'Invoice status after payment: ' . ucwords((string) $paymentRow['invoice_status']);
    $pdf->text($left, $footerTop, $statusLine, 10, 'bold', $darkGray);

    $commentLines = pdf_wrap_text(isset($paymentRow['comment']) ? $paymentRow['comment'] : '', 80);
    if (!empty($commentLines)) {
        $pdf->text($left, $footerTop + 22, 'Payment note', 10, 'bold', $darkGray);
        pdf_draw_text_block($pdf, $left, $footerTop + 38, $commentLines, 10, 'regular', $darkGray, 13);
    }

    return $pdf;
}

function render_payment_receipt_pdf($paymentRow) {
    $pdf = build_payment_receipt_pdf_document($paymentRow);
    return $pdf->output('payment-receipt-' . $paymentRow['paymentID'] . '.pdf');
}

function generate_payment_receipt_pdf_binary($paymentRow) {
    $pdf = build_payment_receipt_pdf_document($paymentRow);
    return $pdf->render();
}
