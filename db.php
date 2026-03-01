<?php
declare(strict_types=1);

function db(): PDO {
    $cfg = parse_ini_file('/etc/rmqapp-db.ini');
    if ($cfg === false) {
        throw new RuntimeException('Cannot read /etc/rmqapp-db.ini');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        (int)$cfg['port'],
        $cfg['dbname']
    );

    return new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}