<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use App\Security\Csrf;
use PDOException;

final class EventRegistrationController
{
    public function __construct(
        private EventRepository $events,
        private RegistrationRepository $regs
    ) {}

    public function register(int $eventId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // CSRF
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $csrf = is_string($csrf) ? trim($csrf, " \t\n\r\0\x0B\"'") : null;
        if (!Csrf::validate($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $userId = (int)($_SESSION['user']['id'] ?? 0);

        // event must be VALIDATED
        $event = $this->events->findValidatedById($eventId);
        if (!$event) {
            http_response_code(404);
            echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // refused => cannot re-register
        if ($this->regs->isRefused($eventId, $userId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Inscription refusée pour cet événement'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // max players
        $active = $this->regs->countActive($eventId);
        if ($active >= (int)$event['max_players']) {
            http_response_code(409);
            echo json_encode(['error' => 'Événement complet'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $this->regs->createActive($eventId, $userId);
            http_response_code(201);
            echo json_encode(['message' => 'Inscription confirmée'], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            // duplicate uq_event_user
            http_response_code(409);
            echo json_encode(['error' => 'Déjà inscrit'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function list(int $eventId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $rows = $this->regs->listByEvent($eventId);
        echo json_encode(['registrations' => $rows], JSON_UNESCAPED_UNICODE);
    }

    public function refuse(int $eventId, int $userId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // CSRF
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $csrf = is_string($csrf) ? trim($csrf, " \t\n\r\0\x0B\"'") : null;

        if (!Csrf::validate($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Optionnel: empêcher de se refuser soi-même
        $me = (int)($_SESSION['user']['id'] ?? 0);
        if ($me === $userId) {
            http_response_code(400);
            echo json_encode(['error' => 'Action invalide'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->regs->refuse($eventId, $userId);

        echo json_encode(['message' => 'Joueur refusé'], JSON_UNESCAPED_UNICODE);
    }

}