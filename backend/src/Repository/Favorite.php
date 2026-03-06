<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class FavoriteRepository
{
    public function __construct(private PDO $pdo) {}

    public function listEventIdsByUser(int $userId): array
    {
        // Adapte si ta table favorites a un autre nom de colonne.
        // Hypothèse la plus probable: (id, user_id, event_id, created_at)
        $stmt = $this->pdo->prepare("
            SELECT event_id
            FROM favorites
            WHERE user_id = :uid
            ORDER BY id DESC
            LIMIT 500
        ");
        $stmt->execute(['uid' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
