<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

api_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $queryParams);
    $id = isset($queryParams['id']) ? (int) $queryParams['id'] : 0;
    if ($id <= 0) {
        api_json(['ok' => false, 'message' => 'Location ID is required.'], 422);
    }
    @mysqli_query($conn, "DELETE FROM `locations` WHERE `id`=$id");
    api_json(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = api_get_json_input();
$location = is_username(isset($input['hname']) ? (string) $input['hname'] : '');
if ($location === '') {
    api_json(['ok' => false, 'message' => 'Location name is required.'], 422);
}

$inserted = $mysqli->query("INSERT INTO `locations` (`location_name`,`geo_id`) VALUES ('$location','undefined')");
if (!$inserted) {
    api_json(['ok' => false, 'message' => 'Location could not be created.'], 500);
}

api_json(['ok' => true, 'id' => (int) $mysqli->insert_id]);
