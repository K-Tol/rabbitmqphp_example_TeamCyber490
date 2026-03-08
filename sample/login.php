<?php
try {
	if (!isset($_POST)) {
		$msg = "NO POST MESSAGE SET, POLITELY FUCK OFF";
		echo json_encode($msg);
		exit(0);
	}
	$request = $_POST['type'];
	$username = $_POST['uname'];
	$password = $_POST['pword'];
	$response = "unsupported request type, politely FUCK OFF";
	switch ($request) {
		case "login":
			$client = new rabbitMQClient("testRabbitMQ.ini","testServer");
			$client->send_request([
				"type"=>"login", 
				"username"=>$username, 
				"password"=>$password]);
			$response = "(testing) login, yeah we can do that";
			break;
	}
	echo json_encode($response);
	exit(0);
} catch (Exception $e) {
	echo $e->getMessage();
}
