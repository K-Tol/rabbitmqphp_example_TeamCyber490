<?php
declare(strict_types=1);

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$sessionKey = $_COOKIE['session_key'] ?? '';
if ($sessionKey === '' || strlen($sessionKey) !== 64) {
  header("Location: /index.html");
  exit;
}

$authClient = new rabbitMQClient("testRabbitMQ.ini","testServer");
$resp = $authClient->send_request([
    "type"=>"validate_session","session_key"=>$sessionKey]);

if (!is_array($resp) || empty($resp["ok"])) {
  header("Location: /index.html");
  exit;
}

$username = $resp["username"] ?? "empty username";
$user_id = $resp["user_id"] ?? "empty user_id";

$movieClient = new rabbitMQClient("movieServer.ini","movieServer");
$watchListResponse = $movieClient->send_request([
    "type"=>"get_watchlist", "user_id"=>$user_id]);

$watchList = $watchListResponse["movies"] ?? [];
$hello = "hello";
?>

<!doctype html>
<html>
<head>
    <link rel="stylesheet" href="movieInfo.css">
    <meta charset="utf-8"><title>Watch List</title>
</head>
<body>
  <h1>Welcome, <?php echo htmlspecialchars($username); ?>, here's your watchlist:</h1>
    
  <div id="div_watchlist"></div>

  <script>
    // this is from movieInfo.html
    var movieArray = <?php echo json_encode($watchList); ?>;
    div_watchlist = document.getElementById("div_watchlist");

    for (let i = 0; i < movieArray.length; i++) {
        movieOptions = 
        `
        <div class="movie">
        <h3>${movieArray[i].title}</h3>
        <img src="https://image.tmdb.org/t/p/w200${movieArray[i].poster_path}" alt="${movieArray[i].title}">
        <p>${movieArray[i].release_date}</p>
        </div>`;
        div_watchlist.innerHTML += movieOptions;
    }

  </script>

  <p><a href="/logout.php">Logout</a></p>
</body>
</html>