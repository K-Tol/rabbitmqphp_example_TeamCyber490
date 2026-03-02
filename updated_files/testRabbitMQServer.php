#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

/**
 * DB is local on the DB VM. No need to bind MySQL to tailscale for this architecture.
 * MySQL can stay on 127.0.0.1 and still work.
 */
function db() {
    static $db = null;
    if ($db !== null) return $db;

    $db = new mysqli('127.0.0.1', 'db_user', 'passwd123', 'auth_db');
    if ($db->connect_errno) {
        die("DB connection failed: " . $db->connect_error . PHP_EOL);
    }
    return $db;
}

function doValidate(string $key): array {
    $stmt = db()->prepare(
        "SELECT u.id AS user_id, u.username
         FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.session_key=? AND s.end_time > UNIX_TIMESTAMP()
         LIMIT 1"
    );
    if (!$stmt) return ["ok"=>false];

    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();

    if ($row) return ["ok"=>true, "user_id"=>(int)$row["user_id"], "username"=>$row["username"]];
    return ["ok"=>false];
}

function doRegister(string $username, string $password): array {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = db()->prepare(
        "INSERT INTO users (username, pass_hash, acc_creation_time)
         VALUES (?, ?, UNIX_TIMESTAMP())"
    );
    if (!$stmt) return ["ok"=>false];

    $stmt->bind_param("ss", $username, $hash);

    if (!$stmt->execute()) {
        // Duplicate username = MySQL error 1062 (unique constraint)
        if (db()->errno === 1062) return ["ok"=>false, "error"=>"username_exists"];
        return ["ok"=>false, "error"=>"db_insert_failed"];
    }

    return ["ok"=>true];
}

function doLogin(string $username, string $password): array {
    $stmt = db()->prepare("SELECT id, pass_hash FROM users WHERE username=? LIMIT 1");
    if (!$stmt) return ["ok"=>false];

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if (!$user || !password_verify($password, $user["pass_hash"])) {
        return ["ok"=>false, "error"=>"invalid_credentials"];
    }

    $session_key = bin2hex(random_bytes(32)); // 64 chars
    $uid = (int)$user["id"];

    // 1 day TTL (change if you want)
    $stmt2 = db()->prepare(
        "INSERT INTO sessions(session_key, user_id, start_time, end_time)
         VALUES(?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 86400)"
    );
    if (!$stmt2) return ["ok"=>false, "error"=>"session_insert_failed"];

    $stmt2->bind_param("si", $session_key, $uid);
    if (!$stmt2->execute()) return ["ok"=>false, "error"=>"session_insert_failed"];

    return ["ok"=>true, "session_key"=>$session_key];
}

function doLogout(string $key): array {
    $stmt = db()->prepare("DELETE FROM sessions WHERE session_key=?");
    if (!$stmt) return ["ok"=>false];
    $stmt->bind_param("s", $key);
    $stmt->execute();
    return ["ok"=>true];
}

function requestProcessor($request)
{
    if (!isset($request['type'])) return ["ok"=>false, "error"=>"missing_type"];
    $type = strtolower($request['type']);

    switch ($type) {
        case "login":
            return doLogin($request['username'] ?? "", $request['password'] ?? "");
        case "register":
            return doRegister($request['username'] ?? "", $request['password'] ?? "");
        case "validate_session":
            return doValidate($request['session_key'] ?? "");
        case "logout":
            return doLogout($request['session_key'] ?? "");
        default:
            return ["ok"=>false, "error"=>"unsupported_type"];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");
$server->process_requests('requestProcessor');
exit();
?>