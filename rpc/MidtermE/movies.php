<?php
header('Content-Type: application/json; charset=utf-8');

function json_out(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

$rootDir = realpath(__DIR__ . '/../../');
if ($rootDir === false) {
    json_out(500, array('success' => false, 'movies' => array(), 'message' => 'Project root not found'));
}

chdir($rootDir);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

try {
    $client = new rabbitMQClient('testRabbitMQ.ini', 'testServer');
    $request = array('type' => 'get_movies');
    $response = $client->send_request($request);

    if (!is_array($response)) {
        json_out(502, array('success' => false, 'movies' => array(), 'message' => 'Invalid response from RabbitMQ worker'));
    }

    $success = isset($response['success']) ? (bool)$response['success'] : false;
    $movies = isset($response['movies']) && is_array($response['movies']) ? $response['movies'] : array();
    $message = isset($response['message']) ? (string)$response['message'] : ($success ? 'OK' : 'Failed to fetch movies');

    json_out($success ? 200 : 502, array(
        'success' => $success,
        'movies' => $movies,
        'message' => $message
    ));
} catch (Throwable $e) {
    json_out(500, array('success' => false, 'movies' => array(), 'message' => 'Server error: ' . $e->getMessage()));
}
