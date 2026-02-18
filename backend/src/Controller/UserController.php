<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ResultRepository;
use App\Repository\UserRepository;

final class UserController
{
    public function __construct(
        private ResultRepository $results,
        private UserRepository $users
    ) {}

    // GET /users/{id}/stats
    public function stats(int $userId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->users->exists($userId)) {
            http_response_code(404);
            echo json_encode(['error' => 'Utilisateur introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $stats = $this->results->statsByUser($userId);
        echo json_encode(['stats' => $stats], JSON_UNESCAPED_UNICODE);
    }

    // GET /users/{id}/results
    public function results(int $userId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->users->exists($userId)) {
            http_response_code(404);
            echo json_encode(['error' => 'Utilisateur introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = $this->results->byUser($userId);
        echo json_encode(['results' => $rows], JSON_UNESCAPED_UNICODE);
    }
}