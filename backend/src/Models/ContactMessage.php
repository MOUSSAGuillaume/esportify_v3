<?php

namespace App\Models;

use App\Core\Database;

final class ContactMessage
{
    public static function listLatest(int $limit = 50): array
    {
        $pdo = Database::pdo();
        $limit = max(1, min($limit, 200));
        $stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT {$limit}");
        return $stmt->fetchAll();
    }

    public static function countUnread(): int
    {
        return (int)Database::pdo()->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    }

    public static function markRead(int $id): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
