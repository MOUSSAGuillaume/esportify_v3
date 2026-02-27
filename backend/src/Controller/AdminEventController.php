<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use App\Security\Csrf;

final class AdminEventController
{
    private const ALLOWED_STATUSES = ['PENDING','VALIDATED','REJECTED','SUSPENDED'];

    public function __construct(private EventRepository $events) {}

    public function list(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $status = strtoupper((string)($_GET['status'] ?? 'PENDING'));
        if (!in_array($status, self::ALLOWED_STATUSES, true)) $status = 'PENDING';

        echo json_encode(['events' => $this->events->findByStatus($status)], JSON_UNESCAPED_UNICODE);
    }

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

    private function setStatus(int $id, string $status, string $msg): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireCsrfOrFail();

        $ok = $this->events->updateStatus($id, $status);
        if (!$ok) {
            http_response_code(404);
            echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['message' => $msg], JSON_UNESCAPED_UNICODE);
    }

    public function validate(int $id): void { $this->setStatus($id, 'VALIDATED', 'Événement validé'); }
    public function reject(int $id): void   { $this->setStatus($id, 'REJECTED', 'Événement refusé'); }
    public function suspend(int $id): void  { $this->setStatus($id, 'SUSPENDED', 'Événement suspendu'); }
}