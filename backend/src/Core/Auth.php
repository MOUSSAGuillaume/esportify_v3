<?php

namespace App\Core;

use App\Models\User;

final class Auth
{
    public static function user(): ?array
    {
        $id = Session::get('user_id');
        if (!$id) return null;
        return User::findById((int)$id);
    }

    public static function requireLogin(): array
    {
        $u = self::user();
        if (!$u) Response::error('Non authentifié', 401);
        return $u;
    }

    public static function requireRole(string ...$roles): array
    {
        $u = self::requireLogin();
        $role = $u['role'] ?? 'player';
        if (!in_array($role, $roles, true)) {
            Response::error('Accès interdit', 403);
        }
        return $u;
    }
}
