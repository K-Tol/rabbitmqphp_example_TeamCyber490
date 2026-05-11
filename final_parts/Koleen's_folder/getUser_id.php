<?php
declare(strict_types=1);
// boilerplate from checkLogin.php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$request = strtolower(trim($_POST['type'] ?? ""));
$username = trim($_POST['username'] ?? "");

$authClient = new rabbitMQClient("testRabbitMQ.ini","testServer");
$resp = $authClient->send_request([
    "type" => "get_id",
    "username" => $username]);

if (!is_array($resp) || empty($resp["ok"])) {
  echo json_encode([
    "ok" => false, 
    "status" => "no-response",
    "message" => "the response is not an array, or \"ok\" is empty"]);
  exit;
}

echo json_encode(["ok" => true, 
  "status" => "got-user_id", 
  "message" => "successfully got user_id",
  "user_id" => $resp["user_id"] ?? "empty user_id"]);
?>