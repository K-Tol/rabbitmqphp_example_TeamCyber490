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
	$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
	$request = $_POST['type'];
	$username = $_POST['uname'];
	$password = $_POST['pword'];
	$response = "unsupported request type, politely FUCK OFF";

	if ($type == "" || $username == "" || $password == "") {
		$msg = "Missing type, username, or password";
		echo json_encode($msg);
	}

	switch ($request) {
		case "login":
			$client->send_request([
				"type" => "login",
				"username" => $username,
				"password" => $password
			]);
			$response = "(testing) login, yeah we can do that";
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
