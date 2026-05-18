<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_admin();

$result = mysqli_query($connection, "SELECT `id`, `name`, `email`, `date`, `role` FROM `admin` ORDER BY `date` DESC, `id` DESC");
$items = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'date' => isset($row['date']) ? (string) $row['date'] : '',
            'role' => isset($row['role']) ? (string) $row['role'] : '',
        ];
    }
}

api_json(['ok' => true, 'items' => $items]);
