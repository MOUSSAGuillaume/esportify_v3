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

    public function myResults(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                er.event_id,
                er.points,
                er.rank_pos,
                e.title,
                e.start_at,
                e.end_at,
                e.finished_at
            FROM event_results er
            JOIN events e ON e.id = er.event_id
            WHERE er.user_id = :user_id
            ORDER BY e.finished_at DESC, e.start_at DESC
            LIMIT 200
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function byUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                er.event_id,
                er.points,
                er.rank_pos,
                er.created_at
            FROM event_results er
            WHERE er.user_id = :uid
            ORDER BY er.created_at DESC
            LIMIT 200
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function leaderboard(
        int $limit = 50,
        int $offset = 0,
        ?string $q = null
    ): array {
        // garde-fous
        if ($limit < 1) $limit = 1;
        if ($limit > 100) $limit = 100;
        if ($offset < 0) $offset = 0;

        $sql = "
            SELECT
                u.id AS user_id,
                u.pseudo,
                SUM(er.points) AS total_points,
                COUNT(DISTINCT er.event_id) AS events_played,
                MIN(COALESCE(er.rank_pos, 999999)) AS best_rank
            FROM event_results er
            JOIN users u ON u.id = er.user_id
        ";

        $params = [];

        if (is_string($q) && trim($q) !== '') {
            $sql .= " WHERE u.pseudo LIKE :q ";
            $params['q'] = '%' . trim($q) . '%';
        }

        $sql .= "
            GROUP BY u.id, u.pseudo
            ORDER BY total_points DESC, best_rank ASC, u.id ASC
            LIMIT :lim OFFSET :off
        ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
        }

        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}