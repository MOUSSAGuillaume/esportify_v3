<?php

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        // Cookies session sécurisés (adapte Secure si HTTPS)
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => false, // true en HTTPS
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }
}
