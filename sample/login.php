<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// from api_listener
function distribute_log(string $log) {
  $ClusterVmName = gethostname();
  $nameAndLog = "[$ClusterVmName]" . $log;
// change to the correct queue on other environments
  $rabbitClient = new rabbitMQClient("logging.ini", "qa_web_log");
  $rabbitClient -> publish([
    "type" => "cluster_log",
    "source" => $ClusterVmName,
    "message" => $log,
    "timestamp" => time()
  ]);
}

try {
	if (!isset($_POST)) {
		echo json_encode(["ok" => false, "message" => "NO POST MESSAGE SET, POLITELY FUCK OFF"]);
		distribute_log("NO POST MESSAGE SET, POLITELY FUCK OFF");
		exit(0);
	}
	if ($_SERVER["REQUEST_METHOD"] !== "POST") {
		echo json_encode(["ok" => false, "message" => "Only type POST allowed"]);
		distribute_log("Only type POST allowed");
		exit(0);
	}

	$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
	$request = strtolower(trim($_POST['type'] ?? ""));
	$username = trim($_POST['uname'] ?? "");
	$password = (string)($_POST['pword'] ?? "");

	if ($request == "" || $username == "" || $password == "") {
		echo json_encode(["ok" => false, "message" => "Missing type, username, or password"]);
		distribute_log("Missing type, username, or password");
		exit(0);
	}

	switch ($request) {
		case "login":
			$response = $client->send_request([
				"type" => "login",
				"username" => $username,
				"password" => $password
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok" => false, "message" => "login failed"]);
				distribute_log("login failed");
				exit(0);
			}
			$sessionKey = $response["session_key"] ?? "";
			if ($sessionKey == "") {
				echo json_encode(["ok" => false, "message" => "no session key"]);
				distribute_log("no session key");
				exit(0);
			}
			setcookie("session_key", $sessionKey, [
				"expires" => time() + 86400,
				 "path" => "/",
				 "httponly" => true,
				 ]);
			echo json_encode(["ok" => true, "status" => "authorized", "message" => "login sucess"]);
			distribute_log("login sucess");
			exit(0);
			break;
		case "register":
			$response = $client->send_request([
				"type" => "register",
				"username" => $username,
				"password" => $password
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok" => false, "message" => "register failed"]);
				distribute_log("register failed");
				exit(0);
			}
			echo json_encode(["ok" => true, "status" => "registered", "message" => "registration successful"]);
			distribute_log("registration successful");
			break;
		default:
			echo json_encode(["ok" => false, "message" => "unsupported request type, politely FUCK OFF"]);
			distribute_log("unsupported request type, politely FUCK OFF");
			exit(0);
	}
} catch (Exception $e) {
	echo $e->getMessage();
	distribute_log($e);
}
?>