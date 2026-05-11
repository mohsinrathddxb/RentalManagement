<?php

function ensure_pic_type_column($connection) {
    $result = mysqli_query($connection, "SHOW COLUMNS FROM `house_pics` LIKE 'pic_type'");
    if ($result && mysqli_num_rows($result) === 0) {
        mysqli_query($connection, "ALTER TABLE `house_pics` ADD COLUMN `pic_type` varchar(30) NOT NULL DEFAULT 'Beds'");
    }
}

function normalize_house_pic_type($picType) {
    $allowedTypes = ['Beds', 'Partitions', 'House'];
    return in_array($picType, $allowedTypes, true) ? $picType : 'Beds';
}

function upload_house_photos($connection, $houseId, $picType, $files) {
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];

    $picType = normalize_house_pic_type($picType);

    if (!$houseId || empty($files['name'][0])) {
        return 0;
    }

    ensure_pic_type_column($connection);

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'house_photos';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $uploaded = 0;
    $fileCount = count($files['name']);

    for ($index = 0; $index < $fileCount; $index++) {
        if ($files['error'][$index] !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmpName = $files['tmp_name'][$index];
        $mimeType = mime_content_type($tmpName);

        if (!isset($allowedMimeTypes[$mimeType])) {
            continue;
        }

        if ($files['size'][$index] > 5 * 1024 * 1024) {
            continue;
        }

        $extension = $allowedMimeTypes[$mimeType];
        $safeType = strtolower($picType);
        $fileName = 'house_' . $houseId . '_' . $safeType . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($tmpName, $destination)) {
            continue;
        }

        $storedPath = 'uploads/house_photos/' . $fileName;
        $nextIdResult = mysqli_query($connection, "SELECT COALESCE(MAX(pic_id), 0) + 1 AS next_id FROM `house_pics`");
        $nextIdRow = mysqli_fetch_assoc($nextIdResult);
        $nextId = (int) $nextIdRow['next_id'];

        $statement = mysqli_prepare($connection, "INSERT INTO `house_pics` (`pic_id`, `pic_name`, `house_id`, `pic_type`) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($statement, 'isis', $nextId, $storedPath, $houseId, $picType);

        if (mysqli_stmt_execute($statement)) {
            $uploaded++;
        } else {
            @unlink($destination);
        }
    }

    return $uploaded;
}
