/*
THIS FILE WILL CREATE OUR MOVIE DATABASE
EXECUTE THIS FILE WITH ALL THE OTHER .SQL FILES IN THIS FOLDER TO TEST OUT THE DB
*/

-- obj right now: give us the ability to browse/search movies and view movie profile pages

CREATE DATABASE movie_db;
USE movie_db;

-- table for storing info about movies (referencing the TMDB api)
CREATE TABLE movies(
    id INT AUTO_INCREMENT PRIMARY KEY,
    tmdb_id INT NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    overview TEXT,
    release_date DATE,
    runtime INT,
    poster_path VARCHAR(255),
    backdrop_path VARCHAR(255),
    original_language VARCHAR(10),
    vote_average FLOAT,
    vote_count INT,
    popularity FLOAT,
    adult BOOLEAN,
    last_synced_at BIGINT
);