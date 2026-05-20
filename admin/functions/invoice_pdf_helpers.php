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

function invoice_value_to_cents($value) {
    if (function_exists('money_to_cents')) {
        return money_to_cents((string) $value);
    }

    return (int) round(((float) $value) * 100);
}

function invoice_cents_to_float($value) {
    return ((int) $value) / 100;
}

function invoice_base_components_from_row($invoiceRow) {
    $rentCents = max(0, invoice_value_to_cents(isset($invoiceRow['rent_amount']) ? $invoiceRow['rent_amount'] : 0));
    $depositCents = max(0, invoice_value_to_cents(isset($invoiceRow['deposit_amount']) ? $invoiceRow['deposit_amount'] : 0));
    $bookingCents = max(0, invoice_value_to_cents(isset($invoiceRow['booking_amount']) ? $invoiceRow['booking_amount'] : 0));
    $fallbackTotalCents = max(
        0,
        invoice_value_to_cents(isset($invoiceRow['total_amount']) ? $invoiceRow['total_amount'] : 0),
        invoice_value_to_cents(isset($invoiceRow['amountDue']) ? $invoiceRow['amountDue'] : 0)
    );

    $components = [
        [
            'key' => 'deposit',
            'label' => 'Deposit',
            'total_cents' => $depositCents + $bookingCents,
        ],
        [
            'key' => 'rent',
            'label' => 'Monthly rent',
            'total_cents' => $rentCents,
        ],
    ];

    $knownTotalCents = $rentCents + $depositCents + $bookingCents;
    if ($knownTotalCents <= 0 && $fallbackTotalCents > 0) {
        $components[1]['total_cents'] = $fallbackTotalCents;
        $knownTotalCents = $fallbackTotalCents;
    } elseif ($fallbackTotalCents > $knownTotalCents) {
        $components[1]['total_cents'] += $fallbackTotalCents - $knownTotalCents;
        $knownTotalCents = $fallbackTotalCents;
    }

    return [$components, $knownTotalCents];
}

function invoice_allocate_amount(&$components, $amountCents, $bucketKey) {
    $remaining = max(0, (int) $amountCents);

    foreach ($components as &$component) {
        $alreadyAllocated = isset($component[$bucketKey]) ? (int) $component[$bucketKey] : 0;
        $capacity = max(0, (int) $component['total_cents'] - $alreadyAllocated);
        $applied = min($capacity, $remaining);
        $component[$bucketKey] = $alreadyAllocated + $applied;
        $remaining -= $applied;

        if ($remaining <= 0) {
            break;
        }
    }
    unset($component);

    return $remaining;
}

function summarize_invoice_financials($invoiceRow, $cashPaidOverrideCents = null) {
    list($components, $chargeTotalCents) = invoice_base_components_from_row($invoiceRow);

    foreach ($components as &$component) {
        $component['credit_applied_cents'] = 0;
        $component['cash_paid_cents'] = 0;
    }
    unset($component);

    $creditAppliedCents = min(
        $chargeTotalCents,
        max(0, invoice_value_to_cents(isset($invoiceRow['credit_applied']) ? $invoiceRow['credit_applied'] : 0))
    );
    $rawCashPaidCents = $cashPaidOverrideCents === null
        ? max(0, invoice_value_to_cents(isset($invoiceRow['total_paid']) ? $invoiceRow['total_paid'] : 0))
        : max(0, (int) $cashPaidOverrideCents);
    $cashApplicableCents = min(max(0, $chargeTotalCents - $creditAppliedCents), $rawCashPaidCents);
    $extraAdvanceCents = max(0, $rawCashPaidCents - $cashApplicableCents);

    invoice_allocate_amount($components, $creditAppliedCents, 'credit_applied_cents');

    foreach ($components as &$component) {
        $component['remaining_after_credit_cents'] = max(0, (int) $component['total_cents'] - (int) $component['credit_applied_cents']);
    }
    unset($component);

    $remainingCashToAllocate = $cashApplicableCents;
    foreach ($components as &$component) {
        $cashApplied = min((int) $component['remaining_after_credit_cents'], $remainingCashToAllocate);
        $component['cash_paid_cents'] = $cashApplied;
        $component['remaining_due_cents'] = max(0, (int) $component['remaining_after_credit_cents'] - $cashApplied);
        $remainingCashToAllocate -= $cashApplied;
    }
    unset($component);

    $remainingDueCents = 0;
    $rentDueCents = 0;
    $depositDueCents = 0;
    $rentPaidCents = 0;
    $depositPaidCents = 0;
    $rentCreditCents = 0;
    $depositCreditCents = 0;
    $displayComponents = [];

    foreach ($components as $component) {
        if ((int) $component['total_cents'] <= 0) {
            continue;
        }

        $remainingDueCents += (int) $component['remaining_due_cents'];
        $paidCents = (int) $component['credit_applied_cents'] + (int) $component['cash_paid_cents'];
        $displayComponents[] = [
            'key' => $component['key'],
            'label' => $component['label'],
            'total' => invoice_cents_to_float($component['total_cents']),
            'credit_applied' => invoice_cents_to_float($component['credit_applied_cents']),
            'cash_paid' => invoice_cents_to_float($component['cash_paid_cents']),
            'paid_total' => invoice_cents_to_float($paidCents),
            'remaining_due' => invoice_cents_to_float($component['remaining_due_cents']),
        ];

        if ($component['key'] === 'rent') {
            $rentDueCents = (int) $component['remaining_due_cents'];
            $rentPaidCents = (int) $component['cash_paid_cents'];
            $rentCreditCents = (int) $component['credit_applied_cents'];
        } elseif ($component['key'] === 'deposit') {
            $depositDueCents = (int) $component['remaining_due_cents'];
            $depositPaidCents = (int) $component['cash_paid_cents'];
            $depositCreditCents = (int) $component['credit_applied_cents'];
        }
    }

    $settledCents = min($chargeTotalCents, $creditAppliedCents + $cashApplicableCents);
    $status = 'unpaid';
    if ($remainingDueCents <= 0 && $chargeTotalCents > 0) {
        $status = 'paid';
    } elseif ($settledCents > 0) {
        $status = 'partial paid';
    }

    return [
        'components' => $displayComponents,
        'charge_total' => invoice_cents_to_float($chargeTotalCents),
        'charge_total_cents' => $chargeTotalCents,
        'credit_applied' => invoice_cents_to_float($creditAppliedCents),
        'credit_applied_cents' => $creditAppliedCents,
        'cash_paid' => invoice_cents_to_float($cashApplicableCents),
        'cash_paid_cents' => $cashApplicableCents,
        'remaining_due' => invoice_cents_to_float($remainingDueCents),
        'remaining_due_cents' => $remainingDueCents,
        'extra_advance' => invoice_cents_to_float($extraAdvanceCents),
        'extra_advance_cents' => $extraAdvanceCents,
        'status' => $status,
        'rent_due' => invoice_cents_to_float($rentDueCents),
        'rent_due_cents' => $rentDueCents,
        'deposit_due' => invoice_cents_to_float($depositDueCents),
        'deposit_due_cents' => $depositDueCents,
        'rent_paid' => invoice_cents_to_float($rentPaidCents),
        'rent_paid_cents' => $rentPaidCents,
        'deposit_paid' => invoice_cents_to_float($depositPaidCents),
        'deposit_paid_cents' => $depositPaidCents,
        'rent_credit' => invoice_cents_to_float($rentCreditCents),
        'rent_credit_cents' => $rentCreditCents,
        'deposit_credit' => invoice_cents_to_float($depositCreditCents),
        'deposit_credit_cents' => $depositCreditCents,
        'booking_due' => 0.0,
        'booking_due_cents' => 0,
        'booking_paid' => 0.0,
        'booking_paid_cents' => 0,
        'booking_credit' => 0.0,
        'booking_credit_cents' => 0,
    ];
}

function summarize_payment_allocation($paymentRow) {
    $paidBeforeCents = max(0, invoice_value_to_cents(isset($paymentRow['paid_before']) ? $paymentRow['paid_before'] : 0));
    $paidThroughCents = max(0, invoice_value_to_cents(isset($paymentRow['paid_through_this_receipt']) ? $paymentRow['paid_through_this_receipt'] : 0));
    $paymentCents = max(0, invoice_value_to_cents(isset($paymentRow['amountPaid']) ? $paymentRow['amountPaid'] : 0));

    $before = summarize_invoice_financials($paymentRow, $paidBeforeCents);
    $after = summarize_invoice_financials($paymentRow, $paidThroughCents);

    $componentMap = [];
    foreach ($before['components'] as $component) {
        $componentMap[$component['key']]['before'] = $component;
    }
    foreach ($after['components'] as $component) {
        $componentMap[$component['key']]['after'] = $component;
    }

    $receiptItems = [];
    foreach ($componentMap as $key => $componentState) {
        $beforeCash = isset($componentState['before']) ? invoice_value_to_cents($componentState['before']['cash_paid']) : 0;
        $afterCash = isset($componentState['after']) ? invoice_value_to_cents($componentState['after']['cash_paid']) : 0;
        $appliedThisReceiptCents = max(0, $afterCash - $beforeCash);
        if ($appliedThisReceiptCents <= 0) {
            continue;
        }

        $label = isset($componentState['after']['label'])
            ? $componentState['after']['label']
            : (isset($componentState['before']['label']) ? $componentState['before']['label'] : ucwords((string) $key));
        $receiptItems[] = [
            'key' => $key,
            'label' => $label,
            'amount' => invoice_cents_to_float($appliedThisReceiptCents),
            'amount_cents' => $appliedThisReceiptCents,
        ];
    }

    $appliedToInvoiceCents = 0;
    foreach ($receiptItems as $receiptItem) {
        $appliedToInvoiceCents += (int) $receiptItem['amount_cents'];
    }

    $advanceCreatedCents = max(0, $paymentCents - $appliedToInvoiceCents);

    return [
        'before' => $before,
        'after' => $after,
        'receipt_items' => $receiptItems,
        'applied_to_invoice' => invoice_cents_to_float($appliedToInvoiceCents),
        'applied_to_invoice_cents' => $appliedToInvoiceCents,
        'advance_created' => invoice_cents_to_float($advanceCreatedCents),
        'advance_created_cents' => $advanceCreatedCents,
    ];
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
    $financials = summarize_invoice_financials($invoiceRow);

    foreach ($financials['components'] as $component) {
        $lineItems[] = [
            'description' => $component['label'],
            'quantity' => 1,
            'unit_price' => $component['total'],
            'amount' => $component['total'],
        ];
    }

    if (empty($lineItems)) {
        $lineItems[] = [
            'description' => 'Rental charges',
            'quantity' => 1,
            'unit_price' => $financials['charge_total'],
            'amount' => $financials['charge_total'],
        ];
    }

    if ($financials['credit_applied'] > 0) {
        $lineItems[] = [
            'description' => 'Advance credit applied',
            'quantity' => 1,
            'unit_price' => $financials['credit_applied'] * -1,
            'amount' => $financials['credit_applied'] * -1,
        ];
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
    $row['financials'] = summarize_invoice_financials($row);
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
    $row['financials'] = summarize_invoice_financials($row, max(0, invoice_value_to_cents(isset($row['paid_through_this_receipt']) ? $row['paid_through_this_receipt'] : 0)));
    $row['payment_allocation'] = summarize_payment_allocation($row);
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
    $financials = isset($invoiceRow['financials']) && is_array($invoiceRow['financials'])
        ? $invoiceRow['financials']
        : summarize_invoice_financials($invoiceRow);
    $totalAmount = (float) $financials['charge_total'];
    $amountDue = (float) $financials['remaining_due'];
    $totalPaid = (float) $financials['cash_paid'];
    $creditApplied = (float) $financials['credit_applied'];

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
    $summaryRows = [
        ['label' => 'TOTAL CHARGES', 'value' => pdf_money($totalAmount), 'fill' => $softGray, 'textColor' => [0, 0, 0]],
        ['label' => 'ADVANCE APPLIED', 'value' => pdf_money($creditApplied), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
        ['label' => 'CASH RECEIVED', 'value' => pdf_money($totalPaid), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
        ['label' => 'RENT REMAINING', 'value' => pdf_money($financials['rent_due']), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
        ['label' => 'DEPOSIT DUE', 'value' => pdf_money($financials['deposit_due']), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
    ];

    $summaryOffset = 0;
    foreach ($summaryRows as $summaryRow) {
        $blockY = $summaryTop + $summaryOffset;
        $pdf->rect($summaryX, $blockY, $summaryWidth, 28, 'B', [220, 223, 228], $summaryRow['fill'], 0.8);
        $pdf->text($summaryX + 10, $blockY + 18, $summaryRow['label'], 10, 'bold', $summaryRow['textColor']);
        $pdf->text($summaryValueX, $blockY + 18, $summaryRow['value'], 10, 'bold', $summaryRow['textColor']);
        $summaryOffset += 28;
    }

    $summaryFooterTop = $summaryTop + $summaryOffset;

    $pdf->rect($summaryX, $summaryFooterTop, $summaryWidth, 34, 'B', $brandBlue, $brandBlue, 0.8);
    $pdf->text($summaryX + 10, $summaryFooterTop + 22, 'TOTAL DUE', 11, 'bold', [255, 255, 255]);
    $pdf->text($summaryValueX, $summaryFooterTop + 22, pdf_money($amountDue), 11, 'bold', [255, 255, 255]);

    $footerTop = $summaryFooterTop + 72;
    $pdf->text($left, $footerTop, 'Invoice status: ' . ucwords((string) $financials['status']), 10, 'bold', $darkGray);

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

    $paymentAllocation = isset($paymentRow['payment_allocation']) && is_array($paymentRow['payment_allocation'])
        ? $paymentRow['payment_allocation']
        : summarize_payment_allocation($paymentRow);
    $lineItems = [];
    foreach ($paymentAllocation['receipt_items'] as $receiptItem) {
        $lineItems[] = [
            'description' => $receiptItem['label'],
            'quantity' => 1,
            'unit_price' => $receiptItem['amount'],
            'amount' => $receiptItem['amount'],
        ];
    }
    if ((float) $paymentAllocation['advance_created'] > 0) {
        $lineItems[] = [
            'description' => 'Added to tenant advance',
            'quantity' => 1,
            'unit_price' => $paymentAllocation['advance_created'],
            'amount' => $paymentAllocation['advance_created'],
        ];
    }
    if (empty($lineItems)) {
        $lineItems[] = [
            'description' => 'Payment received',
            'quantity' => 1,
            'unit_price' => (float) $paymentRow['amountPaid'],
            'amount' => (float) $paymentRow['amountPaid'],
        ];
    }
    $paymentAmount = (float) $paymentRow['amountPaid'];
    $remainingBalance = (float) $paymentAllocation['after']['remaining_due'];
    $invoiceTotal = (float) $paymentAllocation['after']['charge_total'];
    $paidBefore = (float) $paymentAllocation['before']['cash_paid'];
    $paidThrough = (float) $paymentAllocation['after']['cash_paid'];

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
        ['label' => 'ADVANCE APPLIED', 'value' => pdf_money($paymentAllocation['after']['credit_applied']), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
        ['label' => 'PAID BEFORE', 'value' => pdf_money($paidBefore), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
        ['label' => 'THIS PAYMENT', 'value' => pdf_money($paymentAmount), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
        ['label' => 'PAID TO DATE', 'value' => pdf_money($paidThrough), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
        ['label' => 'RENT REMAINING', 'value' => pdf_money($paymentAllocation['after']['rent_due']), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
        ['label' => 'DEPOSIT DUE', 'value' => pdf_money($paymentAllocation['after']['deposit_due']), 'fill' => [250, 250, 250], 'textColor' => [0, 0, 0]],
        ['label' => 'BALANCE DUE', 'value' => pdf_money($remainingBalance), 'fill' => $brandBlue, 'textColor' => [255, 255, 255]]
    ];

    if ((float) $paymentAllocation['advance_created'] > 0) {
        array_splice($summaryRows, count($summaryRows) - 1, 0, [[
            'label' => 'NEW ADVANCE',
            'value' => pdf_money($paymentAllocation['advance_created']),
            'fill' => [250, 250, 250],
            'textColor' => [0, 0, 0],
        ]]);
    }

    $rowIndex = 0;
    foreach ($summaryRows as $summaryRow) {
        $blockY = $summaryTop + ($rowIndex * 28);
        $pdf->rect($summaryX, $blockY, $summaryWidth, 28, 'B', [220, 223, 228], $summaryRow['fill'], 0.8);
        $pdf->text($summaryX + 10, $blockY + 18, $summaryRow['label'], 10, 'bold', $summaryRow['textColor']);
        $pdf->text($summaryValueX, $blockY + 18, $summaryRow['value'], 10, 'bold', $summaryRow['textColor']);
        $rowIndex++;
    }

    $footerTop = $summaryTop + ($rowIndex * 28) + 28;
    $statusLine = 'Invoice status after payment: ' . ucwords((string) $paymentAllocation['after']['status']);
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
