<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Http.php';
require_once __DIR__ . '/../lib/EventBridge.php';

$pdo = Database::get();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method !== 'POST') {
    Http::json(['error' => 'Method not allowed.'], 405);
}

$body = Http::body();
$days = max(1, min(30, (int) ($body['days'] ?? 7)));

$stmt = $pdo->prepare(
    'SELECT w.user_id, w.movie_id, u.email, u.display_name, m.title, m.release_date
     FROM watchlists w
     JOIN users u ON u.id = w.user_id
     JOIN movies m ON m.id = w.movie_id
     WHERE w.notification_sent_at IS NULL
       AND u.email IS NOT NULL
       AND u.email <> ""
       AND m.release_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)'
);
$stmt->bindValue(':days', $days, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$markSent = $pdo->prepare(
    'UPDATE watchlists
     SET notification_sent_at = CURRENT_TIMESTAMP
     WHERE user_id = :user_id AND movie_id = :movie_id'
);

$sent = [];

foreach ($rows as $row) {
    $subject = 'Upcoming movie from your watchlist';
    $message = sprintf(
        "Hi %s, %s is releasing on %s.",
        (string) ($row['display_name'] ?: 'Movie Fan'),
        (string) $row['title'],
        (string) $row['release_date']
    );

    $ok = mail((string) $row['email'], $subject, $message);

    if ($ok) {
        $markSent->execute([
            'user_id' => (int) $row['user_id'],
            'movie_id' => (int) $row['movie_id'],
        ]);

        EventBridge::publish('watchlist.notification.sent', [
            'user_id' => (int) $row['user_id'],
            'movie_id' => (int) $row['movie_id'],
            'channel' => 'email',
        ]);

        $sent[] = [
            'user_id' => (int) $row['user_id'],
            'movie_id' => (int) $row['movie_id'],
            'email' => (string) $row['email'],
        ];
    }
}

Http::json([
    'checked' => count($rows),
    'sent' => count($sent),
    'details' => $sent,
]);
