<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = api_get_json_input();
    $id = isset($input['id']) ? (int) $input['id'] : 0;

    if ($id <= 0) {
        api_json([
            'ok' => false,
            'message' => 'A valid message id is required.',
        ], 422);
    }

    $deleteSql = sprintf('DELETE FROM `contacts` WHERE `id` = %d LIMIT 1', $id);
    $deleted = mysqli_query($connection, $deleteSql);

    if (!$deleted) {
        api_json([
            'ok' => false,
            'message' => 'The message could not be deleted.',
        ], 500);
    }

    api_json([
        'ok' => true,
        'deleted' => mysqli_affected_rows($connection) > 0,
    ]);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    api_json([
        'ok' => false,
        'message' => 'A valid message id is required.',
    ], 422);
}

$sql = sprintf('SELECT * FROM `contacts` WHERE `id` = %d LIMIT 1', $id);
$result = mysqli_query($connection, $sql);
$row = $result ? mysqli_fetch_assoc($result) : null;

if (!$row) {
    api_json([
        'ok' => false,
        'message' => 'Message not found.',
    ], 404);
}

api_json([
    'ok' => true,
    'item' => [
        'id' => (int) $row['id'],
        'names' => isset($row['names']) ? (string) $row['names'] : '',
        'email' => isset($row['email']) ? (string) $row['email'] : '',
        'message' => isset($row['message']) ? (string) $row['message'] : '',
        'date' => isset($row['date']) ? (string) $row['date'] : '',
    ],
]);
