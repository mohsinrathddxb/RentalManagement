<?php

require_once __DIR__ . '/country_options.php';

function ensure_admin_profile_schema($connection) {
    // Make sure admin.id is a real auto-increment primary key on hosted MySQL too.
    $idColumnResult = mysqli_query($connection, "SHOW COLUMNS FROM `admin` LIKE 'id'");
    if ($idColumnResult && mysqli_num_rows($idColumnResult) === 1) {
        $idColumn = mysqli_fetch_assoc($idColumnResult);
        $needsPrimaryFix = true;
        $primaryResult = mysqli_query($connection, "SHOW INDEX FROM `admin` WHERE Key_name = 'PRIMARY'");
        if ($primaryResult && mysqli_num_rows($primaryResult) > 0) {
            $needsPrimaryFix = false;
        }

        if ($needsPrimaryFix) {
            @mysqli_query($connection, "ALTER TABLE `admin` ADD PRIMARY KEY (`id`)");
        }

        if (stripos($idColumn['Extra'], 'auto_increment') === false) {
            @mysqli_query($connection, "ALTER TABLE `admin` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT");
        }
    }

    $columns = [
        'tenant_id' => "ALTER TABLE `admin` ADD COLUMN `tenant_id` int(11) DEFAULT NULL AFTER `role`",
        'emirates_id' => "ALTER TABLE `admin` ADD COLUMN `emirates_id` varchar(100) DEFAULT NULL AFTER `tenant_id`",
        'property_address' => "ALTER TABLE `admin` ADD COLUMN `property_address` text DEFAULT NULL AFTER `emirates_id`",
        'property_details' => "ALTER TABLE `admin` ADD COLUMN `property_details` text DEFAULT NULL AFTER `property_address`",
        'property_document' => "ALTER TABLE `admin` ADD COLUMN `property_document` text DEFAULT NULL AFTER `property_details`",
        'country' => "ALTER TABLE `admin` ADD COLUMN `country` varchar(100) DEFAULT NULL AFTER `property_document`",
        'phone_number' => "ALTER TABLE `admin` ADD COLUMN `phone_number` varchar(30) DEFAULT NULL AFTER `country`"
    ];

    foreach ($columns as $column => $sql) {
        $result = mysqli_query($connection, "SHOW COLUMNS FROM `admin` LIKE '$column'");
        if ($result && mysqli_num_rows($result) === 0) {
            @mysqli_query($connection, $sql);
        }
    }

    $tenantIdIndex = mysqli_query($connection, "SHOW INDEX FROM `admin` WHERE Key_name = 'tenant_id'");
    if ($tenantIdIndex && mysqli_num_rows($tenantIdIndex) === 0) {
        @mysqli_query($connection, "ALTER TABLE `admin` ADD KEY `tenant_id` (`tenant_id`)");
    }
}

function upload_admin_property_document($file) {
    if (!isset($file['name']) || trim((string) $file['name']) === '') {
        return '';
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }

    if ((int) $file['size'] > 8 * 1024 * 1024) {
        return false;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
    if (!in_array($extension, $allowed, true)) {
        return false;
    }

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'property_docs';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $fileName = 'property_doc_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
    $target = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
    if (!@move_uploaded_file($file['tmp_name'], $target)) {
        return false;
    }

    return 'uploads/property_docs/' . $fileName;
}
