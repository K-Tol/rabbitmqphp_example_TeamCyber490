<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$sessionKey = $_COOKIE['session_key'] ?? '';
if ($sessionKey === '' || strlen($sessionKey) !== 64) {
    header('Location: /index.html');
    exit;
}

$pdo = db();
$now = time();

$stmt = $pdo->prepare(
    'SELECT u.username
     FROM sessions s
     JOIN users u ON u.id = s.user_id
     WHERE s.session_key = ? AND s.end_time > ?
     LIMIT 1'
);
$stmt->execute([$sessionKey, $now]);
$row = $stmt->fetch();

if (!$row) {
    header('Location: /index.html');
    exit;
}

$username = $row['username'];
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Welcome</title></head>
<body>
  <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
  <p>Your session is validated via the database sessions table.</p>
  <p><a href="/logout.php">Logout</a></p>
</body>
</html>