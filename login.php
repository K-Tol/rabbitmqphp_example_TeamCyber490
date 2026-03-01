<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rmq.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function set_session_cookie(string $sessionKey, int $ttlSeconds): void {
    setcookie('session_key', $sessionKey, [
        'expires'  => time() + $ttlSeconds,
        'path'     => '/',
        'httponly' => true,
        'secure'   => false,   // set true when HTTPS enabled
        'samesite' => 'Lax'
    ]);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ['ok' => false, 'status' => 'error', 'message' => 'POST only']);
    }

    $type  = trim($_POST['type'] ?? '');
    $uname = trim($_POST['uname'] ?? '');
    $pword = (string)($_POST['pword'] ?? '');

    if ($type === '' || $uname === '' || $pword === '') {
        respond(400, ['ok' => false, 'status' => 'error', 'message' => 'Missing type/uname/pword']);
    }

    $pdo = db();
    $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $now = time();

    if ($type === 'register') {
        if (strlen($pword) < 10) {
            publish_auth_event(['type'=>'register_failed','username'=>$uname,'reason'=>'weak_password','ts'=>$now,'ip'=>$ip]);
            respond(400, ['ok'=>false,'status'=>'error','message'=>'Password must be at least 10 chars']);
        }

        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $check->execute([$uname]);
        if ($check->fetch()) {
            publish_auth_event(['type'=>'register_failed','username'=>$uname,'reason'=>'username_taken','ts'=>$now,'ip'=>$ip]);
            respond(409, ['ok'=>false,'status'=>'error','message'=>'Username already exists']);
        }

        $hash = password_hash($pword, PASSWORD_DEFAULT);
        $ins = $pdo->prepare('INSERT INTO users (username, pass_hash, acc_creation_time) VALUES (?, ?, ?)');
        $ins->execute([$uname, $hash, $now]);
        $uid = (int)$pdo->lastInsertId();

        publish_auth_event(['type'=>'user_registered','user_id'=>$uid,'username'=>$uname,'ts'=>$now,'ip'=>$ip,'user_agent'=>$ua]);

        respond(200, ['ok'=>true,'status'=>'registered','message'=>'Account created. Now login.']);
    }

    if ($type === 'login') {
        $sel = $pdo->prepare('SELECT id, pass_hash FROM users WHERE username = ? LIMIT 1');
        $sel->execute([$uname]);
        $user = $sel->fetch();

        if (!$user || !password_verify($pword, $user['pass_hash'])) {
            publish_auth_event(['type'=>'login_failed','username'=>$uname,'ts'=>$now,'ip'=>$ip,'user_agent'=>$ua]);
            respond(200, ['ok'=>false,'status'=>'denied','message'=>'Invalid credentials']);
        }

        $uid = (int)$user['id'];

        $sessionKey = bin2hex(random_bytes(32)); // 64 hex chars
        $ttl = 3600; // 1 hour
        $start = $now;
        $end   = $now + $ttl;

        $insS = $pdo->prepare('INSERT INTO sessions (session_key, user_id, start_time, end_time) VALUES (?, ?, ?, ?)');
        $insS->execute([$sessionKey, $uid, $start, $end]);

        set_session_cookie($sessionKey, $ttl);

        publish_auth_event(['type'=>'login_success','user_id'=>$uid,'username'=>$uname,'ts'=>$now,'ip'=>$ip,'user_agent'=>$ua]);

        respond(200, ['ok'=>true,'status'=>'authorized','message'=>'Login success','redirect'=>'welcome.php']);
    }

    respond(400, ['ok'=>false,'status'=>'error','message'=>'Unknown type (use register/login)']);

} catch (Throwable $e) {
    respond(500, ['ok'=>false,'status'=>'error','message'=>'Server error','detail'=>$e->getMessage()]);
}