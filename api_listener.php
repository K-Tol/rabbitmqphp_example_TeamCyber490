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
function syncMovie(int $tmdb_id) {
  try {
    $client = tmdbClient();
    $repository = new MovieRepository($client);
    $movie = $repository -> load($tmdb_id);

    $genre_ids = [];
    foreach ($movie -> getGenres() as $n) {
      $genre_ids[] = $n -> getId();
    }
    
    $result = new rabbitMQClient("movieServer.ini", "movieServer");
    $result ->send_request([
      "type" => "store_movie",
      "genre_ids" => $genre_ids,
      "movie" => [
        "tmdb_id" => $movie -> getId(),
        "title" => $movie -> getTitle(),
        "overview" => $movie -> getOverview(),
        "release_date" => $movie -> getReleaseDate(),
        "runtime" => $movie -> getRuntime(),
        "poster_path" => $movie -> getPosterPath(),
        "backdrop_path" => $movie -> getBackdropPath(),
        "original_language" => $movie -> getOriginalLanguage(),
        "vote_average" => $movie -> getVoteAverage(),
        "vote_count" => $movie -> getVoteCount(),
        "popularity" => $movie -> getPopularity(),
        "adult" => $movie -> getAdult()
      ]
    ]);
  }
  catch (Exception $e) {
    echo $e->getMessage();
  }
}

function syncGenres() {
  try {
    $client = tmdbClient();
    $genreRepository = new GenreRepository($client);
    $genres = $genreRepository -> loadMovieCollection();

    $genres_result = [];
    foreach ($genres as $n) {
      $genres_result[] = [
        "tmdb_genre_id" => $n -> getId(),
        "name" => $n -> getName()
      ];
    }

    $result = new rabbitMQClient("movieServer.ini", "movieServer");
    return $result -> send_request([
      "type" => "store_genre",
      "genres" => $genres_result
    ]);
  }
  catch (Exception $e) {
    echo $e->getMessage();
  }
}

function requestProcessor($request) {
  echo "received request".PHP_EOL;
  var_dump($request);

  if(!isset($request['type'])) {
    return ["ok" => false, "error" => "unsupported message type"];
  }
  switch ($request['type']) {
    case "sync_movie":
      // return syncMovie() function to get a movie info from tmdb
      return;
    case "sync_genres":
      // return syncGenres() function to get 
    default:
      return ["ok" => false, "error" => "unsupported message type"];
  }
}

$server = new rabbitMQServer("datasource.ini","datasourceServer");
$server->process_requests('requestProcessor');
exit();
?>

