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

}
?>