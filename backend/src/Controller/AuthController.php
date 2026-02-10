<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use Throwable;

final class AuthController
{
    public function __construct(private AuthService $auth) {}

    public function register(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $this->auth->register(
                $data['email'] ?? '',
                $data['password'] ?? '',
                $data['pseudo'] ?? ''
            );

            http_response_code(201);
            echo json_encode(['message' => 'Utilisateur créé']);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

        public function login(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $user = $this->auth->login(
                $data['email'] ?? '',
                $data['password'] ?? ''
            );

            session_regenerate_id(true);
            $_SESSION['user'] = $user;

            echo json_encode([
                'message' => 'Connecté',
                'user' => $user
            ], JSON_UNESCAPED_UNICODE);

        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function logout(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();

        echo json_encode(['message' => 'Déconnecté'], JSON_UNESCAPED_UNICODE);
    }

    public function me(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['user' => $_SESSION['user']], JSON_UNESCAPED_UNICODE);
    }
}