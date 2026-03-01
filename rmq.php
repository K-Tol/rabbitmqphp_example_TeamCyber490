<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

function publish_auth_event(array $payload): void {
    $cfg = parse_ini_file('/etc/rmqapp.ini');
    if ($cfg === false) throw new RuntimeException('Cannot read /etc/rmqapp.ini');

    $exchange = 'web.direct';
    $queue    = 'auth.events';
    $routing  = 'auth.event';

    $conn = new AMQPStreamConnection(
        $cfg['host'], (int)$cfg['port'], $cfg['user'], $cfg['pass'], $cfg['vhost'],
        false, 'AMQPLAIN', null, 'en_US',
        3.0, 3.0
    );

    $ch = $conn->channel();
    $ch->exchange_declare($exchange, 'direct', false, true, false);
    $ch->queue_declare($queue, false, true, false, false);
    $ch->queue_bind($queue, $exchange, $routing);

    $msg = new AMQPMessage(
        json_encode($payload, JSON_UNESCAPED_SLASHES),
        ['content_type' => 'application/json', 'delivery_mode' => 2]
    );

    $ch->basic_publish($msg, $exchange, $routing);
    $ch->close();
    $conn->close();
}