<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class RegistrationRepository
{
    public const STATUS_ACTIVE    = 'ACTIVE';
    public const STATUS_REFUSED   = 'REFUSED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public function __construct(private PDO $pdo) {}

    public function isRefused(int $eventId, int $userId): bool
    {
        return $this->hasStatus($eventId, $userId, self::STATUS_REFUSED);
    }

    public function isActive(int $eventId, int $userId): bool
    {
        return $this->hasStatus($eventId, $userId, self::STATUS_ACTIVE);
    }

    private function hasStatus(int $eventId, int $userId, string $status): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM registrations
            WHERE event_id = :event_id
              AND user_id = :user_id
              AND status = :status
            LIMIT 1
        ");
        $stmt->execute([
            'event_id' => $eventId,
            'user_id'  => $userId,
            'status'   => $status,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public function countActive(int $eventId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM registrations
            WHERE event_id = :event_id AND status = :s
        ");
        $stmt->execute(['event_id' => $eventId, 's' => self::STATUS_ACTIVE]);
        return (int) $stmt->fetchColumn();
    }

    /*
     * Inscription "pro" (idempotente)
     * - REFUSED => interdit
     * - CANCELLED => redevient ACTIVE
     * - ACTIVE => ne fait rien
     *
     * Retourne:
     *  - true si inscription ACTIVE assurée
     *  - false si bloqué (REFUSED)
     */
    public function ensureActive(int $eventId, int $userId): bool
    {
        // Si déjà refusé => blocage définitif
        if ($this->isRefused($eventId, $userId)) {
            return false;
        }

        // UPSERT : si existe (CANCELLED) => re-ACTIVE ; si ACTIVE => no-op
        $stmt = $this->pdo->prepare("
            INSERT INTO registrations (event_id, user_id, status)
            VALUES (:event_id, :user_id, :active)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $stmt->execute([
            'event_id' => $eventId,
            'user_id'  => $userId,
            'active'   => self::STATUS_ACTIVE,
        ]);

        return true;
    }

    public function listByEvent(int $eventId, int $limit = 500): array
    {
        $limit = max(1, min(500, $limit));

        $stmt = $this->pdo->prepare("
            SELECT r.id, r.user_id, r.status, r.created_at,
                   u.email, u.pseudo, u.role
            FROM registrations r
            JOIN users u ON u.id = r.user_id
            WHERE r.event_id = :event_id
            ORDER BY r.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function refuse(int $eventId, int $userId): void
    {
        $this->setStatus($eventId, $userId, self::STATUS_REFUSED);
    }

    public function cancel(int $eventId, int $userId): bool
    {
        // Ici on veut uniquement ACTIVE -> CANCELLED
        $stmt = $this->pdo->prepare("
            UPDATE registrations
            SET status = :cancelled
            WHERE event_id = :event_id
              AND user_id = :user_id
              AND status = :active
            LIMIT 1
        ");
        $stmt->execute([
            'event_id'   => $eventId,
            'user_id'    => $userId,
            'active'     => self::STATUS_ACTIVE,
            'cancelled'  => self::STATUS_CANCELLED,
        ]);

        return $stmt->rowCount() === 1;
    }

    private function setStatus(int $eventId, int $userId, string $status): void
    {
        // nécessite UNIQUE(event_id,user_id)
        $stmt = $this->pdo->prepare("
            INSERT INTO registrations (event_id, user_id, status)
            VALUES (:event_id, :user_id, :status)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $stmt->execute(['event_id' => $eventId, 'user_id' => $userId, 'status' => $status]);
    }
}
