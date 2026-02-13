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

    public function findByStatus(string $status): array
    {
        $allowed = ['PENDING','VALIDATED','REJECTED','SUSPENDED'];
        if (!in_array($status, $allowed, true)) {
            $status = 'PENDING';
        }

        $stmt = $this->pdo->prepare("
            SELECT id, organizer_id, title, description, start_at, end_at, max_players, status, created_at
            FROM events
            WHERE status = :status
            ORDER BY created_at DESC
            LIMIT 200
        ");
        $stmt->execute(['status' => $status]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $eventId, string $status): bool
    {
        $allowed = ['PENDING','VALIDATED','REJECTED','SUSPENDED'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            UPDATE events
            SET status = :status
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([
            'status' => $status,
            'id' => $eventId,
        ]);

        return $stmt->rowCount() === 1;
    }
    public function findValidatedById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, max_players
            FROM events
            WHERE id = :id AND status = 'VALIDATED'
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, organizer_id, start_at, end_at, status, started_at
            FROM events
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function setStartedNow(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE events
            SET started_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public function setFinishedNow(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE events
            SET finished_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() === 1;
    }

}