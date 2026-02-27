<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ContactMessageRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listLatest(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $sql = "SELECT id, name, email, subject, message, is_read, created_at
                FROM contact_messages
                ORDER BY created_at DESC
                LIMIT {$limit}";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markRead(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function countUnread(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0");
        return (int)$stmt->fetchColumn();
    }
}
