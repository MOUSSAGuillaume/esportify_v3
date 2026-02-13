<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use App\Repository\ResultRepository;
use App\Security\Csrf;

final class EventResultController
{
    public function __construct(
        private EventRepository $events,
        private RegistrationRepository $regs,
        private ResultRepository $results
    ) {}

    // POST /events/{id}/finish
    public function finish(int $eventId): void
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

        // session
        if (empty($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $event = $this->events->findById($eventId);
        if (!$event) {
            http_response_code(404);
            echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $me = (int)($_SESSION['user']['id'] ?? 0);
        if ($me !== (int)$event['organizer_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès interdit'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // lifecycle checks
        if (empty($event['started_at'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Événement pas démarré'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!empty($event['finished_at'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Événement déjà terminé'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $rows = $data['results'] ?? null;

        if (!is_array($rows) || count($rows) === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'results requis'], JSON_UNESCAPED_UNICODE);
            return;
        }

        foreach ($rows as $r) {
            $userId = (int)($r['user_id'] ?? 0);
            $points = (int)($r['points'] ?? 0);
            $rank = array_key_exists('rank_pos', $r) ? (int)$r['rank_pos'] : null;

            // sécurité: seulement players inscrits ACTIVE
            if ($userId <= 0 || !$this->regs->isActive($eventId, $userId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Utilisateur non inscrit: ' . $userId], JSON_UNESCAPED_UNICODE);
                return;
            }

            $this->results->upsert($eventId, $userId, $points, $rank);
        }

        $this->events->setFinishedNow($eventId);

        echo json_encode(['message' => 'Événement terminé'], JSON_UNESCAPED_UNICODE);
    }

    // GET /events/{id}/standings
    public function standings(int $eventId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $event = $this->events->findById($eventId);
        if (!$event) {
            http_response_code(404);
            echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = $this->results->standings($eventId);
        echo json_encode(['standings' => $rows], JSON_UNESCAPED_UNICODE);
    }
}