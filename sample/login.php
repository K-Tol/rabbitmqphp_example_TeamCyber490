<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

try {
	if (!isset($_POST)) {
		$msg = "NO POST MESSAGE SET, POLITELY FUCK OFF";
		echo json_encode($msg);
		exit(0);
	}
	if ($_SERVER["REQUEST_METHOD"] !== "POST") {
		$msg = "Only type POST allowed";
		echo json_encode($msg);
		exit(0);
	}

	$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
	$request = strtolower(trim($_POST['type'] ?? ""));
	$username = trim($_POST['uname'] ?? "");
	$password = (string)($_POST['pword'] ?? "");

	$response = "unsupported request type, politely FUCK OFF";

	if ($request == "" || $username == "" || $password == "") {
		$msg = "Missing type, username, or password";
		echo json_encode($msg);
		exit(0);
	}

	switch ($request) {
		case "login":
			$client->send_request([
				"type" => "login",
				"username" => $username,
				"password" => $password
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok"=>false, "message"=>"login failed"]);
				exit(0);
			}
			$response = "(testing) login, yeah we can do that";
			echo json_encode(["ok"=>true, "status"=>"authorized", "message"=>"login sucess"]);
			// add session hash checker
			exit(0);
			break;
		case "register":
			$client->send_request([
				"type" => "register",
				"username" => $username,
				"password" => $password
			]);
			$response = "registering...";
			break;
	}
	echo json_encode($response);
	exit(0);
} catch (Exception $e) {
	echo $e->getMessage();
}
