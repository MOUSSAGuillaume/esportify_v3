<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ResultRepository;

final class MeController
{
    public function __construct(private ResultRepository $results) {}

    // GET /me/results
    public function results(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $userId = (int)$_SESSION['user']['id'];
        $rows = $this->results->myResults($userId);

        echo json_encode(['results' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public function stats(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $meId = (int)$_SESSION['user']['id'];
        $stats = $this->results->statsByUser($meId);

        echo json_encode(['stats' => $stats], JSON_UNESCAPED_UNICODE);
    }
}