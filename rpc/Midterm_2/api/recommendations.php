<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Http.php';
require_once __DIR__ . '/../lib/RecommendationEngine.php';

Http::requireMethod('GET');

$userId = (int) ($_GET['user_id'] ?? 0);
$limit = min(20, max(1, (int) ($_GET['limit'] ?? 8)));

if ($userId <= 0) {
    Http::json(['error' => 'user_id is required.'], 422);
}

$movies = RecommendationEngine::getRecommendations(Database::get(), $userId, $limit);

Http::json([
    'user_id' => $userId,
    'recommendations' => $movies,
]);
