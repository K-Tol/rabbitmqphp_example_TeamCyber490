#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('login.php.inc');
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/apikey.php');

use Tmdb\Repository\MovieRepository;

function tmdbClient() {
  $client = require_once(__DIR__ . '/setup-client.php');
  return $client;
}
// from php-tmdb/api/examples/movies/model/get.php
function syncMovie(int $movieID) {
  $client = require_once("setup-client.php");
  $repository = new MovieRepository($client);
  $movie = $repository -> load($movieID);
  
}

function requestProcessor($request)
{
  echo "received request".PHP_EOL;
  var_dump($request);
  if(!isset($request['type']))
  {
    return "ERROR: unsupported message type";
  }
  switch ($request['type'])
  {
    case "sync_movie":
      // return syncMovie() function to get a movie info from tmdb
      return;
    default:
      return "ERROR: unsupported message type";
  }
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

$server->process_requests('requestProcessor');
exit();
?>

