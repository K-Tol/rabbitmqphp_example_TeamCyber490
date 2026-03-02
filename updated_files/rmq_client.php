<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function rmq(): rabbitMQClient {
    static $client = null;
    if ($client !== null) return $client;
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    return $client;
}