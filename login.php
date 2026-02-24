
<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'POST only']);
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing username/password']);
        exit;
    }

    $cfg = parse_ini_file('/etc/rmqapp.ini');
    if ($cfg === false) {
        throw new RuntimeException('Cannot read /etc/rmqapp.ini');
    }

    require_once __DIR__ . '/vendor/autoload.php';

    // Security: do NOT send raw password. Only send a fingerprint.
    $password_fingerprint = hash('sha256', $password);

    $payload = [
        'type' => 'login_attempt',
        'username' => $username,
        'password_fingerprint' => $password_fingerprint,
        'ts' => time(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ];

    $conn = new PhpAmqpLib\Connection\AMQPStreamConnection(
        $cfg['host'], (int)$cfg['port'], $cfg['user'], $cfg['pass'], $cfg['vhost'],
        false, 'AMQPLAIN', null, 'en_US',
        3.0,  // connection_timeout
        3.0   // read_write_timeout
    );

    $ch = $conn->channel();

    $exchange = 'web.direct';
    $queue    = 'web.login';
    $routing  = 'auth.login';

    // Durable exchange/queue + bind
    $ch->exchange_declare($exchange, 'direct', false, true, false);
    $ch->queue_declare($queue, false, true, false, false);
    $ch->queue_bind($queue, $exchange, $routing);

    $msg = new PhpAmqpLib\Message\AMQPMessage(
        json_encode($payload, JSON_UNESCAPED_SLASHES),
        ['content_type' => 'application/json', 'delivery_mode' => 2]
    );

    $ch->basic_publish($msg, $exchange, $routing);

    $ch->close();
    $conn->close();

    echo json_encode(['ok' => true, 'status' => 'queued', 'queue' => $queue]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
PHP
