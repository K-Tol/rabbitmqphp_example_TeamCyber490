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
        case "follow_user":
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
            echo json_encode(["ok" => true, "status" => "added", "message" => "user added to follow list: " . $target_user_id]);
            exit(0);
        case "unfollow_user":
            if ($user_id == "") {
                echo json_encode(["ok" => false, "message" => "Missing user_id"]);
                exit(0);
            }
            if ($target_user_id == "") {
                echo json_encode(["ok" => false, "message" => "Missing user id you're trying to unfollow"]);
                exit(0);
            }
            $response = $client -> send_request([
                "type" => "unfollow_user",
                "session_key" => $sessionKey,
                "user_id" => $user_id,
                "target_user_id" => $target_user_id
            ]);
            if (!is_array($response) || empty($response["ok"])) {
                echo json_encode(["ok" => false, "message" => "failed"]);
                exit(0);
            }
            echo json_encode(["ok" => true, "status" => "removed", "message" => "user unfollowed from list: " . $target_user_id]);
            exit(0);
        case "is_following_user":
            if ($user_id == "") {
                echo json_encode(["ok" => false, "message" => "Missing user_id"]);
                exit(0);
            }
            if ($target_user_id == "") {
                echo json_encode(["ok" => false, "message" => "Missing user id for follow check"]);
                exit(0);
            }
            $response = $client -> send_request([
                "type" => "is_following_user",
                "session_key" => $sessionKey,
                "user_id" => $user_id,
                "target_user_id" => $target_user_id
            ]);
            if (!is_array($response) || empty($response["ok"])) {
                echo json_encode(["ok" => false, "message" => "failed"]);
                exit(0);
            }
            if ($response["is_following"] == true) {
                echo json_encode(["ok" => true, "is_following" => $response["is_following"], "message" => "you are following: " . $target_user_id]);
                exit(0);
            }
            echo json_encode(["ok" => true, "is_following" => $response["is_following"], "message" => "you are not following: " . $target_user_id]);
            exit(0);
        case "get_following":
            if ($user_id == "") {
                echo json_encode(["ok" => false, "message" => "Missing user_id"]);
                exit(0);
            }
            $response = $client -> send_request([
                "type" => "get_following",
                "session_key" => $sessionKey,
                "user_id" => $user_id
                ]);
            if (!is_array($response) || empty($response["ok"])) {
                echo json_encode(["ok" => false, "message" => "failed"]);
                exit(0);
            }
            $users = $response["users"];
            echo json_encode(["ok" => true, "users" => $users, "message" => "Got following"]);
            exit(0);
        case "get_followers":
            if ($user_id == "") {
                echo json_encode(["ok" => false, "message" => "Missing user_id"]);
                exit(0);
            }
            $response = $client -> send_request([
                "type" => "get_followers",
                "session_key" => $sessionKey,
                "user_id" => $user_id
                ]);
            if (!is_array($response) || empty($response["ok"])) {
                echo json_encode(["ok" => false, "message" => "failed"]);
                exit(0);
            }
            $users = $response["users"];
            echo json_encode(["ok" => true, "users" => $users, "message" => "Got followers"]);
            exit(0);
        default:
            echo json_encode(["ok" => false, "status" => "error", "message" => "something went wrong"]);
            exit(0);
        }
    } catch (Exception $e) {
        echo $e->getMessage();
}
?>