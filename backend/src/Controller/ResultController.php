<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ResultRepository;

final class ResultController
{
    public function __construct(private ResultRepository $results) {}

    // GET /me/results
    public function myResults(int $userId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $rows = $this->results->byUser($userId);
        echo json_encode(['results' => $rows], JSON_UNESCAPED_UNICODE);
    }

    // GET /leaderboard
    public function leaderboard(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;

        $rows = $this->results->leaderboard($limit);
        echo json_encode(['leaderboard' => $rows], JSON_UNESCAPED_UNICODE);
    }
}