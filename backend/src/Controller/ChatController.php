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

    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function me(): array
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            $this->json(['error' => 'Non authentifié'], 401);
        }
        return $user;
    }

    private function requireCsrf(): void
    {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!$csrf && function_exists('getallheaders')) {
            $headers = getallheaders();
            $csrf = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? null;
        }

        $csrf = is_string($csrf) ? trim($csrf, " \t\n\r\0\x0B\"'") : null;

        if (!Csrf::validate($csrf)) {
            $this->json(['error' => 'CSRF invalide'], 403);
        }
    }

    /**
     * Event lisible pour le chat :
     * - doit exister
     * - doit être VALIDATED
     * - ne doit pas être terminé
     *
     * Avant démarrage : OK
     * Pendant direct : OK
     * Après fin : refus
     */
    private function getReadableEventOrFail(int $eventId): array
    {
        $event = $this->events->findById($eventId);

        if (!$event) {
            $this->json(['error' => 'Événement introuvable'], 404);
        }

        if (($event['status'] ?? '') !== 'VALIDATED') {
            $this->json(['error' => 'Événement non disponible'], 403);
        }

        if (!empty($event['finished_at'])) {
            $this->json(['error' => 'Le chat est fermé pour cet événement'], 403);
        }

        return $event;
    }

    /**
     * Écriture autorisée :
     * - avant démarrage : oui pour rôles autorisés
     * - pendant direct : oui pour rôles autorisés
     * - après fin : jamais (déjà bloqué avant)
     */
    private function canWriteToChat(array $event, array $user): bool
    {
        $userId = (int)($user['id'] ?? 0);
        $role = strtoupper((string)($user['role'] ?? ''));

        if ($role === 'ADMIN') {
            return true;
        }

        if ($role === 'ORGANIZER' && $userId === (int)($event['organizer_id'] ?? 0)) {
            return true;
        }

        if ($role === 'PLAYER' && $this->regs->isActive((int)$event['id'], $userId)) {
            return true;
        }

        return false;
    }

    /**
     * Message d'information UI selon l'état
     */
    private function buildInfoMessage(array $event, bool $canWrite): ?string
    {
        $started = !empty($event['started_at']);

        if ($canWrite && !$started) {
            return 'Le salon est déjà ouvert avant le lancement. Vous pouvez écrire dans le chat.';
        }

        if ($canWrite && $started) {
            return null;
        }

        if (!$started) {
            return 'Le salon est ouvert avant le lancement, mais seuls les participants autorisés peuvent écrire.';
        }

        return 'Le direct est en cours. Vous pouvez consulter le fil, mais seuls les participants autorisés peuvent écrire.';
    }

    // GET /events/{id}/chat
    // Lecture possible avant démarrage et pendant le direct
    // Après la fin : fermé
    public function list(int $eventId): void
    {
        $user = $this->me();
        $event = $this->getReadableEventOrFail($eventId);

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;

        $after = isset($_GET['after']) ? (string)$_GET['after'] : null;

        $messages = $this->chat->listMessages($eventId, $limit, $after);
        $canWrite = $this->canWriteToChat($event, $user);

        $this->json([
            'event' => [
                'id' => (int)$event['id'],
                'title' => (string)($event['title'] ?? 'Événement'),
                'status' => (string)($event['status'] ?? ''),
                'started_at' => $event['started_at'] ?? null,
                'finished_at' => $event['finished_at'] ?? null,
            ],
            'canWrite' => $canWrite,
            'infoMessage' => $this->buildInfoMessage($event, $canWrite),
            'messages' => $messages,
        ]);
    }

    // POST /events/{id}/chat
    // Avant démarrage : écriture autorisée pour rôles autorisés
    // Pendant direct : écriture autorisée pour rôles autorisés
    // Après fin : refus
    public function post(int $eventId): void
    {
        $this->requireCsrf();

        $user = $this->me();
        $event = $this->getReadableEventOrFail($eventId);

        if (!$this->canWriteToChat($event, $user)) {
            $this->json([
                'error' => 'Vous n’êtes pas autorisé à écrire dans ce chat.'
            ], 403);
        }

        if (!isset($_SESSION['chat_last_at'])) {
            $_SESSION['chat_last_at'] = [];
        }

        $last = $_SESSION['chat_last_at'][$eventId] ?? 0;
        $now = time();

        if (is_int($last) && ($now - $last) < 1) {
            $this->json(['error' => 'Trop de messages (attendez 1 seconde)'], 429);
        }

        $_SESSION['chat_last_at'][$eventId] = $now;

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $content = trim((string)($data['content'] ?? ''));

        if ($content === '' || mb_strlen($content) > 500) {
            $this->json(['error' => 'Message invalide'], 400);
        }

        $this->chat->addMessage(
            (int)$event['id'],
            (int)($user['id'] ?? 0),
            (string)($user['pseudo'] ?? $user['email'] ?? 'Utilisateur'),
            strtoupper((string)($user['role'] ?? 'PLAYER')),
            $content
        );

        $this->json(['message' => 'Message envoyé'], 201);
    }
}
