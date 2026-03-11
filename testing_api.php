#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Tmdb\Repository\MovieRepository;
use Tmdb\Client;

$client = new Client([
  "api_token" => "eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJjNDI3MjA5NTQ0M2YxYWM3NmZkNWJjMWY2MmNkNTc5MCIsIm5iZiI6MTc3MjczMTQwNS45Njk5OTk4LCJzdWIiOiI2OWE5YmMwZGZjYmFmNzJiMmRmMWIxY2QiLCJzY29wZXMiOlsiYXBpX3JlYWQiXSwidmVyc2lvbiI6MX0.qfwBoqM26jO5K8A-6k3wIz1EycQKgIS7DM3GHqL3nJg"
  ]);
$repository = new MovieRepository($client);
$topRated = $repository->getTopRated(['page' => 3]);
// or
$popular = $repository->getPopular();