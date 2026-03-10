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

    // this block will do the job of actually extracting movie data from the $movie array
    $tmdb_id = isset($movie["tmdb_id"]) ? (int)$movie["tmdb_id"] : 0;
    $title = isset($movie["title"]) ? (string)$movie["title"] : "";
    $overview = array_key_exists("overview", $movie) ? $movie["overview"] : null;
    $release_date = !empty($movie["release_date"]) ? $movie["release_date"] : null;
    $runtime = isset($movie["runtime"]) && $movie["runtime"] !== null ? (int)$movie["runtime"] : null;
    $poster_path = array_key_exists("poster_path", $movie) ? $movie["poster_path"] : null;
    $backdrop_path = array_key_exists("backdrop_path", $movie) ? $movie["backdrop_path"] : null;
    $original_language = array_key_exists("original_language", $movie) ? $movie["original_language"] : null;
    $vote_average = isset($movie["vote_average"]) ? (float)$movie["vote_average"] : 0.0;
    $vote_count = isset($movie["vote_count"]) ? (int)$movie["vote_count"] : 0;
    $popularity = isset($movie["popularity"]) ? (float)$movie["popularity"] : 0.0;
    $adult = !empty($movie["adult"]) ? 1 : 0;

    // binding the variables to their data types and then executing the insert
    $stmt->bind_param("isssisssdddi", $tmdb_id, $title, $overview, $release_date, $runtime, $poster_path,
    $backdrop_path, $original_language, $vote_average, $vote_count, $popularity, $adult);
    $stmt->execute();
    // prepping query to find movie, binding the variable, then executing said query
    $stmt = movieDB()->prepare(
        "SELECT id FROM movies WHERE tmdb_id = ? LIMIT 1"
    );
    $stmt->bind_param("i", $tmdb_id);
    $stmt->execute();
    // getting back results and then checking if the movie exists
    $row = $stmt->get_result()->fetch_assoc();
    if(!$row) {
        return [
            "success" => false,
            "error" => "store_failed"
        ];
    }
    $movie_id = (int)$row["id"]; // storing movie's db id
    //prepping query to remove old genre links
    $stmt = movieDB()->prepare(
        "DELETE FROM movie_genres WHERE movie_id = ?"
    );
    // bind movie id then execute the delete
    $stmt->bind_param("i", $movie_id);
    $stmt->execute();

    // lopps through genre ids 
    foreach($genre_ids as $tmdb_genre_id) {
        // find genre in db then convert genre id to an int
        $stmt = movieDB()->prepare(
            "SELECT id FROM genres WHERE tmdb_genre_id = ? LIMIT 1"
        );
        $tmdb_genre_id = (int)$tmdb_genre_id;
        $stmt->bind_param("i", $tmdb_genre_id);
        $stmt->execute();
        $genre_row = $stmt->get_result()->fetch_assoc();
        // skip if genre doesnt exist
        if(!$genre_row) {
            continue;
        }
        //store internal genre id
        $genre_id = (int)$genre_row["id"];
        // prepping insert stmt into movie_genres to link movie with a genre
        $stmt = movieDB()->prepare(
            "INSERT IGNORE INTO movie_genres (movie_id, genre_id)
             VALUES (?, ?)"
        );

        $stmt->bind_param("ii", $movie_id, $genre_id);
        $stmt->execute();
    }

    return [
        "success" => true,
        "movie_id" => $movie_id
    ];
}

// function for matching movies to search query
function localMovSearch($query) {
    // query for search movies inside our movies table
    $stmt = movieDB()->prepare(
        "SELECT id, tmdb_id, title, release_date, poster_path
         FROM movies
         WHERE title LIKE CONCAT('%', ?, '%')
         ORDER BY popularity DESC, title ASC
         LIMIT 25"
    );
    // bind query to ? then execute it and get results back
    $stmt->bind_param("s", $query);
    $stmt->execute();
    $res = $stmt->get_result();
    $movies = [];
    // looping through every movie returned from the db
    while($row = $res->fetch_assoc()) {
        $movies[] = $row;
    }
    return $movies;
}

?>