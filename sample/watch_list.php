<?php
declare(strict_types=1);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

try {

	$client = new rabbitMQClient("movieServer.ini", "movieServer");
	$request = strtolower(trim($_POST['type'] ?? ""));
	$user_id = trim($_POST['user_id'] ?? "");
	$movie_id = (string)($_POST['movie_id'] ?? "");

	if ($request == "" || $user_id == "" || $movie_id == "") {
		echo json_encode(["ok" => false, "message" => "Missing type, username, or password"]);
		exit(0);
	}

	switch ($request) {
		case "addToWatchList":
			$response = $client->send_request([
				"type" => "add_to_watch",
				"user_id" => $user_id,
				"movie_id" => $movie_id
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok" => false, "message" => "login failed"]);
				exit(0);
			}
			echo json_encode(["ok" => true, "status" => "added", "message" => "movie added to watchlist"]);
			exit(0);
			break;
		default:
			echo json_encode(["ok" => false, "status" => "not added", "message" => "movie was not added to watchlist, something went wrong"]);
			exit(0);
	}
} catch (Exception $e) {
	echo $e->getMessage();
}
?>