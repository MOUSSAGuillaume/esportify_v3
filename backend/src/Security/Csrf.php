<?php

declare(strict_types=1);

namespace App\Security;

final class Csrf
{
    private static function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function token(): string
    {
        self::ensureSessionStarted();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    public static function validate(?string $token): bool
    {
        self::ensureSessionStarted();

        return !empty($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals((string)$_SESSION['csrf_token'], $token);
    }
}
