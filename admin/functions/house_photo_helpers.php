<?php

function ensure_house_auto_increment_schema($connection) {
    $houseIdColumn = mysqli_query($connection, "SHOW COLUMNS FROM `houses` LIKE 'houseID'");
    if ($houseIdColumn && mysqli_num_rows($houseIdColumn) === 1) {
        $column = mysqli_fetch_assoc($houseIdColumn);

        $primaryResult = mysqli_query($connection, "SHOW INDEX FROM `houses` WHERE Key_name = 'PRIMARY'");
        if ($primaryResult && mysqli_num_rows($primaryResult) === 0) {
            @mysqli_query($connection, "ALTER TABLE `houses` ADD PRIMARY KEY (`houseID`)");
        }

        if (stripos($column['Extra'], 'auto_increment') === false) {
            @mysqli_query($connection, "ALTER TABLE `houses` MODIFY `houseID` int(11) NOT NULL AUTO_INCREMENT");
        }
    }

    $picIdColumn = mysqli_query($connection, "SHOW COLUMNS FROM `house_pics` LIKE 'pic_id'");
    if ($picIdColumn && mysqli_num_rows($picIdColumn) === 1) {
        $column = mysqli_fetch_assoc($picIdColumn);

        $primaryResult = mysqli_query($connection, "SHOW INDEX FROM `house_pics` WHERE Key_name = 'PRIMARY'");
        if ($primaryResult && mysqli_num_rows($primaryResult) === 0) {
            @mysqli_query($connection, "ALTER TABLE `house_pics` ADD PRIMARY KEY (`pic_id`)");
        }

        if (stripos($column['Extra'], 'auto_increment') === false) {
            @mysqli_query($connection, "ALTER TABLE `house_pics` MODIFY `pic_id` int(11) NOT NULL AUTO_INCREMENT");
        }
    }
}

function ensure_pic_type_column($connection) {
    ensure_house_auto_increment_schema($connection);

    $result = mysqli_query($connection, "SHOW COLUMNS FROM `house_pics` LIKE 'pic_type'");
    if ($result && mysqli_num_rows($result) === 0) {
        @mysqli_query($connection, "ALTER TABLE `house_pics` ADD COLUMN `pic_type` varchar(30) NOT NULL DEFAULT 'Beds'");
    }

    $partitionResult = mysqli_query($connection, "SHOW COLUMNS FROM `house_pics` LIKE 'partition_id'");
    if ($partitionResult && mysqli_num_rows($partitionResult) === 0) {
        @mysqli_query($connection, "ALTER TABLE `house_pics` ADD COLUMN `partition_id` int(11) DEFAULT NULL AFTER `house_id`");
        @mysqli_query($connection, "ALTER TABLE `house_pics` ADD KEY `partition_id` (`partition_id`)");
    }
}

function normalize_house_pic_type($picType) {
    $allowedTypes = ['Beds', 'Partitions', 'House'];
    return in_array($picType, $allowedTypes, true) ? $picType : 'Beds';
}

function detect_image_extension($tmpName) {
    if (!is_file($tmpName)) {
        return false;
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $tmpName);
            finfo_close($finfo);

            $allowedMimeTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];

            if (isset($allowedMimeTypes[$mimeType])) {
                return $allowedMimeTypes[$mimeType];
            }
        }
    }

    if (function_exists('getimagesize')) {
        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo && isset($imageInfo['mime'])) {
            $allowedMimeTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];

            if (isset($allowedMimeTypes[$imageInfo['mime']])) {
                return $allowedMimeTypes[$imageInfo['mime']];
            }
        }
    }

    return false;
}

function upload_house_photos($connection, $houseId, $picType, $files, $partitionId = null) {
    $picType = normalize_house_pic_type($picType);

    if (!$houseId || empty($files['name'][0])) {
        return 0;
    }

    ensure_pic_type_column($connection);

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'house_photos';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        return 0;
    }

    $uploaded = 0;
    $fileCount = count($files['name']);

    for ($index = 0; $index < $fileCount; $index++) {
        if (!isset($files['error'][$index]) || $files['error'][$index] !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmpName = $files['tmp_name'][$index];
        $extension = detect_image_extension($tmpName);

        if ($extension === false) {
            continue;
        }

        if ((int) $files['size'][$index] > 5 * 1024 * 1024) {
            continue;
        }

        $safeType = strtolower($picType);
        $fileName = 'house_' . (int) $houseId . '_' . $safeType . '_' . time() . '_' . mt_rand(1000, 999999) . '.' . $extension;
        $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

        if (!@move_uploaded_file($tmpName, $destination)) {
            continue;
        }

        $storedPath = 'uploads/house_photos/' . $fileName;

        if ($partitionId) {
            $statement = mysqli_prepare($connection, "INSERT INTO `house_pics` (`pic_name`, `house_id`, `partition_id`, `pic_type`) VALUES (?, ?, ?, ?)");
            if (!$statement) {
                @unlink($destination);
                continue;
            }
            mysqli_stmt_bind_param($statement, 'siis', $storedPath, $houseId, $partitionId, $picType);
        } else {
            $statement = mysqli_prepare($connection, "INSERT INTO `house_pics` (`pic_name`, `house_id`, `pic_type`) VALUES (?, ?, ?)");
            if (!$statement) {
                @unlink($destination);
                continue;
            }
            mysqli_stmt_bind_param($statement, 'sis', $storedPath, $houseId, $picType);
        }

        if (mysqli_stmt_execute($statement)) {
            $uploaded++;
        } else {
            @unlink($destination);
        }

        mysqli_stmt_close($statement);
    }

    return $uploaded;
}
