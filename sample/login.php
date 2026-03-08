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
	switch ($request["type"]) {
		case "login":
			$response = "login, yeah we can do that";
			break;
	}
	echo json_encode($response);
	exit(0);
} catch (Exception $e) {
	echo $e->getMessage();
}
