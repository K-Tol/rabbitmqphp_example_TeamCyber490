#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

/*
functions to make:
-query for movie function
-sum more
*/

// function for establishing connection with the movie database
function movieDB() {
    static $connect = null;
    if($connect !== null) {
        return $connect;
    }

    $connect = new mysqli('127.0.0.1', 'db_user', 'passwd123', 'movie_db');
    if($connect->connect_errno) {
        die("movie_db connection failed: " . $connect->connect_error . PHP_EOL);
    }
    return $connect;
}

// rabbitMQ client for requesting TMDB syncs from the api handler script
function datasourceClient() {
    static $client = null;
    if($client !== null) {
        return $client;
    }
    // might not be called apiServer depending on what Ralph decides to name the queues
    $client = new rabbitMQClient("rabbitMQ.ini", "apiServer");
    return $client;
}

// will store genres from TMDB api into our movie database
function storeGenre($tmdb_genre_id, $name) {
    $stmt = movieDB()->prepare(
        "INSERT INTO genres (tmdb_genre_id, name)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name)" // prevents duplicate entries
    );
    $stmt->bind_param("is", $tmdb_genre_id, $name);
    $stmt->execute();
    return ["success" => true];
}

// work on a function for storing movies next
function storeMovie($movie, $genre_ids = []) {
    // this entire block will handle inserting a movie into the db
    $stmt = $movieDB()->prepare(
        "INSERT INTO movies
         (tmdb_id, title, overview, release_date, runtime, poster_path,
         backdrop_path, original_language, vote_average, vote_count, popularity,
         adult, last_synced_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
         title = VALUES(title),
         overview = VALUES(overview),
         release_date = VALUES(release_date),
         runtime = VALUES(runtime),
         poster_path = VALUES(poster_path),
         backdrop_path = VALUES(backdrop_path),
         original_language = VALUES(original_language),
         vote_average = VALUES(vote_average),
         vote_count = VALUES(vote_count),
         popularity = VALUES(popularity),
         adult = VALUES(adult),
         last_synced_at = UNIX_TIMESTAMP()" // making it epoch time
    );
}
?>