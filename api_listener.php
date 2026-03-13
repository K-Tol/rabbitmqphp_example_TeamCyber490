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
  try {
    $client = tmdbClient();
    $repository = new MovieRepository($client);
    $movie = $repository -> load($tmdb_id);

    $genre_ids = [];
    foreach ($movie -> getGenres() as $n) {
      $genre_ids[] = $n -> getId();
    }
    
    $result = new rabbitMQClient("movieServer.ini", "movieServer");

    $release_date = $movie -> getReleaseDate();
    if ($release_date !== null) {
      $release_date = $release_date -> format("Y-m-d");
    }

    $result ->send_request([
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
  try {
    $client = tmdbClient();
    $searchRepository = new SearchRepository($client);
    $search_result = $searchRepository -> searchMovie($query, new MovieSearchQuery());

    foreach ($search_result as $n) {
      $tmdb_id = $n -> getId();
      if (($tmdb_id ?? "") === "" || ($tmdb_id ?? "") === 0) {
        continue;
      }
      syncMovie($tmdb_id);
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
    default:
      return ["ok" => false, "error" => "unsupported message type"];
  }
}

$server = new rabbitMQServer("datasource.ini","datasourceServer");
$server->process_requests('requestProcessor');
?>

