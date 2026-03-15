<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Http.php';
require_once __DIR__ . '/../../../path.inc';
require_once __DIR__ . '/../../../get_host_info.inc';
require_once __DIR__ . '/../../../rabbitMQLib.inc';

Http::requireMethod('GET');

try {
    $client = new rabbitMQClient("movieServer.ini", "movieServer");

    $id = (int) ($_GET['id'] ?? 0);

    if ($id > 0) {
        $request = [
            'type' => 'GetMovieDetails',
            'id'   => $id
        ];

        $response = $client->send_request($request);

        if (!is_array($response)) {
            Http::json(['error' => 'Invalid response from RabbitMQ worker.'], 500);
        }

        $statusCode = (int) ($response['status_code'] ?? 200);
        unset($response['status_code']);

        Http::json($response, $statusCode);
    }

    $search = trim((string) ($_GET['search'] ?? ''));
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $limit  = min(50, max(1, (int) ($_GET['limit'] ?? 20)));

    $request = [
        'type'   => 'GetMovies',
        'search' => $search,
        'page'   => $page,
        'limit'  => $limit
    ];

    $response = $client->send_request($request);

    if (!is_array($response)) {
        Http::json(['error' => 'Invalid response from RabbitMQ worker.'], 500);
    }

    $statusCode = (int) ($response['status_code'] ?? 200);
    unset($response['status_code']);

    Http::json($response, $statusCode);

} catch (Exception $e) {
    Http::json([
        'error' => 'Server error.',
        'details' => $e->getMessage()
    ], 500);
}