<?php

namespace App\Models;

use App\Core\Database;

final class Event
{
    public static function countAll(): int
    {
        return (int)Database::pdo()->query("SELECT COUNT(*) FROM events")->fetchColumn();
    }

    public static function countByStatus(string $status): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE status = :s");
        $stmt->execute(['s' => $status]);
        return (int)$stmt->fetchColumn();
    }

    public static function listByStatus(string $status, int $limit = 200): array
    {
        $pdo = Database::pdo();
        $limit = max(1, min($limit, 500));
        $stmt = $pdo->prepare("SELECT * FROM events WHERE status = :s ORDER BY created_at DESC LIMIT {$limit}");
        $stmt->execute(['s' => $status]);
        return $stmt->fetchAll();
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $allowed = ['pending', 'approved', 'rejected', 'started', 'ended'];
        if (!in_array($status, $allowed, true)) return false;

        $pdo = Database::pdo();
        $stmt = $pdo->prepare("UPDATE events SET status = :s WHERE id = :id");
        return $stmt->execute(['s' => $status, 'id' => $id]);
    }
}
