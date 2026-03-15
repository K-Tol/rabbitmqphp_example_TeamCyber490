#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function movieDbConfig(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $config = require __DIR__ . '/movie_db_config.php';
    return $config;
}

function movieDB(): mysqli
{
    static $connect = null;
    if ($connect instanceof mysqli) {
        return $connect;
    }

    $db = movieDbConfig();
    $connect = new mysqli($db['host'], $db['user'], $db['pass'], $db['name'], (int) $db['port']);
    if ($connect->connect_errno) {
        die('movie_db connection failed: ' . $connect->connect_error . PHP_EOL);
    }

    $charset = (string) ($db['charset'] ?? 'utf8mb4');
    $connect->set_charset($charset);

    return $connect;
}

function datasourceClient(): rabbitMQClient
{
    static $client = null;
    if ($client instanceof rabbitMQClient) {
        return $client;
    }

    $client = new rabbitMQClient('datasource.ini', 'datasourceServer');
    return $client;
}

function buildPosterUrl(?string $posterPath): ?string
{
    if ($posterPath === null || $posterPath === '') {
        return null;
    }

    if (str_starts_with($posterPath, 'http://') || str_starts_with($posterPath, 'https://')) {
        return $posterPath;
    }

    return 'https://image.tmdb.org/t/p/w500' . $posterPath;
}

function storeGenre(int $tmdbGenreId, string $name): array
{
    $stmt = movieDB()->prepare(
        'INSERT INTO genres (tmdb_genre_id, name)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name)'
    );
    if (!$stmt) {
        return ['success' => false, 'error' => movieDB()->error];
    }

    $stmt->bind_param('is', $tmdbGenreId, $name);
    $stmt->execute();

    return ['success' => true];
}

function storeMovie(array $movie, array $genreIds = []): array
{
    $stmt = movieDB()->prepare(
        'INSERT INTO movies
         (tmdb_id, title, overview, release_date, runtime, poster_path,
          backdrop_path, original_language, vote_average, vote_count, popularity,
          adult, last_synced_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
          title = VALUES(title),
          overview = VALUES(overview),
          release_date = VALUES(release_date),
          runtime = VALUES(runtime),
          poster_path = VALUES(poster_path),
          backdrop_path = VALUES(backdrop_path),
          original_language = VALUES(original_language),
          vote_average = VALUES(vote_average),
          vote_count = VALUES(vote_count),
          popularity = VALUES(popularity),
          adult = VALUES(adult),
          last_synced_at = UNIX_TIMESTAMP()'
    );

    if (!$stmt) {
        return ['success' => false, 'error' => movieDB()->error];
    }

    $tmdbId = isset($movie['tmdb_id']) ? (int) $movie['tmdb_id'] : 0;
    $title = isset($movie['title']) ? (string) $movie['title'] : '';
    $overview = array_key_exists('overview', $movie) ? (string) $movie['overview'] : null;
    $releaseDate = !empty($movie['release_date']) ? (string) $movie['release_date'] : null;
    $runtime = isset($movie['runtime']) && $movie['runtime'] !== null ? (int) $movie['runtime'] : null;
    $posterPath = array_key_exists('poster_path', $movie) ? (string) $movie['poster_path'] : null;
    $backdropPath = array_key_exists('backdrop_path', $movie) ? (string) $movie['backdrop_path'] : null;
    $originalLanguage = array_key_exists('original_language', $movie) ? (string) $movie['original_language'] : null;
    $voteAverage = isset($movie['vote_average']) ? (float) $movie['vote_average'] : 0.0;
    $voteCount = isset($movie['vote_count']) ? (int) $movie['vote_count'] : 0;
    $popularity = isset($movie['popularity']) ? (float) $movie['popularity'] : 0.0;
    $adult = !empty($movie['adult']) ? 1 : 0;

    $stmt->bind_param(
        'isssisssdidi',
        $tmdbId,
        $title,
        $overview,
        $releaseDate,
        $runtime,
        $posterPath,
        $backdropPath,
        $originalLanguage,
        $voteAverage,
        $voteCount,
        $popularity,
        $adult
    );
    $stmt->execute();

    $lookup = movieDB()->prepare('SELECT id FROM movies WHERE tmdb_id = ? LIMIT 1');
    $lookup->bind_param('i', $tmdbId);
    $lookup->execute();
    $row = $lookup->get_result()->fetch_assoc();

    if (!$row) {
        return ['success' => false, 'error' => 'store_failed'];
    }

    $movieId = (int) $row['id'];

    $delete = movieDB()->prepare('DELETE FROM movie_genres WHERE movie_id = ?');
    $delete->bind_param('i', $movieId);
    $delete->execute();

    foreach ($genreIds as $tmdbGenreId) {
        $tmdbGenreId = (int) $tmdbGenreId;
        $genreLookup = movieDB()->prepare('SELECT id FROM genres WHERE tmdb_genre_id = ? LIMIT 1');
        $genreLookup->bind_param('i', $tmdbGenreId);
        $genreLookup->execute();
        $genreRow = $genreLookup->get_result()->fetch_assoc();
        if (!$genreRow) {
            continue;
        }

        $genreId = (int) $genreRow['id'];
        $link = movieDB()->prepare('INSERT IGNORE INTO movie_genres (movie_id, genre_id) VALUES (?, ?)');
        $link->bind_param('ii', $movieId, $genreId);
        $link->execute();
    }

    return ['success' => true, 'movie_id' => $movieId];
}

function hydrateMovieRow(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'title' => (string) ($row['title'] ?? ''),
        'overview' => (string) ($row['overview'] ?? ''),
        'poster_url' => buildPosterUrl($row['poster_path'] ?? null),
        'release_date' => $row['release_date'] ?? null,
        'genres' => (string) ($row['genres'] ?? ''),
        'avg_rating' => (float) ($row['avg_rating'] ?? 0),
        'review_count' => (int) ($row['review_count'] ?? 0),
    ];
}

function getMovies(string $search = '', int $page = 1, int $limit = 20): array
{
    $page = max(1, $page);
    $limit = min(50, max(1, $limit));
    $offset = ($page - 1) * $limit;

    $search = trim($search);
    $where = '';
    $types = '';
    $params = [];

    if ($search !== '') {
        $where = 'WHERE m.title LIKE CONCAT("%", ?, "%") OR m.overview LIKE CONCAT("%", ?, "%")';
        $types = 'ss';
        $params[] = $search;
        $params[] = $search;
    }

    $db = movieDB();

    $countSql = "SELECT COUNT(*) AS total FROM movies m $where";
    $countStmt = $db->prepare($countSql);
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total = (int) (($countStmt->get_result()->fetch_assoc()['total'] ?? 0));

    $sql =
        "SELECT m.id, m.title, m.overview, m.poster_path, m.release_date,\n" .
        "       COALESCE(AVG(r.rating), 0) AS avg_rating,\n" .
        "       COUNT(r.id) AS review_count,\n" .
        "       GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS genres\n" .
        "FROM movies m\n" .
        "LEFT JOIN reviews r ON r.movie_id = m.id\n" .
        "LEFT JOIN movie_genres mg ON mg.movie_id = m.id\n" .
        "LEFT JOIN genres g ON g.id = mg.genre_id\n" .
        "$where\n" .
        "GROUP BY m.id\n" .
        "ORDER BY m.release_date DESC\n" .
        "LIMIT ? OFFSET ?";

    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $bindTypes = $types . 'ii';
        $bindValues = [...$params, $limit, $offset];
        $stmt->bind_param($bindTypes, ...$bindValues);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if ($search !== '' && count($rows) === 0) {
        datasourceClient()->send_request(['type' => 'sync_search', 'query' => $search]);

        $stmt = $db->prepare($sql);
        $bindTypes = 'ssii';
        $stmt->bind_param($bindTypes, $search, $search, $limit, $offset);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $countStmt = $db->prepare($countSql);
        $countStmt->bind_param('ss', $search, $search);
        $countStmt->execute();
        $total = (int) (($countStmt->get_result()->fetch_assoc()['total'] ?? 0));
    }

    return [
        'movies' => array_map('hydrateMovieRow', $rows),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ],
        'status_code' => 200,
    ];
}

function getMovieDetails(int $id): array
{
    if ($id <= 0) {
        return ['error' => 'Movie not found.', 'status_code' => 404];
    }

    $db = movieDB();
    $sql =
        'SELECT m.id, m.tmdb_id, m.title, m.overview, m.poster_path, m.release_date,\n' .
        '       COALESCE(AVG(r.rating), 0) AS avg_rating,\n' .
        '       COUNT(r.id) AS review_count,\n' .
        '       GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ", ") AS genres\n' .
        'FROM movies m\n' .
        'LEFT JOIN reviews r ON r.movie_id = m.id\n' .
        'LEFT JOIN movie_genres mg ON mg.movie_id = m.id\n' .
        'LEFT JOIN genres g ON g.id = mg.genre_id\n' .
        'WHERE m.id = ?\n' .
        'GROUP BY m.id\n' .
        'LIMIT 1';

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        datasourceClient()->send_request(['type' => 'sync_movie', 'tmdb_id' => $id]);

        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            return ['error' => 'Movie not found.', 'status_code' => 404];
        }
    }

    $movie = hydrateMovieRow($row);

    return [
        'movie' => $movie,
        'reviews' => [],
        'threads' => [],
        'status_code' => 200,
    ];
}

function requestProcessor(array $request): array
{
    if (!isset($request['type'])) {
        return ['success' => false, 'error' => 'unsupported_message_type', 'status_code' => 400];
    }

    switch ($request['type']) {
        case 'store_genre':
            return storeGenre((int) ($request['tmdb_genre_id'] ?? 0), (string) ($request['name'] ?? ''));
        case 'store_movie':
            return storeMovie((array) ($request['movie'] ?? []), (array) ($request['genre_ids'] ?? []));
        case 'GetMovies':
            return getMovies(
                (string) ($request['search'] ?? ''),
                (int) ($request['page'] ?? 1),
                (int) ($request['limit'] ?? 20)
            );
        case 'GetMovieDetails':
            return getMovieDetails((int) ($request['id'] ?? 0));
        default:
            return ['success' => false, 'error' => 'unsupported_message_type', 'status_code' => 400];
    }
}

$server = new rabbitMQServer('movieServer.ini', 'movieServer');
$server->process_requests('requestProcessor');
