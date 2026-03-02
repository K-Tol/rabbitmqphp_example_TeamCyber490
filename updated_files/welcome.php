<?php
declare(strict_types=1);

require_once __DIR__ . '/rmq_client.php';

$sessionKey = $_COOKIE['session_key'] ?? '';
if ($sessionKey === '' || strlen($sessionKey) !== 64) {
  header("Location: /index.html");
  exit;
}

$resp = rmq()->send_request(["type"=>"validate_session","session_key"=>$sessionKey]);

if (!is_array($resp) || empty($resp["ok"])) {
  header("Location: /index.html");
  exit;
}

$username = $resp["username"] ?? "user";
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Welcome</title></head>
<body>
  <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
  <p>Session validated via DB sessions table (not PHP sessions).</p>
  <p><a href="/logout.php">Logout</a></p>
</body>
</html>