<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class EventRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(array $data, int $organizerId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO events (organizer_id, title, description, start_at, end_at, max_players, status)
            VALUES (:organizer_id, :title, :description, :start_at, :end_at, :max_players, 'PENDING')
        ");
        $stmt->execute([
            'organizer_id' => $organizerId,
            'title' => $data['title'],
            'description' => $data['description'],
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'max_players' => (int)$data['max_players'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function listValidated(?string $sort, ?string $order): array
    {
        $sortMap = [
            'date' => 'start_at',
            'players' => 'max_players',
            'organizer' => 'organizer_id',
        ];

        $col = $sortMap[$sort ?? 'date'] ?? 'start_at';
        $dir = strtolower($order ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

        $stmt = $this->pdo->query("
            SELECT id, organizer_id, title, description, start_at, end_at, max_players, status
            FROM events
            WHERE status = 'VALIDATED'
            ORDER BY $col $dir
            LIMIT 200
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}