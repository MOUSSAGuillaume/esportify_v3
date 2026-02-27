<?php

namespace App\Models;

use App\Core\Database;

final class User
{
    public static function findById(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("SELECT id, email, pseudo, role, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listLatest(int $limit = 200): array
    {
        $pdo = Database::pdo();
        $limit = max(1, min($limit, 500));
        $stmt = $pdo->query("SELECT id, email, pseudo, role, created_at FROM users ORDER BY created_at DESC LIMIT {$limit}");
        return $stmt->fetchAll();
    }

    public static function updateRole(int $id, string $role): bool
    {
        $allowed = ['player', 'organizer', 'admin'];
        if (!in_array($role, $allowed, true)) return false;

        $pdo = Database::pdo();
        $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
        return $stmt->execute(['role' => $role, 'id' => $id]);
    }

    public static function countAll(): int
    {
        $pdo = Database::pdo();
        return (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    public static function countByRole(string $role): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = :r");
        $stmt->execute(['r' => $role]);
        return (int)$stmt->fetchColumn();
    }
}
