#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// function for connecting to the database locally
function db() {
  static $connect = null;
  if($connect !== null) {
    return $connect;
  }

  $connect = new mysqli('127.0.0.1', 'db_user', 'passwd123', 'auth_db');  // direct connection to our database

  if($connect->connect_errno) {
    die("Database connections failed: " . $connect->connect_errno . PHP_EOL);
  }
  return $connect;
}

// make a function to register
// make a function to validate a session
// make a function to logout

// work on login after register function
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
    case "validate_session":
      return doValidate($request['sessionId']);
  }
  return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

$server->process_requests('requestProcessor');
exit();
?>

