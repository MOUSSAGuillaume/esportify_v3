<?php

declare(strict_types=1);

namespace App\Middleware;

final class AuthMiddleware
{
    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private static function jsonError(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function requireLogin(): void
    {
        self::ensureSession();

        if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
            self::jsonError(401, 'Non authentifié');
        }
        if (empty($_SESSION['user']['id'])) {
            self::jsonError(401, 'Session invalide');
        }
    }

    /**
     * $roles attendus : ['ADMIN','ORGANIZER','PLAYER']
     */
    public static function requireRole(array $roles): void
    {
        self::requireLogin();

        $role = $_SESSION['user']['role'] ?? '';
        $role = strtoupper(trim((string)$role));

        $allowed = array_map(
            fn($r) => strtoupper(trim((string)$r)),
            $roles
        );

        if ($role === '' || !in_array($role, $allowed, true)) {
            self::jsonError(403, 'Accès interdit');
        }
    }

    public static function user(): array
    {
        self::requireLogin();
        return $_SESSION['user'];
    }

    public static function userId(): int
    {
        self::requireLogin();
        return (int)($_SESSION['user']['id'] ?? 0);
    }

    public static function role(): string
    {
        self::requireLogin();
        return strtoupper((string)($_SESSION['user']['role'] ?? ''));
    }
}
