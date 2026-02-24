<?php
// auth.php
require_once(__DIR__ . '/messageQueue.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "POST only"]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

$action = strtolower(trim((string)($data['action'] ?? '')));
$username = trim((string)($data['username'] ?? ''));
$password = (string)($data['password'] ?? '');

if (!in_array($action, ['login', 'register'], true)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "action must be login or register"]);
    exit;
}

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "username and password required"]);
    exit;
}

$request = [
    "type"     => $action,       // matches your worker switch: "login" or "register"
    "username" => $username,
    "password" => $password,
    "message"  => "HI"
];

$response = sendRequestToRabbitMQ($request);

// Optional: set HTTP codes
if (($response['status'] ?? '') !== 'success') {
    http_response_code(401);
}

echo json_encode($response);