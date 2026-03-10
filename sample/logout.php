<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient("testRabbitMQ.ini","testServer");
$sessionKey = $_COOKIE["session_key"] ?? '';

$client -> send_request([
    "type" => "logout",
    "session_key" => $sessionKey
    ]);

header("Location: /index.html");
exit(0);
?>