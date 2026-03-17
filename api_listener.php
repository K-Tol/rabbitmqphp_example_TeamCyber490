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
use Tmdb\Exception\TmdbApiException;
use Tmdb\Model\Search\SearchQuery\MovieSearchQuery;
use Tmdb\Repository\SearchRepository;

function tmdbClient() {
  static $client = null;
  if ($client !== null) {
    return $client;
  }
  $client = require_once(__DIR__ . '/setup-client.php');
  return $client;
}

// base from php-tmdb/api/examples/movies/model/get.php
function syncMovie(int $tmdb_id) {
  error_log("syncMovie started for tmdb_id: $tmdb_id");
  try {
    $client = tmdbClient();
    $repository = new MovieRepository($client);

    // this fixes some visual errors with the php-tmdb wrapper
    /** @var \Tmdb\Model\Movie $movie */
    $movie = $repository -> load($tmdb_id);

    $genre_ids = [];
    foreach ($movie -> getGenres() as $n) {
      $genre_ids[] = $n -> getId();
    }
    
    $result = new rabbitMQClient("movieServer.ini", "movieServer");

    $release_date = $movie -> getReleaseDate();
    if ($release_date !== null && $release_date instanceof DateTime) {
      $release_date = $release_date -> format("Y-m-d");
    }

    error_log("syncMovie sending store_movie for tmdb_id: $tmdb_id");
    $result ->publish([
      "type" => "store_movie",
      "genre_ids" => $genre_ids,
      "movie" => [
        "tmdb_id" => $movie -> getId(),
        "title" => $movie -> getTitle(),
        "overview" => $movie -> getOverview(),
        "release_date" => $release_date,
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
    error_log("syncMovie store_movie response received for tmdb_id: $tmdb_id");
    return ["ok" => true];
  }
  catch (TmdbApiException $e) {
    if (TmdbApiException::STATUS_RESOURCE_NOT_FOUND == $e->getCode()) {
        // not found
        echo $e->getMessage();
        return ["ok" => false, "error" => "not_found"];
    }
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

    $rClient = new rabbitMQClient("movieServer.ini", "movieServer");

    foreach ($genres as $n) {
      $rClient -> send_request([
        "type" => "store_genre",
        "tmdb_genre_id" => $n -> getId(),
        "name" => $n -> getName()
      ]);
    }
    return ["ok" => true];
  }
  catch (TmdbApiException $e) {
    if (TmdbApiException::STATUS_RESOURCE_NOT_FOUND == $e->getCode()) {
        // not found
        echo $e->getMessage();
        return ["ok" => false, "error" => "not_found"];
    }
  }
  catch (Exception $e) {
    echo $e->getMessage();
  }
}

function syncSearch($query) {
  error_log("syncSearch started for query: $query");
  try {
    $client = tmdbClient();
    $searchRepository = new SearchRepository($client);
    $search_result = $searchRepository -> searchMovie($query, new MovieSearchQuery());
    error_log("syncSearch got results from TMDB");

    foreach ($search_result as $n) {
      $tmdb_id = $n -> getId();
      if (($tmdb_id ?? "") === "" || ($tmdb_id ?? "") === 0) {
        continue;
      }
      error_log("syncSearch syncing tmdb_id: $tmdb_id");
      syncMovie($tmdb_id);
      error_log("syncSearch finished syncing tmdb_id: $tmdb_id");
    }
    error_log("syncSearch complete");
    return ["ok" => true];
  }
  catch (TmdbApiException $e) {
    if (TmdbApiException::STATUS_RESOURCE_NOT_FOUND == $e->getCode()) {
        // not found
        echo $e->getMessage();
        return ["ok" => false, "error" => "not_found"];
    }
  }
  catch (Exception $e) {
    echo $e->getMessage();
  }
}

function syncPopular() {
// copied this layout from syncMovie
error_log("syncPopular started");
  try {
    $client = tmdbClient();
    $repository = new MovieRepository($client);

    $popular = $repository -> getPopular();
    error_log("Got popular from tmdb, how many movies?: " . count($popular));

    foreach ($popular as $x) {
      $tmdb_id = $x -> getId();
      if ($tmdb_id === NULL || $tmdb_id === "") {
        continue;
      }
      error_log("syncing movie with tmdb_id: $tmdb_id" );
      syncMovie($tmdb_id);
      error_log("finished syncing movie with tmdb_id: $tmdb_id" );
    }
    
    error_log("syncPopular() finished");
    return ["ok" => true];
  }
  catch (TmdbApiException $e) {
    if (TmdbApiException::STATUS_RESOURCE_NOT_FOUND == $e->getCode()) {
        // not found
        echo $e->getMessage();
        return ["ok" => false, "error" => "not_found"];
    }
  }
  catch (Exception $e) {
    echo $e->getMessage();
  }
}

function syncNowPlaying() {

}

function requestProcessor($request) {
  echo "received request".PHP_EOL;
  var_dump($request);

  if(!isset($request['type'])) {
    return ["ok" => false, "error" => "unsupported message type"];
  }

  switch ($request['type']) {
    case "sync_movie":
      return syncMovie((int)($request['tmdb_id'] ?? 0));
    case "sync_genres":
      return syncGenres();
    case "sync_search":
      return syncSearch($request['query'] ?? "");
    // Cron requests from moviedb_listener
    case "sync_popular":
      return;
    case "sync_now_playing":
      return;
    default:
      return ["ok" => false, "error" => "unsupported message type"];
  }
}


error_log("Syncing genres on startup");
$result = syncGenres();
print_r($result);

$server = new rabbitMQServer("datasource.ini","datasourceServer");
error_log("API listener is now running");
$server->process_requests('requestProcessor');
?>

