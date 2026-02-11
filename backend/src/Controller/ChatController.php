<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\ChatRepository;
use App\Repository\RegistrationRepository;
use App\Security\Csrf;

final class ChatController
{
    public function __construct(
        private ChatRepository $chat,
        private RegistrationRepository $regs
    ) {}

    /**
     * GET /events/{id}/chat
     * Liste des messages d’un event
     */
    public function list(int $eventId): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // L'utilisateur doit être inscrit à l'event
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        if (!$this->regs->countActive($eventId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Inscription requise'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $messages = $this->chat->listByEvent($eventId);

        echo json_encode(['messages' => $messages], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /events/{id}/chat
     * Envoi d’un message
     */
    public function post(int $eventId): void
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

        // Utilisateur connecté
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $userId = (int)$user['id'];

        // Doit être inscrit à l’event
        if (!$this->regs->countActive($eventId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Inscription requise'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $message = trim($data['message'] ?? '');

        if ($message === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Message vide'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->chat->create($eventId, $userId, $message);

        http_response_code(201);
        echo json_encode(['message' => 'Message envoyé'], JSON_UNESCAPED_UNICODE);
    }
}