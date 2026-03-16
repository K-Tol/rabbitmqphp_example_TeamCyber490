<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

header("Content-Type: application/json");

try {

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["ok" => false, "message" => "POST only"]);
        exit;
    }

    $type = strtolower(trim($_POST['type'] ?? ""));

    if ($type == "") {
        echo json_encode(["ok" => false, "message" => "Missing type"]);
        exit;
    }

    $client = new rabbitMQClient("movieServer.ini", "movieServer");

    switch($type) {

        case "add_review":

            $user_id = (int)($_POST['user_id'] ?? 0);
            $movie_id = (int)($_POST['movie_id'] ?? 0);
            $rating = (int)($_POST['rating'] ?? 0);
            $review = trim($_POST['review'] ?? "");

            if ($user_id == 0 || $movie_id == 0) {
                echo json_encode(["ok" => false, "message" => "Missing IDs"]);
                exit;
            }

            $response = $client->send_request([
                "type" => "add_review",
                "user_id" => $user_id,
                "movie_id" => $movie_id,
                "rating" => $rating,
                "review" => $review
            ]);

            echo json_encode($response);
            exit;

        case "get_reviews":

            $movie_id = (int)($_POST['movie_id'] ?? 0);

            $response = $client->send_request([
                "type" => "get_reviews",
                "movie_id" => $movie_id
            ]);

            echo json_encode($response);
            exit;

        default:
            echo json_encode(["ok" => false, "message" => "Invalid request"]);
            exit;
    }

}
catch(Exception $e) {
    echo json_encode([
        "ok" => false,
        "error" => $e->getMessage()
    ]);
}
?>