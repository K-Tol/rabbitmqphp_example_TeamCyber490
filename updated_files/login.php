<?php
declare(strict_types=1);

require_once __DIR__ . '/rmq_client.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES);
  exit;
}

function set_session_cookie(string $sessionKey): void {
  setcookie('session_key', $sessionKey, [
    'expires'  => time() + 86400,
    'path'     => '/',
    'httponly' => true,
    'secure'   => false,  // set true when HTTPS enabled
    'samesite' => 'Lax'
  ]);
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ["ok"=>false, "error"=>"POST only"]);

  $type = strtolower(trim($_POST['type'] ?? ''));
  $u = trim($_POST['uname'] ?? '');
  $p = (string)($_POST['pword'] ?? '');

  if ($type === '' || $u === '' || $p === '') respond(400, ["ok"=>false, "error"=>"Missing type/uname/pword"]);

  if ($type === 'register') {
    $resp = rmq()->send_request(["type"=>"register","username"=>$u,"password"=>$p]);
    if (!is_array($resp) || empty($resp["ok"])) {
      $err = $resp["error"] ?? "register_failed";
      respond(200, ["ok"=>false, "status"=>"denied", "error"=>$err, "message"=>"Register failed"]);
    }
    respond(200, ["ok"=>true, "status"=>"registered", "message"=>"Registered. Now login."]);
  }

  if ($type === 'login') {
    $resp = rmq()->send_request(["type"=>"login","username"=>$u,"password"=>$p]);
    if (!is_array($resp) || empty($resp["ok"])) {
      respond(200, ["ok"=>false, "status"=>"denied", "message"=>"Invalid credentials"]);
    }

    $sessionKey = $resp["session_key"] ?? "";
    if ($sessionKey === "" || strlen($sessionKey) !== 64) {
      respond(500, ["ok"=>false, "status"=>"error", "message"=>"No session key returned"]);
    }

    set_session_cookie($sessionKey);
    respond(200, ["ok"=>true, "status"=>"authorized", "message"=>"Login success"]);
  }

  respond(400, ["ok"=>false, "error"=>"Unknown type"]);
} catch (Throwable $e) {
  respond(500, ["ok"=>false, "error"=>"Server error", "detail"=>$e->getMessage()]);
}