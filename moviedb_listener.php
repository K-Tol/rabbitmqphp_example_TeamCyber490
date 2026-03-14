#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');



/*
function for establishing connection with the movie database
*/
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



/*
rabbitMQ client for requesting TMDB syncs from the api handler script
*/
function datasourceClient() {
    static $client = null;
    if($client !== null) {
        return $client;
    }
    // updated queue name and file name
    $client = new rabbitMQClient("datasource.ini", "datasourceServer");
    return $client;
}



/*
will store genres from TMDB api into our movie database
*/
function storeGenre($tmdb_genre_id, $name) {
    $stmt = movieDB()->prepare(
        "INSERT INTO genres (tmdb_genre_id, name)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name)" // prevents duplicate entries
    );
    $stmt->bind_param("is", $tmdb_genre_id, $name);
    $stmt->execute();
    return ["ok" => true];
}



/*
work on a function for storing movies next
*/
function storeMovie($movie, $genre_ids = []) {
    // this entire block will handle inserting a movie into the db
    $stmt = movieDB()->prepare(
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
            "ok" => false,
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
        "ok" => true,
        "movie_id" => $movie_id
    ];
}



/*
function for matching movies to search query
*/
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



/*
function to wrap localMovSearch, falls back to triggering
a TMDB sync if nothing is found locally
*/
function searchMovies($query) {
    $movies = localMovSearch($query);
    // if no movies were found, sned a rabbitMQ request to api handler on the dmz 
    if(count($movies) === 0) {
        datasourceClient()->send_request(["type" => "sync_search", "query" => $query]);
        $movies = localMovSearch($query);
    }
    return [
        "ok" => true,
        "movies" => $movies
    ];
}



/*
function to get all the details about a movie
*/
function getFullDetails($movie_id) {
    $stmt = movieDB()->prepare(
        "SELECT * FROM  movies WHERE id = ?"
    );
    $stmt->bind_param("i", $movie_id);
    $stmt->execute();
    $movie = $stmt->get_result()->fetch_assoc();
    // checks if movie even exists in the db
    if(!$movie) {
        return [
            "ok" => false,
            "error" => "movie_not_found"
        ];
    }
    // query to retrieve genres that the movie falls under
    $stmt = movieDB()->prepare(
        "SELECT g.id, g.tmdb_genre_id, g.name
         FROM genres g
         JOIN movie_genres mg ON mg.genre_id = g.id
         WHERE mg.movie_id = ?
         ORDER BY g.name ASC"
    );
    $stmt->bind_param("i", $movie_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $genres = [];
    // loop to go through genre results
    while($row = $res->fetch_assoc()) {
        $genres[] = $row;
    }
    // return everything
    return [
        "ok" => true,
        "movie" => $movie,
        "genres" => $genres
    ];
}

// File up till this point only contains functions for supporting this feature:
// Ability to browse and search for movies and see a profile page for them
// OBJ RN: HANDLE WATCH LISTS FEATURE

// NEED FUNCTIONS FOR ADDING AND REMOVING DATA TO WATCH LIST
// ALSO NEED A GET FUNCTION TO RETRIVE THE WATCHLIST IF REQUESTED

function addToWatch($user_id, $movie_id) {
  $stmt = movieDB()->prepare(
    "INSERT IGNORE INTO watchlist_movies (user_id, movie_id, date_added)
     VALUES (?, ?, UNIX_TIMESTAMP())"
  );
  $stmt->bind_param("ii", $user_id, $movie_id);
  $stmt->execute();
  return ["ok" => true];
}

function removeFromWatch($user_id, $movie_id) {
    $stmt = movieDB()->prepare(
        "DELETE FROM watchlist_movies
         WHERE user_id = ? AND movie_id = ?"
    );
    $stmt->bind_param("ii" $user_id, $movie_id);
    $stmt->execute();
    return ["ok" = true];
}

/*
function for routing the requests that are coming through with their needed functions above
*/
function requestProcessor($request) {
    echo "received request".PHP_EOL;
    var_dump($request);
    if(!isset($request['type'])) {
        return [
            "ok" => false,
            "error" => "missing_type"
        ];
    }
    // swtich case stmts to direct the type of request that's coming through
    switch ($request["type"]) {
        case "search_movies":
            return searchMovies($request["query"] ?? "");
        case "get_movie_details":
            return getFullDetails((int)($request["movie_id"] ?? 0));
        case "store_genre":
            return storeGenre((int)($request["tmdb_genre_id"] ?? 0), $request["name"] ?? "");
        case "store_movie":
            return storeMovie($request["movie"] ?? [], $request["genre_ids"] ?? []);
        // STILL NEED TO ADD CASE STMTS FOR THE WATCHLIST FUNCTIONS
        case "add_to_watch":
            return addToWatch((int)$request["user_id"] ?? 0, (int)($request["movie_id"] ?? 0));
        default:
            return [
                "ok" => false,
                "error" => "unknown_request"
            ];
    }
}

$server = new rabbitMQServer("movieServer.ini", "movieServer");
echo "Movie database listener is now running..." . PHP_EOL;
$server->process_requests('requestProcessor');
?>