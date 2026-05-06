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
	$rating = (string)($_POST['rating'] ?? "");
	$comment = (string)($_POST['review'] ?? "");

	if ($request == "" || $user_id == "") {
		echo json_encode(["ok" => false, "message" => "Missing type, or user_id"]);
		exit(0);
	}

	switch ($request) {
		case "addtowatchlist":
			if ($movie_id == "") {
				echo json_encode(["ok" => false, "message" => "Missing movie_id"]);
				exit(0);
			}
			$response = $client->send_request([
				"type" => "add_to_watch",
				"user_id" => $user_id,
				"movie_id" => $movie_id
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok" => false, "message" => "failed"]);
				exit(0);
			}
			echo json_encode(["ok" => true, "status" => "added", "message" => "movie added to watchlist"]);
			exit(0);
			break;
		case "removefromwatchlist":
			$response = $client->send_request([
				"type" => "remove_from_watch",
				"user_id" => $user_id,
				"movie_id" => $movie_id
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok" => false, "message" => "failed"]);
				exit(0);
			}
			echo json_encode(["ok" => true, "status" => "removed", "message" => "movie removed from watchlist"]);
			exit(0);
			break;
		case "getwatchlist":
			$response = $client->send_request([
				"type" => "get_watchlist",
				"user_id" => $user_id,
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok" => false, "message" => "failed"]);
				exit(0);
			}
			$movies = $response['movies'];
			echo json_encode(["ok" => true, "movies" => $movies, "message" => "Success"]);
			exit(0);
			break;
		case "addreview":
			$response = $client->send_request([
				"type" => "submit_review",
				"user_id" => $user_id,
				"movie_id" => $movie_id,
				"rating" => $rating,
				"comment" => $comment
			]);
			if (!is_array($response) || empty($response["ok"])) {
				echo json_encode(["ok" => false, "message" => "failed"]);
				exit(0);
			}
			echo json_encode(["ok" => true, "status" => "added", "message" => "rating and review added"]);
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