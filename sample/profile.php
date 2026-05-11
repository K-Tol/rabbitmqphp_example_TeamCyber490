<?php
    require_once('path.inc');
    require_once('get_host_info.inc');
    require_once('rabbitMQLib.inc');

    try{

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
	$request = strtolower(trim($_POST['type'] ?? ""));
	$user_id = trim($_POST['user_id'] ?? "");
    $target_user_id = trim($_POST['target_user_id'] ?? "");
    $sessionKey = $_COOKIE['session_key'] ?? '';

	if ($request == "") {
		echo json_encode(["ok" => false, "message" => "Missing type"]);
		exit(0);
	}

        switch ($request) {
            case "followuser":
                if ($user_id == "") {
                    echo json_encode(["ok" => false, "message" => "Missing user_id"]);
                    exit(0);
                }
                if ($target_user_id == "") {
                    echo json_encode(["ok" => false, "message" => "Missing user id you're trying to follow"]);
                    exit(0);
                }
                $response = $client->send_request([
                    "type" => "follow_user",
                    "session_key" => $sessionKey,
                    "user_id" => $user_id,
                    "target_user_id" => $target_user_id,
                ]);
                if (!is_array($response) || empty($response["ok"])) {
                    echo json_encode(["ok" => false, "message" => "failed"]);
                    exit(0);
                }
                echo json_encode(["ok" => true, "status" => "added", "message" => "user added to follow list"]);
                exit(0);
                break;
			default:
				echo json_encode(["ok" => false, "status" => "error", "message" => "something went wrong"]);
				exit(0);
        }
    } catch (Exception $e) {
        echo $e->getMessage();
}
?>