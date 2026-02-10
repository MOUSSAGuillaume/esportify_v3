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
}