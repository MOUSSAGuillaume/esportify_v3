<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use PDOException;

final class ContactMessageRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(string $name, string $email, string $subject, string $message): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO contact_messages (name, email, subject, message, is_read, created_at)
            VALUES (:name, :email, :subject, :message, 0, NOW())
        ");

        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':subject' => $subject,
            ':message' => $message,
        ]);
    }

    public function listLatest(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $sql1 = "
            SELECT id, name, email, subject, message, is_read, created_at
            FROM contact_messages
            ORDER BY created_at DESC
            LIMIT {$limit}
        ";

        $sql2 = "
            SELECT id, name, email, message, is_read, created_at
            FROM contact_messages
            ORDER BY created_at DESC
            LIMIT {$limit}
        ";

        try {
            return $this->pdo->query($sql1)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            try {
                return $this->pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException) {
                return [];
            }
        }
    }

    public function markRead(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE contact_messages
                SET is_read = 1
                WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException) {
            return false;
        }
    }

    public function countUnread(): int
    {
        try {
            return (int) $this->pdo
                ->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")
                ->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }
}
