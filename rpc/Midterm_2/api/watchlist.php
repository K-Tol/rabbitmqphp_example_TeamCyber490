<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Http.php';
require_once __DIR__ . '/../lib/EventBridge.php';

$pdo = Database::get();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $userId = (int) ($_GET['user_id'] ?? 0);
    if ($userId <= 0) {
        Http::json(['error' => 'user_id is required.'], 422);
    }

    $stmt = $pdo->prepare(
        'SELECT w.user_id, w.movie_id, w.created_at, w.notification_sent_at,
                m.title, m.poster_url, m.release_date, m.overview
         FROM watchlists w
         JOIN movies m ON m.id = w.movie_id
         WHERE w.user_id = :user_id
         ORDER BY w.created_at DESC'
    );
    $stmt->execute(['user_id' => $userId]);

    Http::json(['watchlist' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $body = Http::body();
    $userId = (int) ($body['user_id'] ?? 0);
    $movieId = (int) ($body['movie_id'] ?? 0);

    if ($userId <= 0 || $movieId <= 0) {
        Http::json(['error' => 'user_id and movie_id are required.'], 422);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO watchlists (user_id, movie_id)
         VALUES (:user_id, :movie_id)
         ON DUPLICATE KEY UPDATE created_at = created_at'
    );
    $stmt->execute(['user_id' => $userId, 'movie_id' => $movieId]);

    EventBridge::publish('watchlist.added', ['user_id' => $userId, 'movie_id' => $movieId]);

    Http::json(['message' => 'Added to watchlist.'], 201);
}

if ($method === 'DELETE') {
    $body = Http::body();
    $userId = (int) ($body['user_id'] ?? 0);
    $movieId = (int) ($body['movie_id'] ?? 0);

    if ($userId <= 0 || $movieId <= 0) {
        Http::json(['error' => 'user_id and movie_id are required.'], 422);
    }

    $stmt = $pdo->prepare('DELETE FROM watchlists WHERE user_id = :user_id AND movie_id = :movie_id');
    $stmt->execute(['user_id' => $userId, 'movie_id' => $movieId]);

    EventBridge::publish('watchlist.removed', ['user_id' => $userId, 'movie_id' => $movieId]);

    Http::json(['message' => 'Removed from watchlist.']);
}

Http::json(['error' => 'Method not allowed.'], 405);
