<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChatRepository;
use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use App\Security\Csrf;

final class ChatController
{
    public function __construct(
        private ChatRepository $chat,
        private EventRepository $events,
        private RegistrationRepository $regs
    ) {}

    // GET /events/{id}/chat
    public function list(int $eventId): void
    {
        header('Content-Type: application/json; charset=utf-8');

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

        if (!empty($event['finished_at'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Événement terminé'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (empty($event['started_at'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Événement pas encore démarré'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $role = (string)($_SESSION['user']['role'] ?? '');
        $meId = (int)($_SESSION['user']['id'] ?? 0);

        // ADMIN/ORGANIZER peuvent lire, PLAYER seulement s’il est inscrit ACTIVE
        if (!in_array($role, ['ADMIN', 'ORGANIZER'], true) && !$this->regs->isActive($eventId, $meId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Inscription requise'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;

        $messages = $this->chat->listMessages($eventId, $limit);
        echo json_encode(['messages' => $messages], JSON_UNESCAPED_UNICODE);
    }

    // POST /events/{id}/chat
    public function post(int $eventId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // CSRF obligatoire
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

        if (!empty($event['finished_at'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Événement terminé'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (empty($event['started_at'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Événement pas encore démarré'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $meId = (int)($_SESSION['user']['id'] ?? 0);
        $pseudo = (string)($_SESSION['user']['pseudo'] ?? 'user');

        // seul un joueur inscrit ACTIVE peut écrire
        if (!$this->regs->isActive($eventId, $meId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Inscription requise'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $msg = trim((string)($data['message'] ?? ''));

        if ($msg === '' || mb_strlen($msg) > 500) {
            http_response_code(400);
            echo json_encode(['error' => 'Message invalide'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->chat->addMessage($eventId, $meId, $pseudo, $msg);
        http_response_code(201);
        echo json_encode(['message' => 'Envoyé'], JSON_UNESCAPED_UNICODE);
    }
}