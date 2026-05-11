<?php
declare(strict_types=1);
// boilerplate from checkLogin.php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$request = strtolower(trim($_POST['type'] ?? ""));
$user_id = trim($_POST['user_id'] ?? "");

$authClient = new rabbitMQClient("testRabbitMQ.ini","testServer");
$resp = $authClient->send_request([
    "type" => "get_username",
    "user_id" => $user_id]);

if (!is_array($resp) || empty($resp["ok"])) {
  echo json_encode([
    "ok" => false, 
    "status" => "no-response",
    "message" => "the response is not an array, or \"ok\" is empty"]);
  exit;
}

echo json_encode(["ok" => true, 
  "status" => "got-username", 
  "message" => "successfully got username",
  "username" => $resp["username"] ?? "empty username",
  "user_id" => $resp["user_id"] ?? "empty user_id"]);
?>