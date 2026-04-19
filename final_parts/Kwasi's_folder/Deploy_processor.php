#!/usr/bin/php
<?php

$projectRoot = dirname(__DIR__, 3);
chdir($projectRoot);

require_once $projectRoot . '/path.inc';
require_once $projectRoot . '/get_host_info.inc';
require_once $projectRoot . '/rabbitMQLib.inc';

function fail_response($message, $exitCode = null)
{
    $response = [
        'status' => 'error',
        'message' => $message,
    ];

    if ($exitCode !== null) {
        $response['exit_code'] = $exitCode;
    }

    return $response;
}

function ensure_directory($path)
{
    return is_dir($path) || mkdir($path, 0775, true);
}

function write_deploy_log($versionDir, $message)
{
    if (!ensure_directory($versionDir)) {
        throw new RuntimeException('Could not create version directory: ' . $versionDir);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($versionDir . DIRECTORY_SEPARATOR . 'deploy.log', $line, FILE_APPEND);
}
function write_deploy_status($versionDir, $status)
{
    if (!ensure_directory($versionDir)) {
        throw new RuntimeException('Could not create version directory: ' . $versionDir);
    }

    file_put_contents($versionDir . DIRECTORY_SEPARATOR . 'status.txt', $status . PHP_EOL);
}

function validate_deploy_request($request)
{
    if (!is_array($request)) {
        return 'Unsupported request';
    }
    if (!isset($request['type']) || $request['type'] !== 'deploy') {
        return 'Unsupported request';
    }
    foreach (['app', 'version', 'environment', 'bundle_path', 'target_path', 'service_name'] as $field) {
        if (!isset($request[$field]) || (string)$request[$field] === '') {
            return 'Missing field: ' . $field;
        }
    }
    return null;
}
function write_uploaded_bundle($request, $bundlePath)
{
    $content = $request['bundle_content'] ?? '';
    if ((string)$content === '') {
        return null;
    }

    if (!isset($request['bundle_encoding']) 
        || $request['bundle_encoding'] !== 'base64') {
        throw new RuntimeException('Unsupported bundle encoding');
    }
    $bundleData = base64_decode((string)$content, true);
    if ($bundleData === false) {
        throw new RuntimeException('Uploaded bundle is not valid base64');
    }

    if (isset($request['bundle_size']) && strlen($bundleData)
         !== (int)$request['bundle_size']) {
        throw new RuntimeException('Uploaded bundle size does not match metadata');
    }
    if (isset($request['bundle_sha256']) && hash('sha256', $bundleData)
         !== (string)$request['bundle_sha256']) {
        throw new RuntimeException('Uploaded bundle checksum does not match metadata');
    }
    $versionDir = dirname($bundlePath);
    if (!ensure_directory($versionDir)) {
        throw new RuntimeException('Could not create version directory: ' . $versionDir);
    }
    if (file_put_contents($bundlePath, $bundleData) === false) {
        throw new RuntimeException('Could not write uploaded bundle: ' . $bundlePath);
    }

    return strlen($bundleData);
}

function run_install_script($installScript, $bundlePath, $targetPath, $serviceName)
{
    $command = sprintf(
        'bash %s %s %s %s 2>&1',
        escapeshellarg($installScript),
        escapeshellarg($bundlePath),
        escapeshellarg($targetPath),
        escapeshellarg($serviceName)
    );

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    return [
        'output' => $output,
        'exit_code' => $exitCode,
    ];
}

function processDeploy($request)
{
    $validationError = validate_deploy_request($request);

    if ($validationError !== null) {
        return fail_response($validationError);
    }

    $bundlePath = (string)$request['bundle_path'];
    $versionDir = dirname($bundlePath);
    $targetPath = (string)$request['target_path'];
    $serviceName = (string)$request['service_name'];
    $installScript = __DIR__ . DIRECTORY_SEPARATOR . 'installBundle.sh';

    if (!file_exists($installScript)) {
        return fail_response('Install script not found');
    }
    try {
        $bytesWritten = write_uploaded_bundle($request, $bundlePath);
        if ($bytesWritten !== null) {
            write_deploy_log($versionDir, 'Uploaded bundle written: ' . $bundlePath);
            write_deploy_log($versionDir, 'Uploaded bundle bytes: ' . $bytesWritten);
        }

        if (!file_exists($bundlePath)) {
            return fail_response('Bundle does not exist: ' . $bundlePath);
        }
        write_deploy_status($versionDir, 'in_progress');
        write_deploy_log($versionDir, 'Deployment started');
        write_deploy_log($versionDir, 'App: ' . $request['app']);
        write_deploy_log($versionDir, 'Version: ' . $request['version']);
        write_deploy_log($versionDir, 'Environment: ' . $request['environment']);

        $result = run_install_script($installScript, $bundlePath, $targetPath, $serviceName);
        foreach ($result['output'] as $line) {
            write_deploy_log($versionDir, $line);
        }
        if ($result['exit_code'] === 0) {
            write_deploy_status($versionDir, 'passed');
            write_deploy_log($versionDir, 'Deployment passed');
            return [
                'status' => 'ok',
                'message' => 'Deployment passed',
            ];
        }
        write_deploy_status($versionDir, 'failed');
        write_deploy_log($versionDir, 'Deployment failed');

        return fail_response('Deployment failed', $result['exit_code']);
    } catch (Throwable $e) {
        write_deploy_status($versionDir, 'failed');
        write_deploy_log($versionDir, 'Deployment failed: ' . $e->getMessage());

        return fail_response($e->getMessage());
    }
}
$server = new rabbitMQServer(__DIR__ . DIRECTORY_SEPARATOR . 'deploy.ini', 'deployServer');
echo 'Deployment processor waiting for messages...' . PHP_EOL;
$server->process_requests('processDeploy');
