<?php
    require_once('path.inc');
    require_once('get_host_info_inc');
    require_once('rabbitMQLib.inc');

    try {
        if (!isset($_POST)) {
            echo json_encode(["ok" => false, "message" => "NO POST MESSAGE SET, POLITELY FUCK OFF"]);
            exit(0);
        }
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            echo json_encode(["ok" => false, "message" => "Only type POST allowed"]);
            exit(0);
        }

        $client = new rabbitMQClient("movieServer.ini", "movieServer");
        $request = strtolower(trim($_POST['type'] ?? ""));
        $query = (string)($_POST['query'] ?? "");

        if ($request == "" || $query == "") {
            echo json_encode(["ok" => false, "message" => "Missing type"]);
            exit(0);
        }

        //This switch block needs work

        switch($request) {
            case "search_movies":
                $response = $client->send_request([
                "type" => "search_movies",
                "query" => $query
                ]);
            break;
            case "get_movie_details":
                $response = $client->send_request([
                "type" => "getFullDetails",
                "query" => $response["movies"]
                ]);
            break;
            default:
                echo json_encode(["ok" => false, "message" => "unsupported request type, politely FUCK OFF"]);
                exit(0);
        }

        $response["movies"];


        switch ($request) {
            case "login":
                $response = $client->send_request([
                    "type" => "login",
                    "username" => $username,
                    "password" => $password
                ]);
                if (!is_array($response) || empty($response["ok"])) {
                    echo json_encode(["ok" => false, "message" => "login failed"]);
                    exit(0);
                }
                $sessionKey = $response["session_key"] ?? "";
                if ($sessionKey == "") {
                    echo json_encode(["ok" => false, "message" => "no session key"]);
                    exit(0);
                }
                setcookie("session_key", $sessionKey, [
                    "expires" => time() + 86400,
                    "path" => "/",
                    "httponly" => true,
                    ]);
                echo json_encode(["ok" => true, "status" => "authorized", "message" => "login sucess"]);
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
                    exit(0);
                }
                echo json_encode(["ok" => true, "status" => "registered", "message" => "registration successful"]);
                break;
            default:
                echo json_encode(["ok" => false, "message" => "unsupported request type, politely FUCK OFF"]);
                exit(0);
        }
    } catch (Exception $e) {
        echo $e->getMessage();
}
?>

