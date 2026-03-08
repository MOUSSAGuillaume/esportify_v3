<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use PDOException;

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
            'title'        => $data['title'],
            'description'  => $data['description'],
            'start_at'     => $data['start_at'],
            'end_at'       => $data['end_at'],
            'max_players'  => (int)$data['max_players'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function listValidated(?string $sort, ?string $order): array
    {
        $sortMap = ['date' => 'start_at', 'players' => 'max_players', 'organizer' => 'organizer_id'];
        $col = $sortMap[$sort ?? 'date'] ?? 'start_at';
        $dir = strtolower($order ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

        $stmt = $this->pdo->query("
            SELECT id, organizer_id, title, description, start_at, end_at, max_players, status
            FROM events
            WHERE status = 'VALIDATED'
            ORDER BY $col $dir
            LIMIT 200
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listValidatedFiltered(
        ?string $q,
        ?string $from,
        ?string $to,
        ?string $sort,
        ?string $order,
        int $limit = 200
    ): array {
        $sortMap = [
            'date'      => 'start_at',
            'players'   => 'max_players',
            'organizer' => 'organizer_id',
        ];

        $col = $sortMap[$sort ?? 'date'] ?? 'start_at';
        $dir = strtolower($order ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

        $limit = max(1, min(200, $limit));

        $sql = "
        SELECT id, organizer_id, title, description, start_at, end_at, max_players, status
        FROM events
        WHERE status = 'VALIDATED'
    ";

        $params = [];

        if (is_string($q) && trim($q) !== '') {
            $sql .= " AND (title LIKE :q OR description LIKE :q) ";
            $params['q'] = '%' . trim($q) . '%';
        }

        if (is_string($from) && trim($from) !== '') {
            $sql .= " AND start_at >= :from ";
            $params['from'] = trim($from);
        }

        if (is_string($to) && trim($to) !== '') {
            $sql .= " AND start_at <= :to ";
            $params['to'] = trim($to);
        }

        $sql .= " ORDER BY {$col} {$dir} LIMIT :lim ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByStatus(string $status): array
    {
        $allowed = ['PENDING', 'VALIDATED', 'REJECTED', 'SUSPENDED'];
        if (!in_array($status, $allowed, true)) $status = 'PENDING';

        // Requête "riche" avec created_at
        $sql1 = "
            SELECT id, organizer_id, title, description, start_at, end_at, max_players, status, created_at
            FROM events
            WHERE status = :status
            ORDER BY created_at DESC
            LIMIT 200
        ";

        // Fallback si created_at n'existe pas
        $sql2 = "
            SELECT id, organizer_id, title, description, start_at, end_at, max_players, status
            FROM events
            WHERE status = :status
            ORDER BY id DESC
            LIMIT 200
        ";

        try {
            $stmt = $this->pdo->prepare($sql1);
            $stmt->execute(['status' => $status]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            $stmt = $this->pdo->prepare($sql2);
            $stmt->execute(['status' => $status]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    public function updateStatus(int $eventId, string $status): bool
    {
        $allowed = ['PENDING', 'VALIDATED', 'REJECTED', 'SUSPENDED'];
        if (!in_array($status, $allowed, true)) return false;

        $stmt = $this->pdo->prepare("UPDATE events SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $eventId]);
        return $stmt->rowCount() === 1;
    }

    public function findValidatedById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, max_players, start_at, finished_at
            FROM events
            WHERE id = :id AND status = 'VALIDATED'
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
        SELECT
            id,
            organizer_id,
            title,
            description,
            start_at,
            end_at,
            max_players,
            status,
            started_at,
            finished_at
        FROM events
        WHERE id = :id
        LIMIT 1
    ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function setStartedNow(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE events
            SET started_at = NOW()
            WHERE id = :id
              AND status = 'VALIDATED'
              AND started_at IS NULL
              AND finished_at IS NULL
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
            WHERE id = :id AND status = 'VALIDATED'
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public function countAll(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM events WHERE status = :s");
        $stmt->execute([':s' => $status]);
        return (int)$stmt->fetchColumn();
    }

    public function startEvent(int $eventId): bool
    {
        $stmt = $this->pdo->prepare("
        UPDATE events
        SET started_at = NOW()
        WHERE id = :id
          AND status = 'VALIDATED'
          AND started_at IS NULL
          AND finished_at IS NULL
          AND NOW() >= DATE_SUB(start_at, INTERVAL 30 MINUTE)
        LIMIT 1
    ");
        $stmt->execute(['id' => $eventId]);
        return $stmt->rowCount() === 1;
    }

    public function findOwnedByOrganizer(int $eventId, int $organizerId): ?array
    {
        $stmt = $this->pdo->prepare("
        SELECT *
        FROM events
        WHERE id = :id AND organizer_id = :oid
        LIMIT 1
    ");
        $stmt->execute(['id' => $eventId, 'oid' => $organizerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByOrganizerWithFilters(
        int $organizerId,
        string $q = '',
        string $status = '',
        string $sort = 'start_desc',
        int $limit = 200
    ): array {
        $limit = max(1, min(200, $limit));

        $allowedStatus = ['PENDING', 'VALIDATED', 'REJECTED', 'SUSPENDED'];
        $status = in_array($status, $allowedStatus, true) ? $status : '';

        $sortMap = [
            'start_asc'    => 'e.start_at ASC',
            'start_desc'   => 'e.start_at DESC',
            'created_desc' => 'e.id DESC',
            'players_desc' => 'e.max_players DESC',
        ];
        $orderBy = $sortMap[$sort] ?? $sortMap['start_desc'];

        $sql = "
        SELECT
            e.id, e.organizer_id, e.title, e.description,
            e.start_at, e.end_at, e.max_players, e.status, e.started_at, e.finished_at,
            COALESCE(r.active_count, 0) AS registered_count
        FROM events e
        LEFT JOIN (
            SELECT event_id, COUNT(*) AS active_count
            FROM registrations
            WHERE status = 'ACTIVE'
            GROUP BY event_id
        ) r ON r.event_id = e.id
        WHERE e.organizer_id = :oid
    ";

        $params = ['oid' => $organizerId];

        if ($status !== '') {
            $sql .= " AND e.status = :status ";
            $params['status'] = $status;
        }

        if (trim($q) !== '') {
            $sql .= " AND (e.title LIKE :q OR e.description LIKE :q) ";
            $params['q'] = '%' . trim($q) . '%';
        }

        $sql .= " ORDER BY {$orderBy} LIMIT :lim";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findDetailById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
        SELECT id, organizer_id, title, description, start_at, end_at,
               max_players, status, started_at, finished_at
        FROM events
        WHERE id = :id
        LIMIT 1
    ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listMyRegisteredEvents(int $userId, int $limit = 200): array
    {
        $limit = max(1, min(200, $limit));

        $stmt = $this->pdo->prepare("
        SELECT
            e.id, e.organizer_id, e.title, e.description,
            e.start_at, e.end_at, e.max_players, e.status, e.started_at, e.finished_at,
            COALESCE(rc.active_count, 0) AS registered_count
        FROM registrations r
        JOIN events e ON e.id = r.event_id
        LEFT JOIN (
            SELECT event_id, COUNT(*) AS active_count
            FROM registrations
            WHERE status = 'ACTIVE'
            GROUP BY event_id
        ) rc ON rc.event_id = e.id
        WHERE r.user_id = :uid
          AND r.status = 'ACTIVE'
        ORDER BY e.start_at ASC
        LIMIT :lim
    ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query("
        SELECT id, organizer_id, title, description, start_at, end_at, max_players, status, game
        FROM events
        ORDER BY start_at DESC
        LIMIT 500
    ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
