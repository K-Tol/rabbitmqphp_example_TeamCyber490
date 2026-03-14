<?php
    require_once('path.inc');
    require_once('get_host_info_inc');
    require_once('rabbitMQLib.inc');

    $client = new rabbitMQClient("movieServer.ini", "movieServer");
?>

