<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ResultRepository;

final class UserController
{
    public function __construct(private ResultRepository $results) {}

    // GET /users/{id}/stats
    public function stats(int $userId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $stats = $this->results->statsByUser($userId);
        echo json_encode(['stats' => $stats], JSON_UNESCAPED_UNICODE);
    }

    // GET /users/{id}/results
    public function results(int $userId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $rows = $this->results->byUser($userId);
        echo json_encode(['results' => $rows], JSON_UNESCAPED_UNICODE);
    }
}