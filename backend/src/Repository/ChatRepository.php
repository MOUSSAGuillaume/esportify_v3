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

    public function addMessage(int $eventId, int $userId, string $pseudo, string $message): void
    {
        $this->col()->insertOne([
            'eventId' => $eventId,
            'userId' => $userId,
            'pseudo' => $pseudo,
            'message' => $message,
            'createdAt' => new UTCDateTime(),
        ]);
    }

    public function listMessages(int $eventId, int $limit = 100): array
    {
        $cursor = $this->col()->find(
            ['eventId' => $eventId],
            ['sort' => ['createdAt' => 1], 'limit' => $limit]
        );

        $out = [];
        foreach ($cursor as $doc) {
            $out[] = [
                'userId' => (int)($doc['userId'] ?? 0),
                'pseudo' => (string)($doc['pseudo'] ?? ''),
                'message' => (string)($doc['message'] ?? ''),
                'createdAt' => isset($doc['createdAt']) ? $doc['createdAt']->toDateTime()->format('c') : null,
            ];
        }
        return $out;
    }
}