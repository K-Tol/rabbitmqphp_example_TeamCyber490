#!/usr/bin/php
<?php
require_once(__DIR__ . '/parts/DMZ/path.inc');
require_once(__DIR__ . '/parts/DMZ/get_host_info.inc');
require_once(__DIR__ . '/parts/DMZ/rabbitMQLib.inc');

$client = new rabbitMQClient("datasource.ini","datasourceServer");

$response = $client->send_request(["type" => "sync_popular"]);
echo "response from sync_popular: ".PHP_EOL;
print_r($response);
echo "\n\n";

$response = $client->send_request(["type" => "sync_now_playing"]);
echo "response from sync_now_playing: ".PHP_EOL;
print_r($response);
echo "\n\n";

exit(0);
?>