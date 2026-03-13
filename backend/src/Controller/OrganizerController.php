<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use App\Security\Csrf;

final class OrganizerController
{
    public function __construct(
        private EventRepository $events,
        private RegistrationRepository $regs
    ) {}

    private function json(array $data, int $code = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function requireCsrf(): void
    {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $csrf = is_string($csrf) ? trim($csrf, " \t\n\r\0\x0B\"'") : null;

        if (!Csrf::validate($csrf)) {
            $this->json(['error' => 'CSRF invalide'], 403);
        }
    }

    private function me(): array
    {
        $u = $_SESSION['user'] ?? null;
        if (!$u) $this->json(['error' => 'Non authentifié'], 401);
        return $u;
    }

    private function canAccessEvent(int $eventId, array $me): array
    {
        $role = strtoupper((string)($me['role'] ?? ''));
        $myId = (int)($me['id'] ?? 0);

        if ($role === 'ADMIN') {
            $event = $this->events->findById($eventId);
            if (!$event) $this->json(['error' => 'Événement introuvable'], 404);
            return $event;
        }

        // organizer => ownership obligatoire
        $event = $this->events->findOwnedByOrganizer($eventId, $myId);
        if (!$event) $this->json(['error' => 'Accès interdit'], 403);
        return $event;
    }

    // GET /organizer/events?q=&status=&sort=
    public function listMyEvents(): void
    {
        $me = $this->me();
        $role = strtoupper((string)($me['role'] ?? ''));
        $myId = (int)($me['id'] ?? 0);

        $q = (string)($_GET['q'] ?? '');
        $status = (string)($_GET['status'] ?? '');
        $sort = (string)($_GET['sort'] ?? 'start_desc');

        if ($role === 'ADMIN') {
            // Option: admin peut voir tous les events (ou tu peux limiter)
            // Ici je te propose: admin garde ses routes /admin/events, donc on renvoie 403
            $this->json(['error' => 'Utilise /admin/events'], 403);
        }

        $rows = $this->events->findByOrganizerWithFilters($myId, $q, $status, $sort);
        $this->json(['events' => $rows]);
    }

    // GET /organizer/events/{id}/registrations
    public function eventRegistrations(int $eventId): void
    {
        $me = $this->me();
        $this->canAccessEvent($eventId, $me);

        $rows = $this->regs->listByEvent($eventId);
        $this->json(['registrations' => $rows]);
    }

    // POST /organizer/events/{id}/registrations/{userId}/refuse
    public function refusePlayer(int $eventId, int $userId): void
    {
        $this->requireCsrf();
        $me = $this->me();

        $event = $this->canAccessEvent($eventId, $me);

        $myId = (int)($me['id'] ?? 0);
        if ($myId === $userId) $this->json(['error' => 'Action invalide'], 400);

        // Optionnel: si event démarré/terminé => on bloque
        if (!empty($event['started_at'])) $this->json(['error' => 'Événement déjà démarré'], 409);
        if (!empty($event['finished_at'])) $this->json(['error' => 'Événement déjà terminé'], 409);

        $this->regs->refuse($eventId, $userId);
        $this->json(['message' => 'Joueur refusé'], 200);
    }

    // POST /organizer/events/{id}/start
    public function startEvent(int $eventId): void
    {
        $this->requireCsrf();
        $me = $this->me();

        $event = $this->canAccessEvent($eventId, $me);

        // règle 30 minutes => doit être dans EventRepository::startEvent()
        $ok = $this->events->startEvent($eventId);
        if (!$ok) $this->json(['error' => 'Start non autorisé'], 403);

        $this->json(['ok' => true], 200);
    }

    public function updateEvent(int $eventId): void
    {
        $this->requireCsrf();
        $me = $this->me();

        $event = $this->canAccessEvent($eventId, $me);

        if (!empty($event['started_at'])) $this->json(['error' => 'Événement déjà démarré'], 409);
        if (!empty($event['finished_at'])) $this->json(['error' => 'Événement déjà terminé'], 409);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $title = trim((string)($data['title'] ?? ''));
        $desc  = trim((string)($data['description'] ?? ''));
        $start = (string)($data['start_at'] ?? '');
        $end   = (string)($data['end_at'] ?? '');
        $max   = (int)($data['max_players'] ?? 0);

        if ($title === '' || $desc === '' || $start === '' || $end === '' || $max <= 0) {
            $this->json(['error' => 'Champs invalides'], 400);
        }

        $this->events->updateById($eventId, [
            'title'       => $title,
            'description' => $desc,
            'start_at'    => $start,
            'end_at'      => $end,
            'max_players' => $max,
        ]);

        $this->json(['message' => 'Événement mis à jour'], 200);
    }
}
