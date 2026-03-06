<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\RegistrationRepository;
use App\Repository\EventRepository;
use App\Middleware\AuthMiddleware;
use App\Security\Csrf;
use Throwable;

final class ProfileController
{
    public function __construct(
        private UserRepository $users,
        private RegistrationRepository $registrations,
        private EventRepository $events
    ) {}

    private function requireAuth(): int
    {
        // AuthMiddleware démarre la session et check la présence de $_SESSION['user']['id']
        return AuthMiddleware::userId();
    }

    public function me(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $uid = $this->requireAuth();

        try {
            $user = $this->users->findById($uid);
            if (!$user) {
                http_response_code(404);
                echo json_encode(['error' => 'Utilisateur introuvable'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $regs = $this->registrations->listByUser($uid);
            $allEvents = $this->events->listAllLight(500);

            echo json_encode([
                'user' => $user,
                'registrations' => $regs,
                'events' => $allEvents,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(
                ['error' => 'Erreur serveur', 'details' => $e->getMessage()],
                JSON_UNESCAPED_UNICODE
            );
        }
    }

    public function updateMe(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $uid = $this->requireAuth();

        // CSRF attendu dans le header : X-CSRF-Token
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $data = json_decode((string)file_get_contents('php://input'), true);

            $pseudo = trim((string)($data['pseudo'] ?? ''));
            $bio = array_key_exists('bio', $data ?? []) ? trim((string)$data['bio']) : null;

            if ($pseudo === '' || mb_strlen($pseudo) < 3 || mb_strlen($pseudo) > 30) {
                http_response_code(400);
                echo json_encode(['error' => 'Pseudo invalide (3 à 30 caractères).'], JSON_UNESCAPED_UNICODE);
                return;
            }

            if ($bio !== null && mb_strlen($bio) > 500) {
                http_response_code(400);
                echo json_encode(['error' => 'Bio trop longue (max 500).'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $this->users->updateProfile($uid, $pseudo, $bio);

            echo json_encode(['message' => 'Profil mis à jour'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}
