<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use App\Security\Csrf;

final class EventLifecycleController
{
    public function __construct(
        private EventRepository $events,
        private RegistrationRepository $regs
    ) {}

    public function start(int $eventId): void
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

        $event = $this->events->findById($eventId);
        if (!$event) {
            http_response_code(404);
            echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // owner check
        $me = (int)($_SESSION['user']['id'] ?? 0);
        if ($me !== (int)$event['organizer_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès interdit'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (($event['status'] ?? '') !== 'VALIDATED') {
            http_response_code(409);
            echo json_encode(['error' => 'Événement non validé'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // cannot start if already finished
        if (!empty($event['finished_at'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Événement déjà terminé'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // if already started, keep behavior but return 409 (cleaner)
        if (!empty($event['started_at'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Déjà démarré'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // require at least 1 ACTIVE registration
        if ($this->regs->countActive($eventId) < 1) {
            http_response_code(409);
            echo json_encode(['error' => 'Aucun joueur inscrit'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // time rule: allowed from start_at - 30 min
        $startAt = new \DateTimeImmutable((string)$event['start_at']);
        $now = new \DateTimeImmutable('now');
        $allowedFrom = $startAt->modify('-30 minutes');

        if ($now < $allowedFrom) {
            http_response_code(409);
            echo json_encode(['error' => 'Démarrage autorisé 30 min avant'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ok = $this->events->setStartedNow($eventId);
        if (!$ok) {
            http_response_code(409);
            echo json_encode(['error' => 'Start impossible'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['message' => 'Événement démarré'], JSON_UNESCAPED_UNICODE);
    }

    public function joinStatus(int $eventId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $event = $this->events->findById($eventId);
        // event must be VALIDATED
        if (!$event || ($event['status'] ?? '') !== 'VALIDATED') {
            http_response_code(404);
            echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!empty($event['finished_at'])) {
            http_response_code(409);
            echo json_encode(['canJoin' => false, 'reason' => 'Terminé'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $me = (int)($_SESSION['user']['id'] ?? 0);

        // must be registered ACTIVE
        if (!$this->regs->isActive($eventId, $me)) {
            http_response_code(403);
            echo json_encode(['error' => 'Inscription requise'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // organizer must have started it
        if (empty($event['started_at'])) {
            echo json_encode(['canJoin' => false, 'reason' => 'Non démarré'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // join available at start time
        $startAt = new \DateTimeImmutable((string)$event['start_at']);
        $now = new \DateTimeImmutable('now');

        if ($now < $startAt) {
            echo json_encode(['canJoin' => false, 'reason' => 'Trop tôt'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['canJoin' => true, 'message' => 'Vous pouvez rejoindre'], JSON_UNESCAPED_UNICODE);
    }
}