#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

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
    distribute_log("Database connections failed: " . $connect->connect_error);
    die("Database connections failed: " . $connect->connect_error . PHP_EOL);
  }
  return $connect;
}



/* 
function for account registrationx
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
    distribute_log("prepare_query_failed");
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
        distribute_log("username_exists");
        return [
          "ok" => false,
          "error" => "username_exists"
        ];
      }
      distribute_log("db_insert_failed");
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
      distribute_log("prepare_query_failed");
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
      distribute_log("invalid_credentials");
      return [
        "ok" => false,
        "error" => "invalid_credentials"
      ];
    }
    // verifying the password that's coming through
    if(!password_verify($password, $user["pass_hash"])) {
      distribute_log("invalid_credentials");
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
      distribute_log("session_insert_failed");
      return [
        "ok" => false,
        "error" => "session_insert_failed"
      ];
    }
    // binding session values
    $stmt2->bind_param("si", $sessionKey, $userId);
    // executing the session insert and if it fails then login fails
    if(!$stmt2->execute()) {
      distribute_log("session_insert_failed");
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
    distribute_log("prepare_query_failed");
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
  distribute_log("no valid sesh was found");
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
    distribute_log("prepare_query_failed");
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
function getUsername(int $user_id) {
  try {
    // get the username associated with user_id from the db
    $stmt = db()->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
    // checking if our query failed
    if(!$stmt) {
      distribute_log("prepare_query_failed");
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
  catch (Throwable $e) {
    return ["ok" => false, "error" => "something_failed"];
  }
}

/*
function to the user_id associated with a specific username from the db
copying from getUsername
*/
function getID(string $username) {
  try {
    // get the user_id associated with username from the db
    $stmt = db()->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    // checking if our query failed
    if(!$stmt) {
      distribute_log("prepare_query_failed");
      return ["ok" => false, "error" => "prepare_query_failed"];
    }
    // inserting username into query, executing query, and getting the result
    $stmt -> bind_param("s", $username);
    $stmt -> execute();
    $result = $stmt -> get_result();
    $row = $result -> fetch_assoc();
    // if a user_id was found, return id
    if($row) {
      return [
        "ok" => true,
        "user_id" => (int)$row["id"]
      ];
    }
    // if no user_id was found
    distribute_log("no user_id was found");
    return ["ok" => false, "error" => "no user_id was found"];
      }
  catch (Throwable $e) {
    return ["ok" => false, "error" => "something_failed"];
  }
}

/*
function to follow a user for follow lists
*/
function followUser(string $session_key, int $user_id, int $target_user_id) {
  try {
    // validate session or fail
    $validation = doValidate($session_key);
    if ($validation["ok"] == false) {
      distribute_log("invalid_session");
      return [
        "ok" => false,
        "error" => "invalid_session"
      ];
    }
    // query to start check to see if the targeted id exists
    $stmt = db() -> prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    // checking if our query prepare failed
    if(!$stmt) {
      distribute_log("prepare_query_failed");
      return ["ok" => false, "error" => "prepare_query_failed"];
    }
    // inserting target_user_id into query, then executing said query, and check if it exists
    $stmt -> bind_param("i", $target_user_id);
    $stmt -> execute();
    if ($stmt -> get_result() -> num_rows === 0) {
      distribute_log("user_not_found");
      return ["ok" => false, "error" => "user_not_found"];
    }
    // if they exist, insert both variables for new entry, execute query
    $stmt2 = db() -> prepare(
      "INSERT INTO follow_list (follower_id, following_id, time_followed)
      VALUES (?, ?, UNIX_TIMESTAMP())"
    );
    if (!$stmt2) {
      distribute_log("prepare_query_failed");
      return ["ok" => false, "error" => "prepare_query_failed"];
    }
    $stmt2 -> bind_param("ii", $user_id, $target_user_id);
    $stmt2 -> execute();
  }
  catch (Throwable $e) {
    return ["ok" => false, "error" => "something_failed"];
  }
  return ["ok" => true];
}

/*
function for unfollowing a user
*/
function unfollowUser(string $session_key, int $user_id, int $target_user_id) {
  try {
    // validate session or fail
    $validation = doValidate($session_key);
    if ($validation["ok"] == false) {
      distribute_log("invalid_session");
      return [
        "ok" => false,
        "error" => "invalid_session"
      ];
    }
    // query will delete the row where user_id matches follower_id
    $stmt = db() -> prepare(
      "DELETE FROM follow_list WHERE follower_id = ? AND following_id = ?"
    );
    // checking if our query prepare failed
    if(!$stmt) {
      distribute_log("prepare_query_failed");
      return ["ok" => false, "error" => "prepare_query_failed"];
    }
    // inserting the two variables from the backend php into the query and executing
    $stmt -> bind_param("ii", $user_id, $target_user_id);
    $stmt -> execute();
    return ["ok" => true];
  }
  catch (Throwable $e) {
    return ["ok" => false, "error" => "something_failed"];
  }
}

/*
function for getting the users that are following the current user
took boilplate followUser and getFollowing
*/
function followingUserCheck(string $session_key, int $user_id, int $target_user_id) {
  try {
    // validate session or fail
    $validation = doValidate($session_key);
    if ($validation["ok"] == false) {
      distribute_log("invalid_session");
      return [
        "ok" => false,
        "error" => "invalid_session"
      ];
    }
    // query will check if there is following happening between follower_id
    // and following_id
    $stmt = db() -> prepare(
      "SELECT 1 FROM follow_list WHERE follower_id = ? AND following_id = ? LIMIT 1"
    );
    // checking if our query prepare failed
    if(!$stmt) {
      distribute_log("prepare_query_failed");
      return ["ok" => false, "error" => "prepare_query_failed"];
    }
    // inserting the two variables from the backend php into the query and executing
    $stmt -> bind_param("ii", $user_id, $target_user_id);
    $stmt -> execute();
    $result = $stmt -> get_result();
    // if there was more than 0 rows from that query they were following
    // and send true, if not send false
    if ($result -> num_rows > 0) {
      return [
        "ok" => true,
        "is_following" => true
      ];
    }
    return [
      "ok" => true,
      "is_following" => false
    ];
  }
  catch (Throwable $e) {
    return ["ok" => false, "error" => "something_failed"];
  }
}

/*
function for getting an array of users following a user_id
*/
function getFollowing(string $session_key, int $user_id) {
  try {
    // validate session or fail
    $validation = doValidate($session_key);
    if ($validation["ok"] == false) {
      distribute_log("invalid_session");
      return [
        "ok" => false,
        "error" => "invalid_session"
      ];
    }
    // query will get associated id and username from users to follow_list
    // from a specific follower_id, and sort by most recent
    $stmt = db() -> prepare(
      "SELECT users.id, users.username
      FROM follow_list
      JOIN users ON follow_list.following_id = users.id
      WHERE follow_list.follower_id = ?
      ORDER BY follow_list.time_followed DESC"
    );
    // checking if our query prepare failed
    if(!$stmt) {
      distribute_log("prepare_query_failed");
      return ["ok" => false, "error" => "prepare_query_failed"];
    }
    // insert user_id into query and execute into a var
    $stmt -> bind_param("i", $user_id);
    $stmt -> execute();
    $queryResult = $stmt -> get_result();
    // get all rows into a var to send to webserver backend php
    $result = $queryResult -> fetch_all(MYSQLI_ASSOC);
    return ["ok" => true, "users" => $result];
  }
  catch (Throwable $e) {
    return ["ok" => false, "error" => "something_failed"];
  }
}

/*
function for getting an array of followers of a user_id
*/
function getFollowers(string $session_key, int $user_id) {
  try {
    // validate session or fail
      $validation = doValidate($session_key);
      if ($validation["ok"] == false) {
        distribute_log("invalid_session");
        return [
          "ok" => false,
          "error" => "invalid_session"
        ];
      }
    // copied from getFollowing
    // query will get associated id and username from users to follow_list
    // from a specific following_id, and sort by most recent
    $stmt = db() -> prepare(
      "SELECT users.id, users.username
      FROM follow_list
      JOIN users ON follow_list.follower_id = users.id
      WHERE follow_list.following_id = ?
      ORDER BY follow_list.time_followed DESC"
    );
    // checking if our query prepare failed
    if(!$stmt) {
      distribute_log("prepare_query_failed");
      return ["ok" => false, "error" => "prepare_query_failed"];
    }
    // insert user_id into query and execute into a var
    $stmt -> bind_param("i", $user_id);
    $stmt -> execute();
    $queryResult = $stmt -> get_result();
    // get all rows into a var to send to webserver backend php
    $result = $queryResult -> fetch_all(MYSQLI_ASSOC);
    return ["ok" => true, "users" => $result];
  }
  catch (Throwable $e) {
    return ["ok" => false, "error" => "something_failed"];
  }
}


/*
function to send logs to other vms, from api_listener
*/
function distribute_log(string $log) {
  try {
    $ClusterVmName = gethostname();
    // change to the correct queue on other environments
    $rabbitClient = new rabbitMQClient("logging.ini", "qa_db_log");
    $rabbitClient -> publish([
      "type" => "cluster_log",
      "source" => $ClusterVmName,
      "message" => $log,
      "timestamp" => time()
    ]);
  }
  catch (Throwable $e) {
    return ["ok" => false, "error" => "something_failed"];
  }
}

/*
function for processing requests that are comming in from rabbitMQ 
*/
function requestProcessor($request)
{
  // will print debug messages
  echo "received request".PHP_EOL;
  distribute_log("received request");
  var_dump($request);
  // check if the requests that are coming through has a type
  if(!isset($request['type']))
  {
    distribute_log("missing_type");
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
        $request['user_id'] ?? 0
      );
    case "get_id":
      return getID(
        $request['username'] ?? ""
      );
    case "follow_user":
      return followUser(
        $request['session_key'] ?? "",
        $request['user_id'] ?? 0,
        $request['target_user_id'] ?? 0
      );
    case "unfollow_user":
      return unfollowUser(
        $request['session_key'] ?? "",
        $request['user_id'] ?? 0,
        $request['target_user_id'] ?? 0
      );
    case "is_following_user":
      return followingUserCheck(
        $request['session_key'] ?? "",
        $request['user_id'] ?? 0,
        $request['target_user_id'] ?? 0
      );
    case "get_following":
      return getFollowing(
      $request['session_key'] ?? "",
      $request['user_id'] ?? 0
      );
    case "get_followers":
      return getFollowers(
        $request['session_key'] ?? "",
        $request['user_id'] ?? 0
      );
    default:
      distribute_log("unsupported_type");
      return [
        "ok" => false,
        "error" => "unsupported_type"
      ];
  }
}


$server = new rabbitMQServer("testRabbitMQ.ini","testServer");
echo "Auth database listener is now running and waiting for requests..." . PHP_EOL;
distribute_log("Auth database listener is now running and waiting for requests...");
$server->process_requests('requestProcessor');
exit();
?>

