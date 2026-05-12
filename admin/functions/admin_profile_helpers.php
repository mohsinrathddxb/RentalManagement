<?php

require_once __DIR__ . '/country_options.php';

function ensure_admin_profile_schema($connection) {
    $columns = [
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
            mysqli_query($connection, $sql);
        }
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

