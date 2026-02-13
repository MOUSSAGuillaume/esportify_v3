<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ResultRepository
{
    public function __construct(private PDO $pdo) {}

    public function upsert(int $eventId, int $userId, int $points, ?int $rankPos): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO event_results (event_id, user_id, points, rank_pos)
            VALUES (:event_id, :user_id, :points, :rank_pos)
            ON DUPLICATE KEY UPDATE points = VALUES(points), rank_pos = VALUES(rank_pos)
        ");
        $stmt->execute([
            'event_id' => $eventId,
            'user_id' => $userId,
            'points' => $points,
            'rank_pos' => $rankPos,
        ]);
    }

    public function standings(int $eventId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT er.user_id, er.points, er.rank_pos, u.pseudo
            FROM event_results er
            JOIN users u ON u.id = er.user_id
            WHERE er.event_id = :event_id
            ORDER BY
              (er.rank_pos IS NULL) ASC,
              er.rank_pos ASC,
              er.points DESC,
              u.pseudo ASC
        ");
        $stmt->execute(['event_id' => $eventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}