<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE email = :email LIMIT 1"
        );
        $stmt->execute(['email' => $email]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(string $email, string $hash, string $pseudo): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (email, password_hash, pseudo)
             VALUES (:email, :hash, :pseudo)"
        );

        $stmt->execute([
            'email' => $email,
            'hash' => $hash,
            'pseudo' => $pseudo,
        ]);
    }

    public function exists(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return (bool)$stmt->fetchColumn();
    }
    
    public function listLatest(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        $sql = "SELECT id, email, pseudo, role, is_active, is_suspended, created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT {$limit}";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function updateRole(int $id, string $role): bool
    {
        $stmt = $this->pdo->prepare("
        UPDATE users
        SET role = :role
        WHERE id = :id
    ");

        $stmt->execute([
            ':role' => $role,
            ':id' => $id
        ]);

        return $stmt->rowCount() > 0;
    }

    public function setSuspended(int $id, bool $suspended): bool
    {
        $stmt = $this->pdo->prepare("
        UPDATE users
        SET is_suspended = :s
        WHERE id = :id
    ");

        $stmt->execute([
            ':s' => $suspended ? 1 : 0,
            ':id' => $id
        ]);

        return $stmt->rowCount() > 0;
    }

    public function countAll(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM users")
            ->fetchColumn();
    }

    public function countByRole(string $role): int
    {
        $stmt = $this->pdo->prepare("
        SELECT COUNT(*) FROM users WHERE role = :r
    ");
        $stmt->execute([':r' => $role]);

        return (int)$stmt->fetchColumn();
    }

    public function countSuspended(): int
    {
        return (int)$this->pdo
            ->query("SELECT COUNT(*) FROM users WHERE is_suspended = 1")
            ->fetchColumn();
    }
}
