<?php

function ensure_ui_column_preferences_schema($connection)
{
    @mysqli_query(
        $connection,
        "CREATE TABLE IF NOT EXISTS `ui_column_preferences` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_email` varchar(191) NOT NULL,
            `table_key` varchar(120) NOT NULL,
            `visible_columns` text NOT NULL,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_table` (`user_email`, `table_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function get_ui_visible_columns($connection, $userEmail, $tableKey)
{
    $safeEmail = mysqli_real_escape_string($connection, strtolower(trim((string) $userEmail)));
    $safeTableKey = mysqli_real_escape_string($connection, trim((string) $tableKey));
    if ($safeEmail === '' || $safeTableKey === '') {
        return null;
    }

    $result = @mysqli_query(
        $connection,
        "SELECT `visible_columns` FROM `ui_column_preferences` WHERE `user_email`='$safeEmail' AND `table_key`='$safeTableKey' LIMIT 1"
    );

    if (!$result || mysqli_num_rows($result) !== 1) {
        return null;
    }

    $row = mysqli_fetch_assoc($result);
    $decoded = json_decode($row['visible_columns'], true);
    if (!is_array($decoded)) {
        return null;
    }

    $columns = [];
    foreach ($decoded as $value) {
        if (is_int($value) || ctype_digit((string) $value)) {
            $columns[] = (int) $value;
        }
    }

    return $columns;
}

function save_ui_visible_columns($connection, $userEmail, $tableKey, $visibleColumns)
{
    $safeEmail = mysqli_real_escape_string($connection, strtolower(trim((string) $userEmail)));
    $safeTableKey = mysqli_real_escape_string($connection, trim((string) $tableKey));
    if ($safeEmail === '' || $safeTableKey === '') {
        return false;
    }

    $normalized = [];
    if (is_array($visibleColumns)) {
        foreach ($visibleColumns as $value) {
            if (is_int($value) || ctype_digit((string) $value)) {
                $normalized[] = (int) $value;
            }
        }
    }

    $normalized = array_values(array_unique($normalized));
    $safeVisible = mysqli_real_escape_string($connection, json_encode($normalized));

    return (bool) @mysqli_query(
        $connection,
        "INSERT INTO `ui_column_preferences` (`user_email`, `table_key`, `visible_columns`)
         VALUES ('$safeEmail', '$safeTableKey', '$safeVisible')
         ON DUPLICATE KEY UPDATE `visible_columns`=VALUES(`visible_columns`), `updated_at`=CURRENT_TIMESTAMP"
    );
}

