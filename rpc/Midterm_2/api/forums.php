<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Http.php';
require_once __DIR__ . '/../lib/EventBridge.php';

$pdo = Database::get();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $movieId = (int) ($_GET['movie_id'] ?? 0);
    $threadId = (int) ($_GET['thread_id'] ?? 0);

    if ($threadId > 0) {
        $threadStmt = $pdo->prepare(
            'SELECT t.id, t.movie_id, t.user_id, u.display_name, t.title, t.content, t.created_at
             FROM forum_threads t
             JOIN users u ON u.id = t.user_id
             WHERE t.id = :thread_id'
        );
        $threadStmt->execute(['thread_id' => $threadId]);
        $thread = $threadStmt->fetch();

        if (!$thread) {
            Http::json(['error' => 'Thread not found.'], 404);
        }

        $postsStmt = $pdo->prepare(
            'SELECT p.id, p.thread_id, p.user_id, u.display_name, p.content, p.created_at
             FROM forum_posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.thread_id = :thread_id
             ORDER BY p.created_at ASC'
        );
        $postsStmt->execute(['thread_id' => $threadId]);

        Http::json(['thread' => $thread, 'posts' => $postsStmt->fetchAll()]);
    }

    if ($movieId <= 0) {
        Http::json(['error' => 'movie_id is required.'], 422);
    }

    $stmt = $pdo->prepare(
        'SELECT t.id, t.movie_id, t.user_id, u.display_name, t.title, t.content, t.created_at,
                (SELECT COUNT(*) FROM forum_posts p WHERE p.thread_id = t.id) AS post_count
         FROM forum_threads t
         JOIN users u ON u.id = t.user_id
         WHERE t.movie_id = :movie_id
         ORDER BY t.created_at DESC'
    );
    $stmt->execute(['movie_id' => $movieId]);

    Http::json(['threads' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $body = Http::body();
    $type = trim((string) ($body['type'] ?? 'thread'));

    if ($type === 'thread') {
        $movieId = (int) ($body['movie_id'] ?? 0);
        $userId = (int) ($body['user_id'] ?? 0);
        $title = trim((string) ($body['title'] ?? ''));
        $content = trim((string) ($body['content'] ?? ''));

        if ($movieId <= 0 || $userId <= 0 || $title === '' || $content === '') {
            Http::json(['error' => 'movie_id, user_id, title, and content are required.'], 422);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO forum_threads (movie_id, user_id, title, content)
             VALUES (:movie_id, :user_id, :title, :content)'
        );
        $stmt->execute([
            'movie_id' => $movieId,
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
        ]);

        $threadId = (int) $pdo->lastInsertId();
        EventBridge::publish('forum.thread.created', ['thread_id' => $threadId, 'movie_id' => $movieId]);

        Http::json(['message' => 'Thread created.', 'thread_id' => $threadId], 201);
    }

    if ($type === 'post') {
        $threadId = (int) ($body['thread_id'] ?? 0);
        $userId = (int) ($body['user_id'] ?? 0);
        $content = trim((string) ($body['content'] ?? ''));

        if ($threadId <= 0 || $userId <= 0 || $content === '') {
            Http::json(['error' => 'thread_id, user_id, and content are required.'], 422);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO forum_posts (thread_id, user_id, content)
             VALUES (:thread_id, :user_id, :content)'
        );
        $stmt->execute([
            'thread_id' => $threadId,
            'user_id' => $userId,
            'content' => $content,
        ]);

        $postId = (int) $pdo->lastInsertId();
        EventBridge::publish('forum.post.created', ['post_id' => $postId, 'thread_id' => $threadId]);

        Http::json(['message' => 'Post added.', 'post_id' => $postId], 201);
    }

    Http::json(['error' => 'type must be thread or post.'], 422);
}

Http::json(['error' => 'Method not allowed.'], 405);
