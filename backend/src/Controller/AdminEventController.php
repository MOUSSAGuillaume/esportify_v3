<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use App\Security\Csrf;

final class AdminEventController
{
    public function __construct(private EventRepository $events) {}

    public function list(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $status = strtoupper((string)($_GET['status'] ?? 'PENDING'));
        $data = $this->events->findByStatus($status);

        echo json_encode(['events' => $data], JSON_UNESCAPED_UNICODE);
    }

    private function requireCsrf(): ?string
    {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $csrf = is_string($csrf) ? trim($csrf, " \t\n\r\0\x0B\"'") : null;

        if (!Csrf::validate($csrf)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'CSRF invalide'], JSON_UNESCAPED_UNICODE);
            return null;
        }
        return $csrf;
    }

    public function validate(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($this->requireCsrf() === null) return;

        $ok = $this->events->updateStatus($id, 'VALIDATED');
        if (!$ok) {
            http_response_code(404);
            echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['message' => 'Événement validé'], JSON_UNESCAPED_UNICODE);
    }

    public function reject(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($this->requireCsrf() === null) return;

        $ok = $this->events->updateStatus($id, 'REJECTED');
        if (!$ok) {
            http_response_code(404);
            echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['message' => 'Événement refusé'], JSON_UNESCAPED_UNICODE);
    }
}