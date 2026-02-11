<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class RegistrationRepository
{
    public function __construct(private PDO $pdo) {}

    public function isRefused(int $eventId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM registrations
            WHERE event_id = :event_id AND user_id = :user_id AND status = 'REFUSED'
            LIMIT 1
        ");
        $stmt->execute(['event_id' => $eventId, 'user_id' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function countActive(int $eventId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM registrations
            WHERE event_id = :event_id AND status = 'ACTIVE'
        ");
        $stmt->execute(['event_id' => $eventId]);
        return (int)$stmt->fetchColumn();
    }

    public function createActive(int $eventId, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO registrations (event_id, user_id, status)
            VALUES (:event_id, :user_id, 'ACTIVE')
        ");
        $stmt->execute(['event_id' => $eventId, 'user_id' => $userId]);
    }
    public function listByEvent(int $eventId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.user_id, r.status, r.created_at,
                u.email, u.pseudo, u.role
            FROM registrations r
            JOIN users u ON u.id = r.user_id
            WHERE r.event_id = :event_id
            ORDER BY r.created_at DESC
            LIMIT 500
        ");
        $stmt->execute(['event_id' => $eventId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}