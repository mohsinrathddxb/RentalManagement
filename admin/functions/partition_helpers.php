<?php

function ensure_partition_tables($connection) {
    mysqli_query($connection, "
        CREATE TABLE IF NOT EXISTS `house_partitions` (
            `partition_id` int(11) NOT NULL AUTO_INCREMENT,
            `house_id` int(11) NOT NULL,
            `partition_number` varchar(100) NOT NULL,
            `rent_amount` double NOT NULL DEFAULT 0,
            `partition_status` varchar(50) NOT NULL DEFAULT 'Vacant',
            `description` text DEFAULT NULL,
            `date_created` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`partition_id`),
            KEY `house_id` (`house_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $facilitiesColumn = mysqli_query($connection, "SHOW COLUMNS FROM `house_partitions` LIKE 'facilities'");
    if ($facilitiesColumn && mysqli_num_rows($facilitiesColumn) === 0) {
        mysqli_query($connection, "ALTER TABLE `house_partitions` ADD COLUMN `facilities` text DEFAULT NULL AFTER `description`");
    }

    $result = mysqli_query($connection, "SHOW COLUMNS FROM `house_pics` LIKE 'partition_id'");
    if ($result && mysqli_num_rows($result) === 0) {
        mysqli_query($connection, "ALTER TABLE `house_pics` ADD COLUMN `partition_id` int(11) DEFAULT NULL AFTER `house_id`");
        mysqli_query($connection, "ALTER TABLE `house_pics` ADD KEY `partition_id` (`partition_id`)");
    }
}

function get_partition_facility_catalog() {
    return [
        'Free Dewa',
        'Wifi',
        'Cleaning Services',
        'Washing Machine',
        'Refrigerator'
    ];
}

function normalize_partition_facilities($facilities) {
    if (!is_array($facilities)) {
        return '';
    }

    $catalog = get_partition_facility_catalog();
    $allowed = array_fill_keys($catalog, true);
    $clean = [];

    foreach ($facilities as $facility) {
        $facility = trim((string) $facility);
        if ($facility !== '' && isset($allowed[$facility])) {
            $clean[$facility] = $facility;
        }
    }

    return implode(', ', array_values($clean));
}

function get_partition_facilities_array($storedFacilities) {
    if (empty($storedFacilities)) {
        return [];
    }

    $parts = array_map('trim', explode(',', (string) $storedFacilities));
    return array_values(array_filter($parts, function ($item) {
        return $item !== '';
    }));
}

function render_partition_facility_checkboxes($selectedFacilities = '', $inputName = 'facilities[]') {
    $selected = array_fill_keys(get_partition_facilities_array($selectedFacilities), true);
    $html = '<div class="row">';

    foreach (get_partition_facility_catalog() as $facility) {
        $checked = isset($selected[$facility]) ? ' checked' : '';
        $safeFacility = htmlspecialchars($facility, ENT_QUOTES, 'UTF-8');
        $html .= '
            <div class="col-sm-6" style="margin-bottom:8px;">
                <label class="checkbox-inline" style="padding-left:20px;">
                    <input type="checkbox" name="'.$inputName.'" value="'.$safeFacility.'"'.$checked.'> '.$safeFacility.'
                </label>
            </div>
        ';
    }

    $html .= '</div>';
    return $html;
}

function render_partition_facilities_badges($storedFacilities) {
    $facilities = get_partition_facilities_array($storedFacilities);
    if (count($facilities) === 0) {
        return '<span class="text-muted">No facilities added.</span>';
    }

    $html = '';
    foreach ($facilities as $facility) {
        $html .= '<span class="label label-success" style="display:inline-block; margin:0 6px 6px 0;">'.htmlspecialchars($facility, ENT_QUOTES, 'UTF-8').'</span>';
    }

    return $html;
}

function get_house_partitions($connection, $houseId) {
    $houseId = (int) $houseId;
    return mysqli_query($connection, "
        SELECT hp.*,
            (SELECT COUNT(*) FROM `house_pics` pics WHERE pics.`partition_id` = hp.`partition_id`) AS photo_count
        FROM `house_partitions` hp
        WHERE hp.`house_id` = $houseId
        ORDER BY hp.`partition_id` ASC
    ");
}

function count_house_partitions($connection, $houseId) {
    $houseId = (int) $houseId;
    $result = mysqli_query($connection, "SELECT COUNT(*) AS total FROM `house_partitions` WHERE `house_id` = $houseId");
    $row = $result ? mysqli_fetch_assoc($result) : ['total' => 0];
    return (int) $row['total'];
}

?>
