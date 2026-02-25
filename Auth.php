<?php
// auth.php  (Web VM)
// Receives JSON from browser, sends request to RabbitMQ, returns JSON response.

header('Content-Type: application/json; charset=utf-8');

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function json_out(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

function sendRequestToRabbitMQ(array $request) {
  // Connect to RabbitMQ using the INI + section name
  $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

  // RPC call: send request, wait for reply
  $response = $client->send_request($request);

  if ($response === null) {
    throw new Exception("No response from RabbitMQ worker");
  }

  return $response;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(405, ["status" => "error", "message" => "POST method required"]);
}

// Read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  json_out(400, ["status" => "error", "message" => "Invalid JSON"]);
}

// Extract inputs
$action   = strtolower(trim($data['action'] ?? ''));
$username = trim($data['username'] ?? '');
$password = (string)($data['password'] ?? '');

// Validate action
if (!in_array($action, ['login', 'register'], true)) {
  json_out(400, ["status" => "error", "message" => "Action must be 'login' or 'register'"]);
}

// Validate fields
if ($username === '' || $password === '') {
  json_out(400, ["status" => "error", "message" => "Username and password required"]);
}

// Optional basic rules (you can adjust)
if (strlen($username) < 3) {
  json_out(400, ["status" => "error", "message" => "Username must be at least 3 characters"]);
}
if ($action === 'register' && strlen($password) < 6) {
  json_out(400, ["status" => "error", "message" => "Password must be at least 6 characters"]);
}

// Build request for RabbitMQ worker
$request = [
  "type"     => $action,     // IMPORTANT: worker switch expects "login" / "register"
  "username" => $username,
  "password" => $password
];

try {
  $result = sendRequestToRabbitMQ($request);

  /**
   * Worker response handling:
   * - Best practice: worker returns array: ["status"=>"success|error", "message"=>"..."]
   * - Some starter workers return boolean true/false
   */
  if (is_array($result)) {
    $status = $result["status"] ?? "error";
    $message = $result["message"] ?? "Unknown response";

    if ($status === "success") {
      json_out(200, ["status" => "success", "message" => $message] + $result);
    } else {
      json_out(401, ["status" => "error", "message" => $message] + $result);
    }
  }

  // Boolean fallback
  if ($result === true) {
    json_out(200, ["status" => "success", "message" => ucfirst($action) . " successful"]);
  } else {
    json_out(401, ["status" => "error", "message" => ucfirst($action) . " failed"]);
  }

} catch (Throwable $e) {
  json_out(500, ["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}