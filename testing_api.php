#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once(__DIR__ . '/apikey.php');

use Tmdb\Repository\MovieRepository;
use Tmdb\Repository\GenreRepository;

$client = require_once(__DIR__ . '/setup-client.php'); 
$repository = new MovieRepository($client);
$genreRepository = new GenreRepository($client);
$topRated = $repository->getTopRated(['page' => 3]);
// or
$popular = $repository->getPopular();

$genres = $genreRepository->loadMovieCollection();

var_dump($genres);