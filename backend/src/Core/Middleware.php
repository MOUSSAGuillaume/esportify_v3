<?php

namespace App\Core;

final class Middleware
{
    public static function adminOnly(): array
    {
        return Auth::requireRole('admin');
    }

    public static function organizerOrAdmin(): array
    {
        return Auth::requireRole('organizer', 'admin');
    }
}
