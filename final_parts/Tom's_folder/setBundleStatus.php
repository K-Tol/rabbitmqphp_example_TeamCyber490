#!/usr/bin/php
<?php
// copied things from api_listener
require_once(__DIR__ . '/path.inc');
require_once(__DIR__ . '/get_host_info.inc');
require_once(__DIR__ . '/rabbitMQLib.inc');

try {
    $app = $argv[1];
    $version = $argv[2];
    $status = $argv[3];

    if ($status == "new" || $status == "failed" || $status == "passed") {
        $client = new rabbitMQClient("deploy.ini", "statusServer");
        $client -> publish([
            "type" => "status_update",
            "app" => $app,
            "version" => $version,
            "status" => $status
        ]);
    }
    else {
        echo "3rd argument needs to be: new, passed, or failed";
    }
}
catch (Exception $e) {
    echo $e->getMessage();
}
?>