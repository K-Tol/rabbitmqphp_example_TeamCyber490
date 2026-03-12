<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Http.php';

Http::requireMethod('GET');
$pdo = Database::get();

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $movieStmt = $pdo->prepare(
        'SELECT m.id, m.title, m.overview, m.poster_url, m.release_date, m.genres,
                COALESCE(AVG(r.rating), 0) AS avg_rating,
                COUNT(r.id) AS review_count
         FROM movies m
         LEFT JOIN reviews r ON r.movie_id = m.id
         WHERE m.id = :id
         GROUP BY m.id'
    );
    $movieStmt->execute(['id' => $id]);
    $movie = $movieStmt->fetch();

    if (!$movie) {
        Http::json(['error' => 'Movie not found.'], 404);
    }

    $reviewsStmt = $pdo->prepare(
        'SELECT r.id, r.user_id, u.display_name, r.rating, r.review_text, r.created_at
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.movie_id = :movie_id
         ORDER BY r.created_at DESC
         LIMIT 20'
    );
    $reviewsStmt->execute(['movie_id' => $id]);

    $threadsStmt = $pdo->prepare(
        'SELECT t.id, t.movie_id, t.user_id, u.display_name, t.title, t.content, t.created_at,
                (SELECT COUNT(*) FROM forum_posts fp WHERE fp.thread_id = t.id) AS post_count
         FROM forum_threads t
         JOIN users u ON u.id = t.user_id
         WHERE t.movie_id = :movie_id
         ORDER BY t.created_at DESC
         LIMIT 20'
    );
    $threadsStmt->execute(['movie_id' => $id]);

    Http::json([
        'movie' => $movie,
        'reviews' => $reviewsStmt->fetchAll(),
        'threads' => $threadsStmt->fetchAll(),
    ]);
}

$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE m.title LIKE :q OR m.overview LIKE :q';
    $params['q'] = '%' . $search . '%';
}

$totalSql = 'SELECT COUNT(*) AS total FROM movies m ' . $where;
$totalStmt = $pdo->prepare($totalSql);
$totalStmt->execute($params);
$total = (int) ($totalStmt->fetch()['total'] ?? 0);

$listSql =
    'SELECT m.id, m.title, m.overview, m.poster_url, m.release_date, m.genres,
            COALESCE(AVG(r.rating), 0) AS avg_rating,
            COUNT(r.id) AS review_count
     FROM movies m
     LEFT JOIN reviews r ON r.movie_id = m.id
     ' . $where . '
     GROUP BY m.id
     ORDER BY m.release_date DESC
     LIMIT :limit OFFSET :offset';

$listStmt = $pdo->prepare($listSql);
if ($search !== '') {
    $listStmt->bindValue(':q', '%' . $search . '%', PDO::PARAM_STR);
}
$listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();

Http::json([
    'movies' => $listStmt->fetchAll(),
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
    ],
]);
