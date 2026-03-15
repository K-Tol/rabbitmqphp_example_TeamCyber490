<?php

declare(strict_types=1);

return [
    'host' => getenv('MOVIE_DB_HOST') ?: (getenv('DB_HOST') ?: '127.0.0.1'),
    'port' => (int) (getenv('MOVIE_DB_PORT') ?: (getenv('DB_PORT') ?: '3306')),
    'name' => getenv('MOVIE_DB_NAME') ?: 'movie_db',
    'user' => getenv('MOVIE_DB_USER') ?: 'db_user',
    'pass' => getenv('MOVIE_DB_PASS') ?: 'passwd123',
    'charset' => getenv('MOVIE_DB_CHARSET') ?: 'utf8mb4',
];
