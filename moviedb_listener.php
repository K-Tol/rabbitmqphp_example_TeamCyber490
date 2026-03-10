#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

/*
functions to make:
-storing movies function
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
?>