<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use PDOException;

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, email, password_hash, pseudo, role, is_active, is_suspended, created_at
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(string $email, string $hash, string $pseudo): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (email, password_hash, pseudo)
            VALUES (:email, :hash, :pseudo)
        ");
        $stmt->execute([
            'email'  => $email,
            'hash'   => $hash,
            'pseudo' => $pseudo,
        ]);
    }

    public function exists(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public function listLatest(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        // Requête "riche"
        $sql1 = "
            SELECT id, email, pseudo, role, is_active, is_suspended, created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT {$limit}
        ";

        // Fallback minimal
        $sql2 = "
            SELECT id, email, pseudo, role, is_suspended
            FROM users
            ORDER BY id DESC
            LIMIT {$limit}
        ";

        try {
            return $this->pdo->query($sql1)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return $this->pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    public function updateRole(int $id, string $role): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
        $stmt->execute([':role' => $role, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function setSuspended(int $id, bool $suspended): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET is_suspended = :s WHERE id = :id");
        $stmt->execute([':s' => $suspended ? 1 : 0, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    public function countByRole(string $role): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role = :r");
        $stmt->execute([':r' => $role]);
        return (int) $stmt->fetchColumn();
    }

    public function countSuspended(): int
    {
        try {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE is_suspended = 1")->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }

    /**
     * Profil (page Profile)
     * - Supporte bio si la colonne existe (fallback si non)
     */
    public function findById(int $id): ?array
    {
        $sqlWithBio = "
            SELECT id, email, pseudo, role, is_active, is_suspended, created_at, bio
            FROM users
            WHERE id = :id
            LIMIT 1
        ";

        $sqlNoBio = "
            SELECT id, email, pseudo, role, is_active, is_suspended, created_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ";

        try {
            $stmt = $this->pdo->prepare($sqlWithBio);
            $stmt->execute(['id' => $id]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            return $u ?: null;
        } catch (PDOException) {
            $stmt = $this->pdo->prepare($sqlNoBio);
            $stmt->execute(['id' => $id]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$u) return null;

            // On normalise "bio" pour éviter des if partout côté front
            $u['bio'] = null;
            return $u;
        }
    }

    public function updateProfile(int $id, string $pseudo, ?string $bio): void
    {
        $sqlWithBio = "
            UPDATE users
            SET pseudo = :pseudo, bio = :bio
            WHERE id = :id
            LIMIT 1
        ";

        $sqlNoBio = "
            UPDATE users
            SET pseudo = :pseudo
            WHERE id = :id
            LIMIT 1
        ";

        try {
            $stmt = $this->pdo->prepare($sqlWithBio);
            $stmt->execute([
                'id' => $id,
                'pseudo' => $pseudo,
                'bio' => $bio,
            ]);
        } catch (PDOException) {
            // si tu n’as pas encore ajouté la colonne bio
            $stmt = $this->pdo->prepare($sqlNoBio);
            $stmt->execute([
                'id' => $id,
                'pseudo' => $pseudo,
            ]);
        }
    }

    public function listAllLight(int $limit = 500): array
    {
        $limit = max(1, min(500, $limit));

        $stmt = $this->pdo->prepare("
        SELECT id, organizer_id, title, start_at, end_at, max_players, status, started_at, finished_at
        FROM events
        ORDER BY start_at DESC
        LIMIT :lim
    ");
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
