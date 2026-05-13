<?php

function ensure_tenant_columns($connection) {
    $tenantIdResult = mysqli_query($connection, "SHOW COLUMNS FROM `tenants` LIKE 'tenantID'");
    if ($tenantIdResult && mysqli_num_rows($tenantIdResult) === 1) {
        $tenantIdColumn = mysqli_fetch_assoc($tenantIdResult);

        $tenantPrimaryResult = mysqli_query($connection, "SHOW INDEX FROM `tenants` WHERE Key_name = 'PRIMARY'");
        if ($tenantPrimaryResult && mysqli_num_rows($tenantPrimaryResult) === 0) {
            @mysqli_query($connection, "ALTER TABLE `tenants` ADD PRIMARY KEY (`tenantID`)");
        }

        if (stripos($tenantIdColumn['Extra'], 'auto_increment') === false) {
            @mysqli_query($connection, "ALTER TABLE `tenants` MODIFY `tenantID` int(11) NOT NULL AUTO_INCREMENT");
        }
    }

    $columns = [
        'partition_id' => "ALTER TABLE `tenants` ADD COLUMN `partition_id` int(11) DEFAULT NULL AFTER `houseNumber`",
        'tenant_address' => "ALTER TABLE `tenants` ADD COLUMN `tenant_address` text DEFAULT NULL AFTER `phone_number`",
        'tenant_home_country_address' => "ALTER TABLE `tenants` ADD COLUMN `tenant_home_country_address` text DEFAULT NULL AFTER `tenant_address`",
        'tenant_country' => "ALTER TABLE `tenants` ADD COLUMN `tenant_country` varchar(100) DEFAULT NULL AFTER `tenant_home_country_address`",
        'start_date' => "ALTER TABLE `tenants` ADD COLUMN `start_date` text DEFAULT NULL AFTER `tenant_country`",
        'end_date' => "ALTER TABLE `tenants` ADD COLUMN `end_date` text DEFAULT NULL AFTER `start_date`",
        'exit_date' => "ALTER TABLE `tenants` ADD COLUMN `exit_date` text DEFAULT NULL AFTER `end_date`",
        'tenant_status' => "ALTER TABLE `tenants` ADD COLUMN `tenant_status` varchar(50) NOT NULL DEFAULT 'Active' AFTER `exit_date`"
    ];

    foreach ($columns as $column => $sql) {
        $result = mysqli_query($connection, "SHOW COLUMNS FROM `tenants` LIKE '$column'");
        if ($result && mysqli_num_rows($result) === 0) {
            @mysqli_query($connection, $sql);
        }
    }

    $idResult = mysqli_query($connection, "SHOW COLUMNS FROM `tenants` LIKE 'ID_number'");
    if ($idResult && ($idColumn = mysqli_fetch_assoc($idResult)) && stripos($idColumn['Type'], 'int') !== false) {
        @mysqli_query($connection, "ALTER TABLE `tenants` MODIFY `ID_number` varchar(50) NOT NULL");
    }

    $phoneResult = mysqli_query($connection, "SHOW COLUMNS FROM `tenants` LIKE 'phone_number'");
    if ($phoneResult && ($phoneColumn = mysqli_fetch_assoc($phoneResult)) && stripos($phoneColumn['Type'], 'varchar(30)') === false) {
        @mysqli_query($connection, "ALTER TABLE `tenants` MODIFY `phone_number` varchar(30) NOT NULL");
    }

    $indexResult = mysqli_query($connection, "SHOW INDEX FROM `tenants` WHERE Key_name = 'partition_id'");
    if ($indexResult && mysqli_num_rows($indexResult) === 0) {
        @mysqli_query($connection, "ALTER TABLE `tenants` ADD KEY `partition_id` (`partition_id`)");
    }

    $adminTenantColumn = mysqli_query($connection, "SHOW COLUMNS FROM `admin` LIKE 'tenant_id'");
    if ($adminTenantColumn && mysqli_num_rows($adminTenantColumn) === 0) {
        @mysqli_query($connection, "ALTER TABLE `admin` ADD COLUMN `tenant_id` int(11) DEFAULT NULL AFTER `role`");
    }

    $adminTenantIndex = mysqli_query($connection, "SHOW INDEX FROM `admin` WHERE Key_name = 'tenant_id'");
    if ($adminTenantIndex && mysqli_num_rows($adminTenantIndex) === 0) {
        @mysqli_query($connection, "ALTER TABLE `admin` ADD KEY `tenant_id` (`tenant_id`)");
    }
}

function ensure_tenant_portal_tables($connection) {
    @mysqli_query($connection, "
        CREATE TABLE IF NOT EXISTS `tenant_complaints` (
            `complaint_id` int(11) NOT NULL AUTO_INCREMENT,
            `tenant_id` int(11) NOT NULL,
            `house_id` int(11) DEFAULT NULL,
            `partition_id` int(11) DEFAULT NULL,
            `title` varchar(255) NOT NULL,
            `description` text NOT NULL,
            `image_path` text DEFAULT NULL,
            `status` varchar(50) NOT NULL DEFAULT 'Open',
            `admin_reason` text DEFAULT NULL,
            `reopened_count` int(11) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`complaint_id`),
            KEY `tenant_id` (`tenant_id`),
            KEY `partition_id` (`partition_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    @mysqli_query($connection, "
        CREATE TABLE IF NOT EXISTS `tenant_notices` (
            `notice_id` int(11) NOT NULL AUTO_INCREMENT,
            `tenant_id` int(11) NOT NULL,
            `subject` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `sender_role` varchar(20) NOT NULL DEFAULT 'Tenant',
            `created_by_name` varchar(150) DEFAULT NULL,
            `document_url` text DEFAULT NULL,
            `document_label` varchar(150) DEFAULT NULL,
            `secondary_document_url` text DEFAULT NULL,
            `secondary_document_label` varchar(150) DEFAULT NULL,
            `status` varchar(50) NOT NULL DEFAULT 'Open',
            `admin_reply` text DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`notice_id`),
            KEY `tenant_id` (`tenant_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $senderRoleColumn = mysqli_query($connection, "SHOW COLUMNS FROM `tenant_notices` LIKE 'sender_role'");
    if ($senderRoleColumn && mysqli_num_rows($senderRoleColumn) === 0) {
        @mysqli_query($connection, "ALTER TABLE `tenant_notices` ADD COLUMN `sender_role` varchar(20) NOT NULL DEFAULT 'Tenant' AFTER `message`");
    }

    $createdByNameColumn = mysqli_query($connection, "SHOW COLUMNS FROM `tenant_notices` LIKE 'created_by_name'");
    if ($createdByNameColumn && mysqli_num_rows($createdByNameColumn) === 0) {
        @mysqli_query($connection, "ALTER TABLE `tenant_notices` ADD COLUMN `created_by_name` varchar(150) DEFAULT NULL AFTER `sender_role`");
    }

    $documentUrlColumn = mysqli_query($connection, "SHOW COLUMNS FROM `tenant_notices` LIKE 'document_url'");
    if ($documentUrlColumn && mysqli_num_rows($documentUrlColumn) === 0) {
        @mysqli_query($connection, "ALTER TABLE `tenant_notices` ADD COLUMN `document_url` text DEFAULT NULL AFTER `created_by_name`");
    }

    $documentLabelColumn = mysqli_query($connection, "SHOW COLUMNS FROM `tenant_notices` LIKE 'document_label'");
    if ($documentLabelColumn && mysqli_num_rows($documentLabelColumn) === 0) {
        @mysqli_query($connection, "ALTER TABLE `tenant_notices` ADD COLUMN `document_label` varchar(150) DEFAULT NULL AFTER `document_url`");
    }

    $secondaryDocumentUrlColumn = mysqli_query($connection, "SHOW COLUMNS FROM `tenant_notices` LIKE 'secondary_document_url'");
    if ($secondaryDocumentUrlColumn && mysqli_num_rows($secondaryDocumentUrlColumn) === 0) {
        @mysqli_query($connection, "ALTER TABLE `tenant_notices` ADD COLUMN `secondary_document_url` text DEFAULT NULL AFTER `document_label`");
    }

    $secondaryDocumentLabelColumn = mysqli_query($connection, "SHOW COLUMNS FROM `tenant_notices` LIKE 'secondary_document_label'");
    if ($secondaryDocumentLabelColumn && mysqli_num_rows($secondaryDocumentLabelColumn) === 0) {
        @mysqli_query($connection, "ALTER TABLE `tenant_notices` ADD COLUMN `secondary_document_label` varchar(150) DEFAULT NULL AFTER `secondary_document_url`");
    }
}

function ensure_tenant_user_account($connection, $tenantId, $tenantName, $email, $phoneNumber) {
    $tenantId = (int) $tenantId;
    $email = is_email($email);
    $tenantName = is_username($tenantName);
    $phoneNumber = trim((string) $phoneNumber);

    if ($tenantId <= 0 || $email === '' || $phoneNumber === '') {
        return false;
    }

    $passwordHash = password_hash($phoneNumber, PASSWORD_BCRYPT, ['cost' => 12]);
    $safeEmail = mysqli_real_escape_string($connection, $email);
    $safeName = mysqli_real_escape_string($connection, $tenantName);
    $safePassword = mysqli_real_escape_string($connection, $passwordHash);

    $existingByTenant = mysqli_query($connection, "SELECT `id` FROM `admin` WHERE `tenant_id`='$tenantId' LIMIT 1");
    if ($existingByTenant && mysqli_num_rows($existingByTenant) === 1) {
        $row = mysqli_fetch_assoc($existingByTenant);
        return (bool) mysqli_query($connection, "
            UPDATE `admin`
            SET `email`='$safeEmail', `name`='$safeName', `password`='$safePassword', `role`='user'
            WHERE `id`='" . (int) $row['id'] . "'
        ");
    }

    $existingByEmail = mysqli_query($connection, "SELECT `id`, `role` FROM `admin` WHERE `email`='$safeEmail' LIMIT 1");
    if ($existingByEmail && mysqli_num_rows($existingByEmail) === 1) {
        $row = mysqli_fetch_assoc($existingByEmail);

        if ($row['role'] !== 'user') {
            return false;
        }

        return (bool) mysqli_query($connection, "
            UPDATE `admin`
            SET `name`='$safeName', `password`='$safePassword', `role`='user', `tenant_id`='$tenantId'
            WHERE `id`='" . (int) $row['id'] . "'
        ");
    }

    return (bool) mysqli_query($connection, "
        INSERT INTO `admin` (`name`, `role`, `tenant_id`, `email`, `password`)
        VALUES ('$safeName', 'user', '$tenantId', '$safeEmail', '$safePassword')
    ");
}

function ensure_missing_tenant_user_accounts($connection) {
    $result = mysqli_query($connection, "
        SELECT t.`tenantID`, t.`tenant_name`, t.`email`, t.`phone_number`
        FROM `tenants` t
        LEFT JOIN `admin` a ON a.`tenant_id` = t.`tenantID`
        WHERE t.`tenant_status`='Active'
          AND t.`email` <> ''
          AND t.`phone_number` <> ''
          AND a.`id` IS NULL
    ");

    if (!$result) {
        return;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        ensure_tenant_user_account(
            $connection,
            (int) $row['tenantID'],
            $row['tenant_name'],
            $row['email'],
            $row['phone_number']
        );
    }
}

function normalize_phone_for_login($phoneNumber) {
    $phoneNumber = trim((string) $phoneNumber);
    if ($phoneNumber === '') {
        return '';
    }

    $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
    if ($phoneNumber === null) {
        return '';
    }

    if (strpos($phoneNumber, '+') === 0) {
        return '+' . preg_replace('/[^0-9]/', '', substr($phoneNumber, 1));
    }

    return preg_replace('/[^0-9]/', '', $phoneNumber);
}

function refresh_tenants_view($connection) {
    try {
        @mysqli_query($connection, "DROP VIEW IF EXISTS `tenantsView`");
        @mysqli_query($connection, "
            CREATE VIEW `tenantsView` AS
            SELECT
                `tenants`.`tenantID` AS `tenantID`,
                `tenants`.`houseNumber` AS `houseNumber`,
                `tenants`.`partition_id` AS `partition_id`,
                `tenants`.`tenant_name` AS `tenant_name`,
                `tenants`.`email` AS `email`,
                `tenants`.`ID_number` AS `ID_number`,
                `tenants`.`profession` AS `profession`,
                `tenants`.`phone_number` AS `phone_number`,
                `tenants`.`tenant_address` AS `tenant_address`,
                `tenants`.`tenant_home_country_address` AS `tenant_home_country_address`,
                `tenants`.`tenant_country` AS `tenant_country`,
                `tenants`.`start_date` AS `start_date`,
                `tenants`.`end_date` AS `end_date`,
                `tenants`.`exit_date` AS `exit_date`,
                `tenants`.`tenant_status` AS `tenant_status`,
                `tenants`.`dateAdmitted` AS `dateAdmitted`,
                `tenants`.`agreement_file` AS `agreement_file`,
                `houses`.`house_name` AS `house_name`,
                `houses`.`number_of_rooms` AS `number_of_rooms`,
                `houses`.`house_status` AS `house_status`,
                COALESCE(`house_partitions`.`rent_amount`, `houses`.`rent_amount`) AS `rent_amount`,
                `houses`.`houseID` AS `houseID`,
                `house_partitions`.`partition_number` AS `partition_number`,
                `house_partitions`.`partition_status` AS `partition_status`
            FROM ((`tenants`
                LEFT JOIN `houses` ON (`tenants`.`houseNumber` = `houses`.`houseID`))
                LEFT JOIN `house_partitions` ON (`tenants`.`partition_id` = `house_partitions`.`partition_id`))
        ");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function ensure_tenant_schema($connection) {
    try {
        ensure_tenant_columns($connection);
    } catch (Throwable $e) {
    }

    try {
        ensure_tenant_portal_tables($connection);
    } catch (Throwable $e) {
    }

    refresh_tenants_view($connection);

    try {
        ensure_missing_tenant_user_accounts($connection);
    } catch (Throwable $e) {
    }
}

?>
