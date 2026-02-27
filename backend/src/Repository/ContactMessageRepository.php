<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use PDOException;

final class ContactMessageRepository
{
    public function __construct(private PDO $pdo) {}

    public function listLatest(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        // Requête "riche" (avec subject)
        $sql1 = "SELECT id, name, email, subject, message, is_read, created_at
                 FROM contact_messages
                 ORDER BY created_at DESC
                 LIMIT {$limit}";

        // Fallback si subject n'existe pas
        $sql2 = "SELECT id, name, email, message, is_read, created_at
                 FROM contact_messages
                 ORDER BY created_at DESC
                 LIMIT {$limit}";

        try {
            return $this->pdo->query($sql1)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            try {
                return $this->pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException) {
                // Table absente => pas de 500, on renvoie vide
                return [];
            }
        }
    }

    public function markRead(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException) {
            return false;
        }
    }

    public function countUnread(): int
    {
        try {
            return (int)$this->pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }
}