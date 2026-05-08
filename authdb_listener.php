#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// This is from allen's db_stuff branch, adding getUsername

/* 
function for connecting to the database locally
*/
function db() {
  static $connect = null;
  if($connect !== null) {
    return $connect;
  }

  $connect = new mysqli('127.0.0.1', 'db_user', 'passwd123', 'auth_db');  // direct connection to our database
  // kills this script if connection fails
  if($connect->connect_errno) {
    die("Database connections failed: " . $connect->connect_error . PHP_EOL);
  }
  return $connect;
}



/* 
function for account registration
*/
function doRegister($username, $password) {
  $hashedPword = password_hash($password, PASSWORD_DEFAULT);
  // prepping our query
  $stmt = db()->prepare(                                      
    "INSERT INTO users (username, pass_hash, acc_creation_time)
     VALUES (?, ?, UNIX_TIMESTAMP())"
  );
  // checking to see if the prepping went wrong
  if(!$stmt) {
    return [
      "ok" => false,
      "error" => "prep_failed"
      ];
  }
  // attaching actual values to the placeholders
  $stmt->bind_param("ss", $username, $hashedPword);
  // a try catch statement for executing queries and catching what went wrong
  // more specifically, seeing if there's a duplicate user
  try{
    $stmt->execute();
  } catch(mysqli_sql_exception $e) {
      if((int)$e->getCode() === 1062) {
        return [
          "ok" => false,
          "error" => "username_exists"
        ];
      }
      return [
        "ok" => false,
        "error" => "db_insert_failed"
      ];
  }
  return ["ok" => true];
}



/*
function for logging in
*/
function doLogin($username,$password)
{
    // prepping the query and checking if it fails
    $stmt = db()->prepare(
      "SELECT id, pass_hash FROM users WHERE username = ? LIMIT 1"
    );
    if(!$stmt) {
      return ["ok" => false];
    }
    // binding the username and executing the query
    // then getting the result 
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    // checking if the user even exists
    if(!$user) {
      return [
        "ok" => false,
        "error" => "invalid_credentials"
      ];
    }
    // verifying the password that's coming through
    if(!password_verify($password, $user["pass_hash"])) {
      return [
        "ok" => false,
        "error" => "invalid_credentials"
      ];
    }
    // generating a session key
    $sessionKey = bin2hex(random_bytes(32));
    $userId = (int)$user["id"];
    // prepping query that'll insert into sessions table
    $stmt2 = db()->prepare(
      "INSERT INTO sessions (session_key, user_id, start_time, end_time)
       VALUES (?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 86400)"
    );
    // checks if our query failed
    if(!$stmt2) {
      return [
        "ok" => false,
        "error" => "session_insert_failed"
      ];
    }
    // binding session values
    $stmt2->bind_param("si", $sessionKey, $userId);
    // executing the session insert and if it fails then login fails
    if(!$stmt2->execute()) {
      return [
        "ok" => false,
        "error" => "session_insert_failed"
      ];
    }
    // returning a successful login
    return [
      "ok" => true,
      "session_key" => $sessionKey
    ];
}



/*
function for checking if a session key exists and is still active
*/
function doValidate($sessionKey) {
  // asking the db if there's a session with this session key that hasn't expired
  // if there is, let us know which user it belongs to
  $stmt = db()->prepare(
    "SELECT u.id, u.username
     FROM sessions s
     JOIN users u ON s.user_id = u.id
     WHERE s.session_key = ?
     AND s.end_time > UNIX_TIMESTAMP()
     LIMIT 1"
  );
  // checking if our query failed
  if(!$stmt) {
    return ["ok" => false];
  }
  // inserting session key into query then executing said query, and getting the result
  $stmt->bind_param("s", $sessionKey);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  // if a valid session is found, return the user's info
  if($row) {
    return [
      "ok" => true,
      "user_id" => (int)$row["id"],
      "username" => $row["username"]
    ];
  }
  // if no valid sesh was found
  return ["ok" => false];
}



/*
function for logging out
*/
function doLogout($sessionKey) {
  // prepping a query to delete a session from our db
  $stmt = db()->prepare(
    "DELETE FROM sessions WHERE session_key = ?"
  );
  // logout will fail if our db fails to create the query
  if(!$stmt) {
    return ["ok" => false];
  }
  // binding session key and executing query, then returns true when logout is complete
  $stmt->bind_param("s", $sessionKey);
  $stmt->execute();
  return ["ok" => true];
}


/*
  function to get the username associated with a specific user_id from the db
  copying from doValidate
*/
function getUsername($user_id) {
  // get the username associated with user_id from the db
  $stmt = db()->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
  // checking if our query failed
  if(!$stmt) {
    return ["ok" => false];
  }
  // inserting user_id into query then executing said query, and getting the result
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  // if a username was found, return id and username
  if($row) {
    return [
      "ok" => true,
      "user_id" => (int)$row["id"],
      "username" => $row["username"]
    ];
  }
  // if no username was found
  return ["ok" => false];
}

/*
function for processing requests that are comming in from rabbitMQ 
*/
function requestProcessor($request)
{
  // will print debug messages
  echo "received request".PHP_EOL;
  var_dump($request);
  // check if the requests that are coming through has a type
  if(!isset($request['type']))
  {
    return [
      "ok" => false,
      "error" => "missing_type"
    ];
  }
  // makes type not case sensitive
  $type = strtolower($request['type']);

  // switch + case statements to check the value of type and decide what function to run
  switch ($type)
  {
    case "login":
      return doLogin(
        $request['username'] ?? "",
        $request['password'] ?? ""
      );
    case "register":
      return doRegister(
        $request['username'] ?? "",
        $request['password'] ?? ""
      );
    case "validate_session":
      return doValidate(
        $request['session_key'] ?? ""
      );
    case "logout":
      return doLogout(
        $request['session_key'] ?? ""
      );
    case "get_username":
      return getUsername(
        $request['user_id'] ?? ""
      );
    default:
      return [
        "ok" => false,
        "error" => "unsupported_type"
      ];
  }
}


$server = new rabbitMQServer("testRabbitMQ.ini","testServer");
echo "Auth database listener is now running and waiting for requests..." . PHP_EOL;
$server->process_requests('requestProcessor');
exit();
?>

