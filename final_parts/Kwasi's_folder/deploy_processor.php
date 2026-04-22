#!/usr/bin/php
<?php
require_once(__DIR__ . '/path.inc');
require_once(__DIR__ . '/get_host_info.inc');
require_once(__DIR__ . '/rabbitMQLib.inc');

function writeLog($app, $version, $message) {
    $logDir = __DIR__ . "/logs/$app/$version";
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/deploy.log';
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

function processDeploy($request) {
    if (!isset($request['type']) || $request['type'] !== 'deploy') {
        return ['status' => 'error', 'message' => 'Unsupported request'];
    }

    $required = ['app', 'version', 'environment', 'bundle_path', 'target_path', 'service_name'];
    foreach ($required as $field) {
        if (!isset($request[$field]) || $request[$field] === '') {
            return ['status' => 'error', 'message' => 'Missing field: ' . $field];
        }
    }

    $app = $request['app'];
    $version = $request['version'];
    $remoteBundlePath = $request['bundle_path'];
    $targetPath = $request['target_path'];
    $serviceName = $request['service_name'];
    $installScript = __DIR__ . '/installBundle.sh';

    if (!file_exists($installScript)) {
        return ['status' => 'error', 'message' => 'Install script not found on target VM'];
    }

    writeLog($app, $version, 'Deployment started for ' . $remoteBundlePath);

    $command = sprintf(
        'bash %s %s %s %s 2>&1',
        escapeshellarg($installScript),
        escapeshellarg($remoteBundlePath),
        escapeshellarg($targetPath),
        escapeshellarg($serviceName)
    );

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    foreach ($output as $line) {
        writeLog($app, $version, $line);
    }

    if ($exitCode === 0) {
        writeLog($app, $version, 'Deployment passed');
        return ['status' => 'ok', 'message' => 'Deployment passed'];
    }

    writeLog($app, $version, 'Deployment failed');
    return ['status' => 'error', 'message' => 'Deployment failed', 'exit_code' => $exitCode];
}

$server = new rabbitMQServer(__DIR__ . '/deployClient.ini', 'testServer');
echo "Deployment processor waiting for messages..." . PHP_EOL;
$server->process_requests('processDeploy');
?>