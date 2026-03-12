<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Http.php';
require_once __DIR__ . '/../lib/EventBridge.php';
require_once __DIR__ . '/../lib/RecommendationEngine.php';

$pdo = Database::get();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $movieId = (int) ($_GET['movie_id'] ?? 0);
    if ($movieId <= 0) {
        Http::json(['error' => 'movie_id is required.'], 422);
    }

    $stmt = $pdo->prepare(
        'SELECT r.id, r.movie_id, r.user_id, u.display_name, r.rating, r.review_text, r.created_at
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.movie_id = :movie_id
         ORDER BY r.created_at DESC'
    );
    $stmt->execute(['movie_id' => $movieId]);

    Http::json(['reviews' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $body = Http::body();

    $movieId = (int) ($body['movie_id'] ?? 0);
    $userId = (int) ($body['user_id'] ?? 0);
    $rating = (int) ($body['rating'] ?? 0);
    $review = trim((string) ($body['review'] ?? ''));

    if ($movieId <= 0 || $userId <= 0 || $rating < 1 || $rating > 5) {
        Http::json(['error' => 'movie_id, user_id, and rating (1-5) are required.'], 422);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO reviews (movie_id, user_id, rating, review_text)
         VALUES (:movie_id, :user_id, :rating, :review)
         ON DUPLICATE KEY UPDATE
           rating = VALUES(rating),
           review_text = VALUES(review_text),
           updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        'movie_id' => $movieId,
        'user_id' => $userId,
        'rating' => $rating,
        'review' => $review,
    ]);

    RecommendationEngine::applyReviewWeight($pdo, $userId, $movieId, $rating);

    EventBridge::publish('reviews.saved', [
        'movie_id' => $movieId,
        'user_id' => $userId,
        'rating' => $rating,
    ]);

    Http::json(['message' => 'Review saved.'], 201);
}

Http::json(['error' => 'Method not allowed.'], 405);
