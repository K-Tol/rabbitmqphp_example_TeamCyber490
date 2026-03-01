<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rmq.php';

$sessionKey = $_COOKIE['session_key'] ?? '';
$ip  = $_SERVER['REMOTE_ADDR'] ?? null;
$ua  = $_SERVER['HTTP_USER_AGENT'] ?? null;

if ($sessionKey !== '' && strlen($sessionKey) === 64) {
    $pdo = db();
    $del = $pdo->prepare('DELETE FROM sessions WHERE session_key = ?');
    $del->execute([$sessionKey]);

    publish_auth_event([
        'type' => 'logout',
        'session_key_prefix' => substr($sessionKey, 0, 8),
        'ts' => time(),
        'ip' => $ip,
        'user_agent' => $ua
    ]);
}

setcookie('session_key', '', time() - 3600, '/');
header('Location: /index.html');
exit;