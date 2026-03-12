#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function fetchPopularMovies(): array
{
  $apiKey = 'c4272095443f1ac76fd5bc1f62cd5790';
  $url = "https://api.themoviedb.org/3/movie/popular?api_key={$apiKey}";

  $body = false;

  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $httpCode < 200 || $httpCode >= 300) {
      return array(
        'success' => false,
        'movies' => array(),
        'message' => 'TMDB request failed'
      );
    }
  } else {
    $ctx = stream_context_create(array('http' => array('timeout' => 15)));
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
      return array(
        'success' => false,
        'movies' => array(),
        'message' => 'TMDB request failed'
      );
    }
  }

  $decoded = json_decode($body, true);
  if (!is_array($decoded)) {
    return array(
      'success' => false,
      'movies' => array(),
      'message' => 'Invalid TMDB response'
    );
  }

  return array(
    'success' => true,
    'movies' => isset($decoded['results']) && is_array($decoded['results']) ? $decoded['results'] : array()
  );
}

function doLogin($username,$password)
{
    // lookup username in databas
    // check password
    return true;
    //return false if not valid
}

function requestProcessor($request)
{
  echo "received request".PHP_EOL;
  var_dump($request);
  if(!isset($request['type']))
  {
    return "ERROR: unsupported message type";
  }
  switch ($request['type'])
  {
    case "login":
      return doLogin($request['username'],$request['password']);
    case "get_movies":
      return fetchPopularMovies();
    case "validate_session":
      return doValidate($request['sessionId']);
  }
  return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

echo "testRabbitMQServer BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;
exit();
?>

