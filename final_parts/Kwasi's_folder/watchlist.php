<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

try {
	if (!isset($_POST)) {
		echo json_encode(["ok" => false, "message" => "NO POST MESSAGE SET"]);
		exit(0);
	}
	if ($_SERVER["REQUEST_METHOD"] !== "POST") {
		echo json_encode(["ok" => false, "message" => "Only type POST allowed"]);
		exit(0);
	}

	$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
	$action = strtolower(trim($_POST['action'] ?? ""));
	$session_key = trim($_COOKIE['session_key'] ?? "");
	$target_user_id = isset($_POST['target_user_id']) ? intval($_POST['target_user_id']) : 0;

	if ($action == "" || $session_key == "") {
		echo json_encode(["ok" => false, "message" => "Missing action or session key"]);
		exit(0);
	}

	switch ($action) {
		case "follow_user":
			if ($target_user_id <= 0) {
				echo json_encode(["ok" => false, "message" => "Invalid target_user_id"]);
				exit(0);
			}
			$response = $client->send_request([
				"type" => "follow_user",
				"session_key" => $session_key,
				"target_user_id" => $target_user_id
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok" => false, "message" => $response["error"] ?? "Failed to follow user"]);
				exit(0);
			}
			echo json_encode(["ok" => true, "message" => "User followed successfully"]);
			exit(0);
			break;

		case "unfollow_user":
			if ($target_user_id <= 0) {
				echo json_encode(["ok" => false, "message" => "Invalid target_user_id"]);
				exit(0);
			}
			$response = $client->send_request([
				"type" => "unfollow_user",
				"session_key" => $session_key,
				"target_user_id" => $target_user_id
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok" => false, "message" => $response["error"] ?? "Failed to unfollow user"]);
				exit(0);
			}
			echo json_encode(["ok" => true, "message" => "User unfollowed successfully"]);
			exit(0);
			break;

		case "get_following":
			$response = $client->send_request([
				"type" => "get_following",
				"session_key" => $session_key
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok" => false, "message" => "Failed to retrieve following list"]);
				exit(0);
			}
			echo json_encode(["ok" => true, "users" => $response["users"] ?? []]);
			exit(0);
			break;

		case "get_followers":
			$response = $client->send_request([
				"type" => "get_followers",
				"session_key" => $session_key
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok" => false, "message" => "Failed to retrieve followers list"]);
				exit(0);
			}
			echo json_encode(["ok" => true, "users" => $response["users"] ?? []]);
			exit(0);
			break;

		case "is_following_user":
			if ($target_user_id <= 0) {
				echo json_encode(["ok" => false, "message" => "Invalid target_user_id"]);
				exit(0);
			}
			$response = $client->send_request([
				"type" => "is_following_user",
				"session_key" => $session_key,
				"target_user_id" => $target_user_id
			]);
			if (!is_array($response)) {
				echo json_encode(["ok" => false, "message" => "Failed to check follow status"]);
				exit(0);
			}
			echo json_encode(["ok" => true, "is_following" => $response["is_following"] ?? false]);
			exit(0);
			break;

		case "get_user_info":
			if ($target_user_id <= 0) {
				echo json_encode(["ok" => false, "message" => "Invalid target_user_id"]);
				exit(0);
			}
			$response = $client->send_request([
				"type" => "get_user_info",
				"session_key" => $session_key,
				"target_user_id" => $target_user_id
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok" => false, "message" => "Failed to retrieve user info"]);
				exit(0);
			}
			echo json_encode(["ok" => true, "user" => $response["user"] ?? []]);
			exit(0);
			break;

		default:
			echo json_encode(["ok" => false, "message" => "Unsupported action type"]);
			exit(0);
	}
} catch (Exception $e) {
	echo json_encode(["ok" => false, "message" => $e->getMessage()]);
}
?>
