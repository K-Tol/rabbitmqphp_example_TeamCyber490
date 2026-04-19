#!/usr/bin/php
<?php

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

if ($argc < 6) {
    die("Usage: php sendDeploy.php <app> <version> <environment> <bundle_path> <target_path> [service_name]\n");
}

$app         = trim($argv[1]);
$version     = trim($argv[2]);
$environment = trim($argv[3]);
$bundlePath  = trim($argv[4]);
$targetPath  = trim($argv[5]);
$serviceName = ($argc >= 7 && trim($argv[6]) !== '') ? trim($argv[6]) : 'apache2';

//  validation
if ($app === '') {
    die("App name is required.\n");
}
if ($version === '') {
    die("Version is required.\n");
}
if ($environment === '') {
    die("Environment is required.\n");
}
if ($targetPath === '') {
    die("Target path is required.\n");
}

// Validate bundle on the local machine running this script
if (!is_file($bundlePath) || !is_readable($bundlePath)) {
    die("Bundle file not found or not readable: $bundlePath\n");
}

// Build request for deploy server
$request = [
    'type'         => 'deploy',
    'app'          => $app,
    'version'      => $version,
    'environment'  => $environment,
    'bundle_path'  => $bundlePath,
    'target_path'  => $targetPath,
    'service_name' => $serviceName
];

try {
    $client = new rabbitMQClient('deploy.ini', 'deployClient');
    $response = $client->send_request($request);

    if ($response === null || $response === false) {
        die("Deploy request failed: no response received.\n");
    }

    if (is_array($response)) {
        if (isset($response['status']) && strtolower((string)$response['status']) !== 'success') {
            $message = isset($response['message']) ? $response['message'] : 'Unknown deployment error';
            die("Deployment failed: $message\n");
        }

        if (isset($response['success']) && $response['success'] === false) {
            $message = isset($response['message']) ? $response['message'] : 'Unknown deployment error';
            die("Deployment failed: $message\n");
        }
    }

    echo "Deploy request processed successfully.\n";
    print_r($response);

} catch (Throwable $e) {
    die("Failed to send deploy request: " . $e->getMessage() . "\n");
}