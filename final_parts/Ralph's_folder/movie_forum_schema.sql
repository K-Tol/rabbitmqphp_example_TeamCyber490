-- movie_forum_schema.sql
-- Database schema for the beginner-friendly forum backend.

CREATE DATABASE IF NOT EXISTS movie_forum
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE movie_forum;

CREATE TABLE IF NOT EXISTS forum_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_title VARCHAR(255) NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS forum_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    comment_name VARCHAR(100) NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_forum_comments_post
        FOREIGN KEY (post_id)
        REFERENCES forum_posts(id)
        ON DELETE CASCADE
);
