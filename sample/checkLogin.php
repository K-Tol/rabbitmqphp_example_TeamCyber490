<?php
declare(strict_types=1);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$sessionKey = $_COOKIE['session_key'] ?? '';
if ($sessionKey === '' || strlen($sessionKey) !== 64) {
  echo json_encode([
    "ok" => false, 
    "status" => "not-authorized", 
    "message" => "Not Logged in"]);
  exit;
}

$authClient = new rabbitMQClient("testRabbitMQ.ini","testServer");
$resp = $authClient->send_request([
    "type"=>"validate_session",
    "session_key"=>$sessionKey]);

if (!is_array($resp) || empty($resp["ok"])) {
  echo json_encode([
    "ok" => false, 
    "status" => "not-authorized", 
    "message" => "Not Logged in"]);
  exit;
}

echo json_encode(["ok" => true, 
  "status" => "authorized", 
  "message" => "logged in",
  "username" => $resp["username"] ?? "empty username",
  "user_id" => $resp["user_id"] ?? "empty user_id"]);

?>