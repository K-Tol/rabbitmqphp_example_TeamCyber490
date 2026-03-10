#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

/*
functions to make:
-datasource client function
-storing genres function
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
?>