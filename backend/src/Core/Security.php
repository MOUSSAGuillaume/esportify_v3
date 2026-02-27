<?php

namespace App\Core;

final class Security
{
    public static function setSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    public static function csrfToken(): string
    {
        $token = Session::get('csrf_token');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf_token', $token);
        }
        return $token;
    }

    public static function requireCsrf(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'GET') return;

        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $postToken = $_POST['csrf_token'] ?? '';
        $token = $header ?: $postToken;

        $sessionToken = Session::get('csrf_token');
        if (!$sessionToken || !$token || !hash_equals($sessionToken, $token)) {
            Response::error('CSRF invalide', 403);
        }
    }

    public static function sanitizeString(string $s): string
    {
        return trim(strip_tags($s));
    }
}
