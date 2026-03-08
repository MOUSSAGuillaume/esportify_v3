<?php

declare(strict_types=1);

namespace App\Repository;

use MongoDB\Database;
use MongoDB\BSON\UTCDateTime;

final class ChatRepository
{
    public function __construct(private Database $db) {}

    private function col()
    {
        return $this->db->selectCollection('event_messages');
    }

    public function addMessage(
        int $eventId,
        int $userId,
        string $pseudo,
        string $role,
        string $message
    ): void {
        $this->col()->insertOne([
            'eventId'   => $eventId,
            'userId'    => $userId,
            'pseudo'    => $pseudo,
            'role'      => $role,
            'message'   => $message,
            'createdAt' => new UTCDateTime(),
        ]);
    }

    public function listMessages(int $eventId, int $limit = 100, ?string $after = null): array
    {
        $filter = ['eventId' => $eventId];

        if (is_string($after) && trim($after) !== '') {
            try {
                $dt = new \DateTimeImmutable($after);
                $ms = ((int)$dt->format('U')) * 1000;
                $filter['createdAt'] = ['$gt' => new UTCDateTime($ms)];
            } catch (\Exception $e) {
                // on ignore after invalide
            }
        }

        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;

        $cursor = $this->col()->find(
            $filter,
            ['sort' => ['createdAt' => 1], 'limit' => $limit]
        );

        $out = [];

        foreach ($cursor as $doc) {
            $out[] = [
                'userId'    => (int)($doc['userId'] ?? 0),
                'pseudo'    => (string)($doc['pseudo'] ?? ''),
                'role'      => (string)($doc['role'] ?? 'PLAYER'),
                'message'   => (string)($doc['message'] ?? ''),
                'createdAt' => isset($doc['createdAt'])
                    ? $doc['createdAt']->toDateTime()->format('c')
                    : null,
            ];
        }

        return $out;
    }
}
