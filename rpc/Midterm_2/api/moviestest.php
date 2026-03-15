<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Http.php';
require_once __DIR__ . '/../rabbitmq/path.inc';
require_once __DIR__ . '/../rabbitmq/get_host_info.inc';
require_once __DIR__ . '/../rabbitmq/rabbitMQLib.inc';

Http::requireMethod('GET');

try {

    // DB worker
    $movieClient = new rabbitMQClient("movieServer.ini", "movieServer");

    // API worker
    $apiClient = new rabbitMQClient("datasource.ini", "datasourceServer");

    $id = (int)($_GET['id'] ?? 0);

    /**
     * ===============================
     * GET SINGLE MOVIE
     * ===============================
     */
    if ($id > 0) {

        // 1. Try getting movie from DB
        $response = $movieClient->send_request([
            "type" => "get_movie",
            "tmdb_id" => $id
        ]);

        // 2. If movie not found → sync from TMDB
        if (!$response || empty($response['movie'])) {

            $apiClient->send_request([
                "type" => "sync_movie",
                "tmdb_id" => $id
            ]);

            // Retry DB query
            $response = $movieClient->send_request([
                "type" => "get_movie",
                "tmdb_id" => $id
            ]);
        }

        if (!$response) {
            Http::json(["error" => "Movie not found"], 404);
        }

        Http::json($response);
    }

    /**
     * ===============================
     * SEARCH MOVIES
     * ===============================
     */
    $search = trim((string)($_GET['search'] ?? ""));
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));

    // 1. Search local DB
    $response = $movieClient->send_request([
        "type" => "search_movies",
        "query" => $search,
        "page" => $page,
        "limit" => $limit
    ]);

    // 2. If nothing found → trigger TMDB search sync
    if ($search !== "" && (empty($response['movies']) || count($response['movies']) === 0)) {

        $apiClient->send_request([
            "type" => "sync_search",
            "query" => $search
        ]);

        // Retry search
        $response = $movieClient->send_request([
            "type" => "search_movies",
            "query" => $search,
            "page" => $page,
            "limit" => $limit
        ]);
    }

    Http::json($response);

} catch (Exception $e) {

    Http::json([
        "error" => "Server error",
        "details" => $e->getMessage()
    ], 500);

}