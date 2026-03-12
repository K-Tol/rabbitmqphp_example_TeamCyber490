<?php

declare(strict_types=1);

final class Http
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            self::json(['error' => 'Invalid JSON body.'], 400);
        }

        return $decoded;
    }

    public static function requireMethod(string $method): void
    {
        $actual = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($actual !== strtoupper($method)) {
            self::json(['error' => 'Method not allowed.'], 405);
        }
    }
}
