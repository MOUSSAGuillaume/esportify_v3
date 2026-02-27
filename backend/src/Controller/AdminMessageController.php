<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContactMessageRepository;
use App\Security\Csrf;

final class AdminMessageController
{
    public function __construct(private ContactMessageRepository $messages) {}

    private function requireCsrfOrFail(): void
    {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $csrf = is_string($csrf) ? trim($csrf, " \t\n\r\0\x0B\"'") : null;

        if (!Csrf::validate($csrf)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'CSRF invalide'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function list(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $limit = (int)($_GET['limit'] ?? 50);
        echo json_encode(['messages' => $this->messages->listLatest($limit)], JSON_UNESCAPED_UNICODE);
    }

    public function markRead(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireCsrfOrFail();

        if (!$this->messages->markRead($id)) {
            http_response_code(404);
            echo json_encode(['error' => 'Message introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['message' => 'Message marqué comme lu'], JSON_UNESCAPED_UNICODE);
    }
}