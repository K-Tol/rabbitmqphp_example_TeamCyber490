/*
THIS FILE WILL CREATE OUR MOVIE DATABASE
EXECUTE THIS FILE WITH ALL THE OTHER .SQL FILES IN THIS FOLDER TO TEST OUT THE DB
*/

-- obj right now: give us the ability to browse/search movies and view movie profile pages

CREATE DATABASE IF NOT EXISTS movie_db;
USE movie_db;

-- table for storing info about movies (referencing the TMDB api)
CREATE TABLE IF NOT EXISTS movies (
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

-- table for genres
CREATE TABLE IF NOT EXISTS genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tmdb_genre_id INT NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL
);

-- table to connect movies to genres in a many to many format
-- (multiple movies can belong to a single genre and a single movie could be under multiple genres)
CREATE TABLE IF NOT EXISTS movie_genres (
    movie_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (movie_id, genre_id),
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
);

-- should be able to handle users making watchlists
CREATE TABLE IF NOT EXISTS watchlist (
    user_id INT NOT NULL,
    movie_id INT NOT NULL,
    date_added BIGINT NOT NULL,
    PRIMARY KEY (user_id, movie_id),
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
);

-- table for reviews
-- we decided to make ratings 1-10 and comment is optional
-- also, one user can only have one review per movie
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    movie_id INT NOT NULL,
    rating INT NOT NULL,
    comment TEXT,
    created_at BIGINT NOT NULL,
    UNIQUE (user_id, movie_id),           
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
);