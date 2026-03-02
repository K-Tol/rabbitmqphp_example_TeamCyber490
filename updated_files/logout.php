<?php
declare(strict_types=1);

require_once __DIR__ . '/rmq_client.php';

$sessionKey = $_COOKIE['session_key'] ?? '';
if ($sessionKey !== '' && strlen($sessionKey) === 64) {
  rmq()->send_request(["type"=>"logout","session_key"=>$sessionKey]);
}

setcookie('session_key', '', time() - 3600, '/');
header("Location: /index.html");
exit;