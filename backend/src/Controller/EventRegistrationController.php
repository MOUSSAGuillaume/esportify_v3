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

    // --------------------------
    // Helpers (propreté)
    // --------------------------
    private function json(array $data, int $code = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function requireCsrf(): bool
    {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $csrf = is_string($csrf) ? trim($csrf, " \t\n\r\0\x0B\"'") : null;

        if (!Csrf::validate($csrf)) {
            $this->json(['error' => 'CSRF invalide'], 403);
            return false;
        }
        return true;
    }

    private function requireAuthUserId(): int
    {
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['error' => 'Non authentifié'], 401);
            exit;
        }
        return $userId;
    }

    private function requireOrganizerOrAdmin(): array
    {
        $u = $_SESSION['user'] ?? null;
        if (!$u) {
            $this->json(['error' => 'Non authentifié'], 401);
            exit;
        }

        $role = (string)($u['role'] ?? 'USER');
        if (!in_array($role, ['ORGANIZER', 'ADMIN'], true)) {
            $this->json(['error' => 'Accès interdit'], 403);
            exit;
        }

        return $u;
    }

    // --------------------------
    // POST /api/events/{eventId}/register
    // --------------------------
    public function register(int $eventId): void
    {
        if (!$this->requireCsrf()) return;

        $userId = $this->requireAuthUserId();

        // ⚠️ IMPORTANT : ici ton findValidatedById ne semble pas retourner started_at (dans ton repo)
        // => Solution pro: utiliser findById + vérifier status VALIDATED
        $event = $this->events->findById($eventId);
        if (!$event || ($event['status'] ?? null) !== 'VALIDATED') {
            $this->json(['error' => 'Événement introuvable'], 404);
            return;
        }

        // cannot register if started or finished
        if (!empty($event['started_at'])) {
            $this->json(['error' => 'Événement déjà démarré'], 409);
            return;
        }
        if (!empty($event['finished_at'])) {
            $this->json(['error' => 'Événement déjà terminé'], 409);
            return;
        }

        // refused => cannot re-register
        if ($this->regs->isRefused($eventId, $userId)) {
            $this->json(['error' => 'Inscription refusée pour cet événement'], 403);
            return;
        }

        // max players
        $maxPlayers = (int)($event['max_players'] ?? 0);
        if ($maxPlayers > 0) {
            $active = $this->regs->countActive($eventId);
            if ($active >= $maxPlayers) {
                $this->json(['error' => 'Événement complet'], 409);
                return;
            }
        }


        $ok = $this->regs->ensureActive($eventId, $userId);
        if (!$ok) {
            $this->json(['error' => 'Inscription refusée pour cet événement'], 403);
            return;
        }
        $this->json(['message' => 'Inscription confirmée'], 201);
        return;
    }

    // --------------------------
    // GET /api/events/{eventId}/registrations
    // --------------------------
    public function list(int $eventId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $event = $this->events->findById($eventId);
        if (!$event) {
            http_response_code(404);
            echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        $meId = (int)($_SESSION['user']['id'] ?? 0);

        if ($role !== 'ADMIN' && $meId !== (int)$event['organizer_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès interdit'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = $this->regs->listByEvent($eventId);
        echo json_encode(['registrations' => $rows], JSON_UNESCAPED_UNICODE);
    }

    // --------------------------
    // POST /api/organizer/events/{eventId}/registrations/{userId}/refuse
    // --------------------------
    public function refuse(int $eventId, int $userId): void
    {
        if (!$this->requireCsrf()) return;

        // 🔒 Sécurité: seul ORGANIZER/ADMIN
        $me = $this->requireOrganizerOrAdmin();
        $myId = (int)$me['id'];

        // empêcher de se refuser soi-même
        if ($myId === $userId) {
            $this->json(['error' => 'Action invalide'], 400);
            return;
        }

        // 🔒 Sécurité: l’event doit appartenir à l’organizer (sauf ADMIN)
        $role = (string)($me['role'] ?? 'USER');
        if ($role !== 'ADMIN') {
            $owned = $this->events->findOwnedByOrganizer($eventId, $myId);
            if (!$owned) {
                $this->json(['error' => 'Accès interdit'], 403);
                return;
            }
        }

        $this->regs->refuse($eventId, $userId);
        $this->json(['message' => 'Joueur refusé'], 200);

        $event = $this->events->findById($eventId);
        if (!$event) {
            http_response_code(404);
            echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
        $meId = (int)($_SESSION['user']['id'] ?? 0);

        if ($role !== 'ADMIN' && $meId !== (int)$event['organizer_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès interdit'], JSON_UNESCAPED_UNICODE);
            return;
        }
    }

    public function myRegistrations(int $userId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $rows = $this->events->listMyRegisteredEvents($userId);
        echo json_encode(['events' => $rows], JSON_UNESCAPED_UNICODE);
    }

    // --------------------------
    // POST /api/events/{eventId}/unregister
    // --------------------------
    public function unregister(int $eventId): void
    {
        if (!$this->requireCsrf()) return;

        $userId = $this->requireAuthUserId();

        $event = $this->events->findById($eventId);
        if (!$event) {
            $this->json(['error' => 'Événement introuvable'], 404);
            return;
        }

        if (!empty($event['started_at'])) {
            $this->json(['error' => 'Événement déjà démarré'], 409);
            return;
        }
        if (!empty($event['finished_at'])) {
            $this->json(['error' => 'Événement déjà terminé'], 409);
            return;
        }

        $ok = $this->regs->cancel($eventId, $userId);
        if (!$ok) {
            $this->json(['error' => 'Aucune inscription active trouvée'], 404);
            return;
        }

        $this->json(['message' => 'Désinscription confirmée'], 200);
    }
}
