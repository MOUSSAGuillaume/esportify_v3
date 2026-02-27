<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\Csrf;

final class AdminUserController
{
    private const ALLOWED_ROLES = ['PLAYER','ORGANIZER','ADMIN'];

    public function __construct(private UserRepository $users) {}

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

    private function readRoleFromBody(): string
    {
        $raw = file_get_contents('php://input') ?: '';
        $ct  = $_SERVER['CONTENT_TYPE'] ?? '';

        $role = '';
        if (stripos($ct, 'application/json') !== false) {
            $json = json_decode($raw, true);
            if (is_array($json) && isset($json['role']) && is_string($json['role'])) {
                $role = $json['role'];
            }
        } else {
            $role = (string)($_POST['role'] ?? '');
            if ($role === '' && $raw !== '') {
                parse_str($raw, $parsed);
                if (is_array($parsed) && isset($parsed['role']) && is_string($parsed['role'])) {
                    $role = $parsed['role'];
                }
            }
        }

        return strtoupper(trim($role));
    }

    public function list(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $limit = (int)($_GET['limit'] ?? 200);
        echo json_encode(['users' => $this->users->listLatest($limit)], JSON_UNESCAPED_UNICODE);
    }

    public function updateRole(int $userId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireCsrfOrFail();

        $role = $this->readRoleFromBody();
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            http_response_code(422);
            echo json_encode(['error' => 'Rôle invalide'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $meId = (int)($_SESSION['user']['id'] ?? 0);
        if ($meId === $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Impossible de modifier votre propre rôle'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->users->updateRole($userId, $role)) {
            http_response_code(404);
            echo json_encode(['error' => 'Utilisateur introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['message' => 'Rôle mis à jour', 'role' => $role], JSON_UNESCAPED_UNICODE);
    }

    public function suspend(int $userId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireCsrfOrFail();

        $meId = (int)($_SESSION['user']['id'] ?? 0);
        if ($meId === $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Impossible de vous suspendre vous-même'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->users->setSuspended($userId, true)) {
            http_response_code(404);
            echo json_encode(['error' => 'Utilisateur introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['message' => 'Utilisateur suspendu'], JSON_UNESCAPED_UNICODE);
    }

    public function unsuspend(int $userId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireCsrfOrFail();

        if (!$this->users->setSuspended($userId, false)) {
            http_response_code(404);
            echo json_encode(['error' => 'Utilisateur introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['message' => 'Utilisateur réactivé'], JSON_UNESCAPED_UNICODE);
    }
}