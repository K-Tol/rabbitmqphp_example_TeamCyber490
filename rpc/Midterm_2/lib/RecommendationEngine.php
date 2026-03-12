<?php

declare(strict_types=1);

final class RecommendationEngine
{
    public static function applyReviewWeight(PDO $pdo, int $userId, int $movieId, int $rating): void
    {
        $movieStmt = $pdo->prepare('SELECT genres FROM movies WHERE id = :id');
        $movieStmt->execute(['id' => $movieId]);
        $movie = $movieStmt->fetch();

        if (!$movie) {
            return;
        }

        $genres = array_filter(array_map('trim', explode(',', (string) ($movie['genres'] ?? ''))));
        if ($genres === []) {
            return;
        }

        $delta = ($rating - 3) / 2;

        $upsert = $pdo->prepare(
            'INSERT INTO user_genre_weights (user_id, genre, weight)
             VALUES (:user_id, :genre, :weight)
             ON DUPLICATE KEY UPDATE
               weight = weight + VALUES(weight),
               updated_at = CURRENT_TIMESTAMP'
        );

        foreach ($genres as $genre) {
            $upsert->execute([
                'user_id' => $userId,
                'genre' => strtolower($genre),
                'weight' => $delta,
            ]);
        }
    }

    public static function getRecommendations(PDO $pdo, int $userId, int $limit = 10): array
    {
        $weightsStmt = $pdo->prepare('SELECT genre, weight FROM user_genre_weights WHERE user_id = :user_id');
        $weightsStmt->execute(['user_id' => $userId]);
        $weights = $weightsStmt->fetchAll();

        $map = [];
        foreach ($weights as $weight) {
            $map[(string) $weight['genre']] = (float) $weight['weight'];
        }

        $movieStmt = $pdo->query(
            'SELECT m.id, m.title, m.overview, m.poster_url, m.release_date, m.genres,
                COALESCE(AVG(r.rating), 0) AS avg_rating
             FROM movies m
             LEFT JOIN reviews r ON r.movie_id = m.id
             GROUP BY m.id'
        );

        $movies = $movieStmt->fetchAll();

        foreach ($movies as &$movie) {
            $genres = array_filter(array_map('trim', explode(',', (string) ($movie['genres'] ?? ''))));
            $score = 0.0;

            foreach ($genres as $genre) {
                $key = strtolower($genre);
                if (isset($map[$key])) {
                    $score += $map[$key];
                }
            }

            $score += ((float) $movie['avg_rating'] - 2.5) * 0.3;
            $movie['recommendation_score'] = round($score, 4);
        }
        unset($movie);

        usort($movies, static fn(array $a, array $b): int => $b['recommendation_score'] <=> $a['recommendation_score']);

        return array_slice($movies, 0, max(1, $limit));
    }
}
