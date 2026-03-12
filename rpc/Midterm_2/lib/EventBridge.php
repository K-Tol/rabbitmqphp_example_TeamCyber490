<?php

declare(strict_types=1);

final class EventBridge
{
    public static function publish(string $topic, array $payload): void
    {
        $config = require __DIR__ . '/config.php';
        $url = trim((string) ($config['queue_webhook_url'] ?? ''));

        if ($url === '') {
            return;
        }

        $message = json_encode([
            'topic' => $topic,
            'payload' => $payload,
            'timestamp' => gmdate('c'),
        ], JSON_UNESCAPED_SLASHES);

        if ($message === false) {
            return;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $message,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}
