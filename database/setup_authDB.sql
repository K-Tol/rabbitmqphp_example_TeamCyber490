/*
THIS FILE WILL MAKE OUR AUTHENTICATION DATABASE
IF YOU WANT TO RUN THE AUTH DB ON YOUR VM FOR TESTING, MAKE SURE TO EXECUTE THIS FILE ALONG WITH "user.sql"
*/

-- making the db
CREATE DATABASE IF NOT EXISTS auth_db;
USE auth_db;

-- making a table to store our users' information
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    pass_hash VARCHAR(255) NOT NULL,
    acc_creation_time BIGINT NOT NULL -- epoch time
);

-- making a table to keep track of all the sessions
CREATE TABLE IF NOT EXISTS sessions (
    session_key CHAR(64) PRIMARY KEY,
    user_id INT NOT NULL,
    start_time BIGINT NOT NULL, -- epoch time
    end_time BIGINT NOT NULL, -- epoch time
    -- linking this table with the users table so if a user is deleted from that table, their sessiosn will be delted too
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (user_id),
    INDEX (end_time)
);