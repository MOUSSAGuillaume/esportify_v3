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
}