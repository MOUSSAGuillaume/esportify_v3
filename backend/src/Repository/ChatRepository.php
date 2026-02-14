<?php
declare(strict_types=1);

namespace App\Repository;

use MongoDB\Database;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;

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

    public function listMessages(int $eventId, int $limit = 100, ?string $afterId = null): array
    {
        $filter = ['eventId' => $eventId];

        if (is_string($afterId) && preg_match('/^[a-f0-9]{24}$/i', $afterId)) {
            $filter['_id'] = ['$gt' => new ObjectId($afterId)];
        }

        $cursor = $this->col()->find(
            $filter,
            ['sort' => ['createdAt' => 1], 'limit' => $limit]
        );

        $out = [];
        foreach ($cursor as $doc) {
            $out[] = [
                'id' => (string)($doc['_id'] ?? ''),
                'userId' => (int)($doc['userId'] ?? 0),
                'pseudo' => (string)($doc['pseudo'] ?? ''),
                'message' => (string)($doc['message'] ?? ''),
                'createdAt' => isset($doc['createdAt']) ? $doc['createdAt']->toDateTime()->format('c') : null,
            ];
        }
        return $out;
    }
}