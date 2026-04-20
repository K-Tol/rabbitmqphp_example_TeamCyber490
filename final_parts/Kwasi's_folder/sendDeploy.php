#!/usr/bin/php
<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 3);
chdir($projectRoot);
require_once($projectRoot . '/path.inc');
require_once($projectRoot . '/get_host_info.inc');
require_once($projectRoot . '/rabbitMQLib.inc');

function usage(): void
{
    echo "Usage:\n";
    echo "php sendDeploy.php <app> <version> <environment> <local_bundle_path> <target_path> [service_name] [remote_deploy_root]\n";
    echo "Example:\n";
    echo "php sendDeploy.php movieapp 1.0.0 qa ./deployments/movieapp/1.0.0/bundle.tar.gz /var/www/html/movieapp apache2 /opt/deployments\n";
    exit(1);
}

if ($argc < 6) {
    usage();
}

$app = $argv[1];
$version = $argv[2];
$environment = $argv[3];
$localBundlePath = $argv[4];
$targetPath = $argv[5];
$serviceName = $argc >= 7 ? $argv[6] : 'apache2';
$remoteDeployRoot = $argc >= 8 ? rtrim($argv[7], DIRECTORY_SEPARATOR) : '/opt/deployments';

if (!file_exists($localBundlePath)) {
    die("Bundle not found: $localBundlePath" . PHP_EOL);
}

$localBundlePath = realpath($localBundlePath);

if ($localBundlePath === false || !is_file($argv[4])) {
    die("Bundle is not a regular file: {$argv[4]}" . PHP_EOL);
}

$bundleContents = file_get_contents($localBundlePath);

if ($bundleContents === false) {
    die("Could not read bundle: $localBundlePath" . PHP_EOL);
}

$bundleFileName = basename($localBundlePath);
$remoteBundlePath = $remoteDeployRoot . '/' . $app . '/' . $version . '/' . $bundleFileName;
$versionDir = dirname($localBundlePath);

if (!is_dir($versionDir)) {
    die("Version directory not found: $versionDir" . PHP_EOL);
}

$statusFile = $versionDir . DIRECTORY_SEPARATOR . 'status.txt';
$metadataFile = $versionDir . DIRECTORY_SEPARATOR . 'metadata.json';
$logFile = $versionDir . DIRECTORY_SEPARATOR . 'deploy.log';

if (!file_exists($statusFile)) {
    file_put_contents($statusFile, "new\n");
}

$metadata = [
    'app' => $app,
    'version' => $version,
    'environment' => $environment,
    'local_bundle_path' => $localBundlePath,
    'remote_bundle_path' => $remoteBundlePath,
    'bundle_size' => filesize($localBundlePath),
    'bundle_sha256' => hash_file('sha256', $localBundlePath),
    'target_path' => $targetPath,
    'service_name' => $serviceName,
    'created_at' => date('Y-m-d H:i:s'),
];

file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

if (!file_exists($logFile)) {
    file_put_contents($logFile, 'Created deployment package at ' . date('Y-m-d H:i:s') . PHP_EOL);
}

$request = [
    'type' => 'deploy',
    'app' => $app,
    'version' => $version,
    'environment' => $environment,
    'bundle_path' => $remoteBundlePath,
    'bundle_name' => $bundleFileName,
    'bundle_encoding' => 'base64',
    'bundle_content' => base64_encode($bundleContents),
    'bundle_size' => filesize($localBundlePath),
    'bundle_sha256' => hash_file('sha256', $localBundlePath),
    'target_path' => $targetPath,
    'service_name' => $serviceName,
];

$client = new rabbitMQClient(__DIR__ . DIRECTORY_SEPARATOR . 'deploy.ini', 'deployClient');
$response = $client->send_request($request);

echo "Deploy request sent with uploaded bundle." . PHP_EOL;
echo "Local bundle: $localBundlePath" . PHP_EOL;
echo "Remote bundle: $remoteBundlePath" . PHP_EOL;
if ($response !== null) {
    print_r($response);
}
