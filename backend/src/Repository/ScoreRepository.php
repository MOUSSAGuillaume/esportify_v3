<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ScoreRepository
{
    public function __construct(private PDO $pdo) {}

    public function listByUser(int $userId): array
    {
        // Hypothèse la plus probable: (id, user_id, event_id, score, created_at)
        $stmt = $this->pdo->prepare("
            SELECT event_id, score, created_at
            FROM scores
            WHERE user_id = :uid
            ORDER BY created_at DESC
            LIMIT 500
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
