#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// save logs here, change to specific path for each environment
$logFile = "/home/cyber/deploy/app_logs/cluster.log";

function processLogs($logRequest) {
    global $logFile;
    if ($logRequest["type"] != null && $logRequest["type"] == "cluster_log") {
        $source = $logRequest["source"];
        $message = $logRequest["message"];
        $timestamp = $logRequest["timestamp"];
        $combinedLog = $timestamp . "From: " . $source . ": " . $message . PHP_EOL;
        file_put_contents($logFile, $combinedLog, FILE_APPEND);
        return true;
    }

}

$server = new rabbitMQServer("logging.ini","dev_dmz_log");

echo "logListener BEGIN".PHP_EOL;
$server->process_requests('processLogs');
echo "logListener END".PHP_EOL;
exit();
?>

