<?php
declare(strict_types=1);

namespace App\Middleware;

final class AuthMiddleware
{
    public static function requireLogin(): void
    {
        if (empty($_SESSION['user'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Non authentifié'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();

        $role = $_SESSION['user']['role'] ?? null;
        if (!$role || !in_array($role, $roles, true)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Accès interdit'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}