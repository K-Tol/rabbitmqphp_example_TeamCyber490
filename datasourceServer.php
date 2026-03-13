#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('login.php.inc');
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/apikey.php');

use Tmdb\Repository\MovieRepository;
use Tmdb\Repository\GenreRepository;

function tmdbClient() {
  $client = null;
  if ($client !== null) {
    return $client;
  }
  $client = require_once(__DIR__ . '/setup-client.php');
  return $client;
}

// base from php-tmdb/api/examples/movies/model/get.php
function syncMovie(int $movieID) {
  try {
  $client = tmdbClient();
  $repository = new MovieRepository($client);
  $movie = $repository -> load($movieID);
  $genreRepository = new GenreRepository($client);
  $genres = $genreRepository -> load($movieID);
  
  $result = new rabbitMQClient("movieServer.ini", "movieServer");
  $result ->send_request([
    "type" => "store_movie",
    $genres,
    "movie" => [
      "tmdb_id" => $movie -> getId(),
      "title" => $movie-> getTitle()
    ]
  ]);
  }
  catch (Exception $e) {
    echo $e->getMessage();
  }
}

function requestProcessor($request) {
  echo "received request".PHP_EOL;
  var_dump($request);
  if(!isset($request['type']))
  {
    return ["ok" => false, "error" => "unsupported message type"];
  }
  switch ($request['type'])
  {
    case "sync_movie":
      // return syncMovie() function to get a movie info from tmdb
      return;
    default:
      return ["ok" => false, "error" => "unsupported message type"];
  }
}

$server = new rabbitMQServer("datasource.ini","datasourceServer");

$server->process_requests('requestProcessor');
exit();
?>

